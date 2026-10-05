<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
list( $pend, $plans ) = lk_client_pending( $client );
$next   = lk_posts( 'p.client_id = %d AND p.scheduled_at >= %s AND ( p.plan_id > 0 OR p.stage <> %s )', array( $client->id, lk_now(), lk_stage_for( 'planejamento' ) ), 'p.scheduled_at LIMIT 8' );
$bills  = lk_rows( 'transactions', "client_id = %d AND type = 'in' AND status = 'pendente'", array( $client->id ), 'due_date' );
$report = lk_rows( 'reports', "client_id = %d AND status = 'publicado'", array( $client->id ), 'period DESC LIMIT 1' );
lk_client_start( 'Início', $client );
$first = $client->name ? strtok( $client->name, ' ' ) : lk_client_label( $client );
?>
<section class="chello chello--logo"><?php echo lk_client_avatar_html( $client, 'avatar avatar--xl' ); // phpcs:ignore ?><div><span class="eyebrow">Área do cliente</span><h1>Olá, <?php echo esc_html( $first ); ?>.</h1><p class="muted">Aprove seus conteúdos, veja o calendário e os relatórios.</p></div></section>
<?php foreach ( $plans as $pl ) : ?>
	<a class="plan-cta" href="<?php echo esc_url( lk_plan_url( $pl ) ); ?>"><span><strong>📝 Planejamento de <?php echo esc_html( lk_month_label( $pl->period ) ); ?> pronto</strong><small>Veja os temas e as legendas do mês e aprove</small></span><em>Ver e aprovar →</em></a>
<?php endforeach; ?>
<?php if ( ! $client->briefing_at ) : ?>
	<a class="plan-cta plan-cta--soft" href="<?php echo esc_url( lk_client_link( 'briefing' ) ); ?>"><span><strong>📋 Responda o briefing</strong><small>Conte sobre a sua marca para os conteúdos ficarem com a sua cara</small></span><em>Responder →</em></a>
<?php endif; ?>
<div class="quick-actions">
	<a class="qa qa--main" href="<?php echo esc_url( lk_client_link( 'aprovacoes' ) ); ?>"><?php echo lk_icon( 'check', 22 ); // phpcs:ignore ?><span><strong><?php echo count( $pend ) + count( $plans ); ?> para aprovar</strong><small>arte e legenda</small></span></a>
	<a class="qa" href="<?php echo esc_url( lk_client_link( 'conteudos' ) ); ?>"><?php echo lk_icon( 'calendario', 22 ); // phpcs:ignore ?><span><strong>Conteúdos do mês</strong><small>calendário e etapas</small></span></a>
	<a class="qa" href="<?php echo esc_url( lk_client_link( 'relatorios' ) ); ?>"><?php echo lk_icon( 'grafico', 22 ); // phpcs:ignore ?><span><strong>Relatórios</strong><small><?php echo $report ? 'último: ' . esc_html( lk_month_label( $report[0]->period ) ) : 'em breve'; ?></small></span></a>
	<a class="qa" href="<?php echo esc_url( lk_client_link( 'mensagens' ) ); ?>"><?php echo lk_icon( 'chat', 22 ); // phpcs:ignore ?><span><strong>Mensagens</strong><small>fale com a equipe</small></span></a>
</div>
<?php foreach ( $bills as $t ) : ?>
	<div class="flash flash--warn"><strong><?php echo esc_html( $t->description ); ?></strong> · <?php echo esc_html( lk_money( $t->amount ) . ' · vence ' . lk_date( $t->due_date ) ); ?> <a class="btn btn--primary btn--sm" href="<?php echo esc_url( lk_pay_url( $t->id ) ); ?>">Pagar</a></div>
<?php endforeach; ?>
<section class="card">
	<div class="card-head"><h3>Próximas publicações</h3></div>
	<?php if ( ! $next ) : ?><p class="muted small">Nada agendado por enquanto.</p><?php endif; ?>
	<ul class="mini-list">
		<?php foreach ( $next as $p ) : ?><?php list( $role, $txt ) = lk_client_stage( $p ); ?><li><a class="ml-row" href="<?php echo esc_url( lk_client_link( 'conteudos', 0, array( 'mes' => substr( $p->scheduled_at, 0, 7 ) ) ) ); ?>"><?php echo lk_thumb_html( $p, 'row-thumb' ); // phpcs:ignore ?><span class="ml-txt"><strong><?php echo esc_html( lk_date( $p->scheduled_at, 'd/m H:i' ) . ' · ' . $p->title ); ?></strong><small><?php echo esc_html( lk_formats()[ $p->format ] ?? '' ); ?> <em class="stg stg--<?php echo esc_attr( $role ); ?>"><?php echo esc_html( $txt ); ?></em></small></span></a></li><?php endforeach; ?>
	</ul>
</section>
<?php
lk_client_end();
