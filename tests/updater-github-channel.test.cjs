const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const updater = fs.readFileSync(path.join(__dirname, '../includes/class-updater.php'), 'utf8');
const builder = fs.readFileSync(path.join(__dirname, '../tools/build-release.ps1'), 'utf8');

assert.match(updater, /DEFAULT_RELEASE_BASE_URL\s*=\s*'https:\/\/github\.com\/wdmin-com\/feuer-einsatzberichte\/releases\/'/);
assert.match(updater, /LEGACY_DEFAULT_MANIFEST_URL\s*=\s*'https:\/\/wdmin\.com\/plugins\/feuer-einsatzberichte\/release\/latest\/update-manifest\.json'/);
assert.match(updater, /'github\.com',/);
assert.match(updater, /releases\/download\/\[\^\/\]\+\/\[\^\/\]\+\\\.zip/);
assert.match(builder, /github\.com\/wdmin-com\/feuer-einsatzberichte\/releases\/download\/v\$version/);
assert.doesNotMatch(builder, /baseDownloadUrl\s*=\s*'https:\/\/wdmin\.com\/plugins\/feuer-einsatzberichte\/release'/);

console.log('Updater uses versioned GitHub release assets and migrates the retired wdmin.com channel.');
