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
		<span><?php echo esc_html( lk_client_label( $client ) ); ?></span>
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

		<article class="apx-post" aria-label="Prévia do post">
			<div class="apx-head">
				<span class="apx-av"><?php echo esc_html( mb_strtoupper( mb_substr( lk_client_label( $client ), 0, 1 ) ) ); ?><?php if ( $avatar ) : ?><img src="<?php echo esc_url( $avatar ); ?>" alt="" onerror="this.remove()"><?php endif; ?></span>
				<strong><?php echo esc_html( $handle ); ?></strong>
				<span class="apx-dots" aria-hidden="true">•••</span>
			</div>
			<div class="apx-media">
				<?php if ( ! $media ) : ?>
					<div class="apx-empty">A arte ainda não foi enviada.</div>
				<?php else : ?>
					<div class="apx-slides" data-slides>
						<?php foreach ( $media as $mi => $m ) : $src = lk_media_src( $p, $mi, $m ); ?>
							<div class="apx-slide">
								<?php if ( $src && 'video' === $m['type'] ) : ?><video src="<?php echo esc_url( $src ); ?>" controls playsinline preload="metadata" data-mi="<?php echo (int) $mi; ?>"></video>
								<?php elseif ( $src ) : ?><img src="<?php echo esc_url( $src ); ?>" alt="Arte <?php echo esc_attr( $p->title ); ?>">
								<?php else : ?><iframe src="<?php echo esc_url( 'https://drive.google.com/file/d/' . rawurlencode( $m['id'] ) . '/preview' ); ?>" allow="autoplay" loading="lazy"></iframe><?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
					<?php if ( count( $media ) > 1 ) : ?>
						<span class="apx-count" data-count>1/<?php echo count( $media ); ?></span>
						<div class="apx-pager" data-pager><?php foreach ( $media as $i => $m ) : ?><i class="<?php echo 0 === $i ? 'is-on' : ''; ?>"></i><?php endforeach; ?></div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<div class="apx-actions" aria-hidden="true">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="#ed4956"><path d="M12 21.35 10.55 20C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54z"/></svg>
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8z"/></svg>
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>
				<svg class="apx-save" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
			</div>
			<div class="apx-caption" data-caption>
				<?php if ( $p->caption ) : ?><strong><?php echo esc_html( $handle ); ?></strong> <?php echo nl2br( esc_html( lk_post_final_caption( $p ) ) ); ?><?php else : ?><em>Sem legenda.</em><?php endif; ?>
			</div>
			<button type="button" class="apx-more" data-more hidden>mais</button>
		</article>

		<?php if ( $msg ) : ?><div class="apx-msg"><?php echo esc_html( $msg ); ?></div><?php endif; ?>
		<?php if ( ! $open && ! $msg ) : ?>
			<div class="apx-msg"><?php echo 'aprovado' === $p->client_status ? 'Conteúdo aprovado ✓ Obrigado!' : 'Estamos trabalhando no ajuste. Você recebe de novo para aprovar.'; ?></div>
		<?php endif; ?>

		<?php if ( $cnotes ) : ?><div class="apx-hist"><p class="apx-hist-t">Apontamentos</p><?php echo lk_notes_html( $p, $cnotes, false ); // phpcs:ignore ?></div><?php endif; ?>
		<?php if ( $open && $media && lk_note_can_client( $p->client_id ) ) : ?>
			<details class="apx-hist apx-note"><summary>📌 Fazer apontamento</summary>
				<form method="post" class="note-form">
					<?php wp_nonce_field( 'lk_approve_' . $p->id ); ?>
					<label>Sobre <select name="alvo"><?php foreach ( lk_note_abouts() as $k => $lab ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $lab ); ?></option><?php endforeach; ?></select></label>
					<label>No tempo do vídeo (opcional) <span class="note-at"><input type="text" name="at" placeholder="0:12" inputmode="numeric"><button type="button" data-grab>📍 Pegar do vídeo</button></span></label>
					<input type="hidden" name="media_i" value="">
					<textarea name="comentario" rows="3" placeholder="Escreva o que você quer apontar…" required></textarea>
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
		<form method="post" class="apx-form" data-apv>
			<?php wp_nonce_field( 'lk_approve_' . $p->id ); ?>
			<div class="apx-sheet" data-apv-box hidden role="dialog" aria-label="Pedir ajuste">
				<div class="apx-sheet-in">
					<strong>O que precisa mudar?</strong>
					<div class="apx-seg">
						<label><input type="radio" name="alvo" value="arte" checked><span>🎨 Na arte</span></label>
						<label><input type="radio" name="alvo" value="legenda"><span>✍️ Na legenda</span></label>
					</div>
					<textarea name="comentario" rows="4" placeholder="Conte o que precisa mudar…"></textarea>
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
		if (d === 'alterar' && !f.querySelector('textarea').value.trim()) { e.preventDefault(); alert('Conte o que precisa mudar.'); return; }
		if (d === 'aprovar') { e.submitter.classList.add('is-go'); document.body.classList.add('apx-approving'); }
	});
})();
</script>
</body>
</html>
