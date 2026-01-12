<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap prowpsmtp-test">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="prowpsmtp-test-info">
        <p><?php _e('Envíe un correo de prueba para verificar que su configuración SMTP está funcionando correctamente.', 'pro-wp-smtp'); ?></p>
    </div>
    
    <form method="post" action="">
        <?php wp_nonce_field('prowpsmtp_test_email'); ?>
        
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="prowpsmtp_test_email"><?php _e('Send To', 'pro-wp-smtp'); ?></label>
                    </th>
                    <td>
                        <input type="email" name="prowpsmtp_test_email" id="prowpsmtp_test_email" 
                               value="<?php echo esc_attr(get_option('admin_email')); ?>" 
                               class="regular-text" required>
                        <p class="description">
                            <?php _e('Ingrese la dirección de correo donde desea recibir el email de prueba.', 'pro-wp-smtp'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="prowpsmtp_test_subject"><?php _e('Subject', 'pro-wp-smtp'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="prowpsmtp_test_subject" id="prowpsmtp_test_subject" 
                               value="<?php echo esc_attr__('Pro WP SMTP - Email de Prueba', 'pro-wp-smtp'); ?>" 
                               class="regular-text" required>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="prowpsmtp_test_message"><?php _e('Message', 'pro-wp-smtp'); ?></label>
                    </th>
                    <td>
                        <textarea name="prowpsmtp_test_message" id="prowpsmtp_test_message" 
                                  rows="10" class="large-text" required><?php echo esc_textarea(__('Este es un email de prueba enviado desde Pro WP SMTP.

Si está recibiendo este mensaje, significa que su configuración SMTP está funcionando correctamente.', 'pro-wp-smtp')); ?></textarea>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <input type="submit" name="prowpsmtp_send_test" class="button button-primary" 
                   value="<?php _e('Enviar Email de Prueba', 'pro-wp-smtp'); ?>">
        </p>
    </form>
</div>
