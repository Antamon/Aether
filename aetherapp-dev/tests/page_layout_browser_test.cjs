const fs = require('node:fs'), path = require('node:path'), http = require('node:http'), assert = require('node:assert/strict');
const { chromium } = require('../tmp/feedback-browser/package');
const root = path.resolve(__dirname, '..');
const pages = ['index.html', 'admin.html', 'research.html'];
function fixture(name) {
    return fs.readFileSync(path.join(root, name), 'utf8')
        .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '')
        .replace(/<head>[\s\S]*?<\/head>/i, `<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="tmp/feedback-browser/bootstrap.min.css"><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/page-nav.css"><link rel="stylesheet" href="css/research.css"></head>`)
        .replace('</body>', `<script>
            window.aetherGetCurrentUser=async()=>({role:'administrator'});
            window.userHasPrivilegedRole=()=>true;window.apiFetchJson=async()=>[];
            const sheet=document.getElementById('sheetBody');
            if(sheet){sheet.classList.remove('d-none');sheet.innerHTML='<div class="row g-4"><div class="col-lg-6"><section class="card aether-panel">Personagegegevens</section></div><div class="col-lg-6"><section class="card aether-panel">Vaardigheden</section></div></div>';const nav=document.getElementById('pageNav');nav.classList.remove('d-none');nav.innerHTML='<div class="aether-page-nav-body">Personage</div>';}
        </script><script src="js/aetherSidebars.js"></script></body>`);
}
const server = http.createServer((req, res) => {
    const name = req.url.split('?')[0].slice(1);
    if (pages.includes(name)) { res.setHeader('Content-Type', 'text/html; charset=utf-8'); return res.end(fixture(name)); }
    const file = path.resolve(root, name);
    if (!file.startsWith(root + path.sep)) { res.writeHead(403); return res.end(); }
    try {
        res.setHeader('Content-Type', file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : 'application/octet-stream');
        res.end(fs.readFileSync(file));
    } catch { res.writeHead(404); res.end(); }
});
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    try {
        const tabs = await Promise.all(pages.map(async name => {
            const tab = await browser.newPage();
            tab.on('pageerror', error => { throw error; });
            await tab.goto(`http://127.0.0.1:${server.address().port}/${name}`);
            return tab;
        }));
        for (const width of [1920, 1500, 1200, 900, 600, 390]) {
            const layouts = [];
            for (const tab of tabs) {
                await tab.setViewportSize({ width, height: 1000 });
                await tab.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
                await tab.waitForFunction(() => document.getElementById('pageNav').style.getPropertyValue('--aether-page-nav-container-width') === `${(document.getElementById('sheetBody') || document.querySelector('main.aether-page')).getBoundingClientRect().width}px`);
                layouts.push(await tab.evaluate(() => {
                    const main=document.getElementById('sheetBody') || document.querySelector('main.aether-page');
                    const rect=main.getBoundingClientRect(), nav=document.querySelector('#pageNav > .aether-page-nav-body').getBoundingClientRect();
                    const panel=main.querySelector('.aether-panel').getBoundingClientRect();
                    const reference=document.createElement('div');reference.className='container';document.body.append(reference);
                    const standard=reference.getBoundingClientRect().width;reference.remove();
                    return { left:rect.left, width:rect.width, navLeft:nav.left, navWidth:nav.width, panelLeft:panel.left, standard, overflow:document.documentElement.scrollWidth>innerWidth+1, overflowElements:[...document.querySelectorAll('body *')].filter(n=>n.getBoundingClientRect().right>innerWidth+1).map(n=>n.id || n.className).slice(0,12) };
                }));
            }
            const reference=layouts[0];
            layouts.forEach((layout, index) => {
                const context=`${pages[index]} at ${width}px`;
                assert(Math.abs(layout.width-layout.standard)<1, `${context}: standard container width`);
                for(const key of ['left','width','navLeft','navWidth']) assert(Math.abs(layout[key]-reference[key])<1, `${context}: ${key} matches character page`);
                assert(Math.abs(layout.panelLeft-layout.navLeft)<1, `${context}: nav aligns with panels`);
                assert(!layout.overflow, `${context}: no horizontal overflow: ${JSON.stringify(layout.overflowElements)}`);
            });
        }
        // The observer also corrects headers when the character container becomes visible after loading.
        await tabs[0].evaluate(() => document.getElementById('sheetBody').classList.add('d-none'));
        await tabs[0].waitForTimeout(60);
        await tabs[0].evaluate(() => document.getElementById('sheetBody').classList.remove('d-none'));
        await tabs[0].waitForFunction(() => document.getElementById('pageNav').style.getPropertyValue('--aether-page-nav-container-width') === '390px');
        console.log('PASS: shared page width, panel/nav alignment, responsive resizing and loading visibility on character, skills/traits and research pages (390–1920px).');
    } finally { await browser.close(); server.close(); }
})().catch(error => { console.error(error); process.exitCode=1; server.close(); });
