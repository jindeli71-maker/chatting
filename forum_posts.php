<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();
$forumId = (int)($_GET['forum_id'] ?? 0);

if (!$forumId) {
	header('Location: forums.php');
	exit;
}

// Get forum info
$stmt = $pdo->prepare('SELECT f.*, u.username as creator_name FROM forums f LEFT JOIN users u ON u.id = f.creator_id WHERE f.id = ?');
$stmt->execute([$forumId]);
$forum = $stmt->fetch();

if (!$forum) {
	header('Location: forums.php');
	exit;
}

// Handle post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_post') {
	$title = trim($_POST['title'] ?? '');
	$content = trim($_POST['content'] ?? '');
	
	if ($title !== '' && $content !== '') {
		$stmt = $pdo->prepare('INSERT INTO forum_posts (forum_id, author_id, title, content) VALUES (?, ?, ?, ?)');
		$stmt->execute([$forumId, $userId, $title, $content]);
		header('Location: forum_posts.php?forum_id=' . $forumId);
		exit;
	}
}

// Get posts for this forum
$stmt = $pdo->prepare('
	SELECT fp.*, u.username as author_name 
	FROM forum_posts fp 
	LEFT JOIN users u ON u.id = fp.author_id 
	WHERE fp.forum_id = ? 
	ORDER BY fp.created_at DESC
');
$stmt->execute([$forumId]);
$posts = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="row">
	<div class="col-12">
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="forums.php">Forums</a></li>
				<li class="breadcrumb-item active"><?php echo htmlspecialchars($forum['title']); ?></li>
			</ol>
		</nav>
		
		<div class="d-flex justify-content-between align-items-center mb-4">
			<div>
				<h2><?php echo htmlspecialchars($forum['title']); ?></h2>
				<p class="text-muted"><?php echo htmlspecialchars($forum['description']); ?></p>
				<small>Created by <strong><?php echo htmlspecialchars($forum['creator_name']); ?></strong></small>
			</div>
			<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPostModal">New Post</button>
		</div>
		
		<div class="list-group">
			<?php foreach ($posts as $post): ?>
			<div class="list-group-item">
				<div class="d-flex w-100 justify-content-between">
					<h5 class="mb-1">
						<a href="forum_post.php?post_id=<?php echo (int)$post['id']; ?>" class="text-decoration-none">
							<?php echo htmlspecialchars($post['title']); ?>
						</a>
					</h5>
					<small><?php echo date('M j, Y g:i A', strtotime($post['created_at'])); ?></small>
				</div>
				<p class="mb-1"><?php echo nl2br(htmlspecialchars(substr($post['content'], 0, 200))); ?><?php echo strlen($post['content']) > 200 ? '...' : ''; ?></p>
				<small>By <strong><?php echo htmlspecialchars($post['author_name']); ?></strong></small>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<!-- Create Post Modal -->
<div class="modal fade" id="createPostModal" tabindex="-1">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Create New Post</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<form method="post">
				<div class="modal-body">
					<input type="hidden" name="action" value="create_post">
					<div class="mb-3">
						<label class="form-label">Post Title</label>
						<input type="text" name="title" class="form-control" required>
					</div>
					<div class="mb-3">
						<label class="form-label">Content</label>
						<textarea name="content" class="form-control" rows="8" required></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary">Create Post</button>
				</div>
			</form>
		</div>
	</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
