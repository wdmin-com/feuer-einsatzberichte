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
    <?php else : ?>
        <div class="feuer-call-card-media is-placeholder" aria-hidden="true"></div>
    <?php endif; ?>
    <div class="feuer-call-card-body">
        <div class="feuer-call-card-kicker">
            <?php if (!empty($report['category']['name'])) : ?>
                <span class="feuer-call-category"><?php echo esc_html($report['category']['name']); ?></span>
            <?php else : ?>
                <span class="feuer-call-category is-placeholder" aria-hidden="true">FEU</span>
            <?php endif; ?>
        </div>
        <h2><a href="<?php echo esc_url($report['permalink']); ?>"><?php echo esc_html($report['title']); ?></a></h2>
        <p><?php echo esc_html($report['event_date']); ?><?php if (!empty($report['event_time'])) : ?> · <?php echo esc_html($report['event_time']); ?><?php endif; ?></p>
        <?php if (!empty($report['excerpt'])) : ?>
            <div class="feuer-call-card-excerpt"><?php echo esc_html($report['excerpt']); ?></div>
        <?php else : ?>
            <div class="feuer-call-card-excerpt is-placeholder" aria-hidden="true"></div>
        <?php endif; ?>
    </div>
</article>
