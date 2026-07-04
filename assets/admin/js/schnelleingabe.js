(function () {
    'use strict';

    function initQuickEntryForm(form) {
        var errorsBox = form.querySelector('[data-feu-errors]');
        var errorsList = form.querySelector('[data-feu-errors-list]');
        var participantSearch = form.querySelector('[data-feu-participant-search]');
        var participantList = form.querySelector('[data-feu-participant-list]');
        var participantCount = form.querySelector('[data-feu-participant-count]');
        var photoInput = form.querySelector('[data-feu-photo-input]');
        var photoPreview = form.querySelector('[data-feu-photo-preview]');
        var photoGrid = form.querySelector('[data-feu-photo-grid]');
        var photoCount = form.querySelector('[data-feu-photo-count]');
        var submitText = form.querySelector('[data-feu-submit-text]');
        var submitSpinner = form.querySelector('[data-feu-submit-spinner]');
        var pendingFiles = [];

        function clearErrors() {
            if (!errorsBox || !errorsList) {
                return;
            }

            errorsList.innerHTML = '';
            errorsBox.hidden = true;
        }

        function showErrors(errors) {
            if (!errorsBox || !errorsList) {
                return;
            }

            errorsList.innerHTML = '';

            errors.forEach(function (message) {
                var item = document.createElement('li');
                item.textContent = message;
                errorsList.appendChild(item);
            });

            errorsBox.hidden = false;
            errorsBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function validateForm() {
            var errors = [];
            var dateInput = form.querySelector('[name="feu_einsatz_datum"]');
            var timeInput = form.querySelector('[name="feu_einsatz_uhrzeit"]');
            var streetInput = form.querySelector('[name="feu_einsatz_strasse"]');
            var postalCodeInput = form.querySelector('[name="feu_einsatz_plz"]');
            var cityInput = form.querySelector('[name="feu_einsatz_stadt"]');

            var dateValue = dateInput ? dateInput.value.trim() : '';
            var timeValue = timeInput ? timeInput.value.trim() : '';
            var streetValue = streetInput ? streetInput.value.trim() : '';
            var postalCodeValue = postalCodeInput ? postalCodeInput.value.trim() : '';
            var cityValue = cityInput ? cityInput.value.trim() : '';

            if (!/^\d{2}\.\d{2}\.\d{4}$/.test(dateValue)) {
                errors.push('Datum fehlt oder hat nicht das Format TT.MM.JJJJ.');
            }

            if (!/^\d{2}:\d{2}$/.test(timeValue)) {
                errors.push('Uhrzeit fehlt oder hat nicht das Format HH:MM.');
            }

            if (!streetValue) {
                errors.push('Strasse ist ein Pflichtfeld.');
            }

            if (!/^\d{5}$/.test(postalCodeValue)) {
                errors.push('PLZ ist ein Pflichtfeld und muss aus fuenf Ziffern bestehen.');
            }

            if (!cityValue) {
                errors.push('Stadt ist ein Pflichtfeld.');
            }

            if (!form.querySelector('input[name="post_category[]"]:checked')) {
                errors.push('Mindestens eine Einsatzart muss gewaehlt werden.');
            }

            return errors;
        }

        function updateParticipantCount() {
            if (!participantCount || !participantList) {
                return;
            }

            var checkedCount = participantList.querySelectorAll('.feu-se-participant-check:checked').length;
            participantCount.textContent = checkedCount > 0 ? String(checkedCount) : '';
        }

        function updatePhotoCount() {
            if (!photoPreview || !photoCount) {
                return;
            }

            var activeCount = pendingFiles.filter(Boolean).length;
            photoPreview.hidden = activeCount === 0;

            if (activeCount === 0) {
                photoCount.textContent = '';
                return;
            }

            photoCount.textContent = activeCount === 1
                ? '1 Foto ausgewaehlt'
                : activeCount + ' Fotos ausgewaehlt';
        }

        function appendPhotoPreview(file, index) {
            if (!photoGrid) {
                return;
            }

            var thumb = document.createElement('div');
            thumb.className = 'feu-se-photo-thumb';
            thumb.dataset.index = String(index);

            var image = document.createElement('img');
            image.className = 'feu-se-photo-img';
            image.alt = file.name;
            thumb.appendChild(image);

            var removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'feu-se-photo-remove';
            removeButton.setAttribute('aria-label', 'Foto entfernen');
            removeButton.textContent = '×';
            removeButton.addEventListener('click', function () {
                pendingFiles[index] = null;
                thumb.remove();
                updatePhotoCount();
            });
            thumb.appendChild(removeButton);

            photoGrid.appendChild(thumb);

            var reader = new FileReader();
            reader.onload = function (event) {
                image.src = event.target.result;
            };
            reader.readAsDataURL(file);
        }

        if (participantSearch && participantList) {
            participantSearch.addEventListener('input', function () {
                var query = participantSearch.value.toLowerCase().trim();
                var visible = 0;
                var emptyHint = participantList.querySelector('.feu-se-empty');

                participantList.querySelectorAll('.feu-se-participant').forEach(function (item) {
                    var haystack = (item.dataset.name || '').toLowerCase();
                    var match = !query || haystack.indexOf(query) !== -1;
                    item.hidden = !match;
                    if (match) {
                        visible++;
                    }
                });

                if (visible === 0 && query) {
                    if (!emptyHint) {
                        emptyHint = document.createElement('p');
                        emptyHint.className = 'feu-se-empty';
                        emptyHint.textContent = 'Keine Teilnehmer gefunden.';
                        participantList.appendChild(emptyHint);
                    }

                    emptyHint.hidden = false;
                } else if (emptyHint) {
                    emptyHint.hidden = true;
                }
            });
        }

        if (participantList) {
            participantList.addEventListener('change', function (event) {
                var checkbox = event.target;

                if (!checkbox.classList.contains('feu-se-participant-check')) {
                    return;
                }

                var item = checkbox.closest('.feu-se-participant');
                var functionWrap = item ? item.querySelector('.feu-se-participant-func') : null;

                if (item) {
                    item.classList.toggle('is-selected', checkbox.checked);
                }

                if (functionWrap) {
                    functionWrap.hidden = !checkbox.checked;
                }

                updateParticipantCount();
            });
        }

        var dateInput = form.querySelector('[name="feu_einsatz_datum"]');
        if (dateInput) {
            dateInput.addEventListener('input', function () {
                var sanitized = dateInput.value.replace(/[^\d.]/g, '');
                var previousLength = parseInt(dateInput.dataset.lastLength || '0', 10);

                if (/^\d{2}$/.test(sanitized) && previousLength < sanitized.length) {
                    sanitized += '.';
                } else if (/^\d{2}\.\d{2}$/.test(sanitized) && previousLength < sanitized.length) {
                    sanitized += '.';
                }

                dateInput.dataset.lastLength = String(sanitized.length);
                dateInput.value = sanitized;
            });
        }

        if (photoInput && photoGrid) {
            photoInput.addEventListener('change', function () {
                Array.from(photoInput.files || []).forEach(function (file) {
                    if (!file.type || file.type.indexOf('image/') !== 0) {
                        return;
                    }

                    pendingFiles.push(file);
                    appendPhotoPreview(file, pendingFiles.length - 1);
                });

                photoInput.value = '';
                updatePhotoCount();
            });
        }

        form.addEventListener('submit', function (event) {
            clearErrors();

            var errors = validateForm();
            if (errors.length > 0) {
                event.preventDefault();
                showErrors(errors);
                return;
            }

            var submitter = event.submitter;
            if (submitter && submitText) {
                submitText.textContent = submitter.value === 'draft'
                    ? 'Wird als Entwurf gespeichert...'
                    : 'Wird gespeichert...';
            }

            if (submitter) {
                submitter.disabled = true;
            }

            if (submitSpinner) {
                submitSpinner.hidden = false;
            }
        });

        updateParticipantCount();
        updatePhotoCount();
    }

    function init() {
        document.querySelectorAll('[data-feu-schnelleingabe-form]').forEach(initQuickEntryForm);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());

