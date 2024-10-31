<?php
namespace Ekliptor\PromptCash;

class SessionManager {
	/** @var AbstractSessionHandler */
	protected $sessionHandler;
	/** @var string */
	protected $sessionName;
	/** @var string */
	protected $savePath;
	/** @var bool */
	protected $gcOnExit;
	/** @var string The ID of the session created in start() */
	protected $mainSessionId = '';
	
	/** @var array Assoc array with (sessionID => Session) */
	protected $sessions = array();
	
	/** @var int */
	protected $lifetime;
	/** @var string */
	protected $path;
	/** @var string */
	protected $domain;
	/** @var bool */
	protected $secure;
	/** @var bool */
	protected $httponly;
	
	/** @var bool */
	protected static $sessionStarted = false;
	
	/**
	 * Create a new session manager.
	 * @param AbstractSessionHandler $sessionHandler The \SessionHandlerInterface implementation to store session data.
	 * @param string $sessionName The session name. This will be the cookie name.
	 * @param string $savePath The save path (only for a "file" SessionHandler).
	 * @param bool $gcOnExit Run Gargabe Collection on exit.
	 */
	public function __construct(AbstractSessionHandler $sessionHandler, string $sessionName, string $savePath = '', bool $gcOnExit = true) {
		$this->sessionHandler = $sessionHandler;
		$this->sessionName = $sessionName;
		$this->savePath = $savePath;
		$this->gcOnExit = $gcOnExit;
		
		// session path is only used for a "file" session handler
		if (empty($savePath)) {
			$savePath = ini_get('session.save_path');
			if (empty($savePath))
				$savePath = sys_get_temp_dir();
		}
		
		$this->sessionHandler->open($savePath, $sessionName);
	}
	
	public function __destruct() {
		// We store all loaded sessions on shutdown (usually only 1)
		foreach ($this->sessions as $sessionId => $session) {
			if ($session->isDirty() === true)
				$this->storeSession($sessionId, $session);
		}
		
		if ($this->gcOnExit === true)
			$this->cleanupOldSessions(true);
		$this->sessionHandler->close();
	}
	
	/**
	 * Set the session cookie parameters
	 * @link http://www.php.net/manual/en/function.session-set-cookie-params.php
	 * @param int $lifetime Lifetime of the
	 * session cookie, defined in seconds.
	 * @param string $path [optional] Path on the domain where
	 * the cookie will work. Use a single slash ('/') for all paths on the
	 * domain.
	 * @param string $domain [optional] Cookie domain, for
	 * example 'www.php.net'. To make cookies visible on all subdomains then
	 * the domain must be prefixed with a dot like '.php.net'.
	 * @param bool $secure [optional] If true cookie will only be sent over
	 * secure connections.
	 * @param bool $httponly [optional] If set to true then PHP will attempt to send the
	 * httponly
	 * flag when setting the session cookie.
	 * @return void 
	 */
	public function setSessionCookieParameters(int $lifetime, string $path = null, string $domain = null, bool $secure = null, bool $httponly = null) {
		$this->lifetime = $lifetime;
		$this->path = $path;
		$this->domain = $domain;
		$this->secure = $secure;
		$this->httponly = $httponly;
	}
	
	/**
	 * Starts a new session or resumes the current session.
	 * Must be called before HTTP headers are sent because this function will add the session cookie if it doesn't exist.
	 * @return bool True if the session was started, false otherwise.
	 */
	public function start(): bool {
		// don't start a new session for WP cron (populates session storage quickly). Fail silently
		if (defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' )/* || defined( 'REST_REQUEST' )*/)
			return true;
		
		if (static::$sessionStarted === true)
			return false;
		static::$sessionStarted = true;
		
		if (isset($_COOKIE[$this->sessionName])) { // existing session?
			$this->mainSessionId = $this->sanitizeSessionId($_COOKIE[$this->sessionName]);
			//$this->getSession($_COOKIE[$this->sessionName]); // we could already load it
			return true;
		}
		
		// this is a 1st time visitor
		// TODO add "keep logged in" feature by renewing cookie
		$this->mainSessionId = $this->createSessionId();
		//PromptCash::notifyErrorExt("starting new session", $_SERVER);
		return setcookie($this->sessionName, $this->mainSessionId, time() + $this->lifetime, $this->path, $this->domain, $this->secure, $this->httponly);
	}
	
	/**
	 * Return the session of the current user by session cookie. Must be called once after start() to create the session.
	 * @return Session The Session object. If no session exists it will create a new one.
	 */
	public function getCurrentSession(): Session {
		//if (!isset($_COOKIE[$this->sessionName]))
			//return new Session();
		$sessionId = isset($_COOKIE[$this->sessionName]) ? $_COOKIE[$this->sessionName] : $this->mainSessionId;
		// if DOING_AJAX mainSessionId is empty string -> return dummy session
		return $this->getSession($sessionId);
	}
	
	/**
	 * Return a session by ID (session cookie).
	 * @param string $sessionId
	 * @return Session The Session object. If no session exists it will create a new one.
	 */
	public function getSession(string $sessionId): Session {
		$sessionId = $this->sanitizeSessionId($sessionId);
		if (!empty($sessionId) && isset($this->sessions[$sessionId]))
			return $this->sessions[$sessionId]; // read from cache
		
		$session = empty($sessionId) ? null : $this->sessionHandler->read($sessionId); // data or empty string
		if (empty($session)) {
			$this->sessions[$sessionId] = new Session($sessionId);
			$this->sessions[$sessionId]->markDirty(); // save the newly created session
		}
		else
			$this->sessions[$sessionId] = unserialize($session);
		return $this->sessions[$sessionId];
	}
	
	/**
	 * Write the session data back to the session handler.
	 * @param string $sessionId
	 * @param Session $session
	 * @return bool true on success, false otherwise
	 */
	public function storeSession(string $sessionId, Session $session): bool {
		if (empty($sessionId))
			return false;
		$serialized = serialize($session);
		return $this->sessionHandler->write($sessionId, $serialized);
	}
	
	public function destroy(string $sessionId): bool {
		return $this->sessionHandler->destroy($sessionId);
	}
	
	/**
	 * Run garbage collection of sessions.
	 * @param bool $useProbability Use PHP's default GC probability settings to decide if GC should run. False means it will
	 * 			always run and is intented to be used when running in background (WP Cron).
	 * @return bool
	 */
	public function cleanupOldSessions(bool $useProbability = false): bool {
		if ($useProbability === true) {
			$probability = $this->sessionHandler->getGargabeCollectionProbability();
			$shift = 100000;
			mt_srand();
			$rand = mt_rand(0, $shift);
			if ($rand > $probability*$shift)
				return true; // don't run
		}
		return $this->sessionHandler->gc($this->sessionHandler->getMaxLifetime());
	}
	
	/**
	 * Generate a unique session ID for this user.
	 * @return string
	 */
	public function createSessionId(): string {
		$input = '';
		if (isset($_SERVER['REMOTE_ADDR']))
			$input .= $_SERVER['REMOTE_ADDR'];
		//if (isset($_SERVER['REQUEST_URI']))
			//$input .= $_SERVER['REQUEST_URI'];
		if (isset($_SERVER['HTTP_USER_AGENT']))
			$input .= $_SERVER['HTTP_USER_AGENT'];
		// microtime() adds "wrong" entropy in case 2 HTTP requests come together
		$sessionId = hash('sha256', 'SessionManager' . time() . $input, true);
		$sessionId = base64_encode($sessionId);
		return preg_replace('/[^a-z0-9_\-]/i', '', $sessionId);
	}
	
	protected function sanitizeSessionId(string $sessionId): string {
		//$filtered = preg_replace('/[\r\n\t ]+/', ' ', $sessionId);
		$filtered = preg_replace('/[^a-z0-9_\-]/i', '', $sessionId);
		return trim($filtered);
	}
}
?>