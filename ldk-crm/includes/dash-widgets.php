<?php
/**
 * Dashboard personalizável: cada pessoa escolhe o que aparece e em que ordem (dentro de cada área).
 * Só entram os blocos que a permissão dela permite. A preferência fica no usuário (meta lk_dash).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** id => array( rótulo, zona (main|grid|side), permissão ('' = equipe, 'admin' ou área), padrão ligado, meia largura ). */
function lk_dash_widgets() {
	return array(
		'hero'           => array( 'Boas-vindas e progresso do mês', 'main', '', true, false ),
		'stats'          => array( 'Números principais', 'main', '', true, false ),
		'chart'          => array( 'Gráfico de publicações', 'main', 'conteudo', true, false ),
		'etapas'         => array( 'Posts por etapa (rosca)', 'main', 'conteudo', true, true ),
		'tarefas_semana' => array( 'Minhas tarefas da semana', 'main', '', true, true ),
		'comigo'         => array( 'Comigo agora (lista)', 'grid', '', true, false ),
		'hoje'           => array( 'Publicações de hoje', 'grid', 'conteudo', true, false ),
		'atrasados'      => array( 'Atrasados', 'grid', 'conteudo', true, false ),
		'aguardando'     => array( 'Aguardando o cliente', 'grid', 'conteudo', true, false ),
		'cobrancas'      => array( 'Cobranças', 'grid', 'financeiro', true, false ),
		'instagram'      => array( 'Instagram não vinculado', 'grid', 'clientes', true, false ),
		'datas'          => array( 'Datas e feriados próximos', 'grid', 'conteudo', true, false ),
		'ranking'        => array( 'Ranking do mês', 'grid', '', false, false ),
		'contratos'      => array( 'Contratos pendentes', 'grid', 'clientes', false, false ),
		'funil'          => array( 'Funil de leads', 'grid', 'leads', false, false ),
		'chat'           => array( 'Chat da equipe', 'grid', '', true, false ),
		'perfil'         => array( 'Meu perfil e nível', 'side', '', true, false ),
		'metas'          => array( 'Metas da agência', 'side', 'admin', true, false ),
		'andamento'      => array( 'Em andamento comigo', 'side', '', true, false ),
		'clientes_ult'   => array( 'Últimos clientes', 'side', 'clientes', true, false ),
	);
}

function lk_dash_zones() {
	return array( 'main' => 'Principal', 'grid' => 'Cartões', 'side' => 'Coluna lateral' );
}

function lk_dash_allowed( $id ) {
	$w = lk_dash_widgets()[ $id ] ?? null;
	if ( ! $w || ! lk_is_team() ) {
		return false;
	}
	if ( '' === $w[2] ) {
		return true;
	}
	return 'admin' === $w[2] ? lk_is_admin() : lk_can( $w[2] );
}

function lk_dash_prefs( $user_id = 0 ) {
	$p = get_user_meta( $user_id ? $user_id : get_current_user_id(), 'lk_dash', true );
	return is_array( $p ) ? $p + array( 'order' => array(), 'on' => null ) : array( 'order' => array(), 'on' => null );
}

/** O bloco aparece para esta pessoa? (permitido e escolhido; sem escolha salva usa o padrão). */
function lk_dash_is_on( $id ) {
	if ( ! lk_dash_allowed( $id ) ) {
		return false;
	}
	$p = lk_dash_prefs();
	if ( is_array( $p['on'] ) ) {
		return in_array( $id, $p['on'], true );
	}
	return (bool) lk_dash_widgets()[ $id ][3];
}

/** Ids de uma zona, na ordem da pessoa. $only_on = só os que aparecem (senão todos os permitidos). */
function lk_dash_zone( $zone, $only_on = true ) {
	$ids = array();
	foreach ( lk_dash_widgets() as $id => $w ) {
		if ( $w[1] === $zone && lk_dash_allowed( $id ) && ( ! $only_on || lk_dash_is_on( $id ) ) ) {
			$ids[] = $id;
		}
	}
	$order = lk_dash_prefs()['order'];
	usort(
		$ids,
		function ( $a, $b ) use ( $order ) {
			$ia = array_search( $a, $order, true );
			$ib = array_search( $b, $order, true );
			$ia = false === $ia ? 999 : $ia;
			$ib = false === $ib ? 999 : $ib;
			return $ia <=> $ib;
		}
	);
	return $ids;
}

function lk_do_dash_save() {
	if ( ! lk_is_team() ) {
		wp_die( 'Sem permissão.' );
	}
	$all   = array_filter( array_keys( lk_dash_widgets() ), 'lk_dash_allowed' );
	$order = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $_POST['w'] ?? array() ) ), $all ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$on    = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $_POST['on'] ?? array() ) ), $all ) ); // phpcs:ignore WordPress.Security.NonceVerification
	update_user_meta( get_current_user_id(), 'lk_dash', array( 'order' => $order, 'on' => $on ) );
	lk_back( 'Dashboard personalizado.' );
}

function lk_do_dash_reset() {
	if ( ! lk_is_team() ) {
		wp_die( 'Sem permissão.' );
	}
	delete_user_meta( get_current_user_id(), 'lk_dash' );
	lk_back( 'Dashboard voltou ao padrão.' );
}

/** Janela "Personalizar" (checkbox + ordem por zona). */
function lk_dash_customize_html() {
	ob_start();
	lk_modal_start( 'dash-custom', 'Personalizar o dashboard' );
	lk_form( 'dash_save', 'stack dash-custom' );
	echo '<p class="muted small">Marque o que você quer ver e use as setas para mudar a ordem. Só aparecem os blocos que o seu acesso permite.</p>';
	foreach ( lk_dash_zones() as $zone => $zl ) {
		$ids = lk_dash_zone( $zone, false );
		if ( ! $ids ) {
			continue;
		}
		echo '<h4 class="dc-zone">' . esc_html( $zl ) . '</h4><ul class="dc-list" data-dc-list>';
		foreach ( $ids as $id ) {
			echo '<li><label class="check"><input type="checkbox" name="on[]" value="' . esc_attr( $id ) . '"' . checked( lk_dash_is_on( $id ), true, false ) . '><span>' . esc_html( lk_dash_widgets()[ $id ][0] ) . '</span></label><input type="hidden" name="w[]" value="' . esc_attr( $id ) . '"><span class="dc-mv"><button type="button" data-dc-up aria-label="Subir">↑</button><button type="button" data-dc-down aria-label="Descer">↓</button></span></li>';
		}
		echo '</ul>';
	}
	echo '<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button></div></form>';
	lk_action_button( 'dash_reset', array(), 'Restaurar o padrão', 'btn btn--link btn--sm', 'Voltar o dashboard ao padrão?' );
	lk_modal_end();
	echo '<script>document.addEventListener("click",function(e){var b=e.target.closest("[data-dc-up],[data-dc-down]");if(!b)return;var li=b.closest("li");var up=b.hasAttribute("data-dc-up");var sib=up?li.previousElementSibling:li.nextElementSibling;if(sib)li.parentNode.insertBefore(up?li:sib,up?sib:li);});</script>';
	return ob_get_clean();
}
