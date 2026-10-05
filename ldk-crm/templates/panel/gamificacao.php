<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$c       = lk_game_cfg();
$pending = lk_rows( 'game_log', "kind = 'redeem' AND status = 'pending'", array(), 'id' );
$done    = lk_rows( 'game_log', "kind = 'redeem' AND status <> 'pending'", array(), 'id DESC LIMIT 10' );
$team    = array();
foreach ( lk_team_users() as $u ) {
	$team[ $u->ID ] = $u->display_name;
}
lk_panel_start( 'Gamificação', 'gamificacao', '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'ranking' ) ) . '">Ver ranking</a>' );
?>
<div class="split">
	<div class="split-main">
		<section class="card">
			<div class="card-head"><h3>Regras e textos</h3><span class="muted small">tudo aqui é editável</span></div>
			<?php lk_form( 'game_save', 'stack' ); ?>
				<?php lk_check( 'on', 'Gamificação ligada', '1' === (string) $c['on'] ); ?>
				<h4 style="margin:12px 0 4px">Pontos</h4>
				<div class="grid-3">
					<?php lk_input( 'pts_task', 'Pontos base por tarefa', $c['pts_task'], 'number', 'min="0"' ); ?>
					<?php lk_input( 'bonus_prazo', 'Bônus se no prazo', $c['bonus_prazo'], 'number', 'min="0"' ); ?>
					<?php lk_input( 'destaque', 'Nome do título mensal', $c['destaque'] ); ?>
					<?php lk_input( 'pts_post_des', 'Post publicado: designer', $c['pts_post_des'], 'number', 'min="0"' ); ?>
					<?php lk_input( 'pts_post_soc', 'Post publicado: social media', $c['pts_post_soc'], 'number', 'min="0"' ); ?>
				</div>
				<h4 style="margin:12px 0 4px">Multiplicador por prioridade da tarefa</h4>
				<div class="grid-4">
					<?php foreach ( lk_priorities() as $k => $lab ) : ?><?php lk_input( 'mult_' . $k, $lab, $c[ 'mult_' . $k ], 'text', 'inputmode="decimal"' ); ?><?php endforeach; ?>
				</div>
				<?php lk_input( 'niveis', 'Níveis (um por linha: Nome | pontos ganhos)', $c['niveis'], 'textarea', 'rows="6"' ); ?>
				<?php lk_input( 'premios', 'Prêmios (um por linha: Nome | custo em pontos | descrição)', $c['premios'], 'textarea', 'rows="6"' ); ?>
				<?php lk_input( 'conquistas', 'Conquistas (Nome | tarefas, posts ou pontos | meta | emoji)', $c['conquistas'], 'textarea', 'rows="6"' ); ?>
				<div class="form-actions"><button type="submit" class="btn btn--primary">Salvar</button></div>
			</form>
		</section>
	</div>
	<aside class="split-side">
		<section class="card">
			<div class="card-head"><h3>Resgates pedidos</h3></div>
			<?php foreach ( $pending as $r ) : $u = get_userdata( $r->user_id ); ?>
				<div class="pay-row"><span><strong><?php echo esc_html( $r->note ); ?></strong><small><?php echo esc_html( ( $u ? $u->display_name : '' ) . ' · ' . abs( (int) $r->points ) . ' pts · ' . lk_ago( $r->created_at ) ); ?></small></span>
				<?php lk_action_button( 'game_redeem_set', array( 'id' => $r->id, 'status' => 'delivered' ), 'Entregue', 'btn btn--primary btn--sm' ); ?>
				<?php lk_action_button( 'game_redeem_set', array( 'id' => $r->id, 'status' => 'refused' ), 'Recusar', 'btn btn--link btn--sm', 'Recusar e devolver os pontos?' ); ?></div>
			<?php endforeach; ?>
			<?php if ( ! $pending ) : ?><p class="muted small">Nenhum pedido pendente.</p><?php endif; ?>
			<?php foreach ( $done as $r ) : $u = get_userdata( $r->user_id ); ?>
				<p class="muted small"><?php echo esc_html( ( $u ? $u->display_name : '' ) . ' — ' . $r->note . ' (' . ( 'refused' === $r->status ? 'recusado' : 'entregue' ) . ')' ); ?></p>
			<?php endforeach; ?>
		</section>
		<section class="card">
			<div class="card-head"><h3>Lançar pontos</h3><span class="muted small">bônus ou ajuste (use negativo para tirar)</span></div>
			<?php lk_form( 'game_bonus', 'stack' ); ?>
				<?php lk_select( 'user_id', 'Pessoa', $team ); ?>
				<?php lk_input( 'points', 'Pontos', '', 'number', 'required' ); ?>
				<?php lk_input( 'note', 'Motivo', '', 'text', 'placeholder="Ex.: entrega impecável no cliente X"' ); ?>
				<div class="form-actions"><button type="submit" class="btn btn--primary">Lançar</button></div>
			</form>
		</section>
	</div>
</div>
<?php
lk_panel_end();
