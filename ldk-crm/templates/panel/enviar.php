<?php
/**
 * Enviar ao cliente: tudo que está pronto e espera o atendimento mandar (e-mail + WhatsApp em um clique).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$items = lk_flow_ready_items();
$total = count( $items['plans'] ) + count( $items['resend'] ) + count( $items['weeks'] );
lk_panel_start( 'Enviar ao cliente', 'enviar', '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'semana' ) ) . '">Escolher cliente e semana</a>' );
?>
<section class="card"><p class="muted small">Aqui aparece tudo que já está pronto para ir ao cliente: planejamentos revisados, ajustes refeitos e a semana fechada. Toda comunicação com o cliente passa pelo atendimento.</p></section>
<?php if ( ! $total ) : ?><div class="empty empty--big"><h3>Nada esperando envio 🎉</h3><p>Quando algo ficar pronto, aparece aqui e na sua lista de tarefas.</p></div><?php endif; ?>

<?php if ( $items['plans'] ) : ?>
	<h2 class="section-title" style="margin:22px 0 10px">Planejamentos prontos</h2>
	<?php foreach ( $items['plans'] as $pl ) : ?>
		<?php $c = lk_get( 'clients', $pl->client_id ); $wa = $c && $c->whatsapp ? lk_wa_link( $c->whatsapp, lk_plan_message( $pl, $c ) ) : ''; ?>
		<section class="card"><div class="card-head"><h3><?php echo esc_html( lk_client_label( $c ) ); ?> <small class="muted">· planejamento de <?php echo esc_html( lk_month_label( $pl->period ) ); ?></small></h3><span class="badge badge--ok">revisado</span></div>
			<div class="form-actions" style="flex-wrap:wrap;gap:8px">
				<?php lk_action_button( 'plan_send_ready', array( 'id' => $pl->id ), lk_icon( 'email', 16 ) . '<span>Enviar por e-mail</span>', 'btn btn--primary btn--sm' ); ?>
				<?php if ( $wa ) : ?><a class="btn btn--wa btn--sm" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Mandar no WhatsApp</span></a><?php endif; ?>
				<a class="btn btn--link btn--sm" href="<?php echo esc_url( lk_panel_url( 'planejamento', 0, array( 'cliente' => $pl->client_id, 'mes' => $pl->period ) ) ); ?>">Abrir</a>
			</div></section>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( $items['resend'] ) : ?>
	<h2 class="section-title" style="margin:22px 0 10px">Ajustes refeitos para reenviar</h2>
	<?php foreach ( $items['resend'] as $p ) : ?>
		<?php $c = lk_get( 'clients', $p->client_id ); $wa = $c && $c->whatsapp ? lk_wa_link( $c->whatsapp, lk_approval_message( $p, $c ) ) : ''; ?>
		<section class="card"><div class="card-head"><h3><?php echo esc_html( $p->title ); ?> <small class="muted">· <?php echo esc_html( lk_client_label( $c ) ); ?></small></h3><span class="badge badge--warn">ajuste feito</span></div>
			<div class="form-actions" style="flex-wrap:wrap;gap:8px">
				<?php lk_action_button( 'post_send', array( 'id' => $p->id ), lk_icon( 'email', 16 ) . '<span>Enviar por e-mail</span>', 'btn btn--primary btn--sm' ); ?>
				<?php if ( $wa ) : ?><a class="btn btn--wa btn--sm" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Mandar no WhatsApp</span></a><?php endif; ?>
				<a class="btn btn--link btn--sm" href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>">Abrir</a>
			</div></section>
	<?php endforeach; ?>
<?php endif; ?>

<?php if ( $items['weeks'] ) : ?>
	<h2 class="section-title" style="margin:22px 0 10px">Semana fechada</h2>
	<?php foreach ( $items['weeks'] as $w ) : ?>
		<?php $c = $w['client']; $sent_posts = array_filter( $w['posts'], function ( $p ) { return lk_stage_for( 'revisao' ) === $p->stage; } ); ?>
		<?php if ( ! $sent_posts ) { continue; } ?>
		<section class="card"><div class="card-head"><h3><?php echo esc_html( lk_client_label( $c ) ); ?> <small class="muted">· <?php echo esc_html( lk_date( $w['from'], 'd/m' ) . ' a ' . lk_date( $w['to'], 'd/m' ) ); ?></small></h3><span class="badge badge--ok"><?php echo (int) count( $sent_posts ); ?> pronto(s)</span></div>
			<div class="form-actions"><a class="btn btn--primary btn--sm" href="<?php echo esc_url( lk_panel_url( 'semana', 0, array( 'cliente' => $c->id, 'semana' => $w['from'] ) ) ); ?>">Revisar e enviar a semana</a></div></section>
	<?php endforeach; ?>
<?php endif; ?>
<?php
lk_panel_end();
