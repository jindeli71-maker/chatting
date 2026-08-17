<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
header('Content-Type: application/json');

$userId = current_user_id();
if (!$userId) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }

$pdo = get_pdo();
$action = $_REQUEST['action'] ?? '';

if ($action === 'like' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$postId = (int)($_POST['post_id'] ?? 0);
	if ($postId) {
		try {
			$stmt = $pdo->prepare('INSERT IGNORE INTO post_likes (post_id, user_id) VALUES (?, ?)');
			$stmt->execute([$postId, $userId]);
			echo json_encode(['success' => true]);
		} catch (Exception $e) {
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}
}

if ($action === 'unlike' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$postId = (int)($_POST['post_id'] ?? 0);
	if ($postId) {
		$stmt = $pdo->prepare('DELETE FROM post_likes WHERE post_id = ? AND user_id = ?');
		$stmt->execute([$postId, $userId]);
		echo json_encode(['success' => true]);
		exit;
	}
}

if ($action === 'comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$postId = (int)($_POST['post_id'] ?? 0);
	$content = trim($_POST['content'] ?? '');
	if ($postId && $content !== '') {
		try {
			$stmt = $pdo->prepare('INSERT INTO post_comments (post_id, author_id, content) VALUES (?, ?, ?)');
			$stmt->execute([$postId, $userId, $content]);
			echo json_encode(['success' => true, 'comment_id' => $pdo->lastInsertId()]);
		} catch (Exception $e) {
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}
}

if ($action === 'share' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$postId = (int)($_POST['post_id'] ?? 0);
	$friendId = (int)($_POST['friend_id'] ?? 0);
	$message = trim($_POST['message'] ?? '');
	if ($postId && $friendId) {
		try {
			$stmt = $pdo->prepare('INSERT INTO post_shares (post_id, sharer_id, shared_with_id, message) VALUES (?, ?, ?, ?)');
			$stmt->execute([$postId, $userId, $friendId, $message]);
			echo json_encode(['success' => true]);
		} catch (Exception $e) {
			echo json_encode(['success' => false, 'error' => $e->getMessage()]);
		}
		exit;
	}
}

echo json_encode(['error' => 'Unknown action']);
