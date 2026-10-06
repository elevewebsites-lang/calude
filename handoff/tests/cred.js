const {chromium}=require('/opt/node22/lib/node_modules/playwright');
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
const q=(c)=>{require('fs').writeFileSync('/tmp/qq.php','<?php require "/tmp/wpt2/wp-load.php"; global $wpdb; '+c);return require('child_process').execSync('php /tmp/qq.php').toString().trim();};
const tok=q("echo $wpdb->get_var('SELECT token FROM '.ap_table('quotes').' ORDER BY id DESC LIMIT 1');");
for(const scheme of ['light','dark']){const ctx=await b.newContext({viewport:{width:1300,height:800},colorScheme:scheme});const p=await ctx.newPage();
 const shots=[['login','/entrar/'],['prop','/orcamento/'+tok+'/']];
 for(const [n,u] of shots){await p.goto('http://localhost:8082'+u);await p.waitForTimeout(500);const el=p.locator('.eleve-credit').first();
  const info=await el.evaluate(e=>{const im=[...e.querySelectorAll('img')].filter(i=>getComputedStyle(i).display!=='none')[0];return {logo:im&&im.dataset.logo,color:getComputedStyle(e).color,bg:getComputedStyle(document.body).backgroundColor}});
  console.log(scheme,n,JSON.stringify(info));await el.scrollIntoViewIfNeeded();await p.screenshot({path:`/tmp/art/cred-${scheme}-${n}.png`});}
 // painel
 await p.goto('http://localhost:8082/entrar/');await p.fill('input[name=email]','admin');await p.fill('input[name=senha]','SENHA_DO_ADMIN_DE_TESTE');await Promise.all([p.waitForNavigation(),p.click('button[type=submit]')]);
 if(scheme==='dark')await p.evaluate(()=>document.documentElement.setAttribute('data-theme','dark'));
 const el=p.locator('.side .eleve-credit').first();console.log(scheme,'painel',JSON.stringify(await el.evaluate(e=>{const im=[...e.querySelectorAll('img')].filter(i=>getComputedStyle(i).display!=='none')[0];return {logo:im&&im.dataset.logo,color:getComputedStyle(e).color}})));
 await el.screenshot({path:`/tmp/art/cred-${scheme}-side.png`});
}
await b.close();})();
