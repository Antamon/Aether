var aetherT = globalThis.aetherT || ((text, values = []) => text.replace(/\{(\d+)\}/g, (match, index) => values[index] === undefined ? match : String(values[index])));
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
                script.onload=resolve;script.onerror=()=>{script.remove();reject(new Error(aetherT("De PDF-module kon niet geladen worden. Herlaad de pagina en probeer opnieuw.")));};
                document.head.append(script);
            });
            if(typeof window.qrcode!=='function')await load('js/vendor/qrcode-generator.js');
            if(!window.jspdf?.jsPDF)await load('js/vendor/jspdf.umd.min.js');
            if(!window.AetherResearchQrPdf?.download)await load('js/researchQrPdf.js');
            if(!window.AetherResearchQrPdf?.download||!window.jspdf?.jsPDF||typeof window.qrcode!=='function')throw new Error(aetherT("De PDF-module is niet beschikbaar. Herlaad de pagina en probeer opnieuw."));
            return window.AetherResearchQrPdf;
        })().catch(error=>{pdfRequest=null;throw error;});
        return pdfRequest;
    }
    const names=[aetherT("Ongetraind"),aetherT("Beginner"),aetherT("Gevorderde"),aetherT("Meester"),'Specialist'];
    const accordion=(label,key)=>{const d=R.el('details','','accordion-item research-accordion');d.dataset.key=key;const b=R.el('div','','accordion-body');const summary=R.el('summary',label,'accordion-button collapsed');d.append(summary,b);d.addEventListener('toggle',()=>summary.classList.toggle('collapsed',!d.open));return {node:d,body:b};};
    const feedbackSection=(label,key,heading)=>{const node=R.el('section','','research-feedback-section'),body=R.el('div');node.dataset.key=key;node.append(R.el(heading,label),body);return {node,body};};
    const saveButton=(body,d,op,payload)=>R.group(R.button(aetherT("Opslaan"),'fa-check',()=>R.run(body,async()=>{const result=await R.write(op,payload());if(op==='challenge_save'){state.challenge=result.id;state.discovery=0;}if(op==='discovery_save')state.discovery=result.id;d.close();await render();})));
    async function render() {
        await R.run(host,async()=>{
            host.replaceChildren();
            const toolbar=R.el('div','','research-toolbar');
            const events=R.select(state.catalog.events,state.event,aetherT("Alle events"));toolbar.append(R.field('Event',events));
            events.addEventListener('change',()=>{state.event=Number(events.value);state.challenge=0;state.discovery=0;state.detail=null;void render();});
            if(state.tab==='challenges') {
                if(state.event)toolbar.append(R.group(R.button(aetherT("Eventtijdzone"),'fa-clock',()=>timezoneDialog())));
            }
            host.append(toolbar);
            const list=await R.read('challenges',{idEvent:state.event});state.challenges=list.challenges;
            if(state.tab==='progress'){await progress(toolbar);return;}
            const layout=R.el('div','','research-layout');const links=R.el('div','','research-list event-character-accordion');state.detailHost=R.el('div','','research-selection');layout.append(links,state.detailHost);host.append(layout);
            links.append(R.button(aetherT("Nieuwe challenge"),'fa-plus',()=>challengeDialog()));
            const navigation=R.el('div','','accordion research-navigation');links.append(navigation);state.navigation=new Map();
            list.challenges.forEach(c=>{
                const item=accordion(c.title,`challenge-nav-${c.id}`);item.node.dataset.challengeId=c.id;
                const summary=item.node.querySelector('summary');
                summary.addEventListener('click',event=>{
                    event.preventDefault();
                    if(item.node.open&&state.challenge===Number(c.id)&&!state.discovery){item.node.open=false;return;}
                    state.challenge=Number(c.id);state.discovery=0;
                    state.navigation.forEach((other,id)=>other.node.open=id===state.challenge);
                    item.body.replaceChildren(R.el('p',aetherT("Challenge laden…"),'small'));
                    void loadChallenge();
                });
                state.navigation.set(Number(c.id),item);navigation.append(item.node);
            });
            if(!list.challenges.length)links.append(R.el('p',aetherT("Nog geen challenges voor dit event.")));
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
                if(d.used)a.body.append(R.el('p',aetherT("Al gevonden: routes en voorwaarden liggen vast. Je kunt de inhoud nog bijwerken."),'small text-muted mt-2'));
                const filled=route=>route.steps.some(step=>R.rich(step.content).textContent.replace(/[\s\u00a0\u200b]/g,'')||(step.media||[]).length);
                const iconButton=(label,icon,action)=>{const b=R.button('',icon,action);b.className='data-info-button';b.title=label;b.setAttribute('aria-label',label);return b;};
                for(let level=0;level<=4;level++){
                    const section=feedbackSection(names[level],'level-'+d.id+'-'+level,'h4');a.body.append(section.node);
                    const routes=d.routes.filter(r=>Number(r.level)===level).sort((x,y)=>(x.kind!=='general')-(y.kind!=='general'));
                    const general=routes.find(r=>r.kind==='general');
                    const table=R.el('table','','table aether-table align-middle'),rows=R.el('tbody');table.append(rows);section.body.append(table);
                    routes.forEach(r=>{
                        const inherited=r.kind==='general'&&!filled(r);
                        const previous=inherited?d.routes.filter(o=>o.kind==='general'&&Number(o.level)<level&&filled(o)).sort((x,y)=>Number(y.level)-Number(x.level))[0]:null;
                        const steps=r.steps.length?r.steps:[null];
                        steps.forEach(step=>{
                            const row=R.el('tr'),kind=R.el('td'),content=R.el('td'),media=R.el('td'),edit=R.el('td');
                            if(step&&Number(step.position)>1){
                                const arrow=R.el('i','','fa-solid fa-arrow-right');arrow.title=aetherT("Vervolgstap");arrow.setAttribute('aria-label',arrow.title);kind.append(arrow);
                            }else if(r.kind!=='general'){
                                const icon=R.el('i','','fa-solid '+(r.kind==='character'?'fa-user':'fa-star'));
                                const labels=(r.characterIds||[]).map(id=>state.catalog.characters.find(c=>Number(c.id)===Number(id))?.name).filter(Boolean);
                                if(r.idRequiredSpecialisation)labels.push(state.catalog.specialisations.find(o=>Number(o.id)===Number(r.idRequiredSpecialisation))?.name||'');
                                icon.title=labels.join(', ');icon.setAttribute('aria-label',icon.title);kind.append(icon);
                            }
                            if(inherited)content.append(R.el('span',previous?aetherT("Niet gedefineerd. Gebruikt de feedback van ")+names[Number(previous.level)]+'.':'No additional information','text-muted'));
                            else if(step){content.append(R.rich(step.content));if(Number(step.confirmationRequired))content.append(R.el('small',aetherT("Bevestiging nodig")));}
                            else content.append(R.el('span',aetherT("Nog geen feedback"),'text-muted'));
                            if(step){const button=iconButton(aetherT("Media beheren"),'fa-photo-film',()=>mediaDialog(step));if((step.media||[]).length){button.className+=' research-has-media';button.title=aetherT("Media beheren (media gekoppeld)");button.setAttribute('aria-label',button.title);}media.append(button);}
                            if(step||!d.used)edit.append(iconButton(aetherT("Feedback bewerken"),'fa-pen-nib',()=>stepDialog(d,r,step)));
                            row.append(kind,content,media,edit);rows.append(row);
                        });
                    });
                    if(!d.used&&general&&filled(general)){const actions=R.el('div','','research-variant-action');actions.append(R.button(aetherT("Variant toevoegen"),'fa-plus',()=>routeDialog(d,level)));section.body.append(actions);}
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
            header.append(R.el('h3',`${d.title}${Number(d.active)?'':aetherT(" · niet actief")}`),R.group(R.button(aetherT("Edit"),'fa-pen-nib',edit)));host.append(header);
            host.append(R.el('p',d.progressionMode==='universal'?aetherT("Universele voortgang (gedeeld binnen dit event)"):aetherT("Persoonlijke voortgang")),R.rich(d.description),R.el('p',aetherT("Vaardigheid: {0}",[state.catalog.skills.find(s=>Number(s.id)===Number(d.idSkill))?.name||''])));
            const bonuses=(d.bonusIds||[]).map(id=>state.catalog.specialisations.find(s=>Number(s.id)===Number(id))?.name).filter(Boolean);
            if(bonuses.length){const box=R.el('div','','research-discovery-bonuses'),list=R.el('ul');box.append(R.el('strong',aetherT("Bonus-specialisaties")));bonuses.forEach(name=>list.append(R.el('li',name)));box.append(list);host.append(box);}
        };
        const edit=()=>{
            host.replaceChildren();
            const header=R.el('div','','research-discovery-header'),form=R.el('form'),fields=R.el('fieldset');
            const title=R.input(d.title),description=R.editor(d.description),skill=R.select(state.catalog.skills,d.idSkill),active=R.check(aetherT("Actief"),Number(d.active));
            title.required=true;title.maxLength=200;
            const progression=R.select([{id:'personal',name:aetherT("Persoonlijke voortgang")},{id:'universal',name:aetherT("Universele voortgang (per event)")}],d.progressionMode||'personal');progression.disabled=!!d.used;
            const conclusion=R.check(aetherT("Conclusie (zonder QR-code)"),Number(d.isConclusion||0));conclusion.input.disabled=!!d.used;
            const bonuses=R.multi(state.catalog.specialisations.filter(s=>Number(s.idSkill)===Number(d.idSkill)),d.bonusIds||[]);
            skill.disabled=!!d.used;bonuses.disabled=!!d.used;
            skill.addEventListener('change',()=>{
                const selected=R.ids(bonuses),options=R.multi(state.catalog.specialisations.filter(s=>Number(s.idSkill)===Number(skill.value)),selected);
                bonuses.replaceChildren(...options.options);bonuses.size=options.size;
            });
            const save=R.button(aetherT("Opslaan"),'fa-check',()=>void commit()),cancel=R.button(aetherT("Annuleren"),'fa-xmark',view);
            const commit=()=>R.run(host,async()=>{
                if(!form.reportValidity())return;
                save.disabled=true;cancel.disabled=true;fields.disabled=true;description.editor.contentEditable='false';
                try{
                    await R.write('discovery_save',{id:Number(d.id),revision:Number(d.revision),idChallenge:Number(c.id),title:title.value,description:description.getHtml(),idSkill:Number(skill.value),bonusIds:R.ids(bonuses),active:active.input.checked,progressionMode:progression.value,isConclusion:conclusion.input.checked});
                    await loadChallenge();
                }finally{save.disabled=false;cancel.disabled=false;fields.disabled=false;description.editor.contentEditable='true';}
            });
            header.append(R.el('h3',aetherT("Discovery bewerken")),R.group(save,cancel));
            fields.append(R.field(aetherT("Titel"),title),R.field(aetherT("Beschrijving"),description.wrapper),R.field(aetherT("Vaardigheid"),skill),R.field(aetherT("Bonus-specialisaties (optioneel)"),bonuses),conclusion.box,R.field(aetherT("Voortgang"),progression),active.box);
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
        if(!Number(c.active))item.body.append(R.el('p',aetherT("Challenge niet actief"),'text-muted'));
        const discoveries=R.el('div','','table-responsive research-discovery-navigation');item.body.append(discoveries);
        const table=R.el('table','','table aether-table align-middle mb-0 research-discovery-table');
        const caption=R.el('caption','Discoveries','visually-hidden');
        const rows=R.el('tbody');table.append(caption,rows);discoveries.append(table);
        c.discoveries.forEach(d=>{
            const row=R.el('tr');row.dataset.discoveryId=d.id;
            row.classList.toggle('is-selected',Number(d.id)===state.discovery);
            const name=R.el('td',d.title),cell=R.el('td','','research-view-column');
            if(Number(d.isConclusion))name.append(R.el('span',aetherT("Conclusie"),'badge text-bg-secondary ms-2'));
            const view=R.el('button','','data-info-button');view.type='button';view.title=aetherT("{0} bekijken",[d.title]);view.setAttribute('aria-label',view.title);
            if(Number(d.id)===state.discovery)view.setAttribute('aria-current','true');
            const icon=R.el('i','','fa-solid fa-eye');icon.setAttribute('aria-hidden','true');view.append(icon);
            view.addEventListener('click',()=>{state.discovery=Number(d.id);void loadChallenge(true);});
            cell.append(view);row.append(name,cell);rows.append(row);
        });
        if(!c.discoveries.length)item.body.append(R.el('p',aetherT("Nog geen discoveries."),'small'));
        const qr=R.button('QR-code','fa-qrcode',()=>R.run(item.body,async()=>{
            qr.disabled=true;
            try{const pdf=await pdfExporter();await pdf.download(c,state.catalog.skills);}finally{qr.disabled=false;}
        }));
        item.body.append(R.group(R.button(aetherT("Edit"),'fa-pen-nib',()=>challengeDialog(c)),R.button(aetherT("Discovery toevoegen"),'fa-plus',()=>discoveryDialog()),qr));
    }
    function challengeDialog(c=null){R.dialog(c?aetherT("Challenge bewerken"):aetherT("Nieuwe challenge"),(body,d)=>{
        const title=R.input(c?.title||''),description=R.editor(c?.description),events=R.multi(state.catalog.events,c?.eventIds||(state.event?[state.event]:[])),active=R.check(aetherT("Actief"),c?Number(c.active):true);
        body.append(R.field(aetherT("Titel"),title),R.field(aetherT("Beschrijving"),description.wrapper),R.field(aetherT("Events (meerdere mogelijk)"),events));if(c)body.append(active.box);
        body.append(saveButton(body,d,'challenge_save',()=>({id:Number(c?.id||0),...(c?{revision:Number(c.revision)}:{}),title:title.value,description:description.getHtml(),eventIds:R.ids(events),active:active.input.checked})));
    });}
    function discoveryDialog(discovery=null){R.dialog(discovery?aetherT("Discovery bewerken"):aetherT("Nieuwe discovery"),(body,d)=>{
        const title=R.input(discovery?.title||''),desc=R.editor(discovery?.description),skill=R.select(state.catalog.skills,discovery?.idSkill||state.catalog.skills[0]?.id),active=R.check(aetherT("Actief"),discovery?Number(discovery.active):true);
        const progression=R.select([{id:'personal',name:aetherT("Persoonlijke voortgang")},{id:'universal',name:aetherT("Universele voortgang (per event)")}],discovery?.progressionMode||'personal');
        const conclusion=R.check(aetherT("Conclusie (zonder QR-code)"),Number(discovery?.isConclusion||0));conclusion.input.disabled=!!discovery;
        if(discovery?.used)skill.disabled=true;
        body.append(R.field(aetherT("Titel"),title),R.field(aetherT("Beschrijving"),desc.wrapper),R.field(aetherT("Vaardigheid"),skill));body.append(conclusion.box,R.field(aetherT("Voortgang"),progression),active.box);
        if(!discovery)body.append(R.el('p',aetherT("Ongetraind begint met “No additional information”. Lege hogere niveaus gebruiken de feedback van het dichtstbijzijnde ingevulde lagere niveau."),'small'));
        body.append(saveButton(body,d,'discovery_save',()=>({id:Number(discovery?.id||0),...(discovery?{revision:Number(discovery.revision)}:{}),idChallenge:Number(state.detail.id),title:title.value,description:desc.getHtml(),idSkill:Number(skill.value),bonusIds:discovery?.bonusIds||[],active:active.input.checked,progressionMode:progression.value,isConclusion:conclusion.input.checked})));
    });}
    function variantFields(body,discovery,level,route=null){
        const kind=R.select([{id:'specialisation',name:aetherT("Specialisatievariant")},{id:'character',name:aetherT("Personagevariant")}],route?.kind||'specialisation');
        const spec=R.select(state.catalog.specialisations.filter(s=>Number(s.idSkill)===Number(discovery.idSkill)),route?.idRequiredSpecialisation||'',aetherT("Geen vereiste specialisatie")),chars=R.multi(state.catalog.characters,route?.characterIds||[]);
        const charsField=R.field(aetherT("Personages"),chars);
        const visibility=()=>{charsField.hidden=kind.value!=='character';};kind.addEventListener('change',visibility);visibility();
        body.append(R.field('Variant',kind),R.field(aetherT("Verplichte specialisatie (geen niveaubonus)"),spec),charsField);
        return ()=>({id:Number(route?.id||0),...(route?{revision:Number(route.revision)}:{}),idDiscovery:Number(discovery.id),level,kind:kind.value,idRequiredSpecialisation:Number(spec.value),characterIds:kind.value==='character'?R.ids(chars):[]});
    }
    function routeDialog(discovery,level){R.dialog(aetherT("Feedbackvariant"),(body,d)=>{
        const readVariant=variantFields(body,discovery,level);
        body.append(saveButton(body,d,'route_save',readVariant));
    });}
    function stepDialog(discovery,route,step=null){R.dialog(step?aetherT("Stap {0} bewerken",[step.position]):aetherT("Volgende feedbackstap"),(body,d)=>{
        const readVariant=route.kind!=='general'&&!discovery.used?variantFields(body,discovery,Number(route.level),route):null;
        const originalVariant=readVariant?JSON.stringify(readVariant()):null;
        const text=R.editor(step?.content),confirm=R.check(aetherT("Bevestiging nodig"),Number(step?.confirmationRequired||0));confirm.input.disabled=!!discovery.used;
        const hasNext=!!step&&route.steps.some(s=>Number(s.position)>Number(step.position));
        const followUp=R.check(aetherT("Vervolgstap toevoegen"),hasNext);followUp.input.disabled=!!discovery.used||hasNext;
        body.append(R.field('Feedback',text.wrapper),confirm.box,R.el('p',aetherT("Door deze optie aan te vinken, vraag je de deelnemer zelf aan te geven wanneer deze stap is afgerond."),'small'),followUp.box,
            R.el('p',aetherT("Door deze optie aan te vinken zal er na deze stap een nieuwe stap toegevoegd worden. Die zal zichtbaar zijn door de huidige stap af te werken (de gebruiker drukt op \"stap afronden\" of na verloop van tijd, door een aftelklok/datum in te stellen)."),'small'));
        const saved=(step?.conditions||[]).find(c=>c.target==='transition');
        const timing=R.el('fieldset'),legend=R.el('legend',aetherT("Tijd instellen"),'h5');timing.disabled=!!discovery.used;timing.append(legend);body.append(timing);
        const group=R.el('div','','d-flex flex-wrap gap-3'),inputs={};let type=saved?.type||'none';
        const radioName='research-time-'+(step?.id||route.id);
        for(const [value,label] of [['none',aetherT("Geen tijd")],['duration','Timer'],['absolute',aetherT("Vast tijdstip")]]){
            const wrapper=R.el('label','','form-check'),input=R.input(value,'radio');input.name=radioName;input.className='form-check-input';input.checked=type===value;inputs[value]=input;wrapper.append(input,document.createTextNode(label));group.append(wrapper);
            input.addEventListener('change',()=>{type=value;update();});
        }
        timing.append(group);
        const duration=R.el('div','','research-grid'),units={};let seconds=Number(saved?.seconds||0);
        for(const [key,label,unit,max] of [['days',aetherT("Dagen"),86400,365],['hours',aetherT("Uren"),3600,23],['minutes',aetherT("Minuten"),60,59],['seconds',aetherT("Seconden"),1,59]]){
            const value=Math.floor(seconds/unit);seconds%=unit;const input=R.input(value,'number');input.min=0;input.max=max;input.step=1;units[key]=input;duration.append(R.field(label,input));
        }
        const absolute=R.el('div'),dates={};
        (state.detail.eventIds||[]).forEach(id=>{const event=state.catalog.events.find(e=>Number(e.id)===Number(id));const input=R.input(saved?.times?.[id]||'','datetime-local');dates[id]=input;absolute.append(R.field(event.title+' · '+(event.timeZone||'Europe/Brussels'),input));});
        const countdown=R.check(aetherT("Countdown tonen"),saved?.showCountdown??true);timing.append(duration,absolute,countdown.box);
        function update(){timing.hidden=!followUp.input.checked;duration.hidden=type!=='duration';absolute.hidden=type!=='absolute';countdown.box.hidden=type==='none';}
        followUp.input.addEventListener('change',update);update();
        const actions=saveButton(body,d,'step_save',()=>{
            if(followUp.input.checked&&type==='none'&&!confirm.input.checked)throw new Error(aetherT("Vink Bevestiging nodig aan voor een vervolgstap zonder tijd."));
            const timingValue={type,showCountdown:countdown.input.checked};
            if(type==='duration'){
                for(const input of Object.values(units))if(!input.checkValidity())throw new Error(aetherT("Vul geldige dagen, uren, minuten en seconden in."));
                timingValue.seconds=Number(units.days.value)*86400+Number(units.hours.value)*3600+Number(units.minutes.value)*60+Number(units.seconds.value);
                if(followUp.input.checked&&timingValue.seconds<=0)throw new Error(aetherT("Stel een timer van minstens één seconde in."));
            }
            if(type==='absolute')timingValue.times=Object.fromEntries(Object.entries(dates).map(([id,input])=>[id,input.value]));
            return {id:Number(step?.id||0),...(step?{revision:Number(step.revision)}:{}),idRoute:Number(route.id),...(readVariant&&JSON.stringify(readVariant())!==originalVariant?{variant:readVariant()}:{}),content:text.getHtml(),confirmationRequired:confirm.input.checked,followUp:followUp.input.checked,conditions:(step?.conditions||[]).map(c=>{const clean={...c};delete clean.utc;return clean;}),timing:timingValue};
        });
        const previous=step?route.steps.filter(s=>Number(s.position)<Number(step.position)).sort((a,b)=>Number(b.position)-Number(a.position))[0]:null;
        if(step&&!discovery.used){
            actions.append(R.button(aetherT("Stap verwijderen"),'fa-trash',()=>{
                const notice=R.el('div','','alert alert-warning');
                notice.append(R.el('p',previous?aetherT("Deze stap en eventuele volgende stappen worden verwijderd. De vorige stap krijgt geen vervolg meer; haar timers en datums worden gewist."):aetherT("Deze feedback en eventuele vervolgstappen worden verwijderd.")+(route.kind==='general'&&Number(route.level)===0?aetherT(" Ongetraind krijgt opnieuw de standaardtekst “No additional information”."):'')));
                notice.append(R.group(R.button(aetherT("Verwijderen bevestigen"),'fa-trash',()=>R.run(notice,async()=>{
                    await R.write('step_delete',{id:Number(step.id),revision:Number(step.revision),...(previous?{previousRevision:Number(previous.revision)}:{})});d.close();await loadChallenge();
                })),R.button(aetherT("Terug"),'fa-arrow-left',()=>notice.remove())));
                body.querySelector('[data-delete-confirmation]')?.remove();notice.dataset.deleteConfirmation='true';body.append(notice);
            }));
        }
        body.append(actions);
    });}
    function mediaDialog(step){R.dialog(aetherT("Media bij feedback"),(body,d)=>{
        body.append(R.el('p',aetherT("Afbeelding, geluid of video, maximaal 8 MB per bestand. Media wordt alleen na een toegangscontrole aangeboden.")));
        const file=R.input('','file');file.accept='image/jpeg,image/png,image/webp,audio/mpeg,audio/ogg,audio/wav,video/mp4,video/webm,video/ogg';body.append(R.field(aetherT("Bestand"),file));
        body.append(R.group(R.button(aetherT("Uploaden"),'fa-upload',()=>R.run(body,async()=>{if(!file.files[0])throw new Error(aetherT("Kies eerst een bestand."));const data=new FormData();data.append('idStep',step.id);data.append('file',file.files[0]);const response=await fetch('api/research/upload.php',{method:'POST',headers:{'X-CSRF-Token':window.AETHER_CSRF_TOKEN},body:data});const result=await response.json();if(!response.ok)throw new Error(result.error);d.close();await loadChallenge();}))));
        R.media(step.media,body);
        step.media.forEach(m=>{const row=R.el('div','','d-flex justify-content-between align-items-center gap-2 mt-3');row.append(R.el('span',m.filename),R.group(R.button(aetherT("Verwijderen"),'fa-trash',()=>R.run(body,async()=>{await R.write('media_delete',{id:Number(m.id)});d.close();await loadChallenge();}))));body.append(row);});
        const library=R.el('div');body.append(library);
        body.append(R.button(aetherT("Media uit andere discoveries"),'fa-photo-film',()=>R.run(body,async()=>{
            const data=await R.read('media_library',{idStep:Number(step.id)});library.replaceChildren();
            data.media.forEach(m=>{const row=R.el('div','','mt-3');row.append(R.el('span',m.discoveryTitle+' · '+m.filename),R.button(aetherT("Koppelen"),'fa-link',()=>R.run(body,async()=>{await R.write('media_link',{idStep:Number(step.id),idMedia:Number(m.id)});d.close();await loadChallenge();})));R.media([m],row);library.append(row);});
            if(!data.media.length)library.append(R.el('p',aetherT("Geen andere media beschikbaar.")));
        })));

    });}
    function timezoneDialog(){const event=state.catalog.events.find(e=>Number(e.id)===state.event);R.dialog(aetherT("Tijdzone · {0}",[event.title]),(body,d)=>{
        const zone=R.select(state.catalog.timeZones.map(z=>({id:z,name:z})),event.timeZone);body.append(R.field(aetherT("Tijdzone van het event"),zone));body.append(R.group(R.button(aetherT("Opslaan"),'fa-check',()=>R.run(body,async()=>{await R.write('timezone_save',{id:state.event,timeZone:zone.value});state.catalog=await R.read('catalog');d.close();await render();}))));
    });}
    async function progress(toolbar){
        const challenge=R.select(state.challenges,state.challenge,aetherT("Alle challenges")),discovery=R.select([],0,aetherT("Alle discoveries")),status=R.select(Object.entries(R.statuses).map(([id,name])=>({id,name})),state.status||'',aetherT("Alle statussen"));
        toolbar.append(R.field('Challenge',challenge),R.field('Discovery',discovery),R.field('Status',status));const content=R.el('div','','research-progress');host.append(content);
        const load=()=>R.run(content,async()=>{
            const data=await R.read('progress',{idEvent:state.event,idChallenge:Number(challenge.value),idDiscovery:Number(discovery.value),status:status.value});content.replaceChildren();
            if(!data.progress.length){content.append(R.el('p',aetherT("Geen gevonden discoveries voor deze selectie.")));return;}
            const table=R.el('table','','table');const head=R.el('thead'),tr=R.el('tr');[aetherT("Personage"),'Event / challenge','Discovery','Status',''].forEach(t=>tr.append(R.el('th',t)));head.append(tr);table.append(head);const tbody=R.el('tbody');table.append(tbody);content.append(table);
            data.progress.forEach(p=>{const row=R.el('tr');[p.characterName,`${p.eventTitle} · ${p.challengeTitle}`,p.discoveryTitle,R.statuses[p.status]].forEach(t=>row.append(R.el('td',t)));const cell=R.el('td');const icon=R.el('button','','research-icon');icon.type='button';icon.title=aetherT("Voortgang van dit personage");icon.setAttribute('aria-label',icon.title);icon.innerHTML='<i class="fa-solid fa-user" aria-hidden="true"></i>';icon.addEventListener('click',()=>progressDialog(p,load));cell.append(icon);row.append(cell);tbody.append(row);});
        });
        const choose=async()=>{state.challenge=Number(challenge.value);const data=state.challenge?await R.read('challenge',{idChallenge:state.challenge}):{discoveries:[]};discovery.replaceChildren(...R.select(data.discoveries,0,aetherT("Alle discoveries")).options);await load();};
        challenge.addEventListener('change',()=>R.run(content,choose));discovery.addEventListener('change',load);status.addEventListener('change',()=>{state.status=status.value;void load();});await choose();
    }
    function progressDialog(p,reload){R.dialog(`${p.characterName} · ${p.discoveryTitle}`,(body,d)=>{
        const link=R.el('a',aetherT("Personage openen"),'research-icon');link.href=`index.html?character=${Number(p.idCharacter)}&tab=actions`;body.append(link,R.el('p',aetherT("{0} · gevonden {1} UTC · effectief niveau {2}",[p.eventTitle,p.foundAt,p.effectiveLevel])));
        p.steps.forEach(s=>{const card=R.el('div','','research-step');card.append(R.el('h4',aetherT("Stap {0}",[s.position])),R.el('p',aetherT("Getoond: {0} · Afgerond: {1} · Bevestigd: {2} (UTC)",[s.shownAt||'nee',s.completedAt||'nee',s.confirmedAt||'nee'])));
            if(s.sharedCompletedAt)card.append(R.el('p',aetherT("Universeel afgerond: {0} UTC · personage {1}",[s.sharedCompletedAt,s.sharedCompletedBy])));
            if(!s.completedAt&&!s.sharedCompletedAt){const reason=R.input();card.append(R.field(aetherT("Reden voor handmatige afronding"),reason),R.group(R.button(aetherT("Afronden als spelleider"),'fa-check',()=>R.run(card,async()=>{await R.write('progress_complete',{idEvent:Number(p.idEvent),idCharacter:Number(p.idCharacter),idDiscovery:Number(p.idDiscovery),idStep:Number(s.id),reason:reason.value});d.close();await reload();}))));}body.append(card);});
        p.timers.forEach(t=>{const card=R.el('div','','research-step');card.append(R.el('h4',aetherT("Klok · stap {0}",[t.position])),R.el('p',aetherT("{0} · gestart door {1} {2} · {3} UTC · eindigt {4} UTC",[Number(t.scopeCharacter)===0?'Gedeeld':'Per personage',t.firstName,t.lastName,t.startedAt,t.endsAt])));
            const end=R.input('','datetime-local'),reason=R.input();const zone=state.catalog.events.find(e=>Number(e.id)===Number(p.idEvent))?.timeZone;
            card.append(R.field(aetherT("Nieuw eindtijdstip ({0})",[zone]),end),R.field(aetherT("Reden"),reason),R.group(R.button(aetherT("Klok aanpassen"),'fa-clock',()=>R.run(card,async()=>{await R.write('timer_adjust',{idEvent:Number(p.idEvent),idStep:Number(t.idStep),scopeCharacter:Number(t.scopeCharacter),localEnd:end.value,reason:reason.value});d.close();await reload();}))));body.append(card);});
    });}
    document.querySelectorAll('[data-research-tab]').forEach(b=>b.addEventListener('click',()=>{state.tab=b.dataset.researchTab;document.querySelectorAll('[data-research-tab]').forEach(n=>n.classList.toggle('active',n===b));void render();}));
    void R.run(host,async()=>{const user=await aetherGetCurrentUser();if(!userHasPrivilegedRole(user)){host.replaceChildren(R.el('p',aetherT("Alleen admins en directors kunnen Onderzoek beheren.")));return;}state.catalog=await R.read('catalog');await render();});
})();
