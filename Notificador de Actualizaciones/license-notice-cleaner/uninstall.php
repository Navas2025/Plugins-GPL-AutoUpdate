<?php
/**
 * Uninstall Script
 * Limpia todos los datos del plugin al desinstalar
 */

// Si no se llamó desde WordPress, salir
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Eliminar todas las opciones almacenadas
delete_option( 'lnc_available_updates' );
delete_option( 'lnc_last_check' );
delete_option( 'lnc_last_error' );
delete_option( 'lnc_last_response' );
delete_option( 'lnc_dismissed_updates' );

// Cancelar el cron job programado
wp_clear_scheduled_hook( 'lnc_check_updates' );
