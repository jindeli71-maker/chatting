<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();

// Get a test user (not current user)
$testUserStmt = $pdo->prepare('SELECT id, username FROM users WHERE id != ? LIMIT 1');
$testUserStmt->execute([$userId]);
$testUser = $testUserStmt->fetch();

if (!$testUser) {
    die('No other users found for testing');
}

$testUserId = $testUser['id'];
$testUsername = $testUser['username'];

// Check current block status
$blockCheck = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
$blockCheck->execute([$userId, $testUserId]);
$isCurrentlyBlocked = (bool)$blockCheck->fetch();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Block Functionality Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Block Functionality Test</h1>
        <p>Testing block functionality with user: <strong><?php echo htmlspecialchars($testUsername); ?></strong> (ID: <?php echo $testUserId; ?>)</p>
        
        <div class="card">
            <div class="card-header">
                <h5>Current Status</h5>
            </div>
            <div class="card-body">
                <p>User is currently: <span class="badge bg-<?php echo $isCurrentlyBlocked ? 'danger' : 'success'; ?>">
                    <?php echo $isCurrentlyBlocked ? 'BLOCKED' : 'NOT BLOCKED'; ?>
                </span></p>
                
                <div class="mt-3">
                    <button class="btn btn-<?php echo $isCurrentlyBlocked ? 'success' : 'danger'; ?>" id="toggleBlock">
                        <i class="fas fa-<?php echo $isCurrentlyBlocked ? 'unlock' : 'ban'; ?>"></i>
                        <?php echo $isCurrentlyBlocked ? 'Unblock User' : 'Block User'; ?>
                    </button>
                </div>
                
                <div class="mt-3">
                    <a href="chat.php?user_id=<?php echo $testUserId; ?>" class="btn btn-primary">
                        <i class="fas fa-comments"></i> Go to Chat
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5>Test Results</h5>
            </div>
            <div class="card-body">
                <div id="testResults">
                    <p class="text-muted">Click the toggle button above to test the block functionality.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const testUserId = <?php echo $testUserId; ?>;
        const isCurrentlyBlocked = <?php echo $isCurrentlyBlocked ? 'true' : 'false'; ?>;
        
        document.getElementById('toggleBlock').addEventListener('click', async function() {
            const button = this;
            const originalText = button.innerHTML;
            
            // Show loading state
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            try {
                const action = isCurrentlyBlocked ? 'unblock' : 'block';
                const response = await fetch('block_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    credentials: 'same-origin',
                    body: new URLSearchParams({ action: action, user_id: testUserId })
                });
                
                const data = await response.json();
                
                if (data.ok) {
                    // Success - reload page to show new status
                    location.reload();
                } else {
                    // Error
                    button.disabled = false;
                    button.innerHTML = originalText;
                    document.getElementById('testResults').innerHTML = 
                        '<div class="alert alert-danger">Error: ' + data.error + '</div>';
                }
            } catch (error) {
                // Network error
                button.disabled = false;
                button.innerHTML = originalText;
                document.getElementById('testResults').innerHTML = 
                    '<div class="alert alert-danger">Network Error: ' + error.message + '</div>';
            }
        });
    </script>
</body>
</html>
