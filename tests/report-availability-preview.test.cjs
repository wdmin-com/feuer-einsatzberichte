const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../assets/admin/js/admin-script.js'), 'utf8');
const moduleStub = { exports: {} };
const jQueryStub = () => ({ ready() {} });
vm.runInNewContext(source, { module: moduleStub, jQuery: jQueryStub, document: {}, Date });

const preview = moduleStub.exports.calculateAvailabilityPreview;
const now = new Date(2026, 9, 4, 10, 0);

assert.equal(preview('sofort', '', '', now).kind, 'immediate');
assert.equal(preview('date', '', '08:00', now).kind, 'missing');
assert.equal(preview('date', '31.02.2026', '08:00', now).kind, 'missing');
assert.equal(preview('date', '05.10.2026', '25:00', now).kind, 'missing');
assert.equal(preview('date', '01.10.2026', '08:00', now).kind, 'immediate');

const eventRelease = preview('date', '05.10.2026', '12:30', now);
assert.equal(eventRelease.kind, 'scheduled');
assert.equal(eventRelease.date, '05.10.2026');
assert.equal(eventRelease.time, '12:30');

const delayedRelease = preview('plus2', '05.10.2026', '12:30', now);
assert.equal(delayedRelease.kind, 'scheduled');
assert.equal(delayedRelease.date, '07.10.2026');
assert.equal(delayedRelease.time, '12:30');

const oldEventRelease = preview('plus2', '01.10.2026', '08:00', now);
assert.equal(oldEventRelease.kind, 'scheduled');
assert.equal(oldEventRelease.date, '06.10.2026');
assert.equal(oldEventRelease.time, '10:00');

console.log('Publication preview follows immediate, event-date and 48-hour scheduling rules.');
