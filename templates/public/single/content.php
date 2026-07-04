<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$report = isset($context['report']) && is_array($context['report']) ? $context['report'] : [];
?>

<?php if (!empty($report['content'])) : ?>
    <div class="card feu-einsatz-single-content-card">
        <div class="card-header">
            <?php echo esc_html__('Beschreibung', 'feuer-einsatzberichte'); ?>
        </div>
        <div class="card-body">
            <div class="feu-einsatz-single-content"><?php echo apply_filters('the_content', $report['content']); ?></div>
        </div>
    </div>
<?php endif; ?>