<?php
require '/tmp/wpt2/wp-load.php'; wp_set_current_user(1); $F=0;
function t($c,$m){global $F; echo ($c?'PASS ':'FAIL ').$m."\n"; if(!$c)$F++;}
function up($path,$name,$type){ $tmp=tempnam(sys_get_temp_dir(),'up'); copy($path,$tmp); $r=new WP_REST_Request('POST','/ap/v1/upload/small'); $r->set_file_params(array('file'=>array('name'=>$name,'type'=>$type,'tmp_name'=>$tmp,'error'=>0,'size'=>filesize($tmp)))); return ap_upload_small($r); }
foreach([['a.pdf','application/pdf'],['a.jpg','image/jpeg'],['a.png','image/png'],['old.cdr','application/octet-stream'],['x8.cdr','application/octet-stream']] as [$n,$ty]){ $r=up("/tmp/art/$n",$n,$ty); t(!is_wp_error($r)&&strpos($r['id'],'wp-')===0,"hospedagem aceita $n".(is_wp_error($r)?' → '.$r->get_error_message():'')); }
file_put_contents('/tmp/art/evil.php','<?php echo 1;'); file_put_contents('/tmp/art/evil.jpg','<?php system($_GET["x"]);'); file_put_contents('/tmp/art/e.exe','MZ');  file_put_contents('/tmp/art/e.html','<script>1</script>'); file_put_contents('/tmp/art/e.svg','<svg onload=alert(1)>');
foreach(['evil.php','evil.jpg','e.exe','e.html','e.svg','evil.php.pdf'] as $n){ $src=$n==='evil.php.pdf'?'/tmp/art/evil.php':"/tmp/art/$n"; $r=up($src,$n,'application/octet-stream'); t(is_wp_error($r),"rejeita $n"); }
echo "FALHAS $F\n";
