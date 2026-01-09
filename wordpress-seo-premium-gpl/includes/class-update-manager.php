<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ⭐ ADAPTADO: Gestor de Actualizaciones integrado con servidor externo
 * VERSIÓN CORREGIDA: Usa hooks correctos para que WordPress detecte actualizaciones
 */
class Yoast_SEO_Premium_GPL_Update_Manager {

    private $plugin_file = 'wordpress-seo-premium-gpl/wordpress-seo-premium-gpl.php';
    private $server_url = PLUGIN_UPDATER_SERVER;
    private $plugin_slug = 'wordpress-seo-premium-gpl';

    public function __construct() {
        // ⭐ CRÍTICO: Usar el hook correcto
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_plugin_update'], 10, 1);
        add_filter('plugins_api', [$this, 'plugins_api_call'], 10, 3);
    }

    /**
     * ⭐ CORREGIDO: Hook correcto para que WordPress detecte las actualizaciones
     * Este hook se dispara ANTES de guardar el transient en caché
     */
    public function check_for_plugin_update( $transient ) {
        // Inicializar si es null
        if ( empty( $transient ) ) {
            $transient = new stdClass();
            $transient->checked = [];
            $transient->response = [];
            $transient->no_update = [];
        }

        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        // Obtener la versión actual instalada
        $current_version = $transient->checked[$this->plugin_file] ?? false;
        $api_key = get_option('yoast_seo_premium_gpl_api_key', '');
        $status = get_option('yoast_seo_premium_gpl_key_status', 'inactive');

        // Si no hay API key activa, no hacer nada
        if ( empty( $api_key ) || $status !== 'active' || !$current_version ) {
            error_log("Yoast SEO GPL: No verificando - API Key activa: " . ($status === 'active' ? 'SÍ' : 'NO'));
            return $transient;
        }

        error_log("Yoast SEO GPL: Verificando actualización. Versión actual: $current_version");

        // ⭐ ADAPTADO: Llamar a get-plugins.php del servidor externo
        $response = wp_remote_get( add_query_arg([
            'apiKey' => $api_key,
            'installed' => $this->plugin_slug
        ], $this->server_url . 'get-plugins.php'), [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json']
        ]);

        if ( is_wp_error( $response ) ) {
            error_log('Yoast SEO GPL: Error conectando a servidor: ' . $response->get_error_message());
            return $transient;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log("Yoast SEO GPL: Servidor retornó HTTP $code");
            return $transient;
        }

        $body = wp_remote_retrieve_body( $response );
        $plugins_data = json_decode( $body, true );

        if ( ! is_array($plugins_data) || empty($plugins_data) ) {
            error_log("Yoast SEO GPL: Respuesta vacía o inválida del servidor");
            return $transient;
        }

        error_log("Yoast SEO GPL: Recibidos " . count($plugins_data) . " plugins del servidor");

        // ⭐ ADAPTADO: Buscar el plugin en la respuesta
        foreach ( $plugins_data as $plugin_info ) {
            if ( isset($plugin_info['slug']) && $plugin_info['slug'] === $this->plugin_slug ) {
                $new_version = $plugin_info['version'] ?? $current_version;
                $download_url = $plugin_info['download_url'] ?? '';

                error_log("Yoast SEO GPL: Plugin encontrado. Versión remota: $new_version, URL: " . substr($download_url, 0, 50) . "...");

                // Si hay versión superior y URL válida
                if ( version_compare( $new_version, $current_version, '>' ) && !empty($download_url) ) {
                    error_log("Yoast SEO GPL: ✅ ACTUALIZACIÓN DISPONIBLE: $new_version > $current_version");

                    // ⭐ IMPORTANTE: Crear objeto de actualización correcto
                    $transient->response[$this->plugin_file] = (object) [
                        'id' => 0,
                        'slug' => $this->plugin_slug,
                        'plugin' => $this->plugin_file,
                        'new_version' => $new_version,
                        'url' => home_url(),
                        'package' => $download_url, // ⭐ URL directa - NO ZIP
                        'tested' => '6.7',
                        'requires' => '6.7',
                        'requires_php' => '7.4',
                        'icons' => [
                            '1x' => '',
                            '2x' => ''
                        ]
                    ];
                } else {
                    error_log("Yoast SEO GPL: Estás en la versión más reciente");
                    // Agregar a no_update si está al día
                    if (!isset($transient->no_update)) {
                        $transient->no_update = [];
                    }
                    $transient->no_update[$this->plugin_file] = $transient->checked[$this->plugin_file];
                }
                break;
            }
        }

        return $transient;
    }

    /**
     * ⭐ ADAPTADO: Obtiene información del plugin para la modal de detalles
     */
    public function plugins_api_call( $result, $action, $args ) {
        if ( $action !== 'plugin_information' || $args->slug !== $this->plugin_slug ) {
            return $result;
        }

        $api_key = get_option('yoast_seo_premium_gpl_api_key', '');
        $status = get_option('yoast_seo_premium_gpl_key_status', 'inactive');
        
        if (empty($api_key) || $status !== 'active') {
            return $result;
        }

        // ⭐ ADAPTADO: Obtener info de list-plugins.php
        $response = wp_remote_get( add_query_arg([
            'apiKey' => $api_key
        ], $this->server_url . 'list-plugins.php'), [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json']
        ]);
        
        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            
            if ( isset( $data['success'] ) && $data['success'] && isset($data['data']) && is_array($data['data']) ) {
                // Buscar el plugin en la data
                foreach ($data['data'] as $plugin) {
                    if ($plugin['slug'] === $this->plugin_slug) {
                        return (object) [
                            'name' => $plugin['name'] ?? 'Yoast SEO Premium GPL',
                            'slug' => $this->plugin_slug,
                            'version' => $plugin['version'] ?? '26.3',
                            'tested' => '6.7',
                            'requires' => '6.7',
                            'requires_php' => '7.4',
                            'author' => 'Yoast / GPL Mod',
                            'description' => $plugin['name'] ?? 'Solución SEO Premium de Yoast con actualizaciones automáticas.',
                            'sections' => [
                                'description' => 'Plugin SEO Premium con API Key activada.'
                            ]
                        ];
                    }
                }
            }
        }
        
        return $result;
    }
}

// ⭐ Inicializar el gestor de actualizaciones
new Yoast_SEO_Premium_GPL_Update_Manager();
?>