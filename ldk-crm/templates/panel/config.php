<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s   = lk_settings();
$sec = function ( $key ) use ( $s ) {
	return $s[ $key ] ? '•••••••• (salva; deixe em branco para manter)' : '';
};
lk_panel_start( 'Configurações', 'config' );
?>
<nav class="config-nav">
	<a href="#modo">Modo de uso</a><a href="#marca">Marca</a><a href="#producao">Esteira e funil</a><a href="#redes">Redes e anúncios</a><a href="#pagamento">Pagamento</a><a href="#email">E-mail</a><a href="#google">Google</a><a href="#formulario-site">Site</a><a href="#seguranca">Segurança</a>
</nav>

<?php lk_form( 'settings_save', 'wizard' ); ?>
	<section class="card step" id="modo">
		<div class="step-head"><span class="step-n">00</span><div><h3>Modo de uso</h3><p class="muted small">Define se o sistema só gerencia o trabalho ou também publica nas redes.</p></div></div>
		<?php lk_select( 'modo_gestao', 'Como usar o CRM agora', array( '1' => 'Só gerenciamento: sem postar e sem vincular redes sociais (o agendamento é feito à mão no mLabs)', '0' => 'Completo: vincular Instagram/Facebook e publicar pelo sistema' ), $s['modo_gestao'] ); ?>
		<p class="muted small">No modo "só gerenciamento" ficam escondidos: vincular Instagram e outras redes, "Onde publicar" e "Publicar agora". A publicação automática fica parada. Cada post ganha o cartão <strong>Para agendar no mLabs</strong>, com a arte, a legenda e o botão para marcar como agendado.</p>
	</section>

	<section class="card step" id="marca">
		<div class="step-head"><span class="step-n">01</span><div><h3>Marca e contato</h3></div></div>
		<div class="grid-2">
			<?php lk_input( 'empresa', 'Nome', $s['empresa'] ); ?>
			<?php lk_input( 'site', 'Site', $s['site'], 'url' ); ?>
			<?php lk_input( 'logo', 'Logo branca (fundo escuro)', $s['logo'], 'url' ); ?>
			<?php lk_input( 'login_bg', 'Imagem de fundo da tela de login (endereço da imagem)', $s['login_bg'], 'url' ); ?>
			<?php lk_input( 'logo_icone', 'Ícone branco', $s['logo_icone'], 'url' ); ?>
			<?php lk_input( 'favicon', 'Favicon', $s['favicon'], 'url' ); ?>
			<?php lk_input( 'whatsapp', 'WhatsApp', $s['whatsapp'], 'tel', 'data-mask="phone"' ); ?>
			<?php lk_input( 'email', 'E-mail (recebe os avisos)', $s['email'], 'email' ); ?>
			<?php lk_input( 'instagram', 'Instagram', $s['instagram'] ); ?>
		</div>
		<?php lk_input( 'retirada_texto', 'Como funciona a retirada (aparece no orçamento e no e-mail de pronto)', $s['retirada_texto'], 'textarea', 'rows="2"' ); ?>
	</section>

	<section class="card step" id="producao">
		<div class="step-head"><span class="step-n">02</span><div><h3>Esteira de conteúdo e funil</h3><p class="muted small">Uma etapa por linha, na ordem. Você pode renomear e criar etapas; abaixo diga qual etapa faz o quê no fluxo automático.</p></div></div>
		<div class="grid-3">
			<?php lk_check( 'revisao_obrigatoria', 'Revisão do texto obrigatória antes de enviar ao cliente (confere português, contexto e limites do Instagram)', '1' === (string) ( $s['revisao_obrigatoria'] ?? '1' ) ); ?>
			<?php lk_input( 'etapas_conteudo', 'Etapas do conteúdo', $s['etapas_conteudo'], 'textarea', 'rows="8" class="mono"' ); ?>
			<?php lk_input( 'funil', 'Etapas do funil de vendas', $s['funil'], 'textarea', 'rows="8" class="mono"' ); ?>
			<?php lk_input( 'origens', 'De onde vêm os clientes', $s['origens'], 'textarea', 'rows="8" class="mono"' ); ?>
		</div>
		<?php $st = lk_stages(); ?>
		<div class="grid-3">
			<?php lk_select( 'etapa_planejamento', 'Etapa do planejamento (antes do cliente aprovar o mês)', $st, lk_stage_for( 'planejamento' ) ); ?>
			<?php lk_select( 'etapa_design', 'Etapa do designer (arte)', $st, lk_stage_for( 'design' ) ); ?>
			<?php lk_select( 'etapa_revisao', 'Etapa de revisão (avisa o revisor do cliente)', $st, lk_stage_for( 'revisao' ) ); ?>
			<?php lk_select( 'etapa_aprovacao', 'Etapa com o cliente (aprovação)', $st, lk_stage_for( 'aprovacao' ) ); ?>
			<?php lk_select( 'etapa_agendado', 'Aprovado → agendado (publica sozinho)', $st, lk_stage_for( 'agendado' ) ); ?>
			<?php lk_select( 'etapa_publicado', 'Publicado', $st, lk_stage_for( 'publicado' ) ); ?>
			<?php lk_input( 'assinatura_antecedencia', 'Gerar a mensalidade quantos dias antes', $s['assinatura_antecedencia'], 'number', 'min="1" max="20"' ); ?>
		</div>
		<div class="grid-3" style="margin-top:14px">
			<?php lk_select( 'revisor_planejamento', 'Quem revisa o planejamento antes de ir para a cliente', array( '' => 'O primeiro administrador' ) + lk_team_options(), $s['revisor_planejamento'] ); ?>
		</div>
		<p class="small" style="margin:16px 0 6px"><strong>Prazos de entrega</strong> <span class="muted">(quantos dias antes da publicação cada etapa precisa estar pronta; dá para mudar em cada post)</span></p>
		<div class="grid-4">
			<?php lk_input( 'prazo_design', 'Design (dias antes)', $s['prazo_design'], 'number', 'min="0" max="60"' ); ?>
			<?php lk_input( 'prazo_revisao', 'Revisão (dias antes)', $s['prazo_revisao'], 'number', 'min="0" max="60"' ); ?>
			<?php lk_input( 'prazo_aprovacao', 'Aprovação do cliente (dias antes)', $s['prazo_aprovacao'], 'number', 'min="0" max="60"' ); ?>
			<?php lk_input( 'prazo_hora', 'Horário do prazo', $s['prazo_hora'], 'time' ); ?>
		</div>
		<?php lk_input( 'briefing_perguntas', 'Perguntas do briefing do cliente novo (uma por linha)', $s['briefing_perguntas'], 'textarea', 'rows="10"' ); ?>
	</section>

	<section class="card step" id="redes">
		<div class="step-head"><span class="step-n">03</span><div><h3>Instagram, Facebook e Meta Ads</h3><p class="muted small">Um app só na Meta (developers.facebook.com), em modo desenvolvimento, com o Instagram de cada cliente como testador. Passo a passo completo em <a href="<?php echo esc_url( lk_panel_url( 'redes' ) ); ?>">Redes conectadas</a>.</p></div></div>
		<div class="grid-2">
			<?php lk_input( 'ig_app_id', 'ID do app do Instagram', $s['ig_app_id'], 'text', 'autocomplete="off"' ); ?>
			<?php lk_input( 'ig_app_secret', 'Chave secreta do app do Instagram', '', 'password', 'autocomplete="new-password" placeholder="' . esc_attr( $sec( 'ig_app_secret' ) ) . '"' ); ?>
			<?php lk_input( 'meta_app_id', 'ID do app (Facebook)', $s['meta_app_id'], 'text', 'autocomplete="off"' ); ?>
			<?php lk_input( 'meta_app_secret', 'Chave secreta do app (Facebook)', '', 'password', 'autocomplete="new-password" placeholder="' . esc_attr( $sec( 'meta_app_secret' ) ) . '"' ); ?>
		</div>
		<p class="muted small">Redirecionamento Instagram: <code><?php echo esc_html( lk_social_redirect( 'instagram' ) ); ?></code><br>Redirecionamento Facebook: <code><?php echo esc_html( lk_social_redirect( 'facebook' ) ); ?></code></p>
		<?php lk_input( 'meta_ads_token', 'Token do Meta Ads (usuário do sistema do Business, permissão ads_read)', '', 'password', 'autocomplete="new-password" placeholder="' . esc_attr( $sec( 'meta_ads_token' ) ) . '"' ); ?>
		<p class="muted small">Google Ads: lance os números do mês em Tráfego pago até a Google liberar o token de desenvolvedor.</p>
		<p class="small" style="margin:16px 0 6px"><strong>LinkedIn (Páginas de empresa)</strong> <span class="muted">app em developer.linkedin.com com o produto "Community Management API"</span></p>
		<div class="grid-2">
			<?php lk_input( 'linkedin_client_id', 'Client ID do LinkedIn', $s['linkedin_client_id'], 'text', 'autocomplete="off"' ); ?>
			<?php lk_input( 'linkedin_client_secret', 'Client Secret do LinkedIn', '', 'password', 'autocomplete="new-password" placeholder="' . esc_attr( $sec( 'linkedin_client_secret' ) ) . '"' ); ?>
		</div>
		<p class="muted small">Redirecionamento LinkedIn: <code><?php echo esc_html( lk_social_redirect( 'linkedin' ) ); ?></code><br>YouTube e Google Meu Negócio usam o app do Google (passo 02). Adicione este redirecionamento lá: <code><?php echo esc_html( lk_social_redirect( 'google' ) ); ?></code></p>
	</section>

	<section class="card step" id="pagamento">
		<div class="step-head"><span class="step-n">04</span><div><h3>Pagamento (InfinitePay)</h3><p class="muted small">Links de pagamento com Pix ou cartão. A baixa é automática quando o cliente paga.</p></div></div>
		<div class="grid-4">
			<?php lk_input( 'infinitepay', 'InfiniteTag (sem o $)', $s['infinitepay'], 'text', 'placeholder="ldkmarketing"' ); ?>
			<?php lk_input( 'desconto_pix', 'Desconto no Pix/à vista (%)', $s['desconto_pix'], 'text', 'inputmode="decimal"' ); ?>
			<?php lk_input( 'parcelas_max', 'Cartão em até (vezes)', $s['parcelas_max'], 'number', 'min="1" max="12"' ); ?>
			<?php lk_input( 'validade_dias', 'Validade do orçamento (dias)', $s['validade_dias'], 'number', 'min="1"' ); ?>
		</div>
		<input type="hidden" name="parcelas_sem_juros" value="<?php echo esc_attr( $s['parcelas_max'] ); ?>">
		<input type="hidden" name="infinitepay_token" value="<?php echo esc_attr( $s['infinitepay_token'] ); ?>">
		<div class="grid-2">
			<?php lk_select( 'cobranca_auto', 'Lembrete de pagamento por e-mail', array( '1' => 'Ligado', '0' => 'Desligado' ), $s['cobranca_auto'] ); ?>
			<?php lk_input( 'cobranca_dias', 'Quantos dias antes e depois do vencimento', $s['cobranca_dias'], 'number', 'min="1" max="15"' ); ?>
		</div>
		<p class="muted small">Importante no app da InfinitePay: (1) ative o <strong>Checkout externo</strong> (sem ele o link não abre); (2) em Parcelamento, deixe no máximo <?php echo (int) $s['parcelas_max']; ?>x e sem juros para o cliente. O link da InfinitePay não deixa o painel limitar as parcelas; quem manda é essa configuração. Webhook: <code><?php echo esc_html( rest_url( 'lk/v1/infinitepay' ) ); ?></code></p>
	</section>

	<section class="card step" id="email">
		<div class="step-head"><span class="step-n">05</span><div><h3>E-mail (SMTP)</h3><p class="muted small">Os dados estão no painel da hospedagem, em E-mails. Assim os e-mails saem do seu endereço e não caem no spam.</p></div></div>
		<div class="grid-3">
			<?php lk_input( 'smtp_host', 'Servidor SMTP', $s['smtp_host'], 'text', 'placeholder="mail.ldkmarketingdigital.com.br"' ); ?>
			<?php lk_input( 'smtp_port', 'Porta', $s['smtp_port'], 'number', 'placeholder="465"' ); ?>
			<?php lk_select( 'smtp_secure', 'Segurança', array( 'ssl' => 'SSL (porta 465)', 'tls' => 'TLS (porta 587)', '' => 'Nenhuma' ), $s['smtp_secure'] ); ?>
			<?php lk_input( 'smtp_user', 'Usuário (e-mail completo)', $s['smtp_user'], 'email', 'placeholder="suporte@ldkmarketingdigital.com.br" autocomplete="off"' ); ?>
			<?php lk_input( 'smtp_pass', 'Senha do e-mail', '', 'password', 'autocomplete="new-password" placeholder="' . esc_attr( $sec( 'smtp_pass' ) ) . '"' ); ?>
			<?php lk_input( 'mail_from_name', 'Nome do remetente', $s['mail_from_name'] ); ?>
		</div>
		<?php lk_input( 'mail_from', 'E-mail do remetente (se for diferente do usuário)', $s['mail_from'], 'email' ); ?>
		<div class="grid-2">
			<?php foreach ( lk_email_types() as $key => $label ) : ?>
				<?php lk_select( $key, $label, array( '1' => 'Enviar', '0' => 'Não enviar' ), $s[ $key ] ); ?>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="card step" id="google">
		<div class="step-head"><span class="step-n">06</span><div><h3>Google Drive</h3><p class="muted small">Artes e vídeos vão para Clientes / &lt;cliente&gt; / Conteúdos AAAA-MM, e cada cliente ganha uma planilha "Conteúdos" com o registro dos posts. Também cria as reuniões com Meet na Agenda.</p></div></div>
		<?php $g = lk_google_state(); ?>
		<?php if ( lk_google_connected() ) : ?>
			<div class="flash">Conectado<?php echo ! empty( $g['email'] ) ? ' como ' . esc_html( $g['email'] ) : ''; ?>.</div>
		<?php else : ?>
			<details class="add-details" open>
				<summary class="btn btn--ghost btn--sm">Como conectar (uma vez só, ~10 minutos)</summary>
				<ol class="howto">
					<li>Acesse <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console.cloud.google.com</a> e crie um projeto (ex.: "Painel LDK").</li>
					<li>Em <strong>APIs e serviços → Biblioteca</strong>, ative a <strong>Google Drive API</strong>, a <strong>Google Sheets API</strong> e a <strong>Google Calendar API</strong>.</li>
					<li>Em <strong>Tela de consentimento OAuth</strong>, escolha "Externo", preencha nome e e-mail e clique em <strong>Publicar app</strong> (em "Teste" a conexão expira a cada 7 dias).</li>
					<li>Em <strong>Credenciais → Criar credenciais → ID do cliente OAuth</strong> (Aplicativo da Web), cole em "URIs de redirecionamento": <code><?php echo esc_html( lk_google_redirect_uri() ); ?></code></li>
					<li>Copie o ID e a chave secreta para os campos abaixo, salve e clique em <strong>Conectar Google</strong> no fim da página.</li>
				</ol>
			</details>
		<?php endif; ?>
		<div class="grid-3">
			<?php lk_input( 'google_client_id', 'Client ID', $s['google_client_id'], 'text', 'autocomplete="off"' ); ?>
			<?php lk_input( 'google_client_secret', 'Client Secret', '', 'password', 'autocomplete="new-password" placeholder="' . esc_attr( $sec( 'google_client_secret' ) ) . '"' ); ?>
			<?php lk_input( 'google_pasta', 'Pasta principal no Drive', $s['google_pasta'], 'text', 'placeholder="' . esc_attr( $s['empresa'] ) . '"' ); ?>
			<?php lk_input( 'places_key', 'Chave da Places API (Prospecção)', '', 'password', 'autocomplete="new-password" placeholder="' . esc_attr( $sec( 'places_key' ) ) . '"' ); ?>
		</div>
	</section>


	<section class="card step" id="ia">
		<div class="card-head"><h3>IA (Groq, Gemini, Mistral…)</h3><span class="muted small">legendas, ideias do mês, revisão de texto e mensagens de prospecção</span></div>
		<p class="muted small">Escolha qual IA usar e cole a chave dela. <b>Groq</b> tem plano grátis sem cartão (console.groq.com → API Keys); o <b>Gemini</b> e a <b>Mistral</b> também têm plano grátis. Preencha só a do provedor que for usar.</p>
		<div class="grid-3">
			<?php lk_select( 'ai_choice', 'IA em uso', array( '' => 'Automática (a primeira com chave)', 'groq' => 'Groq (grátis)', 'gemini' => 'Gemini', 'mistral' => 'Mistral', 'openrouter' => 'OpenRouter' ), $s['ai_choice'] ); ?>
			<?php lk_input( 'groq_key', 'Chave da Groq', '', 'password', 'autocomplete="new-password" placeholder="' . esc_attr( $sec( 'groq_key' ) ) . '"' ); ?>
			<?php lk_input( 'groq_model', 'Modelo da Groq', $s['groq_model'], 'text', 'placeholder="llama-3.3-70b-versatile"' ); ?>
			<?php lk_input( 'gemini_key', 'Chave do Gemini', '', 'password', 'autocomplete="new-password" placeholder="' . esc_attr( $sec( 'gemini_key' ) ) . '"' ); ?>
			<?php lk_input( 'gemini_model', 'Modelo do Gemini', $s['gemini_model'], 'text', 'placeholder="gemini-2.5-flash"' ); ?>
			<?php lk_input( 'mistral_key', 'Chave da Mistral', '', 'password', 'autocomplete="new-password" placeholder="' . esc_attr( $sec( 'mistral_key' ) ) . '"' ); ?>
			<?php lk_input( 'mistral_model', 'Modelo da Mistral', $s['mistral_model'], 'text', 'placeholder="mistral-small-latest"' ); ?>
			<?php lk_input( 'openrouter_key', 'Chave do OpenRouter', '', 'password', 'autocomplete="new-password" placeholder="' . esc_attr( $sec( 'openrouter_key' ) ) . '"' ); ?>
			<div class="field"><span>&nbsp;</span><?php lk_action_button( 'ai_test', array(), 'Testar a IA', 'btn btn--ghost' ); ?></div>
		</div>
		<p class="muted small">Salve as chaves antes de testar. Se o teste mostrar "limite grátis atingido", espere um minuto. Não envie dados sensíveis de clientes em planos grátis.</p>
	</section>

	<section class="card step" id="seguranca">
		<div class="step-head"><span class="step-n">07</span><div><h3>Segurança</h3></div></div>
		<?php lk_select( 'login_card', 'Tela de login: mostrar o cartão de identificação (nome e função) ao digitar o e-mail', array( '1' => 'Ligado', '0' => 'Desligado (mais privado: ninguém vê nome e função sem a senha)' ), $s['login_card'] ); ?>
		<?php lk_select( 'seg_2fa', 'Verificação em duas etapas (código por e-mail no login da equipe)', array( '1' => 'Ligada (recomendado)', '0' => 'Desligada' ), $s['seg_2fa'] ); ?>
		<p class="muted small">Se um dia ficar sem acesso ao e-mail, coloque <code>define( 'LK_DISABLE_2FA', true );</code> no wp-config.php pela hospedagem.</p>
	</section>

	<section class="card step" id="apontamentos">
		<div class="step-head"><span class="step-n">08</span><div><h3>Apontamentos</h3><p class="muted small">Botão "Apontar" no canto das telas: quem está testando clica num ponto e escreve o que quer mudar. Tudo cai em <a href="<?php echo esc_url( lk_panel_url( 'apontamentos' ) ); ?>">Apontamentos</a>. Desligue quando o sistema estiver aprovado.</p></div></div>
		<div class="grid-2">
			<?php lk_select( 'apontamentos', 'Botão Apontar para a equipe', array( '1' => 'Ligado', '0' => 'Desligado' ), $s['apontamentos'] ); ?>
			<?php lk_select( 'apontamentos_clientes', 'Botão Apontar na área do cliente', array( '0' => 'Desligado', '1' => 'Ligado (os clientes também apontam)' ), $s['apontamentos_clientes'] ); ?>
			<?php lk_input( 'apontamentos_email', 'E-mail que recebe o aviso (vazio = e-mail do administrador do WordPress)', $s['apontamentos_email'], 'email' ); ?>
		</div>
	</section>

	<section class="card step" id="contrato">
		<div class="step-head"><span class="step-n">10</span><div><h3>Contrato</h3><p class="muted small">Dados da <?php echo esc_html( $s['empresa'] ); ?> que entram no contrato e o modelo do texto. Deixe o modelo em branco para usar o padrão (serviços de marketing digital, recorrência mensal e assinatura eletrônica).</p></div></div>
		<div class="grid-3">
			<?php lk_input( 'empresa_razao', 'Razão social', $s['empresa_razao'] ); ?>
			<?php lk_input( 'empresa_cnpj', 'CNPJ', $s['empresa_cnpj'], 'text', 'data-mask="doc"' ); ?>
			<?php lk_input( 'empresa_representante', 'Quem assina pela agência', $s['empresa_representante'] ); ?>
			<?php lk_input( 'empresa_endereco', 'Endereço completo', $s['empresa_endereco'] ); ?>
			<?php lk_input( 'empresa_cidade', 'Cidade/UF (foro)', $s['empresa_cidade'], 'text', 'placeholder="São José dos Campos/SP"' ); ?>
		</div>
		<details class="howto">
			<summary>Modelo do contrato (texto)</summary>
			<?php lk_input( 'pacotes', 'Pacotes (um por linha: Nome | valor mensal | artes por mês | serviço 1; serviço 2)', $s['pacotes'] ? $s['pacotes'] : lk_default_packages(), 'textarea', 'rows="6" class="mono"' ); ?>
			<p class="muted small">Os pacotes aparecem como botão de seleção no contrato, no cadastro do cliente e no contrato já assinado. Ao escolher, serviços, valor e artes são preenchidos (dá para ajustar). Os valores acima são exemplos: troque pelos da agência.</p>
			<?php lk_input( 'contrato_modelo', 'Texto (em branco = modelo padrão)', $s['contrato_modelo'] ? $s['contrato_modelo'] : lk_default_contract(), 'textarea', 'rows="20" class="mono"' ); ?>
			<p class="muted small">Marcadores: <?php echo esc_html( implode( ' ', array_keys( lk_contract_tags() ) ) ); ?></p>
		</details>
	</section>

	<section class="card step" id="sala-voz">
		<div class="step-head"><span class="step-n">09</span><div><h3>Sala de voz da equipe</h3><p class="muted small">Funciona sem configurar nada na maioria das redes. Se alguém da equipe entrar na sala e ficar em "conectando…" (rede de empresa ou internet muito fechada), preencha um servidor TURN (ex.: conta gratuita da Metered ou Cloudflare Calls).</p></div></div>
		<div class="grid-3">
			<?php lk_input( 'voz_turn_url', 'TURN: endereço(s) (separe por vírgula)', $s['voz_turn_url'], 'text', 'placeholder="turn:global.relay.metered.ca:80,turns:global.relay.metered.ca:443"' ); ?>
			<?php lk_input( 'voz_turn_user', 'TURN: usuário', $s['voz_turn_user'] ); ?>
			<?php lk_input( 'voz_turn_pass', 'TURN: senha', '', 'password', 'autocomplete="new-password" placeholder="' . ( $s['voz_turn_pass'] ? '•••••• (salva)' : '' ) . '"' ); ?>
		</div>
	</section>

	<div class="form-actions form-actions--sticky"><button type="submit" class="btn btn--primary">Salvar configurações</button></div>
</form>

<section class="card">
	<div class="card-head"><h3>Conta Google</h3></div>
	<?php if ( lk_google_connected() ) : ?>
		<?php lk_action_button( 'google_disconnect', array(), 'Desconectar Google', 'btn btn--danger btn--sm', 'Desconectar o Drive? Os arquivos que já estão lá continuam.' ); ?>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="inline-form"><input type="hidden" name="action" value="lk_google_connect"><?php wp_nonce_field( 'lk_google_connect' ); ?><button type="submit" class="btn btn--primary"<?php echo $s['google_client_id'] ? '' : ' disabled title="Preencha e salve o Client ID primeiro"'; ?>>Conectar Google Drive</button></form>
	<?php endif; ?>
</section>

<section class="card" id="formulario-site">
	<div class="card-head"><h3>Formulários do site → funil</h3></div>
	<p class="muted small">Quem preencher um formulário no <?php echo esc_html( preg_replace( '#^https?://#', '', $s['site'] ) ); ?> entra no Funil como lead (origem "Site"), com lembrete para responder hoje.</p>
	<div class="copy-row"><input type="text" readonly value="<?php echo esc_attr( lk_site_form_url() ); ?>" onclick="this.select()"><button type="button" class="btn btn--ghost" data-copy="<?php echo esc_attr( lk_site_form_url() ); ?>"><?php echo lk_icon( 'copiar', 16 ); // phpcs:ignore ?><span>Copiar</span></button></div>
	<details class="howto">
		<summary>Como ligar no Elementor</summary>
		<ol>
			<li>Abra a página no Elementor e clique no <strong>Formulário</strong>.</li>
			<li>Em <strong>Ações após o envio</strong>, adicione <strong>Webhook</strong> (mantenha as ações que já existem).</li>
			<li>Cole a URL acima, atualize e faça um envio de teste: o lead aparece no Funil.</li>
		</ol>
	</details>
	<?php lk_action_button( 'site_form_token', array(), 'Gerar chave nova', 'btn btn--ghost btn--sm', 'Gerar uma chave nova? A URL muda.' ); ?>
</section>

<section class="card">
	<div class="card-head"><h3>Enviar e-mail de teste</h3></div>
	<?php lk_form( 'mail_test', 'copy-row' ); ?>
		<input type="email" name="to" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" placeholder="Para qual e-mail?">
		<button type="submit" class="btn btn--primary">Enviar teste</button>
	</form>
</section>

<section class="card" id="seguranca-status">
	<div class="card-head"><h3>Segurança: checklist</h3></div>
	<ul class="sec-checks">
		<?php foreach ( lk_security_checks() as $c ) : ?>
			<li class="<?php echo $c[0] ? 'is-ok' : 'is-bad'; ?>"><?php echo $c[0] ? '✓' : '✗'; ?> <?php echo esc_html( $c[1] ); ?><?php if ( ! $c[0] ) : ?> <small class="muted">— <?php echo esc_html( $c[2] ); ?></small><?php endif; ?></li>
		<?php endforeach; ?>
	</ul>
</section>
<?php
lk_panel_end();
