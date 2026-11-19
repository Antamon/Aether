var aetherT = globalThis.aetherT || ((text, values = []) => text.replace(/\{(\d+)\}/g, (match, index) => values[index] === undefined ? match : String(values[index])));
/* Trait administration inside the existing admin page. */
(()=>{
    const root=document.getElementById('adminTraitEditor');if(!root)return;
    const select=document.getElementById('adminTraitSelect'),create=document.getElementById('adminNewTraitButton');
    const classTabs=[...document.querySelectorAll('[data-admin-trait-class]')],groupFilter=document.getElementById('adminTraitGroupFilter');
    const state={catalog:[],current:null,selectedClass:'upper class',editing:false,saving:false,loaded:false,loading:null,sequence:0,loadSequence:0};
    const classes={'all':aetherT("Alle klassen"),'upper class':aetherT("Bovenklasse"),'middle class':aetherT("Middenklasse"),'lower class':aetherT("Onderklasse")};
    const types={status:aetherT("Statuseigenschap"),profession:aetherT("Beroep"),quality:aetherT("Kwaliteit")};
    const ranks={singular:aetherT("Eenmalig"),range_positive:aetherT("Positieve rangen"),range_signed:aetherT("Positieve en negatieve rangen")};
    const el=(tag,text='',cls='')=>{const n=document.createElement(tag);n.textContent=text;n.className=cls;return n;};
    const button=(text,icon,action)=>{const n=el('button','','action-button');n.type='button';const i=el('i','','fa-solid '+icon);i.setAttribute('aria-hidden','true');n.append(i,document.createTextNode(' '+text));n.addEventListener('click',action);return n;};
    const label=(text,input)=>{const box=el('div','','mb-3'),name=el('label',text,'form-label');input.id='adminTraitField'+(++state.sequence);name.htmlFor=input.id;box.append(name,input);return box;};
    const groupOf=item=>String(item.traitGroup||'').trim();
    const matchesClass=item=>item.class==='all'||item.class===state.selectedClass;
    const matches=item=>matchesClass(item)&&(!groupFilter.value||JSON.stringify(groupOf(item))===groupFilter.value);
    function updateClassTabs(){for(const tab of classTabs){const active=tab.dataset.adminTraitClass===state.selectedClass;tab.classList.toggle('active',active);tab.setAttribute('aria-pressed',String(active));}}
    function groupOptions(){
        const previous=groupFilter.value;groupFilter.replaceChildren();const all=el('option',aetherT("Alle groepen"));all.value='';groupFilter.append(all);
        const groups=[...new Set(state.catalog.filter(matchesClass).map(groupOf))].sort((a,b)=>a.localeCompare(b,'nl'));
        for(const group of groups){const option=el('option',group?aetherT(group):aetherT("Geen groep"));option.value=JSON.stringify(group);groupFilter.append(option);}
        groupFilter.value=[...groupFilter.options].some(option=>option.value===previous)?previous:'';
    }
    function options(){select.replaceChildren();const items=state.catalog.filter(matches),empty=el('option',items.length?aetherT("Kies een eigenschap"):aetherT("Geen eigenschappen voor deze filters"));empty.value='0';select.append(empty);for(const item of items){const o=el('option',item.name);o.value=item.id;select.append(o);}select.value=String(state.current?.id||0);}
    function lock(){for(const control of [select,create,...classTabs,groupFilter])control.disabled=state.editing||state.saving;root.querySelectorAll('fieldset,button').forEach(n=>n.disabled=state.saving);}
    function holders(){renderAdminHolders(state.current?{idSkill:state.current.id,holders:state.current.holders||[]}:null,'adminTraitHolders','eigenschap');}
    function header(editing){const row=el('div','','d-flex justify-content-between align-items-start gap-3 mb-3'),heading=el('h1',editing?aetherT("Eigenschap bewerken"):(state.current?.name||aetherT("Eigenschappen")),'h4'),actions=el('div','','button-group');row.append(heading,actions);root.append(row);return actions;}
    function view(){state.editing=false;options();root.replaceChildren();const actions=header(false);if(!state.current){root.append(el('p',aetherT("Kies een eigenschap of maak een nieuwe aan.")));holders();lock();return;}
        const edit=button(aetherT("Edit"),'fa-pen-nib',()=>editForm());edit.classList.add('admin-skill-view-edit');actions.append(edit);
        const trait=state.current;
        for(const [name,value] of [[aetherT("Categorie"),types[trait.type]],[aetherT("Klasse"),classes[trait.class]],[aetherT("Groep"),trait.traitGroup||aetherT("Geen")],[aetherT("Rangtype"),ranks[trait.rankType]]]){const p=el('p');p.append(el('strong',name+': '),document.createTextNode(value));root.append(p);}
        const flags=[Number(trait.isUnique)?aetherT("Uniek"):null,Number(trait.grouped)?aetherT("Gegroepeerd"):null].filter(Boolean);if(flags.length)root.append(el('p',flags.join(', ')));
        root.append(el('p',trait.description||'—','admin-skill-view-text'));
        const table=el('table','','table aether-table'),body=el('tbody');table.setAttribute('aria-label',aetherT("Eigenschapsgegevens"));
        for(const [name,value] of [[aetherT("Kost"),trait.cost],[aetherT("Inkomsten"),trait.income],[aetherT("Evolutie"),trait.evolution],[aetherT("Personeelsvoorwaarde"),trait.staffRequirements]]){const row=el('tr');row.append(el('td',name),el('td',value===null||value===undefined?'—':String(value)));body.append(row);}table.append(body);root.append(table);holders();lock();
    }
    async function load(id){const request=++state.loadSequence;try{const trait=await apiFetchJson('api/admin/traits.php?id='+Number(id));if(request!==state.loadSequence||!matches(trait))return;state.current=trait;select.value=String(trait.id);view();}catch(error){if(request!==state.loadSequence)return;showAdminFeedback(error.message||aetherT("Kon de eigenschap niet laden."),'danger');select.value=String(state.current?.id||0);}}
    function editForm(isNew=false){
        state.editing=true;root.replaceChildren();const trait=isNew?{id:0,version:'',name:'',description:'',type:'status',class:state.selectedClass,rankType:'singular',traitGroup:groupFilter.value?JSON.parse(groupFilter.value):'',cost:0,income:null,evolution:null,staffRequirements:0,isUnique:false,grouped:false}:state.current;
        const actions=header(true),form=el('form'),fields=el('fieldset'),controls={};form.append(fields);root.append(form);
        if(isNew){root.querySelector('h1').textContent=aetherT("Nieuwe eigenschap");const draft=el('option',aetherT("Nieuwe eigenschap"));draft.value='__draft__';select.append(draft);select.value='__draft__';}
        function input(name,title,type='text',values=null){let control;if(values){control=el('select','','form-select');for(const [value,title] of Object.entries(values)){const o=el('option',title);o.value=value;control.append(o);}}else{control=el(type==='textarea'?'textarea':'input','',type==='checkbox'?'form-check-input':'form-control');if(type!=='textarea')control.type=type;else control.rows=5;}
            if(type==='checkbox')control.checked=!!Number(trait[name]);else control.value=trait[name]??'';
            controls[name]=control;fields.append(label(title,control));return control;}
        input('name',aetherT("Naam")).required=true;controls.name.maxLength=150;
        input('type',aetherT("Categorie"),'text',types);input('class',aetherT("Klasse"),'text',classes);input('traitGroup',aetherT("Groep")).maxLength=100;input('rankType',aetherT("Rangtype"),'text',ranks);
        input('isUnique',aetherT("Uniek"),'checkbox');input('grouped',aetherT("Gegroepeerd"),'checkbox');
        input('description',aetherT("Beschrijving"),'textarea').maxLength=16000;
        for(const [name,title] of [['cost',aetherT("Kost")],['income',aetherT("Inkomsten")],['staffRequirements',aetherT("Personeelsvoorwaarde")]]){input(name,title,'number').step='1';}
        controls.staffRequirements.min='0';controls.staffRequirements.required=true;
        input('evolution',aetherT("Evolutie")).inputMode='decimal';
        if(trait.inUse){for(const name of ['type','class','traitGroup','rankType','isUnique','grouped'])controls[name].disabled=true;fields.append(el('p',aetherT("Deze eigenschap is al aan personages gekoppeld. Type, klasse, groep en ranginstellingen liggen vast."),'text-muted small'));}
        const commit=async()=>{
            if(state.saving||!form.reportValidity())return;
            const data={id:Number(trait.id),version:trait.version||''};
            for(const [name,control] of Object.entries(controls)){data[name]=control.type==='checkbox'?control.checked:control.value;}
            for(const name of ['cost','income'])data[name]=controls[name].value===''?null:Number(controls[name].value);
            data.staffRequirements=Number(controls.staffRequirements.value);data.evolution=controls.evolution.value.trim()||null;
            state.saving=true;lock();try{const result=await apiFetchIdempotentJson('api/admin/traits.php',{method:'POST',body:data});state.current=result.trait;state.catalog=result.traits;if(!matchesClass(state.current))state.selectedClass=state.current.class;updateClassTabs();groupOptions();if(!matches(state.current))groupFilter.value='';options();view();showAdminFeedback(aetherT("Eigenschap opgeslagen."),'success');}
            catch(error){showAdminFeedback(error.message||aetherT("Kon de eigenschap niet opslaan."),'danger');}
            finally{state.saving=false;lock();}
        };
        actions.append(button(aetherT("Opslaan"),'fa-check',()=>void commit()),button(aetherT("Annuleren"),'fa-xmark',()=>{if(!state.saving){hideAdminFeedback();view();}}));
        form.addEventListener('submit',event=>{event.preventDefault();void commit();});if(isNew)renderAdminHolders(null,'adminTraitHolders','eigenschap');else holders();lock();controls.name.focus();
    }
    async function initialize(){if(state.loaded||state.loading)return state.loading;state.loading=(async()=>{try{const data=await apiFetchJson('api/admin/traits.php');state.catalog=data.traits;groupOptions();options();const first=state.catalog.find(matches);if(first)await load(first.id);else view();state.loaded=true;}catch(error){showAdminFeedback(error.message||aetherT("Kon de eigenschappen niet laden."),'danger');}finally{state.loading=null;}})();return state.loading;}
    function filterChanged(){state.loadSequence++;if(state.current&&!matches(state.current)){state.current=null;view();}else options();}
    for(const tab of classTabs)tab.addEventListener('click',()=>{if(state.editing||state.saving)return;state.selectedClass=tab.dataset.adminTraitClass;updateClassTabs();groupOptions();filterChanged();});
    groupFilter.addEventListener('change',()=>{if(!state.editing&&!state.saving)filterChanged();});
    select.addEventListener('change',()=>{if(state.editing){select.value=String(state.current?.id||0);return;}if(Number(select.value))void load(select.value);else{state.current=null;view();}});
    create.addEventListener('click',()=>{if(!state.editing)editForm(true);});
    document.addEventListener('aether:admin-tab',event=>{if(event.detail==='traits')void initialize();});
})();
