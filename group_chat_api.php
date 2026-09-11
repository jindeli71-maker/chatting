<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/filter.php';
header('Content-Type: application/json');

$userId = current_user_id();
if (!$userId) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$pdo = get_pdo();
$action = $_REQUEST['action'] ?? '';

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$groupId = (int)($_POST['group_id'] ?? 0);
	$text = trim($_POST['text'] ?? '');
	
	// censor bad words before sending
	if ($text !== '') {
		$text = censor_bad_words($text);
	}

	if ($groupId && $text !== '') {
		// Verify user is member of group
		$stmt = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
		$stmt->execute([$groupId, $userId]);
		if ($stmt->fetch()) {
			$createdAt = now_utc_sql();
			$ins = $pdo->prepare('INSERT INTO group_messages (group_id, sender_id, message_text, created_at) VALUES (?, ?, ?, ?)');
			$ins->execute([$groupId, $userId, $text, $createdAt]);
			echo json_encode(['ok' => true, 'time' => format_message_time($createdAt), 'full_time' => $createdAt]);
			exit;
		}
	}
	echo json_encode(['ok' => false]);
	exit;
}

if ($action === 'fetch') {
	$groupId = (int)($_GET['group_id'] ?? 0);
	$afterId = (int)($_GET['after_id'] ?? 0);
	
	if ($groupId) {
		// Verify user is member of group
		$stmt = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
		$stmt->execute([$groupId, $userId]);
		if ($stmt->fetch()) {
			$q = $pdo->prepare('
				SELECT gm.id, gm.sender_id, gm.message_text, gm.created_at, u.username 
				FROM group_messages gm 
				JOIN users u ON u.id = gm.sender_id 
				WHERE gm.group_id = ? AND gm.id > ? 
				ORDER BY gm.id ASC 
				LIMIT 100
			');
			$q->execute([$groupId, $afterId]);
			$rows = $q->fetchAll();
			$messages = [];
			foreach ($rows as $r) {
				$messages[] = [
					'id' => (string)$r['id'],
					'sender' => $r['username'],
					'sender_id' => (int)$r['sender_id'],
					'text' => $r['message_text'],
					'time' => format_message_time($r['created_at']),
					'full_time' => $r['created_at'],
				];
			}
			echo json_encode(['messages' => $messages]);
			exit;
		}
	}
	echo json_encode(['messages' => []]);
	exit;
}

echo json_encode(['error' => 'Unknown action']);
