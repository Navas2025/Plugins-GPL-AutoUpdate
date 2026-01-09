<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ? ADAPTADO: AJAX para validacion de API Key con servidor externo
 * Usa endpoint validate-key.php del servidor para sincronizaci車n en tiempo real
 */

function yoast_seo_premium_gpl_check_license_status() {
    $api_key = get_option('yoast_seo_premium_gpl_api_key','');
    $status = get_option('yoast_seo_premium_gpl_key_status', 'inactive');
    
    if (empty($api_key) || $status !== 'active') {
        return;
    }
    
    // ? ADAPTADO: Usar servidor externo
    $server_url = defined('PLUGIN_UPDATER_SERVER') ? PLUGIN_UPDATER_SERVER : 'https://actualizarplugins.online/api/';
    
    $response = wp_remote_post($server_url . 'validate-key.php', [
        'timeout' => 30,
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        ],
        'body' => http_build_query(['apiKey' => $api_key, 'siteUrl' => site_url()])
    ]);

    if (is_wp_error($response)) {
        error_log('Yoast SEO GPL: Error conectando a servidor de validaci車n: ' . $response->get_error_message());
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data['success'])) {
        update_option('yoast_seo_premium_gpl_key_status', 'inactive');
        error_log('Yoast SEO Premium GPL: API Key desactivada por validaci車n fallida.');
        
        $expiry_date = get_option('yoast_seo_premium_gpl_expiry','');
        if ( !empty($expiry_date) ) {
            $expiry_timestamp = strtotime($expiry_date);
            $current_timestamp = current_time('timestamp');
            
            if ($current_timestamp >= $expiry_timestamp) {
                update_option('yoast_seo_premium_gpl_key_status', 'expired');
            }
        }
        return;
    }
    
    // ? ADAPTADO: Actualizar estado y datos desde servidor
    update_option('yoast_seo_premium_gpl_key_status', 'active');
    
    if (isset($data['data']['expiry_date'])) {
        update_option('yoast_seo_premium_gpl_expiry', $data['data']['expiry_date']);
    }
    if (isset($data['data']['activations_count'])) {
        update_option('yoast_seo_premium_gpl_activation_count', $data['data']['activations_count']);
    }
    if (isset($data['data']['max_activations'])) {
        update_option('yoast_seo_premium_gpl_max_activations', $data['data']['max_activations']);
    }
    if (isset($data['data']['remaining_activations'])) {
        update_option('yoast_seo_premium_gpl_remaining_activations', $data['data']['remaining_activations']);
    }
}

add_action('admin_init', 'yoast_seo_premium_gpl_check_license_status');

if (!wp_next_scheduled('yoast_seo_premium_gpl_license_check')) {
    wp_schedule_event(time(), 'twicedaily', 'yoast_seo_premium_gpl_license_check');
}
add_action('yoast_seo_premium_gpl_license_check', 'yoast_seo_premium_gpl_check_license_status');

// ? ADAPTADO: AJAX para validaci車n manual
add_action('wp_ajax_yoast_seo_premium_gpl_validate_key', function() {
    check_ajax_referer('yoast_seo_premium_gpl_nonce','security');
    if ( ! current_user_can('manage_options') ) wp_send_json_error(['message'=>'Permisos insuficientes.']);
    
    $api_key = sanitize_text_field($_POST['api_key'] ?? '');
    if (empty($api_key)) wp_send_json_error(['message'=>'API Key no proporcionada.']);

    $server_url = defined('PLUGIN_UPDATER_SERVER') ? PLUGIN_UPDATER_SERVER : 'https://actualizarplugins.online/api/';
    
    // ? ADAPTADO: Llamar a validate-key.php del servidor externo
    $response = wp_remote_post($server_url . 'validate-key.php', [
        'timeout' => 30,
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        ],
        'body' => http_build_query(['apiKey' => $api_key, 'siteUrl' => site_url()])
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Error de conexi車n con el servidor de licencias: ' . $response->get_error_message()]);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['success']) && $data['success']) {
        // ? ADAPTADO: Guardar la API Key y datos del servidor
        update_option('yoast_seo_premium_gpl_api_key', $api_key);
        update_option('yoast_seo_premium_gpl_key_status', 'active');
        
        if (isset($data['data']['expiry_date'])) {
            update_option('yoast_seo_premium_gpl_expiry', $data['data']['expiry_date']);
        }
        if (isset($data['data']['activations_count'])) {
            update_option('yoast_seo_premium_gpl_activation_count', $data['data']['activations_count']);
        }
        if (isset($data['data']['max_activations'])) {
            update_option('yoast_seo_premium_gpl_max_activations', $data['data']['max_activations']);
        }
        if (isset($data['data']['remaining_activations'])) {
            update_option('yoast_seo_premium_gpl_remaining_activations', $data['data']['remaining_activations']);
        }
        
        wp_send_json_success([
            'message' => 'API Key activada correctamente.',
            'api_key' => $api_key,
            'data' => $data['data'] ?? []
        ]);
    } else {
        $error_message = isset($data['message']) ? $data['message'] : 'API Key no v芍lida.';
        wp_send_json_error(['message' => $error_message]);
    }
});

add_action('wp_ajax_yoast_seo_premium_gpl_deactivate_key', function() {
    check_ajax_referer('yoast_seo_premium_gpl_nonce','security');
    if ( ! current_user_can('manage_options') ) wp_send_json_error(['message'=>'Permisos insuficientes.']);
    
    delete_option('yoast_seo_premium_gpl_api_key');
    update_option('yoast_seo_premium_gpl_key_status', 'inactive');
    delete_option('yoast_seo_premium_gpl_expiry');
    delete_option('yoast_seo_premium_gpl_activation_count');
    delete_option('yoast_seo_premium_gpl_max_activations');
    delete_option('yoast_seo_premium_gpl_remaining_activations');
    
    wp_send_json_success(['message'=>'API Key desactivada correctamente.']);
});
?>