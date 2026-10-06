<?php
/** Isolated read-only migration status and permanent notice suppression check. */
if (!defined('ABSPATH')) {
    exit(1);
}

$previous_complete = get_option(FEU_Einsatz_Migration_Overview::COMPLETE_OPTION, null);
$previous_post_run = get_option('feu_einsatz_post_migration_run', null);
$previous_keyword_run = get_option('feu_einsatz_keyword_migration_run', null);
$previous_taxonomy_enabled = get_option(FEU_Einsatz_Report_Taxonomy::ENABLED_OPTION, null);
$previous_selected = get_option(FEU_Einsatz_Report_Taxonomy::SELECTED_OPTION, null);
$legacy_term_id = 0;
$new_term_id = 0;
delete_option(FEU_Einsatz_Migration_Overview::COMPLETE_OPTION);
delete_option('feu_einsatz_post_migration_run');
delete_option('feu_einsatz_keyword_migration_run');
$id = wp_insert_post([
    'post_type' => 'post',
    'post_status' => 'draft',
    'post_title' => 'migration-overview-fixture',
], true);
if (is_wp_error($id)) {
    throw new RuntimeException('Could not create migration overview fixture.');
}

try {
    update_post_meta($id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
    $old = FEU_Einsatz_Migration_Overview::status();
    if (false !== get_option(FEU_Einsatz_Migration_Overview::COMPLETE_OPTION, false)) {
        throw new RuntimeException('Reading migration status wrote a completion marker.');
    }
    if ($old['old_reports'] < 1 || !$old['needs_attention'] || 'old' !== $old['storage'] && 'mixed' !== $old['storage']) {
        throw new RuntimeException('Legacy report did not trigger the migration notice.');
    }
    wp_set_current_user(1);
    $migration_state = $old;
    $keyword_items = $old['keyword_items'];
    $post_preflight = FEU_Einsatz_Post_Migration::preflight();
    $keyword_preflight = [];
    $post_migration_notice = null;
    ob_start();
    include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/migration-overview.php';
    $migration_html = ob_get_clean();
    if (false === strpos($migration_html, 'name="migration_operation" value="start"')
        || false === strpos($migration_html, 'Archiv erstellen und Übertragung starten')
        || false !== strpos($migration_html, 'name="database_backup"')) {
        throw new RuntimeException('The migration overview does not offer the report start action.');
    }

    if (!set_post_type($id, FEU_Einsatz_Report_Post_Type::POST_TYPE)) {
        throw new RuntimeException('Could not move fixture to the new post type.');
    }
    $new = FEU_Einsatz_Migration_Overview::status();
    if ($new['new_reports'] < 1 || $new['old_reports'] !== $old['old_reports'] - 1) {
        throw new RuntimeException('Storage counts did not follow the actual post type.');
    }

    if (0 === $new['old_reports']) {
        $suffix = strtolower(wp_generate_password(8, false, false));
        $legacy_term = wp_insert_term('Migration overview ' . $suffix, 'category', ['slug' => 'migration-overview-' . $suffix]);
        if (is_wp_error($legacy_term)) {
            throw new RuntimeException('Could not create legacy term fixture.');
        }
        $legacy_term_id = (int) $legacy_term['term_id'];
        $new_term = wp_insert_term('Migration overview ' . $suffix, FEU_Einsatz_Report_Taxonomy::TAXONOMY, ['slug' => 'migration-overview-' . $suffix]);
        if (is_wp_error($new_term)) {
            throw new RuntimeException('Could not create new term fixture.');
        }
        $new_term_id = (int) $new_term['term_id'];
        update_term_meta($new_term_id, FEU_Einsatz_Report_Taxonomy::LEGACY_TERM_META, $legacy_term_id);
        update_option(FEU_Einsatz_Report_Taxonomy::ENABLED_OPTION, 1, false);
        update_option(FEU_Einsatz_Report_Taxonomy::SELECTED_OPTION, [$new_term_id], false);
        if (is_wp_error(wp_set_object_terms($id, [$new_term_id], FEU_Einsatz_Report_Taxonomy::TAXONOMY, false))) {
            throw new RuntimeException('Could not assign new term fixture.');
        }
        update_post_meta($id, FEU_Einsatz_Report_Taxonomy::PRIMARY_META, $new_term_id);
        update_post_meta($id, FEU_Einsatz_Report_Post_Type::URL_SCHEME_META, 'legacy');
        $url = get_permalink($id);
        update_option('feu_einsatz_post_migration_run', [
            'run_id' => wp_generate_uuid4(),
            'status' => 'complete',
            'records' => [[
                'id' => $id,
                'state' => 'migrated',
                'status' => 'draft',
                'url' => $url,
                'fingerprint' => FEU_Einsatz_Post_Migration::fingerprint($id),
            ]],
        ], false);
        $keyword_run = [
            'status' => 'complete',
            'old_selected' => [$legacy_term_id],
            'reports' => [$id => ['url' => $url]],
            'processed' => [$id],
            'cutover_selected' => [$new_term_id],
            'cutover_terms' => [$id => ['ids' => [$new_term_id], 'primary' => $new_term_id]],
        ];
        update_option('feu_einsatz_keyword_migration_run', $keyword_run, false);
        $ready = FEU_Einsatz_Migration_Overview::status();
        if (!$ready['ready_for_acceptance'] || !$ready['needs_attention']
            || !FEU_Einsatz_Migration_Overview::verify_for_acceptance()) {
            throw new RuntimeException('Completed technical runs did not request final acceptance.');
        }
        update_post_meta($id, '_feu_einsatz_ci_changed', 'after-migration');
        if (FEU_Einsatz_Migration_Overview::verify_for_acceptance()) {
            throw new RuntimeException('Changed report data passed automatic final verification.');
        }
        delete_post_meta($id, '_feu_einsatz_ci_changed');
        $keyword_run['reports'][$id]['url'] = 'https://invalid.example.test/changed/';
        update_option('feu_einsatz_keyword_migration_run', $keyword_run, false);
        if (FEU_Einsatz_Migration_Overview::verify_for_acceptance()) {
            throw new RuntimeException('Changed URL passed the final server-side verification.');
        }
        update_option('feu_einsatz_keyword_migration_run', [
            'status' => 'complete',
            'old_selected' => [999999999],
        ], false);
        if (FEU_Einsatz_Migration_Overview::status()['ready_for_acceptance']) {
            throw new RuntimeException('Missing keyword mapping was accepted as a complete migration.');
        }
    }
    update_option(FEU_Einsatz_Migration_Overview::COMPLETE_OPTION, 1, false);
    if (FEU_Einsatz_Migration_Overview::status()['needs_attention']) {
        throw new RuntimeException('Completed migration still shows the initial notice.');
    }
} finally {
    wp_delete_post($id, true);
    if ($new_term_id) {
        wp_delete_term($new_term_id, FEU_Einsatz_Report_Taxonomy::TAXONOMY);
    }
    if ($legacy_term_id) {
        wp_delete_term($legacy_term_id, 'category');
    }
    foreach ([
        FEU_Einsatz_Migration_Overview::COMPLETE_OPTION => $previous_complete,
        'feu_einsatz_post_migration_run' => $previous_post_run,
        'feu_einsatz_keyword_migration_run' => $previous_keyword_run,
        FEU_Einsatz_Report_Taxonomy::ENABLED_OPTION => $previous_taxonomy_enabled,
        FEU_Einsatz_Report_Taxonomy::SELECTED_OPTION => $previous_selected,
    ] as $key => $value) {
        if (null === $value) {
            delete_option($key);
        } else {
            update_option($key, $value, false);
        }
    }
}

echo "Migration overview reflects the real post type and suppresses a completed notice.\n";
