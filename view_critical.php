<?php
/* ============================================================================
   Lift Irrigation — Monitoring : Critical Locations Requiring Attention (tab)
   Only red (Water Absent) and grey (Offline / No Data) sensors show here — the
   ones needing action. Green (water present) is excluded. Data comes from
   liCriticalLocations() in functions.php; shared li* helpers from view.php.
   ============================================================================ */

// "8 Hours Ago" style relative time for the Last Data Received column.
function liAgoLong($value)
{
	$value = trim((string) $value);
	if ($value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
		return 'No Data';
	}
	$time = strtotime($value);
	if (!$time) {
		return liEsc($value);
	}
	$diff = time() - $time;
	if ($diff < 0) {
		$diff = 0;
	}
	if ($diff < 60) {
		return 'Just Now';
	}
	$mins = (int) floor($diff / 60);
	if ($mins < 60) {
		return $mins . ' Minute' . ($mins === 1 ? '' : 's') . ' Ago';
	}
	$hrs = (int) floor($mins / 60);
	if ($hrs < 24) {
		return $hrs . ' Hour' . ($hrs === 1 ? '' : 's') . ' Ago';
	}
	$days = (int) floor($hrs / 24);
	return $days . ' Day' . ($days === 1 ? '' : 's') . ' Ago';
}

function liCriticalPane($rows)
{
	$total = count($rows);
	?>
	<div class="li-crit">
		<div class="li-crit-head">
			<div class="li-crit-count">
				<strong><?php echo (int) $total; ?></strong> location<?php echo $total === 1 ? '' : 's'; ?> requiring attention
				<span class="li-crit-legend">
					<span><i class="li-dot-nowater"></i> Water Absent</span>
					<span><i class="li-dot-offline"></i> Offline / No Data</span>
				</span>
			</div>
			<input type="text" id="liCritSearch" class="form-control li-crit-search"
				placeholder="Search sensor / scheme / location…">
		</div>

		<div class="table-responsive">
			<table class="table li-crit-table">
				<thead>
					<tr>
						<th>Sensor ID</th>
						<th>Scheme</th>
						<th>Location</th>
						<th>Status</th>
						<th>Last Data Received</th>
					</tr>
				</thead>
				<tbody id="liCritBody">
					<?php if ($total > 0) {
						foreach ($rows as $r) {
							$state = strtolower(trim((string) $r['state']));
							$isOffline = ($state === 'offline' || $state === 'unknown');
							$label = $isOffline ? 'Offline' : 'Water Absent';
							$cls = $isOffline ? 'li-badge-offline' : 'li-badge-nowater';

							// Offline  -> how long since the device last sent data.
							// Water Absent -> how long since it last reported water.
							if ($isOffline) {
								$when = $r['last_reported_at'];
								if ($when === null || trim((string) $when) === '') {
									$when = $r['last_polled_at'];
								}
							} else {
								$when = isset($r['water_since']) ? $r['water_since'] : null;
								if ($when === null || trim((string) $when) === '') {
									// never seen water — fall back to last data time
									$when = $r['last_reported_at'];
									if ($when === null || trim((string) $when) === '') {
										$when = $r['last_polled_at'];
									}
								}
							}
							?>
							<tr class="li-crit-row">
								<td class="li-crit-id"><?php echo liText($r['site_id']); ?></td>
								<td><?php echo liText($r['scheme_name']); ?></td>
								<td><?php echo liText($r['tail_label']); ?></td>
								<td><span class="li-badge <?php echo $cls; ?>"><?php echo liEsc($label); ?></span></td>
								<td><?php echo liEsc(date("H:i",strtotime($when))); ?></td>
							</tr>
						<?php }
					} else { ?>
						<tr>
							<td colspan="5" class="li-crit-empty">No critical locations — every sensor is reporting water. ✅</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
		<div id="liCritNone" class="li-crit-empty" style="display:none;">No location matches your search.</div>
	</div>

	<script>
		(function () {
			var search = document.getElementById('liCritSearch');
			var body = document.getElementById('liCritBody');
			var none = document.getElementById('liCritNone');
			if (!search || !body) return;
			var rows = body.querySelectorAll('tr.li-crit-row');

			search.addEventListener('input', function () {
				var q = this.value.toLowerCase();
				var shown = 0;
				for (var i = 0; i < rows.length; i++) {
					var txt = rows[i].textContent.toLowerCase();
					var ok = q === '' || txt.indexOf(q) > -1;
					rows[i].style.display = ok ? '' : 'none';
					if (ok) shown++;
				}
				if (none) none.style.display = (rows.length > 0 && shown === 0) ? '' : 'none';
			});
		})();
	</script>

	<?php liCriticalStyles();
}

function liCriticalStyles()
{
	?>
	<style>
		.li-crit-head {
			align-items: center;
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			justify-content: space-between;
			margin-bottom: 12px;
		}

		.li-crit-count {
			color: #334155;
			font-size: 14px;
		}

		.li-crit-count strong {
			color: #b91c1c;
			font-size: 18px;
		}

		.li-crit-legend {
			color: #64748b;
			font-size: 12px;
			margin-left: 10px;
		}

		.li-crit-legend span {
			margin-right: 10px;
			white-space: nowrap;
		}

		.li-crit-legend i {
			border-radius: 50%;
			display: inline-block;
			height: 10px;
			margin-right: 4px;
			vertical-align: middle;
			width: 10px;
		}

		.li-dot-nowater {
			background: #dc2626;
		}

		.li-dot-offline {
			background: #64748b;
		}

		.li-crit-search {
			max-width: 280px;
		}

		.li-crit-table {
			width: 100%;
			border-collapse: collapse;
		}

		.li-crit-table th,
		.li-crit-table td {
			border-bottom: 1px solid #e6edf7;
			font-size: 13px;
			padding: 9px 10px;
			text-align: left;
		}

		.li-crit-table thead th {
			background: #fff5f5;
			color: #7f1d1d;
			font-weight: 700;
			white-space: nowrap;
		}

		.li-crit-table tbody tr:hover {
			background: #fdf7f7;
		}

		.li-crit-id {
			color: #1d3473;
			font-weight: 600;
		}

		.li-badge {
			border-radius: 4px;
			color: #fff;
			display: inline-block;
			font-size: 12px;
			font-weight: 700;
			padding: 2px 9px;
			white-space: nowrap;
		}

		.li-badge-nowater {
			background: #dc2626;
		}

		.li-badge-offline {
			background: #64748b;
		}

		.li-crit-empty {
			color: #65758f;
			padding: 18px;
			text-align: center;
		}
	</style>
	<?php
}
?>
