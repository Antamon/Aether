var aetherT = globalThis.aetherT || ((text, values = []) => text.replace(/\{(\d+)\}/g, (match, index) => values[index] === undefined ? match : String(values[index])));
/* Shared Aether components for research. No server-side JavaScript runtime. */
window.AetherResearch = (() => {
    const el = (tag, text = '', cls = '') => { const n = document.createElement(tag); n.className = cls; n.textContent = text; return n; };
    const button = (label, icon, action) => { const b = AetherActionButtons.button({label, icon}); b.addEventListener('click', action); return b; };
    const group = (...buttons) => AetherActionButtons.group(buttons);
    const rich = html => { const n = el('div', '', 'research-rich'); n.innerHTML = html || ''; return n; }; // HTMLPurifier output only.
    const input = (value = '', type = 'text') => { const n = el('input', '', 'form-control'); n.type = type; n.value = value; return n; };
    const select = (items, value = 0, empty = null) => {
        const n = el('select', '', 'form-select');
        if (empty !== null) { const o = el('option', empty); o.value = ''; n.append(o); }
        items.forEach(item => { const o = el('option', item.name ?? item.title ?? item.label); o.value = item.id ?? item.value; n.append(o); });
        n.value = value;
        if (n.selectedIndex < 0) n.selectedIndex = 0;
        return n;
    };
    let fieldId = 0;
    const field = (text, control) => {
        const box = el('div', '', 'research-field'), label = el('label', text, 'form-label');
        const target = control.matches('input,select,textarea') ? control : control.querySelector('[contenteditable]');
        label.id = `research-label-${++fieldId}`;
        if (target) { if (!target.id) target.id = `research-field-${fieldId}`; label.htmlFor = target.id; target.setAttribute('aria-labelledby', label.id); }
        box.append(label, control); return box;
    };
    const check = (label, checked) => { const box = el('label', '', 'form-check research-check'); const n = input('', 'checkbox'); n.className = 'form-check-input'; n.checked = !!checked; box.append(n, document.createTextNode(label)); return {box, input:n}; };
    const multi = (items, chosen = []) => { const n = select(items); n.multiple = true; n.size = Math.min(8, Math.max(3, items.length)); [...n.options].forEach(o => o.selected = chosen.map(Number).includes(Number(o.value))); return n; };
    const ids = n => [...n.selectedOptions].map(o => Number(o.value));
    const read = (op, data = {}) => apiFetchJson(`api/research/http.php?${new URLSearchParams({op, ...data})}`);
    const write = (op, data) => apiFetchIdempotentJson('api/research/http.php', {method:'POST', body:{op, ...data}});
    const message = (host, error) => { let text = error?.message || String(error); if (text.includes('"error"')) { try { const j = JSON.parse(text.slice(text.indexOf('{'))); text = (j.validationErrors || [j.error]).map(value=>window.AetherI18n?.errorText(value)||value).join(' '); } catch {} } text=window.AetherI18n?.errorText(text)||text; const n = el('p', text, 'alert alert-danger'); n.setAttribute('role','alert'); host.prepend(n); return n; };
    const run = async (host, action) => { try { return await action(); } catch (error) { message(host, error); return null; } };
    const dialog = (title, build) => {
        const d = el('dialog', '', 'share-ui-dialog research-dialog'); const body = el('div', '', 'research-dialog-body');
        d.append(el('h3', title), body); document.body.append(d); build(body, d);
        d.addEventListener('close', () => d.remove(), {once:true}); d.showModal(); return d;
    };
    const editor = html => createCharacterRichTextEditor(html || '');
    const media = (items, host) => (items || []).forEach(m => {
        const type = m.mime.split('/')[0]; const n = document.createElement(type === 'image' ? 'img' : type);
        n.src = m.url; n.className = 'research-media';
        if (type === 'image') n.alt = m.filename; else { n.controls = true; n.preload = 'metadata'; }
        host.append(n);
    });
    const statuses = {completed:aetherT("Afgerond"), awaiting_confirmation:aetherT("Opdracht bezig"), waiting_time:aetherT("Wacht op tijd"), waiting:aetherT("Wacht op vrijgave"), new_feedback:aetherT("Nieuwe feedback beschikbaar"), unavailable:aetherT("Niet beschikbaar")};
    return {el,button,group,rich,input,select,field,check,multi,ids,read,write,message,run,dialog,editor,media,statuses};
})();
