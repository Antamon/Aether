const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Node {
    constructor() {
        this.children = []; this.attributes = {}; this.listeners = {}; this.dataset = {};
        this.style = {setProperty() {}};
        this.classList = {values: new Set(), add: (...names) => names.forEach(n => this.classList.values.add(n)),
            toggle: (name, on) => on ? this.classList.values.add(name) : this.classList.values.delete(name),
            contains: name => this.classList.values.has(name)};
    }
    append(...nodes) { this.children.push(...nodes); }
    prepend(node) { this.children.unshift(node); }
    setAttribute(key, value) { this.attributes[key] = value; }
    addEventListener(key, fn) { this.listeners[key] = fn; }
    cloneNode() { return new Node(); }
    getBoundingClientRect() { return {bottom: 100}; }
    contains() { return false; }
}
async function check(role, companies) {
    const body = new Node(); body.dataset = {};
    const navbar = new Node();
    const all = () => {
        const visit = node => [node, ...node.children.flatMap(visit)];
        return visit(body);
    };
    const requests = [];
    const context = {
        document: {body, createElement: () => new Node(), querySelector: selector => selector === '.aether-top-nav' ? navbar : null,
            getElementById: id => all().find(node => node.id === id), addEventListener() {}},
        window: {location: {search: '?sidebar=admin'}, addEventListener() {}},
        URLSearchParams, console,
        aetherGetCurrentUser: async () => ({role}),
        userHasPrivilegedRole: user => ['administrator', 'director'].includes(user.role),
        apiFetchJson: async url => { requests.push(url); return {companies}; }
    };
    vm.runInNewContext(fs.readFileSync('js/aetherSidebars.js', 'utf8'), context);
    const tab = key => all().find(node => node.id === `${key}SidebarTabToggle`);
    assert(tab('admin').classList.contains('d-none'), 'Admin tab stays hidden during authentication');
    await new Promise(resolve => setImmediate(resolve));
    const privileged = ['administrator', 'director'].includes(role);
    assert.equal(tab('admin').classList.contains('d-none'), !privileged);
    assert.equal(tab('companies').classList.contains('d-none'), !privileged && !companies.length);
    assert.equal(requests.includes('api/companies/getMyCompanies.php'), !privileged);
}
(async () => {
    for (const role of ['participant', 'director', 'administrator']) {
        await check(role, []); await check(role, [{id: 13, companyName: 'Linked company'}]);
    }
    console.log('Sidebar role, linked-company visibility and hidden-until-authorized checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
