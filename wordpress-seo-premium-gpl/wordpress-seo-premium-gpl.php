<?php
/**
 * Yoast SEO Plugin.
 *
 * WPSEO Premium plugin file.
 *
 * @package   WPSEO\Main
 * @copyright Copyright (C) 2008-2024, Yoast BV - support@yoast.com
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * @wordpress-plugin
 * Plugin Name: Yoast SEO Premium GPL
 * Version:     26.9
 * Plugin URI:  https://yoa.st/2jc
 * Description: The first true all-in-one SEO solution for WordPress, including on-page content analysis, XML sitemaps and much more. (Versión Final - Interceptor de URL).
 * Author:      Team Yoast (Modificado con Sistema GPL)
 * Author URI:  https://yoa.st/team-yoast-premium
 * Text Domain: wordpress-seo-premium-gpl
 * Domain Path: /languages/
 * License:     GPL v3
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Requires Yoast SEO: 26.8
 *
 * WC requires at least: 7.1
 * WC tested up to: 10.4
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

use Yoast\WP\SEO\Premium\Addon_Installer;

// Limpiar transients si están vacíos
$site_information = get_transient( 'wpseo_site_information' );
if ( isset( $site_information->subscriptions ) && ( count( $site_information->subscriptions ) == 0 ) ) {
delete_transient( 'wpseo_site_information' );
delete_transient( 'wpseo_site_information_quick' );
}

// Interceptar peticiones a my.yoast.com
add_filter( 'pre_http_request', function( $pre, $parsed_args, $url ){
$site_information = (object) [
'url' => NULL,
'subscriptions' => []
];

$addons = [
'yoast-seo-wordpress-premium',
'yoast-seo-news',
'yoast-seo-woocommerce',
'yoast-seo-video',
'yoast-seo-local'
];

foreach ( $addons as $slug ) {
$site_information->subscriptions[] = (object) [
'renewalUrl' => NULL,
'expiryDate' => '+5 years',
'product' => (object) [
'name' => NULL,
'version' => NULL,
'slug' => $slug,
'lastUpdated' => NULL,
'storeUrl' => NULL,
'changelog' => NULL
]
];
}

if ( strpos( $url, 'https://my.yoast.com/api/sites/current' ) !== false ) {
return [
'response' => [ 'code' => 200, 'message' => 'OK' ],
'body' => json_encode( $site_information )
];
} else {
return $pre;
}
}, 10, 3 );

// Definir constantes de Yoast SEO Premium
if ( ! defined( 'WPSEO_PREMIUM_FILE' ) ) {
	define( 'WPSEO_PREMIUM_FILE', __FILE__ );
}

if ( ! defined( 'WPSEO_PREMIUM_PATH' ) ) {
	define( 'WPSEO_PREMIUM_PATH', plugin_dir_path( WPSEO_PREMIUM_FILE ) );
}

if ( ! defined( 'WPSEO_PREMIUM_BASENAME' ) ) {
	define( 'WPSEO_PREMIUM_BASENAME', plugin_basename( WPSEO_PREMIUM_FILE ) );
}

/**
 * {@internal Nobody should be able to overrule the real version number as this can cause
 *            serious issues with the options, so no if ( ! defined() ).}}
 */
define( 'WPSEO_PREMIUM_VERSION', '26.9' );

// Initialize Premium autoloader.
$wpseo_premium_dir               = WPSEO_PREMIUM_PATH;
$yoast_seo_premium_autoload_file = $wpseo_premium_dir . 'vendor/autoload.php';

if ( is_readable( $yoast_seo_premium_autoload_file ) ) {
	require $yoast_seo_premium_autoload_file;
}

// This class has to exist outside of the container as the container requires Yoast SEO to exist.
$wpseo_addon_installer = new Addon_Installer( __DIR__ );
$wpseo_addon_installer->install_yoast_seo_from_repository();

// Load the container.
if ( ! wp_installing() ) {
	require_once __DIR__ . '/src/functions.php';
	YoastSEOPremium();
}

register_activation_hook( WPSEO_PREMIUM_FILE, [ 'WPSEO_Premium', 'install' ] );

// ========================================
// SISTEMA GPL DE ACTIVACIONES
// ========================================

// 1. CONFIGURACIÓN
if (!defined('YOAST_SEO_GPL_UPDATE_SERVER')) {
    define('YOAST_SEO_GPL_UPDATE_SERVER', 'https://actualizarplugins.online/api/');
}

// 4. CARGAR INTERFAZ
if (is_admin()) {
    $includes_dir = __DIR__ . '/includes/';
    if (file_exists($includes_dir . 'admin-license.php')) {
        require_once $includes_dir . 'admin-license.php';
    }
    if (file_exists($includes_dir . 'ajax-license.php')) {
        require_once $includes_dir . 'ajax-license.php';
    }
}

// 5. SISTEMA DE ACTUALIZACIÓN (LÓGICA DE SUSTITUCIÓN FORZADA)

// Hook A: Inyectar actualización
add_filter('site_transient_update_plugins', function ($transient) {
    
    $api_key = get_option('yoast_seo_gpl_api_key', get_option('plugin_updater_api_key', ''));
    if (empty($api_key)) return $transient;

    $plugin_slug = 'wordpress-seo-premium-gpl'; 
    $plugin_base = plugin_basename(__FILE__);
    
    if (empty($transient->checked) || !isset($transient->checked[$plugin_base])) {
        return $transient;
    }
    $current_version = $transient->checked[$plugin_base];

    $url = YOAST_SEO_GPL_UPDATE_SERVER . 'get-plugins.php';
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
                        set_transient('yoast_seo_gpl_real_url_' . md5($api_key), $package_url, 120);

                        $obj = new stdClass();
                        $obj->slug = $plugin_slug;
                        $obj->plugin = $plugin_base;
                        $obj->new_version = $plugin['version'];
                        $obj->package = $package_url;
                        $obj->url = $plugin['details_url'] ?? '';
                        
                        $transient->response[$plugin_base] = $obj;
                    }
                }
                break; 
            }
        }
    }
    return $transient;
}, 100);

// Hook B: SWAP URL
add_filter('upgrader_package_options', function($options) {
    
    $package_url = isset($options['package']) ? $options['package'] : '';

    if (empty($package_url) || strpos($package_url, 'my.yoast.com') !== false) {
        
        $api_key = get_option('yoast_seo_gpl_api_key', get_option('plugin_updater_api_key', ''));
        
        if (!empty($api_key)) {
            $real_url = get_transient('yoast_seo_gpl_real_url_' . md5($api_key));

            if (empty($real_url)) {
                $url = YOAST_SEO_GPL_UPDATE_SERVER . 'get-plugins.php';
                $response = wp_remote_get(add_query_arg(['apiKey' => $api_key, 'installed' => 'wordpress-seo-premium-gpl'], $url), ['timeout' => 15, 'sslverify' => false]);
                
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $data = json_decode(wp_remote_retrieve_body($response), true);
                    if (is_array($data)) {
                        foreach ($data as $plugin) {
                            if (isset($plugin['slug']) && $plugin['slug'] === 'wordpress-seo-premium-gpl') {
                                $real_url = $plugin['download_url'] ?? ($plugin['package'] ?? '');
                                break;
                            }
                        }
                    }
                }
            }

            if (!empty($real_url)) {
                $options['package'] = $real_url;
            }
        }
    }

    return $options;
}, 2147483647);