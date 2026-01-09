<?php
/**
 * AJAX Handler para Elementor Pro GPL v3.33.1 - Auto Update System
 * Sistema simplificado siguiendo comportamiento de Auto_Updater
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_ajax_elementor_pro_gpl_validate_key', function(){
    check_ajax_referer('elementor_pro_gpl_nonce','security');
    
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'Permisos insuficientes.']);
    }
    
    $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
    if ( empty($api_key) ) {
        wp_send_json_error(['message' => 'API Key vacía.']);
    }
    
    // Usar endpoint del Auto Updater
    $server_url = defined('PLUGIN_UPDATER_SERVER') ? PLUGIN_UPDATER_SERVER : 'https://actualizarplugins.online/api/';
    $validate_endpoint = $server_url . 'validate-key.php';
    
    // Payload siguiendo patrón de Auto Updater
    $payload = [
        'apiKey'  => $api_key,
        'siteUrl' => get_site_url(),  // site_url() como Auto Updater
    ];
    
    $response = wp_remote_post($validate_endpoint, [
        'timeout' => 30,
        'headers' => [
            'Accept' => 'application/json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        ],
        'body' => ['apiKey' => $api_key, 'siteUrl' => site_url()]
    ]);
    
    if ( is_wp_error($response) ) {
        wp_send_json_error(['message' => 'Error al conectar con el servidor: ' . $response->get_error_message()]);
    }
    
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    // Log de debugging
    error_log('Elementor Pro v3.33.1 - Validate Response: HTTP ' . $code . ' - ' . $body);
    
    if ( $code != 200 ) {
        wp_send_json_error(['message' => 'Respuesta inesperada del servidor: HTTP ' . $code]);
    }
    
    $data = json_decode($body, true);
    
    if ( json_last_error() !== JSON_ERROR_NONE || empty($data) ) {
        wp_send_json_error(['message' => 'Respuesta del servidor no válida.']);
    }
    
    // Procesar respuesta
    if ( isset($data['success']) && $data['success'] ) {
        // Almacenar usando nombres de Auto Updater para compatibilidad
        update_option('plugin_updater_api_key', $api_key);
        
        if ( isset($data['data']['expiry_date']) ) {
            update_option('plugin_updater_expiry', $data['data']['expiry_date']);
        }
        
        // También mantener compatibilidad con sistema original
        update_option('elementor_pro_gpl_api_key', $api_key);
        update_option('elementor_pro_gpl_key_status', 'active');
        
        // Información de activación detallada
        $activation_info = [
            'timestamp' => current_time('mysql'),
            'domain' => home_url(),
            'site_url' => get_site_url(),
            'home_url' => home_url(),
            'api_key_preview' => substr($api_key, 0, 8) . '...' . substr($api_key, -4),
            'response' => $data,
            'version' => '3.33.1'
        ];
        update_option('elementor_pro_gpl_last_activation', $activation_info);
        
        // Información de registro para admin
        $registration_info = [
            'timestamp' => current_time('mysql'),
            'status' => 'success',
            'response_code' => $code,
            'message' => $data['message'] ?? 'Registro exitoso',
            'version' => '3.33.1'
        ];
        update_option('elementor_pro_gpl_last_registration_attempt', $registration_info);
        
        // Mensaje de éxito
        $success_message = isset($data['message']) ? $data['message'] : '¡API Key activada y dominio registrado exitosamente!';
        wp_send_json_success([
            'message' => $success_message,
            'api_key' => $api_key,
            'domain_registered' => true,
            'expiry_date' => $data['data']['expiry_date'] ?? null,
            'version' => '3.33.1'
        ]);
    } else {
        // Error del servidor
        $error_message = isset($data['message']) ? $data['message'] : 'API Key inválida o dominio no permitido.';
        
        // Log del intento fallido
        $registration_info = [
            'timestamp' => current_time('mysql'),
            'status' => 'failed',
            'response_code' => $code,
            'message' => $error_message,
            'version' => '3.33.1'
        ];
        update_option('elementor_pro_gpl_last_registration_attempt', $registration_info);
        
        wp_send_json_error(['message' => $error_message]);
    }
});

add_action('wp_ajax_elementor_pro_gpl_deactivate_key', function(){
    check_ajax_referer('elementor_pro_gpl_nonce','security');
    
    if ( ! current_user_can('manage_options') ) {
        wp_send_json_error(['message' => 'Permisos insuficientes.']);
    }
    
    $api_key = get_option('plugin_updater_api_key','');
    
    if ( empty($api_key) ) {
        // Si no hay key en formato Auto Updater, buscar en formato original
        $api_key = get_option('elementor_pro_gpl_api_key','');
    }
    
    $server_url = defined('PLUGIN_UPDATER_SERVER') ? PLUGIN_UPDATER_SERVER : 'https://actualizarplugins.online/api/';
    
    // Intentar desregistrar del servidor
    if ( !empty($api_key) ) {
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
        if ( is_wp_error($response) ) {
            error_log('Elementor Pro v3.33.1 - Deactivation error: ' . $response->get_error_message());
        }
    }
    
    // Limpiar todas las opciones (ambos formatos)
    delete_option('plugin_updater_api_key');
    delete_option('plugin_updater_expiry');
    delete_option('elementor_pro_gpl_api_key');
    delete_option('elementor_pro_gpl_key_status');
    delete_option('elementor_pro_gpl_last_activation');
    delete_option('elementor_pro_gpl_last_registration_attempt');
    
    wp_send_json_success(['message' => 'API Key desactivada exitosamente. Dominio desregistrado.']);
});

// Cron para revalidación diaria
add_action('wp', function() {
    if ( ! wp_next_scheduled('elementor_pro_gpl_revalidate_key') ) {
        wp_schedule_event(time(), 'daily', 'elementor_pro_gpl_revalidate_key');
    }
});

add_action('elementor_pro_gpl_revalidate_key', function() {
    $api_key = get_option('plugin_updater_api_key','');
    
    if ( empty($api_key) ) {
        return; // No hay key para revalidar
    }
    
    $server_url = defined('PLUGIN_UPDATER_SERVER') ? PLUGIN_UPDATER_SERVER : 'https://actualizarplugins.online/api/';
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
    
    if ( is_wp_error($response) ) {
        error_log('Elementor Pro v3.33.1 - Revalidation failed: ' . $response->get_error_message());
        return;
    }
    
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if ( $code == 200 && isset($data['success']) && $data['success'] ) {
        // Revalidación exitosa
        update_option('elementor_pro_gpl_last_revalidation', current_time('mysql'));
        
        if ( isset($data['data']['expiry_date']) ) {
            update_option('plugin_updater_expiry', $data['data']['expiry_date']);
        }
    } else {
        // Revalidación fallida - marcar como inactivo
        // Si el error es por caducidad, el servidor devuelve success: false y un mensaje de error.
        // Si el error es por otro motivo (ej. dominio), también desactiva.
        update_option('elementor_pro_gpl_key_status', 'inactive');
        
        error_log('Elementor Pro v3.33.1 - Revalidation failed: ' . $body);
        
    }
});