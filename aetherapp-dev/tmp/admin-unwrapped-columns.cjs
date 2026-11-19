const fs=require('fs');let p='admin.html',s=fs.readFileSync(p,'utf8');
s=s.replace('<main class="container aether-page admin-page">','<main class="aether-page admin-page px-3">');
for(const id of ['adminSkillsSection','adminTraitsSection']){
    const start=s.indexOf('<section',s.indexOf(id)-40),end=s.indexOf('</section>',start);
    let section=s.slice(start,end);
    section=section.replace(/<section[^>]+>/,`<section id="${id}" class="row g-4 admin-editor-columns${id==='adminTraitsSection'?' d-none':''}">`);
    section=section.replace(/\s*<div class="row g-4">/,'');
    const last=section.lastIndexOf('</div>');if(last<0)throw Error('Missing row end');section=section.slice(0,last)+section.slice(last+6);
    s=s.slice(0,start)+section+s.slice(end);
}
s=s.replace('class="d-none" id="adminKnowledgeSection"','class="container d-none" id="adminKnowledgeSection"').replace('class="d-none" id="adminActionsSection"','class="container d-none" id="adminActionsSection"');
fs.writeFileSync(p,s);
for(const file of ['tests/admin_skill_modes_browser_test.cjs','tests/admin_traits_browser_test.cjs']){
    s=fs.readFileSync(file,'utf8').replace('#adminSkillsSection > .row','#adminSkillsSection').replace('#adminTraitsSection > .row','#adminTraitsSection');fs.writeFileSync(file,s);
}
