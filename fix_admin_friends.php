<?php
require_once 'includes/db.php';

echo "<h2>🔧 Fix Admin User Friendships</h2>";

try {
    $pdo = get_pdo();
    
    // Check if admin user exists
    $adminStmt = $pdo->prepare('SELECT id, username FROM users WHERE id = 5');
    $adminStmt->execute();
    $admin = $adminStmt->fetch();
    
    if (!$admin) {
        echo "<p>❌ Admin user (ID: 5) not found</p>";
        echo "<p>Creating admin user...</p>";
        
        // Create admin user
        $createAdmin = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
        $createAdmin->execute(['admin', password_hash('admin', PASSWORD_DEFAULT)]);
        $adminId = $pdo->lastInsertId();
        echo "<p>✅ Created admin user with ID: $adminId</p>";
    } else {
        echo "<p>✅ Found admin user: {$admin['username']} (ID: {$admin['id']})</p>";
        $adminId = $admin['id'];
    }
    
    // Get all other users
    $otherUsers = $pdo->prepare('SELECT id, username FROM users WHERE id != ?');
    $otherUsers->execute([$adminId]);
    $users = $otherUsers->fetchAll();
    
    echo "<h3>👥 Adding friendships for admin user</h3>";
    
    foreach ($users as $user) {
        // Check if friendship already exists
        $friendshipCheck = $pdo->prepare('SELECT 1 FROM friendships WHERE user_id = ? AND friend_id = ?');
        $friendshipCheck->execute([$adminId, $user['id']]);
        
        if (!$friendshipCheck->fetch()) {
            // Add bidirectional friendship
            $pdo->prepare('INSERT INTO friendships (user_id, friend_id) VALUES (?, ?)')
                ->execute([$adminId, $user['id']]);
            $pdo->prepare('INSERT INTO friendships (user_id, friend_id) VALUES (?, ?)')
                ->execute([$user['id'], $adminId]);
            
            echo "<p>✅ Added friendship: admin ↔ {$user['username']}</p>";
        } else {
            echo "<p>ℹ️ Friendship already exists: admin ↔ {$user['username']}</p>";
        }
    }
    
    // Count total friendships for admin
    $friendCount = $pdo->prepare('SELECT COUNT(*) as count FROM friendships WHERE user_id = ?');
    $friendCount->execute([$adminId]);
    $count = $friendCount->fetch()['count'];
    
    echo "<h3>📊 Results</h3>";
    echo "<p>👥 Admin user now has $count friends</p>";
    echo "<p>🎉 Admin can now chat with all users!</p>";
    
    echo "<h3>🔗 Test Links</h3>";
    foreach ($users as $user) {
        $chatUrl = "chat.php?user_id={$user['id']}";
        echo "<p>💬 <a href='$chatUrl' target='_blank'>Chat with {$user['username']}</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>