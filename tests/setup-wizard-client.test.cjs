const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/admin/js/setup-wizard.js'), 'utf8');
function element() {
    return {
        checked: false,
        required: false,
        hidden: false,
        listeners: {},
        dataset: {},
        classList: { toggle() {}, add() {}, remove() {}, contains() { return false; } },
        addEventListener(name, handler) { this.listeners[name] = handler; },
        setAttribute() {},
        removeAttribute() {},
        focus() {},
        checkValidity() { return this.patternActive ? (!this.value && !this.required) || this.value === '22525' : !this.required || !!this.value; },
        reportValidity() {}
    };
}

const fields = Object.fromEntries([
    'feu_einsatz_sample_street', 'feu_einsatz_sample_postcode', 'feu_einsatz_sample_city',
    'feu_einsatz_sample_first_name', 'feu_einsatz_sample_last_name',
    'feu_einsatz_create_sample_report', 'feu_einsatz_create_sample_participant'
].map((name) => [name, element()]));
fields.feu_einsatz_sample_postcode.value = '12';
fields.feu_einsatz_sample_postcode.patternActive = true;
fields.feu_einsatz_sample_street.value = 'Musterstraße 1';
fields.feu_einsatz_sample_city.value = 'Hamburg';
const form = element();
form.querySelector = (selector) => fields[selector.match(/^\[name="(.+)"\]$/)?.[1]] || null;
const steps = [element(), element(), element()];
steps[0].querySelectorAll = () => [];
steps[1].querySelectorAll = () => [];
steps[2].querySelectorAll = () => [fields.feu_einsatz_sample_postcode];
const progress = [element(), element(), element()];
const check = element();
check.checked = true;
const count = element();
const named = Object.fromEntries([
    'data-feu-setup-prev', 'data-feu-setup-next', 'data-feu-setup-finish',
    'data-feu-setup-select-all', 'data-feu-setup-select-none',
    'data-feu-setup-logo-id', 'data-feu-setup-logo-preview',
    'data-feu-setup-logo-select', 'data-feu-setup-logo-remove'
].map((name) => ['[' + name + ']', element()]));
named['.feu-einsatz-setup-wizard-card'] = element();
const wizard = element();
wizard.querySelector = (selector) => selector === 'form' ? form : selector === '[data-feu-setup-category-count]' ? count : named[selector];
wizard.querySelectorAll = (selector) => selector === '[data-feu-setup-step]' ? steps
    : selector === '[data-feu-setup-progress]' ? progress : [check];

vm.runInNewContext(source, {
    document: { querySelector: () => wizard },
    window: {},
    Array,
    Math
});

function submit() {
    const event = { submitter: named['[data-feu-setup-finish]'], prevented: false, preventDefault() { this.prevented = true; } };
    form.listeners.submit(event);
    return event.prevented;
}

assert.equal(submit(), false, 'Invalid optional sample data must not block setup.');
fields.feu_einsatz_create_sample_report.checked = true;
assert.equal(submit(), true, 'The same data must block setup when a sample report is requested.');
console.log('Setup wizard validates sample fields only when their example is enabled.');
