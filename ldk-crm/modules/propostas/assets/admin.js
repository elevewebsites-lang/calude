/* LDK Propostas · painel */
(function () {
	'use strict';

	/* Copiar link */
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.ldkp-copy');
		if (!btn) return;
		e.preventDefault();
		var url = btn.dataset.url;
		var done = function () {
			var old = btn.textContent;
			btn.textContent = 'Copiado!';
			setTimeout(function () { btn.textContent = old; }, 1600);
		};
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(url).then(done);
		} else {
			var t = document.createElement('textarea');
			t.value = url; document.body.appendChild(t); t.select();
			document.execCommand('copy'); t.remove(); done();
		}
	});

	var builder = document.getElementById('ldkp_builder');
	if (!builder || !window.LDKP_DATA) return;
	var DATA = window.LDKP_DATA;

	var field = function (key) {
		var all = builder.querySelectorAll('[data-key="' + key + '"]');
		for (var i = 0; i < all.length; i++) {
			if (!all[i].closest('.ldkp-plan')) return all[i];
		}
		return null;
	};
	var set = function (key, value, onlyIfEmpty) {
		var el = field(key);
		if (!el || (onlyIfEmpty && el.value.trim())) return;
		el.value = value == null ? '' : value;
		flash(el);
	};
	var flash = function (el) {
		el.classList.remove('ldkp-flash');
		void el.offsetWidth;
		el.classList.add('ldkp-flash');
	};

	/* "Como chamar o cliente" acompanha o nome, até ser editado à mão */
	var client = field('cliente');
	var short = field('cliente_curto');
	if (client && short) {
		var synced = !short.value || short.value === client.value;
		short.addEventListener('input', function () { synced = false; });
		client.addEventListener('input', function () { if (synced) short.value = client.value; });
	}

	/* Máximo de 6 trabalhos */
	builder.addEventListener('change', function (e) {
		if (!e.target.matches('.ldkp-pf input')) return;
		var checked = builder.querySelectorAll('.ldkp-pf input:checked');
		if (checked.length > 6) e.target.checked = false;
	});

	var NICHE_KEYS = ['capa_desc', 'intro_frase', 'cenario', 'oportunidade', 'diag_titulo', 'diagnostico', 'contato_titulo', 'contato_texto'];
	var PLAN_KEYS = ['tag', 'desc', 'prefixo', 'preco_de', 'preco_por', 'pagamento', 'itens', 'cta'];

	document.getElementById('ldkp-autofill').addEventListener('click', function () {
		var nicheId = field('nicho_id').value;
		var niche = DATA.niches[nicheId];
		var services = Array.prototype.map.call(
			builder.querySelectorAll('input[name="ep[servicos][]"]:checked'),
			function (i) { return DATA.services[i.value]; }
		).filter(Boolean);

		if (!client.value.trim()) { alert('Preencha o nome do cliente primeiro.'); client.focus(); return; }
		if (!niche) { alert('Escolha o nicho do cliente.'); field('nicho_id').focus(); return; }
		if (!services.length) { alert('Marque pelo menos um serviço.'); return; }

		var hasText = NICHE_KEYS.concat(['escopo']).some(function (k) { var el = field(k); return el && el.value.trim(); });
		if (hasText && !confirm('Isso vai substituir os textos que já estão preenchidos. Continuar?')) return;

		// Textos do nicho
		NICHE_KEYS.forEach(function (k) { set(k, niche[k]); });

		// Escopo: junta os itens dos serviços marcados, sem repetir título.
		// Itens que aparecem em mais de um serviço (ex.: hospedagem, suporte) vão para o fim.
		var count = {};
		var first = {};
		var order = [];
		services.forEach(function (s) {
			(s.escopo || '').split(/\r?\n/).forEach(function (line) {
				line = line.trim();
				if (!line) return;
				var title = line.split('|')[0].trim().toLowerCase();
				count[title] = (count[title] || 0) + 1;
				if (!first[title]) { first[title] = line; order.push(title); }
			});
		});
		var escopo = order.filter(function (t) { return count[t] === 1; })
			.concat(order.filter(function (t) { return count[t] > 1; }))
			.map(function (t) { return first[t]; });
		set('escopo', escopo.join('\n'));

		// Padrões
		var d = DATA.defaults;
		set('data', d.data, true);
		set('validade', d.validade, true);
		set('responsavel', d.responsavel, true);
		set('passos', d.passos);
		set('invest_badge', d.invest_badge, true);
		set('invest_titulo', d.invest_titulo, true);
		set('invest_nota', d.invest_nota, true);
		set('pos_nota', d.pos_nota, true);

		// Planos: um por serviço (até 3). Com 2 ou mais, o segundo vira destaque.
		builder.querySelectorAll('.ldkp-plan').forEach(function (box, i) {
			var s = services[i];
			var q = function (k) { return box.querySelector('[data-key="' + k + '"]'); };
			q('ativo').checked = !!s;
			if (!s) return;
			q('nome').value = s.title;
			PLAN_KEYS.forEach(function (k) { q(k).value = s[k] || ''; });
			q('estilo').value = services.length >= 2 && i === 1 ? 'destaque' : 'normal';
			box.querySelectorAll('input, textarea, select').forEach(flash);
		});

		// Trabalhos: primeiro os do nicho, depois completa até 6
		var boxes = builder.querySelectorAll('.ldkp-pf input');
		var picked = [];
		DATA.portfolio.forEach(function (nichos, i) {
			if (picked.length < 6 && nichos.indexOf(niche.slug) !== -1) picked.push(i);
		});
		for (var i = 0; picked.length < 6 && i < DATA.portfolio.length; i++) {
			if (picked.indexOf(i) === -1) picked.push(i);
		}
		boxes.forEach(function (b) { b.checked = picked.indexOf(parseInt(b.value, 10)) !== -1; });

		builder.querySelectorAll('details.ldkp-section').forEach(function (s) { s.open = true; });
		var note = document.createElement('div');
		note.className = 'notice notice-success inline ldkp-filled';
		note.innerHTML = '<p><strong>Pronto!</strong> Textos preenchidos. Revise cada seção, personalize o que quiser e clique em <strong>' + (document.querySelector('.pn-form') ? (builder.dataset.editing ? 'Salvar alterações' : 'Gerar proposta') : 'Publicar') + '</strong> para gerar o link.</p>';
		var old = builder.querySelector('.ldkp-filled');
		if (old) old.remove();
		this.parentNode.after(note);
	});
})();
