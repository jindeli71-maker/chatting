<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/filter.php';
header('Content-Type: application/json');

try {
$userId = current_user_id();
if (!$userId) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$pdo = get_pdo();
$action = $_REQUEST['action'] ?? '';

function getMessageReactions($pdo, $messageId, $isGroup = false) {
	try {
		$table = $isGroup ? 'group_message_reactions' : 'message_reactions';
		$stmt = $pdo->prepare("
			SELECT reaction, COUNT(*) as count
			FROM $table
			WHERE message_id = ?
			GROUP BY reaction
			ORDER BY count DESC
		");
		$stmt->execute([$messageId]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	} catch (Throwable $e) {
		return [];
	}
}

function getReplyInfo($pdo, $replyToId, $isGroup = false) {
	if (!$replyToId) return null;
	try {
		if ($isGroup) {
			$stmt = $pdo->prepare('
				SELECT gm.message_text, gm.message_type, gm.file_name, u.username
				FROM group_messages gm
				JOIN users u ON u.id = gm.sender_id
				WHERE gm.id = ? AND (gm.deleted_at IS NULL OR gm.deleted_at = "")
			');
		} else {
			$stmt = $pdo->prepare('
				SELECT m.message_text, m.message_type, m.file_name, u.username
				FROM messages m
				JOIN users u ON u.id = m.sender_id
				WHERE m.id = ? AND (m.deleted_at IS NULL OR m.deleted_at = "")
			');
		}
		$stmt->execute([$replyToId]);
		return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
	} catch (Throwable $e) {
		return null;
	}
}

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$peerId = (int)($_POST['peer_id'] ?? 0);
	$text = trim($_POST['text'] ?? '');
	$replyToId = (int)($_POST['reply_to_id'] ?? 0) ?: null;
	$messageType = $_POST['message_type'] ?? 'text';

	if ($text !== '') {
		$text = censor_bad_words($text);
	}

	if (!$peerId) {
		echo json_encode(['ok' => false, 'error' => 'No chat selected']);
		exit;
	}
	if ($text === '' && $messageType === 'text') {
		echo json_encode(['ok' => false, 'error' => 'Empty message']);
		exit;
	}

	$stmt = $pdo->prepare('SELECT 1 FROM friendships WHERE user_id = ? AND friend_id = ?');
	$stmt->execute([$userId, $peerId]);
	if (!$stmt->fetch()) {
		echo json_encode(['ok' => false, 'error' => 'Not friends with this user. Add them in Friends first.']);
		exit;
	}

	$stmt = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
	$stmt->execute([$peerId, $userId]);
	$isBlockedByPeer = (bool)$stmt->fetch();

	$ins = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, reply_to_message_id, message_type) VALUES (?, ?, ?, ?, ?)');
	$ins->execute([$userId, $peerId, $text, $replyToId, $messageType]);
	$messageId = $pdo->lastInsertId();

	if (!$isBlockedByPeer) {
		try {
			$stmt = $pdo->prepare('UPDATE messages SET delivery_status = ? WHERE id = ?');
			$stmt->execute(['delivered', $messageId]);
		} catch (Throwable $e) {
			// older schema
		}
	}

	if ($isBlockedByPeer) {
		echo json_encode(['ok' => true, 'blocked' => true, 'message' => 'Message sent but rejected by the other user.', 'message_id' => $messageId]);
	} else {
		echo json_encode(['ok' => true, 'blocked' => false, 'message_id' => $messageId]);
	}
	exit;
}

if ($action === 'fetch') {
	$peerId = (int)($_GET['peer_id'] ?? 0);
	$afterId = (int)($_GET['after_id'] ?? 0);
	if (!$peerId) {
		echo json_encode(['messages' => []]);
		exit;
	}

	$stmt = $pdo->prepare('SELECT 1 FROM friendships WHERE user_id = ? AND friend_id = ?');
	$stmt->execute([$userId, $peerId]);
	if (!$stmt->fetch()) {
		echo json_encode(['messages' => [], 'error' => 'Not friends']);
		exit;
	}

	$blockCheck = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
	$blockCheck->execute([$userId, $peerId]);
	$isBlocked = (bool)$blockCheck->fetch();

	$blockedByCheck = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
	$blockedByCheck->execute([$peerId, $userId]);
	$isBlockedBy = (bool)$blockedByCheck->fetch();

	if ($isBlocked || $isBlockedBy) {
		$q = $pdo->prepare('
			SELECT m.id, m.sender_id, m.receiver_id, m.message_text, m.message_type,
				   m.file_path, m.file_name, m.file_size, m.duration,
				   m.reply_to_message_id, m.edited_at, m.delivery_status, m.created_at
			FROM messages m
			WHERE m.sender_id = ? AND m.receiver_id = ? AND m.id > ?
			  AND (m.deleted_at IS NULL OR m.deleted_at = "")
			ORDER BY m.id ASC LIMIT 100
		');
		$q->execute([$userId, $peerId, $afterId]);
	} else {
		$q = $pdo->prepare('
			SELECT m.id, m.sender_id, m.receiver_id, m.message_text, m.message_type,
				   m.file_path, m.file_name, m.file_size, m.duration,
				   m.reply_to_message_id, m.edited_at, m.delivery_status, m.created_at
			FROM messages m
			WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
			  AND m.id > ? AND (m.deleted_at IS NULL OR m.deleted_at = "")
			ORDER BY m.id ASC LIMIT 100
		');
		$q->execute([$userId, $peerId, $peerId, $userId, $afterId]);
	}

	$rows = $q->fetchAll();
	$messages = [];
	foreach ($rows as $r) {
		$messageData = [
			'id' => (string)$r['id'],
			'sender' => ((int)$r['sender_id'] === (int)$userId) ? 'me' : 'them',
			'text' => $r['message_text'] ?? '',
			'type' => $r['message_type'] ?? 'text',
			'time' => date('g:i A', strtotime($r['created_at'])),
			'full_time' => $r['created_at'],
			'edited' => !empty($r['edited_at']),
			'delivery_status' => $r['delivery_status'] ?? 'sent'
		];

		if (!empty($r['file_path'])) {
			$messageData['file'] = [
				'path' => $r['file_path'],
				'name' => $r['file_name'],
				'size' => $r['file_size'],
				'duration' => $r['duration']
			];
		}

		if (!empty($r['reply_to_message_id'])) {
			$replyInfo = getReplyInfo($pdo, $r['reply_to_message_id'], false);
			if ($replyInfo) {
				$messageData['reply_to'] = [
					'id' => $r['reply_to_message_id'],
					'text' => $replyInfo['message_text'],
					'type' => $replyInfo['message_type'],
					'sender' => $replyInfo['username'],
					'file_name' => $replyInfo['file_name']
				];
			}
		}

		$messageData['reactions'] = getMessageReactions($pdo, $r['id'], false);
		$messages[] = $messageData;
	}

	if (!empty($messages) && !$isBlockedBy) {
		try {
			$stmt = $pdo->prepare('
				UPDATE messages
				SET delivery_status = ?
				WHERE sender_id = ? AND receiver_id = ? AND delivery_status != ?
			');
			$stmt->execute(['read', $peerId, $userId, 'read']);

			$stmt = $pdo->prepare('
				INSERT OR IGNORE INTO message_read_receipts (message_id, user_id)
				SELECT id, ? FROM messages
				WHERE sender_id = ? AND receiver_id = ? AND delivery_status = ?
			');
			$stmt->execute([$userId, $peerId, $userId, 'read']);
		} catch (Throwable $e) {
			// optional
		}
	}

	echo json_encode(['messages' => $messages]);
	exit;
}

if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$messageId = (int)($_POST['message_id'] ?? 0);
	$newText = trim($_POST['text'] ?? $_POST['new_text'] ?? '');
	if ($newText !== '') {
		$newText = censor_bad_words($newText);
	}

	if (!$messageId || !$newText) {
		echo json_encode(['ok' => false, 'error' => 'Message ID and text required']);
		exit;
	}

	$stmt = $pdo->prepare('
		SELECT created_at FROM messages
		WHERE id = ? AND sender_id = ? AND (deleted_at IS NULL OR deleted_at = "")
	');
	$stmt->execute([$messageId, $userId]);
	$message = $stmt->fetch();

	if (!$message) {
		echo json_encode(['ok' => false, 'error' => 'Message not found or not authorized']);
		exit;
	}

	if ((time() - strtotime($message['created_at'])) > 120) {
		echo json_encode(['ok' => false, 'error' => 'Message can only be edited within 2 minutes']);
		exit;
	}

	$stmt = $pdo->prepare('UPDATE messages SET message_text = ?, edited_at = CURRENT_TIMESTAMP WHERE id = ?');
	$stmt->execute([$newText, $messageId]);
	echo json_encode(['ok' => true]);
	exit;
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$messageId = (int)($_POST['message_id'] ?? 0);
	if (!$messageId) {
		echo json_encode(['ok' => false, 'error' => 'Message ID required']);
		exit;
	}

	$stmt = $pdo->prepare('
		SELECT created_at FROM messages
		WHERE id = ? AND sender_id = ? AND (deleted_at IS NULL OR deleted_at = "")
	');
	$stmt->execute([$messageId, $userId]);
	$message = $stmt->fetch();

	if (!$message) {
		echo json_encode(['ok' => false, 'error' => 'Message not found or not authorized']);
		exit;
	}

	if ((time() - strtotime($message['created_at'])) > 120) {
		echo json_encode(['ok' => false, 'error' => 'Message can only be deleted within 2 minutes']);
		exit;
	}

	$stmt = $pdo->prepare('
		UPDATE messages
		SET deleted_at = CURRENT_TIMESTAMP, message_text = "[Message deleted]"
		WHERE id = ?
	');
	$stmt->execute([$messageId]);
	echo json_encode(['ok' => true]);
	exit;
}

if ($action === 'typing' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$peerId = (int)($_POST['peer_id'] ?? 0);
	$isTyping = ($_POST['is_typing'] ?? '') === 'true' || ($_POST['is_typing'] ?? '') === '1';
	if (!$peerId) {
		echo json_encode(['ok' => false, 'error' => 'Peer ID required']);
		exit;
	}
	try {
		$stmt = $pdo->prepare('
			INSERT INTO typing_indicators (user_id, chat_type, chat_id, is_typing, updated_at)
			VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
			ON CONFLICT(user_id, chat_type, chat_id) DO UPDATE SET
				is_typing = excluded.is_typing,
				updated_at = CURRENT_TIMESTAMP
		');
		$stmt->execute([$userId, 'private', $peerId, $isTyping ? 1 : 0]);
	} catch (Throwable $e) {
		// optional
	}
	echo json_encode(['ok' => true]);
	exit;
}

if ($action === 'get_typing') {
	$peerId = (int)($_GET['peer_id'] ?? 0);
	if (!$peerId) {
		echo json_encode(['typing' => false]);
		exit;
	}
	try {
		$stmt = $pdo->prepare('
			SELECT is_typing
			FROM typing_indicators
			WHERE user_id = ? AND chat_type = ? AND chat_id = ?
			  AND is_typing = 1 AND updated_at > datetime("now", "-5 seconds")
		');
		$stmt->execute([$peerId, 'private', $userId]);
		$isTyping = (bool)$stmt->fetchColumn();
	} catch (Throwable $e) {
		$isTyping = false;
	}
	echo json_encode(['typing' => $isTyping]);
	exit;
}

echo json_encode(['error' => 'Unknown action']);

} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
