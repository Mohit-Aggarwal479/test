<?php
/* ============================================================================
   Lift Irrigation — Monitoring : Reports module tab
   Five reports, each exportable to Excel (.xls) and PDF (print), built entirely
   client-side (no library). Data comes from the liReport* helpers +
   liSchemeWiseStatus() in functions.php.
   ============================================================================ */

function liReportsPane($daily, $scheme, $uptime, $offline, $monthly)
{
	// helpers to shape each dataset into [head, rows-of-cells]
	$statusOf = function ($state, $water) {
		$st = strtolower(trim((string) $state));
		if ($st === 'offline' || $st === 'unknown') {
			return 'Offline';
		}
		return ((string) $water === '1') ? 'Water Present' : 'Water Absent';
	};
	$agoLong = function ($v) {
		$v = trim((string) $v);
		if ($v === '') return 'No Data';
		$t = strtotime($v);
		return $t ? date('d-m-Y H:i', $t) : $v;
	};

	$reports = array();

	// 1) Daily Water Presence
	$rows = array();
	foreach ($daily as $r) {
		$rows[] = array($r['site_id'], $r['scheme_name'], $r['office_name'], $statusOf($r['state'], $r['last_has_water']), $agoLong($r['last_reported_at']));
	}
	$reports[] = array('key' => 'daily', 'title' => 'Daily Water Presence Report',
		'head' => array('Sensor ID', 'Scheme', 'Division', 'Status', 'Last Reading'), 'rows' => $rows);

	// 2) Scheme-wise Status
	$rows = array();
	foreach ($scheme as $r) {
		$rows[] = array($r['scheme_name'], $r['office_name'], (int) $r['total'], (int) $r['water_present'], (int) $r['water_absent'], (int) $r['offline']);
	}
	$reports[] = array('key' => 'scheme', 'title' => 'Scheme-wise Status Report',
		'head' => array('Scheme', 'Division', 'Total Sensors', 'Water Present', 'Water Absent', 'Offline'), 'rows' => $rows);

	// 3) Sensor Uptime
	$rows = array();
	foreach ($uptime as $r) {
		$rows[] = array($r['site_id'], $r['scheme_name'], $r['office_name'], (float) $r['uptime_pct'] . '%');
	}
	$reports[] = array('key' => 'uptime', 'title' => 'Sensor Uptime Report (7 days)',
		'head' => array('Sensor ID', 'Scheme', 'Division', 'Uptime %'), 'rows' => $rows);

	// 4) Offline Sensor
	$rows = array();
	foreach ($offline as $r) {
		$last = $r['last_reported_at'];
		if ($last === null || trim((string) $last) === '') $last = $r['last_polled_at'];
		$rows[] = array($r['site_id'], $r['scheme_name'], $r['office_name'], $agoLong($last));
	}
	$reports[] = array('key' => 'offline', 'title' => 'Offline Sensor Report',
		'head' => array('Sensor ID', 'Scheme', 'Division', 'Last Data'), 'rows' => $rows);

	// 5) Monthly Water Availability
	$rows = array();
	foreach ($monthly as $r) {
		$rows[] = array($r['site_id'], $r['scheme_name'], $r['office_name'], (int) $r['water_hours']);
	}
	$reports[] = array('key' => 'monthly', 'title' => 'Monthly Water Availability Report',
		'head' => array('Sensor ID', 'Scheme', 'Division', 'Water Hours (this month)'), 'rows' => $rows);

	$payload = array();
	foreach ($reports as $rp) {
		$payload[$rp['key']] = array('title' => $rp['title'], 'head' => $rp['head'], 'rows' => $rp['rows']);
	}
	?>
	<div class="li-rep">
		<p class="li-rep-intro">Generate any report as <strong>Excel</strong> or <strong>PDF</strong>. Reports honor the
			current dashboard filters (Circle / Division / Scheme, etc.).</p>
		<div class="li-rep-list">
			<?php $i = 0;
			foreach ($reports as $rp) {
				$i++; ?>
				<div class="li-rep-card">
					<div class="li-rep-info">
						<span class="li-rep-num"><?php echo $i; ?></span>
						<div>
							<div class="li-rep-title"><?php echo liEsc($rp['title']); ?></div>
							<div class="li-rep-meta"><?php echo count($rp['rows']); ?> row<?php echo count($rp['rows']) === 1 ? '' : 's'; ?></div>
						</div>
					</div>
					<div class="li-rep-actions">
						<button type="button" class="btn li-btn-xls" onclick="liRepExport('<?php echo $rp['key']; ?>','xls')">Excel</button>
						<button type="button" class="btn li-btn-pdf" onclick="liRepExport('<?php echo $rp['key']; ?>','pdf')">PDF</button>
					</div>
				</div>
			<?php } ?>
		</div>
	</div>

	<script>
		var LI_REPORTS = <?php echo json_encode($payload); ?>;
		function liRepEsc(s) {
			return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
			});
		}
		function liRepExport(key, kind) {
			var rep = LI_REPORTS[key];
			if (!rep) return;
			var t = '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
			for (var i = 0; i < rep.head.length; i++) t += '<th>' + liRepEsc(rep.head[i]) + '</th>';
			t += '</tr></thead><tbody>';
			for (var r = 0; r < rep.rows.length; r++) {
				t += '<tr>';
				for (var c = 0; c < rep.rows[r].length; c++) t += '<td>' + liRepEsc(rep.rows[r][c]) + '</td>';
				t += '</tr>';
			}
			t += '</tbody></table>';
			var stamp = new Date().toLocaleString();
			if (kind === 'xls') {
				var wrap = '﻿<html><head><meta charset="utf-8"></head><body><h3>' + liRepEsc(rep.title) + '</h3>' + t + '</body></html>';
				var blob = new Blob([wrap], { type: 'application/vnd.ms-excel' });
				var a = document.createElement('a');
				a.href = URL.createObjectURL(blob);
				a.download = key + '-report.xls';
				document.body.appendChild(a); a.click(); document.body.removeChild(a);
			} else {
				var w = window.open('', '_blank'); if (!w) return;
				w.document.write('<html><head><title>' + liRepEsc(rep.title) + '</title><style>'
					+ 'body{font-family:Arial,sans-serif;padding:16px}h3{margin:0 0 2px}small{color:#555}'
					+ 'table{border-collapse:collapse;width:100%;margin-top:10px;font-size:12px}'
					+ 'th,td{border:1px solid #999;padding:6px 8px;text-align:left}th{background:#eee}'
					+ '</style></head><body><h3>Water Resources Department, Punjab</h3><small>'
					+ liRepEsc(rep.title) + ' — ' + liRepEsc(stamp) + '</small>' + t + '</body></html>');
				w.document.close(); w.focus();
				setTimeout(function () { w.print(); }, 350);
			}
		}
	</script>

	<?php liReportsStyles();
}

function liReportsStyles()
{
	?>
	<style>
		.li-rep-intro {
			color: #475569;
			font-size: 14px;
			margin: 0 0 14px;
		}

		.li-rep-list {
			display: grid;
			gap: 10px;
		}

		.li-rep-card {
			align-items: center;
			background: #fff;
			border: 1px solid #e2e8f0;
			border-radius: 8px;
			display: flex;
			flex-wrap: wrap;
			gap: 12px;
			justify-content: space-between;
			padding: 14px 16px;
		}

		.li-rep-info {
			align-items: center;
			display: flex;
			gap: 12px;
		}

		.li-rep-num {
			background: #1666b0;
			border-radius: 50%;
			color: #fff;
			flex: 0 0 auto;
			font-size: 13px;
			font-weight: 700;
			height: 26px;
			line-height: 26px;
			text-align: center;
			width: 26px;
		}

		.li-rep-title {
			color: #1d3473;
			font-size: 14px;
			font-weight: 700;
		}

		.li-rep-meta {
			color: #94a3b8;
			font-size: 12px;
		}

		.li-rep-actions {
			display: flex;
			gap: 8px;
		}

		.li-btn-xls {
			background: #2E8B57;
			border-color: #2E8B57;
			color: #fff;
		}

		.li-btn-pdf {
			background: #b91c1c;
			border-color: #b91c1c;
			color: #fff;
		}
	</style>
	<?php
}
?>
