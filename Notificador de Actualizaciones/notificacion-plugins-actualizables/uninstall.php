<?php
/**
 * Uninstall script - Limpieza al desinstalar el plugin
 * 
 * @package Notificacion_Plugins_Actualizables
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Eliminar opciones almacenadas (nuevos nombres)
delete_option( 'npa_available_updates' );
delete_option( 'npa_last_check' );
delete_option( 'npa_snoozed_until' );

// Eliminar opciones antiguas (por si acaso)
delete_option( 'lnc_available_updates' );
delete_option( 'lnc_last_check' );
delete_option( 'lnc_last_error' );
delete_option( 'lnc_last_response' );
delete_option( 'lnc_snoozed_until' );

// Cancelar cron jobs (nuevo y antiguo)
wp_clear_scheduled_hook( 'npa_check_updates' );
wp_clear_scheduled_hook( 'lnc_check_updates' );
