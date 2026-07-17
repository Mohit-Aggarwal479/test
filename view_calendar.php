<?php
/* ============================================================================
   Lift Irrigation — Monitoring component (calendar view)
   Loaded on demand by controller.php for task=calendar. Month grid is a
   tail x day matrix; clicking a day opens the hourly (tail x hour) matrix.
   Shared helpers live in view.php (always loaded first); calendar data
   helpers (liCalDevices/liCalMonthMatrix/liCalDayData) live in functions.php.
   ============================================================================ */

function showCalendarPage()
{
	// ?date=YYYY-MM-DD → hourly matrix for that day; else ?ym=YYYY-MM → month grid
	$date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) && strtotime($_GET['date'])
		? $_GET['date'] : '';
	if ($date !== '') {
		liCalendarDay($date);
	} else {
		$ym = isset($_GET['ym']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['ym'])
			? $_GET['ym'] : date('Y-m');
		liCalendarMonth($ym);
	}
}

function liCalendarMonth($ym)
{
	$first = strtotime($ym . '-01');
	$year = (int) date('Y', $first);
	$month = (int) date('n', $first);
	$days_in_month = (int) date('t', $first);
	$today = date('Y-m-d');

	$devices = liCalDevices();
	$matrix = liCalMonthPercent($year, $month);
	$now = time();

	$prev_ym = date('Y-m', strtotime($ym . '-01 -1 month'));
	$next_ym = date('Y-m', strtotime($ym . '-01 +1 month'));
	$colspan = $days_in_month + 3; // LSD ID + Site Name + days + Water days
	?>
	<div class="main-content">
		<div class="container">
			<div class="li-toolbar">
				<div>
					<h3 class="mb-1">Calendar &mdash; <?php echo liEsc(date('F Y', $first)); ?></h3>
					<div class="text-muted">All tails &middot; % of each day water was available (over 24 h) &middot; NC =
						day not yet complete &middot; click a day for the hourly view</div>
				</div>
				<div class="li-cal-nav">
					<a href="<?php echo liEsc(liCalendarUrl(array('ym' => $prev_ym))); ?>" class="btn btn-secondary">&lsaquo; Prev</a>
					<a href="<?php echo liEsc(liCalendarUrl()); ?>" class="btn btn-secondary">Today</a>
					<a href="<?php echo liEsc(liCalendarUrl(array('ym' => $next_ym))); ?>" class="btn btn-secondary">Next &rsaquo;</a>
					<a href="<?php echo liEsc(liCalendarUrl(array('view' => 'chart', 'ym' => $ym))); ?>"
						class="btn btn-primary">Bar graph</a>
					<a href="<?php echo liEsc(liListUrl()); ?>" class="btn btn-primary">Back to list</a>
				</div>
			</div>

			<div class="card">
				<div class="table-responsive">
					<table class="li-hr-table">
						<thead>
							<tr>
								<th class="li-hr-dev">LSD ID</th>
								<th>Site Name</th>
								<?php for ($day = 1; $day <= $days_in_month; $day++) {
									$date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
									$is_today = $date_str === $today;
									?>
									<th class="<?php echo $is_today ? 'li-cal-today' : ''; ?>">
										<a class="li-cal-daynum"
											href="<?php echo liEsc(liCalendarUrl(array('date' => $date_str))); ?>"><?php echo sprintf('%02d', $day); ?></a>
									</th>
								<?php } ?>
								<th>Month avg</th>
							</tr>
						</thead>
						<tbody>
							<?php if (!empty($devices)) {
								foreach ($devices as $dev) {
									$dev_id = (int) $dev['id'];
									$days = isset($matrix[$dev_id]) ? $matrix[$dev_id] : array();
									$pct_sum = 0;
									$pct_days = 0;
									?>
									<tr>
										<td class="li-hr-dev">
											<a href="<?php echo liEsc(liDetailUrl($dev_id)); ?>"
												style="color:#36f; font-weight:600;"><?php echo liText($dev['site_id']); ?></a>
										</td>
										<td class="li-hr-dev">
											<div style="color:black; font-weight:600;"><?php echo liText($dev['site_name']); ?></div>
										</td>
										<?php for ($day = 1; $day <= $days_in_month; $day++) {
											$day_end = strtotime(sprintf('%04d-%02d-%02d 00:00:00', $year, $month, $day) . ' +1 day');
											$completed = $now >= $day_end;
											$cd = isset($days[$day]) ? $days[$day] : null;
											$dstr = sprintf('%02d-%02d-%04d', $day, $month, $year);

											if (!$completed) {
												// the whole 24 h has not elapsed yet — not calculated
												echo '<td class="li-cal-nc" title="' . $dstr . ' — day not complete (NC)">NC</td>';
												continue;
											}
											if ($cd === null || (int) $cd['hours'] === 0) {
												// day is over but the poller logged nothing
												echo '<td class="li-cal-none" title="' . $dstr . ' — no readings">&ndash;</td>';
												continue;
											}
											// % of the 24 h day that water was available
											$wh = (int) $cd['water_hours'];
											$pct = (int) round($wh * 100 / 24);
											$pct_sum += $pct;
											$pct_days++;
											$hue = (int) round($pct * 1.2); // 0% red -> 100% green
											$tip = $dstr . ' — water ' . $pct . '% of day (' . $wh . '/24 h; '
												. (int) $cd['hours'] . ' h with data)';
											echo '<td class="li-cal-pct" style="background:hsl(' . $hue . ', 70%, 42%);" title="'
												. liEsc($tip) . '">' . $pct . '%</td>';
										} ?>
										<td class="li-hr-total"><?php echo $pct_days > 0 ? (int) round($pct_sum / $pct_days) . '%' : 'NC'; ?></td>
									</tr>
								<?php }
							} else { ?>
								<tr>
									<td colspan="<?php echo (int) $colspan; ?>" style="font-size:15px; padding:12px;">No active
										devices found..</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="li-cal-legend">
				<span>Cell = % of the day water was available (over 24 h):</span>
				<span><span class="li-cal-key" style="background:hsl(0, 70%, 42%);"></span> 0%</span>
				<span><span class="li-cal-key" style="background:hsl(60, 70%, 42%);"></span> 50%</span>
				<span><span class="li-cal-key" style="background:hsl(120, 70%, 42%);"></span> 100%</span>
				<span><span class="li-cal-key" style="background:#eef2f7; border:1px solid #dfe7f2;"></span> NC = day not
					complete</span>
				<span><span class="li-cal-key" style="background:#fff; border:1px dashed #dfe7f2;"></span> &ndash; no
					reading</span>
			</div>
		</div>
	</div>
	<?php liStyles(); ?>
	<?php liCalStyles(); ?>
<?php
}

function liCalendarDay($date)
{
	$time = strtotime($date);
	$devices = liCalDevices();
	$matrix = liCalDayQuarters($date);

	$prev_day = date('Y-m-d', strtotime($date . ' -1 day'));
	$next_day = date('Y-m-d', strtotime($date . ' +1 day'));
	$ym = date('Y-m', $time);
	?>
	<div class="main-content">
		<div class="container">
			<div class="li-toolbar">
				<div>
					<h3 class="mb-1">Hourly &mdash; <?php echo liEsc(date('d M Y (l)', $time)); ?></h3>
					<div class="text-muted">All devices &middot; green = water
						present in that 15 min, red = no water (hover a segment for the time)</div>
				</div>
				<div class="li-cal-nav">
					<a href="<?php echo liEsc(liCalendarUrl(array('date' => $prev_day))); ?>" class="btn btn-secondary">&lsaquo; Prev day</a>
					<a href="<?php echo liEsc(liCalendarUrl(array('date' => $next_day))); ?>" class="btn btn-secondary">Next day &rsaquo;</a>
					<a href="<?php echo liEsc(liCalendarUrl(array('ym' => $ym))); ?>" class="btn btn-secondary">Month view</a>
					<a href="<?php echo liEsc(liListUrl()); ?>" class="btn btn-primary">Back to list</a>
				</div>
			</div>

			<div class="card">
				<div class="table-responsive">
					<table class="li-hr-table">
						<thead>
							<tr>
								<th class="li-hr-dev">LSD ID</th>
								<th>Site Name</th>

								<?php for ($h = 0; $h < 24; $h++) { ?>
									<th><?php echo sprintf('%02d', $h); ?></th>
								<?php } ?>

								<th>Latest</th>
							</tr>
						</thead>
						<tbody>
							<?php if (!empty($devices)) {
								foreach ($devices as $dev) {
									$dev_id = (int) $dev['id'];
									$hours = isset($matrix[$dev_id]) ? $matrix[$dev_id] : array();
									$day_last = null;    // latest reading of the whole day
									$day_last_ts = null;
									?>
									<tr>
										<td class="li-hr-dev">
											<a href="<?php echo liEsc(liDetailUrl($dev_id)); ?>"
												style="color:#36f; font-weight:600;"><?php echo liText($dev['site_id']); ?></a>
											<!-- <div class="small text-muted"><?php echo liText($dev['site_name']); ?> &middot;
												<?php echo liText($dev['scheme_name']); ?></div> -->
										</td>
										<td class="li-hr-dev">
											<div style="color:black; font-weight:600;"><?php echo liText($dev['site_name']); ?> </div>
										</td>
										<?php for ($h = 0; $h < 24; $h++) {
											$qs = isset($hours[$h]) ? $hours[$h] : array();
											// four 15-min segments, green where water was present
											$seg = '';
											for ($q = 0; $q < 4; $q++) {
												$s = sprintf('%02d:%02d', $h, $q * 15);
												$e = sprintf('%02d:%02d', $h, $q * 15 + 14);
												if (!isset($qs[$q])) {
													$c = 'li-cal-none';
													$lab = 'no reading';
												} elseif ((int) $qs[$q]['water'] === 1) {
													$c = 'li-cal-w';
													$lab = 'Water';
												} elseif ((int) $qs[$q]['nowater'] === 1) {
													$c = 'li-cal-nw';
													$lab = 'No water';
												} else {
													$c = 'li-cal-unk';
													$lab = 'status unknown';
												}
												// track the latest quarter that has a reading for the Latest column
												if (isset($qs[$q]) && ($day_last_ts === null || ($h * 4 + $q) >= $day_last_ts)) {
													$day_last_ts = $h * 4 + $q;
													$day_last = array('h' => $h, 'q' => $q,
														'water' => (int) $qs[$q]['water'], 'nowater' => (int) $qs[$q]['nowater']);
												}
												$seg .= '<span class="li-q-seg ' . $c . '" title="' . liEsc($s . '-' . $e . ' ' . $lab) . '"></span>';
											}
											echo '<td class="li-q-cell"><div class="li-q">' . $seg . '</div></td>';
										} ?>
										<td class="li-hr-total">
											<?php if ($day_last !== null) {
												echo $day_last['water'] ? 'Yes' : ($day_last['nowater'] ? 'No' : 'Unknown');
												echo '<div class="small">@ ' . sprintf('%02d:%02d', $day_last['h'], $day_last['q'] * 15) . '</div>';
											} else {
												echo '- - -';
											} ?>
										</td>
									</tr>
								<?php }
							} else { ?>
								<tr>
									<td colspan="27" style="font-size:15px; padding:12px;">No active devices found..</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="li-cal-legend">
				<span><span class="li-cal-key" style="background:#16a34a;"></span> Water present</span>
				<span><span class="li-cal-key" style="background:#dc2626;"></span> No water</span>
				<span><span class="li-cal-key" style="background:#f8fafc; border:1px solid #dfe7f2;"></span> Reading, status
					unknown</span>
				<span><span class="li-cal-key" style="background:#fff; border:1px dashed #dfe7f2;"></span> No reading</span>
				<span>Each hour = 4 segments (:00 :15 :30 :45)</span>
			</div>
		</div>
	</div>
	<?php liStyles(); ?>
	<?php liCalStyles(); ?>
<?php
}

function liCalStyles()
{
	?>
	<style>
		.li-cal-nav {
			display: flex;
			flex-wrap: wrap;
			gap: 6px;
		}

		.li-cal-table {
			background: #fff;
			border-collapse: collapse;
			width: 100%;
		}

		.li-cal-table th,
		.li-cal-table td {
			border: 1px solid #dfe7f2;
		}

		.li-cal-table thead th {
			background: #f7f9fd;
			color: #1d3473;
			font-size: 12px;
			padding: 7px;
			text-align: center;
		}

		.li-cal-cell {
			height: 92px;
			min-width: 108px;
			padding: 6px 7px;
			vertical-align: top;
			white-space: normal;
		}

		.li-cal-off {
			background: #fafbfd;
		}

		.li-cal-today {
			box-shadow: inset 0 0 0 2px #0b86c4;
		}

		.li-cal-daynum {
			color: #1d3473;
			display: inline-block;
			font-size: 14px;
			font-weight: 700;
			margin-bottom: 4px;
			text-decoration: none;
		}

		.li-cal-daynum:hover {
			color: #0b86c4;
		}

		.li-cal-chip {
			background: #f1f5f9;
			border-radius: 4px;
			color: #475569;
			display: inline-block;
			font-size: 11px;
			margin: 0 4px 3px 0;
			padding: 1px 6px;
			white-space: nowrap;
		}

		.li-cal-bar {
			background: #e2e8f0;
			border-radius: 3px;
			height: 6px;
			margin-top: 4px;
			overflow: hidden;
		}

		.li-cal-bar span {
			background: #0b86c4;
			display: block;
			height: 100%;
		}

		.li-cal-legend {
			color: #65758f;
			display: flex;
			flex-wrap: wrap;
			font-size: 12px;
			gap: 14px;
			margin-top: 10px;
		}

		.li-cal-legend>span {
			align-items: center;
			display: inline-flex;
			gap: 6px;
		}

		.li-cal-key {
			border-radius: 3px;
			display: inline-block;
			height: 12px;
			width: 12px;
		}

		.li-hr-table {
			background: #fff;
			border-collapse: collapse;
			width: 100%;
		}

		.li-hr-table {
			border: 2px solid #0c0c0c;
		}

		.li-hr-table th
		{
			border: 2px solid #0c0c0c;
			font-size: 11px;
			min-width: 34px;
			padding: 4px 3px;
			text-align: center;
		}

		.li-hr-table td {
			border: 2px solid #0c0c0c;
			font-size: 11px;
			min-width: 34px;
			padding: 4px 3px;
			text-align: center;
		}

		.li-hr-table thead th {
			background: #f7f9fd;
			color: #1d3473;
		}

		.li-hr-dev {
			background: #fff;
			left: 0;
			min-width: 200px;
			padding: 6px 8px !important;
			position: sticky;
			text-align: left !important;
			white-space: normal;
			z-index: 1;
		}

		.li-hr-total {
			background: #f7f9fd;
			color: #1d3473;
			font-weight: 600;
			min-width: 64px;
			white-space: nowrap;
		}

		.li-cal-w {
			background: #16a34a;
			color: #fff;
		}

		.li-cal-nw {
			background: #dc2626;
			color: #fff;
		}


		.li-cal-unk {
			background: #f8fafc;
			color: #94a3b8;
		}

		.li-cal-none {
			background: #fff;
			color: #cbd5e1;
		}

		.li-q-cell {
			min-width: 56px;
			padding: 0 !important;
			height: 26px;
		}

		.li-q {
			display: flex;
			height: 100%;
			min-height: 26px;
		}

		.li-q-seg {
			flex: 1 1 25%;
			box-shadow: inset 0 0 0 1px #cbd5e1;
		}

		.li-cal-pct {
			color: #fff;
			font-weight: 600;
		}

		.li-cal-nc {
			background: #eef2f7;
			color: #94a3b8;
			font-style: italic;
		}

		@media (max-width:767px) {
			.li-cal-cell {
				height: 70px;
				min-width: 84px;
			}
		}
	</style>
	<?php
}
?>
