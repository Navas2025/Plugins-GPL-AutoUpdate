<?php
/**
 * Plugin Name:       Notificacion Plugins Actualizables
 * Plugin URI:        https://plugins-wp.online
 * Description:       Sistema de notificación de actualizaciones disponibles para plugins
 * Version:           2.5.2
 * Author:            Navas
 * Author URI:        https://plugins-wp.online
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       license-notice-cleaner
 * Requires PHP:      7.2
 * Requires at least: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Constantes
define( 'LNC_VERSION', '2.5.2' );
define( 'LNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LNC_API_URL', 'https://plugins-wp.online/check-updates.php' );
define( 'LNC_API_KEY', 'GPL-2024-PREMIUM-ACCESS' );

// Cargar SOLO el verificador de actualizaciones
require_once LNC_PLUGIN_DIR . 'includes/class-update-checker.php';

// Inicializar
add_action( 'plugins_loaded', 'lnc_init' );

function lnc_init() {
    LNC_Update_Checker::init();
}

// Activación
register_activation_hook( __FILE__, 'lnc_activate' );

function lnc_activate() {
    // Programar verificación cada 12 horas
    if ( ! wp_next_scheduled( 'lnc_check_updates' ) ) {
        wp_schedule_event( time(), 'twicedaily', 'lnc_check_updates' );
    }
    
    // Verificar inmediatamente
    LNC_Update_Checker::check_updates();
}

// Desactivación
register_deactivation_hook( __FILE__, 'lnc_deactivate' );

function lnc_deactivate() {
    wp_clear_scheduled_hook( 'lnc_check_updates' );
}