<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$map = isset($context['map']) && is_array($context['map']) ? $context['map'] : [];
$display = isset($context['display']) && is_array($context['display']) ? $context['display'] : [];
$map_display_mode = isset($display['single_map_display_mode'])
    ? FEU_Einsatz_Template_Helpers::normalize_single_map_display_mode($display['single_map_display_mode'])
    : 'live';
$map_privacy_mode = isset($display['single_map_privacy_mode'])
    ? FEU_Einsatz_Template_Helpers::normalize_single_map_privacy_mode($display['single_map_privacy_mode'])
    : 'always';
$has_live_data = !empty($map['has_live_data']);
$cookie_map_integration_active = FEU_Einsatz_Template_Helpers::has_cookie_map_consent_integration();
$cookie_map_consent_granted = !$cookie_map_integration_active || FEU_Einsatz_Template_Helpers::has_cookie_map_consent();
$show_live_map = 'live' === $map_display_mode && $has_live_data && (!$cookie_map_integration_active || $cookie_map_consent_granted);
$post_image_attachment_id = isset($map['post_image_attachment_id']) ? absint($map['post_image_attachment_id']) : 0;
$post_image_url = isset($map['post_image_url']) ? trim((string) $map['post_image_url']) : '';
$post_image_alt = isset($map['post_image_alt']) ? trim((string) $map['post_image_alt']) : '';
$post_image_available = $post_image_attachment_id > 0 || '' !== $post_image_url;

if ('disabled' === $map_display_mode) {
    return;
}

ob_start();
if ($post_image_attachment_id > 0) :
    echo wp_get_attachment_image(
        $post_image_attachment_id,
        'large',
        false,
        [
            'class' => 'feu-einsatz-map-fallback-image',
            'loading' => 'lazy',
            'decoding' => 'async',
            'fetchpriority' => 'low',
        ]
    );
elseif ('' !== $post_image_url) : ?>
    <img src="<?php echo esc_url($post_image_url); ?>"
         alt="<?php echo esc_attr($post_image_alt); ?>"
         class="feu-einsatz-map-fallback-image"
         loading="lazy"
         decoding="async"
         fetchpriority="low" />
<?php else : ?>
    <div class="feu-einsatz-map-placeholder">
        <strong><?php echo esc_html__('Kein Einsatzfoto verfuegbar.', 'feuer-einsatzberichte'); ?></strong>
        <p><?php echo esc_html__('Fuer diesen Einsatz ist weder ein Beitragsbild noch ein Foto in der Galerie hinterlegt, das statt der Karte gezeigt werden kann.', 'feuer-einsatzberichte'); ?></p>
    </div>
<?php endif;
$post_image_markup = (string) ob_get_clean();

ob_start();
if (!empty($map['preview_markup'])) :
    echo $map['preview_markup'];
elseif (!empty($map['fallback_image_url'])) : ?>
    <img src="<?php echo esc_url($map['fallback_image_url']); ?>"
         alt="<?php echo esc_attr($map['fallback_alt'] ?? ''); ?>"
         class="feu-einsatz-map-fallback-image" />
<?php else : ?>
    <div class="feu-einsatz-map-placeholder">
        <strong><?php echo esc_html__('Kartenansicht nicht verfuegbar.', 'feuer-einsatzberichte'); ?></strong>
        <p><?php echo esc_html__('Fuer diesen Einsatz sind noch keine ausreichenden Kartendaten gespeichert.', 'feuer-einsatzberichte'); ?></p>
        <?php if (isset($map['latitude'], $map['longitude']) && is_numeric($map['latitude']) && is_numeric($map['longitude'])) : ?>
            <p class="feu-einsatz-map-placeholder-coordinates">
                <?php echo esc_html(number_format((float) ($map['latitude'] ?? 0), 6, '.', '') . ', ' . number_format((float) ($map['longitude'] ?? 0), 6, '.', '')); ?>
            </p>
        <?php endif; ?>
    </div>
<?php endif;
$preview_markup = (string) ob_get_clean();

$runtime_classes = 'feu-einsatz-map-container h-100 feu-einsatz-map-runtime';

if ($show_live_map) {
    $runtime_classes .= ' has-live-map';
}
?>

<?php echo FEU_Einsatz_Template_Helpers::render_cookie_macro('header'); ?>
<div class="col-12 col-lg-7 feu-einsatz-single-map-col d-flex">
    <div class="<?php echo esc_attr($runtime_classes); ?>"
         data-map-display-mode="<?php echo esc_attr($map_display_mode); ?>"
         data-map-privacy-mode="<?php echo esc_attr($cookie_map_integration_active ? 'always' : $map_privacy_mode); ?>"
         data-map-live-available="<?php echo $show_live_map ? '1' : '0'; ?>"
         data-map-enabled="<?php echo $show_live_map ? '1' : '0'; ?>">
        <?php if ($show_live_map) : ?>
            <div class="feu-einsatz-map-load-bar"
                 role="progressbar"
                 aria-valuemin="0"
                 aria-valuemax="100"
                 aria-valuenow="0"
                 aria-hidden="true">
                <div class="feu-einsatz-map-load-bar-fill"></div>
            </div>
            <div id="<?php echo esc_attr($map['canvas_id'] ?? ''); ?>"
                 class="feu-einsatz-live-map"
                 style="height: <?php echo esc_attr((int) ($map['height'] ?? 500)); ?>px;"></div>
            <script type="application/json" class="feu-einsatz-map-config"><?php echo wp_json_encode($map['config'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
        <?php endif; ?>

        <div class="feu-einsatz-map-runtime-fallback">
            <?php if ('image' === $map_display_mode) : ?>
                <div class="feu-einsatz-map-privacy-preview is-visible">
                    <?php echo $post_image_available ? $post_image_markup : $preview_markup; ?>
                </div>
            <?php elseif ('live' === $map_display_mode && $has_live_data && $cookie_map_integration_active && !$cookie_map_consent_granted) : ?>
                <div class="feu-einsatz-map-privacy-preview is-visible" data-feu-map-preview>
                    <?php echo $post_image_markup; ?>
                </div>
            <?php elseif ('live' === $map_display_mode && $has_live_data && 'always' !== $map_privacy_mode) : ?>
                <div class="feu-einsatz-map-consent" data-feu-map-consent>
                    <strong><?php echo esc_html__('Datenschutz-Hinweis', 'feuer-einsatzberichte'); ?></strong>
                    <p><?php echo esc_html__('Beim Laden der Live-Karte werden externe Kartendaten von OpenStreetMap nachgeladen.', 'feuer-einsatzberichte'); ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-danger btn-sm" data-feu-map-action="allow">
                            <?php echo esc_html__('Live-Karte laden', 'feuer-einsatzberichte'); ?>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-feu-map-action="decline">
                            <?php echo esc_html__('Nicht laden', 'feuer-einsatzberichte'); ?>
                        </button>
                    </div>
                </div>

                <div class="feu-einsatz-map-privacy-preview" data-feu-map-preview hidden>
                    <?php if ('consent_image' === $map_privacy_mode && $post_image_available) : ?>
                        <?php echo $post_image_markup; ?>
                    <?php endif; ?>
                </div>

                <div class="feu-einsatz-map-privacy-rejected" data-feu-map-rejected hidden>
                    <div class="feu-einsatz-map-placeholder">
                        <strong><?php echo esc_html__('Karte nicht geladen', 'feuer-einsatzberichte'); ?></strong>
                        <p><?php echo esc_html__('Sie haben das Nachladen externer Kartendaten abgelehnt.', 'feuer-einsatzberichte'); ?></p>
                    </div>
                </div>
            <?php else : ?>
                <?php echo $preview_markup; ?>
            <?php endif; ?>
        </div>

        <div class="feu-einsatz-map-note">
            <?php echo esc_html__('Hier ist die Strasse markiert, auf der sich der Einsatz ereignet hat. Aus Datenschutzgruenden zeigen wir bewusst nicht den exakten Ereignisort.', 'feuer-einsatzberichte'); ?>
        </div>
    </div>
</div>
<?php echo FEU_Einsatz_Template_Helpers::render_cookie_macro('footer'); ?>