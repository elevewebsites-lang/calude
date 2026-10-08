<?php
/**
 * Agenda do Google (reuniões com Meet): funções vindas do Eleve CRM.
 * A conexão OAuth é a do drive.php (escopo de calendário incluído).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Próximas reuniões (cache de 5 minutos).
 */
function lk_calendar_upcoming( $days = 30, $max = 25 ) {
	if ( ! lk_google_connected() ) {
		return array();
	}
	$key    = 'lk_cal_' . (int) get_option( 'lk_cal_v', 1 ) . '_' . $days . '_' . $max;
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return $cached;
	}
	$url = add_query_arg(
		array(
			'timeMin'      => rawurlencode( gmdate( 'c' ) ),
			'timeMax'      => rawurlencode( gmdate( 'c', time() + $days * DAY_IN_SECONDS ) ),
			'singleEvents' => 'true',
			'orderBy'      => 'startTime',
			'maxResults'   => (int) $max,
		),
		'https://www.googleapis.com/calendar/v3/calendars/primary/events'
	);
	$res = lk_google_api( 'GET', $url );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$out = array();
	foreach ( (array) ( isset( $res['items'] ) ? $res['items'] : array() ) as $ev ) {
		if ( ! empty( $ev['extendedProperties']['private']['lk_call'] ) ) {
			continue; // Chamada rápida do chat da equipe: não é reunião.
		}
		$all   = empty( $ev['start']['dateTime'] );
		$start = $all ? $ev['start']['date'] : $ev['start']['dateTime'];
		$end   = $all ? ( isset( $ev['end']['date'] ) ? $ev['end']['date'] : $start ) : $ev['end']['dateTime'];
		$out[] = array(
			'id'          => isset( $ev['id'] ) ? $ev['id'] : '',
			'title'       => isset( $ev['summary'] ) ? $ev['summary'] : '(sem título)',
			'start'       => strtotime( $start ),
			'end'         => strtotime( $end ),
			'all_day'     => $all,
			'meet'        => isset( $ev['hangoutLink'] ) ? $ev['hangoutLink'] : '',
			'link'        => isset( $ev['htmlLink'] ) ? $ev['htmlLink'] : '',
			'description' => isset( $ev['description'] ) ? $ev['description'] : '',
			'organizer'   => ! empty( $ev['organizer']['self'] ),
			'attendees'   => array_values( array_filter( wp_list_pluck( isset( $ev['attendees'] ) ? $ev['attendees'] : array(), 'email' ) ) ),
		);
	}
	set_transient( $key, $out, 5 * MINUTE_IN_SECONDS );
	return $out;
}

function lk_do_meeting_create() {
	lk_require( 'projetos' );
	$date  = lk_in( 'date', 'date' );
	$time  = preg_match( '/^\d{2}:\d{2}$/', lk_in( 'time' ) ) ? lk_in( 'time' ) : '';
	$title = lk_in( 'title' );
	if ( ! $date || ! $time || ! $title ) {
		lk_back( 'Preencha o título, a data e o horário.', 'erro' );
	}
	$project = lk_in( 'project_id', 'int' ) ? lk_get( 'projects', lk_in( 'project_id', 'int' ) ) : null;
	$client  = $project ? lk_get( 'clients', $project->client_id ) : ( lk_in( 'client_id', 'int' ) ? lk_get( 'clients', lk_in( 'client_id', 'int' ) ) : null );
	$emails  = lk_parse_emails( lk_in( 'guests', 'textarea' ) );
	if ( $client && is_email( $client->email ) && lk_in( 'invite', 'bool' ) ) {
		array_unshift( $emails, $client->email );
	}
	$emails = array_values( array_unique( $emails ) );
	$res    = lk_calendar_create( $title, $date, $time, lk_in( 'duration', 'int' ), $emails, $project, lk_in( 'description', 'textarea' ), lk_in( 'meet', 'bool' ) );
	if ( is_wp_error( $res ) ) {
		lk_back( $res->get_error_message(), 'erro' );
	}
	// Guarda o convite para o botão "Copiar convite" aparecer na volta.
	set_transient(
		'lk_last_meeting_' . get_current_user_id(),
		array( 'title' => $title, 'start' => strtotime( $date . ' ' . $time . ' ' . wp_timezone_string() ), 'duration' => max( 15, lk_in( 'duration', 'int' ) ), 'meet' => isset( $res['hangoutLink'] ) ? $res['hangoutLink'] : '' ),
		10 * MINUTE_IN_SECONDS
	);
	lk_back( 'Reunião marcada' . ( $emails ? ' e convite enviado para ' . implode( ', ', $emails ) : '' ) . '.' );
}

/**
 * E-mails separados por vírgula, ponto e vírgula, espaço ou linha.
 */
function lk_parse_emails( $text ) {
	$out = array();
	foreach ( preg_split( '/[\s,;]+/', (string) $text ) as $e ) {
		$e = sanitize_email( $e );
		if ( $e && is_email( $e ) ) {
			$out[] = strtolower( $e );
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Texto pronto do convite (para colar no grupo do WhatsApp).
 */
function lk_meeting_invite_text( $title, $start, $duration, $meet ) {
	$dur  = $duration >= 60 ? ( 60 === (int) $duration ? '1 hora' : floor( $duration / 60 ) . 'h' . str_pad( $duration % 60, 2, '0', STR_PAD_LEFT ) ) : (int) $duration . ' min';
	$text = "📅 *" . $title . "*\n";
	$text .= '🗓 ' . ucfirst( wp_date( 'l, d \\d\\e F', $start ) ) . ' às ' . wp_date( 'H:i', $start ) . ' (' . $dur . ")\n";
	if ( $meet ) {
		$text .= '🔗 Google Meet: ' . $meet . "\n";
	}
	return $text . "\nQualquer imprevisto, é só avisar por aqui.";
}

/**
 * Editar reunião: assunto, data, horário, duração e convidar mais pessoas.
 */
function lk_do_meeting_update() {
	lk_require( 'projetos' );
	$id    = sanitize_text_field( lk_in( 'event_id' ) );
	$title = lk_in( 'title' );
	$date  = lk_in( 'date', 'date' );
	$time  = preg_match( '/^\d{2}:\d{2}$/', lk_in( 'time' ) ) ? lk_in( 'time' ) : '';
	if ( ! $id || ! $title || ! $date || ! $time ) {
		lk_back( 'Preencha o assunto, a data e o horário.', 'erro' );
	}
	$ev = lk_calendar_get( $id );
	if ( is_wp_error( $ev ) ) {
		lk_back( $ev->get_error_message(), 'erro' );
	}
	$tz    = wp_timezone();
	$start = new DateTime( $date . ' ' . $time, $tz );
	$end   = clone $start;
	$end->modify( '+' . max( 15, lk_in( 'duration', 'int' ) ) . ' minutes' );
	$attendees = isset( $ev['attendees'] ) ? (array) $ev['attendees'] : array();
	$have      = array_map( 'strtolower', wp_list_pluck( $attendees, 'email' ) );
	foreach ( lk_parse_emails( lk_in( 'guests', 'textarea' ) ) as $email ) {
		if ( ! in_array( $email, $have, true ) ) {
			$attendees[] = array( 'email' => $email );
		}
	}
	$body = array(
		'summary'   => $title,
		'start'     => array( 'dateTime' => $start->format( 'c' ), 'timeZone' => wp_timezone_string() ),
		'end'       => array( 'dateTime' => $end->format( 'c' ), 'timeZone' => wp_timezone_string() ),
		'attendees' => array_values( $attendees ),
	);
	if ( null !== lk_in( 'description', 'textarea' ) ) {
		$body['description'] = lk_in( 'description', 'textarea' );
	}
	$res = lk_google_api( 'PATCH', 'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode( $id ) . '?sendUpdates=all', $body );
	if ( is_wp_error( $res ) ) {
		lk_back( $res->get_error_message(), 'erro' );
	}
	update_option( 'lk_cal_v', (int) get_option( 'lk_cal_v', 1 ) + 1, false );
	lk_back( 'Reunião atualizada. Os convidados foram avisados pelo Google.' );
}

/**
 * Desmarcar reunião (o Google avisa os convidados).
 */
function lk_do_meeting_cancel() {
	lk_require( 'projetos' );
	$id = sanitize_text_field( lk_in( 'event_id' ) );
	if ( ! $id ) {
		lk_back( 'Reunião não encontrada.', 'erro' );
	}
	$res = lk_calendar_delete( $id );
	if ( is_wp_error( $res ) ) {
		lk_back( $res->get_error_message(), 'erro' );
	}
	lk_back( 'Reunião desmarcada. Os convidados receberam o aviso de cancelamento.' );
}

/**
 * Cria o evento no Google Agenda. Usado pelo painel e pelo assistente do WhatsApp.
 */
function lk_calendar_create( $title, $date, $time, $duration = 60, $invite_email = '', $project = null, $description = '', $meet = true ) {
	if ( ! lk_clean_date( $date ) || ! preg_match( '/^\d{1,2}:\d{2}$/', $time ) ) {
		return new WP_Error( 'lk_cal', 'Data ou horário inválidos.' );
	}
	$tz    = wp_timezone();
	$start = new DateTime( $date . ' ' . $time, $tz );
	$end   = clone $start;
	$end->modify( '+' . max( 15, (int) $duration ) . ' minutes' );
	$body = array(
		'summary'     => $title,
		'description' => $description . ( $project ? "\n\nProjeto: " . $project->title . ' · ' . lk_panel_url( 'projeto', $project->id ) : '' ),
		'start'       => array( 'dateTime' => $start->format( 'c' ), 'timeZone' => wp_timezone_string() ),
		'end'         => array( 'dateTime' => $end->format( 'c' ), 'timeZone' => wp_timezone_string() ),
	);
	$invites = array_filter( is_array( $invite_email ) ? $invite_email : array( $invite_email ), 'is_email' );
	if ( $invites ) {
		$body['attendees'] = array_map( function ( $e ) { return array( 'email' => $e ); }, array_values( $invites ) );
	}
	$query = '?sendUpdates=all';
	if ( $meet ) {
		$body['conferenceData'] = array( 'createRequest' => array( 'requestId' => wp_generate_password( 16, false ), 'conferenceSolutionKey' => array( 'type' => 'hangoutsMeet' ) ) );
		$query                 .= '&conferenceDataVersion=1';
	}
	$res = lk_google_api( 'POST', 'https://www.googleapis.com/calendar/v3/calendars/primary/events' . $query, $body );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	// Nova versão da chave = cache das próximas reuniões descartado.
	update_option( 'lk_cal_v', (int) get_option( 'lk_cal_v', 1 ) + 1, false );
	if ( $project ) {
		lk_log( $project->id, 'Reunião marcada: ' . $title . ' · ' . lk_date( $date ) . ' às ' . $time . ( ! empty( $res['hangoutLink'] ) ? ' · ' . $res['hangoutLink'] : '' ), true );
	}
	return $res;
}

/**
 * Reuniões de um período (datas AAAA-MM-DD, no fuso do painel), com o ID de cada uma.
 */
function lk_calendar_range( $from, $to, $max = 50 ) {
	if ( ! lk_google_connected() ) {
		return new WP_Error( 'lk_cal', 'O Google Agenda não está conectado (Configurações → Conta Google).' );
	}
	$tz    = wp_timezone();
	$start = new DateTime( $from . ' 00:00:00', $tz );
	$end   = new DateTime( $to . ' 23:59:59', $tz );
	$url   = add_query_arg(
		array(
			'timeMin'      => rawurlencode( $start->format( 'c' ) ),
			'timeMax'      => rawurlencode( $end->format( 'c' ) ),
			'singleEvents' => 'true',
			'orderBy'      => 'startTime',
			'maxResults'   => (int) $max,
		),
		'https://www.googleapis.com/calendar/v3/calendars/primary/events'
	);
	$res = lk_google_api( 'GET', $url );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$out = array();
	foreach ( (array) ( isset( $res['items'] ) ? $res['items'] : array() ) as $ev ) {
		if ( ! empty( $ev['extendedProperties']['private']['lk_call'] ) ) {
			continue;
		}
		$all   = empty( $ev['start']['dateTime'] );
		$out[] = array(
			'id'        => $ev['id'],
			'titulo'    => isset( $ev['summary'] ) ? $ev['summary'] : '(sem título)',
			'inicio'    => $all ? $ev['start']['date'] . ' (dia todo)' : wp_date( 'Y-m-d H:i', strtotime( $ev['start']['dateTime'] ) ),
			'dia_todo'  => $all,
			'convidados' => array_values( array_filter( wp_list_pluck( isset( $ev['attendees'] ) ? $ev['attendees'] : array(), 'email' ) ) ),
		);
	}
	return $out;
}

function lk_calendar_get( $id ) {
	return lk_google_api( 'GET', 'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode( $id ) );
}

/**
 * Desmarca uma reunião (os convidados recebem o aviso de cancelamento do Google).
 */
function lk_calendar_delete( $id ) {
	$res = lk_google_api( 'DELETE', 'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode( $id ) . '?sendUpdates=all' );
	if ( ! is_wp_error( $res ) ) {
		update_option( 'lk_cal_v', (int) get_option( 'lk_cal_v', 1 ) + 1, false );
	}
	return $res;
}

/**
 * Remarca uma reunião para outra data/hora, mantendo a duração (os convidados são avisados).
 */
function lk_calendar_move( $id, $date, $time ) {
	$ev = lk_calendar_get( $id );
	if ( is_wp_error( $ev ) ) {
		return $ev;
	}
	if ( empty( $ev['start']['dateTime'] ) || ! lk_clean_date( $date ) || ! preg_match( '/^\d{1,2}:\d{2}$/', $time ) ) {
		return new WP_Error( 'lk_cal', 'Data ou horário inválidos para remarcar.' );
	}
	$dur   = max( 15, (int) ( ( strtotime( $ev['end']['dateTime'] ) - strtotime( $ev['start']['dateTime'] ) ) / 60 ) );
	$tz    = wp_timezone();
	$start = new DateTime( $date . ' ' . $time, $tz );
	$end   = clone $start;
	$end->modify( '+' . $dur . ' minutes' );
	$res = lk_google_api(
		'PATCH',
		'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode( $id ) . '?sendUpdates=all',
		array(
			'start' => array( 'dateTime' => $start->format( 'c' ), 'timeZone' => wp_timezone_string() ),
			'end'   => array( 'dateTime' => $end->format( 'c' ), 'timeZone' => wp_timezone_string() ),
		)
	);
	if ( ! is_wp_error( $res ) ) {
		update_option( 'lk_cal_v', (int) get_option( 'lk_cal_v', 1 ) + 1, false );
	}
	return $res;
}

/**
 * Janela "Marcar reunião".
 */
function lk_meeting_modal( $project = null ) {
	lk_modal_start( 'nova-reuniao', 'Marcar reunião' );
	if ( ! lk_google_connected() ) {
		echo '<p class="muted">Conecte a sua conta Google em Configurações para marcar reuniões com Google Meet direto daqui.</p>';
		if ( lk_is_admin() ) {
			echo '<a class="btn btn--primary" href="' . esc_url( lk_panel_url( 'config' ) . '#google' ) . '">Conectar Google</a>';
		}
		lk_modal_end();
		return;
	}
	lk_form( 'meeting_create', 'stack' );
	lk_input( 'title', 'Assunto', $project ? 'Reunião · ' . $project->title : '', 'text', 'required' );
	if ( $project ) {
		echo '<input type="hidden" name="project_id" value="' . (int) $project->id . '">';
	} else {
		lk_select( 'project_id', 'Projeto (opcional)', lk_project_options( 'Nenhum' ) );
	}
	echo '<div class="grid-3">';
	lk_input( 'date', 'Data', gmdate( 'Y-m-d', strtotime( lk_today() . ' +1 day' ) ), 'date', 'required' );
	lk_input( 'time', 'Horário', '10:00', 'time', 'required' );
	lk_select( 'duration', 'Duração', array( 30 => '30 min', 45 => '45 min', 60 => '1 hora', 90 => '1h30' ), 60 );
	echo '</div>';
	lk_input( 'description', 'Pauta (opcional)', '', 'textarea', 'rows="3"' );
	lk_input( 'guests', 'Convidar mais pessoas (opcional)', '', 'textarea', 'rows="2" placeholder="E-mails separados por vírgula: socio@empresa.com, designer@…"' );
	echo '<div class="checks">';
	lk_check( 'meet', 'Criar link do Google Meet', true );
	lk_check( 'invite', 'Enviar convite para o cliente', true );
	echo '</div><div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Marcar</button></div></form>';
	lk_modal_end();

	lk_modal_start( 'editar-reuniao', 'Editar reunião' );
	lk_form( 'meeting_update', 'stack' );
	echo '<input type="hidden" name="event_id" value="">';
	lk_input( 'title', 'Assunto', '', 'text', 'required' );
	echo '<div class="grid-3">';
	lk_input( 'date', 'Data', '', 'date', 'required' );
	lk_input( 'time', 'Horário', '', 'time', 'required' );
	lk_select( 'duration', 'Duração', array( 15 => '15 min', 30 => '30 min', 45 => '45 min', 60 => '1 hora', 90 => '1h30', 120 => '2 horas' ), 60 );
	echo '</div>';
	echo '<p class="muted small" data-meeting-guests></p>';
	lk_input( 'guests', 'Convidar mais pessoas', '', 'textarea', 'rows="2" placeholder="E-mails separados por vírgula"' );
	echo '<p class="muted small">Ao salvar, o Google avisa os convidados da mudança.</p>';
	echo '<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar alterações</button></div></form>';
	lk_modal_end();
}

/**
 * Card "Reunião marcada" com o convite pronto para copiar (aparece depois de marcar).
 */
function lk_last_meeting_card() {
	$m = get_transient( 'lk_last_meeting_' . get_current_user_id() );
	if ( ! $m ) {
		return;
	}
	delete_transient( 'lk_last_meeting_' . get_current_user_id() );
	$text = lk_meeting_invite_text( $m['title'], $m['start'], $m['duration'], $m['meet'] );
	echo '<section class="card card--accent"><div class="card-head"><h3>Reunião marcada: ' . esc_html( $m['title'] ) . '</h3></div>';
	echo '<pre class="invite-text">' . esc_html( $text ) . '</pre>';
	echo '<div class="copy-row"><button type="button" class="btn btn--primary" data-copy="' . esc_attr( $text ) . '">' . lk_icon( 'copiar', 16 ) . '<span>Copiar convite para o grupo</span></button>';
	if ( $m['meet'] ) {
		echo '<button type="button" class="btn btn--ghost" data-copy="' . esc_attr( $m['meet'] ) . '">' . lk_icon( 'link', 16 ) . '<span>Copiar só o link do Meet</span></button>';
	}
	echo '</div></section>';
}

/**
 * Lista de reuniões agrupada por dia.
 */
function lk_meetings_html( $events ) {
	if ( is_wp_error( $events ) ) {
		return '<p class="flash flash--erro">' . esc_html( $events->get_error_message() ) . '</p>';
	}
	if ( ! $events ) {
		return '<p class="muted small">Nenhuma reunião nos próximos dias.</p>';
	}
	$html = '<ul class="meetings">';
	$last = '';
	foreach ( $events as $ev ) {
		$day = wp_date( 'Y-m-d', $ev['start'] );
		if ( $day !== $last ) {
			$diff  = lk_days_until( $day );
			$label = 0 === $diff ? 'Hoje' : ( 1 === $diff ? 'Amanhã' : ucfirst( lk_date_long( $day ) ) );
			$html .= '<li class="meet-day">' . esc_html( $label ) . '</li>';
			$last  = $day;
		}
		$day_l = ucfirst( lk_date_long( $day ) );
		$info  = array(
			'title'  => $ev['title'],
			'when'   => $ev['all_day'] ? $day_l . ' · dia todo' : $day_l . ' · ' . wp_date( 'H:i', $ev['start'] ) . ' às ' . wp_date( 'H:i', $ev['end'] ) . ' (' . max( 15, (int) round( ( $ev['end'] - $ev['start'] ) / 60 ) ) . ' min)',
			'client' => '',
			'kind'   => 'Google Agenda',
			'link'   => $ev['meet'] ? $ev['meet'] : ( $ev['link'] ? $ev['link'] : '' ),
			'guests' => $ev['attendees'],
		);
		$html .= '<li class="meet is-click" tabindex="0" role="button" aria-label="Abrir a reunião ' . esc_attr( $ev['title'] ) . '" data-meet-info="' . esc_attr( wp_json_encode( $info ) ) . '"><span class="meet-time">' . ( $ev['all_day'] ? 'Dia todo' : esc_html( wp_date( 'H:i', $ev['start'] ) ) ) . '</span><span class="meet-main"><strong>' . esc_html( $ev['title'] ) . '</strong>' . ( $ev['attendees'] ? '<small>' . esc_html( implode( ', ', array_slice( $ev['attendees'], 0, 3 ) ) . ( count( $ev['attendees'] ) > 3 ? ' +' . ( count( $ev['attendees'] ) - 3 ) : '' ) ) . '</small>' : '' ) . '</span><span class="meet-go" aria-hidden="true">›</span><template data-meet-actions>';
		if ( ! $ev['all_day'] && ! empty( $ev['id'] ) && lk_can( 'projetos' ) ) {
			$dur    = max( 15, (int) round( ( $ev['end'] - $ev['start'] ) / 60 ) );
			$invite = lk_meeting_invite_text( $ev['title'], $ev['start'], $dur, $ev['meet'] );
			$data   = array( 'id' => $ev['id'], 'title' => $ev['title'], 'date' => wp_date( 'Y-m-d', $ev['start'] ), 'time' => wp_date( 'H:i', $ev['start'] ), 'duration' => $dur, 'guests' => $ev['attendees'] );
			$html  .= '<button type="button" class="btn btn--ghost btn--sm" data-copy="' . esc_attr( $invite ) . '">' . lk_icon( 'copiar', 14 ) . '<span>Copiar convite</span></button>';
			$html  .= '<button type="button" class="btn btn--ghost btn--sm" data-open="editar-reuniao" data-meeting="' . esc_attr( wp_json_encode( $data ) ) . '">' . lk_icon( 'relogio', 14 ) . '<span>Remarcar / editar</span></button>';
			if ( $ev['organizer'] ) {
				ob_start();
				lk_form( 'meeting_cancel', 'inline-form' );
				echo '<input type="hidden" name="event_id" value="' . esc_attr( $ev['id'] ) . '"><button type="submit" class="btn btn--ghost btn--sm btn--danger-text" data-confirm="Desmarcar &quot;' . esc_attr( $ev['title'] ) . '&quot;? Os convidados recebem o aviso de cancelamento.">Excluir</button></form>';
				$html .= ob_get_clean();
			}
		}
		$html .= '</template></li>';
	}
	return $html . '</ul>';
}
