/* Allprint CRM · editor de orçamento */
(function () {
	'use strict';
	var D = window.AP_QUOTE || { calcs: {}, products: {} };
	var form = document.querySelector('[data-quote]');
	if (!form) return;
	var root = form.closest('form');
	var $ = function (s, c) { return (c || document).querySelector(s); };
	var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
	var num = function (v) { v = String(v == null ? '' : v).trim(); if (/,\d{1,2}$/.test(v) || /\.\d{3}/.test(v)) v = v.replace(/\./g, '').replace(',', '.'); else v = v.replace(',', '.'); var n = parseFloat(v); return isFinite(n) ? n : 0; };
	var money = function (v) { return 'R$ ' + (Math.round(v * 100) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
	var fmt = function (v) { return v ? (Math.round(v * 100) / 100).toFixed(2).replace('.', ',') : ''; };
	var list = $('[data-qitems]', form);

	function audience() { var r = $('[name=audience]:checked', root); return r ? r.value : 'final'; }

	function render() {
		var sub = 0, cost = 0, isResale = audience() === 'revenda';
		$$('[data-qitem]', list).forEach(function (row) {
			var q = parseInt($('[data-q=qty]', row).value, 10) || 1;
			var u = num($('[data-q=unit]', row).value), c = num($('[data-q=cost]', row).value);
			sub += u * q; cost += c * q;
			var sum = $('[data-q=sum]', row);
			var r = num($('[data-q=resale]', row).value);
			sum.innerHTML = money(u * q) + (c ? '<small>lucro ' + money((u - c) * q) + '</small>' : '') + (isResale && r > u ? '<small>revenda: ' + money(r) + ' (' + Math.round((r - u) / r * 100) + '% p/ a loja)</small>' : '');
			row.classList.toggle('is-extra', $('.qitem-kind', row).value === 'extra');
		});
		root.classList.toggle('is-resale', isResale);
		var disc = num(($('[data-q-discount]', root) || {}).value), fr = num(($('[data-q-freight]', root) || {}).value);
		var total = Math.max(0, sub - disc), pix = total * (1 - (D.pixOff || 0) / 100);
		var set = function (k, v) { var el = $('[data-qo="' + k + '"]', root); if (el) el.textContent = v; };
		set('subtotal', money(sub)); set('discount', disc ? '− ' + money(disc) : '—'); set('total', money(total)); set('pix', money(pix));
		set('freight', fr ? money(fr) : 'só retirada'); set('cost', money(cost)); set('profit', money(pix - cost));
		set('margin', pix > 0 ? Math.round((pix - cost) / pix * 100) + '%' : '—');
	}

	function addRow(kind, data) {
		var first = $('[data-qitem]', list);
		var row = first.cloneNode(true);
		$$('input, textarea', row).forEach(function (i) { i.value = ''; });
		$('[data-q=qty]', row).value = 1;
		$('.qitem-kind', row).value = kind || 'impressao';
		// Se a primeira linha está vazia, usa ela.
		var target = !$('.qitem-name', first).value && !num($('[data-q=unit]', first).value) && $$('[data-qitem]', list).length === 1 ? first : list.appendChild(row);
		if (data) {
			$('.qitem-name', target).value = data.name || '';
			if (data.desc) $('textarea', target).value = data.desc;
			$('[data-q=qty]', target).value = data.qty || 1;
			$('[data-q=unit]', target).value = fmt(data.unit);
			$('[data-q=cost]', target).value = fmt(data.cost);
			$('[data-q=resale]', target).value = fmt(data.resale || (audience() === 'revenda' ? data.unit * (D.resaleMult || 2) : 0));
			$('[data-q=calc]', target).value = data.calc || 0;
			$('[data-q=grams]', target).value = data.grams || 0;
			$('[data-q=hours]', target).value = data.hours || 0;
		}
		render();
		return target;
	}

	root.addEventListener('input', render);
	root.addEventListener('change', render);
	root.addEventListener('click', function (e) {
		var add = e.target.closest('[data-qitem-add]');
		if (add) { var r = addRow(add.getAttribute('data-qitem-add')); $('.qitem-name', r).focus(); }
		var del = e.target.closest('[data-qitem-del]');
		if (del) {
			var row = del.closest('[data-qitem]');
			if ($$('[data-qitem]', list).length > 1) row.remove(); else $$('input, textarea', row).forEach(function (i) { i.value = i.matches('[data-q=qty]') ? 1 : ''; });
			render();
		}
	});
	// Revenda: sugere o preço de revenda quando o preço unitário muda e o campo está vazio.
	list.addEventListener('change', function (e) {
		if (e.target.matches('[data-q=unit]') && audience() === 'revenda') {
			var row = e.target.closest('[data-qitem]'), rs = $('[data-q=resale]', row);
			if (!num(rs.value)) rs.value = fmt(num(e.target.value) * (D.resaleMult || 2));
			render();
		}
	});
	document.addEventListener('click', function (e) {
		var pc = e.target.closest('[data-pick-calc]');
		if (pc) {
			var c = D.calcs[pc.getAttribute('data-pick-calc')];
			if (c) addRow('impressao', { name: c.name, qty: c.qty, unit: c.unit, cost: c.cost, resale: c.resale, calc: pc.getAttribute('data-pick-calc'), grams: c.grams, hours: c.hours });
			pc.closest('dialog').close();
		}
		var pp = e.target.closest('[data-pick-product]');
		if (pp) {
			var p = D.products[pp.getAttribute('data-pick-product')];
			if (p) addRow('impressao', { name: p.name, desc: p.desc, qty: 1, unit: p.unit, cost: p.cost, grams: p.grams, hours: p.hours });
			pp.closest('dialog').close();
		}
	});

	// Frete (Melhor Envio).
	var ff = document.querySelector('[data-freight]');
	if (ff) {
		ff.addEventListener('submit', function (e) {
			e.preventDefault();
			var out = $('[data-freight-out]', ff);
			out.innerHTML = '<p class="muted small">Cotando…</p>';
			var qs = 'cep=' + encodeURIComponent($('[name=f_cep]', ff).value) + '&peso=' + encodeURIComponent($('[name=f_peso]', ff).value || 300) + '&caixa=' + encodeURIComponent($('[name=f_caixa]', ff).value) + '&valor=' + encodeURIComponent(num(($('[data-qo=total]', root) || {}).textContent.replace('R$', '')));
			fetch(window.AP.rest + 'frete?' + qs, { headers: { 'X-WP-Nonce': window.AP.nonce }, credentials: 'same-origin' })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (res) {
					if (!res.ok) throw new Error(res.j.message || 'Erro na cotação.');
					if (!res.j.options.length) { out.innerHTML = '<p class="muted small">Nenhuma transportadora atende esse CEP com essa caixa.</p>'; return; }
					out.innerHTML = res.j.options.map(function (o, i) { return '<button type="button" class="pick" data-fr="' + i + '"><strong>' + o.label + '</strong><small>' + money(o.price) + ' · ' + o.days + ' dias úteis</small></button>'; }).join('');
					$$('[data-fr]', out).forEach(function (b) {
						b.addEventListener('click', function () {
							var o = res.j.options[b.getAttribute('data-fr')];
							$('[data-q-freight]', root).value = fmt(o.price);
							root.querySelector('[name=freight_label]').value = o.label + ' · ' + o.days + ' dias úteis';
							ff.closest('dialog').close(); render();
						});
					});
				})
				.catch(function (err) { out.innerHTML = '<p class="text-late small">' + err.message + '</p>'; });
		});
	}
	render();
})();
