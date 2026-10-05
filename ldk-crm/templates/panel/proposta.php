<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$p = $id ? lk_prop_get( $id ) : null;
if ( $id && ! $p ) {
	lk_render( 'panel/404' );
}
$post = $p ? $p : (object) array( 'ID' => 0, 'post_status' => 'auto-draft' );
$live = $p && 'publish' === $p->post_status;

$acoes = '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'propostas' ) ) . '">' . lk_icon( 'lista', 16 ) . '<span>Todas as propostas</span></a>';
if ( $live ) {
	$acoes .= '<a class="btn btn--ghost" href="' . esc_url( get_permalink( $p ) ) . '" target="_blank" rel="noopener">' . lk_icon( 'olho', 16 ) . '<span>Ver como o cliente</span></a>';
}
lk_panel_start( $p ? 'Proposta · ' . $p->post_title : 'Nova proposta', 'propostas', $acoes );
lk_prop_assets();
?>

<?php if ( $live ) : ?>
	<?php
	$url     = get_permalink( $p );
	$ct      = lk_prop_contact( $p->ID );
	$views   = (int) get_post_meta( $p->ID, '_ldkp_views', true );
	$last    = get_post_meta( $p->ID, '_ldkp_last_view', true );
	$envios  = get_post_meta( $p->ID, '_lk_envios', true );
	$envios  = is_array( $envios ) ? array_reverse( $envios ) : array();
	$msg_tpl = get_post_meta( $p->ID, '_lk_msg', true );
	$msg_tpl = $msg_tpl ? $msg_tpl : "Olá, {cliente}! Tudo bem?\n\nPreparei a sua proposta comercial com tudo o que conversamos. É só abrir o link:\n{link}\n\nQualquer dúvida, estou por aqui.";
	$clients = lk_clients();
	$leads   = lk_leads( '1=1', array(), 'l.id DESC' );
	?>
	<section class="card prop-send" id="enviar">
		<div class="prop-send-head">
			<div>
				<span class="prop-tag">Proposta pronta</span>
				<h2><?php echo esc_html( $p->post_title ); ?></h2>
				<p class="muted small">
					<?php if ( $views ) : ?>
						<strong class="prop-seen">Visualizada <?php echo (int) $views; ?>×</strong><?php echo $last ? ' · última vez em ' . esc_html( mysql2date( 'd/m \à\s H:i', $last ) ) : ''; ?>
					<?php elseif ( $envios ) : ?>
						Enviada, mas o cliente ainda não abriu o link.
					<?php else : ?>
						Ainda não enviada.
					<?php endif; ?>
				</p>
			</div>
			<div class="copy-row prop-link">
				<input type="text" readonly value="<?php echo esc_attr( $url ); ?>" onclick="this.select()">
				<button type="button" class="btn btn--primary" data-copy="<?php echo esc_attr( $url ); ?>"><?php echo lk_icon( 'copiar', 16 ); // phpcs:ignore ?><span>Copiar link</span></button>
			</div>
		</div>

		<?php lk_form( 'prop_send', 'prop-send-form' ); ?>
			<input type="hidden" name="id" value="<?php echo (int) $p->ID; ?>">
			<div class="grid-2">
				<label class="field"><span>Para quem (cliente ou lead do funil)</span>
					<select name="contato" data-prop-contact>
						<option value="">Digitar o contato à mão</option>
						<?php if ( $clients ) : ?>
							<optgroup label="Clientes">
								<?php foreach ( $clients as $c ) : ?>
									<option value="c<?php echo (int) $c->id; ?>" data-phone="<?php echo esc_attr( $c->whatsapp ? $c->whatsapp : $c->phone ); ?>" data-email="<?php echo esc_attr( $c->email ); ?>" <?php selected( $ct['ref'], 'c' . $c->id ); ?>><?php echo esc_html( lk_client_label( $c ) ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endif; ?>
						<?php if ( $leads ) : ?>
							<optgroup label="Funil de leads">
								<?php foreach ( $leads as $l ) : ?>
									<option value="l<?php echo (int) $l->id; ?>" data-phone="<?php echo esc_attr( $l->whatsapp ); ?>" data-email="<?php echo esc_attr( $l->email ); ?>" <?php selected( $ct['ref'], 'l' . $l->id ); ?>><?php echo esc_html( $l->company ? $l->company . ' · ' . $l->name : $l->name ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endif; ?>
					</select>
				</label>
				<div class="grid-2 prop-contact">
					<label class="field"><span>WhatsApp</span><input type="tel" name="phone" value="<?php echo esc_attr( $ct['phone'] ); ?>" placeholder="(DDD) 00000-0000" data-prop-phone></label>
					<label class="field"><span>E-mail</span><input type="email" name="email" value="<?php echo esc_attr( $ct['email'] ); ?>" placeholder="cliente@empresa.com" data-prop-email></label>
				</div>
			</div>
			<label class="field"><span>Mensagem (use {cliente} e {link})</span><textarea name="msg" rows="5"><?php echo esc_textarea( $msg_tpl ); ?></textarea></label>
			<div class="prop-send-btns">
				<button type="submit" class="btn btn--wa" name="via" value="whatsapp" formtarget="_blank" data-prop-wa><?php echo lk_icon( 'chat', 16 ); // phpcs:ignore ?><span>Enviar pelo WhatsApp</span></button>
				<button type="submit" class="btn btn--primary" name="via" value="email" data-prop-mail><?php echo lk_icon( 'email', 16 ); // phpcs:ignore ?><span>Enviar por e-mail</span></button>
				<button type="submit" class="btn btn--ghost" name="via" value="salvar">Só salvar os dados</button>
			</div>
			<p class="muted small">O WhatsApp abre numa aba nova com a mensagem pronta; é só apertar enviar. Sem número, o WhatsApp pede para escolher o contato.</p>
		</form>

		<?php if ( $envios ) : ?>
			<details class="prop-log">
				<summary>Histórico de envios (<?php echo count( $envios ); ?>)</summary>
				<ul>
					<?php foreach ( $envios as $e ) : ?>
						<li><?php echo esc_html( lk_date( $e['at'], 'd/m/Y H:i' ) ); ?> · <?php echo esc_html( 'manual' === $e['via'] ? 'marcada como enviada' : 'por ' . $e['via'] ); ?><?php echo $e['to'] ? ' · ' . esc_html( $e['to'] ) : ''; ?> · <?php echo esc_html( get_the_author_meta( 'display_name', $e['by'] ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</details>
		<?php endif; ?>
		<div class="prop-more">
			<?php if ( ! $envios ) : ?><?php lk_action_button( 'prop_mark_sent', array( 'id' => $p->ID ), 'Já mandei por fora: marcar como enviada', 'btn btn--ghost btn--sm' ); ?><?php endif; ?>
			<?php lk_action_button( 'prop_duplicate', array( 'id' => $p->ID ), 'Duplicar para outro cliente', 'btn btn--ghost btn--sm' ); ?>
			<?php lk_action_button( 'prop_delete', array( 'id' => $p->ID ), 'Excluir proposta', 'btn btn--danger btn--sm', 'Excluir a proposta de ' . $p->post_title . '? O link para de funcionar.' ); ?>
		</div>
	</section>
<?php elseif ( $p ) : ?>
	<div class="flash">Esta proposta é um <strong>rascunho</strong> (cópia). Troque o cliente, revise e clique em <strong>Gerar proposta</strong> para criar o link.</div>
<?php endif; ?>

<div class="prop-head" id="montar">
	<h2><?php echo $live ? 'Editar a proposta' : 'Montar a proposta'; ?></h2>
	<p class="muted"><?php echo $live ? 'O link continua o mesmo: o cliente vê as mudanças assim que você salvar.' : 'Preencha o cliente, escolha o nicho e os serviços, clique em "Preencher textos automaticamente", revise e gere o link.'; ?></p>
</div>

<?php lk_form( 'prop_save', 'pn-form prop-form' ); ?>
	<input type="hidden" name="id" value="<?php echo (int) ( $p ? $p->ID : 0 ); ?>">
	<div id="ldkp_builder"<?php echo $live ? ' data-editing="1"' : ''; ?>>
		<?php ldkp_render_builder( $post ); ?>
	</div>
	<div class="prop-submit">
		<span class="muted small">Serviços, nichos e textos padrão ficam em <a href="<?php echo esc_url( lk_panel_url( 'propostas-servicos' ) ); ?>">Serviços e nichos</a>.</span>
		<button type="submit" class="btn btn--primary btn--lg"><?php echo $live ? 'Salvar alterações' : 'Gerar proposta'; ?></button>
	</div>
</form>

<script>window.LDKP_DATA = <?php echo wp_json_encode( ldkp_admin_data() ); ?>;</script>
<script src="<?php echo esc_url( LDKP_URL . 'assets/admin.js?ver=' . LDKP_VERSION ); ?>"></script>
<script>
(function () {
	// Escolher cliente/lead preenche WhatsApp e e-mail.
	var sel = document.querySelector('[data-prop-contact]');
	if (sel) {
		sel.addEventListener('change', function () {
			var o = sel.options[sel.selectedIndex];
			if (!o || !o.value) return;
			var ph = document.querySelector('[data-prop-phone]'), em = document.querySelector('[data-prop-email]');
			if (o.dataset.phone) ph.value = o.dataset.phone;
			if (o.dataset.email) em.value = o.dataset.email;
		});
	}
	// O botão de WhatsApp abre em aba nova; a página recarrega para mostrar o envio no histórico.
	var wa = document.querySelector('[data-prop-wa]');
	if (wa) wa.addEventListener('click', function () { setTimeout(function () { location.reload(); }, 1500); });
	var mail = document.querySelector('[data-prop-mail]');
	if (mail) mail.addEventListener('click', function (e) {
		var em = document.querySelector('[data-prop-email]');
		if (!em.value) { e.preventDefault(); em.focus(); em.setCustomValidity('Informe o e-mail do cliente.'); em.reportValidity(); setTimeout(function () { em.setCustomValidity(''); }, 2000); }
	});
})();
</script>
<?php
lk_panel_end();
