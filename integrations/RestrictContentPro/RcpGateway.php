<?php
namespace Ekliptor\PromptCash;

if ( !defined( 'ABSPATH' ) ) {
	exit;
}


class RcpGateway extends \RCP_Payment_Gateway {
	const GATEWAY_ID = 'prompt_cash'; // the ID within RestrictContentPro
	
	/**
	 * @var PromptCashGateway
	 */
	//protected $gateway;
	
	/**
	 * @var PromptCashGateway
	 */
	//protected $paymentPage = false;


	/**
	 * Declare feature support and set up any environment variables like API key(s), endpoint URL, etc.
	 */
	public function init() {
		// Declare feature support.
		$this->supports[] = 'one-time';
		//$this->supports[] = 'recurring';
		$this->supports[] = 'fees';
		//$this->supports[] = 'trial';
		
		// if enabled we have to submit the form using JS: $("#rcp_registration_form").submit();
		//$this->supports[] = 'gateway-submits-form';

		// Configure API. Their settings.php page only has an action rcp_misc_settings but no hook to add our own input fields.
		// But we load them from our plugin settings page.
		// rcp_is_sandbox()
		
		//$promptCash = PromptCash::getInstance();
		//$this->gateway = $promptCash->getGateway();
		
		// Add hooks
		//add_filter(PromptCash::HOOK_PREFIX . '_js_config', array($this, 'addPluginFooterCode'));
	}

	/**
	 * Interact with the payment gateway API to create a charge / subscription,
	 * or redirect to third party payment page to complete payment.
	 *
	 * Useful properties are:
	 *
	 * $this->auto_renew (bool) - Whether or not this registration has auto renew enabled.
	 * $this->initial_amount (float) - Amount to be charged today. This has fees/credits/discounts included.
	 * $this->amount (float) - Amount to be charged on renewals.
	 * $this->length (int) - Length of the membership billing cycle. So if each cycle is 1 month then this value will
	 *                       be "1" and the below value will be "month".
	 * $this->length_unit (string) - Duration unit ("day", "month", or "year").
	 * $this->payment (object) - "Pending" payment record for the payment to be made.
	 * $this->membership (RCP_Membership object) - "Pending" membership record.
	 * $this->email (string) - Email address for the customer signing up.
	 * $this->user_id (int) - ID of the user account for the customer signing up.
	 *
	 * @return void
	 */
	public function process_signup() {
		rcp_log( 'Starting to process Prompt.Cash signup.', true );
		
		/**
		 * @var \RCP_Payments $rcp_payments_db
		 */
		global $rcp_payments_db;

		//$payment_failed = false;

		// Don't use this variable. It's for backwards compatibility in some action hooks.
		$member = new \RCP_Member( $this->membership->get_customer()->get_user_id() );
		$callbackUrl = add_query_arg( 'listener', static::GATEWAY_ID, home_url( 'index.php' ) );
		
		$promptCash = PromptCash::getInstance();
		$gatway = $promptCash->getGateway();
		//$customerID = $this->membership->get_customer()->get_user_id();
		$paymentID = $this->payment->id;
		$payUrl = $gatway->getPayPageUrl($paymentID, $this->initial_amount, $this->currency,
				__('Payment', 'ekliptor'), $this->return_url, $callbackUrl);
		
		$payUrlParts = parse_url($payUrl);
		if ($payUrlParts === false || empty($payUrlParts['query']))
			$this->handle_processing_error( new \Exception( 'Invalid Prompt.Cash payment URL.' ) );
		$queryObj = array();
		parse_str($payUrlParts['query'], $queryObj);
		if (empty($queryObj) || empty($queryObj['tx_id']))
			$this->handle_processing_error( new \Exception( 'Invalid Prompt.Cash payment URL query arguments.' ) );
		$this->membership->set_gateway_subscription_id( $queryObj['tx_id'] );
		
		// send the user to our gateway
		wp_redirect( $payUrl );
		exit;

		/*
		//if ( $this->auto_renew ) {

		// What to do if part of the process fails.
		if ( $payment_failed ) {
			$error_message = __( 'An error occurred during payment' ); 

			$this->handle_processing_error( new \Exception( $error_message ) ); // This will wp_die()
		}
		*/
	}

	/**
	 * Handles the error processing.
	 *
	 * @param \Exception $exception
	 */
	protected function handle_processing_error( $exception ) {
		$this->error_message = $exception->getMessage();

		do_action( 'rcp_registration_failed', $this );

		wp_die( $exception->getMessage(), __( 'Error', 'rcp' ), array( 'response' => 401 ) );
	}

	/**
	 * Demonstrates how to add fields to the registration form, like credit card fields.
	 *
	 * @return string
	 */
	public function fields() {
		//$this->paymentPage = true; // gets loaded after page via AJAX
		// shwon in [register_form] https://help.ithemes.com/hc/en-us/articles/360049406934--register-form-
		ob_start();
		//rcp_get_template_part( 'card-form' );
		
		//$promptCash = PromptCash::getInstance();
		//$gatway = $promptCash->getGateway();
		/* // payment amount etc is not available in this function. moved to process_signup()
		$frameCfg = array(
				'url' => $gatway->getPayFrameUrl($order_id, $order->get_total(), $order->get_currency()),
				'orderID' => $order_id,
		);
		include PRCA__PLUGIN_DIR . 'tpl/client/RestrictContentPro/payFrame.php';
		*/
		return ob_get_clean();
	}

	/**
	 * Demonstrates how to check for errors on the form fields.
	 *
	 */
	public function validate_fields() {
		/*
		if ( empty( $_POST['rcp_card_number'] ) ) {
			rcp_errors()->add( 'missing_card_number', __( 'Please enter a card number', 'rcp' ), 'register' );
		}
		*/
	}

	/**
	 * Process webhooks - for logging renewal payments
	 * URL included in process_signup()
	 * https://help.ithemes.com/hc/en-us/articles/360052351054#processing-webhooks
	 * http://domain.com/index.php?listener=prompt_cash
	 *
	 * @return void
	 */
	public function process_webhooks() {
		/**
		 * @var \RCP_Payments $rcp_payments_db
		 */
		global $rcp_payments_db;
		
		if (!isset($_GET['listener']) || $_GET['listener'] !== static::GATEWAY_ID)
			die();
		
		$post = json_decode(file_get_contents('php://input'), true);
		if (empty($post))
			die();
		rcp_log( 'Received Prompt.Cash webhook: ' . print_r($post, true), true );

		$promptCash = PromptCash::getInstance();
		$promptCash->init(); // not initialized in this webhook
		$gatway = $promptCash->getGateway();
		/*
		if ($gatway->getSecretToken() !== $post['token']) { // prevent spoofing
			rcp_log( 'Prevent spoofing on Prompt.Cash webhook: token ' . $post['token'] . ' Server: ' . print_r($_SERVER, true), true );
			die();
		}*/
		
		rcp_log( 'Starting to process Prompt.Cash webhook.', true );
		
		$paymentID = $gatway->getPaymentId($post['payment']['description']);
		if ($paymentID === 0) {
			rcp_log( 'Received invalid payment ID in Prompt.Cash webhook.', true );
			die();
		}
		
		// check if paid
		if ($post['payment']['status'] !== 'PAID') {
			rcp_log( 'Received Prompt.Cash webhook with unpaid state: ' . print_r($post['payment'], true), true );
			die();
		}

		// The best way to link a webhook to associated membership is by the gateway subscription ID, assuming
		// the webhook contains this information.
		$this->membership = rcp_get_membership_by( 'gateway_subscription_id', $post['payment']['tx_id'] );

		// You will need to exit if the membership cannot be located.
		if ( empty( $this->membership ) ) {
			rcp_log( 'Unable to find member for Prompt.Cash webhook ' . print_r($post['payment'], true), true );
			die();
		}

		// Don't use this variable. It's for backwards compatibility in some action hooks.
		$member = new \RCP_Member( $this->membership->get_customer()->get_user_id() );
		
		// If payment can be confirmed now, then activate the membership by completing the pending payment.
		// This activates the membership for you automatically.
		$rcp_payments_db->update( $paymentID, array(
			'transaction_id' => $post['payment']['tx_id'],
			'status'         => 'complete'
		) );

		do_action( 'rcp_gateway_payment_processed', $member, $paymentID, $this );

		/*
		switch ( $data['event_type'] ) {

			// Successful renewal payment.
			case 'renewal_payment_success' :

				/*
				// Renew the membership.
				$this->membership->renew( true );

				// Insert a new payment record.
				$payment_id = $rcp_payments_db->insert( array(
					'transaction_type' => 'renewal',
					'user_id'          => $this->membership->get_customer()->get_user_id(),
					'customer_id'      => $this->membership->get_customer_id(),
					'membership_id'    => $this->membership->get_id(),
					'amount'           => $data['amount'], // @todo Payment amount.
					'transaction_id'   => $data['transaction_id'], // @todo Transaction ID.
					'subscription'     => rcp_get_subscription_name( $this->membership->get_object_id() ),
					'subscription_key' => $this->membership->get_subscription_key(),
					'object_type'      => 'subscription',
					'object_id'        => $this->membership->get_object_id(),
					'gateway'          => static::GATEWAY_ID, // slug
				) );

				do_action( 'rcp_webhook_recurring_payment_processed', $member, $payment_id, $this );
				do_action( 'rcp_gateway_payment_processed', $member, $payment_id, $this );

				die( 'renewal payment recorded' );
				***
				// TODO add renewals by sending customers links via email
				// all event names can be changed by our gateway
				die( 'renewal payment recorded' );

				break;

			// Renewal payment failed.
			case 'renewal_payment_failed' :

				$this->webhook_event_id = $data['transaction_id']; // @todo Set to failed transaction ID if available.

				do_action( 'rcp_recurring_payment_failed', $member, $this );

				die( 'renewal payment failed' );

				break;

			// Subscription cancelled.
			case 'subscription_cancelled' :

				// If this is a completed payment plan, we can skip any cancellation actions.
				if ( $this->membership->has_payment_plan() && $this->membership->at_maximum_renewals() ) {
					rcp_log( sprintf( 'Membership #%d has completed its payment plan - not cancelling.', $this->membership->get_id() ) );
					die( 'membership payment plan completed' );
				}

				if ( $this->membership->is_active() ) {
					$this->membership->cancel();
				}

				do_action( 'rcp_webhook_cancel', $member, $this );

				die( 'subscription cancelled' );

				break;
		}
		*/
	}
	
	/*
	public function addPluginFooterCode(array $cfg) {
		$cfg['restrictContentPro'] = array(
				//'paymentPage' => $this->paymentPage,
		);
		return $cfg;
	}
	*/
}
?>