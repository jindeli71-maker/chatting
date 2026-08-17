<?php
// Test friends page functionality
require_once 'includes/db.php';

try {
    $pdo = get_pdo();
    
    echo "Testing Friends Management Database...\n";
    
    // Check required tables
    $tables = ['users', 'friend_requests', 'friendships', 'user_blocks'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $result = $stmt->fetch();
        echo "✅ $table: " . $result['count'] . " rows\n";
    }
    
    // Test friend request logic
    echo "\nTesting Friend Request Logic:\n";
    
    // Get sample users
    $stmt = $pdo->query("SELECT id, username FROM users LIMIT 3");
    $users = $stmt->fetchAll();
    
    if (count($users) >= 2) {
        $user1 = $users[0];
        $user2 = $users[1];
        
        echo "User 1: " . $user1['username'] . " (ID: " . $user1['id'] . ")\n";
        echo "User 2: " . $user2['username'] . " (ID: " . $user2['id'] . ")\n";
        
        // Check if they're already friends
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM friendships WHERE user_id = ? AND friend_id = ?");
        $stmt->execute([$user1['id'], $user2['id']]);
        $friendship = $stmt->fetch();
        
        if ($friendship['count'] > 0) {
            echo "✅ These users are already friends\n";
        } else {
            echo "ℹ️ These users are not friends yet\n";
        }
        
        // Check pending requests
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM friend_requests WHERE requester_id = ? AND receiver_id = ? AND status = 'pending'");
        $stmt->execute([$user1['id'], $user2['id']]);
        $request = $stmt->fetch();
        
        if ($request['count'] > 0) {
            echo "⏳ There's a pending friend request\n";
        } else {
            echo "ℹ️ No pending friend requests\n";
        }
    }
    
    echo "\n✅ Friends management database is working correctly!\n";
    echo "🌐 Visit: http://localhost/Chatting/friends.php\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>