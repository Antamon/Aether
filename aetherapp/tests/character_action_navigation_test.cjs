const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
class Element {
    constructor(tag){this.tag=tag;this.children=[];this.dataset={};this.listeners={};this.classList={add(){},remove(){}};}
    append(...nodes){this.children.push(...nodes);}
    appendChild(node){this.append(node);return node;}
    get childNodes(){return this.children;}
    get options(){return this.children.filter(node=>node.tag==='option');}
    addEventListener(type,fn){this.listeners[type]=fn;}
    setAttribute(){}
}
const all=node=>[node,...node.children.flatMap(all)];
async function check(role){
    const character={id:21,skills:[{id:6,level:1}]};
    const context={Intl,Date,console,currentCharacter:character,
        window:{},document:{addEventListener(){},createElement:tag=>new Element(tag)},
        getCurrentUser:()=>({role}),userHasPrivilegedRole:user=>['director','administrator'].includes(user?.role),
        aetherShareUi:{tr:()=> 'Economie'},aetherShareMarketClient:{resetActionView(){}}};
    vm.createContext(context);
    vm.runInContext(fs.readFileSync('js/actionButtons.js','utf8'),context);
    vm.runInContext(fs.readFileSync('js/actionsCharacter.js','utf8'),context);
    vm.runInContext(`aetherCharacterActionsState.events=[{idEvent:7,title:'Holiday',dateStart:'2027-05-15',dateEnd:'2027-05-17'}];
        aetherCharacterActionsState.selectedEventId=7;`,context);
    let layout=context.buildCharacterActionsLayout(character);
    assert.equal(all(layout).filter(node=>node.tag==='select').length,Number(role!=='participant'),'Only managers get an event selector');
    assert.equal(all(layout).filter(node=>node.className?.startsWith('character-action-tile ')).length,1,'Entry view shows available actions');
    vm.runInContext("aetherCharacterActionsState.activeAction='economy';",context);
    layout=context.buildCharacterActionsLayout(character);
    assert(!all(layout).some(node=>node.className?.startsWith('character-action-tile ')),'Selected action hides the action tiles');
    const back=all(layout).find(node=>node.tag==='button'&&node.children.some(child=>child.textContent==='Toon acties'));
    assert(back,'Selected action provides the back button');
    assert(all(layout).some(node=>node.dataset.shareActionContent==='economy'));
    context.renderCharacterActionsTab=async()=>{};
    back.listeners.click();
    assert.equal(vm.runInContext('aetherCharacterActionsState.activeAction',context),'','Back returns to the action list');
}
(async()=>{for(const role of ['participant','director','administrator'])await check(role);console.log('Action list, role-specific event selector, selected-action view and back navigation passed.');})().catch(error=>{console.error(error);process.exitCode=1;});
