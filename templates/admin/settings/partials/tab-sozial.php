<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_social_classes = 'feu-einsatz-tab-content';

if (isset($active_tab) && 'sozial' !== (string) $active_tab) {
    $tab_social_classes .= ' feu-einsatz-tab-content-hidden';
}

$social_share_preview_example = isset($social_share_preview_example) && is_array($social_share_preview_example)
    ? $social_share_preview_example
    : [];

$social_share_preview_site_name = (string) ($social_share_preview_example['site_name'] ?? get_bloginfo('name'));
$social_share_preview_badge = (string) ($social_share_preview_example['badge'] ?? $social_share_badge_text ?? 'EINSATZBERICHT');
$social_share_preview_title = (string) ($social_share_preview_example['title'] ?? 'FEUK - Beispielstrasse');
$social_share_preview_number = (string) ($social_share_preview_example['number'] ?? '55/2026');
$social_share_preview_date = (string) ($social_share_preview_example['date'] ?? '30.04.2026');
$social_share_preview_time = (string) ($social_share_preview_example['time'] ?? '20:58');
$social_share_preview_category = (string) ($social_share_preview_example['category'] ?? 'FEUK');
$social_share_preview_street = (string) ($social_share_preview_example['street'] ?? 'Beispielstrasse');
$social_share_preview_location = (string) ($social_share_preview_example['location'] ?? '22547 Hamburg');
$social_share_preview_description = (string) ($social_share_preview_example['description'] ?? 'Kurzer Beispieltext fuer die Share-Karte. Laengere Texte werden automatisch gekuerzt.');
$social_share_preview_url = (string) ($social_share_preview_example['url'] ?? home_url('/einsaetze/beispiel/'));
$_preview_host = wp_parse_url(home_url(), PHP_URL_HOST);
$_preview_host = $_preview_host ? preg_replace('/^www\./i', '', (string) $_preview_host) : get_bloginfo('name');
$social_share_preview_website_text = 'www.' . $_preview_host;
$social_share_overlay_enabled = isset($social_share_overlay_enabled) ? (int) $social_share_overlay_enabled : 1;
$social_share_image_blur = isset($social_share_image_blur) ? max(0, min(20, (int) $social_share_image_blur)) : 0;
$social_share_panel_radius = isset($social_share_panel_radius) ? max(0, min(120, (int) $social_share_panel_radius)) : 30;
$social_share_badge_radius = isset($social_share_badge_radius) ? max(0, min(120, (int) $social_share_badge_radius)) : 40;
$social_share_link_radius = isset($social_share_link_radius) ? max(0, min(80, (int) $social_share_link_radius)) : 14;
$social_share_text_align_options = isset($social_share_text_align_options) && is_array($social_share_text_align_options)
    ? $social_share_text_align_options
    : FEU_Einsatz_Template_Helpers::get_social_share_text_align_options();
$social_share_text_align = FEU_Einsatz_Template_Helpers::normalize_social_share_text_align($social_share_text_align ?? 'auto');
$social_share_logo_position_options = isset($social_share_logo_position_options) && is_array($social_share_logo_position_options)
    ? $social_share_logo_position_options
    : FEU_Einsatz_Template_Helpers::get_social_share_logo_position_options();
$social_share_logo_position = FEU_Einsatz_Template_Helpers::normalize_social_share_logo_position($social_share_logo_position ?? 'bottom-right');

$social_share_preview_style = '--feu-share-preview-panel:' . $social_share_panel_color . ';'
    . '--feu-share-preview-accent:' . $social_share_accent_color . ';'
    . '--feu-share-preview-title:' . $social_share_title_color . ';'
    . '--feu-share-preview-description:' . $social_share_description_color . ';'
    . '--feu-share-preview-title-scale:' . max(70, min(180, (int) $social_share_title_scale)) . '%;'
    . '--feu-share-preview-description-scale:' . max(70, min(180, (int) $social_share_description_scale)) . '%;'
    . '--feu-share-preview-description-lines:' . max(2, min(10, (int) $social_share_description_max_lines)) . ';'
    . '--feu-share-preview-logo-width:' . max(60, min(520, (int) $social_share_logo_width)) . 'px;'
    . '--feu-share-preview-image-blur:' . $social_share_image_blur . 'px;'
    . '--feu-share-preview-panel-radius:' . $social_share_panel_radius . 'px;'
    . '--feu-share-preview-badge-radius:' . $social_share_badge_radius . 'px;'
    . '--feu-share-preview-link-radius:' . $social_share_link_radius . 'px;';

if ($social_share_background_url) {
    $social_share_preview_style .= 'background-image:url(' . esc_url_raw($social_share_background_url) . ');'
        . '--feu-share-preview-bg-image:url(' . esc_url_raw($social_share_background_url) . ');';
}
?>

<div id="tab-sozial" class="<?php echo esc_attr($tab_social_classes); ?>">
    <div class="feu-admin-settings-stack">
        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Soziale Netzwerke', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Share-Buttons, Share-Karte und Meta-Daten fuer Link-Vorschauen werden hier zentral gesteuert.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-share-settings-workbench" data-feu-share-settings-workbench>
                <div class="feu-share-settings-workbench-copy">
                    <strong><?php esc_html_e('In drei Schritten zur fertigen Share-Karte', 'feuer-einsatzberichte'); ?></strong>
                    <span data-feu-share-settings-status aria-live="polite"></span>
                </div>
                <div class="feu-share-settings-workbench-actions" aria-label="<?php esc_attr_e('Schnellaktionen fuer die Share-Karte', 'feuer-einsatzberichte'); ?>">
                    <button type="button" class="button" data-feu-share-preset="operational"><?php esc_html_e('Einsatz kompakt', 'feuer-einsatzberichte'); ?></button>
                    <button type="button" class="button" data-feu-share-preset="photo"><?php esc_html_e('Foto im Fokus', 'feuer-einsatzberichte'); ?></button>
                    <button type="button" class="button" data-feu-share-preset="story"><?php esc_html_e('Story mobil', 'feuer-einsatzberichte'); ?></button>
                    <a class="button button-secondary" href="#feu-einsatz-social-share-preview"><?php esc_html_e('Vorschau ansehen', 'feuer-einsatzberichte'); ?></a>
                </div>
            </div>

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h3><?php esc_html_e('Netzwerke', 'feuer-einsatzberichte'); ?></h3>
                        <p class="description"><?php esc_html_e('Nur veroeffentlichte Einsatzberichte zeigen diese Buttons im Frontend an.', 'feuer-einsatzberichte'); ?></p>
                    </div>

                    <div class="feu-admin-settings-check-grid">
                        <?php foreach ($social_share_network_definitions as $network_key => $network_definition) : ?>
                            <label class="feu-admin-settings-check-card">
                                <input
                                    type="checkbox"
                                    name="feu_einsatz_social_share_enabled_networks[]"
                                    value="<?php echo esc_attr($network_key); ?>"
                                    <?php checked(in_array($network_key, (array) $social_share_enabled_networks, true)); ?>
                                />
                                <span class="feu-admin-settings-check-copy">
                                    <strong><?php echo esc_html($network_definition['label']); ?></strong>
                                    <small><?php echo esc_html($network_definition['description']); ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h3><?php esc_html_e('Bildquelle und Format', 'feuer-einsatzberichte'); ?></h3>
                        <p class="description"><?php esc_html_e('Sie bestimmen hier, ob ein vorhandenes Beitragsbild oder eine generierte Share-Karte genutzt wird.', 'feuer-einsatzberichte'); ?></p>
                    </div>

                    <div class="feu-admin-form-radio-grid">
                        <label class="feu-admin-form-radio-card">
                            <input type="radio" name="feu_einsatz_social_share_image_mode" value="post_image" <?php checked($social_share_image_mode, 'post_image'); ?> />
                            <span class="feu-admin-form-radio-copy">
                                <strong><?php esc_html_e('Vorhandenes Beitragsbild', 'feuer-einsatzberichte'); ?></strong>
                                <small><?php esc_html_e('Nutzen Sie zuerst das echte Foto des Einsatzberichts.', 'feuer-einsatzberichte'); ?></small>
                            </span>
                        </label>
                        <label class="feu-admin-form-radio-card">
                            <input type="radio" name="feu_einsatz_social_share_image_mode" value="generated" <?php checked($social_share_image_mode, 'generated'); ?> />
                            <span class="feu-admin-form-radio-copy">
                                <strong><?php esc_html_e('Generierte Share-Karte', 'feuer-einsatzberichte'); ?></strong>
                                <small><?php esc_html_e('Logo, Badge und Text werden direkt auf dem Bild gerendert.', 'feuer-einsatzberichte'); ?></small>
                            </span>
                        </label>
                    </div>

                    <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Layout', 'feuer-einsatzberichte'); ?></span>
                            <select id="feu_einsatz_social_share_layout" name="feu_einsatz_social_share_layout" class="regular-text">
                                <?php foreach ($social_share_layout_definitions as $layout_key => $layout_definition) : ?>
                                    <option value="<?php echo esc_attr($layout_key); ?>" <?php selected($social_share_layout, $layout_key); ?>>
                                        <?php echo esc_html($layout_definition['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Kurztext fuer Website-Hinweis', 'feuer-einsatzberichte'); ?></span>
                            <input type="text" id="feu_einsatz_social_share_cta_text" name="feu_einsatz_social_share_cta_text" class="regular-text" value="<?php echo esc_attr($social_share_cta_text); ?>" placeholder="<?php esc_attr_e('Weitere Infos', 'feuer-einsatzberichte'); ?>" />
                            <small><?php esc_html_e('Optional. In der aktuellen Karte wird unten primaer die kurze Website-Adresse gezeigt.', 'feuer-einsatzberichte'); ?></small>
                        </label>
                    </div>
                </div>
            </div>
        </section>

        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h3><?php esc_html_e('Share-Karte gestalten', 'feuer-einsatzberichte'); ?></h3>
                    <p class="description"><?php esc_html_e('Die generierte Karte kann hier ohne externe Ressourcen direkt im Plugin gestaltet werden.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h4><?php esc_html_e('Bilder', 'feuer-einsatzberichte'); ?></h4>
                    </div>

                    <div class="feu-admin-settings-media-stack">
                        <div class="feu-admin-settings-media-card">
                            <label for="feu_einsatz_social_share_background_id"><strong><?php esc_html_e('Hintergrundbild', 'feuer-einsatzberichte'); ?></strong></label>
                            <input type="hidden" id="feu_einsatz_social_share_background_id" name="feu_einsatz_social_share_background_id" value="<?php echo esc_attr($social_share_background_id); ?>" />
                            <div class="feu-einsatz-watermark-image-panel feu-einsatz-social-share-image-panel">
                                <div id="feu-einsatz-social-share-image-preview" class="feu-einsatz-watermark-image-preview">
                                    <?php if ($social_share_background_url) : ?>
                                        <img src="<?php echo esc_url($social_share_background_url); ?>" alt="" class="feu-einsatz-watermark-preview-image" />
                                    <?php else : ?>
                                        <div class="feu-einsatz-watermark-placeholder"><?php esc_html_e('Kein eigenes Hintergrundbild gewaehlt. Dann greift die Karte auf Beitragsbild oder neutrale Flaeche zurueck.', 'feuer-einsatzberichte'); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="feu-einsatz-watermark-actions">
                                    <button type="button" class="button" id="feu-einsatz-social-share-image-select"><?php esc_html_e('Hintergrundbild waehlen', 'feuer-einsatzberichte'); ?></button>
                                    <button type="button" class="button" id="feu-einsatz-social-share-image-remove" <?php echo $social_share_background_url ? '' : 'hidden'; ?>><?php esc_html_e('Bild entfernen', 'feuer-einsatzberichte'); ?></button>
                                </div>
                            </div>
                        </div>

                        <div class="feu-admin-settings-media-card">
                            <label for="feu_einsatz_social_share_logo_id"><strong><?php esc_html_e('Logo unten rechts', 'feuer-einsatzberichte'); ?></strong></label>
                            <input type="hidden" id="feu_einsatz_social_share_logo_id" name="feu_einsatz_social_share_logo_id" value="<?php echo esc_attr($social_share_logo_id); ?>" />
                            <div class="feu-einsatz-watermark-image-panel feu-einsatz-social-share-image-panel">
                                <div id="feu-einsatz-social-share-logo-preview" class="feu-einsatz-watermark-image-preview">
                                    <?php if ($social_share_logo_url) : ?>
                                        <img src="<?php echo esc_url($social_share_logo_url); ?>" alt="" class="feu-einsatz-watermark-preview-image" />
                                    <?php else : ?>
                                        <div class="feu-einsatz-watermark-placeholder"><?php esc_html_e('Kein Logo gewaehlt. Dann bleibt die rechte untere Ecke frei.', 'feuer-einsatzberichte'); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="feu-einsatz-watermark-actions">
                                    <button type="button" class="button" id="feu-einsatz-social-share-logo-select"><?php esc_html_e('Logo waehlen', 'feuer-einsatzberichte'); ?></button>
                                    <button type="button" class="button" id="feu-einsatz-social-share-logo-remove" <?php echo $social_share_logo_url ? '' : 'hidden'; ?>><?php esc_html_e('Logo entfernen', 'feuer-einsatzberichte'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h4><?php esc_html_e('Texte und Farben', 'feuer-einsatzberichte'); ?></h4>
                    </div>

                    <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Badge-Text', 'feuer-einsatzberichte'); ?></span>
                            <input type="text" id="feu_einsatz_social_share_badge_text" name="feu_einsatz_social_share_badge_text" class="regular-text" value="<?php echo esc_attr($social_share_badge_text); ?>" />
                        </label>
                        <label class="feu-admin-settings-field feu-admin-settings-field--color">
                            <span><?php esc_html_e('Titel-Farbe', 'feuer-einsatzberichte'); ?></span>
                            <input type="color" id="feu_einsatz_social_share_title_color" name="feu_einsatz_social_share_title_color" value="<?php echo esc_attr($social_share_title_color); ?>" />
                        </label>
                        <label class="feu-admin-settings-field feu-admin-settings-field--color">
                            <span><?php esc_html_e('Text-Farbe', 'feuer-einsatzberichte'); ?></span>
                            <input type="color" id="feu_einsatz_social_share_description_color" name="feu_einsatz_social_share_description_color" value="<?php echo esc_attr($social_share_description_color); ?>" />
                        </label>
                        <label class="feu-admin-settings-field feu-admin-settings-field--color">
                            <span><?php esc_html_e('Panel-Farbe', 'feuer-einsatzberichte'); ?></span>
                            <input type="color" id="feu_einsatz_social_share_panel_color" name="feu_einsatz_social_share_panel_color" value="<?php echo esc_attr($social_share_panel_color); ?>" />
                        </label>
                        <label class="feu-admin-settings-field feu-admin-settings-field--color">
                            <span><?php esc_html_e('Akzent / Badge', 'feuer-einsatzberichte'); ?></span>
                            <input type="color" id="feu_einsatz_social_share_accent_color" name="feu_einsatz_social_share_accent_color" value="<?php echo esc_attr($social_share_accent_color); ?>" />
                        </label>
                    </div>

                    <div class="feu-admin-settings-check-grid">
                        <?php foreach ($social_share_field_definitions as $field_key => $field_label) : ?>
                            <label class="feu-admin-settings-check-card<?php echo 'url' === $field_key ? ' is-fixed' : ''; ?>">
                                <input
                                    type="checkbox"
                                    name="feu_einsatz_social_share_fields[]"
                                    value="<?php echo esc_attr($field_key); ?>"
                                    data-feu-share-preview-field-toggle="<?php echo esc_attr($field_key); ?>"
                                    <?php checked('url' === $field_key || in_array($field_key, (array) $social_share_fields, true)); ?>
                                    <?php echo 'url' === $field_key ? 'disabled' : ''; ?>
                                />
                                <span class="feu-admin-settings-check-copy">
                                    <strong><?php echo esc_html($field_label); ?></strong>
                                    <?php if ('url' === $field_key) : ?>
                                        <small><?php esc_html_e('Wird als kurzer Website-Hinweis am unteren Rand ausgegeben.', 'feuer-einsatzberichte'); ?></small>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h4><?php esc_html_e('Textgroessen', 'feuer-einsatzberichte'); ?></h4>
                    </div>
                    <div class="feu-admin-settings-range-grid feu-admin-settings-range-grid--compact">
                        <label class="feu-admin-settings-range-card">
                            <span><?php esc_html_e('Titelgroesse', 'feuer-einsatzberichte'); ?></span>
                            <input type="range" id="feu_einsatz_social_share_title_scale" name="feu_einsatz_social_share_title_scale" min="70" max="180" step="1" value="<?php echo esc_attr($social_share_title_scale); ?>" />
                            <strong><span data-feu-share-title-scale-value><?php echo esc_html($social_share_title_scale); ?></span>%</strong>
                        </label>
                        <label class="feu-admin-settings-range-card">
                            <span><?php esc_html_e('Textgroesse', 'feuer-einsatzberichte'); ?></span>
                            <input type="range" id="feu_einsatz_social_share_description_scale" name="feu_einsatz_social_share_description_scale" min="70" max="180" step="1" value="<?php echo esc_attr($social_share_description_scale); ?>" />
                            <strong><span data-feu-share-description-scale-value><?php echo esc_html($social_share_description_scale); ?></span>%</strong>
                        </label>
                        <label class="feu-admin-settings-range-card">
                            <span><?php esc_html_e('Beschreibung max. Zeilen', 'feuer-einsatzberichte'); ?></span>
                            <input type="range" id="feu_einsatz_social_share_description_max_lines" name="feu_einsatz_social_share_description_max_lines" min="2" max="10" step="1" value="<?php echo esc_attr($social_share_description_max_lines); ?>" />
                            <strong><span data-feu-share-description-lines-value><?php echo esc_html($social_share_description_max_lines); ?></span></strong>
                        </label>
                    </div>
                </div>
                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h4><?php esc_html_e('Logo unten rechts', 'feuer-einsatzberichte'); ?></h4>
                    </div>
                    <div class="feu-admin-settings-range-grid feu-admin-settings-range-grid--compact">
                        <label class="feu-admin-settings-range-card">
                            <span><?php esc_html_e('Logo-Breite', 'feuer-einsatzberichte'); ?></span>
                            <input type="range" id="feu_einsatz_social_share_logo_width" name="feu_einsatz_social_share_logo_width" min="60" max="520" step="1" value="<?php echo esc_attr($social_share_logo_width); ?>" />
                            <strong><span data-feu-share-logo-width-value><?php echo esc_html($social_share_logo_width); ?></span> px</strong>
                        </label>
                    </div>
                </div>
            </div>

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h4><?php esc_html_e('Overlays und Rundungen', 'feuer-einsatzberichte'); ?></h4>
                    </div>

                    <div class="feu-admin-settings-check-grid">
                        <label class="feu-admin-settings-check-card">
                            <input
                                type="checkbox"
                                id="feu_einsatz_social_share_overlay_enabled"
                                name="feu_einsatz_social_share_overlay_enabled"
                                value="1"
                                <?php checked(1 === (int) $social_share_overlay_enabled); ?>
                            />
                            <span class="feu-admin-settings-check-copy">
                                <strong><?php esc_html_e('Hintergrund-Overlays anzeigen', 'feuer-einsatzberichte'); ?></strong>
                                <small><?php esc_html_e('Deaktivieren, wenn Bild, Texte und Logo ohne blaue Panel-Flaeche wirken sollen.', 'feuer-einsatzberichte'); ?></small>
                            </span>
                        </label>
                    </div>

                    <div class="feu-admin-settings-range-grid feu-admin-settings-range-grid--compact">
                        <label class="feu-admin-settings-range-card">
                            <span><?php esc_html_e('Panel-Rundung', 'feuer-einsatzberichte'); ?></span>
                            <input type="range" id="feu_einsatz_social_share_panel_radius" name="feu_einsatz_social_share_panel_radius" min="0" max="120" step="1" value="<?php echo esc_attr($social_share_panel_radius); ?>" />
                            <strong><span data-feu-share-panel-radius-value><?php echo esc_html($social_share_panel_radius); ?></span> px</strong>
                        </label>
                        <label class="feu-admin-settings-range-card">
                            <span><?php esc_html_e('Badge-Rundung', 'feuer-einsatzberichte'); ?></span>
                            <input type="range" id="feu_einsatz_social_share_badge_radius" name="feu_einsatz_social_share_badge_radius" min="0" max="120" step="1" value="<?php echo esc_attr($social_share_badge_radius); ?>" />
                            <strong><span data-feu-share-badge-radius-value><?php echo esc_html($social_share_badge_radius); ?></span> px</strong>
                        </label>
                        <label class="feu-admin-settings-range-card">
                            <span><?php esc_html_e('Website-Hinweis-Rundung', 'feuer-einsatzberichte'); ?></span>
                            <input type="range" id="feu_einsatz_social_share_link_radius" name="feu_einsatz_social_share_link_radius" min="0" max="80" step="1" value="<?php echo esc_attr($social_share_link_radius); ?>" />
                            <strong><span data-feu-share-link-radius-value><?php echo esc_html($social_share_link_radius); ?></span> px</strong>
                        </label>
                    </div>
                </div>

                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h4><?php esc_html_e('Bild, Text und Logo platzieren', 'feuer-einsatzberichte'); ?></h4>
                    </div>

                    <div class="feu-admin-settings-range-grid feu-admin-settings-range-grid--compact">
                        <label class="feu-admin-settings-range-card">
                            <span><?php esc_html_e('Hintergrundbild weichzeichnen', 'feuer-einsatzberichte'); ?></span>
                            <input type="range" id="feu_einsatz_social_share_image_blur" name="feu_einsatz_social_share_image_blur" min="0" max="20" step="1" value="<?php echo esc_attr($social_share_image_blur); ?>" />
                            <strong><span data-feu-share-image-blur-value><?php echo esc_html($social_share_image_blur); ?></span> px</strong>
                        </label>
                    </div>

                    <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Textausrichtung', 'feuer-einsatzberichte'); ?></span>
                            <select id="feu_einsatz_social_share_text_align" name="feu_einsatz_social_share_text_align" class="regular-text">
                                <?php foreach ($social_share_text_align_options as $align_key => $align_label) : ?>
                                    <option value="<?php echo esc_attr($align_key); ?>" <?php selected($social_share_text_align, $align_key); ?>>
                                        <?php echo esc_html($align_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Logo-Position', 'feuer-einsatzberichte'); ?></span>
                            <select id="feu_einsatz_social_share_logo_position" name="feu_einsatz_social_share_logo_position" class="regular-text">
                                <?php foreach ($social_share_logo_position_options as $position_key => $position_label) : ?>
                                    <option value="<?php echo esc_attr($position_key); ?>" <?php selected($social_share_logo_position, $position_key); ?>>
                                        <?php echo esc_html($position_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </div>
            </div>
        </section>

        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h3><?php esc_html_e('Meta-Daten und Link-Vorschau', 'feuer-einsatzberichte'); ?></h3>
                    <p class="description"><?php esc_html_e('Open Graph, X Cards, Canonical und Schema.org werden hier fuer Link-Vorschauen und Suchmaschinen geschaltet.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <div class="feu-admin-settings-check-grid">
                    <label class="feu-admin-settings-check-card">
                        <input type="checkbox" name="feu_einsatz_social_meta_enabled" value="1" <?php checked(1 === (int) $social_meta_enabled); ?> />
                        <span class="feu-admin-settings-check-copy">
                            <strong><?php esc_html_e('Open Graph und X Cards', 'feuer-einsatzberichte'); ?></strong>
                            <small><?php esc_html_e('Titel, Beschreibung und Bild fuer Messenger und Social-Apps.', 'feuer-einsatzberichte'); ?></small>
                        </span>
                    </label>
                    <label class="feu-admin-settings-check-card">
                        <input type="checkbox" name="feu_einsatz_social_meta_canonical_enabled" value="1" <?php checked(1 === (int) $social_meta_canonical_enabled); ?> />
                        <span class="feu-admin-settings-check-copy">
                            <strong><?php esc_html_e('Canonical und Meta-Description', 'feuer-einsatzberichte'); ?></strong>
                            <small><?php esc_html_e('Hilft Suchmaschinen beim Erkennen der Haupt-URL.', 'feuer-einsatzberichte'); ?></small>
                        </span>
                    </label>
                    <label class="feu-admin-settings-check-card">
                        <input type="checkbox" name="feu_einsatz_social_meta_schema_enabled" value="1" <?php checked(1 === (int) $social_meta_schema_enabled); ?> />
                        <span class="feu-admin-settings-check-copy">
                            <strong><?php esc_html_e('Schema.org', 'feuer-einsatzberichte'); ?></strong>
                            <small><?php esc_html_e('Strukturierte Daten fuer Artikel und Breadcrumbs.', 'feuer-einsatzberichte'); ?></small>
                        </span>
                    </label>
                </div>

                <label class="feu-admin-settings-field">
                    <span><?php esc_html_e('X / Twitter Handle', 'feuer-einsatzberichte'); ?></span>
                    <input type="text" id="feu_einsatz_social_meta_twitter_site" name="feu_einsatz_social_meta_twitter_site" class="regular-text" value="<?php echo esc_attr($social_meta_twitter_site); ?>" placeholder="@feuerwehr" />
                    <small><?php esc_html_e('Wird fuer Cards und via= bei X verwendet.', 'feuer-einsatzberichte'); ?></small>
                </label>
            </div>
        </section>

        <section class="feu-admin-settings-surface" id="feu-einsatz-social-share-preview">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h3><?php esc_html_e('Live-Vorschau', 'feuer-einsatzberichte'); ?></h3>
                    <p class="description"><?php esc_html_e('Die Vorschau zeigt die aktuelle Komposition der Share-Karte. Der Hinweis unten ist nur ein kurzer Website-Text und keine klickbare URL.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-einsatz-social-share-live-preview" data-feu-share-preview-root>
                <div class="feu-einsatz-social-share-live-preview-head">
                    <strong><?php esc_html_e('Aktuelle Share-Karte', 'feuer-einsatzberichte'); ?></strong>
                    <span class="feu-einsatz-social-share-live-preview-mode" data-feu-share-preview-mode-label>
                        <?php echo 'generated' === $social_share_image_mode
                            ? esc_html__('Aktiv: Generierte Share-Karte', 'feuer-einsatzberichte')
                            : esc_html__('Aktiv: Vorhandenes Beitragsbild', 'feuer-einsatzberichte'); ?>
                    </span>
                </div>

                <div
                    class="feu-einsatz-social-share-live-preview-card is-layout-<?php echo esc_attr($social_share_layout); ?><?php echo 'generated' === $social_share_image_mode ? ' is-generated-mode' : ' is-post-image-mode'; ?><?php echo $social_share_overlay_enabled ? '' : ' is-overlay-disabled'; ?> is-logo-position-<?php echo esc_attr($social_share_logo_position); ?> is-text-align-<?php echo esc_attr($social_share_text_align); ?>"
                    data-feu-share-preview-card
                    data-feu-share-preview-layout="<?php echo esc_attr($social_share_layout); ?>"
                    style="<?php echo esc_attr($social_share_preview_style); ?>"
                >
                    <div class="feu-einsatz-social-share-live-preview-panel">
                        <div class="feu-einsatz-social-share-live-preview-badge" data-feu-share-preview-badge><?php echo esc_html($social_share_preview_badge); ?></div>
                        <div class="feu-einsatz-social-share-live-preview-site"><?php echo esc_html($social_share_preview_site_name); ?></div>
                        <div class="feu-einsatz-social-share-live-preview-title" data-feu-share-preview-field="title"><?php echo esc_html($social_share_preview_title); ?></div>

                        <div class="feu-einsatz-social-share-live-preview-facts" data-feu-share-preview-group="facts">
                            <div class="feu-einsatz-social-share-live-preview-fact" data-feu-share-preview-field="number"><?php echo esc_html(sprintf(__('Einsatz Nr. %s', 'feuer-einsatzberichte'), $social_share_preview_number)); ?></div>
                            <div class="feu-einsatz-social-share-live-preview-fact" data-feu-share-preview-field="date"><?php echo esc_html(sprintf(__('Datum %s', 'feuer-einsatzberichte'), $social_share_preview_date)); ?></div>
                            <div class="feu-einsatz-social-share-live-preview-fact" data-feu-share-preview-field="time"><?php echo esc_html(sprintf(__('um %s Uhr', 'feuer-einsatzberichte'), $social_share_preview_time)); ?></div>
                            <div class="feu-einsatz-social-share-live-preview-fact" data-feu-share-preview-field="category"><?php echo esc_html(sprintf(__('Einsatzart %s', 'feuer-einsatzberichte'), $social_share_preview_category)); ?></div>
                            <div class="feu-einsatz-social-share-live-preview-fact" data-feu-share-preview-field="street"><?php echo esc_html($social_share_preview_street); ?></div>
                            <div class="feu-einsatz-social-share-live-preview-fact" data-feu-share-preview-field="location"><?php echo esc_html(sprintf(__('Ort: %s', 'feuer-einsatzberichte'), $social_share_preview_location)); ?></div>
                        </div>

                        <div class="feu-einsatz-social-share-live-preview-description" data-feu-share-preview-field="description"><?php echo esc_html($social_share_preview_description); ?></div>
                        <div class="feu-einsatz-social-share-live-preview-website" data-feu-share-preview-field="url" data-feu-share-preview-website-label><?php echo esc_html($social_share_preview_website_text); ?></div>

                        <?php if ($social_share_logo_url) : ?>
                            <div class="feu-einsatz-social-share-live-preview-logo">
                                <img src="<?php echo esc_url($social_share_logo_url); ?>" alt="" data-feu-share-preview-logo />
                            </div>
                        <?php else : ?>
                            <div class="feu-einsatz-social-share-live-preview-logo" hidden>
                                <img src="" alt="" data-feu-share-preview-logo />
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="description feu-einsatz-social-share-live-preview-note" data-feu-share-preview-note>
                    <?php echo 'generated' === $social_share_image_mode
                        ? esc_html__('Die Vorschau zeigt die aktuelle generierte Share-Karte mit kurzer Website-Adresse.', 'feuer-einsatzberichte')
                        : esc_html__('Aktuell nutzt der Beitrag zuerst sein Bild. Die Vorschau zeigt die Kartenkomposition mit kurzer Website-Adresse.', 'feuer-einsatzberichte'); ?>
                </p>
            </div>
        </section>
    </div>
</div>
