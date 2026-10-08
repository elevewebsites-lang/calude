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
	$c     = $m->client_id ? lk_get( 'clients', $m->client_id ) : null;
	$link  = $m->place && preg_match( '#^https?://#i', $m->place ) ? $m->place : ( $m->meet_link ? $m->meet_link : '' );
	$data  = lk_meeting_data( $m );
	$team  = array();
	foreach ( array_filter( array_map( 'absint', explode( ',', (string) $m->team_ids ) ) ) as $uid ) {
		$u = get_userdata( $uid );
		if ( $u ) {
			$team[] = $u->display_name;
		}
	}
	$ts   = strtotime( $m->starts_at );
	$info = array(
		'title'  => $m->title,
		'when'   => ucfirst( lk_date_long( gmdate( 'Y-m-d', $ts ) ) ) . ' · ' . gmdate( 'H:i', $ts ) . ' às ' . gmdate( 'H:i', $ts + (int) $m->duration * 60 ) . ' (' . (int) $m->duration . ' min)',
		'client' => $c ? lk_client_label( $c ) : 'Reunião interna',
		'kind'   => lk_meeting_kinds()[ $m->kind ] ?? '',
		'status' => lk_meeting_status_labels()[ $m->status ] ?? '',
		'link'   => $link,
		'place'  => $link ? '' : (string) $m->place,
		'guests' => (string) $m->guests,
		'team'   => $team,
		'notes'  => (string) $m->notes,
	);
	$h  = '<li class="meet is-click" tabindex="0" role="button" aria-label="Abrir a reunião ' . esc_attr( $m->title ) . '" data-meet-info="' . esc_attr( wp_json_encode( $info ) ) . '"><span class="meet-time">' . esc_html( lk_meeting_when( $m ) ) . '</span><span class="meet-main"><strong>' . esc_html( $m->title ) . '</strong><small>' . esc_html( ( $show_client && $c ? lk_client_label( $c ) . ' · ' : '' ) . ( lk_meeting_kinds()[ $m->kind ] ?? '' ) ) . '</small></span><span class="meet-go" aria-hidden="true">›</span>';
	// Ações que aparecem dentro da janela (copiadas por JavaScript).
	$h .= '<template data-meet-actions>';
	$wa = function_exists( 'lk_meeting_wa' ) ? lk_meeting_wa( $m ) : '';
	$h .= '<button type="button" class="btn btn--ghost btn--sm" data-open="remarcar-reuniao-crm" data-remarcar="' . esc_attr( wp_json_encode( array( 'id' => (int) $m->id, 'date' => gmdate( 'Y-m-d', $ts ), 'time' => gmdate( 'H:i', $ts ), 'title' => $m->title, 'has_client' => $c && is_email( $c->email ) ? 1 : 0 ) ) ) . '">' . lk_icon( 'relogio', 14 ) . '<span>Remarcar</span></button>';
	$h .= '<button type="button" class="btn btn--ghost btn--sm" data-open="editar-reuniao-crm" data-reuniao="' . esc_attr( wp_json_encode( $data ) ) . '">' . lk_icon( 'editar', 14 ) . '<span>Editar / ata</span></button>';
	if ( $wa ) {
		$h .= '<a class="btn btn--wa btn--sm" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . lk_icon( 'whatsapp', 14 ) . '<span>WhatsApp do cliente</span></a>';
	}
	ob_start();
	if ( $c && is_email( $c->email ) ) {
		lk_action_button( 'reuniao_enviar', array( 'id' => $m->id ), lk_icon( 'email', 14 ) . '<span>Enviar por e-mail</span>', 'btn btn--ghost btn--sm', 'Enviar esta reunião ao cliente por e-mail?' );
	}
	lk_action_button( 'reuniao_excluir', array( 'id' => $m->id ), '<span>Excluir</span>', 'btn btn--ghost btn--sm btn--danger-text', 'Excluir a reunião "' . $m->title . '"? ' . ( $c && is_email( $c->email ) ? 'O cliente recebe um aviso de cancelamento.' : '' ) );
	$h .= ob_get_clean();
	$h .= '</template></li>';
	return $h;
}

/** Lista curta para o dashboard: reuniões do CRM + do Google Agenda (se conectado), em ordem de horário. */
function lk_meetings_widget_html() {
	$items = array();
	foreach ( lk_meetings_upcoming( 8 ) as $m ) {
		$items[] = array( strtotime( $m->starts_at ), lk_meeting_row_html( $m ) );
	}
	if ( function_exists( 'lk_calendar_upcoming' ) && lk_google_connected() ) {
		$ev     = lk_calendar_upcoming( 14, 8 );
		$linked = array_filter( wp_list_pluck( lk_rows( 'meetings', "google_id <> ''", array() ), 'google_id' ) );
		if ( ! is_wp_error( $ev ) ) {
			foreach ( $ev as $e ) {
				if ( in_array( $e['id'], $linked, true ) ) {
					continue;
				}
				$day  = wp_date( 'Y-m-d', $e['start'] );
				$d    = lk_days_until( $day );
				$when = ( 0 === $d ? 'Hoje' : ( 1 === $d ? 'Amanhã' : lk_date( $day, 'd/m' ) . ' · ' . lk_dow_short( $day ) ) ) . ( $e['all_day'] ? '' : ' · ' . wp_date( 'H:i', $e['start'] ) );
				$info    = array( 'title' => $e['title'], 'when' => ucfirst( lk_date_long( $day ) ) . ( $e['all_day'] ? ' · dia todo' : ' · ' . wp_date( 'H:i', $e['start'] ) . ' às ' . wp_date( 'H:i', $e['end'] ) ), 'client' => '', 'kind' => 'Google Agenda', 'link' => $e['meet'] ? $e['meet'] : ( $e['link'] ? $e['link'] : '' ), 'guests' => $e['attendees'] );
				$items[] = array( $e['start'], '<li class="meet is-click" tabindex="0" role="button" data-meet-info="' . esc_attr( wp_json_encode( $info ) ) . '"><span class="meet-time">' . esc_html( $when ) . '</span><span class="meet-main"><strong>' . esc_html( $e['title'] ) . '</strong><small>Google Agenda</small></span><span class="meet-go" aria-hidden="true">›</span></li>' );
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
	lk_modal_start( 'detalhe-reuniao', 'Reunião' );
	echo '<div class="meet-detail" data-md-body></div><div class="meet-detail-acts" data-md-acts></div>';
	lk_modal_end();
	lk_modal_start( 'remarcar-reuniao-crm', 'Remarcar reunião' );
	lk_form( 'reuniao_remarcar', 'stack' );
	echo '<input type="hidden" name="id" value="0"><p class="muted small" data-remarcar-title></p>';
	echo '<div class="grid-2">';
	lk_input( 'date', 'Nova data', lk_today(), 'date', 'required' );
	lk_input( 'time', 'Novo horário', '10:00', 'time', 'required' );
	echo '</div>';
	echo '<label class="check"><input type="checkbox" name="avisar" value="1" checked><span>Avisar o cliente por e-mail (com o novo horário)</span></label>';
	echo '<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Remarcar</button></div></form>';
	lk_modal_end();
	?>
	<script>
	(function(){
	function sync(f){var h=f.querySelector('[name=client_id]');if(!h)return;var root=f.querySelector('[data-cpick]'),sel=root.querySelector('[data-cpick-sel]'),btn=root.querySelector('[data-cid="'+(h.value||0)+'"]');
		if(h.value&&h.value!=='0'&&btn){sel.hidden=false;sel.innerHTML='<span>✓ <b></b></span><button type="button" data-cchange>trocar</button>'+(btn.dataset.cmail==='0'?'<em>sem e-mail na ficha: não dá para avisar</em>':'');sel.querySelector('b').textContent=btn.dataset.cname;root.classList.add('is-picked');}
		else{sel.hidden=true;sel.innerHTML='';root.classList.remove('is-picked');if(h.value==='0'){h.value='0';}}}
	window.lkPickSync=sync;
	function esc(t){return String(t==null?'':t).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
	window.lkMeetOpen=function(row){
		var d={};try{d=JSON.parse(row.getAttribute('data-meet-info'))||{};}catch(x){return;}
		var dlg=document.getElementById('detalhe-reuniao');if(!dlg)return;
		var body=dlg.querySelector('[data-md-body]'),acts=dlg.querySelector('[data-md-acts]');
		var h='<h3>'+esc(d.title)+'</h3><p class="meet-when">📅 '+esc(d.when)+'</p><dl class="meet-dl">';
		if(d.client)h+='<dt>Cliente</dt><dd>'+esc(d.client)+'</dd>';
		if(d.kind)h+='<dt>Tipo</dt><dd>'+esc(d.kind)+'</dd>';
		if(d.status)h+='<dt>Situação</dt><dd>'+esc(d.status)+'</dd>';
		if(d.place)h+='<dt>Local</dt><dd>'+esc(d.place)+'</dd>';
		if(d.link)h+='<dt>Link</dt><dd><a href="'+esc(d.link)+'" target="_blank" rel="noopener">'+esc(d.link)+'</a></dd>';
		if(d.guests&&d.guests.length)h+='<dt>Participantes</dt><dd>'+esc(Array.isArray(d.guests)?d.guests.join(', '):d.guests)+'</dd>';
		if(d.team&&d.team.length)h+='<dt>Equipe</dt><dd>'+esc(d.team.join(', '))+'</dd>';
		if(d.notes)h+='<dt>Pauta / ata</dt><dd style="white-space:pre-wrap">'+esc(d.notes)+'</dd>';
		body.innerHTML=h+'</dl>';
		var a='';if(d.link)a+='<a class="btn btn--primary" href="'+esc(d.link)+'" target="_blank" rel="noopener">Entrar na reunião</a>';
		acts.innerHTML=a;
		var tpl=row.querySelector('template[data-meet-actions]');if(tpl)acts.appendChild(tpl.content.cloneNode(true));
		if(dlg.showModal){if(!dlg.open)dlg.showModal();}else{dlg.setAttribute('open','');}
	};
	document.addEventListener('keydown',function(e){if((e.key==='Enter'||e.key===' ')&&e.target.matches&&e.target.matches('li.meet.is-click')){e.preventDefault();window.lkMeetOpen(e.target);}});
	document.addEventListener('click',function(e){
		var pb=e.target.closest&&e.target.closest('[data-cpick] [data-cid]');
		if(pb){var root=pb.closest('[data-cpick]');root.querySelector('[name=client_id]').value=pb.dataset.cid;sync(root.closest('form'));return;}
		if(e.target.closest&&e.target.closest('[data-cchange]')){var r=e.target.closest('[data-cpick]');r.querySelector('[name=client_id]').value='';sync(r.closest('form'));return;}
		var row=e.target.closest&&e.target.closest('li.meet.is-click');
		if(row&&!e.target.closest('a,button,form,input,select,textarea,summary')){lkMeetOpen(row);return;}
		var rm=e.target.closest&&e.target.closest('[data-remarcar]');
		if(rm){var dd=JSON.parse(rm.dataset.remarcar);setTimeout(function(){var f=document.querySelector('#remarcar-reuniao-crm form');if(!f)return;f.querySelector('[name=id]').value=dd.id;f.querySelector('[name=date]').value=dd.date;f.querySelector('[name=time]').value=dd.time;var t=f.querySelector('[data-remarcar-title]');if(t)t.textContent=dd.title;var a=f.querySelector('[name=avisar]');if(a){a.checked=!!dd.has_client;a.disabled=!dd.has_client;}},30);return;}
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
	lk_input( 'guests', 'Participantes (nomes ou e-mails)', '', 'text', 'placeholder="Quem vai participar"' );
	if ( ! $edit ) {
		echo '<input type="hidden" name="team_picker" value="1">';
		lk_meeting_team_picker();
	}
	if ( function_exists( 'lk_google_connected' ) && lk_google_connected() ) {
		echo '<label class="check"><input type="checkbox" name="google" value="1" checked><span>Criar no Google Agenda com link do Google Meet (os e-mails em "Participantes" recebem o convite do Google)</span></label>';
	} else {
		echo '<p class="muted small">Google Agenda não conectado: a reunião fica só no CRM. <a href="' . esc_url( lk_panel_url( 'config' ) . '#google' ) . '">Conectar o Google</a> para criar o link do Meet automaticamente.</p>';
	}
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
		if ( $m->google_id && function_exists( 'lk_calendar_delete' ) ) {
			lk_calendar_delete( $m->google_id );
		}
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
		$old = $m;
		$m   = lk_get( 'meetings', $m->id );
		$msg = 'Reunião atualizada.';
		if ( $m->google_id && function_exists( 'lk_calendar_move' ) ) {
			$g = 'cancelada' === $m->status ? lk_calendar_delete( $m->google_id ) : ( $moved && $data['starts_at'] !== $old->starts_at ? lk_calendar_move( $m->google_id, $date, $time ) : null );
			if ( is_wp_error( $g ) ) {
				$msg .= ' (Google Agenda: ' . $g->get_error_message() . ')';
			} elseif ( $g ) {
				$msg .= ' Google Agenda atualizado.';
			}
		}
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
	if ( lk_in( 'team_picker', 'bool' ) ) {
		lk_meeting_save_team( $mid, true );
	}
	$msg                = 'Reunião marcada. Ela aparece no dashboard, na Agenda e na área do cliente.';
	if ( lk_in( 'google', 'bool' ) && function_exists( 'lk_calendar_create' ) && lk_google_connected() ) {
		$emails = function_exists( 'lk_parse_emails' ) ? lk_parse_emails( $data['guests'] ) : array();
		$res    = lk_calendar_create( $title, $date, $time, $data['duration'], $emails, null, 'Reunião com ' . lk_setting( 'empresa' ) . ( $data['place'] ? "\n" . $data['place'] : '' ), 'presencial' !== $data['kind'] );
		if ( is_wp_error( $res ) ) {
			$msg .= ' (Não foi possível criar no Google Agenda: ' . $res->get_error_message() . ' Reconecte o Google em Configurações.)';
		} else {
			$meet = isset( $res['hangoutLink'] ) ? $res['hangoutLink'] : '';
			lk_update( 'meetings', $mid, array( 'google_id' => isset( $res['id'] ) ? $res['id'] : '', 'meet_link' => $meet, 'place' => $data['place'] ? $data['place'] : $meet ) );
			$msg .= $meet ? ' Link do Meet criado.' : ' Criada no Google Agenda.';
		}
	}
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

/* -----------------------------------------------------------------------
 * Google Agenda dentro do calendário de reuniões
 * -------------------------------------------------------------------- */

/** Eventos do Google Agenda de um período (AAAA-MM-DD), que ainda não estão no CRM. Cache de 5 min. */
function lk_gcal_events( $from, $to ) {
	if ( ! function_exists( 'lk_google_connected' ) || ! lk_google_connected() ) {
		return array();
	}
	$key = 'lk_gev_' . (int) get_option( 'lk_cal_v', 1 ) . '_' . $from . '_' . $to;
	$ev  = get_transient( $key );
	if ( false === $ev ) {
		$tz  = wp_timezone();
		$url = add_query_arg(
			array(
				'timeMin'      => rawurlencode( ( new DateTime( $from . ' 00:00:00', $tz ) )->format( 'c' ) ),
				'timeMax'      => rawurlencode( ( new DateTime( $to . ' 00:00:00', $tz ) )->format( 'c' ) ),
				'singleEvents' => 'true',
				'orderBy'      => 'startTime',
				'maxResults'   => 250,
			),
			'https://www.googleapis.com/calendar/v3/calendars/primary/events'
		);
		$res = lk_google_api( 'GET', $url );
		$ev  = array();
		if ( ! is_wp_error( $res ) ) {
			foreach ( (array) ( $res['items'] ?? array() ) as $e ) {
				if ( ! empty( $e['extendedProperties']['private']['lk_call'] ) || empty( $e['start']['dateTime'] ) ) {
					continue; // chamadas rápidas do chat e eventos de dia inteiro ficam de fora
				}
				$ev[] = array(
					'id'    => $e['id'],
					'title' => $e['summary'] ?? '(sem título)',
					'start' => strtotime( $e['start']['dateTime'] ),
					'end'   => strtotime( $e['end']['dateTime'] ?? $e['start']['dateTime'] ),
					'meet'  => $e['hangoutLink'] ?? '',
					'link'  => $e['htmlLink'] ?? '',
				);
			}
		}
		set_transient( $key, $ev, 5 * MINUTE_IN_SECONDS );
	}
	$linked = array_filter( wp_list_pluck( lk_rows( 'meetings', "google_id <> ''", array() ), 'google_id' ) );
	return array_values( array_filter( $ev, function ( $e ) use ( $linked ) { return ! in_array( $e['id'], $linked, true ); } ) );
}

/** Remarcar: muda o dia e a hora, move no Google Agenda e avisa o cliente (opcional). */
function lk_do_reuniao_remarcar() {
	if ( ! lk_is_team() ) {
		wp_die( 'Sem permissão.' );
	}
	$m    = lk_get( 'meetings', lk_in( 'id', 'int' ) );
	$date = lk_in( 'date', 'date' );
	$time = preg_match( '/^\d{2}:\d{2}$/', (string) lk_in( 'time' ) ) ? lk_in( 'time' ) : '';
	if ( ! $m || ! $date || ! $time ) {
		lk_back( 'Informe a nova data e o horário.', 'erro' );
	}
	lk_update( 'meetings', $m->id, array( 'starts_at' => $date . ' ' . $time . ':00', 'reminded' => 0, 'status' => 'agendada' ) );
	$m   = lk_get( 'meetings', $m->id );
	$msg = 'Reunião remarcada para ' . lk_date( $date, 'd/m' ) . ' às ' . $time . '.';
	if ( $m->google_id && function_exists( 'lk_calendar_move' ) ) {
		$g = lk_calendar_move( $m->google_id, $date, $time );
		$msg .= is_wp_error( $g ) ? ' (Google Agenda: ' . $g->get_error_message() . ')' : ' Google Agenda atualizado.';
	}
	if ( lk_in( 'avisar', 'bool' ) && $m->client_id ) {
		$msg .= lk_meeting_mail( $m, 'remarcada' ) ? ' O cliente foi avisado por e-mail.' : '';
	}
	if ( function_exists( 'lk_meeting_save_team' ) ) {
		foreach ( array_filter( array_map( 'absint', explode( ',', (string) $m->team_ids ) ) ) as $uid ) {
			lk_notify( $uid, '📅 Reunião remarcada: "' . $m->title . '" agora em ' . lk_date( $date, 'd/m' ) . ' às ' . $time, lk_panel_url( 'agenda' ) );
		}
	}
	lk_back( $msg );
}

/** Excluir: apaga a reunião (e do Google Agenda) e avisa o cliente do cancelamento. */
function lk_do_reuniao_excluir() {
	if ( ! lk_is_team() ) {
		wp_die( 'Sem permissão.' );
	}
	$m = lk_get( 'meetings', lk_in( 'id', 'int' ) );
	if ( ! $m ) {
		lk_back();
	}
	$msg = 'Reunião excluída.';
	if ( $m->client_id && lk_meeting_mail( $m, 'cancelada' ) ) {
		$msg .= ' O cliente foi avisado do cancelamento.';
	}
	if ( $m->google_id && function_exists( 'lk_calendar_delete' ) ) {
		lk_calendar_delete( $m->google_id );
	}
	foreach ( array_filter( array_map( 'absint', explode( ',', (string) $m->team_ids ) ) ) as $uid ) {
		lk_notify( $uid, '📅 Reunião cancelada: "' . $m->title . '"', lk_panel_url( 'agenda' ) );
	}
	lk_delete( 'meetings', $m->id );
	lk_back( $msg );
}
