<?php
/**
 * Uninstall script - Limpieza al desinstalar el plugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Eliminar opciones almacenadas
delete_option( 'npa_available_updates' );
delete_option( 'npa_last_check' );
delete_option( 'npa_last_error' );
delete_option( 'npa_last_response' );
delete_option( 'npa_notices_hidden_until' );

// Cancelar cron job programado
wp_clear_scheduled_hook( 'npa_check_updates' );
