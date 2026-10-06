<?php
define('ABSPATH','/'); define('AP_URL','/x/'); define('AP_VERSION','1.2.0');
$GLOBALS['opts']=[];
function esc_attr($s){return htmlspecialchars($s);} function esc_url($s){return $s;} function get_option($k,$d=false){return $GLOBALS['opts'][$k]??$d;}
function ap_setting($k){return $GLOBALS['set'][$k]??($k=='empresa'?'AllPrint':'');}
function ap_identity(){return ['tema'=>$GLOBALS['tema']];}
require '/home/user/calude/allprint-crm/includes/brand.php'; require '/home/user/calude/allprint-crm/includes/paystatus.php';
$themes=[
 'AllPrint (azul + amarelo)'=>['ink'=>'#15132B','bg'=>'#F5F5F7','accent'=>'#FDCC2A','side'=>'#15132B'],
 'Tema claro de teste (sidebar branca, acento azul)'=>['ink'=>'#0b2a4a','bg'=>'#ffffff','accent'=>'#2563eb','side'=>'#ffffff'],
 'Acento verde-limão fraco'=>['ink'=>'#111111','bg'=>'#f4f4f2','accent'=>'#b7f000','side'=>'#111111'],
 'Sidebar cinza médio'=>['ink'=>'#1f2937','bg'=>'#f3f4f6','accent'=>'#f59e0b','side'=>'#6b7280'],
];
$fail=0;
foreach($themes as $n=>$t){ $GLOBALS['tema']=$t+['font'=>'Inter']; $GLOBALS['x']=null;
  // limpa cache estático rodando em processo novo por tema
  $cmd='php -r '.escapeshellarg('1;').'';
}
if($argc>1){ $t=json_decode($argv[1],true); $GLOBALS['tema']=$t; $rows=ap_contrast_report(); $bad=0;
  foreach($rows as $r){ if(!$r['ok']){$bad++; printf("  FALHA [%s] %-62s %.2f < %.1f (%s sobre %s)\n",$r['mode'],$r['label'],$r['ratio'],$r['min'],$r['fg'],$r['bg']);} }
  $tok=ap_brand_tokens(); echo "  tokens claro: ".json_encode($tok['light'])."\n  tokens escuro: ".json_encode($tok['dark'])."\n";
  foreach(ap_surfaces() as $k=>$s){ echo "  logo $k: claro=".ap_logo_variant($s[1])." escuro=".ap_logo_variant($s[2])."\n"; }
  echo "  verificações: ".count($rows)." · falhas: $bad\n"; exit($bad?1:0);}
foreach($themes as $n=>$t){ echo "== $n\n"; passthru('php '.escapeshellarg(__FILE__).' '.escapeshellarg(json_encode($t+['font'=>'Inter'])),$rc); if($rc) $fail++; }
echo $fail? "TEMAS COM FALHA: $fail\n":"TODOS OS TEMAS OK\n";
