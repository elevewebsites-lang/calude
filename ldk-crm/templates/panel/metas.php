<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$goals   = lk_goals();
$types   = lk_goal_types();
$periods = array( 'mes' => 'Mês atual (recomeça todo mês)', 'ano' => 'Ano atual', 'sempre' => 'Acumulado' );
$presets = array(
	array( 'Receita do mês', 'receita', 'mes' ),
	array( 'Recorrente (MRR)', 'mrr', 'sempre' ),
	array( 'Novos clientes no mês', 'clientes_novos', 'mes' ),
	array( 'Total de clientes', 'clientes_total', 'sempre' ),
);
lk_panel_start( 'Metas', 'metas', '<button type="button" class="btn btn--ghost" data-cel-test="goal">▶ Ver animação</button>' );
?>
<p class="muted hint">Defina as metas da agência e acompanhe o progresso. <strong>Quando uma meta for batida, você recebe uma comemoração aqui no painel.</strong> Só administradores veem esta página.</p>
<?php if ( ! $goals ) : ?>
	<section class="card"><h3>Comece com uma meta</h3>
		<p class="muted small">Escolha um modelo e ajuste o valor:</p>
		<div class="row-btns"><?php foreach ( $presets as $pr ) : ?>
			<?php lk_form( 'goal_save', 'inline-form' ); ?><input type="hidden" name="name" value="<?php echo esc_attr( $pr[0] ); ?>"><input type="hidden" name="type" value="<?php echo esc_attr( $pr[1] ); ?>"><input type="hidden" name="period" value="<?php echo esc_attr( $pr[2] ); ?>"><input type="hidden" name="target" value="<?php echo esc_attr( in_array( $pr[1], array( 'receita', 'mrr' ), true ) ? '10000' : '10' ); ?>"><button type="submit" class="btn btn--ghost btn--sm">+ <?php echo esc_html( $pr[0] ); ?></button></form>
		<?php endforeach; ?></div></section>
<?php endif; ?>
<div class="goal-grid">
	<?php foreach ( $goals as $g ) : $p = lk_goal_progress( $g ); $t = $types[ $g['type'] ] ?? $types['receita']; $hit = $p['pct'] >= 100; $c = 2 * M_PI * 44; ?>
		<section class="goal-card<?php echo $hit ? ' is-hit' : ''; ?>">
			<div class="goal-ring"><svg viewBox="0 0 100 100"><circle class="goal-bg" cx="50" cy="50" r="44"/><circle class="goal-fg" cx="50" cy="50" r="44" stroke-dasharray="<?php echo esc_attr( round( $c * $p['pct'] / 100, 1 ) . ' ' . round( $c, 1 ) ); ?>" transform="rotate(-90 50 50)"/></svg><b><?php echo (int) $p['pct']; ?>%</b></div>
			<div class="goal-body">
				<h3><?php echo esc_html( $g['name'] ); ?><?php echo $hit ? ' 🏆' : ''; ?></h3>
				<p class="goal-nums"><strong><?php echo esc_html( lk_goal_fmt( $g, $p['value'] ) ); ?></strong> <span class="muted">de <?php echo esc_html( lk_goal_fmt( $g, $p['target'] ) ); ?></span></p>
				<p class="muted small"><?php echo esc_html( $t[0] . ' · ' . $p['label'] ); ?><?php echo $hit ? ' · <strong>meta batida!</strong>' : ' · faltam ' . esc_html( lk_goal_fmt( $g, $p['left'] ) ) . ( 'sempre' !== ( $g['period'] ?? '' ) ? ' · ' . (int) $p['days'] . ' dia(s)' : '' ); // phpcs:ignore ?></p>
				<details class="goal-edit"><summary class="small">Editar</summary>
					<?php lk_form( 'goal_save', 'stack' ); ?>
						<input type="hidden" name="id" value="<?php echo esc_attr( $g['id'] ); ?>">
						<?php lk_input( 'name', 'Nome da meta', $g['name'] ); ?>
						<div class="grid-3">
							<?php lk_select( 'type', 'O que medir', array_map( function ( $x ) { return $x[0]; }, $types ), $g['type'] ); ?>
							<?php lk_input( 'target', 'Meta', $t[3] ? number_format( (float) $g['target'], 2, ',', '.' ) : (string) (float) $g['target'], 'text', 'inputmode="decimal" required' ); ?>
							<?php lk_select( 'period', 'Período', $periods, $g['period'] ?? 'mes' ); ?>
						</div>
						<div class="form-actions"><button type="submit" class="btn btn--primary btn--sm">Salvar</button></div>
					</form>
					<?php lk_action_button( 'goal_delete', array( 'id' => $g['id'] ), 'Excluir meta', 'btn btn--link btn--sm', 'Excluir esta meta?' ); ?>
				</details>
			</div>
		</section>
	<?php endforeach; ?>
</div>
<section class="card">
	<div class="card-head"><h3>Nova meta</h3></div>
	<?php lk_form( 'goal_save', 'stack' ); ?>
		<?php lk_input( 'name', 'Nome da meta', '', 'text', 'placeholder="Ex.: Faturar R$ 30 mil em outubro"' ); ?>
		<div class="grid-3">
			<?php lk_select( 'type', 'O que medir', array_map( function ( $x ) { return $x[0]; }, $types ), 'receita' ); ?>
			<?php lk_input( 'target', 'Meta (valor ou quantidade)', '', 'text', 'inputmode="decimal" required placeholder="30000"' ); ?>
			<?php lk_select( 'period', 'Período', $periods, 'mes' ); ?>
		</div>
		<p class="muted small">Receita = pagamentos marcados como pagos no período. Recorrente = soma das mensalidades ativas. Clientes novos = cadastrados no período.</p>
		<div class="form-actions"><button type="submit" class="btn btn--primary">Criar meta</button></div>
	</form>
</section>
<?php
lk_panel_end();
