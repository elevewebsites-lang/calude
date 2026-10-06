/* AllPrint · pedido do cliente: cupom e crédito (confere no servidor a cada mudança) */
(function () {
	'use strict';
	var box = document.querySelector('[data-cart]');
	if (!box) return;
	var $ = function (s) { return box.querySelector(s); };
	var money = function (v) { return 'R$ ' + (Math.round(v * 100) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
	var timer;
	function recalc() {
		var subtotal = (window.APORDER && APORDER.total) || 0;
		clearTimeout(timer); if (!window.AP) { timer = setTimeout(recalc, 200); return; }
		timer = setTimeout(function () {
			var uc = $('[data-usecredit]');
			fetch(window.AP.rest + 'coupon', { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': window.AP.nonce, 'Content-Type': 'application/json' }, body: JSON.stringify({ subtotal: subtotal, code: $('[data-coupon]').value.trim(), use_credit: uc ? uc.checked : false }) })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					var d = j.discount || 0, cr = j.credit_used || 0, pay = j.total != null ? j.total : subtotal;
					$('[data-row-discount]').hidden = d <= 0; $('[data-sum-discount]').textContent = '-' + money(d);
					$('[data-row-credit]').hidden = cr <= 0; $('[data-sum-credit]').textContent = '-' + money(cr);
					$('[data-sum-pay]').textContent = money(pay);
					var m = $('[data-coupon-msg]'); m.textContent = $('[data-coupon]').value.trim() ? (j.message || '') : ''; m.className = j.ok === false ? 'text-late' : 'muted';
					window.APORDER.payable = pay;
					var btn = document.querySelector('[data-submit]');
					if (btn && !btn.disabled) { var later = document.querySelector('[name=pagamento]:checked'); btn.textContent = pay <= 0 ? 'Finalizar pedido' : (later && later.value === 'retirada' ? 'Finalizar pedido' : 'Finalizar e pagar ' + money(pay)); }
				}).catch(function () {});
		}, 250);
	}
	box.addEventListener('input', recalc); box.addEventListener('change', recalc);
	document.addEventListener('ap:total', recalc);
	recalc();
})();
