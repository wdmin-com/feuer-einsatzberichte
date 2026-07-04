<?php
if (!defined('ABSPATH')) {
    exit;
}

class FEU_Einsatz_Image_Protection {

    const OUTPUT_DIRECTORY = 'feuer-einsatzberichte-watermarked';
    const BACKGROUND_HOOK = 'feu_einsatz_prime_watermark_cache';
    const MAX_IMAGE_PIXELS = 16000000;
    const MAX_IMAGE_DIMENSION = 6000;

    public static function is_image_watermark_enabled() {
        return 1 === (int) get_option('feu_einsatz_photo_watermark_enabled', 1)
            && self::get_watermark_attachment_id() > 0;
    }

    public static function get_protected_image_urls($attachment_id, $allow_generation = null) {
        $attachment_id = absint($attachment_id);

        if (!$attachment_id || !self::is_image_watermark_enabled()) {
            return false;
        }

        if (null === $allow_generation) {
            $allow_generation = is_admin() || (defined('DOING_AJAX') && DOING_AJAX);
        }

        $full_image = self::generate_protected_image($attachment_id, 'full', (bool) $allow_generation);
        if (is_wp_error($full_image) || empty($full_image['url'])) {
            return false;
        }

        $thumb_image = self::generate_protected_image($attachment_id, 'medium_large', (bool) $allow_generation);
        if (is_wp_error($thumb_image) || empty($thumb_image['url'])) {
            $thumb_image = $full_image;
        }

        return [
            'thumb' => $thumb_image['url'],
            'full' => $full_image['url'],
            'embedded' => true,
        ];
    }

    public static function prime_attachment_cache($attachment_ids) {
        if (!self::is_image_watermark_enabled()) {
            return;
        }

        $attachment_ids = array_values(array_unique(array_filter(array_map('absint', (array) $attachment_ids))));

        if (empty($attachment_ids)) {
            return;
        }

        $queue_key = 'feu_einsatz_watermark_' . md5(wp_json_encode($attachment_ids));

        if (get_transient($queue_key)) {
            return;
        }

        set_transient($queue_key, 1, HOUR_IN_SECONDS);
        wp_schedule_single_event(time() + 30, self::BACKGROUND_HOOK, [$attachment_ids, $queue_key]);
    }

    public static function handle_background_generation($attachment_ids, $queue_key = '') {
        foreach (array_values(array_unique(array_filter(array_map('absint', (array) $attachment_ids)))) as $attachment_id) {
            self::generate_protected_image($attachment_id, 'medium_large', true);
            self::generate_protected_image($attachment_id, 'full', true);
        }

        if ('' !== $queue_key) {
            delete_transient(sanitize_key((string) $queue_key));
        }
    }

    private static function get_watermark_attachment_id() {
        return absint(get_option('feu_einsatz_photo_watermark_image_id', 0));
    }

    private static function get_watermark_opacity() {
        $opacity = (int) get_option('feu_einsatz_photo_watermark_opacity', 36);

        return max(5, min(100, $opacity));
    }

    private static function get_watermark_scale() {
        $scale = (int) get_option('feu_einsatz_photo_watermark_scale', 42);

        return max(10, min(90, $scale));
    }

    private static function generate_protected_image($attachment_id, $size, $allow_generation = true) {
        if (!function_exists('imagecreatetruecolor')) {
            return new WP_Error(
                'feu_einsatz_missing_gd',
                __('Die GD-Bildbibliothek ist auf dem Server nicht verfügbar.', 'feuer-einsatzberichte')
            );
        }

        $source = self::get_attachment_variant($attachment_id, $size);
        if (is_wp_error($source)) {
            return $source;
        }

        $watermark = self::get_watermark_source();
        if (is_wp_error($watermark)) {
            return $watermark;
        }

        $output = self::get_output_target($attachment_id, $size, $source, $watermark);
        if (is_wp_error($output)) {
            return $output;
        }

        if (file_exists($output['path'])) {
            return $output;
        }

        if (!$allow_generation) {
            return false;
        }

        if (!self::is_processable_image($source) || !self::is_processable_image($watermark)) {
            return new WP_Error(
                'feu_einsatz_watermark_image_too_large',
                __('Das Wasserzeichen konnte nicht erzeugt werden, weil eine Bilddatei für die sichere Serververarbeitung zu groß ist.', 'feuer-einsatzberichte')
            );
        }

        $source_resource = self::load_image_resource($source['path'], $source['mime']);
        if (is_wp_error($source_resource)) {
            return $source_resource;
        }

        $watermark_resource = self::load_image_resource($watermark['path'], $watermark['mime']);
        if (is_wp_error($watermark_resource)) {
            imagedestroy($source_resource);
            return $watermark_resource;
        }

        $render_result = self::render_watermark($source_resource, $watermark_resource);
        imagedestroy($watermark_resource);

        if (is_wp_error($render_result)) {
            imagedestroy($source_resource);
            return $render_result;
        }

        self::cleanup_stale_variants($attachment_id, $size, $output['path']);

        $saved = self::save_image_resource($source_resource, $output['path'], $output['format']);
        imagedestroy($source_resource);

        if (!$saved) {
            return new WP_Error(
                'feu_einsatz_watermark_save_failed',
                __('Das geschuetzte Bild konnte nicht gespeichert werden.', 'feuer-einsatzberichte')
            );
        }

        return $output;
    }

    private static function get_attachment_variant($attachment_id, $size) {
        $attachment_path = get_attached_file($attachment_id);
        if (!$attachment_path || !file_exists($attachment_path)) {
            return new WP_Error(
                'feu_einsatz_attachment_missing',
                __('Das Originalbild für das Wasserzeichen wurde nicht gefunden.', 'feuer-einsatzberichte')
            );
        }

        $candidate_path = $attachment_path;
        if ('full' !== $size) {
            $metadata = wp_get_attachment_metadata($attachment_id);
            $base_dir = trailingslashit(pathinfo($attachment_path, PATHINFO_DIRNAME));

            if (!empty($metadata['sizes'][$size]['file'])) {
                $intermediate_path = $base_dir . $metadata['sizes'][$size]['file'];
                if (file_exists($intermediate_path)) {
                    $candidate_path = $intermediate_path;
                }
            }
        }

        $image_info = self::get_image_info($candidate_path);
        if (is_wp_error($image_info)) {
            return $image_info;
        }

        return [
            'path' => $candidate_path,
            'mime' => $image_info['mime'],
            'width' => $image_info['width'],
            'height' => $image_info['height'],
        ];
    }

    private static function get_watermark_source() {
        $watermark_id = self::get_watermark_attachment_id();
        if (!$watermark_id) {
            return new WP_Error(
                'feu_einsatz_watermark_not_configured',
                __('Es wurde noch kein Wasserzeichen-Bild ausgewählt.', 'feuer-einsatzberichte')
            );
        }

        $watermark_path = get_attached_file($watermark_id);
        if (!$watermark_path || !file_exists($watermark_path)) {
            return new WP_Error(
                'feu_einsatz_watermark_file_missing',
                __('Die Wasserzeichen-Datei konnte nicht gefunden werden.', 'feuer-einsatzberichte')
            );
        }

        $image_info = self::get_image_info($watermark_path);
        if (is_wp_error($image_info)) {
            return $image_info;
        }

        return [
            'path' => $watermark_path,
            'mime' => $image_info['mime'],
            'width' => $image_info['width'],
            'height' => $image_info['height'],
        ];
    }

    private static function get_image_info($path) {
        $image_size = @getimagesize($path);
        if (!$image_size || empty($image_size['mime'])) {
            return new WP_Error(
                'feu_einsatz_invalid_image',
                __('Eine Bilddatei konnte nicht gelesen werden.', 'feuer-einsatzberichte')
            );
        }

        return [
            'width' => (int) $image_size[0],
            'height' => (int) $image_size[1],
            'mime' => (string) $image_size['mime'],
        ];
    }

    private static function is_processable_image($image_info) {
        $width = isset($image_info['width']) ? (int) $image_info['width'] : 0;
        $height = isset($image_info['height']) ? (int) $image_info['height'] : 0;

        if ($width < 1 || $height < 1) {
            return false;
        }

        if ($width > self::MAX_IMAGE_DIMENSION || $height > self::MAX_IMAGE_DIMENSION) {
            return false;
        }

        return ($width * $height) <= self::MAX_IMAGE_PIXELS;
    }

    private static function get_output_target($attachment_id, $size, $source, $watermark) {
        $upload_dir = wp_upload_dir();

        if (!empty($upload_dir['error'])) {
            return new WP_Error(
                'feu_einsatz_watermark_upload_dir_missing',
                __('Der Upload-Ordner für geschützte Bilder ist nicht verfügbar.', 'feuer-einsatzberichte')
            );
        }

        $target_subdir = self::get_output_subdirectory($attachment_id);
        $target_dir = trailingslashit($upload_dir['basedir']) . $target_subdir;
        $target_url = trailingslashit($upload_dir['baseurl']) . str_replace('\\', '/', $target_subdir);

        if (!is_dir($target_dir) && !wp_mkdir_p($target_dir)) {
            return new WP_Error(
                'feu_einsatz_watermark_directory_create_failed',
                __('Der Ordner für geschützte Bilder konnte nicht angelegt werden.', 'feuer-einsatzberichte')
            );
        }

        if (!is_dir($target_dir)) {
            return new WP_Error(
                'feu_einsatz_watermark_directory_missing',
                __('Der Ordner für geschützte Bilder ist nicht vorhanden.', 'feuer-einsatzberichte')
            );
        }

        if (!file_exists(trailingslashit($target_dir) . 'index.html')) {
            $index_file = trailingslashit($target_dir) . 'index.html';
            @file_put_contents($index_file, '');
        }

        $format = self::determine_output_format($source['mime']);
        $hash = md5(implode('|', [
            $attachment_id,
            $size,
            $source['path'],
            @filemtime($source['path']),
            $watermark['path'],
            @filemtime($watermark['path']),
            self::get_watermark_opacity(),
            self::get_watermark_scale(),
            $format['extension'],
        ]));

        $filename = 'feu-einsatz-protected-' . $attachment_id . '-' . $size . '-' . $hash . '.' . $format['extension'];

        return [
            'path' => trailingslashit($target_dir) . $filename,
            'url' => trailingslashit($target_url) . $filename,
            'format' => $format['key'],
        ];
    }

    private static function get_output_subdirectory($attachment_id) {
        $attachment_id = absint($attachment_id);
        $relative_path = (string) get_post_meta($attachment_id, '_wp_attached_file', true);

        if (preg_match('#^(\d{4})/(\d{2})/#', str_replace('\\', '/', $relative_path), $matches)) {
            return self::OUTPUT_DIRECTORY . '/' . $matches[1] . '/' . $matches[2];
        }

        $attachment = get_post($attachment_id);
        if ($attachment instanceof WP_Post && !empty($attachment->post_date)) {
            $timestamp = strtotime((string) $attachment->post_date);

            if ($timestamp) {
                return self::OUTPUT_DIRECTORY . '/' . gmdate('Y', $timestamp) . '/' . gmdate('m', $timestamp);
            }
        }

        return self::OUTPUT_DIRECTORY . '/' . gmdate('Y') . '/' . gmdate('m');
    }

    private static function determine_output_format($mime_type) {
        switch ($mime_type) {
            case 'image/png':
                return [
                    'key' => 'png',
                    'extension' => 'png',
                ];

            case 'image/webp':
                if (function_exists('imagewebp')) {
                    return [
                        'key' => 'webp',
                        'extension' => 'webp',
                    ];
                }
                break;

            case 'image/jpeg':
            case 'image/jpg':
                return [
                    'key' => 'jpg',
                    'extension' => 'jpg',
                ];

            case 'image/gif':
            default:
                break;
        }

        return [
            'key' => 'png',
            'extension' => 'png',
        ];
    }

    private static function load_image_resource($path, $mime_type) {
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/jpg':
                $resource = function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false;
                break;

            case 'image/png':
                $resource = function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false;
                break;

            case 'image/gif':
                $resource = function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : false;
                break;

            case 'image/webp':
                $resource = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
                break;

            default:
                $resource = false;
                break;
        }

        if (!$resource) {
            return new WP_Error(
                'feu_einsatz_unsupported_image_format',
                __('Dieses Bildformat wird für das Wasserzeichen nicht unterstützt.', 'feuer-einsatzberichte')
            );
        }

        $width = imagesx($resource);
        $height = imagesy($resource);
        $canvas = imagecreatetruecolor($width, $height);

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopy($canvas, $resource, 0, 0, 0, 0, $width, $height);

        imagedestroy($resource);

        return $canvas;
    }

    private static function render_watermark($source_resource, $watermark_resource) {
        $source_width = imagesx($source_resource);
        $source_height = imagesy($source_resource);
        $watermark_width = imagesx($watermark_resource);
        $watermark_height = imagesy($watermark_resource);

        if ($source_width < 1 || $source_height < 1 || $watermark_width < 1 || $watermark_height < 1) {
            return new WP_Error(
                'feu_einsatz_invalid_image_dimensions',
                __('Das Wasserzeichen konnte wegen ungültiger Bildgrößen nicht erzeugt werden.', 'feuer-einsatzberichte')
            );
        }

        $scale_ratio = self::get_watermark_scale() / 100;
        $max_width = max(1, (int) round($source_width * $scale_ratio));
        $max_height = max(1, (int) round($source_height * $scale_ratio));
        $ratio = min($max_width / $watermark_width, $max_height / $watermark_height);
        $ratio = max(0.05, $ratio);

        $target_width = max(1, (int) round($watermark_width * $ratio));
        $target_height = max(1, (int) round($watermark_height * $ratio));

        $scaled_watermark = imagecreatetruecolor($target_width, $target_height);
        imagealphablending($scaled_watermark, false);
        imagesavealpha($scaled_watermark, true);

        $transparent = imagecolorallocatealpha($scaled_watermark, 0, 0, 0, 127);
        imagefill($scaled_watermark, 0, 0, $transparent);

        imagecopyresampled(
            $scaled_watermark,
            $watermark_resource,
            0,
            0,
            0,
            0,
            $target_width,
            $target_height,
            $watermark_width,
            $watermark_height
        );

        self::apply_opacity($scaled_watermark, self::get_watermark_opacity());

        imagealphablending($source_resource, true);
        imagesavealpha($source_resource, true);

        imagecopy(
            $source_resource,
            $scaled_watermark,
            (int) round(($source_width - $target_width) / 2),
            (int) round(($source_height - $target_height) / 2),
            0,
            0,
            $target_width,
            $target_height
        );

        imagedestroy($scaled_watermark);

        return true;
    }

    private static function apply_opacity($image_resource, $opacity_percent) {
        $opacity_percent = max(0, min(100, (int) $opacity_percent));

        if (100 === $opacity_percent) {
            return;
        }

        if (function_exists('imagefilter') && defined('IMG_FILTER_COLORIZE')) {
            $additional_alpha = (int) round(127 * (1 - ($opacity_percent / 100)));

            if (imagefilter($image_resource, IMG_FILTER_COLORIZE, 0, 0, 0, $additional_alpha)) {
                return;
            }
        }

        $width = imagesx($image_resource);
        $height = imagesy($image_resource);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgba = imagecolorat($image_resource, $x, $y);
                $colors = imagecolorsforindex($image_resource, $rgba);

                $effective_alpha = 127 - ((127 - $colors['alpha']) * ($opacity_percent / 100));
                $effective_alpha = (int) round(max(0, min(127, $effective_alpha)));

                $color = imagecolorallocatealpha(
                    $image_resource,
                    $colors['red'],
                    $colors['green'],
                    $colors['blue'],
                    $effective_alpha
                );

                imagesetpixel($image_resource, $x, $y, $color);
            }
        }
    }

    private static function cleanup_stale_variants($attachment_id, $size, $keep_file) {
        $directory = trailingslashit(dirname($keep_file));
        $pattern = $directory . 'feu-einsatz-protected-' . absint($attachment_id) . '-' . $size . '-*.*';
        $files = glob($pattern);

        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            if ($file !== $keep_file && is_file($file)) {
                @unlink($file);
            }
        }
    }

    private static function save_image_resource($resource, $path, $format) {
        switch ($format) {
            case 'jpg':
                imageinterlace($resource, true);
                return imagejpeg($resource, $path, 90);

            case 'webp':
                return function_exists('imagewebp') ? imagewebp($resource, $path, 90) : false;

            case 'png':
            default:
                return imagepng($resource, $path, 6);
        }
    }
}
