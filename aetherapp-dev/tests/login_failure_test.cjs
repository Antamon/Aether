const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http');
const {chromium}=require(process.env.RESEARCH_PLAYWRIGHT||'../tmp/feedback-browser/package');
const script=fs.readFileSync(path.join(__dirname,'../js/mainFunctions.js'));
const html='<!doctype html><meta charset="utf-8"><body><div id="navbarName"></div><script src="js/mainFunctions.js"></script></body>';
const server=http.createServer((req,res)=>{if(req.url.endsWith('/js/mainFunctions.js')){res.setHeader('Content-Type','text/javascript');res.end(script);}else if(req.url==='/index.php'){res.end('<p>Oneiros</p>');}else{res.setHeader('Content-Type','text/html');res.end(html);}});
(async()=>{
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    const browser=await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
    try{
        const url='http://127.0.0.1:'+server.address().port+'/aether/aetherapp/index.html';
        for(const failure of ['500','network','invalid-json','invalid-payload']){
            const page=await browser.newPage();
            await page.route('**/checkLogin.php',route=>{
                if(failure==='network')return route.abort();
                return route.fulfill({status:failure==='500'?500:200,contentType:'application/json',body:failure==='invalid-json'?'<html>Serverfout</html>':JSON.stringify(failure==='invalid-payload'?{error:'Temporary failure'}:{error:'Server error.'})});
            });
            await page.goto(url);await page.waitForURL('**/index.php');
            assert.equal(new URL(page.url()).pathname,'/index.php',failure+' returns to Oneiros');
            assert.equal(await page.locator('#aether-login-error').count(),0);
            await page.close();
        }
        const authenticated=await browser.newPage();
        await authenticated.route('**/checkLogin.php',route=>route.fulfill({contentType:'application/json',body:JSON.stringify({status:'ok',user:{id:1,displayName:'Test',role:'participant'},csrfToken:'test'})}));
        await authenticated.goto(url);await authenticated.getByText('Welkom Test',{exact:true}).waitFor();assert.equal(authenticated.url(),url);await authenticated.close();
        for(const status of [200,401]){
            const page=await browser.newPage();await page.route('**/checkLogin.php',route=>route.fulfill({status,contentType:'application/json',body:JSON.stringify({status:'redirect'})}));
            await page.goto(url);await page.waitForURL('**/index.php');assert.equal(new URL(page.url()).pathname,'/index.php');await page.close();
        }
        console.log('PASS: failed or unauthenticated login checks return to Oneiros; authenticated users remain in the app.');
    }finally{await browser.close();server.close();}
})().catch(error=>{console.error(error);process.exitCode=1;server.close();});
