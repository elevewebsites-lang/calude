<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$error = isset( $GLOBALS['ap_login_error'] ) ? $GLOBALS['ap_login_error'] : '';
$back  = isset( $_GET['volta'] ) ? esc_url_raw( wp_unslash( $_GET['volta'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
ap_head( 'Entrar' );
?>
<body class="ap ap-auth">
<div class="auth">
	<div class="auth-side">
		<?php echo ap_logo_for( 'auth', 'auth-logo' ); // phpcs:ignore ?>
		<div class="auth-side-text">
			<span class="eyebrow">Área de clientes</span>
			<h2>Sua gráfica parceira<br><strong>de grande formato.</strong></h2>
		</div>
		<span class="auth-copy">© <?php echo esc_html( gmdate( 'Y' ) . ' ' . ap_setting( 'empresa' ) ); ?> <?php echo ap_credit_html( 'dark' ); // phpcs:ignore ?></span>
	</div>
	<div class="auth-main">
		<form method="post" class="auth-form">
			<h1>Entrar</h1>
			<p class="muted">Use o e-mail e a senha que você cadastrou.</p>
			<?php if ( $error ) : ?><div class="flash flash--erro"><?php echo esc_html( $error ); ?></div><?php endif; ?>
			<?php wp_nonce_field( 'ap_login', 'ap_login_nonce' ); ?>
			<input type="hidden" name="volta" value="<?php echo esc_attr( $back ); ?>">
			<?php ap_input( 'email', 'E-mail', isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '', 'text', 'autocomplete="username" required autofocus' ); // phpcs:ignore WordPress.Security.NonceVerification ?>
			<?php ap_input( 'senha', 'Senha', '', 'password', 'autocomplete="current-password" required' ); ?>
			<div class="auth-row">
				<?php ap_check( 'lembrar', 'Manter conectado', true ); ?>
				<a href="<?php echo esc_url( wp_lostpassword_url( ap_url( 'entrar' ) ) ); ?>">Esqueci a senha</a>
			</div>
			<button type="submit" class="btn btn--primary btn--block">Entrar <?php echo ap_icon( 'seta', 16 ); // phpcs:ignore ?></button>
			<p class="muted small">Ainda não é cliente? <a href="<?php echo esc_url( ap_url( 'cadastro' ) ); ?>">Faça o seu cadastro</a></p>
		</form>
	</div>
</div>
</body>
</html>
