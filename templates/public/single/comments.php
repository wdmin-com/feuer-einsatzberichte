<?php
if (!defined('ABSPATH')) {
    exit;
}

$context = isset($context) && is_array($context) ? $context : [];
$post = isset($context['post']) && $context['post'] instanceof WP_Post ? $context['post'] : null;
$comments = isset($context['comments']) && is_array($context['comments']) ? $context['comments'] : [];
$report_comments = isset($comments['items']) && is_array($comments['items']) ? $comments['items'] : [];
$comments_enabled = !empty($comments['enabled']);
$report_comments_title = isset($comments['title']) ? (string) $comments['title'] : '';
?>

<?php if ($comments_enabled && $post instanceof WP_Post) : ?>
    <section class="feu-einsatz-comments-section" id="feu-einsatz-comments">
        <div class="row justify-content-lg-center">
            <div class="col-lg-8">
                <div class="feu-einsatz-comments-shell">
                    <div class="feu-einsatz-comments-header text-center">
                        <div class="w-100">
                            <h2 class="feu-einsatz-comments-heading"><?php echo esc_html($report_comments_title); ?></h2>
                            <p class="feu-einsatz-comments-subtitle"><?php echo esc_html__('Angemeldete WordPress-Benutzer koennen Einsatzberichte kommentieren.', 'feuer-einsatzberichte'); ?></p>
                        </div>
                    </div>

                    <?php if (!empty($report_comments)) : ?>
                        <?php
                        if (!function_exists('feu_einsatz_render_report_comment')) {
                            function feu_einsatz_render_report_comment($comment, $args, $depth) {
                                $tag = ('div' === $args['style']) ? 'div' : 'li';
                                ?>
                                <<?php echo $tag; ?> <?php comment_class('feu-einsatz-comment', $comment); ?> id="comment-<?php comment_ID(); ?>">
                                    <article class="feu-einsatz-comment-body">
                                        <div class="feu-einsatz-comment-avatar">
                                            <?php echo get_avatar($comment, (int) ($args['avatar_size'] ?? 56)); ?>
                                        </div>
                                        <div class="feu-einsatz-comment-content">
                                            <div class="feu-einsatz-comment-meta">
                                                <strong class="feu-einsatz-comment-author"><?php echo get_comment_author_link($comment); ?></strong>
                                                <time class="feu-einsatz-comment-date" datetime="<?php comment_date('c', $comment); ?>">
                                                    <?php comment_date('', $comment); ?>
                                                </time>
                                                <?php edit_comment_link(__('Bearbeiten', 'feuer-einsatzberichte'), '<span class="feu-einsatz-comment-edit">', '</span>'); ?>
                                            </div>
                                            <?php if ('0' === $comment->comment_approved) : ?>
                                                <p class="feu-einsatz-comment-awaiting-moderation small text-muted">
                                                    <?php esc_html_e('Ihr Kommentar wartet auf Freigabe.', 'feuer-einsatzberichte'); ?>
                                                </p>
                                            <?php endif; ?>
                                            <div class="feu-einsatz-comment-text">
                                                <?php comment_text($comment); ?>
                                            </div>
                                            <?php
                                            comment_reply_link(array_merge($args, [
                                                'add_below' => 'comment',
                                                'depth'     => $depth,
                                                'max_depth' => $args['max_depth'],
                                                'before'    => '<div class="feu-einsatz-comment-reply">',
                                                'after'     => '</div>',
                                            ]), $comment);
                                            ?>
                                        </div>
                                    </article>
                                <?php
                            }
                        }

                        if (!function_exists('feu_einsatz_render_report_comment_end')) {
                            function feu_einsatz_render_report_comment_end($comment, $args, $depth) {
                                $tag = ('div' === $args['style']) ? 'div' : 'li';
                                echo '</' . $tag . '>';
                            }
                        }
                        ?>
                        <ul class="list-comment">
                            <?php
                            wp_list_comments([
                                'style' => 'ul',
                                'short_ping' => true,
                                'avatar_size' => 56,
                                'max_depth' => (int) get_option('thread_comments_depth', 3),
                                'callback' => 'feu_einsatz_render_report_comment',
                                'end-callback' => 'feu_einsatz_render_report_comment_end',
                            ], $report_comments);
                            ?>
                        </ul>
                    <?php else : ?>
                        <div class="feu-einsatz-comments-empty alert alert-light mb-4" role="status">
                            <?php echo esc_html__('Bisher gibt es noch keine Kommentare zu diesem Einsatzbericht.', 'feuer-einsatzberichte'); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (is_user_logged_in()) : ?>
                        <?php
                        $current_user = wp_get_current_user();
                        comment_form([
                            'title_reply' => __('Kommentar verfassen', 'feuer-einsatzberichte'),
                            'title_reply_before' => '<h3 class="feu-einsatz-comment-form-title">',
                            'title_reply_after' => '</h3>',
                            'cancel_reply_before' => '<div class="feu-einsatz-comment-cancel-link">',
                            'cancel_reply_after' => '</div>',
                            'class_form' => 'feu-einsatz-comment-form',
                            'class_submit' => 'btn btn-dark feu-einsatz-comment-submit',
                            'label_submit' => __('Kommentar absenden', 'feuer-einsatzberichte'),
                            'submit_button' => '<button name="%1$s" type="submit" id="%2$s" class="%3$s">%4$s</button>',
                            'submit_field' => '<div class="feu-einsatz-comment-submit-wrap">%1$s %2$s</div>',
                            'comment_notes_before' => '',
                            'comment_notes_after' => '',
                            'logged_in_as' => '<p class="feu-einsatz-comment-logged-in small text-muted">' .
                                sprintf(
                                    esc_html__('Angemeldet als %s.', 'feuer-einsatzberichte'),
                                    '<strong>' . esc_html($current_user->display_name) . '</strong>'
                                ) .
                                ' <a href="' . esc_url(wp_logout_url(get_permalink($post->ID))) . '">' . esc_html__('Abmelden', 'feuer-einsatzberichte') . '</a></p>',
                            'fields' => [],
                            'comment_field' => '<div class="mb-3 comment-form-comment">' .
                                '<label for="comment" class="form-label">' . esc_html__('Kommentar', 'feuer-einsatzberichte') . '</label>' .
                                '<textarea id="comment" name="comment" class="form-control" rows="5" maxlength="5000" required placeholder="' . esc_attr__('Kommentar schreiben...', 'feuer-einsatzberichte') . '"></textarea>' .
                                '</div>',
                        ]);
                        ?>
                    <?php else : ?>
                        <div class="feu-einsatz-comments-login-card">
                            <h3 class="feu-einsatz-comment-form-title"><?php echo esc_html__('Kommentar verfassen', 'feuer-einsatzberichte'); ?></h3>
                            <p class="mb-3"><?php echo esc_html__('Zum Kommentieren ist eine Anmeldung im WordPress-System erforderlich.', 'feuer-einsatzberichte'); ?></p>
                            <a class="btn btn-dark" href="<?php echo esc_url(wp_login_url(get_permalink($post->ID) . '#feu-einsatz-comments')); ?>">
                                <?php echo esc_html__('Jetzt anmelden', 'feuer-einsatzberichte'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>