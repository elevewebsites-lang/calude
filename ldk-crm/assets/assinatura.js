/* Quadro de assinatura: desenha com dedo/mouse e grava o PNG no campo escondido. */
(function () {
	document.querySelectorAll('[data-sig-pad]').forEach(function (box) {
		var cv = box.querySelector('canvas'), ctx = cv.getContext('2d'), inp = box.querySelector('[data-sig-input]');
		var drawing = false, last = null, dirty = false;
		ctx.lineWidth = 2.6; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0b1b2b';
		function pos(e) { var r = cv.getBoundingClientRect(), t = e.touches ? e.touches[0] : e; return { x: (t.clientX - r.left) * cv.width / r.width, y: (t.clientY - r.top) * cv.height / r.height }; }
		function start(e) { e.preventDefault(); drawing = true; last = pos(e); }
		function move(e) { if (!drawing) return; e.preventDefault(); var p = pos(e); ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke(); last = p; dirty = true; }
		function end() { if (!drawing) return; drawing = false; if (dirty) inp.value = cv.toDataURL('image/png'); box.classList.toggle('is-signed', dirty); }
		cv.addEventListener('mousedown', start); cv.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
		cv.addEventListener('touchstart', start, { passive: false }); cv.addEventListener('touchmove', move, { passive: false }); cv.addEventListener('touchend', end);
		box.querySelector('[data-sig-clear]').addEventListener('click', function () { ctx.clearRect(0, 0, cv.width, cv.height); inp.value = ''; dirty = false; box.classList.remove('is-signed'); });
		var form = box.closest('form');
		if (form) form.addEventListener('submit', function (e) { if (!inp.value && form.querySelector('[data-sig-required]')) { e.preventDefault(); alert('Desenhe a sua assinatura.'); } });
	});
})();
