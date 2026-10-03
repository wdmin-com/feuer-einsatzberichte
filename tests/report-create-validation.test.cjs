const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/admin/js/report-create-validation.js'), 'utf8');

function control(value = '') {
    return {
        value,
        listeners: {},
        attributes: {},
        classList: { toggle() {}, add() {} },
        addEventListener(name, handler) { this.listeners[name] = handler; },
        setAttribute(name, value) { this.attributes[name] = value; },
        fire(name) { if (this.listeners[name]) this.listeners[name](); }
    };
}

const first = control('11');
const second = control('12');
first.checked = true;
second.checked = true;
const primary = control('0');
primary.options = [{ value: '0' }, { value: '11' }, { value: '12' }];
const primaryError = { hidden: true, textContent: '' };
const form = control();
let reviewOpened = false;
form.getAttribute = (name) => name === 'data-feu-current-status' ? 'publish' : '';
form.querySelector = (selector) => selector === '[data-feu-review-open]' ? { click() { reviewOpened = true; } } : null;
form.querySelectorAll = (selector) => {
    if (selector === 'input[name="post_category[]"]') return [first, second];
    if (selector === 'input[name="post_category[]"]:checked') return [first, second].filter((item) => item.checked);
    return [];
};

const document = {
    querySelector(selector) {
        if (selector === '.feu-einsatz-report-create-form') return form;
        if (selector === '[data-feu-primary-category-error]') return primaryError;
        return null;
    },
    getElementById(id) { return id === 'feu-einsatz-primary-category' ? primary : null; }
};
vm.runInNewContext(source, {
    document,
    window: { feuEinsatzReportValidation: { strings: { primaryCategoryRequired: 'Choose a selected keyword' } } },
    Array
});

function submit() {
    const event = { prevented: false, preventDefault() { this.prevented = true; } };
    form.listeners.submit(event);
    return event.prevented;
}

assert.equal(submit(), true, 'Two selected keywords require an explicit primary keyword.');
assert.equal(primaryError.hidden, false);
primary.value = '11';
assert.equal(submit(), false, 'A selected primary keyword allows submission.');
assert.equal(primaryError.hidden, true);
second.checked = false;
primary.value = '12';
assert.equal(submit(), true, 'A keyword removed from the selection cannot remain primary.');
primary.value = '0';
assert.equal(submit(), false, 'A single eligible keyword can be chosen automatically.');
first.value = '99';
assert.equal(submit(), true, 'A root or ineligible category cannot supply a public URL.');
first.value = '11';
const enter = { key: 'Enter', isComposing: false, target: { tagName: 'INPUT', type: 'text' }, preventDefault() {} };
form.listeners.keydown(enter);
assert.equal(reviewOpened, true, 'Enter on a published report must open the review before submission.');
console.log('Report primary keyword validation passed.');
