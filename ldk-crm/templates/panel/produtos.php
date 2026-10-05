<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$f     = isset( $_GET['f'] ) ? sanitize_key( $_GET['f'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$where = 'portfolio' === $f ? 'portfolio = 1' : ( 'venda' === $f ? 'active = 1' : '1=1' );
$prods = lk_rows( 'products', $where, array(), 'id DESC' );
$lists = array();
foreach ( lk_rows( 'listings', "status = 'ativo'" ) as $l ) {
	$lists[ $l->product_id ][] = $l->channel;
}
$ch      = lk_channels();
$actions = '<button type="button" class="btn btn--ghost" data-open="importar">' . lk_icon( 'link', 16 ) . '<span>Importar do MakerWorld</span></button><a class="btn btn--primary" href="' . esc_url( lk_panel_url( 'produto' ) ) . '">' . lk_icon( 'mais', 16 ) . '<span>Produto</span></a>';
lk_panel_start( 'Produtos', 'produtos', $actions );
?>
<div class="toolbar">
	<div class="seg">
		<a href="<?php echo esc_url( lk_panel_url( 'produtos' ) ); ?>" class="<?php echo '' === $f ? 'is-active' : ''; ?>">Todos</a>
		<a href="<?php echo esc_url( lk_panel_url( 'produtos', 0, array( 'f' => 'portfolio' ) ) ); ?>" class="<?php echo 'portfolio' === $f ? 'is-active' : ''; ?>">Portfólio (já fiz)</a>
		<a href="<?php echo esc_url( lk_panel_url( 'produtos', 0, array( 'f' => 'venda' ) ) ); ?>" class="<?php echo 'venda' === $f ? 'is-active' : ''; ?>">À venda</a>
	</div>
	<input type="search" class="filter" placeholder="Filtrar…" data-filter=".prod">
</div>
<p class="muted small hint">O portfólio guarda tudo o que você já fez (foto, preço cobrado, custo, gramas e tempo) para orçar parecido rapidinho. Qualquer produto pode virar anúncio no Mercado Livre, Shopee, Elo7 e outros, com o preço de cada canal já com a comissão embutida.</p>

<?php if ( ! $prods ) : ?>
	<div class="empty empty--big"><?php echo lk_icon( 'tag', 28 ); // phpcs:ignore ?><h3>Nenhum produto ainda</h3><p>Quando um pedido ficar pronto, clique em "Salvar no portfólio". Ou importe um modelo do MakerWorld pelo link.</p></div>
<?php else : ?>
	<div class="prod-grid">
		<?php foreach ( $prods as $p ) : ?>
			<?php $cover = lk_product_cover( $p ); ?>
			<a class="prod" href="<?php echo esc_url( lk_panel_url( 'produto', $p->id ) ); ?>">
				<div class="prod-img"><?php if ( $cover ) : ?><img src="<?php echo esc_url( $cover ); ?>" alt="" loading="lazy"><?php else : ?><?php echo lk_icon( 'cubo', 32 ); // phpcs:ignore ?><?php endif; ?>
					<?php if ( $p->portfolio ) : ?><em class="badge prod-tag">portfólio</em><?php endif; ?>
				</div>
				<div class="prod-body">
					<strong><?php echo esc_html( $p->name ); ?></strong>
					<div class="prod-nums">
						<span class="money"><?php echo $p->price > 0 ? esc_html( lk_money( $p->price ) ) : 'sem preço'; ?></span>
						<?php if ( $p->cost > 0 ) : ?><small class="muted">custo <?php echo esc_html( lk_money( $p->cost ) ); ?></small><?php endif; ?>
					</div>
					<?php if ( $p->sold_count ) : ?><small class="muted">Vendido <?php echo (int) $p->sold_count; ?>× · cobrado <?php echo esc_html( lk_money( $p->sold_price ) ); ?></small><?php endif; ?>
					<?php if ( ! empty( $lists[ $p->id ] ) ) : ?><div class="prod-ch"><?php foreach ( $lists[ $p->id ] as $c ) : ?><em class="badge badge--channel"><?php echo esc_html( $ch[ $c ]['name'] ?? $c ); ?></em><?php endforeach; ?></div><?php endif; ?>
					<?php if ( 'pessoal' === $p->license ) : ?><small class="text-late">Licença só para uso pessoal</small><?php endif; ?>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php lk_modal_start( 'importar', 'Importar modelo' ); ?>
	<?php lk_form( 'product_import', 'stack' ); ?>
		<?php lk_input( 'url', 'Link do modelo (MakerWorld, Printables, Thingiverse…)', '', 'url', 'required placeholder="https://makerworld.com/pt/models/…"' ); ?>
		<p class="muted small">O painel pega o nome, a imagem e a descrição. Antes de vender, confira a licença do modelo na página dele: muitos são só para uso pessoal, e vender exige licença comercial do criador (no MakerWorld, procure "Commercial Use" ou a assinatura do criador).</p>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Importar</button></div>
	</form>
<?php lk_modal_end(); ?>
<?php
lk_panel_end();
