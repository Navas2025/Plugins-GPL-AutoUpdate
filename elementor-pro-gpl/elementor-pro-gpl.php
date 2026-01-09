<?php
/**
 * Plugin Name: Elementor Pro GPL
 * Description: Elevate your designs and unlock the full power of Elementor. (Versión Final - Interceptor de URL).
 * Plugin URI: https://elementor.com/
 * Version: 3.33.1
 * Author: Elementor.com (Modificado con Sistema GPL)
 * Author URI: https://elementor.com/
 * Requires PHP: 7.4
 * Requires at least: 6.6
 * Requires Plugins: elementor
 * Elementor tested up to: 3.33.0
 * Text Domain: elementor-pro-gpl
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

// 1. CONFIGURACIÓN
if ( ! defined( 'ELEMENTOR_PRO_GPL_UPDATE_SERVER' ) ) {
    define( 'ELEMENTOR_PRO_GPL_UPDATE_SERVER', 'https://actualizarplugins.online/api/' ); 
}

// 2. BYPASS DE LICENCIA (Funciones Pro)
if ( get_option('_elementor_pro_license_data') ) {
	delete_option( '_elementor_pro_license_data');
}
update_option( 'elementor_pro_license_key', 'activated' );
update_option( '_elementor_pro_license_v2_data', [ 'timeout' => strtotime( '+12 hours', current_time( 'timestamp' ) ), 'value' => json_encode( [ 'success' => true, 'license' => 'valid', 'expires' => '01.01.2030', 'features' => [] ] ) ] );
add_filter( 'elementor/connect/additional-connect-info', '__return_empty_array', 999 );


// 3. INTERCEPTOR DE SEGURIDAD (Evita conexiones no deseadas)
add_action( 'plugins_loaded', function() {
	add_filter( 'pre_http_request', function( $pre, $parsed_args, $url ) {
        // Simular respuesta OK para licencia
		if ( strpos( $url, 'my.elementor.com/api/v2/licenses' ) !== false ) {
			return [ 'response' => [ 'code' => 200, 'message' => 'ОК' ], 'body' => json_encode( [ 'success' => true, 'license' => 'valid', 'expires' => '10.10.2030' ] ) ];
		} 
        // Simular respuesta OK para librería
        elseif ( strpos( $url, 'my.elementor.com/api/connect/v1/library/get_template_content' ) !== false ) {
			$response = wp_remote_get( "http://wordpressnull.org/elementor/templates/{$parsed_args['body']['id']}.json", [ 'sslverify' => false, 'timeout' => 25 ] );
			return ( wp_remote_retrieve_response_code( $response ) == 200 ) ? $response : $pre;
		}
		return $pre;
	}, 10, 3 );
});

// 4. CARGAR INTERFAZ
if ( is_admin() ) {
    $includes_dir = __DIR__ . '/includes/';
    if ( file_exists( $includes_dir . 'admin-license.php' ) ) require_once $includes_dir . 'admin-license.php';
    if ( file_exists( $includes_dir . 'ajax-license.php' ) ) require_once $includes_dir . 'ajax-license.php';
}

// 5. SISTEMA DE ACTUALIZACIÓN (LÓGICA DE SUSTITUCIÓN FORZADA)
// ------------------------------------------------------------------

/**
 * Hook A: Inyectar actualización en el transient de WordPress.
 */
add_filter('site_transient_update_plugins', function ($transient) {
    
    $api_key = get_option('elementor_pro_gpl_api_key', get_option('plugin_updater_api_key', ''));
    if (empty($api_key)) return $transient;

    $plugin_slug = 'elementor-pro-gpl'; 
    $plugin_base = plugin_basename( __FILE__ );
    
    if (empty($transient->checked) || !isset($transient->checked[$plugin_base])) {
        return $transient;
    }
    $current_version = $transient->checked[$plugin_base];

    // Llamada a TU servidor
    $url = ELEMENTOR_PRO_GPL_UPDATE_SERVER . 'get-plugins.php';
    $args = [ 'apiKey' => $api_key, 'installed' => $plugin_slug ];
    
    $response = wp_remote_get(add_query_arg($args, $url), ['timeout' => 15, 'sslverify' => false]);
    
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) return $transient;

    $remote_plugins = json_decode(wp_remote_retrieve_body($response), true);

    if (is_array($remote_plugins)) {
        foreach ($remote_plugins as $plugin) {
            if (isset($plugin['slug']) && $plugin['slug'] === $plugin_slug) {
                
                if (isset($plugin['version']) && version_compare($current_version, $plugin['version'], '<')) {
                    
                    // Extraer URL de HiDrive desde tu JSON
                    $package_url = $plugin['download_url'] ?? ($plugin['package'] ?? '');

                    if (!empty($package_url)) {
                        // Guardar URL en transient para acceso rápido en el Hook B
                        set_transient('elpro_gpl_real_url_' . md5($api_key), $package_url, 120);

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


/**
 * Hook B: SWAP URL (La pieza clave).
 * Detecta si la URL es de Elementor.com y la cambia por la de HiDrive.
 */
add_filter('upgrader_package_options', function($options) {
    
    // URL actual que WordPress intenta descargar
    $package_url = isset($options['package']) ? $options['package'] : '';

    // Si la URL está vacía O pertenece a elementor.com, intervenimos
    if ( empty($package_url) || strpos($package_url, 'elementor.com') !== false ) {
        
        $api_key = get_option('elementor_pro_gpl_api_key', get_option('plugin_updater_api_key', ''));
        
        if (!empty($api_key)) {
            // 1. Intentar recuperar del transient (rápido)
            $real_url = get_transient('elpro_gpl_real_url_' . md5($api_key));

            // 2. Si no está en transient, hacemos llamada de emergencia al servidor
            if (empty($real_url)) {
                $url = ELEMENTOR_PRO_GPL_UPDATE_SERVER . 'get-plugins.php';
                $response = wp_remote_get(add_query_arg(['apiKey' => $api_key, 'installed' => 'elementor-pro-gpl'], $url), ['timeout' => 15, 'sslverify' => false]);
                
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $data = json_decode(wp_remote_retrieve_body($response), true);
                    if (is_array($data)) {
                        foreach ($data as $plugin) {
                            if (isset($plugin['slug']) && $plugin['slug'] === 'elementor-pro-gpl') {
                                $real_url = $plugin['download_url'] ?? ($plugin['package'] ?? '');
                                break;
                            }
                        }
                    }
                }
            }

            // 3. Si tenemos la URL real, hacemos el cambio
            if (!empty($real_url)) {
                $options['package'] = $real_url;
                
                // IMPORTANTE: Limpiar hook_extra para evitar validaciones extrañas de WP
                // Esto a veces ayuda cuando se cambian dominios
                // $options['hook_extra'] = []; 
            }
        }
    }

    return $options;
}, 2147483647); // Prioridad Máxima


// 6. CÓDIGO BASE ELEMENTOR PRO (ORIGINAL)
// ------------------------------------------------------------------

define( 'ELEMENTOR_PRO_VERSION', '3.33.1' );
define( 'ELEMENTOR_PRO_REQUIRED_CORE_VERSION', '3.31' );
define( 'ELEMENTOR_PRO_RECOMMENDED_CORE_VERSION', '3.33' );
define( 'ELEMENTOR_PRO__FILE__', __FILE__ );
define( 'ELEMENTOR_PRO_PLUGIN_BASE', plugin_basename( ELEMENTOR_PRO__FILE__ ) );
define( 'ELEMENTOR_PRO_PATH', plugin_dir_path( ELEMENTOR_PRO__FILE__ ) );
define( 'ELEMENTOR_PRO_ASSETS_PATH', ELEMENTOR_PRO_PATH . 'assets/' );
define( 'ELEMENTOR_PRO_MODULES_PATH', ELEMENTOR_PRO_PATH . 'modules/' );
define( 'ELEMENTOR_PRO_URL', plugins_url( '/', ELEMENTOR_PRO__FILE__ ) );
define( 'ELEMENTOR_PRO_ASSETS_URL', ELEMENTOR_PRO_URL . 'assets/' );
define( 'ELEMENTOR_PRO_MODULES_URL', ELEMENTOR_PRO_URL . 'modules/' );

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
	if ( ! $message ) return;
	echo '<div class="error">' . $message . '</div>';
}

function elementor_pro_fail_load() {
	$screen = get_current_screen();
	if ( isset( $screen->parent_file ) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id ) return;
	$plugin = 'elementor/elementor.php';
	if ( _is_elementor_installed() ) {
		if ( ! current_user_can( 'activate_plugins' ) ) return;
		$activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $plugin );
		$message = '<h3>' . esc_html__( 'You\'re not using Elementor Pro yet!', 'elementor-pro-gpl' ) . '</h3>';
		$message .= '<p>' . esc_html__( 'Activate the Elementor plugin to start using all of Elementor Pro plugin’s features.', 'elementor-pro-gpl' ) . '</p>';
		$message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $activation_url, esc_html__( 'Activate Now', 'elementor-pro-gpl' ) ) . '</p>';
	} else {
		if ( ! current_user_can( 'install_plugins' ) ) return;
		$install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=elementor' ), 'install-plugin_elementor' );
		$message = '<h3>' . esc_html__( 'Elementor Pro plugin requires installing the Elementor plugin', 'elementor-pro' ) . '</h3>';
		$message .= '<p>' . esc_html__( 'Install and activate the Elementor plugin to access all the Pro features.', 'elementor-pro' ) . '</p>';
		$message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $install_url, esc_html__( 'Install Now', 'elementor-pro' ) ) . '</p>';
	}
	print_error( $message );
}

function elementor_pro_fail_load_out_of_date() {
	if ( ! current_user_can( 'update_plugins' ) ) return;
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
	if ( ! current_user_can( 'update_plugins' ) ) return;
	$file_path = 'elementor/elementor.php';
	$upgrade_link = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $file_path, 'upgrade-plugin_' . $file_path );
	$message = sprintf(
		'<h3>%1$s</h3><p>%2$s <a href="%3$s" class="button-primary">%4$s</a></p>',
		esc_html__( 'Don’t miss out on the new version of Elementor', 'elementor-pro' ),
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