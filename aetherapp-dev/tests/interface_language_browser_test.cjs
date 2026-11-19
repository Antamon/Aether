const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {chromium}=require('../tmp/feedback-browser/package'),root=path.resolve(__dirname,'..');
let profile={id:42,displayName:'Jan Mertens',firstName:'Jan',lastName:'Mertens',role:'director',interfaceLanguage:'en'};
const entryPages=['admin.html','index.html','companies.html','eventParticipation.html','leaderPersonnel.html','research.html','static.html'];
function fixture(name){return fs.readFileSync(path.join(root,name),'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'').replace(/<head>[\s\S]*?<\/head>/i,`<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="tmp/feedback-browser/bootstrap.min.css"><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/page-nav.css"><link rel="stylesheet" href="css/i18n.css"><script src="js/interface-copy.js"></script><script src="js/i18n.js"></script></head>`).replace('</body>','<script src="js/mainFunctions.js"></script></body>');}
const server=http.createServer((req,res)=>{
 const name=req.url.split('?')[0].slice(1);
 if(entryPages.includes(name)){res.setHeader('Content-Type','text/html; charset=utf-8');return res.end(fixture(name));}
 if(name==='checkLogin.php'){res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({status:'ok',user:profile,csrfToken:'test-token'}));}
 if(name==='test-error'){res.writeHead(422,{'Content-Type':'application/json'});return res.end(JSON.stringify({error:'Ongeldige invoer.',validationErrors:['Veld name is verplicht.','Veld companyValue: Het bedrag mag maximaal 2 decimalen bevatten.']}));}
 const file=path.resolve(root,name);if(!file.startsWith(root+path.sep)){res.writeHead(403);return res.end();}
 try{res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript; charset=utf-8':file.endsWith('.css')?'text/css':'application/octet-stream');res.end(fs.readFileSync(file));}catch{res.writeHead(404);res.end();}
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const browser=await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
 try{
  const context=await browser.newContext({viewport:{width:1500,height:1000}}),page=await context.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));const base='http://127.0.0.1:'+server.address().port+'/';
  await page.goto(base+'admin.html');await page.waitForFunction(()=>document.documentElement.lang==='en'&&document.querySelector('.aether-language-picker'));
  assert((await page.locator('#navbarName').innerText()).includes('Welcome Jan Mertens'));assert.equal(await page.locator('#adminNewSkillButton').innerText(),'+ New skill'.replace('+ ',''));
  assert.equal(await page.locator('label[for="adminSkillSelect"]').innerText(),'Skill');
  await page.addScriptTag({url:base+'js/adminFunctions.js'});
  await page.evaluate(()=>{
   adminCurrentUser={role:'director'};setupAdminPageListeners();
   window.fixtureTrait={id:1,name:'Onderzoek',description:'Bewaren',class:'upper class',type:'status',rankType:'singular',traitGroup:'Economie',cost:1,staffRequirements:0,version:'v1',holders:[]};
   window.apiFetchJson=async url=>url.includes('?id=')?structuredClone(fixtureTrait):{traits:[structuredClone(fixtureTrait)]};
  });
  await page.addScriptTag({url:base+'js/adminTraits.js'});await page.evaluate(()=>setActiveAdminTab('traits'));
  await page.locator('#adminTraitEditor h1').getByText('Onderzoek',{exact:true}).waitFor();
  assert.equal(await page.locator('#adminTraitEditor .admin-skill-view-text').innerText(),'Bewaren','User-authored descriptions stay unchanged even when equal to an interface key');
  assert.equal(await page.locator('#adminTraitSelect option[value="1"]').innerText(),'Onderzoek','Database names stay unchanged');
  assert.equal(await page.locator('#adminTraitClassTabs .active').innerText(),'Upper class');
  await page.locator('#adminTraitEditor').getByRole('button',{name:'Edit',exact:true}).click();
  assert.equal(await page.locator('#adminTraitEditor').getByLabel('Class',{exact:true}).inputValue(),'upper class','Translated options retain canonical API values');
  assert.equal(await page.locator('#adminTraitEditor').getByLabel('Category',{exact:true}).inputValue(),'status');
  await page.locator('#adminTraitEditor').getByRole('button',{name:'Cancel',exact:true}).click();
  const error=await page.evaluate(async()=>{try{await aetherRequestJson('test-error');}catch(error){return {message:error.message,raw:error.responseBody};}});
  assert(error.message.includes('Invalid input.'));assert(error.message.includes('Field name is required.'));assert(error.message.includes('at most 2 decimal places'));assert(error.raw.includes('Ongeldige invoer.'),'Original response preserved for diagnostics');
  // Templates cloned after initial rendering are translated by the shared observer.
  await page.goto(base+'index.html');await page.waitForSelector('.aether-language-picker');
  await page.evaluate(()=>{const nav=document.getElementById('pageNav');nav.append(document.getElementById('tplNavSheetAdmin').content.cloneNode(true));nav.classList.remove('d-none');});
  await page.locator('#pageNav').getByText('Actions',{exact:true}).waitFor();
  assert.equal(await page.locator('#pageNav select option[value="player"]').first().innerText(),'Player character');
  for(const name of entryPages.slice(2)){await page.goto(base+name);await page.waitForSelector('.aether-language-picker');assert.equal(await page.locator('html').getAttribute('lang'),'en',name);}
  await page.goto(base+'research.html');await page.waitForSelector('.aether-language-picker');
  for(const script of ['js/actionButtons.js','js/richTextCharacter.js','js/researchUi.js'])await page.addScriptTag({url:base+script});
  await page.evaluate(()=>{AetherResearch.read=async op=>op==='catalog'?{events:[{id:1,title:'Holiday'}],skills:[],specialisations:[],characters:[],timeZones:['Europe/Brussels']}:{challenges:[]};});
  await page.addScriptTag({url:base+'js/researchAdmin.js'});
  await page.getByRole('button',{name:'New challenge',exact:true}).click();await page.locator('dialog').getByRole('heading',{name:'New challenge',exact:true}).waitFor();
  assert(await page.locator('dialog').getByLabel('Title',{exact:true}).isVisible());assert(await page.locator('dialog').getByRole('button',{name:'Save',exact:true}).isVisible());
  await page.goto(base+'admin.html');await page.waitForSelector('.aether-language-picker');
  await page.locator('.aether-language-picker summary').click();await Promise.all([page.waitForEvent('load'),page.getByRole('button',{name:'Nederlands',exact:true}).click()]);
  await page.waitForFunction(()=>document.documentElement.lang==='nl'&&document.querySelector('.aether-language-picker'));
  assert((await page.locator('#navbarName').innerText()).includes('Welkom Jan Mertens'));
  assert.equal(await page.locator('label[for="adminSkillSelect"]').innerText(),'Vaardigheid');
  await page.reload();await page.waitForSelector('.aether-language-picker');assert.equal(await page.locator('html').getAttribute('lang'),'nl','User override survives reload');
  profile={...profile,id:43,displayName:'Other user',interfaceLanguage:'en'};await page.reload();await page.waitForFunction(()=>document.documentElement.lang==='en'&&document.querySelector('#navbarName')?.textContent.includes('Other user'));
  assert.equal(await page.evaluate(()=>localStorage.getItem('aether:interface-language:42')),'nl','Preferences are isolated per user');
  await page.setViewportSize({width:390,height:844});await page.evaluate(()=>document.querySelector('.navbar-collapse').classList.add('show'));assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Language menu does not overflow on mobile');
  await page.locator('.aether-language-picker summary').focus();await page.keyboard.press('Enter');assert(await page.getByRole('button',{name:'English',exact:true}).isVisible());await page.keyboard.press('Escape');assert(await page.getByRole('button',{name:'English',exact:true}).isHidden());
  assert.deepEqual(errors,[]);
  console.log('PASS: WordPress initial language, user-scoped override, seven entry pages, late templates, dynamic editor, unchanged content/API values, translated validation, keyboard and mobile language picker.');
 }finally{await browser.close();server.close();}
})().catch(error=>{console.error(error);process.exitCode=1;server.close();});
