<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_media_classes = 'feu-einsatz-tab-content';

if (isset($active_tab) && 'medien' !== (string) $active_tab) {
    $tab_media_classes .= ' feu-einsatz-tab-content-hidden';
}
?>

<div id="tab-medien" class="<?php echo esc_attr($tab_media_classes); ?>">
    <div class="feu-admin-settings-stack">
        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Medien und Wasserzeichen', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Wasserzeichen fuer Galerie-Fotos, Text und Logo zentral pflegen.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-admin-settings-check-grid">
                <label class="feu-admin-settings-check-card">
                    <input type="checkbox"
                           id="feu_einsatz_photo_watermark_enabled"
                           name="feu_einsatz_photo_watermark_enabled"
                           value="1"
                           <?php checked($photo_watermark_enabled, 1); ?> />
                    <span class="feu-admin-settings-check-copy">
                        <strong><?php esc_html_e('Wasserzeichen auf Fotos aktivieren', 'feuer-einsatzberichte'); ?></strong>
                        <small><?php esc_html_e('Galerie-Fotos in Einsatzberichten werden mit Wasserzeichen ausgeliefert.', 'feuer-einsatzberichte'); ?></small>
                    </span>
                </label>
            </div>
        </section>

        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <label class="feu-admin-settings-field">
                    <span><?php esc_html_e('Wasserzeichen-Text', 'feuer-einsatzberichte'); ?></span>
                    <input type="text"
                           id="feu_einsatz_photo_watermark_text"
                           name="feu_einsatz_photo_watermark_text"
                           value="<?php echo esc_attr($photo_watermark_text); ?>"
                           class="regular-text"
                           maxlength="80" />
                    <small><?php esc_html_e('Dieser Text wird auf den Galerie-Fotos im Einsatzbericht eingeblendet.', 'feuer-einsatzberichte'); ?></small>
                </label>

                <div class="feu-admin-settings-field">
                    <span><?php esc_html_e('Wasserzeichen-Bild', 'feuer-einsatzberichte'); ?></span>
                    <input type="hidden"
                           id="feu_einsatz_photo_watermark_image_id"
                           name="feu_einsatz_photo_watermark_image_id"
                           value="<?php echo esc_attr($photo_watermark_image_id); ?>" />

                    <div class="feu-admin-settings-media-stack">
                        <div id="feu-einsatz-watermark-image-preview" class="feu-einsatz-watermark-image-preview">
                            <?php if ($photo_watermark_image_url) : ?>
                                <img src="<?php echo esc_url($photo_watermark_image_url); ?>"
                                     alt=""
                                     class="feu-einsatz-watermark-preview-image" />
                            <?php else : ?>
                                <div class="feu-einsatz-watermark-placeholder"><?php esc_html_e('Noch kein Wasserzeichen-Bild ausgewaehlt.', 'feuer-einsatzberichte'); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="feu-admin-settings-action-stack">
                            <button type="button" class="button" id="feu-einsatz-watermark-image-select">
                                <?php esc_html_e('Wasserzeichen-Bild waehlen', 'feuer-einsatzberichte'); ?>
                            </button>
                            <button type="button"
                                    class="button"
                                    id="feu-einsatz-watermark-image-remove"
                                    <?php echo $photo_watermark_image_url ? '' : 'hidden'; ?>>
                                <?php esc_html_e('Bild entfernen', 'feuer-einsatzberichte'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-range-grid">
                <label class="feu-admin-settings-range-card">
                    <span><?php esc_html_e('Deckkraft des Bild-Wasserzeichens', 'feuer-einsatzberichte'); ?></span>
                    <input type="number"
                           id="feu_einsatz_photo_watermark_opacity"
                           name="feu_einsatz_photo_watermark_opacity"
                           value="<?php echo esc_attr($photo_watermark_opacity); ?>"
                           min="5"
                           max="100"
                           step="1"
                           class="small-text" />
                    <strong><?php esc_html_e('Prozent', 'feuer-einsatzberichte'); ?></strong>
                    <small><?php esc_html_e('Je hoeher der Wert, desto sichtbarer liegt das Wasserzeichen ueber dem Foto.', 'feuer-einsatzberichte'); ?></small>
                </label>

                <label class="feu-admin-settings-range-card">
                    <span><?php esc_html_e('Groesse des Bild-Wasserzeichens', 'feuer-einsatzberichte'); ?></span>
                    <input type="number"
                           id="feu_einsatz_photo_watermark_scale"
                           name="feu_einsatz_photo_watermark_scale"
                           value="<?php echo esc_attr($photo_watermark_scale); ?>"
                           min="10"
                           max="90"
                           step="1"
                           class="small-text" />
                    <strong><?php esc_html_e('Prozent', 'feuer-einsatzberichte'); ?></strong>
                    <small><?php esc_html_e('Beschreibt, wie viel der Bildbreite bzw. Bildhoehe das Wasserzeichen maximal einnehmen darf.', 'feuer-einsatzberichte'); ?></small>
                </label>
            </div>
        </section>
    </div>
</div>
