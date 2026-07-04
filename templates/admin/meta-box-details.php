<?php
if (!defined('ABSPATH')) {
    exit;
}

$show_basic_fields = !isset($feu_einsatz_show_basic_fields) ? true : (bool) $feu_einsatz_show_basic_fields;
$show_availability_panel = !isset($feu_einsatz_show_availability_panel) ? true : (bool) $feu_einsatz_show_availability_panel;
$show_media_panels = !isset($feu_einsatz_show_media_panels) ? true : (bool) $feu_einsatz_show_media_panels;
$show_kartenbild = isset($feu_einsatz_show_kartenbild) ? (bool) $feu_einsatz_show_kartenbild : $show_media_panels;
$show_fotos = isset($feu_einsatz_show_fotos) ? (bool) $feu_einsatz_show_fotos : $show_media_panels;
$comments_feature_enabled = 1 === (int) get_option('feu_einsatz_default_comments_enabled', 0);
$show_kommentare = $comments_feature_enabled && (!isset($feu_einsatz_show_kommentare) ? $show_media_panels : (bool) $feu_einsatz_show_kommentare);
$render_nonce_field = empty($feu_einsatz_skip_nonce_field);
$use_modern_form = !empty($feu_einsatz_use_modern_form);
$suppress_section_titles = !empty($feu_einsatz_suppress_section_titles);

if (!$show_basic_fields && !$show_availability_panel && !$show_kartenbild && !$show_fotos && !$show_kommentare) {
    return;
}

if ($render_nonce_field) {
    wp_nonce_field('feu_einsatz_save_post_data', 'feu_einsatz_meta_box_nonce');
}

$strasse = get_post_meta($post->ID, '_feu_einsatz_strasse', true);
$hausnummer = get_post_meta($post->ID, '_feu_einsatz_hausnummer', true);
$plz = get_post_meta($post->ID, '_feu_einsatz_plz', true);
$stadt = get_post_meta($post->ID, '_feu_einsatz_stadt', true);
$datum = get_post_meta($post->ID, '_feu_einsatz_datum', true);
$uhrzeit = get_post_meta($post->ID, '_feu_einsatz_uhrzeit', true);
$has_thumbnail = has_post_thumbnail($post->ID);
$gallery_ids = get_post_meta($post->ID, '_feu_einsatz_gallery', true);
$generated_map_preview_url = get_post_meta($post->ID, '_feu_einsatz_generated_map_preview_url', true);
$generated_map_publish_hold = get_post_meta($post->ID, '_feu_einsatz_generated_map_publish_hold', true);
$is_new_report = empty($post->ID);
$generated_map_thumbnail_id = !$is_new_report && isset($this) && method_exists($this, 'get_generated_map_thumbnail_id')
    ? (int) $this->get_generated_map_thumbnail_id($post->ID)
    : 0;
$has_generated_map_asset = !$is_new_report && isset($this) && method_exists($this, 'has_generated_map_asset')
    ? (bool) $this->has_generated_map_asset($post->ID)
    : false;
$map_action_redirect = !$is_new_report && isset($this) && method_exists($this, 'get_internal_report_edit_url')
    ? (string) $this->get_internal_report_edit_url($post->ID)
    : admin_url('admin.php?page=feu-einsatz-neuer-bericht');
$generated_map_status = !$is_new_report && isset($this) && method_exists($this, 'get_generated_map_admin_status')
    ? $this->get_generated_map_admin_status($post->ID)
    : [
        'status' => 'idle',
        'class_name' => '',
        'message' => '',
        'scheduled_for' => 0,
        'scheduled_for_display' => '',
    ];
$post_status = isset($post->post_status) ? (string) $post->post_status : 'draft';
$availability_mode = get_post_meta($post->ID, '_feu_einsatz_availability_mode', true);
$available_from = get_post_meta($post->ID, '_feu_einsatz_available_from', true);
$comments_enabled = get_post_meta($post->ID, '_feu_einsatz_comments_enabled', true);

if (!empty($datum)) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $datum)) {
        $timestamp = strtotime($datum);
        if (false !== $timestamp) {
            $datum = date_i18n('d.m.Y', $timestamp);
        }
    } elseif (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', (string) $datum)) {
        $timestamp = strtotime($datum);
        if (false !== $timestamp) {
            $datum = date_i18n('d.m.Y', $timestamp);
        }
    }
}

if (!is_array($gallery_ids)) {
    $gallery_ids = [];
}

if (empty($stadt)) {
    $stadt = 'Hamburg';
}

if (empty($availability_mode)) {
    $availability_mode = 'date';
}

if ('' === $comments_enabled) {
    $comments_enabled = (int) get_option('feu_einsatz_default_comments_enabled', 0) ? '1' : '0';
}

if ('plus3' === $availability_mode) {
    $availability_mode = 'plus2';
}

if (!in_array($availability_mode, ['sofort', 'date', 'plus2'], true)) {
    $availability_mode = 'date';
}

$available_date = !empty($datum) ? $datum : '';
$available_time = !empty($uhrzeit) ? $uhrzeit : '08:00';
$available_timestamp = false;

if (!empty($available_from)) {
    $available_timestamp = strtotime($available_from);
} elseif (!empty($post->post_date) && '0000-00-00 00:00:00' !== $post->post_date) {
    $available_timestamp = strtotime($post->post_date);
}

if (false !== $available_timestamp) {
    $available_date = date_i18n('d.m.Y', $available_timestamp);
    $available_time = date_i18n('H:i', $available_timestamp);
}

if ('' === $available_date) {
    $available_date = date_i18n('d.m.Y', current_time('timestamp'));
}

$map_generation_flash_type = isset($_GET['feu_map_generation']) ? sanitize_key(wp_unslash($_GET['feu_map_generation'])) : '';
$map_generation_flash_message = isset($_GET['feu_map_message']) ? sanitize_text_field(rawurldecode(wp_unslash($_GET['feu_map_message']))) : '';
?>

<div class="feu-einsatz-meta-box<?php echo $use_modern_form ? ' feu-einsatz-meta-box--modern' : ''; ?>">
    <?php if ($show_basic_fields) : ?>
        <div class="feu-einsatz-form-row feu-einsatz-form-grid feu-einsatz-form-grid--address">
            <div class="feu-einsatz-field">
                <label class="feu-einsatz-field-label" for="feu_einsatz_strasse">
                    <?php esc_html_e('Strasse', 'feuer-einsatzberichte'); ?>
                </label>
                <input
                    type="text"
                    id="feu_einsatz_strasse"
                    name="feu_einsatz_strasse"
                    value="<?php echo esc_attr($strasse); ?>"
                    class="widefat"
                    list="feu-einsatz-street-suggestions"
                    autocomplete="street-address"
                    placeholder="z.B. Suedestrasse"
                    required
                />
                <p class="description feu-einsatz-field-hint">
                    <?php esc_html_e('Die Strasse ist fuer Karte, Share-Bild und den automatischen Titel relevant.', 'feuer-einsatzberichte'); ?>
                </p>
            </div>

            <div class="feu-einsatz-field feu-einsatz-field--compact">
                <label class="feu-einsatz-field-label" for="feu_einsatz_hausnummer">
                    <?php esc_html_e('Hausnummer', 'feuer-einsatzberichte'); ?>
                </label>
                <input
                    type="text"
                    id="feu_einsatz_hausnummer"
                    name="feu_einsatz_hausnummer"
                    value="<?php echo esc_attr($hausnummer); ?>"
                    class="widefat"
                    placeholder="z.B. 12a"
                />
                <p class="description feu-einsatz-field-hint">
                    <?php esc_html_e('Optional. Die Hausnummer wird intern verwendet, aber nicht oeffentlich auf Kartenbildern gezeigt.', 'feuer-einsatzberichte'); ?>
                </p>
            </div>
        </div>

        <div class="feu-einsatz-form-row feu-einsatz-form-grid feu-einsatz-form-grid--location">
            <div class="feu-einsatz-field feu-einsatz-field--compact">
                <label class="feu-einsatz-field-label" for="feu_einsatz_plz">
                    <?php esc_html_e('PLZ', 'feuer-einsatzberichte'); ?>
                </label>
                <input
                    type="text"
                    id="feu_einsatz_plz"
                    name="feu_einsatz_plz"
                    value="<?php echo esc_attr($plz); ?>"
                    class="widefat"
                    placeholder="z.B. 22547"
                    maxlength="5"
                    inputmode="numeric"
                    pattern="\d{5}"
                    required
                />
            </div>

            <div class="feu-einsatz-field">
                <label class="feu-einsatz-field-label" for="feu_einsatz_stadt">
                    <?php esc_html_e('Stadt', 'feuer-einsatzberichte'); ?>
                </label>
                <input
                    type="text"
                    id="feu_einsatz_stadt"
                    name="feu_einsatz_stadt"
                    value="<?php echo esc_attr($stadt); ?>"
                    class="widefat"
                    placeholder="Hamburg"
                    required
                />
            </div>
        </div>

        <div class="feu-einsatz-form-row feu-einsatz-form-grid feu-einsatz-form-grid--timing">
            <div class="feu-einsatz-field">
                <label class="feu-einsatz-field-label" for="feu_einsatz_datum">
                    <?php esc_html_e('Datum', 'feuer-einsatzberichte'); ?>
                </label>
                <input
                    type="text"
                    id="feu_einsatz_datum"
                    name="feu_einsatz_datum"
                    value="<?php echo esc_attr($datum); ?>"
                    class="feu-einsatz-datepicker widefat"
                    placeholder="TT.MM.JJJJ"
                    required
                />
            </div>

            <div class="feu-einsatz-field feu-einsatz-field--compact">
                <label class="feu-einsatz-field-label" for="feu_einsatz_uhrzeit">
                    <?php esc_html_e('Uhrzeit', 'feuer-einsatzberichte'); ?>
                </label>
                <input
                    type="time"
                    id="feu_einsatz_uhrzeit"
                    name="feu_einsatz_uhrzeit"
                    value="<?php echo esc_attr($uhrzeit); ?>"
                    class="widefat"
                    required
                />
            </div>
        </div>
    <?php endif; ?>

    <?php if ($show_availability_panel) : ?>
        <div class="feu-einsatz-form-row feu-einsatz-inline-card feu-einsatz-inline-card--availability">
            <div class="feu-einsatz-inline-card-head">
                <div>
                    <h4 class="feu-einsatz-inline-card-title"><?php esc_html_e('Veroeffentlichung steuern', 'feuer-einsatzberichte'); ?></h4>
                    <p class="description feu-einsatz-inline-card-copy">
                        <?php esc_html_e('Lege fest, ob der Bericht sofort, zum Einsatzdatum oder automatisch versetzt freigegeben wird.', 'feuer-einsatzberichte'); ?>
                    </p>
                </div>
            </div>

            <div class="feu-einsatz-choice-grid">
                <label class="feu-einsatz-choice-card">
                    <input type="radio" name="feu_einsatz_availability_mode" value="sofort" <?php checked($availability_mode, 'sofort'); ?> />
                    <span class="feu-einsatz-choice-card-title"><?php esc_html_e('Sofort', 'feuer-einsatzberichte'); ?></span>
                    <span class="feu-einsatz-choice-card-copy"><?php esc_html_e('Wird direkt veroeffentlicht.', 'feuer-einsatzberichte'); ?></span>
                </label>

                <label class="feu-einsatz-choice-card">
                    <input type="radio" name="feu_einsatz_availability_mode" value="date" <?php checked($availability_mode, 'date'); ?> />
                    <span class="feu-einsatz-choice-card-title"><?php esc_html_e('Zum Einsatzdatum', 'feuer-einsatzberichte'); ?></span>
                    <span class="feu-einsatz-choice-card-copy"><?php esc_html_e('Nutzen von Datum und Uhrzeit aus den Einsatzdetails.', 'feuer-einsatzberichte'); ?></span>
                </label>

                <label class="feu-einsatz-choice-card">
                    <input type="radio" name="feu_einsatz_availability_mode" value="plus2" <?php checked($availability_mode, 'plus2'); ?> />
                    <span class="feu-einsatz-choice-card-title"><?php esc_html_e('Automatisch in 48 Stunden', 'feuer-einsatzberichte'); ?></span>
                    <span class="feu-einsatz-choice-card-copy"><?php esc_html_e('Plant die Freigabe exakt zwei Tage nach Datum und Uhrzeit des Einsatzes.', 'feuer-einsatzberichte'); ?></span>
                </label>
            </div>

            <div class="feu-einsatz-availability-date-preview">
                <div class="feu-einsatz-preview-stat">
                    <span class="feu-einsatz-field-heading"><?php esc_html_e('Datum', 'feuer-einsatzberichte'); ?></span>
                    <strong id="feu-einsatz-availability-preview-date"><?php echo esc_html($available_date ?: __('Nicht gesetzt', 'feuer-einsatzberichte')); ?></strong>
                </div>
                <div class="feu-einsatz-preview-stat">
                    <span class="feu-einsatz-field-heading"><?php esc_html_e('Uhrzeit', 'feuer-einsatzberichte'); ?></span>
                    <strong id="feu-einsatz-availability-preview-time"><?php echo esc_html($available_time ?: '08:00'); ?></strong>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($show_kartenbild) : ?>
        <div class="feu-einsatz-form-row feu-einsatz-inline-card feu-einsatz-inline-card--media">
            <div class="feu-einsatz-inline-card-head">
                <div>
                    <?php if (!$suppress_section_titles) : ?>
                        <h4 class="feu-einsatz-inline-card-title"><?php esc_html_e('Kartenbild / Beitragsbild', 'feuer-einsatzberichte'); ?></h4>
                    <?php endif; ?>
                    <p class="description feu-einsatz-inline-card-copy">
                        <?php esc_html_e('Erstellt oder aktualisiert das automatisch generierte Kartenbild fuer den Bericht.', 'feuer-einsatzberichte'); ?>
                    </p>
                </div>
            </div>

            <?php if (
                !$is_new_report
                && !empty($generated_map_status['message'])
                && in_array(($generated_map_status['status'] ?? 'idle'), ['queued', 'processing', 'error'], true)
            ) : ?>
                <div class="<?php echo esc_attr($generated_map_status['class_name']); ?>">
                    <span class="dashicons dashicons-update-alt" aria-hidden="true"></span>
                    <div class="feu-einsatz-map-generation-status-copy">
                        <strong><?php esc_html_e('Hintergrund-Status', 'feuer-einsatzberichte'); ?></strong>
                        <p><?php echo esc_html($generated_map_status['message']); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$is_new_report && '' !== $map_generation_flash_message && in_array($map_generation_flash_type, ['success', 'error'], true)) : ?>
                <div class="notice inline <?php echo 'success' === $map_generation_flash_type ? 'notice-success' : 'notice-error'; ?>">
                    <p><?php echo esc_html($map_generation_flash_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!$is_new_report && is_array($generated_map_publish_hold) && !empty($generated_map_publish_hold['target_status'])) : ?>
                <div class="notice inline notice-warning">
                    <p><?php esc_html_e('Dieser Bericht bleibt bis zur erfolgreichen Kartenbild-Generierung unveroeffentlicht.', 'feuer-einsatzberichte'); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($is_new_report) : ?>
                <p class="description feu-einsatz-inline-card-copy">
                    <?php esc_html_e('Beim ersten Speichern wird das Kartenbild zeitversetzt im Hintergrund erzeugt, sobald eine Strasse vorhanden ist.', 'feuer-einsatzberichte'); ?>
                </p>
            <?php elseif ($has_thumbnail) : ?>
                <div class="feu-einsatz-media-preview">
                    <strong><?php esc_html_e('Aktuelles Beitragsbild', 'feuer-einsatzberichte'); ?></strong>
                    <div class="feu-einsatz-media-preview-frame">
                        <?php echo wp_get_attachment_image(get_post_thumbnail_id($post->ID), 'thumbnail'); ?>
                    </div>
                </div>
                <?php if ($generated_map_preview_url) : ?>
                    <p class="description feu-einsatz-inline-card-copy">
                        <?php esc_html_e('Eine Karten-Vorschau ist zusaetzlich als Fallback gespeichert.', 'feuer-einsatzberichte'); ?>
                    </p>
                <?php endif; ?>
                <p class="feu-einsatz-report-status-actions">
                    <button type="button" id="feu-einsatz-regenerate-map-image" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Kartenbild neu generieren', 'feuer-einsatzberichte'); ?>
                    </button>
                    <?php if ($has_generated_map_asset) : ?>
                        <button
                            type="button"
                            class="button button-link-delete feu-einsatz-delete-map-image-button"
                            data-post-id="<?php echo esc_attr((string) $post->ID); ?>"
                            data-redirect-to="<?php echo esc_attr($map_action_redirect); ?>"
                            data-nonce="<?php echo esc_attr(wp_create_nonce('feu_einsatz_delete_map_image_' . $post->ID)); ?>"
                            data-confirm="<?php echo esc_attr__('Soll das automatisch generierte Kartenbild wirklich geloescht werden?', 'feuer-einsatzberichte'); ?>"
                        >
                            <?php esc_html_e('Kartenbild loeschen', 'feuer-einsatzberichte'); ?>
                        </button>
                    <?php endif; ?>
                </p>
            <?php elseif ($generated_map_preview_url) : ?>
                <div class="feu-einsatz-media-preview">
                    <strong><?php esc_html_e('Karten-Vorschau', 'feuer-einsatzberichte'); ?></strong>
                    <div class="feu-einsatz-media-preview-frame">
                        <img
                            src="<?php echo esc_url($generated_map_preview_url); ?>"
                            alt=""
                            class="feu-einsatz-map-preview-thumb"
                        />
                    </div>
                </div>
                <p class="feu-einsatz-report-status-actions">
                    <button type="button" id="feu-einsatz-regenerate-map-image" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Kartenbild neu generieren', 'feuer-einsatzberichte'); ?>
                    </button>
                    <?php if ($has_generated_map_asset || $generated_map_thumbnail_id > 0) : ?>
                        <button
                            type="button"
                            class="button button-link-delete feu-einsatz-delete-map-image-button"
                            data-post-id="<?php echo esc_attr((string) $post->ID); ?>"
                            data-redirect-to="<?php echo esc_attr($map_action_redirect); ?>"
                            data-nonce="<?php echo esc_attr(wp_create_nonce('feu_einsatz_delete_map_image_' . $post->ID)); ?>"
                            data-confirm="<?php echo esc_attr__('Soll das automatisch generierte Kartenbild wirklich geloescht werden?', 'feuer-einsatzberichte'); ?>"
                        >
                            <?php esc_html_e('Kartenbild loeschen', 'feuer-einsatzberichte'); ?>
                        </button>
                    <?php endif; ?>
                </p>
            <?php else : ?>
                <button type="button" id="feu-einsatz-generate-map-image" class="button button-primary">
                    <span class="dashicons dashicons-format-image"></span>
                    <?php esc_html_e('Kartenbild generieren', 'feuer-einsatzberichte'); ?>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($show_fotos) : ?>
        <div class="feu-einsatz-form-row feu-einsatz-inline-card feu-einsatz-inline-card--media">
            <div class="feu-einsatz-inline-card-head">
                <div>
                    <?php if (!$suppress_section_titles) : ?>
                        <h4 class="feu-einsatz-inline-card-title"><?php esc_html_e('Fotos zum Einsatz', 'feuer-einsatzberichte'); ?></h4>
                    <?php endif; ?>
                    <p class="description feu-einsatz-inline-card-copy">
                        <?php esc_html_e('Die ausgewaehlten Bilder erscheinen im Bericht als Galerie.', 'feuer-einsatzberichte'); ?>
                    </p>
                </div>
            </div>

            <input
                type="hidden"
                id="feu_einsatz_gallery_ids"
                name="feu_einsatz_gallery_ids"
                value="<?php echo esc_attr(implode(',', array_map('absint', $gallery_ids))); ?>"
            />

            <div id="feu-einsatz-gallery-preview" class="feu-einsatz-gallery-preview-grid">
                <?php foreach ($gallery_ids as $attachment_id) : ?>
                    <?php $attachment_id = absint($attachment_id); ?>
                    <div class="feu-einsatz-gallery-item" data-id="<?php echo esc_attr($attachment_id); ?>">
                        <?php echo wp_get_attachment_image($attachment_id, 'thumbnail', false, ['class' => 'feu-einsatz-gallery-thumb']); ?>
                        <button
                            type="button"
                            class="button-link-delete feu-einsatz-remove-gallery-image feu-einsatz-gallery-remove-button"
                        >
                            x
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <p>
                <button type="button" id="feu-einsatz-add-gallery-images" class="button">
                    <span class="dashicons dashicons-format-gallery"></span>
                    <?php esc_html_e('Fotos aus Mediathek waehlen', 'feuer-einsatzberichte'); ?>
                </button>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($show_kommentare) : ?>
        <div class="feu-einsatz-form-row feu-einsatz-inline-card feu-einsatz-inline-card--comments">
            <div class="feu-einsatz-inline-card-head">
                <div>
                    <?php if (!$suppress_section_titles) : ?>
                        <h4 class="feu-einsatz-inline-card-title"><?php esc_html_e('Kommentare', 'feuer-einsatzberichte'); ?></h4>
                    <?php endif; ?>
                    <p class="description feu-einsatz-inline-card-copy">
                        <?php esc_html_e('Steuert, ob angemeldete WordPress-Benutzer diesen Bericht kommentieren duerfen.', 'feuer-einsatzberichte'); ?>
                    </p>
                </div>
            </div>

            <label class="feu-einsatz-checkbox-row feu-einsatz-checkbox-row--card">
                <input
                    type="checkbox"
                    id="feu_einsatz_comments_enabled"
                    name="feu_einsatz_comments_enabled"
                    value="1"
                    <?php checked($comments_enabled, '1'); ?>
                />
                <span><?php esc_html_e('Kommentare fuer diesen Einsatzbericht aktivieren', 'feuer-einsatzberichte'); ?></span>
            </label>
        </div>
    <?php endif; ?>

    <?php if ($show_basic_fields) : ?>
        <input type="hidden" name="feu_einsatz_is_einsatzbericht" value="1" />
    <?php endif; ?>
</div>
