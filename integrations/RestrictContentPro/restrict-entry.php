<?php
namespace Ekliptor\PromptCash;


add_filter( 'rcp_payment_gateways', function (array $gateways) {
	//$slug = RcpGateway::GATEWAY_ID; // not loaded yet
	$slug = 'prompt_cash';
	$gateways[$slug] = array(
		'label'       => __('Bitcoin Cash (BCH)', 'ekliptor'), 	// Displayed on front-end registration form
		'admin_label' => __('Prompt.Cash', 'ekliptor'), 		// Displayed in admin area
		'class'       => 'Ekliptor\PromptCash\RcpGateway' 		// Name of the custom gateway class
	);

	return $gateways;
} );




add_action( 'plugins_loaded', function () {
	if (class_exists('RCP_Payment_Gateway') === false) {
		return; // RestrictContentPro not installed
	}
	
	require_once PRCA__PLUGIN_DIR . 'integrations/RestrictContentPro/RcpGateway.php';
} );
?>