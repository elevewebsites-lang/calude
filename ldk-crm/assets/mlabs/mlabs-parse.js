/* Lê o relatório em PDF do mLabs (já convertido em "primitivas" por pdf-prims.js) e devolve dados estruturados:
 * indicadores com variação, funis, textos, gráficos de linha/barras/pizza e tabelas (com a posição das miniaturas).
 * Roda no navegador e no Node (testes). Nada aqui depende de layout fixo de página: a estrutura é descoberta pelos ícones
 * de rede social, pelos títulos, pelas faixas de cabeçalho das tabelas e pelas cores dos desenhos. */
(function (root, factory) {
	if (typeof module === 'object' && module.exports) module.exports = factory();
	else root.LKMlabsParse = factory();
})(typeof self !== 'undefined' ? self : this, function () {
	var NUM = /^[−-]?\d[\d.,]*%?$/;
	var PCT = /^\d[\d.,]*%$/;

	function bbox(p) {
		var xs = p.pts.map(function (q) { return q[0]; }), ys = p.pts.map(function (q) { return q[1]; });
		return { x0: Math.min.apply(null, xs), y0: Math.min.apply(null, ys), x1: Math.max.apply(null, xs), y1: Math.max.apply(null, ys) };
	}
	function isGray(c, lo, hi) { return c && Math.abs(c[0] - c[1]) < 8 && Math.abs(c[1] - c[2]) < 8 && c[0] >= lo && c[0] <= hi; }
	function isWhite(c) { return c && c[0] > 250 && c[1] > 250 && c[2] > 250; }
	function sameColor(a, b) { return a && b && Math.abs(a[0] - b[0]) + Math.abs(a[1] - b[1]) + Math.abs(a[2] - b[2]) < 12; }
	function colorHex(c) { return '#' + c.map(function (v) { return ('0' + v.toString(16)).slice(-2); }).join(''); }
	function lines(items, tol) { // agrupa por linha (y) e junta da esquerda para a direita
		tol = tol || 2.5;
		var sorted = items.slice().sort(function (a, b) { return a.y - b.y || a.x - b.x; }), out = [];
		sorted.forEach(function (it) {
			var l = out[out.length - 1];
			if (l && Math.abs(l.y - it.y) <= tol) { l.items.push(it); } else out.push({ y: it.y, items: [it] });
		});
		out.forEach(function (l) {
			l.items.sort(function (a, b) { return a.x - b.x; });
			l.x = l.items[0].x;
			l.text = l.items.reduce(function (s, it, i) {
				if (!i) return it.str;
				var prev = l.items[i - 1], gap = it.x - (prev.x + prev.w);
				return s + (gap > 1.2 ? ' ' : '') + it.str;
			}, '').replace(/\s+/g, ' ').trim();
		});
		return out;
	}
	function dirFromBadge(color) {
		if (!color) return 0;
		if (color[1] > color[0] + 4 && color[1] >= color[2]) return 1;   // verde
		if (color[0] > color[1] + 4 && color[0] > color[2] + 4) return -1; // vermelho
		return 0;                                                          // azul/cinza = sem variação
	}
	function badgeColor(page, it) {
		var best = null, area = 1e9;
		page.paths.forEach(function (p) {
			if (p.kind === 'stroke' || !p.fill || isWhite(p.fill) || p.pts.length < 4) return;
			var b = bbox(p), w = b.x1 - b.x0, h = b.y1 - b.y0;
			if (w > 120 || h > 40 || w < 20) return;
			if (it.x + it.w / 2 >= b.x0 && it.x + it.w / 2 <= b.x1 && it.y - 4 >= b.y0 && it.y - 4 <= b.y1 && w * h < area) { best = p.fill; area = w * h; }
		});
		return best;
	}
	function networkOf(color) {
		if (!color) return 'instagram';
		var r = color[0], g = color[1], b = color[2];
		if (Math.abs(r - 58) < 25 && Math.abs(g - 90) < 25 && Math.abs(b - 154) < 30) return 'facebook';
		if (r < 40 && g > 90 && g < 140 && b > 160) return 'linkedin';
		if (r > 200 && g < 60 && b < 60) return 'youtube';
		return 'instagram';
	}

	/* ---------- cabeçalho do relatório ---------- */
	function readMeta(pages) {
		var meta = { title: '', client: '', from: '', to: '' };
		for (var i = 0; i < pages.length; i++) {
			var head = pages[i].items.filter(function (it) { return it.y < 60; });
			if (!head.length) continue;
			lines(head).forEach(function (l) {
				var m = l.text.match(/Per[ií]odo:\s*(\d{2})\/(\d{2})\/(\d{4})\s*-\s*(\d{2})\/(\d{2})\/(\d{4})/);
				if (m) { meta.from = m[3] + '-' + m[2] + '-' + m[1]; meta.to = m[6] + '-' + m[5] + '-' + m[4]; }
				else if (!meta.title && l.text.length > 6) {
					meta.title = l.text;
					var parts = l.text.split(/\s[-–]\s/);
					if (parts.length > 1) meta.client = parts.slice(1).join(' - ').trim();
				}
			});
			if (meta.title && meta.from) break;
		}
		return meta;
	}

	/* ---------- blocos de uma página ---------- */
	function pageBlocks(page, pageNo, carry) {
		var icons = [];
		page.paths.forEach(function (p) {
			if (p.kind === 'stroke' || !p.fill || isWhite(p.fill) || p.pts.length < 4) return;
			var b = bbox(p), w = b.x1 - b.x0, h = b.y1 - b.y0;
			if (b.x0 >= 20 && b.x0 < page.w - 40 && w >= 16 && w <= 22 && h >= 16 && h <= 22) icons.push({ x: b.x0, y: b.y0, y1: b.y1, color: p.fill });
		});
		// ícones fora da margem esquerda só valem quando estão lado a lado com um da margem (duas colunas)
		icons = icons.filter(function (ic) { return (ic.x >= 33 && ic.x <= 40) || icons.some(function (o) { return o.x >= 33 && o.x <= 40 && Math.abs(o.y - ic.y) < 6; }); });
		icons.sort(function (a, b) { return a.y - b.y || a.x - b.x; });
		var top = 58, bottom = page.h - 20, blocks = [];
		var rows = [];
		icons.forEach(function (ic) {
			var r = rows[rows.length - 1];
			if (r && Math.abs(r[0].y - ic.y) < 6) r.push(ic); else rows.push([ic]);
		});
		if (!rows.length || rows[0][0].y > top + 22) {
			blocks.push({ x0: 0, x1: page.w, y0: top, y1: rows.length ? rows[0][0].y - 4 : bottom, network: carry.network, continuation: true, page: pageNo });
		}
		rows.forEach(function (r, i) {
			var y1 = i + 1 < rows.length ? rows[i + 1][0].y - 4 : bottom;
			r.sort(function (a, b) { return a.x - b.x; }).forEach(function (ic, j) {
				carry.network = networkOf(ic.color);
				blocks.push({ x0: j ? ic.x - 6 : 0, x1: j + 1 < r.length ? r[j + 1].x - 6 : page.w, y0: ic.y - 4, y1: y1, network: carry.network, icon: ic, page: pageNo });
			});
		});
		return blocks;
	}

	function inBlock(it, bl) { return it.y >= bl.y0 && it.y < bl.y1 && it.x >= bl.x0 && it.x < bl.x1; }

	/* ---------- tipos de bloco ---------- */
	function readKpis(page, bl, items) {
		var vals = items.filter(function (it) { return it.h >= 14 && NUM.test(it.str); });
		if (vals.length < 1) return null;
		var out = [];
		vals.forEach(function (v) {
			var label = items.filter(function (it) { return Math.abs(it.x - v.x) < 6 && it.y < v.y - 8 && it.y > v.y - 40 && it.h < 13 && !PCT.test(it.str); }).sort(function (a, b) { return b.y - a.y; })[0];
			var delta = items.filter(function (it) { return PCT.test(it.str) && Math.abs(it.y - v.y) < 8 && it.x > v.x + 5 && it.x < v.x + 190; }).sort(function (a, b) { return a.x - b.x; })[0];
			if (!label) return;
			out.push({ label: label.str, value: v.str, delta: delta ? delta.str : '', dir: delta ? dirFromBadge(badgeColor(page, delta)) : 0 });
		});
		return out.length ? { type: 'kpis', items: out } : null;
	}

	function readFunnel(items) {
		var ls = lines(items.filter(function (it) { return it.h < 14 || it.h >= 10; }));
		if (!ls.some(function (l) { return /^Per[ií]odo Anterior:/.test(l.text); })) return null;
		var steps = [], pending = null;
		ls.forEach(function (l) {
			var m;
			if ((m = l.text.match(/^([↑↓])\s*(\d[\d.,]*%)$/))) { pending = { delta: m[2], dir: m[1] === '↑' ? 1 : -1 }; return; }
			if (/^\d[\d.,]*%$/.test(l.text)) { pending = { delta: l.text, dir: 0 }; return; }
			if ((m = l.text.match(/^Per[ií]odo Anterior:\s*(.+)$/))) { if (steps.length) steps[steps.length - 1].prev = m[1]; return; }
			if ((m = l.text.match(/^(.+?):\s*(.+)$/))) { steps.push({ label: m[1], value: m[2], prev: '', delta: pending ? pending.delta : '', dir: pending ? pending.dir : 0 }); pending = null; }
		});
		return steps.length ? { type: 'funnel', steps: steps } : null;
	}

	function readText(items) {
		var ls = lines(items.filter(function (it) { return it.h < 14 && it.h >= 9; }));
		var long = ls.filter(function (l) { return l.text.length > 45 && l.x < 60; });
		if (long.length < 2) return null;
		var paras = [], cur = [], lastY = null;
		ls.forEach(function (l) {
			if (lastY !== null && l.y - lastY > 21) { paras.push(cur.join(' ')); cur = []; }
			cur.push(l.text); lastY = l.y;
		});
		if (cur.length) paras.push(cur.join(' '));
		return { type: 'text', paragraphs: paras };
	}

	function tidy(v, step) { if (step >= 4) return Math.round(v); var r = Math.round(v * 10) / 10; return Math.abs(r - Math.round(r)) < 0.06 ? Math.round(r) : r; }
	function stepOf(ticks) { if (ticks.length < 2) return 0; var v = ticks.map(function (t) { return t.v; }); return (Math.max.apply(null, v) - Math.min.apply(null, v)) / (ticks.length - 1); }
	function fit(ticks) { // regressão linear y -> valor
		if (ticks.length < 2) return null;
		var n = ticks.length, sx = 0, sy = 0, sxx = 0, sxy = 0;
		ticks.forEach(function (t) { sx += t.y; sy += t.v; sxx += t.y * t.y; sxy += t.y * t.v; });
		var d = n * sxx - sx * sx;
		if (!d) return null;
		var a = (n * sxy - sx * sy) / d, b = (sy - a * sx) / n;
		return function (y) { return a * y + b; };
	}
	function parseNum(s) { return parseFloat(String(s).replace(/\./g, '').replace(',', '.')); }
	function parsePct(s) { return parseFloat(String(s).replace(',', '.')); }

	function axisTicks(items, gridYs, side, plot) {
		var tk = items.filter(function (it) {
			if (Math.abs(it.rot) > 0.05 || !/^[−-]?\d[\d.]*(,\d+)?$/.test(it.str)) return false;
			return side === 'left' ? it.x + it.w <= plot.x0 + 2 && it.x > plot.x0 - 60 : it.x >= plot.x1 - 2 && it.x < plot.x1 + 60;
		}).map(function (it) {
			var yc = it.y - it.h * 0.35, g = null, gd = 6;
			gridYs.forEach(function (gy) { if (Math.abs(gy - yc) < gd) { gd = Math.abs(gy - yc); g = gy; } });
			return { y: g !== null ? g : yc, v: parseNum(it.str) };
		});
		return tk;
	}

	function readChart(page, bl, items, title) {
		var inPaths = page.paths.filter(function (p) { var b = bbox(p); return b.y0 >= bl.y0 - 2 && b.y1 <= bl.y1 + 2 && (b.x0 + b.x1) / 2 >= bl.x0 && (b.x0 + b.x1) / 2 < bl.x1; });
		var series = {}, order = [];
		inPaths.forEach(function (p) {
			if (p.kind !== 'stroke' || p.closed || p.pts.length < 5 || isGray(p.stroke, 150, 255) || !p.stroke) return;
			var b = bbox(p);
			if (b.x1 - b.x0 < 80) return;
			var key = colorHex(p.stroke);
			if (!series[key]) { series[key] = { color: p.stroke, pts: p.pts }; order.push(key); }
		});
		var grid = [];
		inPaths.forEach(function (p) {
			if (p.kind === 'stroke' && p.pts.length === 2 && Math.abs(p.pts[0][1] - p.pts[1][1]) < 0.5 && Math.abs(p.pts[0][0] - p.pts[1][0]) > 100) grid.push(p.pts[0][1]);
		});
		var bars = inPaths.filter(function (p) {
			if (p.kind === 'stroke' || !p.fill || isWhite(p.fill) || isGray(p.fill, 200, 255) || p.pts.length < 4 || p.pts.length > 16) return false;
			var b = bbox(p), w = b.x1 - b.x0, h = b.y1 - b.y0;
			return w >= 14 && w <= 90 && h >= 0.3 && h < 400;
		});
		if (bars.length) { // só barras que partem da mesma linha de base
			var baseY = {};
			bars.forEach(function (p) { var k = Math.round(bbox(p).y1); baseY[k] = (baseY[k] || 0) + 1; });
			var bestK = Object.keys(baseY).sort(function (a, b) { return baseY[b] - baseY[a]; })[0];
			bars = bars.filter(function (p) { return Math.abs(bbox(p).y1 - bestK) < 1.6; });
		}
		var legendItems = items.filter(function (it) { return Math.abs(it.rot) < 0.05 && it.h < 11 && !/^[\d.,%−-]+$/.test(it.str) && !/^\d{1,2}:\d{2}$/.test(it.str) && !/\d{2}\/\d{2}\/\d{4}/.test(it.str); });
		var plot;
		if (order.length) {
			var all = []; order.forEach(function (k) { all = all.concat(series[k].pts); });
			plot = { x0: Math.min.apply(null, all.map(function (q) { return q[0]; })), x1: Math.max.apply(null, all.map(function (q) { return q[0]; })) };
			var left = axisTicks(items, grid, 'left', plot), right = axisTicks(items, grid, 'right', plot);
			var fl = fit(left), fr = fit(right);
			var labelsRaw = items.filter(function (it) { return /^\d{2}\/\d{2}\/\d{4}$/.test(it.str) || /^\d{1,2}:\d{2}$/.test(it.str); }).sort(function (a, b) { return a.x - b.x; });
			var plotBottom = Math.max.apply(null, all.map(function (q) { return q[1]; }));
			legendItems = legendItems.filter(function (it) { return it.y > plotBottom + 30; });
			var n = series[order[0]].pts.length;
			var labels = labelsRaw.length === n ? labelsRaw.map(function (l) { return l.str; }) : series[order[0]].pts.map(function (q, i) { return ''; });
			var out = order.map(function (k, idx) {
				var s = series[k], f = (right.length && fr && idx > 0) ? fr : (fl || fr);
				var ys = s.pts.map(function (q) { return q[1]; }), flat = Math.max.apply(null, ys) - Math.min.apply(null, ys) < 2, one = (idx > 0 && right.length === 1) ? right[0] : (left.length === 1 ? left[0] : null);
				if (!f && flat && one) f = function () { return one.v; };
				var name = '';
				page.paths.forEach(function (p) { // marcador da legenda: bolinha com a cor da série, abaixo do gráfico
					if (p.kind === 'stroke' || !sameColor(p.fill, s.color)) return;
					var b = bbox(p);
					if (b.y0 > plotBottom + 25 && b.x1 - b.x0 < 14) {
						var nm = legendItems.filter(function (it) { return Math.abs(it.y - (b.y0 + b.y1) / 2 - 3) < 6 && it.x > b.x1 - 2 && it.x < b.x1 + 14; })[0];
						if (nm) name = nm.str;
					}
				});
				return { name: name, color: colorHex(s.color), values: s.pts.map(function (q) { return f ? tidy(f(q[1]), stepOf((right.length && idx > 0) ? right : left)) : null; }), axis: (right.length && idx > 0) ? 'right' : 'left' };
			});
			var axisTitle = items.filter(function (it) { return Math.abs(Math.abs(it.rot) - Math.PI / 2) < 0.2; }).map(function (it) { return it.str; });
			return { type: 'line', title: title, labels: labels, series: out, axisTitles: axisTitle };
		}
		if (bars.length >= 2) {
			var bb = bars.map(function (p) { var b = bbox(p); return { b: b, p: p }; }).sort(function (a, b) { return a.b.x0 - b.b.x0; });
			var base = Math.max.apply(null, bb.map(function (o) { return o.b.y1; }));
			plot = { x0: Math.min.apply(null, bb.map(function (o) { return o.b.x0; })), x1: Math.max.apply(null, bb.map(function (o) { return o.b.x1; })) };
			var ticks = axisTicks(items, grid, 'left', plot), f2 = fit(ticks);
			var cats = items.filter(function (it) { return it.y > base - 1 && it.y < base + 150 && !/^[\d.,%]+$/.test(it.str) && it.h < 11 && Math.abs(Math.abs(it.rot) - Math.PI / 2) > 0.1; });
			if (cats.some(function (c) { return Math.abs(c.rot) > 0.05; })) cats = cats.filter(function (c) { return Math.abs(c.rot) > 0.05; });
			var vals = bb.map(function (o) {
				var cx = (o.b.x0 + o.b.x1) / 2, best = null, bd = 1e9;
				cats.forEach(function (c) {
					var endx = c.x + (Math.abs(c.rot) > 0.05 ? c.w * Math.cos(c.rot) : c.w / 2);
					var d = Math.abs(endx - cx);
					if (d < bd) { bd = d; best = c; }
				});
				return { label: best && bd < 40 ? best.str : '', value: f2 ? tidy(f2(o.b.y0), stepOf(ticks)) : null, color: colorHex(o.p.fill) };
			});
			var legend = legendItems.filter(function (it) { return it.y > base + 40; })[0];
			return { type: 'bars', title: title, labels: vals.map(function (v) { return v.label; }), series: [{ name: legend ? legend.str : '', color: vals[0].color, values: vals.map(function (v) { return v.value; }) }] };
		}
		var slices = [];
		items.forEach(function (it) {
			var m = it.str.match(/^(.+?)\s*\((\d+(?:[.,]\d+)?)%\)$/);
			if (m) slices.push({ label: m[1], pct: parsePct(m[2]) });
		});
		if (slices.length >= 2) {
			var ints = items.filter(function (it) { return /^\d+$/.test(it.str) && it.h < 11; }).map(function (it) { return parseInt(it.str, 10); });
			if (ints.length === slices.length) {
				var total = ints.reduce(function (a, b) { return a + b; }, 0);
				slices.forEach(function (s) { var best = null, bd = 1e9; ints.forEach(function (c) { var d = Math.abs(c / total * 100 - s.pct); if (d < bd) { bd = d; best = c; } }); s.count = best; });
			}
			return { type: 'pie', title: title, slices: slices };
		}
		return null;
	}

	function readTable(page, bl, items, title, carry) {
		var cells = page.paths.filter(function (p) {
			if (p.kind === 'stroke' || !p.fill || !isGray(p.fill, 236, 248) || p.pts.length !== 4) return false;
			var b = bbox(p);
			return b.y0 >= bl.y0 - 2 && b.y1 <= bl.y1 + 2 && b.x1 - b.x0 >= 30 && b.y1 - b.y0 >= 20 && b.y1 - b.y0 <= 90;
		}).map(bbox);
		var band = null;
		cells.forEach(function (c) {
			var same = cells.filter(function (o) { return Math.abs(o.y0 - c.y0) < 2 && Math.abs(o.y1 - c.y1) < 2; });
			var w = Math.max.apply(null, same.map(function (o) { return o.x1; })) - Math.min.apply(null, same.map(function (o) { return o.x0; }));
			if (w >= 250 && (!band || c.y0 < band.y0)) band = { y0: c.y0, y1: c.y1 };
		});
		var cols, bodyTop;
		if (band) {
			var head = items.filter(function (it) { return it.y >= band.y0 && it.y <= band.y1 + 2; });
			var clusters = [];
			head.sort(function (a, b) { return a.x - b.x; }).forEach(function (it) {
				var c = clusters.filter(function (k) { return Math.abs(k.x - it.x) < 7; })[0];
				if (c) c.items.push(it); else clusters.push({ x: it.x, items: [it] });
			});
			cols = clusters.map(function (c) {
				var ls = lines(c.items);
				return { x: c.x, label: joinHeader(ls.map(function (l) { return l.text; })) };
			});
			bodyTop = band.y1;
			carry.table = { cols: cols };
		} else if (bl.continuation && carry.table) {
			cols = carry.table.cols; bodyTop = bl.y0;
		} else return null;
		if (cols.length < 2) return null;
		var textCol = cols[0], numCols = cols.slice(1);
		var body = items.filter(function (it) { return it.y > bodyTop + 1; });
		// linhas = posições y dos números da primeira coluna numérica
		var anchors = body.filter(function (it) { return it.x >= numCols[0].x - 3 && it.x < numCols[0].x + 40 && /^[\d.,%−-]+$/.test(it.str) && it.h < 12; }).sort(function (a, b) { return a.y - b.y; });
		var rowYs = [];
		anchors.forEach(function (a) { if (!rowYs.length || a.y - rowYs[rowYs.length - 1] > 12) rowYs.push(a.y); });
		if (!rowYs.length) return null;
		var rows = rowYs.map(function (y, i) {
			var lo = i ? (rowYs[i - 1] + y) / 2 : y - 50, hi = i + 1 < rowYs.length ? (y + rowYs[i + 1]) / 2 : y + 50;
			var inRow = body.filter(function (it) { return it.y > lo && it.y <= hi; });
			var cells = {};
			numCols.forEach(function (c, ci) {
				var next = numCols[ci + 1], cell = inRow.filter(function (it) { return it.x >= c.x - 3 && (!next || it.x < next.x - 3) && Math.abs(it.y - y) < 6; });
				cells[c.label] = cell.map(function (it) { return it.str; }).join(' ');
			});
			var caption = lines(inRow.filter(function (it) { return it.x < numCols[0].x - 3 && it.x >= textCol.x - 2 && !(it.x < 60 && /^[\d.,]+$/.test(it.str) && false); })).map(function (l) { return l.text; }).join(' ').replace(/\s+\.\.\.$/, '…');
			var img = page.images.filter(function (im) { var cy = im.y + im.h / 2; return cy > lo && cy <= hi && im.x < numCols[0].x; })[0];
			return { caption: caption, cells: cells, thumb: img ? { page: page.no, x: img.x, y: img.y, w: img.w, h: img.h } : null };
		});
		var heading = title || (cols[0].label || 'Tabela');
		return { type: 'table', title: heading, firstLabel: textCol.label, columns: numCols.map(function (c) { return c.label; }), rows: rows, cont: !band };
	}

	var KNOWN = ['Comentários por post', 'Compartilhamentos por post', 'Salvamentos por post', 'Curtidas por post', 'Taxa de engajamento', 'Visualizações por post', 'Visualizações', 'Alcance por post', 'Alcance', 'Cliques nas Publicações', 'Total de interações', 'Saídas', 'Respostas', 'Reproduções Totais de Reels', 'Tempo Médio Assistido de Postagem (segundos)', 'Curtir', 'Comentários', 'Compartilhamentos', 'Seguidores', 'Região principal (cidade)'];
	function norm(s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9()]/g, ''); }
	function joinHeader(parts) {
		var raw = parts.join('');
		var n = norm(raw), hit = null;
		KNOWN.forEach(function (k) { if (norm(k) === n) hit = k; });
		return hit || parts.join(' ').replace(/\s+/g, ' ').trim();
	}

	/* ---------- principal ---------- */
	function parse(pages) {
		pages.forEach(function (p, i) {
			p.no = i + 1;
			var seen = {}; // o mLabs desenha o mesmo texto 2 ou 3 vezes (sombra): fica um só
			p.items = p.items.filter(function (it) { var k = it.str + '|' + Math.round(it.x) + '|' + Math.round(it.y); if (seen[k]) return false; seen[k] = 1; return true; });
		});
		var meta = readMeta(pages), carry = { network: 'instagram', table: null }, sections = [];
		pages.forEach(function (page, pi) {
			var textual = page.items.filter(function (it) { return it.y >= 58; });
			if (!textual.length) return;
			pageBlocks(page, pi + 1, carry).forEach(function (bl) {
				var items = page.items.filter(function (it) { return inBlock(it, bl); });
				if (!items.length && !bl.icon) return;
				var tIt = bl.icon ? items.filter(function (it) { return it.h >= 14 && it.x >= bl.icon.x + 24 && it.x <= bl.icon.x + 38 && it.y >= bl.icon.y - 4 && it.y <= bl.icon.y1 + 8; }) : [];
				bl.title = lines(tIt).map(function (l) { return l.text; }).join(' ');
				var rest = items.filter(function (it) { return tIt.indexOf(it) < 0; });
				var sec = null;
				sec = readFunnel(rest) || readTable(page, bl, rest, bl.title, carry);
				if (!sec) sec = readKpis(page, bl, rest);
				if (!sec) sec = readChart(page, bl, rest, bl.title);
				if (!sec) sec = readText(rest);
				if (!sec) return;
				if (sec.type === 'table' && sec.cont && sections.length) {
					var prev = sections[sections.length - 1];
					if (prev.type === 'table' && prev.network === bl.network) { prev.rows = prev.rows.concat(sec.rows); return; }
				}
				sec.network = bl.network;
				if (!sec.title && bl.title) sec.title = bl.title;
				sec.page = pi + 1;
				sections.push(sec);
			});
		});
		return { meta: meta, sections: sections };
	}

	return { parse: parse, lines: lines };
});
