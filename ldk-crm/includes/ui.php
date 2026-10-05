<?php
/**
 * Pedaços de interface usados pelas telas.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_head( $title, $themed = false ) {
	?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<?php if ( $themed ) : ?>
<script>(function(){try{var t=localStorage.getItem('lk-theme')||'claro';if(t==='sistema'){t=window.matchMedia('(prefers-color-scheme: dark)').matches?'escuro':'claro';}document.documentElement.setAttribute('data-theme',t==='escuro'?'dark':'light');if(localStorage.getItem('lk-hide-money')==='1'){document.documentElement.setAttribute('data-hide-money','');}}catch(e){}})();</script>
<?php endif; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0a0a0a">
<title><?php echo esc_html( $title . ' · ' . lk_setting( 'empresa' ) ); ?></title>
<link rel="icon" href="<?php echo esc_url( lk_setting( 'favicon' ) ); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php lk_theme_head( 'app.css' ); ?>
<?php if ( $themed ) : ?><link rel="stylesheet" href="<?php echo esc_url( LK_URL . 'assets/feedback.css?ver=' . LK_VERSION ); ?>"><?php endif; ?>
</head>
	<?php
}

/**
 * Topo das páginas do cliente (entrega e relatório): a logo do cliente, se tiver;
 * senão, a da Eleve. A da Eleve aparece sempre no canto inferior (lk_made_by).
 */
function lk_brand_top( $client ) {
	if ( $client && ! empty( $client->logo ) ) {
		echo '<span class="brand-plate"><img src="' . esc_url( $client->logo ) . '" alt="' . esc_attr( lk_client_label( $client ) ) . '"></span>';
		return;
	}
	if ( lk_setting( 'logo' ) ) {
		echo '<img class="dv-logo" src="' . esc_url( lk_setting( 'logo' ) ) . '" alt="' . esc_attr( lk_setting( 'empresa' ) ) . '">';
	}
}

function lk_made_by() {
	if ( ! lk_setting( 'logo' ) ) {
		return;
	}
	echo '<a class="made-by" href="https://elevewebsites.com.br" target="_blank" rel="noopener"><span>feito por</span><img src="' . esc_url( lk_setting( 'logo' ) ) . '" alt="' . esc_attr( lk_setting( 'empresa' ) ) . '"></a>';
}

/**
 * Botão do tema: claro → escuro → igual ao sistema. Fica guardado neste aparelho.
 */
function lk_theme_button() {
	echo '<button type="button" class="theme-btn" data-theme-toggle title="Tema: claro, escuro ou igual ao sistema" aria-label="Trocar tema">'
		. '<span class="theme-ic theme-ic--light">' . lk_icon( 'sol', 18 ) . '</span>' // phpcs:ignore
		. '<span class="theme-ic theme-ic--dark">' . lk_icon( 'lua', 18 ) . '</span>' // phpcs:ignore
		. '<span class="theme-label" data-theme-label></span></button>';
}

/**
 * Olhinho: esconde (ou mostra) todos os valores em R$ do painel. Fica guardado neste aparelho.
 */
function lk_money_button( $class = '', $label = '' ) {
	echo '<button type="button" class="money-btn ' . esc_attr( $class ) . '" data-money-toggle title="Esconder ou mostrar os valores em R$" aria-label="Esconder ou mostrar os valores">'
		. '<span class="money-ic money-ic--on">' . lk_icon( 'olho', 18 ) . '</span>' // phpcs:ignore
		. '<span class="money-ic money-ic--off">' . lk_icon( 'olho-off', 18 ) . '</span>' . ( $label ? '<span class="money-label">' . esc_html( $label ) . '</span>' : '' ) . '</button>'; // phpcs:ignore
}

function lk_money_button_html() {
	ob_start();
	lk_money_button( 'btn btn--ghost btn--icon' );
	return ob_get_clean();
}

function lk_scripts() {
	$data = array(
		'rest'  => esc_url_raw( rest_url( 'lk/v1/' ) ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
	);
	if ( lk_is_team() && get_query_var( 'lk_route' ) === 'panel' ) {
		$data['commands'] = lk_quick_commands();
	}
	echo '<script>window.LK = ' . wp_json_encode( $data ) . ';</script>';
	echo '<script src="' . esc_url( LK_URL . 'assets/app.js?ver=' . LK_VERSION ) . '"></script>';
	if ( is_user_logged_in() ) {
		echo '<script src="' . esc_url( LK_URL . 'assets/chat.js?ver=' . LK_VERSION ) . '"></script>';
		if ( lk_is_team() ) {
			echo '<script src="' . esc_url( LK_URL . 'assets/team.js?ver=' . LK_VERSION ) . '"></script>';
			if ( get_query_var( 'lk_route' ) === 'panel' ) {
				echo '<script src="' . esc_url( LK_URL . 'assets/hub.js?ver=' . LK_VERSION ) . '"></script>';
				echo '<script src="' . esc_url( LK_URL . 'assets/revisao.js?ver=' . LK_VERSION ) . '"></script>';
			}
		}
	}
	lk_feedback_script();
}

/**
 * Abre o painel: menu lateral + barra do topo.
 */
function lk_panel_start( $title, $active = '', $actions = '' ) {
	lk_head( $title, true );
	$user  = wp_get_current_user();
	$items = array(
		'Operação' => array(
			''          => array( 'Dashboard', 'dashboard', '' ),
			'conteudo'  => array( 'Conteúdo', 'projetos', 'conteudo' ),
			'planejamento' => array( 'Planejamento do mês', 'lista', 'conteudo' ),
			'time'      => array( 'Equipe e demandas', 'equipe', '' ),
			'tarefas'   => array( 'Tarefas', 'tarefas', 'tarefas' ),
			'foco'      => array( 'Modo foco', 'alvo', '' ),
			'chat'      => array( 'Chat da equipe', 'chat', '' ),
			'ranking'   => array( 'Ranking e prêmios', 'alvo', '' ),
			'apontamentos' => array( 'Apontamentos', 'alvo', '' ),
		),
		'Clientes' => array(
			'clientes'   => array( 'Clientes', 'clientes', 'clientes' ),
			'contratos'  => array( 'Contratos', 'proposta', 'clientes' ),
			'redes'      => array( 'Redes conectadas', 'globo', 'clientes' ),
			'mensagens'  => array( 'Mensagens', 'chat', 'clientes' ),
			'agenda'     => array( 'Agenda', 'relogio', 'clientes' ),
			'relatorios' => array( 'Relatórios', 'grafico', 'relatorios' ),
			'trafego'    => array( 'Tráfego pago', 'funil', 'trafego' ),
		),
		'Comercial' => array(
			'leads'      => array( 'Funil de leads', 'funil', 'leads' ),
			'propostas'  => array( 'Propostas', 'proposta', 'orcamentos' ),
			'propostas-servicos' => array( 'Serviços das propostas', 'lista', 'orcamentos' ),
			'prospeccao' => array( 'Prospecção', 'busca', 'leads' ),
			'marketing'  => array( 'Ideias de conteúdo', 'lampada', 'leads' ),
			'emails'     => array( 'E-mails para clientes', 'email', 'emails' ),
		),
		'Financeiro' => array(
			'cobrancas'  => array( 'Mensalidades e cobranças', 'financeiro', 'financeiro' ),
			'financeiro' => array( 'Financeiro', 'grafico', 'financeiro' ),
		),
		'Ajuda' => array(
			'ajuda'      => array( 'Tutorial', 'lampada', '' ),
			'novidades'  => array( 'Novidades', 'sino', '' ),
			'feedback'   => array( 'Feedback', 'chat', '' ),
		),
	);
	if ( ! lk_game_on() ) {
		unset( $items['Operação']['ranking'] );
	}
	$today_count = lk_count_today_tasks();
	$fb_open     = lk_feedback_open_count();
	// Apontamentos: some do menu quando o botão está desligado e não sobrou nenhum aberto.
	if ( '1' !== (string) lk_setting( 'apontamentos' ) && ! $fb_open ) {
		unset( $items['Operação']['apontamentos'] );
	}
	?>
<body class="lk lk-panel">
<div class="app">
	<aside class="side" id="side">
		<a class="side-brand" href="<?php echo esc_url( lk_panel_url() ); ?>">
			<?php if ( lk_setting( 'logo' ) ) : ?><img src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt="<?php echo esc_attr( lk_setting( 'empresa' ) ); ?>"><?php endif; ?>
		</a>
		<button type="button" class="side-search" data-search-open><?php echo lk_icon( 'busca', 16 ); // phpcs:ignore ?><span>Buscar…</span><kbd>⌘K</kbd></button>
		<nav class="side-nav">
			<?php foreach ( $items as $group => $links ) : ?>
				<?php
				$visible = array_filter(
					array_keys( $links ),
					function ( $slug ) use ( $links ) {
						return ! lk_page_off( $slug ) && ( ! $links[ $slug ][2] || lk_can( $links[ $slug ][2] ) || ( 'tarefas' === $links[ $slug ][2] && lk_is_team() ) );
					}
				);
				if ( ! $visible ) {
					continue;
				}
				?>
				<span class="side-label"><?php echo esc_html( $group ); ?></span>
				<?php foreach ( $links as $slug => $it ) : ?>
					<?php
					if ( lk_page_off( $slug ) || ( $it[2] && ! lk_can( $it[2] ) && ! ( 'tarefas' === $it[2] && lk_is_team() ) ) ) {
						continue;
					}
					?>
					<a href="<?php echo esc_url( lk_panel_url( $slug ) ); ?>" class="<?php echo $active === $slug ? 'is-active' : ''; ?>">
						<?php echo lk_icon( $it[1] ); // phpcs:ignore ?><span><?php echo esc_html( $it[0] ); ?></span>
						<?php if ( 'tarefas' === $slug && $today_count ) : ?><em class="side-count"><?php echo (int) $today_count; ?></em><?php endif; ?>
						<?php if ( 'novidades' === $slug && lk_changelog_unseen() ) : ?><em class="side-count side-count--new">novo</em><?php endif; ?>
						<?php if ( 'feedback' === $slug && lk_is_admin() && lk_feedback_open_mine() ) : ?><em class="side-count"><?php echo (int) lk_feedback_open_mine(); ?></em><?php endif; ?>
						<?php if ( 'apontamentos' === $slug ) : ?><em class="side-count" data-fb-count<?php echo $fb_open ? '' : ' hidden'; ?>><?php echo (int) $fb_open; ?></em><?php endif; ?>
					</a>
				<?php endforeach; ?>
			<?php endforeach; ?>
			<?php if ( lk_is_admin() ) : ?>
				<span class="side-label">Admin</span>
				<a href="<?php echo esc_url( lk_panel_url( 'conta' ) ); ?>" class="<?php echo 'conta' === $active ? 'is-active' : ''; ?>"><?php echo lk_icon( 'config' ); // phpcs:ignore ?><span>Minha conta e senha</span></a>
				<a href="<?php echo esc_url( lk_panel_url( 'equipe' ) ); ?>" class="<?php echo 'equipe' === $active ? 'is-active' : ''; ?>"><?php echo lk_icon( 'equipe' ); // phpcs:ignore ?><span>Equipe</span></a>
				<a href="<?php echo esc_url( lk_panel_url( 'metas' ) ); ?>" class="<?php echo 'metas' === $active ? 'is-active' : ''; ?>"><?php echo lk_icon( 'alvo' ); // phpcs:ignore ?><span>Metas</span></a>
				<a href="<?php echo esc_url( lk_panel_url( 'gamificacao' ) ); ?>" class="<?php echo 'gamificacao' === $active ? 'is-active' : ''; ?>"><?php echo lk_icon( 'alvo' ); // phpcs:ignore ?><span>Gamificação</span></a>
				<a href="<?php echo esc_url( lk_panel_url( 'config' ) ); ?>" class="<?php echo 'config' === $active ? 'is-active' : ''; ?>"><?php echo lk_icon( 'config' ); // phpcs:ignore ?><span>Configurações</span></a>
				<a href="<?php echo esc_url( lk_panel_url( 'saude' ) ); ?>" class="<?php echo 'saude' === $active ? 'is-active' : ''; ?>"><?php echo lk_icon( 'pulso' ); // phpcs:ignore ?><span>Saúde do sistema</span><?php $hb = lk_health_bad_count(); if ( $hb ) : ?><em class="side-count"><?php echo (int) $hb; ?></em><?php endif; ?></a>
			<?php endif; ?>
		</nav>
		<div class="side-tools">
			<?php lk_money_button( 'side-tool', 'Valores' ); ?>
			<?php lk_theme_button(); ?>
		</div>
		<div class="side-user">
			<span class="avatar"><?php echo esc_html( lk_initials( $user->display_name ) ); ?></span>
			<a class="side-user-name" href="<?php echo esc_url( lk_panel_url( 'conta' ) ); ?>" title="Minha conta e senha"><?php echo esc_html( $user->display_name ); ?><small><?php echo lk_is_admin() ? 'Administrador' : 'Equipe'; ?> · minha conta</small></a>
			<a href="<?php echo esc_url( lk_url( 'sair' ) ); ?>" title="Sair" class="side-out"><?php echo lk_icon( 'sair', 17 ); // phpcs:ignore ?></a>
		</div>
		<?php echo lk_credit_html( 'dark' ); // phpcs:ignore ?>
	</aside>
	<div class="side-backdrop" data-side-close></div>
	<main class="main">
		<header class="top">
			<button type="button" class="top-menu" data-side-open aria-label="Menu"><?php echo lk_icon( 'menu', 20 ); // phpcs:ignore ?></button>
			<h1 class="top-title"><?php echo esc_html( $title ); ?></h1>
			<div class="top-actions"><?php echo $actions; // phpcs:ignore -- HTML montado pelas telas com esc_*. ?><?php echo lk_team_bell_html(); // phpcs:ignore ?></div>
		</header>
		<div class="content">
			<?php lk_flash_html(); ?>
	<?php
}

function lk_panel_end() {
	?>
		</div>
	</main>
</div>
<div class="search" id="search" hidden>
	<div class="search-box">
		<div class="search-input"><?php echo lk_icon( 'busca', 18 ); // phpcs:ignore ?><input type="text" placeholder="Buscar cliente, post, tarefa, proposta… ou digite um comando" autocomplete="off"><kbd>Esc</kbd></div>
		<div class="search-results"></div>
	</div>
</div>
	<?php
	if ( lk_is_team() ) {
		echo lk_hub_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	lk_scripts();
	if ( lk_is_team() && function_exists( 'lk_celebrate_html' ) ) {
		echo lk_celebrate_html(); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</body></html>';
}

/**
 * Área do cliente: topo simples com a marca.
 */
function lk_client_start( $title, $client ) {
	lk_head( $title, true );
	$preview = lk_is_team();
	?>
<body class="lk lk-client">
	<?php if ( $preview ) : ?>
		<div class="preview-bar">Você está vendo a área do cliente como <strong><?php echo esc_html( lk_client_label( $client ) ); ?></strong>. <a href="<?php echo esc_url( lk_panel_url( 'cliente', $client->id ) ); ?>">Voltar ao painel</a></div>
	<?php endif; ?>
<header class="ctop">
	<div class="ctop-inner">
		<a class="ctop-brand" href="<?php echo esc_url( lk_client_link() ); ?>"><?php if ( lk_setting( 'logo' ) ) : ?><img src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt="<?php echo esc_attr( lk_setting( 'empresa' ) ); ?>"><?php endif; ?><span>Área do cliente</span></a>
		<span class="ctop-who"><?php echo lk_client_avatar_html( $client, 'avatar avatar--sm' ); // phpcs:ignore ?><b><?php echo esc_html( lk_client_label( $client ) ); ?></b></span>
		<nav class="ctop-nav">
			<a href="<?php echo esc_url( lk_client_link() ); ?>">Início</a>
			<a href="<?php echo esc_url( lk_client_link( 'conteudos' ) ); ?>">Conteúdos</a>
			<a href="<?php echo esc_url( lk_client_link( 'aprovacoes' ) ); ?>">Aprovações</a>
			<a href="<?php echo esc_url( lk_client_link( 'briefing' ) ); ?>">Briefing</a>
			<a href="<?php echo esc_url( lk_client_link( 'contratos' ) ); ?>">Contratos</a>
			<a href="<?php echo esc_url( lk_client_link( 'relatorios' ) ); ?>">Relatórios</a>
			<a href="<?php echo esc_url( lk_client_link( 'mensagens' ) ); ?>">Mensagens</a>
			<a href="<?php echo esc_url( lk_client_link( 'perfil' ) ); ?>">Meus dados</a>
			<?php echo lk_client_alerts_html( $client ); // phpcs:ignore ?>
			<?php echo $preview ? '' : lk_bell_html(); // phpcs:ignore ?>
			<?php lk_theme_button(); ?>
			<?php if ( ! $preview ) : ?><a href="<?php echo esc_url( lk_url( 'sair' ) ); ?>" class="ctop-out"><?php echo lk_icon( 'sair', 16 ); // phpcs:ignore ?> Sair</a><?php endif; ?>
		</nav>
	</div>
</header>
<main class="cmain">
	<?php lk_flash_html(); ?>
	<?php
}

function lk_client_end() {
	$wa = lk_setting( 'whatsapp' );
	echo '</main><footer class="cfoot">' . lk_credit_html( 'light' ) . '</footer>'; // phpcs:ignore
	if ( $wa ) {
		echo '<a class="wa-float" href="' . esc_url( lk_wa_link( $wa, 'Olá! Sou cliente da LDK e preciso de ajuda.' ) ) . '" target="_blank" rel="noopener" aria-label="Falar no WhatsApp">' . lk_icon( 'whatsapp', 24 ) . '</a>'; // phpcs:ignore
	}
	lk_scripts();
	echo '</body></html>';
}

function lk_flash_html() {
	if ( function_exists( 'lk_game_toast_html' ) ) {
		echo lk_game_toast_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- já escapado lá.
	}
	$flash = lk_take_flash();
	if ( $flash ) {
		echo '<div class="flash flash--' . esc_attr( $flash[1] ) . '" role="status">' . esc_html( $flash[0] ) . '</div>';
	}

}

/**
 * Abre um formulário que envia para lk_do_<acao>.
 */
function lk_form( $do, $class = '', $upload = false ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="' . esc_attr( $class ) . '"' . ( $upload ? ' enctype="multipart/form-data"' : '' ) . '>';
	echo '<input type="hidden" name="action" value="lk"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
	wp_nonce_field( 'lk_' . $do );
}

/**
 * Botão que envia uma ação com um id (ex.: excluir), com confirmação opcional.
 */
function lk_action_button( $do, $fields, $label, $class = 'btn btn--ghost btn--sm', $confirm = '' ) {
	lk_form( $do, 'inline-form' );
	foreach ( $fields as $k => $v ) {
		echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	echo '<button type="submit" class="' . esc_attr( $class ) . '"' . ( $confirm ? ' data-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>' . $label . '</button></form>'; // phpcs:ignore -- $label pode conter ícone SVG fixo.
}

/**
 * Campo com rótulo.
 */
function lk_input( $name, $label, $value = '', $type = 'text', $attrs = '' ) {
	$id = 'f_' . preg_replace( '/[^a-z0-9_]/', '_', strtolower( $name ) ) . '_' . wp_rand( 100, 999 );
	echo '<label class="field" for="' . esc_attr( $id ) . '"><span>' . esc_html( $label ) . '</span>';
	if ( 'textarea' === $type ) {
		echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" ' . $attrs . '>' . esc_textarea( $value ) . '</textarea>'; // phpcs:ignore
	} else {
		echo '<input id="' . esc_attr( $id ) . '" type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" ' . $attrs . '>'; // phpcs:ignore
	}
	echo '</label>';
}

function lk_select( $name, $label, $options, $selected = '', $attrs = '' ) {
	echo '<label class="field"><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '" ' . $attrs . '>'; // phpcs:ignore
	foreach ( $options as $value => $text ) {
		echo '<option value="' . esc_attr( $value ) . '"' . selected( (string) $selected, (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
	}
	echo '</select></label>';
}

function lk_check( $name, $label, $checked = false ) {
	echo '<label class="check"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $checked, true, false ) . '><span>' . esc_html( $label ) . '</span></label>';
}

function lk_check_named( $name, $value, $label, $checked = false, $disabled = false ) {
	echo '<label class="check"><input type="checkbox" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . checked( (bool) $checked, true, false ) . ( $disabled ? ' disabled' : '' ) . '><span>' . esc_html( $label ) . '</span></label>';
}

/* -----------------------------------------------------------------------
 * Selos
 * -------------------------------------------------------------------- */

function lk_priority_badge( $priority ) {
	$list = lk_priorities();
	if ( 'normal' === $priority || ! isset( $list[ $priority ] ) ) {
		return '';
	}
	return '<span class="badge badge--' . esc_attr( $priority ) . '">' . esc_html( $list[ $priority ] ) . '</span>';
}

function lk_due_badge( $date, $done = false ) {
	if ( ! $date ) {
		return '';
	}
	$days  = lk_days_until( $date );
	$class = $done ? '' : ( $days < 0 ? 'late' : ( 0 === $days ? 'today' : ( $days <= 2 ? 'soon' : '' ) ) );
	$text  = 0 === $days ? 'Hoje' : ( 1 === $days ? 'Amanhã' : ( -1 === $days ? 'Ontem' : lk_date( $date, 'd/m' ) ) );
	return '<span class="due due--' . esc_attr( $class ) . '">' . lk_icon( 'relogio', 13 ) . esc_html( $text ) . '</span>'; // phpcs:ignore
}

function lk_status_badge( $status ) {
	$map = array(
		'pago'      => array( 'Pago', 'ok' ),
		'pendente'  => array( 'Pendente', 'warn' ),
		'atrasado'  => array( 'Atrasado', 'late' ),
		'ativo'     => array( 'Ativo', 'ok' ),
		'convidado' => array( 'Convite enviado', 'warn' ),
	);
	$m = isset( $map[ $status ] ) ? $map[ $status ] : array( $status, '' );
	return '<span class="pill pill--' . esc_attr( $m[1] ) . '">' . esc_html( $m[0] ) . '</span>';
}

/**
 * Avatar do cliente: a logo (se tiver) no lugar das iniciais. $fallback_url = foto alternativa (ex.: Instagram).
 */
function lk_client_avatar_html( $c, $class = 'avatar', $fallback_url = '' ) {
	$logo = ! empty( $c->logo ) ? $c->logo : '';
	if ( $logo ) {
		return '<span class="' . esc_attr( $class ) . ' avatar--logo"><img src="' . esc_url( $logo ) . '" alt="Logo de ' . esc_attr( lk_client_label( $c ) ) . '" loading="lazy"></span>';
	}
	$ini = esc_html( lk_initials( lk_client_label( $c ) ) );
	return '<span class="' . esc_attr( $class ) . '">' . $ini . ( $fallback_url ? '<img src="' . esc_url( $fallback_url ) . '" alt="" onerror="this.remove()">' : '' ) . '</span>';
}

function lk_avatar( $user_id, $size = 'sm' ) {
	$user = $user_id ? get_userdata( $user_id ) : null;
	if ( ! $user ) {
		return '';
	}
	return '<span class="avatar avatar--' . esc_attr( $size ) . '" title="' . esc_attr( $user->display_name ) . '">' . esc_html( lk_initials( $user->display_name ) ) . '</span>';
}

function lk_progress_bar( $pct ) {
	return '<div class="progress"><span style="width:' . (int) $pct . '%"></span></div>';
}

/**
 * Tarefas pendentes para hoje (e atrasadas) do usuário, para o contador do menu.
 */
function lk_count_today_tasks() {
	global $wpdb;
	$where = "status <> 'done' AND due_date IS NOT NULL AND due_date <= %s";
	$args  = array( lk_today() );
	if ( ! lk_is_admin() ) {
		$where .= ' AND assignee = %d';
		$args[] = get_current_user_id();
	}
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . lk_table( 'tasks' ) . ' WHERE ' . $where, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Status real de um lançamento (pendente vencido = atrasado).
 */
function lk_trans_status( $t ) {
	if ( 'pago' === $t->status ) {
		return 'pago';
	}
	return $t->due_date && $t->due_date < lk_today() ? 'atrasado' : 'pendente';
}

/**
 * Opções de clientes / projetos / equipe para selects.
 */
function lk_client_options( $empty = 'Selecione…' ) {
	$out = array( '' => $empty );
	foreach ( lk_clients() as $c ) {
		$out[ $c->id ] = lk_client_label( $c );
	}
	return $out;
}

/**
 * Campo "Já é cliente?": escolher preenche nome, empresa, e-mail e WhatsApp (data-client-fill).
 */
function lk_existing_client_select( $selected = 0 ) {
	$map = array();
	foreach ( lk_clients() as $c ) {
		$map[ $c->id ] = array( 'name' => $c->name, 'company' => $c->company, 'email' => $c->email, 'whatsapp' => $c->whatsapp );
	}
	echo '<label class="field field--client"><span>Já é cliente?</span><select name="client_id" data-client-fill="' . esc_attr( wp_json_encode( $map ) ) . '">';
	echo '<option value="">Não, é um contato novo</option>';
	foreach ( lk_clients() as $c ) {
		echo '<option value="' . (int) $c->id . '"' . selected( (int) $selected, (int) $c->id, false ) . '>' . esc_html( lk_client_label( $c ) ) . '</option>';
	}
	echo '</select></label>';
}

function lk_project_options( $empty = 'Sem pedido (tarefa do estúdio)' ) {
	$out = array( '' => $empty );
	foreach ( lk_projects( 'p.archived = 0' ) as $p ) {
		$out[ $p->id ] = $p->title;
	}
	return $out;
}

function lk_team_options( $empty = 'Ninguém' ) {
	$out = array( '' => $empty );
	foreach ( lk_team_users() as $u ) {
		$f               = lk_user_role_label( $u->ID );
		$out[ $u->ID ] = $u->display_name . ( $f ? ' · ' . $f : '' );
	}
	return $out;
}

function lk_list_options( $setting ) {
	$out = array();
	foreach ( lk_list( lk_setting( $setting ) ) as $v ) {
		$out[ $v ] = $v;
	}
	return $out;
}

/**
 * Janela (modal) nativa: <dialog>. Abra com data-open="id".
 */
function lk_modal_start( $id, $title ) {
	echo '<dialog class="modal" id="' . esc_attr( $id ) . '"><div class="modal-head"><h3>' . esc_html( $title ) . '</h3><button type="button" class="modal-x" data-close aria-label="Fechar">×</button></div><div class="modal-body">';
}

function lk_modal_end() {
	echo '</div></dialog>';
}

/**
 * Tipos de acesso sugeridos na aba Acessos.
 */
function lk_access_types() {
	return array( 'Registro.br', 'Hospedagem', 'WordPress (painel do site)', 'Cloudflare', 'E-mail', 'Google (Analytics, Search Console)', 'Instagram', 'Facebook / Meta', 'Outro' );
}

/**
 * Janela de criar/editar tarefa.
 */
function lk_task_modal( $modal_id, $title, $values = array(), $groups = array() ) {
	$v = wp_parse_args(
		$values,
		array(
			'id'             => 0,
			'title'          => '',
			'description'    => '',
			'project_id'     => 0,
			'grp'            => '',
			'status'         => 'todo',
			'priority'       => 'normal',
			'due_date'       => '',
			'assignee'       => get_current_user_id(),
			'client_visible' => 0,
			'needs_approval' => 0,
		)
	);
	lk_modal_start( $modal_id, $title );
	lk_form( 'task_save', 'stack' );
	echo '<input type="hidden" name="id" value="' . (int) $v['id'] . '">';
	lk_input( 'title', 'O que precisa ser feito', $v['title'], 'text', 'required' );
	echo '<div class="grid-2">';
	if ( $v['project_id'] && ! empty( $values['lock_project'] ) ) {
		echo '<input type="hidden" name="project_id" value="' . (int) $v['project_id'] . '">';
	} else {
		lk_select( 'project_id', 'Projeto', lk_project_options(), $v['project_id'] );
	}
	echo '<label class="field"><span>Grupo / etapa</span><input type="text" name="grp" value="' . esc_attr( $v['grp'] ) . '" list="' . esc_attr( $modal_id ) . '-grps" placeholder="Ex.: Página · Início"><datalist id="' . esc_attr( $modal_id ) . '-grps">';
	foreach ( $groups as $g ) {
		echo '<option value="' . esc_attr( $g ) . '">';
	}
	echo '</datalist></label>';
	lk_input( 'due_date', 'Prazo', $v['due_date'], 'date' );
	lk_select( 'priority', 'Urgência', lk_priorities(), $v['priority'] );
	lk_select( 'assignee', 'Responsável', lk_team_options(), $v['assignee'] );
	lk_select( 'status', 'Status', lk_task_statuses(), $v['status'] );
	echo '</div>';
	lk_input( 'description', 'Detalhes', $v['description'], 'textarea', 'rows="4"' );
	echo '<div class="checks">';
	lk_check( 'client_visible', 'O cliente vê esta tarefa', $v['client_visible'] );
	lk_check( 'needs_approval', 'Precisa da aprovação do cliente', $v['needs_approval'] );
	echo '</div>';
	echo '<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar tarefa</button></div></form>';
	lk_modal_end();
}

/**
 * Resposta do briefing em HTML: texto, opções marcadas, links e imagens de referência ou arquivos.
 */
function lk_briefing_answer_html( $a ) {
	if ( is_array( $a ) && ( isset( $a['links'] ) || isset( $a['files'] ) ) ) {
		$html = '';
		foreach ( (array) ( isset( $a['links'] ) ? $a['links'] : array() ) as $u ) {
			$html .= '<a class="ref-link" href="' . esc_url( $u ) . '" target="_blank" rel="noopener">' . esc_html( preg_replace( '#^https?://(www\.)?#', '', $u ) ) . '</a>';
		}
		$files = isset( $a['files'] ) ? (array) $a['files'] : array();
		if ( $files ) {
			$html .= '<span class="thumbs">';
			foreach ( $files as $u ) {
				$html .= '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $u ) . '" alt="" loading="lazy"></a>';
			}
			$html .= '</span>';
		}
		return $html ? $html : '<span class="muted">—</span>';
	}
	if ( is_array( $a ) ) {
		$a = array_values( array_filter( $a ) );
		if ( ! $a ) {
			return '<span class="muted">—</span>';
		}
		// Lista de arquivos enviados.
		if ( preg_match( '#^https?://#', (string) $a[0] ) ) {
			$html = '';
			foreach ( $a as $u ) {
				$html .= '<a class="ref-link" href="' . esc_url( $u ) . '" target="_blank" rel="noopener">' . esc_html( basename( (string) wp_parse_url( $u, PHP_URL_PATH ) ) ) . '</a>';
			}
			return $html;
		}
		return '<span class="tags">' . implode( '', array_map( function ( $v ) { return '<span class="badge">' . esc_html( $v ) . '</span>'; }, $a ) ) . '</span>';
	}
	return '' !== (string) $a ? nl2br( esc_html( $a ) ) : '<span class="muted">—</span>';
}

/**
 * Atalhos da barra de busca (⌘K): ações rápidas e páginas.
 */
function lk_quick_commands() {
	$c   = array();
	$add = function ( $label, $keys, $target, $group = 'Ação' ) use ( &$c ) {
		$slug = preg_match( '#/painel/([a-z-]+)#', $target, $m ) ? $m[1] : '';
		if ( $slug && lk_page_off( $slug ) ) {
			return;
		}
		$c[] = array( 'label' => $label, 'keys' => $keys, 'target' => $target, 'group' => $group );
	};
	if ( lk_can( 'orcamentos' ) ) {
		$add( 'Novo orçamento', 'orcamento proposta cliente preco', lk_panel_url( 'orcamento' ) );
		$add( 'Calcular preço de uma peça', 'calculadora custo preco filamento gramas', lk_panel_url( 'calculadora' ) );
	}
	if ( lk_can( 'projetos' ) ) {
		$add( 'Novo pedido (balcão / marketplace)', 'pedido venda manual', lk_panel_url( 'pedidos', 0, array( 'abrir' => 'novo-pedido' ) ) );
		$add( 'Calcular frete', 'frete correios jadlog envio cep', lk_panel_url( 'frete' ) );
	}
	if ( lk_can( 'estoque' ) ) {
		$add( 'Cadastrar carretel de filamento', 'filamento carretel estoque pla petg', lk_panel_url( 'filamentos', 0, array( 'abrir' => 'novo-filamento' ) ) );
		$add( 'Registrar compra de insumo', 'insumo sacola caixa compra', lk_panel_url( 'insumos' ) );
		$add( 'Adicionar à lista de compras', 'comprar desejo mercado livre link', lk_panel_url( 'compras', 0, array( 'abrir' => 'novo-item' ) ) );
	}
	if ( lk_can( 'clientes' ) ) {
		$add( 'Novo cliente', 'cadastrar cliente', lk_panel_url( 'clientes', 0, array( 'abrir' => 'novo-cliente' ) ) );
	}
	if ( lk_can( 'leads' ) ) {
		$add( 'Novo lead', 'funil contato interessado', lk_panel_url( 'leads', 0, array( 'abrir' => 'novo-lead' ) ) );
		$add( 'Ideias de post', 'marketing instagram reels conteudo', lk_panel_url( 'marketing' ) );
	}
	if ( lk_can( 'produtos' ) ) {
		$add( 'Importar modelo do MakerWorld', 'makerworld modelo produto importar link', lk_panel_url( 'produtos', 0, array( 'abrir' => 'importar' ) ) );
	}
	if ( lk_can( 'financeiro' ) ) {
		$add( 'Novo lançamento no financeiro', 'despesa receita conta pagar', lk_panel_url( 'financeiro', 0, array( 'abrir' => 'novo-lancamento' ) ) );
	}
	if ( lk_is_team() ) {
		$add( 'Nova tarefa', 'criar tarefa afazer', lk_panel_url( 'tarefas', 0, array( 'abrir' => 'nova-tarefa' ) ) );
	}
	$add( 'Esconder ou mostrar os valores', 'olho dinheiro reais privacidade', 'js:money' );
	$add( 'Trocar tema (claro, escuro, sistema)', 'modo escuro dark claro tema', 'js:theme' );
	$pages = array(
		array( '', 'Dashboard', '' ),
		array( 'leads', 'Funil', 'leads' ),
		array( 'orcamentos', 'Orçamentos', 'orcamentos' ),
		array( 'pedidos', 'Pedidos', 'projetos' ),
		array( 'clientes', 'Clientes', 'clientes' ),
		array( 'filamentos', 'Filamentos', 'estoque' ),
		array( 'insumos', 'Insumos', 'estoque' ),
		array( 'compras', 'Compras e desejos', 'estoque' ),
		array( 'impressoras', 'Impressoras', 'estoque' ),
		array( 'produtos', 'Produtos', 'produtos' ),
		array( 'financeiro', 'Financeiro', 'financeiro' ),
		array( 'tarefas', 'Tarefas', '' ),
	);
	foreach ( $pages as $pg ) {
		if ( ! $pg[2] || lk_can( $pg[2] ) ) {
			$add( $pg[1], 'ir abrir pagina ' . strtolower( remove_accents( $pg[1] ) ), lk_panel_url( $pg[0] ), 'Página' );
		}
	}
	if ( lk_is_admin() ) {
		$add( 'Configurações', 'config ajustes', lk_panel_url( 'config' ), 'Página' );
	}
	return $c;
}

/** Luminância relativa (WCAG) de #rrggbb. */
function lk_hex_lum( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return 0.0;
	}
	$f = function ( $c ) { $c = hexdec( $c ) / 255; return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 ); };
	return 0.2126 * $f( substr( $hex, 0, 2 ) ) + 0.7152 * $f( substr( $hex, 2, 2 ) ) + 0.0722 * $f( substr( $hex, 4, 2 ) );
}

function lk_contrast_ratio( $a, $b ) {
	$la = lk_hex_lum( $a ); $lb = lk_hex_lum( $b );
	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

/** Texto legível (preto ou branco) sobre uma cor de fundo. */
function lk_on_color( $hex ) {
	return lk_contrast_ratio( $hex, '#ffffff' ) >= lk_contrast_ratio( $hex, '#0a0a0a' ) ? '#ffffff' : '#0a0a0a';
}

/** Escurece a cor até ela servir como TEXTO (4.5:1) sobre $bg. */
function lk_text_color_on( $hex, $bg = '#ffffff' ) {
	$hex = '#' . ltrim( (string) $hex, '#' );
	for ( $i = 0; $i < 40 && lk_contrast_ratio( $hex, $bg ) < 4.5; $i++ ) {
		$h = ltrim( $hex, '#' );
		if ( 3 === strlen( $h ) ) {
			$h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
		}
		$hex = sprintf( '#%02x%02x%02x', (int) ( hexdec( substr( $h, 0, 2 ) ) * 0.93 ), (int) ( hexdec( substr( $h, 2, 2 ) ) * 0.93 ), (int) ( hexdec( substr( $h, 4, 2 ) ) * 0.93 ) );
	}
	return $hex;
}

/**
 * Fontes + CSS + variáveis do tema (identity.php). $css: arquivo em assets/.
 */
function lk_theme_head( $css, $light = true ) {
	$t = lk_identity()['tema'];
	echo '<link href="https://fonts.googleapis.com/css2?family=' . esc_attr( $t['google'] ) . '&display=swap" rel="stylesheet">' . "\n";
	echo '<link rel="stylesheet" href="' . esc_url( LK_URL . 'assets/' . $css . '?ver=' . LK_VERSION ) . '">' . "\n";
	$vars = sprintf(
		"--font:'%s',-apple-system,sans-serif;--head:'%s','%s',-apple-system,sans-serif;--body:'%s',-apple-system,sans-serif;--radius:%s;",
		esc_attr( $t['font'] ),
		esc_attr( $t['head'] ),
		esc_attr( $t['font'] ),
		esc_attr( $t['font'] ),
		esc_attr( $t['radius'] )
	);
	$light_vars = sprintf( '--ink:%s;--bg:%s;--accent:%s;--on-accent:%s;--accent-text:%s;--on-ink:%s;', esc_attr( $t['ink'] ), esc_attr( $t['bg'] ), esc_attr( $t['accent'] ), esc_attr( lk_on_color( $t['accent'] ) ), esc_attr( lk_text_color_on( $t['accent'], '#ffffff' ) ), esc_attr( lk_on_color( $t['ink'] ) ) );
	$light = $light ? $light_vars : '';
	echo '<style>:root{' . $vars . '}' . ( $light ? ':root:not([data-theme="dark"]){' . $light . '}' : '' ) . '.side{background:' . esc_attr( $t['side'] ) . '}</style>' . "\n"; // phpcs:ignore
}

/**
 * Crédito discreto: "Sistema personalizado desenvolvido por Eleve Websites" (clica e vai para o site da Eleve).
 * $tone: 'dark' (fundo escuro) ou 'light' (fundo claro).
 */
function lk_credit_html( $tone = 'dark' ) {
	return '<a class="eleve-credit eleve-credit--' . esc_attr( $tone ) . '" href="https://elevewebsites.com.br/?utm_source=sistema&utm_medium=credito&utm_campaign=' . rawurlencode( sanitize_title( lk_setting( 'empresa' ) ) ) . '" target="_blank" rel="noopener" title="Eleve Websites"><span>Sistema personalizado desenvolvido por</span><img src="https://elevewebsites.com.br/wp-content/uploads/2025/03/Ativo-11.png" alt="Eleve Websites" loading="lazy"></a>';
}
