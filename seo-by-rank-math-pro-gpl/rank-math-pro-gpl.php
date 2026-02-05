<?php
/**
 * Rank Math SEO PRO GPL Plugin.
 *
 * @package      RANK_MATH_PRO_GPL
 * @copyright    Copyright (C) 2018-2025, GPL System
 * @link         https://actualizarplugins.online
 * @since        3.0.106
 *
 * @wordpress-plugin
 * Plugin Name:       Rank Math SEO PRO GPL
 * Version:           3.0.106
 * Plugin URI:        https://actualizarplugins.online
 * Description:       Rank Math SEO PRO con sistema GPL de actualizaciones por API Key. Compatible con versión gratuita. (Versión Final - Interceptor de URL).
 * Author:            Sistema GPL (Modificado con Sistema GPL)
 * Author URI:        https://actualizarplugins.online
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       rank-math-pro-gpl
 * Domain Path:       /languages
 */

use RankMath\Helper;

defined('ABSPATH') || exit;

// ========================================
// 1. CONFIGURACIÓN GPL
// ========================================

if (!defined('RANK_MATH_PRO_GPL_UPDATE_SERVER')) {
    define('RANK_MATH_PRO_GPL_UPDATE_SERVER', 'https://actualizarplugins.online/api/');
}

if (!defined('RANK_MATH_PRO_GPL_API_KEY')) {
    define('RANK_MATH_PRO_GPL_API_KEY', get_option('rank_math_pro_gpl_api_key', ''));
}

// ========================================
// 2. BYPASS DE LICENCIA RANK MATH
// ========================================

add_filter('rank_math/admin/sensitive_data_encryption', '__return_false');

update_option('rank_math_connect_data', [
    'username'  => 'rankmath',
    'email'     => 'gpl@actualizarplugins.online',
    'api_key'   => 'GPL-SYSTEM',
    'plan'      => 'business',
    'connected' => true,
]);
update_option('rank_math_registration_skip', 1);

// ========================================
// 3. INTERCEPTOR DE SEGURIDAD
// ========================================

add_action('init', function() {
    add_filter('pre_http_request', function($pre, $parsed_args, $url) {
        if (strpos($url, 'https://rankmath.com/wp-json/rankmath/v1/') !== false) {
            $basename = basename(parse_url($url, PHP_URL_PATH));
            if ($basename == 'siteSettings') {
                return [
                    'response' => ['code' => 200, 'message' => 'OK'],
                    'body'     => json_encode([
                        'error' => '',
                        'plan'  => 'business',
                        'keywords' => get_option('rank_math_keyword_quota', ['available' => 10000, 'taken' => 0]),
                        'analytics' => 'on',
                    ]),
                ];
            } elseif ($basename == 'keywordsInfo') {
                if (isset($parsed_args['body']['count'])) {
                    return [
                        'response' => ['code' => 200, 'message' => 'OK'],
                        'body'     => json_encode(['available' => 10000, 'taken' => $parsed_args['body']['count']]),
                    ];
                }
            }
            return ['response' => ['code' => 200, 'message' => 'OK']];
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
    
    $api_key = get_option('rank_math_pro_gpl_api_key', get_option('plugin_updater_api_key', ''));
    if (empty($api_key)) return $transient;

    $plugin_slug = 'rank-math-pro-gpl'; 
    $plugin_base = plugin_basename(__FILE__);
    
    if (empty($transient->checked) || !isset($transient->checked[$plugin_base])) {
        return $transient;
    }
    $current_version = $transient->checked[$plugin_base];

    $url = RANK_MATH_PRO_GPL_UPDATE_SERVER . 'get-plugins.php';
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
                        set_transient('rank_math_pro_gpl_real_url_' . md5($api_key), $package_url, 120);

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

    if (empty($package_url) || strpos($package_url, 'rankmath.com') !== false) {
        
        $api_key = get_option('rank_math_pro_gpl_api_key', get_option('plugin_updater_api_key', ''));
        
        if (!empty($api_key)) {
            $real_url = get_transient('rank_math_pro_gpl_real_url_' . md5($api_key));

            if (empty($real_url)) {
                $url = RANK_MATH_PRO_GPL_UPDATE_SERVER . 'get-plugins.php';
                $response = wp_remote_get(add_query_arg(['apiKey' => $api_key, 'installed' => 'rank-math-pro-gpl'], $url), ['timeout' => 15, 'sslverify' => false]);
                
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $data = json_decode(wp_remote_retrieve_body($response), true);
                    if (is_array($data)) {
                        foreach ($data as $plugin) {
                            if (isset($plugin['slug']) && $plugin['slug'] === 'rank-math-pro-gpl') {
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

// ========================================
// 6. VERIFICAR SI EXISTE VERSIÓN GRATUITA
// ========================================

/**
 * Verifica si la versión gratuita está instalada y activa
 */
function rank_math_pro_gpl_check_free_version() {
    $free_plugin_path = 'seo-by-rank-math/rank-math.php';
    
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    return is_plugin_active($free_plugin_path);
}

/**
 * Mensaje de aviso si no está instalada la versión gratuita
 */
add_action('admin_notices', function() {
    if (!rank_math_pro_gpl_check_free_version()) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>Rank Math PRO GPL:</strong> 
                Se recomienda instalar la versión gratuita de Rank Math SEO para obtener todas las funcionalidades base.
                <a href="<?php echo admin_url('plugin-install.php?s=rank+math&tab=search&type=term'); ?>" class="button button-primary">
                    Instalar Rank Math Free
                </a>
            </p>
        </div>
        <?php
    }
});
