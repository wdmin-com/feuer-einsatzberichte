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
$stadtteil = get_post_meta($post->ID, '_feu_einsatz_stadtteil', true);
$datum = get_post_meta($post->ID, '_feu_einsatz_datum', true);
$uhrzeit = get_post_meta($post->ID, '_feu_einsatz_uhrzeit', true);
$map_location_mode = class_exists('FEU_Einsatz_Template_Helpers')
    ? FEU_Einsatz_Template_Helpers::get_report_map_location_mode($post->ID)
    : 'address';
$map_highlight_override = class_exists('FEU_Einsatz_Template_Helpers')
    ? (string) get_post_meta($post->ID, FEU_Einsatz_Template_Helpers::MAP_HIGHLIGHT_OVERRIDE_META, true)
    : 'default';
$map_highlight_override = in_array($map_highlight_override, ['full', 'length', 'radius'], true) ? $map_highlight_override : 'default';
$map_highlight_length = class_exists('FEU_Einsatz_Template_Helpers')
    ? max(20, min(5000, absint(get_post_meta($post->ID, FEU_Einsatz_Template_Helpers::MAP_HIGHLIGHT_LENGTH_META, true)) ?: 100))
    : 100;
$map_highlight_radius = class_exists('FEU_Einsatz_Template_Helpers')
    ? max(20, min(5000, absint(get_post_meta($post->ID, FEU_Einsatz_Template_Helpers::MAP_HIGHLIGHT_RADIUS_META, true)) ?: 100))
    : 100;
$map_latitude = get_post_meta($post->ID, '_feu_einsatz_latitude', true);
$map_longitude = get_post_meta($post->ID, '_feu_einsatz_longitude', true);
$map_extra_streets = class_exists('FEU_Einsatz_Template_Helpers')
    ? FEU_Einsatz_Template_Helpers::get_report_map_extra_streets($post->ID)
    : [];
$map_area_geojson = class_exists('FEU_Einsatz_Template_Helpers')
    ? FEU_Einsatz_Template_Helpers::get_report_map_area_geojson($post->ID)
    : [];
$map_public_precision = class_exists('FEU_Einsatz_Template_Helpers')
    ? FEU_Einsatz_Template_Helpers::get_report_map_public_precision($post->ID)
    : 'exact';
$map_history = get_post_meta($post->ID, class_exists('FEU_Einsatz_Template_Helpers') ? FEU_Einsatz_Template_Helpers::MAP_HISTORY_META : '_feu_einsatz_map_history', true);
$map_history = is_array($map_history) ? array_slice($map_history, 0, 5) : [];
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
$street_highlight_settings = class_exists('FEU_Einsatz_Template_Helpers')
    ? FEU_Einsatz_Template_Helpers::get_report_street_highlight_settings($post->ID)
    : ['mode' => 'full', 'length_meters' => 100, 'radius_meters' => 100];
$street_highlight_mode = (string) ($street_highlight_settings['mode'] ?? 'full');
$street_highlight_mode_label = 'full' === $street_highlight_mode
    ? __('Ganze Strasse', 'feuer-einsatzberichte')
    : ('radius' === $street_highlight_mode
        ? sprintf(__('%d m Radius', 'feuer-einsatzberichte'), (int) ($street_highlight_settings['radius_meters'] ?? 100))
        : sprintf(__('%d m Strassenabschnitt', 'feuer-einsatzberichte'), (int) ($street_highlight_settings['length_meters'] ?? 100)));
?>

<div class="feu-einsatz-meta-box<?php echo $use_modern_form ? ' feu-einsatz-meta-box--modern' : ''; ?>">
    <?php if ($show_basic_fields) : ?>
        <div class="feu-einsatz-map-profile" data-feu-map-profile>
            <div class="feu-einsatz-map-profile-head">
                <div>
                    <span class="feu-einsatz-map-profile-kicker"><?php esc_html_e('Einsatzort & Karte', 'feuer-einsatzberichte'); ?></span>
                    <h4><?php esc_html_e('Ort für diesen Einsatz festlegen', 'feuer-einsatzberichte'); ?></h4>
                    <p class="description"><?php esc_html_e('Standardmäßig nutzt der Bericht die allgemeinen Kartenregeln. Bei besonderen Lagen kannst du Ort und Kartenmarkierung nur für diesen Einsatz anpassen.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>
            <div class="feu-einsatz-location-mode-tabs" role="radiogroup" aria-label="<?php esc_attr_e('Art des Einsatzorts', 'feuer-einsatzberichte'); ?>">
                <label class="feu-einsatz-location-mode-tab">
                    <input type="radio" name="feu_einsatz_map_location_mode" value="address" <?php checked($map_location_mode, 'address'); ?> />
                    <span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
                    <span><strong><?php esc_html_e('Adresse', 'feuer-einsatzberichte'); ?></strong><small><?php esc_html_e('Straße, Hausnummer und Ort verwenden', 'feuer-einsatzberichte'); ?></small></span>
                </label>
                <label class="feu-einsatz-location-mode-tab">
                    <input type="radio" name="feu_einsatz_map_location_mode" value="coordinates" <?php checked($map_location_mode, 'coordinates'); ?> />
                    <span class="dashicons dashicons-location" aria-hidden="true"></span>
                    <span><strong><?php esc_html_e('Genaue Koordinaten', 'feuer-einsatzberichte'); ?></strong><small><?php esc_html_e('Für Wald-, Flächen- und Sonderlagen', 'feuer-einsatzberichte'); ?></small></span>
                </label>
            </div>
        </div>

        <div class="feu-einsatz-form-row feu-einsatz-form-grid feu-einsatz-form-grid--address" data-feu-location-panel="address">
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
                    <?php echo 'address' === $map_location_mode ? 'required' : ''; ?>
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

        <div class="feu-einsatz-form-row feu-einsatz-form-grid feu-einsatz-form-grid--location" data-feu-location-panel="address">
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
                    <?php echo 'address' === $map_location_mode ? 'required' : ''; ?>
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
                    <?php echo 'address' === $map_location_mode ? 'required' : ''; ?>
                />
            </div>

            <div class="feu-einsatz-field">
                <label class="feu-einsatz-field-label" for="feu_einsatz_stadtteil">
                    <?php esc_html_e('Stadtteil', 'feuer-einsatzberichte'); ?>
                </label>
                <input
                    type="text"
                    id="feu_einsatz_stadtteil"
                    name="feu_einsatz_stadtteil"
                    value="<?php echo esc_attr($stadtteil); ?>"
                    class="widefat"
                    placeholder="z.B. Lurup"
                    autocomplete="address-level3"
                />
                <p class="description feu-einsatz-field-hint">
                    <?php esc_html_e('Optional. Wird für Ortsangaben und zukünftige SEO-Texte verwendet, aber nicht für die Karten-Geokodierung.', 'feuer-einsatzberichte'); ?>
                </p>
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

        <div class="feu-einsatz-form-row feu-einsatz-map-profile-fields" data-feu-location-panel="coordinates" hidden>
            <div class="feu-einsatz-map-profile-fields-head">
                <span class="dashicons dashicons-location" aria-hidden="true"></span>
                <div>
                    <h4><?php esc_html_e('Genaue Einsatzkoordinaten', 'feuer-einsatzberichte'); ?></h4>
                    <p class="description"><?php esc_html_e('Die Koordinaten werden nicht durch die Adresssuche ersetzt. Für Flächenlagen eignet sich zusätzlich der Kartenmodus „Radius“.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>
            <div class="feu-einsatz-form-grid feu-einsatz-form-grid--coordinates">
                <div class="feu-einsatz-field">
                    <label class="feu-einsatz-field-label" for="feu_einsatz_latitude"><?php esc_html_e('Breitengrad', 'feuer-einsatzberichte'); ?></label>
                    <input type="text" id="feu_einsatz_latitude" name="feu_einsatz_latitude" value="<?php echo esc_attr($map_latitude); ?>" class="widefat" placeholder="z.B. 53,575320" inputmode="decimal" autocomplete="off" />
                </div>
                <div class="feu-einsatz-field">
                    <label class="feu-einsatz-field-label" for="feu_einsatz_longitude"><?php esc_html_e('Längengrad', 'feuer-einsatzberichte'); ?></label>
                    <input type="text" id="feu_einsatz_longitude" name="feu_einsatz_longitude" value="<?php echo esc_attr($map_longitude); ?>" class="widefat" placeholder="z.B. 9,993682" inputmode="decimal" autocomplete="off" />
                </div>
            </div>
            <p class="description feu-einsatz-field-hint">
                <?php esc_html_e('Komma und Punkt werden akzeptiert. Du kannst die Position auch unten direkt mit einem Klick in der Live-Karte setzen.', 'feuer-einsatzberichte'); ?>
            </p>
        </div>

        <div class="feu-einsatz-form-row feu-einsatz-map-profile-fields feu-einsatz-map-highlight-profile" data-feu-map-highlight-profile>
            <div class="feu-einsatz-map-profile-fields-head">
                <span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
                <div>
                    <h4><?php esc_html_e('Kartenmarkierung für diesen Einsatz', 'feuer-einsatzberichte'); ?></h4>
                    <p class="description"><?php esc_html_e('„Standard“ übernimmt die globale Einstellung. Eine Auswahl hier gilt ausschließlich für diesen Bericht und auch für das erzeugte Kartenbild.', 'feuer-einsatzberichte'); ?></p>
                </div>
            </div>
            <div class="feu-einsatz-map-highlight-controls">
                <label class="feu-einsatz-field">
                    <span class="feu-einsatz-field-label"><?php esc_html_e('Schnellprofil', 'feuer-einsatzberichte'); ?></span>
                    <select id="feu_einsatz_map_profile_preset" class="widefat" data-feu-map-profile-preset>
                        <option value="custom"><?php esc_html_e('Individuell / unverändert', 'feuer-einsatzberichte'); ?></option>
                        <option value="standard"><?php esc_html_e('Standard aus Einstellungen', 'feuer-einsatzberichte'); ?></option>
                        <option value="full"><?php esc_html_e('Ganze Straße', 'feuer-einsatzberichte'); ?></option>
                        <option value="segment_100"><?php esc_html_e('Verkehrsunfall – Abschnitt 100 m', 'feuer-einsatzberichte'); ?></option>
                        <option value="radius_500"><?php esc_html_e('Wald- / Flächenlage – Radius 500 m', 'feuer-einsatzberichte'); ?></option>
                        <option value="radius_1000"><?php esc_html_e('Großschaden – Radius 1.000 m', 'feuer-einsatzberichte'); ?></option>
                    </select>
                </label>
                <label class="feu-einsatz-field">
                    <span class="feu-einsatz-field-label"><?php esc_html_e('Darstellung', 'feuer-einsatzberichte'); ?></span>
                    <select name="feu_einsatz_map_highlight_override" id="feu_einsatz_map_highlight_override" class="widefat">
                        <option value="default" <?php selected($map_highlight_override, 'default'); ?>><?php printf(esc_html__('Standard übernehmen (%s)', 'feuer-einsatzberichte'), esc_html($street_highlight_mode_label)); ?></option>
                        <option value="full" <?php selected($map_highlight_override, 'full'); ?>><?php esc_html_e('Ganze Straße', 'feuer-einsatzberichte'); ?></option>
                        <option value="length" <?php selected($map_highlight_override, 'length'); ?>><?php esc_html_e('Straßenabschnitt', 'feuer-einsatzberichte'); ?></option>
                        <option value="radius" <?php selected($map_highlight_override, 'radius'); ?>><?php esc_html_e('Radius um Einsatzort', 'feuer-einsatzberichte'); ?></option>
                    </select>
                </label>
                <label class="feu-einsatz-field" data-feu-highlight-length-field hidden>
                    <span class="feu-einsatz-field-label"><?php esc_html_e('Länge des Abschnitts (m)', 'feuer-einsatzberichte'); ?></span>
                    <input type="number" min="20" max="5000" step="10" name="feu_einsatz_map_highlight_length_meters" id="feu_einsatz_map_highlight_length_meters" value="<?php echo esc_attr($map_highlight_length); ?>" class="widefat" />
                </label>
                <label class="feu-einsatz-field" data-feu-highlight-radius-field hidden>
                    <span class="feu-einsatz-field-label"><?php esc_html_e('Radius (m)', 'feuer-einsatzberichte'); ?></span>
                    <input type="number" min="20" max="5000" step="10" name="feu_einsatz_map_highlight_radius_meters" id="feu_einsatz_map_highlight_radius_meters" value="<?php echo esc_attr($map_highlight_radius); ?>" class="widefat" />
                </label>
            </div>
        </div>

        <details class="feu-einsatz-form-row feu-einsatz-map-advanced" data-feu-map-advanced>
            <summary><?php esc_html_e('Erweiterter Einsatzbereich, weitere Straßen und Datenschutz', 'feuer-einsatzberichte'); ?></summary>
            <div class="feu-einsatz-form-grid feu-einsatz-form-grid--map-advanced">
                <label class="feu-einsatz-field">
                    <span class="feu-einsatz-field-label"><?php esc_html_e('Weitere betroffene Straßen', 'feuer-einsatzberichte'); ?></span>
                    <textarea name="feu_einsatz_map_extra_streets" id="feu_einsatz_map_extra_streets" class="widefat" rows="3" placeholder="<?php esc_attr_e('Eine Straße pro Zeile', 'feuer-einsatzberichte'); ?>"><?php echo esc_textarea(implode("\n", $map_extra_streets)); ?></textarea>
                    <small><?php esc_html_e('Maximal fünf. Die Straßen werden zusätzlich markiert, sobald ihre reale OSM-Geometrie verfügbar ist.', 'feuer-einsatzberichte'); ?></small>
                </label>
                <label class="feu-einsatz-field">
                    <span class="feu-einsatz-field-label"><?php esc_html_e('Öffentliche Kartengenauigkeit', 'feuer-einsatzberichte'); ?></span>
                    <select name="feu_einsatz_map_public_precision" id="feu_einsatz_map_public_precision" class="widefat">
                        <option value="exact" <?php selected($map_public_precision, 'exact'); ?>><?php esc_html_e('Exakte Karte anzeigen', 'feuer-einsatzberichte'); ?></option>
                        <option value="approx_100" <?php selected($map_public_precision, 'approx_100'); ?>><?php esc_html_e('Ungefähr auf 100 m runden', 'feuer-einsatzberichte'); ?></option>
                        <option value="approx_500" <?php selected($map_public_precision, 'approx_500'); ?>><?php esc_html_e('Ungefähr auf 500 m runden', 'feuer-einsatzberichte'); ?></option>
                        <option value="hidden" <?php selected($map_public_precision, 'hidden'); ?>><?php esc_html_e('Öffentliche Karte nicht anzeigen', 'feuer-einsatzberichte'); ?></option>
                    </select>
                    <small><?php esc_html_e('Diese Einstellung schützt ausschließlich die Karte. Prüfe Beitragstitel und -text separat. Beim Ausblenden werden automatisch erzeugte Kartenbilder dieses Berichts entfernt.', 'feuer-einsatzberichte'); ?></small>
                </label>
            </div>
            <input type="hidden" id="feu_einsatz_map_area_geojson" name="feu_einsatz_map_area_geojson" value="<?php echo esc_attr(wp_json_encode($map_area_geojson)); ?>" />
            <div class="feu-einsatz-map-area-actions">
                <button type="button" class="button button-secondary" data-feu-map-area-start><?php esc_html_e('Bereich in Live-Karte zeichnen', 'feuer-einsatzberichte'); ?></button>
                <button type="button" class="button button-link-delete" data-feu-map-area-clear hidden><?php esc_html_e('Bereich entfernen', 'feuer-einsatzberichte'); ?></button>
                <span class="description" data-feu-map-area-status><?php esc_html_e('Optional: mindestens drei Punkte in der Live-Karte setzen und anschließend abschließen.', 'feuer-einsatzberichte'); ?></span>
            </div>
        </details>

        <div
            class="feu-einsatz-form-row feu-einsatz-inline-card feu-einsatz-inline-card--map-preview"
            data-feu-address-map-preview
            hidden
            aria-live="polite"
        >
            <div class="feu-einsatz-inline-card-head">
                <div>
                    <div class="feu-einsatz-map-preview-kicker">
                        <span class="dashicons dashicons-location-alt" aria-hidden="true"></span>
                        <?php esc_html_e('Live-Kartenvorschau', 'feuer-einsatzberichte'); ?>
                    </div>
                    <h4 class="feu-einsatz-inline-card-title"><?php esc_html_e('Markierung für diesen Einsatzort', 'feuer-einsatzberichte'); ?></h4>
                    <p class="description feu-einsatz-inline-card-copy" data-feu-address-map-preview-status><?php esc_html_e('Die Adresse wird geprüft und die Karte vorbereitet.', 'feuer-einsatzberichte'); ?></p>
                </div>
                <div class="feu-einsatz-map-preview-actions">
                    <span class="feu-einsatz-map-preview-mode" data-feu-address-map-preview-mode data-default-map-label="<?php echo esc_attr($street_highlight_mode_label); ?>" data-default-map-mode="<?php echo esc_attr($street_highlight_mode); ?>"><?php echo esc_html($street_highlight_mode_label); ?></span>
                    <button type="button" class="button button-secondary" data-feu-address-map-preview-refresh><?php esc_html_e('Karte aktualisieren', 'feuer-einsatzberichte'); ?></button>
                </div>
            </div>
            <div class="feu-einsatz-address-map-preview-canvas" data-feu-address-map-preview-canvas>
                <span class="feu-einsatz-address-map-preview-placeholder"><?php esc_html_e('Die Kartenansicht wird nach dem Prüfen der Einsatzdaten geladen.', 'feuer-einsatzberichte'); ?></span>
            </div>
            <div class="feu-einsatz-map-preview-diagnostics" data-feu-map-diagnostics hidden></div>
        </div>

        <?php if (!empty($map_history)) : ?>
            <details class="feu-einsatz-form-row feu-einsatz-map-history">
                <summary><?php esc_html_e('Letzte Änderungen an der Karte', 'feuer-einsatzberichte'); ?></summary>
                <ul>
                    <?php foreach ($map_history as $entry) : ?>
                        <li><?php echo esc_html(trim((string) ($entry['label'] ?? __('Kartenprofil geändert', 'feuer-einsatzberichte')) . ' · ' . (string) ($entry['changed_at'] ?? ''))); ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
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
                && in_array(($generated_map_status['status'] ?? 'idle'), ['queued', 'processing', 'error', 'privacy_hidden'], true)
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

            <?php if ('hidden' === $map_public_precision) : ?>
                <div class="notice inline notice-info">
                    <p><?php esc_html_e('Für diesen Bericht ist die öffentliche Karte deaktiviert. Es wird kein automatisches Kartenbild erstellt.', 'feuer-einsatzberichte'); ?></p>
                </div>
            <?php elseif ($is_new_report) : ?>
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
