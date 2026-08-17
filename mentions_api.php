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
        case 'process_mentions':
            $groupId = (int)($_POST['group_id'] ?? 0);
            $messageText = $_POST['message_text'] ?? '';

            if (!$groupId || !$messageText) {
                throw new Exception('Group ID and message text required');
            }

            // Verify user is in the group
            $stmt = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
            $stmt->execute([$groupId, $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('Not a member of this group');
            }

            // Extract mentions from message text (@username format)
            preg_match_all('/@([a-zA-Z0-9_]+)/', $messageText, $matches);
            $mentionedUsernames = array_unique($matches[1]);

            if (empty($mentionedUsernames)) {
                echo json_encode([
                    'success' => true,
                    'mentioned_users' => [],
                    'processed_text' => $messageText
                ]);
                break;
            }

            // Get user IDs for mentioned usernames (only group members)
            $placeholders = str_repeat('?,', count($mentionedUsernames) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT u.id, u.username
                FROM users u
                JOIN group_members gm ON gm.user_id = u.id
                WHERE u.username IN ($placeholders) AND gm.group_id = ?
            ");
            $params = array_merge($mentionedUsernames, [$groupId]);
            $stmt->execute($params);
            $validUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $mentionedUserIds = [];
            $processedText = $messageText;

            // Replace valid mentions with formatted mentions
            foreach ($validUsers as $user) {
                $mentionedUserIds[] = $user['id'];
                $pattern = '/@' . preg_quote($user['username'], '/') . '\b/';
                $replacement = '<span class="mention" data-user-id="' . $user['id'] . '">@' . $user['username'] . '</span>';
                $processedText = preg_replace($pattern, $replacement, $processedText);
            }

            echo json_encode([
                'success' => true,
                'mentioned_users' => $mentionedUserIds,
                'processed_text' => $processedText,
                'valid_mentions' => $validUsers
            ]);
            break;

        case 'get_group_members':
            $groupId = (int)($_POST['group_id'] ?? 0);
            $query = trim($_POST['query'] ?? '');

            if (!$groupId) {
                throw new Exception('Group ID required');
            }

            // Verify user is in the group
            $stmt = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
            $stmt->execute([$groupId, $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('Not a member of this group');
            }

            // Get group members for mention autocomplete
            $sql = "
                SELECT u.id, u.username, u.status_message
                FROM users u
                JOIN group_members gm ON gm.user_id = u.id
                WHERE gm.group_id = ? AND u.id != ?
            ";
            $params = [$groupId, $userId];

            if ($query) {
                $sql .= " AND u.username LIKE ?";
                $params[] = '%' . $query . '%';
            }

            $sql .= " ORDER BY u.username LIMIT 20";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'members' => $members
            ]);
            break;

        case 'get_mention_notifications':
            // Get mentions for current user across all groups
            $stmt = $pdo->prepare("
                SELECT 
                    gm.id as message_id,
                    gm.message_text,
                    gm.created_at,
                    gc.name as group_name,
                    gc.id as group_id,
                    u.username as sender_name,
                    u.id as sender_id,
                    CASE WHEN gmrr.read_at IS NULL THEN 0 ELSE 1 END as is_read
                FROM group_messages gm
                JOIN group_chats gc ON gc.id = gm.group_id
                JOIN users u ON u.id = gm.sender_id
                LEFT JOIN group_message_read_receipts gmrr ON gmrr.message_id = gm.id AND gmrr.user_id = ?
                WHERE JSON_CONTAINS(gm.mentioned_users, JSON_QUOTE(?))
                  AND gm.sender_id != ?
                  AND gm.deleted_at IS NULL
                ORDER BY gm.created_at DESC
                LIMIT 50
            ");
            $stmt->execute([$userId, (string)$userId, $userId]);
            $mentions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'mentions' => $mentions
            ]);
            break;

        case 'mark_mention_read':
            $messageId = (int)($_POST['message_id'] ?? 0);

            if (!$messageId) {
                throw new Exception('Message ID required');
            }

            // Verify the message mentions this user
            $stmt = $pdo->prepare("
                SELECT 1 FROM group_messages 
                WHERE id = ? AND JSON_CONTAINS(mentioned_users, JSON_QUOTE(?))
            ");
            $stmt->execute([$messageId, (string)$userId]);
            
            if (!$stmt->fetch()) {
                throw new Exception('Message not found or you are not mentioned');
            }

            // Mark as read
            $stmt = $pdo->prepare("
                INSERT INTO group_message_read_receipts (message_id, user_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE read_at = NOW()
            ");
            $stmt->execute([$messageId, $userId]);

            echo json_encode([
                'success' => true,
                'message' => 'Mention marked as read'
            ]);
            break;

        case 'get_unread_mention_count':
            // Get count of unread mentions for badge
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM group_messages gm
                LEFT JOIN group_message_read_receipts gmrr ON gmrr.message_id = gm.id AND gmrr.user_id = ?
                WHERE JSON_CONTAINS(gm.mentioned_users, JSON_QUOTE(?))
                  AND gm.sender_id != ?
                  AND gm.deleted_at IS NULL
                  AND gmrr.read_at IS NULL
            ");
            $stmt->execute([$userId, (string)$userId, $userId]);
            $count = $stmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'unread_count' => (int)$count
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