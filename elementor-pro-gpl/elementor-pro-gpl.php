<?php
/**
 * Plugin Name: Elementor Pro GPL
 * Description: Elevate your designs and unlock the full power of Elementor. (Sistema de actualización GPL con API).
 * Plugin URI: https://elementor.com/
 * Version: 3.34.3
 * Author: Elementor.com (Modificado con Sistema GPL)
 * Author URI: https://elementor.com/
 * Requires PHP: 7.4
 * Requires at least: 6.7
 * Requires Plugins: elementor
 * Elementor tested up to: 3.34.0
 * Text Domain: elementor-pro-gpl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ========== CONFIGURACIÓN ==========

// Define Base Config
$_config = (object) [
    "name" => "elementor",
    "pro" => "_pro_",
    "wpn" => "wordpressnull",
    "timeout" => strtotime('+12 hours', current_time('timestamp'))
];

// Servidor de actualizaciones GPL
if ( ! defined( 'ELEMENTOR_PRO_GPL_UPDATE_SERVER' ) ) {
    define( 'ELEMENTOR_PRO_GPL_UPDATE_SERVER', 'https://actualizarplugins.online/api/' );
}

// Configuración de la respuesta con features específicas y tier Agency
$_config->cloud_response = [
    'success' => true,
    'license' => 'valid',
    'status' => 'valid',
    'expires' => '10.10.2030',
    'tier' => 'agency',
    'features' => [
        'form-submissions',
        'element-manager-permissions',
        'notes'
    ]
];

$_config->lic_response = $_config->cloud_response;
$_config->api = "https://my.{$_config->name}.com/api";
$_config->templates = "http://{$_config->wpn}.org/{$_config->name}/templates";
$_config->lic_data = ['timeout' => $_config->timeout, 'value' => json_encode($_config->lic_response)];

// ========== BYPASS DE LICENCIA (Funciones Pro) ==========

if ( get_option('_elementor_pro_license_data') ) {
    delete_option('_elementor_pro_license_data');
}
update_option("{$_config->name}{$_config->pro}license_key", 'activated');
update_option("_{$_config->name}{$_config->pro}license_v2_data", $_config->lic_data);

add_filter("{$_config->name}/connect/additional-connect-info", '__return_empty_array', 999);

// ========== INTERCEPTOR DE SEGURIDAD ==========

add_action('plugins_loaded', function () {
    add_filter('pre_http_request', function ($pre, $parsed_args, $url) {
        global $_config;

        if (strpos($url, "{$_config->api}/v2/licenses") !== false) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body' => json_encode($_config->cloud_response)
            ];
        } elseif (strpos($url, "{$_config->api}/connect/v1/library/get_template_content") !== false) {
            $response = wp_remote_get("{$_config->templates}/{$parsed_args['body']['id']}.json", ['timeout' => 25]);
            if (wp_remote_retrieve_response_code($response) === 200) {
                return $response;
            } else {
                return $pre;
            }
        }

        return $pre;
    }, 10, 3);
});

// ========== FAKE LICENSE FIX (CSS) ==========

add_action('admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'elementor_page_elementor-license' ) {
        return;
    }

    $css = '
    .wrap.elementor-admin-page-license
    .elementor-license-box h3 > span {
        position: relative !important;
        color: transparent !important;
        font-style: normal !important;
    }

    .wrap.elementor-admin-page-license
    .elementor-license-box h3 > span::after {
        content: "Active";
        position: absolute;
        left: 6px;
        top: 0;
        color: #46b450 !important;
        font-weight: 600 !important;
        white-space: nowrap;
        font-style: italic;
    }
    ';

    wp_add_inline_style('wp-admin', $css);
}, 9999);

// ========== OCULTAR AVISOS DE PROMOCIÓN ==========

add_action('admin_head', function () {
    ?>
    <style>
        .e-notice[data-notice_id*="_promotion"] {
            display: none !important;
        }
    </style>
    <?php
});

// ========== CARGAR INTERFAZ ADMIN ==========

if ( is_admin() ) {
    $includes_dir = __DIR__ . '/includes/';
    if ( file_exists( $includes_dir . 'admin-license.php' ) ) require_once $includes_dir . 'admin-license.php';
    if ( file_exists( $includes_dir . 'ajax-license.php' ) ) require_once $includes_dir . 'ajax-license.php';
}

// ========== SISTEMA DE ACTUALIZACIÓN GPL (API KEY) ==========

add_filter('pre_set_site_transient_update_plugins', function ($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_slug = plugin_basename(__FILE__);
    $current_version = ELEMENTOR_PRO_VERSION;

    $remote = wp_remote_post(ELEMENTOR_PRO_GPL_UPDATE_SERVER . 'check-update', [
        'body' => [
            'plugin_slug' => 'elementor-pro',
            'current_version' => $current_version,
            'api_key' => 'GPL-2024-PREMIUM-ACCESS'
        ],
        'timeout' => 10
    ]);

    if (!is_wp_error($remote) && isset($remote['response']['code']) && $remote['response']['code'] === 200 && !empty($remote['body'])) {
        $remote_data = json_decode($remote['body']);

        if ($remote_data && version_compare($current_version, $remote_data->version, '<')) {
            $transient->response[$plugin_slug] = (object) [
                'slug' => 'elementor-pro-gpl',
                'plugin' => $plugin_slug,
                'new_version' => $remote_data->version,
                'url' => $remote_data->url,
                'package' => $remote_data->package,
                'tested' => $remote_data->tested,
                'requires_php' => '7.4'
            ];
        }
    }

    return $transient;
});

add_filter('plugins_api', function ($res, $action, $args) {
    if ($action !== 'plugin_information') {
        return $res;
    }

    if ($args->slug !== 'elementor-pro-gpl') {
        return $res;
    }

    $remote = wp_remote_post(ELEMENTOR_PRO_GPL_UPDATE_SERVER . 'plugin-info', [
        'body' => [
            'plugin_slug' => 'elementor-pro',
            'api_key' => 'GPL-2024-PREMIUM-ACCESS'
        ],
        'timeout' => 10
    ]);

    if (!is_wp_error($remote) && isset($remote['response']['code']) && $remote['response']['code'] === 200 && !empty($remote['body'])) {
        $remote_data = json_decode($remote['body']);

        $res = (object) [
            'name' => $remote_data->name,
            'slug' => 'elementor-pro-gpl',
            'version' => $remote_data->version,
            'tested' => $remote_data->tested,
            'requires' => '6.7',
            'requires_php' => '7.4',
            'author' => '<a href="https://elementor.com">Elementor.com</a>',
            'author_profile' => 'https://elementor.com',
            'download_link' => $remote_data->package,
            'trunk' => $remote_data->package,
            'last_updated' => $remote_data->last_updated,
            'sections' => [
                'description' => $remote_data->description,
                'changelog' => $remote_data->changelog
            ],
            'banners' => [
                'low' => $remote_data->banner_low,
                'high' => $remote_data->banner_high
            ]
        ];

        return $res;
    }

    return $res;
}, 10, 3);

add_action('upgrader_process_complete', function ($upgrader_object, $options) {
    if ($options['action'] == 'update' && $options['type'] === 'plugin') {
        delete_transient('update_plugins');
    }
}, 10, 2);

// ========== CONSTANTES DE ELEMENTOR PRO ==========

define('ELEMENTOR_PRO_VERSION', '3.34.3');
define('ELEMENTOR_PRO_REQUIRED_CORE_VERSION', '3.32');
define('ELEMENTOR_PRO_RECOMMENDED_CORE_VERSION', '3.34');

define('ELEMENTOR_PRO__FILE__', __FILE__);
define('ELEMENTOR_PRO_PLUGIN_BASE', plugin_basename(ELEMENTOR_PRO__FILE__));
define('ELEMENTOR_PRO_PATH', plugin_dir_path(ELEMENTOR_PRO__FILE__));
define('ELEMENTOR_PRO_ASSETS_PATH', ELEMENTOR_PRO_PATH . 'assets/');
define('ELEMENTOR_PRO_MODULES_PATH', ELEMENTOR_PRO_PATH . 'modules/');
define('ELEMENTOR_PRO_URL', plugins_url('/', ELEMENTOR_PRO__FILE__));
define('ELEMENTOR_PRO_ASSETS_URL', ELEMENTOR_PRO_URL . 'assets/');
define('ELEMENTOR_PRO_MODULES_URL', ELEMENTOR_PRO_URL . 'modules/');

// ========== CARGA DEL PLUGIN ==========

function elementor_pro_load_plugin() {
    if (!did_action('elementor/loaded')) {
        add_action('admin_notices', 'elementor_pro_fail_load');
        return;
    }

    $core_version = ELEMENTOR_VERSION;
    $core_version_required = ELEMENTOR_PRO_REQUIRED_CORE_VERSION;
    $core_version_recommended = ELEMENTOR_PRO_RECOMMENDED_CORE_VERSION;

    if (!elementor_pro_compare_major_version($core_version, $core_version_required, '>=')) {
        add_action('admin_notices', 'elementor_pro_fail_load_out_of_date');
        return;
    }

    if (!elementor_pro_compare_major_version($core_version, $core_version_recommended, '>=')) {
        add_action('admin_notices', 'elementor_pro_admin_notice_upgrade_recommendation');
    }

    require ELEMENTOR_PRO_PATH . 'plugin.php';
}

function elementor_pro_compare_major_version($left, $right, $operator) {
    $pattern = '/^(\d+\.\d+).*/';
    $replace = '$1.0';

    $left  = preg_replace($pattern, $replace, $left);
    $right = preg_replace($pattern, $replace, $right);

    return version_compare($left, $right, $operator);
}

add_action('plugins_loaded', 'elementor_pro_load_plugin');

function print_error($message) {
    if (!$message) {
        return;
    }
    echo '<div class="error">' . $message . '</div>';
}

function elementor_pro_fail_load() {
    $screen = get_current_screen();
    if (isset($screen->parent_file) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id) {
        return;
    }

    $plugin = 'elementor/elementor.php';

    if (_is_elementor_installed()) {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $activation_url = wp_nonce_url('plugins.php?action=activate&amp;plugin=' . $plugin . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $plugin);

        $message = '<h3>' . esc_html__('You\'re not using Elementor Pro yet!', 'elementor-pro') . '</h3>';
        $message .= '<p>' . esc_html__('Activate the Elementor plugin to start using all of Elementor Pro plugin\'s features.', 'elementor-pro') . '</p>';
        $message .= '<p>' . sprintf('<a href="%s" class="button-primary">%s</a>', $activation_url, esc_html__('Activate Now', 'elementor-pro')) . '</p>';
    } else {
        if (!current_user_can('install_plugins')) {
            return;
        }

        $install_url = wp_nonce_url(self_admin_url('update.php?action=install-plugin&plugin=elementor'), 'install-plugin_elementor');

        $message = '<h3>' . esc_html__('Elementor Pro plugin requires installing the Elementor plugin', 'elementor-pro') . '</h3>';
        $message .= '<p>' . esc_html__('Install and activate the Elementor plugin to access all the Pro features.', 'elementor-pro') . '</p>';
        $message .= '<p>' . sprintf('<a href="%s" class="button-primary">%s</a>', $install_url, esc_html__('Install Now', 'elementor-pro')) . '</p>';
    }

    print_error($message);
}

function elementor_pro_fail_load_out_of_date() {
    if (!current_user_can('update_plugins')) {
        return;
    }

    $file_path = 'elementor/elementor.php';
    $upgrade_link = wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=') . $file_path, 'upgrade-plugin_' . $file_path);

    $message = sprintf(
        '<h3>%1$s</h3><p>%2$s <a href="%3$s" class="button-primary">%4$s</a></p>',
        esc_html__('Elementor Pro requires newer version of the Elementor plugin', 'elementor-pro'),
        esc_html__('Update the Elementor plugin to reactivate the Elementor Pro plugin.', 'elementor-pro'),
        $upgrade_link,
        esc_html__('Update Now', 'elementor-pro')
    );

    print_error($message);
}

function elementor_pro_admin_notice_upgrade_recommendation() {
    if (!current_user_can('update_plugins')) {
        return;
    }

    $file_path = 'elementor/elementor.php';
    $upgrade_link = wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=') . $file_path, 'upgrade-plugin_' . $file_path);

    $message = sprintf(
        '<h3>%1$s</h3><p>%2$s <a href="%3$s" class="button-primary">%4$s</a></p>',
        esc_html__('Don\'t miss out on the new version of Elementor', 'elementor-pro'),
        esc_html__('Update to the latest version of Elementor to enjoy new features, better performance and compatibility.', 'elementor-pro'),
        $upgrade_link,
        esc_html__('Update Now', 'elementor-pro')
    );

    print_error($message);
}

if (!function_exists('_is_elementor_installed')) {
    function _is_elementor_installed() {
        $file_path = 'elementor/elementor.php';
        $installed_plugins = get_plugins();
        return isset($installed_plugins[$file_path]);
    }
}
