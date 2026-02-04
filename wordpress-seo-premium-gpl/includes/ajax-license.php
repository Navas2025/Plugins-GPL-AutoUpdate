<?php
if (!defined('ABSPATH')) exit;

// AJAX: Validar API Key
add_action('wp_ajax_yoast_seo_gpl_validate_key', function(){
    check_ajax_referer('yoast_seo_gpl_nonce','security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permisos insuficientes.']);
    }
    
    $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
    if (empty($api_key)) {
        wp_send_json_error(['message' => 'API Key vacía.']);
    }
    
    $server_url = defined('YOAST_SEO_GPL_UPDATE_SERVER') ? YOAST_SEO_GPL_UPDATE_SERVER : 'https://actualizarplugins.online/api/';
    $validate_endpoint = $server_url . 'validate-key.php';
    
    $response = wp_remote_post($validate_endpoint, [
        'timeout' => 30,
        'headers' => [
            'Accept' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        ],
        'body' => [
            'api_key' => $api_key,
            'domain' => parse_url(home_url(), PHP_URL_HOST),
            'plugin' => 'wordpress-seo-premium-gpl',
            'version' => defined('WPSEO_PREMIUM_VERSION') ? WPSEO_PREMIUM_VERSION : '26.9'
        ]
    ]);
    
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Error: ' . $response->get_error_message()]);
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['success']) && $data['success']) {
        update_option('yoast_seo_gpl_api_key', $api_key);
        update_option('yoast_seo_gpl_key_status', 'active');
        
        if (isset($data['data']['expiry_date'])) {
            update_option('yoast_seo_gpl_expiry', $data['data']['expiry_date']);
        }
        
        // Guardar datos de activaciones
        if (isset($data['data']['activation_count'])) {
            update_option('yoast_seo_gpl_activation_count', $data['data']['activation_count']);
        }
        
        if (isset($data['data']['max_activations'])) {
            update_option('yoast_seo_gpl_max_activations', $data['data']['max_activations']);
        }
        
        if (isset($data['data']['remaining_activations'])) {
            update_option('yoast_seo_gpl_remaining_activations', $data['data']['remaining_activations']);
        }
        
        $success_message = isset($data['message']) ? $data['message'] : '¡API Key activada!';
        wp_send_json_success([
            'message' => $success_message,
            'api_key' => $api_key,
            'expiry_date' => $data['data']['expiry_date'] ?? null
        ]);
    } else {
        $error_message = isset($data['message']) ? $data['message'] : 'Error al validar la API Key.';
        wp_send_json_error(['message' => $error_message]);
    }
});

// AJAX: Desactivar API Key
add_action('wp_ajax_yoast_seo_gpl_deactivate_key', function(){
    check_ajax_referer('yoast_seo_gpl_nonce','security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permisos insuficientes.']);
    }
    
    delete_option('yoast_seo_gpl_api_key');
    delete_option('yoast_seo_gpl_key_status');
    delete_option('yoast_seo_gpl_expiry');
    delete_option('yoast_seo_gpl_activation_count');
    delete_option('yoast_seo_gpl_max_activations');
    delete_option('yoast_seo_gpl_remaining_activations');
    
    wp_send_json_success(['message' => 'API Key desactivada.']);
});
