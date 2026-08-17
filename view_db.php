<?php
/**
 * Simple SQLite viewer — open in browser:
 * http://localhost/Chatting/view_db.php
 * Delete this file after you finish checking / before public deploy.
 */
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

$path = SQLITE_PATH;
$exists = file_exists($path);
$size = $exists ? filesize($path) : 0;

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>SQLite Viewer</title>';
echo '<style>
body{font-family:Segoe UI,sans-serif;margin:24px;background:#f5f5f5;color:#222}
h1{margin:0 0 8px} .meta{color:#666;margin-bottom:20px}
table{border-collapse:collapse;background:#fff;margin:0 0 28px;width:100%;max-width:1000px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
th,td{border:1px solid #e5e5e5;padding:8px 10px;text-align:left;font-size:14px;vertical-align:top}
th{background:#06C755;color:#fff}
.card{background:#fff;padding:16px 18px;border-radius:10px;margin-bottom:16px;max-width:1000px}
.warn{background:#fff3cd;padding:12px 14px;border-radius:8px;margin-bottom:16px;max-width:1000px}
code{background:#eee;padding:2px 6px;border-radius:4px}
</style></head><body>';

echo '<h1>chatting.db contents</h1>';
echo '<div class="meta">Path: <code>' . htmlspecialchars($path) . '</code><br>';
echo 'Exists: ' . ($exists ? 'yes' : 'NO') . ' · Size: ' . number_format($size) . ' bytes</div>';

echo '<div class="warn"><strong>Why Cursor shows nothing:</strong> '
	. '<code>.db</code> is a binary SQLite file. Open this page in your browser instead, '
	. 'or use <em>DB Browser for SQLite</em>.</div>';

if (!$exists) {
	echo '<p>Database file missing.</p></body></html>';
	exit;
}

try {
	$pdo = get_pdo();

	echo '<div class="card"><h2>Tables</h2><table><tr><th>Table</th><th>Rows</th></tr>';
	foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $t) {
		$name = $t['name'];
		$count = (int)$pdo->query('SELECT COUNT(*) FROM "' . str_replace('"', '""', $name) . '"')->fetchColumn();
		echo '<tr><td>' . htmlspecialchars($name) . '</td><td>' . $count . '</td></tr>';
	}
	echo '</table></div>';

	echo '<div class="card"><h2>Users</h2><table><tr><th>ID</th><th>Username</th></tr>';
	foreach ($pdo->query('SELECT id, username FROM users ORDER BY id') as $u) {
		echo '<tr><td>' . (int)$u['id'] . '</td><td>' . htmlspecialchars($u['username']) . '</td></tr>';
	}
	echo '</table></div>';

	echo '<div class="card"><h2>Friendships</h2><table><tr><th>User</th><th>Friend</th></tr>';
	$fs = $pdo->query('
		SELECT a.username AS u, b.username AS f
		FROM friendships fr
		JOIN users a ON a.id = fr.user_id
		JOIN users b ON b.id = fr.friend_id
		ORDER BY a.username, b.username
	');
	$n = 0;
	foreach ($fs as $row) {
		echo '<tr><td>' . htmlspecialchars($row['u']) . '</td><td>' . htmlspecialchars($row['f']) . '</td></tr>';
		$n++;
	}
	if (!$n) echo '<tr><td colspan="2">(none)</td></tr>';
	echo '</table></div>';

	echo '<div class="card"><h2>Recent messages (last 20)</h2><table><tr><th>ID</th><th>From</th><th>To</th><th>Text</th><th>Time</th></tr>';
	$msg = $pdo->query('
		SELECT m.id, s.username AS sender, r.username AS receiver,
		       substr(COALESCE(m.message_text,""), 1, 80) AS text, m.created_at
		FROM messages m
		JOIN users s ON s.id = m.sender_id
		JOIN users r ON r.id = m.receiver_id
		ORDER BY m.id DESC LIMIT 20
	');
	$n = 0;
	foreach ($msg as $m) {
		echo '<tr><td>' . (int)$m['id'] . '</td><td>' . htmlspecialchars($m['sender']) . '</td><td>'
			. htmlspecialchars($m['receiver']) . '</td><td>' . htmlspecialchars($m['text']) . '</td><td>'
			. htmlspecialchars($m['created_at']) . '</td></tr>';
		$n++;
	}
	if (!$n) echo '<tr><td colspan="5">(no messages)</td></tr>';
	echo '</table></div>';

} catch (Throwable $e) {
	echo '<p style="color:#c00">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<p class="meta">Delete <code>view_db.php</code> before putting the site on a public server.</p>';
echo '</body></html>';
