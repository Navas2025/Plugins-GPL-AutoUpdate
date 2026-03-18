<?php
/**
 * Plugin Name: WP Rocket GPL
 * Plugin URI: https://wp-rocket.me
 * Description: The best WordPress performance plugin. (Versión GPL con Actualizaciones Automáticas).
 * Version: 3.20.6.1
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
// PARTE 1: CONFIGURACIÓN GPL
// ========================================

if (!defined('WP_ROCKET_GPL_UPDATE_SERVER')) {
    define('WP_ROCKET_GPL_UPDATE_SERVER', 'https://actualizarplugins.online/api/');
}

// Define license credentials for GPL bypass
if (!defined('WP_ROCKET_GPL_LICENSE_KEY')) {
    define('WP_ROCKET_GPL_LICENSE_KEY', 'B5E0B5F8DD8689E6ACA49DD6E6E1A930');
}
if (!defined('WP_ROCKET_GPL_LICENSE_EMAIL')) {
    define('WP_ROCKET_GPL_LICENSE_EMAIL', 'noreply@gmail.com');
}

// ========================================
// PARTE 2: BYPASS DE LICENCIA (WP Rocket Pro)
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
// PARTE 3: INTERCEPTOR DE SEGURIDAD (ACTUALIZADO 3.20.6.1)
// ========================================

add_filter('pre_http_request', function($response, $args, $url) {
    
    // Bypass WP Rocket license validation
    if (strpos($url, 'api.wp-rocket.me/valid_key.php') !== false || strpos($url, 'wp-rocket.me/valid_key.php') !== false) {
        $key = WP_ROCKET_GPL_LICENSE_KEY;
        $email = WP_ROCKET_GPL_LICENSE_EMAIL;
        return [
            'response' => ['code' => 200],
            'body' => json_encode([
                'success' => true,
                'data' => [
                    'consumer_key' => substr($key, 0, 8),
                    'consumer_email' => $email,
                    'secret_key' => hash('crc32', $email)
                ]
            ])
        ];
    }
    
    // Bypass user info endpoint
    if (strpos($url, 'api.wp-rocket.me/stat/1.0/wp-rocket/user.php') !== false) {
        return [
            'response' => ['code' => 200],
            'body' => json_encode([
                'licence_account' => -1,
                'licence_expiration' => time() + (50 * YEAR_IN_SECONDS),
                'licence' => ['name' => 'Infinite'],
                'status' => 'valid',
                'has_auto_renew' => true,
                'date_created' => time() - (30 * DAY_IN_SECONDS)
            ])
        ];
    }
    
    // Bypass update check endpoint
    if (strpos($url, 'api.wp-rocket.me/check_update.php') !== false || strpos($url, 'wp-rocket.me/check_update.php') !== false) {
        return [
            'response' => ['code' => 200],
            'body' => json_encode([
                'version' => '3.20.6.1',
                'details_url' => '',
                'download_url' => ''
            ])
        ];
    }
    
    // Bypass wpsaas endpoint (NUEVO EN 3.20.6.1)
    if (strpos($url, 'wpsaas.gpltimes.com/rucss-job') !== false) {
        return [
            'response' => ['code' => 200],
            'body' => json_encode([
                'status' => 'ok',
                'code' => 200
            ])
        ];
    }
    
    return $response;
}, 10, 3);

// ========================================
// PARTE 4: INICIALIZACIÓN DE LICENCIA (ACTUALIZADO 3.20.6.1)
// ========================================

add_action('init', function() {
    $key = WP_ROCKET_GPL_LICENSE_KEY;
    $email = WP_ROCKET_GPL_LICENSE_EMAIL;
    
    // Actualizar transient de settings
    $options = get_transient('wp_rocket_settings');
    if ($options && isset($options['license']) && '1' === $options['license']) {
        $options['license'] = time();
        $options['ignore'] = true;
        set_transient('wp_rocket_settings', $options, YEAR_IN_SECONDS);
    }
    if (!$options || empty($options['secret_key'])) {
        $options = [
            'consumer_key' => substr($key, 0, 8),
            'consumer_email' => $email,
            'secret_key' => hash('crc32', $email),
            'license' => time(),
            'ignore' => true
        ];
        set_transient('wp_rocket_settings', $options, YEAR_IN_SECONDS);
    }
    
    update_option('wp_rocket_no_licence', 0);
    
    // Customer data con Performance Monitoring (NUEVO EN 3.20.6.1)
    $customer_data = (object) [
        'licence_account' => -1,
        'licence_expiration' => time() + (50 * YEAR_IN_SECONDS),
        'licence' => (object) ['name' => 'Infinite'],
        'status' => 'valid',
        'has_auto_renew' => true,
        'date_created' => time() - (30 * DAY_IN_SECONDS),
        'performance_monitoring' => (object) [
            'expiration' => time() + (50 * YEAR_IN_SECONDS),
            'cancelled_at' => null,
            'manage_url' => null,
            'active_sku' => 'perf-monitor-advanced',
            'plans' => [
                (object) [
                    'sku' => 'perf-monitor-advanced',
                    'price' => '8.99',
                    'limit' => '10',
                    'title' => 'Advanced',
                    'subtitle' => 'See how your top pages perform and quickly spot and optimize what slows your site down.',
                    'description' => 'Up to 10 pages • Weekly updates',
                    'billing' => '* Billed monthly. You can cancel at any time, each month started is due.',
                    'highlights' => [
                        'Up to 10 pages tracked',
                        'Automatic performance monitoring',
                        'Unlimited on-demand tests',
                        'Full GTmetrix performance reports'
                    ],
                    'status' => 'active',
                    'button' => (object) [
                        'label' => 'Your plan',
                        'action' => 'none',
                        'url' => null
                    ]
                ]
            ]
        ]
    ];
    set_transient('wp_rocket_customer_data', $customer_data, DAY_IN_SECONDS);
});

// ========================================
// PARTE 5: CARGAR SISTEMA DE ACTUALIZACIÓN GPL
// ========================================

if (is_admin()) {
    $includes_dir = __DIR__ . '/includes/';
    
    // Sistema de actualización
    if (file_exists($includes_dir . 'class-update-manager.php')) {
        require_once $includes_dir . 'class-update-manager.php';
    }
    
    // Interfaz de licencia
    if (file_exists($includes_dir . 'admin-license.php')) {
        require_once $includes_dir . 'admin-license.php';
    }
    if (file_exists($includes_dir . 'ajax-license.php')) {
        require_once $includes_dir . 'ajax-license.php';
    }
    
    // Proteger transients de licencia
    if (file_exists($includes_dir . 'protect-license-transient.php')) {
        require_once $includes_dir . 'protect-license-transient.php';
    }
}

// ========================================
// PARTE 6: INTERCEPTOR DE DESCARGA GPL
// ========================================

// Intercept BEFORE download (DEFINITIVE SOLUTION)
add_filter('upgrader_pre_download', function($reply, $package, $upgrader) {
    
    error_log('=== WP ROCKET GPL - UPGRADER PRE DOWNLOAD ===');
    error_log('Package URL received: ' . $package);
    
    // Only intercept URLs from wp-rocket.me
    if (!empty($package) && strpos($package, 'wp-rocket.me') !== false) {
        
        error_log('⚠️ WP Rocket URL detected, proceeding to replace...');
        
        $api_key = get_option('wp_rocket_gpl_api_key', get_option('plugin_updater_api_key', ''));
        
        if (empty($api_key)) {
            error_log('❌ API Key is empty');
            error_log('=== END UPGRADER PRE DOWNLOAD ===');
            return new WP_Error(
                'no_api_key',
                __('API Key no configurada. Por favor, ve a Ajustes → Licencia WP Rocket para activar tu licencia GPL.', 'wp-rocket-gpl')
            );
        }
        
        error_log('✅ API Key found: ' . substr($api_key, 0, 10) . '...');
        
        // Get real URL from transient
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
            error_log('✅✅✅ DOWNLOADING FROM HIDRIVE: ' . $real_url);
            
            // Download file directly using WordPress
            $tmpfile = download_url($real_url);
            
            if (is_wp_error($tmpfile)) {
                error_log('❌ Error downloading from HiDrive: ' . $tmpfile->get_error_message());
                error_log('=== END UPGRADER PRE DOWNLOAD ===');
                return $tmpfile;
            }
            
            error_log('✅✅✅ FILE DOWNLOADED SUCCESSFULLY');
            error_log('Temporary path: ' . $tmpfile);
            error_log('File size: ' . filesize($tmpfile) . ' bytes');
            error_log('=== END UPGRADER PRE DOWNLOAD ===');
            
            // Return temporary file path
            return $tmpfile;
        } else {
            error_log('❌❌❌ COULD NOT OBTAIN REPLACEMENT URL');
            return new WP_Error(
                'no_download_url',
                __('No se pudo obtener la URL de descarga GPL. Por favor, intenta de nuevo o contacta con soporte.', 'wp-rocket-gpl')
            );
        }
    } else {
        error_log('ℹ️ URL does not require replacement (not from wp-rocket.me)');
    }
    
    error_log('=== END UPGRADER PRE DOWNLOAD ===');
    return $reply;
    
}, 10, 3);

// ========================================
// PARTE 7: RENOMBRAR CARPETA SI ES INCORRECTA
// ========================================

/**
 * Corregir nombre de carpeta después de extraer el ZIP
 * Esto soluciona el problema cuando el ZIP tiene carpeta "wp-rocket" en lugar de "wp-rocket-gpl"
 */
add_filter('upgrader_source_selection', function($source, $remote_source, $upgrader, $hook_extra) {
    global $wp_filesystem;
    
    error_log('=== WP ROCKET GPL - UPGRADER SOURCE SELECTION ===');
    error_log('Source: ' . $source);
    error_log('Remote source: ' . $remote_source);
    
    // Solo aplicar para WP Rocket GPL
    if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== 'wp-rocket-gpl/wp-rocket-gpl.php') {
        error_log('ℹ️ No es WP Rocket GPL, saltando...');
        error_log('=== FIN UPGRADER SOURCE SELECTION ===');
        return $source;
    }
    
    error_log('✅ Plugin detectado: WP Rocket GPL');
    
    // Obtener el nombre de la carpeta extraída
    $dirname = basename($source);
    error_log('Nombre de carpeta extraída: ' . $dirname);
    
    // Si la carpeta NO es "wp-rocket-gpl", renombrarla
    if ($dirname !== 'wp-rocket-gpl') {
        error_log('⚠️ Carpeta incorrecta detectada: ' . $dirname);
        
        $new_source = trailingslashit($remote_source) . 'wp-rocket-gpl/';
        error_log('Nueva ruta: ' . $new_source);
        
        // Si ya existe wp-rocket-gpl en tmp, eliminarla
        if ($wp_filesystem->exists($new_source)) {
            error_log('⚠️ Ya existe carpeta wp-rocket-gpl en tmp, eliminando...');
            $wp_filesystem->delete($new_source, true);
        }
        
        // Renombrar carpeta
        error_log('🔄 Renombrando: ' . $source . ' → ' . $new_source);
        $moved = $wp_filesystem->move($source, $new_source);
        
        if ($moved) {
            error_log('✅✅✅ Carpeta renombrada correctamente a wp-rocket-gpl');
            error_log('=== FIN UPGRADER SOURCE SELECTION ===');
            return $new_source;
        } else {
            error_log('❌ Error al renombrar carpeta');
            // Si falla el renombrado, devolver el source original para que no se rompa la actualización
            error_log('=== FIN UPGRADER SOURCE SELECTION ===');
            return $source;
        }
    } else {
        error_log('✅ Carpeta correcta: wp-rocket-gpl');
    }
    
    error_log('=== FIN UPGRADER SOURCE SELECTION ===');
    return $source;
}, 10, 4);

/**
 * Mantener plugin activo después de actualizar
 */
add_filter('update_plugin_complete_actions', function($update_actions, $plugin) {
    // Solo para WP Rocket GPL
    if ($plugin === 'wp-rocket-gpl/wp-rocket-gpl.php') {
        error_log('✅ WP Rocket GPL actualizado - verificando estado...');
        
        // Verificar si el plugin está activo
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Si no está activo, activarlo
        if (!is_plugin_active($plugin)) {
            error_log('⚠️ Plugin desactivado después de actualizar - reactivando...');
            $result = activate_plugin($plugin);
            if (is_wp_error($result)) {
                error_log('❌ Error al reactivar: ' . $result->get_error_message());
            } else {
                error_log('✅ Plugin reactivado correctamente');
            }
        } else {
            error_log('✅ Plugin ya está activo');
        }
    }
    
    return $update_actions;
}, 10, 2);

/**
 * Limpiar carpeta incorrecta si existe después de actualizar
 */
add_action('upgrader_process_complete', function($upgrader, $hook_extra) {
    global $wp_filesystem;
    
    // Solo aplicar para WP Rocket GPL
    if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === 'wp-rocket-gpl/wp-rocket-gpl.php') {
        error_log('=== WP ROCKET GPL - POST UPDATE CLEANUP ===');
        
        $plugins_dir = WP_PLUGIN_DIR . '/';
        $wrong_folder = $plugins_dir . 'wp-rocket/';
        $correct_folder = $plugins_dir . 'wp-rocket-gpl/';
        
        // Si existe la carpeta incorrecta "wp-rocket", eliminarla
        if ($wp_filesystem->exists($wrong_folder) && $wp_filesystem->exists($correct_folder)) {
            error_log('⚠️ Detectada carpeta duplicada: wp-rocket/');
            error_log('🗑️ Eliminando carpeta incorrecta...');
            
            $deleted = $wp_filesystem->delete($wrong_folder, true);
            
            if ($deleted) {
                error_log('✅ Carpeta wp-rocket/ eliminada correctamente');
            } else {
                error_log('❌ No se pudo eliminar carpeta wp-rocket/');
            }
        }
        
        error_log('=== FIN POST UPDATE CLEANUP ===');
    }
}, 10, 2);

// ========================================
// PARTE 8: CÓDIGO BASE WP ROCKET (VERSIÓN 3.20.6.1)
// ========================================

// Rocket defines.
define( 'WP_ROCKET_VERSION',               '3.20.6.1' );
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
	define( 'WP_ROCKET_LASTVERSION', '3.20.5' );
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
