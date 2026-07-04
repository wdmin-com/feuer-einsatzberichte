<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$posts = isset($context['posts']) && is_array($context['posts']) ? $context['posts'] : [];
$format = isset($context['latest_format']) ? (string) $context['latest_format'] : 'cards';
$selected_year = isset($context['selected_year']) ? (string) $context['selected_year'] : '';
$card_variant = FEU_Einsatz_Template_Helpers::normalize_report_card_variant(isset($context['card_variant']) ? $context['card_variant'] : 'modern');
$card_template = FEU_Einsatz_Template_Helpers::get_overview_report_card_template($card_variant);
?>

<?php if (!empty($context['warning'])) : ?>
    <div class="alert alert-warning">
        <?php echo esc_html($context['warning']); ?>
    </div>
<?php elseif (empty($posts)) : ?>
    <?php
    echo FEU_Einsatz_Template_Helpers::render('templates/public/overview/partials/empty.php', [
        'selected_year' => $selected_year,
    ]);
    ?>
<?php elseif ('compact' === $format) : ?>
    <div class="list-group shadow-sm feu-einsatz-overview-compact">
        <?php foreach ($posts as $report) : ?>
            <?php
            echo FEU_Einsatz_Template_Helpers::render('templates/public/overview/partials/compact-item.php', [
                'report' => $report,
            ]);
            ?>
        <?php endforeach; ?>
    </div>
<?php else : ?>
    <div class="einsatz-posts">
        <?php foreach ($posts as $index => $report) : ?>
            <?php
            echo FEU_Einsatz_Template_Helpers::render($card_template, [
                'report' => $report,
                'card_variant' => $card_variant,
                'image_loading' => 0 === (int) $index ? 'eager' : 'lazy',
                'image_fetchpriority' => 0 === (int) $index ? 'high' : 'auto',
            ]);
            ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>