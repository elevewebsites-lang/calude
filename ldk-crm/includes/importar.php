<?php
/**
 * Importar planilha (Excel .xlsx ou CSV), por exemplo a exportação do Monday com o calendário de conteúdo de um cliente.
 *
 * Fluxo: subir o arquivo → o sistema reconhece as colunas (Name, Data, Produto, Arte, Legenda, Vídeo, Pessoa…) →
 * mostra uma pré-visualização → a pessoa confirma e os posts entram no Conteúdo do cliente.
 *
 * Só entra o que tem texto: linhas vazias, linhas com só "." e os títulos de grupo (mês) são ignorados,
 * assim como os itens cancelados. Quem já foi importado antes (mesmo cliente, título e data) não duplica.
 * Os posts são gravados direto no banco, sem disparar publicação, notificações ou pontos de gamificação.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Leitura do arquivo
 * -------------------------------------------------------------------- */

function lk_xlsx_col_index( $ref ) {
	$letters = preg_replace( '/[^A-Z]/', '', strtoupper( $ref ) );
	$n       = 0;
	for ( $i = 0, $len = strlen( $letters ); $i < $len; $i++ ) {
		$n = $n * 26 + ( ord( $letters[ $i ] ) - 64 );
	}
	return max( 0, $n - 1 );
}

/** Texto de um <si> / <is> (inclui texto formatado em pedaços). */
function lk_xlsx_text( $node ) {
	if ( ! $node ) {
		return '';
	}
	if ( isset( $node->t ) ) {
		return (string) $node->t;
	}
	$out = '';
	if ( isset( $node->r ) ) {
		foreach ( $node->r as $r ) {
			$out .= (string) $r->t;
		}
	}
	return $out;
}

/**
 * Lê a primeira planilha de um .xlsx. Devolve uma lista de linhas (cada linha: lista de textos; datas viram AAAA-MM-DD)
 * ou WP_Error.
 */
function lk_xlsx_rows( $path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'lk', 'O servidor não lê arquivos .xlsx. Salve a planilha como CSV e envie de novo.' );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return new WP_Error( 'lk', 'Não foi possível abrir o arquivo. Confira se é um .xlsx válido.' );
	}
	libxml_use_internal_errors( true );
	$strings = array();
	$ss      = $zip->getFromName( 'xl/sharedStrings.xml' );
	if ( false !== $ss ) {
		$x = simplexml_load_string( $ss );
		if ( $x ) {
			foreach ( $x->si as $si ) {
				$strings[] = lk_xlsx_text( $si );
			}
		}
	}
	// Quais estilos são de data.
	$date_xf = array();
	$st      = $zip->getFromName( 'xl/styles.xml' );
	if ( false !== $st && ( $sx = simplexml_load_string( $st ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
		$custom = array();
		if ( isset( $sx->numFmts ) ) {
			foreach ( $sx->numFmts->numFmt as $nf ) {
				$code  = strtolower( preg_replace( '/"[^"]*"|\[[^\]]*\]/', '', (string) $nf['formatCode'] ) );
				if ( preg_match( '/[dy]/', $code ) && ! preg_match( '/[#0]/', $code ) ) {
					$custom[ (int) $nf['numFmtId'] ] = true;
				}
			}
		}
		$i = 0;
		if ( isset( $sx->cellXfs ) ) {
			foreach ( $sx->cellXfs->xf as $xf ) {
				$id = (int) $xf['numFmtId'];
				if ( ( $id >= 14 && $id <= 22 ) || ( $id >= 45 && $id <= 47 ) || isset( $custom[ $id ] ) ) {
					$date_xf[ $i ] = true;
				}
				$i++;
			}
		}
	}
	// Primeira aba.
	$sheet = 'xl/worksheets/sheet1.xml';
	$wb    = $zip->getFromName( 'xl/workbook.xml' );
	$rels  = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
	if ( false !== $wb && false !== $rels ) {
		$w = simplexml_load_string( $wb );
		$r = simplexml_load_string( $rels );
		if ( $w && $r && isset( $w->sheets->sheet[0] ) ) {
			$rid = (string) $w->sheets->sheet[0]->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )->id;
			foreach ( $r->Relationship as $rel ) {
				if ( (string) $rel['Id'] === $rid ) {
					$t     = (string) $rel['Target'];
					$sheet = 0 === strpos( $t, '/' ) ? ltrim( $t, '/' ) : 'xl/' . $t;
				}
			}
		}
	}
	$xml = $zip->getFromName( $sheet );
	$zip->close();
	if ( false === $xml ) {
		return new WP_Error( 'lk', 'Não encontrei a planilha dentro do arquivo.' );
	}
	$sx = simplexml_load_string( $xml );
	if ( ! $sx || ! isset( $sx->sheetData ) ) {
		return new WP_Error( 'lk', 'A planilha está vazia ou em um formato que não reconheço.' );
	}
	$rows = array();
	foreach ( $sx->sheetData->row as $row ) {
		$line = array();
		$last = -1;
		foreach ( $row->c as $c ) {
			$idx = isset( $c['r'] ) ? lk_xlsx_col_index( (string) $c['r'] ) : $last + 1;
			$type = (string) $c['t'];
			$val  = '';
			if ( 's' === $type ) {
				$val = $strings[ (int) $c->v ] ?? '';
			} elseif ( 'inlineStr' === $type ) {
				$val = lk_xlsx_text( $c->is );
			} elseif ( 'b' === $type ) {
				$val = (string) $c->v ? 'Sim' : 'Não';
			} else {
				$val = isset( $c->v ) ? (string) $c->v : '';
				if ( '' !== $val && 'str' !== $type && 'e' !== $type && is_numeric( $val ) && isset( $date_xf[ (int) $c['s'] ] ) ) {
					$val = gmdate( 'Y-m-d', (int) round( ( (float) $val - 25569 ) * 86400 ) );
				}
			}
			while ( $last + 1 < $idx ) {
				$line[] = '';
				$last++;
			}
			$line[] = trim( (string) $val );
			$last   = $idx;
		}
		$rows[] = $line;
		if ( count( $rows ) > 20000 ) {
			break;
		}
	}
	return $rows;
}

function lk_csv_rows( $path ) {
	$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === $raw ) {
		return new WP_Error( 'lk', 'Não consegui ler o arquivo.' );
	}
	if ( 0 === strpos( $raw, "\xEF\xBB\xBF" ) ) {
		$raw = substr( $raw, 3 );
	}
	if ( ! mb_check_encoding( $raw, 'UTF-8' ) ) {
		$raw = mb_convert_encoding( $raw, 'UTF-8', 'ISO-8859-1' );
	}
	$first = strtok( $raw, "\n" );
	$delim = substr_count( (string) $first, ';' ) > substr_count( (string) $first, ',' ) ? ';' : ',';
	$rows  = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$rows[] = array_map( 'trim', str_getcsv( $line, $delim ) );
	}
	return $rows;
}

/* -----------------------------------------------------------------------
 * Reconhecimento do conteúdo
 * -------------------------------------------------------------------- */

function lk_import_norm( $s ) {
	return sanitize_title( remove_accents( (string) $s ) );
}

/** Data de uma célula: AAAA-MM-DD ou dd/mm/aaaa → AAAA-MM-DD; senão ''. */
function lk_import_date( $v ) {
	$v = trim( (string) $v );
	if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})/', $v, $m ) ) {
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? "$m[1]-$m[2]-$m[3]" : '';
	}
	if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{2,4})#', $v, $m ) ) {
		$y = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];
		return checkdate( (int) $m[2], (int) $m[1], $y ) ? sprintf( '%04d-%02d-%02d', $y, $m[2], $m[1] ) : '';
	}
	return '';
}

/** Colunas pelo cabeçalho. Devolve array( campo => índice ) ou null se a linha não é um cabeçalho. */
function lk_import_header( $row ) {
	$map = array();
	foreach ( $row as $i => $cell ) {
		$n = lk_import_norm( $cell );
		if ( '' === $n ) {
			continue;
		}
		if ( ! isset( $map['name'] ) && in_array( $n, array( 'name', 'nome', 'titulo', 'item', 'post', 'tarefa', 'conteudo' ), true ) ) {
			$map['name'] = $i;
		} elseif ( ! isset( $map['entrega'] ) && false !== strpos( $n, 'entrega' ) ) {
			$map['entrega'] = $i;
		} elseif ( ! isset( $map['date'] ) && in_array( $n, array( 'data', 'date', 'data-de-publicacao', 'data-publicacao', 'publicacao' ), true ) ) {
			$map['date'] = $i;
		} elseif ( ! isset( $map['product'] ) && in_array( $n, array( 'produto', 'formato', 'tipo', 'product', 'format' ), true ) ) {
			$map['product'] = $i;
		} elseif ( ! isset( $map['arte'] ) && in_array( $n, array( 'arte', 'design' ), true ) ) {
			$map['arte'] = $i;
		} elseif ( ! isset( $map['legenda'] ) && in_array( $n, array( 'legenda', 'copy', 'texto' ), true ) ) {
			$map['legenda'] = $i;
		} elseif ( ! isset( $map['video'] ) && in_array( $n, array( 'video', 'reels' ), true ) ) {
			$map['video'] = $i;
		} elseif ( ! isset( $map['people'] ) && in_array( $n, array( 'pessoa', 'pessoas', 'responsavel', 'responsaveis', 'people', 'person', 'owner' ), true ) ) {
			$map['people'] = $i;
		}
	}
	return isset( $map['name'] ) && isset( $map['date'] ) ? $map : null;
}

/** Formato do post a partir de "Produto" (e dos status). */
function lk_import_format( $product, $has_video ) {
	$p = lk_import_norm( $product );
	if ( false !== strpos( $p, 'carrossel' ) ) {
		return 'carrossel';
	}
	if ( false !== strpos( $p, 'video' ) || false !== strpos( $p, 'reel' ) ) {
		return 'reels';
	}
	if ( 'story' === $p || 'stories' === $p ) {
		return 'story';
	}
	if ( 'foto' === $p ) {
		return 'foto';
	}
	if ( '' === $p && $has_video ) {
		return 'reels';
	}
	return 'arte';
}

/** Posição do status no fluxo (0 = começo … 5 = publicado) ou 'x' para cancelado. */
function lk_import_status_rank( $status ) {
	$s = lk_import_norm( $status );
	if ( '' === $s ) {
		return null;
	}
	if ( false !== strpos( $s, 'cancel' ) ) {
		return 'x';
	}
	if ( false !== strpos( $s, 'postado' ) || false !== strpos( $s, 'publicado' ) || false !== strpos( $s, 'agendado' ) ) {
		return 5;
	}
	if ( false !== strpos( $s, 'aprovado' ) || false !== strpos( $s, 'pronto' ) || false !== strpos( $s, 'vendid' ) ) {
		return 4;
	}
	if ( false !== strpos( $s, 'aprova' ) ) {
		return 3;
	}
	if ( false !== strpos( $s, 'revis' ) ) {
		return 2;
	}
	if ( false !== strpos( $s, 'entregar' ) || false !== strpos( $s, 'gravacao' ) || false !== strpos( $s, 'standby' ) || false !== strpos( $s, 'andamento' ) || false !== strpos( $s, 'fazendo' ) ) {
		return 1;
	}
	return 0; // "criar legenda", "Arte", "Legenda", "Vídeo"…: ainda no começo
}

/** Etapa do CRM (chave) pelo ranking. */
function lk_import_stage( $rank ) {
	$roles = array( 0 => 'planejamento', 1 => 'design', 2 => 'revisao', 3 => 'aprovacao', 4 => 'agendado', 5 => 'publicado' );
	$role  = $roles[ $rank ] ?? 'planejamento';
	// "Agendado" do CRM publica sozinho (e sem arte daria erro a cada rodada): o que já está pronto entra como Publicado.
	if ( 'agendado' === $role ) {
		$role = 'publicado';
	}
	return lk_stage_for( $role );
}

/**
 * Lê as linhas e separa o que vale importar.
 * Devolve array( board, about, instagram, items[], skipped[ vazios, cancelados ], months[] ).
 */
function lk_import_parse( $rows ) {
	$out = array( 'board' => '', 'about' => '', 'instagram' => '', 'items' => array(), 'skipped' => array( 'vazios' => 0, 'cancelados' => 0 ), 'columns' => array() );
	$map = null;
	foreach ( $rows as $ri => $row ) {
		$filled = array_values( array_filter( $row, function ( $c ) { return '' !== trim( (string) $c ); } ) );
		if ( ! $filled ) {
			continue;
		}
		$hdr = lk_import_header( $row );
		if ( $hdr ) {
			$map = $hdr;
			if ( ! $out['columns'] ) {
				$out['columns'] = array_keys( $hdr );
			}
			continue;
		}
		if ( ! $map ) { // antes do primeiro cabeçalho: nome do quadro e descrição
			if ( preg_match( '/^\p{L}+\s+\d{4}$/u', trim( (string) $filled[0] ) ) && 1 === count( $filled ) ) {
				continue; // título de grupo (mês) antes do cabeçalho
			}
			if ( '' === $out['board'] ) {
				$out['board'] = trim( (string) $filled[0] );
			} else {
				$out['about'] .= ( $out['about'] ? "\n" : '' ) . trim( (string) $filled[0] );
			}
			if ( preg_match( '#instagram\.com/([A-Za-z0-9_.]+)#', implode( ' ', $filled ), $m ) ) {
				$out['instagram'] = $m[1];
			}
			continue;
		}
		$get   = function ( $key ) use ( $row, $map ) {
			return isset( $map[ $key ] ) && isset( $row[ $map[ $key ] ] ) ? trim( (string) $row[ $map[ $key ] ] ) : '';
		};
		$name  = $get( 'name' );
		$date  = lk_import_date( $get( 'date' ) );
		$extra = array( $get( 'product' ), $get( 'arte' ), $get( 'legenda' ), $get( 'video' ), $get( 'people' ), $get( 'entrega' ) );
		$other = array_filter( $extra, function ( $v ) { return '' !== $v; } );
		// Título de grupo (ex.: "Outubro 2026"): só a primeira célula preenchida.
		if ( 1 === count( $filled ) && '' !== $name && '' === $date && preg_match( '/^\p{L}+\s+\d{4}$/u', $name ) ) {
			continue;
		}
		// Sem texto de verdade: vazio ou só pontuação (".", "-", "…").
		if ( '' === $name || '' === preg_replace( '/[\s\p{P}\p{S}]+/u', '', $name ) ) {
			$out['skipped']['vazios']++;
			continue;
		}
		$ranks = array();
		foreach ( array( 'arte', 'legenda', 'video' ) as $col ) {
			$r = lk_import_status_rank( $get( $col ) );
			if ( null !== $r ) {
				$ranks[ $col ] = $r;
			}
		}
		if ( in_array( 'x', $ranks, true ) || false !== strpos( lk_import_norm( $get( 'product' ) ), 'cancel' ) ) {
			$out['skipped']['cancelados']++;
			continue;
		}
		// Sem nenhum status: se a data já passou, é histórico (entra como publicado); se não, começa no planejamento.
		$rank = $ranks ? min( $ranks ) : ( $date && $date < lk_today() ? 5 : 0 );
		$note = array();
		foreach ( array( 'arte' => 'Arte', 'legenda' => 'Legenda', 'video' => 'Vídeo' ) as $col => $lab ) {
			if ( '' !== $get( $col ) ) {
				$note[] = $lab . ': ' . $get( $col );
			}
		}
		if ( '' !== $get( 'entrega' ) ) {
			$d      = lk_import_date( $get( 'entrega' ) );
			$note[] = 'Entrega da arte: ' . ( $d ? lk_date( $d ) : $get( 'entrega' ) );
		}
		if ( '' !== $get( 'people' ) ) {
			$note[] = 'Responsáveis: ' . $get( 'people' );
		}
		$out['items'][] = array(
			'title'  => mb_substr( $name, 0, 190 ),
			'date'   => $date,
			'format' => lk_import_format( $get( 'product' ), isset( $ranks['video'] ) ),
			'rank'   => $rank,
			'notes'  => $note ? 'Importado da planilha · ' . implode( ' · ', $note ) : 'Importado da planilha',
			'row'    => $ri + 1,
		);
	}
	return $out;
}

/** Cliente cujo nome combina com o do quadro (MEGAMOTOS → Mega Motos). */
function lk_import_guess_client( $board ) {
	$b = str_replace( '-', '', lk_import_norm( $board ) );
	if ( '' === $b ) {
		return 0;
	}
	foreach ( lk_clients() as $c ) {
		foreach ( array( $c->company, $c->name ) as $label ) {
			$n = str_replace( '-', '', lk_import_norm( $label ) );
			if ( '' !== $n && ( $n === $b || false !== strpos( $n, $b ) || false !== strpos( $b, $n ) ) ) {
				return (int) $c->id;
			}
		}
	}
	return 0;
}

/* -----------------------------------------------------------------------
 * Ações
 * -------------------------------------------------------------------- */

/** Passo 1: recebe o arquivo, lê e guarda o resultado para a pré-visualização. */
function lk_do_import_upload() {
	lk_require( 'conteudo' );
	$f = $_FILES['planilha'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	if ( ! $f || empty( $f['name'] ) || empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
		lk_back( 'Escolha o arquivo da planilha.', 'erro' );
	}
	if ( ! empty( $f['size'] ) && $f['size'] > 15 * MB_IN_BYTES ) {
		lk_back( 'O arquivo passa de 15 MB.', 'erro' );
	}
	$ext = strtolower( pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'xlsx', 'csv' ), true ) ) {
		lk_back( 'Envie um arquivo .xlsx (Excel) ou .csv.', 'erro' );
	}
	$rows = 'csv' === $ext ? lk_csv_rows( $f['tmp_name'] ) : lk_xlsx_rows( $f['tmp_name'] );
	if ( is_wp_error( $rows ) ) {
		lk_back( $rows->get_error_message(), 'erro' );
	}
	$data = lk_import_parse( $rows );
	if ( ! $data['items'] ) {
		lk_back( 'Não encontrei nenhuma linha com texto para importar. Confira se a planilha tem as colunas Name (ou Nome) e Data.', 'erro' );
	}
	$data['user']  = get_current_user_id();
	$data['client_id'] = lk_in( 'client_id', 'int' );
	$data['file']  = sanitize_file_name( $f['name'] );
	$token         = strtolower( wp_generate_password( 20, false ) );
	set_transient( 'lk_import_' . $token, $data, HOUR_IN_SECONDS );
	lk_back( '', 'ok', lk_panel_url( 'importar', 0, array( 't' => $token ) ) );
}

/** Bloco da ficha do cliente: botão + janela para subir a planilha já com o cliente escolhido. */
function lk_client_import_html( $client ) {
	ob_start();
	?>
	<section class="card" id="importar">
		<div class="pay-row"><span><strong>Importar planilha de conteúdo (Excel)</strong><small>Suba o Excel do Monday e o sistema preenche o conteúdo deste cliente. Só entram as linhas com texto.</small></span>
			<button type="button" class="btn btn--primary btn--sm" data-open="importar-planilha"><?php echo lk_icon( 'upload', 15 ); // phpcs:ignore ?><span>Subir Excel</span></button></div>
	</section>
	<?php
	lk_modal_start( 'importar-planilha', 'Importar planilha · ' . lk_client_label( $client ) );
	lk_form( 'import_upload', 'stack', true );
	?>
		<input type="hidden" name="client_id" value="<?php echo (int) $client->id; ?>">
		<p class="muted small">Arquivo .xlsx (ou .csv) com as colunas Name/Nome, Data, Produto, Arte, Legenda, Vídeo e Pessoa. Você vê uma prévia antes de importar.</p>
		<label class="field"><span>Arquivo (até 15 MB)</span><input type="file" name="planilha" accept=".xlsx,.csv" required></label>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Ler a planilha</button></div>
	</form>
	<?php
	lk_modal_end();
	return ob_get_clean();
}

/** Passo 2: grava os posts. */
function lk_do_import_commit() {
	lk_require( 'conteudo' );
	global $wpdb;
	$token  = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) lk_in( 'token' ) ) );
	$data   = $token ? get_transient( 'lk_import_' . $token ) : false;
	$client = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	if ( ! $data || (int) $data['user'] !== get_current_user_id() ) {
		lk_back( 'A pré-visualização expirou. Envie a planilha de novo.', 'erro', lk_panel_url( 'importar' ) );
	}
	if ( ! $client ) {
		lk_back( 'Escolha o cliente que vai receber os posts.', 'erro', lk_panel_url( 'importar', 0, array( 't' => $token ) ) );
	}
	$table = lk_table( 'posts' );
	$added = 0;
	$dups  = 0;
	$now   = lk_now();
	foreach ( $data['items'] as $it ) {
		$sched = $it['date'] ? $it['date'] . ' 10:00:00' : null;
		if ( $it['date'] ) {
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE client_id = %d AND title = %s AND DATE(scheduled_at) = %s LIMIT 1", $client->id, $it['title'], $it['date'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		} else {
			$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE client_id = %d AND title = %s AND scheduled_at IS NULL LIMIT 1", $client->id, $it['title'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		if ( $exists ) {
			$dups++;
			continue;
		}
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'client_id'      => $client->id,
				'title'          => $it['title'],
				'format'         => isset( lk_formats()[ $it['format'] ] ) ? $it['format'] : 'arte',
				'scheduled_at'   => $sched,
				'stage'          => lk_import_stage( (int) $it['rank'] ),
				'designer_id'    => (int) $client->designer_id,
				'social_id'      => (int) $client->social_id,
				'atendimento_id' => (int) $client->atendimento_id,
				'revisor_id'     => (int) $client->revisor_id,
				'notes'          => $it['notes'],
				'approval_token' => strtolower( wp_generate_password( 24, false ) ),
				'created_by'     => get_current_user_id(),
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);
		$added++;
	}
	if ( ! $client->instagram && ! empty( $data['instagram'] ) && lk_in( 'usar_instagram', 'bool' ) ) {
		lk_update( 'clients', $client->id, array( 'instagram' => $data['instagram'] ) );
	}
	delete_transient( 'lk_import_' . $token );
	lk_back( $added . ' posts importados para ' . lk_client_label( $client ) . ( $dups ? ' (' . $dups . ' já existiam e foram pulados)' : '' ) . '.', 'ok', lk_panel_url( 'conteudo', 0, array( 'cliente' => $client->id ) ) );
}
