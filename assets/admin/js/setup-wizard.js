(function () {
    'use strict';

    var wizard = document.querySelector('[data-feu-setup-wizard]');
    if (!wizard) return;

    var form = wizard.querySelector('form');
    var steps = Array.prototype.slice.call(wizard.querySelectorAll('[data-feu-setup-step]'));
    var progress = Array.prototype.slice.call(wizard.querySelectorAll('[data-feu-setup-progress]'));
    var prev = wizard.querySelector('[data-feu-setup-prev]');
    var next = wizard.querySelector('[data-feu-setup-next]');
    var finish = wizard.querySelector('[data-feu-setup-finish]');
    var checks = Array.prototype.slice.call(wizard.querySelectorAll('.feu-einsatz-setup-wizard-categories input[type="checkbox"]'));
    var count = wizard.querySelector('[data-feu-setup-category-count]');
    var step = 0;

    function updateCount() {
        if (count) count.textContent = checks.filter(function (check) { return check.checked; }).length + ' / ' + checks.length;
    }

    function showStep(index) {
        step = Math.max(0, Math.min(steps.length - 1, index));
        steps.forEach(function (section, position) { section.classList.toggle('is-active', position === step); });
        progress.forEach(function (item, position) {
            item.classList.toggle('is-active', position === step);
            if (position === step) item.setAttribute('aria-current', 'step');
            else item.removeAttribute('aria-current');
        });
        prev.hidden = step === 0;
        next.hidden = step === steps.length - 1;
        finish.hidden = step !== steps.length - 1;
        wizard.querySelector('.feu-einsatz-setup-wizard-card').scrollTop = 0;
    }

    function validateStep(index) {
        if (index === 1 && !checks.some(function (check) { return check.checked; })) {
            count.classList.add('is-error');
            count.textContent = 'Mindestens ein Einsatzstichwort auswählen';
            if (checks[0]) checks[0].focus();
            return false;
        }
        var fields = Array.prototype.slice.call(steps[index].querySelectorAll('input[required], input[pattern]'));
        if (index === 2) {
            fields = [];
            var reportFields = ['feu_einsatz_sample_street', 'feu_einsatz_sample_postcode', 'feu_einsatz_sample_city'];
            var participantFields = ['feu_einsatz_sample_first_name', 'feu_einsatz_sample_last_name'];
            reportFields.concat(participantFields).forEach(function (name) {
                var field = form.querySelector('[name="' + name + '"]');
                if (field) field.required = false;
            });
            var sampleReport = form.querySelector('[name="feu_einsatz_create_sample_report"]');
            var sampleParticipant = form.querySelector('[name="feu_einsatz_create_sample_participant"]');
            if (sampleReport && sampleReport.checked) {
                reportFields.forEach(function (name) {
                    var field = form.querySelector('[name="' + name + '"]');
                    if (field) {
                        field.required = true;
                        fields.push(field);
                    }
                });
            }
            if (sampleParticipant && sampleParticipant.checked) {
                participantFields.forEach(function (name) {
                    var field = form.querySelector('[name="' + name + '"]');
                    if (field) {
                        field.required = true;
                        fields.push(field);
                    }
                });
            }
        }
        for (var i = 0; i < fields.length; i++) {
            if (!fields[i].checkValidity()) {
                fields[i].reportValidity();
                return false;
            }
        }
        return true;
    }

    wizard.dataset.enhanced = '1';
    form.noValidate = true;
    showStep(0);
    updateCount();
    prev.addEventListener('click', function () { showStep(step - 1); });
    next.addEventListener('click', function () { if (validateStep(step)) showStep(step + 1); });
    wizard.querySelector('[data-feu-setup-select-all]').addEventListener('click', function () {
        checks.forEach(function (check) { check.checked = true; });
        count.classList.remove('is-error');
        updateCount();
    });
    wizard.querySelector('[data-feu-setup-select-none]').addEventListener('click', function () {
        checks.forEach(function (check) { check.checked = false; });
        updateCount();
    });
    checks.forEach(function (check) { check.addEventListener('change', function () { count.classList.remove('is-error'); updateCount(); }); });

    form.addEventListener('submit', function (event) {
        if (event.submitter && event.submitter.value === 'later') return;
        if (step < steps.length - 1 && event.submitter && event.submitter.classList.contains('screen-reader-text')) {
            event.preventDefault();
            if (validateStep(step)) showStep(step + 1);
            return;
        }
        for (var i = 0; i < steps.length; i++) {
            showStep(i);
            if (!validateStep(i)) {
                event.preventDefault();
                return;
            }
        }
    });

    var logoId = wizard.querySelector('[data-feu-setup-logo-id]');
    var logoPreview = wizard.querySelector('[data-feu-setup-logo-preview]');
    wizard.querySelector('[data-feu-setup-logo-select]').addEventListener('click', function () {
        if (!window.wp || !window.wp.media) return;
        var frame = window.wp.media({ title: 'Logo des Feuerwehrhauses', button: { text: 'Logo verwenden' }, library: { type: 'image' }, multiple: false });
        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            logoId.value = attachment.id;
            logoPreview.src = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
        });
        frame.open();
    });
    wizard.querySelector('[data-feu-setup-logo-remove]').addEventListener('click', function () {
        logoId.value = '0';
        logoPreview.src = window.feuSetupWizardDefaultLogo || '';
    });
}());
