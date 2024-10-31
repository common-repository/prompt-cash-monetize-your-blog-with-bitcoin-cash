<?php
namespace Ekliptor\PromptCash;

class PromptCashGateway {
	const API_ENDPOINT = 'https://prompt.cash';
	//const API_ENDPOINT = 'http://danielmac.local:2929';
	
	const FRAME_URL = '/pay-frame?token=%s&tx_id=%s&amount=%s&currency=%s&desc=%s';
	const PAGE_URL = '/pay?token=%s&tx_id=%s&amount=%s&currency=%s&desc=%s&return=%s&callback=%s';
	
	/** @var string */
	protected $publicToken = '';
	/** @var string */
	protected $secretToken = '';
	/** @var string */
	protected $siteUrl = '';
	
	public function __construct(string $publicToken, string $secretToken, string $siteUrl = '') {
		// TODO reject/warn if values are empty?
		$this->publicToken = $publicToken;
		$this->secretToken = $secretToken;
		$this->siteUrl = $siteUrl;
		
		//$payment = $this->GetPayment('your-id');
		//pre_print_r("payment");
		//pre_print_r($payment);
	}
	
	/**
	 * Gets a pay frame URL for the specified parameters.
	 * @param int $postID
	 * @param float $amount
	 * @param string $currency
	 * @param string $type A custom type if $postID relates to another object (for example User-ID). Only used for display purpose on invoice history.
	 * @return string
	 */
	public function getPayFrameUrl(int $postID, float $amount, string $currency, string $type = ''): string {
		if (empty($type))
			$type = __('Post', 'ekliptor');
		$promptTxId = sprintf("%s-%d-%s", $postID, time(), static::getRandomString(4));
		$currency = strtoupper($currency);
		$desc = urlencode(trim(sprintf("%s %d @ %s", $type, $postID, $this->siteUrl)));
		$amountFormat = number_format($amount, 8, '.', '');
		$url = sprintf(static::API_ENDPOINT . static::FRAME_URL, $this->publicToken, $promptTxId, $amountFormat, $currency, urlencode($desc));
		return $url;
	}
	
	/**
	 * Get the full-page payment URL for the user to make a payment.
	 * @param int $postID
	 * @param float $amount
	 * @param string $currency
	 * @param string $type A custom type if $postID relates to another object (for example User-ID). Only used for display purpose on invoice history.
	 * @param string $returnUrl
	 * @param string $callbackUrl
	 * @return string
	 */
	public function getPayPageUrl(int $postID, float $amount, string $currency, string $type = '', string $returnUrl = '', string $callbackUrl = ''): string {
		if (empty($type))
			$type = __('Post', 'ekliptor');
		$promptTxId = sprintf("%s-%d-%s", $postID, time(), static::getRandomString(4));
		$currency = strtoupper($currency);
		$desc = urlencode(trim(sprintf("%s %d @ %s", $type, $postID, $this->siteUrl)));
		$amountFormat = number_format($amount, 8, '.', '');
		$url = sprintf(static::API_ENDPOINT . static::PAGE_URL, $this->publicToken, $promptTxId, $amountFormat, $currency, urlencode($desc),
				urlencode($returnUrl), urlencode($callbackUrl));
		return $url;
	}
	
	/**
	 * Gets a pay frame URL with placeholders: {promptTxId}, {amount}, {currency}, {desc}
	 * @return string
	 */
	public function getNamedPayFrameUrl(): string {
		$url = sprintf(static::API_ENDPOINT . static::FRAME_URL, $this->publicToken, '{promptTxId}', '{amount}', '{currency}', '{desc}');
		return $url;
	}
	
	/**
	 * Returns the internal payment ID (or PostID when creating here) from the Prompt.Cash reference/description. 
	 * @param string $payReference
	 * @return int
	 */
	public function getPaymentId(string $payReference): int {
		$stop = mb_strpos($payReference, '@');
		if ($stop === false)
			return 0;
		$firstPart = mb_substr($payReference, 0, $stop);
		$id = preg_replace("/[^0-9]+/", "", $firstPart);
		return intval($id);
	}
	
	public function getPublicToken(): string {
		return $this->publicToken;
	}
	
	public function getSecretToken(): string {
		return $this->secretToken;
	}
	
	/**
	 * Returns the payment with Associated ID (Post/Order ID). It returns null if no such payment exists.
	 * For a list of properties see: https://prompt.cash/pub/docs/#get-a-single-payment
	 * @param int|string $txID
	 * @return object
	 */
	public function GetPayment($txID) {
		$checkUrl = sprintf("%s/api/v1/get-payment/%s", static::API_ENDPOINT, $txID);
		$res = wp_remote_get($checkUrl, array(
				'timeout' => 10,
				'headers' => array(
						'Accept' => 'application/json',
						'Authorization' => $this->secretToken,
				),
		));
		if ($res instanceof \WP_Error) {
			return null;
		}
		$body = wp_remote_retrieve_body($res);
		$json = json_decode($body);
		if (empty($json) || !isset($json->data) || !isset($json->data->id)) {
			return null;
		}
		
		return $json->data;
	}
	
	protected static function getRandomString($len) {
		$chars = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		$max = strlen($chars)-1;
		mt_srand();
		$random = '';
		for ($i = 0; $i < $len; $i++)
			$random .= $chars[mt_rand(0, $max)];
		return $random;
	}
}
?>