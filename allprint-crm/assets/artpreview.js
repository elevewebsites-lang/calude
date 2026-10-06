/* AllPrint · leitor de arte no navegador: miniatura de imagem, PDF e CorelDRAW (.cdr) + medida do arquivo.
 * Nada é enviado para a hospedagem: o arquivo vai ao Google Drive e só uma miniatura pequena (JPEG, ~10 KB)
 * fica guardada dentro do pedido. Se qualquer passo falhar, o envio do arquivo segue normalmente (sem miniatura).
 *
 * window.apArtPreview(file) → Promise<{ thumb?:dataURI, pw?:cm, ph?:cm, px?:[w,h], kind, note? }>
 * window.apArtCheck(meta, wCm, hCm) → texto de aviso (ou '') comparando o arquivo com a medida pedida.
 */
(function () {
	'use strict';
	var MAX = 300;              // maior lado da miniatura (px)
	var MAX_BYTES = 40000;      // limite da miniatura em texto (o servidor aceita até 45000)
	var PDFJS = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
	var PDFJS_WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
	var PDF_FULL_LIMIT = 150 * 1048576; // acima disso só lê o tamanho da página (sem renderizar)

	function ext(name) { var m = /\.([a-z0-9]+)$/i.exec(name || ''); return m ? m[1].toLowerCase() : ''; }
	function read(file, start, end) { return file.slice(start, end).arrayBuffer(); }
	function u32(dv, o) { return dv.getUint32(o, true); }
	function ascii(u8, o, n) { var s = ''; for (var i = 0; i < n; i++) s += String.fromCharCode(u8[o + i]); return s; }

	/* ---------- miniatura a partir de um <img>/<canvas> ---------- */
	function toThumb(src, w, h) {
		var k = Math.min(1, MAX / Math.max(w, h)), cw = Math.max(1, Math.round(w * k)), ch = Math.max(1, Math.round(h * k));
		var c = document.createElement('canvas'); c.width = cw; c.height = ch;
		var g = c.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, cw, ch); g.drawImage(src, 0, 0, cw, ch);
		var q = 0.7, out = c.toDataURL('image/jpeg', q);
		while (out.length > MAX_BYTES && q > 0.3) { q -= 0.1; out = c.toDataURL('image/jpeg', q); }
		if (out.length > MAX_BYTES) { // ainda grande: reduz o tamanho
			var s2 = document.createElement('canvas'); s2.width = Math.round(cw * 0.6); s2.height = Math.round(ch * 0.6);
			s2.getContext('2d').drawImage(c, 0, 0, s2.width, s2.height); out = s2.toDataURL('image/jpeg', 0.6);
		}
		return out.length <= 45000 ? out : '';
	}

	function loadImage(blob) {
		return new Promise(function (ok, no) {
			var url = URL.createObjectURL(blob), im = new Image();
			im.onload = function () { URL.revokeObjectURL(url); ok(im); };
			im.onerror = function () { URL.revokeObjectURL(url); no(new Error('imagem ilegível')); };
			im.src = url;
		});
	}

	/* ---------- imagem comum ---------- */
	function previewImage(file) {
		if (file.size > 400 * 1048576) return Promise.resolve({ kind: 'imagem', note: 'Arquivo muito grande para gerar prévia aqui.' });
		return loadImage(file).then(function (im) {
			return { kind: 'imagem', thumb: toThumb(im, im.naturalWidth, im.naturalHeight), px: [im.naturalWidth, im.naturalHeight] };
		});
	}

	/* ---------- PDF ---------- */
	function pdfBoxFromText(txt) {
		// Procura /MediaBox [x0 y0 x1 y1] (e /UserUnit) no trecho lido.
		var m = /\/MediaBox\s*\[\s*([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s*\]/.exec(txt);
		if (!m) return null;
		var w = Math.abs(parseFloat(m[3]) - parseFloat(m[1])), h = Math.abs(parseFloat(m[4]) - parseFloat(m[2]));
		var uu = /\/UserUnit\s+([\d.]+)/.exec(txt), u = uu ? parseFloat(uu[1]) : 1;
		return w && h ? [w * u / 72 * 2.54, h * u / 72 * 2.54] : null;
	}
	function pdfSizeOnly(file) {
		var n = Math.min(file.size, 2 * 1048576);
		return Promise.all([read(file, 0, n), file.size > n ? read(file, Math.max(0, file.size - n), file.size) : Promise.resolve(new ArrayBuffer(0))]).then(function (b) {
			var dec = new TextDecoder('latin1');
			var box = pdfBoxFromText(dec.decode(b[0])) || pdfBoxFromText(dec.decode(b[1]));
			return box ? { kind: 'pdf', pw: box[0], ph: box[1], note: 'Prévia não gerada (só a medida da página foi lida).' } : { kind: 'pdf', note: 'Prévia não gerada para este PDF.' };
		});
	}
	function loadPdfJs() {
		if (window.pdfjsLib) return Promise.resolve(window.pdfjsLib);
		return new Promise(function (ok, no) {
			var s = document.createElement('script'); s.src = PDFJS; s.async = true;
			s.onload = function () { if (window.pdfjsLib) { window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER; ok(window.pdfjsLib); } else no(new Error('pdf.js')); };
			s.onerror = function () { no(new Error('pdf.js indisponível')); };
			document.head.appendChild(s);
		});
	}
	function previewPdf(file) {
		var size = pdfSizeOnly(file).catch(function () { return { kind: 'pdf' }; });
		if (file.size > PDF_FULL_LIMIT) return size;
		return size.then(function (base) {
			return loadPdfJs().then(function (lib) {
				return file.arrayBuffer().then(function (buf) { return lib.getDocument({ data: buf }).promise; }).then(function (doc) {
					return doc.getPage(1).then(function (page) {
						var v1 = page.getViewport({ scale: 1 }), sc = MAX / Math.max(v1.width, v1.height), vp = page.getViewport({ scale: sc });
						var c = document.createElement('canvas'); c.width = Math.ceil(vp.width); c.height = Math.ceil(vp.height);
						var ctx = c.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
						return page.render({ canvasContext: ctx, viewport: vp }).promise.then(function () {
							var q = 0.7, out = c.toDataURL('image/jpeg', q);
							while (out.length > MAX_BYTES && q > 0.3) { q -= 0.1; out = c.toDataURL('image/jpeg', q); }
							var wcm = v1.width / 72 * 2.54, hcm = v1.height / 72 * 2.54;
							return { kind: 'pdf', thumb: out.length <= 45000 ? out : '', pw: base.pw || wcm, ph: base.ph || hcm, pages: doc.numPages };
						});
					});
				});
			}).catch(function () { return base; });
		});
	}

	/* ---------- CorelDRAW (.cdr) ---------- */
	// Formato novo (X7/X8 em diante): é um ZIP com a miniatura dentro. Formato antigo: contêiner RIFF com o bloco "DISP" (bitmap).
	function inflateRaw(u8) {
		if (typeof DecompressionStream === 'undefined') return Promise.reject(new Error('sem DecompressionStream'));
		var ds = new DecompressionStream('deflate-raw'), w = ds.writable.getWriter();
		w.write(u8); w.close();
		return new Response(ds.readable).arrayBuffer().then(function (b) { return new Uint8Array(b); });
	}
	function zipEntries(file) {
		var tail = Math.min(file.size, 66000);
		return read(file, file.size - tail, file.size).then(function (buf) {
			var u8 = new Uint8Array(buf), dv = new DataView(buf), i, eocd = -1;
			for (i = u8.length - 22; i >= 0; i--) { if (u8[i] === 0x50 && u8[i + 1] === 0x4b && u8[i + 2] === 5 && u8[i + 3] === 6) { eocd = i; break; } }
			if (eocd < 0) throw new Error('zip sem fim');
			var total = dv.getUint16(eocd + 10, true), cdSize = u32(dv, eocd + 12), cdOff = u32(dv, eocd + 16);
			if (cdOff === 0xffffffff) throw new Error('zip64 não suportado');
			return read(file, cdOff, cdOff + cdSize).then(function (cb) {
				var c8 = new Uint8Array(cb), cv = new DataView(cb), p = 0, list = [];
				for (var n = 0; n < total && p + 46 <= c8.length; n++) {
					if (u32(cv, p) !== 0x02014b50) break;
					var method = cv.getUint16(p + 10, true), csize = u32(cv, p + 20), usize = u32(cv, p + 24), nl = cv.getUint16(p + 28, true), el = cv.getUint16(p + 30, true), cl = cv.getUint16(p + 32, true), lo = u32(cv, p + 42);
					list.push({ name: ascii(c8, p + 46, nl), method: method, csize: csize, usize: usize, off: lo });
					p += 46 + nl + el + cl;
				}
				return list;
			});
		});
	}
	function zipRead(file, e) {
		return read(file, e.off, e.off + 30).then(function (hb) {
			var hv = new DataView(hb), nl = hv.getUint16(26, true), el = hv.getUint16(28, true), start = e.off + 30 + nl + el;
			return read(file, start, start + e.csize).then(function (db) {
				var u8 = new Uint8Array(db);
				if (e.method === 0) return u8;
				if (e.method === 8) return inflateRaw(u8);
				throw new Error('compressão não suportada');
			});
		});
	}
	function cdrFromZip(file) {
		return zipEntries(file).then(function (list) {
			var cands = list.filter(function (e) { return /\.(png|bmp|jpe?g)$/i.test(e.name) && /thumb|preview|prev/i.test(e.name); });
			if (!cands.length) cands = list.filter(function (e) { return /\.(png|bmp|jpe?g)$/i.test(e.name) && e.usize < 3 * 1048576; });
			if (!cands.length) throw new Error('sem miniatura no zip');
			cands.sort(function (a, b) { return b.usize - a.usize; });
			return zipRead(file, cands[0]).then(function (u8) {
				var type = /\.png$/i.test(cands[0].name) ? 'image/png' : /\.bmp$/i.test(cands[0].name) ? 'image/bmp' : 'image/jpeg';
				return loadImage(new Blob([u8], { type: type }));
			});
		});
	}
	function dibToBlob(dib) {
		// dib = Uint8Array a partir do BITMAPINFOHEADER (sem o cabeçalho de arquivo BMP)
		var dv = new DataView(dib.buffer, dib.byteOffset, dib.byteLength), hs = dv.getUint32(0, true), bpp, colors = 0, comp = 0;
		if (hs === 12) { bpp = dv.getUint16(10, true); } else { bpp = dv.getUint16(14, true); comp = dv.getUint32(16, true); colors = dv.getUint32(32, true); }
		var pal = 0;
		if (bpp <= 8) pal = (colors || (1 << bpp)) * (hs === 12 ? 3 : 4);
		if (comp === 3 && hs === 40) pal += 12; // BI_BITFIELDS
		var off = 14 + hs + pal, out = new Uint8Array(14 + dib.length), ov = new DataView(out.buffer);
		out[0] = 0x42; out[1] = 0x4d; ov.setUint32(2, out.length, true); ov.setUint32(10, off, true);
		out.set(dib, 14);
		return new Blob([out], { type: 'image/bmp' });
	}
	function cdrFromRiff(file) {
		return read(file, 0, 12).then(function (hb) {
			var h = new Uint8Array(hb);
			if (ascii(h, 0, 4) !== 'RIFF' || ascii(h, 8, 3) !== 'CDR') throw new Error('não é RIFF CDR');
			var off = 12, tries = 0;
			function next() {
				if (tries++ > 400 || off >= file.size) return Promise.reject(new Error('sem DISP'));
				return read(file, off, off + 8).then(function (b) {
					var u = new Uint8Array(b), dv = new DataView(b), id = ascii(u, 0, 4), size = dv.getUint32(4, true);
					if (id === 'DISP') {
						if (size > 6 * 1048576) return Promise.reject(new Error('DISP grande demais'));
						return read(file, off + 8, off + 8 + size).then(function (db) {
							var d = new Uint8Array(db), v = new DataView(db), start = 0;
							if (v.getUint32(0, true) === 40 || v.getUint32(0, true) === 12) start = 0;
							else if (size > 8 && (v.getUint32(4, true) === 40 || v.getUint32(4, true) === 12)) start = 4;
							else return Promise.reject(new Error('DISP desconhecido'));
							return loadImage(dibToBlob(d.subarray(start)));
						});
					}
					off += 8 + size + (size & 1);
					return next();
				});
			}
			return next();
		});
	}
	function previewCdr(file) {
		return read(file, 0, 4).then(function (b) {
			var u = new Uint8Array(b), zip = u[0] === 0x50 && u[1] === 0x4b;
			return (zip ? cdrFromZip(file) : cdrFromRiff(file));
		}).then(function (im) {
			return { kind: 'cdr', thumb: toThumb(im, im.naturalWidth, im.naturalHeight) };
		}).catch(function () {
			return { kind: 'cdr', note: 'Sem prévia: este CDR não traz miniatura. Se puder, peça também um PDF ou JPG de referência.' };
		});
	}

	/* ---------- entrada ---------- */
	window.apArtPreview = function (file) {
		var e = ext(file && file.name), p;
		try {
			if (e === 'cdr') p = previewCdr(file);
			else if (e === 'pdf') p = previewPdf(file);
			else if (/^(jpe?g|png|gif|webp|bmp)$/.test(e)) p = previewImage(file);
			else p = Promise.resolve({ kind: e || 'arquivo', note: e === 'tif' || e === 'tiff' ? 'Prévia não disponível para TIFF.' : '' });
		} catch (err) { p = Promise.resolve({ kind: e }); }
		return p.catch(function () { return { kind: e }; });
	};

	/* Compara o arquivo com a medida pedida. Devolve um aviso em texto ou ''. */
	window.apArtCheck = function (meta, wCm, hCm) {
		if (!meta || !wCm || !hCm) return '';
		var fmt = function (v) { return (Math.round(v * 10) / 10).toString().replace('.', ','); };
		if (meta.pw && meta.ph) {
			var a = meta.pw / meta.ph, b = wCm / hCm, ratioOff = Math.abs(a - b) / b, swapOff = Math.abs(1 / a - b) / b;
			var sameSize = (Math.abs(meta.pw - wCm) / wCm < 0.03 && Math.abs(meta.ph - hCm) / hCm < 0.03) || (Math.abs(meta.pw - hCm) / hCm < 0.03 && Math.abs(meta.ph - wCm) / wCm < 0.03);
			if (sameSize) return '';
			if (Math.min(ratioOff, swapOff) > 0.03) return 'O arquivo tem ' + fmt(meta.pw) + ' × ' + fmt(meta.ph) + ' cm e a proporção não bate com o item (' + fmt(wCm) + ' × ' + fmt(hCm) + ' cm). Confira a medida ou o arquivo.';
			return 'O arquivo tem ' + fmt(meta.pw) + ' × ' + fmt(meta.ph) + ' cm (o item é ' + fmt(wCm) + ' × ' + fmt(hCm) + ' cm). A proporção bate: a gráfica vai ajustar a escala.';
		}
		if (meta.px) {
			var dpi = Math.min(meta.px[0] / (wCm / 2.54), meta.px[1] / (hCm / 2.54)), dpi2 = Math.min(meta.px[1] / (wCm / 2.54), meta.px[0] / (hCm / 2.54));
			dpi = Math.max(dpi, dpi2);
			if (dpi < 50) return 'Resolução baixa para este tamanho (' + Math.round(dpi) + ' dpi). Pode sair pixelado: se tiver o arquivo em PDF ou em maior resolução, envie.';
		}
		return '';
	};
})();
