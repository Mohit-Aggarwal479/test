<?php
/* ============================================================================
   Lift Irrigation — Monitoring : Sensor Health tab
   Parameter/Count panel: Online, Offline, Communication Failures Today,
   Sensors Under Maintenance. Data from liSensorHealth() in functions.php.
   ============================================================================ */

function liHealthPane($h)
{
	$tiles = array(
		array('Online Sensors', (int) $h['online'], 'li-h-online', '#16a34a'),
		array('Offline Sensors', (int) $h['offline'], 'li-h-offline', '#64748b'),
		array('Communication Failures Today', (int) $h['comm_fail'], 'li-h-comm', '#d97706'),
		array('Sensors Under Maintenance', (int) $h['maintenance'], 'li-h-maint', '#2563eb'),
	);
	?>
	<div class="li-health">
		<div class="li-health-grid">
			<?php foreach ($tiles as $t) { ?>
				<div class="li-health-tile <?php echo $t[2]; ?>">
					<span class="li-health-label"><?php echo liEsc($t[0]); ?></span>
					<strong class="li-health-value"><?php echo (int) $t[1]; ?></strong>
				</div>
			<?php } ?>
		</div>
		<table class="table li-health-table">
			<thead>
				<tr>
					<th>Parameter</th>
					<th class="text-center">Count</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($tiles as $t) { ?>
					<tr>
						<td><?php echo liEsc($t[0]); ?></td>
						<td class="text-center" style="font-weight:700;color:<?php echo $t[3]; ?>;"><?php echo (int) $t[1]; ?></td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
		<p class="li-health-note">Online / Offline reflect the current snapshot. Communication Failures Today =
			active sensors that have not sent data today. Under Maintenance = deactivated sensors.</p>
	</div>
	<?php liHealthStyles();
}

function liHealthStyles()
{
	?>
	<style>
		.li-health-grid {
			display: grid;
			gap: 14px;
			grid-template-columns: repeat(4, minmax(0, 1fr));
			margin-bottom: 16px;
		}

		.li-health-tile {
			background: #fff;
			border: 1px solid #e2e8f0;
			border-left: 6px solid #94a3b8;
			border-radius: 8px;
			display: flex;
			flex-direction: column;
			gap: 4px;
			padding: 16px 18px;
		}

		.li-health-label {
			color: #64748b;
			font-size: 13px;
			font-weight: 600;
		}

		.li-health-value {
			color: #0f172a;
			font-size: 30px;
			font-weight: 700;
		}

		.li-h-online {
			border-left-color: #16a34a;
		}

		.li-h-offline {
			border-left-color: #64748b;
		}

		.li-h-comm {
			border-left-color: #d97706;
		}

		.li-h-maint {
			border-left-color: #2563eb;
		}

		.li-health-table {
			width: 100%;
			border-collapse: collapse;
			max-width: 480px;
		}

		.li-health-table th,
		.li-health-table td {
			border-bottom: 1px solid #e6edf7;
			font-size: 13px;
			padding: 9px 10px;
		}

		.li-health-table thead th {
			background: #f5f8fc;
			color: #1d3473;
			font-weight: 700;
		}

		.li-health-note {
			color: #94a3b8;
			font-size: 12px;
			margin-top: 10px;
		}

		@media (max-width: 767px) {
			.li-health-grid {
				grid-template-columns: 1fr 1fr;
			}
		}
	</style>
	<?php
}
?>
