<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
ap_client_start( 'Meus dados', $client );
?>

<section class="chello">
	<span class="eyebrow">(Meus dados)</span>
	<h1>Seus dados de cadastro.</h1>
</section>

<?php ap_form( 'client_profile', 'card stack narrow' ); ?>
	<div class="grid-2">
		<?php ap_input( 'name', 'Nome', $client->name, 'text', 'required' ); ?>
		<?php ap_input( 'company', 'Empresa', $client->company ); ?>
		<?php ap_input( 'cnpj', 'CNPJ ou CPF', $client->cnpj, 'text', 'data-mask="doc"' ); ?>
		<?php ap_input( 'phone', 'Telefone', $client->phone, 'tel', 'data-mask="phone"' ); ?>
		<?php ap_input( 'whatsapp', 'WhatsApp', $client->whatsapp, 'tel', 'data-mask="phone"' ); ?>
		<label class="field"><span>E-mail (login)</span><input type="text" value="<?php echo esc_attr( $client->email ); ?>" disabled></label>
	</div>
	<?php ap_input( 'address', 'Endereço completo', $client->address, 'text', 'autocomplete="street-address"' ); ?>
	<h4>Trocar a senha</h4>
	<div class="grid-2">
		<?php ap_input( 'senha', 'Nova senha', '', 'password', 'minlength="8" autocomplete="new-password"' ); ?>
		<?php ap_input( 'senha2', 'Repita a nova senha', '', 'password', 'minlength="8" autocomplete="new-password"' ); ?>
	</div>
	<p class="muted small">Para trocar o e-mail de acesso, fale com a gente.</p>
	<div class="form-actions"><button type="submit" class="btn btn--primary"<?php echo ap_is_team() ? ' disabled title="Modo visualização"' : ''; ?>>Salvar</button></div>
</form>

<?php
ap_client_end();
