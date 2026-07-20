<?php
/* ============================================================================
   Lift Irrigation — Monitoring : Daily Water Availability (bar chart) tab
   Bar chart of water-availability hours per day for a selected sensor OR scheme.
   Inline SVG, vanilla JS (no chart library). Data from liDailyAvailabilityData()
   + the pickers reuse liTrendDeviceOptions() / liSchemeOptions().
   ============================================================================ */

function liAvailHiddenFilters()
{
	global $component;
	$menuid = liMenu();
	$cid = isset($_GET['Cid']) ? $_GET['Cid'] : $menuid['component_headingid'];
	echo '<input type="hidden" name="c" value="' . liEsc($component) . '">';
	echo '<input type="hidden" name="Cid" value="' . liEsc($cid) . '">';
	echo '<input type="hidden" name="task" value="dashboard">';
	echo '<input type="hidden" name="tab" value="availability">';
	foreach (array('circle', 'division', 'subdiv', 'scheme', 'rd', 'sensor', 'from', 'to') as $k) {
		if (isset($_GET[$k]) && $_GET[$k] !== '') {
			echo '<input type="hidden" name="' . liEsc($k) . '" value="' . liEsc($_GET[$k]) . '">';
		}
	}
}

function liAvailabilityPane($devices, $schemes, $selDevice, $selScheme, $days, $rows)
{
	$selDevice = trim((string) $selDevice);
	$selScheme = trim((string) $selScheme);
	$days = (int) $days ?: 30;

	// build JS bars: [label, hours]
	$bars = array();
	foreach ($rows as $r) {
		$ts = strtotime((string) $r['day']);
		$label = $ts ? date('d-M', $ts) : (string) $r['day'];
		$bars[] = array($label, (int) $r['water_hours']);
	}
	?>
	<div class="li-av">
		<form name="availForm" method="get" action="index.php" class="li-av-pick">
			<?php liAvailHiddenFilters(); ?>
			<div class="li-av-row">
				<div>
					<label class="form-label">Sensor</label>
					<select name="avail_device" class="form-control" onchange="if(this.value){this.form.avail_scheme.value='';} this.form.submit()">
						<option value="">-- Choose sensor --</option>
						<?php foreach ($devices as $d) {
							$s = ((string) $d['device_id'] === $selDevice) ? 'selected' : '';
							$lbl = trim((string) $d['site_id']);
							if (trim((string) $d['tail_label']) !== '') $lbl .= ' — ' . $d['tail_label'];
							?>
							<option value="<?php echo liEsc($d['device_id']); ?>" <?php echo $s; ?>><?php echo liText($lbl); ?></option>
						<?php } ?>
					</select>
				</div>
				<div class="li-av-or">or</div>
				<div>
					<label class="form-label">Scheme</label>
					<select name="avail_scheme" class="form-control" onchange="if(this.value){this.form.avail_device.value='';} this.form.submit()">
						<option value="">-- Choose scheme --</option>
						<?php foreach ($schemes as $sc) {
							$s = ((string) $sc['code'] === $selScheme && $selDevice === '') ? 'selected' : '';
							?>
							<option value="<?php echo liEsc($sc['code']); ?>" <?php echo $s; ?>><?php echo liText($sc['name']); ?></option>
						<?php } ?>
					</select>
				</div>
				<div>
					<label class="form-label">Range</label>
					<select name="avail_days" class="form-control" onchange="this.form.submit()">
						<?php foreach (array(7 => 'Last 7 days', 14 => 'Last 14 days', 30 => 'Last 30 days') as $dv => $dl) {
							$s = ($days === $dv) ? 'selected' : '';
							?>
							<option value="<?php echo $dv; ?>" <?php echo $s; ?>><?php echo $dl; ?></option>
						<?php } ?>
					</select>
				</div>
			</div>
		</form>

		<?php if ($selDevice === '' && $selScheme === '') { ?>
			<div class="li-av-empty">Choose a sensor or a scheme to see its daily water-availability (hours per day).</div>
		<?php } elseif (count($bars) === 0) { ?>
			<div class="li-av-empty">No readings for the selected scope in this range.</div>
		<?php } else { ?>
			<div class="li-av-legend"><i></i> Water Availability (Hours / day, max 24)</div>
			<div id="liAvChart" class="li-av-chart"></div>
		<?php } ?>
	</div>

	<script>
		var LI_AV = <?php echo json_encode($bars); ?>;
		(function () {
			var host = document.getElementById('liAvChart');
			if (!host || !LI_AV.length) return;
			var n = LI_AV.length;
			var W = Math.max(680, n * 42), H = 300, padL = 42, padR = 16, padT = 16, padB = 54;
			var innerW = W - padL - padR, innerH = H - padT - padB;
			var max = 24;
			function y(v) { return padT + innerH - (v / max) * innerH; }
			var bw = innerW / n * 0.62, gap = innerW / n;

			var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="xMidYMid meet" class="li-av-svg">';
			// y gridlines at 0,6,12,18,24
			[0, 6, 12, 18, 24].forEach(function (g) {
				var gy = y(g);
				svg += '<line x1="' + padL + '" y1="' + gy + '" x2="' + (W - padR) + '" y2="' + gy + '" stroke="#eef2f7"/>';
				svg += '<text x="' + (padL - 8) + '" y="' + (gy + 4) + '" text-anchor="end" font-size="11" fill="#94a3b8">' + g + '</text>';
			});
			for (var i = 0; i < n; i++) {
				var v = LI_AV[i][1], cx = padL + i * gap + (gap - bw) / 2;
				var bh = (v / max) * innerH, by = y(v);
				var col = v >= 18 ? '#16a34a' : (v >= 8 ? '#f59e0b' : '#dc2626');
				svg += '<rect x="' + cx + '" y="' + by + '" width="' + bw + '" height="' + Math.max(0, bh) + '" rx="3" fill="' + col + '"><title>' + LI_AV[i][0] + ': ' + v + ' h</title></rect>';
				svg += '<text x="' + (cx + bw / 2) + '" y="' + (by - 4) + '" text-anchor="middle" font-size="10" fill="#475569">' + v + '</text>';
				if (n <= 31) svg += '<text x="' + (cx + bw / 2) + '" y="' + (H - padB + 16) + '" text-anchor="middle" font-size="10" fill="#64748b" transform="rotate(45 ' + (cx + bw / 2) + ' ' + (H - padB + 16) + ')">' + LI_AV[i][0] + '</text>';
			}
			svg += '</svg>';
			host.innerHTML = svg;
		})();
	</script>

	<?php liAvailabilityStyles();
}

function liAvailabilityStyles()
{
	?>
	<style>
		.li-av-row {
			align-items: flex-end;
			display: flex;
			flex-wrap: wrap;
			gap: 12px;
			margin-bottom: 14px;
		}

		.li-av-row>div {
			min-width: 190px;
		}

		.li-av-row .form-label {
			display: block;
			font-size: 12px;
			font-weight: 600;
			color: #475569;
			margin-bottom: 4px;
		}

		.li-av-or {
			color: #94a3b8;
			font-size: 12px;
			min-width: 0 !important;
			padding-bottom: 10px;
		}

		.li-av-legend {
			color: #64748b;
			font-size: 13px;
			margin-bottom: 6px;
		}

		.li-av-legend i {
			background: #16a34a;
			border-radius: 2px;
			display: inline-block;
			height: 11px;
			margin-right: 6px;
			vertical-align: middle;
			width: 16px;
		}

		.li-av-chart {
			border: 1px solid #e6edf7;
			border-radius: 8px;
			overflow-x: auto;
			padding: 6px;
		}

		.li-av-svg {
			display: block;
			height: auto;
			width: 100%;
		}

		.li-av-empty {
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
