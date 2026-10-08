<?php
/**
 * Roteiro do vídeo: /roteiro/<token>/ (sem login). O cliente lê, aprova ou pede ajustes.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$client = lk_get( 'clients', $s->client_id );
$done   = 'aprovado' === $s->status;
lk_head( 'Roteiro · ' . $s->title );
?>
<body class="lk apx-page">
<div class="apx">
	<header class="apx-top">
		<?php if ( lk_setting( 'logo' ) ) : ?><img src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt="<?php echo esc_attr( lk_setting( 'empresa' ) ); ?>"><?php endif; ?>
		<span class="apx-who"><?php echo lk_client_avatar_html( $client, 'avatar avatar--sm' ); // phpcs:ignore ?><?php echo esc_html( lk_client_label( $client ) ); ?></span>
	</header>
	<main class="apx-main" style="max-width:720px;margin:0 auto;padding:24px 18px 120px">
		<span class="apx-status apx-status--<?php echo $done ? 'aprovado' : 'open'; ?>"><?php echo esc_html( $done ? 'Roteiro aprovado ✓' : 'Aguardando sua aprovação' ); ?></span>
		<h1><?php echo esc_html( $s->title ); ?></h1>
		<p class="muted">
			<?php if ( $s->record_date ) : ?>📅 Gravação em <strong><?php echo esc_html( lk_date( $s->record_date, 'd/m/Y' ) . ( $s->record_time ? ' às ' . $s->record_time : '' ) ); ?></strong><?php endif; ?>
			<?php if ( $s->place ) : ?> · 📍 <?php echo esc_html( $s->place ); ?><?php endif; ?>
		</p>
		<?php if ( $msg ) : ?><div class="apx-msg"><?php echo esc_html( $msg ); ?></div><?php endif; ?>
		<article class="card" style="padding:22px;margin:18px 0;line-height:1.75;font-size:16px"><?php echo nl2br( esc_html( (string) $s->body ) ); ?></article>
		<?php if ( 'ajustes' === $s->status && $s->client_note ) : ?><div class="apx-hist"><p class="apx-hist-t">O seu pedido de ajuste</p><p><?php echo nl2br( esc_html( $s->client_note ) ); ?></p></div><?php endif; ?>
		<?php if ( ! $done ) : ?>
			<form method="post" class="stack">
				<?php wp_nonce_field( 'lk_script_' . $s->id ); ?>
				<label class="field"><span>Quer mudar alguma coisa? Escreva aqui (opcional se for aprovar)</span><textarea name="comentario" rows="3" placeholder="Ex.: trocar a primeira frase; incluir o endereço no final…"></textarea></label>
				<div class="apx-sheet-btns" style="display:flex;gap:10px;flex-wrap:wrap">
					<button type="submit" name="decisao" value="ajustar" class="apx-send" style="background:transparent;border:1px solid currentColor">✏️ Pedir ajuste</button>
					<button type="submit" name="decisao" value="aprovar" class="apx-send">👍 Aprovar roteiro</button>
				</div>
			</form>
		<?php endif; ?>
		<?php
		$all = lk_rows( 'scripts', 'client_id = %d AND id <> %d', array( (int) $s->client_id, (int) $s->id ), 'record_date IS NULL, record_date, id' );
		$lab = lk_script_status_labels();
		?>
		<?php if ( $all ) : ?>
			<section class="apx-hist" style="margin-top:30px">
				<p class="apx-hist-t">Todos os roteiros e vídeos de <?php echo esc_html( lk_client_label( $client ) ); ?></p>
				<ul style="list-style:none;margin:0;padding:0;display:grid;gap:10px">
					<?php foreach ( $all as $o ) : ?>
						<li style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px 14px;border:1px solid rgba(128,128,128,.25);border-radius:12px">
							<span><strong><?php echo esc_html( $o->title ); ?></strong><br><small class="muted"><?php echo $o->record_date ? '📅 ' . esc_html( lk_date( $o->record_date, 'd/m/Y' ) . ( $o->record_time ? ' às ' . $o->record_time : '' ) ) : 'Sem dia de gravação'; ?> · <?php echo esc_html( $lab[ $o->status ] ?? $o->status ); ?></small></span>
							<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_script_url( $o ) ); ?>">Abrir</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
	</main>
	<footer class="apx-foot"><?php echo lk_credit_html( 'dark' ); // phpcs:ignore ?></footer>
</div>
</body>
</html>
