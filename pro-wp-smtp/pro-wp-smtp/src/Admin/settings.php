<?php
if (!defined('ABSPATH')) {
    exit;
}

$current_mailer = isset($settings['mailer']) ? $settings['mailer'] : 'smtp';
?>

<div class="wrap prowpsmtp-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="prowpsmtp-header">
        <p class="description">
            <?php _e('Configure su servidor SMTP para enviar correos electrónicos de forma confiable. Todas las funciones PRO están activadas.', 'pro-wp-smtp'); ?>
        </p>
    </div>
    
    <form method="post" action="">
        <?php wp_nonce_field('prowpsmtp_settings'); ?>
        
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="prowpsmtp_from_email"><?php _e('From Email', 'pro-wp-smtp'); ?></label>
                    </th>
                    <td>
                        <input type="email" name="prowpsmtp_from_email" id="prowpsmtp_from_email" 
                               value="<?php echo esc_attr(isset($settings['mail_from']) ? $settings['mail_from'] : get_option('admin_email')); ?>" 
                               class="regular-text" required>
                        <p class="description">
                            <?php _e('El correo electrónico que aparecerá como remitente.', 'pro-wp-smtp'); ?>
                        </p>
                        <label>
                            <input type="checkbox" name="prowpsmtp_force_from_email" value="1" 
                                   <?php checked(!empty($settings['force_from_email'])); ?>>
                            <?php _e('Force From Email (sobrescribe el remitente de plugins y temas)', 'pro-wp-smtp'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="prowpsmtp_from_name"><?php _e('From Name', 'pro-wp-smtp'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="prowpsmtp_from_name" id="prowpsmtp_from_name" 
                               value="<?php echo esc_attr(isset($settings['mail_from_name']) ? $settings['mail_from_name'] : get_option('blogname')); ?>" 
                               class="regular-text" required>
                        <p class="description">
                            <?php _e('El nombre que aparecerá como remitente.', 'pro-wp-smtp'); ?>
                        </p>
                        <label>
                            <input type="checkbox" name="prowpsmtp_force_from_name" value="1" 
                                   <?php checked(!empty($settings['force_from_name'])); ?>>
                            <?php _e('Force From Name (sobrescribe el nombre de plugins y temas)', 'pro-wp-smtp'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="prowpsmtp_mailer"><?php _e('Mailer', 'pro-wp-smtp'); ?></label>
                    </th>
                    <td>
                        <select name="prowpsmtp_mailer" id="prowpsmtp_mailer" class="prowpsmtp-mailer-select">
                            <option value="smtp" <?php selected($current_mailer, 'smtp'); ?>>
                                <?php _e('Otro SMTP (Para correos corporativos)', 'pro-wp-smtp'); ?>
                            </option>
                            <option value="gmail" <?php selected($current_mailer, 'gmail'); ?>>
                                <?php _e('Gmail', 'pro-wp-smtp'); ?>
                            </option>
                            <option value="sendgrid" <?php selected($current_mailer, 'sendgrid'); ?>>
                                <?php _e('SendGrid', 'pro-wp-smtp'); ?>
                            </option>
                            <option value="mailgun" <?php selected($current_mailer, 'mailgun'); ?>>
                                <?php _e('Mailgun', 'pro-wp-smtp'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Seleccione "Otro SMTP" para configurar servidores corporativos personalizados.', 'pro-wp-smtp'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="prowpsmtp_return_path"><?php _e('Return Path', 'pro-wp-smtp'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="prowpsmtp_return_path" id="prowpsmtp_return_path" value="1" 
                                   <?php checked(!empty($settings['return_path'])); ?>>
                            <?php _e('Establecer el Return-Path para que coincida con el From Email', 'pro-wp-smtp'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Recomendado para reducir correos rebotados.', 'pro-wp-smtp'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <div id="prowpsmtp-smtp-settings" class="prowpsmtp-mailer-settings" style="display: <?php echo $current_mailer === 'smtp' ? 'block' : 'none'; ?>;">
            <h2><?php _e('Configuración SMTP - Correo Corporativo', 'pro-wp-smtp'); ?></h2>
            <p class="description">
                <?php _e('Configure su servidor SMTP corporativo. Consulte con su proveedor de hosting o administrador de correo para obtener estos datos.', 'pro-wp-smtp'); ?>
            </p>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="prowpsmtp_smtp_host"><?php _e('SMTP Host', 'pro-wp-smtp'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="prowpsmtp_smtp_host" id="prowpsmtp_smtp_host" 
                                   value="<?php echo esc_attr(isset($settings['smtp_host']) ? $settings['smtp_host'] : ''); ?>" 
                                   class="regular-text" placeholder="smtp.ejemplo.com">
                            <p class="description">
                                <?php _e('Servidor SMTP de su proveedor. Ejemplos: smtp.gmail.com, smtp.office365.com, mail.sudominio.com', 'pro-wp-smtp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="prowpsmtp_smtp_port"><?php _e('SMTP Port', 'pro-wp-smtp'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="prowpsmtp_smtp_port" id="prowpsmtp_smtp_port" 
                                   value="<?php echo esc_attr(isset($settings['smtp_port']) ? $settings['smtp_port'] : '587'); ?>" 
                                   class="small-text">
                            <p class="description">
                                <?php _e('Puertos comunes: 587 (TLS - recomendado), 465 (SSL), 25 (sin encriptación)', 'pro-wp-smtp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="prowpsmtp_smtp_encryption"><?php _e('Encryption', 'pro-wp-smtp'); ?></label>
                        </th>
                        <td>
                            <select name="prowpsmtp_smtp_encryption" id="prowpsmtp_smtp_encryption">
                                <option value="none" <?php selected(isset($settings['smtp_encryption']) ? $settings['smtp_encryption'] : 'tls', 'none'); ?>>
                                    <?php _e('None', 'pro-wp-smtp'); ?>
                                </option>
                                <option value="ssl" <?php selected(isset($settings['smtp_encryption']) ? $settings['smtp_encryption'] : 'tls', 'ssl'); ?>>
                                    <?php _e('SSL', 'pro-wp-smtp'); ?>
                                </option>
                                <option value="tls" <?php selected(isset($settings['smtp_encryption']) ? $settings['smtp_encryption'] : 'tls', 'tls'); ?>>
                                    <?php _e('TLS (recomendado)', 'pro-wp-smtp'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('TLS es el método más seguro para la mayoría de servidores modernos.', 'pro-wp-smtp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="prowpsmtp_smtp_auth"><?php _e('Authentication', 'pro-wp-smtp'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="prowpsmtp_smtp_auth" id="prowpsmtp_smtp_auth" value="1" 
                                       <?php checked(!empty($settings['smtp_auth']) || !isset($settings['smtp_auth'])); ?>>
                                <?php _e('Usar autenticación SMTP', 'pro-wp-smtp'); ?>
                            </label>
                            <p class="description">
                                <?php _e('La mayoría de los servidores SMTP requieren autenticación. Mantenga esta opción activada.', 'pro-wp-smtp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr class="prowpsmtp-smtp-auth-row">
                        <th scope="row">
                            <label for="prowpsmtp_smtp_user"><?php _e('SMTP Username', 'pro-wp-smtp'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="prowpsmtp_smtp_user" id="prowpsmtp_smtp_user" 
                                   value="<?php echo esc_attr(isset($settings['smtp_user']) ? $settings['smtp_user'] : ''); ?>" 
                                   class="regular-text" placeholder="usuario@ejemplo.com">
                            <p class="description">
                                <?php _e('Nombre de usuario SMTP. Normalmente es su dirección de correo electrónico completa.', 'pro-wp-smtp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr class="prowpsmtp-smtp-auth-row">
                        <th scope="row">
                            <label for="prowpsmtp_smtp_pass"><?php _e('SMTP Password', 'pro-wp-smtp'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="prowpsmtp_smtp_pass" id="prowpsmtp_smtp_pass" 
                                   value="" 
                                   class="regular-text" placeholder="<?php echo !empty($settings['smtp_pass']) ? '••••••••' : ''; ?>">
                            <p class="description">
                                <?php _e('Contraseña SMTP. Se almacena de forma segura en la base de datos.', 'pro-wp-smtp'); ?>
                                <?php if (!empty($settings['smtp_pass'])): ?>
                                    <br><strong><?php _e('⚠️ Deje este campo vacío si no desea cambiar la contraseña actual.', 'pro-wp-smtp'); ?></strong>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div id="prowpsmtp-gmail-settings" class="prowpsmtp-mailer-settings" style="display: <?php echo $current_mailer === 'gmail' ? 'block' : 'none'; ?>;">
            <h2><?php _e('Configuración Gmail', 'pro-wp-smtp'); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="prowpsmtp_gmail_client_id"><?php _e('Client ID', 'pro-wp-smtp'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="prowpsmtp_gmail_client_id" id="prowpsmtp_gmail_client_id" 
                                   value="<?php echo esc_attr(isset($settings['gmail_client_id']) ? $settings['gmail_client_id'] : ''); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="prowpsmtp_gmail_client_secret"><?php _e('Client Secret', 'pro-wp-smtp'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="prowpsmtp_gmail_client_secret" id="prowpsmtp_gmail_client_secret" 
                                   value="<?php echo esc_attr(isset($settings['gmail_client_secret']) ? $settings['gmail_client_secret'] : ''); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div id="prowpsmtp-sendgrid-settings" class="prowpsmtp-mailer-settings" style="display: <?php echo $current_mailer === 'sendgrid' ? 'block' : 'none'; ?>;">
            <h2><?php _e('Configuración SendGrid', 'pro-wp-smtp'); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="prowpsmtp_sendgrid_api_key"><?php _e('API Key', 'pro-wp-smtp'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="prowpsmtp_sendgrid_api_key" id="prowpsmtp_sendgrid_api_key" 
                                   value="<?php echo esc_attr(isset($settings['sendgrid_api_key']) ? $settings['sendgrid_api_key'] : ''); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div id="prowpsmtp-mailgun-settings" class="prowpsmtp-mailer-settings" style="display: <?php echo $current_mailer === 'mailgun' ? 'block' : 'none'; ?>;">
            <h2><?php _e('Configuración Mailgun', 'pro-wp-smtp'); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="prowpsmtp_mailgun_api_key"><?php _e('API Key', 'pro-wp-smtp'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="prowpsmtp_mailgun_api_key" id="prowpsmtp_mailgun_api_key" 
                                   value="<?php echo esc_attr(isset($settings['mailgun_api_key']) ? $settings['mailgun_api_key'] : ''); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="prowpsmtp_mailgun_domain"><?php _e('Domain Name', 'pro-wp-smtp'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="prowpsmtp_mailgun_domain" id="prowpsmtp_mailgun_domain" 
                                   value="<?php echo esc_attr(isset($settings['mailgun_domain']) ? $settings['mailgun_domain'] : ''); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="prowpsmtp_mailgun_region"><?php _e('Region', 'pro-wp-smtp'); ?></label>
                        </th>
                        <td>
                            <select name="prowpsmtp_mailgun_region" id="prowpsmtp_mailgun_region">
                                <option value="us" <?php selected(isset($settings['mailgun_region']) ? $settings['mailgun_region'] : 'us', 'us'); ?>>US</option>
                                <option value="eu" <?php selected(isset($settings['mailgun_region']) ? $settings['mailgun_region'] : 'us', 'eu'); ?>>EU</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <p class="submit">
            <input type="submit" name="prowpsmtp_save_settings" class="button button-primary" value="<?php _e('Guardar Configuración', 'pro-wp-smtp'); ?>">
        </p>
    </form>
    
    <div class="prowpsmtp-pro-notice">
        <h3>✅ <?php _e('Funciones PRO Activadas', 'pro-wp-smtp'); ?></h3>
        <ul>
            <li>✓ <?php _e('Email Logging - Registro completo de correos enviados', 'pro-wp-smtp'); ?></li>
            <li>✓ <?php _e('Email Tracking - Seguimiento de emails', 'pro-wp-smtp'); ?></li>
            <li>✓ <?php _e('Reportes y Estadísticas - Análisis detallado', 'pro-wp-smtp'); ?></li>
            <li>✓ <?php _e('Múltiples Proveedores - SMTP, Gmail, SendGrid, Mailgun', 'pro-wp-smtp'); ?></li>
            <li>✓ <?php _e('Sin límites - Todas las funciones disponibles', 'pro-wp-smtp'); ?></li>
        </ul>
    </div>
</div>
