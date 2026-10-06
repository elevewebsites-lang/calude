<?php
require '/tmp/wpt2/wp-load.php'; require_once ABSPATH.'wp-admin/includes/plugin.php';
update_option('active_plugins',array());
$r=activate_plugin('allprint-crm/allprint-crm.php'); var_dump(is_wp_error($r)?$r->get_error_message():'ok');
flush_rewrite_rules();
global $wpdb; echo count($wpdb->get_col("SHOW TABLES LIKE '%ap\\_%'")),"tables\n";
echo ap_count_rows ?? '';
