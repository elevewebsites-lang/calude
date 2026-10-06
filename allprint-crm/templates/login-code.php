<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$error = isset( $GLOBALS['ap_login_error'] ) ? $GLOBALS['ap_login_error'] : '';
$email = isset( $GLOBALS['ap_2fa_email'] ) ? $GLOBALS['ap_2fa_email'] : '';
// Mostra só o começo do e-mail: th***@gmail.com.
$masked = $email ? preg_replace( '/^(.{2})[^@]*(@.*)$/', '$1***$2', $email ) : 'seu e-mail';
ap_head( 'Código de acesso' );
?>
<body class="ap ap-auth">
<div class="auth">
	<div class="auth-side">
		<?php echo ap_logo_for( 'auth', 'auth-logo' ); // phpcs:ignore ?>
		<div class="auth-side-text">
			<span class="eyebrow">(Segurança)</span>
			<h2>Só mais um passo para proteger o painel.</h2>
		</div>
		<span class="auth-copy">© <?php echo esc_html( gmdate( 'Y' ) . ' ' . ap_setting( 'empresa' ) ); ?> <?php echo ap_credit_html( 'dark' ); // phpcs:ignore ?></span>
	</div>
	<div class="auth-main">
		<form method="post" class="auth-form">
			<h1>Digite o código</h1>
			<p class="muted">Enviamos um código de 6 números para <strong><?php echo esc_html( $masked ); ?></strong>. Ele vale por 10 minutos.</p>
			<?php if ( $error ) : ?><div class="flash flash--erro"><?php echo esc_html( $error ); ?></div><?php endif; ?>
			<?php wp_nonce_field( 'ap_2fa' ); ?>
			<?php ap_input( 'code', 'Código', '', 'text', 'inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus class="code-input"' ); ?>
			<div class="auth-row"><?php ap_check( 'confiar', 'Confiar neste aparelho por 30 dias', true ); ?></div>
			<button type="submit" class="btn btn--primary btn--block">Entrar <?php echo ap_icon( 'seta', 16 ); // phpcs:ignore ?></button>
			<p class="muted small">Não chegou? Confira o spam ou <a href="<?php echo esc_url( ap_url( 'entrar' ) ); ?>">entre de novo</a> para receber outro.</p>
		</form>
	</div>
</div>
</body>
</html>
