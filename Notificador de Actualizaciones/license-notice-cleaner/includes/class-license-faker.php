<?php
/**
 * Clase para simular licencias activas - VERSIÓN EQUILIBRADA
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LNC_License_Faker {

    public static function init() {
        // Solo interceptar peticiones HTTP, NO modificar opciones agresivamente
        add_filter( 'pre_http_request', array( __CLASS__, 'intercept_license_requests' ), 10, 3 );
        
        // Filtros específicos para plugins conocidos
        add_filter( 'yith_plugin_fw_licence_is_activated', '__return_true', 999 );
        add_filter( 'elementor/connect/is_connected', '__return_true', 999 );
        add_filter( 'woocommerce_helper_suppress_admin_notices', '__return_true', 999 );
    }

    /**
     * Interceptar peticiones HTTP de validación de licencias
     */
    public static function intercept_license_requests( $response, $args, $url ) {
        // Lista de dominios conocidos de validación
        $license_domains = array(
            'yithemes.com',
            'elementor.com',
            'freemius.com',
            'appsero.com'
        );
        
        $url_lower = strtolower( $url );
        $is_license_check = false;
        
        // Solo interceptar si es de un dominio conocido
        foreach ( $license_domains as $domain ) {
            if ( strpos( $url_lower, $domain ) !== false ) {
                // Verificar que sea una petición de licencia
                if ( strpos( $url_lower, 'license' ) !== false || 
                     strpos( $url_lower, 'activation' ) !== false ||
                     strpos( $url_lower, 'validate' ) !== false ) {
                    $is_license_check = true;
                    break;
                }
            }
        }
        
        // Devolver respuesta falsa solo si es verificación de licencia
        if ( $is_license_check ) {
            return array(
                'headers' => array( 'content-type' => 'application/json' ),
                'body' => json_encode( array(
                    'success' => true,
                    'license' => 'valid',
                    'status' => 'active',
                    'activated' => true,
                    'expires' => date( 'Y-m-d', strtotime( '+10 years' ) )
                ) ),
                'response' => array(
                    'code' => 200,
                    'message' => 'OK'
                ),
                'cookies' => array(),
                'filename' => null
            );
        }
        
        return $response;
    }
}