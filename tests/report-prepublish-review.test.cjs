const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/admin/js/report-prepublish-review.js'), 'utf8');
function node(value = '') {
    return {
        value, textContent: '', hidden: true, disabled: false, listeners: {}, attributes: {}, children: [],
        addEventListener(name, handler) { this.listeners[name] = handler; },
        appendChild(child) { this.children.push(child); },
        getAttribute(name) { return this.attributes[name]; },
        setAttribute(name, value) { this.attributes[name] = value; },
        scrollIntoView() {}, focus() {}
    };
}
const fields = {
    feu_einsatz_strasse: node('Musterstraße'), feu_einsatz_hausnummer: node('7'),
    feu_einsatz_plz: node('22525'), feu_einsatz_stadt: node('Hamburg'),
    feu_einsatz_map_public_precision: node('exact'),
    feu_einsatz_new_report_title: node('Feuer in Straße'),
    feu_einsatz_new_report_content: node('Einsatzbericht'),
    feu_einsatz_datum: node('07.10.2026'), feu_einsatz_uhrzeit: node('13:30'),
    feu_einsatz_gallery_ids: node('')
};
const address = node();
const map = node();
const url = node();
const urlStatus = node();
const confirm = node();
const checklist = node();
const heading = node();
const panel = node();
panel.attributes = { 'data-feu-fixed-url': '' };
panel.querySelector = (selector) => ({
    '[data-feu-review-address]': address, '[data-feu-review-map]': map,
    '[data-feu-review-url]': url, '[data-feu-review-url-status]': urlStatus,
    '[data-feu-review-confirm]': confirm, '[data-feu-review-checklist]': checklist, h3: heading
})[selector];
const open = node();
const pendingOpen = node();
pendingOpen.attributes['data-feu-review-status'] = 'pending';
const keyword = node('11');
const status = node();
status.textContent = 'Karte bereit';
const mode = node();
mode.textContent = 'Ganze Straße';
const form = node();
form.attributes['data-feu-report-id'] = '42';
form.querySelector = (selector) => ({
    '[data-feu-prepublish-review]': panel, '[data-feu-review-open]': open,
    'input[name="feu_einsatz_map_location_mode"]:checked': { value: 'address' },
    '#feu-einsatz-primary-category': { value: '11' },
    '[data-feu-address-map-preview-status]': status,
    '[data-feu-address-map-preview-mode]': mode
})[selector] || null;
form.querySelectorAll = (selector) => selector === '[data-feu-review-open]' ? [open, pendingOpen] : [keyword];
const document = { querySelector: () => form, getElementById: (id) => fields[id] || null, createElement: () => node() };
const requests = [];
const window = {
    feuEinsatzReportUrlReview: { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'test-nonce', strings: {} },
    setTimeout(callback) { callback(); return 1; },
    clearTimeout() {},
    fetch(_url, options) { return new Promise((resolve) => { requests.push({ body: options.body, resolve }); }); }
};
vm.runInNewContext(source, { document, window, Array, URLSearchParams });
const flush = () => new Promise((resolve) => setImmediate(resolve));

(async () => {
    open.listeners.click();
    assert.equal(panel.hidden, false);
    assert.equal(address.textContent, 'Musterstraße 7 22525 Hamburg');
    assert.match(map.textContent, /Karte bereit/);
    assert.equal(confirm.disabled, true);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].body.get('post_title'), 'Feuer in Straße');
    assert.equal(requests[0].body.get('primary_category'), '11');
    requests[0].resolve({ ok: true, json: async () => ({ success: true, data: { status: 'available', url: 'https://example.test/einsaetze/feuer/feuer-in-strasse/' } }) });
    await flush();
    assert.equal(url.textContent, 'https://example.test/einsaetze/feuer/feuer-in-strasse/');
    assert.equal(confirm.disabled, false);
    fields.feu_einsatz_new_report_content.value = '';
    form.listeners.input();
    assert.equal(confirm.disabled, true);
    fields.feu_einsatz_new_report_content.value = 'Einsatzbericht';
    form.listeners.input();
    assert.equal(confirm.disabled, false);
    assert.equal(urlStatus.attributes['data-state'], 'available');
    form.listeners.input();
    assert.equal(requests.length, 1);

    fields.feu_einsatz_new_report_title.value = 'Existing';
    form.listeners.input();
    assert.equal(requests.length, 2);
    fields.feu_einsatz_new_report_title.value = 'Unique';
    form.listeners.input();
    assert.equal(requests.length, 3);
    requests[2].resolve({ ok: true, json: async () => ({ success: true, data: { status: 'available', url: 'https://example.test/einsaetze/feuer/unique/' } }) });
    await flush();
    requests[1].resolve({ ok: true, json: async () => ({ success: true, data: { status: 'conflict', url: 'https://example.test/einsaetze/feuer/existing/' } }) });
    await flush();
    assert.equal(url.textContent, 'https://example.test/einsaetze/feuer/unique/');
    assert.equal(confirm.disabled, false);

    fields.feu_einsatz_new_report_title.value = 'Existing';
    form.listeners.input();
    requests[3].resolve({ ok: true, json: async () => ({ success: true, data: { status: 'conflict', url: 'https://example.test/einsaetze/feuer/existing/' } }) });
    await flush();
    assert.equal(confirm.disabled, true);
    assert.equal(urlStatus.attributes['data-state'], 'conflict');

    fields.feu_einsatz_new_report_title.value = 'Needs keyword';
    form.listeners.input();
    requests[4].resolve({ ok: true, json: async () => ({ success: true, data: { status: 'missing_category', url: '' } }) });
    await flush();
    assert.equal(confirm.disabled, true);
    assert.equal(urlStatus.attributes['data-state'], 'missing_category');

    fields.feu_einsatz_new_report_title.value = 'Network unavailable';
    form.listeners.input();
    requests[5].resolve({ ok: false });
    await flush();
    assert.equal(confirm.disabled, false);
    assert.equal(urlStatus.attributes['data-state'], 'unavailable');

    panel.attributes['data-feu-fixed-url'] = 'https://example.test/einsaetze/feuer/fest/';
    form.listeners.change();
    assert.equal(url.textContent, 'https://example.test/einsaetze/feuer/fest/');
    assert.equal(confirm.disabled, false);
    pendingOpen.listeners.click();
    assert.equal(confirm.value, 'pending');
    console.log('Prepublication URL review handles availability, conflicts and stale responses.');
})().catch((error) => { console.error(error); process.exitCode = 1; });
