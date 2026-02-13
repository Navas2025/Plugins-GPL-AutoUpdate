<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_rank_math_pro_gpl_validate_key', function(){
    check_ajax_referer('rank_math_pro_gpl_nonce','security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permisos insuficientes.']);
    }
    
    $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
    if (empty($api_key)) {
        wp_send_json_error(['message' => 'API Key vacía.']);
    }
    
    $server_url = defined('RANK_MATH_PRO_GPL_UPDATE_SERVER') ? RANK_MATH_PRO_GPL_UPDATE_SERVER : 'https://actualizarplugins.online/api/';
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
    
    // Log de debugging
    error_log('Rank Math Pro v3.0.107 - Validate Response: HTTP ' . $code . ' - ' . $body);
    
    if ($code != 200) {
        wp_send_json_error(['message' => 'Respuesta inesperada: HTTP ' . $code]);
    }
    
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
        wp_send_json_error(['message' => 'Respuesta no válida.']);
    }
    
    if (isset($data['success']) && $data['success']) {
        update_option('plugin_updater_api_key', $api_key);
        
        if (isset($data['data']['expiry_date'])) {
            update_option('plugin_updater_expiry', $data['data']['expiry_date']);
        }
        
        // Guardar datos de activaciones
        if (isset($data['data']['activation_count'])) {
            update_option('rank_math_pro_gpl_activation_count', absint($data['data']['activation_count']));
        }
        
        if (isset($data['data']['max_activations'])) {
            update_option('rank_math_pro_gpl_max_activations', absint($data['data']['max_activations']));
        }
        
        if (isset($data['data']['remaining_activations'])) {
            update_option('rank_math_pro_gpl_remaining_activations', intval($data['data']['remaining_activations']));
        }
        
        update_option('rank_math_pro_gpl_api_key', $api_key);
        update_option('rank_math_pro_gpl_key_status', 'active');
        
        // Información de activación detallada
        $activation_info = [
            'timestamp' => current_time('mysql'),
            'domain' => home_url(),
            'site_url' => get_site_url(),
            'home_url' => home_url(),
            'api_key_preview' => substr($api_key, 0, 8) . '...' . substr($api_key, -4),
            'response' => $data,
            'version' => '3.0.107'
        ];
        update_option('rank_math_pro_gpl_last_activation', $activation_info);
        
        // Información de registro para admin
        $registration_info = [
            'timestamp' => current_time('mysql'),
            'status' => 'success',
            'response_code' => $code,
            'message' => $data['message'] ?? 'Registro exitoso',
            'version' => '3.0.107'
        ];
        update_option('rank_math_pro_gpl_last_registration_attempt', $registration_info);
        
        $success_message = isset($data['message']) ? $data['message'] : '¡API Key activada!';
        wp_send_json_success([
            'message' => $success_message,
            'api_key' => $api_key,
            'expiry_date' => $data['data']['expiry_date'] ?? null
        ]);
    } else {
        // Error del servidor
        $error_message = isset($data['message']) ? $data['message'] : 'API Key inválida.';
        
        // Log del intento fallido
        $registration_info = [
            'timestamp' => current_time('mysql'),
            'status' => 'failed',
            'response_code' => $code,
            'message' => $error_message,
            'version' => '3.0.107'
        ];
        update_option('rank_math_pro_gpl_last_registration_attempt', $registration_info);
        
        wp_send_json_error(['message' => $error_message]);
    }
});

add_action('wp_ajax_rank_math_pro_gpl_deactivate_key', function(){
    check_ajax_referer('rank_math_pro_gpl_nonce','security');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permisos insuficientes.']);
    }
    
    $api_key = get_option('plugin_updater_api_key','');
    
    if (empty($api_key)) {
        // Si no hay key en formato Auto Updater, buscar en formato original
        $api_key = get_option('rank_math_pro_gpl_api_key','');
    }
    
    $server_url = defined('RANK_MATH_PRO_GPL_UPDATE_SERVER') ? RANK_MATH_PRO_GPL_UPDATE_SERVER : 'https://actualizarplugins.online/api/';
    
    // Intentar desregistrar del servidor
    if (!empty($api_key)) {
        $deactivate_endpoint = $server_url . 'deactivate.php';
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];
        $payload = [
            'apiKey' => $api_key,
            'siteUrl' => get_site_url()
        ];
        
        $response = wp_remote_post($deactivate_endpoint, [
            'timeout' => 10,
            'headers' => $headers,
            'body' => json_encode($payload)
        ]);
        
        // No fallar si la desregistración remota falla
        if (is_wp_error($response)) {
            error_log('Rank Math Pro v3.0.107 - Deactivation error: ' . $response->get_error_message());
        }
    }
    
    // Limpiar todas las opciones (ambos formatos)
    delete_option('plugin_updater_api_key');
    delete_option('plugin_updater_expiry');
    delete_option('rank_math_pro_gpl_api_key');
    delete_option('rank_math_pro_gpl_key_status');
    delete_option('rank_math_pro_gpl_activation_count');
    delete_option('rank_math_pro_gpl_max_activations');
    delete_option('rank_math_pro_gpl_remaining_activations');
    delete_option('rank_math_pro_gpl_last_activation');
    delete_option('rank_math_pro_gpl_last_registration_attempt');
    
    wp_send_json_success(['message' => 'API Key desactivada exitosamente. Dominio desregistrado.']);
});

// Cron para revalidación diaria
add_action('wp', function() {
    if (!wp_next_scheduled('rank_math_pro_gpl_revalidate_key')) {
        wp_schedule_event(time(), 'daily', 'rank_math_pro_gpl_revalidate_key');
    }
});

add_action('rank_math_pro_gpl_revalidate_key', function() {
    $api_key = get_option('plugin_updater_api_key','');
    
    if (empty($api_key)) {
        return; // No hay key para revalidar
    }
    
    $server_url = defined('RANK_MATH_PRO_GPL_UPDATE_SERVER') ? RANK_MATH_PRO_GPL_UPDATE_SERVER : 'https://actualizarplugins.online/api/';
    $validate_endpoint = $server_url . 'validate-key.php';
    
    $payload = [
        'apiKey'  => $api_key,
        'siteUrl' => get_site_url(),
    ];
    
    $response = wp_remote_post($validate_endpoint, [
        'timeout' => 15,
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($payload)
    ]);
    
    if (is_wp_error($response)) {
        error_log('Rank Math Pro v3.0.107 - Revalidation failed: ' . $response->get_error_message());
        return;
    }
    
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if ($code == 200 && isset($data['success']) && $data['success']) {
        // Revalidación exitosa
        update_option('rank_math_pro_gpl_last_revalidation', current_time('mysql'));
        
        if (isset($data['data']['expiry_date'])) {
            update_option('plugin_updater_expiry', $data['data']['expiry_date']);
        }
    } else {
        // Revalidación fallida - marcar como inactivo
        update_option('rank_math_pro_gpl_key_status', 'inactive');
        
        error_log('Rank Math Pro v3.0.107 - Revalidation failed: ' . $body);
    }
});
