<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$pend = lk_billing_pending();
$last = get_transient( 'lk_last_charge_' . get_current_user_id() );
if ( $last ) {
	delete_transient( 'lk_last_charge_' . get_current_user_id() );
}
lk_panel_start( 'Mensalidades e cobranças', 'cobrancas' );
?>
<?php if ( $last && $last['wa'] ) : ?><section class="card card--accent ready-bar"><div>Cobrança de <strong><?php echo esc_html( $last['who'] ); ?></strong> enviada por e-mail.</div><a class="btn btn--wa" href="<?php echo esc_url( $last['wa'] ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Mandar no WhatsApp com o link</span></a></section><?php endif; ?>
<section class="stats stats--3">
	<div class="stat stat--dark"><span class="stat-label">Receita recorrente</span><strong class="money"><?php echo esc_html( lk_money( lk_billing_mrr() ) ); ?></strong><small>por mês</small></div>
	<div class="stat"><span class="stat-label">Pendentes</span><strong><?php echo count( $pend ); ?></strong><small class="money"><?php echo esc_html( lk_money( array_sum( wp_list_pluck( $pend, 'amount' ) ) ) ); ?></small></div>
	<div class="stat"><span class="stat-label">Vencidas</span><strong class="text-late"><?php echo count( array_filter( $pend, function ( $t ) { return $t->due_date < lk_today(); } ) ); ?></strong></div>
</section>
<section class="card">
	<div class="card-head"><h3>Pendentes</h3><span class="muted small">vencidas e a vencer em 10 dias · lembrete automático por e-mail também sai sozinho</span></div>
	<?php if ( ! $pend ) : ?><p class="muted small">Tudo pago. 🎉</p><?php endif; ?>
	<?php foreach ( $pend as $t ) : ?>
		<?php $st = lk_trans_status( $t ); $ch = get_option( 'lk_charged_' . $t->id ); ?>
		<div class="pay-row">
			<span><strong><?php echo esc_html( $t->description ); ?></strong><small><?php echo esc_html( lk_money( $t->amount ) . ' · vence ' . lk_date( $t->due_date ) ); ?><?php echo $ch ? ' · cobrado ' . esc_html( lk_ago( $ch ) ) : ''; ?></small></span>
			<em class="badge badge--<?php echo 'atrasado' === $st ? 'late' : 'warn'; ?>"><?php echo esc_html( $st ); ?></em>
			<?php lk_action_button( 'billing_charge', array( 'id' => $t->id ), lk_icon( 'whatsapp', 14 ) . '<span>Cobrar</span>', 'btn btn--primary btn--sm' ); ?>
			<button type="button" class="btn btn--ghost btn--sm" data-copy="<?php echo esc_attr( lk_pay_url( $t->id ) ); ?>">Copiar link</button>
			<?php lk_action_button( 'trans_pay', array( 'id' => $t->id ), 'Marcar pago', 'btn btn--ghost btn--sm' ); ?>
		</div>
	<?php endforeach; ?>
</section>
<section class="card">
	<div class="card-head"><h3>Mensalidade de cada cliente</h3></div>
	<div class="table">
		<?php foreach ( lk_clients() as $c ) : ?>
			<?php lk_form( 'billing_client', 'table-row billing-row' ); ?>
				<input type="hidden" name="client_id" value="<?php echo (int) $c->id; ?>">
				<span class="cell-main"><strong><?php echo esc_html( lk_client_label( $c ) ); ?></strong></span>
				<label class="field field--inline"><span>R$</span><input type="text" name="monthly_fee" value="<?php echo esc_attr( $c->monthly_fee > 0 ? number_format( $c->monthly_fee, 2, ',', '.' ) : '' ); ?>" inputmode="decimal" size="8"></label>
				<label class="field field--inline"><span>dia</span><input type="number" name="due_day" min="1" max="28" value="<?php echo (int) $c->due_day; ?>" style="width:64px"></label>
				<label class="chk"><input type="checkbox" name="billing_active" value="1"<?php checked( $c->billing_active ); ?>> ativa</label>
				<button type="submit" class="btn btn--ghost btn--sm">Salvar</button>
			</form>
		<?php endforeach; ?>
	</div>
</section>
<?php
lk_panel_end();
