<?php
/**
 * Plugin Name: WP Rocket GPL
 * Plugin URI: https://wp-rocket.me
 * Description: The best WordPress performance plugin. (Versión Final - Interceptor de URL).
 * Version: 3.20.3
 * Requires at least: 5.8
 * Requires PHP: 7.3
 * Code Name: Iego
 * Author: WP Media (Modificado con Sistema GPL)
 * Author URI: https://wp-media.me
 * Licence: GPLv2 or later
 *
 * Text Domain: wp-rocket-gpl
 * Domain Path: /languages
 *
 * Copyright 2013-2025 WP Rocket
 */

defined( 'ABSPATH' ) || exit;

// ========================================
// 1. CONFIGURACIÓN
// ========================================

if (!defined('WP_ROCKET_GPL_UPDATE_SERVER')) {
    define('WP_ROCKET_GPL_UPDATE_SERVER', 'https://actualizarplugins.online/api/');
}

// ========================================
// 2. BYPASS DE LICENCIA (WP Rocket Pro)
// ========================================

/**
 * Asegurar opción de licencia simulada
 */
if (!function_exists('wp_rocket_gpl_set_license_now')) {
    function wp_rocket_gpl_set_license_now() {
        $options = get_option('wp_rocket_settings', []);
        
        if (empty($options['consumer_key']) || empty($options['secret_key'])) {
            $options['consumer_key'] = 'WP_ROCKET_GPL_KEY';
            $options['consumer_email'] = 'admin@local.test';
            $options['secret_key'] = 'gpl_active_license';
            update_option('wp_rocket_settings', $options);
        }
    }
}
wp_rocket_gpl_set_license_now();

// ========================================
// 3. INTERCEPTOR DE SEGURIDAD
// ========================================

add_action('plugins_loaded', function () {
    add_filter('pre_http_request', function ($pre, $parsed_args, $url) {
        
        // Bypass WP Rocket license validation
        if (strpos($url, 'api.wp-rocket.me/valid_key.php') !== false || strpos($url, 'wp-rocket.me/valid_key.php') !== false) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'headers' => [],
                'body' => json_encode([
                    'success' => true,
                    'data' => [
                        'consumer_key' => 'WP_ROCKET_GPL_KEY',
                        'consumer_email' => 'admin@local.test',
                        'secret_key' => 'gpl_active_license',
                    ],
                ]),
            ];
        }
        
        // Bypass user info endpoint
        if (strpos($url, 'api.wp-rocket.me/stat/1.0/wp-rocket/user.php') !== false) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'headers' => [],
                'body' => json_encode([
                    'licence_account' => -1,
                    'licence_expiration' => time() + (50 * YEAR_IN_SECONDS),
                    'licence' => (object) ['name' => 'GPL Unlimited'],
                    'status' => 'valid',
                    'has_auto_renew' => true,
                    'date_created' => time() - (30 * DAY_IN_SECONDS),
                ]),
            ];
        }
        
        // Bypass update check endpoint
        if (strpos($url, 'api.wp-rocket.me/check_update.php') !== false || strpos($url, 'wp-rocket.me/check_update.php') !== false) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'headers' => [],
                'body' => json_encode([
                    'version' => '3.20.3',
                    'details_url' => '',
                    'download_url' => '',
                ]),
            ];
        }
        
        // Bypass wpsaas endpoint
        if (strpos($url, 'wpsaas.gpltimes.com') !== false) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'headers' => [],
                'body' => json_encode(['status' => 'ok', 'code' => 200]),
            ];
        }
        
        return $pre;
    }, 10, 3);
});

// ========================================
// 4. CARGAR INTERFAZ
// ========================================

if (is_admin()) {
    $includes_dir = __DIR__ . '/includes/';
    if (file_exists($includes_dir . 'admin-license.php')) {
        require_once $includes_dir . 'admin-license.php';
    }
    if (file_exists($includes_dir . 'ajax-license.php')) {
        require_once $includes_dir . 'ajax-license.php';
    }
}

// ========================================
// 5. SISTEMA DE ACTUALIZACIÓN
// ========================================

// Hook A: Inyectar actualización
add_filter('site_transient_update_plugins', function ($transient) {
    
    $api_key = get_option('wp_rocket_gpl_api_key', get_option('plugin_updater_api_key', ''));
    if (empty($api_key)) return $transient;

    $plugin_slug = 'wp-rocket-gpl'; 
    $plugin_base = plugin_basename(__FILE__);
    
    if (empty($transient->checked) || !isset($transient->checked[$plugin_base])) {
        return $transient;
    }
    $current_version = $transient->checked[$plugin_base];

    $url = WP_ROCKET_GPL_UPDATE_SERVER . 'get-plugins.php';
    $args = ['apiKey' => $api_key, 'installed' => $plugin_slug];
    
    $response = wp_remote_get(add_query_arg($args, $url), ['timeout' => 15, 'sslverify' => false]);
    
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return $transient;

    $remote_plugins = json_decode(wp_remote_retrieve_body($response), true);

    if (is_array($remote_plugins)) {
        foreach ($remote_plugins as $plugin) {
            if (isset($plugin['slug']) && $plugin['slug'] === $plugin_slug) {
                
                if (isset($plugin['version']) && version_compare($current_version, $plugin['version'], '<')) {
                    
                    $package_url = $plugin['download_url'] ?? ($plugin['package'] ?? '');

                    if (!empty($package_url)) {
                        set_transient('wprocket_gpl_real_url_' . md5($api_key), $package_url, DAY_IN_SECONDS);
                        error_log('✅ WP Rocket GPL - Hook A: New version detected: ' . $plugin['version']);
                        error_log('✅ WP Rocket GPL - Hook A: URL saved in transient');

                        $obj = new stdClass();
                        $obj->slug = $plugin_slug;
                        $obj->plugin = $plugin_base;
                        $obj->new_version = $plugin['version'];
                        $obj->package = $package_url;
                        $obj->url = $plugin['details_url'] ?? '';
                        $obj->tested = $plugin['tested'] ?? '6.7';
                        $obj->requires = $plugin['requires'] ?? '6.0';
                        
                        $transient->response[$plugin_base] = $obj;
                    }
                }
                break; 
            }
        }
    }
    return $transient;
}, 100);

// Hook B: Interceptar ANTES de la descarga (SOLUCIÓN DEFINITIVA)
add_filter('upgrader_pre_download', function($reply, $package, $upgrader) {
    
    error_log('=== WP ROCKET GPL - UPGRADER PRE DOWNLOAD ===');
    error_log('Package URL received: ' . $package);
    
    // Solo interceptar URLs de wp-rocket.me
    if (!empty($package) && strpos($package, 'wp-rocket.me') !== false) {
        
        error_log('⚠️ WP Rocket URL detected, proceeding to replace...');
        
        $api_key = get_option('wp_rocket_gpl_api_key', get_option('plugin_updater_api_key', ''));
        
        if (empty($api_key)) {
            error_log('❌ API Key is empty');
            error_log('=== END UPGRADER PRE DOWNLOAD ===');
            return $reply;
        }
        
        error_log('✅ API Key found: ' . substr($api_key, 0, 10) . '...');
        
        // Obtener URL real del transient
        $real_url = get_transient('wprocket_gpl_real_url_' . md5($api_key));
        
        error_log('Transient key: wprocket_gpl_real_url_' . md5($api_key));
        error_log('URL from Transient: ' . ($real_url ?: '❌ EMPTY'));
        
        // If no transient, query server
        if (empty($real_url)) {
            error_log('⚠️ Empty transient, querying server...');
            
            $url = WP_ROCKET_GPL_UPDATE_SERVER . 'get-plugins.php';
            $query_url = add_query_arg(['apiKey' => $api_key, 'installed' => 'wp-rocket-gpl'], $url);
            
            error_log('Query URL: ' . $query_url);
            
            $response = wp_remote_get($query_url, [
                'timeout' => 15,
                'sslverify' => false,
                'headers' => [
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
                ]
            ]);
            
            $http_code = wp_remote_retrieve_response_code($response);
            error_log('Server response: HTTP ' . $http_code);
            
            if (!is_wp_error($response) && $http_code === 200) {
                $body = wp_remote_retrieve_body($response);
                error_log('Body received: ' . strlen($body) . ' bytes');
                
                $data = json_decode($body, true);
                
                if (is_array($data)) {
                    error_log('Total plugins in response: ' . count($data));
                    
                    foreach ($data as $plugin) {
                        if (isset($plugin['slug']) && $plugin['slug'] === 'wp-rocket-gpl') {
                            $real_url = $plugin['download_url'] ?? ($plugin['package'] ?? '');
                            error_log('✅ URL obtained from server: ' . $real_url);
                            
                            // Save in transient
                            set_transient('wprocket_gpl_real_url_' . md5($api_key), $real_url, DAY_IN_SECONDS);
                            break;
                        }
                    }
                } else {
                    error_log('❌ Error: Response is not a valid array');
                }
            } else {
                if (is_wp_error($response)) {
                    error_log('❌ Error WP: ' . $response->get_error_message());
                } else {
                    error_log('❌ Incorrect HTTP Code: ' . $http_code);
                }
            }
        }
        
        // If we have real URL, download from there
        if (!empty($real_url)) {
            error_log('✅✅✅ DESCARGANDO DESDE HIDRIVE: ' . $real_url);
            
            // Download file directly using WordPress
            $tmpfile = download_url($real_url);
            
            if (is_wp_error($tmpfile)) {
                error_log('❌ Error downloading from HiDrive: ' . $tmpfile->get_error_message());
                error_log('=== END UPGRADER PRE DOWNLOAD ===');
                return $tmpfile;
            }
            
            error_log('✅✅✅ ARCHIVO DESCARGADO EXITOSAMENTE');
            error_log('Temporary path: ' . $tmpfile);
            error_log('File size: ' . filesize($tmpfile) . ' bytes');
            error_log('=== END UPGRADER PRE DOWNLOAD ===');
            
            // Return temporary file path
            return $tmpfile;
        } else {
            error_log('❌❌❌ COULD NOT OBTAIN REPLACEMENT URL');
        }
    } else {
        error_log('ℹ️ URL does not require replacement (not from wp-rocket.me)');
    }
    
    error_log('=== END UPGRADER PRE DOWNLOAD ===');
    return $reply;
    
}, 10, 3);

// ========================================
// 6. CÓDIGO BASE WP ROCKET (ORIGINAL)
// ========================================

// Rocket defines.
define( 'WP_ROCKET_VERSION',               '3.20.3' );
define( 'WP_ROCKET_WP_VERSION',            '5.8' );
define( 'WP_ROCKET_WP_VERSION_TESTED',     '6.3.1' );
define( 'WP_ROCKET_PHP_VERSION',           '7.3' );
define( 'WP_ROCKET_PRIVATE_KEY',           false );
define( 'WP_ROCKET_SLUG',                  'wp_rocket_settings' );
define( 'WP_ROCKET_WEB_MAIN',              'https://wp-rocket.me/' );
define( 'WP_ROCKET_WEB_API',               WP_ROCKET_WEB_MAIN . 'api/wp-rocket/' );
define( 'WP_ROCKET_WEB_CHECK',             WP_ROCKET_WEB_MAIN . 'check_update.php' );
define( 'WP_ROCKET_WEB_VALID',             WP_ROCKET_WEB_MAIN . 'valid_key.php' );
define( 'WP_ROCKET_WEB_INFO',              WP_ROCKET_WEB_MAIN . 'plugin_information.php' );
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
	define( 'CHMOD_WP_ROCKET_CACHE_DIRS', 0755 );
}
if ( ! defined( 'WP_ROCKET_LASTVERSION' ) ) {
	define( 'WP_ROCKET_LASTVERSION', '3.19.4' );
}

/**
 * We use is_readable() with @ silencing as WP_Filesystem() can use different methods to access the filesystem.
 *
 * This is more performant and more compatible. It allows us to work around file permissions and missing credentials.
 */
if ( @is_readable( WP_ROCKET_PATH . 'licence-data.php' ) ) {
	@include WP_ROCKET_PATH . 'licence-data.php';
}

require WP_ROCKET_INC_PATH . 'compat.php';
require WP_ROCKET_INC_PATH . 'classes/class-wp-rocket-requirements-check.php';

/**
 * Loads WP Rocket translations
 *
 * @since 3.0
 * @author Remy Perona
 *
 * @return void
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
