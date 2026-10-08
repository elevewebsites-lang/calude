<?php
/**
 * Contratos (extras): gerar a partir do lead/cliente, resumo das partes, cláusulas adicionais, cópia assinada por e-mail
 * e no Drive, página do cliente e lista central de contratos.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Cláusulas adicionais
 * -------------------------------------------------------------------- */

/** Bloco de texto das cláusulas adicionais (um parágrafo = uma cláusula). */
function lk_contract_clauses_block( $clauses ) {
	$items = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $clauses ) ) ) );
	if ( ! $items ) {
		return '';
	}
	$out = "## CLÁUSULAS ADICIONAIS\nAs partes acordam também o seguinte:\n";
	foreach ( $items as $i => $t ) {
		$out .= '**Cláusula adicional ' . ( $i + 1 ) . 'ª.** ' . ltrim( $t, "-• \t" ) . "\n";
	}
	return rtrim( $out );
}

/** Troca (ou insere) o bloco de cláusulas no texto do contrato sem perder edições manuais. */
function lk_contract_apply_clauses( $body, $clauses ) {
	$paras = preg_split( '/\n{2,}/', trim( (string) $body ) );
	$keep  = array();
	foreach ( $paras as $p ) {
		$t = ltrim( $p );
		if ( 0 === strpos( $t, '## CLÁUSULAS ADICIONAIS' ) || 0 === strpos( $t, '**Cláusula adicional' ) ) {
			continue; // bloco antigo (o bloco é um parágrafo só; se foi quebrado à mão, cada pedaço começa assim)
		}
		$keep[] = $p;
	}
	$block = lk_contract_clauses_block( $clauses );
	if ( $block ) {
		$last = array_pop( $keep ); // a linha "Cidade, data." fica por último
		$keep[] = $block;
		if ( null !== $last ) {
			$keep[] = $last;
		}
	}
	return implode( "\n\n", $keep );
}

function lk_do_contract_clauses() {
	lk_require( 'clientes' );
	$k = lk_get( 'contracts', lk_in( 'id', 'int' ) );
	if ( ! $k || 'rascunho' !== $k->status ) {
		lk_back( 'As cláusulas só podem mudar enquanto o contrato é rascunho.', 'erro' );
	}
	$clauses = lk_in( 'clauses', 'textarea' );
	lk_update( 'contracts', $k->id, array( 'clauses' => $clauses, 'body' => lk_contract_apply_clauses( $k->body, $clauses ) ) );
	lk_back( $clauses ? 'Cláusulas adicionais incluídas no contrato.' : 'Cláusulas adicionais removidas.' );
}

/* -----------------------------------------------------------------------
 * Gerar contrato (do lead aceito ou direto de um cliente)
 * -------------------------------------------------------------------- */

function lk_contract_draft_for( $client, $lead = null, $over = array() ) {
	$data = array(
		'client_id'     => $client->id,
		'title'         => 'Contrato de prestação de serviços de marketing digital',
		'services'      => "Planejamento mensal de conteúdo\nCriação de artes e legendas\nProgramação das postagens\nRelatório mensal de resultados",
		'monthly_value' => $lead && (float) $lead->value > 0 ? (float) $lead->value : (float) $client->monthly_fee,
		'months'        => 12,
		'start_date'    => lk_today(),
		'due_day'       => $client->due_day ? (int) $client->due_day : 10,
		'posts_quota'   => (int) $client->posts_quota,
		'token'         => strtolower( wp_generate_password( 28, false ) ),
		'created_by'    => get_current_user_id(),
		'lead_id'       => $lead ? (int) $lead->id : 0,
	);
	$data = array_merge( $data, $over );
	$id   = lk_insert( 'contracts', $data );
	$k  = lk_get( 'contracts', $id );
	lk_update( 'contracts', $id, array( 'body' => lk_contract_compose( $k ) ) );
	return $id;
}

/** Cliente aceitou o serviço (lead no funil) → gerar contrato. */
function lk_do_lead_to_contract() {
	lk_require( 'clientes' );
	$lead = lk_get( 'leads', lk_in( 'lead_id', 'int' ) );
	if ( ! $lead ) {
		lk_back( 'Lead não encontrado.', 'erro' );
	}
	$client = lk_get( 'clients', lk_lead_make_client( $lead ) );
	if ( lk_funnel_won() && lk_funnel_won() !== $lead->stage ) {
		lk_update( 'leads', $lead->id, array( 'stage' => lk_funnel_won() ) );
	}
	$id = lk_contract_draft_for( $client, $lead );
	lk_lead_log( $lead->id, 'Contrato gerado.' );
	lk_back( 'Contrato gerado com os dados do cliente e da agência. Confira, inclua cláusulas se precisar e envie para assinatura.', 'ok', lk_panel_url( 'contrato', $id ) );
}

/** Novo contrato direto da lista central. */
function lk_do_contract_new() {
	lk_require( 'clientes' );
	$client = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	if ( ! $client ) {
		lk_back( 'Escolha o cliente.', 'erro' );
	}
	$id = lk_contract_draft_for( $client );
	lk_back( 'Contrato criado. Ajuste os dados e envie para assinatura.', 'ok', lk_panel_url( 'contrato', $id ) );
}

/* -----------------------------------------------------------------------
 * Resumo das partes (tela do contrato)
 * -------------------------------------------------------------------- */

function lk_contract_parties_html( $k, $client ) {
	$cfg = lk_panel_url( 'config' ) . '#contrato';
	$row = function ( $label, $value, $fix_url = '' ) {
		$ok = '' !== trim( (string) $value );
		return '<li class="' . ( $ok ? '' : 'is-missing' ) . '"><span>' . esc_html( $label ) . '</span><b>' . ( $ok ? esc_html( $value ) : '⚠ falta preencher' . ( $fix_url ? ' · <a href="' . esc_url( $fix_url ) . '">completar</a>' : '' ) ) . '</b></li>'; // phpcs:ignore
	};
	$cl_url = lk_panel_url( 'cliente', $client->id );
	$addr   = trim( ( $client->address ?: '' ) . ( $client->city ? ', ' . $client->city : '' ) . ( $client->cep ? ', CEP ' . $client->cep : '' ), ', ' );
	$ini    = $k->start_date ? $k->start_date : lk_today();
	$fim    = gmdate( 'Y-m-d', strtotime( $ini . ' +' . max( 1, (int) $k->months ) . ' months -1 day' ) );
	$srv    = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $k->services ) ) );
	$o      = '<div class="ctr-parties">';
	$o     .= '<section class="card"><div class="card-head"><h3>Contratada · agência</h3></div><ul class="ctr-list">'
		. $row( 'Razão social', lk_setting( 'empresa_razao' ) ?: lk_setting( 'empresa' ), $cfg ) . $row( 'CNPJ', lk_setting( 'empresa_cnpj' ), $cfg ) . $row( 'Endereço', lk_setting( 'empresa_endereco' ), $cfg )
		. $row( 'Responsável', lk_setting( 'empresa_representante' ), $cfg ) . $row( 'Cidade (foro)', lk_setting( 'empresa_cidade' ), $cfg ) . '</ul></section>';
	$o     .= '<section class="card"><div class="card-head"><h3>Contratante · cliente</h3></div><ul class="ctr-list">'
		. $row( 'Empresa', lk_client_label( $client ) ) . $row( 'Responsável', $client->name, $cl_url ) . $row( 'CNPJ/CPF', $client->cnpj, $cl_url ) . $row( 'CPF do responsável', $client->rep_cpf, $cl_url )
		. $row( 'Endereço', $addr, $cl_url ) . $row( 'E-mail (assinatura)', $client->email, $cl_url ) . $row( 'WhatsApp', $client->whatsapp ) . '</ul></section>';
	$o     .= '<section class="card"><div class="card-head"><h3>Condições</h3></div><ul class="ctr-list">'
		. '<li><span>Serviços</span><b>' . ( $srv ? esc_html( implode( ' · ', $srv ) ) : 'Conforme proposta aceita' ) . '</b></li>'
		. '<li><span>Valor mensal</span><b>' . esc_html( lk_money( $k->monthly_value ) ) . '</b></li>'
		. ( (float) $k->setup_value > 0 ? '<li><span>Implantação</span><b>' . esc_html( lk_money( $k->setup_value ) ) . '</b></li>' : '' )
		. '<li><span>Prazo</span><b>' . (int) $k->months . ' meses · ' . esc_html( lk_date( $ini ) . ' a ' . lk_date( $fim ) ) . '</b></li>'
		. '<li><span>Vencimento</span><b>todo dia ' . (int) $k->due_day . '</b></li>'
		. '<li><span>Pacote</span><b>' . ( (int) $k->posts_quota ? (int) $k->posts_quota . ' artes/mês' : 'a combinar' ) . '</b></li></ul></section>';
	return $o . '</div>';
}

/* -----------------------------------------------------------------------
 * Cópia assinada: HTML autônomo, e-mail com anexo e Drive
 * -------------------------------------------------------------------- */

function lk_contract_standalone_html( $k ) {
	$client = lk_get( 'clients', $k->client_id );
	$sig    = function ( $title, $name, $doc, $img, $when, $extra ) {
		return '<div class="s"><h4>' . esc_html( $title ) . '</h4>' . ( $img ? '<img src="' . esc_attr( $img ) . '" alt="Assinatura">' : '<p><em>não assinado</em></p>' ) . '<p><strong>' . esc_html( $name ) . '</strong>' . ( $doc ? '<br>' . esc_html( $doc ) : '' ) . ( $when ? '<br>' . esc_html( lk_date( $when, 'd/m/Y H:i' ) ) : '' ) . ( $extra ? '<br><small>' . esc_html( $extra ) . '</small>' : '' ) . '</p></div>';
	};
	$h  = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>' . esc_html( $k->title . ' · ' . lk_client_label( $client ) ) . '</title><style>body{font:15px/1.6 Georgia,serif;max-width:780px;margin:30px auto;padding:0 20px;color:#111}h3{margin:24px 0 6px}.sigs{display:flex;gap:24px;flex-wrap:wrap;margin-top:30px}.s{flex:1;min-width:260px;border-top:1px solid #999;padding-top:8px}.s img{max-height:90px}.cert{margin-top:34px;padding:14px;border:1px solid #bbb;font:13px/1.5 Arial,sans-serif;word-break:break-all}</style></head><body>';
	$h .= lk_contract_html( $k->body );
	$h .= '<div class="sigs">' . $sig( 'CONTRATANTE', $k->signer_name, $k->signer_doc, $k->signer_sig, $k->signed_at, 'IP ' . $k->signer_ip ) . $sig( 'CONTRATADA', $k->agency_name ?: lk_setting( 'empresa' ), $k->agency_doc, $k->agency_sig, $k->agency_signed_at, '' ) . '</div>';
	$h .= '<div class="cert"><strong>Certificado de assinatura eletrônica</strong><br>E-mail confirmado por código: ' . esc_html( $k->signer_email ) . '<br>Navegador: ' . esc_html( $k->signer_ua ) . '<br>Código do documento (SHA-256): ' . esc_html( $k->hash ) . '<br>Assinatura eletrônica nos termos da Lei 14.063/2020 e da MP 2.200-2/2001, art. 10, § 2º.</div></body></html>';
	return $h;
}

/** Envia o arquivo como Google Doc em Clientes/<cliente>/Contratos. Devolve o link ('' se não deu). */
function lk_drive_put_html( $path, $name, $html ) {
	if ( ! function_exists( 'lk_google_connected' ) || ! lk_google_connected() ) {
		return '';
	}
	$folder = lk_drive_folder( $path );
	if ( is_wp_error( $folder ) ) {
		return '';
	}
	$b    = 'lkb' . wp_generate_password( 14, false );
	$meta = wp_json_encode( array( 'name' => $name, 'parents' => array( $folder ), 'mimeType' => 'application/vnd.google-apps.document' ) );
	$body = "--$b\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n--$b\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n$html\r\n--$b--";
	$res  = lk_google_api( 'POST', 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,webViewLink', null, null, array( 'type' => 'multipart/related; boundary=' . $b, 'body' => $body ) );
	return is_wp_error( $res ) || empty( $res['webViewLink'] ) ? '' : $res['webViewLink'];
}

/** Depois de uma assinatura: cópia por e-mail (com anexo), Drive e perfil do cliente. $final = as duas partes assinaram. */
function lk_contract_archive( $k, $final ) {
	$client = lk_get( 'clients', $k->client_id );
	if ( ! $client ) {
		return;
	}
	$html = lk_contract_standalone_html( $k );
	$drive = lk_drive_put_html( array( 'Clientes', lk_client_label( $client ), 'Contratos' ), ( $final ? 'Contrato assinado' : 'Contrato (assinado pelo cliente)' ) . ' · ' . lk_client_label( $client ) . ' · ' . current_time( 'Y-m-d' ), $html );
	if ( $drive ) {
		lk_update( 'contracts', $k->id, array( 'drive_url' => $drive ) );
	}
	$up   = wp_upload_dir();
	$file = trailingslashit( $up['basedir'] ) . 'lk-contrato-' . $k->id . '-' . wp_generate_password( 8, false ) . '.html';
	file_put_contents( $file, $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$attach = function ( $args ) use ( $file ) {
		$args['attachments'] = array_merge( (array) ( $args['attachments'] ?? array() ), array( $file ) );
		return $args;
	};
	add_filter( 'wp_mail', $attach );
	$rows = array( array( 'Contratante', esc_html( lk_client_label( $client ) ) ), array( 'Valor mensal', esc_html( lk_money( $k->monthly_value ) ) ), array( 'Assinado em', esc_html( lk_date( $k->signed_at, 'd/m/Y H:i' ) ) ), array( 'Código (SHA-256)', esc_html( substr( $k->hash, 0, 16 ) . '…' ) ) );
	$body = '<hr style="border:0;border-top:1px solid #e7e7e3;margin:22px 0"><div style="font-size:13px">' . lk_contract_html( $k->body ) . '</div>';
	if ( $final ) {
		$to = array_unique( array_filter( array( $k->signer_email, get_option( 'admin_email' ), lk_setting( 'email' ) ) ) );
		foreach ( $to as $email ) {
			lk_mail( $email, 'Contrato assinado · ' . lk_client_label( $client ), 'Contrato assinado ✓', '<p>O contrato entre ' . esc_html( lk_setting( 'empresa' ) ) . ' e ' . esc_html( lk_client_label( $client ) ) . ' foi assinado pelas duas partes. Segue a cópia completa abaixo e o arquivo em anexo (abra e use <em>Imprimir → Salvar em PDF</em>). Ele também fica guardado na área do cliente' . ( $drive ? ' e no Google Drive' : '' ) . '.</p>', $rows, 'Ver o contrato assinado', lk_contract_url( $k ), $body );
		}
	} else {
		if ( is_email( $k->signer_email ) ) {
			lk_mail( $k->signer_email, 'Recebemos a sua assinatura · ' . lk_setting( 'empresa' ), 'Assinatura recebida ✓', '<p>Obrigado! Recebemos a sua assinatura. Assim que a ' . esc_html( lk_setting( 'empresa' ) ) . ' assinar, você recebe a versão final. Já deixamos uma cópia abaixo e em anexo, e ela fica na sua área do cliente.</p>', $rows, 'Ver o contrato', lk_contract_url( $k ), $body );
		}
		foreach ( array_unique( array_filter( array( get_option( 'admin_email' ), lk_setting( 'email' ) ) ) ) as $email ) {
			lk_mail( $email, 'Cliente assinou o contrato · ' . lk_client_label( $client ), 'Falta a assinatura da agência', '<p><strong>' . esc_html( lk_client_label( $client ) ) . '</strong> assinou o contrato. Falta a assinatura da ' . esc_html( lk_setting( 'empresa' ) ) . '. A cópia está em anexo.</p>', $rows, 'Abrir o contrato', lk_panel_url( 'contrato', $k->id ) );
		}
	}
	remove_filter( 'wp_mail', $attach );
	wp_delete_file( $file );
}

/* -----------------------------------------------------------------------
 * Briefing: cópia no Drive e resumo na área do cliente
 * -------------------------------------------------------------------- */

add_action( 'lk_briefing_saved', 'lk_briefing_drive_sync' );
function lk_briefing_drive_sync( $client_id ) {
	$c = lk_get( 'clients', $client_id );
	if ( ! $c || ! function_exists( 'lk_google_connected' ) || ! lk_google_connected() ) {
		return;
	}
	$h = '<h2>Briefing · ' . esc_html( lk_client_label( $c ) ) . '</h2><p>Atualizado em ' . esc_html( lk_date( $c->briefing_at, 'd/m/Y H:i' ) ) . '</p>';
	foreach ( lk_briefing_answers( $c ) as $i => $r ) {
		$h .= '<h4>' . ( $i + 1 ) . '. ' . esc_html( $r['q'] ) . '</h4><p>' . nl2br( esc_html( $r['a'] ?: '—' ) ) . '</p>';
	}
	$map = (array) get_option( 'lk_briefing_docs', array() );
	if ( ! empty( $map[ $client_id ] ) ) {
		lk_google_api( 'DELETE', 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $map[ $client_id ] ) ); // troca a versão antiga
	}
	$url = lk_drive_put_html( array( 'Clientes', lk_client_label( $c ), 'Briefing' ), 'Briefing · ' . lk_client_label( $c ), '<!doctype html><html><meta charset="utf-8"><body>' . $h . '</body></html>' );
	if ( $url && preg_match( '#/d/([\w-]+)#', $url, $m ) ) {
		$map[ $client_id ] = $m[1];
		update_option( 'lk_briefing_docs', $map, false );
	}
}

/* -----------------------------------------------------------------------
 * Página do cliente: Contratos
 * -------------------------------------------------------------------- */

function lk_client_contract_rows( $client ) {
	return lk_rows( 'contracts', "client_id = %d AND status IN ('enviado','assinado','concluido')", array( $client->id ), 'id DESC' );
}
