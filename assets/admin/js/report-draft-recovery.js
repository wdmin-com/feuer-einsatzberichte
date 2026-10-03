(function () {
    'use strict';

    var form = document.querySelector('.feu-einsatz-report-create-form');
    var notice = document.querySelector('[data-feu-draft-recovery]');
    var content = document.getElementById('feu_einsatz_new_report_content');
    if (!form || !notice || !content) return;
    var storage;
    try { storage = window.sessionStorage; } catch (error) { return; }
    var key = 'feu-einsatz-draft:' + location.origin + ':' + form.dataset.feuUserId + ':' + form.dataset.feuReportId;
    var warningKey = key + ':warnings';
    var warningNotice = document.querySelector('[data-feu-draft-recovery-warning]');
    var warningList = warningNotice ? warningNotice.querySelector('[data-feu-draft-recovery-warning-list]') : null;
    var recoveryConfig = window.feuEinsatzDraftRecovery || {};
    var recoveryStrings = recoveryConfig.strings || {};
    var warnings = [];
    var unresolved = { participants: [], organizations: [], gallery: [], categories: [] };
    var maxAge = 24 * 60 * 60 * 1000;
    var submitting = false;
    var restoring = false;
    var ignored = /^(?:action|post_id|feu_einsatz_report_status|.*_nonce|_wp_http_referer|feu_einsatz_is_einsatzbericht)$/;

    function editor() { return window.tinymce && window.tinymce.get ? window.tinymce.get(content.id) : null; }
    function all(selector) { return Array.prototype.slice.call(form.querySelectorAll(selector)); }
    function controls() {
        return all('[name]').filter(function (field) {
            return field.name && !ignored.test(field.name)
                && !/^feu_einsatz_(?:teilnehmer|organisationen)\[/.test(field.name)
                && !/^(?:submit|button|file|password)$/i.test(field.type || '');
        });
    }
    function rows(selector) { return all(selector); }
    function includeUnresolved(current, pending) {
        var present = current.map(function (item) { return String(item.id); });
        var stillMissing = pending.filter(function (item) { return present.indexOf(String(item.id)) === -1; });
        pending.splice.apply(pending, [0, pending.length].concat(stillMissing));
        return current.concat(stillMissing);
    }
    function snapshot() {
        var fields = {};
        controls().forEach(function (field) {
            if (!fields[field.name]) fields[field.name] = [];
            var state = field.type === 'checkbox' || field.type === 'radio'
                ? { value: field.value, checked: field.checked } : { value: field.value };
            if (field.name === 'post_category[]') state.name = field.getAttribute('data-feu-category-name') || '';
            fields[field.name].push(state);
        });
        var availableCategories = (fields['post_category[]'] || []).map(function (item) { return String(item.value); });
        unresolved.categories = unresolved.categories.filter(function (item) { return availableCategories.indexOf(String(item.value)) === -1; });
        if (unresolved.categories.length) {
            fields['post_category[]'] = (fields['post_category[]'] || []).concat(unresolved.categories);
        }
        var activeEditor = editor();
        fields.post_content = [{ value: activeEditor && !activeEditor.isHidden() ? activeEditor.getContent() : content.value }];
        return {
            fields: fields,
            participants: includeUnresolved(rows('#feu-einsatz-teilnehmer-list .feu-einsatz-participant-row').map(function (row) {
                var role = row.querySelector('.feu-einsatz-function-select');
                var name = row.querySelector('.feu-einsatz-item-name');
                return { id: row.getAttribute('data-id'), role: role ? role.value : '', name: name ? name.textContent.trim() : '' };
            }), unresolved.participants),
            organizations: includeUnresolved(rows('#feu-einsatz-organisation-list .feu-einsatz-organization-row').map(function (row) {
                var name = row.querySelector('.feu-einsatz-item-name');
                return { id: row.getAttribute('data-id'), name: name ? name.textContent.trim() : '' };
            }), unresolved.organizations),
            gallery: includeUnresolved(rows('#feu-einsatz-gallery-preview .feu-einsatz-gallery-item').map(function (row) {
                var image = row.querySelector('img');
                return { id: row.getAttribute('data-id'), src: image ? image.src : '' };
            }), unresolved.gallery)
        };
    }
    function serialized(value) { return JSON.stringify(value); }
    function migrateCategoryNames(saved) {
        var categories = saved.fields && saved.fields['post_category[]'];
        if (!Array.isArray(categories)) return;
        var current = all('input[name="post_category[]"]');
        categories.forEach(function (item) {
            if (!item || Object.prototype.hasOwnProperty.call(item, 'name')) return;
            var field = current.find(function (candidate) { return String(candidate.value) === String(item.value); });
            item.name = field ? field.getAttribute('data-feu-category-name') || '' : '';
        });
    }
    function showWarnings(persist) {
        if (!warningNotice || !warningList) return;
        warningList.replaceChildren();
        warnings.forEach(function (message) {
            var item = document.createElement('li');
            item.textContent = message;
            warningList.appendChild(item);
        });
        warningNotice.hidden = warnings.length === 0;
        if (persist === false) return;
        try {
            if (warnings.length) storage.setItem(warningKey, serialized({ savedAt: Date.now(), messages: warnings }));
            else storage.removeItem(warningKey);
        } catch (error) { /* A storage failure must not hide the warning. */ }
    }
    function warn(template, id) {
        var message = template.replace('%s', String(id));
        if (warnings.indexOf(message) === -1) {
            warnings.push(message);
            showWarnings();
        }
    }
    var initial;
    function save() {
        if (restoring) return;
        var current = snapshot();
        if (serialized(current) === serialized(initial)) { storage.removeItem(key); return; }
        try { storage.setItem(key, serialized({ savedAt: Date.now(), snapshot: current })); }
        catch (error) { /* Storage quota must not block editing. */ }
    }
    function pickerClick(selector, id) {
        var button = all(selector).find(function (item) { return item.getAttribute('data-id') === String(id); });
        if (button && !button.disabled) button.click();
    }
    function restoreSelections(saved) {
        unresolved.participants = [];
        unresolved.organizations = [];
        var participants = Array.isArray(saved.participants) ? saved.participants.filter(function (item) { return item && item.id != null; }) : [];
        rows('#feu-einsatz-teilnehmer-list .feu-einsatz-participant-row').forEach(function (row) {
            if (!participants.some(function (item) { return String(item.id) === row.getAttribute('data-id'); })) {
                pickerClick('#feu-einsatz-participant-picker .feu-einsatz-participant-chip-card', row.getAttribute('data-id'));
            }
        });
        participants.forEach(function (item) {
            if (!rows('#feu-einsatz-teilnehmer-list .feu-einsatz-participant-row').some(function (row) { return row.getAttribute('data-id') === String(item.id); })) {
                pickerClick('#feu-einsatz-participant-picker .feu-einsatz-participant-chip-card', item.id);
            }
            if (!rows('#feu-einsatz-teilnehmer-list .feu-einsatz-participant-row').some(function (row) { return row.getAttribute('data-id') === String(item.id); })) {
                unresolved.participants.push(item);
                warn(recoveryStrings.participant || 'Teilnehmer %s konnte nicht wiederhergestellt werden.', item.name || '#' + item.id);
            }
            rows('#feu-einsatz-teilnehmer-list .feu-einsatz-participant-row').forEach(function (row) {
                if (row.getAttribute('data-id') !== String(item.id)) return;
                var role = row.querySelector('.feu-einsatz-function-select');
                if (role) { role.value = item.role; role.dispatchEvent(new Event('change', { bubbles: true })); }
            });
        });
        var participantList = document.getElementById('feu-einsatz-teilnehmer-list');
        if (participantList) {
            participants.forEach(function (item) {
                var row = rows('#feu-einsatz-teilnehmer-list .feu-einsatz-participant-row').find(function (candidate) {
                    return candidate.getAttribute('data-id') === String(item.id);
                });
                if (row) participantList.appendChild(row);
            });
            rows('#feu-einsatz-teilnehmer-list .feu-einsatz-participant-row').forEach(function (row, index) {
                var idField = row.querySelector('.feu-einsatz-participant-id-field');
                var roleField = row.querySelector('.feu-einsatz-function-select');
                if (idField) idField.name = 'feu_einsatz_teilnehmer[' + index + '][id]';
                if (roleField) roleField.name = 'feu_einsatz_teilnehmer[' + index + '][funktion]';
            });
        }
        var organizationEntries = Array.isArray(saved.organizations) ? saved.organizations : [];
        var organizations = organizationEntries.map(function (item) { return String(item && typeof item === 'object' ? item.id : item); });
        rows('#feu-einsatz-organisation-list .feu-einsatz-organization-row').forEach(function (row) {
            if (organizations.indexOf(row.getAttribute('data-id')) === -1) {
                pickerClick('#feu-einsatz-organization-picker .feu-einsatz-organization-chip', row.getAttribute('data-id'));
            }
        });
        organizations.forEach(function (id) {
            if (!rows('#feu-einsatz-organisation-list .feu-einsatz-organization-row').some(function (row) { return row.getAttribute('data-id') === id; })) {
                pickerClick('#feu-einsatz-organization-picker .feu-einsatz-organization-chip', id);
            }
            if (!rows('#feu-einsatz-organisation-list .feu-einsatz-organization-row').some(function (row) { return row.getAttribute('data-id') === id; })) {
                var savedOrganization = organizationEntries.find(function (item) { return item && typeof item === 'object' && String(item.id) === id; });
                unresolved.organizations.push(savedOrganization || { id: id, name: '' });
                warn(recoveryStrings.organization || 'Organisation %s konnte nicht wiederhergestellt werden.', savedOrganization && savedOrganization.name || '#' + id);
            }
        });
        organizations.forEach(function (id, targetIndex) {
            var current = rows('#feu-einsatz-organisation-list .feu-einsatz-organization-row');
            var index = current.findIndex(function (row) { return row.getAttribute('data-id') === id; });
            while (index > targetIndex) {
                var up = current[index].querySelector('.feu-einsatz-move-up');
                if (!up) break;
                up.click(); index--;
                current = rows('#feu-einsatz-organisation-list .feu-einsatz-organization-row');
            }
        });
    }
    function restoreGallery(saved) {
        unresolved.gallery = [];
        var gallery = document.getElementById('feu-einsatz-gallery-preview');
        var galleryInput = document.getElementById('feu_einsatz_gallery_ids');
        if (!gallery || !galleryInput || !Array.isArray(saved.gallery)) return;
        gallery.replaceChildren();
        saved.gallery.forEach(function (entry) {
            if (!entry) return;
            if (!/^\d+$/.test(String(entry.id))) {
                warn(recoveryStrings.restoreFailed || 'Einige Formulardaten konnten nicht wiederhergestellt werden.', '');
                return;
            }
            var item = document.createElement('div');
            item.className = 'feu-einsatz-gallery-item';
            item.setAttribute('data-id', entry.id);
            if (entry.src && /^https?:\/\//.test(entry.src)) {
                var image = document.createElement('img');
                image.src = entry.src; image.alt = ''; image.className = 'feu-einsatz-gallery-thumb';
                image.addEventListener('error', function () {
                    warn(recoveryStrings.photoPreview || 'Die Vorschau für Foto-ID %s konnte nicht geladen werden.', entry.id);
                });
                item.appendChild(image);
            } else {
                warn(recoveryStrings.photoPreview || 'Die Vorschau für Foto-ID %s konnte nicht geladen werden.', entry.id);
            }
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'button-link-delete feu-einsatz-remove-gallery-image feu-einsatz-gallery-remove-button';
            remove.textContent = '×';
            item.appendChild(remove);
            gallery.appendChild(item);
        });
        galleryInput.value = saved.gallery.filter(Boolean).map(function (entry) { return entry.id; }).filter(function (id) { return /^\d+$/.test(String(id)); }).join(',');
    }
    function verifyGallery(saved) {
        var ids = Array.isArray(saved.gallery) ? saved.gallery.filter(Boolean).map(function (entry) { return String(entry.id); }).filter(function (id) { return /^\d+$/.test(id); }) : [];
        if (!ids.length) return;
        if (!recoveryConfig.ajaxUrl || !recoveryConfig.nonce || typeof window.fetch !== 'function') {
            warn(recoveryStrings.galleryUnchecked || 'Die wiederhergestellten Fotos konnten nicht geprüft werden.', '');
            return;
        }
        var params = new URLSearchParams();
        params.set('action', 'feu_einsatz_validate_recovery_gallery');
        params.set('nonce', recoveryConfig.nonce);
        params.set('post_id', form.dataset.feuReportId || '0');
        ids.forEach(function (id) { params.append('ids[]', id); });
        window.fetch(recoveryConfig.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: params })
            .then(function (response) { if (!response.ok) throw new Error('Gallery validation failed'); return response.json(); })
            .then(function (response) {
                if (!response.success || !response.data || !Array.isArray(response.data.validIds)) {
                    throw new Error('Gallery validation failed');
                }
                var validIds = response.data.validIds.map(String);
                var invalidIds = ids.filter(function (id) { return validIds.indexOf(id) === -1; });
                invalidIds.forEach(function (id) {
                    var original = saved.gallery.find(function (entry) { return entry && String(entry.id) === id; });
                    if (original) unresolved.gallery.push(original);
                    warn(recoveryStrings.photo || 'Foto-ID %s ist nicht mehr verfügbar.', id);
                });
                if (!invalidIds.length) return;
                rows('#feu-einsatz-gallery-preview .feu-einsatz-gallery-item').forEach(function (row) {
                    if (invalidIds.indexOf(row.getAttribute('data-id')) !== -1) row.remove();
                });
                var galleryInput = document.getElementById('feu_einsatz_gallery_ids');
                if (galleryInput) {
                    galleryInput.value = galleryInput.value.split(',').filter(function (id) { return invalidIds.indexOf(id) === -1; }).join(',');
                }
                save();
            })
            .catch(function () {
                warn(recoveryStrings.galleryUnchecked || 'Die wiederhergestellten Fotos konnten nicht geprüft werden.', '');
            });
    }
    function restore(saved) {
        warnings = [];
        showWarnings();
        restoring = true;
        try {
            restoreSelections(saved);
            var availableCategories = all('input[name="post_category[]"]').map(function (field) { return String(field.value); });
            unresolved.categories = (saved.fields && Array.isArray(saved.fields['post_category[]']) ? saved.fields['post_category[]'] : [])
                .filter(function (item) { return item && item.checked && availableCategories.indexOf(String(item.value)) === -1; });
            unresolved.categories.forEach(function (item) {
                warn(recoveryStrings.category || 'Einsatzstichwort %s konnte nicht wiederhergestellt werden.', item.name || '#' + item.value);
            });
            controls().forEach(function (field) {
                if (field.name === 'post_content' || field.name === 'feu_einsatz_gallery_ids') return;
                var group = all('[name]').filter(function (item) { return item.name === field.name; });
                var values = saved.fields && saved.fields[field.name];
                values = Array.isArray(values) ? values : [];
                var item = (field.type === 'checkbox' || field.type === 'radio')
                    ? values.find(function (candidate) { return candidate && candidate.value === field.value; })
                    : values[group.indexOf(field)];
                if (!item) return;
                if (field.type === 'checkbox' || field.type === 'radio') field.checked = !!item.checked;
                else field.value = item.value;
                field.dispatchEvent(new Event(field.type === 'checkbox' || field.type === 'radio' || field.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true }));
            });
            var savedContent = saved.fields && saved.fields.post_content && saved.fields.post_content[0];
            if (savedContent) {
                content.value = savedContent.value;
                var activeEditor = editor();
                if (activeEditor && !activeEditor.isHidden()) activeEditor.setContent(savedContent.value);
                content.dispatchEvent(new Event('input', { bubbles: true }));
            }
            restoreGallery(saved);
        } catch (error) {
            warn(recoveryStrings.restoreFailed || 'Einige Formulardaten konnten nicht wiederhergestellt werden.', '');
            return;
        } finally {
            restoring = false;
        }
        notice.hidden = true;
        save();
        verifyGallery(saved);
    }

    if (form.dataset.feuSaved === '1') {
        storage.removeItem(key);
        storage.removeItem(warningKey);
    }
    try {
        var priorWarnings = JSON.parse(storage.getItem(warningKey) || 'null');
        if (priorWarnings && Date.now() - Number(priorWarnings.savedAt) <= maxAge && Array.isArray(priorWarnings.messages)) {
            warnings = priorWarnings.messages.filter(function (message) { return typeof message === 'string'; });
            showWarnings(false);
        } else {
            storage.removeItem(warningKey);
        }
    } catch (error) { storage.removeItem(warningKey); }
    if (warningNotice) {
        var dismiss = warningNotice.querySelector('[data-feu-draft-recovery-warning-dismiss]');
        if (dismiss) dismiss.addEventListener('click', function () { warnings = []; showWarnings(); });
    }
    initial = snapshot();
    try {
        var saved = JSON.parse(storage.getItem(key) || 'null');
        if (saved && !saved.snapshot && typeof saved.title === 'string' && typeof saved.content === 'string') {
            saved.snapshot = snapshot();
            if (saved.snapshot.fields.post_title) saved.snapshot.fields.post_title[0].value = saved.title;
            saved.snapshot.fields.post_content[0].value = saved.content;
        }
        if (saved && saved.snapshot) migrateCategoryNames(saved.snapshot);
        if (saved && saved.snapshot && Number(saved.savedAt) > 0) {
            if (Date.now() - Number(saved.savedAt) > maxAge) storage.removeItem(key);
            else if (serialized(saved.snapshot) !== serialized(initial)) {
                notice.hidden = false;
                notice.querySelector('[data-feu-draft-restore]').addEventListener('click', function () { restore(saved.snapshot); });
                notice.querySelector('[data-feu-draft-discard]').addEventListener('click', function () { storage.removeItem(key); storage.removeItem(warningKey); warnings = []; showWarnings(); notice.hidden = true; });
            }
        }
        if (notice.hidden && warnings.length) {
            warnings = [];
            showWarnings();
        }
    } catch (error) {
        storage.removeItem(key);
        warnings = [];
        showWarnings();
    }

    form.addEventListener('input', save);
    form.addEventListener('change', save);
    form.addEventListener('click', function () { window.setTimeout(save, 0); });
    document.addEventListener('tinymce-editor-init', function (event, activeEditor) {
        if (activeEditor && activeEditor.id === content.id) activeEditor.on('change keyup input', save);
    });
    window.setInterval(save, 5000);
    form.addEventListener('submit', function (event) { if (!event.defaultPrevented) { save(); submitting = true; } });
    window.addEventListener('beforeunload', function (event) {
        if (!submitting && serialized(snapshot()) !== serialized(initial)) {
            save(); event.preventDefault(); event.returnValue = '';
        }
    });
}());
