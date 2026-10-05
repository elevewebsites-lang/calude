<?php
/**
 * Modo foco: escolher o que fazer. Com ?itens[]=… abre a tela de foco.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.NonceVerification -- formulário GET, só leitura.
if ( ! empty( $_GET['itens'] ) ) {
	$items  = lk_focus_items( wp_unslash( (array) $_GET['itens'] ) );
	$cycles = lk_focus_cycles();
	$cycle  = isset( $_GET['ciclo'] ) && isset( $cycles[ sanitize_key( $_GET['ciclo'] ) ] ) ? sanitize_key( $_GET['ciclo'] ) : '25-5';
	$goal   = isset( $_GET['meta'] ) ? sanitize_text_field( wp_unslash( $_GET['meta'] ) ) : '';
	if ( $items ) {
		lk_render( 'panel/foco-sessao', compact( 'items', 'cycle', 'goal' ) );
	}
}
// phpcs:enable

$tasks    = lk_focus_task_options();
$projects = lk_focus_project_options();
// Foco por cliente: só os posts dele, na ordem do prazo mais urgente (depois pela data da publicação).
$fcli = isset( $_GET['cliente'] ) ? absint( $_GET['cliente'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
if ( $fcli ) {
	$projects = array_values( array_filter( $projects, function ( $p ) use ( $fcli ) { return (int) $p->client_id === $fcli; } ) );
}
usort(
	$projects,
	function ( $a, $b ) {
		$x = ( function_exists( 'lk_post_deadline' ) && lk_post_deadline( $a ) ) ? lk_post_deadline( $a ) : ( $a->scheduled_at ? $a->scheduled_at : '9999' );
		$y = ( function_exists( 'lk_post_deadline' ) && lk_post_deadline( $b ) ) ? lk_post_deadline( $b ) : ( $b->scheduled_at ? $b->scheduled_at : '9999' );
		return strcmp( $x, $y );
	}
);
$fclients = array();
foreach ( lk_focus_project_options() as $p ) {
	$fclients[ (int) $p->client_id ] = lk_post_client_label( $p );
}
$today    = lk_today();
$week     = gmdate( 'Y-m-d', strtotime( $today . ' -' . ( (int) gmdate( 'N', strtotime( $today ) ) - 1 ) . ' day' ) );
$sec_day  = lk_focus_seconds_since( $today );
$sec_week = lk_focus_seconds_since( $week );

global $wpdb;
$top = $wpdb->get_results( $wpdb->prepare( 'SELECT f.project_id, SUM(f.seconds) AS s, p.title FROM ' . lk_table( 'focus' ) . ' f LEFT JOIN ' . lk_table( 'projects' ) . ' p ON p.id = f.project_id WHERE f.user_id = %d AND f.created_at >= %s AND f.project_id > 0 GROUP BY f.project_id ORDER BY s DESC LIMIT 5', get_current_user_id(), $week . ' 00:00:00' ) ); // phpcs:ignore WordPress.DB.PreparedSQL

$group_of = function ( $t ) use ( $today ) {
	if ( ! $t->due_date ) {
		return 'Sem prazo';
	}
	if ( $t->due_date < $today ) {
		return 'Atrasadas';
	}
	return $t->due_date === $today ? 'Para hoje' : 'Próximas';
};
$grouped = array( 'Atrasadas' => array(), 'Para hoje' => array(), 'Próximas' => array(), 'Sem prazo' => array() );
foreach ( $tasks as $t ) {
	$grouped[ $group_of( $t ) ][] = $t;
}
$pre = isset( $_GET['com'] ) ? sanitize_key( wp_unslash( $_GET['com'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

lk_panel_start( 'Modo foco', 'foco' );
?>

<section class="stats stats--4 focus-stats">
	<div class="stat stat--dark"><span class="stat-label">Focado hoje</span><strong><?php echo esc_html( lk_focus_duration( $sec_day ) ); ?></strong><small><?php echo (int) floor( $sec_day / 1500 ); ?> blocos de 25 min</small></div>
	<div class="stat"><span class="stat-label">Nesta semana</span><strong><?php echo esc_html( lk_focus_duration( $sec_week ) ); ?></strong></div>
	<div class="stat focus-top">
		<span class="stat-label">Onde foi o seu foco na semana</span>
		<?php if ( $top ) : ?>
			<ul><?php foreach ( $top as $row ) : ?><li><span><?php echo esc_html( $row->title ? $row->title : 'Projeto' ); ?></span><strong><?php echo esc_html( lk_focus_duration( $row->s ) ); ?></strong></li><?php endforeach; ?></ul>
		<?php else : ?>
			<small>Ainda nada registrado. O tempo aparece aqui depois do primeiro bloco.</small>
		<?php endif; ?>
	</div>
</section>

<?php if ( $fclients ) : ?>
<form method="get" class="focus-client card">
	<label class="field"><span>Focar em um cliente</span>
		<select name="cliente" onchange="this.form.submit()"><option value="">Todos os clientes</option><?php foreach ( $fclients as $cid => $cl ) : ?><option value="<?php echo (int) $cid; ?>"<?php selected( $fcli, $cid ); ?>><?php echo esc_html( $cl ); ?></option><?php endforeach; ?></select>
	</label>
	<p class="muted small">Os posts aparecem na ordem de urgência (prazo da etapa, depois o dia da publicação).</p>
</form>
<?php endif; ?>

<?php lk_form( 'focus_start', 'focus-pick' ); ?>
<div class="wizard" data-focus-pick>
	<section class="card step">
		<div class="step-head"><span class="step-n">01</span><div><h3>O que você vai fazer agora?</h3><p class="muted small">Escolha uma ou mais tarefas ou posts. Menos é mais: o ideal é de 1 a 3.</p></div></div>
		<input type="search" class="focus-filter" placeholder="Filtrar por nome…" data-focus-filter>
		<div class="focus-cols">
			<div>
				<h4 class="focus-h">Tarefas</h4>
				<?php if ( ! $tasks ) : ?><p class="muted small">Nenhuma tarefa aberta.</p><?php endif; ?>
				<?php foreach ( $grouped as $label => $list ) : ?>
					<?php if ( ! $list ) { continue; } ?>
					<p class="focus-group<?php echo 'Atrasadas' === $label ? ' is-late' : ''; ?>"><?php echo esc_html( $label ); ?> <em><?php echo (int) count( $list ); ?></em></p>
					<?php foreach ( $list as $t ) : ?>
						<label class="focus-opt" data-focus-item>
							<input type="checkbox" name="itens[]" value="t<?php echo (int) $t->id; ?>"<?php checked( 't' . $t->id, $pre ); ?>>
							<span><strong><?php echo esc_html( $t->title ); ?></strong><small><?php echo esc_html( trim( ( $t->project_title ? $t->project_title : 'Tarefa avulsa' ) . ( $t->due_date ? ' · ' . lk_date( $t->due_date, 'd/m' ) : '' ) ) ); ?></small></span>
							<?php echo lk_priority_badge( $t->priority ); // phpcs:ignore ?>
						</label>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
			<?php if ( $projects ) : ?>
				<div>
					<h4 class="focus-h">Posts</h4>
					<p class="focus-group"><?php echo $fcli ? esc_html( $fclients[ $fcli ] ?? 'Cliente' ) : 'Com você agora'; ?> <em><?php echo (int) count( $projects ); ?></em><?php if ( $fcli && $projects ) : ?> <button type="button" class="btn btn--link btn--sm" data-focus-all>marcar todos</button><?php endif; ?></p>
					<?php foreach ( $projects as $i => $p ) : ?>
						<label class="focus-opt" data-focus-item>
							<input type="checkbox" name="itens[]" value="p<?php echo (int) $p->id; ?>"<?php checked( 'p' . $p->id, $pre ); ?>>
							<span><strong><?php echo esc_html( ( $i + 1 ) . '. ' . $p->title ); ?></strong><small><?php echo esc_html( lk_post_client_label( $p ) . ' · ' . ( lk_stages()[ $p->stage ] ?? '' ) . ( $p->scheduled_at ? ' · publica ' . lk_date( $p->scheduled_at, 'd/m' ) : '' ) ); ?> <?php echo function_exists( 'lk_deadline_badge' ) ? lk_deadline_badge( $p ) : ''; // phpcs:ignore ?></small></span>
						</label>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<div class="focus-new">
			<?php lk_input( 'novas', 'Coisas rápidas que não estão no painel (uma por linha, viram tarefas de hoje)', '', 'textarea', 'rows="3" placeholder="Responder os e-mails&#10;Ligar para a Clínica Sorriso" data-focus-new' ); ?>
		</div>
	</section>

	<section class="card step">
		<div class="step-head"><span class="step-n">02</span><div><h3>Como vai ser a sessão</h3></div></div>
		<div class="grid-2">
			<?php lk_select( 'ciclo', 'Ritmo', array_map( function ( $c ) { return $c[0]; }, lk_focus_cycles() ), '25-5' ); ?>
			<?php lk_input( 'meta', 'Objetivo da sessão (opcional)', '', 'text', 'placeholder="Ex.: terminar o design da home" maxlength="120"' ); ?>
		</div>
	</section>

	<div class="form-actions form-actions--sticky">
		<span class="muted small" data-focus-count>Nada escolhido</span>
		<button type="submit" class="btn btn--accent" data-focus-go disabled><?php echo lk_icon( 'alvo', 16 ); // phpcs:ignore ?> Entrar em foco</button>
	</div>
</div>
</form>
<script>
document.addEventListener('click', function (e) {
	var b = e.target.closest('[data-focus-all]'); if (!b) return;
	b.closest('div').querySelectorAll('input[name="itens[]"]').forEach(function (c) { if (!c.checked) { c.checked = true; c.dispatchEvent(new Event('change', { bubbles: true })); } });
});
</script>

<?php
lk_panel_end();
