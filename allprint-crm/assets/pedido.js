/* AllPrint · novo pedido: preço ao vivo (mesma regra de includes/grafica.php) + upload grande para o Drive */
(function () {
	'use strict';
	var C = window.AP_CAT, box = document.querySelector('[data-order]');
	if (!C || !box) return;
	var form = box.closest('form');
	var $ = function (s, c) { return (c || document).querySelector(s); };
	var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
	var num = function (v) { return parseFloat(String(v || '').replace(',', '.')) || 0; };
	var money = function (v) { return 'R$ ' + (Math.round(v * 100) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
	var m2 = function (v) { return (Math.round(v * 100) / 100).toFixed(2).replace('.', ',') + ' m²'; };
	var uploading = 0;

	function items() { return $$('[data-oitem]', box); }
	function f(row, k) { return $('[data-f="' + k + '"]', row); }

	function renumber() {
		items().forEach(function (row, i) {
			$('[data-n]', row).textContent = i + 1;
			$$('[data-side]', row).forEach(function (c) { c.name = 'i[' + c.getAttribute('data-side') + '][' + i + ']'; });
			f(row, 'lam').name = 'i[lam][' + i + ']';
		});
	}

	function render() {
		var byMat = {}, lamArea = 0, eyeM = 0, total = 0;
		items().forEach(function (row) {
			var m = C.items[f(row, 'material').value];
			var w = num(f(row, 'w').value), h = num(f(row, 'h').value), q = Math.max(1, parseInt(f(row, 'qty').value, 10) || 1);
			f(row, 'eyelets').hidden = !(m && m.eyelets);
			f(row, 'lamwrap').hidden = !(m && m.lam);
			f(row, 'finishwrap').hidden = !(m && /banner/i.test(m.name));
			f(row, 'info').textContent = m ? 'Larguras da bobina: ' + m.widths + ' cm' + (m.included ? ' · Incluso: ' + m.included : '') : '';
			var warn = f(row, 'warn'), msg = '';
			if (!m || !w || !h) { f(row, 'area').textContent = ''; f(row, 'price').textContent = ''; warn.hidden = true; return; }
			var area = w * h / 10000 * q;
			if (m.maxw && Math.min(w, h) > m.maxw) msg = 'A peça passa da largura máxima da bobina (' + m.maxw + ' cm). Divida em partes ou fale com a gente.';
			warn.textContent = msg; warn.hidden = !msg;
			byMat[f(row, 'material').value] = (byMat[f(row, 'material').value] || 0) + area;
			var lin = 0;
			if (m.eyelets) {
				if ($('[data-side=st]', row).checked) lin += w / 100;
				if ($('[data-side=sb]', row).checked) lin += w / 100;
				if ($('[data-side=sl]', row).checked) lin += h / 100;
				if ($('[data-side=sr]', row).checked) lin += h / 100;
				lin *= q;
			}
			eyeM += lin;
			var lam = m.lam && f(row, 'lam').checked;
			if (lam) lamArea += area;
			f(row, 'area').textContent = m2(area) + (lin ? ' · ' + lin.toFixed(2).replace('.', ',') + ' m de ilhós' : '') + (lam ? ' · laminado' : '');
			f(row, 'price').textContent = money(area * m.price + lin * C.eyelet + (lam ? area * C.lam : 0));
		});
		var lines = [];
		Object.keys(byMat).forEach(function (id) {
			var a = byMat[id], bill = Math.max(C.minArea, a), v = bill * C.items[id].price;
			total += v;
			lines.push('<div><span>' + C.items[id].name + '<small>' + m2(a) + (a < C.minArea ? ' → cobrado ' + m2(bill) + ' (mínimo)' : '') + '</small></span><b>' + money(v) + '</b></div>');
		});
		if (lamArea) { var lb = Math.max(C.minArea, lamArea), lv = lb * C.lam; total += lv; lines.push('<div><span>Laminação<small>' + m2(lamArea) + (lamArea < C.minArea ? ' → mínimo ' + m2(lb) : '') + '</small></span><b>' + money(lv) + '</b></div>'); }
		if (eyeM) { var ev = eyeM * C.eyelet; total += ev; lines.push('<div><span>Reforço e ilhós<small>' + eyeM.toFixed(2).replace('.', ',') + ' m linear</small></span><b>' + money(ev) + '</b></div>'); }
		$('[data-sum-lines]').innerHTML = lines.length ? lines.join('') : '<p class="muted small">Escolha o material e as medidas.</p>';
		$('[data-sum-total]').textContent = money(total);
		var btn = $('[data-submit]');
		btn.disabled = uploading > 0;
		btn.textContent = uploading ? 'Aguarde o envio dos arquivos…' : ($('[name=pagamento]:checked') && $('[name=pagamento]:checked').value === 'retirada' ? 'Finalizar pedido' : 'Finalizar e pagar ' + money(total));
	}

	form.addEventListener('input', render);
	form.addEventListener('change', render);
	box.addEventListener('click', function (e) {
		if (e.target.closest('[data-oitem-add]')) {
			var first = items()[0], copy = first.cloneNode(true);
			$$('input:not([type=checkbox]):not([type=file]), textarea', copy).forEach(function (i) { i.value = i.matches('[data-f=qty]') ? 1 : ''; });
			$$('input[type=checkbox]', copy).forEach(function (c) { c.checked = false; });
			$$('select', copy).forEach(function (s) { s.selectedIndex = s.matches('[data-f=material]') ? 0 : 0; });
			f(copy, 'files').value = '[]';
			$('[data-upfiles]', copy).innerHTML = '';
			box.insertBefore(copy, e.target.closest('[data-oitem-add]'));
			renumber(); render();
			f(copy, 'material').focus();
		}
		var del = e.target.closest('[data-oitem-del]');
		if (del && items().length > 1) { del.closest('[data-oitem]').remove(); renumber(); render(); }
		var rm = e.target.closest('[data-rmfile]');
		if (rm) {
			var row = rm.closest('[data-oitem]'), list = JSON.parse(f(row, 'files').value || '[]');
			list.splice(+rm.getAttribute('data-rmfile'), 1);
			f(row, 'files').value = JSON.stringify(list);
			drawFiles(row); render();
		}
	});

	function drawFiles(row) {
		var list = JSON.parse(f(row, 'files').value || '[]');
		var done = list.map(function (x, i) { return '<div class="upfile is-done"><span>✓ ' + x.name + '<small>' + (x.size ? (x.size / 1048576).toFixed(1).replace('.', ',') + ' MB' : '') + '</small></span><button type="button" class="icon-btn" data-rmfile="' + i + '">×</button></div>'; }).join('');
		var box2 = $('[data-upfiles]', row);
		$$('.upfile.is-done', box2).forEach(function (n) { n.remove(); });
		box2.insertAdjacentHTML('afterbegin', done);
	}

	/* ---------- Upload ---------- */
	var api = function (path, body, isForm) {
		return fetch(window.AP.rest + path, { method: 'POST', credentials: 'same-origin', headers: isForm ? { 'X-WP-Nonce': window.AP.nonce } : { 'X-WP-Nonce': window.AP.nonce, 'Content-Type': 'application/json' }, body: isForm ? body : JSON.stringify(body) })
			.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Erro no envio.'); return j; }); });
	};

	function putChunks(url, file, chunk, bar) {
		return new Promise(function (resolve, reject) {
			var start = 0;
			function next() {
				var end = Math.min(start + chunk, file.size);
				var xhr = new XMLHttpRequest();
				xhr.open('PUT', url, true);
				xhr.setRequestHeader('Content-Range', 'bytes ' + start + '-' + (end - 1) + '/' + file.size);
				xhr.upload.onprogress = function (ev) { bar.style.width = Math.round((start + ev.loaded) / file.size * 100) + '%'; };
				xhr.onload = function () {
					if (xhr.status === 308) { var rg = xhr.getResponseHeader('Range'); start = rg ? parseInt(rg.split('-')[1], 10) + 1 : end; next(); }
					else if (xhr.status === 200 || xhr.status === 201) { try { resolve(JSON.parse(xhr.responseText)); } catch (e) { reject(new Error('Resposta inválida do Drive.')); } }
					else reject(new Error('O Drive recusou o envio (' + xhr.status + ').'));
				};
				xhr.onerror = function () { setTimeout(next, 3000); }; // queda de conexão: tenta o mesmo pedaço de novo
				xhr.send(file.slice(start, end));
			}
			next();
		});
	}

	function uploadOne(row, file) {
		var holder = $('[data-upfiles]', row);
		var el = document.createElement('div');
		el.className = 'upfile';
		el.innerHTML = '<span>' + file.name + '<small>' + (file.size / 1048576).toFixed(1).replace('.', ',') + ' MB</small></span><div class="upbar"><i></i></div>';
		holder.appendChild(el);
		var bar = $('i', el);
		uploading++; render();
		return api('upload/start', { name: file.name, size: file.size, type: file.type || 'application/octet-stream' })
			.then(function (s) {
				if (s.mode === 'drive') return putChunks(s.url, file, s.chunk, bar).then(function (g) { return api('upload/done', { id: g.id }); });
				if (file.size > s.max) throw new Error('Arquivo maior que o limite do servidor (' + Math.round(s.max / 1048576) + ' MB). Peça para a ' + 'AllPrint conectar o Google Drive.');
				var fd = new FormData(); fd.append('file', file);
				return api('upload/small', fd, true);
			})
			.then(function (res) {
				var list = JSON.parse(f(row, 'files').value || '[]');
				list.push({ id: res.id, name: res.name, size: res.size, link: res.link });
				f(row, 'files').value = JSON.stringify(list);
				el.remove(); drawFiles(row);
			})
			.catch(function (err) { el.classList.add('is-err'); el.querySelector('.upbar').outerHTML = '<small class="text-late">' + err.message + '</small>'; })
			.then(function () { uploading--; render(); });
	}

	box.addEventListener('change', function (e) {
		if (!e.target.matches('[data-upload]')) return;
		var row = e.target.closest('[data-oitem]');
		Array.prototype.forEach.call(e.target.files, function (file) { uploadOne(row, file); });
		e.target.value = '';
	});
	window.addEventListener('beforeunload', function (e) { if (uploading) { e.preventDefault(); e.returnValue = ''; } });
	form.addEventListener('submit', function (e) {
		if (uploading) { e.preventDefault(); alert('Espere terminar o envio dos arquivos.'); return; }
		if (!$('[data-no-file]').checked && items().some(function (r) { return JSON.parse(f(r, 'files').value || '[]').length === 0; })) {
			e.preventDefault(); alert('Envie o arquivo de cada item ou marque "Vou enviar o arquivo depois".');
		}
	});
	renumber(); render();
})();
