<?php
/**
 * Alterações pedidas pelo cliente (arte e/ou legenda) que estão com você. Quando terminar, o atendimento é avisado.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$list = lk_flow_alterations();
lk_panel_start( 'Alterações', 'alteracoes', '' );
?>
<section class="card">
	<p class="muted small">Pedidos de ajuste do cliente (por texto, áudio ou referência). Cada um também vira uma <strong>tarefa com prazo</strong>. Quando você termina, clique em <em>Refiz</em> e o atendimento recebe a tarefa de enviar de novo ao cliente.</p>
</section>
<?php if ( ! $list ) : ?>
	<div class="empty empty--big"><h3>Nenhuma alteração pendente 🎉</h3><p>Quando um cliente pedir ajuste, aparece aqui.</p></div>
<?php endif; ?>
<?php foreach ( $list as $p ) : ?>
	<?php
	$client = lk_get( 'clients', $p->client_id );
	$what   = array( 'arte' => 'Arte', 'legenda' => 'Legenda', 'ambos' => 'Arte e legenda' )[ $p->change_target ] ?? 'Apontamento';
	$msgs   = lk_rows( 'post_comments', "post_id = %d AND from_client = 1 AND target IN ('arte','legenda','ambos','apontamento','geral')", array( $p->id ), 'id DESC LIMIT 5' );
	$tk     = '';
	global $wpdb;
	$due = $wpdb->get_var( $wpdb->prepare( 'SELECT MIN(due_date) FROM ' . lk_table( 'tasks' ) . " WHERE grp = 'Fluxo' AND status <> 'done' AND description LIKE %s", '%[alt:' . (int) $p->id . ':%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	?>
	<section class="card">
		<div class="card-head">
			<h3><?php echo esc_html( $p->title ); ?> <small class="muted">· <?php echo esc_html( $client ? lk_client_label( $client ) : '' ); ?></small></h3>
			<span class="badge badge--warn"><?php echo esc_html( $what ); ?></span>
		</div>
		<?php if ( $due ) : ?><p class="small"><strong>Prazo:</strong> <?php echo esc_html( lk_date( $due, 'd/m/Y' ) ); ?> <?php echo lk_due_badge( $due ); // phpcs:ignore ?></p><?php endif; ?>
		<?php foreach ( array_reverse( $msgs ) as $m ) : ?>
			<div class="plan-note"><strong>Cliente:</strong> <?php echo nl2br( esc_html( $m->body ) ); ?><?php echo function_exists( 'lk_attach_html' ) ? lk_attach_html( $m ) : ''; // phpcs:ignore ?> <small class="muted"><?php echo esc_html( lk_ago( $m->created_at ) ); ?></small></div>
		<?php endforeach; ?>
		<div class="form-actions" style="flex-wrap:wrap;gap:8px;margin-top:10px">
			<a class="btn btn--primary btn--sm" href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) . '#apontamentos' ); ?>">Abrir o post</a>
			<?php if ( in_array( $p->change_target, array( 'legenda', 'ambos' ), true ) ) : ?><?php lk_action_button( 'post_redone_caption', array( 'id' => $p->id ), '✓ Refiz a legenda', 'btn btn--ghost btn--sm', 'Já ajustou a legenda?' ); ?><?php endif; ?>
			<?php if ( in_array( $p->change_target, array( 'arte', 'ambos' ), true ) ) : ?><span class="muted small">Arte: suba a nova arte no post (ela vai sozinha para revisão e avisa o atendimento).</span><?php endif; ?>
		</div>
	</section>
<?php endforeach; ?>
<?php
lk_panel_end();
