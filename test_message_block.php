<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();

echo "<h1>Message Block Test</h1>";
echo "<p>Current User ID: " . $userId . "</p>";

// Get all users
$usersStmt = $pdo->query('SELECT id, username FROM users ORDER BY id');
$users = $usersStmt->fetchAll();

echo "<h2>Test Message Sending</h2>";

foreach ($users as $user) {
    if ($user['id'] == $userId) continue;
    
    echo "<h3>Testing with User: " . htmlspecialchars($user['username']) . " (ID: " . $user['id'] . ")</h3>";
    
    // Check if current user has blocked this user
    $blockCheck = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
    $blockCheck->execute([$userId, $user['id']]);
    $isBlocked = (bool)$blockCheck->fetch();
    
    // Check if this user has blocked current user
    $blockedByCheck = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
    $blockedByCheck->execute([$user['id'], $userId]);
    $isBlockedBy = (bool)$blockedByCheck->fetch();
    
    echo "<p>I blocked them: " . ($isBlocked ? 'YES' : 'NO') . "</p>";
    echo "<p>They blocked me: " . ($isBlockedBy ? 'YES' : 'NO') . "</p>";
    
    // Test message sending logic
    $testMessage = "Test message to " . $user['username'];
    
    // Simulate the chat_api.php logic
    if ($isBlockedBy) {
        echo "<p style='color: red;'>MESSAGE WOULD BE BLOCKED - They blocked me</p>";
        echo "<p>API would return: blocked=true, message='消息已发出，但被对方拒收了。'</p>";
    } else {
        echo "<p style='color: green;'>MESSAGE WOULD BE SENT NORMALLY</p>";
        echo "<p>API would return: blocked=false</p>";
    }
    
    echo "<hr>";
}

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

// Quick block/unblock actions
if (isset($_POST['action']) && isset($_POST['target_id'])) {
    $action = $_POST['action'];
    $targetId = (int)$_POST['target_id'];
    
    if ($action === 'block') {
        $stmt = $pdo->prepare('INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)');
        $stmt->execute([$userId, $targetId]);
        echo "<p style='color: green;'>User blocked successfully!</p>";
    } elseif ($action === 'unblock') {
        $stmt = $pdo->prepare('DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
        $stmt->execute([$userId, $targetId]);
        echo "<p style='color: green;'>User unblocked successfully!</p>";
    }
    
    echo "<script>setTimeout(() => location.reload(), 1000);</script>";
}
?>

<h2>Quick Actions:</h2>
<?php foreach ($users as $user): ?>
    <?php if ($user['id'] == $userId) continue; ?>
    <p>
        <strong><?php echo htmlspecialchars($user['username']); ?>:</strong>
        <a href="?action=block&target_id=<?php echo $user['id']; ?>" onclick="return confirm('Block this user?')">Block</a> |
        <a href="?action=unblock&target_id=<?php echo $user['id']; ?>" onclick="return confirm('Unblock this user?')">Unblock</a>
    </p>
<?php endforeach; ?>
