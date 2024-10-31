<?php
namespace Ekliptor\PromptCash;

class Session {
	/** @var string */
	protected $sessionId;
	
	/** @var array Assoc array session data */
	protected $data = array();
	
	/** @var bool Indicates whether the session needs to be saved. */
	protected $dirty = false;
	
	public function __construct(string $sessionId) {
		$this->sessionId = $sessionId;
	}
	
	/**
	 * Sets the value in the user's session under a given key.
	 * @param string $key
	 * @param mixed $value
	 * @return bool
	 */
	public function set(string $key, $value): bool {
		$key = $this->sanitizeKey($key);
		$this->data[$key] = $value;
		$this->dirty = true;
		return true;
	}
	
	/**
	 * Return the session data for a given key.
	 * @param string $key
	 * @return NULL|mixed The session data or null if no data exists under the given key.
	 */
	public function get(string $key) {
		$key = $this->sanitizeKey($key);
		if (!isset($this->data[$key]))
			return null;
		
		return $this->data[$key];
	}
	
	/**
	 * Return the unique ID of this session.
	 * @return string
	 */
	public function getId(): string {
		return $this->sessionId;
	}
	
	/**
	 * Returns true if this session was modified and needs to be saved.
	 * @return bool
	 */
	public function isDirty(): bool {
		return $this->dirty;
	}
	
	/**
	 * Marks the session as 'dirty', meaning it must be saved on script termination.
	 */
	public function markDirty(): void {
		$this->dirty = true;
	}
	
	protected function sanitizeKey(string $key): string {
		//$key     = strtolower($key);
		$key     = preg_replace('/[^a-z0-9_\-]/', '', $key);
		return $key;
	}
}
?>