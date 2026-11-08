<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$display = isset($context['display']) && is_array($context['display']) ? $context['display'] : [];
$map = isset($context['map']) && is_array($context['map']) ? $context['map'] : [];
$map_display_mode = FEU_Einsatz_Template_Helpers::normalize_single_map_display_mode($display['single_map_display_mode'] ?? 'live');
$has_public_map = 'disabled' !== $map_display_mode
    && empty($map['publicly_hidden']);
?>

<?php if ($has_public_map) : ?>
    <aside class="feuer-call-detail-notice" aria-labelledby="feuer-call-detail-notice-title">
        <h2 id="feuer-call-detail-notice-title"><?php echo esc_html__('Hinweis', 'feuer-einsatzberichte'); ?></h2>
        <p><?php echo esc_html__('Hier ist die Straße markiert, auf der sich der Einsatz ereignet hat. Aus Datenschutzgründen zeigen wir bewusst nicht den exakten Ereignisort.', 'feuer-einsatzberichte'); ?></p>
    </aside>
<?php endif; ?>
