const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
    constructor(tag) {
        this.tag = tag; this.children = []; this.dataset = {}; this.listeners = {}; this.classes = new Set();
        this.style = { setProperty() {} }; this.value = '';
        this.classList = { add: (...names) => names.forEach(name => this.classes.add(name)),
            remove: name => this.classes.delete(name), contains: name => this.classes.has(name),
            toggle: (name, force = !this.classes.has(name)) => force ? this.classes.add(name) : this.classes.delete(name) };
    }
    set className(value) { this.classes = new Set(value.split(/\s+/)); }
    append(...children) { this.children.push(...children); }
    prepend(...children) { this.children.unshift(...children); }
    replaceChildren(...children) { this.children = children; }
    setAttribute() {}
    addEventListener(name, handler) { this.listeners[name] = handler; }
    cloneNode() { return new Element(this.tag); }
    contains(node) { return this === node || this.children.some(child => child.contains(node)); }
    getBoundingClientRect() { return { bottom: 100, left: 0, width: 1000 }; }
    querySelector(selector) { return find(this, node => selector.startsWith('.') && node.classes.has(selector.slice(1))); }
}
const find = (node, predicate) => predicate(node) ? node : node.children.map(child => find(child, predicate)).find(Boolean);
const settle = () => new Promise(resolve => setImmediate(resolve));

async function checkSidebar(role, page = 'characters') {
    const body = new Element('body'); body.dataset.sidebarPage = page;
    const characterParticipantHost = new Element('div'); characterParticipantHost.id = 'listParticipant';
    const characterParticipantSelect = new Element('select'); characterParticipantSelect.id = 'listParticipant-input';
    characterParticipantHost.append(characterParticipantSelect); body.append(characterParticipantHost);
    const navbar = new Element('nav');
    const calls = [];
    const context = { console, URLSearchParams, window: { location: { search: '', href: '' }, addEventListener() {} },
        document: { body, createElement: tag => new Element(tag), querySelector: () => navbar,
            getElementById: id => find(body, node => node.id === id), addEventListener() {} },
        aetherGetCurrentUser: async () => ({ role }), userHasPrivilegedRole: user => ['director', 'administrator'].includes(user.role),
        apiFetchJson: async url => { calls.push(url); return url.includes('getUserList')
            ? [{ id: 20, displayName: 'Zoe' }, { id: 10, displayName: 'Ada' }]
            : [{ id: 7, title: 'Event', imageUrl: 'img/events/7.png' }]; }
    };
    vm.createContext(context); vm.runInContext(fs.readFileSync('js/aetherSidebars.js', 'utf8'), context);
    const tab = context.document.getElementById('eventsSidebarTabToggle').children[0];
    tab.listeners.click(); await settle();
    const wrapper = context.document.getElementById('participantFilterWrapper');
    const select = context.document.getElementById('eventParticipantSelect');
    assert.deepEqual(characterParticipantHost.children, [characterParticipantSelect], 'Event filter must preserve the character participant control');
    if (role === 'director' || role === 'administrator') {
        assert(!wrapper.classList.contains('d-none'), 'Participant dropdown must be visible away from the event page');
        assert(wrapper.contains(select), 'Event participant dropdown must be rendered inside its sidebar filter');
        assert.deepEqual(select.children.map(option => option.textContent), ['Kies een deelnemer', 'Ada', 'Zoe']);
        select.value = '20'; select.listeners.change();
        assert.equal(context.window.location.href, 'eventParticipation.html?participant=20');
    } else {
        assert(wrapper.classList.contains('d-none')); assert.equal(select, undefined);
        assert(!calls.some(url => url.includes('getUserList')), 'Participants must not request the privileged user list');
    }
    const list = context.document.getElementById('eventSidebarList');
    assert.equal(list.children.length, 1);
    list.children[0].listeners.click();
    assert.equal(context.window.location.href, 'eventParticipation.html?event=7');
}

async function checkStartup(search, role, expectedDetails, expectedParticipation) {
    const visits = []; const lists = []; const filters = [];
    const context = { console, URLSearchParams,
        window: { location: { search }, addEventListener() {}, setupEventParticipantFilter: async (onChoose, id) => filters.push(id) },
        aetherGetCurrentUser: async () => ({ role }), eventDetailsState: { request: 0 },
        setupEventDetailUI() {}, refreshEventSidebar: async () => {},
        showEventPageView: detail => visits.push(detail), openEventDetails: async id => visits.push(id) };
    vm.createContext(context); vm.runInContext(fs.readFileSync('js/eventFunctions.js', 'utf8'), context);
    context.generateEventList = async id => lists.push(id);
    await context.initEventPage();
    assert.deepEqual(visits, expectedDetails); assert.deepEqual(lists, expectedParticipation);
    assert.deepEqual(filters, role === 'director' ? [expectedParticipation[0] || 0] : []);
}

(async () => {
    for (const page of ['characters', 'companies', 'admin', 'static']) {
        for (const role of ['director', 'administrator', 'participant']) await checkSidebar(role, page);
    }
    await checkStartup('?event=7', 'director', [true, 7], []);
    await checkStartup('?event=7', 'participant', [true, 7], []);
    await checkStartup('?participant=20', 'director', [false], [20]);
    await checkStartup('?participant=20', 'participant', [false], [0]);
    await checkStartup('', 'director', [false], [0]);
    const html = fs.readFileSync('eventParticipation.html', 'utf8');
    assert(html.includes('id="eventParticipantFilterChoices"'), 'Event page must use the dedicated participant filter host');
    assert(html.includes('class="aether-panel d-none" id="eventParticipationView"'), 'Participation view must start hidden before initialization');
    console.log('Shared event sidebar and direct event/participant navigation tests passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
