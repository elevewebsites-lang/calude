<?php require "/tmp/wpt4/wp-load.php";
global $wpdb;
// ---------- Google simulado ----------
$GLOBALS['G'] = array('sheet'=>array('Pedidos'=>array(),'Clientes'=>array(),'Custos'=>array()), 'calls'=>array(), 'drive_files'=>array(), 'limit'=>1000*1048576, 'usage'=>990*1048576);
function g_col($s){ $n=0; foreach(str_split($s) as $c) $n=$n*26+ord($c)-64; return $n; }
function g_range($r){ // 'Pedidos!A2:Y2' ou 'Pedidos!Q2:Q' ou 'Pedidos!A1:Z'
  preg_match('/^([^!]+)!([A-Z]+)(\d+)?(?::([A-Z]+)(\d+)?)?$/',$r,$m); return array('tab'=>$m[1],'c1'=>g_col($m[2]),'r1'=>(int)($m[3]?:1),'c2'=>isset($m[4])&&$m[4]?g_col($m[4]):g_col($m[2]),'r2'=>isset($m[5])&&$m[5]!==''?(int)$m[5]:99999); }
function g_put(&$sheet,$rg,$values){ $r=$rg['r1']; foreach($values as $row){ $c=$rg['c1']; foreach($row as $v){ $sheet[$r][$c]=$v; $c++; } $r++; } }
add_filter('ap_google_api_pre', function($pre,$method,$url,$body,$raw){
  $G=&$GLOBALS['G']; $G['calls'][]=$method.' '.preg_replace('#https://[^/]+#','',$url);
  $base='https://sheets.googleapis.com/v4/spreadsheets';
  if ($url===$base && $method==='POST') return array('spreadsheetId'=>'SHEET1','spreadsheetUrl'=>'https://docs.google.com/spreadsheets/d/SHEET1','sheets'=>array(array('properties'=>array('title'=>'Pedidos','sheetId'=>11)),array('properties'=>array('title'=>'Clientes','sheetId'=>22)),array('properties'=>array('title'=>'Custos','sheetId'=>33))));
  if (strpos($url,$base.'/SHEET1')===0) {
    $path=substr($url,strlen($base.'/SHEET1'));
    if ($path===':batchUpdate') { return array('replies'=>array(array('addSheet'=>array('properties'=>array('sheetId'=>44))))); }
    if ($path==='/values:batchClear') { foreach($body['ranges'] as $r){ $g=g_range($r); foreach($G['sheet'][$g['tab']] as $row=>$cols){ if($row>=$g['r1']&&$row<=$g['r2']) foreach($cols as $c=>$_) if($c>=$g['c1']&&$c<=$g['c2']) unset($G['sheet'][$g['tab']][$row][$c]); } } return array(); }
    if (preg_match('#^/values/([^?]+)\?valueInputOption#',$path,$m) && $method==='PUT') { $g=g_range(rawurldecode($m[1])); g_put($G['sheet'][$g['tab']],$g,$body['values']); return array('updatedRows'=>count($body['values'])); }
    if (preg_match('#^/values/([^?:]+)$#',$path,$m) && $method==='GET') { $g=g_range(rawurldecode($m[1])); $vals=array(); $max=0; foreach($G['sheet'][$g['tab']] as $row=>$cols) if($row>=$g['r1']) $max=max($max,$row); for($r=$g['r1'];$r<=$max;$r++) $vals[]=isset($G['sheet'][$g['tab']][$r][$g['c1']])?array($G['sheet'][$g['tab']][$r][$g['c1']]):array(); return array('values'=>$vals); }
    if ($path==='/values:batchUpdate') { foreach($body['data'] as $d){ $g=g_range($d['range']); g_put($G['sheet'][$g['tab']],$g,$d['values']); } return array(); }
    if (preg_match('#^/values/([^:]+):append#',$path) ) { $rows=$G['sheet']['Pedidos']; $last=$rows?max(array_keys($rows)):0; $start=$last+1; $g=array('r1'=>$start,'c1'=>1); g_put($G['sheet']['Pedidos'],$g,$body['values']); return array('updates'=>array('updatedRange'=>'Pedidos!A'.$start.':Y'.($start+count($body['values'])-1))); }
  }
  if (strpos($url,'drive/v3/about')!==false) return array('storageQuota'=>array('limit'=>(string)$G['limit'],'usage'=>(string)$G['usage']));
  if (strpos($url,'drive/v3/files')!==false && $method==='POST' && strpos($url,'upload')===false) return array('id'=>'FOLDER'.count($G['drive_files']),'webViewLink'=>'https://drive.google.com/drive/folders/x');
  if (strpos($url,'upload/drive/v3/files')!==false) { $n=count($G['drive_files'])+1; $G['drive_files'][]=$n; return array('id'=>'FILE'.$n,'name'=>'enviado.pdf','size'=>'1234','webViewLink'=>'https://drive.google.com/file/d/FILE'.$n); }
  if (strpos($url,'drive/v3/files/')!==false && $method==='PATCH') return array();
  return null;
}, 10, 5);
update_option('ap_google', array('refresh'=>ap_encrypt('r'),'access'=>ap_encrypt('a'),'expires'=>time()+3000,'email'=>'teste@gmail.com','scope'=>'openid email https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/spreadsheets'));
delete_option('ap_sheet'); delete_option('ap_sheet_queue');
function ok($c,$m){ echo ($c?'  ✓ ':'  ✗ FALHOU: ').$m."\n"; if(!$c) $GLOBALS['fails']=($GLOBALS['fails']??0)+1; }

echo "== Planilha do Google ==\n";
ok(ap_sheets_scope_ok(),'escopo de planilhas reconhecido');
$n=ap_sheets_full();
ok(!is_wp_error($n),'carga inicial sem erro'.(is_wp_error($n)?' ('.$n->get_error_message().')':''));
$G=&$GLOBALS['G']; $ped=$G['sheet']['Pedidos'];
$expected=0; foreach(ap_projects('1=1') as $p) $expected+=max(1,count(ap_order_items($p)));
ok(count($ped)===$expected+1, "linhas na aba Pedidos = $expected itens + cabeçalho (".count($ped).")");
ok($ped[1][1]==='Data' && $ped[1][17]==='ID Pedido', 'cabeçalho no formato da planilha antiga (coluna Q = ID Pedido)');
$vals=0.0; foreach($ped as $i=>$r){ if($i>1 && isset($r[9])) $vals+=(float)$r[9]; }
$dbsum=0.0; foreach(ap_projects('1=1') as $p){ $its=ap_order_items($p); if(count($its)<=1) $dbsum+=(float)$p->value; else foreach($its as $it) $dbsum+=round((float)($it['unit']??0)*max(1,(int)($it['qty']??1)),2); }
ok(abs($vals-$dbsum)<0.01, 'soma de "Valor Cobrado" da planilha = soma dos itens no sistema (R$ '.number_format($vals,2,',','.').')');
$imp=0.0; foreach($ped as $i=>$r){ if($i>1 && strpos((string)($r[17]??''),'PED-')===0) $imp+=(float)$r[9]; }
ok(abs($imp-337992.21)<0.01,'pedidos importados somam R$ 337.992,21, igual à planilha original ('.number_format($imp,2,',','.').')');
ok(count($G['sheet']['Clientes'])===93, 'aba Clientes: 92 clientes + cabeçalho ('.count($G['sheet']['Clientes']).')');
ok(ap_sheets_enabled(),'sincronização ligada');

echo "== Mudança no pedido atualiza a linha ==\n";
$items2=wp_json_encode(array(array('kind'=>'impressao','name'=>'Banner','qty'=>1,'unit'=>45,'cost'=>10,'w'=>100,'h'=>100,'area'=>1),array('kind'=>'impressao','name'=>'Adesivo','qty'=>2,'unit'=>30,'cost'=>8,'w'=>50,'h'=>50,'area'=>0.5)));
$pid=ap_insert('projects',array('client_id'=>1,'title'=>'Teste 2 itens','status'=>'revisao','value'=>105,'items'=>$items2,'start_date'=>ap_today(),'created_at'=>ap_now()));
ap_insert('transactions',array('type'=>'in','project_id'=>$pid,'client_id'=>1,'category'=>'Pedido','description'=>'x','amount'=>105,'status'=>'pago','method'=>'Pix','paid_at'=>ap_today(),'due_date'=>ap_today())); ap_sheets_flush(); $key='AP-'.$pid;
$find=function() use (&$G,&$key){ $out=array(); foreach($G['sheet']['Pedidos'] as $i=>$r) if(($r[17]??'')===$key) $out[$i]=$r; return $out; };
$before=$find(); ok(count($before)===2,'pedido 1007 tem 2 itens na planilha'); $row=reset($before);
echo "     antes: status='{$row[11]}' pago='{$row[15]}' etapa='{$row[18]}' situação='{$row[19]}'\n";
$calls0=count($G['calls']);
ap_update('projects',$pid,array('status'=>'producao')); ap_sheets_flush();
$after=$find(); $row=reset($after);
ok($row[11]==='Produção' && $row[18]==='Produção','etapa mudou para Produção na planilha ('.$row[11].')');
ok(count($after)===2,'continua com 2 linhas (não duplicou)');
// volta o pedido para 'pendente' e registra o pagamento de novo
$wpdb->query('UPDATE '.ap_table('transactions')." SET status='pendente', paid_at=NULL, method='Na retirada' WHERE project_id=$pid AND status='pago' AND method<>'Crédito na loja'"); ap_order_payment_reset(); ap_update("projects",$pid,array("notes"=>"reaberto")); ap_sheets_flush(); $after=$find(); $row=reset($after);
ok($row[15]==='Não' && strpos($row[19],'Paga na retirada')===0,'com saldo em aberto a planilha mostra Pago?=Não e "Paga na retirada" ('.$row[15].' / '.$row[19].')');
ap_order_register_payment($pid,'Pix'); ap_sheets_flush(); $after=$find(); $row=reset($after);
ok($row[15]==='Sim' && $row[13]==='Pix','pagamento registrado: Pago?=Sim, forma=Pix na planilha');
echo "     depois: status='{$row[11]}' pago='{$row[15]}' forma='{$row[13]}' situação='{$row[19]}' a receber={$row[22]}\n";
// pedido novo
$new=ap_insert('projects',array('client_id'=>1,'title'=>'Pedido novo de teste','status'=>'aguardando-pagamento','value'=>45,'items'=>wp_json_encode(array(array('kind'=>'impressao','name'=>'Banner','qty'=>1,'unit'=>45,'cost'=>10,'w'=>100,'h'=>100,'area'=>1))),'start_date'=>ap_today(),'created_at'=>ap_now()));
ap_sheets_flush(); $key='AP-'.$new; $rowsNew=$find();
ok(count($rowsNew)===1,'pedido novo apareceu como linha nova (AP-'.$new.')');
ap_update('projects',$new,array('status'=>'entregue')); ap_sheets_flush(); $rowsNew=$find(); $r=reset($rowsNew);
ok($r[11]==='Entregue','pedido novo: status Entregue ('.$r[11].')');
$tot=count($G['sheet']['Pedidos']); ap_update('projects',$new,array('title'=>'Renomeado')); ap_sheets_flush(); ok(count($G['sheet']['Pedidos'])===$tot,'editar não cria linhas extras');
echo "     chamadas ao Google nesta rodada: ".(count($G['calls'])-$calls0)."\n";

echo "== Arquivos do cliente: Drive primeiro, hospedagem só sem espaço ==\n";
wp_set_current_user(1);
$mk=function($name){ $f=tempnam(sys_get_temp_dir(),'up'); file_put_contents($f,'%PDF-1.4 teste '.str_repeat('x',500)); $r=new WP_REST_Request('POST','/ap/v1/upload/small'); $r->set_file_params(array('file'=>array('name'=>$name,'type'=>'application/pdf','tmp_name'=>$f,'error'=>0,'size'=>filesize($f)))); return $r; };
// Drive cheio (limite 1000 MB, usado 990 MB → restam 10 MB < 50 MB de margem)
delete_transient('ap_drive_quota');
$st=ap_upload_start(new WP_REST_Request('POST','/ap/v1/upload/start')); // sem params → inválido
$rq=new WP_REST_Request('POST','/ap/v1/upload/start'); $rq->set_param('name','arte.pdf'); $rq->set_param('size',2000000); $rq->set_param('type','application/pdf');
$st=ap_upload_start($rq); ok($st['mode']==='small' && $st['why']==='drive-full','Drive sem espaço → start manda para a hospedagem (why='.$st['why'].')');
$res=ap_upload_small($mk('arte.pdf')); ok(!is_wp_error($res) && !empty($res['hosted']) && 0===strpos($res['id'],'wp-'),'arquivo ficou na hospedagem: '.(is_wp_error($res)?$res->get_error_message():$res['id']));
$aid=(int)substr($res['id'],3); ok((int)get_post_meta($aid,'_ap_pending_drive',true)===1,'marcado para migrar ao Drive');
ok(file_exists(get_attached_file($aid)),'arquivo físico está na hospedagem');
ok(ap_hosted_pending()['n']>=1,'aviso de "arquivo na hospedagem" conta 1');
// pedido apontando para o arquivo na hospedagem
$pf=ap_insert('projects',array('client_id'=>1,'title'=>'Com arquivo','status'=>'revisao-allprint','items'=>wp_json_encode(array(array('name'=>'Banner','qty'=>1,'files'=>array(array('id'=>$res['id'],'name'=>'arte.pdf','link'=>$res['link'])))))));
// Drive liberou espaço
$G['usage']=100*1048576; delete_transient('ap_drive_quota');
$rq->set_param('size',2000000); $st=ap_upload_start($rq); ok($st['mode']==='small' || $st['mode']==='drive','com espaço o start tenta o Drive (modo '.$st['mode'].')');
$moved=ap_drive_migrate_hosted(); ok($moved===1,'migração movimentou 1 arquivo para o Drive');
ok(!file_exists($f=get_attached_file($aid)) && !get_post($aid),'cópia da hospedagem foi apagada');
$it=json_decode(ap_get('projects',$pf)->items,true); $fl=$it[0]['files'][0];
ok(0===strpos($fl['id'],'FILE') && strpos($fl['link'],'drive.google.com')!==false,'pedido agora aponta para o Drive: '.$fl['id']);
// Drive com espaço: upload pequeno vai direto ao Drive, sem tocar na hospedagem
$before=wp_count_posts('attachment')->inherit; $res=ap_upload_small($mk('direto.pdf')); $after=wp_count_posts('attachment')->inherit;
ok(!is_wp_error($res) && 0===strpos($res['id'],'FILE') && $before===$after,'com espaço, o arquivo vai direto ao Drive e nada entra na hospedagem ('.$res['id'].')');
echo "== Aba Custos (financeiro + veículos) ==\n";
$vid=ap_insert('vehicles',array('name'=>'Fiorino','plate'=>'ABC1D23','active'=>1));
$nrows=count($G['sheet']['Custos']);
ap_insert('transactions',array('type'=>'out','category'=>AP_VEHICLE_CAT,'description'=>'Combustível · Fiorino','amount'=>250,'status'=>'pago','paid_at'=>ap_today(),'due_date'=>ap_today(),'vehicle_id'=>$vid,'vtype'=>'Combustível','odometer'=>1000,'liters'=>40));
ap_sheets_flush();
$cust=$G['sheet']['Custos'];
ok(count($cust)>=2,'aba Custos tem cabeçalho e linhas ('.count($cust).')');
$found=false; foreach($cust as $r){ if(($r[4]??'')==='Fiorino · ABC1D23' && ($r[5]??'')==='Combustível') $found=true; }
ok($found,'custo de combustível do veículo aparece na aba Custos');
ok(($cust[1][2]??'')==='Categoria','cabeçalho da aba Custos');
// planilha antiga sem a aba Custos
$st=get_option('ap_sheet'); unset($st['sheets']['Custos']); update_option('ap_sheet',$st,false);
ap_insert('transactions',array('type'=>'out','category'=>'Material','description'=>'Teste custo','amount'=>10,'status'=>'pendente','due_date'=>ap_today())); ap_sheets_flush();
$st=get_option('ap_sheet'); ok(!empty($st['sheets']['Custos']),'planilha antiga ganha a aba Custos sozinha');


echo "\n".(empty($GLOBALS['fails'])?"TUDO OK":"FALHAS: ".$GLOBALS['fails'])."\n";