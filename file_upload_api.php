<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');
require_login();

$userId = current_user_id();
$pdo = get_pdo();

// Configuration
const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB
const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10MB
const MAX_VOICE_SIZE = 5 * 1024 * 1024; // 5MB
const UPLOAD_DIR = __DIR__ . '/uploads/';
const ALLOWED_IMAGE_TYPES = [
    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml'
];
const ALLOWED_FILE_TYPES = [
    // Documents
    'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain', 'text/csv', 'application/rtf',
    // Archives
    'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed', 'application/x-tar',
    // Images (allow in files too)
    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
    // Audio/Video
    'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/webm', 'audio/mp4',
    'video/mp4', 'video/webm', 'video/avi', 'video/mov', 'video/wmv',
    // Other common types
    'application/json', 'application/xml', 'application/octet-stream'
];
const ALLOWED_VOICE_TYPES = [
    'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/webm', 'audio/mp4', 'audio/aac'
];

// Create upload directories if they don't exist
$directories = ['images', 'files', 'voices', 'videos'];
foreach ($directories as $dir) {
    $fullPath = UPLOAD_DIR . $dir;
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0755, true);
    }
}

function generateSecureFilename($originalName, $type) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $secureName = uniqid() . '_' . time() . '.' . $extension;
    
    switch ($type) {
        case 'image':
            return 'images/' . $secureName;
        case 'voice':
            return 'voices/' . $secureName;
        case 'video':
            return 'videos/' . $secureName;
        default:
            return 'files/' . $secureName;
    }
}

function validateFileType($fileType, $category) {
    // Debug: log the file type being checked
    error_log("Validating file type: $fileType for category: $category");
    
    // More permissive validation - only block dangerous executable types
    $dangerousTypes = [
        'application/x-msdownload', 'application/x-msdos-program', 'application/x-executable',
        'application/x-msdos-windows', 'application/x-winexe', 'application/x-ms-dos-executable',
        'application/exe', 'application/x-exe', 'application/dos-exe'
    ];
    
    // Block dangerous types
    if (in_array(strtolower($fileType), array_map('strtolower', $dangerousTypes))) {
        return false;
    }
    
    // Be very permissive for all categories
    switch ($category) {
        case 'image':
            // Allow any image type
            return strpos($fileType, 'image/') === 0 || in_array($fileType, ALLOWED_IMAGE_TYPES);
        case 'voice':
            // Allow any audio type
            return strpos($fileType, 'audio/') === 0 || in_array($fileType, ALLOWED_VOICE_TYPES);
        case 'file':
        default:
            // Allow almost everything except dangerous executables
            return true;
    }
}

function getMaxFileSize($category) {
    switch ($category) {
        case 'image':
            return MAX_IMAGE_SIZE;
        case 'voice':
            return MAX_VOICE_SIZE;
        default:
            return MAX_FILE_SIZE;
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'upload':
            if (!isset($_FILES['file'])) {
                throw new Exception('No file uploaded');
            }

            $file = $_FILES['file'];
            $category = $_POST['category'] ?? 'file'; // image, voice, file
            $chatType = $_POST['chat_type'] ?? 'private'; // private or group
            $chatId = (int)($_POST['chat_id'] ?? 0);
            $messageText = trim($_POST['message_text'] ?? '');

            // Validate file
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload error: ' . $file['error']);
            }

            if ($file['size'] > getMaxFileSize($category)) {
                $maxSize = getMaxFileSize($category) / (1024 * 1024);
                throw new Exception("File too large. Maximum size: {$maxSize}MB");
            }

            if (!validateFileType($file['type'], $category)) {
                throw new Exception('File type not allowed');
            }

            // Generate secure filename and path
            $relativePath = generateSecureFilename($file['name'], $category);
            $fullPath = UPLOAD_DIR . $relativePath;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                throw new Exception('Failed to save uploaded file');
            }

            // Get file info
            $fileSize = filesize($fullPath);
            $duration = null;

            // For voice files, try to get duration (requires ffmpeg or similar)
            if ($category === 'voice') {
                // You can implement duration detection here if needed
                // $duration = getAudioDuration($fullPath);
            }

            // Insert message into database
            if ($chatType === 'private') {
                // Private message
                $stmt = $pdo->prepare('
                    INSERT INTO messages (sender_id, receiver_id, message_text, message_type, file_path, file_name, file_size, duration)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $userId,
                    $chatId, // receiver_id
                    $messageText,
                    $category,
                    $relativePath,
                    $file['name'],
                    $fileSize,
                    $duration
                ]);
                $messageId = $pdo->lastInsertId();
            } else {
                // Group message
                $stmt = $pdo->prepare('
                    INSERT INTO group_messages (group_id, sender_id, message_text, message_type, file_path, file_name, file_size, duration)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $chatId, // group_id
                    $userId,
                    $messageText,
                    $category,
                    $relativePath,
                    $file['name'],
                    $fileSize,
                    $duration
                ]);
                $messageId = $pdo->lastInsertId();
            }

            echo json_encode([
                'success' => true,
                'message_id' => $messageId,
                'file_path' => $relativePath,
                'file_name' => $file['name'],
                'file_size' => $fileSize,
                'file_type' => $category,
                'duration' => $duration
            ]);
            break;

        case 'get_file_url':
            $filePath = $_POST['file_path'] ?? '';
            if (empty($filePath)) {
                throw new Exception('File path required');
            }

            // Security check - ensure file path is within uploads directory
            $realPath = realpath(UPLOAD_DIR . $filePath);
            $uploadsPath = realpath(UPLOAD_DIR);
            
            if (!$realPath || strpos($realPath, $uploadsPath) !== 0) {
                throw new Exception('Invalid file path');
            }

            if (!file_exists($realPath)) {
                throw new Exception('File not found');
            }

            // Return URL to access file
            $fileUrl = '/uploads/' . $filePath;
            echo json_encode([
                'success' => true,
                'file_url' => $fileUrl
            ]);
            break;

        case 'delete_file':
            $filePath = $_POST['file_path'] ?? '';
            $messageId = (int)($_POST['message_id'] ?? 0);
            $chatType = $_POST['chat_type'] ?? 'private';

            if (empty($filePath) || !$messageId) {
                throw new Exception('File path and message ID required');
            }

            // Verify user owns the message
            if ($chatType === 'private') {
                $stmt = $pdo->prepare('SELECT sender_id FROM messages WHERE id = ? AND sender_id = ?');
            } else {
                $stmt = $pdo->prepare('SELECT sender_id FROM group_messages WHERE id = ? AND sender_id = ?');
            }
            $stmt->execute([$messageId, $userId]);
            
            if (!$stmt->fetch()) {
                throw new Exception('Not authorized to delete this file');
            }

            // Delete physical file
            $fullPath = UPLOAD_DIR . $filePath;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            // Update message to mark as deleted
            if ($chatType === 'private') {
                $stmt = $pdo->prepare('UPDATE messages SET deleted_at = NOW(), file_path = NULL WHERE id = ?');
            } else {
                $stmt = $pdo->prepare('UPDATE group_messages SET deleted_at = NOW(), file_path = NULL WHERE id = ?');
            }
            $stmt->execute([$messageId]);

            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}