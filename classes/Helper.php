<?php
namespace Ekliptor\PromptCash;

class Helper {
	/**
	 * Updates the $key of the order meta with $value only if it doesn't already have the same value.
	 * This is only applicable to metadata with a unique key, otherwise we just always add the value using add_meta_data().
	 * @param \WC_Order $order
	 * @param string $key
	 * @param mixed $value
	 * @return bool True if the value was updated, false otherwise.
	 */
	/*
	public static function updateOrderMeta(\WC_Order $order, string $key, $value): bool {
		$currentValue = $order->get_meta($key);
		if ($currentValue === $value)
			return false;
		$order->add_meta_data($key, $value, true);
    	$order->save_meta_data();
    	return true;
	}
	*/
		
	public static function getRandomString($len) {
		$chars = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$max = strlen($chars)-1;
		mt_srand();
		$random = '';
		for ($i = 0; $i < $len; $i++)
			$random .= $chars[mt_rand(0, $max)];
		return $random;
	}
	
	public static function isValidUrl(string $url): bool {
		if (strlen($url) < 6 || preg_match("/^https?:\/\//", $url) !== 1 || strpos($url, '.') === false)
			return false;
		return true;
	}
}
?>