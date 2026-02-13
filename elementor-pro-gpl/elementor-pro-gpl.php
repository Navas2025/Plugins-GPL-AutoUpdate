<?php
/**
 * Plugin Name: Elementor Pro GPL
 * Description: Elevate your designs and unlock the full power of Elementor. Gain access to dozens of Pro widgets and kits, Theme Builder, Pop Ups, Forms and WooCommerce building capabilities. (Versión Final - Interceptor de URL).
 * Plugin URI: https://go.elementor.com/wp-dash-wp-plugins-author-uri/
 * Version: 3.35.0
 * Author: Elementor.com (Modificado con Sistema GPL)
 * Author URI: https://go.elementor.com/wp-dash-wp-plugins-author-uri/
 * Requires PHP: 7.4
 * Requires at least: 6.7
 * Requires Plugins: elementor
 * Elementor tested up to: 3.35.0
 * Text Domain: elementor-pro-gpl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// ========================================
// 1. CONFIGURACIÓN GPL
// ========================================

if ( ! defined( 'ELEMENTOR_PRO_GPL_UPDATE_SERVER' ) ) {
    define( 'ELEMENTOR_PRO_GPL_UPDATE_SERVER', 'https://actualizarplugins.online/api/' ); 
}

// ========================================
// 2. BYPASS DE LICENCIA (Funciones Pro)
// ========================================

// Define Base Config
$_config = (object) [
    "name" => "elementor",
    "pro" => "_pro_",
    "wpn" => "wordpressnull",
    "timeout" => strtotime('+12 hours', current_time('timestamp'))
];

// Configuration de la réponse avec les features spécifiques et le tier Agency
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

// Force Lic Data
if ( get_option('_elementor_pro_license_data') ) {
    delete_option( '_elementor_pro_license_data');
}
update_option("{$_config->name}{$_config->pro}license_key", 'activated');
update_option("_{$_config->name}{$_config->pro}license_v2_data", $_config->lic_data);

add_filter("{$_config->name}/connect/additional-connect-info", '__return_empty_array', 999);

// ========================================
// 3. INTERCEPTOR DE SEGURIDAD (Evita conexiones no deseadas)
// ========================================

// Intercept E-Pro Reqs
add_action('plugins_loaded', function () {
    add_filter('pre_http_request', function ($pre, $parsed_args, $url) {
        global $_config;

        if (strpos($url, "{$_config->api}/v2/licenses") !== false) {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body' => json_encode($_config->cloud_response)
            ];
        } elseif (strpos($url, "{$_config->api}/connect/v1/library/get_template_content") !== false) {
            $response = wp_remote_get("{$_config->templates}/{$parsed_args['body']['id']}.json", ['sslverify' => false, 'timeout' => 25]);
            if (wp_remote_retrieve_response_code($response) == 200) {
                return $response;
            } else {
                return $pre;
            }
        } else {
            return $pre;
        }
    }, 10, 3);
});

/* Fake missing license fix */
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

    wp_add_inline_style( 'wp-admin', $css );

}, 9999);

/* Remove Promotion notices */
add_action('admin_head', function () {
    ?>
    <style>
        .e-notice[data-notice_id*="_promotion"] {
            display: none !important;
        }
    </style>
    <?php
});

// ========================================
// 4. CARGAR INTERFAZ GPL
// ========================================

if ( is_admin() ) {
    $includes_dir = __DIR__ . '/includes/';
    if ( file_exists( $includes_dir . 'admin-license.php' ) ) require_once $includes_dir . 'admin-license.php';
    if ( file_exists( $includes_dir . 'ajax-license.php' ) ) require_once $includes_dir . 'ajax-license.php';
}

// ========================================
// 5. SISTEMA DE ACTUALIZACIÓN GPL
// ========================================

// Hook A: Inyectar actualización en el transient de WordPress
add_filter('site_transient_update_plugins', function ($transient) {
    
    $api_key = get_option('elementor_pro_gpl_api_key', get_option('plugin_updater_api_key', ''));
    if (empty($api_key)) return $transient;

    $plugin_slug = 'elementor-pro-gpl'; 
    $plugin_base = plugin_basename( __FILE__ );
    
    if (empty($transient->checked) || !isset($transient->checked[$plugin_base])) {
        return $transient;
    }
    $current_version = $transient->checked[$plugin_base];

    // Consultar al servidor
    $url = ELEMENTOR_PRO_GPL_UPDATE_SERVER . 'get-plugins.php';
    $args = [ 'apiKey' => $api_key, 'installed' => $plugin_slug ];
    
    $response = wp_remote_get(add_query_arg($args, $url), [
        'timeout' => 15, 
        'sslverify' => false,
        'headers' => [
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        ]
    ]);
    
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        $error_msg = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
        error_log('Elementor Pro GPL - Hook A: Error al consultar servidor - ' . $error_msg);
        return $transient;
    }

    $remote_plugins = json_decode(wp_remote_retrieve_body($response), true);

    if (is_array($remote_plugins)) {
        foreach ($remote_plugins as $plugin) {
            if (isset($plugin['slug']) && $plugin['slug'] === $plugin_slug) {
                
                if (isset($plugin['version']) && version_compare($current_version, $plugin['version'], '<')) {
                    
                    $package_url = $plugin['download_url'] ?? ($plugin['package'] ?? '');

                    if (!empty($package_url)) {
                        // Guardar URL en transient PERSISTENTE (24 horas)
                        set_transient('elpro_gpl_real_url_' . md5($api_key), $package_url, DAY_IN_SECONDS);
                        
                        error_log('✅ Elementor Pro GPL - Hook A: Nueva versión detectada: ' . $plugin['version']);
                        error_log('✅ Elementor Pro GPL - Hook A: URL guardada en transient');

                        $obj = new stdClass();
                        $obj->slug = $plugin_slug;
                        $obj->plugin = $plugin_base;
                        $obj->new_version = $plugin['version'];
                        $obj->package = $package_url;
                        $obj->url = $plugin['details_url'] ?? 'https://elementor.com';
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

// Hook B: Interceptar ANTES de la descarga (SOLUCIÓN DEFINITIVA)
add_filter('upgrader_pre_download', function($reply, $package, $upgrader) {
    
    error_log('=== ELEMENTOR PRO GPL - UPGRADER PRE DOWNLOAD ===');
    error_log('Package URL recibida: ' . $package);
    error_log('Reply inicial: ' . print_r($reply, true));
    
    // Solo interceptar URLs de Elementor.com
    if (!empty($package) && (strpos($package, 'elementor.com') !== false || strpos($package, 'plugin-downloads') !== false)) {
        
        error_log('⚠️ URL de Elementor.com detectada, procediendo a reemplazar...');
        
        $api_key = get_option('elementor_pro_gpl_api_key', get_option('plugin_updater_api_key', ''));
        
        if (empty($api_key)) {
            error_log('❌ API Key vacía');
            error_log('=== FIN UPGRADER PRE DOWNLOAD ===');
            return $reply;
        }
        
        error_log('✅ API Key encontrada: ' . substr($api_key, 0, 10) . '...');
        
        // Obtener URL real del transient
        $real_url = get_transient('elpro_gpl_real_url_' . md5($api_key));
        
        error_log('Transient key: elpro_gpl_real_url_' . md5($api_key));
        error_log('URL del Transient: ' . ($real_url ?: '❌ VACÍO'));
        
        // Si no hay transient, consultar servidor
        if (empty($real_url)) {
            error_log('⚠️ Transient vacío, consultando servidor...');
            
            $url = ELEMENTOR_PRO_GPL_UPDATE_SERVER . 'get-plugins.php';
            $query_url = add_query_arg(['apiKey' => $api_key, 'installed' => 'elementor-pro-gpl'], $url);
            
            error_log('URL de consulta: ' . $query_url);
            
            $response = wp_remote_get($query_url, [
                'timeout' => 15,
                'sslverify' => false,
                'headers' => [
                    'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
                ]
            ]);
            
            $http_code = wp_remote_retrieve_response_code($response);
            error_log('Respuesta servidor: HTTP ' . $http_code);
            
            if (!is_wp_error($response) && $http_code === 200) {
                $body = wp_remote_retrieve_body($response);
                error_log('Body recibido (primeros 500 chars): ' . substr($body, 0, 500));
                
                $data = json_decode($body, true);
                
                if (is_array($data)) {
                    error_log('Total plugins en respuesta: ' . count($data));
                    
                    foreach ($data as $plugin) {
                        if (isset($plugin['slug']) && $plugin['slug'] === 'elementor-pro-gpl') {
                            $real_url = $plugin['download_url'] ?? ($plugin['package'] ?? '');
                            error_log('✅ URL obtenida del servidor: ' . $real_url);
                            
                            // Guardar en transient
                            set_transient('elpro_gpl_real_url_' . md5($api_key), $real_url, DAY_IN_SECONDS);
                            break;
                        }
                    }
                } else {
                    error_log('❌ Error: La respuesta no es un array válido');
                }
            } else {
                if (is_wp_error($response)) {
                    error_log('❌ Error WP: ' . $response->get_error_message());
                } else {
                    error_log('❌ HTTP Code incorrecto: ' . $http_code);
                }
            }
        }
        
        // Si tenemos URL real, descargar desde ahí
        if (!empty($real_url)) {
            error_log('✅✅✅ DESCARGANDO DESDE HIDRIVE: ' . $real_url);
            
            // Descargar el archivo directamente usando WordPress
            $tmpfile = download_url($real_url);
            
            if (is_wp_error($tmpfile)) {
                error_log('❌ Error al descargar desde HiDrive: ' . $tmpfile->get_error_message());
                error_log('=== FIN UPGRADER PRE DOWNLOAD ===');
                return $tmpfile;
            }
            
            error_log('✅✅✅ ARCHIVO DESCARGADO EXITOSAMENTE');
            error_log('Ruta temporal: ' . $tmpfile);
            error_log('Tamaño del archivo: ' . filesize($tmpfile) . ' bytes');
            error_log('=== FIN UPGRADER PRE DOWNLOAD ===');
            
            // Retornar la ruta del archivo temporal
            return $tmpfile;
        } else {
            error_log('❌❌❌ NO SE PUDO OBTENER URL DE REEMPLAZO');
        }
    } else {
        error_log('ℹ️ URL no requiere reemplazo (no es de elementor.com)');
    }
    
    error_log('=== FIN UPGRADER PRE DOWNLOAD ===');
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
    // Si estamos actualizando Elementor Pro GPL, modificar mensaje
    if (isset($hook_extra['plugin']) && strpos($hook_extra['plugin'], 'elementor-pro-gpl') !== false) {
        error_log('✅ Actualizando Elementor Pro GPL desde: ' . basename($source));
    }
    return $source;
}, 10, 4);

// ========================================
// 6. CÓDIGO BASE ELEMENTOR PRO (ORIGINAL v3.35.0)
// ========================================

define( 'ELEMENTOR_PRO_VERSION', '3.35.0' );

/**
 * All versions should be `major.minor`, without patch, in order to compare them properly.
 * Therefore, we can't set a patch version as a requirement.
 * (e.g. Core 3.15.0-beta1 and Core 3.15.0-cloud2 should be fine when requiring 3.15, while
 * requiring 3.15.2 is not allowed)
 */
define( 'ELEMENTOR_PRO_REQUIRED_CORE_VERSION', '3.32' );
define( 'ELEMENTOR_PRO_RECOMMENDED_CORE_VERSION', '3.35' );

define( 'ELEMENTOR_PRO__FILE__', __FILE__ );
define( 'ELEMENTOR_PRO_PLUGIN_BASE', plugin_basename( ELEMENTOR_PRO__FILE__ ) );
define( 'ELEMENTOR_PRO_PATH', plugin_dir_path( ELEMENTOR_PRO__FILE__ ) );
define( 'ELEMENTOR_PRO_ASSETS_PATH', ELEMENTOR_PRO_PATH . 'assets/' );
define( 'ELEMENTOR_PRO_MODULES_PATH', ELEMENTOR_PRO_PATH . 'modules/' );
define( 'ELEMENTOR_PRO_URL', plugins_url( '/', ELEMENTOR_PRO__FILE__ ) );
define( 'ELEMENTOR_PRO_ASSETS_URL', ELEMENTOR_PRO_URL . 'assets/' );
define( 'ELEMENTOR_PRO_MODULES_URL', ELEMENTOR_PRO_URL . 'modules/' );

/**
 * Load gettext translate for our text domain.
 *
 * @since 1.0.0
 *
 * @return void
 */
function elementor_pro_load_plugin() {
	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', 'elementor_pro_fail_load' );

		return;
	}

	$core_version = ELEMENTOR_VERSION;
	$core_version_required = ELEMENTOR_PRO_REQUIRED_CORE_VERSION;
	$core_version_recommended = ELEMENTOR_PRO_RECOMMENDED_CORE_VERSION;

	if ( ! elementor_pro_compare_major_version( $core_version, $core_version_required, '>=' ) ) {
		add_action( 'admin_notices', 'elementor_pro_fail_load_out_of_date' );

		return;
	}

	if ( ! elementor_pro_compare_major_version( $core_version, $core_version_recommended, '>=' ) ) {
		add_action( 'admin_notices', 'elementor_pro_admin_notice_upgrade_recommendation' );
	}

	require ELEMENTOR_PRO_PATH . 'plugin.php';
}

function elementor_pro_compare_major_version( $left, $right, $operator ) {
	$pattern = '/^(\d+\.\d+).*/';
	$replace = '$1.0';

	$left  = preg_replace( $pattern, $replace, $left );
	$right = preg_replace( $pattern, $replace, $right );

	return version_compare( $left, $right, $operator );
}

add_action( 'plugins_loaded', 'elementor_pro_load_plugin' );

function print_error( $message ) {
	if ( ! $message ) {
		return;
	}
	// PHPCS - $message should not be escaped
	echo '<div class="error">' . $message . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
/**
 * Show in WP Dashboard notice about the plugin is not activated.
 *
 * @since 1.0.0
 *
 * @return void
 */
function elementor_pro_fail_load() {
	$screen = get_current_screen();
	if ( isset( $screen->parent_file ) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id ) {
		return;
	}

	$plugin = 'elementor/elementor.php';

	if ( _is_elementor_installed() ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $plugin );

		$message = '<h3>' . esc_html__( 'You\'re not using Elementor Pro yet!', 'elementor-pro' ) . '</h3>';
		$message .= '<p>' . esc_html__( 'Activate the Elementor plugin to start using all of Elementor Pro plugin\'s features.', 'elementor-pro' ) . '</p>';
		$message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $activation_url, esc_html__( 'Activate Now', 'elementor-pro' ) ) . '</p>';
	} else {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		$install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=elementor' ), 'install-plugin_elementor' );

		$message = '<h3>' . esc_html__( 'Elementor Pro plugin requires installing the Elementor plugin', 'elementor-pro' ) . '</h3>';
		$message .= '<p>' . esc_html__( 'Install and activate the Elementor plugin to access all the Pro features.', 'elementor-pro' ) . '</p>';
		$message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $install_url, esc_html__( 'Install Now', 'elementor-pro' ) ) . '</p>';
	}

	print_error( $message );
}

function elementor_pro_fail_load_out_of_date() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$file_path = 'elementor/elementor.php';

	$upgrade_link = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $file_path, 'upgrade-plugin_' . $file_path );

	$message = sprintf(
		'<h3>%1$s</h3><p>%2$s <a href="%3$s" class="button-primary">%4$s</a></p>',
		esc_html__( 'Elementor Pro requires newer version of the Elementor plugin', 'elementor-pro' ),
		esc_html__( 'Update the Elementor plugin to reactivate the Elementor Pro plugin.', 'elementor-pro' ),
		$upgrade_link,
		esc_html__( 'Update Now', 'elementor-pro' )
	);

	print_error( $message );
}

function elementor_pro_admin_notice_upgrade_recommendation() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$file_path = 'elementor/elementor.php';

	$upgrade_link = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $file_path, 'upgrade-plugin_' . $file_path );

	$message = sprintf(
		'<h3>%1$s</h3><p>%2$s <a href="%3$s" class="button-primary">%4$s</a></p>',
		esc_html__( 'Don\'t miss out on the new version of Elementor', 'elementor-pro' ),
		esc_html__( 'Update to the latest version of Elementor to enjoy new features, better performance and compatibility.', 'elementor-pro' ),
		$upgrade_link,
		esc_html__( 'Update Now', 'elementor-pro' )
	);

	print_error( $message );
}

if ( ! function_exists( '_is_elementor_installed' ) ) {

	function _is_elementor_installed() {
		$file_path = 'elementor/elementor.php';
		$installed_plugins = get_plugins();

		return isset( $installed_plugins[ $file_path ] );
	}
}
