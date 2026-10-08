<?php
/**
 * Formulários do sistema. Todos enviam para admin-post.php com:
 *   action=lk, do=<nome>, _wpnonce (lk_<nome>)
 * e cada <nome> é tratado por lk_do_<nome>().
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_lk', 'lk_dispatch' );
function lk_dispatch() {
	$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
	if ( ! $do || ! function_exists( 'lk_do_' . $do ) ) {
		wp_die( 'Ação desconhecida.' );
	}
	check_admin_referer( 'lk_' . $do );
	call_user_func( 'lk_do_' . $do );
	lk_back();
}

/**
 * Campo do formulário já limpo.
 */
function lk_in( $key, $type = 'text' ) {
	$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	switch ( $type ) {
		case 'int':
			return absint( $raw );
		case 'bool':
			return empty( $raw ) ? 0 : 1;
		case 'money':
			return lk_parse_money( $raw );
		case 'date':
			return lk_clean_date( $raw );
		case 'url':
			return esc_url_raw( trim( (string) $raw ) );
		case 'email':
			return sanitize_email( $raw );
		case 'textarea':
			return sanitize_textarea_field( $raw );
		case 'html':
			return wp_kses_post( $raw );
		case 'raw':
			return $raw;
		default:
			return sanitize_text_field( $raw );
	}
}

/**
 * Volta para a página de origem (ou para "volta"), com um aviso opcional.
 */
function lk_back( $message = '', $type = 'ok', $url = '' ) {
	if ( $message ) {
		lk_flash( $message, $type );
	}
	if ( ! $url ) {
		$url = lk_in( 'volta', 'url' );
	}
	if ( ! $url ) {
		$url = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	}
	wp_safe_redirect( $url );
	exit;
}

function lk_require( $area ) {
	$ok = 'admin' === $area ? lk_is_admin() : lk_can( $area );
	if ( ! $ok ) {
		wp_die( 'Você não tem permissão para isso.', 'Sem permissão', array( 'response' => 403 ) );
	}
}

/**
 * O cliente logado é dono deste projeto? (A equipe com acesso a projetos também pode.)
 */
function lk_owns_project( $project_id ) {
	$project = lk_get( 'projects', $project_id );
	if ( ! $project ) {
		return null;
	}
	if ( lk_can( 'projetos' ) ) {
		return $project;
	}
	$client = lk_current_client();
	return $client && (int) $client->id === (int) $project->client_id ? $project : null;
}

/* -----------------------------------------------------------------------
 * Clientes
 * -------------------------------------------------------------------- */

function lk_do_client_save() {
	lk_require( 'clientes' );
	$id   = lk_in( 'id', 'int' );
	$data = array(
		'name'     => lk_in( 'name' ),
		'company'  => lk_in( 'company' ),
		'cnpj'     => lk_in( 'cnpj' ),
		'email'    => lk_in( 'email', 'email' ),
		'phone'    => lk_in( 'phone' ),
		'whatsapp' => lk_in( 'whatsapp' ),
		'address'  => lk_in( 'address' ),
		'cep'      => lk_in( 'cep' ),
		'city'     => lk_in( 'city' ),
		'rep_cpf'  => lk_in( 'rep_cpf' ),
		'instagram' => ltrim( lk_in( 'instagram' ), '@' ),
		'hashtags' => lk_in( 'hashtags', 'textarea' ),
		'site'     => lk_in( 'site', 'url' ),
		'source'   => lk_in( 'source' ),
		'birthday' => lk_in( 'birthday', 'date' ),
		'notes'    => lk_in( 'notes', 'textarea' ),
	);

	// Logo do cliente (empresas/revendedores).
	if ( ! empty( $_FILES['logo_file']['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$logo = lk_upload_image( 'logo_file' );
		if ( is_wp_error( $logo ) ) {
			lk_back( $logo->get_error_message(), 'erro' );
		}
		$data['logo'] = $logo;
	} elseif ( lk_in( 'logo_remove', 'bool' ) ) {
		$data['logo'] = '';
	}

	if ( ! $id ) {
		if ( lk_in( 'projeto' ) ) {
			$data['notes'] = trim( 'Projeto contratado: ' . lk_in( 'projeto' ) . "\n" . $data['notes'] );
		}
		$data['invite_token'] = wp_generate_password( 32, false );
		$data['status']       = 'convidado';
		$id                   = lk_insert( 'clients', $data );
		if ( ! empty( $data['logo'] ) ) {
			do_action( 'lk_client_logo_saved', $id, $data['logo'] );
		}
		$pk  = lk_packages();
		$pkg = lk_in( 'package' );
		$upd = array(
			'posts_quota'  => lk_in( 'posts_quota' ) !== '' ? max( 0, lk_in( 'posts_quota', 'int' ) ) : ( isset( $pk[ $pkg ] ) ? (int) $pk[ $pkg ]['arts'] : 0 ),
			'videos_quota' => lk_in( 'videos_quota' ) !== '' ? max( 0, lk_in( 'videos_quota', 'int' ) ) : ( isset( $pk[ $pkg ] ) ? (int) $pk[ $pkg ]['videos'] : 0 ),
		);
		if ( isset( $pk[ $pkg ] ) ) {
			$upd['package'] = $pkg;
			if ( lk_can( 'financeiro' ) ) {
				$upd['monthly_fee'] = $pk[ $pkg ]['value']; // o valor só entra se quem cadastra pode mexer com dinheiro
			}
			$upd['notes'] = trim( 'Pacote: ' . $pkg . "\n" . $data['notes'] );
		}
		lk_update( 'clients', $id, $upd );
		do_action( 'lk_client_created', $id );
		lk_back( 'Cliente criado. Copie o link de cadastro (ou mande pelo WhatsApp): ele preenche os dados e cria a senha.', 'ok', lk_panel_url( 'cliente', $id ) );
	}

	lk_update( 'clients', $id, $data );
	$client = lk_get( 'clients', $id );
	if ( ! empty( $data['logo'] ) && ! empty( $_FILES['logo_file']['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		do_action( 'lk_client_logo_saved', $id, $data['logo'] ); // cópia na pasta do cliente no Drive
	}

	// Suporte: atualizar e-mail de acesso e redefinir a senha do cliente.
	if ( $client && $client->user_id ) {
		$update = array( 'ID' => (int) $client->user_id, 'display_name' => $data['name'] ? $data['name'] : $data['company'] );
		if ( $data['email'] && ! email_exists( $data['email'] ) ) {
			$update['user_email'] = $data['email'];
		}
		$pass = lk_in( 'new_password', 'raw' );
		if ( '' !== trim( (string) $pass ) ) {
			$update['user_pass'] = $pass;
		}
		wp_update_user( $update );
	}
	lk_back( 'Cliente salvo.' );
}

/**
 * Recebe uma imagem enviada no formulário (PNG, JPG ou WebP, até 3 MB) e devolve a URL.
 */
function lk_upload_image( $field ) {
	$file = isset( $_FILES[ $field ] ) ? $_FILES[ $field ] : null; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	if ( ! $file || ! empty( $file['error'] ) ) {
		return new WP_Error( 'lk_upload', 'Não foi possível receber a imagem.' );
	}
	if ( $file['size'] > 3 * MB_IN_BYTES ) {
		return new WP_Error( 'lk_upload', 'A imagem passa de 3 MB. Diminua e tente de novo.' );
	}
	$mimes = array( 'png' => 'image/png', 'jpg|jpeg' => 'image/jpeg', 'webp' => 'image/webp' );
	$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
	if ( empty( $check['type'] ) ) {
		return new WP_Error( 'lk_upload', 'Envie a imagem em PNG, JPG ou WebP.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$up = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => $mimes ) );
	if ( isset( $up['error'] ) ) {
		return new WP_Error( 'lk_upload', 'Não foi possível enviar a imagem: ' . $up['error'] );
	}
	return esc_url_raw( $up['url'] );
}

function lk_do_client_new_invite() {
	lk_require( 'clientes' );
	$id = lk_in( 'id', 'int' );
	lk_update( 'clients', $id, array( 'invite_token' => wp_generate_password( 32, false ) ) );
	lk_back( 'Novo link de convite gerado. O anterior deixou de funcionar.' );
}

function lk_do_client_delete() {
	lk_require( 'admin' );
	global $wpdb;
	$id = lk_in( 'id', 'int' );
	if ( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . lk_table( 'projects' ) . ' WHERE client_id = %d', $id ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL
		lk_back( 'Este cliente tem projetos. Exclua ou transfira os projetos antes.', 'erro' );
	}
	$client = lk_get( 'clients', $id );
	if ( $client && $client->user_id && lk_is_client_user( $client->user_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( (int) $client->user_id );
	}
	lk_delete( 'clients', $id );
	lk_back( 'Cliente excluído.', 'ok', lk_panel_url( 'clientes' ) );
}

/**
 * Cadastro pelo link de convite (/convite/<token>).
 */
function lk_handle_invite() {
	global $wpdb;
	$token  = sanitize_text_field( get_query_var( 'lk_token' ) );
	$client = $token ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . lk_table( 'clients' ) . ' WHERE invite_token = %s', $token ) ) : null; // phpcs:ignore WordPress.DB.PreparedSQL
	$GLOBALS['lk_invite_client'] = $client;

	if ( ! $client || 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'lk_invite_' . $client->id ) ) {
		$GLOBALS['lk_invite_error'] = 'Sessão expirada. Recarregue a página e tente de novo.';
		return;
	}

	$email = lk_in( 'email', 'email' );
	$pass  = (string) lk_in( 'senha', 'raw' );
	$data  = array(
		'name'     => lk_in( 'name' ),
		'company'  => lk_in( 'company' ),
		'cnpj'     => lk_in( 'cnpj' ),
		'phone'    => lk_in( 'phone' ),
		'whatsapp' => lk_in( 'whatsapp' ),
		'address'  => lk_in( 'address' ),
		'cep'      => lk_in( 'cep' ),
		'city'     => lk_in( 'city' ),
		'rep_cpf'  => lk_in( 'rep_cpf' ),
		'instagram' => ltrim( lk_in( 'instagram' ), '@' ),
		'site'     => lk_in( 'site', 'url' ),
		'email'    => $email,
	);

	$errors = array();
	if ( ! $data['name'] ) {
		$errors[] = 'Informe o seu nome.';
	}
	if ( ! is_email( $email ) ) {
		$errors[] = 'Informe um e-mail válido.';
	}
	if ( strlen( $pass ) < 8 ) {
		$errors[] = 'A senha precisa ter pelo menos 8 caracteres.';
	}
	if ( $pass !== (string) lk_in( 'senha2', 'raw' ) ) {
		$errors[] = 'As senhas não conferem.';
	}
	$existing = email_exists( $email );
	if ( $existing && (int) $existing !== (int) $client->user_id ) {
		$errors[] = 'Este e-mail já tem cadastro. Use outro ou fale com a gente.';
	}
	if ( $errors ) {
		$GLOBALS['lk_invite_error'] = implode( ' ', $errors );
		return;
	}

	if ( $client->user_id && get_userdata( $client->user_id ) ) {
		$user_id = (int) $client->user_id;
		wp_update_user( array( 'ID' => $user_id, 'user_email' => $email, 'user_pass' => $pass, 'display_name' => $data['name'] ) );
	} else {
		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $data['name'],
				'first_name'   => $data['name'],
				'role'         => 'lk_client',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			$GLOBALS['lk_invite_error'] = 'Não foi possível criar o acesso: ' . $user_id->get_error_message();
			return;
		}
	}

	$data['user_id']      = $user_id;
	$data['status']       = 'ativo';
	$data['invite_token'] = '';
	lk_update( 'clients', $client->id, $data );

	foreach ( lk_rows( 'projects', 'client_id = %d', array( $client->id ) ) as $p ) {
		lk_log( $p->id, $data['name'] . ' criou o acesso à área do cliente.' );
	}
	// Avisa a equipe que o cliente completou o cadastro.
	foreach ( array_unique( array_filter( array_merge( array( (int) $client->atendimento_id, (int) $client->social_id ), get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) ) ) ) as $uid ) {
		lk_notify( (int) $uid, '🎉 ' . ( $data['company'] ? $data['company'] : $data['name'] ) . ' completou o cadastro pelo link.', lk_panel_url( 'cliente', $client->id ) );
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );
	wp_safe_redirect( lk_client_url( '', 0, array( 'bemvindo' => 1 ) ) );
	exit;
}

/* -----------------------------------------------------------------------
 * Projetos
 * -------------------------------------------------------------------- */




/**
 * Projeto mudou de coluna: registra e, se chegou em "Entregue", marca a entrega e o fim do suporte.
 */
function lk_project_moved( $id, $status ) {
	$cols = lk_columns();
	lk_log( $id, 'Pedido avançou para "' . ( isset( $cols[ $status ] ) ? $cols[ $status ] : $status ) . '".', true );
	do_action( 'lk_project_stage', $id, $status );
}

function lk_do_project_archive() {
	lk_require( 'projetos' );
	$id = lk_in( 'id', 'int' );
	$p  = lk_get( 'projects', $id );
	lk_update( 'projects', $id, array( 'archived' => $p && $p->archived ? 0 : 1 ) );
	lk_back( $p && $p->archived ? 'Pedido reativado.' : 'Pedido arquivado.' );
}

function lk_do_project_delete() {
	lk_require( 'admin' );
	lk_delete_project( lk_in( 'id', 'int' ) );
	lk_back( 'Pedido excluído.', 'ok', lk_panel_url( 'pedidos' ) );
}


/* -----------------------------------------------------------------------
 * Tarefas
 * -------------------------------------------------------------------- */

function lk_do_task_save() {
	$id   = lk_in( 'id', 'int' );
	$task = $id ? lk_get( 'tasks', $id ) : null;
	$own  = $task && (int) $task->assignee === get_current_user_id();
	if ( ! lk_can( 'tarefas' ) && ! lk_can( 'projetos' ) && ! $own ) {
		lk_require( 'tarefas' );
	}
	$data = array(
		'title'          => lk_in( 'title' ),
		'description'    => lk_in( 'description', 'textarea' ),
		'project_id'     => lk_in( 'project_id', 'int' ),
		'client_id'      => lk_in( 'client_id', 'int' ),
		'grp'            => lk_in( 'grp' ),
		'priority'       => array_key_exists( lk_in( 'priority' ), lk_priorities() ) ? lk_in( 'priority' ) : 'normal',
		'due_date'       => lk_in( 'due_date', 'date' ),
		'assignee'       => lk_in( 'assignee', 'int' ),
		'client_visible' => lk_in( 'client_visible', 'bool' ),
		'needs_approval' => lk_in( 'needs_approval', 'bool' ),
	);
	$status = lk_in( 'status' );
	if ( array_key_exists( $status, lk_task_statuses() ) ) {
		$data['status']  = $status;
		$data['done_at'] = 'done' === $status ? ( $task && $task->done_at ? $task->done_at : lk_now() ) : null;
	}
	if ( ! $data['title'] ) {
		lk_back( 'Dê um título para a tarefa.', 'erro' );
	}
	if ( $task ) {
		lk_update( 'tasks', $id, $data );
		lk_back( 'Tarefa salva.' );
	}
	$data['created_by'] = get_current_user_id();
	if ( $data['project_id'] ) {
		global $wpdb;
		$data['position'] = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(position) FROM ' . lk_table( 'tasks' ) . ' WHERE project_id = %d', $data['project_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	if ( empty( $data['status'] ) ) {
		$data['status'] = 'todo';
	}
	$new = lk_insert( 'tasks', $data );
	if ( $data['project_id'] ) {
		lk_log( $data['project_id'], 'Nova tarefa: ' . $data['title'], (bool) $data['client_visible'], $new );
	}
	lk_back( 'Tarefa criada.' );
}

function lk_do_task_delete() {
	$task = lk_get( 'tasks', lk_in( 'id', 'int' ) );
	if ( ! $task ) {
		lk_back();
	}
	lk_require( $task->project_id ? 'projetos' : 'tarefas' );
	lk_delete( 'tasks', $task->id );
	lk_back( 'Tarefa excluída.', 'ok', $task->project_id ? lk_panel_url( 'pedido', $task->project_id ) : lk_panel_url( 'tarefas' ) );
}

function lk_do_comment_add() {
	$task_id    = lk_in( 'task_id', 'int' );
	$project_id = lk_in( 'project_id', 'int' );
	$body       = lk_in( 'body', 'textarea' );
	if ( '' === $body ) {
		lk_back();
	}
	$is_team = lk_is_team();
	$task    = $task_id ? lk_get( 'tasks', $task_id ) : null;
	$allowed = $project_id ? (bool) lk_owns_project( $project_id ) : false;
	// Equipe sem acesso a projetos: só comenta nas tarefas que são dela.
	if ( ! $allowed && $is_team ) {
		$allowed = lk_can( 'tarefas' ) || ( $task && (int) $task->assignee === get_current_user_id() );
	}
	if ( ! $allowed || ( $task && (int) $task->project_id !== (int) $project_id ) ) {
		wp_die( 'Sem permissão.' );
	}
	lk_insert(
		'activity',
		array(
			'project_id'     => $project_id,
			'task_id'        => $task_id,
			'user_id'        => get_current_user_id(),
			'type'           => 'comment',
			'body'           => $body,
			'client_visible' => $is_team ? lk_in( 'client_visible', 'bool' ) : 1,
		)
	);
	lk_back( $is_team ? 'Comentário adicionado.' : 'Mensagem enviada para a equipe.' );
}

/* -----------------------------------------------------------------------
 * Arquivos e acessos (a equipe e o próprio cliente podem adicionar)
 * -------------------------------------------------------------------- */

function lk_do_file_add() {
	$project_id = lk_in( 'project_id', 'int' );
	if ( ! lk_owns_project( $project_id ) ) {
		wp_die( 'Sem permissão.' );
	}
	$label = lk_in( 'label' );
	$url   = lk_in( 'url', 'url' );
	$kind  = 'link';

	if ( ! empty( $_FILES['upload']['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$up = wp_handle_upload( $_FILES['upload'], array( 'test_form' => false ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( isset( $up['error'] ) ) {
			lk_back( 'Não foi possível enviar o arquivo: ' . $up['error'], 'erro' );
		}
		$url   = $up['url'];
		$kind  = 'upload';
		$label = $label ? $label : sanitize_file_name( wp_unslash( $_FILES['upload']['name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	}
	if ( ! $url ) {
		lk_back( 'Cole um link ou escolha um arquivo.', 'erro' );
	}
	lk_insert(
		'files',
		array(
			'project_id' => $project_id,
			'label'      => $label ? $label : $url,
			'url'        => $url,
			'kind'       => $kind,
			'created_by' => get_current_user_id(),
		)
	);
	lk_log( $project_id, ( lk_is_team() ? 'Arquivo adicionado: ' : 'O cliente enviou: ' ) . ( $label ? $label : $url ), true );
	lk_back( 'Arquivo adicionado.' );
}

function lk_do_file_delete() {
	$file = lk_get( 'files', lk_in( 'id', 'int' ) );
	if ( $file && ( lk_can( 'projetos' ) || ( lk_owns_project( $file->project_id ) && (int) $file->created_by === get_current_user_id() ) ) ) {
		lk_delete( 'files', $file->id );
	}
	lk_back( 'Arquivo removido.' );
}

function lk_do_access_save() {
	$project_id = lk_in( 'project_id', 'int' );
	if ( ! lk_owns_project( $project_id ) || ( lk_is_team() && ! lk_can( 'acessos' ) ) ) {
		wp_die( 'Sem permissão.' );
	}
	$id   = lk_in( 'id', 'int' );
	$data = array(
		'project_id' => $project_id,
		'label'      => lk_in( 'label' ),
		'url'        => lk_in( 'url' ),
		'login'      => lk_in( 'login' ),
		'notes'      => lk_in( 'notes', 'textarea' ),
	);
	$secret = (string) lk_in( 'secret', 'raw' );
	if ( '' !== $secret || ! $id ) {
		$data['secret'] = lk_encrypt( $secret );
	}
	if ( $id ) {
		$row = lk_get( 'access', $id );
		if ( ! $row || (int) $row->project_id !== $project_id ) {
			wp_die( 'Sem permissão.' );
		}
		lk_update( 'access', $id, $data );
	} else {
		$data['created_by'] = get_current_user_id();
		lk_insert( 'access', $data );
		lk_log( $project_id, 'Acesso adicionado: ' . $data['label'], true );
	}
	lk_back( 'Acesso salvo.' );
}

function lk_do_access_delete() {
	$row = lk_get( 'access', lk_in( 'id', 'int' ) );
	if ( $row && lk_owns_project( $row->project_id ) && ( lk_can( 'acessos' ) || (int) $row->created_by === get_current_user_id() ) ) {
		lk_delete( 'access', $row->id );
	}
	lk_back( 'Acesso removido.' );
}

/* -----------------------------------------------------------------------
 * Financeiro
 * -------------------------------------------------------------------- */

function lk_do_trans_save() {
	lk_require( 'financeiro' );
	$id         = lk_in( 'id', 'int' );
	$project_id = lk_in( 'project_id', 'int' );
	$project    = $project_id ? lk_get( 'projects', $project_id ) : null;
	$status     = 'pago' === lk_in( 'status' ) ? 'pago' : 'pendente';
	$data       = array(
		'type'        => 'out' === lk_in( 'type' ) ? 'out' : 'in',
		'project_id'  => $project_id,
		'client_id'   => $project ? (int) $project->client_id : lk_in( 'client_id', 'int' ),
		'category'    => lk_in( 'category' ),
		'description' => lk_in( 'description' ),
		'amount'      => lk_in( 'amount', 'money' ),
		'due_date'    => lk_in( 'due_date', 'date' ),
		'method'      => lk_in( 'method' ),
		'status'      => $status,
		'paid_at'     => 'pago' === $status ? ( lk_in( 'paid_at', 'date' ) ? lk_in( 'paid_at', 'date' ) : lk_today() ) : null,
		'pay_url'     => lk_in( 'pay_url', 'url' ),
	);
	if ( ! $data['amount'] ) {
		lk_back( 'Informe o valor.', 'erro' );
	}
	if ( $id ) {
		lk_update( 'transactions', $id, $data );
	} else {
		$new_id = lk_insert( 'transactions', $data );
		if ( lk_in( 'recurring', 'bool' ) ) {
			lk_recurring_create_from_form( $data, $new_id );
			$cycles = lk_recur_cycles();
			lk_back( 'Lançamento salvo como recorrente (' . strtolower( $cycles[ array_key_exists( lk_in( 'cycle' ), $cycles ) ? lk_in( 'cycle' ) : 'mensal' ] ) . '). Os próximos aparecem sozinhos.' );
		}
	}
	lk_back( 'Lançamento salvo.' );
}

function lk_do_trans_pay() {
	lk_require( 'financeiro' );
	$row = lk_get( 'transactions', lk_in( 'id', 'int' ) );
	if ( $row ) {
		$paid = 'pago' !== $row->status;
		lk_update(
			'transactions',
			$row->id,
			array(
				'status'  => $paid ? 'pago' : 'pendente',
				'paid_at' => $paid ? lk_today() : null,
				'method'  => lk_in( 'method' ) ? lk_in( 'method' ) : $row->method,
			)
		);
		if ( $paid && $row->project_id && 'in' === $row->type ) {
			lk_log( $row->project_id, 'Pagamento recebido: ' . lk_money( $row->amount ) . '. Obrigado!', true );
		}
		if ( $paid && 'in' === $row->type ) {
			do_action( 'lk_payment_received', $row->id );
		}
	}
	lk_back( 'Lançamento atualizado.' );
}

function lk_do_trans_date() {
	lk_require( 'financeiro' );
	$id   = lk_in( 'id', 'int' );
	$date = lk_in( 'due_date', 'date' );
	if ( $id && $date ) {
		lk_update( 'transactions', $id, array( 'due_date' => $date ) );
		lk_back( 'Vencimento definido para ' . lk_date( $date ) . '.' );
	}
	lk_back( 'Escolha a data.', 'erro' );
}

function lk_do_trans_delete() {
	lk_require( 'financeiro' );
	lk_delete( 'transactions', lk_in( 'id', 'int' ) );
	lk_back( 'Lançamento excluído.' );
}

/* -----------------------------------------------------------------------
 * Rotinas
 * -------------------------------------------------------------------- */



/* -----------------------------------------------------------------------
 * Serviços
 * -------------------------------------------------------------------- */




/* -----------------------------------------------------------------------
 * Equipe e configurações (só admin)
 * -------------------------------------------------------------------- */

function lk_do_team_save() {
	lk_require( 'admin' );
	$user_id = lk_in( 'user_id', 'int' );
	$name    = lk_in( 'name' );
	$email   = lk_in( 'email', 'email' );
	$pass    = (string) lk_in( 'senha', 'raw' );
	$perms   = array_values( array_intersect( array_keys( lk_areas() ), array_map( 'sanitize_key', (array) ( isset( $_POST['perms'] ) ? wp_unslash( $_POST['perms'] ) : array() ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput

	if ( ! $user_id ) {
		if ( ! is_email( $email ) || strlen( $pass ) < 8 ) {
			lk_back( 'Informe um e-mail válido e uma senha com pelo menos 8 caracteres.', 'erro' );
		}
		if ( email_exists( $email ) ) {
			lk_back( 'Já existe um usuário com este e-mail.', 'erro' );
		}
		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $name ? $name : $email,
				'first_name'   => $name,
				'role'         => 'lk_team',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			lk_back( $user_id->get_error_message(), 'erro' );
		}
	} else {
		if ( lk_is_admin( $user_id ) ) {
			lk_back( 'O administrador já tem acesso a tudo.', 'erro' );
		}
		$update = array( 'ID' => $user_id, 'display_name' => $name );
		if ( '' !== trim( $pass ) ) {
			$update['user_pass'] = $pass;
		}
		wp_update_user( $update );
	}
	update_user_meta( $user_id, 'lk_perms', $perms );
	update_user_meta( $user_id, 'lk_func', isset( lk_team_roles()[ lk_in( 'func' ) ] ) ? lk_in( 'func' ) : '' );
	update_user_meta( $user_id, 'lk_only_own', lk_in( 'only_own', 'bool' ) );
	lk_back( 'Membro da equipe salvo.' );
}

function lk_do_team_delete() {
	lk_require( 'admin' );
	$user_id = lk_in( 'user_id', 'int' );
	if ( $user_id && ! lk_is_admin( $user_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id, get_current_user_id() );
	}
	lk_back( 'Membro removido.' );
}

function lk_do_settings_save() {
	lk_require( 'admin' );
	$out = array();
	$current = lk_settings();
	$new_ai  = '';
	$warn    = '';
	$okmsg   = '';
	foreach ( lk_default_settings() as $key => $default ) {
		// Campo que não veio no formulário (outra aba): mantém o valor salvo.
		if ( ! isset( $_POST[ $key ] ) && ! in_array( $key, array( 'melhorenvio_sandbox', 'email_pronto', 'email_boasvindas', 'email_etapas', 'email_pagamento', 'cobranca_auto', 'seg_2fa', 'revisao_obrigatoria' ), true ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$out[ $key ] = $current[ $key ];
			continue;
		}
		// Segredos: guardados criptografados; campo em branco mantém o que já estava salvo.
		if ( in_array( $key, array( 'google_client_secret', 'smtp_pass', 'anthropic_key', 'gemini_key', 'groq_key', 'mistral_key', 'openrouter_key', 'places_key', 'melhorenvio_token', 'ml_secret', 'shopee_key', 'ig_app_secret', 'meta_app_secret', 'meta_ads_token', 'voz_turn_pass', 'linkedin_client_secret' ), true ) ) {
			$sec         = 'smtp_pass' === $key ? (string) lk_in( $key, 'raw' ) : trim( (string) lk_in( $key, 'raw' ) );
			if ( in_array( $key, lk_plain_secret_keys(), true ) ) {
				// Texto puro: o valor novo sempre sobrescreve o antigo (inclusive cópia criptografada); em branco mantém.
				if ( '' === $sec ) {
					$out[ $key ] = $current[ $key ];
				} elseif ( in_array( $key, array( 'ig_app_secret', 'meta_app_secret' ), true ) && ! preg_match( '/^[0-9a-f]{32}$/i', $sec ) ) {
					$warn        .= ' A chave "' . $key . '" NÃO foi salva: chegaram ' . strlen( $sec ) . ' caracteres e o certo são 32 (letras de a a f e números).';
					$out[ $key ] = $current[ $key ];
				} else {
					$out[ $key ] = $sec;
					$okmsg      .= ' Chave "' . $key . '" salva (' . strlen( $sec ) . ' caracteres).';
				}
				continue;
			}
			if ( '' !== $sec && preg_match( '/^[so]:[A-Za-z0-9+\/=]{20,}$/', $sec ) ) {
				$out[ $key ] = $current[ $key ]; // já é um valor criptografado: não criptografa de novo
				continue;
			}
			$out[ $key ] = '' === $sec ? $current[ $key ] : lk_encrypt( $sec );
			if ( '' !== $sec && in_array( $key, array( 'gemini_key', 'groq_key', 'mistral_key', 'openrouter_key' ), true ) ) {
				$new_ai = substr( $key, 0, -4 ); // chave nova colada agora: essa IA passa a ser a escolhida
			}
			continue;
		}
		$out[ $key ] = in_array( $key, array( 'colunas', 'categorias_in', 'categorias_out', 'metodos', 'funil', 'origens', 'tipos_preco', 'dificuldades', 'descontos_qtd', 'revenda_descontos', 'caixas', 'taxas_canais', 'retirada_texto', 'empresa_nota', 'etapas_conteudo', 'briefing_perguntas', 'contrato_modelo', 'pacotes' ), true )
			? lk_in( $key, 'textarea' )
			: ( in_array( $key, array( 'logo', 'logo_icone', 'favicon', 'site', 'login_bg' ), true ) ? lk_in( $key, 'url' ) : lk_in( $key ) );
	}
	if ( $new_ai ) {
		$out['ai_choice'] = $new_ai;
	}
	update_option( 'lk_settings', $out );
	flush_rewrite_rules();
	lk_back( 'Configurações salvas.' . ( $new_ai ? ' IA em uso: ' . $new_ai . '.' : '' ) . $okmsg . $warn, $warn ? 'erro' : 'ok' );
}

/* -----------------------------------------------------------------------
 * Área do cliente
 * -------------------------------------------------------------------- */


/**
 * Salva vários arquivos de um campo <input type="file" name="x[]" multiple>
 * e registra cada um na aba Arquivos do projeto. Devolve as URLs.
 */
function lk_upload_many( $field, $project_id, $label ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
	if ( empty( $_FILES[ $field ]['name'] ) || ! is_array( $_FILES[ $field ]['name'] ) ) {
		return array();
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$f    = $_FILES[ $field ];
	$urls = array();
	foreach ( $f['name'] as $k => $name ) {
		if ( '' === $name || ! empty( $f['error'][ $k ] ) ) {
			continue;
		}
		$one = array(
			'name'     => $name,
			'type'     => $f['type'][ $k ],
			'tmp_name' => $f['tmp_name'][ $k ],
			'error'    => $f['error'][ $k ],
			'size'     => $f['size'][ $k ],
		);
		$up = wp_handle_upload( $one, array( 'test_form' => false ) );
		if ( empty( $up['url'] ) ) {
			continue;
		}
		$urls[] = $up['url'];
		lk_insert(
			'files',
			array(
				'project_id' => (int) $project_id,
				'label'      => $label . ' · ' . sanitize_file_name( $name ),
				'url'        => $up['url'],
				'kind'       => 'upload',
				'created_by' => get_current_user_id(),
			)
		);
	}
	// phpcs:enable
	return $urls;
}

function lk_do_client_approve() {
	$task = lk_get( 'tasks', lk_in( 'task_id', 'int' ) );
	if ( ! $task || ! $task->needs_approval || ! lk_owns_project( $task->project_id ) ) {
		wp_die( 'Sem permissão.' );
	}
	$note = lk_in( 'note', 'textarea' );
	if ( 'ajustes' === lk_in( 'decisao' ) ) {
		lk_update( 'tasks', $task->id, array( 'status' => 'doing', 'approved_at' => null ) );
		lk_log( $task->project_id, 'Ajustes pedidos em "' . $task->grp . ' › ' . $task->title . '"' . ( $note ? ': ' . $note : '.' ), true, $task->id, 'comment' );
		lk_back( 'Pedido de ajustes enviado para a equipe.' );
	}
	lk_update( 'tasks', $task->id, array( 'approved_at' => lk_now(), 'status' => 'done', 'done_at' => lk_now() ) );
	lk_log( $task->project_id, 'Aprovado pelo cliente: ' . ( $task->grp ? $task->grp . ' › ' : '' ) . $task->title . ( $note ? ' (' . $note . ')' : '' ), true, $task->id );
	lk_back( 'Aprovado! Obrigado.' );
}

function lk_do_client_profile() {
	$client = lk_current_client();
	if ( ! $client ) {
		wp_die( 'Sem permissão.' );
	}
	lk_update(
		'clients',
		$client->id,
		array(
			'name'     => lk_in( 'name' ),
			'company'  => lk_in( 'company' ),
			'cnpj'     => lk_in( 'cnpj' ),
			'phone'    => lk_in( 'phone' ),
			'whatsapp' => lk_in( 'whatsapp' ),
			'address'  => lk_in( 'address' ),
			'cep'      => lk_in( 'cep' ),
			'city'     => lk_in( 'city' ),
		)
	);
	$pass = (string) lk_in( 'senha', 'raw' );
	if ( '' !== $pass ) {
		if ( strlen( $pass ) < 8 || $pass !== (string) lk_in( 'senha2', 'raw' ) ) {
			lk_back( 'A nova senha precisa ter 8 caracteres ou mais e ser igual nos dois campos.', 'erro' );
		}
		wp_set_password( $pass, get_current_user_id() );
		wp_set_auth_cookie( get_current_user_id(), true );
	}
	lk_back( 'Dados atualizados.' );
}
