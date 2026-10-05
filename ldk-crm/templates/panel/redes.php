<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$ver   = isset( $_GET['ver'] ) && 'pendentes' === $_GET['ver'] ? 'pendentes' : 'todos'; // phpcs:ignore WordPress.Security.NonceVerification
$nets  = lk_connect_nets();
$rows  = array();
$count = array_fill_keys( array_keys( $nets ), 0 );
foreach ( lk_clients() as $c ) {
	$accs = lk_social_accounts( $c->id );
	$st   = array();
	$bad  = false;
	foreach ( $nets as $nk => $ni ) {
		$st[ $nk ] = lk_net_state( $accs, $nk );
		if ( 'ok' === $st[ $nk ][0] ) {
			$count[ $nk ]++;
		}
	}
	if ( 'ok' !== $st['instagram'][0] || 'ok' !== $st['facebook'][0] ) {
		$bad = true;
	}
	$rows[] = array( $c, $st, $bad );
}
$total   = count( $rows );
$show    = 'pendentes' === $ver ? array_filter( $rows, function ( $r ) { return $r[2]; } ) : $rows;
$pending = count( array_filter( $rows, function ( $r ) { return $r[2]; } ) );
lk_panel_start( 'Redes conectadas', 'redes' );
?>
<section class="card net-board">
	<div class="card-head"><h3>Status dos <?php echo (int) $total; ?> clientes</h3>
		<span class="chips"><a class="btn btn--sm btn--<?php echo 'todos' === $ver ? 'primary' : 'ghost'; ?>" href="<?php echo esc_url( lk_panel_url( 'redes' ) ); ?>">Todos (<?php echo (int) $total; ?>)</a> <a class="btn btn--sm btn--<?php echo 'pendentes' === $ver ? 'primary' : 'ghost'; ?>" href="<?php echo esc_url( lk_panel_url( 'redes', 0, array( 'ver' => 'pendentes' ) ) ); ?>">Só pendentes (<?php echo (int) $pending; ?>)</a></span></div>
	<div class="net-sum"><?php foreach ( $nets as $nk => $ni ) : ?><div><b><?php echo (int) $count[ $nk ]; ?></b><span>/ <?php echo (int) $total; ?> · <?php echo esc_html( $ni[0] ); ?></span><i><u style="width:<?php echo $total ? (int) round( 100 * $count[ $nk ] / $total ) : 0; ?>%"></u></i></div><?php endforeach; ?></div>
	<p class="muted small">Pendente = o cliente ainda não conectou. Use <strong>Copiar mensagem</strong> (ou o botão do WhatsApp) para mandar o link: ele entra na conta dele e autoriza, sem passar senha.</p>
	<div class="pros-wrap"><table class="pros-table net-table">
		<thead><tr><th>Cliente</th><?php foreach ( $nets as $nk => $ni ) : ?><th><?php echo esc_html( $ni[0] ); ?></th><?php endforeach; ?><th></th></tr></thead>
		<tbody>
		<?php foreach ( $show as $r ) : list( $c, $st ) = $r; $msg = lk_connect_message( $c ); ?>
			<tr>
				<td><a class="net-cli" href="<?php echo esc_url( lk_panel_url( 'cliente', $c->id ) ); ?>#redes"><?php echo lk_client_avatar_html( $c ); // phpcs:ignore ?><strong><?php echo esc_html( lk_client_label( $c ) ); ?></strong></a></td>
				<?php foreach ( $nets as $nk => $ni ) : ?><td><span class="net-pill net-pill--<?php echo esc_attr( $st[ $nk ][0] ); ?>"><?php echo esc_html( $st[ $nk ][1] ); ?></span></td><?php endforeach; ?>
				<td class="net-act"><button type="button" class="btn btn--ghost btn--sm" data-copy="<?php echo esc_attr( $msg ); ?>">Copiar mensagem</button><?php if ( $c->whatsapp ) : ?> <a class="btn btn--wa btn--sm" href="<?php echo esc_url( lk_wa_link( $c->whatsapp, $msg ) ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 14 ); // phpcs:ignore ?><span>WhatsApp</span></a><?php endif; ?></td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $show ) : ?><tr><td colspan="<?php echo count( $nets ) + 2; ?>" class="muted">Tudo conectado por aqui. 🎉</td></tr><?php endif; ?>
		</tbody>
	</table></div>
</section>
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
