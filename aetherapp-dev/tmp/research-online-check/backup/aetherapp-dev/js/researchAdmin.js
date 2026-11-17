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
    const names=['Ongetraind (0)','Beginner (1)','Gevorderde (2)','Meester (3)','Meester + bonus (4)'];
    const accordion=(label,key)=>{const d=R.el('details','','accordion-item research-accordion');d.dataset.key=key;const b=R.el('div','','accordion-body');const summary=R.el('summary',label,'accordion-button collapsed');d.append(summary,b);d.addEventListener('toggle',()=>summary.classList.toggle('collapsed',!d.open));return {node:d,body:b};};
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
            const layout=R.el('div','','research-layout');const links=R.el('div','','research-list event-character-accordion');state.detailHost=R.el('div','','accordion event-character-accordion research-selection');layout.append(links,state.detailHost);host.append(layout);
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
    async function loadChallenge() {
        const out=state.detailHost;if(!out)return;if(!state.challenge){out.replaceChildren();return;}
        await R.run(out,async()=>{
            const open=new Set([...out.querySelectorAll('details[open]')].map(n=>n.dataset.key));
            const selected=state.challenge;const c=await R.read('challenge',{idChallenge:selected});if(state.challenge!==selected||state.detailHost!==out)return;state.detail=c;out.replaceChildren();
            populateNavigation(c);
            out.append(R.el('h2',c.title));
            out.append(R.el('p',c.eventIds.map(id=>state.catalog.events.find(e=>Number(e.id)===id)?.title).filter(Boolean).join(' · ')),R.rich(c.description));
            if(!Number(c.active))out.append(R.el('p','Challenge niet actief','text-muted'));
            c.discoveries.filter(d=>Number(d.id)===state.discovery).forEach(d=>{
                const section=R.el('section','','research-discovery');const a={node:section,body:section};out.append(section);
                const header=R.el('div','','research-discovery-header');
                header.append(R.el('h3',`${d.title}${Number(d.active)?'':' · niet actief'}`),R.group(R.button('Edit','fa-pen',()=>discoveryDialog(d))));section.append(header);
                a.body.append(R.rich(d.description),R.el('p',`Vaardigheid: ${state.catalog.skills.find(s=>Number(s.id)===Number(d.idSkill))?.name||''}`));
                a.body.append(R.group(R.button('Bonus-specialisaties','fa-plus',()=>bonusDialog(d))));
                if(d.used)a.body.append(R.el('p','Al gevonden: routes en voorwaarden liggen vast. Je kunt de inhoud nog bijwerken.','small text-muted mt-2'));
                for(let level=0;level<=4;level++){
                    const l=accordion(names[level],`level-${d.id}-${level}`);a.body.append(l.node);
                    l.body.append(R.el('p','Persoonlijke feedback vervangt specialisatiefeedback; specialisatiefeedback vervangt algemene feedback. Bij specialisatievarianten wint de hoogste prioriteit.','small'));
                    d.routes.filter(r=>Number(r.level)===level).forEach(r=>{
                        let label=r.kind==='general'?'Algemene feedback':r.kind==='character'?'Personagevariant':'Specialisatievariant';
                        if(r.idRequiredSpecialisation)label+=` · ${state.catalog.specialisations.find(s=>Number(s.id)===Number(r.idRequiredSpecialisation))?.name}`;
                        if(r.kind==='specialisation')label+=` · prioriteit ${r.priority}`;
                        if(r.kind==='character')label+=` · ${r.characterIds.map(id=>state.catalog.characters.find(c=>Number(c.id)===id)?.name).join(', ')}`;
                        const route=accordion(label,`route-${r.id}`);l.body.append(route.node);
                        const actions=[];if(r.kind!=='general'&&!d.used)actions.push(R.button('Variant bewerken','fa-pen',()=>routeDialog(d,level,r)));
                        if(!d.used)actions.push(R.button('Volgende feedbackstap','fa-plus',()=>stepDialog(d,r)));
                        if(actions.length)route.body.append(R.group(...actions));
                        if(!r.steps.length)route.body.append(R.el('p','Nog geen feedbackstappen.','mt-2'));
                        r.steps.forEach(s=>{
                            const step=accordion(`Stap ${s.position}${Number(s.confirmationRequired)?' · bevestiging nodig':''}`,`step-${s.id}`);route.body.append(step.node);
                            step.body.append(R.rich(s.content));R.media(s.media,step.body);
                            if(s.conditions.length)step.body.append(R.el('p',`${s.conditions.length} voorwaarde(n); alle voorwaarden moeten vervuld zijn.`, 'small'));
                            step.body.append(R.group(R.button('Edit','fa-pen',()=>stepDialog(d,r,s)),R.button('Media toevoegen','fa-photo-film',()=>mediaDialog(s))));
                        });
                    });
                    if(!d.used)l.body.append(R.group(R.button('Feedbackvariant toevoegen','fa-plus',()=>routeDialog(d,level))));
                }
            });
            out.querySelectorAll('details').forEach(n=>{if(!n.dataset.key.startsWith('discovery-'))n.open=open.has(n.dataset.key);});
        });
    }
    function populateNavigation(c){
        const item=state.navigation.get(Number(c.id));if(!item)return;
        item.node.open=true;item.body.replaceChildren();
        const discoveries=R.el('div','','accordion research-discovery-navigation');item.body.append(discoveries);
        c.discoveries.forEach(d=>{
            const child=accordion(d.title,`discovery-nav-${d.id}`);child.node.dataset.discoveryId=d.id;
            const summary=child.node.querySelector('summary');summary.setAttribute('aria-current',String(Number(d.id)===state.discovery));
            child.node.open=Number(d.id)===state.discovery;
            child.body.append(R.el('span',Number(d.active)?'Actief':'Niet actief','small'));
            summary.addEventListener('click',event=>{
                event.preventDefault();state.discovery=Number(d.id);void loadChallenge();
            });
            discoveries.append(child.node);
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
        const title=R.input(discovery?.title||''),desc=R.editor(discovery?.description),skill=R.select(state.catalog.skills,discovery?.idSkill||state.catalog.skills[0]?.id),active=R.check('Actief (alle routes hebben minstens één stap)',Number(discovery?.active||0));
        if(discovery?.used)skill.disabled=true;
        body.append(R.field('Titel',title),R.field('Beschrijving',desc.wrapper),R.field('Vaardigheid',skill));if(discovery)body.append(active.box);
        body.append(saveButton(body,d,'discovery_save',()=>({id:Number(discovery?.id||0),...(discovery?{revision:Number(discovery.revision)}:{}),idChallenge:Number(state.detail.id),title:title.value,description:desc.getHtml(),idSkill:Number(skill.value),bonusIds:discovery?.bonusIds||[],active:active.input.checked})));
    });}
    function bonusDialog(discovery){R.dialog('Bonus-specialisaties',(body,d)=>{
        const choices=R.multi(state.catalog.specialisations.filter(s=>Number(s.idSkill)===Number(discovery.idSkill)),discovery.bonusIds);choices.disabled=discovery.used;
        body.append(R.el('p','Minstens één passende specialisatie geeft precies +1 niveau, nooit meer. Een verplichte specialisatie van een feedbackvariant geeft op zichzelf geen bonus.'),R.field('Specialisaties',choices));
        if(!discovery.used)body.append(saveButton(body,d,'discovery_save',()=>({id:Number(discovery.id),revision:Number(discovery.revision),idChallenge:Number(discovery.idChallenge),title:discovery.title,description:discovery.description,idSkill:Number(discovery.idSkill),bonusIds:R.ids(choices),active:!!Number(discovery.active)})));
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
        step.media.forEach(m=>{const row=R.el('div','','d-flex justify-content-between align-items-center gap-2 mt-3');row.append(R.el('span',m.filename),R.group(R.button('Verwijderen','fa-trash',()=>R.run(body,async()=>{await R.write('media_delete',{id:Number(m.id)});d.close();await loadChallenge();}))));body.append(row);});
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
