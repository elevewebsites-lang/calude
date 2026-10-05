/* LDK · apontamentos: pular para o instante do vídeo e pegar o tempo atual do player */
document.addEventListener('click', function (e) {
	'use strict';
	function find(mi) { return (mi !== '' && mi !== null && document.querySelector('video[data-mi="' + mi + '"]')) || document.querySelector('video'); }
	var s = e.target.closest('[data-seek]');
	if (s) {
		var v = find(s.getAttribute('data-mi'));
		if (v) { v.currentTime = +s.getAttribute('data-seek'); v.scrollIntoView({ block: 'center', behavior: 'smooth' }); v.play().catch(function () {}); }
		return;
	}
	var g = e.target.closest('[data-grab]');
	if (g) {
		var f = g.closest('form'), sel = f.querySelector('[name=media_i]'), vid = find(sel ? sel.value : '');
		if (!vid) { alert('Nenhum vídeo para marcar o tempo.'); return; }
		vid.pause();
		var t = Math.floor(vid.currentTime);
		f.querySelector('[name=at]').value = Math.floor(t / 60) + ':' + ('0' + (t % 60)).slice(-2);
		if (sel && vid.getAttribute('data-mi') !== null) sel.value = vid.getAttribute('data-mi');
	}
});
