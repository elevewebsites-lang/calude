/* LDK · bolinha de chat da equipe: presença (online/ausente/offline), conversas (Geral e individuais), avisos e áudio */
(function () {
	'use strict';
	var CFG = window.LK, hub = document.querySelector('[data-hub]');
	if (!CFG || !hub) return;
	var $ = function (s) { return hub.querySelector(s); };
	var hdr = { 'X-WP-Nonce': CFG.nonce };
	var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
	var api = function (path, opt) {
		opt = opt || {};
		opt.credentials = 'same-origin';
		opt.headers = Object.assign({}, hdr, opt.headers || {});
		return fetch(CFG.rest + path, opt).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Erro'); return j; }); });
	};
	var post = function (path, data) { return api(path, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data || {}) }); };

	/* ---------- emojis animados (Noto, do Google; não pesam no plugin) ---------- */
	var EMOJIS = { '😂': '1f602', '😍': '1f60d', '🥰': '1f970', '😎': '1f60e', '🤩': '1f929', '😭': '1f62d', '😱': '1f631', '🤔': '1f914', '🙄': '1f644', '😴': '1f634', '🥳': '1f973', '👍': '1f44d', '👏': '1f44f', '🙏': '1f64f', '💪': '1f4aa', '🔥': '1f525', '❤️': '2764_fe0f', '💯': '1f4af', '🎉': '1f389', '🚀': '1f680', '✨': '2728', '👀': '1f440', '🤝': '1f91d', '☕': '2615' };
	var EMO_KEYS = Object.keys(EMOJIS).sort(function (a, b) { return b.length - a.length; });
	var EMO_RE = new RegExp(EMO_KEYS.map(function (k) { return k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('|'), 'g');
	var EMO_ONLY = new RegExp('^(?:' + EMO_KEYS.map(function (k) { return k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('|') + '|\\s){1,6}$');
	function emo(html) { return html.replace(EMO_RE, function (e) { return '<img class="emo" src="https://fonts.gstatic.com/s/e/notoemoji/latest/' + EMOJIS[e] + '/512.gif" alt="' + e + '" loading="lazy">'; }); }

	/* ---------- som: o navegador só libera depois de um clique; destrava no primeiro clique/tecla ---------- */
	var AC = null;
	function audio() { try { AC = AC || new (window.AudioContext || window.webkitAudioContext)(); if (AC.state === 'suspended') AC.resume(); } catch (e) {} return AC; }
	['pointerdown', 'keydown'].forEach(function (ev) { window.addEventListener(ev, function () { audio(); }, { once: true, passive: true }); });
	function tones(list, type) {
		var ac = audio(); if (!ac) return;
		list.forEach(function (n) {
			var o = ac.createOscillator(), g = ac.createGain();
			o.type = type || 'sine'; o.frequency.value = n[0];
			g.gain.setValueAtTime(0.0001, ac.currentTime + n[1]);
			g.gain.exponentialRampToValueAtTime(n[3] || 0.16, ac.currentTime + n[1] + 0.015);
			g.gain.exponentialRampToValueAtTime(0.0001, ac.currentTime + n[1] + n[2]);
			o.connect(g); g.connect(ac.destination); o.start(ac.currentTime + n[1]); o.stop(ac.currentTime + n[1] + n[2] + 0.02);
		});
	}
	// "Alguém entrou" (duas notas subindo, como o aviso do MSN).
	function chimeOnline() { tones([[784, 0, 0.18], [1175, 0.12, 0.35]], 'sine'); }

	/* ---------- "fulano está online" ---------- */
	var lastStatus = null;
	function onlineToasts(team) {
		var now = {};
		team.forEach(function (u) { now[u.id] = u.status; });
		if (lastStatus) {
			team.forEach(function (u) { if (u.status === 'online' && lastStatus[u.id] && lastStatus[u.id] !== 'online') toastOnline(u); });
		}
		lastStatus = now;
	}
	function toastOnline(u) {
		var box = document.querySelector('.msn-toasts') || (function () { var b = document.createElement('div'); b.className = 'msn-toasts'; b.setAttribute('aria-live', 'polite'); document.body.appendChild(b); return b; })();
		var t = document.createElement('button'); t.type = 'button'; t.className = 'msn-toast';
		t.innerHTML = '<span class="hub-av">' + esc(u.ini) + '<i class="dot dot--online"></i></span><span><strong>' + esc(u.name) + '</strong><small>acabou de ficar online</small></span>';
		t.addEventListener('click', function () { openChannel(u.ch, u.name); t.remove(); });
		box.appendChild(t); chimeOnline();
		setTimeout(function () { t.classList.add('is-out'); }, 5000); setTimeout(function () { t.remove(); }, 5600);
	}

	var fab = $('[data-hub-fab]'), win = $('[data-hub-win]'), rail = $('[data-hub-rail]');
	var home = $('[data-hub-home]'), thread = $('[data-hub-thread]'), list = $('[data-hub-list]');
	var title = $('[data-hub-title]'), back = $('[data-hub-back]'), form = $('[data-hub-form]'), text = $('[data-hub-text]');
	var statusSel = $('[data-hub-status]'), myDot = $('[data-hub-mydot]');
	var state = { open: false, ch: '', last: '', data: null, tab: 'conversas', max: false };
	var isCli = function (ch) { return /^cli\d+$/.test(ch); };
	var KEY = 'lk-hub';
	var remember = function () { try { localStorage.setItem(KEY, JSON.stringify({ open: state.open, ch: state.ch, max: state.max })); } catch (e) {} };

	/* ---------- presença: parado há 5 min (ou aba escondida há 5 min) = ausente ---------- */
	var lastAct = Date.now(), hiddenAt = 0, wasIdle = false;
	['mousemove', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (ev) {
		window.addEventListener(ev, function () { lastAct = Date.now(); if (wasIdle) { wasIdle = false; ping(); } }, { passive: true });
	});
	document.addEventListener('visibilitychange', function () { hiddenAt = document.hidden ? Date.now() : 0; if (!document.hidden) { lastAct = Date.now(); ping(); if (state.open && state.ch) loadThread(); } });
	var idle = function () { var now = Date.now(); return now - lastAct > 300000 || (hiddenAt && now - hiddenAt > 300000); };

	var dot = function (st) { return '<i class="dot dot--' + st + '"></i>'; };
	function ping(status) {
		var body = { idle: idle() };
		if (status) body.status = status;
		wasIdle = body.idle;
		return post('team/hub', body).then(draw).catch(function () {});
	}

	function draw(d) {
		state.data = d;
		ringCheck(d.ring || []);
		voiceDraw(d.voice || []);
		onlineToasts(d.team || []);
		if (d.nudges && d.nudges.length) nudgeShow(d.nudges[d.nudges.length - 1]);
		statusSel.value = d.me;
		myDot.className = 'dot dot--' + (d.me === 'online' ? 'online' : d.me);
		var n = $('[data-hub-n]');
		n.textContent = d.total > 99 ? '99+' : d.total; n.hidden = !d.total;
		fab.classList.toggle('has-new', d.total > 0);
		// Trilho à direita: quem é da equipe e como está agora.
		rail.innerHTML = d.team.slice(0, 10).map(function (u) {
			return '<button type="button" class="hub-rail-u" data-hub-open="' + esc(u.ch) + '" data-hub-name="' + esc(u.name) + '" title="' + esc(u.name + ' · ' + u.label) + '" aria-label="' + esc(u.name + ', ' + u.label) + '"><span class="hub-av">' + esc(u.ini) + '</span>' + dot(u.status) + (u.unread ? '<em>' + u.unread + '</em>' : '') + '</button>';
		}).join('');
		// Lista de conversas.
		$('[data-hub-team]').innerHTML = d.team.length ? d.team.map(function (u) {
			return '<button type="button" class="hub-row" data-hub-open="' + esc(u.ch) + '" data-hub-name="' + esc(u.name) + '"><span class="hub-av">' + esc(u.ini) + dot(u.status) + '</span><span class="hub-rt"><strong>' + esc(u.name) + '</strong><small>' + esc((u.role ? u.role + ' · ' : '') + u.label) + '</small></span>' + (u.unread ? '<em class="hub-badge">' + u.unread + '</em>' : '') + '</button>';
		}).join('') : '<p class="muted small hub-pad">Ninguém mais na equipe ainda. Cadastre em Equipe.</p>';
		var on = d.team.filter(function (u) { return u.status === 'online'; }).length;
		$('[data-hub-online]').textContent = d.team.length ? '· ' + on + ' online' : '';
		var note = $('[data-hub-note]'); if (document.activeElement !== note) note.value = d.note || '';
		var convs = d.convs || [];
		$('[data-hub-convs-wrap]').hidden = !convs.length;
		$('[data-hub-convs]').innerHTML = convs.map(function (c) {
			return '<button type="button" class="hub-row" data-hub-open="cli' + c.id + '" data-hub-name="' + esc(c.name) + '"><span class="hub-av hub-av--cli">' + esc(c.ini) + '</span><span class="hub-rt"><strong>' + esc(c.name) + '</strong><small>' + esc(c.last) + ' · ' + esc(c.at) + '</small></span>' + (c.unread ? '<em class="hub-badge">' + c.unread + '</em>' : '') + '</button>';
		}).join('');
		var g = $('[data-hub-geral]'); g.textContent = d.geral; g.hidden = !d.geral;
		markActive();
		var av = d.clients + d.notes, an = $('[data-hub-avisos-n]'); an.textContent = av; an.hidden = !av;
		var cm = $('[data-hub-clientmsg]'); cm.hidden = !d.clients;
		$('[data-hub-clientmsg-n]').textContent = d.clients;
		$('[data-hub-clientmsg-t]').textContent = d.clients === 1 ? '1 mensagem nova' : d.clients + ' mensagens novas';
	}

	/* ---------- abrir, fechar, navegar ---------- */
	function setOpen(open) {
		state.open = open;
		win.hidden = !open;
		hub.classList.toggle('is-open', open);
		fab.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (open && !state.ch) { if (wide()) { openChannel('geral', '# Geral'); return; } showHome(); }
		remember();
	}
	function showHome() {
		state.ch = ''; state.last = '';
		thread.hidden = true; home.hidden = false; back.hidden = true; callBtn.hidden = true;
		markActive();
		title.textContent = 'Equipe';
		remember();
		if (state.tab === 'avisos') loadNotes();
	}
	function openChannel(ch, name) {
		state.ch = ch; state.last = '';
		home.hidden = !wide(); thread.hidden = false; back.hidden = wide();
		markActive();
		title.textContent = name || (ch === 'geral' ? '# Geral' : 'Conversa');
		list.innerHTML = '<p class="muted small center">Carregando…</p>';
		text.placeholder = ch === 'geral' ? 'Mensagem para a equipe…' : isCli(ch) ? 'Responder ao cliente…' : 'Mensagem para ' + (name ? name.split(' ')[0] : 'a pessoa') + '…';
		mic.hidden = !canRec || isCli(ch);
		callBtn.hidden = !(ch === 'geral' || ch.indexOf('dm') === 0);
		thread.classList.toggle('is-cli', isCli(ch));
		setOpen(true);
		loadThread().then(function () { ping(); });
		if (window.matchMedia('(min-width: 721px)').matches) text.focus();
	}
	// Tela cheia no computador: lista e conversa lado a lado.
	function wide() { return state.max && window.matchMedia('(min-width: 721px)').matches; }
	function markActive() { hub.querySelectorAll('[data-hub-home] [data-hub-open]').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-hub-open') === state.ch); }); }
	function fmt(sec) { sec = Math.max(0, Math.round(sec || 0)); return Math.floor(sec / 60) + ':' + ('0' + (sec % 60)).slice(-2); }
	function drawThread(ms) {
		var html = ms.map(function (m) {
			var wl = winkLabel(m);
			var inner = wl ? '<button type="button" class="wink-btn" data-wink-replay="' + String(m.body).replace(/[^a-z]/g, '').replace('wink', '') + '">' + wl + '</button>' : m.audio
				? '<audio controls preload="none" src="' + esc(m.audio) + '"></audio>' + (m.sec ? '<span class="hub-dur">' + fmt(m.sec) + '</span>' : '')
				: emo(esc(m.body).replace(/@([\wÀ-ÿ.-]+)/g, '<b>@$1</b>').replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>').replace(/\n/g, '<br>'));
			var big = !m.audio && EMO_ONLY.test(String(m.body || '').trim());
			return '<div class="msg' + (m.mine ? ' is-mine' : '') + '"><div class="msg-b' + (m.audio ? ' msg-b--audio' : '') + (big ? ' msg-b--emo' : '') + '">' + inner + '</div><small>' + (state.ch.indexOf('dm') === 0 && !m.mine ? '' : esc(m.who) + ' · ') + esc(m.at) + '</small></div>';
		}).join('') || '<p class="muted small center">Nenhuma mensagem ainda. Diga oi 👋</p>';
		// Não redesenha (e não interrompe um áudio tocando) se nada mudou.
		if (html === state.last) return;
		if ([].some.call(list.querySelectorAll('audio'), function (a) { return !a.paused; })) return;
		ms.forEach(function (m) {
			var k = String(m.body || '').match(winkRe);
			if (k && !m.mine && m.id && !played[m.id] && state.lastCh === state.ch) {
				played[m.id] = 1; try { sessionStorage.setItem('lk-winks', JSON.stringify(played)); } catch (e) {}
				winkPlay(k[1]);
			} else if (k && m.id) { played[m.id] = 1; }
		});
		var atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 60;
		list.innerHTML = html; state.last = html;
		if (atBottom || !state.lastCh || state.lastCh !== state.ch) list.scrollTop = list.scrollHeight;
		state.lastCh = state.ch;
	}
	function loadThread() {
		if (!state.ch) return Promise.resolve();
		var ch = state.ch;
		var path = isCli(ch) ? 'chat?client=' + ch.slice(3) : 'team?channel=' + encodeURIComponent(ch);
		return api(path).then(function (j) { if (ch === state.ch && j.messages) drawThread(j.messages); }).catch(function (e) { list.innerHTML = '<p class="muted small center">' + esc(e.message) + '</p>'; });
	}
	function loadNotes() {
		var box = $('[data-hub-notes]');
		api('notifications?read=1').then(function (j) {
			box.innerHTML = j.items && j.items.length ? j.items.map(function (n) { return '<a class="hub-note' + (n['new'] ? ' is-new' : '') + '" href="' + esc(n.url || '#') + '">' + esc(n.text) + '<small>' + esc(n.at) + '</small></a>'; }).join('') : '<p class="muted small hub-pad">Nenhum aviso por aqui.</p>';
			var bell = document.querySelector('[data-nbell] .bell-n'); if (bell) { bell.textContent = j.n; bell.hidden = !j.n; }
			ping();
		}).catch(function () {});
	}

	fab.addEventListener('click', function () {
		if (window.Notification && Notification.permission === 'default') { try { Notification.requestPermission(); } catch (e) {} }
		setOpen(!state.open); if (state.open && state.ch) loadThread(); });
	$('[data-hub-close]').addEventListener('click', function () { setOpen(false); });
	back.addEventListener('click', showHome);
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && state.open && !rec) setOpen(false); });
	hub.addEventListener('click', function (e) {
		var o = e.target.closest('[data-hub-open]');
		if (o) { openChannel(o.getAttribute('data-hub-open'), o.getAttribute('data-hub-name') || (o.getAttribute('data-hub-open') === 'geral' ? '# Geral' : '')); return; }
		var t = e.target.closest('[data-hub-tab]');
		if (t) {
			state.tab = t.getAttribute('data-hub-tab');
			hub.querySelectorAll('[data-hub-tab]').forEach(function (b) { b.classList.toggle('is-on', b === t); });
			hub.querySelectorAll('[data-hub-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-hub-pane') !== state.tab; });
			if (state.tab === 'avisos') loadNotes();
		}
	});
	statusSel.addEventListener('change', function () { ping(statusSel.value); });
	/* Recados rápidos: "Fui almoçar", "Volto já"… marcam como ausente com o recado; "Voltei" limpa tudo. */
	var quick = $('[data-hub-quick]');
	if (quick) {
		var backBtn = quick.querySelector('[data-q-back]');
		var hh = function () { var d = new Date(); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); };
		quick.addEventListener('click', function (e) {
			var b = e.target.closest('button'); if (!b) return;
			if (b.hasAttribute('data-q-back')) {
				post('team/hub', { idle: false, status: 'online', note: '' }).then(draw).catch(function () {});
				return;
			}
			var n = b.getAttribute('data-q-note'); if (!n) return;
			post('team/hub', { idle: false, status: 'ausente', note: (n + ' (desde ' + hh() + ')').slice(0, 60) }).then(draw).catch(function () {});
		});
		var sync = function () { if (backBtn) backBtn.hidden = !(state.data && state.data.me && state.data.me !== 'online'); };
		var _draw = draw;
		draw = function (d) { _draw(d); sync(); };
	}
	var noteIn = $('[data-hub-note]'), noteT = 0;
	var saveNote = function () { clearTimeout(noteT); post('team/hub', { idle: false, note: noteIn.value.trim() }).then(draw).catch(function () {}); };
	noteIn.addEventListener('input', function () { clearTimeout(noteT); noteT = setTimeout(saveNote, 1200); });
	noteIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); saveNote(); noteIn.blur(); } });
	var maxBtn = $('[data-hub-max]');
	function setMax(on) {
		state.max = on; hub.classList.toggle('is-max', on);
		maxBtn.querySelector('[data-hub-max-on]').hidden = on; maxBtn.querySelector('[data-hub-max-off]').hidden = !on;
		maxBtn.title = on ? 'Voltar para a janela pequena' : 'Tela cheia'; maxBtn.setAttribute('aria-label', maxBtn.title);
		if (state.ch) { home.hidden = !wide(); back.hidden = wide(); }
		else if (on && state.open && wide()) openChannel('geral', '# Geral');
		remember();
	}
	maxBtn.addEventListener('click', function () { setMax(!state.max); });

	/* ---------- ligar ---------- */
	var callBtn = $('[data-hub-call]');
	callBtn.addEventListener('click', function () {
		if (!state.ch) return;
		var ch = state.ch, group = ch === 'geral';
		if (group && !confirm('Ligar para toda a equipe?')) return;
		// Abre a aba já no clique (senão o navegador bloqueia) e depois coloca o endereço da sala.
		var w = window.open('about:blank', '_blank');
		if (w) w.document.write('<p style="font:16px sans-serif;padding:24px">Criando a chamada…</p>');
		callBtn.disabled = true;
		post('team/call', { channel: ch }).then(function (j) {
			if (w) w.location.href = j.url; else window.open(j.url, '_blank');
			if (ch === state.ch && j.messages) drawThread(j.messages);
		}).catch(function (err) { if (w) w.close(); alert(err.message); }).then(function () { callBtn.disabled = false; });
	});

	var ringBox = $('[data-hub-ring]'), ringing = null, ringTone = null, baseTitle = document.title, seenRing = {};
	function tone(on) {
		clearInterval(ringTone); ringTone = null;
		if (!on) { document.title = baseTitle; return; }
		var ac; try { ac = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) {}
		var beep = function () {
			if (navigator.vibrate) navigator.vibrate([300, 150, 300]);
			document.title = document.title === baseTitle ? '📞 Ligação…' : baseTitle;
			if (!ac) return;
			[0, 0.35].forEach(function (t) {
				var o = ac.createOscillator(), g = ac.createGain();
				o.frequency.value = 880; g.gain.setValueAtTime(0.0001, ac.currentTime + t);
				g.gain.exponentialRampToValueAtTime(0.2, ac.currentTime + t + 0.02);
				g.gain.exponentialRampToValueAtTime(0.0001, ac.currentTime + t + 0.28);
				o.connect(g); g.connect(ac.destination); o.start(ac.currentTime + t); o.stop(ac.currentTime + t + 0.3);
			});
		};
		beep(); ringTone = setInterval(beep, 1500);
	}
	function ringCheck(list) {
		var c = list.filter(function (x) { return !seenRing[x.id]; })[0];
		if (!c) { if (ringing && !list.some(function (x) { return x.id === ringing.id; })) stopRing(); return; }
		if (ringing && ringing.id === c.id) return;
		ringing = c;
		$('[data-hub-ring-av]').textContent = c.ini;
		$('[data-hub-ring-name]').textContent = c.name;
		$('[data-hub-ring-t]').textContent = c.group ? 'está chamando a equipe…' : 'está te ligando…';
		ringBox.hidden = false; tone(true);
		clearTimeout(ringing.t); ringing.t = setTimeout(stopRing, 60000);
		// Painel em outra aba: aviso do sistema (se a pessoa deixou).
		if (document.hidden && window.Notification && Notification.permission === 'granted') {
			try { var nt = new Notification('📞 ' + c.name, { body: c.group ? 'Chamando a equipe. Clique para atender.' : 'Está te ligando. Clique para atender.', tag: 'lk-call-' + c.id, requireInteraction: true }); nt.onclick = function () { window.focus(); nt.close(); }; } catch (e) {}
		}
	}
	function stopRing() { if (ringing) seenRing[ringing.id] = 1; ringing = null; ringBox.hidden = true; tone(false); }
	function answer(action) {
		if (!ringing) return;
		var id = ringing.id, w = action === 'atender' ? window.open('about:blank', '_blank') : null;
		stopRing();
		post('team/call/answer', { id: id, action: action }).then(function (j) {
			if (action !== 'atender') return;
			if (w) w.location.href = j.url; else window.open(j.url, '_blank');
		}).catch(function (err) { if (w) w.close(); alert(err.message); });
	}
	$('[data-hub-ring-yes]').addEventListener('click', function () { answer('atender'); });
	$('[data-hub-ring-no]').addEventListener('click', function () { answer('recusar'); });

	/* ---------- emojis no chat ---------- */
	var emoBtn = $('[data-hub-emo]'), emoBox = $('[data-hub-emo-box]');
	if (emoBtn && emoBox) {
		emoBox.innerHTML = Object.keys(EMOJIS).map(function (e) { return '<button type="button" data-emo="' + e + '" title="' + e + '"><img src="https://fonts.gstatic.com/s/e/notoemoji/latest/' + EMOJIS[e] + '/512.webp" alt="' + e + '" loading="lazy" width="28" height="28"></button>'; }).join('');
		emoBtn.addEventListener('click', function () { emoBox.hidden = !emoBox.hidden; });
		emoBox.addEventListener('click', function (e) {
			var b = e.target.closest('[data-emo]'); if (!b) return;
			var p = text.selectionStart || text.value.length;
			text.value = text.value.slice(0, p) + b.dataset.emo + text.value.slice(p); text.focus(); emoBox.hidden = true;
		});
	}

	/* ---------- texto ---------- */
	text.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
	text.addEventListener('input', function () { text.style.height = 'auto'; text.style.height = Math.min(text.scrollHeight, 120) + 'px'; });
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		if (rec) { stopRec(true); return; }
		var body = text.value.trim(); if (!body || !state.ch) return;
		var ch = state.ch; text.value = ''; text.style.height = '';
		(isCli(ch) ? post('chat', { client: +ch.slice(3), body: body }) : post('team', { channel: ch, body: body })).then(function (j) { if (ch === state.ch && j.messages) drawThread(j.messages); }).catch(function (err) { text.value = body; alert(err.message); });
	});


	/* ---------- winks (animações de tela cheia, como no MSN) ---------- */
	var WINKS = {
		confete:  { label: 'confete', chars: ['🎉', '🎊', '✨', '⭐'], mode: 'fall', color: ['#fc5521', '#14E9EC', '#ffd34d', '#7c3aed', '#2ee59d'] },
		coracao:  { label: 'uma chuva de corações', chars: ['💖', '💗', '💕', '❤️'], mode: 'rise' },
		foguete:  { label: 'um foguete', chars: ['🚀'], mode: 'rocket' },
		aplausos: { label: 'aplausos', chars: ['👏', '👏', '✨'], mode: 'pop' }
	};
	var winkRe = /^::wink:(confete|coracao|foguete|aplausos)::$/;
	var played = {};
	try { played = JSON.parse(sessionStorage.getItem('lk-winks') || '{}'); } catch (e) {}
	function winkPlay(kind) {
		var w = WINKS[kind]; if (!w) return;
		var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
		var layer = document.createElement('div'); layer.className = 'lk-wink'; layer.setAttribute('aria-hidden', 'true');
		document.body.appendChild(layer);
		var W = window.innerWidth, H = window.innerHeight, n = reduce ? 6 : (w.mode === 'rocket' ? 1 : 46);
		for (var i = 0; i < n; i++) {
			var s = document.createElement('span'), ch = w.chars[Math.floor(Math.random() * w.chars.length)];
			s.textContent = ch; s.className = 'lk-wink-i lk-wink-i--' + w.mode;
			var size = w.mode === 'rocket' ? 90 : 20 + Math.random() * 28;
			s.style.fontSize = size + 'px';
			s.style.left = (w.mode === 'rocket' ? 8 + Math.random() * 12 : Math.random() * 100) + 'vw';
			s.style.animationDuration = (w.mode === 'rocket' ? 2.6 : 2.2 + Math.random() * 2.2) + 's';
			s.style.animationDelay = (w.mode === 'rocket' ? 0 : Math.random() * 0.9) + 's';
			if (w.mode === 'pop') { s.style.top = (10 + Math.random() * 70) + 'vh'; }
			if (w.mode === 'fall' && w.color && Math.random() < 0.5) { s.textContent = ''; s.style.width = '10px'; s.style.height = '16px'; s.style.background = w.color[Math.floor(Math.random() * w.color.length)]; s.style.borderRadius = '2px'; }
			layer.appendChild(s);
		}
		var banner = null;
		setTimeout(function () { layer.remove(); }, 5200);
	}
	function winkLabel(m) {
		var k = String(m.body || '').match(winkRe); if (!k) return null;
		return '<span class="wink-msg">✨ ' + (m.mine ? 'Você mandou ' : esc(m.who) + ' mandou ') + WINKS[k[1]].label + ' <em>(toque para ver de novo)</em></span>';
	}
	hub.addEventListener('click', function (e) {
		var rp = e.target.closest('[data-wink-replay]'); if (rp) { winkPlay(rp.getAttribute('data-wink-replay')); return; }
		var tgl = e.target.closest('[data-hub-wink]');
		var box = $('[data-hub-wink-box]');
		if (tgl) { box.hidden = !box.hidden; var eb = $('[data-hub-emo-box]'); if (eb) eb.hidden = true; return; }
		var pick = e.target.closest('[data-wink]');
		if (pick && state.ch && !isCli(state.ch)) {
			var kind = pick.getAttribute('data-wink'); box.hidden = true; winkPlay(kind);
			post('team', { channel: state.ch, body: '::wink:' + kind + '::' }).then(function (j) { if (j.messages) drawThread(j.messages); }).catch(function () {});
		}
	});

	/* ---------- áudio ---------- */
	var mic = $('[data-hub-mic]'), recBar = $('[data-hub-rec]'), recT = $('[data-hub-rec-t]');
	var rec = null, chunks = [], recStart = 0, recTimer = 0, recStream = null, sendAfter = false;
	var canRec = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder);
	if (!canRec) mic.hidden = true;
	function pickType() {
		var opts = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
		for (var i = 0; i < opts.length; i++) { if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(opts[i])) return opts[i]; }
		return '';
	}
	function uiRec(on) {
		recBar.hidden = !on; text.hidden = on; mic.classList.toggle('is-rec', on);
		mic.setAttribute('aria-label', on ? 'Parar e enviar' : 'Gravar áudio'); mic.title = on ? 'Parar e enviar' : 'Gravar áudio';
	}
	function startRec() {
		navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
			recStream = stream; chunks = []; sendAfter = false;
			var type = pickType();
			rec = type ? new MediaRecorder(stream, { mimeType: type }) : new MediaRecorder(stream);
			rec.ondataavailable = function (ev) { if (ev.data && ev.data.size) chunks.push(ev.data); };
			rec.onstop = function () {
				var sec = (Date.now() - recStart) / 1000, mime = (rec && rec.mimeType) || type || 'audio/webm', ch = state.ch;
				recStream.getTracks().forEach(function (t) { t.stop(); });
				clearInterval(recTimer); rec = null; uiRec(false);
				if (!sendAfter || !chunks.length || sec < 0.8) return;
				var blob = new Blob(chunks, { type: mime.split(';')[0] });
				var ext = /mp4/.test(mime) ? 'm4a' : /ogg/.test(mime) ? 'ogg' : 'webm';
				var fd = new FormData(); fd.append('audio', blob, 'audio.' + ext); fd.append('channel', ch); fd.append('sec', Math.round(sec));
				list.insertAdjacentHTML('beforeend', '<p class="muted small center" data-hub-sending>Enviando áudio…</p>'); list.scrollTop = list.scrollHeight;
				api('team/audio', { method: 'POST', body: fd }).then(function (j) { if (ch === state.ch && j.messages) drawThread(j.messages); }).catch(function (err) {
					var s = list.querySelector('[data-hub-sending]'); if (s) s.remove();
					alert(err.message);
				});
			};
			rec.start(1000); recStart = Date.now(); uiRec(true); recT.textContent = '0:00';
			recTimer = setInterval(function () {
				var sec = (Date.now() - recStart) / 1000; recT.textContent = fmt(sec);
				if (sec >= 600) stopRec(true); // até 10 minutos
			}, 250);
		}).catch(function () { alert('Não deu para usar o microfone. Libere o acesso ao microfone para este site no navegador (ícone do cadeado ao lado do endereço).'); });
	}
	function stopRec(send) { if (!rec) return; sendAfter = send; if (rec.state !== 'inactive') rec.stop(); }
	mic.addEventListener('click', function () { if (rec) stopRec(true); else startRec(); });
	$('[data-hub-rec-cancel]').addEventListener('click', function () { stopRec(false); });

	/* ---------- ritmo: sinal a cada 15 s; com a conversa aberta, busca novas mensagens a cada 5 s ---------- */
	var saved = {}; try { saved = JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) {}
	if (saved.max) setMax(true);
	ping().then(function () {
		// No modo foco a janela começa fechada (só a bolinha).
		if (saved.open && !document.body.classList.contains('lk-focus')) {
			if (saved.ch) {
				var all = state.data ? state.data.team.concat((state.data.convs || []).map(function (c) { return { ch: 'cli' + c.id, name: c.name }; })) : [];
				var u = all.filter(function (x) { return x.ch === saved.ch; })[0];
				if (u || saved.ch === 'geral') openChannel(saved.ch, u ? u.name : '# Geral'); else setOpen(true);
			} else setOpen(true);
		}
	});
	/* ---------- sala de voz (resumo na bolinha) ---------- */
	function voiceDraw(list) {
		var box = $('[data-hub-voice]');
		if (!box) return;
		var me = parseInt(hub.dataset.me, 10), inRoom = list.some(function (u) { return u.id === me; });
		var talking = list.filter(function (u) { return u.mic; });
		$('[data-hub-voice-t]').textContent = !list.length ? 'ninguém na sala' : list.length + (list.length === 1 ? ' pessoa' : ' pessoas') + (talking.length ? ' · 🎙 ' + talking.map(function (u) { return u.name.split(' ')[0]; }).join(', ') : '');
		$('[data-hub-voice-avs]').innerHTML = list.slice(0, 5).map(function (u) { return '<span class="hub-av' + (u.mic ? ' is-mic' : '') + '" title="' + esc(u.name) + '">' + esc(u.ini) + '</span>'; }).join('');
		var go = $('[data-hub-voice-go]'); go.textContent = inRoom ? 'Abrir' : 'Entrar';
		box.classList.toggle('is-live', list.length > 0);
		fab.classList.toggle('has-voice', list.length > 0);
	}
	var voiceGo = $('[data-hub-voice-go]');
	if (voiceGo) voiceGo.addEventListener('click', function (e) {
		e.preventDefault();
		var w = window.open(voiceGo.href, 'lk-sala', 'width=440,height=680,menubar=no,toolbar=no');
		if (!w) location.href = voiceGo.href; else w.focus();
	});

	/* ---------- chamar atenção (estilo MSN) ---------- */
	var nudgeForm = $('[data-hub-nudge]');
	if (nudgeForm) nudgeForm.addEventListener('submit', function (e) {
		e.preventDefault();
		var inp = $('[data-hub-nudge-msg]'), btn = nudgeForm.querySelector('button');
		btn.disabled = true;
		post('team/nudge', { msg: inp.value }).then(function () {
			inp.value = ''; shake(); btn.textContent = '⚡ Enviado!';
			setTimeout(function () { btn.textContent = '⚡ Chamar atenção'; btn.disabled = false; }, 2500);
		}).catch(function (err) { alert(err.message); btn.disabled = false; });
	});
	var nudgeBox = $('[data-nudge]');
	function shake() {
		document.body.classList.remove('lk-shake'); void document.body.offsetWidth; document.body.classList.add('lk-shake');
		setTimeout(function () { document.body.classList.remove('lk-shake'); }, 900);
		if (navigator.vibrate) navigator.vibrate([120, 60, 120, 60, 200]);
	}
	function buzz() {
		// O "tremer" do MSN: zumbido grave vibrando + dois toques.
		tones([[140, 0, 0.5, 0.22], [180, 0.05, 0.45, 0.18], [660, 0.55, 0.12], [880, 0.7, 0.18]], 'sawtooth');
	}
	function nudgeShow(n) {
		$('[data-nudge-av]').textContent = n.ini;
		$('[data-nudge-name]').textContent = n.name;
		var m = $('[data-nudge-msg]'); m.textContent = n.msg || ''; m.hidden = !n.msg;
		nudgeBox.hidden = false; shake(); buzz();
		setTimeout(shake, 1100);
		if (document.hidden && window.Notification && Notification.permission === 'granted') {
			try { new Notification('⚡ ' + n.name + ' chamou sua atenção!', { body: n.msg || 'Abra o painel.', tag: 'lk-nudge-' + n.id }); } catch (e) {}
		}
	}
	if (nudgeBox) $('[data-nudge-ok]').addEventListener('click', function () { nudgeBox.hidden = true; });

	// Sinal a cada 15 s: a ligação chega em até ~15 s (em aba escondida o navegador pode atrasar até ~1 min).
	var pingT = 0;
	(function loop() { clearTimeout(pingT); pingT = setTimeout(function () { ping().then(loop, loop); }, 10000); })();
	setInterval(function () { if (state.open && state.ch && !document.hidden) loadThread(); }, 5000);
})();
