<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nome completo de uma tabela do CRM.
 */
function ap_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'ap_' . $name;
}

function ap_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();

	dbDelta(
		'CREATE TABLE ' . ap_table( 'clients' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(190) NOT NULL DEFAULT '',
			company varchar(190) NOT NULL DEFAULT '',
			cnpj varchar(40) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone varchar(40) NOT NULL DEFAULT '',
			whatsapp varchar(40) NOT NULL DEFAULT '',
			address varchar(255) NOT NULL DEFAULT '',
			cep varchar(12) NOT NULL DEFAULT '',
			city varchar(120) NOT NULL DEFAULT '',
			source varchar(60) NOT NULL DEFAULT '',
			birthday date NULL,
			approved tinyint(1) NOT NULL DEFAULT 0,
			pay_later tinyint(1) NOT NULL DEFAULT 0,
			email_optout tinyint(1) NOT NULL DEFAULT 0,
			logo varchar(255) NOT NULL DEFAULT '',
			notes text NULL,
			invite_token varchar(64) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'convidado',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY invite_token (invite_token)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'services' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(100) NOT NULL DEFAULT '',
			name varchar(190) NOT NULL DEFAULT '',
			color varchar(20) NOT NULL DEFAULT '#fc5521',
			value decimal(12,2) NOT NULL DEFAULT 0,
			support_months int(11) NOT NULL DEFAULT 6,
			stages text NULL,
			pages text NULL,
			page_steps text NULL,
			delivery text NULL,
			internal_form text NULL,
			briefing text NULL,
			proposal longtext NULL,
			contract longtext NULL,
			billing varchar(20) NOT NULL DEFAULT 'unico',
			position int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY slug (slug)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'projects' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			service_id bigint(20) unsigned NOT NULL DEFAULT 0,
			quote_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(190) NOT NULL DEFAULT '',
			status varchar(60) NOT NULL DEFAULT '',
			board_col varchar(60) NOT NULL DEFAULT '',
			position int(11) NOT NULL DEFAULT 0,
			value decimal(12,2) NOT NULL DEFAULT 0,
			cost_estimated decimal(12,2) NOT NULL DEFAULT 0,
			cost_real decimal(12,2) NOT NULL DEFAULT 0,
			grams_used decimal(10,1) NOT NULL DEFAULT 0,
			print_hours decimal(8,2) NOT NULL DEFAULT 0,
			printer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			items longtext NULL,
			photos longtext NULL,
			delivery_mode varchar(20) NOT NULL DEFAULT 'retirada',
			ship_service varchar(120) NOT NULL DEFAULT '',
			ship_price decimal(12,2) NOT NULL DEFAULT 0,
			tracking varchar(120) NOT NULL DEFAULT '',
			start_date date NULL,
			due_date date NULL,
			ready_at datetime NULL,
			ready_notified datetime NULL,
			delivered_at datetime NULL,
			drive_url varchar(255) NOT NULL DEFAULT '',
			notes longtext NULL,
			kind varchar(20) NOT NULL DEFAULT 'pedido',
			urgent tinyint(1) NOT NULL DEFAULT 0,
			art_status varchar(20) NOT NULL DEFAULT 'pendente',
			art_note text NULL,
			stock_done tinyint(1) NOT NULL DEFAULT 0,
			archived tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY status (status)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'tasks' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			grp varchar(190) NOT NULL DEFAULT '',
			title varchar(255) NOT NULL DEFAULT '',
			description longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'todo',
			priority varchar(20) NOT NULL DEFAULT 'normal',
			due_date date NULL,
			assignee bigint(20) unsigned NOT NULL DEFAULT 0,
			client_visible tinyint(1) NOT NULL DEFAULT 0,
			needs_approval tinyint(1) NOT NULL DEFAULT 0,
			approved_at datetime NULL,
			routine_id bigint(20) unsigned NOT NULL DEFAULT 0,
			position int(11) NOT NULL DEFAULT 0,
			done_at datetime NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY project_id (project_id),
			KEY status (status),
			KEY due_date (due_date)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'files' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			label varchar(190) NOT NULL DEFAULT '',
			url text NULL,
			kind varchar(20) NOT NULL DEFAULT 'link',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY project_id (project_id)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'access' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			label varchar(190) NOT NULL DEFAULT '',
			url varchar(255) NOT NULL DEFAULT '',
			login varchar(190) NOT NULL DEFAULT '',
			secret text NULL,
			notes text NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY project_id (project_id)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'transactions' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(10) NOT NULL DEFAULT 'in',
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			category varchar(100) NOT NULL DEFAULT '',
			description varchar(255) NOT NULL DEFAULT '',
			amount decimal(12,2) NOT NULL DEFAULT 0,
			due_date date NULL,
			paid_at date NULL,
			method varchar(60) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pendente',
			pay_url varchar(255) NOT NULL DEFAULT '',
			external_id varchar(120) NOT NULL DEFAULT '',
			bank_ref varchar(190) NOT NULL DEFAULT '',
			recur_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY bank_ref (bank_ref),
			KEY project_id (project_id),
			KEY due_date (due_date),
			KEY status (status)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'recurring' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(10) NOT NULL DEFAULT 'out',
			description varchar(255) NOT NULL DEFAULT '',
			category varchar(100) NOT NULL DEFAULT '',
			amount decimal(12,2) NOT NULL DEFAULT 0,
			method varchar(60) NOT NULL DEFAULT '',
			cycle varchar(20) NOT NULL DEFAULT 'mensal',
			next_date date NULL,
			until_date date NULL,
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY active (active),
			KEY next_date (next_date)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'activity' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			task_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(20) NOT NULL DEFAULT 'log',
			body text NULL,
			client_visible tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY project_id (project_id),
			KEY task_id (task_id)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'leads' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			company varchar(190) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			whatsapp varchar(40) NOT NULL DEFAULT '',
			source varchar(60) NOT NULL DEFAULT '',
			stage varchar(60) NOT NULL DEFAULT '',
			value decimal(12,2) NOT NULL DEFAULT 0,
			service_id bigint(20) unsigned NOT NULL DEFAULT 0,
			niche_id bigint(20) unsigned NOT NULL DEFAULT 0,
			notes text NULL,
			next_action varchar(255) NOT NULL DEFAULT '',
			next_at datetime NULL,
			lost_reason varchar(255) NOT NULL DEFAULT '',
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			proposal_id bigint(20) unsigned NOT NULL DEFAULT 0,
			position int(11) NOT NULL DEFAULT 0,
			last_contact datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY stage (stage),
			KEY whatsapp (whatsapp)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'quotes' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token varchar(64) NOT NULL DEFAULT '',
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			audience varchar(20) NOT NULL DEFAULT 'final',
			title varchar(190) NOT NULL DEFAULT '',
			intro text NULL,
			model3d longtext NULL,
			delivery_mode varchar(20) NOT NULL DEFAULT '',
			items longtext NULL,
			images longtext NULL,
			freight decimal(12,2) NOT NULL DEFAULT 0,
			freight_label varchar(160) NOT NULL DEFAULT '',
			discount decimal(12,2) NOT NULL DEFAULT 0,
			subtotal decimal(12,2) NOT NULL DEFAULT 0,
			total decimal(12,2) NOT NULL DEFAULT 0,
			cost_total decimal(12,2) NOT NULL DEFAULT 0,
			deadline_days int(11) NOT NULL DEFAULT 0,
			valid_until date NULL,
			status varchar(20) NOT NULL DEFAULT 'rascunho',
			views int(11) NOT NULL DEFAULT 0,
			last_view datetime NULL,
			accepted_at datetime NULL,
			pay_option varchar(20) NOT NULL DEFAULT '',
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY token (token),
			KEY client_id (client_id)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'filaments' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			brand varchar(120) NOT NULL DEFAULT '',
			material varchar(40) NOT NULL DEFAULT 'PLA',
			color_name varchar(80) NOT NULL DEFAULT '',
			color_hex varchar(9) NOT NULL DEFAULT '#cccccc',
			spool_g int(11) NOT NULL DEFAULT 1000,
			price decimal(12,2) NOT NULL DEFAULT 0,
			weight_g decimal(10,1) NOT NULL DEFAULT 0,
			tare_g int(11) NOT NULL DEFAULT 0,
			min_g int(11) NOT NULL DEFAULT 200,
			link varchar(255) NOT NULL DEFAULT '',
			notes varchar(255) NOT NULL DEFAULT '',
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY material (material)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'supplies' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(160) NOT NULL DEFAULT '',
			unit varchar(20) NOT NULL DEFAULT 'un',
			qty decimal(12,2) NOT NULL DEFAULT 0,
			unit_cost decimal(12,4) NOT NULL DEFAULT 0,
			min_qty decimal(12,2) NOT NULL DEFAULT 0,
			per_order tinyint(1) NOT NULL DEFAULT 0,
			link varchar(255) NOT NULL DEFAULT '',
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'stock_moves' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			item_type varchar(20) NOT NULL DEFAULT 'supply',
			item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			kind varchar(20) NOT NULL DEFAULT 'compra',
			qty decimal(12,2) NOT NULL DEFAULT 0,
			total decimal(12,2) NOT NULL DEFAULT 0,
			note varchar(255) NOT NULL DEFAULT '',
			trans_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY item (item_type, item_id),
			KEY project_id (project_id)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'printers' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(120) NOT NULL DEFAULT '',
			model varchar(120) NOT NULL DEFAULT '',
			watts int(11) NOT NULL DEFAULT 95,
			price decimal(12,2) NOT NULL DEFAULT 0,
			life_hours int(11) NOT NULL DEFAULT 5000,
			hours_used decimal(10,2) NOT NULL DEFAULT 0,
			maint_every int(11) NOT NULL DEFAULT 500,
			maint_last decimal(10,2) NOT NULL DEFAULT 0,
			bought_at date NULL,
			notes varchar(255) NOT NULL DEFAULT '',
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'shopping' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			kind varchar(10) NOT NULL DEFAULT 'compra',
			title varchar(190) NOT NULL DEFAULT '',
			link varchar(500) NOT NULL DEFAULT '',
			price decimal(12,2) NOT NULL DEFAULT 0,
			qty decimal(10,2) NOT NULL DEFAULT 1,
			priority varchar(10) NOT NULL DEFAULT 'media',
			item_type varchar(20) NOT NULL DEFAULT '',
			item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'aberto',
			bought_at datetime NULL,
			notes varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'calcs' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			data longtext NULL,
			cost decimal(12,2) NOT NULL DEFAULT 0,
			price decimal(12,2) NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'products' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			sku varchar(60) NOT NULL DEFAULT '',
			description longtext NULL,
			photos longtext NULL,
			calc longtext NULL,
			cost decimal(12,2) NOT NULL DEFAULT 0,
			price decimal(12,2) NOT NULL DEFAULT 0,
			grams decimal(10,1) NOT NULL DEFAULT 0,
			print_hours decimal(8,2) NOT NULL DEFAULT 0,
			weight_g int(11) NOT NULL DEFAULT 0,
			dims varchar(40) NOT NULL DEFAULT '',
			stock int(11) NOT NULL DEFAULT 0,
			source_project bigint(20) unsigned NOT NULL DEFAULT 0,
			source_url varchar(500) NOT NULL DEFAULT '',
			license varchar(60) NOT NULL DEFAULT '',
			category varchar(80) NOT NULL DEFAULT '',
			sold_price decimal(12,2) NOT NULL DEFAULT 0,
			sold_count int(11) NOT NULL DEFAULT 0,
			portfolio tinyint(1) NOT NULL DEFAULT 1,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . ap_table( 'ideas' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(190) NOT NULL DEFAULT '',
			body text NULL,
			format varchar(30) NOT NULL DEFAULT 'post',
			status varchar(20) NOT NULL DEFAULT 'ideia',
			post_date date NULL,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . ap_table( 'catalog' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			grp varchar(40) NOT NULL DEFAULT 'impresso',
			name varchar(160) NOT NULL DEFAULT '',
			note varchar(255) NOT NULL DEFAULT '',
			widths varchar(120) NOT NULL DEFAULT '',
			price_m2 decimal(10,2) NOT NULL DEFAULT 0,
			kind varchar(20) NOT NULL DEFAULT 'adesivo',
			allow_lamination tinyint(1) NOT NULL DEFAULT 0,
			allow_eyelets tinyint(1) NOT NULL DEFAULT 0,
			included text NULL,
			supply_id bigint(20) unsigned NOT NULL DEFAULT 0,
			position int(11) NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . ap_table( 'messages' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			from_client tinyint(1) NOT NULL DEFAULT 1,
			body text NULL,
			read_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY unread (from_client, read_at)
		) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . ap_table( 'broadcasts' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			subject varchar(190) NOT NULL DEFAULT '',
			body longtext NULL,
			cta_text varchar(80) NOT NULL DEFAULT '',
			cta_url varchar(255) NOT NULL DEFAULT '',
			audience varchar(40) NOT NULL DEFAULT 'aprovados',
			recipients longtext NULL,
			total int(11) NOT NULL DEFAULT 0,
			sent int(11) NOT NULL DEFAULT 0,
			failed int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'rascunho',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . ap_table( 'feedback' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			url varchar(500) NOT NULL DEFAULT '',
			path varchar(255) NOT NULL DEFAULT '',
			page varchar(190) NOT NULL DEFAULT '',
			sel text NULL,
			snippet varchar(190) NOT NULL DEFAULT '',
			ox float NOT NULL DEFAULT 0,
			oy float NOT NULL DEFAULT 0,
			px float NOT NULL DEFAULT 0,
			py int(11) NOT NULL DEFAULT 0,
			vw int(11) NOT NULL DEFAULT 0,
			body text NULL,
			reply text NULL,
			status varchar(20) NOT NULL DEFAULT 'aberto',
			resolved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			resolved_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY path (path(190)),
			KEY status (status)
		) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . ap_table( 'listings' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			channel varchar(30) NOT NULL DEFAULT '',
			price decimal(12,2) NOT NULL DEFAULT 0,
			external_id varchar(120) NOT NULL DEFAULT '',
			url varchar(500) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'rascunho',
			synced_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id)
		) $c;"
	);
}

/* -----------------------------------------------------------------------
 * Leitura
 * -------------------------------------------------------------------- */

function ap_get( $table, $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ap_table( $table ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function ap_client_by_user( $user_id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ap_table( 'clients' ) . ' WHERE user_id = %d', $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function ap_current_client() {
	static $client = false;
	if ( false === $client ) {
		$client = is_user_logged_in() ? ap_client_by_user( get_current_user_id() ) : null;
	}
	return $client;
}

function ap_clients() {
	global $wpdb;
	return $wpdb->get_results( 'SELECT * FROM ' . ap_table( 'clients' ) . ' ORDER BY company, name' ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function ap_client_label( $client ) {
	if ( ! $client ) {
		return '—';
	}
	return $client->company ? $client->company : ( $client->name ? $client->name : 'Cliente #' . $client->id );
}

function ap_services() {
	global $wpdb;
	return $wpdb->get_results( 'SELECT * FROM ' . ap_table( 'services' ) . ' ORDER BY position, name' ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Projetos com o nome do cliente e do serviço.
 */
function ap_projects( $where = '1=1', $args = array() ) {
	global $wpdb;
	$sql = 'SELECT p.*, c.name AS client_name, c.company AS client_company, s.name AS service_name, s.color AS service_color
		FROM ' . ap_table( 'projects' ) . ' p
		LEFT JOIN ' . ap_table( 'clients' ) . ' c ON c.id = p.client_id
		LEFT JOIN ' . ap_table( 'services' ) . ' s ON s.id = p.service_id
		WHERE ' . $where . ' ORDER BY p.position, p.id DESC';
	return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function ap_project( $id ) {
	$rows = ap_projects( 'p.id = %d', array( $id ) );
	return $rows ? $rows[0] : null;
}

function ap_project_client_label( $p ) {
	return $p->client_company ? $p->client_company : ( $p->client_name ? $p->client_name : '—' );
}

/**
 * Tarefas com o nome do projeto.
 */
function ap_tasks( $where = '1=1', $args = array(), $order = 't.position, t.id' ) {
	global $wpdb;
	$sql = 'SELECT t.*, p.title AS project_title
		FROM ' . ap_table( 'tasks' ) . ' t
		LEFT JOIN ' . ap_table( 'projects' ) . ' p ON p.id = t.project_id
		WHERE ' . $where . ' ORDER BY ' . $order;
	return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function ap_rows( $table, $where, $args = array(), $order = 'id' ) {
	global $wpdb;
	$sql = 'SELECT * FROM ' . ap_table( $table ) . ' WHERE ' . $where . ' ORDER BY ' . $order;
	return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Progresso do projeto: tarefas feitas / total.
 */
function ap_progress( $project_id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, SUM(status = 'done') AS done FROM " . ap_table( 'tasks' ) . " WHERE project_id = %d", $project_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$total = $row ? (int) $row->total : 0;
	$done  = $row ? (int) $row->done : 0;
	return array(
		'total' => $total,
		'done'  => $done,
		'pct'   => $total ? (int) round( $done / $total * 100 ) : 0,
	);
}

/* -----------------------------------------------------------------------
 * Escrita
 * -------------------------------------------------------------------- */

function ap_insert( $table, $data ) {
	global $wpdb;
	if ( ! in_array( $table, array( 'services' ), true ) && empty( $data['created_at'] ) ) {
		$data['created_at'] = ap_now();
	}
	if ( in_array( $table, array( 'projects', 'leads', 'quotes' ), true ) && empty( $data['updated_at'] ) ) {
		$data['updated_at'] = ap_now();
	}
	$wpdb->insert( ap_table( $table ), $data );
	return (int) $wpdb->insert_id;
}

function ap_update( $table, $id, $data ) {
	global $wpdb;
	if ( in_array( $table, array( 'projects', 'leads', 'quotes' ), true ) ) {
		$data['updated_at'] = ap_now();
	}
	return $wpdb->update( ap_table( $table ), $data, array( 'id' => (int) $id ) );
}

function ap_delete( $table, $id ) {
	global $wpdb;
	return $wpdb->delete( ap_table( $table ), array( 'id' => (int) $id ) );
}

/**
 * Registra um evento na linha do tempo do projeto.
 */
function ap_log( $project_id, $body, $client_visible = false, $task_id = 0, $type = 'log' ) {
	return ap_insert(
		'activity',
		array(
			'project_id'     => (int) $project_id,
			'task_id'        => (int) $task_id,
			'user_id'        => get_current_user_id(),
			'type'           => $type,
			'body'           => $body,
			'client_visible' => $client_visible ? 1 : 0,
		)
	);
}

/**
 * Exclui um projeto e tudo o que pertence a ele.
 */
function ap_delete_project( $id ) {
	global $wpdb;
	foreach ( array( 'tasks', 'files', 'access', 'activity' ) as $t ) {
		$wpdb->delete( ap_table( $t ), array( 'project_id' => (int) $id ) );
	}
	$wpdb->delete( ap_table( 'transactions' ), array( 'project_id' => (int) $id, 'status' => 'pendente' ) );
	ap_delete( 'projects', $id );
}
