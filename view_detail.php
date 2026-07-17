<?php
/* ============================================================================
   Lift Irrigation — Monitoring component (device detail view)
   Loaded on demand by controller.php for task=view. Shared helpers
   (liEsc/liText/liWaterBadge/liBattery/liStyles/...) live in view.php,
   which the controller always loads first.
   ============================================================================ */

function liInfo($label, $value, $wide = false)
{
	return '
		<div class="li-info-item' . ($wide ? ' li-info-wide' : '') . '">
			<div class="li-info-label">' . liEsc($label) . '</div>
			<div class="li-info-value">' . $value . '</div>
		</div>
	';
}

function liSection($title, $body)
{
	return '
		<div class="li-section">
			<h4>' . liEsc($title) . '</h4>
			' . $body . '
		</div>
	';
}

function liReadingsTable($rows)
{
	if (empty($rows)) {
		return '<div class="li-empty">No readings found.</div>';
	}
	$html = '
		<div class="table-responsive">
			<table class="table table-bordered table-sm li-inner-table">
				<thead>
					<tr>
						<th>Polled (server)</th>
						<th>Reported (device)</th>
						<th>Water</th>
					</tr>
				</thead>
				<tbody>
	';
	foreach ($rows as $r) {
		$html .= '
			<tr>
				<td>' . liDate($r['fetched_at']) . '</td>
				<td>' . liDate($r['reported_at']) . '</td>
				<td>' . liWaterBadge(isset($r['has_water']) ? $r['has_water'] : null, isset($r['water_status']) ? $r['water_status'] : '') . '</td>
		
			</tr>
		';
	}
	$html .= '</tbody></table></div>';
	return $html;
}

function liReadingsFilterForm($id, $from, $to, $water, $int)
{
	global $component;
	$menuid = liMenu();
	$cid = isset($_GET['Cid']) ? $_GET['Cid'] : $menuid['component_headingid'];
	ob_start();
	?>
	<form method="get" action="index.php"
		style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-bottom:12px;">
		<input type="hidden" name="c" value="<?php echo liEsc($component); ?>">
		<input type="hidden" name="Cid" value="<?php echo liEsc($cid); ?>">
		<input type="hidden" name="task" value="view">
		<input type="hidden" name="id" value="<?php echo liEsc($id); ?>">
		<div style="min-width:150px;">
			<div class="li-info-label">Date From</div>
			<input type="date" name="rfrom" class="form-control" value="<?php echo liEsc($from); ?>">
		</div>
		<div style="min-width:150px;">
			<div class="li-info-label">Date To</div>
			<input type="date" name="rto" class="form-control" value="<?php echo liEsc($to); ?>">
		</div>
		<div style="min-width:140px;">
			<div class="li-info-label">Water Status</div>
			<select name="rwater" class="form-control">
				<option value="">All</option>
				<option value="1" <?php echo $water === '1' ? 'selected' : ''; ?>>Yes (water)</option>
				<option value="0" <?php echo $water === '0' ? 'selected' : ''; ?>>No (no water)</option>
			</select>
		</div>
		<div style="min-width:140px;">
			<div class="li-info-label">Interval</div>
			<select name="rint" class="form-control">
				<option value="">Raw readings</option>
				<option value="60" <?php echo $int === '60' ? 'selected' : ''; ?>>1 hour</option>
				<option value="15" <?php echo $int === '15' ? 'selected' : ''; ?>>15 minutes</option>
			</select>
		</div>
		<div style="display:flex; gap:6px;">
			<input type="submit" class="btn btn-primary" value="Filter">
			<a href="<?php echo liEsc(liDetailUrl($id)); ?>" class="btn btn-secondary">Reset</a>
		</div>
	</form>
	<?php
	return ob_get_clean();
}

function liReadingsBucketTable($rows, $interval)
{
	if (empty($rows)) {
		return '<div class="li-empty">No readings found.</div>';
	}
	$endMin = ($interval === 15) ? 14 : 59; // minutes added for the slot-end label
	$html = '
		<div class="table-responsive">
			<table class="table table-bordered table-sm li-inner-table">
				<thead>
					<tr>
						<th>Interval</th>
						<th>Water</th>
						<th>Readings</th>
					</tr>
				</thead>
				<tbody>
	';
	foreach ($rows as $r) {
		$start = strtotime($r['bucket']);
		$label = $start
			? liEsc(date('d-m-Y H:i', $start)) . ' &ndash; ' . liEsc(date('H:i', $start + $endMin * 60))
			: liText($r['bucket']);
		$w = (int) $r['water'];
		$nw = (int) $r['nowater'];
		$badge = $w > 0 ? liWaterBadge('1') : ($nw > 0 ? liWaterBadge('0') : liWaterBadge(null, 'unknown'));
		$html .= '
			<tr>
				<td>' . $label . '</td>
				<td>' . $badge . '</td>
				<td>' . (int) $r['readings'] . '</td>
			</tr>
		';
	}
	$html .= '</tbody></table></div>';
	return $html;
}

function showDeviceDetailsPage($id)
{
	global $database;

	$id = trim((string) $id);
	$rows = liRows("SELECT * FROM (" . hsp_status_sql() . ") v WHERE device_id = '" . $database->filter($id) . "'");
	?>
	<div class="main-content">
		<div class="container">
			<div class="li-toolbar">
				<div>
					<h3 class="mb-1">Device Details</h3>
					<div class="text-muted">Read-only monitoring view</div>
				</div>
				<a href="<?php echo liEsc(liListUrl()); ?>" class="btn btn-secondary">Back</a>
			</div>

			<?php if (!empty($rows)) {
				$row = $rows[0];
				// --- readings filters: date range + water status ---
				$rvalid = function ($d) {
					if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
						return '';
					}
					$t = strtotime($d);
					return ($t && date('Y-m-d', $t) === $d) ? $d : '';
				};
				$rfrom = $rvalid(isset($_GET['rfrom']) ? $_GET['rfrom'] : '');
				$rto = $rvalid(isset($_GET['rto']) ? $_GET['rto'] : '');
				$rwater = (isset($_GET['rwater']) && ($_GET['rwater'] === '1' || $_GET['rwater'] === '0')) ? $_GET['rwater'] : '';

				$rwhere = " WHERE device_id = '" . $database->filter($id) . "'";
				if ($rfrom !== '') {
					$rwhere .= " AND COALESCE(reported_at, fetched_at) >= '" . $database->filter($rfrom) . " 00:00:00'";
				}
				if ($rto !== '') {
					$rwhere .= " AND COALESCE(reported_at, fetched_at) < '" . $database->filter(date('Y-m-d', strtotime($rto . ' +1 day'))) . " 00:00:00'";
				}
				if ($rwater === '1') {
					$rwhere .= " AND has_water = 1";
				} elseif ($rwater === '0') {
					$rwhere .= " AND has_water = 0";
				}
				$rint = (isset($_GET['rint']) && ($_GET['rint'] === '15' || $_GET['rint'] === '60')) ? $_GET['rint'] : '';
				$rfiltered = ($rfrom !== '' || $rto !== '' || $rwater !== '');

				if ($rint !== '') {
					// bucket readings into 15-min / 1-hour slots (water = present at
					// any point in the slot). $secs comes from a fixed whitelist.
					$secs = ($rint === '15') ? 900 : 3600;
					$readings = liRows("SELECT FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP(ts) / " . $secs . ") * " . $secs . ") AS bucket,
							MAX(CASE WHEN has_water = 1 THEN 1 ELSE 0 END) AS water,
							MAX(CASE WHEN has_water = 0 THEN 1 ELSE 0 END) AS nowater,
							COUNT(*) AS readings
						FROM (SELECT COALESCE(reported_at, fetched_at) AS ts, has_water FROM hsp_readings" . $rwhere . ") b
						GROUP BY bucket ORDER BY bucket DESC LIMIT 200");
				} else {
					$readings = liRows("SELECT fetched_at, reported_at, has_water, water_status, battery_voltage FROM hsp_readings" . $rwhere . " ORDER BY fetched_at DESC LIMIT 200");
				}

				$lat = isset($row['latitude']) ? trim((string) $row['latitude']) : '';
				$lng = isset($row['longitude']) ? trim((string) $row['longitude']) : '';
				$gps = ($lat !== '' && $lng !== '')
					? liEsc($lat) . ', ' . liEsc($lng) . ' &nbsp;<a href="https://www.google.com/maps?q=' . liEsc($lat) . ',' . liEsc($lng) . '" target="_blank">Map &rsaquo;</a>'
					: '- - -';

				$basic = '<div class="li-info-grid">' .
					liInfo('Site ID', liText($row['site_id'])) .
					liInfo('Scheme', liText($row['scheme_name'])) .
					liInfo('Tail', liText($row['tail_label'])) .
					liInfo('Device Code', liText($row['device_code'])) .
					liInfo('Site Name', liText($row['site_name'])) .
					liInfo('Water Status', liWaterBadge($row['last_has_water'], $row['last_water_status'])) .
					liInfo('Battery', liBattery($row['last_battery'])) .
					liInfo('Reported (device)', liDate($row['last_reported_at']) . ' <span class="text-muted">(' . liAgo($row['last_reported_at']) . ')</span>') .
					liInfo('Last Polled (server)', liDate($row['last_polled_at'])) .
					liInfo('SIM Number', liText($row['sim_im_no'])) .
					liInfo('SIM Validity', liSim($row['sim_validity_upto'], $row['sim_days_left'])) .
					liInfo('GPS Location', $gps, true) .
					'</div>';
				?>
				<div class="li-detail-shell">
					<div class="li-detail-hero">
						<div>
							<h3><?php echo liText($row['tail_label']); ?> &mdash; <?php echo liText($row['site_name']); ?></h3>
							<div class="li-subtitle"><?php echo liText($row['scheme_name']); ?> &middot;
								<?php echo liText($row['device_code']); ?></div>
						</div>
						<div class="li-status-stack">
							<div style="display:flex; align-items:center; gap:8px;">
								<span style="color:#51658f; font-size:13px; font-weight:600;">Sensor Status</span>
								<?php echo liStateBadge($row['state']); ?>
							</div>
							<div style="display:flex; align-items:center; gap:8px;">
								<span style="color:#51658f; font-size:13px; font-weight:600;">Water Status</span>
								<?php echo liWaterBadge($row['last_has_water'], $row['last_water_status']); ?>
							</div>
						</div>
					</div>

					<div class="li-stat-grid">
						<div class="li-stat"><span>State</span><strong><?php echo liStateBadge($row['state']); ?></strong></div>
						<div class="li-stat"><span>Battery</span><strong><?php echo liBattery($row['last_battery']); ?></strong>
						</div>
						<div class="li-stat"><span>Last Update</span><strong
								style="font-size:14px;"><?php echo liAgo($row['last_reported_at']); ?></strong></div>
						<div class="li-stat"><span>SIM Validity</span><strong
								style="font-size:14px;"><?php echo liSim($row['sim_validity_upto'], $row['sim_days_left']); ?></strong>
						</div>
					</div>

					<?php echo liSection('Basic Information', $basic); ?>
					<?php
					$rmode = $rint === '60' ? 'hourly' : ($rint === '15' ? '15-min' : 'raw');
					$rd_title = 'Readings — ' . $rmode . ($rfiltered ? ', filtered' : '') . ' (latest 200)';
					$rd_table = $rint !== '' ? liReadingsBucketTable($readings, (int) $rint) : liReadingsTable($readings);
					echo liSection($rd_title, liReadingsFilterForm($id, $rfrom, $rto, $rwater, $rint) . $rd_table);
					?>
				</div>
			<?php } else { ?>
				<div class="alert alert-warning">Device not found.</div>
			<?php } ?>
		</div>
	</div>
	<?php liStyles(); ?>
<?php
}
?>
