<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Proteger transients de licencia de ser borrados
 *
 * Evita que WP Rocket borre las opciones/transients de licencia y forzar
 * sincronización desde las opciones guardadas.
 */

if ( ! function_exists( 'wp_rocket_gpl_sync_license' ) ) {
	function wp_rocket_gpl_sync_license() {
		// Asegurar que las opciones de licencia existen en el transient si es necesario.
		$options = get_option( WP_ROCKET_SLUG, [] );

		$consumer_key = $options['consumer_key'] ?? ( get_option( 'wp_rocket_gpl_api_key', '' ) ? 'WP_ROCKET_GPL_KEY' : '' );
		$secret_key   = $options['secret_key'] ?? ( get_option( 'wp_rocket_gpl_key_status' ) === 'active' ? 'gpl_active_license' : '' );

		$options['consumer_key']   = $consumer_key;
		$options['secret_key']     = $secret_key;
		update_option( WP_ROCKET_SLUG, $options );
	}
}

// Interceptar ANTES de que WP Rocket borre los transients
add_filter('pre_delete_transient_wp_rocket_settings', function($pre_delete, $transient) {
	wp_rocket_gpl_sync_license();
	return false; // Prevenir que se borre
}, 10, 2);

add_filter('pre_delete_transient_wp_rocket_customer_data', function($pre_delete, $transient) {
	wp_rocket_gpl_sync_license();
	return false;
}, 10, 2);

// Interceptar lectura después de que se borró
add_filter('get_transient_wp_rocket_settings', function($value, $transient) {
	if ( ! $value || empty( $value['secret_key'] ) ) {
		wp_rocket_gpl_sync_license();
		return get_transient('wp_rocket_settings');
	}
	return $value;
}, 10, 2);

add_filter('get_transient_wp_rocket_customer_data', function($value, $transient) {
	if ( ! $value ) {
		wp_rocket_gpl_sync_license();
		return get_transient('wp_rocket_customer_data');
	}
	return $value;
}, 10, 2);