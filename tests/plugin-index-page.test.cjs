const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'index.php'), 'utf8');
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'update-manifest.json'), 'utf8'));
const socialImage = fs.readFileSync(path.join(root, 'assets', 'images', 'plugin-social-preview.png'));

assert.match(source, /<html lang="de">/, 'The product page must declare German as its language.');
assert.match(source, /rel="canonical"/, 'A canonical URL is required.');
assert.match(source, /property="og:image"/, 'Open Graph image metadata is required.');
assert.match(source, /name="twitter:card" content="summary_large_image"/, 'Twitter card metadata is required.');
assert.match(source, /SoftwareApplication/, 'SoftwareApplication structured data is required.');
assert.match(source, /release-notes\.json/, 'The current release history must be loaded dynamically.');
assert.match(source, /Version <\?php echo feu_page_escape\(\$plugin_version\); \?>/, 'The visible version must use the detected plugin version.');
assert.doesNotMatch(source, /3\.2\.9/, 'The page must not contain an obsolete fallback version.');
assert.match(source, /Professioneller Berichtseditor/, 'The current report workflow must be described.');
assert.match(source, /Karten mit zuverlässigem Fallback/, 'The current map fallback must be described.');
assert.match(source, /Mannschaft und Funktionen/, 'The current Mannschaft terminology must be used.');
assert.match(source, /Migration und Updates/, 'Migration and updates must be described.');
assert.match(source, /prefers-reduced-motion/, 'Reduced-motion preferences must be respected.');
assert.match(source, /:focus-visible/, 'Keyboard focus must remain visible.');

const ids = [...source.matchAll(/\sid="([^"]+)"/g)].map((match) => match[1]);
assert.equal(new Set(ids).size, ids.length, 'HTML ids must be unique.');

for (const [, target] of source.matchAll(/href="#([^"]+)"/g)) {
    assert.ok(ids.includes(target), `Internal navigation target #${target} must exist.`);
}

assert.equal(socialImage.toString('ascii', 1, 4), 'PNG', 'The social preview must be a PNG file.');
assert.equal(socialImage.readUInt32BE(16), 1200, 'The social preview width must be 1200 px.');
assert.equal(socialImage.readUInt32BE(20), 630, 'The social preview height must be 630 px.');
assert.equal(manifest.version, '3.3.11', 'The page test must run against the expected current release.');

console.log('Plugin product index checks passed.');
