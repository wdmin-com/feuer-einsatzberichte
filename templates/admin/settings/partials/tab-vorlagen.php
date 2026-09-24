<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_classes = 'feu-einsatz-tab-content';
if (isset($active_tab) && 'vorlagen' !== (string) $active_tab) {
    $tab_classes .= ' feu-einsatz-tab-content-hidden';
}
$template_types = [
    'overview' => __('Einsatzübersicht', 'feuer-einsatzberichte'),
    'sidebar' => __('Sidebar der Übersicht', 'feuer-einsatzberichte'),
    'single' => __('Einzelner Einsatzbericht', 'feuer-einsatzberichte'),
];
?>
<div id="tab-vorlagen" class="<?php echo esc_attr($tab_classes); ?>">
    <div class="feu-admin-settings-stack">
        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Öffentliche Vorlagen', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Wählen Sie für jeden Bereich eine geprüfte Standardvorlage oder eine eigene Datei aus dem aktiven Child-Theme.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>
            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <?php foreach ($template_types as $type => $label) : ?>
                    <?php $templates = FEU_Einsatz_Template_Manager::get_templates($type); ?>
                    <label class="feu-admin-settings-field">
                        <span><?php echo esc_html($label); ?></span>
                        <select name="<?php echo esc_attr(FEU_Einsatz_Template_Manager::get_option_key($type)); ?>" class="regular-text">
                            <?php foreach ($templates as $template_key => $template) : ?>
                                <option value="<?php echo esc_attr($template_key); ?>" <?php selected($selected_templates[$type] ?? 'default', $template_key); ?>>
                                    <?php echo esc_html($template['label']); ?><?php echo 'default' !== $template_key ? ' · ' . esc_html($template['origin']) : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head"><div>
                <h2><?php esc_html_e('Eigene Datei anlegen', 'feuer-einsatzberichte'); ?></h2>
                <p class="description"><?php esc_html_e('PHP- und HTML-Dateien werden ausschließlich aus dem Child-Theme gelesen. Uploads oder Bearbeitung im Browser sind absichtlich nicht möglich: Vorlagen bleiben damit revisionssicher und können keinen fremden Code einschleusen.', 'feuer-einsatzberichte'); ?></p>
            </div></div>
            <code><?php echo esc_html(trailingslashit(get_stylesheet_directory()) . 'feuer-einsatzberichte/templates/custom_name.php'); ?></code>
            <p class="description"><?php esc_html_e('Geben Sie im Dateikopf „Template Type: single“, „overview“ oder „sidebar“ an. Alternativ werden Namen wie custom_single_name.php, custom_overview_name.php und custom_sidebar_name.php erkannt. Die mitgelieferten Beispiele zeigen die verfügbaren HTML-Makros. Nach dem Speichern oder Theme-Wechsel wird die Liste automatisch neu eingelesen.', 'feuer-einsatzberichte'); ?></p>
            <p><strong><?php esc_html_e('HTML-Makros:', 'feuer-einsatzberichte'); ?></strong> <code>{{feu:header}}</code>, <code>{{feu:footer}}</code>, <code>{{feu:map}}</code>, <code>{{feu:content}}</code>, <code>{{feu:gallery}}</code>, <code>{{feu:info}}</code>, <code>{{feu:related}}</code>, <code>{{feu:list}}</code>, <code>{{feu:sidebar}}</code>, <code>{{feu:title}}</code>, <code>{{feu:description}}</code>.</p>
        </section>
    </div>
</div>
