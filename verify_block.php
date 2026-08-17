<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();

echo "<h1>Verify Block Status</h1>";
echo "<p>Current User ID: " . $userId . "</p>";

// Get all users except current user
$usersStmt = $pdo->prepare('SELECT id, username FROM users WHERE id != ? ORDER BY id');
$usersStmt->execute([$userId]);
$users = $usersStmt->fetchAll();

echo "<h2>Block Status for Each User:</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>User ID</th><th>Username</th><th>Blocked by Me</th><th>Blocked Me</th><th>Chat Link</th></tr>";

foreach ($users as $user) {
    // Check if current user has blocked this user
    $blockCheck = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
    $blockCheck->execute([$userId, $user['id']]);
    $isBlocked = (bool)$blockCheck->fetch();
    
    // Check if this user has blocked current user
    $blockedByCheck = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
    $blockedByCheck->execute([$user['id'], $userId]);
    $isBlockedBy = (bool)$blockedByCheck->fetch();
    
    echo "<tr>";
    echo "<td>" . $user['id'] . "</td>";
    echo "<td>" . htmlspecialchars($user['username']) . "</td>";
    echo "<td>" . ($isBlocked ? '<span style="color: red;">YES</span>' : '<span style="color: green;">NO</span>') . "</td>";
    echo "<td>" . ($isBlockedBy ? '<span style="color: red;">YES</span>' : '<span style="color: green;">NO</span>') . "</td>";
    echo "<td><a href='chat.php?user_id=" . $user['id'] . "'>Chat</a></td>";
    echo "</tr>";
}

echo "</table>";

// Show all block relationships
echo "<h2>All Block Relationships in Database:</h2>";
$blocksStmt = $pdo->query('SELECT ub.*, u1.username as blocker_name, u2.username as blocked_name FROM user_blocks ub JOIN users u1 ON u1.id = ub.blocker_id JOIN users u2 ON u2.id = ub.blocked_id ORDER BY ub.created_at DESC');
$blocks = $blocksStmt->fetchAll();

if (empty($blocks)) {
    echo "<p>No block relationships found.</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Blocker</th><th>Blocked</th><th>Created At</th></tr>";
    foreach ($blocks as $block) {
        echo "<tr>";
        echo "<td>" . $block['id'] . "</td>";
        echo "<td>" . htmlspecialchars($block['blocker_name']) . " (ID: " . $block['blocker_id'] . ")</td>";
        echo "<td>" . htmlspecialchars($block['blocked_name']) . " (ID: " . $block['blocked_id'] . ")</td>";
        echo "<td>" . $block['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>Test Steps:</h2>";
echo "<ol>";
echo "<li>Go to chat with any user</li>";
echo "<li>Open settings panel (gear icon)</li>";
echo "<li>Toggle the block switch ON</li>";
echo "<li>Refresh the page (F5)</li>";
echo "<li>Open settings panel again</li>";
echo "<li>The block switch should still be ON</li>";
echo "<li>If it's OFF, check the browser console for errors</li>";
echo "</ol>";
?>
