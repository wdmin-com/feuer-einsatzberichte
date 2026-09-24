<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Finds and renders opt-in public templates.  Custom PHP is deliberately
 * loaded only from the active/parent theme, never from an upload directory:
 * a template is executable code and must remain under version control.
 */
final class FEU_Einsatz_Template_Manager {
    const OPTION_PREFIX = 'feu_einsatz_template_';
    const TYPES = ['single', 'overview', 'sidebar'];

    public static function get_option_key($type) {
        return self::OPTION_PREFIX . self::normalize_type($type);
    }

    public static function normalize_type($type) {
        $type = sanitize_key((string) $type);
        return in_array($type, self::TYPES, true) ? $type : 'single';
    }

    public static function get_selected($type) {
        $selected = sanitize_file_name((string) get_option(self::get_option_key($type), 'default'));
        return isset(self::get_templates($type)[$selected]) ? $selected : 'default';
    }

    public static function update_selected($type, $value) {
        $type = self::normalize_type($type);
        $value = sanitize_file_name((string) $value);
        $templates = self::get_templates($type);
        update_option(self::get_option_key($type), isset($templates[$value]) ? $value : 'default', false);
    }

    /** @return array<string,array{label:string,path:string,format:string,origin:string}> */
    public static function get_templates($type) {
        $type = self::normalize_type($type);
        $templates = [
            'default' => [
                'label' => __('Plugin-Standard', 'feuer-einsatzberichte'),
                'path' => '',
                'format' => 'php',
                'origin' => 'plugin',
            ],
        ];

        foreach (self::get_search_roots() as $origin => $root) {
            if (!is_dir($root)) {
                continue;
            }

            $candidate_paths = array_merge(
                glob(trailingslashit($root) . 'custom_*.php') ?: [],
                glob(trailingslashit($root) . 'custom_*.html') ?: []
            );
            foreach ($candidate_paths as $path) {
                $basename = basename($path);
                if (!preg_match('/^custom_[a-z0-9_-]+\.(php|html)$/i', $basename, $matches)) {
                    continue;
                }

                $declared_type = self::get_template_type($path);
                $prefix_type = 0 === strpos(strtolower($basename), 'custom_' . $type . '_') ? $type : '';
                if ($type !== $declared_type && $type !== $prefix_type) {
                    continue;
                }

                // The stylesheet (child) directory wins over the parent and plugin example.
                $key = strtolower($basename);
                if (isset($templates[$key])) {
                    continue;
                }

                $templates[$key] = [
                    'label' => self::get_template_label($path, $basename),
                    'path' => wp_normalize_path($path),
                    'format' => strtolower($matches[1]),
                    'origin' => $origin,
                ];
            }
        }

        return $templates;
    }

    /** @return array<string,string> */
    private static function get_search_roots() {
        $roots = [];
        $stylesheet_root = trailingslashit(get_stylesheet_directory()) . 'feuer-einsatzberichte/templates';
        if ('' !== (string) get_stylesheet_directory()) {
            $roots['child-theme'] = $stylesheet_root;
        }

        $template_directory = get_template_directory();
        $parent_root = trailingslashit($template_directory) . 'feuer-einsatzberichte/templates';
        if ('' !== $template_directory && wp_normalize_path($parent_root) !== wp_normalize_path($stylesheet_root)) {
            $roots['theme'] = $parent_root;
        }

        $roots['plugin-example'] = FEU_EINSATZ_PLUGIN_DIR . 'templates/public/custom';
        return $roots;
    }

    private static function get_template_label($path, $fallback) {
        $header = (string) @file_get_contents($path, false, null, 0, 2048);
        if (preg_match('/(?:Template Name|Vorlagenname)\s*:\s*(.+)$/mi', $header, $matches)) {
            return trim(sanitize_text_field($matches[1]));
        }
        return preg_replace('/\.(php|html)$/i', '', $fallback);
    }

    private static function get_template_type($path) {
        $header = (string) @file_get_contents($path, false, null, 0, 2048);
        if (preg_match('/Template Type\s*:\s*(single|overview|sidebar)\s*(?:-->)?\s*$/mi', $header, $matches)) {
            return self::normalize_type($matches[1]);
        }
        return '';
    }

    public static function render($type, array $context = [], $default_template = '') {
        $selected = self::get_selected($type);
        if ('default' === $selected) {
            return '' !== $default_template ? FEU_Einsatz_Template_Helpers::render($default_template, $context) : '';
        }

        $template = self::get_templates($type)[$selected] ?? null;
        if (!is_array($template) || empty($template['path']) || !is_readable($template['path'])) {
            return '' !== $default_template ? FEU_Einsatz_Template_Helpers::render($default_template, $context) : '';
        }

        try {
            if ('html' === $template['format']) {
                return self::render_html_template($template['path'], $context);
            }

            extract($context, EXTR_SKIP);
            ob_start();
            include $template['path'];
            return (string) ob_get_clean();
        } catch (Throwable $error) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_log('[Feuer-Einsatzberichte] Custom template failed: ' . $error->getMessage());
            return '' !== $default_template ? FEU_Einsatz_Template_Helpers::render($default_template, $context) : '';
        }
    }

    /**
     * HTML templates are intentionally data-only. Macros are rendered by the
     * plugin, so a designer never needs to write PHP to use report content.
     */
    public static function render_html_template($path, array $context = []) {
        $html = (string) @file_get_contents($path);
        if ('' === $html) {
            return '';
        }

        $public_context = isset($context['context']) && is_array($context['context']) ? $context['context'] : $context;
        $report = isset($public_context['report']) && is_array($public_context['report']) ? $public_context['report'] : [];
        $single_context = $public_context;
        $theme_header = '';
        $theme_footer = '';
        // Overview and sidebar templates are usually shortcodes embedded in
        // an existing page. Only a single HTML template that explicitly uses
        // these macros may emit the surrounding theme markup.
        if (false !== strpos($html, '{{feu:header}}')) {
            ob_start();
            get_header();
            $theme_header = (string) ob_get_clean();
        }
        if (false !== strpos($html, '{{feu:footer}}')) {
            ob_start();
            get_footer();
            $theme_footer = (string) ob_get_clean();
        }
        $partials = [
            'header' => $theme_header,
            'footer' => $theme_footer,
            'breadcrumbs' => FEU_Einsatz_Template_Helpers::render('templates/public/single/breadcrumbs.php', ['context' => $single_context]),
            'map' => FEU_Einsatz_Template_Helpers::render('templates/public/single/map.php', ['context' => $single_context]),
            'info' => FEU_Einsatz_Template_Helpers::render('templates/public/single/info.php', ['context' => $single_context]),
            'content' => FEU_Einsatz_Template_Helpers::render('templates/public/single/content.php', ['context' => $single_context]),
            'gallery' => FEU_Einsatz_Template_Helpers::render('templates/public/single/gallery.php', ['context' => $single_context]),
            'share' => FEU_Einsatz_Template_Helpers::render('templates/public/single/share.php', ['context' => $single_context, 'share_data' => []]),
            'comments' => FEU_Einsatz_Template_Helpers::render('templates/public/single/comments.php', ['context' => $single_context]),
            'related' => FEU_Einsatz_Template_Helpers::render('templates/public/single/related.php', ['context' => $single_context]),
            'list' => FEU_Einsatz_Template_Helpers::render('templates/public/overview/list.php', ['context' => $public_context]),
            'sidebar' => FEU_Einsatz_Template_Helpers::render('templates/public/overview/sidebar.php', ['context' => $public_context]),
        ];
        $replacements = [
            '{{feu:title}}' => esc_html((string) ($report['title'] ?? '')),
            '{{feu:description}}' => wp_kses_post(apply_filters('the_content', (string) ($report['content'] ?? ''))),
            '{{feu:date}}' => esc_html((string) ($report['date_display'] ?? '')),
            '{{feu:time}}' => esc_html((string) ($report['time'] ?? '')),
        ];
        foreach ($partials as $macro => $markup) {
            $replacements['{{feu:' . $macro . '}}'] = $markup;
        }

        return strtr($html, $replacements);
    }
}
