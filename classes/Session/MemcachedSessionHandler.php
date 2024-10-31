<?php
namespace Ekliptor\PromptCash;

use \Memcached;

class MemcachedSessionHandler extends AbstractSessionHandler {
	const CONNECT_TIMEOUT_MS = 10; // 10 for LAN
	const SERVER_FAILURE_LIMIT = 2; // remove server after x connection failures
	
	/** @var Memcached */
	protected $memcached;
	/** @var string */
	protected $prefix;
	/** @var int */
	protected $expirationSec; // max 60*60*24*30 http://php.net/manual/en/memcached.expiration.php
	
	public static function isInstalled(): bool {
		return class_exists('Memcached') === true;
	}
	
	public static function checkConnection(string $host, int $port): bool {
		$memcached = new Memcached();
		$memcached->setOption(Memcached::OPT_CONNECT_TIMEOUT, static::CONNECT_TIMEOUT_MS);
		$memcached->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, static::SERVER_FAILURE_LIMIT);
		$memcached->addServers(array(array($host, $port)));
		$stats = $memcached->getStats();
		return isset($stats[$host . ":" . $port]);
	}
	
	/**
	 * Register memcached as session store with the given memcached instance.
	 * @param object $memcached The configured memcached object
	 * @param array $options Array with optional keys: prefix (string), expirationSec (int)
	 */
	public static function registerFromMemcached($memcached, $options = array()) {
		return new self($memcached, $options);
	}
	
	/**
	 * Register memcached as session store with the given memcache servers
	 * @param array $serverArr An array of memcached servers for $memcached->addServers($serverArr)
	 * @param array $options Array with optional keys: prefix (string), expirationSec (int)
	 */
	public static function registerFromServers($serverArr, $options = array()) {
		$memcached = new Memcached();
		$memcached->setOption(Memcached::OPT_CONNECT_TIMEOUT, static::CONNECT_TIMEOUT_MS);
		$memcached->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
		$memcached->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
		$memcached->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, static::SERVER_FAILURE_LIMIT);
		$memcached->setOption(Memcached::OPT_REMOVE_FAILED_SERVERS, true); // >= php-memcached 2.0.0b2
		$memcached->setOption(Memcached::OPT_RETRY_TIMEOUT, 1); // retry after x ms
		$memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true); // must be set before connecting
		$memcached->addServers($serverArr); // array(array('domain.com', 11211, 33)) // weights only used with consistent distribution
		return new self($memcached, $options);
		// TODO persistent connections support
	}
	
	private function __construct($memcached, $options = array()) {
		$this->memcached = $memcached;
		$this->prefix = isset($options['prefix']) ? $options['prefix'] : '';
		$this->expirationSec = isset($options['expirationSec']) ? $options['expirationSec'] : $this->getMaxLifetime();
		if (!isset($options['noSideEffects']) || $options['noSideEffects'] !== true)
			session_set_save_handler($this, true);
	}
	
	public function open($savePath, $sessionName): bool {
		return true;
	}
	
	public function close(): bool {
		return true;
	}
	
	public function read($sessionId) { // string|false
		return $this->memcached->get($this->prefix . $sessionId) ?: '';
	}
	
	public function write($sessionId, $data): bool { // data is already a string
		return $this->memcached->set($this->prefix . $sessionId, $data, time() + $this->expirationSec);
	}
	
	public function destroy($sessionId): bool {
		return $this->memcached->delete($this->prefix . $sessionId);
	}
	
	public function gc($maxlifetime): int {
		//$maxlifetime = max($maxlifetime, static::DEFAULT_MAX_LIFETIME_SEC); // just use our own $this->expirationSec
		return 0; // memcached cleans the cache automatically
	}
}
?>