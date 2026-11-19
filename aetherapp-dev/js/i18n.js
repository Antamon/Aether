/* Interface copy only. Database content is never submitted for translation. */
(() => {
    'use strict';
    const catalog=window.AETHER_INTERFACE_COPY || {};
    const normalize=value=>/^en(?:[-_]|$)/i.test(String(value || ''))?'en':'nl';
    const read=key=>{try{return localStorage.getItem(key);}catch{return null;}};
    const write=(key,value)=>{try{localStorage.setItem(key,value);return true;}catch{return false;}};
    const preferenceKey=id=>'aether:interface-language:'+id;
    let userId=null, current=normalize(read('aether:interface-bootstrap'));
    document.documentElement.lang=current;
    const text=(source,values=[])=>{
        source=String(source);const key=source.trim(),translation=catalog[key];
        const result=current==='en'?(translation?.en ?? translation ?? key):(translation?.nl ?? key);
        const formatted=String(result).replace(/\{(\d+)\}/g,(match,index)=>values[index]===undefined?match:String(values[index]));
        return source.match(/^\s*/)[0]+formatted+source.match(/\s*$/)[0];
    };
    const errorPatterns=Object.keys(catalog).filter(key=>/\{\d+\}/.test(key)).sort((a,b)=>b.length-a.length).map(key=>{
        const indices=[];const escaped=key.split(/(\{\d+\})/).map(part=>{const match=part.match(/^\{(\d+)\}$/);if(match){indices.push(Number(match[1]));return '(.+?)';}return part.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');}).join('');
        return {key,indices,pattern:new RegExp('^'+escaped+'$')};
    });
    function errorText(message){
        if(typeof message!=='string')return message;
        if(current==='nl'||catalog[message.trim()])return text(message);
        for(const {key,indices,pattern} of errorPatterns){const match=message.match(pattern);if(match){const values=[];indices.forEach((index,i)=>values[index]=match[i+1]);return text(key,values);}}
        const decimal=message.match(/^Veld ([\w]+): (.+)$/);if(decimal)return 'Field '+decimal[1]+': '+errorText(decimal[2]);
        return message;
    }
    function apply(root=document){
        const nodes=[];if(root.nodeType===1)nodes.push(root);root.querySelectorAll?.('[data-i18n], [data-i18n-title], [data-i18n-placeholder], [data-i18n-aria-label], [data-i18n-alt]').forEach(n=>nodes.push(n));
        for(const node of nodes){
            if(node.dataset?.i18n!==undefined){const value=text(node.dataset.i18n);if(node.textContent!==value)node.textContent=value;}
            for(const attr of ['title','placeholder','aria-label','alt']){const source=node.getAttribute?.('data-i18n-'+attr);if(source!==null&&source!==undefined){const value=text(source);if(node.getAttribute(attr)!==value)node.setAttribute(attr,value);}}
        }
    }
    function setLanguage(language){
        const next=normalize(language);if(next===current)return;
        if(userId!==null)write(preferenceKey(userId),next);
        const stored=write('aether:interface-bootstrap',next);
        // Rebuild all JS-generated labels and cached configuration in the chosen language.
        if(stored)location.reload();
        else {current=next;document.documentElement.lang=current;apply();if(lastUser)greet(lastUser);}
    }
    function greet(user){
        const host=document.getElementById('navbarName');if(!host)return;
        if(host.dataset.languageUser===String(user.id)&&host.dataset.languageLocale===current&&host.querySelector('.aether-language-picker'))return;
        const name=user.displayName || [user.firstName,user.lastName].filter(Boolean).join(' ');
        const greeting=document.createElement('span');greeting.textContent=text('Welkom {0}',[name]);
        const picker=document.createElement('details');picker.className='aether-language-picker';
        const summary=document.createElement('summary');summary.setAttribute('aria-label',text('Taal kiezen'));summary.textContent='('+current+'▾)';
        const list=document.createElement('div');list.className='aether-language-menu';
        for(const [language,label] of [['en','English'],['nl','Nederlands']]){
            const button=document.createElement('button');button.type='button';button.lang=language;button.textContent=label;button.setAttribute('aria-pressed',String(current===language));
            button.addEventListener('click',()=>{picker.open=false;setLanguage(language);});list.append(button);
        }
        picker.append(summary,list);host.replaceChildren(greeting,picker);
        host.dataset.languageUser=String(user.id);host.dataset.languageLocale=current;
        picker.addEventListener('keydown',event=>{if(event.key==='Escape'){picker.open=false;summary.focus();}});
        document.addEventListener('click',event=>{if(!picker.contains(event.target))picker.open=false;});
    }
    let lastUser=null;
    function initialize(user){
        lastUser=user;
        userId=String(user.id);
        const preferred=normalize(read(preferenceKey(userId)) || user.interfaceLanguage || 'nl');
        if(preferred!==current){if(write('aether:interface-bootstrap',preferred)){location.reload();return false;}current=preferred;document.documentElement.lang=current;}
        greet(user);apply();
        return true;
    }
    window.AetherI18n={t:text,errorText,apply,initialize,setLanguage,get language(){return current;},get locale(){return current==='en'?'en-GB':'nl-BE';}};
    window.aetherT=text;
    const observer=new MutationObserver(records=>{for(const record of records)for(const node of record.addedNodes)if(node.nodeType===1)apply(node);});
    observer.observe(document.documentElement,{childList:true,subtree:true});
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>apply());else apply();
})();
