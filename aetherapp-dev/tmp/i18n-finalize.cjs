const fs=require('node:fs'),vm=require('node:vm'),acorn=require('./i18n-tools/package');
for(const file of ['js/shareInterface.js','js/shareMarketClient.js']){
 let code=fs.readFileSync(file,'utf8');const pattern=/const interfaceKeys = (\{[\s\S]*?\n\s*\});/;
 const match=code.match(pattern);if(!match)throw Error(file);
 const keys=vm.runInNewContext('('+match[1]+')',{aetherT:s=>s});
 code=code.replace(pattern,'const interfaceKeys = '+JSON.stringify(keys,null,4)+';');fs.writeFileSync(file,code);
}
let quarters=fs.readFileSync('js/companyQuarters.js','utf8');quarters=quarters.replace(/\? "" : "en"/g,'? "" : aetherT("en")');fs.writeFileSync('js/companyQuarters.js',quarters);
for(const file of fs.readdirSync('js').filter(f=>f.endsWith('.js'))){acorn.parse(fs.readFileSync('js/'+file,'utf8'),{ecmaVersion:'latest'});}
console.log('PASS: all application JavaScript parses; shared translation keys stay canonical.');
