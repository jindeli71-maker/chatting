<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();

echo "<h1>Simple Block Test</h1>";
echo "<p>Current User ID: " . $userId . "</p>";

// Get all users except current user
$usersStmt = $pdo->prepare('SELECT id, username FROM users WHERE id != ? ORDER BY id');
$usersStmt->execute([$userId]);
$users = $usersStmt->fetchAll();

echo "<h2>Available Users:</h2>";
foreach ($users as $user) {
    echo "<p><a href='chat.php?user_id=" . $user['id'] . "'>" . htmlspecialchars($user['username']) . " (ID: " . $user['id'] . ")</a></p>";
}

// Quick block/unblock actions
if (isset($_GET['action']) && isset($_GET['target_id'])) {
    $action = $_GET['action'];
    $targetId = (int)$_GET['target_id'];
    
    if ($action === 'block') {
        // Block user
        $stmt = $pdo->prepare('INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)');
        $stmt->execute([$userId, $targetId]);
        echo "<p style='color: green;'>Blocked user ID: " . $targetId . "</p>";
    } elseif ($action === 'unblock') {
        // Unblock user
        $stmt = $pdo->prepare('DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
        $stmt->execute([$userId, $targetId]);
        echo "<p style='color: green;'>Unblocked user ID: " . $targetId . "</p>";
    }
}

// Show current block status
echo "<h2>Current Block Status:</h2>";
$blocksStmt = $pdo->prepare('SELECT ub.*, u.username FROM user_blocks ub JOIN users u ON u.id = ub.blocked_id WHERE ub.blocker_id = ?');
$blocksStmt->execute([$userId]);
$blocks = $blocksStmt->fetchAll();

if (empty($blocks)) {
    echo "<p>You haven't blocked any users.</p>";
} else {
    foreach ($blocks as $block) {
        echo "<p>Blocked: " . htmlspecialchars($block['username']) . " (ID: " . $block['blocked_id'] . ") - <a href='?action=unblock&target_id=" . $block['blocked_id'] . "'>Unblock</a></p>";
    }
}

echo "<h2>Block Actions:</h2>";
foreach ($users as $user) {
    echo "<p><a href='?action=block&target_id=" . $user['id'] . "'>Block " . htmlspecialchars($user['username']) . "</a></p>";
}

echo "<h2>Test Instructions:</h2>";
echo "<ol>";
echo "<li>Block a user using the links above</li>";
echo "<li>Go to chat with that user</li>";
echo "<li>Try sending messages - you should see '消息已发出，但被对方拒收了'</li>";
echo "<li>Switch to the blocked user's account</li>";
echo "<li>Check if they can see your messages (they shouldn't)</li>";
echo "</ol>";
?>
