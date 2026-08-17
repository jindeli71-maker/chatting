<?php
require_once __DIR__ . '/config.php';

/**
 * Returns a singleton PDO instance.
 */
function get_pdo(): PDO {
	static $pdo = null;
	if ($pdo !== null) {
		return $pdo;
	}
	
	if (defined('USE_SQLITE') && USE_SQLITE) {
		// Create database directory if it doesn't exist
		$dbDir = dirname(SQLITE_PATH);
		if (!is_dir($dbDir)) {
			mkdir($dbDir, 0755, true);
		}
		
		$dsn = 'sqlite:' . SQLITE_PATH;
		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		];
		$pdo = new PDO($dsn, null, null, $options);
		
		// Enable foreign key constraints for SQLite
		$pdo->exec('PRAGMA foreign_keys = ON');
	} else {
		// MySQL configuration
		$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
		$options = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
		];
		$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
	}
	
	return $pdo;
}
