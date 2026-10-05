<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! lk_game_on() ) {
	lk_render( 'panel/404', array( 'section' => 'ranking' ) );
}
$uid     = get_current_user_id();
$periods = array( 'semana' => 'Esta semana', 'mes' => 'Este mês', 'geral' => 'Geral' );
$per     = isset( $_GET['periodo'] ) && isset( $periods[ sanitize_key( $_GET['periodo'] ) ] ) ? sanitize_key( $_GET['periodo'] ) : 'mes'; // phpcs:ignore WordPress.Security.NonceVerification
$rank    = lk_game_ranking( $per );
$top     = lk_game_last_month_top();
$bal     = lk_game_balance( $uid );
$mine    = lk_rows( 'game_log', 'user_id = %d', array( $uid ), 'id DESC LIMIT 15' );
lk_panel_start( 'Ranking e prêmios', 'ranking', lk_is_admin() ? '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'gamificacao' ) ) . '">Configurar</a>' : '' );
?>
<div class="split">
	<div class="split-main">
		<section class="card">
			<div class="card-head"><h3>Seu perfil</h3></div>
			<?php echo lk_game_card_html( $uid ); // phpcs:ignore ?>
		</section>
		<?php if ( $top && $top['user'] ) : ?>
			<section class="card"><div class="card-head"><h3>🏆 <?php echo esc_html( lk_game_cfg( 'destaque' ) ); ?></h3><span class="muted small"><?php echo esc_html( $top['month'] ); ?></span></div>
				<p><strong><?php echo esc_html( $top['user']->display_name ); ?></strong> · <?php echo (int) $top['pts']; ?> pontos</p></section>
		<?php endif; ?>
		<section class="card">
			<div class="card-head"><h3>Ranking</h3>
				<span class="chips"><?php foreach ( $periods as $k => $lab ) : ?><a class="btn btn--sm btn--<?php echo $per === $k ? 'primary' : 'ghost'; ?>" href="<?php echo esc_url( lk_panel_url( 'ranking', 0, array( 'periodo' => $k ) ) ); ?>"><?php echo esc_html( $lab ); ?></a> <?php endforeach; ?></span></div>
			<div class="table">
				<?php foreach ( $rank as $i => $r ) : $lv = lk_game_level( $r['total'] ); $medal = array( '🥇', '🥈', '🥉' ); ?>
					<div class="table-row<?php echo $r['user']->ID === $uid ? ' is-me' : ''; ?>">
						<span class="cell-main"><strong style="width:28px"><?php echo $r['points'] > 0 && $i < 3 ? esc_html( $medal[ $i ] ) : (int) ( $i + 1 ) . 'º'; ?></strong><?php echo lk_avatar_circle( $r['user'] ); // phpcs:ignore ?><span><strong><?php echo esc_html( $r['user']->display_name ); ?></strong><small><?php echo esc_html( $lv['name'] ); ?></small></span></span>
						<span><b><?php echo (int) $r['points']; ?></b> pts</span>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	</div>
	<aside class="split-side">
		<section class="card">
			<div class="card-head"><h3>Prêmios</h3><span class="muted small">saldo: <b><?php echo (int) $bal; ?></b> pts</span></div>
			<?php foreach ( lk_game_rewards() as $i => $r ) : ?>
				<div class="pay-row"><span><strong><?php echo esc_html( $r['name'] ); ?></strong><small><?php echo esc_html( $r['desc'] ); ?></small></span><em class="badge"><?php echo (int) $r['cost']; ?> pts</em>
				<?php lk_action_button( 'game_redeem', array( 'reward' => $i ), 'Resgatar', 'btn btn--' . ( $bal >= $r['cost'] ? 'primary' : 'ghost' ) . ' btn--sm', 'Resgatar "' . $r['name'] . '" por ' . $r['cost'] . ' pontos?' ); ?></div>
			<?php endforeach; ?>
			<?php if ( ! lk_game_rewards() ) : ?><p class="muted small">Nenhum prêmio cadastrado ainda.</p><?php endif; ?>
		</section>
		<section class="card">
			<div class="card-head"><h3>Seu histórico</h3></div>
			<ul class="timeline">
				<?php foreach ( $mine as $m ) : ?>
					<li><span class="timeline-dot"></span><div><strong><?php echo ( $m->points > 0 ? '+' : '' ) . (int) $m->points; ?></strong> <?php echo esc_html( $m->note ); ?><?php echo 'redeem' === $m->kind ? ' <em class="badge">' . esc_html( array( 'pending' => 'pedido', 'delivered' => 'entregue', 'refused' => 'recusado' )[ $m->status ] ?? '' ) . '</em>' : ''; ?><small><?php echo esc_html( lk_ago( $m->created_at ) ); ?></small></div></li>
				<?php endforeach; ?>
				<?php if ( ! $mine ) : ?><li><div class="muted small">Conclua tarefas e publique posts para pontuar.</div></li><?php endif; ?>
			</ul>
		</section>
	</div>
</div>
<?php
lk_panel_end();
