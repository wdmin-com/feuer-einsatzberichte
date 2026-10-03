(function () {
    'use strict';

    var input = document.getElementById('feu_einsatz_archive_file');
    var label = document.querySelector('[data-feu-archive-file-name]');
    if (!input || !label) {
        return;
    }

    input.addEventListener('change', function () {
        label.textContent = input.files && input.files.length ? input.files[0].name : label.getAttribute('data-empty-label');
    });
}());
