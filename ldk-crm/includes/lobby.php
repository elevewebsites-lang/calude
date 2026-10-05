<?php
/**
 * Tela cheia do login: lobby com LED azul e porta de vidro com a logo.
 * lk_lobby_start() abre a cena e o painel central; lk_lobby_end() fecha.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_lobby_logo() {
	return lk_setting( 'logo' ) ? '<img src="' . esc_url( lk_setting( 'logo' ) ) . '" alt="' . esc_attr( lk_setting( 'empresa' ) ) . '">' : '<b>LDK</b>';
}

/** $gate = true liga o cartão/leitor animado (só na tela de e-mail e senha). */
function lk_lobby_start( $gate = true ) {
	$logo = lk_lobby_logo();
	?>
<div class="lobby" <?php echo $gate ? 'data-gate data-endpoint="' . esc_url( rest_url( 'lk/v1/login-card' ) ) . '" data-preview="' . ( '1' === (string) lk_setting( 'login_card' ) ? 1 : 0 ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?> aria-live="polite">
	<div class="lobby-bg" aria-hidden="true"><i class="led led--top"></i><i class="led led--top2"></i><i class="led led--l"></i><i class="led led--r"></i><i class="led led--floor"></i><i class="lobby-floorgrid"></i></div>
	<div class="lobby-door" aria-hidden="true">
		<div class="lobby-welcome"><strong data-g-hello></strong><small data-g-role></small><em>Acesso autorizado</em></div>
		<div class="pane pane--l"><span class="pane-logo"><?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput ?></span></div>
		<div class="pane pane--r"><span class="pane-logo"><?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput ?></span></div>
	</div>
	<main class="lobby-front">
	<?php
}

function lk_lobby_end() {
	?>
	</main>
	<span class="lobby-copy">© <?php echo esc_html( gmdate( 'Y' ) . ' ' . lk_setting( 'empresa' ) ); ?> <?php echo lk_credit_html( 'dark' ); // phpcs:ignore ?></span>
</div>
	<?php
}
