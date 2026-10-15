const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

let detailRequests = [];
let enabled = null;
let populated = undefined;
const context = {
    window: { addEventListener() {} }, console,
    apiFetchJson: async () => [{ id: 1 }, { id: 2 }],
    document: { getElementById: () => null }
};
vm.createContext(context);
vm.runInContext(fs.readFileSync('js/companyFunctions.js', 'utf8'), context);
context.renderCompanyList = () => {};
context.setCompanyFormState = value => { enabled = value; };
context.populateCompanyForm = value => { populated = value; };
context.showCompanyFeedback = () => {};
context.hideCompanyFeedback = () => {};
context.loadCompany = async id => { detailRequests.push(id); };

(async () => {
    await context.loadCompanyList();
    assert.deepEqual(detailRequests, [], 'Opening the list must not request company details');
    assert.equal(enabled, false);
    assert.equal(populated, null);

    await context.loadCompanyList(2);
    assert.deepEqual(detailRequests, [2], 'An explicit company selection must load that company');

    detailRequests = [];
    context.apiFetchJson = async () => [];
    await context.loadCompanyList();
    assert.deepEqual(detailRequests, [], 'An empty list must not request details');
    assert.equal(enabled, false);
    console.log('Company selection checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
