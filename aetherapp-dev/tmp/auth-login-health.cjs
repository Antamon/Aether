const https=require('node:https');
const get=url=>new Promise((resolve,reject)=>https.get(url,{headers:{'Cache-Control':'no-cache'}},res=>{let body='';res.on('data',b=>body+=b);res.on('end',()=>resolve({status:res.statusCode,body}));}).on('error',reject));
(async()=>{
for(const route of ['checkLogin.php','api/research/http.php?op=catalog','version.php']){
const r=await get('https://oneiros.be/aether/aetherapp/'+route);
console.log(JSON.stringify({endpoint:route,...r}));
if(route==='checkLogin.php'&&(r.status!==200||JSON.parse(r.body).status!=='redirect'))throw Error('Unexpected anonymous login response');
if(route.startsWith('api/')&&r.status!==401)throw Error('Unauthenticated API must remain protected');
}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
