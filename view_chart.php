<?php
/* ============================================================================
   Lift Irrigation — Monitoring component (calendar bar-graph view)
   Loaded on demand by controller.php for task=calendar&view=chart. Draws a
   pure-CSS bar chart: X axis = dates of the month, Y axis = total water-present
   hours summed across all (office-scoped) tails. Shared helpers live in
   view.php; data helpers (liCalDevices/liCalMonthPercent) live in functions.php.
   ============================================================================ */

function showCalendarChart()
{
	$ym = isset($_GET['ym']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['ym'])
		? $_GET['ym'] : date('Y-m');
	$first = strtotime($ym . '-01');
	$year = (int) date('Y', $first);
	$month = (int) date('n', $first);
	$days_in_month = (int) date('t', $first);
	$today = date('Y-m-d');

	$devices = liCalDevices();
	$matrix = liCalMonthPercent($year, $month); // [device_id][day] => hours, water_hours

	// Total water-present hours per day, summed across the office-scoped tails.
	$totals = array();
	$grand = 0;
	for ($day = 1; $day <= $days_in_month; $day++) {
		$sum = 0;
		foreach ($devices as $dev) {
			$did = (int) $dev['id'];
			if (isset($matrix[$did][$day])) {
				$sum += (int) $matrix[$did][$day]['water_hours'];
			}
		}
		$totals[$day] = $sum;
		$grand += $sum;
	}

	$maxVal = 0;
	foreach ($totals as $v) {
		if ($v > $maxVal) {
			$maxVal = $v;
		}
	}
	$yTop = $maxVal > 0 ? $maxVal : 1;

	// y-axis gridline labels, top -> bottom (0)
	$ticks = array();
	for ($i = 4; $i >= 0; $i--) {
		$ticks[] = (int) round($yTop * $i / 4);
	}

	$prev_ym = date('Y-m', strtotime($ym . '-01 -1 month'));
	$next_ym = date('Y-m', strtotime($ym . '-01 +1 month'));
	?>
	<div class="main-content">
		<div class="container">
			<div class="li-toolbar">
				<div>
					<h3 class="mb-1">Water Hours &mdash; <?php echo liEsc(date('F Y', $first)); ?></h3>
					<div class="text-muted">Total water-present hours per day across all tails
						(<?php echo (int) $grand; ?> h this month)</div>
				</div>
				<div class="li-cal-nav">
					<a href="<?php echo liEsc(liCalendarUrl(array('view' => 'chart', 'ym' => $prev_ym))); ?>"
						class="btn btn-secondary">&lsaquo; Prev</a>
					<a href="<?php echo liEsc(liCalendarUrl(array('view' => 'chart'))); ?>"
						class="btn btn-secondary">This month</a>
					<a href="<?php echo liEsc(liCalendarUrl(array('view' => 'chart', 'ym' => $next_ym))); ?>"
						class="btn btn-secondary">Next &rsaquo;</a>
					<a href="<?php echo liEsc(liCalendarUrl(array('ym' => $ym))); ?>" class="btn btn-secondary">Grid view</a>
					<a href="<?php echo liEsc(liListUrl()); ?>" class="btn btn-primary">Back to list</a>
				</div>
			</div>

			<div class="card">
				<div class="card-body">
					<?php if (empty($devices)) { ?>
						<div class="li-empty">No active devices found..</div>
					<?php } else { ?>
						<div style="overflow-x:auto;">
							<div class="li-chart">
								<div class="li-chart-ytitle">Water hours</div>
								<div class="li-chart-yaxis">
									<?php foreach ($ticks as $tk) { ?>
										<div><?php echo (int) $tk; ?></div>
									<?php } ?>
								</div>
								<div class="li-chart-main">
									<div class="li-chart-plot">
										<?php for ($day = 1; $day <= $days_in_month; $day++) {
											$v = $totals[$day];
											$pct = $yTop > 0 ? ($v * 100 / $yTop) : 0;
											$date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
											$is_today = $date_str === $today;
											$tip = date('d M Y', strtotime($date_str)) . ' — ' . $v . ' water-hour' . ($v == 1 ? '' : 's');
											?>
											<div class="li-chart-col" title="<?php echo liEsc($tip); ?>">
												<div class="li-chart-bar<?php echo $is_today ? ' li-chart-today' : ''; ?>"
													style="height:<?php echo $pct; ?>%;"></div>
											</div>
										<?php } ?>
									</div>
									<div class="li-chart-labels">
										<?php for ($day = 1; $day <= $days_in_month; $day++) { ?>
											<div class="li-chart-xlabel"><?php echo sprintf('%02d', $day); ?></div>
										<?php } ?>
									</div>
									<div class="li-chart-axistitle">Date</div>
								</div>
							</div>
						</div>
						<div class="text-muted" style="font-size:12px; margin-top:8px;">
							Y axis = total water-present hours summed over all tails &middot; X axis = date &middot; hover a
							bar for its value.
						</div>
					<?php } ?>
				</div>
			</div>
		</div>
	</div>
	<?php liStyles(); ?>
	<?php liChartStyles(); ?>
<?php
}

function liChartStyles()
{
	?>
	<style>
		.li-chart {
			display: flex;
			gap: 8px;
			min-width: 560px;
		}

		.li-chart-ytitle {
			writing-mode: vertical-rl;
			transform: rotate(180deg);
			color: #65758f;
			font-size: 11px;
			font-weight: 700;
			display: flex;
			align-items: center;
			padding-bottom: 22px;
		}

		.li-chart-yaxis {
			display: flex;
			flex-direction: column;
			justify-content: space-between;
			height: 280px;
			min-width: 30px;
			text-align: right;
			font-size: 11px;
			color: #65758f;
		}

		.li-chart-main {
			flex: 1;
			min-width: 0;
		}

		.li-chart-plot {
			display: flex;
			align-items: flex-end;
			gap: 3px;
			height: 280px;
			padding: 0 4px;
			border-left: 1px solid #cbd5e1;
			border-bottom: 1px solid #cbd5e1;
			background-image: repeating-linear-gradient(to top, #eef2f7 0, #eef2f7 1px, transparent 1px, transparent 25%);
		}

		.li-chart-col {
			flex: 1 1 0;
			display: flex;
			align-items: flex-end;
			justify-content: center;
			height: 100%;
			min-width: 6px;
		}

		.li-chart-bar {
			width: 72%;
			min-width: 4px;
			min-height: 1px;
			background: #16a34a;
			border: 1px solid #12813c;
			border-bottom: 0;
			border-radius: 2px 2px 0 0;
		}

		.li-chart-today {
			background: #0b86c4;
			border-color: #086a9c;
		}

		.li-chart-labels {
			display: flex;
			gap: 3px;
			padding: 3px 4px 0;
		}

		.li-chart-xlabel {
			flex: 1 1 0;
			min-width: 6px;
			text-align: center;
			font-size: 9px;
			color: #65758f;
		}

		.li-chart-axistitle {
			color: #65758f;
			font-size: 11px;
			font-weight: 700;
			margin-top: 4px;
			text-align: center;
		}
	</style>
	<?php
}
?>
