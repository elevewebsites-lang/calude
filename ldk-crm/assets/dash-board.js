/* Dashboard: mover (arrastar pelo ⠿) e redimensionar (canto de baixo) os blocos.
   A largura é em colunas de 12 e nunca fica abaixo do mínimo de cada bloco; a altura só aumenta (mínimo = conteúdo). */
(function () {
	var board = document.querySelector('[data-board]');
	var tgl = document.querySelector('[data-board-toggle]');
	if (!board || !tgl) return;
	var hint = document.querySelector('[data-board-hint]');
	var GAP = 18, ROW = 44, timer = null;
	var label = tgl.querySelector('span');

	function items() { return Array.prototype.slice.call(board.children).filter(function (e) { return e.classList.contains('dw'); }); }
	function setW(el, w) { el.dataset.w = w; el.style.setProperty('--w', w); el.classList.toggle('dw--l', w > 6); el.classList.toggle('dw--s', w <= 6); }
	function setH(el, h) { el.dataset.h = h; el.style.setProperty('--h', h); }
	function toast(msg) {
		var t = document.createElement('div'); t.className = 'dash-toast'; t.setAttribute('role', 'status'); t.textContent = msg; document.body.appendChild(t);
		setTimeout(function () { t.classList.add('is-out'); }, 1400); setTimeout(function () { t.remove(); }, 1900);
	}
	function save() {
		clearTimeout(timer);
		timer = setTimeout(function () {
			var order = [], size = {};
			items().forEach(function (el) { order.push(el.dataset.dw); size[el.dataset.dw] = { w: +el.dataset.w, h: +el.dataset.h }; });
			fetch(window.LK.rest + 'dash-layout', { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': window.LK.nonce, 'Content-Type': 'application/json' }, body: JSON.stringify({ order: order, size: size }) })
				.then(function (r) { if (!r.ok) throw new Error(); toast('Layout salvo ✓'); })
				.catch(function () { toast('Não foi possível salvar o layout.'); });
		}, 350);
	}

	tgl.addEventListener('click', function () {
		var on = document.body.classList.toggle('is-arranging');
		if (label) label.textContent = on ? 'Concluir' : 'Organizar';
		if (hint) hint.hidden = !on;
	});

	// Arrastar para mudar a posição.
	board.addEventListener('pointerdown', function (e) {
		if (!document.body.classList.contains('is-arranging')) return;
		var grip = e.target.closest('.dw-grip');
		var rs = e.target.closest('.dw-rs');
		var el = e.target.closest('.dw');
		if (!el || (!grip && !rs)) return;
		e.preventDefault();
		var cap = grip || rs;
		try { cap.setPointerCapture(e.pointerId); } catch (x) {}
		var changed = false;
		if (grip) {
			el.classList.add('is-drag');
			var move = function (ev) {
				el.style.pointerEvents = 'none';
				var under = document.elementFromPoint(ev.clientX, ev.clientY);
				el.style.pointerEvents = '';
				var t = under && under.closest ? under.closest('.dw') : null;
				if (!t || t === el || t.parentNode !== board) return;
				var r = t.getBoundingClientRect();
				var after = ev.clientX > r.left + r.width / 2 && ev.clientY > r.top;
				board.insertBefore(el, after ? t.nextSibling : t);
				changed = true;
			};
			var up = function () {
				cap.removeEventListener('pointermove', move); cap.removeEventListener('pointerup', up); cap.removeEventListener('pointercancel', up);
				el.classList.remove('is-drag'); if (changed) save();
			};
			cap.addEventListener('pointermove', move); cap.addEventListener('pointerup', up); cap.addEventListener('pointercancel', up);
		} else {
			var min = +el.dataset.min || 3;
			var colw = (board.clientWidth + GAP) / 12;
			var r0 = el.getBoundingClientRect();
			var oldH = el.dataset.h;
			setH(el, 0); var nat = el.offsetHeight; setH(el, oldH);
			var sx = e.clientX, sy = e.clientY;
			el.classList.add('is-resizing');
			var mv = function (ev) {
				var w = Math.max(min, Math.min(12, Math.round((r0.width + (ev.clientX - sx) + GAP) / colw)));
				var hpx = r0.height + (ev.clientY - sy);
				var h = hpx <= nat + ROW / 2 ? 0 : Math.min(40, Math.round(hpx / ROW));
				if (w !== +el.dataset.w) { setW(el, w); changed = true; }
				if (h !== +el.dataset.h) { setH(el, h); changed = true; }
			};
			var end = function () {
				cap.removeEventListener('pointermove', mv); cap.removeEventListener('pointerup', end); cap.removeEventListener('pointercancel', end);
				el.classList.remove('is-resizing'); if (changed) save();
			};
			cap.addEventListener('pointermove', mv); cap.addEventListener('pointerup', end); cap.addEventListener('pointercancel', end);
		}
	});

	// Botões − / + (alternativa ao arrastar, bom no celular).
	board.addEventListener('click', function (e) {
		var b = e.target.closest('[data-dw-step]');
		if (!b) return;
		var el = b.closest('.dw'), min = +el.dataset.min || 3;
		setW(el, Math.max(min, Math.min(12, +el.dataset.w + (+b.dataset.dwStep) * 2)));
		save();
	});
})();
