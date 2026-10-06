<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$client = isset( $GLOBALS['ap_invite_client'] ) ? $GLOBALS['ap_invite_client'] : null;
$error  = isset( $GLOBALS['ap_invite_error'] ) ? $GLOBALS['ap_invite_error'] : '';
$val    = function ( $key ) use ( $client ) {
	if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	return $client && isset( $client->$key ) ? $client->$key : '';
};
ap_head( 'Criar acesso' );
?>
<body class="ap ap-auth">
<div class="auth">
	<div class="auth-side">
		<?php echo ap_logo_for( 'auth', 'auth-logo' ); // phpcs:ignore ?>
		<div class="auth-side-text">
			<span class="eyebrow">Área do cliente</span>
			<h2>Acompanhe o seu pedido<br><strong>em tempo real.</strong></h2>
			<ul class="auth-list">
				<li><?php echo ap_icon( 'check', 16 ); // phpcs:ignore ?> Cada etapa: fila, impressão, acabamento</li>
				<li><?php echo ap_icon( 'check', 16 ); // phpcs:ignore ?> Fotos da sua peça pronta</li>
				<li><?php echo ap_icon( 'check', 16 ); // phpcs:ignore ?> Orçamentos e pagamentos</li>
			</ul>
		</div>
		<span class="auth-copy">© <?php echo esc_html( gmdate( 'Y' ) . ' ' . ap_setting( 'empresa' ) ); ?> <?php echo ap_credit_html( 'dark' ); // phpcs:ignore ?></span>
	</div>
	<div class="auth-main">
		<?php if ( ! $client ) : ?>
			<div class="auth-form">
				<h1>Link inválido</h1>
				<p class="muted">Este convite expirou ou já foi usado. Se você já criou o seu acesso, é só entrar.</p>
				<a class="btn btn--primary btn--block" href="<?php echo esc_url( ap_url( 'entrar' ) ); ?>">Ir para o login</a>
			</div>
		<?php else : ?>
			<form method="post" class="auth-form auth-form--wide">
				<h1>Bem-vindo<?php echo $client->company ? ', ' . esc_html( $client->company ) : ''; ?>!</h1>
				<p class="muted">Complete os seus dados e crie uma senha. É com ela que você vai acessar a sua área.</p>
				<?php if ( $error ) : ?><div class="flash flash--erro"><?php echo esc_html( $error ); ?></div><?php endif; ?>
				<?php wp_nonce_field( 'ap_invite_' . $client->id ); ?>
				<div class="grid-2">
					<?php ap_input( 'name', 'Seu nome', $val( 'name' ), 'text', 'required autocomplete="name"' ); ?>
					<?php ap_input( 'company', 'Empresa', $val( 'company' ), 'text', 'autocomplete="organization"' ); ?>
					<?php ap_input( 'cnpj', 'CNPJ ou CPF', $val( 'cnpj' ), 'text', 'inputmode="numeric" data-mask="doc"' ); ?>
					<?php ap_input( 'phone', 'Telefone', $val( 'phone' ), 'tel', 'data-mask="phone" autocomplete="tel"' ); ?>
					<?php ap_input( 'whatsapp', 'WhatsApp', $val( 'whatsapp' ), 'tel', 'data-mask="phone"' ); ?>
					<?php ap_input( 'email', 'E-mail (será o seu login)', $val( 'email' ), 'email', 'required autocomplete="email"' ); ?>
				</div>
				<?php ap_input( 'address', 'Endereço completo (para o contrato)', $val( 'address' ), 'text', 'autocomplete="street-address" placeholder="Rua, número, bairro, cidade/UF, CEP"' ); ?>
				<div class="grid-2">
					<?php ap_input( 'senha', 'Crie uma senha', '', 'password', 'required minlength="8" autocomplete="new-password"' ); ?>
					<?php ap_input( 'senha2', 'Repita a senha', '', 'password', 'required minlength="8" autocomplete="new-password"' ); ?>
				</div>
				<button type="submit" class="btn btn--primary btn--block">Criar meu acesso <?php echo ap_icon( 'seta', 16 ); // phpcs:ignore ?></button>
			</form>
		<?php endif; ?>
	</div>
</div>
<?php ap_scripts(); ?>
</body>
</html>
