const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

class Element {
    constructor(tag) {
        this.tag = tag; this.children = []; this.listeners = {}; this.value = ''; this.style = {}; this.dataset = {};
        this.classes = new Set();
        this.classList = {
            add: (...names) => names.forEach(name => this.classes.add(name)),
            remove: (...names) => names.forEach(name => this.classes.delete(name)),
            contains: name => this.classes.has(name),
            toggle: (name, force) => force ? this.classes.add(name) : this.classes.delete(name)
        };
    }
    set className(value) { this.classes = new Set(value.split(/\s+/)); }
    get className() { return [...this.classes].join(' '); }
    set innerHTML(value) { this.html = value; this.children = []; }
    appendChild(child) { child.parent = this; this.children.push(child); return child; }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; }
    addEventListener(name, handler) { this.listeners[name] = handler; }
    setAttribute() {}
    remove() { this.parent.children = this.parent.children.filter(child => child !== this); }
    scrollIntoView() { this.scrolled = true; }
    focus() { this.focused = true; }
    querySelector(selector) {
        return find(this, node => selector.startsWith('option') ? node.tag === 'option' && String(node.value) === selector.match(/value="(\d+)"/)[1]
            : node.tag === 'textarea' && !!node.dataset.gossip && !node.classes.has('d-none'));
    }
}
const editors = [];
let payload;
const context = {
    console, window: {}, requestAnimationFrame: callback => callback(), currentUser: { role: 'participant', id: 10 },
    document: { createElement: tag => new Element(tag), addEventListener() {} },
    renderCharacterRichText: (element, html) => { element.innerHTML = html; },
    createCharacterRichTextEditor: html => {
        const editor = { wrapper: new Element('div'), html, getHtml() { return this.html; }, setHtml(value) { this.html = value; } };
        editors.push(editor); return editor;
    },
    AetherActionButtons: { button: options => { const button = new Element('button'); button.textContent = options.label; button.disabled = !!options.disabled; return button; } },
    apiFetchJson: async (url, options) => {
        payload = options.body;
        return { entries: [{ ...payload, id: payload.idDiary || 99, achievements: 'Sanitized achievements' }] };
    },
    alert: message => { throw new Error(message); }
};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../js/diaryCharacter.js'), 'utf8'), context);
const find = (element, predicate) => predicate(element) ? element : element.children.map(child => find(child, predicate)).find(Boolean);

(async () => {
    const entries = [{ id: 1, idEvent: 100, dateStart: '2013-01-01', eventTitle: 'Older' },
        { id: 2, idEvent: 20, dateStart: '2026-01-01', eventTitle: 'Newer' }];
    const container = new Element('div');
    context.renderDiaryEntries(container, { id: 15, type: 'player', idUser: 10 }, entries, []);
    assert.equal(container.children.length, 1, 'Diary shares one decorative panel');
    const panel = container.children[0];
    assert(panel.classList.contains('aether-panel'));
    assert(find(panel.children[0], node => node.tag === 'select'), 'Event selector precedes the diary cards');
    assert.equal(find(panel.children[1], node => node.tag === 'h5').textContent, 'Newer');
    assert.equal(find(panel.children[2], node => node.tag === 'h5').textContent, 'Older');

    editors.length = 0;
    const entry = { id: 7, idEvent: 20, goals: 'Goals', achievements: 'Achievements', gossip1: 'Private gossip' };
    const card = context.renderDiaryEntry(entry, { all: false, achievementsOnly: true }, { id: 15 });
    assert(card.classList.contains('data-card'));
    assert(!card.classList.contains('aether-panel'));
    const edit = find(card, node => node.textContent === 'Edit');
    edit.listeners.click();
    assert(card.classList.contains('is-editing'));
    editors[1].html = 'Unsaved';
    find(card, node => node.textContent === 'Cancel').listeners.click();
    assert.equal(editors[1].html, 'Achievements', 'Cancel resets the editor');
    assert(!card.classList.contains('is-editing'));
    context.currentUser = { role: 'director', id: 20 };
    context.window.AETHER_EVENT_TARGET = { character: 15, event: 100, kind: 'gossip' };
    context.renderDiaryEntries(container, { id: 15 }, entries, []);
    const targetCard = find(container, node => node.classes.has('diary-entry') && find(node, child => child.textContent === 'Older'));
    assert(targetCard.classes.has('is-editing'), 'Deep link must edit only the requested event');
    assert(targetCard.scrolled && find(targetCard, node => node.dataset.gossip === 'gossip1').focused);
    assert.equal(context.window.AETHER_EVENT_TARGET, null, 'Target is consumed once');
    payload = null;
    context.window.AETHER_EVENT_TARGET = { character: 15, event: 300, kind: 'gossip' };
    context.renderDiaryEntries(container, { id: 15 }, [], [{ id: 300, title: 'New gossip', dateStart: '2026-10-01' }]);
    const draft = find(container, node => node.classes.has('diary-entry'));
    assert(draft.classes.has('is-editing') && payload === null, 'Missing gossip opens a local draft without a write');
    find(draft, node => node.dataset.gossip === 'gossip1').value = 'New gossip text';
    await find(draft, node => node.textContent === 'Save').listeners.click();
    assert.equal(payload.idEvent, 300); assert.equal(payload.gossip1, 'New gossip text');
    assert(!draft.classes.has('is-editing'), 'Saving a local draft must return to display mode');
    edit.listeners.click();
    editors[0].html = 'Forbidden goals change';
    editors[1].html = 'New achievements';
    await find(card, node => node.textContent === 'Save').listeners.click();
    assert.equal(payload.goals, 'Goals', 'Achievements-only editors preserve protected goals');
    assert.equal(payload.gossip1, 'Private gossip');
    assert.equal(payload.achievements, 'New achievements');
    assert.equal(entry.achievements, 'Sanitized achievements', 'Display uses the sanitized server response');
    assert(!card.classList.contains('is-editing'));
    context.currentUser = { role: 'participant', id: 10 };
    context.window.AETHER_EVENT_TARGET = { character: 16, event: 100, kind: 'gossip' };
    payload = null;
    context.renderDiaryEntries(container, { id: 16, type: 'extra', idUser: 10 }, entries, []);
    const extraCard = find(container, node => node.classes.has('diary-entry') && find(node, child => child.textContent === 'Older'));
    assert(extraCard.scrolled && !extraCard.classes.has('is-editing'), 'Own extra gossip opens in display mode');
    assert(find(extraCard, node => node.dataset.gossip === 'gossip1').classes.has('d-none'), 'Extra gossip must stay read-only');
    context.window.AETHER_EVENT_TARGET = { character: 16, event: 300, kind: 'gossip' };
    context.renderDiaryEntries(container, { id: 16, type: 'extra', idUser: 10 }, [], [{ id: 300, title: 'Extra event' }]);
    assert(!find(container, node => node.classes.has('diary-entry')), 'Missing extra gossip must not create an editable draft');
    assert(find(container, node => String(node.textContent || '').includes('Alleen een admin of director')).scrolled);
    assert.equal(payload, null, 'Viewing missing extra gossip must never write a diary');
    console.log('Diary card sorting, edit/cancel/save and permissions tests passed.');
    context.AetherActionButtons.group = buttons => { const group = new Element('div'); group.append(...buttons); return group; };
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../js/economyCharacter.js'), 'utf8'), context);
    context.window.AETHER_EVENT_TARGET = { character: 15, event: 300, kind: 'snapshot' };
    const economy = new Element('div');
    context.renderCharacterEconomySnapshotsSection(economy, { id: 15, canCreateEconomySnapshots: true, economySnapshots: [], economySnapshotEventOptions: [{ id: 300, title: 'Target event' }, { id: 301, title: 'Other event' }] });
    const snapshotEvent = find(economy, node => node.dataset.role === 'character-economy-snapshot-event');
    assert.equal(snapshotEvent.value, '300'); assert(snapshotEvent.focused);
    assert.equal(context.window.AETHER_EVENT_TARGET, null);
    console.log('Snapshot deep link selects the requested event and focuses the creation form.');
})().catch(error => { console.error(error); process.exitCode = 1; });
