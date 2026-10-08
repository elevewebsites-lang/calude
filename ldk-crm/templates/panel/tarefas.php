<?php
/**
 * Tarefas: tudo num lugar, em colunas por prazo (Atrasadas · Hoje · Amanhã · Esta semana · Pendentes).
 * Junta tarefas, posts que estão com você e alterações pedidas pelos clientes.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( isset( $_GET['v'] ) && 'status' === $_GET['v'] ) { // phpcs:ignore WordPress.Security.NonceVerification
	include __DIR__ . '/tarefas-status.php';
	return;
}
$only_mine = ! lk_can( 'tarefas' ) || lk_only_own_tasks();
$who       = isset( $_GET['quem'] ) ? sanitize_text_field( wp_unslash( $_GET['quem'] ) ) : 'minhas'; // phpcs:ignore WordPress.Security.NonceVerification
$client_id = isset( $_GET['cliente'] ) ? absint( $_GET['cliente'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$kind      = isset( $_GET['tipo'] ) && in_array( $_GET['tipo'], array( 'task', 'post', 'alter' ), true ) ? sanitize_key( $_GET['tipo'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$only_col  = isset( $_GET['col'] ) && isset( lk_task_cols()[ $_GET['col'] ] ) ? sanitize_key( $_GET['col'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$show_done = isset( $_GET['feitas'] ); // phpcs:ignore WordPress.Security.NonceVerification
$uid       = 'minhas' === $who || $only_mine ? get_current_user_id() : ( 'todas' === $who ? 0 : absint( $who ) );
$items     = lk_task_board( array( 'user_id' => $uid, 'client_id' => $client_id, 'kind' => $kind ) );
$done      = $show_done ? lk_task_board( array( 'user_id' => $uid, 'client_id' => $client_id, 'kind' => 'task', 'done' => true ) ) : array();
$cols      = lk_task_cols();
$by        = array_fill_keys( array_keys( $cols ), array() );
foreach ( $items as $i ) {
	$by[ $i['col'] ][] = $i;
}
$here = function ( $extra = array() ) use ( $who, $client_id, $kind, $only_col, $show_done ) {
	$a = array_filter( array( 'quem' => $who, 'cliente' => $client_id, 'tipo' => $kind, 'col' => $only_col, 'feitas' => $show_done ? 1 : 0 ) );
	return lk_panel_url( 'tarefas', 0, array_merge( $a, $extra ) );
};
$actions = '<button type="button" class="btn btn--primary" data-open="nova-tarefa">' . lk_icon( 'mais', 16 ) . '<span>Tarefa</span></button>';
lk_panel_start( 'Tarefas', 'tarefas', $actions );
?>
<form method="get" class="tk-filters" action="<?php echo esc_url( lk_panel_url( 'tarefas' ) ); ?>">
	<?php if ( ! $only_mine ) : ?>
		<label class="field field--inline"><span>Quem</span>
			<select name="quem" onchange="this.form.submit()">
				<option value="minhas"<?php selected( $who, 'minhas' ); ?>>Minhas</option>
				<option value="todas"<?php selected( $who, 'todas' ); ?>>Todas</option>
				<?php foreach ( lk_team_users() as $tu ) : ?><option value="<?php echo (int) $tu->ID; ?>"<?php selected( $who, (string) $tu->ID ); ?>><?php echo esc_html( $tu->display_name ); ?></option><?php endforeach; ?>
			</select></label>
	<?php endif; ?>
	<label class="field field--inline"><span>Cliente</span>
		<select name="cliente" onchange="this.form.submit()">
			<option value="">Todos os clientes</option>
			<?php foreach ( lk_clients() as $c ) : ?><option value="<?php echo (int) $c->id; ?>"<?php selected( $client_id, (int) $c->id ); ?>><?php echo esc_html( lk_client_label( $c ) ); ?></option><?php endforeach; ?>
		</select></label>
	<label class="field field--inline"><span>Tipo</span>
		<select name="tipo" onchange="this.form.submit()">
			<option value="">Tudo</option>
			<option value="task"<?php selected( $kind, 'task' ); ?>>Tarefas</option>
			<option value="post"<?php selected( $kind, 'post' ); ?>>Posts com você</option>
			<option value="alter"<?php selected( $kind, 'alter' ); ?>>Alterações</option>
		</select></label>
	<label class="chk"><input type="checkbox" name="feitas" value="1" onchange="this.form.submit()"<?php checked( $show_done ); ?>> Mostrar feitas (7 dias)</label>
	<?php if ( $only_col ) : ?><input type="hidden" name="col" value="<?php echo esc_attr( $only_col ); ?>"><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $here( array( 'col' => '' ) ) ); ?>">Ver todas as colunas</a><?php endif; ?>
	<a class="btn btn--link btn--sm" href="<?php echo esc_url( lk_panel_url( 'tarefas', 0, array( 'v' => 'status' ) ) ); ?>">Quadro por status</a>
</form>

<div class="tk-board<?php echo $only_col ? ' tk-board--one' : ''; ?>" data-tk-board data-endpoint="<?php echo esc_url( rest_url( 'lk/v1/task-board' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
	<?php foreach ( $cols as $slug => $name ) : ?>
		<?php if ( $only_col && $only_col !== $slug ) { continue; } ?>
		<section class="tk-col tk-col--<?php echo esc_attr( $slug ); ?>">
			<header class="tk-head"><h3><?php echo esc_html( $name ); ?></h3><span class="kcount"><?php echo (int) count( $by[ $slug ] ); ?></span></header>
			<div class="tk-body" data-col="<?php echo esc_attr( $slug ); ?>"<?php echo 'atrasadas' === $slug ? ' data-nodrop="1"' : ''; ?>>
				<?php foreach ( $by[ $slug ] as $i ) : echo lk_task_card_html( $i ); endforeach; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php if ( ! $by[ $slug ] ) : ?><p class="tk-empty muted small"><?php echo 'atrasadas' === $slug ? 'Nada atrasado 🎉' : 'Nada por aqui'; ?></p><?php endif; ?>
			</div>
		</section>
	<?php endforeach; ?>
	<?php if ( $show_done && ! $only_col ) : ?>
		<section class="tk-col tk-col--feitas"><header class="tk-head"><h3>Feitas</h3><span class="kcount"><?php echo (int) count( $done ); ?></span></header>
			<div class="tk-body" data-nodrop="1"><?php foreach ( $done as $i ) : echo lk_task_card_html( $i ); endforeach; // phpcs:ignore WordPress.Security.EscapeOutput ?></div></section>
	<?php endif; ?>
</div>
<p class="muted small">Arraste uma <strong>tarefa</strong> para outra coluna para mudar o prazo. Posts e alterações seguem o fluxo e saem sozinhos quando o passo é concluído.</p>
<script src="<?php echo esc_url( LK_URL . 'assets/tarefas.js?ver=' . LK_VERSION ); ?>"></script>
<?php
lk_task_modal( 'nova-tarefa', 'Nova tarefa', array( 'client_id' => $client_id ) );
lk_panel_end();
