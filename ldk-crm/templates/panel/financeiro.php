<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;
$table = lk_table( 'transactions' );
$today = lk_today();
$month = isset( $_GET['mes'] ) && preg_match( '/^\d{4}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['mes'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['mes'] ) ) : substr( $today, 0, 7 ); // phpcs:ignore WordPress.Security.NonceVerification
$type  = isset( $_GET['tipo'] ) ? sanitize_key( $_GET['tipo'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$prev  = gmdate( 'Y-m', strtotime( $month . '-01 -1 month' ) );
$next  = gmdate( 'Y-m', strtotime( $month . '-01 +1 month' ) );

// Resumo do mês: pelo pagamento (pagos) e pelo vencimento (pendentes).
$sum = function ( $t, $status ) use ( $wpdb, $table, $month ) {
	$field = 'pago' === $status ? 'paid_at' : 'due_date';
	return (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM $table WHERE type = %s AND status = %s AND $field LIKE %s", $t, $status, $month . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
};
$in_paid  = $sum( 'in', 'pago' );
$in_open  = $sum( 'in', 'pendente' );
$out_paid = $sum( 'out', 'pago' );
$out_open = $sum( 'out', 'pendente' );
$late     = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM $table WHERE type = 'in' AND status = 'pendente' AND due_date < %s", $today ) ); // phpcs:ignore WordPress.DB.PreparedSQL

// Gráfico: últimos 6 meses (recebido x gasto).
$chart = array();
$max   = 1;
for ( $i = 5; $i >= 0; $i-- ) {
	$m   = gmdate( 'Y-m', strtotime( $month . '-01 -' . $i . ' month' ) );
	$in  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM $table WHERE type = 'in' AND status = 'pago' AND paid_at LIKE %s", $m . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$out = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM $table WHERE type = 'out' AND status = 'pago' AND paid_at LIKE %s", $m . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$chart[ $m ] = array( $in, $out );
	$max         = max( $max, $in, $out );
}

// Gastos por categoria no mês.
$cats  = $wpdb->get_results( $wpdb->prepare( "SELECT category, SUM(amount) AS total FROM $table WHERE type = 'out' AND ( ( status = 'pago' AND paid_at LIKE %s ) OR ( status = 'pendente' AND due_date LIKE %s ) ) GROUP BY category ORDER BY total DESC", $month . '%', $month . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
$ctot  = array_sum( wp_list_pluck( $cats, 'total' ) );

// Lançamentos do mês (e atrasados de antes).
$where = "( ( t.status = 'pago' AND t.paid_at LIKE %s ) OR ( t.status = 'pendente' AND ( t.due_date LIKE %s OR t.due_date < %s ) ) )";
$args  = array( $month . '%', $month . '%', $month . '-01' );
if ( in_array( $type, array( 'in', 'out' ), true ) ) {
	$where .= ' AND t.type = %s';
	$args[] = $type;
}
$rows = $wpdb->get_results( $wpdb->prepare( "SELECT t.*, p.title AS project_title FROM $table t LEFT JOIN " . lk_table( 'projects' ) . " p ON p.id = t.project_id WHERE $where ORDER BY COALESCE(t.paid_at, t.due_date), t.id", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL

$month_name = ucfirst( lk_month_label( $month ) );
$actions    = '<div class="month-nav"><a class="icon-btn" href="' . esc_url( lk_panel_url( 'financeiro', 0, array( 'mes' => $prev ) ) ) . '">‹</a><strong>' . esc_html( $month_name ) . '</strong><a class="icon-btn" href="' . esc_url( lk_panel_url( 'financeiro', 0, array( 'mes' => $next ) ) ) . '">›</a></div>';
$actions   .= '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'extrato' ) ) . '">' . lk_icon( 'financeiro', 16 ) . '<span>Importar extrato</span></a>';
$actions   .= '<button type="button" class="btn btn--primary" data-open="novo-lancamento">' . lk_icon( 'mais', 16 ) . '<span>Lançamento</span></button>';
lk_panel_start( 'Financeiro', 'financeiro', lk_money_button_html() . $actions );
?>

<section class="stats stats--4">
	<div class="stat"><span class="stat-label">Recebido</span><strong class="text-ok"><?php echo esc_html( lk_money( $in_paid ) ); ?></strong><small>A receber: <?php echo esc_html( lk_money( $in_open ) ); ?></small></div>
	<div class="stat"><span class="stat-label">Gasto</span><strong><?php echo esc_html( lk_money( $out_paid ) ); ?></strong><small>A pagar: <?php echo esc_html( lk_money( $out_open ) ); ?></small></div>
	<div class="stat stat--dark"><span class="stat-label">Resultado do mês</span><strong><?php echo esc_html( lk_money( $in_paid - $out_paid ) ); ?></strong><small>Previsto: <?php echo esc_html( lk_money( $in_paid + $in_open - $out_paid - $out_open ) ); ?></small></div>
	<div class="stat<?php echo $late > 0 ? ' stat--warn' : ''; ?>"><span class="stat-label">Em atraso (total)</span><strong><?php echo esc_html( lk_money( $late ) ); ?></strong></div>
</section>

<div class="dash-grid">
	<section class="card">
		<div class="card-head"><h3>Últimos 6 meses</h3><span class="legend"><i class="lg-in"></i>Recebido <i class="lg-out"></i>Gasto</span></div>
		<div class="bars">
			<?php foreach ( $chart as $m => $v ) : ?>
				<div class="bar-col<?php echo $m === $month ? ' is-current' : ''; ?>">
					<div class="bar-pair">
						<span class="bar bar--in" style="height:<?php echo esc_attr( round( $v[0] / $max * 100, 1 ) ); ?>%" title="Recebido: <?php echo esc_attr( lk_money( $v[0] ) ); ?>"></span>
						<span class="bar bar--out" style="height:<?php echo esc_attr( round( $v[1] / $max * 100, 1 ) ); ?>%" title="Gasto: <?php echo esc_attr( lk_money( $v[1] ) ); ?>"></span>
					</div>
					<small><?php echo esc_html( ucfirst( lk_month_name( gmdate( 'n', strtotime( $m . '-01' ) ), true ) ) ); ?></small>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<section class="card">
		<div class="card-head"><h3>Gastos por categoria</h3></div>
		<?php if ( ! $cats ) : ?>
			<p class="muted small">Nenhum gasto neste mês.</p>
		<?php else : ?>
			<ul class="cat-list">
				<?php foreach ( $cats as $c ) : ?>
					<li><span><?php echo esc_html( $c->category ? $c->category : 'Sem categoria' ); ?></span><strong><?php echo esc_html( lk_money( $c->total ) ); ?></strong><div class="progress"><span style="width:<?php echo esc_attr( $ctot ? round( $c->total / $ctot * 100 ) : 0 ); ?>%"></span></div></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
</div>

<section class="card">
	<div class="card-head">
		<h3>Lançamentos</h3>
		<div class="seg seg--sm">
			<a href="<?php echo esc_url( lk_panel_url( 'financeiro', 0, array( 'mes' => $month ) ) ); ?>" class="<?php echo '' === $type ? 'is-active' : ''; ?>">Todos</a>
			<a href="<?php echo esc_url( lk_panel_url( 'financeiro', 0, array( 'mes' => $month, 'tipo' => 'in' ) ) ); ?>" class="<?php echo 'in' === $type ? 'is-active' : ''; ?>">Receitas</a>
			<a href="<?php echo esc_url( lk_panel_url( 'financeiro', 0, array( 'mes' => $month, 'tipo' => 'out' ) ) ); ?>" class="<?php echo 'out' === $type ? 'is-active' : ''; ?>">Saídas</a>
		</div>
	</div>
	<?php if ( ! $rows ) : ?>
		<p class="muted small">Nenhum lançamento neste mês.</p>
	<?php else : ?>
		<div class="table table--fin">
			<div class="table-row table-head"><span>Descrição</span><span>Data</span><span>Valor</span><span>Status</span><span></span></div>
			<?php foreach ( $rows as $r ) : ?>
				<?php $st = lk_trans_status( $r ); ?>
				<div class="table-row">
					<span class="cell-main"><span class="round-ic round-ic--<?php echo esc_attr( $r->type ); ?>"><?php echo 'in' === $r->type ? '↓' : '↑'; ?></span><span><strong><?php echo esc_html( $r->description ); ?><?php if ( ! empty( $r->recur_id ) ) : ?> <em class="recur-tag" title="Conta recorrente">↻</em><?php endif; ?></strong><small><?php echo esc_html( trim( $r->category . ( $r->project_title ? ' · ' . $r->project_title : '' ) . ( $r->method ? ' · ' . $r->method : '' ), ' ·' ) ); ?></small></span></span>
					<span data-label="Data"><?php echo esc_html( lk_date( 'pago' === $r->status ? $r->paid_at : $r->due_date ) ); ?></span>
					<span data-label="Valor" class="<?php echo 'out' === $r->type ? 'text-late' : 'text-ok'; ?>"><strong><?php echo esc_html( ( 'out' === $r->type ? '− ' : '+ ' ) . lk_money( $r->amount ) ); ?></strong></span>
					<span data-label="Status"><?php echo lk_status_badge( $st ); // phpcs:ignore ?></span>
					<span class="row-btns">
						<?php if ( 'in' === $r->type && 'pago' !== $r->status ) : ?><button type="button" class="icon-btn" title="Copiar link de pagamento" data-copy="<?php echo esc_attr( lk_pay_url( $r->id ) ); ?>"><?php echo lk_icon( 'link', 15 ); // phpcs:ignore ?></button><?php $wa_pay = lk_payment_wa_link( $r ); ?><?php if ( $wa_pay ) : ?><a class="icon-btn icon-btn--wa" title="Cobrar no WhatsApp" target="_blank" rel="noopener" href="<?php echo esc_url( $wa_pay ); ?>"><?php echo lk_icon( 'whatsapp', 15 ); // phpcs:ignore ?></a><?php endif; ?><?php endif; ?>
						<?php lk_action_button( 'trans_pay', array( 'id' => $r->id ), 'pago' === $r->status ? 'Desfazer' : ( 'in' === $r->type ? 'Recebido' : 'Pago' ), 'btn btn--ghost btn--sm' ); ?>
						<?php lk_action_button( 'trans_delete', array( 'id' => $r->id ), lk_icon( 'lixo', 15 ), 'icon-btn', 'Excluir este lançamento?' ); ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<?php $nodate = $wpdb->get_results( "SELECT t.*, p.title AS project_title FROM $table t LEFT JOIN " . lk_table( 'projects' ) . " p ON p.id = t.project_id WHERE t.status = 'pendente' AND t.due_date IS NULL ORDER BY t.id DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL ?>
<?php if ( $nodate ) : ?>
<section class="card card--warn" id="sem-data">
	<div class="card-head"><h3>Sem data definida</h3><span class="muted small"><?php echo esc_html( lk_money( array_sum( wp_list_pluck( $nodate, 'amount' ) ) ) ); ?> a combinar</span></div>
	<p class="muted small">Pagamentos "a combinar" e parcelas "na entrega". Defina a data quando combinar com o cliente: aí eles entram no mês certo e nos lembretes de cobrança.</p>
	<div class="table table--fin">
		<?php foreach ( $nodate as $r ) : ?>
			<div class="table-row">
				<span class="cell-main"><span class="round-ic round-ic--<?php echo esc_attr( $r->type ); ?>"><?php echo 'in' === $r->type ? '↓' : '↑'; ?></span><span><strong><?php echo esc_html( $r->description ); ?></strong><small><?php echo esc_html( trim( $r->category . ( $r->project_title ? ' · ' . $r->project_title : '' ), ' ·' ) ); ?></small></span></span>
				<span data-label="Data"><?php lk_form( 'trans_date', 'inline-form' ); ?><input type="hidden" name="id" value="<?php echo (int) $r->id; ?>"><input type="date" name="due_date" required aria-label="Vencimento"><button type="submit" class="btn btn--ghost btn--sm">Definir</button></form></span>
				<span data-label="Valor" class="<?php echo 'out' === $r->type ? 'text-late' : 'text-ok'; ?>"><strong><?php echo esc_html( ( 'out' === $r->type ? '− ' : '+ ' ) . lk_money( $r->amount ) ); ?></strong></span>
				<span data-label="Status"><span class="badge badge--wait">a combinar</span></span>
				<span class="row-btns">
					<?php if ( 'in' === $r->type ) : ?><button type="button" class="icon-btn" title="Copiar link de pagamento" data-copy="<?php echo esc_attr( lk_pay_url( $r->id ) ); ?>"><?php echo lk_icon( 'link', 15 ); // phpcs:ignore ?></button><?php endif; ?>
					<?php lk_action_button( 'trans_pay', array( 'id' => $r->id ), 'in' === $r->type ? 'Recebido' : 'Pago', 'btn btn--ghost btn--sm' ); ?>
				</span>
			</div>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php $recs = lk_rows( 'recurring', '1=1', array(), 'active DESC, type, next_date' ); ?>
<section class="card" id="recorrentes">
	<div class="card-head">
		<h3>Contas recorrentes</h3>
		<span class="muted small">Por mês: <strong class="text-ok">+ <?php echo esc_html( lk_money( lk_recurring_monthly( 'in' ) ) ); ?></strong> · <strong class="text-late">− <?php echo esc_html( lk_money( lk_recurring_monthly( 'out' ) ) ); ?></strong></span>
	</div>
	<?php if ( ! $recs ) : ?>
		<p class="muted small">Nenhuma ainda. Ao lançar uma receita ou saída, ligue a chave "Conta recorrente" (ex.: aluguel, softwares, salário da equipe, contador) e os próximos meses aparecem sozinhos.</p>
	<?php else : ?>
		<div class="table table--recur">
			<div class="table-row table-head"><span>Conta</span><span>Repete</span><span>Próximo</span><span>Valor</span><span></span></div>
			<?php $cycles = lk_recur_cycles(); ?>
			<?php foreach ( $recs as $rc ) : ?>
				<div class="table-row<?php echo $rc->active ? '' : ' is-paused'; ?>">
					<span class="cell-main"><span class="round-ic round-ic--<?php echo esc_attr( $rc->type ); ?>"><?php echo 'in' === $rc->type ? '↓' : '↑'; ?></span><span><strong><?php echo esc_html( $rc->description ); ?></strong><small><?php echo esc_html( trim( ( 'in' === $rc->type ? 'Receita' : 'Saída' ) . ' · ' . $rc->category . ( $rc->until_date ? ' · até ' . lk_date( $rc->until_date ) : '' ), ' ·' ) ); ?><?php echo $rc->active ? '' : ' · pausada'; ?></small></span></span>
					<span data-label="Repete"><?php echo esc_html( isset( $cycles[ $rc->cycle ] ) ? $cycles[ $rc->cycle ] : $rc->cycle ); ?></span>
					<span data-label="Próximo"><?php echo $rc->active && $rc->next_date ? esc_html( lk_date( $rc->next_date ) ) : '—'; ?></span>
					<span data-label="Valor" class="<?php echo 'out' === $rc->type ? 'text-late' : 'text-ok'; ?>"><strong><?php echo esc_html( ( 'out' === $rc->type ? '− ' : '+ ' ) . lk_money( $rc->amount ) ); ?></strong></span>
					<span class="row-btns">
						<button type="button" class="icon-btn" data-open="recur-<?php echo (int) $rc->id; ?>" title="Editar"><?php echo lk_icon( 'editar', 15 ); // phpcs:ignore ?></button>
						<?php lk_action_button( 'recurring_toggle', array( 'id' => $rc->id ), $rc->active ? 'Pausar' : 'Retomar', 'btn btn--ghost btn--sm' ); ?>
						<?php lk_action_button( 'recurring_delete', array( 'id' => $rc->id ), lk_icon( 'lixo', 15 ), 'icon-btn', 'Excluir esta conta recorrente? Os lançamentos futuros ainda pendentes serão apagados; o histórico fica.' ); ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php foreach ( $recs as $rc ) : ?>
			<?php lk_modal_start( 'recur-' . $rc->id, 'Conta recorrente' ); ?>
				<?php lk_form( 'recurring_save', 'stack' ); ?>
					<input type="hidden" name="id" value="<?php echo (int) $rc->id; ?>">
					<?php lk_input( 'description', 'Descrição', $rc->description, 'text', 'required' ); ?>
					<div class="grid-2">
						<?php lk_input( 'amount', 'Valor (R$)', number_format( (float) $rc->amount, 2, ',', '.' ), 'text', 'inputmode="decimal" data-money required' ); ?>
						<?php lk_select( 'category', 'Categoria', array_combine( lk_list( lk_setting( 'in' === $rc->type ? 'categorias_in' : 'categorias_out' ) ), lk_list( lk_setting( 'in' === $rc->type ? 'categorias_in' : 'categorias_out' ) ) ) + ( $rc->category ? array( $rc->category => $rc->category ) : array() ), $rc->category ); ?>
						<?php lk_select( 'cycle', 'Repete', lk_recur_cycles(), $rc->cycle ); ?>
						<?php lk_select( 'method', 'Forma', array( '' => '—' ) + lk_list_options( 'metodos' ), $rc->method ); ?>
						<?php lk_input( 'next_date', 'Próximo vencimento', $rc->next_date, 'date' ); ?>
						<?php lk_input( 'until_date', 'Até (opcional)', $rc->until_date, 'date' ); ?>
					</div>
					<p class="muted small">Os lançamentos futuros ainda pendentes passam a usar o novo valor e a nova descrição.</p>
					<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button></div>
				</form>
			<?php lk_modal_end(); ?>
		<?php endforeach; ?>
	<?php endif; ?>
</section>

<?php
// Contas fixas da agência: atalhos que abrem o lançamento já preenchido como conta recorrente.
$fixas = array(
	array( '🏢 Aluguel', 'Aluguel do escritório', 'Aluguel' ),
	array( '💻 Software', 'Assinatura de software', 'Software e ferramentas' ),
	array( '🎬 Videomaker', 'Videomaker', 'Freelancers (videomaker, designer)' ),
	array( '📊 Contador', 'Contabilidade', 'Contabilidade' ),
	array( '🌐 Internet', 'Internet e telefone', 'Internet e telefone' ),
);
foreach ( lk_team_users() as $u ) {
	if ( lk_is_admin( $u->ID ) ) {
		continue;
	}
	$fixas[] = array( '👤 ' . strtok( $u->display_name, ' ' ), 'Pagamento · ' . $u->display_name, 'Salários e equipe' );
}
?>
<section class="card" id="fixas">
	<div class="card-head"><h3>Contas fixas da agência</h3><span class="muted small">um clique abre o lançamento já como conta recorrente</span></div>
	<div class="fix-row">
		<?php foreach ( $fixas as $fx ) : ?>
			<button type="button" class="chip chip--lg" data-open="novo-lancamento" data-fix-desc="<?php echo esc_attr( $fx[1] ); ?>" data-fix-cat="<?php echo esc_attr( $fx[2] ); ?>"><?php echo esc_html( $fx[0] ); ?></button>
		<?php endforeach; ?>
	</div>
</section>
<script>
document.addEventListener('click', function (e) {
	var b = e.target.closest('[data-fix-desc]'); if (!b) return;
	setTimeout(function () {
		var m = document.getElementById('novo-lancamento'); if (!m) return;
		var out = m.querySelector('input[name=type][value=out]'); if (out) { out.checked = true; out.dispatchEvent(new Event('change', { bubbles: true })); }
		m.querySelector('[name=description]').value = b.dataset.fixDesc;
		var cat = m.querySelector('[name=category]'); Array.prototype.forEach.call(cat.options, function (o) { if (o.value === b.dataset.fixCat || o.text === b.dataset.fixCat) cat.value = o.value; });
		var rc = m.querySelector('[data-recur-toggle]'); if (rc && !rc.checked) { rc.checked = true; rc.dispatchEvent(new Event('change', { bubbles: true })); }
		var st = m.querySelector('[name=status]'); if (st) st.value = 'pendente';
		m.querySelector('[name=amount]').focus();
	}, 60);
});
</script>

<?php lk_modal_start( 'novo-lancamento', 'Novo lançamento' ); ?>
	<?php lk_form( 'trans_save', 'stack' ); ?>
		<div class="seg seg--radio">
			<label><input type="radio" name="type" value="in" checked data-cat-switch="in"><span>Receita</span></label>
			<label><input type="radio" name="type" value="out" data-cat-switch="out"><span>Saída</span></label>
		</div>
		<?php lk_input( 'description', 'Descrição', '', 'text', 'required placeholder="Ex.: Assinatura Elementor Pro"' ); ?>
		<div class="grid-2">
			<?php lk_input( 'amount', 'Valor (R$)', '', 'text', 'inputmode="decimal" data-money required' ); ?>
			<label class="field"><span>Categoria</span>
				<select name="category">
					<optgroup label="Entradas" data-cat="in"><?php foreach ( lk_list( lk_setting( 'categorias_in' ) ) as $c ) : ?><option><?php echo esc_html( $c ); ?></option><?php endforeach; ?></optgroup>
					<optgroup label="Saídas" data-cat="out"><?php foreach ( lk_list( lk_setting( 'categorias_out' ) ) as $c ) : ?><option><?php echo esc_html( $c ); ?></option><?php endforeach; ?></optgroup>
				</select>
			</label>
			<?php lk_input( 'due_date', 'Vencimento', $today, 'date' ); ?>
			<?php lk_select( 'status', 'Status', array( 'pago' => 'Pago', 'pendente' => 'Pendente' ) ); ?>
			<?php lk_select( 'method', 'Forma', array( '' => '—' ) + lk_list_options( 'metodos' ) ); ?>
			<?php lk_select( 'client_id', 'Cliente (opcional)', lk_client_options( 'Nenhum' ) ); ?>
		</div>
		<label class="switch-row"><input type="checkbox" name="recurring" value="1" data-recur-toggle><span class="switch" aria-hidden="true"></span><span><strong>Conta recorrente</strong><small>Repete sozinha (ex.: hospedagem todo mês). Os próximos lançamentos são criados automaticamente.</small></span></label>
		<div class="grid-2 recur-fields" data-recur-fields hidden>
			<?php lk_select( 'cycle', 'Repete', lk_recur_cycles(), 'mensal' ); ?>
			<?php lk_input( 'until_date', 'Até (opcional)', '', 'date' ); ?>
		</div>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Lançar</button></div>
	</form>
<?php lk_modal_end(); ?>

<?php
lk_panel_end();
