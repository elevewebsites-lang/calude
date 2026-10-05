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
<body class="lk lk-auth">
<div class="auth">
	<div class="auth-side">
		<?php if ( lk_setting( 'logo' ) ) : ?><img class="auth-logo" src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt="<?php echo esc_attr( lk_setting( 'empresa' ) ); ?>"><?php endif; ?>
		<div class="auth-side-text">
			<span class="eyebrow">(Segurança)</span>
			<h2>Só mais um passo para proteger o painel.</h2>
		</div>
		<span class="auth-copy">© <?php echo esc_html( gmdate( 'Y' ) . ' ' . lk_setting( 'empresa' ) ); ?> <?php echo lk_credit_html( 'dark' ); // phpcs:ignore ?></span>
	</div>
	<div class="auth-main">
		<form method="post" class="auth-form">
			<h1>Digite o código</h1>
			<p class="muted">Enviamos um código de 6 números para <strong><?php echo esc_html( $masked ); ?></strong>. Ele vale por 10 minutos.</p>
			<?php if ( $error ) : ?><div class="flash flash--erro"><?php echo esc_html( $error ); ?></div><?php endif; ?>
			<?php wp_nonce_field( 'lk_2fa' ); ?>
			<?php lk_input( 'code', 'Código', '', 'text', 'inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus class="code-input"' ); ?>
			<div class="auth-row"><?php lk_check( 'confiar', 'Confiar neste aparelho por 30 dias', true ); ?></div>
			<button type="submit" class="btn btn--primary btn--block">Entrar <?php echo lk_icon( 'seta', 16 ); // phpcs:ignore ?></button>
			<p class="muted small">Não chegou? Confira o spam ou <a href="<?php echo esc_url( lk_url( 'entrar' ) ); ?>">entre de novo</a> para receber outro.</p>
		</form>
	</div>
</div>
</body>
</html>
