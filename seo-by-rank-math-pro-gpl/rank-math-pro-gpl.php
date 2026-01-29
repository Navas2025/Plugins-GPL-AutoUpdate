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
 * Description:       Rank Math SEO PRO con sistema GPL de actualizaciones por API Key. Compatible con versión gratuita.
 * Author:            Sistema GPL
 * Author URI:        https://actualizarplugins.online
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       rank-math-pro-gpl
 * Domain Path:       /languages
 */

use RankMath\Helper;

defined('ABSPATH') || exit;

// ========== CONFIGURACIÓN GPL ==========

if (!defined('RANK_MATH_PRO_GPL_UPDATE_SERVER')) {
    define('RANK_MATH_PRO_GPL_UPDATE_SERVER', 'https://actualizarplugins.online/api/');
}

if (!defined('RANK_MATH_PRO_GPL_API_KEY')) {
    define('RANK_MATH_PRO_GPL_API_KEY', get_option('rank_math_pro_gpl_api_key', ''));
}

// ========== BYPASS DE LICENCIA RANK MATH ==========

add_filter('rank_math/admin/sensitive_data_encryption', '__return_false');

update_option('rank_math_connect_data', [
    'username'  => 'rankmath',
    'email'     => 'gpl@actualizarplugins.online',
    'api_key'   => 'GPL-SYSTEM',
    'plan'      => 'business',
    'connected' => true,
]);
update_option('rank_math_registration_skip', 1);

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

// ========== CARGAR INTERFAZ GPL ==========

if (is_admin()) {
    $includes_dir = __DIR__ . '/includes/';
    if (file_exists($includes_dir . 'admin-license.php')) {
        require_once $includes_dir . 'admin-license.php';
    }
    if (file_exists($includes_dir . 'ajax-license.php')) {
        require_once $includes_dir . 'ajax-license.php';
    }
}

// ========== CARGAR GESTOR DE ACTUALIZACIONES ==========

if (file_exists(__DIR__ . '/includes/class-update-manager.php')) {
    require_once __DIR__ . '/includes/class-update-manager.php';
    new Rank_Math_Pro_GPL_Update_Manager();
}

// ========== VERIFICAR SI EXISTE VERSIÓN GRATUITA ==========

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
