<?php
/**
 * Aba Roteiros: todos os roteiros de vídeo por dia de gravação e cliente.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
lk_scripts_backfill();
$f      = isset( $_GET['f'] ) ? sanitize_key( $_GET['f'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$cid    = isset( $_GET['cliente'] ) ? absint( $_GET['cliente'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$labels = lk_script_status_labels();
$where  = '1=1';
$args   = array();
if ( $f && isset( $labels[ $f ] ) ) {
	$where .= ' AND status = %s';
	$args[] = $f;
}
if ( $cid ) {
	$where .= ' AND client_id = %d';
	$args[] = $cid;
}
$rows  = lk_rows( 'scripts', $where, $args, 'record_date IS NULL, record_date, id' );
$days  = array();
foreach ( $rows as $s ) {
	$days[ $s->record_date ? $s->record_date : '' ][ $s->client_id ][] = $s;
}
lk_panel_start( 'Roteiros', 'roteiros', '' );
?>
<section class="card">
	<p class="muted small">Todo post de <strong>vídeo</strong> ganha um roteiro aqui. Defina o dia da gravação: <strong>3 dias antes</strong> o cliente recebe o roteiro do dia por e-mail e a atendente ganha uma tarefa para avisar no WhatsApp e pedir a aprovação.</p>
</section>
<div class="tabs-row">
	<a class="chip<?php echo '' === $f ? ' is-active' : ''; ?>" href="<?php echo esc_url( lk_panel_url( 'roteiros' ) ); ?>">Todos</a>
	<?php foreach ( $labels as $k => $l ) : ?><a class="chip<?php echo $f === $k ? ' is-active' : ''; ?>" href="<?php echo esc_url( lk_panel_url( 'roteiros', 0, array( 'f' => $k ) ) ); ?>"><?php echo esc_html( $l ); ?></a><?php endforeach; ?>
</div>
<?php if ( ! $rows ) : ?>
	<div class="empty empty--big"><h3>Nenhum roteiro ainda</h3><p>Crie um post com o formato <strong>Vídeo</strong> e o roteiro aparece aqui.</p></div>
<?php endif; ?>
<?php $daywa = get_transient( 'lk_day_wa_' . get_current_user_id() ); ?>
<?php if ( $daywa && $daywa['wa'] ) : ?><div class="apx-msg" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap"><span>✅ Roteiros do dia <?php echo esc_html( lk_date( $daywa['date'], 'd/m' ) ); ?> enviados. Reforce no WhatsApp:</span><a class="btn btn--wa" href="<?php echo esc_url( $daywa['wa'] ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Mandar tudo no WhatsApp</span></a></div><?php endif; ?>
<?php foreach ( $days as $date => $clients ) : ?>
	<h2 class="section-title" style="margin:26px 0 10px"><?php echo $date ? esc_html( lk_date( $date, 'd/m/Y' ) . ' · ' . lk_dow_short( $date ) ) . ( lk_days_until( $date ) >= 0 && lk_days_until( $date ) <= 3 ? ' · em ' . (int) lk_days_until( $date ) . ' dia(s)' : '' ) : 'Sem dia de gravação'; ?></h2>
	<?php foreach ( $clients as $client_id => $list ) : ?>
		<?php $client = lk_get( 'clients', $client_id ); ?>
		<?php if ( $date && count( $list ) > 0 ) : ?>
			<div class="form-actions" style="margin:6px 0"><span class="muted small"><?php echo (int) count( $list ); ?> roteiro(s) de <?php echo esc_html( $client ? lk_client_label( $client ) : '' ); ?> no mesmo dia:</span> <?php lk_action_button( 'script_send_day', array( 'client_id' => $client_id, 'date' => $date ), lk_icon( 'email', 16 ) . '<span>Enviar todos juntos ao cliente</span>', 'btn btn--primary btn--sm' ); ?></div>
		<?php endif; ?>
		<?php foreach ( $list as $s ) : ?>
			<?php $wa = lk_script_wa_link( $s ); $post = lk_get( 'posts', $s->post_id ); ?>
			<section class="card" id="roteiro-<?php echo (int) $s->id; ?>">
				<div class="card-head">
					<h3><?php echo esc_html( $s->title ); ?> <small class="muted">· <?php echo esc_html( $client ? lk_client_label( $client ) : '' ); ?></small></h3>
					<span class="badge <?php echo 'aprovado' === $s->status ? 'badge--ok' : ( 'ajustes' === $s->status ? 'badge--warn' : '' ); ?>"><?php echo esc_html( $labels[ $s->status ] ?? $s->status ); ?></span>
				</div>
				<?php if ( 'ajustes' === $s->status && $s->client_note ) : ?><p class="muted"><strong>Ajuste pedido pelo cliente:</strong> <?php echo nl2br( esc_html( $s->client_note ) ); ?></p><?php endif; ?>
				<?php lk_form( 'script_save', 'stack' ); ?>
					<input type="hidden" name="id" value="<?php echo (int) $s->id; ?>">
					<div class="grid-3">
						<?php lk_input( 'record_date', 'Dia da gravação', (string) $s->record_date, 'date' ); ?>
						<?php lk_input( 'record_time', 'Horário', (string) $s->record_time, 'time' ); ?>
						<?php lk_input( 'place', 'Local', (string) $s->place, 'text', 'placeholder="Endereço ou \'na empresa\'"' ); ?>
					</div>
					<?php lk_input( 'title', 'Nome do vídeo', $s->title ); ?>
					<?php lk_input( 'body', 'Roteiro (o que falar e mostrar, cena por cena)', (string) $s->body, 'textarea', 'rows="8" placeholder="Cena 1 — Abertura (5s): …&#10;Cena 2 — …"' ); ?>
					<div class="form-actions">
						<?php if ( $post ) : ?><a class="btn btn--link btn--sm" href="<?php echo esc_url( lk_panel_url( 'post', $post->id ) ); ?>">Abrir o post</a><?php endif; ?>
						<button type="submit" class="btn btn--primary btn--sm">Salvar roteiro</button>
					</div>
				</form>
				<div class="form-actions" style="margin-top:10px;flex-wrap:wrap;gap:8px">
					<?php lk_action_button( 'script_send', array( 'id' => $s->id ), lk_icon( 'email', 16 ) . '<span>Enviar ao cliente (e-mail)</span>', 'btn btn--ghost btn--sm' ); ?>
					<?php if ( $wa ) : ?><a class="btn btn--wa btn--sm" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Mandar no WhatsApp</span></a><?php endif; ?>
					<button type="button" class="btn btn--ghost btn--sm" data-copy="<?php echo esc_attr( lk_script_url( $s ) ); ?>">Copiar link</button>
					<?php if ( 'aprovado' !== $s->status ) : ?><?php lk_action_button( 'script_status', array( 'id' => $s->id, 'to' => 'aprovado' ), 'Marcar como aprovado', 'btn btn--link btn--sm', 'Marcar este roteiro como aprovado pelo cliente?' ); ?><?php endif; ?>
				</div>
			</section>
		<?php endforeach; ?>
	<?php endforeach; ?>
<?php endforeach; ?>
<?php
lk_panel_end();
