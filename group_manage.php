<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();
$groupId = (int)($_GET['group_id'] ?? 0);

if (!$groupId) {
	header('Location: groups.php');
	exit;
}

// Check if user is member of group
$stmt = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
$stmt->execute([$groupId, $userId]);
if (!$stmt->fetch()) {
	header('Location: groups.php');
	exit;
}

// Get group info
$stmt = $pdo->prepare('SELECT * FROM group_chats WHERE id = ?');
$stmt->execute([$groupId]);
$group = $stmt->fetch();

if (!$group) {
	header('Location: groups.php');
	exit;
}

// Handle member management
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';
	
	if ($action === 'add_member') {
		$friendId = (int)($_POST['friend_id'] ?? 0);
		if ($friendId) {
			$stmt = $pdo->prepare('INSERT IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)');
			$stmt->execute([$groupId, $friendId]);
		}
	} elseif ($action === 'remove_member') {
		$memberId = (int)($_POST['member_id'] ?? 0);
		if ($memberId && $memberId !== $userId) { // Can't remove yourself
			$stmt = $pdo->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?');
			$stmt->execute([$groupId, $memberId]);
		}
	}
	
	header('Location: group_manage.php?group_id=' . $groupId);
	exit;
}

// Get group members
$stmt = $pdo->prepare('
	SELECT gm.*, u.username 
	FROM group_members gm 
	JOIN users u ON u.id = gm.user_id 
	WHERE gm.group_id = ? 
	ORDER BY gm.joined_at ASC
');
$stmt->execute([$groupId]);
$members = $stmt->fetchAll();

// Get user's friends not in group
$stmt = $pdo->prepare('
	SELECT u.id, u.username 
	FROM friendships f 
	JOIN users u ON u.id = f.friend_id 
	WHERE f.user_id = ? 
	AND u.id NOT IN (SELECT user_id FROM group_members WHERE group_id = ?)
	ORDER BY u.username
');
$stmt->execute([$userId, $groupId]);
$availableFriends = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="row">
	<div class="col-12">
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="groups.php">Groups</a></li>
				<li class="breadcrumb-item active"><?php echo htmlspecialchars($group['name']); ?></li>
			</ol>
		</nav>
		
		<div class="d-flex justify-content-between align-items-center mb-4">
			<div>
				<h2><?php echo htmlspecialchars($group['name']); ?></h2>
				<p class="text-muted"><?php echo htmlspecialchars($group['description']); ?></p>
			</div>
			<a href="group_chat.php?group_id=<?php echo (int)$groupId; ?>" class="btn btn-primary">Open Chat</a>
		</div>
		
		<div class="row">
			<div class="col-md-6">
				<h4>Group Members</h4>
				<ul class="list-group">
					<?php foreach ($members as $member): ?>
					<li class="list-group-item d-flex justify-content-between align-items-center">
						<span><?php echo htmlspecialchars($member['username']); ?></span>
						<?php if ($member['user_id'] !== $userId): ?>
						<form method="post" class="d-inline" onsubmit="return confirm('Remove this member?');">
							<input type="hidden" name="action" value="remove_member">
							<input type="hidden" name="member_id" value="<?php echo (int)$member['user_id']; ?>">
							<button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
						</form>
						<?php else: ?>
						<small class="text-muted">You</small>
						<?php endif; ?>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>
			
			<div class="col-md-6">
				<h4>Add Members</h4>
				<?php if (empty($availableFriends)): ?>
				<p class="text-muted">No friends available to add.</p>
				<?php else: ?>
				<ul class="list-group">
					<?php foreach ($availableFriends as $friend): ?>
					<li class="list-group-item d-flex justify-content-between align-items-center">
						<span><?php echo htmlspecialchars($friend['username']); ?></span>
						<form method="post" class="d-inline">
							<input type="hidden" name="action" value="add_member">
							<input type="hidden" name="friend_id" value="<?php echo (int)$friend['id']; ?>">
							<button class="btn btn-sm btn-primary" type="submit">Add</button>
						</form>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
