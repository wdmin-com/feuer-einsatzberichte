const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('statistics distinguish the active roster total from annual metrics', () => {
    const database = read('includes/class-database.php');
    const statistics = read('templates/admin/statistics.php');

    assert.match(database, /STATISTICS_DASHBOARD_CACHE_PREFIX \. 'v3_'/);
    assert.match(database, /COUNT\(DISTINCT s\.teilnehmer_id\)/);
    assert.match(database, /WHERE is_deleted = 0 AND is_archived = 0/);
    assert.match(statistics, /Mitglieder gesamt/);
    assert.match(statistics, /Aktiver Mannschaftsbestand/);
    assert.match(statistics, /total_teilnehmer_alle/);
    assert.match(statistics, /Ausgewähltes Jahr: %d/);
    assert.match(statistics, /Einsatztage/);
    assert.doesNotMatch(statistics, /Eingesetzte Mitglieder/);
});
