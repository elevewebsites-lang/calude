<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$cid = isset( $_GET['cliente'] ) ? absint( $_GET['cliente'] ) : 0;
$ym  = isset( $_GET['mes'] ) && preg_match( '/^\d{4}-\d{2}$/', $_GET['mes'] ) ? sanitize_text_field( $_GET['mes'] ) : gmdate( 'Y-m', strtotime( current_time( 'Y-m' ) . '-01 +1 month' ) );
// phpcs:enable
$client = $cid ? lk_get( 'clients', $cid ) : null;
$prev   = gmdate( 'Y-m', strtotime( $ym . '-01 -1 month' ) );
$next   = gmdate( 'Y-m', strtotime( $ym . '-01 +1 month' ) );
$here   = lk_panel_url( 'planejamento', 0, array_filter( array( 'cliente' => $cid, 'mes' => $ym ) ) );

// Sem cliente: visão geral do mês (todos os clientes), com o botão ▶ Start.
if ( ! $client ) {
	lk_panel_start( 'Planejamento · ' . ucfirst( lk_month_label( $ym ) ), 'planejamento' );
	?>
	<div class="cal-head">
		<a class="icon-btn" href="<?php echo esc_url( lk_panel_url( 'planejamento', 0, array( 'mes' => $prev ) ) ); ?>">‹</a>
		<h3><?php echo esc_html( ucfirst( lk_month_label( $ym ) ) ); ?></h3>
		<a class="icon-btn" href="<?php echo esc_url( lk_panel_url( 'planejamento', 0, array( 'mes' => $next ) ) ); ?>">›</a>
	</div>
	<?php if ( lk_is_admin() ) : ?>
	<details class="card"><summary class="card-head" style="cursor:pointer"><h3>Datas extras e feriados locais</h3><span class="muted small">entram nos planejamentos e nos avisos</span></summary>
		<?php lk_form( 'dates_extra_save', 'stack' ); ?>
			<?php lk_input( 'datas_extra', 'Uma por linha: dia/mês | nome | feriado ou data (ano é opcional: 05/12/2026)', (string) get_option( 'lk_dates_extra', '' ), 'textarea', 'rows="4" placeholder="05/12 | Aniversário de Taubaté | feriado&#10;19/06 | Dia do Cinema | data"' ); ?>
			<div class="form-actions"><button type="submit" class="btn btn--primary">Salvar</button></div>
		</form>
	</details>
	<?php endif; ?>
	<p class="muted small hint">Clique em <strong>▶ Start</strong> para abrir o planejamento do cliente: briefing, datas do mês e os posts. Depois vai para a revisão interna e, com o ok, direto para a cliente.</p>
	<div class="table plan-table">
		<div class="table-row table-head"><span>Cliente</span><span>Artes do mês</span><span>Planejamento</span><span>Com</span><span></span></div>
		<?php foreach ( lk_clients() as $c ) : ?>
			<?php
			$plan = lk_plan_for( $c->id, $ym );
			$n    = lk_client_month_count( $c->id, $ym );
			$soc  = $c->social_id ? get_userdata( $c->social_id ) : null;
			?>
			<div class="table-row">
				<a class="cell-main" href="<?php echo esc_url( lk_panel_url( 'planejamento', 0, array( 'cliente' => $c->id, 'mes' => $ym ) ) ); ?>"><span class="svc-dot" style="background:<?php echo esc_attr( $c->color ?: '#14E9EC' ); ?>"></span><strong><?php echo esc_html( lk_client_label( $c ) ); ?></strong></a>
				<span data-label="Artes"><?php echo $c->posts_quota > 0 ? lk_quota_html( $c, $ym ) : esc_html( $n . ' arte(s) · sem pacote definido' ); // phpcs:ignore ?></span>
				<span data-label="Planejamento"><?php echo $plan ? '<em class="badge badge--plan-' . esc_attr( $plan->status ) . '">' . esc_html( lk_plan_status_label( $plan->status ) ) . '</em>' : '<em class="badge badge--off">não começou</em>'; ?></span>
				<span data-label="Com" class="small"><?php echo $soc ? esc_html( strtok( $soc->display_name, ' ' ) ) : '—'; ?></span>
				<span><?php lk_action_button( 'plan_start', array( 'client_id' => $c->id, 'mes' => $ym ), $plan ? 'Abrir' : '▶ Start', $plan ? 'btn btn--ghost btn--sm' : 'btn btn--primary btn--sm' ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	lk_panel_end();
	return;
}

$plan    = lk_plan_for( $client->id, $ym );
$posts   = lk_plan_month_posts( $client->id, $ym );
$pending = lk_plan_pending_posts( $client, $ym );
$sent    = get_transient( 'lk_last_plan_' . get_current_user_id() );
if ( $sent && $plan && (int) $sent['plan'] === (int) $plan->id ) {
	delete_transient( 'lk_last_plan_' . get_current_user_id() );
} else {
	$sent = null;
}
$status   = $plan ? $plan->status : 'rascunho';
$reviewer = get_userdata( lk_plan_reviewer() );
$is_rev   = lk_is_plan_reviewer();
$wa       = $plan && $client->whatsapp ? lk_wa_link( $client->whatsapp, lk_plan_message( $plan, $client ) ) : '';
$alert    = lk_plan_quota_alert( $client, $ym );
$new_day  = $ym === current_time( 'Y-m' ) ? lk_today() : $ym . '-01';
$brief    = function_exists( 'lk_briefing_answers' ) ? lk_briefing_answers( $client ) : array();
$actions  = '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'conteudo', 0, array( 'cliente' => $client->id, 'mes' => $ym ) ) ) . '">' . lk_icon( 'calendario', 16 ) . '<span>Calendário</span></a><button type="button" class="btn btn--primary" data-open="novo-post" data-date="' . esc_attr( $new_day ) . '">' . lk_icon( 'mais', 16 ) . '<span>Post no planejamento</span></button>';
lk_panel_start( 'Planejamento · ' . lk_client_label( $client ), 'planejamento', $actions );
$steps = array(
	'rascunho' => 'Montando',
	'revisao'  => 'Revisão interna',
	'enviado'  => 'Com a cliente',
	'ajustes'  => 'Ajustes',
	'aprovado' => 'Aprovado → Design',
);
$order = array_keys( $steps );
?>
<a class="back" href="<?php echo esc_url( lk_panel_url( 'planejamento', 0, array( 'mes' => $ym ) ) ); ?>"><?php echo lk_icon( 'voltar', 16 ); // phpcs:ignore ?> Todos os clientes</a>

<div class="cal-head">
	<a class="icon-btn" href="<?php echo esc_url( lk_panel_url( 'planejamento', 0, array( 'cliente' => $client->id, 'mes' => $prev ) ) ); ?>">‹</a>
	<h3><?php echo esc_html( ucfirst( lk_month_label( $ym ) ) ); ?></h3>
	<a class="icon-btn" href="<?php echo esc_url( lk_panel_url( 'planejamento', 0, array( 'cliente' => $client->id, 'mes' => $next ) ) ); ?>">›</a>
	<?php echo lk_quota_html( $client, $ym ); // phpcs:ignore ?>
	<?php if ( $client->posts_quota <= 0 ) : ?><a class="small" href="<?php echo esc_url( lk_panel_url( 'cliente', $client->id ) ); ?>#contrato">definir o pacote de artes</a><?php endif; ?>
</div>

<?php if ( ! $plan ) : ?>
	<section class="card plan-start">
		<h3>Planejamento de <?php echo esc_html( lk_month_label( $ym ) ); ?> ainda não começou</h3>
		<p class="muted">O Start já traz as datas comemorativas do mês e o briefing da cliente. Depois é só montar os posts (sem arte).</p>
		<?php lk_action_button( 'plan_start', array( 'client_id' => $client->id, 'mes' => $ym ), '▶ Start no planejamento', 'btn btn--primary btn--lg' ); ?>
	</section>
	<?php
	lk_panel_end();
	return;
endif;
?>

<nav class="plan-flow" aria-label="Etapas do planejamento">
	<?php foreach ( $steps as $k => $label ) : ?>
		<?php $cls = $k === $status ? 'is-now' : ( array_search( $k, $order, true ) < array_search( $status, $order, true ) ? 'is-done' : '' ); ?>
		<span class="plan-step <?php echo esc_attr( $cls ); ?>"><i></i><?php echo esc_html( $label ); ?></span>
	<?php endforeach; ?>
</nav>

<?php if ( $sent && $sent['wa'] ) : ?>
	<section class="card card--accent ready-bar"><div><strong>Enviado para a cliente</strong> (painel dela + e-mail). Reforce pelo WhatsApp:</div><a class="btn btn--wa" href="<?php echo esc_url( $sent['wa'] ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Mandar no WhatsApp</span></a></section>
<?php endif; ?>
<?php if ( $alert ) : ?><div class="flash flash--warn">⚠️ <?php echo esc_html( $alert ); ?></div><?php endif; ?>
<?php
$hol_list = array_values( array_filter( lk_month_dates( $ym ), function ( $d ) { return 'feriado' === $d['type']; } ) );
if ( $hol_list ) :
	$on_hol = array();
	foreach ( $posts as $hp ) {
		$hd = $hp->scheduled_at ? lk_is_holiday( $hp->scheduled_at ) : null;
		if ( $hd ) {
			$on_hol[] = lk_date( $hp->scheduled_at, 'd/m' ) . ' ' . $hp->title . ' (' . $hd['name'] . ')';
		}
	}
	?>
	<div class="flash flash--warn hol-alert">🔴 <strong>Feriados em <?php echo esc_html( lk_month_label( $ym ) ); ?>:</strong>
		<?php echo esc_html( implode( ' · ', array_map( function ( $d ) { return $d['day'] . '/' . substr( $d['date'], 5, 2 ) . ' ' . $d['name'] . ( $d['sub'] ? ' (' . $d['sub'] . ')' : '' ); }, $hol_list ) ) ); ?>.
		<?php if ( $on_hol ) : ?><br><strong>Posts marcados em feriado — confira se vale publicar ou mudar o dia:</strong> <?php echo esc_html( implode( '; ', $on_hol ) ); ?>.<?php else : ?> Nenhum post cai em feriado.<?php endif; ?>
	</div>
<?php endif; ?>
<?php $sheet_url = lk_plan_sheet_url( $plan ); ?>
<p class="muted small"><?php if ( $sheet_url ) : ?>📊 <a href="<?php echo esc_url( $sheet_url ); ?>" target="_blank" rel="noopener">Abrir a planilha do planejamento no Drive</a> (atualiza sozinha; abre e baixa como Excel)<?php elseif ( function_exists( 'lk_google_connected' ) && lk_google_connected() ) : ?>📊 A planilha deste planejamento no Drive é criada em instantes.<?php else : ?>Conecte o Google Drive em Configurações para guardar o planejamento numa planilha.<?php endif; ?> <?php $fl = lk_drive_folder_link( array( 'Clientes', lk_client_label( $client ) ) ); if ( $fl ) : ?> · <a href="<?php echo esc_url( $fl ); ?>" target="_blank" rel="noopener">Pasta do cliente no Drive</a><?php endif; ?></p>
<?php if ( $plan->review_note && 'rascunho' === $status ) : ?><div class="flash flash--warn"><strong>Voltou da revisão:</strong> <?php echo nl2br( esc_html( $plan->review_note ) ); ?></div><?php endif; ?>
<?php if ( $plan->client_notes ) : ?><div class="flash flash--warn"><strong>Comentário geral da cliente:</strong><br><?php echo nl2br( esc_html( $plan->client_notes ) ); ?></div><?php endif; ?>

<section class="card flow-card plan-bar">
	<div class="flow-now">
		<span class="muted small">Planejamento de <?php echo esc_html( lk_month_label( $ym ) ); ?></span>
		<strong><em class="badge badge--plan-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( lk_plan_status_label( $status ) ); ?></em></strong>
		<span class="muted small"><?php echo count( $posts ); ?> post(s) no mês · <?php echo count( $pending ); ?> para a cliente aprovar<?php echo $plan->reviewed_at ? ' · revisado por ' . esc_html( get_userdata( $plan->reviewed_by ) ? get_userdata( $plan->reviewed_by )->display_name : '' ) . ' em ' . esc_html( lk_date( $plan->reviewed_at, 'd/m H:i' ) ) : ''; ?></span>
	</div>
	<div class="row-btns">
		<?php if ( in_array( $status, array( 'rascunho', 'ajustes' ), true ) && $pending ) : ?>
			<?php lk_action_button( 'plan_send', array( 'client_id' => $client->id, 'mes' => $ym ), lk_icon( 'check', 16 ) . '<span>Enviar para a revisão' . ( $reviewer ? ' (' . esc_html( strtok( $reviewer->display_name, ' ' ) ) . ')' : '' ) . '</span>', 'btn btn--primary', $alert ? $alert . ' Enviar mesmo assim?' : '' ); ?>
			<?php if ( $is_rev ) : ?><?php lk_action_button( 'plan_send', array( 'client_id' => $client->id, 'mes' => $ym, 'direto' => 1 ), 'Já revisei: enviar direto à cliente', 'btn btn--ghost', 'Enviar para a cliente agora (painel + e-mail)?' ); ?><?php endif; ?>
		<?php endif; ?>
		<?php if ( 'revisao' === $status && $is_rev ) : ?>
			<?php lk_action_button( 'plan_review_ok', array( 'id' => $plan->id ), '✓ Revisado: enviar para a cliente', 'btn btn--primary', 'Aprovar a revisão? Vai para o painel da cliente e para o e-mail dela.' ); ?>
			<button type="button" class="btn btn--ghost" data-open="plan-back">Devolver para ajustar</button>
		<?php elseif ( 'revisao' === $status ) : ?>
			<span class="muted small">Esperando a revisão de <?php echo esc_html( $reviewer ? $reviewer->display_name : 'quem revisa' ); ?>.</span>
		<?php endif; ?>
		<?php if ( in_array( $status, array( 'enviado', 'ajustes', 'aprovado' ), true ) ) : ?>
			<?php if ( $wa ) : ?><a class="btn btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>WhatsApp</span></a><?php endif; ?>
			<button type="button" class="btn btn--ghost" data-copy="<?php echo esc_attr( lk_plan_url( $plan ) ); ?>">Copiar link</button>
		<?php endif; ?>
		<a class="btn btn--ghost" href="<?php echo esc_url( lk_plan_url( $plan ) ); ?>" target="_blank" rel="noopener">Ver como a cliente</a>
		<?php if ( in_array( $status, array( 'enviado', 'ajustes' ), true ) && $pending ) : ?>
			<?php lk_action_button( 'plan_approve_manual', array( 'id' => $plan->id ), 'Aprovar manualmente', 'btn btn--link', 'Marcar tudo como aprovado (a cliente aprovou por fora)? Os posts vão para o design.' ); ?>
		<?php endif; ?>
	</div>
</section>

<div class="plan-work">
	<aside class="plan-side">
		<section class="card">
			<div class="card-head"><h3>Briefing de <?php echo esc_html( lk_client_label( $client ) ); ?></h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'cliente', $client->id ) ); ?>#briefing">ver tudo</a></div>
			<?php if ( $brief ) : ?>
				<dl class="plan-brief">
					<?php foreach ( array_slice( $brief, 0, 10 ) as $row ) : ?>
						<?php
						$q = is_array( $row ) ? (string) ( $row['q'] ?? '' ) : '';
						$a = is_array( $row ) ? (string) ( $row['a'] ?? '' ) : '';
						if ( '' === trim( $a ) ) {
							continue;
						}
						?>
						<dt><?php echo esc_html( $q ); ?></dt><dd><?php echo nl2br( esc_html( wp_trim_words( (string) $a, 40 ) ) ); ?></dd>
					<?php endforeach; ?>
				</dl>
			<?php else : ?>
				<p class="muted small">A cliente ainda não respondeu o briefing. <a href="<?php echo esc_url( lk_panel_url( 'cliente', $client->id ) ); ?>#briefing">Mandar o briefing</a></p>
			<?php endif; ?>
		</section>

		<section class="card">
			<div class="card-head"><h3>Contexto do mês</h3><span class="muted small">a cliente vê no topo</span></div>
			<?php lk_form( 'plan_meta_save', 'stack' ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $plan->id; ?>">
				<?php lk_input( 'intro', 'Texto de abertura', (string) $plan->intro, 'textarea', 'rows="3"' ); ?>
				<?php lk_input( 'consideracoes', 'Considerações do mês (eventos, promoções, datas, lançamentos)', (string) (string) $plan->consideracoes, 'textarea', 'rows="6" placeholder="Ex.: Outubro Rosa: ação com 10% de desconto de 15 a 20/10&#10;Evento na loja dia 25"' ); ?>
				<div class="plan-chips"><span class="muted small">Datas do mês:</span><?php foreach ( lk_plan_datas( $ym ) as $dt ) : ?><button type="button" class="chip" data-add-line="<?php echo esc_attr( $dt ); ?>">+ <?php echo esc_html( $dt ); ?></button><?php endforeach; ?></div>
				<?php lk_input( 'destaques', 'Pontos importantes (um por linha · aparecem com ✓ para a cliente)', (string) (string) $plan->destaques, 'textarea', 'rows="4" placeholder="Foco em agendamentos pelo WhatsApp&#10;3 Reels com antes e depois&#10;Campanha do Outubro Rosa"' ); ?>
				<div class="form-actions"><button type="submit" class="btn btn--primary btn--sm">Salvar contexto</button></div>
			</form>
		</section>
	</aside>

	<div class="plan-main">
		<section class="card plan-quick">
			<div class="card-head"><h3>Novo post no planejamento</h3><span class="muted small">sem arte: tema, legenda e dia</span></div>
			<?php lk_form( 'post_save', 'plan-quick-form' ); ?>
				<input type="hidden" name="client_id" value="<?php echo (int) $client->id; ?>">
				<input type="hidden" name="volta_url" value="<?php echo esc_attr( $here ); ?>">
				<div class="grid-4">
					<?php lk_input( 'date', 'Dia', $new_day, 'date', 'required min="' . esc_attr( $ym . '-01' ) . '" max="' . esc_attr( gmdate( 'Y-m-t', strtotime( $ym . '-01' ) ) ) . '"' ); ?>
					<?php lk_input( 'time', 'Horário', '18:00', 'time' ); ?>
					<?php lk_select( 'format', 'Formato', lk_formats(), 'arte' ); ?>
					<label class="field"><span>Redes</span><span class="plan-nets"><?php foreach ( lk_networks() as $n => $nl ) : ?><label class="chk"><input type="checkbox" name="networks[]" value="<?php echo esc_attr( $n ); ?>"<?php checked( in_array( $n, array( 'instagram', 'facebook' ), true ) ); ?>> <?php echo esc_html( 'gmn' === $n ? 'GMN' : $nl ); ?></label><?php endforeach; ?></span></label>
				</div>
				<?php lk_input( 'title', 'Tema / título do post', '', 'text', 'required placeholder="Ex.: Outubro Rosa: prevenção começa no cuidado"' ); ?>
				<?php lk_input( 'caption', 'Ideia da legenda', '', 'textarea', 'rows="3" placeholder="A legenda (ou a ideia dela) que a cliente vai aprovar…"' ); ?>
				<?php lk_input( 'notes', 'Briefing da arte para o design (interno)', '', 'textarea', 'rows="2" placeholder="Referências, texto da arte, cores…"' ); ?>
				<div class="form-actions"><button type="submit" class="btn btn--primary">Adicionar ao planejamento</button></div>
			</form>
		</section>

		<?php if ( ! $posts ) : ?>
			<div class="empty"><?php echo lk_icon( 'lista', 26 ); // phpcs:ignore ?><h3>Nenhum post em <?php echo esc_html( lk_month_label( $ym ) ); ?> ainda</h3><p class="muted">Adicione os posts do mês acima.</p></div>
		<?php else : ?>
			<div class="plan-list">
				<?php foreach ( $posts as $p ) : ?>
					<?php
					$notes = lk_rows( 'post_comments', 'post_id = %d AND target = %s', array( $p->id, 'planejamento' ), 'id' );
					$is_pl = $p->stage === lk_stage_for( 'planejamento' );
					$pst   = array( 'ok' => array( 'aprovado pela cliente', 'badge--ok' ), 'ajuste' => array( 'cliente pediu ajuste', 'badge--late' ) )[ $p->plan_status ] ?? null;
					?>
					<article class="plan-item<?php echo $is_pl ? '' : ' is-past'; ?><?php echo 'ajuste' === $p->plan_status ? ' is-adjust' : ''; ?>">
						<div class="plan-date"><b><?php echo esc_html( lk_date( $p->scheduled_at, 'd' ) ); ?></b><small><?php echo esc_html( lk_dow_short( $p->scheduled_at ) ); ?> · <?php echo esc_html( substr( $p->scheduled_at, 11, 5 ) ); ?></small></div>
						<div class="plan-body">
							<div class="plan-top"><span class="badge"><?php echo esc_html( lk_formats()[ $p->format ] ?? '' ); ?></span><?php echo lk_stage_chip( $p ); // phpcs:ignore ?><?php echo $pst ? '<em class="badge ' . esc_attr( $pst[1] ) . '">' . esc_html( $pst[0] ) . '</em>' : ''; ?></div>
							<h4><?php echo esc_html( $p->title ); ?></h4>
							<p class="plan-caption"><?php echo $p->caption ? nl2br( esc_html( $p->caption ) ) : '<em class="muted">Sem legenda ainda.</em>'; ?></p>
							<?php foreach ( $notes as $n ) : ?><p class="plan-note"><strong><?php echo $n->from_client ? 'Cliente' : 'Equipe'; ?>:</strong> <?php echo esc_html( $n->body ); ?> <small class="muted"><?php echo esc_html( lk_ago( $n->created_at ) ); ?></small></p><?php endforeach; ?>
						</div>
						<div class="plan-acts">
							<?php if ( $is_pl ) : ?><button type="button" class="btn btn--ghost btn--sm" data-open="edit-<?php echo (int) $p->id; ?>"><?php echo lk_icon( 'editar', 14 ); // phpcs:ignore ?><span>Editar</span></button><?php endif; ?>
							<a class="btn btn--link btn--sm" href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>">abrir</a>
						</div>
					</article>
					<?php
					if ( $is_pl ) {
						lk_modal_start( 'edit-' . $p->id, 'Editar · ' . $p->title );
						lk_post_form( $p, 0, $here );
						lk_modal_end();
					}
					?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php if ( 'revisao' === $status && $is_rev ) : ?>
	<?php lk_modal_start( 'plan-back', 'Devolver para ajustar' ); ?>
		<?php lk_form( 'plan_review_back', 'stack' ); ?>
			<input type="hidden" name="id" value="<?php echo (int) $plan->id; ?>">
			<?php lk_input( 'nota', 'O que precisa mudar', '', 'textarea', 'rows="4" required' ); ?>
			<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Devolver</button></div>
		</form>
	<?php lk_modal_end(); ?>
<?php endif; ?>

<?php
lk_modal_start( 'novo-post', 'Novo post no planejamento' );
lk_post_form( null, $client->id, $here );
lk_modal_end();
?>
<script src="<?php echo esc_url( LK_URL . 'assets/content.js?ver=' . LK_VERSION ); ?>"></script>
<script>
document.addEventListener('click', function (e) {
	var b = e.target.closest('[data-add-line]'); if (!b) return;
	var t = b.closest('form').querySelector('[name=consideracoes]');
	t.value = (t.value.trim() ? t.value.replace(/\s+$/, '') + '\n' : '') + b.dataset.addLine; b.remove(); t.focus();
});
</script>
<?php
lk_panel_end();
