<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

echo "<h2>🔍 Message Display Debug Tool</h2>";

$currentUserId = current_user_id();
if (!$currentUserId) {
    echo "<p>❌ Not logged in!</p>";
    exit;
}

$pdo = get_pdo();

// Test parameters
$peerId = $_GET['peer_id'] ?? 5; // admin user
$afterId = $_GET['after_id'] ?? 0;

echo "<h3>📊 Debug Information</h3>";
echo "<p><strong>Current User ID:</strong> $currentUserId</p>";
echo "<p><strong>Peer ID:</strong> $peerId</p>";
echo "<p><strong>After ID:</strong> $afterId</p>";

// Check friendship
echo "<h3>👥 Friendship Check</h3>";
$friendshipStmt = $pdo->prepare('SELECT 1 FROM friendships WHERE user_id = ? AND friend_id = ?');
$friendshipStmt->execute([$currentUserId, $peerId]);
if ($friendshipStmt->fetch()) {
    echo "<p>✅ Friendship exists</p>";
} else {
    echo "<p>❌ No friendship found</p>";
    echo "<p>Creating friendship...</p>";
    
    // Create friendship
    $pdo->prepare('INSERT IGNORE INTO friendships (user_id, friend_id) VALUES (?, ?)')
        ->execute([$currentUserId, $peerId]);
    $pdo->prepare('INSERT IGNORE INTO friendships (user_id, friend_id) VALUES (?, ?)')
        ->execute([$peerId, $currentUserId]);
    
    echo "<p>✅ Friendship created</p>";
}

// Check messages in database
echo "<h3>💬 Messages in Database</h3>";
$messagesStmt = $pdo->prepare('
    SELECT m.id, m.sender_id, m.receiver_id, m.message_text, m.created_at,
           sender.username as sender_name, receiver.username as receiver_name
    FROM messages m
    LEFT JOIN users sender ON m.sender_id = sender.id
    LEFT JOIN users receiver ON m.receiver_id = receiver.id
    WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
    ORDER BY m.created_at DESC
    LIMIT 10
');
$messagesStmt->execute([$currentUserId, $peerId, $peerId, $currentUserId]);
$messages = $messagesStmt->fetchAll();

if (empty($messages)) {
    echo "<p>❌ No messages found in database</p>";
    
    // Create test message
    echo "<p>Creating test message...</p>";
    $testStmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)');
    $testStmt->execute([$currentUserId, $peerId, 'Hello! This is a test message.']);
    
    echo "<p>✅ Test message created</p>";
    
    // Recheck messages
    $messagesStmt->execute([$currentUserId, $peerId, $peerId, $currentUserId]);
    $messages = $messagesStmt->fetchAll();
}

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<h4>Found " . count($messages) . " messages:</h4>";
foreach ($messages as $msg) {
    $direction = ($msg['sender_id'] == $currentUserId) ? 'Sent' : 'Received';
    echo "<p><strong>[$direction]</strong> {$msg['message_text']} <small>({$msg['created_at']})</small></p>";
}
echo "</div>";

// Test API call
echo "<h3>🔧 API Test</h3>";
echo "<p>Testing chat_api.php fetch...</p>";

$apiUrl = "chat_api.php?action=fetch&peer_id=$peerId&after_id=$afterId";
echo "<p><strong>API URL:</strong> <a href='$apiUrl' target='_blank'>$apiUrl</a></p>";

// Simulate API call
try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/Chatting/' . $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<p><strong>HTTP Status:</strong> $httpCode</p>";
    echo "<div style='background: #e9ecef; padding: 10px; border-radius: 5px; font-family: monospace; white-space: pre-wrap;'>";
    echo htmlspecialchars($response);
    echo "</div>";
    
    $data = json_decode($response, true);
    if ($data) {
        echo "<p><strong>Parsed JSON:</strong></p>";
        echo "<pre>" . print_r($data, true) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ API Error: " . $e->getMessage() . "</p>";
}

// JavaScript test
echo "<h3>🔬 JavaScript Test</h3>";
echo "<div id='jsTest' style='background: #fff3cd; padding: 15px; border-radius: 8px;'>Testing JavaScript...</div>";

echo "<script>
document.addEventListener('DOMContentLoaded', function() {
    const testDiv = document.getElementById('jsTest');
    
    // Test fetch API
    fetch('$apiUrl')
        .then(response => response.json())
        .then(data => {
            testDiv.innerHTML = '<h4>✅ JavaScript API Test Results:</h4>' +
                               '<p><strong>Messages found:</strong> ' + (data.messages ? data.messages.length : 0) + '</p>' +
                               '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            testDiv.innerHTML = '<h4>❌ JavaScript API Error:</h4><p>' + error.message + '</p>';
        });
});
</script>";

echo "<hr>";
echo "<h3>🔗 Quick Links</h3>";
echo "<p><a href='chat.php?user_id=$peerId'>Open Chat</a> | <a href='admin_login.php'>Admin Login</a></p>";
?>