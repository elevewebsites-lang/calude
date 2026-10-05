/* LDK · revisão do texto antes de postar + hashtags (usa /lk/v1/review) */
(function () {
	'use strict';
	var MAXC = 2200, MAXH = 30;
	function $(f, s) { return f.querySelector(s); }
	function tagsOf(t) { var m = (t || '').match(/#[\p{L}\p{N}_]+/gu) || []; return m.map(function (x) { return x.toLowerCase(); }).filter(function (x, i, a) { return a.indexOf(x) === i; }); }
	function counts(f) {
		var cap = $(f, '[data-caption]'), tg = $(f, '[data-hashtags]'), out = $(f, '[data-tag-count]');
		if (!cap || !tg || !out) return;
		var all = cap.value + (tg.value.trim() ? '\n\n' + tg.value.trim() : ''), n = tagsOf(all).length;
		out.textContent = all.length + '/' + MAXC + ' caracteres · ' + n + '/' + MAXH + ' hashtags';
		out.style.color = (all.length > MAXC || n > MAXH) ? '#b42318' : '';
	}
	function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }
	function addTags(tg, list) {
		var have = tagsOf(tg.value), add = list.filter(function (t) { return have.indexOf(t.toLowerCase()) < 0; });
		if (add.length) { tg.value = (tg.value.trim() ? tg.value.trim() + ' ' : '') + add.join(' '); tg.dispatchEvent(new Event('input', { bubbles: true })); }
	}
	function render(f, res, box) {
		var cap = $(f, '[data-caption]'), tg = $(f, '[data-hashtags]');
		box.innerHTML = '';
		if ((res.rules || []).length) {
			var h = el('strong', '', 'Regras do Instagram'); box.appendChild(h);
			var ul = el('ul', 'rev-list');
			res.rules.forEach(function (r) { ul.appendChild(el('li', r[0] === 'erro' ? 'rev-err' : 'rev-warn', (r[0] === 'erro' ? '⛔ ' : '⚠️ ') + r[1])); });
			box.appendChild(ul);
		}
		if (res.ai) {
			box.appendChild(el('strong', '', 'Revisão de português e contexto'));
			if (!res.ai.issues.length) box.appendChild(el('p', 'rev-ok', '✅ Nenhum erro encontrado.'));
			var ul2 = el('ul', 'rev-list');
			res.ai.issues.forEach(function (i) {
				var li = el('li', 'rev-warn');
				li.appendChild(el('b', '', '[' + (i.tipo || 'revisão') + '] '));
				if (i.trecho) li.appendChild(el('code', '', i.trecho));
				li.appendChild(document.createTextNode(' ' + i.problema + (i.sugestao ? ' → ' : '')));
				if (i.sugestao) li.appendChild(el('em', '', i.sugestao));
				ul2.appendChild(li);
			});
			box.appendChild(ul2);
			if (res.ai.corrected && cap && res.ai.corrected.trim() !== cap.value.trim()) {
				var b = el('button', 'btn btn--primary btn--sm', '✍️ Aplicar texto corrigido'); b.type = 'button';
				b.onclick = function () { cap.value = res.ai.corrected; cap.dispatchEvent(new Event('input', { bubbles: true })); b.textContent = '✓ Aplicado (salve o post)'; b.disabled = true; };
				box.appendChild(b);
			}
			if ((res.ai.tags || []).length && tg) {
				box.appendChild(el('p', 'muted small', 'Hashtags sugeridas (clique para adicionar):'));
				var wrap = el('div', 'rev-tags');
				res.ai.tags.forEach(function (t) { var c = el('button', 'rev-tag', t); c.type = 'button'; c.onclick = function () { addTags(tg, [t]); c.disabled = true; }; wrap.appendChild(c); });
				var all = el('button', 'btn btn--ghost btn--sm', 'Adicionar todas'); all.type = 'button'; all.onclick = function () { addTags(tg, res.ai.tags); };
				wrap.appendChild(all); box.appendChild(wrap);
			}
		}
		if (res.ai_note) box.appendChild(el('p', 'muted small', res.ai_note));
		if (!(res.rules || []).length && res.ai && !res.ai.issues.length) box.insertBefore(el('p', 'rev-ok', '✅ Tudo certo com as regras do Instagram.'), box.firstChild);
	}
	document.addEventListener('input', function (e) { var f = e.target.closest('form'); if (f && e.target.matches('[data-caption],[data-hashtags]')) counts(f); });
	document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('form').forEach(counts); });
	document.addEventListener('click', function (e) {
		var f, b = e.target.closest('[data-client-tags]');
		if (b) {
			f = b.closest('form'); var map = {}, tg = $(f, '[data-hashtags]'), cid = ($(f, '[name="client_id"]') || {}).value;
			try { map = JSON.parse($(f, '[data-tags-map]').textContent); } catch (x) {}
			var list = (map[cid] || '').match(/#[\p{L}\p{N}_]+/gu) || [];
			if (!cid) alert('Escolha o cliente primeiro.'); else if (!list.length) alert('Esse cliente ainda não tem hashtags padrão. Cadastre na ficha dele.'); else addTags(tg, list);
			return;
		}
		b = e.target.closest('[data-review]');
		if (!b) return;
		f = b.closest('form'); var box = $(f, '[data-review-out]');
		if (!window.LK) { alert('Recarregue a página.'); return; }
		b.disabled = true; var old = b.textContent; b.textContent = 'Revisando…'; box.innerHTML = '<p class="muted small">Conferindo regras, português e contexto…</p>';
		fetch(window.LK.rest + 'review', { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': window.LK.nonce, 'Content-Type': 'application/json' }, body: JSON.stringify({ post: ($(f, '[name="id"]') || {}).value || 0, caption: ($(f, '[data-caption]') || {}).value || '', hashtags: ($(f, '[data-hashtags]') || {}).value || '', client: ($(f, '[name="client_id"]') || {}).value || 0, date: ($(f, '[name="date"]') || {}).value || '' }) })
			.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Erro.'); return j; }); })
			.then(function (res) { render(f, res, box); })
			.catch(function (err) { box.innerHTML = ''; box.appendChild(el('p', 'rev-err', 'Não consegui revisar: ' + err.message)); })
			.then(function () { b.disabled = false; b.textContent = old; });
	});
})();
