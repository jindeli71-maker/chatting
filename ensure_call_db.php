<?php
$db = __DIR__ . '/database/chatting.db';
$p = new PDO('sqlite:' . $db);

foreach (['users','friendships','voice_call_sessions','voice_call_signals','user_blocks'] as $t) {
	$r = $p->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$t'")->fetch();
	echo "$t: " . ($r ? 'OK' : 'MISSING') . "\n";
}

echo "users cols:";
foreach ($p->query('PRAGMA table_info(users)') as $c) {
	echo ' ' . $c['name'];
}
echo "\n\nUsers:\n";
foreach ($p->query('SELECT id, username FROM users') as $u) {
	echo "  {$u['id']} = {$u['username']}\n";
}
echo "\nFriendships:\n";
foreach ($p->query('SELECT user_id, friend_id FROM friendships') as $f) {
	echo "  {$f['user_id']} -> {$f['friend_id']}\n";
}

// Ensure schema now
$cols = [];
foreach ($p->query('PRAGMA table_info(users)') as $c) $cols[$c['name']] = true;
if (empty($cols['call_status'])) {
	$p->exec("ALTER TABLE users ADD COLUMN call_status TEXT DEFAULT 'available'");
	echo "Added call_status\n";
}
if (empty($cols['online_status'])) {
	$p->exec("ALTER TABLE users ADD COLUMN online_status TEXT DEFAULT 'offline'");
	echo "Added online_status\n";
}
$p->exec('CREATE TABLE IF NOT EXISTS voice_call_sessions (
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
$p->exec('CREATE TABLE IF NOT EXISTS voice_call_signals (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	call_id INTEGER NOT NULL,
	sender_id INTEGER NOT NULL,
	signal_type TEXT NOT NULL,
	payload TEXT NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)');
$p->exec('CREATE TABLE IF NOT EXISTS user_blocks (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	blocker_id INTEGER NOT NULL,
	blocked_id INTEGER NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE (blocker_id, blocked_id)
)');

// Mutual friendships
$rows = $p->query('SELECT user_id, friend_id FROM friendships')->fetchAll(PDO::FETCH_ASSOC);
$have = [];
foreach ($rows as $r) $have[$r['user_id'].':'.$r['friend_id']] = true;
$ins = $p->prepare('INSERT OR IGNORE INTO friendships (user_id, friend_id) VALUES (?, ?)');
foreach ($rows as $r) {
	if (empty($have[$r['friend_id'].':'.$r['user_id']])) {
		$ins->execute([$r['friend_id'], $r['user_id']]);
		echo "Fixed friendship {$r['friend_id']} <-> {$r['user_id']}\n";
	}
}
echo "\nSchema ensure done.\n";
