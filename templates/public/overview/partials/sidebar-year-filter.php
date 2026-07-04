<?php
if (!defined('ABSPATH')) {
    exit;
}

$selected_year = isset($selected_year) ? (string) $selected_year : '';
$all_years = isset($all_years) && is_array($all_years) ? $all_years : [];
$year_counts = isset($year_counts) && is_array($year_counts) ? $year_counts : [];
?>

<?php if (!empty($all_years)) : ?>
    <section class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light">
            <h2 class="h5 mb-0"><?php echo esc_html__('Jahr auswählen', 'feuer-einsatzberichte'); ?></h2>
        </div>
        <div class="card-body">
            <form method="get" action="<?php echo esc_url(get_permalink()); ?>" class="feu-einsatz-overview-year-form">
                <label for="feu-einsatz-overview-sidebar-year-select" class="screen-reader-text">
                    <?php echo esc_html__('Jahr auswählen', 'feuer-einsatzberichte'); ?>
                </label>
                <select name="jahr" id="feu-einsatz-overview-sidebar-year-select" class="form-select">
                    <option value=""><?php echo esc_html__('Alle Jahre', 'feuer-einsatzberichte'); ?></option>
                    <?php foreach ($all_years as $year) : ?>
                        <option value="<?php echo esc_attr($year); ?>" <?php selected($selected_year, (string) $year); ?>>
                            <?php
                            printf(
                                '%s (%d)',
                                esc_html($year),
                                isset($year_counts[$year]) ? (int) $year_counts[$year] : 0
                            );
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm mt-3">
                    <?php echo esc_html__('Jahr anzeigen', 'feuer-einsatzberichte'); ?>
                </button>
            </form>

            <div class="mt-3 d-flex flex-column gap-2">
                <?php foreach ($all_years as $year) : ?>
                    <a href="<?php echo esc_url(add_query_arg('jahr', $year, get_permalink())); ?>"
                       class="btn btn-outline-secondary btn-sm<?php echo (string) $selected_year === (string) $year ? ' active' : ''; ?>">
                        <?php
                        printf(
                            '%s (%d)',
                            esc_html($year),
                            isset($year_counts[$year]) ? (int) $year_counts[$year] : 0
                        );
                        ?>
                    </a>
                <?php endforeach; ?>
                <?php if ('' !== $selected_year) : ?>
                    <a href="<?php echo esc_url(remove_query_arg('jahr', get_permalink())); ?>" class="btn btn-link btn-sm px-0 text-start">
                        <?php echo esc_html__('Alle Jahre anzeigen', 'feuer-einsatzberichte'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>