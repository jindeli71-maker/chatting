<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();

echo "<h1>Block Debug Page</h1>";
echo "<p>Current User ID: " . $userId . "</p>";

// Get all users
$usersStmt = $pdo->query('SELECT id, username FROM users ORDER BY id');
$users = $usersStmt->fetchAll();

echo "<h2>All Users and Block Status:</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Username</th><th>I Blocked Them</th><th>They Blocked Me</th><th>Actions</th></tr>";

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
    
    echo "<tr>";
    echo "<td>" . $user['id'] . "</td>";
    echo "<td>" . htmlspecialchars($user['username']) . "</td>";
    echo "<td>" . ($isBlocked ? 'YES' : 'NO') . "</td>";
    echo "<td>" . ($isBlockedBy ? 'YES' : 'NO') . "</td>";
    echo "<td>";
    echo "<a href='chat.php?user_id=" . $user['id'] . "'>Chat</a> | ";
    if ($isBlocked) {
        echo "<a href='block_api.php' onclick='unblockUser(" . $user['id'] . "); return false;'>Unblock</a>";
    } else {
        echo "<a href='block_api.php' onclick='blockUser(" . $user['id'] . "); return false;'>Block</a>";
    }
    echo "</td>";
    echo "</tr>";
}

echo "</table>";

// Show all block relationships
echo "<h2>All Block Relationships:</h2>";
$blocksStmt = $pdo->query('SELECT * FROM user_blocks ORDER BY created_at DESC');
$blocks = $blocksStmt->fetchAll();

if (empty($blocks)) {
    echo "<p>No block relationships found.</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Blocker ID</th><th>Blocked ID</th><th>Created At</th></tr>";
    foreach ($blocks as $block) {
        echo "<tr>";
        echo "<td>" . $block['id'] . "</td>";
        echo "<td>" . $block['blocker_id'] . "</td>";
        echo "<td>" . $block['blocked_id'] . "</td>";
        echo "<td>" . $block['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>

<script>
function blockUser(userId) {
    fetch('block_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'block', user_id: userId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok) {
            alert('User blocked successfully');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    });
}

function unblockUser(userId) {
    fetch('block_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'unblock', user_id: userId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.ok) {
            alert('User unblocked successfully');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    });
}
</script>
