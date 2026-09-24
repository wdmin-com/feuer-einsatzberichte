<?php
/**
 * Template Name: Feuerwehr Theme – Karte zuerst
 * Template Type: single
 *
 * Copy this file to:
 * /wp-content/themes/your-child-theme/feuer-einsatzberichte/templates/
 * and rename it to custom_single_name.php. It will appear in plugin settings.
 */
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$report = isset($context['report']) && is_array($context['report']) ? $context['report'] : [];
$render = static function ($template) use ($context) {
    return FEU_Einsatz_Template_Helpers::render($template, ['context' => $context]);
};

get_header();
?>
<main class="feu-einsatz-template-feuer-theme">
    <article class="feu-einsatz-template-article container">
        <?php echo $render('templates/public/single/breadcrumbs.php'); ?>
        <div class="feu-einsatz-template-map row g-0"><?php echo $render('templates/public/single/map.php'); ?></div>
        <header class="feu-einsatz-template-header">
            <p class="feu-einsatz-template-kicker"><?php echo esc_html($report['category']['name'] ?? __('Einsatzbericht', 'feuer-einsatzberichte')); ?></p>
            <h1><?php echo esc_html($report['title'] ?? ''); ?></h1>
            <p class="feu-einsatz-template-meta"><?php echo esc_html(trim(($report['date_display'] ?? '') . ' · ' . ($report['time'] ?? ''))); ?></p>
        </header>
        <div class="feu-einsatz-template-description"><?php echo wp_kses_post(apply_filters('the_content', (string) ($report['content'] ?? ''))); ?></div>
        <section class="feu-einsatz-template-details row g-4">
            <div class="col-12 col-lg-7"><?php echo $render('templates/public/single/gallery.php'); ?></div>
            <aside class="col-12 col-lg-5"><?php echo $render('templates/public/single/info.php'); ?></aside>
        </section>
        <section class="feu-einsatz-template-related-limit-4">
            <?php
            $related_context = $context;
            $related_context['related']['display'] = 'cards';
            $related_context['related']['items'] = array_slice((array) ($context['related']['items'] ?? []), 0, 4);
            echo FEU_Einsatz_Template_Helpers::render('templates/public/single/related.php', ['context' => $related_context]);
            ?>
        </section>
    </article>
</main>
<?php get_footer();
