<?php
/**
 * Plugin Name: Feuer-Einsatzberichte
 * Plugin URI: https://wdmin.com/plugins/feuer-einsatzberichte/
 * Description: Feuer-Einsatzberichte mit Einsatzverwaltung, Karten, Statistik und Archivierung
 * Version: 3.2.36
 * Update URI: https://wdmin.com/plugins/feuer-einsatzberichte/
 * Requires at least: 7.1
 * Requires PHP: 8.1
 * Author URI: https://wdmin.com/
 * Author: Walter Faerber
 * License: Proprietary - personal permission required
 * Text Domain: feuer-einsatzberichte
 */

if (!defined('ABSPATH')) {
    exit;
}

// -------------------------------------------------------------------------
// Plugin constants.
// FEU_EINSATZ_* is the primary constant set.
// FEU_Einsatz_* remains available as a legacy alias layer.
// -------------------------------------------------------------------------

define('FEU_EINSATZ_VERSION', '3.2.36');
define('FEU_EINSATZ_PLUGIN_FILE', __FILE__);
define('FEU_EINSATZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FEU_EINSATZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FEU_EINSATZ_MIN_PHP_VERSION', '8.1');
define('FEU_EINSATZ_MIN_WP_VERSION', '7.1');

// Legacy aliases for older templates and update artifacts.
if (!defined('FEU_Einsatz_VERSION')) {
    define('FEU_Einsatz_VERSION', FEU_EINSATZ_VERSION);
}
if (!defined('FEU_Einsatz_PLUGIN_FILE')) {
    define('FEU_Einsatz_PLUGIN_FILE', FEU_EINSATZ_PLUGIN_FILE);
}
if (!defined('FEU_Einsatz_PLUGIN_DIR')) {
    define('FEU_Einsatz_PLUGIN_DIR', FEU_EINSATZ_PLUGIN_DIR);
}
if (!defined('FEU_Einsatz_PLUGIN_URL')) {
    define('FEU_Einsatz_PLUGIN_URL', FEU_EINSATZ_PLUGIN_URL);
}

function feu_einsatz_is_supported_php_version(): bool {
    return version_compare(PHP_VERSION, FEU_EINSATZ_MIN_PHP_VERSION, '>=');
}

function feu_einsatz_render_php_version_notice(): void {
    if (!current_user_can('activate_plugins')) {
        return;
    }

    printf(
        '<div class="notice notice-error"><p>%s</p></div>',
        esc_html(
            sprintf(
                /* translators: 1: required PHP version, 2: current PHP version */
                __('Einsatzberichte benötigt mindestens PHP %1$s. Aktuell aktiv ist PHP %2$s.', 'feuer-einsatzberichte'),
                FEU_EINSATZ_MIN_PHP_VERSION,
                PHP_VERSION
            )
        )
    );
}

function feu_einsatz_block_activation_on_unsupported_php(): void {
    if (feu_einsatz_is_supported_php_version()) {
        return;
    }

    deactivate_plugins(plugin_basename(__FILE__));

    wp_die(
        esc_html(
            sprintf(
                /* translators: 1: required PHP version, 2: current PHP version */
                __('Einsatzberichte benötigt mindestens PHP %1$s. Aktuell aktiv ist PHP %2$s.', 'feuer-einsatzberichte'),
                FEU_EINSATZ_MIN_PHP_VERSION,
                PHP_VERSION
            )
        ),
        esc_html__('PHP-Version nicht unterstützt', 'feuer-einsatzberichte'),
        ['back_link' => true]
    );
}

function feu_einsatz_handle_deactivation(): void {
    require_once FEU_EINSATZ_PLUGIN_DIR . 'includes/class-installer.php';
    FEU_Einsatz_Installer::deactivate();
}

// -------------------------------------------------------------------------
// Class autoloader.
// Missing files throw a RuntimeException instead of failing silently.
// -------------------------------------------------------------------------

spl_autoload_register(static function (string $class): void {
    $prefix = 'FEU_Einsatz_';
    $base_dir = FEU_EINSATZ_PLUGIN_DIR . 'includes/';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

    if (!file_exists($file)) {
        throw new RuntimeException(
            sprintf(
                '[Feuer-Einsatzberichte] Autoloader: Klassendatei nicht gefunden für "%s" (erwartet: %s)',
                $class,
                $file
            )
        );
    }

    require $file;
});

// -------------------------------------------------------------------------
// Plugin core.
// The core stays final and exposes collaborators through getters only.
// -------------------------------------------------------------------------

final class Feuer_Einsatzberichte_Core {

    private static ?self $instance = null;

    private FEU_Einsatz_Database $db;
    private ?FEU_Einsatz_Admin $admin = null;
    private FEU_Einsatz_Public $public;
    private FEU_Einsatz_Ajax_Handler $ajax;
    private FEU_Einsatz_Updater $updater;
    private FEU_Einsatz_Logger $logger;
    private FEU_Einsatz_Backup_Manager $backup;

    public function get_db(): FEU_Einsatz_Database { return $this->db; }
    public function get_admin(): ?FEU_Einsatz_Admin { return $this->admin; }
    public function get_public(): FEU_Einsatz_Public { return $this->public; }
    public function get_ajax(): FEU_Einsatz_Ajax_Handler { return $this->ajax; }
    public function get_updater(): FEU_Einsatz_Updater { return $this->updater; }
    public function get_logger(): FEU_Einsatz_Logger { return $this->logger; }
    public function get_backup(): FEU_Einsatz_Backup_Manager { return $this->backup; }

    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function __clone(): void {}

    public function __wakeup(): void {
        throw new RuntimeException('Singleton darf nicht deserialisiert werden.');
    }

    private function init_hooks(): void {
        register_activation_hook(__FILE__, 'feu_einsatz_block_activation_on_unsupported_php');
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, 'feu_einsatz_handle_deactivation');
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function activate(): void {
        require_once FEU_EINSATZ_PLUGIN_DIR . 'includes/class-installer.php';
        FEU_Einsatz_Installer::activate();
    }

    public function init(): void {
        if (!feu_einsatz_is_supported_php_version()) {
            add_action('admin_notices', 'feu_einsatz_render_php_version_notice');
            return;
        }

        require_once FEU_EINSATZ_PLUGIN_DIR . 'includes/class-installer.php';
        FEU_Einsatz_Installer::update();

        $this->updater = new FEU_Einsatz_Updater();

        require_once FEU_EINSATZ_PLUGIN_DIR . 'includes/class-database.php';
        $this->db = new FEU_Einsatz_Database();

        require_once FEU_EINSATZ_PLUGIN_DIR . 'includes/class-logger.php';
        $this->logger = FEU_Einsatz_Logger::boot($this->db);

        require_once FEU_EINSATZ_PLUGIN_DIR . 'includes/class-backup-manager.php';
        $this->backup = FEU_Einsatz_Backup_Manager::boot($this->db);

        $is_cron_context = function_exists('wp_doing_cron')
            ? wp_doing_cron()
            : (defined('DOING_CRON') && DOING_CRON);

        $is_cli_context = defined('WP_CLI') && WP_CLI;
        $load_admin_context = is_admin()
            || $is_cron_context
            || $is_cli_context
            || (isset($GLOBALS['pagenow']) && 'admin-post.php' === (string) $GLOBALS['pagenow']);

        if ($load_admin_context) {
            require_once FEU_EINSATZ_PLUGIN_DIR . 'includes/class-admin.php';
            $this->admin = new FEU_Einsatz_Admin($this->db);
        }

        require_once FEU_EINSATZ_PLUGIN_DIR . 'includes/class-public.php';
        $this->public = new FEU_Einsatz_Public($this->db);

        require_once FEU_EINSATZ_PLUGIN_DIR . 'includes/class-ajax-handler.php';
        $this->ajax = new FEU_Einsatz_Ajax_Handler($this->db);
    }
}

Feuer_Einsatzberichte_Core::get_instance();
