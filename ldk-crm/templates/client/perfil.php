<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
lk_client_start( 'Meus dados', $client );
?>

<section class="chello">
	<span class="eyebrow">(Meus dados)</span>
	<h1>Seus dados de cadastro.</h1>
</section>

<?php lk_form( 'client_profile', 'card stack narrow' ); ?>
	<div class="grid-2">
		<?php lk_input( 'name', 'Nome', $client->name, 'text', 'required' ); ?>
		<?php lk_input( 'company', 'Empresa', $client->company ); ?>
		<?php lk_input( 'cnpj', 'CNPJ ou CPF', $client->cnpj, 'text', 'data-mask="doc"' ); ?>
		<?php lk_input( 'phone', 'Telefone', $client->phone, 'tel', 'data-mask="phone"' ); ?>
		<?php lk_input( 'whatsapp', 'WhatsApp', $client->whatsapp, 'tel', 'data-mask="phone"' ); ?>
		<label class="field"><span>E-mail (login)</span><input type="text" value="<?php echo esc_attr( $client->email ); ?>" disabled></label>
	</div>
	<?php lk_input( 'address', 'Endereço completo', $client->address, 'text', 'autocomplete="street-address"' ); ?>
	<h4>Trocar a senha</h4>
	<div class="grid-2">
		<?php lk_input( 'senha', 'Nova senha', '', 'password', 'minlength="8" autocomplete="new-password"' ); ?>
		<?php lk_input( 'senha2', 'Repita a nova senha', '', 'password', 'minlength="8" autocomplete="new-password"' ); ?>
	</div>
	<p class="muted small">Para trocar o e-mail de acesso, fale com a gente.</p>
	<div class="form-actions"><button type="submit" class="btn btn--primary"<?php echo lk_is_team() ? ' disabled title="Modo visualização"' : ''; ?>>Salvar</button></div>
</form>

<?php
lk_client_end();
