(function () {
    'use strict';

    var listeners = [];
    var unsubscribe = null;
    var started = false;
    var readyTimer = null;
    var attempts = 0;
    var MAX_ATTEMPTS = 80;
    var RESOURCE = Object.freeze({
        category: 'functional',
        service: 'feuer-einsatzberichte'
    });

    function getConfig() {
        return window.feu_einsatz_map_consent || {};
    }

    function isRequired() {
        return getConfig().required === true || getConfig().required === '1' || getConfig().required === 1;
    }

    function getFcaApi() {
        var api = window.FCAConsent;

        if (!api || typeof api.allows !== 'function') {
            return null;
        }

        return api;
    }

    function getLegacyDecision() {
        if (typeof window.getFazConsent !== 'function') {
            var initialAllowed = getConfig().initialAllowed;
            return initialAllowed === true || initialAllowed === '1' || initialAllowed === 1
                ? true
                : (initialAllowed === false || initialAllowed === '0' || initialAllowed === 0 ? false : null);
        }

        try {
            var consent = window.getFazConsent();
            var services = consent && consent.services ? consent.services : {};
            var categories = consent && consent.categories ? consent.categories : {};

            if (Object.prototype.hasOwnProperty.call(services, RESOURCE.service)) {
                return services[RESOURCE.service] === true;
            }

            if (Object.prototype.hasOwnProperty.call(categories, RESOURCE.category)) {
                return categories[RESOURCE.category] === true;
            }
        } catch (error) {
            return false;
        }

        var fallbackAllowed = getConfig().initialAllowed;
        return fallbackAllowed === true || fallbackAllowed === '1' || fallbackAllowed === 1
            ? true
            : (fallbackAllowed === false || fallbackAllowed === '0' || fallbackAllowed === 0 ? false : null);
    }

    function allows() {
        if (!isRequired()) {
            return true;
        }

        var api = getFcaApi();

        if (api) {
            try {
                return api.allows(RESOURCE) === true;
            } catch (error) {
                return false;
            }
        }

        var legacyDecision = getLegacyDecision();

        return legacyDecision === true;
    }

    function snapshot() {
        return Object.freeze({
            required: isRequired(),
            ready: !isRequired() || !!getFcaApi() || getLegacyDecision() !== null,
            allowed: allows(),
            resource: RESOURCE
        });
    }

    function notify() {
        var state = snapshot();

        listeners.slice().forEach(function (listener) {
            try {
                listener(state);
            } catch (error) {
                // One integration consumer must not block the others.
            }
        });
    }

    function bindFcaApi() {
        var api = getFcaApi();

        if (!api || unsubscribe) {
            return false;
        }

        if (typeof api.onChange === 'function') {
            unsubscribe = api.onChange(function () {
                notify();
            });
        } else {
            unsubscribe = function () {};
            notify();
        }
        return true;
    }

    function waitForApi() {
        if (bindFcaApi() || !isRequired()) {
            window.clearTimeout(readyTimer);
            readyTimer = null;
            return;
        }

        attempts++;
        if (attempts >= MAX_ATTEMPTS) {
            notify();
            return;
        }

        readyTimer = window.setTimeout(waitForApi, 100);
    }

    function start() {
        if (started) {
            return;
        }

        started = true;
        document.addEventListener('fca:consent-ready', function () {
            window.setTimeout(waitForApi, 0);
        });
        document.addEventListener('fca:consent-change', notify);
        document.addEventListener('fazcookie_consent_ready', notify);
        document.addEventListener('fazcookie_consent_update', notify);
        waitForApi();
    }

    function onChange(callback) {
        if (typeof callback !== 'function') {
            return function () {};
        }

        listeners.push(callback);
        callback(snapshot());
        start();

        return function () {
            listeners = listeners.filter(function (listener) {
                return listener !== callback;
            });
        };
    }

    function openPreferences() {
        var trigger = document.querySelector('[data-faz-open-preferences], [data-feuer-cookie-open]');

        if (trigger) {
            trigger.click();
            return true;
        }

        if (typeof window.revisitFazConsent === 'function') {
            window.revisitFazConsent();
            return true;
        }

        return false;
    }

    window.FEUEinsatzMapConsent = Object.freeze({
        allows: allows,
        isRequired: isRequired,
        onChange: onChange,
        openPreferences: openPreferences,
        resource: RESOURCE,
        snapshot: snapshot
    });

    start();
})();
