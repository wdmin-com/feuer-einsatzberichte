const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const template = fs.readFileSync(path.join(root, 'templates', 'admin', 'participants.php'), 'utf8');
const styles = fs.readFileSync(path.join(root, 'assets', 'admin', 'css', 'admin-modern.css'), 'utf8');

assert.match(template, /feu-einsatz-participant-table--compact/, 'The Mannschaft roster must use the compact table layout.');
assert.match(template, /feu-einsatz-participant-identity/, 'Photo, name and state must form one identity cell.');
assert.match(template, /feu-einsatz-participant-rank-column/, 'The roster must expose the synchronized rank in a dedicated column.');
assert.match(template, /feu-einsatz-participant-position-column/, 'The roster must expose the synchronized job position in a dedicated column.');
assert.match(template, /\$person->rank_title/, 'The rank column must use the synchronized Feuer-Mannschaft rank field.');
assert.match(template, /\$person->job_title/, 'The position column must use the synchronized Feuer-Mannschaft job field.');
assert.match(template, /\[48, 48\]/, 'Roster thumbnails must request a compact image size.');
assert.match(template, /feu-einsatz-participant-action-menu/, 'Secondary row actions must be grouped in an overflow menu.');
assert.match(template, /aria-label="<\?php echo esc_attr__\('Weitere Aktionen'/, 'The overflow trigger needs an accessible label.');
assert.match(template, /participantActionMenus/, 'Only one row action menu should remain open.');
assert.match(styles, /width:\s*44px;\s*\n\s*height:\s*44px;/, 'Desktop avatars must render at 44 by 44 pixels.');
assert.match(styles, /height:\s*66px;/, 'Desktop rows must stay compact.');
assert.match(styles, /\.feu-einsatz-participant-detail-value/, 'Rank and position values must use a compact visual style.');
assert.match(styles, /min-height:\s*44px;/, 'Mobile controls must retain a 44 pixel touch target.');
assert.match(styles, /\.feu-einsatz-participant-action-popover/, 'The secondary action popover must be styled.');

console.log('Compact Mannschaft administration layout checks passed.');
