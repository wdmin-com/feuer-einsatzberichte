const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('Mannschaft synchronization has a read-only preview and isolates ambiguous names', () => {
    const integration = read('includes/class-mannschaft-integration.php');
    const template = read('templates/admin/participants.php');
    assert.match(integration, /function preview_sync\(\): array/);
    assert.match(integration, /'review' => 0/);
    assert.match(integration, /in_array\(\$member_id, \$review_ids, true\)/);
    assert.match(template, /Synchronisierungsvorschau/);
    assert.match(template, /Zuordnung bestätigen/);
});

test('daily image audit validates files and is visible in the existing operations center', () => {
    const service = read('includes/class-maintenance-service.php');
    const operations = read('templates/admin/operations-center.php');
    assert.match(service, /feu_einsatz_daily_image_integrity/);
    assert.match(service, /getimagesize/);
    assert.match(service, /inspect_share_card_integrity/);
    assert.match(operations, /Bildintegrität/);
    assert.match(operations, /feu_einsatz_run_image_integrity/);
});

test('published reports queue an asynchronous cache warm-up', () => {
    const service = read('includes/class-maintenance-service.php');
    assert.match(service, /add_action\('save_post'.*queue_report_cache_warm/);
    assert.match(service, /added_post_meta'.*queue_report_cache_warm_for_marker/);
    assert.match(service, /wp_schedule_single_event/);
    assert.match(service, /get_single_context/);
    assert.match(service, /get_overview_context/);
    assert.match(service, /queue_share_card_generation/);
});

test('weekday statistics use a professional label in dashboard, presentation and PDF', () => {
    const statistics = read('templates/admin/statistics.php');
    assert.doesNotMatch(statistics, /Beliebte Wochentage|Häufigste Wochentage|Top Wochentage/);
    assert.ok((statistics.match(/Einsätze nach Wochentag/g) || []).length >= 5);
});
