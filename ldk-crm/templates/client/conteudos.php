<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$ym   = isset( $_GET['mes'] ) && preg_match( '/^\d{4}-\d{2}$/', $_GET['mes'] ) ? sanitize_text_field( $_GET['mes'] ) : current_time( 'Y-m' );
$prev = gmdate( 'Y-m', strtotime( $ym . '-01 -1 month' ) );
$next = gmdate( 'Y-m', strtotime( $ym . '-01 +1 month' ) );
// O cliente só vê o que já saiu do planejamento interno (enviado a ele ou em produção).
$posts = array_values(
	array_filter(
		lk_plan_month_posts( $client->id, $ym ),
		function ( $p ) {
			return $p->stage !== lk_stage_for( 'planejamento' ) || $p->plan_id;
		}
	)
);
$plan = lk_plan_for( $client->id, $ym );
$link = function ( $p ) {
	$role = lk_post_role( $p );
	if ( 'planejamento' === $role ) {
		$pl = lk_plan_get( $p->plan_id );
		return $pl ? lk_plan_url( $pl ) : '';
	}
	return in_array( $role, array( 'aprovacao', 'agendado', 'publicado' ), true ) || $p->sent_at ? lk_post_url( $p ) : '';
};
lk_client_start( 'Conteúdos', $client );
?>
<section class="chello"><span class="eyebrow">Conteúdos</span><h1><?php echo esc_html( ucfirst( lk_month_label( $ym ) ) ); ?></h1><p class="muted">Todos os conteúdos do mês e em que etapa cada um está.</p></section>
<div class="cal-head">
	<a class="icon-btn" href="<?php echo esc_url( lk_client_link( 'conteudos', 0, array( 'mes' => $prev ) ) ); ?>">‹</a>
	<h3><?php echo esc_html( ucfirst( lk_month_label( $ym ) ) ); ?></h3>
	<a class="icon-btn" href="<?php echo esc_url( lk_client_link( 'conteudos', 0, array( 'mes' => $next ) ) ); ?>">›</a>
	<?php if ( $plan && 'enviado' === $plan->status ) : ?><a class="btn btn--primary btn--sm" href="<?php echo esc_url( lk_plan_url( $plan ) ); ?>">Aprovar o planejamento</a><?php endif; ?>
</div>
<div class="stg-legend">
	<?php foreach ( array( 'planejamento' => 'Planejamento', 'design' => 'Em criação', 'revisao' => 'Em revisão', 'aprovacao' => 'Aguardando você', 'agendado' => 'Agendado', 'publicado' => 'Publicado' ) as $k => $l ) : ?><em class="stg stg--<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></em><?php endforeach; ?>
</div>

<?php if ( ! $posts ) : ?>
	<div class="empty"><?php echo lk_icon( 'calendario', 26 ); // phpcs:ignore ?><h3>Nada neste mês ainda</h3><p class="muted">Assim que o planejamento estiver pronto, ele aparece aqui.</p></div>
<?php else : ?>
	<div class="cc-grid">
		<?php foreach ( $posts as $p ) : ?>
			<?php
			list( $role, $txt ) = lk_client_stage( $p );
			$url                = $link( $p );
			$thumb              = lk_post_thumb( $p );
			$tag                = $url ? 'a' : 'div';
			?>
			<<?php echo $tag; // phpcs:ignore ?> class="cc-card"<?php echo $url ? ' href="' . esc_url( $url ) . '"' : ''; ?>>
				<?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy"><?php else : ?><span class="cc-ph"><b><?php echo esc_html( lk_date( $p->scheduled_at, 'd' ) ); ?></b><small><?php echo esc_html( lk_month_name( (int) lk_date( $p->scheduled_at, 'n' ), true ) ); ?></small></span><?php endif; ?>
				<span class="cc-body">
					<small><?php echo esc_html( lk_dow_short( $p->scheduled_at ) . ', ' . lk_date( $p->scheduled_at, 'd/m' ) . ' · ' . ( lk_formats()[ $p->format ] ?? '' ) ); ?></small>
					<strong><?php echo esc_html( $p->title ); ?></strong>
					<em class="stg stg--<?php echo esc_attr( $role ); ?>"><?php echo esc_html( $txt ); ?></em>
				</span>
			</<?php echo $tag; // phpcs:ignore ?>>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
<?php
lk_client_end();
