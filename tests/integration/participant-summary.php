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

$percent = [FEU_Einsatz_Template_Helpers::class, 'participant_participation_percent'];
$below_minimum = [FEU_Einsatz_Template_Helpers::class, 'participant_below_minimum'];
if (24.0 !== $percent(6, 25) || !$below_minimum(6, 25, 30)) {
    throw new RuntimeException('Six of 25 calls must be shown as 24 percent, below a 30 percent minimum.');
}
if (30.0 !== $percent(3, 10) || $below_minimum(3, 10, 30)) {
    throw new RuntimeException('Participation equal to the minimum must not be highlighted.');
}
if (0.0 !== $percent(0, 0) || $below_minimum(0, 0, 30)) {
    throw new RuntimeException('An empty year must not mark participants as below the minimum.');
}
if (29.9 !== $percent(2999, 10000) || !$below_minimum(2999, 10000, 30)) {
    throw new RuntimeException('A rate just below the minimum must not be displayed as 30 percent.');
}

WP_CLI::success('Participant function summaries and participation thresholds are correct.');
