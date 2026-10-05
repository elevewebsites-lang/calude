<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$error = isset( $GLOBALS['lk_login_error'] ) ? $GLOBALS['lk_login_error'] : '';
$email = isset( $GLOBALS['lk_2fa_email'] ) ? $GLOBALS['lk_2fa_email'] : '';
// Mostra só o começo do e-mail: th***@gmail.com.
$masked = $email ? preg_replace( '/^(.{2})[^@]*(@.*)$/', '$1***$2', $email ) : 'seu e-mail';
lk_head( 'Código de acesso' );
?>
<body class="lk lk-auth lk-lobby">
<?php lk_lobby_start( false ); ?>
		<form method="post" class="lobby-form">
			<h1>Digite o código</h1>
			<p class="muted">Enviamos um código de 6 números para <strong><?php echo esc_html( $masked ); ?></strong>. Ele vale por 10 minutos.</p>
			<?php if ( $error ) : ?><div class="flash flash--erro"><?php echo esc_html( $error ); ?></div><?php endif; ?>
			<?php wp_nonce_field( 'lk_2fa' ); ?>
			<?php lk_input( 'code', 'Código', '', 'text', 'inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus class="code-input"' ); ?>
			<div class="auth-row"><?php lk_check( 'confiar', 'Confiar neste aparelho por 30 dias', true ); ?></div>
			<button type="submit" class="btn btn--primary btn--block">Entrar <?php echo lk_icon( 'seta', 16 ); // phpcs:ignore ?></button>
			<p class="muted small">Não chegou? Confira o spam ou <a href="<?php echo esc_url( lk_url( 'entrar' ) ); ?>">entre de novo</a> para receber outro.</p>
		</form>
<?php lk_lobby_end(); ?>
</body>
</html>
