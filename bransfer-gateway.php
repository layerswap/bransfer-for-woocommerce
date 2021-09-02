<?php
/**
 * Plugin Name: Bransfer Payment Gateway
 * Plugin URI: http://woocommerce.com/products/woocommerce-extension/bransfer
 * Description: Take Bransfer crypto payments on your store.
 * Version: 1.0.1
 * Author: Hayk/Bransfer
 * Author URI: https://www.linkedin.com/in/hayk-gevorgyan-00509a12a/
 * Developer: Hayk/Bransfer
 * Developer URI: https://www.linkedin.com/in/hayk-gevorgyan-00509a12a/
 * Text Domain: bransfer-payment-gateway
 * Domain Path: /languages
 *
 * Copyright: (c) 2021-2022 Bransfer, Inc. (hayk@bransfer.io) and WooCommerce
 * 
 * Woo: 12345:342928dfsfhsf8429842374wdf4234sfd
 * WC requires at least: 3.0
 * WC tested up to: 5.8
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   Bransfer-Payment-Gateway
 * @author    Hayk/Bransfer
 * @category  Admin
 * @copyright * Copyright: (c) 2021-2022 Bransfer, Inc. and WooCommerce
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * This bransfer payment gateway allows to pay with crypto fast and cheap.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	/**
	 * Add the gateway to WC Available Gateways
	 * 
	 * @since 1.0.0
	 * @param array $gateways all available WC gateways
	 * @return array $gateways all WC gateways + offline gateway
	 */
	function wc_bransfer_add_to_gateways( $gateways ) {
		$gateways[] = 'WC_Bransfer_Gateway';
		return $gateways;
	}
	add_filter( 'woocommerce_payment_gateways', 'wc_bransfer_add_to_gateways' );

	/**
	 * Offline Payment Gateway
	 *
	 * Provides an Offline Payment Gateway; mainly for testing purposes.
	 * We load it later to ensure WC is loaded first since we're extending it.
	 *
	 * @class 		WC_Bransfer_Gateway
	 * @extends		WC_Payment_Gateway
	 * @version		1.0.1
	 * @package		WooCommerce/Classes/Payment
	 * @author 		Bransfer
	 */
	add_action( 'plugins_loaded', 'wc_bransfer_payment_gateway_init');

	function wc_bransfer_payment_gateway_init() {

		class WC_Bransfer_Gateway extends WC_Payment_Gateway {

			/**
			 * Logger instance
			 *
			 * @var WC_Logger
			 */
			public static $log = false;
			
			/**
			 * Constructor for the gateway.
			 */
			public function __construct() {

				$this->id                 = 'bransfer_payment_gateway';
				$this->icon 			  = plugin_dir_url( __FILE__ ) . 'images/logo.png';
				$this->method_title       = __( 'Bransfer', 'bransfer-payment-gateway' );
				$this->method_description = __( 'Allows to pay with bitcoin fast and cheap.', 'bransfer-payment-gateway' );

				// Load the settings.
				$this->init_form_fields();
				$this->init_settings();

				// Define user set variables
				$this->enabled        = $this->get_option( 'enabled' );
				$this->title          = $this->get_option( 'title' );
				$this->description    = $this->get_option( 'description' );
				$this->application_id = $this->get_option( 'application_id', $this->description );
				$this->api_token      = $this->get_option( 'api_token', $this->description );
				$this->receiver_email = $this->get_option( 'receiver_email' );

				if ( ! $this->is_valid_for_use() ) {
					$this->enabled = 'no';
				} else {
					include_once dirname( __FILE__ ) . '/includes/wc-gateway-bransfer-ipn-handler.php';
					new WC_Gateway_Bransfer_IPN_Handler( $this->receiver_email );
				}

				if ( 'yes' === $this->enabled ) {
					add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'order_received_text' ), 10, 2 );
				}

				// Actions
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			/**
			 * Return whether or not this gateway still requires setup to function.
			 *
			 * When this gateway is toggled on via AJAX, if this returns true a
			 * redirect will occur to the settings page instead.
			 *
			 * @since 3.4.0
			 * @return bool
			 */
			public function needs_setup() {
				return !empty( $this->application_id ) || !empty( $this->api_token ) || $this->enabled == false;
			}
			
			/**
			* Logging method.
			*
			* @param string $message Log message.
			* @param string $level Optional. Default 'info'. Possible values:
			*                      emergency|alert|critical|error|warning|notice|info|debug.
			*/
			public static function log( $message, $level = 'info' ) {
				if ( empty( self::$log ) ) {
					self::$log = wc_get_logger();
				}
				self::$log->log( $level, $message, array( 'source' => 'bransfer-payment-gateway' ) );
			}
			
			/**
			 * Check if this gateway is available in the user's country based on currency.
			 *
			 * @return bool
			 */
			public function is_valid_for_use() {
				return get_woocommerce_currency() == 'USD';
			}

			/**
			 * Admin Panel Options.
			 * - Options for bits like 'title' and availability on a country-by-country basis.
			 *
			 * @since 1.0.0
			 */
			public function admin_options() {
				if ( $this->is_valid_for_use() ) {
					parent::admin_options();
				} else {
					?>
					<div class="inline error">
						<p>
							<strong><?php esc_html_e( 'Gateway disabled', 'bransfer-payment-gateway' ); ?></strong>: <?php esc_html_e( 'Bransfer Payment does not support your store currency.', 										'bransfer-payment-gateway' ); ?>
						</p>
					</div>
					<?php
				}
			}

			/**
			 * Initialize Gateway Settings Form Fields
			 */
			public function init_form_fields() {

				$this->form_fields = array(

					'enabled' => array(
						'title'   => __( 'Enable/Disable', 'bransfer-payment-gateway' ),
						'type'    => 'checkbox',
						'label'   => __( 'Enable Bransfer Payment', 'bransfer-payment-gateway' ),
						'default' => 'yes'
					),

					'title' => array(
						'title'       => __( 'Title', 'bransfer-payment-gateway' ),
						'type'        => 'text',
						'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'bransfer-payment-gateway' ),
						'default'     => __( 'Bransfer Payment', 'bransfer-payment-gateway' ),
						'desc_tip'    => true,
					),

					'description' => array(
						'title'       => __( 'Description', 'bransfer-payment-gateway' ),
						'type'        => 'textarea',
						'description' => __( 'Bransfer payments with 10x faster and 5x cheaper using cryptocurrencies.', 'bransfer-payment-gateway' ),
						'default'     => __( 'Please remit payment to Store Name upon pickup or delivery.', 'bransfer-payment-gateway' ),
						'desc_tip'    => true,
					),

					'application_id' => array(
						'title'       => __( 'Application ID', 'bransfer-payment-gateway' ),
						'type'        => 'textarea',
						'description' => __( 'The Applciation ID from the Bransfer account.', 'bransfer-payment-gateway' ),
						'default'     => '',
						'desc_tip'    => true,
					),

					'api_token' => array(
						'title'       => __( 'API Token', 'bransfer-payment-gateway' ),
						'type'        => 'textarea',
						'description' => __( 'The Bransfer account API token.', 'bransfer-payment-gateway' ),
						'default'     => '',
						'desc_tip'    => true,
					),
					
					'receiver_email' => array(
						'title'       => __( 'Receiver Email', 'bransfer-payment-gateway' ),
						'type'        => 'textarea',
						'description' => __( 'The Bransfer receiver email.', 'bransfer-payment-gateway' ),
						'default'     => '',
						'desc_tip'    => true,
					),
				);
			}

			public function order_received_text( $text, $order ) {
				if ( $order && $this->id === $order->get_payment_method() ) {
					return esc_html__( 'Thank you for your payment. Your transaction has been completed, and a receipt for your purchase has been emailed to you. Log into your Bransfer account to view 						transaction details.', 'bransfer-payment-gateway' );
				}

				return $text;
			}

			/**
			 * Process the payment and return the result
			 *
			 * @param int $order_id
			 * @return array
			 */
			public function process_payment( $order_id ) {
				$order = wc_get_order( $order_id );

				 // Mark as on-hold (we're awaiting the payment)
				$order->update_status( 'on-hold', __( 'Awaiting Bransfer payment', 'bransfer-payment-gateway' ) ); // unpaid, 'on-hold'

				if ( isset( WC()->cart ) ) {
					WC()->cart->empty_cart();
				}

				if ($this->id === $order->get_payment_method()) {
					$token = 'Bearer ' . $this->api_token;
					$post_url = 'https://api.bransfer.io/api/payments';
					$post = $this->create_payment_request_content($order);

					$response = wp_remote_post( $post_url, array(
						'method' => 'POST',
						'timeout' => 0,
						'redirection' => 5,
						'httpversion' => '1.0',
						'blocking' => true,
						'headers' => array(
							'Authorization' => $token,
							'Content-Type' => 'application/json'
						),
						'body' => $post,
						'cookies' => array()
						)
					);
					error_log(print_r($responce, true));	
					if ( is_wp_error( $response ) ) {
					    $error_message = $response->get_error_message();
					    wc_add_notice( __('Http error:', 'Bransfer') . $error_message, 'error' );
					   
					    return;
					} else {
						if ($response['response']['code'] == 200) {

							$body = wp_remote_retrieve_body( $response );
							$responce_content = json_decode( $body, true );
													
							$payment_id = $responce_content['payment_id'];
							$redirect_url = $responce_content['redirect_url'];
					
							$order->update_meta_data( 'bransfer_payment_id', $payment_id );
							$order->update_meta_data( 'bransfer_payment_status', 'pending' );
							$order->save();
							$order->add_order_note('Bransfer Payment ID: ' . $payment_id);

							return array(
								'result'   => 'success',
								'redirect' => $redirect_url,
							);
						} else {
							$error_message = __('Invalid Status Code: ' . $status);
							wc_add_notice( __('Bransfer Payment Error:', 'bransfer-payment-gateway') . $error_message, 'error' );
							return;
						}
					}
				}
			}

			public function create_payment_request_content($order) {
				$application_id = $this->application_id;
				$total_amount = $order->get_total();
				$currency = get_woocommerce_currency();
				$redirect_url = $order->get_checkout_order_received_url();
				$metadata = $order->get_order_number();
				$order_ites = $order->get_items();

				$content = array(
					'application_id'=> $application_id,
					'total_amount'=> $total_amount,
					'currency'=> $currency,
					'success_redirect_url'=> $redirect_url,
					'metadata'=> $metadata
				);

				$post = json_encode($content);

				return $post;
			}
		}
	}
}