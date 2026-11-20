/* Authorized game-content translations. Source objects and editable controls stay unchanged. */
(() => {
    'use strict';
    const plain = new Map(), html = new Map(), sources = new WeakMap(), richNodes = new Map(), providers = new Map();
    const editions=new Map(),bindings=new Set();
    const canCorrect=()=>['administrator','director'].includes(window.AETHER_CURRENT_USER?.role);
    function editable(source) {
        const entry=editions.get(String(source??''));
        return canCorrect()&&entry&&entry.sourceLanguage!==window.AetherI18n?.language ? entry : null;
    }
    function bindInput(node,source,field=null) {
        for(const b of bindings)if(b.node===node)bindings.delete(b);
        const entry=editable(source);node.value=entry?entry.text:String(source??'');
        if(entry)bindings.add({node,entry,field,read:()=>node.value});
        return node;
    }
    function bindEditor(node,source,field=null) {
        for(const b of bindings)if(b.node===node)bindings.delete(b);
        const entry=editable(source);node.innerHTML=entry?entry.text:String(source??'');
        if(entry)bindings.add({node,entry,field,read:()=>node.innerHTML.trim()});
    }
    function prepareSave(body) {
        if(!body||typeof body!=='object'||Array.isArray(body)||!canCorrect())return body;
        for(const binding of bindings)if(!binding.node.isConnected)bindings.delete(binding);
        const edits=[];
        function walk(value,path=[]) {
            if(value&&typeof value==='object'){for(const[key,child]of Object.entries(value))walk(child,[...path,key]);return;}
            if(typeof value!=='string')return;
            const candidates=[...bindings].filter(b=>(!b.field||b.field===path.at(-1))&&String(b.read()).trim()===value.trim()&&b.entry.targetLanguage===window.AetherI18n?.language);
            const unique=new Map(candidates.map(b=>[b.entry.sourceHash+':'+b.entry.version,b.entry]));
            if(unique.size>1)throw new Error(window.aetherT('Dezelfde tekst heeft verschillende vertalingen in dit formulier.'));
            const entry=unique.values().next().value;
            if(entry)edits.push({path,sourceHash:entry.sourceHash,version:entry.version});
        }
        walk(body);
        return edits.length?{...body,_aetherTranslationEdits:edits}:body;
    }
    const excluded = 'script,style,textarea,input,[contenteditable],[data-content-source],[data-content-rendered],[data-i18n]';
    let scheduled = false;
    function text(value) {
        const source = String(value ?? '');
        if (plain.has(source)) return plain.get(source);
        const trimmed = source.trim();
        if (plain.has(trimmed)) return source.slice(0, source.indexOf(trimmed)) + plain.get(trimmed) + source.slice(source.indexOf(trimmed) + trimmed.length);
        const lowerFirst=trimmed.charAt(0).toLocaleLowerCase()+trimmed.slice(1);
        if(trimmed!==lowerFirst&&plain.has(lowerFirst)){
            const translated=plain.get(lowerFirst);return source.match(/^\s*/)[0]+translated.charAt(0).toLocaleUpperCase()+translated.slice(1)+source.match(/\s*$/)[0];
        }
        return source;
    }
    function label(value) {
        const direct = text(value);
        if (direct !== value) return direct;
        // Lists and headings combine separate database names with interface labels.
        // Translate exact components, never words inside an arbitrary narrative paragraph.
        return String(value).split(/(, | · | - |: | \(|\))/).map(part => text(part)).join('');
    }
    function rich(value) {
        const source = String(value ?? '');
        const translated = html.get(source) ?? html.get(source.trim());
        if (translated !== undefined) return translated;
        const visible = text(source);
        return visible === source ? source : visible.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function apply(root = document.body) {
        if (!root) return;
        const walk = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walk.nextNode())) {
            const parent = node.parentElement;
            if (!parent || parent.closest(excluded)) continue;
            const saved = sources.get(node);
            const source = saved && saved.rendered === node.nodeValue ? saved.source : node.nodeValue;
            const rendered = label(source);
            if (rendered === node.nodeValue) continue;
            if (providers.get(source)==='google-nmt') attribution(parent);
            if (parent.tagName === 'OPTION' && !parent.hasAttribute('value')) parent.value = parent.textContent;
            sources.set(node, {source, rendered});
            node.nodeValue = rendered;
        }
    }
    function schedule() {
        if (scheduled) return;
        scheduled = true;
        queueMicrotask(() => {scheduled = false; apply();});
    }
    function register(entries) {
        for (const entry of entries) {
            if (typeof entry.source !== 'string' || typeof entry.text !== 'string') continue;
            (entry.format === 'html' ? html : plain).set(entry.source, entry.text);
            providers.set(entry.source, entry.provider || '');
            if(entry.sourceHash&&entry.version&&['nl','en'].includes(entry.sourceLanguage))editions.set(entry.source,{...entry,targetLanguage:window.AetherI18n?.language});
        }
        for (const [node, source] of richNodes) {
            if (!node.isConnected) {richNodes.delete(node);continue;}
            const translated = rich(source);
            if (node.innerHTML !== translated) node.innerHTML = translated;
            if (providers.get(source)==='google-nmt') attribution(node);
        }
        schedule();
    }
    function renderRich(node, source) {
        node.setAttribute('data-content-rendered', 'true');
        richNodes.set(node, String(source ?? ''));
        node.innerHTML = rich(source);
        if (providers.get(String(source))==='google-nmt') queueMicrotask(()=>attribution(node));
    }
    function attribution(node) {
        if(!node.isConnected)return;
        const panel=node.closest('.aether-panel') || document.querySelector('main');if(!panel||panel.querySelector('.aether-translation-attribution'))return;
        const note=document.createElement('small'),link=document.createElement('a');note.className='aether-translation-attribution';note.setAttribute('data-content-source','true');
        link.href='https://translate.google.com/';link.target='_blank';link.rel='noopener';link.textContent='Powered by Google Translate';note.append(link);panel.append(note);
    }
    function clear() {plain.clear(); html.clear(); editions.clear();bindings.clear();}
    window.AetherContent = {text, label, rich, renderRich, register, apply, clear,bindInput,bindEditor,prepareSave};
    window.aetherBindTranslatedInput=bindInput;
    const observer = new MutationObserver(records => {
        if (records.some(record => record.type === 'characterData' || record.addedNodes.length)) schedule();
    });
    observer.observe(document.documentElement, {subtree:true, childList:true, characterData:true});
})();
