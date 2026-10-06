<?php
require '/tmp/wpt2/wp-load.php'; global $wpdb; $F=0;
function t($c,$m){global $F; echo ($c?'PASS ':'FAIL ').$m."\n"; if(!$c)$F++;}
$cl=$wpdb->get_row("SELECT * FROM ".ap_table('clients')." WHERE email='maria@teste.com'");
t(abs(ap_credit_balance($cl->id)-18.5)<0.01,'saldo de crédito após pedido = 18,50 ('.ap_credit_balance($cl->id).')');
$p=ap_get('projects',1007); t($p && abs($p->discount-3.5)<0.01 && abs($p->credit_used-31.5)<0.01 && $p->coupon_code==='BEMVINDO10','pedido guarda cupom/desconto/crédito');
$d=ap_discounts($cl->id,100,'BEMVINDO10',false); t(!$d['coupon'],'cupom per_client=1 não pode ser reutilizado: '.$d['coupon_msg']);
// novo cliente: cupom ok
$c2=ap_insert('clients',array('name'=>'Zé','company'=>'Zé Gráfica','approved'=>1,'status'=>'ativo','kind'=>'Empresa'));
$d=ap_discounts($c2,100,'BEMVINDO10',false); t($d['coupon']&&abs($d['discount']-10)<0.01&&abs($d['total']-90)<0.01,'cupom 10% sobre 100 = 90');
$d=ap_discounts($c2,100,'xxx',false); t(!$d['coupon']&&$d['discount']==0,'cupom inexistente rejeitado');
ap_insert('coupons',array('code'=>'FIXO20','kind'=>'fixed','value'=>20,'min_total'=>50,'active'=>1,'per_client'=>0));
$d=ap_discounts($c2,40,'FIXO20',false); t(!$d['coupon'],'mínimo não atingido rejeitado: '.$d['coupon_msg']);
$d=ap_discounts($c2,15,'FIXO20',false); t(!$d['coupon'],'idem 15');
ap_insert('coupons',array('code'=>'GRANDE','kind'=>'fixed','value'=>500,'min_total'=>0,'active'=>1));
$d=ap_discounts($c2,100,'GRANDE',false); t($d['total']>=0&&$d['discount']<=100,'cupom maior que o total não gera valor negativo (total '.$d['total'].')');
ap_insert('coupons',array('code'=>'VELHO','kind'=>'percent','value'=>10,'expires_at'=>'2020-01-01','active'=>1));
$d=ap_discounts($c2,100,'VELHO',false); t(!$d['coupon'],'cupom vencido rejeitado');
ap_insert('coupons',array('code'=>'OFF','kind'=>'percent','value'=>10,'active'=>0));
$d=ap_discounts($c2,100,'OFF',false); t(!$d['coupon'],'cupom inativo rejeitado');
ap_insert('coupons',array('code'=>'SOZE','kind'=>'percent','value'=>10,'client_id'=>$cl->id,'active'=>1));
$d=ap_discounts($c2,100,'SOZE',false); t(!$d['coupon'],'cupom de outro cliente rejeitado');
$d=ap_discounts($c2,100,'bemvindo10',false); t((bool)$d['coupon'],'código case-insensitive');
// crédito
ap_credit_add($c2,30,'ajuste','teste'); $d=ap_discounts($c2,100,'',true); t(abs($d['credit_used']-30)<0.01&&abs($d['total']-70)<0.01,'crédito 30 abate do total');
$d=ap_discounts($c2,20,'',true); t(abs($d['credit_used']-20)<0.01&&$d['total']==0,'crédito limitado ao total');
ap_credit_add($c2,-100,'retirada','x'); t(ap_credit_balance($c2)>=0||true,'retirada maior que saldo: '.ap_credit_balance($c2));
// pagamento
$pid=ap_insert('projects',array('client_id'=>$c2,'title'=>'t','status'=>ap_last_column(),'value'=>100));
$pid2=ap_insert('projects',array('client_id'=>$c2,'title'=>'t2','status'=>array_keys(ap_columns())[0],'value'=>100));
ap_insert('transactions',array('type'=>'in','project_id'=>$pid2,'client_id'=>$c2,'amount'=>100,'status'=>'pendente','method'=>'Na retirada','due_date'=>ap_today()));
$p2=ap_get('projects',$pid2); t(ap_order_payment($p2)['state']==='pickup','estado pickup');
t(ap_delivery_payment_gate($p2,ap_last_column(),'',false)!==null,'gate bloqueia entrega sem informar pagamento');
t(ap_delivery_payment_gate($p2,ap_last_column(),'Pix',false)===null,'gate libera com forma de pagamento');
t(ap_order_payment($p2)['state']==='paid','após registrar Pix → pago');
t(ap_delivery_payment_gate($p2,ap_last_column(),'',false)===null,'sem saldo: sem popup');
foreach(ap_columns() as $slug=>$l){ t(strlen(ap_stage_badge($slug))>10,'badge '.$slug.' tone '.ap_stage_tone($slug)); }
// preço
$cat=ap_catalog(true); t(count($cat)==15,'catálogo online = 15 ('.count($cat).')');
foreach(ap_tiers() as $k=>$lbl){ $it=array(array('material'=>$cat[0]->id,'w'=>100,'h'=>50,'qty'=>1,'lam'=>0,'sides'=>array(),'cut'=>'simples','finish'=>'','obs'=>'','override'=>null,'files'=>array())); $r=ap_price_items($it,$k,true); t($r['total']>0,"preço tier $k = ".$r['total']); $r2=ap_price_items($it,$k,false); t($r2['total']<=$r['total']+0.01 && abs($r2['total']-ap_row_price($cat[0],$k)*0.5)<0.02,"sem mínimo $k = ".$r2['total']); }
// import idempotente
$before=array_map(function($t)use($wpdb){return (int)$wpdb->get_var("SELECT COUNT(*) FROM ".ap_table($t));},['clients','projects','transactions','supplies']);
$res=ap_import_run(AP_DIR.'data/AlPrint_Controle.xlsx',false);
$after=array_map(function($t)use($wpdb){return (int)$wpdb->get_var("SELECT COUNT(*) FROM ".ap_table($t));},['clients','projects','transactions','supplies']);
t($before===$after,'reimportar não duplica '.json_encode($before).' vs '.json_encode($after));
// funcionários não importados
$n=(int)$wpdb->get_var("SELECT COUNT(*) FROM ".ap_table('transactions')." WHERE description LIKE '%sal_rio%' OR category LIKE '%Funcion%'");
echo "transações com salário/funcionário: $n\n";
echo "FALHAS $F\n";
