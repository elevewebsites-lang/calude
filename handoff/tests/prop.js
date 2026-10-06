const {chromium}=require('/opt/node22/lib/node_modules/playwright');
const B='http://localhost:8082';let F=0;const ok=(c,m)=>{console.log((c?'PASS ':'FAIL ')+m);if(!c)F++;};
const q=(s)=>require('child_process').execSync("php -r \"require '/tmp/wpt2/wp-load.php'; global \\$wpdb; "+s+"\"").toString().trim();
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});const ctx=await b.newContext({viewport:{width:1300,height:900}});const pg=await ctx.newPage();const errs=[];pg.on('pageerror',e=>errs.push(e.message));pg.on('dialog',d=>d.accept());
await pg.goto(B+'/entrar/');await pg.fill('input[name=email]','admin');await pg.fill('input[name=senha]','SENHA_DO_ADMIN_DE_TESTE');await Promise.all([pg.waitForNavigation(),pg.click('button[type=submit]')]);
await pg.goto(B+'/painel/orcamento/');
ok(!/3D|filamento|impress[ãa]o 3/i.test(await pg.locator('main, .content, body').first().innerText()),'editor sem referência a 3D');
await pg.selectOption('[name=client_id]',{label:(await pg.locator('[name=client_id] option',{hasText:'Gráfica Teste'}).first().innerText())});
await pg.fill('[name=title]','Fachada com banner e adesivos');await pg.fill('[name=intro]','Olá! Segue a proposta do que conversamos.');
await pg.setInputFiles('[name="photos[]"]',['/tmp/art/a.jpg','/tmp/art/a.png']);
const row=pg.locator('[data-qitem]').first();
await row.locator('[data-q=material]').selectOption({index:1});await row.locator('[data-q=w]').fill('300');await row.locator('[data-q=h]').fill('100');await row.locator('[data-q=qty]').fill('2');
await pg.waitForTimeout(300);
console.log('preço calculado:',await row.locator('[data-q=unit]').inputValue(),'| nome:',await row.locator('.qitem-name').inputValue());
ok(+((await row.locator('[data-q=unit]').inputValue()).replace(',','.'))>0,'preço sai do catálogo pela tabela do cliente');
await pg.click('[data-qitem-add]');const r2=pg.locator('[data-qitem]').nth(1);
await r2.locator('.qitem-name').fill('Instalação no local');await r2.locator('[data-q=unit]').fill('150,00');
await pg.fill('[name=notes]','Instalação não inclusa fora de Taubaté.');
await pg.waitForTimeout(300);console.log('resumo:',(await pg.locator('.calc-card').innerText()).replace(/\n/g,' | ').slice(0,200));
await pg.check('[name=publicar]',{force:true});await Promise.all([pg.waitForNavigation(),pg.click('.calc-card button[type=submit]')]);
console.log('url',pg.url(),'|',(await pg.locator('.flash').first().innerText().catch(()=>'')));
const id=pg.url().match(/orcamento\/(\d+)/)[1];
const tok=q("echo ap_get('quotes',"+id+")->token;");
ok(!!tok,'proposta salva (#'+id+')');
// botão atualizar dados
ok(await pg.locator('button:has-text("Atualizar dados")').count()>0,'botão Atualizar dados no editor');
// preço do catálogo muda → atualizar
q("\\$m=ap_get('catalog',".concat(q("echo ap_get('quotes',"+id+")->items;").match(/"material":(\d+)/)[1],"); ap_update('catalog',\\$m->id,array('price_m2'=>\\$m->price_m2+10,'price_empresa'=>\\$m->price_empresa+10,'price_pf'=>\\$m->price_pf+10));"));
const before=q("echo ap_get('quotes',"+id+")->subtotal;");
await Promise.all([pg.waitForNavigation(),pg.click('button:has-text("Atualizar dados")')]);
console.log('flash:',(await pg.locator('.flash').first().innerText().catch(()=>'')));
const after=q("echo ap_get('quotes',"+id+")->subtotal;");
ok(+after>+before,'Atualizar dados reprecifica ('+before+' → '+after+')');
// página pública
const pub=await (await b.newContext({viewport:{width:1300,height:1000}})).newPage();pub.on('pageerror',e=>errs.push('pub '+e.message));
await pub.goto(B+'/orcamento/'+tok+'/');await pub.waitForTimeout(500);
const t=await pub.locator('body').innerText();
ok(/PROPOSTA COMERCIAL|Proposta comercial/i.test(t)&&/Gráfica Teste/.test(t),'página pública com nome do cliente');
ok(/Instalação no local/.test(t)&&/300 × 100 cm/.test(t),'itens com medidas');
ok(!/3D|modelo/i.test(t),'sem 3D na página pública');
ok(await pub.locator('[data-print]').count()===1,'botão Baixar PDF');
await pub.screenshot({path:'/tmp/art/pp-desk.png',fullPage:true});
await pub.emulateMedia({media:'print'});await pub.pdf({path:'/tmp/art/pp.pdf',format:'A4',printBackground:true});
const mob=await (await b.newContext({viewport:{width:390,height:844}})).newPage();await mob.goto(B+'/orcamento/'+tok+'/');await mob.waitForTimeout(400);
ok(!(await mob.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+2)),'mobile sem rolagem lateral');
await mob.screenshot({path:'/tmp/art/pp-mob.png',fullPage:true});
// ?pdf=1 chama print
let printed=false;const p3=await (await b.newContext()).newPage();await p3.addInitScript(()=>{window.print=()=>{window.__printed=true}});await p3.goto(B+'/orcamento/'+tok+'/?pdf=1');await p3.waitForTimeout(1200);printed=await p3.evaluate(()=>!!window.__printed);ok(printed,'?pdf=1 abre a impressão/salvar PDF');
console.log('erros JS:',errs.join('|')||'nenhum','FALHAS',F);await b.close();})().catch(e=>{console.log('EXC',e.message.slice(0,400));process.exit(1)});
