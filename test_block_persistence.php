<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();

echo "<h1>Block Persistence Test</h1>";
echo "<p>Current User ID: " . $userId . "</p>";

// Get all users except current user
$usersStmt = $pdo->prepare('SELECT id, username FROM users WHERE id != ? ORDER BY id');
$usersStmt->execute([$userId]);
$users = $usersStmt->fetchAll();

echo "<h2>Available Users:</h2>";
foreach ($users as $user) {
    // Check if current user has blocked this user
    $blockCheck = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
    $blockCheck->execute([$userId, $user['id']]);
    $isBlocked = (bool)$blockCheck->fetch();
    
    echo "<p>";
    echo "<a href='chat.php?user_id=" . $user['id'] . "'>" . htmlspecialchars($user['username']) . " (ID: " . $user['id'] . ")</a> - ";
    echo "Status: " . ($isBlocked ? '<span style="color: red;">BLOCKED</span>' : '<span style="color: green;">Not Blocked</span>') . " - ";
    echo "<a href='?action=block&target_id=" . $user['id'] . "'>Block</a> | ";
    echo "<a href='?action=unblock&target_id=" . $user['id'] . "'>Unblock</a>";
    echo "</p>";
}

// Handle block/unblock actions
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
    
    echo "<script>setTimeout(() => location.reload(), 1000);</script>";
}

// Show all block relationships
echo "<h2>All Block Relationships in Database:</h2>";
$blocksStmt = $pdo->query('SELECT ub.*, u1.username as blocker_name, u2.username as blocked_name FROM user_blocks ub JOIN users u1 ON u1.id = ub.blocker_id JOIN users u2 ON u2.id = ub.blocked_id ORDER BY ub.created_at DESC');
$blocks = $blocksStmt->fetchAll();

if (empty($blocks)) {
    echo "<p>No block relationships found.</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Blocker</th><th>Blocked</th><th>Created At</th></tr>";
    foreach ($blocks as $block) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($block['blocker_name']) . " (ID: " . $block['blocker_id'] . ")</td>";
        echo "<td>" . htmlspecialchars($block['blocked_name']) . " (ID: " . $block['blocked_id'] . ")</td>";
        echo "<td>" . $block['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>Test Instructions:</h2>";
echo "<ol>";
echo "<li>Block a user using the links above</li>";
echo "<li>Go to chat with that user</li>";
echo "<li>Check if the block toggle is ON in the settings panel</li>";
echo "<li>Refresh the page</li>";
echo "<li>Check if the block toggle is still ON after refresh</li>";
echo "<li>If it's OFF, there's a persistence issue</li>";
echo "</ol>";
?>
