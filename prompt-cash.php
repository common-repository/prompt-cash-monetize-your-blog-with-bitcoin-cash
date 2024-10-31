<?php
/*
 * Plugin Name: Prompt.Cash: Monetize your blog with Bitcoin Cash
 * Plugin URI: https://prompt.cash/
 * Description: Monetize your blog with Bitcoin Cash (BCH) by hiding content and payment gateway integrations.
 * Version: 1.0.21
 * Author: Ekliptor
 * Author URI: https://twitter.com/ekliptor
 * License: GPLv3
 * Text Domain: ekliptor
 */

use Ekliptor\PromptCash\DatabaseMigration;
use Ekliptor\PromptCash\PromptCash;
use Ekliptor\PromptCash\PromptCashAdmin;
use Ekliptor\PromptCash\PromptCashApi;

// Make sure we don't expose any info if called directly
if (! defined( 'ABSPATH' )) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit ();
}

define ( 'PRCA_VERSION', '1.0.21' );
define ( 'PRCA__MINIMUM_WP_VERSION', '4.9' );
define ( 'PRCA__PLUGIN_DIR', plugin_dir_path ( __FILE__ ) );

if (PHP_VERSION_ID < 70000) {
	load_plugin_textdomain ( 'ekliptor' );
	$escapeHtml = false;
	$message = '<strong>' . esc_html__ ( 'You need PHP v7.0 or higher to use this plugin.', 'ekliptor' ) . '</strong> ' . esc_html__ ( 'Please update in your hosting provider\'s control panel or contact your hosting provider.', 'ekliptor' );
	include PRCA__PLUGIN_DIR . 'tpl/message.php';
	exit();
}

register_activation_hook ( __FILE__, array (
		'Ekliptor\PromptCash\PromptCash',
		'plugin_activation' 
) );
register_deactivation_hook ( __FILE__, array (
		'Ekliptor\PromptCash\PromptCash',
		'plugin_deactivation' 
) );

require_once (PRCA__PLUGIN_DIR . 'data.php');
require_once (PRCA__PLUGIN_DIR . 'functions.php');
require_once (PRCA__PLUGIN_DIR . 'classes/PromptCash.php');
require_once (PRCA__PLUGIN_DIR . 'classes/PromptCashApi.php');
require_once (PRCA__PLUGIN_DIR . 'api.php');
PromptCashApi::getInstance(PromptCash::getInstance());

DatabaseMigration::checkAndMigrate();

add_action ( 'init', array (
		PromptCash::getInstance(),
		'init' 
) );

add_action ( 'rest_api_init', array (
		PromptCashApi::getInstance(),
		'init' 
) );

if (is_admin ()/* || (defined ( 'WP_CLI' ) && WP_CLI)*/) {
	require_once (PRCA__PLUGIN_DIR . 'classes/PromptCashAdmin.php');
	PromptCashAdmin::getInstance(PromptCash::getInstance());
	add_action ( 'init', array (
			PromptCashAdmin::getInstance(),
			'init' 
	) );
}


// WooCommerce and other integrations
require_once (PRCA__PLUGIN_DIR . 'integrations/RestrictContentPro/restrict-entry.php');
require_once (PRCA__PLUGIN_DIR . 'integrations/woocommerce/wc-entry.php');
?>