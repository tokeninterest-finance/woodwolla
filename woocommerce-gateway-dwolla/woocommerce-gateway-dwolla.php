<?php
/**
 * Plugin Name: WooCommerce Dwolla Gateway
 * Plugin URI: http://www.woocommerce.com/products/dwolla/
 * Description: Adds Dwolla as a payment method for customers on your WooCommerce store. SSL certificate recommended, but not required.
 * Author: SkyVerge
 * Author URI: http://www.woocommerce.com/
 * Version: 1.7.0
 * Text Domain: woocommerce-gateway-dwolla
 * Domain Path: /i18n/languages/
 *
 * Copyright: (c) 2012-2017, SkyVerge, Inc. (info@skyverge.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-Dwolla
 * @author    SkyVerge
 * @category  Payment-Gateways
 * @copyright Copyright (c) 2013-2017, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

// required functions
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'woo-includes/woo-functions.php' );
}

// plugin updates
woothemes_queue_update( plugin_basename( __FILE__ ), '96a8cdc17730ca1b50c54127a05d7fe1', '18669' );

// WC active check
if ( ! is_woocommerce_active() ) {
	return;
}

// Required library class
if ( ! class_exists( 'SV_WC_Framework_Bootstrap' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'lib/skyverge/woocommerce/class-sv-wc-framework-bootstrap.php' );
}

SV_WC_Framework_Bootstrap::instance()->register_plugin( '4.6.0', __( 'WooCommerce Dwolla Gateway', 'woocommerce-gateway-dwolla' ), __FILE__, 'init_woocommerce_gateway_dwolla', array(
	'minimum_wc_version'   => '2.5.5',
	'minimum_wp_version'   => '4.1',
	'backwards_compatible' => '4.4',
) );

function init_woocommerce_gateway_dwolla() {

/**
 * Main Plugin Class
 *
 * @since 1.1.0
 */
class WC_Dwolla extends SV_WC_Plugin {


	/** plugin version number */
	const VERSION = '1.7.0';

	/** @var WC_Dwolla single instance of this plugin */
	protected static $instance;

	/** plugin id */
	const PLUGIN_ID = 'dwolla';

	/** class to load as gateway */
	const GATEWAY_CLASS_NAME = 'WC_Gateway_Dwolla';


	/**
	 * Setup main plugin class
	 *
	 * @since 1.1.0
	 * @return \WC_Dwolla
	 */
	public function __construct() {

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION,
			array(
				'dependencies' => array( 'hash' ),
				'text_domain'  => 'woocommerce-gateway-dwolla',
			)
		);

		// include required files
		add_action( 'sv_wc_framework_plugins_loaded', array( $this, 'includes' ) );

		// handle when user cancels payment on dwolla and is redirect back to site
		add_action( 'wp', array( $this, 'process_redirect' ) );

	}


	/**
	 * Load required classes
	 *
	 * @since 1.1.0
	 */
	public function includes() {

		// gateway class
		require_once( $this->get_plugin_path() . '/includes/class-wc-gateway-dwolla.php' );

		// add to WC payment methods
		add_filter( 'woocommerce_payment_gateways', array( $this, 'load_gateway' ) );
	}


	/**
	 * Add Dwolla to the list of available payment gateways
	 *
	 * @since 1.1.0
	 * @param array $gateways
	 * @return array $gateways
	 */
	public function load_gateway( $gateways ) {

		$gateways[] = self::GATEWAY_CLASS_NAME;

		return $gateways;
	}


	/**
	 * Processes redirects from Dwolla when a customer cancels payment
	 *
	 * @since 1.1.0
	 */
	public function process_redirect() {

		if ( ! empty( $_GET['dwolla'] ) ) {

			$wc_gateway_dwolla = new WC_Gateway_Dwolla();

			$wc_gateway_dwolla->process_redirect();
		}
	}


	/** Helper methods ******************************************************/


	/**
	 * Main Dwolla Instance, ensures only one instance is/can be loaded
	 *
	 * @since 1.3.0
	 * @see wc_dwolla()
	 * @return WC_Dwolla
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * Returns the plugin name, localized
	 *
	 * @since 1.2.0
	 * @see SV_WC_Plugin::get_plugin_name()
	 * @return string the plugin name
	 */
	public function get_plugin_name() {
		return __( 'WooCommerce Dwolla Gateway', 'woocommerce-gateway-dwolla' );
	}


	/**
	 * Returns __FILE__
	 *
	 * @since 1.2.0
	 * @see SV_WC_Plugin::get_file()
	 * @return string the full path and filename of the plugin file
	 */
	protected function get_file() {
		return __FILE__;
	}


	/**
	 * Gets the plugin documentation url
	 *
	 * @since 1.2.0
	 * @see SV_WC_Plugin::get_documentation_url()
	 * @return string documentation URL
	 */
	public function get_documentation_url() {
		return 'http://docs.woocommerce.com/document/dwolla/';
	}


	/**
	 * Gets the plugin support URL
	 *
	 * @since 1.4.0
	 * @see SV_WC_Plugin::get_support_url()
	 * @return string
	 */
	public function get_support_url() {

		return 'https://woocommerce.com/my-account/tickets/';
	}


	/**
	 * Gets the gateway configuration URL
	 *
	 * @since 1.2.0
	 * @see SV_WC_Plugin::get_settings_url()
	 * @param string $_ unused
	 * @return string plugin settings URL
	 */
	public function get_settings_url( $_ = null ) {

		$section = SV_WC_Plugin_Compatibility::is_wc_version_gte_2_6() ? $this->get_id() : self::GATEWAY_CLASS_NAME;

		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . strtolower( $section ) );
	}


	/**
	 * Returns true if on the gateway settings page
	 *
	 * @since 1.2.0
	 * @see SV_WC_Plugin::is_plugin_settings()
	 * @return boolean true if on the admin gateway settings page
	 */
	public function is_plugin_settings() {

		$section = SV_WC_Plugin_Compatibility::is_wc_version_gte_2_6() ? $this->get_id() : self::GATEWAY_CLASS_NAME;

		return isset( $_GET['page'] ) && 'wc-settings' == $_GET['page'] &&
			   isset( $_GET['tab'] ) && 'checkout' == $_GET['tab'] &&
			   isset( $_GET['section'] ) && strtolower( $section ) == $_GET['section'];
	}


} // end WC_Dwolla


/**
 * Returns the One True Instance of Dwolla
 *
 * @since 1.3.0
 * @return WC_Dwolla
 */
function wc_dwolla() {
	return WC_Dwolla::instance();
}


// fire it up!
wc_dwolla();

} // init_woocommerce_gateway_dwolla()
