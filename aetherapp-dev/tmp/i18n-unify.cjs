const fs=require('node:fs'),vm=require('node:vm');
let tsv=fs.readFileSync('tools/interface-translations.tsv','utf8');const known=new Set(tsv.split(/\r?\n/).map(line=>line.split('\t')[0]));
for(const [file,name] of [['js/shareInterface.js','copy'],['js/shareMarketClient.js','labels']]){
 let code=fs.readFileSync(file,'utf8');const pattern=new RegExp('const '+name+' = (\\{[\\s\\S]*?\\n    \\});');const match=code.match(pattern);if(!match)throw Error('Dictionary not found: '+file);
 const dictionary=vm.runInNewContext('('+match[1]+')',{aetherT:s=>s});
 for(const key of Object.keys(dictionary.nl)){const source=dictionary.nl[key];if(!known.has(source)){tsv+='\n'+source+'\t'+dictionary.en[key];known.add(source);}}
 code=code.replace(pattern,'const interfaceKeys = '+JSON.stringify(dictionary.nl,null,4)+';');
 code=code.replace(new RegExp('const tr = \\(key\\) => \\('+name+'\\[document\\.documentElement\\.lang\\?\\.slice\\(0, 2\\)\\] \\|\\| '+name+'\\.nl\\)\\[key\\](?: \\|\\| key)?;'),'const tr = key => aetherT(interfaceKeys[key] || key);');
 fs.writeFileSync(file,code);
}
fs.writeFileSync('tools/interface-translations.tsv',tsv+'\n');
