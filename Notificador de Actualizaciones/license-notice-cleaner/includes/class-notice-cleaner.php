<?php
/**
 * Limpiador de avisos de licencias
 * Oculta TODOS los avisos de licencias/API keys de plugins GPL/nulled
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LNC_Notice_Cleaner {

    public static function init() {
        // Ocultar avisos con CSS y JS
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
        
        // Remover acciones de avisos conocidos
        add_action( 'admin_init', array( __CLASS__, 'remove_license_notices' ), 999 );
        
        // Filtrar salida HTML para remover avisos
        add_action( 'admin_notices', array( __CLASS__, 'start_buffer' ), 0 );
        add_action( 'admin_notices', array( __CLASS__, 'end_buffer' ), 999 );
    }

    /**
     * Cargar CSS y JS para ocultar avisos
     */
    public static function enqueue_scripts() {
        // CSS para ocultar avisos
        $css = "
        /* Ocultar avisos de licencias */
        .notice[class*='license'],
        .notice[class*='activation'],
        .notice[class*='api-key'],
        .notice[class*='premium'],
        .error[class*='license'],
        .updated[class*='license'],
        div[class*='license-notice'],
        div[class*='license-warning'],
        div[class*='activation-notice'],
        .yith-plugin-fw-panel-wc-row.license,
        .yith-plugin-fw__panel__content__page__license,
        #yith-license-activation,
        .yith-license-activation-container,
        [id*='license-activation'],
        [class*='license-activation'],
        [id*='yith-license'],
        [class*='yith-license'],
        .elementor-message-dismissed,
        [data-notice*='license'],
        [data-notice*='activation'] {
            display: none !important;
        }
        
        /* Ocultar tabs de licencias */
        .nav-tab[href*='license'],
        .nav-tab[href*='activation'],
        a[href*='license-activation'] {
            display: none !important;
        }
        
        /* Ocultar modales de licencias */
        .yith-plugin-fw-modal[data-content*='license'],
        div[id*='license-modal'],
        div[class*='license-modal'] {
            display: none !important;
        }
        ";
        
        wp_add_inline_style( 'common', $css );
        
        // JavaScript para remover avisos dinámicos
        $js = "
        jQuery(document).ready(function($) {
            // Remover avisos cada 500ms
            setInterval(function() {
                // Buscar y remover avisos de licencias
                $('.notice, .error, .updated, div[class*=\"notice\"]').each(function() {
                    var text = $(this).text().toLowerCase();
                    if (text.indexOf('licen') > -1 || 
                        text.indexOf('activat') > -1 || 
                        text.indexOf('api key') > -1 || 
                        text.indexOf('premium') > -1 ||
                        text.indexOf('register') > -1) {
                        $(this).remove();
                    }
                });
                
                // Remover modales de YITH
                $('.yith-plugin-fw-modal, div[id*=\"license\"], div[class*=\"license-modal\"]').remove();
                
                // Remover overlays
                $('.yith-plugin-fw-overlay').remove();
            }, 500);
            
            // Prevenir que se abran modales de licencias
            $(document).on('click', 'a[href*=\"license\"], a[href*=\"activation\"]', function(e) {
                var href = $(this).attr('href');
                if (href && (href.indexOf('license') > -1 || href.indexOf('activation') > -1)) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
        });
        ";
        
        wp_add_inline_script( 'jquery', $js );
    }

    /**
     * Remover acciones de avisos conocidos
     */
    public static function remove_license_notices() {
        // Remover todos los hooks de admin_notices con prioridad baja
        global $wp_filter;
        
        if ( isset( $wp_filter['admin_notices'] ) ) {
            foreach ( $wp_filter['admin_notices']->callbacks as $priority => $callbacks ) {
                foreach ( $callbacks as $key => $callback ) {
                    // Buscar callbacks relacionados con licencias
                    if ( is_string( $key ) && 
                         ( strpos( $key, 'license' ) !== false || 
                           strpos( $key, 'activation' ) !== false ||
                           strpos( $key, 'yith' ) !== false ) ) {
                        unset( $wp_filter['admin_notices']->callbacks[ $priority ][ $key ] );
                    }
                }
            }
        }
    }

    /**
     * Iniciar buffer de salida
     */
    public static function start_buffer() {
        ob_start();
    }

    /**
     * Filtrar buffer de salida
     */
    public static function end_buffer() {
        $content = ob_get_clean();
        
        // Filtrar contenido que mencione licencias
        $patterns = array(
            '/<div[^>]*class="[^"]*license[^"]*"[^>]*>.*?<\/div>/is',
            '/<div[^>]*class="[^"]*activation[^"]*"[^>]*>.*?<\/div>/is',
            '/<div[^>]*id="[^"]*license[^"]*"[^>]*>.*?<\/div>/is',
            '/<div[^>]*class="[^"]*yith[^"]*license[^"]*"[^>]*>.*?<\/div>/is',
        );
        
        foreach ( $patterns as $pattern ) {
            $content = preg_replace( $pattern, '', $content );
        }
        
        echo $content;
    }
}