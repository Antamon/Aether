/* Authorized game-content translations. Source objects and editable controls stay unchanged. */
(() => {
    'use strict';
    const plain = new Map(), html = new Map(), sources = new WeakMap(), richNodes = new Map(), providers = new Map();
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
    function clear() {plain.clear(); html.clear();}
    window.AetherContent = {text, label, rich, renderRich, register, apply, clear};
    const observer = new MutationObserver(records => {
        if (records.some(record => record.type === 'characterData' || record.addedNodes.length)) schedule();
    });
    observer.observe(document.documentElement, {subtree:true, childList:true, characterData:true});
})();
