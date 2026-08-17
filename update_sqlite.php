<?php
require_once 'includes/db.php';

try {
    echo "Updating SQLite database with missing tables...\n";
    
    $pdo = get_pdo();
    
    // Add missing post_comments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS post_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL,
            author_id INTEGER NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Add missing post_likes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS post_likes (
            post_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (post_id, user_id),
            FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Add missing post_shares table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS post_shares (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL,
            sharer_id INTEGER NOT NULL,
            shared_with_id INTEGER NOT NULL,
            message TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
            FOREIGN KEY (sharer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (shared_with_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Add sample forums if they don't exist
    $pdo->exec("
        INSERT OR IGNORE INTO forums (title, description, creator_id) VALUES 
        ('General Discussion', 'Talk about anything here!', 1),
        ('Tech Talk', 'Discuss technology and programming', 1)
    ");
    
    // Add sample forum posts if they don't exist
    $pdo->exec("
        INSERT OR IGNORE INTO forum_posts (forum_id, author_id, title, content) VALUES 
        (1, 1, 'Welcome to our forum!', 'This is the first post on our forum. Feel free to share your thoughts and ideas here!'),
        (1, 2, 'Hello everyone!', 'Nice to meet you all. Looking forward to great discussions!'),
        (2, 3, 'Latest programming trends', 'What programming languages are you learning this year?')
    ");
    
    // Add indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_post_comments_post ON post_comments(post_id, created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_post_shares_sharer ON post_shares(sharer_id, created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_post_shares_shared_with ON post_shares(shared_with_id, created_at)");
    
    echo "✅ Database updated successfully!\n";
    echo "📊 Missing tables added.\n";
    echo "🌐 Your application should now work at: http://localhost/Chatting/\n";
    
    // Test the tables
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM forum_posts");
    $result = $stmt->fetch();
    echo "📝 Total forum posts: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>