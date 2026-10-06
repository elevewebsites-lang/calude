<?php
/**
 * Briefings personalizados e pesquisa de satisfação.
 * Um mesmo montador (etapas + perguntas de vários tipos), modelos salvos, envio ao cliente (e-mail + área do cliente),
 * resposta passo a passo, cópia no Drive e resultados da pesquisa.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_form_types() {
	return array(
		'short'  => 'Resposta curta',
		'long'   => 'Resposta longa',
		'choice' => 'Múltipla escolha (uma opção)',
		'multi'  => 'Caixas de seleção (várias opções)',
		'sat'    => 'Satisfação (5 níveis)',
		'scale'  => 'Nota de 1 a 5',
		'nps'    => 'Nota de 0 a 10',
		'yesno'  => 'Sim ou não',
	);
}

function lk_form_sat_options() {
	return array( 'Muito satisfeito', 'Satisfeito', 'Neutro', 'Insatisfeito', 'Muito insatisfeito' );
}

function lk_form_kinds() {
	return array( 'briefing' => 'Briefing', 'pesquisa' => 'Pesquisa de satisfação' );
}

function lk_form_status_label( $st ) {
	return 'respondido' === $st ? 'Respondido' : 'Aguardando o cliente';
}

/** Limpa o esquema vindo do montador: etapas → perguntas, só tipos conhecidos, ids estáveis. */
function lk_form_clean_schema( $raw ) {
	$d     = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
	$types = lk_form_types();
	$out   = array( 'steps' => array() );
	$n     = 0;
	foreach ( (array) ( $d['steps'] ?? array() ) as $si => $st ) {
		if ( $si >= 30 || ! is_array( $st ) ) {
			break;
		}
		$qs = array();
		foreach ( (array) ( $st['questions'] ?? array() ) as $q ) {
			if ( $n >= 80 || ! is_array( $q ) ) {
				break;
			}
			$label = sanitize_text_field( (string) ( $q['label'] ?? '' ) );
			$type  = isset( $types[ $q['type'] ?? '' ] ) ? $q['type'] : 'short';
			if ( '' === $label ) {
				continue;
			}
			$opts = array();
			if ( in_array( $type, array( 'choice', 'multi' ), true ) ) {
				foreach ( (array) ( $q['options'] ?? array() ) as $o ) {
					$o = sanitize_text_field( (string) $o );
					if ( '' !== $o && count( $opts ) < 30 ) {
						$opts[] = $o;
					}
				}
			}
			$id  = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $q['id'] ?? '' ) ) );
			$qs[] = array(
				'id'       => $id ? $id : 'q' . ( $n + 1 ),
				'type'     => $type,
				'label'    => $label,
				'options'  => $opts,
				'required' => ! empty( $q['required'] ),
				'other'    => ! empty( $q['other'] ) && in_array( $type, array( 'choice', 'multi' ), true ),
			);
			$n++;
		}
		if ( $qs ) {
			$out['steps'][] = array( 'title' => sanitize_text_field( (string) ( $st['title'] ?? '' ) ) ?: 'Etapa ' . ( count( $out['steps'] ) + 1 ), 'questions' => $qs );
		}
	}
	// ids únicos
	$seen = array();
	foreach ( $out['steps'] as &$st ) {
		foreach ( $st['questions'] as &$q ) {
			$base = $q['id'];
			$i    = 2;
			while ( isset( $seen[ $q['id'] ] ) ) {
				$q['id'] = $base . '_' . $i++;
			}
			$seen[ $q['id'] ] = true;
		}
	}
	unset( $st, $q );
	return $out;
}

function lk_form_count_questions( $schema ) {
	$n = 0;
	foreach ( (array) ( $schema['steps'] ?? array() ) as $st ) {
		$n += count( $st['questions'] );
	}
	return $n;
}

/** Atalhos para montar o esquema nos modelos prontos. */
function lk_fq( $type, $label, $opts = array(), $req = true, $other = false ) {
	return array( 'type' => $type, 'label' => $label, 'options' => $opts, 'required' => $req, 'other' => $other );
}

/** Modelos prontos (aparecem na lista de modelos junto com os que você salvar). */
function lk_form_defaults() {
	$sat = function ( $area, $extra ) {
		return array( 'title' => $area, 'questions' => array( lk_fq( 'sat', 'Como você avalia ' . $extra . '?' ), lk_fq( 'long', 'Quer comentar algo sobre ' . mb_strtolower( $area ) . '?', array(), false ) ) );
	};
	return array(
		'briefing_inicial'  => array(
			'kind'   => 'briefing',
			'title'  => 'Briefing inicial (rápido, com múltipla escolha)',
			'intro'  => 'Responda no seu ritmo, uma etapa por vez. Quanto mais a gente souber, mais certeiros ficam os conteúdos.',
			'schema' => array( 'steps' => array(
				array( 'title' => 'Sobre a marca', 'questions' => array(
					lk_fq( 'short', 'Nome da marca ou empresa' ),
					lk_fq( 'long', 'Em poucas palavras, o que vocês fazem?' ),
					lk_fq( 'choice', 'Há quanto tempo a empresa atua?', array( 'Menos de 1 ano', '1 a 3 anos', '3 a 10 anos', 'Mais de 10 anos' ) ),
					lk_fq( 'choice', 'Qual o principal diferencial?', array( 'Preço', 'Qualidade', 'Atendimento', 'Tradição / experiência', 'Inovação', 'Localização' ), true, true ),
				) ),
				array( 'title' => 'Público', 'questions' => array(
					lk_fq( 'multi', 'Quem você quer atingir?', array( 'Jovens (18 a 24)', 'Adultos (25 a 44)', 'Adultos (45+)', 'Famílias', 'Empresas (B2B)', 'Idosos' ), true, true ),
					lk_fq( 'choice', 'Onde está o seu público?', array( 'Na minha cidade', 'Na região', 'No estado', 'Brasil todo', 'Fora do país' ) ),
					lk_fq( 'long', 'Descreva o cliente ideal', array(), false ),
				) ),
				array( 'title' => 'Objetivos', 'questions' => array(
					lk_fq( 'multi', 'O que você quer das redes sociais?', array( 'Gerar vendas / orçamentos', 'Ganhar seguidores', 'Fortalecer a marca', 'Educar o público', 'Atender clientes', 'Atrair contratações' ), true, true ),
					lk_fq( 'choice', 'Quanto pode investir em anúncios por mês?', array( 'Ainda não invisto', 'Até R$ 1.000', 'R$ 1.000 a R$ 3.000', 'R$ 3.000 a R$ 10.000', 'Acima de R$ 10.000' ) ),
					lk_fq( 'long', 'Qual resultado faria você dizer "valeu a pena" em 3 meses?', array(), false ),
				) ),
				array( 'title' => 'Estilo e comunicação', 'questions' => array(
					lk_fq( 'multi', 'Como a marca deve falar?', array( 'Profissional', 'Descontraída', 'Inspiradora', 'Técnica', 'Divertida', 'Elegante' ) ),
					lk_fq( 'yesno', 'Já tem identidade visual (logo, cores, fontes)?' ),
					lk_fq( 'short', 'Cores e referências que você gosta', array(), false ),
					lk_fq( 'short', 'Perfis que você admira ou concorrentes', array(), false ),
				) ),
				array( 'title' => 'Rotina', 'questions' => array(
					lk_fq( 'choice', 'Quem aprova os posts?', array( 'Eu mesmo(a)', 'Um sócio', 'Uma equipe', 'Pode publicar direto' ), true, true ),
					lk_fq( 'choice', 'Frequência de posts desejada', array( '2 a 3 por semana', '4 a 5 por semana', 'Todo dia', 'Ainda não sei' ) ),
					lk_fq( 'long', 'Algo que a gente nunca deve fazer ou falar?', array(), false ),
				) ),
			) ),
		),
		'briefing_campanha' => array(
			'kind'   => 'briefing',
			'title'  => 'Briefing de campanha',
			'intro'  => 'Vamos alinhar os detalhes da campanha para ela sair certeira.',
			'schema' => array( 'steps' => array(
				array( 'title' => 'A campanha', 'questions' => array(
					lk_fq( 'short', 'Nome da campanha' ),
					lk_fq( 'choice', 'Tipo de campanha', array( 'Lançamento', 'Promoção', 'Data comemorativa / sazonal', 'Captação de contatos', 'Institucional' ), true, true ),
					lk_fq( 'long', 'Qual o objetivo principal?' ),
				) ),
				array( 'title' => 'Oferta e público', 'questions' => array(
					lk_fq( 'long', 'O que está sendo oferecido? (produto, serviço, condições)' ),
					lk_fq( 'short', 'Preço, desconto ou condição especial', array(), false ),
					lk_fq( 'multi', 'Onde a campanha vai rodar?', array( 'Instagram', 'Facebook', 'Google', 'WhatsApp', 'E-mail', 'Site / loja' ), true, true ),
					lk_fq( 'long', 'Quem queremos atingir?', array(), false ),
				) ),
				array( 'title' => 'Prazo e verba', 'questions' => array(
					lk_fq( 'short', 'Data de início' ),
					lk_fq( 'short', 'Data de término', array(), false ),
					lk_fq( 'choice', 'Verba para anúncios', array( 'Sem anúncios', 'Até R$ 1.000', 'R$ 1.000 a R$ 3.000', 'R$ 3.000 a R$ 10.000', 'Acima de R$ 10.000' ) ),
					lk_fq( 'long', 'Links, fotos ou referências importantes', array(), false ),
				) ),
			) ),
		),
		'pesquisa'          => array(
			'kind'   => 'pesquisa',
			'title'  => 'Pesquisa de satisfação',
			'intro'  => 'Sua opinião nos ajuda a evoluir. Leva cerca de 3 minutos.',
			'schema' => array( 'steps' => array(
				$sat( 'Estratégia', 'a estratégia de conteúdo' ),
				$sat( 'Resultados', 'os resultados alcançados nas redes' ),
				$sat( 'Artes e design', 'as artes e o design dos posts' ),
				$sat( 'Legendas e textos', 'as legendas e os textos' ),
				$sat( 'Atendimento e comunicação', 'o atendimento e a comunicação com a equipe' ),
				$sat( 'Prazos e organização', 'o cumprimento de prazos e a organização' ),
				$sat( 'Relatórios', 'os relatórios de desempenho' ),
				array( 'title' => 'No geral', 'questions' => array(
					lk_fq( 'sat', 'Como você avalia o custo-benefício do serviço?' ),
					lk_fq( 'nps', 'De 0 a 10, o quanto você recomendaria a nossa agência a um amigo ou colega?' ),
					lk_fq( 'multi', 'O que mais você valoriza no nosso trabalho?', array( 'Criatividade', 'Resultados', 'Atendimento', 'Prazos', 'Estratégia', 'Preço' ), false, true ),
					lk_fq( 'long', 'O que podemos melhorar?', array(), false ),
				) ),
			) ),
		),
	);
}

/** Modelo pelo id ("default:chave" ou número do modelo salvo) → array( kind, title, intro, schema ) ou null. */
function lk_form_template( $ref ) {
	if ( 0 === strpos( (string) $ref, 'default:' ) ) {
		$d = lk_form_defaults()[ substr( $ref, 8 ) ] ?? null;
		return $d ? lk_form_normalize( $d ) : null;
	}
	$t = $ref ? lk_get( 'forms', (int) $ref ) : null;
	return $t ? array( 'kind' => $t->kind, 'title' => $t->title, 'intro' => (string) $t->intro, 'schema' => lk_form_clean_schema( $t->qschema ) ) : null;
}

function lk_form_normalize( $d ) {
	$d['schema'] = lk_form_clean_schema( $d['schema'] );
	return $d;
}

/* -----------------------------------------------------------------------
 * Ações do painel
 * -------------------------------------------------------------------- */

/** Salva (e opcionalmente envia) o que foi montado. acao: modelo | enviar | rascunho */
function lk_do_form_save() {
	lk_require( 'formularios' );
	$kind   = isset( lk_form_kinds()[ lk_in( 'kind' ) ] ) ? lk_in( 'kind' ) : 'briefing';
	$title  = lk_in( 'title' ) ? lk_in( 'title' ) : lk_form_kinds()[ $kind ];
	$intro  = lk_in( 'intro', 'textarea' );
	$schema = lk_form_clean_schema( lk_in( 'schema', 'raw' ) );
	if ( ! $schema['steps'] ) {
		lk_back( 'Adicione pelo menos uma pergunta.', 'erro' );
	}
	$acao      = lk_in( 'acao' );
	$client_id = lk_in( 'client_id', 'int' );
	$msg       = '';
	$back      = lk_panel_url( 'formularios' );
	$json      = wp_json_encode( $schema );
	if ( 'modelo' === $acao || lk_in( 'salvar_modelo', 'bool' ) ) {
		$tid  = lk_in( 'template_id', 'int' );
		$data = array( 'kind' => $kind, 'title' => $title, 'intro' => $intro, 'qschema' => $json );
		if ( $tid && lk_get( 'forms', $tid ) ) {
			lk_update( 'forms', $tid, $data );
		} else {
			$data['created_by'] = get_current_user_id();
			lk_insert( 'forms', $data );
		}
		$msg = 'Modelo salvo. ';
	}
	if ( in_array( $acao, array( 'enviar', 'rascunho' ), true ) ) {
		$client = $client_id ? lk_get( 'clients', $client_id ) : null;
		if ( ! $client ) {
			lk_back( 'Escolha o cliente para enviar.', 'erro' );
		}
		$sid = lk_in( 'send_id', 'int' );
		$old = $sid ? lk_get( 'form_sends', $sid ) : null;
		$row = array( 'client_id' => $client->id, 'kind' => $kind, 'title' => $title, 'intro' => $intro, 'qschema' => $json );
		if ( $old && 'pendente' === $old->status ) {
			lk_update( 'form_sends', $old->id, $row );
			$sid = $old->id;
		} else {
			$row['token']      = strtolower( wp_generate_password( 24, false ) );
			$row['status']     = 'pendente';
			$row['created_by'] = get_current_user_id();
			$sid               = lk_insert( 'form_sends', $row );
		}
		$send = lk_get( 'form_sends', $sid );
		if ( 'enviar' === $acao ) {
			$mail = lk_form_send_mail( $send );
			lk_update( 'form_sends', $sid, array( 'sent_at' => lk_now() ) );
			$msg .= 'Enviado para ' . lk_client_label( $client ) . ( $mail ? ' por e-mail e na área do cliente.' : ' na área do cliente (sem e-mail válido na ficha, então não foi avisado por e-mail).' );
		} else {
			$msg .= 'Rascunho guardado para ' . lk_client_label( $client ) . '.';
		}
		$back = lk_panel_url( 'cliente', $client->id ) . '#formularios';
	}
	lk_back( trim( $msg ) ? trim( $msg ) : 'Salvo.', 'ok', $back );
}

/** Envio rápido de um modelo (ex.: pesquisa de satisfação) para um cliente. */
function lk_do_form_quick() {
	lk_require( 'formularios' );
	$client = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	$t      = lk_form_template( lk_in( 'ref' ) );
	if ( ! $client || ! $t ) {
		lk_back( 'Escolha o cliente e o modelo.', 'erro' );
	}
	$sid = lk_insert( 'form_sends', array( 'client_id' => $client->id, 'kind' => $t['kind'], 'title' => $t['title'], 'intro' => $t['intro'], 'qschema' => wp_json_encode( $t['schema'] ), 'token' => strtolower( wp_generate_password( 24, false ) ), 'status' => 'pendente', 'created_by' => get_current_user_id(), 'sent_at' => lk_now() ) );
	$mail = lk_form_send_mail( lk_get( 'form_sends', $sid ) );
	lk_back( '"' . $t['title'] . '" enviado para ' . lk_client_label( $client ) . ( $mail ? ' por e-mail e na área do cliente.' : ' na área do cliente (sem e-mail na ficha).' ), 'ok', lk_panel_url( 'cliente', $client->id ) . '#formularios' );
}

function lk_do_form_delete() {
	lk_require( 'formularios' );
	$what = lk_in( 'what' );
	$id   = lk_in( 'id', 'int' );
	if ( 'modelo' === $what && lk_get( 'forms', $id ) ) {
		lk_delete( 'forms', $id );
		lk_back( 'Modelo excluído.' );
	}
	if ( 'envio' === $what && lk_get( 'form_sends', $id ) ) {
		lk_delete( 'form_sends', $id );
		lk_back( 'Envio excluído.' );
	}
	lk_back( 'Nada para excluir.', 'erro' );
}

function lk_do_form_resend() {
	lk_require( 'formularios' );
	$send = lk_get( 'form_sends', lk_in( 'id', 'int' ) );
	if ( ! $send ) {
		lk_back( 'Envio não encontrado.', 'erro' );
	}
	$ok = lk_form_send_mail( $send, true );
	lk_back( $ok ? 'Lembrete enviado por e-mail.' : 'O cliente não tem e-mail válido na ficha.', $ok ? 'ok' : 'erro' );
}

function lk_form_url( $send ) {
	return lk_client_url( 'formulario', $send->id );
}

function lk_form_send_mail( $send, $reminder = false ) {
	$client = lk_get( 'clients', $send->client_id );
	if ( ! $client || ! is_email( $client->email ) ) {
		return false;
	}
	$first = $client->name ? strtok( $client->name, ' ' ) : lk_client_label( $client );
	$n     = lk_form_count_questions( lk_form_clean_schema( $send->qschema ) );
	$kind  = 'pesquisa' === $send->kind ? 'pesquisa' : 'briefing';
	return lk_mail(
		$client->email,
		( $reminder ? 'Lembrete: ' : '' ) . $send->title . ' · ' . lk_setting( 'empresa' ),
		'pesquisa' === $kind ? 'Queremos ouvir você' : 'Vamos alinhar os detalhes',
		'<p>Olá, ' . esc_html( $first ) . '! ' . esc_html( $send->intro ? $send->intro : ( 'pesquisa' === $kind ? 'Sua opinião nos ajuda a evoluir.' : 'Precisamos de algumas informações para seguir.' ) ) . '</p><p>São ' . (int) $n . ' perguntas, em etapas rápidas. Você responde pela sua área do cliente.</p>',
		array( array( 'Formulário', esc_html( $send->title ) ), array( 'Perguntas', (string) (int) $n ) ),
		'Responder agora',
		lk_form_url( $send )
	);
}

/* -----------------------------------------------------------------------
 * Resposta do cliente (passo a passo)
 * -------------------------------------------------------------------- */

function lk_form_collect_answers( $schema, $post ) {
	$ans = array();
	$err = array();
	$a   = isset( $post['a'] ) && is_array( $post['a'] ) ? $post['a'] : array();
	$ao  = isset( $post['a_other'] ) && is_array( $post['a_other'] ) ? $post['a_other'] : array();
	foreach ( $schema['steps'] as $st ) {
		foreach ( $st['questions'] as $q ) {
			$id  = $q['id'];
			$raw = $a[ $id ] ?? '';
			$val = '';
			switch ( $q['type'] ) {
				case 'short':
					$val = sanitize_text_field( wp_unslash( (string) ( is_array( $raw ) ? '' : $raw ) ) );
					break;
				case 'long':
					$val = sanitize_textarea_field( wp_unslash( (string) ( is_array( $raw ) ? '' : $raw ) ) );
					break;
				case 'choice':
					$v   = sanitize_text_field( wp_unslash( (string) ( is_array( $raw ) ? '' : $raw ) ) );
					$val = in_array( $v, $q['options'], true ) ? $v : ( '__outro' === $v && $q['other'] ? 'Outro: ' . sanitize_text_field( wp_unslash( (string) ( $ao[ $id ] ?? '' ) ) ) : '' );
					break;
				case 'multi':
					$val = array();
					foreach ( (array) $raw as $v ) {
						$v = sanitize_text_field( wp_unslash( (string) $v ) );
						if ( in_array( $v, $q['options'], true ) ) {
							$val[] = $v;
						} elseif ( '__outro' === $v && $q['other'] && trim( (string) ( $ao[ $id ] ?? '' ) ) !== '' ) {
							$val[] = 'Outro: ' . sanitize_text_field( wp_unslash( (string) $ao[ $id ] ) );
						}
					}
					break;
				case 'sat':
					$v   = sanitize_text_field( wp_unslash( (string) ( is_array( $raw ) ? '' : $raw ) ) );
					$val = in_array( $v, lk_form_sat_options(), true ) ? $v : '';
					break;
				case 'scale':
					$v   = (int) $raw;
					$val = $v >= 1 && $v <= 5 ? $v : '';
					break;
				case 'nps':
					$val = '' !== (string) $raw && (int) $raw >= 0 && (int) $raw <= 10 ? (int) $raw : '';
					break;
				case 'yesno':
					$v   = sanitize_text_field( wp_unslash( (string) ( is_array( $raw ) ? '' : $raw ) ) );
					$val = in_array( $v, array( 'Sim', 'Não' ), true ) ? $v : '';
					break;
			}
			if ( $q['required'] && ( '' === $val || array() === $val ) ) {
				$err[] = $q['label'];
			}
			$ans[ $id ] = $val;
		}
	}
	return array( $ans, $err );
}

function lk_do_form_answer() {
	$send   = lk_get( 'form_sends', lk_in( 'id', 'int' ) );
	$client = $send ? lk_get( 'clients', $send->client_id ) : null;
	$me     = lk_is_client_user() ? lk_current_client() : null;
	if ( ! $send || ! $client || ! $me || (int) $me->id !== (int) $client->id ) {
		lk_back( 'Não foi possível salvar. Entre na sua área do cliente e tente de novo.', 'erro' );
	}
	if ( 'respondido' === $send->status ) {
		lk_back( 'Este formulário já foi respondido. Obrigado!', 'ok', lk_client_link( 'briefing' ) );
	}
	$schema = lk_form_clean_schema( $send->qschema );
	list( $ans, $err ) = lk_form_collect_answers( $schema, $_POST ); // phpcs:ignore WordPress.Security.NonceVerification -- lk_form já confere o nonce.
	if ( $err ) {
		lk_back( 'Falta responder: ' . implode( '; ', array_slice( $err, 0, 3 ) ) . ( count( $err ) > 3 ? '…' : '' ), 'erro', lk_form_url( $send ) );
	}
	lk_update( 'form_sends', $send->id, array( 'answers' => wp_json_encode( $ans ), 'status' => 'respondido', 'answered_at' => lk_now() ) );
	$send = lk_get( 'form_sends', $send->id );
	lk_form_archive( $send, $client );
	foreach ( array_unique( array_filter( array( get_option( 'admin_email' ), lk_setting( 'email' ) ) ) ) as $to ) {
		lk_mail( $to, lk_client_label( $client ) . ' respondeu: ' . $send->title, 'Resposta recebida', '<p><strong>' . esc_html( lk_client_label( $client ) ) . '</strong> respondeu <em>' . esc_html( $send->title ) . '</em>. Ela fica salva na ficha do cliente.</p>', array(), 'Ver as respostas', lk_panel_url( 'resposta', $send->id ) );
	}
	lk_back( 'Obrigado! Suas respostas foram enviadas.', 'ok', lk_client_link( 'briefing' ) );
}

function lk_form_answers_html( $send, $with_title = true ) {
	$schema = lk_form_clean_schema( $send->qschema );
	$ans    = json_decode( (string) $send->answers, true );
	$ans    = is_array( $ans ) ? $ans : array();
	$h      = '';
	foreach ( $schema['steps'] as $st ) {
		$h .= '<h3>' . esc_html( $st['title'] ) . '</h3>';
		foreach ( $st['questions'] as $q ) {
			$v  = $ans[ $q['id'] ] ?? '';
			$v  = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
			$h .= '<p><strong>' . esc_html( $q['label'] ) . '</strong><br>' . ( '' !== $v ? nl2br( esc_html( $v ) ) : '<em>sem resposta</em>' ) . '</p>';
		}
	}
	return ( $with_title ? '<h2>' . esc_html( $send->title ) . '</h2>' : '' ) . $h;
}

/** Cópia das respostas no Drive do cliente (se o Google estiver conectado). */
function lk_form_archive( $send, $client ) {
	if ( ! function_exists( 'lk_drive_put_html' ) ) {
		return;
	}
	$html = '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>' . esc_html( $send->title ) . '</title><body style="font-family:Arial,sans-serif;max-width:760px;margin:24px auto">' . '<p>' . esc_html( lk_client_label( $client ) ) . ' · respondido em ' . esc_html( lk_date( $send->answered_at, 'd/m/Y H:i' ) ) . '</p>' . lk_form_answers_html( $send ) . '</body></html>';
	$url  = lk_drive_put_html( array( 'Clientes', lk_client_label( $client ), 'pesquisa' === $send->kind ? 'Pesquisas' : 'Briefings' ), $send->title . ' · ' . lk_client_label( $client ) . ' · ' . current_time( 'Y-m-d' ), $html );
	if ( $url ) {
		lk_update( 'form_sends', $send->id, array( 'drive_url' => $url ) );
	}
}

/* -----------------------------------------------------------------------
 * Resultados da pesquisa de satisfação
 * -------------------------------------------------------------------- */

/** Junta as respostas das pesquisas por pergunta. Devolve lista de { label, type, n, avg, dist, texts }. */
function lk_form_results( $client_id = 0 ) {
	$rows = lk_rows( 'form_sends', "kind = 'pesquisa' AND status = 'respondido'" . ( $client_id ? ' AND client_id = %d' : '' ), $client_id ? array( $client_id ) : array(), 'answered_at DESC' );
	$out  = array();
	$sat  = array_reverse( lk_form_sat_options() ); // Muito insatisfeito = 1 … Muito satisfeito = 5
	foreach ( $rows as $s ) {
		$schema = lk_form_clean_schema( $s->qschema );
		$ans    = json_decode( (string) $s->answers, true );
		$ans    = is_array( $ans ) ? $ans : array();
		foreach ( $schema['steps'] as $st ) {
			foreach ( $st['questions'] as $q ) {
				$key = $q['type'] . '|' . $q['label'];
				if ( ! isset( $out[ $key ] ) ) {
					$out[ $key ] = array( 'label' => $q['label'], 'type' => $q['type'], 'step' => $st['title'], 'n' => 0, 'sum' => 0, 'dist' => array(), 'texts' => array() );
				}
				$v = $ans[ $q['id'] ] ?? '';
				if ( '' === $v || array() === $v ) {
					continue;
				}
				$r = &$out[ $key ];
				$r['n']++;
				if ( 'sat' === $q['type'] ) {
					$score = array_search( $v, $sat, true );
					if ( false !== $score ) {
						$r['sum'] += $score + 1;
					}
					$r['dist'][ $v ] = ( $r['dist'][ $v ] ?? 0 ) + 1;
				} elseif ( in_array( $q['type'], array( 'scale', 'nps' ), true ) ) {
					$r['sum']                  += (int) $v;
					$r['dist'][ (string) $v ] = ( $r['dist'][ (string) $v ] ?? 0 ) + 1;
				} elseif ( in_array( $q['type'], array( 'choice', 'multi', 'yesno' ), true ) ) {
					foreach ( (array) $v as $x ) {
						$r['dist'][ $x ] = ( $r['dist'][ $x ] ?? 0 ) + 1;
					}
				} elseif ( count( $r['texts'] ) < 12 ) {
					$r['texts'][] = array( (string) $v, lk_client_label( lk_get( 'clients', $s->client_id ) ?: (object) array( 'company' => '', 'name' => '' ) ) );
				}
				unset( $r );
			}
		}
	}
	return array_values( $out );
}

/** NPS (promotores − detratores) das pesquisas respondidas, ou null. */
function lk_form_nps( $results ) {
	foreach ( $results as $r ) {
		if ( 'nps' === $r['type'] && $r['n'] ) {
			$pro = 0;
			$det = 0;
			foreach ( $r['dist'] as $k => $c ) {
				if ( (int) $k >= 9 ) {
					$pro += $c;
				} elseif ( (int) $k <= 6 ) {
					$det += $c;
				}
			}
			return (int) round( ( $pro - $det ) / $r['n'] * 100 );
		}
	}
	return null;
}

/* -----------------------------------------------------------------------
 * Cartão na ficha do cliente e lista na área do cliente
 * -------------------------------------------------------------------- */

function lk_client_forms_html( $client ) {
	$rows = lk_rows( 'form_sends', 'client_id = %d', array( $client->id ), 'id DESC' );
	ob_start();
	?>
	<section class="card" id="formularios">
		<div class="card-head"><h3>Briefings e pesquisas</h3><span class="row-btns"><a class="btn btn--primary btn--sm" href="<?php echo esc_url( lk_panel_url( 'formulario', 0, array( 'novo' => 'briefing', 'cliente' => $client->id ) ) ); ?>">🧩 Montar briefing</a><?php lk_form( 'form_quick', 'inline-form' ); ?><input type="hidden" name="client_id" value="<?php echo (int) $client->id; ?>"><input type="hidden" name="ref" value="default:pesquisa"><button type="submit" class="btn btn--ghost btn--sm" data-confirm="Enviar a pesquisa de satisfação para <?php echo esc_attr( lk_client_label( $client ) ); ?>?">📊 Enviar pesquisa de satisfação</button></form></span></div>
		<?php if ( ! $rows ) : ?><p class="muted small">Nenhum briefing ou pesquisa enviado. Monte um briefing do zero, a partir de um modelo, ou envie a pesquisa de satisfação pronta.</p><?php else : ?>
		<ul class="mini-list">
			<?php foreach ( $rows as $s ) : ?>
				<li><a href="<?php echo esc_url( 'respondido' === $s->status ? lk_panel_url( 'resposta', $s->id ) : lk_panel_url( 'formulario', 0, array( 'envio' => $s->id ) ) ); ?>"><strong><?php echo esc_html( $s->title ); ?></strong><small><?php echo esc_html( lk_form_kinds()[ $s->kind ] . ' · ' . lk_form_status_label( $s->status ) . ( $s->answered_at ? ' em ' . lk_date( $s->answered_at, 'd/m/Y' ) : '' ) ); ?></small></a><?php if ( 'pendente' === $s->status ) : ?><?php lk_action_button( 'form_resend', array( 'id' => $s->id ), 'Lembrar', 'btn btn--ghost btn--sm' ); ?><?php endif; ?></li>
			<?php endforeach; ?>
		</ul>
		<?php endif; ?>
		<p class="muted small"><a href="<?php echo esc_url( lk_panel_url( 'formularios' ) ); ?>">Ver modelos, enviados e resultados da pesquisa</a></p>
	</section>
	<?php
	return ob_get_clean();
}

/** Formulários pendentes do cliente (área do cliente). */
function lk_client_pending_forms( $client ) {
	return lk_rows( 'form_sends', "client_id = %d AND status = 'pendente'", array( $client->id ), 'id DESC' );
}
