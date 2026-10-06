<?php
/**
 * Saúde do sistema (/painel/saude, só admin).
 *
 * Mostra se as tarefas automáticas estão rodando, se os e-mails estão saindo, se há backup,
 * se o Clarity de algum site está com erro e outros pontos de atenção.
 * Avisa por e-mail (1x por dia) e com uma faixa no painel quando algo importante para.
 * Se o agendador do WordPress atrasar, a primeira visita ao painel roda as tarefas atrasadas.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Registros: quando as tarefas rodaram e como estão os e-mails
 * -------------------------------------------------------------------- */

add_action( 'ap_daily', function () { update_option( 'ap_ran_daily', time(), false ); }, 0 );
add_action( 'ap_hourly', function () { update_option( 'ap_ran_hourly', time(), false ); }, 0 );

add_action(
	'wp_mail_failed',
	function ( $error ) {
		$data = $error->get_error_data();
		$log  = (array) get_option( 'ap_mail_fails', array() );
		array_unshift(
			$log,
			array(
				't'       => time(),
				'to'      => isset( $data['to'] ) ? implode( ', ', (array) $data['to'] ) : '',
				'subject' => isset( $data['subject'] ) ? (string) $data['subject'] : '',
				'error'   => $error->get_error_message(),
			)
		);
		update_option( 'ap_mail_fails', array_slice( $log, 0, 20 ), false );
	}
);
add_action( 'wp_mail_succeeded', function () { update_option( 'ap_mail_ok', time(), false ); } );

/**
 * Plano B: agendador atrasado → a visita da equipe ao painel roda as tarefas, depois de entregar a página.
 * As tarefas são seguras para rodar de novo (cada aviso e cobrança tem a sua trava).
 */
add_action(
	'shutdown',
	function () {
		if ( ! get_query_var( 'ap_route' ) || ! is_user_logged_in() || ! ap_is_team() || wp_doing_ajax() ) {
			return;
		}
		$late_daily  = time() - (int) get_option( 'ap_ran_daily', 0 ) > 26 * HOUR_IN_SECONDS;
		$late_hourly = time() - (int) get_option( 'ap_ran_hourly', 0 ) > 2 * HOUR_IN_SECONDS;
		if ( ( ! $late_daily && ! $late_hourly ) || get_transient( 'ap_catchup_lock' ) ) {
			return;
		}
		set_transient( 'ap_catchup_lock', 1, 10 * MINUTE_IN_SECONDS );
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		ignore_user_abort( true );
		if ( $late_hourly ) {
			do_action( 'ap_hourly' );
		}
		if ( $late_daily ) {
			do_action( 'ap_daily' );
		}
		update_option( 'ap_catchup_at', time(), false );
	},
	1
);

/* -----------------------------------------------------------------------
 * Verificações
 * -------------------------------------------------------------------- */

function ap_health_ago( $ts ) {
	if ( ! $ts ) {
		return 'nunca';
	}
	return ap_ago( gmdate( 'Y-m-d H:i:s', (int) $ts + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) );
}

function ap_health_cron_url() {
	return site_url( 'wp-cron.php?doing_wp_cron' );
}

/**
 * Lista de verificações: [ status (ok|warn|bad|info), título, detalhe, como resolver, link ].
 */
function ap_health_checks() {
	global $wpdb;
	$c         = array();
	$installed = (int) get_option( 'ap_health_since' );
	if ( ! $installed ) {
		$installed = time();
		update_option( 'ap_health_since', $installed, false );
	}
	$fresh = time() - $installed < 26 * HOUR_IN_SECONDS;

	// 1. Tarefas diárias.
	$d   = (int) get_option( 'ap_ran_daily', 0 );
	$age = $d ? time() - $d : PHP_INT_MAX;
	$c['daily'] = array(
		$age < 26 * HOUR_IN_SECONDS ? 'ok' : ( $fresh && ! $d ? 'info' : ( $age < 50 * HOUR_IN_SECONDS ? 'warn' : 'bad' ) ),
		'Tarefas diárias',
		'Lembretes de cobrança, contas recorrentes e estoque baixo. Última vez: ' . ap_health_ago( $d ) . '.',
		'Configure o cron do servidor (logo abaixo). Enquanto isso, entrar no painel já roda as tarefas atrasadas.',
		'',
	);

	// 2. Tarefas de hora em hora.
	$h   = (int) get_option( 'ap_ran_hourly', 0 );
	$age = $h ? time() - $h : PHP_INT_MAX;
	$c['hourly'] = array(
		$age < 3 * HOUR_IN_SECONDS ? 'ok' : ( $fresh && ! $h ? 'info' : ( $age < 26 * HOUR_IN_SECONDS ? 'warn' : 'bad' ) ),
		'Tarefas de hora em hora',
		'Cópia dos arquivos para o Google Drive. Última vez: ' . ap_health_ago( $h ) . '.',
		'Configure o cron do servidor (logo abaixo). Sem ele, a cópia para o Drive só roda quando alguém abre o painel.',
		'',
	);

	// 3. Cron do servidor.
	$real = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	$c['cron'] = array(
		$real ? 'ok' : 'warn',
		'Agendador do servidor',
		$real ? 'O servidor chama o agendador sozinho: as tarefas rodam mesmo sem ninguém no painel.' : 'O agendador depende de alguém abrir o painel. Em dias sem acesso, as tarefas atrasam.',
		$real ? '' : 'No painel da hospedagem (cPanel → Cron Jobs), crie uma tarefa a cada 15 minutos com o comando: wget -q -O - "' . ap_health_cron_url() . '" >/dev/null 2>&1. Depois, coloque define( \'DISABLE_WP_CRON\', true ); no wp-config.php.',
		'',
	);

	// 3b. Arquivos dos clientes: ficam no Google Drive; só vão para a hospedagem se o Drive estiver sem espaço.
	$hp = function_exists( 'ap_hosted_pending' ) ? ap_hosted_pending() : array( 'n' => 0, 'bytes' => 0 );
	if ( ap_google_connected() ) {
		$c['hosted'] = array(
			$hp['n'] ? 'warn' : 'ok',
			'Arquivos dos clientes no Google Drive',
			$hp['n'] ? $hp['n'] . ' arquivo(s) (' . size_format( $hp['bytes'] ) . ') ficaram na hospedagem porque o Drive estava sem espaço. Eles vão para o Drive sozinhos quando houver espaço.' : 'Todos os arquivos enviados pelos clientes estão no Drive (nada ocupa a hospedagem).',
			$hp['n'] ? 'Libere espaço no Google Drive (ou aumente o plano Google One). A cada hora o sistema tenta mover e apaga a cópia da hospedagem.' : '',
			'',
		);
	} else {
		$c['hosted'] = array( 'warn', 'Arquivos dos clientes', 'O Google Drive não está conectado: os arquivos dos clientes estão ficando na hospedagem' . ( $hp['n'] ? ' (' . $hp['n'] . ' arquivo(s), ' . size_format( $hp['bytes'] ) . ')' : '' ) . '.', 'Conecte o Drive em Configurações → Google Drive. Depois disso os arquivos vão para o Drive e a hospedagem é liberada.', '' );
	}

	// 4. E-mail.
	$ok_at  = (int) get_option( 'ap_mail_ok', 0 );
	$fails  = (array) get_option( 'ap_mail_fails', array() );
	$last_f = $fails ? $fails[0] : null;
	$failing = $last_f && $last_f['t'] > $ok_at && time() - $last_f['t'] < 7 * DAY_IN_SECONDS;
	$smtp    = '' !== (string) ap_setting( 'smtp_host' );
	$c['mail'] = array(
		$failing ? 'bad' : ( $smtp ? 'ok' : 'warn' ),
		'E-mails',
		$failing ? 'O último e-mail falhou (' . ap_health_ago( $last_f['t'] ) . '): ' . $last_f['error'] : ( $smtp ? 'Saindo pelo SMTP ' . ap_setting( 'smtp_host' ) . '. Último envio certo: ' . ap_health_ago( $ok_at ) . '.' : 'Sem SMTP configurado: os e-mails podem cair no spam ou não sair.' ),
		$failing ? 'Confira o usuário e a senha do SMTP em Configurações e use "Enviar e-mail de teste".' : ( $smtp ? '' : 'Preencha o SMTP em Configurações → E-mail de envio.' ),
		ap_panel_url( 'config' ),
	);

	// 5. Backup.
	$backup = 0;
	$ud     = get_option( 'updraft_last_backup' );
	if ( is_array( $ud ) && ! empty( $ud['backup_time'] ) ) {
		$backup = (int) $ud['backup_time'];
	}
	$has_plugin = $backup || class_exists( 'UpdraftPlus' ) || defined( 'UPDRAFTPLUS_DIR' ) || class_exists( 'BackWPup' ) || defined( 'JETPACK__VERSION' );
	$bage       = $backup ? time() - $backup : PHP_INT_MAX;
	$c['backup'] = array(
		$backup ? ( $bage < 3 * DAY_IN_SECONDS ? 'ok' : ( $bage < 8 * DAY_IN_SECONDS ? 'warn' : 'bad' ) ) : ( $has_plugin ? 'warn' : 'bad' ),
		'Backup',
		$backup ? 'Último backup: ' . ap_health_ago( $backup ) . '.' : ( $has_plugin ? 'Plugin de backup instalado, mas ainda sem backup registrado.' : 'Nenhum backup automático encontrado. Se o servidor tiver um problema, os dados do painel se perdem.' ),
		$bage < 3 * DAY_IN_SECONDS ? '' : 'Instale o UpdraftPlus (Plugins → Adicionar novo), agende backup diário do banco de dados e semanal dos arquivos, com destino Google Drive.',
		admin_url( 'plugin-install.php?s=updraftplus&tab=search&type=term' ),
	);

	// 9. Integrações.
	$c['pay'] = array(
		ap_ip_handle() ? 'ok' : 'warn',
		'Pagamentos (InfinitePay)',
		ap_ip_handle() ? 'Links de pagamento ativos ($' . ap_ip_handle() . ').' : 'Sem InfiniteTag: os links de pagamento dos e-mails não funcionam.',
		ap_ip_handle() ? '' : 'Preencha a InfiniteTag em Configurações → Pagamentos.',
		ap_panel_url( 'config' ),
	);
	if ( ap_module( 'frete' ) ) {
	$c['frete'] = array(
		ap_setting( 'melhorenvio_token' ) && ap_setting( 'cep_origem' ) ? 'ok' : 'info',
		'Frete (Melhor Envio)',
		ap_setting( 'melhorenvio_token' ) && ap_setting( 'cep_origem' ) ? 'Calculadora de frete ativa.' : 'Sem token do Melhor Envio ou CEP de origem: o cálculo de frete fica desligado.',
		'',
		ap_panel_url( 'config' ),
	);
	}
	$low = ap_stock_low();
	if ( ap_module( 'estoque' ) ) {
		$c['estoque'] = array(
			$low['supplies'] ? 'warn' : 'ok',
			'Estoque',
			$low['supplies'] ? 'Abaixo do mínimo: ' . implode( ', ', wp_list_pluck( $low['supplies'], 'name' ) ) . '.' : 'Insumos acima do mínimo.',
			$low['supplies'] ? 'Os itens já estão na lista de compras.' : '',
			ap_panel_url( 'compras' ),
		);
	}
	if ( ap_setting( 'google_client_id' ) ) {
		$c['google'] = array(
			ap_google_connected() ? 'ok' : 'warn',
			'Google Drive',
			ap_google_connected() ? 'Conectado. Arquivos na fila: ' . count( (array) get_option( 'ap_drive_queue', array() ) ) . '.' : 'Desconectado: as fotos e arquivos não estão indo para o Drive.',
			ap_google_connected() ? '' : 'Clique em "Conectar Google" no fim de Configurações.',
			ap_panel_url( 'config' ),
		);
	}

	// 10. Servidor.
	$tz = get_option( 'timezone_string' );
	$c['tz'] = array(
		'America/Sao_Paulo' === $tz ? 'ok' : 'warn',
		'Fuso horário',
		'America/Sao_Paulo' === $tz ? 'Horário de Brasília.' : 'O WordPress está em "' . ( $tz ? $tz : 'UTC' . get_option( 'gmt_offset' ) ) . '": prazos e avisos podem sair no dia errado.',
		'America/Sao_Paulo' === $tz ? '' : 'Em wp-admin → Configurações → Geral → Fuso horário, escolha São Paulo.',
		admin_url( 'options-general.php' ),
	);
	$https = 0 === strpos( home_url(), 'https://' );
	$c['https'] = array(
		$https ? 'ok' : 'bad',
		'Conexão segura (HTTPS)',
		$https ? 'O painel abre com cadeado.' : 'O painel está sem HTTPS: senhas e dados trafegam abertos.',
		$https ? '' : 'Ative o SSL na hospedagem e troque o endereço do site para https em wp-admin → Configurações → Geral.',
		'',
	);
	$c['versions'] = array(
		version_compare( PHP_VERSION, '7.4', '>=' ) ? 'info' : 'bad',
		'Versões',
		'Allprint CRM ' . AP_VERSION . ' · WordPress ' . get_bloginfo( 'version' ) . ' · PHP ' . PHP_VERSION . '.',
		version_compare( PHP_VERSION, '7.4', '>=' ) ? '' : 'Peça para a hospedagem atualizar o PHP para 8.1 ou mais novo.',
		'',
	);
	return $c;
}

/**
 * Problemas que contam (aviso e erro).
 */
function ap_health_problems( $checks = null ) {
	$checks = null === $checks ? ap_health_checks() : $checks;
	return array_filter(
		$checks,
		function ( $c ) {
			return in_array( $c[0], array( 'warn', 'bad' ), true );
		}
	);
}

/**
 * Número para o menu (só os erros; guardado por 10 minutos para não pesar).
 */
function ap_health_bad_count() {
	$n = get_transient( 'ap_health_bad' );
	if ( false === $n ) {
		$n = count(
			array_filter(
				ap_health_checks(),
				function ( $c ) {
					return 'bad' === $c[0];
				}
			)
		);
		set_transient( 'ap_health_bad', $n, 10 * MINUTE_IN_SECONDS );
	}
	return (int) $n;
}

/**
 * Alerta por e-mail: no máximo 1 por dia, só quando há erro (vermelho).
 */
add_action( 'ap_hourly', 'ap_health_alert', 90 );
function ap_health_alert() {
	$bad = array_filter(
		ap_health_checks(),
		function ( $c ) {
			return 'bad' === $c[0];
		}
	);
	delete_transient( 'ap_health_bad' );
	if ( ! $bad || get_option( 'ap_health_alert_day' ) === ap_today() ) {
		return;
	}
	update_option( 'ap_health_alert_day', ap_today(), false );
	$rows = array();
	foreach ( $bad as $b ) {
		$rows[ $b[1] ] = $b[2];
	}
	ap_mail( ap_setting( 'email' ), 'Painel: ' . count( $bad ) . ( 1 === count( $bad ) ? ' ponto precisa' : ' pontos precisam' ) . ' de atenção', 'Algo precisa de atenção no painel', 'A verificação automática encontrou:', $rows, 'Abrir Saúde do sistema', ap_panel_url( 'saude' ) );
}

function ap_do_health_run() {
	ap_require( 'admin' );
	set_transient( 'ap_catchup_lock', 1, 10 * MINUTE_IN_SECONDS );
	do_action( 'ap_hourly' );
	do_action( 'ap_daily' );
	delete_transient( 'ap_health_bad' );
	ap_back( 'Tarefas automáticas executadas agora.' );
}
