const fs=require('node:fs'),acorn=require('./i18n-tools/package');
const copy=JSON.parse(fs.readFileSync('js/interface-copy.json','utf8'));const key=s=>s.trim().replace(/\s+/g,' ');const changed=[];
for(const file of fs.readdirSync('.').filter(f=>f.endsWith('.html'))){let html=fs.readFileSync(file,'utf8');const original=html;
 html=html.replace(/>([^<>]+)</g,(whole,value,offset,source)=>{const label=key(value);if(!copy[label])return whole;const preceding=source.slice(0,offset).match(/<([\w-]+)\b([^>]*)$/);if(!preceding||/data-i18n/.test(preceding[2])||['script','style','title','textarea','option'].includes(preceding[1]))return whole;return '><span data-i18n="'+label.replace(/&/g,'&amp;').replace(/"/g,'&quot;')+'">'+value+'</span><';});
 if(html!==original){fs.writeFileSync(file,html);changed.push(file);}
}
for(const file of fs.readdirSync('js').filter(f=>f.endsWith('.js')&&!['interface-copy.js','i18n.js'].includes(f))){let code=fs.readFileSync('js/'+file,'utf8');const edits=[],ast=acorn.parse(code,{ecmaVersion:'latest'});
 function walk(node){if(!node||typeof node!=='object')return;if(node.type==='CallExpression'&&node.callee?.name==='aetherT')return;
  if(node.type==='TemplateLiteral')for(const q of node.quasis){const raw=code.slice(q.start,q.end);for(const match of raw.matchAll(/>([^<>]+)(?=<|$)/g)){const label=key(match[1]);if(copy[label]){const start=q.start+match.index+1;edits.push({start,end:start+match[1].length,value:'${aetherT('+JSON.stringify(label)+')}'});}}}
  for(const [k,v]of Object.entries(node)){if(['start','end','loc'].includes(k))continue;if(Array.isArray(v))v.forEach(walk);else if(v&&typeof v==='object')walk(v);}
 }walk(ast);if(edits.length){for(const edit of edits.sort((a,b)=>b.start-a.start))code=code.slice(0,edit.start)+edit.value+code.slice(edit.end);acorn.parse(code,{ecmaVersion:'latest'});fs.writeFileSync('js/'+file,code);changed.push('js/'+file);}
}
console.log(changed.join('\n')||'No additional multiline interface copy.');
