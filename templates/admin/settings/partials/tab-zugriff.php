<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_zugriff_classes = 'feu-einsatz-tab-content';

if (!isset($active_tab) || 'zugriff' !== (string) $active_tab) {
    $tab_zugriff_classes .= ' feu-einsatz-tab-content-hidden';
}

$plugin_access_roles = isset($plugin_access_roles) && is_array($plugin_access_roles) ? $plugin_access_roles : [];
$plugin_access_sections = isset($plugin_access_sections) && is_array($plugin_access_sections) ? $plugin_access_sections : [];
$plugin_role_access_settings = isset($plugin_role_access_settings) && is_array($plugin_role_access_settings) ? $plugin_role_access_settings : [];
$plugin_full_access_roles = isset($plugin_full_access_roles) && is_array($plugin_full_access_roles) ? $plugin_full_access_roles : [];
?>

<div id="tab-zugriff" class="<?php echo esc_attr($tab_zugriff_classes); ?>">
    <div class="feu-admin-card-header feu-admin-card-header--compact">
        <div>
            <h2><?php _e('Zugriff auf Plugin-Bereiche', 'feuer-einsatzberichte'); ?></h2>
            <p class="description">
                <?php _e('Hier wird nur gesteuert, welche Bereiche des Plugins im Backend sichtbar und aufrufbar sind. WordPress-Grundrechte wie Beitragsbearbeitung werden dadurch nicht erweitert.', 'feuer-einsatzberichte'); ?>
            </p>
            <p class="description">
                <?php _e('Administratoren haben immer Vollzugriff. Weitere Rollen koennen pro Bereich gezielt freigegeben oder entfernt werden.', 'feuer-einsatzberichte'); ?>
            </p>
        </div>
    </div>

    <div class="feu-admin-access-grid">
        <?php foreach ($plugin_access_sections as $section_key => $section_definition) : ?>
            <?php
            $allowed_roles = isset($plugin_role_access_settings[$section_key]) && is_array($plugin_role_access_settings[$section_key])
                ? $plugin_role_access_settings[$section_key]
                : [];
            $is_admin_only_section = in_array($section_key, FEU_Einsatz_Admin::get_plugin_admin_only_sections(), true);
            ?>
            <section class="feu-admin-access-card">
                <input type="hidden" name="feu_einsatz_role_access_present[<?php echo esc_attr($section_key); ?>]" value="1" />

                <div class="feu-admin-access-card-head">
                    <div>
                        <h3><?php echo esc_html((string) ($section_definition['label'] ?? $section_key)); ?></h3>
                        <p class="description"><?php echo esc_html((string) ($section_definition['description'] ?? '')); ?></p>
                        <?php if ($is_admin_only_section) : ?>
                            <p class="description"><strong><?php _e('Nur Administratoren koennen diesen Bereich aufrufen.', 'feuer-einsatzberichte'); ?></strong></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="feu-admin-access-role-grid">
                    <?php foreach ($plugin_access_roles as $role_key => $role_label) : ?>
                        <?php $is_fixed_role = in_array($role_key, $plugin_full_access_roles, true); ?>
                        <label class="feu-admin-access-role-card<?php echo $is_fixed_role ? ' is-fixed' : ''; ?>">
                            <input
                                type="checkbox"
                                name="<?php echo esc_attr(FEU_Einsatz_Admin::ROLE_ACCESS_OPTION); ?>[<?php echo esc_attr($section_key); ?>][]"
                                value="<?php echo esc_attr($role_key); ?>"
                                <?php checked($is_fixed_role || (!$is_admin_only_section && in_array($role_key, $allowed_roles, true))); ?>
                                <?php disabled($is_fixed_role || $is_admin_only_section); ?>
                            />
                            <span class="feu-admin-access-role-copy">
                                <strong><?php echo esc_html($role_label); ?></strong>
                                <?php if ($is_fixed_role) : ?>
                                    <em><?php _e('Immer aktiv', 'feuer-einsatzberichte'); ?></em>
                                <?php else : ?>
                                    <em><?php echo esc_html($role_key); ?></em>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>
