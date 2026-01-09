<?php
/**
 * Plugin Name: WP Rocket GPL con API Key
 * Plugin URI: https://wp-rocket.me
 * Description: The best WordPress performance plugin.
 * Version: 3.20.3
 * Requires at least: 5.8
 * Requires PHP: 7.3
 * Code Name: Iego
 * Author: WP Media / GPL Mod
 * Author URI: https://wp-media.me
 * Licence: GPLv2 or later
 *
 * Text Domain: rocket
 * Domain Path: /languages
 *
 * Copyright 2013-2025 WP Rocket
 */

defined( 'ABSPATH' ) || exit;

// Rocket defines.
define( 'WP_ROCKET_VERSION',               '3.20.3' );
define( 'WP_ROCKET_WP_VERSION',            '5.8' );
define( 'WP_ROCKET_WP_VERSION_TESTED',     '6.3.1' );
define( 'WP_ROCKET_PHP_VERSION',           '7.3' );
define( 'WP_ROCKET_PRIVATE_KEY',           false );
define( 'WP_ROCKET_SLUG',                  'wp_rocket_settings' );
define( 'WP_ROCKET_WEB_MAIN',              'https://wp-rocket.me/' );
define( 'WP_ROCKET_WEB_API',               WP_ROCKET_WEB_MAIN . 'api/wp-rocket/' ); // only used in deprecated code.
define( 'WP_ROCKET_WEB_CHECK',             WP_ROCKET_WEB_MAIN . 'check_update.php' ); // only used in deprecated code.
define( 'WP_ROCKET_WEB_VALID',             WP_ROCKET_WEB_MAIN . 'valid_key.php' ); // only used in deprecated code.
define( 'WP_ROCKET_WEB_INFO',              WP_ROCKET_WEB_MAIN . 'plugin_information.php' ); // only used in deprecated code.
define( 'WP_ROCKET_FILE',                  __FILE__ );
define( 'WP_ROCKET_PATH',                  realpath( plugin_dir_path( WP_ROCKET_FILE ) ) . '/' );
define( 'WP_ROCKET_INC_PATH',              realpath( WP_ROCKET_PATH . 'inc/' ) . '/' );

require_once WP_ROCKET_INC_PATH . 'constants.php';

define( 'WP_ROCKET_DEPRECATED_PATH',       realpath( WP_ROCKET_INC_PATH . 'deprecated/' ) . '/' );
define( 'WP_ROCKET_FRONT_PATH',            realpath( WP_ROCKET_INC_PATH . 'front/' ) . '/' );
define( 'WP_ROCKET_ADMIN_PATH',            realpath( WP_ROCKET_INC_PATH . 'admin' ) . '/' );
define( 'WP_ROCKET_ADMIN_UI_PATH',         realpath( WP_ROCKET_ADMIN_PATH . 'ui' ) . '/' );
define( 'WP_ROCKET_ADMIN_UI_MODULES_PATH', realpath( WP_ROCKET_ADMIN_UI_PATH . 'modules' ) . '/' );
define( 'WP_ROCKET_COMMON_PATH',           realpath( WP_ROCKET_INC_PATH . 'common' ) . '/' );
define( 'WP_ROCKET_FUNCTIONS_PATH',        realpath( WP_ROCKET_INC_PATH . 'functions' ) . '/' );
define( 'WP_ROCKET_VENDORS_PATH',          realpath( WP_ROCKET_INC_PATH . 'vendors' ) . '/' );
define( 'WP_ROCKET_3RD_PARTY_PATH',        realpath( WP_ROCKET_INC_PATH . '3rd-party' ) . '/' );
if ( ! defined( 'WP_ROCKET_CONFIG_PATH' ) ) {
	define( 'WP_ROCKET_CONFIG_PATH',       WP_CONTENT_DIR . '/wp-rocket-config/' );
}
define( 'WP_ROCKET_URL',                   plugin_dir_url( WP_ROCKET_FILE ) );
define( 'WP_ROCKET_INC_URL',               WP_ROCKET_URL . 'inc/' );
define( 'WP_ROCKET_ADMIN_URL',             WP_ROCKET_INC_URL . 'admin/' );
define( 'WP_ROCKET_ASSETS_URL',            WP_ROCKET_URL . 'assets/' );
define( 'WP_ROCKET_ASSETS_PATH',            WP_ROCKET_PATH . 'assets/' );
define( 'WP_ROCKET_ASSETS_JS_URL',         WP_ROCKET_ASSETS_URL . 'js/' );
define( 'WP_ROCKET_ASSETS_JS_PATH',         WP_ROCKET_ASSETS_PATH . 'js/' );
define( 'WP_ROCKET_ASSETS_CSS_URL',        WP_ROCKET_ASSETS_URL . 'css/' );
define( 'WP_ROCKET_ASSETS_IMG_URL',        WP_ROCKET_ASSETS_URL . 'img/' );

if ( ! defined( 'WP_ROCKET_CACHE_ROOT_PATH' ) ) {
	define( 'WP_ROCKET_CACHE_ROOT_PATH', WP_CONTENT_DIR . '/cache/' );
}
define( 'WP_ROCKET_CACHE_PATH',         WP_ROCKET_CACHE_ROOT_PATH . 'wp-rocket/' );
define( 'WP_ROCKET_MINIFY_CACHE_PATH',  WP_ROCKET_CACHE_ROOT_PATH . 'min/' );
define( 'WP_ROCKET_CACHE_BUSTING_PATH', WP_ROCKET_CACHE_ROOT_PATH . 'busting/' );
define( 'WP_ROCKET_CRITICAL_CSS_PATH',  WP_ROCKET_CACHE_ROOT_PATH . 'critical-css/' );

define( 'WP_ROCKET_USED_CSS_PATH',  WP_ROCKET_CACHE_ROOT_PATH . 'used-css/' );

if ( ! defined( 'WP_ROCKET_CACHE_ROOT_URL' ) ) {
	define( 'WP_ROCKET_CACHE_ROOT_URL', WP_CONTENT_URL . '/cache/' );
}
define( 'WP_ROCKET_CACHE_URL',         WP_ROCKET_CACHE_ROOT_URL . 'wp-rocket/' );
define( 'WP_ROCKET_MINIFY_CACHE_URL',  WP_ROCKET_CACHE_ROOT_URL . 'min/' );
define( 'WP_ROCKET_CACHE_BUSTING_URL', WP_ROCKET_CACHE_ROOT_URL . 'busting/' );

define( 'WP_ROCKET_USED_CSS_URL', WP_ROCKET_CACHE_ROOT_URL . 'used-css/' );

if ( ! defined( 'CHMOD_WP_ROCKET_CACHE_DIRS' ) ) {
	define( 'CHMOD_WP_ROCKET_CACHE_DIRS', 0755 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
}
if ( ! defined( 'WP_ROCKET_LASTVERSION' ) ) {
	define( 'WP_ROCKET_LASTVERSION', '3.19.4' );
}

/**
 * Definir servidor de actualizaciones si no existe (valor por defecto).
 * Esto evita errores si las includes usan PLUGIN_UPDATER_SERVER.
 */
if ( ! defined( 'PLUGIN_UPDATER_SERVER' ) ) {
	define( 'PLUGIN_UPDATER_SERVER', 'https://actualizarplugins.online/api/' );
}

/**
 * ⭐ GPL ADAPTATIONS: integrar únicamente la parte de licencia/updater
 * - Mantiene el bypass de validación
 * - Carga módulos de include/ (o includes/ o gpl-includes/)
 * - No crea páginas fallback innecesarias
 */

/**
 * Asegurar opción de licencia simulada
 */
if ( ! function_exists( 'wp_rocket_gpl_set_license_now' ) ) {
	function wp_rocket_gpl_set_license_now() {
		$options = get_option( WP_ROCKET_SLUG, [] );

		if ( empty( $options['consumer_key'] ) || empty( $options['secret_key'] ) ) {
			$options['consumer_key']   = 'WP_ROCKET_GPL_KEY';
			$options['consumer_email'] = 'admin@local.test';
			$options['secret_key']     = 'gpl_active_license';
			update_option( WP_ROCKET_SLUG, $options );
		}
	}
}
wp_rocket_gpl_set_license_now();

/**
 * Bypass de endpoints remotos que consultan licencia / update.
 */
add_filter( 'pre_http_request', function( $response, $args, $url ) {
	if ( strpos( $url, 'api.wp-rocket.me/valid_key.php' ) !== false || strpos( $url, 'wp-rocket.me/valid_key.php' ) !== false ) {
		return array(
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'headers'  => array(),
			'body'     => json_encode( array(
				'success' => true,
				'data'    => array(
					'consumer_key'   => 'WP_ROCKET_GPL_KEY',
					'consumer_email' => 'admin@local.test',
					'secret_key'     => 'gpl_active_license',
				),
			) ),
		);
	}

	if ( strpos( $url, 'api.wp-rocket.me/stat/1.0/wp-rocket/user.php' ) !== false ) {
		return array(
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'headers' => array(),
			'body' => json_encode( array(
				'licence_account' => -1,
				'licence_expiration' => time() + ( 50 * YEAR_IN_SECONDS ),
				'licence' => (object) array( 'name' => 'GPL Unlimited' ),
				'status' => 'valid',
				'has_auto_renew' => true,
				'date_created' => time() - ( 30 * DAY_IN_SECONDS ),
			) ),
		);
	}

	if ( strpos( $url, 'api.wp-rocket.me/check_update.php' ) !== false || strpos( $url, 'wp-rocket.me/check_update.php' ) !== false ) {
		return array(
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'headers' => array(),
			'body' => json_encode( array(
				'version' => WP_ROCKET_VERSION,
				'details_url' => '',
				'download_url' => '',
			) ),
		);
	}

	if ( strpos( $url, 'wpsaas.gpltimes.com' ) !== false ) {
		return array(
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'headers' => array(),
			'body' => json_encode( array( 'status' => 'ok', 'code' => 200 ) ),
		);
	}

	return $response;
}, 0, 3 );

/**
 * Cargar utilidades y módulos GPL desde folder real del plugin.
 * Intentamos, en este orden, cargar de: include/, includes/, gpl-includes/
 */
add_action( 'plugins_loaded', function() {
	$possible_dirs = array(
		WP_ROCKET_PATH . 'include/',
		WP_ROCKET_PATH . 'includes/',
		WP_ROCKET_PATH . 'gpl-includes/',
	);

	foreach ( $possible_dirs as $dir ) {
		if ( is_dir( $dir ) ) {

			// cargar utils primero si existe
			if ( file_exists( $dir . 'wp-rocket-gpl-utils.php' ) ) {
				require_once $dir . 'wp-rocket-gpl-utils.php';
			}

			// cargar admin/ajax/update manager si existen
			if ( file_exists( $dir . 'admin-license.php' ) ) {
				require_once $dir . 'admin-license.php';
			}
			if ( file_exists( $dir . 'ajax-license.php' ) ) {
				require_once $dir . 'ajax-license.php';
			}
			if ( file_exists( $dir . 'class-update-manager.php' ) ) {
				require_once $dir . 'class-update-manager.php';
			}
			if ( file_exists( $dir . 'protect-license-transient.php' ) ) {
				require_once $dir . 'protect-license-transient.php';
			}

			// stop at first existing directory (prefer your include/)
			break;
		}
	}
}, 1 );

/* ==============================
   END GPL ADAPTATIONS
   ============================== */

require WP_ROCKET_INC_PATH . 'compat.php';
require WP_ROCKET_INC_PATH . 'classes/class-wp-rocket-requirements-check.php';

/**
 * Loads WP Rocket translations
 *
 * @since 3.0
 */
function rocket_load_textdomain() {
	load_plugin_textdomain( 'rocket', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'init', 'rocket_load_textdomain' );

$wp_rocket_requirement_checks = new WP_Rocket_Requirements_Check(
	[
		'plugin_name'         => 'WP Rocket',
		'plugin_file'         => WP_ROCKET_FILE,
		'plugin_version'      => WP_ROCKET_VERSION,
		'plugin_last_version' => WP_ROCKET_LASTVERSION,
		'wp_version'          => WP_ROCKET_WP_VERSION,
		'php_version'         => WP_ROCKET_PHP_VERSION,
	]
);

if ( $wp_rocket_requirement_checks->check() ) {
	require WP_ROCKET_INC_PATH . 'main.php';
}

unset( $wp_rocket_requirement_checks );