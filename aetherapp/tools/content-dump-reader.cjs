// Read-only SQL dump analysis. No SQL is executed and no content is sent to a provider.
const fs=require('node:fs'),path=require('node:path');
const input=path.resolve(process.argv[2]||'../aetherapp/sql/c8q4331sl_oneiros_be_aether.sql');
const sql=fs.readFileSync(input,'utf8'),tables={};
for(const match of sql.matchAll(/^CREATE TABLE `([^`]+)`\s*\(([\s\S]*?)^\)[^\n]*;/gm)){
 const columns=[];for(const column of match[2].matchAll(/^\s*`([^`]+)`\s+([a-z]+(?:\([^\n]*?\))?)/gm))columns.push({name:column[1],type:column[2],count:0,chars:0,unique:new Set()});
 tables[match[1]]={columns,rows:0,records:[]};
}

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

module.exports=tables;
