<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gestor de actualizaciones para WP Rocket GPL — versión final.
 *
 * - Determina dinámicamente plugin_basename() para encontrar la clave correcta en transient->checked.
 * - Calcula plugin slug de forma segura (dirname o basename sin .php).
 * - Envía ambos parámetros al servidor ('installed' y 'plugin') para máxima compatibilidad.
 * - Normaliza respuestas (objeto único, array o array con items).
 * - Añade logging condicional (WP_DEBUG && WP_DEBUG_LOG) similar a Yoast/Elementor.
 */

if ( ! defined( 'PLUGIN_UPDATER_SERVER' ) ) {
	define( 'PLUGIN_UPDATER_SERVER', 'https://actualizarplugins.online/api/' );
}

class WP_Rocket_GPL_Update_Manager {

	/** @var string plugin basename (ej. wp-rocket/wp-rocket.php) */
	private $plugin_file;

	/** @var string plugin slug (ej. wp-rocket) */
	private $plugin_slug;

	/** @var string human name */
	private $plugin_name = 'WP Rocket GPL';

	/** @var string server base url */
	private $server_url;

	public function __construct() {
		// Derivar plugin_file del WP_ROCKET_FILE si está disponible
		if ( defined( 'WP_ROCKET_FILE' ) && file_exists( WP_ROCKET_FILE ) ) {
			$this->plugin_file = plugin_basename( WP_ROCKET_FILE );
		} else {
			// Fallback clásico: intentar detectar un basename en el directorio del plugin
			$this->plugin_file = 'wp-rocket-gpl/wp-rocket-gpl.php';
		}

		// Derivar slug: dirname(plugin_basename) o nombre de archivo sin .php si dirname es "."
		$dir = dirname( $this->plugin_file );
		if ( $dir && '.' !== $dir ) {
			$this->plugin_slug = $dir;
		} else {
			$this->plugin_slug = preg_replace( '#\.php$#', '', basename( $this->plugin_file ) );
		}

		$this->server_url = defined( 'PLUGIN_UPDATER_SERVER' ) ? rtrim( PLUGIN_UPDATER_SERVER, '/' ) : 'https://actualizarplugins.online/api';

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_plugin_update' ], 10, 1 );
		add_filter( 'plugins_api', [ $this, 'plugins_api_call' ], 10, 3 );
	}

	/**
	 * Intentar obtener la versión instalada desde transient->checked con tolerancia.
	 *
	 * @param object $transient
	 * @return string|false
	 */
	private function get_current_version_from_transient( $transient ) {
		if ( empty( $transient ) || empty( $transient->checked ) ) {
			return false;
		}

		// 1) Match exacto por plugin_file (plugin_basename)
		if ( isset( $transient->checked[ $this->plugin_file ] ) ) {
			return $transient->checked[ $this->plugin_file ];
		}

		// 2) Buscar por basename (archivo php), ejemplo wp-rocket.php
		$basename = basename( $this->plugin_file );
		foreach ( (array) $transient->checked as $key => $ver ) {
			if ( strpos( $key, $basename ) !== false ) {
				return $ver;
			}
		}

		// 3) Buscar por slug (dirname) dentro de la key
		foreach ( (array) $transient->checked as $key => $ver ) {
			if ( strpos( $key, $this->plugin_slug ) !== false ) {
				return $ver;
			}
		}

		return false;
	}

	/**
	 * Hook principal: revisar actualizaciones.
	 *
	 * @param object $transient
	 * @return object
	 */
	public function check_for_plugin_update( $transient ) {
		if ( empty( $transient ) ) {
			$transient = new stdClass();
			$transient->checked  = [];
			$transient->response = [];
		}

		$current_version = $this->get_current_version_from_transient( $transient );
		$api_key         = get_option( 'wp_rocket_gpl_api_key', '' );
		$status          = get_option( 'wp_rocket_gpl_key_status', 'inactive' );

		if ( function_exists( 'wp_rocket_gpl_log_debug' ) ) {
			wp_rocket_gpl_log_debug( sprintf( 'check_for_plugin_update: current=%s, api_key=%s, status=%s', $current_version ?: 'none', $api_key ? 'set' : 'empty', $status ) );
		}

		if ( empty( $api_key ) || 'active' !== $status || ! $current_version ) {
			return $transient;
		}

		// Construir URL robusta, enviando installed (slug) y plugin (plugin_basename)
		$query = [
			'apiKey'    => $api_key,
			'installed' => $this->plugin_slug,
			'plugin'    => $this->plugin_file,
		];

		$request_url = add_query_arg( $query, $this->server_url . '/get-plugins.php' );

		if ( function_exists( 'wp_rocket_gpl_log_debug' ) ) {
			wp_rocket_gpl_log_debug( 'Update check request URL: ' . $request_url );
		}

		$response = wp_remote_get( $request_url, [
			'timeout' => 20,
			'headers' => [ 'Accept' => 'application/json' ],
		] );

		if ( is_wp_error( $response ) ) {
			if ( function_exists( 'wp_rocket_gpl_log_debug' ) ) {
				wp_rocket_gpl_log_debug( 'Update check wp_remote_get error: ' . $response->get_error_message() );
			}
			return $transient;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( function_exists( 'wp_rocket_gpl_log_debug' ) ) {
			wp_rocket_gpl_log_debug( sprintf( 'Update check response code: %s', $code ) );
			wp_rocket_gpl_log_debug( 'Update check response body (truncated): ' . substr( $body, 0, 2000 ) );
		}

		if ( 200 !== (int) $code || empty( $body ) ) {
			return $transient;
		}

		// Normalizar respuesta (aceptar objeto único o array)
		$plugins_data = json_decode( $body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$decoded_obj = json_decode( $body );
			if ( is_object( $decoded_obj ) || is_array( $decoded_obj ) ) {
				$plugins_data = json_decode( wp_json_encode( $decoded_obj ), true );
			}
		}

		// Si es una entrada única con keys de plugin, conviértela a array de items
		if ( is_array( $plugins_data ) && isset( $plugins_data['slug'] ) ) {
			$plugins_data = [ $plugins_data ];
		}

		if ( ! is_array( $plugins_data ) || empty( $plugins_data ) ) {
			return $transient;
		}

		foreach ( $plugins_data as $plugin_info ) {
			// normalizar entrada a array
			if ( is_object( $plugin_info ) ) {
				$plugin_info = json_decode( wp_json_encode( $plugin_info ), true );
			}
			if ( ! is_array( $plugin_info ) ) {
				continue;
			}

			$remote_slug   = $plugin_info['slug'] ?? '';
			$remote_plugin = $plugin_info['plugin'] ?? $plugin_info['installed'] ?? '';

			$match = false;
			if ( $remote_slug && $remote_slug === $this->plugin_slug ) {
				$match = true;
			}
			if ( ! $match && $remote_plugin ) {
				// comparar por basename o por coincidencia parcial
				if ( strpos( $remote_plugin, basename( $this->plugin_file ) ) !== false || strpos( $this->plugin_file, $remote_plugin ) !== false ) {
					$match = true;
				}
			}

			if ( ! $match ) {
				continue;
			}

			$new_version  = $plugin_info['version'] ?? $current_version;
			$download_url = $plugin_info['download_url'] ?? $plugin_info['package'] ?? '';

			if ( version_compare( $new_version, $current_version, '>' ) && ! empty( $download_url ) ) {
				$transient->response[ $this->plugin_file ] = (object) [
					'slug'        => $this->plugin_slug,
					'plugin'      => $this->plugin_file,
					'new_version' => $new_version,
					'url'         => $plugin_info['details_url'] ?? home_url(),
					'package'     => $download_url,
					'tested'      => $plugin_info['tested'] ?? '6.3.1',
					'requires'    => $plugin_info['requires'] ?? '5.8',
				];

				if ( function_exists( 'wp_rocket_gpl_log_debug' ) ) {
					wp_rocket_gpl_log_debug( sprintf( 'Update available for %s -> %s (package: %s)', $this->plugin_file, $new_version, $download_url ) );
				}
			}
		}

		return $transient;
	}

	/**
	 * Plugins API callback to provide plugin information in modal.
	 *
	 * @param mixed  $result
	 * @param string $action
	 * @param object $args
	 * @return mixed
	 */
	public function plugins_api_call( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		// obtener slug/plugin del args (aceptar object o array)
		$args_slug   = is_object( $args ) ? ( $args->slug ?? '' ) : ( is_array( $args ) ? ( $args['slug'] ?? '' ) : '' );
		$args_plugin = is_object( $args ) ? ( $args->plugin ?? '' ) : ( is_array( $args ) ? ( $args['plugin'] ?? '' ) : '' );

		$match = false;
		if ( $args_slug && $args_slug === $this->plugin_slug ) {
			$match = true;
		}
		if ( ! $match && $args_plugin && strpos( $args_plugin, basename( $this->plugin_file ) ) !== false ) {
			$match = true;
		}

		if ( ! $match ) {
			return $result;
		}

		$api_key = get_option( 'wp_rocket_gpl_api_key', '' );
		$status  = get_option( 'wp_rocket_gpl_key_status', 'inactive' );

		if ( empty( $api_key ) || 'active' !== $status ) {
			return $result;
		}

		return (object) [
			'name'        => $this->plugin_name,
			'slug'        => $this->plugin_slug,
			'version'     => WP_ROCKET_VERSION,
			'tested'      => '6.3.1',
			'requires'    => '5.8',
			'description' => 'WP Rocket - GPL Version with Auto Updates',
		];
	}
}

new WP_Rocket_GPL_Update_Manager();