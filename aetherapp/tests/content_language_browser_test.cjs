const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {chromium}=require('../tmp/feedback-browser/package'),root=path.resolve(__dirname,'..');
const original='<p>Je vindt <strong>een brief</strong>.</p>';
const translated='<p>You find <strong>a letter</strong>.</p>';
const payload={id:7,name:'Speuren',description:original,version:'Speuren',specialisations:[{id:72,name:'Aandelenmarkt'}]};
const translations=[{source:'Speuren',text:'Investigation',format:'plain'},{source:'Aandelenmarkt',text:'Stock market',format:'plain'},{source:original,text:translated,format:'html'}];
let requests=[];
const server=http.createServer((req,res)=>{
 const name=req.url.split('?')[0].slice(1);
 if(name==='fixture'){res.setHeader('Content-Type','text/html; charset=utf-8');const language=req.url.includes('lang=nl')?'nl':'en';return res.end('<!doctype html><html><head><meta charset="utf-8"><script>window.AetherI18n={language:'+JSON.stringify(language)+',initialize:()=>true};</script><script src="js/contentLanguage.js"></script><script src="js/mainFunctions.js"></script><script src="js/richTextCharacter.js"></script><script src="js/actionButtons.js"></script><script src="js/researchUi.js"></script></head><body><main></main></body></html>');}
 if(name==='checkLogin.php'){res.setHeader('Content-Type','application/json');return res.end(JSON.stringify({status:'ok',user:{id:1,role:'director',interfaceLanguage:'en'},csrfToken:'test'}));}
 if(name==='content'){requests.push(req.headers);res.setHeader('Content-Type','application/json');const nl=req.headers['x-aether-content-language']==='nl';return res.end(JSON.stringify({aetherPayload:nl?{name:'Investigation',description:translated}:payload,aetherContentTranslations:nl?[{source:'Investigation',text:'Speuren',format:'plain'},{source:translated,text:original,format:'html'}]:translations}));}
 const file=path.resolve(root,name);if(!file.startsWith(root+path.sep)){res.writeHead(403);return res.end();}
 try{res.setHeader('Content-Type','text/javascript; charset=utf-8');res.end(fs.readFileSync(file));}catch{res.writeHead(404);res.end();}
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const browser=await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
 try{
  const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:'+server.address().port+'/fixture');
  const read=await page.evaluate(async()=>{
   const source=await aetherRequestJson('content');window.source=source;
   const main=document.querySelector('main');
   main.innerHTML='<h2 id="name"></h2><p id="list"></p><div id="description"></div><select id="option"><option>Speuren</option></select><input id="input"><textarea id="textarea"></textarea>';
   document.querySelector('#name').textContent=source.name;
   document.querySelector('#list').textContent=source.name+', '+source.specialisations[0].name;
   renderCharacterRichText(document.querySelector('#description'),source.description);
   document.querySelector('#input').value=source.name;document.querySelector('#textarea').value=source.description;
   const editor=createCharacterRichTextEditor(source.description);editor.wrapper.id='editor';main.append(editor.wrapper);window.editor=editor;
   const research=AetherResearch.rich(source.description);research.id='research';main.append(research);
   return source;
  });
  assert.deepEqual(read,payload,'The API object must remain original');
  await page.waitForFunction(()=>document.querySelector('#name').textContent==='Investigation');
  assert.equal(await page.locator('#list').innerText(),'Investigation, Stock market');
  assert.equal(await page.locator('#description').innerHTML(),translated);
  assert.equal(await page.locator('#research').innerHTML(),translated);
  assert.equal(await page.locator('#option').inputValue(),'Speuren');
  assert.equal(await page.locator('#option option').innerText(),'Investigation');
  assert.equal(await page.locator('#input').inputValue(),'Speuren');
  assert.equal(await page.locator('#textarea').inputValue(),original);
  assert.equal(await page.evaluate(()=>editor.getHtml()),original);
  await page.evaluate(()=>{editor.editor.contentEditable='false';editor.editor.textContent='Speuren';});
  await page.waitForTimeout(50);assert.equal(await page.evaluate(()=>editor.getHtml()),'Speuren','Disabled rich editors must also remain source-only');
  await page.evaluate(()=>{const n=document.createElement('p');n.id='late';n.textContent='Aandelenmarkt';document.body.append(n);});
  await page.waitForFunction(()=>document.querySelector('#late').textContent==='Stock market');
  await page.evaluate(()=>{const n=document.createElement('h3');n.id='capitalized';n.textContent='Nieuwe vaardigheid - Expert';document.body.append(n);AetherContent.register([{source:'nieuwe vaardigheid',text:'new skill',format:'plain'}]);});
  await page.waitForFunction(()=>document.querySelector('#capitalized').textContent==='New skill - Expert');
  assert.equal(requests[0]['x-aether-content-language'],'en');
  await page.goto('http://127.0.0.1:'+server.address().port+'/fixture?lang=nl');
  const nlSource=await page.evaluate(async()=>{
   const source=await aetherRequestJson('content');const host=document.querySelector('main');
   host.innerHTML='<h2></h2><div></div>';host.querySelector('h2').textContent=source.name;renderCharacterRichText(host.querySelector('div'),source.description);return source;
  });
  await page.waitForFunction(()=>document.querySelector('h2').textContent==='Speuren');
  assert.equal(await page.locator('main div').innerHTML(),original);
  assert.equal(nlSource.name,'Investigation','Originally English data remains English in the source object');
  assert.equal(requests.at(-1)['x-aether-content-language'],'nl');
  assert.deepEqual(errors,[]);
  console.log('PASS: localized database content and rich text, late rendering, canonical dropdowns, original editor values and API payloads');
 }finally{await browser.close();server.close();}
})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
