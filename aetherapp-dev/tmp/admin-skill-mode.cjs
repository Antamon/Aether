const fs=require('fs');let p='admin.html',s=fs.readFileSync(p,'utf8');const a=s.indexOf('<section id="adminSkillsSection">'),b=s.indexOf('</section>',a);
let section=s.slice(a,b).replace('col-lg-9 col-12','col-lg-8 col-12').replace('col-lg-3 col-12','col-lg-4 col-12');
section=section.replace('                            <form id="adminSkillForm"',`                            <div class="button-group mb-3"><button type="button" class="action-button" id="adminSkillModeButton">Edit</button></div>
                            <div id="adminSkillView"></div>
                            <form id="adminSkillForm"`);
section=section.replace('Nieuwe, gewijzigde en verwijderde specialisaties worden automatisch bewaard.','Wijzigingen worden bewaard wanneer u naar de kijkmodus terugkeert.');
section=section.replace('                                    <div class="col-12">\n                                        <div class="admin-specialisations-header">\n                                            <div>\n                                                <label class="form-label mb-1">Specialisaties</label>',`                                    <div class="col-12" id="adminDisciplineBlock" hidden>
                                        <div class="admin-specialisations-header"><label class="form-label">Disciplines</label><button type="button" class="action-button" id="adminAddDisciplineButton">Discipline toevoegen</button></div>
                                        <div class="admin-specialisations-list" id="adminSkillDisciplines"></div>
                                    </div>
                                    <div class="col-12">
                                        <div class="admin-specialisations-header">
                                            <div>
                                                <label class="form-label mb-1">Specialisaties</label>`);
s=s.slice(0,a)+section+s.slice(b);fs.writeFileSync(p,s);
p='js/adminFunctions.js';s=fs.readFileSync(p,'utf8');s='let adminSkillEditing = false;\nlet adminSkillSaving = false;\n'+s;
s=s.replace('    const form = document.getElementById("adminSkillForm");','    document.getElementById("adminSkillModeButton")?.addEventListener("click",()=>void toggleAdminSkillMode());\n    const form = document.getElementById("adminSkillForm");');
let start=s.indexOf('    const addSpecialisationButton = document.getElementById("adminAddSpecialisationButton");'),end=s.indexOf('    const specialisationsContainer',start);
s=s.slice(0,start)+`    document.getElementById('adminAddSpecialisationButton')?.addEventListener('click',()=>addAdminSkillOption('specialisation'));
    document.getElementById('adminAddDisciplineButton')?.addEventListener('click',()=>addAdminSkillOption('discipline'));
`+s.slice(end);
s=s.replace('const specialisationsContainer = document.getElementById("adminSkillSpecialisations");','const specialisationsContainer = document.getElementById("adminSkillForm");');
s=s.replace('            void ensureAdminSkillCreated(true);','            // New skills are created only when leaving edit mode.');
s=s.replace('        populateAdminSkillForm(skill);','        adminSkillEditing = false;\n        populateAdminSkillForm(skill);');
s=s.replace('function startCreatingAdminSkill() {','function startCreatingAdminSkill() {\n    adminSkillEditing = true;');
s=s.replace('    currentAdminSkill = skill ? normalizeAdminSkillState(skill) : null;', '    currentAdminSkill = skill ? normalizeAdminSkillState(skill) : null;');
s=s.replace('"Wijzigingen worden automatisch bewaard zodra u klaar bent met bewerken."','"Gebruik Edit om wijzigingen aan te brengen en Opslaan en bekijken om te bewaren."').replace('"Geef eerst een naam op. Zodra die naam ingevuld is, wordt de vaardigheid aangemaakt en kunt u de overige velden invullen."','"Vul de vaardigheid in en kies Opslaan en bekijken om te bewaren."');
start=s.indexOf('function applyAdminSkillFormEditability(');end=s.indexOf('function renderAdminCategories(',start);
s=s.slice(0,start)+`function applyAdminSkillFormEditability(skill) {
    const editing=!!skill&&adminSkillEditing;
    document.getElementById('adminSkillForm').hidden=!editing;
    document.getElementById('adminSkillView').hidden=editing;
    document.querySelectorAll('#adminSkillForm input,#adminSkillForm textarea,#adminSkillForm button').forEach(n=>n.disabled=!editing||adminSkillSaving);
    const addCategory=document.getElementById('adminAddCategoryButton');if(addCategory)addCategory.disabled=!editing||adminSkillSaving||!adminUserCanManageSkillTypes();
    const mode=document.getElementById('adminSkillModeButton');mode.disabled=!skill||adminSkillSaving;mode.innerHTML=editing?'<i class="fa-solid fa-check" aria-hidden="true"></i> Opslaan en bekijken':'<i class="fa-solid fa-pen-nib" aria-hidden="true"></i> Edit';
    document.getElementById('adminSkillSelect').disabled=editing;
    document.getElementById('adminNewSkillButton').disabled=editing;
    renderAdminSkillView(skill);
}
function renderAdminSkillView(skill){
    const view=document.getElementById('adminSkillView');view.replaceChildren();if(!skill)return;
    for(const [label,value] of [['Beschrijving',skill.description],['Niveau 1',skill.beginner],['Niveau 2',skill.professional],['Niveau 3',skill.master]]){
        const block=document.createElement('section'),heading=document.createElement('h3'),text=document.createElement('p');heading.className='h5';heading.textContent=label;text.className='admin-skill-view-text';text.textContent=value||'—';block.append(heading,text);view.append(block);
    }
    const categories=document.createElement('p');categories.textContent='Categorieën: '+adminSkillTypeOptions.filter(c=>skill.categoryIds.includes(Number(c.idSkillType))).map(c=>c.name).concat(skill.isSecret?['Geheim']:[]).join(', ');view.append(categories);
    for(const [kind,title] of [['discipline','Disciplines'],['specialisation','Specialisaties']]){
        const options=skill.specialisations.filter(s=>s.kind===kind);if(kind==='discipline'&&!options.length&&getDefaultAdminSpecialisationKind(skill)!=='discipline')continue;
        const heading=document.createElement('h3');heading.className='h5';heading.textContent=title;view.append(heading);
        const list=document.createElement('ul');options.forEach(s=>{const item=document.createElement('li');item.textContent=s.name;list.append(item);});if(!options.length){const item=document.createElement('li');item.textContent='Geen';list.append(item);}view.append(list);
    }
}
function addAdminSkillOption(kind){
    if(!currentAdminSkill||!adminSkillEditing||adminSkillSaving)return;
    currentAdminSkill.specialisations.push({idSkillSpecialisation:0,name:'',kind,clientKey:createAdminSpecialisationClientKey()});renderAdminSpecialisations(currentAdminSkill);markAdminSkillAutoSavePending();
    const target=document.getElementById(kind==='discipline'?'adminSkillDisciplines':'adminSkillSpecialisations');target.querySelector('.admin-specialisation-row:last-child input')?.focus();
}
async function toggleAdminSkillMode(){
    if(!currentAdminSkill||adminSkillSaving)return;
    if(!adminSkillEditing){adminSkillEditing=true;applyAdminSkillFormEditability(currentAdminSkill);return;}
    adminSkillSaving=true;applyAdminSkillFormEditability(currentAdminSkill);
    try{if(await saveAdminSkill()){adminSkillEditing=false;adminSkillAutoSavePending=false;}}
    finally{adminSkillSaving=false;applyAdminSkillFormEditability(currentAdminSkill);}
}

`+s.slice(end);
// Split rendering without changing IDs of existing options.
s=s.replace('function renderAdminSpecialisations(skill) {','function renderAdminSpecialisations(skill) {\n    const discipline=!!skill&&(getDefaultAdminSpecialisationKind(skill)===\'discipline\'||skill.specialisations.some(s=>s.kind===\'discipline\'));document.getElementById(\'adminDisciplineBlock\').hidden=!discipline;\n    renderAdminSkillOptions(skill,\'discipline\',\'adminSkillDisciplines\');renderAdminSkillOptions(skill,\'specialisation\',\'adminSkillSpecialisations\');\n}\nfunction renderAdminSkillOptions(skill,kind,containerId) {');
s=s.replace('const container = document.getElementById("adminSkillSpecialisations");','const container = document.getElementById(containerId);');
start=s.indexOf('    if (Number(skill.idSkill || 0) <= 0) {',s.indexOf('function renderAdminSkillOptions'));end=s.indexOf('    if (!Array.isArray(skill.specialisations)',start);s=s.slice(0,start)+s.slice(end);
s=s.replace('    skill.specialisations.forEach((specialisation) => {','    skill.specialisations.filter(s=>s.kind===kind).forEach((specialisation) => {');
s=s.replace('        kind: getDefaultAdminSpecialisationKind(currentAdminSkill),','        kind: "specialisation",');
// Auto-save signals are draft-only; focusout/navigation must never write.
start=s.indexOf('    adminSkillAutoSaveTimer = window.setTimeout(',s.indexOf('function markAdminSkillAutoSavePending'));end=s.indexOf('\n}',start);s=s.slice(0,start)+s.slice(end);
s=s.replace('    const { force = false } = options;','    const { force = false } = options;\n    if(!force)return !adminSkillEditing||!adminSkillAutoSavePending;');
s=s.replace('            populateAdminSkillForm(result?.skill || null);\n            showAdminFeedback("Vaardigheid aangemaakt."', '            populateAdminSkillForm({...result.skill,...draft,idSkill:result.skill.idSkill,isDraft:false});\n            showAdminFeedback("Vaardigheid aangemaakt."');
s=s.replace('    createAdminSkillPromise = (async () => {','    const draft=normalizeAdminSkillState(currentAdminSkill);\n    createAdminSkillPromise = (async () => {');
s=s.replace('                            name: String(specialisation.name || "").trim(),','                            name: String(specialisation.name || "").trim(),\n                            kind: specialisation.kind,');
start=s.indexOf('            item.textContent = specs.length > 0'),end=s.indexOf('\n            list.appendChild(item);',start);
s=s.slice(0,start)+`            const portrait=document.createElement('img');portrait.className='admin-holder-portrait';portrait.alt='';portrait.loading='lazy';portrait.src=character.portraitUrl||'img/portret/default.png';portrait.addEventListener('error',()=>portrait.hidden=true,{once:true});
            const details=document.createElement('div'),link=document.createElement('a');link.textContent=baseName;link.href='index.html?character='+Number(character.idCharacter)+'&tab=sheet';details.append(link);
            if(specs.length){const names=document.createElement('div');names.className='small';names.textContent=specs.join(', ');details.append(names);}item.append(portrait,details);
`+s.slice(end);
fs.writeFileSync(p,s);
