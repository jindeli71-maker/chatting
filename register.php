<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (current_user_id()) {
	header('Location: chat.php');
	exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = trim($_POST['username'] ?? '');
	$password = $_POST['password'] ?? '';
	$confirm  = $_POST['confirm'] ?? '';

	if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
		$error = 'Username must be 3-32 chars: letters, digits, underscore.';
	} elseif (strlen($password) < 6) {
		$error = 'Password must be at least 6 characters.';
	} elseif ($password !== $confirm) {
		$error = 'Passwords do not match.';
	} elseif (find_user_by_username($username)) {
		$error = 'Username already taken.';
	} else {
		$user = create_user($username, $password);
		login_user($user);
		header('Location: chat.php');
		exit;
	}
}

include __DIR__ . '/includes/header.php';
?>
<div class="register-container line-auth">
	<div class="register-card line-auth-card">
		<div class="line-logo-mark">L</div>
		<h1 class="register-title">Join LINE Chat</h1>
		<p class="register-subtitle">Create an account and start messaging</p>

		<?php if ($error): ?>
		<div class="error-alert">
			<i class="fas fa-exclamation-circle"></i>
			<?php echo htmlspecialchars($error); ?>
		</div>
		<?php endif; ?>

		<form method="post">
			<div class="form-group" style="margin-bottom: 18px;">
				<label class="form-label">Username</label>
				<input name="username" class="form-input" placeholder="Choose a username" required autocomplete="username">
			</div>
			<div class="form-group" style="margin-bottom: 18px;">
				<label class="form-label">Password</label>
				<input type="password" name="password" class="form-input" placeholder="At least 6 characters" required autocomplete="new-password">
			</div>
			<div class="form-group" style="margin-bottom: 22px;">
				<label class="form-label">Confirm password</label>
				<input type="password" name="confirm" class="form-input" placeholder="Re-enter password" required autocomplete="new-password">
			</div>
			<button type="submit" class="register-btn login-btn">Sign up</button>
			<a href="login.php" class="register-link">Already have an account? Log in</a>
		</form>
	</div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
