// Developer-only initial import. Models run locally; game text is never sent to a service.
// Usage: node tools/translate-content-offline.cjs path/to/export.sql [--prepare|--translate|--seed]
// Requires a local @huggingface/transformers + franc-min installation in the TEMP runtime.
const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const {spawnSync}=require('node:child_process'),{pathToFileURL}=require('node:url');
const runtime=path.join(process.env.TEMP||'/tmp','aether-content-translation');
const work=path.join(runtime,'content');fs.mkdirSync(work,{recursive:true});
const glossary=require('./content-translation-glossary.json');
const overrides=require('./content-translation-overrides.json');
const corrections=require('../sql/content-translation-corrections.json');
const selection={
 tblCharacter:['nationality'],
 tblCharacterSection:['content'],tblCharacterDiary:['goals','achievements','gossip1','gossip2','gossip3'],
 tblSkill:['name','description','beginner','professional','master'],tblSkillSpecialisation:['name'],tblSkillType:['name','description'],
 tblTrait:['name','description','traitGroup'],tblTraitTrack:['label','leftModuleLabel'],tblCharacterTie:['description'],
 tblCompany:['description'],tblCompanyPersonnel:['functionDescription'],tblCompanyQuarterEvent:['description'],
 tblResearchChallenge:['title','description'],tblResearchDiscovery:['title','description'],tblResearchStep:['content'],
 tblEvent:['description'],tblLanguage:['name'],tblCharacterSkillActionUse:['resultTitle','resultText'],tblCharacterSecuritiesTransaction:['description']
};
const richFields=new Set(['tblCharacterSection.content','tblCharacterDiary.goals','tblCharacterDiary.achievements','tblCompany.description','tblResearchStep.content']);
const hash=s=>crypto.createHash('sha256').update(s).digest('hex');
const php=process.env.AETHER_PHP||path.resolve('tmp/feedback-runtime/php/php.exe');
function prepare(){
 const tables=require('./content-dump-reader.cjs'),unique=new Map();let fieldCount=0;
 for(const [table,fields] of Object.entries(selection)){
  if(!tables[table])throw Error('Missing table '+table);
  for(const row of tables[table].records)for(const field of fields){const source=row[field];if(typeof source!=='string'||!source.trim())continue;fieldCount++;
   const rich=richFields.has(table+'.'+field)||/<\/?(?:p|strong|em|ul|li|div|span|h[1-6]|br)\b/i.test(source);
   const key=hash(source)+(rich?'html':'plain');if(!unique.has(key))unique.set(key,{source,rich,research:table.startsWith('tblResearch'),field:table+'.'+field});
  }
 }
 const typeSource=fs.readFileSync('api/companies/companyUtils.php','utf8').match(/function getCompanyTypeDefinitions\(\): array\s*\{([\s\S]*?)\n\}/)?.[1];
 if(!typeSource)throw Error('Company type definitions not found');
 for(const match of typeSource.matchAll(/'(?:label|description)'\s*=>\s*'((?:\\.|[^'])*)'/g)){
  const source=match[1].replace(/\\(['\\])/g,'$1');unique.set(hash(source)+'plain',{source,rich:false,research:false,field:'companyTypeDefinitions'});
 }
 unique.set(hash('Aandeelhouder')+'plain',{source:'Aandeelhouder',rich:false,research:false,field:'virtualShareTrait'});
 const result=spawnSync(php,['-d','extension_dir='+path.resolve('tmp/feedback-runtime/php/ext'),'-d','extension=mbstring','tools/content-translation-html.php'],{input:JSON.stringify([...unique.values()]),encoding:'utf8',maxBuffer:30*1024*1024});
 if(result.status!==0)throw Error(result.stderr);
 const entries=JSON.parse(result.stdout);fs.writeFileSync(path.join(work,'entries.json'),JSON.stringify(entries));
 fs.writeFileSync(path.join(work,'manifest.json'),JSON.stringify({sourceExport:path.basename(process.argv[2]),exportHash:hash(fs.readFileSync(process.argv[2])),populatedFields:fieldCount,uniqueSources:entries.length,selection}));
 console.log(JSON.stringify({fields:fieldCount,unique:entries.length,parts:entries.reduce((n,e)=>n+e.parts.length,0),characters:entries.reduce((n,e)=>n+e.parts.join('').length,0)}));
}
async function translate(){
 const {franc}=await import(pathToFileURL(path.join(runtime,'node_modules/franc-min/index.js')).href);
 const {pipeline,env}=await import(pathToFileURL(path.join(runtime,'node_modules/@huggingface/transformers/dist/transformers.node.mjs')).href);
 env.cacheDir=path.join(runtime,'models');env.localModelPath=env.cacheDir;env.allowLocalModels=true;
 const entries=JSON.parse(fs.readFileSync(path.join(work,'entries.json')));
 const cacheFile=path.join(work,'segments.json');const cache=fs.existsSync(cacheFile)?JSON.parse(fs.readFileSync(cacheFile)):{};
 Object.assign(cache,corrections);
 const englishKeys=new Set(['Academic','Criminal','Discipline','Crafts','Society','Warcraft','Reinforcement','Resistance','Esoteric','Accuracy','Healing','Baker','Mechanic','Printing','Cryptography','Boxing','Painter','Strategy','Logistics','Mineralogy','Poet','Transport documents','Drug','Claws','Sniper','Sigils','Dimensional research','Parakinesiology','Parapsychology',"Parautilitarian's",'Werewolf','Luck of the Irish','Ancient Aramaic','No additional information','No additional information.']);
 const knownNl=new Set(Object.keys(glossary).filter(s=>!englishKeys.has(s)));
 const tasks={nl:[],en:[]};
 function language(text){
  if(englishKeys.has(text.trim()))return 'en';if(knownNl.has(text.trim()))return 'nl';
  return franc(text,{only:['nld','eng'],minLength:20})==='eng'?'en':'nl';
 }
 const manual={...glossary};
 // Reuse established interface terminology only for exact phrases, never substring substitutions.
 for(const line of fs.readFileSync('tools/interface-translations.tsv','utf8').split(/\r?\n/)){const [nl,en]=line.split('\t');if(nl&&en&&!/[{}]/.test(nl)&&!(nl in manual))manual[nl]=en;}
 for(const entry of entries){
  entry.language=language(entry.parts.join(' '));entry.segments=[];
  if(overrides[entry.source.trim()]){entry.override=overrides[entry.source.trim()];continue;}
  for(const part of entry.parts){
   const leading=part.match(/^\s*/)[0],trailing=part.match(/\s*$/)[0],body=part.trim();
   if(!/\p{L}/u.test(body)||/^(?:https?:\/\/|www\.|\S+@\S+\.)/i.test(body)){
    const key=entry.language+':'+body;cache[key]=body;entry.segments.push({leading,trailing,parts:[{key,source:body}],language:entry.language});continue;
   }
   if(body in manual){const sourceLanguage=language(body);const key=sourceLanguage+':'+body;cache[key]=manual[body];entry.segments.push({leading,trailing,parts:[{key,source:body}],language:sourceLanguage});continue;}
   const chunks=[];for(const sentence of new Intl.Segmenter(entry.language,{granularity:'sentence'}).segment(body)){
    // Stay below Marian's context limit; split long sentences at whitespace without dropping it.
    let text=sentence.segment;while(text.length>600){let at=text.lastIndexOf(' ',600);if(at<150)at=600;chunks.push(text.slice(0,at));text=text.slice(at);}if(text)chunks.push(text);
   }
   const parts=chunks.map(source=>{const sourceLanguage=corrections['en:'+source.trim()]?'en':corrections['nl:'+source.trim()]?'nl':source.trim().length>=60?language(source):entry.language;const key=sourceLanguage+':'+source.trim();if(source.trim()&&!cache[key]&&!tasks[sourceLanguage].some(t=>t.key===key))tasks[sourceLanguage].push({key,source:source.trim()});return {key,source,language:sourceLanguage};});
   entry.segments.push({leading,trailing,parts,language:entry.language});
  }
 }
 fs.writeFileSync(path.join(work,'prepared.json'),JSON.stringify(entries));
 console.log('Pending local segments: '+JSON.stringify(Object.fromEntries(Object.entries(tasks).map(([k,v])=>[k,v.length]))));
 for(const lang of ['nl','en']){
  const todo=tasks[lang];if(!todo.length)continue;
  const model=await pipeline('translation','Xenova/opus-mt-'+lang+'-'+(lang==='nl'?'en':'nl'),{dtype:'q8',device:'cpu',local_files_only:true,session_options:{intraOpNumThreads:3,interOpNumThreads:1}});
  let index=0;const started=Date.now();
  for(const task of todo){
   const result=await model(task.source,{max_new_tokens:Math.min(512,Math.max(96,Math.ceil(task.source.length*1.2))),num_beams:3,no_repeat_ngram_size:3});
   const translated=result[0].translation_text.trim();if(!translated)throw Error('Empty translation for '+hash(task.source));
   cache[task.key]=translated;index++;
   if(index%10===0){fs.writeFileSync(cacheFile,JSON.stringify(cache));console.log(`${lang}: ${index}/${todo.length} (${Math.round((Date.now()-started)/1000)}s)`);}
  }
  fs.writeFileSync(cacheFile,JSON.stringify(cache));await model.dispose();
 }
 fs.writeFileSync(cacheFile,JSON.stringify(cache));
}
function seed(){
 const entries=JSON.parse(fs.readFileSync(path.join(work,'prepared.json'))),cache=JSON.parse(fs.readFileSync(path.join(work,'segments.json')));
 const rows=new Map();let fields=0;
 const escapeHtml=s=>s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
 for(const entry of entries){
  for(const targetLanguage of ['nl','en']){
   let changed=false;
   const translatedParts=entry.segments.map(segment=>segment.leading+segment.parts.map(({key,source,language})=>{
    if(!source.trim()||(language||segment.language)===targetLanguage)return source;
    if(!cache[key])throw Error('Missing segment '+hash(source));
    const repeated=/(.{15,80})\1\1\1/;
    if((repeated.test(cache[key])&&!repeated.test(source))||(source.length>100&&cache[key].length<source.length*.15))throw Error('Translation needs review before seed: '+hash(key));
    if(cache[key]!==source.trim())changed=true;
    return source.match(/^\s*/)[0]+cache[key]+source.match(/\s*$/)[0];
   }).join('')+segment.trailing);
   if(!changed&&!entry.override)continue; // Already in the requested language, or an invariant game term.
   const translated=entry.override?entry.override[targetLanguage]:(entry.rich?entry.template.replace(/AETHERTRANSLATIONPART(\d+)END/g,(_,n)=>escapeHtml(translatedParts[Number(n)])):translatedParts.join(''));
   if(translated===entry.source.trim())continue;
   if(/AETHERTRANSLATIONPART\d+END/.test(translated))throw Error('Unreplaced HTML placeholder');
   const aliases=new Set([entry.source,entry.source.trim()]);if(entry.sanitized)aliases.add(entry.sanitized);
   for(const source of aliases){const row={sourceHash:hash(source),targetLanguage,sourceLanguage:entry.language,sourceText:source,translatedText:translated,format:entry.rich?'html':'plain'};rows.set(row.sourceHash+':'+targetLanguage,row);}
  }
  fields++;
 }
 const quote=s=>"CONVERT(0x"+Buffer.from(s,'utf8').toString('hex')+" USING utf8mb4)";
 const file='sql/migrations/0028_content_translation_seed.sql';
 const out=['-- Initial offline Dutch/English translations of the 2026-11-19 export.','-- Apply after 0027. Source rows are never modified; exact hashes reject stale content.','-- INSERT IGNORE preserves any later editorial corrections.','START TRANSACTION;'];
 for(const row of rows.values())out.push('INSERT IGNORE INTO tblContentTranslation (sourceHash,targetLanguage,sourceLanguage,sourceText,translatedText,format,provider) VALUES ('+[row.sourceHash,row.targetLanguage,row.sourceLanguage,row.sourceText,row.translatedText,row.format,'offline-opus-mt'].map(quote).join(',')+');');
 out.push("INSERT IGNORE INTO tblSchemaMigration (migrationCode,description) VALUES ('0028_content_translation_seed','Initial locally generated bilingual game content');",'COMMIT;','');fs.writeFileSync(file,out.join('\n'));
 const manifest=JSON.parse(fs.readFileSync(path.join(work,'manifest.json')));
 fs.writeFileSync('docs/content-translation-coverage.json',JSON.stringify({...manifest,translatedOrOriginalSources:fields,translationRows:rows.size,provider:'offline-opus-mt',sourceLanguages:Object.fromEntries(['nl','en'].map(l=>[l,entries.filter(e=>e.language===l).length]))},null,2)+'\n');
 console.log('Seed written: '+rows.size+' translations');
}
(async()=>{const mode=process.argv[3];if(!mode||mode==='--prepare')prepare();if(!mode||mode==='--translate')await translate();if(!mode||mode==='--seed')seed();process.exit(0);})().catch(error=>{console.error(error);process.exit(1);});
