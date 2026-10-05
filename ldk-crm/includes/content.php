<?php
/**
 * Conteúdo: a esteira de posts da agência (no lugar do Monday).
 *
 * Etapas editáveis (Configurações): Pauta → Design → Revisão → Aprovação do cliente → Agendado → Publicado.
 * Papéis de cada etapa (qual é "design", "revisão", "aprovação", "agendado", "publicado") também configuráveis.
 *
 * Automático:
 * - social media/atendimento planejam o mês (título + legenda) → link do planejamento para o cliente (planning.php);
 * - planejamento aprovado → os posts vão para Design e o designer é avisado;
 * - designer sobe a arte e salva → Revisão (avisa quem revisa: revisor do cliente ou atendimento);
 * - atendimento "Enviar para o cliente" → Aprovação (e-mail + link de WhatsApp, link com e sem login);
 * - cliente aprova → Agendado (publica sozinho no horário, social.php) ;
 * - cliente pede alteração na ARTE → volta para Design (avisa o designer);
 *   na LEGENDA → fica com o social media (avisa) → ao reenviar, volta para Aprovação.
 * Tudo vira linha na planilha do Google Sheets do cliente.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_formats() {
	return array( 'arte' => 'Arte única', 'carrossel' => 'Carrossel', 'reels' => 'Vídeo / Reels', 'foto' => 'Foto', 'story' => 'Story' );
}

function lk_networks() {
	return array( 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'gmn' => 'Google Meu Negócio' );
}

function lk_stages() {
	$out = array();
	foreach ( lk_list( lk_setting( 'etapas_conteudo' ) ) as $n ) {
		$out[ sanitize_title( $n ) ] = $n;
	}
	return $out ? $out : array( 'pauta' => 'Pauta', 'publicado' => 'Publicado' );
}

/**
 * Etapa de cada papel: design, revisao, aprovacao, agendado, publicado.
 */
function lk_stage_for( $role ) {
	$st  = array_keys( lk_stages() );
	$set = sanitize_title( lk_setting( 'etapa_' . $role ) );
	if ( $set && in_array( $set, $st, true ) ) {
		return $set;
	}
	$guess = array( 'planejamento' => 'planej', 'design' => 'design', 'revisao' => 'revis', 'aprovacao' => 'aprova', 'agendado' => 'agend', 'publicado' => 'public' );
	foreach ( $st as $s ) {
		if ( false !== strpos( $s, $guess[ $role ] ) ) {
			return $s;
		}
	}
	return $st[0];
}

function lk_post_media( $p ) {
	return lk_json( $p->media );
}

/**
 * Miniatura da arte (primeira imagem do post). Vídeo ou arquivo só no Drive: sem miniatura.
 */
function lk_post_thumb( $p, $size = 'medium' ) {
	foreach ( lk_post_media( $p ) as $m ) {
		if ( 'image' === ( $m['type'] ?? '' ) && 0 === strpos( (string) $m['id'], 'wp-' ) ) {
			$src = wp_get_attachment_image_url( (int) substr( $m['id'], 3 ), $size );
			return $src ? $src : $m['link'];
		}
	}
	return '';
}

function lk_post_has_video( $p ) {
	foreach ( lk_post_media( $p ) as $m ) {
		if ( 'video' === ( $m['type'] ?? '' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Miniatura pronta para os cartões: imagem, ícone de vídeo ou nada.
 */
function lk_thumb_html( $p, $class = 'pthumb' ) {
	$src = lk_post_thumb( $p );
	if ( $src ) {
		return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $src ) . '" alt="" loading="lazy">';
	}
	if ( lk_post_media( $p ) ) {
		return '<span class="' . esc_attr( $class ) . ' ' . esc_attr( $class ) . '--ph">' . ( lk_post_has_video( $p ) ? '▶' : '🖼' ) . '</span>';
	}
	return '';
}

/**
 * Papel da etapa atual: planejamento, design, revisao, aprovacao, agendado, publicado (ou '' se for etapa extra).
 */
function lk_post_role( $p ) {
	foreach ( array( 'planejamento', 'design', 'revisao', 'aprovacao', 'agendado', 'publicado' ) as $r ) {
		if ( $p->stage === lk_stage_for( $r ) ) {
			return $r;
		}
	}
	return '';
}

/**
 * Selo colorido da etapa (painel da equipe).
 */
function lk_stage_chip( $p ) {
	$role = lk_post_role( $p );
	$name = lk_stages()[ $p->stage ] ?? $p->stage;
	if ( 'planejamento' === $role && $p->plan_id ) {
		$plan = lk_plan_get( $p->plan_id );
		$name = $plan ? $name . ' · ' . lk_plan_status_label( $plan->status ) : $name;
	}
	return '<em class="stg stg--' . esc_attr( $role ? $role : 'extra' ) . '">' . esc_html( $name ) . '</em>';
}

/**
 * Etapa como o cliente vê: array( papel, texto ).
 */
function lk_client_stage( $p ) {
	$role = lk_post_role( $p );
	if ( 'planejamento' === $role ) {
		$plan = $p->plan_id ? lk_plan_get( $p->plan_id ) : null;
		return array( 'planejamento', $plan && 'enviado' === $plan->status ? 'Planejamento: aguardando você' : ( $plan && 'aprovado' === $plan->status ? 'Planejamento aprovado' : 'Em planejamento' ) );
	}
	if ( 'alteracao' === $p->client_status && in_array( $role, array( 'design', 'revisao' ), true ) ) {
		return array( $role, 'Ajustando a ' . $p->change_target );
	}
	$map = array(
		'design'    => 'Em criação',
		'revisao'   => 'Em revisão',
		'aprovacao' => 'Aguardando sua aprovação',
		'agendado'  => 'Aprovado · agendado',
		'publicado' => 'Publicado',
	);
	return array( $role ? $role : 'extra', $map[ $role ] ?? ( lk_stages()[ $p->stage ] ?? '' ) );
}

/**
 * Quem revisa: o revisor do post, senão o atendimento.
 */
function lk_post_revisor( $p ) {
	return (int) ( ! empty( $p->revisor_id ) ? $p->revisor_id : $p->atendimento_id );
}

/* -----------------------------------------------------------------------
 * Pacote de artes do cliente (quantidade por mês)
 * -------------------------------------------------------------------- */

function lk_client_month_count( $client_id, $ym ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . lk_table( 'posts' ) . ' WHERE client_id = %d AND scheduled_at BETWEEN %s AND %s', $client_id, $ym . '-01 00:00:00', gmdate( 'Y-m-t', strtotime( $ym . '-01' ) ) . ' 23:59:59' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Barrinha "8 de 12 artes". Vazio se o cliente não tem pacote definido.
 */
function lk_quota_html( $client, $ym ) {
	if ( ! $client || (int) $client->posts_quota <= 0 ) {
		return '';
	}
	$n   = lk_client_month_count( $client->id, $ym );
	$q   = (int) $client->posts_quota;
	$cls = $n > $q ? 'quota--over' : ( $n === $q ? 'quota--full' : '' );
	$txt = $n . ' de ' . $q . ' artes em ' . lk_month_name( gmdate( 'n', strtotime( $ym . '-01' ) ) );
	if ( $n > $q ) {
		$txt .= ' · passou ' . ( $n - $q );
	} elseif ( $n < $q ) {
		$txt .= ' · faltam ' . ( $q - $n );
	} else {
		$txt .= ' · pacote completo';
	}
	return '<span class="quota ' . $cls . '"><i style="width:' . min( 100, (int) round( $n / max( 1, $q ) * 100 ) ) . '%"></i><b>' . esc_html( $txt ) . '</b></span>';
}

/**
 * Aviso quando o mês passa do pacote (ao criar o post ou mudar o mês dele). Avisa o atendimento e o social media.
 */
function lk_quota_check( $client, $scheduled_at ) {
	if ( ! $client || (int) $client->posts_quota <= 0 || ! $scheduled_at ) {
		return '';
	}
	$ym = substr( $scheduled_at, 0, 7 );
	$n  = lk_client_month_count( $client->id, $ym );
	if ( $n <= (int) $client->posts_quota ) {
		return '';
	}
	$msg = lk_client_label( $client ) . ' passou do pacote em ' . lk_month_label( $ym ) . ': ' . $n . ' de ' . (int) $client->posts_quota . ' artes.';
	foreach ( array_unique( array_filter( array( (int) $client->atendimento_id, (int) $client->social_id ) ) ) as $uid ) {
		lk_notify( $uid, '⚠️ ' . $msg, lk_panel_url( 'planejamento', 0, array( 'cliente' => $client->id, 'mes' => $ym ) ) );
	}
	return ' ⚠️ ' . $msg;
}

function lk_post_networks( $p ) {
	return array_values( array_filter( explode( ',', (string) $p->networks ) ) );
}

function lk_post_url( $p ) {
	return lk_url( 'aprovar/' . $p->approval_token );
}

/**
 * Posts com o nome do cliente.
 */
function lk_posts( $where = '1=1', $args = array(), $order = 'p.scheduled_at IS NULL, p.scheduled_at, p.id' ) {
	global $wpdb;
	$sql = 'SELECT p.*, c.name AS client_name, c.company AS client_company, c.color AS client_color FROM ' . lk_table( 'posts' ) . ' p LEFT JOIN ' . lk_table( 'clients' ) . ' c ON c.id = p.client_id WHERE ' . $where . ' ORDER BY ' . $order;
	return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function lk_post_client_label( $p ) {
	return $p->client_company ? $p->client_company : ( $p->client_name ? $p->client_name : '—' );
}

/**
 * Quem é o responsável agora (pela etapa).
 */
function lk_post_owner( $p ) {
	if ( $p->stage === lk_stage_for( 'design' ) ) {
		return (int) $p->designer_id;
	}
	if ( 'legenda' === $p->change_target && $p->stage !== lk_stage_for( 'aprovacao' ) ) {
		return (int) $p->social_id;
	}
	if ( $p->stage === lk_stage_for( 'revisao' ) ) {
		return lk_post_revisor( $p );
	}
	if ( $p->stage === lk_stage_for( 'aprovacao' ) ) {
		return (int) $p->atendimento_id;
	}
	return (int) $p->social_id;
}

function lk_post_late( $p ) {
	$dl = function_exists( 'lk_post_deadline' ) ? lk_post_deadline( $p ) : '';
	if ( $dl && $dl < lk_now() ) {
		return true; // Passou do prazo da etapa atual.
	}
	return $p->scheduled_at && $p->scheduled_at < lk_now() && ! in_array( $p->stage, array( lk_stage_for( 'agendado' ), lk_stage_for( 'publicado' ) ), true );
}

/* -----------------------------------------------------------------------
 * Notificações internas (sininho da equipe)
 * -------------------------------------------------------------------- */

function lk_notify( $user_id, $text, $url = '' ) {
	if ( ! $user_id || (int) $user_id === get_current_user_id() ) {
		return;
	}
	lk_insert( 'notifications', array( 'user_id' => (int) $user_id, 'text' => mb_substr( $text, 0, 250 ), 'url' => $url ) );
}

function lk_notify_mentions( $text, $url, $from_label ) {
	if ( ! preg_match_all( '/@([\p{L}\p{N}_.-]{2,40})/u', $text, $m ) ) {
		return;
	}
	foreach ( lk_team_users() as $u ) {
		$first = sanitize_title( strtok( $u->display_name, ' ' ) );
		foreach ( $m[1] as $tag ) {
			if ( sanitize_title( $tag ) === $first || sanitize_title( $tag ) === sanitize_title( $u->user_login ) ) {
				lk_notify( $u->ID, $from_label . ' mencionou você: ' . wp_trim_words( $text, 14 ), $url );
			}
		}
	}
}

function lk_notifications_unread( $user_id = 0 ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . lk_table( 'notifications' ) . ' WHERE user_id = %d AND read_at IS NULL', $user_id ? $user_id : get_current_user_id() ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/* -----------------------------------------------------------------------
 * Salvar post
 * -------------------------------------------------------------------- */

function lk_do_post_save() {
	lk_require( 'conteudo' );
	$id     = lk_in( 'id', 'int' );
	$old    = $id ? lk_get( 'posts', $id ) : null;
	$client = lk_get( 'clients', lk_in( 'client_id', 'int' ) ? lk_in( 'client_id', 'int' ) : ( $old ? $old->client_id : 0 ) );
	if ( ! $client ) {
		lk_back( 'Escolha o cliente.', 'erro' );
	}
	$nets = array_values( array_intersect( array_keys( lk_networks() ), array_map( 'sanitize_key', (array) ( $_POST['networks'] ?? array() ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$date = lk_in( 'date', 'date' );
	$time = preg_match( '/^\d{2}:\d{2}$/', lk_in( 'time' ) ) ? lk_in( 'time' ) : '10:00';
	$data = array(
		'client_id'      => $client->id,
		'title'          => lk_in( 'title' ) ? lk_in( 'title' ) : 'Post',
		'caption'        => lk_in( 'caption', 'textarea' ),
		'format'         => isset( lk_formats()[ lk_in( 'format' ) ] ) ? lk_in( 'format' ) : 'arte',
		'networks'       => implode( ',', $nets ),
		'scheduled_at'   => $date ? $date . ' ' . $time . ':00' : null,
		'designer_id'    => lk_in( 'designer_id', 'int' ) ? lk_in( 'designer_id', 'int' ) : (int) $client->designer_id,
		'social_id'      => lk_in( 'social_id', 'int' ) ? lk_in( 'social_id', 'int' ) : (int) $client->social_id,
		'atendimento_id' => lk_in( 'atendimento_id', 'int' ) ? lk_in( 'atendimento_id', 'int' ) : (int) $client->atendimento_id,
		'revisor_id'     => lk_in( 'revisor_id', 'int' ) ? lk_in( 'revisor_id', 'int' ) : (int) $client->revisor_id,
		'notes'          => lk_in( 'notes', 'textarea' ),
		'idea'           => lk_in( 'idea', 'textarea' ),
		'hashtags'       => lk_in( 'hashtags', 'textarea' ),
	);
	$data['deadlines'] = lk_prazos_from_form( $data['scheduled_at'] );
	$check = $data['scheduled_at'] && ( ! $old || substr( (string) $old->scheduled_at, 0, 7 ) !== substr( $data['scheduled_at'], 0, 7 ) );
	if ( $old ) {
		lk_update( 'posts', $old->id, $data );
		$id = $old->id;
		if ( $old->caption !== $data['caption'] && 'legenda' === $old->change_target ) {
			lk_post_log( $id, 'Legenda ajustada.' );
		}
	} else {
		$stages               = array_keys( lk_stages() );
		$data['stage']        = lk_in( 'direto_design', 'bool' ) ? lk_stage_for( 'design' ) : lk_stage_for( 'planejamento' );
		$data['approval_token'] = strtolower( wp_generate_password( 24, false ) );
		$data['created_by']   = get_current_user_id();
		$id                   = lk_insert( 'posts', $data );
		if ( $data['stage'] === lk_stage_for( 'design' ) ) {
			lk_notify( $data['designer_id'], 'Arte nova para fazer: ' . lk_client_label( $client ) . ' · ' . $data['title'], lk_panel_url( 'post', $id ) );
		}
	}
	lk_sheet_sync( $id );
	do_action( 'lk_post_saved', $id );
	$warn = $check ? lk_quota_check( $client, $data['scheduled_at'] ) : '';
	lk_back( 'Post salvo.' . $warn, $warn ? 'warn' : 'ok', lk_in( 'volta_url', 'url' ) ? lk_in( 'volta_url', 'url' ) : lk_panel_url( 'post', $id ) );
}

function lk_post_log( $post_id, $text, $internal = 1 ) {
	lk_insert( 'post_comments', array( 'post_id' => $post_id, 'user_id' => get_current_user_id(), 'internal' => $internal ? 1 : 0, 'target' => 'log', 'body' => $text ) );
}

function lk_post_move( $p, $stage, $log = '' ) {
	if ( ! isset( lk_stages()[ $stage ] ) || $stage === $p->stage ) {
		return;
	}
	lk_update( 'posts', $p->id, array( 'stage' => $stage ) );
	lk_post_log( $p->id, $log ? $log : 'Movido para "' . lk_stages()[ $stage ] . '".' );
	$p->stage = $stage;
	$url      = lk_panel_url( 'post', $p->id );
	if ( $stage === lk_stage_for( 'design' ) ) {
		lk_notify( $p->designer_id, 'Arte para fazer: ' . $p->title, $url );
	} elseif ( $stage === lk_stage_for( 'revisao' ) ) {
		lk_notify( lk_post_revisor( $p ), '👀 Arte pronta para revisar: ' . $p->title, $url );
	}
	do_action( 'lk_post_stage', $p->id, $stage );
	lk_sheet_sync( $p->id );
}

function lk_do_post_stage() {
	lk_require( 'conteudo' );
	$p = lk_get( 'posts', lk_in( 'id', 'int' ) );
	if ( $p ) {
		$to = sanitize_key( lk_in( 'stage' ) );
		if ( $to === lk_stage_for( 'aprovacao' ) && $p->stage !== $to ) {
			$gate = lk_review_gate( $p, false );
			if ( $gate ) {
				lk_back( $gate, 'erro' );
			}
		}
		lk_post_move( $p, $to );
	}
	lk_back( 'Etapa atualizada.' );
}

function lk_do_post_delete() {
	lk_require( 'conteudo' );
	$id = lk_in( 'id', 'int' );
	global $wpdb;
	$wpdb->delete( lk_table( 'post_comments' ), array( 'post_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	lk_delete( 'posts', $id );
	lk_back( 'Post excluído.', 'ok', lk_panel_url( 'conteudo' ) );
}

function lk_do_post_duplicate() {
	lk_require( 'conteudo' );
	$p = lk_get( 'posts', lk_in( 'id', 'int' ) );
	if ( ! $p ) {
		lk_back();
	}
	$d = (array) $p;
	unset( $d['id'] );
	$d['title']          = $p->title . ' (cópia)';
	$d['stage']          = array_keys( lk_stages() )[0];
	$d['approval_token'] = strtolower( wp_generate_password( 24, false ) );
	$d['client_status']  = '';
	$d['change_target']  = '';
	$d['published']      = '';
	$d['publish_error']  = '';
	$d['sheet_row']      = 0;
	$d['sent_at']        = null;
	$d['approved_at']    = null;
	$d['created_at']     = lk_now();
	$id                  = lk_insert( 'posts', $d );
	lk_back( 'Post duplicado.', 'ok', lk_panel_url( 'post', $id ) );
}

/**
 * Mídia do post: arquivos que já subiram (Drive ou WordPress) chegam como JSON; remover pelo índice.
 */
function lk_do_post_media() {
	lk_require( 'conteudo' );
	$p = lk_get( 'posts', lk_in( 'id', 'int' ) );
	if ( ! $p ) {
		lk_back();
	}
	$media = lk_post_media( $p );
	$rm    = isset( $_POST['remove'] ) ? array_map( 'absint', (array) $_POST['remove'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	$media = array_values( array_diff_key( $media, array_flip( $rm ) ) );
	$new   = json_decode( (string) lk_in( 'new_media', 'raw' ), true );
	foreach ( (array) $new as $f ) {
		if ( ! empty( $f['id'] ) ) {
			$media[] = array( 'id' => sanitize_text_field( $f['id'] ), 'name' => sanitize_text_field( $f['name'] ?? '' ), 'size' => (int) ( $f['size'] ?? 0 ), 'link' => esc_url_raw( $f['link'] ?? '' ), 'type' => preg_match( '/\.(mp4|mov|m4v|webm)$/i', $f['name'] ?? '' ) ? 'video' : 'image' );
		}
	}
	$bad = lk_media_check( $p, $media );
	if ( $bad ) {
		lk_back( $bad, 'erro' );
	}
	// Ordem do carrossel.
	$order = array_filter( array_map( 'absint', explode( ',', (string) lk_in( 'order' ) ) ), 'is_int' );
	if ( $order && count( $order ) === count( $media ) ) {
		$media = array_values( array_map( function ( $i ) use ( $media ) { return $media[ $i ]; }, $order ) );
	}
	lk_update( 'posts', $p->id, array( 'media' => wp_json_encode( $media ) ) );
	if ( $new ) {
		lk_post_log( $p->id, count( (array) $new ) . ' arquivo(s) enviado(s).' );
	}
	// Na etapa de design, salvar com arte = arte pronta → Revisão (a não ser que marque "ainda não terminei").
	$pronta = $media && ( lk_in( 'pronta', 'bool' ) || ( $p->stage === lk_stage_for( 'design' ) && ! lk_in( 'so_salvar', 'bool' ) ) );
	if ( $pronta && in_array( $p->stage, array( lk_stage_for( 'planejamento' ), lk_stage_for( 'design' ) ), true ) ) {
		lk_update( 'posts', $p->id, array( 'change_target' => '' ) );
		lk_post_move( $p, lk_stage_for( 'revisao' ), 'Arte pronta.' . ( 'arte' === $p->change_target ? ' (alteração feita)' : '' ) );
		$rev = get_userdata( lk_post_revisor( $p ) );
		lk_back( 'Arte salva e enviada para revisão' . ( $rev ? ' (' . $rev->display_name . ' foi avisado)' : '' ) . '.' );
	}
	lk_sheet_sync( $p->id );
	lk_back( 'Arquivos salvos.' );
}

/* -----------------------------------------------------------------------
 * Enviar para o cliente / aprovação
 * -------------------------------------------------------------------- */

function lk_approval_message( $p, $client ) {
	$first = $client->name ? strtok( $client->name, ' ' ) : '';
	return 'Olá' . ( $first ? ', ' . $first : '' ) . '! 😊 O conteúdo "' . $p->title . '"' . ( $p->scheduled_at ? ' (para ' . lk_date( $p->scheduled_at, 'd/m' ) . ')' : '' ) . ' está pronto para a sua aprovação. É só abrir, conferir a arte e a legenda e aprovar ou pedir ajuste: ' . lk_post_url( $p );
}

function lk_do_post_send() {
	lk_require( 'conteudo' );
	$p      = lk_get( 'posts', lk_in( 'id', 'int' ) );
	$client = $p ? lk_get( 'clients', $p->client_id ) : null;
	if ( ! $p || ! $client ) {
		lk_back();
	}
	if ( ! lk_post_media( $p ) ) {
		lk_back( 'Esse post ainda não tem arte.', 'erro' );
	}
	$gate = lk_review_gate( $p, lk_in( 'forcar', 'bool' ) );
	if ( $gate ) {
		lk_back( $gate, 'erro' );
	}
	lk_update( 'posts', $p->id, array( 'client_status' => 'pendente', 'sent_at' => lk_now(), 'change_target' => '' ) );
	lk_post_move( $p, lk_stage_for( 'aprovacao' ), 'Enviado para o cliente aprovar.' );
	if ( is_email( $client->email ) && ! $client->email_optout ) {
		$media = lk_post_media( $p );
		$img   = $media && 'image' === $media[0]['type'] && 0 === strpos( (string) $media[0]['id'], 'wp-' ) ? $media[0]['link'] : '';
		lk_mail(
			$client->email,
			'Aprovação: ' . $p->title,
			'Conteúdo para aprovar',
			'<p>Olá, ' . esc_html( strtok( (string) $client->name, ' ' ) ) . '! O conteúdo <strong>' . esc_html( $p->title ) . '</strong> está pronto. Confira a arte e a legenda e aprove ou peça ajustes com um clique.</p>' . ( $img ? '<p><img src="' . esc_url( $img ) . '" alt="" style="width:100%;border-radius:12px;"></p>' : '' ),
			array( array( 'Formato', esc_html( lk_formats()[ $p->format ] ?? '' ) ), array( 'Publicação prevista', $p->scheduled_at ? esc_html( lk_date( $p->scheduled_at, 'd/m/Y H:i' ) ) : 'a definir' ) ),
			'Ver e aprovar',
			lk_post_url( $p )
		);
	}
	$wa = $client->whatsapp ? lk_wa_link( $client->whatsapp, lk_approval_message( $p, $client ) ) : '';
	set_transient( 'lk_last_send_' . get_current_user_id(), array( 'post' => $p->id, 'wa' => $wa ), 600 );
	lk_back( 'Enviado ao cliente por e-mail.' . ( $wa ? ' Clique em "Mandar no WhatsApp" para reforçar.' : '' ) );
}

/**
 * A equipe aprova no lugar do cliente (ele aprovou pelo WhatsApp, por telefone…).
 */
function lk_do_post_approve_manual() {
	lk_require( 'conteudo' );
	$p = lk_get( 'posts', lk_in( 'id', 'int' ) );
	if ( ! $p ) {
		lk_back();
	}
	lk_approval_decide( $p, 'aprovar', '', 'Aprovado manualmente por ' . wp_get_current_user()->display_name . '.', false );
	lk_back( 'Aprovado e agendado.' );
}

/**
 * Decisão do cliente (página de aprovação ou área do cliente).
 */
function lk_approval_decide( $p, $decision, $target, $comment, $by_client = true ) {
	$client = lk_get( 'clients', $p->client_id );
	if ( $comment ) {
		lk_insert( 'post_comments', array( 'post_id' => $p->id, 'user_id' => get_current_user_id(), 'from_client' => $by_client ? 1 : 0, 'target' => $target ? $target : 'geral', 'body' => $comment ) );
	}
	if ( 'aprovar' === $decision ) {
		lk_update( 'posts', $p->id, array( 'client_status' => 'aprovado', 'approved_at' => lk_now(), 'change_target' => '' ) );
		lk_post_move( $p, lk_stage_for( 'agendado' ), ( $by_client ? 'Aprovado pelo cliente.' : 'Aprovado (manual).' ) . ( lk_post_networks( $p ) && $p->scheduled_at ? ' Publicação automática em ' . lk_date( $p->scheduled_at, 'd/m H:i' ) . '.' : '' ) );
		lk_notify( $p->atendimento_id, '✅ ' . lk_client_label( $client ) . ' aprovou: ' . $p->title, lk_panel_url( 'post', $p->id ) );
		lk_notify( $p->social_id, '✅ Aprovado: ' . $p->title, lk_panel_url( 'post', $p->id ) );
		return 'Aprovado! Obrigado. 🎉';
	}
	$target = 'legenda' === $target ? 'legenda' : 'arte';
	lk_update( 'posts', $p->id, array( 'client_status' => 'alteracao', 'change_target' => $target ) );
	if ( 'arte' === $target ) {
		lk_post_move( $p, lk_stage_for( 'design' ), 'Cliente pediu alteração na arte: ' . wp_trim_words( $comment, 20 ) );
		lk_notify( $p->designer_id, '✏️ Alteração na arte pedida por ' . lk_client_label( $client ) . ': ' . $p->title, lk_panel_url( 'post', $p->id ) );
	} else {
		lk_post_move( $p, lk_stage_for( 'revisao' ), 'Cliente pediu alteração na legenda: ' . wp_trim_words( $comment, 20 ) );
		lk_notify( $p->social_id, '✏️ Alteração na legenda pedida por ' . lk_client_label( $client ) . ': ' . $p->title, lk_panel_url( 'post', $p->id ) );
	}
	lk_notify( $p->atendimento_id, 'Cliente pediu ajuste (' . $target . '): ' . $p->title, lk_panel_url( 'post', $p->id ) );
	return 'Recebemos o seu pedido de ajuste. Assim que estiver pronto, mandamos de novo para você aprovar.';
}

add_action(
	'init',
	function () {
		add_rewrite_rule( '^aprovar/([A-Za-z0-9]+)/?$', 'index.php?lk_route=approval&lk_token=$matches[1]', 'top' );
	}
);

add_action(
	'template_redirect',
	function () {
		if ( 'approval' !== get_query_var( 'lk_route' ) ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		$rows = lk_rows( 'posts', 'approval_token = %s', array( sanitize_text_field( get_query_var( 'lk_token' ) ) ) );
		$p    = $rows ? $rows[0] : null;
		if ( ! $p ) {
			lk_render( 'public/indisponivel' );
		}
		$msg = '';
		if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'lk_approve_' . $p->id ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( 'apontar' === lk_in( 'decisao' ) ) {
				$msg = lk_note_from_client( $p );
			} elseif ( $p->stage === lk_stage_for( 'aprovacao' ) || 'pendente' === $p->client_status ) {
				$msg = lk_approval_decide( $p, lk_in( 'decisao' ), lk_in( 'alvo' ), lk_in( 'comentario', 'textarea' ), ! lk_is_team() );
				$p   = lk_get( 'posts', $p->id );
			} else {
				$msg = 'Este conteúdo já foi respondido. Obrigado!';
			}
		}
		lk_render( 'public/aprovar', array( 'p' => $p, 'msg' => $msg ) );
	},
	0
);

/**
 * Comentário interno / da equipe no post (com @menção).
 */
function lk_do_post_comment() {
	$p = lk_get( 'posts', lk_in( 'id', 'int' ) );
	if ( ! $p ) {
		lk_back();
	}
	$client_side = ! lk_is_team();
	if ( $client_side ) {
		$c = lk_current_client();
		if ( ! $c || (int) $c->id !== (int) $p->client_id ) {
			wp_die( 'Sem permissão.' );
		}
	}
	$body = lk_in( 'body', 'textarea' );
	if ( $body ) {
		lk_insert( 'post_comments', array( 'post_id' => $p->id, 'user_id' => get_current_user_id(), 'from_client' => $client_side ? 1 : 0, 'internal' => $client_side ? 0 : lk_in( 'interno', 'bool' ), 'target' => 'geral', 'body' => $body ) );
		if ( ! $client_side ) {
			lk_notify_mentions( $body, lk_panel_url( 'post', $p->id ), wp_get_current_user()->display_name );
		} else {
			lk_notify( $p->atendimento_id, 'Comentário do cliente em ' . $p->title, lk_panel_url( 'post', $p->id ) );
		}
	}
	lk_back( 'Comentário enviado.' );
}

/* -----------------------------------------------------------------------
 * Google Sheets: uma planilha por cliente, uma linha por post
 * -------------------------------------------------------------------- */

function lk_sheet_sync( $post_id ) {
	if ( ! function_exists( 'lk_google_connected' ) || ! lk_google_connected() ) {
		return;
	}
	$p = lk_get( 'posts', $post_id );
	$c = $p ? lk_get( 'clients', $p->client_id ) : null;
	if ( ! $c ) {
		return;
	}
	if ( ! $c->sheet_id ) {
		$folder = lk_drive_folder( array( 'Clientes', lk_client_label( $c ) ) );
		if ( is_wp_error( $folder ) ) {
			return;
		}
		$res = lk_google_api( 'POST', 'https://www.googleapis.com/drive/v3/files?fields=id', array( 'name' => 'Conteúdos · ' . lk_client_label( $c ), 'mimeType' => 'application/vnd.google-apps.spreadsheet', 'parents' => array( $folder ) ) );
		if ( is_wp_error( $res ) ) {
			return;
		}
		$c->sheet_id = $res['id'];
		lk_update( 'clients', $c->id, array( 'sheet_id' => $c->sheet_id ) );
		lk_google_api( 'PUT', 'https://sheets.googleapis.com/v4/spreadsheets/' . $c->sheet_id . '/values/A1:K1?valueInputOption=RAW', array( 'values' => array( array( 'ID', 'Título', 'Formato', 'Redes', 'Data', 'Etapa', 'Status do cliente', 'Legenda', 'Arquivos', 'Link de aprovação', 'Publicado' ) ) ) );
	}
	$files = implode( ' | ', array_map( function ( $m ) { return $m['link']; }, lk_post_media( $p ) ) );
	$pub   = lk_json( $p->published );
	$row   = array( $p->id, $p->title, lk_formats()[ $p->format ] ?? $p->format, $p->networks, $p->scheduled_at ? lk_date( $p->scheduled_at, 'd/m/Y H:i' ) : '', lk_stages()[ $p->stage ] ?? $p->stage, $p->client_status, $p->caption, $files, lk_post_url( $p ), implode( ' | ', array_map( function ( $x ) { return $x['url'] ?? ''; }, $pub ) ) );
	if ( $p->sheet_row ) {
		lk_google_api( 'PUT', 'https://sheets.googleapis.com/v4/spreadsheets/' . $c->sheet_id . '/values/A' . (int) $p->sheet_row . ':K' . (int) $p->sheet_row . '?valueInputOption=RAW', array( 'values' => array( $row ) ) );
		return;
	}
	$res = lk_google_api( 'POST', 'https://sheets.googleapis.com/v4/spreadsheets/' . $c->sheet_id . '/values/A1:append?valueInputOption=RAW&insertDataOption=INSERT_ROWS', array( 'values' => array( $row ) ) );
	if ( ! is_wp_error( $res ) && ! empty( $res['updates']['updatedRange'] ) && preg_match( '/!A(\d+)/', $res['updates']['updatedRange'], $m ) ) {
		lk_update( 'posts', $p->id, array( 'sheet_row' => (int) $m[1] ) );
	}
}

/* -----------------------------------------------------------------------
 * Carga da equipe
 * -------------------------------------------------------------------- */

function lk_team_load() {
	$out = array();
	foreach ( lk_team_users() as $u ) {
		$out[ $u->ID ] = array( 'user' => $u, 'posts' => 0, 'late' => 0, 'tasks' => 0, 'tasks_late' => 0 );
	}
	foreach ( lk_posts( 'p.stage <> %s', array( lk_stage_for( 'publicado' ) ) ) as $p ) {
		$o = lk_post_owner( $p );
		if ( isset( $out[ $o ] ) && $p->stage !== lk_stage_for( 'agendado' ) ) {
			$out[ $o ]['posts']++;
			if ( lk_post_late( $p ) ) {
				$out[ $o ]['late']++;
			}
		}
	}
	foreach ( lk_tasks( "t.status <> 'done'" ) as $t ) {
		if ( isset( $out[ $t->assignee ] ) ) {
			$out[ $t->assignee ]['tasks']++;
			if ( $t->due_date && $t->due_date < lk_today() ) {
				$out[ $t->assignee ]['tasks_late']++;
			}
		}
	}
	return $out;
}

/* -----------------------------------------------------------------------
 * REST: mover no Kanban/calendário, notificações
 * -------------------------------------------------------------------- */

add_action(
	'rest_api_init',
	function () {
		$team = function () {
			return lk_is_team();
		};
		register_rest_route( 'lk/v1', '/post/move', array( 'methods' => 'POST', 'callback' => 'lk_api_post_move', 'permission_callback' => $team ) );
		register_rest_route( 'lk/v1', '/notifications', array( 'methods' => 'GET', 'callback' => 'lk_api_notifications', 'permission_callback' => $team ) );
	}
);

function lk_api_post_move( WP_REST_Request $r ) {
	$p = lk_get( 'posts', absint( $r['id'] ) );
	if ( ! $p || ! lk_can( 'conteudo' ) ) {
		return new WP_Error( 'lk', 'Sem permissão.', array( 'status' => 403 ) );
	}
	if ( $r['stage'] ) {
		lk_post_move( $p, sanitize_key( $r['stage'] ) );
	}
	if ( $r['date'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $r['date'] ) ) {
		$time = $p->scheduled_at ? substr( $p->scheduled_at, 11 ) : '10:00:00';
		lk_update( 'posts', $p->id, array( 'scheduled_at' => $r['date'] . ' ' . $time ) );
		lk_post_log( $p->id, 'Data mudada para ' . lk_date( $r['date'] ) . '.' );
		lk_sheet_sync( $p->id );
	}
	foreach ( (array) $r['order'] as $pos => $pid ) {
		lk_update( 'posts', absint( $pid ), array( 'position' => (int) $pos ) );
	}
	return array( 'ok' => true );
}

function lk_api_notifications( WP_REST_Request $r ) {
	global $wpdb;
	$uid  = get_current_user_id();
	$rows = lk_rows( 'notifications', 'user_id = %d', array( $uid ), 'id DESC LIMIT 20' );
	if ( $r['read'] ) {
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . lk_table( 'notifications' ) . ' SET read_at = %s WHERE user_id = %d AND read_at IS NULL', lk_now(), $uid ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	return array(
		'n'     => lk_notifications_unread( $uid ) + lk_chat_unread_count(),
		'items' => array_map( function ( $n ) { return array( 'text' => $n->text, 'url' => $n->url, 'at' => lk_ago( $n->created_at ), 'new' => ! $n->read_at ); }, $rows ),
	);
}

/**
 * Formulário do post (criar e editar).
 */
function lk_post_form( $p = null, $client_id = 0, $back = '' ) {
	$team = lk_team_options( '— padrão do cliente —' );
	$cid  = $p ? (int) $p->client_id : (int) $client_id;
	$accs = $cid ? lk_social_accounts( $cid ) : array();
	$nets = $p ? lk_post_networks( $p ) : array_keys( $accs );
	lk_form( 'post_save', 'stack post-form' );
	?>
		<input type="hidden" name="id" value="<?php echo (int) ( $p ? $p->id : 0 ); ?>">
		<input type="hidden" name="volta_url" value="<?php echo esc_attr( $back ); ?>">
		<div class="grid-2">
			<?php lk_select( 'client_id', 'Cliente', lk_client_options( 'Escolha o cliente…' ), $cid, 'required data-post-client' ); ?>
			<?php lk_select( 'format', 'Formato', lk_formats(), $p ? $p->format : 'arte' ); ?>
		</div>
		<?php lk_input( 'title', 'Título (interno)', $p ? $p->title : '', 'text', 'required placeholder="Ex.: Dia das Mães · oferta"' ); ?>
		<?php lk_input( 'idea', 'Ideia do conteúdo (o cliente vê no planejamento)', $p ? $p->idea : '', 'textarea', 'rows="3" placeholder="Em 1–2 frases: o que vai ser esse post, o objetivo e a ideia visual…"' ); ?>
		<?php lk_input( 'caption', 'Legenda', $p ? $p->caption : '', 'textarea', 'rows="6" placeholder="A legenda que vai ser publicada…" data-caption' ); ?>
		<small class="muted" data-caption-count></small>
		<?php lk_input( 'hashtags', 'Hashtags (entram no fim da legenda ao publicar)', $p ? (string) $p->hashtags : '', 'textarea', 'rows="2" placeholder="#suaempresa #nicho #cidade" data-hashtags' ); ?>
		<div class="rev-tools">
			<button type="button" class="btn btn--ghost btn--sm" data-client-tags>#️⃣ Hashtags do cliente</button>
			<button type="button" class="btn btn--primary btn--sm" data-review>🔎 Revisar texto</button>
			<small class="muted" data-tag-count></small>
		</div>
		<div class="rev-out" data-review-out><?php echo $p ? lk_review_summary_html( $p ) : ''; // phpcs:ignore ?></div>
		<?php if ( lk_ai_ready() ) : ?>
		<div class="ai-tools"><span class="ai-tag">✨ IA</span>
			<select data-ai-tone><?php foreach ( lk_ai_tones() as $tk => $tl ) : ?><option value="<?php echo esc_attr( $tk ); ?>"><?php echo esc_html( $tl ); ?></option><?php endforeach; ?></select>
			<button type="button" class="btn btn--ghost btn--sm" data-ai="sugerir">Sugerir legendas</button>
			<button type="button" class="btn btn--ghost btn--sm" data-ai="melhorar">Melhorar o texto</button>
			<button type="button" class="btn btn--ghost btn--sm" data-ai="hashtags">Hashtags</button>
		</div>
		<div class="ai-out" data-ai-out></div>
		<?php else : ?><p class="muted small">✨ Quer ajuda da IA para legendas e ideias? Coloque a chave de uma IA (Groq é grátis) em Configurações → IA.</p><?php endif; ?>
		<?php
		$tagmap = array();
		foreach ( lk_clients() as $tc ) {
			$tagmap[ $tc->id ] = (string) $tc->hashtags;
		}
		?>
		<script type="application/json" data-tags-map><?php echo wp_json_encode( $tagmap ); ?></script>
		<div class="grid-2">
			<?php lk_input( 'date', 'Dia da publicação', $p && $p->scheduled_at ? substr( $p->scheduled_at, 0, 10 ) : '', 'date', 'data-post-date' ); ?>
			<?php lk_input( 'time', 'Horário', $p && $p->scheduled_at ? substr( $p->scheduled_at, 11, 5 ) : '10:00', 'time' ); ?>
		</div>
		<?php lk_prazos_fields( $p ); ?>
		<fieldset class="nets-pick">
			<legend class="small">Onde publicar</legend>
			<?php foreach ( lk_networks() as $n => $nl ) : ?>
				<label class="chk"><input type="checkbox" name="networks[]" value="<?php echo esc_attr( $n ); ?>"<?php checked( in_array( $n, $nets, true ) ); ?>> <?php echo esc_html( $nl ); ?><?php if ( $cid && ! isset( $accs[ $n ] ) ) : ?> <em class="badge badge--off">não vinculado</em><?php endif; ?></label>
			<?php endforeach; ?>
		</fieldset>
		<details class="post-more"<?php echo $p ? '' : ''; ?>>
			<summary class="small">Responsáveis e observações</summary>
			<div class="grid-3">
				<?php lk_select( 'designer_id', 'Designer', $team, $p ? $p->designer_id : '' ); ?>
				<?php lk_select( 'social_id', 'Social media', $team, $p ? $p->social_id : '' ); ?>
				<?php lk_select( 'atendimento_id', 'Atendimento', $team, $p ? $p->atendimento_id : '' ); ?>
				<?php lk_select( 'revisor_id', 'Revisão', $team, $p ? $p->revisor_id : '' ); ?>
			</div>
			<?php lk_input( 'notes', 'Briefing para o design (interno)', $p ? $p->notes : '', 'textarea', 'rows="3" placeholder="Referências, cores, texto da arte…"' ); ?>
			<?php if ( lk_ai_ready() ) : ?><button type="button" class="btn btn--ghost btn--sm" data-ai="briefing_arte">✨ Gerar briefing da arte</button><?php endif; ?>
		</details>
		<?php if ( ! $p ) : ?><?php lk_check( 'direto_design', 'Pular o planejamento e já mandar para o design', false ); ?><?php endif; ?>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary"><?php echo $p ? 'Salvar' : 'Criar post'; ?></button></div>
	</form>
	<?php
}

/**
 * Ficha do cliente: responsáveis, mensalidade, cor, anúncios e e-mails.
 */
function lk_do_client_team() {
	lk_require( 'clientes' );
	$c = lk_get( 'clients', lk_in( 'id', 'int' ) );
	if ( ! $c ) {
		lk_back( 'Cliente não encontrado.', 'erro' );
	}
	$data = array(
		'designer_id'     => lk_in( 'designer_id', 'int' ),
		'social_id'       => lk_in( 'social_id', 'int' ),
		'atendimento_id'  => lk_in( 'atendimento_id', 'int' ),
		'trafego_id'      => lk_in( 'trafego_id', 'int' ),
		'revisor_id'      => lk_in( 'revisor_id', 'int' ),
		'posts_quota'     => max( 0, lk_in( 'posts_quota', 'int' ) ),
		'color'           => sanitize_hex_color( lk_in( 'color' ) ) ?: '#14E9EC',
		'meta_ad_account' => lk_in( 'meta_ad_account' ),
		'ads_visible'     => lk_in( 'ads_visible', 'bool' ),
		'email_optout'    => lk_in( 'email_optout', 'bool' ),
	);
	if ( lk_can( 'financeiro' ) ) {
		$data['monthly_fee']    = lk_in( 'monthly_fee', 'money' );
		$data['due_day']        = min( 28, max( 0, lk_in( 'due_day', 'int' ) ) );
		$data['billing_active'] = lk_in( 'billing_active', 'bool' );
	}
	lk_update( 'clients', $c->id, $data );
	lk_billing_generate();
	lk_back( 'Cliente atualizado.' );
}

/* -----------------------------------------------------------------------
 * Status para o cliente: resumo de onde está cada arte, pronto para o WhatsApp
 * -------------------------------------------------------------------- */

function lk_status_message( $client ) {
	$ym    = current_time( 'Y-m' );
	$end   = gmdate( 'Y-m-t', strtotime( $ym . '-01 +1 month' ) );
	$posts = lk_posts( 'p.client_id = %d AND p.scheduled_at BETWEEN %s AND %s AND ( p.plan_id > 0 OR p.stage <> %s )', array( $client->id, $ym . '-01 00:00:00', $end . ' 23:59:59', lk_stage_for( 'planejamento' ) ), 'p.scheduled_at' );
	$first = $client->name ? strtok( $client->name, ' ' ) : '';
	$icons = array( 'planejamento' => '📝', 'design' => '🎨', 'revisao' => '👀', 'aprovacao' => '⏳', 'agendado' => '📅', 'publicado' => '✅', 'extra' => '•' );
	$lines = array();
	$month = '';
	$pend  = 0;
	foreach ( $posts as $p ) {
		$m = substr( $p->scheduled_at, 0, 7 );
		if ( $m !== $month ) {
			$lines[] = ( $lines ? "\n" : '' ) . '*' . ucfirst( lk_month_label( $m ) ) . '*';
			$month   = $m;
		}
		list( $role, $txt ) = lk_client_stage( $p );
		$lines[]            = ( $icons[ $role ] ?? '•' ) . ' ' . lk_date( $p->scheduled_at, 'd/m' ) . ' · ' . $p->title . ': ' . mb_strtolower( $txt );
		if ( 'aprovacao' === $role ) {
			$pend++;
		}
	}
	$msg = 'Olá' . ( $first ? ', ' . $first : '' ) . '! 😊 Passando para contar como estão os seus conteúdos:' . "\n\n" . ( $lines ? implode( "\n", $lines ) : 'Estamos montando o planejamento do próximo mês e já já mandamos para você aprovar.' );
	if ( $pend ) {
		$msg .= "\n\n" . ( 1 === $pend ? 'Tem 1 arte' : 'Tem ' . $pend . ' artes' ) . ' esperando a sua aprovação. É só entrar na sua área: ' . lk_client_url( 'aprovacoes' );
	} else {
		$msg .= "\n\n" . 'Você acompanha tudo pela sua área: ' . lk_client_url( 'conteudos' );
	}
	return $msg;
}

/**
 * Status por e-mail (o mesmo texto do WhatsApp, editável).
 */
function lk_do_status_email() {
	lk_require( 'clientes' );
	$c    = lk_get( 'clients', lk_in( 'id', 'int' ) );
	$body = lk_in( 'mensagem', 'textarea' );
	if ( ! $c || ! is_email( $c->email ) || ! $body ) {
		lk_back( 'O cliente não tem e-mail cadastrado.', 'erro' );
	}
	lk_mail( $c->email, 'Andamento dos seus conteúdos', 'Como estão os seus conteúdos', '<p>' . nl2br( esc_html( $body ) ) . '</p>', array(), 'Abrir minha área', lk_client_url( 'conteudos' ) );
	lk_back( 'Status enviado por e-mail.' );
}
