<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
status_header( 404 );
ap_head( 'Link indisponível' );
?>
<body class="ap ap-auth">
<div class="pay-result">
	<?php echo ap_logo_for( 'pay', 'pay-logo' ); // phpcs:ignore ?>
	<div class="pay-card">
		<span class="pay-ic pay-ic--warn"><?php echo ap_icon( 'alerta', 30 ); // phpcs:ignore ?></span>
		<h1>Link indisponível</h1>
		<p class="muted">Este link expirou ou não existe mais. Fale com a gente para receber um novo.</p>
		<a class="btn btn--wa btn--block" href="<?php echo esc_url( ap_wa_link( ap_setting( 'whatsapp' ), 'Olá! Recebi um link que não está abrindo.' ) ); ?>" target="_blank" rel="noopener">Falar no WhatsApp</a>
	</div>
</div>
</body>
</html>
