<?php
/**
 * Verificador de actualizaciones
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LNC_Update_Checker {

    public static function init() {
        // Verificación automática cada 12 horas
        add_action( 'lnc_check_updates', array( __CLASS__, 'check_updates' ) );
        
        // Verificar al cargar admin (si han pasado 12 horas)
        add_action( 'admin_init', array( __CLASS__, 'maybe_check_updates' ) );
        
        // Mostrar avisos de actualizaciones
        add_action( 'admin_notices', array( __CLASS__, 'show_update_notices' ) );
        
        // AJAX handlers para descartar avisos
        add_action( 'wp_ajax_lnc_dismiss_update', array( __CLASS__, 'dismiss_update_notice' ) );
        add_action( 'wp_ajax_lnc_dismiss_all_updates', array( __CLASS__, 'dismiss_all_notices' ) );
        
        // AJAX handler para snooze (ocultar 12 horas)
        add_action( 'wp_ajax_lnc_snooze_notices', array( __CLASS__, 'snooze_notices' ) );
        
        // Cargar scripts para AJAX
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_snooze_scripts' ) );
    }

    /**
     * Cargar scripts para AJAX (Descartar avisos individuales/todos)
     */
    public static function enqueue_scripts() {
        // Registrar un script dummy para poder usar wp_localize_script
        wp_register_script( 'lnc-dismiss-handler', '', array( 'jquery' ), LNC_VERSION, true );
        wp_enqueue_script( 'lnc-dismiss-handler' );
        
        // Pasar datos a JavaScript de forma segura
        wp_localize_script( 'lnc-dismiss-handler', 'lncAjax', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'lnc_dismiss_nonce' )
        ) );
        
        // JavaScript inline para manejar los botones
        $js = "
        jQuery(document).ready(function($) {
            // Descartar aviso individual
            $(document).on('click', '.lnc-dismiss-update', function(e) {
                e.preventDefault();
                
                var slug = $(this).data('slug');
                var \$notice = $(this).closest('.lnc-update-item');
                
                console.log('Descartando aviso:', slug);
                
                $.ajax({
                    url: lncAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lnc_dismiss_update',
                        slug: slug,
                        nonce: lncAjax.nonce
                    },
                    success: function(response) {
                        console.log('Respuesta:', response);
                        if (response.success) {
                            \$notice.fadeOut(300, function() {
                                $(this).remove();
                                
                                // Si no quedan más avisos, ocultar el contenedor completo
                                if ($('.lnc-update-item').length === 0) {
                                    $('.lnc-update-notice').fadeOut(300, function() {
                                        $(this).remove();
                                    });
                                }
                            });
                        } else {
                            alert('Error al descartar aviso');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error AJAX:', error);
                        alert('Error de conexión al descartar aviso');
                    }
                });
            });
            
            // Descartar todos los avisos
            $(document).on('click', '.lnc-dismiss-all', function(e) {
                e.preventDefault();
                
                if (!confirm('¿Descartar todas las actualizaciones?')) {
                    return;
                }
                
                console.log('Descartando todos los avisos');
                
                $.ajax({
                    url: lncAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lnc_dismiss_all_updates',
                        nonce: lncAjax.nonce
                    },
                    success: function(response) {
                        console.log('Respuesta:', response);
                        if (response.success) {
                            $('.lnc-update-notice').fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            alert('Error al descartar avisos');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error AJAX:', error);
                        alert('Error de conexión al descartar avisos');
                    }
                });
            });
        });
        ";
        
        wp_add_inline_script( 'lnc-dismiss-handler', $js );
        
        // CSS mejorado
        $css = "
        .lnc-update-notice {
            position: relative;
            padding: 12px 15px !important;
        }
        
        .lnc-update-notice-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .lnc-update-notice-title {
            margin: 0;
            font-size: 14px;
        }
        
        .lnc-update-notice-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .lnc-snooze-button {
            padding: 4px 12px;
            background: #2271b1;
            color: #fff;
            border: 1px solid #2271b1;
            border-radius: 3px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .lnc-snooze-button:hover {
            background: #135e96;
            border-color: #135e96;
            color: #fff;
        }
        
        .lnc-snooze-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .lnc-dismiss-all {
            padding: 4px 12px;
            background: #f0f0f1;
            color: #2c3338;
            border: 1px solid #c3c4c7;
            border-radius: 3px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .lnc-dismiss-all:hover {
            background: #e0e0e1;
            border-color: #8c8f94;
            color: #1d2327;
        }
        
        .lnc-update-list {
            list-style: disc;
            margin-left: 20px;
            margin-bottom: 0;
        }
        
        .lnc-update-item {
            margin: 5px 0;
        }
        
        .lnc-dismiss-update {
            color: #b32d2e;
            text-decoration: none;
            font-size: 13px;
        }
        
        .lnc-dismiss-update:hover {
            color: #dc3232;
            text-decoration: underline;
        }
        ";
        
        wp_add_inline_style( 'common', $css );
    }

    /**
     * Cargar JavaScript para el botón "Ocultar por 12 horas"
     */
    public static function enqueue_snooze_scripts() {
        // Solo cargar si hay actualizaciones
        $updates = get_option( 'lnc_available_updates', array() );
        if ( empty( $updates ) ) {
            return;
        }
        
        // Verificar si están ocultas temporalmente
        $snoozed_until = get_option( 'lnc_snoozed_until', 0 );
        if ( time() < $snoozed_until ) {
            return;
        }
        
        // Registrar script para snooze
        wp_register_script( 'lnc-snooze-handler', '', array( 'jquery' ), LNC_VERSION, true );
        wp_enqueue_script( 'lnc-snooze-handler' );
        
        // Pasar datos de forma segura a JavaScript
        wp_localize_script( 'lnc-snooze-handler', 'lncSnooze', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'lnc_snooze_nonce' )
        ) );
        
        // JavaScript inline
        $js = "
        jQuery(document).ready(function($) {
            // Manejar click en botón 'Ocultar por 12 horas'
            $(document).on('click', '.lnc-snooze-button', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var originalText = button.text();
                button.prop('disabled', true).text('Ocultando...');
                
                console.log('Ocultando avisos por 12 horas');
                
                $.ajax({
                    url: lncSnooze.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'lnc_snooze_notices',
                        nonce: lncSnooze.nonce
                    },
                    success: function(response) {
                        console.log('Respuesta snooze:', response);
                        if (response.success) {
                            // Ocultar el aviso con animación
                            button.closest('.notice').fadeOut(300, function() {
                                $(this).remove();
                            });
                            
                            // Mostrar mensaje de confirmación temporal
                            $('<div class=\"notice notice-success is-dismissible\" style=\"display:none;\"><p><strong>✅ Avisos ocultos por 12 horas.</strong> Volverán a aparecer automáticamente.</p></div>')
                                .insertAfter('.wp-header-end')
                                .fadeIn(300)
                                .delay(3000)
                                .fadeOut(300, function() { $(this).remove(); });
                        } else {
                            alert('Error al ocultar avisos');
                            button.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error AJAX snooze:', error);
                        alert('Error de conexión al ocultar avisos. Inténtalo de nuevo.');
                        button.prop('disabled', false).text(originalText);
                    }
                });
            });
        });
        ";
        
        wp_add_inline_script( 'lnc-snooze-handler', $js );
    }

    /**
     * Verificar si es momento de comprobar actualizaciones
     */
    public static function maybe_check_updates() {
        $last_check = get_option( 'lnc_last_check', 0 );
        $check_interval = 12 * HOUR_IN_SECONDS;
        
        if ( ( time() - $last_check ) > $check_interval ) {
            self::check_updates();
        }
    }

    /**
     * Verificar actualizaciones disponibles
     */
    public static function check_updates() {
        // Limpiar avisos descartados hace más de 7 días
        self::cleanup_old_dismissed();
        
        $installed_plugins = self::get_installed_plugins();
        
        if ( empty( $installed_plugins ) ) {
            return;
        }
        
        $response = wp_remote_post( LNC_API_URL, array(
            'timeout' => 15,
            'body' => array(
                'action' => 'check_updates',
                'api_key' => LNC_API_KEY,
                'plugins' => json_encode( $installed_plugins )
            )
        ) );
        
        if ( is_wp_error( $response ) ) {
            update_option( 'lnc_last_error', $response->get_error_message() );
            return;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        // Guardar respuesta completa para debug
        update_option( 'lnc_last_response', array(
            'time' => current_time( 'mysql' ),
            'http_code' => wp_remote_retrieve_response_code( $response ),
            'body' => $body,
            'data' => $data
        ) );
        
        if ( isset( $data['success'] ) && $data['success'] && isset( $data['updates'] ) ) {
            update_option( 'lnc_available_updates', $data['updates'] );
        } else {
            update_option( 'lnc_available_updates', array() );
        }
        
        update_option( 'lnc_last_check', time() );
    }

    /**
     * Obtener lista de plugins instalados
     */
    private static function get_installed_plugins() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $installed = array();
        
        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            $slug = dirname( $plugin_file );
            if ( $slug === '.' ) {
                $slug = basename( $plugin_file, '.php' );
            }
            
            $installed[] = array(
                'slug' => $slug,
                'version' => $plugin_data['Version'],
                'name' => $plugin_data['Name']
            );
        }
        
        return $installed;
    }

    /**
     * Mostrar avisos de actualizaciones disponibles
     */
    public static function show_update_notices() {
        // Verificar permisos
        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }
        
        // Verificar si están ocultas temporalmente
        $snoozed_until = get_option( 'lnc_snoozed_until', 0 );
        if ( time() < $snoozed_until ) {
            return; // No mostrar avisos hasta que pasen las 12 horas
        }
        
        $updates = get_option( 'lnc_available_updates', array() );
        
        if ( empty( $updates ) || ! is_array( $updates ) ) {
            return;
        }
        
        // Obtener plugins instalados actualmente
        $installed_plugins = self::get_installed_plugins();
        $installed_versions = array();
        
        foreach ( $installed_plugins as $plugin ) {
            $installed_versions[ $plugin['slug'] ] = $plugin['version'];
        }
        
        // Obtener avisos descartados
        $dismissed = get_option( 'lnc_dismissed_updates', array() );
        
        // Filtrar actualizaciones
        $filtered_updates = array();
        foreach ( $updates as $update ) {
            $slug = isset( $update['slug'] ) ? $update['slug'] : '';
            
            if ( empty( $slug ) ) {
                continue;
            }
            
            // Saltar si está descartado
            if ( isset( $dismissed[ $slug ] ) ) {
                continue;
            }
            
            // Saltar si ya está actualizado (comparar versiones)
            if ( isset( $installed_versions[ $slug ] ) ) {
                $installed_version = $installed_versions[ $slug ];
                $new_version = isset( $update['new_version'] ) ? $update['new_version'] : '';
                
                // Si la versión instalada es mayor o igual, no mostrar
                if ( ! empty( $new_version ) && version_compare( $installed_version, $new_version, '>=' ) ) {
                    continue;
                }
            }
            
            $filtered_updates[] = $update;
        }
        
        if ( empty( $filtered_updates ) ) {
            return;
        }
        
        $count = count( $filtered_updates );
        
        echo '<div class="notice notice-warning lnc-update-notice">';
        
        // Header con título y botones de acción
        echo '<div class="lnc-update-notice-header">';
        echo '<p class="lnc-update-notice-title"><strong>🔔 Actualizaciones Disponibles (' . esc_html( $count ) . ')</strong></p>';
        echo '<div class="lnc-update-notice-actions">';
        echo '<button type="button" class="lnc-snooze-button">⏰ Ocultar por 12 horas</button>';
        echo '<a href="#" class="lnc-dismiss-all">Descartar todo</a>';
        echo '</div>';
        echo '</div>';
        
        // Lista de actualizaciones
        echo '<ul class="lnc-update-list">';
        
        foreach ( $filtered_updates as $update ) {
            $slug = isset( $update['slug'] ) ? $update['slug'] : '';
            $name = isset( $update['name'] ) ? $update['name'] : 'Plugin';
            $current_version = isset( $update['current_version'] ) ? $update['current_version'] : '';
            $new_version = isset( $update['new_version'] ) ? $update['new_version'] : '';
            $download_url = isset( $update['download_url'] ) ? $update['download_url'] : '';
            
            echo '<li class="lnc-update-item">';
            echo '<strong>' . esc_html( $name ) . '</strong> ';
            
            if ( ! empty( $current_version ) ) {
                echo 'v' . esc_html( $current_version ) . ' → ';
            }
            
            echo '<strong>v' . esc_html( $new_version ) . '</strong> ';
            
            if ( ! empty( $download_url ) ) {
                echo '<a href="' . esc_url( $download_url ) . '" target="_blank">Ver detalles</a>';
                echo ' | ';
            }
            
            echo '<a href="#" class="lnc-dismiss-update" data-slug="' . esc_attr( $slug ) . '">Descartar</a>';
            echo '</li>';
        }
        
        echo '</ul>';
        echo '</div>';
    }

    /**
     * Descartar un aviso individual (AJAX)
     */
    public static function dismiss_update_notice() {
        // Verificar nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! wp_verify_nonce( $nonce, 'lnc_dismiss_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Nonce inválido' ) );
            return;
        }
        
        // Verificar permisos
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array( 'message' => 'Permisos insuficientes' ) );
            return;
        }
        
        // Verificar que se proporcionó el slug
        if ( ! isset( $_POST['slug'] ) ) {
            wp_send_json_error( array( 'message' => 'Slug no proporcionado' ) );
            return;
        }
        
        $slug = sanitize_text_field( $_POST['slug'] );
        
        // Obtener avisos descartados actuales
        $dismissed = get_option( 'lnc_dismissed_updates', array() );
        
        // Agregar este aviso con timestamp
        $dismissed[ $slug ] = time();
        
        // Guardar
        update_option( 'lnc_dismissed_updates', $dismissed );
        
        wp_send_json_success( array( 'message' => 'Aviso descartado' ) );
    }

    /**
     * Descartar todos los avisos (AJAX)
     */
    public static function dismiss_all_notices() {
        // Verificar nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! wp_verify_nonce( $nonce, 'lnc_dismiss_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Nonce inválido' ) );
            return;
        }
        
        // Verificar permisos
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array( 'message' => 'Permisos insuficientes' ) );
            return;
        }
        
        // Obtener todas las actualizaciones actuales
        $updates = get_option( 'lnc_available_updates', array() );
        $dismissed = get_option( 'lnc_dismissed_updates', array() );
        
        // Marcar todas las actualizaciones como descartadas
        foreach ( $updates as $update ) {
            if ( isset( $update['slug'] ) ) {
                $dismissed[ $update['slug'] ] = time();
            }
        }
        
        // Guardar avisos descartados
        update_option( 'lnc_dismissed_updates', $dismissed );
        
        wp_send_json_success( array( 'message' => 'Todos los avisos descartados' ) );
    }

    /**
     * Ocultar avisos por 12 horas (AJAX)
     */
    public static function snooze_notices() {
        // Verificar nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
        if ( ! wp_verify_nonce( $nonce, 'lnc_snooze_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Nonce inválido' ) );
            return;
        }
        
        // Verificar permisos
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array( 'message' => 'Permisos insuficientes' ) );
            return;
        }
        
        // Guardar timestamp para ocultar por 12 horas
        $snoozed_until = time() + ( 12 * HOUR_IN_SECONDS );
        update_option( 'lnc_snoozed_until', $snoozed_until );
        
        wp_send_json_success( array( 
            'message' => 'Avisos ocultos por 12 horas',
            'until' => $snoozed_until
        ) );
    }

    /**
     * Limpiar avisos descartados hace más de 7 días
     */
    private static function cleanup_old_dismissed() {
        $dismissed = get_option( 'lnc_dismissed_updates', array() );
        
        if ( empty( $dismissed ) ) {
            return;
        }
        
        $seven_days_ago = time() - ( 7 * DAY_IN_SECONDS );
        $updated = false;
        
        foreach ( $dismissed as $slug => $timestamp ) {
            if ( $timestamp < $seven_days_ago ) {
                unset( $dismissed[ $slug ] );
                $updated = true;
            }
        }
        
        if ( $updated ) {
            update_option( 'lnc_dismissed_updates', $dismissed );
        }
    }
}