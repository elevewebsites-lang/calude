const {chromium}=require('/opt/node22/lib/node_modules/playwright');
const fs=require('fs');const B='http://localhost:8085';let F=0;const ok=(c,m)=>{console.log((c?'PASS ':'FAIL ')+m);if(!c)F++;};
const q=(c)=>{fs.writeFileSync('/tmp/qq.php','<?php require "/tmp/wpl/wp-load.php"; global $wpdb; '+c);return require('child_process').execSync('php /tmp/qq.php 2>/dev/null').toString().trim();};
const routes={clientes:'clientes',leads:'leads',orcamentos:'orcamentos',conteudo:'conteudo',agenda:'agenda',reunioes:'reunioes',formularios:'formularios',relatorios:'relatorios',trafego:'trafego',cobrancas:'financeiro',mensagens:'mensagens',emails:'emails',redes:'redes',contratos:'contratos',prospeccao:'prospeccao',financeiro:'financeiro',tarefas:'tarefas',equipe:'admin',config:'admin',saude:'admin',metas:'admin',gamificacao:'admin',chat:'',foco:'',ranking:'',apontamentos:'',time:'',ajuda:''};
const presets={designer:['conteudo','reunioes','tarefas'],social:['conteudo','clientes','redes','relatorios','agenda','reunioes','tarefas'],comercial:['leads','prospeccao','orcamentos','clientes','contratos','agenda','reunioes','emails','tarefas'],financeiro:['financeiro','clientes','contratos','orcamentos','leads','reunioes','tarefas'],atendimento:['conteudo','clientes','contratos','formularios','mensagens','agenda','reunioes','relatorios','emails','leads','tarefas'],gestor:['clientes','leads','orcamentos','conteudo','agenda','reunioes','formularios','relatorios','trafego','mensagens','emails','redes','contratos','prospeccao','financeiro','tarefas']};
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
const login=async(email,pw)=>{const c=await b.newContext({viewport:{width:1400,height:900}});const p=await c.newPage();p.on('pageerror',e=>errs.push(email+' JS '+e.message));p.on('dialog',d=>d.accept());await p.goto(B+'/entrar/');await p.fill('input[name=email]',email);await p.fill('input[name=senha]',pw);await Promise.all([p.waitForNavigation(),p.click('button[type=submit]')]);return p;};
const errs=[];
// re-sincroniza perms dos usuários de teste com os presets
q("foreach(lk_team_roles() as $k=>$r){ $u=get_user_by('email','t_'.$k.'@teste.com'); if($u){update_user_meta($u->ID,'lk_perms',lk_role_areas($k)); delete_user_meta($u->ID,'lk_only_clients'); delete_user_meta($u->ID,'lk_only_meet'); delete_user_meta($u->ID,'lk_only_own');} }");
for(const [role,allowed] of Object.entries(presets)){
 const p=await login('t_'+role+'@teste.com','Teste#12345');
 let bad=[];
 for(const [r,area] of Object.entries(routes)){
  const resp=await p.goto(B+'/painel/'+r+'/');const st=resp.status();const t=await p.locator('body').innerText();
  const denied=/não tem acesso a esta área/i.test(t);const shouldAllow=area===''||allowed.includes(area);
  if(denied===shouldAllow||(area==='admin'&&!denied)) bad.push(r+(denied?'(negado)':'(aberto)'));
  if(/Fatal error|Warning:|Notice:/.test(t)) bad.push(r+'(erro php)');
 }
 // menu só com o permitido
 await p.goto(B+'/painel/');
 const hrefs=await p.locator('aside a[href*="/painel/"], .side a[href*="/painel/"], nav a[href*="/painel/"]').evaluateAll(a=>a.map(x=>x.getAttribute('href').replace(/.*\/painel\//,'').replace(/\/.*/,'')));
 const menuBad=[...new Set(hrefs)].filter(h=>h&&routes[h]!==undefined&&routes[h]!==''&&!allowed.includes(routes[h]));
 ok(!bad.length,`${role}: telas permitidas abrem e as demais mostram "sem acesso"${bad.length?' → '+bad.join(','):''}`);
 ok(!menuBad.length,`${role}: menu não mostra itens sem permissão${menuBad.length?' → '+menuBad.join(','):''}`);
 await p.context().close();
}
console.log('erros JS:',errs.join('|')||'nenhum','FALHAS',F);await b.close();})().catch(e=>{console.log('EXC',e.message.slice(0,300));process.exit(1)});
