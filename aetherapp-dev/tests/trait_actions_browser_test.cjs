const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.join(__dirname, '..');
const listeners = {};
const requests = [];
const identity = { value: '' };
let editingCard = null;
let nameInput = null;
const container = {
    innerHTML: '',
    querySelectorAll: () => [],
    querySelector: () => null
};
const context = {
    console,
    currentCharacter: { id: 15, class: 'upper class' },
    currentUser: { role: 'administrator' },
    document: {
        addEventListener: (name, handler) => { listeners[name] = handler; },
        getElementById: id => id === 'idCharacter' ? identity : id === 'characterForm' ? container
            : id === 'characterDetailsCard' ? editingCard : id === 'firstName' ? nameInput : null,
        querySelector: () => null,
        createElement: () => ({ className: '', textContent: '', append: () => {} })
    },
    setupCharacterPortraitControls: () => {},
    getCharacter: async () => {},
    alert: message => { throw new Error(message); },
    apiFetchJson: async (endpoint, options) => { requests.push({ endpoint, ...options.body }); return {}; }
};
context.window = context;
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(root, 'js/traitsCharacter.js'), 'utf8'), context);
vm.runInContext(fs.readFileSync(path.join(root, 'js/formCharacter.js'), 'utf8'), context);
context.renderCharacterDetailsCard(context.currentCharacter, false, false);
assert.equal(identity.value, 15, 'Rendering must set the existing page character ID');
assert.doesNotMatch(container.innerHTML, /id="idCharacter"/, 'The card must not create a second character ID');

context.setupTraitListeners();

(async () => {
    for (const [action, expected] of [['trait-rank-up', 'rank_up'], ['trait-rank-down', 'rank_down'], ['trait-remove', 'remove']]) {
        const row = { dataset: { idTrait: '59' } };
        const button = { disabled: false, classList: { contains: () => false }, dataset: { action }, closest: () => row };
        await listeners.click({ target: { closest: () => button } });
        assert.equal(requests.at(-1).action, expected);
        assert.equal(requests.at(-1).idCharacter, 15);
        assert.equal(requests.at(-1).idTrait, 59);
    }
    assert.equal(requests.length, 3, 'All rank and delete clicks must reach the update endpoint');
    assert.equal(context.usesTraitActionButtons({ sheetZone: 'left' }), true, 'Upper-class title and nobility controls use action groups');
    assert.equal(context.usesTraitActionButtons({ sheetZone: 'main' }), true, 'Main status properties also use action groups');

    let reopened = false;
    context.currentCharacter.firstName = 'Original';
    nameInput = { id: 'firstName', value: 'Unsaved name' };
    editingCard = { classList: { contains: () => true }, querySelectorAll: () => [nameInput] };
    context.getCharacter = async () => {
        nameInput = { id: 'firstName', value: 'Original' };
        editingCard = { aetherSetEditing: value => { reopened = value; }, contains: input => input === nameInput };
    };
    await context.submitTraitAction({ action: 'rank_up', idCharacter: 15, idTrait: 59 });
    assert.equal(reopened, true, 'Immediate trait updates must reopen the details editor');
    assert.equal(nameInput.value, 'Unsaved name', 'Immediate trait updates must preserve unsaved identity fields');

    reopened = false;
    editingCard = { classList: { contains: () => false } };
    await context.submitTraitAction({ action: 'remove', idCharacter: 15, idTrait: 59 });
    assert.equal(reopened, false, 'Trait changes from display mode must remain in display mode');
    const capital = {id: 105, name: 'Aandelen', traitFlags: {share_capital: true}};
    const oldShares = {id: 9, isCompanyShare: true};
    const shareGroup = [{linkedTraits: [capital, oldShares], options: [capital, oldShares]}];
    context.currentCharacter.state = 'draft';
    assert.equal(context.withoutLegacyShareTraits(shareGroup)[0].options.length, 1, 'Draft keeps the new capital choice while hiding old percentage traits');
    context.currentCharacter.state = 'active';
    const activeShares = context.withoutLegacyShareTraits(shareGroup)[0];
    assert.equal(activeShares.options.length, 0, 'Active characters cannot buy another capital level');
    assert.equal(activeShares.linkedTraits.length, 1, 'Computed Aandelen remains visible after Draft');
    console.log('Character identity and trait rank/delete action tests passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
