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

/* ---- dashboard helpers ------------------------------------------------------
   Header + filters + summary tiles for the overview dashboard (view_dashboard.php).
   ---------------------------------------------------------------------------- */

// The office ids the current operator is allowed to see, used to scope the
// Circle / Division / Sub-Division pickers. Mirrors liOfficeAccessWhere():
//   group 7 → only their own office; group 8 → their office + all descendants
//   (via getAllMappedOffices); anyone else → null (no restriction).
function liHierScopeIds()
{
	global $database;
	$groups = isset($_SESSION['admin_groupid']) ? explode(",", (string) $_SESSION['admin_groupid']) : array();
	$office = isset($_SESSION['admin_establishment_office']) ? $_SESSION['admin_establishment_office'] : '';

	if (in_array(7, $groups)) {
		return liCsvToIds($office);
	}
	if (in_array(8, $groups)) {
		$csv = function_exists('getAllMappedOffices') ? getAllMappedOffices($office, $database) : $office;
		return liCsvToIds($csv);
	}
	return null; // unrestricted
}

// "1,2,foo,3" → array(1,2,3) (positive ints only).
function liCsvToIds($csv)
{
	$out = array();
	foreach (explode(',', (string) $csv) as $v) {
		$v = (int) trim($v);
		if ($v > 0) {
			$out[] = $v;
		}
	}
	return array_values(array_unique($out));
}

// Turn a scope id list into a SQL " AND <col> IN (...)" fragment:
//   null  → "" (no restriction)   empty → " AND 1 = 0" (scoped, nothing visible)
function liScopeClause($col, $ids)
{
	if ($ids === null) {
		return '';
	}
	if (empty($ids)) {
		return ' AND 1 = 0';
	}
	return ' AND ' . $col . ' IN (' . implode(',', array_map('intval', $ids)) . ')';
}

// Office names for a set of ids, from tbl_establishment_office. The hierarchy
// mapping tables only store ids (as CSV), so the pickers look names up here.
function liOfficeNames($ids)
{
	global $database;
	$clean = array();
	foreach ((array) $ids as $v) {
		$v = (int) $v;
		if ($v > 0) {
			$clean[$v] = $v;
		}
	}
	if (empty($clean)) {
		return array();
	}
	$rows = $database->get_results("SELECT e_office_id, e_office_name
		FROM tbl_establishment_office
		WHERE e_office_id IN (" . implode(',', $clean) . ")");
	$map = array();
	if (is_array($rows)) {
		foreach ($rows as $r) {
			$map[(int) $r['e_office_id']] = $r['e_office_name'];
		}
	}
	return $map;
}

// Circle picker. tbl_division_mapping stores one row per circle (circle_id is a
// single id, circle_name a label) with its divisions as a CSV in division_id.
// Office-scoped via liHierScopeIds().
function liCircleOptions()
{
	global $database;
	static $cache = null;
	if ($cache !== null) {
		return $cache;
	}
	$scope = liHierScopeIds();
	$rows = $database->get_results("SELECT circle_id, circle_name
		FROM tbl_division_mapping
		WHERE status = 1");
	$rows = is_array($rows) ? $rows : array();

	$seen = array();
	$out = array();
	foreach ($rows as $r) {
		$c = (int) trim((string) $r['circle_id']);
		if ($c <= 0 || isset($seen[$c])) {
			continue;
		}
		if ($scope !== null && !in_array($c, $scope)) {
			continue;
		}
		$seen[$c] = 1;
		$name = trim((string) $r['circle_name']);
		$out[] = array('id' => $c, 'name' => $name !== '' ? $name : ('Circle #' . $c));
	}
	usort($out, function ($a, $b) {
		return strcasecmp($a['name'], $b['name']);
	});
	$cache = $out;
	return $cache;
}

// Division picker. tbl_division_mapping.division_id is a CSV of division office
// ids under circle_id — split it so every division is its own option carrying
// its parent circle (data-circle). Names come from tbl_establishment_office.
function liDivisionHierOptions()
{
	global $database;
	static $cache = null;
	if ($cache !== null) {
		return $cache;
	}
	$scope = liHierScopeIds();
	$rows = $database->get_results("SELECT circle_id, division_id
		FROM tbl_division_mapping
		WHERE status = 1");
	$rows = is_array($rows) ? $rows : array();

	$parent = array(); // division id => circle id
	foreach ($rows as $r) {
		$circle = (int) trim((string) $r['circle_id']);
		foreach (explode(',', (string) $r['division_id']) as $d) {
			$d = (int) trim($d);
			if ($d <= 0) {
				continue;
			}
			if ($scope !== null && !in_array($d, $scope)) {
				continue;
			}
			$parent[$d] = $circle;
		}
	}

	$names = liOfficeNames(array_keys($parent));
	$out = array();
	foreach ($parent as $d => $circle) {
		$out[] = array('id' => $d, 'parent' => $circle, 'name' => isset($names[$d]) ? $names[$d] : ('Office #' . $d));
	}
	usort($out, function ($a, $b) {
		return strcasecmp($a['name'], $b['name']);
	});
	$cache = $out;
	return $cache;
}

// Sub-Division picker. tbl_sub_division_mapping.sub_division_id is a CSV of
// sub-division office ids under division_id — split it so each is its own option
// carrying its parent division (data-division). Names from tbl_establishment_office.
function liSubDivisionOptions()
{
	global $database;
	static $cache = null;
	if ($cache !== null) {
		return $cache;
	}
	$scope = liHierScopeIds();
	$rows = $database->get_results("SELECT division_id, sub_division_id
		FROM tbl_sub_division_mapping
		WHERE status = 1");
	$rows = is_array($rows) ? $rows : array();

	$parent = array(); // sub-division id => division id
	foreach ($rows as $r) {
		$div = (int) trim((string) $r['division_id']);
		foreach (explode(',', (string) $r['sub_division_id']) as $s) {
			$s = (int) trim($s);
			if ($s <= 0) {
				continue;
			}
			if ($scope !== null && !in_array($s, $scope)) {
				continue;
			}
			$parent[$s] = $div;
		}
	}

	$names = liOfficeNames(array_keys($parent));
	$out = array();
	foreach ($parent as $s => $div) {
		$out[] = array('id' => $s, 'parent' => $div, 'name' => isset($names[$s]) ? $names[$s] : ('Office #' . $s));
	}
	usort($out, function ($a, $b) {
		return strcasecmp($a['name'], $b['name']);
	});
	$cache = $out;
	return $cache;
}

// A chosen hierarchy level → its own office id + every descendant office id, so
// a Circle / Division / Sub-Division choice filters devices by office_id IN(...).
// Reuses the app-wide getAllMappedOffices() (which expands downward from any level).
function liOfficeDescendants($office_id)
{
	global $database;
	$office_id = (int) $office_id;
	if ($office_id <= 0) {
		return array();
	}
	if (function_exists('getAllMappedOffices')) {
		return liCsvToIds(getAllMappedOffices($office_id, $database));
	}
	return array($office_id);
}

// When the snapshot was last refreshed by the poller cron.
function liDashboardLastRefresh()
{
	global $database;
	$rows = $database->get_results("SELECT MAX(COALESCE(updated_at, polled_at, reported_at)) AS last_refresh FROM hsp_device_status");
	return (is_array($rows) && isset($rows[0]['last_refresh'])) ? $rows[0]['last_refresh'] : null;
}

// Build the dashboard filter WHERE from the request. Returns " AND ..." fragments
// applied on top of the current-status set. Circle / Division / Sub-Division each
// expand (via getAllMappedOffices) to their descendant office ids and match on
// office_id, so picking any level narrows to every outlet under it.
function liDashboardWhere()
{
	global $database;
	$w = '';

	// Office hierarchy — each chosen level intersects the previous one.
	foreach (array('circle', 'division', 'subdiv') as $lvl) {
		if (isset($_GET[$lvl]) && $_GET[$lvl] !== '') {
			$ids = liOfficeDescendants($_GET[$lvl]);
			$w .= empty($ids) ? " AND 1 = 0" : (" AND office_id IN (" . implode(',', $ids) . ")");
		}
	}

	if (isset($_GET['scheme']) && $_GET['scheme'] !== '') {
		$w .= " AND scheme_code = '" . $database->filter($_GET['scheme']) . "'";
	}
	if (isset($_GET['sensor']) && $_GET['sensor'] !== '') {
		$w .= " AND state = '" . $database->filter($_GET['sensor']) . "'";
	}
	if (isset($_GET['rd']) && trim($_GET['rd']) !== '') {
		$rd = $database->filter(trim($_GET['rd']));
		$w .= " AND (tail_label LIKE '%" . $rd . "%' OR site_id LIKE '%" . $rd . "%')";
	}

	return $w;
}

// Summary tiles for the dashboard: total sensors, water present / not present,
// offline (incl. no-data), sensors that reported today, and distinct schemes.
// $where is the filter fragment from liDashboardWhere(); office access control
// is added here so every tile respects the operator's scope.
function liDashboardStats($where = '')
{
	global $database;

	$access = liOfficeAccessWhere('office_id');
	$base = "SELECT * FROM (" . hsp_status_sql() . ") v WHERE 1" . $access . $where;

	$agg = $database->get_results("SELECT
			COUNT(*) AS total,
			COALESCE(SUM(state NOT IN ('offline','unknown') AND last_has_water = 1), 0) AS water_present,
			COALESCE(SUM(state NOT IN ('offline','unknown') AND (last_has_water = 0 OR last_has_water IS NULL)), 0) AS water_absent,
			COALESCE(SUM(state IN ('offline','unknown')), 0) AS offline,
			COALESCE(SUM(DATE(COALESCE(last_reported_at, last_polled_at)) = CURDATE()), 0) AS received_today,
			COUNT(DISTINCT scheme_code) AS active_schemes
		FROM (" . $base . ") d");

	$agg = (is_array($agg) && isset($agg[0])) ? $agg[0] : array();

	return array(
		'total'          => isset($agg['total']) ? (int) $agg['total'] : 0,
		'water_present'  => isset($agg['water_present']) ? (int) $agg['water_present'] : 0,
		'water_absent'   => isset($agg['water_absent']) ? (int) $agg['water_absent'] : 0,
		'offline'        => isset($agg['offline']) ? (int) $agg['offline'] : 0,
		'received_today' => isset($agg['received_today']) ? (int) $agg['received_today'] : 0,
		'active_schemes' => isset($agg['active_schemes']) ? (int) $agg['active_schemes'] : 0,
	);
}

// Per-office breakdown for the report table and the export: one row per office
// (division) in scope, with the same water/offline buckets as the summary tiles.
// Grouping is by the device's office_id — so a Circle/Division selection shows
// each underlying office separately, not one lumped circle total. Same filters
// and access control as the tiles.
function liDashboardByOffice($where = '')
{
	global $database;

	$access = liOfficeAccessWhere('office_id');
	$base = "SELECT * FROM (" . hsp_status_sql() . ") v WHERE 1" . $access . $where;

	$rows = $database->get_results("SELECT
			office_id,
			office_name,
			COUNT(*) AS total,
			COALESCE(SUM(state NOT IN ('offline','unknown') AND last_has_water = 1), 0) AS water_present,
			COALESCE(SUM(state NOT IN ('offline','unknown') AND (last_has_water = 0 OR last_has_water IS NULL)), 0) AS water_absent,
			COALESCE(SUM(state IN ('offline','unknown')), 0) AS offline,
			COALESCE(SUM(DATE(COALESCE(last_reported_at, last_polled_at)) = CURDATE()), 0) AS received_today
		FROM (" . $base . ") d
		GROUP BY office_id, office_name
		ORDER BY office_name ASC");

	return is_array($rows) ? $rows : array();
}

// Sensor locations for the State Map View: every in-scope device that has real
// coordinates, with the fields the marker popup needs. Same filters + access
// control as the rest of the dashboard.
function liMapMarkers($where = '')
{
	global $database;

	$access = liOfficeAccessWhere('office_id');
	$base = "SELECT * FROM (" . hsp_status_sql() . ") v WHERE 1" . $access . $where;

	$rows = $database->get_results("SELECT
			device_id, site_id, tail_label, scheme_name, office_name,
			state, last_has_water, last_water_status, last_reported_at,
			latitude, longitude
		FROM (" . $base . ") d
		WHERE latitude IS NOT NULL AND longitude IS NOT NULL
			AND latitude <> 0 AND longitude <> 0
		ORDER BY site_id ASC");

	return is_array($rows) ? $rows : array();
}

// Scheme x Division breakdown for the Scheme-wise Status tab: one row per
// (scheme, division) with the water / offline buckets. Same filters + access
// control as the rest of the dashboard.
function liSchemeWiseStatus($where = '')
{
	global $database;

	$access = liOfficeAccessWhere('office_id');
	$base = "SELECT * FROM (" . hsp_status_sql() . ") v WHERE 1" . $access . $where;

	$rows = $database->get_results("SELECT
			scheme_name, scheme_code, office_name,
			COUNT(*) AS total,
			COALESCE(SUM(state NOT IN ('offline','unknown') AND last_has_water = 1), 0) AS water_present,
			COALESCE(SUM(state NOT IN ('offline','unknown') AND (last_has_water = 0 OR last_has_water IS NULL)), 0) AS water_absent,
			COALESCE(SUM(state IN ('offline','unknown')), 0) AS offline
		FROM (" . $base . ") d
		GROUP BY scheme_code, scheme_name, office_name
		ORDER BY scheme_name ASC, office_name ASC");

	return is_array($rows) ? $rows : array();
}

// How long a sensor must stay in a bad state before it counts as "critical".
// Change these to re-tune the thresholds.
if (!defined('HSP_CRIT_OFFLINE_HOURS')) {
	define('HSP_CRIT_OFFLINE_HOURS', 8);  // offline / no data for >= 8 hours
}
if (!defined('HSP_CRIT_WATER_HOURS')) {
	define('HSP_CRIT_WATER_HOURS', 4);    // water absent for >= 4 hours
}

// Critical Locations tab: only the sensors that need attention — grey (offline /
// no data for >= HSP_CRIT_OFFLINE_HOURS) and red (water absent for >=
// HSP_CRIT_WATER_HOURS). Green (water present) and short blips are excluded.
// Offline first, then the longest-silent. Same filters + access control.
function liCriticalLocations($where = '')
{
	global $database;

	$access = liOfficeAccessWhere('office_id');
	$base = "SELECT * FROM (" . hsp_status_sql() . ") v WHERE 1" . $access . $where;

	$offlineHrs = (int) HSP_CRIT_OFFLINE_HOURS;
	$waterHrs   = (int) HSP_CRIT_WATER_HOURS;

	// Inner: derive last_seen (last contact) and water_since (last time water was
	// reported present). Outer: keep only sensors past the critical threshold —
	//   offline      : no data for >= $offlineHrs hours (or never seen)
	//   water absent : water last present >= $waterHrs hours ago (or never present)
	$rows = $database->get_results("SELECT * FROM (
			SELECT
				device_id, site_id, scheme_name, tail_label, office_name,
				state, last_has_water, last_reported_at, last_polled_at,
				COALESCE(last_reported_at, last_polled_at) AS last_seen,
				(SELECT MAX(COALESCE(r.reported_at, r.fetched_at))
					FROM hsp_readings r
					WHERE r.device_id = d.device_id AND r.has_water = 1) AS water_since
			FROM (" . $base . ") d
		) c
		WHERE
			( c.state IN ('offline','unknown')
				AND (c.last_seen IS NULL OR c.last_seen <= (NOW() - INTERVAL " . $offlineHrs . " HOUR)) )
			OR
			( c.state NOT IN ('offline','unknown')
				AND (c.last_has_water = 0 OR c.last_has_water IS NULL)
				AND (c.water_since IS NULL OR c.water_since <= (NOW() - INTERVAL " . $waterHrs . " HOUR)) )
		ORDER BY (c.state IN ('offline','unknown')) DESC, c.last_seen ASC");

	return is_array($rows) ? $rows : array();
}

// Devices for the Water Presence Trend sensor picker — scoped + filtered like
// the rest of the dashboard, with enough status for the picked-sensor header.
function liTrendDeviceOptions($where = '')
{
	global $database;

	$access = liOfficeAccessWhere('office_id');
	$base = "SELECT * FROM (" . hsp_status_sql() . ") v WHERE 1" . $access . $where;

	$rows = $database->get_results("SELECT device_id, site_id, tail_label, scheme_name, office_name,
			state, last_has_water, last_reported_at
		FROM (" . $base . ") d
		ORDER BY site_id ASC");

	return is_array($rows) ? $rows : array();
}

// One device's water readings over the last 24 hours (time + 0/1), oldest first,
// for the trend line chart. Readings are timestamped COALESCE(reported_at, fetched_at).
function liWaterTrend($device_id)
{
	global $database;

	$device_id = (int) $device_id;
	if ($device_id <= 0) {
		return array();
	}

	$rows = $database->get_results("SELECT
			COALESCE(reported_at, fetched_at) AS t,
			CASE WHEN has_water = 1 THEN 1 ELSE 0 END AS v
		FROM hsp_readings
		WHERE device_id = " . $device_id . "
			AND COALESCE(reported_at, fetched_at) IS NOT NULL
			AND COALESCE(reported_at, fetched_at) >= (NOW() - INTERVAL 24 HOUR)
		ORDER BY t ASC");

	return is_array($rows) ? $rows : array();
}

/* ---- Sensor Health -------------------------------------------------------- */

// Counts for the Sensor Health tab. Online / Offline come from the live status
// set (respecting all filters). Communication Failures Today = active sensors
// that have not sent data today. Under Maintenance = deactivated devices
// (is_active = 0), office-scoped.
function liSensorHealth($where = '')
{
	global $database;

	$access = liOfficeAccessWhere('office_id');
	$base = "SELECT * FROM (" . hsp_status_sql() . ") v WHERE 1" . $access . $where;

	$agg = $database->get_results("SELECT
			COALESCE(SUM(state NOT IN ('offline','unknown')), 0) AS online,
			COALESCE(SUM(state IN ('offline','unknown')), 0) AS offline,
			COALESCE(SUM(last_reported_at IS NULL OR DATE(last_reported_at) <> CURDATE()), 0) AS comm_fail
		FROM (" . $base . ") d");
	$agg = (is_array($agg) && isset($agg[0])) ? $agg[0] : array();

	$m = $database->get_results("SELECT COUNT(*) AS c FROM hsp_devices WHERE is_active = 0" . liOfficeAccessWhere('office_id'));
	$maint = (is_array($m) && isset($m[0]['c'])) ? (int) $m[0]['c'] : 0;

	return array(
		'online'      => isset($agg['online']) ? (int) $agg['online'] : 0,
		'offline'     => isset($agg['offline']) ? (int) $agg['offline'] : 0,
		'comm_fail'   => isset($agg['comm_fail']) ? (int) $agg['comm_fail'] : 0,
		'maintenance' => $maint,
	);
}

/* ---- Recent Events -------------------------------------------------------- */

// Latest 100 readings (as events) for the sensors in scope. Event text is
// derived from has_water; the filters/access are applied via the device set.
function liRecentEvents($where = '')
{
	global $database;

	$access = liOfficeAccessWhere('office_id');
	$idset = "SELECT device_id FROM (" . hsp_status_sql() . ") v WHERE 1" . $access . $where;

	$rows = $database->get_results("SELECT
			COALESCE(r.reported_at, r.fetched_at) AS t,
			d.site_id, r.has_water
		FROM hsp_readings r
		JOIN hsp_devices d ON d.id = r.device_id
		WHERE r.device_id IN (" . $idset . ")
			AND COALESCE(r.reported_at, r.fetched_at) IS NOT NULL
		ORDER BY t DESC
		LIMIT 100");

	return is_array($rows) ? $rows : array();
}

/* ---- Daily Water Availability (bar chart) --------------------------------- */

// Per-day water-availability hours for one sensor, or for a whole scheme, over
// the last $days days. An hour counts if water was present at any point in it
// (MAX across the scope's readings), so a scheme total still tops out at 24/day.
function liDailyAvailabilityData($deviceId, $schemeCode, $days = 30)
{
	global $database;

	$days = (int) $days;
	if ($days <= 0) {
		$days = 30;
	}
	$deviceId = (int) $deviceId;
	$schemeCode = trim((string) $schemeCode);

	if ($deviceId > 0) {
		$scope = " AND device_id = " . $deviceId;
	} elseif ($schemeCode !== '') {
		$scope = " AND device_id IN (SELECT d.id FROM hsp_devices d
			JOIN hsp_schemes s ON s.id = d.scheme_id
			WHERE s.code = '" . $database->filter($schemeCode) . "')";
	} else {
		return array();
	}

	$rows = $database->get_results("SELECT d AS day, COALESCE(SUM(hw), 0) AS water_hours
		FROM (
			SELECT DATE(COALESCE(reported_at, fetched_at)) AS d,
				HOUR(COALESCE(reported_at, fetched_at)) AS h,
				MAX(CASE WHEN has_water = 1 THEN 1 ELSE 0 END) AS hw
			FROM hsp_readings
			WHERE COALESCE(reported_at, fetched_at) >= (CURDATE() - INTERVAL " . $days . " DAY)" . $scope . "
			GROUP BY d, h
		) per_hour
		GROUP BY d
		ORDER BY d ASC");

	return is_array($rows) ? $rows : array();
}

/* ---- Reports module ------------------------------------------------------- */

// 1) Daily Water Presence — current status of every in-scope sensor.
function liReportDailyPresence($where = '')
{
	global $database;
	$access = liOfficeAccessWhere('office_id');
	$base = "SELECT * FROM (" . hsp_status_sql() . ") v WHERE 1" . $access . $where;
	$rows = $database->get_results("SELECT site_id, scheme_name, office_name, state,
			last_has_water, last_reported_at
		FROM (" . $base . ") d
		ORDER BY site_id ASC");
	return is_array($rows) ? $rows : array();
}

// 2) Offline Sensor Report — sensors currently offline / no data.
function liReportOffline($where = '')
{
	global $database;
	$access = liOfficeAccessWhere('office_id');
	$base = "SELECT * FROM (" . hsp_status_sql() . ") v WHERE 1" . $access . $where;
	$rows = $database->get_results("SELECT site_id, scheme_name, office_name,
			last_reported_at, last_polled_at
		FROM (" . $base . ") d
		WHERE state IN ('offline','unknown')
		ORDER BY COALESCE(last_reported_at, last_polled_at) ASC");
	return is_array($rows) ? $rows : array();
}

// 3) Sensor Uptime — % of the last 7 days' hour-slots that had any reading.
function liReportUptime($where = '')
{
	global $database;
	$access = liOfficeAccessWhere('office_id');
	$idset = "SELECT device_id FROM (" . hsp_status_sql() . ") v WHERE 1" . $access . $where;
	$rows = $database->get_results("SELECT d.site_id,
			s.name AS scheme_name,
			eo.e_office_name AS office_name,
			ROUND(100 * COUNT(DISTINCT DATE(COALESCE(r.reported_at, r.fetched_at)), HOUR(COALESCE(r.reported_at, r.fetched_at))) / (7*24), 1) AS uptime_pct
		FROM hsp_devices d
		JOIN hsp_schemes s ON s.id = d.scheme_id
		LEFT JOIN tbl_establishment_office eo ON eo.e_office_id = d.office_id
		LEFT JOIN hsp_readings r ON r.device_id = d.id
			AND COALESCE(r.reported_at, r.fetched_at) >= (NOW() - INTERVAL 7 DAY)
		WHERE d.id IN (" . $idset . ")
		GROUP BY d.id, d.site_id, s.name, eo.e_office_name
		ORDER BY uptime_pct ASC");
	return is_array($rows) ? $rows : array();
}

// 4) Monthly Water Availability — water-hours this month per sensor.
function liReportMonthlyAvailability($where = '')
{
	global $database;
	$access = liOfficeAccessWhere('office_id');
	$idset = "SELECT device_id FROM (" . hsp_status_sql() . ") v WHERE 1" . $access . $where;
	$rows = $database->get_results("SELECT d.site_id,
			s.name AS scheme_name,
			eo.e_office_name AS office_name,
			COALESCE(SUM(ph.hw), 0) AS water_hours
		FROM hsp_devices d
		JOIN hsp_schemes s ON s.id = d.scheme_id
		LEFT JOIN tbl_establishment_office eo ON eo.e_office_id = d.office_id
		LEFT JOIN (
			SELECT device_id,
				DATE(COALESCE(reported_at, fetched_at)) AS dd,
				HOUR(COALESCE(reported_at, fetched_at)) AS hh,
				MAX(CASE WHEN has_water = 1 THEN 1 ELSE 0 END) AS hw
			FROM hsp_readings
			WHERE COALESCE(reported_at, fetched_at) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
			GROUP BY device_id, dd, hh
		) ph ON ph.device_id = d.id
		WHERE d.id IN (" . $idset . ")
		GROUP BY d.id, d.site_id, s.name, eo.e_office_name
		ORDER BY d.site_id ASC");
	return is_array($rows) ? $rows : array();
}

/* ---- Sensor Detail -------------------------------------------------------- */

// One sensor's master + current status for the Sensor Detail tab. Office-scoped
// so a restricted operator can't open a device outside their scope. Returns null
// if not found / not permitted.
function liSensorDetail($device_id)
{
	global $database;
	$device_id = (int) $device_id;
	if ($device_id <= 0) {
		return null;
	}
	$access = liOfficeAccessWhere('d.office_id');
	$rows = $database->get_results("SELECT
			d.id AS device_id, d.site_id, d.tail_label, d.created_at AS installed_at,
			s.name AS scheme_name, s.code AS scheme_code,
			eo.e_office_name AS office_name,
			st.latitude, st.longitude,
			st.has_water AS last_has_water, st.water_status AS last_water_status,
			st.reported_at AS last_reported_at, st.polled_at AS last_polled_at,
			COALESCE(st.is_online, 0) AS is_online,
			CASE
				WHEN st.device_id IS NULL OR COALESCE(st.is_online, 0) = 0 THEN 'offline'
				WHEN st.reported_at IS NULL THEN 'unknown'
				WHEN st.reported_at < (NOW() - INTERVAL 30 MINUTE) THEN 'stale'
				ELSE 'online'
			END AS state
		FROM hsp_devices d
		JOIN hsp_schemes s ON s.id = d.scheme_id
		LEFT JOIN tbl_establishment_office eo ON eo.e_office_id = d.office_id
		LEFT JOIN hsp_device_status st ON st.device_id = d.id
		WHERE d.id = " . $device_id . $access);
	return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
}

// Most recent readings for the Historical Readings table (time + status).
function liSensorHistory($device_id, $limit = 30)
{
	global $database;
	$device_id = (int) $device_id;
	$limit = (int) $limit;
	if ($device_id <= 0) {
		return array();
	}
	if ($limit <= 0) {
		$limit = 30;
	}
	$rows = $database->get_results("SELECT COALESCE(reported_at, fetched_at) AS t, has_water
		FROM hsp_readings
		WHERE device_id = " . $device_id . "
			AND COALESCE(reported_at, fetched_at) IS NOT NULL
		ORDER BY t DESC
		LIMIT " . $limit);
	return is_array($rows) ? $rows : array();
}

// Analytics for the Sensor Detail tab:
//   avail_today    = hours today that had water
//   avail_7d_hours = hours in the last 7 days that had water; _pct over 7*24
//   uptime_pct     = % of the last 7 days' hour-slots that had any reading
//   changes        = number of water on/off transitions in the last 7 days
function liSensorAnalytics($device_id)
{
	global $database;
	$device_id = (int) $device_id;
	$out = array('avail_today' => 0, 'avail_7d_hours' => 0, 'avail_7d_pct' => 0.0, 'uptime_pct' => 0.0, 'changes' => 0);
	if ($device_id <= 0) {
		return $out;
	}

	$r = $database->get_results("SELECT COALESCE(SUM(hw), 0) AS wh FROM (
			SELECT HOUR(COALESCE(reported_at, fetched_at)) AS h,
				MAX(CASE WHEN has_water = 1 THEN 1 ELSE 0 END) AS hw
			FROM hsp_readings
			WHERE device_id = " . $device_id . "
				AND DATE(COALESCE(reported_at, fetched_at)) = CURDATE()
			GROUP BY h
		) x");
	$out['avail_today'] = (is_array($r) && isset($r[0]['wh'])) ? (int) $r[0]['wh'] : 0;

	// Window aligned to the hour boundary so it spans EXACTLY 168 clock-hours
	// (7*24) — otherwise the two partial boundary hours make 169 distinct hour
	// slots and the percentages (denominator 168) exceed 100%.
	$r = $database->get_results("SELECT COUNT(*) AS slots, COALESCE(SUM(hw), 0) AS wh FROM (
			SELECT DATE(t) AS d, HOUR(t) AS h, MAX(CASE WHEN has_water = 1 THEN 1 ELSE 0 END) AS hw FROM (
				SELECT COALESCE(reported_at, fetched_at) AS t, has_water
				FROM hsp_readings
				WHERE device_id = " . $device_id . "
					AND COALESCE(reported_at, fetched_at) >= DATE_FORMAT(NOW() - INTERVAL 167 HOUR, '%Y-%m-%d %H:00:00')
			) r GROUP BY d, h
		) ph");
	$slots = (is_array($r) && isset($r[0]['slots'])) ? (int) $r[0]['slots'] : 0;
	$wh7 = (is_array($r) && isset($r[0]['wh'])) ? (int) $r[0]['wh'] : 0;
	$out['avail_7d_hours'] = $wh7;
	$out['avail_7d_pct'] = min(100.0, round($wh7 / (7 * 24) * 100, 1));
	$out['uptime_pct'] = min(100.0, round($slots / (7 * 24) * 100, 1));

	// Skip NULL water readings before LAG so a NULL between two differing known
	// states doesn't mask the transition; tie-break by id for a stable order.
	$r = $database->get_results("SELECT COUNT(*) AS c FROM (
			SELECT has_water, LAG(has_water) OVER (ORDER BY COALESCE(reported_at, fetched_at), id) AS prev
			FROM hsp_readings
			WHERE device_id = " . $device_id . "
				AND has_water IS NOT NULL
				AND COALESCE(reported_at, fetched_at) >= (NOW() - INTERVAL 7 DAY)
		) t WHERE prev IS NOT NULL AND has_water <> prev");
	$out['changes'] = (is_array($r) && isset($r[0]['c'])) ? (int) $r[0]['c'] : 0;

	return $out;
}
?>
