<?php
/**
 * Sala de voz da equipe. Abre numa janelinha à parte (a bolinha do chat abre com window.open),
 * para o áudio continuar enquanto a pessoa usa o painel na outra janela.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$me = wp_get_current_user();
lk_head( 'Sala de voz', true );
?>
<body class="lk lk-sala">
<main class="sala" data-sala>
	<header class="sala-head">
		<div>
			<strong>Sala de voz</strong>
			<small data-sala-state>Conectando…</small>
		</div>
		<button type="button" class="sala-x" data-sala-leave title="Sair da sala">Sair</button>
	</header>

	<section class="sala-grid" data-sala-grid aria-live="polite"></section>

	<p class="sala-tip" data-sala-tip>Seu microfone começa <strong>desligado</strong>. Precisa falar com alguém? Ligue o microfone (ou segure a <kbd>barra de espaço</kbd>). Todo mundo na sala escuta.</p>

	<footer class="sala-bar">
		<button type="button" class="sala-mic" data-sala-mic aria-pressed="false">
			<svg data-mic-off width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 2 20 20M18.9 13A7 7 0 0 0 19 12v-2M5 10v2a7 7 0 0 0 12 5M15 9.3V5a3 3 0 0 0-5.7-1.3M9 9v3a3 3 0 0 0 5.1 2.1M12 19v3"/></svg>
			<svg data-mic-on hidden width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v3"/></svg>
			<span data-sala-mic-t>Ligar microfone</span>
		</button>
		<label class="sala-vol" title="Volume de quem fala"><span aria-hidden="true">🔊</span><input type="range" min="0" max="1" step="0.05" value="1" data-sala-vol aria-label="Volume"></label>
		<button type="button" class="sala-ic" data-sala-mute title="Silenciar a sala (não escutar ninguém)" aria-pressed="false">🔈</button>
	</footer>
	<p class="sala-err" data-sala-err hidden></p>
</main>
<script>window.LK_SALA = <?php echo wp_json_encode( array( 'rest' => esc_url_raw( rest_url( 'lk/v1/' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'me' => (int) $me->ID, 'ice' => lk_voice_ice() ) ); ?>;</script>
<script src="<?php echo esc_url( LK_URL . 'assets/voz.js?ver=' . LK_VERSION ); ?>"></script>
</body>
</html>
