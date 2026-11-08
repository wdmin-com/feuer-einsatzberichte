<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$selected_year = isset($context['selected_year']) ? (string) $context['selected_year'] : '';
?>

<header class="feu-einsatz-overview-header mb-4">
    <div class="feu-einsatz-overview-header-row">
        <div>
            <h1 class="mb-2"><?php the_title(); ?></h1>
            <?php if ($selected_year) : ?>
                <p class="text-muted mb-0">
                    <?php
                    printf(
                        esc_html__('Einsätze aus dem Jahr %s', 'feuer-einsatzberichte'),
                        esc_html($selected_year)
                    );
                    ?>
                </p>
            <?php else : ?>
                <p class="text-muted mb-0"><?php echo esc_html__('Alle Einsätze', 'feuer-einsatzberichte'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</header>
