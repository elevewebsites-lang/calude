<?php
/**
 * Formulários do sistema. Todos enviam para admin-post.php com:
 *   action=ap, do=<nome>, _wpnonce (ap_<nome>)
 * e cada <nome> é tratado por ap_do_<nome>().
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_ap', 'ap_dispatch' );
function ap_dispatch() {
	$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
	if ( ! $do || ! function_exists( 'ap_do_' . $do ) ) {
		wp_die( 'Ação desconhecida.' );
	}
	check_admin_referer( 'ap_' . $do );
	call_user_func( 'ap_do_' . $do );
	ap_back();
}

/**
 * Campo do formulário já limpo.
 */
function ap_in( $key, $type = 'text' ) {
	$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	switch ( $type ) {
		case 'int':
			return absint( $raw );
		case 'bool':
			return empty( $raw ) ? 0 : 1;
		case 'money':
			return ap_parse_money( $raw );
		case 'date':
			return ap_clean_date( $raw );
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
function ap_back( $message = '', $type = 'ok', $url = '' ) {
	if ( $message ) {
		ap_flash( $message, $type );
	}
	if ( ! $url ) {
		$url = ap_in( 'volta', 'url' );
	}
	if ( ! $url ) {
		$url = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	}
	wp_safe_redirect( $url );
	exit;
}

function ap_require( $area ) {
	$ok = 'admin' === $area ? ap_is_admin() : ap_can( $area );
	if ( ! $ok ) {
		wp_die( 'Você não tem permissão para isso.', 'Sem permissão', array( 'response' => 403 ) );
	}
}

/**
 * O cliente logado é dono deste projeto? (A equipe com acesso a projetos também pode.)
 */
function ap_owns_project( $project_id ) {
	$project = ap_get( 'projects', $project_id );
	if ( ! $project ) {
		return null;
	}
	if ( ap_can( 'projetos' ) ) {
		return $project;
	}
	$client = ap_current_client();
	return $client && (int) $client->id === (int) $project->client_id ? $project : null;
}

/* -----------------------------------------------------------------------
 * Clientes
 * -------------------------------------------------------------------- */

function ap_do_client_save() {
	ap_require( 'clientes' );
	$id   = ap_in( 'id', 'int' );
	$data = array(
		'name'     => ap_in( 'name' ),
		'company'  => ap_in( 'company' ),
		'cnpj'     => ap_in( 'cnpj' ),
		'email'    => ap_in( 'email', 'email' ),
		'phone'    => ap_in( 'phone' ),
		'whatsapp' => ap_in( 'whatsapp' ),
		'address'  => ap_in( 'address' ),
		'cep'      => ap_in( 'cep' ),
		'city'     => ap_in( 'city' ),
		'source'   => ap_in( 'source' ),
		'birthday' => ap_in( 'birthday', 'date' ),
		'notes'    => ap_in( 'notes', 'textarea' ),
	);

	// Logo do cliente (empresas/revendedores).
	if ( ! empty( $_FILES['logo_file']['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$logo = ap_upload_image( 'logo_file' );
		if ( is_wp_error( $logo ) ) {
			ap_back( $logo->get_error_message(), 'erro' );
		}
		$data['logo'] = $logo;
	} elseif ( ap_in( 'logo_remove', 'bool' ) ) {
		$data['logo'] = '';
	}

	if ( ! $id ) {
		$data['invite_token'] = wp_generate_password( 32, false );
		$data['status']       = 'convidado';
		$id                   = ap_insert( 'clients', $data );
		ap_back( 'Cliente criado. Copie o link de convite e mande para ele.', 'ok', ap_panel_url( 'cliente', $id ) );
	}

	ap_update( 'clients', $id, $data );
	$client = ap_get( 'clients', $id );

	// Suporte: atualizar e-mail de acesso e redefinir a senha do cliente.
	if ( $client && $client->user_id ) {
		$update = array( 'ID' => (int) $client->user_id, 'display_name' => $data['name'] ? $data['name'] : $data['company'] );
		if ( $data['email'] && ! email_exists( $data['email'] ) ) {
			$update['user_email'] = $data['email'];
		}
		$pass = ap_in( 'new_password', 'raw' );
		if ( '' !== trim( (string) $pass ) ) {
			$update['user_pass'] = $pass;
		}
		wp_update_user( $update );
	}
	ap_back( 'Cliente salvo.' );
}

/**
 * Recebe uma imagem enviada no formulário (PNG, JPG ou WebP, até 3 MB) e devolve a URL.
 */
function ap_upload_image( $field ) {
	$file = isset( $_FILES[ $field ] ) ? $_FILES[ $field ] : null; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	if ( ! $file || ! empty( $file['error'] ) ) {
		return new WP_Error( 'ap_upload', 'Não foi possível receber a imagem.' );
	}
	if ( $file['size'] > 3 * MB_IN_BYTES ) {
		return new WP_Error( 'ap_upload', 'A imagem passa de 3 MB. Diminua e tente de novo.' );
	}
	$mimes = array( 'png' => 'image/png', 'jpg|jpeg' => 'image/jpeg', 'webp' => 'image/webp' );
	$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
	if ( empty( $check['type'] ) ) {
		return new WP_Error( 'ap_upload', 'Envie a logo em PNG, JPG ou WebP.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$up = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => $mimes ) );
	if ( isset( $up['error'] ) ) {
		return new WP_Error( 'ap_upload', 'Não foi possível enviar a imagem: ' . $up['error'] );
	}
	return esc_url_raw( $up['url'] );
}

function ap_do_client_new_invite() {
	ap_require( 'clientes' );
	$id = ap_in( 'id', 'int' );
	ap_update( 'clients', $id, array( 'invite_token' => wp_generate_password( 32, false ) ) );
	ap_back( 'Novo link de convite gerado. O anterior deixou de funcionar.' );
}

function ap_do_client_delete() {
	ap_require( 'admin' );
	global $wpdb;
	$id = ap_in( 'id', 'int' );
	if ( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . ap_table( 'projects' ) . ' WHERE client_id = %d', $id ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL
		ap_back( 'Este cliente tem projetos. Exclua ou transfira os projetos antes.', 'erro' );
	}
	$client = ap_get( 'clients', $id );
	if ( $client && $client->user_id && ap_is_client_user( $client->user_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( (int) $client->user_id );
	}
	ap_delete( 'clients', $id );
	ap_back( 'Cliente excluído.', 'ok', ap_panel_url( 'clientes' ) );
}

/**
 * Cadastro pelo link de convite (/convite/<token>).
 */
function ap_handle_invite() {
	global $wpdb;
	$token  = sanitize_text_field( get_query_var( 'ap_token' ) );
	$client = $token ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ap_table( 'clients' ) . ' WHERE invite_token = %s', $token ) ) : null; // phpcs:ignore WordPress.DB.PreparedSQL
	$GLOBALS['ap_invite_client'] = $client;

	if ( ! $client || 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ap_invite_' . $client->id ) ) {
		$GLOBALS['ap_invite_error'] = 'Sessão expirada. Recarregue a página e tente de novo.';
		return;
	}

	$email = ap_in( 'email', 'email' );
	$pass  = (string) ap_in( 'senha', 'raw' );
	$data  = array(
		'name'     => ap_in( 'name' ),
		'company'  => ap_in( 'company' ),
		'cnpj'     => ap_in( 'cnpj' ),
		'phone'    => ap_in( 'phone' ),
		'whatsapp' => ap_in( 'whatsapp' ),
		'address'  => ap_in( 'address' ),
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
	if ( $pass !== (string) ap_in( 'senha2', 'raw' ) ) {
		$errors[] = 'As senhas não conferem.';
	}
	$existing = email_exists( $email );
	if ( $existing && (int) $existing !== (int) $client->user_id ) {
		$errors[] = 'Este e-mail já tem cadastro. Use outro ou fale com a gente.';
	}
	if ( $errors ) {
		$GLOBALS['ap_invite_error'] = implode( ' ', $errors );
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
				'role'         => 'ap_client',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			$GLOBALS['ap_invite_error'] = 'Não foi possível criar o acesso: ' . $user_id->get_error_message();
			return;
		}
	}

	$data['user_id']      = $user_id;
	$data['status']       = 'ativo';
	$data['invite_token'] = '';
	ap_update( 'clients', $client->id, $data );

	foreach ( ap_rows( 'projects', 'client_id = %d', array( $client->id ) ) as $p ) {
		ap_log( $p->id, $data['name'] . ' criou o acesso à área do cliente.' );
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );
	wp_safe_redirect( ap_client_url( '', 0, array( 'bemvindo' => 1 ) ) );
	exit;
}

/* -----------------------------------------------------------------------
 * Projetos
 * -------------------------------------------------------------------- */




/**
 * Projeto mudou de coluna: registra e, se chegou em "Entregue", marca a entrega e o fim do suporte.
 */
function ap_project_moved( $id, $status ) {
	$cols = ap_columns();
	ap_log( $id, 'Pedido avançou para "' . ( isset( $cols[ $status ] ) ? $cols[ $status ] : $status ) . '".', true );
	do_action( 'ap_project_stage', $id, $status );
}

function ap_do_project_archive() {
	ap_require( 'projetos' );
	$id = ap_in( 'id', 'int' );
	$p  = ap_get( 'projects', $id );
	ap_update( 'projects', $id, array( 'archived' => $p && $p->archived ? 0 : 1 ) );
	ap_back( $p && $p->archived ? 'Pedido reativado.' : 'Pedido arquivado.' );
}

function ap_do_project_delete() {
	ap_require( 'admin' );
	ap_delete_project( ap_in( 'id', 'int' ) );
	ap_back( 'Pedido excluído.', 'ok', ap_panel_url( 'pedidos' ) );
}


/* -----------------------------------------------------------------------
 * Tarefas
 * -------------------------------------------------------------------- */

function ap_do_task_save() {
	$id   = ap_in( 'id', 'int' );
	$task = $id ? ap_get( 'tasks', $id ) : null;
	$own  = $task && (int) $task->assignee === get_current_user_id();
	if ( ! ap_can( 'tarefas' ) && ! ap_can( 'projetos' ) && ! $own ) {
		ap_require( 'tarefas' );
	}
	$data = array(
		'title'          => ap_in( 'title' ),
		'description'    => ap_in( 'description', 'textarea' ),
		'project_id'     => ap_in( 'project_id', 'int' ),
		'grp'            => ap_in( 'grp' ),
		'priority'       => array_key_exists( ap_in( 'priority' ), ap_priorities() ) ? ap_in( 'priority' ) : 'normal',
		'due_date'       => ap_in( 'due_date', 'date' ),
		'assignee'       => ap_in( 'assignee', 'int' ),
		'client_visible' => ap_in( 'client_visible', 'bool' ),
		'needs_approval' => ap_in( 'needs_approval', 'bool' ),
	);
	$status = ap_in( 'status' );
	if ( array_key_exists( $status, ap_task_statuses() ) ) {
		$data['status']  = $status;
		$data['done_at'] = 'done' === $status ? ( $task && $task->done_at ? $task->done_at : ap_now() ) : null;
	}
	if ( ! $data['title'] ) {
		ap_back( 'Dê um título para a tarefa.', 'erro' );
	}
	if ( $task ) {
		ap_update( 'tasks', $id, $data );
		ap_back( 'Tarefa salva.' );
	}
	$data['created_by'] = get_current_user_id();
	if ( $data['project_id'] ) {
		global $wpdb;
		$data['position'] = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(position) FROM ' . ap_table( 'tasks' ) . ' WHERE project_id = %d', $data['project_id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	if ( empty( $data['status'] ) ) {
		$data['status'] = 'todo';
	}
	$new = ap_insert( 'tasks', $data );
	if ( $data['project_id'] ) {
		ap_log( $data['project_id'], 'Nova tarefa: ' . $data['title'], (bool) $data['client_visible'], $new );
	}
	ap_back( 'Tarefa criada.' );
}

function ap_do_task_delete() {
	$task = ap_get( 'tasks', ap_in( 'id', 'int' ) );
	if ( ! $task ) {
		ap_back();
	}
	ap_require( $task->project_id ? 'projetos' : 'tarefas' );
	ap_delete( 'tasks', $task->id );
	ap_back( 'Tarefa excluída.', 'ok', $task->project_id ? ap_panel_url( 'pedido', $task->project_id ) : ap_panel_url( 'tarefas' ) );
}

function ap_do_comment_add() {
	$task_id    = ap_in( 'task_id', 'int' );
	$project_id = ap_in( 'project_id', 'int' );
	$body       = ap_in( 'body', 'textarea' );
	if ( '' === $body ) {
		ap_back();
	}
	$is_team = ap_is_team();
	$task    = $task_id ? ap_get( 'tasks', $task_id ) : null;
	$allowed = $project_id ? (bool) ap_owns_project( $project_id ) : false;
	// Equipe sem acesso a projetos: só comenta nas tarefas que são dela.
	if ( ! $allowed && $is_team ) {
		$allowed = ap_can( 'tarefas' ) || ( $task && (int) $task->assignee === get_current_user_id() );
	}
	if ( ! $allowed || ( $task && (int) $task->project_id !== (int) $project_id ) ) {
		wp_die( 'Sem permissão.' );
	}
	ap_insert(
		'activity',
		array(
			'project_id'     => $project_id,
			'task_id'        => $task_id,
			'user_id'        => get_current_user_id(),
			'type'           => 'comment',
			'body'           => $body,
			'client_visible' => $is_team ? ap_in( 'client_visible', 'bool' ) : 1,
		)
	);
	ap_back( $is_team ? 'Comentário adicionado.' : 'Mensagem enviada para a equipe.' );
}

/* -----------------------------------------------------------------------
 * Arquivos e acessos (a equipe e o próprio cliente podem adicionar)
 * -------------------------------------------------------------------- */

function ap_do_file_add() {
	$project_id = ap_in( 'project_id', 'int' );
	if ( ! ap_owns_project( $project_id ) ) {
		wp_die( 'Sem permissão.' );
	}
	$label = ap_in( 'label' );
	$url   = ap_in( 'url', 'url' );
	$kind  = 'link';

	if ( ! empty( $_FILES['upload']['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$up = wp_handle_upload( $_FILES['upload'], array( 'test_form' => false ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( isset( $up['error'] ) ) {
			ap_back( 'Não foi possível enviar o arquivo: ' . $up['error'], 'erro' );
		}
		$url   = $up['url'];
		$kind  = 'upload';
		$label = $label ? $label : sanitize_file_name( wp_unslash( $_FILES['upload']['name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	}
	if ( ! $url ) {
		ap_back( 'Cole um link ou escolha um arquivo.', 'erro' );
	}
	ap_insert(
		'files',
		array(
			'project_id' => $project_id,
			'label'      => $label ? $label : $url,
			'url'        => $url,
			'kind'       => $kind,
			'created_by' => get_current_user_id(),
		)
	);
	ap_log( $project_id, ( ap_is_team() ? 'Arquivo adicionado: ' : 'O cliente enviou: ' ) . ( $label ? $label : $url ), true );
	ap_back( 'Arquivo adicionado.' );
}

function ap_do_file_delete() {
	$file = ap_get( 'files', ap_in( 'id', 'int' ) );
	if ( $file && ( ap_can( 'projetos' ) || ( ap_owns_project( $file->project_id ) && (int) $file->created_by === get_current_user_id() ) ) ) {
		ap_delete( 'files', $file->id );
	}
	ap_back( 'Arquivo removido.' );
}

function ap_do_access_save() {
	$project_id = ap_in( 'project_id', 'int' );
	if ( ! ap_owns_project( $project_id ) || ( ap_is_team() && ! ap_can( 'acessos' ) ) ) {
		wp_die( 'Sem permissão.' );
	}
	$id   = ap_in( 'id', 'int' );
	$data = array(
		'project_id' => $project_id,
		'label'      => ap_in( 'label' ),
		'url'        => ap_in( 'url' ),
		'login'      => ap_in( 'login' ),
		'notes'      => ap_in( 'notes', 'textarea' ),
	);
	$secret = (string) ap_in( 'secret', 'raw' );
	if ( '' !== $secret || ! $id ) {
		$data['secret'] = ap_encrypt( $secret );
	}
	if ( $id ) {
		$row = ap_get( 'access', $id );
		if ( ! $row || (int) $row->project_id !== $project_id ) {
			wp_die( 'Sem permissão.' );
		}
		ap_update( 'access', $id, $data );
	} else {
		$data['created_by'] = get_current_user_id();
		ap_insert( 'access', $data );
		ap_log( $project_id, 'Acesso adicionado: ' . $data['label'], true );
	}
	ap_back( 'Acesso salvo.' );
}

function ap_do_access_delete() {
	$row = ap_get( 'access', ap_in( 'id', 'int' ) );
	if ( $row && ap_owns_project( $row->project_id ) && ( ap_can( 'acessos' ) || (int) $row->created_by === get_current_user_id() ) ) {
		ap_delete( 'access', $row->id );
	}
	ap_back( 'Acesso removido.' );
}

/* -----------------------------------------------------------------------
 * Financeiro
 * -------------------------------------------------------------------- */

function ap_do_trans_save() {
	ap_require( 'financeiro' );
	$id         = ap_in( 'id', 'int' );
	$project_id = ap_in( 'project_id', 'int' );
	$project    = $project_id ? ap_get( 'projects', $project_id ) : null;
	$status     = 'pago' === ap_in( 'status' ) ? 'pago' : 'pendente';
	$data       = array(
		'type'        => 'out' === ap_in( 'type' ) ? 'out' : 'in',
		'project_id'  => $project_id,
		'client_id'   => $project ? (int) $project->client_id : ap_in( 'client_id', 'int' ),
		'category'    => ap_in( 'category' ),
		'description' => ap_in( 'description' ),
		'amount'      => ap_in( 'amount', 'money' ),
		'due_date'    => ap_in( 'due_date', 'date' ),
		'method'      => ap_in( 'method' ),
		'status'      => $status,
		'paid_at'     => 'pago' === $status ? ( ap_in( 'paid_at', 'date' ) ? ap_in( 'paid_at', 'date' ) : ap_today() ) : null,
		'pay_url'     => ap_in( 'pay_url', 'url' ),
	);
	if ( ! $data['amount'] ) {
		ap_back( 'Informe o valor.', 'erro' );
	}
	if ( $id ) {
		ap_update( 'transactions', $id, $data );
	} else {
		$new_id = ap_insert( 'transactions', $data );
		if ( ap_in( 'recurring', 'bool' ) ) {
			ap_recurring_create_from_form( $data, $new_id );
			$cycles = ap_recur_cycles();
			ap_back( 'Lançamento salvo como recorrente (' . strtolower( $cycles[ array_key_exists( ap_in( 'cycle' ), $cycles ) ? ap_in( 'cycle' ) : 'mensal' ] ) . '). Os próximos aparecem sozinhos.' );
		}
	}
	ap_back( 'Lançamento salvo.' );
}

function ap_do_trans_pay() {
	ap_require( 'financeiro' );
	$row = ap_get( 'transactions', ap_in( 'id', 'int' ) );
	if ( $row ) {
		$paid = 'pago' !== $row->status;
		ap_update(
			'transactions',
			$row->id,
			array(
				'status'  => $paid ? 'pago' : 'pendente',
				'paid_at' => $paid ? ap_today() : null,
				'method'  => ap_in( 'method' ) ? ap_in( 'method' ) : $row->method,
			)
		);
		if ( $paid && $row->project_id && 'in' === $row->type ) {
			ap_log( $row->project_id, 'Pagamento recebido: ' . ap_money( $row->amount ) . '. Obrigado!', true );
		}
		if ( $paid && 'in' === $row->type ) {
			do_action( 'ap_payment_received', $row->id );
		}
	}
	ap_back( 'Lançamento atualizado.' );
}

function ap_do_trans_date() {
	ap_require( 'financeiro' );
	$id   = ap_in( 'id', 'int' );
	$date = ap_in( 'due_date', 'date' );
	if ( $id && $date ) {
		ap_update( 'transactions', $id, array( 'due_date' => $date ) );
		ap_back( 'Vencimento definido para ' . ap_date( $date ) . '.' );
	}
	ap_back( 'Escolha a data.', 'erro' );
}

function ap_do_trans_delete() {
	ap_require( 'financeiro' );
	ap_delete( 'transactions', ap_in( 'id', 'int' ) );
	ap_back( 'Lançamento excluído.' );
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

function ap_do_team_save() {
	ap_require( 'admin' );
	$user_id = ap_in( 'user_id', 'int' );
	$name    = ap_in( 'name' );
	$email   = ap_in( 'email', 'email' );
	$pass    = (string) ap_in( 'senha', 'raw' );
	$perms   = array_values( array_intersect( array_keys( ap_areas() ), array_map( 'sanitize_key', (array) ( isset( $_POST['perms'] ) ? wp_unslash( $_POST['perms'] ) : array() ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput

	if ( ! $user_id ) {
		if ( ! is_email( $email ) || strlen( $pass ) < 8 ) {
			ap_back( 'Informe um e-mail válido e uma senha com pelo menos 8 caracteres.', 'erro' );
		}
		if ( email_exists( $email ) ) {
			ap_back( 'Já existe um usuário com este e-mail.', 'erro' );
		}
		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $name ? $name : $email,
				'first_name'   => $name,
				'role'         => 'ap_team',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			ap_back( $user_id->get_error_message(), 'erro' );
		}
	} else {
		if ( ap_is_admin( $user_id ) ) {
			ap_back( 'O administrador já tem acesso a tudo.', 'erro' );
		}
		$update = array( 'ID' => $user_id, 'display_name' => $name );
		if ( '' !== trim( $pass ) ) {
			$update['user_pass'] = $pass;
		}
		wp_update_user( $update );
	}
	update_user_meta( $user_id, 'ap_perms', $perms );
	update_user_meta( $user_id, 'ap_only_own', ap_in( 'only_own', 'bool' ) );
	ap_back( 'Membro da equipe salvo.' );
}

function ap_do_team_delete() {
	ap_require( 'admin' );
	$user_id = ap_in( 'user_id', 'int' );
	if ( $user_id && ! ap_is_admin( $user_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id, get_current_user_id() );
	}
	ap_back( 'Membro removido.' );
}

function ap_do_settings_save() {
	ap_require( 'admin' );
	$out = array();
	$current = ap_settings();
	foreach ( ap_default_settings() as $key => $default ) {
		// Campo que não veio no formulário (outra aba): mantém o valor salvo.
		if ( ! isset( $_POST[ $key ] ) && ! in_array( $key, array( 'melhorenvio_sandbox', 'email_pronto', 'email_boasvindas', 'email_etapas', 'email_pagamento', 'cobranca_auto', 'seg_2fa' ), true ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$out[ $key ] = $current[ $key ];
			continue;
		}
		// Segredos: guardados criptografados; campo em branco mantém o que já estava salvo.
		if ( in_array( $key, array( 'google_client_secret', 'smtp_pass', 'anthropic_key', 'melhorenvio_token', 'ml_secret', 'shopee_key' ), true ) ) {
			$sec         = 'smtp_pass' === $key ? (string) ap_in( $key, 'raw' ) : trim( (string) ap_in( $key, 'raw' ) );
			$out[ $key ] = '' === $sec ? $current[ $key ] : ap_encrypt( $sec );
			continue;
		}
		$out[ $key ] = in_array( $key, array( 'colunas', 'categorias_in', 'categorias_out', 'metodos', 'funil', 'origens', 'tipos_preco', 'dificuldades', 'descontos_qtd', 'revenda_descontos', 'caixas', 'taxas_canais', 'retirada_texto', 'empresa_nota' ), true )
			? ap_in( $key, 'textarea' )
			: ( in_array( $key, array( 'logo', 'logo_cor', 'logo_icone', 'favicon', 'site' ), true ) ? ap_in( $key, 'url' ) : ap_in( $key ) );
	}
	// Cores do tema: só aceita hexadecimal; "usar padrão" limpa as quatro.
	foreach ( array( 'cor_ink', 'cor_bg', 'cor_accent', 'cor_side' ) as $ck ) {
		$out[ $ck ] = ( ! empty( $_POST['cor_reset'] ) || empty( $out[ $ck ] ) || ! ap_hex_ok( $out[ $ck ] ) ) ? '' : '#' . ltrim( $out[ $ck ], '#' ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	update_option( 'ap_settings', $out );
	flush_rewrite_rules();
	ap_back( 'Configurações salvas.' );
}

/* -----------------------------------------------------------------------
 * Área do cliente
 * -------------------------------------------------------------------- */


/**
 * Salva vários arquivos de um campo <input type="file" name="x[]" multiple>
 * e registra cada um na aba Arquivos do projeto. Devolve as URLs.
 */
function ap_upload_many( $field, $project_id, $label ) {
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
		ap_insert(
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

function ap_do_client_approve() {
	$task = ap_get( 'tasks', ap_in( 'task_id', 'int' ) );
	if ( ! $task || ! $task->needs_approval || ! ap_owns_project( $task->project_id ) ) {
		wp_die( 'Sem permissão.' );
	}
	$note = ap_in( 'note', 'textarea' );
	if ( 'ajustes' === ap_in( 'decisao' ) ) {
		ap_update( 'tasks', $task->id, array( 'status' => 'doing', 'approved_at' => null ) );
		ap_log( $task->project_id, 'Ajustes pedidos em "' . $task->grp . ' › ' . $task->title . '"' . ( $note ? ': ' . $note : '.' ), true, $task->id, 'comment' );
		ap_back( 'Pedido de ajustes enviado para a equipe.' );
	}
	ap_update( 'tasks', $task->id, array( 'approved_at' => ap_now(), 'status' => 'done', 'done_at' => ap_now() ) );
	ap_log( $task->project_id, 'Aprovado pelo cliente: ' . ( $task->grp ? $task->grp . ' › ' : '' ) . $task->title . ( $note ? ' (' . $note . ')' : '' ), true, $task->id );
	ap_back( 'Aprovado! Obrigado.' );
}

function ap_do_client_profile() {
	$client = ap_current_client();
	if ( ! $client ) {
		wp_die( 'Sem permissão.' );
	}
	ap_update(
		'clients',
		$client->id,
		array(
			'name'     => ap_in( 'name' ),
			'company'  => ap_in( 'company' ),
			'cnpj'     => ap_in( 'cnpj' ),
			'phone'    => ap_in( 'phone' ),
			'whatsapp' => ap_in( 'whatsapp' ),
			'address'  => ap_in( 'address' ),
			'cep'      => ap_in( 'cep' ),
			'city'     => ap_in( 'city' ),
		)
	);
	$pass = (string) ap_in( 'senha', 'raw' );
	if ( '' !== $pass ) {
		if ( strlen( $pass ) < 8 || $pass !== (string) ap_in( 'senha2', 'raw' ) ) {
			ap_back( 'A nova senha precisa ter 8 caracteres ou mais e ser igual nos dois campos.', 'erro' );
		}
		wp_set_password( $pass, get_current_user_id() );
		wp_set_auth_cookie( get_current_user_id(), true );
	}
	ap_back( 'Dados atualizados.' );
}
