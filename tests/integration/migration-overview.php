<?php
/** Isolated read-only migration status and permanent notice suppression check. */
if (!defined('ABSPATH')) {
    exit(1);
}

$previous_complete = get_option(FEU_Einsatz_Migration_Overview::COMPLETE_OPTION, null);
$previous_post_run = get_option('feu_einsatz_post_migration_run', null);
$previous_keyword_run = get_option('feu_einsatz_keyword_migration_run', null);
$previous_taxonomy_enabled = get_option(FEU_Einsatz_Report_Taxonomy::ENABLED_OPTION, null);
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

    if (!set_post_type($id, FEU_Einsatz_Report_Post_Type::POST_TYPE)) {
        throw new RuntimeException('Could not move fixture to the new post type.');
    }
    $new = FEU_Einsatz_Migration_Overview::status();
    if ($new['new_reports'] < 1 || $new['old_reports'] !== $old['old_reports'] - 1) {
        throw new RuntimeException('Storage counts did not follow the actual post type.');
    }

    if (0 === $new['old_reports']) {
        update_option('feu_einsatz_post_migration_run', [
            'run_id' => wp_generate_uuid4(),
            'status' => 'complete',
            'records' => [['id' => $id, 'state' => 'migrated']],
        ], false);
        update_option('feu_einsatz_keyword_migration_run', ['status' => 'complete'], false);
        update_option(FEU_Einsatz_Report_Taxonomy::ENABLED_OPTION, 1, false);
        $ready = FEU_Einsatz_Migration_Overview::status();
        if (!$ready['ready_for_acceptance'] || !$ready['needs_attention']) {
            throw new RuntimeException('Completed technical runs did not request final acceptance.');
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
    foreach ([
        FEU_Einsatz_Migration_Overview::COMPLETE_OPTION => $previous_complete,
        'feu_einsatz_post_migration_run' => $previous_post_run,
        'feu_einsatz_keyword_migration_run' => $previous_keyword_run,
        FEU_Einsatz_Report_Taxonomy::ENABLED_OPTION => $previous_taxonomy_enabled,
    ] as $key => $value) {
        if (null === $value) {
            delete_option($key);
        } else {
            update_option($key, $value, false);
        }
    }
}

echo "Migration overview reflects the real post type and suppresses a completed notice.\n";
