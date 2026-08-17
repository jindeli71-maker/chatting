<?php
// Simple diagnostic script to verify all enhanced features are working
echo "<h2>🔧 Chat Features Diagnostic</h2>";
echo "<p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Check if API files exist
$apiFiles = [
    'voice_api.php' => 'Voice Messages',
    'file_upload_api.php' => 'File Upload',
    'reactions_api.php' => 'Reactions',
    'forward_api.php' => 'Message Forwarding',
    'mentions_api.php' => 'User Mentions'
];

echo "<h3>📁 API Files Status:</h3>";
foreach ($apiFiles as $file => $feature) {
    $path = "C:\\xampp2\\htdocs\\Chatting\\$file";
    $exists = file_exists($path);
    $status = $exists ? "✅ Found" : "❌ Missing";
    echo "<p>$feature ($file): $status</p>";
}

// Check upload directories
echo "<h3>📂 Upload Directories:</h3>";
$uploadDirs = ['uploads', 'uploads/voice', 'uploads/images', 'uploads/files'];
foreach ($uploadDirs as $dir) {
    $path = "C:\\xampp2\\htdocs\\Chatting\\$dir";
    $exists = is_dir($path);
    $status = $exists ? "✅ Found" : "❌ Missing";
    echo "<p>$dir: $status</p>";
}

// JavaScript test
echo "<h3>🔍 JavaScript Feature Test:</h3>";
echo "<div id='featureTest'></div>";

echo "<script>
let features = {
    'Voice Recording Button': document.getElementById('recordVoice'),
    'File Attach Button': document.getElementById('attachFile'),
    'Voice Recording Interface': document.getElementById('voiceRecording'),
    'File Input': document.getElementById('fileInput'),
    'Reply Preview': document.getElementById('replyPreview')
};

let testDiv = document.getElementById('featureTest');
let results = '';

for (let [name, element] of Object.entries(features)) {
    let status = element ? '✅ Found' : '❌ Missing';
    results += '<p>' + name + ': ' + status + '</p>';
}

testDiv.innerHTML = results;

// Add to page title
if (document.getElementById('recordVoice')) {
    document.title = '🎤 Enhanced Chat - All Features Ready';
} else {
    document.title = '⚠️ Basic Chat - Features Missing';
}
</script>";

echo "<h3>🌐 Access Information:</h3>";
echo "<p><strong>Chat URL:</strong> <a href='http://localhost/Chatting/chat.php'>http://localhost/Chatting/chat.php</a></p>";
echo "<p><strong>Main Page:</strong> <a href='http://localhost/Chatting/'>http://localhost/Chatting/</a></p>";

echo "<h3>💡 Troubleshooting Tips:</h3>";
echo "<ul>";
echo "<li>If features are missing, try hard refresh: <strong>Ctrl + F5</strong></li>";
echo "<li>Clear browser cache completely</li>";
echo "<li>Check browser console (F12) for JavaScript errors</li>";
echo "<li>Ensure XAMPP Apache and MySQL are running</li>";
echo "</ul>";
?>