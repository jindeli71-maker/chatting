<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<title>LINE Chat</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<link href="assets/css/line-style.css" rel="stylesheet">
</head>
<body>
<?php if (!empty($_SESSION['user_id'])): ?>
<div class="wechat-container line-app">
	<nav class="wechat-sidebar line-rail" aria-label="Main">
		<div class="line-rail-brand" title="LINE Chat">L</div>
		<a href="friends.php" class="nav-item <?php echo $currentPage === 'friends.php' ? 'active' : ''; ?>" title="Friends">
			<i class="fas fa-user-friends"></i>
			<span class="nav-label">Friends</span>
		</a>
		<a href="chat.php" class="nav-item <?php echo in_array($currentPage, ['chat.php', 'group_chat.php'], true) ? 'active' : ''; ?>" title="Chats">
			<i class="fas fa-comment"></i>
			<span class="nav-label">Chats</span>
		</a>
		<a href="index.php" class="nav-item <?php echo in_array($currentPage, ['index.php', 'forums.php', 'forum_posts.php', 'forum_post.php'], true) ? 'active' : ''; ?>" title="Timeline">
			<i class="fas fa-clock"></i>
			<span class="nav-label">Timeline</span>
		</a>
		<a href="groups.php" class="nav-item <?php echo in_array($currentPage, ['groups.php', 'group_manage.php'], true) ? 'active' : ''; ?>" title="Groups">
			<i class="fas fa-users"></i>
			<span class="nav-label">Groups</span>
		</a>
		<div class="line-rail-spacer"></div>
		<a href="logout.php" class="nav-item" title="Log out">
			<i class="fas fa-sign-out-alt"></i>
			<span class="nav-label">Logout</span>
		</a>
	</nav>
	<div class="wechat-main line-main">
<?php else: ?>
<div class="container-fluid p-0">
<?php endif; ?>
