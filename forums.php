<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();

// Handle forum creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_forum') {
	$title = trim($_POST['title'] ?? '');
	$description = trim($_POST['description'] ?? '');
	
	if ($title !== '') {
		$stmt = $pdo->prepare('INSERT INTO forums (title, description, creator_id) VALUES (?, ?, ?)');
		$stmt->execute([$title, $description, $userId]);
		header('Location: forums.php');
		exit;
	}
}

// Get all forums with creator info
$stmt = $pdo->prepare('
	SELECT f.*, u.username as creator_name, 
	       COUNT(fp.id) as post_count,
	       MAX(fp.created_at) as last_post_at
	FROM forums f 
	LEFT JOIN users u ON u.id = f.creator_id 
	LEFT JOIN forum_posts fp ON fp.forum_id = f.id
	GROUP BY f.id 
	ORDER BY f.created_at DESC
');
$stmt->execute();
$forums = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="row">
	<div class="col-12">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<h2>Forums</h2>
			<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createForumModal">Create Forum</button>
		</div>
		
		<div class="list-group">
			<?php foreach ($forums as $forum): ?>
			<div class="list-group-item">
				<div class="d-flex w-100 justify-content-between">
					<h5 class="mb-1">
						<a href="forum_posts.php?forum_id=<?php echo (int)$forum['id']; ?>" class="text-decoration-none">
							<?php echo htmlspecialchars($forum['title']); ?>
						</a>
					</h5>
					<small><?php echo date('M j, Y', strtotime($forum['created_at'])); ?></small>
				</div>
				<p class="mb-1"><?php echo htmlspecialchars($forum['description']); ?></p>
				<small>
					Created by <strong><?php echo htmlspecialchars($forum['creator_name']); ?></strong>
					• <?php echo (int)$forum['post_count']; ?> posts
					<?php if ($forum['last_post_at']): ?>
					• Last post: <?php echo date('M j, Y g:i A', strtotime($forum['last_post_at'])); ?>
					<?php endif; ?>
				</small>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<!-- Create Forum Modal -->
<div class="modal fade" id="createForumModal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Create New Forum</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<form method="post">
				<div class="modal-body">
					<input type="hidden" name="action" value="create_forum">
					<div class="mb-3">
						<label class="form-label">Forum Title</label>
						<input type="text" name="title" class="form-control" required>
					</div>
					<div class="mb-3">
						<label class="form-label">Description</label>
						<textarea name="description" class="form-control" rows="3"></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Create Forum</button>
				</div>
			</form>
		</div>
	</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
