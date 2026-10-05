<?php
/**
 * Chat interno da equipe:
 *   - canal "Geral", um canal por cliente e conversas individuais (dm<id>-<id>, só as duas pessoas veem);
 *   - @nome notifica a pessoa (sininho);
 *   - mensagens de áudio (gravadas no navegador, guardadas fora do alcance público);
 *   - presença: online / ausente / offline, com a bolinha de chat flutuante em todas as telas do painel.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		$team = function () {
			return lk_is_team();
		};
		register_rest_route( 'lk/v1', '/team', array( array( 'methods' => 'GET', 'callback' => 'lk_api_team_get', 'permission_callback' => $team ), array( 'methods' => 'POST', 'callback' => 'lk_api_team_send', 'permission_callback' => $team ) ) );
		register_rest_route( 'lk/v1', '/team/hub', array( 'methods' => 'POST', 'callback' => 'lk_api_team_hub', 'permission_callback' => $team ) );
		register_rest_route( 'lk/v1', '/team/call', array( 'methods' => 'POST', 'callback' => 'lk_api_team_call', 'permission_callback' => $team ) );
		register_rest_route( 'lk/v1', '/team/call/answer', array( 'methods' => 'POST', 'callback' => 'lk_api_team_call_answer', 'permission_callback' => $team ) );
		register_rest_route( 'lk/v1', '/team/audio', array( array( 'methods' => 'POST', 'callback' => 'lk_api_team_audio_send', 'permission_callback' => $team ), array( 'methods' => 'GET', 'callback' => 'lk_api_team_audio_get', 'permission_callback' => $team ) ) );
	}
);

/* -----------------------------------------------------------------------
 * Canais
 * -------------------------------------------------------------------- */

function lk_dm_channel( $a, $b ) {
	$a = (int) $a;
	$b = (int) $b;
	return 'dm' . min( $a, $b ) . '-' . max( $a, $b );
}

/**
 * Pessoas de uma conversa individual (ou null se não for uma).
 */
function lk_dm_pair( $channel ) {
	return preg_match( '/^dm(\d+)-(\d+)$/', $channel, $m ) ? array( (int) $m[1], (int) $m[2] ) : null;
}

function lk_team_channel( $raw ) {
	$raw = strtolower( preg_replace( '/[^a-z0-9-]/i', '', (string) $raw ) );
	if ( preg_match( '/^(geral|c\d+)$/', $raw ) ) {
		return $raw;
	}
	$pair = lk_dm_pair( $raw );
	return $pair ? lk_dm_channel( $pair[0], $pair[1] ) : 'geral';
}

/**
 * Conversa individual: só as duas pessoas. Canais geral e de cliente: toda a equipe.
 */
function lk_team_channel_allowed( $channel, $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$pair    = lk_dm_pair( $channel );
	if ( ! $pair ) {
		return true;
	}
	return $pair[0] !== $pair[1] && in_array( (int) $user_id, $pair, true ) && lk_is_team( $pair[0] === (int) $user_id ? $pair[1] : $pair[0] );
}

function lk_team_channel_label( $channel ) {
	$pair = lk_dm_pair( $channel );
	if ( $pair ) {
		$other = get_userdata( $pair[0] === get_current_user_id() ? $pair[1] : $pair[0] );
		return $other ? $other->display_name : 'Conversa';
	}
	$chs = lk_team_channels();
	return isset( $chs[ $channel ] ) ? '# ' . $chs[ $channel ] : '# Geral';
}

function lk_team_channels() {
	$out = array( 'geral' => 'Geral' );
	foreach ( lk_clients() as $c ) {
		$out[ 'c' . $c->id ] = lk_short_or_label( $c );
	}
	return $out;
}

function lk_short_or_label( $c ) {
	return lk_client_label( $c );
}

/* -----------------------------------------------------------------------
 * Mensagens
 * -------------------------------------------------------------------- */

function lk_team_audio_url( $id ) {
	return add_query_arg( array( 'id' => (int) $id, '_wpnonce' => wp_create_nonce( 'wp_rest' ) ), rest_url( 'lk/v1/team/audio' ) );
}

function lk_team_thread( $channel ) {
	$out = array();
	foreach ( array_reverse( lk_rows( 'team_chat', 'channel = %s', array( $channel ), 'id DESC LIMIT 150' ) ) as $m ) {
		$u     = get_userdata( $m->user_id );
		$out[] = array(
			'id'    => (int) $m->id,
			'mine'  => (int) $m->user_id === get_current_user_id(),
			'who'   => $u ? $u->display_name : '—',
			'body'  => (string) $m->body,
			'audio' => $m->audio ? lk_team_audio_url( $m->id ) : '',
			'sec'   => (int) $m->audio_sec,
			'at'    => lk_date( $m->created_at, 'd/m H:i' ),
		);
	}
	return $out;
}

function lk_api_team_get( WP_REST_Request $r ) {
	$ch = lk_team_channel( $r['channel'] );
	if ( ! lk_team_channel_allowed( $ch ) ) {
		return new WP_Error( 'lk', 'Conversa não encontrada.', array( 'status' => 403 ) );
	}
	update_user_meta( get_current_user_id(), 'lk_seen_' . $ch, lk_now() );
	return array( 'messages' => lk_team_thread( $ch ), 'title' => lk_team_channel_label( $ch ) );
}

function lk_api_team_send( WP_REST_Request $r ) {
	$ch   = lk_team_channel( $r['channel'] );
	$body = trim( sanitize_textarea_field( (string) $r['body'] ) );
	if ( ! lk_team_channel_allowed( $ch ) ) {
		return new WP_Error( 'lk', 'Conversa não encontrada.', array( 'status' => 403 ) );
	}
	if ( '' === $body ) {
		return new WP_Error( 'lk', 'Escreva a mensagem.', array( 'status' => 400 ) );
	}
	lk_insert( 'team_chat', array( 'channel' => $ch, 'user_id' => get_current_user_id(), 'body' => mb_substr( $body, 0, 4000 ) ) );
	update_user_meta( get_current_user_id(), 'lk_seen_' . $ch, lk_now() );
	if ( ! lk_dm_pair( $ch ) ) {
		lk_notify_mentions( $body, lk_panel_url( 'chat', 0, array( 'canal' => $ch ) ), wp_get_current_user()->display_name );
	}
	return array( 'messages' => lk_team_thread( $ch ) );
}

/* -----------------------------------------------------------------------
 * Áudio: arquivo em uploads/lk-audio (bloqueado para acesso direto), servido só para quem está na conversa
 * -------------------------------------------------------------------- */

function lk_team_audio_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'lk-audio';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( $dir . '/index.php', '<?php // Silêncio.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	return $dir;
}

function lk_team_audio_types() {
	return array(
		'audio/webm' => 'webm',
		'video/webm' => 'webm',
		'audio/ogg'  => 'ogg',
		'audio/mp4'  => 'm4a',
		'video/mp4'  => 'm4a',
		'audio/x-m4a' => 'm4a',
		'audio/aac'  => 'aac',
		'audio/mpeg' => 'mp3',
		'audio/wav'  => 'wav',
		'audio/x-wav' => 'wav',
	);
}

function lk_api_team_audio_send( WP_REST_Request $r ) {
	$ch = lk_team_channel( $r['channel'] );
	if ( ! lk_team_channel_allowed( $ch ) ) {
		return new WP_Error( 'lk', 'Conversa não encontrada.', array( 'status' => 403 ) );
	}
	$files = $r->get_file_params();
	$f     = isset( $files['audio'] ) ? $files['audio'] : null;
	if ( ! $f || ! empty( $f['error'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
		return new WP_Error( 'lk', 'O áudio não chegou. Tente de novo.', array( 'status' => 400 ) );
	}
	if ( $f['size'] > 12 * MB_IN_BYTES ) {
		return new WP_Error( 'lk', 'Áudio grande demais (máx. 12 MB, uns 10 minutos).', array( 'status' => 400 ) );
	}
	// O tipo real do arquivo manda (não o que o navegador disse).
	$mime  = function_exists( 'finfo_open' ) ? (string) finfo_file( finfo_open( FILEINFO_MIME_TYPE ), $f['tmp_name'] ) : '';
	$types = lk_team_audio_types();
	if ( ! isset( $types[ $mime ] ) ) {
		$said = strtolower( strtok( (string) $f['type'], ';' ) );
		if ( 'application/octet-stream' !== $mime || ! isset( $types[ $said ] ) ) {
			return new WP_Error( 'lk', 'Formato de áudio não aceito.', array( 'status' => 400 ) );
		}
		$mime = $said;
	}
	$name = gmdate( 'Y-m' ) . '/' . wp_generate_password( 24, false ) . '.' . $types[ $mime ];
	$dest = lk_team_audio_dir() . '/' . $name;
	wp_mkdir_p( dirname( $dest ) );
	if ( ! move_uploaded_file( $f['tmp_name'], $dest ) ) {
		return new WP_Error( 'lk', 'Não foi possível salvar o áudio.', array( 'status' => 500 ) );
	}
	$play = 'video/webm' === $mime ? 'audio/webm' : ( 'video/mp4' === $mime ? 'audio/mp4' : $mime );
	lk_insert(
		'team_chat',
		array(
			'channel'    => $ch,
			'user_id'    => get_current_user_id(),
			'body'       => '',
			'audio'      => $name,
			'audio_mime' => $play,
			'audio_sec'  => min( 3600, absint( $r['sec'] ) ),
		)
	);
	update_user_meta( get_current_user_id(), 'lk_seen_' . $ch, lk_now() );
	return array( 'messages' => lk_team_thread( $ch ) );
}

/**
 * Toca o áudio (com suporte a Range, que o Safari exige).
 */
function lk_api_team_audio_get( WP_REST_Request $r ) {
	$m = lk_get( 'team_chat', absint( $r['id'] ) );
	if ( ! $m || ! $m->audio || ! lk_team_channel_allowed( $m->channel ) ) {
		return new WP_Error( 'lk', 'Áudio não encontrado.', array( 'status' => 404 ) );
	}
	$path = realpath( lk_team_audio_dir() . '/' . $m->audio );
	if ( ! $path || 0 !== strpos( $path, realpath( lk_team_audio_dir() ) ) || ! is_readable( $path ) ) {
		return new WP_Error( 'lk', 'Áudio não encontrado.', array( 'status' => 404 ) );
	}
	$size  = filesize( $path );
	$start = 0;
	$end   = $size - 1;
	$range = isset( $_SERVER['HTTP_RANGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) ) : '';
	if ( preg_match( '/bytes=(\d*)-(\d*)/', $range, $rm ) ) {
		if ( '' === $rm[1] && '' !== $rm[2] ) {
			$start = max( 0, $size - (int) $rm[2] );
		} else {
			$start = (int) $rm[1];
			$end   = '' !== $rm[2] ? min( (int) $rm[2], $size - 1 ) : $end;
		}
		if ( $start > $end || $start >= $size ) {
			status_header( 416 );
			header( 'Content-Range: bytes */' . $size );
			exit;
		}
		status_header( 206 );
		header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $size );
	} else {
		status_header( 200 );
	}
	while ( ob_get_level() ) {
		ob_end_clean();
	}
	header( 'Content-Type: ' . ( $m->audio_mime ? $m->audio_mime : 'audio/webm' ) );
	header( 'Accept-Ranges: bytes' );
	header( 'Content-Length: ' . ( $end - $start + 1 ) );
	header( 'Cache-Control: private, max-age=86400' );
	header( 'X-Content-Type-Options: nosniff' );
	$fh = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fseek( $fh, $start );
	$left = $end - $start + 1;
	while ( $left > 0 && ! feof( $fh ) ) {
		$chunk = fread( $fh, min( 65536, $left ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput
		$left -= strlen( $chunk );
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

/* -----------------------------------------------------------------------
 * Ligar: cria a sala (Google Meet com a equipe convidada; sem Google, Jitsi),
 * avisa as pessoas ("Fulana está te ligando") e registra atender / recusar.
 * -------------------------------------------------------------------- */

function lk_call_room( $title, $user_ids ) {
	if ( function_exists( 'lk_google_connected' ) && lk_google_connected() ) {
		$emails = array();
		foreach ( $user_ids as $uid ) {
			$u = get_userdata( $uid );
			if ( $u && is_email( $u->user_email ) ) {
				$emails[] = array( 'email' => $u->user_email );
			}
		}
		$start = new DateTime( 'now', wp_timezone() );
		$end   = ( clone $start )->modify( '+30 minutes' );
		$body  = array(
			'summary'            => $title,
			'start'              => array( 'dateTime' => $start->format( 'c' ), 'timeZone' => wp_timezone_string() ),
			'end'                => array( 'dateTime' => $end->format( 'c' ), 'timeZone' => wp_timezone_string() ),
			// Convidados entram direto, sem "pedir para participar". Sem e-mail de convite a cada ligação.
			'attendees'          => $emails,
			'guestsCanModify'    => false,
			'conferenceData'     => array( 'createRequest' => array( 'requestId' => wp_generate_password( 16, false ), 'conferenceSolutionKey' => array( 'type' => 'hangoutsMeet' ) ) ),
			'extendedProperties' => array( 'private' => array( 'lk_call' => '1' ) ),
		);
		$res = lk_google_api( 'POST', 'https://www.googleapis.com/calendar/v3/calendars/primary/events?sendUpdates=none&conferenceDataVersion=1', $body );
		if ( ! is_wp_error( $res ) && ! empty( $res['hangoutLink'] ) ) {
			return array( $res['hangoutLink'], 'Google Meet' );
		}
	}
	return array( 'https://meet.jit.si/LDK' . wp_generate_password( 20, false ), 'Jitsi' );
}

function lk_calls() {
	$calls = get_option( 'lk_calls', array() );
	$calls = is_array( $calls ) ? $calls : array();
	// Guarda só as chamadas das últimas 2 horas.
	return array_filter( $calls, function ( $c ) { return time() - (int) $c['at'] < 2 * HOUR_IN_SECONDS; } );
}

function lk_api_team_call( WP_REST_Request $r ) {
	$ch = lk_team_channel( $r['channel'] );
	$me = get_current_user_id();
	if ( ! lk_team_channel_allowed( $ch ) ) {
		return new WP_Error( 'lk', 'Conversa não encontrada.', array( 'status' => 403 ) );
	}
	$pair = lk_dm_pair( $ch );
	if ( $pair ) {
		$to = array( $pair[0] === $me ? $pair[1] : $pair[0] );
	} elseif ( 'geral' === $ch ) {
		$to = array_values( array_diff( array_map( 'intval', wp_list_pluck( lk_team_users(), 'ID' ) ), array( $me ) ) );
	} else {
		return new WP_Error( 'lk', 'Ligação só nas conversas individuais e no Geral.', array( 'status' => 400 ) );
	}
	if ( ! $to ) {
		return new WP_Error( 'lk', 'Não tem ninguém para receber a ligação.', array( 'status' => 400 ) );
	}
	$name  = wp_get_current_user()->display_name;
	$title = '📞 Chamada · ' . $name . ( $pair ? ' e ' . get_userdata( $to[0] )->display_name : ' com a equipe' );
	list( $url, $via ) = lk_call_room( $title, array_merge( array( $me ), $to ) );
	$id            = wp_generate_password( 12, false );
	$calls         = lk_calls();
	$calls[ $id ]  = array( 'id' => $id, 'from' => $me, 'name' => $name, 'url' => $url, 'ch' => $ch, 'to' => $to, 'answered' => array(), 'declined' => array(), 'at' => time() );
	update_option( 'lk_calls', $calls, false );
	lk_insert( 'team_chat', array( 'channel' => $ch, 'user_id' => $me, 'body' => '📞 Chamada de vídeo (' . $via . '). Entrar: ' . $url ) );
	update_user_meta( $me, 'lk_seen_' . $ch, lk_now() );
	return array( 'url' => $url, 'via' => $via, 'messages' => lk_team_thread( $ch ) );
}

function lk_api_team_call_answer( WP_REST_Request $r ) {
	$me    = get_current_user_id();
	$calls = lk_calls();
	$id    = preg_replace( '/[^A-Za-z0-9]/', '', (string) $r['id'] );
	if ( ! isset( $calls[ $id ] ) || ! in_array( $me, array_map( 'intval', $calls[ $id ]['to'] ), true ) ) {
		return new WP_Error( 'lk', 'Chamada não encontrada.', array( 'status' => 404 ) );
	}
	$c = $calls[ $id ];
	if ( 'recusar' === $r['action'] ) {
		$calls[ $id ]['declined'][] = $me;
		lk_insert( 'team_chat', array( 'channel' => $c['ch'], 'user_id' => $me, 'body' => '📵 Não pude atender agora.' ) );
	} else {
		$calls[ $id ]['answered'][] = $me;
	}
	update_option( 'lk_calls', $calls, false );
	return array( 'url' => $c['url'] );
}

/**
 * Ligações tocando para mim agora (até 75 s depois de começar, sem resposta minha).
 */
function lk_calls_ringing( $me ) {
	$out = array();
	foreach ( lk_calls() as $c ) {
		if ( time() - (int) $c['at'] > 75 || ! in_array( $me, array_map( 'intval', $c['to'] ), true ) || in_array( $me, $c['answered'], true ) || in_array( $me, $c['declined'], true ) ) {
			continue;
		}
		$out[] = array( 'id' => $c['id'], 'name' => $c['name'], 'ini' => lk_initials( $c['name'] ), 'group' => 'geral' === $c['ch'] );
	}
	return $out;
}

/* -----------------------------------------------------------------------
 * Presença: o painel aberto manda um sinal a cada ~45 s.
 *   online  = sinal recente e mexendo no computador;
 *   ausente = escolheu "Ausente" ou está parado há 5 min;
 *   offline = escolheu "Offline" ou sem sinal há mais de 2,5 min (fechou o painel).
 * -------------------------------------------------------------------- */

function lk_presence_labels() {
	return array(
		'online'  => 'Online',
		'ausente' => 'Ausente',
		'offline' => 'Offline',
	);
}

function lk_presence( $user_id ) {
	$ping   = (int) get_user_meta( $user_id, 'lk_ping', true );
	$manual = (string) get_user_meta( $user_id, 'lk_presence', true );
	if ( 'offline' === $manual || ! $ping || time() - $ping > 150 ) {
		return 'offline';
	}
	if ( 'ausente' === $manual || get_user_meta( $user_id, 'lk_idle', true ) ) {
		return 'ausente';
	}
	return 'online';
}

function lk_presence_since( $user_id, $status ) {
	if ( 'offline' !== $status ) {
		return '';
	}
	$ping = (int) get_user_meta( $user_id, 'lk_ping', true );
	return $ping ? 'visto ' . lk_ago( wp_date( 'Y-m-d H:i:s', $ping ) ) : 'ainda não entrou';
}

/**
 * Mensagens de outras pessoas depois da última vez que eu abri o canal.
 */
function lk_team_unread( $channel, $user_id ) {
	global $wpdb;
	$seen = (string) get_user_meta( $user_id, 'lk_seen_' . $channel, true );
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . lk_table( 'team_chat' ) . ' WHERE channel = %s AND user_id <> %d AND created_at > %s', $channel, $user_id, $seen ? $seen : '1970-01-01 00:00:00' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function lk_api_team_hub( WP_REST_Request $r ) {
	$me = get_current_user_id();
	update_user_meta( $me, 'lk_ping', time() );
	update_user_meta( $me, 'lk_idle', $r['idle'] ? 1 : 0 );
	$status = sanitize_key( (string) $r['status'] );
	if ( in_array( $status, array( 'online', 'ausente', 'offline' ), true ) ) {
		update_user_meta( $me, 'lk_presence', 'online' === $status ? '' : $status );
	}
	if ( null !== $r['note'] ) {
		update_user_meta( $me, 'lk_status_note', mb_substr( sanitize_text_field( (string) $r['note'] ), 0, 60 ) );
	}
	$labels = lk_presence_labels();
	$team   = array();
	$total  = 0;
	foreach ( lk_team_users() as $u ) {
		if ( (int) $u->ID === $me ) {
			continue;
		}
		$st     = lk_presence( $u->ID );
		$note   = 'offline' === $st ? '' : (string) get_user_meta( $u->ID, 'lk_status_note', true );
		$ch     = lk_dm_channel( $me, $u->ID );
		$unread = lk_team_unread( $ch, $me );
		$total += $unread;
		$team[] = array(
			'id'     => (int) $u->ID,
			'name'   => $u->display_name,
			'ini'    => lk_user_badge( $u ),
			'role'   => lk_user_role_label( $u->ID ),
			'status' => $st,
			'label'  => $labels[ $st ] . ( lk_presence_since( $u->ID, $st ) ? ' · ' . lk_presence_since( $u->ID, $st ) : '' ) . ( $note ? ' · ' . $note : '' ),
			'ch'     => $ch,
			'unread' => $unread,
		);
	}
	// Online primeiro, depois ausente, depois offline; dentro de cada grupo, por nome.
	$order = array( 'online' => 0, 'ausente' => 1, 'offline' => 2 );
	usort(
		$team,
		function ( $a, $b ) use ( $order ) {
			return array( $order[ $a['status'] ], $a['name'] ) <=> array( $order[ $b['status'] ], $b['name'] );
		}
	);
	$geral   = lk_team_unread( 'geral', $me );
	$clients = lk_can( 'clientes' ) ? lk_chat_unread_count() : 0;
	$notes   = lk_notifications_unread();
	$manual  = (string) get_user_meta( $me, 'lk_presence', true );
	// Conversas com clientes (as mais recentes), para responder direto na janela.
	$convs = array();
	if ( lk_can( 'clientes' ) ) {
		foreach ( array_slice( lk_chat_inbox(), 0, 12 ) as $row ) {
			$c    = lk_get( 'clients', $row->client_id );
			$last = lk_get( 'messages', $row->last_id );
			if ( ! $c || ! $last ) {
				continue;
			}
			$convs[] = array(
				'id'     => (int) $c->id,
				'name'   => lk_client_label( $c ),
				'ini'    => lk_initials( lk_client_label( $c ) ),
				'last'   => ( $last->from_client ? '' : 'Você: ' ) . wp_trim_words( (string) $last->body, 9 ),
				'at'     => lk_ago( $last->created_at ),
				'unread' => (int) $row->unread,
			);
		}
	}
	return array(
		'voice'   => function_exists( 'lk_voice_members' ) ? lk_voice_members() : array(),
		'nudges'  => function_exists( 'lk_nudges_for' ) ? lk_nudges_for( $me ) : array(),
		'ring'    => lk_calls_ringing( $me ),
		'me'      => $manual ? $manual : 'online',
		'note'    => (string) get_user_meta( $me, 'lk_status_note', true ),
		'convs'   => $convs,
		'team'    => $team,
		'geral'   => $geral,
		'clients' => $clients,
		'notes'   => $notes,
		'total'   => $total + $geral + $clients + $notes,
	);
}

/* -----------------------------------------------------------------------
 * Bolinha de chat (todas as telas do painel)
 * -------------------------------------------------------------------- */

function lk_hub_html() {
	$me = wp_get_current_user();
	ob_start();
	?>
<div class="hub" data-hub data-me="<?php echo (int) $me->ID; ?>">
	<div class="nudge-box" data-nudge hidden role="alertdialog" aria-live="assertive" aria-label="Chamaram sua atenção">
		<span class="hub-av" data-nudge-av></span>
		<div class="nudge-t"><strong data-nudge-name></strong><small>chamou sua atenção!</small><p data-nudge-msg></p></div>
		<button type="button" class="btn btn--primary btn--sm" data-nudge-ok>Ok, vi!</button>
	</div>
	<div class="hub-ring" data-hub-ring hidden role="alertdialog" aria-live="assertive" aria-label="Ligação chegando">
		<span class="hub-av" data-hub-ring-av></span>
		<span class="hub-rt"><strong data-hub-ring-name></strong><small data-hub-ring-t>está te ligando…</small></span>
		<button type="button" class="hub-ring-no" data-hub-ring-no aria-label="Recusar">Recusar</button>
		<button type="button" class="hub-ring-yes" data-hub-ring-yes aria-label="Atender">Atender</button>
	</div>
	<div class="hub-rail" data-hub-rail aria-label="Equipe agora"></div>
	<button type="button" class="hub-fab" data-hub-fab aria-label="Abrir o chat da equipe" aria-expanded="false"><?php echo lk_icon( 'chat', 24 ); // phpcs:ignore ?><em class="hub-n" data-hub-n hidden></em></button>
	<section class="hub-win" data-hub-win hidden role="dialog" aria-label="Chat da equipe">
		<header class="hub-head">
			<button type="button" class="hub-ic" data-hub-back hidden aria-label="Voltar"><?php echo lk_icon( 'voltar', 18 ); // phpcs:ignore ?></button>
			<strong class="hub-title" data-hub-title>Equipe</strong>
			<button type="button" class="hub-ic hub-call" data-hub-call hidden aria-label="Ligar (chamada de vídeo)" title="Ligar (chamada de vídeo)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg></button>
			<label class="hub-me" title="O seu status para a equipe"><i class="dot" data-hub-mydot></i><select data-hub-status aria-label="Meu status"><option value="online">Online</option><option value="ausente">Ausente</option><option value="offline">Offline</option></select></label>
			<button type="button" class="hub-ic" data-hub-max aria-label="Tela cheia" title="Tela cheia"><svg data-hub-max-on width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg><svg data-hub-max-off hidden width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14h6v6M20 10h-6V4M14 10l7-7M3 21l7-7"/></svg></button>
			<button type="button" class="hub-ic" data-hub-close aria-label="Fechar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
		</header>
		<div class="hub-home" data-hub-home>
			<label class="hub-note-in"><span class="sr-only">Recado de status</span><input type="text" maxlength="60" data-hub-note placeholder="Recado para a equipe (ex.: em reunião até 15h)"></label>
			<div class="hub-voice" data-hub-voice>
				<span class="hub-voice-ic" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1v-6h3zM3 19a2 2 0 0 0 2 2h1v-6H3z"/></svg></span>
				<span class="hub-rt"><strong>Sala de voz</strong><small data-hub-voice-t>ninguém na sala</small></span>
				<span class="hub-voice-avs" data-hub-voice-avs></span>
				<a class="hub-voice-go" href="<?php echo esc_url( lk_panel_url( 'sala' ) ); ?>" data-hub-voice-go>Entrar</a>
			</div>
			<form class="hub-nudge" data-hub-nudge>
				<input type="text" maxlength="200" data-hub-nudge-msg placeholder="Aviso para todos (opcional)" aria-label="Aviso para todos">
				<button type="submit" class="hub-nudge-btn" title="Treme a tela de todos que estão online, com toque e o seu aviso">⚡ Chamar atenção</button>
			</form>
			<nav class="hub-tabs"><button type="button" class="is-on" data-hub-tab="conversas">Conversas</button><button type="button" data-hub-tab="avisos">Avisos <em data-hub-avisos-n hidden></em></button></nav>
			<div class="hub-pane" data-hub-pane="conversas">
				<button type="button" class="hub-row" data-hub-open="geral"><span class="hub-av hub-av--hash">#</span><span class="hub-rt"><strong>Geral</strong><small>Toda a equipe</small></span><em class="hub-badge" data-hub-geral hidden></em></button>
				<p class="hub-sec">Equipe <span class="hub-sec-n" data-hub-online></span></p>
				<div data-hub-team><p class="muted small hub-pad">Carregando…</p></div>
				<div data-hub-convs-wrap hidden>
					<p class="hub-sec">Clientes</p>
					<div data-hub-convs></div>
				</div>
				<a class="hub-more" href="<?php echo esc_url( lk_panel_url( 'chat' ) ); ?>">Canais por cliente e tela cheia →</a>
			</div>
			<div class="hub-pane" data-hub-pane="avisos" hidden>
				<a class="hub-row" data-hub-clientmsg href="<?php echo esc_url( lk_panel_url( 'mensagens' ) ); ?>" hidden><span class="hub-av"><?php echo lk_icon( 'chat', 16 ); // phpcs:ignore ?></span><span class="hub-rt"><strong>Mensagens de clientes</strong><small data-hub-clientmsg-t></small></span><em class="hub-badge" data-hub-clientmsg-n></em></a>
				<div data-hub-notes><p class="muted small hub-pad">Carregando…</p></div>
			</div>
		</div>
		<div class="hub-thread" data-hub-thread hidden>
			<div class="hub-list" data-hub-list></div>
			<form class="hub-form" data-hub-form>
				<div class="hub-rec" data-hub-rec hidden><i class="hub-rec-dot"></i><span data-hub-rec-t>0:00</span><span class="muted small">gravando…</span><button type="button" class="hub-ic" data-hub-rec-cancel aria-label="Descartar áudio"><?php echo lk_icon( 'lixo', 18 ); // phpcs:ignore ?></button></div>
				<div class="hub-emo-box" data-hub-emo-box hidden></div>
				<button type="button" class="hub-ic" data-hub-emo aria-label="Emojis" title="Emojis animados">😊</button>
				<textarea rows="1" data-hub-text placeholder="Mensagem…"></textarea>
				<button type="button" class="hub-ic hub-mic" data-hub-mic aria-label="Gravar áudio" title="Gravar áudio"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M19 10v1a7 7 0 0 1-14 0v-1M12 18v4"/></svg></button>
				<button type="submit" class="hub-send" aria-label="Enviar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg></button>
			</form>
		</div>
	</section>
</div>
	<?php
	return ob_get_clean();
}

/**
 * Sininho da equipe: notificações + mensagens de clientes não lidas.
 */
function lk_team_bell_html() {
	$n = lk_notifications_unread() + lk_chat_unread_count();
	return '<div class="nbell"><button type="button" class="bell" data-nbell title="Notificações">' . lk_icon( 'sino', 18 ) . '<em class="bell-n"' . ( $n ? '' : ' hidden' ) . '>' . (int) $n . '</em></button><div class="nbell-drop" data-nbell-drop hidden><div class="nbell-head"><strong>Notificações</strong><a href="' . esc_url( lk_panel_url( 'mensagens' ) ) . '">Mensagens de clientes</a></div><div class="nbell-list" data-nbell-list><p class="muted small">Carregando…</p></div></div></div>';
}
