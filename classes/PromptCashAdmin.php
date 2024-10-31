<?php
namespace Ekliptor\PromptCash;

class PromptCashAdmin {
	const PAGE_HOOK = 'toplevel_page_promptcash';
	
	/** @var PromptCashAdmin */
	private static $instance = null;
	
	/**
	 * Name of the page hook when the menu is registered.
	 * For example: toplevel_page_cashtippr
	 * @var string Page hook
	 */
	public $pageHook = '';
	
	/** @var TemplateEngine */
	public $tpl = null;
	
	/** @var PromptCash */
	protected $plugin;
	
	/** @var Settings */
	protected $settings = null;
	
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
		$this->settings = $this->plugin->getSettings(); // settings class created after init, better use getInstance() if WC plugin with AdminSettings
		$this->settings->setPluginClasses($this->plugin, $this);
		$this->tpl = new TemplateEngine($this->settings);
		
		// init hooks
		//add_action( 'admin_init', array( self::$instance, 'baa' ) ); // fired on every admin page (also ajax)
		add_action( 'admin_menu', array( self::$instance, 'createMenu' ), 5 ); // Priority 5, so it's called before Jetpack's admin_menu.
		add_action( 'current_screen', array( $this, 'initCurrentScreen' ), 10, 1 );
		
		//add_action( 'admin_init', array( $this, 'loadAssets' ) ); // done after screen setup
		add_action( 'admin_init', array( $this, 'displayAdminNotices' ) );
		add_action( 'admin_init', array( $this, 'addPrivacyPolicyContent' ) );
		
		add_filter('removable_query_args', array($this, 'addRemovableAdminArgs'));
		//add_filter(PromptCash::HOOK_PREFIX . '_settings_change_detect_adblock', array($this, 'onAdBlockChange'), 10, 4);
		
		do_action(PromptCash::HOOK_PREFIX . '_admin_init', $this);
	}
	
	public function createMenu() {
		/*$this->registeredPageHooks[] = */add_menu_page( __( 'Prompt.Cash', 'ekliptor' ), __( 'Prompt.Cash', 'ekliptor' ), 'manage_options', 'promptcash', array(self::$instance, 'displaySettings'), plugins_url('/img/prompt_16.png', PRCA__PLUGIN_DIR . 'prompt-cash.php'), '55.5' );
		do_action(PromptCash::HOOK_PREFIX . '_admin_menu', $this);
	}
	
	public function getPageHook(): string {
		return $this->pageHook;
	}
	
	public function getTpl(): TemplateEngine {
		return $this->tpl;
	}
	
	public function displaySettings() {
		//global $wpdb;
		include PRCA__PLUGIN_DIR . 'tpl/admin/mainSettingsWrap.php';
	}
	
	public function showAllSettings() {
		include PRCA__PLUGIN_DIR . 'tpl/admin/mainSettings.php';
	}
	
	public function initCurrentScreen(\WP_Screen $screen) {
		// id: [id] => toplevel_page_cashtippr or cashtippr_page_cashtippr_shout <- this is always the hook
		if (strpos($screen->base, 'promptcash') === false)
			return;
		$this->pageHook = $screen->base;
		
		add_action( $this->pageHook . '_settings_page_boxes', array( $this, 'showAllSettings' ) );
		// as an alternative to listen on the screen hook we could register hooks for all sub menus here
		add_action( 'load-' . $this->pageHook, array( $this, 'addMetaBoxes' ) );
		$this->loadAssets();
	}
	
	public function displayAdminNotices() {
		// warn admin about missing or invalid settings
		$publicToken = $this->settings->get('publicToken');
		$secretToken = $this->settings->get('secretToken');
		if (empty($publicToken) || empty($secretToken)) {
			$tplVars = array(
					'msg' => __('You must enter your API keys to use the Prompt.Cash plugin.', 'ekliptor'),
					'link' => admin_url() . 'admin.php?page=promptcash'
			);
			$notice = new AdminNotice($this->tpl->getTemplate('adminNotice.php', $tplVars), 'error');
			$this->tpl->addAdminNotices($notice);
		}
		else if ($this->settings->get('wooEnabledBefore') !== true && class_exists(/*'WooCommerce'*/'WC_Payment_Gateway') !== false) {
			$this->settings->set('wooEnabledBefore', true);
			// notify WC integration can be enabled the 1st time it is configured fully (API keys)
			$tplVars = array(
					'msg' => __('Congratulations! You can now enable Prompt.Cash for WooCommerce payments.', 'ekliptor'),
					'link' => admin_url() . 'admin.php?page=wc-settings&tab=checkout&section=promptcash_gateway'
			);
			$notice = new AdminNotice($this->tpl->getTemplate('adminNotice.php', $tplVars), 'error');
			$this->tpl->addAdminNotices($notice);
		}
		
		//$key = PromptCash::HOOK_PREFIX . '_notices'; // TODO would be better to use plugin-specific key everyhwere
		if (isset($_GET[PromptCash::HOOK_PREFIX . '_notices'])) {
			$notices = explode(',', $_GET[PromptCash::HOOK_PREFIX . '_notices']);
			foreach ($notices as $noticeData) {
				$notice = AdminNotice::urlDecode($noticeData);
				$this->tpl->addAdminNotices($notice);
			}
		}
		
		do_action(PromptCash::HOOK_PREFIX . '_admin_notices');
		add_action('admin_notices', array($this->tpl, 'showAdminNotices'));
	}
	
	public function loadAssets() {
		wp_enqueue_style( 'promptcash-admin', plugins_url( 'tpl/css/promptcash-admin.css', PRCA__PLUGIN_DIR . 'prompt-cash.php' ), array(), PRCA_VERSION );
		wp_enqueue_script( 'promptcash-bundle', plugins_url( 'tpl/js/bundle.js', PRCA__PLUGIN_DIR . 'prompt-cash.php' ), array('jquery'), PRCA_VERSION, false );
		add_action( "load-{$this->pageHook}", array( $this, 'addMetaboxScripts' ) );
	}
	
	public function addMetaboxScripts() {
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
	}
	
	public function addMetaBoxes(string $post_type/*, WP_Post $post*/) {
		if ($this->pageHook === static::PAGE_HOOK) {
			add_meta_box(
					'promptcash-payment-settings',
					esc_html__( 'Payment Settings', 'ekliptor' ),
					array( $this->tpl, 'showMetaboxPayment' ),
					$this->pageHook,
					'main'
				);
			add_meta_box(
					'promptcash-account-settings',
					esc_html__( 'Account Settings', 'ekliptor' ),
					array( $this->tpl, 'showMetaboxAccount' ),
					$this->pageHook,
					'main'
				);
			add_meta_box(
					'promptcash-blurryimage-settings',
					esc_html__( 'Blurry Image Settings', 'ekliptor' ),
					array( $this->tpl, 'showMetaboxBlurryImage' ),
					$this->pageHook,
					'main'
				);
			add_meta_box(
					'promptcash-advanced-settings',
					esc_html__( 'Advanced Settings', 'ekliptor' ),
					array( $this->tpl, 'showMetaboxAdvanced' ),
					$this->pageHook,
					'main'
				);
		}
    }
    
    public function addRemovableAdminArgs(array $removable_query_args) {
    	array_push($removable_query_args, PromptCash::HOOK_PREFIX . '_notices');
    	return $removable_query_args;
    }
    
    public function addPrivacyPolicyContent() {
    	if ( ! function_exists( 'wp_add_privacy_policy_content' ) )
    		return;
    	$content = sprintf(
        	__( 'This website uses cookies to track recurring visitors and their previous donations/payments.
				Additionally it sends personal data such as IP addresses to the API service at prompt.cash. Privacy policy: https://prompt.cash/terms',
        			'ekliptor' )
    	);
    	wp_add_privacy_policy_content('Prompt.Cash', wp_kses_post( wpautop( $content, false ) ) );
    }
}
?>