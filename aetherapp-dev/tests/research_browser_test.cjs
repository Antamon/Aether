/* Run against tests/research_browser_router.php with a disposable MariaDB fixture. */
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {chromium}=require(process.env.RESEARCH_PLAYWRIGHT || '../.research-test-runtime/playwright/package');
const base='http://127.0.0.1:8879';
const challengeName='Browser challenge '+Date.now();
(async()=>{
    const browser=await chromium.launch({executablePath:process.env.RESEARCH_EDGE || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true,args:['--disable-gpu']});
    try {
        const context=await browser.newContext({viewport:{width:1440,height:1100}});const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
        await context.route('**/kit.fontawesome.com/**',route=>route.fulfill({contentType:'text/javascript',body:"const fa=document.createElement('link');fa.rel='stylesheet';fa.href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css';document.head.append(fa);"}));
        await context.route('**/bootstrap@5.3.3/dist/css/bootstrap.min.css',route=>route.fulfill({contentType:'text/css',path:'.research-test-runtime/bootstrap.min.css'}));
        await context.route('**/font-awesome/6.7.2/css/all.min.css',route=>route.fulfill({contentType:'text/css',path:'.research-test-runtime/fontawesome.css'}));
        await context.route('**/fa-solid-900.woff2',route=>route.fulfill({contentType:'font/woff2',path:'.research-test-runtime/fa-solid-900.woff2'}));
        await context.route('**/fonts.googleapis.com/**',route=>route.fulfill({contentType:'text/css',path:'.research-test-runtime/amarante.css'}));
        await context.route('**/fonts.gstatic.com/**',route=>route.fulfill({contentType:'font/ttf',path:'.research-test-runtime/amarante.ttf'}));
        await page.goto(base+'/test-login?id=99');await page.goto(base+'/research.html');
        await page.getByRole('button',{name:'Nieuwe challenge',exact:true}).waitFor();
        await page.getByRole('button',{name:'Nieuwe challenge',exact:true}).click();
        let dialog=page.locator('dialog[open]');await dialog.getByLabel('Titel',{exact:true}).fill(challengeName);
        await dialog.locator('[contenteditable]').fill('Bestaande Aether-componenten');await dialog.getByLabel('Events (meerdere mogelijk)').selectOption(['1','2']);
        await dialog.getByRole('button',{name:'Opslaan',exact:true}).click();await dialog.waitFor({state:'detached'});
        await page.locator('.research-list summary').filter({hasText:challengeName}).waitFor();await page.getByRole('button',{name:'Discovery toevoegen',exact:true}).click();
        dialog=page.locator('dialog[open]');await dialog.getByLabel('Titel',{exact:true}).fill('De verzegelde brief');await dialog.getByLabel('Vaardigheid',{exact:true}).selectOption('6');
        assert.equal(await dialog.getByText('Bevestiging nodig',{exact:false}).count(),0,'Initial discovery modal stays simple');
        assert(await dialog.getByLabel('Actief',{exact:true}).isChecked(),'Discovery can be active immediately');
        await dialog.getByRole('button',{name:'Opslaan',exact:true}).click();await dialog.waitFor({state:'detached'});
        await page.getByRole('button',{name:'De verzegelde brief bekijken',exact:true}).click();
        const defaultStep=page.locator('.research-selection table tbody tr').first();
        await defaultStep.getByText('No additional information',{exact:true}).waitFor();
        await defaultStep.getByRole('button',{name:'Feedback bewerken',exact:true}).click();dialog=page.locator('dialog[open]');await dialog.locator('[contenteditable]').fill('Eerste zichtbare feedback');await dialog.getByRole('button',{name:'Opslaan',exact:true}).click();await dialog.waitFor({state:'detached'});
        const api=async(op,data={})=>page.evaluate(async({op,data})=>AetherResearch.read(op,data),{op,data});
        const write=async(op,data)=>page.evaluate(async({op,data})=>AetherResearch.write(op,data),{op,data});
        let challenges=await api('challenges');const challenge=challenges.challenges.find(c=>c.title===challengeName);let detail=await api('challenge',{idChallenge:challenge.id});let discovery=detail.discoveries[0];
        for(const r of discovery.routes)if(Number(r.level)!==0)await write('step_save',{idRoute:Number(r.id),content:`Niveau ${r.level}`,confirmationRequired:false,conditions:[]});
        const route=discovery.routes.find(r=>Number(r.level)===0);
        const second=await write('step_save',{idRoute:Number(route.id),content:'Druk op de rode knop.',confirmationRequired:true,conditions:[]});
        const third=await write('step_save',{idRoute:Number(route.id),content:'Verborgen geheime informatie',confirmationRequired:false,conditions:[{type:'duration',seconds:60,scope:'shared',discoveryId:Number(discovery.id),stepId:second.id,action:'confirmed',showCountdown:false}]});
        detail=await api('challenge',{idChallenge:challenge.id});discovery=detail.discoveries[0];
        await write('discovery_save',{id:Number(discovery.id),revision:Number(discovery.revision),title:discovery.title,description:discovery.description,idSkill:6,bonusIds:[],active:true});
        // Upload a real PNG, and verify deduplication + authenticated range handling.
        const png=Buffer.from(await page.evaluate(()=>{const c=document.createElement('canvas');c.width=c.height=20;const g=c.getContext('2d');g.fillStyle='red';g.fillRect(0,0,20,20);return c.toDataURL('image/png').split(',')[1];}),'base64');
        const csrf=await page.evaluate(()=>window.AETHER_CSRF_TOKEN);
        const upload=()=>context.request.post(base+'/api/research/upload.php',{headers:{'X-CSRF-Token':csrf},multipart:{idStep:String(third.id),file:{name:'secret.png',mimeType:'image/png',buffer:png}}});
        let response=await upload();assert.equal(response.status(),200,await response.text());response=await upload();assert.equal(response.status(),200);
        detail=await api('challenge',{idChallenge:challenge.id});const hiddenMedia=detail.discoveries[0].routes.find(r=>Number(r.level)===0).steps.find(s=>Number(s.id)===third.id).media;
        assert.equal(hiddenMedia.length,1,'Upload is idempotent by content hash');const mediaId=hiddenMedia[0].id;
        response=await context.request.get(base+`/api/research/media.php?id=${mediaId}`,{headers:{Range:'bytes=0-7'}});assert.equal(response.status(),206);assert.equal((await response.body()).length,8);
        response=await context.request.post(base+'/api/research/http.php',{data:{op:'challenge_save',title:'CSRF attack',eventIds:[1]}});assert.equal(response.status(),403,'Missing CSRF rejected');
        // QR print preview uses only the random token, and decodes with the bundled reader.
        await page.reload();await page.getByRole('button',{name:challengeName,exact:true}).click();await page.getByRole('button',{name:'De verzegelde brief bekijken',exact:true}).click();const qrDownload=page.waitForEvent('download');await page.getByRole('button',{name:'QR-code',exact:true}).click();assert((await qrDownload).suggestedFilename().endsWith('-qr.pdf'));
        // The challenge button now downloads all QR cards as a PDF.
        await page.getByRole('button',{name:'Voortgang',exact:true}).click();await page.locator('.research-progress').waitFor();await page.screenshot({path:'.research-test-runtime/research-progress-desktop.png',fullPage:true});
        // SF uses the same API as the UI, with only this character's linked events.
        await page.goto(base+'/test-login?id=1');await page.goto(base+'/test-character');
        await page.addStyleTag({content:fs.readFileSync('.research-test-runtime/fontawesome.css','utf8').replaceAll('../webfonts/','https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/webfonts/')});
        await page.getByRole('button',{name:'Scan QR-code',exact:true}).waitFor();await page.getByRole('button',{name:'Scan QR-code',exact:true}).click();dialog=page.locator('dialog[open]');
        await dialog.getByLabel('Of plak een onderzoekscode').fill('aether-research:'+discovery.qrToken);await dialog.getByRole('button',{name:'Code openen',exact:true}).click();await dialog.waitFor({state:'detached'});
        await page.getByText('Eerste zichtbare feedback',{exact:true}).waitFor();await page.getByText('Druk op de rode knop.',{exact:true}).waitFor();
        const confirm=page.getByRole('button',{name:'Afgerond',exact:true});await confirm.waitFor();await page.waitForFunction(()=>[...document.querySelectorAll('button')].some(b=>b.textContent.includes('Opdracht uitgevoerd')&&!b.disabled));
        assert.equal(await page.getByText('Verborgen geheime informatie',{exact:true}).count(),0,'Hidden future step not in DOM');
        response=await context.request.get(base+`/api/research/media.php?id=${mediaId}&idEvent=1&idCharacter=1`);assert.equal(response.status(),404,'Hidden file inaccessible');
        response=await context.request.get(base+'/api/research/http.php?op=catalog');assert.equal(response.status(),403,'Participant cannot manage');
        response=await context.request.get(base+'/api/research/http.php?op=found&idEvent=1&idCharacter=2');assert.equal(response.status(),403,'Another character inaccessible');
        await confirm.click();await page.locator('.research-clock').waitFor();await page.locator('.research-detail').getByText('Wacht op tijd',{exact:true}).waitFor();assert.equal(await page.locator('.research-countdown').count(),0,'Countdown remains hidden');assert.equal(await page.locator('.research-clock').count(),1,'Clock icon remains visible');
        await page.setViewportSize({width:390,height:844});await page.screenshot({path:'.research-test-runtime/research-sf-mobile.png',fullPage:true});
        assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'No horizontal overflow on phone');
        await page.getByRole('button',{name:'Scan QR-code',exact:true}).click();dialog=page.locator('dialog[open]');await dialog.getByLabel('Of plak een onderzoekscode').fill('bad-code');await dialog.getByRole('button',{name:'Code openen',exact:true}).click();await dialog.getByRole('alert').waitFor();assert((await dialog.getByRole('alert').textContent()).includes('geldige'),'Understandable invalid code message');await dialog.locator('.aether-modal-close').click();
        await page.locator('[data-research-event] select').selectOption('2');
        assert.equal(await page.locator('.research-detail').textContent(),'','Changing event clears the previously opened feedback immediately');
        await page.getByRole('button',{name:'Scan QR-code',exact:true}).click();dialog=page.locator('dialog[open]');
        await page.evaluate(()=>Object.defineProperty(navigator.mediaDevices,'getUserMedia',{configurable:true,value:async()=>{throw new Error('denied');}}));
        await dialog.getByRole('button',{name:'Camera starten',exact:true}).click();await dialog.getByRole('alert').waitFor();assert((await dialog.getByRole('alert').textContent()).includes('cameratoestemming'),'Camera denial has a usable fallback');await dialog.locator('.aether-modal-close').click();
        await page.setViewportSize({width:1440,height:1100});await page.goto(base+'/test-login?id=99');await page.goto(base+'/research.html');
        await page.getByRole('button',{name:challengeName,exact:true}).click();await page.locator('.research-list summary').filter({hasText:challengeName}).waitFor();await page.getByRole('button',{name:'Voortgang',exact:true}).click();
        await page.getByRole('button',{name:'Voortgang van dit personage',exact:true}).click();dialog=page.locator('dialog[open]');
        await dialog.getByLabel('Nieuw eindtijdstip (Europe/Brussels)',{exact:true}).fill('2080-06-09T12:00');
        await dialog.getByLabel('Reden',{exact:true}).fill('Testcorrectie klok');await dialog.getByRole('button',{name:'Klok aanpassen',exact:true}).click();await dialog.waitFor({state:'detached'});
        await page.getByRole('button',{name:'Voortgang van dit personage',exact:true}).click();dialog=page.locator('dialog[open]');
        await dialog.getByLabel('Reden voor handmatige afronding',{exact:true}).fill('Opdracht tijdens het spel gecontroleerd');await dialog.getByRole('button',{name:'Afronden als spelleider',exact:true}).click();await dialog.waitFor({state:'detached'});
        await page.locator('.research-progress').getByText('Afgerond',{exact:true}).waitFor();await page.screenshot({path:'.research-test-runtime/research-progress-desktop.png',fullPage:true});
        assert.deepEqual(errors,[],'No uncaught browser exceptions');console.log('PASS: actual PHP HTTP + browser: progressive AD create/edit, upload deduplication/range, CSRF, QR dialog, progress, SF scan/show/confirm/timer, hidden media, role and ownership denial, mobile overflow, invalid-code message.');
        await context.close();
    }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
