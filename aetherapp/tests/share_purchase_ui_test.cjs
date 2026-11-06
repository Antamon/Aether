const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
    constructor(tag) {
        this.tag = tag; this.children = []; this.dataset = {}; this.listeners = {}; this.value = '';
        this.isConnected = true; this.style = {}; this.classList = {add() {}, remove() {}, toggle() {}, contains() { return false; }};
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
        AetherActionButtons: {button: ({label, ariaLabel, title}) => Object.assign(new Element('button'), {textContent: label, 'aria-label': ariaLabel, title}),
            group: nodes => {const group = new Element('div'); group.append(...nodes); return group;}},
        aetherDataCard: {close() {}, isEditing() {return false;}, controls() {}},
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
async function checkMarket(role, type = 'player') {
    const app = setup(role), {body, context} = app;
    context.market.attachEconomy(body, {id: 21, idUser: 1, type, state: 'active'}, 3);
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
        managerQuotes: [{idCompany: 10, companyName: 'Company', idSnapshot: 30, idEvent: 3, eventTitle: 'Past', quoteDate: '2020-01-01', freeShareCount: 10}],
        purchaseQuotes: [{idCompany: 10, idEvent: 3, shareMarketPrice: '12.50'}, {idCompany: 10, idEvent: 7, shareMarketPrice: '15.00'}],
    } : {offers: []};
    const card = context.ui.attachPortfolio(body, {id: 21, state: 'inactive'}); await flush();
    assert(!collect(card).some(node => node.className === 'mt-3 share-ui-purchase-history'), 'Purchase history stays inside holding dialogs');
    collect(card).find(node => node.textContent === 'Aandelenaankoop toevoegen').listeners.click();
    const selection = collect(body).find(node => node.tag === 'dialog');
    collect(selection).find(node => node.className === 'share-company-tile').listeners.click();
    const form = collect(body).find(node => node.tag === 'form');
    const selects = collect(form).filter(node => node.tag === 'select');
    selects[0].value = '1920'; selects[0].listeners.change();
    selects[1].value = '1'; selects[1].listeners.change();
    const inputs = collect(form).filter(node => node.tag === 'input');
    inputs[0].value = '2';
    await form.listeners.submit({preventDefault() {}});
    assert.equal(app.writes[0].url, 'api/shares/addSharePurchase.php');
    assert.equal(app.writes[0].body.purchaseYear, 1920);
    assert.equal(app.writes[0].body.purchaseQuarter, 1);
    assert.equal(app.writes[0].body.idCharacter, 21);
    assert.equal(app.writes[0].body.shareCount, 2);
    assert.equal(app.writes[0].body.unitPrice, undefined, 'Quarter purchase price comes from the server snapshot');
    assert.equal(app.visits.at(-1)[2], 'economy', 'After purchase the bank and portfolio are refreshed');
}
async function checkDraftCapital(role) {
    const app = setup(role), {context, body} = app;
    context.apiFetchJson = async url => url.includes('getCharacterPortfolio') ? {
        holdings: [], status: {shareLevel: 1}, canManagePurchases: false, canBuyDraftShares: true,
        canChooseDraftSnapshot: role !== 'participant',
        draftBudget: {purchasedLevel: 1, budgetAmount: '600000.00', spentAmount: '100000.00', remainingAmount: '500000.00', closedAt: null},
        purchaseEvents: [{id: 2, dateStart: '2027-05-15', dateEnd: '2027-05-16'}],
        draftQuotes: [{idCompany: 10, idSnapshot: role === 'participant' ? 12 : 11, idEvent: 2, quoteDate: '2027-05-15', companyName: 'Company', eventTitle: 'Snapshot', unitPrice: '75000.00', freeShareCount: 5}]
    } : {offers: []};
    const card = context.ui.attachPortfolio(body, {id: 21, idUser: 1, type: 'player', state: 'draft'}); await flush();
    assert(collect(card).some(node => node.textContent === 'Beschikbaar aankoopbedrag'));
    assert(!collect(card).some(node => node.tag === 'form'), 'Draft purchases start in a company dialog');
    collect(card).find(node => node.className === 'share-company-tile').listeners.click();
    const dialog = collect(body).find(node => node.tag === 'dialog');
    const form = collect(dialog).find(node => node.tag === 'form');
    assert.equal(collect(form).some(node => node.className === 'share-purchase-quarter'), role !== 'participant',
        'Only managers can choose a Draft purchase year and quarter');
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
    assert(!collect(dialog).some(node => node.role === 'tab'), 'Participants have no management tabs');
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
async function checkPurchaseEditor(role) {
    const app = setup(role), {context, body} = app;
    const portfolio = {
        holdings: [{idCompany: 10, companyName: 'Company', shareCount: 5, issuedShareCount: 100,
            intrinsicValue: '50.00', marketUnitPrice: '10.00', foundationDate: '1919-01-01'}],
        status: {}, canManagePurchases: true, canEditPurchases: true,
        purchases: [{id: 101, idCompany: 10, shareCount: 2, unitPrice: '10.00', totalAmount: '20.00',
            purchaseYear: 1920, purchaseQuarter: 1, idEvent: 3, eventTitle: 'Past', revision: 0},
            {id: 102, idCompany: 10, shareCount: 3, unitPrice: '10.00', totalAmount: '30.00',
            purchaseYear: 1920, purchaseQuarter: 2, idEvent: 7, eventTitle: 'Next', revision: 0}],
        purchaseEvents: [{id: 3, title: 'Past', dateStart: '2020-01-15'}, {id: 7, title: 'Next', dateStart: '2020-06-01'}],
        purchaseCompanies: [{id: 10, companyName: 'Company', foundationDate: '1919-01-01'}],
        purchaseQuotes: [{idCompany: 10, idEvent: 3, shareMarketPrice: '10.00'}, {idCompany: 10, idEvent: 7, shareMarketPrice: '15.00'}],
    };
    context.apiFetchJson = async url => url.includes('getCharacterPortfolio') ? portfolio : {offers: []};
    const card = context.ui.attachPortfolio(body, {id: 21, idUser: 1, type: 'player', state: 'inactive'}); await flush();
    collect(card).find(node => node.className === 'share-company-tile').listeners.click(); await flush();
    const dialog = collect(body).find(node => node.tag === 'dialog');
    const tabs = collect(dialog).filter(node => node.role === 'tab');
    assert.equal(tabs.length, 2, 'Managers have overview and purchase-history tabs');
    assert.equal(tabs[0].textContent, 'Overzicht'); assert.equal(tabs[1].textContent, 'Aankopen');
    assert.equal(app.writes.length, 0, 'Opening the overview must not initialize purchase history');
    assert(!collect(dialog).some(node => node['aria-label'] === 'Sluiten'), 'The shared modal frame provides the only close icon');
    assert(!collect(dialog).some(node => node.tag === 'button' && node.textContent === 'Sluiten'), 'The modal has no bottom close button');
    tabs[1].listeners.click();
    await flush();
    assert.equal(app.writes[0].url, 'api/shares/initializeSharePurchaseHistory.php');
    collect(dialog).filter(node => node.role === 'tab')[0].listeners.click();
    collect(dialog).filter(node => node.role === 'tab')[1].listeners.click();
    const rows = collect(dialog).filter(node => node.className === 'data-card share-purchase-row mb-3');
    assert.equal(rows.length, 2, 'Each purchase has its own entry');
    const edit = collect(rows[0]).find(node => node['aria-label']?.startsWith('Aankoop '));
    edit.listeners.click();
    const form = collect(rows[0]).find(node => node.tag === 'form');
    assert(!collect(form).some(node => node.tag === 'button' && node.textContent === 'Annuleren'), 'Purchase edits do not have a cancel button');
    const selects = collect(form).filter(node => node.tag === 'select');
    selects[0].value = '1920'; selects[0].listeners.change(); selects[1].value = '2'; selects[1].listeners.change();
    const count = collect(form).find(node => node.tag === 'input' && node.type !== 'checkbox');
    const bank = collect(form).find(node => node.type === 'checkbox');
    assert.equal(bank.checked, false, 'Bank adjustment requires an explicit choice');
    count.value = '4'; count.listeners.input(); bank.checked = true; bank.listeners.change();
    assert(collect(form).some(node => String(node.textContent).includes('Fr 15,00 per aandeel')), 'New event snapshot price is previewed');
    await form.listeners.submit({preventDefault() {}}); await flush();
    const update = app.writes.find(write => write.url === 'api/shares/updateSharePurchase.php');
    assert(update);
    assert.deepEqual(JSON.parse(JSON.stringify(update.body)), {
        idCompany: 10, idCharacter: 21, idTransaction: 101, revision: 0,
        shareCount: 4, purchaseYear: 1920, purchaseQuarter: 2, adjustBank: true,
    });
    assert(dialog.open && dialog.isConnected, 'Saving a purchase preserves the holding dialog');
    assert.equal(app.visits.at(-1)[2], 'economy', 'Purchase correction refreshes the bank and character');
}
async function checkManagerHoldingRemoval() {
    const app = setup('director'), {context, body} = app;
    context.apiFetchJson = async url => url.includes('getCharacterPortfolio') ? {
        holdings: [{idCompany: 10, companyName: 'Company', shareCount: 5, issuedShareCount: 100,
            intrinsicValue: '50.00', marketUnitPrice: '10.00'}],
        status: {}, canManagePurchases: true, canEditPurchases: true, purchases: [], purchaseEvents: [], purchaseQuotes: [],
    } : {offers: []};
    const card = context.ui.attachPortfolio(body, {id: 21, type: 'player', state: 'inactive'}); await flush();
    collect(card).find(node => node.className === 'share-company-tile').listeners.click(); await flush();
    const dialog = collect(body).find(node => node.tag === 'dialog');
    const remove = collect(dialog).find(node => node.tag === 'button' && node.textContent === 'Aandelen verwijderen');
    assert(remove, 'Only managers receive the remove-holding action');
    await remove.listeners.click();
    assert.equal(app.writes.at(-1).url, 'api/shares/removeCharacterHolding.php');
    assert.deepEqual(JSON.parse(JSON.stringify(app.writes.at(-1).body)), {idCompany: 10, idCharacter: 21});
    assert.equal(dialog.open, false, 'Removing a holding closes its modal');
}
async function checkLegacyPurchaseTab() {
    const app = setup('director'), {context, body} = app;
    const portfolio = {
        holdings: [{idCompany: 10, companyName: 'Legacy Company', shareCount: 10, issuedShareCount: 100,
            marketUnitPrice: '15.00', intrinsicValue: '150.00', foundationDate: '1925-01-01'}],
        status: {}, canEditPurchases: true, purchases: [], purchaseEvents: [], purchaseQuotes: [],
    };
    context.apiFetchJson = async url => url.includes('getCharacterPortfolio') ? portfolio : {offers: []};
    context.apiFetchIdempotentJson = async (url, options) => {
        app.writes.push({url, body: options.body});
        if (url.includes('initializeSharePurchaseHistory') && !portfolio.purchases.length) {
            portfolio.purchases.push({id: 501, idCompany: 10, idEvent: null, purchaseYear: 1924, purchaseQuarter: 2,
                purchaseDateSource: 'character_created', legacyOwnership: true, shareCount: 10,
                unitPrice: null, totalAmount: '0.00', revision: 0});
        }
        return {};
    };
    const card = context.ui.attachPortfolio(body, {id: 21, type: 'player', state: 'inactive'}); await flush();
    const open = async () => {
        collect(card).find(node => node.className === 'share-company-tile').listeners.click(); await flush();
        return collect(body).find(node => node.tag === 'dialog');
    };
    let dialog = await open();
    assert.equal(app.writes.length, 0, 'Polling and overview stay read-only');
    collect(dialog).filter(node => node.role === 'tab')[1].listeners.click(); await flush();
    assert.equal(app.writes.length, 1);
    assert.equal(app.writes[0].body.idCompany, 10); assert.equal(app.writes[0].body.idCharacter, 21);
    let rows = collect(dialog).filter(node => node.className === 'data-card share-purchase-row mb-3');
    assert.equal(rows.length, 1, 'Opening Purchases loads the reconstructed legacy purchase');
    assert(collect(rows[0]).some(node => String(node.textContent).includes('1924 · K2')));
    assert(collect(rows[0]).some(node => node.textContent === 'Onbekend'), 'Unknown historical price must not appear as a fabricated paid amount');
    const tabs = collect(dialog).filter(node => node.role === 'tab');
    tabs[0].listeners.click(); tabs[1].listeners.click(); await flush();
    assert.equal(app.writes.length, 1, 'Switching tabs again in the same dialog cannot duplicate initialization');
    dialog.close(); dialog = await open();
    collect(dialog).filter(node => node.role === 'tab')[1].listeners.click(); await flush();
    assert.equal(portfolio.purchases.length, 1, 'Reopening the dialog does not create a second historical purchase');
    rows = collect(dialog).filter(node => node.className === 'data-card share-purchase-row mb-3');
    collect(rows[0]).find(node => node['aria-label']?.startsWith('Aankoop ')).listeners.click();
    const form = collect(rows[0]).find(node => node.tag === 'form');
    const period = collect(form).filter(node => node.tag === 'select');
    assert.equal(period[0].children.length, 0, 'No unavailable years appear without company snapshots');
    assert.equal(period[1].children.length, 0, 'No unavailable quarters appear without company snapshots');
    assert(collect(form).some(node => String(node.textContent).startsWith('Bestaand aankoopkwartaal: 1924')), 'The original inferred date remains visible and unchanged');
    const bank = collect(form).find(node => node.type === 'checkbox');
    assert(bank.disabled && !bank.checked, 'Unknown original payment cannot cause an automatic bank correction');
    collect(form).find(node => node.tag === 'input' && node.type !== 'checkbox').value = '12';
    await form.listeners.submit({preventDefault() {}}); await flush();
    const update = app.writes.find(write => write.url.includes('updateSharePurchase'));
    assert(update && update.body.shareCount === 12 && update.body.purchaseYear === 1924 && update.body.purchaseQuarter === 2 && update.body.adjustBank === false,
        'A reconstructed purchase can be edited just like the other purchases');
}
async function checkCompanyShareholders(role) {
    const {context, body} = setup(role);
    const canManageShares = role !== 'participant';
    context.apiFetchJson = async () => ({company: {id: 10}, canManageShares, myBoardCharacterIds: [], snapshots: [], stock: {issued: 30000, held: 3010, free: 26990},
        holdings: [{idCharacter: 21, displayName: 'Ada', shareCount: 10}, {idCharacter: 22, shareCount: 3000}]});
    await context.ui.attachCompany(body, 10, {personnelEntries: [{idCharacter: 22, nameLabel: 'Bob'}]});
    const table = collect(body).find(node => node.tag === 'table');
    assert(table, 'Shareholders are shown in a table');
    const headings = collect(table).filter(node => node.tag === 'th');
    assert.deepEqual(headings.map(node => node.textContent), canManageShares ? ['Naam', 'Aandelen', undefined] : ['Naam', 'Aandelen']);
    assert.equal(headings[1].colSpan, 2, 'Aandelen groups amount and percentage without separate labels');
    const rows = collect(table).filter(node => node.tag === 'tr').slice(1);
    assert.deepEqual(rows.map(row => row.children.slice(0, 3).map(node => node.textContent)), [['Ada', '10', '0,03%'], ['Bob', '3.000', '10,00%']]);
    for (const [index, row] of rows.entries()) {
        assert.equal(row.children.length, canManageShares ? 4 : 3);
        const link = collect(row).find(node => node.tag === 'a');
        if (!canManageShares) { assert.equal(link, undefined); continue; }
        assert.equal(link.href, `index.html?character=${21 + index}&tab=economy`);
        assert.equal(link.className, 'data-info-button share-holder-character-link');
        assert(link['aria-label'].includes('economie'));
        assert(collect(link).some(node => node.className === 'fa-solid fa-user'));
    }
}
async function checkCompanyOwnership(role) {
    const {context, body, writes} = setup(role);
    const manager = role !== 'participant';
    context.apiFetchJson = async url => url.includes('getCompanyList') ? [
        {id:10,companyName:'Dochterbedrijf'}, {id:30,companyName:'Moederbedrijf',logoUrl:'parent.png'}
    ] : {company:{id:10,companyName:'Dochterbedrijf'},canManageShares:manager,canAddCompanyHolding:manager,
        myBoardCharacterIds: manager ? [] : [21],snapshots:[],stock:{issued:100,held:10,anonymous:20,free:70},
        holdings:[{idHoldingCompany:30,displayName:'Moederbedrijf',shareCount:10}]};
    await context.ui.attachCompany(body,10,{personnelEntries:[]});
    const open = collect(body).find(node=>node.textContent==='Aandelen moederbedrijf');
    const offer = collect(body).find(node=>node.textContent==='Privéaanbod');
    assert(offer,'The existing private offer gets its shorter label');
    if (!manager) {
        assert.equal(open,undefined,'Even participant board members cannot assign company holdings');
        assert(!collect(body).some(node=>node.tag==='a'),'Participants see no shareholder navigation icon');
        return;
    }
    assert(open && open.parent===offer.parent,'Company ownership and private offer share a button group');
    const link=collect(body).find(node=>node.tag==='a');
    assert.equal(link.href,'companies.html?company=30');
    assert(collect(link).some(node=>node.className==='fa-solid fa-industry'),'Company shareholder has a factory icon');
    open.listeners.click();await flush();
    const dialog=collect(body).find(node=>node.open);
    const tiles=collect(dialog).filter(node=>node.className==='share-company-tile');
    assert.equal(tiles.length,1,'The issuing company cannot be selected as its own parent');
    assert(collect(tiles[0]).some(node=>node.src==='parent.png'),'The parent logo is shown');
    const save=collect(dialog).find(node=>node.textContent==='Bewaren');
    assert(save.disabled,'Select a parent before saving');
    tiles[0].listeners.click();
    assert.equal(tiles[0]['aria-pressed'],'true','Selected parent is visibly and accessibly selected');
    const count=collect(dialog).find(node=>node.tag==='input');
    count.value='71';await save.listeners.click();
    assert.equal(writes.length,0,'Excess stock cannot be submitted');
    count.value='6';await save.listeners.click();
    assert.deepEqual(JSON.parse(JSON.stringify(writes[0].body)),{idCompany:10,idHoldingCompany:30,shareCount:6});
    assert(writes[0].url.includes('addCompanyShareHolding.php'));
    assert.equal(dialog.open,false,'Saving closes the parent assignment modal');
}
async function checkCompanySnapshotPeriods() {
    const app = setup('director'), {context, body} = app;
    const data = {holdings: [{idCompany: 10, companyName: 'Company', shareCount: 2, foundationDate: '1925-01-01'}],
        status: {}, canEditPurchases: true,
        purchases: [{id: 101, idCompany: 10, idEvent: 27, shareCount: 2, unitPrice: '20.00', totalAmount: '40.00',
            purchaseYear: 1925, purchaseQuarter: 1, revision: 0}],
        purchaseEvents: [{id: 25, title: 'No snapshot 1925', dateStart: '2025-05-01'},
            {id: 26, title: 'Other company 1926', dateStart: '2026-05-01'},
            {id: 27, title: 'Company snapshot 1927', dateStart: '2027-05-01'},
            {id: 28, title: 'No snapshot 1928', dateStart: '2028-05-01'}],
        purchaseQuotes: [{idCompany: 99, idEvent: 26, shareMarketPrice: '5.00'},
            {idCompany: 10, idEvent: 27, shareMarketPrice: '20.00'}]};
    context.apiFetchJson = async url => url.includes('getCharacterPortfolio') ? data : {offers: []};
    const card = context.ui.attachPortfolio(body, {id: 21, state: 'inactive'}); await flush();
    collect(card).find(node => node.className === 'share-company-tile').listeners.click(); await flush();
    const dialog = collect(body).find(node => node.tag === 'dialog');
    collect(dialog).filter(node => node.role === 'tab')[1].listeners.click(); await flush();
    collect(dialog).find(node => node['aria-label']?.startsWith('Aankoop ')).listeners.click();
    const form = collect(dialog).find(node => node.tag === 'form');
    const [year, quarter] = collect(form).filter(node => node.tag === 'select');
    assert.deepEqual(year.children.map(node => node.value), ['1925', '1926', '1927'], 'Years without any future company snapshot are excluded');
    assert.equal(year.value, '1925'); assert.equal(quarter.value, '1');
    assert(collect(form).some(node => String(node.textContent).includes('Company snapshot 1927')), '1925 skips events without this company snapshot');
    year.value = '1927'; year.listeners.change();
    assert.deepEqual(quarter.children.map(node => node.value), ['1', '2'], 'Quarters after the final company snapshot are excluded');
    year.value = '1926'; year.listeners.change(); quarter.value = '1'; quarter.listeners.change();
    await form.listeners.submit({preventDefault() {}}); await flush();
    const update = app.writes.find(write => write.url.includes('updateSharePurchase'));
    assert(update && update.body.purchaseYear === 1926 && update.body.purchaseQuarter === 1, 'An earlier quarter can be saved across missing snapshots');
}
async function checkPrivateOfferCompanyContext() {
    const app = setup('director'), {context, body} = app;
    context.apiFetchJson = async url => url.includes('getCompanyShares') ? {
        company: {id: 5, companyName: 'Abeir'}, canManageShares: true, myBoardCharacterIds: [],
        stock: {issued: 100, held: 0, free: 100}, holdings: [],
        snapshots: [{id: 15, idEvent: 19, eventTitle: 'Holiday', eventDate: '2027-05-15', shareMarketPrice: '1100.00'}],
        managerPrivateOffers: [{id: 4, idCompany: 5, offeredShareCount: 10, purchasedShareCount: 0,
            unitPrice: '1200.00', recipientNames: 'Quinten Delius'}],
    } : {recipients: [{id: 14, firstName: 'Quinten', lastName: 'Delius'}]};
    await context.ui.attachCompany(body, 5);
    collect(body).find(node => node.textContent === 'Privéaanbod').listeners.click();
    await flush();
    const dialog = collect(body).find(node => node.tag === 'dialog');
    const selects = collect(dialog).filter(node => node.tag === 'select');
    assert.equal(selects.length, 2, 'Only snapshot and recipient selectors are visible on a company offer');
    assert(collect(dialog).some(node => node.textContent === 'Quinten Delius'), 'Open offers show the recipient name');
    assert(!collect(dialog).some(node => node.textContent === 'Sluiten'), 'The frame provides the only close control');
}
async function checkAutomaticCompanyResearch(role='participant') {
    const app = setup(role), {context, body} = app;
    let researched = false; const opened=[];
    context.aetherCompanyResearch = {reset() {}, hasDraft:()=>false, mount:async(host, options)=>{opened.push(options);host.appendChild(options.backButton);host.appendChild(options.shareContent);}};
    context.apiFetchJson = async url => {
        app.calls.push(url);
        if(url.includes('getShareMarketVersion')) return {version:researched?'after':'before'};
        if(url.includes('getShareRecipientOptions')) return {recipients:[]};
        return {currentEvent:{id:7,title:'Holiday',dateStart:'2027-05-15',dateEnd:'2027-05-17'},
            events:[{id:7,idSearch:1,assessedCompanies:4,discoveredCompanies:3}],canTradeAtEvent:true,
            researchCompanies:[{idCompany:5,companyName:'Company'}],discoveries:[],offers:[],canEditAssessments:role!=='participant',
            assessments:[{idCompany:5,isShareListed:1,shareMarketPrice:'10.00',freeShareCount:20}]};
    };
    context.apiFetchIdempotentJson = async(url, options)=>{app.writes.push({url,body:options.body});researched=true;};
    context.market.attachActions(body,{id:21,idUser:1,type:'player',state:'active',skills:[{id:6,level:1}]},3);
    await flush();await flush();
    if(role==='participant') assert(app.calls.every(url=>!url.includes('idEvent=3')), 'Participants use the current event for company research');
    assert.equal(app.writes.length,0,'Opening the Economy action does not research all companies');
    const host=body.children[0];
    assert(collect(host).some(node=>node.textContent==='Onderzoek bedrijf'));
    assert(!collect(host).some(node=>String(node.textContent).includes('bedrijven onderzocht')));
    assert(!collect(host).some(node=>node.textContent==='Aandelenmarkt doorzoeken'));
    await collect(host).find(node=>node.className==='share-company-tile').listeners.click();
    await flush();
    assert.deepEqual(JSON.parse(JSON.stringify(app.writes[0].body)),{idCharacter:21,idEvent:7,idCompany:5});
    assert.equal(opened.at(-1).companyId,5,'The researched company opens after the result is stored');
    assert(!collect(host).some(node=>node.textContent==='Onderzoek bedrijf'),'The list title disappears in the company detail view');
    assert(collect(host).some(node=>node.textContent==='Alle bedrijven tonen'),'The company detail view provides a back button');
    assert(!collect(host).some(node=>node.textContent==='Edit'),'The detail header has no nonfunctional edit button');
    const add=collect(host).find(node=>node.textContent==='Gevonden aandelen toevoegen');
    assert.equal(Boolean(add),role!=='participant','Only managers can add found shares');
    if(add){
        add.listeners.click();
        const form=collect(host).find(node=>node.tag==='form');
        const inputs=collect(form).filter(node=>node.tag==='input');inputs[0].value='4';inputs[1].value='9,25';
        await form.listeners.submit({preventDefault(){}});
        const write=app.writes.find(item=>item.url.includes('saveShareDiscovery'));
        assert.deepEqual(JSON.parse(JSON.stringify(write.body)),{idCharacter:21,idCompany:5,idEvent:7,shareCount:4,unitPrice:'9.25'});
    }
}
(async () => {
    for (const role of ['participant','director','administrator']) await checkMarket(role);
    await checkMarket('participant', 'extra');
    await checkManagerForm();
    for (const role of ['participant','director','administrator']) await checkDraftCapital(role);
    await checkHoldingDialog(true);
    await checkHoldingDialog(false);
    await checkPurchaseEditor('director');
    await checkPurchaseEditor('administrator');
    await checkManagerHoldingRemoval();
    await checkLegacyPurchaseTab();
    for (const role of ['participant', 'director', 'administrator']) await checkCompanyShareholders(role);
    for (const role of ['participant', 'director', 'administrator']) await checkCompanyOwnership(role);
    await checkCompanySnapshotPeriods();
    await checkPrivateOfferCompanyContext();
    await checkAutomaticCompanyResearch();
    await checkAutomaticCompanyResearch('director');
    for (const page of ['index.html','admin.html','companies.html','eventParticipation.html','static.html','leaderPersonnel.html']) {
        const nav = fs.readFileSync(page, 'utf8').match(/<nav\b[\s\S]*?<\/nav>/)[0];
        assert(!/Personages|Evenementen|Bedrijven|Personeelsbeheer/.test(nav), `${page} only retains Admin and greeting`);
        assert(!nav.includes('navbarAdminItem') && nav.includes('navbarName'));
    }
    console.log('Share purchase UI, historical event selection and navbar tests passed.');
})().catch(error => {console.error(error); process.exitCode = 1;});
