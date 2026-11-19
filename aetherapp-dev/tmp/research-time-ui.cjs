const fs=require('fs');const p='js/researchAdmin.js';let s=fs.readFileSync(p,'utf8');
s=s.replace("if(!d.used&&general&&filled(general))section.body.append(R.button('Variant toevoegen','fa-plus',()=>routeDialog(d,level)));","if(!d.used&&general&&filled(general)){const actions=R.el('div','','research-variant-action');actions.append(R.button('Variant toevoegen','fa-plus',()=>routeDialog(d,level)));section.body.append(actions);}");
const begin=s.indexOf('        const text=R.editor(step?.content),confirm=',s.indexOf('    function stepDialog'));
const end=s.indexOf('\n    });}',begin);
s=s.slice(0,begin)+`        const text=R.editor(step?.content),confirm=R.check('Bevestiging nodig',Number(step?.confirmationRequired||0));confirm.input.disabled=!!discovery.used;
        const hasNext=!!step&&route.steps.some(s=>Number(s.position)>Number(step.position));
        const followUp=R.check('Vervolgstap toevoegen',hasNext);followUp.input.disabled=!!discovery.used||hasNext;
        body.append(R.field('Feedback',text.wrapper),confirm.box,R.el('p','Door deze optie aan te vinken, vraag je de deelnemer zelf aan te geven wanneer deze stap is afgerond.','small'),followUp.box,
            R.el('p','Door deze optie aan te vinken zal er na deze stap een nieuwe stap toegevoegd worden. Die zal zichtbaar zijn door de huidige stap af te werken (de gebruiker drukt op "stap afronden" of na verloop van tijd, door een aftelklok/datum in te stellen).','small'));
        const saved=(step?.conditions||[]).find(c=>c.target==='transition');
        const timing=R.el('fieldset'),legend=R.el('legend','Tijd instellen','h5');timing.disabled=!!discovery.used;timing.append(legend);body.append(timing);
        const group=R.el('div','','d-flex flex-wrap gap-3'),inputs={};let type=saved?.type||'none';
        const radioName='research-time-'+(step?.id||route.id);
        for(const [value,label] of [['none','Geen tijd'],['duration','Timer'],['absolute','Vast tijdstip']]){
            const wrapper=R.el('label','','form-check'),input=R.input(value,'radio');input.name=radioName;input.className='form-check-input';input.checked=type===value;inputs[value]=input;wrapper.append(input,document.createTextNode(label));group.append(wrapper);
            input.addEventListener('change',()=>{type=value;update();});
        }
        timing.append(group);
        const duration=R.el('div','','research-grid'),units={};let seconds=Number(saved?.seconds||0);
        for(const [key,label,unit,max] of [['days','Dagen',86400,365],['hours','Uren',3600,23],['minutes','Minuten',60,59],['seconds','Seconden',1,59]]){
            const value=Math.floor(seconds/unit);seconds%=unit;const input=R.input(value,'number');input.min=0;input.max=max;input.step=1;units[key]=input;duration.append(R.field(label,input));
        }
        const absolute=R.el('div'),dates={};
        state.detail.eventIds.forEach(id=>{const event=state.catalog.events.find(e=>Number(e.id)===Number(id));const input=R.input(saved?.times?.[id]||'','datetime-local');dates[id]=input;absolute.append(R.field(event.title+' · '+(event.timeZone||'Europe/Brussels'),input));});
        const countdown=R.check('Countdown tonen',saved?.showCountdown??true);timing.append(duration,absolute,countdown.box);
        function update(){timing.hidden=!followUp.input.checked;duration.hidden=type!=='duration';absolute.hidden=type!=='absolute';countdown.box.hidden=type==='none';}
        followUp.input.addEventListener('change',update);update();
        body.append(saveButton(body,d,'step_save',()=>{
            if(followUp.input.checked&&type==='none'&&!confirm.input.checked)throw new Error('Vink Bevestiging nodig aan voor een vervolgstap zonder tijd.');
            const timingValue={type,showCountdown:countdown.input.checked};
            if(type==='duration'){
                for(const input of Object.values(units))if(!input.checkValidity())throw new Error('Vul geldige dagen, uren, minuten en seconden in.');
                timingValue.seconds=Number(units.days.value)*86400+Number(units.hours.value)*3600+Number(units.minutes.value)*60+Number(units.seconds.value);
                if(followUp.input.checked&&timingValue.seconds<=0)throw new Error('Stel een timer van minstens één seconde in.');
            }
            if(type==='absolute')timingValue.times=Object.fromEntries(Object.entries(dates).map(([id,input])=>[id,input.value]));
            return {id:Number(step?.id||0),...(step?{revision:Number(step.revision)}:{}),idRoute:Number(route.id),...(readVariant&&JSON.stringify(readVariant())!==originalVariant?{variant:readVariant()}:{}),content:text.getHtml(),confirmationRequired:confirm.input.checked,followUp:followUp.input.checked,conditions:step?.conditions||[],timing:timingValue};
        }));`+s.slice(end);
fs.writeFileSync(p,s);
