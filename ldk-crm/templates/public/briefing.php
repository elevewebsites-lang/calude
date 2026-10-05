<?php
/**
 * Briefing do cliente: /briefing/<token>/ (sem login). Pode voltar e editar quando quiser.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
lk_head( 'Briefing · ' . lk_client_label( $c ) );
?>
<body class="lk ap-page">
<div class="apv pln">
	<header class="apv-top"><?php if ( lk_setting( 'logo' ) ) : ?><img src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt="<?php echo esc_attr( lk_setting( 'empresa' ) ); ?>"><?php endif; ?><span><?php echo esc_html( lk_client_label( $c ) ); ?></span></header>
	<section class="pln-hero">
		<span class="apv-eyebrow">Briefing</span>
		<h1>Vamos nos conhecer melhor</h1>
		<p>Quanto mais a gente souber sobre a sua marca, mais certeiros ficam os conteúdos. Responda com calma; nada é obrigatório e dá para voltar e editar depois.</p>
	</section>
	<?php if ( $msg ) : ?><div class="apv-msg pln-msg"><?php echo esc_html( $msg ); ?></div><?php endif; ?>
	<?php if ( $c->briefing_at && ! $msg ) : ?><div class="apv-msg pln-msg">Você respondeu em <?php echo esc_html( lk_date( $c->briefing_at, 'd/m/Y' ) ); ?>. Pode editar e salvar de novo.</div><?php endif; ?>
	<form method="post" class="pln-form brf-form">
		<?php wp_nonce_field( 'lk_briefing_' . $c->id ); ?>
		<?php lk_briefing_fields( $c ); ?>
		<div class="pln-end"><div class="pln-btns"><button type="submit" class="apv-btn apv-btn--ok">Salvar respostas</button></div></div>
	</form>
	<footer class="apv-foot"><?php echo lk_credit_html( 'dark' ); // phpcs:ignore ?></footer>
</div>
</body>
</html>
