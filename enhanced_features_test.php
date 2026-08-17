<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

require_login();
$pdo = get_pdo();
$userId = current_user_id();

$testResults = [];

// Test 1: Check database schema
function testDatabaseSchema($pdo) {
    $tables = [
        'pinned_messages',
        'message_edit_history', 
        'voice_call_sessions',
        'message_favorites',
        'notification_preferences'
    ];
    
    $results = [];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->fetchColumn();
            $results[$table] = $exists ? '✅ Exists' : '❌ Missing';
        } catch (Exception $e) {
            $results[$table] = '❌ Error: ' . $e->getMessage();
        }
    }
    
    return $results;
}

// Test 2: Check API endpoints
function testAPIEndpoints() {
    $apis = [
        'voice_call_api.php',
        'message_management_api.php',
        'voice_api.php',
        'file_upload_api.php',
        'forward_api.php',
        'mentions_api.php',
        'reactions_api.php'
    ];
    
    $results = [];
    foreach ($apis as $api) {
        $results[$api] = file_exists(__DIR__ . '/' . $api) ? '✅ Exists' : '❌ Missing';
    }
    
    return $results;
}

// Test 3: Check JavaScript files
function testJavaScriptFiles() {
    $jsFiles = [
        'assets/js/voice-call.js',
        'assets/js/enhanced-features.js',
        'assets/js/voice-recorder.js'
    ];
    
    $results = [];
    foreach ($jsFiles as $file) {
        $results[$file] = file_exists(__DIR__ . '/' . $file) ? '✅ Exists' : '❌ Missing';
    }
    
    return $results;
}

// Test 4: Check upload directories
function testUploadDirectories() {
    $dirs = [
        'uploads/voices/',
        'uploads/images/',
        'uploads/files/',
        'uploads/temp/'
    ];
    
    $results = [];
    foreach ($dirs as $dir) {
        $fullPath = __DIR__ . '/' . $dir;
        if (is_dir($fullPath)) {
            $results[$dir] = is_writable($fullPath) ? '✅ Writable' : '⚠️ Not writable';
        } else {
            // Try to create directory
            if (mkdir($fullPath, 0755, true)) {
                $results[$dir] = '✅ Created';
            } else {
                $results[$dir] = '❌ Cannot create';
            }
        }
    }
    
    return $results;
}

// Run tests
$testResults['Database Schema'] = testDatabaseSchema($pdo);
$testResults['API Endpoints'] = testAPIEndpoints();
$testResults['JavaScript Files'] = testJavaScriptFiles();
$testResults['Upload Directories'] = testUploadDirectories();

include __DIR__ . '/includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="fas fa-check-circle"></i> Enhanced Chat Features Test</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Testing Status:</strong> This page verifies that all enhanced chat features are properly installed and configured.
                    </div>

                    <?php foreach ($testResults as $category => $tests): ?>
                    <div class="mb-4">
                        <h5 class="border-bottom pb-2"><i class="fas fa-cog"></i> <?php echo $category; ?></h5>
                        <div class="row">
                            <?php foreach ($tests as $item => $status): ?>
                            <div class="col-md-6 mb-2">
                                <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                                    <span class="small"><?php echo htmlspecialchars($item); ?></span>
                                    <span class="small"><?php echo $status; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="mt-4">
                        <h5><i class="fas fa-rocket"></i> Quick Feature Test</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>Voice Recording Test</h6>
                                        <button class="btn btn-primary btn-sm" onclick="testVoiceRecording()">
                                            <i class="fas fa-microphone"></i> Test Voice
                                        </button>
                                        <div id="voiceTestResult" class="mt-2 small"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>File Upload Test</h6>
                                        <input type="file" id="testFileInput" class="form-control form-control-sm mb-2">
                                        <button class="btn btn-success btn-sm" onclick="testFileUpload()">
                                            <i class="fas fa-upload"></i> Test Upload
                                        </button>
                                        <div id="fileTestResult" class="mt-2 small"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 text-center">
                        <a href="chat.php" class="btn btn-success">
                            <i class="fas fa-comments"></i> Start Chatting
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Available Features</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-microphone text-success"></i> Voice Messages</li>
                                <li><i class="fas fa-phone text-success"></i> Voice Calling</li>
                                <li><i class="fas fa-paperclip text-success"></i> File Sharing</li>
                                <li><i class="fas fa-reply text-success"></i> Message Replies</li>
                                <li><i class="fas fa-share text-success"></i> Message Forwarding</li>
                                <li><i class="fas fa-at text-success"></i> User Mentions</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-edit text-success"></i> Message Editing (2min)</li>
                                <li><i class="fas fa-trash text-success"></i> Message Deletion</li>
                                <li><i class="fas fa-smile text-success"></i> Emoji Reactions</li>
                                <li><i class="fas fa-thumbtack text-success"></i> Message Pinning</li>
                                <li><i class="fas fa-star text-success"></i> Message Favorites</li>
                                <li><i class="fas fa-mobile-alt text-success"></i> Mobile Responsive</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Test voice recording capability
async function testVoiceRecording() {
    const resultDiv = document.getElementById('voiceTestResult');
    
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Voice recording not supported by browser');
        }
        
        resultDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing microphone access...';
        
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        stream.getTracks().forEach(track => track.stop());
        
        resultDiv.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> Microphone access granted - Voice recording ready!</span>';
    } catch (error) {
        resultDiv.innerHTML = `<span class="text-danger"><i class="fas fa-times"></i> Error: ${error.message}</span>`;
    }
}

// Test file upload capability
async function testFileUpload() {
    const fileInput = document.getElementById('testFileInput');
    const resultDiv = document.getElementById('fileTestResult');
    
    if (!fileInput.files || fileInput.files.length === 0) {
        resultDiv.innerHTML = '<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Please select a file first</span>';
        return;
    }
    
    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append('file', file);
    formData.append('category', 'file');
    formData.append('chat_type', 'private');
    formData.append('chat_id', '1');
    formData.append('action', 'upload');
    
    try {
        resultDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing file upload...';
        
        const response = await fetch('file_upload_api.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            resultDiv.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> File upload successful!</span>';
        } else {
            resultDiv.innerHTML = `<span class="text-danger"><i class="fas fa-times"></i> Upload failed: ${data.error}</span>`;
        }
    } catch (error) {
        resultDiv.innerHTML = `<span class="text-danger"><i class="fas fa-times"></i> Error: ${error.message}</span>`;
    }
}

// Auto-run voice test on page load
document.addEventListener('DOMContentLoaded', () => {
    console.log('🎉 Enhanced Chat Features Test Page Loaded');
    console.log('📍 Location:', window.location.href);
    console.log('🔍 User Agent:', navigator.userAgent);
    
    // Auto-test voice recording capability
    setTimeout(testVoiceRecording, 1000);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>