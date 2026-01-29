<?php
if (!defined('ABSPATH')) exit;

class Amelia_GPL_Update_Manager {

    const PLUGIN_SLUG = 'ameliabooking-gpl';
    const PLUGIN_FILE = 'ameliabooking-gpl/ameliabooking-gpl.php';
    
    public function __construct() {
        add_filter('site_transient_update_plugins', [$this, 'check_for_plugin_update']);
        add_filter('plugins_api', [$this, 'plugins_api_call'], 10, 3);
        add_filter('upgrader_package_options', [$this, 'add_api_key_to_download_url']);
    }

    public function check_for_plugin_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $plugin_file = self::PLUGIN_FILE;
        $current_version = $transient->checked[$plugin_file] ?? false;
        $api_key = get_option('amelia_gpl_api_key', '');
        $status = get_option('amelia_gpl_key_status', 'inactive');
        $expiry_date = get_option('amelia_gpl_expiry', '');
        
        if ($status === 'active' && !empty($expiry_date)) {
            $expiry_timestamp = strtotime($expiry_date);
            $current_timestamp = current_time('timestamp');
            
            if ($current_timestamp >= $expiry_timestamp) {
                $status = 'expired';
                update_option('amelia_gpl_key_status', $status);
            }
        }

        if (!$current_version || empty($api_key) || $status !== 'active') {
            return $transient;
        }

        $server_url = defined('AMELIA_GPL_UPDATE_SERVER') ? AMELIA_GPL_UPDATE_SERVER : 'https://actualizarplugins.online/api/';
        
        $args = [
            'action' => 'get_latest_version',
            'plugin_slug' => self::PLUGIN_SLUG,
            'api_key' => $api_key,
            'site_url' => home_url(),
            'current_version' => $current_version,
        ];

        $headers = [
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        ];
        
        $response = wp_remote_get(add_query_arg($args, $server_url . 'get-plugins.php'), ['timeout' => 10, 'headers' => $headers]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return $transient;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            return $transient;
        }

        if (isset($data['new_version']) && version_compare($current_version, $data['new_version'], '<')) {
            
            $transient->response[$plugin_file] = (object) [
                'slug'        => self::PLUGIN_SLUG,
                'plugin'      => $plugin_file,
                'new_version' => $data['new_version'],
                'url'         => $data['details_url'] ?? $server_url . 'plugin-info.php?plugin=' . self::PLUGIN_SLUG,
                'package'     => add_query_arg([
                    'apiKey' => $api_key,
                    'slug'   => self::PLUGIN_SLUG
                ], $server_url . 'download-plugin.php'),
                'tested'      => $data['tested_up_to'] ?? '6.6',
                'requires'    => $data['requires'] ?? '6.0',
            ];
        }

        return $transient;
    }

    public function plugins_api_call($result, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== self::PLUGIN_SLUG) {
            return $result;
        }
        
        $api_key = get_option('amelia_gpl_api_key', '');
        $status = get_option('amelia_gpl_key_status', 'inactive');
        $expiry_date = get_option('amelia_gpl_expiry', '');
        
        if ($status === 'active' && !empty($expiry_date)) {
            $expiry_timestamp = strtotime($expiry_date);
            $current_timestamp = current_time('timestamp');
            
            if ($current_timestamp >= $expiry_timestamp) {
                $status = 'expired';
                update_option('amelia_gpl_key_status', $status);
            }
        }
        
        if (empty($api_key) || $status !== 'active') {
            return $result;
        }

        $server_url = defined('AMELIA_GPL_UPDATE_SERVER') ? AMELIA_GPL_UPDATE_SERVER : 'https://actualizarplugins.online/api/';
        
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
            $api_key = get_option('amelia_gpl_api_key', '');
            if (!empty($api_key)) {
                $options['package'] = add_query_arg('apiKey', $api_key, $options['package']);
            }
        }
        return $options;
    }
}

new Amelia_GPL_Update_Manager();
