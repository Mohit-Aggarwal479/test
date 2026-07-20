<?php
/* ============================================================================
   Lift Irrigation — Monitoring : Sensor Detail tab
   Pick a sensor to see its Information, Current Status, Last Reading, Historical
   Readings and Analytics. Data from liSensorDetail() / liSensorHistory() /
   liSensorAnalytics() in functions.php; shared li* helpers from view.php.
   ============================================================================ */

function liSensorHiddenFilters()
{
	global $component;
	$menuid = liMenu();
	$cid = isset($_GET['Cid']) ? $_GET['Cid'] : $menuid['component_headingid'];
	echo '<input type="hidden" name="c" value="' . liEsc($component) . '">';
	echo '<input type="hidden" name="Cid" value="' . liEsc($cid) . '">';
	echo '<input type="hidden" name="task" value="dashboard">';
	echo '<input type="hidden" name="tab" value="sensor">';
	foreach (array('circle', 'division', 'subdiv', 'scheme', 'rd', 'sensor', 'from', 'to') as $k) {
		if (isset($_GET[$k]) && $_GET[$k] !== '') {
			echo '<input type="hidden" name="' . liEsc($k) . '" value="' . liEsc($_GET[$k]) . '">';
		}
	}
}

function liSensorPane($devices, $selectedId, $detail, $history, $an)
{
	$selectedId = trim((string) $selectedId);
	?>
	<div class="li-sd">
		<form name="sensorForm" method="get" action="index.php" class="li-sd-pick">
			<?php liSensorHiddenFilters(); ?>
			<label class="form-label">Select Sensor</label>
			<select name="detail" class="form-control" onchange="this.form.submit()">
				<option value="">-- Choose a sensor --</option>
				<?php foreach ($devices as $d) {
					$s = ((string) $d['device_id'] === $selectedId) ? 'selected' : '';
					$lbl = trim((string) $d['site_id']);
					if (trim((string) $d['tail_label']) !== '') $lbl .= ' — ' . $d['tail_label'];
					if (trim((string) $d['scheme_name']) !== '') $lbl .= ' (' . $d['scheme_name'] . ')';
					?>
					<option value="<?php echo liEsc($d['device_id']); ?>" <?php echo $s; ?>><?php echo liText($lbl); ?></option>
				<?php } ?>
			</select>
		</form>

		<?php if ($selectedId === '' || $detail === null) {
			echo '<div class="li-sd-empty">' .
				($selectedId !== '' ? 'Sensor not found or outside your access.' : 'Choose a sensor to see its full detail.') .
				'</div>';
		} else {
			// derive status from the same 'state' the rest of the dashboard uses,
			// so 'unknown'/'stale' devices don't read differently here
			$state = strtolower(trim((string) $detail['state']));
			$offline = ($state === 'offline' || $state === 'unknown');
			$isWater = ((string) $detail['last_has_water'] === '1');
			$coord = ($detail['latitude'] !== null && $detail['longitude'] !== null && (string) $detail['latitude'] !== '' && (string) $detail['longitude'] !== '')
				? (number_format((float) $detail['latitude'], 6) . ', ' . number_format((float) $detail['longitude'], 6))
				: 'Not available';
			$installed = trim((string) $detail['installed_at']);
			$installedDisp = ($installed !== '' && $installed !== '0000-00-00 00:00:00' && $installed !== '0000-00-00') ? date('d-m-Y', strtotime($installed)) : '- - -';
			$lr = trim((string) $detail['last_reported_at']);
			$lrValid = ($lr !== '' && $lr !== '0000-00-00 00:00:00' && $lr !== '0000-00-00');
			$lrDisp = $lrValid ? date('Y-m-d H:i:s', strtotime($lr)) : '- - -';
			?>

			<!-- Sensor Information + Current Status -->
			<div class="li-sd-top">
				<div class="li-sd-card">
					<h5>Sensor Information</h5>
					<div class="li-sd-info">
						<div><span>Sensor ID</span><strong><?php echo liText($detail['site_id']); ?></strong></div>
						<div><span>Scheme Name</span><strong><?php echo liText($detail['scheme_name']); ?></strong></div>
						<div><span>Division</span><strong><?php echo liText($detail['office_name']); ?></strong></div>
						<div><span>Coordinates</span><strong><?php echo liEsc($coord); ?></strong></div>
						<div><span>Installation Date</span><strong><?php echo liEsc($installedDisp); ?></strong></div>
					</div>
				</div>
				<div class="li-sd-card li-sd-status">
					<h5>Current Status</h5>
					<?php if ($offline) { ?>
						<div class="li-sd-badge li-sd-off">Sensor Offline</div>
					<?php } elseif ($isWater) { ?>
						<div class="li-sd-badge li-sd-yes">● Water Present</div>
					<?php } else { ?>
						<div class="li-sd-badge li-sd-no">● Water Not Present</div>
					<?php } ?>
					<div class="li-sd-last">
						<span>Last Reading</span>
						<strong><?php echo liEsc($lrDisp); ?></strong>
					</div>
				</div>
			</div>

			<!-- Analytics -->
			<div class="li-sd-card">
				<h5>Analytics</h5>
				<div class="li-sd-analytics">
					<div class="li-sd-metric">
						<span>Water Availability Today</span>
						<strong><?php echo (int) $an['avail_today']; ?> <em>/ 24 h</em></strong>
					</div>
					<div class="li-sd-metric">
						<span>Water Availability (7 Days)</span>
						<strong><?php echo (float) $an['avail_7d_pct']; ?>% <em><?php echo (int) $an['avail_7d_hours']; ?> h</em></strong>
					</div>
					<div class="li-sd-metric">
						<span>Communication Uptime (7 Days)</span>
						<strong><?php echo (float) $an['uptime_pct']; ?>%</strong>
					</div>
					<div class="li-sd-metric">
						<span>Status Changes (7 Days)</span>
						<strong><?php echo (int) $an['changes']; ?></strong>
					</div>
				</div>
			</div>

			<!-- Historical Readings -->
			<div class="li-sd-card">
				<h5>Historical Readings <small>(latest <?php echo count($history); ?>)</small></h5>
				<div class="table-responsive">
					<table class="table li-sd-hist">
						<thead>
							<tr>
								<th>Time</th>
								<th>Status</th>
							</tr>
						</thead>
						<tbody>
							<?php if (count($history) > 0) {
								foreach ($history as $hr) {
									$present = ((string) $hr['has_water'] === '1');
									$t = trim((string) $hr['t']);
									$ts = $t !== '' ? strtotime($t) : false;
									?>
									<tr>
										<td><?php echo $ts ? liEsc(date('d-m-Y H:i', $ts)) : '- - -'; ?></td>
										<td>
											<span class="li-sd-dot <?php echo $present ? 'li-sd-dp' : 'li-sd-da'; ?>"></span>
											<?php echo $present ? 'Present' : 'Absent'; ?>
										</td>
									</tr>
								<?php }
							} else { ?>
								<tr>
									<td colspan="2" class="li-sd-empty2">No readings recorded.</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php } ?>
	</div>
	<?php liSensorStyles();
}

function liSensorStyles()
{
	?>
	<style>
		.li-sd-pick {
			margin-bottom: 16px;
			max-width: 460px;
		}

		.li-sd-pick .form-label {
			display: block;
			font-size: 12px;
			font-weight: 600;
			color: #475569;
			margin-bottom: 4px;
		}

		.li-sd-top {
			display: grid;
			gap: 14px;
			grid-template-columns: 2fr 1fr;
			margin-bottom: 14px;
		}

		.li-sd-card {
			background: #fff;
			border: 1px solid #e6edf7;
			border-radius: 8px;
			margin-bottom: 14px;
			padding: 16px;
		}

		.li-sd-card h5 {
			border-bottom: 1px solid #eef2f7;
			color: #1d3473;
			font-size: 15px;
			font-weight: 700;
			margin: 0 0 12px;
			padding-bottom: 8px;
		}

		.li-sd-card h5 small {
			color: #94a3b8;
			font-weight: 400;
		}

		.li-sd-info {
			display: grid;
			gap: 10px;
			grid-template-columns: repeat(2, minmax(0, 1fr));
		}

		.li-sd-info div,
		.li-sd-metric {
			background: #f9fbfe;
			border: 1px solid #eef2f7;
			border-radius: 6px;
			padding: 9px 12px;
		}

		.li-sd-info span,
		.li-sd-metric span,
		.li-sd-last span {
			color: #65758f;
			display: block;
			font-size: 11px;
			font-weight: 700;
			letter-spacing: .02em;
			margin-bottom: 4px;
			text-transform: uppercase;
		}

		.li-sd-info strong {
			color: #21376f;
			font-size: 14px;
		}

		.li-sd-status {
			text-align: center;
		}

		.li-sd-badge {
			border-radius: 8px;
			color: #fff;
			font-size: 17px;
			font-weight: 700;
			margin: 8px 0 14px;
			padding: 14px;
		}

		.li-sd-yes {
			background: #16a34a;
		}

		.li-sd-no {
			background: #dc2626;
		}

		.li-sd-off {
			background: #64748b;
		}

		.li-sd-last strong {
			color: #21376f;
			font-size: 15px;
		}

		.li-sd-analytics {
			display: grid;
			gap: 10px;
			grid-template-columns: repeat(4, minmax(0, 1fr));
		}

		.li-sd-metric strong {
			color: #1d3473;
			font-size: 20px;
		}

		.li-sd-metric em {
			color: #94a3b8;
			font-size: 12px;
			font-style: normal;
		}

		.li-sd-hist {
			width: 100%;
			border-collapse: collapse;
		}

		.li-sd-hist th,
		.li-sd-hist td {
			border-bottom: 1px solid #eef2f7;
			font-size: 13px;
			padding: 8px 10px;
			text-align: left;
		}

		.li-sd-hist thead th {
			background: #f7f9fd;
			color: #1d3473;
		}

		.li-sd-dot {
			border-radius: 50%;
			display: inline-block;
			height: 9px;
			margin-right: 7px;
			vertical-align: middle;
			width: 9px;
		}

		.li-sd-dp {
			background: #16a34a;
		}

		.li-sd-da {
			background: #dc2626;
		}

		.li-sd-empty,
		.li-sd-empty2 {
			background: #f9fbfe;
			border: 1px solid #e7edf7;
			border-radius: 8px;
			color: #65758f;
			padding: 22px;
			text-align: center;
		}

		.li-sd-empty2 {
			border: 0;
			background: none;
		}

		@media (max-width: 767px) {

			.li-sd-top,
			.li-sd-info,
			.li-sd-analytics {
				grid-template-columns: 1fr;
			}
		}
	</style>
	<?php
}
?>
