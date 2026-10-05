/* LDK · sininho da equipe (notificações) + chat interno */
(function () {
	'use strict';
	var CFG = window.LK;
	if (!CFG) return;
	var hdr = { 'X-WP-Nonce': CFG.nonce };
	var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
	var btn = document.querySelector('[data-nbell]');
	if (btn) {
		var drop = document.querySelector('[data-nbell-drop]'), list = document.querySelector('[data-nbell-list]'), badge = btn.querySelector('.bell-n');
		var load = function (read) {
			return fetch(CFG.rest + 'notifications' + (read ? '?read=1' : ''), { headers: hdr, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
				if (!j || !j.items) return;
				badge.textContent = j.n; badge.hidden = !j.n;
				list.innerHTML = j.items.length ? j.items.map(function (n) { return '<a class="nitem' + (n['new'] ? ' is-new' : '') + '" href="' + esc(n.url || '#') + '">' + esc(n.text) + '<small>' + esc(n.at) + '</small></a>'; }).join('') : '<p class="muted small">Nada novo.</p>';
			});
		};
		btn.addEventListener('click', function (e) { e.stopPropagation(); drop.hidden = !drop.hidden; if (!drop.hidden) load(true); });
		document.addEventListener('click', function (e) { if (!e.target.closest('.nbell')) drop.hidden = true; });
		setInterval(function () { load(false); }, 30000);
	}
	var box = document.querySelector('[data-teamchat]');
	if (!box) return;
	var ch = box.getAttribute('data-channel'), lst = box.querySelector('[data-chat-list]'), form = box.querySelector('form');
	var last = '';
	function draw(ms) {
		var html = ms.map(function (m) {
			var inner = m.audio ? '<audio controls preload="none" src="' + esc(m.audio) + '"></audio>' : esc(m.body).replace(/@([\wÀ-ÿ.-]+)/g, '<b>@$1</b>').replace(/\n/g, '<br>');
			return '<div class="msg' + (m.mine ? ' is-mine' : '') + '"><div class="msg-b' + (m.audio ? ' msg-b--audio' : '') + '">' + inner + '</div><small>' + esc(m.who) + ' · ' + m.at + '</small></div>';
		}).join('') || '<p class="muted small center">Comece a conversa. Use @nome para chamar alguém.</p>';
		// Não redesenha enquanto um áudio está tocando.
		if ([].some.call(lst.querySelectorAll('audio'), function (a) { return !a.paused; })) return;
		if (html !== last) { lst.innerHTML = html; lst.scrollTop = lst.scrollHeight; last = html; }
	}
	function get() { fetch(CFG.rest + 'team?channel=' + ch, { headers: hdr, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (j.messages) draw(j.messages); }); }
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var ta = form.querySelector('textarea'), body = ta.value.trim(); if (!body) return;
		fetch(CFG.rest + 'team', { method: 'POST', headers: Object.assign({ 'Content-Type': 'application/json' }, hdr), credentials: 'same-origin', body: JSON.stringify({ channel: ch, body: body }) }).then(function (r) { return r.json(); }).then(function (j) { if (j.messages) { draw(j.messages); ta.value = ''; } });
	});
	form.querySelector('textarea').addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
	get(); setInterval(get, 8000);
})();
