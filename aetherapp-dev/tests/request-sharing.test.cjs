const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const os = require('node:os');
const { versionAssets } = require('../tools/version-assets.cjs');

function setup() {
    let now = 0;
    let handler = async endpoint => endpoint === 'checkLogin.php'
        ? { status: 'ok', user: { id: 7, firstName: 'Test', lastName: 'User', displayName: 'Test User', role: 'director' }, csrfToken: 'test-csrf' }
        : [{ id: 1, name: 'Original' }];
    const calls = [];
    const listeners = {};
    const context = {
        console, Map, Promise, Date: { now: () => now },
        document: { getElementById: () => null }, location: { href: '' },
        addEventListener: (name, callback) => { listeners[name] = callback; },
        fetch: async (endpoint, options) => {
            calls.push({ endpoint, options });
            const data = await handler(endpoint, options);
            return { ok: true, json: async () => data };
        }
    };
    context.window = context;
    vm.createContext(context);
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../js/mainFunctions.js'), 'utf8'), context);
    return { context, calls, listeners, advance: ms => now += ms, handle: fn => { handler = fn; } };
}

(async () => {
    const app = setup();
    const api = app.context.apiFetchJson;
    const ties = 'api/characters/getCharacterTies.php';
    const options = { method: 'POST', body: { idCharacter: 15 } };

    const users = await Promise.all([app.context.aetherGetCurrentUser(), app.context.aetherGetCurrentUser(), app.listeners.load()]);
    assert.equal(app.calls.length, 1, 'Navbar and page must share the login request');
    assert.equal(users[0].id, 7);
    assert.equal(app.context.AETHER_CSRF_TOKEN, 'test-csrf');

    const results = await Promise.all([api(ties, options), api(ties, options)]);
    assert.equal(app.calls.length, 2, 'Simultaneous relation requests must share one fetch');
    results[0][0].name = 'Changed locally';
    assert.equal(results[1][0].name, 'Original');
    assert.equal((await api(ties, options))[0].name, 'Original');
    await api(ties, { method: 'POST', body: { idCharacter: 16 } });
    assert.equal(app.calls.length, 3, 'Different characters must not share data');
    app.advance(60001);
    await api(ties, options);
    assert.equal(app.calls.length, 4, 'Expired relation data must reload');

    await api('api/users/getUserList.php');
    await api('api/users/getUserList.php');
    assert.equal(app.calls.length, 5);
    await api('api/characters/saveCharacterTie.php', { method: 'POST', body: { idCharacter: 15 } });
    assert.equal(app.calls.at(-1).options.headers['X-CSRF-Token'], 'test-csrf');
    await api(ties, options);
    await api('api/users/getUserList.php');
    assert.equal(app.calls.length, 8, 'Writes must invalidate shared data');
    app.advance(300001);
    await api('api/users/getUserList.php');
    assert.equal(app.calls.length, 9);
    await api('api/shares/getCharacterOffers.php');
    await api('api/shares/getCharacterOffers.php');
    assert.equal(app.calls.length, 11, 'Other API data must remain uncached');

    const failed = setup();
    failed.handle(async () => { throw Error('Network failure'); });
    await assert.rejects(failed.context.aetherGetCurrentUser(), /Network failure/);
    failed.handle(async () => ({ status: 'redirect' }));
    await assert.rejects(failed.context.aetherGetCurrentUser(), error => error.status === 401);
    failed.handle(async () => ({ status: 'ok', user: { id: 8 }, csrfToken: 'retry-token' }));
    assert.equal((await failed.context.aetherGetCurrentUser()).id, 8, 'Failed authentication can retry');
    failed.handle(async () => { throw Error('Fetch failure'); });
    await assert.rejects(failed.context.apiFetchJson(ties, options), /Fetch failure/);
    failed.handle(async () => []);
    assert.equal((await failed.context.apiFetchJson(ties, options)).length, 0, 'Failed reads can retry');

    const race = setup();
    let release;
    race.handle(endpoint => endpoint === ties ? new Promise(resolve => { release = resolve; }) : Promise.resolve({ ok: true }));
    const pending = race.context.apiFetchJson(ties, options);
    await race.context.apiFetchJson('api/characters/updateCharacter.php', { method: 'POST', body: { id: 15 } });
    release([{ id: 1 }]);
    await pending;
    race.handle(async () => [{ id: 2 }]);
    assert.equal((await race.context.apiFetchJson(ties, options))[0].id, 2, 'A stale in-flight read cannot restore invalidated data');

    const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'aether-assets-'));
    try {
        fs.mkdirSync(path.join(fixture, 'js'));
        fs.writeFileSync(path.join(fixture, 'js/app.js'), 'original');
        fs.writeFileSync(path.join(fixture, 'index.html'), '<script src="asset.php?path=js/app.js"></script><script src="https://cdn.example/app.js"></script>');
        assert.deepEqual(versionAssets(fixture), ['index.html']);
        const first = fs.readFileSync(path.join(fixture, 'index.html'), 'utf8');
        assert.match(first, /src="js\/app.js\?v=[a-f0-9]{16}"/);
        assert.match(first, /https:\/\/cdn.example\/app.js/);
        assert.deepEqual(versionAssets(fixture), []);
        fs.writeFileSync(path.join(fixture, 'js/app.js'), 'updated');
        versionAssets(fixture);
        assert.notEqual(fs.readFileSync(path.join(fixture, 'index.html'), 'utf8'), first);
        fs.writeFileSync(path.join(fixture, 'index.html'), '<script src="asset.php?path=js/missing.js"></script>');
        const before = fs.readFileSync(path.join(fixture, 'index.html'), 'utf8');
        assert.throws(() => versionAssets(fixture), /ENOENT/);
        assert.equal(fs.readFileSync(path.join(fixture, 'index.html'), 'utf8'), before);
    } finally {
        for (const file of ['index.html', 'js/app.js']) fs.unlinkSync(path.join(fixture, file));
        fs.rmdirSync(path.join(fixture, 'js'));
        fs.rmdirSync(fixture);
    }

    const root = path.resolve(__dirname, '..');
    for (const name of ['mainFunctions', 'apiCharacter', 'companyFunctions', 'eventFunctions', 'adminFunctions', 'aetherSidebars', 'characterFunctions', 'createCharacter']) {
        new vm.Script(fs.readFileSync(path.join(root, 'js', name + '.js'), 'utf8'), { filename: name + '.js' });
    }
    new vm.Script(fs.readFileSync(path.join(root, 'js/actionButtons.js'), 'utf8'), { filename: 'actionButtons.js' });
    const backgroundSource = fs.readFileSync(path.join(root, 'js/backgroundCharacter.js'), 'utf8');
    assert.match(backgroundSource, /fa-pen-nib/);
    assert.match(backgroundSource, /fa-plus/);
    assert.match(backgroundSource, /actionGroup\.replaceChildren\(saveBtn, cancelBtn\)/);
    assert.doesNotMatch(backgroundSource, /actionsRow/);
    const stylesheet = fs.readFileSync(path.join(root, 'css/style.css'), 'utf8');
    assert.match(stylesheet, /\.action-button\s*\{/);
    assert.match(stylesheet, /\.company-type-info-icon\s*\{/);
    const companyPage = fs.readFileSync(path.join(root, 'companies.html'), 'utf8');
    assert.match(companyPage, /id="companyTypeInfoButton"/);
    assert.doesNotMatch(companyPage, /companyTypeInfoModal"[^>]*><i/);
    for (const name of fs.readdirSync(root).filter(name => name.endsWith('.html'))) {
        const html = fs.readFileSync(path.join(root, name), 'utf8');
        assert.ok(!html.includes('asset.php?path='), name + ' still redirects assets');
        for (const match of html.matchAll(/(?:src|href)="((?:js|css)\/[^"?]+)\?v=([a-f0-9]{16})"/g)) {
            const hash = require('node:crypto').createHash('sha256').update(fs.readFileSync(path.join(root, match[1]))).digest('hex').slice(0, 16);
            assert.equal(match[2], hash, name + ': outdated ' + match[1]);
        }
    }
    console.log('Request sharing, authentication, invalidation and asset versioning tests passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
