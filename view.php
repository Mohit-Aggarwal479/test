<?php
/* ============================================================================
   Lift Irrigation — Monitoring component (view layer)
   Reads the hsp_ snapshot + hsp_readings tables (filled by poll_devices.php cron),
   building the "current status" rows in PHP via hsp_status_sql() — no DB view needed.
   Helpers prefixed li* so they never clash with other components.
   ============================================================================ */

function liEsc($value)
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function liRows($sql)
{
	global $database;
	$rows = $database->get_results($sql);
	return is_array($rows) ? $rows : array();
}

function liText($value, $fallback = '- - -')
{
	if (is_array($value)) {
		return $fallback;
	}
	$value = trim((string) $value);
	return $value === '' ? $fallback : liEsc($value);
}

function liDate($value)
{
	$value = trim((string) $value);
	if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
		return '- - -';
	}
	$time = strtotime($value);
	return $time ? date('d-m-Y H:i', $time) : liEsc($value);
}

function liAgo($value)
{
	$value = trim((string) $value);
	if ($value === '' || $value === '0000-00-00 00:00:00') {
		return '- - -';
	}
	$time = strtotime($value);
	if (!$time) {
		return liEsc($value);
	}
	$diff = time() - $time;
	if ($diff < 60) {
		return 'just now';
	}
	$mins = floor($diff / 60);
	if ($mins < 60) {
		return $mins . ' min ago';
	}
	$hrs = floor($mins / 60);
	if ($hrs < 24) {
		return $hrs . 'h ' . ($mins % 60) . 'm ago';
	}
	return floor($hrs / 24) . 'd ago';
}

function liStateBadge($state)
{
	$state = strtolower(trim((string) $state));
	$map = array(
		'online' => array('badge-success', 'Online'),
		'stale' => array('badge-warning', 'Stale'),
		'offline' => array('badge-danger', 'Offline'),
		'unknown' => array('badge-secondary', 'Unknown'),
	);
	$cfg = isset($map[$state]) ? $map[$state] : $map['unknown'];
	return '<span class="badge ' . $cfg[0] . '">' . liEsc($cfg[1]) . '</span>';
}

function liWaterBadge($has_water, $raw = '')
{
	if ($has_water === null || $has_water === '') {
		$label = trim((string) $raw);
		return $label !== '' ? '<span class="badge badge-secondary">' . liEsc($label) . '</span>' : '- - -';
	}
	if ((string) $has_water === '1') {
		return '<span class="badge li-badge-water">Yes</span>';
	}
	return '<span class="badge li-badge-nowater">No</span>';
}

function liBattery($volts)
{
	if ($volts === null || $volts === '' || !is_numeric($volts)) {
		return '- - -';
	}
	$v = (float) $volts;
	$cls = $v >= 12.5 ? 'text-success' : ($v < 11.8 ? 'text-danger' : 'text-warning');
	return '<span class="' . $cls . '" style="font-weight:600;">' . number_format($v, 1) . ' V</span>';
}

function liSim($date, $days_left)
{
	$date = trim((string) $date);
	if ($date === '' || $date === '0000-00-00') {
		return '- - -';
	}
	$txt = date('d-m-Y', strtotime($date));
	if ($days_left !== null && $days_left !== '') {
		$d = (int) $days_left;
		if ($d < 0) {
			return '<span class="text-danger" style="font-weight:600;">' . liEsc($txt) . ' (expired)</span>';
		}
		if ($d <= 30) {
			return '<span class="text-warning" style="font-weight:600;">' . liEsc($txt) . ' (' . $d . 'd)</span>';
		}
	}
	return liEsc($txt);
}

function liMenu()
{
	global $database;
	// Cached per request — liDetailUrl() calls this once per table row,
	// which used to re-query tbl_components for every device listed.
	static $cache = null;
	if ($cache !== null) {
		return $cache;
	}
	$component = isset($_GET['c']) ? $_GET['c'] : '';
	$rows = liRows("SELECT * FROM tbl_components WHERE component_option = '" . $database->filter($component) . "'");
	$cache = !empty($rows) ? $rows[0] : array('component_headingid' => '');
	return $cache;
}

function liDetailUrl($id)
{
	global $component;
	$menuid = liMenu();
	$cid = isset($_GET['Cid']) ? $_GET['Cid'] : $menuid['component_headingid'];
	return 'index.php?c=' . rawurlencode($component) .
		'&Cid=' . rawurlencode($cid) .
		'&task=view&id=' . rawurlencode($id);
}

function liListUrl()
{
	global $component;
	$menuid = liMenu();
	$cid = isset($_GET['Cid']) ? $_GET['Cid'] : $menuid['component_headingid'];
	return 'index.php?c=' . rawurlencode($component) . '&Cid=' . rawurlencode($cid);
}

function liCalendarUrl($params = array())
{
	global $component;
	$menuid = liMenu();
	$cid = isset($_GET['Cid']) ? $_GET['Cid'] : $menuid['component_headingid'];
	$url = 'index.php?c=' . rawurlencode($component) . '&Cid=' . rawurlencode($cid) . '&task=calendar';
	foreach ($params as $k => $v) {
		$url .= '&' . rawurlencode($k) . '=' . rawurlencode($v);
	}
	return $url;
}

function liSchemeOptions()
{
	static $cache = null;
	if ($cache === null) {
		$cache = liRows("SELECT code, name FROM hsp_schemes WHERE is_active = 1 ORDER BY sort_order ASC");
	}
	return $cache;
}

function showRecordsListing(&$rows)
{
	global $component, $database, $pagingObject;

	$rows = is_array($rows) ? $rows : array();
	$totalRecords = count($rows);

	$summary_sql = isset($_SESSION['hsp_summary_sql']) ? $_SESSION['hsp_summary_sql'] : ("SELECT * FROM (" . hsp_status_sql() . ") v WHERE 1");

	// One aggregate pass in SQL instead of six queries that each pulled
	// every row into PHP just to count them.
	$agg = liRows("SELECT COUNT(*) AS total,
			COALESCE(SUM(state = 'online'), 0) AS online,
			COALESCE(SUM(state = 'offline'), 0) AS offline,
			COALESCE(SUM(last_has_water = '0' AND state <> 'offline'), 0) AS nowater,
			COALESCE(SUM(last_battery < 11.8 AND state <> 'offline'), 0) AS lowbatt,
			COALESCE(SUM(sim_days_left IS NOT NULL AND sim_days_left <= 30), 0) AS simexp
		FROM (" . $summary_sql . ") cards");
	$agg = !empty($agg) ? $agg[0] : array();
	$total = isset($agg['total']) ? (int) $agg['total'] : 0;
	$online = isset($agg['online']) ? (int) $agg['online'] : 0;
	$offline = isset($agg['offline']) ? (int) $agg['offline'] : 0;
	$nowater = isset($agg['nowater']) ? (int) $agg['nowater'] : 0;
	$lowbatt = isset($agg['lowbatt']) ? (int) $agg['lowbatt'] : 0;
	$simexp = isset($agg['simexp']) ? (int) $agg['simexp'] : 0;

	$scheme_options = liSchemeOptions();
	$menuid = liMenu();
	?>

	<form name="adminForm" action="?c=<?php echo liEsc($component); ?>" method="get">
		<div class="main-content">
			<div class="container">
				<div class="row align-items-center">
					<div class="col-md-8">
						<h3>Lift Irrigation Monitoring</h3>
					</div>
					<div class="col-md-4 text-right">
						<a href="<?php echo liEsc('index.php?c=' . rawurlencode($component) . '&Cid=' . rawurlencode(isset($_GET['Cid']) ? $_GET['Cid'] : $menuid['component_headingid']) . '&task=dashboard'); ?>"
							class="btn btn-primary" style="margin-right:6px;">Dashboard</a>
						<a href="<?php echo liEsc(liCalendarUrl()); ?>" class="btn btn-primary"
							style="margin-right:6px;">Calendar View</a>
						<a href="<?php echo liEsc(liListUrl()); ?>" class="btn btn-secondary"
							style="margin-right:6px;">Refresh</a>
						<!-- <a href="export/lift_irrigation.php" class="btn"
							style="color:#fff!important; background-color:#2E8B57; border-color:#2E8B57;">
							<?php if (function_exists('icon_excel')) { ?><img src="<?php echo liEsc(icon_excel()); ?>"
									style="max-width:23px; margin-right:5px;"><?php } ?>Export
						</a> -->
					</div>
				</div>

				<div style="height:20px;"></div>

				<div class="row">
					<div class="col-md-2 col-6">
						<div class="card">
							<div class="card-body" style="padding:10px;">
								<h5>Total</h5>
								<h3 class="mb-0 mt-1 text-primary fs-25"><?php echo $total; ?></h3>
							</div>
						</div>
					</div>
					<div class="col-md-2 col-6">
						<div class="card">
							<div class="card-body" style="padding:10px;">
								<h5>Online</h5>
								<h3 class="mb-0 mt-1 text-success fs-25"><?php echo $online; ?></h3>
							</div>
						</div>
					</div>
					<div class="col-md-2 col-6">
						<div class="card">
							<div class="card-body" style="padding:10px;">
								<h5>Offline</h5>
								<h3 class="mb-0 mt-1 text-danger fs-25"><?php echo $offline; ?></h3>
							</div>
						</div>
					</div>
					<div class="col-md-2 col-6">
						<div class="card">
							<div class="card-body" style="padding:10px;">
								<h5>No Water</h5>
								<h3 class="mb-0 mt-1 fs-25" style="color:#0b86c4;"><?php echo $nowater; ?></h3>
							</div>
						</div>
					</div>
					<div class="col-md-2 col-6">
						<div class="card">
							<div class="card-body" style="padding:10px;">
								<h5>Low Battery</h5>
								<h3 class="mb-0 mt-1 text-warning fs-25"><?php echo $lowbatt; ?></h3>
							</div>
						</div>
					</div>
					<div class="col-md-2 col-6">
						<div class="card">
							<div class="card-body" style="padding:10px;">
								<h5>SIM Expiring</h5>
								<h3 class="mb-0 mt-1 text-warning fs-25"><?php echo $simexp; ?></h3>
							</div>
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-lg-12">
						<div class="card">
							<div class="card-body">
								<div class="row">
									<div class="col-md-3">
										<label class="form-label" style="line-height:36px;">Scheme</label>
										<select name="scheme" class="form-control">
											<option value="">All</option>
											<?php foreach ($scheme_options as $opt) {
												$selected = isset($_GET['scheme']) && (string) $_GET['scheme'] === (string) $opt['code'] ? 'selected' : '';
												?>
												<option value="<?php echo liEsc($opt['code']); ?>" <?php echo $selected; ?>>
													<?php echo liEsc($opt['name']); ?></option>
											<?php } ?>
										</select>
									</div>
									<div class="col-md-3">
										<label class="form-label" style="line-height:36px;">State</label>
										<select name="state" class="form-control">
											<option value="">All</option>
											<option value="online" <?php echo isset($_GET['state']) && $_GET['state'] === 'online' ? 'selected' : ''; ?>>Online</option>
											<option value="stale" <?php echo isset($_GET['state']) && $_GET['state'] === 'stale' ? 'selected' : ''; ?>>Stale</option>
											<option value="offline" <?php echo isset($_GET['state']) && $_GET['state'] === 'offline' ? 'selected' : ''; ?>>Offline</option>
										</select>
									</div>
									<div class="col-md-3">
										<label class="form-label" style="line-height:36px;">Water</label>
										<select name="water" class="form-control">
											<option value="">All</option>
											<option value="1" <?php echo isset($_GET['water']) && $_GET['water'] === '1' ? 'selected' : ''; ?>>Yes (water)</option>
											<option value="0" <?php echo isset($_GET['water']) && $_GET['water'] === '0' ? 'selected' : ''; ?>>No (no water)</option>
										</select>
									</div>
									<div class="col-md-3">
										<label class="form-label" style="line-height:36px;">Device Code</label>
										<input type="text" class="form-control" name="device_code" placeholder="device1"
											value="<?php echo isset($_GET['device_code']) ? liEsc($_GET['device_code']) : ''; ?>">
									</div>
									<div class="col col-auto mb-4">
										<br><br>
										<input type="submit" class="btn btn-primary" value="Search" style="width:70px;">
									</div>
								</div>
							</div>

							<div class="e-table">
								<div class="table-responsive table-lg">
									<table class="table card-table table-vcenter text-nowrap border" id="example1">
										<thead>
											<tr>
												<th>LSD ID</th>
												<th>Name of Site</th>
												<th>Name of Division</th>

												<th>Scheme Name</th>
												<th>Water</th>
												<th>Battery</th>
												<th>Last Update</th>
												<th>State</th>
											</tr>
										</thead>
										<tbody>
											<?php if ($totalRecords > 0) {
												foreach ($rows as $row) {
													$detail_url = liDetailUrl($row['device_id']);
													?>
													<tr>
														<td class="align-middle">
															<a href="<?php echo liEsc($detail_url); ?>"
																style="color:#36f; font-weight:600;"><?php echo liText($row['site_id']); ?></a>
														</td>
														<td class="align-middle"><?php echo liText($row['site_name']); ?></td>
														<td class="align-middle"><?php echo liText($row['office_name']); ?></td>

														<td class="align-middle"><?php echo liText($row['scheme_name']); ?></td>
														<td class="align-middle">
															<?php echo liWaterBadge($row['last_has_water'], $row['last_water_status']); ?>
														</td>
														<td class="align-middle"><?php echo liBattery($row['last_battery']); ?></td>
														<td class="align-middle"><?php echo liAgo($row['last_reported_at']); ?></td>
														<td class="align-middle"><?php echo liStateBadge($row['state']); ?></td>
													</tr>
												<?php }
											} else { ?>
												<tr>
													<td class="align-middle" style="font-size:15px;" colspan="8">No Record
														found..</td>
												</tr>
											<?php } ?>
										</tbody>
									</table>
									<?php $pagingObject->displayLinks_Front(); ?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<input type="hidden" name="Cid"
				value="<?php echo isset($_GET['Cid']) ? liEsc($_GET['Cid']) : liEsc($menuid['component_headingid']); ?>">
			<input type="hidden" name="c" value="<?php echo liEsc($component); ?>">
		</div>
	</form>
	<?php liStyles(); ?>
<?php }

function liStyles()
{
	?>
	<style>
		.li-badge-water {
			background: #16a34a;
			color: #fff;
		}

		.li-badge-nowater {
			background: #dc2626;
			color: #fff;
		}

		.li-detail-shell {
			background: #f4f7fb;
			border: 1px solid #dfe7f2;
			border-radius: 8px;
			padding: 14px;
		}

		.li-detail-hero {
			align-items: flex-start;
			background: #fff;
			border: 1px solid #dfe7f2;
			border-radius: 8px;
			display: flex;
			justify-content: space-between;
			gap: 16px;
			padding: 16px 18px;
		}

		.li-detail-hero h3 {
			color: #1d3473;
			font-size: 22px;
			font-weight: 700;
			line-height: 1.25;
			margin: 0 0 6px;
		}

		.li-subtitle {
			color: #51658f;
			font-size: 13px;
			line-height: 1.45;
			white-space: normal;
		}

		.li-status-stack {
			display: flex;
			flex-direction: column;
			gap: 8px;
			min-width: 140px;
			align-items: flex-start;
		}

		.li-stat-grid {
			display: grid;
			gap: 10px;
			grid-template-columns: repeat(4, minmax(0, 1fr));
			margin-top: 12px;
		}

		.li-stat,
		.li-section {
			background: #fff;
			border: 1px solid #dfe7f2;
			border-radius: 8px;
		}

		.li-stat {
			padding: 12px;
		}

		.li-stat span {
			color: #65758f;
			display: block;
			font-size: 12px;
			margin-bottom: 3px;
		}

		.li-stat strong {
			color: #1d3473;
			font-size: 20px;
		}

		.li-section {
			margin-top: 12px;
			padding: 14px;
		}

		.li-section h4 {
			border-bottom: 1px solid #e6edf7;
			color: #1d3473;
			font-size: 15px;
			font-weight: 700;
			margin: 0 0 12px;
			padding-bottom: 8px;
		}

		.li-info-grid {
			display: grid;
			gap: 10px;
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}

		.li-info-item {
			background: #f9fbfe;
			border: 1px solid #e7edf7;
			border-radius: 6px;
			padding: 10px 12px;
			white-space: normal;
		}

		.li-info-wide {
			grid-column: 1 / -1;
		}

		.li-info-label {
			color: #65758f;
			font-size: 11px;
			font-weight: 700;
			letter-spacing: .03em;
			margin-bottom: 5px;
			text-transform: uppercase;
		}

		.li-info-value {
			color: #21376f;
			font-size: 14px;
			line-height: 1.45;
			overflow-wrap: anywhere;
		}

		.li-inner-table {
			color: #21376f;
			white-space: normal;
		}

		.li-inner-table th {
			background: #f7f9fd;
			color: #1d3473;
			font-size: 12px;
		}

		.li-empty {
			background: #f9fbfe;
			border: 1px solid #e7edf7;
			border-radius: 6px;
			color: #65758f;
			line-height: 1.55;
			padding: 12px;
			text-align: center;
			white-space: normal;
		}

		.li-toolbar {
			align-items: center;
			display: flex;
			justify-content: space-between;
			margin-bottom: 14px;
		}

		@media (max-width:767px) {

			.li-detail-hero,
			.li-toolbar {
				flex-direction: column;
			}

			.li-stat-grid,
			.li-info-grid {
				grid-template-columns: 1fr;
			}
		}
	</style>
	<?php
}
?>
