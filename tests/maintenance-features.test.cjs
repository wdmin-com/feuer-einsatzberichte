const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('Mannschaft synchronization has a read-only preview and isolates ambiguous names', () => {
    const integration = read('includes/class-mannschaft-integration.php');
    const database = read('includes/class-database.php');
    const admin = read('includes/class-admin.php');
    const template = read('templates/admin/participants.php');
    assert.match(integration, /function preview_sync\(\): array/);
    assert.match(integration, /'review' => 0/);
    assert.match(integration, /in_array\(\$member_id, \$review_ids, true\)/);
    assert.match(integration, /function disconnect_participant\(int \$participant_id\): bool/);
    assert.match(integration, /IGNORED_PROFILES_OPTION/);
    assert.match(database, /function disconnect_external_participant\(/);
    assert.match(admin, /admin_post_feu_einsatz_disconnect_mannschaft_profile/);
    assert.match(template, /<details class="feu-einsatz-mannschaft-sync"/);
    assert.match(template, /Verbindung lösen/);
    assert.match(template, /Wieder zulassen/);
    assert.match(template, /Synchronisierungsvorschau/);
    assert.match(template, /Zuordnung bestätigen/);
});

test('daily image audit validates files and is visible in the existing operations center', () => {
    const service = read('includes/class-maintenance-service.php');
    const operations = read('templates/admin/operations-center.php');
    assert.match(service, /feu_einsatz_daily_image_integrity/);
    assert.match(service, /getimagesize/);
    assert.match(service, /inspect_share_card_integrity/);
    assert.match(service, /get_generated_map_admin_status/);
    assert.match(service, /privacy_hidden/);
    assert.match(service, /pending_count/);
    assert.match(service, /issue_counts/);
    assert.match(service, /thumbnail_fallback_count/);
    assert.match(service, /!empty\(\$map_status\['has_thumbnail_fallback'\]\)/);
    assert.match(service, /'schema_version'\s*=>\s*2/);
    assert.match(service, /\['schema_version'\]\s*\?\?\s*0/);
    assert.match(operations, /Bildintegrität/);
    assert.match(operations, /gesicherte Miniatur/);
    assert.match(operations, /Keine tatsächlichen Probleme/);
    assert.match(operations, /feu_einsatz_run_image_integrity/);
});

test('map status distinguishes a retained thumbnail from a failed full-size refresh', () => {
    const admin = read('includes/class-admin.php');
    const template = read('templates/admin/meta-box-details.php');
    const styles = read('assets/admin/css/admin-style.css');
    assert.match(admin, /function has_generated_map_thumbnail_fallback\(/);
    assert.match(admin, /Ein vorhandenes Kartenbild bleibt sichtbar/);
    assert.match(admin, /'has_thumbnail_fallback' => \$has_thumbnail_fallback/);
    assert.match(admin, /\$visual_status = 'error' === \$status && \$has_thumbnail_fallback \? 'warning' : \$status/);
    assert.match(template, /dashicons-warning/);
    assert.match(styles, /map-generation-status--warning/);
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

test('statistics presentation shows weekday and peak day summaries only on the calendar slide', () => {
    const statistics = read('templates/admin/statistics.php');
    const start = statistics.indexOf('function feu_einsatz_render_statistics_presentation');
    const end = statistics.indexOf('<div class="wrap feu-einsatz-statistics', start);
    assert.ok(start >= 0 && end > start, 'The presentation renderer must be present.');
    const presentation = statistics.slice(start, end);
    assert.equal((presentation.match(/Einsätze nach Wochentag/g) || []).length, 1);
    assert.equal((presentation.match(/Stärkste Tage/g) || []).length, 1);
    assert.doesNotMatch(presentation, /Spitzen-Tage/);
});
