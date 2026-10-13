(function () {
    'use strict';

    var dialog = document.querySelector('[data-feu-map-dialog]');
    if (!dialog) {
        return;
    }

    var image = dialog.querySelector('[data-feu-map-dialog-image]');
    var title = dialog.querySelector('[data-feu-map-dialog-title]');
    var loading = dialog.querySelector('[data-feu-map-dialog-loading]');
    var error = dialog.querySelector('[data-feu-map-dialog-error]');

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-feu-map-dialog-open]');
        if (trigger) {
            var url = trigger.getAttribute('data-map-url') || '';
            if (!url) {
                return;
            }
            title.textContent = trigger.getAttribute('data-map-title') || '';
            loading.hidden = false;
            error.hidden = true;
            image.hidden = true;
            image.src = url;
            dialog.showModal();
            return;
        }

        if (event.target.closest('[data-feu-map-dialog-close]')) {
            dialog.close();
        }
    });

    image.addEventListener('load', function () {
        loading.hidden = true;
        image.hidden = false;
    });
    image.addEventListener('error', function () {
        loading.hidden = true;
        image.hidden = true;
        error.hidden = false;
    });
    dialog.addEventListener('close', function () {
        image.removeAttribute('src');
    });

    document.querySelectorAll('.feu-report-list-image img').forEach(function (thumbnail) {
        thumbnail.addEventListener('error', function () {
            var row = thumbnail.closest('.feu-report-list-item');
            var previewButton = thumbnail.closest('button');
            var empty = document.createElement('span');
            empty.className = 'feu-report-list-image-empty';
            empty.textContent = 'Bild nicht erreichbar';
            previewButton.replaceWith(empty);
            row.querySelectorAll('.feu-report-list-actions [data-feu-map-dialog-open]').forEach(function (button) {
                button.remove();
            });
            var badge = row.querySelector('.feu-report-list-health-badge');
            if (badge) {
                badge.textContent = 'Bild nicht erreichbar';
                badge.className = 'feu-report-list-health-badge feu-report-list-health-badge--missing';
            }
            var message = row.querySelector('.feu-report-list-health span:nth-child(2)');
            if (message) {
                message.textContent = 'Die öffentliche Bildadresse liefert keine darstellbare Datei. Bitte neu generieren.';
            }
        });
    });
}());
