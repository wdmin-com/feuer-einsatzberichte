<?php
if (!defined('ABSPATH')) {
    exit;
}

if (function_exists('wp_enqueue_media')) {
    wp_enqueue_media();
}

$jahre = array_values(array_unique(array_map('intval', (array) $this->db->get_available_years())));
$jahre = array_values(array_filter($jahre));
rsort($jahre);
$aktuelles_jahr = (int) date('Y');
if (empty($jahre)) {
    $jahre = [$aktuelles_jahr];
}

$default_jahr = in_array($aktuelles_jahr, $jahre, true) ? $aktuelles_jahr : (int) $jahre[0];

$allowed_tabs = ['categories', 'calendar', 'activity-map', 'participants', 'presentation-settings'];
$active_tab = 'categories';
if (isset($_POST['feu_einsatz_stats_tab'])) {
    $requested_tab = sanitize_key(wp_unslash($_POST['feu_einsatz_stats_tab']));
    if (in_array($requested_tab, $allowed_tabs, true)) {
        $active_tab = $requested_tab;
    }
}

$requested_year = isset($_REQUEST['jahr']) ? absint(wp_unslash($_REQUEST['jahr'])) : 0;
$jahr = in_array($requested_year, $jahre, true) ? $requested_year : $default_jahr;
$selected_participant_id = isset($_GET['participant']) ? absint(wp_unslash($_GET['participant'])) : 0;
$participant_ranking_unlocked = FEU_Einsatz_Admin::is_participant_ranking_unlocked_for_current_user();
$participant_ranking_notice = null;
$presentation_settings_notice = null;
$presentation_setting_options = [
    'welcome' => ['page' => 1, 'title' => __('Willkommen', 'feuer-einsatzberichte'), 'blocks' => [__('Logo aus Einstellungen', 'feuer-einsatzberichte')]],
    'overview' => ['page' => 2, 'title' => __('Übersicht', 'feuer-einsatzberichte'), 'blocks' => [__('Einsätze', 'feuer-einsatzberichte'), __('Stärkster Monat', 'feuer-einsatzberichte'), __('Top-Einsatzstichwort', 'feuer-einsatzberichte')]],
    'categories' => ['page' => 3, 'title' => __('Einsatzstichworte', 'feuer-einsatzberichte'), 'blocks' => [__('Einsatzstichwort-Balken', 'feuer-einsatzberichte'), __('Einsatzstichwort-Anteile', 'feuer-einsatzberichte')]],
    'calendar' => ['page' => 4, 'title' => __('Kalender', 'feuer-einsatzberichte'), 'blocks' => [__('Beliebte Wochentage', 'feuer-einsatzberichte'), __('Stärkste Tage', 'feuer-einsatzberichte'), __('Monatsverteilung', 'feuer-einsatzberichte')]],
    'activity_map' => ['page' => 5, 'title' => __('Aktivitätskarte', 'feuer-einsatzberichte'), 'blocks' => [__('Live-Karte', 'feuer-einsatzberichte')]],
    'participants' => ['page' => 6, 'title' => __('Teilnehmer', 'feuer-einsatzberichte'), 'blocks' => [__('Top 3 Teilnehmer', 'feuer-einsatzberichte'), __('Teilnehmerliste', 'feuer-einsatzberichte')]],
];
$presentation_setting_keys = array_keys($presentation_setting_options);
$presentation_enabled_settings = get_option('feu_einsatz_statistics_presentation_sections', $presentation_setting_keys);
if (!is_array($presentation_enabled_settings)) {
    $presentation_enabled_settings = $presentation_setting_keys;
}
$presentation_enabled_settings = array_values(array_intersect($presentation_setting_keys, array_map('sanitize_key', $presentation_enabled_settings)));
if (empty($presentation_enabled_settings)) {
    $presentation_enabled_settings = $presentation_setting_keys;
}
$presentation_custom_pages = get_option('feu_einsatz_statistics_presentation_custom_pages', []);
if (!is_array($presentation_custom_pages)) {
    $presentation_custom_pages = [];
}
$presentation_custom_pages = array_values(array_filter(array_map(static function($page) {
    if (!is_array($page)) {
        return null;
    }

    return [
        'enabled' => !empty($page['enabled']) ? 1 : 0,
        'title' => sanitize_text_field((string) ($page['title'] ?? '')),
        'subtitle' => sanitize_text_field((string) ($page['subtitle'] ?? '')),
        'content' => wp_kses_post((string) ($page['content'] ?? '')),
    ];
}, $presentation_custom_pages)));
$presentation_logo_size = max(20, min(140, absint(get_option('feu_einsatz_statistics_presentation_logo_size', 72))));
$presentation_background_id = absint(get_option('feu_einsatz_statistics_presentation_background_image_id', 0));
$presentation_background_url = $presentation_background_id > 0 ? (string) wp_get_attachment_image_url($presentation_background_id, 'large') : '';
$presentation_background_opacity = max(0, min(100, absint(get_option('feu_einsatz_statistics_presentation_background_opacity', 28))));
$presentation_background_darkness = max(0, min(100, absint(get_option('feu_einsatz_statistics_presentation_background_darkness', 18))));
$presentation_background_pages = get_option('feu_einsatz_statistics_presentation_background_pages', []);
if (!is_array($presentation_background_pages)) {
    $presentation_background_pages = [];
}
$presentation_background_pages = array_values(array_map('sanitize_key', $presentation_background_pages));

if (isset($_POST['feu_einsatz_presentation_settings_action'])) {
    if (check_admin_referer('feu_einsatz_statistics_presentation_settings', 'feu_einsatz_statistics_presentation_settings_nonce')) {
        $presentation_action = sanitize_key(wp_unslash($_POST['feu_einsatz_presentation_settings_action']));
        $submitted_presentation_settings = isset($_POST['feu_einsatz_presentation_sections'])
            ? array_map('sanitize_key', (array) wp_unslash($_POST['feu_einsatz_presentation_sections']))
            : [];
        $presentation_enabled_settings = array_values(array_intersect($presentation_setting_keys, $submitted_presentation_settings));

        if (empty($presentation_enabled_settings)) {
            $presentation_enabled_settings = ['welcome'];
        }

        $submitted_titles = isset($_POST['feu_einsatz_presentation_custom_title'])
            ? (array) wp_unslash($_POST['feu_einsatz_presentation_custom_title'])
            : [];
        $submitted_subtitles = isset($_POST['feu_einsatz_presentation_custom_subtitle'])
            ? (array) wp_unslash($_POST['feu_einsatz_presentation_custom_subtitle'])
            : [];
        $submitted_contents = isset($_POST['feu_einsatz_presentation_custom_content'])
            ? (array) wp_unslash($_POST['feu_einsatz_presentation_custom_content'])
            : [];
        $submitted_enabled = isset($_POST['feu_einsatz_presentation_custom_enabled'])
            ? array_map('absint', (array) wp_unslash($_POST['feu_einsatz_presentation_custom_enabled']))
            : [];
        $submitted_order = isset($_POST['feu_einsatz_presentation_custom_order'])
            ? array_map('absint', (array) wp_unslash($_POST['feu_einsatz_presentation_custom_order']))
            : array_map('absint', array_keys($submitted_titles));
        $submitted_background_pages = isset($_POST['feu_einsatz_presentation_background_pages'])
            ? array_values(array_map('sanitize_key', (array) wp_unslash($_POST['feu_einsatz_presentation_background_pages'])))
            : [];
        $presentation_custom_pages = [];
        $custom_background_pages = [];
        $handled_custom_page_indexes = [];

        foreach ($submitted_order as $page_index) {
            if (isset($handled_custom_page_indexes[$page_index]) || !array_key_exists($page_index, $submitted_titles)) {
                continue;
            }

            $handled_custom_page_indexes[$page_index] = true;
            $page_title = $submitted_titles[$page_index];
            $title = sanitize_text_field((string) $page_title);
            $subtitle = isset($submitted_subtitles[$page_index]) ? sanitize_text_field((string) $submitted_subtitles[$page_index]) : '';
            $content = isset($submitted_contents[$page_index]) ? wp_kses_post((string) $submitted_contents[$page_index]) : '';

            if ('' === $title && '' === $subtitle && '' === trim(wp_strip_all_tags($content))) {
                continue;
            }

            $new_page_index = count($presentation_custom_pages);
            $presentation_custom_pages[] = [
                'enabled' => in_array((int) $page_index, $submitted_enabled, true) ? 1 : 0,
                'title' => $title ?: __('Eigene Seite', 'feuer-einsatzberichte'),
                'subtitle' => $subtitle,
                'content' => $content,
            ];

            if (in_array('custom_' . (string) $page_index, $submitted_background_pages, true)) {
                $custom_background_pages[] = 'custom_' . (string) $new_page_index;
            }
        }

        if ('add_custom_page' === $presentation_action) {
            $presentation_custom_pages[] = [
                'enabled' => 1,
                'title' => __('Neue Präsentationsseite', 'feuer-einsatzberichte'),
                'subtitle' => '',
                'content' => '',
            ];
        }

        $presentation_logo_size = isset($_POST['feu_einsatz_presentation_logo_size'])
            ? max(20, min(140, absint(wp_unslash($_POST['feu_einsatz_presentation_logo_size']))))
            : $presentation_logo_size;
        $presentation_background_id = isset($_POST['feu_einsatz_presentation_background_image_id'])
            ? absint(wp_unslash($_POST['feu_einsatz_presentation_background_image_id']))
            : $presentation_background_id;
        $presentation_background_opacity = isset($_POST['feu_einsatz_presentation_background_opacity'])
            ? max(0, min(100, absint(wp_unslash($_POST['feu_einsatz_presentation_background_opacity']))))
            : $presentation_background_opacity;
        $presentation_background_darkness = isset($_POST['feu_einsatz_presentation_background_darkness'])
            ? max(0, min(100, absint(wp_unslash($_POST['feu_einsatz_presentation_background_darkness']))))
            : $presentation_background_darkness;
        $presentation_background_pages = array_values(array_unique(array_merge(
            array_values(array_intersect($presentation_setting_keys, $submitted_background_pages)),
            $custom_background_pages
        )));
        $presentation_background_url = $presentation_background_id > 0 ? (string) wp_get_attachment_image_url($presentation_background_id, 'large') : '';

        update_option('feu_einsatz_statistics_presentation_sections', $presentation_enabled_settings, false);
        update_option('feu_einsatz_statistics_presentation_custom_pages', $presentation_custom_pages, false);
        update_option('feu_einsatz_statistics_presentation_logo_size', $presentation_logo_size, false);
        update_option('feu_einsatz_statistics_presentation_background_image_id', $presentation_background_id, false);
        update_option('feu_einsatz_statistics_presentation_background_opacity', $presentation_background_opacity, false);
        update_option('feu_einsatz_statistics_presentation_background_darkness', $presentation_background_darkness, false);
        update_option('feu_einsatz_statistics_presentation_background_pages', $presentation_background_pages, false);
        $presentation_settings_notice = ['type' => 'success', 'message' => __('Präsentations-Einstellungen wurden gespeichert.', 'feuer-einsatzberichte')];
        $active_tab = 'presentation-settings';
    } else {
        $presentation_settings_notice = ['type' => 'error', 'message' => __('Sicherheitsprüfung fehlgeschlagen. Bitte laden Sie die Seite neu.', 'feuer-einsatzberichte')];
        $active_tab = 'presentation-settings';
    }
}

if (isset($_POST['feu_einsatz_stats_action'])) {
    if (check_admin_referer('feu_einsatz_statistics_participant_access', 'feu_einsatz_statistics_participant_access_nonce')) {
        $stats_action = sanitize_key(wp_unslash($_POST['feu_einsatz_stats_action']));

        if ('unlock_participant_ranking' === $stats_action) {
            $submitted_pin = isset($_POST['feu_einsatz_participant_ranking_pin']) ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_participant_ranking_pin'])) : '';

            if (FEU_Einsatz_Admin::verify_participant_ranking_pin($submitted_pin)) {
                FEU_Einsatz_Admin::unlock_participant_ranking_for_current_user();
                $participant_ranking_unlocked = true;
                $participant_ranking_notice = ['type' => 'success', 'message' => __('Teilnehmer-Ranking wurde erfolgreich entsperrt.', 'feuer-einsatzberichte')];
            } else {
                FEU_Einsatz_Admin::lock_participant_ranking_for_current_user();
                $participant_ranking_unlocked = false;
                $participant_ranking_notice = ['type' => 'error', 'message' => __('PIN ist ungültig. Bitte versuchen Sie es erneut.', 'feuer-einsatzberichte')];
            }

            $active_tab = 'participants';
        }

        if ('lock_participant_ranking' === $stats_action) {
            FEU_Einsatz_Admin::lock_participant_ranking_for_current_user();
            $participant_ranking_unlocked = false;
            $participant_ranking_notice = ['type' => 'success', 'message' => __('Teilnehmer-Ranking wurde wieder gesperrt.', 'feuer-einsatzberichte')];
            $active_tab = 'participants';
        }
    } else {
        $participant_ranking_notice = ['type' => 'error', 'message' => __('Sicherheitsprüfung fehlgeschlagen. Bitte laden Sie die Seite neu.', 'feuer-einsatzberichte')];
        $active_tab = 'participants';
    }
}

if ($selected_participant_id > 0) {
    $active_tab = 'participants';
}

$statistics_dashboard_data = $this->db->get_statistics_dashboard_data($jahr);
$total = $statistics_dashboard_data['total'];
$categories = $statistics_dashboard_data['categories'];
$participants = $participant_ranking_unlocked ? $statistics_dashboard_data['participants'] : [];
$presentation_participants = $statistics_dashboard_data['participants'];
$daily_stats = $statistics_dashboard_data['daily_stats'];
$daily_report_entries = $statistics_dashboard_data['daily_report_entries'];
$activity_map_points = $statistics_dashboard_data['activity_map_points'];
$participant_distribution_chart_data = [];

usort($participants, static function($a, $b) {
    return (int) $b->gesamt_einsaetze <=> (int) $a->gesamt_einsaetze;
});
usort($presentation_participants, static function($a, $b) {
    return (int) $b->gesamt_einsaetze <=> (int) $a->gesamt_einsaetze;
});

$category_history = [];
foreach ($jahre as $history_year) {
    $category_history[$history_year] = $this->db->get_category_statistics((int) $history_year);
}

$count_by_date = [];
foreach ($daily_stats as $stat) {
    $count_by_date[$stat->datum] = (int) $stat->anzahl;
}

$daily_reports_by_date = [];
foreach ($daily_report_entries as $entry) {
    if (empty($entry->datum)) {
        continue;
    }

    $daily_reports_by_date[(string) $entry->datum][] = [
        'post_id' => isset($entry->post_id) ? (int) $entry->post_id : 0,
        'title' => isset($entry->post_title) ? (string) $entry->post_title : '',
        'time' => isset($entry->event_time) ? (string) $entry->event_time : '',
        'street' => FEU_Einsatz_Template_Helpers::strip_house_number_from_street((string) ($entry->street ?? '')),
        'edit_url' => (!empty($entry->post_id) && current_user_can('edit_post', (int) $entry->post_id))
            ? admin_url('post.php?post=' . absint($entry->post_id) . '&action=edit&feu_einsatz_einsatzbericht=1')
            : '',
    ];
}

$participant_distribution_period_labels = [
    'all' => __('Gesamtzeitraum', 'feuer-einsatzberichte'),
    'quarter' => __('Quartal', 'feuer-einsatzberichte'),
    'month' => __('Monat', 'feuer-einsatzberichte'),
    'week' => __('Woche', 'feuer-einsatzberichte'),
];

foreach ($participant_distribution_period_labels as $period_key => $period_label) {
    $distribution_rows = $this->db->get_call_participant_distribution($period_key, 'all' === $period_key ? $jahr : null);
    $total_calls = 0;
    $labels = [];
    $counts = [];

    foreach ($distribution_rows as $distribution_row) {
        $participant_count = isset($distribution_row['teilnehmer_count']) ? max(0, (int) $distribution_row['teilnehmer_count']) : 0;
        $call_count = isset($distribution_row['call_count']) ? max(0, (int) $distribution_row['call_count']) : 0;

        if ($call_count < 1) {
            continue;
        }

        $total_calls += $call_count;
        $labels[] = sprintf(
            _n('%d Person', '%d Personen', $participant_count, 'feuer-einsatzberichte'),
            $participant_count
        );
        $counts[] = $call_count;
    }

    $percentages = [];
    foreach ($counts as $count_value) {
        $percentages[] = $total_calls > 0 ? round(($count_value / $total_calls) * 100, 1) : 0;
    }

    $participant_distribution_chart_data[$period_key] = [
        'label' => $period_label,
        'labels' => $labels,
        'counts' => $counts,
        'percentages' => $percentages,
        'total_calls' => $total_calls,
    ];
}

$max_count = !empty($count_by_date) ? max(array_values($count_by_date)) : 1;
$month_names = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
$days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
if (($jahr % 4 === 0 && $jahr % 100 !== 0) || $jahr % 400 === 0) {
    $days_in_month[1] = 29;
}

$weekday_names = [
    1 => __('Montag', 'feuer-einsatzberichte'),
    2 => __('Dienstag', 'feuer-einsatzberichte'),
    3 => __('Mittwoch', 'feuer-einsatzberichte'),
    4 => __('Donnerstag', 'feuer-einsatzberichte'),
    5 => __('Freitag', 'feuer-einsatzberichte'),
    6 => __('Samstag', 'feuer-einsatzberichte'),
    7 => __('Sonntag', 'feuer-einsatzberichte'),
];
$weekday_counts = array_fill_keys(array_keys($weekday_names), 0);

foreach ($count_by_date as $date_string => $count) {
    $weekday_index = (int) date('N', strtotime($date_string));
    if (isset($weekday_counts[$weekday_index])) {
        $weekday_counts[$weekday_index] += $count;
    }
}

arsort($weekday_counts);
$top_weekdays = [];
foreach (array_slice($weekday_counts, 0, 3, true) as $weekday_index => $weekday_total) {
    if ($weekday_total <= 0) {
        continue;
    }

    $top_weekdays[] = [
        'label' => $weekday_names[$weekday_index],
        'count' => $weekday_total,
    ];
}

$peak_days = [];
$sorted_days = $count_by_date;
arsort($sorted_days);
foreach (array_slice($sorted_days, 0, 5, true) as $date_string => $count) {
    if ($count <= 0) {
        continue;
    }

    $peak_days[] = [
        'date' => date_i18n('d.m.Y', strtotime($date_string)),
        'count' => $count,
    ];
}

$monthly_totals = array_fill(1, 12, 0);
foreach ($count_by_date as $date_string => $count) {
    $month_index = (int) date('n', strtotime($date_string));
    if (isset($monthly_totals[$month_index])) {
        $monthly_totals[$month_index] += (int) $count;
    }
}

$active_day_count = count(array_filter($count_by_date, static function($count) {
    return (int) $count > 0;
}));
$busiest_month_index = 1;
$busiest_month_total = 0;
foreach ($monthly_totals as $month_index => $month_total) {
    if ((int) $month_total > $busiest_month_total) {
        $busiest_month_index = (int) $month_index;
        $busiest_month_total = (int) $month_total;
    }
}
$busiest_month_label = $month_names[$busiest_month_index - 1] ?? '';
$top_category_label = __('Keine Daten', 'feuer-einsatzberichte');
$top_category_total = 0;
if (!empty($categories)) {
    foreach ($categories as $category_entry) {
        if ((int) $category_entry->anzahl > $top_category_total) {
            $top_category_total = (int) $category_entry->anzahl;
            $top_category_label = (string) $category_entry->kategorie_name;
        }
    }
}

$statistics_organization_name = trim((string) get_option('feu_einsatz_photo_watermark_text', get_bloginfo('name')));
if ('' === $statistics_organization_name) {
    $statistics_organization_name = get_bloginfo('name');
}
$statistics_logo_id = absint(get_option('feu_einsatz_area_station_logo_id', 0));
if (!$statistics_logo_id) {
    $statistics_logo_id = absint(get_option('feu_einsatz_social_share_logo_id', 0));
}
$statistics_logo_url = $statistics_logo_id > 0 ? (string) wp_get_attachment_image_url($statistics_logo_id, 'large') : '';
$statistics_station_feature = FEU_Einsatz_Template_Helpers::get_station_feature_from_settings();

$category_total_count = 0;
$category_visual_rows = [];
foreach ((array) $categories as $category_entry) {
    $category_total_count += isset($category_entry->anzahl) ? (int) $category_entry->anzahl : 0;
}

foreach ((array) $categories as $category_entry) {
    $category_count = isset($category_entry->anzahl) ? (int) $category_entry->anzahl : 0;
    $category_percent = $category_total_count > 0 ? round(($category_count / $category_total_count) * 100, 1) : 0;
    $category_visual_rows[] = [
        'name' => isset($category_entry->kategorie_name) ? (string) $category_entry->kategorie_name : __('Ohne Einsatzstichwort', 'feuer-einsatzberichte'),
        'count' => $category_count,
        'percent' => $category_percent,
        'color' => sanitize_hex_color((string) ($category_entry->farbe ?? '')) ?: '#0a4b78',
    ];
}

$participant_ranking_max = !empty($participants)
    ? max(array_map(static function($participant) {
        return isset($participant->gesamt_einsaetze) ? (int) $participant->gesamt_einsaetze : 0;
    }, $participants))
    : 0;

$activity_map_svg = '';

if (!empty($activity_map_points)) {
    $activity_map_distance = static function(array $point_a, array $point_b) {
        $earth_radius = 6371000;
        $delta_lat = deg2rad((float) $point_b['latitude'] - (float) $point_a['latitude']);
        $delta_lng = deg2rad((float) $point_b['longitude'] - (float) $point_a['longitude']);
        $lat1 = deg2rad((float) $point_a['latitude']);
        $lat2 = deg2rad((float) $point_b['latitude']);
        $distance = sin($delta_lat / 2) * sin($delta_lat / 2)
            + cos($lat1) * cos($lat2) * sin($delta_lng / 2) * sin($delta_lng / 2);

        return $earth_radius * 2 * atan2(sqrt($distance), sqrt(1 - $distance));
    };

    $activity_map_clusters = [];

    foreach ($activity_map_points as $activity_point) {
        if (
            !isset($activity_point['latitude'], $activity_point['longitude'])
            || !is_numeric($activity_point['latitude'])
            || !is_numeric($activity_point['longitude'])
        ) {
            continue;
        }

        $matched_cluster_index = null;

        foreach ($activity_map_clusters as $cluster_index => $cluster) {
            if ($activity_map_distance($cluster['center'], $activity_point) <= 350) {
                $matched_cluster_index = $cluster_index;
                break;
            }
        }

        if (null === $matched_cluster_index) {
            $activity_map_clusters[] = [
                'center' => [
                    'latitude' => (float) $activity_point['latitude'],
                    'longitude' => (float) $activity_point['longitude'],
                ],
                'count' => 1,
                'points' => [$activity_point],
            ];
            continue;
        }

        $activity_map_clusters[$matched_cluster_index]['points'][] = $activity_point;
        $activity_map_clusters[$matched_cluster_index]['count'] += 1;

        $lat_sum = 0.0;
        $lng_sum = 0.0;

        foreach ($activity_map_clusters[$matched_cluster_index]['points'] as $cluster_point) {
            $lat_sum += (float) $cluster_point['latitude'];
            $lng_sum += (float) $cluster_point['longitude'];
        }

        $point_count = count($activity_map_clusters[$matched_cluster_index]['points']);

        $activity_map_clusters[$matched_cluster_index]['center']['latitude'] = $lat_sum / $point_count;
        $activity_map_clusters[$matched_cluster_index]['center']['longitude'] = $lng_sum / $point_count;
    }

    if (!empty($activity_map_clusters)) {
        $cluster_latitudes = array_map(static function($cluster) {
            return (float) $cluster['center']['latitude'];
        }, $activity_map_clusters);
        $cluster_longitudes = array_map(static function($cluster) {
            return (float) $cluster['center']['longitude'];
        }, $activity_map_clusters);
        $min_lat = min($cluster_latitudes);
        $max_lat = max($cluster_latitudes);
        $min_lng = min($cluster_longitudes);
        $max_lng = max($cluster_longitudes);
        $lat_range = max($max_lat - $min_lat, 0.01);
        $lng_range = max($max_lng - $min_lng, 0.01);
        $max_cluster_count = max(array_map(static function($cluster) {
            return (int) $cluster['count'];
        }, $activity_map_clusters));

        $activity_map_primary_label = static function(array $cluster) {
            $labels = [];

            foreach ($cluster['points'] as $point) {
                $label = '';

                if (!empty($point['street'])) {
                    $label = sanitize_text_field((string) $point['street']);
                } elseif (!empty($point['city'])) {
                    $label = sanitize_text_field((string) $point['city']);
                }

                if ('' === $label) {
                    $label = 'Aktivitaetszone';
                }

                if (!isset($labels[$label])) {
                    $labels[$label] = 0;
                }

                $labels[$label]++;
            }

            arsort($labels);

            return key($labels) ?: 'Aktivitaetszone';
        };

        ob_start();
        ?>
        <svg class="feu-einsatz-activity-map-svg" viewBox="0 0 1600 920" role="img" aria-labelledby="ebActivityMapTitle ebActivityMapDesc" preserveAspectRatio="xMidYMid meet">
            <title id="ebActivityMapTitle"><?php echo esc_html(sprintf(__('Aktivitätskarte %s', 'feuer-einsatzberichte'), $jahr)); ?></title>
            <desc id="ebActivityMapDesc"><?php echo esc_html__('Visualisierung der Aktivitaetszonen aus gespeicherten Einsatzkoordinaten.', 'feuer-einsatzberichte'); ?></desc>
            <defs>
                <linearGradient id="ebActivityGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#f8fbff"></stop>
                    <stop offset="100%" stop-color="#edf4fb"></stop>
                </linearGradient>
            </defs>
            <rect width="1600" height="920" fill="url(#ebActivityGradient)"></rect>
            <rect x="72" y="170" width="1456" height="620" rx="28" fill="#ffffff" stroke="#cbd5e1" stroke-width="3"></rect>
            <text x="72" y="84" fill="#0f172a" font-size="44" font-family="Arial, sans-serif" font-weight="700">Aktivitätskarte</text>
            <text x="72" y="122" fill="#475569" font-size="24" font-family="Arial, sans-serif">Zonen mit hoechster Einsatzdichte im Jahr <?php echo esc_html($jahr); ?></text>
            <?php for ($grid_x = 0; $grid_x <= 5; $grid_x++) : ?>
                <?php $line_x = 72 + ((1456 / 5) * $grid_x); ?>
                <line x1="<?php echo esc_attr(number_format($line_x, 2, '.', '')); ?>" y1="170" x2="<?php echo esc_attr(number_format($line_x, 2, '.', '')); ?>" y2="790" stroke="rgba(148,163,184,0.22)" stroke-width="1"></line>
            <?php endfor; ?>
            <?php for ($grid_y = 0; $grid_y <= 4; $grid_y++) : ?>
                <?php $line_y = 170 + ((620 / 4) * $grid_y); ?>
                <line x1="72" y1="<?php echo esc_attr(number_format($line_y, 2, '.', '')); ?>" x2="1528" y2="<?php echo esc_attr(number_format($line_y, 2, '.', '')); ?>" stroke="rgba(148,163,184,0.22)" stroke-width="1"></line>
            <?php endfor; ?>
            <?php foreach ($activity_map_clusters as $cluster) : ?>
                <?php
                $relative_x = ((float) $cluster['center']['longitude'] - $min_lng) / $lng_range;
                $relative_y = ((float) $cluster['center']['latitude'] - $min_lat) / $lat_range;
                $x = 72 + 70 + ($relative_x * (1456 - 140));
                $y = 170 + 620 - 70 - ($relative_y * (620 - 140));
                $intensity = $max_cluster_count > 0 ? ((int) $cluster['count'] / $max_cluster_count) : 0;
                $radius = 26 + ((int) $cluster['count'] * 10);
                $fill = $intensity > 0.66 ? 'rgba(197,48,48,0.30)' : ($intensity > 0.33 ? 'rgba(214,158,46,0.30)' : 'rgba(10,75,120,0.30)');
                $stroke = $intensity > 0.66 ? '#c53030' : ($intensity > 0.33 ? '#d69e2e' : '#0a4b78');
                $label = wp_html_excerpt($activity_map_primary_label($cluster), 26, '...');
                $city_label = !empty($cluster['points'][0]['city'])
                    ? wp_html_excerpt(sanitize_text_field((string) $cluster['points'][0]['city']), 22, '...')
                    : '';
                ?>
                <circle cx="<?php echo esc_attr(number_format($x, 2, '.', '')); ?>" cy="<?php echo esc_attr(number_format($y, 2, '.', '')); ?>" r="<?php echo esc_attr((string) $radius); ?>" fill="<?php echo esc_attr($fill); ?>" stroke="<?php echo esc_attr($stroke); ?>" stroke-width="5"></circle>
                <circle cx="<?php echo esc_attr(number_format($x, 2, '.', '')); ?>" cy="<?php echo esc_attr(number_format($y, 2, '.', '')); ?>" r="<?php echo esc_attr(number_format(max(8, $radius * 0.22), 2, '.', '')); ?>" fill="#ffffff"></circle>
                <text x="<?php echo esc_attr(number_format($x, 2, '.', '')); ?>" y="<?php echo esc_attr(number_format($y + 8, 2, '.', '')); ?>" fill="#0f172a" font-size="24" font-family="Arial, sans-serif" font-weight="700" text-anchor="middle"><?php echo esc_html((string) $cluster['count']); ?></text>
                <text x="<?php echo esc_attr(number_format($x + $radius + 16, 2, '.', '')); ?>" y="<?php echo esc_attr(number_format($y - 4, 2, '.', '')); ?>" fill="#0f172a" font-size="20" font-family="Arial, sans-serif" font-weight="600"><?php echo esc_html($label); ?></text>
                <?php if ('' !== $city_label) : ?>
                    <text x="<?php echo esc_attr(number_format($x + $radius + 16, 2, '.', '')); ?>" y="<?php echo esc_attr(number_format($y + 24, 2, '.', '')); ?>" fill="#64748b" font-size="18" font-family="Arial, sans-serif"><?php echo esc_html($city_label); ?></text>
                <?php endif; ?>
            <?php endforeach; ?>
            <text x="72" y="872" fill="#334155" font-size="20" font-family="Arial, sans-serif">Quelle: Einsatzkoordinaten aus den gespeicherten Einsatzberichten</text>
        </svg>
        <?php
        $activity_map_svg = (string) ob_get_clean();
    }
}

if (!function_exists('feu_einsatz_render_statistics_presentation')) {
    function feu_einsatz_render_statistics_presentation(array $args) {
        $presentation_settings = is_array($args['presentation_settings'] ?? null) ? $args['presentation_settings'] : [];
        $is_presentation_enabled = static function($key) use ($presentation_settings) {
            return in_array((string) $key, $presentation_settings, true);
        };
        $year = (int) ($args['year'] ?? date('Y'));
        $organization_name = trim((string) ($args['organization_name'] ?? ''));
        $logo_url = (string) ($args['logo_url'] ?? '');
        $logo_size = max(20, min(140, (int) ($args['logo_size'] ?? 72)));
        $background_url = (string) ($args['background_url'] ?? '');
        $background_opacity = max(0, min(100, (int) ($args['background_opacity'] ?? 28))) / 100;
        $background_darkness = max(0, min(100, (int) ($args['background_darkness'] ?? 18))) / 100;
        $background_pages = is_array($args['background_pages'] ?? null) ? $args['background_pages'] : [];
        $total_stats = is_array($args['total_stats'] ?? null) ? $args['total_stats'] : [];
        $category_rows = is_array($args['category_rows'] ?? null) ? $args['category_rows'] : [];
        $top_weekdays = is_array($args['top_weekdays'] ?? null) ? $args['top_weekdays'] : [];
        $peak_days = is_array($args['peak_days'] ?? null) ? $args['peak_days'] : [];
        $monthly_totals = is_array($args['monthly_totals'] ?? null) ? $args['monthly_totals'] : [];
        $month_names = is_array($args['month_names'] ?? null) ? $args['month_names'] : [];
        $participants = is_array($args['participants'] ?? null) ? $args['participants'] : [];
        $custom_pages = is_array($args['custom_pages'] ?? null) ? $args['custom_pages'] : [];
        $ranking_unlocked = !empty($args['ranking_unlocked']);
        $activity_map_svg = (string) ($args['activity_map_svg'] ?? '');
        $station_feature = is_array($args['station_feature'] ?? null) ? $args['station_feature'] : [];
        $top_category_label = (string) ($args['top_category_label'] ?? __('Keine Daten', 'feuer-einsatzberichte'));
        $busiest_month_label = (string) ($args['busiest_month_label'] ?? '');
        $total_calls = (int) ($total_stats['total_einsaetze'] ?? 0);
        $participant_count = count($participants);
        $top_three = array_slice($participants, 0, 3);
        $max_month_total = !empty($monthly_totals) ? max(array_map('intval', $monthly_totals)) : 0;
        $top_months = [];
        foreach ($monthly_totals as $month_index => $month_total) {
            $top_months[] = [
                'label' => (string) ($month_names[$month_index - 1] ?? ''),
                'count' => (int) $month_total,
            ];
        }
        usort($top_months, static function($a, $b) {
            return (int) $b['count'] <=> (int) $a['count'];
        });
        $top_months = array_slice($top_months, 0, 3);
        $generated_label = sprintf(
            /* translators: 1: year, 2: time */
            __('Live-Daten %1$s · aktualisiert %2$s', 'feuer-einsatzberichte'),
            (string) $year,
            date_i18n('H:i')
        );
        $slide_number = 0;
        $render_slide_background = static function($page_key) use ($background_url, $background_opacity, $background_darkness, $background_pages) {
            if ('' === $background_url || !in_array((string) $page_key, $background_pages, true)) {
                return;
            }
            ?>
            <div class="feu-einsatz-presentation-slide-background" aria-hidden="true">
                <span style="background-image: url('<?php echo esc_url($background_url); ?>'); opacity: <?php echo esc_attr(number_format($background_opacity, 2, '.', '')); ?>;"></span>
                <i style="opacity: <?php echo esc_attr(number_format($background_darkness, 2, '.', '')); ?>;"></i>
            </div>
            <?php
        };
        ?>
        <div class="feu-einsatz-statistics-presentation-deck" id="feu-einsatz-statistics-presentation-deck" hidden aria-modal="true" role="dialog" aria-label="<?php esc_attr_e('Statistik-Präsentation', 'feuer-einsatzberichte'); ?>" style="--feu-presentation-logo-size: <?php echo esc_attr((string) $logo_size); ?>%;">
            <div class="feu-einsatz-presentation-live-badge">
                <span class="ti ti-activity"></span>
                <?php echo esc_html($generated_label); ?>
            </div>
            <div class="feu-einsatz-presentation-stage">
                <?php if ($is_presentation_enabled('welcome')): ?>
                <?php $slide_number++; ?>
                <section class="feu-einsatz-presentation-slide is-active" data-feu-presentation-slide="<?php echo esc_attr((string) $slide_number); ?>">
                    <?php $render_slide_background('welcome'); ?>
                    <div class="feu-einsatz-presentation-welcome">
                        <div class="feu-einsatz-presentation-welcome-copy">
                            <span><i class="ti ti-chart-infographic" aria-hidden="true"></i><?php esc_html_e('Willkommen bei den Statistiken', 'feuer-einsatzberichte'); ?></span>
                            <h2><?php echo esc_html($organization_name); ?></h2>
                            <strong><?php echo esc_html((string) $year); ?></strong>
                        </div>
                        <div class="feu-einsatz-presentation-welcome-logo">
                            <?php if ('' !== $logo_url): ?>
                                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($organization_name); ?>">
                            <?php else: ?>
                                <span class="ti ti-shield-half-filled" aria-hidden="true"></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($is_presentation_enabled('overview')): ?>
                <?php $slide_number++; ?>
                <section class="feu-einsatz-presentation-slide<?php echo 1 === $slide_number ? ' is-active' : ''; ?>" data-feu-presentation-slide="<?php echo esc_attr((string) $slide_number); ?>">
                    <?php $render_slide_background('overview'); ?>
                    <h2><span class="ti ti-dashboard" aria-hidden="true"></span><?php esc_html_e('Übersicht', 'feuer-einsatzberichte'); ?></h2>
                    <div class="feu-einsatz-presentation-kpis">
                        <div><span><?php esc_html_e('Einsätze', 'feuer-einsatzberichte'); ?></span><strong><?php echo esc_html((string) $total_calls); ?></strong></div>
                        <div class="feu-einsatz-presentation-list-kpi">
                            <span><?php esc_html_e('Stärkster Monat', 'feuer-einsatzberichte'); ?></span>
                            <?php foreach ($top_months as $top_month): ?>
                                <p><strong><?php echo esc_html($top_month['label'] ?: '-'); ?></strong><em><?php echo esc_html(sprintf(__('mit %d Einsätze', 'feuer-einsatzberichte'), (int) $top_month['count'])); ?></em></p>
                            <?php endforeach; ?>
                        </div>
                        <div><span><?php esc_html_e('Top-Einsatzstichwort', 'feuer-einsatzberichte'); ?></span><strong><?php echo esc_html($top_category_label); ?></strong></div>
                    </div>
                    <div class="feu-einsatz-presentation-overview-lists">
                        <div>
                            <h3><?php esc_html_e('Häufigste Wochentage', 'feuer-einsatzberichte'); ?></h3>
                            <?php foreach (array_slice($top_weekdays, 0, 3) as $weekday): ?>
                                <p><span><?php echo esc_html((string) ($weekday['label'] ?? '')); ?></span><strong><?php echo esc_html((string) ($weekday['count'] ?? 0)); ?></strong></p>
                            <?php endforeach; ?>
                        </div>
                        <div>
                            <h3><?php esc_html_e('Spitzen-Tage', 'feuer-einsatzberichte'); ?></h3>
                            <?php foreach (array_slice($peak_days, 0, 3) as $peak_day): ?>
                                <p><span><?php echo esc_html((string) ($peak_day['date'] ?? '')); ?></span><strong><?php echo esc_html((string) ($peak_day['count'] ?? 0)); ?></strong></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($is_presentation_enabled('categories')): ?>
                <?php $slide_number++; ?>
                <section class="feu-einsatz-presentation-slide<?php echo 1 === $slide_number ? ' is-active' : ''; ?>" data-feu-presentation-slide="<?php echo esc_attr((string) $slide_number); ?>">
                    <?php $render_slide_background('categories'); ?>
                    <h2><span class="ti ti-category" aria-hidden="true"></span><?php esc_html_e('Einsatzstichworte', 'feuer-einsatzberichte'); ?></h2>
                    <div class="feu-einsatz-presentation-category-grid">
                        <?php foreach ($category_rows as $category_row): ?>
                            <div class="feu-einsatz-presentation-category-row" title="<?php echo esc_attr(sprintf('%s: %d Einsätze (%s%%)', $category_row['name'], (int) $category_row['count'], number_format_i18n((float) $category_row['percent'], 1))); ?>" style="--feu-category-color: <?php echo esc_attr($category_row['color']); ?>; --feu-category-percent: <?php echo esc_attr(number_format((float) $category_row['percent'], 2, '.', '')); ?>%;">
                                <span><?php echo esc_html($category_row['name']); ?></span>
                                <div aria-hidden="true"><em></em></div>
                                <strong><?php echo esc_html((string) $category_row['count']); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($is_presentation_enabled('calendar')): ?>
                <?php $slide_number++; ?>
                <section class="feu-einsatz-presentation-slide<?php echo 1 === $slide_number ? ' is-active' : ''; ?>" data-feu-presentation-slide="<?php echo esc_attr((string) $slide_number); ?>">
                    <?php $render_slide_background('calendar'); ?>
                    <h2><span class="ti ti-calendar-stats" aria-hidden="true"></span><?php esc_html_e('Kalender', 'feuer-einsatzberichte'); ?></h2>
                    <div class="feu-einsatz-presentation-calendar-grid">
                        <div>
                            <h3><?php esc_html_e('Beliebte Wochentage', 'feuer-einsatzberichte'); ?></h3>
                            <?php foreach ($top_weekdays as $weekday): ?>
                                <p><span><?php echo esc_html((string) ($weekday['label'] ?? '')); ?></span><strong><?php echo esc_html((string) ($weekday['count'] ?? 0)); ?></strong></p>
                            <?php endforeach; ?>
                        </div>
                        <div>
                            <h3><?php esc_html_e('Stärkste Tage', 'feuer-einsatzberichte'); ?></h3>
                            <?php foreach ($peak_days as $peak_day): ?>
                                <p><span><?php echo esc_html((string) ($peak_day['date'] ?? '')); ?></span><strong><?php echo esc_html((string) ($peak_day['count'] ?? 0)); ?></strong></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="feu-einsatz-presentation-months">
                        <?php foreach ($monthly_totals as $month_index => $month_total): ?>
                            <?php $month_percent = $max_month_total > 0 ? (((int) $month_total / $max_month_total) * 100) : 0; ?>
                            <?php $month_label = (string) ($month_names[$month_index - 1] ?? ''); ?>
                            <?php $month_short = 3 === $month_index ? 'Mär' : substr($month_label, 0, 3); ?>
                            <span title="<?php echo esc_attr($month_label . ': ' . (int) $month_total . ' Einsätze'); ?>" data-feu-stat-tooltip="<?php echo esc_attr($month_label . ': ' . (int) $month_total . ' Einsätze'); ?>" style="--feu-month-height: <?php echo esc_attr(number_format(max(6, $month_percent), 2, '.', '')); ?>%;"><b><?php echo esc_html((string) (int) $month_total); ?></b><em></em><strong><?php echo esc_html($month_short); ?></strong></span>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($is_presentation_enabled('activity_map')): ?>
                <?php $slide_number++; ?>
                <section class="feu-einsatz-presentation-slide<?php echo 1 === $slide_number ? ' is-active' : ''; ?>" data-feu-presentation-slide="<?php echo esc_attr((string) $slide_number); ?>" data-feu-presentation-kind="activity-map">
                    <?php $render_slide_background('activity_map'); ?>
                    <h2><span class="ti ti-map-2" aria-hidden="true"></span><?php esc_html_e('Aktivitätskarte', 'feuer-einsatzberichte'); ?></h2>
                    <div class="feu-einsatz-presentation-map feu-einsatz-presentation-map--live" id="feu-einsatz-presentation-activity-map">
                        <div class="feu-einsatz-presentation-activity-live-map" id="feu-einsatz-presentation-activity-live-map"></div>
                        <div class="feu-einsatz-presentation-map-fallback">
                            <?php echo $activity_map_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($is_presentation_enabled('participants')): ?>
                <?php $slide_number++; ?>
                <section class="feu-einsatz-presentation-slide<?php echo 1 === $slide_number ? ' is-active' : ''; ?>" data-feu-presentation-slide="<?php echo esc_attr((string) $slide_number); ?>">
                    <?php $render_slide_background('participants'); ?>
                    <h2><span class="ti ti-users-group" aria-hidden="true"></span><?php echo esc_html(sprintf(__('Teilnehmer gesamt %d', 'feuer-einsatzberichte'), $participant_count)); ?></h2>
                    <div class="feu-einsatz-presentation-participant-summary">
                        <?php if (!empty($top_three)): ?>
                            <div class="feu-einsatz-presentation-winners feu-einsatz-presentation-podium">
                                <?php foreach ([1, 0, 2] as $winner_index): ?>
                                    <?php if (!isset($top_three[$winner_index])) { continue; } ?>
                                    <?php $participant = $top_three[$winner_index]; ?>
                                    <?php $rank = $winner_index + 1; ?>
                                    <p class="feu-einsatz-presentation-podium-rank-<?php echo esc_attr((string) $rank); ?>">
                                        <span class="feu-einsatz-presentation-podium-trophy"><i class="ti ti-trophy" aria-hidden="true"></i></span>
                                        <strong><?php echo esc_html(sprintf(__('%d. Platz', 'feuer-einsatzberichte'), $rank)); ?></strong>
                                        <span><?php echo esc_html(FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname)); ?></span>
                                        <em><?php echo esc_html((string) (int) $participant->gesamt_einsaetze); ?> <?php esc_html_e('Teilnahmen', 'feuer-einsatzberichte'); ?></em>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (count($participants) > 3): ?>
                        <div class="feu-einsatz-presentation-participant-list">
                            <?php foreach (array_slice($participants, 3) as $participant_index => $participant): ?>
                                <p>
                                    <span><?php echo esc_html((string) ($participant_index + 4) . '. ' . FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname)); ?></span>
                                    <strong><?php echo esc_html((string) (int) $participant->gesamt_einsaetze); ?></strong>
                                </p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <?php foreach ($custom_pages as $custom_page_index => $custom_page): ?>
                    <?php if (empty($custom_page['enabled'])) { continue; } ?>
                    <?php $slide_number++; ?>
                    <?php $custom_page_key = 'custom_' . (string) $custom_page_index; ?>
                    <section class="feu-einsatz-presentation-slide<?php echo 1 === $slide_number ? ' is-active' : ''; ?>" data-feu-presentation-slide="<?php echo esc_attr((string) $slide_number); ?>">
                        <?php $render_slide_background($custom_page_key); ?>
                        <h2><span class="ti ti-file-text" aria-hidden="true"></span><?php echo esc_html((string) ($custom_page['title'] ?? __('Eigene Seite', 'feuer-einsatzberichte'))); ?></h2>
                        <?php if (!empty($custom_page['subtitle'])): ?>
                            <p class="feu-einsatz-presentation-subtitle"><?php echo esc_html((string) $custom_page['subtitle']); ?></p>
                        <?php endif; ?>
                        <div class="feu-einsatz-presentation-custom-content">
                            <?php echo wp_kses_post((string) ($custom_page['content'] ?? '')); ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
            <div class="feu-einsatz-presentation-controls">
                <button type="button" class="button" data-feu-presentation-prev><?php esc_html_e('Zurück', 'feuer-einsatzberichte'); ?></button>
                <span><strong data-feu-presentation-current>1</strong> / <em data-feu-presentation-total><?php echo esc_html((string) max(1, $slide_number)); ?></em></span>
                <button type="button" class="button button-primary" data-feu-presentation-next><?php esc_html_e('Weiter', 'feuer-einsatzberichte'); ?></button>
                <button type="button" class="button" data-feu-presentation-close><?php esc_html_e('Schließen', 'feuer-einsatzberichte'); ?></button>
            </div>
        </div>
        <?php
    }
}
?>

<div class="wrap feu-einsatz-statistics feu-admin-page">
    <div class="feu-admin-page-header">
        <div class="feu-admin-page-heading">
            <span class="feu-admin-page-eyebrow"><?php esc_html_e('Analyse & Visualisierung', 'feuer-einsatzberichte'); ?></span>
            <h1><?php echo esc_html(sprintf(__('Statistiken %s', 'feuer-einsatzberichte'), $jahr)); ?></h1>
            <p><?php esc_html_e('Umschaltbare Diagrammtypen, Kalenderansicht, Aktivitätskarte und geschütztes Teilnehmer-Ranking in einer modernen Admin-Struktur.', 'feuer-einsatzberichte'); ?></p>
        </div>
        <div class="feu-admin-page-actions">
            <button type="button" class="button button-secondary" id="feu-einsatz-statistics-presentation-toggle">
                <span class="ti ti-presentation"></span>
                <?php esc_html_e('Präsentation', 'feuer-einsatzberichte'); ?>
            </button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=feu-einsatz-einstellungen')); ?>" class="button button-secondary">
                <span class="ti ti-adjustments-horizontal"></span>
                <?php esc_html_e('Einstellungen', 'feuer-einsatzberichte'); ?>
            </a>
            <button type="button" class="button button-primary" id="feu-einsatz-export-statistics-pdf">
                <span class="ti ti-file-download"></span>
                <?php esc_html_e('PDF herunterladen', 'feuer-einsatzberichte'); ?>
            </button>
        </div>
    </div>

    <?php if (!empty($participant_ranking_notice)): ?>
        <div class="notice notice-<?php echo esc_attr($participant_ranking_notice['type']); ?> is-dismissible">
            <p><?php echo esc_html($participant_ranking_notice['message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($presentation_settings_notice)): ?>
        <div class="notice notice-<?php echo esc_attr($presentation_settings_notice['type']); ?> is-dismissible">
            <p><?php echo esc_html($presentation_settings_notice['message']); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="" class="feu-einsatz-statistics-toolbar" id="feu-einsatz-statistics-toolbar">
        <div class="feu-einsatz-toolbar-field">
            <label for="jahr-select"><?php esc_html_e('Jahr:', 'feuer-einsatzberichte'); ?></label>
            <select name="jahr" id="jahr-select">
                <?php foreach ($jahre as $j): ?>
                    <option value="<?php echo esc_attr($j); ?>" <?php selected($j, $jahr); ?>><?php echo esc_html($j); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="feu_einsatz_stats_tab" value="<?php echo esc_attr($active_tab); ?>" class="feu-einsatz-stats-tab-input">
        <input type="submit" value="<?php esc_attr_e('Aktualisieren', 'feuer-einsatzberichte'); ?>" class="button button-primary">
    </form>

    <div class="stats-cards feu-einsatz-stat-visual-grid" id="feu-einsatz-statistics-summary-cards">
        <div class="stat-card feu-einsatz-stat-visual-card is-primary" style="--feu-stat-progress: <?php echo esc_attr((string) min(100, max(0, (int) ($total['total_einsaetze'] ?? 0)))); ?>%;">
            <div class="feu-einsatz-stat-card-top">
                <span class="feu-einsatz-stat-icon ti ti-flame"></span>
                <span class="stat-label"><?php esc_html_e('Einsätze', 'feuer-einsatzberichte'); ?></span>
            </div>
            <div class="stat-value" data-feu-count-up="<?php echo esc_attr((string) (int) ($total['total_einsaetze'] ?? 0)); ?>"><?php echo esc_html($total['total_einsaetze']); ?></div>
            <div class="feu-einsatz-stat-meter" aria-hidden="true"><span></span></div>
        </div>
        <div class="stat-card feu-einsatz-stat-visual-card is-people" style="--feu-stat-progress: <?php echo esc_attr((string) min(100, max(0, (int) ($total['total_teilnehmer_alle'] ?? 0)))); ?>%;">
            <div class="feu-einsatz-stat-card-top">
                <span class="feu-einsatz-stat-icon ti ti-users"></span>
                <span class="stat-label"><?php esc_html_e('Teilnehmer gesamt', 'feuer-einsatzberichte'); ?></span>
            </div>
            <div class="stat-value" data-feu-count-up="<?php echo esc_attr((string) (int) ($total['total_teilnehmer_alle'] ?? 0)); ?>"><?php echo esc_html($total['total_teilnehmer_alle']); ?></div>
            <div class="feu-einsatz-stat-meter" aria-hidden="true"><span></span></div>
        </div>
        <div class="stat-card feu-einsatz-stat-visual-card is-average" style="--feu-stat-progress: <?php echo esc_attr((string) min(100, max(0, ((float) ($total['avg_teilnehmer'] ?? 0)) * 10))); ?>%;">
            <div class="feu-einsatz-stat-card-top">
                <span class="feu-einsatz-stat-icon ti ti-chart-arcs"></span>
                <span class="stat-label"><?php esc_html_e('Durchschnitt pro Einsatz', 'feuer-einsatzberichte'); ?></span>
            </div>
            <div class="stat-value" data-feu-count-up="<?php echo esc_attr((string) (float) ($total['avg_teilnehmer'] ?? 0)); ?>" data-feu-count-decimals="1"><?php echo esc_html($total['avg_teilnehmer']); ?></div>
            <div class="feu-einsatz-stat-meter" aria-hidden="true"><span></span></div>
        </div>
        <div class="stat-card feu-einsatz-stat-visual-card is-days" style="--feu-stat-progress: <?php echo esc_attr((string) min(100, max(0, ($active_day_count / 366) * 100))); ?>%;">
            <div class="feu-einsatz-stat-card-top">
                <span class="feu-einsatz-stat-icon ti ti-calendar-stats"></span>
                <span class="stat-label"><?php esc_html_e('Aktive Tage', 'feuer-einsatzberichte'); ?></span>
            </div>
            <div class="stat-value" data-feu-count-up="<?php echo esc_attr((string) $active_day_count); ?>"><?php echo esc_html($active_day_count); ?></div>
            <div class="feu-einsatz-stat-meter" aria-hidden="true"><span></span></div>
        </div>
    </div>

    <section class="feu-einsatz-command-visual" aria-label="<?php esc_attr_e('Operativer Statistiküberblick', 'feuer-einsatzberichte'); ?>">
        <div class="feu-einsatz-command-radar" aria-hidden="true">
            <span class="feu-einsatz-command-radar-ring"></span>
            <span class="feu-einsatz-command-radar-ring"></span>
            <span class="feu-einsatz-command-radar-ring"></span>
            <strong data-feu-count-up="<?php echo esc_attr((string) (int) ($total['total_einsaetze'] ?? 0)); ?>"><?php echo esc_html((int) ($total['total_einsaetze'] ?? 0)); ?></strong>
        </div>
        <div class="feu-einsatz-command-main">
            <span class="feu-einsatz-command-eyebrow"><?php esc_html_e('Live-Visualisierung', 'feuer-einsatzberichte'); ?></span>
            <h2><?php esc_html_e('Statistiklage im Admin-Dashboard', 'feuer-einsatzberichte'); ?></h2>
            <div class="feu-einsatz-command-metrics">
                <span><strong><?php echo esc_html($busiest_month_label ?: '-'); ?></strong><?php esc_html_e('stärkster Monat', 'feuer-einsatzberichte'); ?></span>
                <span><strong><?php echo esc_html($top_category_label); ?></strong><?php esc_html_e('Top-Einsatzstichwort', 'feuer-einsatzberichte'); ?></span>
            </div>
        </div>
        <div class="feu-einsatz-command-months" aria-label="<?php esc_attr_e('Monatsverteilung', 'feuer-einsatzberichte'); ?>">
            <?php foreach ($monthly_totals as $month_index => $month_total): ?>
                <?php $month_height = $busiest_month_total > 0 ? max(8, ((int) $month_total / $busiest_month_total) * 100) : 8; ?>
                <span class="feu-einsatz-command-month" style="--feu-month-height: <?php echo esc_attr(number_format($month_height, 2, '.', '')); ?>%;" title="<?php echo esc_attr(($month_names[$month_index - 1] ?? '') . ': ' . (int) $month_total); ?>">
                    <span></span>
                    <em><?php echo esc_html(substr($month_names[$month_index - 1] ?? '', 0, 1)); ?></em>
                </span>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="feu-einsatz-admindek-visuals" aria-label="<?php esc_attr_e('Grafische Statistikübersicht', 'feuer-einsatzberichte'); ?>">
        <div class="feu-einsatz-admindek-card feu-einsatz-admindek-card--wide">
            <div class="feu-einsatz-admindek-card-head">
                <div>
                    <span><i class="ti ti-chart-line"></i><?php esc_html_e('Monatsverlauf', 'feuer-einsatzberichte'); ?></span>
                    <h2><?php esc_html_e('Einsätze pro Monat', 'feuer-einsatzberichte'); ?></h2>
                </div>
                <strong><?php echo esc_html((string) (int) ($total['total_einsaetze'] ?? 0)); ?></strong>
            </div>
            <div class="feu-einsatz-admindek-chart-wrap">
                <canvas id="feu-einsatz-admindek-monthly-chart" height="120"></canvas>
            </div>
        </div>

        <div class="feu-einsatz-admindek-card">
            <div class="feu-einsatz-admindek-card-head">
                <div>
                    <span><i class="ti ti-chart-donut"></i><?php esc_html_e('Einsatzstichworte', 'feuer-einsatzberichte'); ?></span>
                    <h2><?php esc_html_e('Verteilung', 'feuer-einsatzberichte'); ?></h2>
                </div>
            </div>
            <div class="feu-einsatz-admindek-donut-wrap">
                <canvas id="feu-einsatz-admindek-category-chart" height="180"></canvas>
            </div>
        </div>

        <div class="feu-einsatz-admindek-card">
            <div class="feu-einsatz-admindek-card-head">
                <div>
                    <span><i class="ti ti-calendar-week"></i><?php esc_html_e('Häufigste Wochentage', 'feuer-einsatzberichte'); ?></span>
                    <h2><?php esc_html_e('Top Wochentage', 'feuer-einsatzberichte'); ?></h2>
                </div>
            </div>
            <div class="feu-einsatz-admindek-progress-list">
                <?php $top_weekday_max = !empty($top_weekdays) ? max(array_map(static fn($entry) => (int) $entry['count'], $top_weekdays)) : 0; ?>
                <?php foreach ($top_weekdays as $weekday_entry): ?>
                    <?php $weekday_percent = $top_weekday_max > 0 ? (((int) $weekday_entry['count'] / $top_weekday_max) * 100) : 0; ?>
                    <div class="feu-einsatz-admindek-progress-row" title="<?php echo esc_attr((string) $weekday_entry['label'] . ': ' . (int) $weekday_entry['count'] . ' Einsätze'); ?>">
                        <p><span><?php echo esc_html((string) $weekday_entry['label']); ?></span><strong><?php echo esc_html((string) (int) $weekday_entry['count']); ?></strong></p>
                        <em><i style="width: <?php echo esc_attr(number_format(max(4, $weekday_percent), 2, '.', '')); ?>%;"></i></em>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="feu-einsatz-admindek-card">
            <div class="feu-einsatz-admindek-card-head">
                <div>
                    <span><i class="ti ti-calendar-star"></i><?php esc_html_e('Spitzen-Tage', 'feuer-einsatzberichte'); ?></span>
                    <h2><?php esc_html_e('Höchste Tageswerte', 'feuer-einsatzberichte'); ?></h2>
                </div>
            </div>
            <div class="feu-einsatz-admindek-progress-list">
                <?php $peak_day_max = !empty($peak_days) ? max(array_map(static fn($entry) => (int) $entry['count'], $peak_days)) : 0; ?>
                <?php foreach (array_slice($peak_days, 0, 4) as $peak_day): ?>
                    <?php $peak_percent = $peak_day_max > 0 ? (((int) $peak_day['count'] / $peak_day_max) * 100) : 0; ?>
                    <div class="feu-einsatz-admindek-progress-row" title="<?php echo esc_attr((string) $peak_day['date'] . ': ' . (int) $peak_day['count'] . ' Einsätze'); ?>">
                        <p><span><?php echo esc_html((string) $peak_day['date']); ?></span><strong><?php echo esc_html((string) (int) $peak_day['count']); ?></strong></p>
                        <em><i style="width: <?php echo esc_attr(number_format(max(4, $peak_percent), 2, '.', '')); ?>%;"></i></em>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <div class="feu-einsatz-stats-tabs" role="tablist" aria-label="<?php esc_attr_e('Statistikbereiche', 'feuer-einsatzberichte'); ?>">
        <button type="button" class="button feu-einsatz-stats-tab <?php echo 'categories' === $active_tab ? 'is-active' : ''; ?>" data-target="categories"><?php esc_html_e('Einsätze nach Einsatzstichwort', 'feuer-einsatzberichte'); ?></button>
        <button type="button" class="button feu-einsatz-stats-tab <?php echo 'calendar' === $active_tab ? 'is-active' : ''; ?>" data-target="calendar"><?php esc_html_e('Kalender', 'feuer-einsatzberichte'); ?></button>
        <button type="button" class="button feu-einsatz-stats-tab <?php echo 'activity-map' === $active_tab ? 'is-active' : ''; ?>" data-target="activity-map"><?php esc_html_e('Aktivitätskarte', 'feuer-einsatzberichte'); ?></button>
        <button type="button" class="button feu-einsatz-stats-tab <?php echo 'participants' === $active_tab ? 'is-active' : ''; ?>" data-target="participants">
            <?php esc_html_e('Teilnehmer-Ranking', 'feuer-einsatzberichte'); ?>
            <?php if (!$participant_ranking_unlocked): ?><span class="feu-einsatz-tab-badge"><?php esc_html_e('PIN', 'feuer-einsatzberichte'); ?></span><?php endif; ?>
        </button>
        <button type="button" class="button feu-einsatz-stats-tab <?php echo 'presentation-settings' === $active_tab ? 'is-active' : ''; ?>" data-target="presentation-settings">
            <span class="ti ti-slideshow"></span>
            <?php esc_html_e('Präsentation einstellen', 'feuer-einsatzberichte'); ?>
        </button>
    </div>

    <section class="feu-einsatz-stats-panel <?php echo 'presentation-settings' === $active_tab ? 'is-active' : ''; ?>" data-panel="presentation-settings">
        <form method="post" action="" class="feu-einsatz-presentation-settings-panel">
            <?php wp_nonce_field('feu_einsatz_statistics_presentation_settings', 'feu_einsatz_statistics_presentation_settings_nonce'); ?>
            <input type="hidden" name="feu_einsatz_presentation_settings_action" value="save" data-feu-presentation-settings-action>
            <input type="hidden" name="jahr" value="<?php echo esc_attr((string) $jahr); ?>">
            <input type="hidden" name="feu_einsatz_stats_tab" value="presentation-settings" class="feu-einsatz-stats-tab-input">
            <div class="feu-einsatz-presentation-settings-head">
                <span class="ti ti-slideshow"></span>
                <div>
                    <h2><?php esc_html_e('Präsentation einstellen', 'feuer-einsatzberichte'); ?></h2>
                    <p><?php esc_html_e('Steuern Sie die Präsentation nach Seiten, aktivieren Sie einzelne Seiten und erstellen Sie eigene Seiten mit Editor.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>
            <div class="feu-einsatz-presentation-page-settings">
                <?php foreach ($presentation_setting_options as $setting_key => $setting_config): ?>
                    <article class="feu-einsatz-presentation-page-card">
                        <div class="feu-einsatz-presentation-page-number"><?php echo esc_html((string) $setting_config['page']); ?></div>
                        <div>
                            <h3><?php echo esc_html($setting_config['title']); ?></h3>
                            <p><?php echo esc_html(implode(' · ', (array) $setting_config['blocks'])); ?></p>
                        </div>
                        <label class="feu-einsatz-presentation-setting-toggle">
                            <input type="checkbox" name="feu_einsatz_presentation_sections[]" value="<?php echo esc_attr($setting_key); ?>" <?php checked(in_array($setting_key, $presentation_enabled_settings, true)); ?>>
                            <span class="ti ti-check"></span>
                            <strong><?php esc_html_e('Anzeigen', 'feuer-einsatzberichte'); ?></strong>
                        </label>
                        <label class="feu-einsatz-presentation-setting-toggle">
                            <input type="checkbox" name="feu_einsatz_presentation_background_pages[]" value="<?php echo esc_attr($setting_key); ?>" <?php checked(in_array($setting_key, $presentation_background_pages, true)); ?>>
                            <span class="ti ti-photo"></span>
                            <strong><?php esc_html_e('Hintergrund', 'feuer-einsatzberichte'); ?></strong>
                        </label>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="feu-einsatz-presentation-design-settings">
                <div class="feu-einsatz-presentation-settings-head">
                    <span class="ti ti-adjustments"></span>
                    <div>
                        <h2><?php esc_html_e('Design', 'feuer-einsatzberichte'); ?></h2>
                        <p><?php esc_html_e('Logo-Größe und Hintergrundbild für Präsentationsseiten einstellen.', 'feuer-einsatzberichte'); ?></p>
                    </div>
                </div>
                <div class="feu-einsatz-presentation-design-grid">
                    <label>
                        <span><?php esc_html_e('Logo-Größe', 'feuer-einsatzberichte'); ?></span>
                        <input type="range" min="20" max="140" step="1" name="feu_einsatz_presentation_logo_size" value="<?php echo esc_attr((string) $presentation_logo_size); ?>">
                        <strong><?php echo esc_html((string) $presentation_logo_size); ?>%</strong>
                    </label>
                    <label>
                        <span><?php esc_html_e('Hintergrundbild ID', 'feuer-einsatzberichte'); ?></span>
                        <input type="number" min="0" step="1" name="feu_einsatz_presentation_background_image_id" value="<?php echo esc_attr((string) $presentation_background_id); ?>" data-feu-presentation-background-id>
                        <button type="button" class="button button-secondary" data-feu-presentation-background-select><?php esc_html_e('Bild wählen', 'feuer-einsatzberichte'); ?></button>
                    </label>
                    <label>
                        <span><?php esc_html_e('Transparenz', 'feuer-einsatzberichte'); ?></span>
                        <input type="range" min="0" max="100" step="1" name="feu_einsatz_presentation_background_opacity" value="<?php echo esc_attr((string) $presentation_background_opacity); ?>">
                        <strong><?php echo esc_html((string) $presentation_background_opacity); ?>%</strong>
                    </label>
                    <label>
                        <span><?php esc_html_e('Abdunklung', 'feuer-einsatzberichte'); ?></span>
                        <input type="range" min="0" max="100" step="1" name="feu_einsatz_presentation_background_darkness" value="<?php echo esc_attr((string) $presentation_background_darkness); ?>">
                        <strong><?php echo esc_html((string) $presentation_background_darkness); ?>%</strong>
                    </label>
                </div>
                <?php if ('' !== $presentation_background_url): ?>
                    <div class="feu-einsatz-presentation-background-preview">
                        <img src="<?php echo esc_url($presentation_background_url); ?>" alt="">
                    </div>
                <?php endif; ?>
            </div>

            <div class="feu-einsatz-presentation-custom-pages">
                <div class="feu-einsatz-presentation-settings-head">
                    <span class="ti ti-file-plus"></span>
                    <div>
                        <h2><?php esc_html_e('Eigene Seiten', 'feuer-einsatzberichte'); ?></h2>
                        <p><?php esc_html_e('Eigene Präsentationsseiten werden nach den Statistikseiten angezeigt.', 'feuer-einsatzberichte'); ?></p>
                    </div>
                </div>
                <?php if (empty($presentation_custom_pages)): ?>
                    <p class="description"><?php esc_html_e('Noch keine eigenen Seiten angelegt.', 'feuer-einsatzberichte'); ?></p>
                <?php endif; ?>
                <?php foreach ($presentation_custom_pages as $custom_page_index => $custom_page): ?>
                    <article class="feu-einsatz-presentation-editor-card">
                        <input type="hidden" name="feu_einsatz_presentation_custom_order[]" value="<?php echo esc_attr((string) $custom_page_index); ?>">
                        <div class="feu-einsatz-presentation-editor-head">
                            <span class="feu-einsatz-presentation-page-number"><?php echo esc_html((string) (7 + $custom_page_index)); ?></span>
                            <div class="feu-einsatz-presentation-page-move">
                                <button type="button" class="button button-secondary" data-feu-presentation-page-move="up" title="<?php esc_attr_e('Seite nach oben verschieben', 'feuer-einsatzberichte'); ?>">
                                    <span class="ti ti-arrow-up"></span>
                                </button>
                                <button type="button" class="button button-secondary" data-feu-presentation-page-move="down" title="<?php esc_attr_e('Seite nach unten verschieben', 'feuer-einsatzberichte'); ?>">
                                    <span class="ti ti-arrow-down"></span>
                                </button>
                            </div>
                            <label>
                                <span><?php esc_html_e('Seitentitel', 'feuer-einsatzberichte'); ?></span>
                                <input type="text" class="regular-text" name="feu_einsatz_presentation_custom_title[<?php echo esc_attr((string) $custom_page_index); ?>]" value="<?php echo esc_attr((string) ($custom_page['title'] ?? '')); ?>">
                            </label>
                            <label>
                                <span><?php esc_html_e('Untertitel', 'feuer-einsatzberichte'); ?></span>
                                <input type="text" class="regular-text" name="feu_einsatz_presentation_custom_subtitle[<?php echo esc_attr((string) $custom_page_index); ?>]" value="<?php echo esc_attr((string) ($custom_page['subtitle'] ?? '')); ?>">
                            </label>
                            <label class="feu-einsatz-presentation-setting-toggle">
                                <input type="checkbox" name="feu_einsatz_presentation_custom_enabled[]" value="<?php echo esc_attr((string) $custom_page_index); ?>" <?php checked(!empty($custom_page['enabled'])); ?>>
                                <span class="ti ti-check"></span>
                                <strong><?php esc_html_e('Anzeigen', 'feuer-einsatzberichte'); ?></strong>
                            </label>
                            <label class="feu-einsatz-presentation-setting-toggle">
                                <input type="checkbox" name="feu_einsatz_presentation_background_pages[]" value="<?php echo esc_attr('custom_' . (string) $custom_page_index); ?>" <?php checked(in_array('custom_' . (string) $custom_page_index, $presentation_background_pages, true)); ?>>
                                <span class="ti ti-photo"></span>
                                <strong><?php esc_html_e('Hintergrund', 'feuer-einsatzberichte'); ?></strong>
                            </label>
                        </div>
                        <?php
                        wp_editor(
                            (string) ($custom_page['content'] ?? ''),
                            'feu_einsatz_presentation_custom_content_' . (int) $custom_page_index,
                            [
                                'textarea_name' => 'feu_einsatz_presentation_custom_content[' . (int) $custom_page_index . ']',
                                'textarea_rows' => 8,
                                'media_buttons' => false,
                                'teeny' => true,
                            ]
                        );
                        ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="feu-einsatz-presentation-settings-actions">
                <button type="submit" class="button button-secondary" data-feu-add-presentation-page>
                    <span class="ti ti-plus"></span>
                    <?php esc_html_e('Neue Seite hinzufügen', 'feuer-einsatzberichte'); ?>
                </button>
                <button type="submit" class="button button-primary">
                    <span class="ti ti-device-floppy"></span>
                    <?php esc_html_e('Präsentation speichern', 'feuer-einsatzberichte'); ?>
                </button>
            </div>
        </form>
    </section>

    <section class="feu-einsatz-stats-panel <?php echo 'categories' === $active_tab ? 'is-active' : ''; ?>" data-panel="categories">
        <div class="feu-einsatz-panel-card">
            <h2><?php esc_html_e('Einsätze nach Einsatzstichwort', 'feuer-einsatzberichte'); ?></h2>
            <div class="feu-einsatz-category-summary-head">
                <p class="description"><?php esc_html_e('Jedes Einsatzstichwort wird als eigene Linie mit Anzahl und Prozentanteil gezeigt.', 'feuer-einsatzberichte'); ?></p>
                <strong class="feu-einsatz-category-total"><?php echo esc_html((string) $category_total_count); ?> <?php esc_html_e('Einsätze', 'feuer-einsatzberichte'); ?></strong>
            </div>
            <?php if (!empty($category_visual_rows)): ?>
                <div class="feu-einsatz-category-bars" aria-label="<?php esc_attr_e('Einsatzstichwort-Balken', 'feuer-einsatzberichte'); ?>">
                    <?php foreach ($category_visual_rows as $category_row): ?>
                        <div class="feu-einsatz-category-bar-row" style="--feu-category-color: <?php echo esc_attr($category_row['color']); ?>; --feu-category-percent: <?php echo esc_attr(number_format((float) $category_row['percent'], 2, '.', '')); ?>%;">
                            <div class="feu-einsatz-category-bar-top">
                                <span><?php echo esc_html($category_row['name']); ?></span>
                                <strong><?php echo esc_html((string) $category_row['count']); ?></strong>
                            </div>
                            <div class="feu-einsatz-category-bar-track" aria-hidden="true"><span></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="category-stats feu-einsatz-category-donut-layout">
                <div class="category-chart-container"><canvas id="categoryDonutChart" width="320" height="320"></canvas></div>
                <div class="category-list">
                    <?php $total_categories = 0; foreach ($categories as $cat) { $total_categories += (int) $cat->anzahl; } ?>
                    <?php if (empty($categories)): ?>
                        <p><?php esc_html_e('Keine Einsatzstichwort-Daten für dieses Jahr vorhanden.', 'feuer-einsatzberichte'); ?></p>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <?php $percent = $total_categories > 0 ? round(((int) $cat->anzahl / $total_categories) * 100, 1) : 0; ?>
                            <div class="category-list-item">
                                <span class="category-color" style="background: <?php echo esc_attr($cat->farbe ?? '#0073aa'); ?>"></span>
                                <span class="category-list-name"><?php echo esc_html($cat->kategorie_name); ?></span>
                                <span class="category-list-count"><?php echo esc_html($cat->anzahl); ?></span>
                                <span class="category-list-prozent"><?php echo esc_html($percent); ?>%</span>
                            </div>
                        <?php endforeach; ?>
                        <div class="category-list-total"><strong><?php esc_html_e('Gesamt:', 'feuer-einsatzberichte'); ?></strong> <?php echo esc_html($total_categories); ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="feu-einsatz-category-history">
                <h3><?php esc_html_e('Einsatzstichworte nach Jahren', 'feuer-einsatzberichte'); ?></h3>
                <div class="feu-einsatz-category-history-grid">
                    <?php foreach ($category_history as $history_year => $history_categories): ?>
                        <div class="feu-einsatz-year-card">
                            <h4><?php echo esc_html($history_year); ?></h4>
                            <?php if (empty($history_categories)): ?>
                                <p class="description"><?php esc_html_e('Keine Daten', 'feuer-einsatzberichte'); ?></p>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($history_categories as $history_category): ?>
                                        <li><span><?php echo esc_html($history_category->kategorie_name); ?></span><strong><?php echo esc_html($history_category->anzahl); ?></strong></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="feu-einsatz-day-popover" id="feu-einsatz-day-popover" hidden>
                <div class="feu-einsatz-day-popover-card">
                    <div class="feu-einsatz-day-popover-head">
                        <strong id="feu-einsatz-day-popover-title"><?php esc_html_e('Einsätze am Tag', 'feuer-einsatzberichte'); ?></strong>
                        <button type="button" class="button-link" id="feu-einsatz-day-popover-close" aria-label="<?php esc_attr_e('Vorschau schließen', 'feuer-einsatzberichte'); ?>">&times;</button>
                    </div>
                    <div id="feu-einsatz-day-popover-content"></div>
                </div>
            </div>
        </div>
    </section>

    <section class="feu-einsatz-stats-panel <?php echo 'calendar' === $active_tab ? 'is-active' : ''; ?>" data-panel="calendar">
        <div class="feu-einsatz-panel-card">
            <div class="feu-einsatz-panel-header">
                <div>
                    <h2><?php esc_html_e('Kalender', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Monatsansicht mit Tagesverteilung der Einsätze. Auf Mobilgeräten können die Monatskarten horizontal gescrollt werden.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-einsatz-calendar-insights">
                <div class="feu-einsatz-insight-card">
                    <h3><?php esc_html_e('Häufigste Wochentage', 'feuer-einsatzberichte'); ?></h3>
                    <?php if (empty($top_weekdays)): ?>
                        <p class="description"><?php esc_html_e('Noch keine Tagesdaten für dieses Jahr.', 'feuer-einsatzberichte'); ?></p>
                    <?php else: ?>
                        <ul class="feu-einsatz-insight-list">
                            <?php foreach ($top_weekdays as $weekday_entry): ?>
                                <li><span><?php echo esc_html($weekday_entry['label']); ?></span><strong><?php echo esc_html($weekday_entry['count']); ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="feu-einsatz-insight-card">
                    <h3><?php esc_html_e('Spitzen-Tage', 'feuer-einsatzberichte'); ?></h3>
                    <?php if (empty($peak_days)): ?>
                        <p class="description"><?php esc_html_e('Noch keine Einsätze für dieses Jahr.', 'feuer-einsatzberichte'); ?></p>
                    <?php else: ?>
                        <ul class="feu-einsatz-insight-list">
                            <?php foreach ($peak_days as $peak_day): ?>
                                <li><span><?php echo esc_html($peak_day['date']); ?></span><strong><?php echo esc_html($peak_day['count']); ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="feu-einsatz-chart-card">
                <div class="feu-einsatz-chart-card-header">
                    <div>
                        <h3><?php esc_html_e('Einsätze pro Tag', 'feuer-einsatzberichte'); ?></h3>
                        <p class="description"><?php esc_html_e('Kompakte Jahresansicht mit allen Tagen und der Anzahl der Einsätze pro Tag.', 'feuer-einsatzberichte'); ?></p>
                    </div>
                </div>
                <div class="feu-einsatz-chart-canvas-wrap feu-einsatz-chart-canvas-wrap-compact">
                    <canvas id="dailyTimelineChart" height="64"></canvas>
                </div>
            </div>

            <div class="feu-einsatz-active-days-card">
                <div class="feu-einsatz-chart-card-header">
                    <div>
                        <h3><?php esc_html_e('Tage mit Einsätzen', 'feuer-einsatzberichte'); ?></h3>
                        <p class="description"><?php esc_html_e('Schnelle Liste aller Tage mit mindestens einem Einsatz.', 'feuer-einsatzberichte'); ?></p>
                    </div>
                </div>
                <div class="feu-einsatz-active-days-list">
                    <?php if (empty($sorted_days)): ?>
                        <div class="feu-einsatz-empty-card"><?php esc_html_e('Noch keine Einsätze für dieses Jahr.', 'feuer-einsatzberichte'); ?></div>
                    <?php else: ?>
                        <?php foreach ($sorted_days as $date_string => $count): ?>
                            <?php if ((int) $count <= 0) { continue; } ?>
                            <div class="feu-einsatz-active-day-item">
                                <span><?php echo esc_html(date_i18n('d.m.Y', strtotime($date_string))); ?></span>
                                <strong><?php echo esc_html($count); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="feu-einsatz-calendar-months-card">
                <div class="feu-einsatz-chart-card-header">
                    <div>
                        <h3><?php esc_html_e('Kalenderansicht', 'feuer-einsatzberichte'); ?></h3>
                        <p class="description"><?php esc_html_e('Kompakte Monatskarten mit allen Tagen und der Anzahl der Einsätze.', 'feuer-einsatzberichte'); ?></p>
                    </div>
                </div>
                <div class="feu-einsatz-calendar-month-grid">
                    <?php $weekday_headers = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']; ?>
                    <?php for ($month = 0; $month < 12; $month++): ?>
                        <?php
                        $month_number = $month + 1;
                        $first_day_weekday = (int) date('N', strtotime(sprintf('%04d-%02d-01', $jahr, $month_number)));
                        ?>
                        <div class="feu-einsatz-month-card">
                            <h4><?php echo esc_html($month_names[$month]); ?></h4>
                            <div class="feu-einsatz-month-weekdays">
                                <?php foreach ($weekday_headers as $weekday_header): ?>
                                    <span><?php echo esc_html($weekday_header); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="feu-einsatz-month-days">
                                <?php for ($empty = 1; $empty < $first_day_weekday; $empty++): ?>
                                    <span class="feu-einsatz-month-day feu-einsatz-month-day-empty" aria-hidden="true"></span>
                                <?php endfor; ?>

                                <?php for ($day = 1; $day <= $days_in_month[$month]; $day++): ?>
                                    <?php
                                    $date_str = sprintf('%04d-%02d-%02d', $jahr, $month_number, $day);
                                    $count = isset($count_by_date[$date_str]) ? (int) $count_by_date[$date_str] : 0;
                                    $intensity = $max_count > 0 ? max(0.12, min(1, $count / $max_count)) : 0.12;
                                    $style = $count > 0 ? 'style="--feu-einsatz-day-intensity:' . esc_attr((string) $intensity) . ';"' : '';
                                    ?>
                                    <div class="feu-einsatz-month-day <?php echo $count > 0 ? 'is-active' : ''; ?>" <?php echo $style; ?> title="<?php echo esc_attr($day . '.' . $month_number . '.' . $jahr . ': ' . $count . ' Einsätze'); ?>">
                                        <span class="feu-einsatz-month-day-number"><?php echo esc_html($day); ?></span>
                                        <span class="feu-einsatz-month-day-count"><?php echo $count > 0 ? esc_html($count) : ''; ?></span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="feu-einsatz-stats-panel <?php echo 'activity-map' === $active_tab ? 'is-active' : ''; ?>" data-panel="activity-map">
        <div class="feu-einsatz-panel-card">
            <div class="feu-einsatz-panel-header">
                <div>
                    <h2><?php esc_html_e('Aktivitätskarte', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Nahe beieinander liegende Einsätze werden zu Aktivitätszonen zusammengefasst. Größere Kreise bedeuten mehr Einsätze in diesem Bereich.', 'feuer-einsatzberichte'); ?></p>
                </div>
                <?php if (!empty($activity_map_points)) : ?>
                    <button type="button" class="button button-secondary" id="feu-einsatz-activity-map-fullscreen">
                        <?php esc_html_e('Vollbild', 'feuer-einsatzberichte'); ?>
                    </button>
                <?php endif; ?>
            </div>

            <?php if (empty($activity_map_points)): ?>
                <div class="feu-einsatz-loading-card"><?php esc_html_e('Keine Koordinaten für dieses Jahr vorhanden.', 'feuer-einsatzberichte'); ?></div>
            <?php else: ?>
                <div id="feu-einsatz-activity-map" class="feu-einsatz-activity-map"><?php echo $activity_map_svg; ?></div>
                <div class="feu-einsatz-map-legend"><strong><?php esc_html_e('Hinweis:', 'feuer-einsatzberichte'); ?></strong> <?php esc_html_e('Je größer und dunkler der Kreis, desto mehr Einsätze in diesem Gebiet.', 'feuer-einsatzberichte'); ?></div>
            <?php endif; ?>
        </div>
    </section>

    <section class="feu-einsatz-stats-panel <?php echo 'participants' === $active_tab ? 'is-active' : ''; ?>" data-panel="participants">
        <div class="feu-einsatz-panel-card">
            <div class="feu-einsatz-panel-header">
                <div>
                    <h2><?php esc_html_e('Teilnehmer-Ranking', 'feuer-einsatzberichte'); ?> <?php echo esc_html($jahr); ?></h2>
                    <p class="description"><?php echo $participant_ranking_unlocked ? esc_html__('Details und Einsätze können jetzt eingesehen werden.', 'feuer-einsatzberichte') : esc_html__('Dieser Bereich ist gesichert. Die Daten bleiben unscharf, bis die PIN eingegeben wurde.', 'feuer-einsatzberichte'); ?></p>
                </div>
                <?php if ($participant_ranking_unlocked): ?>
                    <form method="post" action="" class="feu-einsatz-inline-lock-form">
                        <?php wp_nonce_field('feu_einsatz_statistics_participant_access', 'feu_einsatz_statistics_participant_access_nonce'); ?>
                        <input type="hidden" name="jahr" value="<?php echo esc_attr($jahr); ?>">
                        <input type="hidden" name="feu_einsatz_stats_tab" value="participants" class="feu-einsatz-stats-tab-input">
                        <input type="hidden" name="feu_einsatz_stats_action" value="lock_participant_ranking">
                        <button type="submit" class="button"><?php esc_html_e('Wieder sperren', 'feuer-einsatzberichte'); ?></button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="feu-einsatz-ranking-stage <?php echo $participant_ranking_unlocked ? '' : 'is-locked'; ?>">
                <div class="feu-einsatz-panel-header feu-einsatz-participant-tools">
                    <div class="feu-einsatz-participant-jump">
                        <label for="feu-einsatz-participant-jump"><?php esc_html_e('Teilnehmerdetails:', 'feuer-einsatzberichte'); ?></label>
                        <select id="feu-einsatz-participant-jump" <?php disabled(!$participant_ranking_unlocked); ?>>
                            <option value=""><?php esc_html_e('Teilnehmer auswählen', 'feuer-einsatzberichte'); ?></option>
                            <?php foreach ($participants as $participant): ?>
                                <option value="<?php echo esc_attr($participant->id); ?>"><?php echo esc_html(FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php if (!empty($participants)): ?>
                    <div class="feu-einsatz-chart-card feu-einsatz-ranking-chart-card">
                        <div class="feu-einsatz-chart-card-header">
                            <div>
                                <h3><?php esc_html_e('Einsätze nach Teilnehmer', 'feuer-einsatzberichte'); ?></h3>
                                <p class="description"><?php esc_html_e('Die Grafik zeigt die aktivsten Teilnehmer des ausgewählten Jahres als Vergleich nach Einsätzen.', 'feuer-einsatzberichte'); ?></p>
                            </div>
                        </div>
                        <div class="feu-einsatz-chart-canvas-wrap feu-einsatz-chart-canvas-wrap-ranking">
                            <canvas id="participantRankingChart" height="300"></canvas>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($participants)): ?>
                    <p class="description" style="padding:12px 0;"><?php esc_html_e('Keine Teilnehmerdaten für dieses Jahr vorhanden.', 'feuer-einsatzberichte'); ?></p>
                <?php else: ?>
                    <?php $podium_slots = [1, 0, 2]; ?>
                    <div class="feu-einsatz-ranking-podium">
                        <?php foreach ($podium_slots as $pos): ?>
                            <?php if (!isset($participants[$pos])) continue; ?>
                            <?php $p = $participants[$pos]; ?>
                            <?php $trophy = $pos === 0 ? '&#x1F947;' : ($pos === 1 ? '&#x1F948;' : '&#x1F949;'); ?>
                            <div class="feu-einsatz-podium-card feu-einsatz-podium-rank-<?php echo $pos + 1; ?>">
                                <div class="feu-einsatz-podium-trophy"><?php echo $trophy; ?></div>
                                <div class="feu-einsatz-podium-name"><?php echo esc_html(FEU_Einsatz_Template_Helpers::format_participant_name($p->vorname, $p->nachname)); ?></div>
                                <div class="feu-einsatz-podium-count"><?php echo esc_html($p->gesamt_einsaetze); ?> <span><?php esc_html_e('Einsätze', 'feuer-einsatzberichte'); ?></span></div>
                                <button type="button" class="button button-small feu-einsatz-open-participant-details" data-participant-id="<?php echo esc_attr($p->id); ?>" data-participant-name="<?php echo esc_attr(FEU_Einsatz_Template_Helpers::format_participant_name($p->vorname, $p->nachname)); ?>" <?php disabled(!$participant_ranking_unlocked); ?>><?php esc_html_e('Details', 'feuer-einsatzberichte'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($participants) > 3): ?>
                        <div class="feu-einsatz-ranking-compact-list">
                            <?php foreach (array_slice($participants, 3) as $idx => $participant): ?>
                                <div class="feu-einsatz-ranking-compact-item">
                                    <span class="feu-einsatz-ranking-compact-rank">#<?php echo $idx + 4; ?></span>
                                    <span class="feu-einsatz-ranking-compact-name"><?php echo esc_html(FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname)); ?></span>
                                    <span class="feu-einsatz-ranking-compact-count"><?php echo esc_html($participant->gesamt_einsaetze); ?></span>
                                    <button type="button" class="button button-small feu-einsatz-open-participant-details" data-participant-id="<?php echo esc_attr($participant->id); ?>" data-participant-name="<?php echo esc_attr(FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname)); ?>" <?php disabled(!$participant_ranking_unlocked); ?>><?php esc_html_e('Details', 'feuer-einsatzberichte'); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!$participant_ranking_unlocked): ?>
                    <div class="feu-einsatz-ranking-overlay">
                        <div class="feu-einsatz-pin-card">
                            <form method="post" action="" class="feu-einsatz-pin-form">
                                <?php wp_nonce_field('feu_einsatz_statistics_participant_access', 'feu_einsatz_statistics_participant_access_nonce'); ?>
                                <input type="hidden" name="jahr" value="<?php echo esc_attr($jahr); ?>">
                                <input type="hidden" name="feu_einsatz_stats_tab" value="participants" class="feu-einsatz-stats-tab-input">
                                <input type="hidden" name="feu_einsatz_stats_action" value="unlock_participant_ranking">
                                <label for="feu-einsatz-participant-ranking-pin"><?php esc_html_e('PIN eingeben', 'feuer-einsatzberichte'); ?></label>
                                <input type="password" id="feu-einsatz-participant-ranking-pin" name="feu_einsatz_participant_ranking_pin" inputmode="numeric" pattern="[0-9]*" maxlength="12" autocomplete="one-time-code" class="regular-text" placeholder="<?php esc_attr_e('PIN', 'feuer-einsatzberichte'); ?>" required>
                                <button type="submit" class="button button-primary"><?php esc_html_e('Freischalten', 'feuer-einsatzberichte'); ?></button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div id="feu-einsatz-participant-detail-panel" class="feu-einsatz-detail-panel" hidden>
                <div class="feu-einsatz-detail-panel-header">
                    <div>
                        <h3 id="feu-einsatz-participant-detail-title"><?php esc_html_e('Teilnehmerdetails', 'feuer-einsatzberichte'); ?></h3>
                        <p class="description"><?php esc_html_e('Übersicht zu Einsätzen und Funktionen im ausgewählten Jahr.', 'feuer-einsatzberichte'); ?></p>
                    </div>
                    <button type="button" class="button" id="feu-einsatz-close-participant-details"><?php esc_html_e('Schließen', 'feuer-einsatzberichte'); ?></button>
                </div>
                <div id="feu-einsatz-participant-detail-content"></div>
            </div>
        </div>
    </section>
    <?php
    feu_einsatz_render_statistics_presentation([
        'year' => $jahr,
        'organization_name' => $statistics_organization_name,
        'logo_url' => $statistics_logo_url,
        'total_stats' => $total,
        'category_rows' => $category_visual_rows,
        'top_weekdays' => $top_weekdays,
        'peak_days' => $peak_days,
        'monthly_totals' => $monthly_totals,
        'month_names' => $month_names,
        'participants' => $presentation_participants,
        'ranking_unlocked' => true,
        'activity_map_svg' => $activity_map_svg,
        'station_feature' => is_array($statistics_station_feature) ? $statistics_station_feature : [],
        'top_category_label' => $top_category_label,
        'busiest_month_label' => $busiest_month_label,
        'presentation_settings' => $presentation_enabled_settings,
        'custom_pages' => $presentation_custom_pages,
        'logo_size' => $presentation_logo_size,
        'background_url' => $presentation_background_url,
        'background_opacity' => $presentation_background_opacity,
        'background_darkness' => $presentation_background_darkness,
        'background_pages' => $presentation_background_pages,
    ]);
    ?>
</div>

<?php
$jspdf_path = FEU_EINSATZ_PLUGIN_DIR . 'assets/vendor/jspdf/jspdf.umd.min.js';
$jspdf_version = file_exists($jspdf_path) ? (string) filemtime($jspdf_path) : FEU_EINSATZ_VERSION;
?>
<script src="<?php echo esc_url(FEU_EINSATZ_PLUGIN_URL . 'assets/vendor/jspdf/jspdf.umd.min.js?ver=' . rawurlencode($jspdf_version)); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var categories = <?php echo wp_json_encode($categories); ?>;
    var categoryVisualRows = <?php echo wp_json_encode($category_visual_rows); ?>;
    var monthNames = <?php echo wp_json_encode($month_names); ?>;
    var monthlyTotals = <?php echo wp_json_encode(array_values($monthly_totals)); ?>;
    var selectedYear = <?php echo wp_json_encode($jahr); ?>;
    var activityMapPoints = <?php echo wp_json_encode($activity_map_points); ?>;
    var presentationStationFeature = <?php echo wp_json_encode(is_array($statistics_station_feature) ? $statistics_station_feature : null); ?>;
    var totalStats = <?php echo wp_json_encode($total); ?>;
    var peakDays = <?php echo wp_json_encode($peak_days); ?>;
    var topWeekdays = <?php echo wp_json_encode($top_weekdays); ?>;
    var dailyCountByDate = <?php echo wp_json_encode($count_by_date); ?>;
    var dailyReportsByDate = <?php echo wp_json_encode($daily_reports_by_date); ?>;
    var participantLookup = <?php echo wp_json_encode($participant_ranking_unlocked ? array_values(array_map(static function($participant) {
        return ['id' => (int) $participant->id, 'name' => FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname)];
    }, $participants)) : []); ?>;
    var participantStats = <?php echo wp_json_encode($participant_ranking_unlocked ? array_values(array_map(static function($participant) {
        return [
            'name' => FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname),
            'count' => (int) $participant->gesamt_einsaetze,
        ];
    }, $participants)) : []); ?>;
    var participantDistributionData = <?php echo wp_json_encode($participant_distribution_chart_data); ?>;
    var preselectedParticipantId = <?php echo wp_json_encode($participant_ranking_unlocked ? $selected_participant_id : 0); ?>;
    var rankingUnlocked = <?php echo wp_json_encode($participant_ranking_unlocked); ?>;
    var activeTab = <?php echo wp_json_encode($active_tab); ?>;
    var detailPanel = document.getElementById('feu-einsatz-participant-detail-panel');
    var detailTitle = document.getElementById('feu-einsatz-participant-detail-title');
    var detailContent = document.getElementById('feu-einsatz-participant-detail-content');
    var activityMapInstance = null;
    var presentationActivityMapInstance = null;
    var activityMapRendered = false;
    var activityClusters = [];
    var categoryChart = null;
    var weekdayChart = null;
    var participantRankingChart = null;
    var participantDistributionChart = null;
    var monthlyChart = null;
    var dayPopover = document.getElementById('feu-einsatz-day-popover');
    var dayPopoverTitle = document.getElementById('feu-einsatz-day-popover-title');
    var dayPopoverContent = document.getElementById('feu-einsatz-day-popover-content');
    var dayPopoverClose = document.getElementById('feu-einsatz-day-popover-close');

    if (dayPopover && dayPopover.parentNode !== document.body) {
        document.body.appendChild(dayPopover);
    }

    function animateStatisticVisuals() {
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        document.querySelectorAll('[data-feu-count-up]').forEach(function(element) {
            var target = parseFloat(element.getAttribute('data-feu-count-up') || '0');
            var decimals = parseInt(element.getAttribute('data-feu-count-decimals') || '0', 10);
            var duration = reduceMotion ? 0 : 900;
            var startTime = null;

            if (!Number.isFinite(target)) {
                return;
            }

            function renderValue(progress) {
                var eased = 1 - Math.pow(1 - progress, 3);
                var value = target * eased;
                element.textContent = decimals > 0 ? value.toFixed(decimals) : String(Math.round(value));
            }

            if (!duration) {
                renderValue(1);
                return;
            }

            window.requestAnimationFrame(function step(timestamp) {
                if (!startTime) {
                    startTime = timestamp;
                }

                var progress = Math.min(1, (timestamp - startTime) / duration);
                renderValue(progress);

                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            });
        });

        document.querySelectorAll('.feu-einsatz-stat-visual-card, .feu-einsatz-command-visual, .feu-einsatz-admindek-card').forEach(function(element, index) {
            window.setTimeout(function() {
                element.classList.add('is-animated');
            }, reduceMotion ? 0 : index * 90);
        });
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getCategoryColor(category, index) {
        var visualRow = categoryVisualRows[index] || {};
        return category.color || category.farbe || visualRow.color || '#0a4b78';
    }

    function getIsoDateFromCalendarCell(cell) {
        var monthCard = cell ? cell.closest('.feu-einsatz-month-card') : null;
        var dayNumberElement = cell ? cell.querySelector('.feu-einsatz-month-day-number') : null;
        var monthNameElement = monthCard ? monthCard.querySelector('h4') : null;
        var dayNumber = dayNumberElement ? parseInt(dayNumberElement.textContent || '0', 10) : 0;
        var monthIndex = monthNameElement ? monthNames.indexOf((monthNameElement.textContent || '').trim()) : -1;

        if (!dayNumber || monthIndex < 0) {
            return '';
        }

        return String(selectedYear)
            + '-'
            + String(monthIndex + 1).padStart(2, '0')
            + '-'
            + String(dayNumber).padStart(2, '0');
    }

    function hideDayPopover() {
        if (dayPopover) {
            dayPopover.hidden = true;
        }
    }

    function showDayPopover(targetCell) {
        var isoDate = getIsoDateFromCalendarCell(targetCell);
        var entries = isoDate && dailyReportsByDate[isoDate] ? dailyReportsByDate[isoDate] : [];
        var count = isoDate && dailyCountByDate[isoDate] ? Number(dailyCountByDate[isoDate]) : 0;
        var dateParts = isoDate ? isoDate.split('-') : [];
        var formattedDate = dateParts.length === 3 ? (dateParts[2] + '.' + dateParts[1] + '.' + dateParts[0]) : '';
        var html = '';
        var rect;

        if (!dayPopover || !dayPopoverContent || !isoDate || !count) {
            hideDayPopover();
            return;
        }

        if (dayPopoverTitle) {
            dayPopoverTitle.textContent = formattedDate + ' · ' + count + ' Einsatz' + (count === 1 ? '' : 'e');
        }

        html += '<div class="feu-einsatz-day-popover-list">';
        entries.forEach(function(entry) {
            var time = entry && entry.time ? String(entry.time).trim() : '';
            var street = entry && entry.street ? String(entry.street).trim() : '';
            var title = entry && entry.title ? String(entry.title).trim() : '';
            var editUrl = entry && entry.edit_url ? String(entry.edit_url) : '';
            var metaLine = [time ? time + ' Uhr' : '', street].filter(Boolean).join(' · ');
            html += '<a class="feu-einsatz-day-popover-item" href="' + escapeHtml(editUrl || '#') + '">';
            html += '  <div class="feu-einsatz-day-popover-copy">';
            html += '    <strong>' + escapeHtml(title || 'Einsatzbericht') + '</strong>';
            if (metaLine) {
                html += '    <span>' + escapeHtml(metaLine) + '</span>';
            }
            html += '  </div>';
            html += '</a>';
        });
        html += '</div>';

        if (!entries.length) {
            html = '<p class="description">Keine einzelnen Einsatzberichte für diesen Tag gefunden.</p>';
        }

        dayPopoverContent.innerHTML = html;
        dayPopover.hidden = false;
        rect = targetCell.getBoundingClientRect();
        dayPopover.style.top = String(window.scrollY + rect.bottom + 10) + 'px';
        dayPopover.style.left = String(window.scrollX + rect.left) + 'px';
    }

    function syncTabInputs(tabName) {
        document.querySelectorAll('.feu-einsatz-stats-tab-input').forEach(function(input) {
            input.value = tabName;
        });
    }

    function setActiveTab(target) {
        activeTab = target;
        syncTabInputs(target);

        document.querySelectorAll('.feu-einsatz-stats-tab').forEach(function(tab) {
            tab.classList.toggle('is-active', tab.getAttribute('data-target') === target);
        });

        document.querySelectorAll('.feu-einsatz-stats-panel').forEach(function(panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-panel') === target);
        });

        if (target === 'activity-map') {
            ensureActivityMap();
        }

        window.setTimeout(function() {
            if (target === 'categories' && categoryChart) {
                categoryChart.resize();
            }

            if (target === 'calendar' && weekdayChart) {
                weekdayChart.resize();
            }

            if (target === 'calendar' && monthlyChart) {
                monthlyChart.resize();
            }

            if (target === 'participants' && participantRankingChart) {
                participantRankingChart.resize();
            }
        }, 80);
    }

    document.querySelectorAll('.feu-einsatz-stats-tab').forEach(function(button) {
        button.addEventListener('click', function() {
            setActiveTab(button.getAttribute('data-target'));
        });
    });

    var statisticsToolbar = document.getElementById('feu-einsatz-statistics-toolbar');
    var yearSelect = document.getElementById('jahr-select');
    var addPresentationPageButton = document.querySelector('[data-feu-add-presentation-page]');
    if (addPresentationPageButton) {
        addPresentationPageButton.addEventListener('click', function() {
            var actionInput = document.querySelector('[data-feu-presentation-settings-action]');
            if (actionInput) {
                actionInput.value = 'add_custom_page';
            }
        });
    }

    function refreshPresentationCustomPageNumbers() {
        document.querySelectorAll('.feu-einsatz-presentation-custom-pages .feu-einsatz-presentation-editor-card').forEach(function(card, index) {
            var numberElement = card.querySelector('.feu-einsatz-presentation-page-number');
            if (numberElement) {
                numberElement.textContent = String(index + 7);
            }
        });
    }

    document.querySelectorAll('[data-feu-presentation-page-move]').forEach(function(button) {
        button.addEventListener('click', function() {
            var direction = button.getAttribute('data-feu-presentation-page-move');
            var card = button.closest('.feu-einsatz-presentation-editor-card');
            var sibling;

            if (!card) {
                return;
            }

            if ('up' === direction) {
                sibling = card.previousElementSibling;
                while (sibling && !sibling.classList.contains('feu-einsatz-presentation-editor-card')) {
                    sibling = sibling.previousElementSibling;
                }
                if (sibling) {
                    card.parentNode.insertBefore(card, sibling);
                }
            } else {
                sibling = card.nextElementSibling;
                while (sibling && !sibling.classList.contains('feu-einsatz-presentation-editor-card')) {
                    sibling = sibling.nextElementSibling;
                }
                if (sibling) {
                    card.parentNode.insertBefore(sibling, card);
                }
            }

            refreshPresentationCustomPageNumbers();
        });
    });

    document.querySelectorAll('.feu-einsatz-presentation-design-grid input[type="range"]').forEach(function(rangeInput) {
        rangeInput.addEventListener('input', function() {
            var valueLabel = rangeInput.parentElement ? rangeInput.parentElement.querySelector('strong') : null;
            if (valueLabel) {
                valueLabel.textContent = rangeInput.value + '%';
            }
            if ('feu_einsatz_presentation_logo_size' === rangeInput.name) {
                var deck = document.getElementById('feu-einsatz-statistics-presentation-deck');
                if (deck) {
                    deck.style.setProperty('--feu-presentation-logo-size', rangeInput.value + '%');
                }
            }
        });
    });

    var presentationBackgroundButton = document.querySelector('[data-feu-presentation-background-select]');
    if (presentationBackgroundButton) {
        presentationBackgroundButton.addEventListener('click', function() {
            var idInput = document.querySelector('[data-feu-presentation-background-id]');
            var frame;

            if (!window.wp || !wp.media || !idInput) {
                return;
            }

            frame = wp.media({
                title: '<?php echo esc_js(__('Hintergrundbild wählen', 'feuer-einsatzberichte')); ?>',
                button: { text: '<?php echo esc_js(__('Bild übernehmen', 'feuer-einsatzberichte')); ?>' },
                multiple: false
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first();
                if (attachment) {
                    idInput.value = attachment.get('id') || '';
                }
            });

            frame.open();
        });
    }

    if (statisticsToolbar && yearSelect) {
        yearSelect.addEventListener('change', function() {
            syncTabInputs(activeTab);
            statisticsToolbar.submit();
        });
    }

    if (dayPopover && dayPopoverClose) {
        dayPopoverClose.addEventListener('click', hideDayPopover);

        document.addEventListener('click', function(event) {
            if (!dayPopover || dayPopover.hidden) {
                return;
            }

            if (!dayPopover.contains(event.target) && !event.target.closest('.feu-einsatz-month-day.is-active')) {
                hideDayPopover();
            }
        });

        document.querySelectorAll('.feu-einsatz-month-day.is-active').forEach(function(dayCell) {
            dayCell.addEventListener('mouseenter', function() {
                showDayPopover(dayCell);
            });
            dayCell.addEventListener('click', function() {
                showDayPopover(dayCell);
            });
        });
    }

    function getCategoryChartConfig() {
        return {
            type: 'doughnut',
            data: {
                labels: categories.map(function(category) { return category.kategorie_name; }),
                datasets: [{
                    data: categories.map(function(category) { return parseInt(category.anzahl, 10); }),
                    backgroundColor: categories.map(function(category, index) { return getCategoryColor(category, index); }),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var value = Number(context.raw || 0);
                                var totalValue = categories.reduce(function(sum, category) {
                                    return sum + Number(category.anzahl || 0);
                                }, 0);
                                var percent = totalValue > 0 ? ((value / totalValue) * 100).toFixed(1) : '0.0';
                                return context.label + ': ' + value + ' Einsätze (' + percent + '%)';
                            }
                        }
                    }
                }
            }
        };
    }

    function buildDailyTimelineSeries() {
        var startDate = new Date(Number(selectedYear), 0, 1);
        var endDate = new Date(Number(selectedYear), 11, 31);
        var cursor = new Date(startDate.getTime());
        var labels = [];
        var values = [];
        var monthMarkers = [];

        while (cursor <= endDate) {
            var month = String(cursor.getMonth() + 1).padStart(2, '0');
            var day = String(cursor.getDate()).padStart(2, '0');
            var key = cursor.getFullYear() + '-' + month + '-' + day;

            labels.push(day + '.' + month + '.');
            values.push(parseInt(dailyCountByDate[key] || 0, 10));

            if (cursor.getDate() === 1) {
                monthMarkers.push(labels.length - 1);
            }

            cursor.setDate(cursor.getDate() + 1);
        }

        return {
            labels: labels,
            values: values,
            monthMarkers: monthMarkers
        };
    }

    function getDailyTimelineChartConfig() {
        var timelineSeries = buildDailyTimelineSeries();

        return {
            type: 'bar',
            data: {
                labels: timelineSeries.labels,
                datasets: [{
                    label: 'Einsätze',
                    data: timelineSeries.values,
                    backgroundColor: timelineSeries.values.map(function(value) {
                        return value > 0 ? '#0a4b78' : 'rgba(148,163,184,0.32)';
                    }),
                    borderRadius: 2,
                    borderSkipped: false,
                    barPercentage: 1,
                    categoryPercentage: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw + ' Einsätze';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 0,
                            callback: function(value, index) {
                                return timelineSeries.monthMarkers.indexOf(index) !== -1 ? timelineSeries.labels[index] : '';
                            }
                        },
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        };
    }

    function getParticipantRankingChartConfig(limit) {
        var chartLimit = limit || 10;

        return {
            type: 'bar',
            data: {
                labels: participantStats.slice(0, chartLimit).map(function(participant) { return participant.name; }),
                datasets: [{
                    label: 'Einsätze',
                    data: participantStats.slice(0, chartLimit).map(function(participant) { return participant.count; }),
                    backgroundColor: '#0a4b78',
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 48
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw + ' Einsätze';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 35,
                            minRotation: 20,
                            color: '#334155'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        };
    }

    function getCategoryChartConfigForType(chartType) {
        var selectedType = chartType || 'doughnut';
        var config = getCategoryChartConfig();
        config.type = selectedType;

        if (selectedType === 'bar') {
            config.options.indexAxis = 'y';
            config.options.scales = {
                x: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            };
            config.data.datasets[0].borderRadius = 10;
            config.data.datasets[0].borderSkipped = false;
        } else {
            delete config.options.scales;
            delete config.options.indexAxis;
        }

        return config;
    }

    function getDailyTimelineChartConfigForType(chartType) {
        var selectedType = chartType || 'bar';
        var timelineSeries = buildDailyTimelineSeries();
        var isLineChart = selectedType === 'line';

        return {
            type: selectedType,
            data: {
                labels: timelineSeries.labels,
                datasets: [{
                    label: 'Einsätze',
                    data: timelineSeries.values,
                    backgroundColor: isLineChart ? 'rgba(10,75,120,0.14)' : timelineSeries.values.map(function(value) {
                        return value > 0 ? '#0a4b78' : 'rgba(148,163,184,0.32)';
                    }),
                    borderColor: '#0a4b78',
                    fill: isLineChart,
                    tension: 0.28,
                    borderRadius: 2,
                    borderSkipped: false,
                    barPercentage: 1,
                    categoryPercentage: 1,
                    pointRadius: isLineChart ? 0 : 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw + ' Einsätze';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 0,
                            callback: function(value, index) {
                                return timelineSeries.monthMarkers.indexOf(index) !== -1 ? timelineSeries.labels[index] : '';
                            }
                        },
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        };
    }

    function getMonthlyTotalsChartConfig(chartType) {
        var selectedType = chartType || 'line';
        var totals = new Array(12).fill(0);
        var isLineChart = selectedType === 'line';

        Object.keys(dailyCountByDate).forEach(function(dateKey) {
            var value = parseInt(dailyCountByDate[dateKey] || 0, 10);
            var parsedDate = new Date(dateKey + 'T00:00:00');

            if (!isNaN(parsedDate.getTime())) {
                totals[parsedDate.getMonth()] += value;
            }
        });

        return {
            type: selectedType,
            data: {
                labels: monthNames,
                datasets: [{
                    label: 'Einsätze pro Monat',
                    data: totals,
                    backgroundColor: isLineChart ? 'rgba(10,75,120,0.14)' : '#0a4b78',
                    borderColor: '#0a4b78',
                    fill: isLineChart,
                    tension: 0.28,
                    borderRadius: 10,
                    borderSkipped: false,
                    pointRadius: isLineChart ? 3 : 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        };
    }

    function getAdmindekMonthlyChartConfig() {
        return {
            type: 'bar',
            data: {
                labels: monthNames,
                datasets: [{
                    type: 'bar',
                    label: 'Einsätze',
                    data: monthlyTotals,
                    backgroundColor: 'rgba(10, 75, 120, 0.82)',
                    borderRadius: 10,
                    borderSkipped: false,
                    maxBarThickness: 34
                }, {
                    type: 'line',
                    label: 'Trend',
                    data: monthlyTotals,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.14)',
                    pointBackgroundColor: '#f59e0b',
                    pointRadius: 4,
                    tension: 0.34,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw + ' Einsätze';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b' }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: '#64748b' }
                    }
                }
            }
        };
    }

    function getAdmindekCategoryChartConfig() {
        return {
            type: 'doughnut',
            data: {
                labels: categories.map(function(category) { return category.kategorie_name; }),
                datasets: [{
                    data: categories.map(function(category) { return parseInt(category.anzahl, 10) || 0; }),
                    backgroundColor: categories.map(function(category, index) { return getCategoryColor(category, index); }),
                    borderColor: '#ffffff',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '66%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var value = Number(context.raw || 0);
                                var totalValue = categories.reduce(function(sum, category) {
                                    return sum + Number(category.anzahl || 0);
                                }, 0);
                                var percent = totalValue > 0 ? ((value / totalValue) * 100).toFixed(1) : '0.0';
                                return context.label + ': ' + value + ' Einsätze (' + percent + '%)';
                            }
                        }
                    }
                }
            }
        };
    }

    function getParticipantRankingChartConfigForType(limit, chartMode) {
        var chartLimit = limit || 10;
        var selectedMode = chartMode || 'absolute';
        var labels = participantStats.slice(0, chartLimit).map(function(participant) { return participant.name; });
        var totalAssignments = participantStats.reduce(function(sum, participant) {
            return sum + Number(participant.count || 0);
        }, 0);
        var values = participantStats.slice(0, chartLimit).map(function(participant) {
            var count = Number(participant.count || 0);
            return selectedMode === 'share'
                ? Number((totalAssignments > 0 ? (count / totalAssignments) * 100 : 0).toFixed(1))
                : count;
        });

        return {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: selectedMode === 'share' ? 'Anteil in %' : 'Einsätze',
                    data: values,
                    backgroundColor: '#0a4b78',
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 48
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return selectedMode === 'share'
                                    ? context.raw + '%'
                                    : context.raw + ' Einsätze';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 35,
                            minRotation: 20,
                            color: '#334155',
                            font: { size: 12 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: selectedMode === 'share' ? 1 : 0,
                            callback: function(value) {
                                return selectedMode === 'share' ? value + '%' : value;
                            }
                        }
                    }
                }
            }
        };
    }

    function getLegacyParticipantDistributionChartConfig(periodKey) {
        var dataset = participantDistributionData[periodKey] || { labels: [], percentages: [], counts: [] };
        return {
            type: 'doughnut',
            data: {
                labels: dataset.labels || [],
                datasets: [{
                    data: dataset.percentages || [],
                    counts: dataset.counts || [],
                    backgroundColor: ['#0a4b78', '#206bc4', '#4ea8de', '#8ecae6', '#a7f3d0', '#fde68a', '#fca5a5'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var percentage = context.raw || 0;
                                var counts = context.dataset.counts || [];
                                var count = counts[context.dataIndex] || 0;
                                return context.label + ': ' + percentage + '% (' + count + ')';
                            }
                        }
                    }
                }
            }
        };
    }

    function renderLegacyParticipantDistributionSummary(periodKey) {
        var root = document.getElementById('feu-einsatz-distribution-summary');
        var dataset = participantDistributionData[periodKey] || { labels: [], counts: [], percentages: [], total_calls: 0, label: '' };
        var html = '';

        if (!root) {
            return;
        }

        html += '<div class="feu-einsatz-distribution-summary-head">';
        html += '<strong>' + (dataset.label || '') + '</strong>';
        html += '<span>' + (dataset.total_calls || 0) + ' Einsätze</span>';
        html += '</div>';

        if (!dataset.labels || !dataset.labels.length) {
            html += '<p class="description">Keine Daten für diesen Zeitraum vorhanden.</p>';
            root.innerHTML = html;
            return;
        }

        html += '<div class="feu-einsatz-distribution-summary-list">';
        dataset.labels.forEach(function(label, index) {
            var count = dataset.counts[index] || 0;
            var percentage = dataset.percentages[index] || 0;
            html += '<div class="feu-einsatz-distribution-summary-item">';
            html += '<span>' + label + '</span>';
            html += '<strong>' + percentage + '%</strong>';
            html += '<small>' + count + ' Einsätze</small>';
            html += '</div>';
        });
        html += '</div>';

        root.innerHTML = html;
    }

    function mountLegacyParticipantDistributionCard() {
        return;
        var participantsPanel = document.querySelector('.feu-einsatz-stats-panel[data-panel="participants"] .feu-einsatz-ranking-stage');
        var rankingCard = participantsPanel ? participantsPanel.querySelector('.feu-einsatz-ranking-chart-card') : null;
        var rankingTable = participantsPanel ? participantsPanel.querySelector('.feu-einsatz-table-scroll') : null;
        var rankingStage = participantsPanel;
        var activePeriod = 'all';
        var distributionCard;
        var distributionCanvas;
        var listCard;

        if (!rankingStage || !rankingCard || !rankingTable || !participantStats.length || document.getElementById('participantDistributionChart')) {
            return;
        }

        rankingCard.insertAdjacentHTML('afterend', ''
            + '<div class="feu-einsatz-chart-card feu-einsatz-distribution-chart-card">'
            + '  <div class="feu-einsatz-chart-card-header">'
            + '    <div>'
            + '      <h3>Teilnehmer je Einsatz</h3>'
            + '      <p class="description">Wie viele Personen typischerweise gleichzeitig bei einem Einsatz eingebunden waren.</p>'
            + '    </div>'
            + '    <div class="feu-einsatz-chart-filter" id="feu-einsatz-distribution-period-filter"></div>'
            + '  </div>'
            + '  <div class="feu-einsatz-chart-canvas-wrap feu-einsatz-chart-canvas-wrap-compact">'
            + '    <canvas id="participantDistributionChart" height="72"></canvas>'
            + '  </div>'
            + '  <div class="feu-einsatz-distribution-summary" id="feu-einsatz-distribution-summary"></div>'
            + '</div>'
        );

        rankingTable.insertAdjacentHTML('beforebegin', ''
            + '<div class="feu-einsatz-ranking-list-card">'
            + '  <div class="feu-einsatz-chart-card-header">'
            + '    <div>'
            + '      <h3>Top Teilnehmer</h3>'
            + '      <p class="description">Kompakte Übersicht der aktivsten Teilnehmer mit direktem Vergleich.</p>'
            + '    </div>'
            + '  </div>'
            + '  <div class="feu-einsatz-ranking-list" id="feu-einsatz-ranking-list"></div>'
            + '</div>'
        );

        distributionCard = document.getElementById('participantDistributionChart');
        listCard = document.getElementById('feu-einsatz-ranking-list');

        if (listCard) {
            var maxCount = participantStats.reduce(function(max, participant) {
                return Math.max(max, Number(participant.count || 0));
            }, 0);
            listCard.innerHTML = participantStats.slice(0, 10).map(function(participant, index) {
                var width = maxCount > 0 ? Math.round((Number(participant.count || 0) / maxCount) * 100) : 0;
                return ''
                    + '<div class="feu-einsatz-ranking-list-item">'
                    + '  <div class="feu-einsatz-ranking-list-copy"><span class="feu-einsatz-ranking-list-rank">#' + (index + 1) + '</span><strong>' + participant.name + '</strong></div>'
                    + '  <div class="feu-einsatz-ranking-meter"><span class="feu-einsatz-ranking-meter-track"><span class="feu-einsatz-ranking-meter-fill" style="width:' + width + '%;"></span></span><span class="feu-einsatz-ranking-meter-value">' + participant.count + '</span></div>'
                    + '</div>';
            }).join('');
        }

        var filterRoot = document.getElementById('feu-einsatz-distribution-period-filter');
        if (filterRoot) {
            Object.keys(participantDistributionData).forEach(function(periodKey) {
                var label = participantDistributionData[periodKey] && participantDistributionData[periodKey].label ? participantDistributionData[periodKey].label : periodKey;
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'button feu-einsatz-chart-filter-button' + (periodKey === activePeriod ? ' is-active' : '');
                button.setAttribute('data-period', periodKey);
                button.textContent = label;
                button.addEventListener('click', function() {
                    activePeriod = periodKey;
                    filterRoot.querySelectorAll('.feu-einsatz-chart-filter-button').forEach(function(toggleButton) {
                        toggleButton.classList.toggle('is-active', toggleButton.getAttribute('data-period') === periodKey);
                    });
                    if (participantDistributionChart) {
                        participantDistributionChart.destroy();
                    }
                    participantDistributionChart = new Chart(distributionCard.getContext('2d'), getParticipantDistributionChartConfig(activePeriod));
                    renderParticipantDistributionSummary(activePeriod);
                });
                filterRoot.appendChild(button);
            });
        }

        if (window.Chart && distributionCard) {
            participantDistributionChart = new Chart(distributionCard.getContext('2d'), getParticipantDistributionChartConfig(activePeriod));
            renderParticipantDistributionSummary(activePeriod);
        }
    }

    function createChartModeToggle(options) {
        if (!options || !options.canvas || !options.canvas.parentNode) {
            return;
        }

        var toolbar = document.createElement('div');
        toolbar.className = 'feu-admin-chart-switcher';

        options.modes.forEach(function(mode) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'button feu-admin-chart-switcher-btn' + (mode.key === options.defaultMode ? ' is-active' : '');
            button.textContent = mode.label;
            button.addEventListener('click', function() {
                toolbar.querySelectorAll('.feu-admin-chart-switcher-btn').forEach(function(toggleButton) {
                    toggleButton.classList.remove('is-active');
                });
                button.classList.add('is-active');
                options.onSelect(mode.key);
            });
            toolbar.appendChild(button);
        });

        options.canvas.parentNode.parentNode.insertBefore(toolbar, options.canvas.parentNode);
    }

    function mountMonthlyChartCard() {
        var calendarPanel = document.querySelector('.feu-einsatz-stats-panel[data-panel="calendar"] .feu-einsatz-panel-card');
        var activeDaysCard = calendarPanel ? calendarPanel.querySelector('.feu-einsatz-active-days-card') : null;

        if (!calendarPanel || !activeDaysCard || document.getElementById('feu-einsatz-monthly-totals-chart')) {
            return;
        }

        var card = document.createElement('div');
        card.className = 'feu-einsatz-chart-card feu-einsatz-chart-card--monthly';
        card.innerHTML = ''
            + '<div class="feu-einsatz-chart-card-header">'
            + '  <div>'
            + '    <h3>Monatsverlauf</h3>'
            + '    <p class="description">Wechseln Sie zwischen Verlauf und Vergleich der Monatswerte.</p>'
            + '  </div>'
            + '</div>'
            + '<div class="feu-einsatz-chart-canvas-wrap feu-einsatz-chart-canvas-wrap-monthly">'
            + '  <canvas id="feu-einsatz-monthly-totals-chart" height="72"></canvas>'
            + '</div>';

        calendarPanel.insertBefore(card, activeDaysCard);

        var monthlyCanvas = document.getElementById('feu-einsatz-monthly-totals-chart');
        if (window.Chart && monthlyCanvas) {
            monthlyChart = new Chart(monthlyCanvas.getContext('2d'), getMonthlyTotalsChartConfig('line'));
            createChartModeToggle({
                canvas: monthlyCanvas,
                defaultMode: 'line',
                modes: [
                    { key: 'line', label: 'Linie' },
                    { key: 'bar', label: 'Balken' }
                ],
                onSelect: function(mode) {
                    if (monthlyChart) {
                        monthlyChart.destroy();
                    }
                    monthlyChart = new Chart(monthlyCanvas.getContext('2d'), getMonthlyTotalsChartConfig(mode));
                }
            });
        }
    }

    var admindekMonthlyChartElement = document.getElementById('feu-einsatz-admindek-monthly-chart');
    if (window.Chart && admindekMonthlyChartElement) {
        new Chart(admindekMonthlyChartElement.getContext('2d'), getAdmindekMonthlyChartConfig());
    }

    var admindekCategoryChartElement = document.getElementById('feu-einsatz-admindek-category-chart');
    if (window.Chart && admindekCategoryChartElement && categories.length) {
        new Chart(admindekCategoryChartElement.getContext('2d'), getAdmindekCategoryChartConfig());
    }

    var chartElement = document.getElementById('categoryDonutChart');
    if (window.Chart && chartElement && categories.length) {
        categoryChart = new Chart(chartElement.getContext('2d'), getCategoryChartConfigForType('doughnut'));
        createChartModeToggle({
            canvas: chartElement,
            defaultMode: 'doughnut',
            modes: [
                { key: 'doughnut', label: 'Donut' },
                { key: 'bar', label: 'Balken' },
                { key: 'polarArea', label: 'Polar' }
            ],
            onSelect: function(mode) {
                if (categoryChart) {
                    categoryChart.destroy();
                }
                categoryChart = new Chart(chartElement.getContext('2d'), getCategoryChartConfigForType(mode));
            }
        });
    }

    var weekdayChartElement = document.getElementById('dailyTimelineChart');
    if (window.Chart && weekdayChartElement) {
        weekdayChart = new Chart(weekdayChartElement.getContext('2d'), getDailyTimelineChartConfigForType('bar'));
        createChartModeToggle({
            canvas: weekdayChartElement,
            defaultMode: 'bar',
            modes: [
                { key: 'bar', label: 'Balken' },
                { key: 'line', label: 'Linie' }
            ],
            onSelect: function(mode) {
                if (weekdayChart) {
                    weekdayChart.destroy();
                }
                weekdayChart = new Chart(weekdayChartElement.getContext('2d'), getDailyTimelineChartConfigForType(mode));
            }
        });
    }

    var participantRankingChartElement = document.getElementById('participantRankingChart');
    if (window.Chart && participantRankingChartElement && participantStats.length) {
        participantRankingChart = new Chart(participantRankingChartElement.getContext('2d'), getParticipantRankingChartConfigForType(10, 'absolute'));
    }

    function getParticipantShareChartConfig(limit) {
        var chartLimit = limit || 8;
        var labels = participantStats.slice(0, chartLimit).map(function(participant) { return participant.name; });
        var totalAssignments = participantStats.reduce(function(sum, participant) {
            return sum + Number(participant.count || 0);
        }, 0);
        var values = participantStats.slice(0, chartLimit).map(function(participant) {
            var count = Number(participant.count || 0);
            return Number((totalAssignments > 0 ? (count / totalAssignments) * 100 : 0).toFixed(1));
        });

        return {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: ['#0a4b78', '#206bc4', '#4ea8de', '#8ecae6', '#a7f3d0', '#fde68a', '#fca5a5', '#cbd5e1'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                }
            }
        };
    }

    function getParticipantDistributionChartConfig(periodKey) {
        var dataset = participantDistributionData[periodKey] || { labels: [], percentages: [], counts: [] };
        return {
            type: 'bar',
            data: {
                labels: dataset.labels || [],
                datasets: [{
                    data: dataset.percentages || [],
                    counts: dataset.counts || [],
                    backgroundColor: '#0a4b78',
                    borderRadius: 10,
                    borderSkipped: false,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                var percentage = context.raw || 0;
                                var counts = context.dataset.counts || [];
                                var count = counts[context.dataIndex] || 0;
                                return context.label + ': ' + percentage + '% (' + count + ' Einsätze)';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        };
    }

    function renderParticipantDistributionSummary(periodKey) {
        var root = document.getElementById('feu-einsatz-distribution-summary');
        var dataset = participantDistributionData[periodKey] || { labels: [], counts: [], percentages: [], total_calls: 0, label: '' };
        var html = '';

        if (!root) {
            return;
        }

        html += '<div class="feu-einsatz-distribution-summary-head">';
        html += '<strong>' + (dataset.label || '') + '</strong>';
        html += '<span>' + (dataset.total_calls || 0) + ' Einsätze</span>';
        html += '</div>';

        if (!dataset.labels || !dataset.labels.length) {
            html += '<p class="description">Keine Daten für diesen Zeitraum vorhanden.</p>';
            root.innerHTML = html;
            return;
        }

        html += '<div class="feu-einsatz-distribution-summary-list">';
        dataset.labels.forEach(function(label, index) {
            var count = dataset.counts[index] || 0;
            var percentage = dataset.percentages[index] || 0;
            html += '<div class="feu-einsatz-distribution-summary-item">';
            html += '<span>' + label + '</span>';
            html += '<strong>' + percentage + '%</strong>';
            html += '<small>' + count + ' Einsätze</small>';
            html += '</div>';
        });
        html += '</div>';
        root.innerHTML = html;
    }

    function mountParticipantShareCard() {
        return;
    }

    function mountParticipantDistributionCard() {
        return;

        rankingTable.insertAdjacentHTML('beforebegin', ''
            + '<div class="feu-einsatz-chart-card feu-einsatz-distribution-chart-card">'
            + '  <div class="feu-einsatz-chart-card-header">'
            + '    <div>'
            + '      <h3>Teilnehmer je Einsatz</h3>'
            + '      <p class="description">Verteilung der Einsatzgroessen nach Anzahl gleichzeitig eingebundener Personen.</p>'
            + '    </div>'
            + '    <div class="feu-einsatz-chart-filter" id="feu-einsatz-distribution-period-filter"></div>'
            + '  </div>'
            + '  <div class="feu-einsatz-chart-canvas-wrap feu-einsatz-chart-canvas-wrap-compact">'
            + '    <canvas id="participantDistributionChart" height="72"></canvas>'
            + '  </div>'
            + '  <div class="feu-einsatz-distribution-summary" id="feu-einsatz-distribution-summary"></div>'
            + '</div>'
        );

        distributionCanvas = document.getElementById('participantDistributionChart');
        var filterRoot = document.getElementById('feu-einsatz-distribution-period-filter');
        if (filterRoot) {
            Object.keys(participantDistributionData).forEach(function(periodKey) {
                var label = participantDistributionData[periodKey] && participantDistributionData[periodKey].label ? participantDistributionData[periodKey].label : periodKey;
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'button feu-einsatz-chart-filter-button' + (periodKey === activePeriod ? ' is-active' : '');
                button.setAttribute('data-period', periodKey);
                button.textContent = label;
                button.addEventListener('click', function() {
                    activePeriod = periodKey;
                    filterRoot.querySelectorAll('.feu-einsatz-chart-filter-button').forEach(function(toggleButton) {
                        toggleButton.classList.toggle('is-active', toggleButton.getAttribute('data-period') === periodKey);
                    });
                    if (participantDistributionChart) {
                        participantDistributionChart.destroy();
                    }
                    participantDistributionChart = new Chart(distributionCanvas.getContext('2d'), getParticipantDistributionChartConfig(activePeriod));
                    renderParticipantDistributionSummary(activePeriod);
                });
                filterRoot.appendChild(button);
            });
        }

        if (window.Chart && distributionCanvas) {
            participantDistributionChart = new Chart(distributionCanvas.getContext('2d'), getParticipantDistributionChartConfig(activePeriod));
            renderParticipantDistributionSummary(activePeriod);
        }
    }

    mountMonthlyChartCard();

    if (participantRankingChart) {
        participantRankingChart.destroy();
        participantRankingChart = new Chart(participantRankingChartElement.getContext('2d'), getParticipantRankingChartConfigForType(10, 'absolute'));
    }

    var rankingToolbar = participantRankingChartElement && participantRankingChartElement.parentNode
        ? participantRankingChartElement.parentNode.previousElementSibling
        : null;
    if (rankingToolbar && rankingToolbar.classList.contains('feu-admin-chart-switcher')) {
        rankingToolbar.remove();
    }

    mountParticipantDistributionCard();

    function getActivityPointLabel(point) {
        return String(point.street || point.street_name || point.city || point.title || 'Einsatzort').trim();
    }

    function getActivityPointKey(point) {
        var label = getActivityPointLabel(point).toLowerCase();
        var city = String(point.city || '').trim().toLowerCase();
        var latitude = parseFloat(point.latitude);
        var longitude = parseFloat(point.longitude);

        if (label || city) {
            return label + '|' + city;
        }

        if (isFinite(latitude) && isFinite(longitude)) {
            return latitude.toFixed(6) + '|' + longitude.toFixed(6);
        }

        return String(point.id || point.title || Math.random());
    }

    function buildActivityClusters(points) {
        var clusterLookup = {};
        var orderedClusters = [];

        points.forEach(function(point) {
            var latitude = parseFloat(point.latitude);
            var longitude = parseFloat(point.longitude);

            if (!isFinite(latitude) || !isFinite(longitude)) {
                return;
            }

            var key = getActivityPointKey(point);

            if (!clusterLookup[key]) {
                clusterLookup[key] = {
                    key: key,
                    center: { latitude: latitude, longitude: longitude },
                    count: 0,
                    points: [],
                    label: getActivityPointLabel(point),
                    city: String(point.city || '').trim()
                };
                orderedClusters.push(clusterLookup[key]);
            }

            clusterLookup[key].points.push(point);
            clusterLookup[key].count += 1;

            var latSum = 0;
            var lngSum = 0;
            clusterLookup[key].points.forEach(function(item) {
                latSum += parseFloat(item.latitude) || 0;
                lngSum += parseFloat(item.longitude) || 0;
            });

            clusterLookup[key].center.latitude = latSum / clusterLookup[key].points.length;
            clusterLookup[key].center.longitude = lngSum / clusterLookup[key].points.length;
        });

        return orderedClusters;
    }

    function getActivityClusters() {
        if (!activityClusters.length && activityMapPoints.length) {
            activityClusters = buildActivityClusters(activityMapPoints);
        }

        return activityClusters;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getActivityMarkerPalette(cluster, maxClusterCount) {
        var count = parseInt(cluster.count || 0, 10);
        var intensity = maxClusterCount > 0 ? (count / maxClusterCount) : 0;

        return {
            color: intensity > 0.66 ? '#c53030' : (intensity > 0.33 ? '#d69e2e' : '#0a4b78'),
            ring: '#ffffff',
            shadow: intensity > 0.66 ? 'rgba(197,48,48,0.24)' : (intensity > 0.33 ? 'rgba(214,158,46,0.24)' : 'rgba(10,75,120,0.24)')
        };
    }

    function getActivityMarkerSize(cluster, maxClusterCount) {
        var count = parseInt(cluster.count || 0, 10);
        var intensity = maxClusterCount > 0 ? (count / maxClusterCount) : 0;

        return Math.max(18, Math.min(34, 18 + Math.round(intensity * 10) + ((count > 1 ? count - 1 : 0) * 2)));
    }

    function getActivityMarkerIcon(cluster, maxClusterCount) {
        var size = getActivityMarkerSize(cluster, maxClusterCount);
        var palette = getActivityMarkerPalette(cluster, maxClusterCount);
        var badge = '';

        if ((cluster.count || 0) > 1) {
            badge = '<span class="feu-einsatz-activity-address-marker-count">' + escapeHtml(String(cluster.count)) + '</span>';
        }

        return L.divIcon({
            className: 'feu-einsatz-activity-address-marker-wrap',
            html: ''
                + '<span class="feu-einsatz-activity-address-marker"'
                + ' style="--feu-marker-size:' + size + 'px;--feu-marker-color:' + palette.color + ';--feu-marker-ring:' + palette.ring + ';--feu-marker-shadow:' + palette.shadow + ';">'
                + '<span class="feu-einsatz-activity-address-marker-pin"></span>'
                + badge
                + '</span>',
            iconSize: [size, size + 14],
            iconAnchor: [Math.round(size / 2), size + 14],
            popupAnchor: [0, -(size + 8)],
            tooltipAnchor: [0, -(size + 6)]
        });
    }

    function getActivityClusterPopup(cluster) {
        var addressLabel = getClusterPrimaryLabel(cluster);
        var cityLabel = String(cluster.city || '').trim();
        var entryList = cluster.points.slice(0, 6).map(function(point) {
            var dateLabel = String(point.date_label || point.date || '').trim();
            var titleLabel = String(point.title || '').trim();
            var postUrl = String(point.url || point.edit_url || '').trim();
            var line = '';

            if (dateLabel) {
                line += escapeHtml(dateLabel);
            }

            if (titleLabel) {
                line += (line ? ' - ' : '') + escapeHtml(titleLabel);
            }

            if (!line) {
                return '';
            }

            return '<li>'
                + (postUrl
                    ? '<a href="' + escapeHtml(postUrl) + '" target="_blank" rel="noopener noreferrer">' + line + '</a>'
                    : '<span>' + line + '</span>')
                + '</li>';
        }).join('');
        var moreCount = cluster.points.length > 6 ? cluster.points.length - 6 : 0;

        return ''
            + '<div class="feu-einsatz-map-popup">'
            + '<h4>' + escapeHtml(addressLabel) + '</h4>'
            + (cityLabel ? '<p><strong>' + escapeHtml('Ort') + ':</strong> ' + escapeHtml(cityLabel) + '</p>' : '')
            + '<p><strong>' + escapeHtml('Einsätze') + ':</strong> ' + escapeHtml(String(cluster.count || 0)) + '</p>'
            + (entryList ? '<ul>' + entryList + (moreCount > 0 ? '<li>+' + escapeHtml(String(moreCount)) + ' weitere</li>' : '') + '</ul>' : '')
            + '</div>';
    }

    function renderActivityMap() {
        var mapElement = document.getElementById('feu-einsatz-activity-map');
        var fullscreenButton = document.getElementById('feu-einsatz-activity-map-fullscreen');
        if (!mapElement || !activityMapPoints.length) {
            return;
        }

        activityClusters = getActivityClusters();

        if (activityMapInstance && typeof activityMapInstance.invalidateSize === 'function') {
            window.setTimeout(function() {
                activityMapInstance.invalidateSize();
            }, 80);
            return;
        }

        if (!window.L || !activityClusters.length) {
            if (!mapElement.querySelector('.feu-einsatz-activity-map-svg') && !mapElement.querySelector('.feu-einsatz-activity-map-image')) {
                var snapshotCanvas = createActivitySnapshotCanvas();
                var previewImage = document.createElement('img');

                previewImage.src = snapshotCanvas.toDataURL('image/png');
                previewImage.alt = 'Lokale Aktivitätskarte für ' + selectedYear;
                previewImage.className = 'feu-einsatz-activity-map-image';
                mapElement.appendChild(previewImage);
            }

            return;
        }

        mapElement.innerHTML = '';

        activityMapInstance = L.map(mapElement, {
            scrollWheelZoom: false,
            zoomControl: true,
            attributionControl: true
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(activityMapInstance);

        var maxClusterCount = activityClusters.reduce(function(maxValue, cluster) {
            return Math.max(maxValue, parseInt(cluster.count || 0, 10));
        }, 0);
        var bounds = [];

        activityClusters.forEach(function(cluster) {
            var latitude = parseFloat(cluster.center.latitude);
            var longitude = parseFloat(cluster.center.longitude);

            if (!isFinite(latitude) || !isFinite(longitude)) {
                return;
            }

            var marker = L.marker([latitude, longitude], {
                icon: getActivityMarkerIcon(cluster, maxClusterCount),
                keyboard: true
            }).addTo(activityMapInstance);
            marker.bindPopup(getActivityClusterPopup(cluster), {
                maxWidth: 320
            });
            marker.bindTooltip(getClusterPrimaryLabel(cluster), {
                direction: 'top',
                offset: [0, -10]
            });

            bounds.push([latitude, longitude]);
        });

        if (bounds.length === 1) {
            activityMapInstance.setView(bounds[0], 15);
        } else if (bounds.length > 1) {
            activityMapInstance.fitBounds(bounds, {
                padding: [36, 36],
                maxZoom: 15
            });
        }

        window.setTimeout(function() {
            if (activityMapInstance) {
                activityMapInstance.invalidateSize();
            }
        }, 80);

        if (fullscreenButton && !fullscreenButton.dataset.feuBound) {
            fullscreenButton.dataset.feuBound = '1';
            fullscreenButton.addEventListener('click', function() {
                if (document.fullscreenElement === mapElement) {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    }
                    return;
                }

                if (mapElement.requestFullscreen) {
                    mapElement.requestFullscreen();
                }
            });

            document.addEventListener('fullscreenchange', function() {
                var isFullscreen = document.fullscreenElement === mapElement;
                fullscreenButton.textContent = isFullscreen ? 'Schließen' : 'Vollbild';

                if (activityMapInstance) {
                    window.setTimeout(function() {
                        activityMapInstance.invalidateSize();

                        if (bounds.length === 1) {
                            activityMapInstance.setView(bounds[0], 15);
                        } else if (bounds.length > 1) {
                            activityMapInstance.fitBounds(bounds, {
                                padding: [36, 36],
                                maxZoom: 15
                            });
                        }
                    }, 80);
                }
            });
        }
    }

    function syncStatisticsPresentationState() {
        var toggleButton = document.getElementById('feu-einsatz-statistics-presentation-toggle');
        var deck = document.getElementById('feu-einsatz-statistics-presentation-deck');
        var isPresentation = !!(deck && !deck.hidden);

        if (toggleButton) {
            toggleButton.textContent = isPresentation ? 'Präsentation schließen' : 'Präsentation';
        }

        if (activityMapInstance && typeof activityMapInstance.invalidateSize === 'function') {
            window.setTimeout(function() {
                activityMapInstance.invalidateSize();
            }, 120);
        }
    }

    function setStatisticsPresentationSlide(index) {
        var deck = document.getElementById('feu-einsatz-statistics-presentation-deck');
        var slides = deck ? Array.prototype.slice.call(deck.querySelectorAll('[data-feu-presentation-slide]')) : [];
        var current = deck ? deck.querySelector('[data-feu-presentation-current]') : null;
        var total = deck ? deck.querySelector('[data-feu-presentation-total]') : null;

        if (!deck || !slides.length) {
            return;
        }

        index = Math.max(0, Math.min(slides.length - 1, index));
        deck.dataset.currentSlide = String(index);

        slides.forEach(function(slide, slideIndex) {
            slide.classList.toggle('is-active', slideIndex === index);
        });

        if (current) {
            current.textContent = String(index + 1);
        }

        if (total) {
            total.textContent = String(slides.length);
        }

        if (slides[index] && slides[index].dataset.feuPresentationKind === 'activity-map') {
            renderStatisticsPresentationMap();
        }
    }

    function renderStatisticsPresentationMap() {
        var mapRoot = document.getElementById('feu-einsatz-presentation-activity-map');
        var liveMapElement = document.getElementById('feu-einsatz-presentation-activity-live-map');
        var fallback = mapRoot ? mapRoot.querySelector('.feu-einsatz-presentation-map-fallback') : null;
        var clusters = getActivityClusters();
        var hasStationFeature = presentationStationFeature
            && typeof presentationStationFeature === 'object'
            && isFinite(parseFloat(presentationStationFeature.latitude))
            && isFinite(parseFloat(presentationStationFeature.longitude));
        var maxClusterCount;
        var bounds = [];

        if (!mapRoot || !liveMapElement || (!activityMapPoints.length && !hasStationFeature)) {
            return;
        }

        if (!window.L || (!clusters.length && !hasStationFeature)) {
            return;
        }

        if (fallback) {
            fallback.hidden = true;
        }

        if (presentationActivityMapInstance && typeof presentationActivityMapInstance.invalidateSize === 'function') {
            window.setTimeout(function() {
                presentationActivityMapInstance.invalidateSize();
            }, 120);
            return;
        }

        presentationActivityMapInstance = L.map(liveMapElement, {
            scrollWheelZoom: false,
            zoomControl: true,
            attributionControl: true
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(presentationActivityMapInstance);

        maxClusterCount = clusters.reduce(function(maxValue, cluster) {
            return Math.max(maxValue, parseInt(cluster.count || 0, 10));
        }, 0);

        clusters.forEach(function(cluster) {
            var latitude = parseFloat(cluster.center.latitude);
            var longitude = parseFloat(cluster.center.longitude);
            var marker;

            if (!isFinite(latitude) || !isFinite(longitude)) {
                return;
            }

            marker = L.marker([latitude, longitude], {
                icon: getActivityMarkerIcon(cluster, maxClusterCount),
                title: getClusterPrimaryLabel(cluster)
            }).addTo(presentationActivityMapInstance);

            marker.bindPopup(getActivityClusterPopup(cluster));
            marker.bindTooltip(getClusterPrimaryLabel(cluster), {
                direction: 'top',
                offset: [0, -12],
                opacity: 0.92
            });
            bounds.push([latitude, longitude]);
        });

        addPresentationStationMarker(bounds);

        if (bounds.length > 1) {
            presentationActivityMapInstance.fitBounds(bounds, { padding: [36, 36], maxZoom: 15 });
        } else if (bounds.length === 1) {
            presentationActivityMapInstance.setView(bounds[0], 14);
        }

        window.setTimeout(function() {
            presentationActivityMapInstance.invalidateSize();
        }, 160);
    }

    function addPresentationStationMarker(bounds) {
        var station = presentationStationFeature && typeof presentationStationFeature === 'object' ? presentationStationFeature : null;
        var latitude = station ? parseFloat(station.latitude) : NaN;
        var longitude = station ? parseFloat(station.longitude) : NaN;
        var label = station && station.label ? String(station.label) : 'Feuerwehrhaus';
        var logoUrl = station && station.logo_url ? String(station.logo_url) : '';
        var logoSize = station && station.logo_size ? parseInt(station.logo_size, 10) : 40;

        if (!presentationActivityMapInstance || !isFinite(latitude) || !isFinite(longitude)) {
            return;
        }

        logoSize = Math.max(20, Math.min(96, logoSize || 40));

        if (logoUrl) {
            L.marker([latitude, longitude], {
                icon: L.divIcon({
                    className: 'feu-einsatz-area-station-icon-shell',
                    html: '<span class="feu-einsatz-area-station-icon" style="width:' + logoSize + 'px;height:' + logoSize + 'px;"><img src="' + escapeHtml(logoUrl) + '" alt="' + escapeHtml(label) + '" /></span>',
                    iconSize: [logoSize, logoSize],
                    iconAnchor: [logoSize / 2, logoSize / 2]
                }),
                interactive: false,
                keyboard: false
            }).addTo(presentationActivityMapInstance);
        } else {
            L.circleMarker([latitude, longitude], {
                radius: 10,
                color: '#ffffff',
                weight: 3,
                fillColor: '#c53030',
                fillOpacity: 1,
                interactive: false
            }).addTo(presentationActivityMapInstance);
        }

        bounds.push([latitude, longitude]);
    }

    function toggleStatisticsPresentation() {
        var deck = document.getElementById('feu-einsatz-statistics-presentation-deck');

        if (!deck) {
            return;
        }

        if (!deck.hidden) {
            deck.hidden = true;
            document.body.classList.remove('feu-einsatz-statistics-presentation');

            if (document.fullscreenElement && document.exitFullscreen) {
                document.exitFullscreen().catch(function() {});
            }

            syncStatisticsPresentationState();
            return;
        }

        deck.hidden = false;
        setStatisticsPresentationSlide(parseInt(deck.dataset.currentSlide || '0', 10) || 0);
        document.body.classList.add('feu-einsatz-statistics-presentation');

        if (deck.requestFullscreen) {
            deck.requestFullscreen().catch(function() {
                syncStatisticsPresentationState();
            });
        }

        syncStatisticsPresentationState();
    }

    function ensureActivityMap() {
        if (!activityMapPoints.length) {
            return;
        }

        if (!activityMapRendered) {
            renderActivityMap();
            activityMapRendered = true;
            return;
        }

        if (activityMapInstance && typeof activityMapInstance.invalidateSize === 'function') {
            window.setTimeout(function() {
                activityMapInstance.invalidateSize();
            }, 80);
        }
    }

    function sanitizePdfText(value) {
        return String(value || '')
            .replace(/ä/g, 'ae')
            .replace(/ö/g, 'oe')
            .replace(/ü/g, 'ue')
            .replace(/Ä/g, 'Ae')
            .replace(/Ö/g, 'Oe')
            .replace(/Ü/g, 'Ue')
            .replace(/ß/g, 'ss')
            .replace(/[–—]/g, '-');
    }

    function roundRect(ctx, x, y, width, height, radius, fill, stroke) {
        ctx.beginPath();
        ctx.moveTo(x + radius, y);
        ctx.lineTo(x + width - radius, y);
        ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
        ctx.lineTo(x + width, y + height - radius);
        ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
        ctx.lineTo(x + radius, y + height);
        ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
        ctx.lineTo(x, y + radius);
        ctx.quadraticCurveTo(x, y, x + radius, y);
        ctx.closePath();

        if (fill) {
            ctx.fill();
        }

        if (stroke) {
            ctx.stroke();
        }
    }

    function renderChartToCanvas(config, width, height) {
        if (!window.Chart) {
            return null;
        }

        var canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        var chartConfig = JSON.parse(JSON.stringify(config));
        chartConfig.options = chartConfig.options || {};
        chartConfig.options.animation = false;
        chartConfig.options.responsive = false;
        chartConfig.options.maintainAspectRatio = false;

        var chart = new Chart(canvas.getContext('2d'), chartConfig);
        chart.update('none');
        chart.destroy();

        return canvas;
    }

    function getClusterPrimaryLabel(cluster) {
        if (cluster.label) {
            return cluster.label;
        }

        if (cluster.points && cluster.points.length) {
            return getActivityPointLabel(cluster.points[0]);
        }

        return 'Einsatzort';
    }

    function drawActivityMarkerOnCanvas(ctx, x, y, cluster, maxClusterCount) {
        var size = getActivityMarkerSize(cluster, maxClusterCount);
        var palette = getActivityMarkerPalette(cluster, maxClusterCount);
        var radius = size / 2;
        var centerY = y - Math.max(8, radius * 0.75);
        var pointerWidth = Math.max(6, radius * 0.55);
        var innerRadius = Math.max(4, radius * 0.36);

        ctx.fillStyle = palette.color;
        ctx.strokeStyle = palette.ring;
        ctx.lineWidth = 4;

        ctx.beginPath();
        ctx.arc(x, centerY, radius, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();

        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineTo(x - pointerWidth, centerY + radius * 0.42);
        ctx.lineTo(x + pointerWidth, centerY + radius * 0.42);
        ctx.closePath();
        ctx.fill();
        ctx.stroke();

        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.arc(x, centerY, innerRadius, 0, Math.PI * 2);
        ctx.fill();

        if ((cluster.count || 0) > 1) {
            var badgeRadius = Math.max(9, Math.min(14, radius * 0.6));
            var badgeX = x + radius * 0.82;
            var badgeY = centerY - radius * 0.72;

            ctx.fillStyle = '#ffffff';
            ctx.strokeStyle = palette.color;
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.arc(badgeX, badgeY, badgeRadius, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();

            ctx.fillStyle = '#0f172a';
            ctx.font = '700 ' + Math.max(11, badgeRadius) + 'px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(String(cluster.count), badgeX, badgeY + 0.5);
            ctx.textAlign = 'left';
            ctx.textBaseline = 'alphabetic';
        }
    }

    function createActivitySnapshotCanvas() {
        var clusters = getActivityClusters();
        var canvas = document.createElement('canvas');
        var width = 1600;
        var height = 920;
        var ctx = canvas.getContext('2d');

        canvas.width = width;
        canvas.height = height;

        var gradient = ctx.createLinearGradient(0, 0, width, height);
        gradient.addColorStop(0, '#f8fbff');
        gradient.addColorStop(1, '#edf4fb');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, width, height);

        ctx.fillStyle = '#0f172a';
        ctx.font = '700 44px Arial';
        ctx.fillText('Aktivitätskarte', 72, 84);
        ctx.fillStyle = '#475569';
        ctx.font = '24px Arial';
        ctx.fillText('Zonen mit hoechster Einsatzdichte im Jahr ' + sanitizePdfText(selectedYear), 72, 122);

        var frameX = 72;
        var frameY = 170;
        var frameWidth = width - 144;
        var frameHeight = 620;

        ctx.fillStyle = '#ffffff';
        ctx.strokeStyle = '#cbd5e1';
        ctx.lineWidth = 3;
        roundRect(ctx, frameX, frameY, frameWidth, frameHeight, 28, true, true);

        if (!clusters.length) {
            ctx.fillStyle = '#64748b';
            ctx.font = '32px Arial';
            ctx.fillText('Keine Koordinaten für dieses Jahr vorhanden.', frameX + 56, frameY + 110);
            return canvas;
        }

        var minLat = Math.min.apply(null, clusters.map(function(cluster) { return cluster.center.latitude; }));
        var maxLat = Math.max.apply(null, clusters.map(function(cluster) { return cluster.center.latitude; }));
        var minLng = Math.min.apply(null, clusters.map(function(cluster) { return cluster.center.longitude; }));
        var maxLng = Math.max.apply(null, clusters.map(function(cluster) { return cluster.center.longitude; }));
        var latRange = Math.max(maxLat - minLat, 0.01);
        var lngRange = Math.max(maxLng - minLng, 0.01);
        var maxClusterCount = clusters.reduce(function(maxValue, cluster) {
            return Math.max(maxValue, cluster.count);
        }, 1);

        for (var gx = 0; gx <= 5; gx++) {
            var x = frameX + ((frameWidth / 5) * gx);
            ctx.strokeStyle = 'rgba(148, 163, 184, 0.22)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(x, frameY);
            ctx.lineTo(x, frameY + frameHeight);
            ctx.stroke();
        }

        for (var gy = 0; gy <= 4; gy++) {
            var y = frameY + ((frameHeight / 4) * gy);
            ctx.strokeStyle = 'rgba(148, 163, 184, 0.22)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(frameX, y);
            ctx.lineTo(frameX + frameWidth, y);
            ctx.stroke();
        }

        clusters.forEach(function(cluster) {
            var relativeX = (cluster.center.longitude - minLng) / lngRange;
            var relativeY = (cluster.center.latitude - minLat) / latRange;
            var x = frameX + 70 + (relativeX * (frameWidth - 140));
            var y = frameY + frameHeight - 70 - (relativeY * (frameHeight - 140));
            var label = getClusterPrimaryLabel(cluster);
            var markerSize = getActivityMarkerSize(cluster, maxClusterCount);

            drawActivityMarkerOnCanvas(ctx, x, y, cluster, maxClusterCount);

            ctx.fillStyle = '#0f172a';
            ctx.font = '600 20px Arial';
            ctx.fillText(sanitizePdfText(label).slice(0, 26), x + markerSize + 18, y - 8);
            ctx.fillStyle = '#64748b';
            ctx.font = '18px Arial';
            ctx.fillText(sanitizePdfText((cluster.points[0].city || '')).slice(0, 22), x + markerSize + 18, y + 18);
        });

        ctx.fillStyle = '#334155';
        ctx.font = '20px Arial';
        ctx.fillText('Quelle: Einsatzkoordinaten aus den gespeicherten Einsatzberichten', frameX, height - 48);

        return canvas;
    }

    function drawPdfHeader(pdf, title, subtitle) {
        pdf.setFillColor(10, 75, 120);
        pdf.rect(0, 0, 210, 26, 'F');
        pdf.setTextColor(255, 255, 255);
        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(20);
        pdf.text(sanitizePdfText(title), 14, 16);
        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(9);
        pdf.text(sanitizePdfText(subtitle), 14, 22);
        pdf.setTextColor(23, 23, 23);
    }

    function drawPdfStatCard(pdf, x, y, width, height, label, value, rgb) {
        pdf.setFillColor(rgb[0], rgb[1], rgb[2]);
        pdf.roundedRect(x, y, width, height, 4, 4, 'F');
        pdf.setTextColor(255, 255, 255);
        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(9);
        pdf.text(sanitizePdfText(label), x + 5, y + 8);
        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(22);
        pdf.text(sanitizePdfText(value), x + 5, y + 19);
        pdf.setTextColor(23, 23, 23);
    }

    function drawPdfListCard(pdf, x, y, width, height, title, items) {
        pdf.setFillColor(248, 250, 252);
        pdf.setDrawColor(203, 213, 225);
        pdf.roundedRect(x, y, width, height, 4, 4, 'FD');
        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(12);
        pdf.text(sanitizePdfText(title), x + 5, y + 8);
        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(9);

        var currentY = y + 16;
        items.slice(0, 7).forEach(function(item) {
            var wrapped = pdf.splitTextToSize(sanitizePdfText(item), width - 10);
            pdf.text(wrapped, x + 5, currentY);
            currentY += (wrapped.length * 4.6) + 2;
        });

        if (!items.length) {
            pdf.text(sanitizePdfText('Keine Daten vorhanden.'), x + 5, currentY);
        }
    }

    function addCanvasBlock(pdf, canvas, x, y, width, height, title, description) {
        pdf.setFillColor(255, 255, 255);
        pdf.setDrawColor(220, 220, 222);
        pdf.roundedRect(x, y, width, height, 4, 4, 'FD');
        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(12);
        pdf.text(sanitizePdfText(title), x + 5, y + 8);
        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(9);
        pdf.text(sanitizePdfText(description), x + 5, y + 14);

        if (canvas) {
            pdf.addImage(canvas.toDataURL('image/png'), 'PNG', x + 4, y + 18, width - 8, height - 22, undefined, 'FAST');
        } else {
            pdf.setTextColor(100, 116, 139);
            pdf.text(sanitizePdfText('Keine Grafik verfuegbar.'), x + 5, y + 26);
            pdf.setTextColor(23, 23, 23);
        }
    }

    function addPdfFooters(pdf) {
        var pageCount = pdf.getNumberOfPages();

        for (var page = 1; page <= pageCount; page++) {
            pdf.setPage(page);
            pdf.setDrawColor(226, 232, 240);
            pdf.line(14, 288, 196, 288);
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(8);
            pdf.setTextColor(100, 116, 139);
            pdf.text('Einsatzberichte Statistik', 14, 293);
            pdf.text('Seite ' + page + ' / ' + pageCount, 196, 293, { align: 'right' });
            pdf.setTextColor(23, 23, 23);
        }
    }

    function drawPdfChartFrame(pdf, x, y, width, height, title, description) {
        pdf.setFillColor(255, 255, 255);
        pdf.setDrawColor(220, 220, 222);
        pdf.roundedRect(x, y, width, height, 4, 4, 'FD');
        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(12);
        pdf.text(sanitizePdfText(title), x + 5, y + 8);
        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(9);
        pdf.text(sanitizePdfText(description), x + 5, y + 14);

        return {
            left: x + 8,
            top: y + 22,
            width: width - 16,
            height: height - 28
        };
    }

    function drawPdfHorizontalBarChart(pdf, x, y, width, height, title, description, items, color) {
        var plot = drawPdfChartFrame(pdf, x, y, width, height, title, description);
        var safeItems = (items || []).slice(0, 8);
        var maxValue = safeItems.reduce(function(max, item) {
            return Math.max(max, item.value || 0);
        }, 1);
        var rowHeight = safeItems.length ? Math.max(10, plot.height / safeItems.length) : plot.height;
        var labelWidth = Math.min(54, plot.width * 0.42);
        var barLeft = plot.left + labelWidth + 4;
        var barWidth = Math.max(20, plot.width - labelWidth - 18);

        if (!safeItems.length) {
            pdf.setTextColor(100, 116, 139);
            pdf.text('Keine Daten vorhanden.', plot.left, plot.top + 10);
            pdf.setTextColor(23, 23, 23);
            return;
        }

        safeItems.forEach(function(item, index) {
            var currentY = plot.top + (index * rowHeight) + 2;
            var barHeight = Math.min(7, rowHeight - 3);
            var valueWidth = Math.max(6, (item.value / maxValue) * barWidth);
            var label = pdf.splitTextToSize(sanitizePdfText(item.label), labelWidth - 2).slice(0, 2);

            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(8);
            pdf.text(label, plot.left, currentY + 4);
            pdf.setFillColor(241, 245, 249);
            pdf.roundedRect(barLeft, currentY, barWidth, barHeight, 1.5, 1.5, 'F');
            pdf.setFillColor(color[0], color[1], color[2]);
            pdf.roundedRect(barLeft, currentY, valueWidth, barHeight, 1.5, 1.5, 'F');
            pdf.setFont('helvetica', 'bold');
            pdf.text(String(item.value), barLeft + valueWidth + 2, currentY + 5);
        });
    }

    function drawPdfDailyTimelineChart(pdf, x, y, width, height, title, description, timelineSeries) {
        var plot = drawPdfChartFrame(pdf, x, y, width, height, title, description);
        var values = timelineSeries.values || [];
        var monthMarkers = timelineSeries.monthMarkers || [];
        var maxValue = values.reduce(function(max, value) {
            return Math.max(max, value || 0);
        }, 1);
        var step = values.length ? plot.width / values.length : plot.width;
        var bottom = plot.top + plot.height - 12;
        var top = plot.top + 8;

        if (!values.length) {
            pdf.setTextColor(100, 116, 139);
            pdf.text('Keine Tagesdaten vorhanden.', plot.left, plot.top + 10);
            pdf.setTextColor(23, 23, 23);
            return;
        }

        pdf.setDrawColor(226, 232, 240);
        pdf.line(plot.left, bottom, plot.left + plot.width, bottom);

        values.forEach(function(value, index) {
            var barHeight = maxValue > 0 ? ((value / maxValue) * (bottom - top)) : 0;
            var barX = plot.left + (index * step);
            var barY = bottom - barHeight;

            pdf.setFillColor(value > 0 ? 10 : 203, value > 0 ? 75 : 213, value > 0 ? 120 : 225);
            pdf.rect(barX, barY, Math.max(0.45, step - 0.1), Math.max(0.6, barHeight), 'F');
        });

        monthMarkers.forEach(function(index) {
            var markerX = plot.left + (index * step);
            pdf.setDrawColor(203, 213, 225);
            pdf.line(markerX, top, markerX, bottom + 2);
        });

        ['Jan', 'Apr', 'Jul', 'Okt'].forEach(function(label, idx) {
            var markerIndex = monthMarkers[idx * 3];
            if (typeof markerIndex === 'number') {
                var labelX = plot.left + (markerIndex * step);
                pdf.setFont('helvetica', 'normal');
                pdf.setFontSize(7);
                pdf.text(label, labelX, bottom + 7);
            }
        });
    }

    function drawPdfActivityMapBlock(pdf, x, y, width, height, title, description, clusters) {
        var plot = drawPdfChartFrame(pdf, x, y, width, height, title, description);
        var safeClusters = (clusters || []).slice(0);

        if (!safeClusters.length) {
            pdf.setTextColor(100, 116, 139);
            pdf.text('Keine Aktivitaetszonen vorhanden.', plot.left, plot.top + 10);
            pdf.setTextColor(23, 23, 23);
            return;
        }

        var minLat = Math.min.apply(null, safeClusters.map(function(cluster) { return cluster.center.latitude; }));
        var maxLat = Math.max.apply(null, safeClusters.map(function(cluster) { return cluster.center.latitude; }));
        var minLng = Math.min.apply(null, safeClusters.map(function(cluster) { return cluster.center.longitude; }));
        var maxLng = Math.max.apply(null, safeClusters.map(function(cluster) { return cluster.center.longitude; }));
        var latRange = Math.max(maxLat - minLat, 0.01);
        var lngRange = Math.max(maxLng - minLng, 0.01);
        var maxClusterCount = safeClusters.reduce(function(max, cluster) {
            return Math.max(max, cluster.count || 0);
        }, 1);

        pdf.setFillColor(248, 250, 252);
        pdf.roundedRect(plot.left, plot.top, plot.width, plot.height, 3, 3, 'F');

        for (var gx = 0; gx <= 4; gx++) {
            var gridX = plot.left + ((plot.width / 4) * gx);
            pdf.setDrawColor(226, 232, 240);
            pdf.line(gridX, plot.top, gridX, plot.top + plot.height);
        }

        for (var gy = 0; gy <= 3; gy++) {
            var gridY = plot.top + ((plot.height / 3) * gy);
            pdf.setDrawColor(226, 232, 240);
            pdf.line(plot.left, gridY, plot.left + plot.width, gridY);
        }

        safeClusters.forEach(function(cluster) {
            var relativeX = (cluster.center.longitude - minLng) / lngRange;
            var relativeY = (cluster.center.latitude - minLat) / latRange;
            var pointX = plot.left + 10 + (relativeX * (plot.width - 20));
            var pointY = plot.top + plot.height - 10 - (relativeY * (plot.height - 20));
            var intensity = cluster.count / maxClusterCount;
            var radius = Math.max(3, Math.min(10, 3 + (cluster.count * 1.2)));
            var stroke = intensity > 0.66 ? [197, 48, 48] : (intensity > 0.33 ? [214, 158, 46] : [10, 75, 120]);
            var fill = intensity > 0.66 ? [252, 226, 226] : (intensity > 0.33 ? [254, 243, 199] : [219, 234, 254]);

            pdf.setFillColor(fill[0], fill[1], fill[2]);
            pdf.setDrawColor(stroke[0], stroke[1], stroke[2]);
            pdf.circle(pointX, pointY, radius, 'FD');
        });

        var legendItems = safeClusters.slice().sort(function(a, b) {
            return b.count - a.count;
        }).slice(0, 4);
        var legendY = plot.top + plot.height + 6;

        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(8);
        legendItems.forEach(function(cluster, index) {
            var label = getClusterPrimaryLabel(cluster) + ' (' + cluster.count + ')';
            pdf.text(sanitizePdfText(label), plot.left + (index % 2) * (plot.width / 2), legendY + Math.floor(index / 2) * 5);
        });
    }

    async function exportStatisticsPdf() {
        if (!window.jspdf || !window.jspdf.jsPDF) {
            window.print();
            return;
        }

        var exportButton = document.getElementById('feu-einsatz-export-statistics-pdf');
        var originalLabel = exportButton ? exportButton.textContent : '';

        if (exportButton) {
            exportButton.disabled = true;
            exportButton.textContent = 'PDF wird erstellt...';
        }

        try {
            var pdf = new window.jspdf.jsPDF({
                orientation: 'p',
                unit: 'mm',
                format: 'a4'
            });

            var dailyTimelineSeries = buildDailyTimelineSeries();
            var activityClustersForPdf = getActivityClusters();
            var categoryItems = categories.slice(0, 7).map(function(category) {
                var percent = totalStats.total_einsaetze > 0 ? Math.round((parseInt(category.anzahl, 10) / totalStats.total_einsaetze) * 100) : 0;
                return {
                    label: category.kategorie_name + ' (' + percent + '%)',
                    value: parseInt(category.anzahl, 10)
                };
            });
            var weekdayItems = topWeekdays.map(function(entry) {
                return entry.label + ': ' + entry.count;
            });
            var peakDayItems = peakDays.map(function(entry) {
                return entry.date + ': ' + entry.count;
            });
            var rankingItems = participantStats.slice(0, 10).map(function(participant, index) {
                return {
                    label: (index + 1) + '. ' + participant.name,
                    value: participant.count
                };
            });
            var rankingListItems = participantStats.slice(0, 12).map(function(participant, index) {
                return (index + 1) + '. ' + participant.name + ' - ' + participant.count;
            });
            var exportSubtitle = 'Jahr ' + selectedYear + ' - Export ' + new Date().toLocaleDateString('de-DE');

            drawPdfHeader(pdf, 'Einsatzberichte Statistik', exportSubtitle);
            drawPdfStatCard(pdf, 14, 34, 88, 26, 'Einsätze', String(totalStats.total_einsaetze || 0), [10, 75, 120]);
            drawPdfStatCard(pdf, 14, 66, 88, 26, 'Teilnehmer gesamt', String(totalStats.total_teilnehmer_alle || 0), [214, 158, 46]);
            drawPdfStatCard(pdf, 108, 66, 88, 26, 'Durchschnittliche Teilnehmer pro Einsatz', String(totalStats.avg_teilnehmer || 0), [197, 48, 48]);
            drawPdfHorizontalBarChart(pdf, 14, 102, 122, 92, 'Einsatzstichworte', 'Verteilung der Einsatzstichworte', categoryItems, [10, 75, 120]);
            drawPdfListCard(pdf, 142, 102, 54, 92, 'Highlights', categoryItems.map(function(item) { return item.label + ' - ' + item.value; }));

            pdf.addPage();
            drawPdfHeader(pdf, 'Zeitmuster und Aktivitaetszonen', exportSubtitle);
            drawPdfDailyTimelineChart(pdf, 14, 34, 182, 66, 'Einsätze pro Tag', 'Tagesverlauf über das gesamte Jahr', dailyTimelineSeries);
            drawPdfListCard(pdf, 14, 108, 88, 48, 'Häufigste Wochentage', weekdayItems);
            drawPdfListCard(pdf, 108, 108, 88, 48, 'Spitzen-Tage', peakDayItems);
            addCanvasBlock(
                pdf,
                createActivitySnapshotCanvas(),
                14,
                164,
                182,
                114,
                'Aktivitätskarte',
                'Snapshot der Aktivitätskarte für den PDF-Export'
            );

            if (rankingUnlocked) {
                pdf.addPage();
                drawPdfHeader(pdf, 'Teilnehmer-Ranking', exportSubtitle);
                drawPdfHorizontalBarChart(pdf, 14, 34, 182, 104, 'Ranking-Grafik', 'Aktivste Teilnehmer im ausgewaehlten Jahr', rankingItems, [10, 75, 120]);
                drawPdfListCard(pdf, 14, 146, 182, 120, 'Top Teilnehmer', rankingListItems);
            } else {
                drawPdfListCard(pdf, 14, 248, 182, 28, 'Teilnehmer-Ranking', ['Nicht im PDF enthalten, da der Bereich aktuell gesperrt ist.']);
            }

            addPdfFooters(pdf);
            pdf.save('einsatzberichte-statistik-' + selectedYear + '.pdf');
        } finally {
            if (exportButton) {
                exportButton.disabled = false;
                exportButton.textContent = originalLabel;
            }
        }
    }

    function exportStatisticsPdfLegacy() {
        if (!window.jspdf || !window.jspdf.jsPDF) {
            window.print();
            return;
        }

        var pdf = new window.jspdf.jsPDF({
            orientation: 'p',
            unit: 'mm',
            format: 'a4'
        });
        var y = 18;
        var pageHeight = 287;

        function line(text, indent, fontStyle) {
            var left = indent || 14;
            var safeText = sanitizePdfText(text);
            var wrapped = pdf.splitTextToSize(safeText, 180 - left);

            if (y + (wrapped.length * 7) > pageHeight) {
                pdf.addPage();
                y = 18;
            }

            pdf.setFont('helvetica', fontStyle || 'normal');
            pdf.text(wrapped, left, y);
            y += (wrapped.length * 7);
        }

        pdf.setFontSize(18);
        pdf.setFont('helvetica', 'bold');
        pdf.text('Einsatzberichte Statistik ' + selectedYear, 14, y);
        y += 10;

        pdf.setFontSize(11);
        line('Gesamteinsätze: ' + (totalStats.total_einsaetze || 0));
        line('Teilnehmer gesamt: ' + (totalStats.total_teilnehmer_alle || 0));
        line('Durchschnitt Teilnehmer pro Einsatz: ' + (totalStats.avg_teilnehmer || 0));
        y += 4;

        line('Einsatzstichworte', 14, 'bold');
        if (categories.length) {
            categories.forEach(function(category) {
                line('- ' + category.kategorie_name + ': ' + category.anzahl, 18);
            });
        } else {
            line('- Keine Einsatzstichwort-Daten vorhanden.', 18);
        }
        y += 4;

        line('Häufigste Wochentage', 14, 'bold');
        if (topWeekdays.length) {
            topWeekdays.forEach(function(entry) {
                line('- ' + entry.label + ': ' + entry.count, 18);
            });
        } else {
            line('- Keine Tagesdaten vorhanden.', 18);
        }
        y += 4;

        line('Spitzen-Tage', 14, 'bold');
        if (peakDays.length) {
            peakDays.forEach(function(entry) {
                line('- ' + entry.date + ': ' + entry.count, 18);
            });
        } else {
            line('- Keine Spitzentage vorhanden.', 18);
        }
        y += 4;

        line('Aktivitätskarte', 14, 'bold');
        line('- Orte mit Koordinaten: ' + activityMapPoints.length, 18);
        y += 4;

        if (rankingUnlocked) {
            line('Teilnehmer-Ranking', 14, 'bold');
            if (participantStats.length) {
                participantStats.slice(0, 15).forEach(function(participant, index) {
                    line((index + 1) + '. ' + participant.name + ': ' + participant.count, 18);
                });
            } else {
                line('- Keine Teilnehmerdaten vorhanden.', 18);
            }
        } else {
            line('Teilnehmer-Ranking', 14, 'bold');
            line('- Gesperrt. Nicht in den Export aufgenommen.', 18);
        }

        pdf.save('einsatzberichte-statistik-' + selectedYear + '.pdf');
    }

    function closeParticipantDetails() {
        if (!detailPanel) {
            return;
        }

        detailPanel.hidden = true;
        detailContent.innerHTML = '';

        if (participantJump) {
            participantJump.value = '';
        }
    }

    function renderParticipantDetails(response, participantName) {
        var summary = response.data.summary || {};
        var einsaetze = Array.isArray(response.data.einsaetze) ? response.data.einsaetze : [];
        var funktionen = Array.isArray(response.data.funktionen) ? response.data.funktionen : [];
        var html = '';

        if (detailTitle) {
            detailTitle.textContent = participantName;
        }

        html += '<div class="feu-einsatz-detail-shell">';
        html += '<div class="feu-einsatz-detail-summary-grid">';
        html += '<div class="feu-einsatz-detail-summary-card"><span>Teilnahmen</span><strong>' + escapeHtml(summary.total_einsaetze || einsaetze.length || 0) + '</strong></div>';
        html += '<div class="feu-einsatz-detail-summary-card"><span>Haeufigste Funktion</span><strong>' + escapeHtml(summary.top_function || 'Keine Daten') + '</strong></div>';
        html += '<div class="feu-einsatz-detail-summary-card"><span>Erster Einsatz</span><strong>' + escapeHtml(summary.first_einsatz_date || 'Keine Daten') + '</strong></div>';
        html += '<div class="feu-einsatz-detail-summary-card"><span>Letzter Einsatz</span><strong>' + escapeHtml(summary.last_einsatz_date || 'Keine Daten') + '</strong></div>';
        html += '</div>';

        html += '<section class="feu-einsatz-detail-section">';
        html += '<div class="feu-einsatz-detail-section-head"><h4>Funktionen</h4><span class="description">Wie oft wurde welche Rolle übernommen.</span></div>';
        html += '<div class="feu-einsatz-role-chip-grid">';

        if (funktionen.length) {
            funktionen.forEach(function(item) {
                html += '<div class="feu-einsatz-role-chip"><span>' + escapeHtml(item.funktion || 'Ohne Funktion') + '</span><strong>' + escapeHtml(item.anzahl) + '</strong></div>';
            });
        } else {
            html += '<div class="feu-einsatz-empty-card">Keine Funktionen für dieses Jahr vorhanden.</div>';
        }

        html += '</div></section>';
        html += '<section class="feu-einsatz-detail-section">';
        html += '<div class="feu-einsatz-detail-section-head"><h4>Einsatzverlauf</h4><span class="description">Chronologische Liste aller Teilnahmen im Jahr ' + escapeHtml(selectedYear) + '.</span></div>';
        html += '<div class="feu-einsatz-einsatz-timeline">';

        if (einsaetze.length) {
            einsaetze.forEach(function(item) {
                html += '<article class="feu-einsatz-einsatz-entry">';
                html += '<div class="feu-einsatz-einsatz-entry-top">';
                html += '<span class="feu-einsatz-einsatz-date">' + escapeHtml(item.date_label || '') + '</span>';
                html += '<span class="feu-einsatz-einsatz-role">' + escapeHtml(item.funktion || 'Ohne Funktion') + '</span>';
                html += '</div>';
                html += '<h5>' + escapeHtml(item.post_title || '') + '</h5>';

                if (item.permalink) {
                    html += '<a href="' + escapeHtml(item.permalink) + '" class="button button-small" target="_blank" rel="noopener">Beitrag ansehen</a>';
                }

                html += '</article>';
            });
        } else {
            html += '<div class="feu-einsatz-empty-card">Keine Einsätze für dieses Jahr gefunden.</div>';
        }

        html += '</div></section></div>';

        detailPanel.hidden = false;
        detailContent.innerHTML = html;
        detailPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function openParticipantDetails(participantId, participantName) {
        if (!rankingUnlocked || !participantId || !detailPanel) {
            return;
        }

        if (participantJump) {
            participantJump.value = String(participantId);
        }

        detailPanel.hidden = false;
        detailContent.innerHTML = '<div class="feu-einsatz-loading-card">Lade Teilnehmerdetails...</div>';

        jQuery.post(ajaxurl, {
            action: 'feu_einsatz_get_participant_details',
            teilnehmer_id: participantId,
            jahr: selectedYear,
            nonce: '<?php echo esc_js(wp_create_nonce('feu_einsatz_ajax_nonce')); ?>'
        }, function(response) {
            if (response.success) {
                renderParticipantDetails(response, participantName);
                return;
            }

            var errorMessage = response && response.data && response.data.message
                ? response.data.message
                : 'Fehler beim Laden der Teilnehmerdetails.';

            detailContent.innerHTML = '<div class="feu-einsatz-loading-card">' + escapeHtml(errorMessage) + '</div>';
        }).fail(function() {
            detailContent.innerHTML = '<div class="feu-einsatz-loading-card">Fehler beim Laden der Teilnehmerdetails.</div>';
        });
    }

    document.querySelectorAll('.feu-einsatz-open-participant-details').forEach(function(button) {
        button.addEventListener('click', function() {
            openParticipantDetails(button.getAttribute('data-participant-id'), button.getAttribute('data-participant-name'));
        });
    });

    var exportButton = document.getElementById('feu-einsatz-export-statistics-pdf');
    if (exportButton) {
        exportButton.addEventListener('click', exportStatisticsPdf);
    }

    var statisticsPresentationToggle = document.getElementById('feu-einsatz-statistics-presentation-toggle');
    if (statisticsPresentationToggle) {
        statisticsPresentationToggle.addEventListener('click', toggleStatisticsPresentation);
    }

    var statisticsPresentationDeck = document.getElementById('feu-einsatz-statistics-presentation-deck');
    if (statisticsPresentationDeck) {
        var presentationPrev = statisticsPresentationDeck.querySelector('[data-feu-presentation-prev]');
        var presentationNext = statisticsPresentationDeck.querySelector('[data-feu-presentation-next]');
        var presentationClose = statisticsPresentationDeck.querySelector('[data-feu-presentation-close]');

        if (presentationPrev) {
            presentationPrev.addEventListener('click', function() {
                setStatisticsPresentationSlide((parseInt(statisticsPresentationDeck.dataset.currentSlide || '0', 10) || 0) - 1);
            });
        }

        if (presentationNext) {
            presentationNext.addEventListener('click', function() {
                setStatisticsPresentationSlide((parseInt(statisticsPresentationDeck.dataset.currentSlide || '0', 10) || 0) + 1);
            });
        }

        if (presentationClose) {
            presentationClose.addEventListener('click', toggleStatisticsPresentation);
        }
    }

    var participantJump = document.getElementById('feu-einsatz-participant-jump');
    if (participantJump) {
        participantJump.addEventListener('change', function() {
            var participantId = this.value;
            var match = participantLookup.find(function(item) {
                return String(item.id) === String(participantId);
            });

            if (participantId && match) {
                openParticipantDetails(participantId, match.name);
                return;
            }

            closeParticipantDetails();
        });
    }

    var closeParticipantDetailsButton = document.getElementById('feu-einsatz-close-participant-details');
    if (closeParticipantDetailsButton) {
        closeParticipantDetailsButton.addEventListener('click', closeParticipantDetails);
    }

    document.addEventListener('keydown', function(event) {
        if (statisticsPresentationDeck && !statisticsPresentationDeck.hidden) {
            if (event.key === 'ArrowRight' || event.key === 'PageDown') {
                event.preventDefault();
                setStatisticsPresentationSlide((parseInt(statisticsPresentationDeck.dataset.currentSlide || '0', 10) || 0) + 1);
                return;
            }

            if (event.key === 'ArrowLeft' || event.key === 'PageUp') {
                event.preventDefault();
                setStatisticsPresentationSlide((parseInt(statisticsPresentationDeck.dataset.currentSlide || '0', 10) || 0) - 1);
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                toggleStatisticsPresentation();
                return;
            }
        }

        if (event.key === 'Escape' && detailPanel && !detailPanel.hidden) {
            closeParticipantDetails();
        }
    });

    document.addEventListener('fullscreenchange', function() {
        if (!document.fullscreenElement && statisticsPresentationDeck && !statisticsPresentationDeck.hidden) {
            statisticsPresentationDeck.hidden = true;
            document.body.classList.remove('feu-einsatz-statistics-presentation');
        }

        syncStatisticsPresentationState();
    });

    animateStatisticVisuals();

    if (preselectedParticipantId && rankingUnlocked) {
        var selectedParticipant = participantLookup.find(function(item) {
            return item.id === preselectedParticipantId;
        });

        if (selectedParticipant) {
            setActiveTab('participants');
            if (participantJump) {
                participantJump.value = String(preselectedParticipantId);
            }
            openParticipantDetails(preselectedParticipantId, selectedParticipant.name);
            return;
        }
    }

    setActiveTab(activeTab);
});
</script>


