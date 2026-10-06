<?php
require '/tmp/wpt2/wp-load.php'; require_once ABSPATH.'wp-admin/includes/plugin.php'; global $wpdb;
$n0=$wpdb->get_var('SELECT COUNT(*) FROM '.ap_table('clients'));
deactivate_plugins('allprint-crm/allprint-crm.php'); echo "desativado: ".(is_plugin_active('allprint-crm/allprint-crm.php')?'NÃO':'sim')."\n";
echo "cron hourly: ".(wp_next_scheduled('ap_hourly')?'ainda agendado':'removido')."\n";
$r=activate_plugin('allprint-crm/allprint-crm.php'); echo "reativado: ".(is_wp_error($r)?$r->get_error_message():'ok')."\n";
echo "clientes preservados: ".($wpdb->get_var('SELECT COUNT(*) FROM '.ap_table('clients'))==$n0?'sim':'NÃO')."\n";
