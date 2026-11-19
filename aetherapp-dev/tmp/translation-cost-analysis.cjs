// Read-only SQL dump analysis. No SQL is executed and no content is sent to a provider.
const fs=require('node:fs'),path=require('node:path');
const input=path.resolve(process.argv[2]||'../aetherapp/sql/c8q4331sl_oneiros_be_aether.sql');
const sql=fs.readFileSync(input,'utf8'),tables={};
for(const match of sql.matchAll(/^CREATE TABLE `([^`]+)`\s*\(([\s\S]*?)^\)[^\n]*;/gm)){
 const columns=[];for(const column of match[2].matchAll(/^\s*`([^`]+)`\s+([a-z]+(?:\([^\n]*?\))?)/gm))columns.push({name:column[1],type:column[2],count:0,chars:0,unique:new Set()});
 tables[match[1]]={columns,rows:0,records:[]};
}
console.log('Parsed schemas: '+Object.keys(tables).length+'; character tables: '+Object.keys(tables).filter(name=>name.startsWith('tblCharacter')).join(', '));
const escapes={'0':'\0','b':'\b','n':'\n','r':'\r','t':'\t','Z':'\x1a'};
const inserts=/^INSERT INTO `([^`]+)`\s*\(([^)]*)\) VALUES\s*/gm;let insert;
while((insert=inserts.exec(sql))){
 const table=tables[insert[1]];if(!table)throw Error('Unknown table '+insert[1]);
 const names=[...insert[2].matchAll(/`([^`]+)`/g)].map(m=>m[1]);let at=inserts.lastIndex;
 const skip=()=>{while(/\s/.test(sql[at]||'')&&at<sql.length)at++;};
 function value(){skip();if(sql[at]==="'"){
   at++;let text='';let closed=false;while(at<sql.length){const char=sql[at++];if(char==='\\'){const next=sql[at++];text+=escapes[next]??next;}else if(char==="'"){if(sql[at]==="'"){text+="'";at++;}else{closed=true;break;}}else text+=char;}if(!closed)throw Error('Unclosed SQL string');return text;
  }
  const start=at;while(at<sql.length&&!/[,)\s]/.test(sql[at]))at++;const token=sql.slice(start,at);if(!/^(?:NULL|[-+]?\d+(?:\.\d+)?(?:[eE][-+]?\d+)?|0x[\da-f]+)$/i.test(token))throw Error('Unsupported literal in '+insert[1]);return token.toUpperCase()==='NULL'?null:Number(token);
 }
 while(true){skip();if(sql[at]!== '(')throw Error('Expected SQL row in '+insert[1]);at++;const row=[];
  while(true){row.push(value());skip();const separator=sql[at++];if(separator===')')break;if(separator!==',')throw Error('Expected column separator in '+insert[1]);}
  if(row.length!==names.length)throw Error('Column count mismatch in '+insert[1]);table.rows++;
  table.records.push(Object.fromEntries(names.map((name,index)=>[name,row[index]])));
  row.forEach((text,index)=>{if(typeof text!=='string'||text.trim()==='')return;const column=table.columns.find(c=>c.name===names[index]);if(!column)throw Error('Unknown column');column.count++;column.chars+=Array.from(text).length;column.unique.add(text);});
  skip();if(sql[at]===';'){at++;break;}if(sql[at++]!==',')throw Error('Expected row separator in '+insert[1]);
 }
 inserts.lastIndex=at;
}
const schema=Object.fromEntries(Object.entries(tables).map(([name,table])=>[name,{rows:table.rows,columns:table.columns.filter(c=>c.count).map(c=>({name:c.name,type:c.type,nonEmpty:c.count,characters:c.chars,unique:c.unique.size}))}]));
fs.writeFileSync('tmp/translation-cost-schema.json',JSON.stringify(schema,null,2));
for(const [name,table] of Object.entries(schema).filter(([name])=>name.startsWith('tbl')))console.log(name+' ('+table.rows+' rows): '+table.columns.map(c=>c.name+'='+c.characters).join(', '));
globalThis.analysisTables=tables;
const groups={
 'Personageachtergronden en persoonlijkheid':{tblCharacterSection:['content']},
 'Dagboeken en roddels':{tblCharacterDiary:['goals','achievements','gossip1','gossip2','gossip3']},
 'Vaardigheden en specialisaties':{tblSkill:['name','description','beginner','professional','master'],tblSkillSpecialisation:['name'],tblSkillType:['name','description']},
 'Statuseigenschappen':{tblTrait:['name','description','traitGroup'],tblTraitTrack:['label','leftModuleLabel']},
 'Relaties tussen personages':{tblCharacterTie:['description']},
 'Bedrijven en bedrijfsgebeurtenissen':{tblCompany:['description'],tblCompanyPersonnel:['functionDescription'],tblCompanyQuarterEvent:['description']},
 'Onderzoek':{tblResearchChallenge:['title','description'],tblResearchDiscovery:['title','description'],tblResearchStep:['content']},
 'Overige spelteksten':{tblEvent:['description'],tblLanguage:['name'],tblCharacterSkillActionUse:['resultTitle','resultText'],tblCharacterSecuritiesTransaction:['description']}
};
globalThis.analysisGroups=groups;
const count=text=>Array.from(text).length,all=new Set(),report=[];
for(const [group,selection]of Object.entries(groups)){
 let raw=0,fields=0,fieldUnique=0;const groupUnique=new Set();
 for(const [name,columns]of Object.entries(selection))for(const nameOfColumn of columns){const column=tables[name].columns.find(c=>c.name===nameOfColumn);if(!column)throw Error('Missing selected field '+name+'.'+nameOfColumn);raw+=column.chars;fields+=column.count;for(const text of column.unique){fieldUnique+=count(text);all.add(text);groupUnique.add(text);}}
 report.push({group,rawCharacters:raw,nonEmptyFields:fields,uniqueWithinGroupCharacters:[...groupUnique].reduce((sum,text)=>sum+count(text),0),uniqueWithinFieldCharacters:fieldUnique});
}
const total=report.reduce((sum,row)=>sum+row.rawCharacters,0),unique=[...all].reduce((sum,text)=>sum+count(text),0);
const markupCharacters=[...all].reduce((sum,text)=>sum+(text.match(/<[^>]*>/g)||[]).reduce((n,tag)=>n+count(tag),0),0);
const prices=characters=>({withUnusedMonthlyAllowanceUSD:Math.max(0,characters-500000)*20/1000000,withoutAllowanceUSD:characters*20/1000000});
const result={file:path.basename(input),fileBytes:fs.statSync(input).size,tables:Object.keys(tables).length,totalRows:Object.values(tables).reduce((sum,t)=>sum+t.rows,0),groups:report,rawCharacters:total,uniqueCharacters:unique,markupCharacters,charactersWithoutMarkup:unique-markupCharacters,rawPrices:prices(total),uniquePrices:prices(unique)};
fs.writeFileSync('tmp/translation-cost-report.json',JSON.stringify(result,null,2)+'\n');
console.log('\nTRANSLATION ESTIMATE\n'+JSON.stringify(result,null,2));
