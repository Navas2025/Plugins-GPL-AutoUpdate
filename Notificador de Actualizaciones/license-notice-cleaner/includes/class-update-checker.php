<?php
/**
 * Verificador de actualizaciones
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LNC_Update_Checker {

    public static function init() {
        // Verificación automática cada 12 horas
        add_action( 'lnc_check_updates', array( __CLASS__, 'check_updates' ) );
        
        // Verificar al cargar admin (si han pasado 12 horas)
        add_action( 'admin_init', array( __CLASS__, 'maybe_check_updates' ) );
        
        // Mostrar avisos de actualizaciones
        add_action( 'admin_notices', array( __CLASS__, 'show_update_notices' ) );
    }

    /**
     * Verificar si es momento de comprobar actualizaciones
     */
    public static function maybe_check_updates() {
        $last_check = get_option( 'lnc_last_check', 0 );
        $check_interval = 12 * HOUR_IN_SECONDS;
        
        if ( ( time() - $last_check ) > $check_interval ) {
            self::check_updates();
        }
    }

    /**
     * Verificar actualizaciones disponibles
     */
    public static function check_updates() {
        $installed_plugins = self::get_installed_plugins();
        
        if ( empty( $installed_plugins ) ) {
            return;
        }
        
        $response = wp_remote_post( LNC_API_URL, array(
            'timeout' => 15,
            'body' => array(
                'action' => 'check_updates',
                'api_key' => LNC_API_KEY,
                'plugins' => json_encode( $installed_plugins )
            )
        ) );
        
        if ( is_wp_error( $response ) ) {
            update_option( 'lnc_last_error', $response->get_error_message() );
            return;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        // Guardar respuesta completa para debug
        update_option( 'lnc_last_response', array(
            'time' => current_time( 'mysql' ),
            'http_code' => wp_remote_retrieve_response_code( $response ),
            'body' => $body,
            'data' => $data
        ) );
        
        if ( isset( $data['success'] ) && $data['success'] && isset( $data['updates'] ) ) {
            update_option( 'lnc_available_updates', $data['updates'] );
        } else {
            update_option( 'lnc_available_updates', array() );
        }
        
        update_option( 'lnc_last_check', time() );
    }

    /**
     * Obtener lista de plugins instalados
     */
    private static function get_installed_plugins() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $installed = array();
        
        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            $slug = dirname( $plugin_file );
            if ( $slug === '.' ) {
                $slug = basename( $plugin_file, '.php' );
            }
            
            $installed[] = array(
                'slug' => $slug,
                'version' => $plugin_data['Version'],
                'name' => $plugin_data['Name']
            );
        }
        
        return $installed;
    }

    /**
     * Mostrar avisos de actualizaciones disponibles
     */
    public static function show_update_notices() {
        $updates = get_option( 'lnc_available_updates', array() );
        
        if ( empty( $updates ) ) {
            return;
        }
        
        // Obtener plugins instalados para comparar versiones
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $installed_plugins = get_plugins();
        
        // Filtrar actualizaciones: solo mostrar si versión instalada < versión disponible
        $updates_to_show = array();
        
        foreach ( $updates as $update ) {
            // Validate required fields
            if ( ! isset( $update['slug'] ) || ! isset( $update['new_version'] ) ) {
                continue;
            }
            
            $plugin_slug = $update['slug'];
            $new_version = $update['new_version'];
            
            // Skip if new_version is empty
            if ( empty( $new_version ) ) {
                continue;
            }
            
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
        
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>🔔 Actualizaciones Disponibles (' . $count . ')</strong></p>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        
        foreach ( $updates_to_show as $update ) {
            $name = isset( $update['name'] ) ? $update['name'] : '';
            $current_version = isset( $update['current_version'] ) ? $update['current_version'] : '';
            $new_version = isset( $update['new_version'] ) ? $update['new_version'] : '';
            $download_url = isset( $update['download_url'] ) ? $update['download_url'] : '#';
            
            echo '<li>';
            echo '<strong>' . esc_html( $name ) . '</strong> ';
            echo 'v' . esc_html( $current_version ) . ' → ';
            echo '<strong>v' . esc_html( $new_version ) . '</strong> ';
            echo '<a href="' . esc_url( $download_url ) . '" target="_blank">Ver detalles</a>';
            echo '</li>';
        }
        
        echo '</ul>';
        echo '</div>';
    }
}