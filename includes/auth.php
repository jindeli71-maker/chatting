<?php
require_once __DIR__ . '/db.php';
session_start();

function find_user_by_username(string $username): ?array {
	$pdo = get_pdo();
	$stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
	$stmt->execute([$username]);
	$user = $stmt->fetch();
	return $user ?: null;
}

function create_user(string $username, string $password): array {
	$pdo = get_pdo();
	$hash = password_hash($password, PASSWORD_DEFAULT);
	$stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
	$stmt->execute([$username, $hash]);
	return ['id' => (int)$pdo->lastInsertId(), 'username' => $username];
}

function authenticate_user(string $username, string $password): ?array {
	$user = find_user_by_username($username);
	if (!$user) return null;
	if (!password_verify($password, $user['password_hash'])) return null;
	return ['id' => (int)$user['id'], 'username' => $user['username']];
}

function login_user(array $user): void {
	$_SESSION['user_id'] = $user['id'];
	$_SESSION['username'] = $user['username'];
}

function logout_user(): void {
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	}
	session_destroy();
}

function current_user_id(): ?int {
	return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function require_login(): void {
	if (!current_user_id()) {
		header('Location: login.php');
		exit;
	}
}
