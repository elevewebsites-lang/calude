<?php
/**
 * Avatares da equipe: cada pessoa escolhe um bicho ou emoji (fica no perfil dela).
 * Aparece na barra lateral, no chat, na equipe, no ranking, nas tarefas e no "bem-vindo" ao entrar.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_avatar_choices() {
	return array(
		'Bichos'    => array( '🦊', '🐼', '🐨', '🦁', '🐯', '🐸', '🐵', '🐰', '🐻', '🐶', '🐱', '🦉', '🐧', '🦄', '🐙', '🦋', '🐢', '🦖', '🐳', '🦈', '🐹', '🐮', '🐷', '🦒', '🦓', '🦔', '🐝', '🐞', '🦜', '🐘', '🦥', '🦦' ),
		'Carinhas'  => array( '😎', '🤓', '🥳', '🤩', '😄', '😂', '🥰', '🤠', '🧐', '😇', '🤖', '👻', '👽', '🎃', '😈', '🫡', '🤗', '😴', '🥸', '🤯' ),
	);
}

function lk_avatar_emoji( $user_id ) {
	$e = (string) get_user_meta( (int) $user_id, 'lk_avatar', true );
	if ( '' === $e ) {
		return '';
	}
	foreach ( lk_avatar_choices() as $list ) {
		if ( in_array( $e, $list, true ) ) {
			return $e;
		}
	}
	return '';
}

/** Texto para o círculo do avatar: o emoji escolhido ou as iniciais. */
function lk_user_badge( $user ) {
	$u = is_object( $user ) ? $user : get_userdata( (int) $user );
	if ( ! $u ) {
		return '';
	}
	$e = lk_avatar_emoji( $u->ID );
	return '' !== $e ? $e : lk_initials( $u->display_name );
}

/** Círculo do avatar em HTML. */
function lk_avatar_circle( $user, $class = 'avatar' ) {
	$u = is_object( $user ) ? $user : get_userdata( (int) $user );
	if ( ! $u ) {
		return '';
	}
	$e = lk_avatar_emoji( $u->ID );
	return '<span class="' . esc_attr( $class ) . ( $e ? ' avatar--emoji' : '' ) . '" title="' . esc_attr( $u->display_name ) . '">' . esc_html( $e ? $e : lk_initials( $u->display_name ) ) . '</span>';
}

function lk_do_avatar_save() {
	if ( ! lk_is_team() ) {
		wp_die( 'Sem permissão.' );
	}
	$uid = get_current_user_id();
	$e   = (string) lk_in( 'avatar', 'raw' );
	if ( '' === $e || 'iniciais' === $e ) {
		delete_user_meta( $uid, 'lk_avatar' );
		update_user_meta( $uid, 'lk_avatar_seen', 1 );
		lk_back( 'Avatar removido: volta a mostrar as suas iniciais.' );
	}
	$ok = false;
	foreach ( lk_avatar_choices() as $list ) {
		$ok = $ok || in_array( $e, $list, true );
	}
	if ( ! $ok ) {
		lk_back( 'Escolha um avatar da lista.', 'erro' );
	}
	update_user_meta( $uid, 'lk_avatar', $e );
	update_user_meta( $uid, 'lk_avatar_seen', 1 );
	lk_back( 'Avatar escolhido: ' . $e . ' Agora todo mundo da equipe vê você assim.' );
}

/** Quem acabou de entrar: guarda o aviso de boas-vindas e atualiza a presença. */
add_action(
	'set_logged_in_cookie',
	function ( $cookie, $expire, $expiration, $user_id ) {
		update_user_meta( (int) $user_id, 'lk_welcome', 1 );
	},
	10,
	4
);

/** "Visto por último" (no máximo 1 gravação por minuto) para mostrar quem está online. */
add_action(
	'template_redirect',
	function () {
		if ( is_user_logged_in() && get_query_var( 'lk_route' ) === 'panel' ) {
			$uid = get_current_user_id();
			if ( time() - (int) get_user_meta( $uid, 'lk_seen', true ) > 60 ) {
				update_user_meta( $uid, 'lk_seen', time() );
			}
		}
	},
	5
);

function lk_user_online( $user_id ) {
	return time() - (int) get_user_meta( (int) $user_id, 'lk_seen', true ) < 5 * MINUTE_IN_SECONDS;
}

/** Janela para escolher o avatar (usa a ação avatar_save). */
function lk_avatar_picker_html() {
	$me  = get_current_user_id();
	$cur = lk_avatar_emoji( $me );
	ob_start();
	lk_modal_start( 'avatar-pick', 'Escolha o seu avatar' );
	lk_form( 'avatar_save', 'stack avpick' );
	echo '<p class="muted small">Todo mundo da equipe vai ver você assim: no chat, nas tarefas, no ranking e nas menções. Dá para trocar quando quiser.</p>';
	foreach ( lk_avatar_choices() as $group => $list ) {
		echo '<h4 class="dc-zone">' . esc_html( $group ) . '</h4><div class="avpick-grid">';
		foreach ( $list as $e ) {
			echo '<label class="avpick-o"><input type="radio" name="avatar" value="' . esc_attr( $e ) . '"' . checked( $cur, $e, false ) . '><span>' . esc_html( $e ) . '</span></label>';
		}
		echo '</div>';
	}
	echo '<div class="form-actions"><button type="submit" name="avatar" value="iniciais" class="btn btn--link" formnovalidate>Usar só as iniciais</button><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Escolher</button></div></form>';
	lk_modal_end();
	return ob_get_clean();
}

/** Ao entrar: "bem-vindo" com o avatar. Quem ainda não escolheu avatar vê o convite para escolher. */
function lk_welcome_html() {
	if ( ! lk_is_team() ) {
		return '';
	}
	$uid  = get_current_user_id();
	$user = wp_get_current_user();
	$out  = '';
	if ( get_user_meta( $uid, 'lk_welcome', true ) ) {
		delete_user_meta( $uid, 'lk_welcome' );
		$hour = (int) current_time( 'G' );
		$hi   = $hour < 12 ? 'Bom dia' : ( $hour < 18 ? 'Boa tarde' : 'Boa noite' );
		$e    = lk_avatar_emoji( $uid );
		$out .= '<div class="welcome" data-welcome role="status"><span class="welcome-av">' . esc_html( $e ? $e : lk_initials( $user->display_name ) ) . '</span><span><strong>' . esc_html( $hi . ', ' . ( $user->first_name ? $user->first_name : $user->display_name ) ) . '!</strong><small>' . ( $e ? 'Que bom ter você por aqui.' : 'Que tal escolher um avatar? <button type="button" class="link-btn" data-open="avatar-pick">Escolher agora</button>' ) . '</small></span><button type="button" class="welcome-x" data-welcome-x aria-label="Fechar">×</button></div>';
	} elseif ( ! get_user_meta( $uid, 'lk_avatar_seen', true ) && ! lk_avatar_emoji( $uid ) ) {
		$out .= '<div class="welcome welcome--ask" data-welcome role="status"><span class="welcome-av">🙂</span><span><strong>Escolha o seu avatar!</strong><small>Bicho ou carinha: todo mundo da equipe vai ver você assim. <button type="button" class="link-btn" data-open="avatar-pick">Escolher agora</button></small></span><button type="button" class="welcome-x" data-welcome-x aria-label="Fechar">×</button></div>';
		update_user_meta( $uid, 'lk_avatar_seen', 1 ); // convida uma vez só
	}
	return $out . '<script>document.addEventListener("click",function(e){var x=e.target.closest("[data-welcome-x]");if(x)x.closest("[data-welcome]").remove();});setTimeout(function(){var w=document.querySelector("[data-welcome]:not(.welcome--ask)");if(w)w.classList.add("is-out");},6500);</script>';
}
