<?php
define('WP_INSTALLING',true);
require '/tmp/wpt2/wp-load.php'; require ABSPATH.'wp-admin/includes/upgrade.php';
$r=wp_install('Teste','admin','admin@test.local',true,'','SENHA_DO_ADMIN_DE_TESTE','pt_BR');
update_option('active_plugins',array('allprint-crm/allprint-crm.php'));
update_option('permalink_structure','/%postname%/');
echo "installed\n";
