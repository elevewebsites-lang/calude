<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$client = isset( $GLOBALS['lk_invite_client'] ) ? $GLOBALS['lk_invite_client'] : null;
$error  = isset( $GLOBALS['lk_invite_error'] ) ? $GLOBALS['lk_invite_error'] : '';
$val    = function ( $key ) use ( $client ) {
	if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	return $client && isset( $client->$key ) ? $client->$key : '';
};
lk_head( 'Criar acesso' );
?>
<body class="lk lk-auth">
<div class="auth">
	<div class="auth-side">
		<?php if ( lk_setting( 'logo' ) ) : ?><img class="auth-logo" src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt="<?php echo esc_attr( lk_setting( 'empresa' ) ); ?>"><?php endif; ?>
		<div class="auth-side-text">
			<span class="eyebrow">Área do cliente</span>
			<h2>Seu marketing<br><strong>num lugar só.</strong></h2>
			<ul class="auth-list">
				<li><?php echo lk_icon( 'check', 16 ); // phpcs:ignore ?> Aprove o planejamento e cada arte com um clique</li>
				<li><?php echo lk_icon( 'check', 16 ); // phpcs:ignore ?> Relatórios do mês e resultados</li>
				<li><?php echo lk_icon( 'check', 16 ); // phpcs:ignore ?> Contrato, pagamentos e conversa com a equipe</li>
			</ul>
		</div>
		<span class="auth-copy">© <?php echo esc_html( gmdate( 'Y' ) . ' ' . lk_setting( 'empresa' ) ); ?> <?php echo lk_credit_html( 'dark' ); // phpcs:ignore ?></span>
	</div>
	<div class="auth-main">
		<?php if ( ! $client ) : ?>
			<div class="auth-form">
				<h1>Link inválido</h1>
				<p class="muted">Este convite expirou ou já foi usado. Se você já criou o seu acesso, é só entrar.</p>
				<a class="btn btn--primary btn--block" href="<?php echo esc_url( lk_url( 'entrar' ) ); ?>">Ir para o login</a>
			</div>
		<?php else : ?>
			<form method="post" class="auth-form auth-form--wide">
				<h1>Bem-vindo<?php echo $client->company ? ', ' . esc_html( $client->company ) : ''; ?>!</h1>
				<p class="muted">Complete os seus dados e crie uma senha. É com ela que você vai acessar a sua área.</p>
				<?php if ( $error ) : ?><div class="flash flash--erro"><?php echo esc_html( $error ); ?></div><?php endif; ?>
				<?php wp_nonce_field( 'lk_invite_' . $client->id ); ?>
				<div class="grid-2">
					<?php lk_input( 'name', 'Seu nome', $val( 'name' ), 'text', 'required autocomplete="name"' ); ?>
					<?php lk_input( 'company', 'Empresa', $val( 'company' ), 'text', 'autocomplete="organization"' ); ?>
					<?php lk_input( 'cnpj', 'CNPJ ou CPF', $val( 'cnpj' ), 'text', 'inputmode="numeric" data-mask="doc"' ); ?>
					<?php lk_input( 'phone', 'Telefone', $val( 'phone' ), 'tel', 'data-mask="phone" autocomplete="tel"' ); ?>
					<?php lk_input( 'whatsapp', 'WhatsApp', $val( 'whatsapp' ), 'tel', 'data-mask="phone"' ); ?>
					<?php lk_input( 'email', 'E-mail (será o seu login)', $val( 'email' ), 'email', 'required autocomplete="email"' ); ?>
				</div>
				<div class="grid-2">
					<?php lk_input( 'rep_cpf', 'CPF do responsável (para o contrato)', $val( 'rep_cpf' ), 'text', 'inputmode="numeric" data-mask="doc"' ); ?>
					<?php lk_input( 'instagram', 'Instagram da empresa', $val( 'instagram' ), 'text', 'placeholder="@suaempresa"' ); ?>
					<?php lk_input( 'site', 'Site (se tiver)', $val( 'site' ), 'url', 'placeholder="https://"' ); ?>
					<?php lk_input( 'cep', 'CEP', $val( 'cep' ), 'text', 'inputmode="numeric"' ); ?>
				</div>
				<?php lk_input( 'address', 'Endereço completo (para o contrato)', $val( 'address' ), 'text', 'autocomplete="street-address" placeholder="Rua, número, bairro"' ); ?>
				<?php lk_input( 'city', 'Cidade/UF', $val( 'city' ), 'text', 'placeholder="São José dos Campos/SP"' ); ?>
				<div class="grid-2">
					<?php lk_input( 'senha', 'Crie uma senha', '', 'password', 'required minlength="8" autocomplete="new-password"' ); ?>
					<?php lk_input( 'senha2', 'Repita a senha', '', 'password', 'required minlength="8" autocomplete="new-password"' ); ?>
				</div>
				<button type="submit" class="btn btn--primary btn--block">Criar meu acesso <?php echo lk_icon( 'seta', 16 ); // phpcs:ignore ?></button>
			</form>
		<?php endif; ?>
	</div>
</div>
<?php lk_scripts(); ?>
</body>
</html>
