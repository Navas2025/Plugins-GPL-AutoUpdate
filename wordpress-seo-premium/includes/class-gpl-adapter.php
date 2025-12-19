<?php
/**
 * GPL License Adapter for Yoast SEO Premium
 *
 * This adapter bypasses license validation and enables all premium features
 * as per GPL licensing requirements.
 *
 * @package WPSEO\Premium
 * @since 26.4
 */

if ( ! defined( 'WPSEO_VERSION' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit();
}

/**
 * GPL Adapter Class
 */
class WPSEO_GPL_Adapter {
	
	/**
	 * Instance of this class
	 *
	 * @var WPSEO_GPL_Adapter
	 */
	protected static $instance = null;
	
	/**
	 * Returns the instance of this class
	 *
	 * @return WPSEO_GPL_Adapter
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_filters();
		$this->init_actions();
	}
	
	/**
	 * Initialize filters to bypass license checks
	 */
	private function init_filters() {
		// License validation filters
		add_filter( 'wpseo_premium_has_valid_license', '__return_true', 999 );
		add_filter( 'wpseo_license_is_valid', '__return_true', 999 );
		add_filter( 'yoast_seo_license_active', '__return_true', 999 );
		add_filter( 'wpseo_has_valid_subscription', '__return_true', 999 );
		
		// Premium features enablement
		add_filter( 'wpseo_enable_premium_features', '__return_true', 999 );
		add_filter( 'wpseo_premium_redirect_manager_enabled', '__return_true', 999 );
		add_filter( 'wpseo_premium_ai_generator_enabled', '__return_true', 999 );
		add_filter( 'wpseo_premium_internal_linking_enabled', '__return_true', 999 );
		add_filter( 'wpseo_premium_orphaned_content_enabled', '__return_true', 999 );
		add_filter( 'wpseo_premium_insights_enabled', '__return_true', 999 );
		
		// Update mechanism
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'modify_update_transient' ), 999 );
		
		// License status display
		add_filter( 'wpseo_license_status_message', array( $this, 'license_status_message' ), 999 );
	}
	
	/**
	 * Initialize actions
	 */
	private function init_actions() {
		// Remove license check actions
		add_action( 'init', array( $this, 'remove_license_checks' ), 1 );
		
		// Add GPL information
		add_action( 'admin_init', array( $this, 'add_gpl_info' ) );
	}
	
	/**
	 * Remove all license check hooks
	 */
	public function remove_license_checks() {
		remove_all_actions( 'wpseo_check_license' );
		remove_all_actions( 'wpseo_validate_license' );
		remove_all_filters( 'wpseo_require_license' );
	}
	
	/**
	 * Modify the update transient for GPL distribution
	 *
	 * @param object $transient The update plugins transient.
	 * @return object Modified transient.
	 */
	public function modify_update_transient( $transient ) {
		if ( empty( $transient ) || ! is_object( $transient ) ) {
			return $transient;
		}
		
		// GPL updates should be handled through the GPL distribution channel
		// This prevents conflicts with official update mechanisms
		
		return $transient;
	}
	
	/**
	 * Customize license status message
	 *
	 * @param string $message Original message.
	 * @return string Modified message.
	 */
	public function license_status_message( $message ) {
		return sprintf(
			/* translators: %s: GPL version */
			esc_html__( 'GPL Version %s - All premium features enabled', 'wordpress-seo-premium' ),
			WPSEO_VERSION
		);
	}
	
	/**
	 * Add GPL information to admin
	 */
	public function add_gpl_info() {
		// Store GPL activation status
		if ( ! get_option( 'wpseo_gpl_activated', false ) ) {
			update_option( 'wpseo_gpl_activated', true );
			update_option( 'wpseo_gpl_version', WPSEO_VERSION );
		}
	}
}

// Initialize GPL adapter
WPSEO_GPL_Adapter::get_instance();
