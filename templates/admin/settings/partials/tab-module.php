<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_module_classes = 'feu-einsatz-tab-content';
if (!isset($active_tab) || 'module' !== (string) $active_tab) {
    $tab_module_classes .= ' feu-einsatz-tab-content-hidden';
}

$settings_section_definitions = isset($settings_section_definitions) && is_array($settings_section_definitions)
    ? $settings_section_definitions
    : FEU_Einsatz_Admin::get_settings_section_definitions();
$settings_section_visibility = isset($settings_section_visibility) && is_array($settings_section_visibility)
    ? $settings_section_visibility
    : FEU_Einsatz_Admin::get_settings_section_visibility();
?>

<div id="tab-module" class="<?php echo esc_attr($tab_module_classes); ?>">
    <div class="feu-admin-settings-stack">
        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Module & Einstellungen', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Blenden Sie nur die Bereiche ein, die Ihr Team tatsächlich verwendet. Das hält die Einstellungen übersichtlich und lässt sich jederzeit wieder ändern.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="notice notice-info inline feu-admin-settings-module-note">
                <p><?php esc_html_e('Diese Schalter steuern ausschließlich die Navigation der Einstellungen. Einsatzberichte, vorhandene Daten, Shortcodes und bereits aktivierte Funktionen bleiben unverändert.', 'feuer-einsatzberichte'); ?></p>
            </div>

            <input type="hidden" name="feu_einsatz_settings_section_visibility_present" value="1" />

            <div class="feu-admin-settings-check-grid feu-admin-settings-module-grid">
                <?php foreach ($settings_section_definitions as $section_key => $definition) : ?>
                    <?php
                    $section_key = sanitize_key((string) $section_key);
                    if ('' === $section_key) {
                        continue;
                    }
                    $is_visible = !empty($settings_section_visibility[$section_key]);
                    $label = (string) ($definition['label'] ?? $section_key);
                    $description = (string) ($definition['description'] ?? '');
                    $icon_classes = preg_split('/\s+/', (string) ($definition['icon'] ?? 'ti ti-settings'));
                    $icon_classes = array_filter(array_map('sanitize_html_class', (array) $icon_classes));
                    $icon = implode(' ', $icon_classes);
                    $card_classes = 'feu-admin-settings-check-card feu-admin-settings-module-card' . ($is_visible ? '' : ' is-disabled');
                    ?>
                    <label class="<?php echo esc_attr($card_classes); ?>">
                        <input type="checkbox"
                               name="feu_einsatz_settings_section_visibility[<?php echo esc_attr($section_key); ?>]"
                               value="1"
                               <?php checked($is_visible); ?> />
                        <span class="feu-admin-settings-check-copy">
                            <strong><span class="<?php echo esc_attr($icon); ?>" aria-hidden="true"></span> <?php echo esc_html($label); ?></strong>
                            <small><?php echo esc_html($description); ?></small>
                        </span>
                        <span class="feu-admin-settings-module-state" aria-hidden="true">
                            <?php echo esc_html($is_visible ? __('Sichtbar', 'feuer-einsatzberichte') : __('Ausgeblendet', 'feuer-einsatzberichte')); ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>
