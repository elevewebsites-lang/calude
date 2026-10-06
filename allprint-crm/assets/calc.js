/* Allprint CRM · calculadora de impressão 3D (mesma conta de includes/pricing.php) */
(function () {
	'use strict';
	var CFG = window.AP_PRICING;
	if (!CFG) return;
	var $ = function (s, c) { return (c || document).querySelector(s); };
	var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
	var num = function (v) { v = String(v == null ? '' : v).trim(); if (/,\d{1,2}$/.test(v) || /\.\d{3}/.test(v)) v = v.replace(/\./g, '').replace(',', '.'); else v = v.replace(',', '.'); var n = parseFloat(v); return isFinite(n) ? n : 0; };
	var money = function (v) { return 'R$ ' + (Math.round(v * 100) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
	var roundPrice = function (v) {
		var end = CFG.round | 0;
		if (end <= 0 || v <= 0) return Math.round(v * 100) / 100;
		var r = Math.floor(v) + end / 100;
		if (r + 0.0001 < v) r += 1;
		return Math.round(r * 100) / 100;
	};
	var tiersFor = function (aud) { return aud === 'revenda' ? CFG.tiersResale : CFG.tiers; };
	var discount = function (q, aud) { var d = 0; tiersFor(aud).forEach(function (t) { if (q >= t[0]) d = t[1]; }); return d; };

	var AP_CALC = window.AP_CALC = function (inp) {
		var perPlate = Math.max(1, inp.perPlate | 0 || 1);
		var hours = Math.max(0, inp.hours || 0);
		var pr = CFG.printers[inp.printer] || CFG.printers[Object.keys(CFG.printers)[0]] || { watts: 95, wear: 0.7 };
		var fil = 0, grams = 0;
		(inp.filaments || []).forEach(function (r) {
			var g = Math.max(0, r[1] || 0);
			var f = CFG.filaments[r[0]];
			fil += g * (f ? f.per_g : 0.1);
			grams += g;
		});
		fil = fil * (1 + CFG.waste / 100) / perPlate;
		var energy = hours * pr.watts / 1000 * CFG.kwh / perPlate;
		var wear = hours * pr.wear / perPlate;
		var fail = Math.min(0.9, Math.max(0, CFG.fail / 100));
		var machine = (fil + energy + wear) / (1 - fail);
		var labor = Math.max(0, inp.laborMin || 0) / 60 * CFG.labor;
		var sup = 0;
		(inp.supplies || []).forEach(function (r) { var s = CFG.supplies[r[0]]; if (s) sup += s.cost * Math.max(0, r[1] || 0); });
		var ext = 0;
		(inp.extras || []).forEach(function (r) { ext += Math.max(0, r[1] || 0); });
		var cost = machine + labor + sup + ext;
		var type = CFG.types[inp.type] || CFG.types[Object.keys(CFG.types)[0]] || ['', 2.5];
		var diff = CFG.difficulty[inp.difficulty] || ['', 0];
		var mult = type[1] * (1 + diff[1] / 100) * (inp.urgent ? 1 + CFG.urgent / 100 : 1);
		var model = Math.max(0, inp.modelHours || 0) * CFG.model;
		var aud = inp.audience || 'final';
		var floor = aud === 'revenda' ? CFG.floorResale : CFG.floor;
		var priceFor = function (q) { var p = cost * mult * (1 - discount(q, aud) / 100); return roundPrice(Math.max(p, cost * floor)); };
		var qty = Math.max(1, inp.qty | 0 || 1);
		var unit = priceFor(qty);
		return {
			gramsUnit: grams / perPlate, hoursUnit: hours / perPlate,
			filament: fil, energy: energy, wear: wear, failExtra: machine - fil - energy - wear, labor: labor, supplies: sup, extras: ext,
			cost: cost, mult: mult, model: model, qty: qty, unit: unit,
			total: Math.max(unit * qty + model, CFG.minimum || 0),
			profit: (unit - cost) * qty + model,
			pix: (unit * qty + model) * (1 - CFG.pixOff / 100),
			resale: aud === 'revenda' ? roundPrice(unit * CFG.resaleMult) : 0,
			tiers: tiersFor(aud).map(function (t) { var u = priceFor(t[0]); return { qty: t[0], unit: u, total: u * t[0] + model, profit: (u - cost) * t[0], off: discount(t[0], aud), resale: aud === 'revenda' ? roundPrice(u * CFG.resaleMult) : 0 }; })
		};
	};

	/* ---------- Tela da calculadora ---------- */
	var form = $('[data-calc]');
	if (!form) return;

	var rowsOf = function (name, idKey, valKey) {
		return $$('[data-rows="' + name + '"] .row-line', form).map(function (line) {
			var id = $('[name$="[' + idKey + '][]"]', line), v = $('[name$="[' + valKey + '][]"]', line);
			return [id ? id.value : '', num(v ? v.value : 0)];
		}).filter(function (r) { return r[0] !== '' && r[1] > 0; });
	};
	var val = function (n) { var el = form.querySelector('[name="c[' + n + ']"]'); return el ? el.value : ''; };
	var checked = function (n) { var el = form.querySelector('[name="c[' + n + ']"]'); return el && el.checked; };

	function read() {
		var extras = $$('[data-rows="ext"] .row-line', form).map(function (line) {
			return [$('[name="c[extra_label][]"]', line).value, num($('[name="c[extra_cost][]"]', line).value)];
		}).filter(function (r) { return r[0] && r[1] > 0; });
		return {
			filaments: rowsOf('fil', 'fil_id', 'fil_g'),
			hours: num(val('hours')) + num(val('minutes')) / 60,
			perPlate: parseInt(val('per_plate'), 10) || 1,
			printer: val('printer'),
			laborMin: num(val('labor_min')),
			modelHours: num(val('model_hours')),
			supplies: rowsOf('sup', 'sup_id', 'sup_qty'),
			extras: extras,
			type: val('type'), difficulty: val('difficulty'), urgent: checked('urgent'),
			qty: parseInt(val('qty'), 10) || 1,
			audience: val('audience')
		};
	}

	var out = $('[data-calc-out]');
	function set(k, v) { var el = out.querySelector('[data-o="' + k + '"]'); if (el) el.textContent = v; }
	function render() {
		var inp = read(), r = AP_CALC(inp);
		set('filament', money(r.filament)); set('energy', money(r.energy)); set('wear', money(r.wear));
		set('fail', money(r.failExtra)); set('labor', money(r.labor)); set('supplies', money(r.supplies)); set('extras', money(r.extras));
		set('cost', money(r.cost)); set('mult', '× ' + r.mult.toFixed(2).replace('.', ','));
		set('unit', money(r.unit)); set('qty', r.qty + ' un.'); set('total', money(r.total)); set('profit', money(r.profit));
		set('pix', money(r.pix)); set('margin', r.total > 0 ? Math.round(r.profit / r.total * 100) + '%' : '—');
		set('grams', (Math.round(r.gramsUnit * 10) / 10).toString().replace('.', ',') + ' g');
		set('hours', (Math.round(r.hoursUnit * 100) / 100).toString().replace('.', ',') + ' h');
		set('model', money(r.model));
		var rs = out.querySelector('[data-o-resale]');
		if (rs) { rs.hidden = !r.resale; set('resale', money(r.resale)); set('resaleProfit', money(r.resale - r.unit) + ' por peça (' + (r.resale > 0 ? Math.round((r.resale - r.unit) / r.resale * 100) : 0) + '%)'); }
		var tb = out.querySelector('[data-o="tiers"]');
		if (tb) {
			tb.innerHTML = r.tiers.map(function (t) {
				return '<tr><td>' + t.qty + '</td><td>' + (t.off ? '−' + String(t.off).replace('.', ',') + '%' : '—') + '</td><td>' + money(t.unit) + '</td><td>' + money(t.total) + '</td><td>' + money(t.profit) + '</td>' + (r.resale ? '<td>' + money(t.resale) + '</td>' : '') + '</tr>';
			}).join('');
			var th = out.querySelector('[data-o="resale-col"]'); if (th) th.hidden = !r.resale;
		}
		var warn = out.querySelector('[data-o="warn"]');
		if (warn) {
			var msgs = [];
			inp.filaments.forEach(function (f) { var s = CFG.filaments[f[0]]; if (s && f[1] * (inp.qty / (inp.perPlate || 1)) > s.left) msgs.push('Pouco ' + s.label + ': restam ' + Math.round(s.left) + ' g.'); });
			if (!inp.filaments.length) msgs.push('Adicione o filamento e as gramas (ou suba o arquivo do fatiador).');
			warn.innerHTML = msgs.map(function (m) { return '<div>' + m + '</div>'; }).join('');
			warn.hidden = !msgs.length;
		}
		var hid = form.querySelector('[name="c[result]"]'); if (hid) hid.value = JSON.stringify({ unit: r.unit, cost: r.cost });
	}
	form.addEventListener('input', render);
	form.addEventListener('change', render);

	// Linhas dinâmicas (filamento, insumo, extra).
	form.addEventListener('click', function (e) {
		var add = e.target.closest('[data-row-add]');
		if (add) {
			var box = $('[data-rows="' + add.getAttribute('data-row-add') + '"]', form);
			var first = box.querySelector('.row-line');
			var copy = first.cloneNode(true);
			$$('input', copy).forEach(function (i) { i.value = ''; });
			$$('select', copy).forEach(function (s) { s.selectedIndex = 0; });
			box.appendChild(copy);
			render();
		}
		var del = e.target.closest('[data-row-del]');
		if (del) {
			var line = del.closest('.row-line'), rows = line.parentNode;
			if (rows.querySelectorAll('.row-line').length > 1) line.remove(); else $$('input', line).forEach(function (i) { i.value = ''; });
			render();
		}
	});
	// Cor do filamento ao lado do select.
	form.addEventListener('change', function (e) {
		if (e.target.matches('[name="c[fil_id][]"]')) {
			var sw = e.target.closest('.row-line').querySelector('.swatch');
			var f = CFG.filaments[e.target.value];
			if (sw) sw.style.background = f ? f.hex : 'transparent';
		}
	});

	// Leitura do fatiador (arquivo ou print).
	var drop = $('[data-slicer]');
	if (drop) {
		var input = drop.querySelector('input[type=file]');
		var status = $('[data-slicer-status]');
		var send = function (file) {
			var fd = new FormData(); fd.append('file', file);
			status.hidden = false; status.className = 'slicer-status'; status.textContent = 'Lendo ' + file.name + '…';
			fetch(window.AP.rest + 'slicer', { method: 'POST', body: fd, headers: { 'X-WP-Nonce': window.AP.nonce }, credentials: 'same-origin' })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (res) {
					if (!res.ok) throw new Error(res.j.message || 'Não consegui ler.');
					var d = res.j;
					form.querySelector('[name="c[hours]"]').value = d.hours;
					form.querySelector('[name="c[minutes]"]').value = d.minutes;
					var box = $('[data-rows="fil"]', form), first = box.querySelector('.row-line');
					$$('.row-line', box).slice(1).forEach(function (l) { l.remove(); });
					(d.filaments.length ? d.filaments : [{ grams: 0 }]).forEach(function (fi, i) {
						var line = i === 0 ? first : box.appendChild(first.cloneNode(true));
						var sel = line.querySelector('select'); sel.value = d.matches && d.matches[i] ? String(d.matches[i]) : '';
						line.querySelector('[name="c[fil_g][]"]').value = String(fi.grams).replace('.', ',');
						var sw = line.querySelector('.swatch'); if (sw) sw.style.background = fi.color && fi.color.charAt(0) === '#' ? fi.color : (CFG.filaments[sel.value] || {}).hex || 'transparent';
						line.setAttribute('title', (fi.type || '') + ' ' + (fi.color || ''));
					});
					var name = form.querySelector('[name="c[name]"]');
					if (name && !name.value) name.value = file.name.replace(/\.(gcode\.)?3mf$|\.gcode$|\.(png|jpe?g|webp)$/i, '').replace(/[_-]+/g, ' ');
					status.className = 'slicer-status is-ok';
					status.textContent = d.source + ': ' + d.hours + 'h' + (d.minutes ? ' ' + d.minutes + 'min' : '') + ' · ' + d.filaments.map(function (f) { return String(f.grams).replace('.', ',') + ' g ' + (f.type || ''); }).join(' + ') + '. Confira o carretel de cada cor.';
					render();
				})
				.catch(function (err) { status.className = 'slicer-status is-err'; status.textContent = err.message; });
		};
		input.addEventListener('change', function () { if (input.files[0]) send(input.files[0]); input.value = ''; });
		['dragover', 'dragenter'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
		['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); }); });
		drop.addEventListener('drop', function (e) { if (e.dataTransfer.files[0]) send(e.dataTransfer.files[0]); });
	}

	// Novo insumo sem sair da tela.
	var supForm = $('[data-supply-quick]');
	if (supForm) {
		supForm.addEventListener('submit', function (e) {
			e.preventDefault();
			var fd = new FormData(supForm); fd.append('ajax', '1');
			fetch(supForm.action, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
				CFG.supplies[j.id] = { label: j.label, unit: j.unit, cost: num(j.cost), per_order: 0 };
				$$('[name="c[sup_id][]"]', form).forEach(function (s) { var o = document.createElement('option'); o.value = j.id; o.textContent = j.label + ' · ' + money(num(j.cost)); s.appendChild(o); });
				var last = $$('[name="c[sup_id][]"]', form).pop(); if (last && !last.value) last.value = j.id;
				supForm.closest('dialog').close(); supForm.reset(); render();
			}).catch(function () { alert('Não consegui salvar o insumo.'); });
		});
	}
	render();
})();
