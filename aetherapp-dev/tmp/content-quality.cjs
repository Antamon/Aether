const fs=require('fs'),path=require('path'),crypto=require('crypto');
const work=path.join(process.env.TEMP,'aether-content-translation/content');
const entries=JSON.parse(fs.readFileSync(path.join(work,'prepared.json'))),cache=JSON.parse(fs.readFileSync(path.join(work,'segments.json')));
const flags=[];let completed=0,pending=0;
for(const entry of entries)for(const segment of entry.segments)for(const part of segment.parts){
 const source=part.source.trim(),translated=cache[part.key];if(!source)continue;if(!translated){pending++;continue;}completed++;
 if(source.length>100&&(translated.length<source.length*.20||translated.length>source.length*3.3)||/(.{15,80})\1\1\1/.test(translated))flags.push({field:entry.field,key:crypto.createHash('sha256').update(part.key).digest('hex').slice(0,12),sourceChars:source.length,targetChars:translated.length,source,translated});
}
fs.writeFileSync(path.join(work,'quality-flags.json'),JSON.stringify(flags));
console.log(JSON.stringify({completed,pending,flags:flags.map(({source,translated,...summary})=>summary)}));
const labels=entries.filter(e=>/^tbl(SkillSpecialisation\.name|Trait\.name|Trait\.traitGroup|Language\.name)$/.test(e.field));
console.log(labels.slice(0,40).map(e=>({source:e.source,translated:e.segments.flatMap(s=>s.parts).map(p=>cache[p.key]||'(pending)').join('')})));
