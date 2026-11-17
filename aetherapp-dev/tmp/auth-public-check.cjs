const https=require('node:https'),crypto=require('node:crypto'),fs=require('node:fs');
const get=url=>new Promise((resolve,reject)=>https.get(url,res=>{let body='';res.on('data',b=>body+=b);res.on('end',()=>resolve({status:res.statusCode,body}));}).on('error',reject));
(async()=>{for(const app of ['aetherapp','aetherapp-dev']){
const version=await get('https://oneiros.be/aether/'+app+'/version.php');
const script=await get('https://oneiros.be/aether/'+app+'/js/mainFunctions.js?auth-fix=2026.11.17.7');
const page=await get('https://oneiros.be/aether/'+app+'/index.html?auth-fix=2026.11.17.7');
const expected=crypto.createHash('sha256').update(fs.readFileSync(app+'/js/mainFunctions.js')).digest('hex');
console.log(JSON.stringify({app,version:version.body,scriptMatches:crypto.createHash('sha256').update(script.body).digest('hex')===expected,pageUsesNewScript:page.body.includes('js/mainFunctions.js?v='+expected.slice(0,16))}));
}})().catch(e=>{console.error(e.message);process.exitCode=1;});
