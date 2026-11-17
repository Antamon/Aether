(() => {
    const R=window.AetherResearch, host=document.getElementById('researchAdmin');
    const state={tab:'challenges',event:0,challenge:0,discovery:0,catalog:null,detail:null};
    let pdfRequest;
    async function pdfExporter(){
        if(window.AetherResearchQrPdf?.download&&window.jspdf?.jsPDF&&typeof window.qrcode==='function')return window.AetherResearchQrPdf;
        if(!pdfRequest)pdfRequest=(async()=>{
            const load=(path)=>new Promise((resolve,reject)=>{
                const script=document.createElement('script');
                // Resolve current asset hashes on the server, including when an older HTML page is cached.
                script.src=`asset.php?path=${encodeURIComponent(path)}`;
                script.onload=resolve;script.onerror=()=>{script.remove();reject(new Error('De PDF-module kon niet geladen worden. Herlaad de pagina en probeer opnieuw.'));};
                document.head.append(script);
            });
            if(typeof window.qrcode!=='function')await load('js/vendor/qrcode-generator.js');
            if(!window.jspdf?.jsPDF)await load('js/vendor/jspdf.umd.min.js');
            if(!window.AetherResearchQrPdf?.download)await load('js/researchQrPdf.js');
            if(!window.AetherResearchQrPdf?.download||!window.jspdf?.jsPDF||typeof window.qrcode!=='function')throw new Error('De PDF-module is niet beschikbaar. Herlaad de pagina en probeer opnieuw.');
            return window.AetherResearchQrPdf;
        })().catch(error=>{pdfRequest=null;throw error;});
        return pdfRequest;
    }
    const names=['Ongetraind','Beginner','Gevorderde','Meester','Specialist'];
    const accordion=(label,key)=>{const d=R.el('details','','accordion-item research-accordion');d.dataset.key=key;const b=R.el('div','','accordion-body');const summary=R.el('summary',label,'accordion-button collapsed');d.append(summary,b);d.addEventListener('toggle',()=>summary.classList.toggle('collapsed',!d.open));return {node:d,body:b};};
    const feedbackSection=(label,key,heading)=>{const node=R.el('section','','research-feedback-section'),body=R.el('div');node.dataset.key=key;node.append(R.el(heading,label),body);return {node,body};};
    const saveButton=(body,d,op,payload)=>R.group(R.button('Opslaan','fa-check',()=>R.run(body,async()=>{const result=await R.write(op,payload());if(op==='challenge_save'){state.challenge=result.id;state.discovery=0;}if(op==='discovery_save')state.discovery=result.id;d.close();await render();})));
    async function render() {
        await R.run(host,async()=>{
            host.replaceChildren();
            const toolbar=R.el('div','','research-toolbar');
            const events=R.select(state.catalog.events,state.event,'Alle events');toolbar.append(R.field('Event',events));
            events.addEventListener('change',()=>{state.event=Number(events.value);state.challenge=0;state.discovery=0;state.detail=null;void render();});
            if(state.tab==='challenges') {
                if(state.event)toolbar.append(R.group(R.button('Eventtijdzone','fa-clock',()=>timezoneDialog())));
            }
            host.append(toolbar);
            const list=await R.read('challenges',{idEvent:state.event});state.challenges=list.challenges;
            if(state.tab==='progress'){await progress(toolbar);return;}
            const layout=R.el('div','','research-layout');const links=R.el('div','','research-list event-character-accordion');state.detailHost=R.el('div','','research-selection');layout.append(links,state.detailHost);host.append(layout);
            links.append(R.button('Nieuwe challenge','fa-plus',()=>challengeDialog()));
            const navigation=R.el('div','','accordion research-navigation');links.append(navigation);state.navigation=new Map();
            list.challenges.forEach(c=>{
                const item=accordion(c.title,`challenge-nav-${c.id}`);item.node.dataset.challengeId=c.id;
                const summary=item.node.querySelector('summary');
                summary.addEventListener('click',event=>{
                    event.preventDefault();
                    if(item.node.open&&state.challenge===Number(c.id)&&!state.discovery){item.node.open=false;return;}
                    state.challenge=Number(c.id);state.discovery=0;
                    state.navigation.forEach((other,id)=>other.node.open=id===state.challenge);
                    item.body.replaceChildren(R.el('p','Challenge laden…','small'));
                    void loadChallenge();
                });
                state.navigation.set(Number(c.id),item);navigation.append(item.node);
            });
            if(!list.challenges.length)links.append(R.el('p','Nog geen challenges voor dit event.'));
            if(!list.challenges.some(c=>Number(c.id)===state.challenge)){state.challenge=0;state.discovery=0;}
            await loadChallenge();
        });
    }
    async function loadChallenge(focusDiscovery=false) {
        const out=state.detailHost;if(!out)return;if(!state.challenge){out.replaceChildren();return;}
        await R.run(out,async()=>{
            const selected=state.challenge;const c=await R.read('challenge',{idChallenge:selected});if(state.challenge!==selected||state.detailHost!==out)return;state.detail=c;out.replaceChildren();
            populateNavigation(c);
            c.discoveries.filter(d=>Number(d.id)===state.discovery).forEach(d=>{
                const section=R.el('section','','research-discovery');const a={node:section,body:section};out.append(section);
                section.append(discoveryGeneral(d,c));
                if(d.used)a.body.append(R.el('p','Al gevonden: routes en voorwaarden liggen vast. Je kunt de inhoud nog bijwerken.','small text-muted mt-2'));
                const filled=route=>route.steps.some(step=>R.rich(step.content).textContent.replace(/[\s\u00a0\u200b]/g,'')||(step.media||[]).length);
                const iconButton=(label,icon,action)=>{const b=R.button('',icon,action);b.className='data-info-button';b.title=label;b.setAttribute('aria-label',label);return b;};
                for(let level=0;level<=4;level++){
                    const section=feedbackSection(names[level],'level-'+d.id+'-'+level,'h4');a.body.append(section.node);
                    const routes=d.routes.filter(r=>Number(r.level)===level).sort((x,y)=>(x.kind!=='general')-(y.kind!=='general'));
                    const general=routes.find(r=>r.kind==='general');
                    const table=R.el('table','','table aether-table align-middle'),rows=R.el('tbody');table.append(rows);section.body.append(table);
                    routes.forEach(r=>{
                        if(r.kind==='general'&&!filled(r)){
                            const previous=d.routes.filter(o=>o.kind==='general'&&Number(o.level)<level&&filled(o)).sort((x,y)=>Number(y.level)-Number(x.level))[0];
                            section.body.append(R.el('p',level===0?'No additional information':'Niet ingevuld: gebruikt de feedback van '+names[Number(previous?.level||0)]+'.','text-muted'));
                        }
                        const steps=r.steps.length?r.steps:[null];
                        steps.forEach(step=>{
                            const row=R.el('tr'),kind=R.el('td'),content=R.el('td'),media=R.el('td'),edit=R.el('td');
                            if(r.kind!=='general'){
                                const icon=R.el('i','','fa-solid '+(r.kind==='character'?'fa-user':'fa-star'));
                                const labels=(r.characterIds||[]).map(id=>state.catalog.characters.find(c=>Number(c.id)===Number(id))?.name).filter(Boolean);
                                if(r.idRequiredSpecialisation)labels.push(state.catalog.specialisations.find(o=>Number(o.id)===Number(r.idRequiredSpecialisation))?.name||'');
                                icon.title=labels.join(', ');icon.setAttribute('aria-label',icon.title);kind.append(icon);
                            }
                            if(step){content.append(R.rich(step.content));if(Number(step.confirmationRequired))content.append(R.el('small','Bevestiging nodig'));if(step.conditions.length)content.append(R.el('small',' · '+step.conditions.length+' voorwaarde(n)'));}
                            else content.append(R.el('span','Nog geen feedback','text-muted'));
                            if(r.kind!=='general'&&!d.used)content.append(R.button('Variantinstellingen','fa-gear',()=>routeDialog(d,level,r)));
                            if(step)media.append(iconButton('Media beheren','fa-photo-film',()=>mediaDialog(step)));
                            if(step||!d.used)edit.append(iconButton('Feedback bewerken','fa-pen',()=>stepDialog(d,r,step)));
                            row.append(kind,content,media,edit);rows.append(row);
                        });
                        if(!d.used&&filled(r))section.body.append(R.button('Volgende feedbackstap','fa-plus',()=>stepDialog(d,r)));
                    });
                    if(!d.used&&general&&filled(general))section.body.append(R.button('Feedbackvariant toevoegen','fa-plus',()=>routeDialog(d,level)));
                }
            });
            if(focusDiscovery)state.navigation.get(Number(c.id))?.body.querySelector(`[data-discovery-id="${state.discovery}"] button`)?.focus({preventScroll:true});
        });
    }
    function discoveryGeneral(d,c){
        const host=R.el('div','','research-discovery-general');
        const view=()=>{
            host.replaceChildren();
            const header=R.el('div','','research-discovery-header');
            header.append(R.el('h3',`${d.title}${Number(d.active)?'':' · niet actief'}`),R.group(R.button('Edit','fa-pen',edit)));host.append(header);
            host.append(R.rich(d.description),R.el('p',`Vaardigheid: ${state.catalog.skills.find(s=>Number(s.id)===Number(d.idSkill))?.name||''}`));
            const bonuses=(d.bonusIds||[]).map(id=>state.catalog.specialisations.find(s=>Number(s.id)===Number(id))?.name).filter(Boolean);
            if(bonuses.length){const box=R.el('div','','research-discovery-bonuses'),list=R.el('ul');box.append(R.el('strong','Bonus-specialisaties'));bonuses.forEach(name=>list.append(R.el('li',name)));box.append(list);host.append(box);}
        };
        const edit=()=>{
            host.replaceChildren();
            const header=R.el('div','','research-discovery-header'),form=R.el('form'),fields=R.el('fieldset');
            const title=R.input(d.title),description=R.editor(d.description),skill=R.select(state.catalog.skills,d.idSkill),active=R.check('Actief',Number(d.active));
            title.required=true;title.maxLength=200;
            const bonuses=R.multi(state.catalog.specialisations.filter(s=>Number(s.idSkill)===Number(d.idSkill)),d.bonusIds||[]);
            skill.disabled=!!d.used;bonuses.disabled=!!d.used;
            skill.addEventListener('change',()=>{
                const selected=R.ids(bonuses),options=R.multi(state.catalog.specialisations.filter(s=>Number(s.idSkill)===Number(skill.value)),selected);
                bonuses.replaceChildren(...options.options);bonuses.size=options.size;
            });
            const save=R.button('Opslaan','fa-check',()=>void commit()),cancel=R.button('Annuleren','fa-xmark',view);
            const commit=()=>R.run(host,async()=>{
                if(!form.reportValidity())return;
                save.disabled=true;cancel.disabled=true;fields.disabled=true;description.editor.contentEditable='false';
                try{
                    await R.write('discovery_save',{id:Number(d.id),revision:Number(d.revision),idChallenge:Number(c.id),title:title.value,description:description.getHtml(),idSkill:Number(skill.value),bonusIds:R.ids(bonuses),active:active.input.checked});
                    await loadChallenge();
                }finally{save.disabled=false;cancel.disabled=false;fields.disabled=false;description.editor.contentEditable='true';}
            });
            header.append(R.el('h3','Discovery bewerken'),R.group(save,cancel));
            fields.append(R.field('Titel',title),R.field('Beschrijving',description.wrapper),R.field('Vaardigheid',skill),R.field('Bonus-specialisaties (optioneel)',bonuses),active.box);
            form.append(fields);form.addEventListener('submit',event=>{event.preventDefault();void commit();});host.append(header,form);title.focus();
        };
        view();return host;
    }
    function populateNavigation(c){
        const item=state.navigation.get(Number(c.id));if(!item)return;
        item.node.open=true;item.body.replaceChildren();
        item.body.append(R.rich(c.description));
        const events=R.el('ul','','research-challenge-events');
        c.eventIds.forEach(id=>{const event=state.catalog.events.find(e=>Number(e.id)===Number(id));if(event)events.append(R.el('li',event.title));});item.body.append(events);
        if(!Number(c.active))item.body.append(R.el('p','Challenge niet actief','text-muted'));
        const discoveries=R.el('div','','table-responsive research-discovery-navigation');item.body.append(discoveries);
        const table=R.el('table','','table aether-table align-middle mb-0 research-discovery-table');
        const caption=R.el('caption','Discoveries','visually-hidden');
        const rows=R.el('tbody');table.append(caption,rows);discoveries.append(table);
        c.discoveries.forEach(d=>{
            const row=R.el('tr');row.dataset.discoveryId=d.id;
            row.classList.toggle('is-selected',Number(d.id)===state.discovery);
            const name=R.el('td',d.title),cell=R.el('td','','research-view-column');
            const view=R.el('button','','data-info-button');view.type='button';view.title=`${d.title} bekijken`;view.setAttribute('aria-label',view.title);
            if(Number(d.id)===state.discovery)view.setAttribute('aria-current','true');
            const icon=R.el('i','','fa-solid fa-eye');icon.setAttribute('aria-hidden','true');view.append(icon);
            view.addEventListener('click',()=>{state.discovery=Number(d.id);void loadChallenge(true);});
            cell.append(view);row.append(name,cell);rows.append(row);
        });
        if(!c.discoveries.length)item.body.append(R.el('p','Nog geen discoveries.','small'));
        const qr=R.button('QR-code','fa-qrcode',()=>R.run(item.body,async()=>{
            qr.disabled=true;
            try{const pdf=await pdfExporter();await pdf.download(c,state.catalog.skills);}finally{qr.disabled=false;}
        }));
        item.body.append(R.group(R.button('Edit','fa-pen',()=>challengeDialog(c)),R.button('Discovery toevoegen','fa-plus',()=>discoveryDialog()),qr));
    }
    function challengeDialog(c=null){R.dialog(c?'Challenge bewerken':'Nieuwe challenge',(body,d)=>{
        const title=R.input(c?.title||''),description=R.editor(c?.description),events=R.multi(state.catalog.events,c?.eventIds||(state.event?[state.event]:[])),active=R.check('Actief',c?Number(c.active):true);
        body.append(R.field('Titel',title),R.field('Beschrijving',description.wrapper),R.field('Events (meerdere mogelijk)',events));if(c)body.append(active.box);
        body.append(saveButton(body,d,'challenge_save',()=>({id:Number(c?.id||0),...(c?{revision:Number(c.revision)}:{}),title:title.value,description:description.getHtml(),eventIds:R.ids(events),active:active.input.checked})));
    });}
    function discoveryDialog(discovery=null){R.dialog(discovery?'Discovery bewerken':'Nieuwe discovery',(body,d)=>{
        const title=R.input(discovery?.title||''),desc=R.editor(discovery?.description),skill=R.select(state.catalog.skills,discovery?.idSkill||state.catalog.skills[0]?.id),active=R.check('Actief',discovery?Number(discovery.active):true);
        if(discovery?.used)skill.disabled=true;
        body.append(R.field('Titel',title),R.field('Beschrijving',desc.wrapper),R.field('Vaardigheid',skill));body.append(active.box);
        if(!discovery)body.append(R.el('p','Ongetraind begint met “No additional information”. Lege hogere niveaus gebruiken de feedback van het dichtstbijzijnde ingevulde lagere niveau.','small'));
        body.append(saveButton(body,d,'discovery_save',()=>({id:Number(discovery?.id||0),...(discovery?{revision:Number(discovery.revision)}:{}),idChallenge:Number(state.detail.id),title:title.value,description:desc.getHtml(),idSkill:Number(skill.value),bonusIds:discovery?.bonusIds||[],active:active.input.checked})));
    });}
    function routeDialog(discovery,level,route=null){R.dialog('Feedbackvariant',(body,d)=>{
        const kind=R.select([{id:'specialisation',name:'Specialisatievariant'},{id:'character',name:'Personagevariant'}],route?.kind||'specialisation');
        const spec=R.select(state.catalog.specialisations,route?.idRequiredSpecialisation||'','Geen vereiste specialisatie'),chars=R.multi(state.catalog.characters,route?.characterIds||[]),priority=R.input(route?.priority||1,'number');priority.min=0;priority.max=9999;
        const charsField=R.field('Personages',chars),priorityField=R.field('Prioriteit (hoger wint)',priority);
        const visibility=()=>{charsField.hidden=kind.value!=='character';priorityField.hidden=kind.value!=='specialisation';};kind.addEventListener('change',visibility);visibility();
        body.append(R.field('Variant',kind),R.field('Verplichte specialisatie (geen niveaubonus)',spec),charsField,priorityField);
        body.append(saveButton(body,d,'route_save',()=>({id:Number(route?.id||0),...(route?{revision:Number(route.revision)}:{}),idDiscovery:Number(discovery.id),level,kind:kind.value,idRequiredSpecialisation:Number(spec.value),priority:Number(priority.value),characterIds:kind.value==='character'?R.ids(chars):[]})));
    });}
    function stepDialog(discovery,route,step=null){R.dialog(step?`Stap ${step.position} bewerken`:'Volgende feedbackstap',(body,d)=>{
        const text=R.editor(step?.content),confirm=R.check('Bevestiging nodig: de SF moet aangeven dat de opdracht uitgevoerd is',Number(step?.confirmationRequired||0));confirm.input.disabled=discovery.used;
        body.append(R.field('Feedback',text.wrapper),confirm.box,R.el('p','Stappen volgen elkaar op. Zonder bevestiging rondt de stap af wanneer de feedback zichtbaar is getoond. Alle toegevoegde voorwaarden moeten vervuld zijn.','small'));
        const conditions=R.el('div');body.append(conditions);const readers=[];
        const add=c=>{const item=conditionEditor(discovery,route,step,c,discovery.used);readers.push(item);conditions.append(item.node);};
        (step?.conditions||[]).forEach(add);
        if(!discovery.used){const type=R.select([{id:'discoveries',name:'Andere discoveries afgerond'},{id:'step',name:'Eerdere stap afgerond'},{id:'duration',name:'Na een tijdsduur'},{id:'absolute',name:'Op een vast tijdstip'}],'discoveries');body.append(R.field('Voorwaarde toevoegen',type),R.group(R.button('Voorwaarde toevoegen','fa-plus',()=>add({type:type.value}))));}
        body.append(saveButton(body,d,'step_save',()=>({id:Number(step?.id||0),...(step?{revision:Number(step.revision)}:{}),idRoute:Number(route.id),content:text.getHtml(),confirmationRequired:confirm.input.checked,conditions:readers.filter(r=>r.node.isConnected).map(r=>r.read())})));
    });}
    function conditionEditor(discovery,route,step,c,locked){
        const node=R.el('div','','research-condition');const inner=R.el('fieldset');inner.disabled=locked;node.append(inner);let read;
        if(c.type==='discoveries'){
            const choices=R.multi(state.detail.discoveries.filter(d=>Number(d.id)!==Number(discovery.id)),c.ids||[]);inner.append(R.field('Alle geselecteerde discoveries zijn afgerond',choices));read=()=>({type:'discoveries',ids:R.ids(choices)});
        }else if(c.type==='step'){
            const choices=R.select(route.steps.filter(s=>!step||Number(s.position)<Number(step.position)).map(s=>({id:s.id,name:`Stap ${s.position}`})),c.id||route.steps[0]?.id);inner.append(R.field('Eerdere stap afgerond',choices));read=()=>({type:'step',id:Number(choices.value)});
        }else if(c.type==='absolute'){
            const inputs={};state.detail.eventIds.forEach(id=>{const e=state.catalog.events.find(e=>Number(e.id)===id);const input=R.input(c.times?.[id]||'','datetime-local');inputs[id]=input;inner.append(R.field(`${e.title} · ${e.timeZone}`,input));});
            const show=R.check('Countdown tonen',c.showCountdown);inner.append(show.box);read=()=>({type:'absolute',times:Object.fromEntries(Object.entries(inputs).map(([id,n])=>[id,n.value])),showCountdown:show.input.checked});
        }else{
            const hours=R.input(Math.floor((c.seconds||0)/3600),'number'),minutes=R.input(Math.floor((c.seconds||0)%3600/60),'number');hours.min=0;minutes.min=0;minutes.max=59;
            const scope=R.select([{id:'shared',name:'Gedeelde klok (dit event)'},{id:'character',name:'Per personage'}],c.scope||'shared');
            const source=R.select(state.detail.discoveries,c.discoveryId||discovery.id),action=R.select([{id:'scanned',name:'Discovery scannen'},{id:'shown',name:'Feedback getoond'},{id:'confirmed',name:'Opdracht bevestigd'},{id:'step_completed',name:'Stap afgerond'},{id:'discovery_completed',name:'Discovery afgerond'}],c.action||'scanned');
            let sourceStep;const stepHost=R.el('div');
            const update=()=>{stepHost.replaceChildren();const d=state.detail.discoveries.find(d=>Number(d.id)===Number(source.value));let steps=(d?.routes||[]).flatMap(r=>r.steps.map(s=>({id:s.id,name:`${names[Number(r.level)]} · ${r.kind} · stap ${s.position}`,...s,idRoute:r.id})));
                if(Number(source.value)===Number(discovery.id))steps=steps.filter(s=>Number(s.idRoute)===Number(route.id)&&(!step||Number(s.position)<Number(step.position)));
                if(action.value==='confirmed')steps=steps.filter(s=>Number(s.confirmationRequired));sourceStep=R.select(steps,c.stepId||steps[0]?.id);if(!['scanned','discovery_completed'].includes(action.value))stepHost.append(R.field('Startende feedbackstap',sourceStep));};
            source.addEventListener('change',update);action.addEventListener('change',update);update();
            const show=R.check('Countdown tonen',c.showCountdown);const grid=R.el('div','','research-grid');grid.append(R.field('Uren',hours),R.field('Minuten',minutes));
            inner.append(grid,R.field('Reikwijdte',scope),R.field('Discovery van de starthandeling',source),R.field('Starthandeling',action),stepHost,show.box);
            read=()=>({type:'duration',seconds:Number(hours.value)*3600+Number(minutes.value)*60,scope:scope.value,discoveryId:Number(source.value),action:action.value,stepId:['scanned','discovery_completed'].includes(action.value)?0:Number(sourceStep.value),showCountdown:show.input.checked});
        }
        if(!locked)node.append(R.group(R.button('Voorwaarde verwijderen','fa-trash',()=>node.remove())));
        // Preserve second precision and saved UTC conversion when structure is locked (server reconverts civil times).
        return {node,read:locked?()=>{const clean={...c};delete clean.utc;return clean;}:read};
    }
    function mediaDialog(step){R.dialog('Media bij feedback',(body,d)=>{
        body.append(R.el('p','Afbeelding, geluid of video, maximaal 8 MB per bestand. Media wordt alleen na een toegangscontrole aangeboden.'));
        const file=R.input('','file');file.accept='image/jpeg,image/png,image/webp,audio/mpeg,audio/ogg,audio/wav,video/mp4,video/webm,video/ogg';body.append(R.field('Bestand',file));
        body.append(R.group(R.button('Uploaden','fa-upload',()=>R.run(body,async()=>{if(!file.files[0])throw new Error('Kies eerst een bestand.');const data=new FormData();data.append('idStep',step.id);data.append('file',file.files[0]);const response=await fetch('api/research/upload.php',{method:'POST',headers:{'X-CSRF-Token':window.AETHER_CSRF_TOKEN},body:data});const result=await response.json();if(!response.ok)throw new Error(result.error);d.close();await loadChallenge();}))));
        R.media(step.media,body);
        step.media.forEach(m=>{const row=R.el('div','','d-flex justify-content-between align-items-center gap-2 mt-3');row.append(R.el('span',m.filename),R.group(R.button('Verwijderen','fa-trash',()=>R.run(body,async()=>{await R.write('media_delete',{id:Number(m.id)});d.close();await loadChallenge();}))));body.append(row);});
        const library=R.el('div');body.append(library);
        body.append(R.button('Media uit andere discoveries','fa-photo-film',()=>R.run(body,async()=>{
            const data=await R.read('media_library',{idStep:Number(step.id)});library.replaceChildren();
            data.media.forEach(m=>{const row=R.el('div','','mt-3');row.append(R.el('span',m.discoveryTitle+' · '+m.filename),R.button('Koppelen','fa-link',()=>R.run(body,async()=>{await R.write('media_link',{idStep:Number(step.id),idMedia:Number(m.id)});d.close();await loadChallenge();})));R.media([m],row);library.append(row);});
            if(!data.media.length)library.append(R.el('p','Geen andere media beschikbaar.'));
        })));

    });}
    function timezoneDialog(){const event=state.catalog.events.find(e=>Number(e.id)===state.event);R.dialog(`Tijdzone · ${event.title}`,(body,d)=>{
        const zone=R.select(state.catalog.timeZones.map(z=>({id:z,name:z})),event.timeZone);body.append(R.field('Tijdzone van het event',zone));body.append(R.group(R.button('Opslaan','fa-check',()=>R.run(body,async()=>{await R.write('timezone_save',{id:state.event,timeZone:zone.value});state.catalog=await R.read('catalog');d.close();await render();}))));
    });}
    async function progress(toolbar){
        const challenge=R.select(state.challenges,state.challenge,'Alle challenges'),discovery=R.select([],0,'Alle discoveries'),status=R.select(Object.entries(R.statuses).map(([id,name])=>({id,name})),state.status||'','Alle statussen');
        toolbar.append(R.field('Challenge',challenge),R.field('Discovery',discovery),R.field('Status',status));const content=R.el('div','','research-progress');host.append(content);
        const load=()=>R.run(content,async()=>{
            const data=await R.read('progress',{idEvent:state.event,idChallenge:Number(challenge.value),idDiscovery:Number(discovery.value),status:status.value});content.replaceChildren();
            if(!data.progress.length){content.append(R.el('p','Geen gevonden discoveries voor deze selectie.'));return;}
            const table=R.el('table','','table');const head=R.el('thead'),tr=R.el('tr');['Personage','Event / challenge','Discovery','Status',''].forEach(t=>tr.append(R.el('th',t)));head.append(tr);table.append(head);const tbody=R.el('tbody');table.append(tbody);content.append(table);
            data.progress.forEach(p=>{const row=R.el('tr');[p.characterName,`${p.eventTitle} · ${p.challengeTitle}`,p.discoveryTitle,R.statuses[p.status]].forEach(t=>row.append(R.el('td',t)));const cell=R.el('td');const icon=R.el('button','','research-icon');icon.type='button';icon.title='Voortgang van dit personage';icon.setAttribute('aria-label',icon.title);icon.innerHTML='<i class="fa-solid fa-user" aria-hidden="true"></i>';icon.addEventListener('click',()=>progressDialog(p,load));cell.append(icon);row.append(cell);tbody.append(row);});
        });
        const choose=async()=>{state.challenge=Number(challenge.value);const data=state.challenge?await R.read('challenge',{idChallenge:state.challenge}):{discoveries:[]};discovery.replaceChildren(...R.select(data.discoveries,0,'Alle discoveries').options);await load();};
        challenge.addEventListener('change',()=>R.run(content,choose));discovery.addEventListener('change',load);status.addEventListener('change',()=>{state.status=status.value;void load();});await choose();
    }
    function progressDialog(p,reload){R.dialog(`${p.characterName} · ${p.discoveryTitle}`,(body,d)=>{
        const link=R.el('a','Personage openen','research-icon');link.href=`index.html?character=${Number(p.idCharacter)}&tab=actions`;body.append(link,R.el('p',`${p.eventTitle} · gevonden ${p.foundAt} UTC · effectief niveau ${p.effectiveLevel}`));
        p.steps.forEach(s=>{const card=R.el('div','','research-step');card.append(R.el('h4',`Stap ${s.position}`),R.el('p',`Getoond: ${s.shownAt||'nee'} · Afgerond: ${s.completedAt||'nee'} · Bevestigd: ${s.confirmedAt||'nee'} (UTC)`));
            if(!s.completedAt){const reason=R.input();card.append(R.field('Reden voor handmatige afronding',reason),R.group(R.button('Afronden als spelleider','fa-check',()=>R.run(card,async()=>{await R.write('progress_complete',{idEvent:Number(p.idEvent),idCharacter:Number(p.idCharacter),idDiscovery:Number(p.idDiscovery),idStep:Number(s.id),reason:reason.value});d.close();await reload();}))));}body.append(card);});
        p.timers.forEach(t=>{const card=R.el('div','','research-step');card.append(R.el('h4',`Klok · stap ${t.position}`),R.el('p',`${Number(t.scopeCharacter)===0?'Gedeeld':'Per personage'} · gestart door ${t.firstName} ${t.lastName} · ${t.startedAt} UTC · eindigt ${t.endsAt} UTC`));
            const end=R.input('','datetime-local'),reason=R.input();const zone=state.catalog.events.find(e=>Number(e.id)===Number(p.idEvent))?.timeZone;
            card.append(R.field(`Nieuw eindtijdstip (${zone})`,end),R.field('Reden',reason),R.group(R.button('Klok aanpassen','fa-clock',()=>R.run(card,async()=>{await R.write('timer_adjust',{idEvent:Number(p.idEvent),idStep:Number(t.idStep),scopeCharacter:Number(t.scopeCharacter),localEnd:end.value,reason:reason.value});d.close();await reload();}))));body.append(card);});
    });}
    document.querySelectorAll('[data-research-tab]').forEach(b=>b.addEventListener('click',()=>{state.tab=b.dataset.researchTab;document.querySelectorAll('[data-research-tab]').forEach(n=>n.classList.toggle('active',n===b));void render();}));
    void R.run(host,async()=>{const user=await aetherGetCurrentUser();if(!userHasPrivilegedRole(user)){host.replaceChildren(R.el('p','Alleen admins en directors kunnen Onderzoek beheren.'));return;}state.catalog=await R.read('catalog');await render();});
})();
