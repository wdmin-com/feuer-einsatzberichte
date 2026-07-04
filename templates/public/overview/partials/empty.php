<?php
if (!defined('ABSPATH')) {
    exit;
}

$selected_year = isset($selected_year) ? (string) $selected_year : '';
?>

<div class="alert alert-warning">
    <?php
    if ($selected_year) {
        printf(
            esc_html__('Fuer das Jahr %s wurden keine Einsatzberichte gefunden.', 'feuer-einsatzberichte'),
            esc_html($selected_year)
        );
    } else {
        echo esc_html__('Es wurden keine Einsatzberichte gefunden.', 'feuer-einsatzberichte');
    }
    ?>
</div>
