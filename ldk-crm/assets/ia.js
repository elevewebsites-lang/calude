/* LDK · IA: legendas, hashtags, briefing da arte e ideias do mês (usa /lk/v1/ai/*) */
(function () {
	'use strict';
	if (!window.LK) return;
	function $(r, s) { return r.querySelector(s); }
	function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }
	function api(path, data) {
		return fetch(window.LK.rest + path, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': window.LK.nonce, 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
			.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Erro.'); return j; }); });
	}
	function fire(node) { node.dispatchEvent(new Event('input', { bubbles: true })); }
	function addTags(tg, list) {
		var have = (tg.value.match(/#[\p{L}\p{N}_]+/gu) || []).map(function (x) { return x.toLowerCase(); });
		var add = list.map(function (t) { return '#' + String(t).replace(/^#/, '').replace(/[^\p{L}\p{N}_]/gu, ''); }).filter(function (t) { return t.length > 2 && have.indexOf(t.toLowerCase()) < 0; });
		if (add.length) { tg.value = (tg.value.trim() ? tg.value.trim() + ' ' : '') + add.join(' '); fire(tg); }
	}
	function tagChips(box, tg, tags) {
		if (!tags || !tags.length) return;
		box.appendChild(el('p', 'muted small', 'Hashtags (clique para adicionar):'));
		var w = el('div', 'rev-tags');
		tags.forEach(function (t) { var c = el('button', 'rev-tag', '#' + String(t).replace(/^#/, '')); c.type = 'button'; c.onclick = function () { addTags(tg, [t]); c.disabled = true; }; w.appendChild(c); });
		var all = el('button', 'btn btn--ghost btn--sm', 'Adicionar todas'); all.type = 'button'; all.onclick = function () { addTags(tg, tags); }; w.appendChild(all);
		box.appendChild(w);
	}

	/* ---- Botões do post ---- */
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-ai]'); if (!b) return;
		var f = b.closest('form'), mode = b.getAttribute('data-ai'), out = $(f, '[data-ai-out]') || $(f, '[data-review-out]');
		var cap = $(f, '[data-caption]'), tg = $(f, '[data-hashtags]'), notes = $(f, '[name="notes"]');
		var data = { mode: mode, caption: cap ? cap.value : '', idea: ($(f, '[name="idea"]') || {}).value || '', title: ($(f, '[name="title"]') || {}).value || '', format: ($(f, '[name="format"]') || {}).value || '', client: ($(f, '[name="client_id"]') || {}).value || 0, date: ($(f, '[name="date"]') || {}).value || '', tone: ($(f, '[data-ai-tone]') || {}).value || '' };
		if (!data.client) { alert('Escolha o cliente primeiro.'); return; }
		var old = b.textContent; b.disabled = true; b.textContent = 'Pensando…';
		if (out && mode !== 'briefing_arte') out.innerHTML = '<p class="muted small">✨ A IA está escrevendo… (leva alguns segundos)</p>';
		api('ai/caption', data).then(function (r) {
			if (mode === 'briefing_arte') { if (notes) { notes.value = (notes.value.trim() ? notes.value.trim() + '\n\n' : '') + r.text; fire(notes); var d = notes.closest('details'); if (d) d.open = true; } return; }
			out.innerHTML = '';
			if (mode === 'sugerir') {
				(r.options || []).forEach(function (o) {
					var card = el('div', 'ai-opt'); card.appendChild(el('strong', '', o.titulo || 'Opção')); var p = el('p', '', o.legenda || ''); p.style.whiteSpace = 'pre-wrap'; card.appendChild(p);
					var use = el('button', 'btn btn--primary btn--sm', 'Usar esta legenda'); use.type = 'button'; use.onclick = function () { cap.value = o.legenda; fire(cap); use.textContent = '✓ Aplicada (salve o post)'; use.disabled = true; }; card.appendChild(use); out.appendChild(card);
				});
				if (tg) tagChips(out, tg, r.tags);
			} else if (mode === 'melhorar') {
				var card = el('div', 'ai-opt'); card.appendChild(el('strong', '', 'Versão melhorada')); var p2 = el('p', '', r.text); p2.style.whiteSpace = 'pre-wrap'; card.appendChild(p2);
				if (r.note) card.appendChild(el('p', 'muted small', r.note));
				var ap = el('button', 'btn btn--primary btn--sm', 'Aplicar'); ap.type = 'button'; ap.onclick = function () { cap.value = r.text; fire(cap); ap.textContent = '✓ Aplicado (salve o post)'; ap.disabled = true; }; card.appendChild(ap); out.appendChild(card);
			} else if (mode === 'hashtags' && tg) { tagChips(out, tg, r.tags); }
		}).catch(function (err) { if (out) { out.innerHTML = ''; out.appendChild(el('p', 'rev-err', err.message)); } else alert(err.message); })
		  .then(function () { b.disabled = false; b.textContent = old; });
	});

	/* ---- Ideias do mês (planejamento) ---- */
	document.addEventListener('click', function (e) {
		var go = e.target.closest('[data-ai-plan-go]'); if (!go) return;
		var box = go.closest('[data-ai-plan]'), out = $(box, '[data-ai-plan-out]'), ctx = { client: box.getAttribute('data-client'), ym: box.getAttribute('data-ym') };
		var formats = { arte: 'Arte única', carrossel: 'Carrossel', reels: 'Vídeo / Reels', foto: 'Foto', story: 'Story' };
		go.disabled = true; var old = go.textContent; go.textContent = 'Pensando…'; out.innerHTML = '<p class="muted small">✨ Montando as ideias do mês… (pode levar até 30 segundos)</p>';
		api('ai/plan', { client: ctx.client, ym: ctx.ym, qty: $(box, '[data-ai-qty]').value, focus: $(box, '[data-ai-focus]').value }).then(function (r) {
			out.innerHTML = ''; var rows = [];
			(r.items || []).forEach(function (it) {
				var card = el('div', 'ai-idea'), head = el('div', 'ai-idea-h');
				var cb = el('input'); cb.type = 'checkbox'; cb.checked = true;
				var day = el('input', 'ai-day'); day.type = 'number'; day.min = 1; day.max = 31; day.value = it.dia; day.title = 'Dia do mês';
				var fm = el('select'); Object.keys(formats).forEach(function (k) { var o = el('option', '', formats[k]); o.value = k; if (k === it.formato) o.selected = true; fm.appendChild(o); });
				var ti = el('input', 'ai-title'); ti.type = 'text'; ti.value = it.titulo;
				head.appendChild(cb); head.appendChild(el('span', 'muted small', 'dia')); head.appendChild(day); head.appendChild(fm); head.appendChild(ti);
				var idea = el('textarea'); idea.rows = 2; idea.value = it.ideia; var cap = el('textarea'); cap.rows = 4; cap.value = it.legenda;
				var det = el('details'); det.appendChild(el('summary', 'small', 'Ver rascunho da legenda')); det.appendChild(cap);
				card.appendChild(head); card.appendChild(idea); card.appendChild(det); out.appendChild(card);
				rows.push(function () { return cb.checked ? { dia: +day.value, formato: fm.value, titulo: ti.value, ideia: idea.value, legenda: cap.value } : null; });
			});
			if (!rows.length) { out.textContent = 'A IA não trouxe ideias. Tente de novo.'; return; }
			var add = el('button', 'btn btn--primary', 'Adicionar selecionadas ao planejamento'); add.type = 'button';
			add.onclick = function () {
				var items = rows.map(function (f) { return f(); }).filter(Boolean); if (!items.length) { alert('Marque ao menos uma ideia.'); return; }
				add.disabled = true; add.textContent = 'Adicionando…';
				api('ai/plan-add', { client: ctx.client, ym: ctx.ym, items: items }).then(function (res) { location.href = res.url; }).catch(function (er) { alert(er.message); add.disabled = false; add.textContent = 'Adicionar selecionadas ao planejamento'; });
			};
			out.appendChild(add);
		}).catch(function (err) { out.innerHTML = ''; out.appendChild(el('p', 'rev-err', err.message)); }).then(function () { go.disabled = false; go.textContent = old; });
	});
})();
