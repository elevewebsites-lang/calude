<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$l = lk_get( 'leads', $id );
if ( ! $l ) {
	lk_render( 'panel/404' );
}
$funnel   = lk_funnel();
$label    = lk_lead_label( $l );
$activity = lk_rows( 'activity', 'lead_id = %d', array( $l->id ), 'id DESC' );
$lost     = lk_funnel_lost();
$is_lost  = $l->stage === $lost;

$actions = '';
if ( $l->whatsapp ) {
	$actions .= '<a class="btn btn--wa" target="_blank" rel="noopener" href="' . esc_url( lk_wa_link( $l->whatsapp ) ) . '">' . lk_icon( 'whatsapp', 16 ) . '<span>WhatsApp</span></a>';
}
lk_panel_start( $label, 'leads', $actions );
?>

<a class="back" href="<?php echo esc_url( lk_panel_url( 'leads' ) ); ?>"><?php echo lk_icon( 'voltar', 16 ); // phpcs:ignore ?> Funil</a>

<section class="project-hero">
	<div class="ph-main">
		<div class="ph-tags">
			<?php $lc = $l->client_id ? lk_get( 'clients', $l->client_id ) : null; ?>
			<?php if ( $lc ) : ?><a class="badge badge--client" href="<?php echo esc_url( lk_panel_url( 'cliente', $lc->id ) ); ?>">Já é cliente · <?php echo esc_html( lk_client_label( $lc ) ); ?></a><?php endif; ?>
			<?php if ( $l->source ) : ?><span class="badge"><?php echo esc_html( $l->source ); ?></span><?php endif; ?>
			<?php if ( $l->value > 0 ) : ?><span class="badge"><?php echo esc_html( lk_money( $l->value ) ); ?></span><?php endif; ?>
			<?php if ( $is_lost && $l->lost_reason ) : ?><span class="badge badge--urgente">Perdido: <?php echo esc_html( $l->lost_reason ); ?></span><?php endif; ?>
		</div>
		<h2><?php echo esc_html( $label ); ?></h2>
		<div class="flow">
			<?php $reached = true; ?>
			<?php foreach ( $funnel as $slug => $name ) : ?>
				<?php if ( $slug === $lost && ! $is_lost ) { continue; } ?>
				<span class="flow-step<?php echo $slug === $l->stage ? ' is-current' : ( $reached ? ' is-done' : '' ); ?>"><?php echo esc_html( $name ); ?></span>
				<?php
				if ( $slug === $l->stage ) {
					$reached = false;
				}
				?>
			<?php endforeach; ?>
		</div>
	</div>
	<div class="lead-cta">
		<?php if ( $l->client_id ) : ?>
			<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'cliente', $l->client_id ) ); ?>">Ver cliente</a>
		<?php else : ?>
			<?php lk_action_button( 'lead_to_client', array( 'lead_id' => $l->id ), 'Virar cliente', 'btn btn--ghost btn--sm' ); ?>
		<?php endif; ?>
		<?php if ( lk_can( 'orcamentos' ) ) : ?>
			<?php lk_action_button( 'lead_to_quote', array( 'lead_id' => $l->id ), 'Fazer orçamento', 'btn btn--primary btn--sm' ); ?>
		<?php endif; ?>
	</div>
</section>

<div class="detail-grid">
	<div class="detail-main">
		<section class="card card--accent">
			<div class="card-head"><h3>Próximo passo</h3><?php echo $l->next_at ? '<span class="muted small">' . esc_html( $l->next_action . ' · ' . lk_date( $l->next_at, 'd/m \à\s H:i' ) ) . '</span>' : ''; ?></div>
			<?php lk_form( 'lead_reminder', 'reminder-form' ); ?>
				<input type="hidden" name="lead_id" value="<?php echo (int) $l->id; ?>">
				<input type="text" name="what" placeholder="Ex.: Mandar mensagem sobre a proposta" value="<?php echo esc_attr( $l->next_action ); ?>">
				<input type="date" name="date" value="<?php echo esc_attr( $l->next_at ? substr( $l->next_at, 0, 10 ) : gmdate( 'Y-m-d', strtotime( lk_today() . ' +2 day' ) ) ); ?>" required>
				<input type="time" name="time" value="<?php echo esc_attr( $l->next_at ? substr( $l->next_at, 11, 5 ) : '10:00' ); ?>">
				<button type="submit" class="btn btn--primary btn--sm">Lembrar</button>
			</form>
			<div class="quick-dates">
				<?php foreach ( array( 1 => 'Amanhã', 3 => 'Em 3 dias', 7 => 'Em 1 semana' ) as $d => $txt ) : ?>
					<button type="button" class="btn btn--ghost btn--sm" data-quick-date="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( lk_today() . ' +' . $d . ' day' ) ) ); ?>"><?php echo esc_html( $txt ); ?></button>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="card">
			<div class="card-head"><h3>Anotações e histórico</h3></div>
			<?php lk_form( 'lead_note', 'comment-box' ); ?>
				<input type="hidden" name="lead_id" value="<?php echo (int) $l->id; ?>">
				<textarea name="body" rows="2" placeholder="Como foi a conversa? O que o lead precisa?" required></textarea>
				<div class="form-actions"><?php lk_check( 'contact', 'Registrar como contato feito', true ); ?><button type="submit" class="btn btn--primary btn--sm">Salvar</button></div>
			</form>
			<ul class="timeline">
				<?php foreach ( $activity as $a ) : ?>
					<?php $au = $a->user_id ? get_userdata( $a->user_id ) : null; ?>
					<li class="<?php echo 'comment' === $a->type ? 'is-comment' : ''; ?>"><span class="dot"></span><div><p><?php echo nl2br( esc_html( $a->body ) ); // phpcs:ignore ?></p><small><?php echo esc_html( ( $au ? $au->display_name . ' · ' : '' ) . lk_ago( $a->created_at ) ); ?></small></div></li>
				<?php endforeach; ?>
			</ul>
		</section>
	</div>

	<aside class="detail-side">
		<section class="card">
			<div class="card-head"><h3>Dados</h3></div>
			<?php lk_form( 'lead_save', 'stack' ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $l->id; ?>">
				<?php lk_select( 'stage', 'Etapa', $funnel, $l->stage ); ?>
				<?php lk_existing_client_select( $l->client_id ); ?>
				<?php lk_input( 'name', 'Nome', $l->name ); ?>
				<?php lk_input( 'company', 'Empresa', $l->company ); ?>
				<div class="grid-2">
					<?php lk_input( 'whatsapp', 'WhatsApp', $l->whatsapp, 'tel', 'data-mask="phone"' ); ?>
					<?php lk_input( 'email', 'E-mail', $l->email, 'email' ); ?>
				</div>
				<div class="grid-2">
					<?php lk_select( 'source', 'Origem', array( '' => '—' ) + lk_list_options( 'origens' ), $l->source ); ?>
					<?php lk_input( 'value', 'Valor estimado', $l->value > 0 ? number_format( (float) $l->value, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money' ); ?>
				</div>
				<?php lk_input( 'notes', 'Resumo', $l->notes, 'textarea', 'rows="3"' ); ?>
				<div class="form-actions"><button type="submit" class="btn btn--primary">Salvar</button></div>
			</form>
		</section>
		<div class="side-actions">
			<?php if ( ! $is_lost && $lost ) : ?>
				<?php lk_form( 'lead_lost', 'inline-form lost-form' ); ?><input type="hidden" name="lead_id" value="<?php echo (int) $l->id; ?>"><input type="text" name="reason" placeholder="Motivo (opcional)"><button type="submit" class="btn btn--ghost btn--sm">Marcar como perdido</button></form>
			<?php endif; ?>
			<?php lk_action_button( 'lead_delete', array( 'id' => $l->id ), 'Excluir', 'btn btn--danger btn--sm', 'Excluir este lead e o histórico?' ); ?>
		</div>
	</aside>
</div>

<?php
lk_panel_end();
