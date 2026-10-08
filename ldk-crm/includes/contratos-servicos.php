<?php
/**
 * Contratos por assunto (tipo de serviço).
 *
 * - Catálogo de assuntos: cada um traz a lista de serviços do contrato e o briefing próprio.
 * - Botão "Gerar contrato": a equipe escolhe o cliente e marca os serviços; os dados do cliente e da agência
 *   entram sozinhos no texto. Nada é gerado sem esse clique.
 * - Pendentes: rascunho, enviado e "falta a agência". O contrato enviado e não assinado avisa a equipe após 1 dia.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Catálogo de assuntos
 * -------------------------------------------------------------------- */

/** chave => nome, serviços (entram no contrato), briefing (modelo "default:<chave>"). */
function lk_service_types() {
	return apply_filters(
		'lk_service_types',
		array(
			'social'     => array(
				'name'     => 'Social media',
				'services' => array( 'Planejamento mensal de conteúdo', 'Criação de artes e legendas', 'Programação das postagens', 'Relatório mensal de resultados' ),
				'briefing' => 'briefing_social',
			),
			'trafego'    => array(
				'name'     => 'Tráfego pago',
				'services' => array( 'Gestão de campanhas (Meta Ads e Google Ads)', 'Criação e teste de anúncios', 'Otimização semanal das campanhas', 'Relatório mensal de investimento e resultados' ),
				'briefing' => 'briefing_trafego',
			),
			'site'       => array(
				'name'     => 'Site e landing page',
				'services' => array( 'Criação de site ou landing page', 'Configuração de domínio e hospedagem', 'Integração com WhatsApp e formulários', 'Suporte após a entrega' ),
				'briefing' => 'briefing_site',
			),
			'hospedagem' => array(
				'name'     => 'Hospedagem e domínio',
				'services' => array( 'Registro e renovação do domínio', 'Hospedagem do site com certificado SSL', 'Contas de e-mail profissional', 'Cópias de segurança e monitoramento', 'Aviso de vencimento antes da renovação' ),
				'briefing' => 'briefing_hospedagem',
			),
			'branding'   => array(
				'name'     => 'Identidade visual',
				'services' => array( 'Logotipo e variações', 'Paleta de cores e tipografia', 'Manual de marca', 'Papelaria e peças de apresentação' ),
				'briefing' => 'briefing_branding',
			),
			'video'      => array(
				'name'     => 'Vídeo e audiovisual',
				'services' => array( 'Roteiro e captação de vídeos', 'Edição e legendagem', 'Reels e vídeos curtos' ),
				'briefing' => 'briefing_video',
			),
			'crm'        => array(
				'name'     => 'CRM e sistema',
				'services' => array( 'Implantação do CRM personalizado', 'Cadastro inicial e treinamento da equipe', 'Suporte e atualizações mensais' ),
				'briefing' => 'briefing_crm',
			),
			'consultoria' => array(
				'name'     => 'Consultoria',
				'services' => array( 'Diagnóstico de marketing e posicionamento', 'Plano de ação com prioridades', 'Reuniões de acompanhamento' ),
				'briefing' => 'briefing_inicial',
			),
		)
	);
}

/** Chaves de assunto de um contrato (a coluna guarda "social,trafego"). */
function lk_contract_subjects( $k ) {
	$types = lk_service_types();
	$out   = array();
	foreach ( explode( ',', (string) $k->subject ) as $key ) {
		$key = trim( $key );
		if ( isset( $types[ $key ] ) ) {
			$out[] = $key;
		}
	}
	return $out;
}

function lk_contract_subject_names( $k ) {
	$types = lk_service_types();
	return array_map(
		function ( $key ) use ( $types ) {
			return $types[ $key ]['name'];
		},
		lk_contract_subjects( $k )
	);
}

/** Assuntos marcados no formulário (campo tipos[]), já filtrados pelo catálogo. */
function lk_contract_subjects_from_post() {
	$types = lk_service_types();
	$raw   = isset( $_POST['tipos'] ) ? (array) wp_unslash( $_POST['tipos'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	$keys  = array();
	foreach ( $raw as $key ) {
		$key = sanitize_key( $key );
		if ( isset( $types[ $key ] ) ) {
			$keys[] = $key;
		}
	}
	return array_values( array_unique( $keys ) );
}

/** Serviços (um por linha) dos assuntos marcados. */
function lk_contract_services_for( $keys ) {
	$types = lk_service_types();
	$list  = array();
	foreach ( $keys as $key ) {
		foreach ( $types[ $key ]['services'] as $s ) {
			$list[] = $s;
		}
	}
	return implode( "\n", array_unique( $list ) );
}

/** Caixas de seleção dos assuntos. $checked = chaves já marcadas. */
function lk_service_type_boxes( $checked = array() ) {
	echo '<fieldset class="ctr-types"><legend>Serviços do contrato</legend>';
	foreach ( lk_service_types() as $key => $t ) {
		echo '<label class="check"><input type="checkbox" name="tipos[]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, (array) $checked, true ), true, false ) . '><span><strong>' . esc_html( $t['name'] ) . '</strong><small>' . esc_html( implode( ' · ', $t['services'] ) ) . '</small></span></label>';
	}
	echo '</fieldset>';
}

/* -----------------------------------------------------------------------
 * Briefing de cada assunto (entram na lista de modelos de Briefings e pesquisas)
 * -------------------------------------------------------------------- */

function lk_form_defaults() {
	return array_merge( lk_form_defaults_base(), lk_service_briefings() );
}

function lk_service_briefings() {
	$verba = array( 'Ainda não invisto', 'Até R$ 1.000', 'R$ 1.000 a R$ 3.000', 'R$ 3.000 a R$ 10.000', 'Acima de R$ 10.000' );
	return array(
		'briefing_social'   => array(
			'kind'   => 'briefing',
			'title'  => 'Briefing · Social media',
			'intro'  => 'Conta pra gente como é a sua marca e o que você espera das redes. Leva uns 8 minutos.',
			'schema' => array( 'steps' => array(
				array( 'title' => 'A marca', 'questions' => array(
					lk_fq( 'short', 'Nome da marca e @ do Instagram' ),
					lk_fq( 'long', 'O que vocês vendem ou prestam, em poucas palavras?' ),
					lk_fq( 'long', 'Qual o maior diferencial frente aos concorrentes?', array(), false ),
				) ),
				array( 'title' => 'Público e conteúdo', 'questions' => array(
					lk_fq( 'long', 'Quem é o cliente ideal?' ),
					lk_fq( 'multi', 'Que tipo de conteúdo você quer ver?', array( 'Posts de venda', 'Conteúdo educativo', 'Bastidores', 'Depoimentos de clientes', 'Reels', 'Stories diários' ), true, true ),
					lk_fq( 'short', 'Perfis que você admira', array(), false ),
				) ),
				array( 'title' => 'Rotina', 'questions' => array(
					lk_fq( 'choice', 'Quem aprova as artes?', array( 'Eu mesmo(a)', 'Um sócio', 'Uma equipe', 'Pode publicar direto' ), true, true ),
					lk_fq( 'yesno', 'Já tem logo, cores e fontes definidos?' ),
					lk_fq( 'long', 'Algo que nunca devemos falar ou mostrar?', array(), false ),
				) ),
			) ),
		),
		'briefing_trafego'  => array(
			'kind'   => 'briefing',
			'title'  => 'Briefing · Tráfego pago',
			'intro'  => 'Com estas respostas montamos as campanhas certas desde o primeiro dia.',
			'schema' => array( 'steps' => array(
				array( 'title' => 'Objetivo', 'questions' => array(
					lk_fq( 'multi', 'O que as campanhas devem trazer?', array( 'Mensagens no WhatsApp', 'Contatos (formulário)', 'Vendas no site', 'Visitas ao perfil', 'Reconhecimento da marca' ), true, true ),
					lk_fq( 'short', 'Quantos clientes novos por mês seriam um bom resultado?' ),
					lk_fq( 'choice', 'Verba mensal para anúncios', $verba ),
				) ),
				array( 'title' => 'Oferta e público', 'questions' => array(
					lk_fq( 'long', 'Qual produto ou serviço vai ser anunciado, e por quanto?' ),
					lk_fq( 'short', 'Cidade ou região de atendimento' ),
					lk_fq( 'long', 'Quem compra de você? (idade, perfil, dor principal)', array(), false ),
				) ),
				array( 'title' => 'Acessos e histórico', 'questions' => array(
					lk_fq( 'yesno', 'Você já anunciou antes?' ),
					lk_fq( 'long', 'O que funcionou e o que não funcionou?', array(), false ),
					lk_fq( 'yesno', 'Tem conta de anúncios e Pixel/Tag instalados?' ),
					lk_fq( 'short', 'Para onde o cliente deve ir depois do clique? (site, WhatsApp, loja)' ),
				) ),
			) ),
		),
		'briefing_site'     => array(
			'kind'   => 'briefing',
			'title'  => 'Briefing · Site e landing page',
			'intro'  => 'Vamos definir o que o seu site precisa ter para converter visitas em clientes.',
			'schema' => array( 'steps' => array(
				array( 'title' => 'Objetivo do site', 'questions' => array(
					lk_fq( 'choice', 'Qual o objetivo principal?', array( 'Apresentar a empresa', 'Gerar contatos / orçamentos', 'Vender online', 'Agendar atendimentos' ), true, true ),
					lk_fq( 'multi', 'Que páginas você imagina?', array( 'Início', 'Sobre', 'Serviços', 'Blog', 'Contato', 'Portfólio / casos', 'Perguntas frequentes' ), true, true ),
					lk_fq( 'long', 'Sites de referência (e o que você gosta neles)', array(), false ),
				) ),
				array( 'title' => 'Conteúdo e acessos', 'questions' => array(
					lk_fq( 'yesno', 'Você já tem domínio (endereço .com.br)?' ),
					lk_fq( 'short', 'Qual domínio e onde está registrado?', array(), false ),
					lk_fq( 'yesno', 'Já tem textos e fotos, ou a gente produz?' ),
					lk_fq( 'multi', 'Integrações desejadas', array( 'WhatsApp', 'Formulário por e-mail', 'Google Maps', 'Instagram', 'Agenda online', 'Pagamento' ), false, true ),
				) ),
			) ),
		),
		'briefing_hospedagem' => array(
			'kind'   => 'briefing',
			'title'  => 'Briefing · Hospedagem e domínio',
			'intro'  => 'Precisamos saber o que já existe para cuidar da hospedagem e do domínio sem tirar o site do ar.',
			'schema' => array( 'steps' => array(
				array( 'title' => 'Domínio', 'questions' => array(
					lk_fq( 'yesno', 'Você já tem um domínio registrado?' ),
					lk_fq( 'short', 'Qual é o domínio? (ex.: meusite.com.br)', array(), false ),
					lk_fq( 'short', 'Onde ele está registrado e em nome de quem?', array(), false ),
					lk_fq( 'short', 'Data de vencimento do domínio (se souber)', array(), false ),
				) ),
				array( 'title' => 'Hospedagem e e-mail', 'questions' => array(
					lk_fq( 'yesno', 'O site já está hospedado em algum lugar?' ),
					lk_fq( 'short', 'Qual empresa de hospedagem e até quando está pago?', array(), false ),
					lk_fq( 'multi', 'O que você precisa?', array( 'Hospedar o site', 'Registrar um domínio novo', 'Transferir domínio ou site para nós', 'E-mails profissionais', 'Certificado SSL (cadeado)' ), true, true ),
					lk_fq( 'short', 'Quantas contas de e-mail você precisa?', array(), false ),
				) ),
			) ),
		),
		'briefing_branding' => array(
			'kind'   => 'briefing',
			'title'  => 'Briefing · Identidade visual',
			'intro'  => 'Uma marca forte começa por entender quem você é. Responda com calma.',
			'schema' => array( 'steps' => array(
				array( 'title' => 'Essência', 'questions' => array(
					lk_fq( 'long', 'Por que a empresa existe? Qual a sua missão?' ),
					lk_fq( 'multi', 'Três palavras que descrevem a marca', array( 'Confiável', 'Moderna', 'Elegante', 'Acessível', 'Ousada', 'Tradicional', 'Acolhedora', 'Técnica' ), true, true ),
					lk_fq( 'long', 'O que a marca nunca deve parecer?', array(), false ),
				) ),
				array( 'title' => 'Visual', 'questions' => array(
					lk_fq( 'short', 'Cores que gosta e cores que evita', array(), false ),
					lk_fq( 'long', 'Marcas de referência (de qualquer área)', array(), false ),
					lk_fq( 'multi', 'Onde a marca vai aparecer?', array( 'Redes sociais', 'Site', 'Fachada', 'Embalagem', 'Uniforme', 'Cartão e papelaria' ), true, true ),
				) ),
			) ),
		),
		'briefing_video'    => array(
			'kind'   => 'briefing',
			'title'  => 'Briefing · Vídeo e audiovisual',
			'intro'  => 'Para roteirizar e gravar certo, precisamos saber o objetivo e o estilo dos vídeos.',
			'schema' => array( 'steps' => array(
				array( 'title' => 'O vídeo', 'questions' => array(
					lk_fq( 'multi', 'Que vídeos você quer?', array( 'Institucional', 'Reels / TikTok', 'Depoimentos', 'Bastidores', 'Anúncio', 'Tutorial' ), true, true ),
					lk_fq( 'long', 'Qual a mensagem principal?' ),
					lk_fq( 'choice', 'Quantos vídeos por mês?', array( '1 a 2', '3 a 4', '5 a 8', 'Mais de 8' ) ),
				) ),
				array( 'title' => 'Gravação', 'questions' => array(
					lk_fq( 'choice', 'Onde serão as gravações?', array( 'No meu estabelecimento', 'Em estúdio', 'Externas', 'Misto' ), true, true ),
					lk_fq( 'yesno', 'Vai aparecer em vídeo?' ),
					lk_fq( 'long', 'Referências de vídeos que você gosta', array(), false ),
				) ),
			) ),
		),
		'briefing_crm'      => array(
			'kind'   => 'briefing',
			'title'  => 'Briefing · CRM e sistema',
			'intro'  => 'Vamos conhecer a rotina do seu negócio para deixar o sistema do jeito que ele funciona.',
			'schema' => array( 'steps' => array(
				array( 'title' => 'Rotina atual', 'questions' => array(
					lk_fq( 'long', 'Como você controla clientes e atendimentos hoje? (planilha, caderno, outro sistema)' ),
					lk_fq( 'long', 'O que mais toma o seu tempo ou se perde no dia a dia?' ),
					lk_fq( 'short', 'Quantas pessoas vão usar o sistema?' ),
				) ),
				array( 'title' => 'O que precisa ter', 'questions' => array(
					lk_fq( 'multi', 'Quais recursos são importantes?', array( 'Cadastro de clientes', 'Contratos com assinatura online', 'Agenda e prazos', 'Cobranças e financeiro', 'Documentos e arquivos', 'Funil de novos clientes', 'Avisos de vencimento', 'Área do cliente' ), true, true ),
					lk_fq( 'long', 'Tem documentos ou modelos que devem entrar prontos? (contratos, petições, propostas)', array(), false ),
					lk_fq( 'yesno', 'Precisa importar uma lista de clientes existente?' ),
				) ),
			) ),
		),
	);
}

/* -----------------------------------------------------------------------
 * Contratos pendentes
 * -------------------------------------------------------------------- */

/** Pendente = ainda falta alguém assinar ou enviar. */
function lk_contract_pending_statuses() {
	return array( 'rascunho', 'enviado', 'assinado' );
}

function lk_contracts_pending_count() {
	static $n = null;
	if ( null === $n ) {
		$n = count( lk_rows( 'contracts', "status IN ('enviado','assinado')" ) ); // rascunho ainda não foi para ninguém: não conta no menu
	}
	return $n;
}

/** Dias que o contrato está esperando a assinatura do cliente (0 se não está esperando). */
function lk_contract_days_waiting( $k ) {
	if ( 'enviado' !== $k->status || ! $k->sent_at ) {
		return 0;
	}
	return (int) floor( ( strtotime( lk_now() ) - strtotime( $k->sent_at ) ) / DAY_IN_SECONDS );
}

/* -----------------------------------------------------------------------
 * Gerar contrato (sempre por clique)
 * -------------------------------------------------------------------- */

function lk_do_contract_generate() {
	lk_require( 'clientes' );
	$client = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	if ( ! $client ) {
		lk_back( 'Escolha o cliente.', 'erro' );
	}
	$keys = lk_contract_subjects_from_post();
	if ( ! $keys ) {
		lk_back( 'Marque pelo menos um serviço.', 'erro' );
	}
	$types = lk_service_types();
	$names = array_map(
		function ( $key ) use ( $types ) {
			return $types[ $key ]['name'];
		},
		$keys
	);
	$over = array(
		'subject'  => implode( ',', $keys ),
		'services' => lk_contract_services_for( $keys ),
		'title'    => 'Contrato de prestação de serviços · ' . implode( ' + ', $names ),
	);
	if ( lk_in( 'monthly_value', 'money' ) > 0 ) {
		$over['monthly_value'] = lk_in( 'monthly_value', 'money' );
	}
	if ( lk_in( 'setup_value', 'money' ) > 0 ) {
		$over['setup_value'] = lk_in( 'setup_value', 'money' );
	}
	if ( lk_in( 'months', 'int' ) > 0 ) {
		$over['months'] = min( 60, lk_in( 'months', 'int' ) );
	}
	$id = lk_contract_draft_for( $client, null, $over );
	lk_back( 'Contrato gerado com os dados do cliente e da agência. Confira, ajuste se precisar e envie para assinatura.', 'ok', lk_panel_url( 'contrato', $id ) );
}

function lk_contract_generate_form() {
	lk_form( 'contract_generate', 'stack' );
	lk_select( 'client_id', 'Cliente', lk_client_options( 'Escolha o cliente…' ), '', 'required' );
	lk_service_type_boxes();
	?>
	<div class="grid-3">
		<?php lk_input( 'monthly_value', 'Valor mensal (R$)', '', 'text', 'inputmode="decimal" data-money placeholder="da ficha do cliente"' ); ?>
		<?php lk_input( 'setup_value', 'Implantação (R$)', '', 'text', 'inputmode="decimal" data-money' ); ?>
		<?php lk_input( 'months', 'Meses de contrato', 12, 'number', 'min="1" max="60"' ); ?>
	</div>
	<p class="muted small">Os dados do cliente e da agência entram sozinhos no texto. O contrato nasce como rascunho: você confere, inclui cláusulas e só então envia para assinatura.</p>
	<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Gerar contrato</button></div>
	</form>
	<?php
}

/* -----------------------------------------------------------------------
 * Aviso: contrato enviado e não assinado há 1 dia
 * -------------------------------------------------------------------- */

add_action( 'lk_hourly', 'lk_contract_unsigned_alert', 40 );
function lk_contract_unsigned_alert() {
	foreach ( lk_rows( 'contracts', "status = 'enviado' AND reminded_at IS NULL AND sent_at IS NOT NULL" ) as $k ) {
		if ( strtotime( lk_now() ) - strtotime( $k->sent_at ) < DAY_IN_SECONDS ) {
			continue;
		}
		$client = lk_get( 'clients', $k->client_id );
		if ( ! $client ) {
			continue;
		}
		lk_update( 'contracts', $k->id, array( 'reminded_at' => lk_now() ) );
		$text = '⏰ ' . lk_client_label( $client ) . ' ainda não assinou o contrato (enviado há mais de 1 dia).';
		foreach ( get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) as $admin ) {
			lk_notify( (int) $admin, $text, lk_panel_url( 'contrato', $k->id ) );
		}
		foreach ( array_unique( array_filter( array( get_option( 'admin_email' ), lk_setting( 'email' ) ) ) ) as $to ) {
			lk_mail( $to, 'Contrato sem assinatura · ' . lk_client_label( $client ), 'Contrato aguardando assinatura', '<p><strong>' . esc_html( lk_client_label( $client ) ) . '</strong> recebeu o contrato em ' . esc_html( lk_date( $k->sent_at, 'd/m/Y H:i' ) ) . ' e ainda não assinou. ' . ( $k->viewed_at ? 'Ele abriu o link em ' . esc_html( lk_date( $k->viewed_at, 'd/m H:i' ) ) . '.' : 'Ele ainda não abriu o link.' ) . ' Vale um lembrete pelo WhatsApp.</p>', array( array( 'Valor mensal', lk_money( $k->monthly_value ) ) ), 'Abrir o contrato', lk_panel_url( 'contrato', $k->id ) );
		}
	}
}

/** Reenvia o link por e-mail para o cliente assinar. */
function lk_do_contract_remind() {
	lk_require( 'clientes' );
	$k      = lk_get( 'contracts', lk_in( 'id', 'int' ) );
	$client = $k ? lk_get( 'clients', $k->client_id ) : null;
	if ( ! $k || ! $client || 'enviado' !== $k->status || ! is_email( $client->email ) ) {
		lk_back( 'Não foi possível lembrar este cliente (confira o e-mail na ficha).', 'erro' );
	}
	lk_mail( $client->email, 'Lembrete: contrato para assinar · ' . lk_setting( 'empresa' ), 'Seu contrato ainda espera a assinatura', '<p>Olá, ' . esc_html( strtok( (string) $client->name, ' ' ) ) . '! O contrato com a ' . esc_html( lk_setting( 'empresa' ) ) . ' continua disponível para assinatura online. Leva poucos minutos.</p>', array( array( 'Valor mensal', lk_money( $k->monthly_value ) ) ), 'Ler e assinar o contrato', lk_contract_url( $k ) );
	lk_back( 'Lembrete enviado para ' . $client->email . '.' );
}
