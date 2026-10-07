<?php
if (!defined('ABSPATH')) {
    exit(1);
}

$summarize = [FEU_Einsatz_Ajax_Handler::class, 'summarize_participant_functions'];
$once = $summarize([(object) ['funktion' => 'Maschinist', 'anzahl' => 1]]);
if ('' !== $once['name'] || 0 !== $once['count']) {
    throw new RuntimeException('A single occurrence was presented as the most frequent function.');
}

$repeated = $summarize([
    (object) ['funktion' => 'Maschinist', 'anzahl' => 3],
    (object) ['funktion' => 'Atemschutz', 'anzahl' => 1],
]);
if ('Maschinist' !== $repeated['name'] || 3 !== $repeated['count']) {
    throw new RuntimeException('Most frequent function count is incorrect.');
}

$tie = $summarize([
    (object) ['funktion' => 'Maschinist', 'anzahl' => 2],
    (object) ['funktion' => 'Atemschutz', 'anzahl' => 2],
]);
if (2 !== $tie['count'] || false === strpos($tie['name'], '2')) {
    throw new RuntimeException('Equal function counts were presented as a unique winner.');
}

WP_CLI::success('Participant function summaries distinguish repeats, single occurrences and ties.');
