const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const template = fs.readFileSync(path.join(__dirname, '../templates/admin/statistics.php'), 'utf8');
const styles = fs.readFileSync(path.join(__dirname, '../assets/admin/css/admin-style.css'), 'utf8');

assert.equal(
    (template.match(/data-feu-presentation-kind="participants"/g) || []).length,
    1,
    'Only the Mannschaft presentation slide must receive the participants layout scope.'
);
assert.match(template, /feu-einsatz-presentation-podium-head/);
assert.match(template, /feu-einsatz-presentation-participant-list-head/);
assert.match(template, /feu-einsatz-presentation-participant-rank/);
assert.match(template, /%1\$d \/ %2\$d Einsätze/);
assert.doesNotMatch(
    template,
    /participant->gesamt_einsaetze\); ?> <\?php esc_html_e\('Einsätze'[^\n]+<\/em>/,
    'The podium must not repeat the deployment count above the participation metric.'
);

assert.match(styles, /\.feu-einsatz-presentation-participant-list\s*\{[^}]*grid-template-columns:\s*repeat\(4,/s);
assert.match(styles, /\.feu-einsatz-presentation-podium p\s*\{[^}]*min-height:\s*142px/s);
assert.match(styles, /\.feu-einsatz-presentation-participant-list-block\s*\{[^}]*border-top:/s);
assert.match(styles, /@media \(max-width:\s*600px\)[\s\S]*?\.feu-einsatz-presentation-podium\s*\{[^}]*grid-template-columns:\s*1fr/s);

console.log('Mannschaft presentation uses compact podium and spaced responsive ranking cards.');
