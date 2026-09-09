<?php
if (!defined('ABSPATH')) {
    exit;
}

class FEU_Einsatz_Logger {

    private static $instance = null;

    private $db;

    private $page_visit_logged = false;
    private $shutdown_error_logged = false;

    public static function boot($database) {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self($database);
        }

        return self::$instance;
    }

    public static function get_instance() {
        return self::$instance;
    }

    public static function log($action_type, $entity_type = '', $entity_id = 0, $message = '', $details = []) {
        if (!(self::$instance instanceof self)) {
            return false;
        }

        return self::$instance->write_log($action_type, $entity_type, $entity_id, $message, $details);
    }

    public static function log_runtime_error($error_code, $message = '', $details = []) {
        if (!(self::$instance instanceof self)) {
            return false;
        }

        return self::$instance->write_log(
            'runtime_error',
            'plugin_error',
            0,
            '' !== trim((string) $message)
                ? $message
                : __('Interner Plugin-Fehler', 'feuer-einsatzberichte'),
            array_merge(
                [
                    'error_code' => sanitize_key((string) $error_code),
                ],
                is_array($details) ? $details : []
            )
        );
    }

    private function __construct($database) {
        $this->db = $database;
        $this->init_hooks();
    }

    private function init_hooks() {
        if (is_admin()) {
            add_action('current_screen', [$this, 'log_admin_page_visit']);
        }

        register_shutdown_function([$this, 'capture_plugin_fatal_error']);
    }

    private function is_plugin_error_file($file_path) {
        $file_path = wp_normalize_path((string) $file_path);
        $plugin_dir = defined('FEU_EINSATZ_PLUGIN_DIR')
            ? wp_normalize_path(FEU_EINSATZ_PLUGIN_DIR)
            : '';

        if ('' === $file_path || '' === $plugin_dir) {
            return false;
        }

        return 0 === strpos($file_path, $plugin_dir);
    }

    private function get_error_type_label($error_type) {
        $labels = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];

        return isset($labels[$error_type]) ? $labels[$error_type] : 'E_UNKNOWN';
    }

    public function capture_plugin_fatal_error() {
        if ($this->shutdown_error_logged) {
            return;
        }

        $error = error_get_last();

        if (
            !is_array($error)
            || empty($error['type'])
            || !in_array((int) $error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)
            || empty($error['file'])
            || !$this->is_plugin_error_file($error['file'])
        ) {
            return;
        }

        $this->shutdown_error_logged = true;

        $this->write_log(
            'runtime_error',
            'plugin_fatal',
            0,
            __('Fataler Plugin-Fehler wurde erkannt.', 'feuer-einsatzberichte'),
            [
                'error_code' => 'fatal_shutdown',
                'type' => $this->get_error_type_label((int) $error['type']),
                'message' => isset($error['message']) ? sanitize_text_field((string) $error['message']) : '',
                'file' => isset($error['file']) ? wp_normalize_path((string) $error['file']) : '',
                'line' => isset($error['line']) ? (int) $error['line'] : 0,
            ]
        );
    }

    private function get_current_user_payload() {
        $user_id = get_current_user_id();
        $user_name = '';

        if ($user_id > 0) {
            $user = get_userdata($user_id);

            if ($user instanceof WP_User) {
                $user_name = trim((string) $user->display_name);

                if ('' === $user_name) {
                    $user_name = trim((string) $user->user_login);
                }
            }
        }

        return [
            'id' => $user_id,
            'name' => $user_name,
        ];
    }

    private function get_request_ip() {
        $remote_address = isset($_SERVER['REMOTE_ADDR'])
            ? trim((string) wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';
        $trusted_proxies = array_filter(array_map('trim', (array) apply_filters('feu_einsatz_trusted_proxy_ips', [])));
        $candidates = ['REMOTE_ADDR'];

        if ('' !== $remote_address && in_array($remote_address, $trusted_proxies, true)) {
            $candidates = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        }

        foreach ($candidates as $candidate) {
            if (empty($_SERVER[$candidate])) {
                continue;
            }

            $value = trim((string) wp_unslash($_SERVER[$candidate]));

            if ('' === $value) {
                continue;
            }

            if (false !== strpos($value, ',')) {
                $parts = array_map('trim', explode(',', $value));
                $value = (string) reset($parts);
            }

            return filter_var($value, FILTER_VALIDATE_IP) ? $value : '';
        }

        return '';
    }

    private function get_current_page_slug() {
        if (!empty($_GET['page'])) {
            return sanitize_key(wp_unslash($_GET['page']));
        }

        global $pagenow;

        return sanitize_key((string) $pagenow);
    }

    private function get_current_page_url() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';

        if ('' === $request_uri) {
            return admin_url();
        }

        $url = home_url($request_uri);
        $parts = wp_parse_url($url);
        $query = [];

        if (!empty($parts['query'])) {
            parse_str((string) $parts['query'], $query);

            foreach (array_keys($query) as $key) {
                if (preg_match('/(?:nonce|password|passwd|token|secret|signature|sig|key)/i', (string) $key)) {
                    unset($query[$key]);
                }
            }
        }

        $base_url = home_url(isset($parts['path']) ? (string) $parts['path'] : '/');

        return esc_url_raw(empty($query) ? $base_url : add_query_arg($query, $base_url));
    }

    private function get_admin_page_labels() {
        return [
            'feuer-einsatzberichte' => __('Dashboard', 'feuer-einsatzberichte'),
            'feu-einsatz-neuer-bericht' => __('Neuer Bericht', 'feuer-einsatzberichte'),
            'feu-einsatz-bericht-bearbeiten' => __('Einsatzbericht bearbeiten', 'feuer-einsatzberichte'),
            'feu-einsatz-teilnehmer' => __('Teilnehmer', 'feuer-einsatzberichte'),
            'feu-einsatz-statistiken' => __('Statistiken', 'feuer-einsatzberichte'),
            'feu-einsatz-einstellungen' => __('Einstellungen', 'feuer-einsatzberichte'),
            'feu-einsatz-archive' => __('Archive', 'feuer-einsatzberichte'),
            'feu-einsatz-logs' => __('Logs', 'feuer-einsatzberichte'),
            'edit-post' => __('Alle Berichte', 'feuer-einsatzberichte'),
            'editphp' => __('Alle Berichte', 'feuer-einsatzberichte'),
            'post' => __('Einsatzbericht bearbeiten', 'feuer-einsatzberichte'),
            'post-new' => __('Neuer Bericht', 'feuer-einsatzberichte'),
            'postnewphp' => __('Neuer Bericht', 'feuer-einsatzberichte'),
        ];
    }

    private function get_admin_page_label_from_value($value) {
        $value = trim(wp_strip_all_tags((string) $value));

        if ('' === $value) {
            return '';
        }

        $labels = $this->get_admin_page_labels();
        $candidates = [$value, sanitize_key($value)];

        if (0 === strpos($value, 'feuer-einsatzberichte_page_')) {
            $derived = (string) substr($value, strlen('feuer-einsatzberichte_page_'));
            $candidates[] = $derived;
            $candidates[] = sanitize_key($derived);
        }

        foreach ($candidates as $candidate) {
            if (isset($labels[$candidate])) {
                return $labels[$candidate];
            }
        }

        return $value;
    }

    private function get_admin_page_visit_details($page_label) {
        $details = [
            'page_label' => $page_label,
        ];

        $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;

        if ($post_id > 0 && '1' === (string) get_post_meta($post_id, '_feu_einsatz_einsatzbericht', true)) {
            $details['title'] = get_the_title($post_id);
            $details['status'] = get_post_status($post_id);
        }

        return $details;
    }

    private function get_action_labels() {
        return [
            'page_visit' => __('Seite besucht', 'feuer-einsatzberichte'),
            'report_created' => __('Einsatzbericht erstellt', 'feuer-einsatzberichte'),
            'report_updated' => __('Einsatzbericht aktualisiert', 'feuer-einsatzberichte'),
            'report_trashed' => __('Einsatzbericht verschoben', 'feuer-einsatzberichte'),
            'report_deleted' => __('Einsatzbericht geloescht', 'feuer-einsatzberichte'),
            'participant_created' => __('Teilnehmer erstellt', 'feuer-einsatzberichte'),
            'participant_updated' => __('Teilnehmer aktualisiert', 'feuer-einsatzberichte'),
            'participant_archived' => __('Teilnehmer archiviert', 'feuer-einsatzberichte'),
            'participant_activated' => __('Teilnehmer aktiviert', 'feuer-einsatzberichte'),
            'participant_deleted' => __('Teilnehmer geloescht', 'feuer-einsatzberichte'),
            'organization_created' => __('Organisation erstellt', 'feuer-einsatzberichte'),
            'organization_updated' => __('Organisation aktualisiert', 'feuer-einsatzberichte'),
            'organization_archived' => __('Organisation archiviert', 'feuer-einsatzberichte'),
            'organization_activated' => __('Organisation aktiviert', 'feuer-einsatzberichte'),
            'organization_deleted' => __('Organisation geloescht', 'feuer-einsatzberichte'),
            'archive_created' => __('Archiv erstellt', 'feuer-einsatzberichte'),
            'archive_uploaded' => __('Archiv hochgeladen', 'feuer-einsatzberichte'),
            'archive_restored' => __('Archiv wiederhergestellt', 'feuer-einsatzberichte'),
            'archive_deleted' => __('Archiv geloescht', 'feuer-einsatzberichte'),
            'archive_pruned' => __('Archiv bereinigt', 'feuer-einsatzberichte'),
            'settings_saved' => __('Einstellungen gespeichert', 'feuer-einsatzberichte'),
            'settings_cache_cleared' => __('Strassen-Cache geleert', 'feuer-einsatzberichte'),
            'data_purge_completed' => __('Daten dauerhaft gelöscht', 'feuer-einsatzberichte'),
            'update_check_refreshed' => __('Update-Prüfung aktualisiert', 'feuer-einsatzberichte'),
            'runtime_error' => __('Laufzeitfehler', 'feuer-einsatzberichte'),
        ];
    }

    public function get_action_label($action_type) {
        $labels = $this->get_action_labels();

        if (isset($labels[$action_type])) {
            return $labels[$action_type];
        }

        return ucwords(str_replace('_', ' ', (string) $action_type));
    }

    public function get_action_choices() {
        return $this->get_action_labels();
    }

    private function get_entity_labels() {
        return [
            '' => '-',
            'admin_page' => __('Admin-Seite', 'feuer-einsatzberichte'),
            'report' => __('Einsatzbericht', 'feuer-einsatzberichte'),
            'participant' => __('Teilnehmer', 'feuer-einsatzberichte'),
            'organization' => __('Organisation', 'feuer-einsatzberichte'),
            'archive' => __('Archiv', 'feuer-einsatzberichte'),
            'settings' => __('Einstellungen', 'feuer-einsatzberichte'),
            'plugin_data' => __('Plugin-Daten', 'feuer-einsatzberichte'),
            'plugin_error' => __('Plugin-Fehler', 'feuer-einsatzberichte'),
            'plugin_fatal' => __('Plugin-Fatalfehler', 'feuer-einsatzberichte'),
        ];
    }

    public function get_entity_label($entity_type) {
        $labels = $this->get_entity_labels();

        if (isset($labels[$entity_type])) {
            return $labels[$entity_type];
        }

        return ucwords(str_replace('_', ' ', (string) $entity_type));
    }

    public function get_entity_choices() {
        return $this->get_entity_labels();
    }

    public function get_log_message($log) {
        $message = isset($log->message) ? trim((string) $log->message) : '';

        if ('page_visit' !== (string) ($log->action_type ?? '')) {
            return '' !== $message ? $message : $this->get_action_label((string) ($log->action_type ?? ''));
        }

        $details = $this->decode_log_details(isset($log->details) ? $log->details : []);
        $page_label = '';

        if (!empty($details['page_label'])) {
            $page_label = $this->get_admin_page_label_from_value($details['page_label']);
        } elseif (!empty($details['page_slug'])) {
            $page_label = $this->get_admin_page_label_from_value($details['page_slug']);
        } elseif (!empty($log->page_slug)) {
            $page_label = $this->get_admin_page_label_from_value($log->page_slug);
        }

        if ('' !== $page_label) {
            return sprintf(
                /* translators: %s: page label */
                __('Admin-Seite besucht: %s', 'feuer-einsatzberichte'),
                $page_label
            );
        }

        return $message;
    }

    public function get_log_area_label($log) {
        $details = $this->decode_log_details(isset($log->details) ? $log->details : []);

        foreach (['page_label', 'page_slug'] as $key) {
            if (!empty($details[$key])) {
                $label = $this->get_admin_page_label_from_value((string) $details[$key]);

                if ('' !== $label) {
                    return $label;
                }
            }
        }

        if (!empty($log->page_slug)) {
            $label = $this->get_admin_page_label_from_value((string) $log->page_slug);

            if ('' !== $label) {
                return $label;
            }
        }

        return $this->get_entity_label((string) ($log->entity_type ?? ''));
    }

    public function get_log_subject_label($log) {
        $details = $this->decode_log_details(isset($log->details) ? $log->details : []);

        foreach (['title', 'name', 'filename', 'archive_key', 'error_code'] as $key) {
            if (!empty($details[$key])) {
                return sanitize_text_field((string) $details[$key]);
            }
        }

        if (!empty($log->entity_id)) {
            return '#' . (int) $log->entity_id;
        }

        return '';
    }

    private function should_log_admin_screen($screen) {
        if ($this->page_visit_logged || !is_admin() || wp_doing_ajax()) {
            return false;
        }

        if (!$screen || !is_user_logged_in()) {
            return false;
        }

        $screen_id = isset($screen->id) ? (string) $screen->id : '';
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $feu_einsatz_filter = isset($_GET['feu_einsatz_filter']) ? sanitize_text_field(wp_unslash($_GET['feu_einsatz_filter'])) : '';
        $is_plugin_page = '' !== $page && 0 === strpos($page, 'feu-einsatz-');
        $is_plugin_menu = false !== strpos($screen_id, 'feuer-einsatzberichte');
        $is_report_list = '1' === $feu_einsatz_filter;
        $is_report_editor = false;

        if (in_array($screen_id, ['post', 'edit-post'], true)) {
            $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;

            if ($post_id > 0 && '1' === get_post_meta($post_id, '_feu_einsatz_einsatzbericht', true)) {
                $is_report_editor = true;
            }

            if (isset($_GET['feu_einsatz_einsatzbericht']) && '1' === sanitize_text_field(wp_unslash($_GET['feu_einsatz_einsatzbericht']))) {
                $is_report_editor = true;
            }
        }

        return $is_plugin_page || $is_plugin_menu || $is_report_list || $is_report_editor;
    }

    private function get_admin_screen_label($screen) {
        if (!$screen) {
            return __('Plugin-Seite', 'feuer-einsatzberichte');
        }

        $page_slug = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $screen_id = isset($screen->id) ? (string) $screen->id : '';
        $screen_base = isset($screen->base) ? (string) $screen->base : '';
        $feu_einsatz_filter = isset($_GET['feu_einsatz_filter']) ? sanitize_text_field(wp_unslash($_GET['feu_einsatz_filter'])) : '';
        $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
        $is_report_editor = false;

        if ($post_id > 0 && '1' === (string) get_post_meta($post_id, '_feu_einsatz_einsatzbericht', true)) {
            $is_report_editor = true;
        }

        if (isset($_GET['feu_einsatz_einsatzbericht']) && '1' === sanitize_text_field(wp_unslash($_GET['feu_einsatz_einsatzbericht']))) {
            $is_report_editor = true;
        }

        if ('1' === $feu_einsatz_filter) {
            return __('Alle Berichte', 'feuer-einsatzberichte');
        }

        if ($is_report_editor) {
            return $post_id > 0
                ? __('Einsatzbericht bearbeiten', 'feuer-einsatzberichte')
                : __('Neuer Bericht', 'feuer-einsatzberichte');
        }

        foreach ([$page_slug, $screen_id, $screen_base] as $candidate) {
            $label = $this->get_admin_page_label_from_value($candidate);

            if ('' !== $label && $label !== $candidate) {
                return $label;
            }
        }

        if (!empty($screen->title)) {
            return $this->get_admin_page_label_from_value((string) $screen->title);
        }

        return __('Plugin-Seite', 'feuer-einsatzberichte');
    }

    public function log_admin_page_visit($screen) {
        if (!$this->should_log_admin_screen($screen)) {
            return;
        }

        $this->page_visit_logged = true;

        $page_label = $this->get_admin_screen_label($screen);

        $this->write_log(
            'page_visit',
            'admin_page',
            0,
            sprintf(
                /* translators: %s: page label */
                __('Admin-Seite besucht: %s', 'feuer-einsatzberichte'),
                $page_label
            ),
            $this->get_admin_page_visit_details($page_label)
        );
    }

    private function decode_log_details($details) {
        if (is_array($details)) {
            return $details;
        }

        if (!is_string($details) || '' === trim($details)) {
            return [];
        }

        $decoded = json_decode($details, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function is_list_array($value) {
        if (!is_array($value)) {
            return false;
        }

        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function get_log_detail_label($key) {
        $labels = [
            'title' => __('Titel', 'feuer-einsatzberichte'),
            'status' => __('Status', 'feuer-einsatzberichte'),
            'street' => __('Straße', 'feuer-einsatzberichte'),
            'street_name' => __('Straße', 'feuer-einsatzberichte'),
            'house_number' => __('Hausnummer', 'feuer-einsatzberichte'),
            'plz' => __('PLZ', 'feuer-einsatzberichte'),
            'postcode' => __('PLZ', 'feuer-einsatzberichte'),
            'city' => __('Stadt', 'feuer-einsatzberichte'),
            'date' => __('Datum', 'feuer-einsatzberichte'),
            'time' => __('Uhrzeit', 'feuer-einsatzberichte'),
            'comments_enabled' => __('Kommentare', 'feuer-einsatzberichte'),
            'default_comments_enabled' => __('Standard-Kommentare', 'feuer-einsatzberichte'),
            'category_labels' => __('Einsatzstichworte', 'feuer-einsatzberichte'),
            'categories' => __('Einsatzstichworte', 'feuer-einsatzberichte'),
            'selected_categories' => __('Einsatzstichworte', 'feuer-einsatzberichte'),
            'sections' => __('Gelöschte Datenbereiche', 'feuer-einsatzberichte'),
            'performed_by_user_id' => __('Ausgeführt von Benutzer-ID', 'feuer-einsatzberichte'),
            'performed_by_user_login' => __('Ausgeführt von Benutzerkonto', 'feuer-einsatzberichte'),
            'performed_by_display_name' => __('Ausgeführt von', 'feuer-einsatzberichte'),
            'name' => __('Name', 'feuer-einsatzberichte'),
            'color' => __('Farbe', 'feuer-einsatzberichte'),
            'post_link' => __('Beitragslink', 'feuer-einsatzberichte'),
            'archived' => __('Archiviert', 'feuer-einsatzberichte'),
            'deleted' => __('Gelöscht', 'feuer-einsatzberichte'),
            'gallery_count' => __('Galeriebilder', 'feuer-einsatzberichte'),
            'default_functions' => __('Standardfunktionen', 'feuer-einsatzberichte'),
            'default_participant_function' => __('Standardfunktion ohne Vorgabe', 'feuer-einsatzberichte'),
            'changed_count' => __('Anzahl Änderungen', 'feuer-einsatzberichte'),
            'screen_id' => __('Screen-ID', 'feuer-einsatzberichte'),
            'screen_base' => __('Screen-Basis', 'feuer-einsatzberichte'),
            'page_label' => __('Seite', 'feuer-einsatzberichte'),
            'page_slug' => __('Seiten-Slug', 'feuer-einsatzberichte'),
            'archive_key' => __('Archiv-Schlüssel', 'feuer-einsatzberichte'),
            'filename' => __('Dateiname', 'feuer-einsatzberichte'),
            'summary.posts' => __('Beiträge', 'feuer-einsatzberichte'),
            'summary.postmeta' => __('Beitrags-Metadaten', 'feuer-einsatzberichte'),
            'summary.comments' => __('Kommentare', 'feuer-einsatzberichte'),
            'summary.terms' => __('Einsatzstichworte', 'feuer-einsatzberichte'),
            'summary.attachments' => __('Anhänge', 'feuer-einsatzberichte'),
            'summary.participants' => __('Teilnehmer', 'feuer-einsatzberichte'),
            'summary.stats' => __('Statistik', 'feuer-einsatzberichte'),
            'summary.statistics_cache' => __('Statistik-Cache', 'feuer-einsatzberichte'),
            'summary.organizations' => __('Organisationen', 'feuer-einsatzberichte'),
            'summary.logs' => __('Logs', 'feuer-einsatzberichte'),
            'error_code' => __('Fehlercode', 'feuer-einsatzberichte'),
            'type' => __('Fehlertyp', 'feuer-einsatzberichte'),
            'file' => __('Datei', 'feuer-einsatzberichte'),
            'line' => __('Zeile', 'feuer-einsatzberichte'),
            'message' => __('Technische Meldung', 'feuer-einsatzberichte'),
            'remote_version' => __('Remote-Version', 'feuer-einsatzberichte'),
            'installed_version' => __('Installierte Version', 'feuer-einsatzberichte'),
            'manifest_url' => __('Manifest-URL', 'feuer-einsatzberichte'),
            'download_url' => __('Download-URL', 'feuer-einsatzberichte'),
            'release_version_path' => __('Release-Ordner Version', 'feuer-einsatzberichte'),
            'release_latest_path' => __('Release-Ordner Latest', 'feuer-einsatzberichte'),
        ];

        if (isset($labels[$key])) {
            return $labels[$key];
        }

        if (false !== strpos($key, '.')) {
            $key = (string) substr($key, (int) strrpos($key, '.') + 1);
        }

        return ucwords(str_replace('_', ' ', $key));
    }

    private function format_log_detail_scalar($key, $value) {
        if (is_bool($value)) {
            return $value ? __('Ja', 'feuer-einsatzberichte') : __('Nein', 'feuer-einsatzberichte');
        }

        if ('page_label' === $key) {
            return $this->get_admin_page_label_from_value($value);
        }

        if (is_numeric($value) && in_array($key, ['line', 'gallery_count', 'changed_count'], true)) {
            return (string) $value;
        }

        if ('status' === $key) {
            $status_labels = [
                'publish' => __('Veröffentlicht', 'feuer-einsatzberichte'),
                'future' => __('Geplant', 'feuer-einsatzberichte'),
                'draft' => __('Entwurf', 'feuer-einsatzberichte'),
                'trash' => __('Papierkorb', 'feuer-einsatzberichte'),
            ];

            return isset($status_labels[$value]) ? $status_labels[$value] : (string) $value;
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function format_log_detail_value($key, $value) {
        if (!is_array($value)) {
            return $this->format_log_detail_scalar($key, $value);
        }

        if ($this->is_list_array($value)) {
            $formatted_items = [];

            foreach ($value as $item) {
                if (is_numeric($item) && in_array($key, ['categories', 'selected_categories'], true)) {
                    $term = get_term((int) $item, 'category');
                    $formatted_items[] = $term && !is_wp_error($term) ? $term->name : (string) $item;
                    continue;
                }

                $formatted_items[] = $this->format_log_detail_scalar($key, $item);
            }

            $formatted_items = array_values(array_filter(array_map('trim', $formatted_items), static function ($value) {
                return '' !== $value;
            }));

            return implode(', ', $formatted_items);
        }

        return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function flatten_log_details($details, $prefix = '') {
        $items = [];
        $skip_keys = ['changed_fields', 'changed_settings', 'screen_id', 'screen_base', 'page_slug', 'page_url', 'ip_address'];

        foreach ((array) $details as $key => $value) {
            if (in_array($key, $skip_keys, true)) {
                continue;
            }

            $path = '' !== $prefix ? $prefix . '.' . $key : $key;

            if (is_array($value) && !$this->is_list_array($value)) {
                $items = array_merge($items, $this->flatten_log_details($value, $path));
                continue;
            }

            $items[] = [
                'label' => $this->get_log_detail_label($path),
                'value' => $this->format_log_detail_value($key, $value),
            ];
        }

        return array_values(array_filter($items, static function ($item) {
            return !empty($item['value']);
        }));
    }

    private function extract_log_changes($details) {
        $changes = [];

        foreach (['changed_fields', 'changed_settings'] as $change_key) {
            if (empty($details[$change_key]) || !is_array($details[$change_key])) {
                continue;
            }

            foreach ($details[$change_key] as $field_key => $change) {
                if (!is_array($change)) {
                    continue;
                }

                $label = !empty($change['label'])
                    ? sanitize_text_field((string) $change['label'])
                    : $this->get_log_detail_label((string) $field_key);
                $before = array_key_exists('before', $change) ? $this->format_log_detail_value((string) $field_key, $change['before']) : '';
                $after = array_key_exists('after', $change) ? $this->format_log_detail_value((string) $field_key, $change['after']) : '';

                if ($before === $after) {
                    continue;
                }

                $changes[] = [
                    'label' => $label,
                    'before' => $before,
                    'after' => $after,
                ];
            }
        }

        return $changes;
    }

    public function get_log_detail_sections($log) {
        $details = $this->decode_log_details(isset($log->details) ? $log->details : []);

        return [
            'details' => $details,
            'summary' => $this->flatten_log_details($details),
            'changes' => $this->extract_log_changes($details),
        ];
    }

    public function write_log($action_type, $entity_type = '', $entity_id = 0, $message = '', $details = []) {
        if (!$this->db || !method_exists($this->db, 'add_log')) {
            return false;
        }

        if (
            false === get_transient('feu_einsatz_log_retention_cleanup')
            && method_exists($this->db, 'delete_logs_older_than')
        ) {
            $retention_days = max(30, (int) apply_filters('feu_einsatz_log_retention_days', 180));
            $this->db->delete_logs_older_than($retention_days);
            set_transient('feu_einsatz_log_retention_cleanup', 1, DAY_IN_SECONDS);
        }

        $user = $this->get_current_user_payload();

        return $this->db->add_log([
            'user_id' => $user['id'],
            'user_name' => $user['name'],
            'action_type' => $action_type,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'message' => $message,
            'details' => $details,
            'page_slug' => $this->get_current_page_slug(),
            'page_url' => $this->get_current_page_url(),
            'ip_address' => $this->get_request_ip(),
        ]);
    }

    public function get_logs($args = []) {
        if (!$this->db || !method_exists($this->db, 'get_logs')) {
            return [];
        }

        return $this->db->get_logs($args);
    }

    public function count_logs($args = []) {
        if (!$this->db || !method_exists($this->db, 'count_logs')) {
            return 0;
        }

        return (int) $this->db->count_logs($args);
    }
}
