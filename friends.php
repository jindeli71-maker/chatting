<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (!current_user_id()) {
	header('Location: login.php');
	exit;
}

$userId = current_user_id();
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';

	switch ($action) {
		case 'send':
			$targetId = (int)($_POST['target_id'] ?? 0);
			if ($targetId && $targetId !== $userId) {
				try {
					$stmt = $pdo->prepare('SELECT id FROM friend_requests WHERE requester_id = ? AND receiver_id = ?');
					$stmt->execute([$userId, $targetId]);
					if (!$stmt->fetch()) {
						$stmt = $pdo->prepare('INSERT INTO friend_requests (requester_id, receiver_id) VALUES (?, ?)');
						$stmt->execute([$userId, $targetId]);
						$_SESSION['friend_message'] = 'Friend request sent.';
					} else {
						$_SESSION['friend_error'] = 'Friend request already sent.';
					}
				} catch (Exception $e) {
					$_SESSION['friend_error'] = 'Error sending friend request.';
				}
			}
			break;

		case 'accept':
			$requestId = (int)($_POST['request_id'] ?? 0);
			if ($requestId) {
				try {
					$pdo->beginTransaction();
					$stmt = $pdo->prepare('SELECT requester_id FROM friend_requests WHERE id = ? AND receiver_id = ? AND status = "pending"');
					$stmt->execute([$requestId, $userId]);
					$request = $stmt->fetch();
					if ($request) {
						$stmt = $pdo->prepare('UPDATE friend_requests SET status = "accepted" WHERE id = ?');
						$stmt->execute([$requestId]);
						$stmt = $pdo->prepare('INSERT INTO friendships (user_id, friend_id) VALUES (?, ?), (?, ?)');
						$stmt->execute([$userId, $request['requester_id'], $request['requester_id'], $userId]);
						$pdo->commit();
						$_SESSION['friend_message'] = 'Friend request accepted.';
					} else {
						$pdo->rollBack();
						$_SESSION['friend_error'] = 'Invalid friend request.';
					}
				} catch (Exception $e) {
					$pdo->rollBack();
					$_SESSION['friend_error'] = 'Error accepting friend request.';
				}
			}
			break;

		case 'reject':
			$requestId = (int)($_POST['request_id'] ?? 0);
			if ($requestId) {
				try {
					$stmt = $pdo->prepare('UPDATE friend_requests SET status = "rejected" WHERE id = ? AND receiver_id = ?');
					$stmt->execute([$requestId, $userId]);
					$_SESSION['friend_message'] = 'Friend request rejected.';
				} catch (Exception $e) {
					$_SESSION['friend_error'] = 'Error rejecting friend request.';
				}
			}
			break;

		case 'unfriend':
			$friendId = (int)($_POST['friend_id'] ?? 0);
			if ($friendId) {
				try {
					$stmt = $pdo->prepare('DELETE FROM friendships WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)');
					$stmt->execute([$userId, $friendId, $friendId, $userId]);
					$_SESSION['friend_message'] = 'Friend removed.';
				} catch (Exception $e) {
					$_SESSION['friend_error'] = 'Error removing friend.';
				}
			}
			break;
	}

	header('Location: friends.php');
	exit;
}

$q = trim($_GET['q'] ?? '');
$searchResults = [];
if ($q !== '') {
	$stmt = $pdo->prepare('
		SELECT id, username
		FROM users
		WHERE username LIKE ?
			AND id != ?
			AND id NOT IN (
				SELECT friend_id FROM friendships WHERE user_id = ?
				UNION
				SELECT receiver_id FROM friend_requests WHERE requester_id = ? AND status = "pending"
			)
		LIMIT 20
	');
	$stmt->execute(["%$q%", $userId, $userId, $userId]);
	$searchResults = $stmt->fetchAll();
}

$stmt = $pdo->prepare('
	SELECT fr.id as request_id, u.id, u.username
	FROM friend_requests fr
	JOIN users u ON u.id = fr.requester_id
	WHERE fr.receiver_id = ? AND fr.status = "pending"
	ORDER BY fr.created_at DESC
');
$stmt->execute([$userId]);
$pending = $stmt->fetchAll();

$stmt = $pdo->prepare('
	SELECT
		u.id, u.username,
		EXISTS(SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = u.id) as is_blocked
	FROM friendships f
	JOIN users u ON u.id = f.friend_id
	WHERE f.user_id = ?
	ORDER BY u.username
');
$stmt->execute([$userId, $userId]);
$friends = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<div class="friends-container line-page">
	<div class="friends-main line-sheet">
		<div class="page-header line-topbar">
			<h2><i class="fas fa-user-friends"></i> Friends</h2>
			<div class="search-section">
				<form method="get">
					<input name="q" placeholder="Add friends by username..." value="<?php echo htmlspecialchars($q); ?>">
				</form>
			</div>
		</div>

		<?php if (isset($_SESSION['friend_message'])): ?>
		<div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
			<?php echo htmlspecialchars($_SESSION['friend_message']); unset($_SESSION['friend_message']); ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
		<?php endif; ?>

		<?php if (isset($_SESSION['friend_error'])): ?>
		<div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
			<?php echo htmlspecialchars($_SESSION['friend_error']); unset($_SESSION['friend_error']); ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
		<?php endif; ?>

		<?php if ($q !== ''): ?>
		<section class="friends-section line-section">
			<h5 class="section-title"><i class="fas fa-search"></i> Search · <?php echo count($searchResults); ?></h5>
			<?php if (empty($searchResults)): ?>
			<div class="no-results">
				<i class="fas fa-user-slash"></i>
				<p>No users found.</p>
			</div>
			<?php else: ?>
			<?php foreach ($searchResults as $u): ?>
			<div class="friend-card line-row">
				<div class="friend-avatar"><?php echo strtoupper(substr($u['username'], 0, 1)); ?></div>
				<div class="friend-info">
					<div class="friend-name"><?php echo htmlspecialchars($u['username']); ?></div>
					<div class="friend-status">Tap to send a friend request</div>
				</div>
				<div class="friend-actions">
					<form method="post">
						<input type="hidden" name="action" value="send">
						<input type="hidden" name="target_id" value="<?php echo (int)$u['id']; ?>">
						<button type="submit" class="action-btn btn-send"><i class="fas fa-user-plus"></i> Add</button>
					</form>
				</div>
			</div>
			<?php endforeach; ?>
			<?php endif; ?>
		</section>
		<?php endif; ?>

		<?php if (!empty($pending)): ?>
		<section class="friends-section line-section">
			<h5 class="section-title"><i class="fas fa-user-clock"></i> Friend requests · <?php echo count($pending); ?></h5>
			<?php foreach ($pending as $p): ?>
			<div class="friend-card line-row">
				<div class="friend-avatar"><?php echo strtoupper(substr($p['username'], 0, 1)); ?></div>
				<div class="friend-info">
					<div class="friend-name"><?php echo htmlspecialchars($p['username']); ?></div>
					<div class="friend-status">Wants to be your friend</div>
				</div>
				<div class="friend-actions">
					<form method="post" style="display:inline;">
						<input type="hidden" name="action" value="accept">
						<input type="hidden" name="request_id" value="<?php echo (int)$p['request_id']; ?>">
						<button type="submit" class="action-btn btn-accept"><i class="fas fa-check"></i> Accept</button>
					</form>
					<form method="post" style="display:inline;">
						<input type="hidden" name="action" value="reject">
						<input type="hidden" name="request_id" value="<?php echo (int)$p['request_id']; ?>">
						<button type="submit" class="action-btn btn-reject"><i class="fas fa-times"></i> Decline</button>
					</form>
				</div>
			</div>
			<?php endforeach; ?>
		</section>
		<?php endif; ?>

		<section class="friends-section line-section">
			<h5 class="section-title"><i class="fas fa-address-book"></i> My friends · <?php echo count($friends); ?></h5>
			<?php if (empty($friends)): ?>
			<div class="no-results">
				<i class="fas fa-user-friends"></i>
				<p>No friends yet. Search above to add someone.</p>
			</div>
			<?php else: ?>
			<?php
			$letter = '';
			foreach ($friends as $f):
				$first = strtoupper(substr($f['username'], 0, 1));
				if ($first !== $letter):
					$letter = $first;
			?>
			<div class="section-title" style="padding-top: 4px;"><?php echo htmlspecialchars($letter); ?></div>
			<?php endif; ?>
			<div class="friend-card line-row <?php echo $f['is_blocked'] ? 'blocked-user' : ''; ?>">
				<div class="friend-avatar"><?php echo strtoupper(substr($f['username'], 0, 1)); ?></div>
				<div class="friend-info">
					<div class="friend-name">
						<?php echo htmlspecialchars($f['username']); ?>
						<?php if ($f['is_blocked']): ?><span class="blocked-badge">Blocked</span><?php endif; ?>
					</div>
					<div class="friend-status"><?php echo $f['is_blocked'] ? 'Blocked' : 'Friend'; ?></div>
				</div>
				<div class="friend-actions">
					<?php if (!$f['is_blocked']): ?>
					<a href="chat.php?user_id=<?php echo (int)$f['id']; ?>" class="action-btn btn-chat"><i class="fas fa-comment"></i> Chat</a>
					<?php endif; ?>
					<form method="post" style="display:inline;">
						<input type="hidden" name="action" value="unfriend">
						<input type="hidden" name="friend_id" value="<?php echo (int)$f['id']; ?>">
						<button type="submit" class="action-btn btn-unfriend" onclick="return confirm('Remove this friend?')">
							<i class="fas fa-user-minus"></i>
						</button>
					</form>
				</div>
			</div>
			<?php endforeach; ?>
			<?php endif; ?>
		</section>
	</div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
