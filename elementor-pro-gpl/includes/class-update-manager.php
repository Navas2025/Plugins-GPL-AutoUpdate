<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gestor de Actualizaciones para plugins GPL a través de un Servidor de API Key.
 */
class Elementor_Pro_GPL_Update_Manager {

    /**
     * El slug y el archivo principal del plugin.
     * El nombre del archivo se ajusta a la carpeta/archivo que usaste.
     */
    const PLUGIN_SLUG = 'elementor-pro-gpl';
    const PLUGIN_FILE = 'elementor-pro-gpl/elementor-pro-gpl.php'; // Ajustado al slug/nombre del archivo
    
    /**
     * Inicializa los hooks de WordPress.
     */
    public function __construct() {
        add_filter( 'site_transient_update_plugins', [ $this, 'check_for_plugin_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugins_api_call' ], 10, 3 );
        add_filter( 'upgrader_package_options', [ $this, 'add_api_key_to_download_url' ] );
    }

    /**
     * Comprueba si hay una nueva versión disponible en el servidor.
     * @param object $transient
     * @return object
     */
    public function check_for_plugin_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }
        
        $plugin_file = self::PLUGIN_FILE;
        $current_version = $transient->checked[ $plugin_file ] ?? false;
        $api_key = get_option('elementor_pro_gpl_api_key', '');
        $status = get_option('elementor_pro_gpl_key_status', 'inactive');
        $expiry_date = get_option('plugin_updater_expiry', '');
        
        // Verificar caducidad en tiempo real antes de permitir actualizaciones
        if ($status === 'active' && !empty($expiry_date)) {
            $expiry_timestamp = strtotime($expiry_date);
            $current_timestamp = current_time('timestamp');
            
            if ($current_timestamp >= $expiry_timestamp) {
                $status = 'expired'; // Marcar como caducado
                update_option('elementor_pro_gpl_key_status', $status); // Actualizar la opción
            }
        }

        // Si no hay versión actual, API key, o no está activo, salir.
        if ( ! $current_version || empty( $api_key ) || $status !== 'active' ) {
            return $transient;
        }

        $server_url = defined('ELEMENTOR_PRO_GPL_UPDATE_SERVER') ? ELEMENTOR_PRO_GPL_UPDATE_SERVER : 'https://actualizarplugins.online/api/';
        
        $args = [
            'action' => 'get_latest_version',
            'plugin_slug' => self::PLUGIN_SLUG,
            'api_key' => $api_key,
            'site_url' => home_url(),
            'current_version' => $current_version,
        ];

        // Headers HTTP Críticos (incluyendo User-Agent)
        $headers = [
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        ];
        
        $response = wp_remote_get( add_query_arg( $args, $server_url . 'get-plugins.php' ), [ 'timeout' => 10, 'headers' => $headers ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return $transient;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        // Si la respuesta es válida y la versión es superior (ej. 3.33.1 > 3.33.0)
        if ( isset( $data['new_version'] ) && version_compare( $current_version, $data['new_version'], '<' ) ) {
            
            $transient->response[ $plugin_file ] = (object) [
                'slug'        => self::PLUGIN_SLUG,
                'plugin'      => $plugin_file,
                'new_version' => $data['new_version'],
                'url'         => $data['details_url'] ?? $server_url . 'plugin-info.php?plugin=' . self::PLUGIN_SLUG,
                'package'     => add_query_arg([
                    'apiKey' => $api_key,
                    'slug'   => self::PLUGIN_SLUG
                ], $server_url . 'download-plugin.php'), // URL de descarga autenticada
                'tested'      => $data['tested_up_to'] ?? '6.6',
                'requires'    => $data['requires'] ?? '6.0',
            ];
        }

        return $transient;
    }

    /**
     * Muestra la información del plugin en la modal de WordPress.
     */
    public function plugins_api_call( $result, $action, $args ) {
        // Usa el slug corregido
        if ( $action !== 'plugin_information' || $args->slug !== self::PLUGIN_SLUG ) {
            return $result;
        }
        
        $api_key = get_option('elementor_pro_gpl_api_key', '');
        $status = get_option('elementor_pro_gpl_key_status', 'inactive');
        $expiry_date = get_option('plugin_updater_expiry', '');
        
        // Verificar caducidad en tiempo real antes de permitir actualizaciones
        if ($status === 'active' && !empty($expiry_date)) {
            $expiry_timestamp = strtotime($expiry_date);
            $current_timestamp = current_time('timestamp');
            
            if ($current_timestamp >= $expiry_timestamp) {
                $status = 'expired'; // Marcar como caducado
                update_option('elementor_pro_gpl_key_status', $status); // Actualizar la opción
            }
        }
        
        if (empty($api_key) || $status !== 'active') {
            return $result;
        }

        $server_url = defined('ELEMENTOR_PRO_GPL_UPDATE_SERVER') ? ELEMENTOR_PRO_GPL_UPDATE_SERVER : 'https://actualizarplugins.online/api/';
        
        $url = add_query_arg([
            'action' => 'plugin_information',
            'plugin_slug' => self::PLUGIN_SLUG,
            'api_key' => $api_key
        ], $server_url . 'plugin-info.php');

        // Headers HTTP Críticos
        $headers = [
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        ];
        
        $response = wp_remote_get( $url, [ 'timeout' => 10, 'headers' => $headers ] );
        
        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            
            if ( isset( $data['success'] ) && $data['success'] && isset($data['data']) ) {
                return (object) $data['data'];
            }
        }
        
        return $result;
    }
    
    /**
     * Asegura que la URL de descarga del paquete contenga la API Key.
     */
    public function add_api_key_to_download_url( $options ) {
        // Asegura que solo se aplique a la URL de descarga de este plugin
        if ( isset( $options['package'] ) && strpos( $options['package'], 'download-plugin.php' ) !== false && strpos( $options['package'], 'slug=' . self::PLUGIN_SLUG ) !== false ) {
            $api_key = get_option('elementor_pro_gpl_api_key', '');
            if ( ! empty( $api_key ) ) {
                $options['package'] = add_query_arg( 'apiKey', $api_key, $options['package'] );
            }
        }
        return $options;
    }
}

// Inicializar el gestor de actualizaciones
new Elementor_Pro_GPL_Update_Manager();