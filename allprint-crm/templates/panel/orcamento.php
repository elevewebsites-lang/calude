<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$q      = $id ? ap_get( 'quotes', $id ) : null;
$items  = $q ? ap_quote_items( $q ) : array();
$images = $q ? ap_json( $q->images ) : array();
$lead   = isset( $_GET['lead'] ) ? ap_get( 'leads', absint( $_GET['lead'] ) ) : ( $q && $q->lead_id ? ap_get( 'leads', $q->lead_id ) : null );
$cid    = $q ? (int) $q->client_id : ( isset( $_GET['cliente'] ) ? absint( $_GET['cliente'] ) : 0 );
$client = $cid ? ap_get( 'clients', $cid ) : null;
$aud    = $q ? $q->audience : 'final';
if ( ! $items ) {
	$items[] = array( 'kind' => 'impressao', 'name' => '', 'desc' => '', 'qty' => 1, 'unit' => 0, 'cost' => 0, 'resale' => 0, 'material' => 0, 'w' => 0, 'h' => 0 );
}
// phpcs:enable

// Catálogo para montar os itens (preço por tabela: terceirizado, empresa, pessoa física).
$cat = array();
foreach ( ap_catalog( true ) as $c ) {
	$cat[ $c->id ] = array(
		'name'  => $c->name,
		'unit'  => $c->unit ? $c->unit : 'm2',
		'p'     => array( 'parceiro' => ap_row_price( $c, 'parceiro' ), 'empresa' => ap_row_price( $c, 'empresa' ), 'pf' => ap_row_price( $c, 'pf' ) ),
		'cost'  => (float) $c->cost_material,
		'inc'   => (string) $c->included,
	);
}
$clients_tier = array();
$clients_info = array();
foreach ( ap_clients() as $cl ) {
	$clients_tier[ $cl->id ] = ap_client_tier( $cl );
	$clients_info[ $cl->id ] = array( 'first' => $cl->name ? strtok( $cl->name, ' ' ) : '', 'label' => ap_client_label( $cl ), 'wa' => (string) ( $cl->whatsapp ? $cl->whatsapp : $cl->phone ) );
}
// Proposta nova: o sistema já preenche mensagem, condições e prazo.
$auto_intro = static function ( $first ) {
	return 'Olá' . ( $first ? ', ' . $first : '' ) . '! Segue a proposta comercial da ' . ap_setting( 'empresa' ) . ' com o que conversamos: material, medidas, valor e prazo. Qualquer ajuste é só me chamar.';
};
$first0   = $client && $client->name ? strtok( $client->name, ' ' ) : ( $lead && $lead->name ? strtok( $lead->name, ' ' ) : '' );
$def_title = $client ? 'Proposta para ' . ap_client_label( $client ) : '';
$def_intro = $auto_intro( $first0 );
$def_notes = (string) ap_setting( 'empresa_nota' );
$def_days  = max( 1, (int) ap_setting( 'prazo_dias' ) );

$title = $q ? $q->title : ( $items[0]['name'] ? $items[0]['name'] : $def_title );
ap_panel_start( $q ? 'Proposta nº ' . ap_quote_number( $q ) . ' · ' . $q->title : 'Nova proposta comercial', 'orcamentos' );
?>
<a class="back" href="<?php echo esc_url( ap_panel_url( 'orcamentos' ) ); ?>"><?php echo ap_icon( 'voltar', 16 ); // phpcs:ignore ?> Propostas</a>

<?php if ( $q && 'rascunho' !== $q->status ) : ?>
	<?php
	$url = ap_quote_url( $q );
	$wa  = false && $client && $client->whatsapp ? ap_wa_link( $client->whatsapp, 'Olá' . ( $client->name ? ', ' . strtok( $client->name, ' ' ) : '' ) . '! Preparei a proposta comercial de "' . $q->title . '". Dá para ver todos os detalhes, baixar em PDF, aprovar e pagar por aqui (Pix com ' . str_replace( '.', ',', ap_num_setting( 'desconto_pix' ) ) . '% de desconto ou cartão em até ' . (int) ap_setting( 'parcelas_max' ) . 'x): ' . $url ) : '';
	?>
	<section class="card card--accent">
		<div class="card-head"><h3>Link da proposta</h3><span class="badge"><?php echo esc_html( ap_quote_statuses()[ $q->status ] ?? $q->status ); ?></span><?php if ( $q->views ) : ?><span class="muted small">visto <?php echo (int) $q->views; ?>× · <?php echo esc_html( ap_ago( $q->last_view ) ); ?></span><?php endif; ?></div>
		<div class="copy-row">
			<input type="text" readonly value="<?php echo esc_attr( $url ); ?>" onclick="this.select()">
			<button type="button" class="btn btn--ghost" data-copy="<?php echo esc_attr( $url ); ?>"><?php echo ap_icon( 'copiar', 16 ); // phpcs:ignore ?><span>Copiar</span></button>
			<a class="btn btn--ghost" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo ap_icon( 'olho', 16 ); // phpcs:ignore ?><span>Ver</span></a>
			<a class="btn btn--ghost" href="<?php echo esc_url( add_query_arg( 'pdf', 1, $url ) ); ?>" target="_blank" rel="noopener"><?php echo ap_icon( 'download', 16 ); // phpcs:ignore ?><span>PDF</span></a>
			<?php ap_action_button( 'quote_refresh', array( 'id' => $q->id ), 'Atualizar dados', 'btn btn--ghost' ); ?>
			<?php ap_action_button( 'quote_send_wa', array( 'id' => $q->id ), 'Enviar no WhatsApp', 'btn btn--wa' ); ?>
		</div>
		<?php if ( $q->project_id ) : ?><p class="small" style="margin-top:10px;">Aprovado → <a href="<?php echo esc_url( ap_panel_url( 'pedido', $q->project_id ) ); ?>">pedido #<?php echo (int) $q->project_id; ?></a></p><?php endif; ?>
	</section>
<?php endif; ?>

<?php if ( $q && 'rascunho' === $q->status ) : ?>
	<section class="card">
		<div class="card-head"><h3>Rascunho</h3><span class="badge">ainda não enviada</span></div>
		<div class="copy-row">
			<?php ap_action_button( 'quote_send_wa', array( 'id' => $q->id ), 'Enviar no WhatsApp', 'btn btn--wa' ); ?>
			<?php ap_action_button( 'quote_refresh', array( 'id' => $q->id ), 'Atualizar dados', 'btn btn--ghost' ); ?>
		</div>
	</section>
<?php endif; ?>

<script>window.AP_QUOTE = <?php echo wp_json_encode( array( 'catalog' => $cat, 'clients' => $clients_tier, 'info' => $clients_info, 'company' => ap_setting( 'empresa' ), 'pixOff' => ap_num_setting( 'desconto_pix' ) ) ); ?>;</script>

<?php ap_form( 'quote_save', 'quote-grid', true ); ?>
	<input type="hidden" name="id" value="<?php echo (int) ( $q ? $q->id : 0 ); ?>">
	<input type="hidden" name="lead_id" value="<?php echo (int) ( $lead ? $lead->id : 0 ); ?>">
	<div class="quote-form" data-quote>

		<section class="card step">
			<div class="step-head"><span class="step-n">01</span><div><h3>Cliente</h3></div></div>
			<?php ap_select( 'client_id', 'Cliente', ap_client_options( '+ Cliente novo' ), $cid, 'data-new-client' ); ?>
			<div class="new-client grid-2">
				<?php ap_input( 'new_name', 'Nome', $lead ? $lead->name : '' ); ?>
				<?php ap_input( 'new_company', 'Empresa / loja', $lead ? $lead->company : '' ); ?>
				<?php ap_input( 'new_whatsapp', 'WhatsApp', $lead ? $lead->whatsapp : '', 'tel', 'data-mask="phone"' ); ?>
				<?php ap_input( 'new_email', 'E-mail', $lead ? $lead->email : '', 'email' ); ?>
				<?php ap_select( 'new_source', 'Como chegou (origem)', ap_list_options( 'origens' ), $lead ? $lead->source : '' ); ?>
			</div>
			<div class="choice-list choice-list--row">
				<?php foreach ( ap_audiences() as $k => $label ) : ?>
					<label class="choice"><input type="radio" name="audience" value="<?php echo esc_attr( $k ); ?>"<?php checked( $aud, $k ); ?>><span><strong><?php echo esc_html( $label ); ?></strong><small><?php echo esc_html( array( 'final' => 'Pessoa comprando para si ou para presente.', 'empresa' => 'Brindes, peças técnicas, lotes.', 'revenda' => 'Loja que revende: desconto maior e preço sugerido de revenda com a margem dela.' )[ $k ] ); ?></small></span></label>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="card step">
			<div class="step-head"><span class="step-n">02</span><div><h3>Apresentação</h3><p class="muted small">O título e a mensagem abrem a proposta. As imagens (arte, layout, fotos de trabalhos parecidos) aparecem na proposta.</p></div></div>
			<?php ap_input( 'title', 'Título da proposta', $title, 'text', 'required placeholder="Ex.: Fachada com ACM e letreiro luminoso"' ); ?>
			<?php ap_input( 'intro', 'Mensagem para o cliente (já vem preenchida, edite se quiser)', $q ? $q->intro : $def_intro, 'textarea', 'rows="3" data-intro' . ( $q ? '' : ' data-auto="1"' ) ); ?>
			<?php if ( $images ) : ?>
				<div class="thumbs thumbs--edit">
					<?php foreach ( $images as $img ) : ?>
						<label class="thumb-edit"><img src="<?php echo esc_url( $img['thumb'] ?? $img['url'] ); ?>" alt=""><span><input type="checkbox" name="remove_img[]" value="<?php echo (int) $img['id']; ?>"> remover</span></label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<label class="drop"><input type="file" name="photos[]" accept="image/*" multiple><?php echo ap_icon( 'imagem', 20 ); // phpcs:ignore ?><span><strong>Imagens da proposta</strong><small>Arte, layout ou referências · JPG, PNG ou WebP · várias de uma vez</small></span></label>
		</section>

		<section class="card step">
			<div class="step-head"><span class="step-n">03</span><div><h3>Itens</h3><p class="muted small">O cliente vê material, medidas, quantidade e preço. O custo fica só com você.</p></div></div>
			<div class="qitems" data-qitems>
				<?php foreach ( $items as $it ) : ?>
					<div class="qitem" data-qitem>
						<div class="qitem-main">
							<select name="it[material][]" class="qitem-mat" data-q="material">
								<option value="0">Serviço ou item avulso</option>
								<?php foreach ( $cat as $mid => $mc ) : ?><option value="<?php echo (int) $mid; ?>"<?php selected( (int) $it['material'], (int) $mid ); ?>><?php echo esc_html( $mc['name'] ); ?></option><?php endforeach; ?>
							</select>
							<input type="hidden" name="it[kind][]" value="<?php echo esc_attr( $it['kind'] ); ?>">
							<button type="button" class="icon-btn" data-qitem-del title="Remover">×</button>
						</div>
						<input type="text" name="it[name][]" value="<?php echo esc_attr( $it['name'] ); ?>" placeholder="Nome do item (ex.: Banner da fachada)" class="qitem-name">
						<textarea name="it[desc][]" rows="2" placeholder="Detalhes (acabamento, ilhós, laminação, instalação…)"><?php echo esc_textarea( $it['desc'] ); ?></textarea>
						<div class="qitem-nums">
							<label class="field field--sm" data-sizes><span>Larg. (cm)</span><input type="text" name="it[w][]" inputmode="decimal" value="<?php echo esc_attr( $it['w'] ? str_replace( '.', ',', (string) $it['w'] ) : '' ); ?>" data-q="w"></label>
							<label class="field field--sm" data-sizes><span>Alt. (cm)</span><input type="text" name="it[h][]" inputmode="decimal" value="<?php echo esc_attr( $it['h'] ? str_replace( '.', ',', (string) $it['h'] ) : '' ); ?>" data-q="h"></label>
							<label class="field field--sm"><span>Qtd.</span><input type="number" min="1" name="it[qty][]" value="<?php echo (int) $it['qty']; ?>" data-q="qty"></label>
							<label class="field field--sm"><span>Preço un.</span><input type="text" name="it[unit][]" inputmode="decimal" value="<?php echo esc_attr( $it['unit'] ? number_format( $it['unit'], 2, ',', '.' ) : '' ); ?>" data-q="unit"></label>
							<label class="field field--sm"><span>Custo un.</span><input type="text" name="it[cost][]" inputmode="decimal" value="<?php echo esc_attr( $it['cost'] ? number_format( $it['cost'], 2, ',', '.' ) : '' ); ?>" data-q="cost"></label>
							<input type="hidden" name="it[resale][]" value="<?php echo esc_attr( $it['resale'] ); ?>" data-q="resale">
							<span class="qitem-sum" data-q="sum"></span>
						</div>
						<small class="muted qitem-inc" data-q="inc"></small>
					</div>
				<?php endforeach; ?>
			</div>
			<?php $quick = array_slice( $cat, 0, 8, true ); ?>
			<?php if ( $quick ) : ?>
				<div class="quick-chips"><span class="muted small">Atalhos:</span>
					<?php foreach ( $quick as $mid => $mc ) : ?><button type="button" class="chip" data-qitem-quick="<?php echo (int) $mid; ?>">+ <?php echo esc_html( $mc['name'] ); ?></button><?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="row-btns">
				<button type="button" class="btn btn--ghost btn--sm" data-qitem-add="impressao"><?php echo ap_icon( 'mais', 14 ); // phpcs:ignore ?><span>Item do catálogo ou serviço</span></button>
				<button type="button" class="btn btn--ghost btn--sm" data-qitem-reprice><?php echo ap_icon( 'rotinas', 14 ); // phpcs:ignore ?><span>Atualizar preços do catálogo</span></button>
			</div>
			<p class="muted small">Escolha o material, digite as medidas e o preço sai da tabela do cliente (você pode ajustar). O custo fica só com você.</p>
		</section>

		<section class="card step">
			<div class="step-head"><span class="step-n">04</span><div><h3>Condições</h3></div></div>
			<div class="grid-3">
				<?php ap_input( 'discount', 'Desconto (R$)', $q && $q->discount > 0 ? number_format( $q->discount, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money data-q-discount' ); ?>
				<?php ap_input( 'deadline_days', 'Prazo de produção (dias)', $q ? $q->deadline_days : $def_days, 'number', 'min="0"' ); ?>
				<?php ap_input( 'valid_until', 'Válido até', $q ? $q->valid_until : gmdate( 'Y-m-d', strtotime( ap_today() . ' +' . max( 1, (int) ap_setting( 'validade_dias' ) ) . ' day' ) ), 'date' ); ?>
			</div>
			<div class="grid-3">
				<?php ap_input( 'freight', 'Frete para envio (R$)', $q && $q->freight > 0 ? number_format( $q->freight, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money data-q-freight' ); ?>
				<?php ap_input( 'freight_label', 'Serviço do frete', $q ? $q->freight_label : '', 'text', 'placeholder="Ex.: Correios PAC · 5 dias úteis"' ); ?>
				<div class="field"<?php echo ap_module( 'frete' ) ? '' : ' hidden'; ?>><span>&nbsp;</span><button type="button" class="btn btn--ghost" data-open="calc-frete"><?php echo ap_icon( 'caminhao', 16 ); // phpcs:ignore ?><span>Calcular frete</span></button></div>
			</div>
			<?php ap_input( 'notes', 'Condições e observações (aparecem na proposta)', $q ? $q->notes : $def_notes, 'textarea', 'rows="3" placeholder="Ex.: Arte por conta do cliente em PDF ou CDR em curvas. Instalação não inclusa."' ); ?>
			<p class="muted small">Com frete preenchido, o cliente escolhe entre retirar na loja (grátis) ou receber em casa. Sem frete, só retirada.</p>
		</section>
	</div>

	<aside class="calc-out">
		<div class="card calc-card">
			<span class="eyebrow">Resumo</span>
			<div class="calc-lines">
				<div><span>Subtotal</span><b data-qo="subtotal">—</b></div>
				<div><span>Desconto</span><b data-qo="discount">—</b></div>
				<div class="calc-total"><span>Total (cartão até <?php echo (int) ap_setting( 'parcelas_max' ); ?>x)</span><b data-qo="total">—</b></div>
				<div><span>No Pix (−<?php echo esc_html( str_replace( '.', ',', ap_num_setting( 'desconto_pix' ) ) ); ?>%)</span><b data-qo="pix">—</b></div>
				<div><span>+ frete, se envio</span><b data-qo="freight">—</b></div>
			</div>
			<span class="eyebrow">Só para você</span>
			<div class="calc-lines">
				<div><span>Custo</span><b data-qo="cost">—</b></div>
				<div><span>Lucro (no Pix)</span><b data-qo="profit" class="text-ok">—</b></div>
				<div><span>Margem</span><b data-qo="margin">—</b></div>
			</div>
			<div class="stack-btns">
				<?php if ( ! $q || 'rascunho' === $q->status ) : ?><?php ap_check( 'publicar', 'Só salvar: liberar o link e colocar no funil', false ); ?><?php endif; ?>
				<button type="submit" class="btn btn--primary btn--block">Salvar proposta</button>
				<button type="submit" name="send_wa" value="1" class="btn btn--wa btn--block" data-send-wa><?php echo ap_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Salvar e enviar no WhatsApp</span></button>
				<p class="muted small">Um clique: salva, libera o link, abre o WhatsApp do cliente com a mensagem pronta e coloca ele no funil como "Proposta enviada".</p>
			</div>
		</div>
		<?php if ( $q ) : ?>
			<div class="stack-btns" style="margin-top:12px;">
				<?php ap_action_button( 'quote_duplicate', array( 'id' => $q->id ), 'Duplicar', 'btn btn--ghost btn--block' ); ?>
				<?php if ( ! in_array( $q->status, array( 'aceito', 'pago' ), true ) ) : ?><?php ap_action_button( 'quote_status', array( 'id' => $q->id, 'status' => 'recusado' ), 'Marcar como recusado', 'btn btn--ghost btn--block' ); ?><?php endif; ?>
				<?php ap_action_button( 'quote_delete', array( 'id' => $q->id ), 'Excluir', 'btn btn--danger btn--block', 'Excluir esta proposta?' ); ?>
			</div>
		<?php endif; ?>
	</aside>
</form>

<?php ap_modal_start( 'calc-frete', 'Calcular frete' ); ?>
	<form class="stack" data-freight>
		<div class="grid-3">
			<?php ap_input( 'f_cep', 'CEP do cliente', $client ? $client->cep : '', 'text', 'inputmode="numeric" placeholder="00000-000"' ); ?>
			<?php ap_input( 'f_peso', 'Peso com embalagem (g)', '', 'number', 'min="1" placeholder="300"' ); ?>
			<?php ap_select( 'f_caixa', 'Caixa', array_map( function ( $b ) { return $b['name'] . ' (' . $b['l'] . '×' . $b['w'] . '×' . $b['h'] . ' cm)'; }, ap_boxes() ) ); ?>
		</div>
		<div class="form-actions"><button type="submit" class="btn btn--primary">Cotar</button></div>
		<div class="freight-out" data-freight-out></div>
	</form>
<?php ap_modal_end(); ?>

<script src="<?php echo esc_url( AP_URL . 'assets/quote.js?ver=' . AP_VERSION ); ?>"></script>
<?php
ap_panel_end();
