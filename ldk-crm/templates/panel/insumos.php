<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sups  = lk_rows( 'supplies', 'active = 1', array(), 'name' );
$moves = lk_rows( 'stock_moves', "item_type = 'supply' AND kind = 'compra'", array(), 'id DESC LIMIT 15' );
$units = array( 'un' => 'unidade', 'm' => 'metro', 'g' => 'grama', 'ml' => 'ml', 'folha' => 'folha', 'rolo' => 'rolo' );
lk_panel_start( 'Insumos', 'insumos', '<button type="button" class="btn btn--primary" data-open="novo-insumo">' . lk_icon( 'mais', 16 ) . '<span>Insumo</span></button>' );
?>
<p class="muted small hint">Tudo o que vai junto com a peça e não é filamento: sacolinha, cartão, caixa, plástico bolha, etiqueta, ímã, argola, tinta… Registre cada compra: o painel divide o valor (com o frete) pela quantidade e mantém o custo médio de cada unidade. Na calculadora, esses custos entram só para você; o cliente não vê.</p>

<?php if ( ! $sups ) : ?>
	<div class="empty empty--big"><?php echo lk_icon( 'caixa', 28 ); // phpcs:ignore ?><h3>Nenhum insumo</h3><button type="button" class="btn btn--primary" data-open="novo-insumo">Cadastrar insumo</button></div>
<?php else : ?>
	<div class="table table--supplies">
		<div class="table-row table-head"><span>Insumo</span><span>Estoque</span><span>Custo unitário</span><span>Em todo pedido</span><span></span></div>
		<?php foreach ( $sups as $s ) : ?>
			<?php $low = $s->min_qty > 0 && $s->qty < $s->min_qty; ?>
			<div class="table-row">
				<span class="cell-main"><span><strong><?php echo esc_html( $s->name ); ?></strong><?php if ( $s->link ) : ?><small><a href="<?php echo esc_url( $s->link ); ?>" target="_blank" rel="noopener">link de compra ↗</a></small><?php endif; ?></span></span>
				<span data-label="Estoque"><?php echo esc_html( lk_qty_label( $s->qty, $s->unit ) ); ?><?php echo $low ? ' <em class="badge badge--late">baixo</em>' : ''; ?></span>
				<span data-label="Custo unitário" class="money"><?php echo esc_html( 'R$ ' . number_format( (float) $s->unit_cost, $s->unit_cost < 1 ? 3 : 2, ',', '.' ) ); ?></span>
				<span data-label="Em todo pedido"><?php echo $s->per_order ? 'Sim' : '—'; ?></span>
				<span class="row-btns">
					<button type="button" class="btn btn--primary btn--sm" data-open="buy-<?php echo (int) $s->id; ?>">Registrar compra</button>
					<button type="button" class="icon-btn" data-open="sup-<?php echo (int) $s->id; ?>" title="Editar"><?php echo lk_icon( 'editar', 15 ); // phpcs:ignore ?></button>
				</span>
			</div>

			<?php lk_modal_start( 'buy-' . $s->id, 'Compra: ' . $s->name ); ?>
				<?php lk_form( 'supply_buy', 'stack' ); ?>
					<input type="hidden" name="id" value="<?php echo (int) $s->id; ?>">
					<div class="grid-3">
						<?php lk_input( 'qty', 'Quantas vieram (' . $s->unit . ')', '', 'text', 'inputmode="decimal" required placeholder="100"' ); ?>
						<?php lk_input( 'total', 'Quanto pagou (R$)', '', 'text', 'inputmode="decimal" data-money required data-buy-total' ); ?>
						<?php lk_input( 'freight', 'Frete (R$)', '', 'text', 'inputmode="decimal" data-money data-buy-freight' ); ?>
					</div>
					<p class="buy-preview muted small" data-buy-preview>Custo por unidade: —</p>
					<?php lk_input( 'note', 'Onde comprou / observação', '', 'text', 'placeholder="Ex.: Shopee, loja X"' ); ?>
					<?php lk_check( 'lancar', 'Lançar no financeiro (saída paga hoje)', true ); ?>
					<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Registrar</button></div>
				</form>
			<?php lk_modal_end(); ?>

			<?php lk_modal_start( 'sup-' . $s->id, $s->name ); ?>
				<?php lk_form( 'supply_save', 'stack' ); ?>
					<input type="hidden" name="id" value="<?php echo (int) $s->id; ?>">
					<?php lk_input( 'name', 'Nome', $s->name, 'text', 'required' ); ?>
					<div class="grid-2">
						<?php lk_select( 'unit', 'Unidade', $units, $s->unit ); ?>
						<?php lk_input( 'min_qty', 'Avisar abaixo de', $s->min_qty > 0 ? rtrim( rtrim( $s->min_qty, '0' ), '.' ) : '', 'text', 'inputmode="decimal"' ); ?>
						<?php lk_input( 'qty', 'Estoque atual (ajuste)', rtrim( rtrim( $s->qty, '0' ), '.' ), 'text', 'inputmode="decimal"' ); ?>
						<?php lk_input( 'unit_cost', 'Custo unitário (ajuste)', number_format( (float) $s->unit_cost, 4, ',', '' ), 'text', 'inputmode="decimal"' ); ?>
					</div>
					<?php lk_input( 'link', 'Link de compra', $s->link, 'url' ); ?>
					<?php lk_check( 'per_order', 'Vai em todo pedido (já entra marcado na calculadora)', (bool) $s->per_order ); ?>
					<div class="form-actions">
						<?php lk_action_button( 'supply_archive', array( 'id' => $s->id ), 'Arquivar', 'btn btn--link btn--sm', 'Arquivar este insumo?' ); ?>
						<button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button>
					</div>
				</form>
			<?php lk_modal_end(); ?>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php if ( $moves ) : ?>
	<section class="card">
		<div class="card-head"><h3>Últimas compras</h3></div>
		<div class="items-list">
			<?php foreach ( $moves as $m ) : ?>
				<?php $s = lk_get( 'supplies', $m->item_id ); ?>
				<div class="items-row"><span><?php echo esc_html( $s ? $s->name : '—' ); ?><small><?php echo esc_html( lk_date( $m->created_at ) . ( $m->note ? ' · ' . $m->note : '' ) ); ?></small></span><span class="muted small"><?php echo esc_html( lk_qty_label( $m->qty, $s ? $s->unit : 'un' ) ); ?></span><span class="money"><?php echo esc_html( lk_money( $m->total ) ); ?><small><?php echo $m->qty > 0 ? esc_html( lk_money( $m->total / $m->qty ) ) . ' cada' : ''; ?></small></span></div>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php lk_modal_start( 'novo-insumo', 'Novo insumo' ); ?>
	<?php lk_form( 'supply_save', 'stack' ); ?>
		<?php lk_input( 'name', 'Nome', '', 'text', 'required placeholder="Ex.: Sacolinha kraft P"' ); ?>
		<div class="grid-2">
			<?php lk_select( 'unit', 'Unidade', $units ); ?>
			<?php lk_input( 'min_qty', 'Avisar abaixo de', '', 'text', 'inputmode="decimal"' ); ?>
		</div>
		<?php lk_input( 'link', 'Link de compra', '', 'url' ); ?>
		<?php lk_check( 'per_order', 'Vai em todo pedido (sacola, cartão…)' ); ?>
		<p class="muted small">Depois clique em "Registrar compra" para colocar a quantidade e o valor.</p>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Cadastrar</button></div>
	</form>
<?php lk_modal_end(); ?>

<script>
document.addEventListener('input', function (e) {
	var f = e.target.closest('form'); if (!f || !f.querySelector('[data-buy-preview]')) return;
	var n = function (v) { v = String(v || '').replace(/\./g, '').replace(',', '.'); return parseFloat(v) || 0; };
	var q = n(f.querySelector('[name=qty]').value.replace('.', ',')), t = n(f.querySelector('[data-buy-total]').value) + n(f.querySelector('[data-buy-freight]').value);
	f.querySelector('[data-buy-preview]').textContent = 'Custo por unidade: ' + (q > 0 ? 'R$ ' + (t / q).toFixed(t / q < 1 ? 3 : 2).replace('.', ',') : '—');
});
</script>
<?php
lk_panel_end();
