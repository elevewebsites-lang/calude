/* LDK Propostas · navegação horizontal por seções */
(function () {
	'use strict';

	var track = document.getElementById('horizontalScroll');
	var panels = Array.prototype.slice.call(document.querySelectorAll('.panel'));
	var fill = document.getElementById('progressFill');
	var dotsWrap = document.getElementById('navDots');
	var current = document.getElementById('currentSection');
	var hint = document.getElementById('scrollHint');
	var total = panels.length;
	var index = 0;
	var locked = false;
	var LOCK_MS = 1150;

	var pad = function (n) { return String(n).padStart(2, '0'); };
	var isMobile = function () { return window.innerWidth <= 900; };

	document.getElementById('totalSections').textContent = pad(total);

	var dots = panels.map(function (_, i) {
		var b = document.createElement('button');
		b.className = 'nav-dot';
		b.type = 'button';
		b.setAttribute('aria-label', 'Ir para a seção ' + (i + 1));
		b.addEventListener('click', function () { go(i); });
		dotsWrap.appendChild(b);
		return b;
	});

	function render() {
		track.style.transform = isMobile() ? 'none' : 'translateX(' + (-index * 100) + 'vw)';
		fill.style.width = (total > 1 ? (index / (total - 1)) * 100 : 100) + '%';
		current.textContent = pad(index + 1);
		dots.forEach(function (d, i) { d.classList.toggle('active', i === index); });
		panels.forEach(function (p, i) { p.classList.toggle('active', i === index); });
		document.body.classList.toggle('on-dark', panels[index].dataset.theme === 'dark');
		document.body.classList.toggle('on-cover', index === 0);
		if (index > 0) hint.classList.add('hidden');
	}

	function go(i) {
		if (isMobile() || locked || i === index || i < 0 || i >= total) return;
		locked = true;
		setTimeout(function () { locked = false; }, LOCK_MS);
		index = i;
		render();
	}

	/* Roda do mouse / trackpad: acumula o gesto e avança uma seção por vez */
	var delta = 0, timer = null;
	document.addEventListener('wheel', function (e) {
		if (isMobile()) return;
		e.preventDefault();
		if (locked) { delta = 0; return; }
		delta += Math.abs(e.deltaY) > Math.abs(e.deltaX) ? e.deltaY : e.deltaX;
		clearTimeout(timer);
		timer = setTimeout(function () {
			if (!locked && Math.abs(delta) > 30) go(index + (delta > 0 ? 1 : -1));
			delta = 0;
		}, 80);
	}, { passive: false });

	/* Teclado */
	document.addEventListener('keydown', function (e) {
		if (isMobile()) return;
		var map = { ArrowRight: 1, ArrowDown: 1, PageDown: 1, ' ': 1, ArrowLeft: -1, ArrowUp: -1, PageUp: -1 };
		if (map[e.key]) { e.preventDefault(); go(index + map[e.key]); }
		else if (e.key === 'Home') { e.preventDefault(); go(0); }
		else if (e.key === 'End') { e.preventDefault(); go(total - 1); }
	});

	/* Toque (tablet na horizontal) */
	var sx = 0, sy = 0, st = 0;
	document.addEventListener('touchstart', function (e) {
		sx = e.touches[0].clientX; sy = e.touches[0].clientY; st = Date.now();
	}, { passive: true });
	document.addEventListener('touchend', function (e) {
		if (isMobile()) return;
		var dx = sx - e.changedTouches[0].clientX;
		var dy = sy - e.changedTouches[0].clientY;
		var main = Math.abs(dx) > Math.abs(dy) ? dx : dy;
		if (Math.abs(main) > 50 && Date.now() - st < 600) go(index + (main > 0 ? 1 : -1));
	}, { passive: true });

	window.addEventListener('resize', render);

	/* Contadores da capa */
	function countUp() {
		document.querySelectorAll('[data-count]').forEach(function (el) {
			var target = parseFloat(el.dataset.count) || 0;
			var start = performance.now();
			(function step(now) {
				var t = Math.min((now - start) / 2000, 1);
				var v = target * (1 - Math.pow(1 - t, 4));
				el.textContent = target % 1 ? v.toFixed(1) : Math.floor(v);
				if (t < 1) requestAnimationFrame(step);
			})(start);
		});
	}

	render();
	setTimeout(countUp, 900);
})();
