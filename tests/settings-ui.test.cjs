const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const projectRoot = path.resolve(__dirname, '..');
const css = fs.readFileSync(path.join(projectRoot, 'assets/admin/css/apple-admin-system.css'), 'utf8');

function declarations(selector) {
    const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const match = css.match(new RegExp(`${escaped}\\s*\\{([^}]+)\\}`, 'm'));
    assert.ok(match, `Missing CSS rule: ${selector}`);
    return match[1];
}

const navigation = declarations('body.feu-apple-settings .feu-admin-settings-nav');
assert.doesNotMatch(navigation, /border-right\s*:/, 'Settings navigation must not render a right divider.');

const surface = declarations('body.feu-apple-settings .feu-einsatz-tab-content .feu-admin-settings-surface');
assert.match(surface, /border\s*:\s*1px solid var\(--feu-ui-line\)/, 'Settings sections must have a card border.');
assert.match(surface, /background\s*:\s*var\(--feu-ui-surface\)/, 'Settings sections must have a card background.');

const categories = declarations('body.feu-apple-settings .feu-einsatz-categories-list');
assert.match(categories, /max-height\s*:\s*none/, 'The Einsatzstichworte list must show its full height.');
assert.match(categories, /overflow\s*:\s*visible/, 'The Einsatzstichworte list must not have an inner scrollbar.');

for (const partial of ['tab-funktionen.php', 'tab-kategorien.php', 'tab-organisationen.php', 'tab-shortcodes.php', 'tab-zugriff.php']) {
    const markup = fs.readFileSync(path.join(projectRoot, 'templates/admin/settings/partials', partial), 'utf8');
    assert.match(markup, /feu-admin-settings-panel--card/, `${partial} must use the settings card fallback.`);
}

console.log('Settings cards, navigation and Einsatzstichworte list styles are consistent.');
