<?php
/**
 * Voice call self-test. Open while logged in:
 * http://localhost/Chatting/test_call.php?peer_id=OTHER_USER_ID
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

header('Content-Type: text/plain; charset=utf-8');
$pdo = get_pdo();
$me = current_user_id();
$peer = (int)($_GET['peer_id'] ?? 0);

echo "Logged in as user_id={$me}\n";
echo "peer_id={$peer}\n\n";

// Schema
foreach (['users','voice_call_sessions','voice_call_signals','friendships'] as $t) {
	$ok = (bool)$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote($t))->fetch();
	echo "table {$t}: " . ($ok ? 'OK' : 'MISSING') . "\n";
}

$userCols = [];
foreach ($pdo->query('PRAGMA table_info(users)') as $c) $userCols[$c['name']] = true;
echo "users.online_status: " . (isset($userCols['online_status']) ? 'OK' : 'MISSING') . "\n";
echo "users.call_status: " . (isset($userCols['call_status']) ? 'OK' : 'MISSING') . "\n\n";

if ($peer) {
	$f = $pdo->prepare('SELECT 1 FROM friendships WHERE user_id=? AND friend_id=?');
	$f->execute([$me, $peer]);
	echo "friendship me->peer: " . ($f->fetch() ? 'YES' : 'NO') . "\n";
	$f->execute([$peer, $me]);
	echo "friendship peer->me: " . ($f->fetch() ? 'YES' : 'NO') . "\n";
}

echo "\n=== simulate initiate_call via include logic ===\n";
$_POST = [
	'action' => 'initiate_call',
	'receiver_id' => (string)$peer,
	'call_type' => 'voice',
];
$_SERVER['REQUEST_METHOD'] = 'POST';

// Don't include API (it exits). Duplicate minimal ensure + insert test:
try {
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
	if ($peer) {
		$room = 'test_' . time();
		$pdo->prepare('INSERT INTO voice_call_sessions (caller_id, receiver_id, call_type, status, room_id) VALUES (?,?,?,?,?)')
			->execute([$me, $peer, 'voice', 'calling', $room]);
		$id = $pdo->lastInsertId();
		echo "Inserted test call id={$id} room={$room}\n";
		$pdo->prepare("UPDATE voice_call_sessions SET status='ended', ended_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
		echo "Cleaned up test call.\n";
	} else {
		echo "Pass ?peer_id=ID to test insert.\n";
	}
	echo "\nOK — open chat and call again after hard refresh.\n";
} catch (Throwable $e) {
	echo "ERROR: " . $e->getMessage() . "\n";
}
