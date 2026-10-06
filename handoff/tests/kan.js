const {chromium}=require('/opt/node22/lib/node_modules/playwright');
const B='http://localhost:8082';let F=0;const ok=(c,m)=>{console.log((c?'PASS ':'FAIL ')+m);if(!c)F++;};
const q=(s)=>require('child_process').execSync("php -r \"require '/tmp/wpt2/wp-load.php'; global \\$wpdb; "+s+"\"").toString().trim();
(async()=>{
// prepara pedido pronto com saldo na retirada
const pid=q("\\$c=\\$wpdb->get_var('SELECT id FROM '.ap_table('clients').\\\" WHERE email='maria@teste.com'\\\"); \\$cols=array_keys(ap_columns()); \\$pid=ap_insert('projects',array('client_id'=>\\$c,'title'=>'KANBAN TESTE','status'=>\\$cols[count(\\$cols)-2],'value'=>120,'urgent'=>0)); ap_insert('transactions',array('type'=>'in','project_id'=>\\$pid,'client_id'=>\\$c,'category'=>'Pedido','description'=>'k','amount'=>120,'status'=>'pendente','method'=>'Na retirada','due_date'=>ap_today())); echo \\$pid;");
console.log('pedido',pid);
const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});const pg=await (await b.newContext({viewport:{width:2700,height:900}})).newPage();const errs=[];pg.on('pageerror',e=>errs.push(e.message));
await pg.goto(B+'/entrar/');await pg.fill('input[name=email]','admin');await pg.fill('input[name=senha]','SENHA_DO_ADMIN_DE_TESTE');await Promise.all([pg.waitForNavigation(),pg.click('button[type=submit]')]);
await pg.goto(B+'/painel/projetos/');await pg.waitForTimeout(500);
const card=pg.locator('.kcard[data-id="'+pid+'"]');ok(await card.count()===1,'card no kanban');
ok(/retirada/i.test(await card.innerText()),'card mostra tag Paga na retirada: '+(await card.innerText()).replace(/\n/g,' | ').slice(0,120));
await card.dragTo(pg.locator('.kcol[data-last] .kcol-body'));await pg.waitForTimeout(600);
ok(await pg.locator('.paymodal').isVisible(),'popup de pagamento ao mover para Entregue');
await pg.click('.paymodal [data-ok]');ok(await pg.locator('.paymodal-err').isVisible(),'exige a forma de pagamento');
await pg.click('.paymodal [data-cancel]');await pg.waitForTimeout(800);
ok(q("echo ap_get('projects',"+pid+")->status!==ap_last_column()?'1':'0';")==='1','cancelar mantém o pedido na etapa');
await pg.locator('.kcard[data-id="'+pid+'"]').dragTo(pg.locator('.kcol[data-last] .kcol-body'));await pg.waitForTimeout(500);
await pg.check('.paymodal input[value="Pix"]',{force:true});
await Promise.all([pg.waitForNavigation().catch(()=>{}),pg.click('.paymodal [data-ok]')]);await pg.waitForTimeout(1500);
ok(q("echo ap_get('projects',"+pid+")->status===ap_last_column()?'1':'0';")==='1','confirmando Pix: pedido entregue');
ok(q("echo \\$wpdb->get_var('SELECT COUNT(*) FROM '.ap_table('transactions').\\\" WHERE project_id="+pid+" AND status='pago' AND method='Pix'\\\");")==='1','lançamento marcado como pago no Pix');
console.log('erros JS:',errs.join('|')||'nenhum','FALHAS',F);await b.close();})();
