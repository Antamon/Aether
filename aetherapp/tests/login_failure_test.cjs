const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http'),vm=require('node:vm');
const {chromium}=require(process.env.RESEARCH_PLAYWRIGHT||'../tmp/feedback-browser/package');
const root=path.resolve(__dirname,'..');
const script=fs.readFileSync(path.join(root,'js/mainFunctions.js'));
const html='<!doctype html><meta charset="utf-8"><body><div id="navbarName"></div><script src="js/mainFunctions.js"></script></body>';
const server=http.createServer((req,res)=>{if(req.url.endsWith('/js/mainFunctions.js')){res.setHeader('Content-Type','text/javascript');res.end(script);}else if(req.url==='/index.php'){res.end('<p>Oneiros</p>');}else{res.setHeader('Content-Type','text/html');res.end(html);}});
const user={status:'ok',user:{id:1,displayName:'Test',role:'participant'},csrfToken:'test'};
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const browser=await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
 try{
  const url='http://127.0.0.1:'+server.address().port+'/aether/aetherapp/index.html';
  for(const failure of ['500','503','403','network','invalid-json','invalid-payload','invalid-user']){
   const page=await browser.newPage();
   await page.route('**/checkLogin.php',route=>{
    if(failure==='network')return route.abort();
    return route.fulfill({status:/^\d+$/.test(failure)?Number(failure):200,contentType:'application/json',body:failure==='invalid-json'?'<html>Serverfout</html>':JSON.stringify(failure==='invalid-user'?{status:'ok',user:'invalid'}:{error:'Temporary failure'})});
   });
   await page.goto(url);await page.locator('#aether-login-error button').waitFor();
   assert.equal(page.url(),url,failure+' must stay in Aether');
   assert.match(await page.locator('#aether-login-error').innerText(),/opnieuw laden/);
   assert.equal(await page.evaluate(()=>window.AETHER_CURRENT_USER),null,'Failed response cannot authenticate');
   await page.close();
  }
  for(const hooks of [false,true]){
   const page=await browser.newPage();
   if(hooks)await page.addInitScript(()=>{window.AetherI18n={initialize(){throw Error('Optional language failure');}};window.AetherTranslation={initialize(){throw Error('Optional translation failure');},consume(){throw Error('Optional envelope failure');}};window.AetherContent={register(){throw Error('Optional register failure');}};});
   await page.route('**/checkLogin.php',route=>route.fulfill({contentType:'application/json',body:JSON.stringify(hooks?{aetherPayload:user,aetherContentTranslations:[]}:user)}));
   await page.goto(url);await page.getByText('Welkom Test',{exact:true}).waitFor();
   assert.equal(page.url(),url);assert.equal(await page.locator('#aether-login-error').count(),0);
   assert.equal(await page.evaluate(()=>window.AETHER_CSRF_TOKEN),'test');
   assert.equal(await page.evaluate(async()=> (await aetherGetCurrentUser()).id),1);
   await page.close();
  }
  const retry=await browser.newPage();let calls=0;
  await retry.route('**/checkLogin.php',route=>route.fulfill({status:++calls===1?500:200,contentType:'application/json',body:JSON.stringify(calls===1?{error:'Server error.'}:user)}));
  await retry.goto(url);await retry.locator('#aether-login-error button').click();await retry.getByText('Welkom Test',{exact:true}).waitFor();assert.equal(retry.url(),url);assert.equal(await retry.locator('#aether-login-error').count(),0);await retry.close();
  for(const status of [200,401]){
   const page=await browser.newPage();await page.route('**/checkLogin.php',route=>route.fulfill({status,contentType:'application/json',body:JSON.stringify({status:'redirect'})}));
   await page.goto(url);await page.waitForURL('**/index.php');assert.equal(new URL(page.url()).pathname,'/index.php');await page.close();
  }
  // Page initializers must not replace a server failure with a route change.
  for(const [file,entry] of [['adminFunctions.js','initAdminPage'],['companyFunctions.js','initCompanyPage']]){
   const observed=[];const ctx={console:{error(){}},URLSearchParams,window:{addEventListener(){},location:{href:'unchanged',search:''}},aetherGetCurrentUser:async()=>{throw Object.assign(Error('Unavailable'),{status:500});},aetherHandlePageLoadError:error=>observed.push(error.status)};
   vm.createContext(ctx);vm.runInContext(fs.readFileSync(path.join(root,'js',file),'utf8'),ctx);await ctx[entry]();assert.deepEqual(observed,[500]);assert.equal(ctx.window.location.href,'unchanged');
  }
  console.log('PASS: server/network/JSON failures stay recoverable; retry succeeds; real session denial redirects; optional translation failures preserve login and CSRF; admin/company failures preserve routes.');
 }finally{await browser.close();server.close();}
})().catch(error=>{console.error(error);process.exitCode=1;server.close();});
