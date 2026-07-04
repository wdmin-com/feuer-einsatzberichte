<?php
if (!defined('ABSPATH')) {
    exit;
}

if (defined('FEU_EINSATZ_SINGLE_TEMPLATE_BOOTSTRAP_ONLY') && FEU_EINSATZ_SINGLE_TEMPLATE_BOOTSTRAP_ONLY) {
    require FEU_EINSATZ_PLUGIN_DIR . 'templates/public/single-bootstrap.php';
    return;
}

require FEU_EINSATZ_PLUGIN_DIR . 'templates/public/single-render.php';
