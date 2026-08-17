<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');
require_login();

$userId = current_user_id();
$pdo = get_pdo();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'send_voice':
            $peerId = (int)($_POST['peer_id'] ?? 0);
            $duration = (int)($_POST['duration'] ?? 0);
            
            if (!$peerId) {
                throw new Exception('Peer ID required');
            }

            if (!isset($_FILES['voice']) || $_FILES['voice']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Voice file upload failed');
            }

            // Create uploads directory if it doesn't exist
            $uploadDir = __DIR__ . '/uploads/voices/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $fileName = 'voice_' . time() . '_' . $userId . '.webm';
            $filePath = $uploadDir . $fileName;
            $relativeFilePath = 'voices/' . $fileName;

            // Move uploaded file
            if (!move_uploaded_file($_FILES['voice']['tmp_name'], $filePath)) {
                throw new Exception('Failed to save voice file');
            }

            // Check if current user is blocked by peer
            $stmt = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
            $stmt->execute([$peerId, $userId]);
            $isBlockedByPeer = (bool)$stmt->fetch();

            // Verify friendship
            $stmt = $pdo->prepare('SELECT 1 FROM friendships WHERE user_id = ? AND friend_id = ?');
            $stmt->execute([$userId, $peerId]);
            if (!$stmt->fetch()) {
                throw new Exception('You can only send voice messages to friends');
            }

            // Insert voice message
            $stmt = $pdo->prepare('
                INSERT INTO messages (sender_id, receiver_id, message_text, message_type, file_path, file_name, duration, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $userId, 
                $peerId, 
                '[Voice Message]', 
                'voice', 
                $relativeFilePath, 
                $fileName, 
                $duration
            ]);

            $messageId = $pdo->lastInsertId();

            // Update delivery status if not blocked
            if (!$isBlockedByPeer) {
                $stmt = $pdo->prepare('UPDATE messages SET delivery_status = "delivered" WHERE id = ?');
                $stmt->execute([$messageId]);
            }

            echo json_encode([
                'success' => true,
                'message_id' => $messageId,
                'blocked' => $isBlockedByPeer,
                'message' => $isBlockedByPeer ? 'Voice message sent but was blocked by recipient' : 'Voice message sent successfully'
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}