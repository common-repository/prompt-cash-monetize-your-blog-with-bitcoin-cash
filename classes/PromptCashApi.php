<?php
namespace Ekliptor\PromptCash;

class PromptCashApiRes {
	public $error = false;
	public $errorMsg = '';
	public $data = array();
	
	public function setError(string $msg/*, int $code*/) {
		$this->error = true;
		$this->errorMsg = $msg;
	}
}

class PromptCashApi {
	/** @var PromptCashApi */
	private static $instance = null;
	/** @var PromptCash */
	protected $plugin;
	
	private function __construct(PromptCash $plugin) {
		if ($plugin === null)
			throw new \Error("Main plugin class must be provided in constructor of " . get_class($this));
		$this->plugin = $plugin;
	}
	
	public static function getInstance(PromptCash $plugin = null) {
		if (self::$instance === null)
			self::$instance = new self($plugin);
		return self::$instance;
	}
	
	public function init() {
		// init hooks
		$amountParam = array(
						'required' => true,
						'type' => 'number', // valid types: array, boolean, integer, number, string
						'sanitize_callback' => array( self::$instance, 'sanitizeFloatParam' ),
						'description' => __( 'The amount that has been paid.', 'ekliptor' ),
					);
		$currencyParam = array(
						'required' => true,
						'type' => 'string',
						'sanitize_callback' => array( self::$instance, 'sanitizeStringParam' ),
						'description' => __( 'The currency this payment was made in.', 'ekliptor' ),
					);
		$postIDParam = array(
						'required' => true,
						'type' => 'string',
						'sanitize_callback' => array( self::$instance, 'sanitizeStringParam' ),
						'description' => __( 'The Post ID of the WP post (or page) this payment was made.', 'ekliptor' ),
					);
		$txIDParam = array(
						'required' => true,
						'type' => 'string',
						'sanitize_callback' => array( self::$instance, 'sanitizeStringParam' ),
						'description' => __( 'The transaction ID of the gateway.', 'ekliptor' ),
					);
		$typeParam = array(
						'required' => false,
						'type' => 'string',
						'default' => 'WP', // see rest_get_allowed_schema_keywords()
						'sanitize_callback' => array( self::$instance, 'sanitizeStringParam' ),
						'validate_callback' => array( self::$instance, 'validatePaymentTypeParam' ),
						'description' => __( 'The payment type: WP|WC|RCP', 'ekliptor' ),
					);
		register_rest_route( 'promptcash/v1', '/register-payment', array(
			array(
				'methods' => \WP_REST_Server::READABLE,
				'permission_callback' => array( self::$instance, 'apiPermissionCallback' ),
				'callback' => array( self::$instance, 'registerPayment' ),
				'args' => array(
					'amount' => $amountParam,
					'currency' => $currencyParam,
					'postID' => $postIDParam,
					'txID' => $txIDParam,
					'type' => $typeParam,
				)
			)
		) );
	}
	
	public function registerPayment(\WP_REST_Request $request) {
		$type = $request->get_param('type');
		if ($type === 'WC')
			return $this->registerWoocommercePayment($request);
		else if ($type === 'RCP')
			return $this->registerRestrictContentProPayment($request);
		
		$response = new PromptCashApiRes();
		//$gateway = $this->plugin->getGateway();
		//$gateway->getPayFrameUrl($postID, $amount, $currency);
		// TODO validate with prompt.cash REST API that this payment has really been made
		
		//$postID = (int)$request->get_param('postID');
		$buttonIdStr = $request->get_param('postID'); // TODO can also be txID
		$buttonId = new PaymentButtonID($buttonIdStr);
		$sessionID = isset($_COOKIE[PromptCash::SESSION_COOKIE_NAME]) ? sanitize_text_field($_COOKIE[PromptCash::SESSION_COOKIE_NAME]) : '';
		$currentAmount = $request->get_param('amount');
		try {
			$payment = new Payment($currentAmount, $request->get_param('currency'), $request->get_param('txID'), $buttonId);
			if ($payment->addPaymentToSession($sessionID) !== true) {
				$response->error = true;
				$response->errorMsg = 'Error adding payment';
			}
		}
		catch (\Exception $e) {
			$response->error = true;
			$response->errorMsg = 'Exception while adding payment';
		}
		
		if ($response->error !== true) {
			// update the post's total received donations
			if ($buttonId->getPostID() !== 0) {
				$tipAmount = (float)get_post_meta($buttonId->getPostID(), 'prompt_tip_amount', true);
				$tipAmount += $currentAmount;
				update_post_meta($buttonId->getPostID(), 'prompt_tip_amount', $tipAmount); // always unique, not like add_post_meta
			}
			
			// inc total number & amount of tips
			$settings = $this->plugin->getSettings();
			$settings->setMultiple(array(	
				'tips' => $settings->get('tips') + 1,
				'tip_amount' => $settings->get('tip_amount') + $currentAmount
			));
		}
		
		$wpRes = rest_ensure_response($response);
		$this->addNoCacheHeaders($wpRes);
		return $wpRes;
	}
	
	public function apiPermissionCallback(\WP_REST_Request $request) {
		return true; // everyone can access this for now
	}
	
	public function sanitizeStringParam( $value, \WP_REST_Request $request, $param ) {
		return trim( $value );
	}
	
	public function sanitizeFloatParam( $value, \WP_REST_Request $request, $param ) {
		return (float)trim( $value );
	}
	
	public function sanitizeIntParam( $value, \WP_REST_Request $request, $param ) {
		return (int)trim( $value );
	}
	
	public function validatePaymentTypeParam( $value, \WP_REST_Request $request, $param ) {
		$type = trim( $value );
		switch ($type) {
			case 'WP':
			case 'WC':
				return true;
		}
		return new \WP_Error("Invalid value for 'type' parameter.");
	}
	
	protected function registerWoocommercePayment(\WP_REST_Request $request) {
		$response = new PromptCashApiRes();
		if (class_exists('Ekliptor\PromptCash\WcGateway') === false) {
			$response->setError('WooCommerce is not installed.');
			$wpRes = rest_ensure_response($response);
			$this->addNoCacheHeaders($wpRes);
			return $wpRes;
		}
		
		$order = null;
		try {
			$orderID = $request->get_param('postID');
			$orderID = explode('-', $orderID); // there is only 1 order per page, so 2nd param (counter is 0)
			$order = new \WC_Order($orderID[0]);
		}
		catch (\Exception $e) { // invalid order exception
			$response->setError('There is no order with this ID');
			$wpRes = rest_ensure_response($response);
			$this->addNoCacheHeaders($wpRes);
			return $wpRes;
		}
		
		$wcGateway = WcGateway::getInstance();
		if ($wcGateway->checkOrderPaid($order) === false) {
			$response->setError('This order has not yet been paid');
		}
		
		$wpRes = rest_ensure_response($response);
		$this->addNoCacheHeaders($wpRes);
		return $wpRes;
	}
	
	protected function registerRestrictContentProPayment(\WP_REST_Request $request) {
		$response = new PromptCashApiRes();
		// is processed in process_webhooks() of their implementation
		PromptCash::notifyErrorExt("Received unexpected Ajax callback for RestrictContentPro", "");
		$wpRes = rest_ensure_response($response);
		$this->addNoCacheHeaders($wpRes);
		return $wpRes;
	}
	
	protected function addNoCacheHeaders(\WP_REST_Response $wpRes) {
		$wpRes->header('Cache-Control', 'no-cache, private, must-revalidate, max-stale=0, post-check=0, pre-check=0, no-store');
	}
}
?>