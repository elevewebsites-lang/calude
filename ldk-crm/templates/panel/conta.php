<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$u = wp_get_current_user();
lk_panel_start( 'Minha conta', 'conta' );
?>
<?php if ( lk_game_on() ) : ?><section class="card" style="max-width:720px"><div class="card-head"><h3>Meus pontos</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'ranking' ) ); ?>">ver ranking e prêmios</a></div><?php echo lk_game_card_html( $u->ID ); // phpcs:ignore ?></section><?php endif; ?>
<section class="card" style="max-width:720px">
	<div class="card-head"><h3>Seus dados</h3><span class="muted small"><?php echo esc_html( lk_user_role_label( $u->ID ) ); ?></span></div>
	<?php lk_form( 'my_account', 'stack' ); ?>
		<div class="grid-2">
			<?php lk_input( 'name', 'Nome', $u->display_name, 'text', 'required' ); ?>
			<?php lk_input( 'email_ro', 'E-mail (login)', $u->user_email, 'email', 'disabled' ); ?>
			<?php lk_input( 'phone', 'WhatsApp', (string) get_user_meta( $u->ID, 'lk_phone', true ), 'tel', 'data-mask="phone"' ); ?>
			<?php lk_input( 'pix', 'Chave Pix', (string) get_user_meta( $u->ID, 'lk_pix', true ) ); ?>
		</div>
		<h4 style="margin:14px 0 4px">Trocar a senha</h4>
		<p class="muted small">Deixe em branco para manter a atual.</p>
		<div class="grid-3">
			<?php lk_input( 'senha_atual', 'Senha atual', '', 'password', 'autocomplete="current-password"' ); ?>
			<?php lk_input( 'senha', 'Nova senha', '', 'password', 'minlength="8" autocomplete="new-password"' ); ?>
			<?php lk_input( 'senha2', 'Repita a nova', '', 'password', 'minlength="8" autocomplete="new-password"' ); ?>
		</div>
		<div class="form-actions"><button type="submit" class="btn btn--primary">Salvar</button></div>
	</form>
</section>
<?php
lk_panel_end();
