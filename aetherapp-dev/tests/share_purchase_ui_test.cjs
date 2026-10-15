const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
    constructor(tag) {
        this.tag = tag; this.children = []; this.dataset = {}; this.listeners = {}; this.value = '';
        this.isConnected = true; this.style = {}; this.classList = {add() {}, contains() { return false; }};
    }
    append(...nodes) { nodes.forEach(node => {node.parent = this; this.children.push(node);}); }
    appendChild(node) { this.append(node); return node; }
    replaceChildren(...nodes) { this.children = nodes; }
    add(node) { this.append(node); if (this.children.length === 1) this.value = String(node.value); }
    addEventListener(type, listener) { this.listeners[type] = listener; }
    showModal() { this.open = true; }
    close() { this.open = false; this.listeners.close?.(); }
    remove() { if (this.parent) this.parent.children = this.parent.children.filter(node => node !== this); this.isConnected = false; }
    focus() { this.focused = true; }
    setAttribute(name, value) { this[name] = value; }
    closest() { return new Element('tab'); }
    querySelectorAll(selector) {
        return this.children.flatMap(node => [
            ...((selector === '[data-share-market-host]' && node.dataset.shareMarketHost
                || selector === 'input[data-offer-id]' && node.tag === 'input' && node.dataset.offerId
                || selector === '[data-share-feedback]' && node.dataset.shareFeedback) ? [node] : []),
            ...node.querySelectorAll(selector),
        ]);
    }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
}
const collect = node => [node, ...node.children.flatMap(collect)];
const flush = () => new Promise(resolve => setImmediate(resolve));
function setup(role) {
    const body = new Element('body'), calls = [], writes = [], visits = [];
    const context = { console, Intl, URLSearchParams,
        document: {body, documentElement: {lang: 'nl'}, hidden: false, createElement: tag => new Element(tag),
            createTextNode: text => Object.assign(new Element('text'), {textContent: text}),
            querySelectorAll: selector => body.querySelectorAll(selector), addEventListener() {}},
        window: {setInterval() {}, addEventListener() {}},
        getCurrentUser: () => ({id: 1, role}), userHasPrivilegedRole: user => ['director','administrator'].includes(user.role),
        AetherActionButtons: {button: ({label}) => Object.assign(new Element('button'), {textContent: label}),
            group: nodes => {const group = new Element('div'); group.append(...nodes); return group;}},
        aetherDataCard: {close() {}, isEditing() {return false;}},
        apiFetchJson: async url => {
            calls.push(url); const eventId = Number(new URLSearchParams(url.split('?')[1]).get('idEvent')) || 7;
            if (url.includes('getShareMarketVersion')) return {version: String(eventId)};
            return {currentEvent: {id: eventId, title: 'Event', dateStart: '2026-10-13'},
                events: [{id: 7, title: 'Current', dateStart: '2026-10-13'}, {id: 3, title: 'Past', dateStart: '2020-01-01'}],
                canTradeAtEvent: true, canEditAssessments: role !== 'participant',
                offers: ['private_board','holder_sale','search'].map((sourceCode, i) => ({id: i + 1, sourceCode,
                    idCompany: 10, companyName: 'Company', logoUrl: 'img/bedrijfslogo/10.png', availableShareCount: 4, unitPrice: '10.00'}))};
        },
        apiFetchIdempotentJson: async (url, options) => {writes.push({url, body: options.body});},
        getCharacter: async (...args) => visits.push(args), alert(message) {throw Error(message);},
        Option: function(text, value) {return Object.assign(new Element('option'), {textContent: text, value: String(value)});}
    };
    vm.createContext(context);
    vm.runInContext(fs.readFileSync('js/shareInterface.js', 'utf8') + '\nthis.ui = aetherShareUi;', context);
    vm.runInContext(fs.readFileSync('js/shareMarketClient.js', 'utf8') + '\nthis.market = aetherShareMarketClient;', context);
    return {body, context, calls, writes, visits};
}
async function checkMarket(role) {
    const app = setup(role), {body, context} = app;
    context.market.attachEconomy(body, {id: 21, idUser: 1, type: 'player', state: 'active'}, 3);
    await flush();
    const host = body.children[0];
    const selector = collect(host).find(node => node.tag === 'select');
    if (role === 'participant') {
        assert.equal(selector, undefined, 'Participant cannot select historical purchase events');
        assert(app.calls.every(url => !url.includes('idEvent=3')), 'Economy ignores stale action-event selection for participants');
    } else {
        assert.equal(selector.value, '3');
        selector.value = '7'; selector.listeners.change(); await flush();
        assert(app.calls.some(url => url.includes('idEvent=7')), 'Manager can switch purchase events');
    }
    for (const id of [1,2,3]) {
        const tiles = collect(host).filter(node => node.className === 'share-company-tile');
        assert.equal(tiles.length, 1, 'Multiple offers from one company share a single tile');
        await tiles[0].listeners.click();
        const dialog = collect(body).find(node => node.tag === 'dialog');
        assert(dialog.open && collect(dialog).some(node => String(node.textContent).includes('Totaal:')));
        const button = collect(dialog).filter(node => node.tag === 'button' && node.textContent === 'Kopen')[id - 1];
        await button.listeners.click();
        assert.equal(app.writes.at(-1).body.idEvent, 7, 'Every offer type submits the actual purchase event');
        assert.equal(dialog.open, false, 'Successful purchases close the dialog');
    }
}
async function checkManagerForm() {
    const app = setup('director'), {context, body} = app;
    context.apiFetchJson = async url => url.includes('getCharacterPortfolio') ? {
        holdings: [], status: {}, canManagePurchases: true, defaultPurchaseEventId: 7,
        purchases: [{companyName: 'Company', eventTitle: 'Past', eventDate: '2020-01-01', shareCount: 2, totalAmount: '20.00'}],
        purchaseEvents: [{id: 7, title: 'Current', dateStart: '2026-10-13'}, {id: 3, title: 'Past', dateStart: '2020-01-01'}],
        purchaseCompanies: [{id: 10, companyName: 'Company'}],
    } : {offers: []};
    const card = context.ui.attachPortfolio(body, {id: 21, state: 'inactive'}); await flush();
    assert(collect(card).some(node => String(node.textContent).includes('Past (2020-01-01)')), 'Purchase history shows the original event');
    const form = collect(card).find(node => node.tag === 'form');
    const selects = collect(form).filter(node => node.tag === 'select');
    selects[0].value = '3';
    const inputs = collect(form).filter(node => node.tag === 'input');
    inputs[0].value = '2'; inputs[1].value = '12,50';
    await form.listeners.submit({preventDefault() {}});
    assert.equal(app.writes[0].url, 'api/shares/addSharePurchase.php');
    assert.equal(app.writes[0].body.idEvent, 3);
    assert.equal(app.writes[0].body.idCharacter, 21);
    assert.equal(app.writes[0].body.shareCount, 2);
    assert.equal(app.writes[0].body.unitPrice, '12.50');
    assert.equal(app.visits.at(-1)[2], 'economy', 'After purchase the bank and portfolio are refreshed');
}
async function checkDraftCapital(role) {
    const app = setup(role), {context, body} = app;
    context.apiFetchJson = async url => url.includes('getCharacterPortfolio') ? {
        holdings: [], status: {shareLevel: 1}, canManagePurchases: false, canBuyDraftShares: true,
        canChooseDraftSnapshot: role !== 'participant',
        draftBudget: {purchasedLevel: 1, budgetAmount: '600000.00', spentAmount: '100000.00', remainingAmount: '500000.00', closedAt: null},
        draftQuotes: [{idCompany: 10, idSnapshot: role === 'participant' ? 12 : 11, companyName: 'Company', eventTitle: 'Snapshot', unitPrice: '75000.00', freeShareCount: 5}]
    } : {offers: []};
    const card = context.ui.attachPortfolio(body, {id: 21, idUser: 1, type: 'player', state: 'draft'}); await flush();
    assert(collect(card).some(node => node.textContent === 'Beschikbaar aankoopbedrag'));
    assert(!collect(card).some(node => node.tag === 'form'), 'Draft purchases start in a company dialog');
    collect(card).find(node => node.className === 'share-company-tile').listeners.click();
    const dialog = collect(body).find(node => node.tag === 'dialog');
    const form = collect(dialog).find(node => node.tag === 'form');
    const count = collect(form).find(node => node.tag === 'input'); count.value = '2';
    await form.listeners.submit({preventDefault() {}});
    assert.equal(app.writes[0].url, 'api/shares/buyDraftShares.php');
    assert.equal(app.writes[0].body.shareCount, 2);
    assert.equal(app.writes[0].body.idSnapshot, role === 'participant' ? 12 : 11);
    assert.equal(app.writes[0].body.unitPrice, undefined, 'Browser must not choose the Draft purchase price');
}
async function checkHoldingDialog(owned) {
    const app = setup('participant'), {context, body} = app;
    context.apiFetchJson = async url => url.includes('getCharacterPortfolio') ? {
        status: {intrinsicPortfolioValue: '120.00', shareLevel: 1, usedStatusPoints: 1, personnelRequirement: 0},
        holdings: [{idCompany: 10, companyName: 'Company', shareCount: 10, intrinsicValue: '120.00',
            marketUnitPrice: '12.50', logoUrl: 'img/bedrijfslogo/10.png'}]
    } : url.includes('getShareRecipientOptions') ? {recipients: [{id: 22, firstName: 'Recipient'}]} : {offers: []};
    const card = context.ui.attachPortfolio(body, {id: 21, idUser: owned ? 1 : 99, type: 'player', state: 'active'});
    await flush();
    for (const hidden of ['Aandelen-niveau','Gebruikte statuspunten','Personeelsvoorwaarde']) {
        assert(!collect(card).some(node => node.textContent === hidden), `${hidden} belongs on the character sheet`);
    }
    assert(collect(card).some(node => node.textContent === 'Intrinsieke bezitswaarde'));
    const tile = collect(card).find(node => node.className === 'share-company-tile');
    assert.equal(collect(tile).find(node => node.tag === 'img').src, 'img/bedrijfslogo/10.png');
    tile.listeners.click(); await flush();
    const dialog = collect(body).find(node => node.tag === 'dialog');
    assert(collect(dialog).some(node => node.textContent === 'Fr 125,00'), 'Modal shows total market value');
    assert(collect(dialog).some(node => node.textContent === 'Fr 12,50'), 'Modal shows unit market price');
    await card.aetherRefresh(true);
    assert(dialog.open && dialog.isConnected, 'Background refresh must preserve the modal and its form');
    const form = collect(dialog).find(node => node.className === 'share-ui-form-grid');
    if (!owned) {
        assert.equal(form, undefined, 'Viewers cannot offer shares belonging to another character');
        dialog.close(); return;
    }
    const selects = collect(form).filter(node => node.tag === 'select');
    const inputs = collect(form).filter(node => node.tag === 'input');
    selects[1].value = '22';
    inputs[0].value = '11';
    const submit = collect(form).find(node => node.tag === 'button');
    await submit.listeners.click();
    assert.equal(app.writes.length, 0, 'A sale offer cannot exceed the holding');
    inputs[0].value = '2';
    await submit.listeners.click();
    assert.equal(app.writes[0].url, 'api/shares/createHolderOffer.php');
    assert.equal(app.writes[0].body.idCompany, 10);
    assert.equal(app.writes[0].body.idRecipientCharacter, 22);
    assert.equal(app.writes[0].body.shareCount, 2);
    assert.equal(app.writes[0].body.unitPrice, '12.50');
    assert(!dialog.open, 'Creating an offer closes the holding modal');
}
(async () => {
    for (const role of ['participant','director','administrator']) await checkMarket(role);
    await checkManagerForm();
    for (const role of ['participant','director','administrator']) await checkDraftCapital(role);
    await checkHoldingDialog(true);
    await checkHoldingDialog(false);
    for (const page of ['index.html','admin.html','companies.html','eventParticipation.html','static.html','leaderPersonnel.html']) {
        const nav = fs.readFileSync(page, 'utf8').match(/<nav\b[\s\S]*?<\/nav>/)[0];
        assert(!/Personages|Evenementen|Bedrijven|Personeelsbeheer/.test(nav), `${page} only retains Admin and greeting`);
        assert(nav.includes('navbarAdminItem') && nav.includes('navbarName'));
    }
    console.log('Share purchase UI, historical event selection and navbar tests passed.');
})().catch(error => {console.error(error); process.exitCode = 1;});
