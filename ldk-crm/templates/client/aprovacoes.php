<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
list( $pend, $plans ) = lk_client_pending( $client );
$done = lk_posts( 'p.client_id = %d AND p.client_status IN (%s, %s)', array( $client->id, 'aprovado', 'alteracao' ), 'p.id DESC LIMIT 30' );
lk_client_start( 'Aprovações', $client );
$card = function ( $p, $cta ) {
	$img = lk_post_thumb( $p );
	echo '<a class="apc" href="' . esc_url( lk_post_url( $p ) ) . '">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="" loading="lazy">' : '<span class="apc-ph">' . lk_icon( 'imagem', 26 ) . '</span>' ) . '<span class="apc-body"><strong>' . esc_html( $p->title ) . '</strong><small>' . esc_html( ( lk_formats()[ $p->format ] ?? '' ) . ( $p->scheduled_at ? ' · ' . lk_date( $p->scheduled_at, 'd/m' ) : '' ) ) . '</small><em class="' . ( $cta ? 'apc-cta' : 'badge' ) . '">' . esc_html( $cta ? $cta : ( 'aprovado' === $p->client_status ? 'Aprovado' : 'Ajuste pedido' ) ) . '</em></span></a>'; // phpcs:ignore
};
?>
<section class="chello"><span class="eyebrow">Aprovações</span><h1>Conteúdos para aprovar</h1><p class="muted">Abra cada um, confira a arte e a legenda, e aprove ou peça ajuste.</p></section>
<?php foreach ( $plans as $pl ) : ?>
	<a class="plan-cta" href="<?php echo esc_url( lk_plan_url( $pl ) ); ?>"><span><strong>📝 Planejamento de <?php echo esc_html( lk_month_label( $pl->period ) ); ?></strong><small>Temas e legendas do mês para você aprovar</small></span><em>Ver e aprovar →</em></a>
<?php endforeach; ?>
<?php if ( ! $pend && ! $plans ) : ?><div class="empty"><?php echo lk_icon( 'check', 26 ); // phpcs:ignore ?><h3>Tudo aprovado por aqui ✨</h3></div><?php elseif ( $pend ) : ?><div class="apc-grid"><?php foreach ( $pend as $p ) { $card( $p, 'Aprovar' ); } ?></div><?php endif; ?>
<?php if ( $done ) : ?><section class="card"><div class="card-head"><h3>Respondidos</h3></div><div class="apc-grid apc-grid--sm"><?php foreach ( $done as $p ) { $card( $p, '' ); } ?></div></section><?php endif; ?>
<?php
lk_client_end();
