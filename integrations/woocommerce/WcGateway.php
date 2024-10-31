<?php
namespace Ekliptor\PromptCash;


class WcGateway extends \WC_Payment_Gateway {
	const DEBUG = true;
	const PLUGIN_ID = "promptcash_gateway";
	
	/** @var WcGateway */
	private static $instance = null;
	
	
	/** @var \WC_Session|\WC_Session_Handler */
    protected $session = null;
    
    /** @var bool */
    protected $paymentOptionsShowing = false;
	
	public function __construct(/*\WcGateway $gateway*/) {
		// this gets called from Woocommerce, so make sure we cache this instance
		//static::check_plugin_activation();
		if (self::$instance === null)
			self::$instance = $this;
		
		$this->id            		= static::PLUGIN_ID;
        $this->medthod_title 		= __('Prompt.Cash Gateway', 'ekliptor');
        $this->has_fields    		= true;
        $this->method_description 	= __('Prompt.Cash payment gateway to accept Bitcoin Cash (BCH).', 'ekliptor');
        $this->icon					= plugins_url( 'img/bch_32.png', PRCA__PLUGIN_DIR . 'prompt-cash.php' );
        
        $this->init();
        
        $title = isset($this->settings['title']) ? $this->settings['title'] : $this->getFrontendDefaultTitle();
        $description = isset($this->settings['description']) ? $this->settings['description'] : $this->getFrontendDefaultDescription();
        $this->title       			= $title; // for frontend (user), also shown in WC settings
        $this->description 			= $description; // for frontend (user) // allows HTML descriptions
        //$this->order_button_text 	= "fooo..."; // TODO add option to replace the whole button HTML by overwriting parent functions
        
        $this->session 				= WC()->session; // null in WP admin
        //$this->cart    				= WC()->cart;
	}
	
	public static function getInstance(/*\WcGateway $gateway = null*/) {
		if (self::$instance === null)
			self::$instance = new self(/*$gateway*/);
		return self::$instance;
	}
	
	public function init() {
		$this->init_settings();
		$this->init_form_fields();
		
		// init hooks
		// note that functions must be public because they are called from event stack
		// call the main class which will call this plugin via our own action
		
		add_filter('woocommerce_settings_api_sanitized_fields_' . $this->id, array($this, 'onSettingsUpdate'));
		
		
		// WC gateway hooks
		//add_action('woocommerce_api_wc_coinpay', array($this, 'checkIpnResponse')); // called after payment if we use a 3rd party callback
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		
		// different WC plugins might overwrite the order details page. so register for all and only print data once with the first hook (far up on the page)
		add_action('woocommerce_order_details_before_order_table', array ($this, 'addPluginPaymentOptions' ), 100, 1);
		add_action('woocommerce_order_details_after_order_table', array ($this, 'addPluginPaymentOptions' ), 100, 1);
		add_action('woocommerce_order_details_before_order_table_items', array ($this, 'addPluginPaymentOptions' ), 100, 1);
		//add_action('woocommerce_thankyou', array ($this, 'addPluginPaymentOptions' ), 100, 1);
		add_action('woocommerce_thankyou_' . $this->id, array ($this, 'addPluginPaymentOptions' ), 100, 1);
		
		// TODO email hooks for new order?
		
		add_filter(PromptCash::HOOK_PREFIX . '_js_config', array($this, 'addPluginFooterCode'));
		
		// Crons
		// moved to Cron class
		
		if (is_admin() === true) {
			// config checks
			//if (empty($this->pluginSettings->get('gateways')))
				//$this->addSettingsUpdateErrorMessage(__('Please add at least 1 gateway to use the SSG plugin.', 'ekliptor'));
			
			//$this->displayAdminNotices();
		}
	}
	
	public static function addWoocommerceGateway(array $load_gateways) {
		$load_gateways[] = '\\Ekliptor\\PromptCash\\WcGateway';
		return $load_gateways;
	}
	
	public static function setupCronHooks() {
	}
	
	public function init_settings() {
		parent::init_settings();
		$this->enabled  = ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
	}
	
	public function init_form_fields() {
		$this->form_fields = array(
				'enabled' => array(
						'title' 		=> __('Enable Prompt.Cash Gateway', 'ekliptor'),
						'type'			=> 'checkbox',
						'description'	=> '',
						'default'		=> 'yes'
				),
				
				'title' => array(
						'title' 		=> __('Title', 'ekliptor'),
						'type'			=> 'text',
						'description'	=> __('The payment method title your customers will see on your shop.', 'ekliptor'),
						'default'		=> $this->getFrontendDefaultTitle(),
						'desc_tip'		=> true
				),
				'description' => array(
						'title' 		=> __('Description', 'ekliptor'),
						'type'			=> 'text',
						'description'	=> __('The payment method description your customers will see on your shop.', 'ekliptor'),
						'default'		=> $this->getFrontendDefaultDescription(),
						'desc_tip'		=> true
				),
				/*
				'paidMsg' => array(
						'title' 		=> __('Paid Message', 'ekliptor'),
						'type'			=> 'text',
						'description'	=> __('The message to show on the checkout page if the order has already been paid.', 'ekliptor'),
						'default'		=> __('Thanks for your payment.', 'ekliptor'),
						'desc_tip'		=> true
				),
				*/
		);
    }
    
    public function is_available() {
    	// check if API key set in main plugin
	    $promptCash = PromptCash::getInstance();
		$settings = $promptCash->getSettings();
	    if (empty($settings->get('publicToken')) || empty($settings->get('secretToken')))
			return false;
	    
    	return parent::is_available();
    }
    
    public function process_payment($order_id) {
    	if (!$this->session) { // shouldn't happen (and if it does the cart will be empty too)
    		wc_add_notice(esc_html("Your session has expired. You have not been charged. Please add your item(s) to the cart again.", "ekliptor"), 'error');
    		return;
    	}
    	// "place order" has just been clicked. This is the moment the order has been created and we can instantiate an order object by ID
    	$this->clearCustomSessionVariables(); // ensure there is no plugin data left from the previous payment
    	$this->session->set("orderID", $order_id);
    	$order = new \WC_Order($order_id);
    	
    	// TODO add setting to allow redirect to gateway (instead of iframe)
		return array(
				'result' => 'success',
				'redirect' => $this->get_return_url($order) // just redirect to the order details page, whe show our payment button(s) there
		);
	}
	
	public function onSettingsUpdate(array $settings) {
		return $settings;
	}
	
	public function addPluginPaymentOptions($order_id) {
		if ($this->paymentOptionsShowing === true)
			return;
		if (!$this->session)
			return; // shouldn't happen
		if (!$order_id) {
			if (!$this->session->get("orderID"))
				return;
			$order_id = $this->session->get("orderID");
		}
		$this->paymentOptionsShowing = true;
		try {
			$order = is_object($order_id) ? $order_id : new \WC_Order($order_id); // some hooks return the order object already
			$order_id = $order->get_id();
			$this->session->set("orderID", $order_id); // ensure it's the current order
		}
		catch (\Exception $e) { // invalid order exception
			echo esc_html('This order does not exist.', 'ekliptor') . '<br><br>';
			return;
		}
		if ($order->get_payment_method() !== static::PLUGIN_ID)
			return; // the user chose another payment method
		
		if ($order->is_paid() === true) { // moved up to show paid message
			//$msgConf = array('paid' => $this->settings['paidMsg']);
			//include PRCA__PLUGIN_DIR . 'tpl/client/woocommercepaidMsg.php';
			return;
		}
		
		$promptCash = PromptCash::getInstance();
		$gateway = $promptCash->getGateway();
		$frameCfg = array(
				'url' => $gateway->getPayFrameUrl($order_id, $order->get_total(), $order->get_currency(), __('Order', 'ekliptor')),
				'orderID' => $order_id,
		);
		include PRCA__PLUGIN_DIR . 'tpl/client/woocommerce/payFrame.php';
	}
	
	public function addPluginFooterCode(array $cfg) {
		$order = null;
		try {
			$order = new \WC_Order($this->session->get("orderID"));
		}
		catch (\Exception $e) { // invalid order exception
		}
		if ($order !== null) {
			$cfg['woocommerce'] = array(
					'paymentPage' => $this->paymentOptionsShowing === true,
					'orderID' => $order->get_id(),
					'currency' => $order->get_currency(),
					'amount' => (float)$order->get_total(),
			);
		}
		return $cfg;
	}
	
	public function checkOrderPaid(\WC_Order $order): bool {
		// query gateway REST API
		$promptCash = PromptCash::getInstance();
		$gateway = $promptCash->getGateway();
		$payment = $gateway->GetPayment($order->get_id());
		if ($payment === null)
			return false;
		if ($payment->status !== 'PAID') {
			if ($payment->status === 'EXPIRED') {
				$order->set_status('failed', __('Payment expired', 'ekliptor'));
				$order->save();
			}
			// TODO more states? see https://github.com/woocommerce/woocommerce/blob/master/includes/wc-order-functions.php#L86-L104
			return false;
		}
		
		//$order->set_status($this->getSuccessPaymentStatus(), __('Order paid via Prompt.Cash', 'ekliptor'));
		//$order->save();
		$order->add_order_note(__('Order paid via Prompt.Cash', 'ekliptor'));
		$order->payment_complete();
		
		$this->clearCustomSessionVariables();
		return true;
	}
	
	protected function clearCustomSessionVariables() {
		// shouldn't be needed since the whole session gets destroyed eventually, but let's be clean
		if (!$this->session)
			return;
		$keys = array(/*"orderID"*/); // don't remove the order ID because we need it to show success message
		foreach ($keys as $key) {
			//$this->session->__unset($key); // unreliable // TODO why?
			$this->session->set($key, null);
		}
	}
	
	protected function getFrontendDefaultTitle(): string {
		return __('Bitcoin Cash (BCH)', 'ekliptor');
	}
	
	protected function getFrontendDefaultDescription(): string {
		return __('Pay in Bitcoin Cash (BCH) instantly by just scanning a QR code.', 'ekliptor');
	}
}
?>