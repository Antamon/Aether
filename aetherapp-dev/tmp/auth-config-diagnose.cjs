const fs=require('node:fs');const {Client}=require('C:/Users/jan_m/.vscode/extensions/natizyskunk.sftp-1.16.4/node_modules/ssh2');
const config=JSON.parse(fs.readFileSync('aetherapp-dev/.vscode/sftp.json','utf8'));const p=Array.isArray(config)?config[0]:config;
const c=new Client();c.on('ready',()=>c.sftp(async(e,s)=>{if(e){c.end();return;}
const call=(m,...a)=>new Promise((resolve,reject)=>s[m](...a,(e,r)=>e?reject(new Error(e.code)):resolve(r)));
const base=p.remotePath.replace(/\/$/,'').replace(/\/aetherapp-dev$/,'');
try{for(const app of ['aetherapp','aetherapp-dev'])for(const f of ['db.php','config.local.php','db.legacy.local.php','api/shared/config.php','api/shared/database.php','api/shared/runtime.php','api/shared/runtime.json']){
try{const b=await call('readFile',base+'/'+app+'/'+f);const local=fs.existsSync(app+'/'+f)?fs.readFileSync(app+'/'+f):null;console.log(JSON.stringify({app,file:f,exists:true,bytes:b.length,localMatches:local?b.equals(local):null,configKeys:f==='config.local.php'?[...b.toString().matchAll(/['"]([A-Za-z_]+)['"]\s*=>/g)].map(m=>m[1]):undefined}));}catch(e){console.log(JSON.stringify({app,file:f,error:e.message}));}}
const debug=(await call('readFile',base+'/aetherapp-dev/debug.log')).toString();console.log(JSON.stringify({debugTail:debug.split('\n').slice(-30).map(l=>l.replace(/password\s*[:=]\s*\S+/gi,'password=[redacted]'))}));
}finally{c.end();}}));c.on('error',e=>console.log(e.message));c.connect({host:p.host,port:p.port||22,username:p.username,password:p.password,readyTimeout:20000});
