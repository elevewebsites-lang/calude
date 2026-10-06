/* AllPrint · pedido manual: tabela do cliente, desconto, cupom, crédito e forma de pagamento */
(function () {
	'use strict';
	var form = document.querySelector('form.mo');
	if (!form || !window.AP_CAT) return;
	var $ = function (s, c) { return (c || form).querySelector(s); };
	var money = function (v) { return 'R$ ' + (Math.round(v * 100) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
	var num = function (v) { return parseFloat(String(v || '').replace(/\./g, '').replace(',', '.')) || 0; };
	var tier = $('[data-tier]'), nomin = $('[data-nomin]'), sel = $('[data-client]');
	AP_CAT.tier = function () { return tier.value; };
	AP_CAT.noMin = function () { return !nomin.checked; };
	var sinalTouched = false, last = { total: 0, payable: 0 }, timer;

	function clientChanged() {
		var c = (window.AP_CLIENTS || {})[sel.value];
		$('[data-newclient]').hidden = !!c;
		var info = $('[data-client-info]');
		if (c) {
			tier.value = c.tier;
			var bits = [c.kind || 'Tipo não informado', c.phone || '', c.later ? 'pode pagar na retirada' : ''].filter(Boolean);
			info.textContent = bits.join(' · '); info.hidden = false;
			$('[data-credit-row]').hidden = !(c.credit > 0);
			$('[data-credit-balance]').textContent = money(c.credit);
		} else { info.hidden = true; $('[data-credit-row]').hidden = true; $('[data-usecredit]').checked = false; }
		recalc();
	}

	function payFields() {
		var mode = $('[name=pagamento]:checked').value;
		$('[data-sinal-row]').hidden = mode !== 'sinal';
		$('[name=metodo]').closest('label').hidden = (mode === 'retirada' || mode === 'pendente');
		$('[name=data_pagamento]').closest('label').hidden = (mode === 'retirada' || mode === 'pendente');
		if (mode === 'sinal' && !sinalTouched) $('[data-sinal]').value = (last.payable / 2).toFixed(2).replace('.', ',');
	}

	function recalc() {
		var subtotal = (window.APORDER && APORDER.total) || 0;
		clearTimeout(timer);
		timer = setTimeout(function () {
			var body = { subtotal: subtotal, code: $('[data-coupon]').value.trim(), use_credit: $('[data-usecredit]').checked, manual_discount: num($('[data-discount]').value), client_id: sel.value || 0 };
			fetch(window.AP.rest + 'coupon', { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': window.AP.nonce, 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
				.then(function (r) { return r.json(); })
				.then(function (j) { apply(subtotal, j); })
				.catch(function () { apply(subtotal, { discount: num($('[data-discount]').value), credit_used: 0, total: Math.max(0, subtotal - num($('[data-discount]').value)), message: '' }); });
		}, 220);
	}

	function apply(subtotal, j) {
		var d = j.discount || 0, cr = j.credit_used || 0, pay = j.total != null ? j.total : Math.max(0, subtotal - d - cr);
		last = { total: subtotal, payable: pay };
		$('[data-row-discount]').hidden = d <= 0; $('[data-sum-discount]').textContent = '-' + money(d);
		$('[data-discount-label]').textContent = $('[data-coupon]').value.trim() && j.ok ? '(cupom ' + $('[data-coupon]').value.trim().toUpperCase() + ')' : '';
		$('[data-row-credit]').hidden = cr <= 0; $('[data-sum-credit]').textContent = '-' + money(cr);
		$('[data-sum-pay]').textContent = money(pay);
		var msg = $('[data-coupon-msg]'); msg.textContent = $('[data-coupon]').value.trim() ? (j.message || '') : ''; msg.className = j.ok === false ? 'text-late' : 'muted';
		payFields();
	}

	sel.addEventListener('change', clientChanged);
	tier.addEventListener('change', function () { form.dispatchEvent(new Event('input', { bubbles: true })); });
	['[data-discount]', '[data-coupon]', '[data-usecredit]'].forEach(function (s) { $(s).addEventListener('input', recalc); $(s).addEventListener('change', recalc); });
	$('[data-sinal]').addEventListener('input', function () { sinalTouched = true; });
	Array.prototype.forEach.call(form.querySelectorAll('[name=pagamento]'), function (r) { r.addEventListener('change', payFields); });
	document.addEventListener('ap:total', recalc);
	form.addEventListener('submit', function (e) {
		if (!sel.value && !$('[name=new_company]').value.trim() && !$('[name=new_name]').value.trim()) { e.preventDefault(); alert('Escolha o cliente ou preencha o nome do novo cliente.'); }
	});
	clientChanged(); payFields();
})();
