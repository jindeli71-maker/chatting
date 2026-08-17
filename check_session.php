<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

echo "<h2>🔍 Current Session Check</h2>";

$currentUserId = current_user_id();
if ($currentUserId) {
    $pdo = get_pdo();
    $userStmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $userStmt->execute([$currentUserId]);
    $username = $userStmt->fetchColumn();
    
    echo "<p>✅ Logged in as: $username (ID: $currentUserId)</p>";
    
    // Check if this is admin user
    if ($username === 'admin') {
        echo "<p>🎉 You are the admin user!</p>";
        
        // Show available friends
        $friendsStmt = $pdo->prepare('
            SELECT u.id, u.username 
            FROM friendships f 
            JOIN users u ON u.id = f.friend_id 
            WHERE f.user_id = ?
        ');
        $friendsStmt->execute([$currentUserId]);
        $friends = $friendsStmt->fetchAll();
        
        echo "<h3>👥 Your Friends (" . count($friends) . "):</h3>";
        foreach ($friends as $friend) {
            $chatUrl = "chat.php?user_id={$friend['id']}";
            echo "<p>💬 <a href='$chatUrl' style='background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px;'>Chat with {$friend['username']}</a></p>";
        }
    }
} else {
    echo "<p>❌ Not logged in</p>";
    echo "<p><a href='login.php'>Please login</a></p>";
}

// Show all users for reference
$pdo = get_pdo();
$allUsers = $pdo->query('SELECT id, username FROM users ORDER BY id')->fetchAll();

echo "<h3>📋 All Users in Database:</h3>";
foreach ($allUsers as $user) {
    echo "<p>- {$user['username']} (ID: {$user['id']})</p>";
}
?>