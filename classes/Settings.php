<?php
namespace Ekliptor\PromptCash;

class Settings {
	/** @var Settings */
	private static $instance = null;
	
	/** @var PromptCash */
	protected $plugin;
	/** @var PromptCashAdmin */
	protected $pluginAdmin;
	
	/** @var string Settings field */
	public  $settingsField;
	
	/** @var array map with options cached after DB load */
	protected $options = null;
	
	/** @var bool Indicates if the admin is currently saving/updating settings. Used speed up plugin and skip sanitization otherwise. */
	protected $isSaving = false;
	
	public static function getInstance(PromptCash $plugin = null, PromptCashAdmin $pluginAdmin = null) {
		if (self::$instance === null)
			self::$instance = new self($plugin, $pluginAdmin);
		else if ($plugin !== null)
			self::$instance->setPluginClasses($plugin, $pluginAdmin);
		return self::$instance;
	}
	
	public function __construct(PromptCash $plugin, PromptCashAdmin $pluginAdmin = null) {
		$this->settingsField = "promptcash_settings";
		$this->plugin = $plugin;
		$this->pluginAdmin = $pluginAdmin;
		
		//add_action('init', array($this, 'settingsInit'), 10);
		add_action( 'admin_init', array( $this, 'settingsAdminInit' ), 10 );
		add_action( 'admin_init', array( $this, 'registerSettings' ), 5 );
	}
	
	public function setPluginClasses(PromptCash $plugin, PromptCashAdmin $pluginAdmin = null) {
		if ($this->plugin === null)
			$this->plugin = $plugin;
		if ($this->pluginAdmin === null)
			$this->pluginAdmin = $pluginAdmin;
	}
	
	/*
	public function settingsInit() {
		// nothing to do yet
	}
	*/
	
	public function settingsAdminInit() {
		if ( 'options.php' === $GLOBALS['pagenow'] )
			$this->handleUpdatePost();
	}
	
	public function registerSettings() {
		//* If the settings field doesn't exist, we can't register it.
		if (!$this->settingsField )
			return;

		$defaults = array(
			'type'              => 'string', // not string, an associative array containing all settings
			'group'             => $this->settingsField,
			'description'       => '',
			'sanitize_callback' => array($this, 'sanitize'),
			'show_in_rest'      => false,
			'default'			=> ''
		);
		register_setting( $this->settingsField, $this->settingsField, $defaults );
		add_option( $this->settingsField, $this->getDefaultSiteOptions() );

		//$this->checkOptionsReset(); // TODO add resetToDefault
	}
	
	public function sanitize($settings) {
		if ($this->isSaving === false) // this is also always called once when loding this plugin settings page
			return $settings;
		static $sanitized = false; // called twice on save (so 3x in total)
		if ($sanitized === true)
			return $settings;
		$sanitized = true;
		
		$defaultSanitizer = new Sanitizer();
		$allSettings = $this->getAll();
		$defaults = $this->getDefaultSiteOptions(true);
		$sanitizer = $this->getOptionSanitizers();
		// meta parameter one level above the registered cashtippr_settings array
		$allCheckboxes = isset($_POST['all_checkboxes']) ? explode(',', sanitize_text_field($_POST['all_checkboxes'])) : array();
		$allCheckboxes = array_flip($allCheckboxes);
		$allMultiselect = isset($_POST['all_multiselect']) ? explode(',', sanitize_text_field($_POST['all_multiselect'])) : array();
		$allMultiselect = array_flip($allMultiselect);
		
		$allMulticheck = isset($_POST['all_multicheck']) ? explode(',', sanitize_text_field($_POST['all_multicheck'])) : array();
		$allMulticheck = array_flip($allMulticheck);
		
		// sanitize & add missing keys
		foreach ($defaults as $key => $value) {
			if (!isset($settings[$key])) {
				if (isset($allCheckboxes[$key]))
					$settings[$key] = false; // unchecked checkboxes are not present in html forms , so do this BEFORE falling back to prev setting
				else if (isset($allMultiselect[$key]))
					$settings[$key] = array();
				else if (isset($allMulticheck[$key]))
					$settings[$key] = isset($_POST[$key]) ? $defaultSanitizer->formatAssocArray($_POST[$key], $key) : array();
					//$settings[$key] = isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : array();
				else if (isset($allSettings[$key]))
					$settings[$key] = $allSettings[$key]; // keep the previous value
				else
					$settings[$key] = $defaults[$key]; // add the default value
			}
			else
				$settings[$key] = call_user_func($sanitizer[$key], $settings[$key], $key);
			
			// run the filter if the setting value changed
			if ($settings[$key] !== $allSettings[$key])
				$settings[$key] = apply_filters(PromptCash::HOOK_PREFIX . "_settings_change_$key", $settings[$key], $allSettings[$key], $key, $settings); // (newVal, oldVal, key, allSettings)
		}
				
		$settings = apply_filters(PromptCash::HOOK_PREFIX . '_settings_update', $settings);
		update_option($this->settingsField, $settings);
		$this->options = $settings;
		return $settings;
	}
	
	public function handleUpdatePost() {
		// Verify update nonce.
		if ( false === $this->verifyNonce() )
			return;

		//$this->init_sanitizer_filters();
		$this->isSaving = isset($_POST['option_page']) && $_POST['option_page'] === $this->settingsField; // enable sanitization

		//* Flush transients after options have changed.
		add_action( "update_option_{$this->settingsField}", array( $this, 'updateOption' ) );
	}
	
	public function updateOption($options) {
		static $updated = 0;
		if (!is_array($options) || (isset($updated) && $updated >= 2)) {
			//$this->cashtippr->notifyError("options wrong", $options); // happens after update? with empty string
			return;
		}
		/**
		 * Array
			(
			    [myplugin_new_field] => foo
			)
			Array
			(
			    [closedpostboxesnonce] => 9cae849239
			    [meta-box-order-nonce] => ddb0e5d489
			    [option_page] => autodescription-site-settings
			    [action] => update
			    [_wpnonce] => 223346def4
			    [_wp_http_referer] => /wolfbotpress/wp-admin/admin.php?page=cashtippr
			    [myplugin_inner_custom_box_nonce] => c1d2bf285e
			    [myplugin_new_field] => abc
			    [submit] => Save Changes
			)
		 */
		/* // moved handling POST data to sanitize() function to ensure proper sanitization
		foreach ($options as $key => $oldValue) {
			if (isset($_POST[$key]) && !empty($_POST[$key]))
				$options[$key] = sanitize_text_field($_POST[$key]);
		}
		*/
		$updated++; // prevent endless recursion when listening for update event. being called twice by wp
		//update_option($this->settingsField, $options);
		//$this->options = $options;
	}
	
	/**
	 * Get a plugin setting by key.
	 * @param string $key
	 * @param boolean $useCache
	 * @return mixed
	 */
	public function get(string $key, $useCache = true) {
		if ($this->options === null || $useCache === false) {
			$this->options = get_option($this->settingsField); // defaults should already be set, skip 2nd function call
			if ($this->options === false)
				$this->options = $this->getDefaultSiteOptions();
			else if (!isset($this->options['version']) || $this->options['version'] !== PRCA_VERSION) {
				// first access after an update
				// we can't call this from our plugin_activation() functios because no instances exist at that time
				// TODO improve via non-blocking HTTP "cron" request from plugin_activation() hook?
				$this->afterPluginUpdate();
			}
		}
		return isset($this->options[$key]) ? $this->options[$key] : false;
	}
	
	public function getAll($useCache = true) {
		$this->get('version', $useCache); // just load them from DB or cache
		return $this->options;
	}
	
	/**
	 * Update a plugin setting by key.
	 * @param string $key
	 * @param mixed $value
	 * @return bool true if the setting was updated.
	 */
	public function set(string $key, $value): bool {
		$this->options = get_option($this->settingsField); // always reload from DB first to ensure we have the latest version
		if ($this->options === false) // shouldn't happen
			$this->options = $this->getDefaultSiteOptions();
		$this->options[$key] = $value;
		return update_option($this->settingsField, $this->options);
	}
	
	/**
	 * Update multiple plugin settings at once. Use this for improved performance (fewer DB queries).
	 * @param array $settings associative array with settings
	 * @return bool true if the settings were updated.
	 */
	public function setMultiple(array $settings): bool {
		$this->options = get_option($this->settingsField); // always reload from DB first to ensure we have the latest version
		if ($this->options === false) // shouldn't happen
			$this->options = $this->getDefaultSiteOptions();
		foreach ($settings as $key => $value) {
			$this->options[$key] = $value;
		}
		return update_option($this->settingsField, $this->options);
	}
	
	/**
	 * Get the default of any of the The plugin Framework settings.
	 *
	 * @param string $key required The option name
	 * @param string $setting optional The settings field
	 * @param bool $use_cache optional Use the options cache or not. For debugging purposes.
	 * @return int|bool|string default option
	 *         int '-1' if option doesn't exist.
	 */
	public function getDefaultSettings( $key, $setting = '', $use_cache = true ) {
		if ( ! isset( $key ) || empty( $key ) )
			return false;

		//* Fetch default settings if it's not set.
		if ( empty( $setting ) )
			$setting = $this->settingsField;

		//* If we need to bypass the cache
		if ( ! $use_cache ) {
			$defaults = $this->getDefaultSiteOptions();

			if ( ! is_array( $defaults ) || ! array_key_exists( $key, $defaults ) )
				return -1;

			return is_array( $defaults[ $key ] ) ? \stripslashes_deep( $defaults[ $key ] ) : stripslashes( $defaults[ $key ] );
		}

		static $defaults_cache = array();

		//* Check options cache
		if ( isset( $defaults_cache[ $key ] ) )
			//* Option has been cached
			return $defaults_cache[ $key ];

		$defaults_cache = $this->getDefaultSiteOptions();

		if ( ! is_array( $defaults_cache ) || ! array_key_exists( $key, (array) $defaults_cache ) )
			$defaults_cache[ $key ] = -1;

		return $defaults_cache[ $key ];
	}

	/**
	 * Get the warned setting of any of the The plugin Framework settings.
	 *
	 * @param string $key required The option name
	 * @param string $setting optional The settings field
	 * @param bool $use_cache optional Use the options cache or not. For debugging purposes.
	 * @return int 0|1 Whether the option is flagged as dangerous for SEO.
	 *         int '-1' if option doesn't exist.
	 */
	public function getWarnedSettings( $key, $setting = '', $use_cache = true ) {
		if ( empty( $key ) )
			return false;

		return false; // we don't have any warned settings yet
	}
	
	/**
	 * Checks the plugin Settings page nonce. Returns false if nonce can't be found.
	 * Performs wp_die() when nonce verification fails.
	 *
	 * Never run a sensitive function when it's returning false. This means no nonce can be verified.
	 *
	 * @return bool True if verified and matches. False if can't verify.
	 */
	protected function verifyNonce() {
		static $validated = null;
		if ( isset( $validated ) )
			return $validated;

		//* If this page doesn't store settings, no need to sanitize them
		if ( ! $this->settingsField )
			return $validated = false;

		/**
		 * If this page doesn't parse the site options,
		 * There's no need to filter them on each request.
		 * Nonce is handled elsewhere. This function merely injects filters to the $_POST data.
		 */
		if ( empty( $_POST ) || ! isset( $_POST[ $this->settingsField ] ) || ! is_array( $_POST[ $this->settingsField ] ) )
			return $validated = false;

		//* This is also handled in /wp-admin/options.php. Nevertheless, one might register outside of scope.
		\check_admin_referer( $this->settingsField . '-options' );

		return $validated = true;
	}
	
	protected function afterPluginUpdate() {
		// add missing keys of new settings after our plugin has been updated to a new version
		$settings = $this->options;
		$defaults = $this->getDefaultSiteOptions(false);
		foreach ($defaults as $key => $value) {
			if (!isset($settings[$key]))
				$settings[$key] = $defaults[$key]; // add the default value
		}
		$settings['version'] = PRCA_VERSION;
		update_option($this->settingsField, $settings);
		$this->options = $settings;
	}
	
	protected function getOptionSanitizers() {
		$defaultSanitizer = new Sanitizer();
		$tpl = $this->pluginAdmin->tpl;
		$defaults = $this->getDefaultSiteOptions();
		$settings = $this;
		$sanitizer = array(
				// custom sanitizers:
				// values are optional and must contain a PHP callable function($newValue, string $settingName)
				// the return value must be the sanitzed value
				'button_currency' => function($newValue, string $settingName) use($tpl, $defaultSanitizer, $settings) {
					$newValue = strtoupper(sanitize_text_field($newValue));
					if (strlen($newValue) < 3) {
						$tplVars = array(
								'msg' => __('The button currency can not be empty.', 'ekliptor'),
						);
						$notice = new AdminNotice($tpl->getTemplate('adminNotice.php', $tplVars), 'error');
						$defaultSanitizer->addAdminNotices($notice);
						return $settings->get($settingName); // keep the previous value
					}
					return $newValue;
				},
				'tip_btn_txt' => function($newValue, string $settingName) use($tpl, $defaultSanitizer, $settings) {
					$newValue = sanitize_text_field($newValue);
					if (strlen($newValue) < 3) {
						$tplVars = array(
								'msg' => __('The tip button text can not be empty.', 'ekliptor'),
						);
						$notice = new AdminNotice($tpl->getTemplate('adminNotice.php', $tplVars), 'error');
						$defaultSanitizer->addAdminNotices($notice);
						return $settings->get($settingName); // keep the previous value
					}
					return $newValue;
				},
				'use_memcached' => function($newValue, string $settingName) use($tpl, $defaultSanitizer, $defaults, $settings) {
					$newValue = $defaultSanitizer->formatBoolean($newValue, $settingName);
					if ($newValue === false)
						return false;
					if (MemcachedSessionHandler::isInstalled() === false) {
						$tplVars = array(
								'msg' => __('Memcached is not installed on your server where WordPress is running. Please install this or contact your hosting provier about it.', 'ekliptor')
						);
						$notice = new AdminNotice($tpl->getTemplate('adminNotice.php', $tplVars), 'error');
						$defaultSanitizer->addAdminNotices($notice);
						return $defaults[$settingName]; // default is disabled
					}
					if (MemcachedSessionHandler::checkConnection($settings->get('memcached_host'), $settings->get('memcached_port')) === false) {
						$tplVars = array(
								'msg' => __('Unable to connect to the memcached server you entered. Please ensure the memcached daemon is listening on the address you provided and you can access it from your network (firewall settings etc... ).', 'ekliptor')
						);
						$notice = new AdminNotice($tpl->getTemplate('adminNotice.php', $tplVars), 'error');
						$defaultSanitizer->addAdminNotices($notice);
						return $defaults[$settingName]; // default is disabled
					}
					return $newValue;
				},
		);
		$sanitizer = apply_filters(PromptCash::HOOK_PREFIX . '_settings_sanitizer', $sanitizer, $defaultSanitizer, $tpl, $defaults, $this);
		foreach ($defaults as $key => $value) {
			if (isset($sanitizer[$key]))
				continue;
			// add a sanitizer based on the type of the default value
			$sanitizer[$key] = $defaultSanitizer->sanitizeByType($defaults[$key]);
		}
		return $sanitizer;
	}
	
	/**
	 * Get the default settings for this plugin.
	 * @param boolean $onUpdate true if we are updating settings via form submit
	 * @return string[]|number[]|boolean[]
	 */
	protected function getDefaultSiteOptions($onUpdate = false) {
		$defaults = array(
				'version' => PRCA_VERSION,
				
				// Payment
				'button_currency' => 'USD',
				'default_amount' => 0.1,
				'tip_btn_txt' => __('Tip the author', 'ekliptor'),
				'hide_tip_txt' => __('Please tip to see the hidden text ({words} words).', 'ekliptor'),
				
				// API
				'promptCashOrigin' => 'https://prompt.cash',
				'publicToken' => '',
				'secretToken' => '',
				
				// Blurry Images
				'hide_image_txt' => __('Tip to see the clear image:', 'ekliptor'),
				'blurry_cache_mb' => 400,
				
				// Advanced
				'show_search_engines' => false,
				'session_name' => PromptCash::SESSION_COOKIE_NAME,
				'show_cookie_consent' => false,
				'use_memcached' => false,
				'memcached_host' => '127.0.0.1',
				'memcached_port' => 11211,
				
				// Stats
				'tips' => 0, // total number of tips
				'tip_amount' => 0.0, // total amount of all tips
				'wooEnabledBefore' => false,
		);
		$defaults = apply_filters(PromptCash::HOOK_PREFIX . '_default_settings', $defaults);
		if ($onUpdate === true) { // html form checkboxes are not present when false, so assume false for all on update
			foreach ($defaults as $name => $value) {
				if ($value === true && is_bool($defaults[$name]) === true) // is_bool() check shouldn't be needed
					$defaults[$name]= false;
			}
		}
		return $defaults;
	}
}
?>