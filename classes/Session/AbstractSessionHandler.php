<?php
namespace Ekliptor\PromptCash;

abstract class AbstractSessionHandler implements \SessionHandlerInterface {
	const DEFAULT_MAX_LIFETIME_SEC = 86400; // default PHP session lifetime
	
	public function getMaxLifetime(): int {
		return max((int)ini_get("session.gc_maxlifetime"), static::DEFAULT_MAX_LIFETIME_SEC); // some shared hosters enforce short times
	}
	
	/**
	 * Return a value between 0.00001 and 1.0 indicating the probability the gargabe collection should run.
	 * @return float
	 */
	public function getGargabeCollectionProbability(): float {
		// 1/100 means 1% probability GC will run at the end of script
		$gcProbability = (float)ini_get("session.gc_probability");
		$gcDivisor = (float)ini_get("session.gc_divisor");
		if ($gcDivisor <= 0.0)
			$gcDivisor = 100.0;
		$prob = $gcProbability / $gcDivisor;
		if ($prob < 0.00001) // 0.001%
			$prob = 0.00001;
		else if ($prob > 1.0)
			$prob = 1.0;
		return $prob;
	}
}

// include subclasses
require_once PRCA__PLUGIN_DIR . 'classes/Session/MemcachedSessionHandler.php';
require_once PRCA__PLUGIN_DIR . 'classes/Session/MysqlWpSessionHandler.php';
?>