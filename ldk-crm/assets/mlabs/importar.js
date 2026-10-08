/* Subir o PDF do mLabs: lê no navegador (pdf.js), reconhece indicadores, gráficos e tabelas, recorta as miniaturas dos posts
 * e prepara tudo para o CRM montar o relatório. Nada do PDF vai para fora do sistema. */
(function () {
	var root = document.querySelector('[data-mlabs]');
	if (!root || !window.pdfjsLib) return;
	var base = root.getAttribute('data-base');
	pdfjsLib.GlobalWorkerOptions.workerSrc = base + 'pdf.worker.min.js';
	var input = root.querySelector('[data-mlabs-file]'), status = root.querySelector('[data-mlabs-status]'), preview = root.querySelector('[data-mlabs-preview]');
	var payload = root.querySelector('[data-mlabs-payload]'), thumbsField = root.querySelector('[data-mlabs-thumbs]'), period = root.querySelector('[data-mlabs-period]');
	var submit = root.querySelector('[data-mlabs-submit]'), clientSel = root.querySelector('select[name="client_id"]');
	var NET = { instagram: 'Instagram', facebook: 'Facebook', linkedin: 'LinkedIn', youtube: 'YouTube' };

	function say(msg, err) { status.textContent = msg; status.className = 'small ' + (err ? 'text-late' : 'muted'); }
	function norm(s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]/g, ''); }
	function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

	function describe(s) {
		var n = NET[s.network] || s.network, t = s.title || '';
		switch (s.type) {
			case 'kpis': return n + ' · indicadores (' + s.items.length + ')';
			case 'funnel': return n + ' · funil (' + s.steps.length + ' etapas)';
			case 'text': return n + ' · texto do período';
			case 'line': return n + ' · gráfico: ' + (t || 'linha') + ' (' + s.labels.length + ' pontos)';
			case 'bars': return n + ' · gráfico de barras: ' + (t || '');
			case 'pie': return n + ' · pizza: ' + (t || '');
			case 'table': return n + ' · tabela: ' + (t || '') + ' (' + s.rows.length + ' linhas)';
		}
		return n + ' · ' + s.type;
	}

	async function cropThumbs(doc, result) {
		var refs = [];
		result.sections.forEach(function (s) { if (s.type === 'table') s.rows.forEach(function (r) { if (r.thumb) refs.push(r); }); });
		var byPage = {};
		refs.forEach(function (r) { (byPage[r.thumb.page] = byPage[r.thumb.page] || []).push(r); });
		var out = [];
		var scale = 3;
		for (var pg in byPage) {
			var page = await doc.getPage(parseInt(pg, 10)), vp = page.getViewport({ scale: scale });
			var cv = document.createElement('canvas');
			cv.width = Math.ceil(vp.width); cv.height = Math.ceil(vp.height);
			await page.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise;
			byPage[pg].forEach(function (r) {
				var t = r.thumb, w = Math.max(1, Math.round(t.w * scale)), h = Math.max(1, Math.round(t.h * scale));
				var c2 = document.createElement('canvas'), k = Math.min(1, 180 / w);
				c2.width = Math.round(w * k); c2.height = Math.round(h * k);
				c2.getContext('2d').drawImage(cv, Math.round(t.x * scale), Math.round(t.y * scale), w, h, 0, 0, c2.width, c2.height);
				out.push(c2.toDataURL('image/jpeg', 0.82));
				r.thumb = out.length - 1;
			});
		}
		refs.forEach(function (r) { if (typeof r.thumb !== 'number') r.thumb = null; });
		return out;
	}

	async function process(file) {
		submit.disabled = true;
		preview.innerHTML = '';
		if (!file) return;
		if (!/\.pdf$/i.test(file.name)) { say('Envie o relatório em PDF.', true); return; }
		say('Lendo o PDF…');
		try {
			var buf = await file.arrayBuffer();
			var doc = await pdfjsLib.getDocument({ data: buf }).promise, pages = [];
			for (var i = 1; i <= doc.numPages; i++) {
				say('Lendo página ' + i + ' de ' + doc.numPages + '…');
				pages.push(await LKPdfPrims.pagePrims(pdfjsLib, await doc.getPage(i)));
			}
			var result = LKMlabsParse.parse(pages);
			if (!result.sections.length) { say('Não reconheci este PDF como um relatório do mLabs. Confira se é o relatório exportado (não a capa sozinha).', true); return; }
			say('Recortando as miniaturas dos posts…');
			var thumbs = await cropThumbs(doc, result);
			payload.value = JSON.stringify(result);
			thumbsField.value = JSON.stringify(thumbs);
			var ym = result.meta.from ? result.meta.from.slice(0, 7) : '';
			period.value = ym;
			if (clientSel && !clientSel.value && result.meta.client) {
				var want = norm(result.meta.client);
				[].forEach.call(clientSel.options, function (o) { var n = norm(o.textContent); if (n && want && (n === want || n.indexOf(want) >= 0 || want.indexOf(n) >= 0)) clientSel.value = o.value; });
			}
			var html = '<p><strong>' + esc(result.meta.title || file.name) + '</strong>' + (result.meta.from ? ' · período ' + esc(result.meta.from.split('-').reverse().join('/')) + ' a ' + esc(result.meta.to.split('-').reverse().join('/')) : '') + '</p><ul class="mini-list">';
			result.sections.forEach(function (s) { html += '<li><span>' + esc(describe(s)) + '</span><em class="badge badge--ok">lido</em></li>'; });
			preview.innerHTML = html + '</ul>';
			say(result.sections.length + ' partes reconhecidas. Escolha o cliente e crie o relatório.');
			submit.disabled = false;
		} catch (e) {
			console.error(e);
			say('Não consegui ler este PDF (' + (e && e.message ? e.message : 'erro') + ').', true);
		}
	}
	input.addEventListener('change', function () { process(input.files[0]); });
})();
