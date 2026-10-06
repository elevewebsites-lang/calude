<?php
require '/tmp/wpl/wp-load.php'; global $wpdb; $F=0;
function t($c,$m){global $F; echo ($c?'PASS ':'FAIL ').$m."\n"; if(!$c)$F++;}
lk_perms_migrate(); // idempotente
$roles=lk_team_roles();
t(count($roles)>=10,'perfis: '.implode(', ',array_map(function($r){return $r[0];},$roles)));
$uids=array();
foreach($roles as $k=>$r){
  $email="t_$k@teste.com"; $u=get_user_by('email',$email); if($u) {require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($u->ID);}
  $id=wp_insert_user(array('user_login'=>$email,'user_email'=>$email,'user_pass'=>'Teste#12345','display_name'=>'T '.$r[0],'role'=>'lk_team'));
  update_user_meta($id,'lk_func',$k); update_user_meta($id,'lk_perms',lk_role_areas($k)); $uids[$k]=$id;
}
// matriz rota×perfil
$map=array('clientes'=>'clientes','leads'=>'leads','orcamentos'=>'orcamentos','conteudo'=>'conteudo','agenda'=>'agenda','reunioes'=>'reunioes','formularios'=>'formularios','relatorios'=>'relatorios','trafego'=>'trafego','cobrancas'=>'financeiro','mensagens'=>'mensagens','emails'=>'emails','redes'=>'redes','contratos'=>'contratos','prospeccao'=>'prospeccao','financeiro'=>'financeiro','tarefas'=>'tarefas');
$exp=array(
 'designer'=>array('conteudo','reunioes','tarefas'),
 'redator'=>array('conteudo','reunioes','tarefas'),
 'videomaker'=>array('conteudo','reunioes','tarefas'),
 'social'=>array('conteudo','clientes','redes','relatorios','agenda','reunioes','tarefas'),
 'trafego'=>array('trafego','relatorios','clientes','redes','reunioes','tarefas'),
 'comercial'=>array('leads','prospeccao','orcamentos','clientes','contratos','agenda','reunioes','emails','tarefas'),
 'financeiro'=>array('financeiro','clientes','contratos','orcamentos','leads','reunioes','tarefas'),
 'atendimento'=>array('conteudo','clientes','contratos','formularios','mensagens','agenda','reunioes','relatorios','emails','leads','tarefas'),
 'outro'=>array('tarefas'),
);
foreach($exp as $role=>$allowed){
  $bad=array(); foreach($map as $route=>$area){ $can=lk_can($area,$uids[$role]); $should=in_array($area,$allowed,true); if($can!==$should) $bad[]=$route; }
  t(!$bad,"perfil $role: acesso às telas confere".($bad?' ERRO em '.implode(',',$bad):''));
}
$g=$uids['gestor']; $all=true; foreach($map as $a) $all=$all&&lk_can($a,$g); t($all,'gestor vê todas as áreas operacionais');
t(!lk_is_admin($g),'gestor não é admin (Equipe/Config continuam fechados)');
// migração: usuário antigo só com clientes+leads
if($x=get_user_by('email','old@teste.com')){require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($x->ID);} $wpdb->query('DELETE FROM '.lk_table('clients')." WHERE company IN ('Cli A','Cli B')"); $wpdb->query('DELETE FROM '.lk_table('meetings')." WHERE title IN ('R A','R B','Interna')");
$old=wp_insert_user(array('user_login'=>'old@teste.com','user_email'=>'old@teste.com','user_pass'=>'Teste#12345','role'=>'lk_team')); update_user_meta($old,'lk_perms',array('clientes','leads','tarefas')); delete_option('lk_perms_v2'); lk_perms_migrate();
$p=get_user_meta($old,'lk_perms',true); foreach(array('reunioes','agenda','contratos','formularios','redes','mensagens','prospeccao') as $a) t(in_array($a,$p,true),"migração: usuário antigo ganhou $a");
t(!in_array('financeiro',$p,true)&&!in_array('trafego',$p,true),'migração não dá financeiro/tráfego');
foreach($uids as $k=>$id) update_user_meta($id,'lk_perms',lk_role_areas($k)); // a migração mexeu nos usuários de teste: volta ao preset
// escopo clientes
$c1=lk_insert('clients',array('name'=>'Cli A','company'=>'Cli A','designer_id'=>$uids['designer'],'status'=>'ativo'));
$c2=lk_insert('clients',array('name'=>'Cli B','company'=>'Cli B','social_id'=>$uids['social'],'status'=>'ativo'));
update_user_meta($uids['designer'],'lk_only_clients',1); wp_set_current_user($uids['designer']);
t(lk_client_visible($c1)&&!lk_client_visible($c2),'só meus clientes: vê A, não vê B');
$ids=wp_list_pluck(lk_clients(),'id'); t(in_array($c1,$ids)&&!in_array($c2,$ids),'lk_clients() filtrado pela carteira');
// reuniões
$m1=lk_insert('meetings',array('client_id'=>$c1,'title'=>'R A','starts_at'=>gmdate('Y-m-d 15:00:00',strtotime('+1 day')),'status'=>'agendada','created_by'=>$uids['social']));
$m2=lk_insert('meetings',array('client_id'=>$c2,'title'=>'R B','starts_at'=>gmdate('Y-m-d 16:00:00',strtotime('+1 day')),'status'=>'agendada','created_by'=>$uids['social']));
$m3=lk_insert('meetings',array('client_id'=>0,'title'=>'Interna','starts_at'=>gmdate('Y-m-d 17:00:00',strtotime('+1 day')),'status'=>'agendada','created_by'=>$uids['comercial']));
$titles=wp_list_pluck(lk_meetings_upcoming(20),'title'); t(in_array('R A',$titles)&&in_array('Interna',$titles)&&!in_array('R B',$titles),'reuniões: carteira + internas, sem a de cliente alheio ('.implode('/',$titles).')');
t(lk_meeting_visible(lk_get('meetings',$m1))&&!lk_meeting_visible(lk_get('meetings',$m2)),'reunião de cliente alheio não abre');
update_user_meta($uids['designer'],'lk_only_clients',0); update_user_meta($uids['designer'],'lk_only_meet',1);
$titles=wp_list_pluck(lk_meetings_upcoming(20),'title'); t(!$titles,'só minhas reuniões: designer não marcou nenhuma → lista vazia');
// sem permissão de reuniões
update_user_meta($uids['designer'],'lk_only_meet',0); update_user_meta($uids['designer'],'lk_perms',array('conteudo','tarefas')); 
t(lk_meetings_widget_html()==='' && lk_meetings_card_html()==='','sem a permissão Reuniões: widgets vazios');
// ações
add_filter('wp_die_handler',function(){return function($m,$t='',$a=array()){throw new Exception('DIE');};});
$die=function($fn,$uid,$post=array()){ wp_set_current_user($uid); $_POST=$post; try{ob_start();$fn();ob_end_clean();return false;}catch(Throwable $e){ob_end_clean();return strpos($e->getMessage(),'DIE')===0;} };
t($die('lk_do_reuniao_save',$uids['designer'],array('title'=>'x','date'=>'2030-01-01','time'=>'10:00')),'ação reunião: designer sem permissão é barrado');
t($die('lk_do_team_save',$uids['gestor'],array('name'=>'x','email'=>'x@x.com','senha'=>'12345678')),'gestor não salva equipe (só admin)');
t($die('lk_do_settings_save',$uids['gestor'],array()),'gestor não altera configurações');
foreach(array('lk_do_contract_save'=>'designer','lk_do_form_save'=>'comercial','lk_do_social_disconnect'=>'designer','lk_do_lead_save'=>'designer','lk_do_briefing_send'=>'comercial','lk_do_post_publish_now'=>'financeiro') as $fn=>$role){ if(function_exists($fn)) t($die($fn,$uids[$role],array()),"ação $fn barrada para $role"); else echo "n/a $fn\n"; }
echo "FALHAS $F\n";
