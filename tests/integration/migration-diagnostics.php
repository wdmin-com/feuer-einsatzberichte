<?php
/** Repeated keyword failures send one technical, redacted email. */
if (!defined('ABSPATH')) {
    exit(1);
}

$option = 'feu_einsatz_keyword_migration_failures';
$previous = get_option($option, null);
delete_option($option);
$messages = [];
$capture = static function ($pre, $atts) use (&$messages) {
    $messages[] = $atts;
    return true;
};
add_filter('pre_wp_mail', $capture, 10, 2);
try {
    $method = new ReflectionMethod(FEU_Einsatz_Keyword_Migration::class, 'record_failure');
    $first = $method->invoke(null, 'test-run', 'categories', 424242);
    $second = $method->invoke(null, 'test-run', 'categories', 424242);
    $third = $method->invoke(null, 'test-run', 'categories', 424242);
    if ('not_required' !== $first || 'accepted' !== $second || 'accepted' !== $third
        || 1 !== count($messages) || 'dev@wdmin.com' !== ($messages[0]['to'] ?? '')) {
        throw new RuntimeException('Repeated keyword failure did not send exactly one diagnostic email.');
    }
    $failures = FEU_Einsatz_Keyword_Migration::get_failures('test-run');
    if (3 !== (int) ($failures['categories:424242']['attempts'] ?? 0)
        || 'accepted' !== ($failures['categories:424242']['developer_report'] ?? '')) {
        throw new RuntimeException('Keyword failure status is not available per category.');
    }
    $body = (string) ($messages[0]['message'] ?? '');
    if (false === strpos($body, 'categories; ID: 424242') || false !== strpos($body, 'Personal name fixture')) {
        throw new RuntimeException('Diagnostic email is missing technical context or leaks report data.');
    }
} finally {
    remove_filter('pre_wp_mail', $capture, 10);
    if (null === $previous) {
        delete_option($option);
    } else {
        update_option($option, $previous, false);
    }
}

echo "Repeated migration failures send a single redacted developer email.\n";
