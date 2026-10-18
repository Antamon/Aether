const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

class Element {
    constructor(tag) {
        this.tagName = tag.toUpperCase(); this.nodeType = 1;
        this.childNodes = []; this.attributes = {}; this.listeners = {}; this.classes = new Set();
        this.classList = {contains: key => this.classes.has(key), add: (...keys) => keys.forEach(key => this.classes.add(key))};
    }
    set className(value) { this.classes = new Set(value.split(/\s+/)); }
    setAttribute(key, value) { this.attributes[key] = value; }
    append(...nodes) { for (const node of nodes) this.appendChild(node); }
    appendChild(node) {
        if (node.parent) node.parent.childNodes = node.parent.childNodes.filter(child => child !== node);
        node.parent = this; this.childNodes.push(node); return node;
    }
    matches(selector) { return selector.split(',').some(part => {
        part = part.trim(); return part.startsWith('.') ? this.classes.has(part.slice(1)) : this.tagName === part.toUpperCase();
    }); }
    closest(selector) { return this.matches(selector) ? this : this.parent?.closest(selector); }
    querySelectorAll(selector) { return this.childNodes.flatMap(child => [
        ...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector)
    ]); }
    addEventListener(event, handler) { this.listeners[event] = handler; }
    close() { this.closed = true; }
}
const root = new Element('html');
const document = {nodeType: 9, documentElement: root, createElement: tag => new Element(tag), listeners: {},
    querySelectorAll: selector => root.querySelectorAll(selector),
    addEventListener(event, handler) { this.listeners[event] = handler; }};
const node = (tag, classes = '') => { const result = new Element(tag); result.className = classes; return result; };
const bootstrap = node('div', 'modal');
const content = node('div', 'modal-content');
const header = node('div', 'modal-header');
const title = node('h2', 'modal-title');
header.append(title);
const form = node('form', 'modal-body');
const save = node('button'); const originalHandler = () => 'saved'; save.addEventListener('click', originalHandler);
form.append(save); content.append(header, form); bootstrap.append(content); root.append(bootstrap);
const native = node('dialog', 'share-ui-dialog');
const nativeTitle = node('h4'); const nativeForm = node('form'); nativeForm.append(node('input'));
native.append(nativeTitle, nativeForm); root.append(native);
let observer;
vm.runInNewContext(fs.readFileSync('js/modal.js', 'utf8'), {
    document, MutationObserver: class {constructor(callback) {observer = callback;} observe() {}}
});
for (const surface of [content, native]) {
    assert(surface.classList.contains('aether-modal-surface'));
    assert.equal(surface.querySelectorAll('.aether-modal-frame').length, 8);
    const [close] = surface.querySelectorAll('.aether-modal-close');
    assert.equal(close.attributes['aria-label'], 'Sluiten');
    assert(close.querySelectorAll('.fa-xmark').length, 'The medallion uses a Font Awesome cancel icon');
}
assert.equal(content.querySelectorAll('.aether-modal-close')[0].attributes['data-bs-dismiss'], 'modal');
assert.equal(save.listeners.click, originalHandler, 'Decorating must preserve existing save handlers');
assert.equal(native.querySelectorAll('.modal-header')[0].childNodes[0], nativeTitle);
assert.equal(nativeForm.parent, native.querySelectorAll('.modal-body')[0], 'Native form nodes are moved, not recreated');
assert.equal(native.attributes['aria-labelledby'], nativeTitle.id);
native.querySelectorAll('.aether-modal-close')[0].listeners.click();
assert(native.closed, 'The native medallion closes its own dialog');
observer([{addedNodes: [bootstrap, native]}]);
document.listeners['show.bs.modal']({target: bootstrap});
assert.equal(content.querySelectorAll('.aether-modal-frame').length, 8, 'Repeated shows must not duplicate frames');
const dynamic = node('div', 'modal'); const dynamicContent = node('div', 'modal-content');
dynamic.append(dynamicContent); root.append(dynamic);
document.listeners['show.bs.modal']({target: dynamic});
assert(dynamicContent.classList.contains('aether-modal-surface'), 'Dynamic Bootstrap modals are decorated before showing');
const dynamicNative = node('dialog'); root.append(dynamicNative);
observer([{addedNodes: [dynamicNative]}]);
assert(dynamicNative.classList.contains('aether-modal-surface'), 'Dynamic native modals are decorated on insertion');
const css = fs.readFileSync('css/modal.css', 'utf8');
for (const [, asset] of css.matchAll(/url\('\.\.\/([^']+)'\)/g)) assert(fs.existsSync(asset), `Missing modal asset: ${asset}`);
for (const page of ['index.html','companies.html','admin.html','eventParticipation.html','leaderPersonnel.html','static.html']) {
    const html = fs.readFileSync(page, 'utf8');
    assert(html.includes('css/modal.css') && html.includes('js/modal.js'), `${page} must load the common modal theme`);
}
console.log('Modal frames, dynamic dialogs, close controls, retained form handlers and asset coverage passed.');
