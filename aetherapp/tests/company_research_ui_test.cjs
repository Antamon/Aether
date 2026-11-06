const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Node {
    constructor(tag) { this.tag=tag; this.children=[]; this.listeners={}; this.attributes={}; this.isConnected=true; }
    append(...nodes) { this.children.push(...nodes); }
    appendChild(node) { this.append(node); return node; }
    replaceChildren(...nodes) { this.children=nodes; }
    setAttribute(key,value) { this.attributes[key]=String(value); }
    addEventListener(type,callback) { this.listeners[type]=callback; }
}
const all = node => [node,...node.children.flatMap(all)];
async function check(manager) {
    const host=new Node('div'), writes=[];
    const data={company:{companyName:'Abeir',logoUrl:'logo.png',description:'Description',companyValue:1000,issuedShareCount:100},
        eventTitle:'Holiday',canEdit:manager,researchAvailable:true,revision:'a'.repeat(64),
        quarters:[{year:1925,quarter:1,companyValue:1000},{year:1925,quarter:2,companyValue:1200}],
        events:[{id:1,year:1924,quarter:1,description:'Known',publicity:1,isKnown:true},
            ...(manager?[{id:2,year:1924,quarter:2,description:'Unknown',publicity:5,isKnown:false}]:[])]};
    const context={Intl,Map,Set,document:{createElement:tag=>new Node(tag),createElementNS:(ns,tag)=>new Node(tag),
        createTextNode:text=>Object.assign(new Node('text'),{textContent:text})},
        aetherShareUi:{money:value=>`Fr ${value}`,errorMessage:error=>error.message},
        apiFetchJson:async()=>data,
        apiFetchIdempotentJson:async(url,options)=>{writes.push({url,body:options.body}); data.events.forEach(event=>{event.isKnown=options.body.knownEventIds.includes(event.id);});}};
    vm.createContext(context);
    vm.runInContext(fs.readFileSync('js/actionButtons.js','utf8'),context);
    vm.runInContext(fs.readFileSync('js/companyResearch.js','utf8')+'\nthis.research=aetherCompanyResearch;',context);
    const back=new Node('button'); back.textContent='Alle bedrijven tonen';
    await context.research.mount(host,{characterId:14,companyId:5,eventId:19,backButton:back});
    const button=label=>all(host).find(node=>node.tag==='button'&&node.children.some(child=>child.textContent===label));
    assert(all(host).some(node=>node.tag==='svg'),'Company value curve is present');
    assert(!all(host).some(node=>node.className?.split(' ').includes('aether-panel')), 'Research uses the enclosing data card without extra Aether panels');
    const header=all(host).find(node=>node.className==='company-research-heading');
    assert(all(header).includes(back),'The company header contains the back button');
    const facts=all(host).find(node=>node.className?.includes('company-research-facts'));
    assert.equal(facts.children.length,6,'Company details use six equal grid cells');
    assert.equal(all(host).some(node=>String(node.textContent).startsWith('Zichtbaarheid ')),manager,'Only managers see event visibility');
    const sliders=all(host).filter(node=>node.tag==='input'&&node.type==='range');
    assert.equal(sliders.length,2);
    assert.equal(sliders[0].min,'25'); assert.equal(sliders[0].max,'300');
    assert.equal(sliders[1].min,'100'); assert.equal(sliders[1].max,'400');
    const pointX=()=>all(host).filter(node=>node.tag==='circle')[1].attributes.cx;
    const beforeX=pointX(); sliders[0].value='300'; sliders[0].listeners.input();
    assert.notEqual(pointX(),beforeX,'Horizontal zoom changes quarter spacing');
    sliders[1].value='200'; sliders[1].listeners.input();
    assert.equal(all(host).find(node=>node.tag==='svg').attributes.height,'1380','Vertical zoom expands the value axis');
    assert(!all(host).some(node=>node.textContent===' – Unknown'),'Display shows only known descriptions');
    assert.equal(Boolean(button('Edit')),manager);
    if(manager){
        button('Edit').listeners.click();
        const checks=all(host).filter(node=>node.tag==='input'&&node.type==='checkbox');
        assert.equal(checks.length,2,'Manager can choose from all eligible events');
        assert.equal(all(host).filter(node=>node.className==='data-card company-research-event-group mb-3').length,2,'Each event has its own data card');
        checks[1].checked=true; checks[1].listeners.change();
        assert(context.research.hasDraft());
        await button('Save').listeners.click();
        assert.equal(writes[0].url,'api/shares/saveCompanyResearch.php');
        assert.deepEqual(Array.from(writes[0].body.knownEventIds),[1,2]);
        assert(!context.research.hasDraft());
    }
}
(async()=>{await check(false);await check(true);console.log('Company research chart, known-only display, manager staging and save passed.');})().catch(error=>{console.error(error);process.exitCode=1;});
