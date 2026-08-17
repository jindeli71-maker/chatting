<!DOCTYPE html>
<html>
<head>
    <title>Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Enhanced Chat Database Setup</h1>
        <p>Setting up enhanced database schema for new chat features...</p>
        
<?php
require_once __DIR__ . '/includes/config.php';

echo "<div class='setup-log'>";

try {
    // Connect to MySQL server
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p class='success'>✅ Database created/verified</p>";
    
    // Switch to the database
    $pdo->exec("USE `" . DB_NAME . "`");
    
    // Apply additional schema
    $sqlFile = __DIR__ . '/additional_schema.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        
        // Split by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^\s*--/', $statement)) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // Ignore table already exists errors
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        echo "<p class='warning'>⚠️ Warning: " . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                }
            }
        }
        echo "<p class='success'>✅ Additional schema applied</p>";
    } else {
        echo "<p class='error'>❌ Schema file not found: $sqlFile</p>";
    }
    
    // Verify new tables exist
    $tables = ['pinned_messages', 'message_edit_history', 'voice_call_sessions', 'message_favorites'];
    echo "<h3>📊 Table Verification</h3>";
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetchColumn()) {
            echo "<p class='success'>✅ Table '$table' exists</p>";
        } else {
            echo "<p class='error'>❌ Table '$table' missing</p>";
        }
    }
    
    echo "<h3>🎉 Database setup complete!</h3>";
    echo "<p><a href='simple_feature_test.html' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Features</a></p>";
    echo "<p><a href='chat.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Start Chatting</a></p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3>📋 Manual Setup Instructions</h3>";
    echo "<p>If automatic setup failed, please run this SQL manually in your database:</p>";
    echo "<pre>";
    $sqlFile = __DIR__ . '/additional_schema.sql';
    if (file_exists($sqlFile)) {
        echo htmlspecialchars(file_get_contents($sqlFile));
    }
    echo "</pre>";
}

echo "</div>";
?>
    </div>
</body>
</html>