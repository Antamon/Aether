const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
let visible = false;
let rendered = null;
const host = {
    replaceChildren() { rendered = null; },
    classList: { toggle(name, hidden) { visible = !hidden; } },
    append(group) { rendered = group; }
};
const context = {
    window: { addEventListener() {} },
    document: { getElementById: id => id === 'characterDeleteActions' ? host : null },
    currentUser: { id: 10, role: 'participant' },
    AetherActionButtons: {
        button: options => options,
        group: buttons => ({ buttons, classList: { add() {} } })
    }
};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../js/characterFunctions.js'), 'utf8'), context);
const person = { id: 15, idUser: 10, type: 'player' };
for (const role of ['participant', 'director', 'administrator']) {
    context.currentUser.role = role;
    for (const state of ['draft', 'inactive', 'deceased', 'active', 'pending']) {
        const expected = ['draft', 'inactive', 'deceased'].includes(state);
        context.renderCharacterDeleteActions({ ...person, state });
        assert.equal(visible, expected, `${role}/${state}: deletion visibility`);
        if (expected) assert.equal(rendered.buttons[0].label, 'Delete character');
    }
}
context.currentUser.role = 'participant';
context.renderCharacterDeleteActions({ ...person, idUser: 99, state: 'draft' });
assert.equal(visible, false, 'A participant cannot delete another participant\'s character');
context.renderCharacterDeleteActions(null);
assert.equal(visible, false, 'No delete button without a selected character');
console.log('Character deletion state and permission controls passed.');
