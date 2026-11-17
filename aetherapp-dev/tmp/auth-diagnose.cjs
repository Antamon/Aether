const fs=require('node:fs'),https=require('node:https'),crypto=require('node:crypto');
const {Client}=require('C:/Users/jan_m/.vscode/extensions/natizyskunk.sftp-1.16.4/node_modules/ssh2');
const config=JSON.parse(fs.readFileSync('aetherapp-dev/.vscode/sftp.json','utf8'));
const p=Array.isArray(config)?config[0]:config;
for(const file of ['checkLogin.php','version.php'])https.get('https://oneiros.be/aether/aetherapp/'+file,res=>{let data='';res.on('data',b=>data+=b);res.on('end',()=>console.log(JSON.stringify({endpoint:file,status:res.statusCode,type:res.headers['content-type'],body:data.slice(0,800)})));}).on('error',e=>console.log(file+': '+e.message));
const conn=new Client();conn.on('ready',()=>conn.sftp(async(error,sftp)=>{
if(error){console.log('SFTP initialization failed');conn.end();return;}
const call=(method,...args)=>new Promise((resolve,reject)=>sftp[method](...args,(e,result)=>e?reject(new Error(e.code)):resolve(result)));
const base=p.remotePath.replace(/\/$/,'').replace(/\/aetherapp-dev$/,'');
try{
for(const app of ['aetherapp','aetherapp-dev'])for(const file of ['VERSION','checkLogin.php','sessionUserBootstrap.php','api/auth/accessControl.php','api/shared/session.php','js/mainFunctions.js']){
try{const bytes=await call('readFile',base+'/'+app+'/'+file);const local=fs.readFileSync(app+'/'+file);console.log(JSON.stringify({app,file,matches:bytes.equals(local),sha256:crypto.createHash('sha256').update(bytes).digest('hex')}));}catch(e){console.log(app+'/'+file+': '+e.message);}
}
for(const dir of [base+'/aetherapp',base+'/aetherapp-dev',base,base.replace(/\/aether$/,'')]){
try{const items=await call('readdir',dir);console.log(JSON.stringify({directory:dir,logs:items.filter(x=>/error.?log$|\.log$/i.test(x.filename)).map(x=>({name:x.filename,size:x.attrs.size}))}));
for(const item of items.filter(x=>/error.?log$/i.test(x.filename)&&x.attrs.size<2000000)){const log=(await call('readFile',dir+'/'+item.filename)).toString();console.log(JSON.stringify({log:dir+'/'+item.filename,tail:log.split('\n').slice(-60).filter(line=>/fatal|parse|checkLogin|sessionUser|accessControl|research|mainFunctions/i.test(line))}));}}
catch(e){console.log('Log directory unavailable: '+e.message);}
}
}finally{conn.end();}
}));conn.on('error',e=>console.log('SSH: '+e.message));conn.connect({host:p.host,port:p.port||22,username:p.username,password:p.password,readyTimeout:20000});
