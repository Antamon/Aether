const fs=require('fs');const file='js/researchAdmin.js';let s=fs.readFileSync(file,'utf8');
const start=s.indexOf('                for(let level=0;level<=4;level++){');const end=s.indexOf('\n            });',start);
s=s.slice(0,start)+`                const filled=route=>route.steps.some(step=>R.rich(step.content).textContent.replace(/[\\s\\u00a0\\u200b]/g,'')||(step.media||[]).length);
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
                }`+s.slice(end);
s=s.replace("        step.media.forEach(m=>{", "        R.media(step.media,body);\n        step.media.forEach(m=>{");
const at=s.indexOf('\n    });}\n    function timezoneDialog',s.indexOf('    function mediaDialog'));
s=s.slice(0,at)+`
        const library=R.el('div');body.append(library);
        body.append(R.button('Media uit andere discoveries','fa-photo-film',()=>R.run(body,async()=>{
            const data=await R.read('media_library',{idStep:Number(step.id)});library.replaceChildren();
            data.media.forEach(m=>{const row=R.el('div','','mt-3');row.append(R.el('span',m.discoveryTitle+' · '+m.filename),R.button('Koppelen','fa-link',()=>R.run(body,async()=>{await R.write('media_link',{idStep:Number(step.id),idMedia:Number(m.id)});d.close();await loadChallenge();})));R.media([m],row);library.append(row);});
            if(!data.media.length)library.append(R.el('p','Geen andere media beschikbaar.'));
        })));
`+s.slice(at);fs.writeFileSync(file,s);
