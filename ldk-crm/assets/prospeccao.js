/* LDK · prospecção: busca no Google (Places), e-mail pelo site e envio ao funil */
(function () {
	'use strict';
	var form = document.querySelector('[data-pros-form]');
	if (!form || !window.LK) return;
	var box = document.querySelector('[data-pros-box]'), body = document.querySelector('[data-pros-body]'), msg = document.querySelector('[data-pros-msg]'), more = document.querySelector('[data-pros-more]'), title = document.querySelector('[data-pros-title]');
	var rows = [], next = '', query = '';
	function api(path, data) {
		return fetch(window.LK.rest + path, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': window.LK.nonce, 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
			.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Erro.'); return j; }); });
	}
	function td(tr, content) { var c = document.createElement('td'); if (content instanceof Node) c.appendChild(content); else c.textContent = content || '—'; tr.appendChild(c); return c; }
	function link(href, text) { var a = document.createElement('a'); a.href = href; a.textContent = text; a.target = '_blank'; a.rel = 'noopener'; return a; }
	function draw(p) {
		var tr = document.createElement('tr'); p.tr = tr;
		var cb = document.createElement('input'); cb.type = 'checkbox'; cb.disabled = !!p.known; p.cb = cb; td(tr, cb);
		var nm = document.createElement('span'); var s = document.createElement('strong'); s.textContent = p.name; nm.appendChild(s);
		if (p.known) { var b = document.createElement('em'); b.className = 'badge'; b.textContent = p.known === 'cliente' ? 'já é cliente' : 'já no funil'; nm.appendChild(document.createTextNode(' ')); nm.appendChild(b); }
		if (p.maps) { nm.appendChild(document.createTextNode(' ')); nm.appendChild(link(p.maps, 'mapa')); }
		td(tr, nm);
		td(tr, p.niche);
		var ph = p.phone ? (function () { var w = document.createElement('span'); w.appendChild(link('tel:' + p.phone.replace(/\D/g, ''), p.phone)); if (p.wa) { w.appendChild(document.createTextNode(' ')); w.appendChild(link(p.wa, 'WhatsApp')); } return w; })() : '—';
		td(tr, ph);
		p.emailTd = td(tr, p.email || '—');
		p.igTd = td(tr, p.instagram ? link('https://instagram.com/' + p.instagram, '@' + p.instagram) : '—');
		td(tr, p.website ? link(p.website, p.website.replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, '')) : '—');
		td(tr, p.rating ? '★ ' + p.rating.toFixed(1) + ' (' + p.count + ')' : '—');
		td(tr, p.address);
		body.appendChild(tr);
	}
	function search(token) {
		msg.textContent = 'Buscando no Google…';
		var f = new FormData(form);
		return api('prospect/search', { niche: f.get('niche'), city: f.get('city'), token: token || '' }).then(function (res) {
			if (!token) { rows = []; body.innerHTML = ''; }
			query = res.query; next = res.next || '';
			res.places.forEach(function (p) { rows.push(p); draw(p); });
			box.hidden = false; more.hidden = !next;
			title.textContent = rows.length + ' empresa(s) para "' + query + '"';
			msg.textContent = res.places.length ? '' : 'O Google não achou nada para essa busca. Tente outro nicho ou cidade.';
		}).catch(function (e) { msg.textContent = e.message; });
	}
	function selected() { return rows.filter(function (p) { return p.cb && p.cb.checked; }); }
	form.addEventListener('submit', function (e) { e.preventDefault(); search(''); });
	more.addEventListener('click', function () { search(next); });
	document.querySelector('[data-pros-all]').addEventListener('click', function () { var any = rows.some(function (p) { return !p.known && !p.cb.checked; }); rows.forEach(function (p) { if (!p.known) p.cb.checked = any; }); });
	document.querySelector('[data-pros-emails]').addEventListener('click', function (e) {
		var btn = e.currentTarget, list = selected().filter(function (p) { return p.website && (!p.email || !p.instagram); });
		if (!list.length) { alert('Marque empresas que tenham site e ainda não tenham e-mail ou Instagram.'); return; }
		btn.disabled = true; var i = 0;
		(function step() {
			if (i >= list.length) { btn.disabled = false; btn.textContent = '📧 Buscar e-mail e Instagram'; return; }
			var p = list[i++]; btn.textContent = 'Lendo site ' + i + '/' + list.length + '…'; p.emailTd.textContent = '…'; p.igTd.textContent = '…';
			api('prospect/email', { url: p.website }).then(function (r) {
				p.email = p.email || (r.emails || [])[0] || ''; p.emailTd.textContent = p.email || 'não achei';
				p.instagram = p.instagram || r.instagram || ''; p.igTd.textContent = ''; if (p.instagram) p.igTd.appendChild(link('https://instagram.com/' + p.instagram, '@' + p.instagram)); else p.igTd.textContent = 'não achei';
			}).catch(function () { p.emailTd.textContent = 'erro'; p.igTd.textContent = 'erro'; }).then(step);
		})();
	});
	document.querySelector('[data-pros-add]').addEventListener('click', function () {
		var list = selected(); if (!list.length) { alert('Marque as empresas que quer adicionar.'); return; }
		api('prospect/add', { items: list.map(function (p) { return { id: p.id, name: p.name, phone: p.phone, email: p.email || '', instagram: p.instagram || '', website: p.website, address: p.address, niche: p.niche, rating: p.rating, count: p.count, maps: p.maps, query: query }; }) })
			.then(function (r) { list.forEach(function (p) { p.known = 'lead'; p.cb.checked = false; p.cb.disabled = true; }); msg.innerHTML = ''; msg.appendChild(document.createTextNode('✅ ' + r.added + ' adicionada(s) ao funil' + (r.skipped ? ' (' + r.skipped + ' já existiam)' : '') + '. ')); msg.appendChild(link(r.funnel, 'Abrir o funil')); })
			.catch(function (e) { msg.textContent = e.message; });
	});
	document.querySelector('[data-pros-csv]').addEventListener('click', function () {
		if (!rows.length) return;
		var q = function (v) { return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"'; };
		var csv = '﻿Empresa;Nicho;Telefone;E-mail;Instagram;Site;Nota;Avaliações;Endereço;Maps\n' + rows.map(function (p) { return [p.name, p.niche, p.phone, p.email || '', p.instagram ? '@' + p.instagram : '', p.website, p.rating, p.count, p.address, p.maps].map(q).join(';'); }).join('\n');
		var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' })); a.download = 'prospeccao.csv'; a.click();
	});
})();
