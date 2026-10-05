<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$p = $id ? lk_project( $id ) : null;
if ( ! $p ) {
	lk_render( 'panel/404' );
}
$client  = $p->client_id ? lk_get( 'clients', $p->client_id ) : null;
$cols    = lk_columns();
$items   = lk_order_items( $p );
$photos  = lk_order_photos( $p );
$quote   = $p->quote_id ? lk_get( 'quotes', $p->quote_id ) : null;
$qimgs   = $quote ? lk_json( $quote->images ) : array();
$model   = $quote ? lk_json( $quote->model3d ) : array();
$trans   = lk_rows( 'transactions', 'project_id = %d', array( $p->id ), 'due_date, id' );
$moves   = lk_rows( 'stock_moves', "project_id = %d AND kind = 'uso'", array( $p->id ), 'id' );
$log     = lk_rows( 'activity', 'project_id = %d', array( $p->id ), 'id DESC LIMIT 40' );
$fils    = lk_rows( 'filaments', 'active = 1', array(), 'material, color_name' );
$sups    = lk_rows( 'supplies', 'active = 1', array(), 'name' );
$prns    = lk_rows( 'printers', 'active = 1' );
$cost    = $p->cost_real > 0 ? $p->cost_real : $p->cost_estimated;
$profit  = $p->value - $p->ship_price - $cost;
$ready   = lk_ready_column();
$wa      = lk_order_wa_link( $p );
$fin     = lk_can( 'financeiro' );

ob_start();
?>
<?php lk_form( 'order_stage', 'inline-form stage-form' ); ?>
	<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
	<select name="status" onchange="this.form.submit()" aria-label="Etapa"><?php foreach ( $cols as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>"<?php selected( $p->status, $slug ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select>
</form>
<?php
$actions = ob_get_clean();
lk_panel_start( '#' . $p->id . ' · ' . $p->title, 'pedidos', $actions );
?>
<a class="back" href="<?php echo esc_url( lk_panel_url( 'pedidos' ) ); ?>"><?php echo lk_icon( 'voltar', 16 ); // phpcs:ignore ?> Pedidos</a>

<?php if ( $p->status === $ready ) : ?>
	<section class="card card--accent ready-bar">
		<div><strong>Pedido pronto.</strong> <?php echo $p->ready_notified ? 'E-mail enviado ' . esc_html( lk_ago( $p->ready_notified ) ) . '.' : 'O e-mail ainda não foi enviado.'; ?></div>
		<div class="row-btns">
			<?php if ( $wa ) : ?><a class="btn btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Avisar no WhatsApp</span></a><?php endif; ?>
			<?php lk_action_button( 'order_notify_ready', array( 'id' => $p->id ), ( $p->ready_notified ? 'Reenviar e-mail' : 'Enviar e-mail' ), 'btn btn--ghost' ); ?>
		</div>
	</section>
<?php endif; ?>

<div class="split">
	<div class="split-main">

		<section class="card">
			<div class="card-head"><h3>Itens</h3><?php if ( $quote ) : ?><a class="small" href="<?php echo esc_url( lk_panel_url( 'orcamento', $quote->id ) ); ?>">Ver orçamento</a><?php endif; ?></div>
			<?php if ( $items ) : ?>
				<div class="items-list">
					<?php foreach ( $items as $it ) : ?>
						<div class="items-row">
							<span><strong><?php echo (int) ( $it['qty'] ?? 1 ); ?>× <?php echo esc_html( $it['name'] ?? '' ); ?></strong><?php if ( ! empty( $it['desc'] ) ) : ?><small><?php echo esc_html( $it['desc'] ); ?></small><?php endif; ?></span>
							<span class="muted small"><?php echo ! empty( $it['grams'] ) ? esc_html( round( $it['grams'] ) . ' g · ' . round( (float) ( $it['hours'] ?? 0 ), 1 ) . ' h/un.' ) : ''; ?></span>
							<?php if ( $fin ) : ?><span class="money"><?php echo esc_html( lk_money( (float) ( $it['unit'] ?? 0 ) * (int) ( $it['qty'] ?? 1 ) ) ); ?></span><?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="muted small">Pedido sem itens detalhados.</p>
			<?php endif; ?>
			<?php if ( $qimgs || $model ) : ?>
				<p class="small muted" style="margin-top:14px;">Referências do orçamento:</p>
				<div class="thumbs">
					<?php foreach ( $qimgs as $img ) : ?><a href="<?php echo esc_url( $img['url'] ); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url( $img['thumb'] ?? $img['url'] ); ?>" alt=""></a><?php endforeach; ?>
					<?php if ( $model ) : ?><a class="thumb-file" href="<?php echo esc_url( $model['url'] ); ?>" download><?php echo lk_icon( 'cubo', 22 ); // phpcs:ignore ?><span><?php echo esc_html( $model['name'] ); ?></span></a><?php endif; ?>
				</div>
			<?php endif; ?>
		</section>

		<section class="card">
			<div class="card-head"><h3>Fotos da peça pronta</h3><span class="muted small">aparecem para o cliente e vão para o Drive</span></div>
			<?php lk_form( 'order_photos', 'stack', true ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
				<?php if ( $photos ) : ?>
					<div class="thumbs thumbs--edit">
						<?php foreach ( $photos as $img ) : ?>
							<label class="thumb-edit"><img src="<?php echo esc_url( $img['thumb'] ?? $img['url'] ); ?>" alt=""><span><input type="checkbox" name="remove_img[]" value="<?php echo (int) $img['id']; ?>"> remover</span></label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<label class="drop"><input type="file" name="photos[]" accept="image/*" multiple><?php echo lk_icon( 'upload', 20 ); // phpcs:ignore ?><span>Arraste ou escolha as fotos</span></label>
				<div class="form-actions"><button type="submit" class="btn btn--primary">Salvar fotos</button></div>
			</form>
		</section>

		<?php if ( lk_module( 'estoque' ) ) : ?>
		<section class="card">
			<div class="card-head"><h3>Registrar consumo</h3><span class="muted small">o que realmente gastou: baixa o estoque e calcula o custo real</span></div>
			<?php lk_form( 'order_consume', 'stack consume-form' ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
				<div class="rows" data-rows="fil">
					<div class="row-line">
						<label class="field"><span>Filamento</span><select name="u[fil_id][]"><option value="">—</option><?php foreach ( $fils as $f ) : ?><option value="<?php echo (int) $f->id; ?>"><?php echo esc_html( lk_filament_label( $f ) . ' · ' . round( $f->weight_g ) . ' g' ); ?></option><?php endforeach; ?></select></label>
						<label class="field field--sm"><span>Gramas</span><input type="text" name="u[fil_g][]" inputmode="decimal" placeholder="0"></label>
					</div>
				</div>
				<button type="button" class="btn btn--ghost btn--sm" data-row-add="fil"><?php echo lk_icon( 'mais', 14 ); // phpcs:ignore ?><span>Outro filamento</span></button>
				<div class="rows" data-rows="sup">
					<div class="row-line">
						<label class="field"><span>Insumo</span><select name="u[sup_id][]"><option value="">—</option><?php foreach ( $sups as $s ) : ?><option value="<?php echo (int) $s->id; ?>"><?php echo esc_html( $s->name . ' · ' . lk_qty_label( $s->qty, $s->unit ) ); ?></option><?php endforeach; ?></select></label>
						<label class="field field--sm"><span>Qtd.</span><input type="text" name="u[sup_qty][]" inputmode="decimal" placeholder="0"></label>
					</div>
				</div>
				<button type="button" class="btn btn--ghost btn--sm" data-row-add="sup"><?php echo lk_icon( 'mais', 14 ); // phpcs:ignore ?><span>Outro insumo</span></button>
				<div class="grid-4">
					<?php lk_select( 'u[printer]', 'Impressora', array_combine( wp_list_pluck( $prns, 'id' ), wp_list_pluck( $prns, 'name' ) ) ?: array( '' => '—' ), $p->printer_id ); ?>
					<?php lk_input( 'u[hours]', 'Horas', '', 'number', 'min="0" step="1"' ); ?>
					<?php lk_input( 'u[minutes]', 'Minutos', '', 'number', 'min="0" max="59"' ); ?>
					<?php lk_input( 'u[labor_min]', 'Mão de obra (min)', '', 'number', 'min="0"' ); ?>
				</div>
				<div class="form-actions"><button type="submit" class="btn btn--primary">Registrar e baixar do estoque</button></div>
			</form>
			<?php if ( $moves ) : ?>
				<div class="items-list" style="margin-top:16px;">
					<?php foreach ( $moves as $m ) : ?>
						<?php $item = lk_get( 'filament' === $m->item_type ? 'filaments' : 'supplies', $m->item_id ); ?>
						<div class="items-row"><span><?php echo esc_html( $item ? ( 'filament' === $m->item_type ? lk_filament_label( $item ) : $item->name ) : '—' ); ?></span><span class="muted small"><?php echo esc_html( 'filament' === $m->item_type ? round( abs( $m->qty ) ) . ' g' : lk_qty_label( abs( $m->qty ), $item ? $item->unit : 'un' ) ); ?></span><?php if ( $fin ) : ?><span class="money"><?php echo esc_html( lk_money( $m->total ) ); ?></span><?php endif; ?></div>
					<?php endforeach; ?>
				</div>
				<?php lk_action_button( 'order_unconsume', array( 'id' => $p->id ), 'Desfazer consumo', 'btn btn--ghost btn--sm', 'Devolver todo o material deste pedido ao estoque?' ); ?>
			<?php endif; ?>
		</section>
		<?php endif; ?>

		<section class="card">
			<div class="card-head"><h3>Linha do tempo</h3></div>
			<?php lk_form( 'comment_add', 'stack' ); ?>
				<input type="hidden" name="project_id" value="<?php echo (int) $p->id; ?>">
				<?php lk_input( 'body', 'Anotação', '', 'textarea', 'rows="2" placeholder="Ex.: cliente pediu para trocar a cor"' ); ?>
				<div class="form-actions"><?php lk_check( 'client_visible', 'O cliente vê' ); ?><button type="submit" class="btn btn--ghost btn--sm">Anotar</button></div>
			</form>
			<ul class="timeline">
				<?php foreach ( $log as $a ) : ?>
					<li class="<?php echo $a->client_visible ? 'is-client' : ''; ?>"><span class="timeline-dot"></span><div><?php echo esc_html( $a->body ); ?><small><?php echo esc_html( lk_ago( $a->created_at ) ); ?><?php echo $a->client_visible ? ' · o cliente vê' : ''; ?></small></div></li>
				<?php endforeach; ?>
			</ul>
		</section>
	</div>

	<aside class="split-side">
		<section class="card">
			<div class="card-head"><h3>Cliente</h3></div>
			<?php if ( $client ) : ?>
				<p><strong><a href="<?php echo esc_url( lk_panel_url( 'cliente', $client->id ) ); ?>"><?php echo esc_html( lk_client_label( $client ) ); ?></a></strong><br><span class="muted small"><?php echo esc_html( implode( ' · ', array_filter( array( $client->email, $client->whatsapp, $client->source ) ) ) ); ?></span></p>
				<?php if ( $client->whatsapp ) : ?><a class="btn btn--wa btn--sm" href="<?php echo esc_url( lk_wa_link( $client->whatsapp, 'Olá, ' . strtok( (string) $client->name, ' ' ) . '! Sobre o seu pedido "' . $p->title . '": ' ) ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 14 ); // phpcs:ignore ?><span>WhatsApp</span></a><?php endif; ?>
				<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_client_url( '', 0, array( 'como' => $client->id ) ) ); ?>">Ver como cliente</a>
			<?php else : ?>
				<p class="muted small">Sem cliente cadastrado (venda avulsa/marketplace).</p>
			<?php endif; ?>
		</section>

		<?php if ( $fin ) : ?>
			<section class="card">
				<div class="card-head"><h3>Números</h3></div>
				<div class="kv">
					<span>Valor</span><strong class="money"><?php echo esc_html( lk_money( $p->value ) ); ?></strong>
					<?php if ( $p->ship_price > 0 ) : ?><span>Frete (repasse)</span><strong class="money">− <?php echo esc_html( lk_money( $p->ship_price ) ); ?></strong><?php endif; ?>
					<span>Custo estimado</span><strong class="money"><?php echo esc_html( lk_money( $p->cost_estimated ) ); ?></strong>
					<span>Custo real</span><strong class="money"><?php echo $p->cost_real > 0 ? esc_html( lk_money( $p->cost_real ) ) : '<em class="muted">registre o consumo</em>'; ?></strong>
					<span>Lucro</span><strong class="money <?php echo $profit < 0 ? 'text-late' : 'text-ok'; ?>"><?php echo esc_html( lk_money( $profit ) ); ?></strong>
					<?php if ( $p->grams_used > 0 ) : ?><span>Consumo</span><strong><?php echo esc_html( round( $p->grams_used ) . ' g · ' . round( $p->print_hours, 1 ) . ' h' ); ?></strong><?php endif; ?>
				</div>
			</section>

			<section class="card">
				<div class="card-head"><h3>Pagamentos</h3></div>
				<?php foreach ( $trans as $t ) : ?>
					<?php $st = lk_trans_status( $t ); ?>
					<div class="pay-row">
						<span><?php echo esc_html( $t->description ); ?><small><?php echo esc_html( lk_money( $t->amount ) . ( $t->method ? ' · ' . $t->method : '' ) ); ?></small></span>
						<em class="badge badge--<?php echo 'pago' === $st ? 'ok' : ( 'atrasado' === $st ? 'late' : 'warn' ); ?>"><?php echo esc_html( $st ); ?></em>
						<?php if ( 'pago' !== $t->status ) : ?>
							<button type="button" class="btn btn--ghost btn--sm" data-copy="<?php echo esc_attr( lk_pay_url( $t->id ) ); ?>">Copiar link</button>
							<?php lk_action_button( 'trans_pay', array( 'id' => $t->id ), 'Marcar pago', 'btn btn--ghost btn--sm' ); ?>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
				<?php if ( ! $trans ) : ?><p class="muted small">Nenhum lançamento.</p><?php endif; ?>
			</section>
		<?php endif; ?>

		<section class="card">
			<div class="card-head"><h3>Entrega</h3></div>
			<?php lk_form( 'order_save', 'stack' ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
				<?php lk_input( 'title', 'Nome do pedido', $p->title ); ?>
				<?php lk_select( 'delivery_mode', 'Entrega', array( 'retirada' => 'Retirada', 'envio' => 'Envio' ), $p->delivery_mode ); ?>
				<?php lk_input( 'ship_service', 'Serviço de envio', $p->ship_service, 'text', 'placeholder="Correios PAC, Jadlog…"' ); ?>
				<?php lk_input( 'ship_price', 'Frete (R$)', $p->ship_price > 0 ? number_format( $p->ship_price, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money' ); ?>
				<?php lk_input( 'tracking', 'Código de rastreio', $p->tracking ); ?>
				<?php lk_input( 'due_date', 'Prazo', $p->due_date, 'date' ); ?>
				<?php lk_input( 'notes', 'Observações internas', $p->notes, 'textarea', 'rows="3"' ); ?>
				<div class="form-actions"><button type="submit" class="btn btn--primary btn--sm">Salvar</button></div>
			</form>
			<?php if ( lk_module( 'frete' ) && 'envio' === $p->delivery_mode && $client ) : ?><a class="small" href="<?php echo esc_url( lk_panel_url( 'frete', 0, array( 'cep' => $client->cep, 'valor' => $p->value ) ) ); ?>">Calcular frete →</a><?php endif; ?>
		</section>

		<section class="card">
			<div class="card-head"><h3>Mais</h3></div>
			<div class="stack-btns">
				<?php if ( lk_module( 'produtos' ) && lk_can( 'produtos' ) ) : ?>
					<?php if ( $p->product_id ) : ?>
						<a class="btn btn--ghost btn--block" href="<?php echo esc_url( lk_panel_url( 'produto', $p->product_id ) ); ?>"><?php echo lk_icon( 'tag', 16 ); // phpcs:ignore ?><span>Ver no portfólio</span></a>
					<?php else : ?>
						<?php lk_action_button( 'order_to_product', array( 'id' => $p->id ), lk_icon( 'tag', 16 ) . '<span>Salvar no portfólio / vender</span>', 'btn btn--ghost btn--block' ); ?>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( lk_google_connected() && lk_drive_folder_link( array( 'Pedidos', '#' . $p->id . ' ' . ( $client ? lk_client_label( $client ) . ' - ' : '' ) . $p->title ) ) ) : ?>
					<a class="btn btn--ghost btn--block" target="_blank" rel="noopener" href="<?php echo esc_url( lk_drive_folder_link( array( 'Pedidos', '#' . $p->id . ' ' . ( $client ? lk_client_label( $client ) . ' - ' : '' ) . $p->title ) ) ); ?>"><?php echo lk_icon( 'drive', 16 ); // phpcs:ignore ?><span>Abrir no Drive</span></a>
				<?php endif; ?>
				<?php lk_action_button( 'project_archive', array( 'id' => $p->id ), $p->archived ? 'Desarquivar' : 'Arquivar', 'btn btn--ghost btn--block' ); ?>
				<?php lk_action_button( 'project_delete', array( 'id' => $p->id ), 'Excluir pedido', 'btn btn--danger btn--block', 'Excluir o pedido e os lançamentos pendentes? Não dá para desfazer.' ); ?>
			</div>
		</section>
	</aside>
</div>
<?php
lk_panel_end();
