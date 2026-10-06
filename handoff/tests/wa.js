const {chromium}=require('/opt/node22/lib/node_modules/playwright');
const fs=require('fs');const B='http://localhost:8082';let F=0;const ok=(c,m)=>{console.log((c?'PASS ':'FAIL ')+m);if(!c)F++;};
const q=(c)=>{fs.writeFileSync('/tmp/qq.php','<?php require "/tmp/wpt2/wp-load.php"; global $wpdb; '+c);return require('child_process').execSync('php /tmp/qq.php').toString().trim();};
(async()=>{
q("$wpdb->query('DELETE FROM '.ap_table('leads')); $wpdb->query('DELETE FROM '.ap_table('quotes'));");
const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});const ctx=await b.newContext({viewport:{width:1300,height:1000}});const pg=await ctx.newPage();const errs=[];pg.on('pageerror',e=>errs.push(e.message));pg.on('dialog',d=>d.accept());
let waUrl='';pg.on('request',r=>{if(/wa\.me/.test(r.url()))waUrl=r.url();});
await pg.goto(B+'/entrar/');await pg.fill('input[name=email]','admin');await pg.fill('input[name=senha]','SENHA_DO_ADMIN_DE_TESTE');await Promise.all([pg.waitForNavigation(),pg.click('button[type=submit]')]);
await pg.goto(B+'/painel/orcamento/');
await pg.selectOption('[name=client_id]',{label:await pg.locator('[name=client_id] option',{hasText:'Gráfica Teste'}).first().innerText()});
await pg.waitForTimeout(300);
ok(/Olá, Maria!/.test(await pg.inputValue('[name=intro]')),'mensagem preenchida sozinha com o nome: '+(await pg.inputValue('[name=intro]')).slice(0,60));
ok(/Proposta para Gráfica Teste/.test(await pg.inputValue('[name=title]')),'título preenchido sozinho');
ok(/Emitimos recibo|prazo/i.test(await pg.inputValue('[name=notes]')),'condições preenchidas sozinhas');
await pg.click('[data-qitem-quick]');await pg.waitForTimeout(200);
await pg.locator('[data-qitem]').first().locator('[data-q=w]').fill('200');await pg.locator('[data-qitem]').first().locator('[data-q=h]').fill('100');await pg.waitForTimeout(200);
ok(await pg.locator('[data-qitem]').first().locator('.qitem-name').inputValue()!=='','atalho preencheu o item: '+await pg.locator('[data-qitem]').first().locator('.qitem-name').inputValue());
await Promise.all([pg.waitForNavigation().catch(()=>{}),pg.click('[data-send-wa]')]);await pg.waitForTimeout(1500);
console.log('WA url:',decodeURIComponent(waUrl).slice(0,200));
ok(/wa\.me\/5511999990000/.test(waUrl),'abre o WhatsApp do cliente');
ok(/proposta comercial n/i.test(decodeURIComponent(waUrl))&&/orcamento\//.test(waUrl),'mensagem com nº e link da proposta');
const lead=JSON.parse(q("echo json_encode($wpdb->get_row('SELECT * FROM '.ap_table('leads').' ORDER BY id DESC LIMIT 1'));"));
console.log('lead:',lead.name,lead.stage,lead.value,lead.client_id,lead.next_action);
ok(lead.stage==='proposta-enviada','lead criado em "Proposta enviada"');
ok(+lead.value>0,'lead com o valor da proposta');
ok(q("echo $wpdb->get_var('SELECT status FROM '.ap_table('quotes').' ORDER BY id DESC LIMIT 1');")==='enviado','proposta marcada como enviada');
ok(q("echo $wpdb->get_var('SELECT lead_id FROM '.ap_table('quotes').' ORDER BY id DESC LIMIT 1');")===String(lead.id),'proposta ligada ao lead');
ok(/Perguntar/.test(lead.next_action||''),'lembrete de acompanhamento criado');
// segunda proposta p/ mesmo cliente: não duplica lead
await pg.goto(B+'/painel/orcamento/');await pg.selectOption('[name=client_id]',{label:await pg.locator('[name=client_id] option',{hasText:'Gráfica Teste'}).first().innerText()});
await pg.click('[data-qitem-quick]');await pg.locator('[data-qitem]').first().locator('[data-q=w]').fill('100');await pg.locator('[data-qitem]').first().locator('[data-q=h]').fill('100');
await Promise.all([pg.waitForNavigation().catch(()=>{}),pg.click('[data-send-wa]')]);await pg.waitForTimeout(1000);
ok(q("echo $wpdb->get_var('SELECT COUNT(*) FROM '.ap_table('leads'));")==='1','segunda proposta não duplica o lead');
// botão Enviar no WhatsApp na proposta salva
const qid=q("echo $wpdb->get_var('SELECT MAX(id) FROM '.ap_table('quotes'));");
await pg.goto(B+'/painel/orcamento/'+qid+'/');ok(await pg.locator('button:has-text("Enviar no WhatsApp")').count()>0,'botão Enviar no WhatsApp na proposta salva');
// funil mostra o lead
await pg.goto(B+'/painel/leads/');ok(/Proposta enviada/.test(await pg.locator('body').innerText()),'Funil tem a coluna Proposta enviada');
console.log('erros JS:',errs.join('|')||'nenhum','FALHAS',F);await b.close();})().catch(e=>{console.log('EXC',e.message.slice(0,300));process.exit(1)});
