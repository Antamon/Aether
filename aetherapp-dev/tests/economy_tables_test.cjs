const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
    constructor(tag) {
        this.tag = tag; this.children = []; this.dataset = {}; this.listeners = {}; this.attributes = {}; this.classes = new Set(); this.style = {}; this.isConnected = true;
        this.classList = {
            add: (...names) => names.forEach(name => this.classes.add(name)),
            remove: name => this.classes.delete(name),
            contains: name => this.classes.has(name),
            toggle: (name, force) => {
                const enabled = force === undefined ? !this.classes.has(name) : Boolean(force);
                if (enabled) this.classes.add(name); else this.classes.delete(name);
                return enabled;
            }
        };
    }
    set className(value) { this.classes = new Set(value.split(/\s+/)); }
    get childNodes() { return this.children; }
    append(...nodes) { nodes.forEach(node => { node.parent = this; this.children.push(node); }); }
    appendChild(node) { this.append(node); return node; }
    replaceChildren(...nodes) { this.children = []; this.append(...nodes); }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    setAttribute(name, value) { this.attributes[name] = value; }
    remove() { this.parent.children = this.parent.children.filter(node => node !== this); this.isConnected = false; }
    focus() { this.focused = true; }
    querySelectorAll(selector) { return this.children.flatMap(child => [...((selector.startsWith('.') ? child.classes.has(selector.slice(1)) : child.tag === selector) ? [child] : []), ...child.querySelectorAll(selector)]); }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
}
const body = new Element('body'); const writes = []; const refreshes = [];
let lastModal, confirmation = true;
const context = { console, Intl, window: { confirm: () => confirmation }, currentUser: {role: 'administrator'}, currentCharacter: {id: 15, state: 'active'},
    document: {body, createElement: tag => new Element(tag), addEventListener() {}},
    formatCharacterCurrency: value => Number(value).toFixed(2) + ' Fr',
    apiFetchFinancialJson: async (url, options) => writes.push({url, body: options.body}),
    getCharacter: async (...args) => refreshes.push(args), alert: message => { throw Error(message); },
    bootstrap: {Modal: class {
        constructor(element) {
            assert(element.querySelector('.modal-dialog'), 'Build the dialog before Bootstrap caches its elements');
            assert.equal(element.parent, body, 'Attach the complete modal before initializing Bootstrap');
            this.element = element;
        }
        show() { lastModal = this.element; this.element.open = true; }
        hide() { this.element.open = false; this.element.listeners['hidden.bs.modal'](); }
        dispose() { this.element.disposed = true; }
    }}
};
vm.createContext(context);
for (const file of ['js/actionButtons.js','js/dataTable.js','js/economyCharacter.js','js/traitsCharacter.js']) vm.runInContext(fs.readFileSync(file, 'utf8'), context);
const snapshots = Array.from({length: 13}, (_, index) => ({id: index + 1, idEvent: index + 1, eventTitle: 'Event ' + (index + 1),
    transactionDate: '2026-03-' + String(index + 1).padStart(2, '0'), amount: (index + 1) * 10,
    securitiesBalanceSnapshot: 1000, securitiesReturnAmount: index + 1, securitiesStatus: 'pending', securitiesManagerType: 'self'}));
const character = {id: 15, firstName: 'Ada', lastName: 'A', canCreateEconomySnapshots: true, canApproveSecuritiesSnapshots: true,
    canDeleteBankTransactions: true, economySnapshots: snapshots, bankTransactions: snapshots.map(snapshot => ({...snapshot,
        type: 'bank_transfer', direction: 'outgoing', amount: -snapshot.amount, counterpartyName: 'Begunstigde ' + snapshot.id,
        description: '<img src=x onerror=alert(1)>', canDelete: true}))};
function host() { const value = new Element('section'); body.append(value); return value; }
function clickInfo(table) { const button = table.querySelector('tbody').children[0].querySelector('.data-info-button'); button.listeners.click(); return lastModal; }
function buttonNamed(modal, text) { return modal.querySelectorAll('button').find(button => button.querySelector('span')?.textContent === text); }
function checkPagination(root, columns) {
    const table = root.querySelector('table'), tbody = table.querySelector('tbody');
    assert(table.classes.has('aether-table'));
    assert.equal(table.querySelector('thead').children[0].children.length, columns);
    assert.equal(tbody.children.length, 10);
    const pagination = root.querySelector('nav'); const [previous, next] = pagination.querySelectorAll('button');
    assert(previous.disabled && !next.disabled);
    next.listeners.click(); assert.equal(tbody.children.length, 3); assert(!previous.disabled && next.disabled);
    assert.equal(pagination.querySelector('span').textContent, 'Pagina 2 van 2');
    previous.listeners.click(); assert.equal(tbody.children.length, 10);
    return table;
}
(async () => {
    const bank = host(); context.renderTransactionsSection(bank, character);
    const bankTable = checkPagination(bank, 4);
    assert.equal(bankTable.querySelector('tbody').children[0].children[1].textContent, 'Begunstigde 13', 'Newest transaction must appear first');
    assert.equal(bankTable.querySelector('tbody').children[0].children[2].children[0].textContent, '-130.00 Fr', 'Negative amounts must not get a double minus');
    const transfer = clickInfo(bankTable);
    assert(transfer.querySelectorAll('dd').some(node => node.textContent === '<img src=x onerror=alert(1)>'));
    assert.equal(transfer.querySelectorAll('img').length, 0, 'Transfer descriptions must remain safe text');
    confirmation = false;
    await buttonNamed(transfer, 'Verwijderen').listeners.click(); assert.equal(writes.length, 0); assert(transfer.open);
    confirmation = true;
    await buttonNamed(transfer, 'Verwijderen').listeners.click(); assert(!transfer.open && transfer.disposed);
    assert.equal(writes[0].body.idTransaction, 13); assert.equal(refreshes[0][2], 'economy');
    const securities = host(); context.renderSecuritiesSnapshotsSection(securities, character);
    const returns = checkPagination(securities, 3);
    assert.equal(returns.querySelector('tbody').children[0].children[0].textContent, 'Event 13');
    const returnModal = clickInfo(returns);
    assert(buttonNamed(returnModal, 'Opnieuw genereren') && buttonNamed(returnModal, 'Goedkeuren'));
    await buttonNamed(returnModal, 'Goedkeuren').listeners.click(); assert(!returnModal.open);
    assert.equal(writes.at(-1).body.action, 'approve_snapshot'); assert.equal(writes.at(-1).body.idSnapshot, 13);
    const economy = host(); context.renderCharacterEconomySnapshotsSection(economy, character);
    const economyTable = checkPagination(economy, 3);
    const snapshotModal = clickInfo(economyTable);
    assert(snapshotModal.querySelectorAll('dt').some(node => node.textContent === 'Saldo effectenportefeuille'));
    await buttonNamed(snapshotModal, 'Verwijderen').listeners.click(); assert(!snapshotModal.open);
    assert.equal(writes.at(-1).url, 'api/characters/deleteCharacterEconomySnapshot.php'); assert.equal(writes.at(-1).body.idSnapshot, 13);
    const readOnly = host(); context.renderSecuritiesSnapshotsSection(readOnly, {...character, canApproveSecuritiesSnapshots: false});
    const view = clickInfo(readOnly.querySelector('table'));
    assert(!buttonNamed(view, 'Goedkeuren') && !buttonNamed(view, 'Verwijderen'), 'Read-only users must not get financial mutation buttons');
    const single = host(); context.renderTransactionsSection(single, {...character, bankTransactions: [character.bankTransactions[0]]});
    assert.equal(single.querySelectorAll('nav').length, 0, 'One page needs no pagination');
    const securitiesSettings = host();
    context.renderSecuritiesSettingsSection(securitiesSettings, {...character,
        canManageSecurities: true,
        securitiesRiskProfile: 4,
        securitiesManagerOptions: [{id: 22, displayName: 'Actieve beheerder'}],
        securitiesRiskProfileOptions: {4: {label: 'Dynamisch'}}
    });
    const riskSlider = securitiesSettings.querySelector("input");
    assert.equal(riskSlider.type, 'range', 'The portfolio risk profile must be adjustable with a slider');
    assert.equal(riskSlider.value, '4');
    assert(!riskSlider.disabled, 'The character owner may adjust the portfolio risk profile');
    assert.equal(securitiesSettings.querySelector("output").textContent, 'Dynamisch');
    assert.equal(securitiesSettings.querySelectorAll('select').length, 0, 'Manager selectors belong in the modal');
    const settings = securitiesSettings.querySelector('.economy-securities-settings');
    assert(settings.children[1].classes.has('economy-securities-risk'));
    assert(settings.children[2].classes.has('economy-securities-expected'), 'Expected return follows the risk profile directly');
    const readonlySettings = host();
    context.renderSecuritiesSettingsSection(readonlySettings, {...character, canManageSecurities: false});
    assert.equal(readonlySettings.querySelectorAll('input').length, 0, 'Viewers must not see a disabled risk slider');
    assert.equal(readonlySettings.querySelectorAll('button').length, 0, 'Viewers must not see manager edit controls');
    assert(readonlySettings.querySelector('output'), 'Viewers still see the risk profile name');
    context.currentCharacter = {...character, canManageSecurities: true, securitiesRiskProfile: 4,
        securitiesManagerType: 'self', securitiesManagerOptions: [{id: 22, displayName: 'Actieve beheerder'}]};
    context.document.querySelector = () => riskSlider;
    const managerEdit = securitiesSettings.querySelector('.economy-securities-manager-edit');
    managerEdit.listeners.click({currentTarget: managerEdit});
    const managerModal = lastModal;
    const [managerSelect, thirdSelect] = managerModal.querySelectorAll('select');
    assert.equal(thirdSelect.children[1].textContent, 'Actieve beheerder');
    const countBeforeSettings = writes.length;
    managerSelect.value = 'third';
    managerSelect.listeners.change();
    assert.equal(thirdSelect.parent.hidden, false);
    assert.equal(writes.length, countBeforeSettings, 'Changing a modal selection does not save before confirmation');
    await buttonNamed(managerModal, 'Save').listeners.click();
    assert.equal(writes.length, countBeforeSettings, 'A third-party manager must be selected before saving');
    thirdSelect.value = '22';
    await buttonNamed(managerModal, 'Save').listeners.click();
    assert.equal(writes.at(-1).body.managerType, 'third');
    assert.equal(writes.at(-1).body.managerCharacterId, 22);
    assert.equal(writes.at(-1).body.riskProfile, 4, 'Changing manager preserves the selected risk profile');
    assert(!managerModal.open && managerModal.disposed);
    context.openSecuritiesManagerModal(context.currentCharacter, managerEdit);
    const cancelModal = lastModal;
    const countBeforeCancel = writes.length;
    assert.equal(buttonNamed(cancelModal, 'Cancel').dataset.bsDismiss, 'modal');
    cancelModal.listeners['hidden.bs.modal']();
    assert.equal(writes.length, countBeforeCancel, 'Cancel discards unsaved manager changes');
    const trait = {id: 7, idTrait: 7, name: 'Roem', cost: 2, rank: 1, rankType: 'range_positive', traitFlags: {}};
    const group = {options: [trait], linkedTraits: [trait]};
    const labelRow = context.createLinkedTraitRow(trait, group, true, {nameAsLabel: true});
    assert.equal(labelRow.querySelectorAll('select').length, 0); assert.equal(labelRow.querySelector('.trait-name-label').textContent, 'Roem (2)');
    assert.equal(labelRow.querySelectorAll('button').length, 4, 'Book, rank and delete controls remain available');
    const editRow = context.createLinkedTraitRow(trait, group, true);
    assert.equal(editRow.querySelectorAll('select').length, 1, 'The personal-details editor can still select a profession or title');
    console.log('Economy table pagination, modal details/actions, permissions and trait label tests passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
