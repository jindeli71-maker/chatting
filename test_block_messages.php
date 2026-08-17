<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();

echo "<h1>Block Messages Test</h1>";
echo "<p>Current User ID: " . $userId . "</p>";

// Get all users
$usersStmt = $pdo->query('SELECT id, username FROM users ORDER BY id');
$users = $usersStmt->fetchAll();

echo "<h2>All Users:</h2>";
foreach ($users as $user) {
    echo "<p>ID: " . $user['id'] . " - " . htmlspecialchars($user['username']) . "</p>";
}

// Test message sending with different block scenarios
if (isset($_POST['test_scenario'])) {
    $scenario = $_POST['test_scenario'];
    $targetId = (int)$_POST['target_id'];
    $message = $_POST['message'] ?? 'Test message';
    
    echo "<h2>Testing Scenario: " . $scenario . "</h2>";
    echo "<p>Target User ID: " . $targetId . "</p>";
    echo "<p>Message: " . htmlspecialchars($message) . "</p>";
    
    // Clear any existing blocks
    $pdo->prepare('DELETE FROM user_blocks WHERE blocker_id = ? OR blocked_id = ?')->execute([$userId, $userId]);
    $pdo->prepare('DELETE FROM user_blocks WHERE blocker_id = ? OR blocked_id = ?')->execute([$targetId, $targetId]);
    
    if ($scenario === 'normal') {
        echo "<p style='color: green;'>Normal scenario - no blocking</p>";
    } elseif ($scenario === 'i_block_them') {
        // I block them
        $pdo->prepare('INSERT INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)')->execute([$userId, $targetId]);
        echo "<p style='color: orange;'>I blocked them</p>";
    } elseif ($scenario === 'they_block_me') {
        // They block me
        $pdo->prepare('INSERT INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)')->execute([$targetId, $userId]);
        echo "<p style='color: red;'>They blocked me</p>";
    }
    
    // Test message sending
    echo "<h3>Message Send Test:</h3>";
    
    // Simulate the API call
    $isBlockedByPeer = false;
    $stmt = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
    $stmt->execute([$targetId, $userId]);
    $isBlockedByPeer = (bool)$stmt->fetch();
    
    echo "<p>Am I blocked by them? " . ($isBlockedByPeer ? 'YES' : 'NO') . "</p>";
    
    if ($isBlockedByPeer) {
        echo "<p style='color: red;'>Message would be sent but marked as blocked!</p>";
        echo "<p>API would return: blocked=true, message='消息已发出，但被对方拒收了。'</p>";
    } else {
        echo "<p style='color: green;'>Message would be sent normally!</p>";
        echo "<p>API would return: blocked=false</p>";
    }
    
    // Test message fetching
    echo "<h3>Message Fetch Test:</h3>";
    
    $isBlocked = false;
    $stmt = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
    $stmt->execute([$userId, $targetId]);
    $isBlocked = (bool)$stmt->fetch();
    
    echo "<p>Did I block them? " . ($isBlocked ? 'YES' : 'NO') . "</p>";
    echo "<p>Did they block me? " . ($isBlockedByPeer ? 'YES' : 'NO') . "</p>";
    
    if ($isBlocked) {
        echo "<p style='color: orange;'>I blocked them - I would only see my own messages</p>";
    } elseif ($isBlockedByPeer) {
        echo "<p style='color: red;'>They blocked me - I would only see my own messages</p>";
    } else {
        echo "<p style='color: green;'>Normal - both users would see all messages</p>";
    }
}

// Show current block relationships
echo "<h2>Current Block Relationships:</h2>";
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
?>

<h2>Test Different Scenarios:</h2>
<form method="post">
    <p>Target User ID: 
        <select name="target_id">
            <?php foreach ($users as $user): ?>
                <?php if ($user['id'] != $userId): ?>
                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?> (ID: <?php echo $user['id']; ?>)</option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
    </p>
    <p>Message: <input type="text" name="message" value="Test message" required></p>
    <p>Scenario: 
        <select name="test_scenario">
            <option value="normal">Normal (no blocking)</option>
            <option value="i_block_them">I block them</option>
            <option value="they_block_me">They block me</option>
        </select>
    </p>
    <button type="submit">Test Scenario</button>
</form>
