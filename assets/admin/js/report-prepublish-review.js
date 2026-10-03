(function () {
    'use strict';
    var form = document.querySelector('.feu-einsatz-report-create-form');
    if (!form) return;
    var panel = form.querySelector('[data-feu-prepublish-review]');
    var open = form.querySelector('[data-feu-review-open]');
    if (!panel || !open) return;
    var config = window.feuEinsatzReportUrlReview || {};
    var strings = config.strings || {};
    var urlNode = panel.querySelector('[data-feu-review-url]');
    var statusNode = panel.querySelector('[data-feu-review-url-status]');
    var confirm = panel.querySelector('[data-feu-review-confirm]');
    var requestSerial = 0;
    var reviewTimer = null;
    var lastReviewKey = '';

    function field(id) {
        var input = document.getElementById(id);
        return input ? input.value.trim() : '';
    }
    function setUrlState(state, url) {
        var messages = {
            checking: strings.checking || 'URL wird geprüft …',
            available: strings.available || 'URL ist verfügbar.',
            conflict: strings.conflict || 'Diese URL wird bereits verwendet. Bitte den Titel ändern.',
            missing_category: strings.missingCategory || 'Bitte eine Hauptkategorie wählen.',
            unavailable: strings.unavailable || 'Die URL kann gerade nicht geprüft werden.',
            legacy: strings.legacy || 'Die URL wird beim Speichern festgelegt.'
        };
        urlNode.textContent = url || (state === 'checking' ? messages.checking : '—');
        if (statusNode) {
            statusNode.hidden = state === 'fixed';
            statusNode.textContent = state === 'fixed' ? '' : messages[state] || messages.unavailable;
            statusNode.setAttribute('data-state', state);
        }
        if (confirm) confirm.disabled = state === 'checking' || state === 'conflict' || state === 'missing_category';
    }
    function reviewUrl(force) {
        var primary = form.querySelector('#feu-einsatz-primary-category');
        var selected = Array.prototype.map.call(form.querySelectorAll('input[name="post_category[]"]:checked'), function (item) {
            return item.value;
        });
        var reviewKey = JSON.stringify([
            panel.getAttribute('data-feu-fixed-url'),
            form.getAttribute('data-feu-report-id'),
            field('feu_einsatz_new_report_title'),
            field('feu_einsatz_strasse'),
            primary ? primary.value : '0',
            selected
        ]);
        if (!force && reviewKey === lastReviewKey) return;
        lastReviewKey = reviewKey;
        requestSerial++;
        var serial = requestSerial;
        if (reviewTimer) window.clearTimeout(reviewTimer);
        var fixed = panel.getAttribute('data-feu-fixed-url');
        if (fixed) { setUrlState('fixed', fixed); return; }
        if (!config.ajaxUrl || !config.nonce || typeof window.fetch !== 'function') {
            setUrlState('unavailable', '');
            return;
        }
        setUrlState('checking', '');
        reviewTimer = window.setTimeout(function () {
            var params = new URLSearchParams();
            params.set('action', 'feu_einsatz_review_report_url');
            params.set('nonce', config.nonce);
            params.set('post_id', form.getAttribute('data-feu-report-id') || '0');
            params.set('post_title', field('feu_einsatz_new_report_title'));
            params.set('street', field('feu_einsatz_strasse'));
            params.set('primary_category', primary ? primary.value : '0');
            selected.forEach(function (id) {
                params.append('post_category[]', id);
            });
            window.fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: params })
                .then(function (response) { if (!response.ok) throw new Error('URL review failed'); return response.json(); })
                .then(function (response) {
                    if (serial !== requestSerial) return;
                    if (!response.success || !response.data) throw new Error('URL review failed');
                    setUrlState(response.data.status, response.data.url || '');
                })
                .catch(function () { if (serial === requestSerial) setUrlState('unavailable', ''); });
        }, 250);
    }
    function updateDetails() {
        var mode = form.querySelector('input[name="feu_einsatz_map_location_mode"]:checked');
        var coordinates = mode && mode.value === 'coordinates';
        var address = coordinates
            ? [field('feu_einsatz_latitude'), field('feu_einsatz_longitude')].filter(Boolean).join(', ')
            : [field('feu_einsatz_strasse'), field('feu_einsatz_hausnummer'), field('feu_einsatz_plz'), field('feu_einsatz_stadt')].filter(Boolean).join(' ');
        panel.querySelector('[data-feu-review-address]').textContent = address || 'Einsatzort fehlt';

        var precision = field('feu_einsatz_map_public_precision');
        var mapStatus = form.querySelector('[data-feu-address-map-preview-status]');
        var mapMode = form.querySelector('[data-feu-address-map-preview-mode]');
        panel.querySelector('[data-feu-review-map]').textContent = precision === 'hidden'
            ? 'Öffentliche Karte deaktiviert'
            : [(mapMode ? mapMode.textContent.trim() : ''), (mapStatus ? mapStatus.textContent.trim() : '')].filter(Boolean).join(' · ') || 'Kartenvorschau noch nicht geladen';
    }
    open.addEventListener('click', function () {
        panel.hidden = false;
        updateDetails();
        reviewUrl(true);
        panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var focusTarget = confirm && !confirm.disabled ? confirm : panel.querySelector('h3');
        if (focusTarget) focusTarget.focus({ preventScroll: true });
    });
    function update() {
        if (panel.hidden) return;
        updateDetails();
        reviewUrl();
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    if (typeof MutationObserver !== 'undefined') {
        var mapObserver = new MutationObserver(function () { if (!panel.hidden) updateDetails(); });
        ['[data-feu-address-map-preview-status]', '[data-feu-address-map-preview-mode]'].forEach(function (selector) {
            var target = form.querySelector(selector);
            if (target) mapObserver.observe(target, { childList: true, characterData: true, subtree: true });
        });
    }
}());
