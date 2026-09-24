<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_groups = [
    [
        'title' => __('Allgemein & Struktur', 'feuer-einsatzberichte'),
        'items' => [
            'allgemein' => ['label' => __('Allgemein', 'feuer-einsatzberichte'), 'icon' => 'ti ti-adjustments-horizontal'],
            'module' => ['label' => __('Module', 'feuer-einsatzberichte'), 'icon' => 'ti ti-layout-grid'],
            'kategorien' => ['label' => __('Einsatzstichworte', 'feuer-einsatzberichte'), 'icon' => 'ti ti-tags'],
            'organisationen' => ['label' => __('Kräfte vor Ort', 'feuer-einsatzberichte'), 'icon' => 'ti ti-building'],
            'funktionen' => ['label' => __('Funktionen', 'feuer-einsatzberichte'), 'icon' => 'ti ti-badge'],
        ],
    ],
    [
        'title' => __('Karten & Medien', 'feuer-einsatzberichte'),
        'items' => [
            'karten' => ['label' => __('Karteneinstellungen', 'feuer-einsatzberichte'), 'icon' => 'ti ti-map-2'],
            'medien' => ['label' => __('Medien', 'feuer-einsatzberichte'), 'icon' => 'ti ti-photo'],
            'sozial' => ['label' => __('Soziale Netzwerke', 'feuer-einsatzberichte'), 'icon' => 'ti ti-share-3'],
        ],
    ],
    [
        'title' => __('Zugriff & Betrieb', 'feuer-einsatzberichte'),
        'items' => [
            'zugriff' => ['label' => __('Zugriff', 'feuer-einsatzberichte'), 'icon' => 'ti ti-lock'],
        ],
    ],
    [
        'title' => __('Erweiterte Einstellungen', 'feuer-einsatzberichte'),
        'class' => 'feu-admin-settings-nav-group--advanced',
        'items' => [
            'strassenregister' => ['label' => __('Straßenregister', 'feuer-einsatzberichte'), 'icon' => 'ti ti-road'],
            'manifest' => ['label' => __('Lokaler Betrieb', 'feuer-einsatzberichte'), 'icon' => 'ti ti-package'],
            'shortcodes' => ['label' => __('Shortcodes', 'feuer-einsatzberichte'), 'icon' => 'ti ti-code'],
            'vorlagen' => ['label' => __('Vorlagen', 'feuer-einsatzberichte'), 'icon' => 'ti ti-layout-template'],
            'daten' => ['label' => __('Daten löschen', 'feuer-einsatzberichte'), 'icon' => 'ti ti-shield-lock'],
        ],
    ],
];
?>

<nav class="feu-admin-settings-nav" aria-label="<?php esc_attr_e('Navigation der Plugin-Einstellungen', 'feuer-einsatzberichte'); ?>">
    <?php foreach ($tab_groups as $group) : ?>
        <?php
        $visible_items = array_filter($group['items'], static function ($tab_data, $tab_key) {
            return FEU_Einsatz_Admin::is_settings_tab_visible($tab_key);
        }, ARRAY_FILTER_USE_BOTH);

        if (empty($visible_items)) {
            continue;
        }
        ?>
        <div class="feu-admin-settings-nav-group <?php echo esc_attr($group['class'] ?? ''); ?>">
            <span class="feu-admin-settings-nav-title"><?php echo esc_html($group['title']); ?></span>
            <div class="feu-admin-settings-nav-links">
                <?php foreach ($visible_items as $tab_key => $tab_data) : ?>
                    <a href="#<?php echo esc_attr($tab_key); ?>" class="nav-tab feu-admin-settings-link <?php echo $tab_key === $active_tab ? 'nav-tab-active' : ''; ?>" data-tab="<?php echo esc_attr($tab_key); ?>">
                        <span class="<?php echo esc_attr($tab_data['icon']); ?>" aria-hidden="true"></span>
                        <span><?php echo esc_html($tab_data['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</nav>
