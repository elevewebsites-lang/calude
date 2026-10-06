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
	// Tabela de preço: do tipo do cliente (se escolhido) ou da audiência da proposta.
	function tier() {
		var sel = $('[name=client_id]', root), t = sel && D.clients ? D.clients[sel.value] : '';
		return t || { final: 'pf', empresa: 'empresa', revenda: 'parceiro' }[audience()] || 'pf';
	}
	function priceOf(row) {
		var m = D.catalog && D.catalog[$('[data-q=material]', row).value];
		if (!m) return null;
		var w = num($('[data-q=w]', row).value), h = num($('[data-q=h]', row).value), p = m.p[tier()] || m.p.parceiro;
		if (m.unit === 'm2') { var a = w * h / 10000; return { unit: Math.round(p * a * 100) / 100, cost: Math.round(m.cost * a * 100) / 100 }; }
		return { unit: p, cost: m.cost };
	}
	function sync(row, force) {
		var mat = $('[data-q=material]', row).value, m = D.catalog && D.catalog[mat];
		$$('[data-sizes]', row).forEach(function (z) { z.hidden = !m || m.unit !== 'm2'; });
		$('input[name="it[kind][]"]', row).value = m ? 'impressao' : 'extra';
		var inc = $('[data-q=inc]', row); if (inc) inc.textContent = m && m.inc ? 'Já incluso: ' + m.inc : '';
		var nm = $('.qitem-name', row);
		if (m && !nm.value) nm.value = m.name;
		if (m && (force || !row.dataset.manual)) {
			var pr = priceOf(row);
			if (pr && (m.unit !== 'm2' || (num($('[data-q=w]', row).value) && num($('[data-q=h]', row).value)))) { $('[data-q=unit]', row).value = fmt(pr.unit); $('[data-q=cost]', row).value = fmt(pr.cost); }
		}
	}

	function render() {
		var sub = 0, cost = 0;
		$$('[data-qitem]', list).forEach(function (row) {
			var q = parseInt($('[data-q=qty]', row).value, 10) || 1;
			var u = num($('[data-q=unit]', row).value), c = num($('[data-q=cost]', row).value);
			sub += u * q; cost += c * q;
			$('[data-q=sum]', row).innerHTML = money(u * q) + (c ? '<small>lucro ' + money((u - c) * q) + '</small>' : '');
			row.classList.toggle('is-extra', !$('[data-q=material]', row).value || $('[data-q=material]', row).value === '0');
		});
		var disc = num(($('[data-q-discount]', root) || {}).value), fr = num(($('[data-q-freight]', root) || {}).value);
		var total = Math.max(0, sub - disc), pix = total * (1 - (D.pixOff || 0) / 100);
		var set = function (k, v) { var el = $('[data-qo="' + k + '"]', root); if (el) el.textContent = v; };
		set('subtotal', money(sub)); set('discount', disc ? '− ' + money(disc) : '—'); set('total', money(total)); set('pix', money(pix));
		set('freight', fr ? money(fr) : 'só retirada'); set('cost', money(cost)); set('profit', money(pix - cost));
		set('margin', pix > 0 ? Math.round((pix - cost) / pix * 100) + '%' : '—');
	}

	function addRow() {
		var first = $('[data-qitem]', list), row = first.cloneNode(true);
		$$('input[type=text], input[type=number], textarea', row).forEach(function (i) { i.value = ''; });
		$('[data-q=qty]', row).value = 1; $('[data-q=material]', row).value = '0'; delete row.dataset.manual;
		var empty = !$('.qitem-name', first).value && !num($('[data-q=unit]', first).value) && $$('[data-qitem]', list).length === 1;
		var target = empty ? first : list.appendChild(row);
		sync(target, true); render();
		return target;
	}

	root.addEventListener('input', function (e) {
		var row = e.target.closest('[data-qitem]');
		if (row) {
			if (e.target.matches('[data-q=unit]')) row.dataset.manual = '1';
			if (e.target.matches('[data-q=w],[data-q=h]')) sync(row, false);
		}
		render();
	});
	root.addEventListener('change', function (e) {
		var row = e.target.closest('[data-qitem]');
		if (row && e.target.matches('[data-q=material]')) { var nm = $('.qitem-name', row), old = nm.value; var prev = nm.dataset.auto; if (!old || old === prev) nm.value = ''; delete row.dataset.manual; sync(row, true); var m = D.catalog[e.target.value]; if (m) nm.dataset.auto = m.name; }
		if (e.target.matches('[name=client_id], [name=audience]')) $$('[data-qitem]', list).forEach(function (r) { sync(r, false); });
		render();
	});
	root.addEventListener('click', function (e) {
		var add = e.target.closest('[data-qitem-add]');
		if (add) { var r = addRow(); $('[data-q=material]', r).focus(); }
		var rp = e.target.closest('[data-qitem-reprice]');
		if (rp) { $$('[data-qitem]', list).forEach(function (r) { delete r.dataset.manual; sync(r, true); }); render(); }
		var del = e.target.closest('[data-qitem-del]');
		if (del) {
			var row = del.closest('[data-qitem]');
			if ($$('[data-qitem]', list).length > 1) row.remove(); else { $$('input[type=text], input[type=number], textarea', row).forEach(function (i) { i.value = i.matches('[data-q=qty]') ? 1 : ''; }); $('[data-q=material]', row).value = '0'; sync(row, false); }
			render();
		}
	});
	$$('[data-qitem]', list).forEach(function (r) { r.dataset.manual = '1'; sync(r, false); });

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
