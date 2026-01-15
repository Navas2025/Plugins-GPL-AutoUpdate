<?php
/**
 * Verificador de actualizaciones con seguridad mejorada
 * 
 * @package Notificacion_Plugins_Actualizables
 * @version 2.5.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class NPA_Update_Checker {
    
    /**
     * Inicializar hooks
     */
    public static function init() {
        // Verificación automática cada 12 horas
        add_action( 'npa_check_updates', array( __CLASS__, 'check_updates' ) );
        
        // Verificar al cargar admin (si han pasado 12 horas)
        add_action( 'admin_init', array( __CLASS__, 'maybe_check_updates' ) );
        
        // Mostrar avisos de actualizaciones
        add_action( 'admin_notices', array( __CLASS__, 'show_update_notices' ) );
        
        // Cargar scripts para botón "Ocultar por 12 horas"
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_snooze_scripts' ) );
        
        // AJAX para ocultar avisos
        add_action( 'wp_ajax_npa_snooze_notices', array( __CLASS__, 'ajax_snooze_notices' ) );
    }
    
    /**
     * Verificar si es momento de actualizar
     */
    public static function maybe_check_updates() {
        $last_check = get_option( 'npa_last_check', 0 );
        
        // Verificar cada 12 horas
        if ( time() - $last_check > ( 12 * HOUR_IN_SECONDS ) ) {
            self::check_updates();
        }
    }
    
    /**
     * Verificar actualizaciones disponibles con encriptación
     */
    public static function check_updates() {
        // Obtener plugins instalados
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $installed_plugins = get_plugins();
        $plugins_data = array();
        
        foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
            $slug = dirname( $plugin_file );
            if ( $slug === '.' ) {
                $slug = basename( $plugin_file, '.php' );
            }
            
            $plugins_data[] = array(
                'slug' => $slug,
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version']
            );
        }
        
        // Preparar datos para encriptar
        $data_json = json_encode( array(
            'plugins' => $plugins_data,
            'site_url' => home_url(),
            'timestamp' => time()
        ) );
        
        // Encriptar datos
        $encryption_key = npa_get_encryption_key();
        $iv = random_bytes( 16 );
        
        $encrypted_data = openssl_encrypt(
            $data_json,
            'AES-256-CBC',
            $encryption_key,
            0,
            $iv
        );
        
        // Generar firma HMAC para verificar integridad
        $signature = hash_hmac( 'sha256', $encrypted_data, $encryption_key );
        
        // Enviar solicitud encriptada
        $response = wp_remote_post( npa_get_api_url(), array(
            'timeout' => 15,
            'body' => array(
                'action' => 'check_updates',
                'api_key' => npa_get_api_key(),
                'data' => base64_encode( $encrypted_data ),
                'iv' => base64_encode( $iv ),
                'signature' => $signature
            )
        ) );
        
        if ( is_wp_error( $response ) ) {
            update_option( 'npa_last_check', time() );
            return;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( isset( $data['success'] ) && $data['success'] && isset( $data['updates'] ) ) {
            update_option( 'npa_available_updates', $data['updates'] );
            
            // Si hay nuevas actualizaciones, resetear el snooze
            delete_option( 'npa_snoozed_until' );
        } else {
            update_option( 'npa_available_updates', array() );
        }
        
        update_option( 'npa_last_check', time() );
    }
    
    /**
     * Cargar JavaScript para el botón "Ocultar por 12 horas"
     */
    public static function enqueue_snooze_scripts() {
        // Solo cargar si hay actualizaciones
        $updates = get_option( 'npa_available_updates', array() );
        if ( empty( $updates ) ) {
            return;
        }
        
        // Verificar si están ocultas temporalmente
        $snoozed_until = get_option( 'npa_snoozed_until', 0 );
        if ( time() < $snoozed_until ) {
            return;
        }
        
        // JavaScript inline
        $js = "
        jQuery(document).ready(function($) {
            $(document).on('click', '.npa-snooze-button', function(e) {
                e.preventDefault();
                
                var button = $(this);
                button.prop('disabled', true).text('Ocultando...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'npa_snooze_notices',
                        nonce: '" . wp_create_nonce( 'npa_snooze_nonce' ) . "'
                    },
                    success: function(response) {
                        if (response.success) {
                            button.closest('.notice').fadeOut(300, function() {
                                $(this).remove();
                            });
                            
                            $('<div class=\"notice notice-success is-dismissible\" style=\"display:none;\"><p><strong>✅ Avisos ocultos por 12 horas.</strong> Volverán a aparecer automáticamente.</p></div>')
                                .insertAfter('.wp-header-end')
                                .fadeIn(300)
                                .delay(3000)
                                .fadeOut(300, function() { $(this).remove(); });
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text('⏰ Ocultar por 12 horas');
                        alert('Error al ocultar avisos. Inténtalo de nuevo.');
                    }
                });
            });
        });
        ";
        
        wp_add_inline_script( 'jquery', $js );
        
        // CSS inline para el botón
        $css = "
        .npa-snooze-button {
            float: right;
            margin-left: 10px;
            padding: 3px 10px;
            background: #f0f0f1;
            border: 1px solid #c3c4c7;
            border-radius: 3px;
            cursor: pointer;
            font-size: 13px;
            color: #2c3338;
            text-decoration: none;
            transition: all 0.2s;
        }
        .npa-snooze-button:hover {
            background: #fff;
            border-color: #8c8f94;
            color: #1d2327;
        }
        .npa-snooze-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        ";
        
        wp_add_inline_style( 'common', $css );
    }
    
    /**
     * AJAX: Ocultar avisos por 12 horas
     */
    public static function ajax_snooze_notices() {
        // Verificar nonce
        if ( ! check_ajax_referer( 'npa_snooze_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Nonce inválido' ) );
        }
        
        // Verificar permisos
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array( 'message' => 'Sin permisos' ) );
        }
        
        // Guardar timestamp (12 horas = 43200 segundos)
        $snooze_until = time() + ( 12 * HOUR_IN_SECONDS );
        update_option( 'npa_snoozed_until', $snooze_until );
        
        wp_send_json_success( array( 
            'message' => 'Avisos ocultos por 12 horas',
            'until' => date( 'Y-m-d H:i:s', $snooze_until )
        ) );
    }
    
    /**
     * Mostrar avisos de actualizaciones disponibles
     */
    public static function show_update_notices() {
        // Verificar si están ocultas temporalmente
        $snoozed_until = get_option( 'npa_snoozed_until', 0 );
        if ( time() < $snoozed_until ) {
            return;
        }
        
        $updates = get_option( 'npa_available_updates', array() );
        
        if ( empty( $updates ) ) {
            return;
        }
        
        // Obtener plugins instalados para comparar versiones
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $installed_plugins = get_plugins();
        
        // Filtrar: solo mostrar si versión instalada < versión disponible
        $updates_to_show = array();
        
        foreach ( $updates as $update ) {
            if ( empty( $update['slug'] ) || empty( $update['new_version'] ) ) {
                continue;
            }
            
            $plugin_slug = $update['slug'];
            $new_version = $update['new_version'];
            
            // Buscar versión instalada
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
        
        if ( empty( $updates_to_show ) ) {
            return;
        }
        
        $count = count( $updates_to_show );
        
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>🔔 Actualizaciones Disponibles (' . $count . ')</strong>';
        echo '<button type="button" class="npa-snooze-button">⏰ Ocultar por 12 horas</button>';
        echo '</p>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        
        foreach ( $updates_to_show as $update ) {
            echo '<li>';
            echo '<strong>' . esc_html( isset( $update['name'] ) ? $update['name'] : '' ) . '</strong> ';
            
            if ( isset( $update['current_version'] ) ) {
                echo 'v' . esc_html( $update['current_version'] ) . ' → ';
            }
            
            if ( isset( $update['new_version'] ) ) {
                echo '<strong>v' . esc_html( $update['new_version'] ) . '</strong>';
            }
            
            if ( ! empty( $update['download_url'] ) ) {
                echo ' <a href="' . esc_url( $update['download_url'] ) . '" target="_blank">Ver detalles</a>';
            }
            
            echo '</li>';
        }
        
        echo '</ul>';
        echo '</div>';
    }
}
