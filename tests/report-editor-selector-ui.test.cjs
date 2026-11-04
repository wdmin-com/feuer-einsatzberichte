const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('selected Mannschaft members use one compact work row with equal controls', () => {
    const template = read('templates/admin/meta-box-teilnehmer.php');
    const styles = read('assets/admin/css/admin-style.css');
    assert.match(template, /feu-einsatz-pr-layout/);
    assert.match(template, /feu-einsatz-pr-avatar/);
    assert.match(template, /Funktion im Einsatz für %s/);
    assert.match(styles, /grid-template-columns:\s*40px minmax\(170px, 1fr\) minmax\(170px, 230px\) minmax\(150px, 1fr\) 38px/);
    assert.match(styles, /feu-einsatz-pr-function[\s\S]*?feu-einsatz-function-select,[\s\S]*?feu-einsatz-pr-delete[\s\S]*?height:\s*38px/);
    assert.match(styles, /feu-einsatz-participant-chip-card\.is-active/);
});

test('organization selection and ordering expose stable visual states', () => {
    const template = read('templates/admin/meta-box-teilnehmer.php');
    const styles = read('assets/admin/css/admin-style.css');
    assert.match(template, /feu-einsatz-organization-chip-swatch/);
    assert.match(template, /feu-einsatz-order-arrow/);
    assert.match(styles, /feu-einsatz-organization-chip\.is-active::after/);
    assert.match(styles, /feu-einsatz-move-item:disabled/);
});

test('map image action aligns its icon and keeps the label separate', () => {
    const template = read('templates/admin/meta-box-details.php');
    const styles = read('assets/admin/css/admin-style.css');
    assert.match(template, /feu-einsatz-map-regenerate-button/);
    assert.match(template, /dashicons-image-rotate/);
    assert.match(styles, /feu-einsatz-map-regenerate-button \.dashicons/);
});

test('an unverified house number can offer cached OSM alternatives safely', () => {
    const admin = read('includes/class-admin.php');
    const template = read('templates/admin/meta-box-details.php');
    const script = read('assets/admin/js/admin-script.js');
    assert.match(admin, /function request_street_house_number_suggestions\(/);
    assert.match(admin, /feu_einsatz_house_numbers_v1_/);
    assert.match(admin, /'house_numbers' => \$house_number_suggestions/);
    assert.match(template, /data-feu-house-number-suggestions/);
    assert.match(script, /renderHouseNumberSuggestions/);
    assert.match(script, /feu-einsatz-house-number-suggestion/);
});
