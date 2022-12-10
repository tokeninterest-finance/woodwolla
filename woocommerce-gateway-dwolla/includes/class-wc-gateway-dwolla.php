<?php
/**
 * WooCommerce Dwolla
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Dwolla to newer
 * versions in the future. If you wish to customize WooCommerce Dwolla for your
 * needs please refer to hhttp://docs.woocommerce.com/document/dwolla/ for more information.
 *
 * @package   WC-Gateway-Dwolla
 * @author    SkyVerge
 * @copyright Copyright (c) 2013-2017, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Dwolla Base Class
 *
 * Handles all single purchases
 *
 * @since 1.1.0
 * @extends \WC_Payment_Gateway
 */
class WC_Gateway_Dwolla extends WC_Payment_Gateway {


	/* direct post endpoint */
	const ENDPOINT = 'https://www.dwolla.com/payment/request';

	/** @var string dwolla account ID to receive payments to */
	protected $account_id;

	/** @var string dwolla app consumer key */
	protected $app_key;

	/** @var string dwolla app secret key */
	protected $app_secret;

	/** @var string allow guest checkouts */
	protected $guest_checkout;

	/** @var string test mode */
	protected $testmode;

	/** @var string debug mode */
	protected $debug_mode;


	/**
	 * Load payment gateway and related settings
	 *
	 * @since 1.0.0
	 * @return \WC_Gateway_Dwolla
	 */
	public function __construct() {

		// set method info
		$this->id           = 'dwolla';
		$this->method_title = __( 'Dwolla', 'woocommerce-gateway-dwolla' );

		// allow payment icon to be modified
		$this->icon = apply_filters( 'wc_gateway_dwolla_icon', wc_dwolla()->get_plugin_url() . '/assets/images/dwolla.png' );

		// no payment fields
		$this->has_fields = false;

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables
		foreach ( $this->settings as $setting_key => $setting ) {
			$this->$setting_key = $setting;
		}

		// pay page fallback
		add_action( 'woocommerce_receipt_' . $this->id, create_function( '$order', 'echo "<p>" . __( "Thank you for your order.", "woocommerce-gateway-dwolla" ) . "</p>";' ) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'process_callback' ) );

		// save settings
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
	}


	/**
	 * Initialize payment gateway settings fields
	 *
	 * @since 1.0.0
	 */
	function init_form_fields() {

		$this->form_fields = array(

			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-dwolla' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Dwolla', 'woocommerce-gateway-dwolla' ),
				'default' => 'yes'
			),

			'title' => array(
				'title'       => __( 'Title', 'woocommerce-gateway-dwolla' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-dwolla' ),
				'default'     => __( 'Dwolla', 'woocommerce-gateway-dwolla' )
			),

			'description' => array(
				'title'       => __( 'Description', 'woocommerce-gateway-dwolla' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-dwolla' ),
				'default'     => __( "Pay using your Dwolla account", 'woocommerce-gateway-dwolla' )
			),

			'account_id' => array(
				'title'       => __( 'Dwolla Account ID', 'woocommerce-gateway-dwolla' ),
				'type'        => 'text',
				'description' => __( 'Ex. 812-111-1111' ),
				'default'     => ''
			),

			'app_key' => array(
				'title'       => __( 'App Key', 'woocommerce-gateway-dwolla' ),
				'type'        => 'text',
				'description' => __( 'Please enter your Dwolla Application\'s Client ID (Consumer Key).', 'woocommerce-gateway-dwolla' ),
				'default'     => ''
			),

			'app_secret' => array(
				'title'       => __( 'App Secret', 'woocommerce-gateway-dwolla' ),
				'type'        => 'text',
				'description' => __( 'Please enter your Dwolla Application\'s Secret String (Consumer Secret).', 'woocommerce-gateway-dwolla' )
			),

			'guest_checkout' => array(
				'title'   => __( 'Allow Guest Checkouts', 'woocommerce-gateway-dwolla' ),
				'type'    => 'checkbox',
				'label'   => __( 'Allow non-Dwolla customers to checkout with their bank account.', 'woocommerce-gateway-dwolla' ),
				'default' => 'yes'
			),

			'testmode' => array(
				'title'   => __( 'Test Mode', 'woocommerce-gateway-dwolla' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Test Mode', 'woocommerce-gateway-dwolla' ),
				'default' => 'yes'
			),

			'debug_mode' => array(
				'title'   => __( 'Debug', 'woocommerce-gateway-dwolla' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable logging to the WooCommerce log.', 'woocommerce-gateway-dwolla' ),
				'default' => 'no'
			),
		);
	}


	/**
	 * Checks for proper gateway configuration (required fields populated, etc)
	 * and that there are no missing dependencies
	 *
	 * @since 1.1.0
	 */
	public function is_available() {

		// is enabled check
		$is_available = parent::is_available();

		// proper configuration
		if ( ! $this->app_key || ! $this->app_secret|| ! $this->account_id ) {
			$is_available = false;
		}

		// are dependencies met?
		if ( count( wc_dwolla()->get_missing_dependencies() ) > 0 ) {
			$is_available = false;
		}

		return apply_filters( 'wc_gateway_dwolla_is_available', $is_available );
	}


	/**
	 * Payments for direct-post methods are automatically accepted, and the
	 * client is redirected to a 'payment' page which contains the form that
	 * collects the actual payment information and posts to the processor
	 * server
	 *
	 * @since 1.0.0
	 * @param int $order_id identifies the order
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		$response = $this->do_transaction( $order );

		if ( 'Success' == $response['Result'] ) {

			WC()->cart->empty_cart();

			// successful request, redirect user to complete payment
			return array(
				'result'   => 'success',
				'redirect' => esc_url( 'https://www.dwolla.com/payment/checkout/' . $response['CheckoutId'] ),
			);

		} else {

			$this->mark_order_as_failed( $order, $response['Message'] );
		}
	}


	/**
	 * Sends order data to Dwolla and receives a redirect URL to send user to for final checkout
	 *
	 * @since 1.0.0
	 * @param object $order WC Order instance
	 * @return object
	 */
	protected function do_transaction( $order ) {

		// setup HTTP args
		$wp_http_args = array(
			'timeout'     => apply_filters( 'wc_dwolla_timeout', 45 ), // default to 45 seconds
			'redirection' => 0,
			'httpversion' => '1.0',
			'sslverify'   => true,
			'blocking'    => true,
			'headers'     => array(
				'accept'       => 'application/json',
				'content-type' => 'application/json' ),
			'body'        => json_encode( $this->get_order_data( $order ) ),
			'cookies'     => array(),
			'user-agent'  => 'PHP ' . PHP_VERSION,
		);

		// POST request
		$response = wp_safe_remote_post( self::ENDPOINT, $wp_http_args );

		// log response
		if ( $this->debug_mode_enabled() ) {
			wc_dwolla()->log( "WP HTTP POST Response:\n" . print_r( $response, true ) );
		}

		// Check for Network timeout, etc.
		if ( is_wp_error( $response ) ) {
			return array( 'Result' => 'Failure', 'Message' => $response->get_error_message() );
		}

		// check for missing response body
		if ( ! isset( $response['body'] ) ) {
			return array( 'Result' => 'Failure', 'Message' => __( 'Response body missing', 'woocommerce-gateway-dwolla' ) );
		}

		// decode json into associative array
		$response = json_decode( $response['body'], true );

		// check for incorrectly decoded JSON
		if ( ! isset( $response['Result'] ) ) {
			return array( 'Result' => 'Failure', 'Message' => __( 'Response JSON missing Result', 'woocommerce-gateway-dwolla' ) );
		}

		return $response;
	}


	/**
	 * Generate order array for POSTing to Dwolla
	 *
	 * @link https://developers.dwolla.com/dev/pages/gateway
	 * @since 1.0.0
	 * @param \WC_Order $order WC Order object
	 * @return array
	 */
	protected function get_order_data( $order ) {

		// build order data
		$data = array(
			'key'           => $this->app_key,
			'secret'        => $this->app_secret,
			'callback'      => add_query_arg( 'wc-api', get_class( $this ), home_url( '/' ) ),
			'redirect'      => add_query_arg( array( 'dwolla' => 1 ), $this->get_return_url( $order ) ),
			'orderId'       => SV_WC_Order_Compatibility::get_prop( $order, 'id' ),

			'purchaseOrder' => array(

				'customerInfo' => array(
					'firstName' => SV_WC_Order_Compatibility::get_prop( $order, 'billing_first_name' ),
					'lastName'  => SV_WC_Order_Compatibility::get_prop( $order, 'billing_last_name' ),
					'email'     => SV_WC_Order_Compatibility::get_prop( $order, 'billing_email' ),
					'city'      => SV_WC_Order_Compatibility::get_prop( $order, 'billing_city' ),
					'state'     => SV_WC_Order_Compatibility::get_prop( $order, 'billing_state' ),
					'zip'       => SV_WC_Order_Compatibility::get_prop( $order, 'billing_postcode' ),
				),

				'destinationId' => $this->account_id,
				'discount'      => 0, // cart (e.g. product) discounts are already accounted for with each line item
				'shipping'      => number_format( $order->get_total_shipping(), 2, '.', '' ),
				'tax'           => number_format( $order->get_total_tax(), 2, '.', '' ),
				'total'         => number_format( $order->get_total(), 2, '.', '' ),
				/* translators: Placeholders: %1$s - site name, %2$s - order number */
				'notes'         => sprintf( __( '%1$s - Order %2$s', 'woocommerce-gateway-dwolla' ), esc_html( get_bloginfo( 'name' ) ), $order->get_order_number() ),

				'orderItems' => $this->get_order_item_data( $order )
			)
		);

		// test mode
		if ( $this->test_mode_enabled() ) {
			$data['test'] = 'true';
		}

		// guest checkout https://developers.dwolla.com/dev/pages/gateway/guest
		if ( $this->guest_checkout_enabled() ) {
			$data['allowFundingSources'] = 'true';
		}

		// log arguments in debug mode
		if ( $this->debug_mode_enabled() ) {
			wc_dwolla()->log( "Dwolla Data\n" . print_r( $data, true ) );
		}

		return apply_filters( 'woocommerce_dwolla_data', $data, $order, $this );
	}


	/**
	 * Get individual line item data for sending to Dwolla
	 *
	 * @since 1.1.0
	 * @param object $order WC Order object
	 * @return array
	 */
	private function get_order_item_data( $order ) {

		$items = array();

		foreach( $order->get_items() as $item_key => $item ) {

			// instantiate product for line item
			$product = $order->get_product_from_item( $item );

			// build array
			$items[] = array(
				'Name'        => ( '' != $product->get_sku() ) ? $product->get_sku() : $product->get_title(),
				'Description' => $product->get_title(),
				'Price'       => $order->get_item_total( $item ),
				'Quantity'    => $item['qty'],
			);
		}

		// add fees
		foreach ( $order->get_fees() as $fee_key => $fee ) {

			$items[] = array(
				'Name'        => $fee['name'],
				'Description' => apply_filters( 'wc_dwolla_fee_description', __( 'Order Fee', 'woocommerce-gateway-dwolla' ), $fee, $order ),
				'Price'       => $order->get_line_total( $fee ),
				'Quantity'    => 1,
			);
		}

		return $items;
	}


	/**
	 * Process Dwolla payment callback
	 *
	 * @link https://developers.dwolla.com/dev/pages/gateway
	 * @since 1.1.0
	 */
	public function process_callback() {

		// get postback data
		$data = file_get_contents( 'php://input' );

		// decode json
		$data = json_decode( $data );

		// check for errors in decoding
		if ( is_null( $data ) ) {

			if ( ! $this->debug_mode_enabled() ) {
				wc_dwolla()->log( 'JSON Decode Failed' );
			}
			die;
		}

		// log postback data if debug mode enabled
		if ( $this->debug_mode_enabled() ) {
			wc_dwolla()->log( "Callback Vars:\n" . print_r( $data, true ) );
		}

		// set vars
		$checkout_id    = ( isset( $data->CheckoutId ) ) ? $data->CheckoutId : '';
		$clearing_date  = ( isset( $data->ClearingDate ) ) ? $data->ClearingDate : __( 'N/A', 'woocommerce-gateway-dwolla' );
		$signature      = ( isset( $data->Signature ) ) ? $data->Signature : '';
		$transaction_id = ( isset( $data->TransactionId ) ) ? $data->TransactionId : __( 'N/A', 'woocommerce-gateway-dwolla' );
		$amount         = ( isset( $data->Amount ) ) ? $data->Amount : '';
		$order_id       = ( isset( $data->OrderId ) ) ? absint( $data->OrderId ) : '';
		$error          = ( isset( $data->Error ) ) ? $data->Error : '';

		// require order ID
		if ( ! $order_id ) {

			if ( $this->debug_mode_enabled() ) {
				wc_dwolla()->log( 'Order ID Missing' );
			}
			die;
		}

		// setup order
		$order = wc_get_order( $order_id );

		// check for errors
		if ( $error ) {

			$this->mark_order_as_failed( $order, $error );
			die;
		}

		// verify gateway signature
		$amount = number_format( $amount, 2 );
		$calculated_signature = hash_hmac( 'sha1', "{$checkout_id}&{$amount}", $this->app_secret );

		// fail order if signatures do not match
		if ( $signature != $calculated_signature ) {

			$this->mark_order_as_failed( $order, __( 'Signatures do not match', 'woocommerce-gateway-dwolla' ) );
			die;
		}

		// verify amount
		if ( $order->get_total() != $amount ) {

			$this->mark_order_as_failed( $order, __( 'Order amounts do not match', 'woocommerce-gateway-dwolla' ) );
			die;
		}

		// verify order has not already been completed
		if ( ! $order->needs_payment() ) {

			if ( $this->debug_mode_enabled() ) {
				wc_dwolla()->log( sprintf( "Order %s is already complete, aborting.", 'woocommerce-gateway-dwolla' ), $order->get_order_number() );
			}

			die;
		}

		// Payment completed
		/* translators: Placeholders: %1$s - transaction ID, %2$s - transaction clearing date */
		$order->add_order_note( sprintf( __( 'Dwolla Payment completed, transaction ID: %1$s, expected clearing date: %2$s', 'woocommerce-gateway-dwolla' ), $transaction_id, $clearing_date ) );
		$order->payment_complete();

		// Store Details
		update_post_meta( $order_id, 'Dwolla Checkout ID', $checkout_id );
		update_post_meta( $order_id, 'Dwolla Transaction ID', $transaction_id );
		update_post_meta( $order_id, 'Dwolla Signature', $signature );

		// send success
		header( 'HTTP/1.1 200 OK' );
	}


	/**
	 * Process Dwolla redirect by checking for errors
	 *
	 * If a customer cancels payment once redirect to Dwolla, it returns them to the redirect URL with an error query string
	 * Additionally, if the payment callback fails to process, this will add a postback=failure query string as well
	 *
	 * @link https://developers.dwolla.com/dev/pages/gateway
	 * @since 1.1.0
	 */
	public function process_redirect() {

		// clean data
		// note this must be $_REQUEST as somehow hooking in at the `wp` action
		// causes the error query string param to be removed, whereas hooking
		// in earlier at `init` has the query string, but we can't fetch the
		// order ID then
		$data = stripslashes_deep( $_REQUEST );

		// log query vars if debug mode enabled
		if ( $this->debug_mode_enabled() ) {
			wc_dwolla()->log( "Query Vars:\n" . print_r( $data, true ) );
		}

		// get order ID
		$order_id = absint( get_query_var( 'order-received' ) );

		// require order ID
		if ( ! $order_id ) {
			wp_die( __( 'Order Failed, please contact us.', 'woocommerce-gateway-dwolla' ) );
		}

		// setup order
		$order = wc_get_order( $order_id );

		// check for errors (cancelled payment, etc), only add order note if the order is not already failed (callback happens prior to this and could fail the order)
		if ( ! empty( $data['error'] ) && ! $order->has_status( 'failed' ) ) {
			$this->mark_order_as_failed( $order, $data['error_description'] );
		}

		// check for callback failure
		if ( ! empty( $data['postback'] ) && 'failure' == $data['postback'] ) {
			$this->mark_order_as_failed( $order, __( 'Payment callback failed', 'woocommerce-gateway-dwolla' ) );
		}
	}


	/**
	 * Mark the given order as failed and set the order note
	 *
	 * @since 1.1.0
	 * @param \WC_Order $order the order
	 * @param string $error_message a message to display inside the "Dwolla Payment Failed" order note
	 */
	protected function mark_order_as_failed( $order, $error_message ) {

		$order_note = sprintf( __( 'Dwolla Payment Failed (%s)', 'woocommerce-gateway-dwolla' ), $error_message );

		// Mark order as failed if not already set, otherwise, make sure we add the order note so we can detect when someone fails to check out multiple times
		if ( ! $order->has_status( 'failed' ) ) {
			$order->update_status( 'failed', $order_note );
		} else {
			$order->add_order_note( $order_note );
		}

		// show generic error message to customer
		wc_add_notice( __( 'An error occurred, please try again or try an alternate form of payment.', 'woocommerce-gateway-dwolla' ), 'error' );
	}


	/**
	 * Is guest checkout enabled?
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	protected function guest_checkout_enabled() {

		return 'yes' === $this->guest_checkout;
	}


	/**
	 * Is test mode enabled?  Flag if purchase order is for testing purposes
	 * only. Does not affect account balances and no emails are sent. The
	 * transaction ID will always be 1 in the responses.
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	protected function test_mode_enabled() {

		return 'yes' === $this->testmode;
	}


	/**
	 * Is debug mode enabled?
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	protected function debug_mode_enabled() {

		return 'yes' === $this->debug_mode;
	}


}
