<?php
/**
 * Cadastro da equipe pelo link: /equipe-cadastro/<token>/
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$v = function ( $k ) {
	return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
};
lk_head( 'Cadastro na equipe' );
?>
<body class="lk lk-auth">
<div class="auth">
	<div class="auth-side">
		<?php if ( lk_setting( 'logo' ) ) : ?><img class="auth-logo" src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt="<?php echo esc_attr( lk_setting( 'empresa' ) ); ?>"><?php endif; ?>
		<div class="auth-side-text">
			<span class="eyebrow">Equipe <?php echo esc_html( lk_setting( 'empresa' ) ); ?></span>
			<h2>Bem-vindo(a) ao time.<br><strong>Falta pouco.</strong></h2>
			<ul class="auth-list">
				<li><?php echo lk_icon( 'check', 16 ); // phpcs:ignore ?> Preencha os seus dados e crie a sua senha</li>
				<li><?php echo lk_icon( 'check', 16 ); // phpcs:ignore ?> A <?php echo esc_html( lk_setting( 'empresa' ) ); ?> confere e libera o acesso</li>
				<li><?php echo lk_icon( 'check', 16 ); // phpcs:ignore ?> Você recebe um e-mail e já pode entrar</li>
			</ul>
		</div>
		<span class="auth-copy"><?php echo lk_credit_html( 'dark' ); // phpcs:ignore ?></span>
	</div>
	<div class="auth-main">
		<?php if ( ! $ok ) : ?>
			<div class="auth-form"><h1>Link inválido</h1><p class="muted">Este link de cadastro não vale mais. Peça um novo para a <?php echo esc_html( lk_setting( 'empresa' ) ); ?>.</p></div>
		<?php elseif ( $msg ) : ?>
			<div class="auth-form"><h1>Pronto! 🎉</h1><p class="muted"><?php echo esc_html( $msg ); ?></p></div>
		<?php else : ?>
			<form method="post" class="auth-form auth-form--wide">
				<h1>Cadastro na equipe</h1>
				<p class="muted">Seus dados ficam só com a <?php echo esc_html( lk_setting( 'empresa' ) ); ?> (pagamentos e contrato).</p>
				<?php if ( $err ) : ?><div class="flash flash--erro"><?php echo esc_html( $err ); ?></div><?php endif; ?>
				<?php wp_nonce_field( 'lk_team_join' ); ?>
				<input type="text" name="site_url_hp" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
				<div class="grid-2">
					<?php lk_input( 'name', 'Nome completo', $v( 'name' ), 'text', 'required autocomplete="name"' ); ?>
					<?php lk_input( 'email', 'E-mail (será o seu login)', $v( 'email' ), 'email', 'required autocomplete="email"' ); ?>
					<?php lk_input( 'phone', 'WhatsApp', $v( 'phone' ), 'tel', 'required data-mask="phone"' ); ?>
					<?php lk_select( 'funcao', 'Sua função', array_map( function ( $r ) { return $r[0]; }, lk_team_roles() ), $v( 'funcao' ) ); ?>
					<?php lk_input( 'cpf', 'CPF', $v( 'cpf' ), 'text', 'inputmode="numeric" data-mask="doc"' ); ?>
					<?php lk_input( 'pix', 'Chave Pix (para pagamentos)', $v( 'pix' ) ); ?>
					<?php lk_input( 'birthday', 'Data de nascimento', $v( 'birthday' ), 'date' ); ?>
					<?php lk_input( 'address', 'Endereço (cidade/UF)', $v( 'address' ) ); ?>
				</div>
				<?php lk_input( 'notes', 'Algo mais? (portfólio, disponibilidade…)', $v( 'notes' ), 'textarea', 'rows="2"' ); ?>
				<div class="grid-2">
					<?php lk_input( 'senha', 'Crie uma senha', '', 'password', 'required minlength="8" autocomplete="new-password"' ); ?>
					<?php lk_input( 'senha2', 'Repita a senha', '', 'password', 'required minlength="8" autocomplete="new-password"' ); ?>
				</div>
				<button type="submit" class="btn btn--primary btn--block">Enviar cadastro <?php echo lk_icon( 'seta', 16 ); // phpcs:ignore ?></button>
			</form>
		<?php endif; ?>
	</div>
</div>
<?php lk_scripts(); ?>
</body>
</html>
