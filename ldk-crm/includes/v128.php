<?php
/**
 * v1.28: pausar postagem, collab, anexo/áudio nos ajustes, equipe nas reuniões,
 * WhatsApp em tudo que vai para o cliente e a aba Roteiros (gravação de vídeo).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Banco
 * -------------------------------------------------------------------- */

function lk_v128_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c    = $wpdb->get_charset_collate();
	$cols = array(
		'posts'         => array(
			'paused' => 'tinyint(1) NOT NULL DEFAULT 0',
			'collab' => "varchar(190) NOT NULL DEFAULT ''",
			'resend' => 'tinyint(1) NOT NULL DEFAULT 0',
		),
		'post_comments' => array(
			'attach_url'  => 'text NULL',
			'attach_kind' => "varchar(10) NOT NULL DEFAULT ''",
		),
		'meetings'      => array(
			'team_ids' => "varchar(255) NOT NULL DEFAULT ''",
		),
		'tasks'         => array(
			'client_id' => 'bigint(20) unsigned NOT NULL DEFAULT 0',
		),
		'clients'       => array(
			'videos_quota' => 'int(11) NOT NULL DEFAULT 0',
			'package'      => "varchar(120) NOT NULL DEFAULT ''",
		),
	);
	foreach ( $cols as $table => $list ) {
		$t = lk_table( $table );
		foreach ( $list as $col => $def ) {
			if ( ! $wpdb->get_var( "SHOW COLUMNS FROM $t LIKE '$col'" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL
				$wpdb->query( "ALTER TABLE $t ADD COLUMN $col $def" ); // phpcs:ignore WordPress.DB.PreparedSQL
			}
		}
	}
	dbDelta(
		'CREATE TABLE ' . lk_table( 'scripts' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(190) NOT NULL DEFAULT '',
			body longtext NULL,
			record_date date NULL,
			record_time varchar(5) NOT NULL DEFAULT '',
			place varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'rascunho',
			token varchar(40) NOT NULL DEFAULT '',
			client_note text NULL,
			sent_at datetime NULL,
			approved_at datetime NULL,
			notified_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY client_id (client_id),
			KEY record_date (record_date),
			KEY token (token)
		) $c;"
	);
}

/* -----------------------------------------------------------------------
 * Pausar postagem
 * -------------------------------------------------------------------- */

function lk_do_post_pause() {
	lk_require( 'conteudo' );
	$p = lk_get( 'posts', lk_in( 'id', 'int' ) );
	if ( ! $p ) {
		lk_back();
	}
	$now = $p->paused ? 0 : 1;
	lk_update( 'posts', $p->id, array( 'paused' => $now ) );
	lk_post_log( $p->id, $now ? 'Postagem pausada: não será publicada automaticamente.' : 'Postagem retomada.' );
	lk_back( $now ? 'Postagem pausada. Ela não sai sozinha até você retomar.' : 'Postagem retomada.' );
}

/* -----------------------------------------------------------------------
 * Anexo (referência ou áudio) nos pedidos de ajuste e apontamentos
 * -------------------------------------------------------------------- */

/**
 * Recebe um arquivo do formulário (imagem, PDF, vídeo curto ou áudio, até 25 MB).
 * Devolve array( url, kind ), null (nada enviado) ou WP_Error.
 */
function lk_save_attach( $field ) {
	if ( empty( $_FILES[ $field ]['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return null;
	}
	$file = $_FILES[ $field ]; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	if ( ! empty( $file['error'] ) ) {
		return new WP_Error( 'lk_attach', 'Não foi possível receber o arquivo.' );
	}
	if ( $file['size'] > 25 * MB_IN_BYTES ) {
		return new WP_Error( 'lk_attach', 'O arquivo passa de 25 MB.' );
	}
	$mimes = array(
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'webp'     => 'image/webp',
		'gif'      => 'image/gif',
		'pdf'      => 'application/pdf',
		'mp4'      => 'video/mp4',
		'mp3'      => 'audio/mpeg',
		'm4a'      => 'audio/mp4',
		'ogg'      => 'audio/ogg',
		'wav'      => 'audio/wav',
		'webm'     => 'audio/webm',
	);
	$ext = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
	$ok  = false;
	foreach ( array_keys( $mimes ) as $k ) {
		if ( in_array( $ext, explode( '|', $k ), true ) ) {
			$ok = true;
		}
	}
	if ( ! $ok ) {
		return new WP_Error( 'lk_attach', 'Esse tipo de arquivo não é aceito. Envie imagem, PDF, vídeo MP4 ou áudio.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$up = wp_handle_upload( $file, array( 'test_form' => false, 'test_type' => false, 'mimes' => $mimes ) );
	if ( isset( $up['error'] ) ) {
		return new WP_Error( 'lk_attach', 'Não foi possível enviar o arquivo: ' . $up['error'] );
	}
	$kind = in_array( $ext, array( 'mp3', 'm4a', 'ogg', 'wav', 'webm' ), true ) ? 'audio' : ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true ) ? 'image' : 'file' );
	return array( 'url' => esc_url_raw( $up['url'] ), 'kind' => $kind );
}

/** Áudio gravado ou arquivo de referência, o que vier primeiro. */
function lk_collect_attach() {
	foreach ( array( 'audio_file', 'ref_file' ) as $f ) {
		$a = lk_save_attach( $f );
		if ( $a ) {
			return $a;
		}
	}
	return null;
}

function lk_attach_html( $c ) {
	if ( empty( $c->attach_url ) ) {
		return '';
	}
	if ( 'audio' === $c->attach_kind ) {
		return '<div class="att att--audio"><audio controls preload="none" src="' . esc_url( $c->attach_url ) . '"></audio></div>';
	}
	if ( 'image' === $c->attach_kind ) {
		return '<div class="att"><a href="' . esc_url( $c->attach_url ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $c->attach_url ) . '" alt="Referência" style="max-width:220px;border-radius:10px"></a></div>';
	}
	return '<div class="att"><a href="' . esc_url( $c->attach_url ) . '" target="_blank" rel="noopener">📎 Abrir anexo</a></div>';
}

/* -----------------------------------------------------------------------
 * Equipe marcada na reunião
 * -------------------------------------------------------------------- */

function lk_meeting_team_picker( $selected = array() ) {
	echo '<fieldset class="nets-pick"><legend class="small">Equipe na reunião (todos são avisados)</legend>';
	foreach ( lk_team_users() as $u ) {
		echo '<label class="chk"><input type="checkbox" name="team_ids[]" value="' . (int) $u->ID . '"' . checked( in_array( (int) $u->ID, array_map( 'intval', (array) $selected ), true ), true, false ) . '> ' . esc_html( $u->display_name ) . '</label>';
	}
	echo '</fieldset>';
}

/** Chamado depois de salvar a reunião: guarda e avisa a equipe marcada. */
function lk_meeting_save_team( $meeting_id, $is_new ) {
	$m = lk_get( 'meetings', $meeting_id );
	if ( ! $m ) {
		return;
	}
	$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $_POST['team_ids'] ?? array() ) ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$old = array_filter( array_map( 'absint', explode( ',', (string) $m->team_ids ) ) );
	lk_update( 'meetings', $m->id, array( 'team_ids' => implode( ',', $ids ) ) );
	$c = $m->client_id ? lk_get( 'clients', $m->client_id ) : null;
	foreach ( array_diff( $ids, $old ) as $uid ) {
		lk_notify( $uid, '📅 Você foi marcado(a) na reunião "' . $m->title . '"' . ( $c ? ' com ' . lk_client_label( $c ) : '' ) . ' · ' . lk_date( $m->starts_at, 'd/m' ) . ' às ' . gmdate( 'H:i', strtotime( $m->starts_at ) ), lk_panel_url( 'agenda' ) );
		$u = get_userdata( $uid );
		if ( $u && is_email( $u->user_email ) ) {
			lk_mail(
				$u->user_email,
				'Reunião: ' . $m->title,
				'Você foi marcado(a) em uma reunião',
				'<p>Olá, ' . esc_html( strtok( $u->display_name, ' ' ) ) . '! Você participa da reunião <strong>' . esc_html( $m->title ) . '</strong>' . ( $c ? ' com ' . esc_html( lk_client_label( $c ) ) : '' ) . '.</p>',
				array( array( 'Quando', esc_html( lk_date( $m->starts_at, 'd/m/Y' ) . ' às ' . gmdate( 'H:i', strtotime( $m->starts_at ) ) ) ), array( 'Link ou local', esc_html( $m->place ? $m->place : 'a definir' ) ) ),
				'Abrir a agenda',
				lk_panel_url( 'agenda' )
			);
		}
	}
}

/** Mensagem pronta de WhatsApp para a reunião. */
function lk_meeting_wa( $m ) {
	$c = $m->client_id ? lk_get( 'clients', $m->client_id ) : null;
	if ( ! $c || ! $c->whatsapp ) {
		return '';
	}
	$first = $c->name ? strtok( $c->name, ' ' ) : '';
	$msg   = 'Olá' . ( $first ? ', ' . $first : '' ) . "! 😊\n\n" .
		"Nossa reunião *" . $m->title . "* está marcada:\n\n" .
		"📅 *Dia:* " . lk_date( $m->starts_at, 'd/m/Y' ) . "\n" .
		"🕐 *Hora:* " . gmdate( 'H:i', strtotime( $m->starts_at ) ) . "\n" .
		( $m->place ? "📍 *" . ( preg_match( '#^https?://#i', $m->place ) ? 'Link' : 'Local' ) . ":* " . $m->place . "\n" : '' ) .
		"\nQualquer imprevisto é só avisar por aqui.";
	return lk_wa_link( $c->whatsapp, $msg );
}

/** Reenvia o aviso da reunião ao cliente por e-mail ("Alinhamento" → enviar para o cliente). */
function lk_do_reuniao_enviar() {
	if ( ! lk_is_team() ) {
		wp_die( 'Sem permissão.' );
	}
	$m = lk_get( 'meetings', lk_in( 'id', 'int' ) );
	if ( ! $m ) {
		lk_back();
	}
	lk_back( lk_meeting_mail( $m, 'novo' ) ? 'Reunião enviada ao cliente por e-mail.' : 'O cliente não tem e-mail válido na ficha.', 'ok' );
}

/* -----------------------------------------------------------------------
 * Roteiros de vídeo
 * -------------------------------------------------------------------- */

function lk_script_status_labels() {
	return array(
		'rascunho' => 'Rascunho',
		'enviado'  => 'Enviado ao cliente',
		'ajustes'  => 'Cliente pediu ajustes',
		'aprovado' => 'Aprovado',
	);
}

function lk_script_url( $s ) {
	return home_url( '/roteiro/' . $s->token . '/' );
}

/** Todo post de vídeo ganha o seu roteiro. */
add_action(
	'lk_post_saved',
	function ( $post_id ) {
		$p = lk_get( 'posts', $post_id );
		if ( ! $p || 'video' !== $p->format ) {
			return;
		}
		if ( lk_rows( 'scripts', 'post_id = %d', array( $p->id ) ) ) {
			return;
		}
		lk_insert(
			'scripts',
			array(
				'post_id'    => $p->id,
				'client_id'  => $p->client_id,
				'title'      => $p->title,
				'token'      => strtolower( wp_generate_password( 24, false ) ),
				'created_at' => lk_now(),
			)
		);
	}
);

/** Posts de vídeo antigos sem roteiro. */
function lk_scripts_backfill() {
	foreach ( lk_rows( 'posts', "format = 'video'" ) as $p ) {
		if ( ! lk_rows( 'scripts', 'post_id = %d', array( $p->id ) ) ) {
			lk_insert( 'scripts', array( 'post_id' => $p->id, 'client_id' => $p->client_id, 'title' => $p->title, 'token' => strtolower( wp_generate_password( 24, false ) ), 'created_at' => lk_now() ) );
		}
	}
}

function lk_do_script_save() {
	lk_require( 'conteudo' );
	$s = lk_get( 'scripts', lk_in( 'id', 'int' ) );
	if ( ! $s ) {
		lk_back( 'Roteiro não encontrado.', 'erro' );
	}
	$time = preg_match( '/^\d{2}:\d{2}$/', (string) lk_in( 'record_time' ) ) ? lk_in( 'record_time' ) : '';
	$date = lk_in( 'record_date', 'date' );
	$data = array(
		'title'       => lk_in( 'title' ) ? lk_in( 'title' ) : $s->title,
		'body'        => lk_in( 'body', 'textarea' ),
		'record_date' => $date ? $date : null,
		'record_time' => $time,
		'place'       => lk_in( 'place' ),
	);
	// Mudou a data: o aviso de 3 dias antes vale de novo.
	if ( $date !== $s->record_date ) {
		$data['notified_at'] = null;
	}
	lk_update( 'scripts', $s->id, $data );
	lk_back( 'Roteiro salvo.' );
}

function lk_do_script_status() {
	lk_require( 'conteudo' );
	$s = lk_get( 'scripts', lk_in( 'id', 'int' ) );
	if ( ! $s ) {
		lk_back();
	}
	$to = lk_in( 'to' );
	if ( isset( lk_script_status_labels()[ $to ] ) ) {
		lk_update( 'scripts', $s->id, array( 'status' => $to, 'approved_at' => 'aprovado' === $to ? lk_now() : null ) );
	}
	lk_back( 'Situação do roteiro atualizada.' );
}

/** Mensagem de WhatsApp com o roteiro (organizada e espaçada). */
function lk_script_wa_message( $s, $client ) {
	$first = $client && $client->name ? strtok( $client->name, ' ' ) : '';
	$when  = $s->record_date ? lk_date( $s->record_date, 'd/m/Y' ) . ( $s->record_time ? ' às ' . $s->record_time : '' ) : 'a combinar';
	return 'Olá' . ( $first ? ', ' . $first : '' ) . "! 😊\n\n" .
		"Já deixamos pronto o *roteiro do seu vídeo*:\n\n" .
		"🎬 *Vídeo:* " . $s->title . "\n\n" .
		"📅 *Gravação:* " . $when . "\n\n" .
		( $s->place ? "📍 *Local:* " . $s->place . "\n\n" : '' ) .
		"👉 *Veja o roteiro e aprove aqui:*\n" . lk_script_url( $s ) . "\n\n" .
		"Se quiser mudar alguma coisa, é só pedir por lá.";
}

function lk_script_wa_link( $s ) {
	$c = lk_get( 'clients', $s->client_id );
	return $c && $c->whatsapp ? lk_wa_link( $c->whatsapp, lk_script_wa_message( $s, $c ) ) : '';
}

function lk_do_script_send() {
	lk_require( 'conteudo' );
	$s = lk_get( 'scripts', lk_in( 'id', 'int' ) );
	$c = $s ? lk_get( 'clients', $s->client_id ) : null;
	if ( ! $s || ! $c ) {
		lk_back( 'Roteiro ou cliente não encontrado.', 'erro' );
	}
	if ( '' === trim( (string) $s->body ) ) {
		lk_back( 'Escreva o roteiro antes de enviar.', 'erro' );
	}
	lk_update( 'scripts', $s->id, array( 'status' => 'enviado', 'sent_at' => lk_now() ) );
	$sent = false;
	if ( is_email( $c->email ) && ! $c->email_optout ) {
		$sent = lk_script_mail( $c, array( $s ), 'Roteiro do seu vídeo' );
	}
	lk_back( 'Roteiro marcado como enviado' . ( $sent ? ' e mandado por e-mail.' : '.' ) . ( $c->whatsapp ? ' Use o botão de WhatsApp para reforçar.' : '' ) );
}

/** E-mail com um ou mais roteiros (todos da mesma gravação). */
function lk_script_mail( $client, $scripts, $subject, $title = '' ) {
	$first = strtok( (string) $client->name, ' ' );
	$html  = '<p>Olá' . ( $first ? ', ' . esc_html( $first ) : '' ) . '! Segue o roteiro para a gravação. Confira, e se estiver tudo certo é só aprovar com um clique.</p>';
	foreach ( $scripts as $s ) {
		$html .= '<div style="margin:18px 0;padding:16px;border:1px solid #e7e7e3;border-radius:12px"><h3 style="margin:0 0 6px;font-size:17px">' . esc_html( $s->title ) . '</h3>';
		$html .= '<p style="margin:0 0 10px;color:#737373;font-size:13px">' . ( $s->record_date ? '📅 ' . esc_html( lk_date( $s->record_date, 'd/m/Y' ) . ( $s->record_time ? ' às ' . $s->record_time : '' ) ) : '' ) . ( $s->place ? ' · 📍 ' . esc_html( $s->place ) : '' ) . '</p>';
		$html .= '<div style="font-size:15px;line-height:1.7">' . nl2br( esc_html( (string) $s->body ) ) . '</div>';
		$html .= '<p style="margin:14px 0 0"><a href="' . esc_url( lk_script_url( $s ) ) . '" style="color:#fc5521;font-weight:bold">Ver e aprovar este roteiro →</a></p></div>';
	}
	return lk_mail( $client->email, $subject, $title ? $title : 'Roteiro da sua gravação', $html, array(), count( $scripts ) === 1 ? 'Aprovar o roteiro' : '', count( $scripts ) === 1 ? lk_script_url( $scripts[0] ) : '' );
}

/**
 * Todo dia: 3 dias antes da gravação, o cliente recebe os roteiros do dia por e-mail e a atendente
 * ganha uma tarefa para avisar no WhatsApp e pedir a aprovação.
 */
add_action( 'lk_daily', 'lk_scripts_notice' );
add_action( 'lk_hourly', 'lk_scripts_notice', 95 );
function lk_scripts_notice() {
	if ( get_transient( 'lk_scripts_notice_run' ) ) {
		return;
	}
	set_transient( 'lk_scripts_notice_run', 1, 30 * MINUTE_IN_SECONDS );
	$limit = gmdate( 'Y-m-d', strtotime( lk_today() . ' +3 day' ) );
	$list  = lk_rows( 'scripts', "record_date IS NOT NULL AND record_date >= %s AND record_date <= %s AND notified_at IS NULL AND status <> 'aprovado'", array( lk_today(), $limit ), 'record_date, id' );
	$by    = array();
	foreach ( $list as $s ) {
		if ( '' !== trim( (string) $s->body ) ) {
			$by[ $s->client_id ][ $s->record_date ][] = $s;
		}
	}
	foreach ( $by as $cid => $days ) {
		$client = lk_get( 'clients', $cid );
		if ( ! $client ) {
			continue;
		}
		foreach ( $days as $date => $scripts ) {
			$when = lk_date( $date, 'd/m' );
			if ( is_email( $client->email ) && ! $client->email_optout ) {
				lk_script_mail( $client, $scripts, 'Roteiro da gravação de ' . $when, 'Faltam poucos dias para a sua gravação' );
			}
			// Tarefa para a atendente avisar no WhatsApp e conseguir a aprovação.
			$first = $scripts[0];
			$post  = lk_get( 'posts', $first->post_id );
			$who   = $post && $post->atendimento_id ? (int) $post->atendimento_id : ( $client->atendimento_id ? (int) $client->atendimento_id : 0 );
			$wa    = lk_script_wa_link( $first );
			lk_insert(
				'tasks',
				array(
					'grp'         => 'Roteiro',
					'title'       => 'Avisar ' . lk_client_label( $client ) . ' no WhatsApp: roteiro da gravação de ' . $when,
					'description' => "Pedir a aprovação do roteiro antes de gravar.\n\n" . implode( "\n", array_map( function ( $x ) { return '• ' . $x->title . ' — ' . lk_script_url( $x ); }, $scripts ) ) . ( $wa ? "\n\nAbrir o WhatsApp com a mensagem pronta:\n" . $wa : '' ),
					'status'      => 'todo',
					'priority'    => 'alta',
					'due_date'    => lk_today(),
					'assignee'    => $who,
					'client_id'   => (int) $cid,
					'created_at'  => lk_now(),
				)
			);
			if ( $who ) {
				lk_notify( $who, '🎬 Avisar ' . lk_client_label( $client ) . ' no WhatsApp: roteiro da gravação de ' . $when, lk_panel_url( 'roteiros' ) );
			}
			foreach ( $scripts as $s ) {
				lk_update( 'scripts', $s->id, array( 'notified_at' => lk_now(), 'status' => 'rascunho' === $s->status ? 'enviado' : $s->status, 'sent_at' => $s->sent_at ? $s->sent_at : lk_now() ) );
			}
		}
	}
}

/* Página pública do roteiro (o cliente lê e aprova ou pede ajustes). */
add_action(
	'init',
	function () {
		add_rewrite_rule( '^roteiro/([A-Za-z0-9]+)/?$', 'index.php?lk_route=script&lk_token=$matches[1]', 'top' );
	}
);

add_action(
	'template_redirect',
	function () {
		if ( 'script' !== get_query_var( 'lk_route' ) ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		$rows = lk_rows( 'scripts', 'token = %s', array( sanitize_text_field( get_query_var( 'lk_token' ) ) ) );
		$s    = $rows ? $rows[0] : null;
		if ( ! $s ) {
			lk_render( 'public/indisponivel' );
		}
		$msg = '';
		if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'lk_script_' . $s->id ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$client = lk_get( 'clients', $s->client_id );
			$post   = lk_get( 'posts', $s->post_id );
			$who    = $post && $post->atendimento_id ? (int) $post->atendimento_id : 0;
			if ( 'aprovar' === lk_in( 'decisao' ) ) {
				lk_update( 'scripts', $s->id, array( 'status' => 'aprovado', 'approved_at' => lk_now() ) );
				lk_notify( $who, '✅ ' . lk_client_label( $client ) . ' aprovou o roteiro: ' . $s->title, lk_panel_url( 'roteiros' ) );
				$msg = 'Roteiro aprovado! Obrigado. 🎬';
			} else {
				$note = lk_in( 'comentario', 'textarea' );
				lk_update( 'scripts', $s->id, array( 'status' => 'ajustes', 'client_note' => $note ) );
				lk_notify( $who, '✏️ ' . lk_client_label( $client ) . ' pediu ajustes no roteiro: ' . $s->title . ' — ' . wp_trim_words( $note, 15 ), lk_panel_url( 'roteiros' ) );
				$msg = 'Recebemos o seu pedido de ajuste. Já vamos mexer no roteiro.';
			}
			$s = lk_get( 'scripts', $s->id );
		}
		lk_render( 'public/roteiro', array( 's' => $s, 'msg' => $msg ) );
	},
	0
);

/* -----------------------------------------------------------------------
 * Enviar a semana: todas as artes prontas de um cliente num envio só
 * -------------------------------------------------------------------- */

/** Segunda a domingo da semana que contém a data. */
function lk_week_range( $date = '' ) {
	$ts   = strtotime( ( $date ? $date : lk_today() ) . ' 12:00:00' );
	$dow  = (int) gmdate( 'N', $ts );
	$from = gmdate( 'Y-m-d', strtotime( '-' . ( $dow - 1 ) . ' day', $ts ) );
	return array( $from, gmdate( 'Y-m-d', strtotime( $from . ' +6 day' ) ) );
}

/** Posts do cliente na semana que já têm arte e ainda não foram aprovados nem publicados. */
function lk_week_posts( $client_id, $from, $to ) {
	$out = array();
	$ok  = array( lk_stage_for( 'revisao' ), lk_stage_for( 'aprovacao' ) );
	foreach ( lk_posts( 'p.client_id = %d AND p.scheduled_at >= %s AND p.scheduled_at <= %s', array( (int) $client_id, $from . ' 00:00:00', $to . ' 23:59:59' ), 'p.scheduled_at, p.id' ) as $p ) {
		if ( in_array( $p->stage, $ok, true ) && lk_post_media( $p ) && 'aprovado' !== $p->client_status ) {
			$out[] = $p;
		}
	}
	return $out;
}

function lk_week_message( $client, $posts, $from, $to ) {
	$first  = $client->name ? strtok( $client->name, ' ' ) : '';
	$digits = array( '1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣', '9️⃣', '🔟' );
	$msg    = 'Olá' . ( $first ? ', ' . $first : '' ) . "! 😊\n\n" .
		'Estas são as artes da semana (' . lk_date( $from, 'd/m' ) . ' a ' . lk_date( $to, 'd/m' ) . ") para a sua aprovação:\n\n";
	foreach ( array_values( $posts ) as $i => $p ) {
		$msg .= ( $digits[ $i ] ?? ( ( $i + 1 ) . '.' ) ) . ' *' . $p->title . '*' . ( $p->scheduled_at ? ' · ' . lk_dow_short( substr( $p->scheduled_at, 0, 10 ) ) . ' ' . lk_date( $p->scheduled_at, 'd/m' ) : '' ) . "\n" . lk_post_url( $p ) . "\n\n";
	}
	return $msg . "👉 Abra o primeiro link, confira e aprove. Ao aprovar, você já passa para o próximo.";
}

function lk_do_week_send() {
	lk_require( 'conteudo' );
	$client = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	if ( ! $client ) {
		lk_back( 'Escolha o cliente.', 'erro' );
	}
	$from = lk_in( 'from', 'date' );
	$to   = lk_in( 'to', 'date' );
	$ids  = array_map( 'absint', (array) ( $_POST['posts'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$posts = array();
	foreach ( lk_week_posts( $client->id, $from, $to ) as $p ) {
		if ( in_array( (int) $p->id, $ids, true ) ) {
			$posts[] = $p;
		}
	}
	if ( ! $posts ) {
		lk_back( 'Marque pelo menos um post para enviar.', 'erro' );
	}
	$sent = array();
	foreach ( $posts as $p ) {
		$gate = lk_review_gate( $p, lk_in( 'forcar', 'bool' ) );
		if ( $gate ) {
			lk_back( '"' . $p->title . '": ' . $gate, 'erro' );
		}
	}
	foreach ( $posts as $p ) {
		lk_update( 'posts', $p->id, array( 'client_status' => 'pendente', 'sent_at' => lk_now(), 'change_target' => '', 'resend' => 0 ) );
		lk_post_move( $p, lk_stage_for( 'aprovacao' ), 'Enviado para o cliente aprovar (envio da semana).' );
		$sent[] = lk_get( 'posts', $p->id );
	}
	$mail = false;
	if ( is_email( $client->email ) && ! $client->email_optout ) {
		$html = '<p>Olá, ' . esc_html( strtok( (string) $client->name, ' ' ) ) . '! Estas são as artes da semana (' . esc_html( lk_date( $from, 'd/m' ) . ' a ' . lk_date( $to, 'd/m' ) ) . '). Abra cada uma, confira e aprove. Ao aprovar, você já passa para a próxima.</p>';
		foreach ( $sent as $i => $p ) {
			$media = lk_post_media( $p );
			$img   = $media && 'image' === $media[0]['type'] && 0 === strpos( (string) $media[0]['id'], 'wp-' ) ? $media[0]['link'] : '';
			$html .= '<div style="margin:16px 0;padding:14px;border:1px solid #e7e7e3;border-radius:12px"><strong>' . ( $i + 1 ) . '. ' . esc_html( $p->title ) . '</strong><br><small style="color:#737373">' . esc_html( ( lk_formats()[ $p->format ] ?? '' ) . ( $p->scheduled_at ? ' · ' . lk_date( $p->scheduled_at, 'd/m' ) : '' ) ) . '</small>' . ( $img ? '<br><img src="' . esc_url( $img ) . '" alt="" style="width:100%;max-width:320px;border-radius:10px;margin-top:8px">' : '' ) . '<br><a href="' . esc_url( lk_post_url( $p ) ) . '" style="color:#fc5521;font-weight:bold">Ver e aprovar →</a></div>';
		}
		$mail = lk_mail( $client->email, 'Artes da semana para aprovar (' . lk_date( $from, 'd/m' ) . ' a ' . lk_date( $to, 'd/m' ) . ')', 'As artes da semana estão prontas', $html, array(), 'Começar a aprovar', lk_post_url( $sent[0] ) );
	}
	$wa = $client->whatsapp ? lk_wa_link( $client->whatsapp, lk_week_message( $client, $sent, $from, $to ) ) : '';
	set_transient( 'lk_week_wa_' . get_current_user_id(), array( 'client' => $client->id, 'wa' => $wa, 'n' => count( $sent ) ), 900 );
	lk_back( count( $sent ) . ' post(s) enviados' . ( $mail ? ' por e-mail' : ' (o cliente não tem e-mail válido)' ) . '. Agora é só mandar no WhatsApp.', 'ok', lk_panel_url( 'semana', 0, array( 'cliente' => $client->id ) ) );
}

/**
 * Depois que o cliente aprova, leva direto para o próximo conteúdo esperando aprovação.
 */
function lk_next_pending_url( $p ) {
	foreach ( lk_posts( '(p.stage = %s OR p.client_status = %s) AND p.client_id = %d AND p.id <> %d', array( lk_stage_for( 'aprovacao' ), 'pendente', (int) $p->client_id, (int) $p->id ), 'p.scheduled_at, p.id' ) as $n ) {
		if ( 'aprovado' !== $n->client_status ) {
			return add_query_arg( 'aprovado', '1', lk_post_url( $n ) );
		}
	}
	return '';
}
