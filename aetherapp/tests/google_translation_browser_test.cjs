const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {chromium}=require(process.env.RESEARCH_PLAYWRIGHT||'../tmp/feedback-browser/package'),root=path.resolve(__dirname,'..');
let role='director',automatic=[],saves=[];
const short={hash:'short',source:'Nieuwe bedrijfstekst',format:'plain',grant:'proof-short',manualRequired:false};
const rich={hash:'rich',source:'<p>Nieuwe <strong>feedback</strong></p>',format:'html',grant:'proof-rich',manualRequired:false};
const long={hash:'long',source:'Een lange achtergrond. '.repeat(300),format:'html',grant:'proof-long',manualRequired:true};
const usage={month:'2026-11',limit:500000,used:500000,successful:499000,exhausted:true,maxAutomaticCharacters:2000,configured:true,available:true};
const server=http.createServer(async(req,res)=>{
 const name=req.url.split('?')[0].slice(1);
 if(name==='fixture'){
  res.setHeader('Content-Type','text/html; charset=utf-8');return res.end(`<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="tmp/feedback-browser/bootstrap.min.css"><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/i18n.css"><script>localStorage.setItem('aether:interface-bootstrap','en');</script><script src="js/interface-copy.js"></script><script src="js/i18n.js"></script><script src="js/contentLanguage.js"></script><script src="js/translationUi.js"></script></head><body><div id="navbarName"></div><main><section class="aether-panel"><h2 id="plain"></h2><div id="rich"></div></section></main><script src="js/actionButtons.js"></script><script src="js/richTextCharacter.js"></script><script src="js/modal.js"></script><script src="js/mainFunctions.js"></script></body></html>`);
 }
 if(name==='checkLogin.php'){res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({status:'ok',user:{id:1,displayName:'Test User',role,interfaceLanguage:'en'},csrfToken:'csrf'}));}
 if(name==='fixture-content'){res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({aetherPayload:{description:short.source,content:rich.source},aetherContentTranslations:[],aetherMissingContent:[short,rich],...(role==='participant'?{}:{aetherEditableContent:[short,rich,long]})}));}
 if(name==='fixture-save'){res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({aetherPayload:{saved:true},aetherContentTranslations:[],aetherMissingContent:[short,rich].map(e=>({...e,sourceLanguage:'nl',targetLanguage:'en',automaticGrant:'saved-'+e.hash}))}));}
 if(name==='api/translations/http.php'){
  res.setHeader('Content-Type','application/json');if(req.method==='GET')return res.end(JSON.stringify(usage));
  let body='';for await(const part of req)body+=part;body=JSON.parse(body);
  assert.equal(req.headers['x-csrf-token'],'csrf','All translation mutations use CSRF');
  if(body.op==='automatic'){assert(body.automaticGrant);assert.equal(body.target,'en');assert.equal(body.sourceLanguage,'nl');automatic.push(body);return res.end(JSON.stringify({state:'ready',provider:'google-nmt',text:body.source===short.source?'New company text':'<p>New <strong>feedback</strong></p>',format:body.format,...(role==='participant'?{}:{usage})}));}
  if(body.op==='read')return res.end(JSON.stringify({text:'<p>Previous translation</p>',sourceLanguage:'nl',version:'v1'}));
  if(body.op==='save'){saves.push(body);return res.end(JSON.stringify({text:body.text,format:body.format,version:'v2'}));}
 }
 let file=path.resolve(root,name);if(!file.startsWith(root+path.sep)){res.writeHead(403);return res.end();}
 if(!fs.existsSync(file)&&process.env.AETHER_TEST_ASSETS)file=path.resolve(process.env.AETHER_TEST_ASSETS,name);
 try{res.setHeader('Content-Type',file.endsWith('.css')?'text/css':file.endsWith('.js')?'text/javascript; charset=utf-8':'application/octet-stream');res.end(fs.readFileSync(file));}catch{res.writeHead(404);res.end();}
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const base='http://127.0.0.1:'+server.address().port+'/';
 const browser=await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  async function showContent(){await page.evaluate(async()=>{const data=await aetherRequestJson('fixture-content');document.querySelector('#plain').textContent=data.description;renderCharacterRichText(document.querySelector('#rich'),data.content);});}
  await page.goto(base+'fixture');await page.locator('.aether-translation-button').waitFor();
  await showContent();await showContent();await showContent();await page.waitForTimeout(100);
  assert.equal(automatic.length,0,'Page reads and old read-only queue metadata never call Google');
  await page.evaluate(()=>aetherRequestJson('fixture-save',{method:'POST',body:{content:'Saved text'}}));
  await page.addScriptTag({url:base+'js/companyFunctions.js'});
  assert((await page.evaluate(()=>COMPANY_PROFITABILITY_RANGES.find(r=>r.min<=3&&r.max>=3).message)).startsWith('A thriving business.'),'The profitability +3 explanation must be English');
  await page.waitForFunction(()=>document.querySelector('#plain').textContent==='New company text'&&document.querySelector('#rich').textContent==='New feedback');
  assert.equal(automatic.length,2);assert(!automatic.some(b=>b.source===long.source),'Long texts must never enter the automatic queue');
  await showContent();await page.waitForTimeout(100);assert.equal(automatic.length,2,'Reopening content cannot add paid work');
  assert.equal(await page.locator('.aether-translation-warning').innerText(),'Translation allowance exhausted');
  await page.getByRole('button',{name:'Manage translations',exact:true}).click();
  await page.getByLabel('Long texts only').check();
  await page.locator('dialog [contenteditable]').waitFor();
  assert((await page.locator('.aether-translation-source').innerText()).startsWith('Een lange achtergrond.'));
  await page.locator('dialog [contenteditable]').fill('Manual English background');
  await page.getByRole('button',{name:'Save',exact:true}).click();
  await page.getByText('Translation saved.',{exact:true}).waitFor();
  assert.equal(saves.length,1);assert.equal(saves[0].source,long.source);assert.equal(saves[0].text,'<p>Manual English background</p>');
  assert.equal(await page.locator('dialog .aether-modal-close').count(),1,'Only the frame close control is present');
  await page.locator('dialog .aether-modal-close').click();
  role='participant';const participant=await browser.newPage();participant.on('pageerror',e=>errors.push(e.message));await participant.goto(base+'fixture');await participant.evaluate(()=>aetherGetCurrentUser());
  assert.equal(await participant.locator('.aether-translation-button,.aether-translation-warning').count(),0,'Participants never receive manager controls or usage warnings');
  await page.setViewportSize({width:390,height:844});await page.getByRole('button',{name:'Manage translations',exact:true}).click();
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'The translation modal fits a phone');
  await page.locator('dialog .aether-modal-close').click();
  await page.evaluate(()=>{window.AetherI18n={...window.AetherI18n,language:'nl'};document.querySelector('#plain').textContent='Nog een nieuwe tekst';window.AetherTranslation.consume({aetherMissingContent:[{hash:'opposite',source:'Nog een nieuwe tekst',format:'plain',grant:'read-proof',automaticGrant:'saved-proof',sourceLanguage:'nl',targetLanguage:'en'}]});});
  await page.waitForFunction(()=>document.querySelector('#plain').textContent==='Nog een nieuwe tekst');await page.waitForTimeout(150);
  assert.equal(automatic.length,3);assert.equal(await page.locator('#plain').innerText(),'Nog een nieuwe tekst','Preparing English must not replace Dutch source text');
  assert.deepEqual(errors,[]);console.log('PASS: zero-cost page reads, saved-text-only plain/rich translation, no automatic long texts, manager quota warning, manual modal/save, frame close control, participant UI and mobile width');
 }finally{await browser.close();server.close();}
})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
