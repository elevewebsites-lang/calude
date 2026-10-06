/* AllPrint · bolinha de mensagens (cliente ↔ equipe), no estilo do LDK: contador, aviso com prévia e som quando chega mensagem */
(function () {
	'use strict';
	var CFG = window.AP, hub = document.querySelector('[data-hub]');
	if (!CFG || !hub) return;
	var $ = function (s) { return hub.querySelector(s); };
	var team = hub.getAttribute('data-team') === '1';
	var hdr = { 'X-WP-Nonce': CFG.nonce };
	var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
	var api = function (path, opt) {
		opt = opt || {};
		opt.credentials = 'same-origin';
		opt.headers = Object.assign({}, hdr, opt.headers || {});
		return fetch(CFG.rest + path, opt).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Erro'); return j; }); });
	};

	var fab = $('[data-hub-fab]'), win = $('[data-hub-win]'), home = $('[data-hub-home]'), thread = $('[data-hub-thread]');
	var list = $('[data-hub-list]'), form = $('[data-hub-form]'), text = $('[data-hub-text]'), proj = $('[data-hub-project]');
	var back = $('[data-hub-back]'), title = $('[data-hub-title]'), sub = $('[data-hub-sub]');
	var state = { open: false, client: team ? 0 : +hub.getAttribute('data-client'), last: '', data: null, lastProjects: '' };
	// Memória separada para a equipe e para cada cliente (o mesmo navegador pode ter os dois logins).
	var KEY = 'ap-hub-' + (team ? 'equipe' : 'c' + state.client);
	var seen = 0; try { seen = +sessionStorage.getItem(KEY + '-seen') || 0; } catch (e) {}
	var firstVisit = !seen;
	var remember = function () { try { localStorage.setItem(KEY, JSON.stringify({ open: state.open, client: team ? state.client : 0 })); } catch (e) {} };

	/* ---------- contador, lista e aviso ---------- */
	function draw(d) {
		state.data = d;
		var n = $('[data-hub-n]');
		n.textContent = d.n > 99 ? '99+' : d.n; n.hidden = !d.n;
		fab.classList.toggle('has-new', d.n > 0);
		document.querySelectorAll('[data-bell] .bell-n').forEach(function (b) { b.textContent = d.n; b.hidden = !d.n; });
		if (team && home) {
			home.innerHTML = (d.convs || []).length ? d.convs.map(function (c) {
				return '<button type="button" class="hub-row' + (c.id === state.client ? ' is-on' : '') + '" data-hub-open="' + c.id + '" data-hub-name="' + esc(c.name) + '"><span class="hub-av">' + esc(c.ini) + '</span><span class="hub-rt"><strong>' + esc(c.name) + '</strong><small>' + esc(c.last) + ' · ' + esc(c.at) + '</small></span>' + (c.unread ? '<em class="hub-badge">' + c.unread + '</em>' : '') + '</button>';
			}).join('') : '<p class="muted small hub-pad">Nenhuma conversa ainda. Quando um parceiro mandar mensagem, aparece aqui.</p>';
		}
		// Mensagem nova do outro lado: aviso na tela (se a janela não está mostrando essa conversa).
		if (d.last && d.last.id > seen) {
			var showing = state.open && !thread.hidden && (!team || state.client === d.last.client) && !document.hidden;
			seen = d.last.id; try { sessionStorage.setItem(KEY + '-seen', seen); } catch (e) {}
			if (showing) loadThread();
			// Com a página aberta: aviso + som. Ao entrar no sistema com resposta não lida: só o aviso, uma vez por sessão.
			else if (ready || firstVisit) notify(d.last, ready);
		}
	}

	var ready = false, toastT = 0, baseTitle = document.title, blinkT = 0;
	function notify(m, sound) {
		var t = $('[data-hub-toast]');
		$('[data-hub-toast-av]').textContent = (m.name || '?').split(/\s+/).map(function (w) { return w[0]; }).slice(0, 2).join('').toUpperCase();
		$('[data-hub-toast-name]').textContent = m.name;
		$('[data-hub-toast-body]').textContent = m.body;
		t._client = m.client; t.hidden = false;
		clearTimeout(toastT); toastT = setTimeout(function () { t.hidden = true; }, 9000);
		if (sound === false) return;
		beep();
		clearInterval(blinkT);
		var i = 0;
		blinkT = setInterval(function () { document.title = (i++ % 2) ? baseTitle : '💬 Nova mensagem'; if (i > 12 || !document.hidden && i > 6) { clearInterval(blinkT); document.title = baseTitle; } }, 1000);
		if (document.hidden && window.Notification && Notification.permission === 'granted') {
			try { var nt = new Notification('💬 ' + m.name, { body: m.body, tag: 'ap-msg-' + m.id }); nt.onclick = function () { window.focus(); open(m.client); nt.close(); }; } catch (e) {}
		}
	}
	function beep() {
		try {
			var ac = new (window.AudioContext || window.webkitAudioContext)();
			[0, 0.16].forEach(function (t, k) {
				var o = ac.createOscillator(), g = ac.createGain();
				o.frequency.value = k ? 1175 : 880;
				g.gain.setValueAtTime(0.0001, ac.currentTime + t);
				g.gain.exponentialRampToValueAtTime(0.15, ac.currentTime + t + 0.02);
				g.gain.exponentialRampToValueAtTime(0.0001, ac.currentTime + t + 0.22);
				o.connect(g); g.connect(ac.destination); o.start(ac.currentTime + t); o.stop(ac.currentTime + t + 0.25);
			});
		} catch (e) {}
	}

	function poll() { return api('chat/hub').then(function (d) { draw(d); ready = true; }).catch(function () {}); }

	/* ---------- abrir, fechar, conversa ---------- */
	function setOpen(on) {
		state.open = on; win.hidden = !on;
		hub.classList.toggle('is-open', on);
		fab.setAttribute('aria-expanded', on ? 'true' : 'false');
		if (on) { $('[data-hub-toast]').hidden = true; if (!team || state.client) loadThread(); }
		remember();
	}
	function open(client, name) {
		if (team && client) {
			state.client = +client; state.last = ''; state.lastProjects = '';
			home.hidden = true; thread.hidden = false; back.hidden = false;
			title.textContent = name || (state.data && (state.data.convs || []).filter(function (c) { return c.id === +client; }).map(function (c) { return c.name; })[0]) || 'Conversa';
			sub.textContent = 'Parceiro';
			list.innerHTML = '<p class="muted small center">Carregando…</p>';
		}
		setOpen(true);
		if (window.matchMedia('(min-width: 721px)').matches) setTimeout(function () { text.focus(); }, 30);
	}
	function showHome() {
		state.client = 0; state.last = '';
		thread.hidden = true; home.hidden = false; back.hidden = true;
		title.textContent = 'Mensagens dos parceiros'; sub.textContent = 'Responda direto por aqui';
		remember(); poll();
	}
	function drawThread(j) {
		var html = (j.messages || []).map(function (m) {
			return '<div class="msg' + (m.mine ? ' is-mine' : '') + '"><div class="msg-b">' + (m.project ? '<em class="msg-ref">Pedido #' + m.project + '</em>' : '') + esc(m.body).replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>').replace(/\n/g, '<br>') + '</div><small>' + esc(m.who) + ' · ' + esc(m.at) + (m.mine && m.read ? ' · lida ✓' : '') + '</small></div>';
		}).join('') || '<p class="muted small center hub-pad">' + (team ? 'Nenhuma mensagem ainda.' : 'Mande uma mensagem ou observação. Se for sobre um pedido, escolha o pedido acima.') + '</p>';
		if (html !== state.last) {
			var atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 60 || !state.last;
			list.innerHTML = html; state.last = html;
			if (atBottom) list.scrollTop = list.scrollHeight;
		}
		var pj = JSON.stringify(j.projects || []);
		if (pj !== state.lastProjects) {
			var cur = proj.value;
			proj.innerHTML = '<option value="0">Assunto: geral</option>' + (j.projects || []).map(function (p) { return '<option value="' + p.id + '">' + esc(p.title) + '</option>'; }).join('');
			if ([].some.call(proj.options, function (o) { return o.value === cur; })) proj.value = cur;
			state.lastProjects = pj;
		}
		proj.hidden = !(j.projects || []).length;
	}
	function loadThread() {
		if (team && !state.client) return Promise.resolve();
		var c = state.client;
		return api('chat' + (team ? '?client=' + c : '')).then(function (j) {
			if (c !== state.client) return;
			drawThread(j);
			// Abrir a conversa marca como lida: atualiza o contador.
			if (state.data && state.data.n) poll();
		}).catch(function (e) { list.innerHTML = '<p class="muted small center">' + esc(e.message) + '</p>'; });
	}

	fab.addEventListener('click', function () {
		if (window.Notification && Notification.permission === 'default') { try { Notification.requestPermission(); } catch (e) {} }
		setOpen(!state.open);
	});
	$('[data-hub-close]').addEventListener('click', function () { setOpen(false); });
	back.addEventListener('click', showHome);
	$('[data-hub-toast]').addEventListener('click', function () { var c = this._client; this.hidden = true; open(c); });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && state.open) setOpen(false); });
	hub.addEventListener('click', function (e) { var o = e.target.closest('[data-hub-open]'); if (o) open(o.getAttribute('data-hub-open'), o.getAttribute('data-hub-name')); });

	text.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
	text.addEventListener('input', function () { text.style.height = 'auto'; text.style.height = Math.min(text.scrollHeight, 130) + 'px'; });
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var body = text.value.trim(); if (!body) return;
		var c = state.client, btn = form.querySelector('.hub-send');
		text.value = ''; text.style.height = ''; btn.disabled = true;
		api('chat', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ client: c, body: body, project: +proj.value || 0 }) })
			.then(function (j) { if (c === state.client) { state.last = ''; drawThread({ messages: j.messages, projects: JSON.parse(state.lastProjects || '[]') }); list.scrollTop = list.scrollHeight; } })
			.catch(function (err) { text.value = body; alert(err.message); })
			.then(function () { btn.disabled = false; text.focus(); });
	});

	/* ---------- ritmo: contador a cada 15 s; conversa aberta a cada 5 s ---------- */
	var saved = {}; try { saved = JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) {}
	// Pedido já escolhido pela tela (ex.: dentro do pedido #12): vem marcado no assunto.
	var m = location.pathname.match(/\/(?:pedido|projeto)\/(\d+)/);
	poll().then(function () {
		if (saved.open) { if (team && saved.client) open(saved.client); else setOpen(true); }
		if (m && !team) loadThread().then(function () { proj.value = m[1]; });
	});
	setInterval(poll, 15000);
	setInterval(function () { if (state.open && !thread.hidden && !document.hidden) loadThread(); }, 5000);
	document.addEventListener('visibilitychange', function () { if (!document.hidden) { poll(); document.title = baseTitle; } });
})();
