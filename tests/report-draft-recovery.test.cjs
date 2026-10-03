const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/admin/js/report-draft-recovery.js'), 'utf8');
const entries = new Map();
const draftKey = 'feu-einsatz-draft:https://example.test:1:42';
const storage = {
    getItem(key) { return entries.get(key) || null; },
    setItem(key, value) { entries.set(key, value); },
    removeItem(key) { entries.delete(key); }
};
function element(name = '', value = '', type = 'text') {
    return {
        name, value, type, tagName: 'INPUT', textContent: '', checked: false, hidden: true, listeners: {}, children: [],
        addEventListener(event, callback) { this.listeners[event] = callback; },
        dispatchEvent(event) { if (this.listeners[event.type]) this.listeners[event.type](event); },
        setAttribute(key, value) { this[key] = value; },
        getAttribute(key) { return this[key]; },
        appendChild(child) { child.parent = this; this.children.push(child); },
        replaceChildren() { this.children = []; },
        remove() { if (this.parent) this.parent.children = this.parent.children.filter((item) => item !== this); },
        querySelector(selector) { return selector === 'img' ? this.children.find((child) => child.tagName === 'IMG') || null : null; },
        click() { if (this.listeners.click) this.listeners.click(); }
    };
}
function load(saved = '0', options = {}) {
    const title = element('post_title', 'Saved title');
    const content = element('post_content', 'Saved body', 'textarea');
    content.id = 'feu_einsatz_new_report_content';
    const street = element('feu_einsatz_strasse', 'Old street');
    const category = element('post_category[]', '11', 'checkbox');
    category.setAttribute('data-feu-category-name', 'Feuer');
    const galleryInput = element('feu_einsatz_gallery_ids', '', 'hidden');
    galleryInput.id = 'feu_einsatz_gallery_ids';
    const controls = options.missingCategory ? [title, content, street, galleryInput] : [title, content, street, category, galleryInput];
    const participants = [];
    const organizations = [];
    const gallery = element();
    gallery.id = 'feu-einsatz-gallery-preview';
    const participantButton = element();
    participantButton.setAttribute('data-id', '7');
    participantButton.click = () => {
        if (participants.length) participants.pop();
        else participants.push({ id: '7', role: element('', ''), name: 'Max Mustermann' });
    };
    const organizationButton = element();
    organizationButton.setAttribute('data-id', '9');
    organizationButton.click = () => {
        if (organizations.length) organizations.pop();
        else organizations.push('9');
    };
    const restore = element();
    const discard = element();
    const notice = element();
    notice.querySelector = (selector) => selector === '[data-feu-draft-restore]' ? restore : discard;
    const warning = element();
    const warningList = element();
    const dismiss = element();
    warning.querySelector = (selector) => ({
        '[data-feu-draft-recovery-warning-list]': warningList,
        '[data-feu-draft-recovery-warning-dismiss]': dismiss
    })[selector];
    const form = element();
    form.dataset = { feuUserId: '1', feuReportId: '42', feuSaved: saved };
    form.querySelectorAll = (selector) => {
        if (selector === '[name]') return controls;
        if (selector === 'input[name="post_category[]"]') return options.missingCategory ? [] : [category];
        if (selector === '#feu-einsatz-teilnehmer-list .feu-einsatz-participant-row') {
            return participants.map((item) => ({
                getAttribute: () => item.id,
                querySelector: (query) => query === '.feu-einsatz-item-name' ? { textContent: item.name || '' } : item.role
            }));
        }
        if (selector === '#feu-einsatz-organisation-list .feu-einsatz-organization-row') {
            return organizations.map((id) => ({
                getAttribute: () => id,
                querySelector: (query) => query === '.feu-einsatz-item-name' ? { textContent: 'LF Hamburg' } : null
            }));
        }
        if (selector === '#feu-einsatz-gallery-preview .feu-einsatz-gallery-item') return gallery.children;
        if (selector === '#feu-einsatz-participant-picker .feu-einsatz-participant-chip-card') return options.missingParticipant ? [] : [participantButton];
        if (selector === '#feu-einsatz-organization-picker .feu-einsatz-organization-chip') return options.missingOrganization ? [] : [organizationButton];
        return [];
    };
    const document = {
        querySelector: (selector) => ({
            '.feu-einsatz-report-create-form': form,
            '[data-feu-draft-recovery]': notice,
            '[data-feu-draft-recovery-warning]': warning
        })[selector] || null,
        getElementById: (id) => id === content.id ? content : id === galleryInput.id ? galleryInput : id === gallery.id ? gallery : null,
        createElement: (tag) => { const created = element(); created.tagName = tag.toUpperCase(); return created; },
        addEventListener() {}
    };
    const requests = [];
    const window = {
        sessionStorage: storage, setInterval() {}, setTimeout() {}, addEventListener() {},
        feuEinsatzDraftRecovery: { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'test-nonce', strings: {} },
        fetch(_url, request) {
            requests.push(request);
            if (options.galleryFailure) return Promise.reject(new Error('offline'));
            const validIds = options.validGalleryIds === undefined ? [15] : options.validGalleryIds;
            return Promise.resolve({ ok: true, json: async () => ({ success: true, data: { validIds } }) });
        }
    };
    vm.runInNewContext(source, {
        document, window, location: { origin: 'https://example.test' },
        Event: class { constructor(type) { this.type = type; } }, Date, JSON, URLSearchParams
    });
    return { title, content, street, category, galleryInput, gallery, participants, organizations, form, notice, restore, warning, warningList, dismiss, requests };
}
const flush = () => new Promise((resolve) => setImmediate(resolve));

(async () => {
    const first = load();
    first.title.value = 'Unsaved title';
    first.content.value = 'Unsaved body';
    first.street.value = 'New street';
    first.category.checked = true;
    first.participants.push({ id: '7', role: element('', 'Maschinist'), name: 'Max Mustermann' });
    first.organizations.push('9');
    const image = element();
    image.src = 'https://example.test/image.jpg';
    const galleryRow = element();
    galleryRow.setAttribute('data-id', '15');
    galleryRow.querySelector = () => image;
    first.gallery.appendChild(galleryRow);
    first.galleryInput.value = '15';
    first.form.listeners.change();
    assert.equal(entries.size, 1, 'The whole form should be stored in session storage.');
    const savedSnapshot = entries.get(draftKey);

    const second = load();
    assert.equal(second.notice.hidden, false);
    second.restore.click();
    await flush();
    assert.equal(second.title.value, 'Unsaved title');
    assert.equal(second.content.value, 'Unsaved body');
    assert.equal(second.street.value, 'New street');
    assert.equal(second.category.checked, true);
    assert.equal(second.participants[0].role.value, 'Maschinist');
    assert.deepEqual(second.organizations, ['9']);
    assert.equal(second.galleryInput.value, '15');
    assert.equal(second.gallery.children.length, 1);
    assert.equal(second.notice.hidden, true);
    assert.equal(second.warning.hidden, true);
    assert.equal(second.requests.length, 1);
    assert.equal(second.requests[0].body.get('ids[]'), '15');

    const oldFormat = JSON.parse(savedSnapshot);
    oldFormat.snapshot.fields.post_title[0].value = 'Saved title';
    oldFormat.snapshot.fields.post_content[0].value = 'Saved body';
    oldFormat.snapshot.fields.feu_einsatz_strasse[0].value = 'Old street';
    oldFormat.snapshot.fields.feu_einsatz_gallery_ids[0].value = '';
    oldFormat.snapshot.fields['post_category[]'][0].checked = false;
    delete oldFormat.snapshot.fields['post_category[]'][0].name;
    oldFormat.snapshot.participants = [];
    oldFormat.snapshot.organizations = [];
    oldFormat.snapshot.gallery = [];
    entries.set(draftKey, JSON.stringify(oldFormat));
    assert.equal(load().notice.hidden, true, 'An unchanged older snapshot must not show a false recovery prompt.');
    entries.set(draftKey, savedSnapshot);

    const missing = load('0', { missingParticipant: true, missingOrganization: true, missingCategory: true, validGalleryIds: [] });
    missing.restore.click();
    await flush();
    assert.equal(missing.warning.hidden, false);
    assert.equal(missing.warningList.children.length, 4, missing.warningList.children.map((item) => item.textContent).join(' | '));
    assert.match(missing.warningList.children[0].textContent, /Teilnehmer Max Mustermann/);
    assert.match(missing.warningList.children[1].textContent, /Organisation LF Hamburg/);
    assert.match(missing.warningList.children[2].textContent, /Einsatzstichwort Feuer/);
    assert.match(missing.warningList.children[3].textContent, /Foto-ID 15/);
    assert.equal(missing.galleryInput.value, '');
    assert.equal(missing.gallery.children.length, 0);
    assert.equal(entries.has(draftKey + ':warnings'), true);
    const pending = JSON.parse(entries.get(draftKey)).snapshot;
    assert.equal(pending.participants[0].id, '7', 'A missing participant must remain in the recovery snapshot.');
    assert.equal(pending.organizations[0].id, '9', 'A missing organization must remain in the recovery snapshot.');
    assert.equal(pending.gallery[0].id, '15', 'A missing photo must remain in the recovery snapshot.');
    assert.equal(pending.fields['post_category[]'][0].value, '11', 'A missing keyword must remain in the recovery snapshot.');
    const afterReload = load();
    assert.equal(afterReload.warning.hidden, false, 'Partial recovery warning must survive a reload.');
    assert.equal(afterReload.notice.hidden, false, 'Missing selections should be retryable after a reload.');
    afterReload.restore.click();
    await flush();
    assert.equal(afterReload.participants.length, 1);
    assert.deepEqual(afterReload.organizations, ['9']);
    assert.equal(afterReload.category.checked, true, 'A keyword restored after a retry should be selected.');
    assert.equal(afterReload.galleryInput.value, '15');
    assert.equal(afterReload.warning.hidden, true, 'A successful retry should clear the warning.');
    afterReload.dismiss.click();
    assert.equal(entries.has(draftKey + ':warnings'), false);

    entries.set(draftKey, savedSnapshot);
    const offline = load('0', { galleryFailure: true });
    offline.restore.click();
    await flush();
    assert.equal(offline.galleryInput.value, '15', 'A failed check must preserve the photo selection.');
    assert.match(offline.warningList.children[0].textContent, /Fotos konnten nicht geprüft/);

    entries.delete(draftKey + ':warnings');
    entries.set(draftKey, savedSnapshot);
    const brokenPreview = load();
    brokenPreview.restore.click();
    await flush();
    brokenPreview.gallery.children[0].children[0].listeners.error();
    assert.match(brokenPreview.warningList.children[0].textContent, /Vorschau für Foto-ID 15/);

    entries.delete(draftKey);
    const withoutDraft = load();
    assert.equal(withoutDraft.warning.hidden, true, 'Warnings from a vanished draft must not linger.');
    assert.equal(entries.has(draftKey + ':warnings'), false);

    const third = load('1');
    assert.equal(third.notice.hidden, true);
    assert.equal(entries.size, 0, 'A successful server save should clear local recovery state.');
    entries.set(draftKey, JSON.stringify({ savedAt: Date.now(), title: 'Earlier title', content: 'Earlier body' }));
    const legacy = load();
    assert.equal(legacy.notice.hidden, false, 'Text snapshots from the previous version remain recoverable.');
    legacy.restore.click();
    assert.equal(legacy.title.value, 'Earlier title');
    assert.equal(legacy.content.value, 'Earlier body');
    assert.ok(savedSnapshot, 'The original full snapshot was created.');
    console.log('Full report recovery flags missing selections and photos.');
})().catch((error) => { console.error(error); process.exitCode = 1; });
