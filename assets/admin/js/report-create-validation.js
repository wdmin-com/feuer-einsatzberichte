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
    var submitButtons = form.querySelectorAll('button[type="submit"]');
    var fields = [
        { id: 'feu_einsatz_strasse', label: strings.street, message: strings.streetRequired, type: 'required' },
        { id: 'feu_einsatz_plz', label: strings.postcode, message: strings.postcodeInvalid, type: 'postcode' },
        { id: 'feu_einsatz_stadt', label: strings.city, message: strings.cityRequired, type: 'required' },
        { id: 'feu_einsatz_datum', label: strings.date, message: strings.dateInvalid, type: 'date' },
        { id: 'feu_einsatz_uhrzeit', label: strings.time, message: strings.timeInvalid, type: 'time' }
    ];

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
        (field.closest('.feu-einsatz-form-row') || field.parentNode).appendChild(node);
        return node;
    }

    function validateField(configField) {
        var field = getField(configField.id);

        if (!field) {
            return '';
        }

        var errorNode = ensureFieldErrorNode(field);
        var valid = validateValue({ value: field.value, type: configField.type });

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

    function updateNotice(messages) {
        if (!notice || !noticeList) {
            return;
        }

        noticeList.replaceChildren();
        messages.forEach(function (message) {
            var item = document.createElement('li');
            item.textContent = message;
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
                messages.push(configField.label + ': ' + message);
            }
        });

        var categoryMessage = validateCategories();
        if (categoryMessage) {
            messages.push(strings.categoriesLabel + ': ' + categoryMessage);
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

    Array.prototype.forEach.call(form.querySelectorAll('input[name="post_category[]"]'), function (checkbox) {
        checkbox.addEventListener('change', function () {
            if (notice && !notice.hidden) {
                validateForm();
                return;
            }
            validateCategories();
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
