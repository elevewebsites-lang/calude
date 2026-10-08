<?php
/**
 * Contratos com assinatura eletrônica.
 *
 * Fluxo: ficha do cliente → Novo contrato (serviços, valor mensal, meses, início, dia de vencimento, pacote)
 *   → o texto sai do modelo (Configurações → Contrato) com os dados trocados → dá para ajustar o texto
 *   → "Enviar para assinatura": o texto é congelado (hash SHA-256) e a cliente recebe o link por e-mail/WhatsApp
 *   → /contrato/<token>/: ela lê, confirma o código enviado ao e-mail dela, desenha a assinatura e assina
 *   → a LDK assina também (admin). Os dois recebem por e-mail a cópia assinada com o certificado.
 *
 * Validade: assinatura eletrônica (Lei 14.063/2020 e MP 2.200-2/2001, art. 10, § 2º), com as partes
 * concordando com o meio no próprio contrato. O certificado guarda: nome, documento, e-mail confirmado por código,
 * IP, navegador, data/hora e o hash do texto (prova de que o texto não mudou depois da assinatura).
 * Não é assinatura ICP-Brasil (certificado digital); para a maioria dos contratos de serviço entre empresas, basta.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		add_rewrite_rule( '^contrato/([A-Za-z0-9]+)/?$', 'index.php?lk_route=contract&lk_token=$matches[1]', 'top' );
	}
);

function lk_contract_url( $k ) {
	if ( ! empty( $k->imported ) && ! empty( $k->file_url ) ) {
		return $k->file_url; // contrato assinado fora da plataforma: abre o arquivo
	}
	return lk_url( 'contrato/' . $k->token );
}

function lk_contract_status_label( $st ) {
	return array(
		'rascunho'  => 'rascunho',
		'enviado'   => 'aguardando a assinatura da cliente',
		'assinado'  => 'assinado pela cliente',
		'concluido' => 'assinado pelas duas partes',
		'cancelado' => 'cancelado',
	)[ $st ] ?? $st;
}

function lk_contract_tags() {
	return array(
		'{contratada}'             => 'Razão social da LDK',
		'{contratada_documento}'   => 'CNPJ da LDK',
		'{contratada_endereco}'    => 'Endereço da LDK',
		'{contratada_responsavel}' => 'Quem assina pela LDK',
		'{cliente}'                => 'Empresa da cliente',
		'{cliente_nome}'           => 'Responsável da cliente',
		'{cliente_documento}'      => 'CNPJ/CPF da cliente',
		'{cliente_cpf}'            => 'CPF do responsável',
		'{cliente_endereco}'       => 'Endereço da cliente',
		'{cliente_email}'          => 'E-mail da cliente',
		'{cliente_whatsapp}'       => 'WhatsApp da cliente',
		'{pacote}'                 => 'Nome do pacote escolhido',
		'{servicos}'               => 'Serviços contratados (lista)',
		'{pacote_artes}'           => 'Artes por mês',
		'{valor_mensal}'           => 'Valor mensal',
		'{valor_mensal_extenso}'   => 'Valor mensal por extenso',
		'{implantacao}'            => 'Taxa de implantação (se houver)',
		'{meses}'                  => 'Meses de contrato',
		'{inicio}'                 => 'Data de início',
		'{fim}'                    => 'Data de término',
		'{dia_vencimento}'         => 'Dia do vencimento',
		'{condicoes}'              => 'Condições especiais',
		'{data}'                   => 'Data de hoje por extenso',
		'{cidade}'                 => 'Cidade (foro)',
	);
}

function lk_default_contract() {
	return <<<'TXT'
## CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE MARKETING DIGITAL

Pelo presente instrumento particular, de um lado **{contratada}**, inscrita no CNPJ sob o nº {contratada_documento}, com sede em {contratada_endereco}, neste ato representada por {contratada_responsavel}, doravante denominada CONTRATADA, e de outro lado **{cliente}**, inscrita no CNPJ/CPF sob o nº {cliente_documento}, com endereço em {cliente_endereco}, neste ato representada por {cliente_nome}, CPF {cliente_cpf}, e-mail {cliente_email}, doravante denominada CONTRATANTE, têm entre si justo e contratado o seguinte:

## CLÁUSULA 1ª · DO OBJETO
A CONTRATADA prestará à CONTRATANTE os seguintes serviços de marketing digital:
{servicos}
Pacote mensal: até **{pacote_artes} conteúdos** por mês, conforme o planejamento aprovado pela CONTRATANTE.

## CLÁUSULA 2ª · DO FLUXO DE TRABALHO E DAS APROVAÇÕES
Todo mês a CONTRATADA apresenta o planejamento de conteúdo, que a CONTRATANTE aprova ou ajusta pela área do cliente. Depois, cada arte é enviada para aprovação antes da publicação. As aprovações registradas na área do cliente autorizam a CONTRATADA a seguir para a etapa seguinte e a publicar nas datas combinadas. Conteúdos não aprovados até a data prevista podem ser remarcados.

## CLÁUSULA 3ª · DO VALOR E DO PAGAMENTO
Pelos serviços, a CONTRATANTE pagará à CONTRATADA o valor mensal de **{valor_mensal}** ({valor_mensal_extenso}), com vencimento todo dia {dia_vencimento}, por Pix, boleto ou cartão, pelo link de pagamento enviado pela CONTRATADA. {implantacao}
O atraso superior a 10 (dez) dias poderá suspender a execução dos serviços até a regularização, com multa de 2% e juros de 1% ao mês.
A verba de anúncios (tráfego pago), quando houver, é paga pela CONTRATANTE diretamente às plataformas e não está incluída no valor acima.

## CLÁUSULA 4ª · DO PRAZO
Este contrato vale por {meses} meses, de {inicio} a {fim}, e é renovado automaticamente por igual período se nenhuma das partes se manifestar por escrito com 30 (trinta) dias de antecedência.

## CLÁUSULA 5ª · DAS OBRIGAÇÕES DA CONTRATANTE
Fornecer, em tempo hábil, informações, materiais, acessos e aprovações; garantir que possui os direitos de uso de textos, imagens e marcas enviados; responder o briefing e manter atualizadas as informações do negócio (promoções, eventos, horários); e efetuar os pagamentos nas datas combinadas.

## CLÁUSULA 6ª · DAS OBRIGAÇÕES DA CONTRATADA
Executar os serviços com qualidade técnica, respeitar a identidade da marca da CONTRATANTE, cumprir o calendário aprovado e apresentar relatório mensal de resultados. A CONTRATADA não garante número de seguidores, curtidas ou vendas, pois dependem de fatores externos, mas se compromete com as boas práticas para alcançá-los.

## CLÁUSULA 7ª · DA PROPRIEDADE E DOS ACESSOS
As artes e os textos produzidos e pagos pertencem à CONTRATANTE. Os acessos às redes sociais continuam sendo da CONTRATANTE, que pode revogá-los a qualquer momento. A CONTRATADA pode mostrar os trabalhos em seu portfólio, salvo pedido expresso em contrário.

## CLÁUSULA 8ª · DA CONFIDENCIALIDADE E DA LGPD
As partes manterão sigilo sobre informações confidenciais e tratarão dados pessoais de acordo com a Lei Geral de Proteção de Dados (Lei 13.709/2018). Senhas e acessos são armazenados de forma criptografada.

## CLÁUSULA 9ª · DA RESCISÃO
Qualquer das partes pode encerrar este contrato com aviso prévio de 30 (trinta) dias, por escrito. Os serviços do mês em andamento serão concluídos e cobrados normalmente.

## CLÁUSULA 10ª · DAS CONDIÇÕES ESPECIAIS
{condicoes}

## CLÁUSULA 11ª · DA ASSINATURA ELETRÔNICA E DO FORO
As partes reconhecem como válida a assinatura eletrônica deste contrato, nos termos do art. 10, § 2º, da MP 2.200-2/2001 e da Lei 14.063/2020, com identificação por e-mail confirmado por código, registro de data, hora e IP. Fica eleito o foro da comarca de {cidade} para dirimir quaisquer questões oriundas deste contrato.

{clausulas_adicionais}

{cidade}, {data}.
TXT;
}

/**
 * Valor por extenso em reais.
 */
function lk_valor_extenso( $valor ) {
	$valor    = round( (float) $valor, 2 );
	$inteiro  = (int) floor( $valor );
	$centavos = (int) round( ( $valor - $inteiro ) * 100 );
	$u        = array( '', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove', 'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove' );
	$d        = array( '', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa' );
	$c        = array( '', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos' );
	$ate_mil  = function ( $n ) use ( $u, $d, $c ) {
		if ( 100 === $n ) {
			return 'cem';
		}
		$parts = array();
		if ( $n >= 100 ) {
			$parts[] = $c[ (int) floor( $n / 100 ) ];
			$n      %= 100;
		}
		if ( $n >= 20 ) {
			$parts[] = $d[ (int) floor( $n / 10 ) ];
			$n      %= 10;
		}
		if ( $n > 0 ) {
			$parts[] = $u[ $n ];
		}
		return implode( ' e ', $parts );
	};
	$texto = array();
	$mi    = (int) floor( $inteiro / 1000000 );
	$mil   = (int) floor( ( $inteiro % 1000000 ) / 1000 );
	$resto = $inteiro % 1000;
	if ( $mi ) {
		$texto[] = $ate_mil( $mi ) . ( 1 === $mi ? ' milhão' : ' milhões' );
	}
	if ( $mil ) {
		$texto[] = ( 1 === $mil ? 'mil' : $ate_mil( $mil ) . ' mil' );
	}
	if ( $resto ) {
		$texto[] = $ate_mil( $resto );
	}
	$reais = $texto ? implode( ( $resto && ( $resto < 100 || 0 === $resto % 100 ) ) ? ' e ' : ' ', $texto ) : 'zero';
	$out   = $reais . ( 1 === $inteiro ? ' real' : ( $mi && ! $mil && ! $resto ? ' de reais' : ' reais' ) );
	if ( $centavos ) {
		$out .= ' e ' . $ate_mil( $centavos ) . ( 1 === $centavos ? ' centavo' : ' centavos' );
	}
	return $out;
}

/**
 * Monta o texto do contrato com os dados trocados.
 */
function lk_contract_compose( $k ) {
	$client = lk_get( 'clients', $k->client_id );
	$model  = trim( (string) lk_setting( 'contrato_modelo' ) ) ? lk_setting( 'contrato_modelo' ) : lk_default_contract();
	$blank  = '__________';
	$srv    = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $k->services ) ) );
	$ini    = $k->start_date ? $k->start_date : lk_today();
	$fim    = gmdate( 'Y-m-d', strtotime( $ini . ' +' . max( 1, (int) $k->months ) . ' months -1 day' ) );
	$map    = array(
		'{contratada}'             => lk_setting( 'empresa_razao' ) ? lk_setting( 'empresa_razao' ) : lk_setting( 'empresa' ),
		'{contratada_documento}'   => lk_setting( 'empresa_cnpj' ) ? lk_setting( 'empresa_cnpj' ) : $blank,
		'{contratada_endereco}'    => lk_setting( 'empresa_endereco' ) ? lk_setting( 'empresa_endereco' ) : $blank,
		'{contratada_responsavel}' => lk_setting( 'empresa_representante' ) ? lk_setting( 'empresa_representante' ) : $blank,
		'{cliente}'                => $client ? lk_client_label( $client ) : $blank,
		'{cliente_nome}'           => $client && $client->name ? $client->name : $blank,
		'{cliente_documento}'      => $client && $client->cnpj ? $client->cnpj : $blank,
		'{cliente_cpf}'            => $client && $client->rep_cpf ? $client->rep_cpf : $blank,
		'{cliente_endereco}'       => $client && $client->address ? trim( $client->address . ( $client->city ? ', ' . $client->city : '' ) . ( $client->cep ? ', CEP ' . $client->cep : '' ) ) : $blank,
		'{cliente_email}'          => $client && $client->email ? $client->email : $blank,
		'{cliente_whatsapp}'       => $client && $client->whatsapp ? $client->whatsapp : $blank,
		'{pacote}'                 => trim( (string) $k->package ) ? $k->package : 'Personalizado',
		'{servicos}'               => $srv ? '- ' . implode( "\n- ", $srv ) : '- Conforme proposta comercial aceita.',
		'{pacote_artes}'           => (int) $k->posts_quota ? (string) (int) $k->posts_quota : 'a combinar',
		'{valor_mensal}'           => lk_money( $k->monthly_value ),
		'{valor_mensal_extenso}'   => lk_valor_extenso( $k->monthly_value ),
		'{implantacao}'            => (float) $k->setup_value > 0 ? 'Na assinatura, a CONTRATANTE pagará também a taxa única de implantação de ' . lk_money( $k->setup_value ) . ' (' . lk_valor_extenso( $k->setup_value ) . ').' : '',
		'{meses}'                  => (string) (int) $k->months,
		'{inicio}'                 => lk_date( $ini ),
		'{fim}'                    => lk_date( $fim ),
		'{dia_vencimento}'         => (string) (int) $k->due_day,
		'{condicoes}'              => trim( (string) $k->extra ) ? trim( (string) $k->extra ) : 'Não há.',
		'{clausulas_adicionais}'   => function_exists( 'lk_contract_clauses_block' ) ? lk_contract_clauses_block( $k->clauses ) : '',
		'{data}'                   => current_time( 'j' ) . ' de ' . lk_month_name( (int) current_time( 'n' ) ) . ' de ' . current_time( 'Y' ),
		'{cidade}'                 => lk_setting( 'empresa_cidade' ) ? lk_setting( 'empresa_cidade' ) : $blank,
	);
	$text = strtr( $model, $map );
	if ( false === strpos( $model, '{clausulas_adicionais}' ) && function_exists( 'lk_contract_apply_clauses' ) && trim( (string) $k->clauses ) ) {
		$text = lk_contract_apply_clauses( $text, $k->clauses ); // modelo antigo sem a tag: entra antes da data
	}
	return $text;
}

/**
 * Texto simples → HTML: "## Título", **negrito**, listas com "- " e parágrafos.
 */
function lk_contract_html( $text ) {
	$html = '';
	$list = false;
	$b    = function ( $t ) {
		return preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', esc_html( $t ) );
	};
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( 0 === strpos( $line, '- ' ) ) {
			if ( ! $list ) {
				$html .= '<ul>';
				$list  = true;
			}
			$html .= '<li>' . $b( substr( $line, 2 ) ) . '</li>';
			continue;
		}
		if ( $list ) {
			$html .= '</ul>';
			$list  = false;
		}
		if ( '' === $line ) {
			continue;
		}
		$html .= 0 === strpos( $line, '## ' ) ? '<h3>' . $b( substr( $line, 3 ) ) . '</h3>' : '<p>' . $b( $line ) . '</p>';
	}
	return $html . ( $list ? '</ul>' : '' );
}

/* -----------------------------------------------------------------------
 * Ações do painel
 * -------------------------------------------------------------------- */

function lk_do_contract_save() {
	lk_require( 'clientes' );
	$id     = lk_in( 'id', 'int' );
	$client = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	if ( ! $client ) {
		lk_back( 'Cliente não encontrado.', 'erro' );
	}
	$old = $id ? lk_get( 'contracts', $id ) : null;
	if ( $old && ! in_array( $old->status, array( 'rascunho' ), true ) ) {
		lk_back( 'Contrato enviado ou assinado não pode mais ser alterado. Cancele e faça outro.', 'erro' );
	}
	$data = array(
		'client_id'     => $client->id,
		'title'         => lk_in( 'title' ) ? lk_in( 'title' ) : 'Contrato de prestação de serviços',
		'services'      => lk_in( 'services', 'textarea' ),
		'package'       => lk_in( 'package' ),
		'monthly_value' => lk_can( 'financeiro' ) ? lk_in( 'monthly_value', 'money' ) : ( $old ? (float) $old->monthly_value : (float) $client->monthly_fee ),
		'setup_value'   => lk_can( 'financeiro' ) ? lk_in( 'setup_value', 'money' ) : ( $old ? (float) $old->setup_value : 0 ),
		'months'        => max( 1, lk_in( 'months', 'int' ) ),
		'start_date'    => lk_in( 'start_date', 'date' ) ? lk_in( 'start_date', 'date' ) : lk_today(),
		'due_day'       => min( 28, max( 1, lk_in( 'due_day', 'int' ) ? lk_in( 'due_day', 'int' ) : 10 ) ),
		'posts_quota'   => lk_in( 'posts_quota', 'int' ),
		'extra'         => lk_in( 'extra', 'textarea' ),
		'clauses'       => lk_in( 'clauses', 'textarea' ),
	);
	if ( $old ) {
		lk_update( 'contracts', $old->id, $data );
		$id = $old->id;
	} else {
		$data['token']      = strtolower( wp_generate_password( 28, false ) );
		$data['created_by'] = get_current_user_id();
		$id                 = lk_insert( 'contracts', $data );
	}
	// Texto: gera de novo a partir do modelo (se a pessoa não editou à mão).
	$k = lk_get( 'contracts', $id );
	if ( ! $old || lk_in( 'regerar', 'bool' ) || ! trim( (string) $k->body ) ) {
		lk_update( 'contracts', $id, array( 'body' => lk_contract_compose( $k ) ) );
	}
	lk_back( 'Contrato salvo. Confira o texto e envie para assinatura.', 'ok', lk_panel_url( 'contrato', $id ) );
}

function lk_do_contract_body() {
	lk_require( 'clientes' );
	$k = lk_get( 'contracts', lk_in( 'id', 'int' ) );
	if ( ! $k || 'rascunho' !== $k->status ) {
		lk_back( 'Só dá para editar o texto enquanto o contrato é rascunho.', 'erro' );
	}
	lk_update( 'contracts', $k->id, array( 'body' => lk_in( 'body', 'textarea' ) ) );
	lk_back( 'Texto do contrato salvo.' );
}

function lk_do_contract_send() {
	lk_require( 'clientes' );
	$k      = lk_get( 'contracts', lk_in( 'id', 'int' ) );
	$client = $k ? lk_get( 'clients', $k->client_id ) : null;
	if ( ! $k || ! $client || ! in_array( $k->status, array( 'rascunho', 'enviado' ), true ) ) {
		lk_back( 'Contrato não pode ser enviado.', 'erro' );
	}
	if ( ! is_email( $client->email ) ) {
		lk_back( 'A cliente precisa ter um e-mail na ficha (é por ele que ela confirma a assinatura).', 'erro' );
	}
	if ( false !== strpos( (string) $k->body, '__________' ) ) {
		lk_back( 'Faltam dados no contrato (campos "__________"). Complete a ficha da cliente e as Configurações → Contrato e gere o texto de novo.', 'erro' );
	}
	lk_update( 'contracts', $k->id, array( 'status' => 'enviado', 'sent_at' => lk_now(), 'hash' => hash( 'sha256', (string) $k->body ) ) );
	$k = lk_get( 'contracts', $k->id );
	lk_mail( $client->email, 'Contrato para assinar · ' . lk_setting( 'empresa' ), 'Seu contrato está pronto', '<p>Olá, ' . esc_html( strtok( (string) $client->name, ' ' ) ) . '! O contrato de prestação de serviços com a ' . esc_html( lk_setting( 'empresa' ) ) . ' está pronto. Leia com calma e assine online: você recebe um código neste e-mail para confirmar.</p>', array( array( 'Valor mensal', lk_money( $k->monthly_value ) ), array( 'Prazo', (int) $k->months . ' meses' ) ), 'Ler e assinar o contrato', lk_contract_url( $k ) );
	$wa = $client->whatsapp ? lk_wa_link( $client->whatsapp, 'Olá, ' . strtok( (string) $client->name, ' ' ) . '! O seu contrato com a ' . lk_setting( 'empresa' ) . ' está pronto para assinar online: ' . lk_contract_url( $k ) ) : '';
	set_transient( 'lk_contract_wa_' . get_current_user_id(), $wa, 600 );
	lk_back( 'Contrato enviado para ' . $client->email . '.' . ( $wa ? ' Reforce pelo WhatsApp.' : '' ) );
}

function lk_do_contract_cancel() {
	lk_require( 'clientes' );
	$k = lk_get( 'contracts', lk_in( 'id', 'int' ) );
	if ( $k && 'concluido' !== $k->status ) {
		lk_update( 'contracts', $k->id, array( 'status' => 'cancelado' ) );
	}
	lk_back( 'Contrato cancelado. O link deixou de funcionar.' );
}

function lk_do_contract_duplicate() {
	lk_require( 'clientes' );
	$k = lk_get( 'contracts', lk_in( 'id', 'int' ) );
	if ( ! $k ) {
		lk_back();
	}
	$id = lk_insert( 'contracts', array( 'client_id' => $k->client_id, 'title' => $k->title, 'services' => $k->services, 'monthly_value' => $k->monthly_value, 'setup_value' => $k->setup_value, 'months' => $k->months, 'start_date' => $k->start_date, 'due_day' => $k->due_day, 'posts_quota' => $k->posts_quota, 'extra' => $k->extra, 'token' => strtolower( wp_generate_password( 28, false ) ), 'created_by' => get_current_user_id() ) );
	lk_update( 'contracts', $id, array( 'body' => lk_contract_compose( lk_get( 'contracts', $id ) ) ) );
	lk_back( 'Cópia criada como rascunho.', 'ok', lk_panel_url( 'contrato', $id ) );
}

/**
 * A LDK assina (admin, depois da cliente ou antes).
 */
function lk_do_contract_agency_sign() {
	lk_require( 'admin' );
	$k = lk_get( 'contracts', lk_in( 'id', 'int' ) );
	if ( ! $k || ! in_array( $k->status, array( 'enviado', 'assinado' ), true ) || $k->agency_signed_at ) {
		lk_back( 'Este contrato não pode ser assinado agora.', 'erro' );
	}
	$sig = lk_sig_clean( (string) lk_in( 'sig', 'raw' ) );
	if ( ! $sig ) {
		lk_back( 'Desenhe a assinatura.', 'erro' );
	}
	lk_update(
		'contracts',
		$k->id,
		array(
			'agency_name'      => lk_in( 'nome' ) ? lk_in( 'nome' ) : wp_get_current_user()->display_name,
			'agency_doc'       => lk_in( 'doc' ),
			'agency_ip'        => lk_client_ip(),
			'agency_sig'       => $sig,
			'agency_signed_at' => lk_now(),
			'agency_user'      => get_current_user_id(),
			'status'           => 'assinado' === $k->status ? 'concluido' : $k->status,
		)
	);
	$k = lk_get( 'contracts', $k->id );
	if ( 'concluido' === $k->status ) {
		lk_contract_archive( $k, true );
	}
	lk_back( 'concluido' === $k->status ? 'Contrato assinado pelas duas partes. A cópia foi para os dois e-mails.' : 'Assinatura da ' . lk_setting( 'empresa' ) . ' registrada. Falta a cliente.' );
}

/**
 * Assinatura desenhada: só PNG em base64, até ~250 KB.
 */
function lk_sig_clean( $raw ) {
	$raw = trim( $raw );
	if ( 0 !== strpos( $raw, 'data:image/png;base64,' ) || strlen( $raw ) > 350000 ) {
		return '';
	}
	$bin = base64_decode( substr( $raw, 22 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	return $bin && 0 === strpos( $bin, "\x89PNG" ) ? $raw : '';
}

function lk_contract_done_mail( $k ) {
	$client = lk_get( 'clients', $k->client_id );
	$to     = array_filter( array( $k->signer_email, get_option( 'admin_email' ), lk_setting( 'email' ) ) );
	foreach ( array_unique( $to ) as $email ) {
		lk_mail( $email, 'Contrato assinado · ' . lk_client_label( $client ), 'Contrato assinado ✓', '<p>O contrato entre ' . esc_html( lk_setting( 'empresa' ) ) . ' e ' . esc_html( lk_client_label( $client ) ) . ' foi assinado pelas duas partes. Guarde o link: nele estão o contrato e o certificado de assinatura (dá para salvar em PDF).</p>', array( array( 'Código do documento (SHA-256)', substr( $k->hash, 0, 16 ) . '…' ) ), 'Ver o contrato assinado', lk_contract_url( $k ) );
	}
}

/* -----------------------------------------------------------------------
 * Página pública de assinatura
 * -------------------------------------------------------------------- */

add_action(
	'template_redirect',
	function () {
		if ( 'contract' !== get_query_var( 'lk_route' ) ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		$rows = lk_rows( 'contracts', 'token = %s', array( sanitize_text_field( get_query_var( 'lk_token' ) ) ) );
		$k    = $rows ? $rows[0] : null;
		if ( ! $k || ( 'rascunho' === $k->status && ! lk_is_team() ) || 'cancelado' === $k->status ) {
			lk_render( 'public/indisponivel' );
		}
		if ( 'enviado' === $k->status && ! $k->viewed_at && ! lk_is_team() ) {
			lk_update( 'contracts', $k->id, array( 'viewed_at' => lk_now() ) );
		}
		$msg = '';
		$err = '';
		if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'lk_contract_' . $k->id ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$res = 'codigo' === lk_in( 'passo' ) ? lk_contract_send_code( $k ) : lk_contract_sign( $k );
			if ( is_wp_error( $res ) ) {
				$err = $res->get_error_message();
			} else {
				$msg = $res;
			}
			$k = lk_get( 'contracts', $k->id );
		}
		lk_render( 'public/contrato', array( 'k' => $k, 'msg' => $msg, 'err' => $err ) );
	},
	0
);

function lk_contract_send_code( $k ) {
	$client = lk_get( 'clients', $k->client_id );
	if ( 'enviado' !== $k->status || ! $client || ! is_email( $client->email ) ) {
		return new WP_Error( 'lk', 'Este contrato não está aguardando assinatura.' );
	}
	if ( $k->otp_exp && $k->otp_exp - 540 > time() ) {
		return new WP_Error( 'lk', 'Um código acabou de ser enviado. Aguarde 1 minuto para pedir outro.' );
	}
	$code = (string) wp_rand( 100000, 999999 );
	lk_update( 'contracts', $k->id, array( 'otp_hash' => hash( 'sha256', $code . $k->token ), 'otp_exp' => time() + 600, 'otp_tries' => 0 ) );
	$ok = lk_mail( $client->email, 'Código para assinar o contrato: ' . $code, 'Seu código de assinatura', '<p>Use o código abaixo para confirmar a assinatura do contrato com a ' . esc_html( lk_setting( 'empresa' ) ) . '. Ele vale por 10 minutos.</p><p style="font-size:30px;font-weight:700;letter-spacing:6px">' . esc_html( $code ) . '</p><p>Se não foi você, ignore este e-mail.</p>' );
	if ( ! $ok ) {
		return new WP_Error( 'lk', 'Não conseguimos enviar o e-mail agora. Fale com a ' . lk_setting( 'empresa' ) . '.' );
	}
	return 'code_sent';
}

function lk_contract_sign( $k ) {
	$client = lk_get( 'clients', $k->client_id );
	if ( 'enviado' !== $k->status ) {
		return new WP_Error( 'lk', 'Este contrato não está aguardando assinatura.' );
	}
	if ( hash( 'sha256', (string) $k->body ) !== $k->hash ) {
		return new WP_Error( 'lk', 'O texto do contrato mudou depois do envio. Peça um novo link.' );
	}
	if ( (int) $k->otp_tries >= 5 ) {
		return new WP_Error( 'lk', 'Muitas tentativas. Peça um novo código.' );
	}
	$code = preg_replace( '/\D/', '', (string) lk_in( 'codigo' ) );
	if ( ! $k->otp_hash || $k->otp_exp < time() || ! hash_equals( $k->otp_hash, hash( 'sha256', $code . $k->token ) ) ) {
		lk_update( 'contracts', $k->id, array( 'otp_tries' => (int) $k->otp_tries + 1 ) );
		return new WP_Error( 'lk', 'Código inválido ou vencido. Confira o e-mail ou peça outro código.' );
	}
	$name = lk_in( 'nome' );
	$doc  = lk_in( 'doc' );
	$sig  = lk_sig_clean( (string) lk_in( 'sig', 'raw' ) );
	if ( mb_strlen( $name ) < 5 || strlen( preg_replace( '/\D/', '', $doc ) ) < 11 || ! lk_in( 'aceite', 'bool' ) || ! $sig ) {
		return new WP_Error( 'lk', 'Preencha o nome completo e o CPF, desenhe a assinatura e marque "Li e concordo".' );
	}
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 250 ) : '';
	lk_update(
		'contracts',
		$k->id,
		array(
			'signer_name'  => $name,
			'signer_doc'   => $doc,
			'signer_email' => $client->email,
			'signer_ip'    => lk_client_ip(),
			'signer_ua'    => $ua,
			'signer_sig'   => $sig,
			'signed_at'    => lk_now(),
			'otp_hash'     => '',
			'status'       => $k->agency_signed_at ? 'concluido' : 'assinado',
		)
	);
	$k = lk_get( 'contracts', $k->id );
	// Ativa o que foi contratado na ficha da cliente (mensalidade, dia e pacote).
	$upd = array();
	if ( (float) $k->monthly_value > 0 ) {
		$upd['monthly_fee']    = $k->monthly_value;
		$upd['due_day']        = (int) $k->due_day;
		$upd['billing_active'] = 1;
	}
	if ( (int) $k->posts_quota > 0 ) {
		$upd['posts_quota'] = (int) $k->posts_quota;
	}
	if ( $upd ) {
		lk_update( 'clients', $client->id, $upd );
	}
	foreach ( get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) as $admin ) {
		lk_notify( (int) $admin, '✍️ ' . lk_client_label( $client ) . ' assinou o contrato.' . ( 'concluido' === $k->status ? '' : ' Falta a assinatura da ' . lk_setting( 'empresa' ) . '.' ), lk_panel_url( 'contrato', $k->id ) );
	}
	lk_contract_archive( $k, 'concluido' === $k->status ); // e-mail com a cópia, Drive e perfil do cliente
	return 'Contrato assinado ✓ Obrigado! ' . ( 'concluido' === $k->status ? 'A cópia assinada foi para o seu e-mail.' : 'Assim que a ' . lk_setting( 'empresa' ) . ' assinar, você recebe a cópia no e-mail.' );
}

/* -----------------------------------------------------------------------
 * Pedaços de tela
 * -------------------------------------------------------------------- */

/**
 * Formulário "Novo contrato" / "Dados do contrato".
 */
function lk_contract_form( $client, $k = null ) {
	lk_form( 'contract_save', 'stack' );
	?>
	<input type="hidden" name="id" value="<?php echo (int) ( $k ? $k->id : 0 ); ?>">
	<input type="hidden" name="client_id" value="<?php echo (int) $client->id; ?>">
	<?php if ( $k ) : ?><input type="hidden" name="regerar" value="1"><?php endif; ?>
	<?php lk_input( 'title', 'Título', $k ? $k->title : 'Contrato de prestação de serviços de marketing digital' ); ?>
	<?php lk_package_picker( $k ? (string) $k->package : '' ); ?>
	<?php lk_input( 'services', 'Serviços contratados (um por linha)', $k ? $k->services : "Planejamento mensal de conteúdo\nCriação de artes e legendas\nProgramação das postagens\nRelatório mensal de resultados", 'textarea', 'rows="5"' ); ?>
	<div class="grid-3">
		<?php if ( ! lk_can( 'financeiro' ) ) : ?><p class="muted small" style="grid-column:1/-1">🔒 Valores do contrato só aparecem para quem o administrador libera em Equipe → Financeiro.</p><?php else : ?>
		<?php lk_input( 'monthly_value', 'Valor mensal (R$)', number_format( (float) ( $k ? $k->monthly_value : $client->monthly_fee ), 2, ',', '.' ), 'text', 'inputmode="decimal" data-money required' ); ?>
		<?php lk_input( 'setup_value', 'Implantação (R$, opcional)', $k && (float) $k->setup_value ? number_format( (float) $k->setup_value, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money' ); ?>
		<?php endif; ?>
		<?php lk_input( 'months', 'Duração (meses de recorrência)', $k ? $k->months : 12, 'number', 'min="1" max="60"' ); ?>
		<?php lk_input( 'start_date', 'Início', $k && $k->start_date ? $k->start_date : lk_today(), 'date' ); ?>
		<?php lk_input( 'due_day', 'Dia do vencimento', $k ? $k->due_day : ( $client->due_day ? $client->due_day : 10 ), 'number', 'min="1" max="28"' ); ?>
		<?php lk_input( 'posts_quota', 'Artes por mês', $k ? $k->posts_quota : $client->posts_quota, 'number', 'min="0"' ); ?>
	</div>
	<?php lk_input( 'clauses', 'Observações / novas cláusulas (uma por linha; entram no contrato como cláusulas adicionais)', $k ? (string) $k->clauses : '', 'textarea', 'rows="3" placeholder="Ex.: O cliente fornece os acessos em até 5 dias úteis."' ); ?>
	<?php lk_input( 'extra', 'Condições especiais (opcional)', $k ? $k->extra : '', 'textarea', 'rows="3" placeholder="Ex.: 1º mês com 50% de desconto; inclui 2 vídeos por mês"' ); ?>
	<p class="muted small">Ao salvar, o texto é montado com o modelo de Configurações → Contrato e os dados da ficha da cliente. Quando ela assinar, a mensalidade, o dia e o pacote de artes da ficha são atualizados sozinhos.</p>
	<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar e ver o contrato</button></div>
	</form>
	<?php
}

/**
 * Quadro para desenhar a assinatura (dedo ou mouse). Grava em <input name="sig">.
 */
function lk_sig_pad() {
	?>
	<div class="sig-pad" data-sig-pad>
		<span class="sig-pad-t">Assine aqui (com o dedo ou o mouse)</span>
		<canvas width="600" height="200" aria-label="Área para desenhar a assinatura"></canvas>
		<button type="button" class="sig-clear" data-sig-clear>Limpar</button>
		<input type="hidden" name="sig" data-sig-input>
	</div>
	<?php
}

/**
 * Bloco "Contratos" na ficha do cliente.
 */
function lk_client_contracts_html( $client ) {
	$rows = lk_rows( 'contracts', 'client_id = %d', array( $client->id ), 'id DESC' );
	ob_start();
	?>
	<section class="card" id="contratos">
		<div class="card-head"><h3>Contratos</h3><span class="row-btns"><button type="button" class="btn btn--ghost btn--sm" data-open="contrato-assinado">Subir contrato assinado</button><button type="button" class="btn btn--primary btn--sm" data-open="novo-contrato">+ Novo contrato</button></span></div>
		<?php if ( ! $rows ) : ?>
			<p class="muted small">Nenhum contrato ainda. Monte com os serviços, o valor e a duração, e mande para a cliente assinar online.</p>
		<?php else : ?>
			<ul class="mini-list">
				<?php foreach ( $rows as $k ) : ?>
					<li><a href="<?php echo esc_url( lk_panel_url( 'contrato', $k->id ) ); ?>"><strong><?php echo esc_html( $k->title ); ?></strong><small><?php echo esc_html( lk_money( $k->monthly_value ) . '/mês · ' . (int) $k->months . ' meses · ' . lk_contract_status_label( $k->status ) ); ?><?php echo 'concluido' === $k->status ? ' ✓' : ''; ?></small></a><?php if ( $k->drive_url ) : ?> <a class="small" href="<?php echo esc_url( $k->drive_url ); ?>" target="_blank" rel="noopener">Drive</a><?php endif; ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
	<?php
	lk_modal_start( 'novo-contrato', 'Novo contrato · ' . lk_client_label( $client ) );
	lk_contract_form( $client );
	lk_modal_end();
	lk_modal_start( 'contrato-assinado', 'Contrato já assinado · ' . lk_client_label( $client ) );
	lk_contract_upload_form( $client );
	lk_modal_end();
	return ob_get_clean();
}
