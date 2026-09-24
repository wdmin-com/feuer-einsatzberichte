<?php
if (!defined('ABSPATH')) {
    exit;
}

class FEU_Einsatz_Template_Helpers {

    /**
     * Increment when the public report context gains data that must not be
     * served from an older, otherwise valid transient.
     */
    const SINGLE_CONTEXT_CACHE_VERSION = '11';

    /**
     * Marks geometry that was resolved with the exact, local Overpass query.
     * A Nominatim result is also marked explicitly once its real street ways
     * have been merged, so a temporarily unavailable Overpass mirror does not
     * make an already complete route look stale again.
     */
    const FOCUSED_GEOMETRY_SOURCE_META = '_feu_einsatz_street_geometry_source';
    const ADDRESS_COORDINATES_META = '_feu_einsatz_coordinates_address_key';
    const MAP_HIGHLIGHT_OVERRIDE_META = '_feu_einsatz_map_highlight_override';
    const MAP_HIGHLIGHT_LENGTH_META = '_feu_einsatz_map_highlight_length_meters';
    const MAP_HIGHLIGHT_RADIUS_META = '_feu_einsatz_map_highlight_radius_meters';
    const MAP_LOCATION_MODE_META = '_feu_einsatz_map_location_mode';
    const MAP_EXTRA_STREETS_META = '_feu_einsatz_map_extra_streets';
    const MAP_AREA_GEOJSON_META = '_feu_einsatz_map_area_geojson';
    const MAP_PUBLIC_PRECISION_META = '_feu_einsatz_map_public_precision';
    const MAP_HISTORY_META = '_feu_einsatz_map_history';
    const FOCUSED_GEOMETRY_SOURCE = 'overpass-focused-v7';
    const NOMINATIM_GEOMETRY_SOURCE = 'nominatim-street-v7';
    const NOMINATIM_STREET_GEOMETRY_MAX_PAGES = 4;

    const NOMINATIM_SEARCH_URL = 'https://nominatim.openstreetmap.org/search';
    const OVERPASS_API_URL = 'https://overpass-api.de/api/interpreter';
    const OVERPASS_FALLBACK_API_URLS = [
        'https://overpass.kumi.systems/api/interpreter',
        'https://overpass.private.coffee/api/interpreter',
    ];

    public static function render($template, $data = []) {
        $plugin_root = realpath(FEU_EINSATZ_PLUGIN_DIR);

        if (false === $plugin_root) {
            return '';
        }

        $plugin_root = trailingslashit(wp_normalize_path($plugin_root));
        $template_path = realpath(trailingslashit(FEU_EINSATZ_PLUGIN_DIR) . ltrim((string) $template, '/\\'));

        if (false === $template_path) {
            return '';
        }

        $template_path = wp_normalize_path($template_path);

        if (0 !== strpos($template_path, $plugin_root) || !file_exists($template_path)) {
            return '';
        }

        if (!empty($data) && is_array($data)) {
            extract($data, EXTR_SKIP);
        }

        ob_start();
        try {
            include $template_path;
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            throw $e;
        }

        return (string) ob_get_clean();
    }

    public static function render_guarded($template, $data = [], $label = '') {
        try {
            return self::render($template, $data);
        } catch (Throwable $e) {
            if (class_exists('FEU_Einsatz_Logger')) {
                FEU_Einsatz_Logger::log(
                    'template_render_failed',
                    'plugin_error',
                    0,
                    sprintf('Template render failed: %s', (string) $template),
                    [
                        'template' => (string) $template,
                        'label' => (string) $label,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => (int) $e->getLine(),
                    ]
                );
            }

            $title = '' !== (string) $label ? (string) $label : (string) $template;

            return '<div class="notice notice-error inline feu-einsatz-template-error">'
                . '<p><strong>' . esc_html__('Dieser Bereich konnte nicht geladen werden.', 'feuer-einsatzberichte') . '</strong></p>'
                . '<p>' . esc_html($title) . '</p>'
                . '<p><code>' . esc_html($e->getMessage()) . '</code></p>'
                . '</div>';
        }
    }

    public static function sanitize_overview_year($value) {
        $value = sanitize_text_field((string) $value);

        return preg_match('/^\d{4}$/', $value) ? $value : '';
    }

    public static function normalize_report_card_variant($value) {
        $variant = sanitize_key((string) $value);

        $variant_map = [
            '' => 'modern',
            'new' => 'modern',
            'modern' => 'modern',
            'aktuell' => 'modern',
            'side' => 'modern',
            'left' => 'modern',
            'image-left' => 'modern',
            'classic' => 'classic',
            'legacy' => 'classic',
            'old' => 'classic',
            'alt' => 'classic',
            'vorher' => 'classic',
            'top' => 'top',
            'vertical' => 'top',
            'image-top' => 'top',
            'bild-oben' => 'top',
            'minimal' => 'minimal',
            'text' => 'minimal',
            'no-image' => 'minimal',
            'ohne-bild' => 'minimal',
            'banner' => 'banner',
            'hero' => 'banner',
            'cover' => 'banner',
            'poster' => 'poster',
            'visual' => 'poster',
            'outline' => 'outline',
            'border' => 'outline',
            'framed' => 'outline',
            'magazine' => 'magazine',
            'editorial' => 'magazine',
            'mag' => 'magazine',
            'magazine-left' => 'magazine-left',
            'editorial-left' => 'magazine-left',
            'mag-left' => 'magazine-left',
        ];

        return isset($variant_map[$variant]) ? $variant_map[$variant] : 'modern';
    }

    public static function normalize_related_reports_display($value) {
        $display = sanitize_key((string) $value);

        if (in_array($display, ['disabled', 'cards', 'list'], true)) {
            return $display;
        }

        return 'cards';
    }

    public static function get_social_share_network_definitions() {
        return [
            'share' => [
                'label' => __('System teilen', 'feuer-einsatzberichte'),
                'description' => __('Verwendet die systemweite Teilen-Funktion des Geraets mit Bild, Text und Link, sofern der Browser dies unterstuetzt.', 'feuer-einsatzberichte'),
            ],
            'facebook' => [
                'label' => __('Facebook', 'feuer-einsatzberichte'),
                'description' => __('Oeffnet den Facebook-Sharer direkt mit dem Einsatzbericht.', 'feuer-einsatzberichte'),
            ],
            'instagram' => [
                'label' => __('Instagram', 'feuer-einsatzberichte'),
                'description' => __('Verwendet die native Teilen-Funktion des Geraets mit dem vorbereiteten Share-Bild.', 'feuer-einsatzberichte'),
            ],
            'x' => [
                'label' => __('X / Twitter', 'feuer-einsatzberichte'),
                'description' => __('Oeffnet einen Beitrag mit Share-Text und Link.', 'feuer-einsatzberichte'),
            ],
            'whatsapp' => [
                'label' => __('WhatsApp', 'feuer-einsatzberichte'),
                'description' => __('Erstellt eine WhatsApp-Nachricht mit dem Share-Text.', 'feuer-einsatzberichte'),
            ],
            'telegram' => [
                'label' => __('Telegram', 'feuer-einsatzberichte'),
                'description' => __('Oeffnet Telegram mit Link und Begleittext.', 'feuer-einsatzberichte'),
            ],
            'email' => [
                'label' => __('E-Mail', 'feuer-einsatzberichte'),
                'description' => __('Erstellt eine E-Mail mit Betreff, Text und Link.', 'feuer-einsatzberichte'),
            ],
        ];
    }
    public static function get_default_social_share_networks() {
        return ['share', 'facebook', 'instagram', 'whatsapp', 'telegram'];
    }

    public static function normalize_social_share_networks($values) {
        $allowed = array_keys(self::get_social_share_network_definitions());
        $normalized_values = array_map(
            static function ($value) {
                return sanitize_key((string) $value);
            },
            (array) $values
        );
        $normalized = array_values(array_intersect($allowed, $normalized_values));

        if (in_array('share', $normalized, true) && !in_array('instagram', $normalized, true)) {
            $normalized[] = 'instagram';
        }

        return array_values(array_unique($normalized));
    }

    public static function normalize_social_share_account_handle($value) {
        $value = trim((string) $value);

        if ('' === $value) {
            return '';
        }

        $value = ltrim($value, '@');
        $value = preg_replace('/[^A-Za-z0-9_]/', '', $value);

        if ('' === $value) {
            return '';
        }

        return '@' . $value;
    }

    public static function get_public_share_image_signature($post_id, $expires_at = 0) {
        $post_id = absint($post_id);
        $expires_at = absint($expires_at);

        if ($post_id < 1) {
            return '';
        }

        $salt = defined('AUTH_KEY') && '' !== AUTH_KEY ? AUTH_KEY : wp_salt('auth');
        $payload = 'feu_einsatz_share_image_public|' . $post_id;

        if ($expires_at > 0) {
            $payload .= '|' . $expires_at;
        }

        return hash_hmac('sha256', $payload, $salt);
    }

    public static function get_social_share_field_definitions() {
        return [
            'title' => __('Titel', 'feuer-einsatzberichte'),
            'date' => __('Datum', 'feuer-einsatzberichte'),
            'time' => __('Uhrzeit', 'feuer-einsatzberichte'),
            'number' => __('Nummer / Jahr', 'feuer-einsatzberichte'),
            'category' => __('Einsatzart', 'feuer-einsatzberichte'),
            'street' => __('Strasse', 'feuer-einsatzberichte'),
            'location' => __('Ort', 'feuer-einsatzberichte'),
            'description' => __('Beschreibung', 'feuer-einsatzberichte'),
            'url' => __('Link zum Beitrag', 'feuer-einsatzberichte'),
        ];
    }
    public static function get_default_social_share_fields() {
        return ['title', 'date', 'time', 'number', 'description', 'url'];
    }

    public static function normalize_social_share_fields($values) {
        $allowed = array_keys(self::get_social_share_field_definitions());
        $normalized = array_values(array_intersect(
            $allowed,
            array_map('sanitize_key', (array) $values)
        ));

        if (!in_array('url', $normalized, true)) {
            $normalized[] = 'url';
        }

        return array_values(array_unique($normalized));
    }

    public static function normalize_social_share_image_mode($value) {
        $mode = sanitize_key((string) $value);

        if (in_array($mode, ['post_image', 'generated'], true)) {
            return $mode;
        }

        return 'post_image';
    }

    public static function get_social_share_layout_definitions() {
        return [
            'wide' => [
                'label' => __('Breit 1200 x 630', 'feuer-einsatzberichte'),
                'description' => __('Fuer Link-Vorschauen und klassische breite Share-Karten.', 'feuer-einsatzberichte'),
            ],
            'story' => [
                'label' => __('Story 1080 x 1920', 'feuer-einsatzberichte'),
                'description' => __('Fuer mobile Story-Formate mit schmaler, zentraler Textflaeche.', 'feuer-einsatzberichte'),
            ],
            'feed' => [
                'label' => __('Feed 1080 x 1350', 'feuer-einsatzberichte'),
                'description' => __('Fuer hohe Feed-Posts mit viel Platz fuer Titel und Beschreibung.', 'feuer-einsatzberichte'),
            ],
        ];
    }
    public static function normalize_social_share_layout($value) {
        $layout = sanitize_key((string) $value);
        $allowed = array_keys(self::get_social_share_layout_definitions());

        if (in_array($layout, $allowed, true)) {
            return $layout;
        }

        return 'wide';
    }

    public static function get_social_share_text_align_options() {
        return [
            'auto' => __('Automatisch nach Format', 'feuer-einsatzberichte'),
            'left' => __('Links', 'feuer-einsatzberichte'),
            'center' => __('Zentriert', 'feuer-einsatzberichte'),
            'right' => __('Rechts', 'feuer-einsatzberichte'),
        ];
    }

    public static function normalize_social_share_text_align($value) {
        $align = sanitize_key((string) $value);

        return in_array($align, array_keys(self::get_social_share_text_align_options()), true) ? $align : 'auto';
    }

    public static function get_social_share_logo_position_options() {
        return [
            'bottom-right' => __('Unten rechts', 'feuer-einsatzberichte'),
            'bottom-left' => __('Unten links', 'feuer-einsatzberichte'),
            'top-right' => __('Oben rechts', 'feuer-einsatzberichte'),
            'top-left' => __('Oben links', 'feuer-einsatzberichte'),
            'hidden' => __('Logo ausblenden', 'feuer-einsatzberichte'),
        ];
    }

    public static function normalize_social_share_logo_position($value) {
        $position = sanitize_key((string) $value);

        return in_array($position, array_keys(self::get_social_share_logo_position_options()), true) ? $position : 'bottom-right';
    }

    public static function get_social_share_card_dimensions($layout = 'wide') {
        switch (self::normalize_social_share_layout($layout)) {
            case 'story':
                return [
                    'width' => 1080,
                    'height' => 1920,
                    'orientation' => 'portrait',
                ];
            case 'feed':
                return [
                    'width' => 1080,
                    'height' => 1350,
                    'orientation' => 'portrait',
                ];
            case 'wide':
            default:
                return [
                    'width' => 1200,
                    'height' => 630,
                    'orientation' => 'landscape',
                ];
        }
    }

    public static function normalize_social_share_card_text($value, $fallback = '') {
        $value = sanitize_text_field((string) $value);
        $value = trim(preg_replace('/\s+/', ' ', $value));

        if ('' === $value) {
            $value = trim((string) $fallback);
        }

        return $value;
    }

    public static function get_street_suggestions($limit = 250) {
        $records = self::get_street_suggestion_records($limit);
        $suggestions = [];

        foreach ($records as $record) {
            $street = isset($record['street']) ? trim((string) $record['street']) : '';

            if ('' === $street) {
                continue;
            }

            $key = function_exists('mb_strtolower')
                ? mb_strtolower($street, 'UTF-8')
                : strtolower($street);

            if (!isset($suggestions[$key])) {
                $suggestions[$key] = $street;
            }
        }

        return array_values($suggestions);
    }

    public static function get_street_suggestion_records($limit = 500) {
        $limit = max(20, min(1000, absint($limit)));
        $records = [];
        $indexed_records = [];

        if (class_exists('FEU_Einsatz_Database') && method_exists('FEU_Einsatz_Database', 'get_active_instance')) {
            $database = FEU_Einsatz_Database::get_active_instance();
        } else {
            $database = null;
        }

        if ($database instanceof FEU_Einsatz_Database) {
            $registry_rows = $database->get_street_registry_entries(['limit' => $limit]);

            foreach ((array) $registry_rows as $row) {
                $street = isset($row->street) ? sanitize_text_field((string) $row->street) : '';
                $street = trim((string) self::strip_house_number_from_street($street));
                $plz = isset($row->postcode) ? preg_replace('/\D+/', '', (string) $row->postcode) : '';
                $city = isset($row->city) ? sanitize_text_field((string) $row->city) : '';

                if ('' === $street) {
                    continue;
                }

                if ('' !== $plz && !preg_match('/^\d{5}$/', $plz)) {
                    $plz = '';
                }

                $street_key = function_exists('mb_strtolower')
                    ? mb_strtolower($street, 'UTF-8')
                    : strtolower($street);
                $record_key = $street_key . '|' . $plz . '|' . $city;

                $indexed_records[$record_key] = [
                    'street' => $street,
                    'plz' => $plz,
                    'city' => $city,
                    'usage_count' => 0,
                    'last_used' => isset($row->created_at) ? sanitize_text_field((string) $row->created_at) : '',
                    'source' => 'registry',
                ];
            }
        }

        global $wpdb;

        if (!is_object($wpdb) || empty($wpdb->postmeta) || empty($wpdb->posts)) {
            return array_values($indexed_records);
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT
                    street.meta_value AS street,
                    COALESCE(plz.meta_value, '') AS plz,
                    COALESCE(city.meta_value, '') AS city,
                    COUNT(DISTINCT posts.ID) AS usage_count,
                    MAX(posts.post_date) AS last_used
                FROM {$wpdb->postmeta} street
                LEFT JOIN {$wpdb->postmeta} plz
                    ON plz.post_id = street.post_id
                    AND plz.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} city
                    ON city.post_id = street.post_id
                    AND city.meta_key = %s
                INNER JOIN {$wpdb->posts} posts
                    ON posts.ID = street.post_id
                WHERE street.meta_key = %s
                  AND street.meta_value <> ''
                  AND posts.post_type = 'post'
                  AND posts.post_status NOT IN ('trash', 'auto-draft')
                GROUP BY street.meta_value, plz.meta_value, city.meta_value
                ORDER BY usage_count DESC, street.meta_value ASC
                LIMIT %d
                ",
                '_feu_einsatz_plz',
                '_feu_einsatz_stadt',
                '_feu_einsatz_strasse',
                $limit
            )
        );

        foreach ((array) $rows as $row) {
            $street = isset($row->street) ? sanitize_text_field((string) $row->street) : '';
            $street = trim((string) self::strip_house_number_from_street($street));
            $plz = isset($row->plz) ? preg_replace('/\D+/', '', (string) $row->plz) : '';
            $city = isset($row->city) ? sanitize_text_field((string) $row->city) : '';

            if ('' === $street) {
                continue;
            }

            if ('' !== $plz && !preg_match('/^\d{5}$/', $plz)) {
                $plz = '';
            }

            $street_key = function_exists('mb_strtolower')
                ? mb_strtolower($street, 'UTF-8')
                : strtolower($street);
            $record_key = $street_key . '|' . $plz . '|' . $city;

            if (isset($indexed_records[$record_key])) {
                $indexed_records[$record_key]['usage_count'] += max(1, absint($row->usage_count ?? 1));
                continue;
            }

            $indexed_records[$record_key] = [
                'street' => $street,
                'plz' => $plz,
                'city' => $city,
                'usage_count' => max(1, absint($row->usage_count ?? 1)),
                'last_used' => isset($row->last_used) ? sanitize_text_field((string) $row->last_used) : '',
                'source' => 'reports',
            ];
        }

        uasort($indexed_records, static function ($left, $right) {
            $source_compare = ((string) ($left['source'] ?? '')) === 'registry' && ((string) ($right['source'] ?? '')) !== 'registry'
                ? -1
                : ((((string) ($right['source'] ?? '')) === 'registry' && ((string) ($left['source'] ?? '')) !== 'registry') ? 1 : 0);

            if (0 !== $source_compare) {
                return $source_compare;
            }

            $usage_compare = ((int) ($right['usage_count'] ?? 0)) <=> ((int) ($left['usage_count'] ?? 0));

            if (0 !== $usage_compare) {
                return $usage_compare;
            }

            return strnatcasecmp((string) ($left['street'] ?? ''), (string) ($right['street'] ?? ''));
        });

        foreach ($indexed_records as $record_key => $record) {
            unset($indexed_records[$record_key]['source']);
        }

        return array_values($indexed_records);
    }

    public static function search_street_suggestion_records($query, $limit = 5) {
        $query = trim((string) $query);
        $limit = max(1, min(20, absint($limit)));

        if ('' === $query) {
            return [];
        }

        $normalized_query = function_exists('mb_strtolower')
            ? mb_strtolower($query, 'UTF-8')
            : strtolower($query);

        $records = self::get_street_suggestion_records(500);
        $matches = [];

        foreach ($records as $record) {
            $street = isset($record['street']) ? trim((string) $record['street']) : '';

            if ('' === $street) {
                continue;
            }

            $normalized_street = function_exists('mb_strtolower')
                ? mb_strtolower($street, 'UTF-8')
                : strtolower($street);

            $position = strpos($normalized_street, $normalized_query);

            if (false === $position) {
                continue;
            }

            $record['_match_position'] = (int) $position;
            $record['_starts_with'] = 0 === $position ? 1 : 0;
            $matches[] = $record;
        }

        usort($matches, static function ($left, $right) {
            $starts_with_compare = ((int) ($right['_starts_with'] ?? 0)) <=> ((int) ($left['_starts_with'] ?? 0));

            if (0 !== $starts_with_compare) {
                return $starts_with_compare;
            }

            $position_compare = ((int) ($left['_match_position'] ?? 9999)) <=> ((int) ($right['_match_position'] ?? 9999));

            if (0 !== $position_compare) {
                return $position_compare;
            }

            $usage_compare = ((int) ($right['usage_count'] ?? 0)) <=> ((int) ($left['usage_count'] ?? 0));

            if (0 !== $usage_compare) {
                return $usage_compare;
            }

            return strnatcasecmp((string) ($left['street'] ?? ''), (string) ($right['street'] ?? ''));
        });

        $matches = array_slice($matches, 0, $limit);

        foreach ($matches as &$match) {
            unset($match['_match_position'], $match['_starts_with']);
        }
        unset($match);

        return array_values($matches);
    }

    public static function get_default_single_info_fields() {
        return ['street', 'location', 'date', 'time', 'category', 'organizations'];
    }

    public static function normalize_single_info_fields($values) {
        $allowed = self::get_default_single_info_fields();
        $normalized = array_values(array_intersect(
            $allowed,
            array_map('sanitize_key', (array) $values)
        ));

        return array_values(array_unique($normalized));
    }

    public static function normalize_single_map_display_mode($value) {
        $mode = sanitize_key((string) $value);

        if (in_array($mode, ['live', 'image', 'disabled'], true)) {
            return $mode;
        }

        return 'live';
    }

    public static function normalize_single_map_privacy_mode($value) {
        $mode = sanitize_key((string) $value);

        if (in_array($mode, ['always', 'consent_hide', 'consent_image'], true)) {
            return $mode;
        }

        return 'always';
    }

    public static function has_cookie_map_consent_integration() {
        return function_exists('ff_cookie_has_consent')
            || function_exists('feuer_cookie_has_category_consent')
            || function_exists('feuer_cookie_header_macro')
            || function_exists('feuer_cookie_footer_macro');
    }

    public static function has_cookie_map_consent() {
        if (function_exists('ff_cookie_has_consent')) {
            return (bool) ff_cookie_has_consent('leaflet_osm');
        }

        if (function_exists('feuer_cookie_has_category_consent')) {
            return (bool) feuer_cookie_has_category_consent('maps');
        }

        return true;
    }

    public static function render_cookie_macro($position = 'header') {
        $position = 'footer' === sanitize_key((string) $position) ? 'footer' : 'header';

        ob_start();

        if ('header' === $position && function_exists('feuer_cookie_header_macro')) {
            feuer_cookie_header_macro();
        }

        if ('footer' === $position && function_exists('feuer_cookie_footer_macro')) {
            feuer_cookie_footer_macro();
        }

        return (string) ob_get_clean();
    }

    public static function get_overview_report_card_template($variant) {
        $variant = self::normalize_report_card_variant($variant);

        switch ($variant) {
            case 'classic':
                return 'templates/public/overview/partials/report-card-classic.php';
            case 'top':
                return 'templates/public/overview/partials/report-card-top.php';
            case 'minimal':
                return 'templates/public/overview/partials/report-card-minimal.php';
            case 'banner':
                return 'templates/public/overview/partials/report-card-banner.php';
            case 'poster':
                return 'templates/public/overview/partials/report-card-poster.php';
            case 'outline':
                return 'templates/public/overview/partials/report-card-outline.php';
            case 'magazine':
                return 'templates/public/overview/partials/report-card-magazine.php';
            case 'magazine-left':
                return 'templates/public/overview/partials/report-card-magazine-left.php';
            case 'modern':
            default:
                return 'templates/public/overview/partials/report-card.php';
        }
    }

    public static function get_report_card_variant_definitions() {
        return [
            'modern' => [
                'label' => __('Modern', 'feuer-einsatzberichte'),
                'description' => __('Aktuelle Standardkarte mit ausgewogener Bild- und Textflaeche.', 'feuer-einsatzberichte'),
            ],
            'classic' => [
                'label' => __('Classic', 'feuer-einsatzberichte'),
                'description' => __('Kompaktere Variante mit bewusst kuerzerem Beschreibungstext.', 'feuer-einsatzberichte'),
            ],
            'top' => [
                'label' => __('Top', 'feuer-einsatzberichte'),
                'description' => __('Vertikale Karte mit Bildbereich oberhalb des Inhalts.', 'feuer-einsatzberichte'),
            ],
            'minimal' => [
                'label' => __('Minimal', 'feuer-einsatzberichte'),
                'description' => __('Reduzierte Textkarte ohne Bild fuer sehr kompakte Listen.', 'feuer-einsatzberichte'),
            ],
            'banner' => [
                'label' => __('Banner', 'feuer-einsatzberichte'),
                'description' => __('Breite Hero-Karte mit starkem Bildfokus im Hintergrund.', 'feuer-einsatzberichte'),
            ],
            'poster' => [
                'label' => __('Poster', 'feuer-einsatzberichte'),
                'description' => __('Bildstarke Variante mit plakatartiger Gewichtung des Vorschaubilds.', 'feuer-einsatzberichte'),
            ],
            'outline' => [
                'label' => __('Outline', 'feuer-einsatzberichte'),
                'description' => __('Leichte, gerahmte Karte mit ruhigerer Darstellung.', 'feuer-einsatzberichte'),
            ],
            'magazine' => [
                'label' => __('Magazine', 'feuer-einsatzberichte'),
                'description' => __('Editorial-artige Karte mit magazinartiger Aufteilung.', 'feuer-einsatzberichte'),
            ],
            'magazine-left' => [
                'label' => __('Magazine Left', 'feuer-einsatzberichte'),
                'description' => __('Wie Magazine, jedoch mit Bildbereich links statt rechts.', 'feuer-einsatzberichte'),
            ],
        ];
    }

    public static function format_participant_name($vorname, $nachname, $separator = ', ') {
        $parts = array_filter([
            trim((string) $vorname),
            trim((string) $nachname),
        ], static function($value) {
            return '' !== $value;
        });

        return implode((string) $separator, $parts);
    }

    public static function sanitize_postcode_list($value) {
        $parts = self::sanitize_area_entry_list($value);
        $postcodes = [];

        foreach ((array) $parts as $part) {
            $postcode = preg_replace('/\D+/', '', (string) $part);

            if (preg_match('/^\d{4,6}$/', $postcode)) {
                $postcodes[] = $postcode;
            }
        }

        return array_values(array_unique($postcodes));
    }

    public static function sanitize_area_entry_list($value) {
        $parts = is_array($value) ? $value : preg_split('/[\r\n;]+/', (string) $value);
        $entries = [];

        foreach ((array) $parts as $part) {
            $part = trim(sanitize_text_field((string) $part));

            if ('' === $part) {
                continue;
            }

            if (preg_match('/^\d{4,6}(?:[\s,]+\d{4,6})+$/', preg_replace('/\s+/', ' ', $part))) {
                foreach (preg_split('/[\s,]+/', $part) as $numeric_part) {
                    $numeric_part = preg_replace('/\D+/', '', (string) $numeric_part);

                    if (preg_match('/^\d{4,6}$/', $numeric_part)) {
                        $entries[] = $numeric_part;
                    }
                }

                continue;
            }

            $entries[] = $part;
        }

        return array_values(array_unique(array_filter($entries, static function($entry) {
            return '' !== trim((string) $entry);
        })));
    }

    private static function sanitize_location_token($value) {
        return sanitize_title(remove_accents((string) $value));
    }

    private static function build_postcode_area_storage_key($postcode, $city = '') {
        $postcode = preg_replace('/\D+/', '', (string) $postcode);
        $city_token = self::sanitize_location_token($city);

        return $postcode . '|' . ($city_token !== '' ? $city_token : '_default');
    }

    private static function get_postcode_area_cache_key($postcode, $city = '') {
        return 'feu_einsatz_postcode_area_' . md5('de|' . self::build_postcode_area_storage_key($postcode, $city) . '|v2');
    }

    private static function build_named_area_storage_key($entry, $city = '') {
        $entry_token = self::sanitize_location_token($entry);
        $city_token = self::sanitize_location_token($city);

        return 'area|' . ($entry_token !== '' ? $entry_token : '_empty') . '|' . ($city_token !== '' ? $city_token : '_default');
    }

    private static function get_named_area_cache_key($entry, $city = '') {
        return 'feu_einsatz_named_area_' . md5('de|' . self::build_named_area_storage_key($entry, $city) . '|v1');
    }

    private static function get_postcode_area_option_cache() {
        $cache = get_option('feu_einsatz_postcode_area_cache', []);

        return is_array($cache) ? $cache : [];
    }

    private static function get_area_station_cache_key($street, $plz = '', $city = '') {
        return 'feu_einsatz_area_station_' . md5(implode('|', [
            sanitize_text_field((string) $street),
            preg_replace('/\D+/', '', (string) $plz),
            sanitize_text_field((string) $city),
        ]));
    }

    private static function build_postcode_area_feature_from_result($postcode, $result) {
        if (
            !is_array($result)
            || !isset($result['lat'], $result['lon'])
            || !is_numeric($result['lat'])
            || !is_numeric($result['lon'])
        ) {
            return false;
        }

        return [
            'postcode' => $postcode,
            'label' => sprintf(__('PLZ %s', 'feuer-einsatzberichte'), $postcode),
            'center' => [
                'lat' => round((float) $result['lat'], 6),
                'lng' => round((float) $result['lon'], 6),
            ],
            'bounds' => (isset($result['boundingbox']) && is_array($result['boundingbox']) && isset($result['boundingbox'][0], $result['boundingbox'][1], $result['boundingbox'][2], $result['boundingbox'][3]))
                ? [
                    'south' => round((float) $result['boundingbox'][0], 6),
                    'north' => round((float) $result['boundingbox'][1], 6),
                    'west' => round((float) $result['boundingbox'][2], 6),
                    'east' => round((float) $result['boundingbox'][3], 6),
                ]
                : null,
            'geojson' => isset($result['geojson']) && is_array($result['geojson']) ? $result['geojson'] : [],
        ];
    }

    private static function build_named_area_feature_from_result($entry, $result, $city = '') {
        if (
            !is_array($result)
            || !isset($result['lat'], $result['lon'])
            || !is_numeric($result['lat'])
            || !is_numeric($result['lon'])
        ) {
            return false;
        }

        $label = trim((string) $entry);

        if ('' === $label) {
            $label = isset($result['display_name']) ? sanitize_text_field((string) $result['display_name']) : '';
        }

        if ('' === $label && '' !== trim((string) $city)) {
            $label = sanitize_text_field(trim((string) $city));
        }

        return [
            'label' => $label,
            'center' => [
                'lat' => round((float) $result['lat'], 6),
                'lng' => round((float) $result['lon'], 6),
            ],
            'bounds' => (isset($result['boundingbox']) && is_array($result['boundingbox']) && isset($result['boundingbox'][0], $result['boundingbox'][1], $result['boundingbox'][2], $result['boundingbox'][3]))
                ? [
                    'south' => round((float) $result['boundingbox'][0], 6),
                    'north' => round((float) $result['boundingbox'][1], 6),
                    'west' => round((float) $result['boundingbox'][2], 6),
                    'east' => round((float) $result['boundingbox'][3], 6),
                ]
                : null,
            'geojson' => isset($result['geojson']) && is_array($result['geojson']) ? $result['geojson'] : [],
        ];
    }

    private static function postcode_result_matches($postcode, $city, $result) {
        if (!is_array($result)) {
            return false;
        }

        $postcode = preg_replace('/\D+/', '', (string) $postcode);

        if (!preg_match('/^\d{4,6}$/', $postcode)) {
            return false;
        }

        $display_name = isset($result['display_name']) ? (string) $result['display_name'] : '';
        $address = isset($result['address']) && is_array($result['address']) ? $result['address'] : [];
        $result_postcode = isset($address['postcode']) ? preg_replace('/\D+/', '', (string) $address['postcode']) : '';

        if ('' !== $result_postcode && $result_postcode !== $postcode) {
            return false;
        }

        if ('' === $result_postcode && false === strpos($display_name, $postcode)) {
            return false;
        }

        $city = trim((string) $city);

        if ('' === $city) {
            return true;
        }

        $city_token = self::sanitize_location_token($city);
        $display_token = self::sanitize_location_token($display_name);

        if ('' !== $display_token && false !== strpos($display_token, $city_token)) {
            return true;
        }

        $address_fields = ['city', 'town', 'village', 'municipality', 'suburb', 'county', 'state_district'];

        foreach ($address_fields as $field_name) {
            if (empty($address[$field_name])) {
                continue;
            }

            if (self::sanitize_location_token($address[$field_name]) === $city_token) {
                return true;
            }
        }

        return false;
    }

    private static function get_postcode_result_score($postcode, $city, $result) {
        if (!self::postcode_result_matches($postcode, $city, $result)) {
            return -INF;
        }

        $score = 0.0;
        $address = isset($result['address']) && is_array($result['address']) ? $result['address'] : [];
        $result_postcode = isset($address['postcode']) ? preg_replace('/\D+/', '', (string) $address['postcode']) : '';
        $addresstype = isset($result['addresstype']) ? sanitize_key((string) $result['addresstype']) : '';
        $type = isset($result['type']) ? sanitize_key((string) $result['type']) : '';
        $boundingbox = isset($result['boundingbox']) && is_array($result['boundingbox']) ? array_values($result['boundingbox']) : [];

        if ('' !== $result_postcode && $result_postcode === preg_replace('/\D+/', '', (string) $postcode)) {
            $score += 1200;
        }

        if (in_array($addresstype, ['postcode', 'postal_code'], true) || in_array($type, ['postcode', 'postal_code'], true)) {
            $score += 600;
        }

        if (isset($boundingbox[0], $boundingbox[1], $boundingbox[2], $boundingbox[3])) {
            $lat_span = abs((float) $boundingbox[1] - (float) $boundingbox[0]);
            $lng_span = abs((float) $boundingbox[3] - (float) $boundingbox[2]);
            $score -= (($lat_span * 1000) + ($lng_span * 1000));

            if ($lat_span > 0.20 || $lng_span > 0.30) {
                $score -= 2000;
            }
        }

        return $score;
    }

    private static function get_named_area_result_score($entry, $city, $result) {
        if (!is_array($result)) {
            return -INF;
        }

        $entry = trim((string) $entry);
        $city = trim((string) $city);
        $entry_base = trim((string) preg_replace('/\s*,.*$/u', '', $entry));
        $entry_token = self::sanitize_location_token($entry_base !== '' ? $entry_base : $entry);
        $display_name = isset($result['display_name']) ? (string) $result['display_name'] : '';
        $display_token = self::sanitize_location_token($display_name);

        if ('' !== $entry_token && false === strpos($display_token, $entry_token)) {
            return -INF;
        }

        $score = 800.0;
        $address = isset($result['address']) && is_array($result['address']) ? $result['address'] : [];
        $addresstype = isset($result['addresstype']) ? sanitize_key((string) $result['addresstype']) : '';
        $type = isset($result['type']) ? sanitize_key((string) $result['type']) : '';
        $boundingbox = isset($result['boundingbox']) && is_array($result['boundingbox']) ? array_values($result['boundingbox']) : [];

        if (in_array($addresstype, ['suburb', 'quarter', 'neighbourhood', 'city_district', 'borough'], true)) {
            $score += 320;
        }

        if (in_array($type, ['suburb', 'quarter', 'neighbourhood', 'city_district', 'borough', 'administrative'], true)) {
            $score += 180;
        }

        if ('' !== $city) {
            $city_token = self::sanitize_location_token($city);
            $city_matched = false;

            if ('' !== $display_token && false !== strpos($display_token, $city_token)) {
                $city_matched = true;
            }

            foreach (['city', 'town', 'village', 'municipality', 'suburb', 'county', 'state_district'] as $field_name) {
                if (!empty($address[$field_name]) && false !== strpos(self::sanitize_location_token($address[$field_name]), $city_token)) {
                    $city_matched = true;
                    break;
                }
            }

            if ($city_matched) {
                $score += 220;
            } else {
                $score -= 600;
            }
        }

        if (isset($boundingbox[0], $boundingbox[1], $boundingbox[2], $boundingbox[3])) {
            $lat_span = abs((float) $boundingbox[1] - (float) $boundingbox[0]);
            $lng_span = abs((float) $boundingbox[3] - (float) $boundingbox[2]);
            $score -= (($lat_span * 700) + ($lng_span * 700));

            if ($lat_span > 0.28 || $lng_span > 0.40) {
                $score -= 1800;
            }
        }

        return $score;
    }

    private static function build_postcode_synthetic_bounds($center) {
        if (
            !is_array($center)
            || !isset($center['lat'], $center['lng'])
            || !is_numeric($center['lat'])
            || !is_numeric($center['lng'])
        ) {
            return null;
        }

        $latitude = (float) $center['lat'];
        $longitude = (float) $center['lng'];
        $lat_delta = 0.0125;
        $cos_latitude = cos(deg2rad($latitude));
        $lng_delta = $cos_latitude > 0.001 ? max(0.018, 0.0125 / $cos_latitude) : 0.02;

        return [
            'south' => round($latitude - $lat_delta, 6),
            'north' => round($latitude + $lat_delta, 6),
            'west' => round($longitude - $lng_delta, 6),
            'east' => round($longitude + $lng_delta, 6),
        ];
    }

    private static function collect_geojson_coordinate_pairs($coordinates, &$pairs) {
        if (!is_array($coordinates)) {
            return;
        }

        if (
            isset($coordinates[0], $coordinates[1])
            && is_numeric($coordinates[0])
            && is_numeric($coordinates[1])
        ) {
            $pairs[] = [(float) $coordinates[1], (float) $coordinates[0]];
            return;
        }

        foreach ($coordinates as $nested_coordinates) {
            self::collect_geojson_coordinate_pairs($nested_coordinates, $pairs);
        }
    }

    private static function build_bounds_from_geojson($geojson) {
        if (!is_array($geojson) || empty($geojson['coordinates'])) {
            return null;
        }

        $pairs = [];
        self::collect_geojson_coordinate_pairs($geojson['coordinates'], $pairs);

        if (empty($pairs)) {
            return null;
        }

        $latitudes = array_column($pairs, 0);
        $longitudes = array_column($pairs, 1);

        return [
            'south' => round(min($latitudes), 6),
            'north' => round(max($latitudes), 6),
            'west' => round(min($longitudes), 6),
            'east' => round(max($longitudes), 6),
        ];
    }

    private static function normalize_postcode_area_feature($postcode, $feature) {
        if (!is_array($feature)) {
            return false;
        }

        $postcode = preg_replace('/\D+/', '', (string) $postcode);

        if (!preg_match('/^\d{4,6}$/', $postcode)) {
            return false;
        }

        $center = null;

        if (isset($feature['center']) && is_array($feature['center'])) {
            if (
                isset($feature['center']['lat'], $feature['center']['lng'])
                && is_numeric($feature['center']['lat'])
                && is_numeric($feature['center']['lng'])
            ) {
                $center = [
                    'lat' => round((float) $feature['center']['lat'], 6),
                    'lng' => round((float) $feature['center']['lng'], 6),
                ];
            } elseif (
                isset($feature['center']['latitude'], $feature['center']['longitude'])
                && is_numeric($feature['center']['latitude'])
                && is_numeric($feature['center']['longitude'])
            ) {
                $center = [
                    'lat' => round((float) $feature['center']['latitude'], 6),
                    'lng' => round((float) $feature['center']['longitude'], 6),
                ];
            } elseif (
                isset($feature['center'][0], $feature['center'][1])
                && is_numeric($feature['center'][0])
                && is_numeric($feature['center'][1])
            ) {
                $center = [
                    'lat' => round((float) $feature['center'][0], 6),
                    'lng' => round((float) $feature['center'][1], 6),
                ];
            }
        }

        if (
            null === $center
            && isset($feature['latitude'], $feature['longitude'])
            && is_numeric($feature['latitude'])
            && is_numeric($feature['longitude'])
        ) {
            $center = [
                'lat' => round((float) $feature['latitude'], 6),
                'lng' => round((float) $feature['longitude'], 6),
            ];
        }

        if (
            null === $center
            && isset($feature['lat'], $feature['lon'])
            && is_numeric($feature['lat'])
            && is_numeric($feature['lon'])
        ) {
            $center = [
                'lat' => round((float) $feature['lat'], 6),
                'lng' => round((float) $feature['lon'], 6),
            ];
        }

        if (
            null === $center
            && isset($feature['lat'], $feature['lng'])
            && is_numeric($feature['lat'])
            && is_numeric($feature['lng'])
        ) {
            $center = [
                'lat' => round((float) $feature['lat'], 6),
                'lng' => round((float) $feature['lng'], 6),
            ];
        }

        if (null === $center) {
            return false;
        }

        $geojson = [];

        if (isset($feature['geojson'])) {
            $geojson = $feature['geojson'];
        } elseif (isset($feature['geometry'])) {
            $geojson = $feature['geometry'];
        } elseif (isset($feature['feature'])) {
            $geojson = $feature['feature'];
        }

        if (is_string($geojson) && '' !== trim($geojson)) {
            $decoded_geojson = json_decode($geojson, true);
            $geojson = is_array($decoded_geojson) ? $decoded_geojson : [];
        }

        if (is_array($geojson) && isset($geojson['geometry']) && is_array($geojson['geometry']) && !empty($geojson['geometry']['type'])) {
            $geojson = $geojson['geometry'];
        }

        if (!is_array($geojson) || empty($geojson['type'])) {
            $geojson = [];
        } elseif (in_array((string) $geojson['type'], ['Point', 'MultiPoint'], true)) {
            $geojson = [];
        }

        $bounds = null;

        if (isset($feature['bounds']) && is_array($feature['bounds'])) {
            $candidate_bounds = $feature['bounds'];

            if (
                isset($candidate_bounds['south'], $candidate_bounds['north'], $candidate_bounds['west'], $candidate_bounds['east'])
                && is_numeric($candidate_bounds['south'])
                && is_numeric($candidate_bounds['north'])
                && is_numeric($candidate_bounds['west'])
                && is_numeric($candidate_bounds['east'])
            ) {
                $bounds = [
                    'south' => round((float) $candidate_bounds['south'], 6),
                    'north' => round((float) $candidate_bounds['north'], 6),
                    'west' => round((float) $candidate_bounds['west'], 6),
                    'east' => round((float) $candidate_bounds['east'], 6),
                ];
            }
        }

        if (null === $bounds && isset($feature['boundingbox']) && is_array($feature['boundingbox'])) {
            $candidate_bounds = array_values($feature['boundingbox']);

            if (
                isset($candidate_bounds[0], $candidate_bounds[1], $candidate_bounds[2], $candidate_bounds[3])
                && is_numeric($candidate_bounds[0])
                && is_numeric($candidate_bounds[1])
                && is_numeric($candidate_bounds[2])
                && is_numeric($candidate_bounds[3])
            ) {
                $bounds = [
                    'south' => round((float) $candidate_bounds[0], 6),
                    'north' => round((float) $candidate_bounds[1], 6),
                    'west' => round((float) $candidate_bounds[2], 6),
                    'east' => round((float) $candidate_bounds[3], 6),
                ];
            }
        }

        if (null === $bounds && !empty($geojson)) {
            $bounds = self::build_bounds_from_geojson($geojson);
        }

        if (null === $bounds) {
            $bounds = self::build_postcode_synthetic_bounds($center);
        }

        return [
            'postcode' => $postcode,
            'label' => isset($feature['label']) && '' !== trim((string) $feature['label'])
                ? sanitize_text_field((string) $feature['label'])
                : sprintf(__('PLZ %s', 'feuer-einsatzberichte'), $postcode),
            'center' => $center,
            'bounds' => $bounds,
            'geojson' => $geojson,
        ];
    }

    private static function postcode_area_feature_has_shape($feature) {
        $has_supported_geojson = !empty($feature['geojson'])
            && is_array($feature['geojson'])
            && !empty($feature['geojson']['type'])
            && in_array((string) $feature['geojson']['type'], ['Polygon', 'MultiPolygon', 'LineString', 'MultiLineString'], true);

        return is_array($feature)
            && (
                $has_supported_geojson
                || (!empty($feature['bounds']) && is_array($feature['bounds']))
            );
    }

    private static function persist_postcode_area_feature($postcode, $feature, $city = '') {
        $feature = self::normalize_postcode_area_feature($postcode, $feature);

        if (!is_array($feature) || empty($feature['center'])) {
            return;
        }

        $postcode = preg_replace('/\D+/', '', (string) $postcode);

        if (!preg_match('/^\d{4,6}$/', $postcode)) {
            return;
        }

        $cache_key = self::build_postcode_area_storage_key($postcode, $city);
        $cache = self::get_postcode_area_option_cache();
        $cache[$cache_key] = $feature;
        update_option('feu_einsatz_postcode_area_cache', $cache, false);
        set_transient(
            self::get_postcode_area_cache_key($postcode, $city),
            $feature,
            defined('YEAR_IN_SECONDS') ? YEAR_IN_SECONDS : 31536000
        );
    }

    private static function request_postcode_area_feature($postcode, $city = '') {
        $postcode = preg_replace('/\D+/', '', (string) $postcode);
        $city = trim((string) $city);

        if (!preg_match('/^\d{4,6}$/', $postcode)) {
            return false;
        }

        $request_variants = [];

        if ('' !== $city) {
            $request_variants[] = [
                'format' => 'jsonv2',
                // Nominatim stores long streets as several OSM ways. Keeping
                // the first result only produces an arbitrary short section.
                'limit' => 10,
                'countrycodes' => 'de',
                'city' => $city,
                'postalcode' => $postcode,
                'addressdetails' => 1,
                'polygon_geojson' => 1,
            ];
            $request_variants[] = [
                'format' => 'jsonv2',
                // One street commonly consists of several OSM ways. Ask for
                // enough exact address matches to collect those real ways
                // instead of retaining only the short house-number segment.
                'limit' => 10,
                'countrycodes' => 'de',
                'q' => $postcode . ', ' . $city . ', Deutschland',
                'addressdetails' => 1,
                'polygon_geojson' => 1,
            ];
        }

        $request_variants[] = [
            'format' => 'jsonv2',
            'limit' => 1,
            'countrycodes' => 'de',
            'country' => 'Deutschland',
            'postalcode' => $postcode,
            'addressdetails' => 1,
            'polygon_geojson' => 1,
        ];
        $request_variants[] = [
            'format' => 'jsonv2',
            'limit' => 1,
            'countrycodes' => 'de',
            'q' => $postcode . ', Deutschland',
            'addressdetails' => 1,
            'polygon_geojson' => 1,
        ];
        $best_feature = false;
        $best_score = -INF;

        foreach ($request_variants as $request_args) {
            $request_url = add_query_arg($request_args, self::NOMINATIM_SEARCH_URL);
            $response = wp_safe_remote_get($request_url, self::get_map_remote_request_args(20));
            $results = self::decode_map_json_response($response);

            if (empty($results) || !is_array($results)) {
                continue;
            }

            foreach ($results as $result) {
                $result_score = self::get_postcode_result_score($postcode, $city, $result);

                if (!is_finite($result_score)) {
                    continue;
                }

                $feature = self::build_postcode_area_feature_from_result($postcode, $result);

                if (!$feature) {
                    continue;
                }

                if (
                    !$best_feature
                    || $result_score > $best_score
                    || (
                        (float) $result_score === (float) $best_score
                        && empty($best_feature['bounds'])
                        && !empty($feature['bounds'])
                    )
                ) {
                    $best_feature = $feature;
                    $best_score = $result_score;
                }
            }
        }

        return $best_feature ?: false;
    }

    private static function normalize_named_area_feature($entry, $feature, $city = '') {
        if (!is_array($feature)) {
            return false;
        }

        $entry = trim((string) $entry);

        if ('' === $entry) {
            return false;
        }

        $center = null;

        if (isset($feature['center']) && is_array($feature['center'])) {
            if (
                isset($feature['center']['lat'], $feature['center']['lng'])
                && is_numeric($feature['center']['lat'])
                && is_numeric($feature['center']['lng'])
            ) {
                $center = [
                    'lat' => round((float) $feature['center']['lat'], 6),
                    'lng' => round((float) $feature['center']['lng'], 6),
                ];
            } elseif (
                isset($feature['center']['latitude'], $feature['center']['longitude'])
                && is_numeric($feature['center']['latitude'])
                && is_numeric($feature['center']['longitude'])
            ) {
                $center = [
                    'lat' => round((float) $feature['center']['latitude'], 6),
                    'lng' => round((float) $feature['center']['longitude'], 6),
                ];
            } elseif (
                isset($feature['center'][0], $feature['center'][1])
                && is_numeric($feature['center'][0])
                && is_numeric($feature['center'][1])
            ) {
                $center = [
                    'lat' => round((float) $feature['center'][0], 6),
                    'lng' => round((float) $feature['center'][1], 6),
                ];
            }
        }

        if (
            null === $center
            && isset($feature['latitude'], $feature['longitude'])
            && is_numeric($feature['latitude'])
            && is_numeric($feature['longitude'])
        ) {
            $center = [
                'lat' => round((float) $feature['latitude'], 6),
                'lng' => round((float) $feature['longitude'], 6),
            ];
        }

        if (
            null === $center
            && isset($feature['lat'], $feature['lon'])
            && is_numeric($feature['lat'])
            && is_numeric($feature['lon'])
        ) {
            $center = [
                'lat' => round((float) $feature['lat'], 6),
                'lng' => round((float) $feature['lon'], 6),
            ];
        }

        if (
            null === $center
            && isset($feature['lat'], $feature['lng'])
            && is_numeric($feature['lat'])
            && is_numeric($feature['lng'])
        ) {
            $center = [
                'lat' => round((float) $feature['lat'], 6),
                'lng' => round((float) $feature['lng'], 6),
            ];
        }

        if (null === $center) {
            return false;
        }

        $geojson = [];

        if (isset($feature['geojson'])) {
            $geojson = $feature['geojson'];
        } elseif (isset($feature['geometry'])) {
            $geojson = $feature['geometry'];
        } elseif (isset($feature['feature'])) {
            $geojson = $feature['feature'];
        }

        if (is_string($geojson) && '' !== trim($geojson)) {
            $decoded_geojson = json_decode($geojson, true);
            $geojson = is_array($decoded_geojson) ? $decoded_geojson : [];
        }

        if (is_array($geojson) && isset($geojson['geometry']) && is_array($geojson['geometry']) && !empty($geojson['geometry']['type'])) {
            $geojson = $geojson['geometry'];
        }

        if (!is_array($geojson) || empty($geojson['type'])) {
            $geojson = [];
        } elseif (in_array((string) $geojson['type'], ['Point', 'MultiPoint'], true)) {
            $geojson = [];
        }

        $bounds = null;

        if (isset($feature['bounds']) && is_array($feature['bounds'])) {
            $candidate_bounds = $feature['bounds'];

            if (
                isset($candidate_bounds['south'], $candidate_bounds['north'], $candidate_bounds['west'], $candidate_bounds['east'])
                && is_numeric($candidate_bounds['south'])
                && is_numeric($candidate_bounds['north'])
                && is_numeric($candidate_bounds['west'])
                && is_numeric($candidate_bounds['east'])
            ) {
                $bounds = [
                    'south' => round((float) $candidate_bounds['south'], 6),
                    'north' => round((float) $candidate_bounds['north'], 6),
                    'west' => round((float) $candidate_bounds['west'], 6),
                    'east' => round((float) $candidate_bounds['east'], 6),
                ];
            }
        }

        if (null === $bounds && isset($feature['boundingbox']) && is_array($feature['boundingbox'])) {
            $candidate_bounds = array_values($feature['boundingbox']);

            if (
                isset($candidate_bounds[0], $candidate_bounds[1], $candidate_bounds[2], $candidate_bounds[3])
                && is_numeric($candidate_bounds[0])
                && is_numeric($candidate_bounds[1])
                && is_numeric($candidate_bounds[2])
                && is_numeric($candidate_bounds[3])
            ) {
                $bounds = [
                    'south' => round((float) $candidate_bounds[0], 6),
                    'north' => round((float) $candidate_bounds[1], 6),
                    'west' => round((float) $candidate_bounds[2], 6),
                    'east' => round((float) $candidate_bounds[3], 6),
                ];
            }
        }

        if (null === $bounds && !empty($geojson)) {
            $bounds = self::build_bounds_from_geojson($geojson);
        }

        if (null === $bounds) {
            $bounds = self::build_postcode_synthetic_bounds($center);
        }

        $label = isset($feature['label']) && '' !== trim((string) $feature['label'])
            ? sanitize_text_field((string) $feature['label'])
            : sanitize_text_field($entry);

        if ('' === $label && '' !== trim((string) $city)) {
            $label = sanitize_text_field(trim((string) $city));
        }

        return [
            'label' => $label,
            'center' => $center,
            'bounds' => $bounds,
            'geojson' => $geojson,
        ];
    }

    private static function persist_named_area_feature($entry, $feature, $city = '') {
        $feature = self::normalize_named_area_feature($entry, $feature, $city);

        if (!is_array($feature) || empty($feature['center'])) {
            return;
        }

        $storage_key = self::build_named_area_storage_key($entry, $city);
        $cache = self::get_postcode_area_option_cache();
        $cache[$storage_key] = $feature;
        update_option('feu_einsatz_postcode_area_cache', $cache, false);
        set_transient(
            self::get_named_area_cache_key($entry, $city),
            $feature,
            defined('YEAR_IN_SECONDS') ? YEAR_IN_SECONDS : 31536000
        );
    }

    private static function request_named_area_feature($entry, $city = '') {
        $entry = trim((string) $entry);
        $city = trim((string) $city);

        if ('' === $entry) {
            return false;
        }

        $query_variants = [];
        $base_query = false !== strpos($entry, ',') ? $entry : ($city !== '' ? $entry . ', ' . $city : $entry);

        foreach ([$base_query . ', Deutschland', $base_query] as $candidate_query) {
            $candidate_query = trim((string) $candidate_query, ", \t\n\r\0\x0B");

            if ('' !== $candidate_query && !in_array($candidate_query, $query_variants, true)) {
                $query_variants[] = $candidate_query;
            }
        }

        $best_feature = false;
        $best_score = -INF;

        foreach ($query_variants as $query_variant) {
            $request_url = add_query_arg(
                [
                    'format' => 'jsonv2',
                    'limit' => 8,
                    'countrycodes' => 'de',
                    'q' => $query_variant,
                    'addressdetails' => 1,
                    'polygon_geojson' => 1,
                ],
                self::NOMINATIM_SEARCH_URL
            );
            $response = wp_safe_remote_get($request_url, self::get_map_remote_request_args(20));
            $results = self::decode_map_json_response($response);

            if (empty($results) || !is_array($results)) {
                continue;
            }

            foreach ($results as $result) {
                $result_score = self::get_named_area_result_score($entry, $city, $result);

                if (!is_finite($result_score)) {
                    continue;
                }

                $feature = self::build_named_area_feature_from_result($entry, $result, $city);

                if (!$feature) {
                    continue;
                }

                if (!$best_feature || $result_score > $best_score) {
                    $best_feature = $feature;
                    $best_score = $result_score;
                }
            }
        }

        return $best_feature ?: false;
    }

    public static function get_area_station_feature($street, $plz = '', $city = 'Hamburg', $logo_id = 0, $logo_size = 40) {
        $street = trim((string) $street);
        $plz = trim((string) $plz);
        $city = trim((string) $city);

        if ('' === $street) {
            return false;
        }

        if ('' === $city) {
            $city = 'Hamburg';
        }

        $cache_key = self::get_area_station_cache_key($street, $plz, $city);
        $cached_coordinates = get_transient($cache_key);

        if (
            !is_array($cached_coordinates)
            || !isset($cached_coordinates['latitude'], $cached_coordinates['longitude'])
            || !is_numeric($cached_coordinates['latitude'])
            || !is_numeric($cached_coordinates['longitude'])
        ) {
            $geocoded_data = self::request_geocoded_address_data($street, $plz, $city);

            if ($geocoded_data) {
                $cached_coordinates = [
                    'latitude' => (float) $geocoded_data['lat'],
                    'longitude' => (float) $geocoded_data['lng'],
                ];

                set_transient(
                    $cache_key,
                    $cached_coordinates,
                    defined('WEEK_IN_SECONDS') ? WEEK_IN_SECONDS : 604800
                );
            } elseif (preg_match('/^\d{4,6}$/', preg_replace('/\D+/', '', (string) $plz))) {
                $postcode_features = self::get_postcode_area_features([$plz], $city);
                $postcode_feature = !empty($postcode_features[0]) && is_array($postcode_features[0]) ? $postcode_features[0] : null;

                if (
                    $postcode_feature
                    && !empty($postcode_feature['center'])
                    && isset($postcode_feature['center']['lat'], $postcode_feature['center']['lng'])
                    && is_numeric($postcode_feature['center']['lat'])
                    && is_numeric($postcode_feature['center']['lng'])
                ) {
                    $cached_coordinates = [
                        'latitude' => (float) $postcode_feature['center']['lat'],
                        'longitude' => (float) $postcode_feature['center']['lng'],
                    ];
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }

        $station_label = trim((string) get_option('feu_einsatz_photo_watermark_text', get_bloginfo('name')));

        if ('' === $station_label) {
            $station_label = __('Feuerwehrhaus', 'feuer-einsatzberichte');
        }

        $logo_url = $logo_id > 0 ? wp_get_attachment_image_url($logo_id, 'medium') : '';
        if ('' === (string) $logo_url && defined('FEU_EINSATZ_PLUGIN_URL')) {
            $logo_url = FEU_EINSATZ_PLUGIN_URL . 'assets/images/ff-logo.png';
        }

        return [
            'label' => $station_label,
            'address' => self::build_full_address($street, $plz, $city),
            'latitude' => (float) $cached_coordinates['latitude'],
            'longitude' => (float) $cached_coordinates['longitude'],
            'logo_id' => $logo_id > 0 ? $logo_id : 0,
            'logo_url' => (string) $logo_url,
            'logo_size' => max(20, min(96, absint($logo_size))),
        ];
    }

    public static function get_station_feature_from_settings() {
        $street = trim((string) get_option('feu_einsatz_area_station_street', ''));

        if ('' === $street) {
            return false;
        }

        return self::get_area_station_feature(
            $street,
            preg_replace('/\D+/', '', (string) get_option('feu_einsatz_area_station_postcode', '')),
            trim((string) get_option('feu_einsatz_area_station_city', 'Hamburg')),
            absint(get_option('feu_einsatz_area_station_logo_id', 0)),
            max(20, min(96, absint(get_option('feu_einsatz_area_station_logo_size', 40))))
        );
    }

    public static function get_station_street_geometry_from_settings($allow_remote_refresh = false) {
        $street = trim((string) get_option('feu_einsatz_area_station_street', ''));

        if ('' === $street) {
            return false;
        }

        $plz = preg_replace('/\D+/', '', (string) get_option('feu_einsatz_area_station_postcode', ''));
        $city = trim((string) get_option('feu_einsatz_area_station_city', 'Hamburg'));

        if ('' === $city) {
            $city = 'Hamburg';
        }

        $geometry_payload = FEU_Einsatz_Street_Cache::get($street, $plz, $city);

        if (!$geometry_payload && $allow_remote_refresh) {
            $lookup_miss_key = 'feu_einsatz_station_street_geometry_miss_' . md5($street . '|' . $plz . '|' . $city);

            if (get_transient($lookup_miss_key)) {
                return false;
            }

            $station_feature = self::get_station_feature_from_settings();
            $coordinates = false;

            if (
                is_array($station_feature)
                && isset($station_feature['latitude'], $station_feature['longitude'])
                && is_numeric($station_feature['latitude'])
                && is_numeric($station_feature['longitude'])
            ) {
                $coordinates = [
                    'lat' => (float) $station_feature['latitude'],
                    'lng' => (float) $station_feature['longitude'],
                ];
            } else {
                $geocoded_data = self::request_geocoded_address_data($street, $plz, $city);

                if ($geocoded_data) {
                    $coordinates = [
                        'lat' => (float) $geocoded_data['lat'],
                        'lng' => (float) $geocoded_data['lng'],
                    ];
                }
            }

            if ($coordinates) {
                $geometry_payload = self::request_street_geometry_data($street, $coordinates);

                if ($geometry_payload) {
                    FEU_Einsatz_Street_Cache::set($street, $plz, $city, $geometry_payload);
                    delete_transient($lookup_miss_key);
                } else {
                    set_transient($lookup_miss_key, 1, defined('MINUTE_IN_SECONDS') ? MINUTE_IN_SECONDS * 10 : 600);
                }
            } else {
                set_transient($lookup_miss_key, 1, defined('MINUTE_IN_SECONDS') ? MINUTE_IN_SECONDS * 10 : 600);
            }
        }

        return $geometry_payload ?: false;
    }

    public static function get_postcode_area_features($postcodes, $city = '') {
        $features = [];
        $option_cache = self::get_postcode_area_option_cache();
        $city = trim((string) $city);

        foreach (self::sanitize_postcode_list($postcodes) as $postcode) {
            $storage_key = self::build_postcode_area_storage_key($postcode, $city);
            $cache_key = self::get_postcode_area_cache_key($postcode, $city);
            $cached_feature = isset($option_cache[$storage_key]) && is_array($option_cache[$storage_key])
                ? $option_cache[$storage_key]
                : get_transient($cache_key);
            $feature = self::normalize_postcode_area_feature($postcode, $cached_feature);

            if (!is_array($feature) || empty($feature['center']) || !self::postcode_area_feature_has_shape($feature)) {
                $refreshed_feature = self::request_postcode_area_feature($postcode, $city);

                if (!$refreshed_feature) {
                    if (is_array($feature) && !empty($feature['center'])) {
                        $features[] = $feature;
                    }
                    continue;
                }

                $feature = $refreshed_feature;
                self::persist_postcode_area_feature($postcode, $feature, $city);
            } else {
                self::persist_postcode_area_feature($postcode, $feature, $city);
                set_transient(
                    $cache_key,
                    $feature,
                    defined('YEAR_IN_SECONDS') ? YEAR_IN_SECONDS : 31536000
                );
            }

            $features[] = $feature;
        }

        return $features;
    }

    public static function get_named_area_features($entries, $city = '') {
        $features = [];
        $option_cache = self::get_postcode_area_option_cache();
        $city = trim((string) $city);

        foreach (self::sanitize_area_entry_list($entries) as $entry) {
            if (preg_match('/^\d{4,6}$/', preg_replace('/\D+/', '', (string) $entry))) {
                continue;
            }

            $storage_key = self::build_named_area_storage_key($entry, $city);
            $cache_key = self::get_named_area_cache_key($entry, $city);
            $cached_feature = isset($option_cache[$storage_key]) && is_array($option_cache[$storage_key])
                ? $option_cache[$storage_key]
                : get_transient($cache_key);
            $feature = self::normalize_named_area_feature($entry, $cached_feature, $city);

            if (!is_array($feature) || empty($feature['center'])) {
                $refreshed_feature = self::request_named_area_feature($entry, $city);

                if (!$refreshed_feature) {
                    continue;
                }

                $feature = $refreshed_feature;
                self::persist_named_area_feature($entry, $feature, $city);
            } else {
                self::persist_named_area_feature($entry, $feature, $city);
                set_transient(
                    $cache_key,
                    $feature,
                    defined('YEAR_IN_SECONDS') ? YEAR_IN_SECONDS : 31536000
                );
            }

            $features[] = $feature;
        }

        return $features;
    }

    public static function get_area_features($entries, $city = '') {
        $entries = self::sanitize_area_entry_list($entries);

        if (empty($entries)) {
            return [];
        }

        $features = [];

        foreach ($entries as $entry) {
            $entry = trim((string) $entry);

            if ('' === $entry) {
                continue;
            }

            if (preg_match('/^\d{4,6}$/', preg_replace('/\D+/', '', $entry))) {
                $postcode_features = self::get_postcode_area_features([$entry], $city);

                if (!empty($postcode_features[0]) && is_array($postcode_features[0])) {
                    $features[] = $postcode_features[0];
                }

                continue;
            }

            $named_area_features = self::get_named_area_features([$entry], $city);

            if (!empty($named_area_features[0]) && is_array($named_area_features[0])) {
                $features[] = $named_area_features[0];
            }
        }

        return $features;
    }

    public static function find_root_category() {
        $category = get_category_by_slug('einsaetze');

        if ($category instanceof WP_Term) {
            return $category;
        }

        $categories = get_categories([
            'taxonomy' => 'category',
            'hide_empty' => false,
        ]);

        foreach ($categories as $candidate) {
            $normalized_slug = sanitize_title(remove_accents($candidate->slug));
            $normalized_name = sanitize_title(remove_accents($candidate->name));

            if (in_array($normalized_slug, ['einsaetze', 'einsatze'], true) || in_array($normalized_name, ['einsaetze', 'einsatze'], true)) {
                return $candidate;
            }
        }

        return null;
    }

    public static function get_deepest_category($post_id, $root_category_id = 0) {
        static $cache = [];

        $post_id = absint($post_id);
        $root_category_id = absint($root_category_id);
        $cache_key = $post_id . ':' . $root_category_id;

        if (array_key_exists($cache_key, $cache)) {
            return $cache[$cache_key];
        }

        if (!$root_category_id) {
            $root_category = self::find_root_category();
            $root_category_id = $root_category ? (int) $root_category->term_id : 0;
        }

        $post_categories = wp_get_post_categories($post_id);
        $deepest_category = null;
        $deepest_level = -1;

        foreach ($post_categories as $category_id) {
            if ($root_category_id && (int) $category_id !== $root_category_id && !cat_is_ancestor_of($root_category_id, $category_id)) {
                continue;
            }

            $category = get_category($category_id);

            if (!$category || is_wp_error($category)) {
                continue;
            }

            $level = count(get_ancestors($category_id, 'category'));

            if ($level > $deepest_level) {
                $deepest_level = $level;
                $deepest_category = $category;
            }
        }

        $cache[$cache_key] = $deepest_category;

        return $deepest_category;
    }

    private static function is_generated_map_attachment($attachment_id, $post_id = 0) {
        $attachment_id = absint($attachment_id);
        $post_id = absint($post_id);

        if (!$attachment_id) {
            return false;
        }

        if ('1' === (string) get_post_meta($attachment_id, '_feu_einsatz_generated_map_preview', true)) {
            return true;
        }

        if (
            $post_id > 0
            && $attachment_id === absint(get_post_meta($post_id, '_feu_einsatz_generated_map_thumbnail_id', true))
        ) {
            return true;
        }

        $attached_file = get_attached_file($attachment_id);

        if (!$attached_file) {
            return false;
        }

        $basename = wp_basename($attached_file);

        if (
            $post_id > 0
            && (
                0 === strpos($basename, 'einsatzort-' . $post_id . '-')
                || 0 === strpos($basename, 'feuer-einsatzberichte-map-' . $post_id . '-')
            )
        ) {
            return true;
        }

        return 0 === strpos($basename, 'einsatzort-preview-' . $post_id . '.')
            || 0 === strpos($basename, 'feuer-einsatzberichte-map-preview-' . $post_id . '.');
    }

    private static function resolve_local_upload_path_from_url($url) {
        $url = trim((string) $url);

        if ('' === $url) {
            return '';
        }

        $upload_dir = wp_upload_dir();
        $baseurl = isset($upload_dir['baseurl']) ? trim((string) $upload_dir['baseurl']) : '';
        $basedir = isset($upload_dir['basedir']) ? trim((string) $upload_dir['basedir']) : '';

        if ('' === $baseurl || '' === $basedir) {
            return '';
        }

        $normalized_url = strtok($url, '?');

        if (!is_string($normalized_url) || '' === $normalized_url || 0 !== strpos($normalized_url, $baseurl)) {
            return '';
        }

        $relative_path = ltrim(substr($normalized_url, strlen($baseurl)), '/');

        if ('' === $relative_path) {
            return '';
        }

        return wp_normalize_path(trailingslashit($basedir) . $relative_path);
    }

    public static function get_generated_map_preview_public_url($post_id) {
        $post_id = absint($post_id);

        if ($post_id < 1) {
            return '';
        }

        $preview_url = trim((string) get_post_meta($post_id, '_feu_einsatz_generated_map_preview_url', true));

        $preview_file = trim((string) get_post_meta($post_id, '_feu_einsatz_generated_map_preview_file', true));

        if ('' === $preview_file && '' !== $preview_url) {
            $preview_file = self::resolve_local_upload_path_from_url($preview_url);
        }

        if ('' !== $preview_file && file_exists($preview_file)) {
            if ('' !== $preview_url) {
                return add_query_arg('v', (string) filemtime($preview_file), $preview_url);
            }
        }

        $generated_attachment_id = absint(get_post_meta($post_id, '_feu_einsatz_generated_map_thumbnail_id', true));

        if ($generated_attachment_id > 0) {
            $attachment_file = (string) get_attached_file($generated_attachment_id);
            $attachment_url = wp_get_attachment_image_url($generated_attachment_id, 'full');

            if (!$attachment_url) {
                $attachment_url = wp_get_attachment_url($generated_attachment_id);
            }

            if ($attachment_url) {
                if ('' !== $attachment_file && file_exists($attachment_file)) {
                    return add_query_arg('v', (string) filemtime($attachment_file), (string) $attachment_url);
                }

                return (string) $attachment_url;
            }
        }

        if ('' !== $preview_file && file_exists($preview_file) && '' !== $preview_url) {
            return add_query_arg('v', (string) filemtime($preview_file), $preview_url);
        }

        return '';
    }

    public static function get_versioned_attachment_image_url($attachment_id, $size = 'full') {
        $attachment_id = absint($attachment_id);

        if ($attachment_id < 1) {
            return '';
        }

        $image_url = wp_get_attachment_image_url($attachment_id, $size);

        if (!$image_url) {
            return '';
        }

        $image_file = (string) get_attached_file($attachment_id);

        if ('' !== $image_file && file_exists($image_file)) {
            return add_query_arg('v', (string) filemtime($image_file), $image_url);
        }

        if ('' !== $image_file) {
            return '';
        }

        return $image_url;
    }

    public static function get_card_image_data($post_id, $size = 'medium_large') {
        $post_id = absint($post_id);
        $thumbnail_id = (int) get_post_thumbnail_id($post_id);

        if ($thumbnail_id > 0 && !self::is_generated_map_attachment($thumbnail_id, $post_id)) {
            $image_url = self::get_versioned_attachment_image_url($thumbnail_id, $size);

            if ($image_url) {
                return [
                    'url' => $image_url,
                    'attachment_id' => $thumbnail_id,
                    'is_map' => self::is_generated_map_attachment($thumbnail_id, $post_id),
                ];
            }
        }

        $gallery_ids = self::normalize_related_ids(get_post_meta($post_id, '_feu_einsatz_gallery', true));

        foreach ($gallery_ids as $attachment_id) {
            $attachment_id = absint($attachment_id);

            if ($attachment_id < 1 || self::is_generated_map_attachment($attachment_id, $post_id)) {
                continue;
            }

            $image_url = self::get_versioned_attachment_image_url($attachment_id, $size);

            if ($image_url) {
                return [
                    'url' => $image_url,
                    'attachment_id' => $attachment_id,
                    'is_map' => false,
                ];
            }
        }

        if ($thumbnail_id > 0) {
            $image_url = self::get_versioned_attachment_image_url($thumbnail_id, $size);

            if ($image_url) {
                return [
                    'url' => $image_url,
                    'attachment_id' => $thumbnail_id,
                    'is_map' => self::is_generated_map_attachment($thumbnail_id, $post_id),
                ];
            }
        }

        $preview_url = self::get_generated_map_preview_public_url($post_id);

        if ('' !== $preview_url) {
            return [
                'url' => $preview_url,
                'attachment_id' => 0,
                'is_map' => true,
            ];
        }

        return [
            'url' => '',
            'attachment_id' => 0,
            'is_map' => false,
        ];
    }

    public static function get_single_map_fallback_image_data($post_id, $gallery_ids = [], $size = 'large') {
        $post_id = absint($post_id);
        $thumbnail_id = (int) get_post_thumbnail_id($post_id);

        if ($thumbnail_id > 0 && !self::is_generated_map_attachment($thumbnail_id, $post_id)) {
            $image_url = self::get_versioned_attachment_image_url($thumbnail_id, $size);

            if ($image_url) {
                $alt_text = trim((string) get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true));

                if ('' === $alt_text) {
                    $alt_text = get_the_title($post_id);
                }

                return [
                    'url' => $image_url,
                    'attachment_id' => $thumbnail_id,
                    'alt' => $alt_text,
                ];
            }
        }

        $gallery_ids = self::normalize_related_ids($gallery_ids);

        if (empty($gallery_ids)) {
            $gallery_ids = self::normalize_related_ids(get_post_meta($post_id, '_feu_einsatz_gallery', true));
        }

        foreach ($gallery_ids as $attachment_id) {
            $attachment_id = absint($attachment_id);

            if ($attachment_id < 1) {
                continue;
            }

            $image_url = self::get_versioned_attachment_image_url($attachment_id, $size);

            if (!$image_url) {
                continue;
            }

            $alt_text = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));

            if ('' === $alt_text) {
                $alt_text = get_the_title($post_id);
            }

            return [
                'url' => $image_url,
                'attachment_id' => $attachment_id,
                'alt' => $alt_text,
            ];
        }

        $attached_images = get_children([
            'post_parent' => $post_id,
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'orderby' => 'menu_order ID',
            'order' => 'ASC',
            'numberposts' => 20,
        ]);

        if (!empty($attached_images) && is_array($attached_images)) {
            foreach ($attached_images as $attachment) {
                if (!($attachment instanceof WP_Post)) {
                    continue;
                }

                $attachment_id = (int) $attachment->ID;

                if ($attachment_id < 1 || self::is_generated_map_attachment($attachment_id, $post_id)) {
                    continue;
                }

                $image_url = self::get_versioned_attachment_image_url($attachment_id, $size);

                if (!$image_url) {
                    continue;
                }

                $alt_text = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));

                if ('' === $alt_text) {
                    $alt_text = get_the_title($post_id);
                }

                return [
                    'url' => $image_url,
                    'attachment_id' => $attachment_id,
                    'alt' => $alt_text,
                ];
            }
        }

        return [
            'url' => '',
            'attachment_id' => 0,
            'alt' => '',
        ];
    }

    public static function get_report_photo_image_data($post_id, $gallery_ids = [], $size = 'large') {
        $photo_image = self::get_single_map_fallback_image_data($post_id, $gallery_ids, $size);

        if (!empty($photo_image['attachment_id']) || '' !== trim((string) ($photo_image['url'] ?? ''))) {
            return $photo_image;
        }

        return [
            'url' => '',
            'attachment_id' => 0,
            'alt' => '',
        ];
    }

    public static function render_overview_card_image($report, $class_name = '', $args = []) {
        $report = is_array($report) ? $report : [];
        $image_url = isset($report['image_url']) ? trim((string) $report['image_url']) : '';

        if ('' === $image_url) {
            return '';
        }

        $args = wp_parse_args($args, [
            'size' => 'medium_large',
            'loading' => 'lazy',
            'fetchpriority' => 'auto',
            'sizes' => '(max-width: 768px) 100vw, 768px',
        ]);

        $class_name = trim((string) $class_name);
        $alt_text = isset($report['title']) ? (string) $report['title'] : '';
        $attributes = [
            'class' => $class_name,
            'alt' => $alt_text,
            'decoding' => 'async',
        ];
        $loading = trim((string) $args['loading']);
        $fetchpriority = trim((string) $args['fetchpriority']);
        $sizes = trim((string) $args['sizes']);

        if ('' !== $loading) {
            $attributes['loading'] = $loading;
        }

        if ('' !== $fetchpriority && 'auto' !== $fetchpriority) {
            $attributes['fetchpriority'] = $fetchpriority;
        }

        if ('' !== $sizes) {
            $attributes['sizes'] = $sizes;
        }

        $attachment_id = isset($report['image_attachment_id']) ? absint($report['image_attachment_id']) : 0;

        if ($attachment_id > 0) {
            $image_markup = wp_get_attachment_image($attachment_id, $args['size'], false, $attributes);

            return self::normalize_overview_card_image_markup($image_markup, $sizes);
        }

        return sprintf(
            '<img src="%1$s" alt="%2$s" class="%3$s" decoding="async"%4$s%5$s%6$s />',
            esc_url($image_url),
            esc_attr($alt_text),
            esc_attr($class_name),
            '' !== $loading ? ' loading="' . esc_attr($loading) . '"' : '',
            '' !== $fetchpriority && 'auto' !== $fetchpriority ? ' fetchpriority="' . esc_attr($fetchpriority) . '"' : '',
            '' !== $sizes ? ' sizes="' . esc_attr($sizes) . '"' : ''
        );
    }

    private static function normalize_overview_card_image_markup($image_markup, $sizes = '') {
        $image_markup = (string) $image_markup;
        $sizes = trim((string) $sizes);

        if ('' === $image_markup) {
            return '';
        }

        if ('' !== $sizes) {
            $image_markup = preg_replace(
                '/\ssizes="auto,\s*([^"]*)"/i',
                ' sizes="' . esc_attr($sizes) . '"',
                $image_markup
            );

            if (false === stripos($image_markup, ' sizes=')) {
                $image_markup = preg_replace(
                    '/<img\b/i',
                    '<img sizes="' . esc_attr($sizes) . '"',
                    $image_markup,
                    1
                );
            }
        }

        return $image_markup;
    }

    private static function get_map_segment_kind($highway) {
        $highway = sanitize_key((string) $highway);

        return in_array($highway, ['pedestrian', 'footway', 'path', 'steps', 'corridor', 'cycleway', 'track'], true)
            ? 'pedestrian'
            : 'road';
    }

    private static function normalize_map_geometry($geometry) {
        $normalized_geometry = [];

        foreach ((array) $geometry as $segment) {
            if (!is_array($segment)) {
                continue;
            }

            $points_source = isset($segment['points']) && is_array($segment['points'])
                ? $segment['points']
                : $segment;
            $normalized_points = [];

            foreach ($points_source as $point) {
                if (!is_array($point)) {
                    continue;
                }

                $lat = null;
                $lng = null;

                if (isset($point['lat'], $point['lng']) && is_numeric($point['lat']) && is_numeric($point['lng'])) {
                    $lat = (float) $point['lat'];
                    $lng = (float) $point['lng'];
                } elseif (isset($point['latitude'], $point['longitude']) && is_numeric($point['latitude']) && is_numeric($point['longitude'])) {
                    $lat = (float) $point['latitude'];
                    $lng = (float) $point['longitude'];
                } elseif (isset($point['lat'], $point['lon']) && is_numeric($point['lat']) && is_numeric($point['lon'])) {
                    $lat = (float) $point['lat'];
                    $lng = (float) $point['lon'];
                } elseif (isset($point[0], $point[1]) && is_numeric($point[0]) && is_numeric($point[1])) {
                    $lat = (float) $point[0];
                    $lng = (float) $point[1];
                }

                if (null === $lat || null === $lng) {
                    continue;
                }

                $normalized_points[] = [
                    'lat' => $lat,
                    'lng' => $lng,
                ];
            }

            if (count($normalized_points) < 2) {
                continue;
            }

            $highway = isset($segment['highway']) ? sanitize_key((string) $segment['highway']) : '';
            $kind = isset($segment['kind']) ? sanitize_key((string) $segment['kind']) : self::get_map_segment_kind($highway);

            $normalized_geometry[] = [
                'points' => $normalized_points,
                'highway' => $highway,
                'kind' => 'pedestrian' === $kind ? 'pedestrian' : 'road',
            ];
        }

        return $normalized_geometry;
    }

    /**
     * Returns the one map-highlight policy used by the editor, frontend and PNG renderer.
     * Keeping this here prevents a preview from promising a different result than the image.
     */
    public static function get_street_highlight_settings() {
        $mode = sanitize_key((string) get_option('feu_einsatz_street_highlight_mode', 'full'));

        if (!in_array($mode, ['full', 'length', 'radius'], true)) {
            $mode = 'full';
        }

        return [
            'mode' => $mode,
            'length_meters' => max(20, min(5000, absint(get_option('feu_einsatz_street_highlight_length_meters', 100)) ?: 100)),
            'radius_meters' => max(20, min(5000, absint(get_option('feu_einsatz_street_highlight_radius_meters', 100)) ?: 100)),
            'include_pedestrian' => 1 === (int) get_option('feu_einsatz_street_highlight_include_pedestrian', 1),
        ];
    }

    /**
     * Returns the effective highlight policy for one report.  An override is
     * intentionally opt-in: reports created before this setting retain the
     * global map behaviour byte-for-byte.
     */
    public static function get_report_street_highlight_settings($post_id = 0) {
        $settings = self::get_street_highlight_settings();
        $post_id = absint($post_id);
        $override = $post_id ? sanitize_key((string) get_post_meta($post_id, self::MAP_HIGHLIGHT_OVERRIDE_META, true)) : 'default';

        if (!in_array($override, ['full', 'length', 'radius'], true)) {
            $settings['override_mode'] = 'default';
            return $settings;
        }

        $settings['override_mode'] = $override;
        $settings['mode'] = $override;

        if ('length' === $override) {
            $value = absint(get_post_meta($post_id, self::MAP_HIGHLIGHT_LENGTH_META, true));
            if ($value > 0) {
                $settings['length_meters'] = max(20, min(5000, $value));
            }
        }

        if ('radius' === $override) {
            $value = absint(get_post_meta($post_id, self::MAP_HIGHLIGHT_RADIUS_META, true));
            if ($value > 0) {
                $settings['radius_meters'] = max(20, min(5000, $value));
            }
        }

        return $settings;
    }

    /**
     * Manual coordinates describe the incident itself and must never be
     * replaced by an address lookup in background map jobs.
     */
    public static function get_report_map_location_mode($post_id = 0) {
        return 'coordinates' === sanitize_key((string) get_post_meta(absint($post_id), self::MAP_LOCATION_MODE_META, true))
            ? 'coordinates'
            : 'address';
    }

    /**
     * Public map accuracy is deliberately separate from cookie consent. It
     * controls the incident data itself and never changes the editor view.
     */
    public static function normalize_report_map_public_precision($value) {
        $precision = sanitize_key((string) $value);

        return in_array($precision, ['exact', 'approx_100', 'approx_500', 'hidden'], true)
            ? $precision
            : 'exact';
    }

    public static function get_report_map_public_precision($post_id = 0) {
        return self::normalize_report_map_public_precision(
            get_post_meta(absint($post_id), self::MAP_PUBLIC_PRECISION_META, true)
        );
    }

    /**
     * Normalizes one additional street per line. Keeping this as a small,
     * bounded list prevents a report editor from accidentally turning one
     * preview into an unbounded series of OSM lookups.
     */
    public static function normalize_report_map_extra_streets($values) {
        $values = is_array($values) ? $values : preg_split('/[\r\n,;]+/', (string) $values);
        $streets = [];

        foreach ((array) $values as $street) {
            $street = self::strip_house_number_from_street(sanitize_text_field((string) $street));
            $key = strtolower(remove_accents($street));

            if ('' === $street || isset($streets[$key])) {
                continue;
            }

            $streets[$key] = $street;

            if (count($streets) >= 5) {
                break;
            }
        }

        return array_values($streets);
    }

    public static function get_report_map_extra_streets($post_id = 0) {
        return self::normalize_report_map_extra_streets(
            get_post_meta(absint($post_id), self::MAP_EXTRA_STREETS_META, true)
        );
    }

    /**
     * Accept only a simple Polygon ring. It is enough for an incident area,
     * can be rendered by Leaflet, SVG and GD, and keeps stored post meta
     * predictable. GeoJSON order is [longitude, latitude].
     */
    public static function normalize_report_map_area_geojson($value) {
        if (is_string($value)) {
            $value = json_decode(wp_unslash($value), true);
        }

        if (!is_array($value)) {
            return [];
        }

        if (isset($value['type'], $value['coordinates']) && 'Polygon' === (string) $value['type']) {
            $value = $value['coordinates'][0] ?? [];
        }

        $points = [];
        foreach ((array) $value as $point) {
            if (!is_array($point) || !isset($point[0], $point[1]) || !is_numeric($point[0]) || !is_numeric($point[1])) {
                continue;
            }

            $longitude = round((float) $point[0], 6);
            $latitude = round((float) $point[1], 6);
            if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                continue;
            }

            $points[] = [$longitude, $latitude];
            if (count($points) >= 40) {
                break;
            }
        }

        if (count($points) < 3) {
            return [];
        }

        $first = $points[0];
        $last = $points[count($points) - 1];
        if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
            $points[] = $first;
        }

        return [
            'type' => 'Polygon',
            'coordinates' => [$points],
        ];
    }

    public static function get_report_map_area_geojson($post_id = 0) {
        return self::normalize_report_map_area_geojson(
            get_post_meta(absint($post_id), self::MAP_AREA_GEOJSON_META, true)
        );
    }

    public static function get_report_map_area_points($post_id = 0) {
        $area = self::get_report_map_area_geojson($post_id);
        $ring = $area['coordinates'][0] ?? [];
        $points = [];

        foreach ((array) $ring as $point) {
            if (isset($point[0], $point[1]) && is_numeric($point[0]) && is_numeric($point[1])) {
                $points[] = ['lat' => (float) $point[1], 'lng' => (float) $point[0]];
            }
        }

        return $points;
    }

    public static function get_report_map_public_coordinates($post_id, $latitude, $longitude) {
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return false;
        }

        $precision = self::get_report_map_public_precision($post_id);
        if ('hidden' === $precision) {
            return false;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;
        $meters = 'approx_500' === $precision ? 500 : ('approx_100' === $precision ? 100 : 0);

        if ($meters < 1) {
            return ['lat' => $latitude, 'lng' => $longitude, 'precision' => $precision, 'meters' => 0];
        }

        $latitude_step = $meters / 111320;
        $longitude_step = $meters / max(1, 111320 * cos(deg2rad($latitude)));

        return [
            'lat' => round(round($latitude / $latitude_step) * $latitude_step, 6),
            'lng' => round(round($longitude / $longitude_step) * $longitude_step, 6),
            'precision' => $precision,
            'meters' => $meters,
        ];
    }

    /**
     * A limited street segment and a radius are meaningful only around the
     * incident address - never around the centre of the entire street.
     *
     * The key records which street, house number, postcode and city the saved
     * coordinates belong to. Older reports have no key and are repaired once
     * when either limited highlighting mode is used.
     */
    public static function get_report_address_coordinates_key($street, $house_number = '', $plz = '', $city = 'Hamburg') {
        $street = self::strip_house_number_from_street($street);
        $house_number = trim((string) preg_replace('/\s+/', ' ', (string) $house_number));
        $plz = preg_replace('/\D+/', '', (string) $plz);
        $city = sanitize_title(remove_accents(trim((string) $city)));

        if ('' === $city) {
            $city = 'hamburg';
        }

        return hash('sha256', wp_json_encode([
            'street' => sanitize_title(remove_accents($street)),
            'house_number' => strtolower($house_number),
            'postcode' => $plz,
            'city' => $city,
        ]));
    }

    public static function mark_report_address_coordinates($post_id, $street, $house_number = '', $plz = '', $city = 'Hamburg') {
        $post_id = absint($post_id);

        if (!$post_id) {
            return;
        }

        update_post_meta(
            $post_id,
            self::ADDRESS_COORDINATES_META,
            self::get_report_address_coordinates_key($street, $house_number, $plz, $city)
        );
    }

    /**
     * Returns true only for the modes which must be centred on a house number.
     * Full-street rendering deliberately keeps its established resolver intact.
     */
    private static function uses_precise_incident_anchor($post_id = 0) {
        return 'address' === self::get_report_map_location_mode($post_id)
            && in_array(self::get_report_street_highlight_settings($post_id)['mode'], ['length', 'radius'], true);
    }

    public static function report_needs_precise_incident_anchor($post_id, $street, $plz = '', $city = 'Hamburg') {
        $post_id = absint($post_id);
        $house_number = trim((string) get_post_meta($post_id, '_feu_einsatz_hausnummer', true));

        if (!$post_id || 'coordinates' === self::get_report_map_location_mode($post_id) || '' === trim((string) $street) || '' === $house_number || !self::uses_precise_incident_anchor($post_id)) {
            return false;
        }

        $expected_key = self::get_report_address_coordinates_key($street, $house_number, $plz, $city);
        $saved_key = (string) get_post_meta($post_id, self::ADDRESS_COORDINATES_META, true);
        $latitude = get_post_meta($post_id, '_feu_einsatz_latitude', true);
        $longitude = get_post_meta($post_id, '_feu_einsatz_longitude', true);

        return $expected_key !== $saved_key || !is_numeric($latitude) || !is_numeric($longitude);
    }

    private static function normalize_house_number_for_match($value) {
        return strtolower((string) preg_replace('/\s+/', '', trim((string) $value)));
    }

    /**
     * Resolves coordinates for the real event address. A result is only allowed
     * to replace an existing anchor for a house number if Nominatim confirms the
     * same house number in its address details.
     */
    public static function get_report_incident_coordinates($post_id, $street, $plz = '', $city = 'Hamburg', $require_precise_anchor = false) {
        $post_id = absint($post_id);
        $house_number = trim((string) get_post_meta($post_id, '_feu_einsatz_hausnummer', true));
        $latitude = get_post_meta($post_id, '_feu_einsatz_latitude', true);
        $longitude = get_post_meta($post_id, '_feu_einsatz_longitude', true);
        $stored_coordinates = is_numeric($latitude) && is_numeric($longitude)
            ? ['lat' => (float) $latitude, 'lng' => (float) $longitude]
            : false;

        if ('coordinates' === self::get_report_map_location_mode($post_id)) {
            return $stored_coordinates;
        }
        $needs_exact_lookup = !$stored_coordinates
            || ($require_precise_anchor && self::report_needs_precise_incident_anchor($post_id, $street, $plz, $city));

        if (!$needs_exact_lookup) {
            return $stored_coordinates;
        }

        $geocoded_data = self::request_geocoded_address_data($street, $plz, $city, $house_number);

        if (!$geocoded_data) {
            return $stored_coordinates;
        }

        $requested_house_number = self::normalize_house_number_for_match($house_number);
        $resolved_house_number = self::normalize_house_number_for_match($geocoded_data['house_number'] ?? '');
        $is_verified_house = '' === $requested_house_number || $requested_house_number === $resolved_house_number;

        if (!$is_verified_house) {
            return $stored_coordinates;
        }

        update_post_meta($post_id, '_feu_einsatz_latitude', (string) $geocoded_data['lat']);
        update_post_meta($post_id, '_feu_einsatz_longitude', (string) $geocoded_data['lng']);

        if (!empty($geocoded_data['display_name'])) {
            update_post_meta($post_id, '_feu_einsatz_display_address', $geocoded_data['display_name']);
        }

        self::mark_report_address_coordinates($post_id, $street, $house_number, $plz, $city);

        return [
            'lat' => (float) $geocoded_data['lat'],
            'lng' => (float) $geocoded_data['lng'],
        ];
    }

    private static function map_distance_meters(array $a, array $b) {
        $earth_radius = 6371000;
        $lat_delta = deg2rad($b['lat'] - $a['lat']);
        $lng_delta = deg2rad($b['lng'] - $a['lng']);
        $h = sin($lat_delta / 2) * sin($lat_delta / 2)
            + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin($lng_delta / 2) * sin($lng_delta / 2);

        return 2 * $earth_radius * atan2(sqrt($h), sqrt(max(0, 1 - $h)));
    }

    private static function closest_point_on_map_segment(array $point, array $a, array $b) {
        $latitude_scale = 111320;
        $longitude_scale = max(1, $latitude_scale * cos(deg2rad($point['lat'])));
        $ax = ($a['lng'] - $point['lng']) * $longitude_scale;
        $ay = ($a['lat'] - $point['lat']) * $latitude_scale;
        $bx = ($b['lng'] - $point['lng']) * $longitude_scale;
        $by = ($b['lat'] - $point['lat']) * $latitude_scale;
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $denominator = ($dx * $dx) + ($dy * $dy);
        $ratio = $denominator > 0 ? max(0, min(1, - (($ax * $dx) + ($ay * $dy)) / $denominator)) : 0;

        return [
            'ratio' => $ratio,
            'point' => [
                'lat' => $a['lat'] + (($b['lat'] - $a['lat']) * $ratio),
                'lng' => $a['lng'] + (($b['lng'] - $a['lng']) * $ratio),
            ],
        ];
    }

    /**
     * A city-wide street record can be complete in total but still miss the
     * branch nearest to a particular house. Limited modes must detect that
     * situation before cropping, otherwise their marker appears at a remote
     * (often visually central) part of the street.
     */
    private static function get_geometry_distance_to_incident($geometry, array $origin) {
        $closest_distance = null;

        foreach (self::normalize_map_geometry($geometry) as $line) {
            foreach ($line['points'] as $index => $point) {
                if (!isset($line['points'][$index + 1])) {
                    continue;
                }

                $candidate = self::closest_point_on_map_segment($origin, $point, $line['points'][$index + 1]);
                $distance = self::map_distance_meters($origin, $candidate['point']);

                if (null === $closest_distance || $distance < $closest_distance) {
                    $closest_distance = $distance;
                }
            }
        }

        return $closest_distance;
    }

    private static function needs_incident_anchor_geometry($geometry, array $coordinates, $post_id = 0) {
        if (!self::uses_precise_incident_anchor($post_id)) {
            return false;
        }

        $distance = self::get_geometry_distance_to_incident($geometry, $coordinates);

        // An address can legitimately sit several metres away from a road
        // centreline. Above 150 m, however, crop/radius would describe a
        // different part of the street and must be enriched around the house.
        return null === $distance || $distance > 150;
    }

    private static function interpolate_map_point(array $a, array $b, $ratio) {
        $ratio = max(0, min(1, (float) $ratio));

        return [
            'lat' => $a['lat'] + (($b['lat'] - $a['lat']) * $ratio),
            'lng' => $a['lng'] + (($b['lng'] - $a['lng']) * $ratio),
        ];
    }

    /**
     * Finds the point at which a real map segment enters or leaves a radius.
     * A binary search is used instead of drawing a straight substitute route.
     */
    private static function get_map_radius_boundary_point(array $origin, array $a, array $b, $radius, $a_is_inside) {
        $low = 0.0;
        $high = 1.0;

        for ($iteration = 0; $iteration < 20; $iteration++) {
            $middle = ($low + $high) / 2;
            $candidate = self::interpolate_map_point($a, $b, $middle);
            $is_inside = self::map_distance_meters($origin, $candidate) <= $radius;

            if ($is_inside === $a_is_inside) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }

        return self::interpolate_map_point($a, $b, ($low + $high) / 2);
    }

    /**
     * Returns real polyline fragments that fall inside the configured radius.
     * In particular, this retains a line that crosses the circle even if its
     * OSM nodes themselves happen to sit just outside the circle.
     */
    private static function clip_map_line_to_radius(array $line, array $origin, $radius) {
        $points = isset($line['points']) && is_array($line['points']) ? $line['points'] : [];

        if (count($points) < 2) {
            return [];
        }

        $fragments = [];
        $current = [];

        for ($index = 0, $count = count($points) - 1; $index < $count; $index++) {
            $a = $points[$index];
            $b = $points[$index + 1];
            $a_inside = self::map_distance_meters($origin, $a) <= $radius;
            $b_inside = self::map_distance_meters($origin, $b) <= $radius;

            if ($a_inside && $b_inside) {
                if (empty($current)) {
                    $current[] = $a;
                }
                $current[] = $b;
                continue;
            }

            if ($a_inside) {
                if (empty($current)) {
                    $current[] = $a;
                }
                $current[] = self::get_map_radius_boundary_point($origin, $a, $b, $radius, true);
                if (count($current) > 1) {
                    $fragment = $line;
                    $fragment['points'] = self::normalize_segment_points($current);
                    if (count($fragment['points']) > 1) {
                        $fragments[] = $fragment;
                    }
                }
                $current = [];
                continue;
            }

            if ($b_inside) {
                $current = [self::get_map_radius_boundary_point($origin, $a, $b, $radius, false), $b];
                continue;
            }

            // Both endpoints can be outside while a curved/long OSM segment
            // still crosses the radius. Keep precisely that interior part.
            $nearest = self::closest_point_on_map_segment($origin, $a, $b);
            if (self::map_distance_meters($origin, $nearest['point']) < $radius) {
                $entry = self::get_map_radius_boundary_point($origin, $a, $nearest['point'], $radius, false);
                $exit = self::get_map_radius_boundary_point($origin, $nearest['point'], $b, $radius, true);
                $fragment = $line;
                $fragment['points'] = self::normalize_segment_points([$entry, $exit]);
                if (count($fragment['points']) > 1) {
                    $fragments[] = $fragment;
                }
            }
        }

        if (count($current) > 1) {
            $fragment = $line;
            $fragment['points'] = self::normalize_segment_points($current);
            if (count($fragment['points']) > 1) {
                $fragments[] = $fragment;
            }
        }

        return $fragments;
    }

    /**
     * Crops a complete polyline around the nearest point, consuming distance
     * on both sides of the address. The former implementation looked only at
     * the one OSM edge under the address, so a 100 m setting could collapse to
     * a few metres whenever that edge had many shape points.
     */
    private static function crop_map_line_around_point(array $line, $segment_index, $ratio, $requested_length) {
        $points = isset($line['points']) && is_array($line['points']) ? $line['points'] : [];

        if (!isset($points[$segment_index], $points[$segment_index + 1])) {
            return $line;
        }

        $anchor = self::interpolate_map_point($points[$segment_index], $points[$segment_index + 1], $ratio);
        $remaining = max(10, (float) $requested_length / 2);
        $backward = [$anchor];
        $current = $anchor;

        for ($index = $segment_index; $index >= 0 && $remaining > 0; $index--) {
            $target = $points[$index];
            $distance = self::map_distance_meters($current, $target);

            if ($distance <= $remaining) {
                $backward[] = $target;
                $remaining -= $distance;
                $current = $target;
                continue;
            }

            $backward[] = self::interpolate_map_point($current, $target, $remaining / max(1, $distance));
            break;
        }

        $remaining = max(10, (float) $requested_length / 2);
        $forward = [$anchor];
        $current = $anchor;

        for ($index = $segment_index + 1, $count = count($points); $index < $count && $remaining > 0; $index++) {
            $target = $points[$index];
            $distance = self::map_distance_meters($current, $target);

            if ($distance <= $remaining) {
                $forward[] = $target;
                $remaining -= $distance;
                $current = $target;
                continue;
            }

            $forward[] = self::interpolate_map_point($current, $target, $remaining / max(1, $distance));
            break;
        }

        $line['points'] = self::normalize_segment_points(array_merge(array_reverse($backward), array_slice($forward, 1)));

        return $line;
    }

    /**
     * Crops the segment nearest to the address. If it is shorter than the requested
     * length it is intentionally retained whole: inventing a straight extension would
     * put an incident on a street where no geometry exists.
     *
     * Coordinate-format invariant: normalize_map_geometry() yields associative
     * lat/lng points. Any crop or filter added here must preserve that format (or
     * normalize it explicitly); accepting only numeric [lat, lng] pairs makes the
     * "length" mode silently return an empty street. Radius is deliberately a
     * circle-only presentation and never returns a highlighted street line.
     */
    public static function apply_street_highlight_mode($geometry, $latitude, $longitude, $settings = null) {
        $settings = is_array($settings) ? array_merge(self::get_street_highlight_settings(), $settings) : self::get_street_highlight_settings();
        $mode = in_array($settings['mode'], ['full', 'length', 'radius'], true) ? $settings['mode'] : 'full';

        // A radius describes an area around the incident point, not an
        // additionally highlighted road. Returning no line here is the single
        // source of truth for public maps, editor previews and generated images.
        if ('radius' === $mode) {
            return [
                'geometry' => [],
                'mode' => 'radius',
                'radius_meters' => is_numeric($latitude) && is_numeric($longitude)
                    ? max(20, min(5000, absint($settings['radius_meters'])))
                    : 0,
            ];
        }

        $lines = self::normalize_map_geometry($geometry);
        if (empty($settings['include_pedestrian'])) {
            $lines = array_values(array_filter($lines, static function($line) {
                return 'pedestrian' !== ($line['kind'] ?? 'road');
            }));
        }

        if (empty($lines) || !is_numeric($latitude) || !is_numeric($longitude) || 'full' === $mode) {
            // A coordinate is enough to show the radius circle. This is
            // important while a report is still fetching its street geometry:
            // the map must not degrade to the "not available" placeholder.
            return ['geometry' => $lines, 'mode' => $mode, 'radius_meters' => 0];
        }

        $origin = ['lat' => (float) $latitude, 'lng' => (float) $longitude];

        $closest = null;
        foreach ($lines as $line_index => $line) {
            foreach ($line['points'] as $point_index => $point) {
                if (!isset($line['points'][$point_index + 1])) {
                    continue;
                }
                $candidate = self::closest_point_on_map_segment($origin, $point, $line['points'][$point_index + 1]);
                $distance = self::map_distance_meters($origin, $candidate['point']);
                if (null === $closest || $distance < $closest['distance']) {
                    $closest = ['line' => $line_index, 'segment' => $point_index, 'ratio' => $candidate['ratio'], 'distance' => $distance];
                }
            }
        }

        if (null === $closest) {
            return ['geometry' => $lines, 'mode' => 'length', 'radius_meters' => 0];
        }

        $requested_length = max(20, min(5000, absint($settings['length_meters'])));
        $line = self::crop_map_line_around_point(
            $lines[$closest['line']],
            $closest['segment'],
            $closest['ratio'],
            $requested_length
        );

        return ['geometry' => [$line], 'mode' => 'length', 'radius_meters' => 0];
    }

    public static function build_local_map_preview_markup($args = []) {
        $args = wp_parse_args($args, [
            'latitude' => null,
            'longitude' => null,
            'center' => [],
            'geometry' => [],
            'area_geometry' => [],
            'address' => '',
            'height' => 500,
            'station' => false,
            'show_station' => false,
            'line_color' => '',
            'line_width' => 0,
            'font_stack' => '',
            'heading_text' => '',
            'preview_mode' => 'full',
            'highlight_mode' => 'full',
            'highlight_radius_meters' => 0,
        ]);

        $width = 1200;
        $height = max(320, min(680, absint($args['height']) ?: 500));
        $geometry_lines = self::normalize_map_geometry($args['geometry']);
        $area_points = [];
        foreach ((array) $args['area_geometry'] as $area_point) {
            $area_lat = is_array($area_point) && isset($area_point['lat']) ? $area_point['lat'] : (is_array($area_point) ? ($area_point[0] ?? null) : null);
            $area_lng = is_array($area_point) && isset($area_point['lng']) ? $area_point['lng'] : (is_array($area_point) ? ($area_point[1] ?? null) : null);
            if (is_numeric($area_lat) && is_numeric($area_lng)) {
                $area_points[] = ['lat' => (float) $area_lat, 'lng' => (float) $area_lng];
            }
        }
        $show_station = !empty($args['show_station']);
        $station = $show_station
            ? (is_array($args['station']) ? $args['station'] : self::get_station_feature_from_settings())
            : false;
        $line_color = sanitize_hex_color((string) $args['line_color']);
        $line_color = $line_color ? strtolower($line_color) : sanitize_hex_color((string) get_option('feu_einsatz_map_preview_highlight_color', '#d92d20'));
        $line_color = $line_color ? strtolower($line_color) : '#d92d20';
        $line_width = max(3, min(18, absint($args['line_width']) ?: absint(get_option('feu_einsatz_map_preview_stroke_width', 8))));
        $pedestrian_line_width = max(2, $line_width - 3);
        $road_glow_width = $line_width + 4;
        $pedestrian_glow_width = $pedestrian_line_width + 3;
        $font_stack = trim((string) $args['font_stack']);
        if ('' === $font_stack) {
            $font_stack = '"Segoe UI", Arial, sans-serif';
        }
        $heading_text = trim(sanitize_text_field((string) $args['heading_text']));
        if ('' === $heading_text) {
            $heading_text = trim((string) get_option('feu_einsatz_map_preview_heading_text', ''));
        }
        if ('' === $heading_text) {
            $heading_text = __('Einsatzort', 'feuer-einsatzberichte');
        }
        $preview_mode = 'minimal' === $args['preview_mode'] ? 'minimal' : 'full';
        $marker = null;

        if (is_numeric($args['latitude']) && is_numeric($args['longitude'])) {
            $marker = [
                'lat' => (float) $args['latitude'],
                'lng' => (float) $args['longitude'],
            ];
        } elseif (
            is_array($args['center'])
            && isset($args['center'][0], $args['center'][1])
            && is_numeric($args['center'][0])
            && is_numeric($args['center'][1])
        ) {
            $marker = [
                'lat' => (float) $args['center'][0],
                'lng' => (float) $args['center'][1],
            ];
        }

        $all_points = [];

        foreach ($geometry_lines as $segment) {
            foreach ($segment['points'] as $point) {
                $all_points[] = $point;
            }
        }

        foreach ($area_points as $area_point) {
            $all_points[] = $area_point;
        }

        if (null !== $marker) {
            $all_points[] = $marker;

            if ('radius' === $args['highlight_mode'] && absint($args['highlight_radius_meters']) > 0) {
                $radius_degrees = absint($args['highlight_radius_meters']) / 111320;
                $radius_longitude_degrees = absint($args['highlight_radius_meters']) / max(1, 111320 * cos(deg2rad($marker['lat'])));
                $all_points[] = ['lat' => $marker['lat'] + $radius_degrees, 'lng' => $marker['lng']];
                $all_points[] = ['lat' => $marker['lat'] - $radius_degrees, 'lng' => $marker['lng']];
                $all_points[] = ['lat' => $marker['lat'], 'lng' => $marker['lng'] + $radius_longitude_degrees];
                $all_points[] = ['lat' => $marker['lat'], 'lng' => $marker['lng'] - $radius_longitude_degrees];
            }
        }

        if (
            is_array($station)
            && isset($station['latitude'], $station['longitude'])
            && is_numeric($station['latitude'])
            && is_numeric($station['longitude'])
        ) {
            $all_points[] = [
                'lat' => (float) $station['latitude'],
                'lng' => (float) $station['longitude'],
            ];
        }

        if (empty($all_points)) {
            return '';
        }

        $min_lat = min(array_column($all_points, 'lat'));
        $max_lat = max(array_column($all_points, 'lat'));
        $min_lng = min(array_column($all_points, 'lng'));
        $max_lng = max(array_column($all_points, 'lng'));
        $lat_range = max($max_lat - $min_lat, 0.008);
        $lng_range = max($max_lng - $min_lng, 0.008);
        $min_lat -= max(0.0025, $lat_range * 0.16);
        $max_lat += max(0.0025, $lat_range * 0.16);
        $min_lng -= max(0.0025, $lng_range * 0.16);
        $max_lng += max(0.0025, $lng_range * 0.16);
        $lat_range = max($max_lat - $min_lat, 0.008);
        $lng_range = max($max_lng - $min_lng, 0.008);

        $project_point = static function(array $point) use ($min_lat, $max_lat, $min_lng, $lng_range, $lat_range, $width, $height) {
            $x = 76 + ((($point['lng'] - $min_lng) / $lng_range) * ($width - 152));
            $y = 76 + ((($max_lat - $point['lat']) / $lat_range) * ($height - 152));

            return [
                'x' => round($x, 2),
                'y' => round($y, 2),
            ];
        };

        $grid_markup = [];

        for ($column = 1; $column <= 5; $column++) {
            $x = round(($width / 6) * $column, 2);
            $grid_markup[] = '<line x1="' . esc_attr($x) . '" y1="0" x2="' . esc_attr($x) . '" y2="' . esc_attr($height) . '" stroke="#0f172a" stroke-opacity="0.10" stroke-width="1" />';
        }

        for ($row = 1; $row <= 4; $row++) {
            $y = round(($height / 5) * $row, 2);
            $grid_markup[] = '<line x1="0" y1="' . esc_attr($y) . '" x2="' . esc_attr($width) . '" y2="' . esc_attr($y) . '" stroke="#0f172a" stroke-opacity="0.10" stroke-width="1" />';
        }

        $context_markup = [];

        if ('minimal' === $preview_mode) {
            $context_roads = [
                ['path' => 'M 124 214 L 1088 214', 'halo' => 10, 'stroke' => 5],
                ['path' => 'M 188 468 Q 438 438 700 462 T 1048 432', 'halo' => 10, 'stroke' => 5],
                ['path' => 'M 944 96 L 944 560', 'halo' => 10, 'stroke' => 5],
            ];

            foreach ($context_roads as $road_definition) {
                $road_path = isset($road_definition['path']) ? (string) $road_definition['path'] : '';
                $halo_width = isset($road_definition['halo']) ? max(6, (int) $road_definition['halo']) : 10;
                $stroke_width = isset($road_definition['stroke']) ? max(3, (int) $road_definition['stroke']) : 5;

                if ('' === $road_path) {
                    continue;
                }

                $context_markup[] = '<path d="' . esc_attr($road_path) . '" fill="none" stroke="#ffffff" stroke-width="' . esc_attr((string) $halo_width) . '" stroke-linecap="round" stroke-linejoin="round" stroke-opacity="0.82" />';
                $context_markup[] = '<path d="' . esc_attr($road_path) . '" fill="none" stroke="#d4dfeb" stroke-width="' . esc_attr((string) $stroke_width) . '" stroke-linecap="round" stroke-linejoin="round" stroke-opacity="0.96" />';
            }
        }

        $street_halo_markup = [];
        $street_line_markup = [];
        $area_markup = '';
        $radius_markup = '';

        if (count($area_points) >= 3) {
            $polygon_points = [];
            foreach ($area_points as $area_point) {
                $projected = $project_point($area_point);
                $polygon_points[] = $projected['x'] . ',' . $projected['y'];
            }
            $area_markup = '<polygon points="' . esc_attr(implode(' ', $polygon_points)) . '" fill="' . esc_attr($line_color) . '" fill-opacity="0.16" stroke="' . esc_attr($line_color) . '" stroke-width="3" stroke-opacity="0.94" stroke-linejoin="round" />';
        }

        if (null !== $marker && 'radius' === $args['highlight_mode'] && absint($args['highlight_radius_meters']) > 0) {
            $marker_screen = $project_point($marker);
            $radius_meters = absint($args['highlight_radius_meters']);
            $edge_screen_y = $project_point(['lat' => $marker['lat'] + ($radius_meters / 111320), 'lng' => $marker['lng']]);
            $edge_screen_x = $project_point(['lat' => $marker['lat'], 'lng' => $marker['lng'] + ($radius_meters / max(1, 111320 * cos(deg2rad($marker['lat']))))]);
            $radius_px_y = max(8, abs($edge_screen_y['y'] - $marker_screen['y']));
            $radius_px_x = max(8, abs($edge_screen_x['x'] - $marker_screen['x']));
            $radius_markup = '<ellipse cx="' . esc_attr($marker_screen['x']) . '" cy="' . esc_attr($marker_screen['y']) . '" rx="' . esc_attr($radius_px_x) . '" ry="' . esc_attr($radius_px_y) . '" fill="' . esc_attr($line_color) . '" fill-opacity="0.18" stroke="' . esc_attr($line_color) . '" stroke-width="3" stroke-opacity="0.92" />';
        }

        foreach ($geometry_lines as $segment) {
            $commands = [];

            foreach ($segment['points'] as $index => $point) {
                $projected = $project_point($point);
                $commands[] = (0 === $index ? 'M' : 'L') . $projected['x'] . ' ' . $projected['y'];
            }

            if (!empty($commands)) {
                $path = esc_attr(implode(' ', $commands));
                $is_pedestrian = 'pedestrian' === $segment['kind'];
                $street_halo_markup[] = '<path d="' . $path . '" fill="none" stroke="#ffffff" stroke-width="' . ($is_pedestrian ? $pedestrian_glow_width : $road_glow_width) . '" stroke-linecap="round" stroke-linejoin="round" stroke-opacity="0.72" vector-effect="non-scaling-stroke" />';
                $street_line_markup[] = '<path d="' . $path . '" fill="none" stroke="' . ($is_pedestrian ? '#d97706' : esc_attr($line_color)) . '" stroke-width="' . ($is_pedestrian ? $pedestrian_line_width : $line_width) . '" stroke-linecap="round" stroke-linejoin="round" stroke-opacity="0.96" vector-effect="non-scaling-stroke"' . ($is_pedestrian ? ' stroke-dasharray="18 12"' : '') . ' />';
            }
        }

        $coordinates_label = '';

        if (null !== $marker) {
            $coordinates_label = number_format($marker['lat'], 6, '.', '') . ', ' . number_format($marker['lng'], 6, '.', '');
        }

        $address_label = trim(sanitize_text_field((string) $args['address']));
        if ('' === $address_label && !empty($station['address'])) {
            $address_label = trim((string) $station['address']);
        }
        $address_label = '' !== $address_label
            ? wp_html_excerpt($address_label, 68, '...')
            : __('Lokale Einsatzkarte', 'feuer-einsatzberichte');

        $pattern_id = wp_unique_id('feu-einsatz-map-pattern-');
        $title = esc_html($heading_text);
        $card_height = '' !== $coordinates_label ? 88 : 64;
        $footer_markup = '';
        $attribution_markup = '<rect x="' . esc_attr($width - 418) . '" y="' . esc_attr($height - 52) . '" width="392" height="28" rx="14" fill="#ffffff" fill-opacity="0.92" stroke="#0f172a" stroke-opacity="0.14" />' .
            '<text x="' . esc_attr($width - 44) . '" y="' . esc_attr($height - 31) . '" fill="#334155" font-size="16" font-weight="700" text-anchor="end">Leaflet | &copy; OpenStreetMap contributors</text>';
        $station_markup = '';
        $marker_markup = '';
        $minimal_badge_markup = '';
        $minimal_panel_markup = '';
        $minimal_attribution_markup = '';
        $minimal_panel_width = max(320, min(420, (int) round($width * 0.34)));

        if ('full' !== $preview_mode) {
            $attribution_markup = '';
        }

        if ('full' === $preview_mode && '' !== $coordinates_label) {
            $footer_y = $height - 58;
            $footer_markup =
                '<rect x="36" y="' . esc_attr($footer_y) . '" width="286" height="42" rx="16" fill="#ffffff" fill-opacity="0.92" stroke="#0f172a" stroke-opacity="0.12" />' .
                '<text x="58" y="' . esc_attr($footer_y + 26) . '" fill="#0a4b78" font-size="18" font-weight="600">' . esc_html($coordinates_label) . '</text>';
        }

        if (null !== $marker) {
            $projected_marker = $project_point($marker);
            $outer_radius = 'minimal' === $preview_mode ? 15 : 18;
            $inner_radius = 'minimal' === $preview_mode ? 7 : 8;
            $marker_markup =
                '<circle cx="' . esc_attr($projected_marker['x']) . '" cy="' . esc_attr($projected_marker['y']) . '" r="' . esc_attr((string) $outer_radius) . '" fill="#ffffff" fill-opacity="0.92" stroke="#0f172a" stroke-opacity="0.16" stroke-width="2" />' .
                '<circle cx="' . esc_attr($projected_marker['x']) . '" cy="' . esc_attr($projected_marker['y']) . '" r="' . esc_attr((string) $inner_radius) . '" fill="' . esc_attr($line_color) . '" />';

            if ('minimal' === $preview_mode) {
                $label_prefix = trim((string) get_option('feu_einsatz_map_preview_street_label_prefix', 'Einsatz Strasse'));
                if ('' === $label_prefix) {
                    $label_prefix = __('Einsatz Strasse', 'feuer-einsatzberichte');
                }
                $label_enabled = 1 === (int) get_option('feu_einsatz_map_preview_show_street_label', 1);
                $street_label = trim((string) preg_replace('/,\s*Deutschland$/i', '', $address_label));
                $street_label_parts = explode(',', $street_label);
                $street_label = trim((string) $street_label_parts[0]);
                $street_label = trim((string) preg_replace('/\s+\d+[a-zA-Z\-\/]*\s*$/', '', $street_label));
                $label_left = max(24, min($width - 324, (int) round($projected_marker['x'] + 20)));
                $label_top = max(24, min($height - 124, (int) round($projected_marker['y'] - 68)));

                if ($label_enabled && '' !== $street_label) {
                    $minimal_badge_markup =
                        '<div class="feu-einsatz-map-inline-preview-badge" style="left:' . esc_attr((string) $label_left) . 'px;top:' . esc_attr((string) $label_top) . 'px;">' .
                            esc_html($label_prefix . ': ' . $street_label) .
                        '</div>';
                }

                $minimal_panel_markup =
                    '<div class="feu-einsatz-map-inline-preview-panel" style="width:' . esc_attr((string) $minimal_panel_width) . 'px;">' .
                        '<strong>' . esc_html($title) . '</strong>' .
                        '<span>' . esc_html($address_label) . '</span>' .
                    '</div>';

                $minimal_attribution_markup =
                    '<div class="feu-einsatz-map-inline-preview-attribution">Leaflet | &copy; OpenStreetMap contributors</div>';
            }
        }

        if (
            is_array($station)
            && isset($station['latitude'], $station['longitude'])
            && is_numeric($station['latitude'])
            && is_numeric($station['longitude'])
        ) {
            $station_point = $project_point([
                'lat' => (float) $station['latitude'],
                'lng' => (float) $station['longitude'],
            ]);
            $station_label = !empty($station['label']) ? (string) $station['label'] : __('Feuerwehrhaus', 'feuer-einsatzberichte');
            $station_label = wp_html_excerpt(trim($station_label), 28, '...');
            $station_bubble_width = max(170, min(280, 68 + (strlen(remove_accents($station_label)) * 8)));
            $station_bubble_left = min($width - $station_bubble_width - 32, max(32, $station_point['x'] + 22));
            $station_bubble_top = max(88, min($height - 214, $station_point['y'] - 62));
            $station_bubble_right = $station_bubble_left + $station_bubble_width;
            $station_bubble_mid_y = $station_bubble_top + 19;
            $station_logo_url = !empty($station['logo_url']) ? esc_url((string) $station['logo_url']) : '';
            $station_logo_size = max(26, min(54, absint($station['logo_size'] ?? 40)));
            $station_icon_outer = $station_logo_size + 18;
            $station_icon_left = $station_point['x'] - ($station_icon_outer / 2);
            $station_icon_top = $station_point['y'] - ($station_icon_outer / 2);
            $station_icon_markup = '';

            if ('' !== $station_logo_url) {
                $station_logo_padding = 7;
                $clip_id = wp_unique_id('feu-station-logo-');
                $logo_left = $station_icon_left + $station_logo_padding;
                $logo_top = $station_icon_top + $station_logo_padding;
                $logo_box_size = $station_icon_outer - ($station_logo_padding * 2);
                $station_icon_markup =
                    '<defs><clipPath id="' . esc_attr($clip_id) . '"><rect x="' . esc_attr($logo_left) . '" y="' . esc_attr($logo_top) . '" width="' . esc_attr($logo_box_size) . '" height="' . esc_attr($logo_box_size) . '" rx="' . esc_attr(max(8, (int) round($logo_box_size / 4))) . '" /></clipPath></defs>' .
                    '<image x="' . esc_attr($logo_left) . '" y="' . esc_attr($logo_top) . '" width="' . esc_attr($logo_box_size) . '" height="' . esc_attr($logo_box_size) . '" href="' . $station_logo_url . '" preserveAspectRatio="xMidYMid meet" clip-path="url(#' . esc_attr($clip_id) . ')" />';
            } else {
                $station_icon_markup =
                    '<circle cx="' . esc_attr($station_point['x']) . '" cy="' . esc_attr($station_point['y']) . '" r="18" fill="#ffffff" fill-opacity="0.92" stroke="#0f172a" stroke-opacity="0.16" stroke-width="2" />' .
                    '<circle cx="' . esc_attr($station_point['x']) . '" cy="' . esc_attr($station_point['y']) . '" r="9" fill="#0a4b78" />';
            }

            $station_markup =
                '<line x1="' . esc_attr($station_point['x']) . '" y1="' . esc_attr($station_point['y']) . '" x2="' . esc_attr($station_bubble_left) . '" y2="' . esc_attr($station_bubble_mid_y) . '" stroke="#ffffff" stroke-opacity="0.82" stroke-width="3" />' .
                $station_icon_markup .
                '<rect x="' . esc_attr($station_bubble_left) . '" y="' . esc_attr($station_bubble_top) . '" width="' . esc_attr($station_bubble_width) . '" height="38" rx="19" fill="#ffffff" fill-opacity="0.94" stroke="#0f172a" stroke-opacity="0.12" />' .
                '<text x="' . esc_attr($station_bubble_left + 18) . '" y="' . esc_attr($station_bubble_top + 24) . '" fill="#0f172a" font-size="17" font-weight="700">' . esc_html($station_label) . '</text>';
        }

        return
            '<div class="feu-einsatz-map-inline-preview" style="height:' . esc_attr($height) . 'px;">' .
                '<svg viewBox="0 0 ' . esc_attr($width) . ' ' . esc_attr($height) . '" role="img" aria-label="' . esc_attr($title . ': ' . $address_label) . '" preserveAspectRatio="none">' .
                    '<defs>' .
                        '<pattern id="' . esc_attr($pattern_id) . '" width="32" height="32" patternUnits="userSpaceOnUse">' .
                            '<path d="M 32 0 L 0 0 0 32" fill="none" stroke="#0f172a" stroke-opacity="0.05" stroke-width="1" />' .
                        '</pattern>' .
                    '</defs>' .
                    '<rect x="0" y="0" width="' . esc_attr($width) . '" height="' . esc_attr($height) . '" fill="#f8fbff" />' .
                    '<rect x="0" y="0" width="' . esc_attr($width) . '" height="' . esc_attr($height) . '" fill="url(#' . esc_attr($pattern_id) . ')" />' .
                    implode('', $grid_markup) .
                    implode('', $context_markup) .
                    $area_markup .
                    $radius_markup .
                    ('full' === $preview_mode
                        ? '<rect x="30" y="30" width="480" height="' . esc_attr($card_height) . '" rx="20" fill="#ffffff" fill-opacity="0.92" stroke="#0f172a" stroke-opacity="0.10" />' .
                            '<text x="56" y="62" fill="#0f172a" font-size="26" font-weight="700">' . esc_html($title) . '</text>' .
                            '<text x="56" y="92" fill="#475569" font-size="18">' . esc_html($address_label) . '</text>'
                        : '') .
                    implode('', $street_halo_markup) .
                    implode('', $street_line_markup) .
                    $marker_markup .
                    $station_markup .
                    $attribution_markup .
                    $footer_markup .
                '</svg>' .
                $minimal_badge_markup .
                $minimal_panel_markup .
                $minimal_attribution_markup .
            '</div>';
    }

    public static function build_openstreetmap_urls($args = []) {
        $args = wp_parse_args($args, [
            'latitude' => null,
            'longitude' => null,
            'center' => [],
            'geometry' => [],
            'zoom' => 16,
        ]);

        $marker = null;

        if (is_numeric($args['latitude']) && is_numeric($args['longitude'])) {
            $marker = [
                'lat' => (float) $args['latitude'],
                'lng' => (float) $args['longitude'],
            ];
        } elseif (
            is_array($args['center'])
            && isset($args['center'][0], $args['center'][1])
            && is_numeric($args['center'][0])
            && is_numeric($args['center'][1])
        ) {
            $marker = [
                'lat' => (float) $args['center'][0],
                'lng' => (float) $args['center'][1],
            ];
        }

        if (null === $marker) {
            return [
                'embed_url' => '',
                'external_url' => '',
            ];
        }

        $points = [$marker];

        foreach (self::normalize_map_geometry($args['geometry']) as $segment) {
            foreach ($segment['points'] as $point) {
                $points[] = $point;
            }
        }

        $min_lat = min(array_column($points, 'lat'));
        $max_lat = max(array_column($points, 'lat'));
        $min_lng = min(array_column($points, 'lng'));
        $max_lng = max(array_column($points, 'lng'));
        $lat_padding = max(0.0025, ($max_lat - $min_lat) * 0.16);
        $lng_padding = max(0.0035, ($max_lng - $min_lng) * 0.16);
        $min_lat -= $lat_padding;
        $max_lat += $lat_padding;
        $min_lng -= $lng_padding;
        $max_lng += $lng_padding;
        $zoom = max(10, min(18, absint($args['zoom']) ?: 16));
        $lat_string = number_format($marker['lat'], 6, '.', '');
        $lng_string = number_format($marker['lng'], 6, '.', '');
        $bbox_string = implode(',', [
            number_format($min_lng, 6, '.', ''),
            number_format($min_lat, 6, '.', ''),
            number_format($max_lng, 6, '.', ''),
            number_format($max_lat, 6, '.', ''),
        ]);

        return [
            'embed_url' => add_query_arg(
                [
                    'bbox' => $bbox_string,
                    'layer' => 'mapnik',
                    'marker' => $lat_string . ',' . $lng_string,
                ],
                'https://www.openstreetmap.org/export/embed.html'
            ),
            'external_url' => 'https://www.openstreetmap.org/?mlat=' . rawurlencode($lat_string)
                . '&mlon=' . rawurlencode($lng_string)
                . '#map=' . rawurlencode((string) $zoom)
                . '/' . rawurlencode($lat_string)
                . '/' . rawurlencode($lng_string),
        ];
    }

    private static function get_map_remote_user_agent() {
        $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        $email = sanitize_email((string) get_option('admin_email'));
        $details = array_filter([$host, $email], static function ($value) {
            return '' !== trim((string) $value);
        });

        if (empty($details)) {
            return 'Feuer-Einsatzberichte/' . FEU_EINSATZ_VERSION;
        }

        return 'Feuer-Einsatzberichte/' . FEU_EINSATZ_VERSION . ' (' . implode('; ', $details) . ')';
    }

    private static function get_map_remote_request_args($timeout = 15, $accept = 'application/json') {
        return [
            'timeout' => max(5, (int) $timeout),
            'redirection' => 3,
            'headers' => [
                'Accept' => $accept,
                'User-Agent' => self::get_map_remote_user_agent(),
                'Referer' => home_url('/'),
            ],
        ];
    }

    /**
     * Returns the primary public Overpass endpoint followed by bounded
     * fallbacks. A public Overpass instance can temporarily return 406/429 or
     * be under maintenance; such a service failure must not make a saved
     * short Nominatim fragment look like the complete street.
     */
    private static function get_overpass_api_urls() {
        $urls = array_merge([self::OVERPASS_API_URL], self::OVERPASS_FALLBACK_API_URLS);
        $urls = apply_filters('feu_einsatz_overpass_api_urls', $urls);

        return array_values(array_unique(array_filter(array_map('esc_url_raw', (array) $urls))));
    }

    /**
     * Performs one read-only Overpass request with failover. A valid JSON
     * response with no matching elements is still a valid result and does not
     * unnecessarily query another public service.
     */
    private static function request_overpass_payload($query, $timeout = 12) {
        $query = trim((string) $query);

        if ('' === $query) {
            return false;
        }

        foreach (self::get_overpass_api_urls() as $endpoint) {
            $response = wp_safe_remote_post(
                $endpoint,
                array_merge(
                    self::get_map_remote_request_args($timeout),
                    [
                        'body' => [
                            'data' => $query,
                        ],
                    ]
                )
            );
            $payload = self::decode_map_json_response($response);

            if (is_array($payload)) {
                return $payload;
            }
        }

        return false;
    }

    private static function decode_map_json_response($response) {
        if (is_wp_error($response)) {
            return false;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);

        if ($status_code < 200 || $status_code >= 300) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);

        if ('' === trim((string) $body)) {
            return false;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : false;
    }

    private static function build_map_geometry_segment_from_geojson_coordinates($coordinates, $reverse = true) {
        $segment = [];

        foreach ((array) $coordinates as $point) {
            if (!is_array($point) || !isset($point[0], $point[1]) || !is_numeric($point[0]) || !is_numeric($point[1])) {
                continue;
            }

            $segment[] = $reverse
                ? [(float) $point[1], (float) $point[0]]
                : [(float) $point[0], (float) $point[1]];
        }

        return count($segment) > 1 ? $segment : [];
    }

    private static function build_map_geometry_payload_from_geojson($geojson) {
        if (!is_array($geojson) || empty($geojson['type']) || empty($geojson['coordinates'])) {
            return false;
        }

        $segments = [];
        $type = (string) $geojson['type'];
        $coordinates = $geojson['coordinates'];

        if ('LineString' === $type) {
            $segment = self::build_map_geometry_segment_from_geojson_coordinates($coordinates);

            if (!empty($segment)) {
                $segments[] = self::build_geometry_segment_payload($segment, '', 'road');
            }
        } elseif ('MultiLineString' === $type) {
            foreach ((array) $coordinates as $line) {
                $segment = self::build_map_geometry_segment_from_geojson_coordinates($line);

                if (!empty($segment)) {
                    $segments[] = self::build_geometry_segment_payload($segment, '', 'road');
                }
            }
        } elseif ('Polygon' === $type) {
            $outer_ring = isset($coordinates[0]) ? $coordinates[0] : [];
            $segment = self::build_map_geometry_segment_from_geojson_coordinates($outer_ring);

            if (!empty($segment)) {
                $segments[] = self::build_geometry_segment_payload($segment, '', 'road');
            }
        } elseif ('MultiPolygon' === $type) {
            foreach ((array) $coordinates as $polygon) {
                $outer_ring = isset($polygon[0]) ? $polygon[0] : [];
                $segment = self::build_map_geometry_segment_from_geojson_coordinates($outer_ring);

                if (!empty($segment)) {
                    $segments[] = self::build_geometry_segment_payload($segment, '', 'road');
                }
            }
        }

        $segments = array_values(array_filter($segments));

        if (empty($segments)) {
            return false;
        }

        $all_points = [];

        foreach ($segments as $segment) {
            foreach (self::get_geometry_segment_points($segment) as $point) {
                if (isset($point[0], $point[1])) {
                    $all_points[] = $point;
                }
            }
        }

        if (empty($all_points)) {
            return false;
        }

        $latitudes = array_column($all_points, 0);
        $longitudes = array_column($all_points, 1);

        return [
            'geometry' => $segments,
            'center' => [
                (min($latitudes) + max($latitudes)) / 2,
                (min($longitudes) + max($longitudes)) / 2,
            ],
        ];
    }

    /**
     * Nominatim lists separate OSM ways for one named street. Merge each real
     * road result so a request for "Steindamm" does not stop at the first
     * 328-metre way returned by the geocoder.
     */
    private static function build_map_geometry_payload_from_geojson_collection($geojson_items) {
        $payload = false;

        foreach ((array) $geojson_items as $geojson) {
            $candidate = self::build_map_geometry_payload_from_geojson($geojson);

            if (!$candidate) {
                continue;
            }

            $payload = $payload
                ? self::merge_geometry_payloads($payload, $candidate)
                : $candidate;
        }

        return $payload ?: false;
    }

    /*
     * MAP GEOMETRY BASELINE - 3.2.60
     *
     * This is the canonical resolver for full-street geometry used by the public
     * Leaflet map, the editor preview, and generated map/share images. Do not
     * replace it with a PLZ-scoped query (streets cross postal-code areas), one
     * deduplicated Nominatim response (it hides OSM way segments), or an
     * artificial straight line between OSM segments.
     *
     * A future rewrite must retain all of these guarantees: city-wide lookup,
     * dedupe=0, exclude_place_ids pagination, real LineString/MultiLineString
     * geometry only, cache-source versioning, and equivalent output for all map
     * consumers. Change this baseline only with map examples that prove the
     * complete geometry for long and branching streets.
     */
    /**
     * Nominatim limits one response to fifty results. Long streets such as
     * Elbchaussee can contain more OSM ways than that, so continue through a
     * small, rate-limited result set using the documented exclude_place_ids
     * cursor. Geometry lookup intentionally omits the report PLZ: a street
     * can cross several postal-code areas, while the report coordinates still
     * stay tied to its exact address.
     */
    private static function request_nominatim_street_geojson_items($street, $city = 'Hamburg') {
        $street = trim((string) $street);
        $city = trim((string) $city);

        if ('' === $street) {
            return [];
        }

        if ('' === $city) {
            $city = 'Hamburg';
        }

        $query = self::build_full_address($street, '', $city);
        $geojson_items = [];
        $seen_geometries = [];
        $excluded_place_ids = [];

        for ($page = 0; $page < self::NOMINATIM_STREET_GEOMETRY_MAX_PAGES; $page++) {
            $request_args = [
                'q' => $query,
                'format' => 'jsonv2',
                'limit' => 50,
                'dedupe' => 0,
                'polygon_geojson' => 1,
                'countrycodes' => 'de',
            ];

            if (!empty($excluded_place_ids)) {
                $request_args['exclude_place_ids'] = implode(',', array_keys($excluded_place_ids));
            }

            $request_url = add_query_arg($request_args, self::NOMINATIM_SEARCH_URL);
            $results = self::decode_map_json_response(
                wp_safe_remote_get($request_url, self::get_map_remote_request_args(15))
            );

            if (empty($results) || !is_array($results)) {
                break;
            }

            $page_place_ids = [];

            foreach ($results as $result) {
                if (!is_array($result)) {
                    continue;
                }

                if (isset($result['place_id']) && is_numeric($result['place_id'])) {
                    $place_id = (string) absint($result['place_id']);
                    $excluded_place_ids[$place_id] = true;
                    $page_place_ids[$place_id] = true;
                }

                if (empty($result['geojson']) || !is_array($result['geojson'])) {
                    continue;
                }

                $osm_type = isset($result['osm_type']) ? sanitize_key((string) $result['osm_type']) : '';
                $osm_id = isset($result['osm_id']) ? (string) absint($result['osm_id']) : '';
                $geojson_type = isset($result['geojson']['type']) ? (string) $result['geojson']['type'] : '';

                if (
                    !in_array($osm_type, ['way', 'relation'], true)
                    || '' === $osm_id
                    || !in_array($geojson_type, ['LineString', 'MultiLineString'], true)
                ) {
                    continue;
                }

                $geometry_key = $osm_type . ':' . $osm_id;
                if (isset($seen_geometries[$geometry_key])) {
                    continue;
                }

                $seen_geometries[$geometry_key] = true;
                $geojson_items[] = $result['geojson'];
            }

            if (count($results) < 50 || empty($page_place_ids)) {
                break;
            }

            // Public Nominatim permits one request per second. This runs only
            // for a cache miss or a versioned geometry repair, never per tile.
            if ($page + 1 < self::NOMINATIM_STREET_GEOMETRY_MAX_PAGES && function_exists('usleep')) {
                usleep(1100000);
            }
        }

        return $geojson_items;
    }

    /**
     * Gives every map consumer the same complete, cache-safe street fallback.
     * It never invents a connection between separated OSM geometries.
     */
    public static function resolve_nominatim_street_geometry($street, $plz = '', $city = 'Hamburg') {
        $geojson_items = self::request_nominatim_street_geojson_items($street, $city);

        if (empty($geojson_items)) {
            $geocoded_data = self::request_geocoded_address_data($street, $plz, $city);
            $geojson_items = $geocoded_data && !empty($geocoded_data['street_geojson_items'])
                ? $geocoded_data['street_geojson_items']
                : ($geocoded_data ? [$geocoded_data['geojson']] : []);
        }

        return self::build_map_geometry_payload_from_geojson_collection($geojson_items);
    }

    private static function get_overpass_element_highway($element) {
        if (!is_array($element) || empty($element['tags']) || !is_array($element['tags'])) {
            return '';
        }

        foreach (['highway', 'area:highway'] as $tag_key) {
            if (isset($element['tags'][$tag_key]) && is_scalar($element['tags'][$tag_key])) {
                return sanitize_key((string) $element['tags'][$tag_key]);
            }
        }

        return '';
    }

    private static function extract_overpass_element_geometries($element) {
        if (!is_array($element)) {
            return [];
        }

        $geometries = [];

        if (!empty($element['geometry']) && is_array($element['geometry'])) {
            $geometries[] = $element['geometry'];
        }

        if (!empty($element['members']) && is_array($element['members'])) {
            foreach ($element['members'] as $member) {
                if (!is_array($member) || empty($member['geometry']) || !is_array($member['geometry'])) {
                    continue;
                }

                $member_role = isset($member['role']) ? sanitize_key((string) $member['role']) : '';

                if ('inner' === $member_role) {
                    continue;
                }

                $geometries[] = $member['geometry'];
            }
        }

        return $geometries;
    }

    private static function build_street_geometry_overpass_query($pattern, $radius, $include_relations = false) {
        $pattern = (string) $pattern;
        $radius = absint($radius);

        if ('' === $pattern || $radius < 1) {
            return '';
        }

        $query_parts = [
            'way["name"~"' . $pattern . '",i]["highway"](around:' . $radius . ',{{LAT}},{{LNG}});',
            'way["name"~"' . $pattern . '",i]["area:highway"](around:' . $radius . ',{{LAT}},{{LNG}});',
        ];

        if ($include_relations) {
            $query_parts[] = 'relation["name"~"' . $pattern . '",i]["highway"](around:' . $radius . ',{{LAT}},{{LNG}});';
            $query_parts[] = 'relation["name"~"' . $pattern . '",i]["area:highway"](around:' . $radius . ',{{LAT}},{{LNG}});';
        }

        return '[out:json][timeout:16];(' . implode('', $query_parts) . ');out geom;';
    }

    private static function finalize_geometry_payload_segments($segments) {
        $segments = array_values(array_filter((array) $segments));

        if (empty($segments)) {
            return false;
        }

        $segments = self::merge_connected_segments($segments);
        $segments = self::filter_redundant_segments($segments);

        if (empty($segments)) {
            return false;
        }

        $all_points = [];

        foreach ($segments as $segment) {
            foreach (self::get_geometry_segment_points($segment) as $point) {
                $all_points[] = $point;
            }
        }

        if (empty($all_points)) {
            return false;
        }

        $latitudes = array_column($all_points, 0);
        $longitudes = array_column($all_points, 1);

        return [
            'geometry' => $segments,
            'center' => [
                (min($latitudes) + max($latitudes)) / 2,
                (min($longitudes) + max($longitudes)) / 2,
            ],
        ];
    }

    private static function geometry_payload_has_pedestrian_segments($payload) {
        if (!is_array($payload) || empty($payload['geometry']) || !is_array($payload['geometry'])) {
            return false;
        }

        foreach ($payload['geometry'] as $segment) {
            if ('pedestrian' === self::get_geometry_segment_kind($segment)) {
                return true;
            }
        }

        return false;
    }

    private static function merge_geometry_payloads($left, $right) {
        $left_segments = is_array($left) && !empty($left['geometry']) && is_array($left['geometry']) ? $left['geometry'] : [];
        $right_segments = is_array($right) && !empty($right['geometry']) && is_array($right['geometry']) ? $right['geometry'] : [];

        return self::finalize_geometry_payload_segments(array_merge($left_segments, $right_segments));
    }

    private static function calculate_map_distance_meters($left, $right) {
        if (
            !is_array($left)
            || !is_array($right)
            || !isset($left[0], $left[1], $right[0], $right[1])
            || !is_numeric($left[0])
            || !is_numeric($left[1])
            || !is_numeric($right[0])
            || !is_numeric($right[1])
        ) {
            return INF;
        }

        $earth_radius = 6371000;
        $lat_delta = deg2rad((float) $right[0] - (float) $left[0]);
        $lng_delta = deg2rad((float) $right[1] - (float) $left[1]);
        $left_lat = deg2rad((float) $left[0]);
        $right_lat = deg2rad((float) $right[0]);
        $a = sin($lat_delta / 2) * sin($lat_delta / 2)
            + cos($left_lat) * cos($right_lat) * sin($lng_delta / 2) * sin($lng_delta / 2);

        return 2 * $earth_radius * asin(min(1, sqrt($a)));
    }

    private static function get_geometry_segment_points($segment) {
        if (is_array($segment) && isset($segment['points']) && is_array($segment['points'])) {
            return $segment['points'];
        }

        return is_array($segment) ? $segment : [];
    }

    private static function get_geometry_segment_highway($segment) {
        return is_array($segment) && isset($segment['highway'])
            ? sanitize_key((string) $segment['highway'])
            : '';
    }

    private static function get_geometry_segment_kind($segment) {
        if (is_array($segment) && isset($segment['kind'])) {
            $kind = sanitize_key((string) $segment['kind']);
            if (in_array($kind, ['road', 'pedestrian'], true)) {
                return $kind;
            }
        }

        return self::get_map_segment_kind(self::get_geometry_segment_highway($segment));
    }

    private static function get_geometry_segment_priority($segment) {
        static $priority_map = [
            'motorway' => 100,
            'trunk' => 95,
            'primary' => 90,
            'secondary' => 85,
            'tertiary' => 80,
            'unclassified' => 75,
            'residential' => 70,
            'living_street' => 68,
            'road' => 66,
            'service' => 58,
            'pedestrian' => 48,
            'footway' => 44,
            'path' => 40,
            'cycleway' => 36,
            'track' => 32,
            'steps' => 24,
            'corridor' => 20,
        ];

        $highway = self::get_geometry_segment_highway($segment);

        return isset($priority_map[$highway]) ? $priority_map[$highway] : 50;
    }

    private static function build_geometry_segment_payload($points, $highway = '', $kind = '') {
        $normalized_points = self::normalize_segment_points($points);

        if (count($normalized_points) < 2) {
            return false;
        }

        $highway = sanitize_key((string) $highway);
        $kind = sanitize_key((string) $kind);

        if (!in_array($kind, ['road', 'pedestrian'], true)) {
            $kind = self::get_map_segment_kind($highway);
        }

        return [
            'points' => $normalized_points,
            'highway' => $highway,
            'kind' => $kind,
        ];
    }

    private static function normalize_segment_points($segment) {
        $normalized = [];

        foreach (self::get_geometry_segment_points($segment) as $point) {
            if (!is_array($point)) {
                continue;
            }

            $latitude = null;
            $longitude = null;

            if (isset($point[0], $point[1]) && is_numeric($point[0]) && is_numeric($point[1])) {
                $latitude = (float) $point[0];
                $longitude = (float) $point[1];
            } elseif (isset($point['lat'], $point['lng']) && is_numeric($point['lat']) && is_numeric($point['lng'])) {
                $latitude = (float) $point['lat'];
                $longitude = (float) $point['lng'];
            } elseif (isset($point['lat'], $point['lon']) && is_numeric($point['lat']) && is_numeric($point['lon'])) {
                $latitude = (float) $point['lat'];
                $longitude = (float) $point['lon'];
            } elseif (isset($point['latitude'], $point['longitude']) && is_numeric($point['latitude']) && is_numeric($point['longitude'])) {
                $latitude = (float) $point['latitude'];
                $longitude = (float) $point['longitude'];
            }

            if (null === $latitude || null === $longitude) {
                continue;
            }

            $candidate = [round($latitude, 6), round($longitude, 6)];
            $last = !empty($normalized) ? $normalized[count($normalized) - 1] : null;

            if ($last && $candidate[0] === $last[0] && $candidate[1] === $last[1]) {
                continue;
            }

            $normalized[] = $candidate;
        }

        return count($normalized) > 1 ? $normalized : [];
    }

    private static function get_segment_length_meters($segment) {
        $points = self::normalize_segment_points($segment);
        $length = 0.0;

        for ($index = 1; $index < count($points); $index++) {
            $length += self::calculate_map_distance_meters($points[$index - 1], $points[$index]);
        }

        return $length;
    }

    private static function build_segment_hash($segment) {
        $normalized = self::normalize_segment_points($segment);

        if (empty($normalized)) {
            return '';
        }

        $forward = wp_json_encode($normalized);
        $reverse = wp_json_encode(array_reverse($normalized));

        return md5(strcmp((string) $forward, (string) $reverse) <= 0 ? (string) $forward : (string) $reverse);
    }

    private static function get_segment_endpoints($segment) {
        $normalized = self::normalize_segment_points($segment);

        if (count($normalized) < 2) {
            return false;
        }

        return [
            'start' => $normalized[0],
            'end' => $normalized[count($normalized) - 1],
        ];
    }

    private static function merge_segment_pair($left, $right, $endpoint_tolerance_meters = 18.0) {
        if (self::get_geometry_segment_kind($left) !== self::get_geometry_segment_kind($right)) {
            return false;
        }

        $left_points = self::normalize_segment_points($left);
        $right_points = self::normalize_segment_points($right);

        if (empty($left_points) || empty($right_points)) {
            return false;
        }

        $left_endpoints = self::get_segment_endpoints($left_points);
        $right_endpoints = self::get_segment_endpoints($right_points);

        if (!$left_endpoints || !$right_endpoints) {
            return false;
        }

        if (self::calculate_map_distance_meters($left_endpoints['end'], $right_endpoints['start']) <= $endpoint_tolerance_meters) {
            $merged_points = array_merge($left_points, array_slice($right_points, 1));
        } elseif (self::calculate_map_distance_meters($left_endpoints['end'], $right_endpoints['end']) <= $endpoint_tolerance_meters) {
            $right_points = array_reverse($right_points);
            $merged_points = array_merge($left_points, array_slice($right_points, 1));
        } elseif (self::calculate_map_distance_meters($left_endpoints['start'], $right_endpoints['end']) <= $endpoint_tolerance_meters) {
            $merged_points = array_merge(array_slice($right_points, 0, -1), $left_points);
        } elseif (self::calculate_map_distance_meters($left_endpoints['start'], $right_endpoints['start']) <= $endpoint_tolerance_meters) {
            $right_points = array_reverse($right_points);
            $merged_points = array_merge(array_slice($right_points, 0, -1), $left_points);
        } else {
            return false;
        }

        $preferred = self::get_geometry_segment_priority($left) >= self::get_geometry_segment_priority($right) ? $left : $right;

        return self::build_geometry_segment_payload(
            $merged_points,
            self::get_geometry_segment_highway($preferred),
            self::get_geometry_segment_kind($preferred)
        );
    }

    private static function merge_connected_segments($segments, $endpoint_tolerance_meters = 18.0) {
        $remaining = array_values(array_filter(array_map(function($segment) {
            return self::build_geometry_segment_payload(
                self::get_geometry_segment_points($segment),
                self::get_geometry_segment_highway($segment),
                self::get_geometry_segment_kind($segment)
            );
        }, (array) $segments)));
        $merged_segments = [];

        while (!empty($remaining)) {
            $current = array_shift($remaining);
            $did_merge = true;

            while ($did_merge) {
                $did_merge = false;

                foreach ($remaining as $index => $candidate) {
                    $merged = self::merge_segment_pair($current, $candidate, $endpoint_tolerance_meters);

                    if (false === $merged) {
                        continue;
                    }

                    $current = $merged;
                    unset($remaining[$index]);
                    $remaining = array_values($remaining);
                    $did_merge = true;
                    break;
                }
            }

            $merged_segments[] = $current;
        }

        return $merged_segments;
    }

    private static function is_segment_redundant($candidate, $existing, $distance_tolerance_meters = 4.0, $coverage_ratio = 0.9) {
        if (self::get_geometry_segment_kind($candidate) !== self::get_geometry_segment_kind($existing)) {
            return false;
        }

        $candidate_points = self::normalize_segment_points($candidate);
        $existing_points = self::normalize_segment_points($existing);

        if (count($candidate_points) < 2 || count($existing_points) < 2) {
            return false;
        }

        $covered_points = 0;

        foreach ($candidate_points as $candidate_point) {
            $min_distance = INF;

            foreach ($existing_points as $existing_point) {
                $min_distance = min($min_distance, self::calculate_map_distance_meters($candidate_point, $existing_point));
            }

            if ($min_distance <= $distance_tolerance_meters) {
                $covered_points++;
            }
        }

        if (($covered_points / count($candidate_points)) < $coverage_ratio) {
            return false;
        }

        return self::get_segment_length_meters($candidate) <= (self::get_segment_length_meters($existing) * 1.15);
    }

    private static function filter_redundant_segments($segments) {
        $segments = array_values((array) $segments);

        usort($segments, static function($left, $right) {
            $priority_compare = self::get_geometry_segment_priority($right) <=> self::get_geometry_segment_priority($left);

            if (0 !== $priority_compare) {
                return $priority_compare;
            }

            return self::get_segment_length_meters($right) <=> self::get_segment_length_meters($left);
        });

        $kept_segments = [];

        foreach ($segments as $segment) {
            $is_redundant = false;

            foreach ($kept_segments as $existing_segment) {
                if (self::is_segment_redundant($segment, $existing_segment)) {
                    $is_redundant = true;
                    break;
                }
            }

            if (!$is_redundant) {
                $kept_segments[] = $segment;
            }
        }

        return $kept_segments;
    }

    private static function segment_connects_to_group($segment, $group, $endpoint_tolerance_meters = 18.0) {
        $segment_endpoints = self::get_segment_endpoints($segment);

        if (!$segment_endpoints) {
            return false;
        }

        foreach ((array) $group as $group_segment) {
            $group_endpoints = self::get_segment_endpoints($group_segment);

            if (!$group_endpoints) {
                continue;
            }

            foreach (['start', 'end'] as $segment_side) {
                foreach (['start', 'end'] as $group_side) {
                    if (self::calculate_map_distance_meters($segment_endpoints[$segment_side], $group_endpoints[$group_side]) <= $endpoint_tolerance_meters) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private static function build_segment_groups($segments, $endpoint_tolerance_meters = 18.0) {
        $remaining = array_values((array) $segments);
        $groups = [];

        while (!empty($remaining)) {
            $group = [array_shift($remaining)];
            $did_expand = true;

            while ($did_expand) {
                $did_expand = false;

                foreach ($remaining as $index => $candidate) {
                    if (!self::segment_connects_to_group($candidate, $group, $endpoint_tolerance_meters)) {
                        continue;
                    }

                    $group[] = $candidate;
                    unset($remaining[$index]);
                    $remaining = array_values($remaining);
                    $did_expand = true;
                }
            }

            $groups[] = $group;
        }

        return $groups;
    }

    private static function get_segment_distance_to_coordinates($segment, $coordinates) {
        if (
            !is_array($coordinates)
            || !isset($coordinates['lat'], $coordinates['lng'])
            || !is_numeric($coordinates['lat'])
            || !is_numeric($coordinates['lng'])
        ) {
            return INF;
        }

        $reference = [(float) $coordinates['lat'], (float) $coordinates['lng']];
        $distance = INF;

        foreach (self::normalize_segment_points($segment) as $point) {
            $distance = min($distance, self::calculate_map_distance_meters($point, $reference));
        }

        return $distance;
    }

    private static function pick_best_segment_group($groups, $coordinates) {
        if (empty($groups)) {
            return [];
        }

        $best_group = [];
        $best_distance = INF;

        foreach ((array) $groups as $group) {
            $group_distance = INF;

            foreach ((array) $group as $segment) {
                $group_distance = min($group_distance, self::get_segment_distance_to_coordinates($segment, $coordinates));
            }

            if ($group_distance < $best_distance) {
                $best_distance = $group_distance;
                $best_group = $group;
            }
        }

        return $best_group;
    }

    private static function build_map_geometry_payload_from_overpass_elements($elements, $coordinates = null) {
        if (empty($elements) || !is_array($elements)) {
            return false;
        }

        $segments = [];

        foreach ($elements as $element) {
            $highway = self::get_overpass_element_highway($element);

            foreach (self::extract_overpass_element_geometries($element) as $raw_geometry) {
                $segment = [];

                foreach ($raw_geometry as $point) {
                    if (
                        !is_array($point)
                        || !isset($point['lat'], $point['lon'])
                        || !is_numeric($point['lat'])
                        || !is_numeric($point['lon'])
                    ) {
                        continue;
                    }

                    $lat = (float) $point['lat'];
                    $lng = (float) $point['lon'];
                    $segment[] = [$lat, $lng];
                }

                $segment = self::normalize_segment_points($segment);

                if (count($segment) < 2) {
                    continue;
                }

                $segment_hash = self::build_segment_hash($segment);
                $segment_payload = self::build_geometry_segment_payload(
                    $segment,
                    $highway,
                    self::get_map_segment_kind($highway)
                );

                if ('' === $segment_hash || false === $segment_payload) {
                    continue;
                }

                if (
                    isset($segments[$segment_hash])
                    && self::get_geometry_segment_priority($segments[$segment_hash]) >= self::get_geometry_segment_priority($segment_payload)
                ) {
                    continue;
                }

                $segments[$segment_hash] = $segment_payload;
            }
        }

        return self::finalize_geometry_payload_segments(array_values($segments));
    }

    private static function get_geometry_payload_score($payload, $coordinates = null) {
        if (
            !is_array($payload)
            || empty($payload['geometry'])
            || !is_array($payload['geometry'])
        ) {
            return -INF;
        }

        $segment_count = 0;
        $pedestrian_count = 0;
        $total_length = 0.0;
        $closest_distance = INF;

        foreach ($payload['geometry'] as $segment) {
            $points = self::normalize_segment_points($segment);

            if (count($points) < 2) {
                continue;
            }

            $segment_count++;
            $total_length += self::get_segment_length_meters($segment);

            if ('pedestrian' === self::get_geometry_segment_kind($segment)) {
                $pedestrian_count++;
            }

            if (
                is_array($coordinates)
                && isset($coordinates['lat'], $coordinates['lng'])
                && is_numeric($coordinates['lat'])
                && is_numeric($coordinates['lng'])
            ) {
                $closest_distance = min($closest_distance, self::get_segment_distance_to_coordinates($segment, $coordinates));
            }
        }

        if ($segment_count < 1) {
            return -INF;
        }

        $distance_penalty = is_finite($closest_distance) ? min(2000, $closest_distance) * 0.35 : 0;

        return $total_length
            + ($segment_count * 140)
            + ($pedestrian_count * 220)
            - $distance_penalty;
    }

    private static function get_geometry_payload_segment_count($payload) {
        $segments = is_array($payload) && isset($payload['geometry']) && is_array($payload['geometry'])
            ? $payload['geometry']
            : (is_array($payload) ? $payload : []);

        return count(array_filter($segments, static function($segment) {
            return count(self::normalize_segment_points($segment)) > 1;
        }));
    }

    private static function is_geometry_payload_richer($candidate_payload, $current_payload) {
        $candidate_segment_count = self::get_geometry_payload_segment_count($candidate_payload);
        $current_segment_count = self::get_geometry_payload_segment_count($current_payload);

        if ($candidate_segment_count > $current_segment_count) {
            return true;
        }

        $candidate_has_pedestrian = self::geometry_payload_has_pedestrian_segments($candidate_payload);
        $current_has_pedestrian = self::geometry_payload_has_pedestrian_segments($current_payload);

        if ($candidate_has_pedestrian && !$current_has_pedestrian) {
            return true;
        }

        return self::get_geometry_payload_score($candidate_payload) > self::get_geometry_payload_score($current_payload);
    }

    private static function should_attempt_editor_geometry_refresh($payload) {
        $segment_count = self::get_geometry_payload_segment_count($payload);

        if ($segment_count < 3) {
            return true;
        }

        if ($segment_count < 6 && !self::geometry_payload_has_pedestrian_segments($payload)) {
            return true;
        }

        return false;
    }

    private static function get_single_geometry_refresh_lock_key($post_id) {
        return 'feu_einsatz_single_geometry_refresh_' . absint($post_id);
    }

    private static function get_single_geometry_prime_lock_key($post_id) {
        // Keep an unsuccessful repair attempt from an older implementation
        // from suppressing the current recovery path.
        return 'feu_einsatz_single_geometry_prime_v4_' . absint($post_id);
    }

    private static function get_single_context_cache_fingerprint(WP_Post $post) {
        $post_id = (int) $post->ID;

        return md5(wp_json_encode([
            'context_cache_version' => self::SINGLE_CONTEXT_CACHE_VERSION,
            'post_id' => $post_id,
            'post_modified_gmt' => (string) $post->post_modified_gmt,
            'post_title' => (string) $post->post_title,
            'street' => (string) get_post_meta($post_id, '_feu_einsatz_strasse', true),
            'house_number' => (string) get_post_meta($post_id, '_feu_einsatz_hausnummer', true),
            'postcode' => (string) get_post_meta($post_id, '_feu_einsatz_plz', true),
            'city' => (string) get_post_meta($post_id, '_feu_einsatz_stadt', true),
            'date' => (string) get_post_meta($post_id, '_feu_einsatz_datum', true),
            'time' => (string) get_post_meta($post_id, '_feu_einsatz_uhrzeit', true),
            'latitude' => (string) get_post_meta($post_id, '_feu_einsatz_latitude', true),
            'longitude' => (string) get_post_meta($post_id, '_feu_einsatz_longitude', true),
            'coordinates_address_key' => (string) get_post_meta($post_id, self::ADDRESS_COORDINATES_META, true),
            'map_location_mode' => (string) get_post_meta($post_id, self::MAP_LOCATION_MODE_META, true),
            'map_highlight_override' => (string) get_post_meta($post_id, self::MAP_HIGHLIGHT_OVERRIDE_META, true),
            'map_highlight_length_meters' => (string) get_post_meta($post_id, self::MAP_HIGHLIGHT_LENGTH_META, true),
            'map_highlight_radius_meters' => (string) get_post_meta($post_id, self::MAP_HIGHLIGHT_RADIUS_META, true),
            'map_extra_streets' => maybe_serialize(get_post_meta($post_id, self::MAP_EXTRA_STREETS_META, true)),
            'map_area_geojson' => maybe_serialize(get_post_meta($post_id, self::MAP_AREA_GEOJSON_META, true)),
            'map_public_precision' => (string) get_post_meta($post_id, self::MAP_PUBLIC_PRECISION_META, true),
            'street_geometry_source' => (string) get_post_meta($post_id, self::FOCUSED_GEOMETRY_SOURCE_META, true),
            'street_cache_version' => (string) get_post_meta($post_id, FEU_Einsatz_Street_Cache::POST_META_VERSION, true),
            'street_cache_revision' => (string) get_post_meta($post_id, FEU_Einsatz_Street_Cache::POST_META_REVISION, true),
            'participants' => maybe_serialize(get_post_meta($post_id, '_feu_einsatz_teilnehmer', true)),
            'organizations' => maybe_serialize(get_post_meta($post_id, '_feu_einsatz_organisationen', true)),
            'gallery' => maybe_serialize(get_post_meta($post_id, '_feu_einsatz_gallery', true)),
            'generated_map_preview_url' => (string) get_post_meta($post_id, '_feu_einsatz_generated_map_preview_url', true),
            'generated_map_preview_file' => (string) get_post_meta($post_id, '_feu_einsatz_generated_map_preview_file', true),
            'comments_enabled' => (string) get_post_meta($post_id, '_feu_einsatz_comments_enabled', true),
            'comment_count' => (int) get_comments_number($post_id),
            'display' => [
                'related_display' => (string) get_option('feu_einsatz_related_reports_display', 'cards'),
                'related_count' => (int) get_option('feu_einsatz_related_reports_count', 6),
                'card_variant' => (string) get_option('feu_einsatz_default_card_variant', 'modern'),
                'single_info_fields' => self::normalize_single_info_fields(
                    get_option('feu_einsatz_single_info_fields', self::get_default_single_info_fields())
                ),
                'map_display_mode' => (string) get_option('feu_einsatz_single_map_display_mode', 'live'),
                'map_privacy_mode' => (string) get_option('feu_einsatz_single_map_privacy_mode', 'always'),
                'map_height' => (int) get_option('feu_einsatz_map_height', 500),
                'map_zoom' => (int) get_option('feu_einsatz_map_zoom', 16),
                'street_highlight_mode' => (string) get_option('feu_einsatz_street_highlight_mode', 'full'),
                'street_highlight_length_meters' => (int) get_option('feu_einsatz_street_highlight_length_meters', 100),
                'street_highlight_radius_meters' => (int) get_option('feu_einsatz_street_highlight_radius_meters', 100),
                'show_station' => (int) get_option('feu_einsatz_single_live_map_show_station', 0),
                'desaturate_orgs' => (int) get_option('feu_einsatz_single_desaturate_organizations', 0),
                'watermark_enabled' => (int) get_option('feu_einsatz_photo_watermark_enabled', 1),
                'watermark_text' => (string) get_option('feu_einsatz_photo_watermark_text', get_bloginfo('name')),
            ],
        ]));
    }

    private static function get_single_context_transient_key(WP_Post $post) {
        $post_id = (int) $post->ID;

        if ($post_id < 1) {
            return '';
        }

        return 'feu_single_ctx_' . $post_id . '_' . self::get_single_context_cache_fingerprint($post);
    }

    public static function strip_house_number_from_street($street) {
        $street = trim((string) preg_replace('/\s+/', ' ', (string) $street));

        if ('' === $street) {
            return '';
        }

        $street = trim((string) preg_replace('/\s*,.*$/u', '', $street));
        $street_without_number = trim((string) preg_replace('/\s+\d+[[:alpha:]]?(?:\s*[-\/]\s*\d+[[:alpha:]]?)?.*$/u', '', $street));

        if ('' !== $street_without_number) {
            return trim((string) preg_replace('/\s+/', ' ', $street_without_number));
        }

        return $street;
    }

    /**
     * A future-ready, human-readable location line for SEO or introduction text.
     * It deliberately returns an empty district for historic reports that have none.
     */
    public static function get_report_location_seo_text($post_id) {
        $post_id = absint($post_id);
        $city = trim((string) get_post_meta($post_id, '_feu_einsatz_stadt', true));
        $district = trim((string) get_post_meta($post_id, '_feu_einsatz_stadtteil', true));
        $date = trim((string) get_post_meta($post_id, '_feu_einsatz_datum', true));

        if ('' === $city) {
            return '';
        }

        $location = $city . ('' !== $district ? ' ' . $district : '');
        if ('' === $date) {
            return $location;
        }

        $timestamp = strtotime($date);
        $formatted_date = false !== $timestamp ? date_i18n('d.m.Y', $timestamp) : $date;

        return sprintf(__('Feuerwehr-Alarmierung in %1$s am %2$s', 'feuer-einsatzberichte'), $location, $formatted_date);
    }

    private static function get_street_query_candidates($street) {
        $street = trim((string) preg_replace('/\s+/', ' ', (string) $street));

        if ('' === $street) {
            return [];
        }

        $candidates = [$street];
        $first_segment = trim((string) preg_replace('/\s*,.*$/u', '', $street));

        if ('' !== $first_segment) {
            $candidates[] = $first_segment;
        }

        foreach ($candidates as $candidate) {
            $without_number = trim((string) preg_replace('/\s+\d+[[:alpha:]]?(?:\s*[-\/]\s*\d+[[:alpha:]]?)?.*$/u', '', $candidate));

            if ('' !== $without_number) {
                $candidates[] = $without_number;
            }
        }

        $candidates = array_values(array_unique(array_filter(array_map(static function($candidate) {
            return trim((string) preg_replace('/\s+/', ' ', (string) $candidate));
        }, $candidates))));

        usort($candidates, static function($left, $right) {
            return strlen($left) <=> strlen($right);
        });

        return $candidates;
    }

    private static function build_full_address($street, $plz = '', $city = 'Hamburg', $house_number = '') {
        $street = self::strip_house_number_from_street($street);
        $house_number = trim((string) preg_replace('/\s+/', ' ', (string) $house_number));
        $plz = trim((string) $plz);
        $city = trim((string) $city);

        if ('' === $city) {
            $city = 'Hamburg';
        }

        $street_with_house_number = trim($street . ('' !== $house_number ? ' ' . $house_number : ''));
        $parts = array_filter([$street_with_house_number, $plz, $city], static function ($value) {
            return '' !== $value;
        });

        return implode(', ', $parts) . ', Deutschland';
    }

    private static function request_geocoded_address_data($street, $plz = '', $city = 'Hamburg', $house_number = '') {
        $address = self::build_full_address($street, $plz, $city, $house_number);

        if ('' === trim($address)) {
            return false;
        }

        $request_url = add_query_arg(
            [
                'q' => $address,
                'format' => 'jsonv2',
                // This lookup finds the incident coordinate. Complete street
                // geometry is resolved separately without a PLZ restriction.
                'limit' => 1,
                'addressdetails' => 1,
                'polygon_geojson' => 1,
                'countrycodes' => 'de',
            ],
            self::NOMINATIM_SEARCH_URL
        );
        $response = wp_safe_remote_get($request_url, self::get_map_remote_request_args(15));
        $results = self::decode_map_json_response($response);

        if (empty($results[0]) || !is_array($results[0])) {
            return false;
        }

        $result = $results[0];

        if (!isset($result['lat'], $result['lon']) || !is_numeric($result['lat']) || !is_numeric($result['lon'])) {
            return false;
        }

        $street_geojson_items = [];

        foreach ($results as $road_result) {
            if (!is_array($road_result) || empty($road_result['geojson']) || !is_array($road_result['geojson'])) {
                continue;
            }

            $osm_type = isset($road_result['osm_type']) ? sanitize_key((string) $road_result['osm_type']) : '';
            $geojson_type = isset($road_result['geojson']['type']) ? (string) $road_result['geojson']['type'] : '';

            if (
                !in_array($osm_type, ['way', 'relation'], true)
                || !in_array($geojson_type, ['LineString', 'MultiLineString'], true)
            ) {
                continue;
            }

            $street_geojson_items[] = $road_result['geojson'];
        }

        return [
            'lat' => round((float) $result['lat'], 6),
            'lng' => round((float) $result['lon'], 6),
            'display_name' => isset($result['display_name']) ? sanitize_text_field((string) $result['display_name']) : '',
            'house_number' => isset($result['address']['house_number'])
                ? sanitize_text_field((string) $result['address']['house_number'])
                : '',
            'geojson' => (isset($result['geojson']) && is_array($result['geojson'])) ? $result['geojson'] : [],
            'street_geojson_items' => $street_geojson_items,
        ];
    }

    private static function request_street_geometry_data($street, $coordinates) {
        if (
            '' === trim((string) $street)
            || !is_array($coordinates)
            || !isset($coordinates['lat'], $coordinates['lng'])
        ) {
            return false;
        }

        $latitude = (float) $coordinates['lat'];
        $longitude = (float) $coordinates['lng'];
        $street_candidates = self::get_street_query_candidates($street);

        if (empty($street_candidates)) {
            return false;
        }

        $query_specs = [];

        foreach ($street_candidates as $street_candidate) {
            $street_pattern = preg_quote($street_candidate, '/');

            if ('' === $street_pattern) {
                continue;
            }

            foreach ([900, 2200, 4200, 8000] as $radius) {
                $query_specs[] = [
                    'pattern' => '^' . $street_pattern . '$',
                    'radius' => $radius,
                ];
            }
        }

        $primary_candidate = reset($street_candidates);
        if (false !== $primary_candidate) {
            $street_pattern = preg_quote((string) $primary_candidate, '/');

            if ('' !== $street_pattern) {
                $query_specs[] = [
                    'pattern' => $street_pattern,
                    'radius' => 2200,
                ];
                $query_specs[] = [
                    'pattern' => $street_pattern,
                    'radius' => 4200,
                ];
            }
        }

        $best_geometry_payload = false;
        $best_geometry_score = -INF;

        foreach ($query_specs as $query_spec) {
            $query = self::build_street_geometry_overpass_query($query_spec['pattern'], $query_spec['radius'], false);

            if ('' === $query) {
                continue;
            }

            $query = str_replace(['{{LAT}}', '{{LNG}}'], [$latitude, $longitude], $query);
            $payload = self::request_overpass_payload($query, 16);

            if (empty($payload['elements']) || !is_array($payload['elements'])) {
                continue;
            }

            $geometry_payload = self::build_map_geometry_payload_from_overpass_elements($payload['elements'], $coordinates);

            if ($geometry_payload) {
                $geometry_score = self::get_geometry_payload_score($geometry_payload, $coordinates);

                if ($geometry_score > $best_geometry_score) {
                    $best_geometry_score = $geometry_score;
                    $best_geometry_payload = $geometry_payload;
                }
            }
        }

        if ($best_geometry_payload) {
            $exact_pattern = null;

            foreach ($query_specs as $query_spec) {
                if ('^' === substr((string) $query_spec['pattern'], 0, 1)) {
                    $exact_pattern = (string) $query_spec['pattern'];
                    break;
                }
            }

            if (null !== $exact_pattern) {
                $supplement_query = self::build_street_geometry_overpass_query($exact_pattern, 8000, true);

                if ('' !== $supplement_query) {
                    $supplement_query = str_replace(['{{LAT}}', '{{LNG}}'], [$latitude, $longitude], $supplement_query);
                    $payload = self::request_overpass_payload($supplement_query, 16);

                    if (!empty($payload['elements']) && is_array($payload['elements'])) {
                        $supplement_payload = self::build_map_geometry_payload_from_overpass_elements($payload['elements'], $coordinates);

                        if ($supplement_payload) {
                            $merged_payload = self::merge_geometry_payloads($best_geometry_payload, $supplement_payload);

                            if ($merged_payload) {
                                $best_geometry_payload = $merged_payload;
                            }
                        }
                    }
                }
            }
        }

        return $best_geometry_payload ?: false;
    }

    private static function request_focused_street_geometry_data($street, $coordinates) {
        $street = trim((string) $street);

        if (
            '' === $street
            || !is_array($coordinates)
            || !isset($coordinates['lat'], $coordinates['lng'])
            || !is_numeric($coordinates['lat'])
            || !is_numeric($coordinates['lng'])
        ) {
            return false;
        }

        $street_candidates = self::get_street_query_candidates($street);
        $primary_candidate = !empty($street_candidates)
            ? (string) reset($street_candidates)
            : $street;
        $street_pattern = preg_quote($primary_candidate, '/');

        if ('' === $street_pattern) {
            return false;
        }

        $query = self::build_street_geometry_overpass_query('^' . $street_pattern . '$', 8000, true);

        if ('' === $query) {
            return false;
        }

        $query = str_replace(
            ['{{LAT}}', '{{LNG}}'],
            [(float) $coordinates['lat'], (float) $coordinates['lng']],
            $query
        );

        $payload = self::request_overpass_payload($query, 12);

        if (empty($payload['elements']) || !is_array($payload['elements'])) {
            return false;
        }

        return self::build_map_geometry_payload_from_overpass_elements($payload['elements'], $coordinates);
    }

    public static function prime_public_map_data($post_id, $street, $plz = '', $city = 'Hamburg') {
        $post_id = absint($post_id);
        $street = trim((string) $street);
        $plz = trim((string) $plz);
        $city = trim((string) $city);

        if (!$post_id) {
            return false;
        }

        $location_mode = self::get_report_map_location_mode($post_id);

        if ('' === $street && 'coordinates' === $location_mode) {
            $coordinates = self::get_report_incident_coordinates($post_id, '', $plz, $city);

            return $coordinates ? [
                'coordinates' => $coordinates,
                'geometry' => [],
                'center' => [(float) $coordinates['lat'], (float) $coordinates['lng']],
            ] : false;
        }

        if ('' === $street) {
            return false;
        }

        $geometry_payload = FEU_Einsatz_Street_Cache::get_post_cache($post_id);

        if (!$geometry_payload) {
            $geometry_payload = FEU_Einsatz_Street_Cache::get($street, $plz, $city);

            if ($geometry_payload) {
                FEU_Einsatz_Street_Cache::set_post_cache($post_id, $geometry_payload);
            }
        }

        $requires_precise_incident_anchor = self::report_needs_precise_incident_anchor($post_id, $street, $plz, $city);
        $coordinates = self::get_report_incident_coordinates(
            $post_id,
            $street,
            $plz,
            $city,
            $requires_precise_incident_anchor
        );

        $geometry_source = (string) get_post_meta($post_id, self::FOCUSED_GEOMETRY_SOURCE_META, true);
        $has_current_geometry_source = in_array(
            $geometry_source,
            [self::FOCUSED_GEOMETRY_SOURCE, self::NOMINATIM_GEOMETRY_SOURCE],
            true
        );
        $geometry_needs_enrichment = !$geometry_payload
            || (!$has_current_geometry_source && self::should_attempt_editor_geometry_refresh($geometry_payload));
        $nominatim_enriched_geometry = false;

        if ('address' === $location_mode && (!$coordinates || $geometry_needs_enrichment)) {
            $house_number = trim((string) get_post_meta($post_id, '_feu_einsatz_hausnummer', true));
            $geocoded_data = self::request_geocoded_address_data($street, $plz, $city, $house_number);

            if ($geocoded_data) {
                if (!$coordinates) {
                    $coordinates = [
                        'lat' => (float) $geocoded_data['lat'],
                        'lng' => (float) $geocoded_data['lng'],
                    ];

                    update_post_meta($post_id, '_feu_einsatz_latitude', (string) $geocoded_data['lat']);
                    update_post_meta($post_id, '_feu_einsatz_longitude', (string) $geocoded_data['lng']);

                    if (
                        '' === $house_number
                        || self::normalize_house_number_for_match($house_number) === self::normalize_house_number_for_match($geocoded_data['house_number'] ?? '')
                    ) {
                        self::mark_report_address_coordinates($post_id, $street, $house_number, $plz, $city);
                    }
                }

                if (!empty($geocoded_data['display_name'])) {
                    update_post_meta($post_id, '_feu_einsatz_display_address', $geocoded_data['display_name']);
                }
            }

            // The event PLZ finds the incident coordinate. The road itself can
            // cross several postal-code areas, so the shared resolver queries
            // the complete city-wide OSM street geometry independently.
            $nominatim_geometry_payload = self::resolve_nominatim_street_geometry($street, $plz, $city);

            if (
                $nominatim_geometry_payload
                && (!$geometry_payload || self::is_geometry_payload_richer($nominatim_geometry_payload, $geometry_payload))
            ) {
                $geometry_payload = $geometry_payload
                    ? self::merge_geometry_payloads($geometry_payload, $nominatim_geometry_payload)
                    : $nominatim_geometry_payload;
                FEU_Einsatz_Street_Cache::set($street, $plz, $city, $geometry_payload);
                FEU_Einsatz_Street_Cache::set_post_cache($post_id, $geometry_payload);
                update_post_meta($post_id, self::FOCUSED_GEOMETRY_SOURCE_META, self::NOMINATIM_GEOMETRY_SOURCE);
                $nominatim_enriched_geometry = true;
            }
        }

        $needs_focused_anchor_geometry = $coordinates
            && self::needs_incident_anchor_geometry(
                is_array($geometry_payload) ? ($geometry_payload['geometry'] ?? []) : [],
                $coordinates,
                $post_id
            );

        if (
            '' !== $street
            && $coordinates
            && (
                $needs_focused_anchor_geometry
                || (
                    !$nominatim_enriched_geometry
                    && self::NOMINATIM_GEOMETRY_SOURCE !== (string) get_post_meta($post_id, self::FOCUSED_GEOMETRY_SOURCE_META, true)
                )
            )
        ) {
            // One bounded, exact street query is appropriate for a page view.
            // The broad multi-radius resolver is reserved for maintenance and
            // image jobs; it can otherwise keep a public PHP request waiting
            // for multiple external services. Do not skip this merely because
            // Nominatim returned a short house-number segment.
            $focused_geometry_payload = self::request_focused_street_geometry_data($street, $coordinates);

            if ($focused_geometry_payload) {
                $merged_geometry_payload = $geometry_payload
                    ? self::merge_geometry_payloads($geometry_payload, $focused_geometry_payload)
                    : $focused_geometry_payload;

                if (
                    $merged_geometry_payload
                    && (
                        $needs_focused_anchor_geometry
                        || !$geometry_payload
                        || self::is_geometry_payload_richer($focused_geometry_payload, $geometry_payload)
                    )
                ) {
                    $geometry_payload = $merged_geometry_payload;
                    FEU_Einsatz_Street_Cache::set($street, $plz, $city, $geometry_payload);
                    FEU_Einsatz_Street_Cache::set_post_cache($post_id, $geometry_payload);

                    // The address-centred segment is now available to both
                    // limited modes. Keep the full-street cache intact and
                    // merge only real OSM geometry - never a straight line.
                    update_post_meta($post_id, self::FOCUSED_GEOMETRY_SOURCE_META, self::FOCUSED_GEOMETRY_SOURCE);
                }
            }
        }

        return [
            'coordinates' => $coordinates,
            'geometry' => (is_array($geometry_payload) && !empty($geometry_payload['geometry'])) ? $geometry_payload['geometry'] : [],
            'center' => (is_array($geometry_payload) && !empty($geometry_payload['center'])) ? $geometry_payload['center'] : [],
        ];
    }

    public static function get_event_datetime($post_id) {
        $event_date = trim((string) get_post_meta($post_id, '_feu_einsatz_datum', true));
        $event_time = trim((string) get_post_meta($post_id, '_feu_einsatz_uhrzeit', true));

        $formatted_date = get_the_date('d.m.Y', $post_id);

        if ('' !== $event_date) {
            $timestamp = strtotime($event_date);

            if (false !== $timestamp) {
                $formatted_date = date_i18n('d.m.Y', $timestamp);
            }
        }

        $formatted_time = '';

        if ('' !== $event_time) {
            $formatted_time = $event_time . ' Uhr';
        } else {
            $post_time = get_the_time('H:i', $post_id);

            if ($post_time) {
                $formatted_time = $post_time . ' Uhr';
            }
        }

        return [
            'date' => $formatted_date,
            'time' => $formatted_time,
        ];
    }

    private static function normalize_related_ids($values) {
        $normalized_ids = [];

        foreach ((array) $values as $value) {
            if (is_array($value) && isset($value['id'])) {
                $value = $value['id'];
            }

            $value = absint($value);

            if ($value > 0) {
                $normalized_ids[] = $value;
            }
        }

        return array_values(array_unique($normalized_ids));
    }

    private static function build_location_label($street, $plz, $city) {
        $street = self::strip_house_number_from_street($street);
        $plz = trim((string) $plz);
        $city = trim((string) $city);
        $location_parts = [];

        if ('' !== $street) {
            $location_parts[] = $street;
        }

        $city_line = trim(implode(' ', array_filter([$plz, $city], static function($value) {
            return '' !== trim((string) $value);
        })));

        if ('' !== $city_line) {
            $location_parts[] = $city_line;
        }

        return implode(', ', $location_parts);
    }

    private static function get_participant_names($participant_ids) {
        global $wpdb;

        $participant_ids = self::normalize_related_ids($participant_ids);

        if (empty($participant_ids)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($participant_ids), '%d'));
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, vorname, nachname FROM {$wpdb->prefix}feu_einsatz_teilnehmer WHERE id IN ({$placeholders})",
                $participant_ids
            )
        );

        if (!is_array($results)) {
            return [];
        }

        $indexed_results = [];

        foreach ($results as $result) {
            $indexed_results[(int) $result->id] = self::format_participant_name($result->vorname, $result->nachname);
        }

        $ordered_names = [];

        foreach ($participant_ids as $participant_id) {
            if (!empty($indexed_results[$participant_id])) {
                $ordered_names[] = $indexed_results[$participant_id];
            }
        }

        return $ordered_names;
    }

    private static function build_gallery_items($gallery_ids, $can_generate_runtime_assets = false) {
        $gallery_items = [];
        $gallery_ids = self::normalize_related_ids($gallery_ids);
        $image_watermark_enabled = class_exists('FEU_Einsatz_Image_Protection') && FEU_Einsatz_Image_Protection::is_image_watermark_enabled();

        foreach ($gallery_ids as $attachment_id) {
            $protected_urls = $image_watermark_enabled
                ? FEU_Einsatz_Image_Protection::get_protected_image_urls($attachment_id, $can_generate_runtime_assets)
                : false;
            $thumb_url = wp_get_attachment_image_url($attachment_id, 'medium_large');
            $full_url = wp_get_attachment_image_url($attachment_id, 'full');

            if ($protected_urls && !empty($protected_urls['thumb']) && !empty($protected_urls['full'])) {
                $thumb_url = $protected_urls['thumb'];
                $full_url = $protected_urls['full'];
            }

            if (!$thumb_url || !$full_url) {
                continue;
            }

            $gallery_items[] = [
                'thumb' => $thumb_url,
                'full' => $full_url,
                'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                'embedded_watermark' => !empty($protected_urls['embedded']),
            ];
        }

        return $gallery_items;
    }

    private static function build_overview_report_entry($post, $deepest_category = null, $post_number_data = []) {
        if (!($post instanceof WP_Post)) {
            return null;
        }

        $post_id = (int) $post->ID;
        $event_datetime = self::get_event_datetime($post_id);
        $gallery_ids = get_post_meta($post_id, '_feu_einsatz_gallery', true);
        $card_image = self::get_card_image_data($post_id, 'medium_large');
        $street = self::strip_house_number_from_street(get_post_meta($post_id, '_feu_einsatz_strasse', true));
        $city = trim((string) get_post_meta($post_id, '_feu_einsatz_stadt', true));
        $plz = trim((string) get_post_meta($post_id, '_feu_einsatz_plz', true));
        $category_data = null;

        if ($deepest_category instanceof WP_Term) {
            $category_data = [
                'id' => (int) $deepest_category->term_id,
                'name' => (string) $deepest_category->name,
                'slug' => (string) $deepest_category->slug,
            ];
        } elseif (is_array($deepest_category) && !empty($deepest_category['name'])) {
            $category_data = [
                'id' => isset($deepest_category['id']) ? (int) $deepest_category['id'] : 0,
                'name' => (string) $deepest_category['name'],
                'slug' => isset($deepest_category['slug']) ? (string) $deepest_category['slug'] : '',
            ];
        }

        return [
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'permalink' => get_permalink($post_id),
            'excerpt' => wp_trim_words(get_the_excerpt($post_id), 28),
            'image_url' => $card_image['url'],
            'image_attachment_id' => isset($card_image['attachment_id']) ? (int) $card_image['attachment_id'] : 0,
            'image_is_map' => !empty($card_image['is_map']),
            'event_date' => $event_datetime['date'],
            'event_time' => $event_datetime['time'],
            'category' => $category_data,
            'number' => isset($post_number_data['number']) ? (int) $post_number_data['number'] : 0,
            'year' => isset($post_number_data['year']) ? (int) $post_number_data['year'] : (int) self::get_overview_report_year($post_id),
            'has_photos' => !empty(self::normalize_related_ids($gallery_ids)),
            'street' => $street,
            'postcode' => $plz,
            'city' => $city,
            'location' => self::build_location_label($street, $plz, $city),
        ];
    }

    private static function get_posts_page_breadcrumb_url() {
        $posts_page_id = (int) get_option('page_for_posts');

        if ($posts_page_id > 0) {
            $posts_page_url = get_permalink($posts_page_id);

            if ($posts_page_url) {
                return $posts_page_url;
            }
        }

        return home_url('/');
    }

    private static function build_single_breadcrumb_items($post, $deepest_category = null) {
        if (!($post instanceof WP_Post)) {
            return [];
        }

        $items = [
            [
                'label' => get_bloginfo('name'),
                'url' => home_url('/'),
            ],
        ];

        $root_category = self::find_root_category();

        if ($root_category instanceof WP_Term) {
            $root_category_link = get_category_link($root_category);
            $items[] = [
                'label' => (string) $root_category->name,
                'url' => !is_wp_error($root_category_link) ? $root_category_link : '',
            ];
        } else {
            $items[] = [
                'label' => __('Einsaetze', 'feuer-einsatzberichte'),
                'url' => home_url('/'),
            ];
        }

        if ($deepest_category instanceof WP_Term) {
            $category_link = get_category_link($deepest_category);

            if (!($root_category instanceof WP_Term) || (int) $root_category->term_id !== (int) $deepest_category->term_id) {
                $items[] = [
                    'label' => (string) $deepest_category->name,
                    'url' => !is_wp_error($category_link) ? $category_link : '',
                ];
            }
        }

        $items[] = [
            'label' => get_the_title($post->ID),
            'url' => '',
        ];

        return $items;
    }

    private static function get_related_report_entries($current_post_id, $limit = 4) {
        $current_post_id = (int) $current_post_id;
        $limit = max(1, (int) $limit);
        $root_category = self::find_root_category();
        $root_category_id = $root_category instanceof WP_Term ? (int) $root_category->term_id : 0;

        $query_args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => max(12, $limit * 4),
            'post__not_in' => [$current_post_id],
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => '_feu_einsatz_einsatzbericht',
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
            'no_found_rows' => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => true,
        ];

        if ($root_category_id > 0) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => [$root_category_id],
                    'include_children' => true,
                ],
            ];
        }

        $related_posts = get_posts($query_args);

        if (empty($related_posts)) {
            return [];
        }

        $entries = [];

        foreach ((array) $related_posts as $related_post) {
            if (!($related_post instanceof WP_Post)) {
                continue;
            }

            $related_post_id = (int) $related_post->ID;
            $deepest_category = self::get_deepest_category($related_post_id);
            $post_number_data = self::get_report_number_data($related_post_id);

            $report_entry = self::build_overview_report_entry($related_post, $deepest_category, $post_number_data);

            if (is_array($report_entry)) {
                $entries[] = $report_entry;

                if (count($entries) >= $limit) {
                    break;
                }
            }
        }

        return $entries;
    }

    public static function get_single_context($post = null) {
        static $single_context_cache = [];

        $post = $post instanceof WP_Post ? $post : get_post($post ?: get_queried_object_id());

        if (!($post instanceof WP_Post)) {
            return [];
        }

        $post_id = (int) $post->ID;
        $is_public_request = !is_admin();
        $transient_key = '';
        // A request may save a report and render its preview again before PHP
        // exits. Keying only by post ID served the former in-memory context in
        // that case, even though the post meta had already changed.
        $memory_cache_key = $post_id . '_' . self::get_single_context_cache_fingerprint($post);

        if (isset($single_context_cache[$memory_cache_key])) {
            return $single_context_cache[$memory_cache_key];
        }

        if ($is_public_request) {
            $transient_key = self::get_single_context_transient_key($post);

            if ('' !== $transient_key) {
                $cached_context = get_transient($transient_key);

                if (is_array($cached_context) && !empty($cached_context)) {
                    $single_context_cache[$memory_cache_key] = $cached_context;
                    return $single_context_cache[$memory_cache_key];
                }
            }
        }

        $street = trim((string) get_post_meta($post_id, '_feu_einsatz_strasse', true));
        $display_street = self::strip_house_number_from_street($street);
        $postcode = trim((string) get_post_meta($post_id, '_feu_einsatz_plz', true));
        $city = trim((string) get_post_meta($post_id, '_feu_einsatz_stadt', true));
        $event_date = trim((string) get_post_meta($post_id, '_feu_einsatz_datum', true));
        $event_time = trim((string) get_post_meta($post_id, '_feu_einsatz_uhrzeit', true));
        $latitude = get_post_meta($post_id, '_feu_einsatz_latitude', true);
        $longitude = get_post_meta($post_id, '_feu_einsatz_longitude', true);
        $participant_ids = self::normalize_related_ids(get_post_meta($post_id, '_feu_einsatz_teilnehmer', true));
        $organization_ids = self::normalize_related_ids(get_post_meta($post_id, '_feu_einsatz_organisationen', true));
        $gallery_ids = self::normalize_related_ids(get_post_meta($post_id, '_feu_einsatz_gallery', true));
        $post_street_cache = FEU_Einsatz_Street_Cache::get_post_cache($post_id);
        $street_geometry = $post_street_cache ? $post_street_cache['geometry'] : [];
        $street_center = $post_street_cache ? $post_street_cache['center'] : [];

        if ('' !== $street) {
            $street_data = FEU_Einsatz_Street_Cache::get($street, $postcode, $city);

            if (
                $street_data
                && (
                    empty($street_geometry)
                    || self::is_geometry_payload_richer($street_data, ['geometry' => $street_geometry, 'center' => $street_center])
                )
            ) {
                FEU_Einsatz_Street_Cache::set_post_cache($post_id, $street_data);
                $street_geometry = $street_data['geometry'];
                $street_center = $street_data['center'];
            }
        }

        // Public pages normally render only from the local cache. If a plugin
        // update invalidated an old cache, repair that one report once on the
        // server instead of publishing an empty map until an editor opens it.
        $geometry_source = (string) get_post_meta($post_id, self::FOCUSED_GEOMETRY_SOURCE_META, true);
        $has_complete_geometry_source = in_array(
            $geometry_source,
            [self::FOCUSED_GEOMETRY_SOURCE, self::NOMINATIM_GEOMETRY_SOURCE],
            true
        );
        $needs_focused_geometry = !$has_complete_geometry_source
            && (empty($street_geometry) || self::should_attempt_editor_geometry_refresh([
                'geometry' => $street_geometry,
                'center' => $street_center,
            ]));
        $needs_precise_incident_anchor = self::report_needs_precise_incident_anchor($post_id, $street, $postcode, $city);

        if (
            $is_public_request
            && '' !== $street
            && (
                empty($street_geometry)
                || !is_numeric($latitude)
                || !is_numeric($longitude)
                || $needs_focused_geometry
                || $needs_precise_incident_anchor
            )
        ) {
            $prime_lock_key = self::get_single_geometry_prime_lock_key($post_id);

            if (false === get_transient($prime_lock_key)) {
                set_transient(
                    $prime_lock_key,
                    1,
                    defined('MINUTE_IN_SECONDS') ? MINUTE_IN_SECONDS * 10 : 600
                );

                $primed_map_data = self::prime_public_map_data($post_id, $street, $postcode, $city);

                if (is_array($primed_map_data)) {
                    if (!empty($primed_map_data['coordinates']['lat']) && !empty($primed_map_data['coordinates']['lng'])) {
                        $latitude = (string) $primed_map_data['coordinates']['lat'];
                        $longitude = (string) $primed_map_data['coordinates']['lng'];
                    }
                    if (!empty($primed_map_data['geometry'])) {
                        $street_geometry = $primed_map_data['geometry'];
                        $street_center = !empty($primed_map_data['center']) ? $primed_map_data['center'] : $street_center;
                    }
                }
            }
        }

        if (
            is_admin()
            && '' !== $street
            && empty($street_geometry)
            && is_numeric($latitude)
            && is_numeric($longitude)
            && current_user_can('edit_post', $post_id)
        ) {
            $prime_lock_key = self::get_single_geometry_prime_lock_key($post_id);

            if (false === get_transient($prime_lock_key)) {
                set_transient(
                    $prime_lock_key,
                    1,
                    defined('MINUTE_IN_SECONDS') ? MINUTE_IN_SECONDS * 30 : 1800
                );

                $primed_geometry_payload = self::request_street_geometry_data($street, [
                    'lat' => (float) $latitude,
                    'lng' => (float) $longitude,
                ]);

                if ($primed_geometry_payload) {
                    FEU_Einsatz_Street_Cache::set($street, $postcode, $city, $primed_geometry_payload);
                    FEU_Einsatz_Street_Cache::set_post_cache($post_id, $primed_geometry_payload);
                    $street_geometry = $primed_geometry_payload['geometry'];
                    $street_center = $primed_geometry_payload['center'];
                }
            }
        }

        // Secondary streets are intentionally resolved by the background map
        // job and read from the shared cache here. A public page never starts
        // five additional remote lookups merely because a large incident was
        // configured with several street names.
        $extra_streets = self::get_report_map_extra_streets($post_id);
        $primary_street_key = strtolower(remove_accents(self::strip_house_number_from_street($street)));
        foreach ($extra_streets as $extra_street) {
            if (strtolower(remove_accents($extra_street)) === $primary_street_key) {
                continue;
            }

            $extra_payload = FEU_Einsatz_Street_Cache::get($extra_street, $postcode, $city);
            if (is_array($extra_payload) && !empty($extra_payload['geometry'])) {
                $street_geometry = array_merge($street_geometry, (array) $extra_payload['geometry']);
            }
        }

        if (
            is_admin()
            && '' !== $street
            && is_numeric($latitude)
            && is_numeric($longitude)
            && current_user_can('edit_post', $post_id)
        ) {
            $current_geometry_payload = [
                'geometry' => $street_geometry,
                'center' => $street_center,
            ];

            if (self::should_attempt_editor_geometry_refresh($current_geometry_payload)) {
                $refresh_lock_key = self::get_single_geometry_refresh_lock_key($post_id);

                if (false === get_transient($refresh_lock_key)) {
                    set_transient(
                        $refresh_lock_key,
                        1,
                        defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400
                    );

                    $refreshed_geometry_payload = self::request_focused_street_geometry_data($street, [
                        'lat' => (float) $latitude,
                        'lng' => (float) $longitude,
                    ]);

                    if (
                        $refreshed_geometry_payload
                        && self::is_geometry_payload_richer($refreshed_geometry_payload, $current_geometry_payload)
                    ) {
                        FEU_Einsatz_Street_Cache::set($street, $postcode, $city, $refreshed_geometry_payload);
                        FEU_Einsatz_Street_Cache::set_post_cache($post_id, $refreshed_geometry_payload);
                        $street_geometry = $refreshed_geometry_payload['geometry'];
                        $street_center = $refreshed_geometry_payload['center'];
                    }
                }
            }
        }

        $participant_names = self::get_participant_names($participant_ids);
        $organization_details = self::get_organization_details($organization_ids);
        $deepest_category = self::get_deepest_category($post_id);
        $related_reports_display = self::normalize_related_reports_display(get_option('feu_einsatz_related_reports_display', 'cards'));
        $related_reports_limit = max(1, min(12, absint(get_option('feu_einsatz_related_reports_count', 6))));
        $related_reports = 'disabled' === $related_reports_display
            ? []
            : self::get_related_report_entries($post_id, $related_reports_limit);
        $default_card_variant = self::normalize_report_card_variant(get_option('feu_einsatz_default_card_variant', 'modern'));
        $single_info_fields = self::normalize_single_info_fields(
            get_option('feu_einsatz_single_info_fields', self::get_default_single_info_fields())
        );
        $single_map_display_mode = self::normalize_single_map_display_mode(get_option('feu_einsatz_single_map_display_mode', 'live'));
        $single_map_privacy_mode = self::normalize_single_map_privacy_mode(get_option('feu_einsatz_single_map_privacy_mode', 'always'));
        $single_live_map_show_station = 1 === (int) get_option('feu_einsatz_single_live_map_show_station', 0);
        $single_desaturate_organizations = 1 === (int) get_option('feu_einsatz_single_desaturate_organizations', 0);
        $breadcrumb_items = self::build_single_breadcrumb_items($post, $deepest_category);
        $map_height = absint(get_option('feu_einsatz_map_height', 500));
        $photo_watermark_enabled = 1 === (int) get_option('feu_einsatz_photo_watermark_enabled', 1);
        $photo_watermark_text = trim((string) get_option('feu_einsatz_photo_watermark_text', get_bloginfo('name')));
        $can_generate_runtime_assets = current_user_can('edit_post', $post_id);
        $map_fallback_image_url = self::get_generated_map_preview_public_url($post_id);
        $station_feature = self::get_station_feature_from_settings();
        $single_live_map_station_feature = $single_live_map_show_station && is_array($station_feature)
            ? $station_feature
            : false;
        $map_fallback_alt = sprintf(
            __('Kartenvorschau zu %s', 'feuer-einsatzberichte'),
            get_the_title($post_id)
        );
        $single_map_fallback_image = self::get_single_map_fallback_image_data($post_id, $gallery_ids, 'large');

        $fallback_center = [];
        if (is_array($street_center) && isset($street_center[0], $street_center[1])) {
            $fallback_center = [floatval($street_center[0]), floatval($street_center[1])];
        } elseif (is_numeric($latitude) && is_numeric($longitude)) {
            $fallback_center = [floatval($latitude), floatval($longitude)];
        }

        $report_highlight_settings = self::get_report_street_highlight_settings($post_id);
        $highlight = self::apply_street_highlight_mode($street_geometry, $latitude, $longitude, $report_highlight_settings);
        $street_geometry = $highlight['geometry'];
        $area_points = self::get_report_map_area_points($post_id);
        $map_address = trim(implode(', ', array_filter([
            $display_street,
            trim($postcode . ' ' . $city),
        ])));
        $public_precision = self::get_report_map_public_precision($post_id);
        $public_coordinates = self::get_report_map_public_coordinates($post_id, $latitude, $longitude);
        $public_map_hidden = false;

        if (!is_admin() && ('hidden' === $public_precision || !$public_coordinates)) {
            $public_map_hidden = true;
            $street_geometry = [];
            $area_points = [];
            $fallback_center = [];
            $map_address = '';
            // The template returns before it emits its JSON config, but keep
            // the context itself safe as well for filters and custom themes.
            $latitude = null;
            $longitude = null;
        } elseif (!is_admin() && 'exact' !== $public_precision) {
            // A rounded point must not be undermined by an exact street line
            // or a hand-drawn incident polygon. Public output therefore uses
            // a single, deliberately broad circle around the rounded point.
            $latitude = $public_coordinates['lat'];
            $longitude = $public_coordinates['lng'];
            $street_geometry = [];
            $area_points = [];
            $highlight['mode'] = 'radius';
            $highlight['radius_meters'] = max((int) $highlight['radius_meters'], (int) $public_coordinates['meters']);
            $fallback_center = [(float) $latitude, (float) $longitude];
            $map_address = __('Ungefährer Einsatzbereich', 'feuer-einsatzberichte');
        }
        $local_map_preview_markup = '';
        $radius_has_public_preview = 'radius' === ($highlight['mode'] ?? '')
            && is_numeric($latitude)
            && is_numeric($longitude);

        if (is_admin() || $radius_has_public_preview) {
            // A circle around a verified incident coordinate is meaningful on
            // its own. Keep an SVG fallback for public radius maps as well, so
            // a browser-side Leaflet error can never degrade into the generic
            // "not enough map data" placeholder.
            $local_map_preview_markup = self::build_local_map_preview_markup([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'center' => $fallback_center,
                'geometry' => $street_geometry,
                'area_geometry' => $area_points,
                'address' => $map_address,
                'height' => $map_height,
                'station' => false,
                'show_station' => false,
                'highlight_mode' => $highlight['mode'],
                'highlight_radius_meters' => $highlight['radius_meters'],
            ]);
        }
        $map_canvas_id = 'feu-einsatz-einsatz-map-' . $post_id;
        $map_config = [
            'title' => get_the_title($post_id),
            'street' => 'exact' === $public_precision || is_admin() ? $display_street : '',
            'plz' => 'exact' === $public_precision || is_admin() ? $postcode : '',
            'city' => 'exact' === $public_precision || is_admin() ? $city : '',
            'address' => $map_address,
            'latitude' => is_numeric($latitude) ? (float) $latitude : null,
            'longitude' => is_numeric($longitude) ? (float) $longitude : null,
            'center' => $fallback_center,
            'geometry' => is_array($street_geometry) ? $street_geometry : [],
            'area_geometry' => $area_points,
            'highlight_mode' => $highlight['mode'],
            'highlight_radius_meters' => $highlight['radius_meters'],
            'public_precision' => $public_precision,
            'zoom' => absint(get_option('feu_einsatz_map_zoom', 16)),
            'station' => is_array($single_live_map_station_feature) ? [
                'label' => isset($single_live_map_station_feature['label']) ? (string) $single_live_map_station_feature['label'] : '',
                'address' => isset($single_live_map_station_feature['address']) ? (string) $single_live_map_station_feature['address'] : '',
                'latitude' => isset($single_live_map_station_feature['latitude']) ? (float) $single_live_map_station_feature['latitude'] : null,
                'longitude' => isset($single_live_map_station_feature['longitude']) ? (float) $single_live_map_station_feature['longitude'] : null,
                'logo_url' => isset($single_live_map_station_feature['logo_url']) ? (string) $single_live_map_station_feature['logo_url'] : '',
                'logo_size' => isset($single_live_map_station_feature['logo_size']) ? (int) $single_live_map_station_feature['logo_size'] : 40,
            ] : null,
            'stroke_width' => max(3, min(18, absint(get_option('feu_einsatz_map_preview_stroke_width', 8)))),
            'highlight_color' => sanitize_hex_color((string) get_option('feu_einsatz_map_preview_highlight_color', '#d92d20')) ?: '#d92d20',
            'label_style' => in_array(get_option('feu_einsatz_map_label_style', 'bubble'), ['bubble', 'badge', 'plain'], true)
                ? get_option('feu_einsatz_map_label_style', 'bubble')
                : 'bubble',
            'label_text_color' => sanitize_hex_color((string) get_option('feu_einsatz_map_label_text_color', '#ffffff')) ?: '#ffffff',
        ];
        $gallery_items = self::build_gallery_items($gallery_ids, $can_generate_runtime_assets);
        $comments_enabled = self::is_comments_enabled($post_id);
        $comment_count = $comments_enabled ? (int) get_comments_number($post_id) : 0;
        $report_comments = [];

        if ($comments_enabled && $comment_count > 0) {
            $report_comments = get_comments([
                'post_id' => $post_id,
                'status' => 'approve',
                'type' => 'comment',
                'hierarchical' => 'threaded',
                'orderby' => 'comment_date_gmt',
                'order' => 'ASC',
                'update_comment_meta_cache' => false,
                'update_comment_post_cache' => false,
            ]);
            $comment_count = count($report_comments);
        }
        $formatted_event_date = '';

        if ('' !== $event_date) {
            $event_timestamp = strtotime($event_date);

            if (false !== $event_timestamp) {
                $formatted_event_date = date_i18n('d.m.Y', $event_timestamp);
            }
        }

        $single_context_cache[$memory_cache_key] = [
            'post' => $post,
            'breadcrumbs' => $breadcrumb_items,
            'display' => [
                'single_info_fields' => $single_info_fields,
                'single_map_display_mode' => $single_map_display_mode,
                'single_map_privacy_mode' => $single_map_privacy_mode,
                'map_public_precision' => $public_precision,
                'single_desaturate_organizations' => $single_desaturate_organizations,
                'overview_show_stats' => 1 === (int) get_option('feu_einsatz_overview_show_stats', 1),
                'overview_show_year_filter' => 1 === (int) get_option('feu_einsatz_overview_show_year_filter', 1),
            ],
            'report' => [
                'id' => $post_id,
                'title' => get_the_title($post_id),
                'street' => $display_street,
                'postcode' => $postcode,
                'city' => $city,
                'date_raw' => $event_date,
                'date_display' => $formatted_event_date,
                'time' => $event_time,
                'category' => $deepest_category instanceof WP_Term ? [
                    'id' => (int) $deepest_category->term_id,
                    'name' => (string) $deepest_category->name,
                ] : null,
                'organizations' => $organization_details,
                'participant_names' => $participant_names,
                'participant_count' => count($participant_names),
                'content' => (string) $post->post_content,
                'location' => self::build_location_label($display_street, $postcode, $city),
            ],
            'map' => [
                'height' => $map_height,
                'canvas_id' => $map_canvas_id,
                'config' => $map_config,
                'has_live_data' => !empty($map_config['geometry'])
                    || !empty($map_config['area_geometry'])
                    || (is_numeric($map_config['latitude']) && is_numeric($map_config['longitude']))
                    || (is_array($map_config['center']) && isset($map_config['center'][0], $map_config['center'][1]))
                    || (!empty($map_config['station']) && is_numeric($map_config['station']['latitude'] ?? null) && is_numeric($map_config['station']['longitude'] ?? null)),
                'preview_markup' => $local_map_preview_markup,
                'fallback_image_url' => $map_fallback_image_url,
                'fallback_alt' => $map_fallback_alt,
                'post_image_url' => $single_map_fallback_image['url'],
                'post_image_attachment_id' => (int) $single_map_fallback_image['attachment_id'],
                'post_image_alt' => $single_map_fallback_image['alt'],
                'latitude' => is_numeric($latitude) ? (float) $latitude : null,
                'longitude' => is_numeric($longitude) ? (float) $longitude : null,
                'publicly_hidden' => $public_map_hidden,
            ],
            'gallery' => [
                'items' => $gallery_items,
                'watermark_enabled' => $photo_watermark_enabled,
                'watermark_text' => $photo_watermark_text,
            ],
            'related' => [
                'display' => $related_reports_display,
                'items' => $related_reports,
                'card_variant' => $default_card_variant,
            ],
            'comments' => [
                'enabled' => $comments_enabled,
                'items' => $report_comments,
                'count' => $comment_count,
                'title' => sprintf(
                    _n('%d Kommentar', '%d Kommentare', $comment_count, 'feuer-einsatzberichte'),
                    $comment_count
                ),
            ],
        ];

        if ($is_public_request && '' !== $transient_key) {
            set_transient(
                $transient_key,
                $single_context_cache[$memory_cache_key],
                defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600
            );
        }

        return $single_context_cache[$memory_cache_key];
    }

    public static function get_report_number_data($post_id) {
        static $number_cache = [];

        $post_id = absint($post_id);

        if ($post_id < 1) {
            return [
                'number' => 0,
                'year' => 0,
            ];
        }

        if (isset($number_cache[$post_id])) {
            return $number_cache[$post_id];
        }

        $root_category = self::find_root_category();
        $root_category_id = $root_category instanceof WP_Term ? (int) $root_category->term_id : 0;
        $year = (int) self::get_overview_report_year($post_id);

        if ($root_category_id < 1 || $year < 1) {
            $number_cache[$post_id] = [
                'number' => 0,
                'year' => $year,
            ];

            return $number_cache[$post_id];
        }

        $all_category_ids = self::get_overview_category_ids($root_category_id);
        $overview_post_ids = self::get_overview_index_post_ids($all_category_ids);
        $index_data = self::build_overview_index_data($overview_post_ids, $root_category_id, '');
        $post_number_map = isset($index_data['post_number_map']) && is_array($index_data['post_number_map'])
            ? $index_data['post_number_map']
            : [];

        if (!empty($post_number_map[$post_id]) && is_array($post_number_map[$post_id])) {
            $number_cache[$post_id] = [
                'number' => isset($post_number_map[$post_id]['number']) ? (int) $post_number_map[$post_id]['number'] : 0,
                'year' => isset($post_number_map[$post_id]['year']) ? (int) $post_number_map[$post_id]['year'] : $year,
            ];

            return $number_cache[$post_id];
        }

        $number_cache[$post_id] = [
            'number' => 0,
            'year' => $year,
        ];

        return $number_cache[$post_id];
    }

    private static function get_overview_report_year($post_id) {
        $event_date = trim((string) get_post_meta($post_id, '_feu_einsatz_datum', true));

        if ('' !== $event_date) {
            $normalized_event_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)
                ? $event_date
                : '';

            if ('' === $normalized_event_date) {
                $event_timestamp = strtotime($event_date);

                if (false !== $event_timestamp) {
                    return date('Y', $event_timestamp);
                }
            } else {
                return substr($normalized_event_date, 0, 4);
            }
        }

        return mysql2date('Y', get_post_field('post_date', $post_id, 'raw'), false);
    }

    private static function get_overview_report_timestamp($post_id) {
        $event_date = trim((string) get_post_meta($post_id, '_feu_einsatz_datum', true));
        $event_time = trim((string) get_post_meta($post_id, '_feu_einsatz_uhrzeit', true));

        if ('' !== $event_date) {
            $timestamp = strtotime(trim($event_date . ' ' . ('' !== $event_time ? $event_time : '00:00')));

            if (false !== $timestamp) {
                return (int) $timestamp;
            }
        }

        $post_date = get_post_field('post_date', $post_id, 'raw');
        $post_timestamp = $post_date ? strtotime((string) $post_date) : false;

        return false !== $post_timestamp ? (int) $post_timestamp : 0;
    }

    public static function is_comments_enabled($post_id) {
        $post_id = absint($post_id);

        if (!$post_id) {
            return false;
        }

        $stored_value = get_post_meta($post_id, '_feu_einsatz_comments_enabled', true);

        if ('' !== $stored_value) {
            return '1' === (string) $stored_value;
        }

        return 1 === (int) get_option('feu_einsatz_default_comments_enabled', 0);
    }

    public static function get_organization_details($organization_ids) {
        global $wpdb;

        $organization_ids = array_values(array_unique(array_filter(array_map('absint', (array) $organization_ids))));

        if (empty($organization_ids)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($organization_ids), '%d'));
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, color, post_link FROM {$wpdb->prefix}feu_einsatz_organisationen WHERE id IN ({$placeholders})",
                $organization_ids
            )
        );

        if (!is_array($results)) {
            return [];
        }

        $indexed = [];

        foreach ($results as $result) {
            $indexed[(int) $result->id] = [
                'id' => (int) $result->id,
                'name' => (string) $result->name,
                'color' => sanitize_hex_color($result->color) ?: '#0a4b78',
                'post_link' => esc_url_raw(isset($result->post_link) ? (string) $result->post_link : ''),
            ];
        }

        $ordered = [];

        foreach ($organization_ids as $organization_id) {
            if (isset($indexed[$organization_id])) {
                $ordered[] = $indexed[$organization_id];
            }
        }

        return $ordered;
    }

    private static function build_overview_context_base($root_category, $selected_year, $paged, $posts_per_page) {
        return [
            'root_category' => $root_category,
            'selected_year' => $selected_year,
            'paged' => $paged,
            'posts_per_page' => $posts_per_page,
            'all_years' => [],
            'year_counts' => [],
            'scoped_category_stats' => [],
            'scoped_total_posts' => 0,
            'posts' => [],
            'max_num_pages' => 1,
            'warning' => '',
            'display' => [
                'overview_show_stats' => 1 === (int) get_option('feu_einsatz_overview_show_stats', 1),
                'overview_show_year_filter' => 1 === (int) get_option('feu_einsatz_overview_show_year_filter', 1),
            ],
        ];
    }

    private static function get_overview_category_ids($root_category_id) {
        $category_ids = get_term_children((int) $root_category_id, 'category');
        $category_ids[] = (int) $root_category_id;

        return array_values(array_unique(array_map('intval', $category_ids)));
    }

    private static function get_overview_index_post_ids($category_ids) {
        $normalized_category_ids = array_values(array_unique(array_map('intval', (array) $category_ids)));
        sort($normalized_category_ids, SORT_NUMERIC);
        $cache_key = 'feu_overview_index_' . md5(get_current_blog_id() . '|' . wp_json_encode($normalized_category_ids));
        $cached_post_ids = get_transient($cache_key);

        if (is_array($cached_post_ids)) {
            return array_values(array_map('intval', $cached_post_ids));
        }

        $index_query_args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'tax_query' => [
                [
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => $normalized_category_ids,
                    'include_children' => true,
                ],
            ],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => true,
        ];
        $post_ids = array_values(array_map('intval', (array) get_posts($index_query_args)));

        if (empty($post_ids)) {
            set_transient($cache_key, [], 10 * MINUTE_IN_SECONDS);
            return [];
        }

        update_meta_cache('post', $post_ids);
        update_object_term_cache($post_ids, 'post');

        $post_timestamps = [];

        foreach ($post_ids as $post_id) {
            $post_timestamps[$post_id] = self::get_overview_report_timestamp($post_id);
        }

        usort($post_ids, static function($left, $right) use ($post_timestamps) {
            $left_timestamp = isset($post_timestamps[$left]) ? (int) $post_timestamps[$left] : 0;
            $right_timestamp = isset($post_timestamps[$right]) ? (int) $post_timestamps[$right] : 0;

            if ($left_timestamp === $right_timestamp) {
                return (int) $right <=> (int) $left;
            }

            return $right_timestamp <=> $left_timestamp;
        });

        set_transient($cache_key, $post_ids, 10 * MINUTE_IN_SECONDS);

        return $post_ids;
    }

    private static function build_overview_index_data($overview_post_ids, $root_category_id, $selected_year) {
        $posts_by_year = [];
        $post_number_map = [];
        $deepest_category_map = [];
        $year_counts = [];
        $scoped_category_stats = [];
        $scoped_total_posts = 0;

        foreach ((array) $overview_post_ids as $overview_post_id) {
            $overview_post_id = (int) $overview_post_id;
            $post_year = self::get_overview_report_year($overview_post_id);

            if (!$post_year) {
                continue;
            }

            if (!isset($year_counts[$post_year])) {
                $year_counts[$post_year] = 0;
                $posts_by_year[$post_year] = [];
            }

            $year_counts[$post_year]++;
            $posts_by_year[$post_year][] = $overview_post_id;

            $deepest_category = self::get_deepest_category($overview_post_id, $root_category_id);

            if ($deepest_category) {
                $deepest_category_map[$overview_post_id] = [
                    'id' => (int) $deepest_category->term_id,
                    'name' => (string) $deepest_category->name,
                ];

                if ('' === $selected_year || (string) $post_year === (string) $selected_year) {
                    if (!isset($scoped_category_stats[$deepest_category->term_id])) {
                        $scoped_category_stats[$deepest_category->term_id] = [
                            'name' => (string) $deepest_category->name,
                            'count' => 0,
                        ];
                    }

                    $scoped_category_stats[$deepest_category->term_id]['count']++;
                }
            } else {
                $deepest_category_map[$overview_post_id] = null;
            }

            if ('' === $selected_year || (string) $post_year === (string) $selected_year) {
                $scoped_total_posts++;
            }
        }

        foreach ($posts_by_year as $year => $post_ids_for_year) {
            $total_posts_for_year = count($post_ids_for_year);

            foreach ($post_ids_for_year as $index => $post_id_for_year) {
                $post_number_map[$post_id_for_year] = [
                    'number' => $total_posts_for_year - $index,
                    'year' => (int) $year,
                ];
            }
        }

        $all_years = array_keys($year_counts);
        rsort($all_years, SORT_NUMERIC);

        uasort($scoped_category_stats, static function($left, $right) {
            if ((int) $left['count'] === (int) $right['count']) {
                return strcmp($left['name'], $right['name']);
            }

            return (int) $right['count'] <=> (int) $left['count'];
        });

        return [
            'posts_by_year' => $posts_by_year,
            'post_number_map' => $post_number_map,
            'deepest_category_map' => $deepest_category_map,
            'year_counts' => $year_counts,
            'all_years' => $all_years,
            'scoped_category_stats' => $scoped_category_stats,
            'scoped_total_posts' => $scoped_total_posts,
        ];
    }

    private static function get_overview_paged_posts($scoped_post_ids, $paged, $posts_per_page) {
        $scoped_post_ids = array_values(array_map('intval', (array) $scoped_post_ids));
        $max_num_pages = max(1, (int) ceil(max(1, count($scoped_post_ids)) / $posts_per_page));
        $paged = min(max(1, (int) $paged), $max_num_pages);
        $page_offset = max(0, ($paged - 1) * $posts_per_page);
        $page_post_ids = array_slice($scoped_post_ids, $page_offset, $posts_per_page);
        $page_posts = [];

        if (!empty($page_post_ids)) {
            $page_posts = get_posts([
                'post_type' => 'post',
                'post_status' => 'publish',
                'post__in' => $page_post_ids,
                'orderby' => 'post__in',
                'posts_per_page' => count($page_post_ids),
                'no_found_rows' => true,
            ]);

            if (!empty($page_post_ids)) {
                update_meta_cache('post', $page_post_ids);
            }
        }

        return [
            'paged' => $paged,
            'max_num_pages' => $max_num_pages,
            'page_posts' => is_array($page_posts) ? $page_posts : [],
        ];
    }

    private static function build_overview_page_entries($page_posts, $deepest_category_map, $post_number_map) {
        $entries = [];

        foreach ((array) $page_posts as $page_post) {
            if (!($page_post instanceof WP_Post)) {
                continue;
            }

            $current_post_id = (int) $page_post->ID;
            $deepest_category = isset($deepest_category_map[$current_post_id]) ? $deepest_category_map[$current_post_id] : null;
            $post_number_data = isset($post_number_map[$current_post_id]) ? $post_number_map[$current_post_id] : [
                'number' => 0,
                'year' => (int) self::get_overview_report_year($current_post_id),
            ];
            $report_entry = self::build_overview_report_entry($page_post, $deepest_category, $post_number_data);

            if (is_array($report_entry)) {
                $entries[] = $report_entry;
            }
        }

        return $entries;
    }

    public static function get_overview_context($args = []) {
        static $context_cache = [];

        $args = wp_parse_args($args, [
            'selected_year' => '',
            'paged' => 1,
            'posts_per_page' => 10,
        ]);

        $selected_year = self::sanitize_overview_year($args['selected_year']);
        $paged = max(1, (int) $args['paged']);
        $posts_per_page = max(1, (int) $args['posts_per_page']);
        $cache_key = implode(':', [$selected_year, $paged, $posts_per_page]);

        if (isset($context_cache[$cache_key])) {
            return $context_cache[$cache_key];
        }

        $root_category = self::find_root_category();
        $context = self::build_overview_context_base($root_category, $selected_year, $paged, $posts_per_page);

        if (!$root_category) {
            $context['warning'] = __('Kategorie "Einsaetze" wurde nicht gefunden.', 'feuer-einsatzberichte');

            $context_cache[$cache_key] = $context;

            return $context_cache[$cache_key];
        }

        $root_category_id = (int) $root_category->term_id;
        $all_category_ids = self::get_overview_category_ids($root_category_id);
        $overview_post_ids = self::get_overview_index_post_ids($all_category_ids);
        $index_data = self::build_overview_index_data($overview_post_ids, $root_category_id, $selected_year);
        $scoped_post_ids = $selected_year
            ? (isset($index_data['posts_by_year'][$selected_year]) ? $index_data['posts_by_year'][$selected_year] : [])
            : array_values(array_map('intval', $overview_post_ids));
        $page_data = self::get_overview_paged_posts($scoped_post_ids, $paged, $posts_per_page);

        $context['all_years'] = $index_data['all_years'];
        $context['year_counts'] = $index_data['year_counts'];
        $context['scoped_category_stats'] = $index_data['scoped_category_stats'];
        $context['scoped_total_posts'] = $index_data['scoped_total_posts'];
        $context['posts'] = self::build_overview_page_entries(
            $page_data['page_posts'],
            $index_data['deepest_category_map'],
            $index_data['post_number_map']
        );
        $context['paged'] = $page_data['paged'];
        $context['max_num_pages'] = $page_data['max_num_pages'];

        $context_cache[$cache_key] = $context;

        return $context_cache[$cache_key];
    }
}




