<?php
/**
 * Plugin Name: Yoast SEO Premium
 * Plugin URI: https://yoast.com/wordpress/plugins/seo/
 * Description: The first true all-in-one SEO solution for WordPress, including on-page content analysis, XML sitemaps and much more.
 * Version: 26.4
 * Author: Team Yoast
 * Author URI: https://yoast.com/
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: wordpress-seo-premium
 * Domain Path: /languages/
 * Requires at least: 6.4
 * Requires PHP: 7.2.5
 * Network: true
 *
 * WC requires at least: 8.0
 * WC tested up to: 9.2
 *
 * @package WPSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Silence is golden.' );
}

// Define plugin constants
if ( ! defined( 'WPSEO_VERSION' ) ) {
	define( 'WPSEO_VERSION', '26.4' );
}

if ( ! defined( 'WPSEO_FILE' ) ) {
	define( 'WPSEO_FILE', __FILE__ );
}

if ( ! defined( 'WPSEO_PATH' ) ) {
	define( 'WPSEO_PATH', plugin_dir_path( WPSEO_FILE ) );
}

if ( ! defined( 'WPSEO_BASENAME' ) ) {
	define( 'WPSEO_BASENAME', plugin_basename( WPSEO_FILE ) );
}

/**
 * GPL Activation Notice
 * 
 * This is a GPL version of Yoast SEO Premium.
 * License updates and support are handled through GPL distribution.
 */
function wpseo_premium_gpl_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	$dismissed = get_option( 'wpseo_gpl_notice_dismissed', false );
	if ( $dismissed ) {
		return;
	}
	
	?>
	<div class="notice notice-info is-dismissible" id="wpseo-gpl-notice">
		<p>
			<strong><?php esc_html_e( 'Yoast SEO Premium - GPL Version', 'wordpress-seo-premium' ); ?></strong><br>
			<?php esc_html_e( 'You are using the GPL version of Yoast SEO Premium. This version is distributed under the GNU General Public License v3.0 or later.', 'wordpress-seo-premium' ); ?>
		</p>
	</div>
	<script>
	jQuery(document).on('click', '#wpseo-gpl-notice .notice-dismiss', function() {
		jQuery.post(ajaxurl, {
			action: 'wpseo_dismiss_gpl_notice',
			nonce: '<?php echo esc_js( wp_create_nonce( 'wpseo_gpl_notice' ) ); ?>'
		});
	});
	</script>
	<?php
}
add_action( 'admin_notices', 'wpseo_premium_gpl_notice' );

/**
 * Handle dismissal of GPL notice
 */
function wpseo_dismiss_gpl_notice() {
	check_ajax_referer( 'wpseo_gpl_notice', 'nonce' );
	
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( -1 );
	}
	
	update_option( 'wpseo_gpl_notice_dismissed', true );
	wp_die();
}
add_action( 'wp_ajax_wpseo_dismiss_gpl_notice', 'wpseo_dismiss_gpl_notice' );

/**
 * GPL License Adapter
 * 
 * This adapter ensures the plugin works without license key validation
 * as per GPL terms.
 */
if ( ! class_exists( 'WPSEO_Premium_GPL_Adapter' ) ) {
	class WPSEO_Premium_GPL_Adapter {
		
		/**
		 * Initialize GPL adapter
		 */
		public function __construct() {
			add_filter( 'wpseo_premium_has_valid_license', '__return_true', 999 );
			add_filter( 'wpseo_license_is_valid', '__return_true', 999 );
			add_filter( 'yoast_seo_license_active', '__return_true', 999 );
			add_action( 'init', array( $this, 'init' ) );
		}
		
		/**
		 * Initialize on WordPress init
		 */
		public function init() {
			// Remove license check hooks
			remove_all_actions( 'wpseo_check_license' );
			
			// Ensure updates work through GPL distribution
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_updates' ), 999 );
		}
		
		/**
		 * Check for plugin updates
		 *
		 * @param object $transient The update plugins transient.
		 * @return object Modified transient.
		 */
		public function check_updates( $transient ) {
			// GPL version update mechanism
			// Updates should be provided through the GPL distribution channel
			return $transient;
		}
	}
}

// Initialize GPL adapter
new WPSEO_Premium_GPL_Adapter();

/**
 * Load Yoast SEO Premium main functionality
 * 
 * Note: This GPL distribution includes only the core structure.
 * Full functionality requires the complete Yoast SEO Premium package.
 */
if ( file_exists( WPSEO_PATH . 'includes/premium-loader.php' ) ) {
	require_once WPSEO_PATH . 'includes/premium-loader.php';
}

/**
 * Activation hook
 */
function wpseo_premium_gpl_activate() {
	// Set activation flag
	update_option( 'wpseo_premium_gpl_activated', true );
	update_option( 'wpseo_gpl_notice_dismissed', false );
	
	// Flush rewrite rules
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wpseo_premium_gpl_activate' );

/**
 * Deactivation hook
 */
function wpseo_premium_gpl_deactivate() {
	// Clean up
	delete_option( 'wpseo_premium_gpl_activated' );
	delete_option( 'wpseo_gpl_notice_dismissed' );
	
	// Flush rewrite rules
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wpseo_premium_gpl_deactivate' );
