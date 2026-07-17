<?php
// Inline "current status" query — replaces the optional hsp_v_current_status view.
// Joins devices + schemes + latest snapshot and derives state / sim_days_left.
// Defined here (loaded before view.php) so the listing AND detail page can reuse it.
function hsp_status_sql()
{
	// office name is joined in here so the listing no longer runs a per-row
	// lookup against tbl_establishment_office (that was one query per row).
	return "SELECT
		d.id AS device_id, d.site_id, d.device_code, d.tail_label, d.tail_no, d.office_id,
		s.code AS scheme_code, s.name AS scheme_name, s.dashboard_url,
		eo.e_office_name AS office_name,
		st.site_name, st.latitude, st.longitude, st.sim_im_no, st.sim_validity_upto,
		st.has_water AS last_has_water, st.water_status AS last_water_status,
		st.battery_voltage AS last_battery, st.reported_at AS last_reported_at,
		st.polled_at AS last_polled_at, COALESCE(st.is_online,0) AS last_is_online,
		CASE WHEN st.sim_validity_upto IS NULL THEN NULL
		     ELSE DATEDIFF(st.sim_validity_upto, CURDATE()) END AS sim_days_left,
		CASE
			WHEN st.device_id IS NULL OR COALESCE(st.is_online,0)=0 THEN 'offline'
			WHEN st.reported_at IS NULL THEN 'unknown'
			WHEN st.reported_at < (NOW() - INTERVAL 30 MINUTE) THEN 'stale'
			ELSE 'online'
		END AS state
	FROM hsp_devices d
	JOIN hsp_schemes s ON s.id = d.scheme_id
	LEFT JOIN hsp_device_status st ON st.device_id = d.id
	LEFT JOIN tbl_establishment_office eo ON eo.e_office_id = d.office_id
	WHERE d.is_active = 1";
}

// Office-based access control, shared by the listing and the calendar so a
// restricted operator sees the same tails everywhere. Returns a SQL fragment
// like " AND <col> IN (...)" (empty when the group has no office restriction).
function liOfficeAccessWhere($col = 'office_id')
{
	global $database;

	$groups = isset($_SESSION['admin_groupid']) ? explode(",", (string) $_SESSION['admin_groupid']) : array();
	$ids = null;

	if (in_array(7, $groups)) {
		$ids = $database->filter($_SESSION['admin_establishment_office']);
	} elseif (in_array(8, $groups)) {
		$ids = getAllMappedOffices($_SESSION['admin_establishment_office'], $database);
	} else {
		return ""; // not an office-scoped group — no restriction
	}

	$ids = trim((string) $ids);
	if ($ids === '') {
		return " AND 1 = 0"; // scoped operator with no offices → sees nothing
	}
	return " AND " . $col . " IN (" . $ids . ")";
}

function showList()
{
	global $database, $pagingObject;

	// Source of truth is the latest snapshot filled by the cron poller (poll_devices.php).
	$status_sql = "(" . hsp_status_sql() . ") v";
	$sql = "SELECT * FROM " . $status_sql . " WHERE 1";
	$where = "";
	$summary_where = "";

	// Access control applies to BOTH the list and the summary cards, so the
	// card counts match the tails a scoped operator can actually see.
	$access = liOfficeAccessWhere('office_id');
	$where .= $access;
	$summary_where .= $access;

	if (isset($_GET['scheme']) && $_GET['scheme'] !== "") {
		$filter = " AND scheme_code = '" . $database->filter($_GET['scheme']) . "'";
		$where .= $filter;
		$summary_where .= $filter;
	}

	if (isset($_GET['state']) && $_GET['state'] !== "") {
		// online / stale / offline / unknown
		$where .= " AND state = '" . $database->filter($_GET['state']) . "'";
	}

	if (isset($_GET['water']) && $_GET['water'] !== "") {
		// 1 = flowing, 0 = no water
		$where .= " AND last_has_water = '" . $database->filter($_GET['water']) . "'";
	}

	if (isset($_GET['device_code']) && trim($_GET['device_code']) != "") {
		$where .= " AND device_code LIKE '%" . $database->filter(trim($_GET['device_code'])) . "%'";
	}

	$filtered_sql = $sql . $where;
	// Only the summary WHERE is reused later (by the status cards).
	$_SESSION['hsp_summary_sql'] = $sql . $summary_where;

	$list_sql = $filtered_sql . " ORDER BY site_id";

	$pagingObject->setMaxRecords(PAGELIMIT);
	$paged_sql = $pagingObject->setQuery($list_sql);
	$results = $database->get_results($paged_sql);
	$results = is_array($results) ? $results : array();

	showRecordsListing($results);
}

/* ---- calendar data helpers --------------------------------------------------
   Readings are timestamped with COALESCE(reported_at, fetched_at):
   the device's own report time when it sent one, else the server poll time.
   ---------------------------------------------------------------------------- */

// All active devices (office-scoped), scheme-ordered — the rows for both the
// month matrix and the hourly matrix. Cached per request so one query serves
// every calendar view.
function liCalDevices()
{
	global $database;
	static $cache = null;
	if ($cache !== null) {
		return $cache;
	}
	$access = liOfficeAccessWhere('d.office_id');
	$rows = $database->get_results("SELECT d.id, d.site_id, d.device_code, d.tail_label, d.tail_no,
			s.code AS scheme_code, s.name AS scheme_name,
			st.site_name
		FROM hsp_devices d
		JOIN hsp_schemes s ON s.id = d.scheme_id
		LEFT JOIN hsp_device_status st ON st.device_id = d.id
		WHERE d.is_active = 1" . $access . "
		ORDER BY s.code ASC, d.tail_no ASC");
	$cache = is_array($rows) ? $rows : array();
	return $cache;
}

// Per-device, per-day water availability for one month, used to draw the
// month calendar as a % of the day water was available (over a full 24 h).
// An hour counts as a "water hour" when any reading in that hour reported
// water. Returns array[device_id][day 1..31] =
//   hours       = distinct hours that had at least one reading that day
//   water_hours = of those, how many had water present
// The view turns water_hours into a percentage: round(water_hours / 24 * 100).
function liCalMonthPercent($year, $month)
{
	global $database;

	$start = sprintf('%04d-%02d-01', $year, $month);
	$end = date('Y-m-d', strtotime($start . ' +1 month'));

	// OR-split so MariaDB can use the reported_at / fetched_at indexes.
	$from = $database->filter($start) . " 00:00:00";
	$to = $database->filter($end) . " 00:00:00";

	// Inner: collapse each device/day/hour to one row flagged 1 if water was
	// present at any point that hour. Outer: per device/day count the hours
	// with data and the hours with water.
	$sql = "SELECT device_id, d,
			COUNT(*) AS hours,
			COALESCE(SUM(hw), 0) AS water_hours
		FROM (
			SELECT device_id,
				DAY(COALESCE(reported_at, fetched_at)) AS d,
				HOUR(COALESCE(reported_at, fetched_at)) AS h,
				MAX(CASE WHEN has_water = 1 THEN 1 ELSE 0 END) AS hw
			FROM hsp_readings
			WHERE ((reported_at >= '" . $from . "' AND reported_at < '" . $to . "')
				OR (reported_at IS NULL AND fetched_at >= '" . $from . "' AND fetched_at < '" . $to . "'))
			GROUP BY device_id, d, h
		) per_hour
		GROUP BY device_id, d";

	$rows = $database->get_results($sql);
	$out = array();
	if (is_array($rows)) {
		foreach ($rows as $r) {
			$out[(int) $r['device_id']][(int) $r['d']] = $r;
		}
	}
	return $out;
}

// Device x hour x quarter-hour water matrix for one date (Y-m-d), so the daily
// calendar can split each hour into 4 x 15-minute segments. Every reading is
// bucketed into its hour (0..23) and quarter (0..3 = :00/:15/:30/:45); a
// quarter is a "water quarter" when any reading in it reported water.
// Returns array[device_id][hour 0..23][quarter 0..3] =
//   water    = 1 if any reading in the slot reported water
//   nowater  = 1 if any reading in the slot reported no water
//   readings = number of readings in the slot
function liCalDayQuarters($date)
{
	global $database;

	$next = date('Y-m-d', strtotime($date . ' +1 day'));

	// OR-split so the reported_at / fetched_at indexes stay usable.
	$from = $database->filter($date) . " 00:00:00";
	$to = $database->filter($next) . " 00:00:00";

	$sql = "SELECT device_id,
			HOUR(COALESCE(reported_at, fetched_at)) AS h,
			FLOOR(MINUTE(COALESCE(reported_at, fetched_at)) / 15) AS q,
			MAX(CASE WHEN has_water = 1 THEN 1 ELSE 0 END) AS water,
			MAX(CASE WHEN has_water = 0 THEN 1 ELSE 0 END) AS nowater,
			COUNT(*) AS readings
		FROM hsp_readings
		WHERE ((reported_at >= '" . $from . "' AND reported_at < '" . $to . "')
			OR (reported_at IS NULL AND fetched_at >= '" . $from . "' AND fetched_at < '" . $to . "'))
		GROUP BY device_id, h, q";

	$rows = $database->get_results($sql);
	$out = array();
	if (is_array($rows)) {
		foreach ($rows as $r) {
			$out[(int) $r['device_id']][(int) $r['h']][(int) $r['q']] = $r;
		}
	}
	return $out;
}
?>
