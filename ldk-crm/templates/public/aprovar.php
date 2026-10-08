<?php
/**
 * Aprovação do cliente: /aprovar/<token>/ (sem login) — o mesmo conteúdo aparece na área do cliente.
 * Visual de prévia do post (como no Instagram), com os botões 👍 Aprovar e ✏️ Pedir ajuste embaixo (estilo mLabs).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$client = lk_get( 'clients', $p->client_id );
$media  = lk_post_media( $p );
$open   = $p->stage === lk_stage_for( 'aprovacao' ) || 'pendente' === $p->client_status;
$hist   = lk_rows( 'post_comments', "post_id = %d AND internal = 0 AND target NOT IN ('log','apontamento')", array( $p->id ), 'id' );
$cnotes = lk_post_notes( $p, true );
$ig     = $client ? lk_social_account( $client->id, 'instagram' ) : null;
$handle = $ig && $ig->username ? $ig->username : sanitize_title( lk_client_label( $client ) );
$avatar = $ig && $ig->avatar ? $ig->avatar : '';
$nets   = lk_post_networks( $p );
$state  = $msg ? 'msg' : ( $open ? 'open' : ( 'aprovado' === $p->client_status ? 'aprovado' : 'ajuste' ) );
lk_head( 'Aprovação · ' . $p->title );
?>
<body class="lk apx-page">
<div class="apx">
	<header class="apx-top">
		<?php if ( lk_setting( 'logo' ) ) : ?><img src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt="<?php echo esc_attr( lk_setting( 'empresa' ) ); ?>"><?php endif; ?>
		<span class="apx-who"><?php echo lk_client_avatar_html( $client, 'avatar avatar--sm' ); // phpcs:ignore ?><?php echo esc_html( lk_client_label( $client ) ); ?></span>
	</header>

	<main class="apx-main">
		<div class="apx-info">
			<span class="apx-status apx-status--<?php echo esc_attr( $state ); ?>">
				<?php echo esc_html( array( 'open' => 'Aguardando sua aprovação', 'aprovado' => 'Aprovado ✓', 'ajuste' => 'Ajuste em andamento', 'msg' => 'Resposta enviada' )[ $state ] ); ?>
			</span>
			<h1><?php echo esc_html( $p->title ); ?></h1>
			<p><?php echo esc_html( lk_formats()[ $p->format ] ?? '' ); ?><?php if ( $p->scheduled_at ) : ?> · agendado para <strong><?php echo esc_html( lk_date( $p->scheduled_at, 'd/m' ) . ' às ' . substr( $p->scheduled_at, 11, 5 ) ); ?></strong><?php endif; ?></p>
			<div class="apx-nets">
				<?php foreach ( $nets as $n ) : ?><span class="apx-net apx-net--<?php echo esc_attr( $n ); ?>" title="<?php echo esc_attr( lk_networks()[ $n ] ?? $n ); ?>"><?php echo esc_html( lk_networks()[ $n ] ?? $n ); ?></span><?php endforeach; ?>
			</div>
		</div>

		<?php
		$pend_all = lk_posts( 'p.client_id = %d AND ( p.stage = %s OR p.client_status = %s )', array( $p->client_id, lk_stage_for( 'aprovacao' ), 'pendente' ), 'p.scheduled_at, p.id' );
		$ids      = array_map( function ( $x ) { return (int) $x->id; }, $pend_all );
		$pos      = array_search( (int) $p->id, $ids, true );
		$nav      = false === $pos ? array() : array( 'pos' => $pos + 1, 'total' => count( $ids ), 'prev' => $pos > 0 ? lk_post_url( $pend_all[ $pos - 1 ] ) : '', 'next' => $pos < count( $ids ) - 1 ? lk_post_url( $pend_all[ $pos + 1 ] ) : '' );
		echo lk_ig_preview_html( $p, $client, $media, $ig, $nav ); // phpcs:ignore
		?>

		<?php if ( $msg ) : ?><div class="apx-msg"><?php echo esc_html( $msg ); ?></div><?php endif; ?>
		<?php if ( isset( $_GET['aprovado'] ) && $open ) : ?><div class="apx-msg">✅ Aprovado! Agora este<?php echo $nav ? ' (' . (int) $nav['pos'] . ' de ' . (int) $nav['total'] . ')' : ''; ?>:</div><?php endif; // phpcs:ignore WordPress.Security.NonceVerification ?>
		<?php if ( ! $open && ! $msg ) : ?>
			<div class="apx-msg"><?php echo 'aprovado' === $p->client_status ? 'Conteúdo aprovado ✓ Obrigado!' : 'Estamos trabalhando no ajuste. Você recebe de novo para aprovar.'; ?></div>
		<?php endif; ?>

		<?php if ( $cnotes ) : ?><div class="apx-hist"><p class="apx-hist-t">Apontamentos</p><?php echo lk_notes_html( $p, $cnotes, false ); // phpcs:ignore ?></div><?php endif; ?>
		<?php if ( $open && $media && lk_note_can_client( $p->client_id ) ) : ?>
			<details class="apx-hist apx-note"><summary>📌 Fazer apontamento</summary>
				<form method="post" class="note-form" enctype="multipart/form-data">
					<?php wp_nonce_field( 'lk_approve_' . $p->id ); ?>
					<label>Sobre <select name="alvo"><?php foreach ( lk_note_abouts() as $k => $lab ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $lab ); ?></option><?php endforeach; ?></select></label>
					<label>No tempo do vídeo (opcional) <span class="note-at"><input type="text" name="at" placeholder="0:12" inputmode="numeric"><button type="button" data-grab>📍 Pegar do vídeo</button></span></label>
					<input type="hidden" name="media_i" value="">
					<textarea name="comentario" rows="3" placeholder="Escreva o que você quer apontar… ou grave um áudio"></textarea>
					<div class="apx-att" data-audio-rec><label class="apx-att-file">📎 Anexar referência (imagem, PDF ou vídeo)<input type="file" name="ref_file" accept="image/*,application/pdf,video/mp4"></label><input type="file" name="audio_file" hidden data-audio-input><button type="button" data-audio-btn>🎙️ Gravar áudio</button> <audio controls hidden data-audio-prev></audio></div>
					<button type="submit" name="decisao" value="apontar" class="apx-send">Enviar apontamento</button>
				</form>
			</details>
		<?php endif; ?>
		<?php if ( $hist ) : ?>
			<div class="apx-hist">
				<p class="apx-hist-t">Conversa sobre este post</p>
				<?php foreach ( $hist as $h ) : ?><p><strong><?php echo $h->from_client ? 'Você' : esc_html( lk_setting( 'empresa' ) ); ?><?php echo in_array( $h->target, array( 'arte', 'legenda' ), true ) ? ' · ajuste na ' . esc_html( $h->target ) : ''; ?>:</strong> <?php echo esc_html( $h->body ); ?></p><?php endforeach; ?>
			</div>
		<?php endif; ?>
	</main>

	<?php if ( $open && ! $msg && $media ) : ?>
		<form method="post" class="apx-form" data-apv enctype="multipart/form-data">
			<?php wp_nonce_field( 'lk_approve_' . $p->id ); ?>
			<div class="apx-sheet" data-apv-box hidden role="dialog" aria-label="Pedir ajuste">
				<div class="apx-sheet-in">
					<strong>O que precisa mudar?</strong>
					<div class="apx-seg">
						<label><input type="radio" name="alvo" value="arte" checked><span>🎨 Na arte</span></label>
						<label><input type="radio" name="alvo" value="legenda"><span>✍️ Na legenda</span></label>
						<label><input type="radio" name="alvo" value="ambos"><span>🎨✍️ Nos dois</span></label>
					</div>
					<textarea name="comentario" rows="4" placeholder="Conte o que precisa mudar… ou grave um áudio"></textarea>
					<div class="apx-att" data-audio-rec><label class="apx-att-file">📎 Anexar referência<input type="file" name="ref_file" accept="image/*,application/pdf,video/mp4"></label><input type="file" name="audio_file" hidden data-audio-input><button type="button" data-audio-btn>🎙️ Gravar áudio</button> <audio controls hidden data-audio-prev></audio></div>
					<div class="apx-sheet-btns">
						<button type="button" class="apx-cancel" data-apv-cancel>Cancelar</button>
						<button type="submit" name="decisao" value="alterar" class="apx-send">Enviar pedido de ajuste</button>
					</div>
				</div>
			</div>
			<div class="apx-fab">
				<button type="button" class="apx-round apx-round--edit" data-apv-change aria-label="Pedir ajuste" title="Pedir ajuste">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
					<span>Ajustar</span>
				</button>
				<button type="submit" name="decisao" value="aprovar" class="apx-round apx-round--ok" aria-label="Aprovar" title="Aprovar">
					<svg width="30" height="30" viewBox="0 0 24 24" fill="currentColor"><path d="M2 21h4V9H2v12zm22-11a2 2 0 0 0-2-2h-6.31l.95-4.57.03-.32a1.5 1.5 0 0 0-.44-1.06L15.17 1 8.59 7.59A1.98 1.98 0 0 0 8 9v10a2 2 0 0 0 2 2h9a2 2 0 0 0 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73z"/></svg>
					<span>Aprovar</span>
				</button>
			</div>
		</form>
	<?php endif; ?>
	<footer class="apx-foot"><?php echo lk_credit_html( 'dark' ); // phpcs:ignore ?></footer>
</div>
<script src="<?php echo esc_url( LK_URL . 'assets/notes.js?ver=' . LK_VERSION ); ?>"></script>
<script src="<?php echo esc_url( LK_URL . 'assets/audio.js?ver=' . LK_VERSION ); ?>"></script>
<script>
(function () {
	// Carrossel: contador e pontinhos.
	var sl = document.querySelector('[data-slides]'), pg = document.querySelector('[data-pager]'), ct = document.querySelector('[data-count]');
	if (sl && pg) sl.addEventListener('scroll', function () {
		var i = Math.round(sl.scrollLeft / sl.clientWidth);
		pg.querySelectorAll('i').forEach(function (d, k) { d.classList.toggle('is-on', k === i); });
		if (ct) ct.textContent = (i + 1) + '/' + pg.children.length;
	}, { passive: true });
	// Legenda longa: "mais".
	var cap = document.querySelector('[data-caption]'), more = document.querySelector('[data-more]');
	if (cap && more && cap.scrollHeight > 130) { cap.classList.add('is-clamp'); more.hidden = false; more.addEventListener('click', function () { cap.classList.remove('is-clamp'); more.hidden = true; }); }
	// Botões.
	var f = document.querySelector('[data-apv]');
	if (!f) return;
	var box = f.querySelector('[data-apv-box]');
	f.querySelector('[data-apv-change]').addEventListener('click', function () { box.hidden = false; box.querySelector('textarea').focus(); });
	f.querySelector('[data-apv-cancel]').addEventListener('click', function () { box.hidden = true; });
	box.addEventListener('click', function (e) { if (e.target === box) box.hidden = true; });
	f.addEventListener('submit', function (e) {
		var d = e.submitter && e.submitter.value;
		var au = f.querySelector('[data-audio-input]'), rf = f.querySelector('[name=ref_file]'); if (d === 'alterar' && !f.querySelector('textarea').value.trim() && !(au && au.files.length) && !(rf && rf.files.length)) { e.preventDefault(); alert('Conte o que precisa mudar (escrevendo, gravando um áudio ou anexando uma referência).'); return; }
		if (d === 'aprovar') { e.submitter.classList.add('is-go'); document.body.classList.add('apx-approving'); }
	});
})();
</script>
</body>
</html>
