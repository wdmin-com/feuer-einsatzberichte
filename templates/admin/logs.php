<?php
if (!defined('ABSPATH')) {
    exit;
}

$logger = FEU_Einsatz_Logger::get_instance();
$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$action_filter = isset($_GET['log_action']) ? sanitize_key(wp_unslash($_GET['log_action'])) : '';
$entity_filter = isset($_GET['log_entity']) ? sanitize_key(wp_unslash($_GET['log_entity'])) : '';
$paged = max(1, isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1);
$per_page = 100;
$offset = ($paged - 1) * $per_page;
$logs = $logger ? $logger->get_logs([
    'limit' => $per_page,
    'offset' => $offset,
    'action_type' => $action_filter,
    'entity_type' => $entity_filter,
    'search' => $search,
]) : [];
$total_logs = $logger ? $logger->count_logs([
    'action_type' => $action_filter,
    'entity_type' => $entity_filter,
    'search' => $search,
]) : 0;
$total_pages = max(1, (int) ceil($total_logs / $per_page));
$action_choices = $logger ? $logger->get_action_choices() : [];
$entity_choices = $logger ? $logger->get_entity_choices() : [];
$filters_active = '' !== $search || '' !== $action_filter || '' !== $entity_filter;
?>

<div class="wrap feu-einsatz-logs-page feu-admin-page">
    <div class="feu-admin-page-header">
        <div class="feu-admin-page-heading">
            <span class="feu-admin-page-eyebrow"><?php esc_html_e('Monitoring', 'feuer-einsatzberichte'); ?></span>
            <h1><?php esc_html_e('Plugin-Logs', 'feuer-einsatzberichte'); ?></h1>
        </div>
    </div>
    <p class="description"><?php esc_html_e('Zeigt Datum und Uhrzeit, Benutzer, Bereich, Aktion, Änderungen und Ergebnis jeder protokollierten Aktivität.', 'feuer-einsatzberichte'); ?></p>

    <form method="get" class="feu-einsatz-log-filter-bar">
        <input type="hidden" name="page" value="feu-einsatz-logs" />
        <input type="search"
               name="s"
               value="<?php echo esc_attr($search); ?>"
               placeholder="<?php esc_attr_e('Suche in Bereich, Aktion oder Details', 'feuer-einsatzberichte'); ?>"
               class="regular-text" />

        <select name="log_action">
            <option value=""><?php esc_html_e('Alle Aktionen', 'feuer-einsatzberichte'); ?></option>
            <?php foreach ($action_choices as $action_key => $action_label) : ?>
                <option value="<?php echo esc_attr($action_key); ?>" <?php selected($action_filter, $action_key); ?>>
                    <?php echo esc_html($action_label); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="log_entity">
            <option value=""><?php esc_html_e('Alle Bereiche', 'feuer-einsatzberichte'); ?></option>
            <?php foreach ($entity_choices as $entity_key => $entity_label) : ?>
                <?php if ('' === (string) $entity_key) { continue; } ?>
                <option value="<?php echo esc_attr($entity_key); ?>" <?php selected($entity_filter, $entity_key); ?>>
                    <?php echo esc_html($entity_label); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="button button-primary"><?php esc_html_e('Filtern', 'feuer-einsatzberichte'); ?></button>

        <?php if ($filters_active) : ?>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=feu-einsatz-logs')); ?>">
                <?php esc_html_e('Filter zuruecksetzen', 'feuer-einsatzberichte'); ?>
            </a>
        <?php endif; ?>
    </form>

    <?php if (empty($logs)) : ?>
        <div class="feu-einsatz-log-empty-card">
            <h2><?php esc_html_e('Keine Log-Eintraege gefunden', 'feuer-einsatzberichte'); ?></h2>
            <p class="description"><?php esc_html_e('Fuer die aktuelle Filterkombination wurden keine passenden Aktionen gefunden.', 'feuer-einsatzberichte'); ?></p>
        </div>
    <?php else : ?>
        <div class="feu-einsatz-table-scroll">
            <table class="wp-list-table widefat striped feu-einsatz-log-table feu-admin-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Datum und Uhrzeit', 'feuer-einsatzberichte'); ?></th>
                        <th><?php esc_html_e('Benutzer', 'feuer-einsatzberichte'); ?></th>
                        <th><?php esc_html_e('Bereich', 'feuer-einsatzberichte'); ?></th>
                        <th><?php esc_html_e('Aktion', 'feuer-einsatzberichte'); ?></th>
                        <th><?php esc_html_e('Änderungen', 'feuer-einsatzberichte'); ?></th>
                        <th><?php esc_html_e('Ergebnis', 'feuer-einsatzberichte'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : ?>
                        <?php
                        $detail_sections = $logger ? $logger->get_log_detail_sections($log) : ['summary' => [], 'changes' => []];
                        $action_label = $logger ? $logger->get_action_label($log->action_type) : $log->action_type;
                        $area_label = $logger ? $logger->get_log_area_label($log) : ($log->entity_type ?: '-');
                        $message_text = $logger ? $logger->get_log_message($log) : $log->message;
                        $subject_label = $logger ? $logger->get_log_subject_label($log) : '';
                        $action_type = (string) $log->action_type;
                        $status_class = 'is-success';
                        $status_label = __('Erfolgreich', 'feuer-einsatzberichte');
                        $change_lines = [];

                        if ('page_visit' === $action_type) {
                            $status_class = 'is-view';
                            $status_label = __('Ansicht', 'feuer-einsatzberichte');
                            $change_lines[] = __('Keine Änderungen, nur Bereich aufgerufen.', 'feuer-einsatzberichte');
                        } elseif ('runtime_error' === $action_type) {
                            $status_class = 'is-error';
                            $status_label = __('Fehlgeschlagen', 'feuer-einsatzberichte');
                        }

                        if (!empty($detail_sections['changes'])) {
                            foreach (array_slice($detail_sections['changes'], 0, 4) as $change_item) {
                                $before = '' !== trim((string) ($change_item['before'] ?? '')) ? (string) $change_item['before'] : '-';
                                $after = '' !== trim((string) ($change_item['after'] ?? '')) ? (string) $change_item['after'] : '-';
                                $change_lines[] = $change_item['label'] . ': ' . $before . ' -> ' . $after;
                            }
                        } elseif ('page_visit' !== $action_type && !empty($detail_sections['summary'])) {
                            foreach (array_slice($detail_sections['summary'], 0, 3) as $detail_item) {
                                $change_lines[] = $detail_item['label'] . ': ' . $detail_item['value'];
                            }
                        } elseif ('page_visit' !== $action_type) {
                            $change_lines[] = __('Keine protokollierten Änderungen.', 'feuer-einsatzberichte');
                        }
                        ?>
                        <tr>
                            <td data-label="<?php echo esc_attr__('Datum und Uhrzeit', 'feuer-einsatzberichte'); ?>">
                                <strong><?php echo esc_html(date_i18n('d.m.Y H:i:s', strtotime((string) $log->created_at))); ?></strong>
                            </td>
                            <td data-label="<?php echo esc_attr__('Benutzer', 'feuer-einsatzberichte'); ?>">
                                <?php echo esc_html($log->user_name ?: __('System', 'feuer-einsatzberichte')); ?>
                            </td>
                            <td data-label="<?php echo esc_attr__('Bereich', 'feuer-einsatzberichte'); ?>">
                                <div class="feu-einsatz-log-area-text"><?php echo esc_html($area_label); ?></div>
                            </td>
                            <td data-label="<?php echo esc_attr__('Aktion', 'feuer-einsatzberichte'); ?>">
                                <div class="feu-einsatz-log-action-text"><?php echo esc_html($action_label); ?></div>
                                <?php if ('' !== trim((string) $message_text) && $message_text !== $action_label) : ?>
                                    <div class="feu-einsatz-log-action-note"><?php echo esc_html($message_text); ?></div>
                                <?php endif; ?>
                                <?php if ('' !== trim((string) $subject_label)) : ?>
                                    <div class="feu-einsatz-log-action-subject"><?php echo esc_html($subject_label); ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?php echo esc_attr__('Änderungen', 'feuer-einsatzberichte'); ?>">
                                <div class="feu-einsatz-log-change-list">
                                    <?php foreach ($change_lines as $change_line) : ?>
                                        <div class="feu-einsatz-log-change-item"><?php echo esc_html($change_line); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td data-label="<?php echo esc_attr__('Ergebnis', 'feuer-einsatzberichte'); ?>">
                                <span class="feu-einsatz-log-status <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo wp_kses_post(paginate_links([
                    'base' => add_query_arg([
                        'page' => 'feu-einsatz-logs',
                        's' => $search,
                        'log_action' => $action_filter,
                        'log_entity' => $entity_filter,
                        'paged' => '%#%',
                    ], admin_url('admin.php')),
                    'format' => '',
                    'current' => $paged,
                    'total' => $total_pages,
                ]));
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
