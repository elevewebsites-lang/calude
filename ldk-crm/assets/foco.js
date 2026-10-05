/* LDK · Modo foco (timer Pomodoro). O estado fica no aparelho: recarregar a página não perde o tempo. */
(function () {
	'use strict';

	var C = window.LK_FOCUS || {};
	var $ = function (s, r) { return (r || document).querySelector(s); };
	var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
	var free = !C.focus;
	var KEY = 'lk-focus-' + C.key;
	var MIN = 60000;

	var el = {
		phase: $('[data-fz-phase]'), time: $('[data-fz-time]'), ring: $('[data-fz-ring]'), meta: $('[data-fz-meta]'),
		toggle: $('[data-fz-toggle]'), skip: $('[data-fz-skip]'), reset: $('[data-fz-reset]'),
		sound: $('[data-fz-sound]'), full: $('[data-fz-full]'), exit: $('[data-fz-exit]'),
		theme: $('[data-fz-theme-btn]'), chat: $('[data-fz-chat-btn]'), now: $('[data-fz-now]'),
		count: $('[data-fz-count]'), progress: $('[data-fz-progress]')
	};

	var fresh = function () {
		return { phase: 'foco', running: false, endAt: 0, remaining: C.focus * MIN, elapsed: 0, runStart: 0, segStart: 0, count: 0, current: 0 };
	};
	var S = fresh();
	try { var saved = JSON.parse(localStorage.getItem(KEY) || 'null'); if (saved) S = saved; } catch (e) {}
	var today = C.today || 0;
	var soundOn = true;
	try { soundOn = localStorage.getItem('lk-focus-sound') !== '0'; } catch (e) {}

	var save = function () { if (leaving) return; try { localStorage.setItem(KEY, JSON.stringify(S)); } catch (e) {} };
	var pad = function (n) { return (n < 10 ? '0' : '') + n; };
	var fmt = function (ms) { var s = Math.max(0, Math.round(ms / 1000)); var h = Math.floor(s / 3600); var m = Math.floor((s % 3600) / 60); return (h ? h + ':' + pad(m) : pad(m)) + ':' + pad(s % 60); };
	var dur = function (sec) { var m = Math.round(sec / 60); return m < 60 ? m + ' min' : Math.floor(m / 60) + 'h' + (m % 60 ? ' ' + pad(m % 60) : ''); };
	var phaseLen = function () { return (S.phase === 'foco' ? C.focus : (S.phase === 'longa' ? C.long : C.short)) * MIN; };

	/* ---------- Registro do tempo focado ---------- */

	var item = function () { return (C.items || [])[S.current] || null; };
	var post = function (path, body, keepalive) {
		return fetch(C.rest + path, { method: 'POST', credentials: 'same-origin', keepalive: !!keepalive, headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': C.nonce }, body: JSON.stringify(body || {}) })
			.then(function (r) { return r.json(); });
	};
	// Manda o tempo focado desde o último registro, atribuído ao item atual.
	var leaving = false;
	var flush = function (keepalive) {
		if (leaving) return null;
		if (S.phase !== 'foco' || !S.running || !S.segStart) return null;
		var sec = Math.round((Date.now() - S.segStart) / 1000);
		S.segStart = Date.now();
		save();
		if (sec < 30) return null;
		today += sec;
		var it = item();
		return post('focus/log', { seconds: sec, task_id: it && it.type === 'task' ? it.id : 0, project_id: it ? it.project : 0 }, keepalive).catch(function () {});
	};

	/* ---------- Som, aviso e tela ligada ---------- */

	var beep = function () {
		if (!soundOn) return;
		try {
			var ctx = new (window.AudioContext || window.webkitAudioContext)();
			[0, 0.28, 0.56].forEach(function (t, i) {
				var o = ctx.createOscillator(), g = ctx.createGain();
				o.type = 'sine'; o.frequency.value = i === 2 ? 880 : 660;
				g.gain.setValueAtTime(0.0001, ctx.currentTime + t);
				g.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + t + 0.02);
				g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + t + 0.24);
				o.connect(g); g.connect(ctx.destination); o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.26);
			});
		} catch (e) {}
	};
	var notify = function (text) {
		if (!document.hidden || !('Notification' in window) || Notification.permission !== 'granted') return;
		try { new Notification('Modo foco', { body: text }); } catch (e) {}
	};
	var wake = null;
	var keepAwake = function (on) {
		if (!('wakeLock' in navigator)) return;
		if (on && !wake) navigator.wakeLock.request('screen').then(function (w) { wake = w; w.addEventListener('release', function () { wake = null; }); }).catch(function () {});
		if (!on && wake) { wake.release(); wake = null; }
	};

	/* ---------- Timer ---------- */

	var start = function () {
		if (S.running) return;
		S.running = true;
		if (free) { S.runStart = Date.now(); } else { S.endAt = Date.now() + S.remaining; }
		if (S.phase === 'foco') S.segStart = Date.now();
		if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
		keepAwake(true);
		save(); render();
	};
	var pause = function () {
		if (!S.running) return;
		flush();
		if (free) { S.elapsed += Date.now() - S.runStart; } else { S.remaining = Math.max(0, S.endAt - Date.now()); }
		S.running = false; S.segStart = 0;
		keepAwake(false);
		save(); render();
	};
	var nextPhase = function (auto) {
		if (S.phase === 'foco') {
			flush();
			S.count++;
			S.phase = S.count % 4 === 0 ? 'longa' : 'curta';
			beep(); notify('Bloco de foco concluído. Hora da pausa.');
			S.remaining = phaseLen();
			S.running = false; S.segStart = 0;
			if (auto) start(); // a pausa começa sozinha
		} else {
			S.phase = 'foco';
			beep(); notify('Pausa encerrada. Bora voltar?');
			S.remaining = phaseLen();
			S.running = false; S.segStart = 0;
			keepAwake(false);
		}
		save(); render();
	};
	var reset = function () {
		flush();
		var keep = { count: S.count, current: S.current };
		S = fresh(); S.count = keep.count; S.current = keep.current;
		keepAwake(false);
		save(); render();
	};

	var render = function () {
		var left, pct;
		if (free) {
			var el2 = S.elapsed + (S.running ? Date.now() - S.runStart : 0);
			left = el2; pct = ((el2 / MIN) % 60) / 60 * 100;
		} else {
			left = S.running ? S.endAt - Date.now() : S.remaining;
			pct = (1 - left / phaseLen()) * 100;
			if (S.running && left <= 0) { nextPhase(true); return; }
		}
		var names = { foco: 'Foco', curta: 'Pausa', longa: 'Pausa longa' };
		el.time.textContent = fmt(left);
		el.ring.style.setProperty('--p', Math.max(0, Math.min(100, pct)).toFixed(2));
		var started = free ? S.elapsed > 0 : left < phaseLen() - 1000;
		el.phase.textContent = names[S.phase] + (!S.running && started ? ' · pausado' : '');
		document.body.classList.toggle('is-break', S.phase !== 'foco');
		document.body.classList.toggle('is-running', S.running);
		el.toggle.textContent = S.running ? 'Pausar' : (S.phase === 'foco' ? (started ? 'Continuar' : 'Começar') : 'Começar pausa');
		var live = S.running && S.phase === 'foco' && S.segStart ? Math.round((Date.now() - S.segStart) / 1000) : 0;
		el.meta.textContent = (free ? 'Cronômetro livre' : 'Bloco ' + (S.count + (S.phase === 'foco' ? 1 : 0))) + ' · ' + dur(today + live) + ' focados hoje';
		document.title = fmt(left) + ' · ' + names[S.phase];
		$$('[data-fz-item]').forEach(function (n) { n.classList.toggle('is-current', +n.getAttribute('data-fz-item') === S.current); });
	};

	/* ---------- Tarefas ---------- */

	$$('[data-fz-done]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var id = btn.getAttribute('data-fz-done');
			btn.disabled = true;
			post('task/' + id + '/toggle').then(function (d) {
				btn.disabled = false;
				btn.classList.toggle('is-done', !!d.done);
				var row = btn.closest('li'); if (row) row.classList.toggle('is-done', !!d.done);
				if (btn.parentNode.classList.contains('fz-item-head')) { btn.closest('[data-fz-item]').classList.toggle('is-done', !!d.done); progress(); }
				// Terminou a tarefa principal: passa para o próximo item que ainda não foi feito.
				var box = btn.closest('[data-fz-item]');
				if (d.done && box && btn.parentNode.classList.contains('fz-item-head') && +box.getAttribute('data-fz-item') === S.current) {
					var next = $$('[data-fz-item]').filter(function (n) { var t = $('.fz-item-head .fz-tick', n); return !t || !t.classList.contains('is-done'); })[0];
					if (next) { flush(); S.current = +next.getAttribute('data-fz-item'); save(); render(); }
				}
			}).catch(function () { btn.disabled = false; });
		});
	});
	function progress() {
		if (!el.count) return;
		var total = +el.count.getAttribute('data-total'), done = $$('.fz-item-head .fz-tick.is-done').length;
		el.count.textContent = done + ' de ' + total + ' feitas';
		if (el.progress) el.progress.style.width = Math.round(100 * done / total) + '%';
	}
	$$('[data-fz-pick]').forEach(function (b) {
		b.addEventListener('click', function () { flush(); S.current = +b.getAttribute('data-fz-pick'); save(); render(); });
	});

	/* ---------- Botões ---------- */

	el.toggle.addEventListener('click', function () { S.running ? pause() : start(); });
	if (el.skip) el.skip.addEventListener('click', function () { nextPhase(false); });
	el.reset.addEventListener('click', reset);
	var paintSound = function () { el.sound.classList.toggle('is-off', !soundOn); el.sound.setAttribute('aria-pressed', soundOn ? 'true' : 'false'); };
	el.sound.addEventListener('click', function () { soundOn = !soundOn; try { localStorage.setItem('lk-focus-sound', soundOn ? '1' : '0'); } catch (e) {} paintSound(); });
	paintSound();
	el.full.addEventListener('click', function () {
		if (document.fullscreenElement) document.exitFullscreen(); else if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().catch(function () {});
	});
	el.exit.addEventListener('click', function () {
		var sent = flush(true);
		leaving = true;
		keepAwake(false);
		el.exit.disabled = true;
		try { localStorage.removeItem(KEY); } catch (e) {}
		if (document.fullscreenElement) document.exitFullscreen().catch(function () {});
		// Espera o registro do tempo (no máximo 2 segundos) antes de sair.
		var go = function () { location.href = C.exit; };
		if (sent) { Promise.race([sent, new Promise(function (r) { setTimeout(r, 2000); })]).then(go, go); } else { go(); }
	});
	// Fundo preto ou branco (fica lembrado neste aparelho).
	var paintTheme = function () {
		var dark = document.body.getAttribute('data-fz-theme') !== 'claro';
		el.theme.title = dark ? 'Fundo branco' : 'Fundo preto';
		el.theme.setAttribute('aria-label', dark ? 'Trocar para fundo branco' : 'Trocar para fundo preto');
		var meta = document.querySelector('meta[name="theme-color"]'); if (meta) meta.setAttribute('content', dark ? '#05080B' : '#FFFFFF');
	};
	el.theme.addEventListener('click', function () {
		var next = document.body.getAttribute('data-fz-theme') === 'claro' ? 'escuro' : 'claro';
		document.body.setAttribute('data-fz-theme', next);
		try { localStorage.setItem('lk-focus-theme', next); } catch (e) {}
		paintTheme();
	});
	paintTheme();
	// Chat da equipe: mostrar ou ocultar (as ligações continuam chegando).
	var paintChat = function () {
		var off = document.body.classList.contains('fz-chat-off');
		el.chat.classList.toggle('is-off', off);
		el.chat.setAttribute('aria-pressed', off ? 'false' : 'true');
		el.chat.title = off ? 'Mostrar o chat' : 'Ocultar o chat';
		el.chat.setAttribute('aria-label', el.chat.title);
	};
	el.chat.addEventListener('click', function () {
		var off = !document.body.classList.contains('fz-chat-off');
		document.body.classList.toggle('fz-chat-off', off);
		try { localStorage.setItem('lk-focus-chat', off ? '0' : '1'); } catch (e) {}
		if (off) { var close = document.querySelector('[data-hub-close]'); var win = document.querySelector('[data-hub-win]'); if (close && win && !win.hidden) close.click(); }
		paintChat();
	});
	if (!document.querySelector('[data-hub]')) el.chat.hidden = true;
	paintChat();
	// Hora do dia no topo.
	var clock = function () { var d = new Date(); el.now.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()); };
	clock(); setInterval(clock, 15000);

	document.addEventListener('keydown', function (e) {
		if (e.target.closest && e.target.closest('input, textarea, select, [data-hub]')) return;
		if (e.code === 'Space') { e.preventDefault(); S.running ? pause() : start(); }
	});
	// Fechou a aba no meio do foco: registra o que já foi feito.
	window.addEventListener('pagehide', function () { flush(true); });
	document.addEventListener('visibilitychange', function () { if (!document.hidden && S.running) keepAwake(true); });

	if (S.current >= (C.items || []).length) S.current = 0;
	if (S.running) keepAwake(true);
	render();
	setInterval(render, 250);
})();

