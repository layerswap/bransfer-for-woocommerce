<?php
/**
 * Handles responses from Bransfer IPN.
 *
 * @package WooCommerce\Bransfer
 * @version 3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_Bransfer_IPN_Handler class.
 */
class WC_Gateway_Bransfer_IPN_Handler {

	/**
	 * Receiver email address to validate.
	 *
	 * @var string Receiver email address.
	 */
	protected $receiver_email;

	/**
	 * Constructor.
	 *
	 * @param string $receiver_email Email to receive IPN from.
	 */
	public function __construct( $receiver_email = '' ) {
		add_action( 'woocommerce_api_wc_gateway_bransfer', array( $this, 'check_response' ) );
		add_action( 'valid-bransfer-standard-ipn-request', array( $this, 'valid_response' ) );

		$this->receiver_email = $receiver_email;
	}

	/**
	 * Check for Bransfer IPN Response.
	 */
	public function check_response() {
		$data = json_decode(file_get_contents('php://input'), true);
		
		if (!empty( $data )) {
			$posted = wp_unslash( $data );
			
			do_action( 'valid-bransfer-standard-ipn-request', $posted );
			exit;
		}

		//wp_die( 'Bransfer IPN Request Failure', 'Bransfer IPN', array( 'response' => 500 ) );
	}

	/**
	 * There was a valid response.
	 *
	 * @param  array $posted Post data after wp_unslash.
	 */
	public function valid_response( $posted ) {
        
		$order = wc_get_order( $posted['metadata'] );
		
		if ( $order ) {
			// Lowercase returned variables.
			$posted['status'] = strtolower( $posted['status'] );
			
			WC_Bransfer_Gateway::log( 'Found order #' . $order->get_id() );
			WC_Bransfer_Gateway::log( 'Payment status: ' . $posted['status'] );

			if ( method_exists( $this, 'payment_status_' . $posted['status'] ) ) {
				call_user_func( array( $this, 'payment_status_' . $posted['status'] ), $order, $posted );
			}
		}
	}

	/**
	 * Check payment amount from IPN matches the order.
	 *
	 * @param WC_Order $order  Order object.
	 * @param int      $amount Amount to validate.
	 */
	protected function validate_amount( $order, $amount ) {
		if ( number_format( $order->get_total(), 2, '.', '' ) !== number_format( $amount, 2, '.', '' ) ) {
			WC_Bransfer_Gateway::log( 'Payment error: Amounts do not match (gross ' . $amount . ')' );

			/* translators: %s: Amount. */
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: Bransfer amounts do not match (gross %s).', 'woocommerce' ), $amount ) );
			exit;
		}
	}

	/**
	 * Handle a completed payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $posted Posted data.
	 */
	protected function payment_status_completed( $order, $posted ) {
		if ( $order->has_status( wc_get_is_paid_statuses() ) ) {
			WC_Bransfer_Gateway::log( 'Aborting, Order #' . $order->get_id() . ' is already complete.' );
			exit;
		}
		
		$this->validate_amount( $order, $posted['amount'] );
		$this->save_bransfer_meta_data( $order, $posted );
	
		if ( $order->has_status( 'cancelled' ) ) {
			$this->payment_status_paid_cancelled_order( $order, $posted );
		}

        $metadata = $posted['metadata'];
        $order = wc_get_order( $metadata );
        $status = $posted['status'];

        $payment_id = $posted['payment_id'];
        $order_payment_id = $order->get_meta( 'bransfer_payment_id', true );

        if ($payment_id == $order_payment_id)
        {
            $order->add_order_note( __('Bransfer Payment Completed', 'bransfer-payment-gateway') );	
          
    		$this->payment_complete( $order, ( ! empty( $posted['payment_id'] ) ? wc_clean( $posted['payment_id'] ) : '' ), __( 'IPN payment completed', 'woocommerce' ) );
        }
	}

	/**
	 * Complete order, add transaction ID and note.
	 *
	 * @param  WC_Order $order Order object.
	 * @param  string   $txn_id Transaction ID.
	 * @param  string   $note Payment note.
	 */
	protected function payment_complete( $order, $txn_id = '', $note = '' ) {
		if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
			$order->add_order_note( $note );
			$order->payment_complete( $txn_id );

			if ( isset( WC()->cart ) ) {
				WC()->cart->empty_cart();
			}
		}
	}

	/**
	 * Hold order and add note.
	 *
	 * @param  WC_Order $order Order object.
	 * @param  string   $reason Reason why the payment is on hold.
	 */
	protected function payment_on_hold( $order, $reason = '' ) {
		$order->update_status( 'on-hold', $reason );

		if ( isset( WC()->cart ) ) {
			WC()->cart->empty_cart();
		}
	}
	
    /**
	 * Handle a failed payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $posted Posted data.
	 */
	protected function payment_status_failed( $order, $posted ) {
		/* translators: %s: payment status. */
		$order->update_status( 'failed', sprintf( __( 'Payment %s via IPN.', 'woocommerce' ), wc_clean( $posted['status'] ) ) );
		
		if ( ! empty( $posted['status'] ) ) {
			update_post_meta( $order->get_id(), 'bransfer_payment_status', wc_clean( $posted['status'] ) );
		}
	}

	/**
	 * Handle a denied payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $posted Posted data.
	 */
	protected function payment_status_denied( $order, $posted ) {
		$this->payment_status_failed( $order, $posted );
	}

	/**
	 * Handle an expired payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $posted Posted data.
	 */
	protected function payment_status_expired( $order, $posted ) {
		$this->payment_status_failed( $order, $posted );
	}

	/**
	 * Handle a voided payment.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $posted Posted data.
	 */
	protected function payment_status_voided( $order, $posted ) {
		$this->payment_status_failed( $order, $posted );
	}

	/**
	 * When a user cancelled order is marked paid.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $posted Posted data.
	 */
	protected function payment_status_paid_cancelled_order( $order, $posted ) {
		$this->send_ipn_email_notification(
			/* translators: %s: order link. */
			sprintf( __( 'Payment for cancelled order %s received', 'woocommerce' ), '<a class="link" href="' . esc_url( $order->get_edit_order_url() ) . '">' . $order->get_order_number() . '</a>' ),
			/* translators: %s: order ID. */
			sprintf( __( 'Order #%s has been marked paid by Bransfer IPN, but was previously cancelled. Admin handling required.', 'woocommerce' ), $order->get_order_number() )
		);
	}

	/**
	 * Handle a refunded order.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $posted Posted data.
	 */
	protected function payment_status_refunded( $order, $posted ) {
		// Only handle full refunds, not partial.
		if ( $order->get_total() === wc_format_decimal( $posted['mc_gross'] * -1, wc_get_price_decimals() ) ) {

			/* translators: %s: payment status. */
			$order->update_status( 'refunded', sprintf( __( 'Payment %s via IPN.', 'woocommerce' ), strtolower( $posted['payment_status'] ) ) );

			$this->send_ipn_email_notification(
				/* translators: %s: order link. */
				sprintf( __( 'Payment for order %s refunded', 'woocommerce' ), '<a class="link" href="' . esc_url( $order->get_edit_order_url() ) . '">' . $order->get_order_number() . '</a>' ),
				/* translators: %1$s: order ID, %2$s: reason code. */
				sprintf( __( 'Order #%1$s has been marked as refunded - Bransfer reason code: %2$s', 'woocommerce' ), $order->get_order_number(), $posted['reason_code'] )
			);
		}
	}

	/**
	 * Handle a reversal.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $posted Posted data.
	 */
	protected function payment_status_reversed( $order, $posted ) {
		/* translators: %s: payment status. */
		$order->update_status( 'on-hold', sprintf( __( 'Payment %s via IPN.', 'woocommerce' ), wc_clean( $posted['payment_status'] ) ) );

		$this->send_ipn_email_notification(
			/* translators: %s: order link. */
			sprintf( __( 'Payment for order %s reversed', 'woocommerce' ), '<a class="link" href="' . esc_url( $order->get_edit_order_url() ) . '">' . $order->get_order_number() . '</a>' ),
			/* translators: %1$s: order ID, %2$s: reason code. */
			sprintf( __( 'Order #%1$s has been marked on-hold due to a reversal - Bransfer reason code: %2$s', 'woocommerce' ), $order->get_order_number(), wc_clean( $posted['reason_code'] ) )
		);
	}

	/**
	 * Handle a cancelled reversal.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $posted Posted data.
	 */
	protected function payment_status_canceled_reversal( $order, $posted ) {
		$this->send_ipn_email_notification(
			/* translators: %s: order link. */
			sprintf( __( 'Reversal cancelled for order #%s', 'woocommerce' ), $order->get_order_number() ),
			/* translators: %1$s: order ID, %2$s: order link. */
			sprintf( __( 'Order #%1$s has had a reversal cancelled. Please check the status of payment and update the order status accordingly here: %2$s', 'woocommerce' ), $order->get_order_number(), 					esc_url( $order->get_edit_order_url() ) )
		);
	}

	/**
	 * Save important data from the IPN to the order.
	 *
	 * @param WC_Order $order  Order object.
	 * @param array    $posted Posted data.
	 */
	protected function save_bransfer_meta_data( $order, $posted ) {
		if ( ! empty( $posted['payment_id'] ) ) {
			update_post_meta( $order->get_id(), 'bransfer_payment_id', wc_clean( $posted['payment_id'] ) );
		}
		if ( ! empty( $posted['status'] ) ) {
			update_post_meta( $order->get_id(), 'bransfer_payment_status', wc_clean( $posted['status'] ) );
		}
	}

	/**
	 * Send a notification to the user handling orders.
	 *
	 * @param string $subject Email subject.
	 * @param string $message Email message.
	 */
	protected function send_ipn_email_notification( $subject, $message ) {
		$new_order_settings = get_option( 'woocommerce_new_order_settings', array() );
		$mailer             = WC()->mailer();
		$message            = $mailer->wrap_message( $subject, $message );

		$woocommerce_bransfer_settings = get_option( 'woocommerce_bransfer_settings' );
		if ( ! empty( $woocommerce_bransfer_settings['ipn_notification'] ) && 'no' === $woocommerce_bransfer_settings['ipn_notification'] ) {
			return;
		}

		$mailer->send( ! empty( $new_order_settings['recipient'] ) ? $new_order_settings['recipient'] : get_option( 'admin_email' ), strip_tags( $subject ), $message );
	}
}