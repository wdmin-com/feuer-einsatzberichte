<?php
/**
 * Exercises the real settings form POST path through the admin renderer.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress was not bootstrapped.\n");
    exit(1);
}

wp_set_current_user(1);

$core = Feuer_Einsatzberichte_Core::get_instance();
$admin = $core->get_admin();

if (!$admin instanceof FEU_Einsatz_Admin) {
    fwrite(STDERR, "::error title=Settings form integration test::Plugin admin service is unavailable.\n");
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = 'POST';
$settings_section_visibility = array_fill_keys(
    array_keys(FEU_Einsatz_Admin::get_settings_section_definitions()),
    '1'
);
$settings_section_visibility['social'] = '0';

$_POST = [
    'feu_einsatz_settings_nonce' => wp_create_nonce('feu_einsatz_save_settings'),
    'feu_einsatz_settings_action' => 'save',
    'feu_einsatz_active_tab' => 'allgemein',
    'feu_einsatz_map_zoom' => '17',
    'feu_einsatz_map_height' => '420',
    'feu_einsatz_functions' => FEU_Einsatz_Installer::get_default_functions(),
    'feu_einsatz_settings_section_visibility_present' => '1',
    'feu_einsatz_settings_section_visibility' => $settings_section_visibility,
];

$admin->render_settings();

fwrite(STDERR, "::error title=Settings form integration test::Settings renderer returned without completing the POST redirect.\n");
exit(1);
