/* LDK · subir arte: imagens pelo WordPress (prévia + cópia no Drive); vídeos grandes direto no Drive */
document.addEventListener('DOMContentLoaded', function () {
	'use strict';
	var input = document.querySelector('[data-media-upload]');
	if (!input || !window.LK) return;
	var form = input.closest('form'), list = form.querySelector('[data-upfiles]'), hid = form.querySelector('[data-new-media]');
	var client = input.getAttribute('data-client'), busy = 0;
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
				el.querySelector('[data-orig]').onclick = function () { close(file); };
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
		files.forEach(function (orig) {
			chain = chain.then(function () { return croppable(orig) ? cropDialog(orig) : orig; }).then(function (file) { if (file) send(file); });
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
	var grid = form.querySelector('[data-media-grid]');
	if (grid) {
		var drag = null;
		grid.addEventListener('dragstart', function (e) { drag = e.target.closest('.media-item'); });
		grid.addEventListener('dragover', function (e) { e.preventDefault(); var t = e.target.closest('.media-item'); if (t && drag && t !== drag) { var r = t.getBoundingClientRect(); grid.insertBefore(drag, (e.clientX - r.left) > r.width / 2 ? t.nextSibling : t); } });
		grid.addEventListener('drop', function () { form.querySelector('[data-order]').value = Array.prototype.map.call(grid.querySelectorAll('.media-item'), function (m) { return m.getAttribute('data-i'); }).join(','); });
	}
	form.addEventListener('submit', function (e) { if (busy) { e.preventDefault(); alert('Espere terminar o envio.'); } });
});
