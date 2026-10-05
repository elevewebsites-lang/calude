<?php
/**
 * Reuniões do CRM: registro de reuniões (cliente, data, hora, local/link, pauta e ata), lista na ficha do cliente,
 * na Agenda e no dashboard. Funciona sozinho; o Google Agenda continua para reuniões com Meet.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_meeting_kinds() {
	return array( 'online' => 'Online (link)', 'presencial' => 'Presencial', 'telefone' => 'Telefone / WhatsApp' );
}

function lk_meeting_status_labels() {
	return array( 'agendada' => 'Agendada', 'feita' => 'Feita', 'cancelada' => 'Cancelada' );
}

/** Reuniões de hoje em diante (agendadas), da mais próxima para a mais distante. */
function lk_meetings_upcoming( $limit = 8, $client_id = 0 ) {
	$where = "status = 'agendada' AND starts_at >= %s" . ( $client_id ? ' AND client_id = %d' : '' );
	$args  = $client_id ? array( gmdate( 'Y-m-d 00:00:00', strtotime( lk_today() ) ), $client_id ) : array( gmdate( 'Y-m-d 00:00:00', strtotime( lk_today() ) ) );
	return array_slice( lk_rows( 'meetings', $where, $args, 'starts_at ASC' ), 0, $limit );
}

function lk_meeting_when( $m ) {
	$ts  = strtotime( $m->starts_at );
	$day = gmdate( 'Y-m-d', $ts );
	$d   = lk_days_until( $day );
	$lab = 0 === $d ? 'Hoje' : ( 1 === $d ? 'Amanhã' : lk_date( $day, 'd/m' ) . ' · ' . lk_dow_short( $day ) );
	return $lab . ' · ' . gmdate( 'H:i', $ts );
}

function lk_meeting_data( $m ) {
	return array( 'id' => (int) $m->id, 'client_id' => (int) $m->client_id, 'title' => $m->title, 'date' => gmdate( 'Y-m-d', strtotime( $m->starts_at ) ), 'time' => gmdate( 'H:i', strtotime( $m->starts_at ) ), 'duration' => (int) $m->duration, 'kind' => $m->kind, 'place' => $m->place, 'guests' => $m->guests, 'notes' => (string) $m->notes, 'status' => $m->status );
}

/** Escolher o cliente numa lista com busca (logo, nome e e-mail), em vez de um campo escondido. */
function lk_client_picker( $selected = 0 ) {
	echo '<div class="cpick" data-cpick><input type="hidden" name="client_id" value="' . (int) $selected . '"><span class="cpick-label">Cliente</span>';
	echo '<div class="cpick-sel" data-cpick-sel hidden></div>';
	echo '<input type="search" class="cpick-q" placeholder="🔍 Buscar cliente pelo nome…" data-cpick-q autocomplete="off"><ul class="cpick-list" data-cpick-list>';
	echo '<li><button type="button" data-cid="0" data-cname="Reunião interna" data-cmail="1"><span class="avatar avatar--sm">·</span><span><b>Reunião interna</b><small>sem cliente</small></span></button></li>';
	foreach ( lk_clients() as $c ) {
		echo '<li><button type="button" data-cid="' . (int) $c->id . '" data-cname="' . esc_attr( lk_client_label( $c ) ) . '" data-cmail="' . ( is_email( $c->email ) ? 1 : 0 ) . '">' . lk_client_avatar_html( $c, 'avatar avatar--sm' ) . '<span><b>' . esc_html( lk_client_label( $c ) ) . '</b><small>' . esc_html( $c->email ? $c->email : 'sem e-mail cadastrado' ) . '</small></span></button></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</ul></div>';
}

function lk_meeting_row_html( $m, $show_client = true ) {
	$c    = $m->client_id ? lk_get( 'clients', $m->client_id ) : null;
	$link = $m->place && preg_match( '#^https?://#i', $m->place ) ? '<a class="btn btn--primary btn--sm" href="' . esc_url( $m->place ) . '" target="_blank" rel="noopener">Entrar</a>' : '';
	$data = lk_meeting_data( $m );
	$h    = '<li class="meet"><span class="meet-time">' . esc_html( lk_meeting_when( $m ) ) . '</span><span class="meet-main"><strong>' . esc_html( $m->title ) . '</strong><small>' . esc_html( ( $show_client && $c ? lk_client_label( $c ) . ' · ' : '' ) . ( lk_meeting_kinds()[ $m->kind ] ?? '' ) . ( $m->place && ! $link ? ' · ' . $m->place : '' ) ) . '</small></span><span class="meet-btns">' . $link;
	$h   .= '<button type="button" class="icon-btn" title="Editar / registrar ata" data-open="editar-reuniao-crm" data-reuniao="' . esc_attr( wp_json_encode( $data ) ) . '">' . lk_icon( 'editar', 15 ) . '</button></span></li>';
	return $h;
}

/** Lista curta para o dashboard: reuniões do CRM + do Google Agenda (se conectado), em ordem de horário. */
function lk_meetings_widget_html() {
	$items = array();
	foreach ( lk_meetings_upcoming( 8 ) as $m ) {
		$items[] = array( strtotime( $m->starts_at ), lk_meeting_row_html( $m ) );
	}
	if ( function_exists( 'lk_calendar_upcoming' ) && lk_google_connected() ) {
		$ev = lk_calendar_upcoming( 14, 8 );
		if ( ! is_wp_error( $ev ) ) {
			foreach ( $ev as $e ) {
				$day  = wp_date( 'Y-m-d', $e['start'] );
				$d    = lk_days_until( $day );
				$when = ( 0 === $d ? 'Hoje' : ( 1 === $d ? 'Amanhã' : lk_date( $day, 'd/m' ) . ' · ' . lk_dow_short( $day ) ) ) . ( $e['all_day'] ? '' : ' · ' . wp_date( 'H:i', $e['start'] ) );
				$btn  = $e['meet'] ? '<a class="btn btn--primary btn--sm" href="' . esc_url( $e['meet'] ) . '" target="_blank" rel="noopener">Meet</a>' : '';
				$items[] = array( $e['start'], '<li class="meet"><span class="meet-time">' . esc_html( $when ) . '</span><span class="meet-main"><strong>' . esc_html( $e['title'] ) . '</strong><small>Google Agenda</small></span><span class="meet-btns">' . $btn . '</span></li>' );
			}
		}
	}
	usort( $items, function ( $a, $b ) { return $a[0] <=> $b[0]; } );
	$items = array_slice( $items, 0, 6 );
	ob_start();
	?>
	<section class="card">
		<div class="card-head"><h3>📅 Próximas reuniões</h3><span class="row-btns"><button type="button" class="btn btn--ghost btn--sm" data-open="nova-reuniao-crm">+ Marcar reunião</button><a class="small" href="<?php echo esc_url( lk_panel_url( 'agenda' ) ); ?>">agenda</a></span></div>
		<?php if ( ! $items ) : ?><p class="muted small">Nenhuma reunião marcada. Use <strong>+ Reunião</strong> para registrar.</p><?php else : ?>
			<ul class="meetings"><?php foreach ( $items as $it ) { echo $it[1]; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php } ?></ul>
		<?php endif; ?>
	</section>
	<?php
	echo lk_meeting_modals_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	return ob_get_clean();
}

/** Cartão completo na Agenda (próximas e últimas reuniões registradas). */
function lk_meetings_card_html() {
	$up   = lk_meetings_upcoming( 30 );
	$past = array_slice( lk_rows( 'meetings', "(status <> 'agendada' OR starts_at < %s)", array( gmdate( 'Y-m-d 00:00:00', strtotime( lk_today() ) ) ), 'starts_at DESC' ), 0, 8 );
	ob_start();
	?>
	<section class="card">
		<div class="card-head"><h3>Reuniões registradas</h3><button type="button" class="btn btn--primary btn--sm" data-open="nova-reuniao-crm">+ Marcar reunião</button></div>
		<?php if ( ! $up ) : ?><p class="muted small">Nenhuma reunião agendada.</p><?php else : ?><ul class="meetings"><?php foreach ( $up as $m ) { echo lk_meeting_row_html( $m ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php } ?></ul><?php endif; ?>
		<?php if ( $past ) : ?><h4 class="muted small" style="margin:16px 0 6px">Anteriores</h4><ul class="meetings"><?php foreach ( $past as $m ) { echo lk_meeting_row_html( $m ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php } ?></ul><?php endif; ?>
	</section>
	<?php
	echo lk_meeting_modals_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	return ob_get_clean();
}

/** Reuniões na ficha do cliente. */
function lk_client_meetings_html( $client ) {
	$rows = lk_rows( 'meetings', 'client_id = %d', array( $client->id ), 'starts_at DESC' );
	ob_start();
	?>
	<section class="card" id="reunioes">
		<div class="card-head"><h3>Reuniões com este cliente</h3><button type="button" class="btn btn--primary btn--sm" data-open="nova-reuniao-crm" data-reuniao-client="<?php echo (int) $client->id; ?>">📅 Marcar reunião</button></div>
		<?php if ( ! $rows ) : ?><p class="muted small">Nenhuma reunião registrada com este cliente.</p><?php else : ?><ul class="meetings"><?php foreach ( array_slice( $rows, 0, 10 ) as $m ) { echo lk_meeting_row_html( $m, false ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php } ?></ul><?php endif; ?>
	</section>
	<?php
	echo lk_meeting_modals_html( $client->id ); // phpcs:ignore WordPress.Security.EscapeOutput
	return ob_get_clean();
}

/** Formulários (criar e editar). Impresso uma vez por página. */
function lk_meeting_modals_html( $client_id = 0 ) {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	ob_start();
	lk_modal_start( 'nova-reuniao-crm', 'Marcar reunião' );
	lk_meeting_form( $client_id );
	lk_modal_end();
	lk_modal_start( 'editar-reuniao-crm', 'Reunião' );
	lk_meeting_form( 0, true );
	lk_modal_end();
	?>
	<script>
	(function(){
	function sync(f){var h=f.querySelector('[name=client_id]');if(!h)return;var root=f.querySelector('[data-cpick]'),sel=root.querySelector('[data-cpick-sel]'),btn=root.querySelector('[data-cid="'+(h.value||0)+'"]');
		if(h.value&&h.value!=='0'&&btn){sel.hidden=false;sel.innerHTML='<span>✓ <b></b></span><button type="button" data-cchange>trocar</button>'+(btn.dataset.cmail==='0'?'<em>sem e-mail na ficha: não dá para avisar</em>':'');sel.querySelector('b').textContent=btn.dataset.cname;root.classList.add('is-picked');}
		else{sel.hidden=true;sel.innerHTML='';root.classList.remove('is-picked');if(h.value==='0'){h.value='0';}}}
	window.lkPickSync=sync;
	document.addEventListener('click',function(e){
		var pb=e.target.closest&&e.target.closest('[data-cpick] [data-cid]');
		if(pb){var root=pb.closest('[data-cpick]');root.querySelector('[name=client_id]').value=pb.dataset.cid;sync(root.closest('form'));return;}
		if(e.target.closest&&e.target.closest('[data-cchange]')){var r=e.target.closest('[data-cpick]');r.querySelector('[name=client_id]').value='';sync(r.closest('form'));return;}
		var b=e.target.closest&&e.target.closest('[data-reuniao],[data-reuniao-client],[data-reuniao-date]');if(!b)return;
		setTimeout(function(){
			var f;
			if(b.dataset.reuniao){var d=JSON.parse(b.dataset.reuniao);f=document.querySelector('#editar-reuniao-crm form');if(!f)return;
				['id','client_id','title','date','time','duration','kind','place','guests','notes','status'].forEach(function(n){var i=f.querySelector('[name='+n+']');if(i)i.value=d[n]==null?'':d[n];});sync(f);}
			else{f=document.querySelector('#nova-reuniao-crm form');if(!f)return;
				if(b.dataset.reuniaoClient){f.querySelector('[name=client_id]').value=b.dataset.reuniaoClient;}
				if(b.dataset.reuniaoDate){f.querySelector('[name=date]').value=b.dataset.reuniaoDate;}
				if(b.dataset.reuniaoTime){f.querySelector('[name=time]').value=b.dataset.reuniaoTime;}
				sync(f);}
		},30);
	});
	document.addEventListener('input',function(e){var q=e.target;if(!q.matches||!q.matches('[data-cpick-q]'))return;var t=q.value.toLowerCase();q.closest('[data-cpick]').querySelectorAll('[data-cpick-list] li').forEach(function(li){li.hidden=t&&li.textContent.toLowerCase().indexOf(t)<0;});});
	document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('#nova-reuniao-crm form,#editar-reuniao-crm form').forEach(sync);});
	})();
	</script>
	<?php
	return ob_get_clean();
}

function lk_meeting_form( $client_id = 0, $edit = false ) {
	lk_form( 'reuniao_save', 'stack' );
	echo '<input type="hidden" name="id" value="0">';
	lk_client_picker( $client_id );
	lk_input( 'title', 'Assunto', '', 'text', 'required placeholder="Ex.: Alinhamento do mês · Apresentação da proposta"' );
	echo '<div class="grid-3">';
	lk_input( 'date', 'Data', lk_today(), 'date', 'required' );
	lk_input( 'time', 'Hora', '10:00', 'time', 'required' );
	lk_input( 'duration', 'Duração (min)', 60, 'number', 'min="5" max="600"' );
	echo '</div>';
	lk_select( 'kind', 'Tipo', lk_meeting_kinds(), 'online' );
	lk_input( 'place', 'Link ou local', '', 'text', 'placeholder="https://meet.google.com/… ou endereço"' );
	lk_input( 'guests', 'Participantes', '', 'text', 'placeholder="Quem vai participar"' );
	lk_input( 'notes', 'Pauta / ata (uso interno: o cliente não vê)', '', 'textarea', 'rows="4"' );
	echo '<label class="check"><input type="checkbox" name="avisar" value="1" checked><span>Avisar o cliente por e-mail agora (com link e convite de calendário) e lembrar 10 minutos antes</span></label>';
	if ( $edit ) {
		lk_select( 'status', 'Situação', lk_meeting_status_labels(), 'agendada' );
	}
	echo '<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button>';
	if ( $edit ) {
		echo '<button type="submit" name="excluir" value="1" class="btn btn--danger" data-confirm="Excluir esta reunião?">Excluir</button>';
	}
	echo '<button type="submit" class="btn btn--primary">Salvar</button></div></form>';
}

function lk_do_reuniao_save() {
	if ( ! lk_is_team() ) {
		wp_die( 'Sem permissão.' );
	}
	$id = lk_in( 'id', 'int' );
	$m  = $id ? lk_get( 'meetings', $id ) : null;
	if ( $m && lk_in( 'excluir', 'bool' ) ) {
		lk_delete( 'meetings', $m->id );
		lk_back( 'Reunião excluída.' );
	}
	$title = lk_in( 'title' );
	$date  = lk_in( 'date', 'date' );
	$time  = preg_match( '/^\d{2}:\d{2}$/', (string) lk_in( 'time' ) ) ? lk_in( 'time' ) : '10:00';
	if ( ! $title || ! $date ) {
		lk_back( 'Informe o assunto e a data.', 'erro' );
	}
	$kinds = lk_meeting_kinds();
	$stat  = lk_meeting_status_labels();
	$data  = array(
		'client_id' => lk_in( 'client_id', 'int' ),
		'title'     => $title,
		'starts_at' => $date . ' ' . $time . ':00',
		'duration'  => max( 5, lk_in( 'duration', 'int' ) ? lk_in( 'duration', 'int' ) : 60 ),
		'kind'      => isset( $kinds[ lk_in( 'kind' ) ] ) ? lk_in( 'kind' ) : 'online',
		'place'     => lk_in( 'place' ),
		'guests'    => lk_in( 'guests' ),
		'notes'     => lk_in( 'notes', 'textarea' ),
	);
	$avisar = lk_in( 'avisar', 'bool' );
	if ( $m ) {
		$data['status'] = isset( $stat[ lk_in( 'status' ) ] ) ? lk_in( 'status' ) : $m->status;
		$moved          = $data['starts_at'] !== $m->starts_at || $data['place'] !== $m->place;
		if ( $moved ) {
			$data['reminded'] = 0;
		}
		lk_update( 'meetings', $m->id, $data );
		$m   = lk_get( 'meetings', $m->id );
		$msg = 'Reunião atualizada.';
		if ( $avisar && $m->client_id ) {
			$type = 'cancelada' === $m->status ? 'cancelada' : ( $moved ? 'remarcada' : '' );
			if ( $type ) {
				$msg .= lk_meeting_mail( $m, $type ) ? ' O cliente foi avisado por e-mail.' : ' (O cliente não tem e-mail válido na ficha.)';
			}
		}
		lk_back( $msg );
	}
	$data['created_by'] = get_current_user_id();
	$mid                = lk_insert( 'meetings', $data );
	$msg                = 'Reunião marcada. Ela aparece no dashboard, na Agenda e na área do cliente.';
	if ( $avisar && $data['client_id'] ) {
		$msg .= lk_meeting_mail( lk_get( 'meetings', $mid ), 'novo' ) ? ' O cliente recebeu o e-mail com o link.' : ' (O cliente não tem e-mail válido na ficha, então não foi avisado.)';
	}
	lk_back( $msg );
}

/* -----------------------------------------------------------------------
 * Avisos por e-mail ao cliente: ao marcar, ao remarcar/cancelar e 10 minutos antes
 * -------------------------------------------------------------------- */

/** "Agora" no horário do site, no mesmo formato de starts_at (como número). */
function lk_meeting_now_ts() {
	return time() + (int) round( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );
}

/** Convite de calendário (.ics) para o cliente adicionar ao Google Agenda/Outlook/Apple. */
function lk_meeting_ics( $m, $client ) {
	$start = strtotime( $m->starts_at ) - (int) round( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ); // → UTC
	$end   = $start + max( 5, (int) $m->duration ) * MINUTE_IN_SECONDS;
	$esc   = function ( $t ) { return str_replace( array( '\\', ';', ',', "\n" ), array( '\\\\', '\\;', '\\,', '\\n' ), (string) $t ); };
	$loc   = $m->place ? $m->place : '';
	$lines = array( 'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//LDK CRM//Reunioes//PT', 'METHOD:PUBLISH', 'BEGIN:VEVENT', 'UID:lk-reuniao-' . (int) $m->id . '@' . wp_parse_url( home_url(), PHP_URL_HOST ), 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ), 'DTSTART:' . gmdate( 'Ymd\THis\Z', $start ), 'DTEND:' . gmdate( 'Ymd\THis\Z', $end ), 'SUMMARY:' . $esc( $m->title . ' · ' . lk_setting( 'empresa' ) ), 'LOCATION:' . $esc( $loc ), 'DESCRIPTION:' . $esc( 'Reunião com a ' . lk_setting( 'empresa' ) . ( $loc ? '. Link/local: ' . $loc : '' ) ), 'BEGIN:VALARM', 'TRIGGER:-PT10M', 'ACTION:DISPLAY', 'DESCRIPTION:Reunião em 10 minutos', 'END:VALARM', 'END:VEVENT', 'END:VCALENDAR' );
	return implode( "\r\n", $lines ) . "\r\n";
}

/**
 * Envia o e-mail da reunião ao cliente. $type: novo | remarcada | cancelada | lembrete.
 * Devolve true se havia e-mail válido.
 */
function lk_meeting_mail( $m, $type ) {
	$client = $m && $m->client_id ? lk_get( 'clients', $m->client_id ) : null;
	if ( ! $client || ! is_email( $client->email ) ) {
		return false;
	}
	$ts    = strtotime( $m->starts_at );
	$day   = gmdate( 'Y-m-d', $ts );
	$when  = ucfirst( lk_date_long( $day ) ) . ' às ' . gmdate( 'H:i', $ts );
	$kinds = lk_meeting_kinds();
	$link  = $m->place && preg_match( '#^https?://#i', $m->place ) ? $m->place : '';
	$rows  = array( array( 'Assunto', esc_html( $m->title ) ), array( 'Quando', esc_html( $when ) ), array( 'Duração', (int) $m->duration . ' min' ), array( 'Tipo', esc_html( $kinds[ $m->kind ] ?? '' ) ) );
	if ( $m->place ) {
		$rows[] = array( $link ? 'Link' : 'Local', $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $link ) . '</a>' : esc_html( $m->place ) );
	}
	if ( $m->guests ) {
		$rows[] = array( 'Participantes', esc_html( $m->guests ) );
	}
	$first = $client->name ? strtok( $client->name, ' ' ) : lk_client_label( $client );
	$cta   = $link ? 'Entrar na reunião' : 'Ver na minha área';
	$url   = $link ? $link : lk_client_link();
	$sub   = array(
		'novo'      => array( 'Reunião marcada · ' . $when, 'Reunião marcada ✓', 'Marcamos uma reunião com você. Já deixamos o convite em anexo: abra o arquivo para adicionar ao seu calendário.' ),
		'remarcada' => array( 'Reunião remarcada · ' . $when, 'Reunião remarcada', 'Atualizamos o horário da nossa reunião. Veja os novos dados abaixo.' ),
		'cancelada' => array( 'Reunião cancelada · ' . $m->title, 'Reunião cancelada', 'A reunião abaixo foi cancelada. Entraremos em contato para combinar um novo horário.' ),
		'lembrete'  => array( 'Sua reunião começa em 10 minutos', 'Daqui a pouco ⏰', 'Lembrete: a nossa reunião começa em 10 minutos.' ),
	);
	$s    = $sub[ $type ] ?? $sub['novo'];
	$file = '';
	if ( in_array( $type, array( 'novo', 'remarcada' ), true ) ) {
		$up   = wp_upload_dir();
		$file = trailingslashit( $up['basedir'] ) . 'convite-reuniao-' . (int) $m->id . '-' . wp_generate_password( 6, false ) . '.ics';
		file_put_contents( $file, lk_meeting_ics( $m, $client ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$attach = function ( $args ) use ( $file ) {
			$args['attachments'] = array_merge( (array) ( $args['attachments'] ?? array() ), array( $file ) );
			return $args;
		};
		add_filter( 'wp_mail', $attach );
	}
	lk_mail( $client->email, $s[0] . ' · ' . lk_setting( 'empresa' ), $s[1], '<p>Olá, ' . esc_html( $first ) . '! ' . esc_html( $s[2] ) . '</p>', $rows, 'cancelada' === $type ? '' : $cta, 'cancelada' === $type ? '' : $url );
	if ( $file ) {
		remove_filter( 'wp_mail', $attach );
		wp_delete_file( $file );
	}
	if ( 'lembrete' !== $type ) {
		lk_update( 'meetings', $m->id, array( 'notified' => 1 ) );
	}
	return true;
}

/** A cada 5 minutos: lembrete 10 minutos antes (no máximo uma vez por reunião). */
add_action( 'lk_publish_tick', 'lk_meetings_remind' );
function lk_meetings_remind() {
	$now = lk_meeting_now_ts();
	$to  = gmdate( 'Y-m-d H:i:s', $now + 10 * MINUTE_IN_SECONDS );
	$fr  = gmdate( 'Y-m-d H:i:s', $now );
	foreach ( lk_rows( 'meetings', "status = 'agendada' AND reminded = 0 AND client_id > 0 AND starts_at > %s AND starts_at <= %s", array( $fr, $to ) ) as $m ) {
		lk_update( 'meetings', $m->id, array( 'reminded' => 1 ) );
		lk_meeting_mail( $m, 'lembrete' );
	}
}

/** Área do cliente: próximas reuniões (sem a pauta/ata, que é interna). */
function lk_client_meetings_area_html( $client ) {
	$rows = lk_meetings_upcoming( 5, $client->id );
	if ( ! $rows ) {
		return '';
	}
	$soon = '';
	$h    = '<section class="card" id="reunioes"><div class="card-head"><h3>📅 Suas próximas reuniões</h3></div><ul class="meetings">';
	foreach ( $rows as $m ) {
		$link = $m->place && preg_match( '#^https?://#i', $m->place ) ? '<a class="btn btn--primary btn--sm" href="' . esc_url( $m->place ) . '" target="_blank" rel="noopener">Entrar</a>' : '';
		$h   .= '<li class="meet"><span class="meet-time">' . esc_html( lk_meeting_when( $m ) ) . '</span><span class="meet-main"><strong>' . esc_html( $m->title ) . '</strong><small>' . esc_html( ( lk_meeting_kinds()[ $m->kind ] ?? '' ) . ( $m->place && ! $link ? ' · ' . $m->place : '' ) . ' · ' . (int) $m->duration . ' min' ) . '</small></span><span class="meet-btns">' . $link . '</span></li>';
		if ( ! $soon && strtotime( $m->starts_at ) - lk_meeting_now_ts() < DAY_IN_SECONDS ) {
			$soon = '<a class="plan-cta" href="#reunioes"><span><strong>📅 Reunião ' . esc_html( 0 === lk_days_until( gmdate( 'Y-m-d', strtotime( $m->starts_at ) ) ) ? 'hoje' : 'amanhã' ) . ' às ' . esc_html( gmdate( 'H:i', strtotime( $m->starts_at ) ) ) . '</strong><small>' . esc_html( $m->title ) . '</small></span><em>Ver →</em></a>';
		}
	}
	return $soon . $h . '</ul></section>';
}
