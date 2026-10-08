<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Editor shortcuts only; reports persist the resulting map geometry separately. */
class FEU_Einsatz_Map_Quick_Profiles {
    public const OPTION = 'feu_einsatz_map_quick_profiles';

    public static function defaults(): array {
        return [
            'standard' => ['label' => __('Standard aus Einstellungen', 'feuer-einsatzberichte'), 'mode' => 'default', 'meters' => 100, 'enabled' => 1, 'categories' => []],
            'full' => ['label' => __('Ganze Straße', 'feuer-einsatzberichte'), 'mode' => 'full', 'meters' => 100, 'enabled' => 1, 'categories' => []],
            'segment_100' => ['label' => __('Verkehrsunfall – Abschnitt 100 m', 'feuer-einsatzberichte'), 'mode' => 'length', 'meters' => 100, 'enabled' => 1, 'categories' => []],
            'radius_500' => ['label' => __('Wald- / Flächenlage – Radius 500 m', 'feuer-einsatzberichte'), 'mode' => 'radius', 'meters' => 500, 'enabled' => 1, 'categories' => []],
            'radius_1000' => ['label' => __('Großschaden – Radius 1.000 m', 'feuer-einsatzberichte'), 'mode' => 'radius', 'meters' => 1000, 'enabled' => 1, 'categories' => []],
        ];
    }

    public static function normalize($profiles): array {
        $profiles = is_array($profiles) ? $profiles : [];
        $normalized = [];
        foreach (self::defaults() as $key => $default) {
            $raw = isset($profiles[$key]) && is_array($profiles[$key]) ? $profiles[$key] : [];
            $label = trim(sanitize_text_field((string) ($raw['label'] ?? $default['label'])));
            $mode = sanitize_key((string) ($raw['mode'] ?? $default['mode']));
            $categories = array_values(array_unique(array_filter(array_map('absint', (array) ($raw['categories'] ?? [])))));
            $normalized[$key] = [
                'label' => '' !== $label ? wp_html_excerpt($label, 80) : $default['label'],
                'mode' => in_array($mode, ['default', 'full', 'length', 'radius'], true) ? $mode : $default['mode'],
                'meters' => max(20, min(5000, absint($raw['meters'] ?? $default['meters']) ?: $default['meters'])),
                'enabled' => array_key_exists('enabled', $raw) ? (empty($raw['enabled']) ? 0 : 1) : $default['enabled'],
                'categories' => array_slice($categories, 0, 100),
            ];
        }
        return $normalized;
    }

    public static function get(): array {
        return self::normalize(get_option(self::OPTION, []));
    }
}
