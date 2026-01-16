<?php
/**
 * Plugin Name:       Notificacion Plugins Actualizables
 * Plugin URI:        https://plugins-wp.online
 * Description:       Sistema de notificación de actualizaciones disponibles para plugins GPL premium
 * Version:           2.5.5
 * Author:            Navas
 * Author URI:        https://plugins-wp.online
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       notificacion-plugins-actualizables
 * Domain Path:       /languages
 * Requires PHP:      7.2
 * Requires at least: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constantes del plugin
define( 'NPA_VERSION', '2.5.5' );
define( 'NPA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NPA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NPA_API_URL', 'https://plugins-wp.online/check-updates.php' );
define( 'NPA_API_KEY', 'GPL-2024-PREMIUM-ACCESS' );

// Cargar clase de verificación de actualizaciones
require_once NPA_PLUGIN_DIR . 'includes/class-update-checker.php';

/**
 * Inicializar el plugin
 */
function npa_init() {
    NPA_Update_Checker::init();
}
add_action( 'plugins_loaded', 'npa_init' );

/**
 * Activación del plugin
 */
function npa_activate() {
    // Programar verificación cada 12 horas
    if ( ! wp_next_scheduled( 'npa_check_updates' ) ) {
        wp_schedule_event( time(), 'twicedaily', 'npa_check_updates' );
    }
    
    // Verificar actualizaciones inmediatamente
    NPA_Update_Checker::check_updates();
}
register_activation_hook( __FILE__, 'npa_activate' );

/**
 * Desactivación del plugin
 */
function npa_deactivate() {
    // Cancelar cron job
    wp_clear_scheduled_hook( 'npa_check_updates' );
}
register_deactivation_hook( __FILE__, 'npa_deactivate' );
