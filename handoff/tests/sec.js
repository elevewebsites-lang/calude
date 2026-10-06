const {chromium}=require('/opt/node22/lib/node_modules/playwright');
const B='http://localhost:8082';let F=0;const ok=(c,m)=>{console.log((c?'PASS ':'FAIL ')+m);if(!c)F++;};
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
const anon=await (await b.newContext()).newPage();
for(const u of ['/painel/','/painel/clientes/','/painel/importar/','/painel/cupons/','/cliente/novo/','/painel/financeiro/']){const r=await anon.goto(B+u);ok(/entrar/.test(anon.url())||r.status()>=300,'anônimo barrado em '+u+' → '+anon.url().replace(B,''));}
const rr=await anon.request.post(B+'/wp-json/ap/v1/coupon',{data:{subtotal:100,code:'BEMVINDO10'}});ok(rr.status()>=400,'REST coupon sem login: '+rr.status());
for(const e of ['upload/start','upload/small','upload/done']){const r=await anon.request.post(B+'/wp-json/ap/v1/'+e,{data:{name:'a.pdf',size:10}});ok(r.status()>=400,'REST '+e+' sem login: '+r.status());}
// cliente
const c=await (await b.newContext()).newPage();
await c.goto(B+'/entrar/');await c.fill('input[name=email]','maria@teste.com');await c.fill('input[name=senha]','senha-forte-1');await Promise.all([c.waitForNavigation(),c.click('button[type=submit]')]);
for(const u of ['/painel/','/painel/clientes/','/painel/importar/','/painel/cupons/','/painel/financeiro/','/painel/novo-pedido/','/painel/pedido/1007/']){await c.goto(B+u);ok(!/\/painel\//.test(c.url())||/Sem permiss|negado|não tem/i.test(await c.locator('body').innerText()),'cliente barrado em '+u+' → '+c.url().replace(B,''));}
// pedido de outro cliente
await c.goto(B+'/cliente/projeto/1/');const t=await c.locator('body').innerText();ok(!/Pedido #1\b/.test(t)||/n[ãa]o encontrad|sem acesso|não é seu/i.test(t)||c.url().indexOf('/projeto/1/')<0,'cliente não vê pedido #1 de outro: '+c.url().replace(B,''));
// admin-post como cliente
const nonce=await c.evaluate(()=>window.AP&&window.AP.nonce);
const post=async(d)=>{const r=await c.request.post(B+'/wp-admin/admin-post.php',{form:d,maxRedirects:0});return r.status()+' '+(r.headers()['location']||'').replace(B,'').slice(0,60)};
for(const d of ['coupon_save','credit_add','coupon_delete','ap_import','client_kind','order_pay','manual_order']){const r=await post({action:'ap_do',do:d,id:1,client_id:1,amount:999,code:'HACK',value:99});console.log('  admin-post '+d+' como cliente → '+r);}
const cr=await c.request.post(B+'/wp-json/ap/v1/coupon',{headers:{'X-WP-Nonce':nonce},data:{subtotal:100,client_id:1,code:'',use_credit:true}});const j=await cr.json();ok(Math.abs(j.credit-18.5)<0.01,'REST coupon ignora client_id vindo do cliente (crédito '+j.credit+')');
const mv=await c.request.post(B+'/wp-json/ap/v1/project/1/move',{headers:{'X-WP-Nonce':nonce},data:{status:'entregue'}});ok(mv.status()>=400,'cliente não move pedido: '+mv.status());
console.log('FALHAS',F);await b.close();})();
