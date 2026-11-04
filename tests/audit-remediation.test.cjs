const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('long administration lists use compact pagination or disclosure', () => {
    const logs = read('templates/admin/logs.php');
    const participants = read('templates/admin/participants.php');
    const operations = read('templates/admin/operations-center.php');

    assert.match(logs, /\$per_page = .*: 20/);
    assert.match(logs, /feu-einsatz-log-per-page/);
    assert.match(participants, /participantPageSize = 20/);
    assert.match(participants, /data-feu-participant-page-next/);
    assert.match(operations, /array_slice\(\$background_tasks, 0, 6\)/);
    assert.match(operations, /feu-einsatz-operations-more-tasks/);
});

test('editor navigation and statistics states are explicit', () => {
    const editor = read('templates/admin/report-create.php');
    const details = read('templates/admin/meta-box-details.php');
    const statistics = read('templates/admin/statistics.php');

    assert.match(editor, /href="#feu-einsatz-live-map-preview"/);
    assert.match(details, /id="feu-einsatz-live-map-preview"/);
    assert.match(statistics, /title: 'Einsatzort: '/);
    assert.match(statistics, /previousButton\.disabled = index === 0/);
    assert.match(statistics, /nextButton\.disabled = index === slides\.length - 1/);
    assert.doesNotMatch(statistics, /target \* eased/);
});

test('plugin SEO is emitted before generic theme metadata and normalizes breadcrumbs', () => {
    const publicController = read('includes/class-public.php');
    const themeTemplate = read('templates/public/custom/custom_single_feuer-theme.php');
    const reportInfo = read('templates/public/single/info.php');
    const publicCss = read('assets/public/css/public-style.css');

    assert.match(publicController, /render_social_share_meta_tags'\], -9999/);
    assert.match(publicController, /html_entity_decode\(wp_strip_all_tags/);
    assert.doesNotMatch(themeTemplate, /<h1>/);
    assert.match(themeTemplate, /<h2>/);
    assert.doesNotMatch(reportInfo, /<h1/);
    assert.match(reportInfo, /<h2 class="feu-einsatz-single-heading"/);
    assert.match(publicCss, /\.feu-einsatz-template-header h1,\s*\.feu-einsatz-template-header h2/);
});

test('statistics use grammatically correct singular and plural labels', () => {
    const statistics = read('templates/admin/statistics.php');

    assert.match(statistics, /_n\('mit %d Einsatz', 'mit %d Einsätzen'/);
    assert.doesNotMatch(statistics, /mit %d Einsätze['"]/);
    assert.doesNotMatch(statistics, /\+ ' Einsätze'/);
});

test('mobile statistics and settings navigation remain horizontal', () => {
    const css = read('assets/admin/css/apple-admin-system.css');
    const workspaceCss = read('assets/admin/css/apple-workspace.css');

    assert.match(css, /\.feu-einsatz-stats-tabs[\s\S]*?flex-wrap:\s*nowrap/);
    assert.match(css, /\.feu-admin-settings-nav[\s\S]*?overflow-x:\s*auto/);
    assert.match(workspaceCss, /#tab-funktionen\s*\{[\s\S]*?max-width:\s*none/);
});

test('dashboard quick entry keeps large keyword catalogs compact', () => {
    const widget = read('templates/admin/schnelleingabe-widget.php');
    const css = read('assets/admin/css/schnelleingabe.css');
    const adminController = read('includes/class-admin.php');

    assert.match(widget, /\$primary_category_limit = 8/);
    assert.match(widget, /<details class="feu-se-category-more">/);
    assert.match(widget, /<fieldset class="feu-se-field feu-se-category-picker">/);
    assert.match(css, /\.feu-se-category-more > summary/);
    assert.doesNotMatch(adminController, / В· /);
});

test('dashboard map status and report actions stay consistent', () => {
    const adminController = read('includes/class-admin.php');

    assert.match(adminController, /'map' === \$stage && in_array\(\$status_key, \['missing', 'queued', 'processing'\]/);
    assert.match(adminController, /'stage' => \$stage/);
    assert.match(adminController, /\$this->get_internal_report_edit_url\(\$post_id\)/);
    assert.doesNotMatch(adminController, /\$this->get_edit_url\(/);
    assert.match(adminController, /if \(!\$has_generated_map && \$map_is_public\)/);
    assert.match(adminController, /!in_array\(\$actual_status, \['queued', 'processing'\]/);
});

test('dashboard uses the stored event time without timezone drift', () => {
    const database = read('includes/class-database.php');
    const adminController = read('includes/class-admin.php');
    const dashboard = read('templates/admin/dashboard.php');

    assert.match(database, /COALESCE\(event_time\.meta_value, ''\) AS event_time/);
    assert.match(adminController, /DateTimeImmutable::createFromFormat\('!Y-m-d'/);
    assert.match(dashboard, /\$einsatz->event_time/);
    assert.doesNotMatch(dashboard, /wp_date\('d\.m\.Y H:i', \$timestamp\)/);
});

test('report overview opens the plugin editor and avoids duplicate map jobs', () => {
    const dashboard = read('templates/admin/dashboard.php');
    const reports = read('templates/admin/reports.php');

    assert.match(dashboard, /\$this->get_internal_report_edit_url\(\$_mq_id\)/);
    assert.match(dashboard, /!in_array\(\$_mq_actual_status, \['queued', 'processing'\]/);
    assert.match(reports, /\$this->get_internal_report_edit_url\(\$report_id\)/);
    assert.doesNotMatch(dashboard, /\$this->get_edit_url\(/);
    assert.doesNotMatch(reports, /\$this->get_edit_url\(/);
    assert.doesNotMatch(reports, /get_edit_post_link\(\$report_id\)/);
});

test('background administration actions expose failure feedback', () => {
    const settings = read('templates/admin/settings.php');
    const participants = read('templates/admin/participants.php');

    assert.match(settings, /saveOrganizationOrder[\s\S]*?showNotice\('error', texts\.connectionError\)/);
    assert.match(participants, /Archivierung konnte nicht rückgängig gemacht werden/);
});
