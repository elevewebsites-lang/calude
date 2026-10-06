<?php
/**
 * Pedaços de interface usados pelas telas.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ap_head( $title, $themed = false ) {
	?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<?php if ( $themed ) : ?>
<script>(function(){try{var t=localStorage.getItem('ap-theme')||'claro';if(t==='sistema'){t=window.matchMedia('(prefers-color-scheme: dark)').matches?'escuro':'claro';}document.documentElement.setAttribute('data-theme',t==='escuro'?'dark':'light');if(localStorage.getItem('ap-hide-money')==='1'){document.documentElement.setAttribute('data-hide-money','');}}catch(e){}})();</script>
<?php endif; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="<?php echo esc_attr( ap_theme_colors()['ink'] ); ?>">
<title><?php echo esc_html( $title . ' · ' . ap_setting( 'empresa' ) ); ?></title>
<link rel="icon" href="<?php echo esc_url( ap_setting( 'favicon' ) ? ap_setting( 'favicon' ) : ap_logo_url( 'cor', 'icone' ) ); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php ap_theme_head( 'app.css' ); ?>
<?php if ( $themed ) : ?><link rel="stylesheet" href="<?php echo esc_url( AP_URL . 'assets/feedback.css?ver=' . AP_VERSION ); ?>"><link rel="stylesheet" href="<?php echo esc_url( AP_URL . 'assets/hub.css?ver=' . AP_VERSION ); ?>"><?php endif; ?>
</head>
	<?php
}

/**
 * Topo das páginas do cliente (entrega e relatório): a logo do cliente, se tiver;
 * senão, a da Eleve. A da Eleve aparece sempre no canto inferior (ap_made_by).
 */
function ap_brand_top( $client ) {
	if ( $client && ! empty( $client->logo ) ) {
		echo '<span class="brand-plate"><img src="' . esc_url( $client->logo ) . '" alt="' . esc_attr( ap_client_label( $client ) ) . '"></span>';
		return;
	}
	echo ap_logo_for( 'oq', 'dv-logo' ); // phpcs:ignore
}

function ap_made_by() {
	echo ap_credit_html( 'dark' ); // phpcs:ignore
}

/**
 * Botão do tema: claro → escuro → igual ao sistema. Fica guardado neste aparelho.
 */
function ap_theme_button() {
	echo '<button type="button" class="theme-btn" data-theme-toggle title="Tema: claro, escuro ou igual ao sistema" aria-label="Trocar tema">'
		. '<span class="theme-ic theme-ic--light">' . ap_icon( 'sol', 18 ) . '</span>' // phpcs:ignore
		. '<span class="theme-ic theme-ic--dark">' . ap_icon( 'lua', 18 ) . '</span>' // phpcs:ignore
		. '<span class="theme-label" data-theme-label></span></button>';
}

/**
 * Olhinho: esconde (ou mostra) todos os valores em R$ do painel. Fica guardado neste aparelho.
 */
function ap_money_button( $class = '', $label = '' ) {
	echo '<button type="button" class="money-btn ' . esc_attr( $class ) . '" data-money-toggle title="Esconder ou mostrar os valores em R$" aria-label="Esconder ou mostrar os valores">'
		. '<span class="money-ic money-ic--on">' . ap_icon( 'olho', 18 ) . '</span>' // phpcs:ignore
		. '<span class="money-ic money-ic--off">' . ap_icon( 'olho-off', 18 ) . '</span>' . ( $label ? '<span class="money-label">' . esc_html( $label ) . '</span>' : '' ) . '</button>'; // phpcs:ignore
}

function ap_money_button_html() {
	ob_start();
	ap_money_button( 'btn btn--ghost btn--icon' );
	return ob_get_clean();
}

function ap_scripts() {
	$data = array(
		'rest'  => esc_url_raw( rest_url( 'ap/v1/' ) ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
	);
	if ( ap_is_team() && get_query_var( 'ap_route' ) === 'panel' ) {
		$data['commands'] = ap_quick_commands();
	}
	echo '<script>window.AP = ' . wp_json_encode( $data ) . ';</script>';
	echo '<script src="' . esc_url( AP_URL . 'assets/app.js?ver=' . AP_VERSION ) . '"></script>';
	ap_feedback_script();
	if ( is_user_logged_in() ) {
		echo '<script src="' . esc_url( AP_URL . 'assets/chat.js?ver=' . AP_VERSION ) . '"></script>';
		echo '<script src="' . esc_url( AP_URL . 'assets/hub.js?ver=' . AP_VERSION ) . '"></script>';
	}
}

/**
 * Abre o painel: menu lateral + barra do topo.
 */
function ap_panel_start( $title, $active = '', $actions = '' ) {
	ap_head( $title, true );
	$user  = wp_get_current_user();
	$items = array(
		'Vendas'  => array(
			''            => array( 'Dashboard', 'dashboard', '' ),
			'leads'       => array( 'Funil', 'funil', 'leads' ),
			'pedidos'     => array( 'Pedidos', 'projetos', 'projetos' ),
			'novo-pedido' => array( 'Novo pedido manual', 'mais', 'projetos' ),
			'mensagens'   => array( 'Mensagens', 'chat', 'clientes' ),
			'clientes'    => array( 'Clientes', 'clientes', 'clientes' ),
			'orcamentos'  => array( 'Propostas comerciais', 'proposta', 'orcamentos' ),
			'cupons'      => array( 'Cupons e crédito', 'tag', 'clientes' ),
			'calculadora' => array( 'Calculadora', 'calculadora', 'orcamentos' ),
		),
		'Estoque' => array(
			'filamentos'  => array( 'Filamentos', 'carretel', 'estoque' ),
			'insumos'     => array( 'Estoque e insumos', 'caixa', 'estoque' ),
			'compras'     => array( 'Compras e desejos', 'carrinho', 'estoque' ),
			'impressoras' => array( 'Impressoras', 'impressora', 'estoque' ),
			'frete'       => array( 'Frete', 'caminhao', 'projetos' ),
		),
		'Catálogo' => array(
			'catalogo'    => array( 'Tabela de preços', 'tag', 'produtos_cat' ),
			'emails'      => array( 'E-mails para clientes', 'email', 'emails' ),
			'produtos'    => array( 'Produtos', 'tag', 'produtos' ),
			'marketing'   => array( 'Marketing', 'megafone', 'leads' ),
		),
		'Gestão'  => array(
			'financeiro'  => array( 'Financeiro', 'financeiro', 'financeiro' ),
			'tarefas'     => array( 'Tarefas', 'tarefas', 'tarefas' ),
			'apontamentos' => array( 'Apontamentos', 'alvo', '' ),
			'importar'    => array( 'Importar planilha', 'caixa', 'admin' ),
		),
	);
	$today_count = ap_count_today_tasks();
	$fb_open     = ap_feedback_open_count();
	// Apontamentos: some do menu quando o botão está desligado e não sobrou nenhum aberto.
	if ( '1' !== (string) ap_setting( 'apontamentos' ) && ! $fb_open ) {
		unset( $items['Gestão']['apontamentos'] );
	}
	?>
<body class="ap ap-panel">
<div class="app">
	<aside class="side" id="side">
		<a class="side-brand" href="<?php echo esc_url( ap_panel_url() ); ?>">
			<?php echo ap_logo_for( 'side' ); // phpcs:ignore ?>
		</a>
		<button type="button" class="side-search" data-search-open><?php echo ap_icon( 'busca', 16 ); // phpcs:ignore ?><span>Buscar…</span><kbd>⌘K</kbd></button>
		<nav class="side-nav">
			<?php foreach ( $items as $group => $links ) : ?>
				<?php
				$visible = array_filter(
					array_keys( $links ),
					function ( $slug ) use ( $links ) {
						return ! ap_page_off( $slug ) && ( ! $links[ $slug ][2] || ap_can( $links[ $slug ][2] ) || ( 'tarefas' === $links[ $slug ][2] && ap_is_team() ) );
					}
				);
				if ( ! $visible ) {
					continue;
				}
				?>
				<span class="side-label"><?php echo esc_html( $group ); ?></span>
				<?php foreach ( $links as $slug => $it ) : ?>
					<?php
					if ( ap_page_off( $slug ) || ( $it[2] && ! ap_can( $it[2] ) && ! ( 'tarefas' === $it[2] && ap_is_team() ) ) ) {
						continue;
					}
					?>
					<a href="<?php echo esc_url( ap_panel_url( $slug ) ); ?>" class="<?php echo $active === $slug ? 'is-active' : ''; ?>">
						<?php echo ap_icon( $it[1] ); // phpcs:ignore ?><span><?php echo esc_html( $it[0] ); ?></span>
						<?php if ( 'tarefas' === $slug && $today_count ) : ?><em class="side-count"><?php echo (int) $today_count; ?></em><?php endif; ?>
						<?php if ( 'apontamentos' === $slug ) : ?><em class="side-count" data-fb-count<?php echo $fb_open ? '' : ' hidden'; ?>><?php echo (int) $fb_open; ?></em><?php endif; ?>
					</a>
				<?php endforeach; ?>
			<?php endforeach; ?>
			<?php if ( ap_is_admin() ) : ?>
				<span class="side-label">Admin</span>
				<a href="<?php echo esc_url( ap_panel_url( 'equipe' ) ); ?>" class="<?php echo 'equipe' === $active ? 'is-active' : ''; ?>"><?php echo ap_icon( 'equipe' ); // phpcs:ignore ?><span>Equipe</span></a>
				<a href="<?php echo esc_url( ap_panel_url( 'config' ) ); ?>" class="<?php echo 'config' === $active ? 'is-active' : ''; ?>"><?php echo ap_icon( 'config' ); // phpcs:ignore ?><span>Configurações</span></a>
				<a href="<?php echo esc_url( ap_panel_url( 'saude' ) ); ?>" class="<?php echo 'saude' === $active ? 'is-active' : ''; ?>"><?php echo ap_icon( 'pulso' ); // phpcs:ignore ?><span>Saúde do sistema</span><?php $hb = ap_health_bad_count(); if ( $hb ) : ?><em class="side-count"><?php echo (int) $hb; ?></em><?php endif; ?></a>
			<?php endif; ?>
		</nav>
		<div class="side-tools">
			<?php ap_money_button( 'side-tool', 'Valores' ); ?>
			<?php ap_theme_button(); ?>
		</div>
		<div class="side-user">
			<span class="avatar"><?php echo esc_html( ap_initials( $user->display_name ) ); ?></span>
			<span class="side-user-name"><?php echo esc_html( $user->display_name ); ?><small><?php echo ap_is_admin() ? 'Administrador' : 'Equipe'; ?></small></span>
			<a href="<?php echo esc_url( ap_url( 'sair' ) ); ?>" title="Sair" class="side-out"><?php echo ap_icon( 'sair', 17 ); // phpcs:ignore ?></a>
		</div>
		<?php echo ap_credit_html( 'dark' ); // phpcs:ignore ?>
	</aside>
	<div class="side-backdrop" data-side-close></div>
	<main class="main">
		<header class="top">
			<button type="button" class="top-menu" data-side-open aria-label="Menu"><?php echo ap_icon( 'menu', 20 ); // phpcs:ignore ?></button>
			<h1 class="top-title"><?php echo esc_html( $title ); ?></h1>
			<div class="top-actions"><?php echo $actions; // phpcs:ignore -- HTML montado pelas telas com esc_*. ?><?php echo ap_bell_html(); // phpcs:ignore ?></div>
		</header>
		<div class="content">
			<?php ap_flash_html(); ?>
	<?php
}

function ap_panel_end() {
	?>
		</div>
	</main>
</div>
<div class="search" id="search" hidden>
	<div class="search-box">
		<div class="search-input"><?php echo ap_icon( 'busca', 18 ); // phpcs:ignore ?><input type="text" placeholder="Buscar pedido, cliente, orçamento, produto, filamento… ou digite um comando" autocomplete="off"><kbd>Esc</kbd></div>
		<div class="search-results"></div>
	</div>
</div>
	<?php
	echo ap_hub_html(); // phpcs:ignore
	ap_scripts();
	echo '</body></html>';
}

/**
 * Área do cliente: topo simples com a marca.
 */
function ap_client_start( $title, $client ) {
	ap_head( $title, true );
	$preview = ap_is_team();
	?>
<body class="ap ap-client">
	<?php if ( $preview ) : ?>
		<div class="preview-bar">Você está vendo a área do cliente como <strong><?php echo esc_html( ap_client_label( $client ) ); ?></strong>. <a href="<?php echo esc_url( ap_panel_url( 'cliente', $client->id ) ); ?>">Voltar ao painel</a></div>
	<?php endif; ?>
<header class="ctop">
	<div class="ctop-inner">
		<a class="ctop-brand" href="<?php echo esc_url( ap_client_link() ); ?>"><?php echo ap_logo_for( 'ctop' ); // phpcs:ignore ?><span>Área do cliente</span></a>
		<nav class="ctop-nav">
			<a href="<?php echo esc_url( ap_client_link() ); ?>">Meus pedidos</a>
			<?php if ( ! empty( $client->approved ) ) : ?><a class="ctop-cta" href="<?php echo esc_url( ap_client_link( 'novo' ) ); ?>">+ Novo pedido</a><a href="<?php echo esc_url( ap_client_link( 'tabela' ) ); ?>">Preços</a><?php endif; ?>
			<a href="<?php echo esc_url( ap_client_link( 'mensagens' ) ); ?>">Mensagens</a>
			<a href="<?php echo esc_url( ap_client_link( 'perfil' ) ); ?>">Meus dados</a>
			<?php echo $preview ? '' : ap_bell_html(); // phpcs:ignore ?>
			<?php ap_theme_button(); ?>
			<?php if ( ! $preview ) : ?><a href="<?php echo esc_url( ap_url( 'sair' ) ); ?>" class="ctop-out"><?php echo ap_icon( 'sair', 16 ); // phpcs:ignore ?> Sair</a><?php endif; ?>
		</nav>
	</div>
</header>
<main class="cmain">
	<?php ap_flash_html(); ?>
	<?php
}

function ap_client_end() {
	$wa = ap_setting( 'whatsapp' );
	echo '</main><footer class="cfoot">' . ap_credit_html( 'light' ) . '</footer>'; // phpcs:ignore
	if ( $wa ) {
		echo '<a class="wa-float" href="' . esc_url( ap_wa_link( $wa, 'Olá! Sou cliente e preciso de suporte.' ) ) . '" target="_blank" rel="noopener" aria-label="Falar no WhatsApp">' . ap_icon( 'whatsapp', 24 ) . '</a>'; // phpcs:ignore
	}
	echo ap_hub_html(); // phpcs:ignore
	ap_scripts();
	echo '</body></html>';
}

function ap_flash_html() {
	$flash = ap_take_flash();
	if ( $flash ) {
		echo '<div class="flash flash--' . esc_attr( $flash[1] ) . '" role="status">' . esc_html( $flash[0] ) . '</div>';
	}

}

/**
 * Abre um formulário que envia para ap_do_<acao>.
 */
function ap_form( $do, $class = '', $upload = false ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="' . esc_attr( $class ) . '"' . ( $upload ? ' enctype="multipart/form-data"' : '' ) . '>';
	echo '<input type="hidden" name="action" value="ap"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
	wp_nonce_field( 'ap_' . $do );
}

/**
 * Botão que envia uma ação com um id (ex.: excluir), com confirmação opcional.
 */
function ap_action_button( $do, $fields, $label, $class = 'btn btn--ghost btn--sm', $confirm = '' ) {
	ap_form( $do, 'inline-form' );
	foreach ( $fields as $k => $v ) {
		echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	echo '<button type="submit" class="' . esc_attr( $class ) . '"' . ( $confirm ? ' data-confirm="' . esc_attr( $confirm ) . '"' : '' ) . '>' . $label . '</button></form>'; // phpcs:ignore -- $label pode conter ícone SVG fixo.
}

/**
 * Campo com rótulo.
 */
function ap_input( $name, $label, $value = '', $type = 'text', $attrs = '' ) {
	$id = 'f_' . preg_replace( '/[^a-z0-9_]/', '_', strtolower( $name ) ) . '_' . wp_rand( 100, 999 );
	echo '<label class="field" for="' . esc_attr( $id ) . '"><span>' . esc_html( $label ) . '</span>';
	if ( 'textarea' === $type ) {
		echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" ' . $attrs . '>' . esc_textarea( $value ) . '</textarea>'; // phpcs:ignore
	} else {
		echo '<input id="' . esc_attr( $id ) . '" type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" ' . $attrs . '>'; // phpcs:ignore
	}
	echo '</label>';
}

function ap_select( $name, $label, $options, $selected = '', $attrs = '' ) {
	echo '<label class="field"><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '" ' . $attrs . '>'; // phpcs:ignore
	foreach ( $options as $value => $text ) {
		echo '<option value="' . esc_attr( $value ) . '"' . selected( (string) $selected, (string) $value, false ) . '>' . esc_html( $text ) . '</option>';
	}
	echo '</select></label>';
}

function ap_check( $name, $label, $checked = false ) {
	echo '<label class="check"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $checked, true, false ) . '><span>' . esc_html( $label ) . '</span></label>';
}

/* -----------------------------------------------------------------------
 * Selos
 * -------------------------------------------------------------------- */

function ap_priority_badge( $priority ) {
	$list = ap_priorities();
	if ( 'normal' === $priority || ! isset( $list[ $priority ] ) ) {
		return '';
	}
	return '<span class="badge badge--' . esc_attr( $priority ) . '">' . esc_html( $list[ $priority ] ) . '</span>';
}

function ap_due_badge( $date, $done = false ) {
	if ( ! $date ) {
		return '';
	}
	$days  = ap_days_until( $date );
	$class = $done ? '' : ( $days < 0 ? 'late' : ( 0 === $days ? 'today' : ( $days <= 2 ? 'soon' : '' ) ) );
	$text  = 0 === $days ? 'Hoje' : ( 1 === $days ? 'Amanhã' : ( -1 === $days ? 'Ontem' : ap_date( $date, 'd/m' ) ) );
	return '<span class="due due--' . esc_attr( $class ) . '">' . ap_icon( 'relogio', 13 ) . esc_html( $text ) . '</span>'; // phpcs:ignore
}

function ap_status_badge( $status ) {
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

function ap_avatar( $user_id, $size = 'sm' ) {
	$user = $user_id ? get_userdata( $user_id ) : null;
	if ( ! $user ) {
		return '';
	}
	return '<span class="avatar avatar--' . esc_attr( $size ) . '" title="' . esc_attr( $user->display_name ) . '">' . esc_html( ap_initials( $user->display_name ) ) . '</span>';
}

function ap_progress_bar( $pct ) {
	return '<div class="progress"><span style="width:' . (int) $pct . '%"></span></div>';
}

/**
 * Tarefas pendentes para hoje (e atrasadas) do usuário, para o contador do menu.
 */
function ap_count_today_tasks() {
	global $wpdb;
	$where = "status <> 'done' AND due_date IS NOT NULL AND due_date <= %s";
	$args  = array( ap_today() );
	if ( ! ap_is_admin() ) {
		$where .= ' AND assignee = %d';
		$args[] = get_current_user_id();
	}
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . ap_table( 'tasks' ) . ' WHERE ' . $where, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Status real de um lançamento (pendente vencido = atrasado).
 */
function ap_trans_status( $t ) {
	if ( 'pago' === $t->status ) {
		return 'pago';
	}
	return $t->due_date && $t->due_date < ap_today() ? 'atrasado' : 'pendente';
}

/**
 * Opções de clientes / projetos / equipe para selects.
 */
function ap_client_options( $empty = 'Selecione…' ) {
	$out = array( '' => $empty );
	foreach ( ap_clients() as $c ) {
		$out[ $c->id ] = ap_client_label( $c );
	}
	return $out;
}

/**
 * Campo "Já é cliente?": escolher preenche nome, empresa, e-mail e WhatsApp (data-client-fill).
 */
function ap_existing_client_select( $selected = 0 ) {
	$map = array();
	foreach ( ap_clients() as $c ) {
		$map[ $c->id ] = array( 'name' => $c->name, 'company' => $c->company, 'email' => $c->email, 'whatsapp' => $c->whatsapp );
	}
	echo '<label class="field field--client"><span>Já é cliente?</span><select name="client_id" data-client-fill="' . esc_attr( wp_json_encode( $map ) ) . '">';
	echo '<option value="">Não, é um contato novo</option>';
	foreach ( ap_clients() as $c ) {
		echo '<option value="' . (int) $c->id . '"' . selected( (int) $selected, (int) $c->id, false ) . '>' . esc_html( ap_client_label( $c ) ) . '</option>';
	}
	echo '</select></label>';
}

function ap_project_options( $empty = 'Sem pedido (tarefa do estúdio)' ) {
	$out = array( '' => $empty );
	foreach ( ap_projects( 'p.archived = 0' ) as $p ) {
		$out[ $p->id ] = $p->title;
	}
	return $out;
}

function ap_team_options( $empty = 'Ninguém' ) {
	$out = array( '' => $empty );
	foreach ( ap_team_users() as $u ) {
		$out[ $u->ID ] = $u->display_name;
	}
	return $out;
}

function ap_list_options( $setting ) {
	$out = array();
	foreach ( ap_list( ap_setting( $setting ) ) as $v ) {
		$out[ $v ] = $v;
	}
	return $out;
}

/**
 * Janela (modal) nativa: <dialog>. Abra com data-open="id".
 */
function ap_modal_start( $id, $title ) {
	echo '<dialog class="modal" id="' . esc_attr( $id ) . '"><div class="modal-head"><h3>' . esc_html( $title ) . '</h3><button type="button" class="modal-x" data-close aria-label="Fechar">×</button></div><div class="modal-body">';
}

function ap_modal_end() {
	echo '</div></dialog>';
}

/**
 * Tipos de acesso sugeridos na aba Acessos.
 */
function ap_access_types() {
	return array( 'Registro.br', 'Hospedagem', 'WordPress (painel do site)', 'Cloudflare', 'E-mail', 'Google (Analytics, Search Console)', 'Instagram', 'Facebook / Meta', 'Outro' );
}

/**
 * Janela de criar/editar tarefa.
 */
function ap_task_modal( $modal_id, $title, $values = array(), $groups = array() ) {
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
	ap_modal_start( $modal_id, $title );
	ap_form( 'task_save', 'stack' );
	echo '<input type="hidden" name="id" value="' . (int) $v['id'] . '">';
	ap_input( 'title', 'O que precisa ser feito', $v['title'], 'text', 'required' );
	echo '<div class="grid-2">';
	if ( $v['project_id'] && ! empty( $values['lock_project'] ) ) {
		echo '<input type="hidden" name="project_id" value="' . (int) $v['project_id'] . '">';
	} else {
		ap_select( 'project_id', 'Projeto', ap_project_options(), $v['project_id'] );
	}
	echo '<label class="field"><span>Grupo / etapa</span><input type="text" name="grp" value="' . esc_attr( $v['grp'] ) . '" list="' . esc_attr( $modal_id ) . '-grps" placeholder="Ex.: Página · Início"><datalist id="' . esc_attr( $modal_id ) . '-grps">';
	foreach ( $groups as $g ) {
		echo '<option value="' . esc_attr( $g ) . '">';
	}
	echo '</datalist></label>';
	ap_input( 'due_date', 'Prazo', $v['due_date'], 'date' );
	ap_select( 'priority', 'Urgência', ap_priorities(), $v['priority'] );
	ap_select( 'assignee', 'Responsável', ap_team_options(), $v['assignee'] );
	ap_select( 'status', 'Status', ap_task_statuses(), $v['status'] );
	echo '</div>';
	ap_input( 'description', 'Detalhes', $v['description'], 'textarea', 'rows="4"' );
	echo '<div class="checks">';
	ap_check( 'client_visible', 'O cliente vê esta tarefa', $v['client_visible'] );
	ap_check( 'needs_approval', 'Precisa da aprovação do cliente', $v['needs_approval'] );
	echo '</div>';
	echo '<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar tarefa</button></div></form>';
	ap_modal_end();
}

/**
 * Resposta do briefing em HTML: texto, opções marcadas, links e imagens de referência ou arquivos.
 */
function ap_briefing_answer_html( $a ) {
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
function ap_quick_commands() {
	$c   = array();
	$add = function ( $label, $keys, $target, $group = 'Ação' ) use ( &$c ) {
		$slug = preg_match( '#/painel/([a-z-]+)#', $target, $m ) ? $m[1] : '';
		if ( $slug && ap_page_off( $slug ) ) {
			return;
		}
		$c[] = array( 'label' => $label, 'keys' => $keys, 'target' => $target, 'group' => $group );
	};
	if ( ap_can( 'orcamentos' ) ) {
		$add( 'Novo orçamento', 'orcamento proposta cliente preco', ap_panel_url( 'orcamento' ) );
		$add( 'Calcular preço de uma peça', 'calculadora custo preco filamento gramas', ap_panel_url( 'calculadora' ) );
	}
	if ( ap_can( 'projetos' ) ) {
		$add( 'Novo pedido (balcão / marketplace)', 'pedido venda manual', ap_panel_url( 'pedidos', 0, array( 'abrir' => 'novo-pedido' ) ) );
		$add( 'Calcular frete', 'frete correios jadlog envio cep', ap_panel_url( 'frete' ) );
	}
	if ( ap_can( 'estoque' ) ) {
		$add( 'Cadastrar carretel de filamento', 'filamento carretel estoque pla petg', ap_panel_url( 'filamentos', 0, array( 'abrir' => 'novo-filamento' ) ) );
		$add( 'Registrar compra de insumo', 'insumo sacola caixa compra', ap_panel_url( 'insumos' ) );
		$add( 'Adicionar à lista de compras', 'comprar desejo mercado livre link', ap_panel_url( 'compras', 0, array( 'abrir' => 'novo-item' ) ) );
	}
	if ( ap_can( 'clientes' ) ) {
		$add( 'Novo cliente', 'cadastrar cliente', ap_panel_url( 'clientes', 0, array( 'abrir' => 'novo-cliente' ) ) );
	}
	if ( ap_can( 'leads' ) ) {
		$add( 'Novo lead', 'funil contato interessado', ap_panel_url( 'leads', 0, array( 'abrir' => 'novo-lead' ) ) );
		$add( 'Ideias de post', 'marketing instagram reels conteudo', ap_panel_url( 'marketing' ) );
	}
	if ( ap_can( 'produtos' ) ) {
		$add( 'Importar modelo do MakerWorld', 'makerworld modelo produto importar link', ap_panel_url( 'produtos', 0, array( 'abrir' => 'importar' ) ) );
	}
	if ( ap_can( 'financeiro' ) ) {
		$add( 'Novo lançamento no financeiro', 'despesa receita conta pagar', ap_panel_url( 'financeiro', 0, array( 'abrir' => 'novo-lancamento' ) ) );
	}
	if ( ap_is_team() ) {
		$add( 'Nova tarefa', 'criar tarefa afazer', ap_panel_url( 'tarefas', 0, array( 'abrir' => 'nova-tarefa' ) ) );
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
		if ( ! $pg[2] || ap_can( $pg[2] ) ) {
			$add( $pg[1], 'ir abrir pagina ' . strtolower( remove_accents( $pg[1] ) ), ap_panel_url( $pg[0] ), 'Página' );
		}
	}
	if ( ap_is_admin() ) {
		$add( 'Configurações', 'config ajustes', ap_panel_url( 'config' ), 'Página' );
	}
	return $c;
}

/**
 * Fontes + CSS + variáveis do tema (identity.php). $css: arquivo em assets/.
 */
function ap_theme_head( $css, $light = true ) {
	$t = ap_theme_colors();
	echo '<link href="https://fonts.googleapis.com/css2?family=' . esc_attr( $t['google'] ) . '&display=swap" rel="stylesheet">' . "\n";
	echo '<link rel="stylesheet" href="' . esc_url( AP_URL . 'assets/' . $css . '?ver=' . AP_VERSION ) . '">' . "\n";
	$vars = sprintf(
		"--font:'%s',-apple-system,sans-serif;--head:'%s','%s',-apple-system,sans-serif;--body:'%s',-apple-system,sans-serif;--radius:%s;",
		esc_attr( $t['font'] ),
		esc_attr( $t['head'] ),
		esc_attr( $t['font'] ),
		esc_attr( $t['font'] ),
		esc_attr( $t['radius'] )
	);
	$light_vars = sprintf( '--ink:%s;--bg:%s;--accent:%s;', esc_attr( $t['ink'] ), esc_attr( $t['bg'] ), esc_attr( $t['accent'] ) );
	$light = $light ? $light_vars : '';
	echo '<style>:root{' . $vars . '}' . ( $light ? ':root:not([data-theme="dark"]){' . $light . '}' : '' ) . '</style>' . "\n"; // phpcs:ignore
	echo '<link rel="stylesheet" href="' . esc_url( AP_URL . 'assets/brand.css?ver=' . AP_VERSION ) . '">' . "\n";
	echo '<style>' . ap_brand_css() . '</style>' . "\n"; // phpcs:ignore
}

/**
 * Crédito discreto: "Sistema personalizado desenvolvido por Eleve Websites" (clica e vai para o site da Eleve).
 * $tone: 'dark' (fundo escuro) ou 'light' (fundo claro).
 */
function ap_credit_html( $tone = 'dark' ) {
	return '<a class="eleve-credit eleve-credit--' . esc_attr( $tone ) . '" href="https://elevewebsites.com.br/?utm_source=sistema&utm_medium=credito&utm_campaign=' . rawurlencode( sanitize_title( ap_setting( 'empresa' ) ) ) . '" target="_blank" rel="noopener" title="Eleve Websites"><span>Sistema personalizado desenvolvido por</span><img src="https://elevewebsites.com.br/wp-content/uploads/2025/03/Ativo-11.png" alt="Eleve Websites" loading="lazy"></a>';
}
