<?php
if (!defined('ABSPATH')) exit;

// Añadir menú de licencia en Ajustes
add_action('admin_menu', function() {
    add_options_page(
        'Licencia Yoast SEO Premium GPL',
        'Licencia Yoast SEO Premium',
        'manage_options',
        'yoast-seo-gpl-license',
        'yoast_seo_gpl_license_page'
    );
});

// Página de configuración de licencia
function yoast_seo_gpl_license_page() {
    $api_key = get_option('yoast_seo_gpl_api_key', '');
    $status = get_option('yoast_seo_gpl_key_status', 'inactive');
    $expiry = get_option('plugin_updater_expiry', '');
    $activation_count = get_option('yoast_seo_gpl_activation_count', 0);
    $max_activations = get_option('yoast_seo_gpl_max_activations', 0);
    $remaining_activations = get_option('yoast_seo_gpl_remaining_activations', 0);
    
    ?>
    <div class="wrap">
        <h1>🔑 Licencia Yoast SEO Premium GPL</h1>
        
        <div class="card" style="max-width: 800px;">
            <h2>Estado de la Licencia</h2>
            
            <?php if ($status === 'active' && !empty($api_key)): ?>
                <div class="notice notice-success inline">
                    <p><strong>✅ Su API Key está ACTIVA.</strong></p>
                </div>
                
                <?php if (!empty($expiry)): ?>
                    <p><strong>Válida hasta:</strong> <?php echo esc_html(date('d/m/Y', strtotime($expiry))); ?></p>
                <?php endif; ?>
                
                <?php if ($max_activations > 0): ?>
                    <p><strong>Activaciones:</strong> <?php echo esc_html($activation_count); ?> / <?php echo esc_html($max_activations); ?></p>
                    
                    <?php if ($remaining_activations <= 3 && $remaining_activations > 0): ?>
                        <div class="notice notice-warning inline">
                            <p>⚠️ <strong>Le quedan <?php echo esc_html($remaining_activations); ?> activaciones.</strong></p>
                        </div>
                    <?php elseif ($remaining_activations <= 0): ?>
                        <div class="notice notice-warning inline">
                            <p>⚠️ <strong>No le quedan activaciones disponibles.</strong></p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p><strong>Activaciones:</strong> Ilimitadas</p>
                <?php endif; ?>
                
                <p><strong>API Key:</strong> <code><?php echo esc_html(substr($api_key, 0, 20)) . '...'; ?></code></p>
                
                <form method="post" id="yoast-seo-gpl-deactivate-form" style="margin-top: 20px;">
                    <?php wp_nonce_field('yoast_seo_gpl_nonce', 'security'); ?>
                    <button type="submit" class="button button-secondary" id="yoast-seo-gpl-deactivate-btn">
                        Desactivar Licencia
                    </button>
                </form>
                
            <?php else: ?>
                <div class="notice notice-warning inline">
                    <p><strong>⚠️ No hay ninguna API Key activa.</strong></p>
                </div>
                
                <form method="post" id="yoast-seo-gpl-activate-form" style="margin-top: 20px;">
                    <?php wp_nonce_field('yoast_seo_gpl_nonce', 'security'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="api_key">API Key</label>
                            </th>
                            <td>
                                <input 
                                    type="text" 
                                    id="api_key" 
                                    name="api_key" 
                                    class="regular-text" 
                                    placeholder="Introduce tu API Key"
                                    required
                                >
                                <p class="description">
                                    Obtén tu API Key desde <a href="https://actualizarplugins.online" target="_blank" rel="noopener noreferrer">actualizarplugins.online</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary" id="yoast-seo-gpl-activate-btn">
                            Activar Licencia
                        </button>
                    </p>
                </form>
            <?php endif; ?>
        </div>
        
        <div id="yoast-seo-gpl-message" style="margin-top: 20px;"></div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Activar licencia
        $('#yoast-seo-gpl-activate-form').on('submit', function(e) {
            e.preventDefault();
            
            var $btn = $('#yoast-seo-gpl-activate-btn');
            var originalText = $btn.text();
            $btn.text('Validando...').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'yoast_seo_gpl_validate_key',
                    security: $('#security').val(),
                    api_key: $('#api_key').val()
                },
                success: function(response) {
                    if (response.success) {
                        $('#yoast-seo-gpl-message').html(
                            '<div class="notice notice-success"><p>' + response.data.message + '</p></div>'
                        );
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $('#yoast-seo-gpl-message').html(
                            '<div class="notice notice-error"><p>' + response.data.message + '</p></div>'
                        );
                        $btn.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    $('#yoast-seo-gpl-message').html(
                        '<div class="notice notice-error"><p>Error de conexión.</p></div>'
                    );
                    $btn.text(originalText).prop('disabled', false);
                }
            });
        });
        
        // Desactivar licencia
        $('#yoast-seo-gpl-deactivate-form').on('submit', function(e) {
            e.preventDefault();
            
            if (!confirm('¿Estás seguro de desactivar la licencia?')) {
                return;
            }
            
            var $btn = $('#yoast-seo-gpl-deactivate-btn');
            $btn.text('Desactivando...').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'yoast_seo_gpl_deactivate_key',
                    security: $('#security').val()
                },
                success: function(response) {
                    if (response.success) {
                        $('#yoast-seo-gpl-message').html(
                            '<div class="notice notice-success"><p>' + response.data.message + '</p></div>'
                        );
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    }
                },
                error: function() {
                    $('#yoast-seo-gpl-message').html(
                        '<div class="notice notice-error"><p>Error al desactivar.</p></div>'
                    );
                    $btn.text('Desactivar Licencia').prop('disabled', false);
                }
            });
        });
    });
    </script>
    
    <style>
    .card { padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
    .card h2 { margin-top: 0; }
    </style>
    <?php
}
