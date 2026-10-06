const {chromium}=require('/opt/node22/lib/node_modules/playwright');
const B='http://localhost:8082';let bad=0;
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
async function run(label,login,paths,w){const c=await b.newContext({viewport:{width:w||1300,height:900}});const p=await c.newPage();const errs=[];
 p.on('pageerror',e=>errs.push('JS '+e.message));p.on('dialog',d=>d.dismiss());
 await p.goto(B+'/entrar/');await p.fill('input[name=email]',login[0]);await p.fill('input[name=senha]',login[1]);await Promise.all([p.waitForNavigation(),p.click('button[type=submit]')]);
 for(const u of paths){errs.length=0;const r=await p.goto(B+u,{waitUntil:'load'});await p.waitForTimeout(250);const t=await p.locator('body').innerText();
  const fatal=/Fatal error|Warning:|Notice:|Deprecated:|critical error|erro crítico|Parse error/i.test(t);
  const hs=await p.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+2);
  const st=r.status();const ok=st===200&&!fatal&&!errs.length&&!(w&&w<600&&hs);if(!ok)bad++;
  console.log((ok?'ok  ':'BAD ')+label+' '+u+' '+st+(fatal?' FATAL':'')+(errs.length?' '+errs.join(';'):'')+(hs?' overflow-x':''));}
 await c.close();}
const adm=['/painel/','/painel/clientes/','/painel/leads/','/painel/orcamentos/','/painel/catalogo/','/painel/mensagens/','/painel/emails/','/painel/pedidos/','/painel/projetos/','/painel/insumos/','/painel/insumos/?cat=Bobina','/painel/insumos/?cat=Bobina&contagem=1','/painel/compras/','/painel/tarefas/','/painel/financeiro/','/painel/veiculos/','/painel/veiculos/?tipo=IPVA','/painel/equipe/','/painel/config/','/painel/saude/','/painel/apontamentos/','/painel/importar/','/painel/novo-pedido/','/painel/cupons/','/painel/cliente/'+process.env.CID+'/','/painel/pedido/1007/','/painel/projeto/1007/'];
await run('admin',['admin','SENHA_DO_ADMIN_DE_TESTE'],adm);
await run('admin-mobile',['admin','SENHA_DO_ADMIN_DE_TESTE'],adm,390);
const cli=['/cliente/','/cliente/novo/','/cliente/tabela/','/cliente/mensagens/','/cliente/perfil/','/cliente/projeto/1007/'];
await run('cliente',['maria@teste.com','senha-forte-1'],cli);
await run('cliente-mobile',['maria@teste.com','senha-forte-1'],cli,390);
console.log('BAD total',bad);await b.close();})();
