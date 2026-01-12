<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap prowpsmtp-logs">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Fecha', 'pro-wp-smtp'); ?></th>
                <th><?php _e('Para', 'pro-wp-smtp'); ?></th>
                <th><?php _e('Asunto', 'pro-wp-smtp'); ?></th>
                <th><?php _e('Mailer', 'pro-wp-smtp'); ?></th>
                <th><?php _e('Estado', 'pro-wp-smtp'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($logs)): ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log->created_at); ?></td>
                        <td><?php echo esc_html($log->to_email); ?></td>
                        <td><?php echo esc_html($log->subject); ?></td>
                        <td><?php echo esc_html($log->mailer); ?></td>
                        <td>
                            <span class="prowpsmtp-status prowpsmtp-status-<?php echo esc_attr($log->status); ?>">
                                <?php echo esc_html(ucfirst($log->status)); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="prowpsmtp-no-logs">
                        <?php _e('No hay registros de emails todavía.', 'pro-wp-smtp'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if ($total_pages > 1): ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $page
                ));
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
