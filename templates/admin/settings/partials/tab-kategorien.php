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
    <input type="hidden" name="feu_einsatz_categories_present" value="1" />
    <h2><?php _e('Einsatzstichworte', 'feuer-einsatzberichte'); ?></h2>
    <p><?php _e('Wählen Sie die Einsatzstichworte aus, die für Einsatzberichte verwendet werden sollen:', 'feuer-einsatzberichte'); ?></p>
    <?php if (!empty($keyword_migration_notice['error'])) : ?>
        <div class="notice notice-error inline"><p><?php echo esc_html($keyword_migration_notice['error']); ?></p></div>
    <?php elseif (!empty($keyword_migration_notice['success'])) : ?>
        <div class="notice notice-success inline"><p><?php echo esc_html($keyword_migration_notice['success']); ?></p></div>
    <?php endif; ?>
    <?php if (FEU_Einsatz_Report_Taxonomy::enabled()) : ?>
        <p class="description"><?php esc_html_e('Diese Einsatzstichworte werden getrennt von den Blog-Kategorien verwaltet. Alte Kategorie-Beziehungen bleiben vorläufig für bestehende Links und Vorlagen erhalten.', 'feuer-einsatzberichte'); ?></p>
        <p><a class="button" href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=' . FEU_Einsatz_Report_Taxonomy::TAXONOMY . '&post_type=einsatzbericht')); ?>"><?php esc_html_e('Einsatzstichworte verwalten', 'feuer-einsatzberichte'); ?></a></p>
    <?php else : ?>
        <div class="notice notice-info inline">
            <p><strong><?php esc_html_e('Eigene Einsatzstichwort-Struktur', 'feuer-einsatzberichte'); ?></strong></p>
            <p><?php echo esc_html(sprintf(__('Vor der Umstellung werden ein Archiv erstellt und %d Berichte geprüft. Alte Kategorien und URLs bleiben erhalten.', 'feuer-einsatzberichte'), (int) ($keyword_migration_preflight['report_count'] ?? 0))); ?></p>
            <?php if (!empty($keyword_migration_preflight['errors'])) : ?>
                <p><?php echo esc_html(implode(' ', (array) $keyword_migration_preflight['errors'])); ?></p>
            <?php elseif (current_user_can('manage_options')) : ?>
                <button type="submit" form="feu-einsatz-keyword-migrate-form" class="button button-primary" onclick="return window.confirm('<?php echo esc_js(__('Vor der Umstellung wird ein Archiv erstellt. Einsatzstichworte jetzt übertragen?', 'feuer-einsatzberichte')); ?>');"><?php esc_html_e('Sichern und Einsatzstichworte übertragen', 'feuer-einsatzberichte'); ?></button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($default_categories_prompt) && empty($default_categories_root)) : ?>
        <div class="notice notice-info inline feu-einsatz-default-categories-notice">
            <p><strong><?php esc_html_e('Standard-Einsatzstichworte einrichten?', 'feuer-einsatzberichte'); ?></strong></p>
            <p><?php esc_html_e('Die Kategoriegruppe „Einsätze“ mit den offiziellen Einsatzstichworten wurde noch nicht gefunden. Sie können sie jetzt als WordPress-Kategorien anlegen und anschließend einzeln auswählen.', 'feuer-einsatzberichte'); ?></p>
            <button type="submit" class="button button-primary" name="feu_einsatz_install_default_categories" value="1">
                <?php esc_html_e('Einsatzstichworte installieren', 'feuer-einsatzberichte'); ?>
            </button>
        </div>
    <?php endif; ?>

    <div class="feu-einsatz-categories-list">
        <?php if (empty($all_categories)): ?>
            <p><?php _e('Keine Einsatzstichworte vorhanden.', 'feuer-einsatzberichte'); ?></p>
        <?php else: ?>
            <?php foreach ($all_categories as $category): ?>
                <label>
                    <input type="checkbox"
                           name="feu_einsatz_categories[]"
                           value="<?php echo esc_attr((int) $category->term_id); ?>"
                           <?php checked(in_array($category->term_id, $selected_categories)); ?> />
                    <?php echo esc_html($category->name); ?>
                    <small>(<?php echo esc_html((int) $category->count); ?> <?php _e('Berichte', 'feuer-einsatzberichte'); ?>)</small>
                </label>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <p>
        <button type="button" class="button feu-einsatz-select-all"><?php _e('Alle auswählen', 'feuer-einsatzberichte'); ?></button>
        <button type="button" class="button feu-einsatz-deselect-all"><?php _e('Alle abwählen', 'feuer-einsatzberichte'); ?></button>
    </p>
    <?php if (FEU_Einsatz_Report_Taxonomy::enabled() && 'complete' === (string) ($keyword_migration_run['status'] ?? '') && current_user_can('manage_options')) : ?>
        <p class="description"><?php esc_html_e('Rückweg ist nur möglich, solange seit der Umstellung weder Berichte noch deren Einsatzstichworte oder die aktive Auswahl geändert wurden.', 'feuer-einsatzberichte'); ?></p>
        <button type="submit" form="feu-einsatz-keyword-rollback-form" class="button" onclick="return window.confirm('<?php echo esc_js(__('Einsatzstichwort-Umstellung wirklich zurücksetzen?', 'feuer-einsatzberichte')); ?>');"><?php esc_html_e('Umstellung zurücksetzen', 'feuer-einsatzberichte'); ?></button>
    <?php endif; ?>
</div>
