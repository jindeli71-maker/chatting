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
        case 'forward_message':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $sourceType = $_POST['source_type'] ?? 'private'; // private or group
            $targetType = $_POST['target_type'] ?? 'private'; // private or group
            $targetId = (int)($_POST['target_id'] ?? 0);
            $forwardMessage = trim($_POST['forward_message'] ?? '');

            if (!$messageId || !$targetId) {
                throw new Exception('Message ID and target ID required');
            }

            // Get original message
            if ($sourceType === 'private') {
                $stmt = $pdo->prepare('
                    SELECT m.*, u.username as sender_name
                    FROM messages m
                    JOIN users u ON u.id = m.sender_id
                    WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?) AND m.deleted_at IS NULL
                ');
                $stmt->execute([$messageId, $userId, $userId]);
            } else {
                // Group message
                $stmt = $pdo->prepare('
                    SELECT gm.*, u.username as sender_name
                    FROM group_messages gm
                    JOIN users u ON u.id = gm.sender_id
                    JOIN group_members gme ON gme.group_id = gm.group_id
                    WHERE gm.id = ? AND gme.user_id = ? AND gm.deleted_at IS NULL
                ');
                $stmt->execute([$messageId, $userId]);
            }

            $originalMessage = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$originalMessage) {
                throw new Exception('Original message not found or access denied');
            }

            // Verify access to target
            if ($targetType === 'private') {
                // Check friendship
                $stmt = $pdo->prepare('SELECT 1 FROM friendships WHERE user_id = ? AND friend_id = ?');
                $stmt->execute([$userId, $targetId]);
                if (!$stmt->fetch()) {
                    throw new Exception('Cannot forward to this user - not friends');
                }

                // Check if target has blocked sender
                $stmt = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
                $stmt->execute([$targetId, $userId]);
                if ($stmt->fetch()) {
                    throw new Exception('Cannot forward message - you are blocked by this user');
                }
            } else {
                // Check group membership
                $stmt = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
                $stmt->execute([$targetId, $userId]);
                if (!$stmt->fetch()) {
                    throw new Exception('Cannot forward to this group - not a member');
                }
            }

            // Create forwarded message text
            $forwardedText = "📤 Forwarded message from " . htmlspecialchars($originalMessage['sender_name']) . ":\n\n";
            
            if ($originalMessage['message_type'] === 'text') {
                $forwardedText .= $originalMessage['message_text'];
            } else {
                $forwardedText .= "[" . ucfirst($originalMessage['message_type']) . "]";
                if ($originalMessage['file_name']) {
                    $forwardedText .= " " . $originalMessage['file_name'];
                }
            }

            if ($forwardMessage) {
                $forwardedText = $forwardMessage . "\n\n" . $forwardedText;
            }

            // Insert forwarded message
            if ($targetType === 'private') {
                $stmt = $pdo->prepare('
                    INSERT INTO messages (sender_id, receiver_id, message_text, message_type)
                    VALUES (?, ?, ?, "text")
                ');
                $stmt->execute([$userId, $targetId, $forwardedText]);
                $newMessageId = $pdo->lastInsertId();
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO group_messages (group_id, sender_id, message_text, message_type)
                    VALUES (?, ?, ?, "text")
                ');
                $stmt->execute([$targetId, $userId, $forwardedText]);
                $newMessageId = $pdo->lastInsertId();
            }

            // Record forward tracking
            $stmt = $pdo->prepare('
                INSERT INTO message_forwards (
                    original_message_id, original_message_type, 
                    forwarded_to_user_id, forwarded_to_group_id,
                    forwarded_by_user_id, forwarded_message_id
                ) VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $messageId,
                $sourceType,
                $targetType === 'private' ? $targetId : null,
                $targetType === 'group' ? $targetId : null,
                $userId,
                $newMessageId
            ]);

            echo json_encode([
                'success' => true,
                'message_id' => $newMessageId,
                'message' => 'Message forwarded successfully'
            ]);
            break;

        case 'get_forward_targets':
            // Get list of friends and groups user can forward to
            
            // Get friends (not blocked)
            $stmt = $pdo->prepare('
                SELECT u.id, u.username, "private" as type
                FROM friendships f
                JOIN users u ON u.id = f.friend_id
                LEFT JOIN user_blocks ub ON ub.blocker_id = u.id AND ub.blocked_id = ?
                WHERE f.user_id = ? AND ub.id IS NULL
                ORDER BY u.username
            ');
            $stmt->execute([$userId, $userId]);
            $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get groups
            $stmt = $pdo->prepare('
                SELECT gc.id, gc.name as username, "group" as type
                FROM group_members gm
                JOIN group_chats gc ON gc.id = gm.group_id
                WHERE gm.user_id = ?
                ORDER BY gc.name
            ');
            $stmt->execute([$userId]);
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $targets = array_merge($friends, $groups);

            echo json_encode([
                'success' => true,
                'targets' => $targets
            ]);
            break;

        case 'get_forward_history':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $sourceType = $_POST['source_type'] ?? 'private';

            if (!$messageId) {
                throw new Exception('Message ID required');
            }

            // Get forward history for this message
            $stmt = $pdo->prepare('
                SELECT 
                    mf.*,
                    u1.username as forwarded_by_name,
                    u2.username as forwarded_to_user_name,
                    gc.name as forwarded_to_group_name
                FROM message_forwards mf
                JOIN users u1 ON u1.id = mf.forwarded_by_user_id
                LEFT JOIN users u2 ON u2.id = mf.forwarded_to_user_id
                LEFT JOIN group_chats gc ON gc.id = mf.forwarded_to_group_id
                WHERE mf.original_message_id = ? AND mf.original_message_type = ?
                ORDER BY mf.created_at DESC
            ');
            $stmt->execute([$messageId, $sourceType]);
            $forwards = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'forwards' => $forwards
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}