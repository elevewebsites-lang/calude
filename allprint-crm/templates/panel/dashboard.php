<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;
$T     = function ( $t ) {
	return ap_table( $t );
};
$today = ap_today();
$month = substr( $today, 0, 7 );
$cols  = ap_columns();
$ready = ap_ready_column();
$last  = ap_last_column();
$first = ap_first_column();
$fin   = ap_can( 'financeiro' );

// Pedidos por etapa.
$orders   = ap_projects( 'p.archived = 0' );
$by_stage = array_fill_keys( array_keys( $cols ), 0 );
$in_prod  = 0;
$ready_list = array();
$late_list  = array();
foreach ( $orders as $o ) {
	if ( isset( $by_stage[ $o->status ] ) ) {
		$by_stage[ $o->status ]++;
	}
	if ( $o->status !== $last && $o->status !== $ready && $o->status !== $first ) {
		$in_prod++;
	}
	if ( $o->status === $ready ) {
		$ready_list[] = $o;
	}
	if ( $o->due_date && $o->due_date < $today && ! in_array( $o->status, array( $ready, $last ), true ) ) {
		$late_list[] = $o;
	}
}
$done_month = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $T( 'projects' ) . ' WHERE delivered_at LIKE %s', $month . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
$clients    = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $T( 'clients' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
$new_cli    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $T( 'clients' ) . ' WHERE created_at LIKE %s', $month . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL

// Dinheiro do mês.
$in_month  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM " . $T( 'transactions' ) . " WHERE type = 'in' AND status = 'pago' AND paid_at LIKE %s", $month . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
$out_month = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM " . $T( 'transactions' ) . " WHERE type = 'out' AND status = 'pago' AND paid_at LIKE %s", $month . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
$to_pay    = ap_rows( 'transactions', "type = 'out' AND status = 'pendente' AND due_date IS NOT NULL AND due_date <= %s", array( gmdate( 'Y-m-d', strtotime( $today . ' +10 day' ) ) ), 'due_date LIMIT 6' );
$to_recv   = (float) $wpdb->get_var( "SELECT SUM(amount) FROM " . $T( 'transactions' ) . " WHERE type = 'in' AND status = 'pendente'" ); // phpcs:ignore WordPress.DB.PreparedSQL
$profit_m  = 0;
foreach ( ap_projects( 'p.delivered_at LIKE %s', array( $month . '%' ) ) as $o ) {
	$c = $o->cost_real > 0 ? $o->cost_real : $o->cost_estimated;
	$profit_m += $o->value - $o->ship_price - $c;
}

$qt   = ap_can( 'orcamentos' ) ? ap_quote_totals() : null;
$low  = ap_module( 'estoque' ) && ap_can( 'estoque' ) ? ap_stock_low() : array( 'filaments' => array(), 'supplies' => array() );
$fil_kg = (float) $wpdb->get_var( 'SELECT SUM(weight_g) FROM ' . $T( 'filaments' ) . ' WHERE active = 1' ) / 1000; // phpcs:ignore WordPress.DB.PreparedSQL

// Tarefas de hoje e atrasadas.
$where = "t.status <> 'done' AND t.due_date IS NOT NULL AND t.due_date <= %s";
$args  = array( $today );
if ( ! ap_is_admin() ) {
	$where .= ' AND t.assignee = %d';
	$args[] = get_current_user_id();
}
$tasks = ap_tasks( $where, $args, "t.due_date, FIELD(t.priority, 'urgente', 'alta', 'normal', 'baixa'), t.id" );
$follow = ap_can( 'leads' ) ? ap_leads( "l.next_at IS NOT NULL AND DATE(l.next_at) <= %s AND l.stage NOT IN (%s, %s)", array( $today, ap_funnel_won(), ap_funnel_lost() ) ) : array();
$printers = ap_module( 'estoque' ) && ap_can( 'estoque' ) ? array_filter( ap_rows( 'printers', 'active = 1' ), 'ap_printer_maint_due' ) : array();

$hour  = (int) current_time( 'G' );
$hello = $hour < 12 ? 'Bom dia' : ( $hour < 18 ? 'Boa tarde' : 'Boa noite' );
$name  = wp_get_current_user()->first_name ? wp_get_current_user()->first_name : wp_get_current_user()->display_name;

$actions  = ap_money_button_html();
$actions .= ap_can( 'orcamentos' ) ? ( ap_module( 'estoque' ) ? '<a class="btn btn--ghost" href="' . esc_url( ap_panel_url( 'calculadora' ) ) . '">' . ap_icon( 'calculadora', 16 ) . '<span>Calcular</span></a>' : '' ) . '<a class="btn btn--primary" href="' . esc_url( ap_panel_url( 'orcamento' ) ) . '">' . ap_icon( 'mais', 16 ) . '<span>Orçamento</span></a>' : '';
ap_panel_start( 'Dashboard', '', $actions );
?>
<div class="hello">
	<h2><?php echo esc_html( $hello . ( $name ? ', ' . $name : '' ) ); ?>.</h2>
	<p class="muted"><?php echo esc_html( ucfirst( ap_date_long() ) ); ?> · <?php echo (int) $in_prod; ?> pedido(s) em produção<?php echo $ready_list ? ' · ' . count( $ready_list ) . ' pronto(s) esperando o cliente' : ''; ?>.</p>
</div>

<?php if ( ap_is_admin() && ( $hb = ap_health_bad_count() ) ) : // phpcs:ignore ?>
	<a class="alert-bar" href="<?php echo esc_url( ap_panel_url( 'saude' ) ); ?>"><?php echo ap_icon( 'pulso', 16 ); // phpcs:ignore ?> <?php echo (int) $hb; ?> item(ns) precisam de atenção na Saúde do sistema.</a>
<?php endif; ?>

<section class="stats stats--4">
	<a class="stat" href="<?php echo esc_url( ap_panel_url( 'pedidos' ) ); ?>"><span class="stat-label">A fazer</span><strong><?php echo (int) ( $by_stage[ $first ] + $in_prod ); ?></strong><small><?php echo (int) $by_stage[ $first ]; ?> aguardando pagamento · <?php echo (int) $in_prod; ?> em produção</small></a>
	<a class="stat<?php echo $ready_list ? ' stat--warn' : ''; ?>" href="<?php echo esc_url( ap_panel_url( 'pedidos' ) ); ?>"><span class="stat-label">Prontos</span><strong><?php echo count( $ready_list ); ?></strong><small>esperando retirada/envio</small></a>
	<div class="stat"><span class="stat-label">Feitos no mês</span><strong><?php echo (int) $done_month; ?></strong><small>pedidos entregues</small></div>
	<a class="stat" href="<?php echo esc_url( ap_panel_url( 'clientes' ) ); ?>"><span class="stat-label">Clientes</span><strong><?php echo (int) $clients; ?></strong><small>+<?php echo (int) $new_cli; ?> este mês</small></a>
</section>

<?php if ( $fin ) : ?>
	<section class="stats stats--4">
		<div class="stat stat--dark"><span class="stat-label">Entrou no mês</span><strong class="money"><?php echo esc_html( ap_money( $in_month ) ); ?></strong><small>recebido</small></div>
		<div class="stat"><span class="stat-label">Saiu no mês</span><strong class="money"><?php echo esc_html( ap_money( $out_month ) ); ?></strong><small>material, contas…</small></div>
		<div class="stat"><span class="stat-label">Lucro dos pedidos</span><strong class="money <?php echo $profit_m < 0 ? 'text-late' : ''; ?>"><?php echo esc_html( ap_money( $profit_m ) ); ?></strong><small>entregues no mês (valor − custo)</small></div>
		<div class="stat"><span class="stat-label">A receber</span><strong class="money"><?php echo esc_html( ap_money( $to_recv ) ); ?></strong><small>pendente</small></div>
	</section>
<?php endif; ?>

<div class="dash-grid">
	<section class="card">
		<div class="card-head"><h3>Produção agora</h3><a class="small" href="<?php echo esc_url( ap_panel_url( 'pedidos' ) ); ?>">abrir quadro</a></div>
		<div class="stage-bars">
			<?php $max = max( 1, max( $by_stage ) ); foreach ( $cols as $slug => $label ) : ?>
				<div class="stage-bar"><span><?php echo esc_html( $label ); ?></span><div><i style="width:<?php echo (int) round( $by_stage[ $slug ] / $max * 100 ); ?>%"></i></div><b><?php echo (int) $by_stage[ $slug ]; ?></b></div>
			<?php endforeach; ?>
		</div>
		<?php if ( $late_list ) : ?>
			<h4 class="sub text-late">Atrasados</h4>
			<ul class="mini-list">
				<?php foreach ( array_slice( $late_list, 0, 5 ) as $o ) : ?><li><a href="<?php echo esc_url( ap_panel_url( 'pedido', $o->id ) ); ?>"><strong>#<?php echo (int) $o->id; ?> <?php echo esc_html( $o->title ); ?></strong><small><?php echo esc_html( ap_project_client_label( $o ) . ' · prazo ' . ap_date( $o->due_date ) ); ?></small></a></li><?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>

	<section class="card">
		<div class="card-head"><h3>Prontos para entregar</h3></div>
		<?php if ( ! $ready_list ) : ?>
			<p class="muted small">Nenhum pedido esperando o cliente.</p>
		<?php else : ?>
			<ul class="mini-list">
				<?php foreach ( $ready_list as $o ) : ?>
					<?php $wa = ap_order_wa_link( $o ); ?>
					<li><a href="<?php echo esc_url( ap_panel_url( 'pedido', $o->id ) ); ?>"><strong>#<?php echo (int) $o->id; ?> <?php echo esc_html( $o->title ); ?></strong><small><?php echo esc_html( ap_project_client_label( $o ) . ' · ' . ( 'envio' === $o->delivery_mode ? 'envio' : 'retirada' ) . ( $o->ready_at ? ' · pronto ' . ap_ago( $o->ready_at ) : '' ) ); ?></small></a><?php if ( $wa ) : ?><a class="btn btn--wa btn--sm" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo ap_icon( 'whatsapp', 14 ); // phpcs:ignore ?></a><?php endif; ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>

	<section class="card">
		<div class="card-head"><h3>Pendências de hoje</h3><a class="small" href="<?php echo esc_url( ap_panel_url( 'tarefas' ) ); ?>">tarefas</a></div>
		<?php if ( ! $tasks && ! $follow ) : ?>
			<p class="muted small">Tudo em dia. ✨</p>
		<?php else : ?>
			<ul class="mini-list">
				<?php foreach ( array_slice( $tasks, 0, 8 ) as $t ) : ?>
					<li><a href="<?php echo esc_url( ap_panel_url( 'tarefa', $t->id ) ); ?>"><strong><?php echo esc_html( $t->title ); ?></strong><small><?php echo esc_html( ( $t->project_title ? $t->project_title . ' · ' : '' ) ); ?><?php echo ap_due_badge( $t->due_date ); // phpcs:ignore ?></small></a></li>
				<?php endforeach; ?>
				<?php foreach ( array_slice( $follow, 0, 5 ) as $l ) : ?>
					<li><a href="<?php echo esc_url( ap_panel_url( 'lead', $l->id ) ); ?>"><strong>Chamar <?php echo esc_html( ap_lead_label( $l ) ); ?></strong><small><?php echo esc_html( $l->next_action ? $l->next_action : 'retorno do funil' ); ?></small></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>

	<?php if ( $qt ) : ?>
		<section class="card">
			<div class="card-head"><h3>Orçamentos</h3><a class="small" href="<?php echo esc_url( ap_panel_url( 'orcamentos' ) ); ?>">ver todos</a></div>
			<div class="kv">
				<span>Em aberto</span><strong><?php echo (int) $qt['open']; ?> · <span class="money"><?php echo esc_html( ap_money( $qt['open_value'] ) ); ?></span></strong>
				<span>Aprovados</span><strong><?php echo (int) $qt['won']; ?> · <span class="money"><?php echo esc_html( ap_money( $qt['won_value'] ) ); ?></span></strong>
			</div>
			<ul class="mini-list">
				<?php foreach ( ap_rows( 'quotes', "status IN ('enviado','visto')", array(), 'id DESC LIMIT 5' ) as $q ) : ?>
					<li><a href="<?php echo esc_url( ap_panel_url( 'orcamento', $q->id ) ); ?>"><strong><?php echo esc_html( $q->title ); ?></strong><small><?php echo esc_html( ( 'visto' === $q->status ? 'visualizado ' . ap_ago( $q->last_view ) : 'enviado, ainda não aberto' ) ); ?> · <span class="money"><?php echo esc_html( ap_money( $q->total ) ); ?></span></small></a></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( ap_module( 'estoque' ) && ap_can( 'estoque' ) ) : ?>
		<section class="card">
			<div class="card-head"><h3>Estoque</h3><a class="small" href="<?php echo esc_url( ap_panel_url( 'filamentos' ) ); ?>"><?php echo esc_html( number_format( $fil_kg, 2, ',', '.' ) ); ?> kg de filamento</a></div>
			<?php if ( ! $low['filaments'] && ! $low['supplies'] && ! $printers ) : ?>
				<p class="muted small">Filamentos e insumos acima do mínimo.</p>
			<?php else : ?>
				<ul class="mini-list">
					<?php foreach ( $low['filaments'] as $f ) : ?><li><a href="<?php echo esc_url( ap_panel_url( 'filamentos' ) ); ?>"><span class="swatch swatch--sm" style="background:<?php echo esc_attr( $f->color_hex ); ?>"></span><strong><?php echo esc_html( ap_filament_label( $f ) ); ?></strong><small class="text-late">restam <?php echo (int) round( $f->weight_g ); ?> g</small></a></li><?php endforeach; ?>
					<?php foreach ( $low['supplies'] as $s ) : ?><li><a href="<?php echo esc_url( ap_panel_url( 'insumos' ) ); ?>"><strong><?php echo esc_html( $s->name ); ?></strong><small class="text-late">restam <?php echo esc_html( ap_qty_label( $s->qty, $s->unit ) ); ?></small></a></li><?php endforeach; ?>
					<?php foreach ( $printers as $p ) : ?><li><a href="<?php echo esc_url( ap_panel_url( 'impressoras' ) ); ?>"><strong>Manutenção: <?php echo esc_html( $p->name ); ?></strong><small><?php echo esc_html( number_format( $p->hours_used, 0, ',', '.' ) ); ?> h impressas</small></a></li><?php endforeach; ?>
				</ul>
				<a class="small" href="<?php echo esc_url( ap_panel_url( 'compras' ) ); ?>">lista de compras →</a>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( $fin && $to_pay ) : ?>
		<section class="card">
			<div class="card-head"><h3>Contas a pagar</h3><a class="small" href="<?php echo esc_url( ap_panel_url( 'financeiro' ) ); ?>">financeiro</a></div>
			<ul class="mini-list">
				<?php foreach ( $to_pay as $t ) : ?><li><a href="<?php echo esc_url( ap_panel_url( 'financeiro' ) ); ?>"><strong><?php echo esc_html( $t->description ); ?></strong><small><?php echo ap_due_badge( $t->due_date ); // phpcs:ignore ?> · <span class="money"><?php echo esc_html( ap_money( $t->amount ) ); ?></span></small></a></li><?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</div>
<?php
ap_panel_end();
