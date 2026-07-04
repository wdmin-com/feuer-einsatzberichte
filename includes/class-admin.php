<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once FEU_EINSATZ_PLUGIN_DIR . 'includes/class-participant-ranking.php';
require_once FEU_EINSATZ_PLUGIN_DIR . 'includes/class-report-logger.php';
require_once FEU_EINSATZ_PLUGIN_DIR . 'includes/class-report-share.php';
require_once FEU_EINSATZ_PLUGIN_DIR . 'includes/class-schnelleingabe.php';

class FEU_Einsatz_Admin {

    // Ranking-Konstanten: Rückwärtskompatibilität (Aufrufer nutzen FEU_Einsatz_Admin::...)
    const PARTICIPANT_RANKING_PIN_OPTION  = FEU_Einsatz_Participant_Ranking::PIN_OPTION;
    const PARTICIPANT_RANKING_UNLOCK_META = FEU_Einsatz_Participant_Ranking::UNLOCK_META;
    const PARTICIPANT_RANKING_UNLOCK_TTL  = FEU_Einsatz_Participant_Ranking::UNLOCK_TTL;
    const GENERATED_MAP_THUMBNAIL_META = '_feu_einsatz_generated_map_thumbnail_id';
    const GENERATED_MAP_ATTACHMENT_META = '_feu_einsatz_generated_map_preview';
    const GENERATED_MAP_PREVIEW_URL_META = '_feu_einsatz_generated_map_preview_url';
    const GENERATED_MAP_PREVIEW_FILE_META = '_feu_einsatz_generated_map_preview_file';
    const GENERATED_MAP_SIGNATURE_META = '_feu_einsatz_generated_map_signature';
    const GENERATED_MAP_QUEUE_META = '_feu_einsatz_generated_map_queue';
    const GENERATED_MAP_STATUS_META = '_feu_einsatz_generated_map_status';
    const GENERATED_MAP_PUBLISH_HOLD_META = '_feu_einsatz_generated_map_publish_hold';
    const GENERATED_MAP_BACKGROUND_HOOK = 'feu_einsatz_generate_map_preview_background';
    const GENERATED_MAP_PROCESS_LOCK_PREFIX = 'feu_einsatz_generate_map_lock_';
    const BACKGROUND_GEOCODE_HOOK = 'feu_einsatz_background_geocode';
    const ROLE_ACCESS_OPTION = 'feu_einsatz_role_access';
    const NOMINATIM_SEARCH_URL = 'https://nominatim.openstreetmap.org/search';
    const OVERPASS_API_URL = 'https://overpass-api.de/api/interpreter';

    private $db;
    private $generated_map_upload_subdir = '';

    /** @var FEU_Einsatz_Participant_Ranking */
    private $ranking;

    /** @var FEU_Einsatz_Report_Logger */
    private $report_logger;

    /** @var FEU_Einsatz_Report_Share */
    private $report_share;

    /** @var FEU_Einsatz_Schnelleingabe */
    private $schnelleingabe;

    public function __construct($database) {
        $this->db           = $database;
        $this->ranking      = new FEU_Einsatz_Participant_Ranking();
        $this->report_logger = new FEU_Einsatz_Report_Logger();
        $this->report_share  = new FEU_Einsatz_Report_Share(
            fn($c, $x, $y, $t, $s, $col, $mw) => $this->draw_map_preview_text($c, $x, $y, $t, $s, $col, $mw),
            fn()                                => $this->get_map_preview_font_path(),
            fn($text)                           => $this->prepare_map_preview_text($text),
            fn($t, $s, $p)                      => $this->get_map_preview_ttf_text_width($t, $s, $p),
            fn($t, $s, $p, $mw)                 => $this->fit_map_preview_text_to_width($t, $s, $p, $mw),
            fn($post)                            => $this->is_einsatzbericht_post($post),
            fn($post_id)                         => $this->get_editable_report($post_id)
        );
        $this->schnelleingabe = new FEU_Einsatz_Schnelleingabe($this->db);
        $this->init_hooks();
    }

    public static function get_plugin_full_access_roles() {
        return ['administrator'];
    }

    public static function get_plugin_admin_only_sections() {
        return ['settings', 'archives', 'logs'];
    }

    public static function get_plugin_legacy_full_roles() {
        return [];
    }

    public static function get_plugin_access_sections($include_hidden = false) {
        $sections = [
            'dashboard' => [
                'label' => __('Dashboard', 'feuer-einsatzberichte'),
                'description' => __('Startseite des Plugins.', 'feuer-einsatzberichte'),
            ],
            'reports' => [
                'label' => __('Alle Berichte', 'feuer-einsatzberichte'),
                'description' => __('Listenansicht aller Einsatzberichte im WordPress-Backend.', 'feuer-einsatzberichte'),
            ],
            'create_report' => [
                'label' => __('Neuer Bericht', 'feuer-einsatzberichte'),
                'description' => __('Neuen Einsatzbericht anlegen und bearbeiten.', 'feuer-einsatzberichte'),
            ],
            'quick_entry' => [
                'label' => __('Schnelleingabe', 'feuer-einsatzberichte'),
                'description' => __('Mobile Schnelleingabe für neue Einsatzberichte.', 'feuer-einsatzberichte'),
            ],
            'participants' => [
                'label' => __('Teilnehmer', 'feuer-einsatzberichte'),
                'description' => __('Teilnehmerliste und Standardfunktionen verwalten.', 'feuer-einsatzberichte'),
            ],
            'statistics' => [
                'label' => __('Statistiken', 'feuer-einsatzberichte'),
                'description' => __('Statistiken, Aktivitätskarte und Auswertungen ansehen.', 'feuer-einsatzberichte'),
            ],
            'settings' => [
                'label' => __('Einstellungen', 'feuer-einsatzberichte'),
                'description' => __('Plugin-Einstellungen und technische Optionen verwalten.', 'feuer-einsatzberichte'),
            ],
            'archives' => [
                'label' => __('Archive', 'feuer-einsatzberichte'),
                'description' => __('Archive erstellen, einspielen und verwalten.', 'feuer-einsatzberichte'),
            ],
            'logs' => [
                'label' => __('Logs', 'feuer-einsatzberichte'),
                'description' => __('Änderungs- und Systemprotokolle einsehen.', 'feuer-einsatzberichte'),
            ],
            'edit_report' => [
                'label' => __('Einsatzbericht bearbeiten', 'feuer-einsatzberichte'),
                'description' => __('Interne Bearbeitungsseite für bestehende Einsatzberichte.', 'feuer-einsatzberichte'),
                'hidden' => true,
                'alias' => 'create_report',
            ],
        ];

        if ($include_hidden) {
            return $sections;
        }

        return array_filter($sections, static function ($section) {
            return empty($section['hidden']);
        });
    }

    public static function get_plugin_access_roles() {
        $wp_roles = wp_roles();

        if (!($wp_roles instanceof WP_Roles)) {
            return [];
        }

        $roles = [];

        foreach ((array) $wp_roles->roles as $role_key => $role_data) {
            $roles[$role_key] = translate_user_role((string) ($role_data['name'] ?? $role_key));
        }

        return $roles;
    }

    public static function get_default_plugin_role_access_settings() {
        $sections = array_keys(self::get_plugin_access_sections());
        $roles = self::get_plugin_access_roles();
        $wp_roles = wp_roles();
        $defaults = [];
        $basic_sections = ['dashboard', 'reports', 'create_report', 'quick_entry', 'participants'];

        foreach ($sections as $section_key) {
            $defaults[$section_key] = [];
        }

        $default_full_roles = array_merge(self::get_plugin_full_access_roles(), self::get_plugin_legacy_full_roles());

        foreach ($roles as $role_key => $role_label) {
            if (in_array($role_key, $default_full_roles, true)) {
                foreach ($sections as $section_key) {
                    $defaults[$section_key][] = $role_key;
                }

                continue;
            }

            $role_capabilities = (array) (($wp_roles instanceof WP_Roles && isset($wp_roles->roles[$role_key]['capabilities']))
                ? $wp_roles->roles[$role_key]['capabilities']
                : []);

            if (!empty($role_capabilities['edit_posts'])) {
                foreach ($basic_sections as $section_key) {
                    $defaults[$section_key][] = $role_key;
                }
            }
        }

        foreach ($defaults as $section_key => $allowed_roles) {
            $defaults[$section_key] = array_values(array_unique($allowed_roles));
        }

        return $defaults;
    }

    public static function normalize_plugin_role_access_settings($settings) {
        $roles = array_keys(self::get_plugin_access_roles());
        $defaults = self::get_default_plugin_role_access_settings();
        $normalized = [];
        $has_explicit_settings = is_array($settings) && !empty($settings);

        foreach (self::get_plugin_access_sections() as $section_key => $section_definition) {
            $is_admin_only_section = in_array($section_key, self::get_plugin_admin_only_sections(), true);

            if (is_array($settings) && array_key_exists($section_key, $settings)) {
                $raw_roles = is_array($settings[$section_key]) ? $settings[$section_key] : [];
                $submitted_roles = array_map('sanitize_key', $raw_roles);
            } elseif ($has_explicit_settings) {
                $submitted_roles = [];
            } else {
                $submitted_roles = (array) ($defaults[$section_key] ?? []);
            }

            if ($is_admin_only_section) {
                $submitted_roles = [];
            }

            $allowed_roles = array_values(array_unique(array_intersect($roles, $submitted_roles)));

            foreach (self::get_plugin_full_access_roles() as $full_role) {
                if (in_array($full_role, $roles, true) && !in_array($full_role, $allowed_roles, true)) {
                    $allowed_roles[] = $full_role;
                }
            }

            $normalized[$section_key] = array_values(array_unique($allowed_roles));
        }

        return $normalized;
    }

    public static function get_plugin_role_access_settings() {
        return self::normalize_plugin_role_access_settings(
            get_option(self::ROLE_ACCESS_OPTION, [])
        );
    }

    private static function normalize_plugin_access_section_key($section_key) {
        $section_key = sanitize_key((string) $section_key);
        $sections = self::get_plugin_access_sections(true);

        if (!isset($sections[$section_key])) {
            return '';
        }

        if (!empty($sections[$section_key]['alias'])) {
            return sanitize_key((string) $sections[$section_key]['alias']);
        }

        return $section_key;
    }

    public static function current_user_can_access_plugin_section($section_key) {
        $section_key = self::normalize_plugin_access_section_key($section_key);

        if ('' === $section_key || !is_user_logged_in()) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        $user = wp_get_current_user();
        $user_roles = is_array($user->roles) ? $user->roles : [];

        if (empty($user_roles)) {
            return false;
        }

        if (!empty(array_intersect($user_roles, self::get_plugin_full_access_roles()))) {
            return true;
        }

        if (in_array($section_key, self::get_plugin_admin_only_sections(), true)) {
            return false;
        }

        $settings = self::get_plugin_role_access_settings();
        $allowed_roles = isset($settings[$section_key]) && is_array($settings[$section_key])
            ? $settings[$section_key]
            : [];

        return !empty(array_intersect($user_roles, $allowed_roles));
    }

    private function enforce_plugin_section_access($section_key) {
        if (!self::current_user_can_access_plugin_section($section_key)) {
            wp_die(esc_html__('Keine Berechtigung', 'feuer-einsatzberichte'));
        }
    }

    private function get_first_accessible_plugin_admin_url() {
        $can_edit_posts = current_user_can('edit_posts');

        $candidates = [
            'dashboard' => admin_url('admin.php?page=feuer-einsatzberichte'),
            'reports' => $can_edit_posts ? admin_url('edit.php?post_type=post&feu_einsatz_filter=1') : '',
            'create_report' => $can_edit_posts ? admin_url('admin.php?page=feu-einsatz-neuer-bericht') : '',
            'quick_entry' => $can_edit_posts ? admin_url('admin.php?page=feu-einsatz-schnelleingabe') : '',
            'participants' => admin_url('admin.php?page=feu-einsatz-teilnehmer'),
            'statistics' => admin_url('admin.php?page=feu-einsatz-statistiken'),
            'settings' => admin_url('admin.php?page=feu-einsatz-einstellungen'),
            'archives' => admin_url('admin.php?page=feu-einsatz-archive'),
            'logs' => admin_url('admin.php?page=feu-einsatz-logs'),
        ];

        foreach ($candidates as $section_key => $url) {
            if ('' !== $url && self::current_user_can_access_plugin_section($section_key)) {
                return $url;
            }
        }

        return '';
    }

    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_head', [$this, 'hide_internal_admin_page_links']);
        add_action('admin_head-index.php', [$this, 'print_wp_dashboard_widget_styles']);
        add_action('admin_notices', [$this, 'render_setup_required_notice']);
        add_action('admin_footer', [$this, 'render_plugin_setup_status_panel']);
        add_filter('admin_body_class', [$this, 'filter_admin_body_class']);
        add_action('save_post', [$this, 'clear_street_cache'], 10, 3);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_post_feu_einsatz_create_report', [$this, 'handle_create_report']);
        add_action('admin_post_feu_einsatz_update_report', [$this, 'handle_update_report']);
        add_action('admin_post_feu_einsatz_generate_map_image_now', [$this, 'handle_generate_map_image_now']);
        add_action('admin_post_feu_einsatz_delete_map_image', [$this, 'handle_delete_map_image']);
        add_action('admin_post_feu_einsatz_share_image',              [$this->report_share, 'handle_share_image_download']);
        add_action('admin_post_feu_einsatz_share_image_public',         [$this->report_share, 'handle_public_share_image_request']);
        add_action('admin_post_nopriv_feu_einsatz_share_image_public',  [$this->report_share, 'handle_public_share_image_request']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes'], 10, 2);
        add_action('save_post', [$this, 'save_post_data'], 10, 3);
        add_action('post_updated', [$this, 'on_einsatz_updated'], 10, 1);
        add_action('save_post', [$this, 'on_einsatz_updated'], 20, 1);
        add_action('trashed_post',      [$this->report_logger, 'log_trashed_report']);
        add_action('before_delete_post', [$this->report_logger, 'log_deleted_report']);
        add_filter('manage_post_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_post_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
        add_filter('get_edit_post_link', [$this, 'filter_edit_post_link'], 10, 3);
        add_filter('parent_file', [$this, 'filter_admin_parent_file']);
        add_filter('submenu_file', [$this, 'filter_admin_submenu_file']);
        add_action('pre_get_posts', [$this, 'filter_admin_post_list']);
        add_action('wp_ajax_feu_einsatz_generate_map_image', [$this, 'ajax_generate_map_image']);
        add_action(self::GENERATED_MAP_BACKGROUND_HOOK, [$this, 'handle_background_map_preview_generation']);
        add_action('feu_einsatz_background_geocode', [$this, 'handle_background_geocode']);
        add_action(
            FEU_Einsatz_Image_Protection::BACKGROUND_HOOK,
            ['FEU_Einsatz_Image_Protection', 'handle_background_generation'],
            10,
            2
        );
        add_action('admin_post_feu_einsatz_schnelleingabe', [$this->schnelleingabe, 'handle_post']);
        add_action('admin_enqueue_scripts',                 [$this->schnelleingabe, 'enqueue_assets']);
        add_action('wp_dashboard_setup', [$this, 'register_wp_dashboard_widgets']);
    }

    private function is_plugin_admin_screen_context(string $hook = ''): bool {
        $hook = (string) $hook;

        if (
            false !== strpos($hook, 'feuer-einsatzberichte')
            || false !== strpos($hook, 'feu-einsatz-')
        ) {
            return true;
        }

        if ('edit.php' === $hook) {
            $is_report_list_screen = isset($_GET['feu_einsatz_filter'])
                && '1' === sanitize_text_field(wp_unslash($_GET['feu_einsatz_filter']));

            if ($is_report_list_screen) {
                return true;
            }
        }

        return false;
    }

    private function should_load_plugin_dashboard_ui(string $hook = ''): bool {
        $hook = (string) $hook;

        if ('index.php' !== $hook && 'dashboard' !== $hook) {
            return false;
        }

        return self::current_user_can_access_plugin_section('dashboard')
            || self::current_user_can_access_plugin_section('quick_entry')
            || self::current_user_can_access_plugin_section('statistics');
    }

    private function is_plugin_setup_status_screen(): bool {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_id = ($screen && !empty($screen->id)) ? (string) $screen->id : '';

        if ('' !== $screen_id && $this->is_plugin_admin_screen_context($screen_id)) {
            return true;
        }

        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ('feuer-einsatzberichte' === $page || 0 === strpos($page, 'feu-einsatz-')) {
            return true;
        }

        if ($screen && 'post' === (string) $screen->base) {
            $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
            $post = $post_id ? get_post($post_id) : null;

            return $post instanceof WP_Post && $this->is_einsatzbericht_post($post);
        }

        return false;
    }

    private function get_plugin_setup_status(): array {
        $settings_url = admin_url('admin.php?page=feu-einsatz-einstellungen');
        $participants_url = admin_url('admin.php?page=feu-einsatz-teilnehmer');
        $new_report_url = admin_url('admin.php?page=feu-einsatz-neuer-bericht');
        $shortcodes_url = add_query_arg('tab', 'shortcodes', $settings_url);
        $items = [];

        $add_item = static function (
            array &$target,
            string $key,
            string $label,
            string $message,
            string $status,
            bool $required,
            string $action_label,
            string $url
        ): void {
            $target[] = [
                'key' => $key,
                'label' => $label,
                'message' => $message,
                'status' => in_array($status, ['ok', 'missing', 'warning'], true) ? $status : 'warning',
                'required' => $required,
                'action_label' => $action_label,
                'url' => $url,
            ];
        };

        $station_street = trim((string) get_option('feu_einsatz_area_station_street', ''));
        $station_postcode = preg_replace('/\D+/', '', (string) get_option('feu_einsatz_area_station_postcode', ''));
        $station_city = trim((string) get_option('feu_einsatz_area_station_city', 'Hamburg'));
        $station_address_complete = '' !== $station_street && preg_match('/^\d{5}$/', $station_postcode) && '' !== $station_city;

        $add_item(
            $items,
            'station_address',
            __('Feuerwehrhaus-Adresse', 'feuer-einsatzberichte'),
            $station_address_complete
                ? sprintf(
                    /* translators: %1$s: street, %2$s: postcode, %3$s: city */
                    __('Gespeichert: %1$s, %2$s %3$s.', 'feuer-einsatzberichte'),
                    $station_street,
                    $station_postcode,
                    $station_city
                )
                : __('Pflicht: Strasse, PLZ und Stadt des Feuerwehrhauses eintragen. Diese Daten werden fuer Karten, Live-Vorschau und Feuerwehrhaus-Markierung genutzt.', 'feuer-einsatzberichte'),
            $station_address_complete ? 'ok' : 'missing',
            true,
            __('Karten-Einstellungen oeffnen', 'feuer-einsatzberichte'),
            add_query_arg('tab', 'karten', $settings_url)
        );

        $station_logo_id = absint(get_option('feu_einsatz_area_station_logo_id', 0));
        $station_logo_ok = $station_logo_id > 0 && (bool) wp_get_attachment_image_url($station_logo_id, 'thumbnail');

        $add_item(
            $items,
            'station_logo',
            __('Feuerwehrhaus-Logo', 'feuer-einsatzberichte'),
            $station_logo_ok
                ? __('Logo ist hinterlegt und kann auf Karten/Station-Marker genutzt werden.', 'feuer-einsatzberichte')
                : __('Empfohlen: Logo hinterlegen, damit Feuerwehrhaus-Marker und Admin-Vorschauen eindeutig aussehen.', 'feuer-einsatzberichte'),
            $station_logo_ok ? 'ok' : 'warning',
            false,
            __('Logo hinterlegen', 'feuer-einsatzberichte'),
            add_query_arg('tab', 'karten', $settings_url)
        );

        $selected_categories = array_filter(array_map('absint', (array) get_option('feu_einsatz_categories', [])));
        $categories_ok = count($selected_categories) > 0;

        $add_item(
            $items,
            'categories',
            __('Einsatz-Kategorien', 'feuer-einsatzberichte'),
            $categories_ok
                ? sprintf(
                    /* translators: %d: category count */
                    _n('%d Kategorie ist aktiv.', '%d Kategorien sind aktiv.', count($selected_categories), 'feuer-einsatzberichte'),
                    count($selected_categories)
                )
                : __('Pflicht: mindestens eine Kategorie fuer Einsatzberichte aktivieren.', 'feuer-einsatzberichte'),
            $categories_ok ? 'ok' : 'missing',
            true,
            __('Kategorien auswaehlen', 'feuer-einsatzberichte'),
            add_query_arg('tab', 'kategorien', $settings_url)
        );

        $functions = array_values(array_filter(array_map('trim', (array) get_option('feu_einsatz_functions', []))));
        $functions_ok = count($functions) > 0;

        $add_item(
            $items,
            'functions',
            __('Teilnehmer-Funktionen', 'feuer-einsatzberichte'),
            $functions_ok
                ? sprintf(
                    /* translators: %d: function count */
                    _n('%d Funktion ist konfiguriert.', '%d Funktionen sind konfiguriert.', count($functions), 'feuer-einsatzberichte'),
                    count($functions)
                )
                : __('Pflicht: Funktionen wie Maschinist, ATF oder Mannschaft anlegen.', 'feuer-einsatzberichte'),
            $functions_ok ? 'ok' : 'missing',
            true,
            __('Funktionen bearbeiten', 'feuer-einsatzberichte'),
            add_query_arg('tab', 'funktionen', $settings_url)
        );

        $participants_count = 0;
        $organizations_count = 0;

        if ($this->db instanceof FEU_Einsatz_Database) {
            $participants = $this->db->get_participants([
                'include_archived' => false,
                'include_deleted' => false,
            ]);
            $organizations = $this->db->get_organizations([
                'include_archived' => false,
            ]);
            $participants_count = is_array($participants) ? count($participants) : 0;
            $organizations_count = is_array($organizations) ? count($organizations) : 0;
        }

        $add_item(
            $items,
            'participants',
            __('Teilnehmer', 'feuer-einsatzberichte'),
            $participants_count > 0
                ? sprintf(
                    /* translators: %d: participant count */
                    _n('%d aktiver Teilnehmer ist vorhanden.', '%d aktive Teilnehmer sind vorhanden.', $participants_count, 'feuer-einsatzberichte'),
                    $participants_count
                )
                : __('Pflicht: Mannschaftsakte anlegen, damit Teilnehmer in Berichten gewaehlt werden koennen.', 'feuer-einsatzberichte'),
            $participants_count > 0 ? 'ok' : 'missing',
            true,
            __('Teilnehmer verwalten', 'feuer-einsatzberichte'),
            $participants_url
        );

        $add_item(
            $items,
            'organizations',
            __('Kraefte vor Ort', 'feuer-einsatzberichte'),
            $organizations_count > 0
                ? sprintf(
                    /* translators: %d: organization count */
                    _n('%d Organisation ist aktiv.', '%d Organisationen sind aktiv.', $organizations_count, 'feuer-einsatzberichte'),
                    $organizations_count
                )
                : __('Empfohlen: Organisationen/Fahrzeuge fuer Kraefte vor Ort pflegen.', 'feuer-einsatzberichte'),
            $organizations_count > 0 ? 'ok' : 'warning',
            false,
            __('Organisationen bearbeiten', 'feuer-einsatzberichte'),
            add_query_arg('tab', 'organisationen', $settings_url)
        );

        $auto_map_enabled = 1 === (int) get_option('feu_einsatz_auto_map_image', 1);
        $map_zoom = (int) get_option('feu_einsatz_map_zoom', 16);
        $map_height = (int) get_option('feu_einsatz_map_height', 400);
        $map_settings_ok = $auto_map_enabled && $map_zoom >= 1 && $map_zoom <= 20 && $map_height >= 240;

        $add_item(
            $items,
            'map_generation',
            __('Kartenbild-Erzeugung', 'feuer-einsatzberichte'),
            $map_settings_ok
                ? sprintf(
                    /* translators: %1$d: zoom, %2$d: height */
                    __('Aktiv. Zoom %1$d, Hoehe %2$d px.', 'feuer-einsatzberichte'),
                    $map_zoom,
                    $map_height
                )
                : __('Empfohlen: automatische Kartenbilder aktivieren und Zoom/Hoehe sinnvoll setzen.', 'feuer-einsatzberichte'),
            $map_settings_ok ? 'ok' : 'warning',
            false,
            __('Karten konfigurieren', 'feuer-einsatzberichte'),
            add_query_arg('tab', 'karten', $settings_url)
        );

        $attribution_ok = 1 === (int) get_option('feu_einsatz_map_preview_show_attribution', 1)
            && '' !== trim((string) get_option('feu_einsatz_map_preview_attribution_text', ''));

        $add_item(
            $items,
            'map_attribution',
            __('Karten-Copyright', 'feuer-einsatzberichte'),
            $attribution_ok
                ? __('Copyright/Attribution ist sichtbar konfiguriert.', 'feuer-einsatzberichte')
                : __('Pflicht: Karten-Copyright sichtbar lassen, damit OpenStreetMap/Leaflet korrekt genannt werden.', 'feuer-einsatzberichte'),
            $attribution_ok ? 'ok' : 'missing',
            true,
            __('Copyright pruefen', 'feuer-einsatzberichte'),
            add_query_arg('tab', 'karten', $settings_url)
        );

        $social_networks = FEU_Einsatz_Template_Helpers::normalize_social_share_networks(
            get_option('feu_einsatz_social_share_enabled_networks', FEU_Einsatz_Template_Helpers::get_default_social_share_networks())
        );
        $share_logo_id = absint(get_option('feu_einsatz_social_share_logo_id', 0));
        $share_ok = !empty($social_networks);
        $share_logo_ok = $share_logo_id > 0 && (bool) wp_get_attachment_image_url($share_logo_id, 'thumbnail');

        $add_item(
            $items,
            'share',
            __('Teilen / Share-Karte', 'feuer-einsatzberichte'),
            $share_ok && $share_logo_ok
                ? __('Netzwerke und Share-Logo sind konfiguriert.', 'feuer-einsatzberichte')
                : __('Empfohlen: aktive Netzwerke und Logo fuer Share-Karten pruefen.', 'feuer-einsatzberichte'),
            $share_ok && $share_logo_ok ? 'ok' : 'warning',
            false,
            __('Share-Einstellungen oeffnen', 'feuer-einsatzberichte'),
            add_query_arg('tab', 'sozial', $settings_url)
        );

        $watermark_text = trim((string) get_option('feu_einsatz_photo_watermark_text', get_bloginfo('name')));

        $add_item(
            $items,
            'watermark',
            __('Foto-Wasserzeichen', 'feuer-einsatzberichte'),
            '' !== $watermark_text
                ? sprintf(
                    /* translators: %s: watermark text */
                    __('Text gesetzt: %s.', 'feuer-einsatzberichte'),
                    $watermark_text
                )
                : __('Empfohlen: Wasserzeichen-Text setzen, damit Fotos eindeutig zugeordnet bleiben.', 'feuer-einsatzberichte'),
            '' !== $watermark_text ? 'ok' : 'warning',
            false,
            __('Medien-Einstellungen oeffnen', 'feuer-einsatzberichte'),
            add_query_arg('tab', 'medien', $settings_url)
        );

        $overview_shortcode = '[feuer_einsatzberichte]';
        $area_shortcode = '[feuer_einsatzgebiet]';

        $add_item(
            $items,
            'shortcodes',
            __('Shortcodes / Seiten', 'feuer-einsatzberichte'),
            sprintf(
                /* translators: %1$s: overview shortcode, %2$s: area shortcode */
                __('Wichtig fuer Frontend-Seiten: %1$s fuer Uebersicht, %2$s fuer Einsatzgebiet.', 'feuer-einsatzberichte'),
                $overview_shortcode,
                $area_shortcode
            ),
            'warning',
            false,
            __('Shortcodes ansehen', 'feuer-einsatzberichte'),
            $shortcodes_url
        );

        $critical_missing = 0;
        $warnings = 0;
        $ok = 0;

        foreach ($items as $item) {
            if ('ok' === $item['status']) {
                $ok++;
                continue;
            }

            if (!empty($item['required']) && 'missing' === $item['status']) {
                $critical_missing++;
                continue;
            }

            $warnings++;
        }

        return [
            'complete' => 0 === $critical_missing,
            'critical_missing' => $critical_missing,
            'warnings' => $warnings,
            'ok' => $ok,
            'items' => $items,
            'version' => FEU_EINSATZ_VERSION,
            'settings_url' => $settings_url,
            'new_report_url' => $new_report_url,
        ];
    }

    public function render_setup_required_notice(): void {
        if (!$this->is_plugin_setup_status_screen() || !self::current_user_can_access_plugin_section('settings')) {
            return;
        }

        $setup_status = $this->get_plugin_setup_status();

        if (!empty($setup_status['complete'])) {
            return;
        }

        $missing_items = array_values(array_filter($setup_status['items'], static function ($item) {
            return !empty($item['required']) && 'missing' === (string) ($item['status'] ?? '');
        }));

        echo '<div class="notice notice-warning feu-einsatz-setup-notice">';
        echo '<p><strong>' . esc_html__('Ersteinrichtung erforderlich', 'feuer-einsatzberichte') . '</strong> ';
        echo esc_html__('Damit Einsatzberichte, Karten und Teilen-Funktionen sauber arbeiten, muessen zuerst die Pflichtdaten gepflegt werden.', 'feuer-einsatzberichte') . '</p>';

        if (!empty($missing_items)) {
            echo '<ul class="feu-einsatz-setup-notice-list">';

            foreach (array_slice($missing_items, 0, 5) as $missing_item) {
                echo '<li>' . esc_html((string) $missing_item['label']) . ': ' . esc_html((string) $missing_item['message']) . '</li>';
            }

            echo '</ul>';
        }

        echo '<p><a class="button button-primary" href="' . esc_url((string) $setup_status['settings_url']) . '">' . esc_html__('Einrichtung oeffnen', 'feuer-einsatzberichte') . '</a></p>';
        echo '</div>';
    }

    public function render_plugin_setup_status_panel(): void {
        if (!$this->is_plugin_setup_status_screen()) {
            return;
        }

        if (
            !self::current_user_can_access_plugin_section('dashboard')
            && !self::current_user_can_access_plugin_section('settings')
            && !self::current_user_can_access_plugin_section('create_report')
        ) {
            return;
        }

        $setup_status = $this->get_plugin_setup_status();
        $overall_state = !empty($setup_status['complete']) ? 'ok' : 'missing';
        $summary_text = !empty($setup_status['complete'])
            ? __('Pflichtdaten vollstaendig.', 'feuer-einsatzberichte')
            : sprintf(
                /* translators: %d: missing count */
                _n('%d Pflichtpunkt fehlt.', '%d Pflichtpunkte fehlen.', (int) $setup_status['critical_missing'], 'feuer-einsatzberichte'),
                (int) $setup_status['critical_missing']
            );
        ?>
        <div class="feu-plugin-status-widget is-<?php echo esc_attr($overall_state); ?>" data-feu-setup-panel>
            <button type="button" class="feu-plugin-status-toggle" data-feu-setup-panel-toggle aria-expanded="false" aria-controls="feu-plugin-status-panel">
                <span class="feu-plugin-status-toggle-icon ti ti-info-circle" aria-hidden="true"></span>
                <span class="feu-plugin-status-toggle-state" aria-hidden="true"></span>
                <span class="screen-reader-text"><?php esc_html_e('Plugin-Status anzeigen', 'feuer-einsatzberichte'); ?></span>
            </button>

            <section id="feu-plugin-status-panel" class="feu-plugin-status-panel" hidden>
                <div class="feu-plugin-status-panel-header">
                    <div>
                        <span class="feu-plugin-status-eyebrow"><?php esc_html_e('Feuer-Einsatzberichte', 'feuer-einsatzberichte'); ?></span>
                        <h2><?php esc_html_e('Plugin-Status', 'feuer-einsatzberichte'); ?></h2>
                        <p><?php echo esc_html($summary_text); ?> <?php echo esc_html(sprintf(__('Version %s', 'feuer-einsatzberichte'), (string) $setup_status['version'])); ?></p>
                    </div>
                    <button type="button" class="feu-plugin-status-close" data-feu-setup-panel-close aria-label="<?php esc_attr_e('Schliessen', 'feuer-einsatzberichte'); ?>">
                        <span class="ti ti-x" aria-hidden="true"></span>
                    </button>
                </div>

                <div class="feu-plugin-status-legend">
                    <span><i class="feu-plugin-status-dot is-ok"></i><?php esc_html_e('OK', 'feuer-einsatzberichte'); ?></span>
                    <span><i class="feu-plugin-status-dot is-warning"></i><?php esc_html_e('Empfohlen', 'feuer-einsatzberichte'); ?></span>
                    <span><i class="feu-plugin-status-dot is-missing"></i><?php esc_html_e('Fehlt', 'feuer-einsatzberichte'); ?></span>
                </div>

                <div class="feu-plugin-status-list">
                    <?php foreach ($setup_status['items'] as $item) : ?>
                        <?php
                        $item_status = (string) ($item['status'] ?? 'warning');
                        $icon_class = 'ok' === $item_status ? 'ti ti-check' : ('missing' === $item_status ? 'ti ti-x' : 'ti ti-alert-triangle');
                        ?>
                        <article class="feu-plugin-status-item is-<?php echo esc_attr($item_status); ?>">
                            <span class="feu-plugin-status-item-icon"><span class="<?php echo esc_attr($icon_class); ?>" aria-hidden="true"></span></span>
                            <div class="feu-plugin-status-item-body">
                                <h3><?php echo esc_html((string) $item['label']); ?></h3>
                                <p><?php echo esc_html((string) $item['message']); ?></p>
                                <?php if (!empty($item['url'])) : ?>
                                    <a href="<?php echo esc_url((string) $item['url']); ?>"><?php echo esc_html((string) $item['action_label']); ?></a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="feu-plugin-status-recommendations">
                    <strong><?php esc_html_e('Empfohlene Reihenfolge', 'feuer-einsatzberichte'); ?></strong>
                    <ol>
                        <li><?php esc_html_e('Feuerwehrhaus-Adresse und Logo unter Einstellungen > Karten pflegen.', 'feuer-einsatzberichte'); ?></li>
                        <li><?php esc_html_e('Kategorien und Funktionen festlegen.', 'feuer-einsatzberichte'); ?></li>
                        <li><?php esc_html_e('Teilnehmer und Kraefte vor Ort anlegen.', 'feuer-einsatzberichte'); ?></li>
                        <li><?php esc_html_e('Share-Karte, Wasserzeichen und Shortcode-Seiten pruefen.', 'feuer-einsatzberichte'); ?></li>
                    </ol>
                </div>
            </section>
        </div>
        <?php
    }

    private function should_use_modern_shell(string $hook = '', ?WP_Screen $screen = null): bool {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $hook = (string) $hook;
        $screen_base = $screen ? (string) $screen->base : '';

        if ($this->should_load_plugin_dashboard_ui($hook) || $this->should_load_plugin_dashboard_ui($screen_base)) {
            return true;
        }

        $modern_pages = [
            'feuer-einsatzberichte',
            'feu-einsatz-statistiken',
            'feu-einsatz-teilnehmer',
            'feu-einsatz-einstellungen',
            'feu-einsatz-archive',
            'feu-einsatz-logs',
        ];

        return in_array($page, $modern_pages, true);
    }

    public function filter_admin_body_class($classes) {
        $classes = trim((string) $classes);
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $hook = $screen ? (string) $screen->id : '';
        $screen_base = $screen ? (string) $screen->base : '';

        if ($this->is_plugin_admin_screen_context($hook) || $this->should_load_plugin_dashboard_ui($hook) || $this->should_load_plugin_dashboard_ui($screen_base)) {
            $classes .= ' feu-admin-ui';
        }

        if ($this->should_use_modern_shell($hook, $screen)) {
            $classes .= ' feu-admin-ui--modern-shell';
        }

        if ('' !== $page && 0 === strpos($page, 'feu-')) {
            $classes .= ' feu-admin-ui--' . sanitize_html_class($page);
        }

        if ($this->should_load_plugin_dashboard_ui($hook) || $this->should_load_plugin_dashboard_ui($screen_base)) {
            $classes .= ' feu-admin-ui--dashboard';
        }

        return trim($classes);
    }


    // Rueckwaertskompatible Ranking-Weiterleitungen.
    // Externe Aufrufer (templates/admin/statistics.php, class-ajax-handler.php)
    // nutzen FEU_Einsatz_Admin::...; diese Methoden delegieren an die neue Klasse.

    public static function get_participant_ranking_pin_file_path() {
        return FEU_Einsatz_Participant_Ranking::get_pin_file_path();
    }

    public static function sanitize_participant_ranking_pin($pin) {
        return FEU_Einsatz_Participant_Ranking::sanitize_pin((string) $pin);
    }

    public static function get_participant_ranking_pin() {
        return FEU_Einsatz_Participant_Ranking::get_pin();
    }

    public static function cleanup_legacy_participant_ranking_pin_file() {
        return FEU_Einsatz_Participant_Ranking::cleanup_legacy_pin_file();
    }

    public static function update_participant_ranking_pin($pin) {
        return FEU_Einsatz_Participant_Ranking::update_pin((string) $pin);
    }

    public static function verify_participant_ranking_pin($pin) {
        return FEU_Einsatz_Participant_Ranking::verify_pin((string) $pin);
    }

    public static function is_participant_ranking_unlocked_for_current_user() {
        return FEU_Einsatz_Participant_Ranking::is_unlocked_for_current_user();
    }

    public static function unlock_participant_ranking_for_current_user() {
        return FEU_Einsatz_Participant_Ranking::unlock_for_current_user();
    }

    public static function lock_participant_ranking_for_current_user() {
        return FEU_Einsatz_Participant_Ranking::lock_for_current_user();
    }

    // Rueckwaertskompatible Share-Weiterleitung fuer Templates.

    public function get_single_share_box_data($post, array $context = []) {
        // Delegiere an class-public.php (unverändert) oder report_share je nach Kontext
        // Diese Methode existiert in class-public.php; hier nur fuer den Admin-Kontext.
        if (!($post instanceof WP_Post)) {
            return [];
        }
        return $this->report_share->build_admin_share_box_data($post);
    }

    // в”Ђв”Ђ Rückwärts-Kompatibilität: Logger-Forwards в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ

    public function log_trashed_report($post_id) {
        $this->report_logger->log_trashed_report((int) $post_id);
    }

    public function log_deleted_report($post_id) {
        $this->report_logger->log_deleted_report((int) $post_id);
    }

    private function get_report_log_payload($post_id) {
        return $this->report_logger->get_payload((int) $post_id);
    }

    private function get_report_log_change_set($before, $after) {
        return $this->report_logger->get_change_set(
            is_array($before) ? $before : [],
            is_array($after)  ? $after  : []
        );
    }

    // в”Ђв”Ђ Rückwärts-Kompatibilität: Share-Forwards в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ

    public function handle_share_image_download() {
        $this->report_share->handle_share_image_download();
    }

    public function handle_public_share_image_request() {
        $this->report_share->handle_public_share_image_request();
    }

    public function invalidate_share_card_cache($post_id) {
        $this->report_share->invalidate_share_card_cache((int) $post_id);
    }

    public function handle_background_geocode(int $post_id): void {
        $post_id = absint($post_id);

        if (!$post_id) {
            return;
        }

        $strasse = trim((string) get_post_meta($post_id, '_feu_einsatz_strasse', true));
        $hausnummer = trim((string) get_post_meta($post_id, '_feu_einsatz_hausnummer', true));
        $plz = trim((string) get_post_meta($post_id, '_feu_einsatz_plz', true));
        $stadt = trim((string) get_post_meta($post_id, '_feu_einsatz_stadt', true));

        if ('' === $strasse) {
            return;
        }

        $lat = $this->sanitize_coordinate_value((string) get_post_meta($post_id, '_feu_einsatz_latitude', true), 'lat');
        $lng = $this->sanitize_coordinate_value((string) get_post_meta($post_id, '_feu_einsatz_longitude', true), 'lng');

        if (!is_numeric($lat) || !is_numeric($lng)) {
            $this->get_and_save_coordinates($post_id, $strasse, $plz, $stadt, $hausnummer);
            $lat = $this->sanitize_coordinate_value((string) get_post_meta($post_id, '_feu_einsatz_latitude', true), 'lat');
            $lng = $this->sanitize_coordinate_value((string) get_post_meta($post_id, '_feu_einsatz_longitude', true), 'lng');
        }

        if (
            (int) get_option('feu_einsatz_auto_map_image', 1)
            && !wp_next_scheduled(self::GENERATED_MAP_BACKGROUND_HOOK, [$post_id])
            && !get_post_meta($post_id, self::GENERATED_MAP_QUEUE_META, true)
        ) {
            $this->maybe_queue_generated_map_preview_generation(
                $post_id,
                $strasse,
                $plz,
                $stadt,
                true,
                $this->get_generated_map_generation_delay_seconds(),
                'background_geocode'
            );
        }
    }

    public function invalidate_all_share_card_caches() {
        $this->report_share->invalidate_all_share_card_caches();
    }

    public function on_einsatz_updated($post_id): void {
        $post_id = absint($post_id);

        if (!$post_id || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if ('1' !== get_post_meta($post_id, '_feu_einsatz_einsatzbericht', true)) {
            return;
        }

        $this->report_share->invalidate_share_card_cache($post_id);
    }

    private function build_admin_share_box_data($post) {
        if (!($post instanceof WP_Post)) { return []; }
        return $this->report_share->build_admin_share_box_data($post);
    }

    private function get_generated_map_date_segments($post_id) {
        $post_id = absint($post_id);
        $event_date = trim((string) get_post_meta($post_id, '_feu_einsatz_datum', true));

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $event_date, $matches)) {
            return [$matches[1], $matches[2]];
        }

        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $event_date, $matches)) {
            return [$matches[3], $matches[2]];
        }

        $current_datetime = current_datetime();

        return [$current_datetime->format('Y'), $current_datetime->format('m')];
    }

    private function get_generated_map_upload_subdir($post_id) {
        [$year, $month] = $this->get_generated_map_date_segments($post_id);

        return '/feuer-einsatzberichte/' . $year . '/' . $month;
    }

    private function get_generated_map_storage_paths($post_id) {
        $upload_dir = wp_upload_dir();

        if (!empty($upload_dir['error'])) {
            return new WP_Error(
                'feu_einsatz_map_preview_upload_dir_failed',
                __('Der Upload-Ordner für die Kartenvorschau ist nicht verfügbar.', 'feuer-einsatzberichte')
            );
        }

        $subdir = ltrim($this->get_generated_map_upload_subdir($post_id), '/');

        return [
            'subdir' => '/' . $subdir,
            'dir' => trailingslashit($upload_dir['basedir']) . $subdir,
            'url' => trailingslashit($upload_dir['baseurl']) . str_replace('\\', '/', $subdir),
        ];
    }

    private function ensure_generated_map_public_access($base_dir) {
        $base_dir = rtrim((string) $base_dir, '/\\');

        if ('' === $base_dir) {
            return;
        }

        $public_root = $base_dir . DIRECTORY_SEPARATOR . 'feuer-einsatzberichte';

        if (!file_exists($public_root)) {
            wp_mkdir_p($public_root);
        }

        foreach (['.htaccess', 'web.config'] as $access_file) {
            $path = $public_root . DIRECTORY_SEPARATOR . $access_file;

            if (file_exists($path)) {
                @unlink($path);
            }
        }

        $index_file = $public_root . DIRECTORY_SEPARATOR . 'index.html';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, '');
        }

        $index_php_file = $public_root . DIRECTORY_SEPARATOR . 'index.php';
        if (!file_exists($index_php_file)) {
            @file_put_contents($index_php_file, "<?php\n// Silence is golden.\n");
        }
    }

    public function filter_generated_map_upload_dir($uploads) {
        if ('' === $this->generated_map_upload_subdir) {
            return $uploads;
        }

        if (!empty($uploads['basedir'])) {
            $this->ensure_generated_map_public_access($uploads['basedir']);
        }

        $subdir = '/' . ltrim($this->generated_map_upload_subdir, '/');
        $uploads['subdir'] = $subdir;
        $uploads['path'] = $uploads['basedir'] . $subdir;
        $uploads['url'] = $uploads['baseurl'] . $subdir;

        if (!file_exists($uploads['path'])) {
            wp_mkdir_p($uploads['path']);
        }

        return $uploads;
    }

    public function add_admin_menus() {
        if ('' === $this->get_first_accessible_plugin_admin_url()) {
            return;
        }

        $can_edit_posts = current_user_can('edit_posts');

        add_menu_page(
            __('Einsatzberichte', 'feuer-einsatzberichte'),
            __('Einsatzberichte', 'feuer-einsatzberichte'),
            'read',
            'feuer-einsatzberichte',
            [$this, 'render_dashboard'],
            'dashicons-megaphone',
            25
        );

        if (self::current_user_can_access_plugin_section('dashboard')) {
            add_submenu_page(
                'feuer-einsatzberichte',
                __('Dashboard', 'feuer-einsatzberichte'),
                __('Dashboard', 'feuer-einsatzberichte'),
                'read',
                'feuer-einsatzberichte',
                [$this, 'render_dashboard']
            );
        }

        if ($can_edit_posts && self::current_user_can_access_plugin_section('reports')) {
            add_submenu_page(
                'feuer-einsatzberichte',
                __('Alle Berichte', 'feuer-einsatzberichte'),
                __('Alle Berichte', 'feuer-einsatzberichte'),
                'edit_posts',
                'edit.php?post_type=post&feu_einsatz_filter=1'
            );
        }

        if ($can_edit_posts && self::current_user_can_access_plugin_section('create_report')) {
            add_submenu_page(
                'feuer-einsatzberichte',
                __('Neuer Bericht', 'feuer-einsatzberichte'),
                __('Neuer Bericht', 'feuer-einsatzberichte'),
                'read',
                'feu-einsatz-neuer-bericht',
                [$this, 'render_create_report']
            );
        }

        if ($can_edit_posts && self::current_user_can_access_plugin_section('quick_entry')) {
            add_submenu_page(
                'feuer-einsatzberichte',
                __('Schnelleingabe', 'feuer-einsatzberichte'),
                __('Schnelleingabe', 'feuer-einsatzberichte'),
                'read',
                'feu-einsatz-schnelleingabe',
                [$this->schnelleingabe, 'render']
            );
        }

        if ($can_edit_posts && self::current_user_can_access_plugin_section('create_report')) {
            add_submenu_page(
                'feuer-einsatzberichte',
                __('Einsatzbericht bearbeiten', 'feuer-einsatzberichte'),
                __('Einsatzbericht bearbeiten', 'feuer-einsatzberichte'),
                'read',
                'feu-einsatz-bericht-bearbeiten',
                [$this, 'render_edit_report']
            );
        }

        if (self::current_user_can_access_plugin_section('participants')) {
            add_submenu_page(
                'feuer-einsatzberichte',
                __('Teilnehmer', 'feuer-einsatzberichte'),
                __('Teilnehmer', 'feuer-einsatzberichte'),
                'read',
                'feu-einsatz-teilnehmer',
                [$this, 'render_participants']
            );
        }

        if (self::current_user_can_access_plugin_section('statistics')) {
            add_submenu_page(
                'feuer-einsatzberichte',
                __('Statistiken', 'feuer-einsatzberichte'),
                __('Statistiken', 'feuer-einsatzberichte'),
                'read',
                'feu-einsatz-statistiken',
                [$this, 'render_statistics']
            );
        }

        if (self::current_user_can_access_plugin_section('settings')) {
            add_submenu_page(
                'feuer-einsatzberichte',
                __('Einstellungen', 'feuer-einsatzberichte'),
                __('Einstellungen', 'feuer-einsatzberichte'),
                'read',
                'feu-einsatz-einstellungen',
                [$this, 'render_settings']
            );
        }

        if (self::current_user_can_access_plugin_section('archives')) {
            add_submenu_page(
                'feuer-einsatzberichte',
                __('Archive', 'feuer-einsatzberichte'),
                __('Archive', 'feuer-einsatzberichte'),
                'read',
                'feu-einsatz-archive',
                [$this, 'render_archives']
            );
        }

        if (self::current_user_can_access_plugin_section('logs')) {
            add_submenu_page(
                'feuer-einsatzberichte',
                __('Logs', 'feuer-einsatzberichte'),
                __('Logs', 'feuer-einsatzberichte'),
                'read',
                'feu-einsatz-logs',
                [$this, 'render_logs']
            );
        }
    }

    public function register_wp_dashboard_widgets(): void {
        if (!is_admin() || !current_user_can('read')) {
            return;
        }

        if (self::current_user_can_access_plugin_section('quick_entry') && current_user_can('edit_posts')) {
            wp_add_dashboard_widget(
                'feu_einsatz_dashboard_quick_entry',
                __('Schnelleingabe', 'feuer-einsatzberichte'),
                [$this->schnelleingabe, 'render_dashboard_widget']
            );
        }

        if (self::current_user_can_access_plugin_section('statistics')) {
            wp_add_dashboard_widget(
                'feu_einsatz_dashboard_statistics',
                __('Einsatz-Statistiken', 'feuer-einsatzberichte'),
                [$this, 'render_wp_dashboard_statistics_widget']
            );
        }

        if (self::current_user_can_access_plugin_section('create_report') && current_user_can('edit_posts')) {
            wp_add_dashboard_widget(
                'feu_einsatz_dashboard_map_queue',
                __('Kartenbild-Status', 'feuer-einsatzberichte'),
                [$this, 'render_wp_dashboard_map_queue_widget']
            );
        }

        if (self::current_user_can_access_plugin_section('reports') && current_user_can('edit_posts')) {
            wp_add_dashboard_widget(
                'feu_einsatz_dashboard_recent_reports',
                __('Letzte Einsatzberichte', 'feuer-einsatzberichte'),
                [$this, 'render_wp_dashboard_recent_reports_widget']
            );
        }

        $this->move_dashboard_widget_to_top('feu_einsatz_dashboard_quick_entry');
    }

    private function move_dashboard_widget_to_top(string $widget_id): void {
        global $wp_meta_boxes;

        if (
            empty($wp_meta_boxes['dashboard']['normal']['core'][$widget_id])
            && empty($wp_meta_boxes['dashboard']['side']['core'][$widget_id])
        ) {
            return;
        }

        foreach (['normal', 'side'] as $context) {
            if (empty($wp_meta_boxes['dashboard'][$context]['core'][$widget_id])) {
                continue;
            }

            $widget = [$widget_id => $wp_meta_boxes['dashboard'][$context]['core'][$widget_id]];
            unset($wp_meta_boxes['dashboard'][$context]['core'][$widget_id]);
            $wp_meta_boxes['dashboard'][$context]['core'] = $widget + $wp_meta_boxes['dashboard'][$context]['core'];
        }
    }

    public function print_wp_dashboard_widget_styles(): void {
        if (
            !self::current_user_can_access_plugin_section('quick_entry')
            && !self::current_user_can_access_plugin_section('statistics')
            && !self::current_user_can_access_plugin_section('reports')
        ) {
            return;
        }

        echo '<style>
            .feu-einsatz-dashboard-widget-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin:0}
            .feu-einsatz-dashboard-stat{padding:14px;border:1px solid #d8e0ec;border-radius:14px;background:#fff}
            .feu-einsatz-dashboard-stat strong{display:block;font-size:22px;line-height:1.1;margin-bottom:4px}
            .feu-einsatz-dashboard-stat span{color:#475569;font-size:12px;font-weight:600}
            .feu-einsatz-dashboard-widget-links{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
            .feu-einsatz-dashboard-recent-list{display:grid;gap:12px;margin:0}
            .feu-einsatz-dashboard-recent-item{padding:14px;border:1px solid #d8e0ec;border-radius:14px;background:#fff}
            .feu-einsatz-dashboard-recent-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
            .feu-einsatz-dashboard-recent-title{font-weight:700;color:#0f172a;text-decoration:none}
            .feu-einsatz-dashboard-recent-meta{margin-top:6px;color:#475569;font-size:13px}
            .feu-einsatz-dashboard-recent-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
            .feu-einsatz-dashboard-widget-note{color:#475569;margin:0 0 12px}
            .feu-einsatz-dashboard-mapqueue-list{display:grid;gap:8px;margin:0}
            .feu-einsatz-dashboard-mapqueue-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px 12px;padding:10px 12px;border:1px solid #d8e0ec;border-radius:10px;background:#fff;align-items:start}
            .feu-einsatz-dashboard-mapqueue-main{min-width:0}
            .feu-einsatz-dashboard-mapqueue-head{display:flex;align-items:center;gap:8px;min-width:0}
            .feu-einsatz-dashboard-mapqueue-title{font-weight:700;color:#0f172a;text-decoration:none;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .feu-einsatz-dashboard-mapqueue-meta{margin-top:4px;color:#475569;font-size:12px;line-height:1.45}
            .feu-einsatz-dashboard-mapqueue-status{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}
            .feu-einsatz-dashboard-mapqueue-status--geocode{background:#eefafe;color:#0f7490}
            .feu-einsatz-dashboard-mapqueue-status--map{background:#eff6ff;color:#1d4ed8}
            .feu-einsatz-dashboard-mapqueue-status--queued{background:#eff6ff;color:#1d4ed8}
            .feu-einsatz-dashboard-mapqueue-status--processing{background:#fff7ed;color:#c2410c}
            .feu-einsatz-dashboard-mapqueue-status--error{background:#fef2f2;color:#b91c1c}
            .feu-einsatz-dashboard-mapqueue-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;align-self:center}
            .feu-einsatz-dashboard-mapqueue-count{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 8px;border-radius:999px;background:#e5edf8;color:#123b67;font-size:12px;font-weight:700}
            .feu-einsatz-dashboard-inline-form{display:inline-flex}
            @media (max-width: 782px){.feu-einsatz-dashboard-mapqueue-item{grid-template-columns:1fr}.feu-einsatz-dashboard-mapqueue-actions{justify-content:flex-start}}
        </style>';
    }

    public function hide_internal_admin_page_links() {
        if (!current_user_can('edit_posts') || !self::current_user_can_access_plugin_section('create_report')) {
            return;
        }

        echo '<style>#adminmenu a[href="admin.php?page=feu-einsatz-bericht-bearbeiten"]{display:none !important;}</style>';
    }

    public function filter_admin_post_list($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $feu_einsatz_filter = isset($_GET['feu_einsatz_filter']) ? sanitize_text_field(wp_unslash($_GET['feu_einsatz_filter'])) : '';

        if ('1' === $feu_einsatz_filter) {
            $meta_query = [
                [
                    'key' => '_feu_einsatz_einsatzbericht',
                    'value' => '1',
                    'compare' => '=',
                ],
            ];

            $street_filter = isset($_GET['feu_einsatz_street'])
                ? sanitize_text_field(wp_unslash($_GET['feu_einsatz_street']))
                : '';
            $postcode_filter = isset($_GET['feu_einsatz_postcode'])
                ? preg_replace('/\D+/', '', (string) wp_unslash($_GET['feu_einsatz_postcode']))
                : '';
            $city_filter = isset($_GET['feu_einsatz_city'])
                ? sanitize_text_field(wp_unslash($_GET['feu_einsatz_city']))
                : '';

            if ('' !== trim($street_filter)) {
                $meta_query[] = [
                    'key' => '_feu_einsatz_strasse',
                    'value' => trim($street_filter),
                    'compare' => '=',
                ];
            }

            if ('' !== $postcode_filter) {
                $meta_query[] = [
                    'key' => '_feu_einsatz_plz',
                    'value' => $postcode_filter,
                    'compare' => '=',
                ];
            }

            if ('' !== trim($city_filter)) {
                $meta_query[] = [
                    'key' => '_feu_einsatz_stadt',
                    'value' => trim($city_filter),
                    'compare' => '=',
                ];
            }

            if (count($meta_query) > 1) {
                $meta_query['relation'] = 'AND';
            }

            $query->set('meta_query', $meta_query);
        }
    }

    public function clear_street_cache($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (!($post instanceof WP_Post) || 'post' !== $post->post_type) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!$this->has_address_changed_from_request($post_id)) {
            return;
        }

        FEU_Einsatz_Street_Cache::clear_post_cache($post_id);
    }

    private function has_address_changed_from_request($post_id) {
        foreach (['strasse', 'plz', 'stadt'] as $field) {
            $request_key = 'feu_einsatz_' . $field;

            if (!isset($_POST[$request_key])) {
                continue;
            }

            $submitted_value = sanitize_text_field(wp_unslash($_POST[$request_key]));
            $current_value = (string) get_post_meta($post_id, '_feu_einsatz_' . $field, true);

            if (trim($submitted_value) !== trim($current_value)) {
                return true;
            }
        }

        return false;
    }

    private function sanitize_coordinate_value($value, $type) {
        $value = trim((string) $value);

        if ('' === $value) {
            return '';
        }

        $value = str_replace(',', '.', $value);

        if (!is_numeric($value)) {
            return '';
        }

        $value = (float) $value;

        if ('lat' === $type && ($value < -90 || $value > 90)) {
            return '';
        }

        if ('lng' === $type && ($value < -180 || $value > 180)) {
            return '';
        }

        return (string) round($value, 6);
    }

    private function get_cached_coordinates($post_id) {
        $latitude = get_post_meta($post_id, '_feu_einsatz_latitude', true);
        $longitude = get_post_meta($post_id, '_feu_einsatz_longitude', true);

        if (is_numeric($latitude) && is_numeric($longitude)) {
            return [
                'lat' => (float) $latitude,
                'lng' => (float) $longitude,
            ];
        }

        $strasse = (string) get_post_meta($post_id, '_feu_einsatz_strasse', true);
        $hausnummer = (string) get_post_meta($post_id, '_feu_einsatz_hausnummer', true);
        $plz = (string) get_post_meta($post_id, '_feu_einsatz_plz', true);
        $stadt = (string) get_post_meta($post_id, '_feu_einsatz_stadt', true);

        if ('' !== trim($strasse)) {
            $remote_coordinates = $this->get_and_save_coordinates($post_id, $strasse, $plz, $stadt, $hausnummer);

            if ($remote_coordinates) {
                return $remote_coordinates;
            }
        }

        $geometry_payload = $this->get_map_preview_geometry($post_id);

        if (
            is_array($geometry_payload)
            && !empty($geometry_payload['center'])
            && isset($geometry_payload['center'][0], $geometry_payload['center'][1])
        ) {
            $latitude = (float) $geometry_payload['center'][0];
            $longitude = (float) $geometry_payload['center'][1];

            update_post_meta($post_id, '_feu_einsatz_latitude', (string) round($latitude, 6));
            update_post_meta($post_id, '_feu_einsatz_longitude', (string) round($longitude, 6));

            return [
                'lat' => $latitude,
                'lng' => $longitude,
            ];
        }

        return false;
    }


    public function enqueue_scripts($hook) {
        $is_plugin_screen = $this->is_plugin_admin_screen_context((string) $hook);
        $is_report_list_screen = 'edit.php' === $hook
            && isset($_GET['feu_einsatz_filter'])
            && '1' === sanitize_text_field(wp_unslash($_GET['feu_einsatz_filter']));
        $is_dashboard_widget_screen = $this->should_load_plugin_dashboard_ui((string) $hook);
        $is_statistics_screen = false !== strpos((string) $hook, 'feu-einsatz-statistiken');
        $is_settings_screen = false !== strpos((string) $hook, 'feu-einsatz-einstellungen');

        if (!$is_plugin_screen && 'post.php' !== $hook && 'post-new.php' !== $hook && !$is_report_list_screen && !$is_dashboard_widget_screen) {
            return;
        }

        $admin_style_path = FEU_EINSATZ_PLUGIN_DIR . 'assets/admin/css/admin-style.css';
        $admin_modern_style_path = FEU_EINSATZ_PLUGIN_DIR . 'assets/admin/css/admin-modern.css';
        $admin_script_path = FEU_EINSATZ_PLUGIN_DIR . 'assets/admin/js/admin-script.js';
        $chart_script_path = FEU_EINSATZ_PLUGIN_DIR . 'assets/vendor/chartjs/chart.min.js';
        $tabler_style_path = FEU_EINSATZ_PLUGIN_DIR . 'assets/vendor/tabler/css/tabler.min.css';
        $tabler_icons_path = FEU_EINSATZ_PLUGIN_DIR . 'assets/vendor/tabler-icons/tabler-icons.min.css';
        $tabler_script_path = FEU_EINSATZ_PLUGIN_DIR . 'assets/vendor/tabler/js/tabler.min.js';
        $admin_style_version = file_exists($admin_style_path) ? (string) filemtime($admin_style_path) : FEU_EINSATZ_VERSION;
        $admin_modern_style_version = file_exists($admin_modern_style_path) ? (string) filemtime($admin_modern_style_path) : FEU_EINSATZ_VERSION;
        $admin_script_version = file_exists($admin_script_path) ? (string) filemtime($admin_script_path) : FEU_EINSATZ_VERSION;
        $chart_script_version = file_exists($chart_script_path) ? (string) filemtime($chart_script_path) : FEU_EINSATZ_VERSION;
        $tabler_style_version = file_exists($tabler_style_path) ? (string) filemtime($tabler_style_path) : FEU_EINSATZ_VERSION;
        $tabler_icons_version = file_exists($tabler_icons_path) ? (string) filemtime($tabler_icons_path) : FEU_EINSATZ_VERSION;
        $tabler_script_version = file_exists($tabler_script_path) ? (string) filemtime($tabler_script_path) : FEU_EINSATZ_VERSION;

        if ($is_plugin_screen || $is_dashboard_widget_screen) {
            wp_enqueue_style(
                'feu-einsatz-admin-tabler',
                FEU_EINSATZ_PLUGIN_URL . 'assets/vendor/tabler/css/tabler.min.css',
                [],
                $tabler_style_version
            );

            wp_enqueue_style(
                'feu-einsatz-admin-tabler-icons',
                FEU_EINSATZ_PLUGIN_URL . 'assets/vendor/tabler-icons/tabler-icons.min.css',
                ['feu-einsatz-admin-tabler'],
                $tabler_icons_version
            );

            wp_enqueue_script(
                'feu-einsatz-admin-tabler',
                FEU_EINSATZ_PLUGIN_URL . 'assets/vendor/tabler/js/tabler.min.js',
                [],
                $tabler_script_version,
                true
            );
        }

        wp_enqueue_style(
            'feu-einsatz-admin-style',
            FEU_EINSATZ_PLUGIN_URL . 'assets/admin/css/admin-style.css',
            $is_plugin_screen || $is_dashboard_widget_screen ? ['feu-einsatz-admin-tabler', 'feu-einsatz-admin-tabler-icons'] : [],
            $admin_style_version
        );

        wp_enqueue_style(
            'feu-einsatz-admin-modern-style',
            FEU_EINSATZ_PLUGIN_URL . 'assets/admin/css/admin-modern.css',
            ['feu-einsatz-admin-style'],
            $admin_modern_style_version
        );

        wp_enqueue_script(
            'feu-einsatz-admin-script',
            FEU_EINSATZ_PLUGIN_URL . 'assets/admin/js/admin-script.js',
            ['jquery', 'jquery-ui-datepicker'],
            $admin_script_version,
            true
        );

        wp_enqueue_script(
            'chart-js',
            FEU_EINSATZ_PLUGIN_URL . 'assets/vendor/chartjs/chart.min.js',
            [],
            $chart_script_version,
            true
        );

        if ($is_statistics_screen || $is_settings_screen) {
            $leaflet_style_path = FEU_EINSATZ_PLUGIN_DIR . 'assets/vendor/leaflet/leaflet.css';
            $leaflet_script_path = FEU_EINSATZ_PLUGIN_DIR . 'assets/vendor/leaflet/leaflet.js';
            $leaflet_style_version = file_exists($leaflet_style_path) ? (string) filemtime($leaflet_style_path) : FEU_EINSATZ_VERSION;
            $leaflet_script_version = file_exists($leaflet_script_path) ? (string) filemtime($leaflet_script_path) : FEU_EINSATZ_VERSION;

            wp_enqueue_style(
                'feu-einsatz-admin-leaflet',
                FEU_EINSATZ_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.css',
                [],
                $leaflet_style_version
            );

            wp_enqueue_script(
                'feu-einsatz-admin-leaflet',
                FEU_EINSATZ_PLUGIN_URL . 'assets/vendor/leaflet/leaflet.js',
                [],
                $leaflet_script_version,
                true
            );
        }

        if ('post.php' === $hook || 'post-new.php' === $hook || false !== strpos((string) $hook, 'feu-einsatz-einstellungen') || false !== strpos((string) $hook, 'feu-einsatz-neuer-bericht') || false !== strpos((string) $hook, 'feu-einsatz-bericht-bearbeiten')) {
            wp_enqueue_media();
        }

        wp_localize_script('feu-einsatz-admin-script', 'feu_einsatz_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'admin_post_url' => admin_url('admin-post.php'),
            'nonce' => wp_create_nonce('feu_einsatz_ajax_nonce'),
            'street_suggestions' => FEU_Einsatz_Template_Helpers::get_street_suggestions(),
            'street_suggestion_records' => FEU_Einsatz_Template_Helpers::get_street_suggestion_records(),
            'strings' => [
                'confirm_delete' => __('Sind Sie sicher?', 'feuer-einsatzberichte'),
                'error' => __('Ein Fehler ist aufgetreten', 'feuer-einsatzberichte'),
                'success' => __('Erfolgreich gespeichert', 'feuer-einsatzberichte'),
            ],
        ]);
    }

    public function add_meta_boxes($post_type, $post) {
        if ('post' !== $post_type || !$this->is_einsatzbericht_context($post)) {
            return;
        }

        add_meta_box(
            'feu_einsatz_einsatz_details',
            __('Einsatzdetails', 'feuer-einsatzberichte'),
            [$this, 'render_einsatz_details_meta_box'],
            'post',
            'normal',
            'high'
        );

        add_meta_box(
            'feu_einsatz_teilnehmer',
            __('Teilnehmer & Kräfte vor Ort', 'feuer-einsatzberichte'),
            [$this, 'render_teilnehmer_meta_box'],
            'post',
            'normal',
            'high'
        );
    }

    private function is_einsatzbericht_context($post = null) {
        $requested_type = isset($_GET['feu_einsatz_einsatzbericht']) ? sanitize_text_field(wp_unslash($_GET['feu_einsatz_einsatzbericht'])) : '';

        if ('1' === $requested_type) {
            return true;
        }

        if ($post instanceof WP_Post && !empty($post->ID)) {
            return '1' === get_post_meta($post->ID, '_feu_einsatz_einsatzbericht', true);
        }

        return false;
    }

    public function render_einsatz_details_meta_box($post) {
        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/meta-box-details.php';
    }

    public function render_teilnehmer_meta_box($post) {
        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/meta-box-teilnehmer.php';
    }

    public function save_post_data($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (!($post instanceof WP_Post) || 'post' !== $post->post_type) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!isset($_POST['feu_einsatz_meta_box_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['feu_einsatz_meta_box_nonce']));
        if (!wp_verify_nonce($nonce, 'feu_einsatz_save_post_data')) {
            return;
        }

        $is_einsatzbericht = isset($_POST['feu_einsatz_is_einsatzbericht']) && '1' === sanitize_text_field(wp_unslash($_POST['feu_einsatz_is_einsatzbericht']));

        if (!$is_einsatzbericht) {
            delete_post_meta($post_id, '_feu_einsatz_einsatzbericht');
            delete_post_meta($post_id, '_feu_einsatz_teilnehmer');
            delete_post_meta($post_id, '_feu_einsatz_organisationen');
            delete_post_meta($post_id, '_feu_einsatz_gallery');
            delete_post_meta($post_id, '_feu_einsatz_comments_enabled');
            $this->clear_generated_map_queue($post_id, true);
            $this->clear_background_geocode_schedule($post_id);
            $this->clear_generated_map_publish_hold($post_id);
            $this->cleanup_previous_generated_map_thumbnail($post_id);
            $this->cleanup_generated_map_preview_file($post_id);
            delete_post_meta($post_id, self::GENERATED_MAP_SIGNATURE_META);
            $this->db->save_participant_stats($post_id, []);
            return;
        }

        update_post_meta($post_id, '_feu_einsatz_einsatzbericht', '1');

        $this->save_einsatz_details($post_id);

        $strasse = get_post_meta($post_id, '_feu_einsatz_strasse', true);
        $plz = get_post_meta($post_id, '_feu_einsatz_plz', true);
        $stadt = get_post_meta($post_id, '_feu_einsatz_stadt', true);
        $lat = get_post_meta($post_id, '_feu_einsatz_latitude', true);
        $lng = get_post_meta($post_id, '_feu_einsatz_longitude', true);

        if ((empty($lat) || empty($lng)) && !empty($strasse)) {
            $this->schedule_background_geocode($post_id);
        }

        $this->save_teilnehmer($post_id);
        $this->save_organisationen($post_id);
        $this->save_gallery($post_id);
        $this->maybe_apply_report_availability($post_id, $post->post_status);

        $auto_map_image = (int) get_option('feu_einsatz_auto_map_image', 1);
        if ($auto_map_image && !empty($strasse)) {
            $this->maybe_queue_generated_map_preview_generation(
                $post_id,
                $strasse,
                $plz,
                $stadt,
                false,
                $this->get_generated_map_generation_delay_seconds(),
                'save_post'
            );
        }
    }

    private function build_full_address($strasse, $plz = '', $stadt = 'Hamburg', $hausnummer = '') {
        $strasse = trim((string) $strasse);
        $hausnummer = trim((string) $hausnummer);
        $plz = trim((string) $plz);
        $stadt = trim((string) $stadt);

        if ('' === $stadt) {
            $stadt = 'Hamburg';
        }

        $street_address = trim(implode(' ', array_filter([$strasse, $hausnummer], static function ($value) {
            return '' !== $value;
        })));

        $parts = array_filter([$street_address, $plz, $stadt], static function ($value) {
            return '' !== $value;
        });

        return implode(', ', $parts) . ', Deutschland';
    }

    private function get_map_remote_user_agent() {
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

    private function get_map_remote_request_args($timeout = 15, $accept = 'application/json') {
        return [
            'timeout' => max(5, (int) $timeout),
            'redirection' => 3,
            'headers' => [
                'Accept' => $accept,
                'User-Agent' => $this->get_map_remote_user_agent(),
                'Referer' => home_url('/'),
            ],
        ];
    }

    private function decode_map_json_response($response) {
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

    private function build_map_geometry_segment_from_geojson_coordinates($coordinates, $reverse = true) {
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

    private function build_map_geometry_payload_from_geojson($geojson) {
        if (!is_array($geojson) || empty($geojson['type']) || empty($geojson['coordinates'])) {
            return false;
        }

        $segments = [];
        $type = (string) $geojson['type'];
        $coordinates = $geojson['coordinates'];

        if ('LineString' === $type) {
            $segment = $this->build_map_geometry_segment_from_geojson_coordinates($coordinates);

            if (!empty($segment)) {
                $segments[] = $segment;
            }
        } elseif ('MultiLineString' === $type) {
            foreach ((array) $coordinates as $line) {
                $segment = $this->build_map_geometry_segment_from_geojson_coordinates($line);

                if (!empty($segment)) {
                    $segments[] = $segment;
                }
            }
        } elseif ('Polygon' === $type) {
            $outer_ring = isset($coordinates[0]) ? $coordinates[0] : [];
            $segment = $this->build_map_geometry_segment_from_geojson_coordinates($outer_ring);

            if (!empty($segment)) {
                $segments[] = $segment;
            }
        } elseif ('MultiPolygon' === $type) {
            foreach ((array) $coordinates as $polygon) {
                $outer_ring = isset($polygon[0]) ? $polygon[0] : [];
                $segment = $this->build_map_geometry_segment_from_geojson_coordinates($outer_ring);

                if (!empty($segment)) {
                    $segments[] = $segment;
                }
            }
        }

        if (empty($segments)) {
            return false;
        }

        $all_points = [];

        foreach ($segments as $segment) {
            foreach ($segment as $point) {
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

    private function calculate_map_distance_meters($left, $right) {
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

    private function normalize_segment_points($segment) {
        if (is_array($segment) && isset($segment['points']) && is_array($segment['points'])) {
            $segment = $segment['points'];
        }

        $normalized = [];

        foreach ((array) $segment as $point) {
            if (!is_array($point) || !isset($point[0], $point[1]) || !is_numeric($point[0]) || !is_numeric($point[1])) {
                continue;
            }

            $candidate = [round((float) $point[0], 6), round((float) $point[1], 6)];
            $last = !empty($normalized) ? $normalized[count($normalized) - 1] : null;

            if ($last && $candidate[0] === $last[0] && $candidate[1] === $last[1]) {
                continue;
            }

            $normalized[] = $candidate;
        }

        return count($normalized) > 1 ? $normalized : [];
    }

    private function get_geometry_segment_highway($segment) {
        return is_array($segment) && isset($segment['highway'])
            ? sanitize_key((string) $segment['highway'])
            : '';
    }

    private function get_geometry_segment_kind($segment) {
        if (is_array($segment) && isset($segment['kind'])) {
            $kind = sanitize_key((string) $segment['kind']);

            if (in_array($kind, ['road', 'pedestrian'], true)) {
                return $kind;
            }
        }

        return $this->is_pedestrian_map_preview_highway($this->get_geometry_segment_highway($segment))
            ? 'pedestrian'
            : 'road';
    }

    private function build_geometry_segment_payload($points, $highway = '', $kind = '') {
        $normalized_points = $this->normalize_segment_points($points);

        if (count($normalized_points) < 2) {
            return false;
        }

        $highway = sanitize_key((string) $highway);
        $kind = sanitize_key((string) $kind);

        if (!in_array($kind, ['road', 'pedestrian'], true)) {
            $kind = $this->is_pedestrian_map_preview_highway($highway) ? 'pedestrian' : 'road';
        }

        return [
            'points' => $normalized_points,
            'highway' => $highway,
            'kind' => $kind,
        ];
    }

    private function get_overpass_element_highway($element) {
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

    private function extract_overpass_element_geometries($element) {
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

    private function build_street_geometry_overpass_query($pattern, $radius, $include_relations = false) {
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

    private function finalize_geometry_payload_segments($segments) {
        $segments = array_values(array_filter((array) $segments));

        if (empty($segments)) {
            return false;
        }

        $segments = $this->merge_connected_segments($segments);

        if (empty($segments)) {
            return false;
        }

        $all_points = [];

        foreach ($segments as $segment) {
            foreach ($this->normalize_segment_points($segment) as $point) {
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

    private function geometry_payload_has_pedestrian_segments($payload) {
        if (!is_array($payload) || empty($payload['geometry']) || !is_array($payload['geometry'])) {
            return false;
        }

        foreach ($payload['geometry'] as $segment) {
            if ('pedestrian' === $this->get_geometry_segment_kind($segment)) {
                return true;
            }
        }

        return false;
    }

    private function merge_geometry_payloads($left, $right) {
        $left_segments = is_array($left) && !empty($left['geometry']) && is_array($left['geometry']) ? $left['geometry'] : [];
        $right_segments = is_array($right) && !empty($right['geometry']) && is_array($right['geometry']) ? $right['geometry'] : [];

        return $this->finalize_geometry_payload_segments(array_merge($left_segments, $right_segments));
    }

    private function build_segment_hash($segment) {
        $normalized = $this->normalize_segment_points($segment);

        if (empty($normalized)) {
            return '';
        }

        $forward = wp_json_encode($normalized);
        $reverse = wp_json_encode(array_reverse($normalized));

        return md5(strcmp((string) $forward, (string) $reverse) <= 0 ? (string) $forward : (string) $reverse);
    }

    private function get_segment_endpoints($segment) {
        $normalized = $this->normalize_segment_points($segment);

        if (count($normalized) < 2) {
            return false;
        }

        return [
            'start' => $normalized[0],
            'end' => $normalized[count($normalized) - 1],
        ];
    }

    private function merge_segment_pair($left, $right, $endpoint_tolerance_meters = 18.0) {
        if ($this->get_geometry_segment_kind($left) !== $this->get_geometry_segment_kind($right)) {
            return false;
        }

        $left_points = $this->normalize_segment_points($left);
        $right_points = $this->normalize_segment_points($right);

        if (empty($left_points) || empty($right_points)) {
            return false;
        }

        $left_endpoints = $this->get_segment_endpoints($left_points);
        $right_endpoints = $this->get_segment_endpoints($right_points);

        if (!$left_endpoints || !$right_endpoints) {
            return false;
        }

        if ($this->calculate_map_distance_meters($left_endpoints['end'], $right_endpoints['start']) <= $endpoint_tolerance_meters) {
            $merged_points = array_merge($left_points, array_slice($right_points, 1));
        } elseif ($this->calculate_map_distance_meters($left_endpoints['end'], $right_endpoints['end']) <= $endpoint_tolerance_meters) {
            $right_points = array_reverse($right_points);
            $merged_points = array_merge($left_points, array_slice($right_points, 1));
        } elseif ($this->calculate_map_distance_meters($left_endpoints['start'], $right_endpoints['end']) <= $endpoint_tolerance_meters) {
            $merged_points = array_merge(array_slice($right_points, 0, -1), $left_points);
        } elseif ($this->calculate_map_distance_meters($left_endpoints['start'], $right_endpoints['start']) <= $endpoint_tolerance_meters) {
            $right_points = array_reverse($right_points);
            $merged_points = array_merge(array_slice($right_points, 0, -1), $left_points);
        } else {
            return false;
        }

        return $this->build_geometry_segment_payload(
            $merged_points,
            $this->get_geometry_segment_highway($left),
            $this->get_geometry_segment_kind($left)
        );
    }

    private function merge_connected_segments($segments, $endpoint_tolerance_meters = 18.0) {
        $remaining = array_values(array_filter(array_map(function($segment) {
            return $this->build_geometry_segment_payload(
                $this->normalize_segment_points($segment),
                $this->get_geometry_segment_highway($segment),
                $this->get_geometry_segment_kind($segment)
            );
        }, (array) $segments)));
        $merged_segments = [];

        while (!empty($remaining)) {
            $current = array_shift($remaining);
            $did_merge = true;

            while ($did_merge) {
                $did_merge = false;

                foreach ($remaining as $index => $candidate) {
                    $merged = $this->merge_segment_pair($current, $candidate, $endpoint_tolerance_meters);

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

    private function segment_connects_to_group($segment, $group, $endpoint_tolerance_meters = 18.0) {
        $segment_endpoints = $this->get_segment_endpoints($segment);

        if (!$segment_endpoints) {
            return false;
        }

        foreach ((array) $group as $group_segment) {
            $group_endpoints = $this->get_segment_endpoints($group_segment);

            if (!$group_endpoints) {
                continue;
            }

            foreach (['start', 'end'] as $segment_side) {
                foreach (['start', 'end'] as $group_side) {
                    if ($this->calculate_map_distance_meters($segment_endpoints[$segment_side], $group_endpoints[$group_side]) <= $endpoint_tolerance_meters) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function build_segment_groups($segments, $endpoint_tolerance_meters = 18.0) {
        $remaining = array_values((array) $segments);
        $groups = [];

        while (!empty($remaining)) {
            $group = [array_shift($remaining)];
            $did_expand = true;

            while ($did_expand) {
                $did_expand = false;

                foreach ($remaining as $index => $candidate) {
                    if (!$this->segment_connects_to_group($candidate, $group, $endpoint_tolerance_meters)) {
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

    private function get_segment_distance_to_coordinates($segment, $coordinates) {
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

        foreach ($this->normalize_segment_points($segment) as $point) {
            $distance = min($distance, $this->calculate_map_distance_meters($point, $reference));
        }

        return $distance;
    }

    private function pick_best_segment_group($groups, $coordinates) {
        if (empty($groups)) {
            return [];
        }

        $best_group = [];
        $best_distance = INF;

        foreach ((array) $groups as $group) {
            $group_distance = INF;

            foreach ((array) $group as $segment) {
                $group_distance = min($group_distance, $this->get_segment_distance_to_coordinates($segment, $coordinates));
            }

            if ($group_distance < $best_distance) {
                $best_distance = $group_distance;
                $best_group = $group;
            }
        }

        return $best_group;
    }

    private function build_map_geometry_payload_from_overpass_elements($elements, $coordinates = null) {
        if (empty($elements) || !is_array($elements)) {
            return false;
        }

        $segments = [];
        $seen_segments = [];

        foreach ($elements as $element) {
            $highway = $this->get_overpass_element_highway($element);

            foreach ($this->extract_overpass_element_geometries($element) as $raw_geometry) {
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

                $segment_payload = $this->build_geometry_segment_payload(
                    $segment,
                    $highway,
                    $this->is_pedestrian_map_preview_highway($highway) ? 'pedestrian' : 'road'
                );

                if (!$segment_payload) {
                    continue;
                }

                $segment_hash = $this->build_segment_hash($segment_payload);

                if ('' === $segment_hash || isset($seen_segments[$segment_hash])) {
                    continue;
                }

                $seen_segments[$segment_hash] = true;
                $segments[] = $segment_payload;
            }
        }

        return $this->finalize_geometry_payload_segments($segments);
    }

    private function get_map_geometry_payload_score($payload, $coordinates = null) {
        if (
            !is_array($payload)
            || empty($payload['geometry'])
            || !is_array($payload['geometry'])
        ) {
            return -INF;
        }

        $segment_count = 0;
        $total_length = 0.0;
        $closest_distance = INF;

        foreach ($payload['geometry'] as $segment) {
            $points = $this->normalize_segment_points($segment);

            if (count($points) < 2) {
                continue;
            }

            $segment_count++;

            for ($index = 1; $index < count($points); $index++) {
                $total_length += $this->calculate_map_distance_meters($points[$index - 1], $points[$index]);
            }

            if (
                is_array($coordinates)
                && isset($coordinates['lat'], $coordinates['lng'])
                && is_numeric($coordinates['lat'])
                && is_numeric($coordinates['lng'])
            ) {
                $closest_distance = min($closest_distance, $this->get_segment_distance_to_coordinates($segment, $coordinates));
            }
        }

        if ($segment_count < 1) {
            return -INF;
        }

        $distance_penalty = is_finite($closest_distance) ? min(2000, $closest_distance) * 0.35 : 0;

        return $total_length + ($segment_count * 120) - $distance_penalty;
    }

    private function get_street_query_candidates($strasse) {
        $strasse = trim((string) preg_replace('/\s+/', ' ', (string) $strasse));

        if ('' === $strasse) {
            return [];
        }

        $candidates = [$strasse];
        $first_segment = trim((string) preg_replace('/\s*,.*$/u', '', $strasse));

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

    private function request_geocoded_address_data($strasse, $plz = '', $stadt = 'Hamburg', $hausnummer = '') {
        $address = $this->build_full_address($strasse, $plz, $stadt, $hausnummer);

        if ('' === trim($address)) {
            return false;
        }

        $request_url = add_query_arg(
            [
                'q' => $address,
                'format' => 'jsonv2',
                'limit' => 1,
                'addressdetails' => 1,
                'polygon_geojson' => 1,
                'countrycodes' => 'de',
            ],
            self::NOMINATIM_SEARCH_URL
        );
        $response = wp_safe_remote_get($request_url, $this->get_map_remote_request_args(15));
        $results = $this->decode_map_json_response($response);

        if (empty($results[0]) || !is_array($results[0])) {
            return false;
        }

        $result = $results[0];

        if (
            !isset($result['lat'], $result['lon'])
            || !is_numeric($result['lat'])
            || !is_numeric($result['lon'])
        ) {
            return false;
        }

        return [
            'lat' => round((float) $result['lat'], 6),
            'lng' => round((float) $result['lon'], 6),
            'display_name' => isset($result['display_name']) ? sanitize_text_field((string) $result['display_name']) : '',
            'geojson' => (isset($result['geojson']) && is_array($result['geojson'])) ? $result['geojson'] : [],
        ];
    }

    private function request_street_geometry_data($strasse, $coordinates) {
        if (
            '' === trim((string) $strasse)
            || !is_array($coordinates)
            || !isset($coordinates['lat'], $coordinates['lng'])
        ) {
            return false;
        }

        $latitude = (float) $coordinates['lat'];
        $longitude = (float) $coordinates['lng'];
        $street_candidates = $this->get_street_query_candidates($strasse);

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
            $query = $this->build_street_geometry_overpass_query($query_spec['pattern'], $query_spec['radius'], false);

            if ('' === $query) {
                continue;
            }

            $query = str_replace(['{{LAT}}', '{{LNG}}'], [$latitude, $longitude], $query);
            $response = wp_safe_remote_post(
                self::OVERPASS_API_URL,
                array_merge(
                    $this->get_map_remote_request_args(16),
                    [
                        'body' => [
                            'data' => $query,
                        ],
                    ]
                )
            );
            $payload = $this->decode_map_json_response($response);

            if (empty($payload['elements']) || !is_array($payload['elements'])) {
                continue;
            }

            $geometry_payload = $this->build_map_geometry_payload_from_overpass_elements($payload['elements'], $coordinates);

            if ($geometry_payload) {
                $geometry_score = $this->get_map_geometry_payload_score($geometry_payload, $coordinates);

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
                $supplement_query = $this->build_street_geometry_overpass_query($exact_pattern, 8000, true);

                if ('' !== $supplement_query) {
                    $supplement_query = str_replace(['{{LAT}}', '{{LNG}}'], [$latitude, $longitude], $supplement_query);
                    $response = wp_safe_remote_post(
                        self::OVERPASS_API_URL,
                        array_merge(
                            $this->get_map_remote_request_args(16),
                            [
                                'body' => [
                                    'data' => $supplement_query,
                                ],
                            ]
                        )
                    );
                    $payload = $this->decode_map_json_response($response);

                    if (!empty($payload['elements']) && is_array($payload['elements'])) {
                        $supplement_payload = $this->build_map_geometry_payload_from_overpass_elements($payload['elements'], $coordinates);

                        if ($supplement_payload) {
                            $merged_payload = $this->merge_geometry_payloads($best_geometry_payload, $supplement_payload);

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

    private function request_focused_street_geometry_data($strasse, $coordinates) {
        $strasse = trim((string) $strasse);

        if (
            '' === $strasse
            || !is_array($coordinates)
            || !isset($coordinates['lat'], $coordinates['lng'])
            || !is_numeric($coordinates['lat'])
            || !is_numeric($coordinates['lng'])
        ) {
            return false;
        }

        $street_candidates = $this->get_street_query_candidates($strasse);
        $primary_candidate = !empty($street_candidates)
            ? (string) reset($street_candidates)
            : $strasse;
        $street_pattern = preg_quote($primary_candidate, '/');

        if ('' === $street_pattern) {
            return false;
        }

        $query = $this->build_street_geometry_overpass_query('^' . $street_pattern . '$', 8000, true);

        if ('' === $query) {
            return false;
        }

        $query = str_replace(
            ['{{LAT}}', '{{LNG}}'],
            [(float) $coordinates['lat'], (float) $coordinates['lng']],
            $query
        );

        $response = wp_safe_remote_post(
            self::OVERPASS_API_URL,
            array_merge(
                $this->get_map_remote_request_args(12),
                [
                    'body' => [
                        'data' => $query,
                    ],
                ]
            )
        );
        $payload = $this->decode_map_json_response($response);

        if (empty($payload['elements']) || !is_array($payload['elements'])) {
            return false;
        }

        return $this->build_map_geometry_payload_from_overpass_elements($payload['elements'], $coordinates);
    }

    private function build_map_preview_generation_context($post_id, $strasse, $plz = '', $stadt = 'Hamburg', $geocoded_data = null, $options = []) {
        $options = wp_parse_args($options, [
            'allow_focused_geometry_refresh' => false,
            'allow_remote_geometry_prime' => false,
            'prefer_local_canvas' => false,
            'fast_tile_mode' => true,
        ]);

        $strasse = trim((string) $strasse);
        $plz = trim((string) $plz);
        $stadt = trim((string) $stadt);

        if ('' === $stadt) {
            $stadt = 'Hamburg';
        }

        $latitude = '';
        $longitude = '';

        if (
            is_array($geocoded_data)
            && isset($geocoded_data['lat'], $geocoded_data['lng'])
            && is_numeric($geocoded_data['lat'])
            && is_numeric($geocoded_data['lng'])
        ) {
            $latitude = (string) $geocoded_data['lat'];
            $longitude = (string) $geocoded_data['lng'];
        } else {
            $latitude = $this->sanitize_coordinate_value((string) get_post_meta($post_id, '_feu_einsatz_latitude', true), 'lat');
            $longitude = $this->sanitize_coordinate_value((string) get_post_meta($post_id, '_feu_einsatz_longitude', true), 'lng');
        }

        $geometry_payload = FEU_Einsatz_Street_Cache::get_post_cache($post_id);

        if (!$geometry_payload && '' !== $strasse) {
            $geometry_payload = FEU_Einsatz_Street_Cache::get($strasse, $plz, $stadt);

            if ($geometry_payload) {
                FEU_Einsatz_Street_Cache::set_post_cache($post_id, $geometry_payload);
            }
        }

        if (
            !$geometry_payload
            && is_array($geocoded_data)
            && !empty($geocoded_data['geojson'])
        ) {
            $geometry_payload = $this->build_map_geometry_payload_from_geojson($geocoded_data['geojson']);
        }

        if (
            !$geometry_payload
            && !empty($options['allow_remote_geometry_prime'])
            && '' !== $strasse
            && is_numeric($latitude)
            && is_numeric($longitude)
        ) {
            $geometry_payload = $this->request_street_geometry_data($strasse, [
                'lat' => (float) $latitude,
                'lng' => (float) $longitude,
            ]);

            if ($geometry_payload) {
                FEU_Einsatz_Street_Cache::set($strasse, $plz, $stadt, $geometry_payload);
                FEU_Einsatz_Street_Cache::set_post_cache($post_id, $geometry_payload);
            }
        }

        if (
            !empty($options['allow_focused_geometry_refresh'])
            && '' !== $strasse
            && is_numeric($latitude)
            && is_numeric($longitude)
            && (
                !$geometry_payload
                || !$this->geometry_payload_has_pedestrian_segments($geometry_payload)
            )
        ) {
            $focused_geometry_payload = $this->request_focused_street_geometry_data($strasse, [
                'lat' => (float) $latitude,
                'lng' => (float) $longitude,
            ]);

            if ($focused_geometry_payload) {
                if ($geometry_payload) {
                    $geometry_payload = $this->merge_geometry_payloads($geometry_payload, $focused_geometry_payload) ?: $focused_geometry_payload;
                } else {
                    $geometry_payload = $focused_geometry_payload;
                }

                FEU_Einsatz_Street_Cache::set($strasse, $plz, $stadt, $geometry_payload);
                FEU_Einsatz_Street_Cache::set_post_cache($post_id, $geometry_payload);
            }
        }

        return [
            'street' => $strasse,
            'plz' => $plz,
            'city' => $stadt,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'geometry_payload' => $geometry_payload,
            'prefer_local_canvas' => !empty($options['prefer_local_canvas']),
            'fast_tile_mode' => !empty($options['fast_tile_mode']),
            'skip_remote_geometry' => true,
            'allow_remote_geocode' => false,
        ];
    }

    private function normalize_report_date_value($value) {
        $value = trim((string) $value);

        if ('' === $value) {
            return '';
        }

        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $value)) {
            $date = DateTime::createFromFormat('d.m.Y', $value);
            if ($date instanceof DateTime) {
                return $date->format('Y-m-d');
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        $timestamp = strtotime($value);

        if (false !== $timestamp) {
            return date('Y-m-d', $timestamp);
        }

        return $value;
    }

    private function normalize_report_time_value($value) {
        $value = trim((string) $value);

        if (preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', $value)) {
            return $value;
        }

        return '08:00';
    }

    private function build_report_datetime($date, $time = '08:00') {
        $date = $this->normalize_report_date_value($date);

        if ('' === $date) {
            return false;
        }

        $time = $this->normalize_report_time_value($time);
        $datetime = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i',
            $date . ' ' . $time,
            wp_timezone()
        );

        return ($datetime instanceof DateTimeImmutable) ? $datetime : false;
    }

    private function get_report_event_datetime_from_request($fallback_post_id = 0) {
        $date = isset($_POST['feu_einsatz_datum'])
            ? $this->normalize_report_date_value(sanitize_text_field(wp_unslash($_POST['feu_einsatz_datum'])))
            : '';
        $time = isset($_POST['feu_einsatz_uhrzeit'])
            ? $this->normalize_report_time_value(sanitize_text_field(wp_unslash($_POST['feu_einsatz_uhrzeit'])))
            : '08:00';

        if ('' === $date && $fallback_post_id > 0) {
            $date = $this->normalize_report_date_value((string) get_post_meta($fallback_post_id, '_feu_einsatz_datum', true));
            $time = $this->normalize_report_time_value((string) get_post_meta($fallback_post_id, '_feu_einsatz_uhrzeit', true));
        }

        return $this->build_report_datetime($date, $time);
    }

    private function get_report_event_datetime_from_validation(array $validation = [], $fallback_post_id = 0) {
        $date = isset($validation['date']) ? (string) $validation['date'] : '';
        $time = isset($validation['time']) ? (string) $validation['time'] : '08:00';
        $datetime = $this->build_report_datetime($date, $time);

        if ($datetime instanceof DateTimeImmutable) {
            return $datetime;
        }

        return $this->get_report_event_datetime_from_request($fallback_post_id);
    }

    private function is_valid_report_date_input($value) {
        $value = trim((string) $value);

        if ('' === $value) {
            return false;
        }

        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $value)) {
            $date = DateTime::createFromFormat('d.m.Y', $value);

            return $date instanceof DateTime && $date->format('d.m.Y') === $value;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = DateTime::createFromFormat('Y-m-d', $value);

            return $date instanceof DateTime && $date->format('Y-m-d') === $value;
        }

        return false;
    }

    private function is_valid_report_time_input($value) {
        return 1 === preg_match('/^(2[0-3]|[01]\d):([0-5]\d)$/', trim((string) $value));
    }

    private function validate_report_submission_request($post_id = 0) {
        $post_id = absint($post_id);
        $street = isset($_POST['feu_einsatz_strasse'])
            ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_strasse']))
            : ($post_id ? (string) get_post_meta($post_id, '_feu_einsatz_strasse', true) : '');
        $house_number = isset($_POST['feu_einsatz_hausnummer'])
            ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_hausnummer']))
            : ($post_id ? (string) get_post_meta($post_id, '_feu_einsatz_hausnummer', true) : '');
        $plz = isset($_POST['feu_einsatz_plz'])
            ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_plz']))
            : ($post_id ? (string) get_post_meta($post_id, '_feu_einsatz_plz', true) : '');
        $city = isset($_POST['feu_einsatz_stadt'])
            ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_stadt']))
            : ($post_id ? (string) get_post_meta($post_id, '_feu_einsatz_stadt', true) : '');
        $date_raw = isset($_POST['feu_einsatz_datum'])
            ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_datum']))
            : ($post_id ? (string) get_post_meta($post_id, '_feu_einsatz_datum', true) : '');
        $time_raw = isset($_POST['feu_einsatz_uhrzeit'])
            ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_uhrzeit']))
            : ($post_id ? (string) get_post_meta($post_id, '_feu_einsatz_uhrzeit', true) : '');
        $submitted_categories = isset($_POST['post_category']) ? array_map('absint', (array) wp_unslash($_POST['post_category'])) : [];
        $allowed_category_ids = wp_list_pluck($this->get_available_report_categories(), 'term_id');
        $selected_categories = empty($allowed_category_ids)
            ? array_values(array_filter($submitted_categories))
            : array_values(array_intersect($submitted_categories, array_map('absint', $allowed_category_ids)));
        $errors = [];

        $street = trim($street);
        $house_number = trim($house_number);
        $plz = trim($plz);
        $city = trim($city);
        $date_raw = trim($date_raw);
        $time_raw = trim($time_raw);

        if ('' === $street) {
            $errors[] = __('Straße ist ein Pflichtfeld.', 'feuer-einsatzberichte');
        }

        if (!preg_match('/^\d{5}$/', $plz)) {
            $errors[] = __('PLZ ist ein Pflichtfeld und muss genau 5 Ziffern enthalten.', 'feuer-einsatzberichte');
        }

        if ('' === $city) {
            $errors[] = __('Stadt ist ein Pflichtfeld.', 'feuer-einsatzberichte');
        }

        if (!$this->is_valid_report_date_input($date_raw)) {
            $errors[] = __('Datum ist ein Pflichtfeld und muss im Format TT.MM.JJJJ angegeben werden.', 'feuer-einsatzberichte');
        }

        if (!$this->is_valid_report_time_input($time_raw)) {
            $errors[] = __('Uhrzeit ist ein Pflichtfeld und muss im Format HH:MM angegeben werden.', 'feuer-einsatzberichte');
        }

        if (empty($selected_categories)) {
            $errors[] = __('Mindestens eine Kategorie ist ein Pflichtfeld.', 'feuer-einsatzberichte');
        }

        $geocoded_data = false;

        if (empty($errors) && $post_id > 0) {
            $stored_street = trim((string) get_post_meta($post_id, '_feu_einsatz_strasse', true));
            $stored_house_number = trim((string) get_post_meta($post_id, '_feu_einsatz_hausnummer', true));
            $stored_plz = trim((string) get_post_meta($post_id, '_feu_einsatz_plz', true));
            $stored_city = trim((string) get_post_meta($post_id, '_feu_einsatz_stadt', true));
            $stored_latitude = get_post_meta($post_id, '_feu_einsatz_latitude', true);
            $stored_longitude = get_post_meta($post_id, '_feu_einsatz_longitude', true);
            $address_is_unchanged = $street === $stored_street
                && $house_number === $stored_house_number
                && $plz === $stored_plz
                && $city === $stored_city;

            if (
                $address_is_unchanged
                && is_numeric($stored_latitude)
                && is_numeric($stored_longitude)
            ) {
                $geocoded_data = [
                    'lat' => round((float) $stored_latitude, 6),
                    'lng' => round((float) $stored_longitude, 6),
                    'display_name' => (string) get_post_meta($post_id, '_feu_einsatz_display_address', true),
                ];
            } else {
                $geocoded_data = false;

                if (false && !$geocoded_data) {
                    $errors[] = __('Die angegebene Straße konnte für diese PLZ und Stadt nicht bestätigt werden.', 'feuer-einsatzberichte');
                }
            }
        }

        return [
            'street' => $street,
            'house_number' => $house_number,
            'plz' => $plz,
            'city' => $city,
            'date' => $date_raw,
            'time' => $time_raw,
            'categories' => $selected_categories,
            'errors' => $errors,
            'geocoded_data' => $geocoded_data,
        ];
    }

    private function abort_invalid_report_submission($errors, $context = 'report_validation') {
        $errors = array_values(array_filter(array_map('trim', (array) $errors)));

        if (empty($errors)) {
            $errors = [__('Der Einsatzbericht konnte nicht gespeichert werden.', 'feuer-einsatzberichte')];
        }

        FEU_Einsatz_Logger::log_runtime_error(
            $context,
            __('Einsatzbericht konnte wegen unvollständiger oder ungültiger Pflichtfelder nicht gespeichert werden.', 'feuer-einsatzberichte'),
            [
                'errors' => $errors,
            ]
        );

        wp_die(
            wp_kses_post(implode('<br>', array_map('esc_html', $errors))),
            esc_html__('Pflichtfelder prüfen', 'feuer-einsatzberichte'),
            ['back_link' => true]
        );
    }

    private function get_report_availability_request() {
        $mode = isset($_POST['feu_einsatz_availability_mode']) ? sanitize_key(wp_unslash($_POST['feu_einsatz_availability_mode'])) : 'date';

        if ('plus3' === $mode) {
            $mode = 'plus2';
        }

        if (!in_array($mode, ['sofort', 'date', 'plus2'], true)) {
            $mode = 'date';
        }

        $event_date = isset($_POST['feu_einsatz_datum'])
            ? $this->normalize_report_date_value(sanitize_text_field(wp_unslash($_POST['feu_einsatz_datum'])))
            : '';
        $event_time = isset($_POST['feu_einsatz_uhrzeit'])
            ? $this->normalize_report_time_value(sanitize_text_field(wp_unslash($_POST['feu_einsatz_uhrzeit'])))
            : '08:00';
        $date = isset($_POST['feu_einsatz_available_date'])
            ? $this->normalize_report_date_value(sanitize_text_field(wp_unslash($_POST['feu_einsatz_available_date'])))
            : $event_date;
        $time = isset($_POST['feu_einsatz_available_time'])
            ? $this->normalize_report_time_value(sanitize_text_field(wp_unslash($_POST['feu_einsatz_available_time'])))
            : $event_time;

        return [
            'mode' => $mode,
            'date' => $date,
            'time' => $time,
        ];
    }

    private function get_report_availability_datetime($availability_request) {
        $timezone = wp_timezone();
        $now = new DateTimeImmutable('now', $timezone);
        $mode = isset($availability_request['mode']) ? $availability_request['mode'] : 'date';

        if ('plus2' === $mode) {
            $event_datetime = $this->get_report_event_datetime_from_request();

            if ($event_datetime instanceof DateTimeImmutable) {
                $plus48 = $event_datetime->modify('+48 hours');
                return ($plus48 > $now) ? $plus48 : $now->modify('+48 hours');
            }

            if (!empty($availability_request['date'])) {
                $datetime = DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i',
                    $availability_request['date'] . ' ' . $availability_request['time'],
                    $timezone
                );

                if ($datetime instanceof DateTimeImmutable) {
                    $plus48 = $datetime->modify('+48 hours');
                    return ($plus48 > $now) ? $plus48 : $now->modify('+48 hours');
                }
            }

            return $now->modify('+48 hours');
        }

        if ('date' === $mode) {
            $event_datetime = $this->get_report_event_datetime_from_request();

            if ($event_datetime instanceof DateTimeImmutable) {
                return $event_datetime;
            }

            if (!empty($availability_request['date'])) {
                $datetime = DateTimeImmutable::createFromFormat(
                    'Y-m-d H:i',
                    $availability_request['date'] . ' ' . $availability_request['time'],
                    $timezone
                );

                if ($datetime instanceof DateTimeImmutable) {
                    return $datetime;
                }
            }
        }

        return $now;
    }

    private function store_report_availability_meta($post_id, $availability_request, $availability_datetime) {
        update_post_meta($post_id, '_feu_einsatz_availability_mode', $availability_request['mode']);

        if ($availability_datetime instanceof DateTimeInterface) {
            update_post_meta($post_id, '_feu_einsatz_available_from', $availability_datetime->format('Y-m-d H:i:s'));
            return;
        }

        delete_post_meta($post_id, '_feu_einsatz_available_from');
    }

    private function maybe_apply_report_availability($post_id, $current_status) {
        if (!isset($_POST['feu_einsatz_availability_mode'])) {
            return;
        }

        $availability_request = $this->get_report_availability_request();
        $availability_datetime = $this->get_report_availability_datetime($availability_request);
        $this->store_report_availability_meta($post_id, $availability_request, $availability_datetime);

        if (!in_array($current_status, ['publish', 'future'], true)) {
            return;
        }

        if ('sofort' === $availability_request['mode']) {
            $target_status = 'publish';
            $event_datetime = $this->get_report_event_datetime_from_request($post_id);
            $target_date = $event_datetime instanceof DateTimeImmutable
                ? $event_datetime->format('Y-m-d H:i:s')
                : current_time('mysql');

            if ($current_status === $target_status && $target_date === get_post_field('post_date', $post_id, 'raw')) {
                return;
            }
        } else {
            $now = new DateTimeImmutable('now', wp_timezone());
            $target_date = $availability_datetime->format('Y-m-d H:i:s');
            $target_status = ($availability_datetime > $now) ? 'future' : 'publish';

            if ($current_status === $target_status && $target_date === get_post_field('post_date', $post_id, 'raw')) {
                return;
            }
        }

        remove_action('save_post', [$this, 'save_post_data'], 10);

        wp_update_post([
            'ID' => $post_id,
            'post_status' => $target_status,
            'post_date' => $target_date,
            'post_date_gmt' => get_gmt_from_date($target_date),
        ]);

        add_action('save_post', [$this, 'save_post_data'], 10, 3);
    }

    private function get_and_save_coordinates($post_id, $strasse, $plz = '', $stadt = 'Hamburg', $hausnummer = '') {
        $geocoded_data = $this->request_geocoded_address_data($strasse, $plz, $stadt, $hausnummer);

        if (!$geocoded_data) {
            return false;
        }

        update_post_meta($post_id, '_feu_einsatz_latitude', (string) $geocoded_data['lat']);
        update_post_meta($post_id, '_feu_einsatz_longitude', (string) $geocoded_data['lng']);

        if (!empty($geocoded_data['display_name'])) {
            update_post_meta($post_id, '_feu_einsatz_display_address', $geocoded_data['display_name']);
        }

        return [
            'lat' => (float) $geocoded_data['lat'],
            'lng' => (float) $geocoded_data['lng'],
        ];
    }

    private function save_einsatz_details($post_id, $geocoded_data = null) {
        $fields = ['strasse', 'hausnummer', 'plz', 'stadt', 'datum', 'uhrzeit'];
        $previous_address_parts = [
            'strasse' => (string) get_post_meta($post_id, '_feu_einsatz_strasse', true),
            'hausnummer' => (string) get_post_meta($post_id, '_feu_einsatz_hausnummer', true),
            'plz' => (string) get_post_meta($post_id, '_feu_einsatz_plz', true),
            'stadt' => (string) get_post_meta($post_id, '_feu_einsatz_stadt', true),
        ];
        $updated_address_parts = $previous_address_parts;

        foreach ($fields as $field) {
            if (isset($_POST['feu_einsatz_' . $field])) {
                $sanitized_value = sanitize_text_field(wp_unslash($_POST['feu_einsatz_' . $field]));

                if ('datum' === $field) {
                    $sanitized_value = $this->normalize_report_date_value($sanitized_value);
                }

                update_post_meta(
                    $post_id,
                    '_feu_einsatz_' . $field,
                    $sanitized_value
                );

                if (isset($updated_address_parts[$field])) {
                    $updated_address_parts[$field] = $sanitized_value;
                }
            }
        }

        update_post_meta($post_id, '_feu_einsatz_comments_enabled', isset($_POST['feu_einsatz_comments_enabled']) ? '1' : '0');

        $previous_address = $this->build_full_address(
            $previous_address_parts['strasse'],
            $previous_address_parts['plz'],
            $previous_address_parts['stadt'],
            $previous_address_parts['hausnummer']
        );
        $updated_address = $this->build_full_address(
            $updated_address_parts['strasse'],
            $updated_address_parts['plz'],
            $updated_address_parts['stadt'],
            $updated_address_parts['hausnummer']
        );

        $latitude = isset($_POST['feu_einsatz_latitude'])
            ? $this->sanitize_coordinate_value(wp_unslash($_POST['feu_einsatz_latitude']), 'lat')
            : '';
        $longitude = isset($_POST['feu_einsatz_longitude'])
            ? $this->sanitize_coordinate_value(wp_unslash($_POST['feu_einsatz_longitude']), 'lng')
            : '';

        if ('' !== $latitude && '' !== $longitude) {
            update_post_meta($post_id, '_feu_einsatz_latitude', $latitude);
            update_post_meta($post_id, '_feu_einsatz_longitude', $longitude);
        } elseif (
            is_array($geocoded_data)
            && isset($geocoded_data['lat'], $geocoded_data['lng'])
            && is_numeric($geocoded_data['lat'])
            && is_numeric($geocoded_data['lng'])
        ) {
            update_post_meta($post_id, '_feu_einsatz_latitude', (string) round((float) $geocoded_data['lat'], 6));
            update_post_meta($post_id, '_feu_einsatz_longitude', (string) round((float) $geocoded_data['lng'], 6));
        } elseif ('' === $latitude && '' === $longitude && $previous_address !== $updated_address) {
            delete_post_meta($post_id, '_feu_einsatz_latitude');
            delete_post_meta($post_id, '_feu_einsatz_longitude');
        }

        if (
            is_array($geocoded_data)
            && !empty($geocoded_data['display_name'])
        ) {
            update_post_meta($post_id, '_feu_einsatz_display_address', sanitize_text_field((string) $geocoded_data['display_name']));
        } elseif ($previous_address !== $updated_address) {
            delete_post_meta($post_id, '_feu_einsatz_display_address');
        }
    }

    public function rebuild_statistics_cache() {
        if (!self::current_user_can_access_plugin_section('statistics')) {
            wp_die(esc_html__('Keine Berechtigung', 'feuer-einsatzberichte'));
        }

        if (!method_exists($this->db, 'rebuild_all_statistics')) {
            wp_die(esc_html__('Statistik-Rebuild ist aktuell nicht verfügbar.', 'feuer-einsatzberichte'));
        }

        $this->db->rebuild_all_statistics();

        wp_redirect(admin_url('admin.php?page=feu-einsatz-statistiken&cache_rebuilt=1'));
        exit;
    }

    public function clear_all_street_cache_storage() {
        if (!self::current_user_can_access_plugin_section('settings')) {
            return false;
        }

        FEU_Einsatz_Street_Cache::clear_all();
        FEU_Einsatz_Street_Cache::clear_all_post_caches();
        wp_cache_flush();

        return true;
    }

    private function get_generated_map_rebuild_candidate_post_ids($filters = []) {
        $filters = $this->normalize_generated_map_rebuild_filters($filters);
        $query = new WP_Query([
            'post_type' => 'post',
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                [
                    'key' => '_feu_einsatz_einsatzbericht',
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
        ]);

        if (empty($query->posts) || !is_array($query->posts)) {
            return [];
        }

        $candidate_ids = [];

        foreach ($query->posts as $post_id) {
            $post_id = absint($post_id);

            if (!$post_id) {
                continue;
            }

            $generated_thumbnail_id = $this->get_generated_map_thumbnail_id($post_id);
            $has_generated_preview = '' !== trim((string) get_post_meta($post_id, self::GENERATED_MAP_PREVIEW_URL_META, true));

            if (!($generated_thumbnail_id > 0 || $has_generated_preview)) {
                continue;
            }

            if ('' !== $filters['date_from'] || '' !== $filters['date_to']) {
                $reference_date = $this->get_generated_map_rebuild_reference_date($post_id);

                if ('' === $reference_date) {
                    continue;
                }

                if ('' !== $filters['date_from'] && $reference_date < $filters['date_from']) {
                    continue;
                }

                if ('' !== $filters['date_to'] && $reference_date > $filters['date_to']) {
                    continue;
                }
            }

            $candidate_ids[] = $post_id;
        }

        return array_values(array_unique($candidate_ids));
    }

    public function count_generated_map_rebuild_candidates() {
        if (!self::current_user_can_access_plugin_section('settings')) {
            return 0;
        }

        return count($this->get_generated_map_rebuild_candidate_post_ids());
    }

    private function sanitize_generated_map_rebuild_date($value) {
        $value = trim((string) $value);

        if ('' === $value) {
            return '';
        }

        return $this->normalize_report_date_value($value);
    }

    private function normalize_generated_map_rebuild_filters($filters = []) {
        $filters = is_array($filters) ? $filters : [];
        $date_from = $this->sanitize_generated_map_rebuild_date($filters['date_from'] ?? '');
        $date_to = $this->sanitize_generated_map_rebuild_date($filters['date_to'] ?? '');

        if ('' !== $date_from && '' !== $date_to && strtotime($date_from) > strtotime($date_to)) {
            [$date_from, $date_to] = [$date_to, $date_from];
        }

        return [
            'date_from' => $date_from,
            'date_to' => $date_to,
        ];
    }

    private function get_generated_map_rebuild_reference_date($post_id) {
        $post_id = absint($post_id);

        if (!$post_id) {
            return '';
        }

        $report_date = $this->normalize_report_date_value((string) get_post_meta($post_id, '_feu_einsatz_datum', true));

        if ('' !== $report_date) {
            return $report_date;
        }

        $post_date = (string) get_post_field('post_date', $post_id, 'raw');

        if ('' === trim($post_date) || '0000-00-00 00:00:00' === $post_date) {
            return '';
        }

        $timestamp = strtotime($post_date);

        return false !== $timestamp ? gmdate('Y-m-d', $timestamp + (int) (get_option('gmt_offset', 0) * HOUR_IN_SECONDS)) : '';
    }

    private function get_generated_map_generation_delay_seconds() {
        return 0;
    }

    private function get_generated_map_recovery_delay_seconds() {
        return 60;
    }

    private function get_generated_map_recovery_attempt_limit() {
        return 2;
    }

    private function get_background_geocode_delay_seconds() {
        return 0;
    }

    private function build_generated_map_signature($strasse, $plz = '', $stadt = 'Hamburg') {
        $display_street = FEU_Einsatz_Template_Helpers::strip_house_number_from_street((string) $strasse);
        $display_street = trim((string) $display_street);
        $plz = trim((string) $plz);
        $stadt = trim((string) $stadt);

        if ('' === $display_street) {
            return '';
        }

        if ('' === $stadt) {
            $stadt = 'Hamburg';
        }

        return hash('sha256', wp_json_encode([
            'street' => $display_street,
            'plz' => $plz,
            'city' => $stadt,
            'map_style' => [
                'zoom' => (int) get_option('feu_einsatz_map_zoom', 16),
                'heading' => $this->get_map_preview_heading(),
                'show_panel' => $this->is_map_preview_info_panel_enabled(),
                'show_panel_heading' => $this->is_map_preview_panel_heading_enabled(),
                'show_panel_address' => $this->is_map_preview_panel_address_enabled(),
                'panel_position' => $this->get_map_preview_panel_position(),
                'show_street_label' => $this->is_map_preview_street_label_enabled(),
                'street_label_prefix' => $this->get_map_preview_street_label_prefix(),
                'street_label_position' => $this->get_map_preview_street_label_position(),
                'show_attribution' => $this->is_map_preview_attribution_enabled(),
                'attribution_text' => $this->get_map_preview_attribution_text(),
                'attribution_position' => $this->get_map_preview_attribution_position(),
                'highlight_color' => $this->get_map_preview_highlight_hex_color(),
                'stroke_width' => $this->get_map_preview_stroke_width(),
                'font_family' => $this->get_map_preview_font_family(),
            ],
        ]));
    }

    private function has_generated_map_asset($post_id) {
        $post_id = absint($post_id);

        if (!$post_id) {
            return false;
        }

        $preview_file = trim((string) get_post_meta($post_id, self::GENERATED_MAP_PREVIEW_FILE_META, true));
        if ('' !== $preview_file && file_exists($preview_file)) {
            return true;
        }

        if ('' !== FEU_Einsatz_Template_Helpers::get_generated_map_preview_public_url($post_id)) {
            return true;
        }

        $generated_thumbnail_id = $this->get_generated_map_thumbnail_id($post_id);

        return $generated_thumbnail_id > 0 && get_post($generated_thumbnail_id) instanceof WP_Post;
    }

    private function has_current_generated_map_signature($post_id, $strasse, $plz = '', $stadt = 'Hamburg') {
        $post_id = absint($post_id);
        $expected_signature = $this->build_generated_map_signature($strasse, $plz, $stadt);
        $stored_signature = trim((string) get_post_meta($post_id, self::GENERATED_MAP_SIGNATURE_META, true));
        $has_generated_asset = $this->has_generated_map_asset($post_id);

        if (!$post_id || '' === $expected_signature || !$has_generated_asset) {
            return false;
        }

        if ('' === $stored_signature) {
            update_post_meta($post_id, self::GENERATED_MAP_SIGNATURE_META, $expected_signature);
            return true;
        }

        return hash_equals($stored_signature, $expected_signature);
    }

    private function maybe_queue_generated_map_preview_generation($post_id, $strasse, $plz = '', $stadt = 'Hamburg', $force = false, $delay_seconds = null, $reason = 'save') {
        $post_id = absint($post_id);
        $strasse = trim((string) $strasse);
        $plz = trim((string) $plz);
        $stadt = trim((string) $stadt);
        $force = !empty($force);

        if (!$post_id || '' === $strasse) {
            return false;
        }

        if ('' === $stadt) {
            $stadt = 'Hamburg';
        }

        $signature = $this->build_generated_map_signature($strasse, $plz, $stadt);

        if (!$force && $this->has_current_generated_map_signature($post_id, $strasse, $plz, $stadt)) {
            $this->clear_generated_map_queue($post_id);
            $this->maybe_release_deferred_publication($post_id);
            $this->update_generated_map_status(
                $post_id,
                'ready',
                [
                    'stage' => 'map',
                    'message' => __('Kartenbild ist bereits aktuell.', 'feuer-einsatzberichte'),
                    'completed_at' => time(),
                ]
            );

            return false;
        }

        $queue_payload = get_post_meta($post_id, self::GENERATED_MAP_QUEUE_META, true);
        $queue_payload = is_array($queue_payload) ? $queue_payload : [];
        $is_locked = $this->is_generated_map_process_locked($post_id);
        $has_matching_queue = !$force
            && '' !== $signature
            && !empty($queue_payload['signature'])
            && hash_equals((string) $queue_payload['signature'], $signature);

        if ($has_matching_queue && ($is_locked || wp_next_scheduled(self::GENERATED_MAP_BACKGROUND_HOOK, [$post_id]))) {
            return false;
        }

        return $this->queue_map_preview_generation($post_id, $strasse, $plz, $stadt, $force, $delay_seconds, $reason);
    }

    private function clear_generated_map_publish_hold($post_id) {
        delete_post_meta(absint($post_id), self::GENERATED_MAP_PUBLISH_HOLD_META);
    }

    private function should_defer_publication_until_map_ready($target_status, $street, $post_id = 0, $is_new_report = false) {
        $target_status = sanitize_key((string) $target_status);
        $street = trim((string) $street);
        $post_id = absint($post_id);

        if (!in_array($target_status, ['publish', 'future'], true)) {
            return false;
        }

        if (1 !== (int) get_option('feu_einsatz_auto_map_image', 1)) {
            return false;
        }

        if ('' === $street) {
            return false;
        }

        if (
            $post_id > 0
            && $this->has_current_generated_map_signature(
                $post_id,
                $street,
                (string) get_post_meta($post_id, '_feu_einsatz_plz', true),
                (string) get_post_meta($post_id, '_feu_einsatz_stadt', true)
            )
        ) {
            return false;
        }

        if (!$is_new_report && 'publish' === get_post_status($post_id)) {
            return false;
        }

        return true;
    }

    private function store_generated_map_publish_hold($post_id, $target_status, array $postarr = []) {
        $post_id = absint($post_id);
        $target_status = sanitize_key((string) $target_status);

        if (!$post_id || !in_array($target_status, ['publish', 'future'], true)) {
            $this->clear_generated_map_publish_hold($post_id);
            return;
        }

        update_post_meta($post_id, self::GENERATED_MAP_PUBLISH_HOLD_META, [
            'target_status' => $target_status,
            'post_date' => isset($postarr['post_date']) ? (string) $postarr['post_date'] : '',
            'post_date_gmt' => isset($postarr['post_date_gmt']) ? (string) $postarr['post_date_gmt'] : '',
            'created_at' => time(),
        ]);
    }

    private function maybe_release_deferred_publication($post_id) {
        $post_id = absint($post_id);

        if (!$post_id) {
            return false;
        }

        $hold = get_post_meta($post_id, self::GENERATED_MAP_PUBLISH_HOLD_META, true);

        if (!is_array($hold) || empty($hold['target_status'])) {
            return false;
        }

        $post = get_post($post_id);

        if (!($post instanceof WP_Post) || 'trash' === $post->post_status) {
            $this->clear_generated_map_publish_hold($post_id);
            return false;
        }

        $target_status = sanitize_key((string) $hold['target_status']);

        if (!in_array($target_status, ['publish', 'future'], true)) {
            $this->clear_generated_map_publish_hold($post_id);
            return false;
        }

        $street = (string) get_post_meta($post_id, '_feu_einsatz_strasse', true);
        $plz = (string) get_post_meta($post_id, '_feu_einsatz_plz', true);
        $city = (string) get_post_meta($post_id, '_feu_einsatz_stadt', true);

        if (!$this->has_current_generated_map_signature($post_id, $street, $plz, $city)) {
            return false;
        }

        $postarr = [
            'ID' => $post_id,
            'post_status' => $target_status,
        ];

        $post_date = isset($hold['post_date']) ? trim((string) $hold['post_date']) : '';
        $post_date_gmt = isset($hold['post_date_gmt']) ? trim((string) $hold['post_date_gmt']) : '';

        if ('' !== $post_date) {
            $postarr['post_date'] = $post_date;
            $postarr['post_date_gmt'] = '' !== $post_date_gmt ? $post_date_gmt : get_gmt_from_date($post_date);
        }

        if ('future' === $target_status && '' !== $post_date) {
            $scheduled_timestamp = strtotime($postarr['post_date_gmt'] . ' GMT');

            if (false === $scheduled_timestamp || $scheduled_timestamp <= time()) {
                $postarr['post_status'] = 'publish';
            }
        }

        remove_action('save_post', [$this, 'save_post_data'], 10);
        $result = wp_update_post($postarr, true);
        add_action('save_post', [$this, 'save_post_data'], 10, 3);

        if (is_wp_error($result)) {
            FEU_Einsatz_Logger::log_runtime_error(
                'report_publish_hold_release_failed',
                __('Der Einsatzbericht konnte nach Karten-Generierung nicht freigegeben werden.', 'feuer-einsatzberichte'),
                [
                    'post_id' => $post_id,
                    'message' => $result->get_error_message(),
                ]
            );
            return false;
        }

        $this->clear_generated_map_publish_hold($post_id);

        return true;
    }

    private function get_generated_map_status_payload($post_id) {
        $payload = get_post_meta(absint($post_id), self::GENERATED_MAP_STATUS_META, true);

        return is_array($payload) ? $payload : [];
    }

    private function update_generated_map_status($post_id, $status, $data = []) {
        $post_id = absint($post_id);
        $status = sanitize_key((string) $status);

        if (!$post_id) {
            return;
        }

        if ('idle' === $status || '' === $status) {
            delete_post_meta($post_id, self::GENERATED_MAP_STATUS_META);
            return;
        }

        $payload = array_merge(
            $this->get_generated_map_status_payload($post_id),
            is_array($data) ? $data : []
        );

        $payload['status'] = $status;
        $payload['updated_at'] = time();

        update_post_meta($post_id, self::GENERATED_MAP_STATUS_META, $payload);
    }

    private function clear_background_geocode_schedule($post_id) {
        $post_id = absint($post_id);

        if ($post_id > 0) {
            wp_clear_scheduled_hook(self::BACKGROUND_GEOCODE_HOOK, [$post_id]);
        }
    }

    private function clear_generated_map_queue($post_id, $clear_status = false) {
        $post_id = absint($post_id);

        if (!$post_id) {
            return;
        }

        delete_post_meta($post_id, self::GENERATED_MAP_QUEUE_META);
        wp_clear_scheduled_hook(self::GENERATED_MAP_BACKGROUND_HOOK, [$post_id]);
        $this->release_generated_map_process_lock($post_id);

        if ($clear_status) {
            delete_post_meta($post_id, self::GENERATED_MAP_STATUS_META);
        }
    }

    private function schedule_background_geocode($post_id, $delay_seconds = null) {
        $post_id = absint($post_id);

        if (!$post_id) {
            return 0;
        }

        $delay_seconds = null === $delay_seconds ? $this->get_background_geocode_delay_seconds() : absint($delay_seconds);
        $scheduled_for = time() + max(0, $delay_seconds);

        wp_clear_scheduled_hook(self::BACKGROUND_GEOCODE_HOOK, [$post_id]);
        wp_schedule_single_event($scheduled_for, self::BACKGROUND_GEOCODE_HOOK, [$post_id]);
        $this->update_generated_map_status(
            $post_id,
            'queued',
            [
                'stage' => 'geocode',
                'message' => __('Adresse wird im Hintergrund vorbereitet. Das Kartenbild folgt automatisch.', 'feuer-einsatzberichte'),
                'scheduled_for' => $scheduled_for,
                'reason' => 'background_geocode',
            ]
        );

        if ($delay_seconds <= 60 && function_exists('spawn_cron')) {
            spawn_cron(time());
        }

        return $scheduled_for;
    }

    private function maybe_recover_generated_map_schedule($post_id, $queue_payload = []) {
        $post_id = absint($post_id);

        if (!$post_id || !is_array($queue_payload) || empty($queue_payload['street'])) {
            return 0;
        }

        $next_scheduled = wp_next_scheduled(self::GENERATED_MAP_BACKGROUND_HOOK, [$post_id]);
        $now = time();

        if ($next_scheduled && $next_scheduled >= ($now - 120)) {
            return (int) $next_scheduled;
        }

        $attempts = !empty($queue_payload['recovery_attempts']) ? absint($queue_payload['recovery_attempts']) : 0;
        $queued_at = !empty($queue_payload['queued_at']) ? absint($queue_payload['queued_at']) : $now;

        if ($attempts >= $this->get_generated_map_recovery_attempt_limit() && ($now - $queued_at) >= 120) {
            wp_clear_scheduled_hook(self::GENERATED_MAP_BACKGROUND_HOOK, [$post_id]);
            $this->update_generated_map_status(
                $post_id,
                'error',
                [
                    'stage' => 'map',
                    'message' => __('Die Hintergrund-Generierung wurde mehrfach eingeplant, aber nicht ausgefuehrt. Bitte manuell anstossen oder WP-Cron pruefen.', 'feuer-einsatzberichte'),
                    'scheduled_for' => 0,
                    'reason' => 'cron_stalled',
                ]
            );

            return -1;
        }

        $scheduled_for = $now + $this->get_generated_map_recovery_delay_seconds();

        wp_clear_scheduled_hook(self::GENERATED_MAP_BACKGROUND_HOOK, [$post_id]);
        wp_schedule_single_event($scheduled_for, self::GENERATED_MAP_BACKGROUND_HOOK, [$post_id]);

        $queue_payload['queued_at'] = isset($queue_payload['queued_at']) ? absint($queue_payload['queued_at']) : $now;
        $queue_payload['scheduled_for'] = $scheduled_for;
        $queue_payload['reason'] = 'recovery';
        $queue_payload['recovery_attempts'] = $attempts + 1;
        update_post_meta($post_id, self::GENERATED_MAP_QUEUE_META, $queue_payload);

        $this->update_generated_map_status(
            $post_id,
            'queued',
            [
                'stage' => 'map',
                'message' => __('Die Karten-Generierung wurde erneut im Hintergrund eingeplant.', 'feuer-einsatzberichte'),
                'scheduled_for' => $scheduled_for,
                'reason' => 'recovery',
            ]
        );

        if (function_exists('spawn_cron')) {
            spawn_cron(time());
        }

        return $scheduled_for;
    }

    private function maybe_recover_missing_generated_map_queue($post_id, $status_payload = []) {
        $post_id = absint($post_id);

        if (!$post_id || $this->has_generated_map_asset($post_id)) {
            return false;
        }

        if (1 !== (int) get_option('feu_einsatz_auto_map_image', 1)) {
            return false;
        }

        if (get_post_meta($post_id, self::GENERATED_MAP_QUEUE_META, true)) {
            return false;
        }

        if (wp_next_scheduled(self::GENERATED_MAP_BACKGROUND_HOOK, [$post_id])) {
            return false;
        }

        $street = trim((string) get_post_meta($post_id, '_feu_einsatz_strasse', true));
        $plz = trim((string) get_post_meta($post_id, '_feu_einsatz_plz', true));
        $city = trim((string) get_post_meta($post_id, '_feu_einsatz_stadt', true));
        $publish_hold = get_post_meta($post_id, self::GENERATED_MAP_PUBLISH_HOLD_META, true);
        $status_key = !empty($status_payload['status']) ? sanitize_key((string) $status_payload['status']) : '';
        $stage = !empty($status_payload['stage']) ? sanitize_key((string) $status_payload['stage']) : '';

        if ('' === $street) {
            return false;
        }

        if (
            !is_array($publish_hold)
            && !('queued' === $status_key && in_array($stage, ['geocode', 'map'], true))
        ) {
            return false;
        }

        return $this->maybe_queue_generated_map_preview_generation(
            $post_id,
            $street,
            $plz,
            $city,
            true,
            $this->get_generated_map_generation_delay_seconds(),
            'status_recovery'
        );
    }

    public function get_generated_map_admin_status($post_id) {
        $post_id = absint($post_id);

        if (!$post_id) {
            return [
                'status' => 'idle',
                'class_name' => '',
                'message' => '',
                'scheduled_for' => 0,
                'scheduled_for_display' => '',
            ];
        }

        $queue_payload = get_post_meta($post_id, self::GENERATED_MAP_QUEUE_META, true);
        $queue_payload = is_array($queue_payload) ? $queue_payload : [];
        $status_payload = $this->get_generated_map_status_payload($post_id);
        $lock_active = $this->is_generated_map_process_locked($post_id);
        $has_generated_asset = $this->has_generated_map_asset($post_id);
        $scheduled_for = 0;
        $status = 'idle';
        $message = '';
        $stage = isset($status_payload['stage']) ? sanitize_key((string) $status_payload['stage']) : '';

        if ($lock_active) {
            $status = 'processing';
            $message = __('Kartenbild wird gerade im Hintergrund erstellt.', 'feuer-einsatzberichte');
        } elseif (empty($queue_payload) && $has_generated_asset) {
            if (!empty($status_payload)) {
                delete_post_meta($post_id, self::GENERATED_MAP_STATUS_META);
            }

            $status = 'ready';
            $message = __('Kartenbild ist aktuell.', 'feuer-einsatzberichte');
        } elseif (
            empty($queue_payload)
            && !empty($status_payload['status'])
            && 'geocode' === $stage
            && 'queued' === sanitize_key((string) $status_payload['status'])
            && absint(wp_next_scheduled(self::BACKGROUND_GEOCODE_HOOK, [$post_id])) > 0
        ) {
            $status = 'queued';
            $scheduled_for = absint(wp_next_scheduled(self::BACKGROUND_GEOCODE_HOOK, [$post_id]));
            $message = __('Adresse wird im Hintergrund vorbereitet. Das Kartenbild folgt automatisch.', 'feuer-einsatzberichte');
        } elseif (!empty($queue_payload)) {
            $queue_street = isset($queue_payload['street']) ? (string) $queue_payload['street'] : '';
            $queue_plz = isset($queue_payload['plz']) ? (string) $queue_payload['plz'] : '';
            $queue_city = isset($queue_payload['city']) ? (string) $queue_payload['city'] : '';

            if ($this->has_current_generated_map_signature($post_id, $queue_street, $queue_plz, $queue_city)) {
                $this->clear_generated_map_queue($post_id);
                $status = 'ready';
                $message = __('Kartenbild ist aktuell.', 'feuer-einsatzberichte');
            } else {
                $scheduled_for = $this->maybe_recover_generated_map_schedule($post_id, $queue_payload);

                if ($scheduled_for < 0) {
                    $status_payload = $this->get_generated_map_status_payload($post_id);
                    $status = 'error';
                    $message = isset($status_payload['message']) ? trim((string) $status_payload['message']) : '';
                    $scheduled_for = 0;
                } else {
                    if (!$scheduled_for) {
                        $scheduled_for = absint(wp_next_scheduled(self::GENERATED_MAP_BACKGROUND_HOOK, [$post_id]));
                    }

                    if (!$scheduled_for && !empty($queue_payload['scheduled_for'])) {
                        $scheduled_for = absint($queue_payload['scheduled_for']);
                    }

                    $status = 'queued';
                    $stage = !empty($status_payload['stage']) ? sanitize_key((string) $status_payload['stage']) : 'map';
                    $message = 'geocode' === $stage
                        ? __('Adresse wird im Hintergrund vorbereitet. Das Kartenbild folgt automatisch.', 'feuer-einsatzberichte')
                        : __('Kartenbild wird im Hintergrund erstellt.', 'feuer-einsatzberichte');
                }
            }
        } elseif ($this->maybe_recover_missing_generated_map_queue($post_id, $status_payload)) {
            $queue_payload = get_post_meta($post_id, self::GENERATED_MAP_QUEUE_META, true);
            $scheduled_for = is_array($queue_payload) && !empty($queue_payload['scheduled_for'])
                ? absint($queue_payload['scheduled_for'])
                : absint(wp_next_scheduled(self::GENERATED_MAP_BACKGROUND_HOOK, [$post_id]));
            $status = 'queued';
            $stage = 'map';
            $message = __('Kartenbild wird im Hintergrund erstellt.', 'feuer-einsatzberichte');
        } elseif (!empty($status_payload['status'])) {
            $status = sanitize_key((string) $status_payload['status']);
            $message = isset($status_payload['message']) ? trim((string) $status_payload['message']) : '';
            $scheduled_for = !empty($status_payload['scheduled_for']) ? absint($status_payload['scheduled_for']) : 0;
            $stage = isset($status_payload['stage']) ? sanitize_key((string) $status_payload['stage']) : '';
        }

        if ('queued' === $status && $scheduled_for > 0) {
            $message .= ' ' . sprintf(
                __('Geplant fuer %s.', 'feuer-einsatzberichte'),
                wp_date(get_option('date_format') . ' ' . get_option('time_format'), $scheduled_for)
            );
        } elseif ('error' === $status && '' === $message) {
            $message = __('Kartenbild konnte nicht automatisch erstellt werden. Bitte erneut anstossen.', 'feuer-einsatzberichte');
        } elseif ('ready' === $status && '' === $message) {
            $message = __('Kartenbild ist aktuell.', 'feuer-einsatzberichte');
        } elseif ('processing' === $status && 'geocode' === $stage) {
            $message = __('Adresse wird gerade im Hintergrund vorbereitet.', 'feuer-einsatzberichte');
        }

        $publish_hold = get_post_meta($post_id, self::GENERATED_MAP_PUBLISH_HOLD_META, true);

        if (
            is_array($publish_hold)
            && !empty($publish_hold['target_status'])
            && in_array($status, ['queued', 'processing', 'error'], true)
        ) {
            $message = trim($message . ' ' . __('Der Bericht wird erst nach erfolgreicher Kartenbild-Generierung veroeffentlicht.', 'feuer-einsatzberichte'));
        }

        return [
            'status' => $status,
            'class_name' => 'feu-einsatz-map-generation-status feu-einsatz-map-generation-status--' . $status,
            'message' => trim($message),
            'scheduled_for' => $scheduled_for,
            'scheduled_for_display' => $scheduled_for > 0
                ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $scheduled_for)
                : '',
        ];
    }

    private function schedule_generated_map_rebuild_for_post($post_id, $delay_seconds = 5, $reason = 'bulk_privacy_rebuild') {
        $post_id = absint($post_id);

        if (!$post_id) {
            return false;
        }

        $strasse = trim((string) get_post_meta($post_id, '_feu_einsatz_strasse', true));
        $plz = trim((string) get_post_meta($post_id, '_feu_einsatz_plz', true));
        $stadt = trim((string) get_post_meta($post_id, '_feu_einsatz_stadt', true));

        if ('' === $strasse) {
            return false;
        }

        if ('' === $stadt) {
            $stadt = 'Hamburg';
        }

        return $this->queue_map_preview_generation(
            $post_id,
            $strasse,
            $plz,
            $stadt,
            true,
            max(5, absint($delay_seconds)),
            $reason
        );
    }

    public function queue_bulk_generated_map_rebuild($filters = []) {
        if (!self::current_user_can_access_plugin_section('settings')) {
            return [
                'candidates' => 0,
                'scheduled' => 0,
                'skipped' => 0,
                'filters' => [
                    'date_from' => '',
                    'date_to' => '',
                ],
            ];
        }

        $filters = $this->normalize_generated_map_rebuild_filters($filters);
        $candidate_ids = $this->get_generated_map_rebuild_candidate_post_ids($filters);
        $scheduled = 0;
        $skipped = 0;
        $reason = ('' !== $filters['date_from'] || '' !== $filters['date_to'])
            ? 'bulk_privacy_rebuild_period'
            : 'bulk_privacy_rebuild';

        foreach ($candidate_ids as $post_id) {
            $delay_seconds = 5 + ($scheduled * 5);

            if ($this->schedule_generated_map_rebuild_for_post($post_id, $delay_seconds, $reason)) {
                $scheduled++;
            } else {
                $skipped++;
            }
        }

        if ($scheduled > 0 && function_exists('spawn_cron')) {
            spawn_cron(time());
        }

        return [
            'candidates' => count($candidate_ids),
            'scheduled' => $scheduled,
            'skipped' => $skipped,
            'filters' => $filters,
        ];
    }

    private function save_teilnehmer($post_id) {
        $existing_teilnehmer = get_post_meta($post_id, '_feu_einsatz_teilnehmer', true);
        if (!is_array($existing_teilnehmer)) {
            $existing_teilnehmer = [];
        }
        $all_functions = get_option('feu_einsatz_functions', FEU_Einsatz_Installer::get_default_functions());
        $existing_ids = array_map('intval', array_column($existing_teilnehmer, 'id'));

        if (!isset($_POST['feu_einsatz_teilnehmer']) || !is_array($_POST['feu_einsatz_teilnehmer'])) {
            delete_post_meta($post_id, '_feu_einsatz_teilnehmer');
            $this->db->save_participant_stats($post_id, []);
            return;
        }

        $raw_teilnehmer = wp_unslash($_POST['feu_einsatz_teilnehmer']);
        $unique_teilnehmer = [];

        foreach ($raw_teilnehmer as $item) {
            $id = isset($item['id']) ? absint($item['id']) : 0;
            if (!$id) {
                continue;
            }

            $participant = $this->db->get_participant($id);
            if (!$participant) {
                continue;
            }

            $is_locked_for_new_assignment = (!empty($participant->is_archived) || !empty($participant->is_deleted))
                && !in_array($id, $existing_ids, true);

            if ($is_locked_for_new_assignment) {
                continue;
            }

            $funktion = isset($item['funktion']) ? sanitize_text_field($item['funktion']) : '';
            $participant_defaults = !empty($participant->default_functions) && is_array($participant->default_functions)
                ? $participant->default_functions
                : [];
            $funktion = FEU_Einsatz_Installer::resolve_assignment_function($funktion, $participant_defaults, $all_functions);

            $unique_teilnehmer[$id] = [
                'id' => $id,
                'funktion' => $funktion,
            ];
        }

        $teilnehmer = array_values($unique_teilnehmer);

        update_post_meta($post_id, '_feu_einsatz_teilnehmer', $teilnehmer);
        $this->db->save_participant_stats($post_id, $teilnehmer);
    }

    private function save_organisationen($post_id) {
        $existing_organisationen = get_post_meta($post_id, '_feu_einsatz_organisationen', true);
        if (!is_array($existing_organisationen)) {
            $existing_organisationen = [];
        }
        $existing_ids = array_map('intval', $existing_organisationen);

        if (!isset($_POST['feu_einsatz_organisationen']) || !is_array($_POST['feu_einsatz_organisationen'])) {
            delete_post_meta($post_id, '_feu_einsatz_organisationen');
            return;
        }

        $raw_organisationen = array_values(array_filter(array_map('absint', wp_unslash($_POST['feu_einsatz_organisationen']))));
        $unique_organisationen = [];

        foreach ($raw_organisationen as $organization_id) {
            $organization = $this->db->get_organization($organization_id);

            if (!$organization) {
                continue;
            }

            $is_locked_for_new_assignment = !empty($organization->is_archived)
                && !in_array($organization_id, $existing_ids, true);

            if ($is_locked_for_new_assignment) {
                continue;
            }

            $unique_organisationen[$organization_id] = $organization_id;
        }

        $organisationen = array_values($unique_organisationen);
        $this->db->save_einsatz_organizations($post_id, $organisationen);
    }

    private function save_gallery($post_id) {
        $old_gallery_ids = (array) get_post_meta($post_id, '_feu_einsatz_gallery', true);

        if (!isset($_POST['feu_einsatz_gallery_ids'])) {
            delete_post_meta($post_id, '_feu_einsatz_gallery');
            $this->maybe_remove_gallery_thumbnail($post_id, $old_gallery_ids);
            return [];
        }

        $gallery_ids = explode(',', sanitize_text_field(wp_unslash($_POST['feu_einsatz_gallery_ids'])));
        $gallery_ids = array_values(array_unique(array_filter(array_map('absint', $gallery_ids))));
        $gallery_ids = array_values(array_filter($gallery_ids, static function ($attachment_id) {
            return 'attachment' === get_post_type($attachment_id)
                && 0 === strpos((string) get_post_mime_type($attachment_id), 'image/')
                && current_user_can('edit_post', $attachment_id);
        }));

        if (empty($gallery_ids)) {
            delete_post_meta($post_id, '_feu_einsatz_gallery');
            $this->maybe_remove_gallery_thumbnail($post_id, $old_gallery_ids);
            return [];
        }

        update_post_meta($post_id, '_feu_einsatz_gallery', $gallery_ids);
        $this->maybe_remove_gallery_thumbnail($post_id, $gallery_ids);

        if (class_exists('FEU_Einsatz_Image_Protection')) {
            FEU_Einsatz_Image_Protection::prime_attachment_cache($gallery_ids);
        }

        return $gallery_ids;
    }

    private function maybe_remove_gallery_thumbnail($post_id, array $gallery_ids) {
        if (empty($gallery_ids)) {
            return;
        }

        $current_thumbnail_id = (int) get_post_thumbnail_id($post_id);

        if ($current_thumbnail_id > 0 && in_array($current_thumbnail_id, $gallery_ids, true)) {
            delete_post_thumbnail($post_id);
        }
    }

    private function maybe_prime_street_geometry_cache($post_id, $strasse = '') {
        $strasse = trim((string) $strasse);

        if ('' === $strasse) {
            $strasse = trim((string) get_post_meta($post_id, '_feu_einsatz_strasse', true));
        }

        if ('' === $strasse) {
            return false;
        }

        return (bool) $this->get_map_preview_geometry($post_id);
    }

    private function is_pedestrian_map_preview_highway($highway) {
        return in_array(
            sanitize_key((string) $highway),
            ['pedestrian', 'footway', 'path', 'steps', 'corridor', 'cycleway', 'track'],
            true
        );
    }

    private function get_map_preview_segment_points($segment) {
        if (is_array($segment) && isset($segment['points']) && is_array($segment['points'])) {
            $segment = $segment['points'];
        }

        $points = [];

        foreach ((array) $segment as $point) {
            if (!is_array($point) || !isset($point[0], $point[1]) || !is_numeric($point[0]) || !is_numeric($point[1])) {
                continue;
            }

            $candidate = [round((float) $point[0], 6), round((float) $point[1], 6)];
            $last_point = !empty($points) ? $points[count($points) - 1] : null;

            if ($last_point && $last_point[0] === $candidate[0] && $last_point[1] === $candidate[1]) {
                continue;
            }

            $points[] = $candidate;
        }

        return count($points) > 1 ? $points : [];
    }

    private function get_map_preview_segment_kind($segment) {
        if (is_array($segment) && isset($segment['kind'])) {
            $kind = sanitize_key((string) $segment['kind']);

            if (in_array($kind, ['road', 'pedestrian'], true)) {
                return $kind;
            }
        }

        if (is_array($segment) && isset($segment['highway'])) {
            return $this->is_pedestrian_map_preview_highway($segment['highway']) ? 'pedestrian' : 'road';
        }

        return 'road';
    }

    private function normalize_map_preview_geometry($geometry) {
        $normalized_geometry = [];

        foreach ((array) $geometry as $segment) {
            $points = $this->get_map_preview_segment_points($segment);

            if (count($points) < 2) {
                continue;
            }

            $normalized_geometry[] = [
                'points' => $points,
                'kind' => $this->get_map_preview_segment_kind($segment),
            ];
        }

        return $normalized_geometry;
    }

    private function get_generated_map_thumbnail_id($post_id) {
        $generated_thumbnail_id = absint(get_post_meta($post_id, self::GENERATED_MAP_THUMBNAIL_META, true));

        if ($generated_thumbnail_id > 0) {
            if ('1' === get_post_meta($generated_thumbnail_id, self::GENERATED_MAP_ATTACHMENT_META, true)) {
                return $generated_thumbnail_id;
            }

            delete_post_meta($post_id, self::GENERATED_MAP_THUMBNAIL_META);
        }

        $current_thumbnail_id = (int) get_post_thumbnail_id($post_id);
        if (!$current_thumbnail_id) {
            return 0;
        }

        if ('1' === get_post_meta($current_thumbnail_id, self::GENERATED_MAP_ATTACHMENT_META, true)) {
            update_post_meta($post_id, self::GENERATED_MAP_THUMBNAIL_META, $current_thumbnail_id);
            return $current_thumbnail_id;
        }

        $attached_file = get_attached_file($current_thumbnail_id);
        if (
            $attached_file
            && (
                0 === strpos(wp_basename($attached_file), 'einsatzort-' . $post_id . '-')
                || 0 === strpos(wp_basename($attached_file), 'feuer-einsatzberichte-map-' . $post_id . '-')
            )
        ) {
            update_post_meta($post_id, self::GENERATED_MAP_THUMBNAIL_META, $current_thumbnail_id);
            update_post_meta($current_thumbnail_id, self::GENERATED_MAP_ATTACHMENT_META, '1');
            return $current_thumbnail_id;
        }

        return 0;
    }

    private function is_generated_map_thumbnail_current($post_id) {
        $generated_thumbnail_id = $this->get_generated_map_thumbnail_id($post_id);
        $current_thumbnail_id = (int) get_post_thumbnail_id($post_id);

        return $generated_thumbnail_id > 0 && $generated_thumbnail_id === $current_thumbnail_id;
    }

    private function cleanup_previous_generated_map_thumbnail($post_id, $new_attachment_id = 0) {
        $previous_attachment_id = $this->get_generated_map_thumbnail_id($post_id);

        if (!$previous_attachment_id || $previous_attachment_id === $new_attachment_id) {
            if ($new_attachment_id > 0) {
                update_post_meta($post_id, self::GENERATED_MAP_THUMBNAIL_META, $new_attachment_id);
            }

            return;
        }

        if ((int) get_post_thumbnail_id($post_id) === $previous_attachment_id) {
            delete_post_thumbnail($post_id);
        }

        if ('1' === get_post_meta($previous_attachment_id, self::GENERATED_MAP_ATTACHMENT_META, true)) {
            wp_delete_attachment($previous_attachment_id, true);
        }

        if ($new_attachment_id > 0) {
            update_post_meta($post_id, self::GENERATED_MAP_THUMBNAIL_META, $new_attachment_id);
            return;
        }

        delete_post_meta($post_id, self::GENERATED_MAP_THUMBNAIL_META);
    }

    private function cleanup_generated_map_preview_file($post_id) {
        $preview_file = get_post_meta($post_id, self::GENERATED_MAP_PREVIEW_FILE_META, true);

        if ($preview_file && file_exists($preview_file)) {
            @unlink($preview_file);
        }

        delete_post_meta($post_id, self::GENERATED_MAP_PREVIEW_FILE_META);
        delete_post_meta($post_id, self::GENERATED_MAP_PREVIEW_URL_META);
        $this->report_share->invalidate_share_card_cache((int) $post_id);
    }

    private function delete_generated_map_assets($post_id, $clear_status = true) {
        $post_id = absint($post_id);

        if (!$post_id) {
            return;
        }

        $this->clear_generated_map_queue($post_id, $clear_status);
        $this->clear_background_geocode_schedule($post_id);
        $this->clear_generated_map_publish_hold($post_id);
        $this->cleanup_previous_generated_map_thumbnail($post_id);
        $this->cleanup_generated_map_preview_file($post_id);
        delete_post_meta($post_id, self::GENERATED_MAP_SIGNATURE_META);
    }

    private function should_assign_generated_map_as_featured_image($post_id) {
        return absint($post_id) > 0;
    }

    private function maybe_generate_map_preview_image($post_id, $strasse, $plz = '', $stadt = 'Hamburg', $force = false, $context = []) {
        $post_id = absint($post_id);
        $strasse = trim((string) $strasse);

        if ($post_id < 1 || '' === $strasse) {
            return false;
        }

        $hausnummer = (string) get_post_meta($post_id, '_feu_einsatz_hausnummer', true);
        $display_street = FEU_Einsatz_Template_Helpers::strip_house_number_from_street($strasse);
        $address = $this->build_full_address($display_street, $plz, $stadt);
        $context = wp_parse_args((array) $context, [
            'street' => $strasse,
            'house_number' => $hausnummer,
            'plz' => $plz,
            'city' => $stadt,
        ]);

        return $this->generate_map_image($post_id, $address, $context);
    }

    private function get_generated_map_process_lock_key($post_id) {
        return self::GENERATED_MAP_PROCESS_LOCK_PREFIX . absint($post_id);
    }

    private function is_generated_map_process_locked($post_id) {
        $expires_at = (int) get_option($this->get_generated_map_process_lock_key($post_id), 0);

        if ($expires_at > time()) {
            return true;
        }

        if ($expires_at > 0) {
            delete_option($this->get_generated_map_process_lock_key($post_id));
        }

        return false;
    }

    private function acquire_generated_map_process_lock($post_id) {
        $lock_key = $this->get_generated_map_process_lock_key($post_id);
        $expires_at = time() + (defined('MINUTE_IN_SECONDS') ? MINUTE_IN_SECONDS * 10 : 600);

        if (add_option($lock_key, $expires_at, '', false)) {
            return true;
        }

        if (!$this->is_generated_map_process_locked($post_id)) {
            return add_option($lock_key, $expires_at, '', false);
        }

        return false;
    }

    private function release_generated_map_process_lock($post_id) {
        $lock_key = $this->get_generated_map_process_lock_key($post_id);
        delete_option($lock_key);
        delete_transient($lock_key);
    }

    private function register_shutdown_map_preview_generation($post_id) {
        static $registered_post_ids = [];

        $post_id = absint($post_id);

        if (!$post_id || isset($registered_post_ids[$post_id])) {
            return;
        }

        $registered_post_ids[$post_id] = true;

        register_shutdown_function(function () use ($post_id) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            $this->handle_background_map_preview_generation($post_id);
        });
    }

    private function queue_map_preview_generation($post_id, $strasse, $plz = '', $stadt = 'Hamburg', $force = false, $delay_seconds = null, $reason = 'save') {
        $post_id = absint($post_id);
        $strasse = trim((string) $strasse);
        $plz = trim((string) $plz);
        $stadt = trim((string) $stadt);
        $delay_seconds = null === $delay_seconds ? $this->get_generated_map_generation_delay_seconds() : absint($delay_seconds);

        if (!$post_id || '' === $strasse) {
            return false;
        }

        if ('' === $stadt) {
            $stadt = 'Hamburg';
        }

        $signature = $this->build_generated_map_signature($strasse, $plz, $stadt);
        $scheduled_for = time() + max(0, $delay_seconds);

        update_post_meta($post_id, self::GENERATED_MAP_QUEUE_META, [
            'street' => $strasse,
            'plz' => $plz,
            'city' => $stadt,
            'signature' => $signature,
            'force' => !empty($force) ? 1 : 0,
            'queued_at' => time(),
            'scheduled_for' => $scheduled_for,
            'reason' => sanitize_key((string) $reason),
        ]);

        wp_clear_scheduled_hook(self::GENERATED_MAP_BACKGROUND_HOOK, [$post_id]);
        wp_schedule_single_event($scheduled_for, self::GENERATED_MAP_BACKGROUND_HOOK, [$post_id]);
        $this->update_generated_map_status(
            $post_id,
            'queued',
            [
                'stage' => 'map',
                'message' => __('Kartenbild wird im Hintergrund erstellt.', 'feuer-einsatzberichte'),
                'scheduled_for' => $scheduled_for,
                'reason' => sanitize_key((string) $reason),
            ]
        );

        if ($delay_seconds <= 60 && function_exists('spawn_cron')) {
            spawn_cron(time());
        }

        return true;
    }

    public function handle_background_map_preview_generation($post_id) {
        $post_id = absint($post_id);

        if (!$post_id || !$this->acquire_generated_map_process_lock($post_id)) {
            return;
        }

        try {
            $post = get_post($post_id);

            if (!($post instanceof WP_Post) || 'trash' === $post->post_status) {
                $this->clear_generated_map_queue($post_id, true);
                return;
            }

        $queue_payload = get_post_meta($post_id, self::GENERATED_MAP_QUEUE_META, true);
        $strasse = is_array($queue_payload) && isset($queue_payload['street'])
            ? trim((string) $queue_payload['street'])
            : trim((string) get_post_meta($post_id, '_feu_einsatz_strasse', true));
        $plz = is_array($queue_payload) && isset($queue_payload['plz'])
            ? trim((string) $queue_payload['plz'])
            : trim((string) get_post_meta($post_id, '_feu_einsatz_plz', true));
        $stadt = is_array($queue_payload) && isset($queue_payload['city'])
            ? trim((string) $queue_payload['city'])
            : trim((string) get_post_meta($post_id, '_feu_einsatz_stadt', true));
        $force = is_array($queue_payload) && !empty($queue_payload['force']);

        if ('' === $strasse) {
            $this->clear_generated_map_queue($post_id, true);
            return;
        }

        if (!$force && $this->has_current_generated_map_signature($post_id, $strasse, $plz, $stadt)) {
            $this->clear_generated_map_queue($post_id);
            $this->maybe_release_deferred_publication($post_id);
            $this->update_generated_map_status(
                $post_id,
                'ready',
                [
                    'stage' => 'map',
                    'message' => __('Kartenbild ist bereits aktuell.', 'feuer-einsatzberichte'),
                    'completed_at' => time(),
                ]
            );
            return;
        }

        if ('' === $stadt) {
            $stadt = 'Hamburg';
        }

        $this->update_generated_map_status(
            $post_id,
            'processing',
            [
                'stage' => 'map',
                'message' => __('Kartenbild wird gerade im Hintergrund erstellt.', 'feuer-einsatzberichte'),
            ]
        );

        $result = $this->maybe_generate_map_preview_image(
            $post_id,
            $strasse,
            $plz,
            $stadt,
            $force,
            $this->build_map_preview_generation_context($post_id, $strasse, $plz, $stadt, null, [
                'allow_focused_geometry_refresh' => false,
                'allow_remote_geometry_prime' => true,
                'prefer_local_canvas' => false,
                'fast_tile_mode' => true,
            ])
        );

        if (is_wp_error($result)) {
            FEU_Einsatz_Logger::log_runtime_error(
                'report_map_background_generation_failed',
                __('Kartenbild konnte nicht im Hintergrund generiert werden.', 'feuer-einsatzberichte'),
                [
                    'post_id' => $post_id,
                    'street' => $strasse,
                    'message' => $result->get_error_message(),
                ]
            );
            $this->clear_generated_map_queue($post_id);
            $this->update_generated_map_status(
                $post_id,
                'error',
                [
                    'stage' => 'map',
                    'message' => __('Kartenbild konnte nicht automatisch erstellt werden. Bitte erneut anstossen.', 'feuer-einsatzberichte'),
                    'last_error' => $result->get_error_message(),
                ]
            );
            return;
        }

        $this->clear_generated_map_queue($post_id);
        $publication_released = $this->maybe_release_deferred_publication($post_id);
        $this->update_generated_map_status(
            $post_id,
            'ready',
            [
                'stage' => 'map',
                'message' => $publication_released
                    ? __('Kartenbild wurde erstellt und der Bericht freigegeben.', 'feuer-einsatzberichte')
                    : __('Kartenbild wurde im Hintergrund erfolgreich aktualisiert.', 'feuer-einsatzberichte'),
                'completed_at' => time(),
            ]
        );
        } catch (Throwable $error) {
            $this->clear_generated_map_queue($post_id);
            $this->update_generated_map_status(
                $post_id,
                'error',
                [
                    'stage' => 'map',
                    'message' => __('Kartenbild konnte wegen eines internen Fehlers nicht erstellt werden.', 'feuer-einsatzberichte'),
                    'last_error' => $error->getMessage(),
                ]
            );
            FEU_Einsatz_Logger::log_runtime_error(
                'report_map_background_generation_exception',
                __('Interner Fehler bei der Kartenbild-Generierung.', 'feuer-einsatzberichte'),
                [
                    'post_id' => $post_id,
                    'message' => $error->getMessage(),
                ]
            );
        } finally {
            $this->release_generated_map_process_lock($post_id);
        }
    }

    private function get_map_image_coordinates($post_id, $address = '', $context = []) {
        $preview_context = $this->get_map_preview_context($post_id, $context);
        $coordinates = !empty($preview_context['coordinates']) ? $preview_context['coordinates'] : false;

        if ($coordinates) {
            return $coordinates;
        }

        return new WP_Error(
            'feu_einsatz_missing_coordinates',
            __('Die Kartenvorschau konnte nicht erzeugt werden. Bitte pruefen Sie Adresse oder Koordinaten in den Einsatzdetails.', 'feuer-einsatzberichte')
        );
    }

    private function get_map_preview_context($post_id, $context = []) {
        $context = wp_parse_args($context, [
            'street' => '',
            'house_number' => '',
            'plz' => '',
            'city' => '',
            'latitude' => '',
            'longitude' => '',
            'geometry_payload' => false,
            'skip_remote_geometry' => false,
            'allow_remote_geocode' => true,
        ]);

        $stored_street = trim((string) get_post_meta($post_id, '_feu_einsatz_strasse', true));
        $stored_house_number = trim((string) get_post_meta($post_id, '_feu_einsatz_hausnummer', true));
        $stored_plz = trim((string) get_post_meta($post_id, '_feu_einsatz_plz', true));
        $stored_city = trim((string) get_post_meta($post_id, '_feu_einsatz_stadt', true));

        if ('' === $stored_city) {
            $stored_city = 'Hamburg';
        }

        $street = trim((string) $context['street']);
        $house_number = trim((string) $context['house_number']);
        $plz = trim((string) $context['plz']);
        $city = trim((string) $context['city']);

        if ('' === $street) {
            $street = $stored_street;
        }

        if ('' === $plz) {
            $plz = $stored_plz;
        }

        if ('' === $house_number) {
            $house_number = $stored_house_number;
        }

        if ('' === $city) {
            $city = $stored_city;
        }

        if ('' === $city) {
            $city = 'Hamburg';
        }

        $uses_saved_address = $street === $stored_street
            && $house_number === $stored_house_number
            && $plz === $stored_plz
            && $city === $stored_city;
        $coordinates = false;

        if (is_numeric($context['latitude']) && is_numeric($context['longitude'])) {
            $coordinates = [
                'lat' => (float) $context['latitude'],
                'lng' => (float) $context['longitude'],
            ];
        } elseif ($uses_saved_address) {
            $coordinates = $this->get_cached_coordinates($post_id);
        } elseif ('' !== $street && !empty($context['allow_remote_geocode'])) {
            $geocoded_data = $this->request_geocoded_address_data($street, $plz, $city, $house_number);

            if ($geocoded_data) {
                $coordinates = [
                    'lat' => (float) $geocoded_data['lat'],
                    'lng' => (float) $geocoded_data['lng'],
                ];
            }
        }

        $geometry_payload = is_array($context['geometry_payload']) ? $context['geometry_payload'] : false;

        if ($geometry_payload) {
            return [
                'street' => $street,
                'house_number' => $house_number,
                'plz' => $plz,
                'city' => $city,
                'coordinates' => $coordinates,
                'geometry' => $geometry_payload,
            ];
        }

        if ($uses_saved_address) {
            if (!empty($context['skip_remote_geometry'])) {
                $geometry_payload = FEU_Einsatz_Street_Cache::get_post_cache($post_id);

                if (!$geometry_payload && '' !== $street) {
                    $geometry_payload = FEU_Einsatz_Street_Cache::get($street, $plz, $city);

                    if ($geometry_payload) {
                        FEU_Einsatz_Street_Cache::set_post_cache($post_id, $geometry_payload);
                    }
                }
            } else {
                $geometry_payload = $this->get_map_preview_geometry($post_id);
            }
        } elseif ('' !== $street && empty($context['skip_remote_geometry'])) {
            $geometry_payload = FEU_Einsatz_Street_Cache::get($street, $plz, $city);

            if (!$geometry_payload && $coordinates) {
                $geometry_payload = $this->request_street_geometry_data($street, $coordinates);

                if ($geometry_payload) {
                    FEU_Einsatz_Street_Cache::set($street, $plz, $city, $geometry_payload);
                }
            }
        }

        return [
            'street' => $street,
            'house_number' => $house_number,
            'plz' => $plz,
            'city' => $city,
            'coordinates' => $coordinates,
            'geometry' => $geometry_payload,
        ];
    }

    private function get_map_preview_geometry($post_id) {
        $cached_geometry = FEU_Einsatz_Street_Cache::get_post_cache($post_id);
        if ($cached_geometry) {
            return $cached_geometry;
        }

        $strasse = get_post_meta($post_id, '_feu_einsatz_strasse', true);
        if ('' === trim((string) $strasse)) {
            return false;
        }

        $plz = get_post_meta($post_id, '_feu_einsatz_plz', true);
        $stadt = get_post_meta($post_id, '_feu_einsatz_stadt', true);
        $cached_geometry = FEU_Einsatz_Street_Cache::get($strasse, $plz, $stadt);

        if ($cached_geometry) {
            FEU_Einsatz_Street_Cache::set_post_cache($post_id, $cached_geometry);
            return $cached_geometry;
        }

        if (class_exists('FEU_Einsatz_Template_Helpers')) {
            $primed_map_data = FEU_Einsatz_Template_Helpers::prime_public_map_data($post_id, $strasse, $plz, $stadt);

            if (
                is_array($primed_map_data)
                && !empty($primed_map_data['geometry'])
                && !empty($primed_map_data['center'])
            ) {
                return [
                    'geometry' => $primed_map_data['geometry'],
                    'center' => $primed_map_data['center'],
                ];
            }

            $cached_geometry = FEU_Einsatz_Street_Cache::get_post_cache($post_id);

            if ($cached_geometry) {
                return $cached_geometry;
            }
        }

        $latitude = get_post_meta($post_id, '_feu_einsatz_latitude', true);
        $longitude = get_post_meta($post_id, '_feu_einsatz_longitude', true);
        $coordinates = false;

        if (is_numeric($latitude) && is_numeric($longitude)) {
            $coordinates = [
                'lat' => (float) $latitude,
                'lng' => (float) $longitude,
            ];
        } else {
            $coordinates = $this->get_and_save_coordinates($post_id, $strasse, $plz, $stadt);
        }

        if (!$coordinates) {
            return false;
        }

        $remote_geometry = $this->request_street_geometry_data($strasse, $coordinates);

        if (!$remote_geometry) {
            return FEU_Einsatz_Street_Cache::get_post_cache($post_id);
        }

        FEU_Einsatz_Street_Cache::set($strasse, $plz, $stadt, $remote_geometry);
        FEU_Einsatz_Street_Cache::set_post_cache($post_id, $remote_geometry);

        return $remote_geometry;
    }

    private function get_map_preview_heading() {
        $custom_heading = $this->prepare_map_preview_text((string) get_option('feu_einsatz_map_preview_heading_text', ''));

        if ('' !== $custom_heading) {
            return $custom_heading;
        }

        $watermark_text = trim((string) get_option('feu_einsatz_photo_watermark_text', get_bloginfo('name')));

        if ('' !== $watermark_text) {
            return 'Einsatzort ' . $watermark_text;
        }

        return 'Einsatzort';
    }

    private function is_map_preview_info_panel_enabled() {
        return 1 === (int) get_option('feu_einsatz_map_preview_show_panel', 1);
    }

    private function is_map_preview_panel_heading_enabled() {
        return 1 === (int) get_option('feu_einsatz_map_preview_show_panel_heading', 1);
    }

    private function is_map_preview_panel_address_enabled() {
        return 1 === (int) get_option('feu_einsatz_map_preview_show_panel_address', 1);
    }

    private function get_map_preview_panel_position() {
        return $this->normalize_map_preview_position(
            get_option('feu_einsatz_map_preview_panel_position', 'bottom-left'),
            'bottom-left'
        );
    }

    private function get_map_preview_attribution_text() {
        $fallback = 'Leaflet | ' . html_entity_decode('&copy;', ENT_QUOTES, 'UTF-8') . ' OpenStreetMap contributors';
        $custom = $this->prepare_map_preview_text((string) get_option('feu_einsatz_map_preview_attribution_text', $fallback));

        return '' !== $custom ? $custom : $fallback;
    }

    private function is_map_preview_attribution_enabled() {
        return 1 === (int) get_option('feu_einsatz_map_preview_show_attribution', 1);
    }

    private function get_map_preview_attribution_position() {
        return $this->normalize_map_preview_position(
            get_option('feu_einsatz_map_preview_attribution_position', 'bottom-right'),
            'bottom-right'
        );
    }

    private function get_map_preview_street_label_position() {
        return $this->normalize_map_preview_position(
            get_option('feu_einsatz_map_preview_street_label_position', 'auto'),
            'auto',
            true
        );
    }

    private function get_map_preview_station_feature() {
        return false;
    }

    private function is_map_preview_street_label_enabled() {
        return 1 === (int) get_option('feu_einsatz_map_preview_show_street_label', 1);
    }

    private function get_map_preview_street_label_prefix() {
        $prefix = $this->prepare_map_preview_text((string) get_option('feu_einsatz_map_preview_street_label_prefix', 'Einsatz Straße'));

        return '' !== $prefix ? $prefix : 'Einsatz Straße';
    }

    private function get_map_preview_highlight_hex_color() {
        $highlight_color = sanitize_hex_color((string) get_option('feu_einsatz_map_preview_highlight_color', '#d92d20'));

        return $highlight_color ? strtolower($highlight_color) : '#d92d20';
    }

    public function get_map_preview_font_option_definitions() {
        return [
            'auto' => [
                'label' => __('Automatisch (Server-Standard)', 'feuer-einsatzberichte'),
                'css_stack' => '"Segoe UI", Arial, "DejaVu Sans", "Liberation Sans", sans-serif',
                'paths' => [],
            ],
            'segoe-ui' => [
                'label' => 'Segoe UI',
                'css_stack' => '"Segoe UI", Arial, sans-serif',
                'paths' => [
                    'C:\\Windows\\Fonts\\segoeui.ttf',
                ],
            ],
            'arial' => [
                'label' => 'Arial',
                'css_stack' => 'Arial, sans-serif',
                'paths' => [
                    'C:\\Windows\\Fonts\\arial.ttf',
                ],
            ],
            'dejavu-sans' => [
                'label' => 'DejaVu Sans',
                'css_stack' => '"DejaVu Sans", Arial, sans-serif',
                'paths' => [],
            ],
            'liberation-sans' => [
                'label' => 'Liberation Sans',
                'css_stack' => '"Liberation Sans", Arial, sans-serif',
                'paths' => [],
            ],
            'free-sans' => [
                'label' => 'FreeSans',
                'css_stack' => '"FreeSans", Arial, sans-serif',
                'paths' => [],
            ],
        ];
    }

    public function normalize_map_preview_font_family($value) {
        $value = sanitize_key((string) $value);
        $definitions = $this->get_map_preview_font_option_definitions();

        return isset($definitions[$value]) ? $value : 'auto';
    }

    public function normalize_map_label_style($value) {
        $allowed = ['bubble', 'badge', 'plain'];
        $value = sanitize_key((string) $value);

        return in_array($value, $allowed, true) ? $value : 'bubble';
    }

    public function get_map_preview_position_options($include_auto = false) {
        $options = [];

        if ($include_auto) {
            $options['auto'] = __('Automatisch', 'feuer-einsatzberichte');
        }

        return $options + [
            'top-left' => __('Oben links', 'feuer-einsatzberichte'),
            'top-right' => __('Oben rechts', 'feuer-einsatzberichte'),
            'bottom-left' => __('Unten links', 'feuer-einsatzberichte'),
            'bottom-right' => __('Unten rechts', 'feuer-einsatzberichte'),
            'center' => __('Mitte', 'feuer-einsatzberichte'),
        ];
    }

    public function normalize_map_preview_position($value, $default = 'bottom-right', $include_auto = false) {
        $value = sanitize_key((string) $value);
        $allowed = array_keys($this->get_map_preview_position_options($include_auto));

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function get_map_preview_font_family() {
        return $this->normalize_map_preview_font_family(get_option('feu_einsatz_map_preview_font_family', 'auto'));
    }

    private function get_map_preview_font_css_stack() {
        $definitions = $this->get_map_preview_font_option_definitions();
        $font_family = $this->get_map_preview_font_family();

        if (isset($definitions[$font_family]['css_stack'])) {
            return (string) $definitions[$font_family]['css_stack'];
        }

        return (string) $definitions['auto']['css_stack'];
    }

    private function get_map_preview_stroke_width() {
        $value = absint(get_option('feu_einsatz_map_preview_stroke_width', 8));

        return max(3, min(18, $value));
    }

    private function get_map_preview_pedestrian_stroke_width() {
        return max(2, $this->get_map_preview_stroke_width() - 3);
    }

    private function get_map_preview_highlight_rgb() {
        $hex = ltrim($this->get_map_preview_highlight_hex_color(), '#');

        if (6 !== strlen($hex)) {
            return [
                'r' => 217,
                'g' => 45,
                'b' => 32,
            ];
        }

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    private function allocate_map_preview_highlight_color($canvas, $alpha = 0) {
        $rgb = $this->get_map_preview_highlight_rgb();

        return imagecolorallocatealpha(
            $canvas,
            (int) $rgb['r'],
            (int) $rgb['g'],
            (int) $rgb['b'],
            max(0, min(127, (int) $alpha))
        );
    }

    private function build_map_preview_street_label($post_id, $address = '') {
        if (!$this->is_map_preview_street_label_enabled()) {
            return '';
        }

        $street = trim((string) get_post_meta($post_id, '_feu_einsatz_strasse', true));

        if ('' === $street && '' !== trim((string) $address)) {
            $street_parts = explode(',', (string) $address);
            $street = trim((string) ($street_parts[0] ?? ''));
        }

        $street = FEU_Einsatz_Template_Helpers::strip_house_number_from_street($street);
        $street = $this->prepare_map_preview_text($street);

        if ('' === $street) {
            return '';
        }

        $prefix = $this->prepare_map_preview_text($this->get_map_preview_street_label_prefix());

        return '' !== $prefix ? $prefix . ': ' . $street : $street;
    }

    private function get_map_preview_dimensions() {
        return [
            'width' => 1024,
            'height' => 630,
        ];
    }

    private function get_map_preview_points($geometry_payload, $coordinates = [], $station_feature = false) {
        $points = [];
        $segments = $this->normalize_map_preview_geometry(
            is_array($geometry_payload) && !empty($geometry_payload['geometry']) ? $geometry_payload['geometry'] : []
        );

        foreach ($segments as $segment) {
            foreach ($segment['points'] as $point) {
                $points[] = [
                    'lat' => (float) $point[0],
                    'lng' => (float) $point[1],
                ];
            }
        }

        if (
            empty($points)
            && is_array($coordinates)
            && isset($coordinates['lat'], $coordinates['lng'])
            && is_numeric($coordinates['lat'])
            && is_numeric($coordinates['lng'])
        ) {
            $points[] = [
                'lat' => (float) $coordinates['lat'],
                'lng' => (float) $coordinates['lng'],
            ];
        }

        if (
            is_array($station_feature)
            && isset($station_feature['latitude'], $station_feature['longitude'])
            && is_numeric($station_feature['latitude'])
            && is_numeric($station_feature['longitude'])
        ) {
            $points[] = [
                'lat' => (float) $station_feature['latitude'],
                'lng' => (float) $station_feature['longitude'],
            ];
        }

        return $points;
    }

    private function resolve_map_preview_camera($post_id, $coordinates, $width, $height, $preferred_zoom, $geometry_payload = false) {
        $width = max(120, (int) $width);
        $height = max(120, (int) $height);
        $zoom = $this->clamp_preview_zoom($preferred_zoom);
        $station_feature = $this->get_map_preview_station_feature();
        if (!$geometry_payload) {
            $geometry_payload = $this->get_map_preview_geometry($post_id);
        }
        $points = $this->get_map_preview_points($geometry_payload, $coordinates, $station_feature);
        $center = [
            'lat' => (float) $coordinates['lat'],
            'lng' => (float) $coordinates['lng'],
        ];

        if (!empty($points)) {
            $latitudes = array_column($points, 'lat');
            $longitudes = array_column($points, 'lng');
            $center = [
                'lat' => (min($latitudes) + max($latitudes)) / 2,
                'lng' => (min($longitudes) + max($longitudes)) / 2,
            ];

            if (count($points) > 1) {
                $fitted_zoom = 10;

                for ($candidate_zoom = $zoom; $candidate_zoom >= 10; $candidate_zoom--) {
                    $world_points = array_map(function($point) use ($candidate_zoom) {
                        return $this->lat_lng_to_world_pixels($point['lat'], $point['lng'], $candidate_zoom);
                    }, $points);

                    $min_x = min(array_column($world_points, 'x'));
                    $max_x = max(array_column($world_points, 'x'));
                    $min_y = min(array_column($world_points, 'y'));
                    $max_y = max(array_column($world_points, 'y'));
                    $required_width = ($max_x - $min_x) + (max(72, ($max_x - $min_x) * 0.24) * 2);
                    $required_height = ($max_y - $min_y) + (max(72, ($max_y - $min_y) * 0.24) * 2);

                    if ($required_width <= $width && $required_height <= $height) {
                        $fitted_zoom = $candidate_zoom;
                        break;
                    }
                }

                $zoom = $fitted_zoom;
            }
        }

        return [
            'center' => $center,
            'zoom' => $zoom,
            'viewport' => $this->get_map_preview_viewport($center, $width, $height, $zoom),
        ];
    }

    private function clamp_preview_zoom($zoom) {
        $zoom = absint($zoom);

        if ($zoom < 10) {
            return 10;
        }

        if ($zoom > 18) {
            return 18;
        }

        return $zoom;
    }

    private function lat_lng_to_world_pixels($lat, $lng, $zoom) {
        $lat = max(min((float) $lat, 85.05112878), -85.05112878);
        $lng = (float) $lng;
        $tile_size = 256;
        $scale = $tile_size * pow(2, (int) $zoom);
        $sin_lat = sin(deg2rad($lat));

        return [
            'x' => (($lng + 180) / 360) * $scale,
            'y' => (0.5 - (log((1 + $sin_lat) / (1 - $sin_lat)) / (4 * M_PI))) * $scale,
        ];
    }

    private function get_map_preview_viewport($coordinates, $width, $height, $zoom) {
        $center = $this->lat_lng_to_world_pixels($coordinates['lat'], $coordinates['lng'], $zoom);

        return [
            'top_left_x' => $center['x'] - ($width / 2),
            'top_left_y' => $center['y'] - ($height / 2),
        ];
    }

    private function get_map_preview_screen_point($lat, $lng, $viewport, $zoom) {
        $world = $this->lat_lng_to_world_pixels($lat, $lng, $zoom);

        return [
            'x' => $world['x'] - $viewport['top_left_x'],
            'y' => $world['y'] - $viewport['top_left_y'],
        ];
    }

    private function create_local_map_canvas($width, $height) {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }

        $width = max(120, (int) $width);
        $height = max(120, (int) $height);
        $canvas = imagecreatetruecolor($width, $height);

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        $top_color = [248, 251, 255];
        $bottom_color = [231, 241, 251];

        for ($y = 0; $y < $height; $y++) {
            $ratio = $height > 1 ? ($y / ($height - 1)) : 0;
            $red = (int) round($top_color[0] + (($bottom_color[0] - $top_color[0]) * $ratio));
            $green = (int) round($top_color[1] + (($bottom_color[1] - $top_color[1]) * $ratio));
            $blue = (int) round($top_color[2] + (($bottom_color[2] - $top_color[2]) * $ratio));
            $line_color = imagecolorallocate($canvas, $red, $green, $blue);
            imageline($canvas, 0, $y, $width, $y, $line_color);
        }

        $grid_color = imagecolorallocatealpha($canvas, 13, 36, 63, 108);

        for ($column = 1; $column <= 5; $column++) {
            $x = (int) round(($width / 6) * $column);
            imageline($canvas, $x, 0, $x, $height, $grid_color);
        }

        for ($row = 1; $row <= 4; $row++) {
            $y = (int) round(($height / 5) * $row);
            imageline($canvas, 0, $y, $width, $y, $grid_color);
        }

        return $canvas;
    }

    private function build_local_map_preview_canvas($post_id, $address, $camera, $geometry_payload, $width, $height) {
        $canvas = $this->create_local_map_canvas($width, $height);

        if (!$canvas) {
            return false;
        }

        $this->apply_map_preview_overlay($canvas, $post_id, $address, $camera, $geometry_payload);

        return $canvas;
    }

    private function download_static_map_canvas($coordinates, $zoom, $width, $height) {
        return false;
    }

    private function download_tile_map_canvas($camera, $width, $height, $options = []) {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }

        $options = wp_parse_args($options, [
            'timeout' => 8,
            'max_tiles' => 30,
        ]);

        $canvas = $this->create_local_map_canvas($width, $height);

        if (!$canvas) {
            return false;
        }

        if (!is_array($camera) || empty($camera['viewport']) || !isset($camera['zoom'])) {
            imagedestroy($canvas);
            return false;
        }

        $viewport = $camera['viewport'];
        $zoom = $this->clamp_preview_zoom($camera['zoom']);
        $tile_size = 256;
        $start_tile_x = (int) floor($viewport['top_left_x'] / $tile_size);
        $end_tile_x = (int) floor(($viewport['top_left_x'] + $width) / $tile_size);
        $start_tile_y = (int) floor($viewport['top_left_y'] / $tile_size);
        $end_tile_y = (int) floor(($viewport['top_left_y'] + $height) / $tile_size);
        $estimated_tile_count = max(0, ($end_tile_x - $start_tile_x + 1) * ($end_tile_y - $start_tile_y + 1));
        $max_tiles = max(4, (int) $options['max_tiles']);
        $loaded_tiles = 0;

        if ($estimated_tile_count > $max_tiles && !empty($camera['center']['lat']) && !empty($camera['center']['lng'])) {
            for ($candidate_zoom = $zoom - 1; $candidate_zoom >= 10; $candidate_zoom--) {
                $candidate_viewport = $this->get_map_preview_viewport($camera['center'], $width, $height, $candidate_zoom);
                $candidate_start_tile_x = (int) floor($candidate_viewport['top_left_x'] / $tile_size);
                $candidate_end_tile_x = (int) floor(($candidate_viewport['top_left_x'] + $width) / $tile_size);
                $candidate_start_tile_y = (int) floor($candidate_viewport['top_left_y'] / $tile_size);
                $candidate_end_tile_y = (int) floor(($candidate_viewport['top_left_y'] + $height) / $tile_size);
                $candidate_tile_count = max(0, ($candidate_end_tile_x - $candidate_start_tile_x + 1) * ($candidate_end_tile_y - $candidate_start_tile_y + 1));

                if ($candidate_tile_count <= $max_tiles) {
                    $zoom = $candidate_zoom;
                    $viewport = $candidate_viewport;
                    break;
                }
            }
        }

        $max_tile_index = (int) pow(2, (int) $zoom) - 1;
        $start_tile_x = (int) floor($viewport['top_left_x'] / $tile_size);
        $end_tile_x = (int) floor(($viewport['top_left_x'] + $width) / $tile_size);
        $start_tile_y = (int) floor($viewport['top_left_y'] / $tile_size);
        $end_tile_y = (int) floor(($viewport['top_left_y'] + $height) / $tile_size);
        $estimated_tile_count = max(0, ($end_tile_x - $start_tile_x + 1) * ($end_tile_y - $start_tile_y + 1));

        if ($estimated_tile_count > $max_tiles) {
            imagedestroy($canvas);
            return false;
        }

        for ($tile_y = $start_tile_y; $tile_y <= $end_tile_y; $tile_y++) {
            if ($tile_y < 0 || $tile_y > $max_tile_index) {
                continue;
            }

            for ($tile_x = $start_tile_x; $tile_x <= $end_tile_x; $tile_x++) {
                $wrapped_tile_x = $tile_x % ($max_tile_index + 1);

                if ($wrapped_tile_x < 0) {
                    $wrapped_tile_x += ($max_tile_index + 1);
                }

                $tile_url = 'https://tile.openstreetmap.org/' . absint($zoom) . '/' . $wrapped_tile_x . '/' . $tile_y . '.png';
                $response = wp_safe_remote_get(
                    $tile_url,
                    $this->get_map_remote_request_args((int) $options['timeout'], 'image/png,image/*;q=0.8,*/*;q=0.5')
                );

                if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
                    continue;
                }

                $tile_contents = wp_remote_retrieve_body($response);

                if ('' === trim((string) $tile_contents)) {
                    continue;
                }

                $tile_image = @imagecreatefromstring($tile_contents);

                if (!$tile_image) {
                    continue;
                }

                imagecopy(
                    $canvas,
                    $tile_image,
                    (int) round(($tile_x * $tile_size) - $viewport['top_left_x']),
                    (int) round(($tile_y * $tile_size) - $viewport['top_left_y']),
                    0,
                    0,
                    imagesx($tile_image),
                    imagesy($tile_image)
                );

                imagedestroy($tile_image);
                $loaded_tiles++;
            }
        }

        if ($loaded_tiles < 1) {
            imagedestroy($canvas);
            return false;
        }

        return $canvas;
    }

    private function get_map_preview_font_path() {
        $definitions = $this->get_map_preview_font_option_definitions();
        $font_family = $this->get_map_preview_font_family();
        $font_candidates = [];

        if ('auto' !== $font_family && !empty($definitions[$font_family]['paths'])) {
            $font_candidates = array_merge($font_candidates, (array) $definitions[$font_family]['paths']);
        }

        foreach ($definitions as $definition_key => $definition) {
            if ('auto' === $definition_key || empty($definition['paths'])) {
                continue;
            }

            $font_candidates = array_merge($font_candidates, (array) $definition['paths']);
        }

        $font_candidates = array_values(array_unique($font_candidates));

        foreach ($font_candidates as $font_path) {
            if (!$this->is_font_path_accessible($font_path)) {
                continue;
            }

            if (file_exists($font_path) && is_readable($font_path)) {
                return $font_path;
            }
        }

        return '';
    }

    private function is_font_path_accessible($font_path) {
        $font_path = (string) $font_path;

        if ('' === $font_path) {
            return false;
        }

        $open_basedir = (string) ini_get('open_basedir');

        if ('' === $open_basedir) {
            return true;
        }

        $allowed_paths = explode(PATH_SEPARATOR, $open_basedir);

        if (!is_array($allowed_paths) || empty($allowed_paths)) {
            return true;
        }

        $normalized_font_path = wp_normalize_path($font_path);
        $normalized_font_path = rtrim($normalized_font_path, '/');

        foreach ($allowed_paths as $allowed_path) {
            $allowed_path = trim((string) $allowed_path);

            if ('' === $allowed_path || '.' === $allowed_path) {
                continue;
            }

            $normalized_allowed_path = wp_normalize_path($allowed_path);
            $normalized_allowed_path = rtrim($normalized_allowed_path, '/');

            if ('' === $normalized_allowed_path) {
                continue;
            }

            if (
                $normalized_font_path === $normalized_allowed_path
                || 0 === strpos($normalized_font_path, trailingslashit($normalized_allowed_path))
            ) {
                return true;
            }
        }

        return false;
    }

    private function prepare_map_preview_text($text) {
        $text = wp_strip_all_tags((string) $text);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        return $text;
    }

    private function get_map_preview_text_length($text) {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen((string) $text, 'UTF-8');
        }

        return (int) strlen((string) $text);
    }

    private function slice_map_preview_text($text, $length) {
        $length = max(0, (int) $length);

        if (function_exists('mb_substr')) {
            return (string) mb_substr((string) $text, 0, $length, 'UTF-8');
        }

        return (string) substr((string) $text, 0, $length);
    }

    private function fit_map_preview_text_to_width($text, $font_size, $font_path, $max_width) {
        $text = $this->prepare_map_preview_text($text);

        if ('' === $text || $max_width <= 0 || !$font_path || !function_exists('imagettfbbox')) {
            return $text;
        }

        $candidate = $text;
        $max_iterations = max(1, $this->get_map_preview_text_length($candidate) + 2);

        for ($iteration = 0; $iteration < $max_iterations; $iteration++) {
            $box = imagettfbbox($font_size, 0, $font_path, $candidate);
            $text_width = abs((int) $box[2] - (int) $box[0]);

            if ($text_width <= $max_width) {
                return $candidate;
            }

            $base_text = $candidate;
            if (substr($base_text, -3) === '...') {
                $base_text = substr($base_text, 0, -3);
            }

            $base_text = rtrim($base_text);
            $base_length = $this->get_map_preview_text_length($base_text);

            if ($base_length <= 1) {
                return $this->slice_map_preview_text($base_text, 1);
            }

            $shortened = rtrim($this->slice_map_preview_text($base_text, $base_length - 1));
            if ('' === $shortened || $shortened === $base_text) {
                return $this->slice_map_preview_text($base_text, 1);
            }

            $candidate = $shortened;
            if ($this->get_map_preview_text_length($shortened) > 3) {
                $candidate = $shortened . '...';
            }
        }

        return $this->slice_map_preview_text($text, 1);
    }

    private function get_map_preview_ttf_text_width($text, $font_size, $font_path) {
        if ('' === $text || !$font_path || !function_exists('imagettfbbox')) {
            return 0;
        }

        $box = imagettfbbox($font_size, 0, $font_path, $text);

        return abs((int) $box[2] - (int) $box[0]);
    }

    private function draw_map_preview_text($canvas, $x, $y, $text, $font_size, $color, $max_width = 0) {
        $text = $this->prepare_map_preview_text($text);
        if ('' === $text) {
            return;
        }

        $font_path = $this->get_map_preview_font_path();

        if ($font_path && function_exists('imagettftext')) {
            $output_text = $max_width > 0
                ? $this->fit_map_preview_text_to_width($text, $font_size, $font_path, $max_width)
                : $text;

            if ('' === $output_text) {
                return;
            }

            imagettftext($canvas, $font_size, 0, $x, $y, $color, $font_path, $output_text);
            return;
        }

        $fallback_text = remove_accents($text);
        if ($max_width > 0) {
            $max_characters = max(8, (int) floor($max_width / 8));
            if (strlen($fallback_text) > $max_characters) {
                $fallback_text = substr($fallback_text, 0, $max_characters - 3) . '...';
            }
        }

        imagestring($canvas, 5, $x, $y - 16, $fallback_text, $color);
    }

    private function draw_map_preview_polyline($canvas, $points, $color, $thickness, $dashed = false, $dash_length = 18, $gap_length = 12) {
        $n = count($points);

        if ($n < 2) {
            return;
        }

        $half = $thickness / 2.0;
        $diam = (int) ceil($thickness);

        if (!$dashed) {
            for ($i = 1; $i < $n; $i++) {
                $x1 = (float) $points[$i - 1]['x'];
                $y1 = (float) $points[$i - 1]['y'];
                $x2 = (float) $points[$i]['x'];
                $y2 = (float) $points[$i]['y'];
                $dx = $x2 - $x1;
                $dy = $y2 - $y1;
                $len = sqrt($dx * $dx + $dy * $dy);

                if ($len < 0.01) {
                    continue;
                }

                $nx = -$dy / $len * $half;
                $ny =  $dx / $len * $half;

                imagefilledpolygon($canvas, [
                    (int) round($x1 + $nx), (int) round($y1 + $ny),
                    (int) round($x2 + $nx), (int) round($y2 + $ny),
                    (int) round($x2 - $nx), (int) round($y2 - $ny),
                    (int) round($x1 - $nx), (int) round($y1 - $ny),
                ], $color);

                imagefilledellipse($canvas, (int) round($x1), (int) round($y1), $diam, $diam, $color);
                imagefilledellipse($canvas, (int) round($x2), (int) round($y2), $diam, $diam, $color);
            }

            return;
        }

        for ($i = 1; $i < $n; $i++) {
            $x1 = (float) $points[$i - 1]['x'];
            $y1 = (float) $points[$i - 1]['y'];
            $x2 = (float) $points[$i]['x'];
            $y2 = (float) $points[$i]['y'];
            $dx = $x2 - $x1;
            $dy = $y2 - $y1;
            $len = sqrt($dx * $dx + $dy * $dy);

            if ($len < 0.01) {
                continue;
            }

            $ux = $dx / $len;
            $uy = $dy / $len;
            $nx = -$uy * $half;
            $ny =  $ux * $half;
            $offset = 0.0;

            while ($offset < $len) {
                $ds = $offset;
                $de = min($len, $offset + $dash_length);
                $ax1 = $x1 + $ux * $ds;
                $ay1 = $y1 + $uy * $ds;
                $ax2 = $x1 + $ux * $de;
                $ay2 = $y1 + $uy * $de;

                imagefilledpolygon($canvas, [
                    (int) round($ax1 + $nx), (int) round($ay1 + $ny),
                    (int) round($ax2 + $nx), (int) round($ay2 + $ny),
                    (int) round($ax2 - $nx), (int) round($ay2 - $ny),
                    (int) round($ax1 - $nx), (int) round($ay1 - $ny),
                ], $color);

                imagefilledellipse($canvas, (int) round($ax1), (int) round($ay1), $diam, $diam, $color);
                imagefilledellipse($canvas, (int) round($ax2), (int) round($ay2), $diam, $diam, $color);

                $offset += max(1, $dash_length + $gap_length);
            }
        }
    }

    private function draw_map_preview_rounded_rectangle($canvas, $left, $top, $right, $bottom, $radius, $color, $filled = true) {
        $left = (int) round($left);
        $top = (int) round($top);
        $right = (int) round($right);
        $bottom = (int) round($bottom);
        $radius = max(0, (int) round($radius));

        if ($radius < 1) {
            if ($filled) {
                imagefilledrectangle($canvas, $left, $top, $right, $bottom, $color);
            } else {
                imagerectangle($canvas, $left, $top, $right, $bottom, $color);
            }

            return;
        }

        $diameter = $radius * 2;

        if ($filled) {
            imagefilledrectangle($canvas, $left + $radius, $top, $right - $radius, $bottom, $color);
            imagefilledrectangle($canvas, $left, $top + $radius, $right, $bottom - $radius, $color);
            imagefilledellipse($canvas, $left + $radius, $top + $radius, $diameter, $diameter, $color);
            imagefilledellipse($canvas, $right - $radius, $top + $radius, $diameter, $diameter, $color);
            imagefilledellipse($canvas, $left + $radius, $bottom - $radius, $diameter, $diameter, $color);
            imagefilledellipse($canvas, $right - $radius, $bottom - $radius, $diameter, $diameter, $color);

            return;
        }

        imageline($canvas, $left + $radius, $top, $right - $radius, $top, $color);
        imageline($canvas, $left + $radius, $bottom, $right - $radius, $bottom, $color);
        imageline($canvas, $left, $top + $radius, $left, $bottom - $radius, $color);
        imageline($canvas, $right, $top + $radius, $right, $bottom - $radius, $color);
        imagearc($canvas, $left + $radius, $top + $radius, $diameter, $diameter, 180, 270, $color);
        imagearc($canvas, $right - $radius, $top + $radius, $diameter, $diameter, 270, 360, $color);
        imagearc($canvas, $left + $radius, $bottom - $radius, $diameter, $diameter, 90, 180, $color);
        imagearc($canvas, $right - $radius, $bottom - $radius, $diameter, $diameter, 0, 90, $color);
    }

    private function get_map_preview_label_anchor_from_projected_segments(array $projected_segments, $width, $height) {
        $best_candidate = null;
        $best_score = -1;

        foreach ($projected_segments as $segment) {
            if (empty($segment['points']) || count($segment['points']) < 2) {
                continue;
            }

            for ($index = 1, $count = count($segment['points']); $index < $count; $index++) {
                $start = $segment['points'][$index - 1];
                $end = $segment['points'][$index];
                $dx = (float) $end['x'] - (float) $start['x'];
                $dy = (float) $end['y'] - (float) $start['y'];
                $length = sqrt(($dx * $dx) + ($dy * $dy));

                if ($length < 24) {
                    continue;
                }

                $score = $length + ('pedestrian' !== ($segment['kind'] ?? 'road') ? 100000 : 0);

                if ($score <= $best_score) {
                    continue;
                }

                $best_score = $score;
                $best_candidate = [
                    'mid_x' => ((float) $start['x'] + (float) $end['x']) / 2,
                    'mid_y' => ((float) $start['y'] + (float) $end['y']) / 2,
                    'dx' => $dx,
                    'dy' => $dy,
                    'length' => $length,
                ];
            }
        }

        if (!$best_candidate) {
            return null;
        }

        $normal_x = 0.0;
        $normal_y = -1.0;

        if ($best_candidate['length'] > 0.01) {
            $normal_x = -$best_candidate['dy'] / $best_candidate['length'];
            $normal_y = $best_candidate['dx'] / $best_candidate['length'];
        }

        $offset = 54;
        $candidates = [
            [
                'x' => $best_candidate['mid_x'] + ($normal_x * $offset),
                'y' => $best_candidate['mid_y'] + ($normal_y * $offset),
            ],
            [
                'x' => $best_candidate['mid_x'] - ($normal_x * $offset),
                'y' => $best_candidate['mid_y'] - ($normal_y * $offset),
            ],
            [
                'x' => $best_candidate['mid_x'],
                'y' => $best_candidate['mid_y'] - 56,
            ],
            [
                'x' => $best_candidate['mid_x'],
                'y' => $best_candidate['mid_y'] + 56,
            ],
        ];

        $safe_top = 48;
        $safe_bottom = max(72, (int) $height - 210);
        $selected = $candidates[0];
        $selected_score = -PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            $score = 0;

            if ($candidate['y'] >= $safe_top && $candidate['y'] <= $safe_bottom) {
                $score += 1000;
            }

            $score -= abs(($height * 0.45) - $candidate['y']);
            $score -= abs(($width * 0.5) - $candidate['x']) * 0.05;

            if ($score > $selected_score) {
                $selected_score = $score;
                $selected = $candidate;
            }
        }

        return [
            'mid_x' => $best_candidate['mid_x'],
            'mid_y' => $best_candidate['mid_y'],
            'x' => $selected['x'],
            'y' => $selected['y'],
        ];
    }

    private function get_map_preview_fixed_anchor(string $position, int $width, int $height): array {
        $margin_x = 190;
        $margin_y = 64;

        switch ($position) {
            case 'top-left':
                return ['x' => $margin_x, 'y' => $margin_y];
            case 'top-right':
                return ['x' => $width - $margin_x, 'y' => $margin_y];
            case 'bottom-left':
                return ['x' => $margin_x, 'y' => $height - 92];
            case 'bottom-right':
                return ['x' => $width - $margin_x, 'y' => $height - 92];
            case 'center':
                return ['x' => $width / 2, 'y' => $height / 2];
        }

        return ['x' => $width / 2, 'y' => $height / 2];
    }

    private function get_map_preview_box_coordinates(string $position, int $canvas_width, int $canvas_height, int $box_width, int $box_height, int $margin = 24): array {
        switch ($position) {
            case 'top-right':
                $left = $canvas_width - $box_width - $margin;
                $top = $margin;
                break;
            case 'bottom-left':
                $left = $margin;
                $top = $canvas_height - $box_height - $margin;
                break;
            case 'bottom-right':
                $left = $canvas_width - $box_width - $margin;
                $top = $canvas_height - $box_height - $margin;
                break;
            case 'center':
                $left = (int) round(($canvas_width - $box_width) / 2);
                $top = (int) round(($canvas_height - $box_height) / 2);
                break;
            case 'top-left':
            default:
                $left = $margin;
                $top = $margin;
                break;
        }

        $left = max($margin, min((int) $left, $canvas_width - $box_width - $margin));
        $top = max($margin, min((int) $top, $canvas_height - $box_height - $margin));

        return [
            'left' => $left,
            'top' => $top,
            'right' => $left + $box_width,
            'bottom' => $top + $box_height,
        ];
    }

    private function draw_map_preview_street_label_bubble($canvas, $label, array $anchor, $fill_color, $text_color, $outline_color, $connector_color) {
        $label = $this->prepare_map_preview_text($label);

        if ('' === $label) {
            return;
        }

        $canvas_width = imagesx($canvas);
        $canvas_height = imagesy($canvas);
        $font_size = 16;
        $bubble_height = 40;
        $bubble_radius = 18;
        $padding_x = 16;
        $font_path = $this->get_map_preview_font_path();
        $max_text_width = 320;

        if ($font_path && function_exists('imagettfbbox')) {
            $label = $this->fit_map_preview_text_to_width($label, $font_size, $font_path, $max_text_width);
            $text_width = $this->get_map_preview_ttf_text_width($label, $font_size, $font_path);
        } else {
            $max_characters = max(10, (int) floor($max_text_width / 8));
            if (strlen($label) > $max_characters) {
                $label = substr($label, 0, $max_characters - 3) . '...';
            }

            $text_width = max(90, strlen(remove_accents($label)) * 8);
        }

        $bubble_width = max(150, min(360, $text_width + ($padding_x * 2)));
        $bubble_left = (int) round($anchor['x'] - ($bubble_width / 2));
        $bubble_top = (int) round($anchor['y'] - ($bubble_height / 2));
        $bubble_left = max(24, min($bubble_left, $canvas_width - $bubble_width - 24));
        $bubble_top = max(24, min($bubble_top, $canvas_height - $bubble_height - 190));
        $bubble_right = $bubble_left + $bubble_width;
        $bubble_bottom = $bubble_top + $bubble_height;
        $connector_start_x = (int) round($anchor['mid_x']);
        $connector_start_y = (int) round($anchor['mid_y']);
        $connector_end_x = (int) round(
            $connector_start_x < $bubble_left
                ? $bubble_left
                : ($connector_start_x > $bubble_right ? $bubble_right : $connector_start_x)
        );
        $connector_end_y = (int) round(
            $connector_start_y < $bubble_top
                ? $bubble_top
                : ($connector_start_y > $bubble_bottom ? $bubble_bottom : $connector_start_y)
        );

        imagesetthickness($canvas, 4);
        imageline($canvas, $connector_start_x, $connector_start_y, $connector_end_x, $connector_end_y, $connector_color);
        imagesetthickness($canvas, 1);
        $this->draw_map_preview_rounded_rectangle($canvas, $bubble_left, $bubble_top, $bubble_right, $bubble_bottom, $bubble_radius, $fill_color, true);
        $this->draw_map_preview_rounded_rectangle($canvas, $bubble_left, $bubble_top, $bubble_right, $bubble_bottom, $bubble_radius, $outline_color, false);
        $this->draw_map_preview_text(
            $canvas,
            $bubble_left + $padding_x,
            $bubble_top + 26,
            $label,
            $font_size,
            $text_color,
            $bubble_width - ($padding_x * 2)
        );
    }

    /**
     * @return resource|GdImage|false
     */
    private function create_map_preview_image_resource_from_path(string $file_path) {
        if ('' === $file_path || !file_exists($file_path) || !is_readable($file_path)) {
            return false;
        }

        $mime_type = '';

        if (function_exists('wp_check_filetype')) {
            $filetype = wp_check_filetype($file_path);
            $mime_type = isset($filetype['type']) ? (string) $filetype['type'] : '';
        }

        if ('' === $mime_type && function_exists('mime_content_type')) {
            $mime_type = (string) mime_content_type($file_path);
        }

        switch ($mime_type) {
            case 'image/jpeg':
                return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($file_path) : false;
            case 'image/png':
                return function_exists('imagecreatefrompng') ? @imagecreatefrompng($file_path) : false;
            case 'image/gif':
                return function_exists('imagecreatefromgif') ? @imagecreatefromgif($file_path) : false;
            case 'image/webp':
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file_path) : false;
        }

        if (!function_exists('imagecreatefromstring')) {
            return false;
        }

        $contents = @file_get_contents($file_path);

        return false !== $contents ? @imagecreatefromstring($contents) : false;
    }

    /**
     * @param resource|GdImage $canvas
     * @param resource|GdImage $image_resource
     */
    private function draw_map_preview_contain_image($canvas, $image_resource, int $left, int $top, int $width, int $height): void {
        if (!$canvas || !$image_resource || $width < 1 || $height < 1) {
            return;
        }

        $source_width = imagesx($image_resource);
        $source_height = imagesy($image_resource);

        if ($source_width < 1 || $source_height < 1) {
            return;
        }

        $source_ratio = $source_width / $source_height;
        $destination_ratio = $width / $height;
        $draw_width = $width;
        $draw_height = $height;

        if ($source_ratio > $destination_ratio) {
            $draw_height = (int) round($width / $source_ratio);
        } else {
            $draw_width = (int) round($height * $source_ratio);
        }

        $dest_x = (int) round($left + (($width - $draw_width) / 2));
        $dest_y = (int) round($top + (($height - $draw_height) / 2));

        imagecopyresampled($canvas, $image_resource, $dest_x, $dest_y, 0, 0, $draw_width, $draw_height, $source_width, $source_height);
    }

    private function draw_map_preview_station_marker($canvas, array $station_feature, array $point, $fill_color, $outline_color, $text_color, $connector_color) {
        $label = $this->prepare_map_preview_text((string) ($station_feature['label'] ?? __('Feuerwehrhaus', 'feuer-einsatzberichte')));

        if ('' === $label) {
            $label = __('Feuerwehrhaus', 'feuer-einsatzberichte');
        }

        $canvas_width = imagesx($canvas);
        $canvas_height = imagesy($canvas);
        $radius_outer = 16;
        $radius_inner = 8;
        $bubble_height = 38;
        $bubble_radius = 18;
        $padding_x = 18;
        $font_size = 16;
        $font_path = $this->get_map_preview_font_path();
        $max_text_width = 260;
        $logo_id = !empty($station_feature['logo_id']) ? absint($station_feature['logo_id']) : 0;
        $logo_size = max(24, min(64, absint($station_feature['logo_size'] ?? 40)));
        $logo_outer_size = $logo_id > 0 ? max(34, min(82, $logo_size + 18)) : ($radius_outer * 2);
        $logo_left = (int) round($point['x'] - ($logo_outer_size / 2));
        $logo_top = (int) round($point['y'] - ($logo_outer_size / 2));
        $logo_shell_radius = max(12, (int) round($logo_outer_size / 3));

        if ($font_path && function_exists('imagettfbbox')) {
            $label = $this->fit_map_preview_text_to_width($label, $font_size, $font_path, $max_text_width);
            $text_width = $this->get_map_preview_ttf_text_width($label, $font_size, $font_path);
        } else {
            $max_characters = max(10, (int) floor($max_text_width / 8));
            if (strlen($label) > $max_characters) {
                $label = substr($label, 0, $max_characters - 3) . '...';
            }

            $text_width = max(90, strlen(remove_accents($label)) * 8);
        }

        $bubble_width = max(168, min(300, $text_width + ($padding_x * 2)));
        $bubble_left = (int) round(min($canvas_width - $bubble_width - 24, max(24, $point['x'] + 22)));
        $bubble_top = (int) round(max(28, min($canvas_height - $bubble_height - 190, $point['y'] - 56)));
        $bubble_right = $bubble_left + $bubble_width;
        $bubble_bottom = $bubble_top + $bubble_height;
        $connector_end_x = $bubble_left;
        $connector_end_y = $bubble_top + (int) round($bubble_height / 2);

        imagesetthickness($canvas, 3);
        imageline(
            $canvas,
            (int) round($point['x']),
            (int) round($point['y']),
            $connector_end_x,
            $connector_end_y,
            $connector_color
        );
        imagesetthickness($canvas, 1);

        if ($logo_id > 0) {
            $logo_path = (string) get_attached_file($logo_id);
            $logo_resource = $this->create_map_preview_image_resource_from_path($logo_path);

            $this->draw_map_preview_rounded_rectangle(
                $canvas,
                $logo_left,
                $logo_top,
                $logo_left + $logo_outer_size,
                $logo_top + $logo_outer_size,
                $logo_shell_radius,
                $outline_color,
                true
            );

            if ($logo_resource) {
                $logo_padding = max(6, (int) round($logo_outer_size * 0.14));
                $this->draw_map_preview_contain_image(
                    $canvas,
                    $logo_resource,
                    $logo_left + $logo_padding,
                    $logo_top + $logo_padding,
                    $logo_outer_size - ($logo_padding * 2),
                    $logo_outer_size - ($logo_padding * 2)
                );
                imagedestroy($logo_resource);
            } else {
                imagefilledellipse($canvas, (int) round($point['x']), (int) round($point['y']), $radius_outer * 2, $radius_outer * 2, $outline_color);
                imagefilledellipse($canvas, (int) round($point['x']), (int) round($point['y']), $radius_inner * 2, $radius_inner * 2, $fill_color);
            }
        } else {
            imagefilledellipse($canvas, (int) round($point['x']), (int) round($point['y']), $radius_outer * 2, $radius_outer * 2, $outline_color);
            imagefilledellipse($canvas, (int) round($point['x']), (int) round($point['y']), $radius_inner * 2, $radius_inner * 2, $fill_color);
        }

        $this->draw_map_preview_rounded_rectangle($canvas, $bubble_left, $bubble_top, $bubble_right, $bubble_bottom, $bubble_radius, $outline_color, true);
        $this->draw_map_preview_rounded_rectangle($canvas, $bubble_left, $bubble_top, $bubble_right, $bubble_bottom, $bubble_radius, $connector_color, false);
        $this->draw_map_preview_text(
            $canvas,
            $bubble_left + $padding_x,
            $bubble_top + 24,
            $label,
            $font_size,
            $text_color,
            $bubble_width - ($padding_x * 2)
        );
    }

    private function apply_map_preview_overlay($canvas, $post_id, $address, $camera, $geometry_payload = false) {
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        $viewport = (is_array($camera) && !empty($camera['viewport'])) ? $camera['viewport'] : [
            'top_left_x' => 0,
            'top_left_y' => 0,
        ];
        $zoom = (is_array($camera) && isset($camera['zoom'])) ? $this->clamp_preview_zoom($camera['zoom']) : $this->clamp_preview_zoom(get_option('feu_einsatz_map_zoom', 16));
        $station_feature = $this->get_map_preview_station_feature();
        if (!$geometry_payload) {
            $geometry_payload = $this->get_map_preview_geometry($post_id);
        }
        $segments = $this->normalize_map_preview_geometry(
            is_array($geometry_payload) && !empty($geometry_payload['geometry']) ? $geometry_payload['geometry'] : []
        );

        $panel_color = imagecolorallocatealpha($canvas, 255, 255, 255, 18);
        $panel_border = imagecolorallocatealpha($canvas, 15, 23, 42, 84);
        $text_title = imagecolorallocate($canvas, 15, 23, 42);
        $text_subtitle = imagecolorallocate($canvas, 51, 65, 85);
        $attribution_panel = imagecolorallocate($canvas, 238, 240, 243);
        $attribution_border = imagecolorallocate($canvas, 180, 188, 200);
        $attribution_text_color = imagecolorallocate($canvas, 60, 72, 88);
        $road_stroke_width = $this->get_map_preview_stroke_width();
        $pedestrian_stroke_width = $this->get_map_preview_pedestrian_stroke_width();
        $road_glow_width = $road_stroke_width + 6;
        $pedestrian_glow_width = $pedestrian_stroke_width + 3;
        $station_fill = imagecolorallocate($canvas, 10, 75, 120);
        $station_outline = imagecolorallocatealpha($canvas, 255, 255, 255, 12);
        $station_text = imagecolorallocate($canvas, 15, 23, 42);
        $station_connector = imagecolorallocatealpha($canvas, 255, 255, 255, 52);
        $projected_segments = [];

        foreach ($segments as $segment) {
            $projected_points = [];

            foreach ($segment['points'] as $point) {
                $projected_points[] = $this->get_map_preview_screen_point((float) $point[0], (float) $point[1], $viewport, $zoom);
            }

            if (count($projected_points) < 2) {
                continue;
            }

            $projected_segments[] = [
                'points' => $projected_points,
                'kind' => $segment['kind'],
            ];
        }

        usort($projected_segments, static function($left, $right) {
            return ('pedestrian' === $left['kind'] ? 1 : 0) <=> ('pedestrian' === $right['kind'] ? 1 : 0);
        });

        $ss = 2;

        $glow_layer = imagecreatetruecolor($width * $ss, $height * $ss);
        imagealphablending($glow_layer, false);
        imagesavealpha($glow_layer, true);
        imagefill($glow_layer, 0, 0, imagecolorallocatealpha($glow_layer, 0, 0, 0, 127));
        $glow_color = imagecolorallocatealpha($glow_layer, 255, 255, 255, 34);

        foreach ($projected_segments as $segment) {
            $is_pedestrian = 'pedestrian' === $segment['kind'];
            $pts2x = array_map(static fn($p) => ['x' => $p['x'] * $ss, 'y' => $p['y'] * $ss], $segment['points']);
            $this->draw_map_preview_polyline(
                $glow_layer, $pts2x, $glow_color,
                ($is_pedestrian ? $pedestrian_glow_width : $road_glow_width) * $ss,
                $is_pedestrian
            );
        }

        $glow_aa = imagecreatetruecolor($width, $height);
        imagealphablending($glow_aa, false);
        imagesavealpha($glow_aa, true);
        imagefill($glow_aa, 0, 0, imagecolorallocatealpha($glow_aa, 0, 0, 0, 127));
        imagecopyresampled($glow_aa, $glow_layer, 0, 0, 0, 0, $width, $height, $width * $ss, $height * $ss);
        imagedestroy($glow_layer);
        imagealphablending($canvas, true);
        imagecopy($canvas, $glow_aa, 0, 0, 0, 0, $width, $height);
        imagedestroy($glow_aa);

        $hl_layer = imagecreatetruecolor($width * $ss, $height * $ss);
        imagealphablending($hl_layer, false);
        imagesavealpha($hl_layer, true);
        imagefill($hl_layer, 0, 0, imagecolorallocatealpha($hl_layer, 0, 0, 0, 127));
        imagealphablending($hl_layer, true);
        $road_hl_color = $this->allocate_map_preview_highlight_color($hl_layer, 10);
        $ped_hl_color  = $this->allocate_map_preview_highlight_color($hl_layer, 18);

        foreach ($projected_segments as $segment) {
            $is_pedestrian = 'pedestrian' === $segment['kind'];
            $pts2x = array_map(static fn($p) => ['x' => $p['x'] * $ss, 'y' => $p['y'] * $ss], $segment['points']);
            $this->draw_map_preview_polyline(
                $hl_layer, $pts2x,
                $is_pedestrian ? $ped_hl_color : $road_hl_color,
                ($is_pedestrian ? $pedestrian_stroke_width : $road_stroke_width) * $ss,
                $is_pedestrian
            );
        }

        $hl_aa = imagecreatetruecolor($width, $height);
        imagealphablending($hl_aa, false);
        imagesavealpha($hl_aa, true);
        imagefill($hl_aa, 0, 0, imagecolorallocatealpha($hl_aa, 0, 0, 0, 127));
        imagecopyresampled($hl_aa, $hl_layer, 0, 0, 0, 0, $width, $height, $width * $ss, $height * $ss);
        imagedestroy($hl_layer);
        imagealphablending($canvas, true);
        imagecopy($canvas, $hl_aa, 0, 0, 0, 0, $width, $height);
        imagedestroy($hl_aa);

        $street_label = $this->build_map_preview_street_label($post_id, $address);

        if ('' !== $street_label) {
            $street_label_anchor = $this->get_map_preview_label_anchor_from_projected_segments($projected_segments, $width, $height);

            if (is_array($street_label_anchor)) {
                $street_label_position = $this->get_map_preview_street_label_position();

                if ('auto' !== $street_label_position) {
                    $fixed_anchor = $this->get_map_preview_fixed_anchor($street_label_position, $width, $height);
                    $street_label_anchor['x'] = $fixed_anchor['x'];
                    $street_label_anchor['y'] = $fixed_anchor['y'];
                }

                $street_label_fill = $this->allocate_map_preview_highlight_color($canvas, 12);
                $street_label_outline = imagecolorallocatealpha($canvas, 255, 255, 255, 48);
                $street_label_connector = imagecolorallocatealpha($canvas, 255, 255, 255, 68);

                $this->draw_map_preview_street_label_bubble(
                    $canvas,
                    $street_label,
                    $street_label_anchor,
                    $street_label_fill,
                    imagecolorallocate($canvas, 255, 255, 255),
                    $street_label_outline,
                    $street_label_connector
                );
            }
        }

        if (
            is_array($station_feature)
            && isset($station_feature['latitude'], $station_feature['longitude'])
            && is_numeric($station_feature['latitude'])
            && is_numeric($station_feature['longitude'])
        ) {
            $station_point = $this->get_map_preview_screen_point(
                (float) $station_feature['latitude'],
                (float) $station_feature['longitude'],
                $viewport,
                $zoom
            );

            $this->draw_map_preview_station_marker(
                $canvas,
                $station_feature,
                $station_point,
                $station_fill,
                $station_outline,
                $station_text,
                $station_connector
            );
        }

        $show_panel = $this->is_map_preview_info_panel_enabled();
        $show_panel_heading = $this->is_map_preview_panel_heading_enabled();
        $show_panel_address = $this->is_map_preview_panel_address_enabled();

        if ($show_panel && ($show_panel_heading || $show_panel_address)) {
            $panel_width = 600;
            $panel_height = ($show_panel_heading && $show_panel_address) ? 134 : 84;
            $panel = $this->get_map_preview_box_coordinates($this->get_map_preview_panel_position(), $width, $height, $panel_width, $panel_height, 42);
            $text_x = $panel['left'] + 26;
            $text_y = $panel['top'] + ($show_panel_heading ? 50 : 48);

            imagefilledrectangle($canvas, $panel['left'], $panel['top'], $panel['right'], $panel['bottom'], $panel_color);
            imagerectangle($canvas, $panel['left'], $panel['top'], $panel['right'], $panel['bottom'], $panel_border);

            if ($show_panel_heading) {
                $this->draw_map_preview_text(
                    $canvas,
                    $text_x,
                    $text_y,
                    $this->get_map_preview_heading(),
                    28,
                    $text_title,
                    $panel_width - 60
                );
                $text_y += 44;
            }

            if ($show_panel_address) {
                $this->draw_map_preview_text(
                    $canvas,
                    $text_x,
                    $text_y,
                    $address,
                    20,
                    $text_subtitle,
                    $panel_width - 60
                );
            }
        }

        $font_path = $this->get_map_preview_font_path();
        $attribution_text = $this->get_map_preview_attribution_text();
        $attribution_font_size = 13;
        $attribution_max_width = 460;
        $attribution_padding_x = 12;
        $attribution_box_height = 30;
        $attribution_margin = 18;

        if (!$this->is_map_preview_attribution_enabled()) {
            return;
        }

        $attribution_text = $this->fit_map_preview_text_to_width($attribution_text, $attribution_font_size, $font_path, $attribution_max_width);

        if ($font_path && function_exists('imagettftext')) {
            $attribution_text_width = $this->get_map_preview_ttf_text_width($attribution_text, $attribution_font_size, $font_path);
            $attribution_box_width = max(280, $attribution_text_width + ($attribution_padding_x * 2));
            $attribution_box = $this->get_map_preview_box_coordinates($this->get_map_preview_attribution_position(), $width, $height, $attribution_box_width, $attribution_box_height, $attribution_margin);

            imagealphablending($canvas, true);
            imagefilledrectangle(
                $canvas,
                $attribution_box['left'],
                $attribution_box['top'],
                $attribution_box['right'],
                $attribution_box['bottom'],
                $attribution_panel
            );
            imagerectangle(
                $canvas,
                $attribution_box['left'],
                $attribution_box['top'],
                $attribution_box['right'],
                $attribution_box['bottom'],
                $attribution_border
            );

            $this->draw_map_preview_text(
                $canvas,
                $attribution_box['left'] + $attribution_padding_x,
                $attribution_box['top'] + 20,
                $attribution_text,
                $attribution_font_size,
                $attribution_text_color,
                $attribution_max_width
            );
        } else {
            $fallback_font = 4;
            $fallback_text_width = imagefontwidth($fallback_font) * strlen($attribution_text);
            $fallback_text_height = imagefontheight($fallback_font);
            $fallback_box_width = $fallback_text_width + 24;
            $fallback_box_height = $fallback_text_height + 14;
            $fallback_box = $this->get_map_preview_box_coordinates($this->get_map_preview_attribution_position(), $width, $height, $fallback_box_width, $fallback_box_height, $attribution_margin);

            imagefilledrectangle(
                $canvas,
                $fallback_box['left'],
                $fallback_box['top'],
                $fallback_box['right'],
                $fallback_box['bottom'],
                $attribution_panel
            );
            imagerectangle(
                $canvas,
                $fallback_box['left'],
                $fallback_box['top'],
                $fallback_box['right'],
                $fallback_box['bottom'],
                $attribution_border
            );
            imagestring(
                $canvas,
                $fallback_font,
                $fallback_box['left'] + 12,
                $fallback_box['top'] + 7,
                $attribution_text,
                $attribution_text_color
            );
        }
    }

    private function encode_generated_map_canvas_as_png($canvas) {
        if (!is_resource($canvas) && !is_object($canvas)) {
            return new WP_Error(
                'feu_einsatz_map_preview_canvas_missing',
                __('Die Kartenvorschau konnte nicht in ein Bild umgewandelt werden.', 'feuer-einsatzberichte')
            );
        }

        ob_start();
        imagepng($canvas, null, 6);
        $contents = ob_get_clean();

        if (false === $contents || '' === $contents) {
            return new WP_Error(
                'feu_einsatz_map_preview_png_failed',
                __('Die Kartenvorschau konnte nicht als PNG gespeichert werden.', 'feuer-einsatzberichte')
            );
        }

        return $contents;
    }

    private function store_generated_map_canvas_as_attachment($post_id, $canvas) {
        $contents = $this->encode_generated_map_canvas_as_png($canvas);

        if (is_wp_error($contents)) {
            return $contents;
        }

        return $this->store_generated_map_preview_attachment($post_id, $contents, 'png', 'image/png');
    }

    private function store_generated_map_canvas_as_preview_file($post_id, $canvas) {
        $contents = $this->encode_generated_map_canvas_as_png($canvas);

        if (is_wp_error($contents)) {
            return $contents;
        }

        return $this->store_generated_map_preview_file($post_id, $contents, 'png');
    }

    private function build_svg_map_preview($post_id, $address, $coordinates, $geometry_payload = false) {
        if (!$geometry_payload) {
            $geometry_payload = $this->get_map_preview_geometry($post_id);
        }
        $station_feature = $this->get_map_preview_station_feature();
        $segments = $this->normalize_map_preview_geometry(
            is_array($geometry_payload) && !empty($geometry_payload['geometry']) ? $geometry_payload['geometry'] : []
        );
        $width = 1200;
        $height = 630;
        $padding = 56;
        $all_points = [];

        foreach ($segments as $segment) {
            foreach ($segment['points'] as $point) {
                $all_points[] = [(float) $point[0], (float) $point[1]];
            }
        }

        if (
            is_array($station_feature)
            && isset($station_feature['latitude'], $station_feature['longitude'])
            && is_numeric($station_feature['latitude'])
            && is_numeric($station_feature['longitude'])
        ) {
            $all_points[] = [
                (float) $station_feature['latitude'],
                (float) $station_feature['longitude'],
            ];
        }

        if (empty($all_points)) {
            $lat = (float) $coordinates['lat'];
            $lng = (float) $coordinates['lng'];
            $delta = 0.0028;
            $all_points[] = [$lat - $delta, $lng - $delta];
            $all_points[] = [$lat + $delta, $lng + $delta];
        }

        $lats = array_column($all_points, 0);
        $lngs = array_column($all_points, 1);
        $min_lat = min($lats);
        $max_lat = max($lats);
        $min_lng = min($lngs);
        $max_lng = max($lngs);

        if ($min_lat === $max_lat) {
            $min_lat -= 0.001;
            $max_lat += 0.001;
        }

        if ($min_lng === $max_lng) {
            $min_lng -= 0.001;
            $max_lng += 0.001;
        }

        $lat_padding = ($max_lat - $min_lat) * 0.15;
        $lng_padding = ($max_lng - $min_lng) * 0.15;
        $min_lat -= $lat_padding;
        $max_lat += $lat_padding;
        $min_lng -= $lng_padding;
        $max_lng += $lng_padding;

        $project_point = static function($lat, $lng) use ($min_lat, $max_lat, $min_lng, $max_lng, $width, $height, $padding) {
            $usable_width = $width - ($padding * 2);
            $usable_height = $height - ($padding * 2);
            $x = $padding + ((($lng - $min_lng) / ($max_lng - $min_lng)) * $usable_width);
            $y = $padding + ((1 - (($lat - $min_lat) / ($max_lat - $min_lat))) * $usable_height);

            return [round($x, 2), round($y, 2)];
        };

        $projected_segments = [];
        $svg_halos = [];
        $svg_lines = [];
        $highlight_hex = $this->get_map_preview_highlight_hex_color();
        $font_css_stack = $this->get_map_preview_font_css_stack();
        $road_stroke_width = $this->get_map_preview_stroke_width();
        $pedestrian_stroke_width = $this->get_map_preview_pedestrian_stroke_width();
        $road_glow_width = $road_stroke_width + 6;
        $pedestrian_glow_width = $pedestrian_stroke_width + 3;

        foreach ($segments as $segment) {
            $points = [];
            $projected_points = [];

            foreach ($segment['points'] as $point) {
                $projected = $project_point((float) $point[0], (float) $point[1]);
                $points[] = $projected[0] . ',' . $projected[1];
                $projected_points[] = [
                    'x' => (float) $projected[0],
                    'y' => (float) $projected[1],
                ];
            }

            if (count($points) > 1) {
                $polyline = implode(' ', $points);
                $is_pedestrian = 'pedestrian' === $segment['kind'];
                $dash_attr = $is_pedestrian ? ' stroke-dasharray="18 12"' : '';
                $svg_halos[] = '<polyline points="' . $polyline . '" fill="none" stroke="#ffffff" stroke-width="' . ($is_pedestrian ? $pedestrian_glow_width : $road_glow_width) . '" stroke-linecap="round" stroke-linejoin="round" opacity="0.72"' . $dash_attr . ' />';
                $svg_lines[] = '<polyline points="' . $polyline . '" fill="none" stroke="' . esc_attr($highlight_hex) . '" stroke-width="' . ($is_pedestrian ? $pedestrian_stroke_width : $road_stroke_width) . '" stroke-linecap="round" stroke-linejoin="round" opacity="0.96"' . $dash_attr . ' />';
                $projected_segments[] = [
                    'points' => $projected_points,
                    'kind' => $segment['kind'],
                ];
            }
        }

        $safe_title = esc_html(get_the_title($post_id));
        $safe_address = esc_html($address);
        $safe_heading = esc_html($this->get_map_preview_heading());
        $safe_attribution = esc_html($this->get_map_preview_attribution_text());
        $street_label = $this->build_map_preview_street_label($post_id, $address);
        $street_label_markup = '';
        $station_markup = '';
        $panel_markup = '';
        $attribution_markup = '';
        $grid_lines = [];

        for ($i = 1; $i <= 5; $i++) {
            $grid_x = round(($width / 6) * $i, 2);
            $grid_y = round(($height / 6) * $i, 2);
            $grid_lines[] = '<line x1="' . $grid_x . '" y1="0" x2="' . $grid_x . '" y2="' . $height . '" stroke="#0d243f" stroke-opacity="0.08" stroke-width="1" />';
            $grid_lines[] = '<line x1="0" y1="' . $grid_y . '" x2="' . $width . '" y2="' . $grid_y . '" stroke="#0d243f" stroke-opacity="0.08" stroke-width="1" />';
        }

        if ('' !== $street_label) {
            $street_label_anchor = $this->get_map_preview_label_anchor_from_projected_segments($projected_segments, $width, $height);

            if (is_array($street_label_anchor)) {
                $street_label_position = $this->get_map_preview_street_label_position();

                if ('auto' !== $street_label_position) {
                    $fixed_anchor = $this->get_map_preview_fixed_anchor($street_label_position, $width, $height);
                    $street_label_anchor['x'] = $fixed_anchor['x'];
                    $street_label_anchor['y'] = $fixed_anchor['y'];
                }

                $text_length = function_exists('mb_strlen')
                    ? mb_strlen($street_label, 'UTF-8')
                    : strlen($street_label);
                $bubble_width = max(150, min(360, (int) round(($text_length * 9.8) + 34)));
                $bubble_height = 40;
                $bubble_x = (int) round($street_label_anchor['x'] - ($bubble_width / 2));
                $bubble_y = (int) round($street_label_anchor['y'] - ($bubble_height / 2));
                $bubble_x = max(24, min($bubble_x, $width - $bubble_width - 24));
                $bubble_y = max(24, min($bubble_y, $height - $bubble_height - 190));
                $bubble_center_x = $bubble_x + ($bubble_width / 2);
                $bubble_center_y = $bubble_y + ($bubble_height / 2);
                $safe_street_label = esc_html($street_label);

                $street_label_markup = '<g>'
                    . '<line x1="' . round($street_label_anchor['mid_x'], 2) . '" y1="' . round($street_label_anchor['mid_y'], 2) . '" x2="' . round($bubble_center_x, 2) . '" y2="' . round($bubble_center_y, 2) . '" stroke="#ffffff" stroke-opacity="0.36" stroke-width="4" stroke-linecap="round" />'
                    . '<rect x="' . $bubble_x . '" y="' . $bubble_y . '" width="' . $bubble_width . '" height="' . $bubble_height . '" rx="18" fill="' . esc_attr($highlight_hex) . '" fill-opacity="0.94" stroke="#ffffff" stroke-opacity="0.42" />'
                    . '<text x="' . ($bubble_x + 16) . '" y="' . ($bubble_y + 25) . '" font-family="' . esc_attr($font_css_stack) . '" font-size="16" font-weight="600" fill="#ffffff">' . $safe_street_label . '</text>'
                    . '</g>';
            }
        }

        if (
            is_array($station_feature)
            && isset($station_feature['latitude'], $station_feature['longitude'])
            && is_numeric($station_feature['latitude'])
            && is_numeric($station_feature['longitude'])
        ) {
            $station_point = $project_point((float) $station_feature['latitude'], (float) $station_feature['longitude']);
            $station_label = esc_html(
                wp_html_excerpt(
                    $this->prepare_map_preview_text((string) ($station_feature['label'] ?? __('Feuerwehrhaus', 'feuer-einsatzberichte'))),
                    28,
                    '...'
                )
            );
            $station_bubble_width = max(170, min(280, (int) round((strlen(remove_accents((string) wp_strip_all_tags($station_label))) * 8.2) + 68)));
            $station_bubble_x = max(24, min((int) round($station_point[0] + 22), $width - $station_bubble_width - 24));
            $station_bubble_y = max(40, min((int) round($station_point[1] - 56), $height - 224));

            $station_markup = '<g>'
                . '<line x1="' . $station_point[0] . '" y1="' . $station_point[1] . '" x2="' . $station_bubble_x . '" y2="' . ($station_bubble_y + 19) . '" stroke="#ffffff" stroke-opacity="0.52" stroke-width="3" stroke-linecap="round" />'
                . '<circle cx="' . $station_point[0] . '" cy="' . $station_point[1] . '" r="16" fill="#ffffff" fill-opacity="0.94" stroke="#0f172a" stroke-opacity="0.12" stroke-width="2" />'
                . '<circle cx="' . $station_point[0] . '" cy="' . $station_point[1] . '" r="8" fill="#0a4b78" />'
                . '<rect x="' . $station_bubble_x . '" y="' . $station_bubble_y . '" width="' . $station_bubble_width . '" height="38" rx="19" fill="#ffffff" fill-opacity="0.94" stroke="#0f172a" stroke-opacity="0.12" />'
                . '<text x="' . ($station_bubble_x + 18) . '" y="' . ($station_bubble_y + 24) . '" font-family="' . esc_attr($font_css_stack) . '" font-size="16" font-weight="700" fill="#0f172a">' . $station_label . '</text>'
                . '</g>';
        }

        $show_panel = $this->is_map_preview_info_panel_enabled();
        $show_panel_heading = $this->is_map_preview_panel_heading_enabled();
        $show_panel_address = $this->is_map_preview_panel_address_enabled();

        if ($show_panel && ($show_panel_heading || $show_panel_address)) {
            $panel_width = 600;
            $panel_height = ($show_panel_heading && $show_panel_address) ? 112 : 74;
            $panel = $this->get_map_preview_box_coordinates($this->get_map_preview_panel_position(), $width, $height, $panel_width, $panel_height, 52);
            $text_y = $panel['top'] + ($show_panel_heading ? 50 : 46);
            $panel_markup = '<g><rect x="' . $panel['left'] . '" y="' . $panel['top'] . '" width="' . $panel_width . '" height="' . $panel_height . '" rx="18" fill="#ffffff" fill-opacity="0.94" stroke="#0f172a" stroke-opacity="0.10" />';

            if ($show_panel_heading) {
                $panel_markup .= '<text x="' . ($panel['left'] + 28) . '" y="' . $text_y . '" font-family="' . esc_attr($font_css_stack) . '" font-size="28" font-weight="700" fill="#0f172a">' . $safe_heading . '</text>';
                $text_y += 40;
            }

            if ($show_panel_address) {
                $panel_markup .= '<text x="' . ($panel['left'] + 28) . '" y="' . $text_y . '" font-family="' . esc_attr($font_css_stack) . '" font-size="20" fill="#334155">' . $safe_address . '</text>';
            }

            $panel_markup .= '</g>';
        }

        if ($this->is_map_preview_attribution_enabled()) {
            $attribution_width = 556;
            $attribution_height = 36;
            $attribution = $this->get_map_preview_box_coordinates($this->get_map_preview_attribution_position(), $width, $height, $attribution_width, $attribution_height, 32);
            $attribution_markup = '<g><rect x="' . $attribution['left'] . '" y="' . $attribution['top'] . '" width="' . $attribution_width . '" height="' . $attribution_height . '" rx="18" fill="#ffffff" fill-opacity="0.94" stroke="#0f172a" stroke-opacity="0.14" />'
                . '<text x="' . ($attribution['right'] - 16) . '" y="' . ($attribution['top'] + 24) . '" font-family="' . esc_attr($font_css_stack) . '" font-size="16" font-weight="700" text-anchor="end" fill="#3c4858">' . $safe_attribution . '</text></g>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-labelledby="title desc">'
            . '<title id="title">' . $safe_title . '</title>'
            . '<desc id="desc">' . $safe_address . '</desc>'
            . '<defs>'
            . '<linearGradient id="ebBg" x1="0" y1="0" x2="1" y2="1">'
            . '<stop offset="0%" stop-color="#f8fbff" />'
            . '<stop offset="100%" stop-color="#e7f1fb" />'
            . '</linearGradient>'
            . '<filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">'
            . '<feDropShadow dx="0" dy="12" stdDeviation="18" flood-color="#0f172a" flood-opacity="0.14" />'
            . '</filter>'
            . '</defs>'
            . '<rect width="' . $width . '" height="' . $height . '" rx="28" fill="url(#ebBg)" />'
            . implode('', $grid_lines)
            . '<g filter="url(#shadow)"><rect x="30" y="30" width="' . ($width - 60) . '" height="' . ($height - 60) . '" rx="24" fill="#ffffff" fill-opacity="0.72" stroke="#0d243f" stroke-opacity="0.08" /></g>'
            . '<g>' . implode('', $svg_halos) . implode('', $svg_lines) . '</g>'
            . $street_label_markup
            . $station_markup
            . $panel_markup
            . $attribution_markup
            . '</svg>';
    }

    private function store_generated_map_preview_attachment($post_id, $contents, $extension = 'svg', $mime_type = 'image/svg+xml') {
        $filename = 'feuer-einsatzberichte-map-' . $post_id . '-' . time() . '.' . $extension;
        $this->generated_map_upload_subdir = ltrim($this->get_generated_map_upload_subdir($post_id), '/');
        add_filter('upload_dir', [$this, 'filter_generated_map_upload_dir']);
        $upload = wp_upload_bits($filename, null, $contents);
        remove_filter('upload_dir', [$this, 'filter_generated_map_upload_dir']);
        $this->generated_map_upload_subdir = '';

        if (!empty($upload['error'])) {
            return new WP_Error(
                'feu_einsatz_map_preview_store_failed',
                __('Die Kartenvorschau konnte nicht im Upload-Ordner gespeichert werden.', 'feuer-einsatzberichte')
            );
        }

        $attachment_id = wp_insert_attachment(
            [
                'guid' => $upload['url'],
                'post_mime_type' => $mime_type,
                'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit',
            ],
            $upload['file'],
            $post_id
        );

        if (is_wp_error($attachment_id) || !$attachment_id) {
            @unlink($upload['file']);

            return new WP_Error(
                'feu_einsatz_map_preview_attachment_failed',
                __('Die Kartenvorschau wurde erstellt, konnte aber nicht als Anhang gespeichert werden.', 'feuer-einsatzberichte')
            );
        }

        update_post_meta($attachment_id, self::GENERATED_MAP_ATTACHMENT_META, '1');

        if (0 === strpos((string) $mime_type, 'image/')) {
            $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);

            if (!is_wp_error($attachment_metadata) && !empty($attachment_metadata)) {
                wp_update_attachment_metadata($attachment_id, $attachment_metadata);
            }
        }

        return (int) $attachment_id;
    }

    private function store_generated_map_preview_file($post_id, $contents, $extension = 'svg') {
        $storage = $this->get_generated_map_storage_paths($post_id);

        if (is_wp_error($storage)) {
            return $storage;
        }

        $preview_dir = $storage['dir'];
        $preview_url_base = $storage['url'];

        if (!wp_mkdir_p($preview_dir)) {
            return new WP_Error(
                'feu_einsatz_map_preview_directory_failed',
                __('Der Ordner für die Kartenvorschau konnte nicht angelegt werden.', 'feuer-einsatzberichte')
            );
        }

        $preview_file = trailingslashit($preview_dir) . 'feuer-einsatzberichte-map-preview-' . $post_id . '.' . $extension;
        $bytes_written = file_put_contents($preview_file, $contents);

        if (false === $bytes_written) {
            return new WP_Error(
                'feu_einsatz_map_preview_write_failed',
                __('Die lokale Kartenvorschau konnte nicht gespeichert werden.', 'feuer-einsatzberichte')
            );
        }

        update_post_meta($post_id, self::GENERATED_MAP_PREVIEW_FILE_META, $preview_file);
        update_post_meta($post_id, self::GENERATED_MAP_PREVIEW_URL_META, trailingslashit($preview_url_base) . wp_basename($preview_file));
        $this->report_share->invalidate_share_card_cache((int) $post_id);

        return [
            'attachment_id' => 0,
            'url' => get_post_meta($post_id, self::GENERATED_MAP_PREVIEW_URL_META, true),
            'mode' => 'fallback_file',
        ];
    }

    private function generate_map_image($post_id, $address, $context = []) {
        $address = trim((string) $address);
        if ('' === $address) {
            return new WP_Error(
                'feu_einsatz_missing_address',
                __('Bitte geben Sie zuerst eine gültige Adresse ein.', 'feuer-einsatzberichte')
            );
        }

        $preview_context = $this->get_map_preview_context($post_id, $context);
        $generated_map_signature = $this->build_generated_map_signature(
            $preview_context['street'] ?? '',
            $preview_context['plz'] ?? '',
            $preview_context['city'] ?? 'Hamburg'
        );
        $coordinates = !empty($preview_context['coordinates']) ? $preview_context['coordinates'] : false;
        $geometry_payload = !empty($preview_context['geometry']) ? $preview_context['geometry'] : false;
        $prefer_local_canvas = !empty($context['prefer_local_canvas']);
        $fast_tile_mode = !empty($context['fast_tile_mode']);

        if ($prefer_local_canvas && !$geometry_payload) {
            $geometry_payload = [
                'geometry' => [],
                'center' => [],
            ];
        }

        if (!$coordinates) {
            $coordinates = $this->get_map_image_coordinates($post_id, $address, $context);

            if (is_wp_error($coordinates)) {
                return $coordinates;
            }
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $map_dimensions = $this->get_map_preview_dimensions();
        $camera = $this->resolve_map_preview_camera(
            $post_id,
            $coordinates,
            $map_dimensions['width'],
            $map_dimensions['height'],
            get_option('feu_einsatz_map_zoom', 16),
            $geometry_payload
        );

        $canvas = false;

        if ($prefer_local_canvas) {
            $canvas = $this->build_local_map_preview_canvas(
                $post_id,
                $address,
                $camera,
                $geometry_payload,
                $map_dimensions['width'],
                $map_dimensions['height']
            );
        } else {
            $canvas = $this->download_static_map_canvas($camera['center'], $camera['zoom'], $map_dimensions['width'], $map_dimensions['height']);

            if (!$canvas) {
                $canvas = $this->download_tile_map_canvas(
                    $camera,
                    $map_dimensions['width'],
                    $map_dimensions['height'],
                    $fast_tile_mode
                        ? [
                            'timeout' => 5,
                            'max_tiles' => 24,
                        ]
                        : [
                            'timeout' => 8,
                            'max_tiles' => 30,
                        ]
                );
            }

            if (!$canvas) {
                $canvas = $this->build_local_map_preview_canvas(
                    $post_id,
                    $address,
                    $camera,
                    $geometry_payload,
                    $map_dimensions['width'],
                    $map_dimensions['height']
                );
            } else {
                $this->apply_map_preview_overlay($canvas, $post_id, $address, $camera, $geometry_payload);
            }
        }

        if ($canvas) {
            $attachment_id = $this->store_generated_map_canvas_as_attachment($post_id, $canvas);

            if (!is_wp_error($attachment_id) && $attachment_id) {
                $assign_as_featured_image = $this->should_assign_generated_map_as_featured_image($post_id);
                imagedestroy($canvas);

                if ($assign_as_featured_image) {
                    set_post_thumbnail($post_id, $attachment_id);
                }

                update_post_meta($attachment_id, self::GENERATED_MAP_ATTACHMENT_META, '1');
                update_post_meta($post_id, self::GENERATED_MAP_SIGNATURE_META, $generated_map_signature);
                $this->cleanup_previous_generated_map_thumbnail($post_id, $attachment_id);
                $this->cleanup_generated_map_preview_file($post_id);
                $this->report_share->invalidate_share_card_cache((int) $post_id);

                return [
                    'attachment_id' => (int) $attachment_id,
                    'url' => wp_get_attachment_url($attachment_id),
                    'mode' => $assign_as_featured_image ? 'featured_image' : 'generated_attachment',
                ];
            }

            $png_preview = $this->store_generated_map_canvas_as_preview_file($post_id, $canvas);
            imagedestroy($canvas);

            if (!is_wp_error($png_preview)) {
                update_post_meta($post_id, self::GENERATED_MAP_SIGNATURE_META, $generated_map_signature);
                $this->cleanup_previous_generated_map_thumbnail($post_id);
                $this->report_share->invalidate_share_card_cache((int) $post_id);

                return $png_preview;
            }
        }

        $svg_preview = $this->store_generated_map_preview_file(
            $post_id,
            $this->build_svg_map_preview($post_id, $address, $coordinates, $geometry_payload)
        );

        if (is_wp_error($svg_preview)) {
            return $svg_preview;
        }

        update_post_meta($post_id, self::GENERATED_MAP_SIGNATURE_META, $generated_map_signature);
        $this->cleanup_previous_generated_map_thumbnail($post_id);
        $this->report_share->invalidate_share_card_cache((int) $post_id);

        return $svg_preview;
    }

    public function add_custom_columns($columns) {
        $new_columns = [];
        $is_report_list_screen = isset($_GET['feu_einsatz_filter']) && '1' === sanitize_text_field(wp_unslash($_GET['feu_einsatz_filter']));

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ('title' === $key) {
                if ($is_report_list_screen) {
                    $new_columns['einsatz_preview'] = __('Kartenbild', 'feuer-einsatzberichte');
                }
                $new_columns['einsatz_datum'] = __('Einsatzdatum', 'feuer-einsatzberichte');
                $new_columns['einsatz_strasse'] = __('Straße', 'feuer-einsatzberichte');
            }
        }

        return $new_columns;
    }

    private function get_admin_preview_image_url($post_id, $size = 'thumbnail') {
        $post_id = absint($post_id);
        if (!$post_id) {
            return '';
        }

        $thumbnail_id = (int) get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $thumbnail_url = wp_get_attachment_image_url($thumbnail_id, $size);

            if ($thumbnail_url) {
                return $thumbnail_url;
            }
        }

        $preview_url = trim((string) get_post_meta($post_id, self::GENERATED_MAP_PREVIEW_URL_META, true));

        return $preview_url;
    }

    public function render_custom_columns($column, $post_id) {
        if ('1' !== get_post_meta($post_id, '_feu_einsatz_einsatzbericht', true)) {
            return;
        }

        switch ($column) {
            case 'einsatz_preview':
                $preview_image_url = $this->get_admin_preview_image_url($post_id, 'thumbnail');

                if ($preview_image_url) {
                    echo '<div class="feu-einsatz-admin-list-preview">';
                    echo '<img src="' . esc_url($preview_image_url) . '" alt="" class="feu-einsatz-admin-list-preview-image" />';
                    echo '</div>';
                } else {
                    echo '<span class="feu-einsatz-admin-list-preview-empty">' . esc_html__('Kein Kartenbild', 'feuer-einsatzberichte') . '</span>';
                }
                break;

            case 'einsatz_datum':
                $datum = get_post_meta($post_id, '_feu_einsatz_datum', true);
                echo $datum ? esc_html(date_i18n('d.m.Y', strtotime($datum))) : '-';
                break;

            case 'einsatz_strasse':
                $strasse = get_post_meta($post_id, '_feu_einsatz_strasse', true);
                $strasse = FEU_Einsatz_Template_Helpers::strip_house_number_from_street($strasse);
                echo $strasse ? esc_html($strasse) : '-';
                break;
        }
    }

    public function render_dashboard() {
        if (!self::current_user_can_access_plugin_section('dashboard')) {
            $fallback_url = $this->get_first_accessible_plugin_admin_url();

            if ('' !== $fallback_url) {
                wp_safe_redirect($fallback_url);
                exit;
            }

            wp_die(esc_html__('Keine Berechtigung', 'feuer-einsatzberichte'));
        }

        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    private function get_generated_map_dashboard_bucket(array $status): string {
        $status_key = sanitize_key((string) ($status['status'] ?? 'idle'));
        $stage = sanitize_key((string) ($status['stage'] ?? ''));

        if ('error' === $status_key) {
            return 'error';
        }

        if ('geocode' === $stage) {
            return 'geocode';
        }

        if (in_array($status_key, ['queued', 'processing'], true)) {
            return 'map';
        }

        return 'idle';
    }

    private function get_generated_map_dashboard_status_label(array $status): string {
        $bucket = $this->get_generated_map_dashboard_bucket($status);
        $status_key = sanitize_key((string) ($status['status'] ?? 'idle'));

        if ('error' === $bucket) {
            return __('Fehler', 'feuer-einsatzberichte');
        }

        if ('geocode' === $bucket) {
            return 'processing' === $status_key
                ? __('Geocoding laeuft', 'feuer-einsatzberichte')
                : __('Wartet auf Geocoding', 'feuer-einsatzberichte');
        }

        if ('map' === $bucket) {
            return 'processing' === $status_key
                ? __('Karte wird erstellt', 'feuer-einsatzberichte')
                : __('Wartet auf Kartenbild', 'feuer-einsatzberichte');
        }

        return __('Keine Aktion', 'feuer-einsatzberichte');
    }

    private function get_generated_map_dashboard_overview($limit = 6, $scan_limit = 30): array {
        $limit = max(1, absint($limit));
        $scan_limit = max($limit, absint($scan_limit));
        $reports = get_posts([
            'post_type' => 'post',
            'post_status' => ['draft', 'future', 'publish', 'pending', 'private'],
            'posts_per_page' => $scan_limit,
            'meta_query' => [
                [
                    'key' => '_feu_einsatz_einsatzbericht',
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);

        $counters = [
            'geocode' => 0,
            'map' => 0,
            'error' => 0,
            'total' => 0,
        ];
        $items = [];

        foreach ($reports as $report) {
            if (!($report instanceof WP_Post)) {
                continue;
            }

            $status = $this->get_generated_map_admin_status((int) $report->ID);
            $bucket = $this->get_generated_map_dashboard_bucket($status);

            if (!in_array($bucket, ['geocode', 'map', 'error'], true)) {
                continue;
            }

            $counters[$bucket]++;
            $counters['total']++;

            if (count($items) >= $limit) {
                continue;
            }

            $status['bucket'] = $bucket;
            $status['dashboard_label'] = $this->get_generated_map_dashboard_status_label($status);

            $items[] = [
                'post' => $report,
                'status' => $status,
            ];
        }

        return [
            'items' => $items,
            'counters' => $counters,
        ];
    }

    private function current_user_can_manage_generated_map($post_id = 0) {
        $post_id = absint($post_id);

        if (!current_user_can('edit_posts')) {
            return false;
        }

        if ($post_id > 0 && !current_user_can('edit_post', $post_id)) {
            return false;
        }

        return true;
    }

    public function render_wp_dashboard_statistics_widget(): void {
        $total_reports = (int) $this->db->count_report_posts();
        $current_year = (int) current_time('Y');
        $reports_this_year = (int) $this->db->count_report_posts($current_year);
        $participants_total = count($this->db->get_participants([
            'include_archived' => false,
            'include_deleted' => false,
        ]));
        $categories_total = count((array) get_option('feu_einsatz_categories', []));

        echo '<p class="feu-einsatz-dashboard-widget-note">' . esc_html__('Die wichtigsten Kennzahlen direkt auf dem WordPress-Dashboard.', 'feuer-einsatzberichte') . '</p>';
        echo '<div class="feu-einsatz-dashboard-widget-grid">';
        echo '<div class="feu-einsatz-dashboard-stat"><strong>' . esc_html($total_reports) . '</strong><span>' . esc_html__('Einsätze gesamt', 'feuer-einsatzberichte') . '</span></div>';
        echo '<div class="feu-einsatz-dashboard-stat"><strong>' . esc_html($reports_this_year) . '</strong><span>' . esc_html(sprintf(__('Einsätze %d', 'feuer-einsatzberichte'), $current_year)) . '</span></div>';
        echo '<div class="feu-einsatz-dashboard-stat"><strong>' . esc_html($participants_total) . '</strong><span>' . esc_html__('Aktive Teilnehmer', 'feuer-einsatzberichte') . '</span></div>';
        echo '<div class="feu-einsatz-dashboard-stat"><strong>' . esc_html($categories_total) . '</strong><span>' . esc_html__('Konfigurierte Kategorien', 'feuer-einsatzberichte') . '</span></div>';
        echo '</div>';

        if (self::current_user_can_access_plugin_section('statistics')) {
            echo '<div class="feu-einsatz-dashboard-widget-links">';
            echo '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=feu-einsatz-statistiken')) . '">' . esc_html__('Statistiken öffnen', 'feuer-einsatzberichte') . '</a>';
            echo '</div>';
        }
    }

    public function render_wp_dashboard_map_queue_widget(): void {
        $flash_type = isset($_GET['feu_map_generation']) ? sanitize_key(wp_unslash($_GET['feu_map_generation'])) : '';
        $flash_message = isset($_GET['feu_map_message']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['feu_map_message']))) : '';
        $map_queue_overview = $this->get_generated_map_dashboard_overview(6, 30);
        $items = isset($map_queue_overview['items']) && is_array($map_queue_overview['items']) ? $map_queue_overview['items'] : [];
        $counters = isset($map_queue_overview['counters']) && is_array($map_queue_overview['counters']) ? $map_queue_overview['counters'] : ['geocode' => 0, 'map' => 0, 'error' => 0, 'total' => 0];

        if ('' !== $flash_message && in_array($flash_type, ['success', 'error'], true)) {
            echo '<div class="notice inline ' . ('success' === $flash_type ? 'notice-success' : 'notice-error') . '"><p>' . esc_html($flash_message) . '</p></div>';
        }

        echo '<p class="feu-einsatz-dashboard-widget-note">' . esc_html(sprintf(
            __('Geocoding: %1$d | Kartenbild: %2$d | Fehler: %3$d', 'feuer-einsatzberichte'),
            (int) ($counters['geocode'] ?? 0),
            (int) ($counters['map'] ?? 0),
            (int) ($counters['error'] ?? 0)
        )) . '</p>';

        if (empty($items)) {
            echo '<p>' . esc_html__('Aktuell warten keine Berichte auf Geocoding oder Kartenbild-Generierung.', 'feuer-einsatzberichte') . '</p>';
            return;
        }

        echo '<div class="feu-einsatz-dashboard-mapqueue-list">';

        foreach ($items as $item) {
            $report = $item['post'];
            $status = $item['status'];
            $post_id = (int) $report->ID;
            $status_key = sanitize_key((string) ($status['bucket'] ?? 'idle'));
            $status_label = (string) ($status['dashboard_label'] ?? __('Status', 'feuer-einsatzberichte'));

            echo '<div class="feu-einsatz-dashboard-mapqueue-item">';
            echo '<div class="feu-einsatz-dashboard-mapqueue-main">';
            echo '<div class="feu-einsatz-dashboard-mapqueue-head">';
            echo '<a class="feu-einsatz-dashboard-mapqueue-title" href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html(get_the_title($post_id)) . '</a>';
            echo '<span class="feu-einsatz-dashboard-mapqueue-status feu-einsatz-dashboard-mapqueue-status--' . esc_attr($status_key) . '">' . esc_html($status_label) . '</span>';
            echo '</div>';
            echo '<div class="feu-einsatz-dashboard-mapqueue-meta">' . esc_html((string) ($status['message'] ?? '')) . '</div>';
            echo '</div>';
            echo '<div class="feu-einsatz-dashboard-mapqueue-actions">';
            echo '<a class="button button-secondary button-small" href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html__('Bearbeiten', 'feuer-einsatzberichte') . '</a>';
            echo '<form class="feu-einsatz-dashboard-inline-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('feu_einsatz_generate_map_image_now_' . $post_id);
            echo '<input type="hidden" name="action" value="feu_einsatz_generate_map_image_now" />';
            echo '<input type="hidden" name="post_id" value="' . esc_attr((string) $post_id) . '" />';
            echo '<input type="hidden" name="redirect_to" value="' . esc_attr(admin_url('index.php')) . '" />';
            echo '<button type="submit" class="button button-primary button-small">' . esc_html__('Jetzt generieren', 'feuer-einsatzberichte') . '</button>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function render_wp_dashboard_recent_reports_widget(): void {
        $recent_reports = (array) $this->db->get_recent_reports(5);

        if (empty($recent_reports)) {
            echo '<p>' . esc_html__('Noch keine Einsatzberichte vorhanden.', 'feuer-einsatzberichte') . '</p>';
            return;
        }

        echo '<div class="feu-einsatz-dashboard-recent-list">';

        foreach ($recent_reports as $report) {
            $report_id = isset($report->ID) ? (int) $report->ID : 0;
            $title = $report_id ? get_the_title($report_id) : '';
            $status = $report_id ? get_post_status($report_id) : '';
            $status_object = $status ? get_post_status_object($status) : null;
            $street = FEU_Einsatz_Template_Helpers::strip_house_number_from_street((string) ($report->street ?? ''));
            $event_date = (string) ($report->event_date ?? '');
            $date_label = '';

            if ('' !== $event_date) {
                $timestamp = strtotime($event_date);
                $date_label = $timestamp ? wp_date('d.m.Y H:i', $timestamp) : $event_date;
            }

            echo '<div class="feu-einsatz-dashboard-recent-item">';
            echo '<div class="feu-einsatz-dashboard-recent-top">';
            echo '<div>';
            echo '<a class="feu-einsatz-dashboard-recent-title" href="' . esc_url(get_edit_post_link($report_id)) . '">' . esc_html($title) . '</a>';
            if ($date_label || $street) {
                echo '<div class="feu-einsatz-dashboard-recent-meta">';
                echo esc_html(trim($date_label . ($date_label && $street ? ' В· ' : '') . $street));
                echo '</div>';
            }
            echo '</div>';
            if ($status_object) {
                echo '<span class="button button-small" aria-disabled="true">' . esc_html($status_object->label) . '</span>';
            }
            echo '</div>';
            echo '<div class="feu-einsatz-dashboard-recent-actions">';
            echo '<a class="button button-secondary button-small" href="' . esc_url(get_edit_post_link($report_id)) . '">' . esc_html__('Bearbeiten', 'feuer-einsatzberichte') . '</a>';
            echo '<a class="button button-small" href="' . esc_url(get_permalink($report_id)) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Ansehen', 'feuer-einsatzberichte') . '</a>';
            echo '<form class="feu-einsatz-dashboard-inline-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('feu_einsatz_generate_map_image_now_' . $report_id);
            echo '<input type="hidden" name="action" value="feu_einsatz_generate_map_image_now" />';
            echo '<input type="hidden" name="post_id" value="' . esc_attr((string) $report_id) . '" />';
            echo '<input type="hidden" name="redirect_to" value="' . esc_attr(admin_url('index.php')) . '" />';
            echo '<button type="submit" class="button button-primary button-small">' . esc_html__('Karte generieren', 'feuer-einsatzberichte') . '</button>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    private function get_available_report_categories() {
        $configured_category_ids = array_values(array_unique(array_filter(array_map('absint', (array) get_option('feu_einsatz_categories', [])))));

        if (empty($configured_category_ids)) {
            $categories = get_categories([
                'taxonomy' => 'category',
                'hide_empty' => false,
            ]);
        } else {
            $allowed_category_ids = $configured_category_ids;

            foreach ($configured_category_ids as $category_id) {
                $allowed_category_ids = array_merge(
                    $allowed_category_ids,
                    array_map('absint', (array) get_term_children($category_id, 'category'))
                );
            }

            $allowed_category_ids = array_values(array_unique(array_filter($allowed_category_ids)));

            $categories = get_categories([
                'taxonomy' => 'category',
                'hide_empty' => false,
                'include' => $allowed_category_ids,
            ]);
        }

        usort($categories, static function ($left, $right) {
            $left_path = count(get_ancestors($left->term_id, 'category')) . ':' . $left->name;
            $right_path = count(get_ancestors($right->term_id, 'category')) . ':' . $right->name;

            return strnatcasecmp($left_path, $right_path);
        });

        return $categories;
    }

    private function is_einsatzbericht_post($post) {
        if (!($post instanceof WP_Post) || 'post' !== $post->post_type) {
            return false;
        }

        if ('1' === get_post_meta($post->ID, '_feu_einsatz_einsatzbericht', true)) {
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
            $value = get_post_meta($post->ID, $meta_key, true);

            if (is_array($value) && !empty($value)) {
                return true;
            }

            if (!is_array($value) && '' !== trim((string) $value)) {
                return true;
            }
        }

        $categories = get_the_category($post->ID);

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

    private function get_internal_report_edit_url($post_id) {
        $post_id = absint($post_id);

        if (!$post_id) {
            return '';
        }

        return add_query_arg(
            [
                'page' => 'feu-einsatz-bericht-bearbeiten',
                'post' => $post_id,
            ],
            admin_url('admin.php')
        );
    }

    private function get_standard_report_edit_url($post_id) {
        $post_id = absint($post_id);

        if (!$post_id) {
            return '';
        }

        return admin_url('post.php?post=' . $post_id . '&action=edit&feu_einsatz_einsatzbericht=1');
    }

    private function get_editable_report($post_id) {
        $post_id = absint($post_id);
        $post = $post_id ? get_post($post_id) : null;

        if (!$this->is_einsatzbericht_post($post)) {
            return null;
        }

        if (!self::current_user_can_access_plugin_section('create_report')) {
            return null;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return null;
        }

        return $post;
    }

    private function build_default_report_title_from_request($selected_categories = []) {
        $street = isset($_POST['feu_einsatz_strasse']) ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_strasse'])) : '';
        $street = FEU_Einsatz_Template_Helpers::strip_house_number_from_street($street);
        $category_label = '';

        foreach ((array) $selected_categories as $category_id) {
            $category = get_term(absint($category_id), 'category');

            if ($category && !is_wp_error($category) && '' !== trim((string) $category->name)) {
                $category_label = trim((string) $category->name);
                break;
            }
        }

        $parts = [];

        if ('' !== $category_label) {
            $parts[] = $category_label;
        } elseif ('' !== $street) {
            $parts[] = __('Einsatzbericht', 'feuer-einsatzberichte');
        }

        if ('' !== $street) {
            $parts[] = $street;
        }

        if (!empty($parts)) {
            return implode(' - ', $parts);
        }

        return __('Neuer Einsatzbericht', 'feuer-einsatzberichte');
    }


    public function render_create_report() {
        if (!self::current_user_can_access_plugin_section('create_report') || !current_user_can('edit_posts')) {
            wp_die(esc_html__('Keine Berechtigung', 'feuer-einsatzberichte'));
        }

        $post = new stdClass();
        $post->ID = 0;
        $post->post_status = 'draft';
        $post->post_content = '';
        $report_categories = $this->get_available_report_categories();
        $created_report_id = isset($_GET['created_report']) ? absint(wp_unslash($_GET['created_report'])) : 0;
        $created_report_status = isset($_GET['created_status']) ? sanitize_key(wp_unslash($_GET['created_status'])) : '';
        $is_edit_mode = false;
        $success_notice = [];
        if ($created_report_id) {
            $success_notice = [
                'message' => 'future' === $created_report_status
                    ? __('Einsatzbericht wurde im Plugin gespeichert und als geplanter Beitrag angelegt.', 'feuer-einsatzberichte')
                    : ('publish' === $created_report_status
                        ? __('Einsatzbericht wurde im Plugin erstellt und veröffentlicht.', 'feuer-einsatzberichte')
                        : __('Einsatzbericht wurde im Plugin als Entwurf gespeichert.', 'feuer-einsatzberichte')),
                'view_url' => get_permalink($created_report_id),
                'plugin_edit_url' => $this->get_internal_report_edit_url($created_report_id),
                'standard_edit_url' => $this->get_standard_report_edit_url($created_report_id),
            ];
        }

        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/report-create.php';
    }

    public function render_edit_report() {
        $this->enforce_plugin_section_access('create_report');

        $post_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
        $post = $this->get_editable_report($post_id);

        if (!$post) {
            wp_die(esc_html__('Einsatzbericht nicht gefunden oder keine Berechtigung.', 'feuer-einsatzberichte'));
        }

        $report_categories = $this->get_available_report_categories();
        $is_edit_mode = true;
        $updated_report_id = isset($_GET['updated_report']) ? absint(wp_unslash($_GET['updated_report'])) : 0;
        $updated_report_status = isset($_GET['updated_status']) ? sanitize_key(wp_unslash($_GET['updated_status'])) : '';
        $success_notice = [];
        if ($updated_report_id === $post->ID) {
            $success_notice = [
                'message' => 'future' === $updated_report_status
                    ? __('Einsatzbericht wurde im Plugin aktualisiert und bleibt geplant.', 'feuer-einsatzberichte')
                    : ('publish' === $updated_report_status
                        ? __('Einsatzbericht wurde im Plugin aktualisiert und veröffentlicht.', 'feuer-einsatzberichte')
                        : __('Einsatzbericht wurde im Plugin als Entwurf aktualisiert.', 'feuer-einsatzberichte')),
                'view_url' => get_permalink($post->ID),
                'plugin_edit_url' => $this->get_internal_report_edit_url($post->ID),
                'standard_edit_url' => $this->get_standard_report_edit_url($post->ID),
            ];
        }

        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/report-create.php';
    }

    public function handle_create_report() {
        if (!self::current_user_can_access_plugin_section('create_report') || !current_user_can('edit_posts')) {
            wp_die(esc_html__('Keine Berechtigung', 'feuer-einsatzberichte'));
        }

        check_admin_referer('feu_einsatz_create_report', 'feu_einsatz_create_report_nonce');
        $validation = $this->validate_report_submission_request();

        if (!empty($validation['errors'])) {
            $this->abort_invalid_report_submission($validation['errors'], 'report_create_validation');
        }

        $title = isset($_POST['post_title']) ? sanitize_text_field(wp_unslash($_POST['post_title'])) : '';
        $content = isset($_POST['post_content']) ? wp_kses_post(wp_unslash($_POST['post_content'])) : '';
        $status = isset($_POST['feu_einsatz_report_status']) && 'publish' === sanitize_text_field(wp_unslash($_POST['feu_einsatz_report_status']))
            ? 'publish'
            : 'draft';

        if ('publish' === $status && !current_user_can('publish_posts')) {
            $status = 'draft';
        }

        $selected_categories = isset($validation['categories']) && is_array($validation['categories'])
            ? array_values(array_map('absint', $validation['categories']))
            : [];
        $availability_request = $this->get_report_availability_request();
        $availability_datetime = $this->get_report_availability_datetime($availability_request);
        $event_datetime = $this->get_report_event_datetime_from_validation($validation);
        $request_street = isset($_POST['feu_einsatz_strasse']) ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_strasse'])) : '';

        if ('' === $title) {
            $title = $this->build_default_report_title_from_request($selected_categories);
        }

        $_POST['feu_einsatz_is_einsatzbericht'] = '1';

        $postarr = [
            'post_type' => 'post',
            'post_status' => $status,
            'post_title' => $title,
            'post_content' => $content,
            'post_author' => get_current_user_id(),
        ];

        if ($event_datetime instanceof DateTimeImmutable) {
            $event_post_date = $event_datetime->format('Y-m-d H:i:s');
            $postarr['post_date'] = $event_post_date;
            $postarr['post_date_gmt'] = get_gmt_from_date($event_post_date);
        }

        if (!empty($selected_categories)) {
            $postarr['post_category'] = $selected_categories;
        }

        if ('publish' === $status && 'sofort' !== $availability_request['mode']) {
            $scheduled_date = $availability_datetime->format('Y-m-d H:i:s');

            $postarr['post_date'] = $scheduled_date;
            $postarr['post_date_gmt'] = get_gmt_from_date($scheduled_date);
            $postarr['post_status'] = ($availability_datetime > new DateTimeImmutable('now', wp_timezone())) ? 'future' : 'publish';
        }

        $defer_publication_until_map = $this->should_defer_publication_until_map_ready(
            $postarr['post_status'],
            $request_street,
            0,
            true
        );
        $deferred_target_status = $postarr['post_status'];
        $deferred_target_postarr = $postarr;

        if ($defer_publication_until_map) {
            $postarr['post_status'] = 'draft';
            unset($postarr['post_date'], $postarr['post_date_gmt']);
        }

        remove_action('save_post', [$this, 'save_post_data'], 10);
        $post_id = wp_insert_post($postarr, true);
        add_action('save_post', [$this, 'save_post_data'], 10, 3);

        if (is_wp_error($post_id)) {
            FEU_Einsatz_Logger::log_runtime_error(
                'report_create_insert_failed',
                __('Einsatzbericht konnte nicht erstellt werden.', 'feuer-einsatzberichte'),
                ['message' => $post_id->get_error_message()]
            );
            wp_die(esc_html($post_id->get_error_message()));
        }

        update_post_meta($post_id, '_feu_einsatz_einsatzbericht', '1');
        $this->save_einsatz_details($post_id, $validation['geocoded_data']);

        $strasse = (string) get_post_meta($post_id, '_feu_einsatz_strasse', true);
        $plz = (string) get_post_meta($post_id, '_feu_einsatz_plz', true);
        $stadt = (string) get_post_meta($post_id, '_feu_einsatz_stadt', true);

        if ((empty(get_post_meta($post_id, '_feu_einsatz_latitude', true)) || empty(get_post_meta($post_id, '_feu_einsatz_longitude', true))) && '' !== trim($strasse)) {
            $this->schedule_background_geocode($post_id);
        }

        $this->save_teilnehmer($post_id);
        $this->save_organisationen($post_id);
        $this->save_gallery($post_id);
        $this->store_report_availability_meta($post_id, $availability_request, $availability_datetime);
        if ($defer_publication_until_map) {
            $this->store_generated_map_publish_hold($post_id, $deferred_target_status, $deferred_target_postarr);
        } else {
            $this->clear_generated_map_publish_hold($post_id);
        }

        $has_preview_image = (int) get_post_thumbnail_id($post_id) > 0
            || '' !== (string) get_post_meta($post_id, self::GENERATED_MAP_PREVIEW_URL_META, true);

        $map_generation_queued = false;

        if (!$has_preview_image && '' !== trim($strasse)) {
            $map_generation_queued = $this->maybe_queue_generated_map_preview_generation(
                $post_id,
                $strasse,
                $plz,
                $stadt,
                true,
                $this->get_generated_map_generation_delay_seconds(),
                'report_create'
            );
        }

        if (
            $defer_publication_until_map
            && !$map_generation_queued
            && $this->has_current_generated_map_signature($post_id, $strasse, $plz, $stadt)
        ) {
            $this->maybe_release_deferred_publication($post_id);
        }

        // Use the actual post status (may differ from $postarr if a deferred release just ran).
        $actual_post = get_post($post_id);
        $actual_status = ($actual_post instanceof WP_Post) ? $actual_post->post_status : $postarr['post_status'];

        $created_report = $this->get_report_log_payload($post_id);

        FEU_Einsatz_Logger::log(
            'report_created',
            'report',
            $post_id,
            __('Einsatzbericht erstellt', 'feuer-einsatzberichte'),
            [
                'status' => $actual_status,
                'title' => get_the_title($post_id),
                'street' => $created_report['street'] ?? '',
                'house_number' => $created_report['house_number'] ?? '',
                'plz' => $created_report['plz'] ?? '',
                'city' => $created_report['city'] ?? '',
                'date' => $created_report['date'] ?? '',
                'time' => $created_report['time'] ?? '',
                'categories' => $created_report['categories'] ?? $selected_categories,
                'category_labels' => $created_report['category_labels'] ?? [],
                'comments_enabled' => $created_report['comments_enabled'] ?? ('1' === (string) get_post_meta($post_id, '_feu_einsatz_comments_enabled', true)),
            ]
        );

        $redirect_url = add_query_arg(
            [
                'page' => 'feu-einsatz-neuer-bericht',
                'created_report' => absint($post_id),
                'created_status' => sanitize_key($actual_status),
                'map_pending' => ($defer_publication_until_map && $map_generation_queued) ? 1 : 0,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    public function handle_update_report() {
        check_admin_referer('feu_einsatz_update_report', 'feu_einsatz_update_report_nonce');

        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $post = $this->get_editable_report($post_id);

        if (!$post) {
            wp_die(esc_html__('Einsatzbericht nicht gefunden oder keine Berechtigung.', 'feuer-einsatzberichte'));
        }

        $report_before = $this->get_report_log_payload($post_id);
        $validation = $this->validate_report_submission_request($post_id);

        if (!empty($validation['errors'])) {
            $this->abort_invalid_report_submission($validation['errors'], 'report_update_validation');
        }

        $title = isset($_POST['post_title']) ? sanitize_text_field(wp_unslash($_POST['post_title'])) : '';
        $content = isset($_POST['post_content']) ? wp_kses_post(wp_unslash($_POST['post_content'])) : '';
        $status = isset($_POST['feu_einsatz_report_status']) && 'publish' === sanitize_text_field(wp_unslash($_POST['feu_einsatz_report_status']))
            ? 'publish'
            : 'draft';

        if ('publish' === $status && !current_user_can('publish_posts')) {
            $status = 'draft';
        }

        $selected_categories = isset($validation['categories']) && is_array($validation['categories'])
            ? array_values(array_map('absint', $validation['categories']))
            : [];
        $availability_request = $this->get_report_availability_request();
        $availability_datetime = $this->get_report_availability_datetime($availability_request);
        $event_datetime = $this->get_report_event_datetime_from_validation($validation, $post_id);
        $request_street = isset($_POST['feu_einsatz_strasse']) ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_strasse'])) : '';

        if ('' === $title) {
            $title = $this->build_default_report_title_from_request($selected_categories);
        }

        $_POST['feu_einsatz_is_einsatzbericht'] = '1';

        $postarr = [
            'ID' => $post_id,
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $status,
        ];

        if ($event_datetime instanceof DateTimeImmutable) {
            $event_post_date = $event_datetime->format('Y-m-d H:i:s');
            $postarr['post_date'] = $event_post_date;
            $postarr['post_date_gmt'] = get_gmt_from_date($event_post_date);
        }

        if ('publish' === $status && 'sofort' !== $availability_request['mode']) {
            $scheduled_date = $availability_datetime->format('Y-m-d H:i:s');

            $postarr['post_date'] = $scheduled_date;
            $postarr['post_date_gmt'] = get_gmt_from_date($scheduled_date);
            $postarr['post_status'] = ($availability_datetime > new DateTimeImmutable('now', wp_timezone())) ? 'future' : 'publish';
        } elseif ('publish' === $status && 'future' === $post->post_status && !isset($postarr['post_date'])) {
            $postarr['post_date'] = current_time('mysql');
            $postarr['post_date_gmt'] = get_gmt_from_date($postarr['post_date']);
        }

        $defer_publication_until_map = $this->should_defer_publication_until_map_ready(
            $postarr['post_status'],
            $request_street,
            $post_id,
            false
        );
        $deferred_target_status = $postarr['post_status'];
        $deferred_target_postarr = $postarr;

        if ($defer_publication_until_map) {
            $postarr['post_status'] = 'draft';
            unset($postarr['post_date'], $postarr['post_date_gmt']);
        }

        remove_action('save_post', [$this, 'save_post_data'], 10);
        $updated_post_id = wp_update_post($postarr, true);
        add_action('save_post', [$this, 'save_post_data'], 10, 3);

        if (is_wp_error($updated_post_id)) {
            FEU_Einsatz_Logger::log_runtime_error(
                'report_update_failed',
                __('Einsatzbericht konnte nicht aktualisiert werden.', 'feuer-einsatzberichte'),
                [
                    'post_id' => $post_id,
                    'message' => $updated_post_id->get_error_message(),
                ]
            );
            wp_die(esc_html($updated_post_id->get_error_message()));
        }

        update_post_meta($post_id, '_feu_einsatz_einsatzbericht', '1');
        wp_set_post_categories($post_id, $selected_categories, false);

        $this->save_einsatz_details($post_id, $validation['geocoded_data']);

        $strasse = (string) get_post_meta($post_id, '_feu_einsatz_strasse', true);
        $plz = (string) get_post_meta($post_id, '_feu_einsatz_plz', true);
        $stadt = (string) get_post_meta($post_id, '_feu_einsatz_stadt', true);

        if ((empty(get_post_meta($post_id, '_feu_einsatz_latitude', true)) || empty(get_post_meta($post_id, '_feu_einsatz_longitude', true))) && '' !== trim($strasse)) {
            $this->schedule_background_geocode($post_id);
        }

        $this->save_teilnehmer($post_id);
        $this->save_organisationen($post_id);
        $this->save_gallery($post_id);
        $this->store_report_availability_meta($post_id, $availability_request, $availability_datetime);
        if ($defer_publication_until_map) {
            $this->store_generated_map_publish_hold($post_id, $deferred_target_status, $deferred_target_postarr);
        } else {
            $this->clear_generated_map_publish_hold($post_id);
        }

        $auto_map_image = (int) get_option('feu_einsatz_auto_map_image', 1);
        $map_generation_queued = false;
        if ($auto_map_image && '' !== trim($strasse)) {
            $map_generation_queued = $this->maybe_queue_generated_map_preview_generation(
                $post_id,
                $strasse,
                $plz,
                $stadt,
                false,
                $this->get_generated_map_generation_delay_seconds(),
                'report_update'
            );
        }

        if (
            $defer_publication_until_map
            && !$map_generation_queued
            && $this->has_current_generated_map_signature($post_id, $strasse, $plz, $stadt)
        ) {
            $this->maybe_release_deferred_publication($post_id);
        }

        $report_after = $this->get_report_log_payload($post_id);

        FEU_Einsatz_Logger::log(
            'report_updated',
            'report',
            $post_id,
            __('Einsatzbericht aktualisiert', 'feuer-einsatzberichte'),
            [
                'status' => $postarr['post_status'],
                'title' => get_the_title($post_id),
                'street' => $report_after['street'] ?? '',
                'house_number' => $report_after['house_number'] ?? '',
                'plz' => $report_after['plz'] ?? '',
                'city' => $report_after['city'] ?? '',
                'date' => $report_after['date'] ?? '',
                'time' => $report_after['time'] ?? '',
                'categories' => $report_after['categories'] ?? $selected_categories,
                'category_labels' => $report_after['category_labels'] ?? [],
                'comments_enabled' => $report_after['comments_enabled'] ?? ('1' === (string) get_post_meta($post_id, '_feu_einsatz_comments_enabled', true)),
                'changed_fields' => $this->get_report_log_change_set($report_before, $report_after),
            ]
        );

        $redirect_url = add_query_arg(
            [
                'page' => 'feu-einsatz-bericht-bearbeiten',
                'post' => absint($post_id),
                'updated_report' => absint($post_id),
                'updated_status' => sanitize_key($postarr['post_status']),
                'map_pending' => ($defer_publication_until_map && $map_generation_queued) ? 1 : 0,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    public function filter_edit_post_link($location, $post_id, $context) {
        $post_id = absint($post_id);

        if (!$post_id) {
            return $location;
        }

        $post = get_post($post_id);

        if (!$this->is_einsatzbericht_post($post)) {
            return $location;
        }

        if (
            !self::current_user_can_access_plugin_section('create_report')
            || !current_user_can('edit_post', $post_id)
        ) {
            return $location;
        }

        return $this->get_internal_report_edit_url($post_id);
    }

    public function filter_admin_parent_file($parent_file) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ('feu-einsatz-bericht-bearbeiten' === $page) {
            return 'feuer-einsatzberichte';
        }

        return $parent_file;
    }

    public function filter_admin_submenu_file($submenu_file) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ('feu-einsatz-bericht-bearbeiten' === $page) {
            return 'edit.php?post_type=post&feu_einsatz_filter=1';
        }

        return $submenu_file;
    }

    public function render_participants() {
        $this->enforce_plugin_section_access('participants');
        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/participants.php';
    }

    public function render_statistics() {
        $this->enforce_plugin_section_access('statistics');
        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/statistics.php';
    }

    public function render_settings() {
        $this->enforce_plugin_section_access('settings');
        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    public function render_archives() {
        $this->enforce_plugin_section_access('archives');
        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/archives.php';
    }

    public function render_logs() {
        $this->enforce_plugin_section_access('logs');
        include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/logs.php';
    }

    public function handle_generate_map_image_now() {
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';

        check_admin_referer('feu_einsatz_generate_map_image_now_' . $post_id);

        if (
            !$post_id
            || !$this->current_user_can_manage_generated_map($post_id)
        ) {
            wp_die(esc_html__('Keine Berechtigung', 'feuer-einsatzberichte'));
        }

        $strasse = (string) get_post_meta($post_id, '_feu_einsatz_strasse', true);
        $hausnummer = (string) get_post_meta($post_id, '_feu_einsatz_hausnummer', true);
        $plz = (string) get_post_meta($post_id, '_feu_einsatz_plz', true);
        $stadt = (string) get_post_meta($post_id, '_feu_einsatz_stadt', true);
        $display_street = FEU_Einsatz_Template_Helpers::strip_house_number_from_street($strasse);
        $address = $this->build_full_address($display_street, $plz, $stadt);

        if ('' === $display_street && '' === trim((string) get_post_meta($post_id, '_feu_einsatz_latitude', true))) {
            wp_die(esc_html__('Bitte zuerst eine gueltige Adresse oder Koordinaten speichern.', 'feuer-einsatzberichte'));
        }

        $this->clear_generated_map_queue($post_id, true);
        $this->clear_background_geocode_schedule($post_id);

        if (!$this->acquire_generated_map_process_lock($post_id)) {
            wp_die(esc_html__('Das Kartenbild wird bereits verarbeitet. Bitte versuchen Sie es spaeter erneut.', 'feuer-einsatzberichte'));
        }

        $this->update_generated_map_status(
            $post_id,
            'processing',
            [
                'stage' => 'map',
                'message' => __('Kartenbild wird gerade manuell erstellt.', 'feuer-einsatzberichte'),
            ]
        );

        try {
            $latitude = $this->sanitize_coordinate_value((string) get_post_meta($post_id, '_feu_einsatz_latitude', true), 'lat');
            $longitude = $this->sanitize_coordinate_value((string) get_post_meta($post_id, '_feu_einsatz_longitude', true), 'lng');
            $geocoded_data = null;

            if ('' === $latitude || '' === $longitude) {
                $geocoded_data = $this->request_geocoded_address_data($strasse, $plz, $stadt, $hausnummer);
            }

            $map_preview_context = $this->build_map_preview_generation_context($post_id, $strasse, $plz, $stadt, $geocoded_data ?: [
                'lat' => $latitude,
                'lng' => $longitude,
            ], [
                'allow_focused_geometry_refresh' => true,
                'fast_tile_mode' => true,
            ]);

            $result = $this->generate_map_image($post_id, $address, $map_preview_context);
        } catch (Throwable $error) {
            $result = new WP_Error('feu_einsatz_manual_map_exception', $error->getMessage());
        } finally {
            $this->release_generated_map_process_lock($post_id);
        }

        $redirect_to = '' !== $redirect_to ? $redirect_to : $this->get_internal_report_edit_url($post_id);

        if (is_wp_error($result)) {
            $this->update_generated_map_status(
                $post_id,
                'error',
                [
                    'stage' => 'map',
                    'message' => __('Kartenbild konnte nicht manuell erstellt werden.', 'feuer-einsatzberichte'),
                    'last_error' => $result->get_error_message(),
                ]
            );

            wp_safe_redirect(add_query_arg([
                'feu_map_generation' => 'error',
                'feu_map_message' => rawurlencode($result->get_error_message()),
            ], $redirect_to));
            exit;
        }

        $publication_released = $this->maybe_release_deferred_publication($post_id);
        $this->update_generated_map_status(
            $post_id,
            'ready',
            [
                'stage' => 'map',
                'message' => $publication_released
                    ? __('Kartenbild wurde erstellt und der Bericht freigegeben.', 'feuer-einsatzberichte')
                    : __('Kartenbild wurde erfolgreich erstellt.', 'feuer-einsatzberichte'),
                'completed_at' => time(),
            ]
        );

        wp_safe_redirect(add_query_arg([
            'feu_map_generation' => 'success',
            'feu_map_message' => rawurlencode($publication_released
                ? __('Kartenbild wurde erstellt und der Bericht wurde freigegeben.', 'feuer-einsatzberichte')
                : __('Kartenbild wurde erfolgreich erstellt.', 'feuer-einsatzberichte')),
        ], $redirect_to));
        exit;
    }

    public function handle_delete_map_image() {
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';

        check_admin_referer('feu_einsatz_delete_map_image_' . $post_id);

        if (
            !$post_id
            || !$this->current_user_can_manage_generated_map($post_id)
        ) {
            wp_die(esc_html__('Keine Berechtigung', 'feuer-einsatzberichte'));
        }

        $this->delete_generated_map_assets($post_id, true);
        $redirect_to = '' !== $redirect_to ? $redirect_to : $this->get_internal_report_edit_url($post_id);

        wp_safe_redirect(add_query_arg([
            'feu_map_generation' => 'success',
            'feu_map_message' => rawurlencode(__('Kartenbild wurde entfernt.', 'feuer-einsatzberichte')),
        ], $redirect_to));
        exit;
    }

    public function ajax_generate_map_image() {
        check_ajax_referer('feu_einsatz_ajax_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if (
            !$post_id
            || !$this->current_user_can_manage_generated_map($post_id)
        ) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'feuer-einsatzberichte')], 403);
        }

        $address = isset($_POST['address']) ? sanitize_text_field(wp_unslash($_POST['address'])) : '';
        $strasse = isset($_POST['strasse']) ? sanitize_text_field(wp_unslash($_POST['strasse'])) : get_post_meta($post_id, '_feu_einsatz_strasse', true);
        $hausnummer = isset($_POST['hausnummer']) ? sanitize_text_field(wp_unslash($_POST['hausnummer'])) : get_post_meta($post_id, '_feu_einsatz_hausnummer', true);
        $plz = isset($_POST['plz']) ? sanitize_text_field(wp_unslash($_POST['plz'])) : get_post_meta($post_id, '_feu_einsatz_plz', true);
        $stadt = isset($_POST['stadt']) ? sanitize_text_field(wp_unslash($_POST['stadt'])) : get_post_meta($post_id, '_feu_einsatz_stadt', true);
        $display_street = FEU_Einsatz_Template_Helpers::strip_house_number_from_street($strasse);

        if ('' === $address) {
            if ('' !== $display_street) {
                $address = $this->build_full_address($display_street, $plz, $stadt);
            }
        } else {
            $address = $this->build_full_address($display_street, $plz, $stadt);
        }

        if ('' === $address) {
            wp_send_json_error(['message' => __('Bitte geben Sie eine gültige Adresse ein.', 'feuer-einsatzberichte')], 400);
        }

        $this->clear_generated_map_queue($post_id, true);
        $this->clear_background_geocode_schedule($post_id);
        $this->update_generated_map_status(
            $post_id,
            'processing',
            [
                'stage' => 'map',
                'message' => __('Kartenbild wird gerade manuell erstellt.', 'feuer-einsatzberichte'),
            ]
        );

        $latitude = isset($_POST['latitude'])
            ? $this->sanitize_coordinate_value(wp_unslash($_POST['latitude']), 'lat')
            : '';
        $longitude = isset($_POST['longitude'])
            ? $this->sanitize_coordinate_value(wp_unslash($_POST['longitude']), 'lng')
            : '';

        if ('' !== $latitude && '' !== $longitude) {
            update_post_meta($post_id, '_feu_einsatz_latitude', $latitude);
            update_post_meta($post_id, '_feu_einsatz_longitude', $longitude);
        }

        $geocoded_data = null;

        if ('' === $latitude || '' === $longitude) {
            $geocoded_data = $this->request_geocoded_address_data($strasse, $plz, $stadt, $hausnummer);
        }

        $map_preview_context = $this->build_map_preview_generation_context($post_id, $strasse, $plz, $stadt, $geocoded_data ?: [
            'lat' => $latitude,
            'lng' => $longitude,
        ], [
            'allow_focused_geometry_refresh' => true,
            'fast_tile_mode' => true,
        ]);

        $result = $this->generate_map_image($post_id, $address, $map_preview_context);

        if (is_wp_error($result)) {
            FEU_Einsatz_Logger::log_runtime_error(
                'report_map_generation_failed',
                __('Kartenbild konnte nicht generiert werden.', 'feuer-einsatzberichte'),
                [
                    'post_id' => $post_id,
                    'address' => $address,
                    'message' => $result->get_error_message(),
                ]
            );
            $this->update_generated_map_status(
                $post_id,
                'error',
                [
                    'stage' => 'map',
                    'message' => __('Kartenbild konnte nicht manuell erstellt werden.', 'feuer-einsatzberichte'),
                    'last_error' => $result->get_error_message(),
                ]
            );
            wp_send_json_error(['message' => $result->get_error_message()], 500);
        }

        if (is_array($result) && !empty($result['url'])) {
            $publication_released = $this->maybe_release_deferred_publication($post_id);
            $this->update_generated_map_status(
                $post_id,
                'ready',
                [
                    'stage' => 'map',
                    'message' => $publication_released
                        ? __('Kartenbild wurde erstellt und der Bericht freigegeben.', 'feuer-einsatzberichte')
                        : __('Kartenbild wurde erfolgreich erstellt.', 'feuer-einsatzberichte'),
                    'completed_at' => time(),
                ]
            );
            $message = 'featured_image' === $result['mode']
                ? __('Kartenbild wurde als Beitragsbild gespeichert und steht als Karten-Fallback bereit.', 'feuer-einsatzberichte')
                : __('SVG-Kartenvorschau wurde gespeichert und steht als Karten-Fallback bereit.', 'feuer-einsatzberichte');

            wp_send_json_success([
                'message' => $message,
                'image_url' => $result['url'],
            ]);
        }

        $this->update_generated_map_status(
            $post_id,
            'error',
            [
                'stage' => 'map',
                'message' => __('Kartenbild konnte nicht manuell erstellt werden.', 'feuer-einsatzberichte'),
            ]
        );
        wp_send_json_error(['message' => __('Fehler beim Generieren des Kartenbildes.', 'feuer-einsatzberichte')], 500);
    }
}
