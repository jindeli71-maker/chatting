<?php
require_once __DIR__ . '/includes/auth.php';

$deviceType = $_GET['device'] ?? $_SESSION['device_type'] ?? 'desktop';
$_SESSION['device_type'] = $deviceType;

if (current_user_id()) {
	header('Location: chat.php');
	exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = trim($_POST['username'] ?? '');
	$password = $_POST['password'] ?? '';
	$user = authenticate_user($username, $password);
	if ($user) {
		login_user($user);
		header('Location: chat.php');
		exit;
	}
	$error = 'Invalid username or password.';
}

include __DIR__ . '/includes/header.php';
?>
<div class="login-container line-auth">
	<div class="login-card line-auth-card">
		<div class="line-logo-mark">L</div>
		<h1 class="login-title">LINE Chat</h1>
		<p class="login-subtitle">Sign in to keep talking with friends</p>

		<div class="device-indicator">
			<i class="fas fa-<?php echo $deviceType === 'mobile' ? 'mobile-alt' : 'desktop'; ?>"></i>
			<span><?php echo ucfirst(htmlspecialchars($deviceType)); ?> mode</span>
			<a href="device_select.php" class="change-device">Change</a>
		</div>

		<?php if ($error): ?>
		<div class="error-alert">
			<i class="fas fa-exclamation-circle"></i>
			<?php echo htmlspecialchars($error); ?>
		</div>
		<?php endif; ?>

		<form method="post" id="loginForm">
			<div class="form-group" style="margin-bottom: 18px;">
				<label class="form-label">Username</label>
				<input name="username" class="form-input" placeholder="Enter username" required autocomplete="username">
			</div>
			<div class="form-group" style="margin-bottom: 22px;">
				<label class="form-label">Password</label>
				<input type="password" name="password" class="form-input" placeholder="Enter password" required autocomplete="current-password">
			</div>
			<button type="submit" class="login-btn">Log in</button>
			<a href="register.php" class="register-link">Create new account</a>
		</form>
	</div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
