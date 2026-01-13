<?php
/**
 * Uninstall script - Limpieza al desinstalar el plugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Eliminar opciones almacenadas
delete_option( 'lnc_available_updates' );
delete_option( 'lnc_last_check' );
delete_option( 'lnc_last_error' );
delete_option( 'lnc_last_response' );

// Cancelar cron job programado
wp_clear_scheduled_hook( 'lnc_check_updates' );
