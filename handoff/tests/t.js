const {chromium}=require('/opt/node22/lib/node_modules/playwright');
(async()=>{const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});const p=await b.newPage();
const errs=[];p.on('pageerror',e=>errs.push(e.message));p.on('console',m=>m.type()==='error'&&errs.push(m.text()));
await p.goto('http://localhost:8082/art.html');
for(const n of ['a.jpg','a.png','a.pdf','x8.cdr','old.cdr','bad.cdr']){
 const r=await p.evaluate(async n=>{const bl=await (await fetch('/'+n)).blob();const f=new File([bl],n);const m=await apArtPreview(f);return {kind:m.kind,thumb:m.thumb?m.thumb.length:0,pw:m.pw,ph:m.ph,px:m.px,note:m.note,chk:apArtCheck(m,100,50),chk2:apArtCheck(m,300,100)}},n);
 console.log(n,JSON.stringify(r));}
console.log('errors',errs);await b.close();})();
