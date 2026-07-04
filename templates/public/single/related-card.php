<?php
if (!defined('ABSPATH')) {
    exit;
}

$report = isset($report) && is_array($report) ? $report : [];
$category_name = '';

if (isset($report['category'])) {
    if ($report['category'] instanceof WP_Term) {
        $category_name = (string) $report['category']->name;
    } elseif (is_array($report['category']) && !empty($report['category']['name'])) {
        $category_name = (string) $report['category']['name'];
    }
}

$number_label = sprintf(
    '%s/%s',
    str_pad((string) ($report['number'] ?? 0), 2, '0', STR_PAD_LEFT),
    (string) ($report['year'] ?? '')
);
?>

<article class="card h-100 feu-einsatz-related-mini-card">
    <a href="<?php echo esc_url($report['permalink'] ?? ''); ?>"
       class="stretched-link feu-einsatz-report-link"
       data-feu-einsatz-open-report="1"
       aria-label="<?php echo esc_attr($report['title'] ?? ''); ?>"></a>

    <div class="d-flex gap-3">
        <div class="feu-einsatz-related-mini-thumb">
            <?php if (!empty($report['image_url'])) : ?>
                <img src="<?php echo esc_url($report['image_url']); ?>"
                     alt="<?php echo esc_attr($report['title'] ?? ''); ?>"
                     loading="lazy" />
            <?php else : ?>
                <span><?php echo esc_html__('FEU', 'feuer-einsatzberichte'); ?></span>
            <?php endif; ?>
        </div>

        <div class="feu-einsatz-related-mini-body">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <?php if ('' !== $category_name) : ?>
                    <span class="feu-einsatz-related-mini-kicker"><?php echo esc_html($category_name); ?></span>
                <?php endif; ?>
                <span class="feu-einsatz-related-mini-number">#<?php echo esc_html($number_label); ?></span>
            </div>

            <h3 class="h6 mb-2"><?php echo esc_html($report['title'] ?? ''); ?></h3>

            <div class="feu-einsatz-related-mini-meta">
                <span><?php echo esc_html($report['event_date'] ?? ''); ?></span>
                <?php if (!empty($report['event_time'])) : ?>
                    <span><?php echo esc_html($report['event_time']); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</article>
