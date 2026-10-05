/* Ver a arte grande: clique na imagem do post (painel, aprovação do cliente, simulação do Instagram).
   Setas ← → trocam de imagem do mesmo post, clique na imagem alterna entre "caber na tela" e tamanho real, Esc fecha. */
(function () {
	var SEL = '[data-zoom], .media-item img, img[alt^="Arte "], .ig-art img, .ig-media img';
	var box, imgEl, capEl, list = [], idx = 0, zoomed = false;

	function build() {
		if (box) return;
		box = document.createElement('div');
		box.className = 'zoom';
		box.hidden = true;
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-label', 'Arte em tamanho grande');
		box.innerHTML = '<button type="button" class="zoom-x" aria-label="Fechar">×</button><button type="button" class="zoom-nav zoom-prev" aria-label="Anterior">‹</button><button type="button" class="zoom-nav zoom-next" aria-label="Próxima">›</button><div class="zoom-stage"><img alt=""></div><div class="zoom-cap"></div>';
		document.body.appendChild(box);
		imgEl = box.querySelector('img');
		capEl = box.querySelector('.zoom-cap');
		box.addEventListener('click', function (e) {
			if (e.target.closest('.zoom-x') || e.target === box || e.target.classList.contains('zoom-stage')) return close();
			if (e.target.closest('.zoom-prev')) return go(-1);
			if (e.target.closest('.zoom-next')) return go(1);
			if (e.target === imgEl) { zoomed = !zoomed; box.classList.toggle('is-full', zoomed); }
		});
	}
	function show() {
		var a = list[idx];
		zoomed = false;
		box.classList.remove('is-full');
		imgEl.src = a.src;
		imgEl.alt = a.alt || '';
		capEl.innerHTML = '';
		var t = document.createElement('span');
		t.textContent = (list.length > 1 ? (idx + 1) + ' / ' + list.length + ' · ' : '') + 'clique na imagem para ampliar';
		var l = document.createElement('a');
		l.href = a.src; l.target = '_blank'; l.rel = 'noopener'; l.textContent = 'Abrir original';
		capEl.appendChild(t); capEl.appendChild(l);
		box.classList.toggle('has-nav', list.length > 1);
	}
	function open(img) {
		build();
		var scope = img.closest('.media-grid, .ig-card, .igp, .ig-post, [data-zoom-group]') || document;
		list = Array.prototype.slice.call(scope.querySelectorAll(SEL)).filter(function (e) { return e.tagName === 'IMG' && e.src; });
		idx = Math.max(0, list.indexOf(img));
		box.hidden = false;
		document.documentElement.classList.add('zoom-open');
		show();
	}
	function close() { if (box) { box.hidden = true; imgEl.removeAttribute('src'); } document.documentElement.classList.remove('zoom-open'); }
	function go(d) { if (list.length < 2) return; idx = (idx + d + list.length) % list.length; show(); }

	document.addEventListener('click', function (e) {
		var img = e.target.closest && e.target.closest(SEL);
		if (!img || img.tagName !== 'IMG' || (box && box.contains(img))) return;
		if (img.closest('a[href]') && !img.closest('[data-zoom-group], .media-item')) return; // miniaturas que levam a outra página continuam levando
		e.preventDefault();
		open(img);
	});
	document.addEventListener('keydown', function (e) {
		if (!box || box.hidden) return;
		if (e.key === 'Escape') close();
		else if (e.key === 'ArrowLeft') go(-1);
		else if (e.key === 'ArrowRight') go(1);
	});
})();
