/* Gravar áudio no navegador e anexar ao formulário (pedido de ajuste / apontamento). */
(function () {
	'use strict';
	document.querySelectorAll('[data-audio-rec]').forEach(function (box) {
		var btn = box.querySelector('[data-audio-btn]'), input = box.querySelector('[data-audio-input]'), prev = box.querySelector('[data-audio-prev]');
		if (!btn || !input) return;
		if (!navigator.mediaDevices || !window.MediaRecorder) { btn.hidden = true; return; }
		var rec = null, chunks = [], stream = null;
		function stop() { if (rec && rec.state !== 'inactive') rec.stop(); }
		btn.addEventListener('click', function () {
			if (rec && rec.state === 'recording') { stop(); return; }
			navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
				stream = s; chunks = [];
				var mime = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : (MediaRecorder.isTypeSupported('audio/mp4') ? 'audio/mp4' : '');
				rec = mime ? new MediaRecorder(s, { mimeType: mime }) : new MediaRecorder(s);
				rec.ondataavailable = function (e) { if (e.data && e.data.size) chunks.push(e.data); };
				rec.onstop = function () {
					stream.getTracks().forEach(function (t) { t.stop(); });
					var type = rec.mimeType || 'audio/webm', ext = /mp4/.test(type) ? 'm4a' : 'webm';
					var file = new File(chunks, 'audio-' + Date.now() + '.' + ext, { type: type });
					try { var dt = new DataTransfer(); dt.items.add(file); input.files = dt.files; } catch (e) { alert('Seu navegador não conseguiu anexar o áudio. Escreva o pedido.'); return; }
					prev.src = URL.createObjectURL(file); prev.hidden = false;
					btn.textContent = '🎙️ Gravar de novo'; btn.classList.remove('is-rec');
				};
				rec.start();
				btn.textContent = '⏹ Parar e usar o áudio'; btn.classList.add('is-rec');
			}).catch(function () { alert('Não consegui acessar o microfone. Permita o microfone no navegador ou escreva o pedido.'); });
		});
	});
})();
