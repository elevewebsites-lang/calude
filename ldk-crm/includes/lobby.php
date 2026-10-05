<?php
/**
 * Tela cheia do login: arte à esquerda (imagem de fundo + LED azul) e acesso à direita.
 * lk_lobby_start() abre a cena e o painel da direita; lk_lobby_end() fecha.
 * Imagem de fundo: Configurações → Aparência (login_bg). Sem imagem, usa a arte de redes sociais da identidade.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_lobby_logo() {
	return lk_setting( 'logo' ) ? '<img src="' . esc_url( lk_setting( 'logo' ) ) . '" alt="' . esc_attr( lk_setting( 'empresa' ) ) . '">' : '<b>LDK</b>';
}

/** $gate = true liga o cartão/leitor animado (só na tela de e-mail e senha). */
function lk_lobby_start( $gate = true ) {
	$bg = lk_setting( 'login_bg' ) ? lk_setting( 'login_bg' ) : ( file_exists( LK_DIR . 'assets/login-bg.jpg' ) ? LK_URL . 'assets/login-bg.jpg' : '' );
	?>
<div class="lobby split" <?php echo $gate ? 'data-gate data-endpoint="' . esc_url( rest_url( 'lk/v1/login-card' ) ) . '" data-preview="' . ( '1' === (string) lk_setting( 'login_card' ) ? 1 : 0 ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?> aria-live="polite">
	<section class="split-art">
		<?php if ( $bg ) : ?><div class="split-art-img" style="background-image:url('<?php echo esc_url( $bg ); ?>')" aria-hidden="true"></div><?php else : ?><div class="split-art-svg" aria-hidden="true"><?php echo lk_login_art_svg(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div><?php endif; ?>
		<i class="led led--top" aria-hidden="true"></i><i class="led led--bot" aria-hidden="true"></i><i class="led led--v" aria-hidden="true"></i>
		<div class="split-brand"><?php echo lk_lobby_logo(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<div class="split-text"><span class="eyebrow">Painel da Agência</span><h2>Redes sociais<br><strong>com estratégia.</strong></h2><p>Planejamento, aprovação, publicação e resultados em um só lugar.</p></div>
	</section>
	<main class="lobby-front">
	<?php
}

function lk_lobby_end() {
	?>
	<span class="lobby-copy">© <?php echo esc_html( gmdate( 'Y' ) . ' ' . lk_setting( 'empresa' ) ); ?> <?php echo lk_credit_html( 'dark' ); // phpcs:ignore ?></span>
	</main>
</div>
	<?php
}
