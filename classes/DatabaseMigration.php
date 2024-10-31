<?php
namespace Ekliptor\PromptCash;

class DatabaseMigration {
	/** @var string Latest DB version. Only updated with plugin version if there are migrations. */
	protected $lastVersion;
	/** @var string */
	protected $currentVersion;
	/** @var array */
	protected $lastError = array();
	
	public function __construct(string $lastVersion, string $currentVersion) {
		if (!$lastVersion)
			$lastVersion = '1.0.0'; // when we added migration. shouldn't be needed
		$this->lastVersion = $lastVersion;
		$this->currentVersion = $currentVersion;
	}
	
	public static function checkAndMigrate() {
		$lastVersion = get_option('promptcash_version');
		if ($lastVersion === PRCA_VERSION)
			return;
		add_action('plugins_loaded', function() use ($lastVersion) {
			$migrate = new DatabaseMigration($lastVersion, PRCA_VERSION);
			try {
				if ($migrate->migrate() === false) {
					PromptCash::notifyErrorExt("Error ensuring latest DB version on migration", $migrate->getLastError());
					return;
				}
				update_option( 'promptcash_version', PRCA_VERSION ); // done in main class only after re-activation
			}
			catch (\Exception $e) {
				PromptCash::notifyErrorExt("Exception during DB migration: " . get_class(), $e->getMessage());
			}
		}, 200); // load after other plugins
	}
	
	public function migrate(): bool {
		$queries = array();
		if (version_compare ( $this->lastVersion, '1.0.13', '<' )) {
			$table = PromptCash::getTableName('transactions');
			if ($this->columnExists($table, 'post_id_btn') === false) {
				$queries[] = "TRUNCATE `$table`";
				$queries[] = "ALTER TABLE `$table` CHANGE `post_id` `post_id_btn` VARCHAR(20) NOT NULL DEFAULT ''";
			}
		}
		
		// TODO also add crons here if we add more later
		switch ($this->lastVersion) {
			// add migration queries in order from oldest version to newest
			//case '1.0.12': // never released
			//case '1.0.13':	
				
		}
		if (empty($queries))
			return true; // say successful
		return $this->runQueries($queries);
	}
	
	public function getLastError(): array {
		return $this->lastError;
	}
	
	protected function runQueries(array $queries): bool {
		global $wpdb;
		foreach ($queries as $query) {
			$result = $wpdb->query($query);
			if ($result === false) {
				$this->lastError = array(
						'query' => $query,
						'error' => $wpdb->last_error
				);
				return false; // abort
			}
		}
		return true;
	}
	
	protected function columnExists(string $table, string $column) {
		global $wpdb;
		$rows = $wpdb->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
		return empty($rows) ? false : true;
	}
}
?>