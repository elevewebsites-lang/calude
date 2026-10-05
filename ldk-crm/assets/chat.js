/* AllPrint · chat (cliente e equipe) + sininho */
(function () {
	'use strict';
	var CFG = window.LK;
	if (!CFG) return;
	var hdr = { 'X-WP-Nonce': CFG.nonce };
	// Sininho: atualiza a cada 20 s.
	var bells = document.querySelectorAll('[data-bell] .bell-n');
	if (bells.length) {
		var tick = function () {
			fetch(CFG.rest.replace(/[^/]+\/v1\/$/, '') + 'lk/v1/chat/unread', { headers: hdr, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
				bells.forEach(function (b) { b.textContent = j.n; b.hidden = !j.n; });
			}).catch(function () {});
		};
		setInterval(tick, 20000);
	}
	var box = document.querySelector('[data-chat]');
	if (!box) return;
	var list = box.querySelector('[data-chat-list]'), form = box.querySelector('[data-chat-form]');
	var client = box.getAttribute('data-client') || '';
	var url = CFG.rest.replace(/[^/]+\/v1\/$/, '') + 'lk/v1/chat';
	var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
	var last = '';
	function draw(msgs) {
		var html = msgs.map(function (m) {
			return '<div class="msg' + (m.mine ? ' is-mine' : '') + '"><div class="msg-b">' + (m.project ? '<em class="msg-ref">Pedido #' + m.project + '</em>' : '') + esc(m.body).replace(/\n/g, '<br>') + '</div><small>' + esc(m.who) + ' · ' + m.at + (m.mine && m.read ? ' · lida' : '') + '</small></div>';
		}).join('') || '<p class="muted small center">Nenhuma mensagem ainda. Mande a primeira!</p>';
		if (html !== last) { list.innerHTML = html; list.scrollTop = list.scrollHeight; last = html; }
	}
	function load() { fetch(url + '?client=' + client, { headers: hdr, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (j.messages) draw(j.messages); }); }
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var ta = form.querySelector('textarea'), body = ta.value.trim();
		if (!body) return;
		var btn = form.querySelector('button'); btn.disabled = true;
		fetch(url, { method: 'POST', headers: Object.assign({ 'Content-Type': 'application/json' }, hdr), credentials: 'same-origin', body: JSON.stringify({ client: client, body: body, project: (form.querySelector('[name=project]') || {}).value || 0 }) })
			.then(function (r) { return r.json(); }).then(function (j) { if (j.messages) { draw(j.messages); ta.value = ''; } else alert(j.message || 'Não foi possível enviar.'); })
			.finally(function () { btn.disabled = false; ta.focus(); });
	});
	form.querySelector('textarea').addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) form.requestSubmit(); });
	load(); setInterval(load, 10000);
})();
