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
        add_action( 'wp_ajax_lnc_dismiss_update', array( __CLASS__, 'ajax_dismiss_update' ) );
        add_action( 'wp_ajax_lnc_dismiss_all_updates', array( __CLASS__, 'ajax_dismiss_all_updates' ) );
        
        // Limpiar avisos descartados antiguos (diariamente)
        add_action( 'lnc_check_updates', array( __CLASS__, 'cleanup_old_dismissed_updates' ) );
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
            // Filtrar actualizaciones que ya están instaladas
            $filtered_updates = self::filter_already_updated( $data['updates'] );
            update_option( 'lnc_available_updates', $filtered_updates );
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
     * Filtrar actualizaciones que ya están instaladas
     */
    private static function filter_already_updated( $updates ) {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $filtered = array();
        
        foreach ( $updates as $update ) {
            $is_outdated = false;
            
            // Buscar el plugin instalado por slug o nombre
            foreach ( $all_plugins as $plugin_file => $plugin_data ) {
                $slug = dirname( $plugin_file );
                if ( $slug === '.' ) {
                    $slug = basename( $plugin_file, '.php' );
                }
                
                // Verificar si coincide por slug o nombre
                if ( $slug === $update['slug'] || $plugin_data['Name'] === $update['name'] ) {
                    // Comparar versiones: solo mostrar si la nueva versión es mayor
                    if ( version_compare( $plugin_data['Version'], $update['new_version'], '<' ) ) {
                        $is_outdated = true;
                    }
                    break;
                }
            }
            
            // Solo agregar si el plugin está desactualizado
            if ( $is_outdated ) {
                $filtered[] = $update;
            }
        }
        
        return $filtered;
    }

    /**
     * Verificar si un aviso está descartado
     */
    private static function is_update_dismissed( $update ) {
        $dismissed = get_option( 'lnc_dismissed_updates', array() );
        
        if ( empty( $dismissed ) ) {
            return false;
        }
        
        // Verificar si esta actualización específica está descartada
        foreach ( $dismissed as $dismissed_update ) {
            if ( $dismissed_update['slug'] === $update['slug'] && 
                 $dismissed_update['version'] === $update['new_version'] ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Descartar un aviso de actualización
     */
    public static function dismiss_update( $slug, $version ) {
        $dismissed = get_option( 'lnc_dismissed_updates', array() );
        
        // Agregar a la lista de descartados con timestamp
        $dismissed[] = array(
            'slug' => $slug,
            'version' => $version,
            'dismissed_at' => time()
        );
        
        update_option( 'lnc_dismissed_updates', $dismissed );
    }

    /**
     * Descartar todos los avisos actuales
     */
    public static function dismiss_all_updates() {
        $updates = get_option( 'lnc_available_updates', array() );
        
        if ( empty( $updates ) ) {
            return;
        }
        
        $dismissed = get_option( 'lnc_dismissed_updates', array() );
        
        foreach ( $updates as $update ) {
            $dismissed[] = array(
                'slug' => $update['slug'],
                'version' => $update['new_version'],
                'dismissed_at' => time()
            );
        }
        
        update_option( 'lnc_dismissed_updates', $dismissed );
    }

    /**
     * Limpiar avisos descartados después de 7 días
     */
    public static function cleanup_old_dismissed_updates() {
        $dismissed = get_option( 'lnc_dismissed_updates', array() );
        
        if ( empty( $dismissed ) ) {
            return;
        }
        
        $seven_days_ago = time() - ( 7 * DAY_IN_SECONDS );
        $cleaned = array();
        
        foreach ( $dismissed as $dismissed_update ) {
            // Mantener solo los descartados hace menos de 7 días
            if ( $dismissed_update['dismissed_at'] > $seven_days_ago ) {
                $cleaned[] = $dismissed_update;
            }
        }
        
        update_option( 'lnc_dismissed_updates', $cleaned );
    }

    /**
     * AJAX: Descartar un aviso individual
     */
    public static function ajax_dismiss_update() {
        check_ajax_referer( 'lnc_dismiss_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permisos insuficientes' ) );
        }
        
        $slug = isset( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : '';
        $version = isset( $_POST['version'] ) ? sanitize_text_field( $_POST['version'] ) : '';
        
        if ( empty( $slug ) || empty( $version ) ) {
            wp_send_json_error( array( 'message' => 'Datos inválidos' ) );
        }
        
        self::dismiss_update( $slug, $version );
        
        wp_send_json_success( array( 'message' => 'Aviso descartado correctamente' ) );
    }

    /**
     * AJAX: Descartar todos los avisos
     */
    public static function ajax_dismiss_all_updates() {
        check_ajax_referer( 'lnc_dismiss_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permisos insuficientes' ) );
        }
        
        self::dismiss_all_updates();
        
        wp_send_json_success( array( 'message' => 'Todos los avisos descartados' ) );
    }

    /**
     * Mostrar avisos de actualizaciones disponibles
     */
    public static function show_update_notices() {
        $updates = get_option( 'lnc_available_updates', array() );
        
        if ( empty( $updates ) ) {
            return;
        }
        
        // Filtrar avisos descartados
        $visible_updates = array();
        foreach ( $updates as $update ) {
            if ( ! self::is_update_dismissed( $update ) ) {
                $visible_updates[] = $update;
            }
        }
        
        if ( empty( $visible_updates ) ) {
            return;
        }
        
        $count = count( $visible_updates );
        
        // Generar nonce para AJAX
        $nonce = wp_create_nonce( 'lnc_dismiss_nonce' );
        
        echo '<div class="notice notice-warning" id="lnc-update-notice" style="position: relative;">';
        echo '<p><strong>🔔 Actualizaciones Disponibles (' . $count . ')</strong></p>';
        echo '<ul style="list-style: disc; margin-left: 20px;">';
        
        foreach ( $visible_updates as $index => $update ) {
            $update_id = 'lnc-update-' . $index;
            echo '<li id="' . esc_attr( $update_id ) . '" style="margin-bottom: 8px;">';
            echo '<strong>' . esc_html( $update['name'] ) . '</strong> ';
            echo 'v' . esc_html( $update['current_version'] ) . ' → ';
            echo '<strong>v' . esc_html( $update['new_version'] ) . '</strong> ';
            echo '<a href="' . esc_url( $update['download_url'] ) . '" target="_blank">Ver detalles</a> ';
            echo '<button type="button" class="button button-small lnc-dismiss-single" ';
            echo 'data-slug="' . esc_attr( $update['slug'] ) . '" ';
            echo 'data-version="' . esc_attr( $update['new_version'] ) . '" ';
            echo 'data-nonce="' . esc_attr( $nonce ) . '" ';
            echo 'data-update-id="' . esc_attr( $update_id ) . '" ';
            echo 'style="margin-left: 10px;">Descartar</button>';
            echo '</li>';
        }
        
        echo '</ul>';
        echo '<p>';
        echo '<button type="button" class="button button-primary lnc-dismiss-all" ';
        echo 'data-nonce="' . esc_attr( $nonce ) . '">';
        echo 'Descartar todos los avisos</button>';
        echo '</p>';
        echo '</div>';
        
        // Agregar JavaScript para manejar los botones de descarte
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Descartar aviso individual
            $('.lnc-dismiss-single').on('click', function(e) {
                e.preventDefault();
                var $button = $(this);
                var slug = $button.data('slug');
                var version = $button.data('version');
                var nonce = $button.data('nonce');
                var updateId = $button.data('update-id');
                
                $button.prop('disabled', true).text('Descartando...');
                
                $.post(ajaxurl, {
                    action: 'lnc_dismiss_update',
                    slug: slug,
                    version: version,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        $('#' + updateId).fadeOut(300, function() {
                            $(this).remove();
                            // Si no quedan más actualizaciones, ocultar el aviso completo
                            if ($('#lnc-update-notice ul li').length === 0) {
                                $('#lnc-update-notice').fadeOut(300);
                            }
                        });
                    } else {
                        alert('Error al descartar el aviso: ' + (response.data.message || 'Error desconocido'));
                        $button.prop('disabled', false).text('Descartar');
                    }
                });
            });
            
            // Descartar todos los avisos
            $('.lnc-dismiss-all').on('click', function(e) {
                e.preventDefault();
                var $button = $(this);
                var nonce = $button.data('nonce');
                
                if (!confirm('¿Estás seguro de descartar todos los avisos de actualización?')) {
                    return;
                }
                
                $button.prop('disabled', true).text('Descartando...');
                
                $.post(ajaxurl, {
                    action: 'lnc_dismiss_all_updates',
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        $('#lnc-update-notice').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert('Error al descartar los avisos: ' + (response.data.message || 'Error desconocido'));
                        $button.prop('disabled', false).text('Descartar todos los avisos');
                    }
                });
            });
        });
        </script>
        <?php
    }
}