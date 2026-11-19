// DOM-independent regression for feedback rendering and the shared variant editor.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
class Node {
    constructor(tag='',text='',cls=''){Object.assign(this,{tag,text,className:cls,children:[],dataset:{},listeners:{},options:[],isConnected:true});}
    append(...nodes){this.children.push(...nodes);}
    replaceChildren(...nodes){this.children=nodes;}
    addEventListener(event,fn){this.listeners[event]=fn;}
    setAttribute(){}
    querySelector(){return null;}
    get textContent(){return this.text+this.children.map(n=>n.textContent||'').join('');}
}
const all=n=>[n,...n.children.flatMap(all)],writes=[];
let dialog;
const R={
    el:(...args)=>new Node(...args),rich:html=>new Node('div',(html||'').replace(/<[^>]*>/g,'')),
    button:(label,icon,click)=>Object.assign(new Node('button',label,icon),{click}),
    group:(...buttons)=>{const n=new Node('group');n.append(...buttons);return n;},
    field:(label,control)=>{const n=new Node('field',label);n.append(control);return n;},
    input:(value,type)=>Object.assign(new Node('input'),{value,type}),
    select:(items,value,empty)=>Object.assign(new Node('select'),{value,options:[...(empty!==undefined?[{id:'',name:empty}]:[]),...items]}),
    multi:(items,ids)=>Object.assign(new Node('select'),{options:items,ids}),ids:n=>n.ids,
    check:(label,checked)=>{const input=Object.assign(new Node('input'),{checked});const box=new Node('check',label);box.append(input);return {box,input};},
    editor:html=>({wrapper:new Node('editor'),getHtml:()=>html||''}),
    dialog:(title,build)=>{dialog=new Node('dialog',title);build(dialog,{close(){}});},
    run:async(host,action)=>action(),write:async(op,data)=>{writes.push({op,data});return {id:1};}
};
const host=new Node(),window={AetherResearch:R},document={getElementById:()=>host,querySelectorAll:()=>[],createTextNode:text=>new Node('text',text)};
let source=fs.readFileSync(path.join(__dirname,'../js/researchAdmin.js'),'utf8');
source=source.slice(0,source.lastIndexOf('    void R.run(host,'))+'    window.test={state,loadChallenge,stepDialog,routeDialog};\n})();';
vm.runInNewContext(source,{window,document});
const {state,loadChallenge,stepDialog,routeDialog}=window.test;
const step={id:10,revision:1,position:1,content:'Feedback',conditions:[],media:[]};
const general={id:1,kind:'general',level:0,steps:[step]};
const variant={id:6,revision:2,kind:'specialisation',level:0,idRequiredSpecialisation:72,priority:0,characterIds:[],steps:[{...step,id:11}]};
const discovery={id:1,title:'Discovery',idSkill:6,routes:[general,variant,...[1,2,3,4].map(level=>({id:level+1,kind:'general',level,steps:[]}))]};
const challenge={id:1,discoveries:[discovery]};
state.catalog={skills:[{id:6,name:'Speuren'}],specialisations:[{id:72,idSkill:6,name:'Sporen'},{id:73,idSkill:7,name:'Auditeren'}],characters:[]};
Object.assign(state,{detailHost:host,challenge:1,discovery:1,navigation:new Map()});
R.read=async()=>challenge;
(async()=>{
    await loadChallenge();
    assert.equal(all(host).filter(n=>n.text==='Niet gedefineerd. Gebruikt de feedback van Ongetraind.').length,4);
    assert.equal(all(host).filter(n=>n.text==='Nog geen feedback'||n.text==='Variantinstellingen').length,0);
    for(const section of all(host).filter(n=>n.dataset.key?.startsWith('level-1-')&&n.dataset.key!=='level-1-0')){
        assert(all(section).some(n=>n.tag==='td'&&n.textContent.includes('Niet gedefineerd.')),'Fallback is in the feedback cell');
    }
    const edits=all(host).filter(n=>n.tag==='button'&&n.className==='data-info-button'&&n.title==='Feedback bewerken');
    assert.equal(edits.length,6);
    await edits[1].click();
    const fields=all(dialog).filter(n=>n.tag==='field');
    assert(fields.some(n=>n.text==='Feedback'));
    const spec=fields.find(n=>n.text.startsWith('Verplichte specialisatie')).children[0];
    assert.deepEqual(spec.options.map(s=>s.id),['',72]);
    assert(!fields.some(n=>n.text.startsWith('Prioriteit')));
    spec.value=72;
    fields.find(n=>n.text==='Variant').children[0].value='character';
    fields.find(n=>n.text==='Personages').children[0].ids=[5];
    all(dialog).find(n=>n.tag==='check'&&n.text==='Vervolgstap toevoegen').children[0].checked=true;
    all(dialog).find(n=>n.tag==='check'&&n.text==='Bevestiging nodig').children[0].checked=true;
    assert(fields.some(n=>n.text==='Variant'));
    // Keep the draft open on simulated save failure to inspect the single mutation payload.
    R.write=async(op,data)=>{writes.push({op,data});throw new Error('stop after payload');};
    await assert.rejects(all(dialog).find(n=>n.tag==='button'&&n.text==='Opslaan').click(),/stop after payload/);
    assert.equal(writes.length,1);assert.equal(writes[0].op,'step_save');
    assert.equal(writes[0].data.variant.id,6);assert.equal(writes[0].data.variant.revision,2);
    assert.equal(writes[0].data.variant.idRequiredSpecialisation,72);
    assert.equal(writes[0].data.followUp,true);
    assert.equal(writes[0].data.variant.priority,undefined);
    stepDialog({...discovery,used:true},variant,variant.steps[0]);
    assert(!all(dialog).some(n=>n.tag==='field'&&n.text==='Variant'),'Used variants keep their settings locked');
    stepDialog(discovery,general,step);
    assert(!all(dialog).some(n=>n.tag==='field'&&n.text==='Variant'),'General feedback has no variant settings');
    routeDialog(discovery,0);
    assert.deepEqual(all(dialog).find(n=>n.tag==='field'&&n.text.startsWith('Verplichte specialisatie')).children[0].options.map(s=>s.id),['',72]);
    general.steps.push({...step,id:12,content:'Extra'});
    general.steps[1].position=2;general.steps[1].media=[{id:9}];
    discovery.routes.find(r=>r.level===2).steps=[{...step,id:13,content:'Gevorderde feedback'}];
    await loadChallenge();
    assert.equal(all(host).filter(n=>n.text==='Niet gedefineerd. Gebruikt de feedback van Gevorderde.').length,2,'Nearest defined lower level wins');
    assert.equal(all(host).filter(n=>n.className==='fa-solid fa-arrow-right').length,1);
    assert.equal(all(host).filter(n=>n.className.includes('research-has-media')).length,1);
    assert.equal(all(host).filter(n=>n.tag==='button'&&n.text==='Volgende feedbackstap').length,0);
    assert.equal(all(host).filter(n=>n.tag==='button'&&n.text==='Feedbackvariant toevoegen').length,0);
    assert.equal(all(host).filter(n=>n.tag==='button'&&n.text==='Variant toevoegen').length,2);
    stepDialog(discovery,general,step);
    const followUp=all(dialog).find(n=>n.tag==='check'&&n.text==='Vervolgstap toevoegen').children[0];
    assert.equal(followUp.checked,true);assert.equal(followUp.disabled,true,'Existing follow-up cannot be duplicated');
    assert(!/['"]fa-pen['"]/.test(fs.readFileSync(path.join(__dirname,'../js/researchAdmin.js'),'utf8')));
    console.log('PASS: fallback cells, combined variant editing without priority, filtered specialisations, follow-up checkbox/arrow, red media state and locked settings.');
})().catch(error=>{console.error(error);process.exitCode=1;});
