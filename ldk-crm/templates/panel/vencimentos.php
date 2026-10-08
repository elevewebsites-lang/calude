<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$ver  = isset( $_GET['ver'] ) ? sanitize_key( $_GET['ver'] ) : 'todos'; // phpcs:ignore WordPress.Security.NonceVerification
$all  = lk_rows( 'renewals', '1=1', array(), 'due_date IS NULL, due_date ASC' );
$soon = 0;
$late = 0;
foreach ( $all as $r ) {
	$d = lk_renewal_days( $r );
	if ( null !== $d && $d < 0 ) {
		$late++;
	} elseif ( null !== $d && $d <= 30 ) {
		$soon++;
	}
}
$rows = array_filter(
	$all,
	function ( $r ) use ( $ver ) {
		$d = lk_renewal_days( $r );
		if ( 'vencidos' === $ver ) {
			return null !== $d && $d < 0;
		}
		if ( 'proximos' === $ver ) {
			return null !== $d && $d >= 0 && $d <= 30;
		}
		return true;
	}
);
lk_panel_start( 'Hospedagem e domínios', 'vencimentos' );
?>
<section class="stats stats--3">
	<div class="stat"><span class="stat-label">Vencidos</span><strong class="<?php echo $late ? 'text-late' : ''; ?>"><?php echo (int) $late; ?></strong><small>precisam de renovação</small></div>
	<div class="stat"><span class="stat-label">Vencem em 30 dias</span><strong><?php echo (int) $soon; ?></strong><small>avisamos a equipe antes</small></div>
	<div class="stat"><span class="stat-label">Cadastrados</span><strong><?php echo (int) count( $all ); ?></strong><small>hospedagens, domínios e outros</small></div>
</section>
<section class="card">
	<div class="card-head"><span class="chips">
		<?php foreach ( array( 'todos' => 'Todos', 'proximos' => 'Próximos 30 dias', 'vencidos' => 'Vencidos' ) as $k => $lab ) : ?>
			<a class="btn btn--sm btn--<?php echo $ver === $k ? 'primary' : 'ghost'; ?>" href="<?php echo esc_url( lk_panel_url( 'vencimentos', 0, 'todos' === $k ? array() : array( 'ver' => $k ) ) ); ?>"><?php echo esc_html( $lab ); ?></a>
		<?php endforeach; ?>
	</span></div>
	<div class="pros-wrap"><table class="pros-table">
		<thead><tr><th>Cliente</th><th>Item</th><th>Fornecedor</th><th>Vence em</th><th>Valor</th><th></th></tr></thead>
		<tbody>
		<?php foreach ( $rows as $r ) : $c = lk_get( 'clients', $r->client_id ); if ( ! $c ) { continue; } ?>
			<tr>
				<td><a class="net-cli" href="<?php echo esc_url( lk_panel_url( 'cliente', $c->id ) ); ?>#vencimentos"><strong><?php echo esc_html( lk_client_label( $c ) ); ?></strong></a></td>
				<td><?php echo esc_html( lk_renewal_name( $r ) ); ?></td>
				<td><?php echo esc_html( $r->provider ); ?></td>
				<td><?php echo esc_html( lk_date( $r->due_date ) ); ?> <?php echo lk_renewal_badge( $r ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
				<td><?php echo (float) $r->value > 0 ? esc_html( lk_money( $r->value ) ) : '—'; ?></td>
				<td class="net-act"><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'cliente', $c->id ) ); ?>#vencimentos">Abrir</a></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $rows ) : ?><tr><td colspan="6" class="muted">Nada por aqui. Cadastre a hospedagem e o domínio na ficha de cada cliente, em <strong>Hospedagem, domínio e vencimentos</strong>.</td></tr><?php endif; ?>
		</tbody>
	</table></div>
</section>
<?php
lk_panel_end();
