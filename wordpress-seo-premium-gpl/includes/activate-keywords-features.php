<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Activación completa de funcionalidades de meta palabras clave y análisis premium
 * Este archivo asegura que todas las funcionalidades de Yoast SEO Premium
 * estén completamente activadas sin restricciones, independientemente de la licencia.
 * 
 * ⭐ ADAPTADO: Integración con servidor externo de API Keys
 */
class Yoast_SEO_Premium_GPL_Keywords_Activation {
    
    public function __construct() {
        // Asegurar que las opciones de activación se establezcan temprano
        add_action('init', [$this, 'activate_all_keywords_features']);
        
        // Sobreescribir filtros de licencia y restricciones para que siempre devuelvan true/false
        add_filter('wpseo_premium_feature_available', [$this, 'override_feature_restrictions'], 99);
        add_filter('wpseo_premium_keyword_limit', [$this, 'remove_keyword_limits'], 99);
        add_filter('wpseo_license_required', [$this, 'disable_license_requirement'], 99);
        
        // Ocultar avisos de upsell y de licencia
        add_action('admin_head', [$this, 'hide_premium_ui_elements']);
    }
    
    /**
     * Activar todas las funcionalidades de palabras clave y remover restricciones visuales.
     */
    public function activate_all_keywords_features() {
        // ⭐ ADAPTADO: Solo activar si la API Key está activa
        $api_key = get_option('yoast_seo_premium_gpl_api_key', '');
        $status = get_option('yoast_seo_premium_gpl_key_status', 'inactive');
        
        if (!empty($api_key) && $status === 'active') {
            $this->update_premium_options();
        }
    }
    
    /**
     * Actualizar opciones premium para eliminar restricciones
     */
    private function update_premium_options() {
        $options = [
            'wpseo_prominent_words_enabled' => true,
            'wpseo_ai_optimization_enabled' => true,
            'wpseo_unlimited_keyword_analysis' => true,
            'wpseo_premium_keyword_features' => true,
            'wpseo_keyword_suggestions_unlimited' => true,
            'wpseo_content_analysis_enabled' => true,
            'wpseo_keyword_analysis_enabled' => true,
            'wpseo_readability_analysis_enabled' => true,
            'wpseo_premium_analysis_active' => true,
            'wpseo_advanced_analysis_enabled' => true,
            'wpseo_redirect_enabled' => true,
            'wpseo_premium_redirects_enabled' => true,
        ];
        
        foreach ($options as $option => $value) {
            update_option($option, $value);
        }
    }

    public function override_feature_restrictions( $is_available, $feature_name = null ) {
        return true;
    }
    
    public function remove_keyword_limits( $limit ) {
        return false;
    }
    
    public function disable_license_requirement( $required ) {
        return false;
    }
    
    public function hide_premium_ui_elements() {
        echo '<style type="text/css">
            #wp-admin-bar-wpseo-get-premium,
            .yst-button--upsell,
            #wpseo-new-badge-upgrade,
            .wpseo-premium-promotion,
            .wpseo-get-premium-banner,
            .wpseo-upsell-notice,
            .wpseo-premium-notice,
            .wpseo-license-expiry-notice,
            .wpseo-keyword-restriction,
            .wpseo-premium-feature-locked,
            .wpseo-upgrade-notice {
                display: none !important;
            }
        </style>';
    }
}

// Inicializar la clase
new Yoast_SEO_Premium_GPL_Keywords_Activation();

add_action('admin_init', function() {
    $activator = new Yoast_SEO_Premium_GPL_Keywords_Activation();
    $activator->activate_all_keywords_features();
}, 99);
?>