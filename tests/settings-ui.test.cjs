const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const projectRoot = path.resolve(__dirname, '..');
const css = fs.readFileSync(path.join(projectRoot, 'assets/admin/css/apple-admin-system.css'), 'utf8');
const workspaceCss = fs.readFileSync(path.join(projectRoot, 'assets/admin/css/apple-workspace.css'), 'utf8');
const functionsMarkup = fs.readFileSync(path.join(projectRoot, 'templates/admin/settings/partials/tab-funktionen.php'), 'utf8');

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

assert.match(functionsMarkup, /feu-einsatz-functions-editor/, 'Functions must use one grouped editor instead of nested cards.');
assert.match(functionsMarkup, /feu-einsatz-functions-list-head/, 'The flat function editor needs a clear list header.');
assert.match(functionsMarkup, /aria-label="<\?php echo esc_attr\(sprintf\(__\('Funktion „%s“ entfernen'/, 'Remove controls need function-specific accessible names.');
assert.match(workspaceCss, /\.feu-einsatz-functions-editor[\s\S]*?overflow:\s*hidden;[\s\S]*?border:\s*1px solid/, 'Functions must share a single bounded surface.');
assert.match(workspaceCss, /\.feu-einsatz-function-item[\s\S]*?border:\s*0;[\s\S]*?border-bottom:/, 'Individual functions must render as flat divided rows.');
assert.match(workspaceCss, /\.feu-einsatz-function-actions \.feu-einsatz-remove-function[\s\S]*?width:\s*40px;/, 'The repeated remove action must remain compact and icon-only.');

console.log('Settings cards, navigation and Einsatzstichworte list styles are consistent.');
