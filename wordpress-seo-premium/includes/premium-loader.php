<?php
/**
 * Yoast SEO Premium Loader - GPL Version
 *
 * This file loads the premium features for Yoast SEO Premium GPL version.
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
 * Premium Features Loader
 */
class WPSEO_Premium_Loader {
	
	/**
	 * Instance of this class
	 *
	 * @var WPSEO_Premium_Loader
	 */
	protected static $instance = null;
	
	/**
	 * Returns the instance of this class
	 *
	 * @return WPSEO_Premium_Loader
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
		$this->load_dependencies();
		$this->init_hooks();
	}
	
	/**
	 * Load required dependencies
	 */
	private function load_dependencies() {
		// Load GPL adapter
		if ( file_exists( WPSEO_PATH . 'includes/class-gpl-adapter.php' ) ) {
			require_once WPSEO_PATH . 'includes/class-gpl-adapter.php';
		}
		
		// Load premium features
		$features = array(
			'redirect-manager',
			'ai-generator',
			'internal-linking',
			'orphaned-content',
			'insights',
		);
		
		foreach ( $features as $feature ) {
			$feature_file = WPSEO_PATH . 'includes/features/' . $feature . '.php';
			if ( file_exists( $feature_file ) ) {
				require_once $feature_file;
			}
		}
	}
	
	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
	}
	
	/**
	 * Load plugin textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'wordpress-seo-premium',
			false,
			dirname( WPSEO_BASENAME ) . '/languages/'
		);
	}
	
	/**
	 * Add GPL information to plugin row
	 *
	 * @param array  $links Plugin row links.
	 * @param string $file  Plugin file.
	 * @return array Modified links.
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( WPSEO_BASENAME === $file ) {
			$links[] = '<strong>' . esc_html__( 'GPL Version', 'wordpress-seo-premium' ) . '</strong>';
		}
		
		return $links;
	}
}

// Initialize the premium loader
WPSEO_Premium_Loader::get_instance();
