<?php
/**
 * Importação da planilha de controle (AlPrint_Controle.xlsx).
 *
 * Abas lidas: Clientes, Pedidos, Custos Fixos, Tabela de Preços, Estoque (rolos), Estoque - Insumos,
 * Estoque - Revenda e Manutenção - Máquina. A aba Funcionários (salários) NÃO é importada de propósito:
 * é dado de RH, não de CRM.
 *
 * É idempotente: cada registro importado leva um código (ext_id) e rodar de novo só acrescenta o que falta.
 * Leitor de .xlsx próprio (ZipArchive + SimpleXML): não precisa de biblioteca nenhuma.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Leitor de .xlsx
 * -------------------------------------------------------------------- */

function ap_xl_col( $ref ) {
	preg_match( '/^([A-Z]+)/', $ref, $m );
	$n = 0;
	foreach ( str_split( $m[1] ) as $ch ) {
		$n = $n * 26 + ( ord( $ch ) - 64 );
	}
	return $n - 1;
}

/** Lê todas as abas: [ 'Nome da aba' => [ [col0, col1, ...], ... ] ] (linhas vazias descartadas). */
function ap_xlsx_read( $path ) {
	if ( ! class_exists( 'ZipArchive' ) || ! is_readable( $path ) ) {
		return new WP_Error( 'ap_xlsx', 'Não consegui abrir o arquivo (precisa ser .xlsx).' );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return new WP_Error( 'ap_xlsx', 'O arquivo não é um .xlsx válido.' );
	}
	$get = function ( $name ) use ( $zip ) {
		$d = $zip->getFromName( $name );
		return false === $d ? null : simplexml_load_string( $d, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE );
	};
	$wb   = $get( 'xl/workbook.xml' );
	$rels = $get( 'xl/_rels/workbook.xml.rels' );
	if ( ! $wb || ! $rels ) {
		$zip->close();
		return new WP_Error( 'ap_xlsx', 'Planilha sem abas.' );
	}
	$files = array();
	foreach ( $rels->Relationship as $r ) {
		$files[ (string) $r['Id'] ] = 'xl/' . ltrim( (string) $r['Target'], '/' );
		$files[ (string) $r['Id'] ] = str_replace( 'xl//xl/', 'xl/', $files[ (string) $r['Id'] ] );
	}
	$ss     = array();
	$ssxml  = $get( 'xl/sharedStrings.xml' );
	if ( $ssxml ) {
		foreach ( $ssxml->si as $si ) {
			$t = '';
			if ( isset( $si->t ) ) {
				$t = (string) $si->t;
			} else {
				foreach ( $si->r as $run ) {
					$t .= (string) $run->t;
				}
			}
			$ss[] = $t;
		}
	}
	// Estilos: quais formatos são data.
	$date_xf = array();
	$st      = $get( 'xl/styles.xml' );
	if ( $st ) {
		$custom = array();
		if ( isset( $st->numFmts ) ) {
			foreach ( $st->numFmts->numFmt as $nf ) {
				$code = strtolower( (string) $nf['formatCode'] );
				$custom[ (int) $nf['numFmtId'] ] = (bool) preg_match( '/[dmy]/', preg_replace( '/"[^"]*"|\[[^\]]*\]|\\\\./', '', $code ) ) && ! preg_match( '/0\.0|#/', $code );
			}
		}
		$i = 0;
		foreach ( $st->cellXfs->xf as $xf ) {
			$id              = (int) $xf['numFmtId'];
			$date_xf[ $i++ ] = ( $id >= 14 && $id <= 22 ) || ( $id >= 27 && $id <= 36 ) || ( $id >= 45 && $id <= 47 ) || ( $id >= 50 && $id <= 58 ) || ( ! empty( $custom[ $id ] ) );
		}
	}
	$out = array();
	foreach ( $wb->sheets->sheet as $sh ) {
		$rid  = (string) $sh->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )['id'];
		$name = (string) $sh['name'];
		if ( empty( $files[ $rid ] ) ) {
			continue;
		}
		$x = $get( $files[ $rid ] );
		if ( ! $x ) {
			continue;
		}
		$rows = array();
		foreach ( $x->sheetData->row as $row ) {
			$cells = array();
			$any   = false;
			foreach ( $row->c as $c ) {
				$col = ap_xl_col( (string) $c['r'] );
				$t   = (string) $c['t'];
				$v   = isset( $c->v ) ? (string) $c->v : '';
				if ( 'inlineStr' === $t ) {
					$v = (string) $c->is->t;
				} elseif ( 's' === $t ) {
					$v = isset( $ss[ (int) $v ] ) ? $ss[ (int) $v ] : '';
				} elseif ( 'b' === $t ) {
					$v = $v ? 1 : 0;
				} elseif ( 'n' === $t || '' === $t ) {
					if ( '' !== $v && is_numeric( $v ) ) {
						$v = 0 + $v;
						if ( ! empty( $date_xf[ (int) $c['s'] ] ) && $v > 20000 && $v < 80000 ) {
							$v = gmdate( 'Y-m-d', (int) round( ( $v - 25569 ) * 86400 ) );
						}
					}
				}
				if ( '' === $v || null === $v ) {
					continue;
				}
				$cells[ $col ] = is_string( $v ) ? trim( $v ) : $v;
				$any           = true;
			}
			if ( $any ) {
				$max = max( array_keys( $cells ) );
				$r   = array();
				for ( $i = 0; $i <= $max; $i++ ) {
					$r[ $i ] = array_key_exists( $i, $cells ) ? $cells[ $i ] : null;
				}
				$rows[] = $r;
			}
		}
		$out[ $name ] = $rows;
	}
	$zip->close();
	return $out;
}

/** Linhas com cabeçalho → lista de arrays associativos (chave = título normalizado da coluna). */
function ap_xl_assoc( $rows ) {
	if ( ! $rows ) {
		return array();
	}
	$head = array_map( 'ap_norm', array_shift( $rows ) );
	$out  = array();
	foreach ( $rows as $r ) {
		$a = array();
		foreach ( $head as $i => $h ) {
			if ( '' !== $h ) {
				$a[ $h ] = $r[ $i ] ?? null;
			}
		}
		$out[] = $a;
	}
	return $out;
}

/** Minúsculas, sem acento, sem pontuação sobrando: para comparar nomes. */
function ap_norm( $s ) {
	$s = remove_accents( (string) $s );
	$s = strtolower( $s );
	$s = preg_replace( '/[^a-z0-9]+/', ' ', $s );
	return trim( $s );
}

function ap_num( $v ) {
	if ( is_numeric( $v ) ) {
		return (float) $v;
	}
	$v = str_replace( array( 'R$', ' ' ), '', (string) $v );
	if ( false !== strpos( $v, ',' ) ) {
		$v = str_replace( '.', '', $v );
		$v = str_replace( ',', '.', $v );
	}
	return is_numeric( $v ) ? (float) $v : 0.0;
}

function ap_xl_month( $label ) {
	$map = array( 'janeiro' => 1, 'fevereiro' => 2, 'marco' => 3, 'abril' => 4, 'maio' => 5, 'junho' => 6, 'julho' => 7, 'agosto' => 8, 'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12 );
	if ( preg_match( '/([a-zçã]+)\s*\/\s*(\d{4})/iu', (string) $label, $m ) ) {
		$k = ap_norm( $m[1] );
		if ( isset( $map[ $k ] ) ) {
			return sprintf( '%04d-%02d-01', (int) $m[2], $map[ $k ] );
		}
	}
	return null;
}

/* -----------------------------------------------------------------------
 * Importação
 * -------------------------------------------------------------------- */

/** Chave da etapa (coluna do quadro) para o status da planilha. */
function ap_import_stage( $status ) {
	$cols = ap_columns();
	$find = function ( $needle ) use ( $cols ) {
		foreach ( $cols as $k => $label ) {
			if ( false !== strpos( ap_norm( $label ), $needle ) ) {
				return $k;
			}
		}
		return null;
	};
	$n = ap_norm( $status );
	if ( 'producao' === $n ) {
		return $find( 'producao' ) ?: array_keys( $cols )[0];
	}
	if ( 'aguardando retirada' === $n ) {
		return ap_ready_column();
	}
	return $find( 'entregue' ) ?: array_values( array_keys( $cols ) )[ count( $cols ) - 1 ];
}

function ap_import_method( $m ) {
	$n = ap_norm( $m );
	$map = array( 'pix' => 'Pix', 'transferencia' => 'Transferência', 'cartao' => 'Cartão', 'dinheiro' => 'Dinheiro', 'prazo' => 'A prazo', 'recurso proprio' => 'Recurso próprio' );
	return isset( $map[ $n ] ) ? $map[ $n ] : (string) $m;
}

function ap_import_unit_for( $name ) {
	$n = ap_norm( $name );
	if ( preg_match( '/^(revenda|craca|impressao a4|lab |deslocamento|visita|arte |servico de instalacao|instalacao ilhos|recorte|outro|estrutura)/', $n ) ) {
		return 'un';
	}
	if ( preg_match( '/(reforco|refile|macara|mascara.*metro|linear)/', $n ) ) {
		return 'm';
	}
	return 'm2';
}

function ap_import_group_for( $name ) {
	$n = ap_norm( $name );
	if ( 0 === strpos( $n, 'revenda' ) ) {
		return 'revenda';
	}
	if ( preg_match( '/^(servico|visita|arte |deslocamento|instalacao|recorte|estrutura)/', $n ) ) {
		return 'servico';
	}
	if ( preg_match( '/^(acm|ps |aco |chapa|manta|insulfilme|lab |craca|impressao a4|outro)/', $n ) ) {
		return 'outros';
	}
	return 0 === strpos( $n, 'sem impressao' ) ? 'sem-impressao' : 'impresso';
}

/** Nome do item do catálogo do site → produto da planilha (quando o mesmo material tem dois nomes). */
function ap_import_catalog_alias() {
	return array(
		'adesivo'                          => array( 'impresso', 'Adesivo Padrão' ),
		'adesivo premium'                  => array( 'impresso', 'Adesivo Premium' ),
		'adesivo stoplight'                => array( 'impresso', 'Adesivo Stoplight Brilho/Fosco' ),
		'adesivo transparente'             => array( 'impresso', 'Adesivo Transparente ou Jateado' ),
		'adesivo com corte'                => array( 'impresso', 'Adesivo Impresso com corte eletrônico' ),
		'adesivo perfurado'                => array( 'impresso', 'Adesivo Perfurado' ),
		'adesivo perfurado premium'        => array( 'impresso', 'Adesivo Perfurado Premium' ),
		'banner'                           => array( 'impresso', 'Lona 440g com Acabamento (banner/faixa)' ),
		'lona com ilhos'                   => array( 'impresso', 'Lona 440g com Acabamento (reforço e ilhós de alumínio)' ),
		'lona sem cabamento'               => array( 'impresso', 'Lona 440g sem Acabamento' ),
		'lona backlight'                   => array( 'impresso', 'Lona Backlight Premium 440g' ),
		'sem impressao adesivo'            => array( 'sem-impressao', 'Adesivo Padrão Brilho ou Fosco' ),
		'sem impressao adesivo premium'    => array( 'sem-impressao', 'Adesivo Premium ou Stoplight Brilho ou Fosco' ),
		'sem impressao adesivo transparente' => array( 'sem-impressao', 'Adesivo Transparente ou Jateado' ),
		'sem impressao lona'               => array( 'sem-impressao', 'Lona Front 440g' ),
	);
}

/**
 * Importa tudo. $dry = true só conta (nada é gravado).
 * Devolve [ 'counts' => [...], 'log' => [...], 'errors' => [...] ].
 */
function ap_import_run( $path, $dry = false ) {
	@set_time_limit( 300 ); // phpcs:ignore
	$GLOBALS['ap_sheets_pause'] = true; // a planilha do Google recebe tudo de uma vez pelo botão "Sincronizar tudo"
	$sheets = ap_xlsx_read( $path );
	if ( is_wp_error( $sheets ) ) {
		return array( 'counts' => array(), 'log' => array(), 'errors' => array( $sheets->get_error_message() ) );
	}
	$res = array( 'counts' => array(), 'log' => array(), 'errors' => array() );
	$add = function ( $k, $n = 1 ) use ( &$res ) {
		$res['counts'][ $k ] = ( $res['counts'][ $k ] ?? 0 ) + $n;
	};

	/* ---- Clientes ---- */
	$clients_by_nick = array();
	$clients_by_name = array();
	foreach ( ap_rows( 'clients', '1=1' ) as $c ) {
		$clients_by_nick[ ap_norm( $c->name ) ] = $c->id;
		$clients_by_name[ ap_norm( $c->company ) ] = $c->id;
	}
	$cli_kind = array();
	$pay_later = array();
	$orders_raw = isset( $sheets['Pedidos'] ) ? ap_xl_assoc( $sheets['Pedidos'] ) : array();
	foreach ( $orders_raw as $o ) {
		if ( ! empty( $o['cliente'] ) ) {
			$cli_kind[ ap_norm( $o['cliente'] ) ] = $o['tipo cliente'] ?? '';
			if ( 'prazo' === ap_norm( $o['forma de pagamento'] ?? '' ) ) {
				$pay_later[ ap_norm( $o['cliente'] ) ] = true;
			}
		}
	}
	$ensure_client = function ( $nick, $row = array() ) use ( &$clients_by_nick, &$clients_by_name, &$res, $dry, $add, $cli_kind, $pay_later ) {
		$k = ap_norm( $nick );
		if ( isset( $clients_by_nick[ $k ] ) ) {
			return $clients_by_nick[ $k ];
		}
		if ( isset( $clients_by_name[ $k ] ) ) {
			return $clients_by_name[ $k ];
		}
		$kind = $row['tipo'] ?? ( $cli_kind[ $k ] ?? '' );
		$doc  = trim( (string) ( $row['cnpj'] ?? '' ) );
		$wa   = trim( (string) ( $row['contato whatsapp'] ?? '' ) );
		$id   = 0;
		if ( ! $dry ) {
			$id = ap_insert(
				'clients',
				array(
					'name'      => (string) ( $row['apelido nome curto'] ?? $nick ),
					'company'   => (string) ( $row['nome empresa'] ?? $nick ),
					'cnpj'      => $doc,
					'email'     => is_email( $row['e mail'] ?? '' ) ? $row['e mail'] : '',
					'phone'     => $wa,
					'whatsapp'  => $wa,
					'city'      => (string) ( $row['cidade'] ?? '' ),
					'source'    => (string) ( $row['como chegou'] ?? '' ),
					'approved'  => 1,
					'pay_later' => isset( $pay_later[ $k ] ) ? 1 : 0,
					'status'    => 'ativo',
					'kind'      => (string) $kind,
					'ext_id'    => (string) ( $row['id cliente'] ?? '' ),
					'notes'     => trim( 'Importado da planilha. ' . ( $row['observacoes'] ?? '' ) ),
				)
			);
		}
		$add( 'clientes' );
		$id                    = $id ? $id : -1 * ( count( $clients_by_nick ) + 1 );
		$clients_by_nick[ $k ] = $id;
		if ( ! empty( $row['nome empresa'] ) ) {
			$clients_by_name[ ap_norm( $row['nome empresa'] ) ] = $id;
		}
		return $id;
	};
	foreach ( isset( $sheets['Clientes'] ) ? ap_xl_assoc( $sheets['Clientes'] ) : array() as $row ) {
		$nick = $row['apelido nome curto'] ?? ( $row['nome empresa'] ?? '' );
		if ( ! $nick ) {
			continue;
		}
		$ensure_client( $nick, $row );
	}

	/* ---- Pedidos ---- */
	$groups = array();
	foreach ( $orders_raw as $o ) {
		if ( empty( $o['cliente'] ) || empty( $o['produto'] ) ) {
			continue;
		}
		$id = trim( (string) ( $o['id pedido'] ?? '' ) );
		if ( ! $id ) {
			$id = 'SEMID-' . ( $o['data'] ?? '' ) . '-' . ap_norm( $o['cliente'] );
		}
		$groups[ $id ][] = $o;
	}
	global $wpdb;
	$existing = $wpdb->get_col( 'SELECT ext_id FROM ' . ap_table( 'projects' ) . " WHERE ext_id <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL
	$existing = array_flip( $existing );
	foreach ( $groups as $ext => $rows ) {
		if ( isset( $existing[ $ext ] ) ) {
			$add( 'pedidos já importados' );
			continue;
		}
		$first  = $rows[0];
		$cid    = $ensure_client( $first['cliente'], array( 'tipo' => $first['tipo cliente'] ?? '' ) );
		$items  = array();
		$value  = 0;
		$cost   = 0;
		$obs    = array();
		$paid_amt = 0;
		$unpaid   = 0;
		$last_pay = null;
		$method   = '';
		$ready    = null;
		foreach ( $rows as $o ) {
			$qty   = max( 1, (int) ap_num( $o['quantidade'] ?? 1 ) );
			$total = round( ap_num( $o['valor cobrado r'] ?? 0 ), 2 );
			$dims  = (string) ( $o['dimensoes cm'] ?? '' );
			$w     = 0;
			$h     = 0;
			if ( preg_match( '/([\d.,]+)\s*[_xX*]\s*([\d.,]+)/', $dims, $m ) ) {
				$w = ap_num( $m[1] );
				$h = ap_num( $m[2] );
			}
			$items[] = array(
				'kind'     => 'impressao',
				'name'     => (string) $o['produto'],
				'desc'     => $dims ? str_replace( '_', ' × ', trim( $dims ) ) . ' cm' . ( ! empty( $o['observacoes'] ) ? ' · ' . $o['observacoes'] : '' ) : (string) ( $o['observacoes'] ?? '' ),
				'qty'      => $qty,
				'unit'     => $qty ? round( $total / $qty, 2 ) : $total,
				'cost'     => round( ap_num( $o['custo material r'] ?? 0 ), 2 ),
				'w'        => $w,
				'h'        => $h,
				'area'     => round( ap_num( $o['m'] ?? ( $o['m²'] ?? 0 ) ), 4 ),
				'imported' => 1,
			);
			$value += $total;
			$cost  += ap_num( $o['custo material r'] ?? 0 );
			if ( ! empty( $o['observacoes'] ) ) {
				$obs[] = $o['observacoes'];
			}
			$is_paid = 'sim' === ap_norm( $o['pago'] ?? '' );
			if ( $is_paid ) {
				$paid_amt += $total;
				$pd        = $o['data de pagamento'] ?? null;
				if ( is_string( $pd ) && ( ! $last_pay || $pd > $last_pay ) ) {
					$last_pay = $pd;
				}
			} else {
				$unpaid += $total;
			}
			$method = $method ? $method : ap_import_method( $o['forma de pagamento'] ?? '' );
			$ready  = $ready ? $ready : ( is_string( $o['data da retirada instalacao'] ?? null ) ? $o['data da retirada instalacao'] : null );
		}
		$date      = is_string( $first['data'] ?? null ) ? $first['data'] : ap_today();
		$st_raw    = $first['status'] ?? 'Entregue';
		$cancelled = 'cancelado' === ap_norm( $st_raw );
		$stage     = ap_import_stage( $st_raw );
		$internal  = 'uso interno' === ap_norm( $first['tipo cliente'] ?? '' ) || 'recurso proprio' === ap_norm( $first['forma de pagamento'] ?? '' );
		$title     = $ext . ' · ' . $first['produto'] . ( count( $rows ) > 1 ? ' + ' . ( count( $rows ) - 1 ) : '' );
		$done      = in_array( ap_norm( $st_raw ), array( 'entregue', 'instalado', 'cancelado' ), true );
		$add( 'pedidos' );
		$add( 'itens de pedido', count( $items ) );
		if ( $dry ) {
			continue;
		}
		$pid = ap_insert(
			'projects',
			array(
				'client_id'     => max( 0, $cid ),
				'title'         => mb_substr( $title, 0, 190 ),
				'status'        => $stage,
				'value'         => round( $value, 2 ),
				'cost_real'     => round( $cost, 2 ),
				'items'         => wp_json_encode( $items ),
				'notes'         => implode( ' | ', array_unique( array_filter( array_merge( $obs, $cancelled ? array( 'Cancelado' ) : array(), $internal ? array( 'Uso interno (custo da casa)' ) : array(), ap_norm( $st_raw ) === 'instalado' ? array( 'Instalado no cliente' ) : array() ) ) ) ),
				'delivery_mode' => 'retirada',
				'start_date'    => $date,
				'due_date'      => $ready ? $ready : $date,
				'ready_at'      => $ready ? $ready . ' 12:00:00' : null,
				'delivered_at'  => $done && ! $cancelled && $ready ? $ready . ' 12:00:00' : null,
				'art_status'    => 'aprovada',
				'stock_done'    => 1,
				'archived'      => ( $cancelled || $done ) ? 1 : 0,
				'ext_id'        => $ext,
				'created_at'    => $date . ' 09:00:00',
				'updated_at'    => ( $ready ? $ready : $date ) . ' 12:00:00',
			)
		);
		if ( ! $pid ) {
			$res['errors'][] = 'Falhou ao gravar o pedido ' . $ext;
			continue;
		}
		if ( ! $cancelled && ! $internal ) {
			$desc = 'Pedido ' . $ext;
			if ( $paid_amt > 0 ) {
				ap_insert( 'transactions', array( 'type' => 'in', 'project_id' => $pid, 'client_id' => max( 0, $cid ), 'category' => 'Pedido', 'description' => $desc, 'amount' => round( $paid_amt, 2 ), 'due_date' => $date, 'paid_at' => $last_pay ? $last_pay : $date, 'method' => $method, 'status' => 'pago', 'external_id' => $ext . '-pago' ) );
				$add( 'lançamentos de entrada pagos' );
			}
			if ( $unpaid > 0 ) {
				ap_insert( 'transactions', array( 'type' => 'in', 'project_id' => $pid, 'client_id' => max( 0, $cid ), 'category' => 'Pedido', 'description' => $desc, 'amount' => round( $unpaid, 2 ), 'due_date' => $date, 'paid_at' => null, 'method' => $method, 'status' => 'pendente', 'external_id' => $ext . '-pend' ) );
				$add( 'lançamentos a receber' );
			}
		}
	}

	/* ---- Custos fixos ---- */
	$have = array_flip( $wpdb->get_col( 'SELECT external_id FROM ' . ap_table( 'transactions' ) . " WHERE external_id LIKE 'CF-%'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	foreach ( isset( $sheets['Custos Fixos'] ) ? ap_xl_assoc( $sheets['Custos Fixos'] ) : array() as $r ) {
		$amount = ap_num( $r['valor r'] ?? 0 );
		$due    = ap_xl_month( $r['mes ano'] ?? '' );
		if ( $amount <= 0 || ! $due ) {
			continue;
		}
		$paid = 'sim' === ap_norm( $r['pago'] ?? '' );
		$cat  = trim( (string) ( $r['categoria'] ?? 'Outros' ) );
		$desc = trim( ( $r['descricao'] ?? '' ) . ( ! empty( $r['observacoes'] ) ? ' · ' . $r['observacoes'] : '' ) );
		$ext  = 'CF-' . substr( md5( $due . '|' . $cat . '|' . $desc . '|' . $amount ), 0, 12 );
		if ( isset( $have[ $ext ] ) ) {
			continue;
		}
		$add( 'custos fixos' );
		if ( preg_match( '/funcion|sal[aá]rio|folha/iu', $cat ) ) {
			$desc = ''; // folha de pagamento entra só como custo, sem o nome de cada pessoa
		}
		if ( ! $dry ) {
			ap_insert( 'transactions', array( 'type' => 'out', 'category' => $cat, 'description' => mb_substr( $cat . ( $desc ? ' · ' . $desc : '' ), 0, 250 ), 'amount' => $amount, 'due_date' => $due, 'paid_at' => $paid ? ( is_string( $r['data pagamento'] ?? null ) ? $r['data pagamento'] : $due ) : null, 'method' => '', 'status' => $paid ? 'pago' : 'pendente', 'external_id' => $ext ) );
		}
	}

	/* ---- Tabela de preços → catálogo ---- */
	$alias    = ap_import_catalog_alias();
	$by_code  = array();
	$by_name  = array();
	foreach ( ap_rows( 'catalog', '1=1' ) as $c ) {
		if ( $c->code ) {
			$by_code[ $c->code ] = $c;
		}
		$by_name[ $c->grp . '|' . $c->name ] = $c;
	}
	$pos = 100;
	foreach ( isset( $sheets['Tabela de Preços'] ) ? ap_xl_assoc( $sheets['Tabela de Preços'] ) : array() as $r ) {
		$name = trim( (string) ( $r['produto'] ?? '' ) );
		if ( ! $name ) {
			continue;
		}
		$code = trim( (string) ( $r['id produto'] ?? '' ) );
		$code = $code ? $code : 'PRD-' . strtoupper( substr( md5( $name ), 0, 5 ) );
		$vals = array(
			'code'          => $code,
			'price_empresa' => ap_num( $r['empresa r m'] ?? ( $r['empresa r m²'] ?? 0 ) ),
			'price_pf'      => ap_num( $r['cliente pessoa fisica r m'] ?? ( $r['cliente pessoa fisica r m²'] ?? 0 ) ),
			'cost_material' => ap_num( $r['custo material r m'] ?? 0 ),
			'cost_print'    => ap_num( $r['custo de impressao r m'] ?? 0 ),
		);
		$term = 0.0;
		foreach ( $r as $k => $v ) {
			if ( 0 === strpos( $k, 'terceirizado' ) ) {
				$term = ap_num( $v );
			}
			if ( 0 === strpos( $k, 'empresa' ) ) {
				$vals['price_empresa'] = ap_num( $v );
			}
			if ( 0 === strpos( $k, 'cliente pessoa' ) ) {
				$vals['price_pf'] = ap_num( $v );
			}
			if ( 0 === strpos( $k, 'custo material' ) ) {
				$vals['cost_material'] = ap_num( $v );
			}
			if ( 0 === strpos( $k, 'custo de impressao' ) ) {
				$vals['cost_print'] = ap_num( $v );
			}
		}
		$vals['unit'] = ap_import_unit_for( $name );
		$key          = ap_norm( $name );
		$row          = $by_code[ $code ] ?? null;
		if ( ! $row && isset( $alias[ $key ] ) ) {
			$row = $by_name[ $alias[ $key ][0] . '|' . $alias[ $key ][1] ] ?? null;
		}
		if ( $row ) {
			$add( 'preços ligados ao catálogo do site' );
			if ( ! $dry ) {
				ap_update( 'catalog', $row->id, $vals + array( 'price_m2' => $row->price_m2 ? $row->price_m2 : $term ) );
			}
			continue;
		}
		$add( 'itens novos na tabela de preços' );
		if ( ! $dry ) {
			ap_insert( 'catalog', $vals + array( 'grp' => ap_import_group_for( $name ), 'name' => $name, 'note' => 'Da planilha', 'price_m2' => $term, 'kind' => 'outro', 'online' => 0, 'position' => $pos++ ) );
		}
	}

	/* ---- Estoque: rolos, insumos e revenda ---- */
	$sup_have = array();
	foreach ( ap_rows( 'supplies', '1=1' ) as $s ) {
		if ( $s->code ) {
			$sup_have[ $s->code ] = $s->id;
		}
	}
	$mk = function ( $code, $data ) use ( &$sup_have, $dry, $add ) {
		if ( isset( $sup_have[ $code ] ) ) {
			return;
		}
		$add( 'itens de estoque' );
		if ( ! $dry ) {
			$sup_have[ $code ] = ap_insert( 'supplies', $data + array( 'code' => $code ) );
		}
	};
	foreach ( isset( $sheets['Estoque'] ) ? ap_xl_assoc( $sheets['Estoque'] ) : array() as $r ) {
		if ( empty( $r['produto'] ) ) {
			continue;
		}
		$w   = ap_num( $r['larguras m'] ?? 0 );
		$m2  = ap_num( $r['quant rolo 50m'] ?? 0 );
		$rl  = ap_num( $r['estoque rolo'] ?? 0 );
		$nm  = ucfirst( trim( $r['produto'] ) ) . ' · ' . ucfirst( trim( (string) ( $r['marca'] ?? '' ) ) ) . ' · ' . round( $w * 100 ) . ' cm';
		$mk( 'ROL-' . substr( md5( ap_norm( $nm ) ), 0, 10 ), array( 'name' => $nm, 'unit' => 'm2', 'category' => 'Bobina', 'brand' => (string) ( $r['marca'] ?? '' ), 'supplier' => (string) ( $r['fornecedor'] ?? '' ), 'roll_m2' => $m2, 'roll_width' => round( $w * 100, 1 ), 'qty' => round( $rl * $m2, 2 ), 'unit_cost' => ap_num( $r['valor por m r'] ?? ( $r['valor por m²  r'] ?? 0 ) ), 'last_buy' => is_string( $r['data compra'] ?? null ) ? $r['data compra'] : null, 'per_order' => 0 ) );
	}
	foreach ( isset( $sheets['Estoque - Insumos'] ) ? ap_xl_assoc( $sheets['Estoque - Insumos'] ) : array() as $r ) {
		if ( empty( $r['item'] ) ) {
			continue;
		}
		$n   = ap_norm( $r['item'] );
		$cat = 'Consumo interno';
		if ( preg_match( '/tinta|cartaucho|cart /', $n ) ) {
			$cat = 'Tinta';
		} elseif ( preg_match( '/cabeca|kit de manutencao/', $n ) ) {
			$cat = 'Peças da impressora';
		} elseif ( 'insumo impressao' === ap_norm( $r['categoria'] ?? '' ) ) {
			$cat = 'Tinta';
		}
		$mk( 'INS-' . substr( md5( $n ), 0, 10 ), array( 'name' => $r['item'], 'unit' => 'un', 'category' => $cat, 'qty' => ap_num( $r['estoque atual'] ?? 0 ), 'min_qty' => ap_num( $r['ponto de reposicao'] ?? 0 ), 'unit_cost' => ap_num( $r['custo estimado r'] ?? 0 ), 'last_buy' => is_string( $r['ultima reposicao'] ?? null ) ? $r['ultima reposicao'] : null, 'notes' => 'Unidade na planilha: ' . ( $r['unidade'] ?? '' ) ) );
	}
	foreach ( isset( $sheets['Estoque - Revenda'] ) ? ap_xl_assoc( $sheets['Estoque - Revenda'] ) : array() as $r ) {
		if ( empty( $r['produto'] ) ) {
			continue;
		}
		$mk( 'REV-' . substr( md5( ap_norm( $r['produto'] ) ), 0, 10 ), array( 'name' => $r['produto'], 'unit' => 'un', 'category' => 'Revenda', 'brand' => (string) ( $r['marca referencia'] ?? '' ), 'supplier' => (string) ( $r['fornecedor'] ?? '' ), 'qty' => ap_num( $r['estoque atual un'] ?? 0 ), 'min_qty' => ap_num( $r['ponto de reposicao un'] ?? 0 ), 'unit_cost' => ap_num( $r['custo de compra r'] ?? 0 ), 'sale_price' => ap_num( $r['preco de venda r'] ?? 0 ) ) );
	}
	/* ---- Manutenção de máquina → despesa ---- */
	foreach ( isset( $sheets['Manutenção - Máquina'] ) ? ap_xl_assoc( $sheets['Manutenção - Máquina'] ) : array() as $r ) {
		$d = $r['data'] ?? null;
		if ( ! is_string( $d ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) || ap_num( $r['custo r'] ?? 0 ) <= 0 ) {
			continue;
		}
		$ext = 'MQ-' . substr( md5( $d . ( $r['item'] ?? '' ) ), 0, 12 );
		if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . ap_table( 'transactions' ) . ' WHERE external_id = %s', $ext ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL
			continue;
		}
		$add( 'manutenções de máquina' );
		if ( ! $dry ) {
			ap_insert( 'transactions', array( 'type' => 'out', 'category' => 'Manutenção', 'description' => 'Máquina · ' . $r['item'] . ( ! empty( $r['fornecedor'] ) ? ' (' . $r['fornecedor'] . ')' : '' ), 'amount' => ap_num( $r['custo r'] ), 'due_date' => $d, 'paid_at' => $d, 'status' => 'pago', 'external_id' => $ext ) );
		}
	}
	if ( ! $dry ) {
		update_option( 'ap_import_log', array( 'at' => ap_now(), 'counts' => $res['counts'] ), false );
	}
	return $res;
}

/* -----------------------------------------------------------------------
 * Tela e execução automática
 * -------------------------------------------------------------------- */

add_action( 'admin_post_ap_import', 'ap_do_import' );
function ap_do_import() {
	if ( ! ap_is_admin() ) {
		wp_die( 'Sem permissão.' );
	}
	check_admin_referer( 'ap_import' );
	$path = AP_DIR . 'data/AlPrint_Controle.xlsx';
	if ( ! empty( $_FILES['planilha']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$path = $_FILES['planilha']['tmp_name']; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	}
	$dry = ! empty( $_POST['simular'] ); // phpcs:ignore WordPress.Security.NonceVerification
	$r   = ap_import_run( $path, $dry );
	set_transient( 'ap_import_result_' . get_current_user_id(), array( 'dry' => $dry ) + $r, 600 );
	wp_safe_redirect( ap_panel_url( 'importar' ) );
	exit;
}

/** Importação automática, uma vez, se a planilha vier dentro do plugin (data/AlPrint_Controle.xlsx). */
add_action(
	'init',
	function () {
		if ( get_option( 'ap_import_auto' ) || ! is_readable( AP_DIR . 'data/AlPrint_Controle.xlsx' ) || ! get_option( 'ap_version' ) ) {
			return;
		}
		update_option( 'ap_import_auto', 1, false );
		ap_import_run( AP_DIR . 'data/AlPrint_Controle.xlsx', false );
	},
	120
);
