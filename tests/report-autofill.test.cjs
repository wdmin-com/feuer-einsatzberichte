const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function element(initial = '') {
    return {
        value: initial,
        listeners: {},
        attributes: {},
        addEventListener(name, callback) { this.listeners[name] = callback; },
        getAttribute(name) { return this.attributes[name] || ''; },
        fire(name) { if (this.listeners[name]) this.listeners[name](); },
    };
}

const fields = {
    feu_einsatz_new_report_title: element(),
    feu_einsatz_new_report_content: element(),
    feu_einsatz_strasse: element(),
    feu_einsatz_stadt: element('Hamburg'),
    feu_einsatz_stadtteil: element('Lurup'),
    feu_einsatz_datum: element(),
};
const parent = element();
parent.checked = true;
parent.attributes = { 'data-feu-category-name': 'Einsätze', 'data-feu-category-depth': '0' };
const leaf = element();
leaf.checked = false;
leaf.attributes = {
    'data-feu-category-name': 'FEUMANV',
    'data-feu-category-description': 'Feuer mit einem Massenanfall von Verletzten (Großschadenlage) (ab fünf Verletzten)',
    'data-feu-category-depth': '1',
};
const titleReset = element();
const contentReset = element();
const form = {
    querySelector(selector) {
        return selector === '[data-feu-autofill-title-reset]' ? titleReset
            : selector === '[data-feu-autofill-content-reset]' ? contentReset : null;
    },
    querySelectorAll(selector) {
        const categories = [parent, leaf];
        return selector.endsWith(':checked') ? categories.filter((item) => item.checked) : categories;
    },
};
const context = {
    document: {
        readyState: 'complete',
        querySelector(selector) { return selector === '.feu-einsatz-report-create-form' ? form : null; },
        getElementById(id) { return fields[id] || null; },
    },
    window: {},
};
const source = fs.readFileSync('assets/admin/js/report-autofill.js', 'utf8');
vm.runInNewContext(source, context);

leaf.checked = true;
leaf.fire('change');
fields.feu_einsatz_strasse.value = 'Stückweg 50';
fields.feu_einsatz_strasse.fire('input');
fields.feu_einsatz_datum.value = '12.06.2026';
fields.feu_einsatz_datum.fire('input');
assert.equal(fields.feu_einsatz_new_report_title.value, 'FEUMANV - Stückweg');
assert.equal(
    fields.feu_einsatz_new_report_content.value,
    'FEUMANV - Feuer mit einem Massenanfall von Verletzten (Großschadenlage) (ab fünf Verletzten) auf der Stückweg in Hamburg Lurup am 12.06.2026.'
);

fields.feu_einsatz_new_report_title.value = 'Manueller Titel';
fields.feu_einsatz_new_report_title.fire('input');
fields.feu_einsatz_new_report_content.value = 'Manueller Bericht';
fields.feu_einsatz_new_report_content.fire('input');
fields.feu_einsatz_strasse.value = 'Elbchaussee 22';
fields.feu_einsatz_strasse.fire('input');
assert.equal(fields.feu_einsatz_new_report_title.value, 'Manueller Titel');
assert.equal(fields.feu_einsatz_new_report_content.value, 'Manueller Bericht');

titleReset.fire('click');
contentReset.fire('click');
assert.equal(fields.feu_einsatz_new_report_title.value, 'FEUMANV - Elbchaussee');
assert.match(fields.feu_einsatz_new_report_content.value, /auf der Elbchaussee in Hamburg Lurup/);
console.log('Report autofill preserves manual changes and regenerates the selected category text.');
