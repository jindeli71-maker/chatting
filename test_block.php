<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();

echo "<h1>Block Status Test</h1>";
echo "<p>Current User ID: " . $userId . "</p>";

// Get all users
$usersStmt = $pdo->query('SELECT id, username FROM users ORDER BY id');
$users = $usersStmt->fetchAll();

echo "<h2>All Users:</h2>";
foreach ($users as $user) {
    if ($user['id'] == $userId) continue;
    
    // Check if current user has blocked this user
    $blockCheck = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
    $blockCheck->execute([$userId, $user['id']]);
    $isBlocked = (bool)$blockCheck->fetch();
    
    // Check if this user has blocked current user
    $blockedByCheck = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
    $blockedByCheck->execute([$user['id'], $userId]);
    $isBlockedBy = (bool)$blockedByCheck->fetch();
    
    echo "<p>User: " . htmlspecialchars($user['username']) . " (ID: " . $user['id'] . ")</p>";
    echo "<p>I blocked them: " . ($isBlocked ? 'YES' : 'NO') . "</p>";
    echo "<p>They blocked me: " . ($isBlockedBy ? 'YES' : 'NO') . "</p>";
    echo "<hr>";
}

// Test message sending
if (isset($_POST['test_message'])) {
    $targetId = (int)$_POST['target_id'];
    $message = $_POST['message'];
    
    echo "<h2>Testing Message Send to User ID: " . $targetId . "</h2>";
    
    // Check if current user is blocked by target
    $stmt = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
    $stmt->execute([$targetId, $userId]);
    $isBlockedByPeer = (bool)$stmt->fetch();
    
    echo "<p>Am I blocked by them? " . ($isBlockedByPeer ? 'YES' : 'NO') . "</p>";
    
    if ($isBlockedByPeer) {
        echo "<p style='color: red;'>Message would be sent but marked as blocked!</p>";
    } else {
        echo "<p style='color: green;'>Message would be sent normally!</p>";
    }
}
?>

<form method="post">
    <h3>Test Message Send:</h3>
    <p>Target User ID: <input type="number" name="target_id" required></p>
    <p>Message: <input type="text" name="message" value="Test message" required></p>
    <button type="submit" name="test_message">Test Send</button>
</form>
