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
		'reunioes'       => array( 'Próximas reuniões', 'grid', '', true, false ),
		'formularios'    => array( 'Briefings e pesquisas', 'grid', 'clientes', false, false ),
		'trafego_resumo' => array( 'Tráfego pago (resumo do mês)', 'grid', 'trafego', false, false ),
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
	if ( in_array( $id, array( 'hero', 'perfil' ), true ) ) {
		return false; // perfil + demandas ficam sempre fixos no topo, fora da personalização
	}
	$w = lk_dash_widgets()[ $id ] ?? null;
	if ( ! $w || ! lk_is_team() || ! apply_filters( 'lk_dash_widget_allowed', true, $id ) ) {
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

/** id => array( largura padrão em colunas (de 12), largura MÍNIMA que não corta as informações ). */
function lk_dash_sizes() {
	return array(
		'hero' => array( 12, 6 ), 'stats' => array( 12, 6 ), 'chart' => array( 8, 6 ), 'etapas' => array( 4, 4 ), 'tarefas_semana' => array( 4, 4 ),
		'comigo' => array( 4, 4 ), 'hoje' => array( 4, 4 ), 'atrasados' => array( 4, 4 ), 'aguardando' => array( 4, 4 ), 'cobrancas' => array( 4, 4 ),
		'instagram' => array( 4, 4 ), 'reunioes' => array( 4, 4 ), 'datas' => array( 4, 4 ), 'ranking' => array( 4, 4 ), 'contratos' => array( 4, 4 ),
		'funil' => array( 4, 4 ), 'chat' => array( 4, 5 ), 'formularios' => array( 4, 4 ), 'trafego_resumo' => array( 4, 4 ),
		'perfil' => array( 4, 3 ), 'metas' => array( 4, 3 ), 'andamento' => array( 4, 3 ), 'clientes_ult' => array( 4, 3 ),
	);
}

/** Posição e tamanho que a pessoa deixou: order (ids) e size (id => w colunas, h linhas mínimas; 0 = altura automática). */
function lk_dash_layout() {
	$l = get_user_meta( get_current_user_id(), 'lk_dash_layout', true );
	return is_array( $l ) ? $l + array( 'order' => array(), 'size' => array() ) : array( 'order' => array(), 'size' => array() );
}

/** Largura (colunas), altura mínima (linhas) e largura mínima do bloco. */
function lk_dash_size( $id ) {
	$d = lk_dash_sizes()[ $id ] ?? array( 6, 4 );
	$s = lk_dash_layout()['size'][ $id ] ?? array();
	$w = isset( $s['w'] ) ? (int) $s['w'] : $d[0];
	return array( max( $d[1], min( 12, $w ) ), isset( $s['h'] ) ? max( 0, min( 40, (int) $s['h'] ) ) : 0, $d[1] );
}

/** Blocos visíveis na ordem do quadro: a que a pessoa arrumou; sem arrumação, principal → cartões → lateral. */
function lk_dash_board_ids() {
	$default = array();
	foreach ( array( 'main', 'grid', 'side' ) as $z ) {
		foreach ( lk_dash_zone( $z ) as $id ) {
			$default[] = $id;
		}
	}
	$order = lk_dash_layout()['order'];
	if ( ! $order ) {
		return $default;
	}
	$out = array_values( array_intersect( $order, $default ) );
	foreach ( $default as $id ) {
		if ( ! in_array( $id, $out, true ) ) {
			$out[] = $id;
		}
	}
	return $out;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route( 'lk/v1', '/dash-layout', array( 'methods' => 'POST', 'callback' => 'lk_api_dash_layout', 'permission_callback' => function () { return lk_is_team(); } ) );
	}
);

function lk_api_dash_layout( WP_REST_Request $r ) {
	$all   = array_filter( array_keys( lk_dash_widgets() ), 'lk_dash_allowed' );
	$order = array_values( array_intersect( array_map( 'sanitize_key', (array) $r->get_param( 'order' ) ), $all ) );
	$sizes = lk_dash_sizes();
	$size  = array();
	foreach ( (array) $r->get_param( 'size' ) as $id => $v ) {
		$id = sanitize_key( $id );
		if ( ! in_array( $id, $all, true ) || ! is_array( $v ) ) {
			continue;
		}
		$min        = $sizes[ $id ][1] ?? 3;
		$size[ $id ] = array( 'w' => max( $min, min( 12, (int) ( $v['w'] ?? 0 ) ) ), 'h' => max( 0, min( 40, (int) ( $v['h'] ?? 0 ) ) ) );
	}
	update_user_meta( get_current_user_id(), 'lk_dash_layout', array( 'order' => $order, 'size' => $size ) );
	// Remover / adicionar cartões (sem mexer nos outros que já estão ligados).
	$rm  = array_values( array_intersect( array_map( 'sanitize_key', (array) $r->get_param( 'remove' ) ), $all ) );
	$add = array_values( array_intersect( array_map( 'sanitize_key', (array) $r->get_param( 'add' ) ), $all ) );
	if ( $rm || $add ) {
		$p  = lk_dash_prefs();
		$on = is_array( $p['on'] ) ? $p['on'] : array_values( array_filter( array_keys( lk_dash_widgets() ), function ( $id ) { return lk_dash_allowed( $id ) && lk_dash_widgets()[ $id ][3]; } ) );
		$on = array_values( array_unique( array_merge( array_diff( $on, $rm ), $add ) ) );
		update_user_meta( get_current_user_id(), 'lk_dash', array( 'order' => $p['order'], 'on' => $on ) );
		if ( $add ) { // o cartão novo entra no fim do quadro
			$lay          = lk_dash_layout();
			$lay['order'] = array_values( array_unique( array_merge( array_diff( $lay['order'] ? $lay['order'] : $order, $rm ), $add ) ) );
			update_user_meta( get_current_user_id(), 'lk_dash_layout', $lay );
		}
	}
	return array( 'ok' => true );
}

function lk_do_dash_save() {
	if ( ! lk_is_team() ) {
		wp_die( 'Sem permissão.' );
	}
	$all   = array_filter( array_keys( lk_dash_widgets() ), 'lk_dash_allowed' );
	$order = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $_POST['w'] ?? array() ) ), $all ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$on    = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $_POST['on'] ?? array() ) ), $all ) ); // phpcs:ignore WordPress.Security.NonceVerification
	update_user_meta( get_current_user_id(), 'lk_dash', array( 'order' => $order, 'on' => $on ) );
	$lay          = lk_dash_layout();
	$lay['order'] = array(); // a ordem do quadro volta a seguir esta lista; os tamanhos continuam
	update_user_meta( get_current_user_id(), 'lk_dash_layout', $lay );
	lk_back( 'Dashboard personalizado.' );
}

function lk_do_dash_reset() {
	if ( ! lk_is_team() ) {
		wp_die( 'Sem permissão.' );
	}
	delete_user_meta( get_current_user_id(), 'lk_dash' );
	delete_user_meta( get_current_user_id(), 'lk_dash_layout' );
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
