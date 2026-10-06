<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s   = ap_settings();
$sec = function ( $key ) use ( $s ) {
	return $s[ $key ] ? '•••••••• (salva; deixe em branco para manter)' : '';
};
ap_panel_start( 'Configurações', 'config' );
?>
<nav class="config-nav">
	<a href="#marca">Marca</a><a href="#producao">Etapas e funil</a><a href="#preco">Preço</a><a href="#pagamento">Pagamento</a><a href="#frete">Frete</a><a href="#canais">Canais</a><a href="#email">E-mail</a><a href="#google">Google Drive</a><a href="#ia">IA</a><a href="#formulario-site">Site</a><a href="#seguranca">Segurança</a>
</nav>

<?php ap_form( 'settings_save', 'wizard' ); ?>
	<section class="card step" id="marca">
		<div class="step-head"><span class="step-n">01</span><div><h3>Marca e contato</h3></div></div>
		<div class="grid-2">
			<?php ap_input( 'empresa', 'Nome', $s['empresa'] ); ?>
			<?php ap_input( 'site', 'Site', $s['site'], 'url' ); ?>
			<?php ap_input( 'logo', 'Logo para fundo escuro (opcional)', $s['logo'], 'url', 'placeholder="Vazio = logo branca do sistema"' ); ?>
			<?php ap_input( 'logo_cor', 'Logo para fundo claro (opcional)', $s['logo_cor'], 'url', 'placeholder="Vazio = logo colorida do sistema"' ); ?>
			<?php ap_input( 'logo_icone', 'Ícone (opcional)', $s['logo_icone'], 'url' ); ?>
			<?php ap_input( 'favicon', 'Favicon (vazio = ícone do sistema)', $s['favicon'], 'url' ); ?>
			<?php ap_input( 'whatsapp', 'WhatsApp', $s['whatsapp'], 'tel', 'data-mask="phone"' ); ?>
			<?php ap_input( 'email', 'E-mail (recebe os avisos)', $s['email'], 'email' ); ?>
			<?php ap_input( 'instagram', 'Instagram', $s['instagram'] ); ?>
		</div>
		<?php
		$tc = ap_theme_colors();
		$ct = ap_contrast_report();
		$bad = count( array_filter( $ct, function ( $r ) { return ! $r['ok']; } ) );
		?>
		<h4 class="brand-h">Cores do tema</h4>
		<p class="muted small">Troque as cores e salve: o sistema recalcula sozinho a cor dos textos, dos botões e a versão da logo de cada fundo para manter a leitura (contraste mínimo 4,5:1 no texto e 3:1 em ícones e barras).</p>
		<div class="brand-colors">
			<?php foreach ( array( 'accent' => 'Destaque (botões, indicadores)', 'ink' => 'Principal (textos e telas escuras)', 'side' => 'Menu lateral', 'bg' => 'Fundo do painel' ) as $ck => $cl ) : ?>
				<label class="field brand-color"><span><?php echo esc_html( $cl ); ?></span><span class="brand-pick"><input type="color" name="cor_<?php echo esc_attr( $ck ); ?>" value="<?php echo esc_attr( $tc[ $ck ] ); ?>"><code><?php echo esc_html( strtoupper( $tc[ $ck ] ) ); ?></code></span></label>
			<?php endforeach; ?>
		</div>
		<?php ap_check( 'cor_reset', 'Voltar às cores padrão do sistema' ); ?>
		<div class="brand-proof">
			<?php foreach ( ap_surfaces() as $sk => $sf ) : ?>
				<div class="brand-surf" style="background:<?php echo esc_attr( $sf[1] ); ?>"><?php echo ap_logo_img( $sf[1], $sf[1], '', '' ); // phpcs:ignore ?><small style="color:<?php echo esc_attr( ap_on_color( $sf[1] ) ); ?>"><?php echo esc_html( $sf[0] . ' · logo ' . ap_logo_variant( $sf[1] ) ); ?></small></div>
			<?php endforeach; ?>
		</div>
		<details class="brand-report"<?php echo $bad ? ' open' : ''; ?>>
			<summary><?php echo $bad ? esc_html( $bad . ' combinação(ões) abaixo do mínimo' ) : esc_html( count( $ct ) . ' combinações de cor testadas: todas passam' ); ?></summary>
			<table class="table"><thead><tr><th>Tema</th><th>Combinação</th><th>Contraste</th><th></th></tr></thead><tbody>
			<?php foreach ( $ct as $r ) : ?>
				<tr><td><?php echo 'dark' === $r['mode'] ? 'Escuro' : 'Claro'; ?></td><td><?php echo esc_html( $r['label'] ); ?></td><td><?php echo esc_html( number_format( $r['ratio'], 2, ',', '' ) . ':1 (mín. ' . number_format( $r['min'], 1, ',', '' ) . ')' ); ?></td><td><?php echo $r['ok'] ? '✓' : '✗'; ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</details>
		<?php ap_input( 'retirada_texto', 'Como funciona a retirada (aparece no orçamento e no e-mail de pronto)', $s['retirada_texto'], 'textarea', 'rows="2"' ); ?>
	</section>

	<section class="card step" id="producao">
		<div class="step-head"><span class="step-n">02</span><div><h3>Etapas dos pedidos e funil</h3><p class="muted small">Uma por linha, na ordem. A primeira recebe os pedidos aguardando pagamento; quando o pagamento cai, o pedido vai sozinho para a segunda.</p></div></div>
		<div class="grid-3">
			<?php ap_input( 'colunas', 'Etapas dos pedidos', $s['colunas'], 'textarea', 'rows="8" class="mono"' ); ?>
			<?php ap_input( 'funil', 'Etapas do funil de vendas', $s['funil'], 'textarea', 'rows="8" class="mono"' ); ?>
			<?php ap_input( 'origens', 'De onde vêm os clientes', $s['origens'], 'textarea', 'rows="8" class="mono"' ); ?>
		</div>
		<div class="grid-2">
			<?php ap_select( 'coluna_pronto', 'Etapa "pronto" (dispara o e-mail e o botão de WhatsApp)', ap_columns(), ap_ready_column() ); ?>
			<?php ap_input( 'aviso_dias', 'Pedidos: avisar prazo com quantos dias', $s['aviso_dias'], 'number', 'min="1"' ); ?>
		</div>
	</section>

	<section class="card step" id="preco">
		<div class="step-head"><span class="step-n">03</span><div><h3>Preço e pedido</h3><p class="muted small">Regras que valem para o pedido online e para a proposta. Os preços por material ficam em "Tabela de preços".</p></div></div>
		<div class="grid-4">
			<?php ap_input( 'area_minima', 'Mínimo por material (m²)', $s['area_minima'], 'text', 'inputmode="decimal"' ); ?>
			<?php ap_input( 'preco_ilhos', 'Reforço e ilhós (R$ por lado)', $s['preco_ilhos'], 'text', 'inputmode="decimal"' ); ?>
			<?php ap_input( 'preco_laminacao', 'Laminação (R$ por m²)', $s['preco_laminacao'], 'text', 'inputmode="decimal"' ); ?>
			<?php ap_input( 'prazo_dias', 'Prazo padrão (dias úteis)', $s['prazo_dias'], 'number', 'min="0"' ); ?>
			<?php ap_input( 'mao_obra_hora', 'Sua hora de trabalho (R$)', $s['mao_obra_hora'], 'text', 'inputmode="decimal"' ); ?>
			<?php ap_input( 'pedido_minimo', 'Pedido mínimo (R$)', $s['pedido_minimo'], 'text', 'inputmode="decimal"' ); ?>
		</div>
		<?php ap_input( 'empresa_nota', 'Observação para empresas e terceirizados (aparece na proposta)', $s['empresa_nota'] ); ?>
	</section>

	<section class="card step" id="pagamento">
		<div class="step-head"><span class="step-n">04</span><div><h3>Pagamento (InfinitePay)</h3><p class="muted small">Links de pagamento com Pix ou cartão. A baixa é automática quando o cliente paga.</p></div></div>
		<div class="grid-4">
			<?php ap_input( 'infinitepay', 'InfiniteTag (sem o $)', $s['infinitepay'], 'text', 'placeholder="sua-infinitetag"' ); ?>
			<?php ap_input( 'desconto_pix', 'Desconto no Pix/à vista (%)', $s['desconto_pix'], 'text', 'inputmode="decimal"' ); ?>
			<?php ap_input( 'parcelas_max', 'Cartão em até (vezes)', $s['parcelas_max'], 'number', 'min="1" max="12"' ); ?>
			<?php ap_input( 'validade_dias', 'Validade da proposta (dias)', $s['validade_dias'], 'number', 'min="1"' ); ?>
		</div>
		<input type="hidden" name="parcelas_sem_juros" value="<?php echo esc_attr( $s['parcelas_max'] ); ?>">
		<input type="hidden" name="infinitepay_token" value="<?php echo esc_attr( $s['infinitepay_token'] ); ?>">
		<div class="grid-2">
			<?php ap_select( 'cobranca_auto', 'Lembrete de pagamento por e-mail', array( '1' => 'Ligado', '0' => 'Desligado' ), $s['cobranca_auto'] ); ?>
			<?php ap_input( 'cobranca_dias', 'Quantos dias antes e depois do vencimento', $s['cobranca_dias'], 'number', 'min="1" max="15"' ); ?>
		</div>
		<p class="muted small">Importante no app da InfinitePay: (1) ative o <strong>Checkout externo</strong> (sem ele o link não abre); (2) em Parcelamento, deixe no máximo <?php echo (int) $s['parcelas_max']; ?>x e sem juros para o cliente. O link da InfinitePay não deixa o painel limitar as parcelas; quem manda é essa configuração. Webhook: <code><?php echo esc_html( rest_url( 'ap/v1/infinitepay' ) ); ?></code></p>
	</section>

	<section class="card step" id="frete">
		<div class="step-head"><span class="step-n">05</span><div><h3>Frete (Melhor Envio)</h3><p class="muted small">Crie uma conta grátis em melhorenvio.com.br → Configurações → Tokens → gere um token com permissão de "cálculo de frete".</p></div></div>
		<div class="grid-3">
			<?php ap_input( 'melhorenvio_token', 'Token do Melhor Envio', '', 'password', 'autocomplete="new-password" placeholder="' . esc_attr( $sec( 'melhorenvio_token' ) ) . '"' ); ?>
			<?php ap_input( 'cep_origem', 'CEP de origem (de onde sai)', $s['cep_origem'], 'text', 'inputmode="numeric" placeholder="12000-000"' ); ?>
			<?php ap_select( 'melhorenvio_sandbox', 'Ambiente', array( '0' => 'Produção', '1' => 'Teste (sandbox)' ), $s['melhorenvio_sandbox'] ); ?>
		</div>
		<?php ap_input( 'caixas', 'Suas caixas: Nome | comprimento | largura | altura (cm)', $s['caixas'], 'textarea', 'rows="4" class="mono"' ); ?>
	</section>

	<section class="card step" id="canais">
		<div class="step-head"><span class="step-n">06</span><div><h3>Categorias financeiras</h3><p class="muted small">Listas usadas nos lançamentos do financeiro.</p></div></div>
		<div class="grid-3">
			<?php ap_input( 'categorias_in', 'Entradas', $s['categorias_in'], 'textarea', 'rows="5" class="mono"' ); ?>
			<?php ap_input( 'categorias_out', 'Saídas', $s['categorias_out'], 'textarea', 'rows="5" class="mono"' ); ?>
			<?php ap_input( 'metodos', 'Formas de pagamento', $s['metodos'], 'textarea', 'rows="5" class="mono"' ); ?>
		</div>
	</section>

	<section class="card step" id="email">
		<div class="step-head"><span class="step-n">07</span><div><h3>E-mail (SMTP)</h3><p class="muted small">Os dados estão no painel da hospedagem, em E-mails. Assim os e-mails saem do seu endereço e não caem no spam.</p></div></div>
		<div class="grid-3">
			<?php ap_input( 'smtp_host', 'Servidor SMTP', $s['smtp_host'], 'text', 'placeholder="mail.seudominio.com.br"' ); ?>
			<?php ap_input( 'smtp_port', 'Porta', $s['smtp_port'], 'number', 'placeholder="465"' ); ?>
			<?php ap_select( 'smtp_secure', 'Segurança', array( 'ssl' => 'SSL (porta 465)', 'tls' => 'TLS (porta 587)', '' => 'Nenhuma' ), $s['smtp_secure'] ); ?>
			<?php ap_input( 'smtp_user', 'Usuário (e-mail completo)', $s['smtp_user'], 'email', 'placeholder="contato@seudominio.com.br" autocomplete="off"' ); ?>
			<?php ap_input( 'smtp_pass', 'Senha do e-mail', '', 'password', 'autocomplete="new-password" placeholder="' . esc_attr( $sec( 'smtp_pass' ) ) . '"' ); ?>
			<?php ap_input( 'mail_from_name', 'Nome do remetente', $s['mail_from_name'] ); ?>
		</div>
		<?php ap_input( 'mail_from', 'E-mail do remetente (se for diferente do usuário)', $s['mail_from'], 'email' ); ?>
		<div class="grid-2">
			<?php foreach ( ap_email_types() as $key => $label ) : ?>
				<?php ap_select( $key, $label, array( '1' => 'Enviar', '0' => 'Não enviar' ), $s[ $key ] ); ?>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="card step" id="google">
		<div class="step-head"><span class="step-n">08</span><div><h3>Google Drive</h3><p class="muted small">Tudo que você sobe (artes, fotos e arquivos dos clientes) é copiado para o seu Drive, organizado em Orçamentos / Pedidos / Produtos.</p></div></div>
		<?php $g = ap_google_state(); ?>
		<?php if ( ap_google_connected() ) : ?>
			<div class="flash">Conectado<?php echo ! empty( $g['email'] ) ? ' como ' . esc_html( $g['email'] ) : ''; ?>.</div>
		<?php else : ?>
			<details class="add-details" open>
				<summary class="btn btn--ghost btn--sm">Como conectar (uma vez só, ~10 minutos)</summary>
				<ol class="howto">
					<li>Acesse <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console.cloud.google.com</a> e crie um projeto (ex.: "Painel Cardon").</li>
					<li>Em <strong>APIs e serviços → Biblioteca</strong>, ative a <strong>Google Drive API</strong>.</li>
					<li>Em <strong>Tela de consentimento OAuth</strong>, escolha "Externo", preencha nome e e-mail e clique em <strong>Publicar app</strong> (em "Teste" a conexão expira a cada 7 dias).</li>
					<li>Em <strong>Credenciais → Criar credenciais → ID do cliente OAuth</strong> (Aplicativo da Web), cole em "URIs de redirecionamento": <code><?php echo esc_html( ap_google_redirect_uri() ); ?></code></li>
					<li>Copie o ID e a chave secreta para os campos abaixo, salve e clique em <strong>Conectar Google</strong> no fim da página.</li>
				</ol>
			</details>
		<?php endif; ?>
		<div class="grid-3">
			<?php ap_input( 'google_client_id', 'Client ID', $s['google_client_id'], 'text', 'autocomplete="off"' ); ?>
			<?php ap_input( 'google_client_secret', 'Client Secret', '', 'password', 'autocomplete="new-password" placeholder="' . esc_attr( $sec( 'google_client_secret' ) ) . '"' ); ?>
			<?php ap_input( 'google_pasta', 'Pasta principal no Drive', $s['google_pasta'], 'text', 'placeholder="' . esc_attr( $s['empresa'] ) . '"' ); ?>
		</div>
	</section>

	<section class="card step" id="seguranca">
		<div class="step-head"><span class="step-n">09</span><div><h3>Segurança</h3></div></div>
		<?php ap_select( 'seg_2fa', 'Verificação em duas etapas (código por e-mail no login da equipe)', array( '1' => 'Ligada (recomendado)', '0' => 'Desligada' ), $s['seg_2fa'] ); ?>
		<p class="muted small">Se um dia ficar sem acesso ao e-mail, coloque <code>define( 'AP_DISABLE_2FA', true );</code> no wp-config.php pela hospedagem.</p>
	</section>

	<section class="card step" id="apontamentos">
		<div class="step-head"><span class="step-n">11</span><div><h3>Apontamentos</h3><p class="muted small">Botão "Apontar" no canto das telas: quem está testando clica num ponto e escreve o que quer mudar. Tudo cai em <a href="<?php echo esc_url( ap_panel_url( 'apontamentos' ) ); ?>">Apontamentos</a>. Desligue quando o sistema estiver aprovado.</p></div></div>
		<div class="grid-2">
			<?php ap_select( 'apontamentos', 'Botão Apontar para a equipe', array( '1' => 'Ligado', '0' => 'Desligado' ), $s['apontamentos'] ); ?>
			<?php ap_select( 'apontamentos_clientes', 'Botão Apontar na área do cliente', array( '0' => 'Desligado', '1' => 'Ligado (os clientes também apontam)' ), $s['apontamentos_clientes'] ); ?>
			<?php ap_input( 'apontamentos_email', 'E-mail que recebe o aviso (vazio = e-mail do administrador do WordPress)', $s['apontamentos_email'], 'email' ); ?>
		</div>
	</section>

	<div class="form-actions form-actions--sticky"><button type="submit" class="btn btn--primary">Salvar configurações</button></div>
</form>

<section class="card">
	<div class="card-head"><h3>Conta Google</h3></div>
	<?php if ( ap_google_connected() ) : ?>
		<?php ap_action_button( 'google_disconnect', array(), 'Desconectar Google', 'btn btn--danger btn--sm', 'Desconectar o Drive? Os arquivos que já estão lá continuam.' ); ?>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="inline-form"><input type="hidden" name="action" value="ap_google_connect"><?php wp_nonce_field( 'ap_google_connect' ); ?><button type="submit" class="btn btn--primary"<?php echo $s['google_client_id'] ? '' : ' disabled title="Preencha e salve o Client ID primeiro"'; ?>>Conectar Google Drive</button></form>
	<?php endif; ?>
</section>

<section class="card" id="planilha-google">
	<div class="card-head"><h3>Planilha do Google (pedidos)</h3><?php $sh = ap_sheets_state(); ?><?php if ( ! empty( $sh['id'] ) ) : ?><em class="badge badge--ok">sincronizando</em><?php endif; ?></div>
	<p class="muted small">Cria uma planilha na sua conta Google com todos os pedidos (uma linha por item, no mesmo formato da planilha de controle) , uma aba de clientes e uma aba de custos (todas as saídas do financeiro, inclusive veículos). Cada mudança no sistema, como pedido novo, etapa, pagamento ou entrega, atualiza as linhas dela sozinha. Os arquivos dos clientes ficam no Google Drive, numa pasta por cliente e por pedido.</p>
	<?php if ( ! ap_google_connected() ) : ?>
		<p class="flash flash--warn">Conecte o Google acima primeiro.</p>
	<?php elseif ( ! ap_sheets_scope_ok() ) : ?>
		<p class="flash flash--warn">O Google está conectado, mas sem permissão para planilhas. Clique em "Desconectar Google" e conecte de novo (um clique) para liberar.</p>
	<?php elseif ( ! empty( $sh['id'] ) ) : ?>
		<p><a class="btn btn--primary btn--sm" href="<?php echo esc_url( $sh['url'] ); ?>" target="_blank" rel="noopener">Abrir a planilha</a>
		<?php ap_action_button( 'sheets_create', array(), 'Sincronizar tudo de novo', 'btn btn--ghost btn--sm', 'Reescrever a planilha inteira com os dados do sistema?' ); ?>
		<?php ap_action_button( 'sheets_off', array(), 'Desligar', 'btn btn--link btn--sm', 'Parar de atualizar a planilha?' ); ?></p>
		<p class="muted small"><?php echo ! empty( $sh['last_sync'] ) ? 'Última atualização: ' . esc_html( ap_ago( $sh['last_sync'] ) ) . '. ' : ''; ?><?php echo ! empty( $sh['rows'] ) ? (int) $sh['rows'] . ' linhas na carga inicial.' : ''; ?></p>
		<?php if ( ! empty( $sh['error'] ) ) : ?><p class="flash flash--warn">Última tentativa falhou: <?php echo esc_html( $sh['error'] ); ?> (o sistema tenta de novo de hora em hora).</p><?php endif; ?>
	<?php else : ?>
		<?php ap_action_button( 'sheets_create', array(), 'Criar a planilha e enviar os pedidos', 'btn btn--primary', '' ); ?>
	<?php endif; ?>
</section>

<section class="card" id="formulario-site">
	<div class="card-head"><h3>Formulários do site → funil</h3></div>
	<p class="muted small">Quem preencher um formulário no <?php echo esc_html( preg_replace( '#^https?://#', '', $s['site'] ) ); ?> entra no Funil como lead (origem "Site"), com lembrete para responder hoje.</p>
	<div class="copy-row"><input type="text" readonly value="<?php echo esc_attr( ap_site_form_url() ); ?>" onclick="this.select()"><button type="button" class="btn btn--ghost" data-copy="<?php echo esc_attr( ap_site_form_url() ); ?>"><?php echo ap_icon( 'copiar', 16 ); // phpcs:ignore ?><span>Copiar</span></button></div>
	<details class="howto">
		<summary>Como ligar no Elementor</summary>
		<ol>
			<li>Abra a página no Elementor e clique no <strong>Formulário</strong>.</li>
			<li>Em <strong>Ações após o envio</strong>, adicione <strong>Webhook</strong> (mantenha as ações que já existem).</li>
			<li>Cole a URL acima, atualize e faça um envio de teste: o lead aparece no Funil.</li>
		</ol>
	</details>
	<?php ap_action_button( 'site_form_token', array(), 'Gerar chave nova', 'btn btn--ghost btn--sm', 'Gerar uma chave nova? A URL muda.' ); ?>
</section>

<section class="card">
	<div class="card-head"><h3>Enviar e-mail de teste</h3></div>
	<?php ap_form( 'mail_test', 'copy-row' ); ?>
		<input type="email" name="to" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" placeholder="Para qual e-mail?">
		<button type="submit" class="btn btn--primary">Enviar teste</button>
	</form>
</section>

<section class="card" id="seguranca-status">
	<div class="card-head"><h3>Segurança: checklist</h3></div>
	<ul class="sec-checks">
		<?php foreach ( ap_security_checks() as $c ) : ?>
			<li class="<?php echo $c[0] ? 'is-ok' : 'is-bad'; ?>"><?php echo $c[0] ? '✓' : '✗'; ?> <?php echo esc_html( $c[1] ); ?><?php if ( ! $c[0] ) : ?> <small class="muted">— <?php echo esc_html( $c[2] ); ?></small><?php endif; ?></li>
		<?php endforeach; ?>
	</ul>
</section>
<?php
ap_panel_end();
