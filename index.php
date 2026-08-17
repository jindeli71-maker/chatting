<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['device_type']) && !isset($_GET['skip_device'])) {
	header('Location: device_select.php');
	exit;
}

require_login();

$pdo = get_pdo();
$userId = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_forum') {
	$title = trim($_POST['title'] ?? '');
	$description = trim($_POST['description'] ?? '');
	if ($title !== '') {
		$stmt = $pdo->prepare('INSERT INTO forums (title, description, creator_id) VALUES (?, ?, ?)');
		$stmt->execute([$title, $description, $userId]);
		header('Location: index.php');
		exit;
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_post') {
	$forumId = (int)($_POST['forum_id'] ?? 0);
	$title = trim($_POST['title'] ?? '');
	$content = trim($_POST['content'] ?? '');
	if ($forumId && $title !== '' && $content !== '') {
		$stmt = $pdo->prepare('INSERT INTO forum_posts (forum_id, author_id, title, content) VALUES (?, ?, ?, ?)');
		$stmt->execute([$forumId, $userId, $title, $content]);
		header('Location: index.php');
		exit;
	}
}

$forumsStmt = $pdo->prepare('SELECT id, title FROM forums ORDER BY title');
$forumsStmt->execute();
$forums = $forumsStmt->fetchAll();

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

$friendsStmt = $pdo->prepare('SELECT u.id, u.username FROM friendships f JOIN users u ON u.id = f.friend_id WHERE f.user_id = ? ORDER BY u.username');
$friendsStmt->execute([$userId]);
$friends = $friendsStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<style>
.dashboard-container { height: 100%; overflow-y: auto; background: #F5F5F5; }
.dashboard-layout { display: flex; flex-direction: column; max-width: 760px; margin: 0 auto; }
.dashboard-sidebar {
	background: #fff; padding: 14px 16px; border-bottom: 8px solid #F5F5F5;
	width: 100%; border-radius: 0; box-shadow: none; max-height: none;
}
.dashboard-main { background: transparent; padding: 0; box-shadow: none; border-radius: 0; max-height: none; }
.dashboard-header {
	background: #fff; color: #111; padding: 18px 20px; margin: 0;
	border-bottom: 1px solid #E5E5E5; border-radius: 0; box-shadow: none;
}
.dashboard-header h2 { margin: 0 0 4px; font-weight: 800; font-size: 1.35rem; display: flex; align-items: center; gap: 10px; }
.dashboard-header h2 i { color: #06C755; }
.dashboard-header p { margin: 0; color: #8E8E8E; }
.search-box { margin-bottom: 12px; }
.search-box input {
	width: 100%; border: none; background: #F5F5F5; border-radius: 22px;
	padding: 11px 16px; font-family: inherit;
}
.menu-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
.menu-item {
	display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px;
	border-radius: 20px; background: #F7F7F7; border: none; cursor: pointer;
	font-family: inherit; font-weight: 700; font-size: 0.85rem; color: #333;
}
.menu-item:hover, .menu-item.active { background: #E8F8EF; color: #05B34C; }
.menu-avatar, .post-avatar {
	width: 44px; height: 44px; min-width: 44px; border-radius: 14px;
	background: linear-gradient(145deg, #34D399, #06C755); color: #fff;
	display: flex; align-items: center; justify-content: center; font-weight: 700;
}
.post-card { background: #fff; padding: 18px 20px; border-bottom: 8px solid #F5F5F5; margin: 0; }
.post-header { display: flex; gap: 12px; align-items: center; margin-bottom: 12px; }
.forum-badge { background: #E8F8EF; color: #05B34C; padding: 3px 10px; border-radius: 10px; font-size: 0.75rem; font-weight: 700; }
.create-btn, .wechat-btn {
	background: #06C755; color: #fff !important; border: none; border-radius: 18px;
	padding: 10px 18px; font-weight: 700; text-decoration: none;
}
.create-btn:hover, .wechat-btn:hover { background: #05B34C; color: #fff !important; }
.empty-state { text-align: center; padding: 48px 20px; color: #8E8E8E; background: #fff; }
.post-actions { display: flex; gap: 8px; margin-top: 14px; flex-wrap: wrap; }
.post-actions .action-btn {
	background: #F5F5F5; color: #555; border: none; border-radius: 16px;
	padding: 8px 12px; font-weight: 700; font-size: 0.82rem; cursor: pointer;
}
.like-btn.liked { background: #06C755 !important; color: #fff !important; }
.modal-header { background: #06C755 !important; color: #fff; }
.forum-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
</style>

<div class="dashboard-container line-page">
	<div class="dashboard-layout">
		<div class="dashboard-sidebar">
			<div class="search-box">
				<input type="text" placeholder="Search timeline..." id="searchPosts">
			</div>
			<div class="menu-row">
				<button class="menu-item active" type="button" onclick="showAllPosts()">
					<i class="fas fa-clock"></i> Timeline
				</button>
				<button class="menu-item" type="button" data-bs-toggle="modal" data-bs-target="#createPostModal">
					<i class="fas fa-plus"></i> Post
				</button>
				<button class="menu-item" type="button" data-bs-toggle="modal" data-bs-target="#createForumModal">
					<i class="fas fa-folder-plus"></i> Forum
				</button>
			</div>
			<?php if (!empty($forums)): ?>
			<div class="forum-list">
				<?php foreach ($forums as $forum): ?>
				<button class="menu-item" type="button" onclick="filterByForum(<?php echo (int)$forum['id']; ?>)">
					<i class="fas fa-folder"></i> <?php echo htmlspecialchars($forum['title']); ?>
				</button>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>

		<div class="dashboard-main">
			<div class="dashboard-header">
				<h2><i class="fas fa-clock"></i> Timeline</h2>
				<p>Hi <?php echo htmlspecialchars($_SESSION['username']); ?> — see what friends are sharing</p>
			</div>

			<div id="posts-container">
				<?php if (empty($posts)): ?>
				<div class="empty-state">
					<i class="fas fa-comments" style="font-size:2.5rem;color:#D0D0D0;display:block;margin-bottom:12px;"></i>
					<h3>No posts yet</h3>
					<p>Be the first to share something on the timeline.</p>
					<button class="create-btn" data-bs-toggle="modal" data-bs-target="#createPostModal">
						<i class="fas fa-plus"></i> Create post
					</button>
				</div>
				<?php else: ?>
				<?php foreach ($posts as $post): ?>
				<div class="post-card timeline-post" data-post-id="<?php echo (int)$post['id']; ?>" data-forum-id="<?php echo (int)$post['forum_id']; ?>">
					<div class="post-header">
						<div class="post-avatar"><?php echo strtoupper(substr($post['author_name'], 0, 1)); ?></div>
						<div>
							<div class="fw-bold author-name"><?php echo htmlspecialchars($post['author_name']); ?></div>
							<div class="text-muted small">
								in <span class="forum-badge"><?php echo htmlspecialchars($post['forum_title']); ?></span>
								· <?php echo date('M j, Y g:i A', strtotime($post['created_at'])); ?>
							</div>
						</div>
					</div>
					<h5 class="mb-2">
						<a href="forum_post.php?post_id=<?php echo (int)$post['id']; ?>" class="text-decoration-none text-dark">
							<?php echo htmlspecialchars($post['title']); ?>
						</a>
					</h5>
					<p class="mb-0 post-body"><?php echo nl2br(htmlspecialchars(substr($post['content'], 0, 200))); ?><?php echo strlen($post['content']) > 200 ? '...' : ''; ?></p>
					<div class="post-actions">
						<button class="action-btn like-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>" data-post-id="<?php echo (int)$post['id']; ?>">
							<i class="fas fa-heart"></i> <span class="like-count"><?php echo (int)$post['like_count']; ?></span>
						</button>
						<button class="action-btn comment-btn" data-post-id="<?php echo (int)$post['id']; ?>">
							<i class="fas fa-comment"></i> <?php echo (int)$post['comment_count']; ?>
						</button>
						<button class="action-btn share-btn" data-post-id="<?php echo (int)$post['id']; ?>">
							<i class="fas fa-share"></i> <?php echo (int)$post['share_count']; ?>
						</button>
					</div>
				</div>
				<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="createPostModal" tabindex="-1">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Create post</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<form method="post">
				<div class="modal-body">
					<input type="hidden" name="action" value="create_post">
					<div class="mb-3">
						<label class="form-label">Forum</label>
						<select name="forum_id" class="form-select" required>
							<option value="">Choose a forum</option>
							<?php foreach ($forums as $forum): ?>
							<option value="<?php echo (int)$forum['id']; ?>"><?php echo htmlspecialchars($forum['title']); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="mb-3">
						<label class="form-label">Title</label>
						<input type="text" name="title" class="form-control" required>
					</div>
					<div class="mb-3">
						<label class="form-label">Content</label>
						<textarea name="content" class="form-control" rows="6" required></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="wechat-btn">Post</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="createForumModal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Create forum</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<form method="post">
				<div class="modal-body">
					<input type="hidden" name="action" value="create_forum">
					<div class="mb-3">
						<label class="form-label">Title</label>
						<input type="text" name="title" class="form-control" required>
					</div>
					<div class="mb-3">
						<label class="form-label">Description</label>
						<textarea name="description" class="form-control" rows="3"></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="wechat-btn">Create</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="commentModal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Add comment</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<form id="commentForm">
				<div class="modal-body">
					<input type="hidden" id="commentPostId">
					<textarea id="commentContent" class="form-control" rows="4" placeholder="Write a comment..." required></textarea>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="wechat-btn">Comment</button>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="modal fade" id="shareModal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Share post</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<form id="shareForm">
				<div class="modal-body">
					<input type="hidden" id="sharePostId">
					<div class="mb-3">
						<label class="form-label">Friend</label>
						<select id="shareFriendId" class="form-select" required>
							<option value="">Select a friend</option>
							<?php foreach ($friends as $friend): ?>
							<option value="<?php echo (int)$friend['id']; ?>"><?php echo htmlspecialchars($friend['username']); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="mb-3">
						<label class="form-label">Message (optional)</label>
						<textarea id="shareMessage" class="form-control" rows="3"></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="wechat-btn">Share</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
function showAllPosts() {
	document.querySelectorAll('.post-card').forEach(card => { card.style.display = 'block'; });
	document.querySelectorAll('.menu-item').forEach(item => item.classList.remove('active'));
}

function filterByForum(forumId) {
	document.querySelectorAll('.post-card').forEach(card => {
		card.style.display = String(card.dataset.forumId) === String(forumId) ? 'block' : 'none';
	});
}

const searchInput = document.getElementById('searchPosts');
if (searchInput) {
	searchInput.addEventListener('input', function() {
		const term = this.value.toLowerCase();
		document.querySelectorAll('.post-card').forEach(card => {
			const title = (card.querySelector('h5 a')?.textContent || '').toLowerCase();
			const content = (card.querySelector('.post-body')?.textContent || '').toLowerCase();
			const author = (card.querySelector('.author-name')?.textContent || '').toLowerCase();
			card.style.display = (title.includes(term) || content.includes(term) || author.includes(term)) ? 'block' : 'none';
		});
	});
}

document.querySelectorAll('.like-btn').forEach(btn => {
	btn.addEventListener('click', async function() {
		const postId = this.dataset.postId;
		const isLiked = this.classList.contains('liked');
		try {
			const response = await fetch('social_api.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({ action: isLiked ? 'unlike' : 'like', post_id: postId })
			});
			const result = await response.json();
			if (result.success) {
				this.classList.toggle('liked');
				const countEl = this.querySelector('.like-count');
				const count = parseInt(countEl.textContent, 10) || 0;
				countEl.textContent = isLiked ? Math.max(0, count - 1) : count + 1;
			}
		} catch (e) {
			console.error(e);
		}
	});
});

document.querySelectorAll('.comment-btn').forEach(btn => {
	btn.addEventListener('click', function() {
		document.getElementById('commentPostId').value = this.dataset.postId;
		new bootstrap.Modal(document.getElementById('commentModal')).show();
	});
});

document.getElementById('commentForm').addEventListener('submit', async function(e) {
	e.preventDefault();
	try {
		const response = await fetch('social_api.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({
				action: 'comment',
				post_id: document.getElementById('commentPostId').value,
				content: document.getElementById('commentContent').value
			})
		});
		const result = await response.json();
		if (result.success) location.reload();
	} catch (err) {
		console.error(err);
	}
});

document.querySelectorAll('.share-btn').forEach(btn => {
	btn.addEventListener('click', function() {
		document.getElementById('sharePostId').value = this.dataset.postId;
		new bootstrap.Modal(document.getElementById('shareModal')).show();
	});
});

document.getElementById('shareForm').addEventListener('submit', async function(e) {
	e.preventDefault();
	try {
		const response = await fetch('social_api.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({
				action: 'share',
				post_id: document.getElementById('sharePostId').value,
				friend_id: document.getElementById('shareFriendId').value,
				message: document.getElementById('shareMessage').value
			})
		});
		const result = await response.json();
		if (result.success) location.reload();
	} catch (err) {
		console.error(err);
	}
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
