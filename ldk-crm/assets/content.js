/* LDK · conteúdo: calendário (arrastar entre dias), Kanban (arrastar entre etapas), modal com data */
document.addEventListener('DOMContentLoaded', function () {
	'use strict';
	var CFG = window.LK;
	var $ = function (s, c) { return (c || document).querySelector(s); };
	var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
	var move = function (body) {
		return fetch(CFG.rest + 'post/move', { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': CFG.nonce, 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
	};
	// Botões "novo post" levam a data do dia para o formulário.
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-open="novo-post"]');
		if (!b) return;
		var d = $('[data-post-date]', $('#novo-post'));
		if (d) d.value = b.getAttribute('data-date') || '';
		var back = $('#novo-post [name=volta_url]');
		if (back) back.value = location.href;
	});
	// Contador de caracteres da legenda (Instagram: 2.200).
	$$('[data-caption]').forEach(function (ta) {
		var out = ta.closest('form').querySelector('[data-caption-count]');
		var upd = function () { if (out) out.textContent = ta.value.length + ' / 2.200 caracteres' + ((ta.value.match(/#/g) || []).length > 30 ? ' · máximo de 30 hashtags' : ''); };
		ta.addEventListener('input', upd); upd();
	});
	// Calendário: arrastar o post para outro dia.
	var cal = $('[data-cal]');
	if (cal) {
		var dragged = null;
		document.addEventListener('dragstart', function (e) { var p = e.target.closest('[data-post]'); if (p) { dragged = p; p.classList.add('is-dragging'); } });
		document.addEventListener('dragend', function () { if (dragged) dragged.classList.remove('is-dragging'); $$('.cal-day.is-over').forEach(function (d) { d.classList.remove('is-over'); }); });
		$$('.cal-day', cal).forEach(function (day) {
			day.addEventListener('dragover', function (e) { e.preventDefault(); day.classList.add('is-over'); });
			day.addEventListener('dragleave', function () { day.classList.remove('is-over'); });
			day.addEventListener('drop', function (e) {
				e.preventDefault(); day.classList.remove('is-over');
				if (!dragged) return;
				day.appendChild(dragged);
				move({ id: dragged.getAttribute('data-post'), date: day.getAttribute('data-day') });
			});
		});
	}
	// Kanban de posts.
	var kb = $('[data-post-kanban]');
	if (kb) {
		var card = null;
		kb.addEventListener('dragstart', function (e) { card = e.target.closest('.kcard'); if (card) card.classList.add('is-dragging'); });
		kb.addEventListener('dragend', function () { if (card) card.classList.remove('is-dragging'); });
		$$('.kcol-body', kb).forEach(function (col) {
			col.addEventListener('dragover', function (e) { e.preventDefault(); col.classList.add('is-over'); });
			col.addEventListener('dragleave', function () { col.classList.remove('is-over'); });
			col.addEventListener('drop', function (e) {
				e.preventDefault(); col.classList.remove('is-over');
				if (!card) return;
				col.appendChild(card);
				move({ id: card.getAttribute('data-id'), stage: col.getAttribute('data-status'), order: $$('.kcard', col).map(function (c) { return c.getAttribute('data-id'); }) });
				$$('.kcol', kb).forEach(function (c) { $('.kcount', c).textContent = $$('.kcard', c).length; });
			});
		});
		kb.addEventListener('click', function (e) { var c = e.target.closest('.kcard'); if (c && !e.target.closest('a,button')) location.href = c.getAttribute('data-href'); });
	}
});

/* Prazos de entrega: ao mudar o dia da publicação, recalcula os prazos que ninguém mexeu à mão. */
(function () {
	function pad(n) { return (n < 10 ? '0' : '') + n; }
	document.addEventListener('input', function (e) {
		if (e.target.matches('[data-prazo]')) e.target.dataset.touched = '1';
	});
	document.addEventListener('change', function (e) {
		if (!e.target.matches('[data-post-date]')) return;
		var form = e.target.closest('form');
		var box = form && form.querySelector('[data-prazos]');
		if (!box || !e.target.value) return;
		var hora = box.dataset.hora || '18:00';
		var pub = new Date(e.target.value + 'T12:00:00');
		box.querySelectorAll('[data-prazo]').forEach(function (inp) {
			if (inp.dataset.touched) return;
			var d = new Date(pub.getTime() - (parseInt(inp.dataset.dias, 10) || 0) * 864e5);
			inp.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + hora;
		});
	});
})();
