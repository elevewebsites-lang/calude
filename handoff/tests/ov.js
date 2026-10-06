const {chromium}=require('/opt/node22/lib/node_modules/playwright');
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
for(const [lg,pw,urls] of [['admin','SENHA_DO_ADMIN_DE_TESTE',['/painel/orcamentos/','/painel/novo-pedido/']],['maria@teste.com','senha-forte-1',['/cliente/novo/']]]){
const p=await (await b.newContext({viewport:{width:390,height:800}})).newPage();
await p.goto('http://localhost:8082/entrar/');await p.fill('input[name=email]',lg);await p.fill('input[name=senha]',pw);await Promise.all([p.waitForNavigation(),p.click('button[type=submit]')]);
for(const u of urls){await p.goto('http://localhost:8082'+u);await p.waitForTimeout(300);
const r=await p.evaluate(()=>{const W=window.innerWidth;return [...document.querySelectorAll('body *')].filter(e=>{const r=e.getBoundingClientRect();return r.right>W+2&&r.width>0&&getComputedStyle(e).position!=='fixed'}).slice(0,6).map(e=>e.tagName+'.'+e.className+' '+Math.round(e.getBoundingClientRect().right)+' '+(e.getAttribute('href')||'')+' P:'+(e.parentElement.className))});console.log(u,JSON.stringify(r));}}
await b.close();})();
