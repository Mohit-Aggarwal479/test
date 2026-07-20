<?php
/* ============================================================================
   Lift Irrigation — Monitoring : Overview Dashboard
   Outlet's Water Presence Monitoring System for Lift Schemes.
   Sections: Header  |  Filters  |  Summary Statistics (coloured tiles).
   Data helpers live in functions.php (liDashboard*); shared li* helpers in view.php.
   The State Map View tab lives in its own file (view_map.php) and its map
   library loads only when that tab is opened.
   ============================================================================ */

require_once __DIR__ . '/view_map.php';
require_once __DIR__ . '/view_scheme.php';
require_once __DIR__ . '/view_critical.php';
require_once __DIR__ . '/view_trend.php';
require_once __DIR__ . '/view_health.php';
require_once __DIR__ . '/view_events.php';
require_once __DIR__ . '/view_availability.php';
require_once __DIR__ . '/view_reports.php';
require_once __DIR__ . '/view_sensor.php';

// Dashboard URL carrying the component + Cid (mirrors liCalendarUrl()).
function liDashboardUrl($params = array())
{
	global $component;
	$menuid = liMenu();
	$cid = isset($_GET['Cid']) ? $_GET['Cid'] : $menuid['component_headingid'];
	$url = 'index.php?c=' . rawurlencode($component) . '&Cid=' . rawurlencode($cid) . '&task=dashboard';
	foreach ($params as $k => $v) {
		$url .= '&' . rawurlencode($k) . '=' . rawurlencode($v);
	}
	return $url;
}

function showDashboard()
{
	global $component, $database;

	$where = liDashboardWhere();
	$stats = liDashboardStats($where);
	$office_rows = liDashboardByOffice($where);
	$scheme_rows = liSchemeWiseStatus($where);
	$critical_rows = liCriticalLocations($where);
	$trend_devices = liTrendDeviceOptions($where);
	$trend_id = isset($_GET['trend']) ? $_GET['trend'] : '';
	$trend_points = ($trend_id !== '') ? liWaterTrend($trend_id) : array();
	$trend_now = time();
	$markers = liMapMarkers($where);

	// which tab is active on load (persisted across the picker/cascade reloads)
	$valid_tabs = array('overview', 'health', 'scheme', 'critical', 'events', 'trend', 'availability', 'reports', 'sensor', 'map');
	$active_tab = (isset($_GET['tab']) && in_array($_GET['tab'], $valid_tabs, true)) ? $_GET['tab'] : 'overview';

	// Sensor Health + Recent Events (light — always loaded)
	$health = liSensorHealth($where);
	$events = liRecentEvents($where);

	// Daily Availability — only fetch data once a sensor/scheme is chosen
	$av_device  = isset($_GET['avail_device']) ? $_GET['avail_device'] : '';
	$av_scheme  = isset($_GET['avail_scheme']) ? $_GET['avail_scheme'] : '';
	$av_days    = isset($_GET['avail_days']) ? (int) $_GET['avail_days'] : 30;
	$av_schemes = liSchemeOptions();
	$av_rows    = ($av_device !== '' || $av_scheme !== '') ? liDailyAvailabilityData($av_device, $av_scheme, $av_days) : array();

	// Reports datasets are heavier — build them only when the Reports tab is open
	$rep_daily = $rep_uptime = $rep_offline = $rep_monthly = array();
	if ($active_tab === 'reports') {
		$rep_daily   = liReportDailyPresence($where);
		$rep_uptime  = liReportUptime($where);
		$rep_offline = liReportOffline($where);
		$rep_monthly = liReportMonthlyAvailability($where);
	}

	// Sensor Detail — fetch only when a sensor is picked
	$detail_id = isset($_GET['detail']) ? $_GET['detail'] : '';
	$sensor_detail = ($detail_id !== '') ? liSensorDetail($detail_id) : null;
	$sensor_history = ($sensor_detail !== null) ? liSensorHistory($detail_id, 30) : array();
	$sensor_analytics = ($sensor_detail !== null) ? liSensorAnalytics($detail_id) : array('avail_today' => 0, 'avail_7d_hours' => 0, 'avail_7d_pct' => 0, 'uptime_pct' => 0, 'changes' => 0);
	$circles = liCircleOptions();
	$divisions = liDivisionHierOptions();
	$subdivs = liSubDivisionOptions();
	$schemes = liSchemeOptions();
	$last_refresh = liDashboardLastRefresh();
	$menuid = liMenu();
	$cid = isset($_GET['Cid']) ? $_GET['Cid'] : $menuid['component_headingid'];

	// current values for sticky filters
	$f_circle   = isset($_GET['circle']) ? $_GET['circle'] : '';
	$f_division = isset($_GET['division']) ? $_GET['division'] : '';
	$f_subdiv   = isset($_GET['subdiv']) ? $_GET['subdiv'] : '';
	$f_scheme   = isset($_GET['scheme']) ? $_GET['scheme'] : '';
	$f_rd       = isset($_GET['rd']) ? $_GET['rd'] : '';
	$f_sensor   = isset($_GET['sensor']) ? $_GET['sensor'] : '';
	$f_from     = isset($_GET['from']) ? $_GET['from'] : '';
	$f_to       = isset($_GET['to']) ? $_GET['to'] : '';

	// tiles: label, value, css modifier — order matches the spec's coloured cards
	$tiles = array(
		array('Total Sensors Installed', $stats['total'],          'li-tile-total'),
		array('Water Present',           $stats['water_present'],  'li-tile-water'),
		array('Water Not Present',       $stats['water_absent'],   'li-tile-nowater'),
		array('Offline / No Data',       $stats['offline'],        'li-tile-offline'),
	);
	$secondary = array(
		array('Data Received Today', $stats['received_today']),
		array('Active Schemes',      $stats['active_schemes']),
	);
	?>
	<div class="main-content">
		<div class="container-fluid">

			<!-- ============ HEADER SECTION ============ -->
			<div class="li-dash-header">
				<div class="li-dash-head-left">
					<div class="li-dash-dept">Water Resources Department, Punjab</div>
					<h3 class="li-dash-title">Tail-End Water Presence Monitoring Dashboard</h3>
				</div>
				<div class="li-dash-head-right">
					<div class="li-dash-meta"><span>Date &amp; Time:</span>
						<strong id="liDashClock"><?php echo date('d-m-Y H:i:s'); ?></strong>
					</div>
					<div class="li-dash-meta"><span>Last Data Refresh:</span>
						<strong><?php echo $last_refresh ? liDate($last_refresh) : '- - -'; ?></strong>
					</div>
				</div>
			</div>

			<!-- ============ FILTERS SECTION ============ -->
			<form name="dashFilters" method="get" action="index.php" class="card li-dash-filters">
				<input type="hidden" name="c" value="<?php echo liEsc($component); ?>">
				<input type="hidden" name="Cid" value="<?php echo liEsc($cid); ?>">
				<input type="hidden" name="task" value="dashboard">
				<?php // keep the active tab when filters are applied / a dropdown auto-submits ?>
				<input type="hidden" name="tab" id="liFilterTab" value="<?php echo liEsc($active_tab); ?>">
				<div class="card-body">
					<div class="row">
						<div class="col-md-3 col-6 mb-3">
							<label class="form-label">Circle</label>
							<?php // picking a Circle reloads the form; the server then renders its divisions ?>
							<select name="circle" id="liFCircle" class="form-control" onchange="liPick('circle')">
								<option value="">-- Select Circle --</option>
								<?php foreach ($circles as $c) {
									$sel = ((string) $f_circle === (string) $c['id']) ? 'selected' : '';
									?>
									<option value="<?php echo liEsc($c['id']); ?>" <?php echo $sel; ?>>
										<?php echo liText($c['name']); ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="col-md-3 col-6 mb-3">
							<label class="form-label">Division</label>
							<?php // only the selected Circle's divisions are rendered; locked until a Circle is chosen ?>
							<select name="division" id="liFDivision" class="form-control" onchange="liPick('division')" <?php echo $f_circle === '' ? 'disabled' : ''; ?>>
								<option value="">All Divisions</option>
								<?php foreach ($divisions as $d) {
									if ((string) $d['parent'] !== (string) $f_circle) {
										continue;
									}
									$sel = ((string) $f_division === (string) $d['id']) ? 'selected' : '';
									?>
									<option value="<?php echo liEsc($d['id']); ?>" <?php echo $sel; ?>>
										<?php echo liText($d['name']); ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="col-md-3 col-6 mb-3">
							<label class="form-label">Sub-Division</label>
							<?php // only the selected Division's sub-divisions are rendered; locked until a Division is chosen ?>
							<select name="subdiv" id="liFSubdiv" class="form-control" onchange="liPick('subdiv')" <?php echo $f_division === '' ? 'disabled' : ''; ?>>
								<option value="">All Sub-Divisions</option>
								<?php foreach ($subdivs as $sd) {
									if ((string) $sd['parent'] !== (string) $f_division) {
										continue;
									}
									$sel = ((string) $f_subdiv === (string) $sd['id']) ? 'selected' : '';
									?>
									<option value="<?php echo liEsc($sd['id']); ?>" <?php echo $sel; ?>>
										<?php echo liText($sd['name']); ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="col-md-3 col-6 mb-3">
							<label class="form-label">Lift Irrigation Scheme</label>
							<select name="scheme" class="form-control">
								<option value="">All</option>
								<?php foreach ($schemes as $s) {
									$sel = ((string) $f_scheme === (string) $s['code']) ? 'selected' : '';
									?>
									<option value="<?php echo liEsc($s['code']); ?>" <?php echo $sel; ?>>
										<?php echo liEsc($s['name']); ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="col-md-3 col-6 mb-3">
							<label class="form-label">RD of Outlet</label>
							<input type="text" name="rd" class="form-control" placeholder="e.g. Tail 3 / RD 1200"
								value="<?php echo liEsc($f_rd); ?>">
						</div>
						<div class="col-md-3 col-6 mb-3">
							<label class="form-label">Sensor Status</label>
							<select name="sensor" class="form-control">
								<option value="">All</option>
								<option value="online" <?php echo $f_sensor === 'online' ? 'selected' : ''; ?>>Online</option>
								<option value="stale" <?php echo $f_sensor === 'stale' ? 'selected' : ''; ?>>Stale</option>
								<option value="offline" <?php echo $f_sensor === 'offline' ? 'selected' : ''; ?>>Offline</option>
								<option value="unknown" <?php echo $f_sensor === 'unknown' ? 'selected' : ''; ?>>No Data</option>
							</select>
						</div>
						<div class="col-md-3 col-6 mb-3">
							<label class="form-label">Date Range — From</label>
							<input type="date" name="from" class="form-control" value="<?php echo liEsc($f_from); ?>">
						</div>
						<div class="col-md-3 col-6 mb-3">
							<label class="form-label">Date Range — To</label>
							<input type="date" name="to" class="form-control" value="<?php echo liEsc($f_to); ?>">
						</div>
					</div>

					<div class="li-dash-actions">
						<button type="submit" class="btn btn-primary">Apply Filters</button>
						<a href="<?php echo liEsc(liDashboardUrl(array('tab' => $active_tab))); ?>" class="btn btn-secondary">Reset Filters</a>
						<button type="button" class="btn li-btn-export" onclick="liDashExport()">Export Report</button>
						<span class="li-dash-links">
							<a href="<?php echo liEsc(liListUrl()); ?>">Listing</a> &middot;
							<a href="<?php echo liEsc(liCalendarUrl()); ?>">Calendar</a>
						</span>
					</div>
				</div>
			</form>

			<!-- ============ TABS ============ -->
			<ul class="li-tabs">
				<?php
				$tab_labels = array(
					'overview'     => 'Overview',
					'health'       => 'Sensor Health',
					'scheme'       => 'Scheme-wise Status',
					'critical'     => 'Critical Locations',
					'events'       => 'Recent Events',
					'trend'        => 'Water Presence Trend',
					'availability' => 'Daily Availability',
					'reports'      => 'Reports',
					'sensor'       => 'Sensor Detail',
					'map'          => 'State Map View',
				);
				foreach ($tab_labels as $tk => $tl) {
					echo '<li class="li-tab' . ($active_tab === $tk ? ' active' : '') . '" data-tab="' . $tk . '">' . liEsc($tl) . '</li>';
				}
				?>
			</ul>

			<div class="li-tab-pane" id="tab-overview" style="display:<?php echo $active_tab === 'overview' ? '' : 'none'; ?>;">

			<!-- ============ SUMMARY STATISTICS ============ -->
			<div class="li-tiles">
				<?php foreach ($tiles as $t) { ?>
					<div class="li-tile <?php echo $t[2]; ?>">
						<div class="li-tile-dot"></div>
						<div class="li-tile-body">
							<span class="li-tile-label"><?php echo liEsc($t[0]); ?></span>
							<strong class="li-tile-value"><?php echo (int) $t[1]; ?></strong>
						</div>
					</div>
				<?php } ?>
			</div>

			<div class="li-tiles li-tiles-sec">
				<?php foreach ($secondary as $t) { ?>
					<div class="li-tile li-tile-plain">
						<div class="li-tile-body">
							<span class="li-tile-label"><?php echo liEsc($t[0]); ?></span>
							<strong class="li-tile-value"><?php echo (int) $t[1]; ?></strong>
						</div>
					</div>
				<?php } ?>
			</div>

			<!-- ============ OFFICE-WISE REPORT ============ -->
			<div class="card li-report">
				<div class="card-body">
					<h4 class="li-report-title">Office-wise Report</h4>
					<div class="table-responsive">
						<table class="table li-report-table">
							<thead>
								<tr>
									<th>Office / Division</th>
									<th class="text-center">Total Sensors</th>
									<th class="text-center">Water Present</th>
									<th class="text-center">Water Not Present</th>
									<th class="text-center">Offline / No Data</th>
									<th class="text-center">Data Today</th>
								</tr>
							</thead>
							<tbody>
								<?php if (count($office_rows) > 0) {
									foreach ($office_rows as $r) { ?>
										<tr>
											<td><?php echo liText($r['office_name']); ?></td>
											<td class="text-center"><?php echo (int) $r['total']; ?></td>
											<td class="text-center li-c-water"><?php echo (int) $r['water_present']; ?></td>
											<td class="text-center li-c-nowater"><?php echo (int) $r['water_absent']; ?></td>
											<td class="text-center li-c-offline"><?php echo (int) $r['offline']; ?></td>
											<td class="text-center"><?php echo (int) $r['received_today']; ?></td>
										</tr>
									<?php }
								} else { ?>
									<tr>
										<td colspan="6" class="li-report-empty">No offices match the current filters.</td>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			</div><!-- /tab-overview -->

			<!-- ============ SENSOR HEALTH ============ -->
			<div class="li-tab-pane" id="tab-health" style="display:<?php echo $active_tab === 'health' ? '' : 'none'; ?>;">
				<div class="card">
					<div class="card-body">
						<h4 class="li-report-title">Sensor Health Dashboard</h4>
						<?php liHealthPane($health); ?>
					</div>
				</div>
			</div>

			<!-- ============ SCHEME-WISE STATUS ============ -->
			<div class="li-tab-pane" id="tab-scheme" style="display:<?php echo $active_tab === 'scheme' ? '' : 'none'; ?>;">
				<div class="card">
					<div class="card-body">
						<?php liSchemePane($scheme_rows); ?>
					</div>
				</div>
			</div>

			<!-- ============ CRITICAL LOCATIONS ============ -->
			<div class="li-tab-pane" id="tab-critical" style="display:<?php echo $active_tab === 'critical' ? '' : 'none'; ?>;">
				<div class="card">
					<div class="card-body">
						<?php liCriticalPane($critical_rows); ?>
					</div>
				</div>
			</div>

			<!-- ============ RECENT EVENTS ============ -->
			<div class="li-tab-pane" id="tab-events" style="display:<?php echo $active_tab === 'events' ? '' : 'none'; ?>;">
				<div class="card">
					<div class="card-body">
						<h4 class="li-report-title">Recent Events</h4>
						<?php liEventsPane($events); ?>
					</div>
				</div>
			</div>

			<!-- ============ WATER PRESENCE TREND ============ -->
			<div class="li-tab-pane" id="tab-trend" style="display:<?php echo $active_tab === 'trend' ? '' : 'none'; ?>;">
				<div class="card">
					<div class="card-body">
						<h4 class="li-report-title">Water Presence Trend (24 Hours)</h4>
						<?php liTrendPane($trend_devices, $trend_id, $trend_points, $trend_now); ?>
					</div>
				</div>
			</div>

			<!-- ============ DAILY WATER AVAILABILITY ============ -->
			<div class="li-tab-pane" id="tab-availability" style="display:<?php echo $active_tab === 'availability' ? '' : 'none'; ?>;">
				<div class="card">
					<div class="card-body">
						<h4 class="li-report-title">Daily Water Availability</h4>
						<?php liAvailabilityPane($trend_devices, $av_schemes, $av_device, $av_scheme, $av_days, $av_rows); ?>
					</div>
				</div>
			</div>

			<!-- ============ REPORTS ============ -->
			<div class="li-tab-pane" id="tab-reports" style="display:<?php echo $active_tab === 'reports' ? '' : 'none'; ?>;">
				<div class="card">
					<div class="card-body">
						<h4 class="li-report-title">Reports Module</h4>
						<?php liReportsPane($rep_daily, $scheme_rows, $rep_uptime, $rep_offline, $rep_monthly); ?>
					</div>
				</div>
			</div>

			<!-- ============ SENSOR DETAIL ============ -->
			<div class="li-tab-pane" id="tab-sensor" style="display:<?php echo $active_tab === 'sensor' ? '' : 'none'; ?>;">
				<div class="card">
					<div class="card-body">
						<h4 class="li-report-title">Sensor Detail Page</h4>
						<?php liSensorPane($trend_devices, $detail_id, $sensor_detail, $sensor_history, $sensor_analytics); ?>
					</div>
				</div>
			</div>

			<!-- ============ STATE MAP VIEW ============ -->
			<div class="li-tab-pane" id="tab-map" style="display:<?php echo $active_tab === 'map' ? '' : 'none'; ?>;">
				<div class="card">
					<div class="card-body">
						<?php liMapPane($markers); ?>
					</div>
				</div>
			</div>

		</div>
	</div>

	<?php liDashboardStyles(); ?>
	<script>
		// live server clock (started from the PHP-rendered time above)
		(function () {
			var el = document.getElementById('liDashClock');
			if (!el) return;
			var t = new Date(<?php echo (int) (time() * 1000); ?>);
			function two(n) { return (n < 10 ? '0' : '') + n; }
			function tick() {
				t = new Date(t.getTime() + 1000);
				el.textContent = two(t.getDate()) + '-' + two(t.getMonth() + 1) + '-' + t.getFullYear() +
					' ' + two(t.getHours()) + ':' + two(t.getMinutes()) + ':' + two(t.getSeconds());
			}
			setInterval(tick, 1000);
		})();

		// Dependent cascade via server reload: picking a Circle (or Division)
		// resets the levels below it and submits, so the server re-renders the
		// child dropdowns AND the summary for the new selection. Reliable even
		// where inline option-building would be blocked, and keeps the report in
		// step with the chosen level.
		function liPick(level) {
			var f = document.forms['dashFilters'];
			if (!f) return;
			if (level === 'circle') { f.division.value = ''; f.subdiv.value = ''; }
			if (level === 'division') { f.subdiv.value = ''; }
			f.submit();
		}

		// Tabs: Overview (cards + office report) and State Map View. The map's
		// Leaflet library is only fetched the first time its tab is opened.
		(function () {
			var tabs = document.querySelectorAll('.li-tab');
			var panes = { overview: 'tab-overview', health: 'tab-health', scheme: 'tab-scheme', critical: 'tab-critical', events: 'tab-events', trend: 'tab-trend', availability: 'tab-availability', reports: 'tab-reports', sensor: 'tab-sensor', map: 'tab-map' };
			function activate(name, push) {
				for (var i = 0; i < tabs.length; i++) {
					tabs[i].classList.toggle('active', tabs[i].getAttribute('data-tab') === name);
				}
				for (var key in panes) {
					var el = document.getElementById(panes[key]);
					if (el) el.style.display = (key === name) ? '' : 'none';
				}
				// keep the top filter bar's hidden tab field pointing at this tab,
				// so applying filters (or a cascade auto-submit) stays here
				var ft = document.getElementById('liFilterTab');
				if (ft) ft.value = name;
				if (name === 'map' && typeof liInitMap === 'function') liInitMap();
				// remember the tab in the URL so a refresh / picker reload stays put
				if (push && window.history && history.replaceState) {
					try {
						var u = new URL(window.location.href);
						u.searchParams.set('tab', name);
						history.replaceState(null, '', u.toString());
					} catch (e) { }
				}
			}
			for (var i = 0; i < tabs.length; i++) {
				(function (t) {
					t.addEventListener('click', function () { activate(t.getAttribute('data-tab'), true); });
				})(tabs[i]);
			}
			// honor the server-rendered active tab on load (inits the map if it opens there)
			activate(<?php echo json_encode($active_tab); ?>, false);
		})();

		// Export the summary + active filters as a CSV (client-side, no reload).
		function liDashExport() {
			var rows = [
				['Water Resources Department, Punjab'],
				['Tail-End Water Presence Monitoring Dashboard'],
				['Generated', <?php echo json_encode(date('d-m-Y H:i:s')); ?>],
				['Last Data Refresh', <?php echo json_encode($last_refresh ? liDate($last_refresh) : '- - -'); ?>],
				[],
				['Filters'],
				['Circle', document.querySelector('[name=circle]').selectedOptions[0].text],
				['Division', document.querySelector('[name=division]').selectedOptions[0].text],
				['Sub-Division', document.querySelector('[name=subdiv]').selectedOptions[0].text],
				['Scheme', document.querySelector('[name=scheme]').selectedOptions[0].text],
				['RD of Outlet', document.querySelector('[name=rd]').value || 'All'],
				['Sensor Status', document.querySelector('[name=sensor]').selectedOptions[0].text],
				['Date From', document.querySelector('[name=from]').value || 'All'],
				['Date To', document.querySelector('[name=to]').value || 'All'],
				[],
				['Summary Statistics', 'Value'],
				['Total Sensors Installed', <?php echo (int) $stats['total']; ?>],
				['Water Present', <?php echo (int) $stats['water_present']; ?>],
				['Water Not Present', <?php echo (int) $stats['water_absent']; ?>],
				['Offline / No Data', <?php echo (int) $stats['offline']; ?>],
				['Data Received Today', <?php echo (int) $stats['received_today']; ?>],
				['Active Schemes', <?php echo (int) $stats['active_schemes']; ?>]
			];

			// Office-wise breakdown — the report is by office, not by circle.
			var office = <?php echo json_encode(array_map(function ($r) {
				return array(
					($r['office_name'] === null || trim((string) $r['office_name']) === '') ? 'Unassigned' : (string) $r['office_name'],
					(int) $r['total'], (int) $r['water_present'], (int) $r['water_absent'],
					(int) $r['offline'], (int) $r['received_today']
				);
			}, $office_rows)); ?>;
			rows.push([]);
			rows.push(['Office-wise Report']);
			rows.push(['Office / Division', 'Total Sensors', 'Water Present', 'Water Not Present', 'Offline / No Data', 'Data Today']);
			for (var i = 0; i < office.length; i++) rows.push(office[i]);

			var csv = rows.map(function (r) {
				return r.map(function (c) {
					c = (c === null || c === undefined) ? '' : String(c);
					return '"' + c.replace(/"/g, '""') + '"';
				}).join(',');
			}).join('\r\n');
			var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
			var a = document.createElement('a');
			a.href = URL.createObjectURL(blob);
			a.download = 'water-presence-dashboard.csv';
			document.body.appendChild(a);
			a.click();
			document.body.removeChild(a);
		}
	</script>
<?php }

function liDashboardStyles()
{
	?>
	<style>
		.li-dash-header {
			align-items: center;
			background: linear-gradient(90deg, #0d3c78 0%, #1666b0 100%);
			border-radius: 8px;
			color: #fff;
			display: flex;
			flex-wrap: wrap;
			gap: 12px;
			justify-content: space-between;
			margin: 6px 0 16px;
			padding: 16px 20px;
		}

		.li-dash-dept {
			font-size: 13px;
			letter-spacing: .04em;
			opacity: .9;
			text-transform: uppercase;
		}

		.li-dash-title {
			color: #fff;
			font-size: 22px;
			font-weight: 700;
			margin: 4px 0 0;
		}

		.li-dash-head-right {
			text-align: right;
		}

		.li-dash-meta {
			font-size: 13px;
			line-height: 1.5;
		}

		.li-dash-meta span {
			opacity: .85;
		}

		.li-dash-meta strong {
			margin-left: 4px;
		}

		.li-dash-filters {
			margin-bottom: 16px;
		}

		.li-dash-actions {
			align-items: center;
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
		}

		.li-btn-export {
			background: #2E8B57;
			border-color: #2E8B57;
			color: #fff;
		}

		.li-btn-export:hover {
			background: #26744a;
			color: #fff;
		}

		.li-dash-links {
			color: #64748b;
			font-size: 13px;
			margin-left: auto;
		}

		.li-dash-links a {
			color: #1666b0;
		}

		.li-tiles {
			display: grid;
			gap: 14px;
			grid-template-columns: repeat(4, minmax(0, 1fr));
			margin-bottom: 14px;
		}

		.li-tiles-sec {
			grid-template-columns: repeat(4, minmax(0, 1fr));
		}

		.li-tile {
			align-items: center;
			background: #fff;
			border: 1px solid #e2e8f0;
			border-left: 6px solid #94a3b8;
			border-radius: 8px;
			box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
			display: flex;
			gap: 12px;
			padding: 16px 18px;
		}

		.li-tile-dot {
			border-radius: 50%;
			flex: 0 0 auto;
			height: 14px;
			width: 14px;
		}

		.li-tile-body {
			display: flex;
			flex-direction: column;
		}

		.li-tile-label {
			color: #64748b;
			font-size: 13px;
			font-weight: 600;
		}

		.li-tile-value {
			color: #0f172a;
			font-size: 30px;
			font-weight: 700;
			line-height: 1.1;
		}

		.li-tile-total {
			border-left-color: #2563eb;
		}

		.li-tile-total .li-tile-dot {
			background: #2563eb;
		}

		.li-tile-water {
			border-left-color: #16a34a;
		}

		.li-tile-water .li-tile-dot {
			background: #16a34a;
		}

		.li-tile-nowater {
			border-left-color: #dc2626;
		}

		.li-tile-nowater .li-tile-dot {
			background: #dc2626;
		}

		.li-tile-offline {
			border-left-color: #64748b;
		}

		.li-tile-offline .li-tile-dot {
			background: #64748b;
		}

		.li-tile-plain {
			border-left-color: #cbd5e1;
		}

		.li-tabs {
			border-bottom: 2px solid #e2e8f0;
			display: flex;
			gap: 4px;
			list-style: none;
			margin: 0 0 16px;
			padding: 0;
		}

		.li-tab {
			border-bottom: 3px solid transparent;
			color: #475569;
			cursor: pointer;
			font-size: 14px;
			font-weight: 600;
			margin-bottom: -2px;
			padding: 10px 18px;
		}

		.li-tab:hover {
			color: #1666b0;
		}

		.li-tab.active {
			border-bottom-color: #1666b0;
			color: #1666b0;
		}

		.li-report {
			margin-top: 4px;
		}

		.li-report-title {
			color: #1d3473;
			font-size: 16px;
			font-weight: 700;
			margin: 0 0 12px;
		}

		.li-report-table {
			width: 100%;
			border-collapse: collapse;
		}

		.li-report-table th,
		.li-report-table td {
			border-bottom: 1px solid #e6edf7;
			font-size: 13px;
			padding: 9px 10px;
		}

		.li-report-table thead th {
			background: #f5f8fc;
			color: #1d3473;
			font-weight: 700;
			white-space: nowrap;
		}

		.li-report-table tbody tr:hover {
			background: #f9fbfe;
		}

		.li-report-table .li-c-water {
			color: #16a34a;
			font-weight: 700;
		}

		.li-report-table .li-c-nowater {
			color: #dc2626;
			font-weight: 700;
		}

		.li-report-table .li-c-offline {
			color: #64748b;
			font-weight: 700;
		}

		.li-report-empty {
			color: #65758f;
			padding: 16px;
			text-align: center;
		}

		@media (max-width: 991px) {

			.li-tiles,
			.li-tiles-sec {
				grid-template-columns: repeat(2, minmax(0, 1fr));
			}
		}

		@media (max-width: 575px) {
			.li-dash-head-right {
				text-align: left;
			}

			.li-tiles,
			.li-tiles-sec {
				grid-template-columns: 1fr;
			}
		}
	</style>
	<?php
}
?>
