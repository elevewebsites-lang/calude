<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
status_header( 404 );
lk_head( 'Link indisponível' );
?>
<body class="lk lk-auth">
<div class="pay-result">
	<?php if ( lk_setting( 'logo' ) ) : ?><img class="pay-logo" src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt="<?php echo esc_attr( lk_setting( 'empresa' ) ); ?>"><?php endif; ?>
	<div class="pay-card">
		<span class="pay-ic pay-ic--warn"><?php echo lk_icon( 'alerta', 30 ); // phpcs:ignore ?></span>
		<h1>Link indisponível</h1>
		<p class="muted">Este link expirou ou não existe mais. Fale com a gente para receber um novo.</p>
		<a class="btn btn--wa btn--block" href="<?php echo esc_url( lk_wa_link( lk_setting( 'whatsapp' ), 'Olá! Recebi um link que não está abrindo.' ) ); ?>" target="_blank" rel="noopener">Falar no WhatsApp</a>
	</div>
</div>
</body>
</html>
