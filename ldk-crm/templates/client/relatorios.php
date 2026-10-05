<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$reps = lk_rows( 'reports', "client_id = %d AND status = 'publicado'", array( $client->id ), 'period DESC' );
lk_client_start( 'Relatórios', $client );
?>
<section class="chello"><span class="eyebrow">Relatórios</span><h1>Resultados mês a mês</h1></section>
<?php if ( ! $reps ) : ?><div class="empty"><?php echo lk_icon( 'grafico', 26 ); // phpcs:ignore ?><h3>O primeiro relatório sai no fim do mês</h3></div><?php endif; ?>
<ul class="mini-list card"><?php foreach ( $reps as $r ) : ?><li><a href="<?php echo esc_url( lk_report_url( $r ) ); ?>" target="_blank"><strong><?php echo esc_html( ucfirst( lk_month_label( $r->period ) ) ); ?></strong><small>abrir relatório →</small></a></li><?php endforeach; ?></ul>
<?php
lk_client_end();
