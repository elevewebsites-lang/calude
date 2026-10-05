<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$funnel = lk_funnel();
$won    = lk_funnel_won();
$lost   = lk_funnel_lost();
$tot    = lk_lead_totals();
$leads  = lk_leads();
$by     = array_fill_keys( array_keys( $funnel ), array() );
$first  = array_keys( $funnel )[0];
foreach ( $leads as $l ) {
	// Fechados/perdidos antigos (mais de 30 dias) saem do quadro.
	if ( in_array( $l->stage, array( $won, $lost ), true ) && strtotime( $l->updated_at ) < current_time( 'timestamp' ) - 30 * DAY_IN_SECONDS ) {
		continue;
	}
	$by[ isset( $by[ $l->stage ] ) ? $l->stage : $first ][] = $l;
}

$actions = '<button type="button" class="btn btn--primary" data-open="novo-lead">' . lk_icon( 'mais', 16 ) . '<span>Novo lead</span></button>';
lk_panel_start( 'Funil de leads', 'leads', $actions );
?>

<section class="stats stats--4">
	<div class="stat"><span class="stat-label">Leads em aberto</span><strong><?php echo (int) $tot['open']; ?></strong></div>
	<div class="stat stat--dark"><span class="stat-label">Em negociação</span><strong><?php echo esc_html( lk_money( $tot['pipeline'] ) ); ?></strong><small>soma do valor estimado</small></div>
	<div class="stat<?php echo $tot['followup'] ? ' stat--warn' : ''; ?>"><span class="stat-label">Follow-ups para hoje</span><strong><?php echo (int) $tot['followup']; ?></strong></div>
	<div class="stat"><span class="stat-label">Taxa de conversão</span><strong><?php echo (int) $tot['rate']; ?>%</strong><small>fechados ÷ (fechados + perdidos)</small></div>
</section>

<div class="kanban kanban--leads" data-kanban="lead">
	<?php foreach ( $funnel as $slug => $name ) : ?>
		<?php $sum = array_sum( wp_list_pluck( $by[ $slug ], 'value' ) ); ?>
		<section class="kcol<?php echo $slug === $won ? ' kcol--won' : ( $slug === $lost ? ' kcol--lost' : '' ); ?>">
			<header class="kcol-head"><h3><?php echo esc_html( $name ); ?></h3><span class="kcount"><?php echo (int) count( $by[ $slug ] ); ?></span></header>
			<?php if ( $sum > 0 ) : ?><div class="kcol-sum"><?php echo esc_html( lk_money( $sum ) ); ?></div><?php endif; ?>
			<div class="kcol-body" data-status="<?php echo esc_attr( $slug ); ?>">
				<?php foreach ( $by[ $slug ] as $l ) : ?>
					<article class="kcard kcard--lead" draggable="true" data-id="<?php echo (int) $l->id; ?>" data-href="<?php echo esc_url( lk_panel_url( 'lead', $l->id ) ); ?>">
						<div class="kcard-top">
							<?php if ( $l->client_id ) : ?><span class="badge badge--client" title="Já é cliente">Cliente</span><?php endif; ?>
							<?php if ( $l->source ) : ?><span class="badge"><?php echo esc_html( $l->source ); ?></span><?php endif; ?>
							<?php if ( $l->value > 0 ) : ?><strong class="kcard-value"><?php echo esc_html( lk_money( $l->value ) ); ?></strong><?php endif; ?>
						</div>
						<h4><?php echo esc_html( lk_lead_label( $l ) ); ?></h4>
						<?php if ( $l->source || ( $l->company && $l->name ) ) : ?><p class="kcard-sub"><?php echo esc_html( trim( ( $l->company && $l->name ? $l->name : '' ) . ( $l->source ? ' · ' . $l->source : '' ), ' ·' ) ); ?></p><?php endif; ?>
						<?php if ( $l->next_at ) : ?>
							<div class="kcard-next"><?php echo lk_due_badge( substr( $l->next_at, 0, 10 ) ); // phpcs:ignore ?><span><?php echo esc_html( $l->next_action ); ?></span></div>
						<?php endif; ?>
						<div class="kcard-foot">
							<span class="muted small"><?php echo $l->last_contact ? 'contato ' . esc_html( lk_ago( $l->last_contact ) ) : 'sem contato ainda'; ?></span>
							<?php if ( $l->whatsapp ) : ?><a class="icon-btn icon-btn--wa" href="<?php echo esc_url( lk_wa_link( $l->whatsapp ) ); ?>" target="_blank" rel="noopener" title="WhatsApp"><?php echo lk_icon( 'whatsapp', 15 ); // phpcs:ignore ?></a><?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>
</div>

<?php lk_modal_start( 'novo-lead', 'Novo lead' ); ?>
	<?php lk_form( 'lead_save', 'stack' ); ?>
		<?php lk_existing_client_select(); ?>
		<div class="grid-2">
			<?php lk_input( 'name', 'Nome' ); ?>
			<?php lk_input( 'company', 'Empresa' ); ?>
			<?php lk_input( 'whatsapp', 'WhatsApp', '', 'tel', 'data-mask="phone"' ); ?>
			<?php lk_input( 'email', 'E-mail', '', 'email' ); ?>
			<?php lk_select( 'source', 'Origem', array( '' => '—' ) + lk_list_options( 'origens' ) ); ?>
			<?php lk_input( 'value', 'Valor estimado (R$)', '', 'text', 'inputmode="decimal" data-money' ); ?>
			<?php lk_select( 'stage', 'Etapa', $funnel ); ?>
		</div>
		<?php lk_input( 'notes', 'Anotações', '', 'textarea', 'rows="3"' ); ?>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Criar lead</button></div>
	</form>
<?php lk_modal_end(); ?>

<?php
lk_panel_end();
