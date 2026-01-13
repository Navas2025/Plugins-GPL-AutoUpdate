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
        
        // Cargar scripts para AJAX
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
    }

    /**
     * Cargar scripts para AJAX
     */
    public static function enqueue_scripts() {
        // Pasar datos a JavaScript de forma segura
        wp_localize_script( 'jquery', 'lncAjax', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'lnc_dismiss_nonce' )
        ) );
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Descartar aviso individual
            $(document).on('click', '.lnc-dismiss-update', function(e) {
                e.preventDefault();
                var slug = $(this).data('slug');
                var $notice = $(this).closest('.lnc-update-notice');
                
                $.post(lncAjax.ajaxurl, {
                    action: 'lnc_dismiss_update',
                    slug: slug,
                    nonce: lncAjax.nonce
                }, function(response) {
                    if (response.success) {
                        $notice.fadeOut(300, function() {
                            $(this).remove();
                            // Si no quedan más avisos, recargar la página
                            if ($('.lnc-update-notice').length === 0) {
                                location.reload();
                            }
                        });
                    }
                });
            });
            
            // Descartar todos los avisos
            $(document).on('click', '.lnc-dismiss-all', function(e) {
                e.preventDefault();
                
                $.post(lncAjax.ajaxurl, {
                    action: 'lnc_dismiss_all_updates',
                    nonce: lncAjax.nonce
                }, function(response) {
                    if (response.success) {
                        $('.lnc-update-notice').fadeOut(300, function() {
                            $(this).remove();
                            location.reload();
                        });
                    }
                });
            });
        });
        </script>
        <style type="text/css">
        .lnc-update-notice {
            position: relative;
            padding-right: 100px;
        }
        .lnc-dismiss-buttons {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .lnc-dismiss-update,
        .lnc-dismiss-all {
            margin-left: 5px;
            text-decoration: none;
            padding: 5px 10px;
            background: #f0f0f1;
            border: 1px solid #c3c4c7;
            border-radius: 3px;
            font-size: 13px;
            cursor: pointer;
            display: inline-block;
        }
        .lnc-dismiss-update:hover,
        .lnc-dismiss-all:hover {
            background: #e0e0e1;
        }
        .lnc-update-item {
            margin: 5px 0;
        }
        </style>
        <?php
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
        $updates = get_option( 'lnc_available_updates', array() );
        
        if ( empty( $updates ) ) {
            return;
        }
        
        // Obtener plugins instalados para comparar versiones
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $installed_plugins = get_plugins();
        
        // Filtrar actualizaciones: solo mostrar si versión instalada < versión disponible
        $updates_to_show = array();
        
        foreach ( $updates as $update ) {
            $plugin_slug = $update['slug'];
            $new_version = $update['new_version'];
            
            // Buscar versión instalada del plugin
            $installed_version = null;
            foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
                $current_slug = dirname( $plugin_file );
                if ( $current_slug === '.' ) {
                    $current_slug = basename( $plugin_file, '.php' );
                }
                
                if ( $current_slug === $plugin_slug ) {
                    $installed_version = $plugin_data['Version'];
                    break;
                }
            }
            
            // Solo mostrar si: plugin instalado Y versión instalada < versión nueva
            if ( $installed_version && version_compare( $installed_version, $new_version, '<' ) ) {
                $updates_to_show[] = $update;
            }
        }
        
        // Si no hay actualizaciones relevantes, no mostrar nada
        if ( empty( $updates_to_show ) ) {
            return;
        }
        
        $count = count( $updates_to_show );
        
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>🔔 Actualizaciones Disponibles (' . $count . ')</strong></p>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        
        foreach ( $updates_to_show as $update ) {
            echo '<li>';
            echo '<strong>' . esc_html( $update['name'] ) . '</strong> ';
            echo 'v' . esc_html( $update['current_version'] ) . ' → ';
            echo '<strong>v' . esc_html( $update['new_version'] ) . '</strong> ';
            echo '<a href="' . esc_url( $update['download_url'] ) . '" target="_blank">Ver detalles</a>';
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