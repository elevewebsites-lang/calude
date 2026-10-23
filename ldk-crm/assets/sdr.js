/* LDK · Time de SDR: "Abrir WhatsApp" abre a conversa com o texto (já editado) e o formulário registra o envio */
(function () {
	'use strict';
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-sdr-wa]');
		if (!btn) return;
		var body = btn.form.querySelector('[data-sdr-body]');
		window.open(btn.getAttribute('data-sdr-wa') + '?text=' + encodeURIComponent(body ? body.value : ''), '_blank', 'noopener');
	});
})();
