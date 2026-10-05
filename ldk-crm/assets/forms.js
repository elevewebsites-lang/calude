/* Briefings e pesquisas: montador (painel) e resposta passo a passo (cliente). */
(function () {
	'use strict';
	function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }

	/* ---------- Montador ---------- */
	var root = document.querySelector('[data-fb]');
	if (root) {
		var form = root.closest('form');
		var out = form.querySelector('[data-fb-out]');
		var state, types;
		try { state = JSON.parse(document.querySelector('[data-fb-init]').textContent); types = JSON.parse(document.querySelector('[data-fb-types]').textContent); } catch (e) { state = { steps: [] }; types = {}; }
		if (!state.steps || !state.steps.length) state = { steps: [{ title: 'Etapa 1', questions: [] }] };
		var blank = function () { return { type: 'short', label: '', options: [], required: true, other: false }; };
		var move = function (arr, i, d) { var j = i + d; if (j < 0 || j >= arr.length) return; var t = arr[i]; arr[i] = arr[j]; arr[j] = t; render(); };
		var btn = function (txt, title, fn, cls) { var b = el('button', cls || 'fb-ic', txt); b.type = 'button'; b.title = title; b.onclick = fn; return b; };

		var render = function () {
			root.innerHTML = '';
			state.steps.forEach(function (st, si) {
				var card = el('section', 'card fb-step');
				var head = el('div', 'fb-step-h');
				head.appendChild(el('span', 'fb-step-n', 'Etapa ' + (si + 1)));
				var ti = el('input'); ti.type = 'text'; ti.value = st.title || ''; ti.placeholder = 'Nome da etapa (ex.: Sobre a marca)'; ti.oninput = function () { st.title = ti.value; };
				head.appendChild(ti);
				var tools = el('span', 'fb-tools');
				tools.appendChild(btn('↑', 'Subir etapa', function () { move(state.steps, si, -1); }));
				tools.appendChild(btn('↓', 'Descer etapa', function () { move(state.steps, si, 1); }));
				tools.appendChild(btn('×', 'Excluir etapa', function () { if (confirm('Excluir esta etapa e as perguntas dela?')) { state.steps.splice(si, 1); if (!state.steps.length) state.steps.push({ title: 'Etapa 1', questions: [] }); render(); } }, 'fb-ic fb-ic--del'));
				head.appendChild(tools);
				card.appendChild(head);

				st.questions.forEach(function (q, qi) {
					var row = el('div', 'fb-q');
					var top = el('div', 'fb-q-top');
					var sel = el('select');
					Object.keys(types).forEach(function (k) { var o = el('option', '', types[k]); o.value = k; if (k === q.type) o.selected = true; sel.appendChild(o); });
					sel.onchange = function () { q.type = sel.value; if (q.type !== 'choice' && q.type !== 'multi') { q.other = false; } render(); };
					var lab = el('input'); lab.type = 'text'; lab.value = q.label || ''; lab.placeholder = 'Escreva a pergunta'; lab.oninput = function () { q.label = lab.value; };
					top.appendChild(sel); top.appendChild(lab);
					var qt = el('span', 'fb-tools');
					qt.appendChild(btn('↑', 'Subir pergunta', function () { move(st.questions, qi, -1); }));
					qt.appendChild(btn('↓', 'Descer pergunta', function () { move(st.questions, qi, 1); }));
					qt.appendChild(btn('⧉', 'Duplicar pergunta', function () { st.questions.splice(qi + 1, 0, JSON.parse(JSON.stringify(q))); render(); }));
					qt.appendChild(btn('×', 'Excluir pergunta', function () { st.questions.splice(qi, 1); render(); }, 'fb-ic fb-ic--del'));
					top.appendChild(qt);
					row.appendChild(top);

					if (q.type === 'choice' || q.type === 'multi') {
						var ta = el('textarea'); ta.rows = 4; ta.placeholder = 'Uma opção por linha'; ta.value = (q.options || []).join('\n');
						ta.oninput = function () { q.options = ta.value.split('\n').map(function (x) { return x.trim(); }).filter(Boolean); };
						row.appendChild(ta);
					} else if (q.type === 'sat') {
						row.appendChild(el('p', 'muted small', 'Opções fixas: Muito satisfeito · Satisfeito · Neutro · Insatisfeito · Muito insatisfeito (entram no resultado da pesquisa).'));
					} else if (q.type === 'nps') {
						row.appendChild(el('p', 'muted small', 'Nota de 0 a 10. O painel calcula o NPS (promotores − detratores).'));
					}
					var opts = el('div', 'fb-q-opts');
					var l1 = el('label', 'check'), c1 = el('input'); c1.type = 'checkbox'; c1.checked = !!q.required; c1.onchange = function () { q.required = c1.checked; }; l1.appendChild(c1); l1.appendChild(el('span', '', 'Obrigatória'));
					opts.appendChild(l1);
					if (q.type === 'choice' || q.type === 'multi') {
						var l2 = el('label', 'check'), c2 = el('input'); c2.type = 'checkbox'; c2.checked = !!q.other; c2.onchange = function () { q.other = c2.checked; }; l2.appendChild(c2); l2.appendChild(el('span', '', 'Incluir "Outro: …"'));
						opts.appendChild(l2);
					}
					row.appendChild(opts);
					card.appendChild(row);
				});
				var add = btn('+ Pergunta', 'Adicionar pergunta', function () { st.questions.push(blank()); render(); var ins = root.querySelectorAll('.fb-step')[si].querySelectorAll('.fb-q'); var last = ins[ins.length - 1]; if (last) last.querySelector('input[type=text]').focus(); }, 'btn btn--ghost btn--sm');
				card.appendChild(add);
				root.appendChild(card);
			});
			var addStep = btn('+ Adicionar etapa', 'Nova etapa', function () { state.steps.push({ title: 'Etapa ' + (state.steps.length + 1), questions: [blank()] }); render(); window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }); }, 'btn btn--ghost');
			root.appendChild(addStep);
		};
		render();
		form.addEventListener('submit', function (e) {
			var n = 0;
			state.steps.forEach(function (s) { s.questions = s.questions.filter(function (q) { return (q.label || '').trim(); }); n += s.questions.length; });
			state.steps = state.steps.filter(function (s) { return s.questions.length; });
			if (!n) { e.preventDefault(); alert('Adicione pelo menos uma pergunta com texto.'); render(); return; }
			var bad = 0;
			state.steps.forEach(function (s) { s.questions.forEach(function (q) { if ((q.type === 'choice' || q.type === 'multi') && (q.options || []).length < 1 && !q.other) bad++; }); });
			if (bad) { e.preventDefault(); alert('Há pergunta de múltipla escolha sem opções. Escreva uma opção por linha.'); return; }
			out.value = JSON.stringify(state);
		});
	}

	/* ---------- Resposta passo a passo (cliente) ---------- */
	var fs = document.querySelector('.fs-form');
	if (fs && window.LK_FS) {
		var steps = Array.prototype.slice.call(fs.querySelectorAll('[data-fs-step]'));
		var idx = 0, total = steps.length;
		var prev = fs.querySelector('[data-fs-prev]'), next = fs.querySelector('[data-fs-next]'), send = fs.querySelector('[data-fs-send]');
		var lbl = fs.querySelector('[data-fs-label]'), bar = fs.querySelector('[data-fs-bar]'), err = fs.querySelector('[data-fs-err]');
		var KEY = window.LK_FS.key;
		var save = function () {
			try { var d = {}; Array.prototype.forEach.call(fs.elements, function (e) { if (!e.name || e.name === 'id' || e.name === 'action' || e.name === 'do' || e.name === '_wpnonce' || e.name === '_wp_http_referer') return; if (e.type === 'checkbox' || e.type === 'radio') { if (e.checked) (d[e.name] = d[e.name] || []).push(e.value); } else d[e.name] = [e.value]; }); localStorage.setItem(KEY, JSON.stringify({ d: d, i: idx })); } catch (x) {}
		};
		var restore = function () {
			try {
				var s = JSON.parse(localStorage.getItem(KEY) || 'null'); if (!s) return;
				Array.prototype.forEach.call(fs.elements, function (e) { var v = s.d[e.name]; if (!v) return; if (e.type === 'checkbox' || e.type === 'radio') e.checked = v.indexOf(e.value) >= 0; else if (e.type !== 'hidden') e.value = v[0]; });
				idx = Math.min(total - 1, s.i || 0);
			} catch (x) {}
		};
		var show = function () {
			steps.forEach(function (s, i) { s.hidden = i !== idx; });
			lbl.textContent = 'Etapa ' + (idx + 1) + ' de ' + total + ' · ' + (steps[idx].querySelector('.fs-title') || {}).textContent;
			bar.style.width = Math.round(100 * (idx + 1) / total) + '%';
			prev.hidden = idx === 0; next.hidden = idx === total - 1; send.hidden = idx !== total - 1;
			err.hidden = true;
			var top = fs.getBoundingClientRect().top + window.scrollY - 70; window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
			save();
		};
		var validate = function (i) {
			var bad = [];
			Array.prototype.forEach.call(steps[i].querySelectorAll('.fs-q'), function (q) {
				q.classList.remove('is-bad');
				if (q.dataset.req !== '1') return;
				var t = q.dataset.type, ok = true;
				if (t === 'short' || t === 'long') ok = q.querySelector('input,textarea').value.trim() !== '';
				else {
					var ch = q.querySelectorAll('input:checked');
					ok = ch.length > 0;
					Array.prototype.forEach.call(ch, function (c) { if (c.value === '__outro') { var o = q.querySelector('input[type=text]'); if (!o || !o.value.trim()) ok = false; } });
				}
				if (!ok) { q.classList.add('is-bad'); bad.push(q.querySelector('legend').textContent.replace('*', '').trim()); }
			});
			if (bad.length) { err.textContent = 'Falta responder: ' + bad.slice(0, 2).join('; ') + (bad.length > 2 ? '…' : ''); err.hidden = false; return false; }
			err.hidden = true; return true;
		};
		next.addEventListener('click', function () { if (validate(idx)) { idx++; show(); } });
		prev.addEventListener('click', function () { idx = Math.max(0, idx - 1); show(); });
		fs.addEventListener('change', save); fs.addEventListener('input', save);
		fs.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type === 'text') { e.preventDefault(); if (idx < total - 1) next.click(); } });
		fs.addEventListener('submit', function (e) {
			for (var i = 0; i < total; i++) { if (!validate(i)) { e.preventDefault(); idx = i; show(); validate(i); return; } }
			try { localStorage.removeItem(KEY); } catch (x) {}
		});
		restore(); show();
		// "Outro": marcar a opção ao escrever.
		fs.addEventListener('input', function (e) { if (e.target.type === 'text' && e.target.closest('.fs-opt--other')) { var c = e.target.closest('.fs-opt--other').querySelector('input:not([type=text])'); if (c && e.target.value) c.checked = true; } });
	}
})();
