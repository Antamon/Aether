const fs=require('fs'),path=require('path'),http=require('http'),assert=require('assert/strict');
const {chromium}=require(process.env.RESEARCH_PLAYWRIGHT||'../tmp/feedback-browser/package');
const root=process.env.AETHER_TEST_ROOT||path.resolve(__dirname,'..'),assets=process.env.AETHER_TEST_ASSETS||root;
const bootstrap=process.env.AETHER_TEST_BOOTSTRAP||path.join(root,'tmp/feedback-browser/bootstrap.min.css');
const pages=['index.html','admin.html','research.html','companies.html','eventParticipation.html'];
function fixture(name,url){
 const params=new URL(url,'http://fixture').searchParams,role=params.get('role')||'director',linked=params.has('linked');
 return fs.readFileSync(path.join(root,name),'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'')
 .replace(/<head>[\s\S]*?<\/head>/i,'<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="stylesheet" href="bootstrap.css"><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/page-nav.css"><link rel="stylesheet" href="css/research.css"><link rel="stylesheet" href="css/i18n.css"><link rel="stylesheet" href="css/modal.css"><script src="js/interface-copy.js"></script><script src="js/i18n.js"></script></head>')
 .replace('</body>',`<script>
 window.AetherI18n.initialize({id:1,displayName:'Jan Mertens',interfaceLanguage:'nl'});
 const main=document.getElementById('sheetBody')||document.querySelector('main.aether-page');main.classList.remove('d-none');
 const row='<div class="row g-4"><div class="col-lg-6"><section class="card aether-panel mb-4"><h2>Gegevens</h2><p>Leesbare testinhoud op een telefoon.</p><button id="underlyingAction">Onderliggende actie</button></section></div><div class="col-lg-6"><section class="aether-panel mb-4"><h2>Vaardigheden</h2><p>Tweede paneel.</p></section></div></div>';
 main.innerHTML=${JSON.stringify(name==='admin.html')}?'<section id="adminSkillsSection">'+row+'</section>':row;
 const tab=document.createElement('div');tab.id='diaryTab';tab.className='p-3';tab.innerHTML='<section class="aether-panel"><h2>Dagboek</h2><p>Ook tabbladen lopen tot de schermranden.</p></section>';main.append(tab);main.style.minHeight='1600px';
 const nav=document.getElementById('pageNav');if(nav){nav.classList.remove('d-none');nav.innerHTML='<div class="aether-page-nav-body">Personage</div>';}
 window.aetherGetCurrentUser=async()=>({id:1,role:${JSON.stringify(role)}});window.userHasPrivilegedRole=user=>['administrator','director'].includes(user.role);
 window.apiFetchJson=async url=>url.includes('getMyCompanies')?{companies:${linked?'[{id:1,companyName:"Verbonden bedrijf"}]':'[]'}}:[];
 window.renderCompanySidebarCards=(items,host,choose)=>{host.replaceChildren();for(const item of items){const b=document.createElement('button');b.className='company-sidebar-card';b.textContent=item.companyName;b.addEventListener('click',()=>choose(item));host.append(b);}};
 </script><script src="js/aetherSidebars.js"></script></body>`);
}
const server=http.createServer((req,res)=>{
 const name=req.url.split('?')[0].slice(1);if(pages.includes(name)){res.setHeader('Content-Type','text/html; charset=utf-8');return res.end(fixture(name,req.url));}
 let file=name==='bootstrap.css'?bootstrap:path.resolve(root,name);if(name!=='bootstrap.css'&&!file.startsWith(path.resolve(root)+path.sep)){res.writeHead(403);return res.end();}
 if(!fs.existsSync(file)&&name!=='bootstrap.css')file=path.resolve(assets,name);
 try{res.setHeader('Content-Type',name.endsWith('.js')?'text/javascript; charset=utf-8':name.endsWith('.css')?'text/css':name.endsWith('.svg')?'image/svg+xml':name.endsWith('.jpg')?'image/jpeg':name.endsWith('.png')?'image/png':'application/octet-stream');res.end(fs.readFileSync(file));}catch{res.writeHead(404);res.end();}
});
(async()=>{await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const base='http://127.0.0.1:'+server.address().port+'/';const browser=await chromium.launch({executablePath:'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
 try{
  const context=await browser.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true});const errors=[];const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  for(const name of pages){await page.goto(base+name+'?company=1&event=1');await page.waitForFunction(()=>document.querySelectorAll('.offcanvas-tab:not(.d-none)').length===4);
   for(const width of [320,390,600,767,820,991]){await page.setViewportSize({width,height:844});await page.waitForTimeout(30);
    const bounds=await page.evaluate(()=>({width:innerWidth,panels:[...document.querySelectorAll('.aether-page .aether-panel')].filter(n=>n.getClientRects().length).map(n=>({left:n.getBoundingClientRect().left,width:n.getBoundingClientRect().width})),overflow:document.documentElement.scrollWidth>innerWidth+1}));
    for(const rect of bounds.panels){assert(Math.abs(rect.left)<1,name+' panel left at '+width);assert(Math.abs(rect.width-bounds.width)<1,name+' panel right at '+width);}assert(!bounds.overflow,name+' horizontal overflow at '+width);
    const dock=await page.locator('.offcanvas-tab:not(.d-none)').evaluateAll(nodes=>({left:nodes[0].getBoundingClientRect().left,right:nodes.at(-1).getBoundingClientRect().right}));
    assert(Math.abs(dock.right-dock.left-width*.95)<1,name+' dock uses 95% at '+width);
   }
  }
  await page.setViewportSize({width:390,height:844});await page.goto(base+'index.html');await page.waitForFunction(()=>document.querySelectorAll('.offcanvas-tab:not(.d-none)').length===4);
  await page.evaluate(()=>{const list=document.getElementById('characterNames');for(let i=0;i<12;i++){const a=document.createElement('a');a.href='#personage-'+i;a.className='company-sidebar-card character-sidebar-card';a.innerHTML='<div class="company-sidebar-image-frame"><img class="company-sidebar-image" src="img/actionEconomie.png" alt=""></div><span class="company-sidebar-name">Testpersonage '+(i+1)+'</span>';list.append(a);}});
  assert.equal(await page.locator('.navbar-toggler, .navbar-collapse, nav.navbar').count(),0);
  const header=page.locator('.aether-top-bar'),greeting=page.locator('#navbarName');
  assert(await header.evaluate(n=>getComputedStyle(n,'::before').backgroundImage.includes('topBarMobile.png')));
  assert((await greeting.innerText()).includes('Welkom Jan Mertens'));
  const headerBox=await header.boundingBox(),greetingBox=await greeting.boundingBox();
  assert(greetingBox.x<15&&greetingBox.y>headerBox.height/2&&greetingBox.y+greetingBox.height<headerBox.height,'Identity sits at the lower left of the mobile artwork');
  await page.locator('.aether-language-picker summary').click();assert(await page.getByRole('button',{name:'English',exact:true}).isVisible());
  const languageBox=await page.locator('.aether-language-menu').boundingBox();assert(languageBox.x>=0&&languageBox.x+languageBox.width<=390,'Language dropdown stays in the viewport');
  await page.keyboard.press('Escape');
  const buttons=page.locator('.offcanvas-tab:not(.d-none) .tab-button');
  const geometry=await buttons.evaluateAll(nodes=>nodes.map(node=>{const r=node.getBoundingClientRect(),s=getComputedStyle(node);return {x:r.x,y:r.y,w:r.width,h:r.height,bottom:r.bottom,radius:s.borderRadius,shadow:s.boxShadow,filter:s.filter,leather:s.backgroundImage.includes('leather.jpg'),gold:getComputedStyle(node.querySelector('.aether-sidebar-tab-gold')).display};}));
  for(let i=0;i<geometry.length;i++){const g=geometry[i];assert(Math.abs(g.w-g.h)<1);assert(Math.abs(g.bottom-844)<1);assert.equal(g.radius,'0px');assert.equal(g.shadow,'none');assert.equal(g.filter,'none');assert(g.leather);assert.equal(g.gold,'none');if(i)assert(Math.abs(g.x-geometry[i-1].x-geometry[i-1].w-4)<1);}
  assert(Math.abs(geometry.at(-1).x+geometry.at(-1).w-geometry[0].x-390*.95)<1,'Four tabs occupy 95% of available width');
  const characterButton=page.locator('#offcanvasTabToggle .tab-button');await page.screenshot({path:path.join(process.env.TEMP,'aether-auth-diagnostics/mobile-closed.png')});await characterButton.click();await page.waitForTimeout(80);
  const moving=await characterButton.boundingBox();assert(moving.y>0&&moving.y<geometry[0].y,'Active tab travels upward with the panel');await page.waitForTimeout(260);
  const open=await page.evaluate(()=>{const panel=document.querySelector('.aether-sidebar-panel.is-open'),r=panel.getBoundingClientRect(),tab=document.querySelector('.offcanvas-tab.is-active').getBoundingClientRect();return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,tabTop:tab.top,tabHeight:tab.height,modal:document.getElementById('aetherSidebarDeck').getAttribute('aria-modal'),locked:getComputedStyle(document.body).overflow,inert:document.getElementById('sheetBody').inert};});
  assert(Math.abs(open.left)<1&&Math.abs(open.right-390)<1&&Math.abs(open.bottom-844)<1);assert(Math.abs(open.tabTop)<1&&Math.abs(open.top-open.tabHeight)<1);assert.equal(await page.locator('#aetherSidebarDeck').evaluate(n=>getComputedStyle(n).backgroundImage),'none','No full-width leather backdrop behind the raised tab');
  assert.equal(open.modal,'true');assert.equal(open.locked,'hidden');assert(open.inert);
  await page.evaluate(()=>{Object.defineProperty(window.visualViewport,'height',{value:420,configurable:true});window.visualViewport.dispatchEvent(new Event('resize'));});
  assert(await page.locator('.aether-sidebar-panel.is-open').evaluate(n=>Math.abs(n.getBoundingClientRect().bottom-420)<1),'An open menu fits above the on-screen keyboard viewport');
  await page.evaluate(()=>{delete window.visualViewport.height;window.visualViewport.dispatchEvent(new Event('resize'));});
  for(let i=0;i<16;i++){await page.keyboard.press('Tab');assert(await page.evaluate(()=>Boolean(document.activeElement.closest('#aetherSidebarDeck'))),'Keyboard stays in the open menu');}
  await page.locator('.aether-sidebar-panel.is-open .aether-sidebar-scroll').evaluate(n=>{n.scrollTop=n.scrollHeight;});assert(await page.locator('.aether-sidebar-panel.is-open .aether-sidebar-scroll').evaluate(n=>n.scrollTop>0));
  await page.locator('.aether-sidebar-panel.is-open .aether-sidebar-scroll').evaluate(n=>n.scrollTop=0);await page.screenshot({path:path.join(process.env.TEMP,'aether-auth-diagnostics/mobile-open.png')});
  await page.keyboard.press('Escape');await page.waitForTimeout(340);assert.equal(await characterButton.getAttribute('aria-expanded'),'false');assert(!await page.locator('#sheetBody').evaluate(n=>n.inert));assert.equal(await page.locator('#aetherSidebarDeck').getAttribute('aria-modal'),null);
  await characterButton.click();await page.waitForTimeout(300);await page.setViewportSize({width:844,height:390});await page.waitForTimeout(50);assert(await page.locator('.aether-sidebar-panel.is-open').evaluate(n=>Math.abs(n.getBoundingClientRect().bottom-innerHeight)<1));
  await page.setViewportSize({width:1200,height:900});await page.waitForTimeout(50);assert(!await page.locator('#sheetBody').evaluate(n=>n.inert));assert.equal(await page.locator('#aetherSidebarDeck').getAttribute('aria-modal'),null);assert(await header.evaluate(n=>getComputedStyle(n,'::before').backgroundImage.endsWith('/img/topBar.png")')),'Desktop uses its original artwork');
  await page.keyboard.press('Escape');await page.waitForTimeout(330);
  const desktop=await characterButton.evaluate(n=>({w:n.getBoundingClientRect().width,h:n.getBoundingClientRect().height,gold:getComputedStyle(n.querySelector('.aether-sidebar-tab-gold')).display,filter:getComputedStyle(n).filter}));assert.equal(desktop.w,124);assert.equal(desktop.h,175);assert.notEqual(desktop.gold,'none');assert.notEqual(desktop.filter,'none');
  await page.setViewportSize({width:390,height:844});
  for(const [query,count]of [['?role=participant',2],['?role=participant&linked=1',3],['?role=administrator',4]]){await page.goto(base+'index.html'+query);await page.waitForFunction(expected=>document.querySelectorAll('.offcanvas-tab:not(.d-none)').length===expected,count);const tabs=await page.locator('.offcanvas-tab:not(.d-none)').evaluateAll(nodes=>nodes.map(n=>n.getBoundingClientRect().x));assert(tabs.every(x=>x>=0));if(count<4)assert(await page.locator('#adminSidebarTabToggle').isHidden());}
  const reduce=await browser.newPage({viewport:{width:390,height:844},reducedMotion:'reduce'});await reduce.goto(base+'index.html');assert.equal(await reduce.locator('#aetherSidebarDeck').evaluate(n=>getComputedStyle(n).transitionDuration),'0s');await reduce.close();
  assert.deepEqual(errors,[]);console.log('PASS: edge-to-edge panels on five pages; square leather dock; moving active tab; full-screen scrolling; keyboard/focus/escape; landscape/resize; role-aware compact tabs; desktop appearance; reduced motion.');
 }finally{await browser.close();server.close();}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
