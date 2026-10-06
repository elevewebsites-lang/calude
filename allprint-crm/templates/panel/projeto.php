<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$p = $id ? ap_project( $id ) : null;
if ( ! $p ) {
	ap_render( 'panel/404' );
}
$client  = $p->client_id ? ap_get( 'clients', $p->client_id ) : null;
$cols    = ap_columns();
$items   = ap_order_items( $p );
$photos  = ap_order_photos( $p );
$quote   = $p->quote_id ? ap_get( 'quotes', $p->quote_id ) : null;
$qimgs   = $quote ? ap_json( $quote->images ) : array();
$model   = $quote ? ap_json( $quote->model3d ) : array();
$trans   = ap_rows( 'transactions', 'project_id = %d', array( $p->id ), 'due_date, id' );
$moves   = ap_rows( 'stock_moves', "project_id = %d AND kind = 'uso'", array( $p->id ), 'id' );
$log     = ap_rows( 'activity', 'project_id = %d', array( $p->id ), 'id DESC LIMIT 40' );
$fils    = ap_rows( 'filaments', 'active = 1', array(), 'material, color_name' );
$sups    = ap_rows( 'supplies', 'active = 1', array(), 'name' );
$prns    = ap_rows( 'printers', 'active = 1' );
$cost    = $p->cost_real > 0 ? $p->cost_real : $p->cost_estimated;
$profit  = $p->value - $p->ship_price - $cost;
$ready   = ap_ready_column();
$wa      = ap_order_wa_link( $p );
$fin     = ap_can( 'financeiro' );

ob_start();
?>
<?php $pay = ap_order_payment( $p ); ?>
<?php ap_form( 'order_stage', 'inline-form stage-form' ); ?>
	<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
	<span class="stage-form-tags"><?php echo ap_stage_badge( $p->status ); ?><?php echo ap_pay_badge( $p ); // phpcs:ignore ?></span>
	<select name="status" data-stage-select data-due="<?php echo esc_attr( $pay['due'] ); ?>" data-last="<?php echo esc_attr( ap_last_column() ); ?>" data-ptitle="<?php echo esc_attr( '#' . $p->id . ' · ' . $p->title ); ?>" aria-label="Etapa"><?php foreach ( $cols as $slug => $name ) : ?><option value="<?php echo esc_attr( $slug ); ?>"<?php selected( $p->status, $slug ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select>
</form>
<?php
$actions = ob_get_clean();
ap_panel_start( '#' . $p->id . ' · ' . $p->title, 'pedidos', $actions );
?>
<a class="back" href="<?php echo esc_url( ap_panel_url( 'pedidos' ) ); ?>"><?php echo ap_icon( 'voltar', 16 ); // phpcs:ignore ?> Pedidos</a>

<?php if ( $p->status === $ready ) : ?>
	<section class="card card--accent ready-bar">
		<div><strong>Pedido pronto.</strong> <?php echo $p->ready_notified ? 'E-mail enviado ' . esc_html( ap_ago( $p->ready_notified ) ) . '.' : 'O e-mail ainda não foi enviado.'; ?></div>
		<div class="row-btns">
			<?php if ( $wa ) : ?><a class="btn btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo ap_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Avisar no WhatsApp</span></a><?php endif; ?>
			<?php ap_action_button( 'order_notify_ready', array( 'id' => $p->id ), ( $p->ready_notified ? 'Reenviar e-mail' : 'Enviar e-mail' ), 'btn btn--ghost' ); ?>
		</div>
	</section>
<?php endif; ?>

<section class="card opay tone-<?php echo esc_attr( $pay['tone'] ); ?>">
	<div class="card-head"><h3>Pagamento</h3><?php echo ap_pay_badge( $p ); // phpcs:ignore ?></div>
	<?php foreach ( ap_rows( 'transactions', "project_id = %d AND type = 'in'", array( $p->id ), 'id' ) as $tr ) : ?>
		<div class="pay-line"><span><?php echo esc_html( ( 'pago' === $tr->status ? 'Recebido' : 'A receber' ) . ( $tr->method ? ' · ' . $tr->method : '' ) . ( $tr->paid_at ? ' · ' . date_i18n( 'd/m/Y', strtotime( $tr->paid_at ) ) : '' ) ); ?></span><b><?php echo esc_html( ap_money( $tr->amount ) ); ?></b></div>
	<?php endforeach; ?>
	<?php if ( $pay['due'] > 0 ) : ?>
		<?php ap_form( 'order_pay', 'inline-form pay-form' ); ?>
			<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
			<?php ap_input( 'amount', 'Valor recebido (R$)', number_format( $pay['due'], 2, ',', '' ), 'text', 'inputmode="decimal"' ); ?>
			<?php ap_select( 'method', 'Forma de pagamento', ap_pay_methods(), 'Pix' ); ?>
			<?php ap_input( 'date', 'Data', ap_today(), 'date' ); ?>
			<button class="btn btn--primary" type="submit">Registrar pagamento</button>
		</form>
	<?php endif; ?>
</section>

<div class="split">
	<div class="split-main">
		<?php $arts = array( 'pendente' => array( 'Arte aguardando conferência', 'warn' ), 'aprovada' => array( 'Arte conferida e aprovada', 'ok' ), 'problema' => array( 'Arte com problema: aguardando o cliente', 'late' ) ); $as = $arts[ $p->art_status ] ?? $arts['pendente']; ?>
		<section class="card art-review art-review--<?php echo esc_attr( $as[1] ); ?>">
			<div class="card-head"><h3>Revisão da arte</h3><em class="badge badge--<?php echo esc_attr( $as[1] ); ?>"><?php echo esc_html( $as[0] ); ?></em><?php if ( $p->urgent ) : ?><em class="badge badge--late">URGENTE</em><?php endif; ?></div>
			<p class="muted small">Confira se o arquivo bate com o material e as medidas de cada item. Ao aprovar, o pedido vai para Produção e o cliente recebe "arte conferida e aprovada".</p>
			<?php if ( $p->art_note ) : ?><p class="small"><strong>Última observação:</strong> <?php echo esc_html( $p->art_note ); ?></p><?php endif; ?>
			<div class="row-btns">
				<?php ap_form( 'art_review', 'inline-form' ); ?><input type="hidden" name="id" value="<?php echo (int) $p->id; ?>"><input type="hidden" name="decisao" value="aprovada"><button type="submit" class="btn btn--primary"><?php echo ap_icon( 'check', 16 ); // phpcs:ignore ?><span>Arte aprovada → Produção</span></button></form>
				<button type="button" class="btn btn--ghost" data-open="art-problema">Arte com problema</button>
			</div>
		</section>
		<?php ap_modal_start( 'art-problema', 'O que precisa corrigir?' ); ?>
			<?php ap_form( 'art_review', 'stack' ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>"><input type="hidden" name="decisao" value="problema">
				<?php ap_input( 'nota', 'Mensagem para o cliente', '', 'textarea', 'rows="4" required placeholder="Ex.: o arquivo está em 72 dpi; precisamos de 300 dpi no tamanho real. / A medida do arquivo é 90×200, mas o pedido é 100×200."' ); ?>
				<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Avisar o cliente</button></div>
			</form>
		<?php ap_modal_end(); ?>

		<section class="card">
			<div class="card-head"><h3>Itens</h3><?php if ( $quote ) : ?><a class="small" href="<?php echo esc_url( ap_panel_url( 'orcamento', $quote->id ) ); ?>">Ver orçamento</a><?php endif; ?></div>
			<?php if ( $items ) : ?>
				<div class="items-list">
					<?php foreach ( $items as $it ) : ?>
						<div class="items-row">
							<span><strong><?php echo (int) ( $it['qty'] ?? 1 ); ?>× <?php echo esc_html( $it['name'] ?? '' ); ?></strong><?php if ( ! empty( $it['desc'] ) ) : ?><small><?php echo esc_html( $it['desc'] ); ?></small><?php endif; ?><?php if ( isset( $it['area'] ) ) : ?><small><?php echo esc_html( number_format( (float) $it['area'], 2, ',', '.' ) ); ?> m²</small><?php endif; ?><?php foreach ( (array) ( $it['files'] ?? array() ) as $fl ) : ?><?php if ( ! empty( $fl['thumb'] ) ) : ?><img class="upthumb" alt="" src="<?php echo esc_attr( $fl['thumb'] ); ?>"><?php endif; ?><?php if ( ! empty( $fl['pw'] ) ) : ?><small>Arte: <?php echo esc_html( number_format( (float) $fl['pw'], 1, ',', '' ) . ' × ' . number_format( (float) $fl['ph'], 1, ',', '' ) ); ?> cm</small><?php endif; ?><small><a href="<?php echo esc_url( $fl['link'] ?? '#' ); ?>" target="_blank" rel="noopener">📎 <?php echo esc_html( $fl['name'] ); ?></a><?php echo ! empty( $fl['size'] ) ? ' · ' . esc_html( number_format( $fl['size'] / 1048576, 1, ',', '.' ) ) . ' MB' : ''; ?><?php echo ! empty( $fl['new'] ) ? ' <em class="badge badge--warn">novo</em>' : ''; ?></small><?php endforeach; ?></span>
							<span class="muted small"><?php echo ! empty( $it['grams'] ) ? esc_html( round( $it['grams'] ) . ' g · ' . round( (float) ( $it['hours'] ?? 0 ), 1 ) . ' h/un.' ) : ''; ?></span>
							<?php if ( $fin ) : ?><span class="money"><?php echo esc_html( ap_money( (float) ( $it['unit'] ?? 0 ) * (int) ( $it['qty'] ?? 1 ) ) ); ?></span><?php endif; ?>
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
					<?php if ( $model ) : ?><a class="thumb-file" href="<?php echo esc_url( $model['url'] ); ?>" download><?php echo ap_icon( 'cubo', 22 ); // phpcs:ignore ?><span><?php echo esc_html( $model['name'] ); ?></span></a><?php endif; ?>
				</div>
			<?php endif; ?>
		</section>

		<section class="card">
			<div class="card-head"><h3>Fotos da peça pronta</h3><span class="muted small">aparecem para o cliente e vão para o Drive</span></div>
			<?php ap_form( 'order_photos', 'stack', true ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
				<?php if ( $photos ) : ?>
					<div class="thumbs thumbs--edit">
						<?php foreach ( $photos as $img ) : ?>
							<label class="thumb-edit"><img src="<?php echo esc_url( $img['thumb'] ?? $img['url'] ); ?>" alt=""><span><input type="checkbox" name="remove_img[]" value="<?php echo (int) $img['id']; ?>"> remover</span></label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<label class="drop"><input type="file" name="photos[]" accept="image/*" multiple><?php echo ap_icon( 'upload', 20 ); // phpcs:ignore ?><span>Arraste ou escolha as fotos</span></label>
				<div class="form-actions"><button type="submit" class="btn btn--primary">Salvar fotos</button></div>
			</form>
		</section>

		<?php if ( ap_module( 'estoque' ) ) : ?>
		<section class="card">
			<div class="card-head"><h3>Registrar consumo</h3><span class="muted small">o que realmente gastou: baixa o estoque e calcula o custo real</span></div>
			<?php ap_form( 'order_consume', 'stack consume-form' ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
				<div class="rows" data-rows="fil">
					<div class="row-line">
						<label class="field"><span>Filamento</span><select name="u[fil_id][]"><option value="">—</option><?php foreach ( $fils as $f ) : ?><option value="<?php echo (int) $f->id; ?>"><?php echo esc_html( ap_filament_label( $f ) . ' · ' . round( $f->weight_g ) . ' g' ); ?></option><?php endforeach; ?></select></label>
						<label class="field field--sm"><span>Gramas</span><input type="text" name="u[fil_g][]" inputmode="decimal" placeholder="0"></label>
					</div>
				</div>
				<button type="button" class="btn btn--ghost btn--sm" data-row-add="fil"><?php echo ap_icon( 'mais', 14 ); // phpcs:ignore ?><span>Outro filamento</span></button>
				<div class="rows" data-rows="sup">
					<div class="row-line">
						<label class="field"><span>Insumo</span><select name="u[sup_id][]"><option value="">—</option><?php foreach ( $sups as $s ) : ?><option value="<?php echo (int) $s->id; ?>"><?php echo esc_html( $s->name . ' · ' . ap_qty_label( $s->qty, $s->unit ) ); ?></option><?php endforeach; ?></select></label>
						<label class="field field--sm"><span>Qtd.</span><input type="text" name="u[sup_qty][]" inputmode="decimal" placeholder="0"></label>
					</div>
				</div>
				<button type="button" class="btn btn--ghost btn--sm" data-row-add="sup"><?php echo ap_icon( 'mais', 14 ); // phpcs:ignore ?><span>Outro insumo</span></button>
				<div class="grid-4">
					<?php ap_select( 'u[printer]', 'Impressora', array_combine( wp_list_pluck( $prns, 'id' ), wp_list_pluck( $prns, 'name' ) ) ?: array( '' => '—' ), $p->printer_id ); ?>
					<?php ap_input( 'u[hours]', 'Horas', '', 'number', 'min="0" step="1"' ); ?>
					<?php ap_input( 'u[minutes]', 'Minutos', '', 'number', 'min="0" max="59"' ); ?>
					<?php ap_input( 'u[labor_min]', 'Mão de obra (min)', '', 'number', 'min="0"' ); ?>
				</div>
				<div class="form-actions"><button type="submit" class="btn btn--primary">Registrar e baixar do estoque</button></div>
			</form>
			<?php if ( $moves ) : ?>
				<div class="items-list" style="margin-top:16px;">
					<?php foreach ( $moves as $m ) : ?>
						<?php $item = ap_get( 'filament' === $m->item_type ? 'filaments' : 'supplies', $m->item_id ); ?>
						<div class="items-row"><span><?php echo esc_html( $item ? ( 'filament' === $m->item_type ? ap_filament_label( $item ) : $item->name ) : '—' ); ?></span><span class="muted small"><?php echo esc_html( 'filament' === $m->item_type ? round( abs( $m->qty ) ) . ' g' : ap_qty_label( abs( $m->qty ), $item ? $item->unit : 'un' ) ); ?></span><?php if ( $fin ) : ?><span class="money"><?php echo esc_html( ap_money( $m->total ) ); ?></span><?php endif; ?></div>
					<?php endforeach; ?>
				</div>
				<?php ap_action_button( 'order_unconsume', array( 'id' => $p->id ), 'Desfazer consumo', 'btn btn--ghost btn--sm', 'Devolver todo o material deste pedido ao estoque?' ); ?>
			<?php endif; ?>
		</section>
		<?php endif; ?>

		<section class="card">
			<div class="card-head"><h3>Linha do tempo</h3></div>
			<?php ap_form( 'comment_add', 'stack' ); ?>
				<input type="hidden" name="project_id" value="<?php echo (int) $p->id; ?>">
				<?php ap_input( 'body', 'Anotação', '', 'textarea', 'rows="2" placeholder="Ex.: cliente pediu para trocar a cor"' ); ?>
				<div class="form-actions"><?php ap_check( 'client_visible', 'O cliente vê' ); ?><button type="submit" class="btn btn--ghost btn--sm">Anotar</button></div>
			</form>
			<ul class="timeline">
				<?php foreach ( $log as $a ) : ?>
					<li class="<?php echo $a->client_visible ? 'is-client' : ''; ?>"><span class="timeline-dot"></span><div><?php echo esc_html( $a->body ); ?><small><?php echo esc_html( ap_ago( $a->created_at ) ); ?><?php echo $a->client_visible ? ' · o cliente vê' : ''; ?></small></div></li>
				<?php endforeach; ?>
			</ul>
		</section>
	</div>

	<aside class="split-side">
		<section class="card">
			<div class="card-head"><h3>Cliente</h3></div>
			<?php if ( $client ) : ?>
				<p><strong><a href="<?php echo esc_url( ap_panel_url( 'cliente', $client->id ) ); ?>"><?php echo esc_html( ap_client_label( $client ) ); ?></a></strong><br><span class="muted small"><?php echo esc_html( implode( ' · ', array_filter( array( $client->email, $client->whatsapp, $client->source ) ) ) ); ?></span></p>
				<?php if ( $client->whatsapp ) : ?><a class="btn btn--wa btn--sm" href="<?php echo esc_url( ap_wa_link( $client->whatsapp, 'Olá, ' . strtok( (string) $client->name, ' ' ) . '! Sobre o seu pedido "' . $p->title . '": ' ) ); ?>" target="_blank" rel="noopener"><?php echo ap_icon( 'whatsapp', 14 ); // phpcs:ignore ?><span>WhatsApp</span></a><?php endif; ?>
				<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( ap_client_url( '', 0, array( 'como' => $client->id ) ) ); ?>">Ver como cliente</a>
			<?php else : ?>
				<p class="muted small">Sem cliente cadastrado (venda avulsa/marketplace).</p>
			<?php endif; ?>
		</section>

		<?php if ( $fin ) : ?>
			<section class="card">
				<div class="card-head"><h3>Números</h3></div>
				<div class="kv">
					<span>Valor</span><strong class="money"><?php echo esc_html( ap_money( $p->value ) ); ?></strong>
					<?php if ( $p->ship_price > 0 ) : ?><span>Frete (repasse)</span><strong class="money">− <?php echo esc_html( ap_money( $p->ship_price ) ); ?></strong><?php endif; ?>
					<span>Custo estimado</span><strong class="money"><?php echo esc_html( ap_money( $p->cost_estimated ) ); ?></strong>
					<span>Custo real</span><strong class="money"><?php echo $p->cost_real > 0 ? esc_html( ap_money( $p->cost_real ) ) : '<em class="muted">registre o consumo</em>'; ?></strong>
					<span>Lucro</span><strong class="money <?php echo $profit < 0 ? 'text-late' : 'text-ok'; ?>"><?php echo esc_html( ap_money( $profit ) ); ?></strong>
					<?php if ( $p->grams_used > 0 ) : ?><span>Consumo</span><strong><?php echo esc_html( round( $p->grams_used ) . ' g · ' . round( $p->print_hours, 1 ) . ' h' ); ?></strong><?php endif; ?>
				</div>
			</section>

			<section class="card">
				<div class="card-head"><h3>Pagamentos</h3></div>
				<?php foreach ( $trans as $t ) : ?>
					<?php $st = ap_trans_status( $t ); ?>
					<div class="pay-row">
						<span><?php echo esc_html( $t->description ); ?><small><?php echo esc_html( ap_money( $t->amount ) . ( $t->method ? ' · ' . $t->method : '' ) ); ?></small></span>
						<em class="badge badge--<?php echo 'pago' === $st ? 'ok' : ( 'atrasado' === $st ? 'late' : 'warn' ); ?>"><?php echo esc_html( $st ); ?></em>
						<?php if ( 'pago' !== $t->status ) : ?>
							<button type="button" class="btn btn--ghost btn--sm" data-copy="<?php echo esc_attr( ap_pay_url( $t->id ) ); ?>">Copiar link</button>
							<?php ap_action_button( 'trans_pay', array( 'id' => $t->id ), 'Marcar pago', 'btn btn--ghost btn--sm' ); ?>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
				<?php if ( ! $trans ) : ?><p class="muted small">Nenhum lançamento.</p><?php endif; ?>
			</section>
		<?php endif; ?>

		<section class="card">
			<div class="card-head"><h3>Entrega</h3></div>
			<?php ap_form( 'order_save', 'stack' ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
				<?php ap_input( 'title', 'Nome do pedido', $p->title ); ?>
				<?php ap_select( 'delivery_mode', 'Entrega', array( 'retirada' => 'Retirada', 'envio' => 'Envio' ), $p->delivery_mode ); ?>
				<?php ap_input( 'ship_service', 'Serviço de envio', $p->ship_service, 'text', 'placeholder="Correios PAC, Jadlog…"' ); ?>
				<?php ap_input( 'ship_price', 'Frete (R$)', $p->ship_price > 0 ? number_format( $p->ship_price, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money' ); ?>
				<?php ap_input( 'tracking', 'Código de rastreio', $p->tracking ); ?>
				<?php ap_input( 'due_date', 'Prazo', $p->due_date, 'date' ); ?>
				<?php ap_input( 'notes', 'Observações internas', $p->notes, 'textarea', 'rows="3"' ); ?>
				<div class="form-actions"><button type="submit" class="btn btn--primary btn--sm">Salvar</button></div>
			</form>
			<?php if ( ap_module( 'frete' ) && 'envio' === $p->delivery_mode && $client ) : ?><a class="small" href="<?php echo esc_url( ap_panel_url( 'frete', 0, array( 'cep' => $client->cep, 'valor' => $p->value ) ) ); ?>">Calcular frete →</a><?php endif; ?>
		</section>

		<section class="card">
			<div class="card-head"><h3>Mais</h3></div>
			<div class="stack-btns">
				<?php if ( ap_module( 'produtos' ) && ap_can( 'produtos' ) ) : ?>
					<?php if ( $p->product_id ) : ?>
						<a class="btn btn--ghost btn--block" href="<?php echo esc_url( ap_panel_url( 'produto', $p->product_id ) ); ?>"><?php echo ap_icon( 'tag', 16 ); // phpcs:ignore ?><span>Ver no portfólio</span></a>
					<?php else : ?>
						<?php ap_action_button( 'order_to_product', array( 'id' => $p->id ), ap_icon( 'tag', 16 ) . '<span>Salvar no portfólio / vender</span>', 'btn btn--ghost btn--block' ); ?>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( ap_google_connected() && ap_drive_folder_link( array( 'Pedidos', '#' . $p->id . ' ' . ( $client ? ap_client_label( $client ) . ' - ' : '' ) . $p->title ) ) ) : ?>
					<a class="btn btn--ghost btn--block" target="_blank" rel="noopener" href="<?php echo esc_url( ap_drive_folder_link( array( 'Pedidos', '#' . $p->id . ' ' . ( $client ? ap_client_label( $client ) . ' - ' : '' ) . $p->title ) ) ); ?>"><?php echo ap_icon( 'drive', 16 ); // phpcs:ignore ?><span>Abrir no Drive</span></a>
				<?php endif; ?>
				<?php ap_action_button( 'project_archive', array( 'id' => $p->id ), $p->archived ? 'Desarquivar' : 'Arquivar', 'btn btn--ghost btn--block' ); ?>
				<?php ap_action_button( 'project_delete', array( 'id' => $p->id ), 'Excluir pedido', 'btn btn--danger btn--block', 'Excluir o pedido e os lançamentos pendentes? Não dá para desfazer.' ); ?>
			</div>
		</section>
	</aside>
</div>
<?php
ap_panel_end();
