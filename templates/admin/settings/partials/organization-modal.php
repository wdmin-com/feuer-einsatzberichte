<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="feu-einsatz-edit-organization-modal">
    <div class="feu-einsatz-modal-content">
        <div class="feu-einsatz-modal-header">
            <div>
                <h3><?php _e('Organisation bearbeiten', 'feuer-einsatzberichte'); ?></h3>
                <p class="description"><?php _e('Name, Farbe und optionalen Link der Organisation direkt im kompakten Formular anpassen.', 'feuer-einsatzberichte'); ?></p>
            </div>
        </div>

        <form id="feu-einsatz-edit-organization-form">
            <input type="hidden" id="edit_org_id" value="" />

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <label class="feu-admin-settings-field">
                    <span><?php _e('Name', 'feuer-einsatzberichte'); ?></span>
                    <input type="text" id="edit_org_name" class="regular-text" required />
                </label>

                <label class="feu-admin-settings-field">
                    <span><?php _e('Farbe', 'feuer-einsatzberichte'); ?></span>
                    <input type="color" id="edit_org_color" class="feu-einsatz-organization-color-field" value="#0a4b78" />
                </label>
            </div>

            <label class="feu-admin-settings-field">
                <span><?php _e('Link zum Beschreibungspost', 'feuer-einsatzberichte'); ?></span>
                <input type="url" id="edit_org_post_link" class="regular-text" placeholder="<?php _e('https://example.de/post-oder-seite', 'feuer-einsatzberichte'); ?>" />
            </label>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php _e('Aenderungen speichern', 'feuer-einsatzberichte'); ?>
                </button>
                <button type="button" class="button feu-einsatz-modal-close">
                    <?php _e('Abbrechen', 'feuer-einsatzberichte'); ?>
                </button>
            </p>
        </form>
    </div>
</div>
