<?php
/** An incomplete first-run request must not create categories or finish setup. */
if (!defined('ABSPATH')) {
    exit(1);
}
wp_set_current_user(1);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'feu_einsatz_setup_nonce' => wp_create_nonce('feu_einsatz_complete_setup'),
    'feu_einsatz_setup_intent' => 'complete',
    'feu_einsatz_area_station_name' => 'Feuerwehr Akademie Hamburg',
    'feu_einsatz_area_station_street' => 'Bredowstraße 4',
    'feu_einsatz_area_station_postcode' => '22113',
    'feu_einsatz_area_station_city' => 'Hamburg',
    'feu_einsatz_setup_default_categories' => [],
];
$_REQUEST = array_merge($_REQUEST, $_POST);
Feuer_Einsatzberichte_Core::get_instance()->get_admin()->handle_setup_wizard();
exit(1);
