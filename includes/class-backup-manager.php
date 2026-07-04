<?php
if (!defined('ABSPATH')) {
    exit;
}

class FEU_Einsatz_Backup_Manager {

    const MAX_ARCHIVE_MANIFEST_BYTES = 5242880;
    const MAX_ARCHIVE_UPLOAD_BYTES = 536870912;
    const MAX_ARCHIVE_FILE_COUNT = 20000;
    const MAX_ARCHIVE_UNCOMPRESSED_BYTES = 2000000000;

    private static $instance = null;

    private $db;

    public static function boot($database) {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self($database);
        }

        return self::$instance;
    }

    public static function get_instance() {
        return self::$instance;
    }

    private function __construct($database) {
        $this->db = $database;
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('admin_post_feu_einsatz_create_archive', [$this, 'handle_create_archive']);
        add_action('admin_post_feu_einsatz_upload_archive', [$this, 'handle_upload_archive']);
        add_action('admin_post_feu_einsatz_restore_archive', [$this, 'handle_restore_archive']);
        add_action('admin_post_feu_einsatz_delete_archive', [$this, 'handle_delete_archive']);
        add_action('admin_post_feu_einsatz_download_archive', [$this, 'handle_download_archive']);
    }

    private function verify_request($action) {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Keine Berechtigung', 'feuer-einsatzberichte'));
        }

        check_admin_referer($action);
    }

    private function get_upload_data() {
        return wp_upload_dir();
    }

    private function get_max_archive_upload_bytes() {
        return max(1048576, (int) apply_filters('feu_einsatz_max_archive_upload_bytes', self::MAX_ARCHIVE_UPLOAD_BYTES));
    }

    private function is_valid_uploaded_archive_file($tmp_name, $file_name = '') {
        $tmp_name = (string) $tmp_name;
        $file_name = sanitize_file_name((string) $file_name);

        if ('' === $tmp_name || !file_exists($tmp_name)) {
            return false;
        }

        $filetype = wp_check_filetype_and_ext($tmp_name, $file_name, ['zip' => 'application/zip']);
        $allowed_mimes = [
            'application/zip',
            'application/x-zip',
            'application/x-zip-compressed',
            'multipart/x-zip',
        ];

        if (!empty($filetype['ext']) && 'zip' === $filetype['ext'] && !empty($filetype['type']) && in_array($filetype['type'], $allowed_mimes, true)) {
            return true;
        }

        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = strtolower((string) $finfo->file($tmp_name));

            if (in_array($mime, $allowed_mimes, true)) {
                return true;
            }
        }

        return '504b0304' === bin2hex((string) file_get_contents($tmp_name, false, null, 0, 4));
    }

    private function is_plugin_option_name($option_name) {
        $option_name = sanitize_key((string) $option_name);

        return '' !== $option_name && 0 === strpos($option_name, 'feu_einsatz_');
    }

    private function is_plugin_transient_name($option_name) {
        $option_name = sanitize_key((string) $option_name);

        return '' !== $option_name
            && (
                0 === strpos($option_name, '_transient_feu_einsatz_')
                || 0 === strpos($option_name, '_transient_timeout_feu_einsatz_')
            );
    }

    private function is_allowed_report_meta_key($meta_key) {
        $meta_key = (string) $meta_key;

        return '_thumbnail_id' === $meta_key || 0 === strpos($meta_key, '_feu_einsatz_');
    }

    private function is_allowed_attachment_restore_file($relative_file, $mime_type = '') {
        $relative_file = $this->normalize_upload_relative_path($relative_file);
        $mime_type = strtolower(trim((string) $mime_type));

        if ('' === $relative_file) {
            return false;
        }

        $extension = strtolower((string) pathinfo($relative_file, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp'];
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($extension, $allowed_extensions, true)) {
            return false;
        }

        if ('' !== $mime_type && !in_array($mime_type, $allowed_mime_types, true)) {
            return false;
        }

        return true;
    }

    private function normalize_upload_relative_path($path) {
        $path = ltrim(str_replace('\\', '/', trim((string) $path)), '/');

        if (
            '' === $path
            || false !== strpos($path, "\0")
            || preg_match('#^[A-Za-z]:#', $path)
            || false !== strpos($path, ':')
        ) {
            return '';
        }

        $segments = explode('/', $path);
        $normalized = [];

        foreach ($segments as $segment) {
            if ('' === $segment || '.' === $segment || '..' === $segment) {
                return '';
            }

            $normalized[] = $segment;
        }

        return implode('/', $normalized);
    }

    private function parse_archive_entry_name($entry_name) {
        $entry_name = ltrim(str_replace('\\', '/', (string) $entry_name), '/');

        if (
            '' === $entry_name
            || false !== strpos($entry_name, "\0")
            || preg_match('#^[A-Za-z]:#', $entry_name)
            || preg_match('#(^|/)\.\.(/|$)#', $entry_name)
        ) {
            return false;
        }

        $is_dir = '/' === substr($entry_name, -1);
        $normalized_name = $is_dir ? rtrim($entry_name, '/') : $entry_name;

        if ('manifest.json' === $normalized_name && !$is_dir) {
            return [
                'type' => 'manifest',
                'is_dir' => false,
                'relative' => 'manifest.json',
                'path' => 'manifest.json',
            ];
        }

        if ('uploads' === $normalized_name && $is_dir) {
            return [
                'type' => 'uploads_root',
                'is_dir' => true,
                'relative' => '',
                'path' => 'uploads',
            ];
        }

        if (0 !== strpos($normalized_name, 'uploads/')) {
            return false;
        }

        $relative = $this->normalize_upload_relative_path(substr($normalized_name, strlen('uploads/')));

        if ('' === $relative) {
            return false;
        }

        return [
            'type' => 'upload',
            'is_dir' => $is_dir,
            'relative' => $relative,
            'path' => 'uploads/' . $relative,
        ];
    }

    private function validate_zip_archive($zip) {
        if (!(class_exists('ZipArchive') && $zip instanceof ZipArchive)) {
            return new WP_Error('archive_invalid', __('Archiv konnte nicht verarbeitet werden.', 'feuer-einsatzberichte'));
        }

        $file_count = (int) $zip->numFiles;

        if ($file_count < 1) {
            return new WP_Error('archive_empty', __('Archiv ist leer.', 'feuer-einsatzberichte'));
        }

        if ($file_count > self::MAX_ARCHIVE_FILE_COUNT) {
            return new WP_Error('archive_too_large', __('Archiv enthaelt zu viele Dateien und wurde aus Sicherheitsgruenden blockiert.', 'feuer-einsatzberichte'));
        }

        $has_manifest = false;
        $total_uncompressed_bytes = 0;

        for ($index = 0; $index < $file_count; $index++) {
            $stat = $zip->statIndex($index);

            if (!is_array($stat) || empty($stat['name'])) {
                return new WP_Error('archive_invalid_entry', __('Archiv enthält ungültige Einträge.', 'feuer-einsatzberichte'));
            }

            $entry = $this->parse_archive_entry_name($stat['name']);

            if (false === $entry) {
                return new WP_Error('archive_invalid_path', __('Archiv enthaelt unzulaessige Dateipfade und wurde blockiert.', 'feuer-einsatzberichte'));
            }

            $entry_size = isset($stat['size']) ? max(0, (int) $stat['size']) : 0;

            if ('manifest' === $entry['type']) {
                $has_manifest = true;

                if ($entry_size < 1 || $entry_size > self::MAX_ARCHIVE_MANIFEST_BYTES) {
                    return new WP_Error('manifest_too_large', __('manifest.json ist leer oder zu gross.', 'feuer-einsatzberichte'));
                }
            }

            $total_uncompressed_bytes += $entry_size;

            if ($total_uncompressed_bytes > self::MAX_ARCHIVE_UNCOMPRESSED_BYTES) {
                return new WP_Error('archive_uncompressed_limit', __('Archiv wurde blockiert, weil das entpackte Volumen zu gross ist.', 'feuer-einsatzberichte'));
            }
        }

        if (!$has_manifest) {
            return new WP_Error('manifest_missing', __('manifest.json fehlt im Archiv.', 'feuer-einsatzberichte'));
        }

        return true;
    }

    private function extract_archive_safely($zip, $extract_dir) {
        $file_count = (int) $zip->numFiles;

        for ($index = 0; $index < $file_count; $index++) {
            $stat = $zip->statIndex($index);

            if (!is_array($stat) || empty($stat['name'])) {
                return new WP_Error('archive_invalid_entry', __('Archiv enthält ungültige Einträge.', 'feuer-einsatzberichte'));
            }

            $entry = $this->parse_archive_entry_name($stat['name']);

            if (false === $entry) {
                return new WP_Error('archive_invalid_path', __('Archiv enthaelt unzulaessige Dateipfade und wurde blockiert.', 'feuer-einsatzberichte'));
            }

            if ('manifest' === $entry['type']) {
                $destination = trailingslashit($extract_dir) . 'manifest.json';
            } elseif ('uploads_root' === $entry['type']) {
                $destination = trailingslashit($extract_dir) . 'uploads';
            } else {
                $destination = trailingslashit($extract_dir) . 'uploads/' . $entry['relative'];
            }

            if ($entry['is_dir']) {
                if (!wp_mkdir_p($destination)) {
                    return new WP_Error('archive_extract_failed', __('Archiv konnte nicht sicher entpackt werden.', 'feuer-einsatzberichte'));
                }

                continue;
            }

            if (!wp_mkdir_p(dirname($destination))) {
                return new WP_Error('archive_extract_failed', __('Archiv konnte nicht sicher entpackt werden.', 'feuer-einsatzberichte'));
            }

            $stream = $zip->getStream($stat['name']);

            if (!is_resource($stream)) {
                return new WP_Error('archive_extract_failed', __('Archiv konnte nicht sicher entpackt werden.', 'feuer-einsatzberichte'));
            }

            $handle = fopen($destination, 'wb');

            if (false === $handle) {
                fclose($stream);

                return new WP_Error('archive_extract_failed', __('Archiv konnte nicht sicher entpackt werden.', 'feuer-einsatzberichte'));
            }

            $write_failed = false;

            while (!feof($stream)) {
                $buffer = fread($stream, 1048576);

                if (false === $buffer) {
                    $write_failed = true;
                    break;
                }

                if ('' !== $buffer && false === fwrite($handle, $buffer)) {
                    $write_failed = true;
                    break;
                }
            }

            fclose($stream);
            fclose($handle);

            if ($write_failed) {
                @unlink($destination);

                return new WP_Error('archive_extract_failed', __('Archiv konnte nicht sicher entpackt werden.', 'feuer-einsatzberichte'));
            }
        }

        return true;
    }

    private function normalize_restore_manifest($manifest) {
        if (!is_array($manifest)) {
            return new WP_Error('manifest_invalid', __('manifest.json ist ungültig.', 'feuer-einsatzberichte'));
        }

        $manifest['options'] = isset($manifest['options']) && is_array($manifest['options']) ? $manifest['options'] : [];
        $manifest['transients'] = isset($manifest['transients']) && is_array($manifest['transients']) ? $manifest['transients'] : [];
        $manifest['reports'] = isset($manifest['reports']) && is_array($manifest['reports']) ? $manifest['reports'] : [];
        $manifest['comments'] = isset($manifest['comments']) && is_array($manifest['comments']) ? $manifest['comments'] : [];
        $manifest['terms'] = isset($manifest['terms']) && is_array($manifest['terms']) ? $manifest['terms'] : [];
        $manifest['attachments'] = isset($manifest['attachments']) && is_array($manifest['attachments']) ? $manifest['attachments'] : [];

        $tables = isset($manifest['tables']) && is_array($manifest['tables']) ? $manifest['tables'] : [];

        foreach (['participants', 'stats', 'statistics_cache', 'organizations', 'logs'] as $table_key) {
            $tables[$table_key] = isset($tables[$table_key]) && is_array($tables[$table_key]) ? $tables[$table_key] : [];
        }

        $manifest['tables'] = $tables;

        return $manifest;
    }

    private function ensure_private_directory($path) {
        if (!file_exists($path)) {
            wp_mkdir_p($path);
        }

        $index_php_file = trailingslashit($path) . 'index.php';
        if (!file_exists($index_php_file)) {
            file_put_contents($index_php_file, "<?php\n// Silence is golden.\n");
        }

        $index_file = trailingslashit($path) . 'index.html';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '');
        }

        $htaccess_file = trailingslashit($path) . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents(
                $htaccess_file,
                "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\ndeny from all\n</IfModule>\n"
            );
        }

        $web_config_file = trailingslashit($path) . 'web.config';
        if (!file_exists($web_config_file)) {
            file_put_contents(
                $web_config_file,
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <directoryBrowse enabled=\"false\" />\n    <security>\n      <authorization>\n        <remove users=\"*\" roles=\"\" verbs=\"\" />\n        <add accessType=\"Deny\" users=\"*\" />\n      </authorization>\n    </security>\n  </system.webServer>\n</configuration>\n"
            );
        }
    }

    public function get_archive_storage_dir() {
        if (defined('FEU_EINSATZ_ARCHIVE_DIR') && '' !== trim((string) FEU_EINSATZ_ARCHIVE_DIR)) {
            $path = wp_normalize_path((string) FEU_EINSATZ_ARCHIVE_DIR);
        } else {
            $upload_data = $this->get_upload_data();
            $directory_key = substr(hash_hmac('sha256', home_url('/'), wp_salt('auth')), 0, 24);
            $path = trailingslashit($upload_data['basedir']) . '.feu-private-' . $directory_key;
        }

        $this->ensure_private_directory($path);

        return $path;
    }

    public function get_temp_dir() {
        $path = trailingslashit($this->get_archive_storage_dir()) . '.temp';
        $this->ensure_private_directory($path);

        return $path;
    }

    private function create_work_directory($prefix) {
        $base_dir = trailingslashit($this->get_temp_dir()) . sanitize_key($prefix) . '-' . wp_generate_uuid4();

        wp_mkdir_p($base_dir);

        return $base_dir;
    }

    private function delete_directory_recursive($path) {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $items = scandir($path);

        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $this->delete_directory_recursive(trailingslashit($path) . $item);
        }

        @rmdir($path);
    }

    private function copy_directory_recursive($source, $destination) {
        if (!file_exists($source)) {
            return;
        }

        if (is_file($source)) {
            wp_mkdir_p(dirname($destination));
            copy($source, $destination);
            return;
        }

        wp_mkdir_p($destination);
        $items = scandir($source);

        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $source_path = trailingslashit($source) . $item;
            $destination_path = trailingslashit($destination) . $item;

            if (is_dir($source_path)) {
                $this->copy_directory_recursive($source_path, $destination_path);
            } else {
                wp_mkdir_p(dirname($destination_path));
                copy($source_path, $destination_path);
            }
        }
    }

    private function add_directory_to_zip($zip, $folder_path, $base_path) {
        $items = scandir($folder_path);
        $added_entries = 0;

        if (!is_array($items)) {
            return 0;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $absolute_path = trailingslashit($folder_path) . $item;
            $relative_path = ltrim(str_replace('\\', '/', substr($absolute_path, strlen($base_path))), '/');

            if (is_dir($absolute_path)) {
                if (!$zip->addEmptyDir($relative_path)) {
                    return new WP_Error('zip_add_directory_failed', __('Ordner konnte nicht in das Archiv aufgenommen werden.', 'feuer-einsatzberichte'));
                }

                $added_entries++;
                $nested_result = $this->add_directory_to_zip($zip, $absolute_path, $base_path);

                if (is_wp_error($nested_result)) {
                    return $nested_result;
                }

                $added_entries += (int) $nested_result;
            } else {
                if (!$zip->addFile($absolute_path, $relative_path)) {
                    return new WP_Error('zip_add_file_failed', __('Datei konnte nicht in das Archiv aufgenommen werden.', 'feuer-einsatzberichte'));
                }

                $added_entries++;
            }
        }

        return $added_entries;
    }

    private function create_zip_from_directory($source_dir, $zip_path) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('missing_zip', __('ZipArchive ist auf diesem Server nicht verfügbar.', 'feuer-einsatzberichte'));
        }

        $zip = new ZipArchive();
        $result = $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if (true !== $result) {
            return new WP_Error('zip_open_failed', __('Archiv konnte nicht erstellt werden.', 'feuer-einsatzberichte'));
        }

        $entries_result = $this->add_directory_to_zip($zip, $source_dir, trailingslashit($source_dir));

        if (is_wp_error($entries_result)) {
            $zip->close();
            @unlink($zip_path);

            return $entries_result;
        }

        if ((int) $entries_result < 1) {
            $zip->close();
            @unlink($zip_path);

            return new WP_Error('zip_empty', __('Archiv wurde erzeugt, enthaelt aber keine Dateien.', 'feuer-einsatzberichte'));
        }

        if (true !== $zip->close()) {
            @unlink($zip_path);

            return new WP_Error('zip_close_failed', __('Archiv konnte nicht sauber abgeschlossen werden.', 'feuer-einsatzberichte'));
        }

        clearstatcache(true, $zip_path);

        if (!file_exists($zip_path)) {
            return new WP_Error('zip_missing', __('Archivdatei wurde nicht geschrieben.', 'feuer-einsatzberichte'));
        }

        if ((int) filesize($zip_path) < 32) {
            @unlink($zip_path);

            return new WP_Error('zip_too_small', __('Archiv wurde erzeugt, ist aber ungueltig oder leer.', 'feuer-einsatzberichte'));
        }

        return $zip_path;
    }

    private function get_current_user_summary() {
        $user = wp_get_current_user();

        return [
            'id' => get_current_user_id(),
            'login' => $user instanceof WP_User ? (string) $user->user_login : '',
            'display_name' => $user instanceof WP_User ? (string) $user->display_name : '',
            'email' => $user instanceof WP_User ? (string) $user->user_email : '',
        ];
    }

    private function get_plugin_option_names() {
        global $wpdb;

        return $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'feu_einsatz\_%' ESCAPE '\\'"
        );
    }

    private function collect_plugin_options() {
        $options = [];

        foreach ((array) $this->get_plugin_option_names() as $option_name) {
            $options[$option_name] = get_option($option_name);
        }

        return $options;
    }

    private function collect_plugin_transients() {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_feu_einsatz\_%' ESCAPE '\\'
             OR option_name LIKE '_transient_timeout_feu_einsatz\_%' ESCAPE '\\'"
        );

        $transients = [];

        foreach ((array) $rows as $row) {
            $transients[$row->option_name] = maybe_unserialize($row->option_value);
        }

        return $transients;
    }

    private function get_report_post_ids() {
        global $wpdb;

        return array_map('absint', (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
                '_feu_einsatz_einsatzbericht',
                '1'
            )
        ));
    }

    private function collect_report_records() {
        global $wpdb;

        $report_post_ids = $this->get_report_post_ids();

        if (empty($report_post_ids)) {
            return [];
        }

        $reports = [];

        foreach ($report_post_ids as $post_id) {
            $post = get_post($post_id);

            if (!($post instanceof WP_Post)) {
                continue;
            }

            $meta_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
                    $post_id
                )
            );

            $meta = [];

            foreach ((array) $meta_rows as $meta_row) {
                if (0 !== strpos((string) $meta_row->meta_key, '_feu_einsatz_') && '_thumbnail_id' !== (string) $meta_row->meta_key) {
                    continue;
                }

                $meta[] = [
                    'key' => (string) $meta_row->meta_key,
                    'value' => maybe_unserialize($meta_row->meta_value),
                ];
            }

            $author = get_userdata((int) $post->post_author);

            $reports[] = [
                'id' => (int) $post_id,
                'post' => [
                    'post_title' => (string) $post->post_title,
                    'post_content' => (string) $post->post_content,
                    'post_excerpt' => (string) $post->post_excerpt,
                    'post_status' => (string) $post->post_status,
                    'post_date' => (string) $post->post_date,
                    'post_name' => (string) $post->post_name,
                    'comment_status' => (string) $post->comment_status,
                    'ping_status' => (string) $post->ping_status,
                    'menu_order' => (int) $post->menu_order,
                    'author_login' => $author instanceof WP_User ? (string) $author->user_login : '',
                ],
                'categories' => array_map('absint', wp_get_post_categories($post_id)),
                'meta' => $meta,
            ];
        }

        return $reports;
    }

    private function collect_comment_records($report_records) {
        $report_post_ids = array_map(static function($report) {
            return isset($report['id']) ? absint($report['id']) : 0;
        }, (array) $report_records);
        $report_post_ids = array_values(array_filter($report_post_ids));

        if (empty($report_post_ids)) {
            return [];
        }

        $comments = get_comments([
            'post__in' => $report_post_ids,
            'status' => 'all',
            'orderby' => 'comment_date_gmt',
            'order' => 'ASC',
            'number' => 0,
        ]);

        $records = [];

        foreach ($comments as $comment) {
            $user = !empty($comment->user_id) ? get_userdata((int) $comment->user_id) : null;

            $records[] = [
                'comment_id' => (int) $comment->comment_ID,
                'post_id' => (int) $comment->comment_post_ID,
                'parent_id' => (int) $comment->comment_parent,
                'author' => (string) $comment->comment_author,
                'author_email' => (string) $comment->comment_author_email,
                'author_url' => (string) $comment->comment_author_url,
                'content' => (string) $comment->comment_content,
                'date' => (string) $comment->comment_date,
                'approved' => (string) $comment->comment_approved,
                'type' => (string) $comment->comment_type,
                'user_login' => $user instanceof WP_User ? (string) $user->user_login : '',
            ];
        }

        return $records;
    }

    private function collect_term_records($report_records) {
        $term_ids = [];

        foreach ((array) $report_records as $report) {
            foreach ((array) $report['categories'] as $term_id) {
                $term_ids[] = absint($term_id);
            }
        }

        foreach ((array) get_option('feu_einsatz_categories', []) as $term_id) {
            $term_ids[] = absint($term_id);
        }

        $term_ids = array_values(array_unique(array_filter($term_ids)));
        $terms = [];

        foreach ($term_ids as $term_id) {
            $term = get_term($term_id, 'category');

            if (!$term || is_wp_error($term)) {
                continue;
            }

            $terms[$term_id] = [
                'term_id' => (int) $term->term_id,
                'name' => (string) $term->name,
                'slug' => (string) $term->slug,
                'description' => (string) $term->description,
                'parent' => (int) $term->parent,
            ];

            foreach (array_reverse(get_ancestors($term->term_id, 'category')) as $ancestor_id) {
                $ancestor = get_term($ancestor_id, 'category');

                if ($ancestor && !is_wp_error($ancestor)) {
                    $terms[$ancestor_id] = [
                        'term_id' => (int) $ancestor->term_id,
                        'name' => (string) $ancestor->name,
                        'slug' => (string) $ancestor->slug,
                        'description' => (string) $ancestor->description,
                        'parent' => (int) $ancestor->parent,
                    ];
                }
            }
        }

        return array_values($terms);
    }

    private function get_table_rows($table_name) {
        global $wpdb;

        return array_map(static function($row) {
            return (array) $row;
        }, (array) $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A));
    }

    private function collect_plugin_attachment_ids($report_records) {
        $attachment_ids = [];

        foreach ((array) $report_records as $report) {
            foreach ((array) $report['meta'] as $meta_row) {
                $key = isset($meta_row['key']) ? (string) $meta_row['key'] : '';
                $value = isset($meta_row['value']) ? $meta_row['value'] : null;

                if (in_array($key, ['_thumbnail_id', '_feu_einsatz_generated_map_thumbnail_id'], true)) {
                    $attachment_ids[] = absint($value);
                }

                if ('_feu_einsatz_gallery' === $key && is_array($value)) {
                    $attachment_ids = array_merge($attachment_ids, array_map('absint', $value));
                }
            }
        }

        $participant_rows = $this->get_table_rows($this->db->get_participant_table_name());

        foreach ($participant_rows as $participant_row) {
            $gallery_ids = isset($participant_row['gallery_ids']) ? json_decode((string) $participant_row['gallery_ids'], true) : [];
            $attachment_ids = array_merge($attachment_ids, array_map('absint', (array) $gallery_ids));
            $attachment_ids[] = isset($participant_row['primary_image_id']) ? absint($participant_row['primary_image_id']) : 0;
        }

        $attachment_ids[] = absint(get_option('feu_einsatz_photo_watermark_image_id', 0));

        return array_values(array_unique(array_filter($attachment_ids)));
    }

    private function is_plugin_owned_attachment($attachment_id) {
        $attachment_id = absint($attachment_id);

        if (!$attachment_id || 'attachment' !== get_post_type($attachment_id)) {
            return false;
        }

        if ('1' === (string) get_post_meta($attachment_id, '_feu_einsatz_generated_map_preview', true)) {
            return true;
        }

        $attached_file = wp_normalize_path((string) get_attached_file($attachment_id));
        if ('' === $attached_file) {
            return false;
        }

        $upload_data = $this->get_upload_data();
        $upload_base = trailingslashit(wp_normalize_path((string) $upload_data['basedir']));
        $relative_file = 0 === strpos($attached_file, $upload_base)
            ? ltrim(substr($attached_file, strlen($upload_base)), '/')
            : '';

        return '' !== $relative_file && (
            0 === strpos($relative_file, 'feuer-einsatzberichte/')
            || 0 === strpos($relative_file, 'feuer-einsatzberichte-watermarked/')
            || 0 === strpos($relative_file, 'feuer-einsatzberichte-area/')
        );
    }

    private function get_attachment_file_variants($attachment_id) {
        $variants = [];
        $attached_file = get_post_meta($attachment_id, '_wp_attached_file', true);

        if (!empty($attached_file)) {
            $variants[] = (string) $attached_file;
        }

        $metadata = wp_get_attachment_metadata($attachment_id);

        if (is_array($metadata)) {
            $base_dir = !empty($attached_file) ? dirname((string) $attached_file) : '';

            if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size_data) {
                    if (empty($size_data['file'])) {
                        continue;
                    }

                    $variants[] = ('.' === $base_dir || '' === $base_dir)
                        ? (string) $size_data['file']
                        : trailingslashit($base_dir) . $size_data['file'];
                }
            }

            if (!empty($metadata['original_image'])) {
                $variants[] = ('.' === $base_dir || '' === $base_dir)
                    ? (string) $metadata['original_image']
                    : trailingslashit($base_dir) . $metadata['original_image'];
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }

    private function build_attachment_record($attachment_id) {
        $attachment = get_post($attachment_id);

        if (!($attachment instanceof WP_Post) || 'attachment' !== $attachment->post_type) {
            return null;
        }

        return [
            'id' => (int) $attachment_id,
            'post_title' => (string) $attachment->post_title,
            'post_excerpt' => (string) $attachment->post_excerpt,
            'post_content' => (string) $attachment->post_content,
            'post_date' => (string) $attachment->post_date,
            'post_mime_type' => (string) $attachment->post_mime_type,
            'alt' => (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'attached_file' => (string) get_post_meta($attachment_id, '_wp_attached_file', true),
            'metadata' => wp_get_attachment_metadata($attachment_id),
            'files' => $this->get_attachment_file_variants($attachment_id),
        ];
    }

    private function collect_attachment_records($attachment_ids) {
        $records = [];

        foreach ((array) $attachment_ids as $attachment_id) {
            $record = $this->build_attachment_record(absint($attachment_id));

            if (!empty($record)) {
                $records[] = $record;
            }
        }

        return $records;
    }

    private function build_manifest_summary($payload) {
        return [
            'archive_key' => isset($payload['archive_key']) ? (string) $payload['archive_key'] : '',
            'label' => isset($payload['label']) ? (string) $payload['label'] : '',
            'created_at' => isset($payload['created_at']) ? $payload['created_at'] : current_time('mysql'),
            'reports' => isset($payload['reports']) && is_array($payload['reports']) ? count($payload['reports']) : 0,
            'comments' => isset($payload['comments']) && is_array($payload['comments']) ? count($payload['comments']) : 0,
            'attachments' => isset($payload['attachments']) && is_array($payload['attachments']) ? count($payload['attachments']) : 0,
            'participants' => isset($payload['tables']['participants']) && is_array($payload['tables']['participants']) ? count($payload['tables']['participants']) : 0,
            'statistics_cache' => isset($payload['tables']['statistics_cache']) && is_array($payload['tables']['statistics_cache']) ? count($payload['tables']['statistics_cache']) : 0,
            'organizations' => isset($payload['tables']['organizations']) && is_array($payload['tables']['organizations']) ? count($payload['tables']['organizations']) : 0,
            'logs' => isset($payload['tables']['logs']) && is_array($payload['tables']['logs']) ? count($payload['tables']['logs']) : 0,
        ];
    }

    private function build_backup_payload() {
        $report_records = $this->collect_report_records();
        $attachment_ids = $this->collect_plugin_attachment_ids($report_records);
        $creator = $this->get_current_user_summary();
        $upload_data = $this->get_upload_data();

        return [
            'manifest_version' => 1,
            'created_at' => current_time('mysql'),
            'creator' => $creator,
            'site' => [
                'name' => get_bloginfo('name'),
                'home_url' => home_url('/'),
                'site_url' => site_url('/'),
                'upload_basedir' => $upload_data['basedir'],
                'upload_baseurl' => $upload_data['baseurl'],
            ],
            'plugin' => [
                'version' => FEU_EINSATZ_VERSION,
                'schema_version' => (string) get_option('feu_einsatz_schema_version', ''),
            ],
            'options' => $this->collect_plugin_options(),
            'transients' => $this->collect_plugin_transients(),
            'reports' => $report_records,
            'comments' => $this->collect_comment_records($report_records),
            'terms' => $this->collect_term_records($report_records),
            'attachments' => $this->collect_attachment_records($attachment_ids),
            'tables' => [
                'participants' => $this->get_table_rows($this->db->get_participant_table_name()),
                'stats' => $this->get_table_rows($this->db->get_stats_table_name()),
                'statistics_cache' => $this->get_table_rows($this->db->get_statistics_cache_table_name()),
                'organizations' => $this->get_table_rows($this->db->get_organization_table_name()),
                'logs' => $this->get_table_rows($this->db->get_log_table_name()),
            ],
        ];
    }

    private function copy_attachment_files_to_workdir($attachments, $work_dir) {
        $upload_data = $this->get_upload_data();

        foreach ((array) $attachments as $attachment) {
            foreach ((array) $attachment['files'] as $relative_file) {
                $relative_file = $this->normalize_upload_relative_path($relative_file);

                if ('' === $relative_file) {
                    continue;
                }

                $source_path = trailingslashit($upload_data['basedir']) . $relative_file;
                $destination_path = trailingslashit($work_dir) . 'uploads/' . str_replace('\\', '/', $relative_file);

                if (file_exists($source_path)) {
                    wp_mkdir_p(dirname($destination_path));
                    copy($source_path, $destination_path);
                }
            }
        }
    }

    private function copy_plugin_directories_to_workdir($work_dir) {
        $upload_data = $this->get_upload_data();
        $directories = [
            'feuer-einsatzberichte',
            'feuer-einsatzberichte-watermarked',
            'feuer-einsatzberichte-area',
        ];

        foreach ($directories as $directory) {
            $source = trailingslashit($upload_data['basedir']) . $directory;

            if (!file_exists($source)) {
                continue;
            }

            $destination = trailingslashit($work_dir) . 'uploads/' . $directory;
            $this->copy_directory_recursive($source, $destination);
        }
    }

    public function create_archive($label = '', $enforce_retention = true) {
        $work_dir = $this->create_work_directory('archive-build');
        $archive_key = 'feuer-einsatzberichte-archive-' . gmdate('YmdHis') . '-' . wp_generate_password(8, false, false);
        $archive_filename = sanitize_file_name($archive_key . '.zip');
        $archive_path = trailingslashit($this->get_archive_storage_dir()) . $archive_filename;
        $archive_label = '' !== trim((string) $label)
            ? trim((string) $label)
            : sprintf(__('Archiv %s', 'feuer-einsatzberichte'), current_time('d.m.Y H:i'));

        try {
            if (method_exists($this->db, 'rebuild_all_statistics')) {
                $this->db->rebuild_all_statistics();
            }

            $payload = $this->build_backup_payload();
            $payload['archive_key'] = $archive_key;
            $payload['label'] = $archive_label;
            $payload['source'] = 'created';
            $summary = $this->build_manifest_summary($payload);
            $payload['summary'] = $summary;

            $manifest_json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (!is_string($manifest_json) || '' === trim($manifest_json)) {
                return new WP_Error('manifest_encode_failed', __('Archiv-Metadaten konnten nicht als JSON erzeugt werden.', 'feuer-einsatzberichte'));
            }

            $manifest_path = trailingslashit($work_dir) . 'manifest.json';
            $manifest_write_result = file_put_contents($manifest_path, $manifest_json);

            if (false === $manifest_write_result || !file_exists($manifest_path) || (int) filesize($manifest_path) < 2) {
                return new WP_Error('manifest_write_failed', __('manifest.json konnte nicht in das Archiv-Verzeichnis geschrieben werden.', 'feuer-einsatzberichte'));
            }

            $this->copy_attachment_files_to_workdir($payload['attachments'], $work_dir);
            $this->copy_plugin_directories_to_workdir($work_dir);

            $zip_result = $this->create_zip_from_directory($work_dir, $archive_path);

            if (is_wp_error($zip_result)) {
                return $zip_result;
            }

            $creator = $this->get_current_user_summary();
            $record_result = $this->db->save_archive([
                'archive_key' => $archive_key,
                'filename' => $archive_filename,
                'label' => $archive_label,
                'file_size' => file_exists($archive_path) ? filesize($archive_path) : 0,
                'created_by' => $creator['id'],
                'created_by_name' => $creator['display_name'] ?: $creator['login'],
                'source' => 'created',
                'manifest' => $summary,
            ]);

            if (false === $record_result) {
                return new WP_Error('archive_record_failed', __('Archiv wurde erstellt, aber die Archivliste konnte nicht aktualisiert werden.', 'feuer-einsatzberichte'));
            }

            if ($enforce_retention) {
                $this->enforce_retention_limit();
            }

            FEU_Einsatz_Logger::log(
                'archive_created',
                'archive',
                0,
                __('Archiv erstellt', 'feuer-einsatzberichte'),
                [
                    'archive_key' => $archive_key,
                    'filename' => $archive_filename,
                    'summary' => $summary,
                ]
            );

            return [
                'archive_key' => $archive_key,
                'filename' => $archive_filename,
                'path' => $archive_path,
                'summary' => $summary,
            ];
        } finally {
            $this->delete_directory_recursive($work_dir);
        }
    }

    private function read_manifest_from_zip($archive_path) {
        if (!class_exists('ZipArchive') || !file_exists($archive_path)) {
            return new WP_Error('archive_missing', __('Archiv konnte nicht gelesen werden.', 'feuer-einsatzberichte'));
        }

        $zip = new ZipArchive();
        $result = $zip->open($archive_path);

        if (true !== $result) {
            return new WP_Error('archive_open_failed', __('Archiv konnte nicht geöffnet werden.', 'feuer-einsatzberichte'));
        }

        $validation = $this->validate_zip_archive($zip);

        if (is_wp_error($validation)) {
            $zip->close();

            return $validation;
        }

        $content = $zip->getFromName('manifest.json');
        $zip->close();

        if (false === $content) {
            return new WP_Error('manifest_missing', __('manifest.json fehlt im Archiv.', 'feuer-einsatzberichte'));
        }

        return $this->normalize_restore_manifest(json_decode($content, true));
    }

    private function register_uploaded_archive($archive_path, $source = 'uploaded') {
        $manifest = $this->read_manifest_from_zip($archive_path);

        if (is_wp_error($manifest)) {
            return $manifest;
        }

        $archive_key = !empty($manifest['archive_key'])
            ? sanitize_key($manifest['archive_key'])
            : sanitize_key('feu-einsatz-upload-' . gmdate('YmdHis') . '-' . wp_generate_password(8, false, false));

        $creator = $this->get_current_user_summary();
        $manifest_creator = !empty($manifest['creator']) && is_array($manifest['creator']) ? $manifest['creator'] : [];
        $original_creator_name = '';

        if (!empty($manifest_creator['display_name'])) {
            $original_creator_name = (string) $manifest_creator['display_name'];
        } elseif (!empty($manifest_creator['login'])) {
            $original_creator_name = (string) $manifest_creator['login'];
        }

        $original_creator_id = !empty($manifest_creator['login'])
            ? $this->map_user_by_login((string) $manifest_creator['login'])
            : 0;
        $summary = !empty($manifest['summary']) && is_array($manifest['summary'])
            ? $manifest['summary']
            : $this->build_manifest_summary($manifest);

        $existing = $this->db->get_archive_by_key($archive_key);
        $result = $this->db->save_archive([
            'id' => $existing ? (int) $existing->id : 0,
            'archive_key' => $archive_key,
            'filename' => basename($archive_path),
            'label' => !empty($manifest['label']) ? $manifest['label'] : basename($archive_path),
            'file_size' => file_exists($archive_path) ? filesize($archive_path) : 0,
            'created_by' => $original_creator_id,
            'created_by_name' => '' !== $original_creator_name ? $original_creator_name : ($creator['display_name'] ?: $creator['login']),
            'source' => $source,
            'notes' => !empty($manifest['notes']) ? (string) $manifest['notes'] : '',
            'manifest' => $summary,
            'created_at' => !empty($manifest['created_at']) ? (string) $manifest['created_at'] : current_time('mysql'),
        ]);

        if (false === $result) {
            return new WP_Error('archive_register_failed', __('Archiv konnte nicht in der Liste registriert werden.', 'feuer-einsatzberichte'));
        }

        $this->enforce_retention_limit();

        return $this->db->get_archive_by_key($archive_key);
    }

    private function enforce_retention_limit() {
        $limit = max(1, absint(get_option('feu_einsatz_backup_retention_limit', 5)));
        $archives = $this->db->get_archives(500);

        if (count($archives) <= $limit) {
            return;
        }

        $archives_to_delete = array_slice($archives, $limit);

        foreach ($archives_to_delete as $archive) {
            $archive_path = trailingslashit($this->get_archive_storage_dir()) . $archive->filename;

            if (file_exists($archive_path)) {
                @unlink($archive_path);
            }

            $this->db->delete_archive((int) $archive->id);

            FEU_Einsatz_Logger::log(
                'archive_pruned',
                'archive',
                (int) $archive->id,
                __('Archiv wegen Aufbewahrungslimit entfernt', 'feuer-einsatzberichte'),
                [
                    'filename' => $archive->filename,
                    'retention_limit' => $limit,
                ]
            );
        }
    }

    public function get_archives() {
        return $this->db->get_archives(200);
    }

    private function get_archive_path($archive) {
        $filename = sanitize_file_name(wp_basename((string) $archive->filename));

        if ('' === $filename) {
            $filename = 'invalid-archive.zip';
        }

        $archive_path = trailingslashit($this->get_archive_storage_dir()) . $filename;

        if (!file_exists($archive_path)) {
            $upload_data = $this->get_upload_data();
            $legacy_path = trailingslashit($upload_data['basedir']) . 'feuer-einsatzberichte-archives/' . $filename;

            if (file_exists($legacy_path) && is_readable($legacy_path)) {
                wp_mkdir_p(dirname($archive_path));

                if (@rename($legacy_path, $archive_path) || @copy($legacy_path, $archive_path)) {
                    if (file_exists($legacy_path) && file_exists($archive_path)) {
                        wp_delete_file($legacy_path);
                    }
                }
            }
        }

        return $archive_path;
    }

    private function restore_options($manifest) {
        foreach ((array) $manifest['options'] as $option_name => $value) {
            if (!$this->is_plugin_option_name($option_name)) {
                continue;
            }

            update_option($option_name, $value);
        }

        foreach ((array) $manifest['transients'] as $option_name => $value) {
            if (!$this->is_plugin_transient_name($option_name)) {
                continue;
            }

            update_option($option_name, $value, false);
        }
    }

    private function restore_upload_directories($extract_dir, $manifest) {
        $upload_source = trailingslashit($extract_dir) . 'uploads';

        if (!file_exists($upload_source)) {
            return;
        }

        $upload_data = $this->get_upload_data();
        $allowed_files = [];

        foreach ((array) $manifest['attachments'] as $attachment_data) {
            $attachment_files = [];

            if (!empty($attachment_data['attached_file'])) {
                $attachment_files[] = $attachment_data['attached_file'];
            }

            if (!empty($attachment_data['files']) && is_array($attachment_data['files'])) {
                $attachment_files = array_merge($attachment_files, $attachment_data['files']);
            }

            foreach ($attachment_files as $relative_file) {
                $relative_file = $this->normalize_upload_relative_path($relative_file);

                if (!$this->is_allowed_attachment_restore_file($relative_file)) {
                    continue;
                }

                $allowed_files[$relative_file] = true;
            }
        }

        foreach (array_keys($allowed_files) as $relative_file) {
            $source_file = trailingslashit($upload_source) . $relative_file;
            $destination_file = trailingslashit($upload_data['basedir']) . $relative_file;

            if (!file_exists($source_file)) {
                continue;
            }

            wp_mkdir_p(dirname($destination_file));
            copy($source_file, $destination_file);
        }
    }

    private function restore_terms($manifest) {
        $term_map = [];

        foreach ((array) $manifest['terms'] as $term_data) {
            $slug = isset($term_data['slug']) ? (string) $term_data['slug'] : '';

            if ('' === $slug) {
                continue;
            }

            $parent_old_id = isset($term_data['parent']) ? absint($term_data['parent']) : 0;
            $parent_new_id = $parent_old_id && isset($term_map[$parent_old_id]) ? (int) $term_map[$parent_old_id] : 0;
            $existing = get_term_by('slug', $slug, 'category');

            if ($existing && !is_wp_error($existing)) {
                wp_update_term($existing->term_id, 'category', [
                    'name' => isset($term_data['name']) ? sanitize_text_field($term_data['name']) : $existing->name,
                    'description' => isset($term_data['description']) ? wp_kses_post($term_data['description']) : $existing->description,
                    'parent' => $parent_new_id,
                ]);
                $term_map[(int) $term_data['term_id']] = (int) $existing->term_id;
                continue;
            }

            $inserted = wp_insert_term(
                isset($term_data['name']) ? sanitize_text_field($term_data['name']) : $slug,
                'category',
                [
                    'slug' => sanitize_title($slug),
                    'description' => isset($term_data['description']) ? wp_kses_post($term_data['description']) : '',
                    'parent' => $parent_new_id,
                ]
            );

            if (!is_wp_error($inserted) && !empty($inserted['term_id'])) {
                $term_map[(int) $term_data['term_id']] = (int) $inserted['term_id'];
            }
        }

        return $term_map;
    }

    private function map_user_by_login($user_login) {
        $user_login = trim((string) $user_login);

        if ('' === $user_login) {
            return 0;
        }

        $user = get_user_by('login', $user_login);

        return $user instanceof WP_User ? (int) $user->ID : 0;
    }

    private function restore_attachments($manifest) {
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_map = [];
        $upload_data = $this->get_upload_data();

        foreach ((array) $manifest['attachments'] as $attachment_data) {
            $relative_file = isset($attachment_data['attached_file'])
                ? $this->normalize_upload_relative_path($attachment_data['attached_file'])
                : '';
            $mime_type = isset($attachment_data['post_mime_type']) ? (string) $attachment_data['post_mime_type'] : '';

            if ('' === $relative_file || !$this->is_allowed_attachment_restore_file($relative_file, $mime_type)) {
                continue;
            }

            $absolute_file = trailingslashit($upload_data['basedir']) . $relative_file;

            if (!file_exists($absolute_file)) {
                continue;
            }

            $attachment_id = wp_insert_attachment(
                [
                    'post_mime_type' => $mime_type,
                    'post_title' => isset($attachment_data['post_title']) ? sanitize_text_field($attachment_data['post_title']) : basename($relative_file),
                    'post_excerpt' => isset($attachment_data['post_excerpt']) ? wp_kses_post($attachment_data['post_excerpt']) : '',
                    'post_content' => isset($attachment_data['post_content']) ? wp_kses_post($attachment_data['post_content']) : '',
                    'post_status' => 'inherit',
                    'post_date' => isset($attachment_data['post_date']) ? $attachment_data['post_date'] : current_time('mysql'),
                    'guid' => trailingslashit($upload_data['baseurl']) . str_replace('\\', '/', $relative_file),
                ],
                $absolute_file,
                0,
                true
            );

            if (is_wp_error($attachment_id) || !$attachment_id) {
                continue;
            }

            $metadata = wp_generate_attachment_metadata($attachment_id, $absolute_file);

            if (is_array($metadata)) {
                wp_update_attachment_metadata($attachment_id, $metadata);
            }

            if (!empty($attachment_data['alt'])) {
                update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($attachment_data['alt']));
            }

            $attachment_map[(int) $attachment_data['id']] = (int) $attachment_id;
        }

        return $attachment_map;
    }

    private function replace_upload_paths($value, $manifest) {
        $old_basedir = isset($manifest['site']['upload_basedir']) ? (string) $manifest['site']['upload_basedir'] : '';
        $old_baseurl = isset($manifest['site']['upload_baseurl']) ? (string) $manifest['site']['upload_baseurl'] : '';
        $upload_data = $this->get_upload_data();

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->replace_upload_paths($item, $manifest);
            }

            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        if ('' !== $old_basedir) {
            $value = str_replace($old_basedir, $upload_data['basedir'], $value);
        }

        if ('' !== $old_baseurl) {
            $value = str_replace($old_baseurl, $upload_data['baseurl'], $value);
        }

        return $value;
    }

    private function remap_attachment_references($key, $value, $attachment_map) {
        if (in_array($key, ['_thumbnail_id', '_feu_einsatz_generated_map_thumbnail_id'], true)) {
            return isset($attachment_map[(int) $value]) ? (int) $attachment_map[(int) $value] : 0;
        }

        if ('_feu_einsatz_gallery' === $key && is_array($value)) {
            return array_values(array_filter(array_map(static function($attachment_id) use ($attachment_map) {
                $attachment_id = absint($attachment_id);

                return isset($attachment_map[$attachment_id]) ? (int) $attachment_map[$attachment_id] : 0;
            }, $value)));
        }

        return $value;
    }

    private function truncate_table($table_name) {
        global $wpdb;

        $wpdb->query("DELETE FROM {$table_name}");
    }

    private function restore_reports($manifest, $term_map, $attachment_map) {
        $post_map = [];
        $allowed_statuses = ['draft', 'pending', 'private', 'publish', 'future'];

        foreach ((array) $manifest['reports'] as $report_data) {
            $post_data = isset($report_data['post']) && is_array($report_data['post']) ? $report_data['post'] : [];
            $post_status = isset($post_data['post_status']) ? sanitize_key($post_data['post_status']) : 'draft';

            if (!in_array($post_status, $allowed_statuses, true)) {
                $post_status = 'draft';
            }

            $post_id = wp_insert_post([
                'post_type' => 'post',
                'post_title' => isset($post_data['post_title']) ? sanitize_text_field($post_data['post_title']) : '',
                'post_content' => isset($post_data['post_content']) ? wp_kses_post($post_data['post_content']) : '',
                'post_excerpt' => isset($post_data['post_excerpt']) ? wp_kses_post($post_data['post_excerpt']) : '',
                'post_status' => $post_status,
                'post_date' => isset($post_data['post_date']) ? $post_data['post_date'] : current_time('mysql'),
                'post_name' => isset($post_data['post_name']) ? sanitize_title($post_data['post_name']) : '',
                'comment_status' => !empty($post_data['comment_status']) && 'open' === $post_data['comment_status'] ? 'open' : 'closed',
                'ping_status' => 'closed',
                'menu_order' => isset($post_data['menu_order']) ? (int) $post_data['menu_order'] : 0,
                'post_author' => $this->map_user_by_login(isset($post_data['author_login']) ? $post_data['author_login'] : '') ?: get_current_user_id(),
            ], true);

            if (is_wp_error($post_id) || !$post_id) {
                continue;
            }

            $post_map[(int) $report_data['id']] = (int) $post_id;

            $categories = array_values(array_filter(array_map(static function($term_id) use ($term_map) {
                $term_id = absint($term_id);

                return isset($term_map[$term_id]) ? (int) $term_map[$term_id] : 0;
            }, (array) $report_data['categories'])));

            if (!empty($categories)) {
                wp_set_post_categories($post_id, $categories, false);
            }

            foreach ((array) $report_data['meta'] as $meta_row) {
                $key = isset($meta_row['key']) ? (string) $meta_row['key'] : '';
                $value = isset($meta_row['value']) ? $meta_row['value'] : null;

                if ('' === $key || !$this->is_allowed_report_meta_key($key)) {
                    continue;
                }

                $value = $this->remap_attachment_references($key, $value, $attachment_map);
                $value = $this->replace_upload_paths($value, $manifest);
                update_post_meta($post_id, $key, $value);
            }
        }

        return $post_map;
    }

    private function restore_comments($manifest, $post_map) {
        $comment_map = [];

        foreach ((array) $manifest['comments'] as $comment_data) {
            $old_post_id = isset($comment_data['post_id']) ? absint($comment_data['post_id']) : 0;

            if (!$old_post_id || !isset($post_map[$old_post_id])) {
                continue;
            }

            $comment_id = wp_insert_comment([
                'comment_post_ID' => (int) $post_map[$old_post_id],
                'comment_author' => isset($comment_data['author']) ? sanitize_text_field($comment_data['author']) : '',
                'comment_author_email' => isset($comment_data['author_email']) ? sanitize_email($comment_data['author_email']) : '',
                'comment_author_url' => isset($comment_data['author_url']) ? esc_url_raw($comment_data['author_url']) : '',
                'comment_content' => isset($comment_data['content']) ? wp_kses_post($comment_data['content']) : '',
                'comment_date' => isset($comment_data['date']) ? $comment_data['date'] : current_time('mysql'),
                'comment_approved' => isset($comment_data['approved']) ? $comment_data['approved'] : 1,
                'comment_type' => isset($comment_data['type']) ? $comment_data['type'] : 'comment',
                'user_id' => $this->map_user_by_login(isset($comment_data['user_login']) ? $comment_data['user_login'] : ''),
                'comment_parent' => 0,
            ]);

            if ($comment_id) {
                $comment_map[(int) $comment_data['comment_id']] = (int) $comment_id;
            }
        }

        foreach ((array) $manifest['comments'] as $comment_data) {
            $old_comment_id = isset($comment_data['comment_id']) ? absint($comment_data['comment_id']) : 0;
            $old_parent_id = isset($comment_data['parent_id']) ? absint($comment_data['parent_id']) : 0;

            if (!$old_comment_id || !$old_parent_id || !isset($comment_map[$old_comment_id], $comment_map[$old_parent_id])) {
                continue;
            }

            wp_update_comment([
                'comment_ID' => $comment_map[$old_comment_id],
                'comment_parent' => $comment_map[$old_parent_id],
            ]);
        }
    }

    private function restore_organizations($manifest) {
        global $wpdb;

        $table_name = $this->db->get_organization_table_name();
        $this->truncate_table($table_name);

        foreach ((array) $manifest['tables']['organizations'] as $row) {
            $wpdb->insert($table_name, [
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'name' => isset($row['name']) ? $row['name'] : '',
                'color' => sanitize_hex_color(isset($row['color']) ? $row['color'] : '#0a4b78') ?: '#0a4b78',
                'post_link' => esc_url_raw(isset($row['post_link']) ? $row['post_link'] : ''),
                'is_archived' => !empty($row['is_archived']) ? 1 : 0,
                'created_at' => isset($row['created_at']) ? $row['created_at'] : current_time('mysql'),
            ], ['%d', '%s', '%s', '%s', '%d', '%s']);
        }
    }

    private function restore_participants($manifest, $attachment_map, $term_map) {
        global $wpdb;

        $table_name = $this->db->get_participant_table_name();
        $this->truncate_table($table_name);

        foreach ((array) $manifest['tables']['participants'] as $row) {
            $category_ids = !empty($row['category_ids']) ? json_decode((string) $row['category_ids'], true) : [];
            $category_ids = array_values(array_filter(array_map(static function($term_id) use ($term_map) {
                $term_id = absint($term_id);

                return isset($term_map[$term_id]) ? (int) $term_map[$term_id] : 0;
            }, (array) $category_ids)));
            $gallery_ids = !empty($row['gallery_ids']) ? json_decode((string) $row['gallery_ids'], true) : [];
            $gallery_ids = array_values(array_filter(array_map(static function($attachment_id) use ($attachment_map) {
                $attachment_id = absint($attachment_id);

                return isset($attachment_map[$attachment_id]) ? (int) $attachment_map[$attachment_id] : 0;
            }, (array) $gallery_ids)));

            $primary_image_id = isset($row['primary_image_id']) ? absint($row['primary_image_id']) : 0;
            $primary_image_id = isset($attachment_map[$primary_image_id]) ? (int) $attachment_map[$primary_image_id] : 0;

            $wpdb->insert($table_name, [
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'vorname' => isset($row['vorname']) ? $row['vorname'] : '',
                'nachname' => isset($row['nachname']) ? $row['nachname'] : '',
                'job_title' => isset($row['job_title']) ? $row['job_title'] : '',
                'entry_date' => isset($row['entry_date']) ? $row['entry_date'] : '',
                'rank_title' => isset($row['rank_title']) ? $row['rank_title'] : '',
                'member_function' => isset($row['member_function']) ? $row['member_function'] : '',
                'education' => isset($row['education']) ? $row['education'] : '',
                'description' => isset($row['description']) ? $row['description'] : '',
                'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : 0,
                'category_ids' => wp_json_encode($category_ids),
                'gallery_ids' => wp_json_encode($gallery_ids),
                'primary_image_id' => $primary_image_id,
                'default_functions' => isset($row['default_functions']) ? $row['default_functions'] : '',
                'is_archived' => !empty($row['is_archived']) ? 1 : 0,
                'is_deleted' => !empty($row['is_deleted']) ? 1 : 0,
                'created_at' => isset($row['created_at']) ? $row['created_at'] : current_time('mysql'),
            ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%d', '%s']);
        }
    }

    private function restore_statistics_cache($manifest) {
        global $wpdb;

        $table_name = $this->db->get_statistics_cache_table_name();
        $this->truncate_table($table_name);

        foreach ((array) $manifest['tables']['statistics_cache'] as $row) {
            $wpdb->insert($table_name, [
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'teilnehmer_id' => isset($row['teilnehmer_id']) ? (int) $row['teilnehmer_id'] : 0,
                'jahr' => isset($row['jahr']) ? (int) $row['jahr'] : 0,
                'maschinist' => isset($row['maschinist']) ? (int) $row['maschinist'] : 0,
                'gruppenfuhrer' => isset($row['gruppenfuhrer']) ? (int) $row['gruppenfuhrer'] : 0,
                'pa_tf_traeger1' => isset($row['pa_tf_traeger1']) ? (int) $row['pa_tf_traeger1'] : 0,
                'pa_traeger2' => isset($row['pa_traeger2']) ? (int) $row['pa_traeger2'] : 0,
                'melder' => isset($row['melder']) ? (int) $row['melder'] : 0,
                'wasser1' => isset($row['wasser1']) ? (int) $row['wasser1'] : 0,
                'wasser2' => isset($row['wasser2']) ? (int) $row['wasser2'] : 0,
                'schlauch1' => isset($row['schlauch1']) ? (int) $row['schlauch1'] : 0,
                'schlauch2' => isset($row['schlauch2']) ? (int) $row['schlauch2'] : 0,
                'keine_funktion' => isset($row['keine_funktion']) ? (int) $row['keine_funktion'] : 0,
                'gesamt_einsaetze' => isset($row['gesamt_einsaetze']) ? (int) $row['gesamt_einsaetze'] : 0,
                'letztes_update' => isset($row['letztes_update']) ? $row['letztes_update'] : current_time('mysql'),
            ], ['%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s']);
        }
    }

    private function restore_stats($manifest, $post_map) {
        global $wpdb;

        $table_name = $this->db->get_stats_table_name();
        $this->truncate_table($table_name);

        foreach ((array) $manifest['tables']['stats'] as $row) {
            $old_post_id = isset($row['post_id']) ? absint($row['post_id']) : 0;

            if (!$old_post_id || !isset($post_map[$old_post_id])) {
                continue;
            }

            $wpdb->insert($table_name, [
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'post_id' => (int) $post_map[$old_post_id],
                'teilnehmer_id' => isset($row['teilnehmer_id']) ? (int) $row['teilnehmer_id'] : 0,
                'funktion' => isset($row['funktion']) ? $row['funktion'] : '',
                'created_at' => isset($row['created_at']) ? $row['created_at'] : current_time('mysql'),
            ], ['%d', '%d', '%d', '%s', '%s']);
        }
    }

    private function restore_logs($manifest) {
        global $wpdb;

        $table_name = $this->db->get_log_table_name();
        $this->truncate_table($table_name);

        foreach ((array) $manifest['tables']['logs'] as $row) {
            $wpdb->insert($table_name, [
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : 0,
                'user_name' => isset($row['user_name']) ? $row['user_name'] : '',
                'action_type' => isset($row['action_type']) ? $row['action_type'] : '',
                'entity_type' => isset($row['entity_type']) ? $row['entity_type'] : '',
                'entity_id' => isset($row['entity_id']) ? (int) $row['entity_id'] : 0,
                'message' => isset($row['message']) ? $row['message'] : '',
                'details' => isset($row['details']) ? $row['details'] : '',
                'page_slug' => isset($row['page_slug']) ? $row['page_slug'] : '',
                'page_url' => isset($row['page_url']) ? $row['page_url'] : '',
                'ip_address' => isset($row['ip_address']) ? $row['ip_address'] : '',
                'created_at' => isset($row['created_at']) ? $row['created_at'] : current_time('mysql'),
            ], ['%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']);
        }
    }

    private function remap_restored_options($term_map, $attachment_map) {
        $mapped_categories = array_values(array_filter(array_map(static function($term_id) use ($term_map) {
            $term_id = absint($term_id);

            return isset($term_map[$term_id]) ? (int) $term_map[$term_id] : 0;
        }, (array) get_option('feu_einsatz_categories', []))));

        update_option('feu_einsatz_categories', $mapped_categories);

        $watermark_image_id = absint(get_option('feu_einsatz_photo_watermark_image_id', 0));
        update_option(
            'feu_einsatz_photo_watermark_image_id',
            isset($attachment_map[$watermark_image_id]) ? (int) $attachment_map[$watermark_image_id] : 0
        );
    }

    private function delete_plugin_options_and_transients() {
        global $wpdb;

        foreach ((array) $this->get_plugin_option_names() as $option_name) {
            delete_option($option_name);
        }

        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_feu_einsatz\_%' ESCAPE '\\'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_feu_einsatz\_%' ESCAPE '\\'");
    }

    private function collect_current_plugin_attachment_ids() {
        $report_records = $this->collect_report_records();

        return array_values(array_filter(
            $this->collect_plugin_attachment_ids($report_records),
            [$this, 'is_plugin_owned_attachment']
        ));
    }

    private function purge_plugin_upload_directories() {
        $upload_data = $this->get_upload_data();
        $directories = [
            trailingslashit($upload_data['basedir']) . 'feuer-einsatzberichte',
            trailingslashit($upload_data['basedir']) . 'feuer-einsatzberichte-watermarked',
            trailingslashit($upload_data['basedir']) . 'feuer-einsatzberichte-area',
        ];

        foreach ($directories as $directory) {
            $this->delete_directory_recursive($directory);
        }
    }

    private function purge_current_plugin_data() {
        $report_post_ids = $this->get_report_post_ids();
        $attachment_ids = $this->collect_current_plugin_attachment_ids();

        foreach ($report_post_ids as $post_id) {
            wp_delete_post($post_id, true);
        }

        foreach ($attachment_ids as $attachment_id) {
            if ($this->is_plugin_owned_attachment($attachment_id)) {
                wp_delete_attachment($attachment_id, true);
            }
        }

        $this->truncate_table($this->db->get_participant_table_name());
        $this->truncate_table($this->db->get_stats_table_name());
        $this->truncate_table($this->db->get_statistics_cache_table_name());
        $this->truncate_table($this->db->get_organization_table_name());
        $this->truncate_table($this->db->get_log_table_name());

        $this->delete_plugin_options_and_transients();
        delete_metadata('user', 0, '_feu_einsatz_participant_ranking_unlocked_until', '', true);
        $this->purge_plugin_upload_directories();
        wp_cache_flush();
    }

    public function restore_archive_file($archive_path, $archive_record = null) {
        $extract_dir = $this->create_work_directory('archive-restore');

        try {
            if (!class_exists('ZipArchive')) {
                return new WP_Error('missing_zip', __('ZipArchive ist auf diesem Server nicht verfügbar.', 'feuer-einsatzberichte'));
            }

            $zip = new ZipArchive();
            $open_result = $zip->open($archive_path);

            if (true !== $open_result) {
                return new WP_Error('archive_open_failed', __('Archiv konnte nicht geöffnet werden.', 'feuer-einsatzberichte'));
            }

            $validation = $this->validate_zip_archive($zip);

            if (is_wp_error($validation)) {
                $zip->close();

                return $validation;
            }

            $extract_result = $this->extract_archive_safely($zip, $extract_dir);
            $zip->close();

            if (is_wp_error($extract_result)) {
                return $extract_result;
            }

            $manifest_path = trailingslashit($extract_dir) . 'manifest.json';

            if (!file_exists($manifest_path)) {
                return new WP_Error('manifest_missing', __('manifest.json fehlt im Archiv.', 'feuer-einsatzberichte'));
            }

            $manifest = $this->normalize_restore_manifest(
                json_decode((string) file_get_contents($manifest_path), true)
            );

            if (is_wp_error($manifest)) {
                return $manifest;
            }

            $safety_archive = $this->create_archive(
                sprintf(__('Automatische Sicherung vor Wiederherstellung %s', 'feuer-einsatzberichte'), current_time('d.m.Y H:i')),
                false
            );

            if (is_wp_error($safety_archive)) {
                return new WP_Error(
                    'restore_safety_archive_failed',
                    sprintf(
                        __('Wiederherstellung abgebrochen: Die automatische Sicherung konnte nicht erstellt werden. %s', 'feuer-einsatzberichte'),
                        $safety_archive->get_error_message()
                    )
                );
            }

            $database = new FEU_Einsatz_Database();
            $database->create_tables();
            global $wpdb;
            $wpdb->last_error = '';
            $wpdb->query('START TRANSACTION');

            try {
                $this->purge_current_plugin_data();
                $this->restore_upload_directories($extract_dir, $manifest);
                $this->restore_options($manifest);

                $term_map = $this->restore_terms($manifest);
                $attachment_map = $this->restore_attachments($manifest);
                $this->restore_organizations($manifest);
                $this->restore_participants($manifest, $attachment_map, $term_map);
                $post_map = $this->restore_reports($manifest, $term_map, $attachment_map);
                $this->restore_comments($manifest, $post_map);
                $this->restore_stats($manifest, $post_map);
                if (!method_exists($database, 'rebuild_all_statistics') || false === $database->rebuild_all_statistics()) {
                    $this->restore_statistics_cache($manifest);
                }
                $this->restore_logs($manifest);
                $this->remap_restored_options($term_map, $attachment_map);

                if ('' !== (string) $wpdb->last_error) {
                    throw new RuntimeException((string) $wpdb->last_error);
                }

                $wpdb->query('COMMIT');
            } catch (Throwable $error) {
                $wpdb->query('ROLLBACK');

                return new WP_Error(
                    'archive_restore_failed',
                    sprintf(
                        __('Wiederherstellung fehlgeschlagen. Die Datenbank wurde zurueckgesetzt; die automatische Sicherung bleibt erhalten. %s', 'feuer-einsatzberichte'),
                        $error->getMessage()
                    )
                );
            }

            if ($archive_record) {
                $this->db->save_archive([
                    'id' => (int) $archive_record->id,
                    'archive_key' => (string) $archive_record->archive_key,
                    'filename' => (string) $archive_record->filename,
                    'label' => (string) $archive_record->label,
                    'file_size' => (int) $archive_record->file_size,
                    'created_by' => (int) $archive_record->created_by,
                    'created_by_name' => (string) $archive_record->created_by_name,
                    'source' => (string) $archive_record->source,
                    'notes' => (string) $archive_record->notes,
                    'manifest' => !empty($archive_record->manifest) ? $archive_record->manifest : (!empty($manifest['summary']) ? $manifest['summary'] : ''),
                    'created_at' => (string) $archive_record->created_at,
                    'restored_at' => current_time('mysql'),
                ]);
            }

            flush_rewrite_rules(false);
            wp_cache_flush();

            FEU_Einsatz_Logger::log(
                'archive_restored',
                'archive',
                $archive_record ? (int) $archive_record->id : 0,
                __('Archiv wiederhergestellt', 'feuer-einsatzberichte'),
                [
                    'filename' => basename($archive_path),
                    'summary' => !empty($manifest['summary']) ? $manifest['summary'] : $this->build_manifest_summary($manifest),
                ]
            );

            return true;
        } finally {
            $this->delete_directory_recursive($extract_dir);
        }
    }

    public function handle_create_archive() {
        $this->verify_request('feu_einsatz_create_archive');

        $label = isset($_POST['feu_einsatz_archive_label']) ? sanitize_text_field(wp_unslash($_POST['feu_einsatz_archive_label'])) : '';
        $result = $this->create_archive($label);

        $redirect_url = add_query_arg(
            is_wp_error($result)
                ? ['page' => 'feu-einsatz-archive', 'archive_error' => rawurlencode($result->get_error_message())]
                : ['page' => 'feu-einsatz-archive', 'archive_success' => 1],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);
        exit;
    }

    public function handle_upload_archive() {
        $this->verify_request('feu_einsatz_upload_archive');

        if (empty($_FILES['feu_einsatz_archive_file']) || !is_array($_FILES['feu_einsatz_archive_file'])) {
            wp_safe_redirect(add_query_arg([
                'page' => 'feu-einsatz-archive',
                'archive_error' => rawurlencode(__('Bitte wählen Sie zuerst eine ZIP-Datei aus.', 'feuer-einsatzberichte')),
            ], admin_url('admin.php')));
            exit;
        }

        $upload = $_FILES['feu_einsatz_archive_file'];
        $upload_error = isset($upload['error']) ? (int) $upload['error'] : UPLOAD_ERR_NO_FILE;

        if (
            UPLOAD_ERR_OK !== $upload_error
            || empty($upload['tmp_name'])
            || !is_uploaded_file($upload['tmp_name'])
        ) {
            wp_safe_redirect(add_query_arg([
                'page' => 'feu-einsatz-archive',
                'archive_error' => rawurlencode(__('Archiv konnte nicht hochgeladen werden.', 'feuer-einsatzberichte')),
            ], admin_url('admin.php')));
            exit;
        }

        $file_size = isset($upload['size']) ? (int) $upload['size'] : 0;

        if ($file_size < 1) {
            wp_safe_redirect(add_query_arg([
                'page' => 'feu-einsatz-archive',
                'archive_error' => rawurlencode(__('Die hochgeladene Datei ist leer.', 'feuer-einsatzberichte')),
            ], admin_url('admin.php')));
            exit;
        }

        if ($file_size > $this->get_max_archive_upload_bytes()) {
            wp_safe_redirect(add_query_arg([
                'page' => 'feu-einsatz-archive',
                'archive_error' => rawurlencode(__('Die ZIP-Datei ist für den sicheren Import zu groß.', 'feuer-einsatzberichte')),
            ], admin_url('admin.php')));
            exit;
        }

        $file_name = sanitize_file_name((string) $upload['name']);

        if ('zip' !== strtolower(pathinfo($file_name, PATHINFO_EXTENSION))) {
            wp_safe_redirect(add_query_arg([
                'page' => 'feu-einsatz-archive',
                'archive_error' => rawurlencode(__('Nur ZIP-Archive sind erlaubt.', 'feuer-einsatzberichte')),
            ], admin_url('admin.php')));
            exit;
        }

        if (!$this->is_valid_uploaded_archive_file((string) $upload['tmp_name'], $file_name)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'feu-einsatz-archive',
                'archive_error' => rawurlencode(__('Die hochgeladene Datei ist kein gueltiges ZIP-Archiv.', 'feuer-einsatzberichte')),
            ], admin_url('admin.php')));
            exit;
        }

        $storage_dir = $this->get_archive_storage_dir();
        $destination = trailingslashit($storage_dir) . wp_unique_filename($storage_dir, $file_name);

        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            wp_safe_redirect(add_query_arg([
                'page' => 'feu-einsatz-archive',
                'archive_error' => rawurlencode(__('Archiv konnte nicht hochgeladen werden.', 'feuer-einsatzberichte')),
            ], admin_url('admin.php')));
            exit;
        }

        $result = $this->register_uploaded_archive($destination, 'uploaded');

        if (is_wp_error($result)) {
            @unlink($destination);
            wp_safe_redirect(add_query_arg([
                'page' => 'feu-einsatz-archive',
                'archive_error' => rawurlencode($result->get_error_message()),
            ], admin_url('admin.php')));
            exit;
        }

        FEU_Einsatz_Logger::log(
            'archive_uploaded',
            'archive',
            (int) $result->id,
            __('Archiv hochgeladen', 'feuer-einsatzberichte'),
            ['filename' => basename($destination)]
        );

        wp_safe_redirect(add_query_arg([
            'page' => 'feu-einsatz-archive',
            'archive_uploaded' => 1,
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_restore_archive() {
        $this->verify_request('feu_einsatz_restore_archive');

        $archive_id = isset($_GET['archive_id']) ? absint(wp_unslash($_GET['archive_id'])) : 0;
        $archive = $this->db->get_archive($archive_id);

        if (!$archive) {
            wp_safe_redirect(add_query_arg([
                'page' => 'feu-einsatz-archive',
                'archive_error' => rawurlencode(__('Archiv wurde nicht gefunden.', 'feuer-einsatzberichte')),
            ], admin_url('admin.php')));
            exit;
        }

        $archive_path = $this->get_archive_path($archive);
        $result = $this->restore_archive_file($archive_path, $archive);

        wp_safe_redirect(add_query_arg(
            is_wp_error($result)
                ? ['page' => 'feu-einsatz-archive', 'archive_error' => rawurlencode($result->get_error_message())]
                : ['page' => 'feu-einsatz-archive', 'archive_restored' => 1],
            admin_url('admin.php')
        ));
        exit;
    }

    public function handle_delete_archive() {
        $this->verify_request('feu_einsatz_delete_archive');

        $archive_id = isset($_GET['archive_id']) ? absint(wp_unslash($_GET['archive_id'])) : 0;
        $archive = $this->db->get_archive($archive_id);

        if ($archive) {
            $archive_path = $this->get_archive_path($archive);

            if (file_exists($archive_path)) {
                @unlink($archive_path);
            }

            $this->db->delete_archive($archive_id);

            FEU_Einsatz_Logger::log(
                'archive_deleted',
                'archive',
                $archive_id,
                __('Archiv geloescht', 'feuer-einsatzberichte'),
                ['filename' => $archive->filename]
            );
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'feu-einsatz-archive',
            'archive_deleted' => 1,
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_download_archive() {
        $this->verify_request('feu_einsatz_download_archive');

        $archive_id = isset($_GET['archive_id']) ? absint(wp_unslash($_GET['archive_id'])) : 0;
        $archive = $this->db->get_archive($archive_id);

        if (!$archive) {
            wp_die(esc_html__('Archiv wurde nicht gefunden.', 'feuer-einsatzberichte'));
        }

        $archive_path = $this->get_archive_path($archive);

        if (!file_exists($archive_path)) {
            wp_die(esc_html__('Archivdatei fehlt.', 'feuer-einsatzberichte'));
        }

        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($archive_path) . '"');
        header('Content-Length: ' . filesize($archive_path));
        readfile($archive_path);
        exit;
    }
}


