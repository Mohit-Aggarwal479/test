<?php
/* ============================================================================
   Lift Irrigation — Monitoring : Recent Events tab
   Latest 100 events (readings) — Time, Sensor ID, Event (Water Present /
   Water Absent). Data from liRecentEvents() in functions.php.
   ============================================================================ */

function liEventsPane($rows)
{
	$total = count($rows);
	?>
	<div class="li-ev">
		<div class="li-ev-head">
			<span class="li-ev-count">Latest <?php echo (int) $total; ?> event<?php echo $total === 1 ? '' : 's'; ?></span>
			<input type="text" id="liEvSearch" class="form-control li-ev-search" placeholder="Search sensor / event…">
		</div>
		<div class="table-responsive">
			<table class="table li-ev-table">
				<thead>
					<tr>
						<th>Time</th>
						<th>Sensor ID</th>
						<th>Event</th>
					</tr>
				</thead>
				<tbody id="liEvBody">
					<?php if ($total > 0) {
						foreach ($rows as $r) {
							$present = ((string) $r['has_water'] === '1');
							$evt = $present ? 'Water Present' : 'Water Absent';
							$cls = $present ? 'li-ev-present' : 'li-ev-absent';
							$t = trim((string) $r['t']);
							$ts = $t !== '' ? strtotime($t) : false;
							$disp = $ts ? date('d-m-Y h:i A', $ts) : '- - -';
							?>
							<tr class="li-ev-row">
								<td class="li-ev-time"><?php echo liEsc($disp); ?></td>
								<td class="li-ev-id"><?php echo liText($r['site_id']); ?></td>
								<td><span class="li-ev-dot <?php echo $cls; ?>"></span><?php echo liEsc($evt); ?></td>
							</tr>
						<?php }
					} else { ?>
						<tr>
							<td colspan="3" class="li-ev-empty">No recent events.</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
		<div id="liEvNone" class="li-ev-empty" style="display:none;">No event matches your search.</div>
	</div>

	<script>
		(function () {
			var s = document.getElementById('liEvSearch');
			var body = document.getElementById('liEvBody');
			var none = document.getElementById('liEvNone');
			if (!s || !body) return;
			var rows = body.querySelectorAll('tr.li-ev-row');
			s.addEventListener('input', function () {
				var q = this.value.toLowerCase(), shown = 0;
				for (var i = 0; i < rows.length; i++) {
					var ok = q === '' || rows[i].textContent.toLowerCase().indexOf(q) > -1;
					rows[i].style.display = ok ? '' : 'none';
					if (ok) shown++;
				}
				if (none) none.style.display = (rows.length > 0 && shown === 0) ? '' : 'none';
			});
		})();
	</script>

	<?php liEventsStyles();
}

function liEventsStyles()
{
	?>
	<style>
		.li-ev-head {
			align-items: center;
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			justify-content: space-between;
			margin-bottom: 12px;
		}

		.li-ev-count {
			color: #334155;
			font-size: 14px;
			font-weight: 600;
		}

		.li-ev-search {
			max-width: 260px;
		}

		.li-ev-table {
			width: 100%;
			border-collapse: collapse;
		}

		.li-ev-table th,
		.li-ev-table td {
			border-bottom: 1px solid #e6edf7;
			font-size: 13px;
			padding: 9px 10px;
			text-align: left;
		}

		.li-ev-table thead th {
			background: #f5f8fc;
			color: #1d3473;
			font-weight: 700;
			white-space: nowrap;
		}

		.li-ev-time {
			color: #64748b;
			white-space: nowrap;
		}

		.li-ev-id {
			color: #1d3473;
			font-weight: 600;
		}

		.li-ev-dot {
			border-radius: 50%;
			display: inline-block;
			height: 9px;
			margin-right: 7px;
			vertical-align: middle;
			width: 9px;
		}

		.li-ev-present {
			background: #16a34a;
		}

		.li-ev-absent {
			background: #dc2626;
		}

		.li-ev-empty {
			color: #65758f;
			padding: 16px;
			text-align: center;
		}
	</style>
	<?php
}
?>
