<?php
if (!defined('ABSPATH')) {
    exit;
}

$categories = isset($categories) && is_array($categories) ? $categories : [];
$form_values = isset($form_values) && is_array($form_values) ? $form_values : [];
$flash_errors = isset($flash_errors) && is_array($flash_errors) ? $flash_errors : [];
$success_post_id = isset($success_post_id) ? absint($success_post_id) : 0;
$success_post_url = isset($success_post_url) ? (string) $success_post_url : '';
$success_edit_url = isset($success_edit_url) ? (string) $success_edit_url : '';
$can_publish = !empty($can_publish);
$selected_categories = array_map('absint', (array) ($form_values['categories'] ?? []));
$street_suggestions = FEU_Einsatz_Template_Helpers::get_street_suggestions();
?>

<div class="feu-se-widget">
    <?php if ($success_post_id > 0) : ?>
        <div class="feu-se-widget-notice feu-se-widget-notice--success">
            <strong><?php esc_html_e('Einsatz gespeichert.', 'feuer-einsatzberichte'); ?></strong>
            <div class="feu-se-widget-links">
                <?php if ('' !== $success_edit_url) : ?>
                    <a href="<?php echo esc_url($success_edit_url); ?>"><?php esc_html_e('Bearbeiten', 'feuer-einsatzberichte'); ?></a>
                <?php endif; ?>
                <?php if ('' !== $success_post_url) : ?>
                    <a href="<?php echo esc_url($success_post_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ansehen', 'feuer-einsatzberichte'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <form
        method="post"
        action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
        class="feu-se-form feu-se-form--widget"
        data-feu-schnelleingabe-form
        novalidate
    >
        <?php wp_nonce_field('feu_einsatz_schnelleingabe', 'feu_einsatz_schnelleingabe_nonce'); ?>
        <input type="hidden" name="action" value="feu_einsatz_schnelleingabe">
        <input type="hidden" name="feu_einsatz_origin" value="dashboard">

        <div class="feu-se-errors" <?php echo empty($flash_errors) ? 'hidden' : ''; ?> data-feu-errors>
            <ul class="feu-se-errors-list" data-feu-errors-list>
                <?php foreach ($flash_errors as $flash_error) : ?>
                    <li><?php echo esc_html($flash_error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="feu-se-widget-grid">
            <div class="feu-se-field">
                <label class="feu-se-label" for="feu-widget-date"><?php esc_html_e('Datum', 'feuer-einsatzberichte'); ?> <span class="feu-se-required">*</span></label>
                <input type="text" id="feu-widget-date" name="feu_einsatz_datum" class="feu-se-input" value="<?php echo esc_attr($form_values['date'] ?? ''); ?>" placeholder="TT.MM.JJJJ" inputmode="numeric" required>
            </div>
            <div class="feu-se-field">
                <label class="feu-se-label" for="feu-widget-time"><?php esc_html_e('Uhrzeit', 'feuer-einsatzberichte'); ?> <span class="feu-se-required">*</span></label>
                <input type="time" id="feu-widget-time" name="feu_einsatz_uhrzeit" class="feu-se-input" value="<?php echo esc_attr($form_values['time'] ?? ''); ?>" required>
            </div>
        </div>

        <div class="feu-se-field">
            <label class="feu-se-label"><?php esc_html_e('Einsatzart', 'feuer-einsatzberichte'); ?> <span class="feu-se-required">*</span></label>
            <div class="feu-se-chip-group feu-se-chip-group--compact">
                <?php foreach ($categories as $category) : ?>
                    <label class="feu-se-chip">
                        <input
                            type="checkbox"
                            class="feu-se-chip-input"
                            name="post_category[]"
                            value="<?php echo esc_attr($category->term_id); ?>"
                            <?php checked(in_array((int) $category->term_id, $selected_categories, true)); ?>
                        >
                        <span class="feu-se-chip-label"><?php echo esc_html($category->name); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="feu-se-field">
            <label class="feu-se-label" for="feu-widget-street"><?php esc_html_e('Strasse', 'feuer-einsatzberichte'); ?> <span class="feu-se-required">*</span></label>
            <input type="text" id="feu-widget-street" name="feu_einsatz_strasse" class="feu-se-input" value="<?php echo esc_attr($form_values['street'] ?? ''); ?>" placeholder="<?php esc_attr_e('Musterstrasse', 'feuer-einsatzberichte'); ?>" list="feu-einsatz-street-suggestions" autocomplete="street-address" required>
            <?php if (!empty($street_suggestions)) : ?>
                <datalist id="feu-einsatz-street-suggestions">
                    <?php foreach ($street_suggestions as $street_suggestion) : ?>
                        <option value="<?php echo esc_attr($street_suggestion); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            <?php endif; ?>
        </div>

        <div class="feu-se-widget-grid feu-se-widget-grid--three">
            <div class="feu-se-field">
                <label class="feu-se-label" for="feu-widget-house-number"><?php esc_html_e('Hausnr.', 'feuer-einsatzberichte'); ?></label>
                <input type="text" id="feu-widget-house-number" name="feu_einsatz_hausnummer" class="feu-se-input" value="<?php echo esc_attr($form_values['house_number'] ?? ''); ?>" placeholder="12a">
            </div>
            <div class="feu-se-field">
                <label class="feu-se-label" for="feu-widget-postal-code"><?php esc_html_e('PLZ', 'feuer-einsatzberichte'); ?> <span class="feu-se-required">*</span></label>
                <input type="text" id="feu-widget-postal-code" name="feu_einsatz_plz" class="feu-se-input" value="<?php echo esc_attr($form_values['postal_code'] ?? ''); ?>" maxlength="5" inputmode="numeric" required>
            </div>
            <div class="feu-se-field">
                <label class="feu-se-label" for="feu-widget-city"><?php esc_html_e('Stadt', 'feuer-einsatzberichte'); ?> <span class="feu-se-required">*</span></label>
                <input type="text" id="feu-widget-city" name="feu_einsatz_stadt" class="feu-se-input" value="<?php echo esc_attr($form_values['city'] ?? ''); ?>" required>
            </div>
        </div>

        <div class="feu-se-field">
            <label class="feu-se-label" for="feu-widget-title"><?php esc_html_e('Titel', 'feuer-einsatzberichte'); ?></label>
            <input type="text" id="feu-widget-title" name="post_title" class="feu-se-input" value="<?php echo esc_attr($form_values['title'] ?? ''); ?>" placeholder="<?php esc_attr_e('Optionaler Titel', 'feuer-einsatzberichte'); ?>">
        </div>

        <div class="feu-se-widget-actions">
            <button
                type="submit"
                name="feu_einsatz_report_status"
                value="<?php echo $can_publish ? 'publish' : 'draft'; ?>"
                class="button button-primary"
            >
                <span data-feu-submit-text><?php echo esc_html($can_publish ? __('Veroeffentlichen', 'feuer-einsatzberichte') : __('Speichern', 'feuer-einsatzberichte')); ?></span>
                <span class="feu-se-btn-spinner feu-se-btn-spinner--inline" data-feu-submit-spinner hidden></span>
            </button>
            <?php if ($can_publish) : ?>
                <button type="submit" name="feu_einsatz_report_status" value="draft" class="button">
                    <?php esc_html_e('Entwurf', 'feuer-einsatzberichte'); ?>
                </button>
            <?php endif; ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=feu-einsatz-schnelleingabe')); ?>" class="button button-link">
                <?php esc_html_e('Vollstaendige Schnelleingabe oeffnen', 'feuer-einsatzberichte'); ?>
            </a>
        </div>
    </form>
</div>

