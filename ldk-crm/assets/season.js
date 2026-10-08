/* Temas festivos opcionais (Halloween, Natal, Ano Novo): paleta, decoração e animações. Tudo fica neste aparelho. */
(function () {
	'use strict';
	var KEY = 'lk-season', FXKEY = 'lk-season-fx';
	var SEASONS = {
		halloween: { icon: '🎃', name: 'Halloween', msg: 'Tema Halloween ligado 🎃' },
		natal: { icon: '🎄', name: 'Natal', msg: 'Tema Natal ligado 🎄' },
		anonovo: { icon: '🎆', name: 'Ano Novo', msg: 'Tema Ano Novo ligado 🎆' }
	};
	var html = document.documentElement;
	var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
	function get(k, d) { try { return localStorage.getItem(k) || d; } catch (e) { return d; } }
	function set(k, v) { try { if (v) localStorage.setItem(k, v); else localStorage.removeItem(k); } catch (e) {} }
	var cur = get(KEY, ''); if (!SEASONS[cur]) cur = '';
	var fxOn = get(FXKEY, '1') !== '0';
	var timers = [], deco = [], raf = 0, canvas = null, ctx = null, parts = [], mode = '';

	/* ---------- seletor ---------- */
	function toast(t) { var e = document.createElement('div'); e.className = 'season-toast'; e.textContent = t; document.body.appendChild(e); setTimeout(function () { e.remove(); }, 2600); }
	function btnLabel() { return (SEASONS[cur] ? SEASONS[cur].icon : '🎨') + ' Tema festivo'; }
	function buildPicker() {
		var host = document.querySelector('.side-tools'), float = !host;
		var wrap = document.createElement('div'); wrap.className = 'season-pick';
		var b = document.createElement('button'); b.type = 'button'; b.className = 'season-btn' + (float ? ' season-btn--float' : ''); b.setAttribute('aria-haspopup', 'true'); b.setAttribute('aria-expanded', 'false');
		wrap.appendChild(b);
		(host || document.body).appendChild(float ? b : wrap);
		var menu = document.createElement('div'); menu.className = 'season-menu' + (float ? ' season-menu--float' : ''); menu.hidden = true; menu.setAttribute('role', 'menu');
		(float ? document.body : wrap).appendChild(menu);
		function draw() {
			b.textContent = btnLabel();
			var h = '<button type="button" data-s="" class="' + (!cur ? 'is-on' : '') + '">⚪ Nenhum (normal)</button>';
			Object.keys(SEASONS).forEach(function (k) { h += '<button type="button" data-s="' + k + '" class="' + (cur === k ? 'is-on' : '') + '">' + SEASONS[k].icon + ' ' + SEASONS[k].name + '</button>'; });
			h += '<hr><button type="button" data-fx>' + (fxOn ? '⏸ Desligar animações' : '▶ Ligar animações') + '</button><small>Opcional e só neste aparelho. As cores mudam e, com as animações ligadas, entram os efeitos da data.</small>';
			menu.innerHTML = h;
			if (!float) { var r = b.getBoundingClientRect(); menu.style.bottom = 'calc(100% + 6px)'; menu.style.left = '0'; }
		}
		draw();
		b.addEventListener('click', function (e) { e.stopPropagation(); menu.hidden = !menu.hidden; b.setAttribute('aria-expanded', String(!menu.hidden)); });
		document.addEventListener('click', function (e) { if (!menu.hidden && !menu.contains(e.target) && e.target !== b) { menu.hidden = true; } });
		menu.addEventListener('click', function (e) {
			var o = e.target.closest('button'); if (!o) return;
			if (o.hasAttribute('data-fx')) { fxOn = !fxOn; set(FXKEY, fxOn ? '1' : '0'); apply(); draw(); return; }
			cur = o.getAttribute('data-s') || ''; set(KEY, cur); apply(); draw(); menu.hidden = true;
			toast(cur ? SEASONS[cur].msg : 'Tema normal');
		});
	}

	/* ---------- aplicar tema ---------- */
	function clearFx() {
		timers.forEach(clearTimeout); timers = [];
		deco.forEach(function (d) { d.remove(); }); deco = [];
		if (raf) cancelAnimationFrame(raf); raf = 0; parts = [];
		if (canvas) { canvas.remove(); canvas = null; ctx = null; }
		document.querySelectorAll('.sea-bat,.sea-ghost').forEach(function (e) { e.remove(); });
	}
	function addDeco(cls, text, extra) {
		var d = document.createElement('div'); d.className = 'season-deco ' + cls; d.textContent = text || ''; if (extra) d.innerHTML = extra;
		document.body.appendChild(d); deco.push(d); return d;
	}
	function after(ms, fn) { var t = setTimeout(fn, ms); timers.push(t); return t; }
	function apply() {
		clearFx();
		if (cur) html.setAttribute('data-season', cur); else html.removeAttribute('data-season');
		mode = cur;
		if (!cur) return;
		// Decoração fixa (sem movimento pesado).
		if (cur === 'halloween') { addDeco('sea-pumpkin', '🎃'); addDeco('sea-fog', '').className = 'sea-fog'; var sp = addDeco('sea-spider', '', '<span>🕷️</span>'); sp.className = 'sea-spider'; }
		if (cur === 'natal') { addDeco('sea-santa', '🎅'); var l = document.createElement('div'); l.className = 'sea-lights'; for (var i = 0; i < 26; i++) l.appendChild(document.createElement('i')); document.body.appendChild(l); deco.push(l); }
		if (cur === 'anonovo') { addDeco('sea-champ', '🥂'); }
		if (!fxOn || reduce) return;
		canvas = document.createElement('canvas'); canvas.className = 'season-fx'; document.body.appendChild(canvas); ctx = canvas.getContext('2d'); size();
		if (cur === 'halloween') halloweenLoop();
		if (cur === 'natal') snowLoop();
		if (cur === 'anonovo') fireworksLoop();
	}
	function size() { if (!canvas) return; canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
	window.addEventListener('resize', size);
	document.addEventListener('visibilitychange', function () { if (!document.hidden && parts.length && !raf) tick(); });

	/* ---------- partículas ---------- */
	function tick() {
		if (!ctx) return;
		raf = 0; ctx.clearRect(0, 0, canvas.width, canvas.height);
		parts = parts.filter(function (p) { return p.life > 0; });
		parts.forEach(function (p) { p.step(ctx); });
		if (parts.length && !document.hidden) raf = requestAnimationFrame(tick);
	}
	function add(p) { parts.push(p); if (!raf) tick(); }

	/* ❄ Natal: neve às vezes */
	function flake(x, y) {
		var r = 1.5 + Math.random() * 3.2, sp = 0.7 + Math.random() * 1.6, dr = (Math.random() - .5) * .8, ph = Math.random() * 6.28;
		return { life: 1, step: function (c) { y += sp; x += dr + Math.sin((y + ph * 40) / 40) * .5; if (y > canvas.height + 10) this.life = 0; c.beginPath(); c.arc(x, y, r, 0, 6.283); c.fillStyle = 'rgba(255,255,255,' + (0.55 + r / 9) + ')'; c.fill(); } };
	}
	function snowLoop() {
		function burst() {
			var n = 0, total = 70;
			var iv = setInterval(function () { if (!ctx || n++ > total) { clearInterval(iv); return; } add(flake(Math.random() * canvas.width, -10)); add(flake(Math.random() * canvas.width, -30)); }, 220);
			timers.push(iv);
			after(45000 + Math.random() * 60000, burst);
		}
		after(1200, burst);
	}

	/* 🎆 Ano Novo: fogos */
	var COLORS = ['#f5c542', '#ff6b6b', '#6bd6ff', '#b86bff', '#6bff9c', '#ffffff'];
	function firework(x, y, small) {
		var col = COLORS[Math.floor(Math.random() * COLORS.length)], n = small ? 26 : 70, c2 = COLORS[Math.floor(Math.random() * COLORS.length)];
		for (var i = 0; i < n; i++) {
			(function () {
				var a = Math.random() * 6.283, s = (small ? 1.2 : 2) + Math.random() * (small ? 2 : 3.6), vx = Math.cos(a) * s, vy = Math.sin(a) * s, px = x, py = y, life = 1, color = Math.random() < .5 ? col : c2;
				add({ life: 1, step: function (c) { vy += .035; vx *= .985; px += vx; py += vy; life -= .014; this.life = life; c.globalAlpha = Math.max(0, life); c.fillStyle = color; c.beginPath(); c.arc(px, py, 2, 0, 6.283); c.fill(); c.globalAlpha = 1; } });
			})();
		}
	}
	function rocket() {
		if (!ctx) return;
		var x = canvas.width * (.15 + Math.random() * .7), y = canvas.height, ty = canvas.height * (.12 + Math.random() * .35);
		add({ life: 1, step: function (c) { y -= 7; c.fillStyle = '#fff'; c.fillRect(x, y, 2, 8); if (y <= ty) { this.life = 0; firework(x, y); } } });
	}
	function fireworksLoop() {
		function go() { var k = 1 + Math.floor(Math.random() * 3); for (var i = 0; i < k; i++) after(i * 500, rocket); after(6000 + Math.random() * 6000, go); }
		after(800, go);
	}

	/* 🎃 Halloween: morcegos, fantasmas */
	function halloweenLoop() {
		function bats() {
			var n = 3 + Math.floor(Math.random() * 3);
			for (var i = 0; i < n; i++) {
				var b = document.createElement('div'); b.className = 'sea-bat'; b.textContent = '🦇'; b.style.top = (8 + Math.random() * 55) + 'vh'; b.style.animationDuration = (8 + Math.random() * 4) + 's'; b.style.animationDelay = (i * .45) + 's';
				document.body.appendChild(b); (function (e) { setTimeout(function () { e.remove(); }, 14000); })(b);
			}
			after(18000 + Math.random() * 14000, bats);
		}
		function ghost() {
			var g = document.createElement('div'); g.className = 'sea-ghost'; g.textContent = '👻'; g.style.left = (8 + Math.random() * 80) + 'vw'; g.style.bottom = '-40px';
			document.body.appendChild(g); setTimeout(function () { g.remove(); }, 7500);
			after(14000 + Math.random() * 16000, ghost);
		}
		after(2500, bats); after(7000, ghost);
	}

	/* ✓ tarefa concluída: comemoração no cartão */
	document.addEventListener('lk:task-done', function (e) {
		if (!cur || !fxOn || reduce) return;
		var el = e.detail && e.detail.el; var r = el ? el.getBoundingClientRect() : { left: window.innerWidth / 2, top: window.innerHeight / 2, width: 0, height: 0 };
		var x = r.left + r.width / 2, y = r.top + r.height / 2;
		if (cur === 'halloween') { var g = document.createElement('div'); g.className = 'sea-ghost'; g.textContent = '👻'; g.style.left = x + 'px'; g.style.top = y + 'px'; g.style.bottom = 'auto'; document.body.appendChild(g); setTimeout(function () { g.remove(); }, 7200); }
		else if (cur === 'natal' && ctx) { for (var i = 0; i < 24; i++) add(flake(x + (Math.random() - .5) * 120, y - 20 - Math.random() * 40)); }
		else if (cur === 'anonovo' && ctx) { firework(x, y, true); }
	});

	// Só liga no painel, na área do cliente e no login (não nas páginas públicas de aprovação, pagamento etc.).
	var ctxOk = document.querySelector('.side-tools, .lgx, .ctop');
	if (!ctxOk) { html.removeAttribute('data-season'); return; }
	buildPicker();
	apply();
})();
