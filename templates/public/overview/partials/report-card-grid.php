<?php
if (!defined('ABSPATH')) { exit; }
$report = isset($report) && is_array($report) ? $report : [];
$image_loading = isset($image_loading) ? (string) $image_loading : 'lazy';
$show_image = !empty($report['image_url']);
?>
<article class="feuer-call-card">
    <?php if ($show_image) : ?>
        <a class="feuer-call-card-media" href="<?php echo esc_url($report['permalink']); ?>" aria-label="<?php echo esc_attr($report['title']); ?>">
            <?php echo FEU_Einsatz_Template_Helpers::render_overview_card_image($report, 'feuer-call-card-image', ['loading' => $image_loading, 'fetchpriority' => 'auto']); ?>
        </a>
    <?php endif; ?>
    <div class="feuer-call-card-body">
        <?php if (!empty($report['category']['name'])) : ?><span class="feuer-call-category"><?php echo esc_html($report['category']['name']); ?></span><?php endif; ?>
        <h2><a href="<?php echo esc_url($report['permalink']); ?>"><?php echo esc_html($report['title']); ?></a></h2>
        <p><?php echo esc_html($report['event_date']); ?><?php if (!empty($report['event_time'])) : ?> · <?php echo esc_html($report['event_time']); ?> Uhr<?php endif; ?></p>
        <?php if (!empty($report['excerpt'])) : ?><div class="feuer-call-card-excerpt"><?php echo esc_html($report['excerpt']); ?></div><?php endif; ?>
    </div>
</article>
