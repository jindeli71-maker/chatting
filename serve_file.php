<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Require authentication to access files
require_login();

$userId = current_user_id();
$pdo = get_pdo();

// Get file path from URL
$filePath = $_GET['file'] ?? '';

if (empty($filePath)) {
    http_response_code(404);
    exit('File not found');
}

// Security: Validate file path
$uploadDir = __DIR__ . '/uploads/';
$fullPath = realpath($uploadDir . $filePath);
$uploadsPath = realpath($uploadDir);

if (!$fullPath || strpos($fullPath, $uploadsPath) !== 0) {
    http_response_code(403);
    exit('Access denied');
}

if (!file_exists($fullPath)) {
    http_response_code(404);
    exit('File not found');
}

// Check if user has access to this file
// We need to verify the user is part of the conversation where this file was shared
$hasAccess = false;

// Check in private messages
$stmt = $pdo->prepare('
    SELECT COUNT(*) 
    FROM messages m 
    WHERE m.file_path = ? 
    AND (m.sender_id = ? OR m.receiver_id = ?)
    AND m.deleted_at IS NULL
');
$stmt->execute([$filePath, $userId, $userId]);
if ($stmt->fetchColumn() > 0) {
    $hasAccess = true;
}

// Check in group messages if not found in private
if (!$hasAccess) {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) 
        FROM group_messages gm 
        JOIN group_members gme ON gme.group_id = gm.group_id 
        WHERE gm.file_path = ? 
        AND gme.user_id = ?
        AND gm.deleted_at IS NULL
    ');
    $stmt->execute([$filePath, $userId]);
    if ($stmt->fetchColumn() > 0) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    http_response_code(403);
    exit('Access denied');
}

// Get file info
$fileInfo = pathinfo($fullPath);
$mimeType = mime_content_type($fullPath);
$fileSize = filesize($fullPath);

// Set appropriate headers
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');

// For images, add cache headers
if (strpos($mimeType, 'image/') === 0) {
    header('Cache-Control: public, max-age=86400'); // 1 day cache
    header('Expires: ' . gmdate('D, d M Y H:i:s T', time() + 86400));
}

// Output file
readfile($fullPath);