<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap prowpsmtp-reports">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="prowpsmtp-stats-grid">
        <div class="prowpsmtp-stat-box">
            <div class="prowpsmtp-stat-value"><?php echo number_format($total_sent); ?></div>
            <div class="prowpsmtp-stat-label"><?php _e('Emails Enviados', 'pro-wp-smtp'); ?></div>
        </div>
        
        <div class="prowpsmtp-stat-box">
            <div class="prowpsmtp-stat-value"><?php echo number_format($total_failed); ?></div>
            <div class="prowpsmtp-stat-label"><?php _e('Emails Fallidos', 'pro-wp-smtp'); ?></div>
        </div>
        
        <div class="prowpsmtp-stat-box">
            <div class="prowpsmtp-stat-value"><?php echo $success_rate; ?>%</div>
            <div class="prowpsmtp-stat-label"><?php _e('Tasa de Éxito', 'pro-wp-smtp'); ?></div>
        </div>
    </div>
    
    <div class="prowpsmtp-report-info">
        <h2><?php _e('Estadísticas Detalladas', 'pro-wp-smtp'); ?></h2>
        <p><?php _e('Monitoreo completo de sus envíos de email. Funcionalidad PRO activada.', 'pro-wp-smtp'); ?></p>
    </div>
</div>
