<?php
/**
 * Envio de fotos e arquivos pelo cliente: /fotos/<token>/ (sem login).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
lk_head( 'Enviar fotos · ' . lk_client_label( $c ) );
?>
<body class="lk ap-page">
<div class="apv pln">
	<header class="apv-top"><?php if ( lk_setting( 'logo' ) ) : ?><img src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt="<?php echo esc_attr( lk_setting( 'empresa' ) ); ?>"><?php endif; ?><span><?php echo esc_html( lk_client_label( $c ) ); ?></span></header>
	<section class="pln-hero">
		<span class="apv-eyebrow">Fotos e arquivos</span>
		<h1>Envie as suas fotos para a gente</h1>
		<p>Mande fotos, vídeos e arquivos aqui. Tudo chega direto para a equipe e fica guardado com segurança. Se for de um evento ou de uma campanha, escolha uma pasta para separar.</p>
	</section>
	<section class="card cf" data-cfiles data-public data-rest="<?php echo esc_url( rest_url( 'lk/v1/cfile/' ) ); ?>" data-token="<?php echo esc_attr( $c->photos_token ); ?>">
		<div class="cf-row">
			<label class="field"><span>Essas fotos são de…</span>
				<select data-cf-folder>
					<option value="">Geral (sem pasta)</option>
					<option value="__novo">Um evento ou assunto específico</option>
				</select></label>
			<label class="field" data-cf-new hidden><span>Nome do evento ou assunto</span><input type="text" maxlength="60" placeholder="Ex.: Aniversário da loja"></label>
		</div>
		<label class="cf-drop" data-cf-drop><input type="file" multiple data-cf-input hidden><strong>Toque para escolher as fotos</strong><span class="muted small">ou arraste para cá. Pode mandar várias de uma vez.</span></label>
		<div class="cf-up" data-cf-list></div>
		<p class="cf-done" data-cf-done hidden>✓ Recebemos! Pode mandar mais se quiser.</p>
	</section>
	<footer class="apv-foot"><?php echo lk_credit_html( 'dark' ); // phpcs:ignore ?></footer>
</div>
<script src="<?php echo esc_url( LK_URL . 'assets/cliente-arquivos.js?ver=' . LK_VERSION ); ?>"></script>
</body>
</html>
