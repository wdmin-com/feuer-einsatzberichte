<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!empty($_POST) && FEU_Einsatz_Admin::current_user_can_access_plugin_section('settings') && check_admin_referer('feu_einsatz_save_settings', 'feu_einsatz_settings_nonce')) {
    $rebuild_period_label = '';
    if (isset($_POST['feu_einsatz_clear_street_cache'])) {
        $this->clear_all_street_cache_storage();
        FEU_Einsatz_Logger::log('settings_cache_cleared', 'settings', 0, __('Straßen-Cache geleert', 'feuer-einsatzberichte'));

        echo '<div class="notice notice-success is-dismissible"><p>' .
             __('Straßen-Cache wurde erfolgreich geleert.', 'feuer-einsatzberichte') .
             '</p></div>';
    } elseif (isset($_POST['feu_einsatz_refresh_update_check'])) {
        FEU_Einsatz_Updater::clear_cached_metadata();
        $updater = Feuer_Einsatzberichte_Core::get_instance()->get_updater();
        $manifest_payload = ($updater instanceof FEU_Einsatz_Updater) ? $updater->get_manifest_payload(true) : false;

        FEU_Einsatz_Logger::log(
            'update_check_refreshed',
            'settings',
            0,
            __('Update-Prüfung aktualisiert', 'feuer-einsatzberichte'),
            [
                'installed_version' => FEU_EINSATZ_VERSION,
                'remote_version' => is_array($manifest_payload) ? (string) ($manifest_payload['version'] ?? '') : '',
                'manifest_url' => FEU_Einsatz_Updater::get_manifest_url(),
            ]
        );

        if (is_array($manifest_payload)) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 sprintf(
                     /* translators: %1$s: installed version, %2$s: remote version */
                     esc_html__('Update-Prüfung aktualisiert. Installiert: %1$s, Remote: %2$s.', 'feuer-einsatzberichte'),
                     esc_html(FEU_EINSATZ_VERSION),
                     esc_html((string) ($manifest_payload['version'] ?? '-'))
                 ) .
                 ('' !== $rebuild_period_label ? ' ' . $rebuild_period_label : '') .
                 '</p></div>';
        } else {
            echo '<div class="notice notice-warning is-dismissible"><p>' .
                 esc_html__('Update-Prüfung aktualisiert, aber das Remote-Manifest konnte momentan nicht gelesen werden.', 'feuer-einsatzberichte') .
                 '</p></div>';
        }
    } elseif (isset($_POST['feu_einsatz_rebuild_generated_map_images'])) {
        $rebuild_filters = [
            'date_from' => isset($_POST['feu_einsatz_generated_map_rebuild_from'])
                ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_generated_map_rebuild_from']))
                : '',
            'date_to' => isset($_POST['feu_einsatz_generated_map_rebuild_to'])
                ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_generated_map_rebuild_to']))
                : '',
        ];
        $rebuild_result = $this->queue_bulk_generated_map_rebuild($rebuild_filters);
        $rebuild_result_filters = isset($rebuild_result['filters']) && is_array($rebuild_result['filters'])
            ? $rebuild_result['filters']
            : ['date_from' => '', 'date_to' => ''];
        $rebuild_period_label = '';

        if ('' !== $rebuild_result_filters['date_from'] || '' !== $rebuild_result_filters['date_to']) {
            $rebuild_period_label = sprintf(
                /* translators: %1$s: start date or dash, %2$s: end date or dash */
                __('Zeitraum: %1$s bis %2$s.', 'feuer-einsatzberichte'),
                '' !== $rebuild_result_filters['date_from'] ? esc_html($rebuild_result_filters['date_from']) : '-',
                '' !== $rebuild_result_filters['date_to'] ? esc_html($rebuild_result_filters['date_to']) : '-'
            );
        }

        FEU_Einsatz_Logger::log(
            'generated_map_rebuild_queued',
            'settings',
            0,
            __('Automatisch erzeugte Kartenbilder zur Neu-Erstellung vorgemerkt', 'feuer-einsatzberichte'),
            [
                'candidates' => (int) ($rebuild_result['candidates'] ?? 0),
                'scheduled' => (int) ($rebuild_result['scheduled'] ?? 0),
                'skipped' => (int) ($rebuild_result['skipped'] ?? 0),
                'date_from' => (string) ($rebuild_result_filters['date_from'] ?? ''),
                'date_to' => (string) ($rebuild_result_filters['date_to'] ?? ''),
            ]
        );

        $rebuild_period_suffix = '' !== $rebuild_period_label ? ' ' . esc_html($rebuild_period_label) : '';

        if (!empty($rebuild_result['scheduled'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 sprintf(
                     /* translators: %1$d: queued reports, %2$d: skipped reports */
                     esc_html__('%1$d automatisch erzeugte Kartenbilder wurden zur Neu-Erstellung vorgemerkt. %2$d Einträge wurden übersprungen. Manuelle Beitragsbilder bleiben unverändert.', 'feuer-einsatzberichte'),
                     (int) $rebuild_result['scheduled'],
                     (int) ($rebuild_result['skipped'] ?? 0)
                 ) .
                 $rebuild_period_suffix .
                 '</p></div>';
        } else {
            echo '<div class="notice notice-info is-dismissible"><p>' .
                 esc_html__('Es wurden keine automatisch erzeugten Kartenbilder gefunden, die neu aufgebaut werden müssen.', 'feuer-einsatzberichte') .
                 '</p></div>';
        }
    } elseif (isset($_POST['feu_einsatz_install_default_categories'])) {
        $category_install_result = FEU_Einsatz_Installer::install_default_categories();

        if (is_wp_error($category_install_result)) {
            echo '<div class="notice notice-error is-dismissible"><p>' .
                 esc_html($category_install_result->get_error_message()) .
                 '</p></div>';
        } else {
            $installed_category_ids = get_terms([
                'taxonomy' => 'category',
                'hide_empty' => false,
                'parent' => (int) $category_install_result['root_id'],
                'fields' => 'ids',
            ]);
            if (!is_wp_error($installed_category_ids)) {
                update_option('feu_einsatz_categories', array_map('absint', (array) $installed_category_ids), false);
            }

            echo '<div class="notice notice-success is-dismissible"><p>' .
                 esc_html(sprintf(
                     __('Die Kategoriegruppe Einsätze und %d Einsatzstichworte wurden eingerichtet und aktiviert.', 'feuer-einsatzberichte'),
                     count((array) $installed_category_ids)
                 )) .
                 '</p></div>';
        }
    } elseif (isset($_POST['submit'])) {
        $previous_settings = [
            'feu_einsatz_functions' => array_values((array) get_option('feu_einsatz_functions', [])),
            'feu_einsatz_categories' => array_map('intval', (array) get_option('feu_einsatz_categories', [])),
            'feu_einsatz_map_zoom' => (int) get_option('feu_einsatz_map_zoom', 16),
            'feu_einsatz_map_height' => (int) get_option('feu_einsatz_map_height', 400),
            'feu_einsatz_auto_map_image' => (int) get_option('feu_einsatz_auto_map_image', 1),
            'feu_einsatz_map_preview_heading_text' => (string) get_option('feu_einsatz_map_preview_heading_text', ''),
            'feu_einsatz_map_preview_show_panel' => (int) get_option('feu_einsatz_map_preview_show_panel', 1),
            'feu_einsatz_map_preview_show_panel_heading' => (int) get_option('feu_einsatz_map_preview_show_panel_heading', 1),
            'feu_einsatz_map_preview_show_panel_address' => (int) get_option('feu_einsatz_map_preview_show_panel_address', 1),
            'feu_einsatz_map_preview_panel_position' => $this->normalize_map_preview_position(get_option('feu_einsatz_map_preview_panel_position', 'bottom-left'), 'bottom-left'),
            'feu_einsatz_map_preview_show_street_label' => (int) get_option('feu_einsatz_map_preview_show_street_label', 1),
            'feu_einsatz_map_preview_street_label_prefix' => (string) get_option('feu_einsatz_map_preview_street_label_prefix', 'Einsatz Straße'),
            'feu_einsatz_map_preview_street_label_position' => $this->normalize_map_preview_position(get_option('feu_einsatz_map_preview_street_label_position', 'auto'), 'auto', true),
            'feu_einsatz_map_preview_show_attribution' => (int) get_option('feu_einsatz_map_preview_show_attribution', 1),
            'feu_einsatz_map_preview_attribution_text' => (string) get_option('feu_einsatz_map_preview_attribution_text', 'Leaflet | ' . html_entity_decode('&copy;', ENT_QUOTES, 'UTF-8') . ' OpenStreetMap contributors'),
            'feu_einsatz_map_preview_attribution_position' => $this->normalize_map_preview_position(get_option('feu_einsatz_map_preview_attribution_position', 'bottom-right'), 'bottom-right'),
            'feu_einsatz_map_label_style' => $this->normalize_map_label_style(get_option('feu_einsatz_map_label_style', 'bubble')),
            'feu_einsatz_map_label_text_color' => sanitize_hex_color((string) get_option('feu_einsatz_map_label_text_color', '#ffffff')) ?: '#ffffff',
            'feu_einsatz_map_preview_highlight_color' => (string) get_option('feu_einsatz_map_preview_highlight_color', '#d92d20'),
            'feu_einsatz_map_preview_stroke_width' => max(3, min(18, absint(get_option('feu_einsatz_map_preview_stroke_width', 8)))),
            'feu_einsatz_map_preview_font_family' => $this->normalize_map_preview_font_family(get_option('feu_einsatz_map_preview_font_family', 'auto')),
            'feu_einsatz_area_page_enabled' => (int) get_option('feu_einsatz_area_page_enabled', 0),
            'feu_einsatz_area_show_calls' => (int) get_option('feu_einsatz_area_show_calls', 1),
            'feu_einsatz_area_postcodes' => FEU_Einsatz_Template_Helpers::sanitize_area_entry_list(get_option('feu_einsatz_area_postcodes', [])),
            'feu_einsatz_area_station_street' => (string) get_option('feu_einsatz_area_station_street', ''),
            'feu_einsatz_area_station_postcode' => preg_replace('/\D+/', '', (string) get_option('feu_einsatz_area_station_postcode', '')),
            'feu_einsatz_area_station_city' => (string) get_option('feu_einsatz_area_station_city', 'Hamburg'),
            'feu_einsatz_area_station_logo_id' => absint(get_option('feu_einsatz_area_station_logo_id', 0)),
            'feu_einsatz_area_station_logo_size' => max(20, min(96, absint(get_option('feu_einsatz_area_station_logo_size', 40)))),
            'feu_einsatz_default_comments_enabled' => (int) get_option('feu_einsatz_default_comments_enabled', 0),
            'feu_einsatz_default_card_variant' => FEU_Einsatz_Template_Helpers::normalize_report_card_variant(get_option('feu_einsatz_default_card_variant', 'modern')),
            'feu_einsatz_related_reports_display' => FEU_Einsatz_Template_Helpers::normalize_related_reports_display(get_option('feu_einsatz_related_reports_display', 'cards')),
            'feu_einsatz_related_reports_count' => max(1, min(12, absint(get_option('feu_einsatz_related_reports_count', 6)))),
            'feu_einsatz_single_desaturate_organizations' => (int) get_option('feu_einsatz_single_desaturate_organizations', 0),
            'feu_einsatz_single_info_fields' => FEU_Einsatz_Template_Helpers::normalize_single_info_fields(
                get_option('feu_einsatz_single_info_fields', FEU_Einsatz_Template_Helpers::get_default_single_info_fields())
            ),
            'feu_einsatz_single_map_display_mode' => FEU_Einsatz_Template_Helpers::normalize_single_map_display_mode(get_option('feu_einsatz_single_map_display_mode', 'live')),
            'feu_einsatz_single_map_privacy_mode' => FEU_Einsatz_Template_Helpers::normalize_single_map_privacy_mode(get_option('feu_einsatz_single_map_privacy_mode', 'always')),
            'feu_einsatz_single_live_map_show_station' => (int) get_option('feu_einsatz_single_live_map_show_station', 0),
            'feu_einsatz_overview_show_stats' => (int) get_option('feu_einsatz_overview_show_stats', 1),
            'feu_einsatz_overview_show_year_filter' => (int) get_option('feu_einsatz_overview_show_year_filter', 1),
            'feu_einsatz_social_share_enabled_networks' => FEU_Einsatz_Template_Helpers::normalize_social_share_networks(
                get_option('feu_einsatz_social_share_enabled_networks', FEU_Einsatz_Template_Helpers::get_default_social_share_networks())
            ),
            'feu_einsatz_social_share_image_mode' => FEU_Einsatz_Template_Helpers::normalize_social_share_image_mode(
                get_option('feu_einsatz_social_share_image_mode', 'post_image')
            ),
            'feu_einsatz_social_share_background_id' => absint(get_option('feu_einsatz_social_share_background_id', 0)),
            'feu_einsatz_social_share_fields' => FEU_Einsatz_Template_Helpers::normalize_social_share_fields(
                get_option('feu_einsatz_social_share_fields', FEU_Einsatz_Template_Helpers::get_default_social_share_fields())
            ),
            'feu_einsatz_social_share_layout' => FEU_Einsatz_Template_Helpers::normalize_social_share_layout(
                get_option('feu_einsatz_social_share_layout', 'wide')
            ),
            'feu_einsatz_social_share_logo_id' => absint(get_option('feu_einsatz_social_share_logo_id', 0)),
            'feu_einsatz_social_share_badge_text' => FEU_Einsatz_Template_Helpers::normalize_social_share_card_text(
                get_option('feu_einsatz_social_share_badge_text', 'PRESSEMITTEILUNG'),
                'PRESSEMITTEILUNG'
            ),
            'feu_einsatz_social_share_cta_text' => FEU_Einsatz_Template_Helpers::normalize_social_share_card_text(
                get_option('feu_einsatz_social_share_cta_text', 'Weitere Infos'),
                'Weitere Infos'
            ),
            'feu_einsatz_social_share_title_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_title_color', '#ffffff')) ?: '#ffffff',
            'feu_einsatz_social_share_description_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_description_color', '#dbeafe')) ?: '#dbeafe',
            'feu_einsatz_social_share_panel_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_panel_color', '#0f2f5f')) ?: '#0f2f5f',
            'feu_einsatz_social_share_accent_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_accent_color', '#ef233c')) ?: '#ef233c',
            'feu_einsatz_social_share_cta_fill_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_cta_fill_color', '#ffffff')) ?: '#ffffff',
            'feu_einsatz_social_share_cta_text_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_cta_text_color', '#0f2f5f')) ?: '#0f2f5f',
            'feu_einsatz_social_share_title_scale' => max(70, min(180, absint(get_option('feu_einsatz_social_share_title_scale', 118)))),
            'feu_einsatz_social_share_description_scale' => max(70, min(180, absint(get_option('feu_einsatz_social_share_description_scale', 112)))),
            'feu_einsatz_social_share_description_max_lines' => max(2, min(10, absint(get_option('feu_einsatz_social_share_description_max_lines', 5)))),
            'feu_einsatz_social_share_logo_scale' => max(40, min(220, absint(get_option('feu_einsatz_social_share_logo_scale', 100)))),
            'feu_einsatz_social_share_logo_width' => max(60, min(520, absint(get_option('feu_einsatz_social_share_logo_width', 220)))),
            'feu_einsatz_social_share_overlay_enabled' => (int) get_option('feu_einsatz_social_share_overlay_enabled', 1),
            'feu_einsatz_social_share_image_blur' => max(0, min(20, absint(get_option('feu_einsatz_social_share_image_blur', 0)))),
            'feu_einsatz_social_share_panel_radius' => max(0, min(120, absint(get_option('feu_einsatz_social_share_panel_radius', 30)))),
            'feu_einsatz_social_share_badge_radius' => max(0, min(120, absint(get_option('feu_einsatz_social_share_badge_radius', 40)))),
            'feu_einsatz_social_share_link_radius' => max(0, min(80, absint(get_option('feu_einsatz_social_share_link_radius', 14)))),
            'feu_einsatz_social_share_text_align' => FEU_Einsatz_Template_Helpers::normalize_social_share_text_align(get_option('feu_einsatz_social_share_text_align', 'auto')),
            'feu_einsatz_social_share_logo_position' => FEU_Einsatz_Template_Helpers::normalize_social_share_logo_position(get_option('feu_einsatz_social_share_logo_position', 'bottom-right')),
            'feu_einsatz_social_meta_enabled' => (int) get_option('feu_einsatz_social_meta_enabled', 1),
            'feu_einsatz_social_meta_canonical_enabled' => (int) get_option('feu_einsatz_social_meta_canonical_enabled', 1),
            'feu_einsatz_social_meta_schema_enabled' => (int) get_option('feu_einsatz_social_meta_schema_enabled', 1),
            'feu_einsatz_social_meta_twitter_site' => FEU_Einsatz_Template_Helpers::normalize_social_share_account_handle(
                get_option('feu_einsatz_social_meta_twitter_site', '')
            ),
            'feu_einsatz_role_access' => FEU_Einsatz_Admin::get_plugin_role_access_settings(),
            'feu_einsatz_feature_organizations_enabled' => (int) get_option('feu_einsatz_feature_organizations_enabled', 1),
            'feu_einsatz_default_participant_function' => FEU_Einsatz_Installer::get_default_participant_function(),
            'feu_einsatz_backup_retention_limit' => max(1, absint(get_option('feu_einsatz_backup_retention_limit', 5))),
            'feu_einsatz_photo_watermark_enabled' => (int) get_option('feu_einsatz_photo_watermark_enabled', 1),
            'feu_einsatz_photo_watermark_text' => (string) get_option('feu_einsatz_photo_watermark_text', get_bloginfo('name')),
            'feu_einsatz_photo_watermark_image_id' => absint(get_option('feu_einsatz_photo_watermark_image_id', 0)),
            'feu_einsatz_photo_watermark_opacity' => max(5, min(100, absint(get_option('feu_einsatz_photo_watermark_opacity', 36)))),
            'feu_einsatz_photo_watermark_scale' => max(10, min(90, absint(get_option('feu_einsatz_photo_watermark_scale', 42)))),
        ];
        $previous_participant_ranking_pin = FEU_Einsatz_Admin::get_participant_ranking_pin();

        update_option('feu_einsatz_update_manifest_url', FEU_Einsatz_Updater::get_default_manifest_url());

        if (isset($_POST['feu_einsatz_functions'])) {
            $functions = array_map('sanitize_text_field', (array) wp_unslash($_POST['feu_einsatz_functions']));
            $functions = array_filter($functions);
            if (empty($functions)) {
                $functions = FEU_Einsatz_Installer::get_default_functions();
            }
            update_option('feu_einsatz_functions', array_values($functions));
            update_option(
                'feu_einsatz_default_participant_function',
                FEU_Einsatz_Installer::resolve_default_participant_function(
                    isset($_POST['feu_einsatz_default_participant_function']) ? wp_unslash($_POST['feu_einsatz_default_participant_function']) : '',
                    $functions
                )
            );
        }

        $categories = isset($_POST['feu_einsatz_categories']) ? array_map('intval', (array) wp_unslash($_POST['feu_einsatz_categories'])) : [];
        update_option('feu_einsatz_categories', $categories);

        if (isset($_POST['feu_einsatz_map_zoom'])) {
            update_option('feu_einsatz_map_zoom', intval(wp_unslash($_POST['feu_einsatz_map_zoom'])));
        }

        if (isset($_POST['feu_einsatz_map_height'])) {
            update_option('feu_einsatz_map_height', intval(wp_unslash($_POST['feu_einsatz_map_height'])));
        }

        if (isset($_POST['feu_einsatz_auto_map_image'])) {
            update_option('feu_einsatz_auto_map_image', intval(wp_unslash($_POST['feu_einsatz_auto_map_image'])));
        } else {
            update_option('feu_einsatz_auto_map_image', 0);
        }

        update_option(
            'feu_einsatz_map_preview_heading_text',
            isset($_POST['feu_einsatz_map_preview_heading_text'])
                ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_map_preview_heading_text']))
                : ''
        );
        update_option('feu_einsatz_map_preview_show_panel', isset($_POST['feu_einsatz_map_preview_show_panel']) ? 1 : 0);
        update_option('feu_einsatz_map_preview_show_panel_heading', isset($_POST['feu_einsatz_map_preview_show_panel_heading']) ? 1 : 0);
        update_option('feu_einsatz_map_preview_show_panel_address', isset($_POST['feu_einsatz_map_preview_show_panel_address']) ? 1 : 0);
        update_option(
            'feu_einsatz_map_preview_panel_position',
            $this->normalize_map_preview_position(
                isset($_POST['feu_einsatz_map_preview_panel_position']) ? wp_unslash($_POST['feu_einsatz_map_preview_panel_position']) : 'bottom-left',
                'bottom-left'
            )
        );
        update_option('feu_einsatz_map_preview_show_street_label', isset($_POST['feu_einsatz_map_preview_show_street_label']) ? 1 : 0);
        update_option(
            'feu_einsatz_map_preview_street_label_prefix',
            isset($_POST['feu_einsatz_map_preview_street_label_prefix'])
                ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_map_preview_street_label_prefix']))
                : 'Einsatz Straße'
        );
        update_option(
            'feu_einsatz_map_preview_street_label_position',
            $this->normalize_map_preview_position(
                isset($_POST['feu_einsatz_map_preview_street_label_position']) ? wp_unslash($_POST['feu_einsatz_map_preview_street_label_position']) : 'auto',
                'auto',
                true
            )
        );
        update_option('feu_einsatz_map_preview_show_attribution', isset($_POST['feu_einsatz_map_preview_show_attribution']) ? 1 : 0);
        update_option(
            'feu_einsatz_map_preview_attribution_text',
            isset($_POST['feu_einsatz_map_preview_attribution_text'])
                ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_map_preview_attribution_text']))
                : 'Leaflet | ' . html_entity_decode('&copy;', ENT_QUOTES, 'UTF-8') . ' OpenStreetMap contributors'
        );
        update_option(
            'feu_einsatz_map_preview_attribution_position',
            $this->normalize_map_preview_position(
                isset($_POST['feu_einsatz_map_preview_attribution_position']) ? wp_unslash($_POST['feu_einsatz_map_preview_attribution_position']) : 'bottom-right',
                'bottom-right'
            )
        );
        update_option(
            'feu_einsatz_map_label_style',
            $this->normalize_map_label_style(
                isset($_POST['feu_einsatz_map_label_style']) ? wp_unslash($_POST['feu_einsatz_map_label_style']) : 'bubble'
            )
        );
        $submitted_label_text_color = isset($_POST['feu_einsatz_map_label_text_color'])
            ? sanitize_hex_color(wp_unslash($_POST['feu_einsatz_map_label_text_color']))
            : false;
        update_option('feu_einsatz_map_label_text_color', $submitted_label_text_color ? strtolower($submitted_label_text_color) : '#ffffff');

        $submitted_highlight_color = isset($_POST['feu_einsatz_map_preview_highlight_color'])
            ? sanitize_hex_color(wp_unslash($_POST['feu_einsatz_map_preview_highlight_color']))
            : false;
        update_option(
            'feu_einsatz_map_preview_highlight_color',
            $submitted_highlight_color ? strtolower($submitted_highlight_color) : '#d92d20'
        );
        update_option(
            'feu_einsatz_map_preview_stroke_width',
            isset($_POST['feu_einsatz_map_preview_stroke_width'])
                ? max(3, min(18, absint(wp_unslash($_POST['feu_einsatz_map_preview_stroke_width']))))
                : 8
        );
        update_option(
            'feu_einsatz_map_preview_font_family',
            $this->normalize_map_preview_font_family(
                isset($_POST['feu_einsatz_map_preview_font_family']) ? wp_unslash($_POST['feu_einsatz_map_preview_font_family']) : 'auto'
            )
        );

        update_option('feu_einsatz_area_page_enabled', isset($_POST['feu_einsatz_area_page_enabled']) ? 1 : 0);
        update_option('feu_einsatz_area_show_calls', isset($_POST['feu_einsatz_area_show_calls']) ? 1 : 0);
        update_option(
            'feu_einsatz_area_postcodes',
            isset($_POST['feu_einsatz_area_postcodes'])
                ? FEU_Einsatz_Template_Helpers::sanitize_area_entry_list(wp_unslash($_POST['feu_einsatz_area_postcodes']))
                : []
        );
        update_option(
            'feu_einsatz_area_station_street',
            isset($_POST['feu_einsatz_area_station_street']) ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_area_station_street'])) : ''
        );
        update_option(
            'feu_einsatz_area_station_postcode',
            isset($_POST['feu_einsatz_area_station_postcode']) ? preg_replace('/\D+/', '', (string) wp_unslash($_POST['feu_einsatz_area_station_postcode'])) : ''
        );
        update_option(
            'feu_einsatz_area_station_city',
            isset($_POST['feu_einsatz_area_station_city']) ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_area_station_city'])) : 'Hamburg'
        );
        update_option(
            'feu_einsatz_area_station_logo_id',
            isset($_POST['feu_einsatz_area_station_logo_id']) ? absint(wp_unslash($_POST['feu_einsatz_area_station_logo_id'])) : 0
        );
        update_option(
            'feu_einsatz_area_station_logo_size',
            isset($_POST['feu_einsatz_area_station_logo_size']) ? max(20, min(96, absint(wp_unslash($_POST['feu_einsatz_area_station_logo_size'])))) : 40
        );

        update_option('feu_einsatz_default_comments_enabled', isset($_POST['feu_einsatz_default_comments_enabled']) ? 1 : 0);
        update_option(
            'feu_einsatz_default_card_variant',
            FEU_Einsatz_Template_Helpers::normalize_report_card_variant(
                isset($_POST['feu_einsatz_default_card_variant']) ? wp_unslash($_POST['feu_einsatz_default_card_variant']) : 'modern'
            )
        );
        update_option(
            'feu_einsatz_related_reports_display',
            FEU_Einsatz_Template_Helpers::normalize_related_reports_display(
                isset($_POST['feu_einsatz_related_reports_display']) ? wp_unslash($_POST['feu_einsatz_related_reports_display']) : 'cards'
            )
        );
        update_option(
            'feu_einsatz_related_reports_count',
            isset($_POST['feu_einsatz_related_reports_count']) ? max(1, min(12, absint(wp_unslash($_POST['feu_einsatz_related_reports_count'])))) : 6
        );
        update_option('feu_einsatz_single_desaturate_organizations', isset($_POST['feu_einsatz_single_desaturate_organizations']) ? 1 : 0);
        update_option('feu_einsatz_feature_organizations_enabled', isset($_POST['feu_einsatz_feature_organizations_enabled']) ? 1 : 0);
        update_option(
            'feu_einsatz_single_info_fields',
            FEU_Einsatz_Template_Helpers::normalize_single_info_fields(
                isset($_POST['feu_einsatz_single_info_fields']) ? wp_unslash($_POST['feu_einsatz_single_info_fields']) : []
            )
        );
        update_option(
            'feu_einsatz_single_map_display_mode',
            FEU_Einsatz_Template_Helpers::normalize_single_map_display_mode(
                isset($_POST['feu_einsatz_single_map_display_mode']) ? wp_unslash($_POST['feu_einsatz_single_map_display_mode']) : 'live'
            )
        );
        update_option(
            'feu_einsatz_single_map_privacy_mode',
            FEU_Einsatz_Template_Helpers::normalize_single_map_privacy_mode(
                isset($_POST['feu_einsatz_single_map_privacy_mode']) ? wp_unslash($_POST['feu_einsatz_single_map_privacy_mode']) : 'always'
            )
        );
        update_option('feu_einsatz_single_live_map_show_station', isset($_POST['feu_einsatz_single_live_map_show_station']) ? 1 : 0);
        update_option('feu_einsatz_overview_show_stats', isset($_POST['feu_einsatz_overview_show_stats']) ? 1 : 0);
        update_option('feu_einsatz_overview_show_year_filter', isset($_POST['feu_einsatz_overview_show_year_filter']) ? 1 : 0);
        delete_option('feu_einsatz_overview_enable_scroll_animations');
        delete_option('feu_einsatz_overview_scroll_animation');
        update_option(
            'feu_einsatz_social_share_enabled_networks',
            FEU_Einsatz_Template_Helpers::normalize_social_share_networks(
                isset($_POST['feu_einsatz_social_share_enabled_networks']) ? wp_unslash($_POST['feu_einsatz_social_share_enabled_networks']) : []
            )
        );
        update_option(
            'feu_einsatz_social_share_image_mode',
            FEU_Einsatz_Template_Helpers::normalize_social_share_image_mode(
                isset($_POST['feu_einsatz_social_share_image_mode']) ? wp_unslash($_POST['feu_einsatz_social_share_image_mode']) : 'post_image'
            )
        );
        update_option(
            'feu_einsatz_social_share_background_id',
            isset($_POST['feu_einsatz_social_share_background_id']) ? absint(wp_unslash($_POST['feu_einsatz_social_share_background_id'])) : 0
        );
        update_option(
            'feu_einsatz_social_share_fields',
            FEU_Einsatz_Template_Helpers::normalize_social_share_fields(
                isset($_POST['feu_einsatz_social_share_fields']) ? wp_unslash($_POST['feu_einsatz_social_share_fields']) : []
            )
        );
        update_option(
            'feu_einsatz_social_share_layout',
            FEU_Einsatz_Template_Helpers::normalize_social_share_layout(
                isset($_POST['feu_einsatz_social_share_layout']) ? wp_unslash($_POST['feu_einsatz_social_share_layout']) : 'wide'
            )
        );
        update_option(
            'feu_einsatz_social_share_logo_id',
            isset($_POST['feu_einsatz_social_share_logo_id']) ? absint(wp_unslash($_POST['feu_einsatz_social_share_logo_id'])) : 0
        );
        update_option(
            'feu_einsatz_social_share_badge_text',
            FEU_Einsatz_Template_Helpers::normalize_social_share_card_text(
                isset($_POST['feu_einsatz_social_share_badge_text']) ? wp_unslash($_POST['feu_einsatz_social_share_badge_text']) : '',
                'PRESSEMITTEILUNG'
            )
        );
        update_option(
            'feu_einsatz_social_share_cta_text',
            FEU_Einsatz_Template_Helpers::normalize_social_share_card_text(
                isset($_POST['feu_einsatz_social_share_cta_text']) ? wp_unslash($_POST['feu_einsatz_social_share_cta_text']) : '',
                'Weitere Infos'
            )
        );
        update_option(
            'feu_einsatz_social_share_title_color',
            sanitize_hex_color((string) (isset($_POST['feu_einsatz_social_share_title_color']) ? wp_unslash($_POST['feu_einsatz_social_share_title_color']) : '#ffffff')) ?: '#ffffff'
        );
        update_option(
            'feu_einsatz_social_share_description_color',
            sanitize_hex_color((string) (isset($_POST['feu_einsatz_social_share_description_color']) ? wp_unslash($_POST['feu_einsatz_social_share_description_color']) : '#dbeafe')) ?: '#dbeafe'
        );
        update_option(
            'feu_einsatz_social_share_panel_color',
            sanitize_hex_color((string) (isset($_POST['feu_einsatz_social_share_panel_color']) ? wp_unslash($_POST['feu_einsatz_social_share_panel_color']) : '#0f2f5f')) ?: '#0f2f5f'
        );
        update_option(
            'feu_einsatz_social_share_accent_color',
            sanitize_hex_color((string) (isset($_POST['feu_einsatz_social_share_accent_color']) ? wp_unslash($_POST['feu_einsatz_social_share_accent_color']) : '#ef233c')) ?: '#ef233c'
        );
        update_option(
            'feu_einsatz_social_share_cta_fill_color',
            sanitize_hex_color((string) (isset($_POST['feu_einsatz_social_share_cta_fill_color']) ? wp_unslash($_POST['feu_einsatz_social_share_cta_fill_color']) : '#ffffff')) ?: '#ffffff'
        );
        update_option(
            'feu_einsatz_social_share_cta_text_color',
            sanitize_hex_color((string) (isset($_POST['feu_einsatz_social_share_cta_text_color']) ? wp_unslash($_POST['feu_einsatz_social_share_cta_text_color']) : '#0f2f5f')) ?: '#0f2f5f'
        );
        update_option(
            'feu_einsatz_social_share_title_scale',
            isset($_POST['feu_einsatz_social_share_title_scale'])
                ? max(70, min(180, absint(wp_unslash($_POST['feu_einsatz_social_share_title_scale']))))
                : 118
        );
        update_option(
            'feu_einsatz_social_share_description_scale',
            isset($_POST['feu_einsatz_social_share_description_scale'])
                ? max(70, min(180, absint(wp_unslash($_POST['feu_einsatz_social_share_description_scale']))))
                : 112
        );
        update_option(
            'feu_einsatz_social_share_description_max_lines',
            isset($_POST['feu_einsatz_social_share_description_max_lines'])
                ? max(2, min(10, absint(wp_unslash($_POST['feu_einsatz_social_share_description_max_lines']))))
                : 5
        );
        update_option(
            'feu_einsatz_social_share_logo_scale',
            isset($_POST['feu_einsatz_social_share_logo_scale'])
                ? max(40, min(220, absint(wp_unslash($_POST['feu_einsatz_social_share_logo_scale']))))
                : 100
        );
        update_option(
            'feu_einsatz_social_share_logo_width',
            isset($_POST['feu_einsatz_social_share_logo_width'])
                ? max(60, min(520, absint(wp_unslash($_POST['feu_einsatz_social_share_logo_width']))))
                : 220
        );
        update_option('feu_einsatz_social_share_overlay_enabled', isset($_POST['feu_einsatz_social_share_overlay_enabled']) ? 1 : 0);
        update_option(
            'feu_einsatz_social_share_image_blur',
            isset($_POST['feu_einsatz_social_share_image_blur'])
                ? max(0, min(20, absint(wp_unslash($_POST['feu_einsatz_social_share_image_blur']))))
                : 0
        );
        update_option(
            'feu_einsatz_social_share_panel_radius',
            isset($_POST['feu_einsatz_social_share_panel_radius'])
                ? max(0, min(120, absint(wp_unslash($_POST['feu_einsatz_social_share_panel_radius']))))
                : 30
        );
        update_option(
            'feu_einsatz_social_share_badge_radius',
            isset($_POST['feu_einsatz_social_share_badge_radius'])
                ? max(0, min(120, absint(wp_unslash($_POST['feu_einsatz_social_share_badge_radius']))))
                : 40
        );
        update_option(
            'feu_einsatz_social_share_link_radius',
            isset($_POST['feu_einsatz_social_share_link_radius'])
                ? max(0, min(80, absint(wp_unslash($_POST['feu_einsatz_social_share_link_radius']))))
                : 14
        );
        update_option(
            'feu_einsatz_social_share_text_align',
            FEU_Einsatz_Template_Helpers::normalize_social_share_text_align(
                isset($_POST['feu_einsatz_social_share_text_align']) ? wp_unslash($_POST['feu_einsatz_social_share_text_align']) : 'auto'
            )
        );
        update_option(
            'feu_einsatz_social_share_logo_position',
            FEU_Einsatz_Template_Helpers::normalize_social_share_logo_position(
                isset($_POST['feu_einsatz_social_share_logo_position']) ? wp_unslash($_POST['feu_einsatz_social_share_logo_position']) : 'bottom-right'
            )
        );
        update_option('feu_einsatz_social_meta_enabled', isset($_POST['feu_einsatz_social_meta_enabled']) ? 1 : 0);
        update_option('feu_einsatz_social_meta_canonical_enabled', isset($_POST['feu_einsatz_social_meta_canonical_enabled']) ? 1 : 0);
        update_option('feu_einsatz_social_meta_schema_enabled', isset($_POST['feu_einsatz_social_meta_schema_enabled']) ? 1 : 0);
        update_option(
            'feu_einsatz_social_meta_twitter_site',
            FEU_Einsatz_Template_Helpers::normalize_social_share_account_handle(
                isset($_POST['feu_einsatz_social_meta_twitter_site']) ? wp_unslash($_POST['feu_einsatz_social_meta_twitter_site']) : ''
            )
        );
        $submitted_role_access = isset($_POST[FEU_Einsatz_Admin::ROLE_ACCESS_OPTION]) && is_array($_POST[FEU_Einsatz_Admin::ROLE_ACCESS_OPTION])
            ? wp_unslash($_POST[FEU_Einsatz_Admin::ROLE_ACCESS_OPTION])
            : [];
        $present_role_access_sections = isset($_POST['feu_einsatz_role_access_present']) && is_array($_POST['feu_einsatz_role_access_present'])
            ? array_keys(wp_unslash($_POST['feu_einsatz_role_access_present']))
            : [];

        foreach ($present_role_access_sections as $present_section_key) {
            $present_section_key = sanitize_key($present_section_key);

            if ('' !== $present_section_key && !isset($submitted_role_access[$present_section_key])) {
                $submitted_role_access[$present_section_key] = [];
            }
        }

        $submitted_role_access_complete = [];
        foreach (FEU_Einsatz_Admin::get_plugin_access_sections() as $section_key => $section_definition) {
            $submitted_role_access_complete[$section_key] = isset($submitted_role_access[$section_key]) && is_array($submitted_role_access[$section_key])
                ? array_values((array) $submitted_role_access[$section_key])
                : [];
        }

        update_option(
            FEU_Einsatz_Admin::ROLE_ACCESS_OPTION,
            FEU_Einsatz_Admin::normalize_plugin_role_access_settings($submitted_role_access_complete)
        );
        update_option(
            'feu_einsatz_backup_retention_limit',
            isset($_POST['feu_einsatz_backup_retention_limit']) ? max(1, min(50, absint(wp_unslash($_POST['feu_einsatz_backup_retention_limit'])))) : 5
        );
        if (isset($_POST['feu_einsatz_photo_watermark_enabled'])) {
            update_option('feu_einsatz_photo_watermark_enabled', intval(wp_unslash($_POST['feu_einsatz_photo_watermark_enabled'])));
        } else {
            update_option('feu_einsatz_photo_watermark_enabled', 0);
        }

        if (isset($_POST['feu_einsatz_photo_watermark_text'])) {
            update_option('feu_einsatz_photo_watermark_text', sanitize_text_field(wp_unslash($_POST['feu_einsatz_photo_watermark_text'])));
        }

        update_option(
            'feu_einsatz_photo_watermark_image_id',
            isset($_POST['feu_einsatz_photo_watermark_image_id']) ? absint(wp_unslash($_POST['feu_einsatz_photo_watermark_image_id'])) : 0
        );

        update_option(
            'feu_einsatz_photo_watermark_opacity',
            isset($_POST['feu_einsatz_photo_watermark_opacity']) ? max(5, min(100, absint(wp_unslash($_POST['feu_einsatz_photo_watermark_opacity'])))) : 36
        );

        update_option(
            'feu_einsatz_photo_watermark_scale',
            isset($_POST['feu_einsatz_photo_watermark_scale']) ? max(10, min(90, absint(wp_unslash($_POST['feu_einsatz_photo_watermark_scale'])))) : 42
        );

        if (isset($_POST['feu_einsatz_participant_ranking_pin'])) {
            $submitted_participant_ranking_pin = FEU_Einsatz_Admin::sanitize_participant_ranking_pin(
                wp_unslash($_POST['feu_einsatz_participant_ranking_pin'])
            );

            if ('' !== $submitted_participant_ranking_pin) {
                FEU_Einsatz_Admin::update_participant_ranking_pin($submitted_participant_ranking_pin);
            }
        }

        $this->invalidate_all_share_card_caches();

        FEU_Einsatz_Updater::clear_cached_metadata();
        $current_settings = [
            'feu_einsatz_functions' => array_values((array) get_option('feu_einsatz_functions', [])),
            'feu_einsatz_categories' => array_map('intval', (array) get_option('feu_einsatz_categories', [])),
            'feu_einsatz_map_zoom' => (int) get_option('feu_einsatz_map_zoom', 16),
            'feu_einsatz_map_height' => (int) get_option('feu_einsatz_map_height', 400),
            'feu_einsatz_auto_map_image' => (int) get_option('feu_einsatz_auto_map_image', 1),
            'feu_einsatz_map_preview_heading_text' => (string) get_option('feu_einsatz_map_preview_heading_text', ''),
            'feu_einsatz_map_preview_show_panel' => (int) get_option('feu_einsatz_map_preview_show_panel', 1),
            'feu_einsatz_map_preview_show_panel_heading' => (int) get_option('feu_einsatz_map_preview_show_panel_heading', 1),
            'feu_einsatz_map_preview_show_panel_address' => (int) get_option('feu_einsatz_map_preview_show_panel_address', 1),
            'feu_einsatz_map_preview_panel_position' => $this->normalize_map_preview_position(get_option('feu_einsatz_map_preview_panel_position', 'bottom-left'), 'bottom-left'),
            'feu_einsatz_map_preview_show_street_label' => (int) get_option('feu_einsatz_map_preview_show_street_label', 1),
            'feu_einsatz_map_preview_street_label_prefix' => (string) get_option('feu_einsatz_map_preview_street_label_prefix', 'Einsatz Straße'),
            'feu_einsatz_map_preview_street_label_position' => $this->normalize_map_preview_position(get_option('feu_einsatz_map_preview_street_label_position', 'auto'), 'auto', true),
            'feu_einsatz_map_preview_show_attribution' => (int) get_option('feu_einsatz_map_preview_show_attribution', 1),
            'feu_einsatz_map_preview_attribution_text' => (string) get_option('feu_einsatz_map_preview_attribution_text', 'Leaflet | ' . html_entity_decode('&copy;', ENT_QUOTES, 'UTF-8') . ' OpenStreetMap contributors'),
            'feu_einsatz_map_preview_attribution_position' => $this->normalize_map_preview_position(get_option('feu_einsatz_map_preview_attribution_position', 'bottom-right'), 'bottom-right'),
            'feu_einsatz_map_label_style' => $this->normalize_map_label_style(get_option('feu_einsatz_map_label_style', 'bubble')),
            'feu_einsatz_map_label_text_color' => sanitize_hex_color((string) get_option('feu_einsatz_map_label_text_color', '#ffffff')) ?: '#ffffff',
            'feu_einsatz_map_preview_highlight_color' => (string) get_option('feu_einsatz_map_preview_highlight_color', '#d92d20'),
            'feu_einsatz_map_preview_stroke_width' => max(3, min(18, absint(get_option('feu_einsatz_map_preview_stroke_width', 8)))),
            'feu_einsatz_map_preview_font_family' => $this->normalize_map_preview_font_family(get_option('feu_einsatz_map_preview_font_family', 'auto')),
            'feu_einsatz_area_page_enabled' => (int) get_option('feu_einsatz_area_page_enabled', 0),
            'feu_einsatz_area_show_calls' => (int) get_option('feu_einsatz_area_show_calls', 1),
            'feu_einsatz_area_postcodes' => FEU_Einsatz_Template_Helpers::sanitize_area_entry_list(get_option('feu_einsatz_area_postcodes', [])),
            'feu_einsatz_area_station_street' => (string) get_option('feu_einsatz_area_station_street', ''),
            'feu_einsatz_area_station_postcode' => preg_replace('/\D+/', '', (string) get_option('feu_einsatz_area_station_postcode', '')),
            'feu_einsatz_area_station_city' => (string) get_option('feu_einsatz_area_station_city', 'Hamburg'),
            'feu_einsatz_area_station_logo_id' => absint(get_option('feu_einsatz_area_station_logo_id', 0)),
            'feu_einsatz_area_station_logo_size' => max(20, min(96, absint(get_option('feu_einsatz_area_station_logo_size', 40)))),
            'feu_einsatz_default_comments_enabled' => (int) get_option('feu_einsatz_default_comments_enabled', 0),
            'feu_einsatz_default_card_variant' => FEU_Einsatz_Template_Helpers::normalize_report_card_variant(get_option('feu_einsatz_default_card_variant', 'modern')),
            'feu_einsatz_related_reports_display' => FEU_Einsatz_Template_Helpers::normalize_related_reports_display(get_option('feu_einsatz_related_reports_display', 'cards')),
            'feu_einsatz_related_reports_count' => max(1, min(12, absint(get_option('feu_einsatz_related_reports_count', 6)))),
            'feu_einsatz_single_desaturate_organizations' => (int) get_option('feu_einsatz_single_desaturate_organizations', 0),
            'feu_einsatz_single_info_fields' => FEU_Einsatz_Template_Helpers::normalize_single_info_fields(
                get_option('feu_einsatz_single_info_fields', FEU_Einsatz_Template_Helpers::get_default_single_info_fields())
            ),
            'feu_einsatz_single_map_display_mode' => FEU_Einsatz_Template_Helpers::normalize_single_map_display_mode(get_option('feu_einsatz_single_map_display_mode', 'live')),
            'feu_einsatz_single_map_privacy_mode' => FEU_Einsatz_Template_Helpers::normalize_single_map_privacy_mode(get_option('feu_einsatz_single_map_privacy_mode', 'always')),
            'feu_einsatz_single_live_map_show_station' => (int) get_option('feu_einsatz_single_live_map_show_station', 0),
            'feu_einsatz_overview_show_stats' => (int) get_option('feu_einsatz_overview_show_stats', 1),
            'feu_einsatz_overview_show_year_filter' => (int) get_option('feu_einsatz_overview_show_year_filter', 1),
            'feu_einsatz_social_share_enabled_networks' => FEU_Einsatz_Template_Helpers::normalize_social_share_networks(
                get_option('feu_einsatz_social_share_enabled_networks', FEU_Einsatz_Template_Helpers::get_default_social_share_networks())
            ),
            'feu_einsatz_social_share_image_mode' => FEU_Einsatz_Template_Helpers::normalize_social_share_image_mode(
                get_option('feu_einsatz_social_share_image_mode', 'post_image')
            ),
            'feu_einsatz_social_share_background_id' => absint(get_option('feu_einsatz_social_share_background_id', 0)),
            'feu_einsatz_social_share_fields' => FEU_Einsatz_Template_Helpers::normalize_social_share_fields(
                get_option('feu_einsatz_social_share_fields', FEU_Einsatz_Template_Helpers::get_default_social_share_fields())
            ),
            'feu_einsatz_social_share_layout' => FEU_Einsatz_Template_Helpers::normalize_social_share_layout(
                get_option('feu_einsatz_social_share_layout', 'wide')
            ),
            'feu_einsatz_social_share_logo_id' => absint(get_option('feu_einsatz_social_share_logo_id', 0)),
            'feu_einsatz_social_share_badge_text' => FEU_Einsatz_Template_Helpers::normalize_social_share_card_text(
                get_option('feu_einsatz_social_share_badge_text', 'PRESSEMITTEILUNG'),
                'PRESSEMITTEILUNG'
            ),
            'feu_einsatz_social_share_cta_text' => FEU_Einsatz_Template_Helpers::normalize_social_share_card_text(
                get_option('feu_einsatz_social_share_cta_text', 'Weitere Infos'),
                'Weitere Infos'
            ),
            'feu_einsatz_social_share_title_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_title_color', '#ffffff')) ?: '#ffffff',
            'feu_einsatz_social_share_description_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_description_color', '#dbeafe')) ?: '#dbeafe',
            'feu_einsatz_social_share_panel_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_panel_color', '#0f2f5f')) ?: '#0f2f5f',
            'feu_einsatz_social_share_accent_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_accent_color', '#ef233c')) ?: '#ef233c',
            'feu_einsatz_social_share_cta_fill_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_cta_fill_color', '#ffffff')) ?: '#ffffff',
            'feu_einsatz_social_share_cta_text_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_cta_text_color', '#0f2f5f')) ?: '#0f2f5f',
            'feu_einsatz_social_share_title_scale' => max(70, min(180, absint(get_option('feu_einsatz_social_share_title_scale', 118)))),
            'feu_einsatz_social_share_description_scale' => max(70, min(180, absint(get_option('feu_einsatz_social_share_description_scale', 112)))),
            'feu_einsatz_social_share_description_max_lines' => max(2, min(10, absint(get_option('feu_einsatz_social_share_description_max_lines', 5)))),
            'feu_einsatz_social_share_logo_scale' => max(40, min(220, absint(get_option('feu_einsatz_social_share_logo_scale', 100)))),
            'feu_einsatz_social_share_logo_width' => max(60, min(520, absint(get_option('feu_einsatz_social_share_logo_width', 220)))),
            'feu_einsatz_social_share_overlay_enabled' => (int) get_option('feu_einsatz_social_share_overlay_enabled', 1),
            'feu_einsatz_social_share_image_blur' => max(0, min(20, absint(get_option('feu_einsatz_social_share_image_blur', 0)))),
            'feu_einsatz_social_share_panel_radius' => max(0, min(120, absint(get_option('feu_einsatz_social_share_panel_radius', 30)))),
            'feu_einsatz_social_share_badge_radius' => max(0, min(120, absint(get_option('feu_einsatz_social_share_badge_radius', 40)))),
            'feu_einsatz_social_share_link_radius' => max(0, min(80, absint(get_option('feu_einsatz_social_share_link_radius', 14)))),
            'feu_einsatz_social_share_text_align' => FEU_Einsatz_Template_Helpers::normalize_social_share_text_align(get_option('feu_einsatz_social_share_text_align', 'auto')),
            'feu_einsatz_social_share_logo_position' => FEU_Einsatz_Template_Helpers::normalize_social_share_logo_position(get_option('feu_einsatz_social_share_logo_position', 'bottom-right')),
            'feu_einsatz_social_meta_enabled' => (int) get_option('feu_einsatz_social_meta_enabled', 1),
            'feu_einsatz_social_meta_canonical_enabled' => (int) get_option('feu_einsatz_social_meta_canonical_enabled', 1),
            'feu_einsatz_social_meta_schema_enabled' => (int) get_option('feu_einsatz_social_meta_schema_enabled', 1),
            'feu_einsatz_social_meta_twitter_site' => FEU_Einsatz_Template_Helpers::normalize_social_share_account_handle(
                get_option('feu_einsatz_social_meta_twitter_site', '')
            ),
            'feu_einsatz_role_access' => FEU_Einsatz_Admin::get_plugin_role_access_settings(),
            'feu_einsatz_feature_organizations_enabled' => (int) get_option('feu_einsatz_feature_organizations_enabled', 1),
            'feu_einsatz_default_participant_function' => FEU_Einsatz_Installer::get_default_participant_function(),
            'feu_einsatz_backup_retention_limit' => max(1, absint(get_option('feu_einsatz_backup_retention_limit', 5))),
            'feu_einsatz_photo_watermark_enabled' => (int) get_option('feu_einsatz_photo_watermark_enabled', 1),
            'feu_einsatz_photo_watermark_text' => (string) get_option('feu_einsatz_photo_watermark_text', get_bloginfo('name')),
            'feu_einsatz_photo_watermark_image_id' => absint(get_option('feu_einsatz_photo_watermark_image_id', 0)),
            'feu_einsatz_photo_watermark_opacity' => max(5, min(100, absint(get_option('feu_einsatz_photo_watermark_opacity', 36)))),
            'feu_einsatz_photo_watermark_scale' => max(10, min(90, absint(get_option('feu_einsatz_photo_watermark_scale', 42)))),
        ];
        $current_participant_ranking_pin = FEU_Einsatz_Admin::get_participant_ranking_pin();
        $setting_labels = [
            'feu_einsatz_functions' => __('Funktionen', 'feuer-einsatzberichte'),
            'feu_einsatz_categories' => __('Einsatzstichworte', 'feuer-einsatzberichte'),
            'feu_einsatz_map_zoom' => __('Karten-Zoom', 'feuer-einsatzberichte'),
            'feu_einsatz_map_height' => __('Kartenhöhe', 'feuer-einsatzberichte'),
            'feu_einsatz_auto_map_image' => __('Automatische Kartenbilder', 'feuer-einsatzberichte'),
            'feu_einsatz_map_preview_heading_text' => __('Text im Kartenbild-Panel', 'feuer-einsatzberichte'),
            'feu_einsatz_map_preview_show_panel' => __('Infofeld im Kartenbild', 'feuer-einsatzberichte'),
            'feu_einsatz_map_preview_show_panel_heading' => __('Infofeld-Ueberschrift', 'feuer-einsatzberichte'),
            'feu_einsatz_map_preview_show_panel_address' => __('Infofeld-Adresse', 'feuer-einsatzberichte'),
            'feu_einsatz_map_preview_panel_position' => __('Infofeld-Position', 'feuer-einsatzberichte'),
            'feu_einsatz_map_preview_show_street_label' => __('Straßen-Label am Straßenverlauf', 'feuer-einsatzberichte'),
            'feu_einsatz_map_preview_street_label_prefix' => __('Straßen-Label-Präfix', 'feuer-einsatzberichte'),
            'feu_einsatz_map_preview_street_label_position' => __('Straßen-Label-Position', 'feuer-einsatzberichte'),
            'feu_einsatz_map_preview_show_attribution' => __('Karten-Copyright', 'feuer-einsatzberichte'),
            'feu_einsatz_map_preview_attribution_text' => __('Karten-Copyright-Text', 'feuer-einsatzberichte'),
            'feu_einsatz_map_preview_attribution_position' => __('Karten-Copyright-Position', 'feuer-einsatzberichte'),
            'feu_einsatz_map_label_style' => __('Straßen-Label-Stil', 'feuer-einsatzberichte'),
            'feu_einsatz_map_label_text_color' => __('Straßen-Label-Textfarbe', 'feuer-einsatzberichte'),
            'feu_einsatz_map_preview_highlight_color' => __('Farbe der Straßenmarkierung', 'feuer-einsatzberichte'),
            'feu_einsatz_map_preview_stroke_width' => __('Stärke der Straßenmarkierung', 'feuer-einsatzberichte'),
            'feu_einsatz_map_preview_font_family' => __('Schrift im Kartenbild', 'feuer-einsatzberichte'),
            'feu_einsatz_area_page_enabled' => __('Seite Einsatzgebiet', 'feuer-einsatzberichte'),
            'feu_einsatz_area_show_calls' => __('Einsatz-Zonen auf Einsatzgebiet', 'feuer-einsatzberichte'),
            'feu_einsatz_area_postcodes' => __('PLZ/Gebiete Einsatzgebiet', 'feuer-einsatzberichte'),
            'feu_einsatz_area_station_street' => __('Feuerwehrhaus Straße', 'feuer-einsatzberichte'),
            'feu_einsatz_area_station_postcode' => __('Feuerwehrhaus PLZ', 'feuer-einsatzberichte'),
            'feu_einsatz_area_station_city' => __('Feuerwehrhaus Stadt', 'feuer-einsatzberichte'),
            'feu_einsatz_area_station_logo_id' => __('Feuerwehrhaus Logo', 'feuer-einsatzberichte'),
            'feu_einsatz_area_station_logo_size' => __('Feuerwehrhaus Logo-Größe', 'feuer-einsatzberichte'),
            'feu_einsatz_default_comments_enabled' => __('Standard-Kommentare', 'feuer-einsatzberichte'),
            'feu_einsatz_default_card_variant' => __('Standard-Kartenstil', 'feuer-einsatzberichte'),
            'feu_einsatz_related_reports_display' => __('Weitere Einsatzberichte', 'feuer-einsatzberichte'),
            'feu_einsatz_related_reports_count' => __('Anzahl weitere Einsatzberichte', 'feuer-einsatzberichte'),
            'feu_einsatz_single_desaturate_organizations' => __('Kräfte vor Ort entsättigen', 'feuer-einsatzberichte'),
            'feu_einsatz_single_info_fields' => __('Sichtbare Einsatzinformationen', 'feuer-einsatzberichte'),
            'feu_einsatz_single_map_display_mode' => __('Darstellung von Karte/Bild', 'feuer-einsatzberichte'),
            'feu_einsatz_single_map_privacy_mode' => __('Datenschutz-Verhalten der Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_single_live_map_show_station' => __('Feuerwehrhaus in Live-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_overview_show_stats' => __('Statistik in Übersicht', 'feuer-einsatzberichte'),
            'feu_einsatz_overview_show_year_filter' => __('Jahresfilter in Übersicht', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_enabled_networks' => __('Aktive Netzwerke', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_image_mode' => __('Bildquelle für Teilen', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_background_id' => __('Share-Karten-Hintergrund', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_fields' => __('Share-Inhalte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_layout' => __('Layout der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_logo_id' => __('Logo der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_badge_text' => __('Badge-Text', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_cta_text' => __('CTA-Text', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_title_color' => __('Titelfarbe der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_description_color' => __('Beschreibungsfarbe der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_panel_color' => __('Panel-Farbe der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_accent_color' => __('Akzentfarbe der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_cta_fill_color' => __('CTA-Hintergrundfarbe', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_cta_text_color' => __('CTA-Textfarbe', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_title_scale' => __('Titelgröße der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_description_scale' => __('Beschreibungsgröße der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_description_max_lines' => __('Maximale Beschreibungszeilen', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_logo_scale' => __('Logo-Größe der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_logo_width' => __('Logo-Breite der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_overlay_enabled' => __('Hintergrund-Overlays der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_image_blur' => __('Bild-Weichzeichnung der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_panel_radius' => __('Panel-Rundung der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_badge_radius' => __('Badge-Rundung der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_link_radius' => __('Link-Rundung der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_text_align' => __('Textausrichtung der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_share_logo_position' => __('Logo-Position der Share-Karte', 'feuer-einsatzberichte'),
            'feu_einsatz_social_meta_enabled' => __('Open Graph und X Cards', 'feuer-einsatzberichte'),
            'feu_einsatz_social_meta_canonical_enabled' => __('Canonical und Meta-Description', 'feuer-einsatzberichte'),
            'feu_einsatz_social_meta_schema_enabled' => __('Schema.org für Suchmaschinen', 'feuer-einsatzberichte'),
            'feu_einsatz_social_meta_twitter_site' => __('X/Twitter @Handle', 'feuer-einsatzberichte'),
            'feu_einsatz_role_access' => __('Zugriffsrechte', 'feuer-einsatzberichte'),
            'feu_einsatz_feature_organizations_enabled' => __('Funktion: Kräfte vor Ort', 'feuer-einsatzberichte'),
            'feu_einsatz_default_participant_function' => __('Standardfunktion', 'feuer-einsatzberichte'),
            'feu_einsatz_backup_retention_limit' => __('Archiv-Limit', 'feuer-einsatzberichte'),
            'feu_einsatz_photo_watermark_enabled' => __('Foto-Wasserzeichen', 'feuer-einsatzberichte'),
            'feu_einsatz_photo_watermark_text' => __('Wasserzeichen-Text', 'feuer-einsatzberichte'),
            'feu_einsatz_photo_watermark_image_id' => __('Wasserzeichen-Bild', 'feuer-einsatzberichte'),
            'feu_einsatz_photo_watermark_opacity' => __('Wasserzeichen-Deckkraft', 'feuer-einsatzberichte'),
            'feu_einsatz_photo_watermark_scale' => __('Wasserzeichen-Größe', 'feuer-einsatzberichte'),
        ];
        $changed_settings = [];

        foreach ($setting_labels as $setting_key => $setting_label) {
            $before_value = $previous_settings[$setting_key] ?? null;
            $after_value = $current_settings[$setting_key] ?? null;

            if (wp_json_encode($before_value) === wp_json_encode($after_value)) {
                continue;
            }

            $changed_settings[$setting_key] = [
                'label' => $setting_label,
                'before' => $before_value,
                'after' => $after_value,
            ];
        }

        if (
            '' !== $current_participant_ranking_pin
            && !hash_equals($previous_participant_ranking_pin, $current_participant_ranking_pin)
        ) {
            $changed_settings['feu_einsatz_participant_ranking_pin'] = [
                'label' => __('PIN Teilnehmer-Ranking', 'feuer-einsatzberichte'),
                'before' => '' === $previous_participant_ranking_pin ? __('nicht gesetzt', 'feuer-einsatzberichte') : __('gesetzt', 'feuer-einsatzberichte'),
                'after' => '' === $previous_participant_ranking_pin ? __('gesetzt', 'feuer-einsatzberichte') : __('geaendert', 'feuer-einsatzberichte'),
            ];
        }

        $map_preview_style_setting_keys = [
            'feu_einsatz_map_zoom',
            'feu_einsatz_map_height',
            'feu_einsatz_map_preview_heading_text',
            'feu_einsatz_map_preview_show_panel',
            'feu_einsatz_map_preview_show_panel_heading',
            'feu_einsatz_map_preview_show_panel_address',
            'feu_einsatz_map_preview_panel_position',
            'feu_einsatz_map_preview_show_street_label',
            'feu_einsatz_map_preview_street_label_prefix',
            'feu_einsatz_map_preview_street_label_position',
            'feu_einsatz_map_preview_show_attribution',
            'feu_einsatz_map_preview_attribution_text',
            'feu_einsatz_map_preview_attribution_position',
            'feu_einsatz_map_label_style',
            'feu_einsatz_map_label_text_color',
            'feu_einsatz_map_preview_highlight_color',
            'feu_einsatz_map_preview_stroke_width',
            'feu_einsatz_map_preview_font_family',
            'feu_einsatz_area_station_street',
            'feu_einsatz_area_station_postcode',
            'feu_einsatz_area_station_city',
            'feu_einsatz_area_station_logo_id',
            'feu_einsatz_area_station_logo_size',
        ];
        $map_preview_settings_changed = !empty(array_intersect(array_keys($changed_settings), $map_preview_style_setting_keys));

        FEU_Einsatz_Logger::log(
            'settings_saved',
            'settings',
            0,
            __('Plugin-Einstellungen gespeichert', 'feuer-einsatzberichte'),
            [
                'changed_count' => count($changed_settings),
                'changed_settings' => $changed_settings,
                'default_comments_enabled' => 1 === (int) get_option('feu_einsatz_default_comments_enabled', 0),
                'backup_retention_limit' => (int) get_option('feu_einsatz_backup_retention_limit', 5),
                'selected_categories' => $categories,
            ]
        );

        $success_message = __('Einstellungen wurden erfolgreich gespeichert.', 'feuer-einsatzberichte');

        if ($map_preview_settings_changed) {
            $success_message .= ' ' . __('Neue Karten-Einstellungen gelten sofort fuer neue Berichte. Bereits vorhandene Kartenbilder koennen bei Bedarf manuell neu aufgebaut werden.', 'feuer-einsatzberichte');
        }

        echo '<div class="notice notice-success is-dismissible"><p>' .
             esc_html($success_message) .
             '</p></div>';
    }
}

$settings_tabs = ['allgemein', 'zugriff', 'medien', 'sozial', 'funktionen', 'organisationen', 'kategorien', 'karten', 'strassenregister', 'manifest', 'shortcodes'];
$active_tab = 'allgemein';
$legacy_settings_tab_aliases = [
    'einzelbeitrag' => 'allgemein',
    'uebersicht' => 'allgemein',
    'sicherheit' => 'allgemein',
];

if (isset($_POST['feu_einsatz_active_tab'])) {
    $requested_tab = sanitize_key(wp_unslash($_POST['feu_einsatz_active_tab']));
    $requested_tab = $legacy_settings_tab_aliases[$requested_tab] ?? $requested_tab;

    if (in_array($requested_tab, $settings_tabs, true)) {
        $active_tab = $requested_tab;
    }
} elseif (isset($_GET['tab'])) {
    $requested_tab = sanitize_key(wp_unslash($_GET['tab']));
    $requested_tab = $legacy_settings_tab_aliases[$requested_tab] ?? $requested_tab;

    if (in_array($requested_tab, $settings_tabs, true)) {
        $active_tab = $requested_tab;
    }
}

$is_karten_tab = 'karten' === $active_tab;
$is_strassenregister_tab = 'strassenregister' === $active_tab;
$is_manifest_tab = 'manifest' === $active_tab;

$functions = get_option('feu_einsatz_functions', FEU_Einsatz_Installer::get_default_functions());
$participant_fallback_function = FEU_Einsatz_Installer::get_default_participant_function();

$selected_categories = get_option('feu_einsatz_categories', []);
$map_zoom = get_option('feu_einsatz_map_zoom', 16);
$map_height = get_option('feu_einsatz_map_height', 400);
$auto_map_image = get_option('feu_einsatz_auto_map_image', 1);
$map_preview_heading_text = (string) get_option('feu_einsatz_map_preview_heading_text', '');
$map_preview_show_panel = (int) get_option('feu_einsatz_map_preview_show_panel', 1);
$map_preview_show_panel_heading = (int) get_option('feu_einsatz_map_preview_show_panel_heading', 1);
$map_preview_show_panel_address = (int) get_option('feu_einsatz_map_preview_show_panel_address', 1);
$map_preview_panel_position = $this->normalize_map_preview_position(get_option('feu_einsatz_map_preview_panel_position', 'bottom-left'), 'bottom-left');
$map_preview_show_street_label = (int) get_option('feu_einsatz_map_preview_show_street_label', 1);
$map_preview_street_label_prefix = (string) get_option('feu_einsatz_map_preview_street_label_prefix', 'Einsatz Straße');
$map_preview_street_label_position = $this->normalize_map_preview_position(get_option('feu_einsatz_map_preview_street_label_position', 'auto'), 'auto', true);
$map_preview_show_attribution = (int) get_option('feu_einsatz_map_preview_show_attribution', 1);
$map_preview_attribution_text = (string) get_option('feu_einsatz_map_preview_attribution_text', 'Leaflet | ' . html_entity_decode('&copy;', ENT_QUOTES, 'UTF-8') . ' OpenStreetMap contributors');
$map_preview_attribution_position = $this->normalize_map_preview_position(get_option('feu_einsatz_map_preview_attribution_position', 'bottom-right'), 'bottom-right');
$map_preview_position_options = $this->get_map_preview_position_options(false);
$map_preview_label_position_options = $this->get_map_preview_position_options(true);
$map_label_style = $this->normalize_map_label_style(get_option('feu_einsatz_map_label_style', 'bubble'));
$map_label_text_color = sanitize_hex_color((string) get_option('feu_einsatz_map_label_text_color', '#ffffff')) ?: '#ffffff';
$map_preview_highlight_color = (string) get_option('feu_einsatz_map_preview_highlight_color', '#d92d20');
$map_preview_stroke_width = max(3, min(18, absint(get_option('feu_einsatz_map_preview_stroke_width', 8))));
$map_preview_font_family = $this->normalize_map_preview_font_family(get_option('feu_einsatz_map_preview_font_family', 'auto'));
$map_preview_font_options = $this->get_map_preview_font_option_definitions();
$area_page_enabled = (int) get_option('feu_einsatz_area_page_enabled', 0);
$area_show_calls = (int) get_option('feu_einsatz_area_show_calls', 1);
$area_entries = FEU_Einsatz_Template_Helpers::sanitize_area_entry_list(get_option('feu_einsatz_area_postcodes', []));
$area_station_street = (string) get_option('feu_einsatz_area_station_street', '');
$area_station_postcode = preg_replace('/\D+/', '', (string) get_option('feu_einsatz_area_station_postcode', ''));
$area_station_city = (string) get_option('feu_einsatz_area_station_city', 'Hamburg');
$area_station_logo_id = absint(get_option('feu_einsatz_area_station_logo_id', 0));
$area_station_logo_size = max(20, min(96, absint(get_option('feu_einsatz_area_station_logo_size', 40))));
$area_station_logo_url = $area_station_logo_id ? wp_get_attachment_image_url($area_station_logo_id, 'medium') : '';
$default_comments_enabled = (int) get_option('feu_einsatz_default_comments_enabled', 0);
$default_card_variant = FEU_Einsatz_Template_Helpers::normalize_report_card_variant(get_option('feu_einsatz_default_card_variant', 'modern'));
$related_reports_display = FEU_Einsatz_Template_Helpers::normalize_related_reports_display(get_option('feu_einsatz_related_reports_display', 'cards'));
$related_reports_count = max(1, min(12, absint(get_option('feu_einsatz_related_reports_count', 6))));
$single_live_map_show_station = (int) get_option('feu_einsatz_single_live_map_show_station', 0);
$single_desaturate_organizations = (int) get_option('feu_einsatz_single_desaturate_organizations', 0);
$single_info_fields = FEU_Einsatz_Template_Helpers::normalize_single_info_fields(
    get_option('feu_einsatz_single_info_fields', FEU_Einsatz_Template_Helpers::get_default_single_info_fields())
);
$single_map_display_mode = FEU_Einsatz_Template_Helpers::normalize_single_map_display_mode(get_option('feu_einsatz_single_map_display_mode', 'live'));
$single_map_privacy_mode = FEU_Einsatz_Template_Helpers::normalize_single_map_privacy_mode(get_option('feu_einsatz_single_map_privacy_mode', 'always'));
$overview_show_stats = (int) get_option('feu_einsatz_overview_show_stats', 1);
$overview_show_year_filter = (int) get_option('feu_einsatz_overview_show_year_filter', 1);
$social_share_network_definitions = FEU_Einsatz_Template_Helpers::get_social_share_network_definitions();
$social_share_enabled_networks = FEU_Einsatz_Template_Helpers::normalize_social_share_networks(
    get_option('feu_einsatz_social_share_enabled_networks', FEU_Einsatz_Template_Helpers::get_default_social_share_networks())
);
$social_share_image_mode = FEU_Einsatz_Template_Helpers::normalize_social_share_image_mode(get_option('feu_einsatz_social_share_image_mode', 'post_image'));
$social_share_background_id = absint(get_option('feu_einsatz_social_share_background_id', 0));
$social_share_background_url = $social_share_background_id ? wp_get_attachment_image_url($social_share_background_id, 'medium') : '';
$social_share_field_definitions = FEU_Einsatz_Template_Helpers::get_social_share_field_definitions();
$social_share_fields = FEU_Einsatz_Template_Helpers::normalize_social_share_fields(
    get_option('feu_einsatz_social_share_fields', FEU_Einsatz_Template_Helpers::get_default_social_share_fields())
);
$social_share_layout_definitions = FEU_Einsatz_Template_Helpers::get_social_share_layout_definitions();
$social_share_layout = FEU_Einsatz_Template_Helpers::normalize_social_share_layout(
    get_option('feu_einsatz_social_share_layout', 'wide')
);
$social_share_logo_id = absint(get_option('feu_einsatz_social_share_logo_id', 0));
$social_share_logo_url = $social_share_logo_id ? wp_get_attachment_image_url($social_share_logo_id, 'medium') : '';
$social_share_badge_text = FEU_Einsatz_Template_Helpers::normalize_social_share_card_text(
    get_option('feu_einsatz_social_share_badge_text', 'PRESSEMITTEILUNG'),
    'PRESSEMITTEILUNG'
);
$social_share_cta_text = FEU_Einsatz_Template_Helpers::normalize_social_share_card_text(
    get_option('feu_einsatz_social_share_cta_text', 'Weitere Infos'),
    'Weitere Infos'
);
$social_share_title_color = sanitize_hex_color((string) get_option('feu_einsatz_social_share_title_color', '#ffffff')) ?: '#ffffff';
$social_share_description_color = sanitize_hex_color((string) get_option('feu_einsatz_social_share_description_color', '#dbeafe')) ?: '#dbeafe';
$social_share_panel_color = sanitize_hex_color((string) get_option('feu_einsatz_social_share_panel_color', '#0f2f5f')) ?: '#0f2f5f';
$social_share_accent_color = sanitize_hex_color((string) get_option('feu_einsatz_social_share_accent_color', '#ef233c')) ?: '#ef233c';
$social_share_cta_fill_color = sanitize_hex_color((string) get_option('feu_einsatz_social_share_cta_fill_color', '#ffffff')) ?: '#ffffff';
$social_share_cta_text_color = sanitize_hex_color((string) get_option('feu_einsatz_social_share_cta_text_color', '#0f2f5f')) ?: '#0f2f5f';
$social_share_title_scale = max(70, min(180, absint(get_option('feu_einsatz_social_share_title_scale', 118))));
$social_share_description_scale = max(70, min(180, absint(get_option('feu_einsatz_social_share_description_scale', 112))));
$social_share_description_max_lines = max(2, min(10, absint(get_option('feu_einsatz_social_share_description_max_lines', 5))));
$social_share_logo_scale = max(40, min(220, absint(get_option('feu_einsatz_social_share_logo_scale', 100))));
$social_share_logo_width = max(60, min(520, absint(get_option('feu_einsatz_social_share_logo_width', 220))));
$social_share_overlay_enabled = (int) get_option('feu_einsatz_social_share_overlay_enabled', 1);
$social_share_image_blur = max(0, min(20, absint(get_option('feu_einsatz_social_share_image_blur', 0))));
$social_share_panel_radius = max(0, min(120, absint(get_option('feu_einsatz_social_share_panel_radius', 30))));
$social_share_badge_radius = max(0, min(120, absint(get_option('feu_einsatz_social_share_badge_radius', 40))));
$social_share_link_radius = max(0, min(80, absint(get_option('feu_einsatz_social_share_link_radius', 14))));
$social_share_text_align_options = FEU_Einsatz_Template_Helpers::get_social_share_text_align_options();
$social_share_text_align = FEU_Einsatz_Template_Helpers::normalize_social_share_text_align(
    get_option('feu_einsatz_social_share_text_align', 'auto')
);
$social_share_logo_position_options = FEU_Einsatz_Template_Helpers::get_social_share_logo_position_options();
$social_share_logo_position = FEU_Einsatz_Template_Helpers::normalize_social_share_logo_position(
    get_option('feu_einsatz_social_share_logo_position', 'bottom-right')
);
$social_meta_enabled = (int) get_option('feu_einsatz_social_meta_enabled', 1);
$social_meta_canonical_enabled = (int) get_option('feu_einsatz_social_meta_canonical_enabled', 1);
$social_meta_schema_enabled = (int) get_option('feu_einsatz_social_meta_schema_enabled', 1);
$social_meta_twitter_site = FEU_Einsatz_Template_Helpers::normalize_social_share_account_handle(
    get_option('feu_einsatz_social_meta_twitter_site', '')
);
$social_share_preview_example = [
    'site_name' => (string) get_bloginfo('name'),
    'badge' => $social_share_badge_text,
    'title' => 'FEUK – Beispielstraße',
    'number' => '55/2026',
    'date' => '30.04.2026',
    'time' => '20:58',
    'category' => 'FEUK',
    'street' => 'Beispielstraße',
    'location' => '22547 Hamburg',
    'description' => 'Kurzer Beispieltext für die Share-Karte. So sehen Titel, Metadaten und Beschreibung direkt auf dem Bild aus.',
    'url' => home_url('/einsaetze/beispiel/'),
];
$social_share_preview_example['title'] = 'FEUK – Beispielstraße';
$social_share_preview_example['street'] = 'Beispielstraße';
$social_share_preview_example['description'] = 'Kurzer Beispieltext für die Share-Karte. So sehen Titel, Metadaten und Beschreibung direkt auf dem Bild aus.';
$card_variants = FEU_Einsatz_Template_Helpers::get_report_card_variant_definitions();
$backup_retention_limit = max(1, absint(get_option('feu_einsatz_backup_retention_limit', 5)));
$photo_watermark_enabled = get_option('feu_einsatz_photo_watermark_enabled', 1);
$photo_watermark_text = get_option('feu_einsatz_photo_watermark_text', get_bloginfo('name'));
$photo_watermark_image_id = absint(get_option('feu_einsatz_photo_watermark_image_id', 0));
$photo_watermark_opacity = max(5, min(100, absint(get_option('feu_einsatz_photo_watermark_opacity', 36))));
$photo_watermark_scale = max(10, min(90, absint(get_option('feu_einsatz_photo_watermark_scale', 42))));
$photo_watermark_image_url = $photo_watermark_image_id ? wp_get_attachment_image_url($photo_watermark_image_id, 'medium') : '';
$map_preview_live_station_feature = FEU_Einsatz_Template_Helpers::get_station_feature_from_settings();
$map_preview_live_center = [
    'lat' => 53.5853,
    'lng' => 9.8827,
];

if (
    is_array($map_preview_live_station_feature)
    && isset($map_preview_live_station_feature['latitude'], $map_preview_live_station_feature['longitude'])
    && is_numeric($map_preview_live_station_feature['latitude'])
    && is_numeric($map_preview_live_station_feature['longitude'])
) {
    $map_preview_live_center = [
        'lat' => (float) $map_preview_live_station_feature['latitude'],
        'lng' => (float) $map_preview_live_station_feature['longitude'],
    ];
}

$map_preview_live_address = trim(implode(', ', array_filter([
    $area_station_street ?: 'Beispielstrasse',
    trim(($area_station_postcode ?: '22547') . ' ' . ($area_station_city ?: 'Hamburg')),
])));

if ('' !== $map_preview_live_address) {
    $map_preview_live_address .= ', Deutschland';
}

$map_preview_live_geometry_payload = $is_karten_tab
    ? FEU_Einsatz_Template_Helpers::get_station_street_geometry_from_settings(false)
    : false;
$map_preview_live_sample_geometry = (
    is_array($map_preview_live_geometry_payload)
    && !empty($map_preview_live_geometry_payload['geometry'])
    && is_array($map_preview_live_geometry_payload['geometry'])
)
    ? $map_preview_live_geometry_payload['geometry']
    : [];

if ($is_karten_tab && empty($map_preview_live_sample_geometry)) {
    $map_preview_live_sample_geometry = [
        [
            'kind' => 'road',
            'points' => [
                [
                    'lat' => $map_preview_live_center['lat'] - 0.00042,
                    'lng' => $map_preview_live_center['lng'] - 0.00115,
                ],
                [
                    'lat' => $map_preview_live_center['lat'] - 0.00014,
                    'lng' => $map_preview_live_center['lng'] - 0.00038,
                ],
                [
                    'lat' => $map_preview_live_center['lat'] + 0.00018,
                    'lng' => $map_preview_live_center['lng'] + 0.00042,
                ],
                [
                    'lat' => $map_preview_live_center['lat'] + 0.00044,
                    'lng' => $map_preview_live_center['lng'] + 0.00116,
                ],
            ],
        ],
    ];
}

$map_preview_live_fallback_markup = $is_karten_tab
    ? FEU_Einsatz_Template_Helpers::build_local_map_preview_markup([
        'latitude' => $map_preview_live_center['lat'] + 0.00015,
        'longitude' => $map_preview_live_center['lng'] + 0.00015,
        'center' => [$map_preview_live_center['lat'], $map_preview_live_center['lng']],
        'geometry' => $map_preview_live_sample_geometry,
        'address' => $map_preview_live_address,
        'height' => $map_height,
        'station' => false,
        'show_station' => false,
        'line_color' => $map_preview_highlight_color,
        'line_width' => $map_preview_stroke_width,
        'font_stack' => isset($map_preview_font_options[$map_preview_font_family]['css_stack'])
            ? (string) $map_preview_font_options[$map_preview_font_family]['css_stack']
            : '"Segoe UI", Arial, sans-serif',
        'heading_text' => $map_preview_heading_text,
        'preview_mode' => 'minimal',
    ])
    : '';
$participant_ranking_pin_is_configured = '' !== FEU_Einsatz_Admin::get_participant_ranking_pin();
$street_cache_entries = $is_karten_tab ? FEU_Einsatz_Street_Cache::get_cache_entry_count() : 0;
$street_registry_entries = $is_strassenregister_tab ? $this->db->get_street_registry_entries(['limit' => 1000]) : [];
$street_suggestion_records = $is_strassenregister_tab ? FEU_Einsatz_Template_Helpers::get_street_suggestion_records(1000) : [];
$plugin_access_roles = 'zugriff' === $active_tab ? FEU_Einsatz_Admin::get_plugin_access_roles() : [];
$plugin_access_sections = 'zugriff' === $active_tab ? FEU_Einsatz_Admin::get_plugin_access_sections() : [];
$plugin_role_access_settings = 'zugriff' === $active_tab ? FEU_Einsatz_Admin::get_plugin_role_access_settings() : [];
$plugin_full_access_roles = 'zugriff' === $active_tab ? FEU_Einsatz_Admin::get_plugin_full_access_roles() : [];
$generated_map_rebuild_candidates = $is_karten_tab ? $this->count_generated_map_rebuild_candidates() : 0;
$generated_map_rebuild_from = isset($_POST['feu_einsatz_generated_map_rebuild_from'])
    ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_generated_map_rebuild_from']))
    : '';
$generated_map_rebuild_to = isset($_POST['feu_einsatz_generated_map_rebuild_to'])
    ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_generated_map_rebuild_to']))
    : '';
$manifest_path = FEU_EINSATZ_PLUGIN_DIR . 'update-manifest.json';
$manifest_contents = $is_manifest_tab && file_exists($manifest_path)
    ? trim((string) file_get_contents($manifest_path))
    : '';
$manifest_display_contents = $manifest_contents;
if ('' !== $manifest_display_contents) {
    $manifest_display_payload = json_decode($manifest_display_contents, true);
    if (is_array($manifest_display_payload)) {
        unset($manifest_display_payload['download_url'], $manifest_display_payload['package']);
        $manifest_display_contents = (string) wp_json_encode($manifest_display_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
$manifest_example_path = FEU_EINSATZ_PLUGIN_DIR . 'update-manifest.example.json';
$manifest_example_contents = $is_manifest_tab && file_exists($manifest_example_path)
    ? trim((string) file_get_contents($manifest_example_path))
    : '';
$release_notes_path = FEU_EINSATZ_PLUGIN_DIR . 'release-notes.json';
$release_notes_contents = $is_manifest_tab && file_exists($release_notes_path)
    ? trim((string) file_get_contents($release_notes_path))
    : '';
$release_notes_data = json_decode($release_notes_contents, true);
$release_notes_entries = (is_array($release_notes_data) && !empty($release_notes_data['releases']) && is_array($release_notes_data['releases']))
    ? $release_notes_data['releases']
    : [];
$remote_manifest_url = FEU_Einsatz_Updater::get_manifest_url();
$updater = Feuer_Einsatzberichte_Core::get_instance()->get_updater();
$remote_manifest_payload = ($is_manifest_tab && $updater instanceof FEU_Einsatz_Updater) ? $updater->get_manifest_payload() : false;
$functions_reference_path = FEU_EINSATZ_PLUGIN_DIR . 'docs/PLUGIN_FUNCTIONS_AND_FILES.md';
$update_guide_path = FEU_EINSATZ_PLUGIN_DIR . 'docs/UPDATE_HOSTING_AND_GITHUB.md';
$docs_directory = FEU_EINSATZ_PLUGIN_DIR . 'docs';
$templates_directory = FEU_EINSATZ_PLUGIN_DIR . 'templates';
$all_categories = get_categories(['hide_empty' => false]);
$default_categories_root = get_term_by('slug', 'einsatze', 'category');
$default_categories_prompt = 1 === (int) get_option('feu_einsatz_default_categories_prompt', 0);
$organizations = $this->db->get_organizations([
    'include_archived' => true,
]);
$settings_summary_cards = [
    [
        'label' => __('Funktionen', 'feuer-einsatzberichte'),
        'value' => count((array) $functions),
        'icon' => 'ti ti-badge',
    ],
    [
        'label' => __('Einsatzstichworte', 'feuer-einsatzberichte'),
        'value' => count((array) $selected_categories),
        'icon' => 'ti ti-tags',
    ],
    [
        'label' => __('Organisationen', 'feuer-einsatzberichte'),
        'value' => count((array) $organizations),
        'icon' => 'ti ti-building-community',
    ],
    [
        'label' => __('Version', 'feuer-einsatzberichte'),
        'value' => FEU_EINSATZ_VERSION,
        'icon' => 'ti ti-package',
    ],
];
?>

<div class="wrap feu-einsatz-settings feu-admin-page">
    <div class="feu-admin-page-header">
        <div class="feu-admin-page-heading">
            <span class="feu-admin-page-eyebrow"><?php esc_html_e('Plugin-Konfiguration', 'feuer-einsatzberichte'); ?></span>
            <h1 class="wp-heading-inline"><?php _e('Einstellungen', 'feuer-einsatzberichte'); ?></h1>
            <p><?php esc_html_e('Zentrale Verwaltung fuer Darstellung, Karten, Teilen, Zugriffe und technische Optionen des Plugins.', 'feuer-einsatzberichte'); ?></p>
        </div>
        <div class="feu-admin-page-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=feu-einsatz-statistiken')); ?>" class="button button-secondary">
                <span class="ti ti-chart-donut-3"></span>
                <?php esc_html_e('Statistiken', 'feuer-einsatzberichte'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=feu-einsatz-archive')); ?>" class="button button-primary">
                <span class="ti ti-archive"></span>
                <?php esc_html_e('Archive', 'feuer-einsatzberichte'); ?>
            </a>
        </div>
    </div>

    <div class="feu-admin-stat-grid feu-admin-stat-grid--compact">
        <?php foreach ($settings_summary_cards as $summary_card) : ?>
            <article class="feu-admin-stat-card">
                <div class="feu-admin-stat-icon"><span class="<?php echo esc_attr($summary_card['icon']); ?>"></span></div>
                <div class="feu-admin-stat-content">
                    <span class="feu-admin-stat-label"><?php echo esc_html($summary_card['label']); ?></span>
                    <strong class="feu-admin-stat-value"><?php echo esc_html((string) $summary_card['value']); ?></strong>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="feu-admin-settings-shell feu-admin-settings-shell--topnav">
        <div class="feu-admin-settings-topbar">
            <?php
            echo FEU_Einsatz_Template_Helpers::render('templates/admin/settings/partials/tabs.php', [
                'active_tab' => $active_tab,
            ]);
            ?>
        </div>

        <form method="post" action="" id="feu-einsatz-settings-form" class="feu-admin-settings-main">
        <?php wp_nonce_field('feu_einsatz_save_settings', 'feu_einsatz_settings_nonce'); ?>
        <input type="hidden" name="feu_einsatz_active_tab" id="feu_einsatz_active_tab" value="<?php echo esc_attr($active_tab); ?>" />

        <div class="feu-admin-settings-sections">
        <?php
        echo FEU_Einsatz_Template_Helpers::render_guarded('templates/admin/settings/partials/tab-allgemein.php', [
            'active_tab' => $active_tab,
            'auto_map_image' => $auto_map_image,
            'default_comments_enabled' => $default_comments_enabled,
            'default_card_variant' => $default_card_variant,
            'card_variants' => $card_variants,
            'overview_show_stats' => $overview_show_stats,
            'overview_show_year_filter' => $overview_show_year_filter,
            'backup_retention_limit' => $backup_retention_limit,
            'participant_ranking_pin_is_configured' => $participant_ranking_pin_is_configured,
            'related_reports_display' => $related_reports_display,
            'related_reports_count' => $related_reports_count,
            'single_desaturate_organizations' => $single_desaturate_organizations,
            'single_info_fields' => $single_info_fields,
            'single_map_display_mode' => $single_map_display_mode,
            'single_map_privacy_mode' => $single_map_privacy_mode,
        ], 'Einstellungen: Allgemein');

        echo FEU_Einsatz_Template_Helpers::render_guarded('templates/admin/settings/partials/tab-zugriff.php', [
            'active_tab' => $active_tab,
            'plugin_access_roles' => $plugin_access_roles,
            'plugin_access_sections' => $plugin_access_sections,
            'plugin_role_access_settings' => $plugin_role_access_settings,
            'plugin_full_access_roles' => $plugin_full_access_roles,
        ], 'Einstellungen: Zugriff');

        echo FEU_Einsatz_Template_Helpers::render_guarded('templates/admin/settings/partials/tab-medien.php', [
            'active_tab' => $active_tab,
            'photo_watermark_enabled' => $photo_watermark_enabled,
            'photo_watermark_text' => $photo_watermark_text,
            'photo_watermark_image_id' => $photo_watermark_image_id,
            'photo_watermark_image_url' => $photo_watermark_image_url,
            'photo_watermark_opacity' => $photo_watermark_opacity,
            'photo_watermark_scale' => $photo_watermark_scale,
        ], 'Einstellungen: Medien');

        echo FEU_Einsatz_Template_Helpers::render_guarded('templates/admin/settings/partials/tab-sozial.php', [
            'active_tab' => $active_tab,
            'social_share_network_definitions' => $social_share_network_definitions,
            'social_share_enabled_networks' => $social_share_enabled_networks,
            'social_share_image_mode' => $social_share_image_mode,
            'social_share_background_id' => $social_share_background_id,
            'social_share_background_url' => $social_share_background_url,
            'social_share_field_definitions' => $social_share_field_definitions,
            'social_share_fields' => $social_share_fields,
            'social_share_layout_definitions' => $social_share_layout_definitions,
            'social_share_layout' => $social_share_layout,
            'social_share_logo_id' => $social_share_logo_id,
            'social_share_logo_url' => $social_share_logo_url,
            'social_share_badge_text' => $social_share_badge_text,
            'social_share_cta_text' => $social_share_cta_text,
            'social_share_title_color' => $social_share_title_color,
            'social_share_description_color' => $social_share_description_color,
            'social_share_panel_color' => $social_share_panel_color,
            'social_share_accent_color' => $social_share_accent_color,
            'social_share_cta_fill_color' => $social_share_cta_fill_color,
            'social_share_cta_text_color' => $social_share_cta_text_color,
            'social_share_title_scale' => $social_share_title_scale,
            'social_share_description_scale' => $social_share_description_scale,
            'social_share_description_max_lines' => $social_share_description_max_lines,
            'social_share_logo_scale' => $social_share_logo_scale,
            'social_share_logo_width' => $social_share_logo_width,
            'social_share_overlay_enabled' => $social_share_overlay_enabled,
            'social_share_image_blur' => $social_share_image_blur,
            'social_share_panel_radius' => $social_share_panel_radius,
            'social_share_badge_radius' => $social_share_badge_radius,
            'social_share_link_radius' => $social_share_link_radius,
            'social_share_text_align_options' => $social_share_text_align_options,
            'social_share_text_align' => $social_share_text_align,
            'social_share_logo_position_options' => $social_share_logo_position_options,
            'social_share_logo_position' => $social_share_logo_position,
            'social_meta_enabled' => $social_meta_enabled,
            'social_meta_canonical_enabled' => $social_meta_canonical_enabled,
            'social_meta_schema_enabled' => $social_meta_schema_enabled,
            'social_meta_twitter_site' => $social_meta_twitter_site,
            'social_share_preview_example' => $social_share_preview_example,
        ], 'Einstellungen: Soziale Netzwerke');

        echo FEU_Einsatz_Template_Helpers::render_guarded('templates/admin/settings/partials/tab-funktionen.php', [
            'active_tab' => $active_tab,
            'functions' => $functions,
            'participant_fallback_function' => $participant_fallback_function,
        ], 'Einstellungen: Funktionen');

        echo FEU_Einsatz_Template_Helpers::render_guarded('templates/admin/settings/partials/tab-organisationen.php', [
            'active_tab' => $active_tab,
            'organizations' => $organizations,
            'organizations_enabled' => (int) get_option('feu_einsatz_feature_organizations_enabled', 1),
        ], 'Einstellungen: Organisationen');

        echo FEU_Einsatz_Template_Helpers::render_guarded('templates/admin/settings/partials/tab-kategorien.php', [
            'active_tab' => $active_tab,
            'all_categories' => $all_categories,
            'selected_categories' => $selected_categories,
            'default_categories_root' => $default_categories_root,
            'default_categories_prompt' => $default_categories_prompt,
        ], 'Einstellungen: Einsatzstichworte');

        echo FEU_Einsatz_Template_Helpers::render_guarded('templates/admin/settings/partials/tab-karten.php', [
            'active_tab' => $active_tab,
            'auto_map_image' => $auto_map_image,
            'map_zoom' => $map_zoom,
            'map_height' => $map_height,
            'map_preview_heading_text' => $map_preview_heading_text,
            'map_preview_show_panel' => $map_preview_show_panel,
            'map_preview_show_panel_heading' => $map_preview_show_panel_heading,
            'map_preview_show_panel_address' => $map_preview_show_panel_address,
            'map_preview_panel_position' => $map_preview_panel_position,
            'map_preview_show_street_label' => $map_preview_show_street_label,
            'map_preview_street_label_prefix' => $map_preview_street_label_prefix,
            'map_preview_street_label_position' => $map_preview_street_label_position,
            'map_preview_show_attribution' => $map_preview_show_attribution,
            'map_preview_attribution_text' => $map_preview_attribution_text,
            'map_preview_attribution_position' => $map_preview_attribution_position,
            'map_preview_position_options' => $map_preview_position_options,
            'map_preview_label_position_options' => $map_preview_label_position_options,
            'map_label_style' => $map_label_style,
            'map_label_text_color' => $map_label_text_color,
            'map_preview_highlight_color' => $map_preview_highlight_color,
            'map_preview_stroke_width' => $map_preview_stroke_width,
            'map_preview_font_family' => $map_preview_font_family,
            'map_preview_font_options' => $map_preview_font_options,
            'single_live_map_show_station' => $single_live_map_show_station,
            'area_page_enabled' => $area_page_enabled,
            'area_show_calls' => $area_show_calls,
            'area_entries' => $area_entries,
            'area_station_street' => $area_station_street,
            'area_station_postcode' => $area_station_postcode,
            'area_station_city' => $area_station_city,
            'area_station_logo_id' => $area_station_logo_id,
            'area_station_logo_url' => $area_station_logo_url,
            'area_station_logo_size' => $area_station_logo_size,
            'photo_watermark_text' => $photo_watermark_text,
            'map_preview_live_station_feature' => $map_preview_live_station_feature,
            'map_preview_live_center' => $map_preview_live_center,
            'map_preview_live_geometry' => $map_preview_live_sample_geometry,
            'map_preview_live_fallback_markup' => $map_preview_live_fallback_markup,
            'street_cache_entries' => $street_cache_entries,
            'street_registry_entries' => $street_registry_entries,
            'generated_map_rebuild_candidates' => $generated_map_rebuild_candidates,
            'generated_map_rebuild_from' => $generated_map_rebuild_from,
            'generated_map_rebuild_to' => $generated_map_rebuild_to,
        ], 'Einstellungen: Karten');

        echo FEU_Einsatz_Template_Helpers::render_guarded('templates/admin/settings/partials/tab-strassenregister.php', [
            'active_tab' => $active_tab,
            'db' => $this->db,
            'street_registry_entries' => $street_registry_entries,
            'street_suggestion_records' => $street_suggestion_records,
        ], 'Einstellungen: Strassenregister');

        echo FEU_Einsatz_Template_Helpers::render_guarded('templates/admin/settings/partials/tab-manifest.php', [
            'active_tab' => $active_tab,
            'manifest_path' => $manifest_path,
            'manifest_contents' => $manifest_contents,
            'manifest_display_contents' => $manifest_display_contents,
            'manifest_example_path' => $manifest_example_path,
            'manifest_example_contents' => $manifest_example_contents,
            'release_notes_path' => $release_notes_path,
            'release_notes_entries' => $release_notes_entries,
            'remote_manifest_url' => $remote_manifest_url,
            'remote_manifest_payload' => $remote_manifest_payload,
            'functions_reference_path' => $functions_reference_path,
            'update_guide_path' => $update_guide_path,
            'docs_directory' => $docs_directory,
            'templates_directory' => $templates_directory,
            'default_card_variant' => $default_card_variant,
            'card_variants' => $card_variants,
            'participant_fallback_function' => $participant_fallback_function,
        ], 'Einstellungen: Manifest');

        echo FEU_Einsatz_Template_Helpers::render_guarded('templates/admin/settings/partials/tab-shortcodes.php', [
            'active_tab' => $active_tab,
        ], 'Einstellungen: Shortcodes');
        ?>
        </div>

        <div class="feu-admin-form-footer">
            <p class="submit">
                <input type="submit"
                       name="submit"
                       id="submit"
                       class="button button-primary"
                       value="<?php _e('Einstellungen speichern', 'feuer-einsatzberichte'); ?>" />
            </p>
        </div>
        </form>
    </div>
</div>

<?php
echo FEU_Einsatz_Template_Helpers::render('templates/admin/settings/partials/organization-modal.php');
?>

<script>
jQuery(document).ready(function($) {
    var allowedTabs = ['allgemein', 'zugriff', 'medien', 'sozial', 'funktionen', 'organisationen', 'kategorien', 'karten', 'strassenregister', 'manifest', 'shortcodes'];
    var legacyTabAliases = {
        einzelbeitrag: 'allgemein',
        uebersicht: 'allgemein',
        sicherheit: 'allgemein'
    };
    var $settingsForm = $('#feu-einsatz-settings-form');
    var dirtyFieldKeys = {};
    var suppressBeforeUnload = false;
    var settingsFormSubmitting = false;
    var streetRegistryLoaded = $('#tab-strassenregister .feu-einsatz-street-registry-table tbody tr').length > 0;
    var streetRegistryLoading = false;
    var texts = {
        unsavedConfirm: <?php echo wp_json_encode(__('Es gibt ungespeicherte Änderungen in den Einstellungen. Wenn Sie jetzt fortfahren, gehen diese Änderungen verloren. Wirklich fortfahren?', 'feuer-einsatzberichte')); ?>,
        unsavedLeave: <?php echo wp_json_encode(__('Es gibt ungespeicherte Änderungen in den Einstellungen.', 'feuer-einsatzberichte')); ?>,
        functionPlaceholder: <?php echo wp_json_encode(__('Funktion eingeben', 'feuer-einsatzberichte')); ?>,
        remove: <?php echo wp_json_encode(__('Entfernen', 'feuer-einsatzberichte')); ?>,
        moveUp: <?php echo wp_json_encode(__('Nach oben verschieben', 'feuer-einsatzberichte')); ?>,
        moveDown: <?php echo wp_json_encode(__('Nach unten verschieben', 'feuer-einsatzberichte')); ?>,
        functionRequired: <?php echo wp_json_encode(__('Mindestens eine Funktion muss vorhanden sein.', 'feuer-einsatzberichte')); ?>,
        organizationNameRequired: <?php echo wp_json_encode(__('Bitte geben Sie einen Organisationsnamen ein.', 'feuer-einsatzberichte')); ?>,
        saveInProgress: <?php echo wp_json_encode(__('Wird gespeichert...', 'feuer-einsatzberichte')); ?>,
        archiveInProgress: <?php echo wp_json_encode(__('Status wird aktualisiert...', 'feuer-einsatzberichte')); ?>,
        archiveOrganization: <?php echo wp_json_encode(__('Archivieren', 'feuer-einsatzberichte')); ?>,
        activateOrganization: <?php echo wp_json_encode(__('Aktivieren', 'feuer-einsatzberichte')); ?>,
        addOrganization: <?php echo wp_json_encode(__('Hinzufügen', 'feuer-einsatzberichte')); ?>,
        editOrganization: <?php echo wp_json_encode(__('Änderungen speichern', 'feuer-einsatzberichte')); ?>,
        deleteInProgress: <?php echo wp_json_encode(__('Wird gelöscht...', 'feuer-einsatzberichte')); ?>,
        deleteOrganization: <?php echo wp_json_encode(__('Löschen', 'feuer-einsatzberichte')); ?>,
        saveError: <?php echo wp_json_encode(__('Fehler beim Speichern:', 'feuer-einsatzberichte')); ?>,
        archiveError: <?php echo wp_json_encode(__('Status konnte nicht aktualisiert werden:', 'feuer-einsatzberichte')); ?>,
        deleteError: <?php echo wp_json_encode(__('Fehler beim Löschen:', 'feuer-einsatzberichte')); ?>,
        connectionError: <?php echo wp_json_encode(__('Verbindungsfehler. Bitte versuchen Sie es erneut.', 'feuer-einsatzberichte')); ?>,
        archiveSuccess: <?php echo wp_json_encode(__('Organisation wurde archiviert.', 'feuer-einsatzberichte')); ?>,
        activateSuccess: <?php echo wp_json_encode(__('Organisation wurde aktiviert.', 'feuer-einsatzberichte')); ?>,
        archiveConfirm: <?php echo wp_json_encode(__('Organisation archivieren? Sie bleibt in bestehenden Einsatzberichten erhalten, kann aber nicht mehr neu ausgewählt werden.', 'feuer-einsatzberichte')); ?>,
        activateConfirm: <?php echo wp_json_encode(__('Organisation wieder aktivieren?', 'feuer-einsatzberichte')); ?>,
        addSuccess: <?php echo wp_json_encode(__('Organisation wurde hinzugefügt.', 'feuer-einsatzberichte')); ?>,
        updateSuccess: <?php echo wp_json_encode(__('Änderungen wurden gespeichert.', 'feuer-einsatzberichte')); ?>,
        deleteSuccess: <?php echo wp_json_encode(__('Organisation wurde gelöscht.', 'feuer-einsatzberichte')); ?>,
        deleteConfirm: <?php echo wp_json_encode(__('Sind Sie sicher, dass Sie diese Organisation löschen möchten?', 'feuer-einsatzberichte')); ?>,
        streetNameRequired: <?php echo wp_json_encode(__('Bitte zuerst eine Strasse eingeben.', 'feuer-einsatzberichte')); ?>,
        streetDeleteConfirm: <?php echo wp_json_encode(__('Diesen Strassen-Eintrag wirklich entfernen?', 'feuer-einsatzberichte')); ?>,
        streetAdd: <?php echo wp_json_encode(__('Strasse hinzufuegen', 'feuer-einsatzberichte')); ?>,
        streetSave: <?php echo wp_json_encode(__('Strasse speichern', 'feuer-einsatzberichte')); ?>,
        streetEditMode: <?php echo wp_json_encode(__('Strassen-Eintrag wird bearbeitet.', 'feuer-einsatzberichte')); ?>,
        streetSaved: <?php echo wp_json_encode(__('Strassen-Eintrag wurde gespeichert.', 'feuer-einsatzberichte')); ?>,
        streetDeleted: <?php echo wp_json_encode(__('Strassen-Eintrag wurde entfernt.', 'feuer-einsatzberichte')); ?>,
        streetInUse: <?php echo wp_json_encode(__('Diese Strasse wird bereits in Einsatzberichten verwendet und kann nicht geloescht werden.', 'feuer-einsatzberichte')); ?>,
        organizationOrderSaved: <?php echo wp_json_encode(__('Reihenfolge der Organisationen wurde gespeichert.', 'feuer-einsatzberichte')); ?>
    };

    $('.feu-einsatz-delete-organization').each(function() {
        var $button = $(this);
        var isArchived = Number($button.data('archived') || 0) === 1;
        $button.text(isArchived ? texts.activateOrganization : texts.archiveOrganization);
    });

    function getFieldKey($field) {
        return $field.attr('name') || $field.attr('id') || '';
    }

    function markSettingsDirty(key) {
        dirtyFieldKeys[key || '__settings__'] = true;
    }

    function hasUnsavedSettingsChanges(ignoreKeys) {
        var ignored = {};
        var keys = Object.keys(dirtyFieldKeys);

        (ignoreKeys || []).forEach(function(key) {
            ignored[key] = true;
        });

        for (var i = 0; i < keys.length; i++) {
            if (!ignored[keys[i]]) {
                return true;
            }
        }

        return false;
    }

    function confirmPendingSettingsChanges(ignoreKeys) {
        if (!hasUnsavedSettingsChanges(ignoreKeys)) {
            return true;
        }

        return window.confirm(texts.unsavedConfirm);
    }

    function reloadCurrentTab(tab) {
        suppressBeforeUnload = true;
        window.location.hash = tab;
        location.reload();
    }

    function isValidTab(tab) {
        tab = legacyTabAliases[tab] || tab;
        return allowedTabs.indexOf(tab) !== -1;
    }

    function activateTab(tab, updateHash) {
        tab = legacyTabAliases[tab] || tab;
        var safeTab = isValidTab(tab) ? tab : 'allgemein';

        $('.nav-tab').removeClass('nav-tab-active');
        $('.nav-tab[data-tab="' + safeTab + '"]').addClass('nav-tab-active');
        $('.feu-einsatz-tab-content')
            .hide()
            .addClass('feu-einsatz-tab-content-hidden');
        $('#tab-' + safeTab)
            .removeClass('feu-einsatz-tab-content-hidden')
            .show();
        $('#feu_einsatz_active_tab').val(safeTab);

        if (updateHash) {
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', window.location.pathname + window.location.search + '#' + safeTab);
            } else {
                window.location.hash = safeTab;
            }
        }

        document.dispatchEvent(new CustomEvent('feu:einsatz-settings-tab-activated', {
            detail: {
                tab: safeTab
            }
        }));

        if (safeTab === 'strassenregister') {
            loadStreetRegistryEntries();
        }
    }

    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        activateTab($(this).data('tab'), true);
    });

    var initialTab = window.location.hash ? window.location.hash.replace('#', '') : '';
    initialTab = legacyTabAliases[initialTab] || initialTab;

    if (!isValidTab(initialTab)) {
        initialTab = $('#feu_einsatz_active_tab').val() || <?php echo wp_json_encode($active_tab); ?>;
    }

    activateTab(initialTab, false);

    $settingsForm.on('input change', 'input:not([type="hidden"]), textarea, select', function() {
        markSettingsDirty(getFieldKey($(this)));
    });

    $settingsForm.on('submit', function() {
        settingsFormSubmitting = true;
        suppressBeforeUnload = true;
        dirtyFieldKeys = {};
        $(this).removeClass('is-saved').addClass('is-saving');
    });

    $('.feu-admin-page .notice').each(function() {
        $(this).addClass('feu-admin-notice-enter');
    });

    $(window).on('beforeunload', function() {
        if (!suppressBeforeUnload && !settingsFormSubmitting && hasUnsavedSettingsChanges()) {
            return texts.unsavedLeave;
        }

        return undefined;
    });

    function buildFunctionActionButtons() {
        return '<div class="feu-einsatz-function-actions">' +
            '<button type="button" class="button feu-einsatz-move-function-up" aria-label="' + texts.moveUp + '">' +
            '<span class="feu-einsatz-button-icon" aria-hidden="true">↑</span>' +
            '</button>' +
            '<button type="button" class="button feu-einsatz-move-function-down" aria-label="' + texts.moveDown + '">' +
            '<span class="feu-einsatz-button-icon" aria-hidden="true">↓</span>' +
            '</button>' +
            '<button type="button" class="button feu-einsatz-remove-function">' +
            '<span class="feu-einsatz-button-icon" aria-hidden="true">×</span> ' + texts.remove +
            '</button>' +
            '</div>';
    }

    function resetStreetRegistryForm() {
        $('#feu-einsatz-street-registry-id').val('0');
        $('#feu-einsatz-street-registry-street').val('');
        $('#feu-einsatz-street-registry-postcode').val('');
        $('#feu-einsatz-street-registry-city').val('');
        $('#feu-einsatz-add-street-registry-entry').text(texts.streetAdd);
        delete dirtyFieldKeys['feu-einsatz-street-registry-street'];
        delete dirtyFieldKeys['feu-einsatz-street-registry-postcode'];
        delete dirtyFieldKeys['feu-einsatz-street-registry-city'];
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getStreetRegistrySearchValue(entry) {
        return [entry.street, entry.postcode, entry.city]
            .map(function(value) {
                return String(value || '').trim().toLowerCase();
            })
            .filter(Boolean)
            .join(' ');
    }

    function getStreetRegistryMeta(entry) {
        return [entry.postcode, entry.city]
            .map(function(value) {
                return String(value || '').trim();
            })
            .filter(Boolean)
            .join(' / ');
    }

    function ensureManualStreetRegistryTable() {
        var $table = $('#tab-strassenregister .feu-einsatz-street-registry-table:not(.feu-einsatz-street-registry-table--known)').first();
        var tableHtml;

        if ($table.length) {
            return $table;
        }

        tableHtml = '' +
            '<table class="wp-list-table widefat striped feu-einsatz-street-registry-table">' +
                '<thead><tr>' +
                    '<th>Strasse / PLZ / Ort</th>' +
                    '<th>Einsaetze</th>' +
                    '<th>Aktion</th>' +
                '</tr></thead>' +
                '<tbody></tbody>' +
            '</table>';

        $('.feu-einsatz-street-registry-empty').first().replaceWith(tableHtml);
        return $('#tab-strassenregister .feu-einsatz-street-registry-table:not(.feu-einsatz-street-registry-table--known)').first();
    }

    function ensureKnownStreetRegistryTable() {
        var $table = $('#tab-strassenregister .feu-einsatz-street-registry-table--known').first();
        var tableHtml;

        if ($table.length) {
            return $table;
        }

        tableHtml = '' +
            '<table class="wp-list-table widefat striped feu-einsatz-street-registry-table feu-einsatz-street-registry-table--known">' +
                '<thead><tr>' +
                    '<th>Strasse / PLZ / Ort</th>' +
                    '<th>Verwendung</th>' +
                    '<th>Aktion</th>' +
                '</tr></thead>' +
                '<tbody></tbody>' +
            '</table>';

        $('#tab-strassenregister .feu-einsatz-street-registry-section-head--secondary').next('.feu-einsatz-street-registry-empty').replaceWith(tableHtml);
        return $('#tab-strassenregister .feu-einsatz-street-registry-table--known').first();
    }

    function buildStreetRegistryRow(entry) {
        var id = Number(entry && entry.id ? entry.id : 0);
        var street = String(entry && entry.street ? entry.street : '');
        var postcode = String(entry && entry.postcode ? entry.postcode : '');
        var city = String(entry && entry.city ? entry.city : '');
        var usageCount = Number(entry && entry.usage_count ? entry.usage_count : 0);
        var reportUrl = String(entry && entry.report_url ? entry.report_url : '#');
        var canDelete = !entry || entry.can_delete !== false;
        var meta = getStreetRegistryMeta({ postcode: postcode, city: city });

        return '' +
            '<tr data-street-registry-id="' + id + '" data-search="' + escapeHtml(getStreetRegistrySearchValue({ street: street, postcode: postcode, city: city })) + '">' +
                '<td>' +
                    '<strong>' + escapeHtml(street) + '</strong>' +
                    '<span class="feu-einsatz-street-registry-row-meta">' + escapeHtml(meta) + '</span>' +
                '</td>' +
                '<td>' + Math.max(0, usageCount) + '</td>' +
                '<td class="feu-einsatz-table-actions">' +
                    '<button type="button" class="button button-small feu-einsatz-edit-street-registry-entry" data-id="' + id + '" data-street="' + escapeHtml(street) + '" data-postcode="' + escapeHtml(postcode) + '" data-city="' + escapeHtml(city) + '">' +
                        'Bearbeiten' +
                    '</button>' +
                    '<a class="button button-small" href="' + escapeHtml(reportUrl) + '">Einsaetze ansehen</a>' +
                    '<button type="button" class="button button-small feu-einsatz-delete-street-registry-entry" data-id="' + id + '"' + (canDelete ? '' : ' disabled="disabled"') + '>' +
                        escapeHtml(canDelete ? texts.remove : 'In Benutzung') +
                    '</button>' +
                '</td>' +
            '</tr>';
    }

    function buildKnownStreetRegistryRow(entry) {
        var street = String(entry && entry.street ? entry.street : '');
        var postcode = String(entry && entry.plz ? entry.plz : (entry && entry.postcode ? entry.postcode : ''));
        var city = String(entry && entry.city ? entry.city : '');
        var usageCount = Number(entry && entry.usage_count ? entry.usage_count : 0);
        var reportUrl = String(entry && entry.report_url ? entry.report_url : '');
        var meta = getStreetRegistryMeta({ postcode: postcode, city: city });

        if (!reportUrl) {
            reportUrl = 'edit.php?post_type=post&feu_einsatz_filter=1'
                + '&feu_einsatz_street=' + encodeURIComponent(street)
                + (postcode ? '&feu_einsatz_postcode=' + encodeURIComponent(postcode) : '')
                + (city ? '&feu_einsatz_city=' + encodeURIComponent(city) : '');
        }

        return '' +
            '<tr data-search="' + escapeHtml(getStreetRegistrySearchValue({ street: street, postcode: postcode, city: city })) + '">' +
                '<td>' +
                    '<strong>' + escapeHtml(street) + '</strong>' +
                    '<span class="feu-einsatz-street-registry-row-meta">' + escapeHtml(meta) + '</span>' +
                '</td>' +
                '<td>' + Math.max(0, usageCount) + '</td>' +
                '<td class="feu-einsatz-table-actions">' +
                    '<a class="button button-small" href="' + escapeHtml(reportUrl) + '">Einsaetze ansehen</a>' +
                '</td>' +
            '</tr>';
    }

    function renderStreetRegistryTables(registryEntries, knownEntries) {
        var $manualTable = ensureManualStreetRegistryTable();
        var $knownTable = ensureKnownStreetRegistryTable();
        registryEntries = Array.isArray(registryEntries) ? registryEntries : [];
        knownEntries = Array.isArray(knownEntries) ? knownEntries : [];

        if (registryEntries.length) {
            $manualTable.find('tbody').html(registryEntries.map(buildStreetRegistryRow).join(''));
        } else {
            $manualTable.replaceWith('<p class="description feu-einsatz-street-registry-empty">Noch keine manuell gepflegten Strassen vorhanden.</p>');
        }

        if (knownEntries.length) {
            $knownTable.find('tbody').html(knownEntries.map(buildKnownStreetRegistryRow).join(''));
        } else {
            $knownTable.replaceWith('<p class="description feu-einsatz-street-registry-empty">Aktuell sind noch keine bekannten Strassen verfuegbar.</p>');
        }

        $('#feu-einsatz-street-registry-count').text(String(registryEntries.length));
        $('#feu-einsatz-known-street-count').text(String(knownEntries.length));
        applyStreetRegistrySearch();
    }

    function loadStreetRegistryEntries(force) {
        if ((streetRegistryLoaded && !force) || streetRegistryLoading || !$('#tab-strassenregister').length) {
            return;
        }

        streetRegistryLoading = true;
        $('#tab-strassenregister .feu-einsatz-street-registry-empty').first().text('Strassenregister wird geladen...');

        $.post(feu_einsatz_ajax.ajax_url, {
            action: 'feu_einsatz_get_street_registry',
            nonce: feu_einsatz_ajax.nonce,
            limit: 1000
        }).done(function(response) {
            if (response && response.success && response.data) {
                renderStreetRegistryTables(response.data.registry_entries || [], response.data.known_entries || []);
                streetRegistryLoaded = true;
                return;
            }

            showNotice('error', texts.connectionError);
        }).fail(function() {
            showNotice('error', texts.connectionError);
        }).always(function() {
            streetRegistryLoading = false;
        });
    }

    function upsertStreetRegistryRow(entry) {
        var id = Number(entry && entry.id ? entry.id : 0);
        var $table = ensureManualStreetRegistryTable();
        var $existing = $table.find('tbody tr[data-street-registry-id="' + id + '"]');
        var rowHtml = buildStreetRegistryRow(entry);

        if ($existing.length) {
            $existing.replaceWith(rowHtml);
        } else {
            $table.find('tbody').prepend(rowHtml);
        }

        applyStreetRegistrySearch();
    }

    function removeStreetRegistryRow(id) {
        var $table = $('#tab-strassenregister .feu-einsatz-street-registry-table:not(.feu-einsatz-street-registry-table--known)').first();

        $table.find('tbody tr[data-street-registry-id="' + Number(id) + '"]').remove();

        if (!$table.find('tbody tr').length) {
            $table.replaceWith('<p class="description feu-einsatz-street-registry-empty">Noch keine manuell gepflegten Strassen vorhanden.</p>');
        }

        applyStreetRegistrySearch();
    }

    function openStreetRegistryModal(entry) {
        $('#feu-einsatz-edit-street-registry-id').val(String(entry.id || 0));
        $('#feu-einsatz-edit-street-registry-street').val(String(entry.street || ''));
        $('#feu-einsatz-edit-street-registry-postcode').val(String(entry.postcode || ''));
        $('#feu-einsatz-edit-street-registry-city').val(String(entry.city || ''));
        $('#feu-einsatz-edit-street-registry-modal').fadeIn(160);
        window.setTimeout(function() {
            $('#feu-einsatz-edit-street-registry-street').trigger('focus');
        }, 180);
    }

    function closeStreetRegistryModal() {
        $('#feu-einsatz-edit-street-registry-modal').fadeOut(160);
    }

    function applyStreetRegistrySearch() {
        var query = String($('#feu-einsatz-street-registry-search').val() || '').trim().toLowerCase();

        $('.feu-einsatz-street-registry-table tbody tr').each(function() {
            var haystack = String($(this).data('search') || '');
            $(this).toggle(!query || haystack.indexOf(query) !== -1);
        });
    }

    function collectOrganizationOrder() {
        var ids = [];
        $('.feu-einsatz-organizations-table tbody tr[data-organization-id]').each(function() {
            ids.push(Number($(this).data('organizationId') || 0));
        });
        return ids.filter(function(id) {
            return id > 0;
        });
    }

    function syncOrganizationMoveButtons() {
        var $rows = $('.feu-einsatz-organizations-table tbody tr[data-organization-id]');
        $rows.each(function(index) {
            $(this).find('.feu-einsatz-move-organization-up').prop('disabled', index === 0);
            $(this).find('.feu-einsatz-move-organization-down').prop('disabled', index === ($rows.length - 1));
        });
    }

    function saveOrganizationOrder() {
        var organizationIds = collectOrganizationOrder();

        if (!organizationIds.length) {
            return;
        }

        $.post(feu_einsatz_ajax.ajax_url, {
            action: 'feu_einsatz_sort_organizations',
            nonce: feu_einsatz_ajax.nonce,
            organization_ids: organizationIds
        }).done(function(response) {
            if (response && response.success) {
                showNotice('success', texts.organizationOrderSaved);
            }
        });
    }

    function syncFunctionMoveButtons() {
        var $items = $('#feu-einsatz-functions-list .feu-einsatz-function-item');

        $items.each(function(index) {
            $(this).find('.feu-einsatz-move-function-up').prop('disabled', index === 0);
            $(this).find('.feu-einsatz-move-function-down').prop('disabled', index === ($items.length - 1));
        });
    }

    $('#feu-einsatz-add-function').on('click', function() {
        var template = '<div class="feu-einsatz-function-item">' +
            '<input type="text" name="feu_einsatz_functions[]" value="" class="regular-text" placeholder="' + texts.functionPlaceholder + '" /> ' +
            buildFunctionActionButtons() +
            '</div>';

        $('#feu-einsatz-functions-list').append(template);
        markSettingsDirty('__functions_structure__');
        syncFunctionMoveButtons();
    });

    $(document).on('click', '.feu-einsatz-remove-function', function() {
        if ($('#feu-einsatz-functions-list .feu-einsatz-function-item').length > 1) {
            $(this).closest('.feu-einsatz-function-item').fadeOut(300, function() {
                $(this).remove();
                syncFunctionMoveButtons();
            });
            markSettingsDirty('__functions_structure__');
        } else {
            alert(texts.functionRequired);
        }
    });

    $(document).on('click', '.feu-einsatz-move-function-up', function() {
        var $item = $(this).closest('.feu-einsatz-function-item');
        var $previous = $item.prev('.feu-einsatz-function-item');

        if (!$previous.length) {
            return;
        }

        $item.insertBefore($previous);
        markSettingsDirty('__functions_structure__');
        syncFunctionMoveButtons();
    });

    $(document).on('click', '.feu-einsatz-move-function-down', function() {
        var $item = $(this).closest('.feu-einsatz-function-item');
        var $next = $item.next('.feu-einsatz-function-item');

        if (!$next.length) {
            return;
        }

        $item.insertAfter($next);
        markSettingsDirty('__functions_structure__');
        syncFunctionMoveButtons();
    });

    $('#feu-einsatz-add-street-registry-entry').on('click', function() {
        var id = 0;
        var street = $('#feu-einsatz-street-registry-street').val().trim();
        var postcode = $('#feu-einsatz-street-registry-postcode').val().trim();
        var city = $('#feu-einsatz-street-registry-city').val().trim();
        var $button = $(this);

        if (!street) {
            alert(texts.streetNameRequired);
            return;
        }

        $button.prop('disabled', true).text(texts.saveInProgress);

        $.post(feu_einsatz_ajax.ajax_url, {
            action: 'feu_einsatz_save_street_registry_entry',
            nonce: feu_einsatz_ajax.nonce,
            id: id,
            street: street,
            postcode: postcode,
            city: city
        }).done(function(response) {
            if (response && response.success) {
                showNotice('success', texts.streetSaved);
                if (response.data && response.data.entry) {
                    upsertStreetRegistryRow(response.data.entry);
                    loadStreetRegistryEntries(true);
                }
                resetStreetRegistryForm();
                return;
            }

            alert(texts.saveError + ' ' + ((response && response.data && response.data.message) ? response.data.message : ''));
            $button.prop('disabled', false).text(texts.streetAdd);
        }).fail(function(xhr) {
            var message = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message
                : texts.connectionError;
            alert(message);
        }).always(function() {
            $button.prop('disabled', false).text(texts.streetAdd);
        });
    });

    $(document).on('click', '.feu-einsatz-edit-street-registry-entry', function() {
        openStreetRegistryModal({
            id: Number($(this).data('id') || 0),
            street: String($(this).data('street') || ''),
            postcode: String($(this).data('postcode') || ''),
            city: String($(this).data('city') || '')
        });
    });

    $('#feu-einsatz-save-street-registry-modal').on('click', function() {
        var id = Number($('#feu-einsatz-edit-street-registry-id').val() || 0);
        var street = $('#feu-einsatz-edit-street-registry-street').val().trim();
        var postcode = $('#feu-einsatz-edit-street-registry-postcode').val().trim();
        var city = $('#feu-einsatz-edit-street-registry-city').val().trim();
        var $button = $('#feu-einsatz-save-street-registry-modal');

        if (!id || !street) {
            alert(texts.streetNameRequired);
            return;
        }

        $button.prop('disabled', true).text(texts.saveInProgress);

        $.post(feu_einsatz_ajax.ajax_url, {
            action: 'feu_einsatz_save_street_registry_entry',
            nonce: feu_einsatz_ajax.nonce,
            id: id,
            street: street,
            postcode: postcode,
            city: city
        }).done(function(response) {
            if (response && response.success && response.data && response.data.entry) {
                upsertStreetRegistryRow(response.data.entry);
                loadStreetRegistryEntries(true);
                showNotice('success', texts.streetSaved);
                closeStreetRegistryModal();
                return;
            }

            alert(texts.saveError + ' ' + ((response && response.data && response.data.message) ? response.data.message : ''));
        }).fail(function(xhr) {
            var message = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message
                : texts.connectionError;
            alert(message);
        }).always(function() {
            $button.prop('disabled', false).text(texts.streetSave);
        });
    });

    $('.feu-einsatz-street-registry-modal-close').on('click', function() {
        closeStreetRegistryModal();
    });

    $(document).on('click', '.feu-einsatz-delete-street-registry-entry', function() {
        var id = Number($(this).data('id') || 0);

        if (!id || !confirm(texts.streetDeleteConfirm)) {
            return;
        }

        $.post(feu_einsatz_ajax.ajax_url, {
            action: 'feu_einsatz_delete_street_registry_entry',
            nonce: feu_einsatz_ajax.nonce,
            id: id
        }).done(function(response) {
            if (response && response.success) {
                showNotice('success', texts.streetDeleted);
                removeStreetRegistryRow(id);
                loadStreetRegistryEntries(true);
                return;
            }

            alert(texts.deleteError + ' ' + ((response && response.data && response.data.message) ? response.data.message : ''));
        }).fail(function(xhr) {
            var message = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message
                : texts.connectionError;
            alert(message);
        });
    });

    $('#feu-einsatz-street-registry-search').on('input', applyStreetRegistrySearch);

    $('#feu-einsatz-refresh-update-check').on('click', function() {
        var $form = $('<form method="post"></form>');
        var targetUrl = window.location.href.split('#')[0];

        if (!confirmPendingSettingsChanges()) {
            return;
        }

        suppressBeforeUnload = true;
        $form.attr('action', targetUrl);
        $form.append($('<input type="hidden" name="feu_einsatz_settings_nonce" />').val($('#feu_einsatz_settings_nonce').val() || ''));
        $form.append($('<input type="hidden" name="feu_einsatz_active_tab" />').val($('#feu_einsatz_active_tab').val() || 'manifest'));
        $form.append($('<input type="hidden" name="feu_einsatz_refresh_update_check" />').val('1'));
        $('body').append($form);
        $form.trigger('submit');
    });

    $('#feu-einsatz-add-organization').on('click', function() {
        var name = $('#feu-einsatz-new-organization').val().trim();
        var postLink = $('#feu-einsatz-new-organization-link').val().trim();
        var color = $('#feu-einsatz-new-organization-color').val() || '#0a4b78';
        var $button = $(this);

        if (!name) {
            alert(texts.organizationNameRequired);
            return;
        }

        if (!confirmPendingSettingsChanges(['feu-einsatz-new-organization', 'feu-einsatz-new-organization-link', 'feu-einsatz-new-organization-color'])) {
            return;
        }

        $button.prop('disabled', true).text(texts.saveInProgress);

        $.ajax({
            url: feu_einsatz_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'feu_einsatz_save_organization',
                nonce: feu_einsatz_ajax.nonce,
                name: name,
                color: color,
                post_link: postLink
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', texts.addSuccess);
                    $('#feu-einsatz-new-organization').val('');
                    $('#feu-einsatz-new-organization-link').val('');
                    $('#feu-einsatz-new-organization-color').val('#0a4b78');
                    setTimeout(function() {
                        reloadCurrentTab('organisationen');
                    }, 1000);
                } else {
                    alert(texts.saveError + ' ' + response.data.message);
                    $button.prop('disabled', false).text(texts.addOrganization);
                }
            },
            error: function() {
                alert(texts.connectionError);
                $button.prop('disabled', false).text(texts.addOrganization);
            }
        });
    });

    $('.feu-einsatz-edit-organization').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var name = $(this).data('name');
        var color = $(this).data('color') || '#0a4b78';
        var postLink = $(this).data('postLink') || '';

        $('#edit_org_id').val(id);
        $('#edit_org_name').val(name);
        $('#edit_org_color').val(color);
        $('#edit_org_post_link').val(postLink);

        $('#feu-einsatz-edit-organization-modal').fadeIn(300);
    });

    $('#feu-einsatz-edit-organization-form').on('submit', function(e) {
        e.preventDefault();

        var id = $('#edit_org_id').val();
        var name = $('#edit_org_name').val().trim();
        var color = $('#edit_org_color').val() || '#0a4b78';
        var postLink = $('#edit_org_post_link').val().trim();
        var $button = $(this).find('button[type="submit"]');

        if (!name) {
            alert(texts.organizationNameRequired);
            return;
        }

        if (!confirmPendingSettingsChanges()) {
            return;
        }

        $button.prop('disabled', true).text(texts.saveInProgress);

        $.ajax({
            url: feu_einsatz_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'feu_einsatz_save_organization',
                nonce: feu_einsatz_ajax.nonce,
                id: id,
                name: name,
                color: color,
                post_link: postLink
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', texts.updateSuccess);
                    $('#feu-einsatz-edit-organization-modal').fadeOut(300);
                    setTimeout(function() {
                        reloadCurrentTab('organisationen');
                    }, 1000);
                } else {
                    alert(texts.saveError + ' ' + response.data.message);
                    $button.prop('disabled', false).text(texts.editOrganization);
                }
            },
            error: function() {
                alert(texts.connectionError);
                $button.prop('disabled', false).text(texts.editOrganization);
            }
        });
    });

    $('.feu-einsatz-delete-organization').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var id = Number($button.data('id') || 0);
        var currentlyArchived = Number($button.data('archived') || 0) === 1;
        var nextArchived = currentlyArchived ? 0 : 1;
        var question = currentlyArchived ? texts.activateConfirm : texts.archiveConfirm;

        if (!id || !confirm(question)) {
            return;
        }

        if (!confirmPendingSettingsChanges()) {
            return;
        }

        $button.prop('disabled', true).text(texts.archiveInProgress);

        $.ajax({
            url: feu_einsatz_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'feu_einsatz_toggle_organization_archive',
                nonce: feu_einsatz_ajax.nonce,
                id: id,
                archived: nextArchived
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', nextArchived ? texts.archiveSuccess : texts.activateSuccess);
                    setTimeout(function() {
                        reloadCurrentTab('organisationen');
                    }, 1000);
                } else {
                    alert(texts.archiveError + ' ' + response.data.message);
                    $button.prop('disabled', false).text(currentlyArchived ? texts.activateOrganization : texts.archiveOrganization);
                }
            },
            error: function() {
                alert(texts.connectionError);
                $button.prop('disabled', false).text(currentlyArchived ? texts.activateOrganization : texts.archiveOrganization);
            }
        });
    });

    $(document).on('click', '.feu-einsatz-move-organization-up', function() {
        var $row = $(this).closest('tr');
        var $previous = $row.prev('tr[data-organization-id]');

        if (!$previous.length) {
            return;
        }

        $row.insertBefore($previous);
        syncOrganizationMoveButtons();
        saveOrganizationOrder();
    });

    $(document).on('click', '.feu-einsatz-move-organization-down', function() {
        var $row = $(this).closest('tr');
        var $next = $row.next('tr[data-organization-id]');

        if (!$next.length) {
            return;
        }

        $row.insertAfter($next);
        syncOrganizationMoveButtons();
        saveOrganizationOrder();
    });

    $('.feu-einsatz-select-all').on('click', function() {
        $('.feu-einsatz-categories-list input[type="checkbox"]').prop('checked', true);
        markSettingsDirty('__categories_bulk__');
    });

    $('.feu-einsatz-deselect-all').on('click', function() {
        $('.feu-einsatz-categories-list input[type="checkbox"]').prop('checked', false);
        markSettingsDirty('__categories_bulk__');
    });

    $('.feu-einsatz-modal-close').on('click', function() {
        $('#feu-einsatz-edit-organization-modal').fadeOut(300);
    });

    $(window).on('click', function(e) {
        if ($(e.target).is('#feu-einsatz-edit-organization-modal')) {
            $('#feu-einsatz-edit-organization-modal').fadeOut(300);
        }

        if ($(e.target).is('#feu-einsatz-edit-street-registry-modal')) {
            closeStreetRegistryModal();
        }
    });

    $(document).on('keyup', function(e) {
        if (e.key === 'Escape') {
            $('#feu-einsatz-edit-organization-modal').fadeOut(300);
            closeStreetRegistryModal();
        }
    });

    syncFunctionMoveButtons();
    syncOrganizationMoveButtons();
    resetStreetRegistryForm();

    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p></p></div>');

        notice.find('p').text(message || '');

        $('.wrap h1').after(notice);

        setTimeout(function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);

        notice.find('.notice-dismiss').on('click', function() {
            notice.remove();
        });
    }
});
</script>
