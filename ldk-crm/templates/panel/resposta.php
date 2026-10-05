<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s = lk_get( 'form_sends', $id );
$c = $s ? lk_get( 'clients', $s->client_id ) : null;
if ( ! $s || ! $c ) {
	lk_render( 'panel/404' );
}
lk_panel_start( $s->title, 'formularios', '<button type="button" class="btn btn--ghost" onclick="window.print()">Imprimir / salvar em PDF</button>' );
?>
<a class="back" href="<?php echo esc_url( lk_panel_url( 'cliente', $c->id ) ); ?>#formularios"><?php echo lk_icon( 'voltar', 16 ); // phpcs:ignore ?> <?php echo esc_html( lk_client_label( $c ) ); ?></a>
<section class="card fm-answers">
	<p class="muted small"><?php echo esc_html( lk_form_kinds()[ $s->kind ] . ' · ' . lk_form_status_label( $s->status ) . ( $s->answered_at ? ' em ' . lk_date( $s->answered_at, 'd/m/Y H:i' ) : '' ) ); ?><?php if ( $s->drive_url ) : ?> · <a href="<?php echo esc_url( $s->drive_url ); ?>" target="_blank" rel="noopener">abrir no Drive</a><?php endif; ?></p>
	<?php echo 'respondido' === $s->status ? lk_form_answers_html( $s, false ) : '<p class="muted">O cliente ainda não respondeu.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput ?>
</section>
<?php
lk_panel_end();
