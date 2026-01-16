<?php
/**
 * Verificador de actualizaciones de plugins
 * 
 * @package Notificacion_Plugins_Actualizables
 * @version 2.5.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NPA_Update_Checker {
    
    /**
     * Inicializar hooks
     */
    public static function init() {
        // Hook para mostrar notificaciones en admin
        add_action( 'admin_notices', array( __CLASS__, 'show_update_notices' ) );
        
        // Hook para verificar actualizaciones (cron)
        add_action( 'npa_check_updates', array( __CLASS__, 'check_updates' ) );
        
        // AJAX para ocultar avisos por 12 horas
        add_action( 'wp_ajax_npa_hide_notice_12h', array( __CLASS__, 'ajax_hide_notice_12h' ) );
        
        // Scripts y estilos para el admin
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
    }
    
    /**
     * Verificar actualizaciones disponibles desde el servidor
     */
    public static function check_updates() {
        // Obtener lista de plugins instalados
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $installed_plugins = get_plugins();
        $plugins_data = array();
        
        foreach ( $installed_plugins as $plugin_file => $plugin_info ) {
            $slug = dirname( $plugin_file );
            if ( $slug === '.' ) {
                $slug = basename( $plugin_file, '.php' );
            }
            
            $plugins_data[] = array(
                'name'    => $plugin_info['Name'],
                'slug'    => $slug,
                'version' => $plugin_info['Version']
            );
        }
        
        // Preparar datos para enviar (SIN ENCRIPTAR)
        $request_data = array(
            'api_key' => NPA_API_KEY,
            'plugins' => $plugins_data,
            'site_url' => home_url(),
            'wp_version' => get_bloginfo( 'version' )
        );
        
        // Enviar petición al servidor
        $response = wp_remote_post( NPA_API_URL, array(
            'timeout' => 15,
            'body'    => json_encode( $request_data ),
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        ) );
        
        // Manejar respuesta
        if ( is_wp_error( $response ) ) {
            update_option( 'npa_last_error', $response->get_error_message() );
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( ! empty( $data['updates'] ) && is_array( $data['updates'] ) ) {
            update_option( 'npa_available_updates', $data['updates'] );
            update_option( 'npa_last_check', current_time( 'mysql' ) );
            delete_option( 'npa_last_error' );
            return true;
        }
        
        // Si no hay actualizaciones, limpiar
        update_option( 'npa_available_updates', array() );
        update_option( 'npa_last_check', current_time( 'mysql' ) );
        return false;
    }
    
    /**
     * Mostrar avisos de actualizaciones disponibles
     */
    public static function show_update_notices() {
        // Verificar si el usuario puede ver notificaciones
        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }
        
        // Verificar si están ocultos temporalmente (12 horas)
        $hidden_until = get_option( 'npa_notices_hidden_until', 0 );
        if ( $hidden_until && time() < $hidden_until ) {
            return;
        }
        
        // Obtener actualizaciones disponibles
        $updates = get_option( 'npa_available_updates', array() );
        
        if ( empty( $updates ) || ! is_array( $updates ) ) {
            return;
        }
        
        // Obtener plugins instalados para comparar versiones
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $installed_plugins = get_plugins();
        
        // Filtrar: solo mostrar actualizaciones donde versión instalada < versión disponible
        $updates_to_show = array();
        
        foreach ( $updates as $update ) {
            // Validar campos necesarios
            if ( empty( $update['slug'] ) || empty( $update['new_version'] ) ) {
                continue;
            }
            
            $plugin_slug = $update['slug'];
            $new_version = $update['new_version'];
            
            // Buscar versión instalada del plugin
            $installed_version = null;
            foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
                $current_slug = dirname( $plugin_file );
                if ( $current_slug === '.' ) {
                    $current_slug = basename( $plugin_file, '.php' );
                }
                
                if ( $current_slug === $plugin_slug ) {
                    $installed_version = $plugin_data['Version'];
                    break;
                }
            }
            
            // Solo mostrar si: plugin instalado Y versión instalada < versión nueva
            if ( $installed_version && version_compare( $installed_version, $new_version, '<' ) ) {
                $updates_to_show[] = $update;
            }
        }
        
        // Si no hay actualizaciones relevantes, no mostrar nada
        if ( empty( $updates_to_show ) ) {
            return;
        }
        
        $count = count( $updates_to_show );
        $nonce = wp_create_nonce( 'npa_hide_notice_nonce' );
        
        ?>
        <div class="notice notice-warning npa-update-notice" id="npa-update-notice">
            <p><strong>🔔 Actualizaciones Disponibles (<?php echo $count; ?>)</strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach ( $updates_to_show as $update ) : ?>
                    <li>
                        <strong><?php echo esc_html( $update['name'] ); ?></strong> 
                        v<?php echo esc_html( $update['current_version'] ); ?> → 
                        <strong>v<?php echo esc_html( $update['new_version'] ); ?></strong>
                        <?php if ( ! empty( $update['download_url'] ) ) : ?>
                            <a href="<?php echo esc_url( $update['download_url'] ); ?>" target="_blank">Ver detalles</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p>
                <button type="button" class="button button-secondary" id="npa-hide-12h" data-nonce="<?php echo esc_attr( $nonce ); ?>">
                    ⏰ Ocultar por 12 horas
                </button>
            </p>
        </div>
        <?php
    }
    
    /**
     * AJAX: Ocultar avisos por 12 horas
     */
    public static function ajax_hide_notice_12h() {
        // Verificar nonce
        check_ajax_referer( 'npa_hide_notice_nonce', 'nonce' );
        
        // Verificar permisos
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array( 'message' => 'Permiso denegado' ) );
        }
        
        // Establecer tiempo de ocultación (12 horas)
        $hidden_until = time() + ( 12 * HOUR_IN_SECONDS );
        update_option( 'npa_notices_hidden_until', $hidden_until );
        
        wp_send_json_success( array( 
            'message' => 'Avisos ocultados por 12 horas',
            'hidden_until' => $hidden_until
        ) );
    }
    
    /**
     * Cargar scripts y estilos en admin
     */
    public static function enqueue_scripts( $hook ) {
        // CSS inline para el aviso
        wp_add_inline_style( 'wp-admin', '
            .npa-update-notice {
                border-left-color: #f0ad4e !important;
                padding: 12px 20px !important;
            }
            .npa-update-notice ul {
                margin-top: 8px;
                margin-bottom: 12px;
            }
            .npa-update-notice li {
                margin-bottom: 5px;
            }
            #npa-hide-12h {
                margin-top: 8px;
            }
            #npa-hide-12h:hover {
                background: #f0ad4e;
                border-color: #f0ad4e;
                color: #fff;
            }
        ' );
        
        // JavaScript inline para el botón
        wp_add_inline_script( 'wp-util', "
            (function($) {
                $(document).ready(function() {
                    $('#npa-hide-12h').on('click', function(e) {
                        e.preventDefault();
                        var btn = $(this);
                        var nonce = btn.data('nonce');
                        
                        btn.prop('disabled', true).text('Ocultando...');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'npa_hide_notice_12h',
                                nonce: nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    $('#npa-update-notice').fadeOut(300, function() {
                                        $(this).remove();
                                    });
                                } else {
                                    var errorMsg = response.data && response.data.message ? response.data.message : 'Error desconocido';
                                    alert('Error: ' + errorMsg);
                                    btn.prop('disabled', false).text('⏰ Ocultar por 12 horas');
                                }
                            },
                            error: function() {
                                alert('Error de conexión');
                                btn.prop('disabled', false).text('⏰ Ocultar por 12 horas');
                            }
                        });
                    });
                });
            })(jQuery);
        " );
    }
}
