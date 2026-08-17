<?php
// Test the exact query that's failing
require_once 'includes/db.php';

try {
    $pdo = get_pdo();
    $userId = 1; // Test with user ID 1
    
    echo "Testing the exact query from index.php...\n";
    
    $stmt = $pdo->prepare('
        SELECT 
            fp.id, fp.title, fp.content, fp.created_at,
            f.title as forum_title, f.id as forum_id,
            u.username as author_name,
            COUNT(DISTINCT pl.user_id) as like_count,
            COUNT(DISTINCT pc.id) as comment_count,
            COUNT(DISTINCT ps.id) as share_count,
            EXISTS(SELECT 1 FROM post_likes WHERE post_id = fp.id AND user_id = ?) as user_liked
        FROM forum_posts fp
        LEFT JOIN forums f ON f.id = fp.forum_id
        LEFT JOIN users u ON u.id = fp.author_id
        LEFT JOIN post_likes pl ON pl.post_id = fp.id
        LEFT JOIN post_comments pc ON pc.post_id = fp.id
        LEFT JOIN post_shares ps ON ps.post_id = fp.id
        GROUP BY fp.id
        ORDER BY fp.created_at DESC
        LIMIT 20
    ');
    
    $stmt->execute([$userId]);
    $posts = $stmt->fetchAll();
    
    echo "✅ Query executed successfully!\n";
    echo "📊 Found " . count($posts) . " posts\n";
    
    foreach($posts as $post) {
        echo "- " . $post['title'] . " by " . $post['author_name'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Error details: " . $e->getFile() . " line " . $e->getLine() . "\n";
}
?>