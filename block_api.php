<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';
$targetUserId = (int)($_POST['user_id'] ?? 0);

if (!$targetUserId || $targetUserId === $userId) {
    echo json_encode(['ok' => false, 'error' => 'Invalid user']);
    exit;
}

try {
    if ($action === 'block') {
        // Check if already blocked
        $stmt = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
        $stmt->execute([$userId, $targetUserId]);
        
        if ($stmt->fetch()) {
            echo json_encode(['ok' => true, 'message' => 'User already blocked']);
            exit;
        }
        
        // Block user
        $stmt = $pdo->prepare('INSERT INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)');
        $stmt->execute([$userId, $targetUserId]);
        
        // Debug logging
        error_log("Block action: User $userId blocked user $targetUserId");
        
        // Don't remove friendship - keep them in friends list but blocked
        // This allows the blocked user to still appear in friends list
        // but messages will be rejected
        
        echo json_encode(['ok' => true, 'message' => 'User blocked successfully']);
        
    } elseif ($action === 'unblock') {
        // Unblock user
        $stmt = $pdo->prepare('DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
        $stmt->execute([$userId, $targetUserId]);
        
        // Debug logging
        error_log("Unblock action: User $userId unblocked user $targetUserId, rows affected: " . $stmt->rowCount());
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['ok' => true, 'message' => 'User unblocked successfully']);
        } else {
            echo json_encode(['ok' => false, 'error' => 'User was not blocked']);
        }
        
    } elseif ($action === 'check') {
        // Check if user is blocked
        $stmt = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
        $stmt->execute([$userId, $targetUserId]);
        $isBlocked = (bool)$stmt->fetch();
        
        // Debug logging
        error_log("Block check: User $userId checking if they blocked user $targetUserId: " . ($isBlocked ? 'YES' : 'NO'));
        
        echo json_encode(['ok' => true, 'blocked' => $isBlocked]);
        
    } else {
        echo json_encode(['ok' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log('Block API error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
