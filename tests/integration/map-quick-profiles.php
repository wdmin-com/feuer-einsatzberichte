<?php
if (!defined('ABSPATH')) {
    exit(1);
}

wp_set_current_user(1);
$profiles = FEU_Einsatz_Map_Quick_Profiles::get();
if (5 !== count($profiles)
    || 'Technische Hilfe' !== $profiles['segment_100']['label']
    || 'radius' !== $profiles['segment_100']['mode']
    || 750 !== $profiles['segment_100']['meters']
    || [123] !== $profiles['segment_100']['categories']
    || 1 !== $profiles['segment_100']['enabled']
    || 0 !== $profiles['full']['enabled']) {
    throw new RuntimeException('Map quick-profile settings were not saved by the settings form.');
}

$invalid = FEU_Einsatz_Map_Quick_Profiles::normalize([
    'full' => ['label' => '<script></script>', 'mode' => 'invalid', 'meters' => 999999, 'categories' => ['0', '7', '7']],
]);
if ('full' !== $invalid['full']['mode'] || 5000 !== $invalid['full']['meters'] || [7] !== $invalid['full']['categories']) {
    throw new RuntimeException('Invalid map quick-profile values were not normalized.');
}

$post_id = wp_insert_post(['post_type' => FEU_Einsatz_Report_Post_Type::POST_TYPE, 'post_status' => 'draft', 'post_title' => 'Profile stability test'], true);
if (is_wp_error($post_id)) {
    throw new RuntimeException($post_id->get_error_message());
}
try {
    update_post_meta($post_id, FEU_Einsatz_Template_Helpers::MAP_HIGHLIGHT_OVERRIDE_META, 'length');
    update_post_meta($post_id, FEU_Einsatz_Template_Helpers::MAP_HIGHLIGHT_LENGTH_META, 130);
    $report_map = FEU_Einsatz_Template_Helpers::get_report_street_highlight_settings($post_id);
    if ('length' !== $report_map['mode'] || 130 !== $report_map['length_meters']) {
        throw new RuntimeException('Changing profile settings altered an existing report map.');
    }
} finally {
    wp_delete_post($post_id, true);
    delete_option(FEU_Einsatz_Map_Quick_Profiles::OPTION);
}

WP_CLI::success('Configurable quick profiles are saved, normalized and leave report maps intact.');
