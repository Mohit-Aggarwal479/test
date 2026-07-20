<?php
/* ============================================================================
   Lift Irrigation — Monitoring : Scheme-wise Status Table (dashboard tab)
   One row per Scheme x Division with Total / Water Present / Water Absent /
   Offline. Search, column sort, pagination and Export (Excel + PDF) are all
   handled client-side (vanilla JS, no external library) so it works offline.
   Data comes from liSchemeWiseStatus() in functions.php.
   ============================================================================ */

function liSchemePane($rows)
{
	// flatten to a plain matrix for the JS table controller
	$data = array();
	foreach ($rows as $r) {
		$data[] = array(
			($r['scheme_name'] !== null && trim((string) $r['scheme_name']) !== '') ? (string) $r['scheme_name'] : '- - -',
			($r['office_name'] !== null && trim((string) $r['office_name']) !== '') ? (string) $r['office_name'] : '- - -',
			(int) $r['total'],
			(int) $r['water_present'],
			(int) $r['water_absent'],
			(int) $r['offline'],
		);
	}
	?>
	<div class="li-sch">
		<div class="li-sch-toolbar">
			<input type="text" id="liSchSearch" class="form-control li-sch-search" placeholder="Search scheme or division…">
			<div class="li-sch-actions">
				<label class="li-sch-rows">Rows
					<select id="liSchPageSize" class="form-control">
						<option value="10">10</option>
						<option value="25">25</option>
						<option value="50">50</option>
						<option value="0">All</option>
					</select>
				</label>
				<button type="button" class="btn li-btn-xls" onclick="liSchExport('xls')">Export Excel</button>
				<button type="button" class="btn li-btn-pdf" onclick="liSchExport('pdf')">Export PDF</button>
			</div>
		</div>

		<div class="table-responsive">
			<table class="table li-sch-table">
				<thead>
					<tr>
						<th data-key="0" data-type="text" class="li-sortable">Scheme</th>
						<th data-key="1" data-type="text" class="li-sortable">Division</th>
						<th data-key="2" data-type="num" class="li-sortable text-center">Total Sensors</th>
						<th data-key="3" data-type="num" class="li-sortable text-center">Water Present</th>
						<th data-key="4" data-type="num" class="li-sortable text-center">Water Absent</th>
						<th data-key="5" data-type="num" class="li-sortable text-center">Offline</th>
					</tr>
				</thead>
				<tbody id="liSchBody"></tbody>
			</table>
		</div>

		<div class="li-sch-foot">
			<span id="liSchInfo" class="li-sch-info"></span>
			<div id="liSchPager" class="li-sch-pager"></div>
		</div>
	</div>

	<script>
		var LI_SCHEME = <?php echo json_encode($data); ?>;

		(function () {
			var state = { q: '', key: null, dir: 1, page: 1, size: 10 };
			var body = document.getElementById('liSchBody');
			var info = document.getElementById('liSchInfo');
			var pager = document.getElementById('liSchPager');
			var search = document.getElementById('liSchSearch');
			var sizeSel = document.getElementById('liSchPageSize');
			var heads = document.querySelectorAll('.li-sch-table th.li-sortable');

			function esc(s) {
				return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
					return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
				});
			}

			function filtered() {
				var q = state.q.toLowerCase();
				var rows = LI_SCHEME.filter(function (r) {
					return q === '' || String(r[0]).toLowerCase().indexOf(q) > -1
						|| String(r[1]).toLowerCase().indexOf(q) > -1;
				});
				if (state.key !== null) {
					var k = state.key, t = heads[k] ? heads[k].getAttribute('data-type') : 'text';
					rows.sort(function (a, b) {
						var x = a[k], y = b[k];
						if (t === 'num') { x = parseFloat(x) || 0; y = parseFloat(y) || 0; return (x - y) * state.dir; }
						return String(x).localeCompare(String(y)) * state.dir;
					});
				}
				return rows;
			}

			function render() {
				var rows = filtered();
				var size = state.size === 0 ? rows.length : state.size;
				var pages = size ? Math.max(1, Math.ceil(rows.length / size)) : 1;
				if (state.page > pages) state.page = pages;
				var start = size ? (state.page - 1) * size : 0;
				var slice = size ? rows.slice(start, start + size) : rows;

				if (slice.length === 0) {
					body.innerHTML = '<tr><td colspan="6" class="li-sch-empty">No matching rows.</td></tr>';
				} else {
					var html = '';
					for (var i = 0; i < slice.length; i++) {
						var r = slice[i];
						html += '<tr>'
							+ '<td>' + esc(r[0]) + '</td>'
							+ '<td>' + esc(r[1]) + '</td>'
							+ '<td class="text-center">' + esc(r[2]) + '</td>'
							+ '<td class="text-center li-c-water">' + esc(r[3]) + '</td>'
							+ '<td class="text-center li-c-nowater">' + esc(r[4]) + '</td>'
							+ '<td class="text-center li-c-offline">' + esc(r[5]) + '</td>'
							+ '</tr>';
					}
					body.innerHTML = html;
				}

				var from = rows.length ? start + 1 : 0;
				var to = size ? Math.min(start + size, rows.length) : rows.length;
				info.textContent = 'Showing ' + from + '–' + to + ' of ' + rows.length;

				// pager
				var p = '';
				if (pages > 1) {
					p += '<button type="button" data-pg="' + (state.page - 1) + '" ' + (state.page <= 1 ? 'disabled' : '') + '>Prev</button>';
					var lo = Math.max(1, state.page - 2), hi = Math.min(pages, lo + 4);
					lo = Math.max(1, hi - 4);
					for (var pg = lo; pg <= hi; pg++) {
						p += '<button type="button" data-pg="' + pg + '" class="' + (pg === state.page ? 'active' : '') + '">' + pg + '</button>';
					}
					p += '<button type="button" data-pg="' + (state.page + 1) + '" ' + (state.page >= pages ? 'disabled' : '') + '>Next</button>';
				}
				pager.innerHTML = p;
				var btns = pager.querySelectorAll('button[data-pg]');
				for (var b = 0; b < btns.length; b++) {
					btns[b].addEventListener('click', function () {
						var t = parseInt(this.getAttribute('data-pg'), 10);
						if (t >= 1 && t <= pages) { state.page = t; render(); }
					});
				}
			}

			// header sort
			for (var h = 0; h < heads.length; h++) {
				(function (th) {
					th.addEventListener('click', function () {
						var k = parseInt(th.getAttribute('data-key'), 10);
						if (state.key === k) { state.dir = -state.dir; } else { state.key = k; state.dir = 1; }
						for (var j = 0; j < heads.length; j++) heads[j].classList.remove('li-asc', 'li-desc');
						th.classList.add(state.dir === 1 ? 'li-asc' : 'li-desc');
						state.page = 1;
						render();
					});
				})(heads[h]);
			}

			search.addEventListener('input', function () { state.q = this.value; state.page = 1; render(); });
			sizeSel.addEventListener('change', function () { state.size = parseInt(this.value, 10); state.page = 1; render(); });

			// export (Excel via HTML-table .xls, PDF via print window) — uses the
			// current search + sort, all rows (not just the visible page).
			window.liSchExport = function (kind) {
				var rows = filtered();
				var head = ['Scheme', 'Division', 'Total Sensors', 'Water Present', 'Water Absent', 'Offline'];
				var t = '<table border="1" cellspacing="0" cellpadding="4"><thead><tr>';
				for (var i = 0; i < head.length; i++) t += '<th>' + esc(head[i]) + '</th>';
				t += '</tr></thead><tbody>';
				for (var r = 0; r < rows.length; r++) {
					t += '<tr>';
					for (var c = 0; c < rows[r].length; c++) t += '<td>' + esc(rows[r][c]) + '</td>';
					t += '</tr>';
				}
				t += '</tbody></table>';

				var title = 'Scheme-wise Status';
				if (kind === 'xls') {
					var wrap = '﻿<html><head><meta charset="utf-8"></head><body><h3>' + title + '</h3>' + t + '</body></html>';
					var blob = new Blob([wrap], { type: 'application/vnd.ms-excel' });
					var a = document.createElement('a');
					a.href = URL.createObjectURL(blob);
					a.download = 'scheme-wise-status.xls';
					document.body.appendChild(a); a.click(); document.body.removeChild(a);
				} else {
					var w = window.open('', '_blank');
					if (!w) return;
					w.document.write('<html><head><title>' + title + '</title><style>'
						+ 'body{font-family:Arial,sans-serif;padding:16px}h3{margin:0 0 4px}small{color:#555}'
						+ 'table{border-collapse:collapse;width:100%;margin-top:10px;font-size:12px}'
						+ 'th,td{border:1px solid #999;padding:6px 8px;text-align:left}th{background:#eee}'
						+ '</style></head><body>'
						+ '<h3>Water Resources Department, Punjab</h3><small>' + title + '</small>'
						+ t + '</body></html>');
					w.document.close(); w.focus();
					setTimeout(function () { w.print(); }, 350);
				}
			};

			render();
		})();
	</script>

	<?php liSchemeStyles();
}

function liSchemeStyles()
{
	?>
	<style>
		.li-sch-toolbar {
			align-items: center;
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			justify-content: space-between;
			margin-bottom: 12px;
		}

		.li-sch-search {
			max-width: 280px;
		}

		.li-sch-actions {
			align-items: center;
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
		}

		.li-sch-rows {
			align-items: center;
			color: #64748b;
			display: flex;
			font-size: 13px;
			gap: 6px;
			margin: 0;
		}

		.li-sch-rows select {
			width: auto;
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

		.li-sch-table {
			width: 100%;
			border-collapse: collapse;
		}

		.li-sch-table th,
		.li-sch-table td {
			border-bottom: 1px solid #e6edf7;
			font-size: 13px;
			padding: 9px 10px;
		}

		.li-sch-table thead th {
			background: #f5f8fc;
			color: #1d3473;
			font-weight: 700;
			white-space: nowrap;
		}

		.li-sortable {
			cursor: pointer;
			user-select: none;
		}

		.li-sortable::after {
			color: #94a3b8;
			content: ' \2195';
			font-size: 11px;
		}

		.li-sortable.li-asc::after {
			content: ' \2191';
			color: #1666b0;
		}

		.li-sortable.li-desc::after {
			content: ' \2193';
			color: #1666b0;
		}

		.li-sch-table tbody tr:hover {
			background: #f9fbfe;
		}

		.li-c-water {
			color: #16a34a;
			font-weight: 700;
		}

		.li-c-nowater {
			color: #dc2626;
			font-weight: 700;
		}

		.li-c-offline {
			color: #64748b;
			font-weight: 700;
		}

		.li-sch-empty {
			color: #65758f;
			padding: 16px;
			text-align: center;
		}

		.li-sch-foot {
			align-items: center;
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			justify-content: space-between;
			margin-top: 12px;
		}

		.li-sch-info {
			color: #64748b;
			font-size: 13px;
		}

		.li-sch-pager button {
			background: #fff;
			border: 1px solid #cbd5e1;
			border-radius: 5px;
			color: #334155;
			cursor: pointer;
			font-size: 13px;
			margin-left: 4px;
			min-width: 34px;
			padding: 5px 9px;
		}

		.li-sch-pager button.active {
			background: #1666b0;
			border-color: #1666b0;
			color: #fff;
		}

		.li-sch-pager button:disabled {
			color: #cbd5e1;
			cursor: default;
		}
	</style>
	<?php
}
?>
