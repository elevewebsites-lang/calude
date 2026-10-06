<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$only_mine = ! ap_can( 'tarefas' ) || ap_only_own_tasks();
$filter    = isset( $_GET['f'] ) ? sanitize_key( $_GET['f'] ) : ( $only_mine ? 'minhas' : 'todas' ); // phpcs:ignore WordPress.Security.NonceVerification
$project   = isset( $_GET['projeto'] ) ? absint( $_GET['projeto'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$statuses  = ap_task_statuses();

// Etapas geradas pelos serviços ficam dentro do projeto; aqui entram quando ganham prazo, responsável ou andamento.
$where = "( p.archived IS NULL OR p.archived = 0 ) AND ( t.project_id = 0 OR t.due_date IS NOT NULL OR t.assignee > 0 OR t.status IN ('doing', 'review') )";
$args  = array();
if ( $only_mine || 'minhas' === $filter ) {
	$where .= ' AND t.assignee = %d';
	$args[] = get_current_user_id();
}
if ( 'agencia' === $filter ) {
	$where .= ' AND t.project_id = 0';
}
if ( $project ) {
	$where = 't.project_id = %d';
	$args  = array( $project );
}
// Feitas: só as dos últimos 14 dias, para o quadro não crescer para sempre.
$where .= " AND ( t.status <> 'done' OR t.done_at >= %s )";
$args[] = gmdate( 'Y-m-d', strtotime( ap_today() . ' -14 day' ) );

$tasks  = ap_tasks( $where, $args, "t.position, FIELD(t.priority, 'urgente', 'alta', 'normal', 'baixa'), t.due_date IS NULL, t.due_date, t.id" );
$by_col = array_fill_keys( array_keys( $statuses ), array() );
foreach ( $tasks as $t ) {
	$by_col[ isset( $by_col[ $t->status ] ) ? $t->status : 'todo' ][] = $t;
}

$tabs = array( 'todas' => 'Todas', 'minhas' => 'Minhas', 'agencia' => 'Estúdio' );
$seg  = '';
if ( ! $only_mine ) {
	$seg = '<div class="seg">';
	foreach ( $tabs as $k => $label ) {
		$seg .= '<a href="' . esc_url( ap_panel_url( 'tarefas', 0, array( 'f' => $k ) ) ) . '" class="' . ( $filter === $k && ! $project ? 'is-active' : '' ) . '">' . esc_html( $label ) . '</a>';
	}
	$seg .= '</div>';
}
$actions = $seg . '<button type="button" class="btn btn--primary" data-open="nova-tarefa">' . ap_icon( 'mais', 16 ) . '<span>Tarefa</span></button>';
ap_panel_start( 'Tarefas', 'tarefas', $actions );
?>

<?php if ( $project ) : ?>
	<?php $pp = ap_get( 'projects', $project ); ?>
	<p class="muted">Tarefas do pedido <a href="<?php echo esc_url( ap_panel_url( 'pedido', $project ) ); ?>"><?php echo esc_html( $pp ? $pp->title : '' ); ?></a>. <a href="<?php echo esc_url( ap_panel_url( 'tarefas' ) ); ?>">Ver todas</a></p>
<?php else : ?>
	<p class="muted small hint">Tarefas do estúdio e dos pedidos: produzir pedidos pagos, chamar leads, combinar entregas… As automáticas (pedido pago, pagamento com problema) entram sozinhas.</p>
<?php endif; ?>

<div class="kanban kanban--tasks" data-kanban="task">
	<?php foreach ( $statuses as $slug => $name ) : ?>
		<section class="kcol kcol--<?php echo esc_attr( $slug ); ?>">
			<header class="kcol-head"><h3><?php echo esc_html( $name ); ?></h3><span class="kcount"><?php echo (int) count( $by_col[ $slug ] ); ?></span></header>
			<div class="kcol-body" data-status="<?php echo esc_attr( $slug ); ?>">
				<?php foreach ( $by_col[ $slug ] as $t ) : ?>
					<article class="kcard kcard--task prio-<?php echo esc_attr( $t->priority ); ?>" draggable="true" data-id="<?php echo (int) $t->id; ?>" data-href="<?php echo esc_url( ap_panel_url( 'tarefa', $t->id ) ); ?>">
						<?php if ( $t->project_title ) : ?><span class="kcard-project"><?php echo esc_html( $t->project_title ); ?></span><?php endif; ?>
						<h4><?php echo esc_html( $t->title ); ?></h4>
						<div class="kcard-foot">
							<span class="kcard-badges"><?php echo ap_priority_badge( $t->priority ) . ap_due_badge( $t->due_date, 'done' === $t->status ); // phpcs:ignore ?><?php echo $t->routine_id ? '<span class="badge" title="Criada por uma rotina">' . ap_icon( 'rotinas', 12 ) . '</span>' : ''; // phpcs:ignore ?></span>
							<?php echo ap_avatar( $t->assignee ); // phpcs:ignore ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endforeach; ?>
</div>

<?php
ap_task_modal( 'nova-tarefa', 'Nova tarefa', array( 'project_id' => $project ) );
ap_panel_end();
