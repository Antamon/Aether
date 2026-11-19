const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {chromium}=require('../tmp/feedback-browser/package'),root=path.resolve(__dirname,'..');
const initial={idSkill:1,name:'Ambachten',description:'Beschrijving',beginner:'Beginner',professional:'Gevorderd',master:'Meester',categoryIds:[2],specialisations:[{idSkillSpecialisation:3,name:'Timmeren',kind:'discipline'},{idSkillSpecialisation:4,name:'Fijnwerk',kind:'specialisation'}],holders:[{level:2,label:'Gevorderde',characters:[{idCharacter:8,displayName:'Ada Test',portraitUrl:'portrait.svg',specialisations:['Fijnwerk']}]}]};
let html=fs.readFileSync(path.join(root,'admin.html'),'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
html=html.replace(/<head>[\s\S]*?<\/head>/i,'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="tmp/feedback-browser/bootstrap.min.css"><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/page-nav.css"></head>');
html=html.replace('</body>',`<script src="js/adminFunctions.js"></script><script>
window.writes=[];window.initial=${JSON.stringify(initial)};window.failSave=false;
window.apiFetchIdempotentJson=async(url,options)=>{writes.push({url,body:options.body});if(failSave)throw Error('Save failed');const skill={...initial,...options.body,specialisations:options.body.specialisations.map((s,i)=>({...s,idSkillSpecialisation:s.idSkillSpecialisation||100+i}))};return {skill,skills:[{idSkill:skill.idSkill,name:skill.name}]};};
window.apiFetchJson=async(url,options)=>{writes.push({url,body:options.body});if(url.includes('newSkill'))return {skill:{idSkill:9,name:options.body.name,specialisations:[]},skills:[{idSkill:9,name:options.body.name}]};return initial;};
adminCurrentUser={role:'director'};adminSkillTypeOptions=[{idSkillType:2,name:'Discipline',code:'discipline'}];adminSkillList=[{idSkill:1,name:'Ambachten'}];setupAdminPageListeners();populateAdminSkillForm(initial);
</script></body>`);
const server=http.createServer((req,res)=>{
    if(req.url==='/'){res.setHeader('Content-Type','text/html; charset=utf-8');return res.end(html);}
    if(req.url==='/portrait.svg'){res.setHeader('Content-Type','image/svg+xml');return res.end('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="80"><rect width="64" height="80" fill="brown"/></svg>');}
    const file=path.resolve(root,'.'+req.url.split('?')[0]);if(!file.startsWith(root+path.sep)){res.writeHead(403);return res.end();}
    try{let content=fs.readFileSync(file);if(file.endsWith('adminFunctions.js'))content=content.toString().replace(/window.addEventListener\("load",\s*\(\)\s*=>\s*\{\s*initAdminPage\(\);\s*\}\);/,'');res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript; charset=utf-8':file.endsWith('.css')?'text/css':'application/octet-stream');res.end(content);}catch{res.writeHead(404);res.end();}
});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const browser=await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});try{
    const page=await browser.newPage({viewport:{width:1500,height:1100}}),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto('http://127.0.0.1:'+server.address().port);
    await page.locator('#adminSkillView').getByText('Beschrijving',{exact:true}).first().waitFor();assert(await page.locator('#adminSkillForm').isHidden());
    const alignment=await page.evaluate(()=>{const main=document.querySelector('main.admin-page'),bounds=main.getBoundingClientRect(),padding=parseFloat(getComputedStyle(main).paddingLeft),reference=document.createElement('div');reference.className='container';document.body.append(reference);const width=reference.getBoundingClientRect().width;reference.remove();const cards=[...document.querySelectorAll('#adminSkillsSection > div > .card')].map(n=>n.getBoundingClientRect());return {width:bounds.width,standard:width,left:cards[0].left-bounds.left,right:bounds.right-cards[1].right,padding};});
    assert(Math.abs(alignment.width-alignment.standard)<1,'Standard page text width is retained');assert(Math.abs(alignment.left-alignment.padding)<1&&Math.abs(alignment.right-alignment.padding)<1,'No additional side inset around the columns');
    assert.equal(await page.locator('#pageNav #adminSkillSelect').count(),1);assert.equal(await page.locator('#pageNav #adminNewSkillButton').count(),1);assert.equal(await page.locator('#adminSkillsSection #adminSkillsToolbar').count(),0,'Toolbar is above panels');
    const cards=await page.locator('#adminSkillHolders .admin-holder-group-list').evaluate(n=>getComputedStyle(n).gridTemplateColumns.split(' ').length);assert.equal(cards,3,'Holder grid has three columns');
    assert.deepEqual(await page.locator('#adminSkillView > p').allTextContents(),['Categorie: Discipline','Discipline: Timmeren','Specialisaties: Fijnwerk','Beschrijving']);
    assert.equal(await page.locator('#adminSkillView h3').count(),0,'No description/list headings');assert.equal(await page.locator('#adminSkillView table thead').count(),0);
    assert.deepEqual(await page.locator('#adminSkillView table tr td:first-child').allTextContents(),['Beginneling','Deskundige','Meester']);
    assert(await page.locator('#adminSkillSubtitle').isHidden());
    const corners=await page.locator('#adminSkillModeButton').evaluate(n=>{const s=getComputedStyle(n);return [s.borderTopLeftRadius,s.borderTopRightRadius,s.borderBottomLeftRadius,s.borderBottomRightRadius];});assert(corners.every(v=>parseFloat(v)>0));
    assert.equal(await page.locator('#adminSkillHolders .character-sidebar-name').count(),1);assert.equal(await page.locator('#adminSkillHolders .character-sidebar-portrait-image').count(),1);
    const widths=await page.locator('#adminSkillsSection').evaluate(n=>[...n.children].map(c=>c.getBoundingClientRect().width));assert(Math.abs(widths[0]-2*widths[1])<2);
    await page.locator('#adminSkillHolders').getByText('Ada Test',{exact:true}).waitFor();assert.equal(await page.locator('#adminSkillHolders img').count(),1);assert((await page.locator('#adminSkillHolders').innerText()).includes('Fijnwerk'));
    await page.evaluate(()=>{currentAdminSkill.holders.push({level:3,label:'Meester',characters:[{idCharacter:9,displayName:'Tweede personage',specialisations:[]}]});renderAdminHolders(currentAdminSkill);});
    assert.equal(await page.locator('#adminSkillHolders > hr').count(),1,'One separator between levels');
    const groupBorders=await page.locator('#adminSkillHolders .admin-holder-group').evaluateAll(nodes=>nodes.map(n=>getComputedStyle(n).borderTopWidth));assert(groupBorders.every(v=>parseFloat(v)===0),'No border around skill-level groups');
    const beforeEditCancel=await page.evaluate(()=>writes.length);await page.locator('#adminSkillModeButton').click();assert.equal(await page.locator('#adminSkillModeButton').innerText(),'Opslaan');
    assert(await page.locator('#adminSkillCancelButton').isVisible());await page.locator('#adminSkillDescription').fill('Annuleer bestaande wijziging');await page.locator('#adminSkillCancelButton').click();assert.equal(await page.evaluate(()=>writes.length),beforeEditCancel);await page.locator('#adminSkillView').getByText('Beschrijving',{exact:true}).waitFor();
    await page.locator('#adminSkillModeButton').click();await page.locator('#adminSkillDescription').fill('Nog niet bewaard');await page.locator('#adminSkillDescription').blur();
    await page.waitForTimeout(900);assert.equal(await page.evaluate(()=>writes.length),0,'Typing and blur never save');
    assert.equal(await page.locator('#adminSkillDisciplines input').inputValue(),'Timmeren');assert.equal(await page.locator('#adminSkillSpecialisations input').inputValue(),'Fijnwerk');
    await page.locator('#adminAddDisciplineButton').click();await page.locator('#adminSkillDisciplines input').last().fill('Smeden');
    await page.locator('#adminAddSpecialisationButton').click();await page.locator('#adminSkillSpecialisations input').last().fill('Precisie');
    await page.locator('#adminSkillModeButton').click();await page.locator('#adminSkillForm').waitFor({state:'hidden'});
    assert.equal(await page.evaluate(()=>writes.length),1);const payload=await page.evaluate(()=>writes[0].body);assert.equal(payload.description,'Nog niet bewaard');assert.deepEqual(payload.specialisations.slice(-2).map(s=>s.kind),['discipline','specialisation']);
    await page.locator('#adminSkillView').getByText('Discipline: Timmeren, Smeden',{exact:true}).waitFor();
    await page.locator('#adminSkillModeButton').click();await page.locator('#adminSkillName').fill('Bewaar mijn invoer');await page.evaluate(()=>failSave=true);await page.locator('#adminSkillModeButton').click();await page.locator('#adminFeedback').getByText('Kon de vaardigheid niet opslaan.',{exact:true}).waitFor();assert(await page.locator('#adminSkillForm').isVisible());assert.equal(await page.locator('#adminSkillName').inputValue(),'Bewaar mijn invoer');
    await page.evaluate(()=>failSave=false);await page.locator('#adminSkillModeButton').click();await page.locator('#adminSkillForm').waitFor({state:'hidden'});
    const beforeCancel=await page.evaluate(()=>writes.length);const previousTitle=await page.locator('#adminSkillTitle').innerText();
    await page.locator('#adminNewSkillButton').click();await page.locator('#adminSkillName').fill('Niet aanmaken');
    assert(await page.locator('#adminSkillCancelButton').isVisible());assert(await page.locator('#adminSkillCancelButton').evaluate(n=>n.parentElement===document.getElementById('adminSkillModeButton').parentElement));
    await page.locator('#adminSkillCancelButton').click();assert(await page.locator('#adminSkillForm').isHidden());assert.equal(await page.locator('#adminSkillTitle').innerText(),previousTitle);assert.equal(await page.evaluate(()=>writes.length),beforeCancel,'Cancel makes no API request');
    await page.locator('#adminNewSkillButton').click();await page.locator('#adminSkillName').fill('Nieuwe vaardigheid');await page.locator('#adminSkillDescription').fill('Volledige nieuwe inhoud');const before=await page.evaluate(()=>writes.length);await page.locator('#adminSkillDescription').blur();await page.waitForTimeout(800);assert.equal(await page.evaluate(()=>writes.length),before);
    await page.locator('#adminSkillModeButton').click();await page.locator('#adminSkillForm').waitFor({state:'hidden'});await page.locator('#adminSkillView').getByText('Volledige nieuwe inhoud',{exact:true}).waitFor();
    await page.setViewportSize({width:390,height:844});assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));assert.deepEqual(errors,[]);
    console.log('PASS: skill view/edit, explicit save only, separate kinds, holder portraits and specialisations, 2/3–1/3 layout, failed save retains draft, new-skill draft and mobile width.');
}finally{await browser.close();server.close();}})().catch(e=>{console.error(e);process.exitCode=1;server.close();});
