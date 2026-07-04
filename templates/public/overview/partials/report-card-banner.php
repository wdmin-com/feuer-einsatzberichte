<?php
if (!defined('ABSPATH')) {
    exit;
}

$report = isset($report) && is_array($report) ? $report : [];
$banner_excerpt = !empty($report['excerpt']) ? wp_trim_words((string) $report['excerpt'], 22) : '';
$number_label = sprintf(
    '%s/%s',
    str_pad((string) $report['number'], 2, '0', STR_PAD_LEFT),
    (string) $report['year']
);
$background_style = !empty($report['image_url'])
    ? ' style="background-image:url(\'' . esc_url($report['image_url']) . '\');"'
    : '';
?>

<article class="card mb-4 shadow-sm overflow-hidden feu-einsatz-report-card feu-einsatz-report-card-banner<?php echo empty($report['image_url']) ? ' is-no-image' : ''; ?>"<?php echo $background_style; ?>>
    <div class="feu-einsatz-report-card-banner-overlay"></div>
    <div class="card-body p-4 p-lg-5 position-relative">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if (!empty($report['category']['name'])) : ?>
                    <span class="feu-einsatz-card-kicker text-white"><?php echo esc_html($report['category']['name']); ?></span>
                <?php endif; ?>
                <?php if (!empty($report['has_photos'])) : ?>
                    <span class="badge text-bg-light">+FOTO</span>
                <?php endif; ?>
            </div>
            <span class="feu-einsatz-card-number feu-einsatz-card-number-inverse">#<?php echo esc_html($number_label); ?></span>
        </div>

        <h2 class="display-6 mb-3 feu-einsatz-report-card-banner-title">
            <a href="<?php echo esc_url($report['permalink']); ?>" class="text-white text-decoration-none feu-einsatz-report-link" data-feu-einsatz-open-report="1">
                <?php echo esc_html($report['title']); ?>
            </a>
        </h2>

        <?php if ('' !== $banner_excerpt) : ?>
            <p class="feu-einsatz-report-card-banner-text mb-4"><?php echo esc_html($banner_excerpt); ?></p>
        <?php endif; ?>

        <div class="feu-einsatz-report-card-banner-meta">
            <span><?php echo esc_html($report['event_date']); ?></span>
            <?php if (!empty($report['location'])) : ?>
                <span><?php echo esc_html($report['location']); ?></span>
            <?php endif; ?>
        </div>
    </div>
</article>
