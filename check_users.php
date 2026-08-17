<?php
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

try {
    $pdo = get_pdo();
    
    // Get all users
    $stmt = $pdo->prepare('SELECT id, username, email FROM users ORDER BY id');
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'count' => count($users)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>