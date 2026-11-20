const fs=require('fs'),path=require('path'),http=require('http'),assert=require('assert/strict'),vm=require('vm');
const {chromium}=require(process.env.RESEARCH_PLAYWRIGHT||'../tmp/feedback-browser/package');
const root=path.resolve(__dirname,'..'),assets=process.env.AETHER_TEST_ASSETS||root;let eventSaves=[],otherSaves=[],googleCalls=0,corrected=false;
const hash='a'.repeat(64),version='b'.repeat(64),nextVersion='c'.repeat(64);
const event={id:1,title:'Cut!',description:'English description',dateStart:'2027-05-15',dateEnd:'2027-05-17',type:'weekend',ep:0,venue:''};
const translation=()=>({source:'Cut!',text:corrected?'Cut!':'Snee!',format:'plain',provider:corrected?'manual':'google-nmt',sourceHash:hash,sourceLanguage:'en',version:corrected?nextVersion:version});
const server=http.createServer(async(req,res)=>{
 const name=req.url.split('?')[0].slice(1);res.setHeader('Content-Type','application/json');
 if(name==='fixture'){res.setHeader('Content-Type','text/html');return res.end(`<!doctype html><html><head><meta charset="utf-8"><script>localStorage.setItem('aether:interface-bootstrap','nl');</script><script src="js/interface-copy.js"></script><script src="js/i18n.js"></script><script src="js/contentLanguage.js"></script><script src="js/actionButtons.js"></script><script src="js/richTextCharacter.js"></script><script src="js/eventDetails.js"></script><script src="js/mainFunctions.js"></script></head><body><div id="navbarName"></div><div id="eventFeedback" class="d-none"></div><div id="eventParticipationView"></div><div id="eventDetailsView"></div><div id="richFixture"></div></body></html>`);}
 if(name==='checkLogin.php')return res.end(JSON.stringify({status:'ok',user:{id:1,role:'director',displayName:'Test',interfaceLanguage:'nl'},csrfToken:'csrf'}));
 if(name==='api/events/eventDetails.php')return res.end(JSON.stringify({aetherPayload:{event,characters:[],canManage:true},aetherContentTranslations:[translation()]}));
 if(name==='api/events/updateEvent.php'){let body='';for await(const part of req)body+=part;eventSaves.push(JSON.parse(body));assert.equal(req.headers['x-csrf-token'],'csrf');corrected=true;return res.end(JSON.stringify({aetherPayload:1,aetherContentTranslations:[translation()]}));}
 if(name==='api/characters/saveCharacterSection.php'){let body='';for await(const part of req)body+=part;otherSaves.push(JSON.parse(body));return res.end('{"success":true}');}
 if(name==='api/translations/http.php'){googleCalls++;return res.end('{}');}
 let file=path.resolve(root,name);if(!file.startsWith(root+path.sep)){res.writeHead(403);return res.end();}if(!fs.existsSync(file))file=path.resolve(assets,name);
 try{res.setHeader('Content-Type','text/javascript; charset=utf-8');res.end(fs.readFileSync(file));}catch{res.writeHead(404);res.end();}
});
(async()=>{
 const catalog=JSON.parse(fs.readFileSync(path.join(root,'js/interface-copy.json'),'utf8'));
 const context={console,aetherT:source=>typeof catalog[source]==='string'?catalog[source]:(catalog[source]?.en||source),document:{getElementById:()=>null,addEventListener(){}}};context.window=context;vm.createContext(context);vm.runInContext(fs.readFileSync(path.join(root,'js/formCharacter.js'),'utf8'),context);context.getCharacterTraitIncomeTotal=c=>c.income;
 for(const income of [75,150,250,500,2000,5000]){assert.match(context.getMiddleClassLivingStandardText({income}),/^You /);assert.match(context.getResolvedMiddleClassLivingStandardText({middleClassLivingStandardIncome:income}),/^You /);}
 assert.match(context.getLowerClassLivingStandardText(),/^You /);for(let tier=1;tier<=5;tier++)assert.match(context.getUpperClassHouseholdStaffLivingStandardText(tier),/^You /);
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const browser=await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
 try{
  const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto('http://127.0.0.1:'+server.address().port+'/fixture');await page.waitForFunction(()=>window.AETHER_CURRENT_USER);
  await page.evaluate(async()=>{const data=await aetherRequestJson('api/events/eventDetails.php',{method:'POST',body:{idEvent:1}});window.inlineData=data;window.renderEventCharacters=()=>{};window.refreshEventSidebar=async()=>{};window.openEventDetails=async()=>renderEventDetails(window.inlineData,false);renderEventDetails(data,true);});
  await page.getByLabel('Titel',{exact:true}).fill('Cut!');
  const initial=await page.evaluate(()=>{const n=document.createElement('input');AetherContent.bindInput(n,'Cut!','title');return n.value;});assert.equal(initial,'Snee!','The editor opens in the selected Dutch language.');
  await page.getByRole('button',{name:'Opslaan',exact:true}).click();await page.waitForFunction(()=>!eventDetailsState.editing);
  assert.equal(eventSaves.length,1);assert.equal(eventSaves[0].title,'Cut!');assert.deepEqual(eventSaves[0]._aetherTranslationEdits,[{path:['title'],sourceHash:hash,version}]);
  assert.equal(await page.locator('#eventDetailsView h1').innerText(),'Cut!','Saving a translation equal to the original clears stale Snee!.');
  await page.evaluate(()=>{AetherContent.register([{source:'<p>English source</p>',text:'<p>Nederlands</p>',format:'html',provider:'google-nmt',sourceHash:'d'.repeat(64),sourceLanguage:'en',version:'e'.repeat(64)}]);const edit=createCharacterRichTextEditor('<p>English source</p>',{field:'content'});document.querySelector('#richFixture').append(edit.wrapper);window.richEdit=edit;});
  assert.equal(await page.locator('#richFixture [contenteditable]').innerText(),'Nederlands');await page.locator('#richFixture [contenteditable]').fill('Verbeterde tekst');
  await page.evaluate(()=>aetherRequestJson('api/characters/saveCharacterSection.php',{method:'POST',body:{idCharacter:7,section:'knowledge',content:richEdit.getHtml()}}));assert.equal(otherSaves.length,1);assert.deepEqual(otherSaves[0]._aetherTranslationEdits[0].path,['content']);
  const participant=await page.evaluate(()=>{AETHER_CURRENT_USER.role='participant';const n=document.createElement('input');AetherContent.bindInput(n,'Cut!','title');return {value:n.value,payload:AetherContent.prepareSave({title:'Other'})};});assert.equal(participant.value,'Cut!');assert.deepEqual(participant.payload,{title:'Other'});
  assert.equal(googleCalls,0,'Reading and correcting translations must never call Google');assert.deepEqual(errors,[]);
  console.log('PASS: all living-standard tiers in English; normal event editor correction; identity refresh; versioned request metadata; rich editor; existing role restrictions; zero Google calls.');
 }finally{await browser.close();server.close();}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
