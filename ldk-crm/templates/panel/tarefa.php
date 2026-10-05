<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$t = lk_get( 'tasks', $id );
if ( ! $t ) {
	lk_render( 'panel/404' );
}
$project  = $t->project_id ? lk_get( 'projects', $t->project_id ) : null;
$comments = lk_rows( 'activity', 'task_id = %d', array( $t->id ), 'id' );
$groups   = array();
if ( $project ) {
	global $wpdb;
	$groups = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT grp FROM ' . lk_table( 'tasks' ) . " WHERE project_id = %d AND grp <> ''", $project->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}
$back = $project ? lk_panel_url( 'pedido', $project->id ) : lk_panel_url( 'tarefas' );

lk_panel_start( 'Tarefa', 'tarefas', 'done' !== $t->status ? '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'foco', 0, array( 'itens' => array( 't' . $t->id ) ) ) ) . '">' . lk_icon( 'alvo', 16 ) . '<span>Focar nesta tarefa</span></a>' : '' );
?>

<a class="back" href="<?php echo esc_url( $back ); ?>"><?php echo lk_icon( 'voltar', 16 ); // phpcs:ignore ?> <?php echo esc_html( $project ? $project->title : 'Tarefas' ); ?></a>

<div class="detail-grid">
	<div class="detail-main">
		<section class="card task-view">
			<div class="task-title">
				<button type="button" class="tick tick--lg<?php echo 'done' === $t->status ? ' is-done' : ''; ?>" data-toggle-task="<?php echo (int) $t->id; ?>" data-reload aria-label="Concluir"></button>
				<div>
					<?php if ( $t->grp ) : ?><span class="eyebrow"><?php echo esc_html( $t->grp ); ?></span><?php endif; ?>
					<h2><?php echo esc_html( $t->title ); ?></h2>
					<div class="task-badges">
						<?php echo lk_priority_badge( $t->priority ) . lk_due_badge( $t->due_date, 'done' === $t->status ); // phpcs:ignore ?>
						<?php if ( $t->needs_approval ) : ?><?php echo $t->approved_at ? '<span class="badge badge--ok">Aprovado pelo cliente em ' . esc_html( lk_date( $t->approved_at, 'd/m H:i' ) ) . '</span>' : '<span class="badge badge--wait">Aguardando aprovação do cliente</span>'; ?><?php endif; ?>
						<?php if ( $t->client_visible ) : ?><span class="badge">Visível ao cliente</span><?php endif; ?>
					</div>
				</div>
			</div>
			<?php if ( $t->description ) : ?>
				<div class="task-desc"><?php echo wp_kses_post( wpautop( make_clickable( esc_html( $t->description ) ) ) ); ?></div>
			<?php endif; ?>
		</section>

		<section class="card">
			<div class="card-head"><h3>Comentários</h3></div>
			<ul class="timeline">
				<?php foreach ( $comments as $a ) : ?>
					<?php $au = $a->user_id ? get_userdata( $a->user_id ) : null; ?>
					<li class="is-comment"><span class="dot"></span><div><p><?php echo nl2br( esc_html( $a->body ) ); // phpcs:ignore ?></p><small><?php echo esc_html( ( $au ? $au->display_name . ' · ' : '' ) . lk_ago( $a->created_at ) ); ?></small></div></li>
				<?php endforeach; ?>
			</ul>
			<?php lk_form( 'comment_add', 'comment-box' ); ?>
				<input type="hidden" name="task_id" value="<?php echo (int) $t->id; ?>">
				<input type="hidden" name="project_id" value="<?php echo (int) $t->project_id; ?>">
				<textarea name="body" rows="2" placeholder="Escreva um comentário…" required></textarea>
				<div class="form-actions"><button type="submit" class="btn btn--primary btn--sm">Comentar</button></div>
			</form>
		</section>
	</div>

	<aside class="detail-side">
		<section class="card">
			<div class="card-head"><h3>Editar</h3></div>
			<?php lk_form( 'task_save', 'stack' ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $t->id; ?>">
				<?php lk_input( 'title', 'Título', $t->title, 'text', 'required' ); ?>
				<?php lk_select( 'status', 'Status', lk_task_statuses(), $t->status ); ?>
				<?php lk_select( 'project_id', 'Pedido', lk_project_options(), $t->project_id ); ?>
				<label class="field"><span>Grupo / etapa</span><input type="text" name="grp" value="<?php echo esc_attr( $t->grp ); ?>" list="task-grps"><datalist id="task-grps"><?php foreach ( $groups as $g ) : ?><option value="<?php echo esc_attr( $g ); ?>"><?php endforeach; ?></datalist></label>
				<div class="grid-2">
					<?php lk_input( 'due_date', 'Prazo', $t->due_date, 'date' ); ?>
					<?php lk_select( 'priority', 'Urgência', lk_priorities(), $t->priority ); ?>
				</div>
				<?php lk_select( 'assignee', 'Responsável', lk_team_options(), $t->assignee ); ?>
				<?php lk_input( 'description', 'Detalhes', $t->description, 'textarea', 'rows="5"' ); ?>
				<div class="checks">
					<?php lk_check( 'client_visible', 'O cliente vê', $t->client_visible ); ?>
					<?php lk_check( 'needs_approval', 'Precisa de aprovação', $t->needs_approval ); ?>
				</div>
				<div class="form-actions"><button type="submit" class="btn btn--primary">Salvar</button></div>
			</form>
		</section>
		<div class="side-actions">
			<?php lk_action_button( 'task_delete', array( 'id' => $t->id ), 'Excluir tarefa', 'btn btn--danger btn--sm', 'Excluir esta tarefa?' ); ?>
		</div>
	</aside>
</div>

<?php
lk_panel_end();
