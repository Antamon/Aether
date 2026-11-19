// All processing stays local. Only lengths and record labels are written to the report.
const fs=require('node:fs');
const originalLog=console.log;console.log=()=>{};require('./translation-cost-analysis.cjs');console.log=originalLog;
const tables=globalThis.analysisTables,groups=globalThis.analysisGroups;
const characters=new Map(tables.tblCharacter.records.map(c=>[c.id,[c.firstName,c.lastName].filter(Boolean).join(' ')]));
const events=new Map(tables.tblEvent.records.map(e=>[e.id,e.title]));
const nameMaps={};for(const name of ['tblSkill','tblSkillSpecialisation','tblSkillType','tblTrait','tblCompany','tblResearchChallenge','tblResearchDiscovery'])nameMaps[name]=new Map(tables[name].records.map(r=>[r.id,r.name||r.title||r.companyName]));
const fieldNames={content:'Inhoud',description:'Beschrijving',goals:'Doelen',achievements:'Verwezenlijkingen',gossip1:'Roddel 1',gossip2:'Roddel 2',gossip3:'Roddel 3',name:'Naam',beginner:'Beginneling',professional:'Deskundige',master:'Meester',resultTitle:'Resultaattitel',resultText:'Actieresultaat',functionDescription:'Functiebeschrijving'};
const sections={personal_background:'Persoonlijke achtergrond',knowledge:'Kennis',nature:'Aard',demeanour:'Gedrag'};
const candidates=[];
for(const [group,selection]of Object.entries(groups))for(const [table,fields]of Object.entries(selection))for(const row of tables[table].records)for(const field of fields){
 const text=row[field];if(typeof text!=='string'||text.trim()==='')continue;
 let label=nameMaps[table]?.get(row.id)||'';
 if(row.idCharacter)label=characters.get(row.idCharacter)||'Personage '+row.idCharacter;
 if(table==='tblCharacterSection')label+=' — '+(sections[row.section]||row.section);
 else label+=(label?' — ':'')+(fieldNames[field]||field);
 if(table==='tblCharacterDiary')label+=' ('+(events.get(row.idEvent)||'event '+row.idEvent)+')';
 if(table==='tblResearchStep'){
  const route=tables.tblResearchRoute.records.find(r=>r.id===row.idRoute),discovery=route&&tables.tblResearchDiscovery.records.find(d=>d.id===route.idDiscovery);
  label=(discovery?.title||'Discovery')+' — feedbackstap '+row.position+' (niveau '+(route?.effectiveLevel??route?.level??'?')+')';
 }
 candidates.push({group,table,id:row.id,field,label,rawCharacters:[...text].length,text});
}
(async()=>{
 const {chromium}=require('./feedback-browser/package');const browser=await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
 try{
  const context=await browser.newContext();await context.route('**/*',route=>route.abort());const page=await context.newPage();
  const measured=await page.evaluate(items=>items.map(item=>{
   const document=new DOMParser().parseFromString(item.text,'text/html');document.querySelectorAll('script,style').forEach(node=>node.remove());
   const text=document.body.textContent||'';const normalized=text.replace(/\s+/g,' ').trim();
   const {text:source,...metadata}=item;
   return {...metadata,textCharacters:[...text].length,words:normalized?normalized.split(' ').length:0,markupShare:Math.round(100*(1-[...text].length/item.rawCharacters))};
  }),candidates);
  const byText=[...measured].sort((a,b)=>b.textCharacters-a.textCharacters),byRaw=[...measured].sort((a,b)=>b.rawCharacters-a.rawCharacters);
  const totalText=measured.reduce((sum,item)=>sum+item.textCharacters,0),totalRaw=measured.reduce((sum,item)=>sum+item.rawCharacters,0);
  const summary={topByVisibleText:byText.slice(0,15),topByStoredLength:byRaw.slice(0,15),totals:{records:measured.length,textCharacters:totalText,storedCharacters:totalRaw,topFiveTextCharacters:byText.slice(0,5).reduce((sum,item)=>sum+item.textCharacters,0),topFiveStoredCharacters:byRaw.slice(0,5).reduce((sum,item)=>sum+item.rawCharacters,0)}};
  fs.writeFileSync('tmp/translation-long-texts-report.json',JSON.stringify(summary,null,2)+'\n');console.log(JSON.stringify(summary,null,2));
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
