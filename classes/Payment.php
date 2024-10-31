<?php
namespace Ekliptor\PromptCash;

class PaymentButtonID {
	/** @var int */
	protected $postID = 0;
	/** @var int */
	protected $buttonNr = 0;
	
	/**
	 * Creates a new (internal) button ID unique for each button.
	 * @param string $combinedId postID-buttonNr
	 */
	public function __construct(string $combinedId) {
		$idParts = explode('-', $combinedId);
		if (count($idParts) !== 2)
			throw new \Exception("PaymentButtonID must have exactly 2 parts: postID-buttonNr, got $combinedId");
		$this->postID = (int)$idParts[0];
		$this->buttonNr = (int)$idParts[1];
	}
	
	public function getPostID(): int {
		return $this->postID;
	}
	
	public function getButtonNr(): int {
		return $this->buttonNr;
	}
	
	public function getButtonIdStr(): string {
		return sprintf("%d-%d", $this->postID, $this->buttonNr);
	}
}


class Payment {
	const MIN_TIP_AMOUNT_TOLERANCE = 0.001; // alow 0.1 cent less
	
	/** @var float */
	protected $amount = 0.0;
	/** @var string */
	protected $currency = '';
	/** @var string Transaction ID on API server */
	protected $txID = '';
	/** @var PaymentButtonID */
	protected $buttonID = 0;
	
	public function __construct(float $amount, string $currency, string $txID, PaymentButtonID $buttonID = null) {
		$this->amount = $amount;
		$this->currency = $currency;
		$this->txID = trim($txID);
		if (empty($this->txID))
			throw new \Exception("TxID in Prompt.Cash Payment can not be empty.");
		$this->buttonID = $buttonID;
	}
	
	public function addPaymentToSession(string $sessionId): bool {
		if (empty($sessionId))
			return false;
		
		return $this->storePaymentData($sessionId);
	}
	
	public static function createTransactionsTable(): bool {
		global $wpdb;
		$table = PromptCash::getTableName('transactions');
		if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table)
			return true; // table already exists
		$success = $wpdb->query("CREATE TABLE `$table` (
  				`txid` varchar(64) NOT NULL,
				 `amount` float NOT NULL,
				 `currency` varchar(3) NOT NULL,
				 `session_id` varchar(64) NOT NULL,
				 `post_id_btn` varchar(20) NOT NULL DEFAULT '',
  				`created` timestamp NOT NULL DEFAULT current_timestamp()
				) ENGINE=InnoDB DEFAULT CHARSET=utf8;") !== false;
		if ($success === false)
			return false;
		$success = $wpdb->query("ALTER TABLE `$table` ADD PRIMARY KEY (`txid`);") !== false;
		if ($success === false)
			return false;
		$success = $wpdb->query("ALTER TABLE `$table` ADD INDEX(`session_id`);") !== false;
		return $success === true;
	}
	
	public static function isTippedPost(string $sessionId, string $buttonIdStr, float $minAmount = 0.00000001, string $currency = 'USD'): bool {
		global $wpdb;
		$table = PromptCash::getTableName('transactions');
		if ($minAmount > static::MIN_TIP_AMOUNT_TOLERANCE)
			$minAmount -= static::MIN_TIP_AMOUNT_TOLERANCE;
		$query = $wpdb->prepare("SELECT COUNT(*) AS cnt FROM $table WHERE session_id = '%s' AND post_id_btn = '%s' AND amount >= '%f' AND currency = '%s'",
				array($sessionId, $buttonIdStr, $minAmount, $currency));
		$row = $wpdb->get_row($query);
		return $row && $row->cnt > 0;
	}
	
	protected function storePaymentData(string $sessionId): bool {
		global $wpdb;
		$table = PromptCash::getTableName('transactions');
		$query = $wpdb->prepare("REPLACE INTO $table (txid, amount, currency, session_id, post_id_btn) VALUES('%s', '%f', '%s', '%s', '%s')", 
				array($this->txID, $this->amount, $this->currency, $sessionId, $this->buttonID->getButtonIdStr()));
		if ($wpdb->query($query) === false) {
			PromptCash::notifyErrorExt("Error storing Prompt.Cash payment", "Query: $query\r\nError: " . $wpdb->last_error);
			return false;
		}
		return true;
	}
}
?>