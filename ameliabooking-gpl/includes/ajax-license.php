<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_amelia_gpl_validate_key', function(){
    check_ajax_referer('amelia_gpl_nonce','security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permisos insuficientes.']);
    }
    
    $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
    if (empty($api_key)) {
        wp_send_json_error(['message' => 'API Key vacía.']);
    }
    
    $server_url = defined('AMELIABOOKING_GPL_UPDATE_SERVER') ? AMELIABOOKING_GPL_UPDATE_SERVER : 'https://actualizarplugins.online/api/';
    $validate_endpoint = $server_url . 'validate-key.php';
    
    $response = wp_remote_post($validate_endpoint, [
        'timeout' => 30,
        'headers' => [
            'Accept' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        ],
        'body' => ['apiKey' => $api_key, 'siteUrl' => site_url()]
    ]);
    
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Error al conectar: ' . $response->get_error_message()]);
    }
    
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($code !== 200) {
        wp_send_json_error(['message' => 'Respuesta inesperada: HTTP ' . $code]);
    }
    
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
        wp_send_json_error(['message' => 'Respuesta no válida.']);
    }
    
    if (isset($data['success']) && $data['success']) {
        update_option('ameliabooking_gpl_api_key', $api_key);
        update_option('ameliabooking_gpl_key_status', 'active');
        
        if (isset($data['data']['expiry_date'])) {
            update_option('plugin_updater_expiry', $data['data']['expiry_date']);
        }
        
        // Guardar datos de activaciones
        if (isset($data['data']['activation_count'])) {
            update_option('ameliabooking_gpl_activation_count', absint($data['data']['activation_count']));
        }
        
        if (isset($data['data']['max_activations'])) {
            update_option('ameliabooking_gpl_max_activations', absint($data['data']['max_activations']));
        }
        
        if (isset($data['data']['remaining_activations'])) {
            update_option('ameliabooking_gpl_remaining_activations', intval($data['data']['remaining_activations']));
        }
        
        $success_message = isset($data['message']) ? $data['message'] : '¡API Key activada!';
        wp_send_json_success([
            'message' => $success_message,
            'api_key' => $api_key,
            'expiry_date' => $data['data']['expiry_date'] ?? null
        ]);
    } else {
        $error_message = isset($data['message']) ? $data['message'] : 'API Key inválida.';
        wp_send_json_error(['message' => $error_message]);
    }
});

add_action('wp_ajax_amelia_gpl_deactivate_key', function(){
    check_ajax_referer('amelia_gpl_nonce','security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permisos insuficientes.']);
    }
    
    delete_option('ameliabooking_gpl_api_key');
    delete_option('ameliabooking_gpl_key_status');
    delete_option('plugin_updater_expiry');
    delete_option('ameliabooking_gpl_activation_count');
    delete_option('ameliabooking_gpl_max_activations');
    delete_option('ameliabooking_gpl_remaining_activations');
    
    wp_send_json_success(['message' => 'API Key desactivada.']);
});
