<?php
function promptcash_woocommerce_load() {
	if (class_exists(/*'WooCommerce'*/'WC_Payment_Gateway') === false) {
		return; // WooCommerce not installed
	}
	
	require_once (PRCA__PLUGIN_DIR . 'integrations/woocommerce/WcGateway.php');
	
	add_filter('woocommerce_payment_gateways', array('Ekliptor\PromptCash\WcGateway', 'addWoocommerceGateway'), 10, 1);
}

add_action( 'plugins_loaded', function () {
	promptcash_woocommerce_load();
}, 100 );

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		$pluginMainFile = PRCA__PLUGIN_DIR . 'prompt-cash.php';
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', $pluginMainFile, true );
	}
} );
?>