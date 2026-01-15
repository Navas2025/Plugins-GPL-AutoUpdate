<?php
/**
 * Plugin Name:       Notificacion Plugins Actualizables
 * Plugin URI:        https://plugins-wp.online
 * Description:       Sistema de notificación de actualizaciones disponibles para plugins con seguridad mejorada
 * Version:           2.5.4
 * Author:            Navas
 * Author URI:        https://plugins-wp.online
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       notificacion-plugins-actualizables
 * Requires PHP:      7.2
 * Requires at least: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Constantes
define( 'NPA_VERSION', '2.5.4' );
define( 'NPA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Obtener URL de la API (ofuscada)
 * @return string
 */
function npa_get_api_url() {
    // URL ofuscada en base64: https://plugins-wp.online/check-updates.php
    return base64_decode( 'aHR0cHM6Ly9wbHVnaW5zLXdwLm9ubGluZS9jaGVjay11cGRhdGVzLnBocA==' );
}

/**
 * Obtener API Key (ofuscada)
 * @return string
 */
function npa_get_api_key() {
    // API Key ofuscada: GPL-2024-PREMIUM-ACCESS
    // Paso 1: base64 encode
    $encoded = 'VkNDLTIwMjQtQ0VSVFZVTS1BUFBSRkZG';
    // Paso 2: decode
    $decoded = base64_decode( $encoded );
    // Paso 3: rot13 para ofuscar más
    return str_rot13( $decoded );
}

/**
 * Obtener clave de encriptación
 * @return string
 */
function npa_get_encryption_key() {
    // Generar clave de 32 bytes basada en API key
    return hash( 'sha256', npa_get_api_key() );
}

// Cargar clase principal
require_once NPA_PLUGIN_DIR . 'includes/class-update-checker.php';

// Inicializar
add_action( 'plugins_loaded', 'npa_init' );

function npa_init() {
    NPA_Update_Checker::init();
}

// Activación
register_activation_hook( __FILE__, 'npa_activate' );

function npa_activate() {
    // Programar verificación cada 12 horas
    if ( ! wp_next_scheduled( 'npa_check_updates' ) ) {
        wp_schedule_event( time(), 'twicedaily', 'npa_check_updates' );
    }
    
    // Verificar inmediatamente
    NPA_Update_Checker::check_updates();
}

// Desactivación
register_deactivation_hook( __FILE__, 'npa_deactivate' );

function npa_deactivate() {
    wp_clear_scheduled_hook( 'npa_check_updates' );
}
