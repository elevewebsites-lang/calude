const {chromium}=require('/opt/node22/lib/node_modules/playwright');
const fs=require('fs');const B='http://localhost:8085';let F=0;const ok=(c,m)=>{console.log((c?'PASS ':'FAIL ')+m);if(!c)F++;};
const q=(c)=>{fs.writeFileSync('/tmp/qq.php','<?php require "/tmp/wpl/wp-load.php"; global $wpdb; '+c);return require('child_process').execSync('php /tmp/qq.php 2>/dev/null').toString().trim();};
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});const errs=[];
const login=async(email,pw,w=1400)=>{const c=await b.newContext({viewport:{width:w,height:1000}});const p=await c.newPage();p.on('pageerror',e=>errs.push(email+' JS '+e.message));p.on('dialog',d=>d.accept());await p.goto(B+'/entrar/');await p.fill('input[name=email]',email);await p.fill('input[name=senha]',pw);await Promise.all([p.waitForNavigation(),p.click('button[type=submit]')]);return p;};
q("$u=get_user_by('email','dani@teste.com'); if($u){require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($u->ID);} $wpdb->query('DELETE FROM '.lk_table('clients').\" WHERE company IN ('Cliente da Dani','Cliente do Zé')\"); $wpdb->query('DELETE FROM '.lk_table('meetings').\" WHERE title LIKE 'Reunião teste%'\");");
const a=await login('admin','SENHA_DO_ADMIN_DE_TESTE');
await a.goto(B+'/painel/equipe/');
let t=await a.locator('body').innerText();
ok(/Perfis/.test(t)&&/Gestor \/ coordenação/.test(t)&&/Comercial \/ vendas/.test(t)&&/Redator/.test(t),'tela Equipe lista os perfis');
await a.click('button:has-text("Adicionar pessoa")');
const m='#membro-0';
await a.fill(m+' [name=name]','Dani Designer');await a.fill(m+' [name=email]','dani@teste.com');await a.fill(m+' [name=senha]','Dani#12345');
await a.selectOption(m+' [data-func-preset]','designer');
const marked=await a.locator(m+' input[name="perms[]"]:checked').evaluateAll(x=>x.map(i=>i.value).sort());
ok(JSON.stringify(marked)===JSON.stringify(['conteudo','reunioes','tarefas']),'escolher Designer marca conteúdo, reuniões e tarefas: '+marked.join(','));
ok(await a.locator(m+' fieldset legend').count()>=6,'permissões agrupadas por seção');
await a.check(m+' [name=only_clients]');await a.check(m+' [name=only_own]');
await Promise.all([a.waitForNavigation(),a.click(m+' button[type=submit]')]);
const uid=q("echo get_user_by('email','dani@teste.com')->ID;");
ok(q("echo json_encode(get_user_meta("+uid+",'lk_perms',true));").includes('conteudo'),'pessoa criada com as permissões do perfil');
ok(q("echo get_user_meta("+uid+",'lk_only_clients',true);")==='1'&&q("echo get_user_meta("+uid+",'lk_only_own',true);")==='1','escopos salvos (só meus clientes / tarefas)');
t=await a.locator('body').innerText();ok(/Dani Designer/.test(t)&&/Limitado: só as tarefas dela · só os clientes dela/.test(t),'lista mostra os limites da pessoa');
// copiar de
await a.click('button:has-text("Adicionar pessoa")');
const opts=await a.locator(m+' [data-copy-from] option').allInnerTexts();ok(opts.some(o=>/Dani/.test(o)),'opção "copiar de" lista a Dani');
await a.selectOption(m+' [data-copy-from]',{label:'Dani Designer'});
ok(await a.locator(m+' [name=only_clients]').isChecked(),'copiar de outra pessoa copia também os limites');
await a.keyboard.press('Escape');
// carteira: dois clientes
q("lk_insert('clients',array('name'=>'Dani C','company'=>'Cliente da Dani','designer_id'=>"+uid+",'status'=>'ativo')); lk_insert('clients',array('name'=>'Ze C','company'=>'Cliente do Zé','status'=>'ativo'));");
const cid1=q("echo $wpdb->get_var('SELECT id FROM '.lk_table('clients').\" WHERE company='Cliente da Dani'\");"),cid2=q("echo $wpdb->get_var('SELECT id FROM '.lk_table('clients').\" WHERE company='Cliente do Zé'\");");
q("update_user_meta("+uid+",'lk_perms',array('conteudo','reunioes','tarefas','clientes'));");
const d=await login('dani@teste.com','Dani#12345');
await d.goto(B+'/painel/clientes/');
t=await d.locator('body').innerText();ok(/Cliente da Dani/.test(t)&&!/Cliente do Zé/.test(t),'designer com "só meus clientes" vê só a carteira dele');
// designer não tem a permissão clientes: libera p/ testar ficha
q("update_user_meta("+uid+",'lk_perms',array('conteudo','reunioes','tarefas','clientes'));");
await d.goto(B+'/painel/cliente/'+cid1+'/');ok(!/não tem acesso a esta área/.test(await d.locator('body').innerText()),'ficha do cliente da carteira abre');
await d.goto(B+'/painel/cliente/'+cid2+'/');ok(/não tem acesso a esta área/.test(await d.locator('body').innerText()),'ficha de cliente fora da carteira é negada');
// reuniões via interface
await d.goto(B+'/painel/reunioes/');
ok(await d.locator('[data-open="nova-reuniao-crm"]').count()>0,'designer (com Reuniões) vê o botão de marcar reunião');
await d.click('[data-open="nova-reuniao-crm"] >> nth=0');await d.waitForTimeout(300);
const f='#nova-reuniao-crm';
await d.fill(f+' [name=title]','Reunião teste interna');
const dt=new Date(Date.now()+864e5).toISOString().slice(0,10);await d.fill(f+' [name=date]',dt);
await Promise.all([d.waitForNavigation(),d.click(f+' button[type=submit]')]);
ok(q("echo $wpdb->get_var('SELECT COUNT(*) FROM '.lk_table('meetings').\" WHERE title='Reunião teste interna'\");")==='1','designer marca reunião interna');
// sem Reuniões → não vê nada
q("update_user_meta("+uid+",'lk_perms',array('conteudo','tarefas'));");
await d.goto(B+'/painel/reunioes/');ok(/não tem acesso a esta área/.test(await d.locator('body').innerText()),'sem a permissão, a tela de reuniões é negada');
await d.goto(B+'/painel/');ok(!/Próximas reuniões/.test(await d.locator('body').innerText()),'dashboard sem o cartão de reuniões');
// mobile equipe
const am=await login('admin','SENHA_DO_ADMIN_DE_TESTE',390);await am.goto(B+'/painel/equipe/');
ok(!(await am.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+2)),'tela Equipe sem rolagem lateral no celular');
await am.screenshot({path:'/tmp/art/equipe-mob.png',fullPage:false});
await a.goto(B+'/painel/equipe/');await a.click('button:has-text("Adicionar pessoa")');await a.selectOption('#membro-0 [data-func-preset]','comercial');await a.screenshot({path:'/tmp/art/equipe-modal.png'});
console.log('erros JS:',errs.join('|')||'nenhum','FALHAS',F);await b.close();})().catch(e=>{console.log('EXC',e.message.slice(0,400));process.exit(1)});
