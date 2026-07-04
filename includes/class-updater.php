<?php
if (!defined('ABSPATH')) {
    exit;
}

class FEU_Einsatz_Updater {

    const MANIFEST_TRANSIENT = 'feu_einsatz_update_manifest_payload';
    const MANIFEST_TTL = 21600;
    const PACKAGE_HASH_ALGORITHM = 'sha256';
    const PLUGIN_SLUG = 'feuer-einsatzberichte';
    const DEFAULT_PROJECT_URL = 'https://wdmin.com/plugins/feuer-einsatzberichte/';
    const DEFAULT_RELEASE_BASE_URL = 'https://wdmin.com/plugins/feuer-einsatzberichte/release/';

    public function __construct() {
        $this->maybe_initialize_manifest_url();

        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update_payload']);
        add_filter('plugins_api', [$this, 'handle_plugin_information'], 20, 3);
        add_filter('upgrader_pre_download', [$this, 'verify_update_package'], 10, 4);
        add_filter('upgrader_source_selection', [$this, 'fix_extracted_source_dir'], 1, 3);
        add_action('upgrader_process_complete', [$this, 'handle_upgrader_process_complete'], 10, 2);
        add_action('admin_notices', [$this, 'render_update_notice']);
    }

    public static function get_default_manifest_url() {
        return self::build_release_manifest_url('latest');
    }

    public static function get_project_url() {
        return self::DEFAULT_PROJECT_URL;
    }

    public static function get_release_base_url() {
        return self::DEFAULT_RELEASE_BASE_URL;
    }

    private static function sanitize_release_segment($segment, $fallback = 'latest') {
        $segment = trim((string) $segment);
        $segment = trim($segment, "/\\ \t\n\r\0\x0B");

        if ('' === $segment) {
            $segment = (string) $fallback;
        }

        return rawurlencode($segment);
    }

    public static function build_release_directory_url($segment) {
        return trailingslashit(self::get_release_base_url()) . self::sanitize_release_segment($segment) . '/';
    }

    public static function build_release_manifest_url($channel = 'latest') {
        return self::build_release_directory_url($channel) . 'update-manifest.json';
    }

    public static function build_package_url($version = null) {
        $version = trim((string) ($version ?: FEU_EINSATZ_VERSION));

        if ('' === $version) {
            $version = 'latest';
        }

        return self::build_release_directory_url($version) . 'feuer-einsatzberichte-' . rawurlencode($version) . '.zip';
    }

    public static function build_latest_package_url($version = null) {
        $version = trim((string) ($version ?: FEU_EINSATZ_VERSION));

        if ('' === $version) {
            $version = 'latest';
        }

        return self::build_release_directory_url('latest') . 'feuer-einsatzberichte-' . rawurlencode($version) . '.zip';
    }

    public static function get_plugin_basename() {
        return plugin_basename(FEU_EINSATZ_PLUGIN_FILE);
    }

    public static function get_plugin_slug() {
        return self::PLUGIN_SLUG;
    }

    public static function clear_cached_metadata() {
        delete_transient(self::MANIFEST_TRANSIENT);
        delete_site_transient('update_plugins');
    }

    public static function sanitize_manifest_url($url) {
        $url = trim((string) $url);

        if ('' === $url) {
            return self::get_default_manifest_url();
        }

        $sanitized = esc_url_raw($url);
        $parts = wp_parse_url($sanitized);
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';

        if ('https' !== $scheme || !in_array($host, self::get_allowed_hosts(), true)) {
            return self::get_default_manifest_url();
        }

        return $sanitized;
    }

    private static function sanitize_remote_asset_url($url) {
        $url = trim((string) $url);

        if ('' === $url) {
            return '';
        }

        $sanitized = esc_url_raw($url);
        $parts = wp_parse_url($sanitized);
        $host = isset($parts['host']) ? strtolower((string) $parts['host']) : '';
        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';

        if ('https' !== $scheme || !in_array($host, self::get_allowed_hosts(), true)) {
            return '';
        }

        return $sanitized;
    }

    private static function get_allowed_hosts() {
        return [
            'wdmin.com',
            'www.wdmin.com',
        ];
    }

    public static function get_manifest_url() {
        return self::sanitize_manifest_url((string) get_option('feu_einsatz_update_manifest_url', self::get_default_manifest_url()));
    }

    private function maybe_initialize_manifest_url() {
        $current = (string) get_option('feu_einsatz_update_manifest_url', '');
        $sanitized = self::sanitize_manifest_url($current);

        if ($current !== $sanitized) {
            update_option('feu_einsatz_update_manifest_url', $sanitized);
        }
    }

    private function get_user_agent() {
        return 'Feuer-Einsatzberichte/' . FEU_EINSATZ_VERSION . '; ' . home_url('/');
    }

    public function get_manifest_payload($force = false) {
        if (!$force) {
            $cached = get_transient(self::MANIFEST_TRANSIENT);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $response = wp_remote_get(
            self::get_manifest_url(),
            [
                'timeout' => 15,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => $this->get_user_agent(),
                ],
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            return false;
        }

        $manifest = json_decode((string) wp_remote_retrieve_body($response), true);
        $normalized_manifest = $this->normalize_manifest_payload($manifest);

        if (!is_array($normalized_manifest)) {
            return false;
        }

        set_transient(self::MANIFEST_TRANSIENT, $normalized_manifest, self::MANIFEST_TTL);

        return $normalized_manifest;
    }

    private function get_manifest_payload_for_package($package) {
        $package_url = self::sanitize_remote_asset_url($package);

        if ('' === $package_url) {
            return false;
        }

        $parts = wp_parse_url($package_url);
        $path = isset($parts['path']) ? (string) $parts['path'] : '';

        if (!preg_match('#/release/[^/]+/[^/]+\.zip$#', $path)) {
            return false;
        }

        $scheme = isset($parts['scheme']) ? (string) $parts['scheme'] : 'https';
        $host = isset($parts['host']) ? (string) $parts['host'] : '';
        $port = isset($parts['port']) ? ':' . (string) $parts['port'] : '';
        $manifest_url = $scheme . '://' . $host . $port . trailingslashit(dirname($path)) . 'update-manifest.json';
        $manifest_url = self::sanitize_remote_asset_url($manifest_url);

        if ('' === $manifest_url) {
            return false;
        }

        $response = wp_remote_get(
            $manifest_url,
            [
                'timeout' => 15,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => $this->get_user_agent(),
                ],
            ]
        );

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            return false;
        }

        return $this->normalize_manifest_payload(json_decode((string) wp_remote_retrieve_body($response), true));
    }

    private function normalize_manifest_payload($manifest) {
        if (!is_array($manifest)) {
            return false;
        }

        $version = trim((string) ($manifest['version'] ?? ''));
        $download_url = self::sanitize_remote_asset_url($manifest['download_url'] ?? ($manifest['package'] ?? ''));

        if ('' === $version || '' === $download_url) {
            return false;
        }

        $sections = isset($manifest['sections']) && is_array($manifest['sections']) ? $manifest['sections'] : [];
        $normalized_sections = [];

        foreach ($sections as $key => $value) {
            $normalized_sections[sanitize_key($key)] = wp_kses_post((string) $value);
        }

        $icons = isset($manifest['icons']) && is_array($manifest['icons']) ? $manifest['icons'] : [];
        $normalized_icons = [];

        foreach ($icons as $key => $value) {
            $sanitized_icon = self::sanitize_remote_asset_url($value);

            if ('' !== $sanitized_icon) {
                $normalized_icons[sanitize_key($key)] = $sanitized_icon;
            }
        }

        $banners = isset($manifest['banners']) && is_array($manifest['banners']) ? $manifest['banners'] : [];
        $normalized_banners = [];

        foreach ($banners as $key => $value) {
            $sanitized_banner = self::sanitize_remote_asset_url($value);

            if ('' !== $sanitized_banner) {
                $normalized_banners[sanitize_key($key)] = $sanitized_banner;
            }
        }

        return [
            'name' => sanitize_text_field((string) ($manifest['name'] ?? 'Einsatzberichte')),
            'slug' => sanitize_key((string) ($manifest['slug'] ?? self::get_plugin_slug())),
            'version' => $version,
            'homepage' => self::sanitize_remote_asset_url($manifest['homepage'] ?? self::get_project_url()) ?: self::get_project_url(),
            'download_url' => $download_url,
            'requires' => sanitize_text_field((string) ($manifest['requires'] ?? '6.0')),
            'tested' => sanitize_text_field((string) ($manifest['tested'] ?? '6.9')),
            'requires_php' => sanitize_text_field((string) ($manifest['requires_php'] ?? FEU_EINSATZ_MIN_PHP_VERSION)),
            'last_updated' => sanitize_text_field((string) ($manifest['last_updated'] ?? gmdate('Y-m-d'))),
            'sections' => $normalized_sections,
            'icons' => $normalized_icons,
            'banners' => $normalized_banners,
            'package_hash' => preg_replace('/[^a-fA-F0-9]/', '', (string) ($manifest['package_hash'] ?? '')),
            'package_hash_algorithm' => sanitize_key((string) ($manifest['package_hash_algorithm'] ?? self::PACKAGE_HASH_ALGORITHM)),
        ];
    }

    public function inject_update_payload($transient) {
        if (!is_object($transient)) {
            $transient = new stdClass();
        }

        $manifest = $this->get_manifest_payload();
        if (!$manifest) {
            return $transient;
        }

        if (version_compare(FEU_EINSATZ_VERSION, $manifest['version'], '>=')) {
            return $transient;
        }

        $plugin_basename = self::get_plugin_basename();

        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }

        $transient->response[$plugin_basename] = (object) [
            'slug' => self::get_plugin_slug(),
            'plugin' => $plugin_basename,
            'new_version' => $manifest['version'],
            'url' => $manifest['homepage'],
            'package' => $manifest['download_url'],
            'tested' => $manifest['tested'],
            'requires' => $manifest['requires'],
            'requires_php' => $manifest['requires_php'],
            'icons' => $manifest['icons'],
            'banners' => $manifest['banners'],
        ];

        return $transient;
    }

    public function handle_plugin_information($result, $action, $args) {
        if ('plugin_information' !== $action || empty($args->slug) || self::get_plugin_slug() !== (string) $args->slug) {
            return $result;
        }

        $manifest = $this->get_manifest_payload();
        if (!$manifest) {
            return $result;
        }

        return (object) [
            'name' => $manifest['name'],
            'slug' => self::get_plugin_slug(),
            'version' => $manifest['version'],
            'author' => '<a href="https://wdmin.com/">Walter Faerber</a>',
            'author_profile' => 'https://wdmin.com/',
            'homepage' => $manifest['homepage'],
            'requires' => $manifest['requires'],
            'tested' => $manifest['tested'],
            'requires_php' => $manifest['requires_php'],
            'last_updated' => $manifest['last_updated'],
            'sections' => $manifest['sections'],
            'icons' => $manifest['icons'],
            'banners' => $manifest['banners'],
        ];
    }

    public function verify_update_package($reply, $package, $upgrader, $hook_extra) {
        if (false !== $reply && null !== $reply) {
            return $reply;
        }

        $plugin = is_array($hook_extra) ? (string) ($hook_extra['plugin'] ?? '') : '';
        $plugins = is_array($hook_extra) ? array_map('strval', (array) ($hook_extra['plugins'] ?? [])) : [];

        if (
            self::get_plugin_basename() !== $plugin
            && !in_array(self::get_plugin_basename(), $plugins, true)
        ) {
            return $reply;
        }

        $manifest = $this->get_manifest_payload_for_package($package);

        if (!$manifest) {
            $manifest = $this->get_manifest_payload(true);
        }

        if (!$manifest) {
            return new WP_Error(
                'feu_einsatz_update_manifest_mismatch',
                __('Das Update-Paket stimmt nicht mit dem freigegebenen Manifest ueberein.', 'feuer-einsatzberichte')
            );
        }

        $algorithm = strtolower((string) ($manifest['package_hash_algorithm'] ?? ''));
        $expected_hash = strtolower((string) ($manifest['package_hash'] ?? ''));

        if ('sha256' !== $algorithm || 64 !== strlen($expected_hash)) {
            return new WP_Error(
                'feu_einsatz_update_hash_missing',
                __('Das Update wurde blockiert, weil kein gueltiger SHA-256-Pruefwert vorhanden ist.', 'feuer-einsatzberichte')
            );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $downloaded_file = download_url($package, 300);

        if (is_wp_error($downloaded_file)) {
            return $downloaded_file;
        }

        $actual_hash = hash_file('sha256', $downloaded_file);

        if (!is_string($actual_hash) || !hash_equals($expected_hash, strtolower($actual_hash))) {
            wp_delete_file($downloaded_file);

            return new WP_Error(
                'feu_einsatz_update_hash_mismatch',
                __('Das Update-Paket wurde blockiert, weil seine Pruefsumme nicht stimmt.', 'feuer-einsatzberichte')
            );
        }

        return $downloaded_file;
    }

    public function fix_extracted_source_dir($source, $remote_source, $upgrader) {
        global $wp_filesystem;

        if (!$wp_filesystem instanceof WP_Filesystem_Base) {
            return $source;
        }

        $skin_options = isset($upgrader->skin->options) && is_array($upgrader->skin->options)
            ? $upgrader->skin->options
            : [];
        $plugin = (string) ($skin_options['plugin'] ?? '');

        if ('' !== $plugin && self::get_plugin_basename() !== $plugin) {
            return $source;
        }

        $correct_slug   = self::get_plugin_slug();
        $correct_source = trailingslashit($remote_source) . $correct_slug . '/';

        if (trailingslashit($source) === $correct_source) {
            return $source;
        }

        // Confirm this is our plugin by looking for the main plugin file inside.
        $main_file = basename(FEU_EINSATZ_PLUGIN_FILE);
        if (!$wp_filesystem->exists(trailingslashit($source) . $main_file)) {
            return $source;
        }

        if ($wp_filesystem->move($source, $correct_source)) {
            return $correct_source;
        }

        return $source;
    }

    public function handle_upgrader_process_complete($upgrader_object, $options) {
        if (!is_array($options)) {
            return;
        }

        if ('update' !== ($options['action'] ?? '') || 'plugin' !== ($options['type'] ?? '')) {
            return;
        }

        $updated_plugins = isset($options['plugins']) ? array_map('strval', (array) $options['plugins']) : [];
        if (in_array(self::get_plugin_basename(), $updated_plugins, true)) {
            self::clear_cached_metadata();
        }
    }

    public function render_update_notice() {
        if (!is_admin() || !current_user_can('update_plugins')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        $allowed_screen = false;

        if ($screen && in_array((string) $screen->id, ['plugins', 'update-core'], true)) {
            $allowed_screen = true;
        }

        if ('' !== $page && 0 === strpos($page, 'feu-einsatz')) {
            $allowed_screen = true;
        }

        if ('feuer-einsatzberichte' === $page) {
            $allowed_screen = true;
        }

        if (!$allowed_screen) {
            return;
        }

        $manifest = $this->get_manifest_payload();
        if (!$manifest || version_compare(FEU_EINSATZ_VERSION, $manifest['version'], '>=')) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            wp_kses_post(
                sprintf(
                    __('Fuer Einsatzberichte ist Version %1$s verfuegbar. <a href="%2$s">Jetzt zur Plugin-Aktualisierung wechseln</a>.', 'feuer-einsatzberichte'),
                    esc_html($manifest['version']),
                    esc_url(admin_url('plugins.php'))
                )
            )
        );
    }
}
