<?php
/**
 * Export chatting.db → MySQL dump for cPanel phpMyAdmin
 * Run: C:\xampp2\php\php.exe export_mysql.php
 * Output: cpanel_mysql.sql
 */
$sqlite = __DIR__ . '/database/chatting.db';
$outFile = __DIR__ . '/cpanel_mysql.sql';

$pdo = new PDO('sqlite:' . $sqlite);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$out = [];
$out[] = '-- Chatting app MySQL dump for cPanel phpMyAdmin';
$out[] = '-- Generated: ' . date('c');
$out[] = 'SET NAMES utf8mb4;';
$out[] = 'SET FOREIGN_KEY_CHECKS=0;';
$out[] = '';

// Preferred table order (parents first)
$preferred = [
	'users', 'friend_requests', 'friendships', 'user_blocks',
	'messages', 'message_reactions', 'message_read_receipts', 'typing_indicators',
	'group_chats', 'group_members', 'group_messages', 'group_message_reactions',
	'forums', 'forum_posts', 'post_likes', 'post_comments', 'post_shares',
	'voice_call_sessions', 'voice_call_signals',
];

$existing = [];
foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'") as $t) {
	$existing[$t['name']] = true;
}

$tables = [];
foreach ($preferred as $t) {
	if (isset($existing[$t])) $tables[] = $t;
}
foreach (array_keys($existing) as $t) {
	if (!in_array($t, $tables, true)) $tables[] = $t;
}

$typeMap = function (string $sqliteType, string $col): string {
	$t = strtoupper($sqliteType);
	if ($col === 'id' || str_ends_with($col, '_id')) {
		if (str_contains($t, 'INT')) return 'INT';
	}
	if (str_contains($t, 'INT')) return 'INT';
	if (str_contains($t, 'REAL') || str_contains($t, 'FLOA') || str_contains($t, 'DOUB')) return 'DOUBLE';
	if (str_contains($t, 'BLOB')) return 'BLOB';
	if (str_contains($t, 'CHAR') || str_contains($t, 'CLOB') || $t === 'TEXT' || $t === '') return 'TEXT';
	if (str_contains($t, 'TIMESTAMP') || str_contains($t, 'DATE')) return 'DATETIME';
	return 'TEXT';
};

foreach ($tables as $table) {
	$cols = [];
	$pk = [];
	foreach ($pdo->query("PRAGMA table_info(" . $pdo->quote($table) . ")") as $c) {
		// PRAGMA doesn't work with quote that way on table name - use bracket
	}
	$colsInfo = $pdo->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC);

	$colDefs = [];
	foreach ($colsInfo as $c) {
		$name = $c['name'];
		$type = $typeMap((string)$c['type'], $name);
		$notnull = ((int)$c['notnull'] === 1) ? ' NOT NULL' : '';
		$auto = '';
		if ((int)$c['pk'] === 1 && strtoupper($type) === 'INT' && $name === 'id') {
			$auto = ' AUTO_INCREMENT';
			$type = 'INT';
		}
		$default = '';
		if ($c['dflt_value'] !== null && $c['dflt_value'] !== '') {
			$d = $c['dflt_value'];
			if (preg_match('/^CURRENT_TIMESTAMP$/i', trim($d, "()"))) {
				$default = ' DEFAULT CURRENT_TIMESTAMP';
			} elseif (is_numeric($d)) {
				$default = ' DEFAULT ' . $d;
			} else {
				$d = trim($d, "'\"");
				$default = " DEFAULT '" . str_replace("'", "''", $d) . "'";
			}
		}
		$colDefs[] = "  `$name` $type$notnull$default$auto";
		if ((int)$c['pk'] > 0) $pk[(int)$c['pk']] = $name;
	}
	ksort($pk);
	$pkSql = '';
	if ($pk) {
		$pkSql = ",\n  PRIMARY KEY (`" . implode('`,`', array_values($pk)) . "`)";
	}

	$out[] = "DROP TABLE IF EXISTS `$table`;";
	$out[] = "CREATE TABLE `$table` (";
	$out[] = implode(",\n", $colDefs) . $pkSql;
	$out[] = ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
	$out[] = '';

	// Data
	$rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
	if (!$rows) continue;

	$colNames = array_keys($rows[0]);
	$colList = '`' . implode('`, `', $colNames) . '`';

	foreach (array_chunk($rows, 50) as $chunk) {
		$values = [];
		foreach ($chunk as $row) {
			$vals = [];
			foreach ($colNames as $cn) {
				$v = $row[$cn];
				if ($v === null) $vals[] = 'NULL';
				else $vals[] = $pdo->quote((string)$v);
			}
			$values[] = '(' . implode(', ', $vals) . ')';
		}
		$out[] = "INSERT INTO `$table` ($colList) VALUES";
		$out[] = implode(",\n", $values) . ';';
		$out[] = '';
	}
}

$out[] = 'SET FOREIGN_KEY_CHECKS=1;';
file_put_contents($outFile, implode("\n", $out));
echo "Wrote $outFile (" . number_format(filesize($outFile)) . " bytes)\n";
echo "Tables exported: " . count($tables) . "\n";
