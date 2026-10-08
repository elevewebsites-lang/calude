<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$error = isset( $GLOBALS['lk_login_error'] ) ? $GLOBALS['lk_login_error'] : '';
$email = isset( $GLOBALS['lk_2fa_email'] ) ? $GLOBALS['lk_2fa_email'] : '';
$masked = $email ? preg_replace( '/^(.{2})[^@]*(@.*)$/', '$1***$2', $email ) : 'seu e-mail';
lk_head( 'Código de acesso' );
lk_glass_start();
?>
		<h1>Digite o código</h1>
		<p class="lg-sub">Enviamos um código de 6 números para <strong><?php echo esc_html( $masked ); ?></strong>. Ele vale por 10 minutos.</p>
		<?php if ( $error ) : ?><div class="lg-err"><?php echo esc_html( $error ); ?></div><?php endif; ?>
		<form method="post" data-lg-form>
			<?php wp_nonce_field( 'lk_2fa' ); ?>
			<label class="lg-field lg-code"><input type="text" name="code" placeholder="000000" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus></label>
			<div class="lg-row"><label class="lg-check"><input type="checkbox" name="confiar" value="1" checked> Confiar neste aparelho por 30 dias</label></div>
			<div class="lg-btn-wrap"><button type="submit" class="lg-btn" data-lg-btn>Entrar</button></div>
		</form>
		<p class="lg-alt">não chegou? <a href="<?php echo esc_url( lk_url( 'entrar' ) ); ?>">entre de novo</a></p>
<?php lk_glass_end(); ?>
