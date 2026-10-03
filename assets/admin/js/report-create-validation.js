(function () {
    'use strict';

    var config = window.feuEinsatzReportValidation || {};
    var strings = config.strings || {};
    var form = document.querySelector('.feu-einsatz-report-create-form');

    if (!form) {
        return;
    }

    var notice = document.getElementById('feu-einsatz-report-validation-notice');
    var noticeList = notice ? notice.querySelector('.feu-einsatz-report-validation-list') : null;
    var detailsBox = document.getElementById('feu-einsatz-report-box-details');
    var categoriesBox = document.getElementById('feu-einsatz-report-box-categories');
    var categoryList = document.querySelector('[data-feu-category-list]');
    var categoryError = document.querySelector('[data-feu-category-error]');
    var primaryCategory = document.getElementById('feu-einsatz-primary-category');
    var primaryCategoryError = document.querySelector('[data-feu-primary-category-error]');
    var submitButtons = form.querySelectorAll('button[type="submit"]');
    var fields = [
        { id: 'feu_einsatz_strasse', label: strings.street, message: strings.streetRequired, type: 'required' },
        { id: 'feu_einsatz_hausnummer', label: strings.houseNumber, message: strings.houseNumberRequired, type: 'required' },
        { id: 'feu_einsatz_plz', label: strings.postcode, message: strings.postcodeInvalid, type: 'postcode' },
        { id: 'feu_einsatz_stadt', label: strings.city, message: strings.cityRequired, type: 'required' },
        { id: 'feu_einsatz_latitude', label: strings.latitude, message: strings.latitudeInvalid, type: 'latitude' },
        { id: 'feu_einsatz_longitude', label: strings.longitude, message: strings.longitudeInvalid, type: 'longitude' },
        { id: 'feu_einsatz_datum', label: strings.date, message: strings.dateInvalid, type: 'date' },
        { id: 'feu_einsatz_uhrzeit', label: strings.time, message: strings.timeInvalid, type: 'time' }
    ];

    function needsPreciseAddress() {
        var modeField = getField('feu_einsatz_map_highlight_override');
        var previewMode = document.querySelector('[data-feu-address-map-preview-mode]');
        var mode = modeField ? modeField.value : 'default';
        if (mode === 'default') {
            mode = previewMode ? (previewMode.getAttribute('data-default-map-mode') || 'full') : 'full';
        }
        return mode === 'length' || mode === 'radius';
    }

    function fieldIsRequired(configField) {
        var location = form.querySelector('input[name="feu_einsatz_map_location_mode"]:checked');
        var coordinates = location && location.value === 'coordinates';
        if (configField.id === 'feu_einsatz_hausnummer') {
            return !coordinates && needsPreciseAddress();
        }
        if (['feu_einsatz_strasse', 'feu_einsatz_plz', 'feu_einsatz_stadt'].indexOf(configField.id) !== -1) {
            return !coordinates;
        }
        if (['feu_einsatz_latitude', 'feu_einsatz_longitude'].indexOf(configField.id) !== -1) {
            return coordinates;
        }
        return true;
    }

    function getField(id) {
        return document.getElementById(id);
    }

    function isValidDate(value) {
        var parts;
        var date;

        if (/^\d{2}\.\d{2}\.\d{4}$/.test(value)) {
            parts = value.split('.');
            date = new Date(Number(parts[2]), Number(parts[1]) - 1, Number(parts[0]));
            return date.getFullYear() === Number(parts[2]) && date.getMonth() === Number(parts[1]) - 1 && date.getDate() === Number(parts[0]);
        }

        if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            parts = value.split('-');
            date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
            return date.getFullYear() === Number(parts[0]) && date.getMonth() === Number(parts[1]) - 1 && date.getDate() === Number(parts[2]);
        }

        return false;
    }

    function validateValue(field) {
        var value = field.value.trim();

        if (field.type === 'required') {
            return value !== '';
        }
        if (field.type === 'postcode') {
            return /^\d{5}$/.test(value);
        }
        if (field.type === 'date') {
            return isValidDate(value);
        }
        if (field.type === 'time') {
            return /^([01]\d|2[0-3]):[0-5]\d$/.test(value);
        }
        if (field.type === 'latitude' || field.type === 'longitude') {
            var normalized = value.replace(',', '.');
            var number = Number(normalized);
            var limit = field.type === 'latitude' ? 90 : 180;
            return normalized !== '' && Number.isFinite(number) && number >= -limit && number <= limit;
        }

        return true;
    }

    function ensureFieldErrorNode(field) {
        var existing = form.querySelector('[data-feu-field-error-for="' + field.id + '"]');

        if (existing) {
            return existing;
        }

        var node = document.createElement('p');
        node.className = 'feu-einsatz-field-error';
        node.setAttribute('data-feu-field-error-for', field.id);
        node.setAttribute('aria-live', 'polite');
        node.hidden = true;
        (field.closest('.feu-einsatz-field') || field.closest('.feu-einsatz-form-row') || field.parentNode).appendChild(node);
        return node;
    }

    function validateField(configField) {
        var field = getField(configField.id);

        if (!field) {
            return '';
        }

        var errorNode = ensureFieldErrorNode(field);
        var valid = !fieldIsRequired(configField) || validateValue({ value: field.value, type: configField.type });

        field.classList.toggle('feu-einsatz-field-invalid', !valid);
        field.setAttribute('aria-invalid', valid ? 'false' : 'true');
        errorNode.textContent = valid ? '' : configField.message;
        errorNode.hidden = valid;
        return valid ? '' : configField.message;
    }

    function validateCategories() {
        var checkboxes = form.querySelectorAll('input[name="post_category[]"]');
        var selected = Array.prototype.some.call(checkboxes, function (checkbox) {
            return checkbox.checked;
        });

        if (categoryList) {
            categoryList.classList.toggle('feu-einsatz-field-invalid', !selected);
            categoryList.setAttribute('aria-invalid', selected ? 'false' : 'true');
        }
        if (categoriesBox) {
            categoriesBox.classList.toggle('feu-einsatz-section-invalid', !selected);
        }
        if (categoryError) {
            categoryError.hidden = selected;
        }

        return selected ? '' : strings.categoryRequired;
    }

    function validatePrimaryCategory() {
        if (!primaryCategory) {
            return '';
        }
        var selected = Array.prototype.map.call(form.querySelectorAll('input[name="post_category[]"]:checked'), function (checkbox) {
            return checkbox.value;
        });
        var eligible = selected.filter(function (id) {
            return Array.prototype.some.call(primaryCategory.options, function (option) { return option.value === id; });
        });
        var chosen = primaryCategory.value === '0' ? '' : primaryCategory.value;
        var valid = eligible.length > 0 && (chosen ? eligible.indexOf(chosen) !== -1 : eligible.length === 1);
        primaryCategory.classList.toggle('feu-einsatz-field-invalid', !valid);
        primaryCategory.setAttribute('aria-invalid', valid ? 'false' : 'true');
        if (primaryCategoryError) {
            primaryCategoryError.textContent = valid ? '' : strings.primaryCategoryRequired;
            primaryCategoryError.hidden = valid;
        }
        return valid ? '' : strings.primaryCategoryRequired;
    }

    function updateNotice(messages) {
        if (!notice || !noticeList) {
            return;
        }

        noticeList.replaceChildren();
        messages.forEach(function (message) {
            var item = document.createElement('li');
            var link = document.createElement('a');
            link.href = '#' + message.id;
            link.textContent = message.text;
            item.appendChild(link);
            noticeList.appendChild(item);
        });
        notice.hidden = messages.length === 0;
    }

    function validateForm() {
        var messages = [];
        var detailErrors = false;

        fields.forEach(function (configField) {
            var message = validateField(configField);
            if (message) {
                detailErrors = true;
                messages.push({ id: configField.id, text: configField.label + ': ' + message });
            }
        });

        var categoryMessage = validateCategories();
        if (categoryMessage) {
            messages.push({ id: 'feu-einsatz-report-box-categories', text: strings.categoriesLabel + ': ' + categoryMessage });
        }
        var primaryCategoryMessage = validatePrimaryCategory();
        if (primaryCategoryMessage) {
            messages.push({ id: 'feu-einsatz-primary-category', text: strings.primaryCategoryLabel + ': ' + primaryCategoryMessage });
        }

        if (detailsBox) {
            detailsBox.classList.toggle('feu-einsatz-section-invalid', detailErrors);
        }
        updateNotice(messages);
        return messages.length === 0;
    }

    fields.forEach(function (configField) {
        var field = getField(configField.id);
        if (!field) {
            return;
        }

        ['input', 'change', 'blur'].forEach(function (eventName) {
            field.addEventListener(eventName, function () {
                if (notice && !notice.hidden) {
                    validateForm();
                    return;
                }
                validateField(configField);
            });
        });
    });

    Array.prototype.forEach.call(submitButtons, function (button) {
        button.addEventListener('click', function () {
            form.setAttribute('data-feu-submit-intent', button.value || 'draft');
        });
    });

    form.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || event.isComposing || !event.target || event.target.tagName !== 'INPUT') {
            return;
        }
        var inputType = (event.target.type || '').toLowerCase();
        if (['text', 'number', 'date', 'time', 'search'].indexOf(inputType) === -1) {
            return;
        }
        var currentStatus = form.getAttribute('data-feu-current-status');
        var safeStatus = currentStatus === 'publish' || currentStatus === 'future' ? 'publish' : 'draft';
        var safeButton = safeStatus === 'publish'
            ? form.querySelector('[data-feu-review-open]')
            : form.querySelector('button[type="submit"][value="draft"]');
        if (!safeButton) {
            return;
        }
        event.preventDefault();
        if (safeStatus === 'publish') {
            safeButton.click();
        } else if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(safeButton);
        } else {
            safeButton.click();
        }
    });

    Array.prototype.forEach.call(form.querySelectorAll('input[name="post_category[]"]'), function (checkbox) {
        checkbox.addEventListener('change', function () {
            if (notice && !notice.hidden) {
                validateForm();
                return;
            }
            validateCategories();
            validatePrimaryCategory();
        });
    });

    if (primaryCategory) {
        primaryCategory.addEventListener('change', function () {
            if (notice && !notice.hidden) {
                validateForm();
                return;
            }
            validatePrimaryCategory();
        });
    }

    Array.prototype.forEach.call(form.querySelectorAll('input[name="feu_einsatz_map_location_mode"], #feu_einsatz_map_highlight_override'), function (control) {
        control.addEventListener('change', function () {
            if (notice && !notice.hidden) {
                validateForm();
            }
        });
    });

    form.addEventListener('submit', function (event) {
        if (validateForm()) {
            return;
        }

        event.preventDefault();
        form.classList.add('feu-einsatz-form-has-validation-errors');
        if (notice) {
            notice.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
}());
