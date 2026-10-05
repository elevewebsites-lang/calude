<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nome completo de uma tabela do CRM.
 */
function lk_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'lk_' . $name;
}

function lk_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();

	dbDelta(
		'CREATE TABLE ' . lk_table( 'clients' ) . " (
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
			monthly_fee decimal(12,2) NOT NULL DEFAULT 0,
			due_day tinyint(2) NOT NULL DEFAULT 10,
			billing_active tinyint(1) NOT NULL DEFAULT 0,
			designer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			social_id bigint(20) unsigned NOT NULL DEFAULT 0,
			atendimento_id bigint(20) unsigned NOT NULL DEFAULT 0,
			trafego_id bigint(20) unsigned NOT NULL DEFAULT 0,
			revisor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			posts_quota int(11) NOT NULL DEFAULT 0,
			briefing longtext NULL,
			briefing_token varchar(40) NOT NULL DEFAULT '',
			briefing_at datetime NULL,
			color varchar(9) NOT NULL DEFAULT '#14E9EC',
			email_optout tinyint(1) NOT NULL DEFAULT 0,
			ads_visible tinyint(1) NOT NULL DEFAULT 0,
			sheet_id varchar(120) NOT NULL DEFAULT '',
			meta_ad_account varchar(40) NOT NULL DEFAULT '',
			google_ads_id varchar(40) NOT NULL DEFAULT '',
			logo varchar(255) NOT NULL DEFAULT '',
			notes text NULL,
			invite_token varchar(64) NOT NULL DEFAULT '',
			rep_cpf varchar(20) NOT NULL DEFAULT '',
			instagram varchar(120) NOT NULL DEFAULT '',
			hashtags text NULL,
			site varchar(190) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'convidado',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY invite_token (invite_token)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . lk_table( 'services' ) . " (
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
		'CREATE TABLE ' . lk_table( 'projects' ) . " (
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
			archived tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY status (status)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . lk_table( 'tasks' ) . " (
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
		'CREATE TABLE ' . lk_table( 'files' ) . " (
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
		'CREATE TABLE ' . lk_table( 'access' ) . " (
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
		'CREATE TABLE ' . lk_table( 'transactions' ) . " (
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
		'CREATE TABLE ' . lk_table( 'recurring' ) . " (
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
		'CREATE TABLE ' . lk_table( 'activity' ) . " (
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
		'CREATE TABLE ' . lk_table( 'leads' ) . " (
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
		'CREATE TABLE ' . lk_table( 'quotes' ) . " (
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
		'CREATE TABLE ' . lk_table( 'filaments' ) . " (
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
		'CREATE TABLE ' . lk_table( 'supplies' ) . " (
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
		'CREATE TABLE ' . lk_table( 'stock_moves' ) . " (
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
		'CREATE TABLE ' . lk_table( 'printers' ) . " (
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
		'CREATE TABLE ' . lk_table( 'shopping' ) . " (
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
		'CREATE TABLE ' . lk_table( 'calcs' ) . " (
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
		'CREATE TABLE ' . lk_table( 'products' ) . " (
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
		'CREATE TABLE ' . lk_table( 'ideas' ) . " (
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
		'CREATE TABLE ' . lk_table( 'posts' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(190) NOT NULL DEFAULT '',
			caption longtext NULL,
			format varchar(20) NOT NULL DEFAULT 'arte',
			networks varchar(120) NOT NULL DEFAULT '',
			scheduled_at datetime NULL,
			stage varchar(60) NOT NULL DEFAULT '',
			position int(11) NOT NULL DEFAULT 0,
			designer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			social_id bigint(20) unsigned NOT NULL DEFAULT 0,
			atendimento_id bigint(20) unsigned NOT NULL DEFAULT 0,
			revisor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			plan_id bigint(20) unsigned NOT NULL DEFAULT 0,
			idea text NULL,
			hashtags text NULL,
			review longtext NULL,
			media longtext NULL,
			approval_token varchar(40) NOT NULL DEFAULT '',
			client_status varchar(20) NOT NULL DEFAULT '',
			change_target varchar(20) NOT NULL DEFAULT '',
			sent_at datetime NULL,
			approved_at datetime NULL,
			published longtext NULL,
			publish_error text NULL,
			deadlines longtext NULL,
			plan_status varchar(20) NOT NULL DEFAULT '',
			sheet_row int(11) NOT NULL DEFAULT 0,
			notes text NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY stage (stage),
			KEY scheduled_at (scheduled_at),
			KEY approval_token (approval_token)
		) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . lk_table( 'plans' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			period varchar(7) NOT NULL DEFAULT '',
			token varchar(40) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'rascunho',
			intro text NULL,
			consideracoes text NULL,
			destaques text NULL,
			sheet_id varchar(120) NOT NULL DEFAULT '',
			reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
			reviewed_at datetime NULL,
			review_note text NULL,
			client_notes text NULL,
			sent_at datetime NULL,
			answered_at datetime NULL,
			approved_at datetime NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_period (client_id, period),
			KEY token (token)
		) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . lk_table( 'post_comments' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			from_client tinyint(1) NOT NULL DEFAULT 0,
			internal tinyint(1) NOT NULL DEFAULT 0,
			target varchar(20) NOT NULL DEFAULT 'geral',
			body text NULL,
			about varchar(20) NOT NULL DEFAULT '',
			media_i int(11) NOT NULL DEFAULT -1,
			at_sec int(11) NOT NULL DEFAULT -1,
			assignee bigint(20) unsigned NOT NULL DEFAULT 0,
			resolved tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id)
		) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . lk_table( 'social_accounts' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			network varchar(20) NOT NULL DEFAULT '',
			account_id varchar(80) NOT NULL DEFAULT '',
			username varchar(120) NOT NULL DEFAULT '',
			name varchar(160) NOT NULL DEFAULT '',
			avatar varchar(500) NOT NULL DEFAULT '',
			token text NULL,
			expires_at datetime NULL,
			extra longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'ok',
			error text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_net (client_id, network)
		) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . lk_table( 'metrics' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			network varchar(20) NOT NULL DEFAULT '',
			day date NULL,
			data longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_day (client_id, day)
		) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . lk_table( 'reports' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			kind varchar(20) NOT NULL DEFAULT 'social',
			period varchar(7) NOT NULL DEFAULT '',
			token varchar(64) NOT NULL DEFAULT '',
			data longtext NULL,
			notes longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'rascunho',
			sent_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY token (token)
		) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . lk_table( 'notifications' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			text varchar(255) NOT NULL DEFAULT '',
			url varchar(255) NOT NULL DEFAULT '',
			read_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_read (user_id, read_at)
		) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . lk_table( 'team_chat' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			channel varchar(60) NOT NULL DEFAULT 'geral',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			body text NULL,
			audio varchar(190) NOT NULL DEFAULT '',
			audio_mime varchar(60) NOT NULL DEFAULT '',
			audio_sec smallint(5) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY channel (channel)
		) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . lk_table( 'messages' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			from_client tinyint(1) NOT NULL DEFAULT 1,
			body text NULL,
			read_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id)
		) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . lk_table( 'focus' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			task_id bigint(20) unsigned NOT NULL DEFAULT 0,
			project_id bigint(20) unsigned NOT NULL DEFAULT 0,
			seconds int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_day (user_id,created_at)
		) $c;"
	);

	dbDelta(
		'CREATE TABLE ' . lk_table( 'listings' ) . " (
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

	dbDelta(
		'CREATE TABLE ' . lk_table( 'feedback' ) . " (
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

	// E-mails em massa (faltava na v1.2.0).
	dbDelta(
		'CREATE TABLE ' . lk_table( 'broadcasts' ) . " (
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

	// Sala de voz: convites de conexão entre os navegadores (apagados assim que lidos).
	dbDelta(
		'CREATE TABLE ' . lk_table( 'voice_signals' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			to_user bigint(20) unsigned NOT NULL DEFAULT 0,
			from_user bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(20) NOT NULL DEFAULT '',
			payload longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY to_user (to_user)
		) $c;"
	);

	// Contratos com assinatura eletrônica.
	dbDelta(
		'CREATE TABLE ' . lk_table( 'contracts' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(190) NOT NULL DEFAULT '',
			services text NULL,
			monthly_value decimal(12,2) NOT NULL DEFAULT 0,
			setup_value decimal(12,2) NOT NULL DEFAULT 0,
			months int(11) NOT NULL DEFAULT 12,
			start_date date NULL,
			due_day tinyint(2) NOT NULL DEFAULT 10,
			posts_quota int(11) NOT NULL DEFAULT 0,
			extra text NULL,
			clauses text NULL,
			drive_url varchar(255) NOT NULL DEFAULT '',
			lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
			package varchar(120) NOT NULL DEFAULT '',
			file_url varchar(255) NOT NULL DEFAULT '',
			file_att bigint(20) unsigned NOT NULL DEFAULT 0,
			imported tinyint(1) NOT NULL DEFAULT 0,
			body longtext NULL,
			hash varchar(64) NOT NULL DEFAULT '',
			token varchar(40) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'rascunho',
			sent_at datetime NULL,
			viewed_at datetime NULL,
			otp_hash varchar(64) NOT NULL DEFAULT '',
			otp_exp int(11) NOT NULL DEFAULT 0,
			otp_tries int(11) NOT NULL DEFAULT 0,
			signer_name varchar(190) NOT NULL DEFAULT '',
			signer_doc varchar(40) NOT NULL DEFAULT '',
			signer_email varchar(190) NOT NULL DEFAULT '',
			signer_ip varchar(64) NOT NULL DEFAULT '',
			signer_ua varchar(255) NOT NULL DEFAULT '',
			signer_sig longtext NULL,
			signed_at datetime NULL,
			agency_name varchar(190) NOT NULL DEFAULT '',
			agency_doc varchar(40) NOT NULL DEFAULT '',
			agency_ip varchar(64) NOT NULL DEFAULT '',
			agency_sig longtext NULL,
			agency_signed_at datetime NULL,
			agency_user bigint(20) unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY token (token)
		) $c;"
	);

	// Briefings personalizados e pesquisas de satisfação: modelos e envios (com respostas).
	dbDelta(
		'CREATE TABLE ' . lk_table( 'forms' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			kind varchar(20) NOT NULL DEFAULT 'briefing',
			title varchar(190) NOT NULL DEFAULT '',
			intro text NULL,
			qschema longtext NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . lk_table( 'form_sends' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			kind varchar(20) NOT NULL DEFAULT 'briefing',
			title varchar(190) NOT NULL DEFAULT '',
			intro text NULL,
			qschema longtext NULL,
			answers longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'pendente',
			token varchar(40) NOT NULL DEFAULT '',
			drive_url varchar(255) NOT NULL DEFAULT '',
			sent_at datetime NULL,
			answered_at datetime NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY status (status)
		) $c;"
	);

	// Reuniões registradas no CRM (com ou sem Google Agenda).
	dbDelta(
		'CREATE TABLE ' . lk_table( 'meetings' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(190) NOT NULL DEFAULT '',
			starts_at datetime NOT NULL,
			duration int(11) NOT NULL DEFAULT 60,
			kind varchar(20) NOT NULL DEFAULT 'online',
			place varchar(255) NOT NULL DEFAULT '',
			guests varchar(255) NOT NULL DEFAULT '',
			notes text NULL,
			status varchar(20) NOT NULL DEFAULT 'agendada',
			google_id varchar(190) NOT NULL DEFAULT '',
			meet_link varchar(255) NOT NULL DEFAULT '',
			notified tinyint(1) NOT NULL DEFAULT 0,
			reminded tinyint(1) NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY starts_at (starts_at)
		) $c;"
	);

	// Cadastro da equipe pelo link (fica esperando aprovação do admin).
	dbDelta(
		'CREATE TABLE ' . lk_table( 'team_requests' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone varchar(40) NOT NULL DEFAULT '',
			funcao varchar(40) NOT NULL DEFAULT '',
			cpf varchar(20) NOT NULL DEFAULT '',
			pix varchar(120) NOT NULL DEFAULT '',
			address varchar(255) NOT NULL DEFAULT '',
			birthday date NULL,
			notes text NULL,
			pass_hash varchar(255) NOT NULL DEFAULT '',
			ip varchar(64) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pendente',
			decided_by bigint(20) unsigned NOT NULL DEFAULT 0,
			decided_at datetime NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) $c;"
	);
}

/* -----------------------------------------------------------------------
 * Leitura
 * -------------------------------------------------------------------- */

function lk_get( $table, $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . lk_table( $table ) . ' WHERE id = %d', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function lk_client_by_user( $user_id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . lk_table( 'clients' ) . ' WHERE user_id = %d', $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function lk_current_client() {
	static $client = false;
	if ( false === $client ) {
		$client = is_user_logged_in() ? lk_client_by_user( get_current_user_id() ) : null;
	}
	return $client;
}

function lk_clients() {
	global $wpdb;
	return $wpdb->get_results( 'SELECT * FROM ' . lk_table( 'clients' ) . ' ORDER BY company, name' ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function lk_client_label( $client ) {
	if ( ! $client ) {
		return '—';
	}
	return $client->company ? $client->company : ( $client->name ? $client->name : 'Cliente #' . $client->id );
}

function lk_services() {
	global $wpdb;
	return $wpdb->get_results( 'SELECT * FROM ' . lk_table( 'services' ) . ' ORDER BY position, name' ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Projetos com o nome do cliente e do serviço.
 */
function lk_projects( $where = '1=1', $args = array() ) {
	global $wpdb;
	$sql = 'SELECT p.*, c.name AS client_name, c.company AS client_company, s.name AS service_name, s.color AS service_color
		FROM ' . lk_table( 'projects' ) . ' p
		LEFT JOIN ' . lk_table( 'clients' ) . ' c ON c.id = p.client_id
		LEFT JOIN ' . lk_table( 'services' ) . ' s ON s.id = p.service_id
		WHERE ' . $where . ' ORDER BY p.position, p.id DESC';
	return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function lk_project( $id ) {
	$rows = lk_projects( 'p.id = %d', array( $id ) );
	return $rows ? $rows[0] : null;
}

function lk_project_client_label( $p ) {
	return $p->client_company ? $p->client_company : ( $p->client_name ? $p->client_name : '—' );
}

/**
 * Tarefas com o nome do projeto.
 */
function lk_tasks( $where = '1=1', $args = array(), $order = 't.position, t.id' ) {
	global $wpdb;
	$sql = 'SELECT t.*, p.title AS project_title
		FROM ' . lk_table( 'tasks' ) . ' t
		LEFT JOIN ' . lk_table( 'projects' ) . ' p ON p.id = t.project_id
		WHERE ' . $where . ' ORDER BY ' . $order;
	return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function lk_rows( $table, $where, $args = array(), $order = 'id' ) {
	global $wpdb;
	$sql = 'SELECT * FROM ' . lk_table( $table ) . ' WHERE ' . $where . ' ORDER BY ' . $order;
	return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Progresso do projeto: tarefas feitas / total.
 */
function lk_progress( $project_id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, SUM(status = 'done') AS done FROM " . lk_table( 'tasks' ) . " WHERE project_id = %d", $project_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
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

function lk_insert( $table, $data ) {
	global $wpdb;
	if ( ! in_array( $table, array( 'services' ), true ) && empty( $data['created_at'] ) ) {
		$data['created_at'] = lk_now();
	}
	if ( in_array( $table, array( 'projects', 'leads', 'quotes', 'posts' ), true ) && empty( $data['updated_at'] ) ) {
		$data['updated_at'] = lk_now();
	}
	$wpdb->insert( lk_table( $table ), $data );
	return (int) $wpdb->insert_id;
}

function lk_update( $table, $id, $data ) {
	global $wpdb;
	if ( in_array( $table, array( 'projects', 'leads', 'quotes', 'posts' ), true ) ) {
		$data['updated_at'] = lk_now();
	}
	$res = $wpdb->update( lk_table( $table ), $data, array( 'id' => (int) $id ) );
	do_action( 'lk_updated', $table, (int) $id, $data ); // gamificação e afins
	return $res;
}

function lk_delete( $table, $id ) {
	global $wpdb;
	return $wpdb->delete( lk_table( $table ), array( 'id' => (int) $id ) );
}

/**
 * Registra um evento na linha do tempo do projeto.
 */
function lk_log( $project_id, $body, $client_visible = false, $task_id = 0, $type = 'log' ) {
	return lk_insert(
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
function lk_delete_project( $id ) {
	global $wpdb;
	foreach ( array( 'tasks', 'files', 'access', 'activity' ) as $t ) {
		$wpdb->delete( lk_table( $t ), array( 'project_id' => (int) $id ) );
	}
	$wpdb->delete( lk_table( 'transactions' ), array( 'project_id' => (int) $id, 'status' => 'pendente' ) );
	lk_delete( 'projects', $id );
}
