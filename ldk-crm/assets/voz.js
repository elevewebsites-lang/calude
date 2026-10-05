/* LDK · Sala de voz da equipe (WebRTC, cada navegador ligado a todos; o servidor só troca os convites). */
(function () {
	'use strict';
	var CFG = window.LK_SALA;
	var root = document.querySelector('[data-sala]');
	if (!CFG || !root) return;
	var $ = function (s) { return root.querySelector(s); };
	var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
	var api = function (path, data) {
		return fetch(CFG.rest + path, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': CFG.nonce, 'Content-Type': 'application/json' }, body: JSON.stringify(data || {}), keepalive: path === 'voice/leave' })
			.then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Erro'); return j; }); });
	};

	var ME = CFG.me, local = null, micOn = false, pttDown = false, deaf = false, vol = 1;
	var peers = {};      // id → { pc, audio, level, iAmCaller, t }
	var members = [];    // quem está na sala (do servidor)
	var ac = null, meter = {}; // medidores de volume (quem está falando)
	var left = false;

	function err(msg) { var e = $('[data-sala-err]'); e.textContent = msg; e.hidden = !msg; }
	function state(t) { $('[data-sala-state]').textContent = t; }

	/* ---------- microfone ---------- */
	function setMic(on) {
		micOn = on;
		if (local) local.getAudioTracks().forEach(function (t) { t.enabled = on; });
		var b = $('[data-sala-mic]');
		b.classList.toggle('is-on', on);
		b.setAttribute('aria-pressed', on ? 'true' : 'false');
		b.querySelector('[data-mic-on]').hidden = !on;
		b.querySelector('[data-mic-off]').hidden = on;
		$('[data-sala-mic-t]').textContent = on ? 'Microfone ligado' : 'Ligar microfone';
		document.title = (on ? '🎙 ' : '') + 'Sala de voz';
		beat();
	}
	$('[data-sala-mic]').addEventListener('click', function () { setMic(!micOn); });
	// Segurar espaço = falar (push-to-talk).
	window.addEventListener('keydown', function (e) {
		if (e.code !== 'Space' || e.repeat || /INPUT|TEXTAREA|BUTTON/.test(document.activeElement.tagName)) return;
		e.preventDefault(); if (!micOn) { pttDown = true; setMic(true); }
	});
	window.addEventListener('keyup', function (e) { if (e.code === 'Space' && pttDown) { pttDown = false; setMic(false); } });

	$('[data-sala-vol]').addEventListener('input', function (e) { vol = parseFloat(e.target.value); Object.keys(peers).forEach(function (id) { if (peers[id].audio) peers[id].audio.volume = vol; }); });
	$('[data-sala-mute]').addEventListener('click', function (e) {
		deaf = !deaf; e.currentTarget.setAttribute('aria-pressed', deaf ? 'true' : 'false'); e.currentTarget.textContent = deaf ? '🔇' : '🔈';
		Object.keys(peers).forEach(function (id) { if (peers[id].audio) peers[id].audio.muted = deaf; });
	});

	/* ---------- medidor: quem está falando ---------- */
	function watch(id, stream) {
		try {
			ac = ac || new (window.AudioContext || window.webkitAudioContext)();
			var src = ac.createMediaStreamSource(stream), an = ac.createAnalyser();
			an.fftSize = 512; src.connect(an);
			meter[id] = { an: an, buf: new Uint8Array(an.fftSize), lvl: 0 };
		} catch (e) {}
	}
	function levels() {
		Object.keys(meter).forEach(function (id) {
			var m = meter[id]; m.an.getByteTimeDomainData(m.buf);
			var sum = 0; for (var i = 0; i < m.buf.length; i++) { var v = (m.buf[i] - 128) / 128; sum += v * v; }
			var rms = Math.sqrt(sum / m.buf.length);
			m.lvl = m.lvl * 0.7 + rms * 0.3;
			var tile = root.querySelector('[data-tile="' + id + '"]');
			if (tile) tile.classList.toggle('is-talking', m.lvl > 0.02 && (String(id) !== String(ME) || micOn));
		});
		requestAnimationFrame(levels);
	}

	/* ---------- desenho ---------- */
	function draw() {
		var grid = $('[data-sala-grid]');
		grid.innerHTML = members.map(function (u) {
			var p = peers[u.id], st = u.id === ME ? 'você' : (p && p.pc ? ({ connected: 'conectado', completed: 'conectado', checking: 'conectando…', new: 'conectando…', disconnected: 'instável…', failed: 'sem conexão' }[p.pc.iceConnectionState] || 'conectando…') : 'conectando…');
			return '<div class="sala-u' + (u.mic ? ' is-mic' : '') + '" data-tile="' + u.id + '"><span class="sala-av">' + esc(u.ini) + '<i>' + (u.mic ? '🎙' : '🔇') + '</i></span><strong>' + esc(u.id === ME ? u.name + ' (você)' : u.name) + '</strong><small>' + esc(st) + '</small></div>';
		}).join('') || '<p class="muted">Entrando…</p>';
		var others = members.filter(function (u) { return u.id !== ME; }).length;
		state(others ? others + (others === 1 ? ' pessoa' : ' pessoas') + ' com você na sala' : 'Só você por enquanto. Chame a equipe!');
	}

	/* ---------- conexões ---------- */
	function iceDone(pc) {
		return new Promise(function (res) {
			if (pc.iceGatheringState === 'complete') return res();
			var t = setTimeout(res, 3000);
			pc.addEventListener('icegatheringstatechange', function () { if (pc.iceGatheringState === 'complete') { clearTimeout(t); res(); } });
		});
	}
	function makePeer(id) {
		close(id);
		var pc = new RTCPeerConnection({ iceServers: CFG.ice });
		var p = peers[id] = { pc: pc, audio: null, t: Date.now() };
		local.getTracks().forEach(function (t) { pc.addTrack(t, local); });
		pc.ontrack = function (e) {
			if (p.audio) return;
			var a = new Audio(); a.autoplay = true; a.srcObject = e.streams[0]; a.volume = vol; a.muted = deaf;
			a.play().catch(function () { err('Clique em qualquer lugar para liberar o som.'); document.addEventListener('click', function () { a.play(); err(''); }, { once: true }); });
			p.audio = a; watch(id, e.streams[0]);
		};
		pc.oniceconnectionstatechange = function () {
			draw();
			if (pc.iceConnectionState === 'failed') { setTimeout(function () { if (peers[id] && peers[id].pc === pc) { close(id); } }, 1000); }
		};
		return p;
	}
	function close(id) {
		var p = peers[id]; if (!p) return;
		try { p.pc.close(); } catch (e) {}
		if (p.audio) { p.audio.srcObject = null; }
		delete peers[id]; delete meter[id];
	}
	function call(id) {
		var p = makePeer(id); p.caller = true;
		p.pc.createOffer().then(function (o) { return p.pc.setLocalDescription(o); })
			.then(function () { return iceDone(p.pc); })
			.then(function () { return api('voice/signal', { to: id, type: 'offer', data: p.pc.localDescription }); })
			.catch(function () { close(id); });
	}
	function onSignal(s) {
		if (s.type === 'offer') {
			var p = makePeer(s.from);
			p.pc.setRemoteDescription(s.data)
				.then(function () { return p.pc.createAnswer(); })
				.then(function (a) { return p.pc.setLocalDescription(a); })
				.then(function () { return iceDone(p.pc); })
				.then(function () { return api('voice/signal', { to: s.from, type: 'answer', data: p.pc.localDescription }); })
				.catch(function () { close(s.from); });
		} else if (s.type === 'answer') {
			var q = peers[s.from];
			if (q && q.pc.signalingState === 'have-local-offer') q.pc.setRemoteDescription(s.data).catch(function () { close(s.from); });
		} else if (s.type === 'bye') {
			close(s.from);
		}
	}
	function sync() {
		var ids = members.map(function (u) { return u.id; });
		// Quem saiu da sala.
		Object.keys(peers).forEach(function (id) { if (ids.indexOf(parseInt(id, 10)) < 0) close(id); });
		// Quem entrou: o de número menor liga (evita os dois ligarem ao mesmo tempo).
		members.forEach(function (u) {
			if (u.id === ME) return;
			var p = peers[u.id];
			var stuck = p && p.caller && p.pc.signalingState === 'have-local-offer' && Date.now() - p.t > 15000;
			if (ME < u.id && (!p || stuck)) call(u.id);
		});
	}

	/* ---------- sinal com o servidor ---------- */
	var beatT = 0, busy = false, joinedSound = {};
	function beat() {
		if (left || busy) return;
		busy = true; clearTimeout(beatT);
		api('voice/beat', { mic: micOn ? 1 : 0 }).then(function (j) {
			var before = members.map(function (u) { return u.id + ':' + u.mic; }).join(',');
			members = j.members || [];
			(j.signals || []).forEach(onSignal);
			sync();
			// Toque curto quando alguém liga o microfone.
			members.forEach(function (u) { if (u.id !== ME && u.mic && !joinedSound[u.id]) blip(); joinedSound[u.id] = u.mic; });
			if (before !== members.map(function (u) { return u.id + ':' + u.mic; }).join(',')) draw(); else draw();
			err('');
		}).catch(function (e) { err('Sem conexão com o painel: ' + e.message); })
			.then(function () {
				busy = false;
				var pending = Object.keys(peers).some(function (id) { var s = peers[id].pc.iceConnectionState; return s !== 'connected' && s !== 'completed'; });
				beatT = setTimeout(beat, pending || members.length < 2 ? 1200 : 3000);
			});
	}
	function blip() {
		try {
			ac = ac || new (window.AudioContext || window.webkitAudioContext)();
			var o = ac.createOscillator(), g = ac.createGain();
			o.frequency.value = 740; g.gain.setValueAtTime(0.0001, ac.currentTime);
			g.gain.exponentialRampToValueAtTime(0.12, ac.currentTime + 0.01); g.gain.exponentialRampToValueAtTime(0.0001, ac.currentTime + 0.18);
			o.connect(g); g.connect(ac.destination); o.start(); o.stop(ac.currentTime + 0.2);
		} catch (e) {}
	}

	function leave() {
		if (left) return; left = true;
		Object.keys(peers).forEach(function (id) { api('voice/signal', { to: parseInt(id, 10), type: 'bye', data: {} }).catch(function () {}); close(id); });
		api('voice/leave').catch(function () {});
		if (local) local.getTracks().forEach(function (t) { t.stop(); });
	}
	$('[data-sala-leave]').addEventListener('click', function () { leave(); window.close(); state('Você saiu da sala. Pode fechar esta janela.'); });
	window.addEventListener('pagehide', leave);

	/* ---------- entrar ---------- */
	if (!navigator.mediaDevices || !window.RTCPeerConnection) { err('Este navegador não tem suporte à sala de voz. Use o Chrome, Edge ou Safari atualizados.'); return; }
	navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }, video: false })
		.then(function (s) {
			local = s; setMic(false); watch(ME, s); requestAnimationFrame(levels); beat();
		})
		.catch(function () {
			err('Libere o microfone para entrar na sala (ícone de cadeado na barra de endereço). Sem microfone você ainda escuta a sala.');
			// Sem microfone: entra só para ouvir (cria uma faixa muda).
			try {
				ac = ac || new (window.AudioContext || window.webkitAudioContext)();
				local = ac.createMediaStreamDestination().stream;
			} catch (e) { local = new MediaStream(); }
			$('[data-sala-mic]').disabled = true;
			requestAnimationFrame(levels); beat();
		});
})();
