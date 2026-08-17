<?php
require_once 'includes/db.php';

try {
    echo "Initializing SQLite database...\n";
    
    // Get PDO connection (will create SQLite database automatically)
    $pdo = get_pdo();
    
    // Read and execute the SQLite schema
    $sql = file_get_contents('init_sqlite.sql');
    
    // Split the SQL into individual statements
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    echo "✅ Database initialized successfully!\n";
    echo "📊 Sample data inserted.\n";
    echo "🌐 You can now access your application at: http://localhost/Chatting/\n";
    
    // Test the connection by counting users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "👥 Total users in database: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>