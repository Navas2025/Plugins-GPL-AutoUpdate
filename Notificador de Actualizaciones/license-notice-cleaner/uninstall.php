<?php
/**
 * Uninstall handler for License Notice Cleaner
 * 
 * This file is called when the plugin is uninstalled from WordPress.
 * It cleans up all plugin data including options and scheduled events.
 */

// Exit if accessed directly or not during uninstall
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete all plugin options
delete_option( 'lnc_available_updates' );
delete_option( 'lnc_last_check' );
delete_option( 'lnc_last_error' );
delete_option( 'lnc_last_response' );
delete_option( 'lnc_dismissed_updates' );

// Clear all scheduled cron jobs
wp_clear_scheduled_hook( 'lnc_check_updates' );

// For multisite installations, clean up for all sites
if ( is_multisite() ) {
    global $wpdb;
    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
    
    foreach ( $blog_ids as $blog_id ) {
        switch_to_blog( $blog_id );
        
        // Delete options for this site
        delete_option( 'lnc_available_updates' );
        delete_option( 'lnc_last_check' );
        delete_option( 'lnc_last_error' );
        delete_option( 'lnc_last_response' );
        delete_option( 'lnc_dismissed_updates' );
        
        // Clear scheduled events for this site
        wp_clear_scheduled_hook( 'lnc_check_updates' );
        
        restore_current_blog();
    }
}
