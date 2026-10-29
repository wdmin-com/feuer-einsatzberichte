const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/public/js/public-script.js'), 'utf8');

async function runMapScenario(tileSucceeds, privacyMode) {
    const appended = [];
    const tileEvents = {};
    const listeners = {};
    const error = { hidden: true };
    const fallback = {
        hidden: false,
        querySelector(selector) {
            if (selector === '.feu-einsatz-map-static-preview') { return {}; }
            if (selector === '[data-feu-map-load-error]') { return error; }
            return null;
        }
    };
    const button = {
        disabled: false,
        addEventListener(type, callback) { listeners[type] = callback; }
    };
    const canvas = {};
    const classList = { add() {}, remove() {} };
    const runtime = {
        classList,
        setAttribute() {},
        getAttribute(name) {
            return {
                'data-map-display-mode': 'live',
                'data-map-privacy-mode': privacyMode,
                'data-map-live-available': '1'
            }[name] || '';
        },
        querySelector(selector) {
            if (selector.includes('[data-feu-map-action="activate"]')) { return button; }
            if (selector === '.feu-einsatz-map-runtime-fallback') { return fallback; }
            if (selector === '.feu-einsatz-live-map') { return canvas; }
            if (selector === '.feu-einsatz-map-config') {
                return { textContent: JSON.stringify({ center: [53.5, 10.0], zoom: 15 }) };
            }
            if (selector === '[data-feu-map-load-error]') { return error; }
            return null;
        }
    };
    const documentStub = {
        readyState: 'complete',
        head: { appendChild(element) { appended.push(element); } },
        addEventListener() {},
        querySelector() { return null; },
        querySelectorAll(selector) {
            return selector === '.feu-einsatz-map-runtime' ? [runtime] : [];
        },
        createElement(tagName) {
            return { tagName, setAttribute() {}, remove() {} };
        }
    };
    let removed = 0;
    let viewSet = false;
    const map = {
        setView() { viewSet = true; return this; },
        invalidateSize() {},
        remove() { removed++; }
    };
    const tileLayer = {
        on(name, callback) { tileEvents[name] = callback; return this; },
        addTo() {
            assert.equal(viewSet, true, 'Leaflet must have a view before requesting tiles.');
            return this;
        }
    };
    const windowStub = {
        feu_einsatz_public: {
            leafletScriptUrl: '/plugin/leaflet.js',
            leafletStyleUrl: '/plugin/leaflet.css'
        },
        setTimeout() { return 1; },
        clearTimeout() {}
    };

    vm.runInNewContext(source, { document: documentStub, window: windowStub, Promise, navigator: {} });
    if (privacyMode === 'always') {
        assert.equal(appended.length, 2, 'Direct-load mode must start Leaflet automatically.');
    } else {
        assert.equal(appended.length, 0, 'Consent mode must wait for the visitor.');
        listeners.click();
    }
    assert.equal(appended.length, 2);

    windowStub.L = {
        map() { return map; },
        tileLayer() { return tileLayer; },
        latLngBounds() { return { isValid() { return false; } }; }
    };
    appended[0].onload();
    appended[1].onload();
    await new Promise(setImmediate);

    assert.equal(fallback.hidden, false, 'The cached image must remain visible while tiles load.');
    assert.equal(button.disabled, true, 'The user cannot start a second map while tiles are pending.');

    if (tileSucceeds) {
        tileEvents.tileload();
    }
    tileEvents.load();
    await new Promise(setImmediate);

    assert.equal(fallback.hidden, tileSucceeds);
    assert.equal(button.disabled, false);
    assert.equal(removed, tileSucceeds ? 0 : 1);
    assert.equal(error.hidden, tileSucceeds);
}

Promise.all([
    runMapScenario(true, 'always'),
    runMapScenario(false, 'always'),
    runMapScenario(true, 'consent_hide')
]).then(() => {
    console.log('Cached map remains visible until tiles succeed and survives tile failures.');
}).catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
