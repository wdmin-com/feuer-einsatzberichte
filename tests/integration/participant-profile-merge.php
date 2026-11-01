<?php
/** Verifies lossless participant profile merging and future name locking. */
if (!defined('ABSPATH')) {
    exit(1);
}

wp_set_current_user(1);
$db = Feuer_Einsatzberichte_Core::get_instance()->get_db();
$primary_id = 0;
$duplicate_id = 0;
$report_ids = [];

try {
    $primary_id = (int) $db->save_participant(0, [
        'vorname' => 'Maximilian',
        'nachname' => 'Mustermann',
        'description' => 'Lokale Hauptakte',
        'default_functions' => ['Maschinist'],
    ]);
    $duplicate_id = (int) $db->save_participant(0, [
        'vorname' => 'Max',
        'nachname' => 'Muster',
        'rank_title' => 'Brandmeister',
        'default_functions' => ['Atemschutz'],
    ]);
    if ($primary_id < 1 || $duplicate_id < 1) {
        throw new RuntimeException('Participant merge fixtures could not be created.');
    }

    global $wpdb;
    $wpdb->update(
        $db->get_participant_table_name(),
        ['external_provider' => 'mannschaft', 'external_id' => 987654],
        ['id' => $duplicate_id],
        ['%s', '%d'],
        ['%d']
    );

    for ($index = 0; $index < 3; $index++) {
        $report_id = wp_insert_post([
            'post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => 'Participant merge fixture ' . $index,
        ], true);
        if (is_wp_error($report_id) || !$report_id) {
            throw new RuntimeException('Participant merge report fixture could not be created.');
        }
        $report_ids[] = (int) $report_id;
        update_post_meta($report_id, FEU_Einsatz_Report_Post_Type::MARKER_META, '1');
        update_post_meta($report_id, '_feu_einsatz_datum', '2097-02-0' . ($index + 1));
    }

    $assignments = [
        [['id' => $primary_id, 'funktion' => 'Maschinist']],
        [['id' => $duplicate_id, 'funktion' => 'Mannschaft']],
        [
            ['id' => $primary_id, 'funktion' => 'Mannschaft'],
            ['id' => $duplicate_id, 'funktion' => 'Atemschutz'],
        ],
    ];
    foreach ($report_ids as $index => $report_id) {
        update_post_meta($report_id, '_feu_einsatz_teilnehmer', $assignments[$index]);
        $db->save_participant_stats($report_id, $assignments[$index]);
    }

    $result = $db->merge_participant_profiles($primary_id, $duplicate_id, true);
    if (is_wp_error($result)) {
        throw new RuntimeException('Participant profiles could not be merged: ' . $result->get_error_code());
    }

    $primary = $db->get_participant($primary_id);
    $duplicate = $db->get_participant($duplicate_id);
    if (!$primary || 'Maximilian' !== $primary->vorname || 'Mustermann' !== $primary->nachname
        || 'mannschaft' !== $primary->external_provider || 987654 !== (int) $primary->external_id
        || 1 !== (int) $primary->external_name_locked || 'Brandmeister' !== $primary->rank_title) {
        throw new RuntimeException('Primary identity, external mapping, name lock or complementary fields were not preserved.');
    }
    if (!$duplicate || 1 !== (int) $duplicate->is_deleted || 1 !== (int) $duplicate->is_archived
        || '' !== $duplicate->external_provider || 0 !== (int) $duplicate->external_id) {
        throw new RuntimeException('Merged duplicate profile was not safely retired.');
    }

    $stats = $wpdb->get_results($wpdb->prepare(
        "SELECT post_id, teilnehmer_id, funktion FROM {$db->get_participant_statistics_table_name()} WHERE post_id IN (%d, %d, %d) ORDER BY post_id ASC",
        $report_ids[0],
        $report_ids[1],
        $report_ids[2]
    ));
    if (3 !== count($stats) || count(array_filter($stats, static fn($row) => (int) $row->teilnehmer_id !== $primary_id)) > 0) {
        throw new RuntimeException('Merged statistics contain duplicates or the retired participant ID.');
    }
    $third_function = '';
    foreach ($stats as $row) {
        if ((int) $row->post_id === $report_ids[2]) {
            $third_function = (string) $row->funktion;
        }
    }
    if ('Atemschutz' !== $third_function) {
        throw new RuntimeException('The more specific duplicate assignment function was not preserved.');
    }

    foreach ($report_ids as $report_id) {
        $meta = get_post_meta($report_id, '_feu_einsatz_teilnehmer', true);
        if (!is_array($meta) || 1 !== count($meta) || $primary_id !== (int) ($meta[0]['id'] ?? 0)) {
            throw new RuntimeException('Serialized report assignments were not normalized to the primary profile.');
        }
    }

    $synced = $db->sync_external_participant_identity('mannschaft', 987654, [
        'vorname' => 'M.',
        'nachname' => 'Muster',
        'job_title' => 'Gerätewart',
        'rank_title' => 'Oberbrandmeister',
        'primary_image_id' => 0,
    ]);
    $primary_after_sync = $db->get_participant($primary_id);
    if ($primary_id !== (int) $synced || 'Maximilian' !== $primary_after_sync->vorname || 'Mustermann' !== $primary_after_sync->nachname
        || 'Gerätewart' !== $primary_after_sync->job_title || 'Oberbrandmeister' !== $primary_after_sync->rank_title) {
        throw new RuntimeException('Future synchronization ignored the local name lock or failed to refresh non-name identity fields.');
    }
} finally {
    foreach ($report_ids as $report_id) {
        $db->save_participant_stats($report_id, []);
        wp_delete_post($report_id, true);
    }
    global $wpdb;
    foreach ([$primary_id, $duplicate_id] as $participant_id) {
        if ($participant_id > 0) {
            $wpdb->delete($db->get_participant_table_name(), ['id' => $participant_id], ['%d']);
        }
    }
    $db->invalidate_statistics_dashboard_cache();
}

WP_CLI::success('Participant profiles merge without duplicate statistics and keep the selected local name.');
