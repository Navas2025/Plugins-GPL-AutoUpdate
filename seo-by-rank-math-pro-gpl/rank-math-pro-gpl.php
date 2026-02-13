<?php
/**
 * Rank Math SEO PRO GPL Plugin.
 *
 * @package      RANK_MATH_PRO_GPL
 * @copyright    Copyright (C) 2018-2025, GPL System
 * @link         https://actualizarplugins.online
 * @since        3.0.107
 *
 * @wordpress-plugin
 * Plugin Name:       Rank Math SEO PRO GPL
 * Version:           3.0.107
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

    $plugin_slug = 'seo-by-rank-math-pro-gpl'; 
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
                        set_transient('rankmath_gpl_real_url_' . md5($api_key), $package_url, DAY_IN_SECONDS);
                        error_log('✅ Rank Math GPL v3.0.107 - Hook A: New version detected: ' . $plugin['version']);
                        error_log('✅ Rank Math GPL v3.0.107 - Hook A: URL saved in transient');

                        $obj = new stdClass();
                        $obj->slug = $plugin_slug;
                        $obj->plugin = $plugin_base;
                        $obj->new_version = $plugin['version'];
                        $obj->package = $package_url;
                        $obj->url = $plugin['details_url'] ?? 'https://rankmath.com';
                        $obj->tested = $plugin['tested'] ?? '6.7';
                        $obj->requires = $plugin['requires'] ?? '6.0';
                        $obj->requires_php = $plugin['requires_php'] ?? '7.4';
                        
                        $transient->response[$plugin_base] = $obj;
                    }
                }
                break; 
            }
        }
    }
    return $transient;
}, 100);

// Hook B: Intercept BEFORE download (DEFINITIVE SOLUTION)
add_filter('upgrader_pre_download', function($reply, $package, $upgrader) {
    
    error_log('=== RANK MATH PRO GPL v3.0.107 - UPGRADER PRE DOWNLOAD ===');
    error_log('Package URL received: ' . $package);
    error_log('Reply inicial: ' . print_r($reply, true));
    
    // Only intercept URLs from rankmath.com
    if (!empty($package) && strpos($package, 'rankmath.com') !== false) {
        
        error_log('⚠️ Rank Math URL detected, proceeding to replace...');
        
        $api_key = get_option('rank_math_pro_gpl_api_key', get_option('plugin_updater_api_key', ''));
        
        if (empty($api_key)) {
            error_log('❌ API Key is empty');
            error_log('=== END UPGRADER PRE DOWNLOAD ===');
            return $reply;
        }
        
        error_log('✅ API Key found: ' . substr($api_key, 0, 10) . '...');
        
        // Get real URL from transient
        $real_url = get_transient('rankmath_gpl_real_url_' . md5($api_key));
        
        error_log('Transient key: rankmath_gpl_real_url_' . md5($api_key));
        error_log('URL from Transient: ' . ($real_url ?: '❌ EMPTY'));
        
        // If no transient, query server
        if (empty($real_url)) {
            error_log('⚠️ Empty transient, querying server...');
            
            $url = RANK_MATH_PRO_GPL_UPDATE_SERVER . 'get-plugins.php';
            $query_url = add_query_arg(['apiKey' => $api_key, 'installed' => 'seo-by-rank-math-pro-gpl'], $url);
            
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
                        if (isset($plugin['slug']) && $plugin['slug'] === 'seo-by-rank-math-pro-gpl') {
                            $real_url = $plugin['download_url'] ?? ($plugin['package'] ?? '');
                            error_log('✅ URL obtained from server: ' . $real_url);
                            
                            // Save in transient
                            set_transient('rankmath_gpl_real_url_' . md5($api_key), $real_url, DAY_IN_SECONDS);
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
        }
    } else {
        error_log('ℹ️ URL does not require replacement (not from rankmath.com)');
    }
    
    error_log('=== END UPGRADER PRE DOWNLOAD ===');
    return $reply;
    
}, 10, 3);

// Ocultar URL de descarga en la interfaz de WordPress
add_filter('gettext', function($translation, $text, $domain) {
    // Interceptar mensaje "Downloading update from %s&#8230;"
    if ($text === 'Downloading update from %s&#8230;' || $text === 'Downloading update from %s…') {
        // Aplicar en contexto admin o durante actualizaciones automáticas
        if (is_admin() || (defined('DOING_CRON') && DOING_CRON)) {
            return 'Descargando actualización desde servidor seguro...';
        }
    }
    return $translation;
}, 10, 3);

// Filtro adicional para ocultar URL en el upgrader
add_filter('upgrader_source_selection', function($source, $remote_source, $upgrader, $hook_extra) {
    // Si estamos actualizando Rank Math Pro GPL, modificar mensaje
    if (isset($hook_extra['plugin']) && strpos($hook_extra['plugin'], 'rank-math-pro-gpl') !== false) {
        error_log('✅ Actualizando Rank Math Pro GPL desde: ' . basename($source));
    }
    return $source;
}, 10, 4);

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
