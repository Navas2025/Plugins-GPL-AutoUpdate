<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Activación COMPLETA de funcionalidades premium de Yoast SEO
 * Compatible con Yoast SEO Premium 26.9
 */

// ========================================
// 1. DESBLOQUEAR TODAS LAS FUNCIONALIDADES
// ========================================
add_filter('wpseo_premium_feature_available', '__return_true', 999);
add_filter('wpseo_enable_redirects', '__return_true', 999);
add_filter('wpseo_enable_workouts', '__return_true', 999);
add_filter('wpseo_premium_keyword_limit', '__return_false', 999);
add_filter('wpseo_ai_optimization_enabled', '__return_true', 999);
add_filter('wpseo_license_required', '__return_false', 999);

// ========================================
// 2. OCULTAR UPSELLS Y BADGES PREMIUM
// ========================================
add_action('admin_head', function() {
    echo '<style type="text/css">
        #wp-admin-bar-wpseo-get-premium,
        .yst-button--upsell,
        #wpseo-new-badge-upgrade,
        .wpseo-premium-promotion,
        .wpseo-get-premium-banner,
        .wpseo-upsell-notice,
        .wpseo-premium-notice,
        .yoast-notification.yoast-notification--warning,
        a[href*="yoast.com/wordpress/plugins/seo/pricing"],
        a[href*="my.yoast.com"],
        .yoast-seo-premium-badge,
        .yoast-seo-premium-upsell {
            display: none !important;
        }
    </style>';
}, 999);

// ========================================
// 3. FORZAR PREMIUM EN JAVASCRIPT
// ========================================
add_action('admin_footer', function() {
    ?>
    <script>
    (function() {
        if (typeof window.wpseoAdminGlobalL10n !== 'undefined') {
            window.wpseoAdminGlobalL10n.isPremium = true;
        }
        if (typeof window.wpseo !== 'undefined') {
            window.wpseo.isPremium = true;
        }
        if (typeof window.YoastSEO !== 'undefined') {
            window.YoastSEO.isPremium = true;
        }
    })();
    </script>
    <?php
}, 999);

// ========================================
// 4. CAPACIDADES DE USUARIO
// ========================================
add_filter('user_has_cap', function($allcaps, $caps, $args) {
    if (isset($allcaps['manage_options']) && $allcaps['manage_options']) {
        $allcaps['wpseo_manage_redirects'] = true;
        $allcaps['wpseo_edit_advanced_metadata'] = true;
    }
    return $allcaps;
}, 999, 3);