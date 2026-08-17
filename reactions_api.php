<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');
require_login();

$userId = current_user_id();
$pdo = get_pdo();

// Common emoji reactions
const ALLOWED_REACTIONS = ['👍', '👎', '❤️', '😂', '😮', '😢', '😡', '🙏', '👏', '🔥', '💯', '🤔', '😍', '🎉', '✅'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_reaction':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $reaction = $_POST['reaction'] ?? '';
            $chatType = $_POST['chat_type'] ?? 'private'; // private or group

            if (!$messageId || !$reaction) {
                throw new Exception('Message ID and reaction required');
            }

            if (!in_array($reaction, ALLOWED_REACTIONS)) {
                throw new Exception('Invalid reaction');
            }

            // Verify user has access to this message
            if ($chatType === 'private') {
                $stmt = $pdo->prepare('
                    SELECT COUNT(*) 
                    FROM messages 
                    WHERE id = ? AND (sender_id = ? OR receiver_id = ?) AND deleted_at IS NULL
                ');
                $stmt->execute([$messageId, $userId, $userId]);
                $table = 'message_reactions';
            } else {
                $stmt = $pdo->prepare('
                    SELECT COUNT(*) 
                    FROM group_messages gm 
                    JOIN group_members gme ON gme.group_id = gm.group_id 
                    WHERE gm.id = ? AND gme.user_id = ? AND gm.deleted_at IS NULL
                ');
                $stmt->execute([$messageId, $userId]);
                $table = 'group_message_reactions';
            }

            if ($stmt->fetchColumn() == 0) {
                throw new Exception('Message not found or access denied');
            }

            // Check if user already reacted with this emoji
            $stmt = $pdo->prepare("SELECT id FROM $table WHERE message_id = ? AND user_id = ? AND reaction = ?");
            $stmt->execute([$messageId, $userId, $reaction]);
            
            if ($stmt->fetch()) {
                // Remove existing reaction
                $stmt = $pdo->prepare("DELETE FROM $table WHERE message_id = ? AND user_id = ? AND reaction = ?");
                $stmt->execute([$messageId, $userId, $reaction]);
                $action_taken = 'removed';
            } else {
                // Add new reaction
                $stmt = $pdo->prepare("INSERT INTO $table (message_id, user_id, reaction) VALUES (?, ?, ?)");
                $stmt->execute([$messageId, $userId, $reaction]);
                $action_taken = 'added';
            }

            // Get updated reaction counts
            $stmt = $pdo->prepare("
                SELECT reaction, COUNT(*) as count 
                FROM $table 
                WHERE message_id = ? 
                GROUP BY reaction 
                ORDER BY count DESC
            ");
            $stmt->execute([$messageId]);
            $reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'action' => $action_taken,
                'reactions' => $reactions
            ]);
            break;

        case 'get_reactions':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $chatType = $_POST['chat_type'] ?? 'private';

            if (!$messageId) {
                throw new Exception('Message ID required');
            }

            $table = $chatType === 'private' ? 'message_reactions' : 'group_message_reactions';

            // Get reaction counts and user lists
            $stmt = $pdo->prepare("
                SELECT 
                    mr.reaction,
                    COUNT(*) as count,
                    GROUP_CONCAT(u.username ORDER BY mr.created_at) as users,
                    CASE WHEN SUM(CASE WHEN mr.user_id = ? THEN 1 ELSE 0 END) > 0 THEN 1 ELSE 0 END as user_reacted
                FROM $table mr 
                JOIN users u ON u.id = mr.user_id 
                WHERE mr.message_id = ? 
                GROUP BY mr.reaction 
                ORDER BY count DESC, mr.reaction
            ");
            $stmt->execute([$userId, $messageId]);
            $reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'reactions' => $reactions
            ]);
            break;

        case 'get_reaction_details':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $reaction = $_POST['reaction'] ?? '';
            $chatType = $_POST['chat_type'] ?? 'private';

            if (!$messageId || !$reaction) {
                throw new Exception('Message ID and reaction required');
            }

            $table = $chatType === 'private' ? 'message_reactions' : 'group_message_reactions';

            // Get users who reacted with this emoji
            $stmt = $pdo->prepare("
                SELECT 
                    u.username,
                    mr.created_at
                FROM $table mr 
                JOIN users u ON u.id = mr.user_id 
                WHERE mr.message_id = ? AND mr.reaction = ?
                ORDER BY mr.created_at
            ");
            $stmt->execute([$messageId, $reaction]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'reaction' => $reaction,
                'users' => $users
            ]);
            break;

        case 'remove_all_reactions':
            $messageId = (int)($_POST['message_id'] ?? 0);
            $chatType = $_POST['chat_type'] ?? 'private';

            if (!$messageId) {
                throw new Exception('Message ID required');
            }

            // Verify user is the sender of the message
            if ($chatType === 'private') {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE id = ? AND sender_id = ?');
                $table = 'message_reactions';
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM group_messages WHERE id = ? AND sender_id = ?');
                $table = 'group_message_reactions';
            }
            $stmt->execute([$messageId, $userId]);

            if ($stmt->fetchColumn() == 0) {
                throw new Exception('Only message sender can remove all reactions');
            }

            // Remove all reactions for this message
            $stmt = $pdo->prepare("DELETE FROM $table WHERE message_id = ?");
            $stmt->execute([$messageId]);

            echo json_encode([
                'success' => true,
                'message' => 'All reactions removed'
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