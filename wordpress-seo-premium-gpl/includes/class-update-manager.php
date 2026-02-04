<?php
if (!defined('ABSPATH')) exit;

class Yoast_SEO_GPL_Update_Manager {
    
    private $api_url;
    private $plugin_slug;
    private $plugin_file;
    
    public function __construct() {
        $this->api_url = defined('YOAST_SEO_GPL_UPDATE_SERVER') ? YOAST_SEO_GPL_UPDATE_SERVER : 'https://actualizarplugins.online/api/';
        $this->plugin_slug = 'wordpress-seo-premium-gpl';
        $this->plugin_file = 'wordpress-seo-premium-gpl/wordpress-seo-premium-gpl.php';
        
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
    }
    
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $api_key = get_option('yoast_seo_gpl_api_key', '');
        if (empty($api_key)) {
            return $transient;
        }
        
        $current_version = defined('WPSEO_PREMIUM_VERSION') ? WPSEO_PREMIUM_VERSION : '26.9';
        
        $response = wp_remote_post($this->api_url . 'check-update.php', [
            'timeout' => 15,
            'body' => [
                'api_key' => $api_key,
                'plugin' => $this->plugin_slug,
                'version' => $current_version,
                'domain' => parse_url(home_url(), PHP_URL_HOST)
            ]
        ]);
        
        if (is_wp_error($response)) {
            return $transient;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['new_version']) && version_compare($current_version, $data['new_version'], '<')) {
            $plugin_data = (object) [
                'slug' => $this->plugin_slug,
                'new_version' => $data['new_version'],
                'package' => $data['download_url'] ?? '',
                'url' => $data['url'] ?? '',
                'tested' => $data['tested'] ?? ''
            ];
            
            $transient->response[$this->plugin_file] = $plugin_data;
        }
        
        return $transient;
    }
    
    public function plugin_info($false, $action, $args) {
        if ($action !== 'plugin_information') {
            return $false;
        }
        
        if (!isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $false;
        }
        
        $api_key = get_option('yoast_seo_gpl_api_key', '');
        if (empty($api_key)) {
            return $false;
        }
        
        $response = wp_remote_post($this->api_url . 'plugin-info.php', [
            'timeout' => 15,
            'body' => [
                'api_key' => $api_key,
                'plugin' => $this->plugin_slug
            ]
        ]);
        
        if (is_wp_error($response)) {
            return $false;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['name'])) {
            return (object) $data;
        }
        
        return $false;
    }
}

// Inicializar el gestor de actualizaciones
new Yoast_SEO_GPL_Update_Manager();
