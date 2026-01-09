<?php
/**
 * Plugin Name: Yoast SEO Premium GPL con API Key
 * Plugin URI:  https://yoa.st/2jc
 * Description: Yoast SEO Premium adaptado para actualizaciones automáticas vía API Key y servidor personalizado.
 * Version:     26.4
 * Author:      Team Yoast / GPL Mod
 * Text Domain: wordpress-seo-premium-gpl
 *
 * NOTA: Este fichero es la versión corregida para:
 *  - Evitar "Constant PLUGIN_UPDATER_SERVER already defined"
 *  - Evitar "Creation of dynamic property ... is deprecated" (suprime E_DEPRECATED temporalmente al cargar el autoloader)
 *  - Evitar salida inesperada durante activación (suprime avisos/errores en momentos críticos)
 *
 * Coloca este archivo en la raíz del plugin (ej. wp-content/plugins/wordpress-seo-premium-gpl/wordpress-seo-premium-gpl.php)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Definiciones de constantes básicas
 */
define( 'WPSEO_PREMIUM_FILE', __FILE__ );
define( 'WPSEO_PREMIUM_PATH', plugin_dir_path( WPSEO_PREMIUM_FILE ) );
define( 'WPSEO_PREMIUM_BASENAME', plugin_basename( WPSEO_PREMIUM_FILE ) );

// Evitar warning si ya está definida en otro sitio (wp-config.php, mu-plugin, otro wrapper...)
if ( ! defined( 'PLUGIN_UPDATER_SERVER' ) ) {
	define( 'PLUGIN_UPDATER_SERVER', 'https://actualizarplugins.online/api/' );
}

// Texto dominio (no tocar salvo que lo necesites)
if ( ! defined( 'YOAST_SEO_GPL_TEXT_DOMAIN' ) ) {
	define( 'YOAST_SEO_GPL_TEXT_DOMAIN', 'wordpress-seo-premium-gpl' );
}

// Forzar constante de versión para evitar fatal errors en migraciones
if ( ! defined( 'WPSEO_PREMIUM_VERSION' ) ) {
	define( 'WPSEO_PREMIUM_VERSION', '26.4' );
}

/**
 * Forzar Premium: si hay API Key guardada, marcar estado activo y aplicar filtros.
 * Ejecutamos sin producir salida.
 */
add_action( 'plugins_loaded', function() {
	// Evitar que avisos/problemas impriman salida en activación - no cambiamos error_reporting global permanente
	$api_key = get_option( 'yoast_seo_premium_gpl_api_key', '' );
	if ( ! empty( $api_key ) ) {
		update_option( 'yoast_seo_premium_gpl_key_status', 'active' );
		update_option( 'yoast_seo_premium_gpl_expiry', date( 'Y-m-d', strtotime( '+10 years' ) ) );
		update_option( 'wpseo_premium_active', true );
		update_option( 'wpseo_premium_subscription_active', true );
		update_option( 'wpseo_premium_advanced_analysis_enabled', true );
	}

	add_filter( 'wpseo_premium_feature_available', '__return_true' );
	add_filter( 'wpseo_license_required', '__return_false' );
	add_filter( 'wpseo_premium_keyword_limit', '__return_false' );
	add_filter( 'wpseo_ai_optimization_disabled', '__return_false' );
	add_filter( 'wpseo_content_analysis_premium_restricted', '__return_false' );
}, 1 );

/**
 * Clase WP_Updater_GPL - adapta el updater a tu servidor
 */
if ( ! class_exists( 'WP_Updater_GPL' ) ) {
	class WP_Updater_GPL {

		private $plugin_file = 'wordpress-seo-premium-gpl/wordpress-seo-premium-gpl.php';
		private $server_url  = PLUGIN_UPDATER_SERVER;

		public function __construct() {
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'add_custom_updates' ) );
			add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
			add_filter( 'upgrader_post_install', array( $this, 'after_update' ), 10, 2 );
		}

		public function add_custom_updates( $transient ) {
			if ( empty( $transient ) || ! is_object( $transient ) || empty( $transient->checked ) ) {
				return $transient;
			}

			$api_key    = get_option( 'yoast_seo_premium_gpl_api_key', '' );
			$key_status = get_option( 'yoast_seo_premium_gpl_key_status', 'inactive' );
			if ( empty( $api_key ) || $key_status !== 'active' ) {
				return $transient;
			}

			$current_version = $transient->checked[ $this->plugin_file ] ?? '0.0';

			$response = wp_remote_get( add_query_arg( array(
				'apiKey'    => $api_key,
				'installed' => 'wordpress-seo-premium-gpl',
			), $this->server_url . 'get-plugins.php' ), array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/json' ) ) );

			if ( is_wp_error( $response ) ) {
				// no imprimimos nada en pantalla, solo log
				error_log( 'WP_Updater_GPL: Error conectando a servidor de actualizaciones: ' . $response->get_error_message() );
				return $transient;
			}

			$body = wp_remote_retrieve_body( $response );
			$plugins_data = json_decode( $body, true );

			if ( ! is_array( $plugins_data ) || empty( $plugins_data ) ) {
				return $transient;
			}

			foreach ( $plugins_data as $plugin_info ) {
				if ( isset( $plugin_info['slug'] ) && $plugin_info['slug'] === 'wordpress-seo-premium-gpl' ) {
					$new_version  = $plugin_info['version'] ?? $current_version;
					$download_url = $plugin_info['download_url'] ?? '';
					if ( version_compare( $new_version, $current_version, '>' ) && ! empty( $download_url ) ) {
						$transient->response[ $this->plugin_file ] = (object) array(
							'id'          => 0,
							'slug'        => 'wordpress-seo-premium-gpl',
							'plugin'      => $this->plugin_file,
							'new_version' => $new_version,
							'url'         => home_url(),
							'package'     => $download_url,
							'tested'      => '6.7',
							'requires'    => '6.7',
						);
						// Forzar refresco inmediato
						delete_site_transient( 'update_plugins' );
					}
					break;
				}
			}

			return $transient;
		}

		public function plugin_info( $result, $action, $args ) {
			if ( $action == 'plugin_information' && ( $args->slug ?? '' ) == 'wordpress-seo-premium-gpl' ) {
				$api_key    = get_option( 'yoast_seo_premium_gpl_api_key', '' );
				$key_status = get_option( 'yoast_seo_premium_gpl_key_status', 'inactive' );
				if ( empty( $api_key ) || $key_status !== 'active' ) {
					return $result;
				}

				$response = wp_remote_get( add_query_arg( array(
					'apiKey' => $api_key,
					'slug'   => 'wordpress-seo-premium-gpl',
				), $this->server_url . 'list-plugins.php' ) );

				if ( is_wp_error( $response ) ) {
					return $result;
				}

				$data = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( ! is_array( $data ) || empty( $data['data'] ) ) {
					return $result;
				}

				foreach ( $data['data'] as $plugin ) {
					if ( isset( $plugin['slug'] ) && $plugin['slug'] === 'wordpress-seo-premium-gpl' ) {
						return (object) array(
							'name'        => $plugin['name'],
							'slug'        => $plugin['slug'],
							'version'     => $plugin['version'],
							'tested'      => $plugin['tested'] ?? '6.7',
							'requires'    => $plugin['requires'] ?? '6.7',
							'author'      => 'Yoast / GPL Mod',
							'description' => $plugin['description'] ?? '',
						);
					}
				}
			}
			return $result;
		}

		public function after_update( $response, $hook_extra ) {
			if ( isset( $hook_extra['action'] ) && $hook_extra['action'] === 'update' && isset( $hook_extra['type'] ) && $hook_extra['type'] === 'plugin' ) {
				delete_site_transient( 'update_plugins' );
				delete_transient( 'wpseo_site_information' );
			}
			return $response;
		}
	}
}

new WP_Updater_GPL();

/**
 * Inicializar autoloader / instalador / container del premium
 * Suprimimos temporalmente E_DEPRECATED/E_USER_DEPRECATED solo alrededor de las inclusiones
 * para evitar que PHP 8.2+ muestre la deprecated dynamic property en pantalla (esto no cambia reporting global).
 */
$wpseo_premium_dir               = WPSEO_PREMIUM_PATH;
$yoast_seo_premium_autoload_file = $wpseo_premium_dir . 'vendor/autoload.php';

if ( is_readable( $yoast_seo_premium_autoload_file ) ) {
	$old_reporting = error_reporting();
	// Suprimir solo E_DEPRECATED y E_USER_DEPRECATED temporalmente
	error_reporting( $old_reporting & ~E_DEPRECATED & ~E_USER_DEPRECATED );

	require $yoast_seo_premium_autoload_file;

	// Restaurar reporting
	error_reporting( $old_reporting );
}

// Instalar desde repo si la clase existe (envolvemos para suprimir deprecated temporalmente)
if ( class_exists( 'Yoast\WP\SEO\Premium\Addon_Installer' ) ) {
	$old_reporting = error_reporting();
	error_reporting( $old_reporting & ~E_DEPRECATED & ~E_USER_DEPRECATED );

	$installer = new \Yoast\WP\SEO\Premium\Addon_Installer( __DIR__ );
	$installer->install_yoast_seo_from_repository();

	error_reporting( $old_reporting );
}

// Cargar contenedor / bootstrap (envolver la carga para suprimir deprecated temporalmente)
if ( ! wp_installing() ) {
	$functions_file = WPSEO_PREMIUM_PATH . 'src/functions.php';
	if ( file_exists( $functions_file ) ) {
		$old_reporting = error_reporting();
		error_reporting( $old_reporting & ~E_DEPRECATED & ~E_USER_DEPRECATED );

		require_once $functions_file;
		if ( function_exists( 'YoastSEOPremium' ) ) {
			YoastSEOPremium();
		}

		error_reporting( $old_reporting );
	}
}

// Registrar activación usando la clase global (evita namespace faltante)
if ( function_exists( 'register_activation_hook' ) ) {
	register_activation_hook( WPSEO_PREMIUM_FILE, array( 'WPSEO_Premium', 'install' ) );
}

/**
 * Ocultar elementos de upsell / premium en el admin y frontend
 * NO ejecuta nada en la activación; solo imprime estilo cuando se carga admin/front.
 */
if ( ! function_exists( 'hide_yoast_premium_buttons' ) ) {
	function hide_yoast_premium_buttons() {
		?>
		<style>
			#wp-admin-bar-wpseo-get-premium,
			.yst-button--upsell,
			#wpseo-new-badge-upgrade,
			.wpseo-premium-promotion,
			.wpseo-get-premium-banner,
			.wpseo-upsell-notice,
			.wpseo-premium-notice,
			.wpseo-license-expiry-notice,
			.wpseo-keyword-restriction,
			.wpseo-premium-feature-locked,
			.wpseo-upgrade-notice {
				display: none !important;
			}
		</style>
		<?php
	}
}
add_action( 'admin_head', 'hide_yoast_premium_buttons' );
add_action( 'wp_head', 'hide_yoast_premium_buttons' );

/**
 * Incluir archivos de includes/ (admin-license, ajax-license, etc.) si existen
 * Evitamos emitir salida al incluir (estos ficheros deben estar bien escritos).
 */
$includes = WPSEO_PREMIUM_PATH . 'includes/';
if ( is_dir( $includes ) ) {
	if ( file_exists( $includes . 'admin-license.php' ) ) {
		require_once $includes . 'admin-license.php';
	}
	if ( file_exists( $includes . 'ajax-license.php' ) ) {
		require_once $includes . 'ajax-license.php';
	}
	if ( file_exists( $includes . 'activate-keywords-features.php' ) ) {
		require_once $includes . 'activate-keywords-features.php';
	}
	if ( file_exists( $includes . 'class-update-manager.php' ) ) {
		require_once $includes . 'class-update-manager.php';
	}
}

/* End of file */