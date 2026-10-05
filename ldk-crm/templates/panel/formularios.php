<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$cid     = isset( $_GET['c'] ) ? absint( $_GET['c'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$tab     = isset( $_GET['aba'] ) ? sanitize_key( $_GET['aba'] ) : 'modelos'; // phpcs:ignore WordPress.Security.NonceVerification
$tab     = in_array( $tab, array( 'modelos', 'enviados', 'resultados' ), true ) ? $tab : 'modelos';
$saved   = lk_rows( 'forms', '1=1', array(), 'id DESC' );
$sends   = lk_rows( 'form_sends', '1=1', array(), 'id DESC LIMIT 300' );
$kinds   = lk_form_kinds();
$actions = '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'formulario', 0, array( 'novo' => 'pesquisa' ) ) ) . '">📊 Nova pesquisa</a> <a class="btn btn--primary" href="' . esc_url( lk_panel_url( 'formulario', 0, array( 'novo' => 'briefing' ) ) ) . '">🧩 Montar briefing</a>';
lk_panel_start( 'Briefings e pesquisas', 'formularios', $actions );
$tabs = array( 'modelos' => 'Modelos', 'enviados' => 'Enviados (' . count( $sends ) . ')', 'resultados' => 'Resultados da pesquisa' );
?>
<div class="toolbar"><div class="seg"><?php foreach ( $tabs as $k => $l ) : ?><a href="<?php echo esc_url( lk_panel_url( 'formularios', 0, array( 'aba' => $k ) ) ); ?>" class="<?php echo $tab === $k ? 'is-active' : ''; ?>"><?php echo esc_html( $l ); ?></a><?php endforeach; ?></div></div>

<?php if ( 'modelos' === $tab ) : ?>
	<p class="muted small">Escolha um modelo para usar ou ajustar. Você também pode montar do zero e marcar <strong>"salvar como modelo"</strong> para reaproveitar depois.</p>
	<div class="fm-grid">
		<?php foreach ( lk_form_defaults() as $key => $d ) : $d = lk_form_normalize( $d ); ?>
			<section class="card fm-card">
				<span class="badge <?php echo 'pesquisa' === $d['kind'] ? 'badge--ok' : ''; ?>"><?php echo esc_html( $kinds[ $d['kind'] ] ); ?> · pronto</span>
				<h3><?php echo esc_html( $d['title'] ); ?></h3>
				<p class="muted small"><?php echo (int) lk_form_count_questions( $d['schema'] ); ?> perguntas em <?php echo count( $d['schema']['steps'] ); ?> etapas</p>
				<div class="row-btns"><a class="btn btn--primary btn--sm" href="<?php echo esc_url( lk_panel_url( 'formulario', 0, array( 'modelo' => 'default:' . $key ) ) ); ?>">Usar / ajustar</a><button type="button" class="btn btn--ghost btn--sm" data-open="enviar-form" data-ref="default:<?php echo esc_attr( $key ); ?>" data-ftitle="<?php echo esc_attr( $d['title'] ); ?>">Enviar a um cliente</button></div>
			</section>
		<?php endforeach; ?>
		<?php foreach ( $saved as $t ) : $sc = lk_form_clean_schema( $t->qschema ); ?>
			<section class="card fm-card">
				<span class="badge <?php echo 'pesquisa' === $t->kind ? 'badge--ok' : ''; ?>"><?php echo esc_html( $kinds[ $t->kind ] ?? 'Briefing' ); ?> · meu modelo</span>
				<h3><?php echo esc_html( $t->title ); ?></h3>
				<p class="muted small"><?php echo (int) lk_form_count_questions( $sc ); ?> perguntas em <?php echo count( $sc['steps'] ); ?> etapas</p>
				<div class="row-btns"><a class="btn btn--primary btn--sm" href="<?php echo esc_url( lk_panel_url( 'formulario', $t->id ) ); ?>">Editar</a><button type="button" class="btn btn--ghost btn--sm" data-open="enviar-form" data-ref="<?php echo (int) $t->id; ?>" data-ftitle="<?php echo esc_attr( $t->title ); ?>">Enviar a um cliente</button><?php lk_action_button( 'form_delete', array( 'what' => 'modelo', 'id' => $t->id ), 'Excluir', 'btn btn--link btn--sm', 'Excluir este modelo?' ); ?></div>
			</section>
		<?php endforeach; ?>
	</div>

<?php elseif ( 'enviados' === $tab ) : ?>
	<section class="card">
		<?php if ( ! $sends ) : ?><p class="muted small">Nada enviado ainda.</p><?php else : ?>
		<div class="pros-wrap"><table class="pros-table"><thead><tr><th>Cliente</th><th>Formulário</th><th>Situação</th><th>Enviado</th><th>Respondido</th><th></th></tr></thead><tbody>
			<?php foreach ( $sends as $s ) : $c = lk_get( 'clients', $s->client_id ); if ( ! $c ) { continue; } ?>
				<tr>
					<td><a class="net-cli" href="<?php echo esc_url( lk_panel_url( 'cliente', $c->id ) ); ?>#formularios"><?php echo lk_client_avatar_html( $c ); // phpcs:ignore ?><strong><?php echo esc_html( lk_client_label( $c ) ); ?></strong></a></td>
					<td><strong><?php echo esc_html( $s->title ); ?></strong><br><small class="muted"><?php echo esc_html( $kinds[ $s->kind ] ?? '' ); ?></small></td>
					<td><em class="badge <?php echo 'respondido' === $s->status ? 'badge--ok' : 'badge--warn'; ?>"><?php echo esc_html( lk_form_status_label( $s->status ) ); ?></em></td>
					<td><?php echo $s->sent_at ? esc_html( lk_date( $s->sent_at, 'd/m/Y' ) ) : '—'; ?></td>
					<td><?php echo $s->answered_at ? esc_html( lk_date( $s->answered_at, 'd/m/Y' ) ) : '—'; ?></td>
					<td class="net-act">
						<?php if ( 'respondido' === $s->status ) : ?><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'resposta', $s->id ) ); ?>">Ver respostas</a><?php if ( $s->drive_url ) : ?> <a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $s->drive_url ); ?>" target="_blank" rel="noopener">Drive</a><?php endif; ?>
						<?php else : ?><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'formulario', 0, array( 'envio' => $s->id ) ) ); ?>">Editar</a> <?php lk_action_button( 'form_resend', array( 'id' => $s->id ), 'Lembrar', 'btn btn--ghost btn--sm' ); ?><?php endif; ?>
						<?php lk_action_button( 'form_delete', array( 'what' => 'envio', 'id' => $s->id ), '×', 'btn btn--link btn--sm', 'Excluir este envio e as respostas?' ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody></table></div>
		<?php endif; ?>
	</section>

<?php else : $res = lk_form_results( $cid ); $nps = lk_form_nps( $res ); $count = count( lk_rows( 'form_sends', "kind = 'pesquisa' AND status = 'respondido'" . ( $cid ? ' AND client_id = %d' : '' ), $cid ? array( $cid ) : array() ) ); ?>
	<form method="get" class="inline-form fm-filter"><input type="hidden" name="aba" value="resultados"><label class="field field--inline"><span>Cliente</span><select name="c" onchange="this.form.submit()"><option value="0">Todos os clientes</option><?php foreach ( lk_clients() as $c ) : ?><option value="<?php echo (int) $c->id; ?>" <?php selected( $cid, $c->id ); ?>><?php echo esc_html( lk_client_label( $c ) ); ?></option><?php endforeach; ?></select></label></form>
	<?php if ( ! $count ) : ?>
		<div class="empty empty--big"><h3>Ainda não há pesquisas respondidas</h3><p class="muted">Envie a pesquisa de satisfação na ficha do cliente (ou em <strong>Modelos → Enviar a um cliente</strong>). Quando ele responder, os resultados aparecem aqui.</p></div>
	<?php else : ?>
		<section class="stats stats--4">
			<div class="stat"><span class="stat-label">Pesquisas respondidas</span><strong><?php echo (int) $count; ?></strong><small><?php echo $cid ? 'deste cliente' : 'de todos os clientes'; ?></small></div>
			<?php if ( null !== $nps ) : ?><div class="stat"><span class="stat-label">NPS</span><strong><?php echo (int) $nps; ?></strong><small>promotores − detratores (−100 a 100)</small></div><?php endif; ?>
		</section>
		<div class="fm-grid fm-grid--res">
		<?php foreach ( $res as $r ) : if ( ! $r['n'] ) { continue; } $avg = in_array( $r['type'], array( 'sat', 'scale', 'nps' ), true ) && $r['n'] ? $r['sum'] / $r['n'] : null; $max = $r['dist'] ? max( $r['dist'] ) : 1; $order = 'sat' === $r['type'] ? lk_form_sat_options() : array_keys( $r['dist'] ); if ( in_array( $r['type'], array( 'scale', 'nps' ), true ) ) { sort( $order, SORT_NUMERIC ); } ?>
			<section class="card fm-res">
				<span class="muted small"><?php echo esc_html( $r['step'] ); ?> · <?php echo (int) $r['n']; ?> resposta<?php echo 1 === (int) $r['n'] ? '' : 's'; ?></span>
				<h3><?php echo esc_html( $r['label'] ); ?></h3>
				<?php if ( null !== $avg ) : ?><p class="fm-avg"><strong><?php echo esc_html( number_format( $avg, 1, ',', '' ) ); ?></strong> <span class="muted small">de <?php echo 'nps' === $r['type'] ? '10' : '5'; ?></span></p><?php endif; ?>
				<?php if ( $r['dist'] ) : foreach ( $order as $lab ) : $n = (int) ( $r['dist'][ $lab ] ?? 0 ); if ( ! $n && 'sat' !== $r['type'] ) { continue; } ?>
					<div class="fm-bar"><span><?php echo esc_html( $lab ); ?></span><i><b style="width:<?php echo (int) round( 100 * $n / $max ); ?>%"></b></i><em><?php echo (int) $n; ?></em></div>
				<?php endforeach; endif; ?>
				<?php foreach ( $r['texts'] as $t ) : ?><blockquote class="fm-quote"><?php echo esc_html( $t[0] ); ?><small><?php echo esc_html( $t[1] ); ?></small></blockquote><?php endforeach; ?>
			</section>
		<?php endforeach; ?>
		</div>
	<?php endif; ?>
<?php endif; ?>

<?php lk_modal_start( 'enviar-form', 'Enviar a um cliente' ); ?>
	<?php lk_form( 'form_quick', 'stack' ); ?>
		<input type="hidden" name="ref" value="" data-fref>
		<p class="muted small" data-ftitle-out></p>
		<?php lk_select( 'client_id', 'Cliente', lk_client_options( 'Escolha o cliente…' ), '', 'required' ); ?>
		<p class="muted small">O cliente recebe um e-mail com o link e também vê o formulário na área dele.</p>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Enviar</button></div>
	</form>
<?php lk_modal_end(); ?>
<script>document.addEventListener('click',function(e){var b=e.target.closest('[data-open="enviar-form"]');if(!b)return;var d=document.getElementById('enviar-form');d.querySelector('[data-fref]').value=b.dataset.ref;d.querySelector('[data-ftitle-out]').textContent=b.dataset.ftitle||'';});</script>
<?php
lk_panel_end();
