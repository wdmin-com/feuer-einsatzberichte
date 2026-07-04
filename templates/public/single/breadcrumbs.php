<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$breadcrumbs = isset($context['breadcrumbs']) && is_array($context['breadcrumbs']) ? $context['breadcrumbs'] : [];
?>

<?php if (!empty($breadcrumbs)) : ?>
    <nav class="feu-einsatz-single-breadcrumbs" aria-label="<?php echo esc_attr__('Beitragsposition', 'feuer-einsatzberichte'); ?>">
        <ol class="breadcrumb mb-0">
            <?php foreach ($breadcrumbs as $index => $breadcrumb_item) : ?>
                <?php
                $is_last = ($index === array_key_last($breadcrumbs));
                $label = isset($breadcrumb_item['label']) ? (string) $breadcrumb_item['label'] : '';
                $url = isset($breadcrumb_item['url']) ? (string) $breadcrumb_item['url'] : '';
                ?>
                <li class="breadcrumb-item<?php echo $is_last ? ' active' : ''; ?>"<?php echo $is_last ? ' aria-current="page"' : ''; ?>>
                    <?php if (!$is_last && '' !== $url) : ?>
                        <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
                    <?php else : ?>
                        <?php echo esc_html($label); ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
<?php endif; ?>