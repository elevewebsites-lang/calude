/* AllPrint · reenviar arquivo corrigido (mesmo fluxo de upload do pedido) */
document.addEventListener('DOMContentLoaded', function () {
	'use strict';
	var boxes = document.querySelectorAll('[data-reup]');
	if (!boxes.length || !window.AP) return;
	var busy = 0;
	var api = function (path, body, isForm) {
		return fetch(window.AP.rest + path, { method: 'POST', credentials: 'same-origin', headers: isForm ? { 'X-WP-Nonce': window.AP.nonce } : { 'X-WP-Nonce': window.AP.nonce, 'Content-Type': 'application/json' }, body: isForm ? body : JSON.stringify(body) }).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Erro.'); return j; }); });
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
	boxes.forEach(function (b) {
		var input = b.querySelector('[data-reup-input]'), list = b.querySelector('[data-upfiles]'), hid = b.querySelector('[data-reup-files]');
		input.addEventListener('change', function () {
			Array.prototype.forEach.call(input.files, function (file) {
				var el = document.createElement('div'); el.className = 'upfile'; el.innerHTML = '<span>' + file.name + '</span><div class="upbar"><i></i></div>'; list.appendChild(el);
				busy++;
				var art = window.apArtPreview ? window.apArtPreview(file) : Promise.resolve({});
				api('upload/start', { name: file.name, size: file.size, type: file.type }).then(function (st) {
					var viaServer = function () { var fd = new FormData(); fd.append('file', file); return api('upload/small', fd, true); };
					if (st.mode === 'drive') return put(st.url, file, st.chunk, el.querySelector('i')).then(function (g) { return api('upload/done', { id: g.id }); }).catch(function (err) { if (/403|espa/i.test(err.message || '')) return viaServer(); throw err; });
					return viaServer();
				}).then(function (res) { return art.then(function (m) { m = m || {}; if (m.thumb) res.thumb = m.thumb; if (m.pw) { res.pw = m.pw; res.ph = m.ph; } if (m.note) res.note = m.note; return res; }); }).then(function (res) {
					var arr = JSON.parse(hid.value || '[]'); arr.push(res); hid.value = JSON.stringify(arr);
					el.className = 'upfile is-done'; el.innerHTML = '<span>✓ ' + res.name + '</span>';
				}).catch(function (err) { el.classList.add('is-err'); el.innerHTML += '<small class="text-late">' + err.message + '</small>'; }).then(function () { busy--; });
			});
			input.value = '';
		});
	});
	var form = boxes[0].closest('form');
	form.addEventListener('submit', function (e) { if (busy) { e.preventDefault(); alert('Espere terminar o envio.'); } });
});
