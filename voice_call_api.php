<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

$userId = current_user_id();
if (!$userId) {
	http_response_code(401);
	echo json_encode(['success' => false, 'error' => 'Unauthorized — please log in again']);
	exit;
}

$pdo = get_pdo();

function ensure_call_schema(PDO $pdo): void {
	static $done = false;
	if ($done) return;
	$done = true;

	$cols = [];
	try {
		foreach ($pdo->query('PRAGMA table_info(users)') as $c) {
			$cols[$c['name']] = true;
		}
	} catch (Throwable $e) {
		$cols = [];
	}

	$alters = [
		'online_status' => "ALTER TABLE users ADD COLUMN online_status TEXT DEFAULT 'offline'",
		'call_status' => "ALTER TABLE users ADD COLUMN call_status TEXT DEFAULT 'available'",
		'last_seen' => 'ALTER TABLE users ADD COLUMN last_seen TIMESTAMP',
	];
	foreach ($alters as $name => $sql) {
		if (empty($cols[$name])) {
			try { $pdo->exec($sql); } catch (Throwable $e) {}
		}
	}

	$pdo->exec('CREATE TABLE IF NOT EXISTS user_blocks (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		blocker_id INTEGER NOT NULL,
		blocked_id INTEGER NOT NULL,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		UNIQUE (blocker_id, blocked_id)
	)');

	$pdo->exec('CREATE TABLE IF NOT EXISTS voice_call_sessions (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		caller_id INTEGER NOT NULL,
		receiver_id INTEGER NOT NULL,
		call_type TEXT NOT NULL DEFAULT "voice",
		status TEXT NOT NULL DEFAULT "calling",
		room_id TEXT,
		started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		connected_at TIMESTAMP,
		ended_at TIMESTAMP,
		duration INTEGER DEFAULT 0
	)');

	$pdo->exec('CREATE TABLE IF NOT EXISTS voice_call_signals (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		call_id INTEGER NOT NULL,
		sender_id INTEGER NOT NULL,
		signal_type TEXT NOT NULL,
		payload TEXT NOT NULL,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
	)');
}

try {
	ensure_call_schema($pdo);
	$action = $_POST['action'] ?? $_GET['action'] ?? '';

	switch ($action) {
		case 'heartbeat':
			$pdo->prepare("UPDATE users SET online_status = 'online', last_seen = CURRENT_TIMESTAMP WHERE id = ?")
				->execute([$userId]);
			$stmt = $pdo->prepare("
				SELECT 1 FROM voice_call_sessions
				WHERE (caller_id = ? OR receiver_id = ?) AND status IN ('calling','connected') LIMIT 1
			");
			$stmt->execute([$userId, $userId]);
			if (!$stmt->fetch()) {
				$pdo->prepare("UPDATE users SET call_status = 'available' WHERE id = ?")->execute([$userId]);
			}
			echo json_encode(['success' => true]);
			break;

		case 'initiate_call':
			$receiverId = (int)($_POST['receiver_id'] ?? 0);
			$callType = $_POST['call_type'] ?? 'voice';
			if (!$receiverId) throw new Exception('Receiver ID required');
			if ($receiverId === (int)$userId) throw new Exception('Cannot call yourself');

			$stmt = $pdo->prepare('SELECT 1 FROM friendships WHERE user_id = ? AND friend_id = ?');
			$stmt->execute([$userId, $receiverId]);
			if (!$stmt->fetch()) {
				// try auto-heal reverse friendship if peer already friended me
				$stmt2 = $pdo->prepare('SELECT 1 FROM friendships WHERE user_id = ? AND friend_id = ?');
				$stmt2->execute([$receiverId, $userId]);
				if ($stmt2->fetch()) {
					$pdo->prepare('INSERT OR IGNORE INTO friendships (user_id, friend_id) VALUES (?, ?)')
						->execute([$userId, $receiverId]);
				} else {
					throw new Exception('Can only call friends. Add them in Friends first.');
				}
			}

			try {
				$stmt = $pdo->prepare('
					SELECT 1 FROM user_blocks
					WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)
				');
				$stmt->execute([$userId, $receiverId, $receiverId, $userId]);
				if ($stmt->fetch()) throw new Exception('Cannot call blocked user');
			} catch (Throwable $e) {
				if (strpos($e->getMessage(), 'Cannot call blocked') !== false) throw $e;
			}

			$pdo->prepare("UPDATE users SET online_status = 'online', last_seen = CURRENT_TIMESTAMP WHERE id = ?")
				->execute([$userId]);

			$pdo->prepare("
				UPDATE voice_call_sessions
				SET status = 'ended', ended_at = CURRENT_TIMESTAMP
				WHERE status IN ('calling','connected')
				  AND (caller_id IN (?, ?) OR receiver_id IN (?, ?))
			")->execute([$userId, $receiverId, $userId, $receiverId]);

			try {
				$pdo->prepare("UPDATE users SET call_status = 'available' WHERE id IN (?, ?)")
					->execute([$userId, $receiverId]);
				$pdo->prepare("UPDATE users SET call_status = 'in_call' WHERE id = ?")->execute([$userId]);
			} catch (Throwable $e) {}

			$roomId = 'call_' . time() . '_' . bin2hex(random_bytes(3));
			$stmt = $pdo->prepare('
				INSERT INTO voice_call_sessions (caller_id, receiver_id, call_type, status, room_id)
				VALUES (?, ?, ?, ?, ?)
			');
			$stmt->execute([$userId, $receiverId, $callType, 'calling', $roomId]);
			$callId = (int)$pdo->lastInsertId();

			echo json_encode([
				'success' => true,
				'call_id' => $callId,
				'room_id' => $roomId,
				'call_type' => $callType,
				'receiver_id' => $receiverId
			]);
			break;

		case 'answer_call':
			$callId = (int)($_POST['call_id'] ?? 0);
			$callAction = $_POST['call_action'] ?? '';
			if (!$callId) throw new Exception('Call ID required');

			$stmt = $pdo->prepare('
				SELECT caller_id, receiver_id, status, call_type, room_id
				FROM voice_call_sessions
				WHERE id = ? AND receiver_id = ? AND status = ?
			');
			$stmt->execute([$callId, $userId, 'calling']);
			$call = $stmt->fetch(PDO::FETCH_ASSOC);
			if (!$call) throw new Exception('Call not found or already ended');

			if ($callAction === 'accept') {
				$pdo->prepare("
					UPDATE voice_call_sessions
					SET status = 'connected', connected_at = CURRENT_TIMESTAMP WHERE id = ?
				")->execute([$callId]);
				try {
					$pdo->prepare("UPDATE users SET call_status = 'in_call' WHERE id IN (?, ?)")
						->execute([$call['caller_id'], $userId]);
				} catch (Throwable $e) {}

				echo json_encode([
					'success' => true,
					'action' => 'accepted',
					'room_id' => $call['room_id'],
					'call_type' => $call['call_type'],
					'caller_id' => (int)$call['caller_id'],
					'call_id' => $callId
				]);
			} else {
				$pdo->prepare("
					UPDATE voice_call_sessions
					SET status = 'declined', ended_at = CURRENT_TIMESTAMP WHERE id = ?
				")->execute([$callId]);
				try {
					$pdo->prepare("UPDATE users SET call_status = 'available' WHERE id IN (?, ?)")
						->execute([$call['caller_id'], $userId]);
				} catch (Throwable $e) {}
				echo json_encode(['success' => true, 'action' => 'declined']);
			}
			break;

		case 'end_call':
			$callId = (int)($_POST['call_id'] ?? 0);
			if (!$callId) {
				echo json_encode(['success' => true, 'duration' => 0]);
				break;
			}
			$stmt = $pdo->prepare('
				SELECT caller_id, receiver_id, status, connected_at
				FROM voice_call_sessions
				WHERE id = ? AND (caller_id = ? OR receiver_id = ?) AND status IN (?, ?)
			');
			$stmt->execute([$callId, $userId, $userId, 'calling', 'connected']);
			$call = $stmt->fetch(PDO::FETCH_ASSOC);
			if (!$call) {
				echo json_encode(['success' => true, 'duration' => 0]);
				break;
			}
			$duration = 0;
			if ($call['status'] === 'connected' && $call['connected_at']) {
				$duration = max(0, time() - strtotime($call['connected_at']));
			}
			$pdo->prepare("
				UPDATE voice_call_sessions
				SET status = 'ended', ended_at = CURRENT_TIMESTAMP, duration = ? WHERE id = ?
			")->execute([$duration, $callId]);
			try {
				$pdo->prepare("UPDATE users SET call_status = 'available' WHERE id IN (?, ?)")
					->execute([$call['caller_id'], $call['receiver_id']]);
			} catch (Throwable $e) {}
			echo json_encode(['success' => true, 'duration' => $duration]);
			break;

		case 'check_incoming_calls':
			$pdo->prepare("UPDATE users SET online_status = 'online', last_seen = CURRENT_TIMESTAMP WHERE id = ?")
				->execute([$userId]);
			$stmt = $pdo->prepare('
				SELECT vcs.id, vcs.caller_id, vcs.call_type, vcs.room_id, vcs.started_at, u.username as caller_name
				FROM voice_call_sessions vcs
				JOIN users u ON u.id = vcs.caller_id
				WHERE vcs.receiver_id = ? AND vcs.status = ?
				ORDER BY vcs.id DESC LIMIT 1
			');
			$stmt->execute([$userId, 'calling']);
			$incomingCall = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
			echo json_encode(['success' => true, 'incoming_call' => $incomingCall]);
			break;

		case 'get_call_status':
			$callId = (int)($_POST['call_id'] ?? $_GET['call_id'] ?? 0);
			if (!$callId) throw new Exception('Call ID required');
			$stmt = $pdo->prepare('
				SELECT status, connected_at, ended_at, duration, room_id, caller_id, receiver_id
				FROM voice_call_sessions
				WHERE id = ? AND (caller_id = ? OR receiver_id = ?)
			');
			$stmt->execute([$callId, $userId, $userId]);
			echo json_encode(['success' => true, 'call_status' => $stmt->fetch(PDO::FETCH_ASSOC)]);
			break;

		case 'send_signal':
			$callId = (int)($_POST['call_id'] ?? 0);
			$signalType = trim($_POST['signal_type'] ?? '');
			$payload = $_POST['payload'] ?? '';
			if (!$callId || $signalType === '' || $payload === '') {
				throw new Exception('call_id, signal_type and payload required');
			}
			$stmt = $pdo->prepare('
				SELECT 1 FROM voice_call_sessions
				WHERE id = ? AND (caller_id = ? OR receiver_id = ?) AND status IN (?, ?)
			');
			$stmt->execute([$callId, $userId, $userId, 'calling', 'connected']);
			if (!$stmt->fetch()) throw new Exception('Invalid call for signaling');
			$pdo->prepare('
				INSERT INTO voice_call_signals (call_id, sender_id, signal_type, payload)
				VALUES (?, ?, ?, ?)
			')->execute([$callId, $userId, $signalType, $payload]);
			echo json_encode(['success' => true]);
			break;

		case 'get_signals':
			$callId = (int)($_POST['call_id'] ?? 0);
			$afterId = (int)($_POST['after_id'] ?? 0);
			if (!$callId) throw new Exception('call_id required');
			$stmt = $pdo->prepare('
				SELECT id, sender_id, signal_type, payload, created_at
				FROM voice_call_signals
				WHERE call_id = ? AND sender_id != ? AND id > ?
				ORDER BY id ASC LIMIT 100
			');
			$stmt->execute([$callId, $userId, $afterId]);
			echo json_encode(['success' => true, 'signals' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
			break;

		default:
			throw new Exception('Invalid action: ' . $action);
	}
} catch (Throwable $e) {
	http_response_code(400);
	echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
