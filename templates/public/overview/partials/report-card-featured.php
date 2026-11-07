<?php
if (!defined('ABSPATH')) { exit; }
$report = isset($report) && is_array($report) ? $report : [];
$show_map = !empty($show_map);
$show_image = !empty($report['image_url']) && ($show_map || empty($report['image_is_map']));
?>
<article class="feuer-call-featured">
    <?php if ($show_image) : ?>
        <a class="feuer-call-featured-media" href="<?php echo esc_url($report['permalink']); ?>" aria-label="<?php echo esc_attr($report['title']); ?>">
            <?php echo FEU_Einsatz_Template_Helpers::render_overview_card_image($report, 'feuer-call-featured-image', ['loading' => 'eager', 'fetchpriority' => 'high']); ?>
        </a>
    <?php endif; ?>
    <div class="feuer-call-featured-body">
        <div class="feuer-call-eyebrow">
            <span><?php echo esc_html__('Aktuellster Einsatz', 'feuer-einsatzberichte'); ?></span>
            <?php if (!empty($report['category']['name'])) : ?><span class="feuer-call-category"><?php echo esc_html($report['category']['name']); ?></span><?php endif; ?>
        </div>
        <h2><a href="<?php echo esc_url($report['permalink']); ?>"><?php echo esc_html($report['title']); ?></a></h2>
        <p class="feuer-call-featured-date"><?php echo esc_html($report['event_date']); ?><?php if (!empty($report['event_time'])) : ?> · <?php echo esc_html($report['event_time']); ?> Uhr<?php endif; ?></p>
        <?php if (!empty($report['excerpt'])) : ?><p class="feuer-call-featured-excerpt"><?php echo esc_html($report['excerpt']); ?></p><?php endif; ?>
        <a class="feuer-call-read-more" href="<?php echo esc_url($report['permalink']); ?>"><?php esc_html_e('Einsatz ansehen', 'feuer-einsatzberichte'); ?><span aria-hidden="true">→</span></a>
    </div>
</article>
