<?php
/**
 * One-click repair for two-browser chat testing:
 * - Ensures mutual friendships between all existing one-way pairs
 * - Ensures messages table has columns required by chat_api.php
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

header('Content-Type: text/plain; charset=utf-8');
$pdo = get_pdo();
$fixed = [];

// Ensure mutual friendships
$rows = $pdo->query('SELECT user_id, friend_id FROM friendships')->fetchAll();
$have = [];
foreach ($rows as $r) {
	$have[$r['user_id'] . ':' . $r['friend_id']] = true;
}
$ins = $pdo->prepare('INSERT OR IGNORE INTO friendships (user_id, friend_id) VALUES (?, ?)');
foreach ($rows as $r) {
	$key = $r['friend_id'] . ':' . $r['user_id'];
	if (empty($have[$key])) {
		$ins->execute([$r['friend_id'], $r['user_id']]);
		$fixed[] = "Added reverse friendship {$r['friend_id']} <-> {$r['user_id']}";
	}
}

// Ensure message columns (SQLite)
$cols = [];
foreach ($pdo->query('PRAGMA table_info(messages)') as $c) {
	$cols[$c['name']] = true;
}
$alter = [
	'message_type' => "ALTER TABLE messages ADD COLUMN message_type TEXT DEFAULT 'text'",
	'file_path' => 'ALTER TABLE messages ADD COLUMN file_path VARCHAR(500)',
	'file_name' => 'ALTER TABLE messages ADD COLUMN file_name VARCHAR(255)',
	'file_size' => 'ALTER TABLE messages ADD COLUMN file_size INTEGER',
	'duration' => 'ALTER TABLE messages ADD COLUMN duration INTEGER',
	'reply_to_message_id' => 'ALTER TABLE messages ADD COLUMN reply_to_message_id INTEGER',
	'edited_at' => 'ALTER TABLE messages ADD COLUMN edited_at TIMESTAMP',
	'deleted_at' => 'ALTER TABLE messages ADD COLUMN deleted_at TIMESTAMP',
	'delivery_status' => "ALTER TABLE messages ADD COLUMN delivery_status TEXT DEFAULT 'sent'",
];
foreach ($alter as $name => $sql) {
	if (empty($cols[$name])) {
		try {
			$pdo->exec($sql);
			$fixed[] = "Added messages.$name";
		} catch (Throwable $e) {
			$fixed[] = "Skip messages.$name: " . $e->getMessage();
		}
	}
}

// Ensure helper tables exist
$pdo->exec('CREATE TABLE IF NOT EXISTS message_reactions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	message_id INTEGER NOT NULL,
	user_id INTEGER NOT NULL,
	reaction VARCHAR(10) NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE (message_id, user_id, reaction)
)');
$pdo->exec('CREATE TABLE IF NOT EXISTS message_read_receipts (
	message_id INTEGER NOT NULL,
	user_id INTEGER NOT NULL,
	read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (message_id, user_id)
)');
$pdo->exec('CREATE TABLE IF NOT EXISTS typing_indicators (
	user_id INTEGER NOT NULL,
	chat_type TEXT NOT NULL DEFAULT "private",
	chat_id INTEGER NOT NULL,
	is_typing INTEGER NOT NULL DEFAULT 0,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (user_id, chat_type, chat_id)
)');
$pdo->exec('CREATE TABLE IF NOT EXISTS user_blocks (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	blocker_id INTEGER NOT NULL,
	blocked_id INTEGER NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE (blocker_id, blocked_id)
)');

// Voice call support
$userCols = [];
foreach ($pdo->query('PRAGMA table_info(users)') as $c) {
	$userCols[$c['name']] = true;
}
if (empty($userCols['online_status'])) {
	$pdo->exec("ALTER TABLE users ADD COLUMN online_status TEXT DEFAULT 'offline'");
	$fixed[] = 'Added users.online_status';
}
if (empty($userCols['call_status'])) {
	$pdo->exec("ALTER TABLE users ADD COLUMN call_status TEXT DEFAULT 'available'");
	$fixed[] = 'Added users.call_status';
}
$pdo->exec('CREATE TABLE IF NOT EXISTS voice_call_sessions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	caller_id INTEGER NOT NULL,
	receiver_id INTEGER NOT NULL,
	call_type TEXT NOT NULL DEFAULT "voice",
	status TEXT NOT NULL DEFAULT "calling",
	room_id TEXT,
	started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	connected_at TIMESTAMP,
	ended_at TIMESTAMP,
	duration INTEGER DEFAULT 0
)');
$pdo->exec('CREATE TABLE IF NOT EXISTS voice_call_signals (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	call_id INTEGER NOT NULL,
	sender_id INTEGER NOT NULL,
	signal_type TEXT NOT NULL,
	payload TEXT NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)');
$fixed[] = 'Ensured voice_call_sessions + voice_call_signals';

echo "Repair complete.\n";
if (!$fixed) {
	echo "Nothing needed fixing.\n";
} else {
	foreach ($fixed as $line) echo "- $line\n";
}
echo "\nOpen chat.php with both accounts and try sending again.\n";
echo "You can delete this file (fix_chat.php) afterward.\n";
