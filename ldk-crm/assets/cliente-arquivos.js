/* LDK · fotos e arquivos do cliente: sobe direto para o Drive (ou pelo site) e registra na ficha. */
(function () {
	'use strict';
	document.querySelectorAll('[data-cfiles]').forEach(function (box) {
		var rest = box.getAttribute('data-rest'), nonce = box.getAttribute('data-nonce'), token = box.getAttribute('data-token'), client = box.getAttribute('data-client');
		var sel = box.querySelector('[data-cf-folder]'), newWrap = box.querySelector('[data-cf-new]'), newInp = newWrap && newWrap.querySelector('input');
		var drop = box.querySelector('[data-cf-drop]'), input = box.querySelector('[data-cf-input]'), list = box.querySelector('[data-cf-list]'), done = box.querySelector('[data-cf-done]');
		var pending = 0, okCount = 0;
		function base(extra) { var o = token ? { token: token } : { client: client }; for (var k in extra) o[k] = extra[k]; return o; }
		function api(path, body, isForm) {
			var h = nonce ? { 'X-WP-Nonce': nonce } : {};
			if (!isForm) h['Content-Type'] = 'application/json';
			return fetch(rest + path, { method: 'POST', credentials: 'same-origin', headers: h, body: isForm ? body : JSON.stringify(body) })
				.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Erro.'); return j; }); });
		}
		function folder() { if (sel.value === '__novo') return (newInp.value || '').trim(); return sel.value; }
		sel.addEventListener('change', function () { newWrap.hidden = sel.value !== '__novo'; if (!newWrap.hidden) newInp.focus(); });
		function put(url, file, chunk, bar) {
			return new Promise(function (ok, no) {
				var s = 0;
				(function next() {
					var e = Math.min(s + chunk, file.size), x = new XMLHttpRequest();
					x.open('PUT', url); x.setRequestHeader('Content-Range', 'bytes ' + s + '-' + (e - 1) + '/' + file.size);
					x.upload.onprogress = function (ev) { bar.style.width = Math.round((s + ev.loaded) / file.size * 100) + '%'; };
					x.onload = function () { if (x.status === 308) { var r = x.getResponseHeader('Range'); s = r ? +r.split('-')[1] + 1 : e; next(); } else if (x.status < 300) ok(JSON.parse(x.responseText)); else no(new Error('O Drive recusou (' + x.status + ').')); };
					x.onerror = function () { setTimeout(next, 3000); };
					x.send(file.slice(s, e));
				})();
			});
		}
		function send(file, fo) {
			var el = document.createElement('div'); el.className = 'cf-file';
			el.innerHTML = '<span></span><div class="upbar"><i></i></div>'; el.firstChild.textContent = file.name; list.appendChild(el);
			var bar = el.querySelector('i'); pending++;
			var isVideo = /^video\//.test(file.type) || /\.(mp4|mov|m4v|webm)$/i.test(file.name);
			var small = function () { var fd = new FormData(); fd.append('file', file); fd.append('folder', fo); if (token) fd.append('token', token); else fd.append('client', client); return api('small', fd, true); };
			var go = (!isVideo && file.size < 25 * 1048576) ? small()
				: api('start', base({ name: file.name, size: file.size, type: file.type, folder: fo })).then(function (st) {
					if (st.mode !== 'drive') return small();
					return put(st.url, file, st.chunk, bar).then(function (g) { return { id: g.id }; });
				});
			go.then(function (res) { return thumbOf(file).then(function (t) { return api('add', base({ id: res.id, folder: fo, thumb: t })); }); })
				.then(function () { okCount++; el.className = 'cf-file is-done'; el.firstChild.textContent = '✓ ' + file.name; })
				.catch(function (err) { el.className = 'cf-file is-err'; el.firstChild.textContent = file.name + ' — ' + err.message; })
				.then(function () {
					pending--;
					if (!pending) {
						if (done && okCount) done.hidden = false;
						if (!token && okCount) setTimeout(function () { location.hash = 'arquivos'; location.reload(); }, 900);
					}
				});
		}
		function thumbOf(file) { // miniatura JPEG de 360 px feita no próprio navegador
			return new Promise(function (ok) {
				if (!/^image\/(jpeg|png|webp|gif)$/.test(file.type)) return ok('');
				var img = new Image(), u = URL.createObjectURL(file);
				img.onload = function () {
					var w = Math.min(360, img.width), h = Math.round(img.height * w / img.width), c = document.createElement('canvas');
					c.width = w; c.height = h; c.getContext('2d').drawImage(img, 0, 0, w, h);
					URL.revokeObjectURL(u); ok(c.toDataURL('image/jpeg', 0.72));
				};
				img.onerror = function () { ok(''); };
				img.src = u;
			});
		}
		function take(files) {
			var fo = folder();
			if (sel.value === '__novo' && !fo) { alert('Escreva o nome da pasta.'); return; }
			if (done) done.hidden = true;
			Array.prototype.forEach.call(files, function (f) { send(f, fo); });
		}
		input.addEventListener('change', function () { take(input.files); input.value = ''; });
		['dragover', 'dragenter'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
		['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); }); });
		drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) take(e.dataTransfer.files); });
		drop.addEventListener('click', function (e) { if (e.target !== input) { e.preventDefault(); input.click(); } });
	});
})();

/* Tela do post: "Usar as selecionadas" coloca as fotos do cliente na lista de arquivos do post. */
document.querySelectorAll('[data-cf-picker]').forEach(function (box) {
	var form = box.closest('form'), hid = form && form.querySelector('[data-new-media]'), list = form && form.querySelector('[data-upfiles]');
	var btn = box.querySelector('[data-cf-use]');
	if (!hid || !btn) return;
	btn.addEventListener('click', function () {
		var arr = JSON.parse(hid.value || '[]'), n = 0;
		box.querySelectorAll('input[type=checkbox]:checked').forEach(function (cb) {
			arr.push({ id: cb.value, name: cb.getAttribute('data-name'), size: +cb.getAttribute('data-size') || 0, link: cb.getAttribute('data-link') });
			cb.checked = false; n++;
			if (list) { var el = document.createElement('div'); el.className = 'upfile is-done'; el.innerHTML = '<span></span><small>escolhida · clique em Salvar</small>'; el.firstChild.textContent = '✓ ' + cb.getAttribute('data-name'); list.appendChild(el); }
		});
		hid.value = JSON.stringify(arr);
		if (!n) alert('Marque as fotos que quer usar.'); else box.open = false;
	});
});

/* Novo post: trocar o cliente recarrega as fotos dele no seletor. */
document.querySelectorAll('[data-cf-formpick]').forEach(function (box) {
	var form = box.closest('form'), sel = form && form.querySelector('[data-post-client]'), body = box.querySelector('[data-cf-formpick-body]');
	if (!sel || !body) return;
	sel.addEventListener('change', function () {
		body.innerHTML = '<p class="muted small">Carregando…</p>';
		fetch(box.getAttribute('data-rest') + '?client=' + encodeURIComponent(sel.value), { credentials: 'same-origin', headers: { 'X-WP-Nonce': box.getAttribute('data-nonce') } })
			.then(function (r) { return r.json(); }).then(function (d) { body.innerHTML = d.html; });
	});
});
