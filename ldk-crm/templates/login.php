<?php
/**
 * Login da LDK: cartão de vidro sobre fundo azul animado, logo no topo e boas-vindas.
 */
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
$back = isset( $_GET['volta'] ) ? esc_url_raw( wp_unslash( $_GET['volta'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$hour = (int) current_time( 'G' );
$wa   = lk_setting( 'whatsapp' ) ? lk_wa_link( lk_setting( 'whatsapp' ), 'Olá! Preciso de ajuda para acessar o painel.' ) : '';
lk_head( 'Entrar' );
lk_glass_start( true );
?>
		<div class="lg-head"><h1>Seja bem-vindo</h1>
		<p class="lg-sub">Entre para acessar o painel da <?php echo esc_html( lk_setting( 'empresa' ) ? lk_setting( 'empresa' ) : 'LDK' ); ?>.</p></div>
		<form method="post" class="lobby-form" data-login-form data-lg-form>
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
			<?php if ( $error ) : ?><div class="lg-err flash--erro"><?php echo esc_html( $error ); ?></div><?php endif; ?>
			<?php wp_nonce_field( 'lk_login', 'lk_login_nonce' ); ?>
			<input type="hidden" name="volta" value="<?php echo esc_attr( $back ); ?>">
			<label class="lg-field"><?php echo lk_glass_icon( 'mail' ); // phpcs:ignore ?><input type="text" name="email" placeholder="E-mail" value="<?php echo esc_attr( isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification ?>" autocomplete="username" required autofocus></label>
			<label class="lg-field"><?php echo lk_glass_icon( 'lock' ); // phpcs:ignore ?><input type="password" name="senha" placeholder="Senha" autocomplete="current-password" required><button type="button" class="lg-eye" data-lg-eye aria-label="Mostrar senha"><?php echo lk_glass_icon( 'eye' ); // phpcs:ignore ?></button></label>
			<div class="lg-row">
				<label class="lg-check"><input type="checkbox" name="lembrar" value="1" checked> Manter conectado</label>
				<a href="<?php echo esc_url( wp_lostpassword_url( lk_url( 'entrar' ) ) ); ?>">Esqueci a senha</a>
			</div>
			<div class="lg-btn-wrap"><button type="submit" class="lg-btn" data-lg-btn>Entrar</button></div>
		</form>
		<?php if ( $wa ) : ?><p class="lg-alt">primeiro acesso? <a href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener">fale com a gente</a></p><?php endif; ?>
<?php lk_glass_end( '<script src="' . esc_url( LK_URL . 'assets/login-card.js?ver=' . LK_VERSION ) . '"></script>' ); ?>
