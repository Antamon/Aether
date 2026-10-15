const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
    constructor(tag) {
        this.tag = tag; this.children = []; this.dataset = {}; this.listeners = {};
        this.isConnected = true; this.open = false;
        this.classList = { add() {} };
    }
    appendChild(child) { this.children.push(child); return child; }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; }
    addEventListener(name, handler) { (this.listeners[name] ||= []).push(handler); }
    querySelectorAll(selector) {
        return this.children.flatMap(child => [
            ...(child.tag === 'details' && (selector === 'details' || child.open) ? [child] : []),
            ...child.querySelectorAll(selector)
        ]);
    }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
    setAttribute() {}
}
let value = 100;
let gate;
const context = {
    document: { documentElement: { lang: 'nl' }, createElement: tag => new Element(tag), addEventListener() {} },
    window: { setInterval() {}, addEventListener() {} },
    getCurrentUser: () => ({ id: 1 }), userHasPrivilegedRole: () => true,
    AetherActionButtons: { button: () => new Element('button'), group: children => {
        const group = new Element('div'); group.append(...children); return group;
    } },
    apiFetchJson: async url => {
        if (gate) await gate;
        return url.includes('getCharacterPortfolio') ? {
            status: { intrinsicPortfolioValue: value },
            holdings: [{ idCompany: 1, companyName: 'Company', shareCount: 2 }]
        } : { offers: [] };
    }
};
vm.createContext(context);
vm.runInContext(fs.readFileSync('js/shareInterface.js', 'utf8') + '\nthis.ui = aetherShareUi;', context);
const flush = () => new Promise(resolve => setImmediate(resolve));
(async () => {
    const card = context.ui.attachPortfolio(new Element('div'), { id: 15, state: 'active' });
    await flush();
    assert(!card.className.includes('p-3'), 'Portfolio uses the standard panel padding');
    const content = card.children[0];
    const offers = new Element('section'); card.appendChild(offers);
    const form = content.querySelectorAll('details')[1];
    form.open = true;
    const draft = new Element('input'); draft.value = 'Draft offer'; form.appendChild(draft);
    value = 200;
    await card.aetherRefresh();
    assert.equal(content.querySelectorAll('details')[1], form, 'Polling preserves the open form');
    assert.equal(draft.value, 'Draft offer');
    form.open = false;
    await card.aetherRefresh();
    assert.notEqual(content.querySelectorAll('details')[1], form, 'Closed cards receive fresh data');
    assert.equal(card.children[1], offers, 'Available offers survive portfolio updates');
    const updatedForm = content.querySelectorAll('details')[1];
    // Opening the form while a request is in flight must also preserve it.
    let release;
    gate = new Promise(resolve => { release = resolve; });
    value = 300;
    const pending = card.aetherRefresh();
    await flush();
    updatedForm.open = true;
    release();
    await pending;
    gate = null;
    assert.equal(content.querySelectorAll('details')[1], updatedForm, 'In-flight refresh preserves a newly opened form');
    console.log('Portfolio refresh checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
