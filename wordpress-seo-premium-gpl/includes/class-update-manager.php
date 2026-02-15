<?php
if (!defined('ABSPATH')) exit;

class Yoast_SEO_GPL_Update_Manager {

    const PLUGIN_SLUG = 'wordpress-seo-premium-gpl';
    const PLUGIN_FILE = 'wordpress-seo-premium-gpl/wordpress-seo-premium-gpl.php';
    
    private $last_check_time = 0;
    private $check_interval = 3600; // 1 hora en segundos
    
    public function __construct() {
        error_log('✅ [Yoast_SEO_GPL_Update_Manager] inicializado');
        add_filter('site_transient_update_plugins', [$this, 'check_for_plugin_update']);
        add_filter('plugins_api', [$this, 'plugins_api_call'], 10, 3);
        add_filter('upgrader_package_options', [$this, 'add_api_key_to_download_url']);
    }

    public function check_for_plugin_update($transient) {
        
        // Evitar comprobaciones múltiples en la misma carga
        $current_time = time();
        if (($current_time - $this->last_check_time) < 5) {
            // Si han pasado menos de 5 segundos desde la última comprobación, saltar
            return $transient;
        }
        
        error_log('=== [YOAST SEO] GPL - CHECK FOR UPDATE ===');

        if (empty($transient->checked)) {
            error_log('❌ Transient->checked está vacío');
            return $transient;
        }
        
        $plugin_file = self::PLUGIN_FILE;
        $current_version = $transient->checked[$plugin_file] ?? false;

        error_log('Plugin file: ' . $plugin_file);
        error_log('Versión actual: ' . ($current_version ?: 'NO ENCONTRADA'));

        $api_key = get_option('yoast_seo_gpl_api_key', '');
        $status = get_option('yoast_seo_gpl_key_status', 'inactive');
        $expiry_date = get_option('plugin_updater_expiry', '');

        error_log('API Key: ' . ($api_key ? substr($api_key, 0, 10) . '...' : 'VACÍA'));
        error_log('Status: ' . $status);
        error_log('Expiry: ' . $expiry_date);
        
        if ($status === 'active' && !empty($expiry_date)) {
            $expiry_timestamp = strtotime($expiry_date);
            $current_timestamp = current_time('timestamp');
            
            if ($current_timestamp >= $expiry_timestamp) {
                $status = 'expired';
                update_option('yoast_seo_gpl_key_status', $status);
            }
        }

        if (!$current_version || empty($api_key) || $status !== 'active') {
            error_log('=== FIN CHECK FOR UPDATE ===');
            return $transient;
        }
        
        // Comprobar cache de última comprobación
        $cache_key = 'yoast_seo_gpl_last_check_' . md5($api_key);
        $last_check = get_transient($cache_key);
        
        if ($last_check !== false && ($current_time - $last_check) < $this->check_interval) {
            $time_remaining = $this->check_interval - ($current_time - $last_check);
            error_log('ℹ️ Comprobación en cache. Próxima en ' . round($time_remaining / 60) . ' minutos');
            error_log('=== FIN CHECK FOR UPDATE ===');
            return $transient;
        }
        
        // Actualizar tiempo de última comprobación
        $this->last_check_time = $current_time;
        set_transient($cache_key, $current_time, $this->check_interval);

        $server_url = defined('YOAST_SEO_GPL_UPDATE_SERVER') ? YOAST_SEO_GPL_UPDATE_SERVER : 'https://actualizarplugins.online/api/';
        
        // ✅ PARÁMETROS CORRECTOS PARA EL SERVIDOR
        $args = [
            'apiKey' => $api_key,
            'installed' => self::PLUGIN_SLUG
        ];

        $headers = [
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        ];

        error_log('🌐 URL: ' . add_query_arg($args, $server_url . 'get-plugins.php'));
        
        $response = wp_remote_get(add_query_arg($args, $server_url . 'get-plugins.php'), ['timeout' => 15, 'headers' => $headers]);

        $http_code = wp_remote_retrieve_response_code($response);
        error_log('📥 HTTP: ' . $http_code);

        if (is_wp_error($response)) {
            error_log('❌ Error: ' . $response->get_error_message());
            error_log('=== FIN CHECK FOR UPDATE ===');
            return $transient;
        }

        if ($http_code !== 200) {
            error_log('❌ HTTP != 200: ' . $http_code);
            error_log('=== FIN CHECK FOR UPDATE ===');
            return $transient;
        }

        $body = wp_remote_retrieve_body($response);
        error_log('📄 Body: ' . substr($body, 0, 300) . '...');
        $data = json_decode($body, true);

        if (!is_array($data)) {
            error_log('❌ Respuesta no es array');
            error_log('=== FIN CHECK FOR UPDATE ===');
            return $transient;
        }

        error_log('📦 Plugins en respuesta: ' . count($data));

        // Buscar el plugin en el array de respuesta
        foreach ($data as $plugin) {
            if (isset($plugin['slug']) && $plugin['slug'] === self::PLUGIN_SLUG) {
                $new_version = $plugin['version'] ?? $plugin['new_version'] ?? '';
                
                error_log('🔍 Plugin encontrado en respuesta');
                error_log('🔄 Comparando: ' . $current_version . ' vs ' . $new_version);
                
                if (!empty($new_version) && version_compare($current_version, $new_version, '<')) {
                    error_log('🎉 ¡ACTUALIZACIÓN DISPONIBLE! ' . $new_version);
                    
                    $transient->response[$plugin_file] = (object) [
                        'slug'        => self::PLUGIN_SLUG,
                        'plugin'      => $plugin_file,
                        'new_version' => $new_version,
                        'url'         => $plugin['details_url'] ?? $server_url . 'plugin-info.php?plugin=' . self::PLUGIN_SLUG,
                        'package'     => $plugin['download_url'] ?? add_query_arg([
                            'apiKey' => $api_key,
                            'slug'   => self::PLUGIN_SLUG
                        ], $server_url . 'download-plugin.php'),
                        'tested'      => $plugin['tested_up_to'] ?? '6.6',
                        'requires'    => $plugin['requires'] ?? '6.0',
                    ];
                    
                    error_log('✅ Actualización agregada al transient');
                } else {
                    error_log('ℹ️ Versión actual es igual o superior');
                }
                
                break;
            }
        }

        error_log('=== FIN CHECK FOR UPDATE ===');
        return $transient;
    }

    public function plugins_api_call($result, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== self::PLUGIN_SLUG) {
            return $result;
        }
        
        $api_key = get_option('yoast_seo_gpl_api_key', '');
        $status = get_option('yoast_seo_gpl_key_status', 'inactive');
        $expiry_date = get_option('plugin_updater_expiry', '');
        
        if ($status === 'active' && !empty($expiry_date)) {
            $expiry_timestamp = strtotime($expiry_date);
            $current_timestamp = current_time('timestamp');
            
            if ($current_timestamp >= $expiry_timestamp) {
                $status = 'expired';
                update_option('yoast_seo_gpl_key_status', $status);
            }
        }
        
        if (empty($api_key) || $status !== 'active') {
            return $result;
        }

        $server_url = defined('YOAST_SEO_GPL_UPDATE_SERVER') ? YOAST_SEO_GPL_UPDATE_SERVER : 'https://actualizarplugins.online/api/';
        
        $url = add_query_arg([
            'action' => 'plugin_information',
            'plugin_slug' => self::PLUGIN_SLUG,
            'api_key' => $api_key
        ], $server_url . 'plugin-info.php');

        $headers = [
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        ];
        
        $response = wp_remote_get($url, ['timeout' => 10, 'headers' => $headers]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($data['success']) && $data['success'] && isset($data['data'])) {
                return (object) $data['data'];
            }
        }
        
        return $result;
    }
    
    public function add_api_key_to_download_url($options) {
        if (isset($options['package']) && strpos($options['package'], 'download-plugin.php') !== false && strpos($options['package'], 'slug=' . self::PLUGIN_SLUG) !== false) {
            $api_key = get_option('yoast_seo_gpl_api_key', '');
            if (!empty($api_key)) {
                $options['package'] = add_query_arg('apiKey', $api_key, $options['package']);
            }
        }
        return $options;
    }
}

// Inicializar el gestor de actualizaciones
new Yoast_SEO_GPL_Update_Manager();
