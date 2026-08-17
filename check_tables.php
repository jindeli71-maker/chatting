<?php
require_once 'includes/db.php';

try {
    $pdo = get_pdo();
    
    echo "Checking database tables...\n";
    
    // List all tables
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll();
    echo "Available tables:\n";
    foreach($tables as $table) {
        echo "- " . $table['name'] . "\n";
    }
    
    // Check specifically for the problematic tables
    $requiredTables = ['post_likes', 'post_comments', 'post_shares', 'forums', 'forum_posts'];
    echo "\nChecking required tables:\n";
    
    foreach($requiredTables as $tableName) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $tableName");
            $result = $stmt->fetch();
            echo "✅ $tableName: " . $result['count'] . " rows\n";
        } catch (Exception $e) {
            echo "❌ $tableName: " . $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>