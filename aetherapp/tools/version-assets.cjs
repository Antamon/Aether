// Run with Node before uploading changed CSS/JS and the HTML entry pages.
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

function versionAssets(root) {
    const pages = fs.readdirSync(root).filter(name => name.endsWith('.html'));
    const changes = [];
    const planned = pages.map(name => {
        const file = path.join(root, name);
        const source = fs.readFileSync(file, 'utf8');
        const result = source.replace(/\b(src|href)=(['"])(?:asset\.php\?path=)?((?:js|css)\/[A-Za-z0-9_./-]+\.(?:js|css))(?:\?v=[A-Za-z0-9._-]+)?\2/g,
            (match, attribute, quote, asset) => {
                if (asset.split('/').includes('..')) throw Error('Invalid asset path: ' + asset);
                const digest = crypto.createHash('sha256').update(fs.readFileSync(path.join(root, asset))).digest('hex').slice(0, 16);
                return `${attribute}=${quote}${asset}?v=${digest}${quote}`;
            });
        return { file, name, source, result };
    });
    // Check all dependencies before writing any page.
    for (const page of planned) {
        if (page.source !== page.result) {
            fs.writeFileSync(page.file, page.result);
            changes.push(page.name);
        }
    }
    return changes;
}

module.exports = { versionAssets };
if (require.main === module) {
    console.log(versionAssets(path.resolve(__dirname, '..')).join('\n') || 'Asset links already current.');
}
