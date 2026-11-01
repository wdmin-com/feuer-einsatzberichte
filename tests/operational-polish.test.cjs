const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('report editor exposes saved, dirty, autosaved and error states', () => {
    const template = read('templates/admin/report-create.php');
    const script = read('assets/admin/js/report-draft-recovery.js');
    assert.match(template, /data-feu-save-indicator/);
    for (const label of ['Gespeichert', 'Nicht gespeicherte Änderungen', 'Automatisch gesichert', 'Speichern fehlgeschlagen']) {
        assert.match(template, new RegExp(label));
    }
    assert.match(script, /setSaveIndicator\('dirty'/);
    assert.match(script, /setSaveIndicator\('autosaved'/);
    assert.match(script, /setSaveIndicator\('error'/);
});

test('Mannschaft actions provide quick filters and undoable archiving', () => {
    const template = read('templates/admin/participants.php');
    assert.match(template, /data-feu-participant-filter="active"/);
    assert.match(template, /data-feu-participant-filter="archived"/);
    assert.match(template, /data-feu-participant-filter="linked"/);
    assert.match(template, /data-feu-participant-filter="without-photo"/);
    assert.match(template, /data-feu-participant-undo/);
    assert.match(template, /showArchiveToast/);
});

test('diagnostic copy is privacy-safe and reports operational health only', () => {
    const controller = read('includes/class-operations-center.php');
    const template = read('templates/admin/operations-center.php');
    assert.match(controller, /function build_diagnostic_report\(\): string/);
    assert.match(controller, /Privacy: no names, addresses, report content, URLs or image data included/);
    assert.doesNotMatch(controller, /\$log->message|\$log->details|post_content/);
    assert.match(template, /Diagnose kopieren/);
    assert.match(template, /navigator\.clipboard\.writeText/);
});
