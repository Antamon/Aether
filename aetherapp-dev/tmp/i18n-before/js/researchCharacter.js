window.AetherResearchCharacter = (() => {
    const R = window.AetherResearch;
    let session = null;
    function stop() { if (session) { clearInterval(session.poll); clearInterval(session.tick); session.observers.forEach(o => o.disconnect()); session.scan?.close(); session = null; } }
    function countdown(s) {
        if (!s.countdownNode?.isConnected || s.deadline === null) return;
        const seconds = Math.max(0,Math.ceil((s.deadline-Date.now())/1000));
        s.countdownNode.textContent = `Nog ${Math.floor(seconds/3600)} uur ${Math.floor(seconds%3600/60)} min ${seconds%60} sec`;
    }
    async function attach(host, character, preferredEvent) {
        stop(); const s = {host, character:Number(character.id ?? character.idCharacter), event:0, selected:0, observers:[], busy:false}; session = s;
        host.replaceChildren(R.el('p', 'Onderzoek laden…'));
        await R.run(host, async () => {
            const data = await R.read('events', {idCharacter:s.character}); if (session !== s || !host.isConnected) return;
            s.events = data.events; const selected = data.events.find(e => Number(e.id) === Number(preferredEvent)) || data.events[0]; s.event = Number(selected?.id || 0);
            // The research-specific event list contains only events linked to this character.
            const header = host.closest('.card').querySelector('.character-actions-card-header');
            header?.querySelector('[data-research-event]')?.remove();
            const eventSelect = R.select(data.events.map(e => ({id:e.id, name:`${e.title} (${e.dateStart})`})), s.event, 'Kies een event');
            const eventField = R.field('Event', eventSelect); eventField.dataset.researchEvent = '1'; header?.prepend(eventField);
            eventSelect.addEventListener('change', () => {
                s.scan?.close(); s.event = Number(eventSelect.value); s.selected = 0; s.signature = '';
                s.observers.forEach(o => o.disconnect()); s.observers = []; s.detail.replaceChildren();
                s.busy = false; void refresh(s);
            });
            s.toolbar = R.el('div', '', 'd-flex flex-wrap gap-2 mb-3');
            s.toolbar.append(R.group(R.button('Scan QR-code', 'fa-qrcode', () => scan(s))));
            s.list = R.el('div', '', 'research-found-list'); s.detail = R.el('div', '', 'research-detail');
            host.replaceChildren(s.toolbar, s.list, s.detail); await refresh(s);
            s.poll = setInterval(() => { if (!host.isConnected) return stop(); if (!document.hidden && !s.busy) void refresh(s); }, 10000);
            s.tick = setInterval(() => countdown(s), 1000);
        });
    }
    async function refresh(s) {
        if (session !== s || s.busy) return; s.busy = true;
        try {
            const event = s.event;
            if (!event) {s.list.replaceChildren(R.el('p','Kies een gekoppeld event om te onderzoeken.'));s.detail.replaceChildren();return;}
            const data = await R.read('found', {idCharacter:s.character,idEvent:event}); if (session !== s || event !== s.event) return;
            s.list.replaceChildren();
            if (!data.discoveries.length) s.list.append(R.el('p','Je hebt voor dit event nog geen discoveries gevonden.'));
            data.discoveries.forEach(d => {
                const b = R.el('button', '', 'research-found-item data-card');b.type = 'button';
                b.append(R.el('strong',d.title), R.el('span',R.statuses[d.status]));
                b.addEventListener('click', () => {s.selected = Number(d.id);void open(s);});s.list.append(b);
            });
            if (s.selected) await open(s);
        } catch (e) { R.message(s.host,e); } finally {s.busy=false;}
    }
    async function open(s) {
        const event=s.event,id=s.selected;
        await R.run(s.detail, async () => {
            const d=await R.read('detail',{idEvent:event,idCharacter:s.character,idDiscovery:id});
            if (session!==s || s.event!==event || s.selected!==id) return;
            s.deadline=d.countdownSeconds===null?null:Date.now()+d.countdownSeconds*1000;countdown(s);
            const signature=JSON.stringify({...d,countdownSeconds:null});if (s.signature===signature) return;s.signature=signature;
            s.observers.forEach(o=>o.disconnect());s.observers=[];s.detail.replaceChildren();
            const heading=R.el('div','','d-flex justify-content-between gap-3');heading.append(R.el('h3',d.title));
            if (d.timed) {const clock=R.el('span','','research-clock');clock.innerHTML='<i class="fa-solid fa-clock" aria-hidden="true"></i>';clock.setAttribute('aria-label','Tijdgestuurde feedback');heading.append(clock);}
            s.detail.append(heading);
            if (d.countdownSeconds!==null) {s.countdownNode=R.el('p','','research-countdown');s.detail.append(s.countdownNode);countdown(s);}
            const draw = (step,current) => {
                const card=R.el('section','','data-card research-step');
                if (!current && step.timed) {const clock=R.el('span','','research-clock float-end');clock.innerHTML='<i class="fa-solid fa-clock" aria-hidden="true"></i>';clock.setAttribute('aria-label','Tijdgestuurde feedback');card.append(clock);}
                card.append(R.rich(step.content));R.media(step.media,card);s.detail.append(card);
                if (current && step.confirmationRequired) {
                    const confirm=R.button('Afgerond','fa-check',()=>R.run(card,async()=>{confirm.disabled=true;try{await R.write('confirm',{idEvent:event,idCharacter:s.character,idDiscovery:id,idStep:step.id});s.signature='';await refresh(s);}finally{confirm.disabled=false;}}));
                    confirm.disabled=!step.shownAt||step.canConfirm===false;card.append(R.group(confirm));
                }
                if (current && !step.shownAt) {
                    let sent=false;
                    const mark=async()=>{
                        if (sent || document.hidden || session!==s || s.event!==event || !card.isConnected || !card.getClientRects().length) return;
                        const rect=card.getBoundingClientRect();if (rect.bottom<=0 || rect.top>=innerHeight) return;
                        sent=true;
                        try {await R.write('shown',{idEvent:event,idCharacter:s.character,idDiscovery:id,idStep:step.id});s.signature='';s.busy=false;await refresh(s);} catch(e){sent=false;R.message(card,e);}
                    };
                    const observer=new IntersectionObserver(entries=>{if(entries.some(e=>e.isIntersecting)) requestAnimationFrame(()=>requestAnimationFrame(mark));},{threshold:0});observer.observe(card);s.observers.push(observer);
                    document.addEventListener('visibilitychange',mark);
                    s.observers.push({disconnect:()=>document.removeEventListener('visibilitychange',mark)});
                }
            };
            d.history.forEach(step=>draw(step,false));
            const currents=d.currents||(d.current?[d.current]:[]);
            currents.forEach(step=>draw(step,true));
            if(!currents.length)s.detail.append(R.el('p',R.statuses[d.status]));
        });
    }
    function scan(s) {
        let stream=null, frame=0, reading=false,lastCode='',lastFrame=0;const canvas=document.createElement('canvas');const ctx=canvas.getContext('2d',{willReadFrequently:true});
        s.scan=R.dialog('Scan QR-code',(body,d)=>{
            const video=R.el('video','','research-camera');video.setAttribute('playsinline','');video.muted=true;
            const note=R.el('p','Richt de camera op een Aether-onderzoekscode.');const code=R.input();code.placeholder='aether-research:…';
            const send=async value=>{if(reading)return;reading=true;try{const result=await R.write('scan',{idCharacter:s.character,idEvent:s.event,code:value});s.selected=result.idDiscovery;s.signature='';d.close();await refresh(s);}catch(e){R.message(body,e);reading=false;}};
            const decode=now=>{if(!d.isConnected)return;if(!reading && now-lastFrame>200 && video.readyState>=2 && video.videoWidth){lastFrame=now;const scale=Math.min(1,1000/video.videoWidth);canvas.width=Math.round(video.videoWidth*scale);canvas.height=Math.round(video.videoHeight*scale);ctx.drawImage(video,0,0,canvas.width,canvas.height);const data=ctx.getImageData(0,0,canvas.width,canvas.height);const qr=jsQR(data.data,data.width,data.height);if(qr && qr.data!==lastCode){lastCode=qr.data;void send(qr.data);}else if(!qr)lastCode='';}frame=requestAnimationFrame(decode);};
            const start=R.button('Camera starten','fa-camera',()=>R.run(body,async()=>{
                if(!navigator.mediaDevices?.getUserMedia) throw new Error('Camera niet beschikbaar. Gebruik HTTPS of kies een foto van de QR-code.');
                try {stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'},audio:false});if(!d.isConnected){stream.getTracks().forEach(t=>t.stop());return;}video.srcObject=stream;await video.play();cancelAnimationFrame(frame);decode();}catch{throw new Error('Geen toegang tot de camera. Geef cameratoestemming of kies een foto van de QR-code.');}
            }));
            const file=R.input('','file');file.accept='image/*';file.addEventListener('change',()=>R.run(body,async()=>{
                if(!file.files[0])return;const bitmap=await createImageBitmap(file.files[0]);const scale=Math.min(1,1600/Math.max(bitmap.width,bitmap.height));canvas.width=Math.round(bitmap.width*scale);canvas.height=Math.round(bitmap.height*scale);ctx.drawImage(bitmap,0,0,canvas.width,canvas.height);bitmap.close();const pixels=ctx.getImageData(0,0,canvas.width,canvas.height);const qr=jsQR(pixels.data,pixels.width,pixels.height);if(!qr)throw new Error('Geen leesbare QR-code gevonden in deze foto.');await send(qr.data);
            }));
            body.append(note,video,R.group(start),R.field('Of kies een foto',file),R.field('Of plak een onderzoekscode',code),R.group(R.button('Code openen','fa-arrow-right',()=>send(code.value.trim()))));
            d.addEventListener('close',()=>{cancelAnimationFrame(frame);stream?.getTracks().forEach(t=>t.stop());s.scan=null;},{once:true});
        });
    }
    return {attach,stop};
})();
