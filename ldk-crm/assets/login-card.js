/* Login: cartão de identificação, leitor e porta.
   - Ao digitar o e-mail, o cartão mostra nome e função.
   - Ao entrar: o cartão passa no leitor; senha certa = acesso autorizado e a porta abre; senha errada = acesso negado.
   Sem JavaScript (ou se algo falhar), o formulário envia normalmente. */
(function () {
	'use strict';
	var gate = document.querySelector('[data-gate]');
	var form = document.querySelector('[data-login-form]');
	if (!gate || !form || !window.fetch) return;
	var q = function (s) { return gate.querySelector(s); };
	var scene = gate;
	var el = { av: q('[data-g-av]'), name: q('[data-g-name]'), func: q('[data-g-func]'), num: q('[data-g-num]'), screen: q('[data-g-screen]'), hello: q('[data-g-hello]'), role: q('[data-g-role]') };
	var email = form.querySelector('[name="email"]');
	var pass = form.querySelector('[name="senha"]');
	var preview = gate.getAttribute('data-preview') === '1';
	var endpoint = gate.getAttribute('data-endpoint');
	var busy = false, timer = null, cache = {}, reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

	function initials(n) { var p = String(n || '').trim().split(/\s+/); return ((p[0] || '?')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toUpperCase(); }
	function state(s, text) { gate.setAttribute('data-state', s); if (text) el.screen.textContent = text; }
	function card(d) {
		if (!d || !d.name) {
			el.av.textContent = '?'; el.name.textContent = 'Identifique-se'; el.func.textContent = 'Digite o seu e-mail'; el.num.textContent = 'LDK · ····'; gate.removeAttribute('data-kind'); return;
		}
		el.av.textContent = ''; el.av.classList.remove('is-photo', 'is-logo');
		if (d.photo) {
			var im = document.createElement('img'); im.alt = ''; im.src = d.photo;
			im.onerror = function () { el.av.classList.remove('is-photo', 'is-logo'); el.av.textContent = d.emoji || initials(d.name); };
			el.av.appendChild(im); el.av.classList.add(d.photo_is_logo ? 'is-logo' : 'is-photo');
		} else { el.av.textContent = d.emoji || initials(d.name); }
		el.name.textContent = d.name; el.func.textContent = d.role || '';
		el.num.textContent = 'LDK · ' + (d.num || '····');
		gate.setAttribute('data-kind', d.kind || '');
	}

	// 1) Cartão ao digitar o e-mail.
	function lookup() {
		var v = (email.value || '').trim().toLowerCase();
		if (!preview || busy) return;
		if (!/^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/.test(v)) { card(null); return; }
		if (cache[v]) { card(cache[v]); return; }
		fetch(endpoint + (endpoint.indexOf('?') < 0 ? '?' : '&') + 'e=' + encodeURIComponent(v), { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : { found: false }; })
			.then(function (d) { cache[v] = d && d.found ? d : { name: 'Visitante', role: 'Aguardando a senha', num: '····' }; if ((email.value || '').trim().toLowerCase() === v) card(cache[v]); })
			.catch(function () {});
	}
	email.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(lookup, 450); });
	email.addEventListener('blur', lookup);
	if (email.value) lookup();

	// 2) Entrar.
	function showError(msg) {
		var f = form.querySelector('.flash--erro');
		if (!f) { f = document.createElement('div'); f.className = 'flash flash--erro'; var p = form.querySelector('.muted'); (p || form.firstChild).insertAdjacentElement('afterend', f); }
		f.textContent = msg;
	}
	form.addEventListener('submit', function (e) {
		if (busy) { e.preventDefault(); return; }
		e.preventDefault();
		busy = true;
		var t0 = Date.now();
		state('swipe', 'Lendo cartão…');
		var wait = reduce ? 300 : 1250;
		var fd = new FormData(form);
		fetch(location.href, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-LK-Login': '1' } })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				setTimeout(function () { result(d); }, Math.max(0, wait - (Date.now() - t0)));
			})
			.catch(function () { form.submit(); });
	});
	function result(d) {
		if (d && d.ok) {
			if (d.name) card(d);
			state('ok', 'ACESSO AUTORIZADO');
			if (d.need_code) { el.hello.textContent = 'Senha confirmada ✓'; el.role.textContent = 'Falta só o código enviado ao seu e-mail'; }
			else { el.hello.textContent = 'Seja bem-vindo(a), ' + (d.first || d.name || '') + '!'; el.role.textContent = d.role || ''; }
			var wait2 = reduce ? 700 : (d.need_code ? 2200 : 3000);
			setTimeout(function () { var l = gate.querySelector('[data-g-loading]'); if (l) l.hidden = false; }, Math.max(0, wait2 - 700));
			setTimeout(function () { location.href = d.redirect; }, wait2);
		} else {
			state('deny', 'ACESSO NEGADO');
			showError((d && d.msg) || 'E-mail ou senha incorretos.');
			setTimeout(function () { state('idle', 'Aproxime o cartão'); busy = false; if (pass) { pass.value = ''; pass.focus(); } }, reduce ? 800 : 2300);
		}
	}
	state('idle');
})();
