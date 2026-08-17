<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

echo "<h2>🔐 Admin Login</h2>";

// Auto-login as admin
if (!current_user_id()) {
    $pdo = get_pdo();
    $adminStmt = $pdo->prepare('SELECT id, username FROM users WHERE username = ?');
    $adminStmt->execute(['admin']);
    $admin = $adminStmt->fetch();
    
    if ($admin) {
        login_user($admin);
        echo "<p>✅ Automatically logged in as admin!</p>";
    } else {
        echo "<p>❌ Admin user not found</p>";
    }
}

// Check current login
$currentUserId = current_user_id();
if ($currentUserId) {
    $pdo = get_pdo();
    $userStmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $userStmt->execute([$currentUserId]);
    $username = $userStmt->fetchColumn();
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3>🎉 Successfully logged in as: $username (ID: $currentUserId)</h3>";
    echo "</div>";
    
    // Show available friends for chatting
    $friendsStmt = $pdo->prepare('
        SELECT u.id, u.username 
        FROM friendships f 
        JOIN users u ON u.id = f.friend_id 
        WHERE f.user_id = ?
    ');
    $friendsStmt->execute([$currentUserId]);
    $friends = $friendsStmt->fetchAll();
    
    echo "<h3>💬 Ready to Chat! Click a friend below:</h3>";
    echo "<div style='display: flex; gap: 15px; flex-wrap: wrap; margin: 20px 0;'>";
    
    foreach ($friends as $friend) {
        $chatUrl = "chat.php?user_id={$friend['id']}";
        echo "<a href='$chatUrl' style='
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            color: white;
            padding: 15px 25px;
            text-decoration: none;
            border-radius: 12px;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(79, 172, 254, 0.3);
            transition: transform 0.3s ease;
            display: inline-block;
        ' onmouseover='this.style.transform=\"translateY(-2px)\"' onmouseout='this.style.transform=\"translateY(0)\"'>
            💬 Chat with {$friend['username']}
        </a>";
    }
    echo "</div>";
    
    echo "<p><strong>Instructions:</strong></p>";
    echo "<ol>";
    echo "<li>Click on any friend above to start chatting</li>";
    echo "<li>Type a message and press Enter or click Send</li>";
    echo "<li>Check browser console (F12) for any error messages</li>";
    echo "</ol>";
    
} else {
    echo "<p>❌ Login failed</p>";
}
?>