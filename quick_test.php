<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();

echo "<h1>Quick Block Test</h1>";
echo "<p>Current User ID: " . $userId . "</p>";

// Get all users except current user
$usersStmt = $pdo->prepare('SELECT id, username FROM users WHERE id != ? ORDER BY id');
$usersStmt->execute([$userId]);
$users = $usersStmt->fetchAll();

echo "<h2>Users:</h2>";
foreach ($users as $user) {
    echo "<p>ID: " . $user['id'] . " - " . htmlspecialchars($user['username']) . "</p>";
}

// Test block action
if (isset($_GET['block'])) {
    $targetId = (int)$_GET['block'];
    
    // Block user
    $stmt = $pdo->prepare('INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)');
    $stmt->execute([$userId, $targetId]);
    
    echo "<p style='color: green;'>Blocked user ID: " . $targetId . "</p>";
}

// Test unblock action
if (isset($_GET['unblock'])) {
    $targetId = (int)$_GET['unblock'];
    
    // Unblock user
    $stmt = $pdo->prepare('DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
    $stmt->execute([$userId, $targetId]);
    
    echo "<p style='color: green;'>Unblocked user ID: " . $targetId . "</p>";
}

// Show current block status
echo "<h2>Current Block Status:</h2>";
$blocksStmt = $pdo->prepare('SELECT ub.*, u.username FROM user_blocks ub JOIN users u ON u.id = ub.blocked_id WHERE ub.blocker_id = ?');
$blocksStmt->execute([$userId]);
$blocks = $blocksStmt->fetchAll();

if (empty($blocks)) {
    echo "<p>No users blocked by you.</p>";
} else {
    foreach ($blocks as $block) {
        echo "<p>Blocked: " . htmlspecialchars($block['username']) . " (ID: " . $block['blocked_id'] . ") - <a href='?unblock=" . $block['blocked_id'] . "'>Unblock</a></p>";
    }
}

// Show who blocked you
echo "<h2>Users who blocked you:</h2>";
$blockedByStmt = $pdo->prepare('SELECT ub.*, u.username FROM user_blocks ub JOIN users u ON u.id = ub.blocker_id WHERE ub.blocked_id = ?');
$blockedByStmt->execute([$userId]);
$blockedBy = $blockedByStmt->fetchAll();

if (empty($blockedBy)) {
    echo "<p>No users have blocked you.</p>";
} else {
    foreach ($blockedBy as $block) {
        echo "<p>Blocked by: " . htmlspecialchars($block['username']) . " (ID: " . $block['blocker_id'] . ")</p>";
    }
}

echo "<h2>Actions:</h2>";
foreach ($users as $user) {
    echo "<p><a href='?block=" . $user['id'] . "'>Block " . htmlspecialchars($user['username']) . "</a></p>";
}
?>
