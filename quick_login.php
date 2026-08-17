<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

echo "<h2>🔐 Quick Login & Message Test</h2>";

// Handle login
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $user = authenticate_user($username, $password);
    if ($user) {
        login_user($user);
        echo "<p>✅ Login successful! Welcome, {$user['username']}!</p>";
        echo "<script>window.location.reload();</script>";
    } else {
        echo "<p>❌ Login failed. Please check username and password.</p>";
    }
}

// Check current login status
$currentUserId = current_user_id();
if ($currentUserId) {
    $pdo = get_pdo();
    $userStmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $userStmt->execute([$currentUserId]);
    $currentUser = $userStmt->fetchColumn();
    
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h3>✅ Already logged in as: $currentUser (ID: $currentUserId)</h3>";
    echo "<p><a href='logout.php'>Logout</a> | <a href='chat.php'>Go to Chat</a></p>";
    echo "</div>";
    
    // Show available chat links
    $friendsStmt = $pdo->prepare('
        SELECT u.id, u.username 
        FROM friendships f 
        JOIN users u ON u.id = f.friend_id 
        WHERE f.user_id = ?
    ');
    $friendsStmt->execute([$currentUserId]);
    $friends = $friendsStmt->fetchAll();
    
    if (!empty($friends)) {
        echo "<h3>💬 Available Chats</h3>";
        foreach ($friends as $friend) {
            $chatUrl = "chat.php?peer_id={$friend['id']}";
            echo "<p>📱 <a href='$chatUrl' style='background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px;'>Chat with {$friend['username']}</a></p>";
        }
    }
} else {
    // Show login form
    echo "<h3>Please login to test messaging:</h3>";
    echo "<form method='post' style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<div style='margin-bottom: 15px;'>";
    echo "<label>Username:</label><br>";
    echo "<input type='text' name='username' value='jasper' required style='padding: 8px; width: 200px; margin-top: 5px;'>";
    echo "</div>";
    echo "<div style='margin-bottom: 15px;'>";
    echo "<label>Password:</label><br>";
    echo "<input type='password' name='password' value='password' required style='padding: 8px; width: 200px; margin-top: 5px;'>";
    echo "</div>";
    echo "<button type='submit' name='login' style='padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;'>Login</button>";
    echo "</form>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h4>💡 Available Test Accounts:</h4>";
    echo "<p><strong>Username:</strong> jasper | <strong>Password:</strong> password</p>";
    echo "<p><strong>Username:</strong> alice | <strong>Password:</strong> password</p>";
    echo "<p><strong>Username:</strong> bob | <strong>Password:</strong> password</p>";
    echo "</div>";
}

// Show database status
try {
    $pdo = get_pdo();
    $userCount = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
    $friendshipCount = $pdo->query("SELECT COUNT(*) as count FROM friendships")->fetch()['count'];
    
    echo "<hr>";
    echo "<h3>📊 Database Status</h3>";
    echo "<p>👥 Users: $userCount</p>";
    echo "<p>🤝 Friendships: $friendshipCount</p>";
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}
?>