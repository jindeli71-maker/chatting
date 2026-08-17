<?php
require_once 'includes/db.php';

try {
    $pdo = get_pdo();
    
    echo "Adding sample friend data for testing...\n";
    
    // Get users
    $stmt = $pdo->query("SELECT id, username FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    if (count($users) >= 3) {
        $jasper = $users[0]; // jasper
        $alice = $users[1];  // alice
        $bob = $users[2];    // bob
        
        echo "Found users:\n";
        foreach ($users as $user) {
            echo "- " . $user['username'] . " (ID: " . $user['id'] . ")\n";
        }
        
        // Add sample friend request (bob wants to be friends with jasper)
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO friend_requests (requester_id, receiver_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$bob['id'], $jasper['id']]);
        echo "✅ Added friend request: " . $bob['username'] . " → " . $jasper['username'] . "\n";
        
        // Add sample friendship (jasper and alice are friends)
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO friendships (user_id, friend_id) VALUES (?, ?), (?, ?)");
        $stmt->execute([$jasper['id'], $alice['id'], $alice['id'], $jasper['id']]);
        echo "✅ Added friendship: " . $jasper['username'] . " ↔ " . $alice['username'] . "\n";
        
        // Check current state
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM friend_requests WHERE status = 'pending'");
        $result = $stmt->fetch();
        echo "📋 Pending requests: " . $result['count'] . "\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM friendships");
        $result = $stmt->fetch();
        echo "👥 Total friendships: " . $result['count'] . "\n";
        
        echo "\n✅ Sample data added successfully!\n";
        echo "🌐 Now test: http://localhost/Chatting/friends.php\n";
        
    } else {
        echo "❌ Need at least 3 users for testing\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>