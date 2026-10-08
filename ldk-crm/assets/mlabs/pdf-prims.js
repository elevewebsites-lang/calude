/* Primitivas de uma página de PDF (texto, imagens e desenhos vetoriais com posição e cor), via pdf.js.
 * Funciona no navegador (window.pdfjsLib) e no Node (para testes). Coordenadas em pontos, origem no canto superior esquerdo. */
(function (root, factory) {
	if (typeof module === 'object' && module.exports) module.exports = factory();
	else root.LKPdfPrims = factory();
})(typeof self !== 'undefined' ? self : this, function () {
	function mul(a, b) { // a * b (matrizes PDF [a b c d e f])
		return [a[0] * b[0] + a[2] * b[1], a[1] * b[0] + a[3] * b[1], a[0] * b[2] + a[2] * b[3], a[1] * b[2] + a[3] * b[3], a[0] * b[4] + a[2] * b[5] + a[4], a[1] * b[4] + a[3] * b[5] + a[5]];
	}
	function apply(m, x, y) { return [m[0] * x + m[2] * y + m[4], m[1] * x + m[3] * y + m[5]]; }
	function rgb(a) { return a ? [a[0] | 0, a[1] | 0, a[2] | 0] : null; }

	async function pagePrims(pdfjsLib, page) {
		var OPS = pdfjsLib.OPS, H = page.view[3] - page.view[1], W = page.view[2] - page.view[0];
		var tc = await page.getTextContent();
		var items = [];
		tc.items.forEach(function (it) {
			if (!it.str || !it.str.trim()) return;
			var t = it.transform, rot = Math.atan2(t[1], t[0]);
			items.push({ x: t[4], y: H - t[5], w: it.width, h: Math.hypot(t[2], t[3]), str: it.str, rot: rot });
		});
		var ol = await page.getOperatorList();
		var ctm = [1, 0, 0, 1, 0, 0], stack = [], stroke = null, fill = null, images = [], paths = [];
		var pending = null;
		function flush(kind) {
			if (!pending) return;
			var p = pending; pending = null;
			paths.push({ kind: kind, fill: p.fill, stroke: p.stroke, pts: p.pts, closed: p.closed });
		}
		for (var i = 0; i < ol.fnArray.length; i++) {
			var f = ol.fnArray[i], a = ol.argsArray[i];
			switch (f) {
				case OPS.save: stack.push(ctm.slice()); break;
				case OPS.restore: ctm = stack.pop() || ctm; break;
				case OPS.transform: ctm = mul(ctm, a); break;
				case OPS.setStrokeRGBColor: stroke = rgb(a); break;
				case OPS.setFillRGBColor: fill = rgb(a); break;
				case OPS.paintImageXObject: case OPS.paintInlineImageXObject: case OPS.paintJpegXObject: {
					var p0 = apply(ctm, 0, 0), p1 = apply(ctm, 1, 1), x0 = Math.min(p0[0], p1[0]), x1 = Math.max(p0[0], p1[0]), y0 = Math.min(p0[1], p1[1]), y1 = Math.max(p0[1], p1[1]);
					images.push({ x: x0, y: H - y1, w: x1 - x0, h: y1 - y0 });
					break;
				}
				case OPS.constructPath: {
					var ops = a[0], co = a[1], k = 0, pts = [], closed = false;
					for (var j = 0; j < ops.length; j++) {
						var o = ops[j];
						if (o === OPS.moveTo || o === OPS.lineTo) { var q = apply(ctm, co[k], co[k + 1]); pts.push([q[0], H - q[1]]); k += 2; }
						else if (o === OPS.curveTo) { var q1 = apply(ctm, co[k + 4], co[k + 5]); pts.push([q1[0], H - q1[1]]); k += 6; }
						else if (o === OPS.curveTo2 || o === OPS.curveTo3) { var q2 = apply(ctm, co[k + 2], co[k + 3]); pts.push([q2[0], H - q2[1]]); k += 4; }
						else if (o === OPS.rectangle) { var r0 = apply(ctm, co[k], co[k + 1]), r1 = apply(ctm, co[k] + co[k + 2], co[k + 1] + co[k + 3]); pts.push([r0[0], H - r0[1]], [r1[0], H - r0[1]], [r1[0], H - r1[1]], [r0[0], H - r1[1]]); closed = true; k += 4; }
						else if (o === OPS.closePath) closed = true;
					}
					pending = { pts: pts, closed: closed, fill: fill, stroke: stroke };
					break;
				}
				case OPS.fill: case OPS.eoFill: flush('fill'); break;
				case OPS.stroke: case OPS.closeStroke: flush('stroke'); break;
				case OPS.fillStroke: case OPS.eoFillStroke: case OPS.closeFillStroke: flush('fillstroke'); break;
				case OPS.endPath: pending = null; break;
			}
		}
		return { w: W, h: H, items: items, images: images, paths: paths };
	}

	return { pagePrims: pagePrims };
});
