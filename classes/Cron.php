<?php
namespace Ekliptor\PromptCash;

class Cron {
	const CLEANUP_TRANSACTIONS_H = 25;
	
	public static $cron_events = array (
			'prompt_cleanup_transactions',
			'prompt_cleanup_sessions',
			'prompt_cleanup_image_cache',
		);
	public static $cron_events_hourly = array(
		);
	
	/** @var PromptCash */
	protected $plugin;
	/** @var Settings */
	protected $settings;
	
	public function __construct(PromptCash $plugin, Settings $settings) {
		$this->plugin = $plugin;
		$this->settings = $settings;
		
		// init cron events
		add_action( 'prompt_cleanup_transactions', array ($this, 'cleanupTransactions' ) );
		add_action ( 'prompt_cleanup_image_cache', array ($this, 'cleanupImageCache' ) );
		
		// cleanup old sessions
		$sessionManager = $this->plugin->getSessionManager();
		if ($sessionManager !== null)
			add_action( 'prompt_cleanup_sessions', array ($sessionManager, 'cleanupOldSessions' ) );
	}
	
	public function cleanupTransactions() {
		global $wpdb;
		$table = PromptCash::getTableName('transactions');
		$maxAge = date('Y-m-d H:i:s', time() - static::CLEANUP_TRANSACTIONS_H * HOUR_IN_SECONDS);
		$wpdb->query("DELETE FROM $table WHERE created < '$maxAge'");
		
		// cleanup the dir with QR codes
		$cacheDir = PRCA__PLUGIN_DIR . 'data/temp/qr/';
		$files = scandir($cacheDir);
		if ($files === false) {
			PromptCash::notifyErrorExt('Error scanning qr code dir to cleanup', "cache dir: $cacheDir");
			return;
		}
		// cleanup by age, oldest creation/changed time first
		$deletionTime = time() - static::CLEANUP_TRANSACTIONS_H*HOUR_IN_SECONDS;
		foreach ($files as $file) {
			if (empty($file) || $file[0] === '.')
				continue;
			$filePath = $cacheDir . '/' . $file;
			$lastChanged = filectime($filePath);
			if ($lastChanged < $deletionTime)
				@unlink($filePath);
		}
	}
	
	public function cleanupImageCache() {
		$limitMb = (int)$this->settings->get('blurry_cache_mb');
		$imageCache = $this->plugin->getImageCache();
		$imageCache->cleanupImageCache($limitMb);
	}
}

?>