const fs=require('fs');let p='js/researchAdmin.js',s=fs.readFileSync(p,'utf8');
s=s.replace("const bonuses=R.multi(state.catalog", "const progression=R.select([{id:'personal',name:'Persoonlijke voortgang'},{id:'universal',name:'Universele voortgang (per event)'}],d.progressionMode||'personal');progression.disabled=!!d.used;\n            const bonuses=R.multi(state.catalog");
s=s.replace('bonusIds:R.ids(bonuses),active:active.input.checked','bonusIds:R.ids(bonuses),active:active.input.checked,progressionMode:progression.value');
s=s.replace("R.field('Bonus-specialisaties (optioneel)',bonuses),active.box", "R.field('Bonus-specialisaties (optioneel)',bonuses),R.field('Voortgang',progression),active.box");
s=s.replace("host.append(R.rich(d.description),", "host.append(R.el('p',d.progressionMode==='universal'?'Universele voortgang (gedeeld binnen dit event)':'Persoonlijke voortgang'),R.rich(d.description),");
s=s.replace("        if(discovery?.used)skill.disabled=true;", "        const progression=R.select([{id:'personal',name:'Persoonlijke voortgang'},{id:'universal',name:'Universele voortgang (per event)'}],discovery?.progressionMode||'personal');\n        if(discovery?.used)skill.disabled=true;");
s=s.replace('body.append(active.box);','body.append(R.field(\'Voortgang\',progression),active.box);');
s=s.replace('bonusIds:discovery?.bonusIds||[],active:active.input.checked','bonusIds:discovery?.bonusIds||[],active:active.input.checked,progressionMode:progression.value');
// Condition target applies to the step being edited, not an implicit next step.
s=s.replace("        if(c.type==='discoveries'){", "        const target=R.select([{id:'visibility',name:'Feedback zichtbaar maken'},{id:'completion',name:'Knop Afgerond beschikbaar maken'}],c.target||'visibility');\n        if(['duration','absolute'].includes(c.type))inner.append(R.field('Na het verstrijken van de tijd',target));\n        if(c.type==='discoveries'){");
s=s.replace("(!step||Number(s.position)<Number(step.position)));", "(!step||Number(s.position)<Number(step.position)||(target.value==='completion'&&action.value==='shown'&&Number(s.id)===Number(step.id))));");
s=s.replace("source.addEventListener('change',update);", "target.addEventListener('change',update);source.addEventListener('change',update);");
s=s.replace("        return {node,read:locked?", "        const originalRead=read;read=()=>({...originalRead(),...(['duration','absolute'].includes(c.type)?{target:target.value}:{})});\n        return {node,read:locked?");
fs.writeFileSync(p,s);
p='js/researchCharacter.js';s=fs.readFileSync(p,'utf8').replace("R.button('Opdracht uitgevoerd'","R.button('Afgerond'").replace('confirm.disabled=!step.shownAt','confirm.disabled=!step.shownAt||step.canConfirm===false');fs.writeFileSync(p,s);
