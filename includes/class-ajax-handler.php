<?php
if (!defined('ABSPATH')) {
    exit;
}

class FEU_Einsatz_Ajax_Handler {

    private $db;

    public function __construct($database) {
        $this->db = $database;
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_ajax_feu_einsatz_search_streets', [$this, 'feu_einsatz_search_streets']);
        add_action('wp_ajax_feu_einsatz_get_street_registry', [$this, 'feu_einsatz_get_street_registry']);
        add_action('wp_ajax_feu_einsatz_save_street_registry_entry', [$this, 'feu_einsatz_save_street_registry_entry']);
        add_action('wp_ajax_feu_einsatz_delete_street_registry_entry', [$this, 'feu_einsatz_delete_street_registry_entry']);
        add_action('wp_ajax_feu_einsatz_get_participant_details', [$this, 'feu_einsatz_get_participant_details']);
        add_action('wp_ajax_feu_einsatz_get_statistics', [$this, 'feu_einsatz_get_statistics']);
        add_action('wp_ajax_feu_einsatz_save_participant', [$this, 'feu_einsatz_save_participant']);
        add_action('wp_ajax_feu_einsatz_toggle_participant_archive', [$this, 'feu_einsatz_toggle_participant_archive']);
        add_action('wp_ajax_feu_einsatz_delete_participant', [$this, 'feu_einsatz_delete_participant']);
        add_action('wp_ajax_feu_einsatz_save_organization', [$this, 'feu_einsatz_save_organization']);
        add_action('wp_ajax_feu_einsatz_sort_organizations', [$this, 'feu_einsatz_sort_organizations']);
        add_action('wp_ajax_feu_einsatz_toggle_organization_archive', [$this, 'feu_einsatz_toggle_organization_archive']);
        add_action('wp_ajax_feu_einsatz_delete_organization', [$this, 'feu_einsatz_delete_organization']);
    }

    private function verify_section_request($section) {
        if (!check_ajax_referer('feu_einsatz_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Sicherheitsfehler', 'feuer-einsatzberichte')], 403);
            return false;
        }

        if (!FEU_Einsatz_Admin::current_user_can_access_plugin_section($section)) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'feuer-einsatzberichte')], 403);
            return false;
        }

        return true;
    }

    private function verify_street_search_request() {
        if (!check_ajax_referer('feu_einsatz_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Sicherheitsfehler', 'feuer-einsatzberichte')], 403);
            return false;
        }

        if (
            !current_user_can('edit_posts')
            || !FEU_Einsatz_Admin::current_user_can_access_plugin_section('reports')
        ) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'feuer-einsatzberichte')], 403);
            return false;
        }

        return true;
    }

    private function can_remove_participant() {
        if (is_multisite()) {
            return is_super_admin();
        }

        return current_user_can('manage_options')
            && current_user_can('delete_users')
            && current_user_can('activate_plugins');
    }

    private function normalize_id_list($values) {
        return array_values(
            array_unique(
                array_filter(
                    array_map('absint', (array) $values)
                )
            )
        );
    }

    private function normalize_entry_date($value) {
        $value = sanitize_text_field((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return '';
    }

    private function sanitize_category_ids($values) {
        $category_ids = $this->normalize_id_list($values);
        $allowed_categories = array_values(
            array_unique(
                array_filter(
                    array_map('absint', (array) get_option('feu_einsatz_categories', []))
                )
            )
        );

        if (empty($allowed_categories)) {
            return $category_ids;
        }

        return array_values(array_intersect($category_ids, $allowed_categories));
    }

    private function collect_participant_payload() {
        $payload = [
            'vorname' => isset($_POST['vorname']) ? sanitize_text_field(wp_unslash($_POST['vorname'])) : '',
            'nachname' => isset($_POST['nachname']) ? sanitize_text_field(wp_unslash($_POST['nachname'])) : '',
            'job_title' => isset($_POST['job_title']) ? sanitize_text_field(wp_unslash($_POST['job_title'])) : '',
            'entry_date' => $this->normalize_entry_date(isset($_POST['entry_date']) ? wp_unslash($_POST['entry_date']) : ''),
            'rank_title' => isset($_POST['rank_title']) ? sanitize_text_field(wp_unslash($_POST['rank_title'])) : '',
            'member_function' => isset($_POST['member_function']) ? sanitize_text_field(wp_unslash($_POST['member_function'])) : '',
            'education' => isset($_POST['education']) ? sanitize_text_field(wp_unslash($_POST['education'])) : '',
            'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
            'sort_order' => isset($_POST['sort_order']) ? absint(wp_unslash($_POST['sort_order'])) : 0,
            'default_functions' => isset($_POST['default_functions']) && is_array($_POST['default_functions'])
                ? array_map('sanitize_text_field', wp_unslash($_POST['default_functions']))
                : [],
            'category_ids' => $this->sanitize_category_ids(
                isset($_POST['category_ids']) && is_array($_POST['category_ids'])
                    ? wp_unslash($_POST['category_ids'])
                    : []
            ),
        ];

        $existing_gallery_ids = $this->normalize_id_list(
            isset($_POST['gallery_existing']) && is_array($_POST['gallery_existing'])
                ? wp_unslash($_POST['gallery_existing'])
                : []
        );
        $remove_gallery_ids = $this->normalize_id_list(
            isset($_POST['gallery_remove']) && is_array($_POST['gallery_remove'])
                ? wp_unslash($_POST['gallery_remove'])
                : []
        );

        $payload['gallery_ids'] = array_values(array_filter(
            array_diff($existing_gallery_ids, $remove_gallery_ids),
            static function ($attachment_id) {
                return 'attachment' === get_post_type($attachment_id)
                    && 0 === strpos((string) get_post_mime_type($attachment_id), 'image/')
                    && current_user_can('edit_post', $attachment_id);
            }
        ));

        return $payload;
    }

    public function filter_participant_upload_dir($uploads) {
        $subdir = '/feuer-einsatzberichte-area';

        $uploads['subdir'] = $subdir;
        $uploads['path'] = $uploads['basedir'] . $subdir;
        $uploads['url'] = $uploads['baseurl'] . $subdir;

        if (!file_exists($uploads['path'])) {
            wp_mkdir_p($uploads['path']);
        }

        return $uploads;
    }

    private function handle_participant_gallery_uploads($participant_name = '') {
        if (
            empty($_FILES['participant_gallery'])
            || empty($_FILES['participant_gallery']['name'])
            || !is_array($_FILES['participant_gallery']['name'])
        ) {
            return [];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $uploaded_ids = [];
        $files = $_FILES['participant_gallery'];
        $file_count = count($files['name']);

        for ($i = 0; $i < $file_count; $i++) {
            if (UPLOAD_ERR_NO_FILE === (int) $files['error'][$i]) {
                continue;
            }

            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];

            add_filter('upload_dir', [$this, 'filter_participant_upload_dir']);
            $uploaded = wp_handle_upload(
                $file,
                [
                    'test_form' => false,
                    'mimes' => [
                        'jpg|jpeg|jpe' => 'image/jpeg',
                        'png' => 'image/png',
                        'gif' => 'image/gif',
                        'webp' => 'image/webp',
                    ],
                ]
            );
            remove_filter('upload_dir', [$this, 'filter_participant_upload_dir']);

            if (!empty($uploaded['error']) || empty($uploaded['file'])) {
                continue;
            }

            $this->apply_text_watermark($uploaded['file'], $participant_name);

            $attachment_id = wp_insert_attachment(
                [
                    'guid' => $uploaded['url'],
                    'post_mime_type' => $uploaded['type'],
                    'post_title' => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
                    'post_status' => 'inherit',
                ],
                $uploaded['file'],
                0
            );

            if (is_wp_error($attachment_id)) {
                continue;
            }

            $metadata = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
            wp_update_attachment_metadata($attachment_id, $metadata);
            $uploaded_ids[] = (int) $attachment_id;
        }

        return $uploaded_ids;
    }

    private function apply_text_watermark($file_path, $participant_name = '') {
        if (1 !== (int) get_option('feu_einsatz_photo_watermark_enabled', 1)) {
            return false;
        }

        if (!file_exists($file_path)) {
            return false;
        }

        $watermark_suffix = trim((string) get_option('feu_einsatz_photo_watermark_text', ''));
        $watermark_text = trim(implode(' ', array_filter([$participant_name, $watermark_suffix])));

        if ('' === $watermark_text) {
            return false;
        }

        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        $image = $this->create_image_resource($file_path, $extension);

        if (!$image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if (!$width || !$height) {
            imagedestroy($image);
            return false;
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $font = 5;
        $text = $this->prepare_watermark_text($watermark_text, $width, $font);
        $padding = 14;
        $text_width = imagefontwidth($font) * strlen($text);
        $text_height = imagefontheight($font);
        $bar_height = $text_height + ($padding * 2);
        $x = max(10, (int) floor(($width - $text_width) / 2));
        $y = $height - $bar_height + $padding;

        $overlay_color = imagecolorallocatealpha($image, 0, 0, 0, 55);
        $text_color = imagecolorallocatealpha($image, 255, 255, 255, 0);

        imagefilledrectangle($image, 0, $height - $bar_height, $width, $height, $overlay_color);
        imagestring($image, $font, $x, $y, $text, $text_color);

        $saved = $this->save_image_resource($image, $file_path, $extension);
        imagedestroy($image);

        return $saved;
    }

    private function create_image_resource($file_path, $extension) {
        if ('jpg' === $extension || 'jpeg' === $extension || 'jpe' === $extension) {
            return function_exists('imagecreatefromjpeg') ? imagecreatefromjpeg($file_path) : false;
        }

        if ('png' === $extension) {
            return function_exists('imagecreatefrompng') ? imagecreatefrompng($file_path) : false;
        }

        if ('gif' === $extension) {
            return function_exists('imagecreatefromgif') ? imagecreatefromgif($file_path) : false;
        }

        if ('webp' === $extension) {
            return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($file_path) : false;
        }

        return false;
    }

    private function save_image_resource($image, $file_path, $extension) {
        if ('jpg' === $extension || 'jpeg' === $extension || 'jpe' === $extension) {
            return imagejpeg($image, $file_path, 90);
        }

        if ('png' === $extension) {
            return imagepng($image, $file_path, 6);
        }

        if ('gif' === $extension) {
            return imagegif($image, $file_path);
        }

        if ('webp' === $extension && function_exists('imagewebp')) {
            return imagewebp($image, $file_path, 90);
        }

        return false;
    }

    private function prepare_watermark_text($text, $width, $font) {
        $safe_text = $text;

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
            if (false !== $converted) {
                $safe_text = $converted;
            }
        }

        $max_chars = (int) floor(max(20, ($width - 24)) / imagefontwidth($font));
        if (strlen($safe_text) > $max_chars) {
            $safe_text = substr($safe_text, 0, max(0, $max_chars - 3)) . '...';
        }

        return $safe_text;
    }

    public function feu_einsatz_get_participant_details() {
        if (!$this->verify_section_request('statistics')) {
            return;
        }

        if (!FEU_Einsatz_Admin::is_participant_ranking_unlocked_for_current_user()) {
            wp_send_json_error(['message' => __('Teilnehmer-Ranking ist gesperrt. Bitte PIN eingeben.', 'feuer-einsatzberichte')], 403);
            return;
        }

        $teilnehmer_id = isset($_POST['teilnehmer_id']) ? absint(wp_unslash($_POST['teilnehmer_id'])) : 0;
        $jahr = isset($_POST['jahr']) ? absint(wp_unslash($_POST['jahr'])) : (int) date('Y');

        if (!$teilnehmer_id) {
            wp_send_json_error(['message' => __('Keine Teilnehmer-ID', 'feuer-einsatzberichte')], 400);
            return;
        }

        $einsaetze = $this->db->get_participant_details($teilnehmer_id, $jahr);
        $funktionen = $this->db->get_participant_function_stats($teilnehmer_id, $jahr);

        $formatted_einsaetze = array_map(static function($einsatz) {
            $date_source = !empty($einsatz->event_date) ? $einsatz->event_date : $einsatz->post_date;

            return [
                'id' => (int) $einsatz->ID,
                'post_title' => $einsatz->post_title,
                'post_date' => $date_source,
                'date_label' => mysql2date('d.m.Y', $date_source),
                'funktion' => $einsatz->funktion ?: __('Ohne Funktion', 'feuer-einsatzberichte'),
                'permalink' => get_permalink($einsatz->ID),
            ];
        }, $einsaetze);

        $top_function = '';
        if (!empty($funktionen[0]) && !empty($funktionen[0]->funktion)) {
            $top_function = $funktionen[0]->funktion;
        }

        $last_einsatz_date = '';
        $first_einsatz_date = '';

        if (!empty($formatted_einsaetze)) {
            $last_einsatz_date = !empty($formatted_einsaetze[0]['date_label']) ? $formatted_einsaetze[0]['date_label'] : '';
            $first_index = count($formatted_einsaetze) - 1;
            $first_einsatz_date = !empty($formatted_einsaetze[$first_index]['date_label']) ? $formatted_einsaetze[$first_index]['date_label'] : '';
        }

        $summary = [
            'total_einsaetze' => count($formatted_einsaetze),
            'top_function' => $top_function,
            'last_einsatz_date' => $last_einsatz_date,
            'first_einsatz_date' => $first_einsatz_date,
        ];

        wp_send_json_success([
            'einsaetze' => $formatted_einsaetze,
            'funktionen' => $funktionen,
            'summary' => $summary,
        ]);
    }

    public function feu_einsatz_get_statistics() {
        if (!$this->verify_section_request('statistics')) {
            return;
        }

        $jahr = isset($_POST['jahr']) ? absint(wp_unslash($_POST['jahr'])) : (int) date('Y');

        $statistics_dashboard_data = $this->db->get_statistics_dashboard_data($jahr);
        $data = [
            'total' => $statistics_dashboard_data['total'],
            'categories' => $statistics_dashboard_data['categories'],
            'participants' => FEU_Einsatz_Admin::is_participant_ranking_unlocked_for_current_user()
                ? $statistics_dashboard_data['participants']
                : [],
        ];

        wp_send_json_success($data);
    }

    public function feu_einsatz_search_streets() {
        if (!$this->verify_street_search_request()) {
            return;
        }

        $query = isset($_POST['query']) ? sanitize_text_field(wp_unslash($_POST['query'])) : '';
        $limit = isset($_POST['limit']) ? absint(wp_unslash($_POST['limit'])) : 5;

        if ('' === trim($query)) {
            wp_send_json_success([
                'items' => [],
            ]);
            return;
        }

        $items = FEU_Einsatz_Template_Helpers::search_street_suggestion_records($query, $limit);

        wp_send_json_success([
            'items' => $items,
        ]);
    }

    public function feu_einsatz_get_street_registry() {
        if (!$this->verify_section_request('settings')) {
            return;
        }

        $limit = isset($_POST['limit']) ? absint(wp_unslash($_POST['limit'])) : 1000;
        $limit = max(20, min(1000, $limit));
        $registry_rows = $this->db->get_street_registry_entries(['limit' => $limit]);
        $registry_entries = [];

        foreach ((array) $registry_rows as $row) {
            $entry = $this->format_street_registry_entry_response($row);

            if ($entry) {
                $registry_entries[] = $entry;
            }
        }

        $known_entries = FEU_Einsatz_Template_Helpers::get_street_suggestion_records($limit);
        foreach ($known_entries as &$known_entry) {
            $known_street = isset($known_entry['street']) ? (string) $known_entry['street'] : '';
            $known_postcode = isset($known_entry['plz']) ? (string) $known_entry['plz'] : '';
            $known_city = isset($known_entry['city']) ? (string) $known_entry['city'] : '';
            $known_entry['report_url'] = add_query_arg(
                array_filter(
                    [
                        'post_type' => 'post',
                        'feu_einsatz_filter' => '1',
                        'feu_einsatz_street' => $known_street,
                        'feu_einsatz_postcode' => $known_postcode,
                        'feu_einsatz_city' => $known_city,
                    ],
                    static function ($value) {
                        return '' !== (string) $value;
                    }
                ),
                admin_url('edit.php')
            );
        }
        unset($known_entry);

        wp_send_json_success([
            'registry_entries' => $registry_entries,
            'known_entries' => $known_entries,
        ]);
    }

    public function feu_einsatz_save_street_registry_entry() {
        if (!$this->verify_section_request('settings')) {
            return;
        }

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
        $street = isset($_POST['street']) ? sanitize_text_field(wp_unslash($_POST['street'])) : '';
        $postcode = isset($_POST['postcode']) ? sanitize_text_field(wp_unslash($_POST['postcode'])) : '';
        $city = isset($_POST['city']) ? sanitize_text_field(wp_unslash($_POST['city'])) : '';

        if ('' === trim($street)) {
            wp_send_json_error(['message' => __('Bitte eine Strasse eingeben.', 'feuer-einsatzberichte')], 400);
            return;
        }

        $result = $this->db->save_street_registry_entry($id, $street, $postcode, $city);

        if (false === $result) {
            wp_send_json_error(['message' => __('Strasse konnte nicht gespeichert werden. Ein identischer Eintrag existiert eventuell bereits.', 'feuer-einsatzberichte')], 500);
            return;
        }

        $entry_id = $id > 0 ? $id : (int) $result;
        $entry = $this->db->get_street_registry_entry($entry_id);

        wp_send_json_success([
            'id' => $entry_id,
            'entry' => $this->format_street_registry_entry_response($entry),
        ]);
    }

    public function feu_einsatz_delete_street_registry_entry() {
        if (!$this->verify_section_request('settings')) {
            return;
        }

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;

        if ($id < 1) {
            wp_send_json_error(['message' => __('Ungueltige ID.', 'feuer-einsatzberichte')], 400);
            return;
        }

        if ($this->db->count_posts_using_street_registry_entry($id) > 0) {
            wp_send_json_error(['message' => __('Diese Strasse wird bereits in Einsatzberichten verwendet und kann deshalb nicht geloescht werden.', 'feuer-einsatzberichte')], 409);
            return;
        }

        if (!$this->db->delete_street_registry_entry($id)) {
            wp_send_json_error(['message' => __('Strasse konnte nicht geloescht werden.', 'feuer-einsatzberichte')], 500);
            return;
        }

        wp_send_json_success(['id' => $id]);
    }

    private function format_street_registry_entry_response($entry) {
        if (!$entry || empty($entry->id)) {
            return null;
        }

        $street = isset($entry->street) ? (string) $entry->street : '';
        $postcode = isset($entry->postcode) ? (string) $entry->postcode : '';
        $city = isset($entry->city) ? (string) $entry->city : '';
        $usage_count = $this->db->count_posts_using_street_registry_entry((int) $entry->id);
        $report_url = add_query_arg(
            array_filter(
                [
                    'post_type' => 'post',
                    'feu_einsatz_filter' => '1',
                    'feu_einsatz_street' => $street,
                    'feu_einsatz_postcode' => $postcode,
                    'feu_einsatz_city' => $city,
                ],
                static function ($value) {
                    return '' !== (string) $value;
                }
            ),
            admin_url('edit.php')
        );

        return [
            'id' => (int) $entry->id,
            'street' => $street,
            'postcode' => $postcode,
            'city' => $city,
            'usage_count' => max(0, (int) $usage_count),
            'report_url' => $report_url,
            'can_delete' => $usage_count < 1,
        ];
    }

    public function feu_einsatz_save_participant() {
        if (!$this->verify_section_request('participants')) {
            return;
        }

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
        $participant = $this->collect_participant_payload();
        $existing_participant = $id > 0 ? $this->db->get_participant($id) : null;

        if ('' === $participant['vorname'] || '' === $participant['nachname']) {
            wp_send_json_error(['message' => __('Bitte alle Felder ausfuellen', 'feuer-einsatzberichte')], 400);
            return;
        }

        $participant_name = FEU_Einsatz_Template_Helpers::format_participant_name($participant['vorname'], $participant['nachname']);
        $uploaded_gallery_ids = $this->handle_participant_gallery_uploads($participant_name);

        if (!empty($uploaded_gallery_ids)) {
            $participant['gallery_ids'] = array_values(
                array_unique(
                    array_merge($participant['gallery_ids'], $uploaded_gallery_ids)
                )
            );
        }

        if (
            $id > 0
            && empty($uploaded_gallery_ids)
            && !isset($_POST['gallery_existing'])
            && !isset($_POST['gallery_remove'])
            && $existing_participant
            && !empty($existing_participant->gallery_ids)
            && is_array($existing_participant->gallery_ids)
        ) {
            $participant['gallery_ids'] = array_values(array_map('absint', $existing_participant->gallery_ids));
        }

        $participant['primary_image_id'] = !empty($participant['gallery_ids'])
            ? (int) $participant['gallery_ids'][0]
            : 0;

        if ($id > 0) {
            if ($existing_participant) {
                if (!isset($_POST['job_title'])) {
                    $participant['job_title'] = (string) ($existing_participant->job_title ?? '');
                }
                if (!isset($_POST['entry_date'])) {
                    $participant['entry_date'] = (string) ($existing_participant->entry_date ?? '');
                }
                if (!isset($_POST['rank_title'])) {
                    $participant['rank_title'] = (string) ($existing_participant->rank_title ?? '');
                }
                if (!isset($_POST['member_function'])) {
                    $participant['member_function'] = (string) ($existing_participant->member_function ?? '');
                }
                if (!isset($_POST['education'])) {
                    $participant['education'] = (string) ($existing_participant->education ?? '');
                }
                if (!isset($_POST['description'])) {
                    $participant['description'] = (string) ($existing_participant->description ?? '');
                }
                if (!isset($_POST['sort_order'])) {
                    $participant['sort_order'] = isset($existing_participant->sort_order) ? absint($existing_participant->sort_order) : 0;
                }
                if (!isset($_POST['category_ids'])) {
                    $participant['category_ids'] = !empty($existing_participant->category_ids) && is_array($existing_participant->category_ids)
                        ? array_values(array_map('absint', $existing_participant->category_ids))
                        : [];
                }
                if (!isset($_POST['default_functions'])) {
                    $participant['default_functions'] = !empty($existing_participant->default_functions) && is_array($existing_participant->default_functions)
                        ? array_values(array_map('sanitize_text_field', $existing_participant->default_functions))
                        : [];
                }
            }

            if (isset($_POST['is_archived'])) {
                $participant['is_archived'] = absint(wp_unslash($_POST['is_archived']));
            } else {
                $participant['is_archived'] = $existing_participant && !empty($existing_participant->is_archived) ? 1 : 0;
                $participant['is_deleted'] = $existing_participant && !empty($existing_participant->is_deleted) ? 1 : 0;
            }

            if (!isset($participant['is_deleted'])) {
                $existing_participant = $existing_participant ?: $this->db->get_participant($id);
                $participant['is_deleted'] = $existing_participant && !empty($existing_participant->is_deleted) ? 1 : 0;
            }
        } else {
            $participant['is_archived'] = 0;
            $participant['is_deleted'] = 0;
        }

        $result = $this->db->save_participant($id, $participant);

        if (false !== $result) {
            $participant_id = $id > 0 ? $id : (int) $result;

            FEU_Einsatz_Logger::log(
                $id > 0 ? 'participant_updated' : 'participant_created',
                'participant',
                $participant_id,
                $id > 0 ? __('Teilnehmer aktualisiert', 'feuer-einsatzberichte') : __('Teilnehmer erstellt', 'feuer-einsatzberichte'),
                [
                    'name' => $participant_name,
                    'archived' => !empty($participant['is_archived']),
                    'deleted' => !empty($participant['is_deleted']),
                    'gallery_count' => count((array) $participant['gallery_ids']),
                    'default_functions' => array_values((array) $participant['default_functions']),
                ]
            );

            wp_send_json_success(['message' => __('Teilnehmer gespeichert', 'feuer-einsatzberichte')]);
            return;
        }

        wp_send_json_error(['message' => __('Fehler beim Speichern', 'feuer-einsatzberichte')], 500);
    }

    public function feu_einsatz_toggle_participant_archive() {
        if (!$this->verify_section_request('participants')) {
            return;
        }

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
        $archived = isset($_POST['archived']) ? absint(wp_unslash($_POST['archived'])) : 0;

        if ($id <= 0) {
            wp_send_json_error(['message' => __('Ungültige ID', 'feuer-einsatzberichte')], 400);
            return;
        }

        $result = $this->db->set_participant_archive_status($id, 1 === $archived);

        if ($result) {
            $participant = $this->db->get_participant($id);

            FEU_Einsatz_Logger::log(
                1 === $archived ? 'participant_archived' : 'participant_activated',
                'participant',
                $id,
                1 === $archived ? __('Teilnehmer archiviert', 'feuer-einsatzberichte') : __('Teilnehmer aktiviert', 'feuer-einsatzberichte'),
                [
                    'name' => $participant ? FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname) : '',
                ]
            );

            wp_send_json_success([
                'message' => 1 === $archived
                    ? __('Teilnehmer archiviert', 'feuer-einsatzberichte')
                    : __('Teilnehmer aktiviert', 'feuer-einsatzberichte'),
            ]);
            return;
        }

        wp_send_json_error(['message' => __('Status konnte nicht geaendert werden', 'feuer-einsatzberichte')], 500);
    }

    public function feu_einsatz_delete_participant() {
        if (!$this->verify_section_request('participants')) {
            return;
        }

        if (!$this->can_remove_participant()) {
            wp_send_json_error(
                ['message' => __('Nur die Hauptadministration darf Teilnehmer aus der Verwaltung entfernen.', 'feuer-einsatzberichte')],
                403
            );
            return;
        }

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;

        if ($id <= 0) {
            wp_send_json_error(['message' => __('Ungültige ID', 'feuer-einsatzberichte')], 400);
            return;
        }

        $result = $this->db->delete_participant($id);

        if ($result) {
            $participant = $this->db->get_participant($id);

            FEU_Einsatz_Logger::log(
                'participant_deleted',
                'participant',
                $id,
                __('Teilnehmer aus der Verwaltung entfernt', 'feuer-einsatzberichte'),
                [
                    'name' => $participant ? FEU_Einsatz_Template_Helpers::format_participant_name($participant->vorname, $participant->nachname) : '',
                ]
            );

            wp_send_json_success([
                'message' => __('Teilnehmer wurde aus der Verwaltung entfernt. Statistik und bestehende Einsatzberichte bleiben erhalten.', 'feuer-einsatzberichte'),
            ]);
            return;
        }

        wp_send_json_error(
            ['message' => __('Teilnehmer konnte nicht entfernt werden.', 'feuer-einsatzberichte')],
            500
        );
    }

    public function feu_einsatz_save_organization() {
        if (!$this->verify_section_request('participants')) {
            return;
        }

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $color = isset($_POST['color']) ? sanitize_hex_color(wp_unslash($_POST['color'])) : '';
        $post_link = isset($_POST['post_link']) ? esc_url_raw(wp_unslash($_POST['post_link'])) : '';

        if ('' === $name) {
            wp_send_json_error(['message' => __('Bitte Namen eingeben', 'feuer-einsatzberichte')], 400);
            return;
        }

        if (!$color) {
            $color = '#0a4b78';
        }

        $result = $this->db->save_organization($id, $name, $color, $post_link);

        if (false !== $result) {
            $organization_id = $id > 0 ? $id : (int) $result;

            FEU_Einsatz_Logger::log(
                $id > 0 ? 'organization_updated' : 'organization_created',
                'organization',
                $organization_id,
                $id > 0 ? __('Organisation aktualisiert', 'feuer-einsatzberichte') : __('Organisation erstellt', 'feuer-einsatzberichte'),
                [
                    'name' => $name,
                    'color' => $color,
                    'post_link' => $post_link,
                ]
            );

            wp_send_json_success([
                'message' => __('Organisation gespeichert', 'feuer-einsatzberichte'),
                'organization_id' => $organization_id,
                'color' => $color,
                'post_link' => $post_link,
            ]);
            return;
        }

        wp_send_json_error(['message' => __('Fehler beim Speichern', 'feuer-einsatzberichte')], 500);
    }

    public function feu_einsatz_sort_organizations() {
        if (!$this->verify_section_request('settings')) {
            return;
        }

        $organization_ids = isset($_POST['organization_ids']) && is_array($_POST['organization_ids'])
            ? array_map('absint', wp_unslash($_POST['organization_ids']))
            : [];

        if (empty($organization_ids)) {
            wp_send_json_error(['message' => __('Keine Organisationen uebergeben.', 'feuer-einsatzberichte')], 400);
            return;
        }

        if (!$this->db->update_organization_sort_order($organization_ids)) {
            wp_send_json_error(['message' => __('Sortierung konnte nicht gespeichert werden.', 'feuer-einsatzberichte')], 500);
            return;
        }

        wp_send_json_success(['organization_ids' => $organization_ids]);
    }

    public function feu_einsatz_delete_organization() {
        if (!$this->verify_section_request('participants')) {
            return;
        }

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;

        if ($id <= 0) {
            wp_send_json_error(['message' => __('Ungültige ID', 'feuer-einsatzberichte')], 400);
            return;
        }

        $organization = $this->db->get_organization($id);
        $result = $this->db->set_organization_archive_status($id, true);

        if ($result) {
            FEU_Einsatz_Logger::log(
                'organization_archived',
                'organization',
                $id,
                __('Organisation archiviert', 'feuer-einsatzberichte'),
                [
                    'name' => $organization ? (string) $organization->name : '',
                ]
            );

            wp_send_json_success(['message' => __('Organisation archiviert', 'feuer-einsatzberichte')]);
            return;
        }

        wp_send_json_error(['message' => __('Status konnte nicht geaendert werden', 'feuer-einsatzberichte')], 500);
    }

    public function feu_einsatz_toggle_organization_archive() {
        if (!$this->verify_section_request('participants')) {
            return;
        }

        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
        $archived = isset($_POST['archived']) ? absint(wp_unslash($_POST['archived'])) : 0;

        if ($id <= 0) {
            wp_send_json_error(['message' => __('Ungültige ID', 'feuer-einsatzberichte')], 400);
            return;
        }

        $organization = $this->db->get_organization($id);
        $result = $this->db->set_organization_archive_status($id, 1 === $archived);

        if ($result) {
            FEU_Einsatz_Logger::log(
                1 === $archived ? 'organization_archived' : 'organization_activated',
                'organization',
                $id,
                1 === $archived ? __('Organisation archiviert', 'feuer-einsatzberichte') : __('Organisation aktiviert', 'feuer-einsatzberichte'),
                [
                    'name' => $organization ? (string) $organization->name : '',
                ]
            );

            wp_send_json_success([
                'message' => 1 === $archived
                    ? __('Organisation archiviert', 'feuer-einsatzberichte')
                    : __('Organisation aktiviert', 'feuer-einsatzberichte'),
            ]);
            return;
        }

        wp_send_json_error(['message' => __('Status konnte nicht geaendert werden', 'feuer-einsatzberichte')], 500);
    }
}
