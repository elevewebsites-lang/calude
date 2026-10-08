/* Abas da ficha do cliente: Resumo, Dados, Contratos… O endereço guarda a aba (#contratos) e links antigos (#vencimentos, #formularios) abrem a aba certa. */
(function () {
	var nav = document.querySelector('[data-ctabs]');
	if (!nav) return;
	var btns = [].slice.call(nav.querySelectorAll('[data-tab-btn]'));
	var panels = [].slice.call(document.querySelectorAll('[data-tab-panel]'));
	function has(key) { return panels.some(function (p) { return p.getAttribute('data-tab-panel') === key; }); }
	function show(key, push) {
		if (!has(key)) key = 'resumo';
		panels.forEach(function (p) { p.hidden = p.getAttribute('data-tab-panel') !== key; });
		btns.forEach(function (b) {
			var on = b.getAttribute('data-tab-btn') === key;
			b.classList.toggle('is-active', on);
			b.setAttribute('aria-selected', on ? 'true' : 'false');
		});
		if (push && history.replaceState) history.replaceState(null, '', '#' + key);
	}
	function fromHash() {
		var h = decodeURIComponent(location.hash.replace(/^#/, ''));
		if (!h) return { key: 'resumo' };
		if (has(h)) return { key: h };
		var el = document.getElementById(h);
		var p = el && el.closest('[data-tab-panel]');
		return p ? { key: p.getAttribute('data-tab-panel'), el: el } : { key: 'resumo' };
	}
	btns.forEach(function (b) {
		b.addEventListener('click', function () { show(b.getAttribute('data-tab-btn'), true); window.scrollTo({ top: 0 }); });
	});
	document.addEventListener('click', function (e) {
		var g = e.target.closest('[data-goto]');
		if (!g) return;
		e.preventDefault();
		show(g.getAttribute('data-goto'), true);
	});
	function apply() {
		var t = fromHash();
		show(t.key, false);
		if (t.el) t.el.scrollIntoView({ block: 'start' });
	}
	window.addEventListener('hashchange', apply);
	apply();
})();
