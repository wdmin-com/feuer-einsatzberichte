<?php
/** Participant lifecycle and report statistics invariants. */
if (!defined('ABSPATH')) {
    exit(1);
}

wp_set_current_user(1);
$db = Feuer_Einsatzberichte_Core::get_instance()->get_db();
$report_ids = [];
$participant_id = 0;
$unmarked_participant_id = 0;
$missing_id = 999999999;

try {
    if ($db->get_participant($missing_id)
        || false !== $db->save_participant($missing_id, ['vorname' => 'Missing', 'nachname' => 'Person'])
        || false !== $db->set_participant_archive_status($missing_id, true)
        || false !== $db->delete_participant($missing_id)) {
        throw new RuntimeException('A missing participant was reported as saved, archived or deleted.');
    }

    $participant_id = (int) $db->save_participant(0, ['vorname' => 'Workflow', 'nachname' => 'Fixture']);
    if ($participant_id < 1 || !$db->set_participant_archive_status($participant_id, true)) {
        throw new RuntimeException('A real participant could not be created and archived.');
    }
    for ($index = 0; $index < 2; $index++) {
        $id = wp_insert_post([
            'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => 'Workflow statistics fixture ' . $index,
        ], true);
        if (is_wp_error($id) || !$id) {
            throw new RuntimeException('Could not create statistics report fixture.');
        }
        $report_ids[] = (int) $id;
        update_post_meta($id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
        update_post_meta($id, '_feu_einsatz_datum', '2098-01-02');
    }
    $unmarked_participant_id = (int) $db->save_participant(0, ['vorname' => 'Ordinary', 'nachname' => 'Post']);
    $ordinary_post_id = wp_insert_post([
        'post_type' => 'post',
        'post_status' => 'publish',
        'post_title' => 'Ordinary post with stale assignment',
    ], true);
    if ($unmarked_participant_id < 1 || is_wp_error($ordinary_post_id) || !$ordinary_post_id) {
        throw new RuntimeException('Could not create an ordinary post statistics fixture.');
    }
    $report_ids[] = (int) $ordinary_post_id;
    update_post_meta($ordinary_post_id, '_feu_einsatz_datum', '2098-01-02');
    $db->save_participant_stats($ordinary_post_id, [['id' => $unmarked_participant_id, 'funktion' => 'Mannschaft']]);
    $db->save_participant_stats($report_ids[0], [['id' => $participant_id, 'funktion' => 'Maschinist']]);
    $totals = $db->get_total_statistics(2098);
    if (2 !== (int) $totals['total_einsaetze']
        || 1 !== (int) $totals['total_teilnehmer_aktiv']
        || 0.5 !== (float) $totals['avg_teilnehmer']) {
        throw new RuntimeException('Statistics excluded a zero-participant report or included an ordinary post.');
    }

    if (!$db->delete_participant($participant_id)
        || false !== $db->set_participant_archive_status($participant_id, false)) {
        throw new RuntimeException('A removed participant was reactivated.');
    }
    $removed = $db->get_participant($participant_id);
    if (!$removed || !$removed->is_deleted || !$removed->is_archived) {
        throw new RuntimeException('Removed participant state was not preserved.');
    }
    $after_removal = $db->get_total_statistics(2098);
    if (1 !== (int) $after_removal['total_teilnehmer_aktiv']
        || 0.5 !== (float) $after_removal['avg_teilnehmer']) {
        throw new RuntimeException('Removing a participant changed historical report statistics.');
    }
} finally {
    foreach ($report_ids as $id) {
        $db->save_participant_stats($id, []);
        wp_delete_post($id, true);
    }
    if ($participant_id > 0 || $unmarked_participant_id > 0) {
        global $wpdb;
        foreach ([$participant_id, $unmarked_participant_id] as $id) {
            if ($id > 0) {
                $wpdb->delete($db->get_participant_table_name(), ['id' => $id], ['%d']);
            }
        }
    }
    $db->invalidate_statistics_dashboard_cache();
}

echo "Participant lifecycle and zero-participant average verified.\n";
