/* Apontamentos: clicar num ponto da tela e escrever o que precisa mudar. Os pontos ficam numerados na tela. */
(function () {
	'use strict';
	var CFG = window.AP, FB = window.AP_FB;
	if (!CFG || !FB) return;
	var hdr = { 'X-WP-Nonce': CFG.nonce };
	var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
	var api = function (path, opt) {
		opt = opt || {};
		opt.credentials = 'same-origin';
		opt.headers = Object.assign({ 'Content-Type': 'application/json' }, hdr, opt.headers || {});
		return fetch(CFG.rest + path, opt).then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Não foi possível salvar.'); return j; }); });
	};
	var KEY = 'ap-fb-hide';
	var hidden = false; try { hidden = localStorage.getItem(KEY) === '1'; } catch (e) {}
	var items = [], mode = false, draft = null, openId = 0;

	/* ---------- interface ---------- */
	var ui = document.createElement('div');
	ui.className = 'fb';
	ui.setAttribute('data-fb', '');
	ui.innerHTML =
		'<div class="fb-bar" data-fb-bar>' +
			'<button type="button" class="fb-btn" data-fb-start title="Marcar um ponto da tela e escrever o que precisa mudar"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg><span>Apontar</span><em class="fb-n" data-fb-n hidden></em></button>' +
			'<button type="button" class="fb-eye" data-fb-eye title="Mostrar ou esconder os pontos desta tela" aria-label="Mostrar ou esconder os pontos"></button>' +
			(FB.list ? '<a class="fb-eye fb-list" href="' + esc(FB.list) + '" title="Ver todos os apontamentos" aria-label="Ver todos os apontamentos"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg></a>' : '') +
		'</div>' +
		'<div class="fb-tip" data-fb-tip hidden role="status"><strong>Clique no ponto da tela</strong> que você quer comentar. <button type="button" data-fb-cancel>Cancelar (Esc)</button></div>' +
		'<div class="fb-hl" data-fb-hl hidden></div>' +
		'<div class="fb-layer" data-fb-layer></div>' +
		'<div class="fb-pop" data-fb-pop hidden role="dialog" aria-label="Apontamento"></div>' +
		'<div class="fb-toast" data-fb-toast hidden role="status"></div>';
	document.body.appendChild(ui);
	var $ = function (s) { return ui.querySelector(s); };
	var layer = $('[data-fb-layer]'), pop = $('[data-fb-pop]'), hl = $('[data-fb-hl]'), tip = $('[data-fb-tip]'), eye = $('[data-fb-eye]');

	function drawEye() {
		eye.innerHTML = hidden
			? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17.94 17.94A10 10 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9 9 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M1 1l22 22"/></svg>'
			: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
		layer.hidden = hidden;
		eye.classList.toggle('is-off', hidden);
	}
	drawEye();

	var toastT = 0;
	function toast(msg) {
		var t = $('[data-fb-toast]');
		t.textContent = msg; t.hidden = false;
		clearTimeout(toastT); toastT = setTimeout(function () { t.hidden = true; }, 3800);
	}

	/* ---------- onde fica o ponto: elemento (seletor) + posição dentro dele; reserva: posição na página ---------- */
	function cssPath(el) {
		var parts = [];
		while (el && el.nodeType === 1 && el !== document.body && el !== document.documentElement) {
			// id fixo (sem números sorteados) encurta o caminho
			if (el.id && !/\d{3,}/.test(el.id) && document.querySelectorAll('#' + CSS.escape(el.id)).length === 1) { parts.unshift('#' + CSS.escape(el.id)); return parts.join(' > '); }
			var i = 1, sib = el;
			while ((sib = sib.previousElementSibling)) { if (sib.tagName === el.tagName) i++; }
			parts.unshift(el.tagName.toLowerCase() + ':nth-of-type(' + i + ')');
			el = el.parentElement;
		}
		return 'body > ' + parts.join(' > ');
	}
	function find(sel) { try { return sel ? document.querySelector(sel) : null; } catch (e) { return null; } }
	function docH() { return Math.max(document.documentElement.scrollHeight, document.body.scrollHeight); }
	// Posição do ponto na janela (px), ou null se o elemento estiver escondido (outra aba, janela fechada…).
	function where(it) {
		var el = find(it.sel);
		if (el) {
			var r = el.getBoundingClientRect();
			if (!r.width && !r.height) return null;
			return { x: r.left + r.width * it.ox, y: r.top + r.height * it.oy };
		}
		return { x: it.px * window.innerWidth, y: it.py - window.scrollY };
	}

	/* ---------- pontos na tela ---------- */
	function place() {
		layer.querySelectorAll('[data-fb-pin]').forEach(function (pin) {
			var it = pin._it, p = where(it);
			pin.hidden = !p;
			if (p) { pin.style.transform = 'translate(' + Math.round(p.x) + 'px,' + Math.round(p.y) + 'px)'; }
		});
		if (!pop.hidden && pop._pin) aim(pop._pin);
	}
	var raf = 0;
	function soon() { if (!raf) raf = requestAnimationFrame(function () { raf = 0; place(); }); }
	window.addEventListener('scroll', soon, true);
	window.addEventListener('resize', soon);
	setInterval(place, 1200); // conteúdo que carrega depois (listas, abas)

	function drawPins() {
		layer.innerHTML = '';
		items.forEach(function (it, i) {
			var b = document.createElement('button');
			b.type = 'button'; b.className = 'fb-pin' + (it.id === openId ? ' is-on' : '');
			b.setAttribute('data-fb-pin', it.id);
			b.setAttribute('aria-label', 'Apontamento ' + (i + 1) + ': ' + it.body.slice(0, 60));
			b.innerHTML = '<span><b>' + (i + 1) + '</b></span>';
			b._it = it;
			layer.appendChild(b);
		});
		place();
		var n = $('[data-fb-n]'); n.textContent = items.length; n.hidden = !items.length;
	}

	function load() {
		return api('feedback?path=' + encodeURIComponent(location.pathname)).then(function (j) { items = j.items || []; drawPins(); }).catch(function () {});
	}

	/* ---------- janelinha do ponto ---------- */
	function aim(anchor) {
		var r = anchor.getBoundingClientRect ? anchor.getBoundingClientRect() : anchor;
		var w = pop.offsetWidth || 320, h = pop.offsetHeight || 200, gap = 14;
		if (window.innerWidth < 600) { pop.style.left = ''; pop.style.top = ''; pop.classList.add('is-sheet'); return; }
		pop.classList.remove('is-sheet');
		var x = r.left + (r.width || 0) + gap, y = r.top - 12;
		if (x + w > window.innerWidth - 12) x = r.left - w - gap;
		x = Math.max(12, Math.min(x, window.innerWidth - w - 12));
		y = Math.max(12, Math.min(y, window.innerHeight - h - 12));
		pop.style.left = x + 'px'; pop.style.top = y + 'px';
	}
	function closePop() {
		pop.hidden = true; pop._pin = null; openId = 0;
		layer.querySelectorAll('.fb-pin.is-on').forEach(function (p) { p.classList.remove('is-on'); });
		if (draft) { draft.remove(); draft = null; }
	}
	function showItem(it, pin) {
		if (draft) { draft.remove(); draft = null; }
		openId = it.id; pop._pin = pin;
		layer.querySelectorAll('.fb-pin').forEach(function (p) { p.classList.toggle('is-on', p === pin); });
		var n = items.indexOf(it) + 1;
		pop.innerHTML =
			'<div class="fb-pop-h"><strong>Apontamento ' + n + '</strong><small>#' + it.id + ' · ' + esc(it.who) + ' · ' + esc(it.at) + ' · ' + esc(it.device) + '</small><button type="button" class="fb-x" data-fb-close aria-label="Fechar">×</button></div>' +
			'<div class="fb-pop-b" data-fb-text>' + esc(it.body).replace(/\n/g, '<br>') + '</div>' +
			(it.reply ? '<div class="fb-reply"><small>Resposta</small>' + esc(it.reply).replace(/\n/g, '<br>') + '</div>' : '') +
			(FB.team ? '<textarea rows="2" data-fb-reply placeholder="Responder (quem apontou vê a resposta)">' + esc(it.reply) + '</textarea>' : '') +
			'<div class="fb-acts">' +
				(it.mine ? '<button type="button" class="fb-a fb-a--del" data-fb-del>Excluir</button><button type="button" class="fb-a" data-fb-edit>Editar</button>' : '') +
				(FB.team ? '<button type="button" class="fb-a" data-fb-savereply>Salvar resposta</button><button type="button" class="fb-a fb-a--ok" data-fb-done>✓ Resolvido</button>' : '') +
			'</div>';
		pop.hidden = false; aim(pin);
	}
	function newDraft(x, y, target) {
		closePop();
		var r = target.getBoundingClientRect();
		draft = document.createElement('div');
		draft.className = 'fb-pin fb-pin--draft is-on';
		draft.innerHTML = '<span><b>+</b></span>';
		draft.style.transform = 'translate(' + Math.round(x) + 'px,' + Math.round(y) + 'px)';
		layer.hidden = false;
		layer.appendChild(draft);
		var snippet = (target.innerText || target.value || target.getAttribute('aria-label') || target.getAttribute('placeholder') || target.alt || '').replace(/\s+/g, ' ').trim().slice(0, 120);
		draft._data = {
			url: location.pathname + location.search,
			page: document.title,
			sel: cssPath(target),
			snippet: snippet,
			ox: r.width ? (x - r.left) / r.width : 0,
			oy: r.height ? (y - r.top) / r.height : 0,
			px: x / window.innerWidth,
			py: Math.round(y + window.scrollY),
			vw: window.innerWidth
		};
		pop.innerHTML =
			'<div class="fb-pop-h"><strong>Novo apontamento</strong><small>' + (snippet ? 'Em: “' + esc(snippet.slice(0, 50)) + (snippet.length > 50 ? '…' : '') + '”' : 'Neste ponto da tela') + '</small><button type="button" class="fb-x" data-fb-close aria-label="Fechar">×</button></div>' +
			'<textarea rows="4" data-fb-body placeholder="O que precisa mudar aqui? Ex.: trocar o texto para…, este botão não funciona…, a cor ficou…"></textarea>' +
			'<div class="fb-acts"><button type="button" class="fb-a" data-fb-close>Cancelar</button><button type="button" class="fb-a fb-a--main" data-fb-save>Salvar apontamento</button></div>';
		pop._pin = draft; pop.hidden = false; aim(draft);
		setTimeout(function () { var ta = pop.querySelector('[data-fb-body]'); if (ta) ta.focus(); }, 30);
	}

	/* ---------- modo "apontar" ---------- */
	function setMode(on) {
		mode = on;
		document.documentElement.classList.toggle('fb-mode', on);
		tip.hidden = !on; hl.hidden = true;
		if (on) { closePop(); if (hidden) { hidden = false; drawEye(); } }
	}
	function isOurs(el) { return el && el.closest && el.closest('[data-fb]'); }
	document.addEventListener('mousemove', function (e) {
		if (!mode) return;
		var el = document.elementFromPoint(e.clientX, e.clientY);
		if (!el || isOurs(el)) { hl.hidden = true; return; }
		var r = el.getBoundingClientRect();
		hl.hidden = false;
		hl.style.transform = 'translate(' + r.left + 'px,' + r.top + 'px)'; hl.style.width = r.width + 'px'; hl.style.height = r.height + 'px';
	}, true);
	// Durante o modo, o clique não aciona links nem botões da tela: só marca o ponto.
	['pointerdown', 'mousedown', 'mouseup', 'click'].forEach(function (ev) {
		document.addEventListener(ev, function (e) {
			if (!mode || isOurs(e.target)) return;
			e.preventDefault(); e.stopPropagation();
			if (ev !== 'click') return;
			var el = document.elementFromPoint(e.clientX, e.clientY) || e.target;
			setMode(false);
			newDraft(e.clientX, e.clientY, el);
		}, true);
	});
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') return;
		if (mode) { setMode(false); return; }
		if (!pop.hidden) closePop();
	});

	/* ---------- cliques na nossa interface ---------- */
	ui.addEventListener('click', function (e) {
		var t = e.target;
		if (t.closest('[data-fb-start]')) { setMode(!mode); return; }
		if (t.closest('[data-fb-cancel]')) { setMode(false); return; }
		if (t.closest('[data-fb-eye]')) { hidden = !hidden; try { localStorage.setItem(KEY, hidden ? '1' : '0'); } catch (err) {} drawEye(); if (hidden) closePop(); return; }
		if (t.closest('[data-fb-close]')) { closePop(); return; }
		var pin = t.closest('[data-fb-pin]');
		if (pin && pin._it) { if (openId === pin._it.id) closePop(); else showItem(pin._it, pin); return; }
		var it = items.filter(function (x) { return x.id === openId; })[0];
		if (t.closest('[data-fb-save]')) { save(t.closest('[data-fb-save]')); return; }
		if (!it) return;
		if (t.closest('[data-fb-done]')) {
			api('feedback/' + it.id, { method: 'POST', body: JSON.stringify({ status: 'resolvido', reply: (pop.querySelector('[data-fb-reply]') || {}).value }) }).then(function (j) {
				items = items.filter(function (x) { return x.id !== it.id; }); closePop(); drawPins(); badge(j.open); toast('Apontamento #' + it.id + ' resolvido.');
			}).catch(function (err) { alert(err.message); });
			return;
		}
		if (t.closest('[data-fb-savereply]')) {
			api('feedback/' + it.id, { method: 'POST', body: JSON.stringify({ reply: pop.querySelector('[data-fb-reply]').value }) }).then(function (j) {
				Object.assign(it, j.item); showItem(it, pop._pin); toast('Resposta salva.');
			}).catch(function (err) { alert(err.message); });
			return;
		}
		if (t.closest('[data-fb-edit]')) {
			var box = pop.querySelector('[data-fb-text]');
			box.outerHTML = '<textarea rows="4" data-fb-newbody>' + esc(it.body) + '</textarea>';
			t.closest('[data-fb-edit]').outerHTML = '<button type="button" class="fb-a fb-a--main" data-fb-saveedit>Salvar</button>';
			pop.querySelector('[data-fb-newbody]').focus();
			return;
		}
		if (t.closest('[data-fb-saveedit]')) {
			api('feedback/' + it.id, { method: 'POST', body: JSON.stringify({ body: pop.querySelector('[data-fb-newbody]').value }) }).then(function (j) {
				Object.assign(it, j.item); showItem(it, pop._pin); toast('Apontamento atualizado.');
			}).catch(function (err) { alert(err.message); });
			return;
		}
		if (t.closest('[data-fb-del]')) {
			if (!confirm('Excluir este apontamento?')) return;
			api('feedback/' + it.id, { method: 'DELETE' }).then(function (j) {
				items = items.filter(function (x) { return x.id !== it.id; }); closePop(); drawPins(); badge(j.open); toast('Apontamento excluído.');
			}).catch(function (err) { alert(err.message); });
		}
	});
	pop.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { var b = pop.querySelector('[data-fb-save],[data-fb-saveedit],[data-fb-savereply]'); if (b) b.click(); }
	});

	function save(btn) {
		if (!draft) return;
		var body = pop.querySelector('[data-fb-body]').value.trim();
		if (!body) { pop.querySelector('[data-fb-body]').focus(); return; }
		btn.disabled = true; btn.textContent = 'Salvando…';
		var data = Object.assign({ body: body }, draft._data);
		api('feedback', { method: 'POST', body: JSON.stringify(data) }).then(function (j) {
			draft.remove(); draft = null; pop.hidden = true;
			items.push(j.item); drawPins(); badge(j.open);
			toast('Apontamento salvo (' + items.length + ' nesta tela). Obrigado!');
		}).catch(function (err) { btn.disabled = false; btn.textContent = 'Salvar apontamento'; alert(err.message); });
	}

	// Contador do menu lateral (equipe).
	function badge(n) {
		if (!FB.team || n == null) return;
		document.querySelectorAll('[data-fb-count]').forEach(function (b) { b.textContent = n; b.hidden = !n; });
	}

	/* ---------- abrir já num ponto (?apontamento=ID, vindo da lista) ---------- */
	load().then(function () {
		if (!FB.focus) return;
		var it = items.filter(function (x) { return x.id === FB.focus; })[0];
		if (!it) { toast('Esse apontamento já foi resolvido ou não está nesta tela.'); return; }
		var el = find(it.sel);
		if (el) el.scrollIntoView({ block: 'center', behavior: 'smooth' });
		else window.scrollTo({ top: Math.max(0, it.py - window.innerHeight / 2), behavior: 'smooth' });
		setTimeout(function () {
			place();
			var pin = layer.querySelector('[data-fb-pin="' + it.id + '"]');
			if (pin) { pin.classList.add('is-pulse'); showItem(it, pin); }
		}, 600);
	});
})();
