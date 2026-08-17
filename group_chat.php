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

// Get group members
$stmt = $pdo->prepare('
	SELECT u.id, u.username 
	FROM group_members gm 
	JOIN users u ON u.id = gm.user_id 
	WHERE gm.group_id = ? 
	ORDER BY u.username
');
$stmt->execute([$groupId]);
$members = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<style>
/* Group chat — LINE-aligned page styles (shell comes from line-style.css) */
.group-chat-container {
	height: 100%;
	width: 100%;
	display: flex;
	flex-direction: column;
	background: #fff;
	overflow: hidden;
}
.group-chat-main {
	flex: 1;
	min-height: 0;
	display: flex;
	flex-direction: column;
	background: #fff;
	overflow: hidden;
}
.group-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 12px 16px;
	background: rgba(255, 255, 255, 0.96);
	border-bottom: 1px solid var(--line-border, #E5E5E5);
	z-index: 2;
}
.group-info {
	display: flex;
	align-items: center;
	gap: 12px;
	min-width: 0;
}
.group-header .group-avatar {
	width: 40px;
	height: 40px;
	min-width: 40px;
	font-size: 0.95rem;
}
.group-details {
	min-width: 0;
}
.group-details h3 {
	margin: 0;
	font-size: 1.05rem;
	font-weight: 800;
	color: var(--line-text, #111);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.group-details p {
	margin: 2px 0 0;
	font-size: 0.78rem;
	color: var(--line-text-secondary, #8E8E8E);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.group-actions {
	display: flex;
	gap: 8px;
	flex-shrink: 0;
}
.group-header .group-btn {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 7px 12px;
	border-radius: 10px;
	border: 1px solid var(--line-border, #E5E5E5);
	background: #fff;
	color: #555;
	font-size: 0.85rem;
	font-weight: 600;
	text-decoration: none;
}
.group-header .group-btn:hover {
	background: var(--line-green-soft, #E8F8EF);
	border-color: var(--line-green, #06C755);
	color: var(--line-green-dark, #05B34C);
}
.group-content {
	display: flex;
	flex: 1;
	min-height: 0;
	overflow: hidden;
}
.group-sidebar {
	width: 260px;
	min-width: 220px;
	background: #fff;
	border-right: 1px solid var(--line-border, #E5E5E5);
	padding: 14px 12px;
	overflow-y: auto;
}
.group-sidebar h6 {
	margin: 0 0 12px;
	color: var(--line-text, #111);
	font-weight: 700;
	font-size: 0.85rem;
}
.member-item {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 8px 6px;
	border-radius: 10px;
}
.member-item:hover { background: var(--line-hover, #F7F7F7); }
.member-avatar {
	width: 36px;
	height: 36px;
	min-width: 36px;
	border-radius: 12px;
	background: linear-gradient(145deg, #34D399, #06C755);
	display: flex;
	align-items: center;
	justify-content: center;
	color: #fff;
	font-weight: 700;
	font-size: 0.75rem;
}
.member-name {
	flex: 1;
	min-width: 0;
	font-weight: 600;
	font-size: 0.9rem;
	color: var(--line-text, #111);
}
.member-name small { color: var(--line-green, #06C755); font-weight: 700; }
.online-indicator {
	width: 8px;
	height: 8px;
	border-radius: 50%;
	background: var(--line-green, #06C755);
	flex-shrink: 0;
}
.chat-area {
	flex: 1;
	min-width: 0;
	display: flex;
	flex-direction: column;
	background: var(--line-chat-bg, #8EABBE);
	position: relative;
}
.messages-container {
	flex: 1;
	padding: 16px;
	overflow-y: auto;
	background: transparent;
}
.message-item {
	display: flex;
	align-items: flex-start;
	gap: 10px;
	margin-bottom: 12px;
}
.message-item.own { flex-direction: row-reverse; }
.message-avatar {
	width: 34px;
	height: 34px;
	min-width: 34px;
	border-radius: 12px;
	background: linear-gradient(145deg, #34D399, #06C755);
	display: flex;
	align-items: center;
	justify-content: center;
	color: #fff;
	font-weight: 700;
	font-size: 0.7rem;
	flex-shrink: 0;
}
.message-content { max-width: 70%; }
.message-header {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 4px;
}
.sender-name {
	font-weight: 700;
	font-size: 0.8rem;
	color: rgba(0, 0, 0, 0.55);
}
.message-time {
	font-size: 0.7rem;
	color: rgba(0, 0, 0, 0.45);
}
.message-bubble {
	background: #fff;
	color: #111;
	padding: 10px 14px;
	border-radius: 18px;
	border-bottom-left-radius: 4px;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
	word-wrap: break-word;
	font-size: 0.95rem;
	line-height: 1.45;
}
.message-item.own .message-bubble {
	background: #92E3A9;
	color: #111;
	border-bottom-left-radius: 18px;
	border-bottom-right-radius: 4px;
}
.chat-input-area {
	padding: 10px 12px;
	background: #F7F7F7;
	border-top: 1px solid var(--line-border, #E5E5E5);
}
.input-container {
	display: flex;
	align-items: center;
	gap: 8px;
	background: #fff;
	border: 1px solid var(--line-border, #E5E5E5);
	border-radius: 24px;
	padding: 6px 8px 6px 14px;
}
.input-container:focus-within {
	border-color: var(--line-green, #06C755);
	box-shadow: 0 0 0 2px rgba(6, 199, 85, 0.15);
}
.message-input {
	flex: 1;
	border: none;
	background: transparent;
	padding: 8px 4px;
	font-size: 0.95rem;
	outline: none;
	font-family: inherit;
	color: var(--line-text, #111);
}
.input-actions { display: flex; gap: 4px; align-items: center; }
.action-btn {
	width: 36px;
	height: 36px;
	border: none;
	border-radius: 50%;
	background: transparent;
	color: #888;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	cursor: pointer;
}
.action-btn:hover {
	background: var(--line-green-soft, #E8F8EF);
	color: var(--line-green, #06C755);
}
.send-btn {
	background: var(--line-green, #06C755);
	color: #fff;
}
.send-btn:hover {
	background: var(--line-green-dark, #05B34C);
	color: #fff;
}
.typing-indicator {
	padding: 6px 16px;
	font-size: 0.82rem;
	color: rgba(0, 0, 0, 0.55);
	font-style: italic;
}
.typing-dots { display: inline-block; animation: typing 1.5s infinite; }
@keyframes typing {
	0%, 60%, 100% { opacity: 0; }
	30% { opacity: 1; }
}
@media (max-width: 768px) {
	.group-content { flex-direction: column; }
	.group-sidebar {
		width: 100%;
		max-height: 140px;
		border-right: none;
		border-bottom: 1px solid var(--line-border, #E5E5E5);
	}
	.members-list {
		display: flex;
		gap: 8px;
		overflow-x: auto;
	}
	.member-item {
		flex-direction: column;
		text-align: center;
		min-width: 72px;
	}
	.online-indicator { display: none; }
}
</style>

<div class="group-chat-container">
	<div class="group-chat-main">
		<div class="group-header wechat-chat-header">
			<div class="group-info">
				<div class="group-avatar">
					<?php echo strtoupper(substr($group['name'], 0, 2)); ?>
				</div>
				<div class="group-details">
					<h3 class="wechat-chat-title"><?php echo htmlspecialchars($group['name']); ?></h3>
					<p><?php echo count($members); ?> members<?php if (!empty($group['description'])): ?> · <?php echo htmlspecialchars($group['description']); ?><?php endif; ?></p>
				</div>
			</div>
			<div class="group-actions chat-controls">
				<a href="groups.php" class="group-btn" title="Back">
					<i class="fas fa-arrow-left"></i> Back
				</a>
				<a href="group_manage.php?group_id=<?php echo (int)$groupId; ?>" class="group-btn" title="Manage">
					<i class="fas fa-cog"></i> Manage
				</a>
			</div>
		</div>

		<div class="group-content">
			<div class="group-sidebar">
				<h6><i class="fas fa-users"></i> Members (<?php echo count($members); ?>)</h6>
				<div class="members-list">
					<?php foreach ($members as $member): ?>
					<div class="member-item">
						<div class="member-avatar">
							<?php echo strtoupper(substr($member['username'], 0, 2)); ?>
						</div>
						<div class="member-name">
							<?php echo htmlspecialchars($member['username']); ?>
							<?php if ($member['id'] == $userId): ?>
								<small>(You)</small>
							<?php endif; ?>
						</div>
						<div class="online-indicator"></div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="chat-area">
				<div id="messages" class="messages-container"></div>

				<div id="typingIndicator" class="typing-indicator" style="display: none;">
					Someone is typing<span class="typing-dots">...</span>
				</div>

				<div class="chat-input-area">
					<form id="sendForm" onsubmit="return false;">
						<div class="input-container">
							<input type="text" id="msg" class="message-input" placeholder="Type a message" required>
							<div class="input-actions">
								<button type="button" class="action-btn" title="Attach File">
									<i class="fas fa-paperclip"></i>
								</button>
								<button type="button" class="action-btn" title="Voice Message">
									<i class="fas fa-microphone"></i>
								</button>
								<button type="button" id="sendButton" class="action-btn send-btn" title="Send Message">
									<i class="fas fa-paper-plane"></i>
								</button>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
let lastId = 0;
const groupId = <?php echo (int)$groupId; ?>;
const currentUserId = <?php echo (int)$userId; ?>;

async function fetchMessages() {
	try {
		const res = await fetch('group_chat_api.php?action=fetch&group_id=' + groupId + '&after_id=' + lastId, { credentials: 'same-origin' });
		const data = await res.json();
		if (Array.isArray(data.messages) && data.messages.length > 0) {
			const container = document.getElementById('messages');
			let hasNewMessages = false;
			
			for (const m of data.messages) {
				// Check if message already exists to avoid duplicates
				const existingMessage = container.querySelector(`[data-message-id="${m.id}"]`);
				if (!existingMessage) {
					const messageDiv = createMessageElement(m);
					container.appendChild(messageDiv);
					lastId = Math.max(lastId, parseInt(m.id));
					hasNewMessages = true;
				}
			}
			
			// Only scroll if there are new messages
			if (hasNewMessages) {
				container.scrollTop = container.scrollHeight;
			}
		}
	} catch (e) {
		console.error('Error fetching messages:', e);
	}
}

function createMessageElement(message) {
	const div = document.createElement('div');
	div.className = `message-item ${message.sender_id == currentUserId ? 'own' : ''}`;
	div.setAttribute('data-message-id', message.id);
	
	const isOwn = message.sender_id == currentUserId;
	const avatarText = message.sender ? message.sender.substring(0, 2).toUpperCase() : 'UN';
	
	div.innerHTML = `
		<div class="message-avatar">${avatarText}</div>
		<div class="message-content">
			${!isOwn ? `
			<div class="message-header">
				<span class="sender-name">${escapeHtml(message.sender || 'Unknown')}</span>
				<span class="message-time">${formatTime(message.time)}</span>
			</div>
			` : ''}
			<div class="message-bubble">
				${escapeHtml(message.text)}
				${isOwn ? `<div class="message-time" style="margin-top: 5px; font-size: 0.75em; opacity: 0.7;">${formatTime(message.time)}</div>` : ''}
			</div>
		</div>
	`;
	
	return div;
}

function formatTime(timeString) {
	const date = new Date(timeString);
	const now = new Date();
	const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
	const messageDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
	
	if (messageDate.getTime() === today.getTime()) {
		// Today - show time only
		return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
	} else {
		// Other day - show date and time
		return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ' ' +
			   date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
	}
}

function escapeHtml(text) {
	const p = document.createElement('p');
	p.appendChild(document.createTextNode(text));
	return p.innerHTML;
}

async function sendMessage() {
	const input = document.getElementById('msg');
	const text = input.value.trim();
	if (!text) return false;
	
	// Clear input immediately for better UX
	input.value = '';
	
	// Add sending animation to button
	const sendButton = document.getElementById('sendButton');
	const originalHTML = sendButton.innerHTML;
	sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
	sendButton.disabled = true;
	
	try {
		const response = await fetch('group_chat_api.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			credentials: 'same-origin',
			body: new URLSearchParams({ action: 'send', group_id: String(groupId), text })
		});
		
		const result = await response.json();
		if (result.ok) {
			// Fetch new messages after successful send
			await fetchMessages();
		} else {
			// Restore input if sending failed
			input.value = text;
			alert('Failed to send message. Please try again.');
		}
	} catch (e) {
		console.error('Error sending message:', e);
		// Restore input if sending failed
		input.value = text;
		alert('Failed to send message. Please try again.');
	} finally {
		// Restore button
		sendButton.innerHTML = originalHTML;
		sendButton.disabled = false;
		input.focus();
	}
	return false;
}

// Initialize chat
document.addEventListener('DOMContentLoaded', function() {
	// Initial load
	fetchMessages();
	
	// Set up polling with better error handling
	let pollInterval = setInterval(fetchMessages, 2000);
	
	// Pause polling when user is typing
	const messageInput = document.getElementById('msg');
	let typingTimer;
	
	messageInput.addEventListener('input', function() {
		clearInterval(pollInterval);
		clearTimeout(typingTimer);
		
		typingTimer = setTimeout(() => {
			pollInterval = setInterval(fetchMessages, 2000);
		}, 3000); // Resume polling 3 seconds after user stops typing
	});
	
	// Handle form submission
	document.getElementById('sendForm').addEventListener('submit', function(e) {
		e.preventDefault();
		e.stopPropagation();
		sendMessage();
		clearTimeout(typingTimer);
		clearInterval(pollInterval);
		pollInterval = setInterval(fetchMessages, 2000);
		return false;
	});
	
	// Handle send button click
	document.getElementById('sendButton').addEventListener('click', function(e) {
		e.preventDefault();
		e.stopPropagation();
		sendMessage();
		clearTimeout(typingTimer);
		clearInterval(pollInterval);
		pollInterval = setInterval(fetchMessages, 2000);
		return false;
	});
	
	// Handle Enter key press
	messageInput.addEventListener('keypress', function(e) {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			e.stopPropagation();
			sendMessage();
			clearTimeout(typingTimer);
			clearInterval(pollInterval);
			pollInterval = setInterval(fetchMessages, 2000);
			return false;
		}
	});
	
	// Auto-focus message input
	messageInput.focus();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
