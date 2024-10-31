<?php
/**
 * Prompt.Cash Uninstall
 *
 * Uninstall and delete all stored session & payment data from all users.
 */
namespace Ekliptor\PromptCash;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once (plugin_dir_path ( __FILE__ ) . 'data.php');

class PromptCashUninstall {
	public function __construct() {
	}
	
	public function uninstall() {
		global $wpdb, $wp_version;
		
		// Only remove all user session + payment data if this is set to true.
		// This is to prevent data loss when deleting the plugin from the backend
		// and to ensure only the site owner can perform this action.
		if (PromptCashData::REMOVE_ALL_DATA !== true)
			return;
		
		//wp_clear_scheduled_hook( 'woocommerce_scheduled_sales' );
		
		//$table = Cashtippr::getTableName('sessions'); // we don't have that class loaded
		$tables = get_option('promptcash_tables', array());
		foreach ($tables as $table) {
			$wpdb->query( "DROP TABLE IF EXISTS $table" );
		}
		
		delete_option('promptcash_tables');
		delete_option('promptcash_settings'); // TODO load settings class in case that option name has been changed (currently not possible)
		delete_option('promptcash_version');
		
		/*
		$attributes = array('tipAmount');
		$attributesStr = "'" . implode("', '", $attributes) . "'";
		$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ($attributesStr)");
		*/
		
		// No post data to delete.
		
		// Clear any cached data that has been removed.
		wp_cache_flush();
	}
}

$uninstall = new PromptCashUninstall();
$uninstall->uninstall();
