const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const template = fs.readFileSync(path.join(root, 'templates', 'admin', 'meta-box-details.php'), 'utf8');
const styles = fs.readFileSync(path.join(root, 'assets', 'admin', 'css', 'apple-workspace.css'), 'utf8');

assert.match(template, /feu-einsatz-form-grid--municipality-timing/, 'City, district, date and time must share one compact editor row.');
assert.match(template, /feu-einsatz-field--city/, 'The city field needs an explicit compact grid role.');
assert.match(template, /feu-einsatz-field--district/, 'The district field needs a flexible grid role.');
assert.match(template, /feu-einsatz-field--date/, 'The date field needs a fixed compact grid role.');
assert.match(template, /feu-einsatz-field--time/, 'The time field needs a fixed compact grid role.');
assert.match(template, /id="feu_einsatz_stadtteil"[\s\S]*?title="<\?php echo esc_attr\(\$stadtteil\); \?>"/, 'A clipped district must expose its complete value as a native tooltip.');
assert.match(styles, /grid-template-columns:\s*minmax\(150px, 180px\)\s+minmax\(160px, 1fr\)\s+150px\s+112px;/, 'The desktop editor row must reserve compact widths for city, date and time.');
assert.match(styles, /\.feu-einsatz-field--district input[\s\S]*?text-overflow:\s*ellipsis;/, 'Long district values must remain visually contained.');
assert.match(styles, /@media \(min-width: 601px\) and \(max-width: 920px\)[\s\S]*?feu-einsatz-form-grid--municipality-timing[\s\S]*?repeat\(2, minmax\(0, 1fr\)\)/, 'The compact row must use two columns on medium screens.');
assert.match(styles, /@media \(max-width: 600px\)[\s\S]*?feu-einsatz-form-grid--municipality-timing[\s\S]*?grid-template-columns:\s*minmax\(0, 1fr\);/, 'The compact row must stack safely on phones.');

console.log('Compact report location and timing layout checks passed.');
