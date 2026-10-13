const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const listeners = {};
const appended = [];
const button = {
    disabled: false,
    addEventListener(type, callback) { listeners[type] = callback; }
};
const runtime = {
    setAttribute() {},
    getAttribute(name) {
        return { 'data-map-display-mode': 'live', 'data-map-live-available': '1' }[name] || '';
    },
    querySelector(selector) {
        return selector.includes('[data-feu-map-action="activate"]') ? button : null;
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
        return { tagName, setAttribute() {} };
    }
};
const windowStub = {
    feu_einsatz_public: {
        leafletScriptUrl: '/plugin/leaflet.js',
        leafletStyleUrl: '/plugin/leaflet.css'
    }
};
const source = fs.readFileSync(path.join(__dirname, '../assets/public/js/public-script.js'), 'utf8');
vm.runInNewContext(source, { document: documentStub, window: windowStub, Promise, navigator: {} });

assert.equal(appended.length, 0, 'The page must not request Leaflet or map tiles on initial render.');
assert.equal(typeof listeners.click, 'function', 'The cached map must offer an interactive map action.');

listeners.click();
assert.deepEqual(appended.map((element) => element.tagName), ['link', 'script']);
assert.equal(appended[0].href, '/plugin/leaflet.css');
assert.equal(appended[1].src, '/plugin/leaflet.js');
assert.equal(button.disabled, true);

console.log('Public report map loads local Leaflet assets only after user activation.');
