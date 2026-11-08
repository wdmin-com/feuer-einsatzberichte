<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$report = isset($context['report']) && is_array($context['report']) ? $context['report'] : [];
?>

<?php if (!empty($report['content'])) : ?>
    <?php
    $content_markup = (string) apply_filters('the_content', $report['content']);
    $content_markup = preg_replace_callback('/<p\b([^>]*)>/i', static function ($matches) {
        $attributes = (string) ($matches[1] ?? '');
        if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/i', $attributes)) {
            $attributes = preg_replace('/\bclass\s*=\s*(["\'])(.*?)\1/i', 'class=$1$2 site-article-lead$1', $attributes, 1);
        } else {
            $attributes = ' class="site-article-lead"' . $attributes;
        }
        return '<p' . $attributes . '>';
    }, $content_markup, 1);
    ?>
    <div class="card feu-einsatz-single-content-card">
        <div class="card-body">
            <div class="feu-einsatz-single-content"><?php echo $content_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress the_content filters the stored report HTML. ?></div>
        </div>
    </div>
<?php endif; ?>
