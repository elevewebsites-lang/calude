(function () {
	'use strict';
	var D = document, W = window, C = W.LDKSITE || {};
	var reduce = W.matchMedia && W.matchMedia('(prefers-reduced-motion: reduce)').matches;
	function $(s, c) { return (c || D).querySelector(s); }
	function $$(s, c) { return Array.prototype.slice.call((c || D).querySelectorAll(s)); }

	/* Entrada com a logo (1x por visita) */
	var intro = $('[data-intro]');
	if (intro) {
		var seen = false;
		try { seen = sessionStorage.getItem('ldk-intro'); } catch (e) {}
		if (seen || reduce) { intro.parentNode.removeChild(intro); }
		else {
			D.documentElement.style.overflow = 'hidden';
			var done = function () {
				intro.classList.add('out');
				D.documentElement.style.overflow = '';
				try { sessionStorage.setItem('ldk-intro', '1'); } catch (e) {}
				setTimeout(function () { if (intro.parentNode) { intro.parentNode.removeChild(intro); } }, 1000);
			};
			setTimeout(done, 2100);
			intro.addEventListener('click', done);
		}
	}

	/* Cabeçalho, progresso e menu */
	var header = $('[data-header]'), bar = $('[data-progress]');
	function onScroll() {
		var y = W.pageYOffset || 0, h = D.documentElement.scrollHeight - W.innerHeight;
		if (header) { header.classList.toggle('sc', y > 10); }
		if (bar) { bar.style.width = (h > 0 ? Math.min(100, y / h * 100) : 0) + '%'; }
		$$('[data-par]').forEach(function (el) {
			if (reduce) { return; }
			el.style.transform = 'translate3d(0,' + (y * parseFloat(el.getAttribute('data-par'))) + 'px,0)';
		});
	}
	W.addEventListener('scroll', onScroll, { passive: true }); onScroll();
	var burger = $('[data-burger]'), nav = $('#ldk-nav');
	if (burger && nav) {
		burger.addEventListener('click', function () {
			var o = nav.classList.toggle('open');
			header.classList.toggle('openh', o);
			burger.setAttribute('aria-expanded', o ? 'true' : 'false');
		});
		$$('a', nav).forEach(function (a) { a.addEventListener('click', function () { nav.classList.remove('open'); header.classList.remove('openh'); }); });
	}

	/* Revelar ao rolar */
	var els = $$('[data-r]');
	if ('IntersectionObserver' in W && !reduce) {
		var io = new IntersectionObserver(function (es) {
			es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
		}, { threshold: .12, rootMargin: '0px 0px -6% 0px' });
		els.forEach(function (el) { io.observe(el); });
	} else { els.forEach(function (el) { el.classList.add('in'); }); }

	/* Contadores */
	$$('[data-count]').forEach(function (el) {
		var end = parseFloat(el.getAttribute('data-count'));
		if (isNaN(end) || end > 1900 || reduce || !('IntersectionObserver' in W)) { return; }
		var o = new IntersectionObserver(function (es) {
			if (!es[0].isIntersecting) { return; }
			o.disconnect();
			var t0 = null;
			(function step(t) {
				t0 = t0 || t;
				var p = Math.min(1, (t - t0) / 1400);
				el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3)));
				if (p < 1) { requestAnimationFrame(step); }
			})(performance.now());
		});
		el.textContent = '0'; o.observe(el);
	});

	/* Títulos entram palavra por palavra */
	$$('[data-split]').forEach(function (h) {
		var i = 0;
		(function walk(node) {
			Array.prototype.slice.call(node.childNodes).forEach(function (n) {
				if (n.nodeType === 3) {
					var frag = D.createDocumentFragment();
					n.textContent.split(/(\s+)/).forEach(function (t) {
						if (!t) { return; }
						if (/^\s+$/.test(t)) { frag.appendChild(D.createTextNode(' ')); return; }
						var w = D.createElement('span'), inner = D.createElement('span');
						w.className = 'w'; inner.textContent = t; inner.style.setProperty('--i', i++);
						w.appendChild(inner); frag.appendChild(w);
					});
					node.replaceChild(frag, n);
				} else if (n.nodeType === 1) { walk(n); }
			});
		})(h);
		if (!('IntersectionObserver' in W) || reduce) { h.classList.add('in'); return; }
		var o = new IntersectionObserver(function (es) { if (es[0].isIntersecting) { h.classList.add('in'); o.disconnect(); } }, { threshold: .1 });
		o.observe(h);
	});

	/* A seção clara entra por cima da escura, abrindo as laterais conforme a rolagem */
	var sheets = $$('.ldk[data-tone=light]');
	function sheetScrub() {
		if (reduce) { return; }
		var vh = W.innerHeight;
		sheets.forEach(function (el) {
			var top = el.getBoundingClientRect().top;
			var p = Math.max(0, Math.min(1, (vh - top) / (vh * .55)));
			el.style.setProperty('--ci', ((1 - p) * 4).toFixed(2) + '%');
		});
	}
	W.addEventListener('scroll', sheetScrub, { passive: true }); W.addEventListener('resize', sheetScrub); sheetScrub();

	/* Imagem que não carregar: o bloco fica com o fundo da marca, sem ícone quebrado */
	function fixImg(img) { img.style.visibility = 'hidden'; }
	D.addEventListener('error', function (e) { if (e.target && e.target.tagName === 'IMG' && e.target.closest('.ldk')) { fixImg(e.target); } }, true);
	$$('.ldk img').forEach(function (i) { if (i.complete && i.naturalWidth === 0 && i.getAttribute('src')) { fixImg(i); } });

	/* Painel do cliente: abas com as telas */
	$$('[data-panel]').forEach(function (p) {
		var tabs = $$('.pt', p), shots = $$('.ps', p), cur = 0, timer;
		function go(i) {
			cur = i;
			tabs.forEach(function (t, k) { t.classList.toggle('on', k === i); });
			shots.forEach(function (s, k) { s.classList.toggle('on', k === i); });
		}
		tabs.forEach(function (t, k) { t.addEventListener('click', function () { clearInterval(timer); go(k); }); });
		if (!reduce) { timer = setInterval(function () { go((cur + 1) % tabs.length); }, 5200); }
	});

	/* Envio para o CRM */
	function send(tipo, fields, msg, ok) {
		msg.className = 'fmsg'; msg.textContent = 'Enviando…';
		return fetch(C.lead, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ tipo: tipo, fields: fields, page: location.href, website: fields.website || '', _t: Date.now() - (W.__ldkT || Date.now()) })
		}).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
			.then(function (x) {
				if (x.ok && x.j && x.j.ok) { ok(); }
				else { msg.className = 'fmsg err'; msg.textContent = (x.j && x.j.message) || 'Não foi possível enviar. Tente pelo WhatsApp.'; }
			}).catch(function () { msg.className = 'fmsg err'; msg.textContent = 'Sem conexão. Tente pelo WhatsApp.'; });
	}
	W.__ldkT = Date.now();
	function collect(form) {
		var f = {};
		$$('input,select,textarea', form).forEach(function (i) {
			if (!i.name || i.type === 'radio' || i.type === 'checkbox') { return; }
			f[i.name] = i.value.trim();
		});
		return f;
	}
	function valid(form, msg) {
		var bad = null;
		$$('[required]', form).forEach(function (i) {
			if (bad) { return; }
			if ((i.type === 'checkbox' && !i.checked) || (i.type !== 'checkbox' && !i.value.trim())) { bad = i; }
		});
		var em = $('input[type=email]', form);
		if (!bad && em && em.value && !/^\S+@\S+\.\S+$/.test(em.value)) { bad = em; }
		if (bad) { msg.className = 'fmsg err'; msg.textContent = 'Confira os campos obrigatórios.'; bad.focus(); return false; }
		return true;
	}
	$$('[data-ldk-form]').forEach(function (form) {
		var msg = $('.fmsg', form);
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (!valid(form, msg)) { return; }
			var btn = $('button[type=submit]', form); btn.disabled = true;
			var f = collect(form);
			send(form.getAttribute('data-tipo') || 'contato', f, msg, function () {
				form.reset();
				if (form.getAttribute('data-wa') === '1' && C.waNum) {
					var t = 'Olá! Sou ' + f['Nome'] + (f['Empresa'] ? ' da ' + f['Empresa'] : '') + '. Acabei de enviar meus dados pelo site da LDK.' +
						(f['Interesse'] ? '\nTenho interesse em: ' + f['Interesse'] + '.' : '') + (f['Mensagem'] ? '\n' + f['Mensagem'] : '');
					var url = 'https://wa.me/' + C.waNum + '?text=' + encodeURIComponent(t);
					msg.className = 'fmsg ok';
					msg.innerHTML = 'Recebemos seus dados. Abrindo o WhatsApp… <a href="' + url + '">Se não abrir, toque aqui.</a>';
					setTimeout(function () { W.location.href = url; }, 1400);
				} else {
					msg.className = 'fmsg ok'; msg.textContent = 'Mensagem enviada! A equipe da LDK entra em contato em breve.';
				}
			}).then(function () { btn.disabled = false; });
		});
	});

	/* Diagnóstico de perfil */
	$$('[data-ldk-quiz]').forEach(function (q) {
		var total = parseInt(q.getAttribute('data-total'), 10), step = 0;
		var sets = $$('.dq', q), next = $('[data-next]', q), prev = $('[data-prev]', q), fill = $('.qbar i', q), msg = $('.fmsg', q), n = $('[data-qn]', q);
		var box = q.parentNode, res = $('[data-result]', box);
		function can() {
			var s = sets[step];
			return step === total ? true : !!$('input:checked', s);
		}
		function show() {
			sets.forEach(function (s, i) { s.hidden = i !== step; });
			prev.hidden = step === 0;
			next.disabled = !can();
			$('span', next).textContent = step === total ? 'Receber meu diagnóstico' : 'Continuar';
			fill.style.width = (step / (total + 1) * 100 + 100 / (total + 1) * (can() ? 1 : 0)) + '%';
			n.textContent = step + 1;
			msg.textContent = '';
		}
		q.addEventListener('change', function () { next.disabled = !can(); fill.style.width = ((step + 1) / (total + 1) * 100) + '%'; });
		prev.addEventListener('click', function () { if (step > 0) { step--; show(); } });
		next.addEventListener('click', function () {
			if (step < total) { step++; show(); return; }
			if (!valid(sets[total], msg)) { return; }
			var f = collect(q), score = 0, max = 0, answers = [];
			sets.forEach(function (s, i) {
				if (i >= total) { return; }
				var c = $('input:checked', s);
				if (!c) { return; }
				f[s.getAttribute('data-label')] = c.value;
				answers.push(s.getAttribute('data-label') + ': ' + c.value);
				if (s.getAttribute('data-score') === '1') { score += parseInt(c.getAttribute('data-pts'), 10); max += $$('input', s).length - 1; }
			});
			var pct = Math.round(score / max * 100);
			var lv = pct < 35 ? ['Perfil em construção', 'Seu perfil ainda não tem uma base estratégica. A boa notícia: é o melhor momento para estruturar do jeito certo, sem desperdiçar verba.', ['Defina o público e a oferta principal', 'Monte uma linha editorial com objetivo para cada post', 'Organize a identidade visual antes de anunciar']]
				: pct < 70 ? ['Perfil com potencial travado', 'Você já tem movimento, mas falta método para transformar presença em contatos de forma previsível.', ['Crie um calendário fixo com conteúdos de venda e de autoridade', 'Comece a medir contatos e vendas, não só curtidas', 'Teste anúncios com verba controlada e um destino claro']]
					: ['Perfil pronto para escalar', 'A base está boa. O próximo passo é otimizar, ampliar o alcance e acompanhar o retorno com relatórios claros.', ['Escale as campanhas que já trazem contatos de qualidade', 'Treine o atendimento para converter os leads', 'Acompanhe tudo em relatório mensal e reunião de resultado']];
			f['Mensagem'] = 'Diagnóstico de perfil: ' + pct + '/100 · ' + lv[0] + '\n' + answers.join('\n');
			next.disabled = true;
			send('diagnostico', f, msg, function () {
				q.hidden = true; res.hidden = false;
				$('[data-score]', res).textContent = pct;
				$('[data-level]', res).textContent = lv[0];
				$('[data-leveltxt]', res).textContent = lv[1];
				$('[data-next-steps]', res).innerHTML = lv[2].map(function (t) { return '<li>' + t.replace(/</g, '&lt;') + '</li>'; }).join('');
				var v = $('.v', res); setTimeout(function () { v.style.strokeDashoffset = 327 - 327 * pct / 100; }, 100);
				var hd = $('.ldk-head', box); if (hd) { hd.hidden = true; }
				res.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
			}).then(function () { next.disabled = false; });
		});
		show();
	});
})();
