<?php
/** Isolated WP-CLI integration test: batch migration, lock, recovery and rollback. */
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    exit(1);
}

function feu_einsatz_migration_fail(string $message): void {
    fwrite(STDERR, "::error title=Report migration::{$message}\n");
    exit(1);
}

wp_set_current_user(1);
if (!current_user_can('manage_options')) {
    feu_einsatz_migration_fail('Administrator context is missing.');
}
if (!defined('FEU_EINSATZ_ALLOW_POST_MIGRATION')) {
    define('FEU_EINSATZ_ALLOW_POST_MIGRATION', true);
}

$legacy_ids = [];
foreach (['migration-batch-one', 'migration-batch-two'] as $slug) {
    $id = wp_insert_post([
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_title' => $slug,
        'post_name' => $slug,
    ], true);
    if (is_wp_error($id)) {
        feu_einsatz_migration_fail('Could not create a legacy report fixture.');
    }
    update_post_meta($id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
    $legacy_ids[] = (int) $id;
}
wp_set_post_tags($legacy_ids[0], ['migration-ci-tag']);
$tags_before = wp_get_post_tags($legacy_ids[0], ['fields' => 'ids']);

$urls_before = array_map('get_permalink', $legacy_ids);
$db_backup = tempnam(sys_get_temp_dir(), 'feu-migration-db-');
$uploads_backup = tempnam(sys_get_temp_dir(), 'feu-migration-uploads-');
file_put_contents($db_backup, 'CI database backup fixture');
file_put_contents($uploads_backup, 'CI uploads backup fixture');

$wrong_checksum = FEU_Einsatz_Post_Migration::start(
    $db_backup,
    $uploads_backup,
    str_repeat('0', 64),
    hash_file('sha256', $uploads_backup),
    true
);
if (!is_wp_error($wrong_checksum) || 'backup_checksum_mismatch' !== $wrong_checksum->get_error_code()) {
    feu_einsatz_migration_fail('A mismatched backup checksum was accepted.');
}

$started = FEU_Einsatz_Post_Migration::start(
    $db_backup,
    $uploads_backup,
    hash_file('sha256', $db_backup),
    hash_file('sha256', $uploads_backup),
    true
);
if (is_wp_error($started) || 'running' !== ($started['status'] ?? '') || count($legacy_ids) !== ($started['total'] ?? -1)) {
    feu_einsatz_migration_fail('Migration did not start with the fixed legacy snapshot.');
}
$run_id = (string) $started['run_id'];

if (false !== update_post_meta($legacy_ids[0], '_feu_einsatz_ci_locked', 'should-not-save')) {
    feu_einsatz_migration_fail('Report metadata could be edited while migration was active.');
}

$first = FEU_Einsatz_Post_Migration::migrate_batch($run_id, 1);
if (is_wp_error($first) || 'running' !== ($first['status'] ?? '') || 1 !== ($first['cursor'] ?? -1)) {
    feu_einsatz_migration_fail('The first batch did not stop after one report.');
}
$journal = get_option('feu_einsatz_post_migration_run');
$journal['cursor'] = 0;
$journal['records'][0]['state'] = 'pending';
update_option('feu_einsatz_post_migration_run', $journal, false);
$recovered = FEU_Einsatz_Post_Migration::migrate_batch($run_id, 1);
if (is_wp_error($recovered) || 1 !== ($recovered['cursor'] ?? -1)) {
    feu_einsatz_migration_fail('A conversion interrupted before journal update could not resume.');
}
$second = FEU_Einsatz_Post_Migration::migrate_batch($run_id, 1);
if (is_wp_error($second) || 'complete' !== ($second['status'] ?? '') || 2 !== ($second['counts']['migrated'] ?? -1)) {
    feu_einsatz_migration_fail('The second batch did not complete the migration.');
}
foreach ($legacy_ids as $index => $id) {
    if (FEU_Einsatz_Report_Post_Type::POST_TYPE !== get_post_type($id) || get_permalink($id) !== $urls_before[$index]) {
        feu_einsatz_migration_fail('A migrated report changed its type incorrectly or lost its public URL.');
    }
}
if ($tags_before !== wp_get_post_tags($legacy_ids[0], ['fields' => 'ids'])) {
    feu_einsatz_migration_fail('A legacy report lost its tags during conversion.');
}

$completed_journal = get_option('feu_einsatz_post_migration_run');
if (!set_post_type($legacy_ids[1], 'post')) {
    feu_einsatz_migration_fail('Could not reset the second report for the isolation check.');
}
$failing_journal = $completed_journal;
$failing_journal['status'] = 'failed';
$failing_journal['cursor'] = 0;
$failing_journal['records'][0]['fingerprint'] = str_repeat('0', 64);
$failing_journal['records'][1]['state'] = 'pending';
update_option('feu_einsatz_post_migration_run', $failing_journal, false);
$diagnostic_mails = [];
$intercept_mail = static function ($pre, $atts) use (&$diagnostic_mails) {
    $diagnostic_mails[] = $atts;
    return true;
};
add_filter('pre_wp_mail', $intercept_mail, 10, 2);
$failure_one = FEU_Einsatz_Post_Migration::migrate_batch($run_id, 2);
$failure_two = FEU_Einsatz_Post_Migration::migrate_batch($run_id, 1, $legacy_ids[0]);
$failure_three = FEU_Einsatz_Post_Migration::migrate_batch($run_id, 1, $legacy_ids[0]);
remove_filter('pre_wp_mail', $intercept_mail, 10);
if (is_wp_error($failure_one) || is_wp_error($failure_two) || is_wp_error($failure_three)
    || 'partial_error' !== $failure_one['status']
    || 1 !== (int) ($failure_one['counts']['migrated'] ?? 0)
    || 1 !== (int) ($failure_one['counts']['failed'] ?? 0)
    || FEU_Einsatz_Report_Post_Type::POST_TYPE !== get_post_type($legacy_ids[1])
    || 'partial_error' !== $failure_three['status']
    || 1 !== count($diagnostic_mails)
    || 'dev@wdmin.com' !== ($diagnostic_mails[0]['to'] ?? '')
    || false !== strpos((string) ($diagnostic_mails[0]['message'] ?? ''), 'migration-batch-one')) {
    feu_einsatz_migration_fail('Repeated report failures did not produce one redacted diagnostic email.');
}
update_option('feu_einsatz_post_migration_run', $completed_journal, false);

wp_update_post(['ID' => $legacy_ids[0], 'post_name' => 'migration-should-not-change-url']);
if ('migration-batch-one' !== get_post_field('post_name', $legacy_ids[0])) {
    feu_einsatz_migration_fail('An ordinary edit changed the immutable legacy slug.');
}

// Simulate a process dying after set_post_type() during rollback, before
// restoring the original URL meta and recording the journal state.
$journal = get_option('feu_einsatz_post_migration_run');
$journal['records'][1]['state'] = 'pending';
update_option('feu_einsatz_post_migration_run', $journal, false);
if (!set_post_type($legacy_ids[1], 'post')) {
    feu_einsatz_migration_fail('Could not simulate an interrupted rollback.');
}

$rollback_first = FEU_Einsatz_Post_Migration::rollback_batch($run_id, 1);
$journal = get_option('feu_einsatz_post_migration_run');
$journal['status'] = 'failed';
update_option('feu_einsatz_post_migration_run', $journal, false);
$forward_after_rollback = FEU_Einsatz_Post_Migration::migrate_batch($run_id, 1);
if (!is_wp_error($forward_after_rollback) || 'migration_run_invalid' !== $forward_after_rollback->get_error_code()) {
    feu_einsatz_migration_fail('A failed rollback could incorrectly resume forward migration.');
}
$rollback_second = FEU_Einsatz_Post_Migration::rollback_batch($run_id, 1);
if (is_wp_error($rollback_first) || is_wp_error($rollback_second)
    || 'rolled_back' !== ($rollback_second['status'] ?? '')
    || 2 !== ($rollback_second['counts']['rolled_back'] ?? -1)) {
    feu_einsatz_migration_fail('Rollback did not complete in two batches.');
}
foreach ($legacy_ids as $index => $id) {
    if ('post' !== get_post_type($id) || get_permalink($id) !== $urls_before[$index]) {
        feu_einsatz_migration_fail('Rollback did not restore the original type and URL.');
    }
}

set_current_screen('dashboard');
$_POST = [
    'migration_operation' => 'start',
    'database_backup' => $db_backup,
    'uploads_backup' => $uploads_backup,
    'database_sha256' => hash_file('sha256', $db_backup),
    'uploads_sha256' => hash_file('sha256', $uploads_backup),
    'staging_verified' => '1',
    'migration_confirmation' => 'START',
    'feu_einsatz_post_migration_nonce' => wp_create_nonce('feu_einsatz_post_migration'),
];
$_REQUEST = $_POST;
$admin_start = FEU_Einsatz_Post_Migration::handle_admin_action();
if (is_wp_error($admin_start) || 'running' !== ($admin_start['status'] ?? '')) {
    feu_einsatz_migration_fail('The administrator start action is unavailable.');
}
$_POST = [
    'migration_operation' => 'batch',
    'run_id' => (string) $admin_start['run_id'],
    'feu_einsatz_post_migration_nonce' => wp_create_nonce('feu_einsatz_post_migration'),
];
$_REQUEST = $_POST;
$admin_batch = FEU_Einsatz_Post_Migration::handle_admin_action();
if (is_wp_error($admin_batch) || 'complete' !== ($admin_batch['status'] ?? '')) {
    feu_einsatz_migration_fail('The administrator batch action did not migrate the reports.');
}
$_POST = [
    'migration_operation' => 'rollback',
    'run_id' => (string) $admin_start['run_id'],
    'migration_confirmation' => 'ROLLBACK',
    'feu_einsatz_post_migration_nonce' => wp_create_nonce('feu_einsatz_post_migration'),
];
$_REQUEST = $_POST;
$admin_rollback = FEU_Einsatz_Post_Migration::handle_admin_action();
if (is_wp_error($admin_rollback) || 'rolled_back' !== ($admin_rollback['status'] ?? '')) {
    feu_einsatz_migration_fail('The administrator rollback action did not restore the reports.');
}
foreach ($legacy_ids as $id) {
    wp_delete_post($id, true);
}

delete_option('feu_einsatz_post_migration_run');
unlink($db_backup);
unlink($uploads_backup);
WP_CLI::success('Batch migration, checksum guard, write lock, URL preservation and rollback passed.');
