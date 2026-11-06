const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
    constructor(tag) {
        this.tag = tag; this.children = []; this.listeners = {}; this.attributes = {}; this.value = ''; this.dataset = {};
        this.classes = new Set(); this.isConnected = true;
        this.classList = {add: (...keys) => keys.forEach(key => this.classes.add(key)), remove: key => this.classes.delete(key),
            contains: key => this.classes.has(key), toggle: (key, force) => {if(force) this.classes.add(key); else this.classes.delete(key);}};
    }
    set className(value) { this.classes = new Set(value.split(/\s+/)); }
    append(...nodes) { for (const node of nodes) {node.parentElement = this; this.children.push(node);} }
    appendChild(node) {this.append(node); return node;}
    replaceChildren(...nodes) {this.children = []; this.append(...nodes);}
    addEventListener(name, fn) {this.listeners[name] = fn;}
    setAttribute(name, value) {this.attributes[name] = value;}
    focus() {}
    querySelectorAll(selector) {return this.children.flatMap(child => [
        ...((selector.startsWith('.') ? child.classes.has(selector.slice(1)) : child.tag === selector) ? [child] : []),
        ...child.querySelectorAll(selector)
    ]);}
    querySelector(selector) {return this.querySelectorAll(selector)[0] || null;}
    get elements() {return [...this.querySelectorAll('input'), ...this.querySelectorAll('textarea'), ...this.querySelectorAll('button')];}
}
const root = new Element('body');
function add(parent, tag, id, className = '') {const node = new Element(tag); node.id = id; node.className = className; parent.append(node); return node;}
const panel = add(root, 'section', 'companyDetailsPanel');
add(panel, 'div', 'companyDetailsActions'); add(panel, 'h2', 'companyFormTitle'); add(panel, 'p', 'companyFormSubtitle');
const form = add(panel, 'form', 'companyForm');
add(form, 'input', 'companyId');
for (const id of ['companyName','companyFoundationDate','companyDescription']) add(add(form,'div',id+'Wrapper'), id === 'companyDescription' ? 'textarea' : 'input', id);
add(form, 'div', 'companyValue'); add(form, 'div', 'companyShareListing');
add(form, 'div', 'logoActions', 'company-logo-actions');
add(form, 'input', 'companyLogoFileInput'); add(form, 'button', 'companyLogoUploadButton'); add(form, 'button', 'companyLogoDeleteButton');
const activity = add(root, 'section', 'companyActivityPanel');
const find = (node, id) => node.id === id ? node : node.children.map(child => find(child,id)).find(Boolean);
const writes = [], revoked = [], feedback = [];
const context = {console, Date, window: {addEventListener() {}},
    document: {getElementById: id => find(root,id), createElement: tag => new Element(tag),
        createTextNode: text => Object.assign(new Element('#text'), {textContent:text}), addEventListener() {}},
    URL: {createObjectURL: () => 'blob:preview', revokeObjectURL: url => revoked.push(url)},
    apiFetchFinancialJson: async (url, options) => {writes.push({url, body: options.body});},
    apiFetchIdempotentJson: async (url, options) => {writes.push({url, body: options.body}); return {id:44};},
    aetherShareUi: {mutateCompany: async (endpoint, body) => writes.push({url:endpoint,body})}
};
vm.createContext(context);
for (const file of ['js/actionButtons.js','js/dataCard.js','js/companyFunctions.js']) vm.runInContext(fs.readFileSync(file,'utf8'), context);
const renderPersonnel = context.renderCompanyPersonnel;
for (const name of ['renderCompanySnapshots','renderCompanyPersonnel','renderCompanyShareholders','renderCompanyList','updateCompanyTypePresentation','updateSliderPresentation','updateCompanyLogoActions']) context[name] = () => {};
context.updateCompanyLogo = url => {context.logo = url;};
context.showCompanyFeedback = message => feedback.push(message); context.hideCompanyFeedback = () => {};
vm.runInContext('loadCompanyList = async function () { populateCompanyForm(currentCompanyData); };', context);
const company = {id:7, companyName:'Bedrijf', foundationDate:'1920-02-03', description:'Oude beschrijving', companyValue:1000, logoUrl:'logo.png', isShareListed:0};
context.company = company;
vm.runInContext('currentCompanyId=7; currentCompanyData=company;', context);
context.populateCompanyForm(company);
context.setupCompanyPageListeners();
function button(label) {return find(root,'companyDetailsActions').querySelectorAll('button').find(node => node.querySelector('span')?.textContent === label);}
function editing() {return panel.classList.contains('is-editing');}
(async () => {
    assert(!editing()); assert(find(root,'companyName').classList.contains('d-none'));
    assert.equal(find(root,'companyNameDisplay').textContent,'Bedrijf');
    assert.equal(find(root,'companyFoundationDateDisplay').textContent,'03/02/1920');
    assert.equal(find(root,'companyShareListing').querySelector('input'),null, 'Display mode has plain listing text');
    assert(!activity.classList.contains('d-none'));
    button('Edit').listeners.click();
    assert(editing());
    const name = find(root,'companyName'); name.value='Nog niet bewaren'; name.listeners.input(); name.listeners.blur();
    const listing = find(root,'companyShareListing').querySelector('input'); listing.checked=true; listing.listeners.change();
    const file = find(root,'companyLogoFileInput'); file.files=[{name:'logo.png'}]; await file.listeners.change();
    assert.equal(context.logo,'blob:preview'); assert.equal(writes.length,0, 'Field, listing and logo changes are staged until Save');
    button('Cancel').listeners.click();
    assert(!editing()); assert.equal(find(root,'companyNameDisplay').textContent,'Bedrijf');
    assert.equal(context.logo,'logo.png'); assert(revoked.includes('blob:preview'));
    assert.equal(writes.length,0, 'Cancel must not mutate the server');
    button('Edit').listeners.click(); name.value='Nieuw'; name.listeners.input();
    const listed = find(root,'companyShareListing').querySelector('input'); listed.checked=true; listed.listeners.change();
    await button('Save').listeners.click();
    assert(!editing()); assert.equal(find(root,'companyNameDisplay').textContent,'Nieuw');
    assert.equal(writes[0].url,'api/companies/updateCompany.php'); assert.equal(writes[0].body.companyName,'Nieuw');
    assert.equal(writes[0].body.stability,undefined, 'Saving details must not change the other panel');
    assert.equal(writes[1].url,'setShareListing'); assert.equal(writes[1].body.isShareListed,true);
    button('Edit').listeners.click(); name.value='Mislukt'; name.listeners.input();
    const successfulRequest = context.apiFetchFinancialJson;
    context.apiFetchFinancialJson=async () => {throw Error('Bewaren mislukt');};
    await button('Save').listeners.click();
    assert(editing()); assert.equal(name.value,'Mislukt'); assert(feedback.includes('Bewaren mislukt'));
    button('Cancel').listeners.click(); assert.equal(find(root,'companyNameDisplay').textContent,'Nieuw');
    context.apiFetchFinancialJson=successfulRequest;
    context.startCreatingCompany();
    assert(editing()); assert(activity.classList.contains('d-none'));
    name.value='Nieuw bedrijf'; name.listeners.input(); name.listeners.blur();
    const beforeCreate=writes.length;
    assert(!writes.some(write => write.url.includes('newCompany')), 'New companies must wait for Save');
    await button('Save').listeners.click();
    assert.equal(writes[beforeCreate].url,'api/companies/newCompany.php');
    assert.equal(writes[beforeCreate+1].body.id,44); assert(!editing());
    context.populateCompanyForm({...company, canManageCompany:false});
    assert.equal(find(root,'companyDetailsActions').querySelectorAll('button').length, 0, 'Linked users have no edit actions');
    assert.equal(find(root,'companyNameDisplay').textContent, company.companyName);
    assert(find(root,'companyName').disabled, 'Linked users cannot change the company name');
    const personnelCard = add(root, 'div', 'companyPersonnelCard');
    add(personnelCard, 'div', 'companyPersonnelList'); add(personnelCard, 'p', 'companyPersonnelEmpty');
    add(personnelCard, 'button', 'addCompanyPersonnelButton');
    const modal = add(root, 'div', 'companyPersonnelEditModal');
    add(modal, 'h3', 'companyPersonnelEditTitle');
    const modalBody = add(modal, 'div', 'personnelModalBody', 'modal-body');
    context.bootstrap = {Modal: {getInstance: () => ({hide() {}}), getOrCreateInstance: () => ({show() {}})}};
    context.renderCompanyPersonnel = renderPersonnel;
    for (const manager of [false, true]) {
        context.personnelCompany = {...company, canManageCompany: manager, canAddPersonnel: true};
        vm.runInContext(`currentCompanyData=personnelCompany; companyPersonnelDraftIndex=0;
            companyPersonnelDraft={idCharacter:21,nameLabel:'Ada',importance:'High',functionDescription:'Functie',salaryIncreasePercentage:2,isBoardMember:false,skills:[]};
            currentCompanyPersonnelState=[{...companyPersonnelDraft}]; aetherDataCard.reset(); aetherDataCard.open('company:7:personnel:0');`, context);
        renderPersonnel(context.personnelCompany);
        const all = node => [node,...node.children.flatMap(all)];
        const editable = all(modalBody).map(node => node.dataset?.companyPersonnelField).filter(Boolean);
        for (const key of ['functionDescription','salaryIncreasePercentage','isBoardMember']) assert(editable.includes(key));
        for (const key of ['importance','skillId','skillLevel']) assert.equal(editable.includes(key), manager);
        assert(!all(modalBody).some(node => node.classList?.contains('data-card')),
            'The personnel editor modal has no nested data card frame');
        const personnelButtons = all(modalBody).filter(node => node.tag === 'button');
        const save = personnelButtons.find(node => node.querySelector('span')?.textContent === 'Save');
        const remove = personnelButtons.find(node => node.querySelector('span')?.textContent === 'Verwijderen');
        assert(save && remove && save.parentElement === remove.parentElement,
            'Save and delete share a single action group');
        assert(!personnelButtons.some(node => node.querySelector('span')?.textContent === 'Cancel'),
            'Closing the modal replaces the personnel cancel button');
        assert.equal(find(root,'companyPersonnelList').children.length, 0, 'The editor is in a modal, not inline');
    }
    const html=fs.readFileSync('companies.html','utf8');
    assert(html.indexOf('</form>') < html.indexOf('id="companyActivityPanel"'), 'The general-data form must end before the independent second panel');
    console.log('Company details display/edit, staged listing/logo, cancel, save, failure and creation tests passed.');
})().catch(error => {console.error(error); process.exitCode=1;});
