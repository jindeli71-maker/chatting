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
        case 'edit_message':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $newText = trim($_POST['new_text'] ?? '');
            $messageType = $_POST['message_type'] ?? 'private'; // private or group

            if (!$messageId || !$newText) {
                throw new Exception('Message ID and new text required');
            }

            if ($messageType === 'private') {
                // Get original message and verify ownership
                $stmt = $pdo->prepare('
                    SELECT message_text, created_at, sender_id 
                    FROM messages 
                    WHERE id = ? AND sender_id = ? AND deleted_at IS NULL
                ');
                $stmt->execute([$messageId, $userId]);
                $message = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$message) {
                    throw new Exception('Message not found or you cannot edit this message');
                }

                // Check 2-minute edit window
                $created = strtotime($message['created_at']);
                $now = time();
                $twoMinutes = 2 * 60; // 2 minutes in seconds

                if (($now - $created) > $twoMinutes) {
                    throw new Exception('Cannot edit message after 2 minutes');
                }

                // Store original text in edit history
                $stmt = $pdo->prepare('
                    INSERT INTO message_edit_history (message_id, message_type, original_text)
                    VALUES (?, "private", ?)
                ');
                $stmt->execute([$messageId, $message['message_text']]);

                // Update message
                $stmt = $pdo->prepare('
                    UPDATE messages 
                    SET message_text = ?, edited_at = NOW() 
                    WHERE id = ? AND sender_id = ?
                ');
                $stmt->execute([$newText, $messageId, $userId]);

            } else {
                // Group message
                $stmt = $pdo->prepare('
                    SELECT message_text, created_at, sender_id 
                    FROM group_messages 
                    WHERE id = ? AND sender_id = ? AND deleted_at IS NULL
                ');
                $stmt->execute([$messageId, $userId]);
                $message = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$message) {
                    throw new Exception('Message not found or you cannot edit this message');
                }

                // Check 2-minute edit window
                $created = strtotime($message['created_at']);
                $now = time();
                $twoMinutes = 2 * 60;

                if (($now - $created) > $twoMinutes) {
                    throw new Exception('Cannot edit message after 2 minutes');
                }

                // Store original text in edit history
                $stmt = $pdo->prepare('
                    INSERT INTO message_edit_history (message_id, message_type, original_text)
                    VALUES (?, "group", ?)
                ');
                $stmt->execute([$messageId, $message['message_text']]);

                // Update message
                $stmt = $pdo->prepare('
                    UPDATE group_messages 
                    SET message_text = ?, edited_at = NOW() 
                    WHERE id = ? AND sender_id = ?
                ');
                $stmt->execute([$newText, $messageId, $userId]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Message edited successfully'
            ]);
            break;

        case 'delete_message':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $messageType = $_POST['message_type'] ?? 'private';
            $deleteType = $_POST['delete_type'] ?? 'for_me'; // for_me or for_everyone

            if (!$messageId) {
                throw new Exception('Message ID required');
            }

            if ($messageType === 'private') {
                // Get message and verify ownership
                $stmt = $pdo->prepare('
                    SELECT sender_id, created_at, receiver_id 
                    FROM messages 
                    WHERE id = ? AND deleted_at IS NULL
                ');
                $stmt->execute([$messageId]);
                $message = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$message) {
                    throw new Exception('Message not found');
                }

                // Check if user can delete this message
                $canDelete = ($message['sender_id'] == $userId);
                
                if ($deleteType === 'for_everyone') {
                    // Only sender can delete for everyone, and within 2 minutes
                    if ($message['sender_id'] != $userId) {
                        throw new Exception('Only sender can delete message for everyone');
                    }

                    $created = strtotime($message['created_at']);
                    $now = time();
                    $twoMinutes = 2 * 60;

                    if (($now - $created) > $twoMinutes) {
                        throw new Exception('Cannot delete message for everyone after 2 minutes');
                    }

                    // Mark as deleted for everyone
                    $stmt = $pdo->prepare('
                        UPDATE messages 
                        SET deleted_at = NOW(), message_text = "[This message was deleted]" 
                        WHERE id = ?
                    ');
                    $stmt->execute([$messageId]);
                } else {
                    // Delete for current user only (can be sender or receiver)
                    if (!$canDelete && $message['receiver_id'] != $userId) {
                        throw new Exception('You cannot delete this message');
                    }

                    // For personal deletion, we create a user-specific deletion record
                    // This would require a separate table, for now we'll mark as deleted
                    $stmt = $pdo->prepare('
                        UPDATE messages 
                        SET deleted_at = NOW() 
                        WHERE id = ? AND (sender_id = ? OR receiver_id = ?)
                    ');
                    $stmt->execute([$messageId, $userId, $userId]);
                }

            } else {
                // Group message
                $stmt = $pdo->prepare('
                    SELECT sender_id, created_at, group_id 
                    FROM group_messages 
                    WHERE id = ? AND deleted_at IS NULL
                ');
                $stmt->execute([$messageId]);
                $message = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$message) {
                    throw new Exception('Message not found');
                }

                if ($deleteType === 'for_everyone') {
                    // Only sender can delete for everyone, and within 2 minutes
                    if ($message['sender_id'] != $userId) {
                        throw new Exception('Only sender can delete message for everyone');
                    }

                    $created = strtotime($message['created_at']);
                    $now = time();
                    $twoMinutes = 2 * 60;

                    if (($now - $created) > $twoMinutes) {
                        throw new Exception('Cannot delete message for everyone after 2 minutes');
                    }

                    // Mark as deleted for everyone
                    $stmt = $pdo->prepare('
                        UPDATE group_messages 
                        SET deleted_at = NOW(), message_text = "[This message was deleted]" 
                        WHERE id = ?
                    ');
                    $stmt->execute([$messageId]);
                } else {
                    // For group messages, "delete for me" doesn't make much sense
                    // We'll still mark as deleted
                    $stmt = $pdo->prepare('
                        UPDATE group_messages 
                        SET deleted_at = NOW() 
                        WHERE id = ? AND sender_id = ?
                    ');
                    $stmt->execute([$messageId, $userId]);
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Message deleted successfully'
            ]);
            break;

        case 'pin_message':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $messageType = $_POST['message_type'] ?? 'private';
            $chatId = (int)($_POST['chat_id'] ?? 0); // peer_id for private, group_id for group

            if (!$messageId || !$chatId) {
                throw new Exception('Message ID and chat ID required');
            }

            // Check if message exists and user has access
            if ($messageType === 'private') {
                $stmt = $pdo->prepare('
                    SELECT 1 FROM messages 
                    WHERE id = ? AND (sender_id = ? OR receiver_id = ?) AND deleted_at IS NULL
                ');
                $stmt->execute([$messageId, $userId, $userId]);
            } else {
                // Check if user is in the group
                $stmt = $pdo->prepare('
                    SELECT 1 FROM group_messages gm
                    JOIN group_members gme ON gme.group_id = gm.group_id
                    WHERE gm.id = ? AND gme.user_id = ? AND gm.deleted_at IS NULL
                ');
                $stmt->execute([$messageId, $userId]);
            }

            if (!$stmt->fetch()) {
                throw new Exception('Message not found or access denied');
            }

            // Check if already pinned
            $stmt = $pdo->prepare('
                SELECT id FROM pinned_messages 
                WHERE message_id = ? AND message_type = ? AND user_id = ? AND is_active = 1
            ');
            $stmt->execute([$messageId, $messageType, $userId]);
            $existingPin = $stmt->fetch();

            if ($existingPin) {
                // Unpin message
                $stmt = $pdo->prepare('
                    UPDATE pinned_messages 
                    SET is_active = 0, unpinned_at = NOW() 
                    WHERE id = ?
                ');
                $stmt->execute([$existingPin['id']]);
                
                $action_performed = 'unpinned';
            } else {
                // Pin message
                $stmt = $pdo->prepare('
                    INSERT INTO pinned_messages (message_id, message_type, user_id, chat_id)
                    VALUES (?, ?, ?, ?)
                ');
                $stmt->execute([$messageId, $messageType, $userId, $chatId]);
                
                $action_performed = 'pinned';
            }

            echo json_encode([
                'success' => true,
                'action' => $action_performed,
                'message' => "Message {$action_performed} successfully"
            ]);
            break;

        case 'get_pinned_messages':
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $chatType = $_POST['chat_type'] ?? 'private';

            if (!$chatId) {
                throw new Exception('Chat ID required');
            }

            if ($chatType === 'private') {
                $stmt = $pdo->prepare('
                    SELECT pm.message_id, pm.pinned_at, m.message_text, m.message_type, 
                           m.file_name, m.created_at, u.username as sender_name
                    FROM pinned_messages pm
                    JOIN messages m ON m.id = pm.message_id
                    JOIN users u ON u.id = m.sender_id
                    WHERE pm.user_id = ? AND pm.chat_id = ? AND pm.message_type = "private" 
                          AND pm.is_active = 1 AND m.deleted_at IS NULL
                    ORDER BY pm.pinned_at DESC
                ');
                $stmt->execute([$userId, $chatId]);
            } else {
                $stmt = $pdo->prepare('
                    SELECT pm.message_id, pm.pinned_at, gm.message_text, gm.message_type, 
                           gm.file_name, gm.created_at, u.username as sender_name
                    FROM pinned_messages pm
                    JOIN group_messages gm ON gm.id = pm.message_id
                    JOIN users u ON u.id = gm.sender_id
                    WHERE pm.user_id = ? AND pm.chat_id = ? AND pm.message_type = "group" 
                          AND pm.is_active = 1 AND gm.deleted_at IS NULL
                    ORDER BY pm.pinned_at DESC
                ');
                $stmt->execute([$userId, $chatId]);
            }

            $pinnedMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'pinned_messages' => $pinnedMessages
            ]);
            break;

        case 'add_favorite':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $messageType = $_POST['message_type'] ?? 'private';

            if (!$messageId) {
                throw new Exception('Message ID required');
            }

            // Check if already favorited
            $stmt = $pdo->prepare('
                SELECT id FROM message_favorites 
                WHERE message_id = ? AND message_type = ? AND user_id = ?
            ');
            $stmt->execute([$messageId, $messageType, $userId]);
            $existingFavorite = $stmt->fetch();

            if ($existingFavorite) {
                // Remove from favorites
                $stmt = $pdo->prepare('
                    DELETE FROM message_favorites 
                    WHERE id = ?
                ');
                $stmt->execute([$existingFavorite['id']]);
                
                $action_performed = 'removed from favorites';
            } else {
                // Add to favorites
                $stmt = $pdo->prepare('
                    INSERT INTO message_favorites (message_id, message_type, user_id)
                    VALUES (?, ?, ?)
                ');
                $stmt->execute([$messageId, $messageType, $userId]);
                
                $action_performed = 'added to favorites';
            }

            echo json_encode([
                'success' => true,
                'action' => $action_performed,
                'message' => "Message {$action_performed}"
            ]);
            break;

        case 'get_edit_history':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $messageType = $_POST['message_type'] ?? 'private';

            if (!$messageId) {
                throw new Exception('Message ID required');
            }

            $stmt = $pdo->prepare('
                SELECT original_text, edited_at 
                FROM message_edit_history 
                WHERE message_id = ? AND message_type = ?
                ORDER BY edited_at ASC
            ');
            $stmt->execute([$messageId, $messageType]);
            $editHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'edit_history' => $editHistory
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