<?php
if (!defined('ABSPATH')) {
    exit;
}

global $post;

try {
    $context = FEU_Einsatz_Template_Helpers::get_single_context($post);
} catch (Throwable $e) {
    error_log('[Feuer-Einsatzberichte] Fehler in get_single_context: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    $context = [];
}

if (empty($context['post']) || !($context['post'] instanceof WP_Post)) {
    return;
}

// Keep the original renderer as the safe default. A selected custom template
// receives the same complete context, therefore it cannot accidentally skip
// map geometry, privacy or gallery handling supplied by the plugin.
if ('default' !== FEU_Einsatz_Template_Manager::get_selected('single')) {
    echo FEU_Einsatz_Template_Manager::render('single', [
        'context' => $context,
        'report' => (array) ($context['report'] ?? []),
    ]);
    return;
}

$plugin_core = class_exists('Feuer_Einsatzberichte_Core', false) ? Feuer_Einsatzberichte_Core::get_instance() : null;
$plugin_public = $plugin_core instanceof Feuer_Einsatzberichte_Core ? $plugin_core->get_public() : null;
$share_capability = (string) apply_filters('feu_einsatz_share_capability', 'manage_options');
$share_data = [];

if (
    $plugin_public instanceof FEU_Einsatz_Public
    && current_user_can('' !== $share_capability ? $share_capability : 'manage_options')
) {
    try {
        $share_data = $plugin_public->get_single_share_box_data($post, $context);
    } catch (Throwable $e) {
        error_log('[Feuer-Einsatzberichte] Fehler in get_single_share_box_data: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $share_data = [];
    }
}

get_header();

$_feu_partials = [
    'breadcrumbs' => ['templates/public/single/breadcrumbs.php', ['context' => $context]],
    'info'        => ['templates/public/single/info.php',        ['context' => $context]],
    'map'         => ['templates/public/single/map.php',         ['context' => $context]],
    'content'     => ['templates/public/single/content.php',     ['context' => $context]],
    'gallery'     => ['templates/public/single/gallery.php',     ['context' => $context]],
    'notice'      => ['templates/public/single/notice.php',      ['context' => $context]],
    'share'       => ['templates/public/single/share.php',       ['context' => $context, 'share_data' => $share_data]],
    'comments'    => ['templates/public/single/comments.php',    ['context' => $context]],
    'related'     => ['templates/public/single/related.php',     ['context' => $context]],
];

$_feu_render = static function ($key, $overrides = []) use ($_feu_partials) {
    [$tpl, $data] = $_feu_partials[$key];
    $data = array_merge($data, (array) $overrides);
    try {
        return FEU_Einsatz_Template_Helpers::render($tpl, $data);
    } catch (Throwable $e) {
        error_log('[Feuer-Einsatzberichte] Fehler beim Rendern von ' . $key . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return '';
    }
};
?>

<div class="feuer-call-detail">
    <article class="feuer-call-article">
        <div class="feuer-call-article-shell">
            <header class="feuer-call-detail-head">
                <?php echo $_feu_render('info', ['header_only' => true]); ?>
            </header>

            <?php if ('disabled' !== FEU_Einsatz_Template_Helpers::normalize_single_map_display_mode($context['display']['single_map_display_mode'] ?? 'live')) : ?>
                <section class="feuer-call-detail-map" aria-label="<?php echo esc_attr__('Karte des Einsatzortes', 'feuer-einsatzberichte'); ?>"><?php echo $_feu_render('map'); ?></section>
            <?php endif; ?>
            <?php $description_markup = $_feu_render('content'); ?>
            <?php if ('' !== trim($description_markup)) : ?><section class="feuer-call-detail-description"><?php echo $description_markup; ?></section><?php endif; ?>
            <section class="feuer-call-detail-facts"><?php echo $_feu_render('info', ['facts_only' => true]); ?></section>
            <?php $gallery_markup = $_feu_render('gallery'); ?>
            <?php if ('' !== trim($gallery_markup)) : ?><section class="feuer-call-detail-gallery"><?php echo $gallery_markup; ?></section><?php endif; ?>
            <?php $share_markup = $_feu_render('share'); ?>
            <?php if ('' !== trim($share_markup)) : ?><section class="feuer-call-detail-share"><?php echo $share_markup; ?></section><?php endif; ?>
            <?php echo $_feu_render('notice'); ?>
        </div>
    </article>

    <div class="feuer-call-detail-after">
        <?php
        echo $_feu_render('comments');
        echo $_feu_render('related');
        ?>
    </div>
</div>

<?php get_footer(); ?>
