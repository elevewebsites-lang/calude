<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$status = isset( $_GET['s'] ) ? sanitize_key( $_GET['s'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$st     = lk_quote_statuses();
$where  = $status && isset( $st[ $status ] ) ? $status : '';
$quotes = $where ? lk_rows( 'quotes', 'status = %s', array( $where ), 'id DESC' ) : lk_rows( 'quotes', '1=1', array(), 'id DESC LIMIT 200' );
$tot    = lk_quote_totals();
$actions = ( lk_module( 'estoque' ) ? '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'calculadora' ) ) . '">' . lk_icon( 'calculadora', 16 ) . '<span>Calculadora</span></a>' : '' ) . '<a class="btn btn--primary" href="' . esc_url( lk_panel_url( 'orcamento' ) ) . '">' . lk_icon( 'mais', 16 ) . '<span>Novo orçamento</span></a>';
lk_panel_start( 'Orçamentos', 'orcamentos', $actions );
?>
<section class="stats stats--4">
	<div class="stat"><span class="stat-label">Em aberto</span><strong><?php echo (int) $tot['open']; ?></strong><small class="money"><?php echo esc_html( lk_money( $tot['open_value'] ) ); ?></small></div>
	<div class="stat"><span class="stat-label">Aprovados</span><strong><?php echo (int) $tot['won']; ?></strong><small class="money"><?php echo esc_html( lk_money( $tot['won_value'] ) ); ?></small></div>
	<div class="stat"><span class="stat-label">Conversão</span><strong><?php echo $tot['open'] + $tot['won'] ? (int) round( $tot['won'] / ( $tot['open'] + $tot['won'] ) * 100 ) : 0; ?>%</strong><small>aprovados ÷ enviados</small></div>
	<div class="stat stat--dark"><span class="stat-label">Ticket médio</span><strong class="money"><?php echo esc_html( lk_money( $tot['won'] ? $tot['won_value'] / $tot['won'] : 0 ) ); ?></strong><small>dos aprovados</small></div>
</section>

<div class="toolbar">
	<div class="seg">
		<a href="<?php echo esc_url( lk_panel_url( 'orcamentos' ) ); ?>" class="<?php echo '' === $where ? 'is-active' : ''; ?>">Todos</a>
		<?php foreach ( $st as $k => $label ) : ?><a href="<?php echo esc_url( lk_panel_url( 'orcamentos', 0, array( 's' => $k ) ) ); ?>" class="<?php echo $where === $k ? 'is-active' : ''; ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?>
	</div>
	<input type="search" class="filter" placeholder="Filtrar…" data-filter=".table-row:not(.table-head)">
</div>

<?php if ( ! $quotes ) : ?>
	<div class="empty empty--big"><?php echo lk_icon( 'proposta', 28 ); // phpcs:ignore ?><h3>Nenhum orçamento ainda</h3><p>Monte o preço na calculadora e transforme em orçamento com fotos, 3D e pagamento na hora.</p><a class="btn btn--primary" href="<?php echo esc_url( lk_panel_url( 'calculadora' ) ); ?>">Abrir a calculadora</a></div>
<?php else : ?>
	<div class="table table--quotes">
		<div class="table-row table-head"><span>Orçamento</span><span>Público</span><span>Status</span><span>Total</span><span>Lucro</span><span></span></div>
		<?php foreach ( $quotes as $q ) : ?>
			<?php
			$cl  = $q->client_id ? lk_get( 'clients', $q->client_id ) : null;
			$img = lk_json( $q->images );
			?>
			<div class="table-row">
				<a class="cell-main" href="<?php echo esc_url( lk_panel_url( 'orcamento', $q->id ) ); ?>">
					<?php if ( $img ) : ?><img class="row-thumb" src="<?php echo esc_url( $img[0]['thumb'] ?? $img[0]['url'] ); ?>" alt=""><?php else : ?><span class="row-thumb row-thumb--empty"><?php echo lk_icon( 'cubo', 16 ); // phpcs:ignore ?></span><?php endif; ?>
					<span><strong><?php echo esc_html( $q->title ); ?></strong><small><?php echo esc_html( ( $cl ? lk_client_label( $cl ) . ' · ' : '' ) . lk_date( $q->created_at ) ); ?></small></span>
				</a>
				<span data-label="Público"><?php echo esc_html( lk_audiences()[ $q->audience ] ?? '' ); ?></span>
				<span data-label="Status"><em class="badge badge--q-<?php echo esc_attr( $q->status ); ?>"><?php echo esc_html( $st[ $q->status ] ?? $q->status ); ?></em><?php echo $q->views ? ' <small class="muted">' . (int) $q->views . '×</small>' : ''; ?></span>
				<span data-label="Total" class="money"><?php echo esc_html( lk_money( $q->total ) ); ?></span>
				<span data-label="Lucro" class="money"><?php echo $q->cost_total > 0 ? esc_html( lk_money( $q->total - $q->cost_total ) ) : '—'; ?></span>
				<span class="row-btns">
					<?php if ( 'rascunho' !== $q->status ) : ?><button type="button" class="btn btn--ghost btn--sm" data-copy="<?php echo esc_attr( lk_quote_url( $q ) ); ?>">Copiar link</button><?php endif; ?>
				</span>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
<?php
lk_panel_end();
