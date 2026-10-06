<?php
require '/tmp/wpt2/wp-load.php'; $F=0;
add_filter('wp_die_handler',function(){return function($m,$t='',$a=array()){throw new Exception('DIE:'.(is_string($m)?$m:'x'));};});
$u=get_user_by('email','maria@teste.com'); wp_set_current_user($u->ID);
foreach(['coupon_save','coupon_delete','credit_add','import','client_kind','order_pay','manual_order','client_approve_partner','client_pay_later','order_move','stock_adjust','supply_save'] as $d){
 $fn='ap_do_'.$d; if(!function_exists($fn)){echo "n/a $d\n";continue;}
 $_POST=array('id'=>1,'client_id'=>1,'amount'=>999,'code'=>'HACK','value'=>10);
 try{ob_start();$fn();ob_end_clean();echo "FAIL $d executou como cliente\n";$F++;}catch(Throwable $e){ob_end_clean();echo (strpos($e->getMessage(),'DIE:')===0?'PASS ':'?? ').$d.' → '.substr($e->getMessage(),0,50)."\n";}
}
echo "FALHAS $F\n";
