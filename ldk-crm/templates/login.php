<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$error  = isset( $GLOBALS['lk_login_error'] ) ? $GLOBALS['lk_login_error'] : '';
$avisos = array(
	'email'      => 'Senha certa, mas o painel não conseguiu enviar o código de acesso por e-mail. Avise a administração: o envio de e-mails (SMTP) precisa ser conferido em Configurações.',
	'expirou'    => 'O código expirou. Entre de novo para receber outro.',
	'tentativas' => 'Código errado 5 vezes. Entre de novo para receber outro.',
);
$aviso = isset( $_GET['aviso'] ) ? sanitize_key( $_GET['aviso'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
if ( ! $error && isset( $avisos[ $aviso ] ) ) {
	$error = $avisos[ $aviso ];
}
$back  = isset( $_GET['volta'] ) ? esc_url_raw( wp_unslash( $_GET['volta'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
lk_head( 'Entrar' );
?>
<body class="lk lk-auth lk-lobby">
<?php lk_lobby_start( true ); ?>
		<form method="post" class="lobby-form" data-login-form>
			<div class="gate-stage">
				<div class="gate-card" data-g-card>
					<span class="gc-brand"><?php echo lk_lobby_logo(); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><span class="gc-title">Cartão de acesso</span>
					<span class="gc-av" data-g-av>?</span>
					<span class="gc-info"><strong data-g-name>Identifique-se</strong><small data-g-func>Digite o seu e-mail</small></span>
					<span class="gc-chip"></span><span class="gc-num" data-g-num>LDK · ····</span>
				</div>
				<div class="gate-reader"><span class="gate-led" data-g-led></span><span class="gate-screen" data-g-screen>Aproxime o cartão</span><span class="gate-slot"></span></div>
			</div>
			<div class="lobby-welcome"><strong data-g-hello></strong><small data-g-role></small><em>✓ Acesso autorizado</em></div>
			<h1>Acesso restrito</h1>
			<p class="muted">Só entra quem tem permissão. Use o e-mail e a senha que você cadastrou.</p>
			<?php if ( $error ) : ?><div class="flash flash--erro"><?php echo esc_html( $error ); ?></div><?php endif; ?>
			<?php wp_nonce_field( 'lk_login', 'lk_login_nonce' ); ?>
			<input type="hidden" name="volta" value="<?php echo esc_attr( $back ); ?>">
			<?php lk_input( 'email', 'E-mail', isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '', 'text', 'autocomplete="username" required autofocus' ); // phpcs:ignore WordPress.Security.NonceVerification ?>
			<?php lk_input( 'senha', 'Senha', '', 'password', 'autocomplete="current-password" required' ); ?>
			<div class="auth-row">
				<?php lk_check( 'lembrar', 'Manter conectado', true ); ?>
				<a href="<?php echo esc_url( wp_lostpassword_url( lk_url( 'entrar' ) ) ); ?>">Esqueci a senha</a>
			</div>
			<button type="submit" class="btn btn--primary btn--block">Entrar <?php echo lk_icon( 'seta', 16 ); // phpcs:ignore ?></button>
		</form>
<?php lk_lobby_end(); ?>
<script src="<?php echo esc_url( LK_URL . 'assets/login-card.js?ver=' . LK_VERSION ); ?>"></script>
</body>
</html>
