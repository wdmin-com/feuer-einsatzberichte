(function () {
    'use strict';

    var form = document.querySelector('.feu-einsatz-report-create-form');
    if (!form) {
        return;
    }

    var titleField = document.getElementById('feu_einsatz_new_report_title');
    var contentField = document.getElementById('feu_einsatz_new_report_content');
    var titleReset = form.querySelector('[data-feu-autofill-title-reset]');
    var contentReset = form.querySelector('[data-feu-autofill-content-reset]');
    if (!titleField || !contentField) {
        return;
    }

    var titleWasEdited = titleField.value.trim() !== '';
    var contentWasEdited = contentField.value.replace(/<[^>]*>/g, '').replace(/&nbsp;|&#160;/gi, '').trim() !== '';
    var lastTitle = '';
    var lastContent = '';
    var writing = false;

    function value(id) {
        var field = document.getElementById(id);
        return field ? field.value.trim().replace(/\s+/g, ' ') : '';
    }

    function streetName(raw) {
        return raw.replace(/\s*,.*$/u, '').replace(/\s+\d+[\p{L}]?(?:\s*[-/]\s*\d+[\p{L}]?)?.*$/u, '').trim();
    }

    function primaryCategory() {
        var chosen = form.querySelectorAll('input[name="post_category[]"]:checked');
        var primary = null;
        var depth = -1;
        Array.prototype.forEach.call(chosen, function (checkbox) {
            var candidateDepth = Number(checkbox.getAttribute('data-feu-category-depth') || 0);
            if (candidateDepth > depth) {
                primary = checkbox;
                depth = candidateDepth;
            }
        });
        return primary;
    }

    function formattedDate(raw) {
        var parts;
        var date;
        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
            parts = raw.split('-');
            date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
            if (date.getFullYear() !== Number(parts[0]) || date.getMonth() !== Number(parts[1]) - 1 || date.getDate() !== Number(parts[2])) {
                return '';
            }
            return parts[2] + '.' + parts[1] + '.' + parts[0];
        }
        if (/^\d{2}\.\d{2}\.\d{4}$/.test(raw)) {
            parts = raw.split('.');
            date = new Date(Number(parts[2]), Number(parts[1]) - 1, Number(parts[0]));
            return date.getFullYear() === Number(parts[2]) && date.getMonth() === Number(parts[1]) - 1 && date.getDate() === Number(parts[0]) ? raw : '';
        }
        return '';
    }

    function suggestions() {
        var category = primaryCategory();
        var street = streetName(value('feu_einsatz_strasse'));
        var city = value('feu_einsatz_stadt');
        var district = value('feu_einsatz_stadtteil');
        var date = formattedDate(value('feu_einsatz_datum'));
        var name = category ? (category.getAttribute('data-feu-category-name') || '').trim() : '';
        var description = category ? (category.getAttribute('data-feu-category-description') || '').trim().replace(/[.\s]+$/u, '') : '';
        var title = name ? name + (street ? ' - ' + street : '') : (street ? 'Einsatzbericht - ' + street : '');
        var intro = name + (description ? ' - ' + description : '');
        var location = city + (district ? ' ' + district : '');
        var content = name && street && city && date
            ? intro + ' auf der ' + street + ' in ' + location + ' am ' + date + '.'
            : '';
        return { title: title, content: content };
    }

    function tinyEditor() {
        return window.tinymce && window.tinymce.get ? window.tinymce.get(contentField.id) : null;
    }

    function contentText() {
        var editor = tinyEditor();
        if (editor && editor.initialized && !editor.isHidden()) {
            return editor.getContent({ format: 'text' }).trim();
        }
        return contentField.value.replace(/<[^>]*>/g, '').trim();
    }

    function putContent(text) {
        var editor = tinyEditor();
        writing = true;
        contentField.value = text;
        if (editor && editor.initialized) {
            var escaped = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            editor.setContent(escaped ? '<p>' + escaped + '</p>' : '');
        }
        lastContent = text;
        writing = false;
    }

    function refresh(forceTitle, forceContent) {
        var next = suggestions();
        if (titleReset) {
            titleReset.disabled = !next.title;
        }
        if (contentReset) {
            contentReset.disabled = !next.content;
        }
        if (forceTitle) {
            titleWasEdited = false;
        }
        if (forceContent) {
            contentWasEdited = false;
        }
        if (!titleWasEdited) {
            titleField.value = next.title;
            lastTitle = next.title;
        }
        if (!contentWasEdited) {
            putContent(next.content);
        }
    }

    titleField.addEventListener('input', function () {
        if (!writing) {
            titleWasEdited = titleField.value.trim() !== lastTitle;
        }
    });
    contentField.addEventListener('input', function () {
        if (!writing) {
            contentWasEdited = contentText() !== lastContent;
        }
    });

    function bindTinyEditor(editor) {
        if (!editor || editor.id !== contentField.id || editor.feuAutofillBound) {
            return;
        }
        editor.feuAutofillBound = true;
        editor.on('input change keyup', function () {
            if (!writing) {
                contentWasEdited = contentText() !== lastContent;
            }
        });
    }

    if (window.tinymce) {
        bindTinyEditor(tinyEditor());
        window.tinymce.on('AddEditor', function (event) {
            bindTinyEditor(event.editor);
        });
    }
    function bindLateEditor() {
        var editor = tinyEditor();
        bindTinyEditor(editor);
        if (editor && editor.initialized && !contentWasEdited && lastContent && contentText() === '') {
            putContent(lastContent);
        }
    }
    if (document.readyState === 'complete') {
        bindLateEditor();
    } else {
        window.addEventListener('load', bindLateEditor);
    }

    ['feu_einsatz_strasse', 'feu_einsatz_stadt', 'feu_einsatz_stadtteil', 'feu_einsatz_datum'].forEach(function (id) {
        var field = document.getElementById(id);
        if (field) {
            field.addEventListener('input', function () { refresh(false, false); });
            field.addEventListener('change', function () { refresh(false, false); });
        }
    });
    Array.prototype.forEach.call(form.querySelectorAll('input[name="post_category[]"]'), function (field) {
        field.addEventListener('change', function () { refresh(false, false); });
    });
    if (titleReset) {
        titleReset.addEventListener('click', function () { refresh(true, false); });
    }
    if (contentReset) {
        contentReset.addEventListener('click', function () { refresh(false, true); });
    }
    refresh(false, false);
}());
