<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$q      = $id ? lk_get( 'quotes', $id ) : null;
$items  = $q ? lk_quote_items( $q ) : array();
$images = $q ? lk_json( $q->images ) : array();
$model  = $q ? lk_json( $q->model3d ) : array();
$lead   = isset( $_GET['lead'] ) ? lk_get( 'leads', absint( $_GET['lead'] ) ) : ( $q && $q->lead_id ? lk_get( 'leads', $q->lead_id ) : null );
$cid    = $q ? (int) $q->client_id : ( isset( $_GET['cliente'] ) ? absint( $_GET['cliente'] ) : 0 );
$client = $cid ? lk_get( 'clients', $cid ) : null;
$aud    = $q ? $q->audience : 'final';

// Veio da calculadora: já entra como item.
if ( ! $q && isset( $_GET['calc'] ) && ( $calc = lk_get( 'calcs', absint( $_GET['calc'] ) ) ) ) { // phpcs:ignore
	$cd      = lk_calc_data( $calc );
	$out     = $cd['out'];
	$aud     = $cd['in']['audience'] ?? 'final';
	$items[] = array(
		'kind'   => 'impressao',
		'name'   => $calc->name,
		'desc'   => '',
		'qty'    => max( 1, (int) ( $out['qty'] ?? ( $_GET['qty'] ?? 1 ) ) ),
		'unit'   => (float) ( $out['unit'] ?? $calc->price ),
		'cost'   => (float) ( $out['cost'] ?? $calc->cost ),
		'resale' => (float) ( $out['resale'] ?? 0 ),
		'calc'   => (int) $calc->id,
		'grams'  => (float) ( $out['grams_unit'] ?? 0 ),
		'hours'  => (float) ( $out['hours_unit'] ?? 0 ),
	);
	if ( ! empty( $out['model'] ) ) {
		$items[] = array( 'kind' => 'extra', 'name' => 'Modelagem 3D', 'desc' => '', 'qty' => 1, 'unit' => (float) $out['model'], 'cost' => 0, 'resale' => 0, 'calc' => 0, 'grams' => 0, 'hours' => 0 );
	}
}
if ( ! $items ) {
	$items[] = array( 'kind' => 'impressao', 'name' => '', 'desc' => '', 'qty' => 1, 'unit' => 0, 'cost' => 0, 'resale' => 0, 'calc' => 0, 'grams' => 0, 'hours' => 0 );
}
// phpcs:enable

// Cálculos salvos para puxar como item.
$calcs = array();
foreach ( lk_rows( 'calcs', '1=1', array(), 'id DESC LIMIT 40' ) as $c ) {
	$cd                = lk_calc_data( $c );
	$calcs[ $c->id ] = array(
		'name'   => $c->name,
		'unit'   => (float) ( $cd['out']['unit'] ?? $c->price ),
		'cost'   => (float) ( $cd['out']['cost'] ?? $c->cost ),
		'qty'    => (int) ( $cd['out']['qty'] ?? 1 ),
		'grams'  => (float) ( $cd['out']['grams_unit'] ?? 0 ),
		'hours'  => (float) ( $cd['out']['hours_unit'] ?? 0 ),
		'resale' => (float) ( $cd['out']['resale'] ?? 0 ),
	);
}
$products = array();
foreach ( lk_module( 'produtos' ) ? lk_rows( 'products', 'active = 1', array(), 'name' ) : array() as $pr ) {
	$products[ $pr->id ] = array( 'name' => $pr->name, 'unit' => (float) $pr->price, 'cost' => (float) $pr->cost, 'grams' => (float) $pr->grams, 'hours' => (float) $pr->print_hours, 'desc' => (string) $pr->description, 'img' => lk_product_cover( $pr ) );
}

$title = $q ? $q->title : ( $items[0]['name'] ? $items[0]['name'] : '' );
lk_panel_start( $q ? 'Orçamento · ' . $q->title : 'Novo orçamento', 'orcamentos' );
?>
<a class="back" href="<?php echo esc_url( lk_panel_url( 'orcamentos' ) ); ?>"><?php echo lk_icon( 'voltar', 16 ); // phpcs:ignore ?> Orçamentos</a>

<?php if ( $q && 'rascunho' !== $q->status ) : ?>
	<?php
	$url = lk_quote_url( $q );
	$wa  = $client && $client->whatsapp ? lk_wa_link( $client->whatsapp, 'Olá' . ( $client->name ? ', ' . strtok( $client->name, ' ' ) : '' ) . '! Preparei o seu orçamento de "' . $q->title . '". Dá para ver as fotos, aprovar e pagar por aqui (Pix com ' . str_replace( '.', ',', lk_num_setting( 'desconto_pix' ) ) . '% de desconto ou cartão em até ' . (int) lk_setting( 'parcelas_max' ) . 'x): ' . $url ) : '';
	?>
	<section class="card card--accent">
		<div class="card-head"><h3>Link do orçamento</h3><span class="badge"><?php echo esc_html( lk_quote_statuses()[ $q->status ] ?? $q->status ); ?></span><?php if ( $q->views ) : ?><span class="muted small">visto <?php echo (int) $q->views; ?>× · <?php echo esc_html( lk_ago( $q->last_view ) ); ?></span><?php endif; ?></div>
		<div class="copy-row">
			<input type="text" readonly value="<?php echo esc_attr( $url ); ?>" onclick="this.select()">
			<button type="button" class="btn btn--ghost" data-copy="<?php echo esc_attr( $url ); ?>"><?php echo lk_icon( 'copiar', 16 ); // phpcs:ignore ?><span>Copiar</span></button>
			<a class="btn btn--ghost" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'olho', 16 ); // phpcs:ignore ?><span>Ver</span></a>
			<?php if ( $wa ) : ?><a class="btn btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Enviar no WhatsApp</span></a><?php endif; ?>
		</div>
		<?php if ( $q->project_id ) : ?><p class="small" style="margin-top:10px;">Aprovado → <a href="<?php echo esc_url( lk_panel_url( 'pedido', $q->project_id ) ); ?>">pedido #<?php echo (int) $q->project_id; ?></a></p><?php endif; ?>
	</section>
<?php endif; ?>

<script>window.LK_QUOTE = <?php echo wp_json_encode( array( 'calcs' => $calcs, 'products' => $products, 'pixOff' => lk_num_setting( 'desconto_pix' ), 'resaleMult' => lk_num_setting( 'revenda_sugerido' ) ) ); ?>;</script>

<?php lk_form( 'quote_save', 'quote-grid', true ); ?>
	<input type="hidden" name="id" value="<?php echo (int) ( $q ? $q->id : 0 ); ?>">
	<input type="hidden" name="lead_id" value="<?php echo (int) ( $lead ? $lead->id : 0 ); ?>">
	<div class="quote-form" data-quote>

		<section class="card step">
			<div class="step-head"><span class="step-n">01</span><div><h3>Cliente</h3></div></div>
			<?php lk_select( 'client_id', 'Cliente', lk_client_options( '+ Cliente novo' ), $cid, 'data-new-client' ); ?>
			<div class="new-client grid-2">
				<?php lk_input( 'new_name', 'Nome', $lead ? $lead->name : '' ); ?>
				<?php lk_input( 'new_company', 'Empresa / loja', $lead ? $lead->company : '' ); ?>
				<?php lk_input( 'new_whatsapp', 'WhatsApp', $lead ? $lead->whatsapp : '', 'tel', 'data-mask="phone"' ); ?>
				<?php lk_input( 'new_email', 'E-mail', $lead ? $lead->email : '', 'email' ); ?>
				<?php lk_select( 'new_source', 'Como chegou (origem)', lk_list_options( 'origens' ), $lead ? $lead->source : '' ); ?>
			</div>
			<div class="choice-list choice-list--row">
				<?php foreach ( lk_audiences() as $k => $label ) : ?>
					<label class="choice"><input type="radio" name="audience" value="<?php echo esc_attr( $k ); ?>"<?php checked( $aud, $k ); ?>><span><strong><?php echo esc_html( $label ); ?></strong><small><?php echo esc_html( array( 'final' => 'Pessoa comprando para si ou para presente.', 'empresa' => 'Brindes, peças técnicas, lotes.', 'revenda' => 'Loja que revende: desconto maior e preço sugerido de revenda com a margem dela.' )[ $k ] ); ?></small></span></label>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="card step">
			<div class="step-head"><span class="step-n">02</span><div><h3>Apresentação</h3><p class="muted small">Fotos do item (referência, peças parecidas, render) e, se quiser, o arquivo 3D para o cliente girar na tela.</p></div></div>
			<?php lk_input( 'title', 'Título do orçamento', $title, 'text', 'required placeholder="Ex.: Capacete do Halo em tamanho real"' ); ?>
			<?php lk_input( 'intro', 'Mensagem para o cliente', $q ? $q->intro : '', 'textarea', 'rows="3" placeholder="Ex.: Oi, Ana! Segue o orçamento com as opções que conversamos…"' ); ?>
			<?php if ( $images ) : ?>
				<div class="thumbs thumbs--edit">
					<?php foreach ( $images as $img ) : ?>
						<label class="thumb-edit"><img src="<?php echo esc_url( $img['thumb'] ?? $img['url'] ); ?>" alt=""><span><input type="checkbox" name="remove_img[]" value="<?php echo (int) $img['id']; ?>"> remover</span></label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="grid-2">
				<label class="drop"><input type="file" name="photos[]" accept="image/*" multiple><?php echo lk_icon( 'imagem', 20 ); // phpcs:ignore ?><span><strong>Fotos</strong><small>JPG, PNG ou WebP · várias de uma vez</small></span></label>
				<label class="drop"><input type="file" name="model3d[]" accept=".stl,.3mf,.obj,.glb"><?php echo lk_icon( 'cubo', 20 ); // phpcs:ignore ?><span><strong>Arquivo 3D (opcional)</strong><small><?php echo $model ? 'Atual: ' . esc_html( $model['name'] ) : 'STL, 3MF, OBJ ou GLB · o cliente gira e dá zoom'; ?></small></span></label>
			</div>
			<?php if ( $model ) : ?><?php lk_check( 'remove_model', 'Remover o arquivo 3D' ); ?><?php endif; ?>
		</section>

		<section class="card step">
			<div class="step-head"><span class="step-n">03</span><div><h3>Itens</h3><p class="muted small">O cliente vê nome, descrição, quantidade e preço. O custo (com os insumos) fica só com você.</p></div></div>
			<div class="qitems" data-qitems>
				<?php foreach ( $items as $it ) : ?>
					<div class="qitem" data-qitem>
						<div class="qitem-main">
							<select name="it[kind][]" class="qitem-kind"><option value="impressao"<?php selected( $it['kind'], 'impressao' ); ?>>Impressão 3D</option><option value="extra"<?php selected( $it['kind'], 'extra' ); ?>>Item extra</option></select>
							<input type="text" name="it[name][]" value="<?php echo esc_attr( $it['name'] ); ?>" placeholder="Nome do item" class="qitem-name">
							<button type="button" class="icon-btn" data-qitem-del title="Remover">×</button>
						</div>
						<textarea name="it[desc][]" rows="2" placeholder="Descrição (cores, tamanho, acabamento…)"><?php echo esc_textarea( $it['desc'] ); ?></textarea>
						<div class="qitem-nums">
							<label class="field field--sm"><span>Qtd.</span><input type="number" min="1" name="it[qty][]" value="<?php echo (int) $it['qty']; ?>" data-q="qty"></label>
							<label class="field field--sm"><span>Preço un.</span><input type="text" name="it[unit][]" inputmode="decimal" value="<?php echo esc_attr( $it['unit'] ? number_format( $it['unit'], 2, ',', '.' ) : '' ); ?>" data-q="unit"></label>
							<label class="field field--sm"><span>Custo un.</span><input type="text" name="it[cost][]" inputmode="decimal" value="<?php echo esc_attr( $it['cost'] ? number_format( $it['cost'], 2, ',', '.' ) : '' ); ?>" data-q="cost"></label>
							<label class="field field--sm qitem-resale"><span>Revenda sug.</span><input type="text" name="it[resale][]" inputmode="decimal" value="<?php echo esc_attr( $it['resale'] ? number_format( $it['resale'], 2, ',', '.' ) : '' ); ?>" data-q="resale"></label>
							<span class="qitem-sum" data-q="sum"></span>
						</div>
						<input type="hidden" name="it[calc][]" value="<?php echo (int) $it['calc']; ?>" data-q="calc">
						<input type="hidden" name="it[grams][]" value="<?php echo esc_attr( $it['grams'] ); ?>" data-q="grams">
						<input type="hidden" name="it[hours][]" value="<?php echo esc_attr( $it['hours'] ); ?>" data-q="hours">
					</div>
				<?php endforeach; ?>
			</div>
			<div class="row-btns">
				<button type="button" class="btn btn--ghost btn--sm" data-qitem-add="impressao"><?php echo lk_icon( 'mais', 14 ); // phpcs:ignore ?><span>Peça</span></button>
				<button type="button" class="btn btn--ghost btn--sm" data-qitem-add="extra"><?php echo lk_icon( 'mais', 14 ); // phpcs:ignore ?><span>Item extra</span></button>
				<?php if ( $calcs ) : ?><button type="button" class="btn btn--ghost btn--sm" data-open="puxar-calc"><?php echo lk_icon( 'calculadora', 14 ); // phpcs:ignore ?><span>Da calculadora</span></button><?php endif; ?>
				<?php if ( $products ) : ?><button type="button" class="btn btn--ghost btn--sm" data-open="puxar-produto"><?php echo lk_icon( 'tag', 14 ); // phpcs:ignore ?><span>Do portfólio</span></button><?php endif; ?>
				<?php if ( lk_module( 'estoque' ) ) : ?><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'calculadora' ) ); ?>" target="_blank">Abrir calculadora ↗</a><?php endif; ?>
			</div>
		</section>

		<section class="card step">
			<div class="step-head"><span class="step-n">04</span><div><h3>Condições</h3></div></div>
			<div class="grid-3">
				<?php lk_input( 'discount', 'Desconto (R$)', $q && $q->discount > 0 ? number_format( $q->discount, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money data-q-discount' ); ?>
				<?php lk_input( 'deadline_days', 'Prazo de produção (dias)', $q ? $q->deadline_days : 7, 'number', 'min="0"' ); ?>
				<?php lk_input( 'valid_until', 'Válido até', $q ? $q->valid_until : gmdate( 'Y-m-d', strtotime( lk_today() . ' +' . max( 1, (int) lk_setting( 'validade_dias' ) ) . ' day' ) ), 'date' ); ?>
			</div>
			<div class="grid-3">
				<?php lk_input( 'freight', 'Frete para envio (R$)', $q && $q->freight > 0 ? number_format( $q->freight, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money data-q-freight' ); ?>
				<?php lk_input( 'freight_label', 'Serviço do frete', $q ? $q->freight_label : '', 'text', 'placeholder="Ex.: Correios PAC · 5 dias úteis"' ); ?>
				<div class="field"<?php echo lk_module( 'frete' ) ? '' : ' hidden'; ?>><span>&nbsp;</span><button type="button" class="btn btn--ghost" data-open="calc-frete"><?php echo lk_icon( 'caminhao', 16 ); // phpcs:ignore ?><span>Calcular frete</span></button></div>
			</div>
			<p class="muted small">Com frete preenchido, o cliente escolhe entre retirar em Taubaté (grátis) ou receber em casa. Sem frete, só retirada.</p>
		</section>
	</div>

	<aside class="calc-out">
		<div class="card calc-card">
			<span class="eyebrow">Resumo</span>
			<div class="calc-lines">
				<div><span>Subtotal</span><b data-qo="subtotal">—</b></div>
				<div><span>Desconto</span><b data-qo="discount">—</b></div>
				<div class="calc-total"><span>Total (cartão até <?php echo (int) lk_setting( 'parcelas_max' ); ?>x)</span><b data-qo="total">—</b></div>
				<div><span>No Pix (−<?php echo esc_html( str_replace( '.', ',', lk_num_setting( 'desconto_pix' ) ) ); ?>%)</span><b data-qo="pix">—</b></div>
				<div><span>+ frete, se envio</span><b data-qo="freight">—</b></div>
			</div>
			<span class="eyebrow">Só para você</span>
			<div class="calc-lines">
				<div><span>Custo</span><b data-qo="cost">—</b></div>
				<div><span>Lucro (no Pix)</span><b data-qo="profit" class="text-ok">—</b></div>
				<div><span>Margem</span><b data-qo="margin">—</b></div>
			</div>
			<div class="stack-btns">
				<?php if ( ! $q || 'rascunho' === $q->status ) : ?><?php lk_check( 'publicar', 'Liberar o link para o cliente', true ); ?><?php endif; ?>
				<button type="submit" class="btn btn--primary btn--block">Salvar orçamento</button>
			</div>
		</div>
		<?php if ( $q ) : ?>
			<div class="stack-btns" style="margin-top:12px;">
				<?php lk_action_button( 'quote_duplicate', array( 'id' => $q->id ), 'Duplicar', 'btn btn--ghost btn--block' ); ?>
				<?php if ( ! in_array( $q->status, array( 'aceito', 'pago' ), true ) ) : ?><?php lk_action_button( 'quote_status', array( 'id' => $q->id, 'status' => 'recusado' ), 'Marcar como recusado', 'btn btn--ghost btn--block' ); ?><?php endif; ?>
				<?php lk_action_button( 'quote_delete', array( 'id' => $q->id ), 'Excluir', 'btn btn--danger btn--block', 'Excluir este orçamento?' ); ?>
			</div>
		<?php endif; ?>
	</aside>
</form>

<?php lk_modal_start( 'puxar-calc', 'Puxar da calculadora' ); ?>
	<div class="pick-list">
		<?php foreach ( $calcs as $cid2 => $c ) : ?>
			<button type="button" class="pick" data-pick-calc="<?php echo (int) $cid2; ?>"><strong><?php echo esc_html( $c['name'] ); ?></strong><small><?php echo esc_html( $c['qty'] . ' un. · ' . lk_money( $c['unit'] ) . '/un. · custo ' . lk_money( $c['cost'] ) ); ?></small></button>
		<?php endforeach; ?>
	</div>
<?php lk_modal_end(); ?>

<?php lk_modal_start( 'puxar-produto', 'Puxar do portfólio' ); ?>
	<div class="pick-list">
		<?php foreach ( $products as $pid => $pr ) : ?>
			<button type="button" class="pick" data-pick-product="<?php echo (int) $pid; ?>"><?php if ( $pr['img'] ) : ?><img src="<?php echo esc_url( $pr['img'] ); ?>" alt=""><?php endif; ?><strong><?php echo esc_html( $pr['name'] ); ?></strong><small><?php echo esc_html( lk_money( $pr['unit'] ) ); ?></small></button>
		<?php endforeach; ?>
	</div>
<?php lk_modal_end(); ?>

<?php lk_modal_start( 'calc-frete', 'Calcular frete' ); ?>
	<form class="stack" data-freight>
		<div class="grid-3">
			<?php lk_input( 'f_cep', 'CEP do cliente', $client ? $client->cep : '', 'text', 'inputmode="numeric" placeholder="00000-000"' ); ?>
			<?php lk_input( 'f_peso', 'Peso com embalagem (g)', '', 'number', 'min="1" placeholder="300"' ); ?>
			<?php lk_select( 'f_caixa', 'Caixa', array_map( function ( $b ) { return $b['name'] . ' (' . $b['l'] . '×' . $b['w'] . '×' . $b['h'] . ' cm)'; }, lk_boxes() ) ); ?>
		</div>
		<div class="form-actions"><button type="submit" class="btn btn--primary">Cotar</button></div>
		<div class="freight-out" data-freight-out></div>
	</form>
<?php lk_modal_end(); ?>

<script src="<?php echo esc_url( LK_URL . 'assets/quote.js?ver=' . LK_VERSION ); ?>"></script>
<?php
lk_panel_end();
