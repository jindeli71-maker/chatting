<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();
$postId = (int)($_GET['post_id'] ?? 0);

if (!$postId) {
	header('Location: forums.php');
	exit;
}

// Get post with forum and author info
$stmt = $pdo->prepare('
	SELECT fp.*, u.username as author_name, f.title as forum_title, f.id as forum_id
	FROM forum_posts fp 
	LEFT JOIN users u ON u.id = fp.author_id 
	LEFT JOIN forums f ON f.id = fp.forum_id
	WHERE fp.id = ?
');
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
	header('Location: forums.php');
	exit;
}

include __DIR__ . '/includes/header.php';
?>
<div class="row">
	<div class="col-12">
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="forums.php">Forums</a></li>
				<li class="breadcrumb-item"><a href="forum_posts.php?forum_id=<?php echo (int)$post['forum_id']; ?>"><?php echo htmlspecialchars($post['forum_title']); ?></a></li>
				<li class="breadcrumb-item active">Post</li>
			</ol>
		</nav>
		
		<div class="card">
			<div class="card-header">
				<div class="d-flex justify-content-between align-items-start">
					<div>
						<h4 class="mb-1"><?php echo htmlspecialchars($post['title']); ?></h4>
						<small class="text-muted">
							By <strong><?php echo htmlspecialchars($post['author_name']); ?></strong> 
							on <?php echo date('M j, Y g:i A', strtotime($post['created_at'])); ?>
						</small>
					</div>
				</div>
			</div>
			<div class="card-body">
				<div class="post-content">
					<?php echo nl2br(htmlspecialchars($post['content'])); ?>
				</div>
			</div>
		</div>
		
		<div class="mt-3">
			<a href="forum_posts.php?forum_id=<?php echo (int)$post['forum_id']; ?>" class="btn btn-secondary">Back to Forum</a>
		</div>
	</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
