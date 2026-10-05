/* LDK · subir arte: imagens pelo WordPress (prévia + cópia no Drive); vídeos grandes direto no Drive */
document.addEventListener('DOMContentLoaded', function () {
	'use strict';
	var input = document.querySelector('[data-media-upload]');
	if (!input || !window.LK) return;
	var form = input.closest('form'), list = form.querySelector('[data-upfiles]'), hid = form.querySelector('[data-new-media]');
	var client = input.getAttribute('data-client'), busy = 0, grid = form.querySelector('[data-media-grid]');
	var api = function (path, body, isForm) {
		return fetch(window.LK.rest + path, { method: 'POST', credentials: 'same-origin', headers: isForm ? { 'X-WP-Nonce': window.LK.nonce } : { 'X-WP-Nonce': window.LK.nonce, 'Content-Type': 'application/json' }, body: isForm ? body : JSON.stringify(body) }).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Erro.'); return j; }); });
	};
	function put(url, file, chunk, bar) {
		return new Promise(function (ok, no) {
			var s = 0;
			(function next() {
				var e = Math.min(s + chunk, file.size), x = new XMLHttpRequest();
				x.open('PUT', url); x.setRequestHeader('Content-Range', 'bytes ' + s + '-' + (e - 1) + '/' + file.size);
				x.upload.onprogress = function (ev) { bar.style.width = Math.round((s + ev.loaded) / file.size * 100) + '%'; };
				x.onload = function () { if (x.status === 308) { var r = x.getResponseHeader('Range'); s = r ? +r.split('-')[1] + 1 : e; next(); } else if (x.status < 300) ok(JSON.parse(x.responseText)); else no(new Error('Drive recusou (' + x.status + ').')); };
				x.onerror = function () { setTimeout(next, 3000); };
				x.send(file.slice(s, e));
			})();
		});
	}
	function toggle() { form.querySelectorAll('[data-media-save]').forEach(function (b) { b.disabled = busy > 0; }); }


	/* ---- Limites do Instagram (valores vêm do servidor em window.LK_LIMITS) ---- */
	var L = window.LK_LIMITS || { image_mb: 8, image_max_w: 1440, video_mb: 300, video_min_s: 3, video_max_s: 900, story_mb: 100, story_min_s: 3, story_max_s: 60, carousel_max: 10, video_ext: ['mp4', 'mov'] };
	function fmtNow() { return (document.querySelector('select[name="format"]') || {}).value || ''; }
	function mb(n) { return (n / 1048576).toFixed(n > 10485760 ? 0 : 1) + ' MB'; }
	function reencode(img, file) { // JPEG, até a largura máxima e dentro do peso máximo
		return new Promise(function (ok) {
			var jpeg = /^image\/jpeg$/.test(file.type);
			if (jpeg && file.size <= L.image_mb * 1048576 && img.width <= L.image_max_w) return ok(file);
			var w = Math.min(img.width, L.image_max_w), h = Math.round(img.height * w / img.width), c = document.createElement('canvas');
			c.width = w; c.height = h; var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, w, h); x.drawImage(img, 0, 0, w, h);
			(function tryQ(q) { c.toBlob(function (b) { if (b.size > L.image_mb * 1048576 && q > 0.5) return tryQ(q - 0.15); ok(new File([b], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' })); }, 'image/jpeg', q); })(0.92);
		});
	}
	function videoDialog(file) {
		return new Promise(function (done) {
			var story = fmtNow() === 'story', maxMb = story ? L.story_mb : L.video_mb, minS = story ? L.story_min_s : L.video_min_s, maxS = story ? L.story_max_s : L.video_max_s;
			var ext = (file.name.split('.').pop() || '').toLowerCase(), url = URL.createObjectURL(file);
			var el = document.createElement('div'); el.className = 'lkcrop';
			el.innerHTML = '<div class="lkcrop-box" role="dialog" aria-modal="true"><div class="lkcrop-head"><strong>Conferir vídeo</strong><span class="muted small">' + file.name + '</span></div>' +
				'<div class="lkcrop-stage"><video controls playsinline preload="metadata" style="max-width:100%;max-height:360px"></video></div><ul class="lkv-checks"></ul>' +
				'<div class="lkcrop-actions"><button type="button" class="btn btn--ghost" data-skip>Cancelar</button><button type="button" class="btn btn--primary" data-ok disabled>Usar este vídeo</button></div></div>';
			document.body.appendChild(el);
			var v = el.querySelector('video'), ul = el.querySelector('.lkv-checks'), ok = el.querySelector('[data-ok]');
			function row(level, text) { var li = document.createElement('li'); li.className = 'lkv-' + level; li.textContent = (level === 'ok' ? '✓ ' : level === 'warn' ? '⚠ ' : '✗ ') + text; ul.appendChild(li); return level; }
			function check(meta) {
				ul.innerHTML = ''; var bad = 0;
				bad += row(L.video_ext.indexOf(ext) >= 0 ? 'ok' : 'err', 'Formato .' + ext + (L.video_ext.indexOf(ext) >= 0 ? '' : ' — use MP4 ou MOV')) === 'err';
				bad += row(file.size <= maxMb * 1048576 ? 'ok' : 'err', 'Tamanho ' + mb(file.size) + ' (máximo ' + maxMb + ' MB' + (story ? ' para Story' : '') + ')') === 'err';
				if (meta) {
					var d = meta.duration;
					bad += row(d >= minS && d <= maxS ? 'ok' : 'err', 'Duração ' + Math.round(d) + ' s (de ' + minS + ' s a ' + (maxS >= 60 ? Math.round(maxS / 60) + ' min' : maxS + ' s') + ')') === 'err';
					var r = meta.w / meta.h;
					if (fmtNow() === 'reels' || story) row(Math.abs(r - 0.5625) < 0.03 ? 'ok' : 'warn', 'Proporção ' + meta.w + '×' + meta.h + (Math.abs(r - 0.5625) < 0.03 ? ' (9:16)' : ' — o ideal é vertical 9:16 (1080×1920); pode ser cortado'));
					row(meta.w >= 540 ? 'ok' : 'warn', 'Resolução ' + meta.w + '×' + meta.h + (meta.w >= 540 ? '' : ' — baixa, vai ficar com pouca qualidade'));
				} else row('warn', 'Não consegui ler os dados do vídeo neste navegador. Confira se está em H.264/AAC antes de enviar.');
				ok.disabled = !!bad;
			}
			check(null);
			v.addEventListener('loadedmetadata', function () { check({ duration: v.duration, w: v.videoWidth, h: v.videoHeight }); });
			v.addEventListener('error', function () { check(null); });
			v.src = url;
			function close(res) { URL.revokeObjectURL(url); el.remove(); done(res); }
			el.querySelector('[data-skip]').onclick = function () { close(null); };
			ok.onclick = function () { close(file); };
		});
	}
	function prepare(file) {
		var isVideo = /^video\//.test(file.type) || /\.(mp4|mov|m4v|webm|avi|mkv)$/i.test(file.name);
		if (isVideo) return videoDialog(file);
		if (croppable(file)) return cropDialog(file);
		alert('"' + file.name + '": o Instagram só aceita imagem JPG. Envie JPG, PNG ou WebP (eu converto).');
		return null;
	}
	/* ---- Recorte automático (tamanhos exatos do Instagram) ---- */
	var RATIOS = [
		{ k: '4:5',  label: 'Feed 4:5',     w: 1080, h: 1350 },
		{ k: '1:1',  label: 'Quadrado 1:1', w: 1080, h: 1080 },
		{ k: '1.91', label: 'Paisagem',     w: 1080, h: 566 },
		{ k: '9:16', label: 'Story/Reels 9:16', w: 1080, h: 1920 }
	];
	var last = null; // proporção do primeiro recorte: o carrossel mantém todas iguais
	function defaultRatio() {
		if (last) return last;
		var f = document.querySelector('select[name="format"]');
		return RATIOS[f && /^(story|reels)$/.test(f.value) ? 3 : 0];
	}
	function croppable(file) { return /^image\/(jpeg|png|webp)$/.test(file.type); }
	function cropDialog(file) {
		return new Promise(function (done) {
			var url = URL.createObjectURL(file), img = new Image();
			img.onerror = function () { URL.revokeObjectURL(url); done(file); }; // formato que o navegador não abre: envia como está
			img.onload = function () {
				var r = defaultRatio(), box, fw, fh, base, scale = 1, zoom = 1, ox = 0, oy = 0;
				var el = document.createElement('div'); el.className = 'lkcrop';
				el.innerHTML = '<div class="lkcrop-box" role="dialog" aria-modal="true"><div class="lkcrop-head"><strong>Enquadrar imagem</strong><span class="muted small">' + file.name + '</span></div>' +
					'<div class="lkcrop-ratios"></div><div class="lkcrop-stage"><canvas></canvas></div>' +
					'<label class="lkcrop-zoom">Zoom <input type="range" min="1" max="4" step="0.01" value="1"></label>' +
					'<p class="muted small lkcrop-info"></p>' +
					'<div class="lkcrop-actions"><button type="button" class="btn btn--ghost" data-orig>Usar sem recortar</button><button type="button" class="btn btn--ghost" data-skip>Cancelar</button><button type="button" class="btn btn--primary" data-ok>Recortar e usar</button></div></div>';
				document.body.appendChild(el);
				var cv = el.querySelector('canvas'), ctx = cv.getContext('2d'), rg = el.querySelector('input[type=range]'), info = el.querySelector('.lkcrop-info'), bar = el.querySelector('.lkcrop-ratios');
				function clamp() {
					var dw = img.width * scale * zoom, dh = img.height * scale * zoom;
					ox = Math.min(0, Math.max(fw - dw, ox)); oy = Math.min(0, Math.max(fh - dh, oy));
				}
				function draw() {
					clamp(); ctx.clearRect(0, 0, cv.width, cv.height);
					ctx.drawImage(img, ox, oy, img.width * scale * zoom, img.height * scale * zoom);
				}
				function layout(keep) {
					var maxW = Math.min(420, window.innerWidth - 64), maxH = Math.min(520, window.innerHeight - 320);
					var k = Math.min(maxW / r.w, maxH / r.h); fw = Math.round(r.w * k); fh = Math.round(r.h * k);
					cv.width = fw; cv.height = fh;
					scale = Math.max(fw / img.width, fh / img.height); // "cover": a foto preenche o quadro inteiro
					if (!keep) { zoom = 1; rg.value = 1; }
					ox = (fw - img.width * scale * zoom) / 2; oy = (fh - img.height * scale * zoom) / 2; draw();
					var srcW = Math.round(fw / (scale * zoom));
					info.textContent = 'Saída: ' + r.w + '×' + r.h + ' px' + (srcW < r.w * 0.8 ? ' · atenção: a foto é pequena para esse tamanho, pode ficar com qualidade menor.' : ' · arraste a foto para enquadrar.');
				}
				RATIOS.forEach(function (x) {
					var b = document.createElement('button'); b.type = 'button'; b.textContent = x.label; b.className = 'chip' + (x === r ? ' is-on' : '');
					b.onclick = function () { r = x; Array.prototype.forEach.call(bar.children, function (c) { c.classList.toggle('is-on', c === b); }); layout(false); };
					bar.appendChild(b);
				});
				var drag = null;
				cv.addEventListener('pointerdown', function (e) { drag = { x: e.clientX - ox, y: e.clientY - oy }; cv.setPointerCapture(e.pointerId); });
				cv.addEventListener('pointermove', function (e) { if (drag) { ox = e.clientX - drag.x; oy = e.clientY - drag.y; draw(); } });
				cv.addEventListener('pointerup', function () { drag = null; });
				cv.addEventListener('wheel', function (e) { e.preventDefault(); rg.value = Math.min(4, Math.max(1, +rg.value - e.deltaY / 500)); rg.dispatchEvent(new Event('input')); }, { passive: false });
				rg.addEventListener('input', function () {
					var cx = (fw / 2 - ox) / (img.width * scale * zoom), cy = (fh / 2 - oy) / (img.height * scale * zoom);
					zoom = +rg.value; ox = fw / 2 - cx * img.width * scale * zoom; oy = fh / 2 - cy * img.height * scale * zoom; draw();
				});
				function close(res) { URL.revokeObjectURL(url); el.remove(); done(res); }
				el.querySelector('[data-orig]').onclick = function () {
					var ratio = img.width / img.height, fmt = (document.querySelector('select[name="format"]') || {}).value || '';
					if (fmt !== 'story' && (ratio < 0.8 || ratio > 1.91) && !confirm('Essa proporção (' + img.width + '×' + img.height + ') pode ser recusada pelo Instagram no feed (aceita de 4:5 a 1.91:1). Usar mesmo assim?')) return;
					reencode(img, file).then(close);
				};
				el.querySelector('[data-skip]').onclick = function () { close(null); };
				el.querySelector('[data-ok]').onclick = function () {
					var out = document.createElement('canvas'); out.width = r.w; out.height = r.h;
					var sc = scale * zoom, o = out.getContext('2d');
					o.imageSmoothingQuality = 'high'; o.fillStyle = '#fff'; o.fillRect(0, 0, r.w, r.h);
					o.drawImage(img, -ox / sc, -oy / sc, fw / sc, fh / sc, 0, 0, r.w, r.h); // recorte exato na resolução final
					out.toBlob(function (b) {
						last = r;
						close(new File([b], file.name.replace(/\.[^.]+$/, '') + '-' + r.w + 'x' + r.h + '.jpg', { type: 'image/jpeg' }));
					}, 'image/jpeg', 0.92);
				};
				layout(false);
			};
			img.src = url;
		});
	}
	input.addEventListener('change', function () {
		var files = Array.prototype.slice.call(input.files), chain = Promise.resolve();
		input.value = '';
		if (fmtNow() === 'carrossel') { // carrossel: no máximo 10 itens no total
			var have = (grid ? grid.querySelectorAll('.media-item').length : 0) + JSON.parse(hid.value || '[]').length, room = L.carousel_max - have;
			if (files.length > room) { alert('Carrossel aceita no máximo ' + L.carousel_max + ' itens (já tem ' + have + '). Vou usar só os primeiros ' + Math.max(0, room) + '.'); files = files.slice(0, Math.max(0, room)); }
		}
		files.forEach(function (orig) {
			chain = chain.then(function () { return prepare(orig); }).then(function (file) { if (file) send(file); });
		});
	});
	function send(file) {
		{
			var el = document.createElement('div'); el.className = 'upfile'; el.innerHTML = '<span>' + file.name + '</span><div class="upbar"><i></i></div>'; list.appendChild(el);
			busy++; toggle();
			var isVideo = /^video\//.test(file.type) || /\.(mp4|mov|m4v|webm)$/i.test(file.name);
			var go = (!isVideo && file.size < 25 * 1048576)
				? (function () { var fd = new FormData(); fd.append('file', file); fd.append('client', client); return api('upload/small', fd, true); })()
				: api('upload/start', { name: file.name, size: file.size, type: file.type, client: client }).then(function (st) {
					if (st.mode === 'drive') return put(st.url, file, st.chunk, el.querySelector('i')).then(function (g) { return api('upload/done', { id: g.id }); });
					var fd = new FormData(); fd.append('file', file); fd.append('client', client); return api('upload/small', fd, true);
				});
			go.then(function (res) {
				var arr = JSON.parse(hid.value || '[]'); arr.push(res); hid.value = JSON.stringify(arr);
				el.className = 'upfile is-done'; el.innerHTML = '<span>✓ ' + res.name + '</span><small>enviado · agora clique em Salvar</small>';
			}).catch(function (err) { el.classList.add('is-err'); el.innerHTML += '<small class="text-late">' + err.message + '</small>'; })
			  .then(function () { busy--; toggle(); });
		}
	}
	// Ordem do carrossel (arrastar).
	if (grid) {
		var drag = null;
		grid.addEventListener('dragstart', function (e) { drag = e.target.closest('.media-item'); });
		grid.addEventListener('dragover', function (e) { e.preventDefault(); var t = e.target.closest('.media-item'); if (t && drag && t !== drag) { var r = t.getBoundingClientRect(); grid.insertBefore(drag, (e.clientX - r.left) > r.width / 2 ? t.nextSibling : t); } });
		grid.addEventListener('drop', function () { form.querySelector('[data-order]').value = Array.prototype.map.call(grid.querySelectorAll('.media-item'), function (m) { return m.getAttribute('data-i'); }).join(','); });
	}
	form.addEventListener('submit', function (e) { if (busy) { e.preventDefault(); alert('Espere terminar o envio.'); } });
});
