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

function lk_meeting_row_html( $m, $show_client = true ) {
	$c    = $m->client_id ? lk_get( 'clients', $m->client_id ) : null;
	$link = $m->place && preg_match( '#^https?://#i', $m->place ) ? '<a class="btn btn--primary btn--sm" href="' . esc_url( $m->place ) . '" target="_blank" rel="noopener">Entrar</a>' : '';
	$data = array( 'id' => (int) $m->id, 'client_id' => (int) $m->client_id, 'title' => $m->title, 'date' => gmdate( 'Y-m-d', strtotime( $m->starts_at ) ), 'time' => gmdate( 'H:i', strtotime( $m->starts_at ) ), 'duration' => (int) $m->duration, 'kind' => $m->kind, 'place' => $m->place, 'guests' => $m->guests, 'notes' => (string) $m->notes, 'status' => $m->status );
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
		<div class="card-head"><h3>📅 Próximas reuniões</h3><span class="row-btns"><button type="button" class="btn btn--ghost btn--sm" data-open="nova-reuniao-crm">+ Reunião</button><a class="small" href="<?php echo esc_url( lk_panel_url( 'agenda' ) ); ?>">agenda</a></span></div>
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
		<div class="card-head"><h3>Reuniões registradas</h3><button type="button" class="btn btn--primary btn--sm" data-open="nova-reuniao-crm">+ Registrar reunião</button></div>
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
		<div class="card-head"><h3>Reuniões</h3><button type="button" class="btn btn--primary btn--sm" data-open="nova-reuniao-crm" data-reuniao-client="<?php echo (int) $client->id; ?>">+ Registrar reunião</button></div>
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
	lk_modal_start( 'nova-reuniao-crm', 'Registrar reunião' );
	lk_meeting_form( $client_id );
	lk_modal_end();
	lk_modal_start( 'editar-reuniao-crm', 'Reunião' );
	lk_meeting_form( 0, true );
	lk_modal_end();
	?>
	<script>
	document.addEventListener('click',function(e){
		var b=e.target.closest&&e.target.closest('[data-reuniao],[data-reuniao-client]');if(!b)return;
		setTimeout(function(){
			var f;
			if(b.dataset.reuniao){var d=JSON.parse(b.dataset.reuniao);f=document.querySelector('#editar-reuniao-crm form, [data-modal="editar-reuniao-crm"] form');if(!f)return;
				['id','client_id','title','date','time','duration','kind','place','guests','notes','status'].forEach(function(n){var i=f.querySelector('[name='+n+']');if(i)i.value=d[n]==null?'':d[n];});}
			else if(b.dataset.reuniaoClient){f=document.querySelector('#nova-reuniao-crm form, [data-modal="nova-reuniao-crm"] form');var i=f&&f.querySelector('[name=client_id]');if(i)i.value=b.dataset.reuniaoClient;}
		},30);
	});
	</script>
	<?php
	return ob_get_clean();
}

function lk_meeting_form( $client_id = 0, $edit = false ) {
	lk_form( 'reuniao_save', 'stack' );
	echo '<input type="hidden" name="id" value="0">';
	lk_select( 'client_id', 'Cliente (opcional)', lk_client_options( '— Sem cliente / interna —' ), $client_id );
	lk_input( 'title', 'Assunto', '', 'text', 'required placeholder="Ex.: Alinhamento do mês · Apresentação da proposta"' );
	echo '<div class="grid-3">';
	lk_input( 'date', 'Data', lk_today(), 'date', 'required' );
	lk_input( 'time', 'Hora', '10:00', 'time', 'required' );
	lk_input( 'duration', 'Duração (min)', 60, 'number', 'min="5" max="600"' );
	echo '</div>';
	lk_select( 'kind', 'Tipo', lk_meeting_kinds(), 'online' );
	lk_input( 'place', 'Link ou local', '', 'text', 'placeholder="https://meet.google.com/… ou endereço"' );
	lk_input( 'guests', 'Participantes', '', 'text', 'placeholder="Quem vai participar"' );
	lk_input( 'notes', 'Pauta / ata (o que foi combinado)', '', 'textarea', 'rows="4"' );
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
	if ( $m ) {
		$data['status'] = isset( $stat[ lk_in( 'status' ) ] ) ? lk_in( 'status' ) : $m->status;
		lk_update( 'meetings', $m->id, $data );
		lk_back( 'Reunião atualizada.' );
	}
	$data['created_by'] = get_current_user_id();
	lk_insert( 'meetings', $data );
	lk_back( 'Reunião registrada. Ela aparece no dashboard e na Agenda.' );
}
