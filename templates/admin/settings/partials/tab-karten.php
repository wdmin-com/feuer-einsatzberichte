<?php
if (!defined('ABSPATH')) {
    exit;
}

$tab_karten_classes = 'feu-einsatz-tab-content';

if (!isset($active_tab) || 'karten' !== (string) $active_tab) {
    $tab_karten_classes .= ' feu-einsatz-tab-content-hidden';
}

$selected_map_preview_font_definition = (
    isset($map_preview_font_family, $map_preview_font_options[$map_preview_font_family])
    && is_array($map_preview_font_options[$map_preview_font_family])
)
    ? $map_preview_font_options[$map_preview_font_family]
    : ((isset($map_preview_font_options['auto']) && is_array($map_preview_font_options['auto']))
        ? $map_preview_font_options['auto']
        : ['css_stack' => '"Segoe UI", Arial, sans-serif']);

$live_preview_heading = isset($map_preview_heading_text) && '' !== trim((string) $map_preview_heading_text)
    ? (string) $map_preview_heading_text
    : 'Einsatzort';
$live_preview_prefix = isset($map_preview_street_label_prefix) && '' !== trim((string) $map_preview_street_label_prefix)
    ? (string) $map_preview_street_label_prefix
    : 'Einsatz Strasse';
$map_preview_show_panel = isset($map_preview_show_panel) ? (int) $map_preview_show_panel : 1;
$map_preview_show_panel_heading = isset($map_preview_show_panel_heading) ? (int) $map_preview_show_panel_heading : 1;
$map_preview_show_panel_address = isset($map_preview_show_panel_address) ? (int) $map_preview_show_panel_address : 1;
$map_preview_panel_position = isset($map_preview_panel_position) ? (string) $map_preview_panel_position : 'bottom-left';
$map_preview_street_label_position = isset($map_preview_street_label_position) ? (string) $map_preview_street_label_position : 'auto';
$map_preview_show_attribution = isset($map_preview_show_attribution) ? (int) $map_preview_show_attribution : 1;
$map_preview_attribution_text = isset($map_preview_attribution_text) && '' !== trim((string) $map_preview_attribution_text)
    ? (string) $map_preview_attribution_text
    : 'Leaflet | ' . html_entity_decode('&copy;', ENT_QUOTES, 'UTF-8') . ' OpenStreetMap contributors';
$map_preview_attribution_position = isset($map_preview_attribution_position) ? (string) $map_preview_attribution_position : 'bottom-right';
$map_preview_position_options = isset($map_preview_position_options) && is_array($map_preview_position_options)
    ? $map_preview_position_options
    : [];
if (empty($map_preview_position_options)) {
    $map_preview_position_options = [
        'top-left' => __('Oben links', 'feuer-einsatzberichte'),
        'top-right' => __('Oben rechts', 'feuer-einsatzberichte'),
        'bottom-left' => __('Unten links', 'feuer-einsatzberichte'),
        'bottom-right' => __('Unten rechts', 'feuer-einsatzberichte'),
        'center' => __('Mitte', 'feuer-einsatzberichte'),
    ];
}
$map_preview_label_position_options = isset($map_preview_label_position_options) && is_array($map_preview_label_position_options)
    ? $map_preview_label_position_options
    : $map_preview_position_options;
if (!isset($map_preview_label_position_options['auto'])) {
    $map_preview_label_position_options = ['auto' => __('Automatisch', 'feuer-einsatzberichte')] + $map_preview_label_position_options;
}
$single_live_map_show_station = (int) ($single_live_map_show_station ?? 0);
$live_preview_station_street = trim((string) ($area_station_street ?? ''));
$live_preview_station_city = trim((string) ($area_station_city ?? 'Hamburg'));
$live_preview_station_postcode = trim((string) ($area_station_postcode ?? ''));
$live_preview_station_label = trim((string) ($photo_watermark_text ?? ''));
$live_preview_station_feature = isset($map_preview_live_station_feature) && is_array($map_preview_live_station_feature)
    ? $map_preview_live_station_feature
    : [];
$live_preview_station_logo = !empty($live_preview_station_feature['logo_url'])
    ? (string) $live_preview_station_feature['logo_url']
    : trim((string) ($area_station_logo_url ?? ''));
$live_preview_station_logo_size = isset($live_preview_station_feature['logo_size'])
    ? max(20, min(96, absint($live_preview_station_feature['logo_size'])))
    : max(20, min(96, absint($area_station_logo_size ?? 40)));
$live_preview_center_lat = isset($map_preview_live_center['lat']) && is_numeric($map_preview_live_center['lat'])
    ? (float) $map_preview_live_center['lat']
    : 53.5853;
$live_preview_center_lng = isset($map_preview_live_center['lng']) && is_numeric($map_preview_live_center['lng'])
    ? (float) $map_preview_live_center['lng']
    : 9.8827;
$live_preview_station_lat = isset($live_preview_station_feature['latitude']) && is_numeric($live_preview_station_feature['latitude'])
    ? (float) $live_preview_station_feature['latitude']
    : $live_preview_center_lat;
$live_preview_station_lng = isset($live_preview_station_feature['longitude']) && is_numeric($live_preview_station_feature['longitude'])
    ? (float) $live_preview_station_feature['longitude']
    : $live_preview_center_lng;
$live_preview_fallback_markup = isset($map_preview_live_fallback_markup) ? (string) $map_preview_live_fallback_markup : '';
$live_preview_geometry = isset($map_preview_live_geometry) && is_array($map_preview_live_geometry)
    ? $map_preview_live_geometry
    : [];
$live_preview_geometry_json = wp_json_encode($live_preview_geometry);

if ('' === $live_preview_station_label) {
    $live_preview_station_label = __('Feuerwehrhaus', 'feuer-einsatzberichte');
}

$live_preview_street_only = $live_preview_station_street;
if ('' !== $live_preview_street_only) {
    $street_parts = explode(',', $live_preview_street_only);
    $live_preview_street_only = trim((string) $street_parts[0]);
    $live_preview_street_only = trim((string) preg_replace('/\s+\d+[a-zA-Z\-\/]*\s*$/', '', $live_preview_street_only));
}
if ('' === $live_preview_street_only) {
    $live_preview_street_only = 'Beispielstrasse';
}

$live_preview_address = trim(implode(', ', array_filter([
    '' !== $live_preview_station_street ? $live_preview_station_street : $live_preview_street_only,
    trim($live_preview_station_postcode . ' ' . $live_preview_station_city),
])));

if ('' === $live_preview_address) {
    $live_preview_address = 'Beispielstrasse, 22547 Hamburg';
}

$generated_map_rebuild_from = isset($generated_map_rebuild_from) ? (string) $generated_map_rebuild_from : '';
$generated_map_rebuild_to = isset($generated_map_rebuild_to) ? (string) $generated_map_rebuild_to : '';
?>

<div id="tab-karten" class="<?php echo esc_attr($tab_karten_classes); ?>">
    <div class="feu-admin-settings-stack">
        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h2><?php esc_html_e('Karteneinstellungen', 'feuer-einsatzberichte'); ?></h2>
                    <p class="description"><?php esc_html_e('Live-Karte, Kartenbild, Feuerwehrhaus und Wartung werden hier in einem kompakteren Layout gesteuert.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h3><?php esc_html_e('Grundkarte im Beitrag', 'feuer-einsatzberichte'); ?></h3>
                        <p class="description"><?php esc_html_e('Diese Werte wirken auf die Live-Karte im Einsatzbericht und auf die lokale Kartenvorschau.', 'feuer-einsatzberichte'); ?></p>
                    </div>

                    <div class="feu-admin-settings-check-grid">
                        <label class="feu-admin-settings-check-card">
                            <input
                                type="checkbox"
                                id="feu_einsatz_single_live_map_show_station"
                                name="feu_einsatz_single_live_map_show_station"
                                value="1"
                                <?php checked(1 === $single_live_map_show_station); ?>
                            />
                            <span class="feu-admin-settings-check-copy">
                                <strong><?php esc_html_e('Feuerwehrhaus in Live-Karte', 'feuer-einsatzberichte'); ?></strong>
                                <small><?php esc_html_e('Zeigt das Feuerwehrhaus zusaetzlich in der Live-Karte des Einsatzberichts an, ohne die Kartenbild-Generierung zu veraendern.', 'feuer-einsatzberichte'); ?></small>
                            </span>
                        </label>
                    </div>

                    <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Zoom-Level', 'feuer-einsatzberichte'); ?></span>
                            <input
                                type="number"
                                id="feu_einsatz_map_zoom"
                                name="feu_einsatz_map_zoom"
                                value="<?php echo esc_attr($map_zoom); ?>"
                                min="1"
                                max="20"
                                step="1"
                                class="small-text"
                            />
                            <small><?php esc_html_e('1 = weit weg, 20 = sehr nah.', 'feuer-einsatzberichte'); ?></small>
                        </label>
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Kartenhoehe (px)', 'feuer-einsatzberichte'); ?></span>
                            <input
                                type="number"
                                id="feu_einsatz_map_height"
                                name="feu_einsatz_map_height"
                                value="<?php echo esc_attr($map_height); ?>"
                                min="200"
                                max="1000"
                                step="50"
                                class="small-text"
                            />
                            <small><?php esc_html_e('Steuert die sichtbare Hoehe der Karte im Einzelbeitrag.', 'feuer-einsatzberichte'); ?></small>
                        </label>
                    </div>
                </div>

                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h3><?php esc_html_e('Automatisches Kartenbild', 'feuer-einsatzberichte'); ?></h3>
                        <p class="description"><?php esc_html_e('Das Kartenbild wird als Beitragsbild erzeugt, solange kein manuelles Beitragsbild gesetzt wurde.', 'feuer-einsatzberichte'); ?></p>
                    </div>

                    <div class="feu-admin-settings-check-grid">
                        <label class="feu-admin-settings-check-card">
                            <input
                                type="checkbox"
                                id="feu_einsatz_auto_map_image"
                                name="feu_einsatz_auto_map_image"
                                value="1"
                                <?php checked($auto_map_image, 1); ?>
                            />
                            <span class="feu-admin-settings-check-copy">
                                <strong><?php esc_html_e('Kartenbild automatisch aktualisieren', 'feuer-einsatzberichte'); ?></strong>
                                <small><?php esc_html_e('Solange kein eigenes Foto gesetzt wurde, kann das Plugin das Kartenbild automatisch neu erzeugen.', 'feuer-einsatzberichte'); ?></small>
                            </span>
                        </label>
                    </div>

                    <p class="description feu-admin-settings-inline-note">
                        <?php esc_html_e('Aenderungen an Farbe, Linienbreite, Schrift oder Feuerwehrhaus koennen anschliessend per Neuaufbau auch auf aeltere Kartenbilder angewendet werden.', 'feuer-einsatzberichte'); ?>
                    </p>
                </div>
            </div>
        </section>

        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h3><?php esc_html_e('Kartenbild fuer Einsatzberichte', 'feuer-einsatzberichte'); ?></h3>
                    <p class="description"><?php esc_html_e('Diese Einstellungen wirken direkt auf das generierte PNG/SVG-Kartenbild und auf die Vorschau darunter.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h4><?php esc_html_e('Beschriftung und Panel', 'feuer-einsatzberichte'); ?></h4>
                    </div>

                    <label class="feu-admin-settings-field">
                        <span><?php esc_html_e('Text im Kartenbild-Panel', 'feuer-einsatzberichte'); ?></span>
                        <input
                            type="text"
                            id="feu_einsatz_map_preview_heading_text"
                            name="feu_einsatz_map_preview_heading_text"
                            value="<?php echo esc_attr(isset($map_preview_heading_text) ? (string) $map_preview_heading_text : ''); ?>"
                            class="regular-text"
                            placeholder="<?php esc_attr_e('Leer lassen fuer den Standardtext', 'feuer-einsatzberichte'); ?>"
                        />
                        <small><?php esc_html_e('Steuert die Ueberschrift im unteren Infofeld des Kartenbilds.', 'feuer-einsatzberichte'); ?></small>
                    </label>

                    <div class="feu-admin-settings-check-grid">
                        <label class="feu-admin-settings-check-card">
                            <input type="checkbox" id="feu_einsatz_map_preview_show_panel" name="feu_einsatz_map_preview_show_panel" value="1" <?php checked(1 === (int) $map_preview_show_panel); ?> />
                            <span class="feu-admin-settings-check-copy">
                                <strong><?php esc_html_e('Infofeld anzeigen', 'feuer-einsatzberichte'); ?></strong>
                                <small><?php esc_html_e('Blendet das Textfeld mit Ueberschrift und Adresse ein oder aus.', 'feuer-einsatzberichte'); ?></small>
                            </span>
                        </label>
                        <label class="feu-admin-settings-check-card">
                            <input type="checkbox" id="feu_einsatz_map_preview_show_panel_heading" name="feu_einsatz_map_preview_show_panel_heading" value="1" <?php checked(1 === (int) $map_preview_show_panel_heading); ?> />
                            <span class="feu-admin-settings-check-copy">
                                <strong><?php esc_html_e('Ueberschrift im Infofeld', 'feuer-einsatzberichte'); ?></strong>
                                <small><?php esc_html_e('Zeigt den Kartenbild-Panel-Text im Infofeld.', 'feuer-einsatzberichte'); ?></small>
                            </span>
                        </label>
                        <label class="feu-admin-settings-check-card">
                            <input type="checkbox" id="feu_einsatz_map_preview_show_panel_address" name="feu_einsatz_map_preview_show_panel_address" value="1" <?php checked(1 === (int) $map_preview_show_panel_address); ?> />
                            <span class="feu-admin-settings-check-copy">
                                <strong><?php esc_html_e('Adresse im Infofeld', 'feuer-einsatzberichte'); ?></strong>
                                <small><?php esc_html_e('Zeigt Strasse, PLZ und Stadt im Infofeld.', 'feuer-einsatzberichte'); ?></small>
                            </span>
                        </label>
                    </div>

                    <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Position des Infofelds', 'feuer-einsatzberichte'); ?></span>
                            <select id="feu_einsatz_map_preview_panel_position" name="feu_einsatz_map_preview_panel_position">
                                <?php foreach ($map_preview_position_options as $position_key => $position_label) : ?>
                                    <option value="<?php echo esc_attr((string) $position_key); ?>" <?php selected($map_preview_panel_position, (string) $position_key); ?>><?php echo esc_html((string) $position_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Position des Strassen-Labels', 'feuer-einsatzberichte'); ?></span>
                            <select id="feu_einsatz_map_preview_street_label_position" name="feu_einsatz_map_preview_street_label_position">
                                <?php foreach ($map_preview_label_position_options as $position_key => $position_label) : ?>
                                    <option value="<?php echo esc_attr((string) $position_key); ?>" <?php selected($map_preview_street_label_position, (string) $position_key); ?>><?php echo esc_html((string) $position_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <div class="feu-admin-settings-check-grid">
                        <label class="feu-admin-settings-check-card">
                            <input
                                type="checkbox"
                                id="feu_einsatz_map_preview_show_street_label"
                                name="feu_einsatz_map_preview_show_street_label"
                                value="1"
                                <?php checked(1 === (int) ($map_preview_show_street_label ?? 1)); ?>
                            />
                            <span class="feu-admin-settings-check-copy">
                                <strong><?php esc_html_e('Strassen-Label an der Markierung', 'feuer-einsatzberichte'); ?></strong>
                                <small><?php esc_html_e('Blendet direkt an der markierten Strasse ein zusaetzliches Label ein.', 'feuer-einsatzberichte'); ?></small>
                            </span>
                        </label>
                    </div>

                    <label class="feu-admin-settings-field">
                        <span><?php esc_html_e('Text vor dem Strassennamen', 'feuer-einsatzberichte'); ?></span>
                        <input
                            type="text"
                            id="feu_einsatz_map_preview_street_label_prefix"
                            name="feu_einsatz_map_preview_street_label_prefix"
                            value="<?php echo esc_attr(isset($map_preview_street_label_prefix) ? (string) $map_preview_street_label_prefix : 'Einsatz Strasse'); ?>"
                            class="regular-text"
                            placeholder="<?php esc_attr_e('Einsatz Strasse', 'feuer-einsatzberichte'); ?>"
                        />
                        <small><?php esc_html_e('Beispiel: Einsatz Strasse: Beispielstrasse', 'feuer-einsatzberichte'); ?></small>
                    </label>

                    <div class="feu-admin-settings-check-grid">
                        <label class="feu-admin-settings-check-card">
                            <input type="checkbox" id="feu_einsatz_map_preview_show_attribution" name="feu_einsatz_map_preview_show_attribution" value="1" <?php checked(1 === (int) $map_preview_show_attribution); ?> />
                            <span class="feu-admin-settings-check-copy">
                                <strong><?php esc_html_e('Karten-Copyright anzeigen', 'feuer-einsatzberichte'); ?></strong>
                                <small><?php esc_html_e('Empfohlen aktiv lassen, damit OpenStreetMap korrekt sichtbar genannt wird.', 'feuer-einsatzberichte'); ?></small>
                            </span>
                        </label>
                    </div>

                    <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Copyright-Text', 'feuer-einsatzberichte'); ?></span>
                            <input
                                type="text"
                                id="feu_einsatz_map_preview_attribution_text"
                                name="feu_einsatz_map_preview_attribution_text"
                                value="<?php echo esc_attr($map_preview_attribution_text); ?>"
                                class="regular-text"
                            />
                        </label>
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Position des Copyrights', 'feuer-einsatzberichte'); ?></span>
                            <select id="feu_einsatz_map_preview_attribution_position" name="feu_einsatz_map_preview_attribution_position">
                                <?php foreach ($map_preview_position_options as $position_key => $position_label) : ?>
                                    <option value="<?php echo esc_attr((string) $position_key); ?>" <?php selected($map_preview_attribution_position, (string) $position_key); ?>><?php echo esc_html((string) $position_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <label class="feu-admin-settings-field">
                        <span><?php esc_html_e('Stil des Strassen-Labels', 'feuer-einsatzberichte'); ?></span>
                        <select id="feu_einsatz_map_label_style" name="feu_einsatz_map_label_style">
                            <option value="bubble" <?php selected('bubble', isset($map_label_style) ? (string) $map_label_style : 'bubble'); ?>><?php esc_html_e('Sprechblase', 'feuer-einsatzberichte'); ?></option>
                            <option value="badge" <?php selected('badge', isset($map_label_style) ? (string) $map_label_style : 'bubble'); ?>><?php esc_html_e('Badge', 'feuer-einsatzberichte'); ?></option>
                            <option value="plain" <?php selected('plain', isset($map_label_style) ? (string) $map_label_style : 'bubble'); ?>><?php esc_html_e('Nur Text', 'feuer-einsatzberichte'); ?></option>
                        </select>
                    </label>
                </div>

                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h4><?php esc_html_e('Farben, Linie und Schrift', 'feuer-einsatzberichte'); ?></h4>
                    </div>

                    <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Textfarbe des Strassen-Labels', 'feuer-einsatzberichte'); ?></span>
                            <input
                                type="color"
                                id="feu_einsatz_map_label_text_color"
                                name="feu_einsatz_map_label_text_color"
                                value="<?php echo esc_attr(isset($map_label_text_color) && sanitize_hex_color($map_label_text_color) ? strtolower((string) $map_label_text_color) : '#ffffff'); ?>"
                            />
                        </label>
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Farbe der Strassenmarkierung', 'feuer-einsatzberichte'); ?></span>
                            <input
                                type="color"
                                id="feu_einsatz_map_preview_highlight_color"
                                name="feu_einsatz_map_preview_highlight_color"
                                value="<?php echo esc_attr(isset($map_preview_highlight_color) && sanitize_hex_color($map_preview_highlight_color) ? strtolower((string) $map_preview_highlight_color) : '#d92d20'); ?>"
                            />
                        </label>
                        <label class="feu-admin-settings-field feu-admin-settings-field--full">
                            <span><?php esc_html_e('Schrift im Kartenbild', 'feuer-einsatzberichte'); ?></span>
                            <select id="feu_einsatz_map_preview_font_family" name="feu_einsatz_map_preview_font_family">
                                <?php foreach ((array) ($map_preview_font_options ?? []) as $font_key => $font_definition) : ?>
                                    <option
                                        value="<?php echo esc_attr((string) $font_key); ?>"
                                        data-feu-map-font-css="<?php echo esc_attr((string) ($font_definition['css_stack'] ?? '"Segoe UI", Arial, sans-serif')); ?>"
                                        <?php selected((string) ($map_preview_font_family ?? 'auto'), (string) $font_key); ?>
                                    >
                                        <?php echo esc_html((string) ($font_definition['label'] ?? $font_key)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small><?php esc_html_e('Wenn die bevorzugte Schrift auf dem Server fehlt, nutzt das Plugin automatisch die naechste verfuegbare Alternative.', 'feuer-einsatzberichte'); ?></small>
                        </label>
                    </div>

                    <div class="feu-admin-settings-range-grid">
                        <label class="feu-admin-settings-range-card">
                            <span><?php esc_html_e('Staerke der Strassenmarkierung', 'feuer-einsatzberichte'); ?></span>
                            <input
                                type="range"
                                id="feu_einsatz_map_preview_stroke_width"
                                name="feu_einsatz_map_preview_stroke_width"
                                min="3"
                                max="18"
                                step="1"
                                value="<?php echo esc_attr(isset($map_preview_stroke_width) ? (int) $map_preview_stroke_width : 8); ?>"
                            />
                            <strong><span data-feu-map-preview-stroke-value><?php echo esc_html((string) (isset($map_preview_stroke_width) ? (int) $map_preview_stroke_width : 8)); ?></span> px</strong>
                        </label>
                    </div>
                </div>
            </div>

            <div class="feu-admin-settings-fieldset feu-admin-settings-fieldset--preview">
                <div class="feu-admin-settings-fieldset-head">
                    <h4><?php esc_html_e('Live-Vorschau Kartenbild', 'feuer-einsatzberichte'); ?></h4>
                    <p class="description"><?php esc_html_e('Die Vorschau verwendet standardmaessig die Feuerwehrhaus-Adresse als Beispiel und zeigt live, wie Strassenmarkierung, Panel-Text und Attribution im Kartenbild aussehen.', 'feuer-einsatzberichte'); ?></p>
                </div>

                <div
                    class="feu-einsatz-map-live-preview"
                    data-feu-map-preview-root
                    style="--feu-map-preview-line-color: <?php echo esc_attr(isset($map_preview_highlight_color) && sanitize_hex_color($map_preview_highlight_color) ? strtolower((string) $map_preview_highlight_color) : '#d92d20'); ?>; --feu-map-preview-line-width: <?php echo esc_attr(isset($map_preview_stroke_width) ? (int) $map_preview_stroke_width : 8); ?>px; --feu-map-preview-font-stack: <?php echo esc_attr((string) ($selected_map_preview_font_definition['css_stack'] ?? '"Segoe UI", Arial, sans-serif')); ?>; --feu-map-preview-height: <?php echo esc_attr(max(320, min(680, (int) $map_height))); ?>px;"
                >
                    <div class="feu-einsatz-map-live-preview-head">
                        <div>
                            <strong><?php esc_html_e('Aktuelle Vorschau', 'feuer-einsatzberichte'); ?></strong>
                            <p><?php esc_html_e('Zoom, Hoehe, Linienbreite, Schrift und Attribution werden hier sofort sichtbar.', 'feuer-einsatzberichte'); ?></p>
                        </div>
                        <div class="feu-einsatz-map-live-preview-meta">
                            <span><strong><?php esc_html_e('Zoom', 'feuer-einsatzberichte'); ?>:</strong> <span data-feu-map-preview-zoom><?php echo esc_html((string) $map_zoom); ?></span></span>
                            <span><strong><?php esc_html_e('Hoehe', 'feuer-einsatzberichte'); ?>:</strong> <span data-feu-map-preview-height-value><?php echo esc_html((string) $map_height); ?></span> px</span>
                            <span><?php esc_html_e('Hausnummer bleibt ausgeblendet', 'feuer-einsatzberichte'); ?></span>
                        </div>
                    </div>

                    <div class="feu-einsatz-map-live-preview-canvas">
                        <div
                            class="feu-einsatz-map-live-preview-stage"
                            data-feu-map-preview-stage
                            data-feu-map-preview-center-lat="<?php echo esc_attr(number_format($live_preview_center_lat, 6, '.', '')); ?>"
                            data-feu-map-preview-center-lng="<?php echo esc_attr(number_format($live_preview_center_lng, 6, '.', '')); ?>"
                            data-feu-map-preview-street="<?php echo esc_attr($live_preview_station_street); ?>"
                            data-feu-map-preview-postcode="<?php echo esc_attr($live_preview_station_postcode); ?>"
                            data-feu-map-preview-city="<?php echo esc_attr($live_preview_station_city); ?>"
                            data-feu-map-preview-geometry="<?php echo esc_attr(false !== $live_preview_geometry_json ? $live_preview_geometry_json : '[]'); ?>"
                            data-feu-map-preview-station-label="<?php echo esc_attr($live_preview_station_label); ?>"
                            data-feu-map-preview-station-logo="<?php echo esc_url($live_preview_station_logo); ?>"
                            data-feu-map-preview-station-logo-size="<?php echo esc_attr((string) $live_preview_station_logo_size); ?>"
                        >
                            <div class="feu-einsatz-map-live-preview-map" data-feu-map-preview-map></div>
                            <div class="feu-einsatz-map-live-preview-fallback" data-feu-map-preview-fallback>
                                <?php echo $live_preview_fallback_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                            <div class="feu-einsatz-map-live-preview-overlay" data-feu-map-preview-overlay></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h3><?php esc_html_e('Feuerwehrhaus und Einsatzgebiet', 'feuer-einsatzberichte'); ?></h3>
                    <p class="description"><?php esc_html_e('Adresse, Logo und oeffentliche Einsatzgebiet-Seite werden hier zentral gepflegt.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h4><?php esc_html_e('Seite Einsatzgebiet', 'feuer-einsatzberichte'); ?></h4>
                    </div>

                    <div class="feu-admin-settings-check-grid">
                        <label class="feu-admin-settings-check-card">
                            <input
                                type="checkbox"
                                id="feu_einsatz_area_page_enabled"
                                name="feu_einsatz_area_page_enabled"
                                value="1"
                                <?php checked($area_page_enabled, 1); ?>
                            />
                            <span class="feu-admin-settings-check-copy">
                                <strong><?php esc_html_e('Seite aktivieren', 'feuer-einsatzberichte'); ?></strong>
                                <small><?php esc_html_e('Die oeffentliche Seite Einsatzgebiet wird nur dort angezeigt, wo der Shortcode eingebunden wurde.', 'feuer-einsatzberichte'); ?></small>
                            </span>
                        </label>
                        <label class="feu-admin-settings-check-card">
                            <input
                                type="checkbox"
                                id="feu_einsatz_area_show_calls"
                                name="feu_einsatz_area_show_calls"
                                value="1"
                                <?php checked($area_show_calls, 1); ?>
                            />
                            <span class="feu-admin-settings-check-copy">
                                <strong><?php esc_html_e('Einsatz-Zonen anzeigen', 'feuer-einsatzberichte'); ?></strong>
                                <small><?php esc_html_e('Zeigt zusaetzlich Kreise mit Einsatzanzahl und Strassen beim Hover an.', 'feuer-einsatzberichte'); ?></small>
                            </span>
                        </label>
                    </div>

                    <label class="feu-admin-settings-field feu-admin-settings-field--important">
                        <span><?php esc_html_e('PLZ / Einsatzbereiche in denen gearbeitet wird', 'feuer-einsatzberichte'); ?></span>
                        <textarea
                            id="feu_einsatz_area_postcodes"
                            name="feu_einsatz_area_postcodes"
                            rows="6"
                            class="large-text code feu-admin-settings-textarea-code"
                        ><?php echo esc_textarea(implode("\n", isset($area_entries) && is_array($area_entries) ? $area_entries : [])); ?></textarea>
                        <small><?php esc_html_e('Eine Zeile pro Bereich. Trage hier die PLZ oder Stadtteile ein, in denen die Feuerwehr arbeitet. Beispiele: 22547, 22549, Lurup, Osdorf.', 'feuer-einsatzberichte'); ?></small>
                    </label>
                </div>

                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h4><?php esc_html_e('Adresse und Logo des Feuerwehrhauses', 'feuer-einsatzberichte'); ?></h4>
                    </div>

                    <label class="feu-admin-settings-field">
                        <span><?php esc_html_e('Strasse und Hausnummer', 'feuer-einsatzberichte'); ?></span>
                        <input
                            type="text"
                            id="feu_einsatz_area_station_street"
                            name="feu_einsatz_area_station_street"
                            value="<?php echo esc_attr($area_station_street); ?>"
                            class="regular-text"
                            placeholder="<?php esc_attr_e('Strasse und Hausnummer', 'feuer-einsatzberichte'); ?>"
                        />
                    </label>

                    <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('PLZ', 'feuer-einsatzberichte'); ?></span>
                            <input
                                type="text"
                                id="feu_einsatz_area_station_postcode"
                                name="feu_einsatz_area_station_postcode"
                                value="<?php echo esc_attr($area_station_postcode); ?>"
                                class="small-text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                placeholder="<?php esc_attr_e('PLZ', 'feuer-einsatzberichte'); ?>"
                            />
                        </label>
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Ort', 'feuer-einsatzberichte'); ?></span>
                            <input
                                type="text"
                                id="feu_einsatz_area_station_city"
                                name="feu_einsatz_area_station_city"
                                value="<?php echo esc_attr($area_station_city); ?>"
                                class="regular-text"
                                placeholder="<?php esc_attr_e('Ort', 'feuer-einsatzberichte'); ?>"
                            />
                        </label>
                    </div>

                    <div class="feu-admin-settings-media-card">
                        <label for="feu_einsatz_area_station_logo_id"><strong><?php esc_html_e('Logo Feuerwehrhaus', 'feuer-einsatzberichte'); ?></strong></label>
                        <input
                            type="hidden"
                            id="feu_einsatz_area_station_logo_id"
                            name="feu_einsatz_area_station_logo_id"
                            value="<?php echo esc_attr($area_station_logo_id); ?>"
                        />
                        <div class="feu-einsatz-watermark-image-panel feu-einsatz-social-share-image-panel">
                            <div id="feu-einsatz-area-station-logo-preview" class="feu-einsatz-watermark-image-preview feu-einsatz-settings-station-logo-preview">
                                <?php if (!empty($area_station_logo_url)) : ?>
                                    <img src="<?php echo esc_url($area_station_logo_url); ?>" alt="" class="feu-einsatz-watermark-preview-image" />
                                <?php else : ?>
                                    <div class="feu-einsatz-watermark-placeholder"><?php esc_html_e('Noch kein Feuerwehr-Logo ausgewaehlt.', 'feuer-einsatzberichte'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="feu-einsatz-watermark-actions">
                                <button type="button" class="button" id="feu-einsatz-area-station-logo-select"><?php esc_html_e('Logo auswaehlen', 'feuer-einsatzberichte'); ?></button>
                                <button type="button" class="button" id="feu-einsatz-area-station-logo-remove" <?php echo $area_station_logo_id ? '' : 'hidden'; ?>><?php esc_html_e('Logo entfernen', 'feuer-einsatzberichte'); ?></button>
                            </div>
                        </div>
                    </div>

                    <div class="feu-admin-settings-range-grid">
                        <label class="feu-admin-settings-range-card">
                            <span><?php esc_html_e('Logo-Groesse', 'feuer-einsatzberichte'); ?></span>
                            <input
                                type="range"
                                id="feu_einsatz_area_station_logo_size"
                                name="feu_einsatz_area_station_logo_size"
                                min="20"
                                max="96"
                                step="2"
                                value="<?php echo esc_attr($area_station_logo_size); ?>"
                            />
                            <strong><span data-feu-station-logo-size-value><?php echo esc_html((string) $area_station_logo_size); ?></span> px</strong>
                        </label>
                    </div>
                </div>
            </div>
        </section>

        <section class="feu-admin-settings-surface">
            <div class="feu-admin-settings-surface-head">
                <div>
                    <h3><?php esc_html_e('Wartung und Neuaufbau', 'feuer-einsatzberichte'); ?></h3>
                    <p class="description"><?php esc_html_e('Werkzeuge fuer Cache, Geometrien und die Neuerstellung vorhandener Kartenbilder.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>

            <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h4><?php esc_html_e('Strassen-Cache', 'feuer-einsatzberichte'); ?></h4>
                    </div>

                    <p class="feu-admin-settings-inline-note">
                        <?php
                        printf(
                            esc_html__('Aktuell zwischengespeicherte Strassen: %d', 'feuer-einsatzberichte'),
                            intval($street_cache_entries)
                        );
                        ?>
                    </p>
                    <p class="description"><?php esc_html_e('Leert den gemeinsamen Strassen-Cache und alle zwischengespeicherten Geometrien in den Einsatzberichten. Danach werden Karten beim naechsten Aufruf neu aufgebaut.', 'feuer-einsatzberichte'); ?></p>
                    <div class="feu-admin-settings-action-stack">
                        <button type="submit" name="feu_einsatz_clear_street_cache" value="1" class="button">
                            <?php esc_html_e('Strassen-Cache leeren', 'feuer-einsatzberichte'); ?>
                        </button>
                    </div>
                </div>

                <div class="feu-admin-settings-fieldset">
                    <div class="feu-admin-settings-fieldset-head">
                        <h4><?php esc_html_e('Automatisch erzeugte Kartenbilder', 'feuer-einsatzberichte'); ?></h4>
                    </div>

                    <p class="feu-admin-settings-inline-note">
                        <?php
                        printf(
                            esc_html__('Einsatzberichte mit automatisch erzeugtem Kartenbild: %d', 'feuer-einsatzberichte'),
                            intval(isset($generated_map_rebuild_candidates) ? $generated_map_rebuild_candidates : 0)
                        );
                        ?>
                    </p>
                    <p class="description"><?php esc_html_e('Setzt automatisch erzeugte Kartenbilder und Kartenvorschauen im Hintergrund neu auf. Bereits manuell gesetzte Beitragsbilder bleiben unveraendert.', 'feuer-einsatzberichte'); ?></p>

                    <div class="feu-admin-settings-form-grid feu-admin-settings-form-grid--2">
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Zeitraum von', 'feuer-einsatzberichte'); ?></span>
                            <input
                                type="date"
                                id="feu_einsatz_generated_map_rebuild_from"
                                name="feu_einsatz_generated_map_rebuild_from"
                                value="<?php echo esc_attr($generated_map_rebuild_from); ?>"
                            />
                        </label>
                        <label class="feu-admin-settings-field">
                            <span><?php esc_html_e('Zeitraum bis', 'feuer-einsatzberichte'); ?></span>
                            <input
                                type="date"
                                id="feu_einsatz_generated_map_rebuild_to"
                                name="feu_einsatz_generated_map_rebuild_to"
                                value="<?php echo esc_attr($generated_map_rebuild_to); ?>"
                            />
                        </label>
                    </div>

                    <p class="description"><?php esc_html_e('Leer bedeutet: alle automatisch erzeugten Kartenbilder. Mit Zeitraum werden nur Einsatzberichte innerhalb dieses Datumsbereichs neu vorgemerkt.', 'feuer-einsatzberichte'); ?></p>
                    <div class="feu-admin-settings-action-stack">
                        <button type="submit" name="feu_einsatz_rebuild_generated_map_images" value="1" class="button button-secondary">
                            <?php esc_html_e('Automatische Kartenbilder neu aufbauen', 'feuer-einsatzberichte'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
