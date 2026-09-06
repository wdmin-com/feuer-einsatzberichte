<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_kategorien_classes = 'feu-einsatz-tab-content';

if (!isset($active_tab) || 'kategorien' !== (string) $active_tab) {
    $tab_kategorien_classes .= ' feu-einsatz-tab-content-hidden';
}
?>

<div id="tab-kategorien" class="<?php echo esc_attr($tab_kategorien_classes); ?>">
    <h2><?php _e('Einsatzstichworte', 'feuer-einsatzberichte'); ?></h2>
    <p><?php _e('Wählen Sie die Einsatzstichworte aus, die für Einsatzberichte verwendet werden sollen:', 'feuer-einsatzberichte'); ?></p>

    <div class="feu-einsatz-categories-list">
        <?php if (empty($all_categories)): ?>
            <p><?php _e('Keine Einsatzstichworte vorhanden. Erstellen Sie zuerst passende Kategorien in WordPress.', 'feuer-einsatzberichte'); ?></p>
        <?php else: ?>
            <?php foreach ($all_categories as $category): ?>
                <label>
                    <input type="checkbox"
                           name="feu_einsatz_categories[]"
                           value="<?php echo esc_attr((int) $category->term_id); ?>"
                           <?php checked(in_array($category->term_id, $selected_categories)); ?> />
                    <?php echo esc_html($category->name); ?>
                    <small>(<?php echo esc_html((int) $category->count); ?> <?php _e('Beiträge', 'feuer-einsatzberichte'); ?>)</small>
                </label>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <p>
        <button type="button" class="button feu-einsatz-select-all"><?php _e('Alle auswählen', 'feuer-einsatzberichte'); ?></button>
        <button type="button" class="button feu-einsatz-deselect-all"><?php _e('Alle abwählen', 'feuer-einsatzberichte'); ?></button>
    </p>
</div>
