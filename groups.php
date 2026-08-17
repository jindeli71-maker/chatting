<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_group') {
	$name = trim($_POST['name'] ?? '');
	$description = trim($_POST['description'] ?? '');
	$selectedFriends = $_POST['friends'] ?? [];

	if ($name !== '') {
		$pdo->beginTransaction();
		try {
			$stmt = $pdo->prepare('INSERT INTO group_chats (name, description, creator_id) VALUES (?, ?, ?)');
			$stmt->execute([$name, $description, $userId]);
			$groupId = $pdo->lastInsertId();

			$stmt = $pdo->prepare('INSERT INTO group_members (group_id, user_id) VALUES (?, ?)');
			$stmt->execute([$groupId, $userId]);

			foreach ($selectedFriends as $friendId) {
				$friendId = (int)$friendId;
				if ($friendId > 0) {
					$stmt = $pdo->prepare('INSERT INTO group_members (group_id, user_id) VALUES (?, ?)');
					$stmt->execute([$groupId, $friendId]);
				}
			}

			$pdo->commit();
			header('Location: groups.php');
			exit;
		} catch (Exception $e) {
			$pdo->rollBack();
		}
	}
}

$stmt = $pdo->prepare('
	SELECT gc.*, COUNT(gm.user_id) as member_count
	FROM group_chats gc
	LEFT JOIN group_members gm ON gm.group_id = gc.id
	WHERE gc.id IN (
		SELECT group_id FROM group_members WHERE user_id = ?
	)
	GROUP BY gc.id
	ORDER BY gc.created_at DESC
');
$stmt->execute([$userId]);
$groups = $stmt->fetchAll();

$friendsStmt = $pdo->prepare('SELECT u.id, u.username FROM friendships f JOIN users u ON u.id = f.friend_id WHERE f.user_id = ? ORDER BY u.username');
$friendsStmt->execute([$userId]);
$friends = $friendsStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="groups-container line-page">
	<div class="groups-main line-sheet">
		<div class="page-header line-topbar">
			<h2 class="page-title"><i class="fas fa-users"></i> Groups</h2>
			<button class="create-btn" data-bs-toggle="modal" data-bs-target="#createGroupModal">
				<i class="fas fa-plus"></i> Create
			</button>
		</div>

		<?php if (empty($groups)): ?>
		<div class="no-groups">
			<i class="fas fa-users"></i>
			<h4>No groups yet</h4>
			<p>Create a group to chat with several friends at once.</p>
		</div>
		<?php else: ?>
		<div class="groups-grid">
			<?php foreach ($groups as $group): ?>
			<div class="group-card">
				<div class="group-header">
					<div class="group-avatar"><?php echo strtoupper(substr($group['name'], 0, 2)); ?></div>
					<div class="group-info">
						<h5>
							<a href="group_chat.php?group_id=<?php echo (int)$group['id']; ?>">
								<?php echo htmlspecialchars($group['name']); ?>
							</a>
						</h5>
					</div>
				</div>
				<div class="group-description">
					<?php echo htmlspecialchars($group['description'] ?: 'No description'); ?>
				</div>
				<div class="group-meta">
					<div class="meta-item">
						<i class="fas fa-users"></i>
						<span><?php echo (int)$group['member_count']; ?> members</span>
					</div>
					<div class="meta-item">
						<i class="fas fa-calendar"></i>
						<span><?php echo date('M j, Y', strtotime($group['created_at'])); ?></span>
					</div>
				</div>
				<div class="group-actions">
					<a href="group_chat.php?group_id=<?php echo (int)$group['id']; ?>" class="group-btn btn-primary-custom">
						<i class="fas fa-comments"></i> Open
					</a>
					<a href="group_manage.php?group_id=<?php echo (int)$group['id']; ?>" class="group-btn btn-outline-custom">
						<i class="fas fa-cog"></i> Manage
					</a>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>
</div>

<div class="modal fade" id="createGroupModal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Create group</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
			</div>
			<form method="post">
				<div class="modal-body">
					<input type="hidden" name="action" value="create_group">
					<div class="mb-3">
						<label class="form-label">Group name</label>
						<input type="text" name="name" class="form-control" required>
					</div>
					<div class="mb-3">
						<label class="form-label">Description</label>
						<textarea name="description" class="form-control" rows="3"></textarea>
					</div>
					<div class="mb-3">
						<label class="form-label">Add friends</label>
						<?php if (empty($friends)): ?>
						<p class="text-muted mb-0">Add friends first to invite them.</p>
						<?php else: ?>
						<?php foreach ($friends as $friend): ?>
						<div class="form-check">
							<input class="form-check-input" type="checkbox" name="friends[]" value="<?php echo (int)$friend['id']; ?>" id="friend_<?php echo (int)$friend['id']; ?>">
							<label class="form-check-label" for="friend_<?php echo (int)$friend['id']; ?>">
								<?php echo htmlspecialchars($friend['username']); ?>
							</label>
						</div>
						<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn" style="background:var(--line-green);color:#fff;">Create</button>
				</div>
			</form>
		</div>
	</div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
