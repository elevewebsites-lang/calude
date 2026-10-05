<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$k = lk_get( 'contracts', $id );
if ( ! $k ) {
	lk_render( 'panel/404' );
}
$client = lk_get( 'clients', $k->client_id );
$wa     = get_transient( 'lk_contract_wa_' . get_current_user_id() );
if ( $wa ) {
	delete_transient( 'lk_contract_wa_' . get_current_user_id() );
}
$acoes = '<a class="btn btn--ghost" href="' . esc_url( lk_contract_url( $k ) ) . '" target="_blank" rel="noopener">' . lk_icon( 'olho', 16 ) . '<span>Ver como a cliente</span></a>';
lk_panel_start( 'Contrato · ' . lk_client_label( $client ), 'clientes', $acoes );
?>
<a class="back" href="<?php echo esc_url( lk_panel_url( 'cliente', $client->id ) ); ?>#contratos"><?php echo lk_icon( 'voltar', 16 ); // phpcs:ignore ?> <?php echo esc_html( lk_client_label( $client ) ); ?></a>

<?php if ( $wa ) : ?><section class="card card--accent ready-bar"><div><strong>Enviado por e-mail.</strong> Reforce pelo WhatsApp:</div><a class="btn btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Mandar no WhatsApp</span></a></section><?php endif; ?>

<section class="card flow-card">
	<div class="flow-now">
		<span class="muted small"><?php echo esc_html( $k->title ); ?></span>
		<strong><em class="badge <?php echo 'concluido' === $k->status ? 'badge--ok' : ( 'cancelado' === $k->status ? 'badge--late' : '' ); ?>"><?php echo esc_html( lk_contract_status_label( $k->status ) ); ?></em></strong>
		<span class="muted small"><?php echo esc_html( lk_money( $k->monthly_value ) . '/mês · ' . (int) $k->months . ' meses · vence dia ' . (int) $k->due_day ); ?><?php echo $k->sent_at ? ' · enviado ' . esc_html( lk_date( $k->sent_at, 'd/m H:i' ) ) : ''; ?><?php echo $k->viewed_at ? ' · a cliente abriu ' . esc_html( lk_date( $k->viewed_at, 'd/m H:i' ) ) : ''; ?></span>
	</div>
	<div class="row-btns">
		<?php if ( 'rascunho' === $k->status ) : ?>
			<?php lk_action_button( 'contract_send', array( 'id' => $k->id ), lk_icon( 'email', 16 ) . '<span>Enviar para assinatura</span>', 'btn btn--primary', 'Enviar o contrato para ' . $client->email . '? Depois de enviado, o texto não muda mais.' ); ?>
			<button type="button" class="btn btn--ghost" data-open="contrato-dados">Editar dados</button>
		<?php elseif ( 'enviado' === $k->status ) : ?>
			<button type="button" class="btn btn--ghost" data-copy="<?php echo esc_attr( lk_contract_url( $k ) ); ?>">Copiar link</button>
			<?php if ( $client->whatsapp ) : ?><a class="btn btn--wa" href="<?php echo esc_url( lk_wa_link( $client->whatsapp, 'Olá! O seu contrato com a ' . lk_setting( 'empresa' ) . ' está pronto para assinar: ' . lk_contract_url( $k ) ) ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>WhatsApp</span></a><?php endif; ?>
		<?php endif; ?>
		<?php if ( in_array( $k->status, array( 'enviado', 'assinado' ), true ) && ! $k->agency_signed_at && lk_is_admin() ) : ?>
			<button type="button" class="btn btn--primary" data-open="assinar-agencia">✍️ Assinar pela <?php echo esc_html( lk_setting( 'empresa' ) ); ?></button>
		<?php endif; ?>
		<?php lk_action_button( 'contract_duplicate', array( 'id' => $k->id ), 'Duplicar', 'btn btn--ghost' ); ?>
		<?php if ( ! in_array( $k->status, array( 'concluido', 'cancelado' ), true ) ) : ?><?php lk_action_button( 'contract_cancel', array( 'id' => $k->id ), 'Cancelar', 'btn btn--danger', 'Cancelar este contrato? O link deixa de funcionar.' ); ?><?php endif; ?>
	</div>
</section>

<?php echo lk_contract_parties_html( $k, $client ); // phpcs:ignore ?>
<?php if ( 'rascunho' === $k->status ) : ?>
<section class="card" id="clausulas">
	<div class="card-head"><h3>Observações e novas cláusulas</h3><span class="muted small">entram no contrato como cláusulas adicionais</span></div>
	<?php lk_form( 'contract_clauses', 'stack' ); ?>
		<input type="hidden" name="id" value="<?php echo (int) $k->id; ?>">
		<?php lk_input( 'clauses', 'Uma cláusula por linha', (string) $k->clauses, 'textarea', 'rows="4" placeholder="Ex.: O cliente se compromete a enviar os acessos em até 5 dias úteis.&#10;Ex.: Reunião mensal de alinhamento de 30 minutos."' ); ?>
		<div class="form-actions"><button type="submit" class="btn btn--primary btn--sm">Incluir no contrato</button></div>
	</form>
</section>
<?php elseif ( trim( (string) $k->clauses ) ) : ?>
<section class="card"><div class="card-head"><h3>Cláusulas adicionais</h3></div><ul class="mini-list"><?php foreach ( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $k->clauses ) ) ) as $cl ) : ?><li><span><?php echo esc_html( $cl ); ?></span></li><?php endforeach; ?></ul></section>
<?php endif; ?>
<?php if ( $k->drive_url ) : ?><p class="muted small">📁 Cópia assinada guardada no Google Drive: <a href="<?php echo esc_url( $k->drive_url ); ?>" target="_blank" rel="noopener">abrir</a> · também na área do cliente e enviada por e-mail para as duas partes.</p><?php endif; ?>
<div class="contract-grid">
	<section class="card contract-doc">
		<?php if ( 'rascunho' === $k->status ) : ?>
			<details class="contract-edit">
				<summary>✏️ Ajustar o texto à mão</summary>
				<?php lk_form( 'contract_body', 'stack' ); ?>
					<input type="hidden" name="id" value="<?php echo (int) $k->id; ?>">
					<textarea name="body" rows="24" class="mono"><?php echo esc_textarea( $k->body ); ?></textarea>
					<p class="muted small">"## " vira título, "**texto**" vira negrito e linhas com "- " viram lista. Campos com "__________" estão faltando na ficha ou nas Configurações.</p>
					<div class="form-actions"><button type="submit" class="btn btn--primary btn--sm">Salvar texto</button></div>
				</form>
			</details>
		<?php endif; ?>
		<div class="contract-text"><?php echo lk_contract_html( $k->body ); // phpcs:ignore ?></div>
	</section>
	<aside class="contract-side">
		<section class="card">
			<div class="card-head"><h3>Assinaturas</h3></div>
			<div class="sig-box">
				<strong><?php echo esc_html( lk_client_label( $client ) ); ?></strong>
				<?php if ( $k->signed_at ) : ?>
					<img src="<?php echo esc_attr( $k->signer_sig ); ?>" alt="Assinatura da cliente">
					<small><?php echo esc_html( $k->signer_name . ' · ' . $k->signer_doc ); ?><br><?php echo esc_html( lk_date( $k->signed_at, 'd/m/Y H:i' ) . ' · IP ' . $k->signer_ip ); ?></small>
				<?php else : ?><small class="muted">aguardando</small><?php endif; ?>
			</div>
			<div class="sig-box">
				<strong><?php echo esc_html( lk_setting( 'empresa' ) ); ?></strong>
				<?php if ( $k->agency_signed_at ) : ?>
					<img src="<?php echo esc_attr( $k->agency_sig ); ?>" alt="Assinatura da agência">
					<small><?php echo esc_html( $k->agency_name . ( $k->agency_doc ? ' · ' . $k->agency_doc : '' ) ); ?><br><?php echo esc_html( lk_date( $k->agency_signed_at, 'd/m/Y H:i' ) ); ?></small>
				<?php else : ?><small class="muted">aguardando</small><?php endif; ?>
			</div>
			<?php if ( $k->hash ) : ?><p class="muted small" style="word-break:break-all">SHA-256: <?php echo esc_html( $k->hash ); ?></p><?php endif; ?>
		</section>
	</aside>
</div>

<?php if ( 'rascunho' === $k->status ) : ?>
	<?php lk_modal_start( 'contrato-dados', 'Dados do contrato' ); ?>
		<?php lk_contract_form( $client, $k ); ?>
	<?php lk_modal_end(); ?>
<?php endif; ?>

<?php if ( lk_is_admin() ) : ?>
	<?php lk_modal_start( 'assinar-agencia', 'Assinar pela ' . lk_setting( 'empresa' ) ); ?>
		<?php lk_form( 'contract_agency_sign', 'stack' ); ?>
			<input type="hidden" name="id" value="<?php echo (int) $k->id; ?>">
			<div class="grid-2">
				<?php lk_input( 'nome', 'Nome de quem assina', lk_setting( 'empresa_representante' ) ? lk_setting( 'empresa_representante' ) : wp_get_current_user()->display_name, 'text', 'required' ); ?>
				<?php lk_input( 'doc', 'CPF', '', 'text', 'data-mask="doc"' ); ?>
			</div>
			<?php lk_sig_pad(); ?>
			<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Assinar</button></div>
		</form>
	<?php lk_modal_end(); ?>
<?php endif; ?>
<script src="<?php echo esc_url( LK_URL . 'assets/assinatura.js?ver=' . LK_VERSION ); ?>"></script>
<?php
lk_panel_end();
