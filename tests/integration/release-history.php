<?php
/**
 * Verifies the packaged release history and its settings presentation.
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress was not bootstrapped.\n");
    exit(1);
}

$history_path = FEU_EINSATZ_PLUGIN_DIR . 'release-notes.json';
if (!is_readable($history_path)) {
    fwrite(STDERR, "::error title=Release history integration test::release-notes.json is missing from the installed plugin package.\n");
    exit(1);
}

$history = json_decode((string) file_get_contents($history_path), true);
$releases = is_array($history) && isset($history['releases']) && is_array($history['releases'])
    ? $history['releases']
    : [];

if (count($releases) < 10) {
    fwrite(STDERR, "::error title=Release history integration test::Fewer than ten release entries are available.\n");
    exit(1);
}

$seen_versions = [];
$previous_version = null;
foreach ($releases as $release) {
    $version = trim((string) ($release['version'] ?? ''));
    $required_values = [
        $version,
        trim((string) ($release['date'] ?? '')),
        trim((string) ($release['title'] ?? '')),
        trim((string) ($release['summary'] ?? '')),
    ];

    if (in_array('', $required_values, true) || empty($release['changes']) || !is_array($release['changes'])) {
        fwrite(STDERR, "::error title=Release history integration test::Release {$version} is incomplete.\n");
        exit(1);
    }

    if (isset($seen_versions[$version])) {
        fwrite(STDERR, "::error title=Release history integration test::Duplicate release {$version}.\n");
        exit(1);
    }

    if (null !== $previous_version && version_compare($previous_version, $version, '<=')) {
        fwrite(STDERR, "::error title=Release history integration test::Release entries are not ordered from newest to oldest.\n");
        exit(1);
    }

    if (null !== $previous_version) {
        $previous_parts = array_map('intval', explode('.', $previous_version));
        $current_parts = array_map('intval', explode('.', $version));
        if (
            count($previous_parts) >= 3
            && count($current_parts) >= 3
            && $previous_parts[0] === $current_parts[0]
            && $previous_parts[1] === $current_parts[1]
            && $previous_parts[2] - 1 !== $current_parts[2]
        ) {
            fwrite(STDERR, "::error title=Release history integration test::A release is missing between {$previous_version} and {$version}.\n");
            exit(1);
        }
    }

    $seen_versions[$version] = true;
    $previous_version = $version;
}

$active_tab = 'manifest';
$remote_manifest_payload = false;
$participant_fallback_function = FEU_Einsatz_Installer::get_default_participant_function();
$manifest_path = FEU_EINSATZ_PLUGIN_DIR . 'update-manifest.json';
$manifest_display_contents = '';
$release_notes_path = $history_path;
$release_notes_entries = $releases;
$remote_manifest_url = FEU_Einsatz_Updater::get_manifest_url();
$functions_reference_path = FEU_EINSATZ_PLUGIN_DIR . 'docs/PLUGIN_FUNCTIONS_AND_FILES.md';
$update_guide_path = FEU_EINSATZ_PLUGIN_DIR . 'docs/UPDATE_HOSTING_AND_GITHUB.md';
$docs_directory = FEU_EINSATZ_PLUGIN_DIR . 'docs';
$templates_directory = FEU_EINSATZ_PLUGIN_DIR . 'templates';

ob_start();
include FEU_EINSATZ_PLUGIN_DIR . 'templates/admin/settings/partials/tab-manifest.php';
$html = (string) ob_get_clean();

if (10 !== substr_count($html, '<article class="feu-einsatz-release-note-card">')) {
    fwrite(STDERR, "::error title=Release history integration test::Settings do not render exactly ten release entries.\n");
    exit(1);
}

foreach (array_slice($releases, 0, 10) as $release) {
    if (false === strpos($html, '>' . esc_html((string) $release['version']) . '</h4>')) {
        fwrite(STDERR, "::error title=Release history integration test::A latest release is missing from the settings output.\n");
        exit(1);
    }
}

if (isset($releases[10]) && false !== strpos($html, '>' . esc_html((string) $releases[10]['version']) . '</h4>')) {
    fwrite(STDERR, "::error title=Release history integration test::Settings render more than ten release entries.\n");
    exit(1);
}

echo "release-history-ok\n";
