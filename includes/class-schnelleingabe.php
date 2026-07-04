<?php
if (!defined('ABSPATH')) {
    exit;
}

class FEU_Einsatz_Schnelleingabe {

    private const FLASH_TRANSIENT_PREFIX = 'feu_einsatz_schnelleingabe_flash_';

    private FEU_Einsatz_Database $db;

    public function __construct(FEU_Einsatz_Database $db) {
        $this->db = $db;
    }

    public function enqueue_assets(string $hook): void {
        if ('index.php' !== $hook) {
            return;
        }

        if (!FEU_Einsatz_Admin::current_user_can_access_plugin_section('quick_entry') || !current_user_can('edit_posts')) {
            return;
        }

        $style_path = FEU_EINSATZ_PLUGIN_DIR . 'assets/admin/css/schnelleingabe.css';
        $script_path = FEU_EINSATZ_PLUGIN_DIR . 'assets/admin/js/schnelleingabe.js';

        wp_enqueue_style(
            'feu-einsatz-schnelleingabe',
            FEU_EINSATZ_PLUGIN_URL . 'assets/admin/css/schnelleingabe.css',
            [],
            file_exists($style_path) ? (string) filemtime($style_path) : FEU_EINSATZ_VERSION
        );

        wp_enqueue_script(
            'feu-einsatz-schnelleingabe',
            FEU_EINSATZ_PLUGIN_URL . 'assets/admin/js/schnelleingabe.js',
            [],
            file_exists($script_path) ? (string) filemtime($script_path) : FEU_EINSATZ_VERSION,
            true
        );
    }

    public function render(): void {
        if (!FEU_Einsatz_Admin::current_user_can_access_plugin_section('quick_entry') || !current_user_can('edit_posts')) {
            wp_die(esc_html__('Keine Berechtigung.', 'feuer-einsatzberichte'));
        }

        $context = $this->get_form_context('full');

        extract($context, EXTR_SKIP);
        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/schnelleingabe.php';
    }

    public function render_dashboard_widget(): void {
        if (!FEU_Einsatz_Admin::current_user_can_access_plugin_section('quick_entry') || !current_user_can('edit_posts')) {
            echo '<p>' . esc_html__('Keine Berechtigung.', 'feuer-einsatzberichte') . '</p>';
            return;
        }

        $context = $this->get_form_context('dashboard');

        extract($context, EXTR_SKIP);
        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/schnelleingabe-widget.php';
    }

    public function handle_post(): void {
        if (!FEU_Einsatz_Admin::current_user_can_access_plugin_section('quick_entry') || !current_user_can('edit_posts')) {
            wp_die(esc_html__('Keine Berechtigung.', 'feuer-einsatzberichte'));
        }

        check_admin_referer('feu_einsatz_schnelleingabe', 'feu_einsatz_schnelleingabe_nonce');

        $origin = $this->normalize_origin(isset($_POST['feu_einsatz_origin']) ? wp_unslash($_POST['feu_einsatz_origin']) : '');
        $can_publish = current_user_can('publish_posts');

        $strasse = isset($_POST['feu_einsatz_strasse'])
            ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_strasse']))
            : '';
        $hausnummer = isset($_POST['feu_einsatz_hausnummer'])
            ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_hausnummer']))
            : '';
        $plz = isset($_POST['feu_einsatz_plz'])
            ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_plz']))
            : '';
        $stadt = isset($_POST['feu_einsatz_stadt'])
            ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_stadt']))
            : '';
        $datum_raw = isset($_POST['feu_einsatz_datum'])
            ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_datum']))
            : '';
        $uhrzeit_raw = isset($_POST['feu_einsatz_uhrzeit'])
            ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_uhrzeit']))
            : '';
        $post_title = isset($_POST['post_title'])
            ? sanitize_text_field(wp_unslash($_POST['post_title']))
            : '';

        $status = isset($_POST['feu_einsatz_report_status']) && 'publish' === sanitize_key(wp_unslash($_POST['feu_einsatz_report_status']))
            ? 'publish'
            : 'draft';

        if ('publish' === $status && !$can_publish) {
            $status = 'draft';
        }

        $submitted_categories = isset($_POST['post_category']) && is_array($_POST['post_category'])
            ? array_map('absint', wp_unslash($_POST['post_category']))
            : [];
        $teilnehmer_ids = isset($_POST['feu_einsatz_teilnehmer_ids']) && is_array($_POST['feu_einsatz_teilnehmer_ids'])
            ? array_map('absint', wp_unslash($_POST['feu_einsatz_teilnehmer_ids']))
            : [];
        $teilnehmer_funktionen = isset($_POST['feu_einsatz_teilnehmer_funktion']) && is_array($_POST['feu_einsatz_teilnehmer_funktion'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['feu_einsatz_teilnehmer_funktion']))
            : [];
        $organisationen = isset($_POST['feu_einsatz_organisationen']) && is_array($_POST['feu_einsatz_organisationen'])
            ? array_map('absint', wp_unslash($_POST['feu_einsatz_organisationen']))
            : [];
        $comments_enabled = isset($_POST['feu_einsatz_comments_enabled_present'])
            ? (isset($_POST['feu_einsatz_comments_enabled']) ? 1 : 0)
            : (int) get_option('feu_einsatz_default_comments_enabled', 0);

        $form_values = $this->normalize_form_values([
            'date' => $datum_raw,
            'time' => $uhrzeit_raw,
            'street' => $strasse,
            'house_number' => $hausnummer,
            'postal_code' => $plz,
            'city' => $stadt,
            'title' => $post_title,
            'categories' => $submitted_categories,
            'participant_ids' => $teilnehmer_ids,
            'participant_functions' => $teilnehmer_funktionen,
            'organization_ids' => $organisationen,
            'comments_enabled' => $comments_enabled,
            'status' => $status,
        ]);

        $errors = $this->validate_form_values($form_values);

        if (!empty($errors)) {
            $this->set_flash($origin, [
                'errors' => $errors,
                'form_values' => $form_values,
            ]);
            wp_safe_redirect($this->get_return_url($origin));
            exit;
        }

        if ('' === $post_title) {
            $post_title = $this->build_default_title($form_values['categories'], $form_values['street'], $form_values['date']);
        }

        $event_dt = DateTimeImmutable::createFromFormat(
            'd.m.Y H:i',
            $form_values['date'] . ' ' . $form_values['time'],
            wp_timezone()
        );

        $postarr = [
            'post_type' => 'post',
            'post_status' => $status,
            'post_title' => $post_title,
            'post_content' => '',
            'post_author' => get_current_user_id(),
            'post_category' => $form_values['categories'],
            'comment_status' => $comments_enabled ? 'open' : 'closed',
        ];

        if ($event_dt instanceof DateTimeImmutable) {
            $event_post_date = $event_dt->format('Y-m-d H:i:s');
            $postarr['post_date'] = $event_post_date;
            $postarr['post_date_gmt'] = get_gmt_from_date($event_post_date);
        }

        $post_id = wp_insert_post($postarr, true);

        if (is_wp_error($post_id)) {
            $this->set_flash($origin, [
                'errors' => [$post_id->get_error_message()],
                'form_values' => $form_values,
            ]);
            wp_safe_redirect($this->get_return_url($origin));
            exit;
        }

        update_post_meta($post_id, '_feu_einsatz_einsatzbericht', '1');
        update_post_meta($post_id, '_feu_einsatz_strasse', $form_values['street']);
        update_post_meta($post_id, '_feu_einsatz_hausnummer', $form_values['house_number']);
        update_post_meta($post_id, '_feu_einsatz_plz', $form_values['postal_code']);
        update_post_meta($post_id, '_feu_einsatz_stadt', $form_values['city']);
        update_post_meta($post_id, '_feu_einsatz_datum', $form_values['date']);
        update_post_meta($post_id, '_feu_einsatz_uhrzeit', $form_values['time']);
        update_post_meta($post_id, '_feu_einsatz_comments_enabled', (string) $comments_enabled);

        $gallery_ids = $this->collect_gallery_ids_from_request();
        $uploaded_gallery_ids = $this->handle_uploaded_gallery($post_id);
        $gallery_ids = array_values(array_unique(array_filter(array_merge($gallery_ids, $uploaded_gallery_ids))));

        if (!empty($gallery_ids)) {
            update_post_meta($post_id, '_feu_einsatz_gallery', $gallery_ids);

            if (class_exists('FEU_Einsatz_Image_Protection')) {
                FEU_Einsatz_Image_Protection::prime_attachment_cache($gallery_ids);
            }
        }

        $this->save_participants($post_id, $form_values['participant_ids'], $form_values['participant_functions']);
        $this->save_organizations($post_id, $form_values['organization_ids']);

        wp_schedule_single_event(time() + 5, 'feu_einsatz_background_geocode', [$post_id]);

        if (function_exists('spawn_cron')) {
            spawn_cron(time());
        }

        FEU_Einsatz_Logger::log(
            'report_created',
            'report',
            $post_id,
            __('Einsatzbericht per Schnelleingabe erstellt.', 'feuer-einsatzberichte'),
            [
                'status' => $status,
                'title' => $post_title,
                'street' => $form_values['street'],
                'postal_code' => $form_values['postal_code'],
                'city' => $form_values['city'],
                'date' => $form_values['date'],
                'time' => $form_values['time'],
                'categories' => $form_values['categories'],
                'participants' => $form_values['participant_ids'],
                'organizations' => $form_values['organization_ids'],
                'gallery_ids' => $gallery_ids,
                'source' => 'schnelleingabe',
            ]
        );

        wp_safe_redirect($this->get_return_url($origin, [
            'feu_einsatz_created' => $post_id,
        ]));
        exit;
    }

    private function get_form_context(string $mode): array {
        $mode = 'dashboard' === $mode ? 'dashboard' : 'full';
        $flash = $this->consume_flash($mode);
        $default_city = $this->get_default_city();
        $default_comments_enabled = (int) get_option('feu_einsatz_default_comments_enabled', 0);
        $now = current_datetime();

        $form_values = $this->normalize_form_values([
            'date' => $now->format('d.m.Y'),
            'time' => $now->format('H:i'),
            'street' => '',
            'house_number' => '',
            'postal_code' => '',
            'city' => $default_city,
            'title' => '',
            'categories' => [],
            'participant_ids' => [],
            'participant_functions' => [],
            'organization_ids' => [],
            'comments_enabled' => $default_comments_enabled,
            'status' => current_user_can('publish_posts') ? 'publish' : 'draft',
        ]);

        if (!empty($flash['form_values']) && is_array($flash['form_values'])) {
            $form_values = $this->normalize_form_values(array_merge($form_values, $flash['form_values']));
        }

        $success_post_id = isset($_GET['feu_einsatz_created']) ? absint(wp_unslash($_GET['feu_einsatz_created'])) : 0;

        return [
            'mode' => $mode,
            'categories' => $this->get_available_categories(),
            'participants' => $this->db->get_participants([
                'include_archived' => false,
                'include_deleted' => false,
                'order_by_usage' => true,
            ]),
            'functions' => (array) get_option('feu_einsatz_functions', FEU_Einsatz_Installer::get_default_functions()),
            'organizations' => $this->db->get_organizations(['include_archived' => false]),
            'default_city' => $default_city,
            'form_values' => $form_values,
            'flash_errors' => isset($flash['errors']) && is_array($flash['errors']) ? $flash['errors'] : [],
            'success_post_id' => $success_post_id,
            'success_post_url' => $success_post_id ? get_permalink($success_post_id) : '',
            'success_edit_url' => $success_post_id ? admin_url('admin.php?page=feu-einsatz-bericht-bearbeiten&post_id=' . $success_post_id) : '',
            'can_publish' => current_user_can('publish_posts'),
        ];
    }

    private function normalize_origin($origin): string {
        return 'dashboard' === sanitize_key((string) $origin) ? 'dashboard' : 'full';
    }

    private function get_return_url(string $origin, array $query_args = []): string {
        $origin = $this->normalize_origin($origin);
        $base_url = 'dashboard' === $origin
            ? admin_url('index.php')
            : admin_url('admin.php?page=feu-einsatz-schnelleingabe');

        return !empty($query_args) ? add_query_arg($query_args, $base_url) : $base_url;
    }

    private function get_flash_key(string $origin): string {
        return self::FLASH_TRANSIENT_PREFIX . get_current_user_id() . '_' . $this->normalize_origin($origin);
    }

    private function set_flash(string $origin, array $payload): void {
        set_transient($this->get_flash_key($origin), $payload, 10 * MINUTE_IN_SECONDS);
    }

    private function consume_flash(string $origin): array {
        $key = $this->get_flash_key($origin);
        $payload = get_transient($key);
        delete_transient($key);

        return is_array($payload) ? $payload : [];
    }

    private function normalize_form_values(array $values): array {
        return [
            'date' => sanitize_text_field((string) ($values['date'] ?? '')),
            'time' => sanitize_text_field((string) ($values['time'] ?? '')),
            'street' => sanitize_text_field((string) ($values['street'] ?? '')),
            'house_number' => sanitize_text_field((string) ($values['house_number'] ?? '')),
            'postal_code' => sanitize_text_field((string) ($values['postal_code'] ?? '')),
            'city' => sanitize_text_field((string) ($values['city'] ?? '')),
            'title' => sanitize_text_field((string) ($values['title'] ?? '')),
            'categories' => array_values(array_unique(array_filter(array_map('absint', (array) ($values['categories'] ?? []))))),
            'participant_ids' => array_values(array_unique(array_filter(array_map('absint', (array) ($values['participant_ids'] ?? []))))),
            'participant_functions' => is_array($values['participant_functions'] ?? null)
                ? array_map('sanitize_text_field', $values['participant_functions'])
                : [],
            'organization_ids' => array_values(array_unique(array_filter(array_map('absint', (array) ($values['organization_ids'] ?? []))))),
            'comments_enabled' => !empty($values['comments_enabled']) ? 1 : 0,
            'status' => 'publish' === sanitize_key((string) ($values['status'] ?? 'draft')) ? 'publish' : 'draft',
        ];
    }

    private function validate_form_values(array &$form_values): array {
        $errors = [];

        $form_values['street'] = trim($form_values['street']);
        $form_values['postal_code'] = trim($form_values['postal_code']);
        $form_values['city'] = trim($form_values['city']);

        if ('' === $form_values['street']) {
            $errors[] = __('Strasse ist ein Pflichtfeld.', 'feuer-einsatzberichte');
        }

        if (!preg_match('/^\d{5}$/', $form_values['postal_code'])) {
            $errors[] = __('PLZ ist ein Pflichtfeld und muss aus fuenf Ziffern bestehen.', 'feuer-einsatzberichte');
        }

        if ('' === $form_values['city']) {
            $errors[] = __('Stadt ist ein Pflichtfeld.', 'feuer-einsatzberichte');
        }

        if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $form_values['date'])) {
            $errors[] = __('Datum fehlt oder hat nicht das Format TT.MM.JJJJ.', 'feuer-einsatzberichte');
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $form_values['time'])) {
            $errors[] = __('Uhrzeit fehlt oder hat nicht das Format HH:MM.', 'feuer-einsatzberichte');
        }

        $allowed_category_ids = wp_list_pluck($this->get_available_categories(), 'term_id');
        $form_values['categories'] = empty($allowed_category_ids)
            ? $form_values['categories']
            : array_values(array_intersect($form_values['categories'], array_map('absint', $allowed_category_ids)));

        if (empty($form_values['categories'])) {
            $errors[] = __('Mindestens eine Einsatzart muss gewaehlt werden.', 'feuer-einsatzberichte');
        }

        return $errors;
    }

    private function build_default_title(array $category_ids, string $street, string $date): string {
        $title_parts = [];
        $street = trim($street);

        if (!empty($category_ids)) {
            $first_category = get_term((int) $category_ids[0], 'category');

            if ($first_category && !is_wp_error($first_category)) {
                $title_parts[] = $first_category->name;
            }
        }

        if ('' !== $street) {
            $title_parts[] = $street;
        }

        if (!empty($title_parts)) {
            return implode(' - ', $title_parts);
        }

        return sprintf(
            /* translators: %s: event date */
            __('Einsatz %s', 'feuer-einsatzberichte'),
            $date
        );
    }

    private function save_participants(int $post_id, array $participant_ids, array $participant_functions): void {
        if (empty($participant_ids)) {
            delete_post_meta($post_id, '_feu_einsatz_teilnehmer');
            $this->db->save_participant_stats($post_id, []);
            return;
        }

        $default_function = FEU_Einsatz_Installer::get_default_participant_function();
        $meta = [];

        foreach ($participant_ids as $participant_id) {
            $participant_id = absint($participant_id);

            if (!$participant_id) {
                continue;
            }

            $meta[] = [
                'id' => $participant_id,
                'funktion' => FEU_Einsatz_Installer::resolve_assignment_function(
                    $participant_functions[$participant_id] ?? $default_function
                ),
            ];
        }

        update_post_meta($post_id, '_feu_einsatz_teilnehmer', $meta);
        $this->db->save_participant_stats($post_id, $meta);
    }

    private function save_organizations(int $post_id, array $organization_ids): void {
        $organization_ids = array_values(array_unique(array_filter(array_map('absint', $organization_ids))));
        $this->db->save_einsatz_organizations($post_id, $organization_ids);
    }

    private function collect_gallery_ids_from_request(): array {
        if (!isset($_POST['feu_einsatz_gallery_ids'])) {
            return [];
        }

        $raw_value = sanitize_text_field(wp_unslash($_POST['feu_einsatz_gallery_ids']));
        if ('' === $raw_value) {
            return [];
        }

        $attachment_ids = array_values(array_unique(array_filter(array_map('absint', explode(',', $raw_value)))));

        return array_values(array_filter($attachment_ids, static function ($attachment_id) {
            return 'attachment' === get_post_type($attachment_id)
                && 0 === strpos((string) get_post_mime_type($attachment_id), 'image/')
                && current_user_can('edit_post', $attachment_id);
        }));
    }

    private function handle_uploaded_gallery(int $post_id): array {
        if (
            empty($_FILES['feu_einsatz_gallery_upload'])
            || !isset($_FILES['feu_einsatz_gallery_upload']['name'])
            || !is_array($_FILES['feu_einsatz_gallery_upload']['name'])
        ) {
            return [];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $uploaded_ids = [];
        $files = $_FILES['feu_einsatz_gallery_upload'];

        foreach ((array) $files['name'] as $index => $name) {
            $error_code = isset($files['error'][$index]) ? (int) $files['error'][$index] : UPLOAD_ERR_NO_FILE;

            if (UPLOAD_ERR_NO_FILE === $error_code || '' === trim((string) $name)) {
                continue;
            }

            $_FILES['feu_einsatz_gallery_upload_single'] = [
                'name' => $files['name'][$index] ?? '',
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $error_code,
                'size' => $files['size'][$index] ?? 0,
            ];

            $attachment_id = media_handle_upload('feu_einsatz_gallery_upload_single', $post_id);

            if (!is_wp_error($attachment_id)) {
                $uploaded_ids[] = (int) $attachment_id;
            } else {
                FEU_Einsatz_Logger::log(
                    'quick_entry_photo_upload_failed',
                    'report',
                    $post_id,
                    __('Ein Foto aus der Schnelleingabe konnte nicht hochgeladen werden.', 'feuer-einsatzberichte'),
                    [
                        'file_name' => (string) $name,
                        'error' => $attachment_id->get_error_message(),
                    ]
                );
            }
        }

        unset($_FILES['feu_einsatz_gallery_upload_single']);

        return $uploaded_ids;
    }

    private function get_available_categories(): array {
        $configured_ids = array_values(
            array_unique(
                array_filter(
                    array_map('absint', (array) get_option('feu_einsatz_categories', []))
                )
            )
        );

        if (empty($configured_ids)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => 'category',
            'include' => $configured_ids,
            'hide_empty' => false,
            'orderby' => 'include',
        ]);

        return is_array($terms) ? array_filter($terms, static function ($term) {
            return !is_wp_error($term);
        }) : [];
    }

    private function get_default_city(): string {
        return (string) get_option('feu_einsatz_default_city', 'Hamburg');
    }
}
