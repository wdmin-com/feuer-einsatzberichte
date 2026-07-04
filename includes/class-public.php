<?php
if (!defined('ABSPATH')) {
    exit;
}

class FEU_Einsatz_Public {

    private $db;
    private $overview_context_cache = [];
    private $area_page_context_cache = null;

    public function __construct($database) {
        $this->db = $database;
        $this->init_hooks();
    }

    private function get_shortcode_tag_map() {
        return [
            'count' => ['feu_einsatz_anzahl'],
            'current' => ['feu_einsatz_aktuell'],
            'previous' => ['feu_einsatz_vorjahr'],
            'overview_page' => ['feu_einsatz_seite'],
            'overview_list' => ['feu_einsatz_liste'],
            'overview_sidebar' => ['feu_einsatz_sidebar'],
            'latest' => ['feu_einsatz_letzte'],
            'alarm_list' => ['feu_einsatz_alarm_liste'],
            'area_page' => ['feu_einsatz_gebiet'],
        ];
    }

    private function get_primary_shortcode_tag($key) {
        $map = $this->get_shortcode_tag_map();

        return isset($map[$key][0]) ? $map[$key][0] : '';
    }

    private function register_shortcode_aliases($key, $callback) {
        $map = $this->get_shortcode_tag_map();

        if (empty($map[$key])) {
            return;
        }

        foreach ($map[$key] as $tag) {
            add_shortcode($tag, $callback);
        }
    }

    private function has_shortcode_alias($content, $key) {
        $map = $this->get_shortcode_tag_map();

        if (!isset($map[$key])) {
            return false;
        }

        foreach ($map[$key] as $tag) {
            if (has_shortcode($content, $tag)) {
                return true;
            }
        }

        return false;
    }

    private function init_hooks() {
        add_filter('theme_page_templates', [$this, 'register_page_templates']);
        add_filter('template_include', [$this, 'load_einsatzbericht_template']);
        add_filter('comments_open', [$this, 'force_comments_open_for_reports'], 10, 2);
        add_filter('preprocess_comment', [$this, 'restrict_report_comments_to_logged_in_users']);
        add_filter('has_post_thumbnail', [$this, 'filter_has_post_thumbnail'], 10, 3);
        add_filter('post_thumbnail_html', [$this, 'filter_post_thumbnail_html'], 10, 5);
        add_filter('post_thumbnail_url', [$this, 'filter_post_thumbnail_url'], 10, 3);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_head', [$this, 'render_social_share_meta_tags'], 5);
        $this->register_shortcode_aliases('count', [$this, 'render_einsatz_count_shortcode']);
        $this->register_shortcode_aliases('current', [$this, 'render_current_einsatz_count_shortcode']);
        $this->register_shortcode_aliases('previous', [$this, 'render_previous_einsatz_count_shortcode']);
        $this->register_shortcode_aliases('overview_page', [$this, 'render_overview_page_shortcode']);
        $this->register_shortcode_aliases('overview_list', [$this, 'render_overview_list_shortcode']);
        $this->register_shortcode_aliases('overview_sidebar', [$this, 'render_overview_sidebar_shortcode']);
        $this->register_shortcode_aliases('latest', [$this, 'render_latest_reports_shortcode']);
        $this->register_shortcode_aliases('alarm_list', [$this, 'render_alarm_list_shortcode']);
        $this->register_shortcode_aliases('area_page', [$this, 'render_area_page_shortcode']);
    }

    private function is_einsatzbericht_post($post_id) {
        $post_id = absint($post_id);

        if (!$post_id || 'post' !== get_post_type($post_id)) {
            return false;
        }

        if ('1' === get_post_meta($post_id, '_feu_einsatz_einsatzbericht', true)) {
            return true;
        }

        $report_meta_keys = [
            '_feu_einsatz_strasse',
            '_feu_einsatz_plz',
            '_feu_einsatz_stadt',
            '_feu_einsatz_datum',
            '_feu_einsatz_uhrzeit',
            '_feu_einsatz_teilnehmer',
            '_feu_einsatz_organisationen',
        ];

        foreach ($report_meta_keys as $meta_key) {
            $value = get_post_meta($post_id, $meta_key, true);

            if (is_array($value) && !empty($value)) {
                return true;
            }

            if (!is_array($value) && '' !== trim((string) $value)) {
                return true;
            }
        }

        $categories = get_the_category($post_id);

        if (empty($categories) || is_wp_error($categories)) {
            return false;
        }

        $candidate_terms = [];

        foreach ($categories as $category) {
            $candidate_terms[] = $category;

            foreach (get_ancestors($category->term_id, 'category') as $ancestor_id) {
                $ancestor = get_category($ancestor_id);

                if ($ancestor && !is_wp_error($ancestor)) {
                    $candidate_terms[] = $ancestor;
                }
            }
        }

        foreach ($candidate_terms as $term) {
            $normalized_slug = sanitize_title(remove_accents($term->slug));
            $normalized_name = sanitize_title(remove_accents($term->name));

            if (in_array($normalized_slug, ['einsaetze', 'einsatze'], true) || in_array($normalized_name, ['einsaetze', 'einsatze'], true)) {
                return true;
            }
        }

        return false;
    }

    private function is_overview_archive_request() {
        if (!is_category()) {
            return false;
        }

        $queried_object = get_queried_object();

        if (!($queried_object instanceof WP_Term) || 'category' !== (string) $queried_object->taxonomy) {
            return false;
        }

        $root_category = FEU_Einsatz_Template_Helpers::find_root_category();

        if (!($root_category instanceof WP_Term)) {
            return false;
        }

        $root_category_id = (int) $root_category->term_id;
        $current_category_id = (int) $queried_object->term_id;

        return $current_category_id === $root_category_id
            || cat_is_ancestor_of($root_category_id, $current_category_id);
    }

    private function get_generated_map_preview_url($post_id) {
        $post_id = absint($post_id);

        if (!$post_id || !$this->is_einsatzbericht_post($post_id)) {
            return '';
        }

        $preview_url = FEU_Einsatz_Template_Helpers::get_generated_map_preview_public_url($post_id);
        $preview_file = trim((string) get_post_meta($post_id, '_feu_einsatz_generated_map_preview_file', true));

        if ('' === $preview_url) {
            return '';
        }

        if ('' !== $preview_file) {
            return file_exists($preview_file) ? $preview_url : '';
        }

        return $preview_url;
    }

    private function get_generated_map_preview_dimensions($post_id) {
        $preview_file = trim((string) get_post_meta($post_id, '_feu_einsatz_generated_map_preview_file', true));

        if ('' === $preview_file || !file_exists($preview_file)) {
            return [];
        }

        $image_size = @getimagesize($preview_file);

        if (!$image_size || empty($image_size[0]) || empty($image_size[1])) {
            return [];
        }

        return [
            'width' => (int) $image_size[0],
            'height' => (int) $image_size[1],
        ];
    }

    private function build_html_attributes(array $attributes) {
        $parts = [];

        foreach ($attributes as $name => $value) {
            if (null === $value || false === $value || '' === $value) {
                continue;
            }

            if (true === $value) {
                $parts[] = sanitize_key($name);
                continue;
            }

            $parts[] = sprintf('%s="%s"', sanitize_key($name), esc_attr((string) $value));
        }

        return implode(' ', $parts);
    }

    private function get_social_share_settings() {
        return [
            'enabled_networks' => FEU_Einsatz_Template_Helpers::normalize_social_share_networks(
                get_option('feu_einsatz_social_share_enabled_networks', FEU_Einsatz_Template_Helpers::get_default_social_share_networks())
            ),
            'image_mode' => FEU_Einsatz_Template_Helpers::normalize_social_share_image_mode(
                get_option('feu_einsatz_social_share_image_mode', 'post_image')
            ),
            'background_id' => absint(get_option('feu_einsatz_social_share_background_id', 0)),
            'fields' => FEU_Einsatz_Template_Helpers::normalize_social_share_fields(
                get_option('feu_einsatz_social_share_fields', FEU_Einsatz_Template_Helpers::get_default_social_share_fields())
            ),
            'layout' => FEU_Einsatz_Template_Helpers::normalize_social_share_layout(
                get_option('feu_einsatz_social_share_layout', 'wide')
            ),
            'logo_id' => absint(get_option('feu_einsatz_social_share_logo_id', 0)),
            'badge_text' => FEU_Einsatz_Template_Helpers::normalize_social_share_card_text(
                get_option('feu_einsatz_social_share_badge_text', 'PRESSEMITTEILUNG'),
                'PRESSEMITTEILUNG'
            ),
            'cta_text' => FEU_Einsatz_Template_Helpers::normalize_social_share_card_text(
                get_option('feu_einsatz_social_share_cta_text', 'Weitere Infos'),
                'Weitere Infos'
            ),
            'title_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_title_color', '#ffffff')) ?: '#ffffff',
            'description_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_description_color', '#dbeafe')) ?: '#dbeafe',
            'panel_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_panel_color', '#0f2f5f')) ?: '#0f2f5f',
            'accent_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_accent_color', '#ef233c')) ?: '#ef233c',
            'cta_fill_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_cta_fill_color', '#ffffff')) ?: '#ffffff',
            'cta_text_color' => sanitize_hex_color((string) get_option('feu_einsatz_social_share_cta_text_color', '#0f2f5f')) ?: '#0f2f5f',
            'title_scale' => max(70, min(180, absint(get_option('feu_einsatz_social_share_title_scale', 118)))),
            'description_scale' => max(70, min(180, absint(get_option('feu_einsatz_social_share_description_scale', 112)))),
            'description_max_lines' => max(2, min(10, absint(get_option('feu_einsatz_social_share_description_max_lines', 5)))),
            'logo_scale' => max(40, min(220, absint(get_option('feu_einsatz_social_share_logo_scale', 100)))),
            'logo_width' => max(60, min(520, absint(get_option('feu_einsatz_social_share_logo_width', 220)))),
            'overlay_enabled' => 1 === (int) get_option('feu_einsatz_social_share_overlay_enabled', 1),
            'image_blur' => max(0, min(20, absint(get_option('feu_einsatz_social_share_image_blur', 0)))),
            'panel_radius' => max(0, min(120, absint(get_option('feu_einsatz_social_share_panel_radius', 30)))),
            'badge_radius' => max(0, min(120, absint(get_option('feu_einsatz_social_share_badge_radius', 40)))),
            'link_radius' => max(0, min(80, absint(get_option('feu_einsatz_social_share_link_radius', 14)))),
            'text_align' => FEU_Einsatz_Template_Helpers::normalize_social_share_text_align(
                get_option('feu_einsatz_social_share_text_align', 'auto')
            ),
            'logo_position' => FEU_Einsatz_Template_Helpers::normalize_social_share_logo_position(
                get_option('feu_einsatz_social_share_logo_position', 'bottom-right')
            ),
            'meta_enabled' => 1 === (int) get_option('feu_einsatz_social_meta_enabled', 1),
            'canonical_enabled' => 1 === (int) get_option('feu_einsatz_social_meta_canonical_enabled', 1),
            'schema_enabled' => 1 === (int) get_option('feu_einsatz_social_meta_schema_enabled', 1),
            'twitter_site' => FEU_Einsatz_Template_Helpers::normalize_social_share_account_handle(
                get_option('feu_einsatz_social_meta_twitter_site', '')
            ),
            'twitter_handle' => FEU_Einsatz_Template_Helpers::normalize_social_share_account_handle(
                get_option('feu_einsatz_social_meta_twitter_site', '')
            ),
        ];
    }

    private function get_report_share_description($post) {
        if (!($post instanceof WP_Post)) {
            return '';
        }

        $raw_text = '' !== trim((string) $post->post_excerpt)
            ? (string) $post->post_excerpt
            : (string) $post->post_content;

        $raw_text = wp_strip_all_tags(strip_shortcodes($raw_text));
        $raw_text = trim(preg_replace('/\s+/', ' ', $raw_text));

        if ('' === $raw_text) {
            return '';
        }

        return wp_trim_words($raw_text, 26, ' ...');
    }

    private function normalize_share_text_value($value) {
        $value = is_scalar($value) ? (string) $value : '';
        $value = wp_strip_all_tags($value);

        for ($index = 0; $index < 3; $index++) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($decoded === $value) {
                break;
            }

            $value = $decoded;
        }

        $value = str_replace(
            ['‘', '—', '&ndash;', '&mdash;'],
            ['‘', '—', '–', '—'],
            $value
        );

        return trim(preg_replace('/\\s+/u', ' ', $value));
    }

    private function build_share_location_label($street, $postcode, $city) {
        $postcode = trim((string) $postcode);
        $city = trim((string) $city);
        $locality = trim($postcode . ' ' . $city);

        return $locality;
    }

    private function build_report_share_text($selected_fields, $field_values) {
        $selected_fields = FEU_Einsatz_Template_Helpers::normalize_social_share_fields($selected_fields);
        $lines = [];
        $meta_parts = [];

        if (in_array('title', $selected_fields, true) && '' !== trim((string) ($field_values['title'] ?? ''))) {
            $lines[] = trim((string) $field_values['title']);
        }

        if (in_array('number', $selected_fields, true) && '' !== trim((string) ($field_values['number'] ?? ''))) {
            $meta_parts[] = sprintf(
                __('Einsatz-Nr. %s', 'feuer-einsatzberichte'),
                trim((string) $field_values['number'])
            );
        }

        if (in_array('date', $selected_fields, true) && '' !== trim((string) ($field_values['date'] ?? ''))) {
            $meta_parts[] = sprintf(
                __('Datum: %s', 'feuer-einsatzberichte'),
                trim((string) $field_values['date'])
            );
        }

        if (in_array('time', $selected_fields, true) && '' !== trim((string) ($field_values['time'] ?? ''))) {
            $meta_parts[] = sprintf(
                __('Uhrzeit: %s', 'feuer-einsatzberichte'),
                trim((string) $field_values['time'])
            );
        }

        if (!empty($meta_parts)) {
            $lines[] = implode(' | ', $meta_parts);
        }

        foreach ([
            'category' => __('Einsatzart: %s', 'feuer-einsatzberichte'),
            'street' => __('Strasse: %s', 'feuer-einsatzberichte'),
            'location' => __('Ort: %s', 'feuer-einsatzberichte'),
        ] as $field_key => $line_format) {
            if (!in_array($field_key, $selected_fields, true)) {
                continue;
            }

            $field_value = trim((string) ($field_values[$field_key] ?? ''));

            if ('' === $field_value) {
                continue;
            }

            $lines[] = sprintf($line_format, $field_value);
        }

        if (in_array('description', $selected_fields, true) && '' !== trim((string) ($field_values['description'] ?? ''))) {
            $lines[] = '';
            $lines[] = trim((string) $field_values['description']);
        }

        $lines[] = '';
        $lines[] = sprintf(
            __('Link: %s', 'feuer-einsatzberichte'),
            trim((string) ($field_values['url'] ?? ''))
        );

        return trim(implode("\n", array_values(array_filter($lines, static function ($line, $index) {
            return !('' === $line && 0 === $index);
        }, ARRAY_FILTER_USE_BOTH))));
    }

    private function normalize_share_text_for_transport($text) {
        $text = trim((string) $text);

        if ('' === $text) {
            return '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return str_replace("\n", "\r\n", $text);
    }

    private function build_share_network_url($network_key, $url, $share_text, $title, $twitter_handle = '') {
        $url = trim((string) $url);
        $share_text = $this->normalize_share_text_for_transport($share_text);
        $title = trim((string) $title);
        $twitter_handle = trim((string) $twitter_handle);

        switch ($network_key) {
            case 'share':
                return '';

            case 'facebook':
                return 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url);

            case 'x':
                // 3.1.44: x.com statt twitter.com.
                $x_url = 'https://x.com/intent/post?text=' . rawurlencode($share_text) . '&url=' . rawurlencode($url);

                if ('' !== $twitter_handle) {
                    $x_url .= '&via=' . rawurlencode(ltrim($twitter_handle, '@'));
                }

                return $x_url;

            case 'whatsapp':
                return 'https://wa.me/?text=' . rawurlencode($share_text);

            case 'telegram':
                return 'https://t.me/share/url?url=' . rawurlencode($url) . '&text=' . rawurlencode($share_text);

            case 'email':
                return 'mailto:?subject=' . rawurlencode($title) . '&body=' . rawurlencode($share_text);
        }

        return '';
    }

    private function build_share_image_endpoint_url($post_id, $download = false) {
        $post_id = absint($post_id);

        if ($post_id < 1) {
            return '';
        }

        $url = add_query_arg(
            [
                'action' => 'feu_einsatz_share_image',
                'post_id' => $post_id,
            ],
            admin_url('admin-post.php')
        );

        if ($download) {
            $url = add_query_arg('download', '1', $url);
        }

        return wp_nonce_url($url, 'feu_einsatz_share_image_' . $post_id);
    }

    private function build_public_share_image_endpoint_url($post_id, $download = false) {
        $post_id = absint($post_id);

        if ($post_id < 1) {
            return '';
        }

        $args = [
            'action' => 'feu_einsatz_share_image_public',
            'post_id' => $post_id,
        ];
        $expires_at = time() + DAY_IN_SECONDS;
        $args['exp'] = $expires_at;
        $args['sig'] = FEU_Einsatz_Template_Helpers::get_public_share_image_signature($post_id, $expires_at);

        $revision = $this->get_public_share_image_revision($post_id);

        if ('' !== $revision) {
            $args['rev'] = $revision;
        }

        $url = add_query_arg($args, admin_url('admin-post.php'));

        if ($download) {
            $url = add_query_arg('download', '1', $url);
        }

        return $url;
    }

    private function get_public_share_image_revision($post_id) {
        $post_id = absint($post_id);

        if ($post_id < 1) {
            return '';
        }

        $settings = $this->get_social_share_settings();
        $thumbnail_id = (int) get_post_thumbnail_id($post_id);
        $preview_file = trim((string) get_post_meta($post_id, '_feu_einsatz_generated_map_preview_file', true));
        $preview_mtime = ('' !== $preview_file && file_exists($preview_file)) ? (int) @filemtime($preview_file) : 0;
        $background_id = (int) ($settings['background_id'] ?? 0);
        $background_file = $background_id > 0 ? (string) get_attached_file($background_id) : '';
        $background_mtime = ('' !== $background_file && file_exists($background_file)) ? (int) @filemtime($background_file) : 0;
        $fingerprint = [
            'post_modified' => (string) get_post_modified_time('U', true, $post_id),
            'thumbnail_id' => $thumbnail_id,
            'thumbnail_modified' => $thumbnail_id > 0 ? (string) get_post_modified_time('U', true, $thumbnail_id) : '',
            'image_mode' => (string) ($settings['image_mode'] ?? ''),
            'background_id' => $background_id,
            'background_mtime' => $background_mtime,
            'layout' => (string) ($settings['layout'] ?? 'wide'),
            'logo_id' => (int) ($settings['logo_id'] ?? 0),
            'badge_text' => (string) ($settings['badge_text'] ?? ''),
            'cta_text' => (string) ($settings['cta_text'] ?? ''),
            'title_color' => (string) ($settings['title_color'] ?? ''),
            'description_color' => (string) ($settings['description_color'] ?? ''),
            'panel_color' => (string) ($settings['panel_color'] ?? ''),
            'accent_color' => (string) ($settings['accent_color'] ?? ''),
            'cta_fill_color' => (string) ($settings['cta_fill_color'] ?? ''),
            'cta_text_color' => (string) ($settings['cta_text_color'] ?? ''),
            'title_scale' => (int) ($settings['title_scale'] ?? 118),
            'description_scale' => (int) ($settings['description_scale'] ?? 112),
            'description_max_lines' => (int) ($settings['description_max_lines'] ?? 5),
            'logo_scale' => (int) ($settings['logo_scale'] ?? 100),
            'logo_width' => (int) ($settings['logo_width'] ?? 220),
            'overlay_enabled' => !empty($settings['overlay_enabled']),
            'image_blur' => (int) ($settings['image_blur'] ?? 0),
            'panel_radius' => (int) ($settings['panel_radius'] ?? 30),
            'badge_radius' => (int) ($settings['badge_radius'] ?? 40),
            'link_radius' => (int) ($settings['link_radius'] ?? 14),
            'text_align' => (string) ($settings['text_align'] ?? 'auto'),
            'logo_position' => (string) ($settings['logo_position'] ?? 'bottom-right'),
            'fields' => array_values((array) ($settings['fields'] ?? [])),
            'preview_file' => '' !== $preview_file ? wp_basename($preview_file) : '',
            'preview_mtime' => $preview_mtime,
        ];

        return substr(md5((string) wp_json_encode($fingerprint)), 0, 12);
    }

    public function get_single_share_box_data($post, array $context = []) {
        if (
            !($post instanceof WP_Post)
            || !current_user_can((string) apply_filters('feu_einsatz_share_capability', 'manage_options'))
        ) {
            return [];
        }

        $post_id = (int) $post->ID;

        if (!$this->is_einsatzbericht_post($post_id)) {
            return [];
        }

        $settings = $this->get_social_share_settings();
        $report = isset($context['report']) && is_array($context['report']) ? $context['report'] : [];
        $number_data = FEU_Einsatz_Template_Helpers::get_report_number_data($post_id);
        $card_image = FEU_Einsatz_Template_Helpers::get_report_photo_image_data($post_id, [], 'large');
        $permalink = get_permalink($post_id);
        $post_status = (string) get_post_status($post_id);
        $description = $this->get_report_share_description($post);
        $street = isset($report['street'])
            ? (string) $report['street']
            : FEU_Einsatz_Template_Helpers::strip_house_number_from_street(get_post_meta($post_id, '_feu_einsatz_strasse', true));
        $postcode = trim((string) get_post_meta($post_id, '_feu_einsatz_plz', true));
        $city = trim((string) get_post_meta($post_id, '_feu_einsatz_stadt', true));
        $number_label = !empty($number_data['number']) && !empty($number_data['year'])
            ? absint($number_data['number']) . '/' . absint($number_data['year'])
            : '';
        $field_values = [
            'title' => $this->normalize_share_text_value(isset($report['title']) ? (string) $report['title'] : get_the_title($post_id)),
            'date' => isset($report['date_display']) ? (string) $report['date_display'] : '',
            'time' => isset($report['time']) ? (string) $report['time'] : '',
            'number' => $number_label,
            'category' => $this->normalize_share_text_value(isset($report['category']['name']) ? (string) $report['category']['name'] : ''),
            'street' => $street,
            'location' => $this->build_share_location_label($street, $postcode, $city),
            'description' => $description,
            'url' => (string) $permalink,
        ];
        $share_text = $this->build_report_share_text($settings['fields'], $field_values);
        $network_definitions = FEU_Einsatz_Template_Helpers::get_social_share_network_definitions();
        $networks = [];

        foreach ((array) $settings['enabled_networks'] as $network_key) {
            if (!isset($network_definitions[$network_key])) {
                continue;
            }

            $share_url = $this->build_share_network_url(
                $network_key,
                $field_values['url'],
                $share_text,
                $field_values['title'],
                (string) ($settings['twitter_handle'] ?? '')
            );

            if (!in_array($network_key, ['share', 'instagram'], true) && '' === $share_url) {
                continue;
            }

            $networks[] = [
                'key' => $network_key,
                'label' => (string) $network_definitions[$network_key]['label'],
                'description' => (string) $network_definitions[$network_key]['description'],
                'url' => $share_url,
                'mode' => in_array($network_key, ['share', 'instagram'], true) ? 'native' : 'direct',
            ];
        }

        $background_image_url = $settings['background_id'] ? wp_get_attachment_image_url($settings['background_id'], 'large') : '';
        $post_image_url = isset($card_image['url']) ? (string) $card_image['url'] : '';
        $post_image_attachment_id = isset($card_image['attachment_id']) ? absint($card_image['attachment_id']) : 0;
        $generated_preview_url = $this->get_generated_map_preview_url($post_id);

        $is_public_share_image = 'publish' === $post_status;

        if ('generated' === $settings['image_mode']) {
            $preview_image_url = $is_public_share_image
                ? $this->build_public_share_image_endpoint_url($post_id, false)
                : $this->build_share_image_endpoint_url($post_id, false);
            $download_image_url = $is_public_share_image
                ? $this->build_public_share_image_endpoint_url($post_id, true)
                : $this->build_share_image_endpoint_url($post_id, true);
            $image_notice = $background_image_url
                ? __('Die Share-Karte verwendet das eingestellte Hintergrundbild und blendet die freigegebenen Einsatzdaten darueber ein.', 'feuer-einsatzberichte')
                : __('Die Share-Karte verwendet mangels eigenem Hintergrund zuerst das Beitragsbild des Einsatzberichts und sonst einen neutralen Farbverlauf.', 'feuer-einsatzberichte');
        } else {
            $generated_share_preview_url = $is_public_share_image
                ? $this->build_public_share_image_endpoint_url($post_id, false)
                : $this->build_share_image_endpoint_url($post_id, false);
            $generated_share_download_url = $is_public_share_image
                ? $this->build_public_share_image_endpoint_url($post_id, true)
                : $this->build_share_image_endpoint_url($post_id, true);

            $preview_image_url = '' !== $post_image_url ? $post_image_url : $generated_share_preview_url;
            $download_image_url = '' !== $post_image_url ? $post_image_url : $generated_share_download_url;
            $image_notice = '' !== $post_image_url
                ? __('Es wird das vorhandene Beitragsbild beziehungsweise das aktuell fuer den Beitrag genutzte Einsatzbild geteilt.', 'feuer-einsatzberichte')
                : __('Kein verwendbares Beitragsbild gefunden. Die Vorschau nutzt deshalb automatisch die generierte Share-Karte.', 'feuer-einsatzberichte');
        }

        return [
            'post_id' => $post_id,
            'post_status' => $post_status,
            'is_public' => 'publish' === $post_status,
            'title' => $field_values['title'],
            'description' => $description,
            'date_display' => $field_values['date'],
            'time' => $field_values['time'],
            'category_name' => $field_values['category'],
            'street_label' => $field_values['street'],
            'location_label' => $field_values['location'],
            'permalink' => (string) $permalink,
            'share_text' => $share_text,
            'networks' => $networks,
            'image_mode' => $settings['image_mode'],
            'image_mode_label' => 'generated' === $settings['image_mode']
                ? __('Generierte Share-Karte', 'feuer-einsatzberichte')
                : __('Vorhandenes Beitragsbild', 'feuer-einsatzberichte'),
            'background_image_url' => $background_image_url,
            'background_image_id' => $settings['background_id'],
            'post_image_url' => $post_image_url,
            'post_image_attachment_id' => $post_image_attachment_id,
            'preview_image_url' => $preview_image_url,
            'download_image_url' => $download_image_url,
            'has_preview_image' => '' !== trim((string) $preview_image_url),
            'image_notice' => $image_notice,
            'number_label' => $number_label,
            'selected_fields' => $settings['fields'],
            'status_notice' => 'publish' === $post_status
                ? ''
                : __('Share-Links fuer soziale Netzwerke werden erst nach der Veroeffentlichung freigegeben, damit keine nicht oeffentlichen Beitragslinks verteilt werden.', 'feuer-einsatzberichte'),
        ];
    }

    private function limit_social_meta_text($text, $max_length = 200) {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text));
        $max_length = max(80, (int) $max_length);

        if ('' === $text) {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') <= $max_length) {
                return $text;
            }

            return rtrim(mb_substr($text, 0, $max_length - 1, 'UTF-8')) . '…';
        }

        if (strlen($text) <= $max_length) {
            return $text;
        }

        return rtrim(substr($text, 0, $max_length - 3)) . '...';
    }

    private function build_social_meta_description(array $selected_fields, array $field_values, $fallback = '') {
        $selected_fields = FEU_Einsatz_Template_Helpers::normalize_social_share_fields($selected_fields);
        $summary_parts = [];
        $date_time_parts = [];

        if (in_array('category', $selected_fields, true) && '' !== trim((string) ($field_values['category'] ?? ''))) {
            $summary_parts[] = trim((string) $field_values['category']);
        }

        if (in_array('number', $selected_fields, true) && '' !== trim((string) ($field_values['number'] ?? ''))) {
            $summary_parts[] = sprintf(
                __('Einsatz-Nr. %s', 'feuer-einsatzberichte'),
                trim((string) $field_values['number'])
            );
        }

        if (in_array('date', $selected_fields, true) && '' !== trim((string) ($field_values['date'] ?? ''))) {
            $date_time_parts[] = trim((string) $field_values['date']);
        }

        if (in_array('time', $selected_fields, true) && '' !== trim((string) ($field_values['time'] ?? ''))) {
            $date_time_parts[] = trim((string) $field_values['time']);
        }

        if (!empty($date_time_parts)) {
            $summary_parts[] = implode(' ', $date_time_parts);
        }

        if (in_array('location', $selected_fields, true) && '' !== trim((string) ($field_values['location'] ?? ''))) {
            $summary_parts[] = trim((string) $field_values['location']);
        } elseif (in_array('street', $selected_fields, true) && '' !== trim((string) ($field_values['street'] ?? ''))) {
            $summary_parts[] = trim((string) $field_values['street']);
        }

        $description_text = in_array('description', $selected_fields, true)
            ? trim((string) ($field_values['description'] ?? ''))
            : '';

        $summary = implode(' | ', array_values(array_filter($summary_parts)));

        if ('' !== $description_text) {
            $summary = '' !== $summary ? $summary . ' — ' . $description_text : $description_text;
        }

        if ('' === $summary) {
            $summary = trim((string) $fallback);
        }

        return $this->limit_social_meta_text($summary, 200);
    }

    private function get_attachment_social_meta_image_data($attachment_id, $default_alt = '') {
        $attachment_id = absint($attachment_id);

        if ($attachment_id < 1) {
            return [];
        }

        $image_url = FEU_Einsatz_Template_Helpers::get_versioned_attachment_image_url($attachment_id, 'full');

        if (!$image_url) {
            return [];
        }

        $metadata = wp_get_attachment_metadata($attachment_id);
        $alt_text = trim((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));

        if ('' === $alt_text) {
            $alt_text = trim((string) $default_alt);
        }

        return [
            'url' => $image_url,
            'alt' => $alt_text,
            'width' => isset($metadata['width']) ? absint($metadata['width']) : 0,
            'height' => isset($metadata['height']) ? absint($metadata['height']) : 0,
            'type' => (string) get_post_mime_type($attachment_id),
        ];
    }

    private function get_public_social_meta_image_data($post_id, array $settings, array $context, $default_alt = '') {
        $post_id = absint($post_id);
        $default_alt = trim((string) $default_alt);

        if ('generated' === ($settings['image_mode'] ?? 'post_image')) {
            $generated_image_url = $this->build_public_share_image_endpoint_url($post_id);
            $dimensions = FEU_Einsatz_Template_Helpers::get_social_share_card_dimensions($settings['layout'] ?? 'wide');

            if ('' !== $generated_image_url) {
                return [
                    'url' => $generated_image_url,
                    'alt' => $default_alt,
                    'width' => isset($dimensions['width']) ? (int) $dimensions['width'] : 1200,
                    'height' => isset($dimensions['height']) ? (int) $dimensions['height'] : 630,
                    'type' => 'image/png',
                ];
            }
        }

        $card_image = FEU_Einsatz_Template_Helpers::get_report_photo_image_data($post_id, [], 'full');
        $card_attachment_id = isset($card_image['attachment_id']) ? absint($card_image['attachment_id']) : 0;

        if ($card_attachment_id > 0) {
            $attachment_image = $this->get_attachment_social_meta_image_data($card_attachment_id, $default_alt);

            if (!empty($attachment_image['url'])) {
                return $attachment_image;
            }
        }

        if (!empty($card_image['url'])) {
            return [
                'url' => (string) $card_image['url'],
                'alt' => $default_alt,
                'width' => 0,
                'height' => 0,
                'type' => '',
            ];
        }

        $fallback_image = isset($context['map']) && is_array($context['map']) ? $context['map'] : [];
        $fallback_attachment_id = isset($fallback_image['post_image_attachment_id']) ? absint($fallback_image['post_image_attachment_id']) : 0;
        $fallback_alt = !empty($fallback_image['post_image_alt']) ? (string) $fallback_image['post_image_alt'] : $default_alt;

        if ($fallback_attachment_id > 0) {
            $attachment_image = $this->get_attachment_social_meta_image_data($fallback_attachment_id, $fallback_alt);

            if (!empty($attachment_image['url'])) {
                return $attachment_image;
            }
        }

        if (!empty($fallback_image['post_image_url'])) {
            return [
                'url' => (string) $fallback_image['post_image_url'],
                'alt' => $fallback_alt,
                'width' => 0,
                'height' => 0,
                'type' => '',
            ];
        }

        $generated_preview_url = $this->get_generated_map_preview_url($post_id);

        if ('generated' === ($settings['image_mode'] ?? 'post_image') && '' !== $generated_preview_url) {
            $dimensions = $this->get_generated_map_preview_dimensions($post_id);
            $preview_file = trim((string) get_post_meta($post_id, '_feu_einsatz_generated_map_preview_file', true));
            $preview_type = 'image/png';

            if ('' !== $preview_file) {
                $preview_extension = strtolower((string) pathinfo($preview_file, PATHINFO_EXTENSION));

                if ('svg' === $preview_extension) {
                    $preview_type = 'image/svg+xml';
                } elseif ('webp' === $preview_extension) {
                    $preview_type = 'image/webp';
                }
            }

            return [
                'url' => $generated_preview_url,
                'alt' => $default_alt,
                'width' => isset($dimensions['width']) ? absint($dimensions['width']) : 0,
                'height' => isset($dimensions['height']) ? absint($dimensions['height']) : 0,
                'type' => $preview_type,
            ];
        }

        return [];
    }

    private function build_breadcrumb_schema_items(array $breadcrumbs, $fallback_url = '') {
        $items = [];
        $position = 1;
        $fallback_url = trim((string) $fallback_url);

        foreach ($breadcrumbs as $breadcrumb) {
            if (empty($breadcrumb['label'])) {
                continue;
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => (string) $breadcrumb['label'],
                'item' => !empty($breadcrumb['url']) ? (string) $breadcrumb['url'] : $fallback_url,
            ];

            $position++;
        }

        return $items;
    }
    public function render_social_share_meta_tags() {
        if (!is_singular('post')) {
            return;
        }

        $post_id = get_queried_object_id();

        if (!$post_id || !$this->is_einsatzbericht_post($post_id)) {
            return;
        }

        $settings = $this->get_social_share_settings();

        if (empty($settings['meta_enabled'])) {
            return;
        }

        $post = get_post($post_id);

        if (!($post instanceof WP_Post)) {
            return;
        }

        try {
            $context = FEU_Einsatz_Template_Helpers::get_single_context($post);
        } catch (Throwable $e) {
            error_log('[Feuer-Einsatzberichte] Fehler in render_social_share_meta_tags/get_single_context: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return;
        }
        $report = isset($context['report']) && is_array($context['report']) ? $context['report'] : [];
        $permalink = get_permalink($post_id);
        $number_data = FEU_Einsatz_Template_Helpers::get_report_number_data($post_id);
        $title = $this->normalize_share_text_value(isset($report['title']) ? (string) $report['title'] : get_the_title($post_id));
        $raw_description = $this->get_report_share_description($post);
        $field_values = [
            'title' => $title,
            'date' => isset($report['date_display']) ? (string) $report['date_display'] : '',
            'time' => isset($report['time']) ? (string) $report['time'] : '',
            'number' => (!empty($number_data['number']) && !empty($number_data['year']))
                ? absint($number_data['number']) . '/' . absint($number_data['year'])
                : '',
            'category' => isset($report['category']['name']) ? $this->normalize_share_text_value((string) $report['category']['name']) : '',
            'street' => isset($report['street']) ? (string) $report['street'] : '',
            'location' => isset($report['location']) ? (string) $report['location'] : '',
            'description' => $raw_description,
            'url' => (string) $permalink,
        ];
        try {
            $description = $this->build_social_meta_description($settings['fields'], $field_values, $raw_description ?: $title);
            $image = $this->get_public_social_meta_image_data($post_id, $settings, $context, $title);
        } catch (Throwable $e) {
            error_log('[Feuer-Einsatzberichte] Fehler in render_social_share_meta_tags/image-data: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return;
        }
        $image_url = !empty($image['url']) ? trim((string) $image['url']) : '';

        if ('' === $title || '' === $permalink) {
            return;
        }

        if ('' === $description) {
            $description = $title;
        }

        $locale = str_replace('-', '_', determine_locale());
        $published_time = get_post_time(DATE_W3C, true, $post_id);
        $modified_time = get_post_modified_time(DATE_W3C, true, $post_id);
        $site_name = get_bloginfo('name');
        $site_icon_url = function_exists('get_site_icon_url') ? (string) get_site_icon_url(512) : '';

        echo "\n";

        if (!empty($settings['canonical_enabled'])) {
            echo '<link rel="canonical" href="' . esc_url($permalink) . '" />' . "\n";
            echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
        }

        echo '<meta property="og:locale" content="' . esc_attr($locale) . '" />' . "\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($permalink) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '" />' . "\n";
        echo '<meta property="article:published_time" content="' . esc_attr($published_time) . '" />' . "\n";
        echo '<meta property="article:modified_time" content="' . esc_attr($modified_time) . '" />' . "\n";
        echo '<meta property="og:updated_time" content="' . esc_attr($modified_time) . '" />' . "\n";
        echo '<meta name="robots" content="max-image-preview:large" />' . "\n";

        if (!empty($field_values['category'])) {
            echo '<meta property="article:section" content="' . esc_attr($field_values['category']) . '" />' . "\n";
        }

        echo '<meta name="twitter:card" content="' . esc_attr('' !== $image_url ? 'summary_large_image' : 'summary') . '" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '" />' . "\n";
        echo '<meta name="twitter:url" content="' . esc_url($permalink) . '" />' . "\n";

        if (!empty($settings['twitter_site'])) {
            echo '<meta name="twitter:site" content="' . esc_attr($settings['twitter_site']) . '" />' . "\n";
        }

        if ('' !== $image_url) {
            $image_alt = !empty($image['alt']) ? (string) $image['alt'] : $title;
            echo '<meta property="og:image" content="' . esc_url($image_url) . '" />' . "\n";
            echo '<meta property="og:image:url" content="' . esc_url($image_url) . '" />' . "\n";
            echo '<meta property="og:image:secure_url" content="' . esc_url($image_url) . '" />' . "\n";
            echo '<meta property="og:image:alt" content="' . esc_attr($image_alt) . '" />' . "\n";
            echo '<meta name="twitter:image" content="' . esc_url($image_url) . '" />' . "\n";
            echo '<meta name="twitter:image:alt" content="' . esc_attr($image_alt) . '" />' . "\n";

            if (!empty($image['width'])) {
                echo '<meta property="og:image:width" content="' . esc_attr((string) absint($image['width'])) . '" />' . "\n";
            }

            if (!empty($image['height'])) {
                echo '<meta property="og:image:height" content="' . esc_attr((string) absint($image['height'])) . '" />' . "\n";
            }

            if (!empty($image['type'])) {
                echo '<meta property="og:image:type" content="' . esc_attr((string) $image['type']) . '" />' . "\n";
            }
        }

        if (!empty($settings['schema_enabled'])) {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id' => $permalink,
                ],
                'headline' => $title,
                'description' => $description,
                'url' => $permalink,
                'inLanguage' => str_replace('_', '-', $locale),
                'datePublished' => $published_time,
                'dateModified' => $modified_time,
                'author' => [
                    '@type' => 'Organization',
                    'name' => $site_name,
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $site_name,
                ],
            ];

            if ('' !== $image_url) {
                $schema['image'] = [$image_url];
            }

            if ('' !== $site_icon_url) {
                $schema['publisher']['logo'] = [
                    '@type' => 'ImageObject',
                    'url' => $site_icon_url,
                ];
            }

            if (!empty($field_values['category'])) {
                $schema['articleSection'] = $field_values['category'];
            }

            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";

            $breadcrumb_items = $this->build_breadcrumb_schema_items(
                isset($context['breadcrumbs']) && is_array($context['breadcrumbs']) ? $context['breadcrumbs'] : [],
                $permalink
            );

            if (!empty($breadcrumb_items)) {
                $breadcrumb_schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => $breadcrumb_items,
                ];

                echo '<script type="application/ld+json">' . wp_json_encode($breadcrumb_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
            }
        }
    }

    private function build_generated_map_thumbnail_html($post_id, $size = 'post-thumbnail', $attr = []) {
        $post_id = absint($post_id);
        $preview_url = $this->get_generated_map_preview_url($post_id);

        if ('' === $preview_url) {
            return '';
        }

        if (!is_array($attr)) {
            $attr = [];
        }

        $size_name = is_string($size) && '' !== $size ? sanitize_html_class($size) : 'post-thumbnail';
        $default_class = 'attachment-' . $size_name . ' size-' . $size_name . ' wp-post-image feu-einsatz-generated-map-preview';
        $custom_class = isset($attr['class']) ? trim((string) $attr['class']) : '';

        $attributes = $attr;
        $attributes['src'] = $preview_url;
        $attributes['class'] = trim($default_class . ' ' . $custom_class);
        $attributes['alt'] = isset($attr['alt']) && '' !== trim((string) $attr['alt'])
            ? (string) $attr['alt']
            : wp_strip_all_tags(get_the_title($post_id));
        $attributes['loading'] = isset($attr['loading']) ? $attr['loading'] : 'lazy';
        $attributes['decoding'] = isset($attr['decoding']) ? $attr['decoding'] : 'async';

        $dimensions = $this->get_generated_map_preview_dimensions($post_id);

        if (!isset($attributes['width']) && !empty($dimensions['width'])) {
            $attributes['width'] = (int) $dimensions['width'];
        }

        if (!isset($attributes['height']) && !empty($dimensions['height'])) {
            $attributes['height'] = (int) $dimensions['height'];
        }

        return '<img ' . $this->build_html_attributes($attributes) . ' />';
    }

    public function filter_has_post_thumbnail($has_thumbnail, $post, $thumbnail_id) {
        if ($has_thumbnail || !empty($thumbnail_id)) {
            return $has_thumbnail;
        }

        $post = get_post($post);

        if (!$post instanceof WP_Post) {
            return $has_thumbnail;
        }

        return '' !== $this->get_generated_map_preview_url($post->ID);
    }

    public function filter_post_thumbnail_html($html, $post_id, $post_thumbnail_id, $size, $attr) {
        if (!empty($html) || !empty($post_thumbnail_id)) {
            return $html;
        }

        $fallback_html = $this->build_generated_map_thumbnail_html($post_id, $size, $attr);

        return '' !== $fallback_html ? $fallback_html : $html;
    }

    public function filter_post_thumbnail_url($thumbnail_url, $post, $size) {
        if (!empty($thumbnail_url)) {
            return $thumbnail_url;
        }

        $post = get_post($post);

        if (!$post instanceof WP_Post) {
            return $thumbnail_url;
        }

        $fallback_url = $this->get_generated_map_preview_url($post->ID);

        return '' !== $fallback_url ? $fallback_url : $thumbnail_url;
    }

    private function get_page_template_map() {
        $template_path = FEU_EINSATZ_PLUGIN_DIR . 'templates/feuer-einsatzberichte-uebersicht-bootstrap.php';
        $template_label = __('Einsatz Uebersicht (Bootstrap)', 'feuer-einsatzberichte');

        return [
            'feuer-einsatzberichte-uebersicht-bootstrap.php' => [
                'label' => $template_label,
                'path' => $template_path,
            ],
        ];
    }

    private function get_primary_page_template_key() {
        return 'feuer-einsatzberichte-uebersicht-bootstrap.php';
    }

    public function register_page_templates($templates) {
        $template_map = $this->get_page_template_map();
        $primary_template_key = $this->get_primary_page_template_key();

        if (isset($template_map[$primary_template_key])) {
            $templates[$primary_template_key] = $template_map[$primary_template_key]['label'];
        }

        return $templates;
    }

    private function is_area_page_enabled() {
        return 1 === (int) get_option('feu_einsatz_area_page_enabled', 0);
    }

    private function should_show_area_calls() {
        return 1 === (int) get_option('feu_einsatz_area_show_calls', 1);
    }

    private function is_area_page_request($page_id = 0) {
        if (!$page_id) {
            $page_id = get_queried_object_id();
        }

        if (!$page_id || !is_page($page_id)) {
            return false;
        }

        $page_content = (string) get_post_field('post_content', $page_id);

        return $this->has_shortcode_alias($page_content, 'area_page');
    }

    private function calculate_area_distance_meters(array $left, array $right) {
        if (
            !isset($left['latitude'], $left['longitude'], $right['latitude'], $right['longitude'])
            || !is_numeric($left['latitude'])
            || !is_numeric($left['longitude'])
            || !is_numeric($right['latitude'])
            || !is_numeric($right['longitude'])
        ) {
            return INF;
        }

        $earth_radius = 6371000;
        $delta_lat = deg2rad((float) $right['latitude'] - (float) $left['latitude']);
        $delta_lng = deg2rad((float) $right['longitude'] - (float) $left['longitude']);
        $lat_1 = deg2rad((float) $left['latitude']);
        $lat_2 = deg2rad((float) $right['latitude']);
        $distance = sin($delta_lat / 2) * sin($delta_lat / 2)
            + cos($lat_1) * cos($lat_2) * sin($delta_lng / 2) * sin($delta_lng / 2);

        return $earth_radius * 2 * atan2(sqrt($distance), sqrt(max(0, 1 - $distance)));
    }

    private function build_area_call_clusters($points, $radius = 350) {
        $clusters = [];

        foreach ((array) $points as $point) {
            if (
                !is_array($point)
                || !isset($point['latitude'], $point['longitude'])
                || !is_numeric($point['latitude'])
                || !is_numeric($point['longitude'])
            ) {
                continue;
            }

            $matched_cluster_index = null;

            foreach ($clusters as $cluster_index => $cluster) {
                if ($this->calculate_area_distance_meters($cluster['center'], $point) <= $radius) {
                    $matched_cluster_index = $cluster_index;
                    break;
                }
            }

            if (null === $matched_cluster_index) {
                $clusters[] = [
                    'center' => [
                        'latitude' => (float) $point['latitude'],
                        'longitude' => (float) $point['longitude'],
                    ],
                    'points' => [$point],
                    'count' => 1,
                ];
                continue;
            }

            $clusters[$matched_cluster_index]['points'][] = $point;
            $clusters[$matched_cluster_index]['count'] += 1;

            $lat_sum = 0.0;
            $lng_sum = 0.0;

            foreach ($clusters[$matched_cluster_index]['points'] as $cluster_point) {
                $lat_sum += (float) $cluster_point['latitude'];
                $lng_sum += (float) $cluster_point['longitude'];
            }

            $point_count = count($clusters[$matched_cluster_index]['points']);
            $clusters[$matched_cluster_index]['center']['latitude'] = $lat_sum / $point_count;
            $clusters[$matched_cluster_index]['center']['longitude'] = $lng_sum / $point_count;
        }

        return array_values(array_map(static function($cluster) {
            $street_counts = [];

            foreach ($cluster['points'] as $cluster_point) {
                $street = trim(sanitize_text_field((string) ($cluster_point['street'] ?? '')));

                if ('' === $street) {
                    continue;
                }

                if (!isset($street_counts[$street])) {
                    $street_counts[$street] = 0;
                }

                $street_counts[$street]++;
            }

            arsort($street_counts);

            return [
                'latitude' => (float) $cluster['center']['latitude'],
                'longitude' => (float) $cluster['center']['longitude'],
                'count' => (int) $cluster['count'],
                'streets' => array_values(array_slice(array_keys($street_counts), 0, 12)),
                'street_count' => count($street_counts),
            ];
        }, $clusters));
    }

    private function get_area_page_context() {
        if (is_array($this->area_page_context_cache)) {
            return $this->area_page_context_cache;
        }

        if (!$this->is_area_page_enabled()) {
            $this->area_page_context_cache = [
                'enabled' => false,
                'show_calls' => false,
                'area_entries' => [],
                'postcodes' => [],
                'areas' => [],
                'clusters' => [],
                'has_map_data' => false,
            ];

            return $this->area_page_context_cache;
        }

        $area_entries = FEU_Einsatz_Template_Helpers::sanitize_area_entry_list(get_option('feu_einsatz_area_postcodes', []));
        $postcodes = FEU_Einsatz_Template_Helpers::sanitize_postcode_list($area_entries);
        $area_station_street = trim((string) get_option('feu_einsatz_area_station_street', ''));
        $area_station_postcode = trim((string) get_option('feu_einsatz_area_station_postcode', ''));
        $area_city = trim((string) get_option('feu_einsatz_area_station_city', 'Hamburg'));
        $areas = !empty($area_entries) ? FEU_Einsatz_Template_Helpers::get_area_features($area_entries, $area_city) : [];
        $station = FEU_Einsatz_Template_Helpers::get_area_station_feature(
            $area_station_street,
            $area_station_postcode,
            $area_city,
            absint(get_option('feu_einsatz_area_station_logo_id', 0)),
            absint(get_option('feu_einsatz_area_station_logo_size', 40))
        );
        $station_address = trim(implode(', ', array_filter([
            $area_station_street,
            trim(implode(' ', array_filter([$area_station_postcode, $area_city], static function($value) {
                return '' !== trim((string) $value);
            }))),
        ], static function($value) {
            return '' !== trim((string) $value);
        })));
        $call_points = $this->should_show_area_calls()
            && !empty($postcodes)
            ? $this->db->get_activity_map_points_for_postcodes($postcodes)
            : [];
        $clusters = $this->should_show_area_calls()
            ? $this->build_area_call_clusters($call_points)
            : [];

        $this->area_page_context_cache = [
            'enabled' => $this->is_area_page_enabled(),
            'show_calls' => $this->should_show_area_calls(),
            'area_entries' => $area_entries,
            'postcodes' => $postcodes,
            'areas' => $areas,
            'station' => $station,
            'station_display' => [
                'label' => __('Feuerwehrhaus', 'feuer-einsatzberichte'),
                'address' => $station_address,
                'has_marker' => !empty($station),
            ],
            'clusters' => $clusters,
            'has_map_data' => !empty($areas) || !empty($clusters) || !empty($station),
        ];

        return $this->area_page_context_cache;
    }

    public function load_einsatzbericht_template($template) {
        if (is_singular('post')) {
            $post_id = get_queried_object_id();

            if ($this->is_einsatzbericht_post($post_id)) {
                $plugin_template = FEU_EINSATZ_PLUGIN_DIR . 'templates/public/single-render.php';

                if (file_exists($plugin_template)) {
                    return $plugin_template;
                }
            }
        }

        if (is_page()) {
            $page_id = get_queried_object_id();
            $page_template = get_page_template_slug($page_id);
            $overview_template_map = $this->get_page_template_map();

            if (
                $page_template
                && isset($overview_template_map[$page_template])
                && file_exists($overview_template_map[$page_template]['path'])
            ) {
                return $overview_template_map[$page_template]['path'];
            }
        }

        return $template;
    }

    public function force_comments_open_for_reports($open, $post_id) {
        if ($this->is_einsatzbericht_post($post_id)) {
            return FEU_Einsatz_Template_Helpers::is_comments_enabled($post_id);
        }

        return $open;
    }

    public function restrict_report_comments_to_logged_in_users($commentdata) {
        $post_id = isset($commentdata['comment_post_ID']) ? absint($commentdata['comment_post_ID']) : 0;

        if (!$post_id || !$this->is_einsatzbericht_post($post_id)) {
            return $commentdata;
        }

        if (!FEU_Einsatz_Template_Helpers::is_comments_enabled($post_id)) {
            wp_die(
                esc_html__('Kommentare sind fuer diesen Einsatzbericht deaktiviert.', 'feuer-einsatzberichte'),
                esc_html__('Kommentare deaktiviert', 'feuer-einsatzberichte'),
                [
                    'response' => 403,
                ]
            );
        }

        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();

            if ($current_user instanceof WP_User && $current_user->exists()) {
                $public_name = trim((string) $current_user->display_name);

                if ('' === $public_name) {
                    $public_name = trim((string) $current_user->nickname);
                }

                if ('' === $public_name) {
                    $public_name = trim((string) $current_user->user_login);
                }

                if ('' !== $public_name) {
                    $commentdata['comment_author'] = $public_name;
                }
            }

            return $commentdata;
        }

        wp_die(
            esc_html__('Kommentare zu Einsatzberichten sind nur fuer angemeldete Benutzer verfuegbar.', 'feuer-einsatzberichte'),
            esc_html__('Anmeldung erforderlich', 'feuer-einsatzberichte'),
            [
                'response' => 403,
            ]
        );
    }

    public function enqueue_scripts() {
        $public_style_path = FEU_EINSATZ_PLUGIN_DIR . 'assets/public/css/public-style.css';
        $public_script_path = FEU_EINSATZ_PLUGIN_DIR . 'assets/public/js/public-script.js';
        $public_style_version = file_exists($public_style_path) ? (string) filemtime($public_style_path) : FEU_EINSATZ_VERSION;
        $public_script_version = file_exists($public_script_path) ? (string) filemtime($public_script_path) : FEU_EINSATZ_VERSION;
        $current_post_id = get_queried_object_id();
        $current_post = $current_post_id ? get_post($current_post_id) : null;
        $current_post_content = $current_post instanceof WP_Post ? (string) $current_post->post_content : '';
        $is_area_page_request = $this->is_area_page_request($current_post_id);
        $is_single_report = is_singular('post') && $this->is_einsatzbericht_post($current_post_id);
        $is_overview_archive_request = $this->is_overview_archive_request();
        $is_overview_template_request = $current_post_id
            && is_page($current_post_id)
            && $this->get_primary_page_template_key() === (string) get_page_template_slug($current_post_id);
        $has_overview_shortcodes = $current_post instanceof WP_Post && (
            $this->has_shortcode_alias($current_post_content, 'overview_page')
            || $this->has_shortcode_alias($current_post_content, 'overview_list')
            || $this->has_shortcode_alias($current_post_content, 'overview_sidebar')
            || $this->has_shortcode_alias($current_post_content, 'latest')
            || $this->has_shortcode_alias($current_post_content, 'alarm_list')
        );
        $needs_public_runtime_script = $is_single_report || $has_overview_shortcodes || $is_overview_template_request || $is_overview_archive_request;
        $public_strings = [
            'mapUnavailable' => __('Kartenansicht nicht verfuegbar.', 'feuer-einsatzberichte'),
            'mapUnavailableHint' => __('Fuer diesen Einsatz sind noch keine ausreichenden Kartendaten gespeichert.', 'feuer-einsatzberichte'),
            'pedestrianPartLabel' => __('Fussgaengerbereich', 'feuer-einsatzberichte'),
            'streetFallback' => __('Strasse', 'feuer-einsatzberichte'),
            'mapConsentTitle' => __('Datenschutz-Hinweis', 'feuer-einsatzberichte'),
            'mapConsentText' => __('Beim Laden der Live-Karte werden externe Kartendaten von OpenStreetMap nachgeladen.', 'feuer-einsatzberichte'),
            'mapConsentAllow' => __('Live-Karte laden', 'feuer-einsatzberichte'),
            'mapConsentDecline' => __('Nicht laden', 'feuer-einsatzberichte'),
            'mapPrivacyRejected' => __('Karte nicht geladen', 'feuer-einsatzberichte'),
            'mapPrivacyRejectedHint' => __('Sie haben das Nachladen externer Kartendaten abgelehnt.', 'feuer-einsatzberichte'),
        ];
        $area_map_strings = [
            'mapUnavailable' => __('Einsatzgebiet-Karte nicht verfuegbar.', 'feuer-einsatzberichte'),
            'mapUnavailableHint' => __('Bitte pruefen Sie die hinterlegten PLZ oder die Kartendaten.', 'feuer-einsatzberichte'),
            'callSingular' => __('Einsatz', 'feuer-einsatzberichte'),
            'callPlural' => __('Einsaetze', 'feuer-einsatzberichte'),
            'streetsLabel' => __('Strassen', 'feuer-einsatzberichte'),
            'moreStreetsLabel' => __('weitere Strassen', 'feuer-einsatzberichte'),
        ];

        wp_enqueue_style(
            'feu-einsatz-public-style',
            FEU_EINSATZ_PLUGIN_URL . 'assets/public/css/public-style.css',
            [],
            $public_style_version
        );

        if ($needs_public_runtime_script && !$is_single_report) {
            wp_enqueue_script(
                'feu-einsatz-public-script',
                FEU_EINSATZ_PLUGIN_URL . 'assets/public/js/public-script.js',
                [],
                $public_script_version,
                true
            );
        }

        if ($is_single_report) {
            $single_map_display_mode = FEU_Einsatz_Template_Helpers::normalize_single_map_display_mode(get_option('feu_einsatz_single_map_display_mode', 'live'));
            $cookie_map_integration_active = FEU_Einsatz_Template_Helpers::has_cookie_map_consent_integration();
            $cookie_map_consent_granted = !$cookie_map_integration_active || FEU_Einsatz_Template_Helpers::has_cookie_map_consent();
            $should_load_single_map_runtime = 'live' === $single_map_display_mode && $cookie_map_consent_granted;

            wp_enqueue_style(
                'dashicons',
                includes_url('css/dashicons.min.css'),
                [],
                get_bloginfo('version')
            );

            if ($should_load_single_map_runtime) {
                wp_enqueue_style(
                    'feu-einsatz-leaflet',
                    FEU_EINSATZ_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.css',
                    [],
                    '1.9.4'
                );

                wp_enqueue_script(
                    'feu-einsatz-leaflet',
                    FEU_EINSATZ_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.js',
                    [],
                    '1.9.4',
                    true
                );

                wp_enqueue_script(
                    'feu-einsatz-public-script',
                    FEU_EINSATZ_PLUGIN_URL . 'assets/public/js/public-script.js',
                    ['feu-einsatz-leaflet'],
                    $public_script_version,
                    true
                );
            } else {
                wp_enqueue_script(
                    'feu-einsatz-public-script',
                    FEU_EINSATZ_PLUGIN_URL . 'assets/public/js/public-script.js',
                    [],
                    $public_script_version,
                    true
                );
            }

            if (FEU_Einsatz_Template_Helpers::is_comments_enabled($current_post_id) && get_option('thread_comments')) {
                wp_enqueue_script('comment-reply');
            }
        }

        if ($is_area_page_request && $this->is_area_page_enabled()) {
            $area_script_path = FEU_EINSATZ_PLUGIN_DIR . 'assets/public/js/area-map.js';
            $area_script_version = file_exists($area_script_path) ? (string) filemtime($area_script_path) : FEU_EINSATZ_VERSION;

            wp_enqueue_style(
                'feu-einsatz-leaflet',
                FEU_EINSATZ_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.css',
                [],
                '1.9.4'
            );

            wp_enqueue_script(
                'feu-einsatz-leaflet',
                FEU_EINSATZ_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.js',
                [],
                '1.9.4',
                true
            );

            wp_enqueue_script(
                'feu-einsatz-area-map-script',
                FEU_EINSATZ_PLUGIN_URL . 'assets/public/js/area-map.js',
                ['feu-einsatz-leaflet'],
                $area_script_version,
                true
            );

            wp_localize_script('feu-einsatz-area-map-script', 'feu_einsatz_area_map', [
                'strings' => [
                    'mapUnavailable' => __('Einsatzgebiet-Karte nicht verfuegbar.', 'feuer-einsatzberichte'),
                    'mapUnavailableHint' => __('Bitte pruefen Sie die hinterlegten PLZ oder die Kartendaten.', 'feuer-einsatzberichte'),
                    'callSingular' => __('Einsatz', 'feuer-einsatzberichte'),
                    'callPlural' => __('Einsaetze', 'feuer-einsatzberichte'),
                    'streetsLabel' => __('Strassen', 'feuer-einsatzberichte'),
                    'moreStreetsLabel' => __('weitere Strassen', 'feuer-einsatzberichte'),
                ],
            ]);

            wp_localize_script('feu-einsatz-area-map-script', 'feu_einsatz_area_map', [
                'strings' => $area_map_strings,
            ]);
        }

        if (false && wp_script_is('feu-einsatz-public-script', 'enqueued')) {
            wp_add_inline_script(
                'feu-einsatz-public-script',
                'window.feu_einsatz_public = Object.assign({}, window.feu_einsatz_public || {}, {strings: ' . wp_json_encode([
                    'mapUnavailable' => __('Kartenansicht nicht verfuegbar.', 'feuer-einsatzberichte'),
                    'mapUnavailableHint' => __('Fuer diesen Einsatz sind noch keine ausreichenden Kartendaten gespeichert.', 'feuer-einsatzberichte'),
                    'pedestrianPartLabel' => __('Fussgaengerbereich', 'feuer-einsatzberichte'),
                    'streetFallback' => __('Strasse', 'feuer-einsatzberichte'),
                ]) . '});',
                'after'
            );
        }

        if (false && wp_script_is('feu-einsatz-area-map-script', 'enqueued')) {
            wp_add_inline_script(
                'feu-einsatz-area-map-script',
                'window.feu_einsatz_area_map = Object.assign({}, window.feu_einsatz_area_map || {}, {strings: ' . wp_json_encode([
                    'mapUnavailable' => __('Einsatzgebiet-Karte nicht verfuegbar.', 'feuer-einsatzberichte'),
                    'mapUnavailableHint' => __('Bitte pruefen Sie die hinterlegten PLZ oder die Kartendaten.', 'feuer-einsatzberichte'),
                    'callSingular' => __('Einsatz', 'feuer-einsatzberichte'),
                    'callPlural' => __('Einsaetze', 'feuer-einsatzberichte'),
                    'streetsLabel' => __('Strassen', 'feuer-einsatzberichte'),
                    'moreStreetsLabel' => __('weitere Strassen', 'feuer-einsatzberichte'),
                ]) . '});',
                'after'
            );
        }

        if (wp_script_is('feu-einsatz-public-script', 'enqueued')) {
            wp_add_inline_script(
                'feu-einsatz-public-script',
                'window.feu_einsatz_public = Object.assign({}, window.feu_einsatz_public || {}, {strings: ' . wp_json_encode($public_strings) . '});',
                'after'
            );
        }

        if (wp_script_is('feu-einsatz-area-map-script', 'enqueued')) {
            wp_add_inline_script(
                'feu-einsatz-area-map-script',
                'window.feu_einsatz_area_map = Object.assign({}, window.feu_einsatz_area_map || {}, {strings: ' . wp_json_encode($area_map_strings) . '});',
                'after'
            );
        }
    }

    public function render_einsatz_count_shortcode($atts = [], $content = null, $shortcode_tag = '') {
        $atts = shortcode_atts([
            'zeitraum' => 'aktuell',
        ], (array) $atts, (string) $shortcode_tag);

        $zeitraum = sanitize_key((string) $atts['zeitraum']);
        $jahr = (int) current_time('Y');

        if (in_array($zeitraum, ['vorjahr', 'previous', 'last'], true)) {
            $jahr--;
        }

        $statistics = $this->db->get_total_statistics($jahr);
        $count = is_array($statistics) && isset($statistics['total_einsaetze'])
            ? (int) $statistics['total_einsaetze']
            : 0;

        return (string) $count;
    }

    public function render_current_einsatz_count_shortcode() {
        return $this->render_einsatz_count_shortcode([
            'zeitraum' => 'aktuell',
        ], null, $this->get_primary_shortcode_tag('current'));
    }

    public function render_previous_einsatz_count_shortcode() {
        return $this->render_einsatz_count_shortcode([
            'zeitraum' => 'vorjahr',
        ], null, $this->get_primary_shortcode_tag('previous'));
    }

    private function get_overview_context($atts = []) {
        $atts = shortcode_atts([
            'jahr' => '',
            'posts_per_page' => 10,
        ], (array) $atts, $this->get_primary_shortcode_tag('overview_list'));

        $selected_year = '';

        if (isset($_REQUEST['jahr'])) {
            $selected_year = FEU_Einsatz_Template_Helpers::sanitize_overview_year(wp_unslash($_REQUEST['jahr']));
        }

        if ('' === $selected_year) {
            $selected_year = FEU_Einsatz_Template_Helpers::sanitize_overview_year($atts['jahr']);
        }

        $paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
        $posts_per_page = max(1, absint($atts['posts_per_page']));
        $cache_key = $selected_year . ':' . $paged . ':' . $posts_per_page;

        if (!isset($this->overview_context_cache[$cache_key])) {
            $this->overview_context_cache[$cache_key] = FEU_Einsatz_Template_Helpers::get_overview_context([
                'selected_year' => $selected_year,
                'paged' => $paged,
                'posts_per_page' => $posts_per_page,
            ]);
        }

        return $this->overview_context_cache[$cache_key];
    }

    private function get_overview_card_variant($atts = []) {
        $variant = '';

        if (!empty($atts['card_variant'])) {
            $variant = $atts['card_variant'];
        } elseif (!empty($atts['card_style'])) {
            $variant = $atts['card_style'];
        } elseif (!empty($atts['kartenstil'])) {
            $variant = $atts['kartenstil'];
        } else {
            $variant = get_option('feu_einsatz_default_card_variant', 'modern');
        }

        return FEU_Einsatz_Template_Helpers::normalize_report_card_variant($variant);
    }

    public function render_overview_list_shortcode($atts = []) {
        $context = $this->get_overview_context($atts);
        $context['card_variant'] = $this->get_overview_card_variant((array) $atts);

        return FEU_Einsatz_Template_Helpers::render('templates/public/overview/list.php', [
            'context' => $context,
        ]);
    }

    public function render_overview_sidebar_shortcode($atts = []) {
        $context = $this->get_overview_context($atts);

        return FEU_Einsatz_Template_Helpers::render('templates/public/overview/sidebar.php', [
            'context' => $context,
        ]);
    }

    public function render_overview_page_shortcode($atts = []) {
        $context = $this->get_overview_context($atts);
        $context['card_variant'] = $this->get_overview_card_variant((array) $atts);

        return FEU_Einsatz_Template_Helpers::render('templates/public/overview/page.php', [
            'context' => $context,
        ]);
    }

    private function build_alarm_list_context($atts, $shortcode_tag) {
        $atts = shortcode_atts([
            'anzahl' => 3,
            'jahr' => '',
            'uebertitel' => __('Aktuelles Einsatzgeschehen', 'feuer-einsatzberichte'),
            'titel' => __('Zuletzt alarmiert.', 'feuer-einsatzberichte'),
            'alle_text' => __('Alle Einsätze', 'feuer-einsatzberichte'),
            'alle_url' => '',
            'anzeigen' => 'zeit,nummer,ort,fotos',
            'beschreibung' => '1',
        ], (array) $atts, (string) $shortcode_tag);

        $selected_year = FEU_Einsatz_Template_Helpers::sanitize_overview_year($atts['jahr']);
        $limit = max(1, min(50, absint($atts['anzahl'])));
        $context = FEU_Einsatz_Template_Helpers::get_overview_context([
            'selected_year' => $selected_year,
            'paged' => 1,
            'posts_per_page' => $limit,
        ]);
        $raw_visible_fields = preg_split('/[\s,;|]+/', strtolower((string) $atts['anzeigen']));
        $visible_fields = array_values(array_filter(array_unique(array_map(
            'sanitize_key',
            is_array($raw_visible_fields) ? $raw_visible_fields : []
        ))));
        $all_url = esc_url_raw((string) $atts['alle_url']);

        if ('' === $all_url) {
            $overview_page = get_page_by_path('einsaetze');

            if ($overview_page instanceof WP_Post) {
                $overview_page_url = get_permalink($overview_page);

                if ($overview_page_url) {
                    $all_url = (string) $overview_page_url;
                }
            }
        }

        if ('' === $all_url && !empty($context['root_category']) && $context['root_category'] instanceof WP_Term) {
            $category_url = get_term_link($context['root_category']);

            if (!is_wp_error($category_url)) {
                $all_url = (string) $category_url;
            }
        }

        if ('' === $all_url) {
            $all_url = home_url('/einsaetze/');
        }

        $context['alarm_list'] = [
            'limit' => $limit,
            'eyebrow' => sanitize_text_field((string) $atts['uebertitel']),
            'title' => sanitize_text_field((string) $atts['titel']),
            'all_text' => sanitize_text_field((string) $atts['alle_text']),
            'all_url' => $all_url,
            'visible_fields' => $visible_fields,
            'show_description' => !in_array(strtolower(trim((string) $atts['beschreibung'])), ['0', 'false', 'nein', 'no', 'off'], true),
        ];

        return $context;
    }

    public function render_alarm_list_shortcode($atts = []) {
        $context = $this->build_alarm_list_context($atts, $this->get_primary_shortcode_tag('alarm_list'));

        return FEU_Einsatz_Template_Helpers::render('templates/public/overview/alarm-list.php', [
            'context' => $context,
        ]);
    }

    public function render_latest_reports_shortcode($atts = []) {
        $atts = shortcode_atts([
            'anzahl' => 3,
            'format' => 'cards',
            'jahr' => '',
            'card_variant' => '',
            'card_style' => '',
            'kartenstil' => '',
            'uebertitel' => __('Aktuelles Einsatzgeschehen', 'feuer-einsatzberichte'),
            'titel' => __('Zuletzt alarmiert.', 'feuer-einsatzberichte'),
            'alle_text' => __('Alle Einsätze', 'feuer-einsatzberichte'),
            'alle_url' => '',
            'anzeigen' => 'zeit,nummer,ort,fotos',
            'beschreibung' => '1',
        ], (array) $atts, $this->get_primary_shortcode_tag('latest'));

        $selected_year = FEU_Einsatz_Template_Helpers::sanitize_overview_year($atts['jahr']);
        $limit = max(1, min(50, absint($atts['anzahl'])));
        $format = strtolower(trim((string) $atts['format']));
        $card_variant = $this->get_overview_card_variant($atts);

        if (in_array($format, ['liste', 'list', 'kompakt'], true)) {
            $format = 'compact';
        }

        if (in_array($format, ['alarm', 'alarme', 'timeline'], true)) {
            return FEU_Einsatz_Template_Helpers::render('templates/public/overview/alarm-list.php', [
                'context' => $this->build_alarm_list_context($atts, $this->get_primary_shortcode_tag('latest')),
            ]);
        }

        if (!in_array($format, ['cards', 'compact'], true)) {
            $format = 'cards';
        }

        $context = FEU_Einsatz_Template_Helpers::get_overview_context([
            'selected_year' => $selected_year,
            'paged' => 1,
            'posts_per_page' => $limit,
        ]);

        $context['latest_limit'] = $limit;
        $context['latest_format'] = $format;
        $context['card_variant'] = $card_variant;

        return FEU_Einsatz_Template_Helpers::render('templates/public/overview/latest.php', [
            'context' => $context,
        ]);
    }

    public function render_area_page_shortcode($atts = []) {
        if (!$this->is_area_page_enabled()) {
            return current_user_can('manage_options')
                ? '<div class="feu-einsatz-area-page feu-einsatz-area-page-disabled">' . esc_html__('Die Einsatzgebiet-Seite ist derzeit in den Plugin-Einstellungen deaktiviert.', 'feuer-einsatzberichte') . '</div>'
                : '';
        }

        $context = $this->get_area_page_context();

        return FEU_Einsatz_Template_Helpers::render('templates/public/area/page.php', [
            'context' => $context,
        ]);
    }
}



