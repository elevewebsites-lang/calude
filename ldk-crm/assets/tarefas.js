/* Quadro de tarefas por prazo: arrastar muda o prazo; ✓ conclui; clicar abre. */
(function () {
	'use strict';
	var board = document.querySelector('[data-tk-board]');
	if (!board) return;
	var ep = board.getAttribute('data-endpoint'), nonce = board.getAttribute('data-nonce');
	function call(body) {
		return fetch(ep, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify(body) })
			.then(function (r) { return r.json().then(function (d) { if (!r.ok) throw new Error(d.message || 'Erro'); return d; }); });
	}
	function recount() {
		board.querySelectorAll('.tk-col').forEach(function (c) {
			var n = c.querySelectorAll('.tk-card').length, k = c.querySelector('.kcount'); if (k) k.textContent = n;
		});
	}
	var drag = null;
	board.addEventListener('dragstart', function (e) { var c = e.target.closest('.tk-card[data-task]'); if (!c) return; drag = c; c.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; });
	board.addEventListener('dragend', function () { if (drag) drag.classList.remove('is-drag'); drag = null; board.querySelectorAll('.is-over').forEach(function (b) { b.classList.remove('is-over'); }); });
	board.addEventListener('dragover', function (e) { var b = e.target.closest('.tk-body'); if (!b || !drag || b.hasAttribute('data-nodrop')) return; e.preventDefault(); b.classList.add('is-over'); });
	board.addEventListener('dragleave', function (e) { var b = e.target.closest('.tk-body'); if (b) b.classList.remove('is-over'); });
	board.addEventListener('drop', function (e) {
		var b = e.target.closest('.tk-body'); if (!b || !drag || b.hasAttribute('data-nodrop')) return;
		e.preventDefault(); b.classList.remove('is-over');
		var card = drag, from = card.parentNode, empty = b.querySelector('.tk-empty'); if (empty) empty.remove();
		b.appendChild(card); recount();
		call({ id: card.getAttribute('data-id'), col: b.getAttribute('data-col') }).then(function () { location.reload(); }).catch(function (err) { from.appendChild(card); recount(); alert(err.message); });
	});
	board.addEventListener('click', function (e) {
		var done = e.target.closest('[data-tk-done]');
		if (done) {
			e.stopPropagation();
			var c = done.closest('.tk-card');
			call({ id: c.getAttribute('data-id'), op: 'done' }).then(function () { document.dispatchEvent(new CustomEvent('lk:task-done', { detail: { el: c } })); c.classList.add('is-gone'); setTimeout(function () { c.remove(); recount(); }, 300); }).catch(function (err) { alert(err.message); });
			return;
		}
		var card = e.target.closest('.tk-card'); if (card && card.getAttribute('data-href')) location.href = card.getAttribute('data-href');
	});
})();
