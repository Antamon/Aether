const fs=require('fs');let p='admin.html',s=fs.readFileSync(p,'utf8');
s=s.replace('</div></div></nav>',`</div>
        <div class="row admin-toolbar navRow g-3 d-none" id="adminTraitsToolbar"><div class="col-lg-8 col-12"><label class="form-label admin-toolbar-label" for="adminTraitSelect">Eigenschap</label><select class="form-select" id="adminTraitSelect"><option value="0">Kies een eigenschap</option></select></div><div class="col-lg-4 col-12 align-self-end"><div class="button-group"><button type="button" class="action-button" id="adminNewTraitButton">Nieuwe eigenschap</button></div></div></div>
    </div></nav>`);
let a=s.indexOf('        <section class="d-none" id="adminTraitsSection">'),b=s.indexOf('</section>',a)+10;
s=s.slice(0,a)+`        <section class="d-none" id="adminTraitsSection">
            <div class="row g-4"><div class="col-lg-8 col-12"><div class="card aether-panel shadow-sm admin-card"><div class="card-body" id="adminTraitEditor"><p>Kies een eigenschap.</p></div></div></div>
            <div class="col-lg-4 col-12"><div class="card aether-panel shadow-sm admin-card"><div class="card-body"><h2 class="h5 mb-3">Personages met deze eigenschap</h2><div class="admin-holders-list" id="adminTraitHolders"></div></div></div></div></div>
        </section>`+s.slice(b);
s=s.replace('    <script src="js/adminActions.js','    <script src="js/adminTraits.js"></script>\n    <script src="js/adminActions.js');fs.writeFileSync(p,s);
p='js/adminFunctions.js';s=fs.readFileSync(p,'utf8').replace("tabName!=='skills');","!['skills','traits'].includes(tabName));");
s=s.replace('    const knowledgeToolbar =',"    document.getElementById('adminTraitsToolbar')?.classList.toggle('d-none',tabName!=='traits');\n    document.dispatchEvent(new CustomEvent('aether:admin-tab',{detail:tabName}));\n    const knowledgeToolbar =");
s=s.replace('function renderAdminHolders(skill) {\n    const container = document.getElementById("adminSkillHolders");',"function renderAdminHolders(skill,containerId='adminSkillHolders',kind='vaardigheid') {\n    const container = document.getElementById(containerId);");
const x=s.indexOf('function renderAdminHolders('),y=s.indexOf('function createAdminSpecialisationClientKey',x);let h=s.slice(x,y).replace('"Kies een vaardigheid om de gekoppelde personages te zien."',"'Kies een '+kind+' om de gekoppelde personages te zien.'").replace('"Geen actieve personages gekoppeld aan deze vaardigheid."',"'Geen actieve personages gekoppeld aan deze '+kind+'.'");s=s.slice(0,x)+h+s.slice(y);fs.writeFileSync(p,s);
p='css/style.css';s=fs.readFileSync(p,'utf8').replaceAll('#adminSkillsSection',':is(#adminSkillsSection, #adminTraitsSection)');fs.writeFileSync(p,s);
