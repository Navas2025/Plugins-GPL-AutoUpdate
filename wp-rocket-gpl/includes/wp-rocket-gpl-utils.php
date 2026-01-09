<?php
/**
 * Utils for WP Rocket GPL updater (final).
 *
 * Helpers:
 *  - wp_rocket_gpl_ensure_array: normaliza arrays/objetos/JSON string
 *  - wp_rocket_gpl_array_column: wrapper seguro para array_column
 *  - wp_rocket_gpl_log_debug: registro condicional cuando WP_DEBUG && WP_DEBUG_LOG
 *
 * Colócalo en include/ (o includes/ / gpl-includes/) y asegúrate de que wp-rocket.php lo carga antes del update manager.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_rocket_gpl_ensure_array' ) ) {
	function wp_rocket_gpl_ensure_array( $maybe ) {
		if ( is_array( $maybe ) ) {
			return $maybe;
		}
		if ( is_object( $maybe ) ) {
			$converted = json_decode( wp_json_encode( $maybe ), true );
			return is_array( $converted ) ? $converted : [];
		}
		if ( is_string( $maybe ) ) {
			$decoded = json_decode( $maybe, true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return $decoded;
			}
		}
		return [];
	}
}

if ( ! function_exists( 'wp_rocket_gpl_array_column' ) ) {
	function wp_rocket_gpl_array_column( $input, $column_key, $index_key = null ) {
		$input = wp_rocket_gpl_ensure_array( $input );
		foreach ( $input as $k => $v ) {
			if ( is_object( $v ) ) {
				$input[ $k ] = json_decode( wp_json_encode( $v ), true );
			}
		}
		if ( ! is_array( $input ) ) {
			return [];
		}
		return $index_key === null ? array_column( $input, $column_key ) : array_column( $input, $column_key, $index_key );
	}
}

if ( ! function_exists( 'wp_rocket_gpl_log_debug' ) ) {
	function wp_rocket_gpl_log_debug( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[wp-rocket-gpl] ' . $message );
		}
	}
}