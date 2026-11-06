const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
    constructor() {
        this.dataset = {}; this.children = []; this.value = ''; this.disabled = false;
        this.classes = new Set(); this.labels = [{textContent: 'Bedrag'}];
        this.classList = {add: key => this.classes.add(key), remove: key => this.classes.delete(key)};
    }
    appendChild(child) { this.children.push(child); return child; }
    checkValidity() { return true; }
    focus() { this.focused = true; }
}
const elements = Object.fromEntries(['Modal','Error','Confirm','Value','Shares','Price','Stability','Profitability']
    .map(name => [`companySnapshotCreate${name}`, new Element()]));
elements.companySnapshotCreateModal.dataset = {idCompany: '4', idEvent: '8'};
const writes = [];
const context = {
    console, window: {addEventListener() {}},
    document: {getElementById: id => elements[id], createElement: () => new Element()},
    bootstrap: {Modal: {getOrCreateInstance: () => ({hide() {}})}},
    apiFetchFinancialJson: async (url, options) => { writes.push({url, body: JSON.parse(JSON.stringify(options.body))}); },
};
vm.createContext(context);
vm.runInContext(fs.readFileSync('js/companyFunctions.js', 'utf8'), context);
context.applyCompanySnapshotResponse = async () => {};
context.showCompanyFeedback = () => {};

function values(companyValue, price) {
    elements.companySnapshotCreateValue.value = companyValue;
    elements.companySnapshotCreatePrice.value = price;
    elements.companySnapshotCreateShares.value = '30000';
    elements.companySnapshotCreateStability.value = '0';
    elements.companySnapshotCreateProfitability.value = '0';
}

(async () => {
    values('7308257,13', '5481,25');
    await context.submitCompanySnapshotCreation();
    assert.equal(writes.length, 1, 'Comma amounts must be accepted');
    assert.equal(writes[0].body.companyValue, '7308257.13', 'Company value must cross JSON as exact decimal text');
    assert.equal(writes[0].body.shareMarketPrice, '5481.25', 'Share price must cross JSON as exact decimal text');
    assert.equal(writes[0].body.issuedShareCount, 30000, 'Integer fields stay integers');

    values('7.308.257,13', '5481.25');
    await context.submitCompanySnapshotCreation();
    assert.equal(writes.at(-1).body.companyValue, '7308257.13', 'Belgian thousands separators are supported');
    assert.equal(writes.at(-1).body.shareMarketPrice, '5481.25', 'A decimal point is also supported');

    values('7\u202f308\u202f257,13', '0,1');
    await context.submitCompanySnapshotCreation();
    assert.equal(writes.at(-1).body.companyValue, '7308257.13', 'Spaces used as thousands separators are supported');
    assert.equal(writes.at(-1).body.shareMarketPrice, '0.10');

    for (const invalid of ['7308257,131', '12abc', '-1,00', '10000000000,00', '']) {
        const before = writes.length;
        values(invalid, '12,50');
        await context.submitCompanySnapshotCreation();
        assert.equal(writes.length, before, `Invalid amount ${JSON.stringify(invalid)} must be rejected before sending`);
        assert(!elements.companySnapshotCreateError.classes.has('d-none'));
    }
    values('0', '');
    await context.submitCompanySnapshotCreation();
    assert.equal(writes.at(-1).body.companyValue, '0.00');
    assert.equal(writes.at(-1).body.shareMarketPrice, undefined, 'Unknown share price remains optional');

    const parent = new Element();
    const moneyInput = context.appendSnapshotInput(parent, 'companyValue', 'Bedrijfswaarde', 7308257.13);
    assert.equal(moneyInput.type, 'text', 'Existing snapshot editors must accept commas too');
    assert.equal(moneyInput.inputMode, 'decimal');
    assert.equal(moneyInput.value, '7308257,13');
    assert.equal(context.appendSnapshotInput(parent, 'issuedShareCount', 'Aantal', 30000, {integer: true}).type, 'number');

    console.log('Snapshot comma input and exact two-decimal JSON amounts passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
