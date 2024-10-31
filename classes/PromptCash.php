<?php
namespace Ekliptor\PromptCash;

//require_once PRCA__PLUGIN_DIR . 'classes/autoload.php'; // better call this at the end of this file if needed (or outside) to avoid circular loading
require_once PRCA__PLUGIN_DIR . 'classes/Settings.php';
require_once PRCA__PLUGIN_DIR . 'classes/Sanitizer.php';
// TODO move some classes to Admin class if we really don't need them here
require_once PRCA__PLUGIN_DIR . 'classes/TemplateEngine.php';
require_once PRCA__PLUGIN_DIR . 'classes/DatabaseMigration.php';
require_once PRCA__PLUGIN_DIR . 'classes/AdminNotice.php';
require_once PRCA__PLUGIN_DIR . 'classes/Payment.php';
require_once PRCA__PLUGIN_DIR . 'classes/Cron.php';
require_once PRCA__PLUGIN_DIR . 'classes/Session/AbstractSessionHandler.php';
require_once PRCA__PLUGIN_DIR . 'classes/gateway/PromptCashGateway.php';
require_once PRCA__PLUGIN_DIR . 'classes/Session/Session.php';
require_once PRCA__PLUGIN_DIR . 'classes/Session/SessionManager.php';

// Blurry Images
require_once PRCA__PLUGIN_DIR . 'classes/blurry/AbstractImage.php';
require_once PRCA__PLUGIN_DIR . 'classes/blurry/ImageParser.php';
require_once PRCA__PLUGIN_DIR . 'classes/blurry/ImageCache.php';
require_once PRCA__PLUGIN_DIR . 'classes/blurry/ImageManipulator.php';

class PromptCash {
	const DEBUG = true;
	const HOOK_PREFIX = 'promptcash';
	const SESSION_LIFETIME_SEC = 365 * DAY_IN_SECONDS; // TODO validate max input for membership lenght
	const CLEANUP_TRANSACTIONS_H = 25;
	const SESSION_COOKIE_NAME = 'prc';
	const CONSENT_COOKIE_NAME = 'prc-ck';
	const GATEWAY_FRAME = '<iframe src="%s" scrolling="no" style="overflow: hidden" width="400" height="800"></iframe>';
	
	/** @var PromptCash */
	private static $instance = null;
	private static $prefix = "prc_";
	/** @var \WC_Log_Handler_File */
    protected static  $logger = null;
    /** @var bool */
    protected static $initDone = false;
	
	/** @var Settings */
	protected $settings;
	/** @var array URL parts of this WP site as the result of: parse_url(site_url('/')) */
	protected $siteUrlParts = null;
	/** @var Sanitizer */
	protected $sanitizer = null;
	/** @var Cron */
	protected $cron = null;
	/** @var SessionManager */
	protected $sessionManager = null;
	
	/** @var PromptCashGateway */
	protected $gateway = null;
	/** @var ImageParser */
	protected $imageParser;
	/** @var ImageCache */
	protected $imageCache;
	
	/** @var \WP_Post A reference to the post/page currently showing. */
	protected $post = null;
	/** @var array A map with the button number within each post. */
	protected $postButtnNr = array(); // (post ID, counter)
	
	private function __construct() {
		$this->imageParser = new ImageParser();
	}
	
	public static function getInstance() {
		if (self::$instance === null)
			self::$instance = new self ();
		return self::$instance;
	}
	
	public static function getTableName($tableName) {
		global $wpdb;
		return $wpdb->prefix . self::$prefix . $tableName;
	}
	
	public function init() {
		if (static::$initDone === true)
			return;
		static::$initDone = true;
		
		// if WP multisite is enabled we need these tables once per site (with prefix wp_siteNr_)
		if (is_multisite() === true && is_admin() === true) {
			$sessionTableSuccess = MysqlWpSessionHandler::createTable(static::getTableName('sessions'));
			$transactionsTableSuccess = Payment::createTransactionsTable();
			if ($sessionTableSuccess === false || $transactionsTableSuccess === false) {
				$sessionTable = static::getTableName('sessions');
				$txTable = static::getTableName('transactions');
				static::notifyErrorExt("Unable to create WP Multisite tables", "TX table $transactionsTableSuccess, Session table $sessionTableSuccess. Please ensure these tables exist: $sessionTable, $txTable", null, true);
			}
		}
		
		$siteUrl = site_url('/');
		$this->siteUrlParts = parse_url($siteUrl);
		//if (CashtipprAdmin::adminLoaded() === true) // if we are not on an admin page we don't include the source
		if (class_exists('PromptCash', false) === true) // must be delayed until init call so that source is ready
			$this->settings = Settings::getInstance($this, PromptCashAdmin::getInstance($this));
		else
			$this->settings = Settings::getInstance($this);
		$this->sanitizer = new Sanitizer();
		
		// session handler must be created before Cron
		//if (is_admin() === false) {
			$sessionHandler = null;
			if ($this->settings->get('use_memcached') === true) {
				$memcachedServer = array($this->settings->get('memcached_host'), $this->settings->get('memcached_port'));
				$sessionHandler = MemcachedSessionHandler::registerFromServers(array($memcachedServer), array(
						'prefix' => get_option('cashtippr_memcached_secret'),
						'expirationSec' => static::SESSION_LIFETIME_SEC,
						'noSideEffects' => true,
				));
			}
			else
				$sessionHandler = MysqlWpSessionHandler::register(static::getTableName('sessions'), array(
						'noSideEffects' => true,
				));
			$this->sessionManager = new SessionManager($sessionHandler, $this->settings->get('session_name'), '', false);
		//}
		$this->imageCache = new ImageCache(); // needed before cron
		$this->cron = new Cron($this, $this->settings);
		
		// load gatway after settings
		$this->gateway = new PromptCashGateway($this->settings->get('publicToken'), $this->settings->get('secretToken'), $siteUrl);
		
		// init hooks
		// note that functions must be public because they are called from event stack
		add_action('init', array(self::$instance, 'startSession'), 11);
		add_shortcode('prompt_button', array(self::$instance, 'showTipprButton'));
		add_shortcode('prompt_hide', array(self::$instance, 'showTipprButton'));
		add_shortcode('prompt_blur', array(self::$instance, 'showTipprButton'));
		
		add_action( 'wp_enqueue_scripts', array (self::$instance, 'addPluginScripts' ) );
		add_action( 'wp_footer', array(self::$instance, 'addFooterCode') );
		add_action( 'the_post', array(self::$instance, 'getCurrentPost') );
		
		//if ($this->settings->get('rate_usd_bch') === 0.0)
			//add_action ( 'shutdown', array ($this, 'updateCurrencyRates' ) );
	}
	
	public static function plugin_activation() {
		if (version_compare ( $GLOBALS ['wp_version'], PRCA__MINIMUM_WP_VERSION, '<' )) {
			load_plugin_textdomain ( 'ekliptor' );
			$message = '<strong>' . sprintf ( esc_html__ ( '%s plugin %s requires WordPress %s or higher.', 'ekliptor' ), get_class(), PRCA_VERSION, PRCA__MINIMUM_WP_VERSION ) . '</strong> ' . sprintf ( __ ( 'Please <a href="%1$s">upgrade WordPress</a> to a current version.', 'ekliptor' ), 'https://codex.wordpress.org/Upgrading_WordPress' );
			static::bailOnActivation ( $message, false );
		}
		
		// create tables
		$sessionTableSuccess = MysqlWpSessionHandler::createTable(static::getTableName('sessions'));
		$transactionsTableSuccess = Payment::createTransactionsTable();
		if ($sessionTableSuccess === false || $transactionsTableSuccess === false) {
			load_plugin_textdomain ( 'ekliptor' );
			$message = '<strong>' . esc_html__ ( 'Error creating required MySQL tables.', 'ekliptor' ) . '</strong> ' . sprintf ( __ ( 'Please ensure that you have sufficient database privileges. If you still encounter this problem afterwards, please <a href="%1$s">file a bug report</a>.', 'ekliptor' ), 'https://cashtippr.com/' );
			static::bailOnActivation ( $message, false );
		}
		$tables = get_option('promptcash_tables', array());
		if (in_array(static::getTableName('sessions'), $tables) === false)
			$tables[] = static::getTableName('sessions');
		if (in_array(static::getTableName('transactions'), $tables) === false)
			$tables[] = static::getTableName('transactions');
		update_option('promptcash_tables', $tables);
		
		// ensure directories exist
		$dataDirs = array(
				PRCA__PLUGIN_DIR . 'data',
				PRCA__PLUGIN_DIR . 'data/temp',
		);
		foreach ($dataDirs as $dir) {
			if (file_exists($dir) === true)
				continue;
			if (mkdir($dir) === false) { // TODO even though we don't create php files, using WP filesystem API would still be better
				load_plugin_textdomain ( 'ekliptor' );
				$message = '<strong>' . esc_html__ ( 'Error creating data folder.', 'ekliptor' ) . '</strong> ' . sprintf ( __ ( 'Please ensure that your WordPress installation has write permissions on the /plugins folder (0755) or create this folder manually with permissions 0755: %s', 'ekliptor' ), $dir );
				static::bailOnActivation ( $message, false );
			}
		}
		
		// === Blurry Image ===
		// ====================
		// ensure image functions exist
		if (function_exists('imagecreatefromjpeg') === false || function_exists('imagecreatefrompng') === false/* || function_exists('imagecreatefromwebp') === false*/) {
			load_plugin_textdomain ( 'ekliptor' );
			$message = '<strong>' . esc_html__ ( 'Error loading image functions.', 'ekliptor' ) . '</strong> ' . sprintf ( __ ( 'Please ensure that the latest <a href="%1$s">PHP image functions</a> exist on your server. Upgrade your PHP version or contact your hosting provider about this issue.', 'ekliptor' ), 'http://php.net/manual/en/ref.image.php' );
			static::bailOnActivation ( $message, false );
		}
		
		// create image dir
		$cacheDir = ImageCache::getCacheDir();
		if (file_exists($cacheDir) === false && @mkdir($cacheDir, 0755) === false) {
			load_plugin_textdomain ( 'ekliptor' );
			$message = '<strong>' . esc_html__ ( 'Error creating image cache folder.', 'ekliptor' ) . '</strong> ' . sprintf ( __ ( 'Please ensure that your WordPress installation has write permissions on the /uploads folder (0755) or create this folder manually with permissions 0755: %s', 'ekliptor' ), $cacheDir );
			static::bailOnActivation ( $message, false );
		}
		// ensure it's writable
		$tempFile = $cacheDir . 'temp.txt';
		if (@file_put_contents($tempFile, 'test') === false) {
			load_plugin_textdomain ( 'ekliptor' );
			$message = '<strong>' . esc_html__ ( 'Image cache folder is not writable.', 'ekliptor' ) . '</strong> ' . sprintf ( __ ( 'Please ensure that your WordPress installation has write permissions on the /uploads folder (0755) or create this folder manually with permissions 0755: %s', 'ekliptor' ), $cacheDir );
			static::bailOnActivation ( $message, false );
		}
		@unlink($tempFile);
		
		foreach ( Cron::$cron_events as $cron_event ) {
			$timestamp = wp_next_scheduled ( $cron_event );
			if (!$timestamp)
				wp_schedule_event(time(), 'daily', $cron_event);
		}
		foreach ( Cron::$cron_events_hourly as $cron_event ) {
			$timestamp = wp_next_scheduled ( $cron_event );
			if (!$timestamp)
				wp_schedule_event(time(), 'hourly', $cron_event);
		}
		
		//if (!get_option ( 'promptcash_version' )) { // first install
		//}
		update_option ( 'promptcash_version', PRCA_VERSION );
	}
	
	public static function plugin_deactivation() {
		//global $wpdb;
		// Remove any scheduled cron jobs.
		$events = array_merge(Cron::$cron_events, Cron::$cron_events_hourly);
		foreach ( $events as $cron_event ) {
			$timestamp = wp_next_scheduled ( $cron_event );
			if ($timestamp) {
				wp_unschedule_event ( $timestamp, $cron_event );
			}
		}
		
		// tables are only being dropped on uninstall. also cashtippr_settings
		
		//delete_option('promptcash_version'); // done on uninstall
	}
	
	public function getCurrentPost(\WP_Post $post_object) {
		$this->post = $post_object;
	}
	
	public function startSession() {
		/*
		MysqlWpSessionHandler::register(static::getTableName('sessions'));
		session_name($this->settings->get('session_name'));
		session_set_cookie_params(static::SESSION_LIFETIME_SEC, $this->siteUrlParts['path'], null, false, true);	
		// here we could use session_id() to set a previous session ID (provided by req params)
		$success = session_start();
		if ($success !== true)
			static::notifyErrorExt("A session has already been started by another plugin. Prompt.Cash buttons might not work properly. Please check the PHP Notices for details.", '');
		*/
		//if (is_admin() === true) // used in new editor live preview
			//return;
		
		// easy way: always start a session. otherwise we have to know if there is a tip button on the page before sending HTTP headers
		$this->sessionManager->setSessionCookieParameters(static::SESSION_LIFETIME_SEC, $this->siteUrlParts['path'], null, false, true);
		$success = $this->sessionManager->start();
		if ($success !== true)
			static::notifyErrorExt("A session has already been started by another plugin. Prompt.Cash buttons might not work properly. Please check the PHP Notices for details.", '');
		
		$session = $this->sessionManager->getCurrentSession(); // create the session
		
		/*
		pre_print_r($session);
		$session->set("foo", array(1234,5555));
		$session->set("ba", "1234");
		$this->sessionManager->storeSession($session->getId(), $session);
		*/
		
	}
	
	public function showTipprButton($attrs, $content = null, $tag = "") {
		// configure the button
		$btnConf = array();
		$btnConf['postID'] = $this->getButtonID($this->post !== null ? $this->post->ID : 0);
		$btnConf['currency'] = isset($attrs['currency']) ? $attrs['currency'] : $this->settings->get('button_currency');
		$btnConf['amount'] = isset($attrs['amount']) ? floatval($attrs['amount']) : $this->settings->get('default_amount');
		if ($btnConf['amount'] < 0.00000001)
			$btnConf['amount'] = 0.00000001;
		$btnConf['content'] = $content ? $content : '';
		$btnConf['edit'] = $this->canEditButtonAmount($attrs);
		$btnConf['btnText'] = $this->settings->get('tip_btn_txt');
		$btnConf['text'] = ''; // text beofre. currently not used
		$btnConf['restrictedTxt'] = isset($attrs['text']) && !empty($attrs['text']) ? $attrs['text'] : $this->settings->get('hide_tip_txt'); // text next to the button (hidden words)
		
		// hiding content
		$btnConf['isRestricted'] = false;
		$btnConf['restrictedTxt'] = '';
		
		//$attrs['id'] = $btnConf['postID'];
		
		// render the HTML for the tag
		ob_start();
		switch ($tag)
		{
			case 'prompt_button':
				//echo "BUTTON";
				//pre_print_r($btnConf);
				//$frameUrl = $this->gateway->getPayFrameUrl($btnConf['postID'], $btnConf['amount'], $btnConf['currency']);
				//echo sprintf(static::GATEWAY_FRAME, $frameUrl);
				//echo $this->gateway->getPayFrameUrlAnyAmount($btnConf['postID'], $btnConf['currency']);
				include PRCA__PLUGIN_DIR . 'tpl/client/button/button.php';
				break;
				
			case 'prompt_hide':
				if (empty($content)) {
					esc_html_e("Missing closing tag for shortcode: '$tag' - usage: [$tag]my hidden text[/$tag]", "ekliptor");
					echo "<br><br>";
				}
				else if (Payment::isTippedPost($this->sessionManager->getCurrentSession()->getId(), $btnConf['postID'], $btnConf['amount'], $btnConf['currency']) === true) {
					// TODO support for multiple tip buttons per post and hide them individually (supported on front end now)
					echo $content;
					//include PRCA__PLUGIN_DIR . 'tpl/client/button/button.php'; // add a normal tip button below so users can continue tipping
				}
				else {
					$btnConf['isRestricted'] = true; // must be true, just to be sure
					//if ($btnConf['text'] !== '')
						//$btnConf['text'] = $this->fillTipprButtonHiddenTextTemplate($btnConf['text'], $this->countWords($content));
					//else
						$btnConf['restrictedTxt'] = $this->fillTipprButtonHiddenTextTemplate($this->settings->get('hide_tip_txt'), $this->countWords($content));
					include PRCA__PLUGIN_DIR . 'tpl/client/button/hiddenContent.php';
				}
				break;
				
			case "prompt_blur":
				if (empty($content)) {
					esc_html_e("Missing closing tag for shortcode: '$tag' - usage: [$tag]my image to blur[/$tag]", "ekliptor");
					echo "<br><br>";
				}
				else if (Payment::isTippedPost($this->sessionManager->getCurrentSession()->getId(), $btnConf['postID'], $btnConf['amount'], $btnConf['currency']) === true) {
					// TODO support for multiple tip buttons per post and hide them individually (supported on front end now)
					echo $content;
					//include PRCA__PLUGIN_DIR . 'tpl/client/button/button.php'; // add a normal tip button below so users can continue tipping
				}
				else {
					$originalImages = $this->imageParser->parseImageTags($content);
					$blurryImages = $this->imageCache->getBlurryImages($originalImages, true);
					//pre_print_r($blurryImages);
					$content = $this->replaceClearWithBlurryUrls($content, $originalImages, $blurryImages);
					$btnConf['isRestricted'] = true; // must be true, just to be sure
					//if ($btnConf['text'] !== '')
						//$btnConf['text'] = $btnConf['text']; // no image count to fill in (yet?)
					//else
						$btnConf['restrictedTxt'] = $this->settings->get('hide_image_txt');
					include PRCA__PLUGIN_DIR . 'tpl/client/button/blurryImages.php';
				}
				break;
		}
		$docHtml = ob_get_contents();
		ob_end_clean();
		//$this->setIncludedPromptCashScript(true); // prompt.cash QR code is in an iframe
		//wp_enqueue_script( 'prompt-cash-core-js', 'https://...', array(), PRCA_VERSION, true );
		return $docHtml;
	}
	
	public function addFooterCode() {
		$cfg = array(
			'cookieLifeDays' => ceil(static::SESSION_LIFETIME_SEC / DAY_IN_SECONDS),
			'cookiePath' => $this->siteUrlParts['path'],
			'siteUrl' => $this->getSiteUrl(),
			'show_search_engines' => $this->settings->get('show_search_engines'),
			'gatewayOrigin' => PromptCashGateway::API_ENDPOINT,
			'frameUrl' => $this->gateway !== null ? $this->gateway->getNamedPayFrameUrl() : '',
			'postID' => $this->post !== null ? $this->post->ID : 0,
			'title' => $this->post !== null ? $this->post->post_title : '',
			'tr' => array(
					'order' => __('Order', 'ekliptor'),
					'post' => __('Post', 'ekliptor'),
				)
		);
		if ($this->settings->get('show_cookie_consent') === true && !isset($_COOKIE[static::CONSENT_COOKIE_NAME])) {
			// TODO add option to only show this to specific countries
			// from get_the_privacy_policy_link()
			//pre_print_r("COOKIE consent");
			$policy_page_id = (int)get_option( 'wp_page_for_privacy_policy' );
			$privacyPageTitle = $policy_page_id ? get_the_title( $policy_page_id ) : __('Privacy Policy', 'ekliptor');
			include PRCA__PLUGIN_DIR . 'tpl/cookieConfirm.php';
		}
		$cfg = apply_filters(static::HOOK_PREFIX . '_js_config', $cfg);
		echo '<script type="text/javascript">var promptCashCfg = ' . json_encode($cfg) . ';</script>';
	}
	
	public function addPluginScripts() {
		wp_enqueue_style( 'promptcash', plugins_url( 'tpl/css/promptcash.css', PRCA__PLUGIN_DIR . 'prompt-cash.php' ), array(), PRCA_VERSION );
		wp_enqueue_script( 'promptcash-bundle', plugins_url( 'tpl/js/bundle.js', PRCA__PLUGIN_DIR . 'prompt-cash.php' ), array('jquery'), PRCA_VERSION, true );
	}
	
	public function getSettings(): Settings {
		return $this->settings;
	}
	
	public static function notifyErrorExt($subject, $error, $data = null, bool $silent = false) {
		global $wpdb;
		if (defined('WC_LOG_DIR') === true) {
			if (static::$logger === null)
				static::$logger = new \WC_Log_Handler_File();
			$logMsg = $subject . "\r\n" . print_r($error, true);
			if ($data !== null)
				$logMsg .= "\r\n" . print_r($data, true);
			static::$logger->handle(time(), 'error', $logMsg, array('source' => static::HOOK_PREFIX));
		}
		if (static::DEBUG === false || $silent === true)
			return;
		$table = static::getTableName("messages_system");
		if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
			pre_print_r($subject);
			pre_print_r($error);
			if ($data !== null)
				pre_print_r($data);
			return; // table doesn't exist
		}
		if (!is_string($error))
			$error = print_r($error, true);
		$rowCount = $wpdb->insert($table, array(
				'sender' => 'SystemError',
				'subject' => $subject,
				'text' => $error,
				'data' => $data !== null ? serialize($data) : null,
				'site' => strtolower(get_bloginfo('name'))
		));
	}
	
	public function getSiteUrl(array $query = array()) {
		$url = $this->siteUrlParts['scheme'] . '://' . $this->siteUrlParts['host'];
		if (isset($this->siteUrlParts['port']))
			$url .= $this->siteUrlParts['port'];
		$url .= $this->siteUrlParts['path'];
		$first = true;
		foreach ($query as $key => $value) {
			$url .= $first === true ? '?' : '&';
			$url .= $key . '=' . urlencode($value);
			$first = false;
		}
		return $url;
	}
	
	public function getCurrentUrl() {
		global $wp;
		return home_url( add_query_arg( array(), $wp->request ) );
	}
	
	public function getGateway(): PromptCashGateway {
		return $this->gateway;
	}
	
	/**
	 * Returns the session manager.
	 * @return \Ekliptor\PromptCash\SessionManager the session manager or NULL in WP admin.
	 */
	public function getSessionManager() {
		return $this->sessionManager;
	}
	
	public function getImageCache(): ImageCache {
		return $this->imageCache;
	}
	
	public function fillTipprButtonHiddenTextTemplate(string $text, int $hiddenWords/*, string $post*/): string {
		$text = str_replace('{words}', $hiddenWords, $text);
		return $text;
	}
	
	protected function countWords(string $text): int {
		return str_word_count(wp_strip_all_tags($text, true), 0);
	}
	
	/**
	 * Get a unique ID for this button. There may be multiple buttons within
	 * the same post, so we can't use post ID.
	 * @param int $postID
	 * @return string postID-buttonNr starting at 0
	 */
	protected function getButtonID(int $postID): string {
		$buttonNr = isset($this->postButtnNr[$postID]) ? $this->postButtnNr[$postID] : 0;
		$this->postButtnNr[$postID] = $buttonNr + 1;
		return sprintf("%d-%d", $postID, $buttonNr); // see getButtonIdStr()
	}
	
	protected function canEditButtonAmount($attrs): bool {
		if (is_array($attrs) === false)
			return false;
		foreach ($attrs as $key => $value) {
			$value = strtolower($value);
			if ($value === 'edit') // attr without value = numeric array
				return true;
			if (strtolower($key) === 'edit') // attr with value = associative array
				return $value === '1' || $value === 'true';
		}
		return false;
	}
	
	protected function replaceClearWithBlurryUrls(string $content, array $originalImages, array $blurryImages): string {
		$len = min(count($originalImages), count($blurryImages)); // each link in original must hold a corresponding link in blurry
		for ($i = 0; $i < $len; $i++) {
			$original = $originalImages[$i];
			$blurry = $blurryImages[$i];
			$content = str_replace($original->src, $blurry->src, $content);
			$lenSrcSet = min(count($original->srcSet), count($blurry->srcSet));
			for ($u = 0; $u < $lenSrcSet; $u++) {
				$content = str_replace($original->srcSet[$u], $blurry->srcSet[$u], $content);
			}
		}
		return $content;
	}
	
	protected static function bailOnActivation($message, $escapeHtml = true, $deactivate = true) {
		include PRCA__PLUGIN_DIR . 'tpl/message.php';
		if ($deactivate) {
			$plugins = get_option ( 'active_plugins' );
			$thisPlugin = plugin_basename ( PRCA__PLUGIN_DIR . 'prompt-cash.php' );
			$update = false;
			foreach ( $plugins as $i => $plugin ) {
				if ($plugin === $thisPlugin) {
					$plugins [$i] = false;
					$update = true;
				}
			}
			
			if ($update) {
				update_option ( 'active_plugins', array_filter ( $plugins ) );
			}
		}
		exit ();
	}
}
?>