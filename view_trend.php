<?php
/* ============================================================================
   Lift Irrigation — Monitoring : Water Presence Trend (24 Hours) tab
   Step line chart for one selected sensor — Y is 1 (Water Present) / 0 (Water
   Absent), X is time over the last 24 h. Rendered as inline SVG in vanilla JS
   (no chart library, works offline). Data: liTrendDeviceOptions() +
   liWaterTrend() in functions.php.
   ============================================================================ */

// Emit the current filter GET params as hidden inputs so the sensor picker's
// reload keeps the active filters (and stays on this tab).
function liTrendHiddenFilters()
{
	global $component;
	$menuid = liMenu();
	$cid = isset($_GET['Cid']) ? $_GET['Cid'] : $menuid['component_headingid'];
	echo '<input type="hidden" name="c" value="' . liEsc($component) . '">';
	echo '<input type="hidden" name="Cid" value="' . liEsc($cid) . '">';
	echo '<input type="hidden" name="task" value="dashboard">';
	echo '<input type="hidden" name="tab" value="trend">';
	foreach (array('circle', 'division', 'subdiv', 'scheme', 'rd', 'sensor', 'from', 'to') as $k) {
		if (isset($_GET[$k]) && $_GET[$k] !== '') {
			echo '<input type="hidden" name="' . liEsc($k) . '" value="' . liEsc($_GET[$k]) . '">';
		}
	}
}

function liTrendPane($devices, $selectedId, $points, $now)
{
	$selectedId = trim((string) $selectedId);

	// find the picked device's status for the header
	$sel = null;
	foreach ($devices as $d) {
		if ((string) $d['device_id'] === $selectedId) {
			$sel = $d;
			break;
		}
	}

	// build the JS point payload: [unixSeconds, 0|1]
	$js = array();
	foreach ($points as $p) {
		$ts = strtotime((string) $p['t']);
		if ($ts) {
			$js[] = array($ts, ((string) $p['v'] === '1') ? 1 : 0);
		}
	}
	?>
	<div class="li-trend">
		<div class="li-trend-head">
			<form name="trendForm" method="get" action="index.php" class="li-trend-pick">
				<?php liTrendHiddenFilters(); ?>
				<label class="form-label">Select Sensor</label>
				<select name="trend" class="form-control" onchange="this.form.submit()">
					<option value="">-- Choose a sensor --</option>
					<?php foreach ($devices as $d) {
						$sel_attr = ((string) $d['device_id'] === $selectedId) ? 'selected' : '';
						$lbl = trim((string) $d['site_id']);
						if (trim((string) $d['tail_label']) !== '') {
							$lbl .= ' — ' . $d['tail_label'];
						}
						if (trim((string) $d['scheme_name']) !== '') {
							$lbl .= ' (' . $d['scheme_name'] . ')';
						}
						?>
						<option value="<?php echo liEsc($d['device_id']); ?>" <?php echo $sel_attr; ?>>
							<?php echo liText($lbl); ?></option>
					<?php } ?>
				</select>
			</form>

			<?php if ($sel !== null) {
				$isWater = ((string) $sel['last_has_water'] === '1');
				$stCls = $isWater ? 'li-badge-water' : 'li-badge-nowater';
				$stTxt = $isWater ? 'Water Present' : 'Water Absent';
				?>
				<div class="li-trend-meta">
					<span class="li-trend-site"><?php echo liText($sel['site_id']); ?></span>
					<span><?php echo liText($sel['scheme_name']); ?></span>
					<span><?php echo liText($sel['office_name']); ?></span>
					<span class="li-badge <?php echo $stCls; ?>"><?php echo liEsc($stTxt); ?></span>
					<span class="li-trend-upd">Last update: <?php echo liAgo($sel['last_reported_at']); ?></span>
				</div>
			<?php } ?>
		</div>

		<?php if ($selectedId === '') { ?>
			<div class="li-trend-empty">Choose a sensor above to see its water-presence trend for the last 24 hours.</div>
		<?php } elseif (count($js) === 0) { ?>
			<div class="li-trend-empty">No readings for this sensor in the last 24 hours.</div>
		<?php } else { ?>
			<div class="li-trend-legend">
				<span><i style="background:#16a34a;"></i> 1 · Water Present</span>
				<span><i style="background:#dc2626;"></i> 0 · Water Absent</span>
			</div>
			<div id="liTrendChart" class="li-trend-chart"></div>
		<?php } ?>
	</div>

	<script>
		var LI_TREND = <?php echo json_encode($js); ?>;
		var LI_TREND_NOW = <?php echo (int) $now; ?>;

		(function () {
			var host = document.getElementById('liTrendChart');
			if (!host || !LI_TREND.length) return;

			var W = 1000, H = 280, padL = 64, padR = 24, padT = 24, padB = 46;
			var t1 = LI_TREND_NOW, t0 = t1 - 24 * 3600;
			var innerW = W - padL - padR;

			function x(t) { return padL + (Math.max(t0, Math.min(t1, t)) - t0) / (t1 - t0) * innerW; }
			function y(v) { return v === 1 ? padT + 14 : H - padB - 14; }
			function two(n) { return (n < 10 ? '0' : '') + n; }
			function hhmm(ts) { var d = new Date(ts * 1000); return two(d.getHours()) + ':' + two(d.getMinutes()); }

			var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="xMidYMid meet" class="li-trend-svg">';

			// Y reference lines + labels
			svg += '<line x1="' + padL + '" y1="' + y(1) + '" x2="' + (W - padR) + '" y2="' + y(1) + '" stroke="#e2e8f0"/>';
			svg += '<line x1="' + padL + '" y1="' + y(0) + '" x2="' + (W - padR) + '" y2="' + y(0) + '" stroke="#e2e8f0"/>';
			svg += '<text x="' + (padL - 10) + '" y="' + (y(1) + 4) + '" text-anchor="end" font-size="12" fill="#16a34a">1 Present</text>';
			svg += '<text x="' + (padL - 10) + '" y="' + (y(0) + 4) + '" text-anchor="end" font-size="12" fill="#dc2626">0 Absent</text>';

			// X gridlines + time labels every 3 hours
			for (var hh = 0; hh <= 24; hh += 3) {
				var tt = t0 + hh * 3600, gx = x(tt);
				svg += '<line x1="' + gx + '" y1="' + padT + '" x2="' + gx + '" y2="' + (H - padB) + '" stroke="#f1f5f9"/>';
				svg += '<text x="' + gx + '" y="' + (H - padB + 18) + '" text-anchor="middle" font-size="11" fill="#64748b">' + hhmm(tt) + '</text>';
			}

			// step line: horizontal at v[i] until next reading, vertical connector.
			// each horizontal segment is coloured by its value (green=1, red=0).
			var segs = '', dots = '';
			for (var i = 0; i < LI_TREND.length; i++) {
				var ts = LI_TREND[i][0], v = LI_TREND[i][1];
				var xNext = (i + 1 < LI_TREND.length) ? x(LI_TREND[i + 1][0]) : x(t1);
				var xa = x(ts), yv = y(v);
				var col = v === 1 ? '#16a34a' : '#dc2626';
				segs += '<line x1="' + xa + '" y1="' + yv + '" x2="' + xNext + '" y2="' + yv + '" stroke="' + col + '" stroke-width="2.5"/>';
				if (i + 1 < LI_TREND.length) {
					var yn = y(LI_TREND[i + 1][1]);
					if (yn !== yv) segs += '<line x1="' + xNext + '" y1="' + yv + '" x2="' + xNext + '" y2="' + yn + '" stroke="#94a3b8" stroke-width="1.5"/>';
				}
				dots += '<circle cx="' + xa + '" cy="' + yv + '" r="3" fill="' + col + '"/>';
			}
			svg += segs + dots + '</svg>';
			host.innerHTML = svg;
		})();
	</script>

	<?php liTrendStyles();
}

function liTrendStyles()
{
	?>
	<style>
		.li-trend-head {
			align-items: flex-end;
			display: flex;
			flex-wrap: wrap;
			gap: 16px;
			justify-content: space-between;
			margin-bottom: 14px;
		}

		.li-trend-pick {
			max-width: 420px;
			width: 100%;
		}

		.li-trend-pick .form-label {
			display: block;
			font-size: 12px;
			font-weight: 600;
			color: #475569;
			margin-bottom: 4px;
		}

		.li-trend-meta {
			align-items: center;
			color: #51658f;
			display: flex;
			flex-wrap: wrap;
			font-size: 13px;
			gap: 12px;
		}

		.li-trend-site {
			color: #1d3473;
			font-weight: 700;
		}

		.li-trend-upd {
			color: #94a3b8;
		}

		.li-badge {
			border-radius: 4px;
			color: #fff;
			font-size: 12px;
			font-weight: 700;
			padding: 2px 9px;
		}

		.li-badge-water {
			background: #16a34a;
		}

		.li-badge-nowater {
			background: #dc2626;
		}

		.li-trend-legend {
			color: #64748b;
			display: flex;
			font-size: 13px;
			gap: 18px;
			margin-bottom: 8px;
		}

		.li-trend-legend i {
			border-radius: 2px;
			display: inline-block;
			height: 11px;
			margin-right: 5px;
			vertical-align: middle;
			width: 16px;
		}

		.li-trend-chart {
			border: 1px solid #e6edf7;
			border-radius: 8px;
			padding: 6px;
		}

		.li-trend-svg {
			display: block;
			width: 100%;
			height: auto;
		}

		.li-trend-empty {
			background: #f9fbfe;
			border: 1px solid #e7edf7;
			border-radius: 8px;
			color: #65758f;
			padding: 26px;
			text-align: center;
		}
	</style>
	<?php
}
?>
