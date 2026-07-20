<?php
/* ============================================================================
   Lift Irrigation — Monitoring : State Map View (dashboard tab)
   Interactive Leaflet map of every in-scope sensor location. Loaded as a tab on
   the dashboard; the Leaflet library is fetched lazily only when the tab opens
   (see liInitMap) so ordinary dashboard loads pay nothing for the map.
   Data comes from liMapMarkers() in functions.php; helpers li* from view.php.
   ============================================================================ */

// Render the map pane: legend + container + the marker payload + lazy loader.
function liMapPane($markers)
{
	$points = array();
	foreach ($markers as $m) {
		$state = strtolower(trim((string) $m['state']));
		if ($state === 'offline' || $state === 'unknown') {
			$key = 'offline';
			$label = 'Sensor Offline';
			$color = '#64748b';
		} elseif ((string) $m['last_has_water'] === '1') {
			$key = 'water';
			$label = 'Water Present';
			$color = '#16a34a';
		} else {
			$key = 'nowater';
			$label = 'Water Not Present';
			$color = '#dc2626';
		}

		$lat = (float) $m['latitude'];
		$lng = (float) $m['longitude'];
		$val = function ($v) {
			$v = trim((string) $v);
			return $v === '' ? '—' : $v;
		};

		$points[] = array(
			'lat'      => $lat,
			'lng'      => $lng,
			'color'    => $color,
			'key'      => $key,
			'id'       => $val($m['site_id']),
			'rd'       => $val($m['tail_label']),
			'scheme'   => $val($m['scheme_name']),
			'division' => $val($m['office_name']),
			'status'   => $label,
			'time'     => ($m['last_reported_at'] ? liDate($m['last_reported_at']) : '—'),
			'coord'    => number_format($lat, 6) . ', ' . number_format($lng, 6),
		);
	}
	?>
	<div class="li-map-wrap">
		<div class="li-map-legend">
			<span><i style="background:#16a34a;"></i> Water Present</span>
			<span><i style="background:#dc2626;"></i> Water Not Present</span>
			<span><i style="background:#64748b;"></i> Sensor Offline</span>
			<span class="li-map-count"><?php echo count($points); ?> sensor(s) mapped</span>
		</div>
		<div id="liMap"></div>
		<?php if (count($points) === 0) { ?>
			<div class="li-map-empty">No sensor locations (coordinates) available for the current filters.</div>
		<?php } ?>
	</div>

	<script>
		var LI_MARKERS = <?php echo json_encode($points); ?>;

		// Fetch Leaflet only the first time the Map tab is opened, then draw.
		function liInitMap() {
			if (window._liMapInit) {
				if (window._liMap) { setTimeout(function () { window._liMap.invalidateSize(); }, 60); }
				return;
			}
			window._liMapInit = true;

			var css = document.createElement('link');
			css.rel = 'stylesheet';
			css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
			document.head.appendChild(css);

			var js = document.createElement('script');
			js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
			js.onload = liBuildMap;
			js.onerror = function () {
				var el = document.getElementById('liMap');
				if (el) el.innerHTML = '<div class="li-map-empty">Map library could not be loaded (check network access to unpkg.com).</div>';
			};
			document.head.appendChild(js);
		}

		function liBuildMap() {
			var el = document.getElementById('liMap');
			if (!el || !window.L) return;

			var map = L.map(el);
			window._liMap = map;
			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 19,
				attribution: '&copy; OpenStreetMap contributors'
			}).addTo(map);

			var pts = [];
			for (var i = 0; i < LI_MARKERS.length; i++) {
				var m = LI_MARKERS[i];
				var mk = L.circleMarker([m.lat, m.lng], {
					radius: 8, weight: 2, color: '#ffffff', fillColor: m.color, fillOpacity: 1
				});
				mk.bindPopup(liMapPopup(m));
				mk.addTo(map);
				pts.push([m.lat, m.lng]);
			}

			if (pts.length) {
				map.fitBounds(pts, { padding: [30, 30], maxZoom: 14 });
			} else {
				map.setView([31.147, 75.341], 8); // Punjab fallback
			}
			setTimeout(function () { map.invalidateSize(); }, 60);
		}

		function liMapEsc(s) {
			return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
			});
		}

		function liMapPopup(m) {
			function row(k, v) {
				return '<div class="li-pop-row"><span>' + k + '</span>' + liMapEsc(v) + '</div>';
			}
			return '<div class="li-map-pop">'
				+ '<div class="li-pop-id">' + liMapEsc(m.id) + '</div>'
				+ '<div class="li-pop-status li-' + m.key + '">' + liMapEsc(m.status) + '</div>'
				+ row('RD of Outlet', m.rd)
				+ row('Scheme', m.scheme)
				+ row('Division', m.division)
				+ row('Last Reading', m.time)
				+ row('Coordinates', m.coord)
				+ '</div>';
		}
	</script>

	<?php liMapStyles();
}

function liMapStyles()
{
	?>
	<style>
		.li-map-wrap {
			position: relative;
		}

		.li-map-legend {
			align-items: center;
			color: #475569;
			display: flex;
			flex-wrap: wrap;
			font-size: 13px;
			gap: 16px;
			margin-bottom: 10px;
		}

		.li-map-legend i {
			border-radius: 50%;
			display: inline-block;
			height: 12px;
			margin-right: 5px;
			vertical-align: middle;
			width: 12px;
		}

		.li-map-count {
			color: #64748b;
			margin-left: auto;
		}

		#liMap {
			border: 1px solid #dfe7f2;
			border-radius: 8px;
			height: 560px;
			width: 100%;
			z-index: 0;
		}

		.li-map-empty {
			color: #65758f;
			margin-top: 10px;
			padding: 12px;
			text-align: center;
		}

		.li-map-pop {
			min-width: 190px;
		}

		.li-pop-id {
			color: #1d3473;
			font-size: 15px;
			font-weight: 700;
		}

		.li-pop-status {
			border-radius: 4px;
			color: #fff;
			display: inline-block;
			font-size: 12px;
			font-weight: 700;
			margin: 6px 0 8px;
			padding: 2px 8px;
		}

		.li-pop-status.li-water {
			background: #16a34a;
		}

		.li-pop-status.li-nowater {
			background: #dc2626;
		}

		.li-pop-status.li-offline {
			background: #64748b;
		}

		.li-pop-row {
			border-top: 1px solid #eef2f7;
			font-size: 13px;
			padding: 4px 0;
		}

		.li-pop-row span {
			color: #64748b;
			display: inline-block;
			min-width: 92px;
		}
	</style>
	<?php
}
?>
