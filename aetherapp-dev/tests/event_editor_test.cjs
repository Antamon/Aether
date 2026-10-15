const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
    constructor(tag) {
        this.tag = tag; this.children = []; this.dataset = {}; this.listeners = {}; this.value = '';
        this.classes = new Set();
        this.classList = { add: (...names) => names.forEach(n => this.classes.add(n)), remove: n => this.classes.delete(n),
            contains: n => this.classes.has(n), toggle: (n, force) => force ? this.classes.add(n) : this.classes.delete(n) };
    }
    set className(value) { this.classes = new Set(value.split(/\s+/)); }
    get className() { return [...this.classes].join(' '); }
    append(...children) { children.forEach(child => { child.parent = this; this.children.push(child); }); }
    replaceChildren(...children) { this.children = []; this.append(...children); }
    remove() { this.parent.children = this.parent.children.filter(child => child !== this); }
    setAttribute() {}
    addEventListener(name, callback) { this.listeners[name] = callback; }
    querySelectorAll(selector) {
        const match = el => selector.startsWith('.') ? selector.slice(1).split('.').every(n => el.classes.has(n)) : el.tag === selector;
        return this.children.flatMap(child => [...(match(child) ? [child] : []), ...child.querySelectorAll(selector)]);
    }
}
const host = new Element('div'); host.id = 'eventDetailsView';
const find = (node, id) => node.id === id ? node : node.children.map(child => find(child, id)).find(Boolean);
let mutation, shownModal, snapshotMissing = false;
const pageBody = new Element('body');
const context = {
    console, Intl, window: { confirm: () => true, location: {}, scrollX: 0, scrollY: 220, scrollTo: position => { assert.equal(position.top, 220); } },
    document: { body: pageBody, createElement: tag => new Element(tag), getElementById: id => find(host, id) },
    AetherActionButtons: {
        button: options => { const node = new Element('button'); node.options = options; node.disabled = !!options.disabled; if (options.onClick) node.addEventListener('click', options.onClick); return node; },
        group: children => { const node = new Element('div'); node.append(...children); return node; }
    },
    bootstrap: { Modal: class { constructor(node) { this.node = node; } show() { shownModal = this.node; } dispose() {} } },
    apiFetchJson: async (url, options) => {
        mutation = options.body;
        const identity = { firstName: 'Ada', eventTitle: '<script>Event</script>' };
        if (options.body.action === 'snapshot') return snapshotMissing ? { identity, details: null, message: 'Financiële toestand nog niet aangepast voor "Event".' }
            : { identity, details: { amount: '12.34', transactionDate: '2026-09-02' } };
        if (options.body.action === 'gossip') return { identity, details: { gossip1: '<b>Private gossip</b>', gossip2: 'Two', gossip3: 'Three' } };
        return { ok: true };
    }
};
vm.createContext(context);
vm.runInContext(fs.readFileSync('js/eventDetails.js', 'utf8'), context);
const data = { event: { id: 7, title: 'Event', type: 'weekend', dateStart: '2026-09-01', dateEnd: '2026-09-02', ep: 1 }, canManage: true,
    characters: [ { id: 1, type: 'player', firstName: 'Ada', linked: true, canOpen: true, hasSnapshot: true, gossipComplete: false }, { id: 2, type: 'player', firstName: 'Bob', linked: false, canOpen: false }, { id: 3, type: 'extra', firstName: 'Eve', linked: true } ] };
(async () => {
    context.renderEventDetails(data);
    assert.equal(host.querySelectorAll('input').length, 0, 'Display mode must show plain event values');
    assert.equal(host.querySelectorAll('.event-character-panel').length, 2);
    assert.equal(host.querySelectorAll('.aether-panel').length, 1, 'Event and both rosters must share one ornamental panel');
    assert.equal(host.querySelectorAll('.event-character-status').length, 4, 'Managers see camera and gossip status per linked character');
    assert.equal(host.querySelectorAll('.event-character-status.is-complete').length, 1);
    context.renderEventDetails(data, true);
    const description = host.querySelectorAll('textarea')[0]; description.value = 'Unsaved event description';
    const panel = host.querySelectorAll('.event-character-panel')[0];
    const collapse = panel.querySelectorAll('.accordion-collapse')[0]; collapse.classList.add('show');
    const originalCard = panel.querySelectorAll('.event-character-card')[0];
    const otherPanel = host.querySelectorAll('.event-character-panel')[1];
    const select = panel.querySelectorAll('select')[0]; select.value = '2';
    panel.querySelectorAll('button').find(button => button.options?.label === 'Koppelen').listeners.click();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(mutation.idEvent, 7); assert.equal(mutation.idCharacter, 2); assert.equal(mutation.linked, true);
    assert.equal(host.querySelectorAll('textarea')[0], description, 'Roster mutation must preserve the event editor');
    assert.equal(description.value, 'Unsaved event description');
    assert.equal(data.characters[1].linked, true);
    assert.equal(host.querySelectorAll('.event-character-panel')[0], panel);
    assert.equal(panel.querySelectorAll('.accordion-collapse')[0], collapse);
    assert.equal(panel.querySelectorAll('.event-character-card')[0], originalCard);
    assert.equal(host.querySelectorAll('.event-character-panel')[1], otherPanel);
    originalCard.querySelectorAll('.event-character-status')[0].listeners.click();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(mutation.action, 'snapshot'); assert.equal(mutation.idCharacter, 1);
    assert.equal(shownModal.querySelectorAll('.event-character-modal-value')[1].textContent, '12,34 Fr');
    originalCard.querySelectorAll('.event-character-status')[1].listeners.click();
    assert.equal(context.window.location.href, 'index.html?character=1&tab=diary&event=7&focus=gossip');
    panel.querySelectorAll('.event-character-card')[1].querySelectorAll('.event-character-status')[0].listeners.click();
    assert.equal(context.window.location.href, 'index.html?character=2&tab=economy&event=7&focus=snapshot');
    panel.querySelectorAll('.event-character-card')[1].querySelectorAll('.event-character-remove')[0].listeners.click();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(data.characters[1].linked, false);
    assert.equal(panel.querySelectorAll('.event-character-card').length, 1);
    assert.equal(panel.querySelectorAll('.event-character-card')[0], originalCard);
    data.characters[1].linked = true;
    assert.equal(host.querySelectorAll('.aether-panel').length, 1, 'Roster updates must keep one panel');
    const participantData = { ...data, canManage: false, characters: [
        { ...data.characters[0], canViewStatus: true, canLink: true, gossipComplete: true, hasSnapshot: false },
        { ...data.characters[1], canViewStatus: false, canLink: false },
        { id: 4, type: 'player', firstName: 'Eligible', linked: false, canOpen: true, canViewStatus: true, canLink: true },
        { id: 5, type: 'extra', firstName: 'Own extra', linked: true, canOpen: true, canViewStatus: true, canLink: false },
    ] };
    context.renderEventDetails(participantData);
    assert.equal(host.querySelectorAll('.event-character-panel').length, 2, 'Own linked extras can be viewed');
    assert.equal(host.querySelectorAll('.event-character-status').length, 4, 'Only own characters show snapshot and gossip status');
    assert.equal(host.querySelectorAll('.event-character-remove').length, 0);
    const cards = host.querySelectorAll('.event-character-card');
    assert.equal(cards.length, 3);
    assert.equal(cards[0].querySelectorAll('a').length, 1, 'Own portrait and name are one link');
    assert.equal(cards[1].querySelectorAll('a').length, 0, 'Other players are visible without a link');
    assert.equal(cards[1].querySelectorAll('.event-character-status').length, 0, 'Other players have no private status icons');
    const participantPanels = host.querySelectorAll('.event-character-panel');
    const ownSelect = participantPanels[0].querySelectorAll('select')[0];
    assert.equal(ownSelect.children.length, 2, 'Only one eligible unlinked player may be selected');
    assert.equal(participantPanels[1].querySelectorAll('select').length, 0, 'Participants cannot link extras');
    ownSelect.value = '4';
    participantPanels[0].querySelectorAll('button').find(button => button.options?.label === 'Koppelen').listeners.click();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(mutation.idCharacter, 4); assert.equal(mutation.linked, true);
    assert.equal(host.querySelectorAll('.event-character-panel')[0], participantPanels[0], 'Participant linking stays asynchronous');
    snapshotMissing = true;
    cards[0].querySelectorAll('.event-character-status')[0].listeners.click();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(mutation.action, 'snapshot');
    assert.equal(shownModal.querySelectorAll('.event-character-modal-message')[0].textContent, 'Financiële toestand nog niet aangepast voor "Event".');
    assert.equal(shownModal.querySelectorAll('.event-character-modal-value').length, 0);
    cards[0].querySelectorAll('.event-character-status')[1].listeners.click();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(mutation.action, 'gossip');
    assert.equal(shownModal.querySelectorAll('.event-character-modal-value')[0].textContent, '<b>Private gossip</b>', 'Gossip remains safe plain text in the modal');
    cards[2].querySelectorAll('.event-character-status')[1].listeners.click();
    assert.equal(context.window.location.href, 'index.html?character=5&tab=diary&event=7&focus=gossip');
    console.log('Event editor checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
