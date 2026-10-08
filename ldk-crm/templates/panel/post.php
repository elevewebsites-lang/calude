<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rows = $id ? lk_posts( 'p.id = %d', array( $id ) ) : array();
$p    = $rows ? $rows[0] : null;
if ( ! $p ) {
	lk_render( 'panel/404' );
}
$client   = lk_get( 'clients', $p->client_id );
$stages   = lk_stages();
$media    = lk_post_media( $p );
$comments = lk_rows( 'post_comments', "post_id = %d AND target <> 'apontamento'", array( $p->id ), 'id' );
$notes    = lk_post_notes( $p );
$teamu    = array( '' => 'Automático (quem cuida do assunto)' );
foreach ( lk_team_users() as $tu ) {
	$teamu[ $tu->ID ] = $tu->display_name;
}
$mopts = array( '' => 'Geral' );
foreach ( $media as $mi => $mm ) {
	$mopts[ $mi ] = ( $mi + 1 ) . ' · ' . $mm['name'];
}
$pub      = lk_json( $p->published );
$owner    = get_userdata( lk_post_owner( $p ) );
$send     = get_transient( 'lk_last_send_' . get_current_user_id() );
if ( $send && (int) $send['post'] === (int) $p->id ) {
	delete_transient( 'lk_last_send_' . get_current_user_id() );
} else {
	$send = null;
}
$wa = $client && $client->whatsapp ? lk_wa_link( $client->whatsapp, lk_approval_message( $p, $client ) ) : '';
ob_start();
lk_form( 'post_stage', 'inline-form stage-form' );
echo '<input type="hidden" name="id" value="' . (int) $p->id . '"><select name="stage" onchange="this.form.submit()">';
foreach ( $stages as $s => $n ) {
	echo '<option value="' . esc_attr( $s ) . '"' . selected( $p->stage, $s, false ) . '>' . esc_html( $n ) . '</option>';
}
echo '</select></form>';
lk_panel_start( $p->title, 'conteudo', ob_get_clean() );
?>
<a class="back" href="<?php echo esc_url( lk_panel_url( 'conteudo', 0, array( 'cliente' => $p->client_id ) ) ); ?>"><?php echo lk_icon( 'voltar', 16 ); // phpcs:ignore ?> <?php echo esc_html( lk_post_client_label( $p ) ); ?></a>

<?php if ( $send && $send['wa'] ) : ?>
	<section class="card card--accent ready-bar"><div><strong>Enviado por e-mail.</strong> Reforce pelo WhatsApp com a mensagem pronta:</div><a class="btn btn--wa" href="<?php echo esc_url( $send['wa'] ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Mandar no WhatsApp</span></a></section>
<?php endif; ?>
<?php if ( $p->publish_error && ! lk_manage_only() ) : ?><div class="flash flash--erro"><strong>Não publicou:</strong> <?php echo esc_html( $p->publish_error ); ?></div><?php endif; ?>
<?php if ( 'alteracao' === $p->client_status ) : ?><div class="flash flash--warn"><strong>O cliente pediu ajuste na <?php echo esc_html( $p->change_target ); ?>.</strong> <?php echo 'arte' === $p->change_target ? 'Está com o design.' : 'Está com o social media: ajuste a legenda e reenvie.'; ?></div><?php endif; ?>

<div class="split">
	<div class="split-main">
		<section class="card flow-card">
			<div class="flow-now"><span class="muted small">Etapa</span><strong><?php echo lk_stage_chip( $p ); // phpcs:ignore ?></strong><span class="muted small">Com <?php echo $owner ? esc_html( $owner->display_name ) : 'ninguém'; ?><?php echo $p->scheduled_at ? ' · publicação ' . esc_html( lk_date( $p->scheduled_at, 'd/m H:i' ) ) : ''; ?></span><?php echo lk_deadline_badge( $p, 'entregar até' ); // phpcs:ignore ?></div>
			<?php echo lk_prazos_line( $p ); // phpcs:ignore ?>
			<div class="row-btns">
				<?php if ( $p->stage === lk_stage_for( 'planejamento' ) ) : ?>
					<a class="btn btn--primary" href="<?php echo esc_url( lk_panel_url( 'planejamento', 0, array( 'cliente' => $p->client_id, 'mes' => $p->scheduled_at ? substr( $p->scheduled_at, 0, 7 ) : current_time( 'Y-m' ) ) ) ); ?>"><?php echo lk_icon( 'lista', 16 ); // phpcs:ignore ?><span>Planejamento do mês</span></a>
					<?php lk_action_button( 'post_stage', array( 'id' => $p->id, 'stage' => lk_stage_for( 'design' ) ), 'Pular e mandar para o design', 'btn btn--ghost', 'Mandar esta arte para o design sem esperar o planejamento ser aprovado?' ); ?>
				<?php endif; ?>
				<?php if ( $p->stage === lk_stage_for( 'design' ) && $media ) : ?>
					<?php lk_form( 'post_media', 'inline-form' ); ?><input type="hidden" name="id" value="<?php echo (int) $p->id; ?>"><input type="hidden" name="pronta" value="1"><button type="submit" class="btn btn--primary">Arte pronta → Revisão</button></form>
				<?php endif; ?>
				<?php if ( in_array( $p->stage, array( lk_stage_for( 'revisao' ), lk_stage_for( 'aprovacao' ) ), true ) && $media ) : ?>
					<?php lk_action_button( 'post_send', array( 'id' => $p->id ), lk_icon( 'email', 16 ) . '<span>' . ( $p->stage === lk_stage_for( 'revisao' ) ? 'Revisado ✓ enviar para o cliente' : 'Reenviar para o cliente' ) . '</span>', 'btn btn--primary' ); ?>
					<?php if ( get_transient( 'lk_gate_' . $p->id ) ) : ?><?php lk_action_button( 'post_send', array( 'id' => $p->id, 'forcar' => 1 ), 'Enviar mesmo assim (ignorar a revisão)', 'btn btn--ghost', 'Enviar para o cliente mesmo com os pontos da revisão?' ); ?><?php endif; ?>
				<?php endif; ?>
				<?php if ( in_array( $p->stage, array( lk_stage_for( 'revisao' ), lk_stage_for( 'aprovacao' ) ), true ) && $media ) : ?>
					<?php lk_action_button( 'post_approve_manual', array( 'id' => $p->id ), 'Aprovar manualmente', 'btn btn--ghost', 'Marcar como aprovado pelo cliente (ele aprovou por fora) e agendar?' ); ?>
				<?php endif; ?>
				<?php if ( $wa ) : ?><a class="btn btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Enviar no WhatsApp</span></a><?php endif; ?>
				<?php if ( in_array( $p->change_target, array( 'legenda', 'ambos' ), true ) ) : ?><?php lk_action_button( 'post_redone_caption', array( 'id' => $p->id ), '✓ Refiz a legenda', 'btn btn--primary', 'Já ajustou a legenda? O atendimento será avisado para enviar ao cliente.' ); ?><?php endif; ?>
				<?php if ( ! lk_manage_only() ) { lk_action_button( 'post_pause', array( 'id' => $p->id ), $p->paused ? '▶ Retomar postagem' : '⏸ Pausar postagem', 'btn btn--ghost', $p->paused ? '' : 'Pausar? O post não será publicado sozinho até você retomar.' ); } ?>
				<button type="button" class="btn btn--ghost" data-copy="<?php echo esc_attr( lk_post_url( $p ) ); ?>">Copiar link de aprovação</button>
				<?php if ( ! lk_manage_only() && ( $p->stage === lk_stage_for( 'agendado' ) || $p->publish_error ) ) : ?>
					<?php lk_action_button( 'post_publish_now', array( 'id' => $p->id ), 'Publicar agora', 'btn btn--ghost', 'Publicar agora nas redes marcadas?' ); ?>
				<?php endif; ?>
				<?php if ( $p->stage !== lk_stage_for( 'publicado' ) ) : ?><?php lk_action_button( 'post_mark_published', array( 'id' => $p->id ), 'Marcar como publicado', 'btn btn--link btn--sm' ); ?><?php endif; ?>
			</div>
		</section>

		<section class="card">
			<div class="card-head"><h3>Arte</h3><span class="muted small"><?php echo esc_html( lk_formats()[ $p->format ] ?? '' ); ?> · vai para o Drive do cliente</span></div>
			<?php lk_form( 'post_media', 'stack', true ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
				<input type="hidden" name="new_media" value="[]" data-new-media>
				<input type="hidden" name="order" value="" data-order>
				<?php if ( $media ) : ?>
					<div class="media-grid" data-media-grid>
						<?php foreach ( $media as $i => $m ) : ?>
							<figure class="media-item" draggable="true" data-i="<?php echo (int) $i; ?>">
								<?php $src = lk_media_src( $p, $i, $m ); ?><?php if ( $src && 'video' === $m['type'] ) : ?><video class="media-play" src="<?php echo esc_url( $src ); ?>" controls preload="metadata" playsinline data-mi="<?php echo (int) $i; ?>"></video><?php elseif ( $src ) : ?><img src="<?php echo esc_url( $src ); ?>" alt="" loading="lazy"><?php elseif ( 'video' === $m['type'] ) : ?><div class="media-video"><?php echo lk_icon( 'tela', 28 ); // phpcs:ignore ?><span>Vídeo</span></div><?php else : ?><div class="media-video"><?php echo lk_icon( 'imagem', 28 ); // phpcs:ignore ?><span>Drive</span></div><?php endif; ?>
								<figcaption><a href="<?php echo esc_url( $m['link'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $m['name'] ); ?></a><label class="chk small"><input type="checkbox" name="remove[]" value="<?php echo (int) $i; ?>"> remover</label></figcaption>
							</figure>
						<?php endforeach; ?>
					</div>
					<?php if ( count( $media ) > 1 ) : ?><p class="muted small">Arraste para mudar a ordem do carrossel.</p><?php endif; ?>
				<?php endif; ?>
				<label class="drop drop--file"><input type="file" multiple accept="image/*,video/*" data-media-upload data-client="<?php echo (int) $p->client_id; ?>"><?php echo lk_icon( 'upload', 20 ); // phpcs:ignore ?><span><strong>Subir arte, fotos ou vídeo</strong><small>Instagram: imagem JPG até 8 MB · vídeo MP4/MOV até 300 MB (3 s a 15 min) · Story até 100 MB (60 s) · carrossel até 20 · salva sozinho ao terminar de subir · vídeo grande vai direto para o Drive</small></span></label>
				<div class="upfiles" data-upfiles></div>
				<?php $pc = lk_get( 'clients', $p->client_id ); echo $pc ? lk_post_picker_html( $pc ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php echo lk_video_opts_html( $p, true ); // phpcs:ignore ?>
				<div class="form-actions">
					<?php if ( $p->stage === lk_stage_for( 'design' ) ) : ?>
						<button type="submit" name="so_salvar" value="1" class="btn btn--ghost" data-media-save>Só salvar (ainda não terminei)</button>
						<button type="submit" class="btn btn--primary" data-media-save>Salvar e enviar para revisão</button>
					<?php else : ?>
						<button type="submit" class="btn btn--ghost" data-media-save>Salvar arquivos</button>
					<?php endif; ?>
				</div>
			</form>
		</section>

		<section class="card" id="apontamentos">
			<div class="card-head"><h3>Apontamentos</h3><span class="muted small"><?php echo count( array_filter( $notes, function ( $n ) { return ! $n->resolved; } ) ); ?> aberto(s)</span></div>
			<?php if ( lk_note_can() ) : ?><details class="note-new"><summary class="btn btn--primary btn--sm">📌 Fazer apontamento</summary>
				<?php lk_form( 'post_note', 'stack', true ); ?>
					<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
					<div class="grid-2">
						<?php lk_select( 'assignee', 'Para quem é', $teamu, '' ); ?>
						<?php lk_select( 'about', 'Sobre', lk_note_abouts(), 'arte' ); ?>
						<?php lk_select( 'media_i', 'Em qual arquivo', $mopts, '' ); ?>
						<label class="field"><span>No tempo do vídeo (mm:ss)</span><span class="note-at"><input type="text" name="at" placeholder="0:12" inputmode="numeric"><button type="button" class="btn btn--ghost btn--sm" data-grab>📍 Pegar do player</button></span></label>
					</div>
					<?php lk_input( 'body', 'O que precisa mudar (ou grave um áudio)', '', 'textarea', 'rows="3" placeholder="Ex.: trocar a trilha aos 0:12; texto cortado na lateral"' ); ?>
					<div class="note-att" data-audio-rec><label class="field"><span>Anexar referência (imagem, PDF ou vídeo)</span><input type="file" name="ref_file" accept="image/*,application/pdf,video/mp4"></label><input type="file" name="audio_file" hidden data-audio-input><button type="button" class="btn btn--ghost btn--sm" data-audio-btn>🎙️ Gravar áudio</button> <audio controls hidden data-audio-prev></audio></div>
					<div class="form-actions"><?php lk_check( 'visible', 'Mostrar também ao cliente (desmarcado = só a equipe vê, antes de chegar nele)', false ); ?><button type="submit" class="btn btn--primary">Enviar apontamento</button></div>
				</form>
			</details><?php else : ?><p class="muted small">Seu usuário não está liberado para fazer apontamentos (o administrador decide).</p><?php endif; ?>
			<?php echo lk_notes_html( $p, $notes, true ); // phpcs:ignore ?>
		</section>

		<section class="card">
			<div class="card-head"><h3>Conversa</h3><span class="muted small">use @nome para chamar alguém da equipe</span></div>
			<ul class="timeline post-thread">
				<?php foreach ( $comments as $cm ) : ?>
					<?php $u = get_userdata( $cm->user_id ); ?>
					<li class="<?php echo $cm->from_client ? 'is-client' : ''; ?><?php echo 'log' === $cm->target ? ' is-log' : ''; ?>"><span class="timeline-dot"></span><div>
						<?php if ( 'log' !== $cm->target ) : ?><strong><?php echo esc_html( $cm->from_client ? 'Cliente' : ( $u ? $u->display_name : '' ) ); ?></strong><?php echo in_array( $cm->target, array( 'arte', 'legenda', 'ambos' ), true ) ? ' <em class="badge badge--warn">ajuste na ' . esc_html( $cm->target ) . '</em>' : ( 'planejamento' === $cm->target ? ' <em class="badge badge--warn">no planejamento</em>' : '' ); ?><?php echo $cm->internal ? ' <em class="badge">interno</em>' : ''; ?><br><?php endif; ?>
						<?php echo nl2br( esc_html( $cm->body ) ); ?><?php echo lk_attach_html( $cm ); // phpcs:ignore ?><small><?php echo esc_html( lk_ago( $cm->created_at ) ); ?></small></div></li>
				<?php endforeach; ?>
			</ul>
			<?php lk_form( 'post_comment', 'stack' ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
				<?php lk_input( 'body', 'Comentário', '', 'textarea', 'rows="2" placeholder="Ex.: @Ana ajusta a cor do fundo"' ); ?>
				<div class="form-actions"><?php lk_check( 'interno', 'Só a equipe vê', true ); ?><button type="submit" class="btn btn--ghost btn--sm">Comentar</button></div>
			</form>
		</section>
	</div>
	<aside class="split-side">
		<?php if ( lk_manage_only() ) { echo lk_post_mlabs_html( $p ); } // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<section class="card">
			<div class="card-head"><h3>Post</h3></div>
			<?php lk_post_form( $p ); ?>
		</section>
		<?php if ( $pub ) : ?>
			<section class="card"><div class="card-head"><h3>Publicado</h3></div>
				<?php foreach ( $pub as $n => $x ) : ?><p class="small"><strong><?php echo esc_html( lk_networks()[ $n ] ?? $n ); ?>:</strong> <?php echo ! empty( $x['url'] ) ? '<a href="' . esc_url( $x['url'] ) . '" target="_blank" rel="noopener">ver post</a>' : ( ! empty( $x['manual'] ) ? 'manual' : 'ok' ); ?> <span class="muted"><?php echo esc_html( lk_date( $x['at'] ?? '', 'd/m H:i' ) ); ?></span></p><?php endforeach; ?>
			</section>
		<?php endif; ?>
		<div class="stack-btns">
			<?php lk_action_button( 'post_duplicate', array( 'id' => $p->id ), 'Duplicar', 'btn btn--ghost btn--block' ); ?>
			<?php lk_action_button( 'post_delete', array( 'id' => $p->id ), 'Excluir', 'btn btn--danger btn--block', 'Excluir este post?' ); ?>
		</div>
	</aside>
</div>
<script>window.LK_LIMITS = <?php echo wp_json_encode( lk_media_limits() ); ?>;</script>
<script src="<?php echo esc_url( LK_URL . 'assets/media.js?ver=' . LK_VERSION ); ?>"></script>
<script src="<?php echo esc_url( LK_URL . 'assets/cliente-arquivos.js?ver=' . LK_VERSION ); ?>"></script>
<script src="<?php echo esc_url( LK_URL . 'assets/notes.js?ver=' . LK_VERSION ); ?>"></script>
<script src="<?php echo esc_url( LK_URL . 'assets/audio.js?ver=' . LK_VERSION ); ?>"></script>
<script src="<?php echo esc_url( LK_URL . 'assets/content.js?ver=' . LK_VERSION ); ?>"></script>
<?php
lk_panel_end();
