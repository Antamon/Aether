const fs=require('node:fs'),crypto=require('node:crypto'),path=require('node:path'),https=require('node:https');
const {Client}=require('C:/Users/jan_m/.vscode/extensions/natizyskunk.sftp-1.16.4/node_modules/ssh2');const config=JSON.parse(fs.readFileSync('aetherapp-dev/.vscode/sftp.json','utf8'));const p=Array.isArray(config)?config[0]:config;
const base=p.remotePath.replace(/\/$/,'').replace(/\/aetherapp-dev$/,'');
if(base!=='/customers/1/a/4/c8q4331sl/webroots/www/aether')throw Error('Unexpected deployment root');
const hash=b=>crypto.createHash('sha256').update(b).digest('hex');const release='2026.11.17.7';
const c=new Client();c.on('ready',()=>c.sftp(async(e,s)=>{if(e){c.end();return;}const call=(m,...a)=>new Promise((resolve,reject)=>s[m](...a,(e,r)=>e?reject(new Error(m+': '+e.code)):resolve(r)));
const published=[];
try{for(const app of ['aetherapp','aetherapp-dev']){
    const current=(await call('readFile',base+'/'+app+'/VERSION')).toString().trim();if(!['2026.11.17.6',release].includes(current))throw Error('Unexpected deployed version: '+current);
    const source=fs.readFileSync(app+'/js/mainFunctions.js'),digest=hash(source).slice(0,16);
    const changes=[['js/mainFunctions.js',source]];
    for(const page of ['admin.html','companies.html','eventParticipation.html','index.html','leaderPersonnel.html','research.html','static.html']){
        const old=await call('readFile',base+'/'+app+'/'+page);const text=old.toString();const updated=text.replace(/js\/mainFunctions\.js\?v=[a-f0-9]+/g,'js/mainFunctions.js?v='+digest);if(text!==updated)changes.push([page,Buffer.from(updated)]);
    }
    changes.push(['VERSION',Buffer.from(release+'\n')]);
    for(const [file,bytes]of changes){const remote=base+'/'+app+'/'+file;const old=await call('readFile',remote);if(!old.equals(bytes)){
        const backup=path.join('aetherapp-dev/tmp/auth-backup-'+release,app,file);fs.mkdirSync(path.dirname(backup),{recursive:true});if(!fs.existsSync(backup))fs.writeFileSync(backup,old);
        const temp=remote+'.auth-fix.tmp';await call('writeFile',temp,bytes,{mode:0o644});if(hash(await call('readFile',temp))!==hash(bytes))throw Error('Upload verification failed');await call('ext_openssh_rename',temp,remote);
    }if(hash(await call('readFile',remote))!==hash(bytes))throw Error('Published verification failed');published.push({app,file,sha256:hash(bytes)});}
    console.log(app+': login error handling and asset references verified; '+release);
}
fs.writeFileSync('aetherapp-dev/tmp/auth-release.json',JSON.stringify({release,published},null,2));
}catch(e){console.error(e.message);process.exitCode=1;}finally{c.end();}
}));c.on('error',e=>{console.log(e.message);process.exitCode=1;});c.connect({host:p.host,port:p.port||22,username:p.username,password:p.password,readyTimeout:20000});
