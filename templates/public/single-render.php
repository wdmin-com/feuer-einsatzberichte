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
    'share'       => ['templates/public/single/share.php',       ['context' => $context, 'share_data' => $share_data]],
    'comments'    => ['templates/public/single/comments.php',    ['context' => $context]],
    'related'     => ['templates/public/single/related.php',     ['context' => $context]],
];

$_feu_render = static function ($key) use ($_feu_partials) {
    [$tpl, $data] = $_feu_partials[$key];
    try {
        return FEU_Einsatz_Template_Helpers::render($tpl, $data);
    } catch (Throwable $e) {
        error_log('[Feuer-Einsatzberichte] Fehler beim Rendern von ' . $key . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return '';
    }
};
?>

<div class="container feu-einsatz-single-layout">
    <?php echo $_feu_render('breadcrumbs'); ?>

    <div class="row g-4 align-items-stretch feu-einsatz-single-row">
        <?php
        echo $_feu_render('info');
        echo $_feu_render('map');
        ?>
    </div>

    <div class="row feu-einsatz-single-content-row">
        <div class="col-12">
            <?php
            echo $_feu_render('content');
            echo $_feu_render('gallery');
            echo $_feu_render('share');
            ?>
        </div>
    </div>

    <?php
    echo $_feu_render('comments');
    echo $_feu_render('related');
    ?>
</div>

<?php get_footer(); ?>
