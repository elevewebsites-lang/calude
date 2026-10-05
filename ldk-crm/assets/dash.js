/* Dashboard: botão "Atualizar" + atualização sozinha a cada 3 min.
   Busca a própria página e troca só os blocos marcados com data-dash (números, esteira e cartões).
   O chat da equipe do dashboard não é recriado (continua funcionando sem perder o que está sendo digitado). */
(function () {
	var btn = document.querySelector('[data-dash-refresh]');
	var grid = document.querySelector('[data-dash-grid]');
	var stamp = document.querySelector('[data-dash-stamp]');
	if (!btn || !grid) return;
	var busy = false, last = Date.now(), EVERY = 3 * 60 * 1000;

	function hhmm(d) { return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
	function mark(text) { if (stamp) stamp.textContent = text; }
	mark('Atualizado às ' + hhmm(new Date()));

	function refresh(manual) {
		if (busy || document.body.classList.contains('is-arranging')) return;
		busy = true;
		btn.classList.add('is-loading');
		btn.disabled = true;
		fetch(location.href, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'lk-dash' } })
			.then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
			.then(function (html) {
				var doc = new DOMParser().parseFromString(html, 'text/html');
				var newGrid = doc.querySelector('[data-dash-grid]');
				if (!newGrid) throw new Error('sessao');
				// Números e esteira.
				document.querySelectorAll('[data-dash]').forEach(function (el) {
					var fresh = doc.querySelector('[data-dash="' + el.getAttribute('data-dash') + '"]');
					if (fresh) el.replaceWith(document.importNode(fresh, true));
				});
				// Cartões: monta de novo na ordem nova, mas mantém o chat vivo.
				var liveChat = grid.querySelector('[data-dw="chat"]');
				var list = [];
				Array.prototype.forEach.call(newGrid.children, function (c) {
					if (c.getAttribute('data-dw') === 'chat' && liveChat) { ['style', 'class', 'data-w', 'data-h'].forEach(function (a) { var v = c.getAttribute(a); if (v !== null) liveChat.setAttribute(a, v); }); list.push(liveChat); } else { list.push(document.importNode(c, true)); }
				});
				grid.replaceChildren.apply(grid, list);
				last = Date.now();
				mark('Atualizado às ' + hhmm(new Date()));
				if (manual) toast('Dashboard atualizado.');
			})
			.catch(function (e) {
				if (manual) toast(e.message === 'sessao' ? 'Sua sessão expirou. Recarregue a página para entrar de novo.' : 'Não foi possível atualizar agora. Tente de novo.');
			})
			.then(function () { busy = false; btn.classList.remove('is-loading'); btn.disabled = false; });
	}

	function toast(msg) {
		var t = document.createElement('div');
		t.className = 'dash-toast';
		t.setAttribute('role', 'status');
		t.textContent = msg;
		document.body.appendChild(t);
		setTimeout(function () { t.classList.add('is-out'); }, 2200);
		setTimeout(function () { t.remove(); }, 2700);
	}

	btn.addEventListener('click', function () { refresh(true); });
	// Sozinho: a cada 3 min com a aba visível, e ao voltar para a aba depois de 3 min fora.
	setInterval(function () { if (!document.hidden) refresh(false); }, EVERY);
	document.addEventListener('visibilitychange', function () {
		if (!document.hidden && Date.now() - last > EVERY) refresh(false);
	});
})();
