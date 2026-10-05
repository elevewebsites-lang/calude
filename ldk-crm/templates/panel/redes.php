<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
lk_panel_start( 'Redes conectadas', 'redes' );
?>
<details class="card howto" <?php echo lk_setting( 'ig_app_id' ) ? '' : 'open'; ?>>
	<summary><strong>Passo a passo: conectar o Instagram de um cliente</strong></summary>
	<ol>
		<li><strong>Uma vez só (a LDK):</strong> em <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">developers.facebook.com</a>, crie um app do tipo "Empresa" e adicione o produto <strong>Instagram → API com login do Instagram</strong>. Copie o <em>ID do app do Instagram</em> e a <em>chave secreta</em> para Configurações → Redes sociais. Em "URIs de redirecionamento OAuth válidos", cole: <code><?php echo esc_html( lk_social_redirect( 'instagram' ) ); ?></code></li>
		<li><strong>No Instagram do cliente:</strong> confira se a conta é <strong>Profissional</strong> (Configurações → Tipo de conta → Comercial ou Criador de conteúdo).</li>
		<li><strong>No app da Meta:</strong> Funções do app → Funções → <strong>Testadores do Instagram</strong> → adicione o @ do cliente.</li>
		<li><strong>Logado no Instagram do cliente</strong> (navegador ou app): Configurações → Apps e sites → <strong>Convites de testador</strong> → Aceitar.</li>
		<li>Aqui no painel, abra o cliente → <strong>Redes sociais → Conectar Instagram</strong> e entre com a conta dele. Pronto: posts e relatórios automáticos.</li>
	</ol>
	<p class="muted small">Facebook: mesmo app, produto "Login do Facebook para empresas". A pessoa que conecta precisa administrar a Página do cliente. Redirecionamento: <code><?php echo esc_html( lk_social_redirect( 'facebook' ) ); ?></code>.</p>
</details>
<details class="card howto">
	<summary><strong>Passo a passo: LinkedIn (Página de empresa)</strong></summary>
	<ol>
		<li><strong>Uma vez só (a LDK):</strong> em <a href="https://developer.linkedin.com" target="_blank" rel="noopener">developer.linkedin.com</a>, crie um app ligado à Página da LDK e peça o produto <strong>Community Management API</strong> (o LinkedIn analisa o pedido). Em Auth, cole o redirecionamento: <code><?php echo esc_html( lk_social_redirect( 'linkedin' ) ); ?></code>. Copie o Client ID e o Secret para Configurações → Redes sociais.</li>
		<li>Quem conecta precisa ser <strong>administrador da Página</strong> do cliente no LinkedIn (o cliente pode adicionar alguém da LDK como admin).</li>
		<li>Na ficha do cliente → Redes sociais → <strong>Conectar LinkedIn</strong>. Imagens, carrossel e vídeo saem sozinhos no horário.</li>
	</ol>
	<p class="muted small">Enquanto o LinkedIn não aprova o app, posts marcados para o LinkedIn continuam como lembrete manual.</p>
</details>
<details class="card howto">
	<summary><strong>Passo a passo: YouTube e Google Meu Negócio</strong></summary>
	<ol>
		<li><strong>Uma vez só (a LDK):</strong> no mesmo projeto do Google Cloud do Drive, ative as APIs <strong>YouTube Data API v3</strong> e <strong>Google My Business API</strong> (+ Business Profile Performance). Na tela de consentimento, adicione os escopos <code>youtube.upload</code>, <code>youtube.readonly</code> e <code>business.manage</code>. Em Credenciais, adicione o redirecionamento: <code><?php echo esc_html( lk_social_redirect( 'google' ) ); ?></code></li>
		<li><strong>Google Meu Negócio:</strong> a API precisa ser liberada pelo Google: preencha o formulário "Business Profile API access request" com o projeto da LDK (sem isso a conexão dá erro de cota 0).</li>
		<li><strong>YouTube:</strong> enquanto o app do Google não passar pela verificação (auditoria da YouTube API), os vídeos enviados pela API ficam <strong>privados</strong>. Depois de verificado, saem públicos.</li>
		<li>Na ficha do cliente → Redes sociais → <strong>Conectar YouTube</strong> / <strong>Conectar Google Meu Negócio</strong>, entrando com a conta Google do cliente (ou uma conta com acesso de gerente ao canal/perfil).</li>
	</ol>
	<p class="muted small">YouTube recebe só posts com vídeo (Reels/vídeo viram vídeo ou Shorts). Google Meu Negócio recebe a legenda + a primeira imagem.</p>
</details>
<div class="table table--redes">
	<div class="table-row table-head"><span>Cliente</span><?php foreach ( lk_networks() as $nl ) : ?><span><?php echo esc_html( $nl ); ?></span><?php endforeach; ?><span></span></div>
	<?php foreach ( lk_clients() as $c ) : ?>
		<?php $accs = lk_social_accounts( $c->id ); ?>
		<a class="table-row" href="<?php echo esc_url( lk_panel_url( 'cliente', $c->id ) ); ?>#redes"><span class="cell-main"><strong><?php echo esc_html( lk_client_label( $c ) ); ?></strong></span>
			<?php foreach ( array_keys( lk_networks() ) as $n ) : ?><?php $a = $accs[ $n ] ?? null; ?><span><?php echo $a ? '<em class="badge ' . ( 'ok' === $a->status ? 'badge--ok' : 'badge--late' ) . '">' . esc_html( $a->username ? '@' . $a->username : $a->name ) . '</em>' : '<em class="badge badge--off">não vinculado</em>'; ?></span><?php endforeach; ?>
			<span class="cell-arrow"><?php echo lk_icon( 'seta', 16 ); // phpcs:ignore ?></span></a>
	<?php endforeach; ?>
</div>
<?php
lk_panel_end();
