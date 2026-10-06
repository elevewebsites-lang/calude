<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$error = $GLOBALS['ap_signup_error'] ?? '';
$old   = function ( $k ) {
	return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
};
ap_head( 'Cadastro de cliente' );
?>
<body class="ap ap-auth">
<div class="auth">
	<div class="auth-side">
		<?php echo ap_logo_for( 'auth', 'auth-logo' ); // phpcs:ignore ?>
		<div class="auth-side-text">
			<span class="eyebrow">Área de clientes</span>
			<h2>Sua gráfica parceira<br><strong>de grande formato.</strong></h2>
			<ul class="auth-list">
				<li><?php echo ap_icon( 'check', 16 ); // phpcs:ignore ?> Tabela de preços por m²</li>
				<li><?php echo ap_icon( 'check', 16 ); // phpcs:ignore ?> Acabamentos e previsão de entrega</li>
				<li><?php echo ap_icon( 'check', 16 ); // phpcs:ignore ?> Envio de arquivos pesados e pagamento no Pix</li>
				<li><?php echo ap_icon( 'check', 16 ); // phpcs:ignore ?> Acompanhe cada pedido até a retirada</li>
			</ul>
		</div>
		<span class="auth-copy">© <?php echo esc_html( gmdate( 'Y' ) . ' ' . ap_setting( 'empresa' ) ); ?> <?php echo ap_credit_html( 'dark' ); // phpcs:ignore ?></span>
	</div>
	<div class="auth-main">
		<form method="post" class="auth-form auth-form--wide">
			<h1>Cadastro de cliente</h1>
			<p class="muted">Atendemos gráficas, agências, revendedores e empresas. Depois da aprovação, você vê os preços e faz pedidos pelo painel.</p>
			<?php if ( $error ) : ?><div class="flash flash--erro"><?php echo esc_html( $error ); ?></div><?php endif; ?>
			<?php wp_nonce_field( 'ap_signup' ); ?>
			<input type="text" name="site_url" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
			<div class="grid-2">
				<?php ap_input( 'company', 'Empresa', $old( 'company' ), 'text', 'required autocomplete="organization"' ); ?>
				<?php ap_input( 'cnpj', 'CPF ou CNPJ', $old( 'cnpj' ), 'text', 'required inputmode="numeric" data-mask="doc"' ); ?>
				<?php ap_input( 'name', 'Responsável', $old( 'name' ), 'text', 'required autocomplete="name"' ); ?>
				<?php ap_input( 'whatsapp', 'Telefone / WhatsApp', $old( 'whatsapp' ), 'tel', 'required data-mask="phone" autocomplete="tel"' ); ?>
				<?php ap_input( 'email', 'E-mail (será o seu login)', $old( 'email' ), 'email', 'required autocomplete="email"' ); ?>
				<?php ap_input( 'senha', 'Crie uma senha', '', 'password', 'required minlength="8" autocomplete="new-password"' ); ?>
			</div>
			<?php ap_input( 'address', 'Endereço (opcional)', $old( 'address' ), 'text', 'autocomplete="street-address"' ); ?>
			<button type="submit" class="btn btn--primary btn--block">Enviar cadastro</button>
			<p class="muted small">Já tem cadastro? <a href="<?php echo esc_url( ap_url( 'entrar' ) ); ?>">Entrar</a></p>
		</form>
	</div>
</div>
<?php ap_scripts(); ?>
</body>
</html>
