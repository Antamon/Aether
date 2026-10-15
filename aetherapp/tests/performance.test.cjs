const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../js/performance.js'), 'utf8');

function create(search, stored = null, storageThrows = false) {
    let time = 0;
    let editing = true;
    let saveHandler;
    const storage = new Map(stored ? [['aether:performance', stored]] : []);
    const originalApi = async (endpoint) => {
        if (endpoint.includes('failed')) throw Object.assign(new Error('PRIVATE_RESPONSE'), { status: 422 });
        return { name: 'PRIVATE_PERSON', token: 'PRIVATE_TOKEN' };
    };
    const context = {
        URL, URLSearchParams, Promise, Blob, console: { info() {}, table() {} },
        location: { search, href: 'https://example.test/aether/index.html' + search, origin: 'https://example.test' },
        document: { readyState: 'complete', hidden: false },
        sessionStorage: {
            getItem(key) { if (storageThrows) throw Error(); return storage.get(key); },
            setItem(key, value) { if (storageThrows) throw Error(); storage.set(key, value); },
            removeItem(key) { if (storageThrows) throw Error(); storage.delete(key); }
        },
        performance: {
            now: () => time += 10,
            getEntriesByType(type) {
                return type === 'resource' ? [{ name: 'https://example.test/aether/api/shares/getCharacterOffers.php?idCharacter=PRIVATE_ID&token=PRIVATE_QUERY', initiatorType: 'fetch', startTime: 1, duration: 20, responseStart: 11, transferSize: 12, decodedBodySize: 20, redirectEnd: 0, redirectStart: 0 }] : [];
            }
        },
        requestAnimationFrame(callback) { callback(); },
        setTimeout(callback) { callback(); },
        apiFetchJson: originalApi,
        getCharacter: async () => 42,
        aetherDataCard: {
            controls(header, options) { saveHandler = options.onSave; return 'controls'; },
            isEditing() { return editing; }
        }
    };
    context.window = context;
    vm.createContext(context);
    vm.runInContext(source, context);
    return { context, originalApi, storage, save: (...args) => saveHandler(...args), setEditing(value) { editing = value; } };
}

(async () => {
    const disabled = create('');
    assert.equal(disabled.context.apiFetchJson, disabled.originalApi);
    assert.equal(disabled.context.AetherPerformance, undefined);
    const reset = create('?performance=0', '1');
    assert.equal(reset.context.AetherPerformance, undefined);
    assert.equal(reset.storage.size, 0);
    const enabled = create('?character=PRIVATE_CHARACTER&performance=1');
    const { context } = enabled;
    const response = await context.apiFetchJson('api/shares/getCharacterOffers.php?idCharacter=PRIVATE_ID', { body: { private: 'PRIVATE_BODY' } });
    assert.equal(response.name, 'PRIVATE_PERSON');
    assert.equal(await context.getCharacter(12), 42);
    await assert.rejects(context.apiFetchJson('api/failed.php'), { status: 422 });
    assert.equal(context.aetherDataCard.controls(null, { key: 'personnel:PRIVATE_CARD', onSave: async () => { enabled.setEditing(false); return 'saved'; } }), 'controls');
    assert.equal(await enabled.save(), 'saved');
    let result = context.AetherPerformance.snapshot();
    assert.equal(result.operations.find((row) => row.name === 'card.save').status, 'completed');
    assert.equal(result.operations.find((row) => row.name === 'api/failed.php').status, 'http-422');
    assert.equal(result.resources[0].name, 'api/shares/getCharacterOffers.php');
    assert.ok(!JSON.stringify(result).includes('PRIVATE_'), 'Report must exclude identifiers, payloads, responses and errors');
    enabled.setEditing(true);
    context.aetherDataCard.controls(null, { key: 'other', onSave: async () => false });
    assert.equal(await enabled.save(), false);
    assert.equal(context.AetherPerformance.snapshot().operations.at(-1).status, 'not-saved');
    context.AetherPerformance.stop();
    const count = context.AetherPerformance.snapshot().operations.length;
    await context.getCharacter(1);
    assert.equal(context.AetherPerformance.snapshot().operations.length, count);
    assert.ok(create('', '1').context.AetherPerformance, 'Opt-in follows navigation within the tab');
    assert.ok(create('?performance=1', null, true).context.AetherPerformance, 'URL opt-in survives unavailable storage');
    console.log('Performance diagnostics: behavior, opt-in, failures, card saves, stop and privacy passed.');
})().catch((error) => { console.error(error); process.exitCode = 1; });
