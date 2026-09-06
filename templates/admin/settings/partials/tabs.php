<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_groups = [
    [
        'title' => __('Allgemein & Struktur', 'feuer-einsatzberichte'),
        'items' => [
            'allgemein' => ['label' => __('Allgemein', 'feuer-einsatzberichte'), 'icon' => 'ti ti-adjustments-horizontal'],
            'kategorien' => ['label' => __('Einsatzstichworte', 'feuer-einsatzberichte'), 'icon' => 'ti ti-tags'],
            'organisationen' => ['label' => __('Kraefte vor Ort', 'feuer-einsatzberichte'), 'icon' => 'ti ti-building'],
            'funktionen' => ['label' => __('Funktionen', 'feuer-einsatzberichte'), 'icon' => 'ti ti-badge'],
        ],
    ],
    [
        'title' => __('Karten & Medien', 'feuer-einsatzberichte'),
        'items' => [
            'karten' => ['label' => __('Karteneinstellungen', 'feuer-einsatzberichte'), 'icon' => 'ti ti-map-2'],
            'strassenregister' => ['label' => __('Strassenregister', 'feuer-einsatzberichte'), 'icon' => 'ti ti-road'],
            'medien' => ['label' => __('Medien', 'feuer-einsatzberichte'), 'icon' => 'ti ti-photo'],
            'sozial' => ['label' => __('Soziale Netzwerke', 'feuer-einsatzberichte'), 'icon' => 'ti ti-share-3'],
        ],
    ],
    [
        'title' => __('Zugriff & Betrieb', 'feuer-einsatzberichte'),
        'items' => [
            'zugriff' => ['label' => __('Zugriff', 'feuer-einsatzberichte'), 'icon' => 'ti ti-lock'],
            'manifest' => ['label' => __('Lokaler Betrieb', 'feuer-einsatzberichte'), 'icon' => 'ti ti-package'],
        ],
    ],
    [
        'title' => __('Integration', 'feuer-einsatzberichte'),
        'items' => [
            'shortcodes' => ['label' => __('Shortcodes', 'feuer-einsatzberichte'), 'icon' => 'ti ti-code'],
        ],
    ],
];
?>

<nav class="feu-admin-settings-nav" aria-label="<?php esc_attr_e('Navigation der Plugin-Einstellungen', 'feuer-einsatzberichte'); ?>">
    <?php foreach ($tab_groups as $group) : ?>
        <div class="feu-admin-settings-nav-group">
            <span class="feu-admin-settings-nav-title"><?php echo esc_html($group['title']); ?></span>
            <div class="feu-admin-settings-nav-links">
                <?php foreach ($group['items'] as $tab_key => $tab_data) : ?>
                    <a href="#<?php echo esc_attr($tab_key); ?>" class="nav-tab feu-admin-settings-link <?php echo $tab_key === $active_tab ? 'nav-tab-active' : ''; ?>" data-tab="<?php echo esc_attr($tab_key); ?>">
                        <span class="<?php echo esc_attr($tab_data['icon']); ?>" aria-hidden="true"></span>
                        <span><?php echo esc_html($tab_data['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</nav>
