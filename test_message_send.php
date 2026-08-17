<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/filter.php';

echo "<h2>🔧 Message Sending Diagnostic</h2>";

try {
    $pdo = get_pdo();
    
    // Check login status
    echo "<h3>🔐 Login Status</h3>";
    $currentUserId = current_user_id();
    if (!$currentUserId) {
        echo "<p>❌ Not logged in! <a href='login.php'>Please login first</a></p>";
        exit;
    }
    echo "<p>✅ Logged in as user ID: $currentUserId</p>";
    
    // Get current user info
    $userStmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $userStmt->execute([$currentUserId]);
    $currentUser = $userStmt->fetchColumn();
    echo "<p>👤 Username: $currentUser</p>";
    
    // Check friends
    echo "<h3>👥 Available Friends</h3>";
    $friendsStmt = $pdo->prepare('
        SELECT u.id, u.username 
        FROM friendships f 
        JOIN users u ON u.id = f.friend_id 
        WHERE f.user_id = ?
    ');
    $friendsStmt->execute([$currentUserId]);
    $friends = $friendsStmt->fetchAll();
    
    if (empty($friends)) {
        echo "<p>❌ No friends found. You need friends to send messages.</p>";
        echo "<p><a href='friends.php'>Go to Friends page to add friends</a></p>";
    } else {
        echo "<p>✅ You have " . count($friends) . " friends:</p>";
        foreach ($friends as $friend) {
            echo "<p>- {$friend['username']} (ID: {$friend['id']})</p>";
        }
        
        // Test message sending API
        echo "<h3>🧪 Test Message Sending</h3>";
        $testFriend = $friends[0];
        echo "<form method='post' style='background: #f0f0f0; padding: 20px; border-radius: 8px;'>";
        echo "<h4>Send test message to {$testFriend['username']}</h4>";
        echo "<input type='hidden' name='test_send' value='1'>";
        echo "<input type='hidden' name='peer_id' value='{$testFriend['id']}'>";
        echo "<input type='text' name='message' placeholder='Enter test message' required style='padding: 8px; margin-right: 10px; width: 300px;'>";
        echo "<button type='submit' style='padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px;'>Send Test Message</button>";
        echo "</form>";
    }
    
    // Handle test message sending
    if (isset($_POST['test_send'])) {
        $peerId = (int)$_POST['peer_id'];
        $message = trim($_POST['message']);
        
        if ($peerId && $message) {
            echo "<h4>📤 Sending test message...</h4>";
            
            // Simulate the same logic as chat_api.php
            try {
                // Check friendship
                $friendCheck = $pdo->prepare('SELECT 1 FROM friendships WHERE user_id = ? AND friend_id = ?');
                $friendCheck->execute([$currentUserId, $peerId]);
                
                if (!$friendCheck->fetch()) {
                    echo "<p>❌ Not friends with this user</p>";
                } else {
                    // Check if blocked
                    $blockCheck = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
                    $blockCheck->execute([$peerId, $currentUserId]);
                    $isBlockedByPeer = (bool)$blockCheck->fetch();
                    
                    // Insert message (apply profanity filter just like the API)
                    $filtered = censor_bad_words($message);
                    $insertStmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)');
                    $result = $insertStmt->execute([$currentUserId, $peerId, $filtered]);
                    
                    if ($result) {
                        $messageId = $pdo->lastInsertId();
                        echo "<p>✅ Message sent successfully! Message ID: $messageId</p>";
                        
                        if ($isBlockedByPeer) {
                            echo "<p>⚠️ Warning: You are blocked by this user</p>";
                        }
                        
                        // Show the message (censored version stored in db)
                        echo "<div style='background: #e8f5e8; padding: 10px; border-radius: 8px; margin: 10px 0;'>";
                        echo "<strong>Message:</strong> " . htmlspecialchars($filtered);
                        echo "<br><small>Sent at: " . date('Y-m-d H:i:s') . "</small>";
                        echo "</div>";
                        
                    } else {
                        echo "<p>❌ Failed to insert message into database</p>";
                    }
                }
            } catch (Exception $e) {
                echo "<p>❌ Error: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // Check recent messages
    echo "<h3>💬 Recent Messages</h3>";
    $recentStmt = $pdo->prepare('
        SELECT m.*, 
               sender.username as sender_name, 
               receiver.username as receiver_name
        FROM messages m 
        JOIN users sender ON m.sender_id = sender.id 
        JOIN users receiver ON m.receiver_id = receiver.id 
        WHERE m.sender_id = ? OR m.receiver_id = ?
        ORDER BY m.created_at DESC 
        LIMIT 5
    ');
    $recentStmt->execute([$currentUserId, $currentUserId]);
    $recentMessages = $recentStmt->fetchAll();
    
    if (empty($recentMessages)) {
        echo "<p>No recent messages found</p>";
    } else {
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px;'>";
        foreach ($recentMessages as $msg) {
            $direction = ($msg['sender_id'] == $currentUserId) ? 'Sent to' : 'Received from';
            $otherUser = ($msg['sender_id'] == $currentUserId) ? $msg['receiver_name'] : $msg['sender_name'];
            echo "<p><strong>$direction {$otherUser}:</strong> " . htmlspecialchars($msg['message_text']) . "</p>";
            echo "<small>Time: {$msg['created_at']}</small><hr>";
        }
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Database Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>🔗 Quick Links</h3>";
echo "<p><a href='login.php'>Login Page</a> | <a href='friends.php'>Friends Page</a> | <a href='chat.php'>Chat Page</a></p>";
?>