/* Background Google translations and authorized manual editing within existing Aether modals. */
(() => {
    'use strict';
    const entries=new Map(),queued=new Set(),jobs=[];
    let user=null,manager=false,running=false,status=null,insufficient=false,poll=null;
    const t=(source,values=[])=>window.aetherT(source,values);
    const el=(tag,text='',cls='')=>{const node=document.createElement(tag);node.className=cls;node.textContent=text;return node;};
    const language=()=>window.AetherI18n?.language||'nl';
    const post=(op,entry,extra={})=>aetherRequestJson('api/translations/http.php',{method:'POST',body:{op,source:entry.source,format:entry.format,grant:entry.grant,target:language(),...extra}});
    function setStatus(next) {
        if (!next) return;
        if (status?.month!==next.month) insufficient=false;
        status=next;navbar();
    }
    function navbar() {
        const host=document.getElementById('navbarName');if (!host||!manager) return;
        let button=host.querySelector('.aether-translation-button');
        if (!button) {
            button=el('button','','aether-translation-button');button.type='button';button.innerHTML='<i class="fa-solid fa-language" aria-hidden="true"></i>';
            button.setAttribute('aria-label',t('Vertalingen beheren'));button.addEventListener('click',openEditor);host.append(button);
        }
        const number=value=>Number(value||0).toLocaleString(window.AetherI18n?.locale||'nl-BE');
        button.title=status?t('Vertaalgebruik deze maand: {0} / {1} tekens',[number(status.used),number(status.limit)]):t('Vertalingen beheren');
        let warning=host.querySelector('.aether-translation-warning');
        if (!warning) {warning=el('span','','aether-translation-warning');warning.setAttribute('role','status');host.append(warning);}
        warning.textContent=status?.exhausted?t('Vertaaltegoed op'):insufficient?t('Onvoldoende vertaaltegoed'):'';
        warning.hidden=!warning.textContent;
    }
    async function refresh() {
        if (!manager) return;
        try {setStatus(await aetherRequestJson('api/translations/http.php?op=status'));} catch { /* Game screens remain usable if translation setup is incomplete. */ }
    }
    function initialize(current) {
        if (user?.id===current.id) return;
        user=current;manager=['administrator','director'].includes(current.role);
        if (manager) {navbar();void refresh();if(poll)clearInterval(poll);poll=setInterval(refresh,60000);}
    }
    function consume(envelope) {
        for (const entry of envelope.aetherEditableContent||[]) entries.set(entry.hash,entry);
        setStatus(envelope.aetherTranslationUsage);
        for (const entry of envelope.aetherMissingContent||[]) {
            const target=entry.targetLanguage;
            if(!entry.automaticGrant||!['nl','en'].includes(entry.sourceLanguage)||!['nl','en'].includes(target)||target===entry.sourceLanguage)continue;
            const key=entry.hash+':'+target;if(queued.has(key)||entry.manualRequired)continue;
            queued.add(key);jobs.push({entry,target});
        }
        if(jobs.length&&!running)queueMicrotask(run);
    }
    async function run() {
        if(running)return;running=true;
        try {
            while(jobs.length) {
                const {entry,target}=jobs.shift();
                try {
                    const result=await post('automatic',entry,{target,sourceLanguage:entry.sourceLanguage,automaticGrant:entry.automaticGrant});setStatus(result.usage);
                    if(result.state==='ready'&&target===language()) AetherContent.register([{source:entry.source,text:result.text,format:result.format,provider:result.provider||'google-nmt'}]);
                    if(result.state==='quota_exhausted') {insufficient=true;navbar();jobs.length=0;}
                    if(result.state==='manual_required'&&entries.has(entry.hash))entries.get(entry.hash).manualRequired=true;
                }catch { /* Source text stays visible. A reload can retry after the server cooldown. */ }
            }
        } finally {running=false;}
    }
    function plain(source) {
        const template=document.createElement('template');template.innerHTML=source.replace(/<\/(?:p|div|li|h4)>/gi,'\n');
        return template.content.textContent.trim();
    }
    function openEditor() {
        if (!manager) return;
        const dialog=el('dialog','','share-ui-dialog aether-translation-dialog');
        const body=el('div','','aether-translation-body');dialog.append(el('h3',t('Vertalingen beheren')),body);
        const info=el('p',status?t('Vertaalgebruik deze maand: {0} / {1} tekens',[status.used.toLocaleString(),status.limit.toLocaleString()]):'');body.append(info);
        if(!status?.configured)body.append(el('p',t('Google is nog niet ingesteld. Handmatig vertalen blijft mogelijk.'),'text-muted'));
        const search=el('input','','form-control mb-2');search.type='search';search.placeholder=t('Tekst zoeken');search.setAttribute('aria-label',t('Tekst zoeken'));
        const selection=el('select','','form-select mb-3');selection.setAttribute('aria-label',t('Tekst op deze pagina'));
        selection.setAttribute('data-content-source','true');
        const onlyLong=el('input');onlyLong.type='checkbox';const onlyLongLabel=el('label','','d-flex gap-2 align-items-center mb-3');onlyLongLabel.append(onlyLong,document.createTextNode(t('Alleen lange teksten')));
        const controls=el('div','','row g-3 mb-3');
        const target=el('select','','form-select'),sourceLanguage=el('select','','form-select');
        for(const control of [target,sourceLanguage])for(const [value,name]of [['en','English'],['nl','Nederlands']]){const option=el('option',name);option.value=value;control.append(option);}
        target.value=language();
        const field=(label,control)=>{const box=el('label',label,'col-12 col-md-6');box.append(control);return box;};
        controls.append(field(t('Vertalen naar'),target),field(t('Brontaal'),sourceLanguage));
        const original=el('pre','','aether-translation-source'),editorHost=el('div','','mb-3'),note=el('p','','text-muted'),error=el('p','','alert alert-danger');error.hidden=true;error.setAttribute('role','alert');
        original.setAttribute('data-content-source','true');
        const save=AetherActionButtons.button({icon:'fa-check',label:t('Opslaan'),onClick:()=>void commit()});
        body.append(search,onlyLongLabel,selection,controls,el('strong',t('Oorspronkelijke tekst')),original,note,editorHost,error,AetherActionButtons.group([save]));
        let chosen=null,editor=null,version='',loading=0,busy=false;
        function options() {
            const previous=selection.value;selection.replaceChildren();
            const matching=[...entries.values()].filter(e=>(!onlyLong.checked||e.manualRequired)&&plain(e.source).toLocaleLowerCase().includes(search.value.toLocaleLowerCase()));
            const empty=el('option',matching.length?t('Kies een tekst'):t('Geen teksten beschikbaar op deze pagina.'));empty.value='';selection.append(empty);
            for(const entry of matching){const option=el('option',(entry.manualRequired?t('Handmatig')+' · ':'')+plain(entry.source).replace(/\s+/g,' ').slice(0,100));option.value=entry.hash;selection.append(option);}
            selection.value=matching.some(e=>e.hash===previous)?previous:'';
            if(!selection.value&&matching.length)selection.value=matching[0].hash;
            void load();
        }
        async function load() {
            const sequence=++loading;chosen=entries.get(selection.value)||null;save.disabled=true;editorHost.replaceChildren();editor=null;error.hidden=true;
            if(!chosen){original.textContent='';note.textContent='';return;}
            original.textContent=plain(chosen.source);note.textContent=chosen.manualRequired?t('Deze tekst is te lang voor automatische vertaling. Voer de vertaling handmatig in.'):'';
            try {
                const data=await post('read',chosen,{target:target.value});if(sequence!==loading)return;
                sourceLanguage.value=data.sourceLanguage;version=data.version;
                if(chosen.format==='html') {editor=createCharacterRichTextEditor(data.text,{translate:false});editorHost.append(editor.wrapper);}
                else {const input=el('textarea','','form-control');input.rows=10;input.value=data.text;input.setAttribute('aria-label',t('Vertaling'));editorHost.append(input);editor={getHtml:()=>input.value};}
                save.disabled=false;
            }catch(e){if(sequence===loading){error.textContent=e.message;error.hidden=false;}}
        }
        async function commit() {
            if(busy||!chosen||!editor)return;busy=true;save.disabled=true;selection.disabled=true;target.disabled=true;sourceLanguage.disabled=true;search.disabled=true;onlyLong.disabled=true;
            for(const control of editorHost.querySelectorAll('textarea,button'))control.disabled=true;
            if(editor.editor)editor.editor.contentEditable='false';
            try {
                const data=await apiFetchIdempotentJson('api/translations/http.php',{method:'POST',body:{op:'save',source:chosen.source,format:chosen.format,grant:chosen.grant,target:target.value,sourceLanguage:sourceLanguage.value,text:editor.getHtml(),version}});
                if(target.value===language()) AetherContent.register([{source:chosen.source,text:data.text,format:data.format}]);
                version=data.version;note.textContent=t('Vertaling opgeslagen.');error.hidden=true;
            }catch(e){error.textContent=e.message;error.hidden=false;}
            finally{busy=false;save.disabled=false;selection.disabled=false;target.disabled=false;sourceLanguage.disabled=false;search.disabled=false;onlyLong.disabled=false;for(const control of editorHost.querySelectorAll('textarea,button'))control.disabled=false;if(editor.editor)editor.editor.contentEditable='true';}
        }
        selection.addEventListener('change',load);target.addEventListener('change',load);search.addEventListener('input',options);onlyLong.addEventListener('change',options);
        dialog.addEventListener('close',()=>{loading++;dialog.remove();},{once:true});
        document.body.append(dialog);options();dialog.showModal();
    }
    window.AetherTranslation={initialize,consume,setStatus,openEditor};
})();
