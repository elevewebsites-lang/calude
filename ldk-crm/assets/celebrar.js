/* LDK · comemorações: meta batida (confete + troféu) e subida de nível (explosão de estrelas).
   Fila em window.LK_CELEBRATE; window.LKCelebrate.show(item) toca uma na hora; [data-cel-test="goal|level"] mostra um exemplo. */
(function () {
	'use strict';
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var busy = false, queue = [];

	function chime(kind) {
		try {
			var AC = window.AudioContext || window.webkitAudioContext; if (!AC) return;
			var ctx = new AC(), notes = kind === 'level' ? [523, 659, 784, 1047] : [392, 523, 659, 784, 1047];
			notes.forEach(function (f, i) {
				var o = ctx.createOscillator(), g = ctx.createGain(), t = ctx.currentTime + i * 0.12;
				o.type = 'triangle'; o.frequency.value = f; g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.12, t + 0.03); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.35);
				o.connect(g); g.connect(ctx.destination); o.start(t); o.stop(t + 0.4);
			});
			setTimeout(function () { ctx.close(); }, 1500);
		} catch (e) {}
	}

	function particles(kind, done) {
		if (reduce) { done(); return; }
		var cv = document.createElement('canvas'); cv.className = 'cel-canvas'; document.body.appendChild(cv);
		var ctx = cv.getContext('2d'), W, H, dpr = Math.min(2, window.devicePixelRatio || 1);
		function size() { W = cv.width = innerWidth * dpr; H = cv.height = innerHeight * dpr; cv.style.width = innerWidth + 'px'; cv.style.height = innerHeight + 'px'; }
		size();
		var colors = kind === 'level' ? ['#ffd166', '#8b5cf6', '#06d6a0', '#ff7a90', '#4f8cff', '#fff'] : ['#ffd166', '#ff7a90', '#6c5ce7', '#14e9ec', '#06d6a0', '#ff9a5c'];
		var ps = [], n = kind === 'level' ? 140 : 190, i;
		for (i = 0; i < n; i++) {
			if (kind === 'level') { var a = Math.random() * Math.PI * 2, v = (4 + Math.random() * 9) * dpr; ps.push({ x: W / 2, y: H * 0.42, vx: Math.cos(a) * v, vy: Math.sin(a) * v - 3 * dpr, g: 0.16 * dpr, s: (5 + Math.random() * 9) * dpr, r: Math.random() * 6, vr: (Math.random() - 0.5) * 0.3, c: colors[i % colors.length], shape: 'star', life: 1 }); }
			else { ps.push({ x: Math.random() * W, y: -Math.random() * H * 0.6, vx: (Math.random() - 0.5) * 3 * dpr, vy: (2 + Math.random() * 4) * dpr, g: 0.05 * dpr, s: (6 + Math.random() * 8) * dpr, r: Math.random() * 6, vr: (Math.random() - 0.5) * 0.3, c: colors[i % colors.length], shape: Math.random() > 0.5 ? 'rect' : 'circ', life: 1 }); }
		}
		var t0 = performance.now(), DUR = kind === 'level' ? 3800 : 5200;
		function star(x, y, r) { ctx.beginPath(); for (var k = 0; k < 10; k++) { var rad = k % 2 ? r * 0.45 : r, an = k * Math.PI / 5 - Math.PI / 2; ctx.lineTo(x + Math.cos(an) * rad, y + Math.sin(an) * rad); } ctx.closePath(); ctx.fill(); }
		(function frame(t) {
			var el = t - t0; ctx.clearRect(0, 0, W, H);
			ps.forEach(function (p) {
				p.vy += p.g; p.x += p.vx; p.y += p.vy; p.r += p.vr; if (kind === 'level') p.vx *= 0.99; p.life = Math.max(0, 1 - Math.max(0, el - DUR * 0.6) / (DUR * 0.4));
				ctx.save(); ctx.globalAlpha = p.life; ctx.fillStyle = p.c; ctx.translate(p.x, p.y); ctx.rotate(p.r);
				if (p.shape === 'rect') ctx.fillRect(-p.s / 2, -p.s / 4, p.s, p.s / 2); else if (p.shape === 'circ') { ctx.beginPath(); ctx.arc(0, 0, p.s / 3, 0, 7); ctx.fill(); } else star(0, 0, p.s);
				ctx.restore();
			});
			if (el < DUR) requestAnimationFrame(frame); else { cv.remove(); done(); }
		})(t0);
		window.addEventListener('resize', size, { once: true });
		return cv;
	}

	function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

	function show(item) {
		if (busy) { queue.push(item); return; }
		busy = true;
		var kind = item.type === 'level' ? 'level' : 'goal';
		var ov = document.createElement('div'); ov.className = 'cel cel--' + kind; ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-modal', 'true');
		ov.innerHTML = '<div class="cel-card"><div class="cel-glow"></div><div class="cel-emoji">' + esc(item.emoji || '🏆') + '</div><h2>' + esc(item.title) + '</h2><div class="cel-sub">' + esc(item.sub) + '</div>' +
			(kind === 'goal' ? '<div class="cel-bar"><i></i></div>' : (item.from ? '<div class="cel-lv"><span>' + esc(item.from) + '</span><b>→</b><strong>' + esc(item.to) + '</strong></div>' : '')) +
			'<p>' + esc(item.detail) + '</p><button type="button" class="cel-btn">' + (kind === 'goal' ? 'Uhuuu! 🎉' : 'Continuar 🚀') + '</button></div>';
		document.body.appendChild(ov);
		requestAnimationFrame(function () { ov.classList.add('is-in'); });
		chime(kind); particles(kind, function () {});
		function close() { ov.classList.remove('is-in'); setTimeout(function () { ov.remove(); busy = false; var n = queue.shift(); if (n) show(n); }, 300); }
		ov.querySelector('.cel-btn').addEventListener('click', close);
		ov.addEventListener('click', function (e) { if (e.target === ov) close(); });
		document.addEventListener('keydown', function esc2(e) { if (e.key === 'Escape') { document.removeEventListener('keydown', esc2); close(); } });
		setTimeout(function () { if (document.body.contains(ov)) close(); }, 12000);
	}

	window.LKCelebrate = { show: show };
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-cel-test]'); if (!b) return;
		show(b.getAttribute('data-cel-test') === 'level'
			? { type: 'level', title: 'Você subiu de nível! ⭐', sub: 'Prata', detail: '320 pontos ganhos', emoji: '🚀', from: 'Bronze', to: 'Prata' }
			: { type: 'goal', title: 'Meta batida! 🎉', sub: 'Receita do mês', detail: 'R$ 20.000,00 de R$ 20.000,00 · em outubro', emoji: '🏆' });
	});
	(window.LK_CELEBRATE || []).forEach(function (it, i) { setTimeout(function () { show(it); }, 600 + i * 50); });
})();
