<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$load  = lk_team_load();
$sel   = isset( $_GET['pessoa'] ) ? absint( $_GET['pessoa'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$roles = array( 'designer_id' => 'Designer', 'social_id' => 'Social media', 'atendimento_id' => 'Atendimento' );
lk_panel_start( 'Equipe e demandas', 'time', lk_is_admin() ? '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'equipe' ) ) . '">' . lk_icon( 'equipe', 16 ) . '<span>Pessoas e permissões</span></a>' : '' );
?>
<p class="muted small hint">Quem está com o quê agora (pela etapa de cada post) e o que está atrasado. Clique numa pessoa para ver a lista.</p>
<div class="team-grid">
	<?php foreach ( $load as $uid => $l ) : ?>
		<a class="team-card<?php echo $l['late'] + $l['tasks_late'] ? ' is-late' : ''; ?><?php echo $sel === $uid ? ' is-active' : ''; ?>" href="<?php echo esc_url( lk_panel_url( 'time', 0, array( 'pessoa' => $uid ) ) ); ?>">
			<span class="avatar"><?php echo esc_html( lk_initials( $l['user']->display_name ) ); ?></span>
			<?php $st = lk_presence( $l['user']->ID ); ?>
			<strong><i class="pres pres--<?php echo esc_attr( $st ); ?>" title="<?php echo esc_attr( lk_presence_labels()[ $st ] ); ?>"></i><?php echo esc_html( $l['user']->display_name ); ?></strong>
			<span class="team-nums"><b><?php echo (int) $l['posts']; ?></b> posts · <b><?php echo (int) $l['tasks']; ?></b> tarefas</span>
			<?php if ( $l['late'] + $l['tasks_late'] ) : ?><em class="badge badge--late"><?php echo (int) ( $l['late'] + $l['tasks_late'] ); ?> atrasado(s)</em><?php else : ?><em class="badge badge--ok">em dia</em><?php endif; ?>
		</a>
	<?php endforeach; ?>
</div>
<?php if ( $sel ) : ?>
	<?php $mine = array_filter( lk_posts( 'p.stage NOT IN (%s, %s)', array( lk_stage_for( 'agendado' ), lk_stage_for( 'publicado' ) ) ), function ( $p ) use ( $sel ) { return lk_post_owner( $p ) === $sel; } ); ?>
	<section class="card"><div class="card-head"><h3>Posts com <?php echo esc_html( get_userdata( $sel )->display_name ); ?></h3></div>
		<div class="table">
			<?php foreach ( $mine as $p ) : ?>
				<a class="table-row" href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>"><span class="cell-main"><span><strong><?php echo esc_html( $p->title ); ?></strong><small><?php echo esc_html( lk_post_client_label( $p ) . ' · ' . ( lk_stages()[ $p->stage ] ?? '' ) ); ?></small></span></span><span><?php echo lk_post_deadline( $p ) ? lk_deadline_badge( $p ) : ( $p->scheduled_at ? lk_due_badge( substr( $p->scheduled_at, 0, 10 ) ) : '—' ); // phpcs:ignore ?></span><span><?php echo lk_post_late( $p ) ? '<em class="badge badge--late">atrasado</em>' : ''; ?></span></a>
			<?php endforeach; ?>
			<?php if ( ! $mine ) : ?><p class="muted small" style="padding:16px">Nada com essa pessoa agora.</p><?php endif; ?>
		</div>
	</section>
<?php endif; ?>
<section class="card">
	<div class="card-head"><h3>Responsáveis por cliente</h3><span class="muted small">definidos na ficha de cada cliente</span></div>
	<div class="table">
		<?php foreach ( lk_clients() as $c ) : ?>
			<a class="table-row" href="<?php echo esc_url( lk_panel_url( 'cliente', $c->id ) ); ?>"><span class="cell-main"><span class="svc-dot" style="background:<?php echo esc_attr( $c->color ?: '#14E9EC' ); ?>"></span><strong><?php echo esc_html( lk_client_label( $c ) ); ?></strong></span>
			<?php foreach ( $roles as $f => $lab ) : ?><?php $u = $c->$f ? get_userdata( $c->$f ) : null; ?><span data-label="<?php echo esc_attr( $lab ); ?>"><small class="muted"><?php echo esc_html( $lab ); ?></small> <?php echo $u ? esc_html( strtok( $u->display_name, ' ' ) ) : '<em class="muted">—</em>'; ?></span><?php endforeach; ?></a>
		<?php endforeach; ?>
	</div>
</section>
<?php
lk_panel_end();
