<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$cats   = ap_supply_cats();
$units  = ap_supply_units();
$all    = ap_rows( 'supplies', 'active = 1', array(), 'category, name' );
$tab    = isset( $_GET['cat'] ) ? sanitize_text_field( wp_unslash( $_GET['cat'] ) ) : 'todos'; // phpcs:ignore WordPress.Security.NonceVerification
$mode   = isset( $_GET['contagem'] ) ? 'contagem' : 'lista'; // phpcs:ignore WordPress.Security.NonceVerification
$tabs   = array( 'todos' => 'Todos', 'repor' => 'Repor' );
foreach ( $cats as $k => $l ) {
	if ( '' !== $k ) {
		$tabs[ $k ] = $k;
	}
}
$count  = array_fill_keys( array_keys( $tabs ), 0 );
$value  = 0.0;
$low    = 0;
foreach ( $all as $s ) {
	$count['todos']++;
	if ( isset( $count[ $s->category ] ) ) {
		$count[ $s->category ]++;
	}
	if ( 'ok' !== ap_supply_state( $s ) ) {
		$count['repor']++;
		$low++;
	}
	$value += max( 0, (float) $s->qty ) * (float) $s->unit_cost;
}
$rows = array_filter(
	$all,
	function ( $s ) use ( $tab ) {
		if ( 'todos' === $tab ) {
			return true;
		}
		return 'repor' === $tab ? 'ok' !== ap_supply_state( $s ) : $s->category === $tab;
	}
);
$moves = ap_rows( 'stock_moves', "item_type = 'supply'", array(), 'id DESC LIMIT 25' );
$kinds = array( 'compra' => 'Entrada', 'uso' => 'Produção', 'baixa' => 'Baixa', 'contagem' => 'Contagem' );

$actions  = '<a class="btn btn--ghost" href="' . esc_url( add_query_arg( 'contagem', '1', ap_panel_url( 'insumos', 0, 'todos' !== $tab ? array( 'cat' => $tab ) : array() ) ) ) . '">' . ap_icon( 'check', 16 ) . '<span>Contagem de estoque</span></a>';
$actions .= '<button type="button" class="btn btn--primary" data-sup-act="new">' . ap_icon( 'mais', 16 ) . '<span>Item</span></button>';
ap_panel_start( 'Estoque e insumos', 'insumos', $actions );
?>
<section class="stats stats--3 est-stats">
	<div class="stat"><span class="stat-label">Itens no estoque</span><strong><?php echo (int) $count['todos']; ?></strong></div>
	<div class="stat<?php echo $low ? ' stat--warn' : ''; ?>"><span class="stat-label">Para repor</span><strong><?php echo (int) $low; ?></strong></div>
	<div class="stat"><span class="stat-label">Valor em estoque (custo)</span><strong class="money"><?php echo esc_html( ap_money( $value ) ); ?></strong></div>
</section>

<nav class="fbl-tabs est-tabs">
	<?php foreach ( $tabs as $k => $l ) : ?>
		<a class="<?php echo (string) $k === $tab ? 'is-on' : ''; ?>" href="<?php echo esc_url( 'todos' === $k ? ap_panel_url( 'insumos', 0, 'contagem' === $mode ? array( 'contagem' => 1 ) : array() ) : ap_panel_url( 'insumos', 0, array( 'cat' => $k ) + ( 'contagem' === $mode ? array( 'contagem' => 1 ) : array() ) ) ); ?>"><?php echo esc_html( $l . ' (' . $count[ $k ] . ')' ); ?></a>
	<?php endforeach; ?>
</nav>

<?php if ( 'contagem' === $mode ) : ?>
	<section class="card">
		<div class="card-head"><h3>Contagem de estoque</h3><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( ap_panel_url( 'insumos', 0, 'todos' !== $tab ? array( 'cat' => $tab ) : array() ) ); ?>">Voltar à lista</a></div>
		<p class="muted small">Conte o que tem na prateleira e preencha só o que contou. Em <strong>bobina</strong>, informe os <strong>rolos fechados</strong> e os <strong>m² que sobraram no rolo aberto</strong>. O sistema ajusta o estoque e guarda a diferença no histórico.</p>
		<?php ap_form( 'supply_count', 'est-count' ); ?>
			<div class="table table--count">
				<div class="table-row table-head"><span>Item</span><span>No sistema</span><span>Contado</span></div>
				<?php foreach ( $rows as $s ) : $roll = 'm2' === $s->unit && (float) $s->roll_m2 > 0; ?>
					<div class="table-row">
						<span class="cell-main"><span><strong><?php echo esc_html( $s->name ); ?></strong><small><?php echo esc_html( $s->category . ( $s->counted_at ? ' · contado em ' . ap_date( $s->counted_at ) : '' ) ); ?></small></span></span>
						<span data-label="No sistema"><?php echo esc_html( ap_supply_qty_label( $s ) ); ?></span>
						<span data-label="Contado" class="est-inputs">
							<?php if ( $roll ) : ?>
								<label class="field field--inline"><span>rolos</span><input type="text" inputmode="decimal" name="c[<?php echo (int) $s->id; ?>][rolls]" placeholder="—"></label>
								<label class="field field--inline"><span>+ m²</span><input type="text" inputmode="decimal" name="c[<?php echo (int) $s->id; ?>][extra]" placeholder="—"></label>
							<?php else : ?>
								<label class="field field--inline"><span><?php echo esc_html( 'm2' === $s->unit ? 'm²' : $s->unit ); ?></span><input type="text" inputmode="decimal" name="c[<?php echo (int) $s->id; ?>][qty]" placeholder="—"></label>
							<?php endif; ?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="form-actions form-actions--sticky"><button class="btn btn--primary" type="submit">Salvar a contagem</button></div>
		</form>
	</section>
<?php else : ?>
	<div class="toolbar"><input type="search" class="filter" placeholder="Filtrar por nome, marca ou fornecedor…" data-filter=".table-row"></div>
	<?php if ( ! $rows ) : ?>
		<div class="empty empty--big"><?php echo ap_icon( 'caixa', 28 ); // phpcs:ignore ?><h3><?php echo 'repor' === $tab ? 'Nada para repor agora' : 'Nenhum item aqui'; ?></h3></div>
	<?php else : ?>
		<div class="table table--supplies est-table">
			<div class="table-row table-head"><span>Item</span><span>Estoque</span><span>Custo</span><span>Nível</span><span></span></div>
			<?php foreach ( $rows as $s ) : $st = ap_supply_state( $s ); $max = max( (float) $s->min_qty * 3, (float) $s->qty, 1 ); $pct = min( 100, round( (float) $s->qty / $max * 100 ) ); ?>
				<div class="table-row">
					<span class="cell-main"><span><strong><?php echo esc_html( $s->name ); ?></strong><small><?php echo esc_html( implode( ' · ', array_filter( array( $s->category, $s->supplier, $s->last_buy ? 'comprado em ' . ap_date( $s->last_buy ) : '' ) ) ) ); ?></small></span></span>
					<span data-label="Estoque"><strong><?php echo esc_html( ap_supply_qty_label( $s ) ); ?></strong><?php if ( 'm2' === $s->unit && (float) $s->roll_m2 > 0 ) : ?><small class="muted"> (<?php echo esc_html( ap_qty_label( $s->qty, 'm²' ) ); ?>)</small><?php endif; ?></span>
					<span data-label="Custo" class="money"><?php echo esc_html( 'R$ ' . number_format( (float) $s->unit_cost, $s->unit_cost < 1 ? 3 : 2, ',', '.' ) . ( 'm2' === $s->unit ? '/m²' : '' ) ); ?><?php echo (float) $s->sale_price > 0 ? '<small class="muted"> venda ' . esc_html( ap_money( $s->sale_price ) ) . '</small>' : ''; // phpcs:ignore ?></span>
					<span data-label="Nível" class="est-lvl">
						<span class="lvl tone-<?php echo 'ok' === $st ? 'ready' : ( 'low' === $st ? 'pay' : 'wait' ); ?>"><i style="width:<?php echo (int) $pct; ?>%"></i></span>
						<small class="<?php echo 'ok' === $st ? 'muted' : 'text-late'; ?>"><?php echo 'out' === $st ? 'zerado' : ( 'low' === $st ? 'repor (mín. ' . esc_html( ap_qty_label( $s->min_qty, $s->unit ) ) . ')' : ( $s->min_qty > 0 ? 'ok' : 'defina o mínimo' ) ); ?></small>
					</span>
					<span class="row-btns">
						<button type="button" class="btn btn--primary btn--sm" data-sup-act="buy" data-id="<?php echo (int) $s->id; ?>" data-name="<?php echo esc_attr( $s->name ); ?>" data-unit="<?php echo esc_attr( 'm2' === $s->unit ? 'm²' : $s->unit ); ?>">Entrada</button>
						<button type="button" class="btn btn--ghost btn--sm" data-sup-act="use" data-id="<?php echo (int) $s->id; ?>" data-name="<?php echo esc_attr( $s->name ); ?>" data-unit="<?php echo esc_attr( 'm2' === $s->unit ? 'm²' : $s->unit ); ?>">Baixa</button>
						<button type="button" class="icon-btn" data-sup-act="edit" data-id="<?php echo (int) $s->id; ?>" data-json="<?php echo esc_attr( wp_json_encode( array( 'name' => $s->name, 'category' => $s->category, 'unit' => $s->unit, 'min_qty' => (float) $s->min_qty, 'brand' => $s->brand, 'supplier' => $s->supplier, 'roll_m2' => (float) $s->roll_m2, 'roll_width' => (float) $s->roll_width, 'unit_cost' => (float) $s->unit_cost, 'sale_price' => (float) $s->sale_price, 'link' => $s->link, 'per_order' => (int) $s->per_order, 'notes' => $s->notes, 'qty' => (float) $s->qty ) ) ); ?>" title="Editar"><?php echo ap_icon( 'editar', 15 ); // phpcs:ignore ?></button>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $moves ) : ?>
		<section class="card">
			<div class="card-head"><h3>Últimas movimentações</h3></div>
			<div class="items-list">
				<?php foreach ( $moves as $m ) : $s = ap_get( 'supplies', $m->item_id ); ?>
					<div class="items-row"><span><?php echo esc_html( $s ? $s->name : '—' ); ?><small><?php echo esc_html( ap_date( $m->created_at ) . ' · ' . ( $kinds[ $m->kind ] ?? $m->kind ) . ( $m->note ? ' · ' . $m->note : '' ) . ( $m->project_id ? ' · pedido #' . (int) $m->project_id : '' ) ); ?></small></span><span class="<?php echo $m->qty < 0 ? 'text-late' : 'muted'; ?> small"><?php echo esc_html( ( $m->qty > 0 ? '+' : '' ) . ap_qty_label( $m->qty, $s ? $s->unit : 'un' ) ); ?></span><span class="money"><?php echo $m->total > 0 ? esc_html( ap_money( $m->total ) ) : ''; ?></span></div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
<?php endif; ?>

<button type="button" hidden data-open="m-buy" id="open-buy"></button><button type="button" hidden data-open="m-use" id="open-use"></button><button type="button" hidden data-open="m-edit" id="open-edit"></button>

<?php ap_modal_start( 'm-buy', 'Entrada de estoque' ); ?>
	<?php ap_form( 'supply_buy', 'stack' ); ?>
		<input type="hidden" name="id" data-f="id">
		<p class="muted small" data-f="title"></p>
		<div class="grid-3">
			<?php ap_input( 'qty', 'Quanto chegou', '', 'text', 'inputmode="decimal" required placeholder="1" data-f="qty"' ); ?>
			<?php ap_input( 'total', 'Quanto pagou (R$)', '', 'text', 'inputmode="decimal" data-money required data-buy-total' ); ?>
			<?php ap_input( 'freight', 'Frete (R$)', '', 'text', 'inputmode="decimal" data-money data-buy-freight' ); ?>
		</div>
		<p class="muted small">Para bobina, informe em m² (rolo de 50 m × 1,06 m = 53 m²). O custo por m² é calculado e o custo médio do item é atualizado.</p>
		<p class="buy-preview muted small" data-buy-preview>Custo por unidade: —</p>
		<?php ap_input( 'note', 'Fornecedor / observação', '', 'text' ); ?>
		<?php ap_check( 'lancar', 'Lançar no financeiro (saída paga hoje)', true ); ?>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Registrar entrada</button></div>
	</form>
<?php ap_modal_end(); ?>

<?php ap_modal_start( 'm-use', 'Baixa de estoque' ); ?>
	<?php ap_form( 'supply_use', 'stack' ); ?>
		<input type="hidden" name="id" data-f="id">
		<p class="muted small" data-f="title"></p>
		<?php ap_input( 'qty', 'Quanto saiu', '', 'text', 'inputmode="decimal" required' ); ?>
		<?php ap_input( 'note', 'Motivo', '', 'text', 'placeholder="Ex.: perda, teste de cor, amostra"' ); ?>
		<p class="muted small">A produção dos pedidos já dá baixa sozinha nas bobinas (com <?php echo esc_html( rtrim( rtrim( number_format( ap_num_setting( 'perda_impressao' ), 1, ',', '' ), '0' ), ',' ) ); ?>% de perda). Use esta baixa para o resto.</p>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Dar baixa</button></div>
	</form>
<?php ap_modal_end(); ?>

<?php ap_modal_start( 'm-edit', 'Item de estoque' ); ?>
	<?php ap_form( 'supply_edit', 'stack' ); ?>
		<input type="hidden" name="id" data-f="id" value="0">
		<?php ap_input( 'name', 'Nome', '', 'text', 'required data-f="name" placeholder="Ex.: Adesivo fosco · Seywa · 106 cm"' ); ?>
		<div class="grid-2">
			<?php ap_select( 'category', 'Categoria', $cats, 'Bobina', 'data-f="category"' ); ?>
			<?php ap_select( 'unit', 'Medido em', $units, 'm2', 'data-f="unit"' ); ?>
			<?php ap_input( 'min_qty', 'Avisar para repor abaixo de', '', 'text', 'inputmode="decimal" data-f="min_qty"' ); ?>
			<?php ap_input( 'qty', 'Estoque atual', '', 'text', 'inputmode="decimal" data-f="qty" data-new-only' ); ?>
			<?php ap_input( 'brand', 'Marca', '', 'text', 'data-f="brand"' ); ?>
			<?php ap_input( 'supplier', 'Fornecedor', '', 'text', 'data-f="supplier"' ); ?>
			<?php ap_input( 'roll_m2', 'm² de um rolo fechado (bobina)', '', 'text', 'inputmode="decimal" data-f="roll_m2"' ); ?>
			<?php ap_input( 'roll_width', 'Largura da bobina (cm)', '', 'text', 'inputmode="decimal" data-f="roll_width"' ); ?>
			<?php ap_input( 'unit_cost', 'Custo por unidade (ou m²)', '', 'text', 'inputmode="decimal" data-f="unit_cost"' ); ?>
			<?php ap_input( 'sale_price', 'Preço de venda (revenda)', '', 'text', 'inputmode="decimal" data-f="sale_price"' ); ?>
		</div>
		<?php ap_input( 'link', 'Link de compra (opcional)', '', 'url', 'data-f="link"' ); ?>
		<?php ap_input( 'notes', 'Observação', '', 'text', 'data-f="notes"' ); ?>
		<?php ap_check( 'per_order', 'Vai em todo pedido (aparece marcado na calculadora)' ); ?>
		<div class="form-actions">
			<span data-edit-only><?php ap_action_button( 'supply_archive', array( 'id' => 0 ), 'Arquivar', 'btn btn--link btn--sm', 'Arquivar este item?' ); ?></span>
			<button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button>
		</div>
	</form>
<?php ap_modal_end(); ?>

<script>
(function () {
	var $ = function (s, c) { return (c || document).querySelector(s); };
	function fill(modal, map) { Object.keys(map).forEach(function (k) { var el = $('[data-f="' + k + '"]', modal); if (el) { if (el.tagName === 'P') el.textContent = map[k]; else el.value = map[k]; } }); }
	function modal(id) { return document.getElementById(id) || $('[data-modal="' + id + '"]'); }
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-sup-act]'); if (!b) return;
		var act = b.getAttribute('data-sup-act'), id = b.getAttribute('data-id') || 0;
		if (act === 'buy' || act === 'use') {
			var m = modal('m-' + act);
			fill(m, { id: id, title: b.getAttribute('data-name') + ' · em ' + b.getAttribute('data-unit') });
			$('#open-' + act).click();
		} else {
			var m2 = modal('m-edit'), d = {};
			try { d = JSON.parse(b.getAttribute('data-json') || '{}'); } catch (x) {}
			var isNew = act === 'new';
			fill(m2, { id: isNew ? 0 : id, name: d.name || '', category: isNew ? 'Bobina' : (d.category || ''), unit: isNew ? 'm2' : (d.unit || 'un'), min_qty: d.min_qty || '', qty: d.qty || '', brand: d.brand || '', supplier: d.supplier || '', roll_m2: d.roll_m2 || '', roll_width: d.roll_width || '', unit_cost: d.unit_cost ? String(d.unit_cost).replace('.', ',') : '', sale_price: d.sale_price ? String(d.sale_price).replace('.', ',') : '', link: d.link || '', notes: d.notes || '' });
            var pc = $('[name=per_order]', m2); if (pc) pc.checked = !!d.per_order;
			var nq = $('[data-new-only]', m2); if (nq) nq.closest('label').hidden = !isNew;
			var ed = $('[data-edit-only]', m2); if (ed) { ed.hidden = isNew; var hid = $('input[name=id]', ed); if (hid) hid.value = id; }
			$('#open-edit').click();
		}
	});
	document.addEventListener('input', function (e) {
		var f = e.target.closest('form'); if (!f || !f.querySelector('[data-buy-preview]')) return;
		var n = function (v) { v = String(v || '').replace(/\./g, '').replace(',', '.'); return parseFloat(v) || 0; };
		var q = n(f.querySelector('[name=qty]').value.replace('.', ',')), t = n(f.querySelector('[data-buy-total]').value) + n(f.querySelector('[data-buy-freight]').value);
		f.querySelector('[data-buy-preview]').textContent = 'Custo por unidade: ' + (q > 0 ? 'R$ ' + (t / q).toFixed(t / q < 1 ? 3 : 2).replace('.', ',') : '—');
	});
})();
</script>
<?php
ap_panel_end();
