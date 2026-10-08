<?php
/**
 * Enviar a semana: as artes prontas do cliente, todas juntas, com o link de cada uma.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$cid  = isset( $_GET['cliente'] ) ? absint( $_GET['cliente'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$base = isset( $_GET['semana'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_GET['semana'] ) ? sanitize_text_field( wp_unslash( $_GET['semana'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
list( $from, $to ) = lk_week_range( $base );
$prev   = gmdate( 'Y-m-d', strtotime( $from . ' -7 day' ) );
$next   = gmdate( 'Y-m-d', strtotime( $from . ' +7 day' ) );
$client = $cid ? lk_get( 'clients', $cid ) : null;
$posts  = $client ? lk_week_posts( $client->id, $from, $to ) : array();
$last   = get_transient( 'lk_week_wa_' . get_current_user_id() );
lk_panel_start( 'Enviar a semana', 'semana', '' );
?>
<section class="card">
	<p class="muted small">As artes vão ficando prontas ao longo da semana. Aqui você escolhe o cliente e manda <strong>tudo junto</strong>, com o link de cada post. O cliente aprova um e já passa para o próximo.</p>
	<form method="get" class="stack" action="<?php echo esc_url( lk_panel_url( 'semana' ) ); ?>">
		<div class="grid-2">
			<?php lk_select( 'cliente', 'Cliente', lk_client_options( 'Escolha o cliente…' ), $cid ); ?>
			<label class="field"><span>Semana (qualquer dia dela)</span><input type="date" name="semana" value="<?php echo esc_attr( $from ); ?>"></label>
		</div>
		<div class="form-actions"><button class="btn btn--primary btn--sm" type="submit">Ver as artes da semana</button></div>
	</form>
</section>
<?php if ( $last && $client && (int) $last['client'] === (int) $client->id && $last['wa'] ) : ?>
	<div class="apx-msg" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap"><span>✅ <?php echo (int) $last['n']; ?> post(s) enviados. Reforce no WhatsApp:</span><a class="btn btn--wa" href="<?php echo esc_url( $last['wa'] ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Mandar tudo no WhatsApp</span></a></div>
<?php endif; ?>
<?php if ( $client ) : ?>
	<div class="cal-head" style="margin:18px 0 8px"><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'semana', 0, array( 'cliente' => $cid, 'semana' => $prev ) ) ); ?>">← Semana anterior</a><strong style="margin:0 12px"><?php echo esc_html( lk_client_label( $client ) . ' · ' . lk_date( $from, 'd/m' ) . ' a ' . lk_date( $to, 'd/m' ) ); ?></strong><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'semana', 0, array( 'cliente' => $cid, 'semana' => $next ) ) ); ?>">Próxima →</a></div>
	<?php if ( ! $posts ) : ?>
		<div class="empty"><h3>Nada pronto para enviar nesta semana</h3><p>Só entram posts com arte, em Revisão ou já enviados, que ainda não foram aprovados.</p></div>
	<?php else : ?>
		<?php lk_form( 'week_send', 'card stack' ); ?>
			<input type="hidden" name="client_id" value="<?php echo (int) $client->id; ?>">
			<input type="hidden" name="from" value="<?php echo esc_attr( $from ); ?>">
			<input type="hidden" name="to" value="<?php echo esc_attr( $to ); ?>">
			<ul class="mini-list">
				<?php foreach ( $posts as $p ) : ?>
					<li><label class="chk" style="display:flex;gap:10px;align-items:center"><input type="checkbox" name="posts[]" value="<?php echo (int) $p->id; ?>" checked><span><strong><?php echo esc_html( $p->title ); ?></strong><br><small class="muted"><?php echo esc_html( ( lk_formats()[ $p->format ] ?? '' ) . ' · ' . lk_date( $p->scheduled_at, 'd/m' ) . ' ' . substr( $p->scheduled_at, 11, 5 ) . ' · ' . ( $p->stage === lk_stage_for( 'aprovacao' ) ? 'já enviado' : 'em revisão' ) ); ?></small></span></label><a href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>" class="btn btn--link btn--sm">Abrir</a></li>
				<?php endforeach; ?>
			</ul>
			<div class="form-actions"><?php lk_check( 'forcar', 'Enviar mesmo com pontos da revisão de texto', false ); ?><button type="submit" class="btn btn--primary"><?php echo lk_icon( 'email', 16 ); // phpcs:ignore ?><span>Enviar a semana ao cliente</span></button></div>
		</form>
	<?php endif; ?>
<?php endif; ?>
<?php
lk_panel_end();
