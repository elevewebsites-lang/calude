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
<body class="lk lk-auth">
<div class="auth">
	<div class="auth-side">
		<?php if ( lk_setting( 'logo' ) ) : ?><img class="auth-logo" src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt="<?php echo esc_attr( lk_setting( 'empresa' ) ); ?>"><?php endif; ?>
		<div class="auth-art" aria-hidden="false"><?php echo lk_login_art_svg(); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG fixo do tema. ?></div>
		<div class="auth-side-text">
			<span class="eyebrow">Painel da Agência</span>
			<h2>Redes sociais<br><strong>com estratégia.</strong></h2>
			<p class="auth-sub">Planejamento, aprovação, publicação e resultados em um só lugar.</p>
		</div>
		<span class="auth-copy">© <?php echo esc_html( gmdate( 'Y' ) . ' ' . lk_setting( 'empresa' ) ); ?> <?php echo lk_credit_html( 'dark' ); // phpcs:ignore ?></span>
	</div>
	<div class="auth-main">
		<form method="post" class="auth-form" data-login-form>
			<div class="gate" data-gate data-endpoint="<?php echo esc_url( rest_url( 'lk/v1/login-card' ) ); ?>" data-preview="<?php echo '1' === (string) lk_setting( 'login_card' ) ? 1 : 0; ?>" aria-live="polite">
				<div class="gate-sign"><?php if ( lk_setting( 'logo' ) ) : ?><img src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt="<?php echo esc_attr( lk_setting( 'empresa' ) ); ?>"><?php else : ?><b>LDK</b><?php endif; ?></div>
				<div class="gate-stage">
					<div class="gate-door"><div class="gate-light"><strong data-g-hello></strong><small data-g-role></small></div><i class="gate-panel gate-panel--l"></i><i class="gate-panel gate-panel--r"></i></div>
					<div class="gate-reader"><span class="gate-led" data-g-led></span><span class="gate-screen" data-g-screen>Aproxime o cartão</span><span class="gate-slot"></span></div>
					<div class="gate-card" data-g-card>
						<span class="gc-brand"><?php echo lk_setting( 'logo' ) ? '<img src="' . esc_url( lk_setting( 'logo' ) ) . '" alt="">' : '<b>LDK</b>'; // phpcs:ignore ?></span><span class="gc-title">Cartão de acesso</span>
						<span class="gc-av" data-g-av>?</span>
						<span class="gc-info"><strong data-g-name>Identifique-se</strong><small data-g-func>Digite o seu e-mail</small></span>
						<span class="gc-chip"></span><span class="gc-num" data-g-num>LDK · ····</span><span class="gc-strip"></span>
					</div>
				</div>
			</div>
			<h1>Entrar</h1>
			<p class="muted">Use o e-mail e a senha que você cadastrou.</p>
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
	</div>
</div>
<script src="<?php echo esc_url( LK_URL . 'assets/login-card.js?ver=' . LK_VERSION ); ?>"></script>
</body>
</html>
