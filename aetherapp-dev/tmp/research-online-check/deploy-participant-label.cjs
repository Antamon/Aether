const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const {Client}=require('C:/Users/jan_m/.vscode/extensions/natizyskunk.sftp-1.16.4/node_modules/ssh2');
const config=JSON.parse(fs.readFileSync('.vscode/sftp.json','utf8'));
const p=Array.isArray(config)?config[0]:config;
const root=p.remotePath.replace(/\/$/,'');
if(root!=='/customers/1/a/4/c8q4331sl/webroots/www/aether/aetherapp-dev')throw Error('Unexpected webroot');
const release='2026.11.18.1';
const files=['js/researchAdmin.js','research.html','VERSION'];
const sha=b=>crypto.createHash('sha256').update(b).digest('hex');
const connection=new Client();
connection.on('ready',()=>connection.sftp(async(err,sftp)=>{
  if(err){console.error('SFTP initialization failed');connection.end();process.exitCode=1;return;}
  const call=(method,...args)=>new Promise((resolve,reject)=>sftp[method](...args,(e,result)=>e?reject(new Error(method+': '+e.code)):resolve(result)));
  const records=[];
  try{
    for(const app of ['aetherapp-dev','aetherapp']){
      const remote=root.replace(/aetherapp-dev$/,app);
      const current=(await call('readFile',remote+'/VERSION')).toString().trim();
      if(!['2026.11.17.8',release].includes(current))throw Error('Unexpected online version: '+app+' '+current);
      for(const file of files){
        const local=fs.readFileSync(file),target=remote+'/'+file,old=await call('readFile',target);
        if(sha(old)!==sha(local)){
          const backup=path.join('tmp/research-online-check/backup-'+release,app,file);
          fs.mkdirSync(path.dirname(backup),{recursive:true});
          if(!fs.existsSync(backup))fs.writeFileSync(backup,old);
          const temporary=target+'.research-'+release+'.tmp';
          await call('writeFile',temporary,local,{mode:0o644});
          if(sha(await call('readFile',temporary))!==sha(local))throw Error('Temporary hash mismatch: '+file);
          await call('ext_openssh_rename',temporary,target);
        }
        const actual=sha(await call('readFile',target));
        if(actual!==sha(local))throw Error('Published hash mismatch: '+app+'/'+file);
        records.push({app,file,sha256:actual});
      }
      console.log(app+': '+files.length+' files verified; VERSION '+release);
    }
    fs.writeFileSync('tmp/research-online-check/feedback-deployment.json',JSON.stringify({release,records},null,2));
  }catch(e){console.error(e.message);process.exitCode=1;}finally{connection.end();}
}));
connection.on('error',()=>{console.error('SSH connection failed');process.exitCode=1;});
connection.connect({host:p.host,port:p.port||22,username:p.username,password:p.password,readyTimeout:20000});
