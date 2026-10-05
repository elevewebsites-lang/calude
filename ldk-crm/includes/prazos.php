<?php
/**
 * Prazos de entrega por etapa: cada post tem uma data para o Design, a Revisão e a Aprovação do cliente,
 * sempre antes da publicação. Por padrão são calculados a partir do dia da publicação
 * (Configurações → Conteúdo: "dias antes"); no post dá para mudar cada um.
 *
 * posts.deadlines guarda só os prazos mudados à mão (JSON papel => 'Y-m-d H:i:s').
 * Os outros acompanham a data da publicação quando ela muda.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_prazo_roles() {
	return array(
		'design'    => 'Design',
		'revisao'   => 'Revisão',
		'aprovacao' => 'Aprovação do cliente',
	);
}

/**
 * Prazo automático de um papel: X dias antes da publicação, no horário configurado.
 */
function lk_prazo_auto( $scheduled_at, $role ) {
	if ( ! $scheduled_at ) {
		return '';
	}
	$days = max( 0, (int) lk_setting( 'prazo_' . $role ) );
	$hour = preg_match( '/^\d{2}:\d{2}$/', (string) lk_setting( 'prazo_hora' ) ) ? lk_setting( 'prazo_hora' ) : '18:00';
	$pub  = strtotime( $scheduled_at );
	$ts   = strtotime( gmdate( 'Y-m-d', $pub - $days * DAY_IN_SECONDS ) . ' ' . $hour . ':00' );
	// Nunca depois da publicação (post marcado para cedo, prazo no mesmo dia).
	if ( $ts >= $pub ) {
		$ts = $pub - HOUR_IN_SECONDS;
	}
	return gmdate( 'Y-m-d H:i:s', $ts );
}

/**
 * Todos os prazos do post: array( papel => array( 'at' => datetime, 'manual' => bool ) ).
 */
function lk_post_deadlines( $p ) {
	$manual = isset( $p->deadlines ) ? lk_json( $p->deadlines ) : array();
	$out    = array();
	foreach ( array_keys( lk_prazo_roles() ) as $role ) {
		if ( ! empty( $manual[ $role ] ) ) {
			$out[ $role ] = array( 'at' => $manual[ $role ], 'manual' => true );
		} else {
			$auto         = lk_prazo_auto( $p->scheduled_at, $role );
			$out[ $role ] = array( 'at' => $auto, 'manual' => false );
		}
	}
	return $out;
}

/**
 * Papel cujo prazo vale agora (pela etapa atual do post).
 */
function lk_post_deadline_role( $p ) {
	$role = lk_post_role( $p );
	if ( 'legenda' === $p->change_target && $p->stage !== lk_stage_for( 'aprovacao' ) ) {
		return 'revisao'; // Ajuste de legenda: prazo da revisão.
	}
	return isset( lk_prazo_roles()[ $role ] ) ? $role : '';
}

/**
 * Prazo da etapa atual ('' quando a etapa não tem prazo).
 */
function lk_post_deadline( $p ) {
	$role = lk_post_deadline_role( $p );
	if ( ! $role ) {
		return '';
	}
	return lk_post_deadlines( $p )[ $role ]['at'];
}

/**
 * Selo "entrega dd/mm HH:MM" da etapa atual (vermelho se passou, laranja se é hoje/amanhã).
 */
function lk_deadline_badge( $p, $prefix = 'entrega' ) {
	$at = lk_post_deadline( $p );
	if ( ! $at ) {
		return '';
	}
	$ts    = strtotime( $at );
	$now   = strtotime( lk_now() );
	$class = $ts < $now ? 'late' : ( $ts - $now < DAY_IN_SECONDS ? 'today' : ( $ts - $now < 2 * DAY_IN_SECONDS ? 'soon' : '' ) );
	$when  = gmdate( 'Y-m-d', $ts ) === lk_today() ? 'hoje ' . gmdate( 'H:i', $ts ) : lk_date( $at, 'd/m H:i' );
	return '<span class="due due--' . esc_attr( $class ) . '" title="Prazo da etapa atual">' . lk_icon( 'relogio', 13 ) . esc_html( $prefix . ' ' . $when ) . '</span>'; // phpcs:ignore
}

/**
 * Lê os campos de prazo do formulário do post e devolve o JSON com os que foram mudados à mão.
 */
function lk_prazos_from_form( $scheduled_at ) {
	$out = array();
	foreach ( array_keys( lk_prazo_roles() ) as $role ) {
		$v = isset( $_POST[ 'prazo_' . $role ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'prazo_' . $role ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- dentro de lk_do_post_save
		if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/', $v, $m ) ) {
			continue;
		}
		$at = $m[1] . ' ' . $m[2] . ':00';
		if ( $at !== lk_prazo_auto( $scheduled_at, $role ) ) {
			$out[ $role ] = $at;
		}
	}
	return $out ? wp_json_encode( $out ) : '';
}

/**
 * Campos de prazo no formulário do post.
 */
function lk_prazos_fields( $p ) {
	$dl    = $p ? lk_post_deadlines( $p ) : array();
	echo '<fieldset class="prazos-pick" data-prazos data-hora="' . esc_attr( lk_setting( 'prazo_hora' ) ? lk_setting( 'prazo_hora' ) : '18:00' ) . '">';
	echo '<legend class="small">Prazos de entrega <span class="muted">(automático: ' . esc_html( (int) lk_setting( 'prazo_design' ) . ', ' . (int) lk_setting( 'prazo_revisao' ) . ' e ' . (int) lk_setting( 'prazo_aprovacao' ) ) . ' dias antes da publicação)</span></legend><div class="grid-3">';
	foreach ( lk_prazo_roles() as $role => $label ) {
		$val = $dl && $dl[ $role ]['at'] ? str_replace( ' ', 'T', substr( $dl[ $role ]['at'], 0, 16 ) ) : '';
		echo '<label class="field"><span>' . esc_html( $label ) . ( $dl && $dl[ $role ]['manual'] ? ' <em class="badge">ajustado</em>' : '' ) . '</span><input type="datetime-local" name="prazo_' . esc_attr( $role ) . '" value="' . esc_attr( $val ) . '" data-prazo="' . esc_attr( $role ) . '" data-dias="' . (int) lk_setting( 'prazo_' . $role ) . '"' . ( $dl && $dl[ $role ]['manual'] ? ' data-touched="1"' : '' ) . '></label>';
	}
	echo '</div><small class="muted">Mudou a data da publicação? Os prazos que você não mexeu acompanham sozinhos.</small></fieldset>';
}

/**
 * Linha com os prazos na tela do post.
 */
function lk_prazos_line( $p ) {
	if ( ! $p->scheduled_at ) {
		return '';
	}
	$cur = lk_post_deadline_role( $p );
	$now = lk_now();
	$h   = '<div class="prazos-line">';
	foreach ( lk_post_deadlines( $p ) as $role => $d ) {
		if ( ! $d['at'] ) {
			continue;
		}
		$cls = $role === $cur ? ( $d['at'] < $now ? 'is-late' : 'is-now' ) : '';
		$h  .= '<span class="prazo ' . $cls . '"><small>' . esc_html( lk_prazo_roles()[ $role ] ) . '</small><strong>' . esc_html( lk_date( $d['at'], 'd/m H:i' ) ) . '</strong></span>';
	}
	$h .= '<span class="prazo prazo--pub"><small>Publicação</small><strong>' . esc_html( lk_date( $p->scheduled_at, 'd/m H:i' ) ) . '</strong></span></div>';
	return $h;
}

/**
 * Avisa o responsável quando o prazo está chegando (menos de 24 h) e quando atrasa. Uma vez só por prazo.
 */
add_action( 'lk_hourly', 'lk_prazos_avisos', 40 );
function lk_prazos_avisos() {
	$sent = get_option( 'lk_prazo_avisos', array() );
	$sent = is_array( $sent ) ? $sent : array();
	$now  = strtotime( lk_now() );
	$keep = array();
	foreach ( lk_posts( 'p.stage NOT IN (%s, %s) AND p.scheduled_at IS NOT NULL', array( lk_stage_for( 'agendado' ), lk_stage_for( 'publicado' ) ) ) as $p ) {
		$role = lk_post_deadline_role( $p );
		$at   = lk_post_deadline( $p );
		if ( ! $role || ! $at ) {
			continue;
		}
		$ts    = strtotime( $at );
		$owner = lk_post_owner( $p );
		$base  = $p->id . '|' . $role . '|' . $at;
		$url   = lk_panel_url( 'post', $p->id );
		if ( $ts > $now && $ts - $now <= DAY_IN_SECONDS ) {
			if ( empty( $sent[ $base . '|soon' ] ) ) {
				lk_notify( $owner, '⏰ Prazo chegando (' . lk_date( $at, 'd/m H:i' ) . '): ' . lk_prazo_roles()[ $role ] . ' · ' . $p->title, $url );
			}
			$keep[ $base . '|soon' ] = 1;
		} elseif ( $ts <= $now ) {
			if ( empty( $sent[ $base . '|late' ] ) ) {
				lk_notify( $owner, '🔴 Prazo vencido: ' . lk_prazo_roles()[ $role ] . ' · ' . $p->title, $url );
			}
			$keep[ $base . '|late' ] = 1;
			if ( ! empty( $sent[ $base . '|soon' ] ) ) {
				$keep[ $base . '|soon' ] = 1;
			}
		}
	}
	update_option( 'lk_prazo_avisos', $keep, false );
}
