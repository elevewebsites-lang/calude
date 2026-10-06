<?php
/**
 * AllPrint: catálogo por m², pedido feito pelo cliente, revisão da arte, baixa de estoque e avisos.
 *
 * Regras (tabela 2026 + briefing):
 * - preço por m² do material; mínimo de 1 m² POR PEDIDO somando as áreas do mesmo material; acima disso, área exata;
 * - não cobra desperdício da bobina;
 * - reforço e ilhós avulso: R$/metro linear só nos lados escolhidos (em lonas sem acabamento incluso);
 * - laminação: R$/m² (também com mínimo de 1 m² somado);
 * - corte complexo e urgência: não cobram, só informam a produção.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ap_catalog_kinds() {
	return array( 'adesivo' => 'Adesivo', 'lona' => 'Lona', 'outro' => 'Outro' );
}

function ap_catalog_groups() {
	return array( 'impresso' => 'Material impresso', 'sem-impressao' => 'Material sem impressão', 'outros' => 'Chapas e outros', 'servico' => 'Serviços', 'revenda' => 'Revenda' );
}

/**
 * Tabela 2026 (entra uma vez, na instalação). Depois é editada em Catálogo.
 */
function ap_catalog_seed() {
	if ( get_option( 'ap_catalog_seeded' ) || ap_rows( 'catalog', '1=1' ) ) {
		return;
	}
	$std  = '106 / 127 / 152';
	$lona = '100 / 120 / 140 / 160';
	$rows = array(
		array( 'impresso', 'Adesivo Padrão', 'indicação do fabricante: 1 ano', $std, 35, 'adesivo', 1, 0, '' ),
		array( 'impresso', 'Adesivo Premium', 'indicação do fabricante: 2 anos', $std, 42, 'adesivo', 1, 0, '' ),
		array( 'impresso', 'Adesivo Stoplight Brilho/Fosco', '', $std, 42, 'adesivo', 1, 0, '' ),
		array( 'impresso', 'Adesivo Transparente ou Jateado', 'transparente 106/127/152 · jateado 122', '106 / 122 / 127 / 152', 45, 'adesivo', 1, 0, '' ),
		array( 'impresso', 'Adesivo Impresso com corte eletrônico', '', $std, 65, 'adesivo', 1, 0, '' ),
		array( 'impresso', 'Adesivo Perfurado', 'promocional', '137', 42, 'adesivo', 0, 0, '' ),
		array( 'impresso', 'Adesivo Perfurado Premium', '', '106 / 137', 55, 'adesivo', 0, 0, '' ),
		array( 'impresso', 'Lona 440g com Acabamento (banner/faixa)', 'banner: bolsa, bastão de madeira, ponteira branca e cordinha · faixa: ilhós em cada ponta e bastão sem ponteira', $lona, 45, 'lona', 0, 0, 'Banner: bolsa, bastão de madeira, ponteira branca e cordinha. Faixa: um ilhós em cada ponta e bastão sem ponteira.' ),
		array( 'impresso', 'Lona 440g sem Acabamento', '', $lona, 39, 'lona', 0, 1, '' ),
		array( 'impresso', 'Lona 440g com Acabamento (reforço e ilhós de alumínio)', 'reforço e ilhós já inclusos', $lona, 42, 'lona', 0, 0, 'Reforço e ilhós de alumínio já inclusos.' ),
		array( 'impresso', 'Lona Backlight Premium 440g', '', '160', 85, 'lona', 0, 1, '' ),
		array( 'sem-impressao', 'Adesivo Padrão Brilho ou Fosco', '', $std, 16, 'adesivo', 0, 0, '' ),
		array( 'sem-impressao', 'Adesivo Premium ou Stoplight Brilho ou Fosco', '', $std, 21, 'adesivo', 0, 0, '' ),
		array( 'sem-impressao', 'Adesivo Transparente ou Jateado', 'transparente 106/127/152 · jateado 122', '106 / 122 / 127 / 152', 28, 'adesivo', 0, 0, '' ),
		array( 'sem-impressao', 'Lona Front 440g', '', $lona, 21, 'lona', 0, 1, '' ),
	);
	foreach ( $rows as $i => $r ) {
		ap_insert(
			'catalog',
			array(
				'grp'              => $r[0],
				'name'             => $r[1],
				'note'             => $r[2],
				'widths'           => $r[3],
				'price_m2'         => $r[4],
				'kind'             => $r[5],
				'allow_lamination' => $r[6],
				'allow_eyelets'    => $r[7],
				'included'         => $r[8],
				'position'         => $i,
			)
		);
	}
	update_option( 'ap_catalog_seeded', 1, false );
}
add_action( 'init', 'ap_catalog_seed', 100 );

function ap_catalog( $only_active = true, $online_only = false ) {
	$where = array();
	if ( $only_active ) {
		$where[] = 'active = 1';
	}
	if ( $online_only ) {
		$where[] = 'online = 1';
	}
	return ap_rows( 'catalog', $where ? implode( ' AND ', $where ) : '1=1', array(), 'grp, position, id' );
}

/** Tabela de preço do cliente: terceirizado, empresa ou pessoa física. (A chave interna 'parceiro' é a tabela de terceirizado.) */
function ap_client_tier( $client ) {
	$k = $client ? ap_norm( $client->kind ) : '';
	if ( 'empresa' === $k ) {
		return 'empresa';
	}
	if ( 'cliente p f' === $k || 'pf' === $k || 'pessoa fisica' === $k ) {
		return 'pf';
	}
	return 'parceiro';
}

function ap_tiers() {
	return array( 'parceiro' => 'Terceirizado (revenda)', 'empresa' => 'Empresa', 'pf' => 'Pessoa física' );
}

/** Preço por unidade (m², un ou m linear) na tabela escolhida; cai para o do terceirizado se a tabela estiver vazia. */
function ap_row_price( $row, $tier = 'parceiro' ) {
	$v = (float) $row->price_m2;
	if ( 'empresa' === $tier && (float) $row->price_empresa > 0 ) {
		$v = (float) $row->price_empresa;
	} elseif ( 'pf' === $tier && (float) $row->price_pf > 0 ) {
		$v = (float) $row->price_pf;
	}
	return $v;
}

function ap_max_width( $row ) {
	preg_match_all( '/\d+/', (string) $row->widths, $m );
	return $m[0] ? max( array_map( 'intval', $m[0] ) ) : 0;
}

/**
 * Dados do catálogo para o JavaScript do pedido.
 */
function ap_catalog_js( $all = false ) {
	$out = array();
	foreach ( ap_catalog( true, ! $all ) as $c ) {
		$out[ $c->id ] = array(
			'name'     => $c->name,
			'grp'      => $c->grp,
			'unit'     => $c->unit ? $c->unit : 'm2',
			'prices'   => array( 'parceiro' => (float) $c->price_m2, 'empresa' => ap_row_price( $c, 'empresa' ), 'pf' => ap_row_price( $c, 'pf' ) ),
			'price'    => (float) $c->price_m2,
			'maxw'     => ap_max_width( $c ),
			'widths'   => $c->widths,
			'lam'      => (int) $c->allow_lamination,
			'eyelets'  => (int) $c->allow_eyelets,
			'included' => (string) $c->included,
			'kind'     => $c->kind,
		);
	}
	return array(
		'items'   => $out,
		'eyelet'  => ap_num_setting( 'preco_ilhos' ),
		'lam'     => ap_num_setting( 'preco_laminacao' ),
		'minArea' => ap_num_setting( 'area_minima' ),
	);
}

/**
 * Preço de um conjunto de itens.
 * item: [material, w, h (cm), qty, sides:[t,b,l,r], lam, cut, obs]
 * Devolve linhas por item + cobranças por material (com o mínimo) + total.
 */
function ap_price_items( $items, $tier = 'parceiro', $apply_min = true ) {
	$cat = array();
	foreach ( ap_catalog( false ) as $c ) {
		$cat[ $c->id ] = $c;
	}
	$min       = $apply_min ? max( 0, ap_num_setting( 'area_minima' ) ) : 0;
	$eye_price = ap_num_setting( 'preco_ilhos' );
	$lam_price = ap_num_setting( 'preco_laminacao' );
	$by_mat    = array();
	$lam_area  = 0;
	$eye_m     = 0;
	$lines     = array();
	$errors    = array();
	$notes     = array();
	$charges   = array();
	$total     = 0;
	foreach ( $items as $i => $it ) {
		$c = $cat[ (int) $it['material'] ] ?? null;
		if ( ! $c ) {
			continue;
		}
		$unit  = $c->unit ? $c->unit : 'm2';
		$price = ap_row_price( $c, $tier );
		$q     = max( 1, (int) $it['qty'] );
		$ov    = ( isset( $it['override'] ) && null !== $it['override'] && '' !== $it['override'] ) ? round( (float) $it['override'], 2 ) : null;
		if ( 'm2' !== $unit ) {
			// Por unidade, metro ou serviço: quantidade × preço.
			$v         = null !== $ov ? $ov : round( $q * $price, 2 );
			$charges[] = array( 'label' => $c->name . ( 'm2' !== $unit ? ' · ' . $q . ( 'm' === $unit ? ' m' : ' un' ) : '' ) . ( null !== $ov ? ' (valor ajustado)' : '' ), 'area' => 0, 'billed' => 0, 'value' => $v, 'min' => false );
			$total    += $v;
			$lines[]   = array( 'material' => $c->name, 'area' => 0, 'eyelet_m' => 0, 'lam' => false );
			continue;
		}
		$w    = max( 0, (float) $it['w'] );
		$h    = max( 0, (float) $it['h'] );
		$area = $w * $h / 10000 * $q;
		$maxw = ap_max_width( $c );
		if ( $maxw && min( $w, $h ) > $maxw ) {
			// Sem limite: a gráfica faz com emenda. Só avisa (não bloqueia).
			$notes[] = 'Item ' . ( $i + 1 ) . ': passa da largura da bobina (' . $maxw . ' cm), será feito com emenda.';
		}
		$sides = (array) ( $it['sides'] ?? array() );
		$lin   = 0;
		if ( $c->allow_eyelets ) {
			foreach ( array( 't' => $w, 'b' => $w, 'l' => $h, 'r' => $h ) as $sd => $len ) {
				if ( ! empty( $sides[ $sd ] ) ) {
					$lin += $len / 100;
				}
			}
			$lin *= $q;
		}
		$lam = $c->allow_lamination && ! empty( $it['lam'] );
		$lines[] = array( 'material' => $c->name, 'area' => round( $area, 4 ), 'eyelet_m' => round( $lin, 2 ), 'lam' => $lam );
		if ( null !== $ov ) {
			$charges[] = array( 'label' => $c->name . ' (valor ajustado)', 'area' => round( $area, 4 ), 'billed' => round( $area, 4 ), 'value' => $ov, 'min' => false );
			$total    += $ov;
			continue;
		}
		$by_mat[ $c->id ] = ( $by_mat[ $c->id ] ?? 0 ) + $area;
		$eye_m           += $lin;
		if ( $lam ) {
			$lam_area += $area;
		}
	}
	foreach ( $by_mat as $mid => $area ) {
		$bill      = max( $min, $area );
		$v         = round( $bill * ap_row_price( $cat[ $mid ], $tier ), 2 );
		$charges[] = array( 'label' => $cat[ $mid ]->name, 'area' => round( $area, 4 ), 'billed' => round( $bill, 4 ), 'value' => $v, 'min' => $area < $min );
		$total    += $v;
	}
	if ( $lam_area > 0 ) {
		$bill      = max( $min, $lam_area );
		$v         = round( $bill * $lam_price, 2 );
		$charges[] = array( 'label' => 'Laminação', 'area' => round( $lam_area, 4 ), 'billed' => round( $bill, 4 ), 'value' => $v, 'min' => $lam_area < $min );
		$total    += $v;
	}
	if ( $eye_m > 0 ) {
		$v         = round( $eye_m * $eye_price, 2 );
		$charges[] = array( 'label' => 'Reforço e ilhós (' . number_format( $eye_m, 2, ',', '.' ) . ' m linear)', 'area' => 0, 'billed' => 0, 'value' => $v, 'min' => false );
		$total    += $v;
	}
	return array( 'lines' => $lines, 'charges' => $charges, 'total' => round( $total, 2 ), 'errors' => $errors, 'notes' => $notes );
}

/**
 * Nome do arquivo no padrão da AllPrint: 1x_Adesivo_fosco_corte simples_100x100cm.pdf
 */
function ap_file_name( $qty, $material, $finish, $w, $h, $orig ) {
	$ext  = strtolower( pathinfo( (string) $orig, PATHINFO_EXTENSION ) );
	$name = (int) $qty . 'x_' . $material . '_' . $finish . '_' . rtrim( rtrim( number_format( (float) $w, 1, '.', '' ), '0' ), '.' ) . 'x' . rtrim( rtrim( number_format( (float) $h, 1, '.', '' ), '0' ), '.' ) . 'cm';
	$name = preg_replace( '/[\\\\\/:*?"<>|]+/', '-', remove_accents( $name ) );
	return $name . ( $ext ? '.' . $ext : '' );
}

/* -----------------------------------------------------------------------
 * Painel: catálogo
 * -------------------------------------------------------------------- */

function ap_do_catalog_save() {
	ap_require( 'produtos_cat' );
	$id   = ap_in( 'id', 'int' );
	$data = array(
		'grp'              => isset( ap_catalog_groups()[ ap_in( 'grp' ) ] ) ? ap_in( 'grp' ) : 'impresso',
		'name'             => ap_in( 'name' ),
		'note'             => ap_in( 'note' ),
		'widths'           => ap_in( 'widths' ),
		'price_m2'         => ap_in( 'price_m2', 'money' ),
		'kind'             => isset( ap_catalog_kinds()[ ap_in( 'kind' ) ] ) ? ap_in( 'kind' ) : 'outro',
		'allow_lamination' => ap_in( 'allow_lamination', 'bool' ),
		'allow_eyelets'    => ap_in( 'allow_eyelets', 'bool' ),
		'included'         => ap_in( 'included', 'textarea' ),
		'supply_id'        => ap_in( 'supply_id', 'int' ),
		'position'         => ap_in( 'position', 'int' ),
		'active'           => ap_in( 'active', 'bool' ),
	);
	if ( ! $data['name'] ) {
		ap_back( 'Dê um nome ao material.', 'erro' );
	}
	if ( $id ) {
		ap_update( 'catalog', $id, $data );
	} else {
		ap_insert( 'catalog', $data );
	}
	ap_back( 'Catálogo salvo.' );
}

/* -----------------------------------------------------------------------
 * Pedido feito pelo cliente
 * -------------------------------------------------------------------- */

/**
 * Dados de um arquivo enviado (ele mora no Google Drive; aqui só ficam o link, o nome e uma miniatura pequena
 * gerada no navegador, guardada no próprio pedido: nenhum arquivo ocupa a hospedagem).
 */
function ap_clean_file_meta( $f ) {
	$out = array(
		'id'   => sanitize_text_field( $f['id'] ),
		'name' => sanitize_text_field( $f['name'] ?? '' ),
		'size' => (int) ( $f['size'] ?? 0 ),
		'link' => esc_url_raw( $f['link'] ?? '' ),
	);
	$thumb = (string) ( $f['thumb'] ?? '' );
	if ( $thumb && strlen( $thumb ) <= 45000 && preg_match( '#^data:image/(jpeg|png);base64,[A-Za-z0-9+/=]+$#', $thumb ) ) {
		$out['thumb'] = $thumb;
	}
	foreach ( array( 'pw', 'ph' ) as $k ) {
		if ( isset( $f[ $k ] ) && is_numeric( $f[ $k ] ) && $f[ $k ] > 0 && $f[ $k ] < 100000 ) {
			$out[ $k ] = round( (float) $f[ $k ], 2 );
		}
	}
	if ( ! empty( $f['note'] ) ) {
		$out['note'] = sanitize_text_field( mb_substr( (string) $f['note'], 0, 200 ) );
	}
	return $out;
}

/**
 * Lê os itens do formulário do pedido (i[material][], i[w][], …).
 */
function ap_order_items_from_post() {
	$raw   = isset( $_POST['i'] ) ? (array) wp_unslash( $_POST['i'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	$items = array();
	foreach ( (array) ( $raw['material'] ?? array() ) as $k => $mat ) {
		if ( ! absint( $mat ) ) {
			continue;
		}
		$files = json_decode( (string) ( $raw['files'][ $k ] ?? '[]' ), true );
		$items[] = array(
			'material' => absint( $mat ),
			'w'        => (float) str_replace( ',', '.', (string) ( $raw['w'][ $k ] ?? 0 ) ),
			'h'        => (float) str_replace( ',', '.', (string) ( $raw['h'][ $k ] ?? 0 ) ),
			'qty'      => max( 1, absint( $raw['qty'][ $k ] ?? 1 ) ),
			'sides'    => array_filter(
				array(
					't' => ! empty( $raw['st'][ $k ] ),
					'b' => ! empty( $raw['sb'][ $k ] ),
					'l' => ! empty( $raw['sl'][ $k ] ),
					'r' => ! empty( $raw['sr'][ $k ] ),
				)
			),
			'lam'      => ! empty( $raw['lam'][ $k ] ),
			'cut'      => 'complexo' === ( $raw['cut'][ $k ] ?? '' ) ? 'complexo' : 'simples',
			'finish'   => ( ( $cm = ap_get( 'catalog', absint( $mat ) ) ) && preg_match( '/banner/i', (string) $cm->name ) ) ? sanitize_text_field( $raw['finish'][ $k ] ?? '' ) : '',
			'obs'      => sanitize_textarea_field( $raw['obs'][ $k ] ?? '' ),
			'override' => isset( $raw['ov'][ $k ] ) && '' !== trim( (string) $raw['ov'][ $k ] ) ? ap_parse_money( $raw['ov'][ $k ] ) : null,
			'files'    => is_array( $files ) ? array_values(
				array_filter(
					array_map(
						function ( $f ) {
							return is_array( $f ) && ! empty( $f['id'] ) ? ap_clean_file_meta( $f ) : null;
						},
						$files
					)
				)
			) : array(),
		);
	}
	return $items;
}

function ap_do_client_order() {
	$client = ap_current_client();
	if ( ! $client ) {
		wp_die( 'Entre na sua conta para fazer pedidos.' );
	}
	if ( ! $client->approved ) {
		ap_back( 'Seu cadastro ainda está em análise. Assim que for aprovado, você já pode fazer pedidos.', 'erro' );
	}
	$items = ap_order_items_from_post();
	if ( ! $items ) {
		ap_back( 'Adicione pelo menos um item.', 'erro' );
	}
	$price = ap_price_items( $items, ap_client_tier( $client ) );
	if ( $price['errors'] ) {
		ap_back( implode( ' ', $price['errors'] ), 'erro' );
	}
	foreach ( $items as $i => $it ) {
		if ( ! $it['files'] && ! ap_in( 'sem_arquivo', 'bool' ) ) {
			ap_back( 'Envie o arquivo do item ' . ( $i + 1 ) . ' (ou marque que vai mandar depois).', 'erro' );
		}
	}
	$cat = array();
	foreach ( ap_catalog( false ) as $c ) {
		$cat[ $c->id ] = $c;
	}
	$saved = array();
	foreach ( $items as $i => $it ) {
		$c       = $cat[ $it['material'] ];
		$extras  = array_filter(
			array(
				$it['sides'] ? 'reforço e ilhós: ' . implode( '/', array_map( function ( $k ) { return array( 't' => 'cima', 'b' => 'baixo', 'l' => 'esquerda', 'r' => 'direita' )[ $k ]; }, array_keys( $it['sides'] ) ) ) : '',
				$it['lam'] ? 'laminação' : '',
				'corte ' . $it['cut'],
				$it['finish'],
			)
		);
		$saved[] = array(
			'kind'     => 'impressao',
			'name'     => $c->name . ' · ' . rtrim( rtrim( number_format( $it['w'], 1, ',', '' ), '0' ), ',' ) . '×' . rtrim( rtrim( number_format( $it['h'], 1, ',', '' ), '0' ), ',' ) . ' cm',
			'desc'     => ucfirst( implode( ' · ', $extras ) ) . ( $it['obs'] ? ' · ' . $it['obs'] : '' ),
			'qty'      => $it['qty'],
			'unit'     => round( ap_row_price( $cat[ $it['material'] ], ap_client_tier( $client ) ) * $it['w'] * $it['h'] / 10000, 2 ),
			'cost'     => 0,
			'material' => $it['material'],
			'w'        => $it['w'],
			'h'        => $it['h'],
			'area'     => round( $it['w'] * $it['h'] / 10000 * $it['qty'], 4 ),
			'sides'    => $it['sides'],
			'lam'      => $it['lam'],
			'cut'      => $it['cut'],
			'files'    => $it['files'],
		);
	}
	$d = ap_discounts( $client->id, $price['total'], ap_in( 'cupom' ), (bool) ap_in( 'usar_credito', 'bool' ) );
	if ( ap_in( 'cupom' ) && ! $d['coupon'] ) {
		ap_back( $d['coupon_msg'] . ' Tire o cupom ou corrija o código e finalize de novo.', 'erro' );
	}
	$pay_later = $client->pay_later && 'retirada' === ap_in( 'pagamento' );
	$free      = $d['total'] <= 0;
	$cols      = array_keys( ap_columns() );
	$urgent    = ap_in( 'urgente', 'bool' );
	$pid       = ap_insert(
		'projects',
		array(
			'client_id'     => $client->id,
			'title'         => ap_in( 'titulo' ) ? ap_in( 'titulo' ) : ( count( $saved ) . ' item(ns) · ' . $cat[ $items[0]['material'] ]->name ),
			'status'        => ( $pay_later || $free ) ? ( $cols[1] ?? $cols[0] ) : $cols[0],
			'value'         => round( $price['total'] - $d['discount'], 2 ),
			'coupon_code'   => $d['coupon'] ? $d['coupon']->code : '',
			'discount'      => $d['discount'],
			'credit_used'   => $d['credit_used'],
			'items'         => wp_json_encode( $saved ),
			'notes'         => wp_json_encode( $price['charges'] ),
			'delivery_mode' => 'retirada',
			'urgent'        => $urgent ? 1 : 0,
			'start_date'    => ap_today(),
			'due_date'      => ap_next_business_day( ap_today(), max( 1, (int) ap_setting( 'prazo_dias' ) ) ),
		)
	);
	$trans = 0;
	if ( $d['credit_used'] > 0 ) {
		ap_insert( 'transactions', array( 'type' => 'in', 'project_id' => $pid, 'client_id' => $client->id, 'category' => 'Pedido', 'description' => 'Pedido #' . $pid . ' (crédito na loja)', 'amount' => $d['credit_used'], 'due_date' => ap_today(), 'paid_at' => ap_today(), 'method' => 'Crédito na loja', 'status' => 'pago' ) );
	}
	if ( $d['total'] > 0 ) {
		$trans = ap_insert(
			'transactions',
			array(
				'type'        => 'in',
				'project_id'  => $pid,
				'client_id'   => $client->id,
				'category'    => 'Pedido',
				'description' => 'Pedido #' . $pid,
				'amount'      => $d['total'],
				'due_date'    => ap_today(),
				'method'      => $pay_later ? 'Na retirada' : 'Pix',
				'status'      => 'pendente',
			)
		);
	}
	ap_discounts_commit( $d, $pid, $client->id );
	ap_log( $pid, 'Pedido feito pelo cliente: ' . ap_money( $price['total'] ) . ( $d['discount'] > 0 ? ' − desconto ' . ap_money( $d['discount'] ) : '' ) . ( $d['credit_used'] > 0 ? ' − crédito ' . ap_money( $d['credit_used'] ) : '' ) . ( $urgent ? ' · URGENTE' : '' ) . ( $pay_later ? ' · paga na retirada' : '' ) . '.', true );
	ap_drive_label_order( $pid, $client, $saved );
	ap_email_welcome( $pid );
	ap_notify_team( $pid, 'Pedido novo #' . $pid . ( $urgent ? ' (URGENTE)' : '' ), ap_client_label( $client ) . ' fez um pedido de ' . ap_money( $price['total'] - $d['discount'] ) . '.' . ( $pay_later ? ' Vai pagar na retirada.' : ' Aguardando o pagamento.' ) );
	if ( $free ) {
		ap_back( 'Pedido #' . $pid . ' recebido! Foi pago com o seu crédito e já está na revisão da arte.', 'ok', ap_client_url( 'projeto', $pid ) );
	}
	if ( $pay_later ) {
		ap_back( 'Pedido #' . $pid . ' recebido! Já está na revisão da arte. O pagamento fica para a retirada.', 'ok', ap_client_url( 'projeto', $pid ) );
	}
	wp_safe_redirect( ap_pay_url( $trans ) );
	exit;
}

function ap_next_business_day( $date, $days = 1 ) {
	$t = strtotime( $date );
	while ( $days > 0 ) {
		$t = strtotime( '+1 day', $t );
		if ( (int) gmdate( 'N', $t ) < 6 ) {
			$days--;
		}
	}
	return gmdate( 'Y-m-d', $t );
}

/**
 * Cliente reenvia arquivo (depois de "arte com problema" ou quando mandou depois).
 */
function ap_do_client_files() {
	$client = ap_current_client();
	$p      = $client ? ap_get( 'projects', ap_in( 'id', 'int' ) ) : null;
	if ( ! $p || (int) $p->client_id !== (int) $client->id ) {
		wp_die( 'Sem permissão.' );
	}
	$items = ap_order_items( $p );
	$raw   = isset( $_POST['f'] ) ? (array) wp_unslash( $_POST['f'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	$added = 0;
	foreach ( $raw as $k => $json ) {
		$files = json_decode( (string) $json, true );
		if ( isset( $items[ $k ] ) && is_array( $files ) ) {
			foreach ( $files as $f ) {
				if ( ! empty( $f['id'] ) ) {
					$items[ $k ]['files'][] = ap_clean_file_meta( $f ) + array( 'new' => 1 );
					$added++;
				}
			}
		}
	}
	if ( $added ) {
		ap_update( 'projects', $p->id, array( 'items' => wp_json_encode( $items ), 'art_status' => 'pendente' ) );
		ap_log( $p->id, 'Cliente enviou ' . $added . ' arquivo(s) novo(s). ' . ( ap_in( 'msg', 'textarea' ) ? 'Recado: ' . ap_in( 'msg', 'textarea' ) : '' ), true );
		ap_notify_team( $p->id, 'Arquivo novo no pedido #' . $p->id, ap_client_label( $client ) . ' enviou ' . $added . ' arquivo(s) novo(s) para conferir.' );
	}
	ap_back( $added ? 'Arquivo(s) enviado(s). Vamos conferir e te avisamos.' : 'Nenhum arquivo enviado.' );
}

/* -----------------------------------------------------------------------
 * Revisão da arte
 * -------------------------------------------------------------------- */

function ap_do_art_review() {
	ap_require( 'projetos' );
	$p = ap_get( 'projects', ap_in( 'id', 'int' ) );
	if ( ! $p ) {
		ap_back();
	}
	$client = $p->client_id ? ap_get( 'clients', $p->client_id ) : null;
	$ok     = 'aprovada' === ap_in( 'decisao' );
	$note   = ap_in( 'nota', 'textarea' );
	if ( $ok ) {
		$cols = array_keys( ap_columns() );
		$prod = in_array( 'producao', $cols, true ) ? 'producao' : ( $cols[2] ?? $p->status );
		ap_update( 'projects', $p->id, array( 'art_status' => 'aprovada', 'art_note' => $note, 'status' => $prod, 'board_col' => '' ) );
		ap_log( $p->id, 'Arte conferida e aprovada pela AllPrint. Pedido em produção.' . ( $note ? ' ' . $note : '' ), true );
		do_action( 'ap_project_stage', $p->id, $prod );
		if ( $client && is_email( $client->email ) ) {
			ap_mail( $client->email, 'Arte aprovada: pedido #' . $p->id . ' em produção', 'Arte conferida e aprovada ✓', '<p>Olá, ' . esc_html( strtok( (string) $client->name, ' ' ) ) . '! Conferimos o arquivo do pedido <strong>#' . (int) $p->id . '</strong>: está tudo certo com o material e as medidas. Ele já entrou em produção.</p>' . ( $note ? '<p>' . esc_html( $note ) . '</p>' : '' ), array(), 'Acompanhar o pedido', ap_client_entry_url( $client, 'projeto', $p->id ) );
		}
		ap_back( 'Arte aprovada: pedido em produção e cliente avisado.' );
	}
	if ( ! $note ) {
		ap_back( 'Escreva o que precisa ser corrigido na arte.', 'erro' );
	}
	ap_update( 'projects', $p->id, array( 'art_status' => 'problema', 'art_note' => $note ) );
	ap_log( $p->id, 'Arte com problema: ' . $note, true );
	if ( $client && is_email( $client->email ) ) {
		ap_mail( $client->email, 'Pedido #' . $p->id . ': precisamos de um ajuste na arte', 'Precisamos de um ajuste na arte', '<p>Olá, ' . esc_html( strtok( (string) $client->name, ' ' ) ) . '! Ao conferir o arquivo do pedido <strong>#' . (int) $p->id . '</strong>, encontramos o seguinte:</p><p style="padding:12px 14px;background:#fff7e0;border-radius:10px;">' . nl2br( esc_html( $note ) ) . '</p><p>Envie o arquivo corrigido pela sua área do cliente. A produção começa assim que estiver certo.</p>', array(), 'Enviar o arquivo corrigido', ap_client_entry_url( $client, 'projeto', $p->id ) );
	}
	ap_back( 'Cliente avisado sobre o problema na arte.' );
}

/* -----------------------------------------------------------------------
 * Estoque: entrou em produção → baixa os m² do material ligado ao catálogo
 * -------------------------------------------------------------------- */

add_action( 'ap_project_stage', 'ap_stage_stock', 20, 2 );
function ap_stage_stock( $id, $status ) {
	if ( 'producao' !== $status || ! function_exists( 'ap_stock_consume' ) ) {
		return;
	}
	$p = ap_get( 'projects', $id );
	if ( ! $p || $p->stock_done ) {
		return;
	}
	$use = array();
	foreach ( ap_order_items( $p ) as $it ) {
		if ( empty( $it['material'] ) ) {
			continue;
		}
		$c = ap_get( 'catalog', $it['material'] );
		if ( $c && $c->supply_id ) {
			$use[ $c->supply_id ] = ( $use[ $c->supply_id ] ?? 0 ) + (float) ( $it['area'] ?? 0 ) * ap_stock_loss_factor();
		}
	}
	if ( $use ) {
		$pairs = array();
		foreach ( $use as $sid => $a ) {
			$pairs[] = array( $sid, round( $a, 3 ) );
		}
		$cost = ap_stock_consume( $id, array(), $pairs );
		ap_update( 'projects', $id, array( 'stock_done' => 1, 'cost_real' => $p->cost_real + $cost ) );
		ap_log( $id, 'Estoque: baixa de ' . number_format( array_sum( $use ), 2, ',', '.' ) . ' m² de material (inclui ' . round( ( ap_stock_loss_factor() - 1 ) * 100 ) . '% de perda da máquina).' );
	}
}

/* -----------------------------------------------------------------------
 * Avisos para a equipe
 * -------------------------------------------------------------------- */

function ap_notify_team( $project_id, $subject, $text ) {
	$to = ap_setting( 'email' ) ? ap_setting( 'email' ) : get_option( 'admin_email' );
	if ( ! is_email( $to ) ) {
		return;
	}
	ap_mail( $to, $subject, $subject, '<p>' . esc_html( $text ) . '</p>', array(), 'Abrir o pedido', ap_panel_url( 'pedido', $project_id ) );
}

// Pronto: além do e-mail do cliente (orders.php), avisa a equipe.
add_action(
	'ap_project_stage',
	function ( $id, $status ) {
		if ( $status === ap_ready_column() ) {
			$p = ap_project( $id );
			if ( $p ) {
				ap_notify_team( $id, 'Pedido #' . $id . ' pronto para retirada', ap_project_client_label( $p ) . ' · ' . $p->title . '. Clique no pedido para avisar o cliente no WhatsApp.' );
			}
		}
	},
	30,
	2
);

/**
 * Renomeia no Drive os arquivos do pedido no padrão (1x_Material_Acabamento_LxAcm) e põe na pasta do pedido.
 */
function ap_drive_label_order( $pid, $client, $items ) {
	if ( ! ap_google_connected() ) {
		return;
	}
	$folder = ap_drive_folder( array( 'Clientes', ap_client_label( $client ), 'Pedido #' . $pid ) );
	if ( is_wp_error( $folder ) ) {
		return;
	}
	foreach ( $items as $it ) {
		$finish = trim( $it['cut'] === 'complexo' ? 'corte complexo' : 'corte simples' ) . ( $it['lam'] ? ' laminado' : '' ) . ( $it['sides'] ? ' ilhos' : '' );
		foreach ( (array) $it['files'] as $n => $f ) {
			if ( 0 === strpos( (string) $f['id'], 'wp-' ) ) {
				continue;
			}
			$c    = ap_get( 'catalog', $it['material'] );
			$name = ap_file_name( $it['qty'], $c ? $c->name : 'material', $finish, $it['w'], $it['h'], $f['name'] );
			if ( $n ) {
				$name = preg_replace( '/(\.[a-z0-9]+)$/i', '_' . ( $n + 1 ) . '$1', $name );
			}
			$parent = ap_google_api( 'GET', 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $f['id'] ) . '?fields=parents' );
			$prev   = ! is_wp_error( $parent ) && ! empty( $parent['parents'] ) ? implode( ',', $parent['parents'] ) : '';
			ap_google_api( 'PATCH', 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $f['id'] ) . '?addParents=' . rawurlencode( $folder ) . ( $prev ? '&removeParents=' . rawurlencode( $prev ) : '' ), array( 'name' => $name ) );
		}
	}
}
