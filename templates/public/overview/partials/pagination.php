<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$selected_year = isset($context['selected_year']) ? (string) $context['selected_year'] : '';
$paged = isset($context['paged']) ? (int) $context['paged'] : 1;
$max_num_pages = isset($context['max_num_pages']) ? (int) $context['max_num_pages'] : 1;
?>

<?php if ($max_num_pages > 1) : ?>
    <nav aria-label="<?php echo esc_attr__('Seitennavigation', 'feuer-einsatzberichte'); ?>" class="mt-4">
        <?php
        echo paginate_links([
            'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
            'format' => '?paged=%#%',
            'current' => max(1, $paged),
            'total' => $max_num_pages,
            'prev_text' => esc_html__('< Zurueck', 'feuer-einsatzberichte'),
            'next_text' => esc_html__('Weiter >', 'feuer-einsatzberichte'),
            'type' => 'list',
            'add_args' => $selected_year ? ['jahr' => $selected_year] : [],
        ]);
        ?>
    </nav>
<?php endif; ?>