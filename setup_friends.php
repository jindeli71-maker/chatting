<?php
require_once 'includes/db.php';

try {
    $pdo = get_pdo();
    
    echo "Setting up friendships for testing...\n";
    
    // Get user IDs
    $users = $pdo->query("SELECT id, username FROM users ORDER BY id")->fetchAll();
    
    echo "Available users:\n";
    foreach ($users as $user) {
        echo "- {$user['username']} (ID: {$user['id']})\n";
    }
    
    if (count($users) >= 2) {
        $user1 = $users[0]; // jasper
        $user2 = $users[1]; // alice
        
        // Check if friendship already exists
        $stmt = $pdo->prepare('SELECT 1 FROM friendships WHERE user_id = ? AND friend_id = ?');
        $stmt->execute([$user1['id'], $user2['id']]);
        
        if (!$stmt->fetch()) {
            // Add friendship (both directions)
            $pdo->prepare('INSERT INTO friendships (user_id, friend_id) VALUES (?, ?)')
                ->execute([$user1['id'], $user2['id']]);
            $pdo->prepare('INSERT INTO friendships (user_id, friend_id) VALUES (?, ?)')
                ->execute([$user2['id'], $user1['id']]);
            
            echo "✅ Added friendship: {$user1['username']} ↔ {$user2['username']}\n";
        } else {
            echo "ℹ️ Friendship already exists: {$user1['username']} ↔ {$user2['username']}\n";
        }
        
        // If there's a third user, add friendship with user1
        if (count($users) >= 3) {
            $user3 = $users[2]; // bob
            
            $stmt = $pdo->prepare('SELECT 1 FROM friendships WHERE user_id = ? AND friend_id = ?');
            $stmt->execute([$user1['id'], $user3['id']]);
            
            if (!$stmt->fetch()) {
                $pdo->prepare('INSERT INTO friendships (user_id, friend_id) VALUES (?, ?)')
                    ->execute([$user1['id'], $user3['id']]);
                $pdo->prepare('INSERT INTO friendships (user_id, friend_id) VALUES (?, ?)')
                    ->execute([$user3['id'], $user1['id']]);
                
                echo "✅ Added friendship: {$user1['username']} ↔ {$user3['username']}\n";
            } else {
                echo "ℹ️ Friendship already exists: {$user1['username']} ↔ {$user3['username']}\n";
            }
        }
    }
    
    // Count total friendships
    $friendshipCount = $pdo->query("SELECT COUNT(*) as count FROM friendships")->fetch()['count'];
    echo "👥 Total friendships: $friendshipCount\n";
    
    echo "🎉 Friendship setup complete!\n";
    echo "🌐 You can now test messaging at: http://localhost/your-app/chat.php?peer_id=2\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>