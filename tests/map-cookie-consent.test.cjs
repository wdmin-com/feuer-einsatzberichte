const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const adapterSource = fs.readFileSync(path.join(__dirname, '../assets/public/js/map-consent.js'), 'utf8');
const reportSource = fs.readFileSync(path.join(__dirname, '../assets/public/js/public-script.js'), 'utf8');
const areaSource = fs.readFileSync(path.join(__dirname, '../assets/public/js/area-map.js'), 'utf8');
const reportTemplate = fs.readFileSync(path.join(__dirname, '../templates/public/single/map.php'), 'utf8');
const areaTemplate = fs.readFileSync(path.join(__dirname, '../templates/public/area/partials/map.php'), 'utf8');

const documentEvents = {};
const timers = [];
const states = [];
let allowed = true;
let consentListener = null;

const documentStub = {
    addEventListener(name, callback) { documentEvents[name] = callback; },
    querySelector() { return null; }
};
const windowStub = {
    feu_einsatz_map_consent: { required: true },
    setTimeout(callback) { timers.push(callback); return timers.length; },
    clearTimeout() {}
};

vm.runInNewContext(adapterSource, {
    document: documentStub,
    window: windowStub,
    Object,
    Array
});

windowStub.FEUEinsatzMapConsent.onChange((state) => states.push(state));
assert.equal(states.at(-1).allowed, false, 'The OpenStreetMap resource must fail closed before the consent API is ready.');

windowStub.FeuerCookieConsent = {
    allows(resource) {
        assert.equal(resource.category, 'functional');
        assert.equal(resource.service, 'feuer-einsatzberichte');
        return allowed;
    },
    onChange(callback) {
        consentListener = callback;
        callback({ status: 'decided' });
        return () => {};
    }
};

documentEvents['feuer-cookie-analytics:consent-ready']();
while (timers.length) timers.shift()();
assert.equal(states.at(-1).allowed, true, 'A granted functional consent must release the map runtime.');

allowed = false;
consentListener({ status: 'decided' });
assert.equal(states.at(-1).allowed, false, 'Withdrawing consent must immediately close the map resource again.');

assert.match(reportSource, /consent\.allows\(\) !== true/, 'The report map must check consent before Leaflet or tiles are loaded.');
assert.match(reportSource, /showCookieConsentState\(runtime\)/, 'The report map must restore a consent-safe state.');
assert.match(areaSource, /consent\.onChange/, 'The area map must react to live consent changes.');
assert.match(areaSource, /showConsent\(runtime\)/, 'The area map must remain blocked before consent.');
assert.match(reportTemplate, /data-map-consent-required/, 'The report template must declare the consent gate.');
assert.match(reportTemplate, /data-faz-open-preferences="1"/, 'The report map must provide the official preference-center trigger.');
assert.match(areaTemplate, /data-map-consent-required/, 'The area template must declare the consent gate.');
assert.match(areaTemplate, /data-faz-open-preferences="1"/, 'The area map must provide the official preference-center trigger.');

console.log('OpenStreetMap tiles remain blocked until functional consent and are blocked again after withdrawal.');
