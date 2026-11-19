const fs=require('fs');let p='js/adminFunctions.js',s=fs.readFileSync(p,'utf8');
s=s.replace('    if(adminSkillSaving||!adminSkillEditing||Number(currentAdminSkill?.idSkill||0)>0)return;','    if(adminSkillSaving||!adminSkillEditing)return;');
s=s.replace("    if(!adminSkillEditing){adminSkillEditing=true;applyAdminSkillFormEditability(currentAdminSkill);return;}","    if(!adminSkillEditing){adminSkillBeforeDraft=JSON.parse(JSON.stringify(currentAdminSkill));adminSkillEditing=true;applyAdminSkillFormEditability(currentAdminSkill);return;}");
s=s.replace('Opslaan en bekijken</','Opslaan</');
s=s.replace("cancel.hidden=!editing||Number(skill?.idSkill||0)>0;", "cancel.hidden=!editing;");
s=s.replace('    renderAdminSkillView(skill);',"    mode.classList.toggle('admin-skill-view-edit',!editing);\n    const subtitle=document.getElementById('adminSkillSubtitle');if(subtitle)subtitle.hidden=!!skill;\n    renderAdminSkillView(skill);");
s=s.replace('"Gebruik Edit om wijzigingen aan te brengen en Opslaan en bekijken om te bewaren."','""');
let a=s.indexOf("    for(const [label,value] of [['Beschrijving'",s.indexOf('function renderAdminSkillView')),b=s.indexOf('\n}\nfunction addAdminSkillOption',a);
s=s.slice(0,a)+`    const line=(label,items)=>{const row=document.createElement('p'),name=document.createElement('strong');name.textContent=label+': ';row.append(name,document.createTextNode(items.length?items.join(', '):'Geen'));view.append(row);};
    line('Categorie',adminSkillTypeOptions.filter(c=>skill.categoryIds.includes(Number(c.idSkillType))).map(c=>c.name).concat(skill.isSecret?['Geheim']:[]));
    const disciplines=skill.specialisations.filter(s=>s.kind==='discipline').map(s=>s.name);
    if(disciplines.length||getDefaultAdminSpecialisationKind(skill)==='discipline')line('Discipline',disciplines);
    line('Specialisaties',skill.specialisations.filter(s=>s.kind==='specialisation').map(s=>s.name));
    const description=document.createElement('p');description.className='admin-skill-view-text';description.textContent=skill.description||'—';view.append(description);
    const table=document.createElement('table');table.className='table aether-table admin-skill-levels';table.setAttribute('aria-label','Bekwaamheidsniveaus');
    const body=document.createElement('tbody');
    for(const [label,value] of [['Beginneling',skill.beginner],['Deskundige',skill.professional],['Meester',skill.master]]){
        const row=document.createElement('tr'),name=document.createElement('td'),text=document.createElement('td');name.textContent=label;text.className='admin-skill-view-text';text.textContent=value||'—';row.append(name,text);body.append(row);
    }
    table.append(body);view.append(table);`+s.slice(b);
s=s.replace('        groupEl.className = "admin-holder-group";',"        if(container.children.length)container.append(document.createElement('hr'));\n        groupEl.className = \"admin-holder-group\";");
fs.writeFileSync(p,s);
