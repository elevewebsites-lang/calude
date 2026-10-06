<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$p = $id ? ap_get( 'products', $id ) : null;
if ( $id && ! $p ) {
	ap_render( 'panel/404' );
}
$calc = ! $p && isset( $_GET['calc'] ) ? ap_get( 'calcs', absint( $_GET['calc'] ) ) : null;
// phpcs:enable
$cd     = $calc ? ap_calc_data( $calc ) : null;
$photos = $p ? ap_product_photos( $p ) : array();
$v      = function ( $k, $def = '' ) use ( $p ) {
	return $p ? $p->$k : $def;
};
$price = $p ? (float) $p->price : ( $calc ? (float) $calc->price : 0 );
$cost  = $p ? (float) $p->cost : ( $calc ? (float) $calc->cost : 0 );
$lists = array();
if ( $p ) {
	foreach ( ap_rows( 'listings', 'product_id = %d', array( $p->id ) ) as $l ) {
		$lists[ $l->channel ] = $l;
	}
}
$src = $p && $p->source_project ? ap_get( 'projects', $p->source_project ) : null;
ap_panel_start( $p ? $p->name : 'Novo produto', 'produtos' );
?>
<a class="back" href="<?php echo esc_url( ap_panel_url( 'produtos' ) ); ?>"><?php echo ap_icon( 'voltar', 16 ); // phpcs:ignore ?> Produtos</a>

<?php ap_form( 'product_save', 'split', true ); ?>
	<input type="hidden" name="id" value="<?php echo (int) ( $p ? $p->id : 0 ); ?>">
	<input type="hidden" name="calc_id" value="<?php echo (int) ( $calc ? $calc->id : 0 ); ?>">
	<div class="split-main">
		<section class="card">
			<div class="card-head"><h3>Produto</h3><?php if ( $src ) : ?><a class="small" href="<?php echo esc_url( ap_panel_url( 'pedido', $src->id ) ); ?>">veio do pedido #<?php echo (int) $src->id; ?></a><?php endif; ?></div>
			<div class="grid-3">
				<?php ap_input( 'name', 'Nome', $v( 'name', $calc ? $calc->name : '' ), 'text', 'required' ); ?>
				<?php ap_input( 'sku', 'Código (SKU)', $v( 'sku' ) ); ?>
				<?php ap_input( 'category', 'Categoria', $v( 'category' ), 'text', 'placeholder="Geek, decoração, brinde…"' ); ?>
			</div>
			<?php ap_input( 'description', 'Descrição', $v( 'description' ), 'textarea', 'rows="4"' ); ?>
			<?php if ( $photos ) : ?>
				<div class="thumbs thumbs--edit">
					<?php foreach ( $photos as $img ) : ?>
						<label class="thumb-edit"><img src="<?php echo esc_url( $img['thumb'] ?? $img['url'] ); ?>" alt=""><?php if ( ! empty( $img['id'] ) ) : ?><span><input type="checkbox" name="remove_img[]" value="<?php echo (int) $img['id']; ?>"> remover</span><?php endif; ?></label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<div class="grid-2">
				<label class="drop"><input type="file" name="photos[]" accept="image/*" multiple><?php echo ap_icon( 'imagem', 20 ); // phpcs:ignore ?><span><strong>Fotos</strong><small>vão para o Drive também</small></span></label>
				<?php ap_input( 'photo_url', 'Ou o link de uma imagem', '', 'url' ); ?>
			</div>
		</section>

		<section class="card">
			<div class="card-head"><h3>Números</h3><a class="small" href="<?php echo esc_url( ap_panel_url( 'calculadora' ) ); ?>">recalcular na calculadora</a></div>
			<div class="grid-4">
				<?php ap_input( 'cost', 'Custo por peça (R$)', $cost ? number_format( $cost, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money data-prod-cost' ); ?>
				<?php ap_input( 'price', 'Preço de venda (R$)', $price ? number_format( $price, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money data-prod-price' ); ?>
				<?php ap_input( 'sold_price', 'Preço que cobrei', $v( 'sold_price' ) > 0 ? number_format( $v( 'sold_price' ), 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money' ); ?>
				<?php ap_input( 'stock', 'Pronta-entrega (un.)', $v( 'stock', 0 ), 'number', 'min="0"' ); ?>
				<?php ap_input( 'grams', 'Gramas por peça', $v( 'grams', $cd ? ( $cd['out']['grams_unit'] ?? '' ) : '' ), 'text', 'inputmode="decimal"' ); ?>
				<?php ap_input( 'print_hours', 'Horas por peça', $v( 'print_hours', $cd ? ( $cd['out']['hours_unit'] ?? '' ) : '' ), 'text', 'inputmode="decimal"' ); ?>
				<?php ap_input( 'weight_g', 'Peso com embalagem (g)', $v( 'weight_g', '' ), 'number', 'min="0"' ); ?>
				<?php ap_input( 'dims', 'Medidas', $v( 'dims' ), 'text', 'placeholder="10 × 8 × 5 cm"' ); ?>
			</div>
		</section>

		<section class="card">
			<div class="card-head"><h3>Canais de venda</h3><span class="muted small">preço com a comissão embutida (Configurações → Canais)</span></div>
			<div class="channels" data-channels>
				<?php foreach ( ap_channels() as $slug => $c ) : ?>
					<?php $l = $lists[ $slug ] ?? null; $suggest = ap_channel_price( $price, $slug ); ?>
					<div class="channel">
						<div class="channel-head"><strong><?php echo esc_html( $c['name'] ); ?></strong><small class="muted"><?php echo esc_html( str_replace( '.', ',', $c['pct'] ) . '%' . ( $c['fixed'] ? ' + ' . ap_money( $c['fixed'] ) : '' ) ); ?></small></div>
						<div class="grid-3">
							<label class="field"><span>Preço no canal</span><input type="text" name="ch[<?php echo esc_attr( $slug ); ?>][price]" inputmode="decimal" value="<?php echo esc_attr( number_format( $l ? (float) $l->price : $suggest, 2, ',', '.' ) ); ?>" data-ch-price data-pct="<?php echo esc_attr( $c['pct'] ); ?>" data-fixed="<?php echo esc_attr( $c['fixed'] ); ?>"></label>
							<label class="field"><span>Link do anúncio</span><input type="url" name="ch[<?php echo esc_attr( $slug ); ?>][url]" value="<?php echo esc_attr( $l ? $l->url : '' ); ?>" placeholder="cole depois de anunciar"></label>
							<label class="field"><span>Status</span><select name="ch[<?php echo esc_attr( $slug ); ?>][status]"><?php foreach ( array( 'rascunho' => 'Não anunciado', 'ativo' => 'Anunciado', 'pausado' => 'Pausado' ) as $k => $lab ) : ?><option value="<?php echo esc_attr( $k ); ?>"<?php selected( $l ? $l->status : 'rascunho', $k ); ?>><?php echo esc_html( $lab ); ?></option><?php endforeach; ?></select></label>
						</div>
						<div class="channel-foot">
							<span class="muted small" data-ch-profit>Você recebe: —</span>
							<?php if ( $c['link'] ) : ?><a class="small" href="<?php echo esc_url( $c['link'] ); ?>" target="_blank" rel="noopener">Anunciar ↗</a><?php endif; ?>
							<?php if ( $l && $l->url ) : ?><a class="small" href="<?php echo esc_url( $l->url ); ?>" target="_blank" rel="noopener">Ver anúncio ↗</a><?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( $p ) : ?>
				<?php $txt = ap_listing_text( $p ); ?>
				<details class="listing-text">
					<summary>Texto pronto para o anúncio</summary>
					<label class="field"><span>Título (até 60 caracteres)</span><input type="text" readonly value="<?php echo esc_attr( $txt['title'] ); ?>" onclick="this.select()"></label>
					<label class="field"><span>Descrição</span><textarea rows="7" readonly onclick="this.select()"><?php echo esc_textarea( $txt['description'] ); ?></textarea></label>
					<button type="button" class="btn btn--ghost btn--sm" data-copy="<?php echo esc_attr( $txt['title'] . "\n\n" . $txt['description'] ); ?>">Copiar tudo</button>
				</details>
			<?php endif; ?>
		</section>
	</div>

	<aside class="split-side">
		<section class="card">
			<div class="card-head"><h3>Status</h3></div>
			<?php ap_check( 'active', 'À venda', $p ? (bool) $p->active : true ); ?>
			<?php ap_check( 'portfolio', 'Mostrar no portfólio (já fiz)', $p ? (bool) $p->portfolio : false ); ?>
			<?php ap_select( 'license', 'Licença do modelo', ap_licenses(), $v( 'license' ) ); ?>
			<?php ap_input( 'source_url', 'Link do modelo', $v( 'source_url' ), 'url' ); ?>
			<?php if ( $p && $p->source_url ) : ?><a class="small" href="<?php echo esc_url( $p->source_url ); ?>" target="_blank" rel="noopener">Abrir modelo ↗</a><?php endif; ?>
			<div class="form-actions"><button type="submit" class="btn btn--primary btn--block">Salvar produto</button></div>
		</section>
		<?php if ( $p ) : ?>
			<section class="card">
				<div class="card-head"><h3>Vendeu?</h3></div>
				<p class="muted small">Registre a venda do marketplace: vira pedido na fila e entra no financeiro.</p>
				<button type="button" class="btn btn--ghost btn--block" data-open="venda">Registrar venda</button>
				<a class="btn btn--ghost btn--block" href="<?php echo esc_url( ap_panel_url( 'orcamento' ) ); ?>">Usar em um orçamento</a>
				<?php ap_action_button( 'product_delete', array( 'id' => $p->id ), 'Excluir produto', 'btn btn--danger btn--block', 'Excluir este produto?' ); ?>
			</section>
		<?php endif; ?>
	</aside>
</form>

<?php if ( $p ) : ?>
	<?php ap_modal_start( 'venda', 'Venda: ' . $p->name ); ?>
		<?php ap_form( 'product_sale', 'stack' ); ?>
			<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
			<div class="grid-2">
				<?php ap_select( 'channel', 'Canal', array_map( function ( $c ) { return $c['name']; }, ap_channels() ) ); ?>
				<?php ap_input( 'qty', 'Quantidade', 1, 'number', 'min="1"' ); ?>
				<?php ap_input( 'value', 'Quanto vai receber (líquido)', '', 'text', 'inputmode="decimal" data-money' ); ?>
				<?php ap_input( 'payout_date', 'Quando o dinheiro cai', '', 'date' ); ?>
				<?php ap_input( 'buyer', 'Comprador', '', 'text' ); ?>
				<?php ap_input( 'due_date', 'Prazo de envio', '', 'date' ); ?>
			</div>
			<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Registrar</button></div>
		</form>
	<?php ap_modal_end(); ?>
<?php endif; ?>

<script>
(function () {
	var n = function (v) { v = String(v || '').trim(); if (/,\d{1,2}$/.test(v)) v = v.replace(/\./g, '').replace(',', '.'); return parseFloat(v.replace(',', '.')) || 0; };
	var m = function (v) { return 'R$ ' + v.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
	function upd() {
		var cost = n((document.querySelector('[data-prod-cost]') || {}).value);
		document.querySelectorAll('[data-ch-price]').forEach(function (i) {
			var get = n(i.value) * (1 - (+i.dataset.pct) / 100) - (+i.dataset.fixed);
			var out = i.closest('.channel').querySelector('[data-ch-profit]');
			out.textContent = 'Você recebe ' + m(get) + (cost ? ' · lucro ' + m(get - cost) : '');
			out.classList.toggle('text-late', cost > 0 && get - cost < 0);
		});
	}
	document.addEventListener('input', upd); upd();
})();
</script>
<?php
ap_panel_end();
