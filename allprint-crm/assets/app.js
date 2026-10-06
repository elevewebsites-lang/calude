/* Allprint CRM · interações do painel e da área do cliente */
(function () {
	'use strict';

	var CFG = window.AP || {};
	var $ = function (sel, root) { return (root || document).querySelector(sel); };
	var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

	/* ---------- Chamadas à API ---------- */

	function api(path, method, body) {
		var opts = { method: method || 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': CFG.nonce } };
		if (body) {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify(body);
		}
		return fetch(CFG.rest + path, opts).then(function (r) {
			return r.json().then(function (data) {
				if (!r.ok) throw new Error((data && data.message) || 'Erro ' + r.status);
				return data;
			});
		});
	}

	/* ---------- Tema claro / escuro ---------- */

	var themeNames = { claro: 'Claro', escuro: 'Escuro', sistema: 'Sistema' };
	var themeGet = function () { try { return localStorage.getItem('ap-theme') || 'claro'; } catch (e) { return 'claro'; } };
	var themeApply = function (t) {
		var dark = t === 'escuro' || (t === 'sistema' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
		document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
		$$('[data-theme-label]').forEach(function (el) { el.textContent = themeNames[t] || ''; });
	};
	if (document.documentElement.hasAttribute('data-theme')) {
		themeApply(themeGet());
		$$('[data-theme-toggle]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var order = ['claro', 'escuro', 'sistema'];
				var next = order[(order.indexOf(themeGet()) + 1) % order.length];
				try { localStorage.setItem('ap-theme', next); } catch (e) {}
				themeApply(next);
			});
		});
		if (window.matchMedia) {
			var mq = window.matchMedia('(prefers-color-scheme: dark)');
			var onMq = function () { if (themeGet() === 'sistema') themeApply('sistema'); };
			if (mq.addEventListener) mq.addEventListener('change', onMq);
		}
	}

	/* ---------- Hospedagem vence junto com o domínio (padrão) ---------- */

	$$('[data-same-date]').forEach(function (box) {
		var form = box.closest('form');
		var host = $('[data-host-end]', form), dom = $('[data-domain-end]', form);
		if (!host || !dom) return;
		var sync = function () {
			if (box.checked && dom.value) host.value = dom.value;
			host.readOnly = box.checked;
			host.closest('.field').classList.toggle('is-locked', box.checked);
		};
		box.addEventListener('change', sync);
		dom.addEventListener('input', sync);
		dom.addEventListener('change', sync);
		sync();
	});

	/* ---------- Ideias: texto longo, menu e botão ocupado ---------- */

	$$('[data-idea-body]').forEach(function (body) {
		var more = body.nextElementSibling;
		if (!more || !more.hasAttribute('data-idea-more')) return;
		if (body.scrollHeight > body.clientHeight + 4) {
			more.hidden = false;
			more.addEventListener('click', function () {
				var open = body.classList.toggle('is-open');
				more.textContent = open ? 'Ver menos' : 'Ver tudo';
			});
		}
	});
	document.addEventListener('click', function (e) {
		$$('details.menu-more[open]').forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute('open'); });
	});
	document.addEventListener('submit', function (e) {
		var btn = e.target.querySelector('[data-busy]');
		if (btn) { btn.disabled = true; btn.textContent = btn.getAttribute('data-busy'); }
	});

	/* ---------- Modo foco: escolher o que fazer ---------- */

	var fpick = $('[data-focus-pick]');
	if (fpick) {
		var fcount = $('[data-focus-count]', fpick), fgo = $('[data-focus-go]', fpick), ffilter = $('[data-focus-filter]', fpick);
		var fupdate = function () {
			var nw = $('[data-focus-new]', fpick);
			var n = $$('input[name="itens[]"]:checked', fpick).length + (nw ? nw.value.split('\n').filter(function (l) { return l.trim(); }).length : 0);
			fcount.textContent = n ? n + (n === 1 ? ' item escolhido' : ' itens escolhidos') : 'Nada escolhido';
			fgo.disabled = !n;
		};
		fpick.addEventListener('change', fupdate);
		fpick.addEventListener('input', fupdate);
		if (ffilter) ffilter.addEventListener('input', function () {
			var q = ffilter.value.trim().toLowerCase();
			$$('[data-focus-item]', fpick).forEach(function (row) { row.hidden = q && row.textContent.toLowerCase().indexOf(q) === -1; });
		});
		fupdate();
	}

	/* ---------- Olhinho: esconder valores em R$ ---------- */

	var moneyRe = /R\$\s?-?[\d.]+(?:,\d{1,2})?/g;
	var moneyOrig = new WeakMap();
	var moneyObserver = null;
	var maskIn = function (root) {
		var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
		var nodes = [];
		while (walker.nextNode()) nodes.push(walker.currentNode);
		nodes.forEach(function (n) {
			if (!moneyRe.test(n.nodeValue)) return;
			moneyRe.lastIndex = 0;
			if (!moneyOrig.has(n)) moneyOrig.set(n, n.nodeValue);
			n.nodeValue = n.nodeValue.replace(moneyRe, 'R$ •••••');
			n.parentNode && n.parentNode.classList && n.parentNode.classList.add('is-masked');
		});
	};
	var unmaskIn = function (root) {
		var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
		while (walker.nextNode()) {
			var n = walker.currentNode;
			if (moneyOrig.has(n)) {
				n.nodeValue = moneyOrig.get(n);
				moneyOrig.delete(n);
				n.parentNode && n.parentNode.classList && n.parentNode.classList.remove('is-masked');
			}
		}
	};
	var moneyRoots = function () { return $$('.content, .search, dialog'); };
	var moneyApply = function (hide) {
		var html = document.documentElement;
		if (hide) {
			html.setAttribute('data-hide-money', '');
			moneyRoots().forEach(maskIn);
			if (!moneyObserver && window.MutationObserver) {
				moneyObserver = new MutationObserver(function (list) {
					list.forEach(function (m) { m.addedNodes.forEach(function (node) { if (node.nodeType === 1) maskIn(node); else if (node.nodeType === 3 && node.parentNode) maskIn(node.parentNode); }); });
				});
				moneyRoots().forEach(function (r) { moneyObserver.observe(r, { childList: true, subtree: true }); });
			}
		} else {
			html.removeAttribute('data-hide-money');
			if (moneyObserver) { moneyObserver.disconnect(); moneyObserver = null; }
			moneyRoots().forEach(unmaskIn);
		}
		html.setAttribute('data-money-ready', '');
		$$('[data-money-toggle]').forEach(function (b) { b.classList.toggle('is-hidden-money', hide); b.setAttribute('aria-pressed', hide ? 'true' : 'false'); });
	};
	if (document.body.classList.contains('ap-panel')) {
		var moneyHidden = false;
		try { moneyHidden = localStorage.getItem('ap-hide-money') === '1'; } catch (e) {}
		moneyApply(moneyHidden);
		$$('[data-money-toggle]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				moneyHidden = !moneyHidden;
				try { localStorage.setItem('ap-hide-money', moneyHidden ? '1' : '0'); } catch (e) {}
				moneyApply(moneyHidden);
			});
		});
	}

	/* ---------- Avisos rápidos ---------- */

	function toast(msg, type) {
		var el = document.createElement('div');
		el.className = 'toast toast--' + (type || 'ok');
		el.textContent = msg;
		document.body.appendChild(el);
		requestAnimationFrame(function () { el.classList.add('is-in'); });
		setTimeout(function () {
			el.classList.remove('is-in');
			setTimeout(function () { el.remove(); }, 300);
		}, 2600);
	}

	var flash = $('.flash');
	if (flash && !flash.closest('.auth-form')) {
		setTimeout(function () { flash.classList.add('is-out'); }, 4500);
	}

	/* ---------- Menu lateral (celular) ---------- */

	document.addEventListener('click', function (e) {
		if (e.target.closest('[data-side-open]')) document.body.classList.add('side-open');
		if (e.target.closest('[data-side-close]')) document.body.classList.remove('side-open');
	});

	/* ---------- Janelas (dialog) ---------- */

	document.addEventListener('click', function (e) {
		var opener = e.target.closest('[data-open]');
		if (opener) {
			// Numa linha clicável, botões e formulários internos têm prioridade.
			var inner = e.target.closest('button, a, form, input, select, textarea, label');
			if (!(opener.matches('button') || !inner || inner === opener || !opener.contains(inner))) return;
			var dlg = document.getElementById(opener.getAttribute('data-open'));
			if (dlg && dlg.showModal) {
				e.preventDefault();
				dlg.showModal();
				var first = $('input:not([type=hidden]), textarea, select', dlg);
				if (first) setTimeout(function () { first.focus(); }, 50);
			}
			return;
		}
		if (e.target.closest('[data-close]')) {
			var d = e.target.closest('dialog');
			if (d) d.close();
		}
	});
	$$('dialog.modal').forEach(function (d) {
		d.addEventListener('click', function (e) { if (e.target === d) d.close(); });
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && e.target.matches('[data-open][role=button]')) e.target.click();
	});

	/* ---------- Confirmar antes de excluir ---------- */

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-confirm]');
		if (btn && !window.confirm(btn.getAttribute('data-confirm'))) {
			e.preventDefault();
			e.stopPropagation();
		}
	}, true);

	/* ---------- Copiar ---------- */

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-copy]');
		if (!btn) return;
		e.preventDefault();
		var text = btn.getAttribute('data-copy');
		var done = function () { toast('Copiado!'); };
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done);
		} else {
			var t = document.createElement('textarea');
			t.value = text; document.body.appendChild(t); t.select();
			document.execCommand('copy'); t.remove(); done();
		}
	});

	/* ---------- Abas ---------- */

	$$('[data-tabs]').forEach(function (nav) {
		var links = $$('a:not([data-external])', nav);
		var show = function (hash, push) {
			var target = links.filter(function (a) { return a.getAttribute('href') === hash; })[0] || links[0];
			links.forEach(function (a) {
				var on = a === target;
				a.classList.toggle('is-active', on);
				var panel = document.getElementById(a.getAttribute('href').slice(1));
				if (panel) panel.hidden = !on;
			});
			if (push) history.replaceState(null, '', target.getAttribute('href'));
		};
		links.forEach(function (a) {
			a.addEventListener('click', function (e) { e.preventDefault(); show(a.getAttribute('href'), true); });
		});
		show(location.hash, false);
	});

	/* ---------- Checklist: concluir etapa ---------- */

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-toggle-task]');
		if (!btn) return;
		e.preventDefault();
		var item = btn.closest('.check-item');
		var id = btn.getAttribute('data-toggle-task');
		btn.disabled = true;
		api('task/' + id + '/toggle', 'POST').then(function (res) {
			if (btn.hasAttribute('data-reload')) return location.reload();
			if (item) item.classList.toggle('is-done', res.done);
			btn.classList.toggle('is-done', res.done);
			if (res.progress) updateProgress(res.progress);
			updateGroupCount(item);
		}).catch(function (err) { toast(err.message, 'erro'); }).then(function () { btn.disabled = false; });
	});

	function updateProgress(p) {
		$$('.ring').forEach(function (r) { r.style.setProperty('--p', p.pct); });
		$$('[data-progress-pct]').forEach(function (el) { el.textContent = p.pct + '%'; });
		$$('[data-progress-done]').forEach(function (el) { el.textContent = p.done; });
	}

	function updateGroupCount(item) {
		var group = item && item.closest('.group');
		if (!group) return;
		var all = $$('.check-item', group).length;
		var done = $$('.check-item.is-done', group).length;
		var count = $('.group-count', group);
		if (count) count.textContent = done + '/' + all;
	}

	/* ---------- Marcadores rápidos (cliente vê) e aprovação ---------- */

	document.addEventListener('click', function (e) {
		var flag = e.target.closest('[data-flag]');
		if (flag) {
			e.preventDefault();
			api('task/' + flag.getAttribute('data-task') + '/flag', 'POST', { flag: flag.getAttribute('data-flag') })
				.then(function (res) {
					flag.classList.toggle('is-on', !!res.value);
					toast(res.value ? 'O cliente passa a ver esta etapa.' : 'Etapa escondida do cliente.');
				}).catch(function (err) { toast(err.message, 'erro'); });
			return;
		}
		var review = e.target.closest('[data-send-review]');
		if (review) {
			e.preventDefault();
			review.disabled = true;
			api('task/' + review.getAttribute('data-send-review') + '/review', 'POST')
				.then(function () { toast('Enviado para o cliente aprovar.'); setTimeout(function () { location.reload(); }, 700); })
				.catch(function (err) { review.disabled = false; toast(err.message, 'erro'); });
		}
	});

	/* ---------- Revelar senha ---------- */

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-reveal]');
		if (!btn) return;
		var id = btn.getAttribute('data-reveal');
		var code = $('[data-secret="' + id + '"]');
		if (!code) return;
		if (code.dataset.shown) {
			code.textContent = '••••••••';
			delete code.dataset.shown;
			return;
		}
		api('access/' + id).then(function (res) {
			code.textContent = res.secret || '(vazia)';
			code.dataset.shown = '1';
			var copy = document.createElement('button');
			copy.type = 'button';
			copy.className = 'icon-btn';
			copy.setAttribute('data-copy', res.secret || '');
			copy.textContent = 'Copiar';
			if (!code.parentNode.querySelector('[data-copy]')) code.parentNode.appendChild(copy);
		}).catch(function (err) { toast(err.message, 'erro'); });
	});

	/* ---------- WHOIS / RDAP ---------- */

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-whois]');
		if (!btn) return;
		e.preventDefault();
		var form = btn.closest('form');
		var domain = $('[data-domain]', form);
		if (!domain || !domain.value.trim()) { toast('Digite o domínio primeiro.', 'erro'); return; }
		btn.disabled = true;
		btn.textContent = '…';
		api('whois?domain=' + encodeURIComponent(domain.value.trim()))
			.then(function (res) {
				var end = $('[data-domain-end]', form);
				if (end) end.value = res.expires;
				// A hospedagem acompanha o domínio (se a caixinha estiver marcada).
				if (end) end.dispatchEvent(new Event('change'));
				var reg = $('[name="registrar"]', form);
				if (reg && !reg.value.trim() && res.registrar) reg.value = res.registrar;
				toast(res.domain + ' vence em ' + res.label + (res.source ? ' (' + res.source + ')' : '') + '.');
			})
			.catch(function (err) { toast(err.message, 'erro'); })
			.then(function () { btn.disabled = false; btn.textContent = 'WHOIS'; });
	});

	/* ---------- Entrega: "o pedido foi pago?" ---------- */
	window.apAskPayment = function (info) {
		return new Promise(function (resolve) {
			var methods = ['Pix', 'Cartão de crédito', 'Cartão de débito', 'Dinheiro'];
			var money = function (v) { return 'R$ ' + (Math.round(v * 100) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
			var back = document.createElement('div');
			back.className = 'paymodal-back';
			back.innerHTML = '<div class="paymodal" role="dialog" aria-modal="true" aria-labelledby="pm-t">' +
				'<h3 id="pm-t">O pedido foi pago?</h3>' +
				'<p class="paymodal-sub"><b></b><span></span></p>' +
				'<p class="paymodal-due">Falta receber <b>' + money(info.due) + '</b></p>' +
				'<fieldset class="paymodal-methods"><legend>Forma de pagamento</legend>' + methods.map(function (m, i) { return '<label><input type="radio" name="pm" value="' + m + '"' + (i === 0 ? '' : '') + '><span>' + m + '</span></label>'; }).join('') + '</fieldset>' +
				'<p class="paymodal-err" hidden>Escolha a forma de pagamento.</p>' +
				'<div class="paymodal-actions"><button type="button" class="btn btn--primary" data-ok>Foi pago, entregar</button><button type="button" class="btn btn--ghost" data-unpaid>Ainda não pagou, entregar assim mesmo</button><button type="button" class="btn btn--ghost" data-cancel>Cancelar</button></div></div>';
			$('.paymodal-sub b', back).textContent = info.title || '';
			$('.paymodal-sub span', back).textContent = info.client ? ' · ' + info.client : '';
			document.body.appendChild(back);
			var done = function (v) { document.removeEventListener('keydown', onkey); back.remove(); resolve(v); };
			var onkey = function (e) { if (e.key === 'Escape') done(null); };
			document.addEventListener('keydown', onkey);
			back.addEventListener('click', function (e) { if (e.target === back) done(null); });
			$('[data-cancel]', back).addEventListener('click', function () { done(null); });
			$('[data-unpaid]', back).addEventListener('click', function () { done({ unpaid: true }); });
			$('[data-ok]', back).addEventListener('click', function () {
				var m = $('input[name=pm]:checked', back);
				if (!m) { $('.paymodal-err', back).hidden = false; return; }
				done({ method: m.value });
			});
			setTimeout(function () { var f = $('input[name=pm]', back); if (f) f.focus(); }, 30);
		});
	};
	$$('[data-stage-select]').forEach(function (sel) {
		var prev = sel.value;
		sel.addEventListener('change', function () {
			var form = sel.form, due = parseFloat(sel.getAttribute('data-due') || '0') || 0;
			if (sel.value === sel.getAttribute('data-last') && due > 0) {
				window.apAskPayment({ title: sel.getAttribute('data-ptitle'), due: due }).then(function (res) {
					if (!res) { sel.value = prev; return; }
					['pay_method', 'unpaid'].forEach(function (n) { var i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = n === 'unpaid' ? '1' : res.method || ''; if ((n === 'unpaid') === !!res.unpaid) form.appendChild(i); });
					form.submit();
				});
				return;
			}
			form.submit();
		});
	});

	/* ---------- Kanban (arrastar e soltar) ---------- */

	$$('[data-kanban]').forEach(function (board) {
		var type = board.getAttribute('data-kanban');
		var dragging = null;
		var moved = false;

		board.addEventListener('dragstart', function (e) {
			var card = e.target.closest('.kcard');
			if (!card) return;
			dragging = card;
			moved = false;
			card.classList.add('is-dragging');
			e.dataTransfer.effectAllowed = 'move';
			e.dataTransfer.setData('text/plain', card.dataset.id);
		});

		board.addEventListener('dragend', function () {
			if (!dragging) return;
			dragging.classList.remove('is-dragging');
			$$('.kcol-body', board).forEach(function (c) { c.classList.remove('is-over'); });
			var col = dragging.closest('.kcol-body');
			if (moved && col) save(dragging, col);
			dragging = null;
		});

		board.addEventListener('dragover', function (e) {
			var col = e.target.closest('.kcol-body');
			if (!col || !dragging) return;
			e.preventDefault();
			$$('.kcol-body', board).forEach(function (c) { c.classList.toggle('is-over', c === col); });
			var after = cardAfter(col, e.clientY);
			if (after) col.insertBefore(dragging, after);
			else col.appendChild(dragging);
			moved = true;
		});

		board.addEventListener('drop', function (e) { e.preventDefault(); });

		function cardAfter(col, y) {
			var cards = $$('.kcard:not(.is-dragging)', col);
			for (var i = 0; i < cards.length; i++) {
				var box = cards[i].getBoundingClientRect();
				if (y < box.top + box.height / 2) return cards[i];
			}
			return null;
		}

		function save(card, col) {
			var status = col.getAttribute('data-status');
			var lastBody = $('.kcol[data-last] .kcol-body', board);
			var isLast = !!lastBody && lastBody.getAttribute('data-status') === status;
			var due = parseFloat(card.dataset.due || '0') || 0;
			if (type === 'project' && isLast && due > 0 && window.apAskPayment) {
				window.apAskPayment({ title: card.dataset.ptitle, client: card.dataset.client, due: due }).then(function (res) {
					if (!res) { location.reload(); return; }
					send(card, col, res);
				});
				return;
			}
			send(card, col, {});
		}

		function send(card, col, extra) {
			var order = $$('.kcard', col).map(function (c) { return parseInt(c.dataset.id, 10); });
			counts();
			var body = { type: type, id: parseInt(card.dataset.id, 10), status: col.getAttribute('data-status'), order: order };
			if (extra.method) body.pay_method = extra.method;
			if (extra.unpaid) body.unpaid = true;
			api('move', 'POST', body)
				.then(function () { if (extra.method) { card.dataset.due = '0'; location.reload(); } })
				.catch(function (err) { toast(err.message, 'erro'); setTimeout(function () { location.reload(); }, 1400); });
		}

		function counts() {
			$$('.kcol', board).forEach(function (c) {
				var n = $('.kcount', c);
				if (n) n.textContent = $$('.kcard', c).length;
			});
		}

		// Clique no card abre a página dele.
		board.addEventListener('click', function (e) {
			var card = e.target.closest('.kcard');
			if (card && card.dataset.href && !e.target.closest('a, button')) location.href = card.dataset.href;
		});

		// Celular: segurar o card mostra um menu para mover de coluna.
		var holdTimer;
		board.addEventListener('touchstart', function (e) {
			var card = e.target.closest('.kcard');
			if (!card) return;
			holdTimer = setTimeout(function () { moveMenu(card); }, 550);
		}, { passive: true });
		['touchend', 'touchmove'].forEach(function (ev) {
			board.addEventListener(ev, function () { clearTimeout(holdTimer); }, { passive: true });
		});

		function moveMenu(card) {
			var cols = $$('.kcol', board);
			var sheet = document.createElement('div');
			sheet.className = 'sheet';
			sheet.innerHTML = '<div class="sheet-box"><strong>Mover para</strong></div>';
			cols.forEach(function (c) {
				var b = document.createElement('button');
				b.type = 'button';
				b.textContent = $('h3', c).textContent;
				b.addEventListener('click', function () {
					var body = $('.kcol-body', c);
					body.insertBefore(card, body.firstChild);
					save(card, body);
					sheet.remove();
				});
				$('.sheet-box', sheet).appendChild(b);
			});
			sheet.addEventListener('click', function (e) { if (e.target === sheet) sheet.remove(); });
			document.body.appendChild(sheet);
		}
	});

	/* ---------- Barra de comandos (Ctrl/⌘ + K): ações rápidas, busca e IA ---------- */

	var search = $('#search');
	if (search) {
		var input = $('input', search);
		var results = $('.search-results', search);
		var timer, active = 0, seq = 0;
		var commands = CFG.commands || [];
		var norm = function (t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
		var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };

		var open = function () { search.hidden = false; input.value = ''; render(''); setTimeout(function () { input.focus(); }, 20); };
		var close = function () { search.hidden = true; };

		var matchCommands = function (q) {
			if (!q) return commands.filter(function (c) { return c.group === 'Ação'; }).slice(0, 8);
			var words = norm(q).split(/\s+/).filter(Boolean);
			return commands.filter(function (c) {
				var hay = norm(c.label + ' ' + c.keys);
				return words.every(function (w) { return hay.indexOf(w) !== -1; });
			}).slice(0, 7);
		};
		var item = function (type, title, sub, attrs) {
			var a = document.createElement(attrs.href ? 'a' : 'button');
			if (attrs.href) a.href = attrs.href; else a.type = 'button';
			a.className = 'search-item' + (attrs.cls ? ' ' + attrs.cls : '');
			a.innerHTML = '<em>' + esc(type) + '</em><span><strong>' + esc(title) + '</strong>' + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</span>';
			if (attrs.run) a.setAttribute('data-run', attrs.run);
			if (attrs.ai) a.setAttribute('data-ai', attrs.ai);
			return a;
		};
		var highlight = function () {
			var items = $$('.search-item', results);
			items.forEach(function (it, i) { it.classList.toggle('is-active', i === active); });
			if (items[active]) items[active].scrollIntoView({ block: 'nearest' });
		};
		var render = function (q, found) {
			results.innerHTML = '';
			active = 0;
			var cmds = matchCommands(q);
			cmds.forEach(function (c) {
				var js = c.target.indexOf('js:') === 0;
				results.appendChild(item(c.group, c.label, '', js ? { run: c.target.slice(3) } : { href: c.target }));
			});
			(found || []).forEach(function (r) { results.appendChild(item(r.type, r.title, r.sub, { href: r.url })); });
			if (CFG.ai && q.length >= 4) {
				results.appendChild(item('IA', 'Pedir para a IA: “' + q + '”', 'Ela mostra o que vai fazer e você confirma antes', { ai: q, cls: 'search-item--ai' }));
			}
			if (!results.children.length) {
				results.innerHTML = '<p class="search-hint">' + (q.length < 2 ? 'Digite para buscar ou um comando, como “nova tarefa”.' : 'Nada encontrado.') + (CFG.ai ? '' : ' Comandos em português (“desmarcar as reuniões de amanhã”) funcionam com a chave do Claude em Configurações.') + '</p>';
			}
			highlight();
		};

		/* IA: pedido → lista de ações → confirmar */
		var aiRun = function (text) {
			results.innerHTML = '<div class="cmd-ai"><p class="cmd-you">' + esc(text) + '</p><p class="cmd-wait"><span class="cmd-spin"></span> Entendendo o pedido…</p></div>';
			api('command', 'POST', { texto: text }).then(function (d) {
				var box = document.createElement('div');
				box.className = 'cmd-ai';
				box.innerHTML = '<p class="cmd-you">' + esc(text) + '</p>' + (d.reply ? '<p class="cmd-reply">' + esc(d.reply) + '</p>' : '');
				if (d.plan && d.actions.length) {
					var list = document.createElement('ul');
					list.className = 'cmd-plan';
					d.actions.forEach(function (label, i) {
						var li = document.createElement('li');
						li.innerHTML = '<label><input type="checkbox" checked data-i="' + i + '"><span>' + esc(label) + '</span></label>';
						list.appendChild(li);
					});
					box.appendChild(list);
					var bar = document.createElement('div');
					bar.className = 'cmd-actions';
					bar.innerHTML = '<button type="button" class="btn btn--ghost btn--sm" data-cmd-cancel>Cancelar</button><button type="button" class="btn btn--primary btn--sm" data-cmd-go>Executar</button>';
					box.appendChild(bar);
					$('[data-cmd-cancel]', bar).addEventListener('click', function () { input.value = ''; render(''); input.focus(); });
					$('[data-cmd-go]', bar).addEventListener('click', function () {
						var skip = $$('input[data-i]', list).filter(function (c) { return !c.checked; }).map(function (c) { return +c.getAttribute('data-i'); });
						if (skip.length === d.actions.length) { toast('Nada marcado para executar.', 'erro'); return; }
						this.disabled = true; this.textContent = 'Executando…';
						api('command/confirm', 'POST', { plan: d.plan, skip: skip }).then(function (r) {
							var done = document.createElement('ul');
							done.className = 'cmd-plan cmd-plan--done';
							r.results.forEach(function (x) {
								var li = document.createElement('li');
								li.className = x.ok ? 'is-ok' : 'is-bad';
								li.innerHTML = '<span>' + (x.ok ? '✓' : '✗') + '</span><div><strong>' + esc(x.label) + '</strong><small>' + esc(x.msg || '') + '</small></div>';
								done.appendChild(li);
							});
							list.replaceWith(done);
							bar.innerHTML = '<button type="button" class="btn btn--primary btn--sm" data-cmd-reload>Fechar e atualizar</button>';
							$('[data-cmd-reload]', bar).addEventListener('click', function () { location.reload(); });
						}).catch(function (err) { toast(err.message, 'erro'); });
					});
				}
				results.innerHTML = '';
				results.appendChild(box);
			}).catch(function (err) {
				results.innerHTML = '<div class="cmd-ai"><p class="cmd-you">' + esc(text) + '</p><p class="cmd-reply cmd-reply--err">' + esc(err.message) + '</p></div>';
			});
		};

		var choose = function (el) {
			if (!el) return;
			if (el.hasAttribute('data-ai')) { aiRun(el.getAttribute('data-ai')); return; }
			if (el.hasAttribute('data-run')) {
				var what = el.getAttribute('data-run');
				close();
				var btn = $(what === 'money' ? '[data-money-toggle]' : '[data-theme-toggle]');
				if (btn) btn.click();
				return;
			}
			location.href = el.href;
		};
		results.addEventListener('click', function (e) {
			var el = e.target.closest('.search-item');
			if (el && !el.href) { e.preventDefault(); choose(el); }
		});

		document.addEventListener('keydown', function (e) {
			if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); search.hidden ? open() : close(); }
			if (e.key === 'Escape' && !search.hidden) close();
		});
		document.addEventListener('click', function (e) {
			if (e.target.closest('[data-search-open]')) open();
			else if (e.target === search) close();
		});

		input.addEventListener('input', function () {
			clearTimeout(timer);
			var q = input.value.trim();
			render(q);
			if (q.length < 2) return;
			var my = ++seq;
			timer = setTimeout(function () {
				api('search?q=' + encodeURIComponent(q)).then(function (list) { if (my === seq) render(q, list); }).catch(function () {});
			}, 180);
		});

		input.addEventListener('keydown', function (e) {
			var items = $$('.search-item', results);
			if (!items.length) return;
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				active = (active + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
				highlight();
			}
			if (e.key === 'Enter') { e.preventDefault(); choose(items[Math.max(0, active)]); }
		});
	}

	// Abrir uma janela pela URL (?abrir=id), usado pelas ações da barra de comandos.
	(function () {
		var m = location.search.match(/[?&]abrir=([a-z0-9-]+)/);
		var dlg = m && document.getElementById(m[1]);
		if (dlg && dlg.showModal) {
			dlg.showModal();
			var first = $('input:not([type=hidden]), textarea, select', dlg);
			if (first) setTimeout(function () { first.focus(); }, 50);
			if (history.replaceState) history.replaceState(null, '', location.pathname + location.search.replace(/[?&]abrir=[a-z0-9-]+/, '').replace(/^&/, '?'));
		}
	})();

	/* ---------- Filtro de listas ---------- */

	$$('[data-filter]').forEach(function (input) {
		input.addEventListener('input', function () {
			var q = input.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
			$$(input.getAttribute('data-filter')).forEach(function (row) {
				if (row.classList.contains('table-head')) return;
				var text = row.textContent.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
				row.hidden = q && text.indexOf(q) === -1;
			});
		});
	});

	/* ---------- Máscaras ---------- */

	document.addEventListener('input', function (e) {
		var el = e.target;
		var mask = el.getAttribute && el.getAttribute('data-mask');
		if (!mask) return;
		var d = el.value.replace(/\D/g, '');
		if (mask === 'phone' && el.dataset.ddi && el.dataset.ddi !== '55') {
			d = d.slice(0, 15);
			el.value = d.replace(/(\d{3})(?=\d)/g, '$1 ').trim();
			return;
		}
		if (mask === 'phone' && el.value.trim().charAt(0) === '+' && el._phoneDetect) { el._phoneDetect(); return; }
		if (mask === 'phone') {
			d = d.slice(0, 11);
			el.value = d.length > 10 ? d.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3')
				: d.length > 6 ? d.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3')
				: d.length > 2 ? d.replace(/(\d{2})(\d{0,5})/, '($1) $2') : d;
		}
		if (mask === 'doc') {
			d = d.slice(0, 14);
			el.value = d.length > 11 ? d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{0,2})/, '$1.$2.$3/$4-$5')
				: d.length > 9 ? d.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4')
				: d.length > 6 ? d.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3')
				: d.length > 3 ? d.replace(/(\d{3})(\d{0,3})/, '$1.$2') : d;
		}
	});

	// Mesmo critério do servidor (ap_parse_money): vírgula é centavos; ponto é centavos
	// só com 1 ou 2 dígitos depois ("4.300.00"), com 3 é milhar ("4.300").
	function parseMoney(raw) {
		var v = String(raw).replace(/[^\d,.-]/g, '');
		var neg = v.charAt(0) === '-';
		v = v.replace(/-/g, '');
		var last = Math.max(v.lastIndexOf(','), v.lastIndexOf('.'));
		if (last !== -1) {
			var tail = v.slice(last + 1);
			v = (v.charAt(last) === ',' || tail.length <= 2) ? v.slice(0, last).replace(/[.,]/g, '') + '.' + tail : v.replace(/[.,]/g, '');
		}
		var n = parseFloat(v);
		return isNaN(n) ? NaN : (neg ? -n : n);
	}

	document.addEventListener('blur', function (e) {
		var el = e.target;
		if (!el.hasAttribute || !el.hasAttribute('data-money') || !el.value.trim()) return;
		var n = parseMoney(el.value);
		if (!isNaN(n)) el.value = n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}, true);

	/* ---------- Novo projeto: serviço preenche páginas e valor ---------- */

	var serviceSel = $('[data-service]');
	if (serviceSel) {
		var pagesWrap = $('[data-pages-wrap]');
		var pages = $('[data-pages]');
		var value = $('[data-value]');
		var onService = function () {
			var opt = serviceSel.options[serviceSel.selectedIndex];
			var hasPages = opt && opt.getAttribute('data-has-pages') === '1';
			pagesWrap.hidden = !hasPages;
			if (hasPages && pages && !pages.dataset.touched) pages.value = opt.getAttribute('data-pages') || '';
			if (value && opt && opt.getAttribute('data-value') && !value.dataset.touched) value.value = opt.getAttribute('data-value');
		};
		if (pages) pages.addEventListener('input', function () { pages.dataset.touched = '1'; });
		if (value) value.addEventListener('input', function () { value.dataset.touched = '1'; });
		serviceSel.addEventListener('change', onService);
		onService();

		// Projeto em andamento: etapas do serviço escolhido para "em qual etapa ele está".
		var milestone = $('[data-milestone]');
		var fillMilestones = function () {
			if (!milestone) return;
			var opt = serviceSel.options[serviceSel.selectedIndex];
			var list = {};
			try { list = JSON.parse((opt && opt.getAttribute('data-milestones')) || '{}') || {}; } catch (e) { list = {}; }
			milestone.innerHTML = '';
			var first = document.createElement('option');
			first.value = '';
			first.textContent = 'Não marcar nenhuma etapa como feita';
			milestone.appendChild(first);
			Object.keys(list).forEach(function (k) {
				var o = document.createElement('option');
				o.value = k;
				o.textContent = list[k];
				milestone.appendChild(o);
			});
			if (Object.keys(list).length) {
				var end = document.createElement('option');
				end.value = 'fim';
				end.textContent = 'Tudo pronto (só falta publicar)';
				milestone.appendChild(end);
			}
		};
		serviceSel.addEventListener('change', fillMilestones);
		fillMilestones();
	}

	var kindPick = $('[data-kind-pick]');
	if (kindPick) {
		var kindForm = kindPick.closest('form');
		var onKind = function () {
			var checked = $('input[name=kind]:checked', kindForm);
			var andamento = checked && checked.value === 'andamento';
			$$('[data-kind-only]', kindForm).forEach(function (el) { el.hidden = el.getAttribute('data-kind-only') !== (andamento ? 'andamento' : 'novo'); });
			var welcome = $('input[name=welcome]', kindForm);
			var firstPaid = $('input[name=first_paid]', kindForm);
			if (welcome) welcome.checked = !andamento;
			if (firstPaid) firstPaid.checked = !andamento;
		};
		$$('input[name=kind]', kindForm).forEach(function (r) { r.addEventListener('change', onKind); });
	}

	var clientSel = $('[data-new-client]');
	if (clientSel) {
		var newClient = $('.new-client');
		var onClient = function () { newClient.hidden = !!clientSel.value; };
		clientSel.addEventListener('change', onClient);
		onClient();
	}

	/* ---------- Financeiro: categorias conforme entrada/saída ---------- */

	$$('[data-cat-switch]').forEach(function (radio) {
		var form = radio.closest('form');
		var apply = function () {
			var type = $('[data-cat-switch]:checked', form).value;
			var select = $('select[name=category]', form);
			$$('optgroup', select).forEach(function (g) {
				var on = g.getAttribute('data-cat') === type;
				g.hidden = !on;
				g.disabled = !on;
			});
			var firstOpt = $('optgroup[data-cat="' + type + '"] option', select);
			if (firstOpt) select.value = firstOpt.value;
		};
		radio.addEventListener('change', apply);
		if (radio.checked) apply();
	});

	/* ---------- Linhas clicáveis ---------- */

	document.addEventListener('click', function (e) {
		var row = e.target.closest('.table-row[data-href]');
		if (row && !e.target.closest('a, button, form, input, select')) location.href = row.getAttribute('data-href');
	});

	/* ---------- Briefing: mais um link de referência ---------- */

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-add-ref]');
		if (!btn) return;
		var box = btn.previousElementSibling;
		while (box && !box.hasAttribute('data-refs')) box = box.previousElementSibling;
		if (!box) return;
		var input = document.createElement('input');
		input.type = 'url';
		input.name = 'ref[' + btn.getAttribute('data-add-ref') + '][]';
		input.placeholder = 'https://';
		box.appendChild(input);
		input.focus();
	});

	/* ---------- Gerador de propostas ---------- */

	var builder = document.getElementById('prop-builder');
	if (builder && window.AP_PROP) {
		var P = window.AP_PROP;
		var fieldOf = function (key) {
			var all = builder.querySelectorAll('[data-key="' + key + '"]');
			for (var i = 0; i < all.length; i++) if (!all[i].closest('.plan-edit')) return all[i];
			return null;
		};
		var flashEl = function (el) { el.classList.remove('is-flash'); void el.offsetWidth; el.classList.add('is-flash'); };
		var setF = function (key, value, onlyIfEmpty) {
			var el = fieldOf(key);
			if (!el || (onlyIfEmpty && el.value.trim())) return;
			el.value = value == null ? '' : value;
			flashEl(el);
		};

		// Nome curto acompanha o nome, até ser editado à mão.
		var cName = fieldOf('cliente'), cShort = fieldOf('cliente_curto'), cSel = fieldOf('client_id');
		if (cName && cShort) {
			var synced = !cShort.value || cShort.value === cName.value;
			cShort.addEventListener('input', function () { synced = false; });
			cName.addEventListener('input', function () { if (synced) cShort.value = cName.value; });
		}
		if (cSel) {
			cSel.addEventListener('change', function () {
				var c = P.clients[cSel.value];
				if (!c) return;
				if (!cName.value.trim()) cName.value = c.label;
				if (!cShort.value.trim()) cShort.value = c.label;
			});
		}

		// No máximo 3 trabalhos.
		builder.addEventListener('change', function (e) {
			if (!e.target.matches('.pf-opt input')) return;
			if (builder.querySelectorAll('.pf-opt input:checked').length > 3) e.target.checked = false;
		});

		var NICHE_KEYS = ['capa_desc', 'intro_frase', 'cenario', 'oportunidade', 'diag_titulo', 'diagnostico', 'contato_titulo', 'contato_texto'];
		var PLAN_KEYS = ['tag', 'desc', 'prefixo', 'preco_de', 'preco_por', 'implantacao', 'pagamento', 'itens', 'cta'];

		document.getElementById('prop-autofill').addEventListener('click', function () {
			var niche = P.niches[fieldOf('nicho_id').value];
			var services = Array.prototype.map.call(builder.querySelectorAll('input[name="p[servicos][]"]:checked'), function (i) { return P.services[i.value]; }).filter(Boolean);
			if (!cName.value.trim()) { alert('Preencha o nome do cliente primeiro.'); cName.focus(); return; }
			if (!niche) { alert('Escolha o nicho do cliente.'); fieldOf('nicho_id').focus(); return; }
			if (!services.length) { alert('Marque pelo menos um serviço.'); return; }
			var hasText = NICHE_KEYS.concat(['escopo']).some(function (k) { var el = fieldOf(k); return el && el.value.trim(); });
			if (hasText && !confirm('Isso vai substituir os textos que já estão preenchidos. Continuar?')) return;

			NICHE_KEYS.forEach(function (k) { setF(k, niche[k]); });

			// Escopo: junta os itens dos serviços sem repetir título; os que se repetem (hospedagem, suporte) vão para o fim.
			var count = {}, first = {}, order = [];
			services.forEach(function (s) {
				(s.escopo || '').split(/\r?\n/).forEach(function (line) {
					line = line.trim();
					if (!line) return;
					var t = line.split('|')[0].trim().toLowerCase();
					count[t] = (count[t] || 0) + 1;
					if (!first[t]) { first[t] = line; order.push(t); }
				});
			});
			setF('escopo', order.filter(function (t) { return count[t] === 1; }).concat(order.filter(function (t) { return count[t] > 1; })).map(function (t) { return first[t]; }).join('\n'));

			var d = P.defaults;
			setF('data', d.data, true);
			setF('validade', d.validade, true);
			setF('responsavel', d.responsavel, true);
			setF('passos', d.passos);
			setF('invest_badge', d.invest_badge, true);
			setF('invest_titulo', d.invest_titulo, true);
			setF('invest_nota', d.invest_nota, true);
			setF('pos_nota', d.pos_nota, true);

			// Planos: um por serviço; com 2 ou mais, o segundo vira destaque.
			builder.querySelectorAll('.plan-edit').forEach(function (box, i) {
				var s = services[i];
				var q = function (k) { return box.querySelector('[data-pk="' + k + '"]'); };
				q('ativo').checked = !!s;
				if (!s) return;
				q('nome').value = s.title;
				q('service_id').value = s.id;
				PLAN_KEYS.forEach(function (k) { q(k).value = s[k] || ''; });
				q('estilo').value = services.length >= 2 && i === 1 ? 'destaque' : 'normal';
				box.querySelectorAll('input, textarea, select').forEach(flashEl);
			});

			// Trabalhos: primeiro os do nicho, depois completa até 3.
			var boxes = builder.querySelectorAll('.pf-opt input');
			var picked = [];
			P.portfolio.forEach(function (nichos, i) { if (picked.length < 3 && nichos.indexOf(niche.slug) !== -1) picked.push(i); });
			for (var i = 0; picked.length < 3 && i < P.portfolio.length; i++) if (picked.indexOf(i) === -1) picked.push(i);
			boxes.forEach(function (b) { b.checked = picked.indexOf(parseInt(b.value, 10)) !== -1; });

			toast('Textos preenchidos. Revise e gere a proposta.');
		});
	}

	/* ---------- Aceite: mostra a data quando escolhe "pagar depois" ---------- */

	$$('input[name=pagamento]').forEach(function (r) {
		r.addEventListener('change', function () {
			var box = $('.pay-date');
			if (box) box.hidden = !$('[data-later]').checked;
		});
	});
	var noDate = $('[data-no-date]');
	if (noDate) {
		var syncDate = function () { var dt = $('[data-pay-date]'); if (dt) { dt.disabled = noDate.checked; dt.closest('.field').classList.toggle('is-locked', noDate.checked); } };
		noDate.addEventListener('change', syncDate);
		syncDate();
	}

	/* ---------- Entrega: mede o PageSpeed (celular e computador) ---------- */

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-pagespeed]');
		if (!btn) return;
		var id = btn.getAttribute('data-pagespeed');
		var label = btn.querySelector('span');
		var old = label.textContent;
		btn.disabled = true;
		var run = function (strategy, text) {
			label.textContent = text;
			return api('delivery/' + id + '/pagespeed', 'POST', { strategy: strategy }).then(function (res) {
				var el = $('[data-ps="' + strategy + '"]');
				if (el && res.scores) el.textContent = res.scores.performance;
			});
		};
		run('mobile', 'Medindo no celular… (até 1 min)')
			.then(function () { return run('desktop', 'Medindo no computador…'); })
			.then(function () { toast('PageSpeed medido e prints salvos.'); })
			.catch(function (err) { toast(err.message, 'erro'); })
			.then(function () { btn.disabled = false; label.textContent = old; });
	});

	/* ---------- Mensalidade: serviço preenche nome e valor ---------- */

	$$('[data-sub-service]').forEach(function (sel) {
		sel.addEventListener('change', function () {
			var opt = sel.options[sel.selectedIndex];
			var form = sel.closest('form');
			if (!opt.value) return;
			var n = $('[data-sub-name]', form), v = $('[data-sub-value]', form);
			if (n && !n.value) n.value = opt.getAttribute('data-name');
			if (v && (!v.value || v.value === '0,00')) v.value = opt.getAttribute('data-value');
		});
	});

	/* ---------- Lembrete do lead: atalhos de data ---------- */

	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-quick-date]');
		if (!b) return;
		var input = $('.reminder-form input[type=date]', b.closest('.card'));
		if (input) { input.value = b.getAttribute('data-quick-date'); input.focus(); }
	});

	/* ---------- Enter não envia os formulários longos por engano ---------- */

	$$('form.wizard, form.stack').forEach(function (form) {
		form.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && e.target.matches('input:not([type=submit]), select')) e.preventDefault();
		});
	});

	/* ---------- WhatsApp: conversa atualiza sozinha ---------- */

	var thread = $('[data-wa-thread]');
	function waScroll() { if (thread) thread.scrollTop = thread.scrollHeight; }
	if (thread) {
		waScroll();
		setInterval(function () {
			if (document.hidden) return;
			api('wa/thread?fone=' + encodeURIComponent(thread.getAttribute('data-wa-thread'))).then(function (d) {
				if (d.last && String(d.last) !== thread.getAttribute('data-last')) {
					var atEnd = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 80;
					thread.innerHTML = d.html;
					thread.setAttribute('data-last', d.last);
					if (atEnd) waScroll();
				}
			}).catch(function () {});
		}, 8000);
	}

	$$('textarea[data-autosize]').forEach(function (t) {
		var fit = function () { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight, 160) + 'px'; };
		t.addEventListener('input', fit);
		t.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
				e.preventDefault();
				var f = t.closest('form');
				if (f.requestSubmit) f.requestSubmit(); else f.submit();
			}
		});
	});

	/* ---------- Testar assistente ---------- */

	var aForm = $('#assistant-form');
	if (aForm) {
		var log = $('#assistant-log');
		var bubble = function (text, who) {
			var el = document.createElement('div');
			el.className = 'wa-msg wa-msg--' + (who === 'me' ? 'in' : 'out') + (who === 'ai' ? ' is-ai' : '');
			var p = document.createElement('p');
			// *negrito* do WhatsApp
			p.innerHTML = text.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; })
				.replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
			el.appendChild(p);
			log.appendChild(el);
			log.scrollTop = log.scrollHeight;
			return el;
		};
		aForm.addEventListener('submit', function (e) {
			e.preventDefault();
			var ta = $('textarea', aForm), text = ta.value.trim(), btn = $('[type=submit]', aForm);
			if (!text) return;
			bubble(text, 'me');
			ta.value = '';
			ta.style.height = 'auto';
			var wait = bubble('Pensando…', 'ai');
			wait.classList.add('is-typing');
			api('assistant', 'POST', { text: text }).then(function (d) {
				wait.remove();
				bubble(d.reply || '(sem resposta)', 'ai');
			}).catch(function (err) {
				wait.remove();
				bubble('Erro: ' + err.message, 'ai');
			}).then(function () { setTimeout(function () { btn.disabled = false; ta.focus(); }, 10); });
		});
		var reset = $('[data-assistant-reset]');
		if (reset) reset.addEventListener('click', function () {
			api('assistant', 'POST', { text: '', reset: '1' });
			log.innerHTML = '';
		});
	}

	/* ---------- Redes sociais: editor ---------- */

	var postText = $('#post-text');
	if (postText) {
		var counter = $('[data-count-for="post-text"]');
		var prevText = $('[data-preview-text]');
		var sync = function () {
			var n = postText.value.length;
			if (counter) {
				counter.textContent = n + ' / 2200';
				counter.classList.toggle('text-late', n > 2200);
			}
			if (prevText) {
				var t = postText.value;
				prevText.textContent = t.length > 180 ? t.slice(0, 180) + '… mais' : t;
			}
		};
		postText.addEventListener('input', sync);
		sync();
	}

	var mediaInput = $('[data-media-input]');
	if (mediaInput) {
		mediaInput.addEventListener('change', function () {
			var box = $('[data-media-preview]');
			var prev = $('[data-preview-media]');
			box.innerHTML = '';
			Array.prototype.forEach.call(mediaInput.files, function (f, i) {
				var url = URL.createObjectURL(f);
				var el = document.createElement(f.type.indexOf('video') === 0 ? 'video' : 'img');
				el.src = url;
				var wrap = document.createElement('span');
				wrap.className = 'media-item';
				wrap.appendChild(el);
				box.appendChild(wrap);
				if (i === 0 && prev && !prev.querySelector('img') && f.type.indexOf('image') === 0) {
					var im = document.createElement('img');
					im.src = url;
					prev.appendChild(im);
				}
			});
		});
	}

	var aiBtn = $('[data-ai-caption]');
	if (aiBtn) {
		aiBtn.addEventListener('click', function () {
			var form = aiBtn.closest('form');
			var nets = $$('input[name="networks[]"]:checked', form).map(function (c) { return c.value; }).join(', ');
			var topic = postText.value.trim() || ($('input[name=title]', form).value || '').trim();
			if (!topic) { toast('Escreva o tema ou um rascunho primeiro.', 'erro'); postText.focus(); return; }
			var label = aiBtn.textContent;
			aiBtn.disabled = true;
			aiBtn.textContent = 'Escrevendo…';
			api('social/ai', 'POST', { what: 'legenda', topic: topic, networks: nets }).then(function (d) {
				postText.value = d.text || '';
				postText.dispatchEvent(new Event('input'));
				if (d.linkedin) {
					var li = $('textarea[name=text_linkedin]', form);
					li.value = d.linkedin;
					var box = $('[data-li-box]');
					if (box) box.open = true;
				}
			}).catch(function (err) { toast(err.message, 'erro'); }).then(function () {
				aiBtn.disabled = false;
				aiBtn.textContent = label;
			});
		});
	}

	var ideasForm = $('[data-social-ideas]');
	if (ideasForm) {
		ideasForm.addEventListener('submit', function (e) {
			e.preventDefault();
			var btn = $('[type=submit]', ideasForm);
			var label = btn.textContent;
			btn.textContent = 'Pensando… (uns 30 segundos)';
			api('social/ai', 'POST', { what: 'ideias', topic: $('input[name=topic]', ideasForm).value }).then(function (d) {
				toast(d.count + ' ideias novas!');
				setTimeout(function () { location.reload(); }, 700);
			}).catch(function (err) {
				toast(err.message, 'erro');
				btn.textContent = label;
				btn.disabled = false;
			});
		});
	}

	/* ---------- Extrato: categoria só para lançamento novo ---------- */

	$$('[data-bank-act]').forEach(function (sel) {
		var cat = sel.parentNode.querySelector('.bank-cat');
		var sync = function () { if (cat) cat.hidden = sel.value !== 'new'; };
		sel.addEventListener('change', sync);
		sync();
	});



	/* ---------- Quadro de projetos: colunas editáveis ---------- */

	var editable = $('.kanban--editable');
	if (editable) {
		var colCall = function (payload) {
			return api('columns', 'POST', payload).then(function () { location.reload(); }).catch(function (err) { toast(err.message, 'erro'); });
		};
		editable.addEventListener('click', function (e) {
			var title = e.target.closest('[data-col-name]');
			var btn = e.target.closest('[data-col-op]');
			var col = e.target.closest('.kcol[data-col]');
			if (!col) return;
			if (title && !title.isContentEditable) {
				var before = title.textContent.trim();
				title.contentEditable = 'true';
				title.classList.add('is-editing');
				title.focus();
				var range = document.createRange(); range.selectNodeContents(title);
				var selc = window.getSelection(); selc.removeAllRanges(); selc.addRange(range);
				var done = false;
				var finish = function (save) {
					if (done) return; done = true;
					title.contentEditable = 'false';
					title.classList.remove('is-editing');
					var now = title.textContent.trim();
					if (!save || !now || now === before) { title.textContent = before; return; }
					colCall({ op: 'rename', kind: col.dataset.kind, slug: col.dataset.col, name: now });
				};
				title.addEventListener('keydown', function (ev) {
					if (ev.key === 'Enter') { ev.preventDefault(); finish(true); }
					if (ev.key === 'Escape') { ev.preventDefault(); finish(false); }
				});
				title.addEventListener('blur', function () { finish(true); }, { once: true });
				return;
			}
			if (btn) {
				e.preventDefault();
				var op = btn.getAttribute('data-col-op');
				if (op === 'delete') {
					var n = $$('.kcard', col).length;
					if (col.dataset.kind === 'stage' && n) { toast('Mova os projetos dessa etapa antes de excluir.', 'erro'); return; }
					if (!confirm('Excluir a coluna "' + $('[data-col-name]', col).textContent.trim() + '"?' + (n ? ' Os projetos voltam para a etapa deles.' : ''))) return;
					colCall({ op: 'delete', kind: col.dataset.kind, slug: col.dataset.col });
				} else {
					colCall({ op: 'move', kind: col.dataset.kind, slug: col.dataset.col, dir: op });
				}
			}
		});
		var addForm = $('[data-col-add]');
		if (addForm) {
			addForm.addEventListener('submit', function (e) {
				e.preventDefault();
				var name = $('[name="col_name"]', addForm).value.trim();
				var kind = ($('[name="col_kind"]:checked', addForm) || {}).value || 'internal';
				if (!name) return;
				colCall({ op: 'add', kind: kind, name: name });
			});
		}
	}


	/* ---------- "Já é cliente?": preenche os dados do cliente ---------- */

	$$('[data-client-fill]').forEach(function (sel) {
		var map = {};
		try { map = JSON.parse(sel.getAttribute('data-client-fill')) || {}; } catch (e) {}
		sel.addEventListener('change', function () {
			var c = map[sel.value];
			var form = sel.closest('form');
			if (!c || !form) return;
			['name', 'company', 'email', 'whatsapp'].forEach(function (f) {
				var el = $('[name="' + f + '"]', form);
				if (el && c[f]) {
					el.value = c[f];
					if (f === 'whatsapp' && el._phoneDetect && String(c[f]).charAt(0) === '+') el._phoneDetect();
					el.dispatchEvent(new Event('input', { bubbles: true }));
				}
			});
		});
		// Veio da ficha do cliente (?cliente=ID): já escolhe o cliente.
		var pre = location.search.match(/[?&]cliente=(\d+)/);
		if (pre && !sel.value && $('option[value="' + pre[1] + '"]', sel)) {
			sel.value = pre[1];
			sel.dispatchEvent(new Event('change'));
		}
	});


	/* ---------- Editar reunião: preenche a janela com os dados do evento ---------- */

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-meeting]');
		if (!btn) return;
		var m = {};
		try { m = JSON.parse(btn.getAttribute('data-meeting')) || {}; } catch (err) { return; }
		var dlg = document.getElementById('editar-reuniao');
		if (!dlg) return;
		var set = function (name, v) { var el = $('[name="' + name + '"]', dlg); if (el) el.value = v == null ? '' : v; };
		set('event_id', m.id); set('title', m.title); set('date', m.date); set('time', m.time); set('guests', '');
		var dur = $('[name="duration"]', dlg);
		if (dur) {
			if (!$('option[value="' + m.duration + '"]', dur)) { var o = document.createElement('option'); o.value = m.duration; o.textContent = m.duration + ' min'; dur.appendChild(o); }
			dur.value = String(m.duration);
		}
		var g = $('[data-meeting-guests]', dlg);
		if (g) g.textContent = m.guests && m.guests.length ? 'Já convidados: ' + m.guests.join(', ') : 'Ninguém convidado ainda.';
	}, true);


	/* ---------- Financeiro: chave "Conta recorrente" ---------- */

	$$('[data-recur-toggle]').forEach(function (box) {
		var fields = $('[data-recur-fields]', box.closest('form'));
		var sync = function () { if (fields) fields.hidden = !box.checked; };
		box.addEventListener('change', sync);
		sync();
	});

	/* ---------- Telefone / WhatsApp com país (bandeirinha) ---------- */

	var PAISES = [
		['🇧🇷', '55', 'Brasil'], ['🇵🇹', '351', 'Portugal'], ['🇺🇸', '1', 'Estados Unidos / Canadá'], ['🇦🇷', '54', 'Argentina'],
		['🇺🇾', '598', 'Uruguai'], ['🇵🇾', '595', 'Paraguai'], ['🇨🇱', '56', 'Chile'], ['🇧🇴', '591', 'Bolívia'], ['🇵🇪', '51', 'Peru'],
		['🇨🇴', '57', 'Colômbia'], ['🇻🇪', '58', 'Venezuela'], ['🇪🇨', '593', 'Equador'], ['🇲🇽', '52', 'México'], ['🇪🇸', '34', 'Espanha'],
		['🇫🇷', '33', 'França'], ['🇮🇹', '39', 'Itália'], ['🇩🇪', '49', 'Alemanha'], ['🇬🇧', '44', 'Reino Unido'], ['🇮🇪', '353', 'Irlanda'],
		['🇨🇭', '41', 'Suíça'], ['🇳🇱', '31', 'Holanda'], ['🇧🇪', '32', 'Bélgica'], ['🇱🇺', '352', 'Luxemburgo'], ['🇦🇹', '43', 'Áustria'],
		['🇸🇪', '46', 'Suécia'], ['🇳🇴', '47', 'Noruega'], ['🇩🇰', '45', 'Dinamarca'], ['🇵🇱', '48', 'Polônia'], ['🇦🇴', '244', 'Angola'],
		['🇲🇿', '258', 'Moçambique'], ['🇨🇻', '238', 'Cabo Verde'], ['🇿🇦', '27', 'África do Sul'], ['🇦🇪', '971', 'Emirados Árabes'],
		['🇮🇱', '972', 'Israel'], ['🇯🇵', '81', 'Japão'], ['🇨🇳', '86', 'China'], ['🇮🇳', '91', 'Índia'], ['🇦🇺', '61', 'Austrália'], ['🇳🇿', '64', 'Nova Zelândia']
	];

	function splitPhone(v) {
		v = String(v || '').trim();
		if (v.charAt(0) !== '+') return { ddi: '55', num: v };
		var d = v.replace(/\D/g, ''), best = '';
		PAISES.forEach(function (p) { if (d.indexOf(p[1]) === 0 && p[1].length > best.length) best = p[1]; });
		return best ? { ddi: best, num: d.slice(best.length) } : { ddi: '55', num: d };
	}

	function formatNum(ddi, digits) {
		digits = String(digits).replace(/\D/g, '');
		if (ddi !== '55') return digits.replace(/(\d{3})(?=\d)/g, '$1 ').trim();
		return digits.length > 10 ? digits.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3')
			: digits.length > 6 ? digits.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3')
			: digits.length > 2 ? digits.replace(/(\d{2})(\d{0,5})/, '($1) $2') : digits;
	}

	function initPhone(input) {
		if (input.dataset.phoneReady) return;
		input.dataset.phoneReady = '1';
		var parts = splitPhone(input.value);
		var sel = document.createElement('select');
		sel.className = 'phone-country';
		sel.setAttribute('aria-label', 'País');
		PAISES.forEach(function (p) {
			var o = document.createElement('option');
			o.value = p[1];
			o.textContent = p[0] + ' +' + p[1];
			o.title = p[2];
			sel.appendChild(o);
		});
		sel.value = parts.ddi;
		var wrap = document.createElement('span');
		wrap.className = 'phone-wrap';
		input.parentNode.insertBefore(wrap, input);
		wrap.appendChild(sel);
		wrap.appendChild(input);
		var apply = function () {
			input.dataset.ddi = sel.value;
			input.placeholder = sel.value === '55' ? '(00) 00000-0000' : 'número com DDD / código de área';
		};
		apply();
		if (parts.ddi !== '55' || input.value.trim().charAt(0) === '+') input.value = formatNum(parts.ddi, parts.num);
		sel.addEventListener('change', function () { apply(); input.value = formatNum(sel.value, input.value); });
		// Colou/digitou "+351…": troca o país sozinho.
		input._phoneDetect = function () {
			var p = splitPhone(input.value);
			sel.value = p.ddi;
			apply();
			input.value = formatNum(p.ddi, p.num);
		};
		var form = input.closest('form');
		if (form) {
			form.addEventListener('submit', function () {
				var digits = input.value.replace(/\D/g, '');
				if (digits && sel.value !== '55') input.value = '+' + sel.value + ' ' + formatNum(sel.value, digits);
			}, true);
		}
	}

	$$('input[data-mask="phone"]').forEach(initPhone);

	/* ---------- Evita envio duplo ---------- */

	document.addEventListener('submit', function (e) {
		var btn = e.submitter || $('[type=submit]', e.target);
		if (btn && !btn.hasAttribute('formnovalidate')) {
			setTimeout(function () { btn.disabled = true; }, 0);
		}
	});

	/* ---------- Linhas dinâmicas fora da calculadora (ex.: registrar consumo) ---------- */
	document.addEventListener('click', function (e) {
		var add = e.target.closest('[data-row-add]');
		if (!add || add.closest('[data-calc]')) return;
		var form = add.closest('form');
		var box = form && form.querySelector('[data-rows="' + add.getAttribute('data-row-add') + '"]');
		if (!box) return;
		var copy = box.querySelector('.row-line').cloneNode(true);
		copy.querySelectorAll('input').forEach(function (i) { i.value = ''; });
		copy.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
		box.appendChild(copy);
	});
})();
