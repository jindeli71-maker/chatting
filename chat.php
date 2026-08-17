<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_login();

$pdo = get_pdo();
$userId = current_user_id();
$peerId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Ensure peer is a friend (or allow open if not chosen yet)
$isFriend = false;
if ($peerId) {
	$stmt = $pdo->prepare('SELECT 1 FROM friendships WHERE user_id = ? AND friend_id = ?');
	$stmt->execute([$userId, $peerId]);
	$isFriend = (bool)$stmt->fetch();
	if (!$isFriend) { $peerId = 0; }
}

include __DIR__ . '/includes/header.php';
?>
<style>
/* Minimal page helpers — visual design lives in assets/css/line-style.css */
@keyframes slideIn {
	from { transform: translateX(100%); opacity: 0; }
	to { transform: translateX(0); opacity: 1; }
}
@keyframes slideOut {
	from { transform: translateX(0); opacity: 1; }
	to { transform: translateX(100%); opacity: 0; }
}
</style>

<div class="chat-container">
	<div class="chat-layout<?php echo $peerId ? ' has-peer' : ''; ?>">
		<div class="wechat-chat-list">
			<div class="wechat-search line-list-header">
				<h1 class="line-page-title">Chats</h1>
				<input type="text" placeholder="Search" class="form-control line-search-input" id="friendSearch">
				<div class="friends-count">
					<i class="fas fa-user-friends"></i>
					<span id="friendsCount">0</span> friends
				</div>
			</div>
			<?php
			// Get friends (including blocked ones, but marked)
			$friendsStmt = $pdo->prepare('
				SELECT u.id, u.username,
					   CASE WHEN ub.blocked_id IS NOT NULL THEN 1 ELSE 0 END as is_blocked
			FROM friendships f 
			JOIN users u ON u.id = f.friend_id 
			LEFT JOIN user_blocks ub ON ub.blocker_id = ? AND ub.blocked_id = u.id
			WHERE f.user_id = ? 
			ORDER BY u.username
			');
			$friendsStmt->execute([$userId, $userId]);
			$friends = $friendsStmt->fetchAll();
			?>
			<div id="friendsList">
			<?php foreach ($friends as $f): ?>
			<div class="wechat-chat-item <?php echo ($peerId === (int)$f['id']) ? 'active' : ''; ?> <?php echo $f['is_blocked'] ? 'blocked-user' : ''; ?>" 
				 onclick="location.href='chat.php?user_id=<?php echo (int)$f['id']; ?>'">
				<div class="wechat-avatar">
					<?php echo strtoupper(substr($f['username'], 0, 1)); ?>
					<?php if ($f['is_blocked']): ?>
						<span class="blocked-indicator" title="This user is blocked">
							<i class="fas fa-ban"></i>
						</span>
					<?php endif; ?>
				</div>
				<div class="wechat-chat-content">
					<div class="wechat-chat-name">
						<?php echo htmlspecialchars($f['username']); ?>
						<?php if ($f['is_blocked']): ?>
							<span class="blocked-badge">Blocked</span>
						<?php endif; ?>
					</div>
					<div class="wechat-chat-preview">
						Tap to open chat
					</div>
				</div>
			</div>
			<?php endforeach; ?>
			</div>
		</div>
		<div class="wechat-chat-area">
			<?php if (!$peerId): ?>
			<div class="wechat-empty">
				<i class="fas fa-comments"></i>
				<h3>Keep the conversation going</h3>
				<p>Select a chat from the list to start messaging</p>
			</div>
			<?php else: ?>
			<?php $peerUsername = ''; ?>
			<div class="chat-main-container">
		<div class="chat-messages-container">
			<div class="wechat-chat-header">
				<div class="d-flex justify-content-between align-items-center">
					<div class="d-flex align-items-center gap-2">
						<a href="chat.php" class="btn btn-sm btn-outline-secondary d-md-none" title="Back" style="border-radius:10px;">
							<i class="fas fa-chevron-left"></i>
						</a>
						<div class="wechat-chat-title">
						<?php
$u = $pdo->prepare('SELECT username FROM users WHERE id = ?');
$u->execute([$peerId]);
$peerUsername = $u->fetchColumn() ?: 'Unknown';
echo htmlspecialchars($peerUsername);

// Check if current user has blocked the peer (for UI display)
$blockCheck = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
$blockCheck->execute([$userId, $peerId]);
$isBlocked = (bool)$blockCheck->fetch();

// Also check if peer has blocked current user (for message sending logic)
$blockedByPeerCheck = $pdo->prepare('SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?');
$blockedByPeerCheck->execute([$peerId, $userId]);
$isBlockedByPeer = (bool)$blockedByPeerCheck->fetch();
						?>
					</div>
					</div>
					<div class="chat-controls">
						<button class="btn btn-sm btn-outline-success me-2" id="voiceCall" title="Voice Call" type="button" onclick="if(window.startLineCall){window.startLineCall();return false;}">
							<i class="fas fa-phone"></i>
						</button>
						<button class="btn btn-sm btn-outline-secondary me-2" id="toggleNotifications" title="Toggle Notifications">
							<i class="fas fa-bell" id="notificationIcon"></i>
						</button>
						<button class="btn btn-sm btn-outline-secondary me-2" id="toggleChatSettings" title="Chat Settings">
							<i class="fas fa-cog" id="settingsIcon"></i>
						</button>
						<button class="btn btn-sm btn-outline-secondary" id="toggleChatBox" title="Minimize Chat">
							<i class="fas fa-minus" id="toggleIcon"></i>
						</button>
					</div>
				</div>
			</div>
			<div id="chatContent" class="chat-content">
				<div id="messages" class="wechat-messages"></div>
				<div class="wechat-input-area">
					<!-- Voice Recording Interface -->
					<div id="voiceRecording" class="voice-recording-interface" style="display: none;">
						<div class="voice-recording-content">
							<div class="voice-timer">00:00</div>
							<div class="voice-waveform">
								<div class="waveform-bars">
									<div class="bar"></div><div class="bar"></div><div class="bar"></div><div class="bar"></div><div class="bar"></div>
									<div class="bar"></div><div class="bar"></div><div class="bar"></div><div class="bar"></div><div class="bar"></div>
								</div>
							</div>
							<div class="voice-actions">
								<button id="cancelVoice" class="voice-btn cancel"><i class="fas fa-times"></i></button>
								<button id="sendVoice" class="voice-btn send"><i class="fas fa-paper-plane"></i></button>
							</div>
						</div>
					</div>
					
					<!-- Reply Preview -->
					<div id="replyPreview" class="reply-preview" style="display: none;">
						<div class="reply-content">
							<div class="reply-header">
								<i class="fas fa-reply"></i>
								<span>Replying to <strong id="replyToUser"></strong></span>
								<button id="cancelReply" class="reply-cancel"><i class="fas fa-times"></i></button>
							</div>
							<div class="reply-text" id="replyToText"></div>
						</div>
					</div>
					
					<!-- Main Input Form -->
					<form id="sendForm" class="wechat-input-group line-composer">
						<input type="hidden" id="replyToMessageId" value="">
						<button type="button" id="attachFile" class="input-action-btn" title="Attach">
							<i class="fas fa-plus"></i>
						</button>
						<button type="button" id="attachImage" class="input-action-btn" title="Emoji / Image">
							<i class="far fa-smile"></i>
						</button>
						<input type="text" id="msg" class="wechat-input" placeholder="Type a message" autocomplete="off">
						<button type="button" id="recordVoice" class="input-action-btn voice-record-btn" title="Voice message">
							<i class="fas fa-microphone"></i>
						</button>
						<button type="submit" id="sendBtn" class="wechat-send-btn" title="Send"><i class="fas fa-paper-plane"></i></button>
					</form>
					
					<!-- File Upload Hidden Inputs -->
					<input type="file" id="fileInput" style="display: none;" multiple accept="*/*">
					<input type="file" id="imageInput" style="display: none;" accept="image/*" multiple>
				</div>
			</div>
		</div>
		
		<!-- Chat Sidebar -->
		<div id="chatSidebar" class="chat-sidebar">
			<div class="sidebar-header">
				<h5>Chat Info</h5>
				<button class="btn btn-sm btn-outline-secondary" id="closeSidebar" title="Close Sidebar">
					<i class="fas fa-times"></i>
				</button>
			</div>
			
			<div class="sidebar-content">
				<!-- User Profile Section -->
				<div class="sidebar-section">
					<div class="user-profile">
						<div class="profile-avatar">
							<?php echo strtoupper(substr($u->fetchColumn() ?: 'U', 0, 1)); ?>
						</div>
						<div class="profile-info">
							<h6><?php echo htmlspecialchars($u->fetchColumn() ?: 'Unknown'); ?></h6>
							<p class="text-muted">Online</p>
						</div>
					</div>
				</div>
				
				<!-- Chat Settings -->
				<div class="sidebar-section">
					<h6>Chat Settings</h6>
					<div class="setting-item">
						<div class="d-flex justify-content-between align-items-center">
							<span>Notifications</span>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" id="sidebarNotifications" checked>
							</div>
						</div>
					</div>
					<div class="setting-item">
						<div class="d-flex justify-content-between align-items-center">
							<span>Auto-scroll</span>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" id="autoScroll" checked>
							</div>
						</div>
					</div>
					<div class="setting-item">
						<div class="d-flex justify-content-between align-items-center">
							<span>Show Timestamps</span>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" id="showTimestamps" checked>
							</div>
						</div>
					</div>
				</div>
				
				<!-- Chat Actions -->
				<div class="sidebar-section">
					<h6>Actions</h6>
					<div class="action-buttons">
						<button class="btn btn-outline-primary btn-sm w-100 mb-2" id="clearChat">
							<i class="fas fa-trash"></i> Clear Chat
						</button>
						<button class="btn btn-outline-info btn-sm w-100 mb-2" id="exportChat">
							<i class="fas fa-download"></i> Export Chat
						</button>
					</div>
				</div>
				
				<!-- Block User Toggle -->
				<div class="sidebar-section">
					<h6>User Control</h6>
					<div class="setting-item">
						<div class="d-flex justify-content-between align-items-center">
							<span id="sidebarBlockLabel">Block User</span>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" id="sidebarBlockToggle" data-user-id="<?php echo (int)$peerId; ?>">
							</div>
						</div>
						<small class="text-muted" id="sidebarBlockStatus">User is not blocked</small>
					</div>
				</div>
				
				<!-- Chat Statistics -->
				<div class="sidebar-section">
					<h6>Statistics</h6>
					<div class="stat-item">
						<span>Messages sent:</span>
						<span id="messagesSent">0</span>
					</div>
					<div class="stat-item">
						<span>Messages received:</span>
						<span id="messagesReceived">0</span>
					</div>
					<div class="stat-item">
						<span>Chat duration:</span>
						<span id="chatDuration">0 min</span>
					</div>
				</div>
				
				<!-- Quick Actions -->
				<div class="sidebar-section">
					<h6>Quick Actions</h6>
					<div class="quick-actions">
						<button class="btn btn-outline-secondary btn-sm w-100 mb-1" id="sendFile">
							<i class="fas fa-paperclip"></i> Send File
						</button>
						<button class="btn btn-outline-secondary btn-sm w-100 mb-1" id="sendImage">
							<i class="fas fa-image"></i> Send Image
						</button>
						<button class="btn btn-outline-secondary btn-sm w-100 mb-1" id="voiceMessage">
							<i class="fas fa-microphone"></i> Voice Message
						</button>
						<button class="btn btn-outline-warning btn-sm w-100 mb-1" id="testContextMenu">
							<i class="fas fa-bug"></i> Test Context Menu
						</button>
					</div>
				</div>
			</div>
		</div>
		
		<!-- Chat Settings Panel -->
		<div id="chatSettingsPanel" class="chat-settings-panel" style="display: none;">
			<div class="settings-header">
				<h5><i class="fas fa-cog"></i> Chat Settings</h5>
				<button class="btn btn-sm btn-outline-secondary" id="closeSettings" title="Close Settings">
					<i class="fas fa-times"></i>
				</button>
			</div>
			
			<div class="settings-content">
				<!-- User Profile Section -->
				<div class="settings-section">
					<div class="user-profile">
						<div class="profile-avatar">
							<?php echo strtoupper(substr($peerUsername, 0, 1)); ?>
						</div>
						<div class="profile-info">
							<div class="profile-name"><?php echo htmlspecialchars($peerUsername); ?></div>
							<div class="profile-status">Online</div>
						</div>
					</div>
				</div>
				
				<!-- Chat Settings -->
				<div class="settings-section">
					<h6>Chat Settings</h6>
					<div class="setting-item">
						<div class="d-flex justify-content-between align-items-center">
							<span>Notifications</span>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" id="settingsNotifications" checked>
							</div>
						</div>
					</div>
					<div class="setting-item">
						<div class="d-flex justify-content-between align-items-center">
							<span>Auto-scroll</span>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" id="settingsAutoScroll" checked>
							</div>
						</div>
					</div>
					<div class="setting-item">
						<div class="d-flex justify-content-between align-items-center">
							<span>Show Timestamps</span>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" id="settingsTimestamps" checked>
							</div>
						</div>
					</div>
				</div>
				
				<!-- Actions -->
				<div class="settings-section">
					<h6>Actions</h6>
					<div class="setting-item">
						<button class="btn btn-outline-primary btn-sm w-100 mb-2" id="settingsClearChat">
							<i class="fas fa-trash me-2"></i> Clear Chat
						</button>
						<button class="btn btn-outline-info btn-sm w-100" id="settingsExportChat">
							<i class="fas fa-download me-2"></i> Export Chat
						</button>
					</div>
				</div>
				
				<!-- User Control -->
				<div class="settings-section">
					<h6>User Control</h6>
					<div class="setting-item">
						<div class="d-flex justify-content-between align-items-center">
							<span id="settingsBlockLabel">Block User</span>
							<div class="form-check form-switch">
								<input class="form-check-input" type="checkbox" id="settingsBlockToggle" data-user-id="<?php echo (int)$peerId; ?>">
							</div>
						</div>
						<small class="text-muted" id="settingsBlockStatus">User is not blocked</small>
					</div>
				</div>
				
				<!-- Statistics -->
				<div class="settings-section">
					<h6>Statistics</h6>
					<div class="stat-item">
						<span>Messages sent:</span>
						<span id="settingsMessagesSent" class="text-success">0</span>
					</div>
					<div class="stat-item">
						<span>Messages received:</span>
						<span id="settingsMessagesReceived" class="text-success">0</span>
					</div>
					<div class="stat-item">
						<span>Chat duration:</span>
						<span id="settingsChatDuration" class="text-success">0 min</span>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Forward Message Modal -->
	<div id="forwardModal" class="modal fade" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Forward Message</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="forward-message-preview mb-3">
						<div class="fw-bold">Message to forward:</div>
						<div id="forwardMessageText" class="border rounded p-2 bg-light"></div>
					</div>
					<div class="mb-3">
						<label class="form-label">Select recipients:</label>
						<div id="forwardContacts" class="forward-contacts-list">
							<!-- Contacts will be loaded here -->
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" id="confirmForward" class="btn btn-primary">Forward</button>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Edit Message Modal -->
	<div id="editModal" class="modal fade" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Edit Message</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label">Edit your message:</label>
						<textarea id="editMessageText" class="form-control" rows="3"></textarea>
						<small class="text-muted">You can edit messages within 2 minutes of sending.</small>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="button" id="confirmEdit" class="btn btn-primary">Update</button>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Voice Call Modal -->
	<div id="voiceCallModal" class="modal fade" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content" style="border-radius:16px;overflow:hidden;">
				<div class="modal-header border-0" style="background:#06C755;color:#fff;">
					<h5 class="modal-title">Voice Call</h5>
				</div>
				<div class="modal-body text-center py-4">
					<div class="caller-avatar mx-auto mb-3" style="width:88px;height:88px;border-radius:24px;background:linear-gradient(145deg,#34D399,#06C755);color:#fff;display:flex;align-items:center;justify-content:center;font-size:2rem;">
						<i class="fas fa-user"></i>
					</div>
					<h4 id="callerName" class="mb-1">Contact</h4>
					<p id="callStatus" class="text-muted mb-4">Calling...</p>
					<audio id="remoteAudio" autoplay playsinline style="display:none;"></audio>
					<div id="outgoingControls" class="call-controls d-flex justify-content-center gap-3">
						<button type="button" id="muteCall" class="btn btn-light rounded-circle" style="width:56px;height:56px;" title="Mute">
							<i class="fas fa-microphone"></i>
						</button>
						<button type="button" id="endCall" class="btn btn-danger rounded-circle" style="width:56px;height:56px;" title="End Call">
							<i class="fas fa-phone-slash"></i>
						</button>
					</div>
					<div id="incomingControls" class="call-controls d-flex justify-content-center gap-3" style="display:none !important;">
						<button type="button" id="declineCall" class="btn btn-danger rounded-circle" style="width:56px;height:56px;" title="Decline">
							<i class="fas fa-phone-slash"></i>
						</button>
						<button type="button" id="acceptCall" class="btn btn-success rounded-circle" style="width:56px;height:56px;background:#06C755;border-color:#06C755;" title="Accept">
							<i class="fas fa-phone"></i>
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Context Menu -->
	<div id="messageContextMenu" class="context-menu" style="display: none;">
		<div class="context-menu-reactions">
			<div class="reaction-emoji" data-reaction="❤️">❤️</div>
			<div class="reaction-emoji" data-reaction="😂">😂</div>
			<div class="reaction-emoji" data-reaction="😮">😮</div>
			<div class="reaction-emoji" data-reaction="😢">😢</div>
			<div class="reaction-emoji" data-reaction="🎉">🎉</div>
			<div class="reaction-emoji" data-reaction="+">+</div>
		</div>
		<div class="context-menu-options">
			<div class="context-menu-item" data-action="reply">
				<i class="fas fa-reply"></i>
				<span>回复</span>
			</div>
			<div class="context-menu-item" data-action="copy">
				<i class="fas fa-copy"></i>
				<span>复制</span>
			</div>
			<div class="context-menu-item" data-action="forward">
				<i class="fas fa-share"></i>
				<span>转发</span>
			</div>
			<div class="context-menu-item" data-action="edit" data-time-sensitive="true">
				<i class="fas fa-edit"></i>
				<span>编辑</span>
			</div>
			<div class="context-menu-item" data-action="favorite">
				<i class="fas fa-star"></i>
				<span>收藏</span>
			</div>
			<div class="context-menu-item" data-action="pin">
				<i class="fas fa-thumbtack"></i>
				<span>置顶</span>
			</div>
			<div class="context-menu-item" data-action="delete" data-time-sensitive="true">
				<i class="fas fa-trash"></i>
				<span>删除</span>
			</div>
			<div class="context-menu-item" data-action="select">
				<i class="fas fa-check"></i>
				<span>选择</span>
			</div>
		</div>
	</div>
	<script>
	let lastId = 0;
	const peerId = <?php echo (int)$peerId; ?>;
	let notificationsEnabled = true;
	let chatMinimized = false;
	let unreadCount = 0;
	let sidebarOpen = false;
	let settingsOpen = false;
	let messagesSent = 0;
	let messagesReceived = 0;
	let chatStartTime = Date.now();
	let isBlocked = <?php echo $isBlocked ? 'true' : 'false'; ?>; // Current user blocked peer
	let isBlockedByPeer = <?php echo $isBlockedByPeer ? 'true' : 'false'; ?>; // Peer blocked current user

	async function fetchMessages() {
		try {
			console.log('🔄 Fetching messages after ID:', lastId);
			const res = await fetch('chat_api.php?action=fetch&peer_id=' + peerId + '&after_id=' + lastId, { credentials: 'same-origin' });
			const data = await res.json();
			console.log('📬 Received data:', data);
			
			if (Array.isArray(data.messages) && data.messages.length > 0) {
				const container = document.getElementById('messages');
				let hasNewMessages = false;
				
				for (const m of data.messages) {
					// Check if message already exists to avoid duplicates
					const existingMessage = container.querySelector(`[data-message-id="${m.id}"]`);
					if (!existingMessage) {
						console.log('🆕 Adding new message:', m.id, String(m.text || '').substring(0, 20) + '...');
						
						const messageDiv = document.createElement('div');
						messageDiv.className = 'wechat-message ' + (m.sender === 'me' ? 'sent' : 'received');
						messageDiv.setAttribute('data-message-id', m.id);
						messageDiv.setAttribute('data-message-text', m.text || '');
						messageDiv.setAttribute('data-sender', m.sender);
						
						// Add context menu event listener
						messageDiv.addEventListener('contextmenu', function(e) {
							e.preventDefault();
							e.stopPropagation();
							console.log('Context menu triggered for message:', m.id);
							showContextMenu(e, m.id, m.text, m.sender);
							return false;
						});
						
						// Also add double-click as fallback for mobile
						messageDiv.addEventListener('dblclick', function(e) {
							e.preventDefault();
							console.log('Double-click triggered for message:', m.id);
							showContextMenu(e, m.id, m.text, m.sender);
						});
						
						const bubble = document.createElement('div');
						bubble.className = 'wechat-message-bubble';
						bubble.textContent = m.text;
						
						const time = document.createElement('div');
						time.className = 'wechat-message-time';
						time.textContent = m.time;
						
						messageDiv.appendChild(bubble);
						messageDiv.appendChild(time);
						container.appendChild(messageDiv);
						lastId = Math.max(lastId, parseInt(m.id));
						hasNewMessages = true;
						
						// Update statistics
						if (m.sender === 'them') {
							messagesReceived++;
						}
					} else {
						console.log('ℹ️ Message already exists:', m.id);
					}
				}
				
				// Only scroll if there are new messages
				if (hasNewMessages) {
					console.log('✅ New messages added, scrolling to bottom');
					const autoScroll = document.getElementById('autoScroll')?.checked;
					if (autoScroll !== false) {
						container.scrollTop = container.scrollHeight;
					}
					
					// Update statistics
					updateMessageStats();
					
					// Show notification if chat is minimized or notifications enabled
					if (chatMinimized || notificationsEnabled) {
						showNotification();
						updateUnreadCount();
					}
				} else {
					console.log('ℹ️ No new messages to display');
				}
			} else {
				console.log('ℹ️ No messages in response or empty array');
			}
		} catch (e) {
			console.error('❌ Error fetching messages:', e);
		}
	}

	async function sendChatMessage() {
		console.log('🚀 Starting sendChatMessage');
		console.log('🔍 Peer ID:', peerId);
		
		const input = document.getElementById('msg');
		const form = document.getElementById('sendForm');
		const text = input.value.trim();
		
		console.log('📝 Message text:', text);
		
		if (!text) {
			console.log('❌ Empty message, aborting');
			return false;
		}
		
		if (!peerId || peerId === 0) {
			console.log('❌ No peer ID, aborting');
			showMessage('Please select a friend to chat with', 'error');
			return false;
		}
		
		// Clear input immediately for better UX
		input.value = '';
		if (form) form.classList.remove('has-text');
		
		try {
			console.log('📡 Sending API request to chat_api.php');
			
			const response = await fetch('chat_api.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				credentials: 'same-origin',
				body: new URLSearchParams({ action: 'send', peer_id: String(peerId), text })
			});
			
			console.log('📡 Response status:', response.status);
			
			const responseText = await response.text();
			console.log('📡 Raw response:', responseText);
			
			let result;
			try {
				result = JSON.parse(responseText);
			} catch (parseError) {
				console.error('❌ JSON parse error:', parseError);
				console.error('❌ Response text:', responseText);
				throw new Error('Invalid JSON response from server');
			}
			
			console.log('✅ Parsed result:', result);
			
			if (result.ok) {
				if (result.blocked || isBlockedByPeer) {
					console.log('⚠️ Message was blocked, showing blocked UI');
					addBlockedMessageToChat(text);
					showBlockedMessage(result.message || '消息已发出，但被对方拒收了。');
				} else {
					console.log('✅ Message sent successfully, adding to chat immediately');
					addMessageToChat(text, 'me', new Date().toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'}), result.message_id);
					setTimeout(() => fetchMessages(), 100);
					messagesSent++;
				}
			} else {
				console.error('❌ Server returned error:', result.error);
				showMessage('Failed to send: ' + (result.error || 'Unknown error'), 'error');
				input.value = text;
				if (form) form.classList.add('has-text');
			}
		} catch (e) {
			console.error('❌ Error sending message:', e);
			showMessage('Error sending message: ' + e.message, 'error');
			input.value = text;
			if (form) form.classList.add('has-text');
		}
		return false;
	}
	// Keep old name as alias in case other scripts call it
	window.sendChatMessage = sendChatMessage;
	window.sendMessage = sendChatMessage;
	
	// Show user feedback messages
	function showMessage(message, type = 'info') {
		console.log(`📢 ${type.toUpperCase()}: ${message}`);
		
		// Remove any existing message
		const existingMsg = document.querySelector('.user-feedback-message');
		if (existingMsg) {
			existingMsg.remove();
		}
		
		// Create new message element
		const messageDiv = document.createElement('div');
		messageDiv.className = `user-feedback-message alert-${type}`;
		messageDiv.style.cssText = `
			position: fixed;
			top: 20px;
			right: 20px;
			background: ${type === 'error' ? '#ff6b6b' : type === 'success' ? '#51cf66' : '#339af0'};
			color: white;
			padding: 15px 25px;
			border-radius: 8px;
			box-shadow: 0 4px 15px rgba(0,0,0,0.2);
			z-index: 10000;
			font-weight: 500;
			max-width: 400px;
			word-wrap: break-word;
			animation: slideIn 0.3s ease;
		`;
		messageDiv.innerHTML = `
			<i class="fas fa-${type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle'}"></i>
			${message}
			<button onclick="this.parentElement.remove()" style="
				background: none;
				border: none;
				color: white;
				float: right;
				margin-left: 10px;
				cursor: pointer;
				font-size: 16px;
			">&times;</button>
		`;
		
		document.body.appendChild(messageDiv);
		
		// Auto remove after 5 seconds
		setTimeout(() => {
			if (messageDiv.parentNode) {
				messageDiv.style.animation = 'slideOut 0.3s ease';
				setTimeout(() => messageDiv.remove(), 300);
			}
		}, 5000);
	}
	function addBlockedMessageToChat(text) {
		const messagesContainer = document.getElementById('messages');
		const messageDiv = document.createElement('div');
		messageDiv.className = 'wechat-message sent blocked-message-sent';
		messageDiv.innerHTML = `
			<div class="wechat-message-bubble-container">
				<div class="blocked-error-indicator">
					<i class="fas fa-exclamation-circle"></i>
				</div>
				<div class="wechat-message-bubble">${text}</div>
			</div>
			<div class="wechat-message-time">${new Date().toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'})}</div>
		`;
		
		messagesContainer.appendChild(messageDiv);
		messagesContainer.scrollTop = messagesContainer.scrollHeight;
	}
	
	// Show blocked message
	function showBlockedMessage(message) {
		const messageDiv = document.createElement('div');
		messageDiv.className = 'wechat-message blocked-message';
		messageDiv.innerHTML = `
			<div class="wechat-message-content">
				<div class="blocked-indicator">
					<i class="fas fa-exclamation-circle"></i>
				</div>
				<div class="blocked-text">${message}</div>
			</div>
		`;
		
		// Add to messages container
		const messagesContainer = document.getElementById('messages');
		messagesContainer.appendChild(messageDiv);
		
		// Scroll to bottom
		messagesContainer.scrollTop = messagesContainer.scrollHeight;
		
		// Auto remove after 5 seconds
		setTimeout(() => {
			if (messageDiv.parentNode) {
				messageDiv.remove();
			}
		}, 5000);
	}

	// Add message to chat immediately for instant feedback
	function addMessageToChat(text, sender, time, messageId) {
		console.log('💬 Adding message to chat:', { text, sender, time, messageId });
		
		const container = document.getElementById('messages');
		if (!container) {
			console.error('❌ Messages container not found');
			return;
		}
		
		// Check if message already exists to avoid duplicates
		if (messageId && container.querySelector(`[data-message-id="${messageId}"]`)) {
			console.log('ℹ️ Message already exists, skipping');
			return;
		}
		
		const messageDiv = document.createElement('div');
		messageDiv.className = 'wechat-message ' + (sender === 'me' ? 'sent' : 'received');
		if (messageId) {
			messageDiv.setAttribute('data-message-id', messageId);
		}
		messageDiv.setAttribute('data-message-text', text);
		messageDiv.setAttribute('data-sender', sender);
		
		// Add context menu for sent messages
		if (sender === 'me') {
			messageDiv.addEventListener('contextmenu', function(e) {
				e.preventDefault();
				e.stopPropagation();
				showContextMenu(e, messageId, text, sender);
				return false;
			});
		}
		
		// Create message bubble with modern styling
		const bubbleContainer = document.createElement('div');
		bubbleContainer.className = 'wechat-message-bubble-container';
		
		const bubble = document.createElement('div');
		bubble.className = 'wechat-message-bubble';
		bubble.textContent = text;
		
		const timeElement = document.createElement('div');
		timeElement.className = 'wechat-message-time';
		timeElement.textContent = time;
		
		bubbleContainer.appendChild(bubble);
		messageDiv.appendChild(bubbleContainer);
		messageDiv.appendChild(timeElement);
		
		// Add with smooth animation
		messageDiv.style.opacity = '0';
		messageDiv.style.transform = 'translateY(20px)';
		container.appendChild(messageDiv);
		
		// Trigger animation
		setTimeout(() => {
			messageDiv.style.transition = 'all 0.3s ease';
			messageDiv.style.opacity = '1';
			messageDiv.style.transform = 'translateY(0)';
		}, 10);
		
		// Update last ID for polling
		if (messageId) {
			lastId = Math.max(lastId, parseInt(messageId));
		}
		
		// Scroll to bottom
		container.scrollTop = container.scrollHeight;
		
		console.log('✅ Message added to chat successfully');
	}

	// Initial load
	fetchMessages();
	
	// Set up polling with better error handling
	let pollInterval = setInterval(fetchMessages, 1500);
	
	// Pause polling when user is typing
	const messageInput = document.getElementById('msg');
	let typingTimer;
	
	messageInput.addEventListener('input', function() {
		clearInterval(pollInterval);
		clearTimeout(typingTimer);
		
		typingTimer = setTimeout(() => {
			pollInterval = setInterval(fetchMessages, 1500);
		}, 2000); // Resume polling 2 seconds after user stops typing
	});
	
	// Handle Enter key press
	messageInput.addEventListener('keypress', function(e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			sendChatMessage();
		}
	});
	
	// Handle form submission
	document.getElementById('sendForm').addEventListener('submit', function(e) {
		e.preventDefault(); // Prevent form submission and page reload
		sendChatMessage();
		clearTimeout(typingTimer);
		clearInterval(pollInterval);
		pollInterval = setInterval(fetchMessages, 1500);
	});

	// WhatsApp-style: mic when empty, send when typing
	(function setupComposerToggle() {
		const msgInput = document.getElementById('msg');
		const form = document.getElementById('sendForm');
		if (!msgInput || !form) return;
		const sync = () => form.classList.toggle('has-text', msgInput.value.trim().length > 0);
		msgInput.addEventListener('input', sync);
		sync();
	})();
	
	// Toggle chat box functionality
	document.getElementById('toggleChatBox').addEventListener('click', function() {
		const chatContent = document.getElementById('chatContent');
		const toggleIcon = document.getElementById('toggleIcon');
		
		if (chatMinimized) {
			// Expand chat
			chatContent.style.display = 'flex';
			toggleIcon.className = 'fas fa-minus';
			chatMinimized = false;
			unreadCount = 0;
			updateUnreadCount();
		} else {
			// Minimize chat
			chatContent.style.display = 'none';
			toggleIcon.className = 'fas fa-plus';
			chatMinimized = true;
		}
	});
	
	
	// Toggle chat settings functionality
	document.getElementById('toggleChatSettings').addEventListener('click', function() {
		toggleSettings();
	});
	
	// Close settings panel
	document.getElementById('closeSettings').addEventListener('click', function() {
		toggleSettings();
	});
	
	// Block/Unblock functionality in settings
	document.getElementById('settingsBlockToggle').addEventListener('change', function() {
		toggleBlockUser();
	});
	
	// Block/Unblock functionality in sidebar
	document.getElementById('sidebarBlockToggle').addEventListener('change', function() {
		toggleBlockUser();
	});
	
	// Settings panel functions
	function toggleSettings() {
		const settingsPanel = document.getElementById('chatSettingsPanel');
		const settingsIcon = document.getElementById('settingsIcon');
		
		if (settingsOpen) {
			settingsPanel.classList.remove('open');
			settingsPanel.style.display = 'none';
			settingsIcon.className = 'fas fa-cog';
			settingsOpen = false;
		} else {
			settingsPanel.classList.add('open');
			settingsPanel.style.display = 'flex';
			settingsIcon.className = 'fas fa-cog';
			settingsOpen = true;
			updateSettingsStats();
		}
	}
	
	// Toggle block user function
	async function toggleBlockUser() {
		if (isBlocked && !confirm('Are you sure you want to unblock this user?')) {
			document.getElementById('settingsBlockToggle').checked = true;
			document.getElementById('sidebarBlockToggle').checked = true;
			return;
		}
		if (!isBlocked && !confirm('Are you sure you want to block this user? This will prevent them from messaging you.')) {
			document.getElementById('settingsBlockToggle').checked = false;
			document.getElementById('sidebarBlockToggle').checked = false;
			return;
		}
		
		try {
			const action = isBlocked ? 'unblock' : 'block';
			const response = await fetch('block_api.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				credentials: 'same-origin',
				body: new URLSearchParams({ action: action, user_id: String(peerId) })
			});
			
			const data = await response.json();
			if (data.ok) {
				isBlocked = !isBlocked;
				updateBlockStatus();
				
				// Show success message
				const message = isBlocked ? 'User blocked successfully' : 'User unblocked successfully';
				showMessage(message, 'success');
			} else {
				// Revert toggle state on error
				document.getElementById('settingsBlockToggle').checked = isBlocked;
				document.getElementById('sidebarBlockToggle').checked = isBlocked;
				showMessage('Failed to update block status: ' + data.error, 'error');
			}
		} catch (error) {
			console.error('Error toggling block status:', error);
			document.getElementById('settingsBlockToggle').checked = isBlocked;
			document.getElementById('sidebarBlockToggle').checked = isBlocked;
			showMessage('Error updating block status', 'error');
		}
	}
	
	// Update block status display
	function updateBlockStatus() {
		// Update settings panel elements
		const settingsBlockLabel = document.getElementById('settingsBlockLabel');
		const settingsBlockStatus = document.getElementById('settingsBlockStatus');
		const settingsBlockToggle = document.getElementById('settingsBlockToggle');
		
		// Update sidebar elements
		const sidebarBlockLabel = document.getElementById('sidebarBlockLabel');
		const sidebarBlockStatus = document.getElementById('sidebarBlockStatus');
		const sidebarBlockToggle = document.getElementById('sidebarBlockToggle');
		
		// Update settings panel
		if (settingsBlockToggle) {
			settingsBlockToggle.checked = isBlocked;
		}
		if (settingsBlockLabel) {
			settingsBlockLabel.textContent = isBlocked ? 'Unblock User' : 'Block User';
		}
		if (settingsBlockStatus) {
			settingsBlockStatus.textContent = isBlocked ? 'User is blocked' : 'User is not blocked';
			settingsBlockStatus.className = isBlocked ? 'text-danger' : 'text-muted';
		}
		
		// Update sidebar
		if (sidebarBlockToggle) {
			sidebarBlockToggle.checked = isBlocked;
		}
		if (sidebarBlockLabel) {
			sidebarBlockLabel.textContent = isBlocked ? 'Unblock User' : 'Block User';
		}
		if (sidebarBlockStatus) {
			sidebarBlockStatus.textContent = isBlocked ? 'User is blocked' : 'User is not blocked';
			sidebarBlockStatus.className = isBlocked ? 'text-danger' : 'text-muted';
		}
		
		console.log('Updated block status to:', isBlocked);
		
		// Update UI to reflect blocked state
		if (isBlocked) {
			// Hide chat content and show blocked message
			document.getElementById('chatContent').style.display = 'none';
			// Remove any existing blocked message first
			const existingBlockedMessage = document.querySelector('.wechat-empty.blocked-message');
			if (existingBlockedMessage) {
				existingBlockedMessage.remove();
			}
			
			const blockedMessage = document.createElement('div');
			blockedMessage.className = 'wechat-empty blocked-message';
			blockedMessage.innerHTML = `
				<i class="fas fa-ban"></i>
				<h3>User Blocked</h3>
				<p>You have blocked this user. Unblock them to resume chatting.</p>
			`;
			document.querySelector('.chat-messages-container').appendChild(blockedMessage);
		} else {
			// Show chat content and remove blocked message
			document.getElementById('chatContent').style.display = 'flex';
			const blockedMessage = document.querySelector('.wechat-empty.blocked-message');
			if (blockedMessage) {
				blockedMessage.remove();
			}
		}
	}
	
	// Update settings statistics
	function updateSettingsStats() {
		document.getElementById('settingsMessagesSent').textContent = messagesSent;
		document.getElementById('settingsMessagesReceived').textContent = messagesReceived;
		
		const duration = Math.floor((Date.now() - chatStartTime) / 60000);
		document.getElementById('settingsChatDuration').textContent = `${duration} min`;
	}
	
	// Initialize block status from server on page load
	// This ensures the block status is properly loaded from the database
	// and the UI is synchronized with the actual server state
	checkCurrentBlockStatus();
	
	// Check block status when page becomes visible (user switches back to tab)
	document.addEventListener('visibilitychange', function() {
		if (!document.hidden) {
			console.log('Page became visible, checking block status...');
			checkCurrentBlockStatus();
		}
	});
	
	// Check current block status from server
	async function checkCurrentBlockStatus() {
		try {
			const response = await fetch('block_api.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				credentials: 'same-origin',
				body: new URLSearchParams({ action: 'check', user_id: String(peerId) })
			});
			
			const data = await response.json();
			if (data.ok) {
				// Update the isBlocked variable with server data
				isBlocked = data.blocked;
				updateBlockStatus();
				console.log('Block status updated from server:', isBlocked);
			}
		} catch (error) {
			console.error('Error checking block status:', error);
		}
	}
	
	// Toggle notifications functionality
	document.getElementById('toggleNotifications').addEventListener('click', function() {
		const notificationIcon = document.getElementById('notificationIcon');
		
		if (notificationsEnabled) {
			// Disable notifications
			notificationsEnabled = false;
			notificationIcon.className = 'fas fa-bell-slash';
			notificationIcon.style.color = '#dc3545';
		} else {
			// Enable notifications
			notificationsEnabled = true;
			notificationIcon.className = 'fas fa-bell';
			notificationIcon.style.color = '#28a745';
		}
	});
	
	// Notification functions
	function showNotification() {
		if (notificationsEnabled && 'Notification' in window) {
			if (Notification.permission === 'granted') {
				new Notification('New Message', {
					body: 'You have a new message in chat',
					icon: '/favicon.ico'
				});
			} else if (Notification.permission !== 'denied') {
				Notification.requestPermission().then(permission => {
					if (permission === 'granted') {
						new Notification('New Message', {
							body: 'You have a new message in chat',
							icon: '/favicon.ico'
						});
					}
				});
			}
		}
	}
	
	function updateUnreadCount() {
		// Update unread count in sidebar if chat is minimized
		if (chatMinimized) {
			unreadCount++;
			const chatItem = document.querySelector(`[onclick*="user_id=${peerId}"]`);
			if (chatItem) {
				let badge = chatItem.querySelector('.unread-badge');
				if (!badge) {
					badge = document.createElement('span');
					badge.className = 'unread-badge wechat-badge';
					chatItem.appendChild(badge);
				}
				badge.textContent = unreadCount;
			}
		}
	}
	
	// Sidebar functionality
	const toggleSidebarBtn = document.getElementById('toggleSidebar');
	if (toggleSidebarBtn) {
		toggleSidebarBtn.addEventListener('click', function() {
			const sidebar = document.getElementById('chatSidebar');
			const sidebarIcon = document.getElementById('sidebarIcon');
			
			if (sidebarOpen) {
				// Close sidebar
				sidebar.classList.remove('open');
				sidebar.style.display = 'none';
				sidebarIcon.className = 'fas fa-info-circle';
				sidebarOpen = false;
			} else {
				// Open sidebar
				sidebar.classList.add('open');
				sidebar.style.display = 'flex';
				sidebarIcon.className = 'fas fa-times';
				sidebarOpen = true;
			}
		});
	}
	
	document.getElementById('closeSidebar').addEventListener('click', function() {
		const sidebar = document.getElementById('chatSidebar');
		const sidebarIcon = document.getElementById('sidebarIcon');
		sidebar.classList.remove('open');
		sidebar.style.display = 'none';
		sidebarIcon.className = 'fas fa-info-circle';
		sidebarOpen = false;
	});
	
	// Sidebar settings functionality
	document.getElementById('sidebarNotifications').addEventListener('change', function() {
		notificationsEnabled = this.checked;
		const notificationIcon = document.getElementById('notificationIcon');
		if (notificationsEnabled) {
			notificationIcon.className = 'fas fa-bell';
			notificationIcon.style.color = '#28a745';
		} else {
			notificationIcon.className = 'fas fa-bell-slash';
			notificationIcon.style.color = '#dc3545';
		}
	});
	
	document.getElementById('showTimestamps').addEventListener('change', function() {
		const messages = document.querySelectorAll('.wechat-message-time');
		messages.forEach(msg => {
			msg.style.display = this.checked ? 'block' : 'none';
		});
	});
	
	// Sidebar actions
	document.getElementById('clearChat').addEventListener('click', function() {
		if (confirm('Are you sure you want to clear this chat? This action cannot be undone.')) {
			document.getElementById('messages').innerHTML = '';
			messagesSent = 0;
			messagesReceived = 0;
			updateMessageStats();
		}
	});
	
	document.getElementById('exportChat').addEventListener('click', function() {
		const messages = document.querySelectorAll('.wechat-message');
		let chatText = 'Chat Export\n================\n\n';
		
		messages.forEach(msg => {
			const sender = msg.querySelector('.wechat-message-bubble').textContent;
			const time = msg.querySelector('.wechat-message-time').textContent;
			const isSent = msg.classList.contains('sent');
			chatText += `[${time}] ${isSent ? 'You' : 'Friend'}: ${sender}\n`;
		});
		
		const blob = new Blob([chatText], { type: 'text/plain' });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = `chat_export_${new Date().toISOString().split('T')[0]}.txt`;
		a.click();
		URL.revokeObjectURL(url);
	});
	
	
	// Quick actions
	document.getElementById('sendFile').addEventListener('click', function() {
		alert('File upload feature coming soon!');
	});
	
	document.getElementById('sendImage').addEventListener('click', function() {
		alert('Image upload feature coming soon!');
	});
	
	document.getElementById('voiceMessage').addEventListener('click', function() {
		alert('Voice message feature coming soon!');
	});
	
	// Test context menu
	document.getElementById('testContextMenu').addEventListener('click', function() {
		console.log('Testing context menu...');
		const event = { 
			pageX: 300, 
			pageY: 300,
			clientX: 300,
			clientY: 300
		};
		showContextMenu(event, '999', 'Test message', 'test');
	});
	
	// Statistics functions
	function updateMessageStats() {
		const messages = document.querySelectorAll('.wechat-message');
		messagesSent = document.querySelectorAll('.wechat-message.sent').length;
		messagesReceived = document.querySelectorAll('.wechat-message.received').length;
		
		document.getElementById('messagesSent').textContent = messagesSent;
		document.getElementById('messagesReceived').textContent = messagesReceived;
		
		// Update chat duration
		const duration = Math.floor((Date.now() - chatStartTime) / 60000);
		document.getElementById('chatDuration').textContent = `${duration} min`;
	}
	
	
	// Show message function
	function showMessage(text, type = 'info') {
		const messageDiv = document.createElement('div');
		messageDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
		messageDiv.innerHTML = `
			${text}
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		`;
		
		// Insert at the top of the chat area
		const chatArea = document.querySelector('.wechat-chat-area');
		chatArea.insertBefore(messageDiv, chatArea.firstChild);
		
		// Auto remove after 3 seconds
		setTimeout(() => {
			if (messageDiv.parentNode) {
				messageDiv.remove();
			}
		}, 3000);
	}
	
	
	
	// Friends search functionality
	document.getElementById('friendSearch').addEventListener('input', function() {
		const searchTerm = this.value.toLowerCase();
		const friendsList = document.getElementById('friendsList');
		const friendItems = friendsList.querySelectorAll('.wechat-chat-item');
		let visibleCount = 0;
		
		friendItems.forEach(item => {
			const friendName = item.querySelector('.wechat-chat-name').textContent.toLowerCase();
			if (friendName.includes(searchTerm)) {
				item.style.display = 'flex';
				visibleCount++;
			} else {
				item.style.display = 'none';
			}
		});
		
		// Update friends count
		document.getElementById('friendsCount').textContent = visibleCount;
	});
	
	// Initialize friends count
	document.getElementById('friendsCount').textContent = document.querySelectorAll('.wechat-chat-item').length;
	
	// Request notification permission on page load
	if ('Notification' in window && Notification.permission === 'default') {
		Notification.requestPermission();
	}
	
	// Context Menu Functions
	let contextMenuVisible = false;
	let currentMessageId = null;
	let currentMessageText = '';
	let currentMessageSender = '';
	let currentMessageTimestamp = null;
	
	// Show context menu
	function showContextMenu(event, messageId, messageText, sender, timestamp) {
		console.log('showContextMenu called:', messageId, messageText, sender, timestamp);
		
		// Hide any existing context menu first
		hideContextMenu();
		
		currentMessageId = messageId;
		currentMessageText = messageText;
		currentMessageSender = sender;
		currentMessageTimestamp = timestamp || Date.now();
		
		const contextMenu = document.getElementById('messageContextMenu');
		if (!contextMenu) {
			console.error('Context menu element not found!');
			return;
		}
		
		// Show/hide time-sensitive options based on 2-minute window
		const timeSensitiveItems = contextMenu.querySelectorAll('[data-time-sensitive="true"]');
		const canEdit = sender === 'me' && canEditOrDelete();
		
		timeSensitiveItems.forEach(item => {
			item.style.display = canEdit ? 'flex' : 'none';
		});
		
		// Get mouse position
		const x = event.pageX || event.clientX;
		const y = event.pageY || event.clientY;
		
		console.log('Showing context menu at:', x, y);
		
		// Show the menu
		contextMenu.style.display = 'block';
		contextMenu.style.position = 'fixed';
		contextMenu.style.left = x + 'px';
		contextMenu.style.top = y + 'px';
		contextMenu.style.zIndex = '99999';
		
		// Adjust position if menu goes outside viewport
		const rect = contextMenu.getBoundingClientRect();
		if (rect.right > window.innerWidth) {
			contextMenu.style.left = (event.pageX - rect.width) + 'px';
		}
		if (rect.bottom > window.innerHeight) {
			contextMenu.style.top = (event.pageY - rect.height) + 'px';
		}
		
		contextMenuVisible = true;
		
		// Add event listeners for context menu items
		const menuItems = contextMenu.querySelectorAll('.context-menu-item');
		menuItems.forEach(item => {
			item.onclick = handleContextMenuAction;
		});
		
		// Add event listeners for reactions
		const reactionItems = contextMenu.querySelectorAll('.reaction-emoji');
		reactionItems.forEach(item => {
			item.onclick = handleReactionClick;
		});
	}
	
	// Hide context menu
	function hideContextMenu() {
		const contextMenu = document.getElementById('messageContextMenu');
		contextMenu.style.display = 'none';
		contextMenuVisible = false;
	}
	
	// Handle context menu actions
	function handleContextMenuAction(event) {
		const action = event.currentTarget.getAttribute('data-action');
		
		switch(action) {
			case 'reply':
				replyToMessage();
				break;
			case 'copy':
				copyMessage();
				break;
			case 'forward':
				forwardMessage();
				break;
			case 'favorite':
				favoriteMessage();
				break;
			case 'quote':
				quoteMessage();
				break;
			case 'delete':
				deleteMessage();
				break;
			case 'select':
				selectMessage();
				break;
			case 'share':
				shareMessage();
				break;
		}
		
		hideContextMenu();
	}
	
	// Handle reaction clicks
	function handleReactionClick(event) {
		const reaction = event.currentTarget.getAttribute('data-reaction');
		
		if (reaction === '+') {
			// Show more reactions
			showMoreReactions();
		} else {
			// Add reaction to message
			addReaction(reaction);
		}
		
		hideContextMenu();
	}
	
	// Context menu action functions
	function replyToMessage() {
		const messageInput = document.getElementById('msg');
		messageInput.value = `@${currentMessageSender}: "${currentMessageText}" - `;
		messageInput.focus();
		showMessage('Reply mode activated', 'info');
	}
	
	function copyMessage() {
		navigator.clipboard.writeText(currentMessageText).then(() => {
			showMessage('Message copied to clipboard', 'success');
		}).catch(() => {
			showMessage('Failed to copy message', 'error');
		});
	}
	
	function forwardMessage() {
		// For now, show a simple prompt
		showMessage('Forward feature coming soon!', 'info');
	}
	
	function favoriteMessage() {
		// Add star indicator to message
		const messageElement = document.querySelector(`[data-message-id="${currentMessageId}"]`);
		if (messageElement) {
			if (!messageElement.querySelector('.favorite-star')) {
				const star = document.createElement('span');
				star.className = 'favorite-star';
				star.innerHTML = '<i class="fas fa-star"></i>';
				messageElement.appendChild(star);
				showMessage('Message added to favorites', 'success');
			} else {
				messageElement.querySelector('.favorite-star').remove();
				showMessage('Message removed from favorites', 'info');
			}
		}
	}
	
	function quoteMessage() {
		// Pin message to top
		const messageElement = document.querySelector(`[data-message-id="${currentMessageId}"]`);
		if (messageElement) {
			messageElement.classList.toggle('pinned-message');
			showMessage('Message pinned to top', 'success');
		}
	}
	
	function deleteMessage() {
		if (confirm('Are you sure you want to delete this message from your client?')) {
			const messageElement = document.querySelector(`[data-message-id="${currentMessageId}"]`);
			if (messageElement) {
				messageElement.style.opacity = '0.5';
				messageElement.style.textDecoration = 'line-through';
				showMessage('Message deleted from your view', 'info');
			}
		}
	}
	
	function selectMessage() {
		const messageElement = document.querySelector(`[data-message-id="${currentMessageId}"]`);
		if (messageElement) {
			messageElement.classList.toggle('selected-message');
			showMessage('Message selected', 'info');
		}
	}
	
	function shareMessage() {
		if (navigator.share) {
			navigator.share({
				title: 'Shared Message',
				text: currentMessageText
			}).then(() => {
				showMessage('Message shared successfully', 'success');
			}).catch(() => {
				showMessage('Share cancelled', 'info');
			});
		} else {
			// Fallback to copying
			copyMessage();
		}
	}
	
	function addReaction(reaction) {
		// Add reaction to message visually
		const messageElement = document.querySelector(`[data-message-id="${currentMessageId}"]`);
		if (messageElement) {
			let reactionsContainer = messageElement.querySelector('.message-reactions');
			if (!reactionsContainer) {
				reactionsContainer = document.createElement('div');
				reactionsContainer.className = 'message-reactions';
				messageElement.appendChild(reactionsContainer);
			}
			
			// Check if reaction already exists
			const existingReaction = reactionsContainer.querySelector(`[data-reaction="${reaction}"]`);
			if (existingReaction) {
				// Increment count
				const count = existingReaction.querySelector('.reaction-count');
				count.textContent = parseInt(count.textContent) + 1;
			} else {
				// Create new reaction
				const reactionDiv = document.createElement('div');
				reactionDiv.className = 'message-reaction';
				reactionDiv.setAttribute('data-reaction', reaction);
				reactionDiv.innerHTML = `
					<span class="reaction-emoji">${reaction}</span>
					<span class="reaction-count">1</span>
				`;
				reactionsContainer.appendChild(reactionDiv);
			}
			
			showMessage(`Reacted with ${reaction}`, 'success');
		}
	}
	
	function showMoreReactions() {
		// Show additional reaction options
		const moreReactions = ['👍', '👎', '😍', '🤔', '😡', '😱', '🎉', '🔥'];
		const reaction = prompt('Choose a reaction: ' + moreReactions.join(' '));
		if (reaction && moreReactions.includes(reaction)) {
			addReaction(reaction);
		}
	}
	
	// Global click event to hide context menu
	document.addEventListener('click', function(event) {
		if (contextMenuVisible && !event.target.closest('#messageContextMenu')) {
			hideContextMenu();
		}
	});
	
	// Prevent default context menu on messages container
	document.addEventListener('DOMContentLoaded', function() {
		const messagesContainer = document.getElementById('messages');
		if (messagesContainer) {
			messagesContainer.addEventListener('contextmenu', function(e) {
				// Only prevent default if target is a message
				if (e.target.closest('.wechat-message')) {
					e.preventDefault();
					return false;
				}
			});
		}
		
		// Initialize all new features
		initializeVoiceRecording();
		initializeFileUpload();
		initializeNewFeatures();
		// Voice call is initialized after voice-call.js at page bottom (initLineVoiceCall)
	});
	
	// ===== NEW FEATURES IMPLEMENTATION =====
	
	// Voice Recording Variables
	let mediaRecorder = null;
	let audioChunks = [];
	let recordingStartTime = null;
	let recordingTimer = null;
	let isRecording = false;
	
	// Initialize Voice Recording
	function initializeVoiceRecording() {
		document.getElementById('recordVoice').addEventListener('click', toggleVoiceRecording);
		document.getElementById('cancelVoice').addEventListener('click', cancelVoiceRecording);
		document.getElementById('sendVoice').addEventListener('click', sendVoiceMessage);
	}
	
	// Toggle voice recording
	async function toggleVoiceRecording() {
		if (!isRecording) {
			startVoiceRecording();
		} else {
			stopVoiceRecording();
		}
	}
	
	// Start voice recording
	async function startVoiceRecording() {
		try {
			const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
			mediaRecorder = new MediaRecorder(stream);
			audioChunks = [];
			
			mediaRecorder.ondataavailable = (event) => {
				audioChunks.push(event.data);
			};
			
			mediaRecorder.onstop = () => {
				stream.getTracks().forEach(track => track.stop());
			};
			
			mediaRecorder.start();
			isRecording = true;
			recordingStartTime = Date.now();
			
			// Show recording interface
			document.getElementById('voiceRecording').style.display = 'block';
			document.getElementById('sendForm').style.display = 'none';
			
			// Start timer
			startRecordingTimer();
			
			// Animate waveform
			animateWaveform();
			
			showMessage('Recording started...', 'info');
		} catch (error) {
			console.error('Error starting recording:', error);
			showMessage('Could not access microphone', 'error');
		}
	}
	
	// Stop voice recording
	function stopVoiceRecording() {
		if (mediaRecorder && isRecording) {
			mediaRecorder.stop();
			isRecording = false;
			clearInterval(recordingTimer);
			stopWaveformAnimation();
		}
	}
	
	// Cancel voice recording
	function cancelVoiceRecording() {
		stopVoiceRecording();
		audioChunks = [];
		hideVoiceRecording();
		showMessage('Recording cancelled', 'info');
	}
	
	// Send voice message
	async function sendVoiceMessage() {
		if (audioChunks.length === 0) {
			showMessage('No recording to send', 'error');
			return;
		}
		
		const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
		const formData = new FormData();
		formData.append('voice', audioBlob, 'voice_message.wav');
		formData.append('action', 'send_voice');
		formData.append('peer_id', peerId);
		formData.append('duration', Math.floor((Date.now() - recordingStartTime) / 1000));
		
		try {
			const response = await fetch('voice_api.php', {
				method: 'POST',
				body: formData
			});
			
			const result = await response.json();
			if (result.success) {
				hideVoiceRecording();
				audioChunks = [];
				await fetchMessages();
				showMessage('Voice message sent', 'success');
			} else {
				showMessage('Failed to send voice message: ' + result.error, 'error');
			}
		} catch (error) {
			console.error('Error sending voice message:', error);
			showMessage('Error sending voice message', 'error');
		}
	}
	
	// Hide voice recording interface
	function hideVoiceRecording() {
		document.getElementById('voiceRecording').style.display = 'none';
		document.getElementById('sendForm').style.display = 'flex';
		document.querySelector('.voice-timer').textContent = '00:00';
	}
	
	// Start recording timer
	function startRecordingTimer() {
		recordingTimer = setInterval(() => {
			const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
			const minutes = Math.floor(elapsed / 60).toString().padStart(2, '0');
			const seconds = (elapsed % 60).toString().padStart(2, '0');
			document.querySelector('.voice-timer').textContent = `${minutes}:${seconds}`;
		}, 1000);
	}
	
	// Animate waveform during recording
	function animateWaveform() {
		const bars = document.querySelectorAll('.waveform-bars .bar');
		setInterval(() => {
			if (isRecording) {
				bars.forEach(bar => {
					const height = Math.random() * 100 + 20;
					bar.style.height = height + '%';
				});
			}
		}, 100);
	}
	
	// Stop waveform animation
	function stopWaveformAnimation() {
		const bars = document.querySelectorAll('.waveform-bars .bar');
		bars.forEach(bar => {
			bar.style.height = '20%';
		});
	}
	
	// Play voice message
	function playVoiceMessage(filePath) {
		const audio = new Audio(filePath);
		audio.play().catch(error => {
			console.error('Error playing audio:', error);
			showMessage('Could not play voice message', 'error');
		});
	}
	
	// Initialize File Upload
	function initializeFileUpload() {
		document.getElementById('attachFile').addEventListener('click', () => {
			document.getElementById('fileInput').click();
		});
		
		document.getElementById('attachImage').addEventListener('click', () => {
			document.getElementById('imageInput').click();
		});
		
		document.getElementById('fileInput').addEventListener('change', handleFileUpload);
		document.getElementById('imageInput').addEventListener('change', handleImageUpload);
	}
	
	// Handle file upload
	async function handleFileUpload(event) {
		const files = event.target.files;
		if (files.length === 0) return;
		
		for (const file of files) {
			await uploadFile(file, 'file');
		}
		
		// Clear input
		event.target.value = '';
	}
	
	// Handle image upload
	async function handleImageUpload(event) {
		const files = event.target.files;
		if (files.length === 0) return;
		
		for (const file of files) {
			await uploadFile(file, 'image');
		}
		
		// Clear input
		event.target.value = '';
	}
	
	// Upload file function
	async function uploadFile(file, type) {
		const formData = new FormData();
		formData.append('file', file);
		formData.append('action', 'upload');
		formData.append('peer_id', peerId);
		formData.append('file_type', type);
		
		try {
			showMessage(`Uploading ${file.name}...`, 'info');
			
			const response = await fetch('file_upload_api.php', {
				method: 'POST',
				body: formData
			});
			
			const result = await response.json();
			if (result.success) {
				await fetchMessages();
				showMessage(`${file.name} uploaded successfully`, 'success');
			} else {
				showMessage(`Failed to upload ${file.name}: ${result.error}`, 'error');
			}
		} catch (error) {
			console.error('Error uploading file:', error);
			showMessage(`Error uploading ${file.name}`, 'error');
		}
	}
	
	// Download file function
	function downloadFile(filePath, fileName) {
		const link = document.createElement('a');
		link.href = `serve_file.php?file=${encodeURIComponent(filePath)}`;
		link.download = fileName;
		link.click();
	}
	
	// Open image preview
	function openImagePreview(imagePath) {
		const modal = document.createElement('div');
		modal.className = 'image-preview-modal';
		modal.innerHTML = `
			<div class="image-preview-content">
				<span class="image-preview-close">&times;</span>
				<img src="${imagePath}" alt="Image Preview">
			</div>
		`;
		
		document.body.appendChild(modal);
		
		// Close on click
		modal.addEventListener('click', () => {
			document.body.removeChild(modal);
		});
	}
	
	// Initialize other new features
	function initializeNewFeatures() {
		// Voice call is handled by setupLineVoiceCall()
		
		// Edit and delete buttons (for 2-minute window)
		document.addEventListener('click', handleMessageActions);
		
		// Reply functionality
		document.getElementById('cancelReply').addEventListener('click', cancelReply);
		
		// @ mention functionality
		document.getElementById('msg').addEventListener('input', handleMentionInput);
		
		// Forward modal
		document.getElementById('confirmForward').addEventListener('click', confirmForward);
		
		// Edit modal
		document.getElementById('confirmEdit').addEventListener('click', confirmEdit);
	}
	
	function setupLineVoiceCall() {
		// Deprecated — real init is initLineVoiceCall after voice-call.js
		if (typeof window.startLineCall === 'function') return;
		console.warn('setupLineVoiceCall skipped; waiting for bottom init');
	}

	function initiateVoiceCall() {
		if (typeof window.startLineCall === 'function') window.startLineCall();
	}
	
	// Handle @ mentions
	function handleMentionInput(event) {
		const input = event.target;
		const value = input.value;
		const atIndex = value.lastIndexOf('@');
		
		if (atIndex !== -1 && atIndex === value.length - 1) {
			// Show mention suggestions (simplified)
			showMessage('@ mention feature - type username after @', 'info');
		}
	}
	
	// Reply to message
	function replyToMessage(messageId, messageText, sender) {
		document.getElementById('replyToMessageId').value = messageId;
		document.getElementById('replyToUser').textContent = sender === 'me' ? 'You' : sender;
		document.getElementById('replyToText').textContent = messageText;
		document.getElementById('replyPreview').style.display = 'block';
		document.getElementById('msg').focus();
	}
	
	// Cancel reply
	function cancelReply() {
		document.getElementById('replyToMessageId').value = '';
		document.getElementById('replyPreview').style.display = 'none';
	}
	
	// Enhanced context menu actions
	function handleContextMenuAction(event) {
		const action = event.currentTarget.getAttribute('data-action');
		const isTimeSensitive = event.currentTarget.getAttribute('data-time-sensitive') === 'true';
		
		// Check 2-minute window for edit/delete
		if (isTimeSensitive && !canEditOrDelete()) {
			showMessage('You can only edit or delete messages within 2 minutes', 'error');
			hideContextMenu();
			return;
		}
		
		switch(action) {
			case 'reply':
				replyToMessage(currentMessageId, currentMessageText, currentMessageSender);
				break;
			case 'copy':
				copyMessage();
				break;
			case 'forward':
				showForwardModal();
				break;
			case 'edit':
				showEditModal();
				break;
			case 'favorite':
				toggleFavorite();
				break;
			case 'pin':
				togglePin();
				break;
			case 'delete':
				deleteMessage();
				break;
			case 'select':
				selectMessage();
				break;
		}
		
		hideContextMenu();
	}
	
	// Check if message can be edited or deleted (2-minute window)
	function canEditOrDelete() {
		if (!currentMessageTimestamp) {
			const messageElement = document.querySelector(`[data-message-id="${currentMessageId}"]`);
			if (!messageElement) return false;
			
			const timestamp = parseInt(messageElement.getAttribute('data-timestamp'));
			if (timestamp) {
				currentMessageTimestamp = timestamp;
			}
		}
		
		if (!currentMessageTimestamp) return false;
		
		const now = Date.now();
		const twoMinutes = 2 * 60 * 1000; // 2 minutes in milliseconds
		
		return (now - currentMessageTimestamp) < twoMinutes;
	}
	
	// Show forward modal
	function showForwardModal() {
		document.getElementById('forwardMessageText').textContent = currentMessageText;
		loadForwardContacts();
		const forwardModal = new bootstrap.Modal(document.getElementById('forwardModal'));
		forwardModal.show();
	}
	
	// Load forward contacts
	async function loadForwardContacts() {
		try {
			const response = await fetch('forward_api.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({ action: 'get_forward_targets' })
			});
			const data = await response.json();
			
			if (data.success) {
				const container = document.getElementById('forwardContacts');
				container.innerHTML = '';
				
				data.targets.forEach(target => {
					const contactDiv = document.createElement('div');
					contactDiv.className = 'forward-contact';
					contactDiv.innerHTML = `
						<input type="checkbox" value="${target.id}" data-type="${target.type}" id="contact_${target.id}_${target.type}">
						<label for="contact_${target.id}_${target.type}">
							<i class="fas fa-${target.type === 'group' ? 'users' : 'user'}"></i>
							${target.username}
						</label>
					`;
					container.appendChild(contactDiv);
				});
			}
		} catch (error) {
			console.error('Error loading contacts:', error);
			showMessage('Error loading contacts', 'error');
		}
	}
	
	// Show edit modal
	function showEditModal() {
		document.getElementById('editMessageText').value = currentMessageText;
		const editModal = new bootstrap.Modal(document.getElementById('editModal'));
		editModal.show();
	}
	
	// Confirm forward
	async function confirmForward() {
		const selectedContacts = Array.from(document.querySelectorAll('#forwardContacts input:checked')).map(cb => cb.value);
		
		if (selectedContacts.length === 0) {
			showMessage('Please select at least one contact', 'error');
			return;
		}
		
		try {
			const response = await fetch('forward_api.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({
					action: 'forward',
					message_id: currentMessageId,
					contacts: selectedContacts
				})
			});
			
			const result = await response.json();
			if (result.success) {
				bootstrap.Modal.getInstance(document.getElementById('forwardModal')).hide();
				showMessage('Message forwarded successfully', 'success');
			} else {
				showMessage('Failed to forward message: ' + result.error, 'error');
			}
		} catch (error) {
			console.error('Error forwarding message:', error);
			showMessage('Error forwarding message', 'error');
		}
	}
	
	// Confirm edit
	async function confirmEdit() {
		const newText = document.getElementById('editMessageText').value.trim();
		
		if (!newText) {
			showMessage('Message cannot be empty', 'error');
			return;
		}
		
		try {
			const response = await fetch('chat_api.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({
					action: 'edit',
					message_id: currentMessageId,
					new_text: newText
				})
			});
			
			const result = await response.json();
			if (result.success) {
				bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
				await fetchMessages();
				showMessage('Message edited successfully', 'success');
			} else {
				showMessage('Failed to edit message: ' + result.error, 'error');
			}
		} catch (error) {
			console.error('Error editing message:', error);
			showMessage('Error editing message', 'error');
		}
	}
	
	// Toggle favorite
	function toggleFavorite() {
		const messageElement = document.querySelector(`[data-message-id="${currentMessageId}"]`);
		if (messageElement) {
			const star = messageElement.querySelector('.favorite-star');
			if (!star) {
				const starElement = document.createElement('span');
				starElement.className = 'favorite-star';
				starElement.innerHTML = '<i class="fas fa-star"></i>';
				messageElement.appendChild(starElement);
				showMessage('Message added to favorites', 'success');
			} else {
				star.remove();
				showMessage('Message removed from favorites', 'info');
			}
		}
	}
	
	// Toggle pin
	function togglePin() {
		const messageElement = document.querySelector(`[data-message-id="${currentMessageId}"]`);
		if (messageElement) {
			messageElement.classList.toggle('pinned-message');
			const isPinned = messageElement.classList.contains('pinned-message');
			showMessage(isPinned ? 'Message pinned' : 'Message unpinned', 'success');
		}
	}
	</script>
	
	<style>
	.chat-controls {
		display: flex;
		gap: 5px;
	}
	
	.chat-content {
		transition: all 0.3s ease;
	}
	
	.unread-badge {
		position: absolute;
		top: -5px;
		right: -5px;
		background-color: #FF3B30;
		color: white;
		border-radius: 10px;
		padding: 2px 6px;
		font-size: 11px;
		font-weight: 500;
		min-width: 16px;
		text-align: center;
	}
	
	.wechat-chat-item {
		position: relative;
	}
	
	/* Friends count styling */
	.friends-count {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px 12px;
		background-color: #f8f9fa;
		border-radius: 6px;
		margin-top: 8px;
		font-size: 14px;
		color: #6c757d;
		border: 1px solid #e9ecef;
	}
	
	.friends-count i {
		color: var(--wechat-green);
	}
	
	.friends-count span {
		font-weight: 600;
		color: var(--wechat-green);
	}
	
	/* Search input styling */
	#friendSearch {
		margin-bottom: 8px;
		border-radius: 6px;
		border: 1px solid #e9ecef;
		padding: 10px 12px;
		font-size: 14px;
	}
	
	#friendSearch:focus {
		border-color: var(--wechat-green);
		box-shadow: 0 0 0 2px rgba(7, 193, 96, 0.1);
		outline: none;
	}
	
	/* Blocked user styling for chat list */
	.blocked-user {
		opacity: 0.6;
		background-color: #f8f9fa;
		cursor: not-allowed;
	}
	
	.blocked-indicator {
		position: absolute;
		top: -2px;
		right: -2px;
		background-color: #dc3545;
		color: white;
		border-radius: 50%;
		width: 16px;
		height: 16px;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 8px;
	}
	
	.blocked-badge {
		background-color: #dc3545;
		color: white;
		font-size: 10px;
		padding: 2px 6px;
		border-radius: 10px;
		margin-left: 8px;
	}
	
	.wechat-avatar {
		position: relative;
	}
	
	.blocked-user .wechat-chat-name {
		color: #6c757d;
	}
	
	.blocked-user .wechat-chat-preview {
		color: #dc3545;
		font-style: italic;
	}
	
	/* Blocked message styling */
	.blocked-message {
		display: flex;
		justify-content: center;
		margin: 10px 0;
	}
	
	.blocked-message .wechat-message-content {
		background-color: #f8f9fa;
		border: 1px solid #e9ecef;
		border-radius: 20px;
		padding: 10px 15px;
		display: flex;
		align-items: center;
		gap: 8px;
		max-width: 80%;
	}
	
	.blocked-message .blocked-indicator {
		color: #dc3545;
		font-size: 16px;
	}
	
	.blocked-message .blocked-text {
		color: #6c757d;
		font-size: 14px;
		font-style: italic;
	}
	
	/* Blocked message sent styling */
	.blocked-message-sent {
		position: relative;
	}
	
	.wechat-message-bubble-container {
		display: flex;
		align-items: center;
		gap: 8px;
	}
	
	.blocked-error-indicator {
		background-color: #dc3545;
		color: white;
		border-radius: 50%;
		width: 20px;
		height: 20px;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 12px;
		flex-shrink: 0;
	}
	
	.blocked-message-sent .wechat-message-bubble {
		background-color: #95ec69;
		color: #000;
	}
	
	/* Chat Settings Panel */
	.chat-settings-panel {
		position: fixed;
		top: 0;
		right: 0;
		width: 350px;
		height: 100vh;
		background-color: white;
		border-left: 1px solid #e9ecef;
		z-index: 1000;
		overflow-y: auto;
		box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
	}
	
	.settings-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 15px 20px;
		border-bottom: 1px solid #e9ecef;
		background-color: #f8f9fa;
	}
	
	.settings-header h5 {
		margin: 0;
		color: #333;
	}
	
	.settings-content {
		padding: 20px;
	}
	
	.settings-section {
		margin-bottom: 25px;
	}
	
	.settings-section h6 {
		color: #666;
		margin-bottom: 15px;
		font-weight: 600;
		border-bottom: 1px solid #e9ecef;
		padding-bottom: 8px;
	}
	
	.setting-item {
		margin-bottom: 15px;
	}
	
	.user-profile {
		display: flex;
		align-items: center;
		padding: 15px;
		background-color: #f8f9fa;
		border-radius: 8px;
		margin-bottom: 20px;
	}
	
	.profile-avatar {
		width: 50px;
		height: 50px;
		background-color: var(--wechat-green);
		color: white;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 20px;
		font-weight: bold;
		margin-right: 15px;
	}
	
	.profile-info {
		flex: 1;
	}
	
	.profile-name {
		font-weight: 600;
		color: #333;
		margin-bottom: 4px;
	}
	
	.profile-status {
		color: #28a745;
		font-size: 14px;
	}
	
	.stat-item {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 8px 0;
		border-bottom: 1px solid #f0f0f0;
	}
	
	.stat-item:last-child {
		border-bottom: none;
	}
	
	.stat-item span:first-child {
		color: #666;
	}
	
	.stat-item span:last-child {
		font-weight: 600;
	}
	
	/* Form switch styling */
	.form-check-input:checked {
		background-color: var(--wechat-green);
		border-color: var(--wechat-green);
	}
	
	.form-check-input:focus {
		border-color: var(--wechat-green);
		box-shadow: 0 0 0 0.2rem rgba(7, 193, 96, 0.25);
	}
	
	
	#notificationIcon {
		transition: color 0.3s ease;
	}
	
	#toggleIcon {
		transition: transform 0.3s ease;
	}
	
	/* Chat Layout */
	.chat-main-container {
		display: flex;
		flex: 1;
		min-height: 0;
		height: 100%;
		position: relative;
		overflow: hidden;
	}
	.chat-messages-container {
		flex: 1;
		min-width: 0;
		min-height: 0;
		height: 100%;
		display: flex;
		flex-direction: column;
		overflow: hidden;
	}
	.chat-content {
		display: flex;
		flex-direction: column;
		flex: 1;
		min-height: 0;
		overflow: hidden;
	}
	.wechat-input-area {
		flex: 0 0 auto;
		margin-top: auto;
	}
	
	/* Chat Sidebar */
	.chat-sidebar {
		width: 300px;
		background-color: #f8f9fa;
		border-left: 1px solid #dee2e6;
		display: none;
		flex-direction: column;
		height: 100%;
		overflow-y: auto;
	}
	
	.sidebar-header {
		padding: 15px 20px;
		border-bottom: 1px solid #dee2e6;
		background-color: white;
		display: flex;
		justify-content: space-between;
		align-items: center;
	}
	
	.sidebar-content {
		flex: 1;
		padding: 20px;
	}
	
	.sidebar-section {
		margin-bottom: 25px;
	}
	
	.sidebar-section h6 {
		color: #495057;
		margin-bottom: 15px;
		font-weight: 600;
	}
	
	/* User Profile */
	.user-profile {
		display: flex;
		align-items: center;
		padding: 15px;
		background-color: white;
		border-radius: 8px;
		box-shadow: 0 2px 4px rgba(0,0,0,0.1);
	}
	
	.profile-avatar {
		width: 50px;
		height: 50px;
		border-radius: 50%;
		background-color: var(--wechat-green);
		display: flex;
		align-items: center;
		justify-content: center;
		color: white;
		font-size: 20px;
		font-weight: bold;
		margin-right: 15px;
	}
	
	.profile-info h6 {
		margin: 0;
		color: #212529;
	}
	
	.profile-info p {
		margin: 0;
		font-size: 14px;
	}
	
	/* Settings */
	.setting-item {
		padding: 10px 0;
		border-bottom: 1px solid #e9ecef;
	}
	
	.setting-item:last-child {
		border-bottom: none;
	}
	
	/* Action Buttons */
	.action-buttons {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}
	
	/* Statistics */
	.stat-item {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 8px 0;
		border-bottom: 1px solid #e9ecef;
		font-size: 14px;
	}
	
	.stat-item:last-child {
		border-bottom: none;
	}
	
	.stat-item span:first-child {
		color: #6c757d;
	}
	
	.stat-item span:last-child {
		font-weight: 600;
		color: var(--wechat-green);
	}
	
	/* Quick Actions */
	.quick-actions {
		display: flex;
		flex-direction: column;
		gap: 5px;
	}
	
	/* Custom Scrollbar Styles */
	.wechat-chat-list::-webkit-scrollbar,
	.wechat-messages::-webkit-scrollbar,
	.chat-sidebar::-webkit-scrollbar {
		width: 8px;
	}
	
	.wechat-chat-list::-webkit-scrollbar-track,
	.wechat-messages::-webkit-scrollbar-track,
	.chat-sidebar::-webkit-scrollbar-track {
		background: #f1f1f1;
		border-radius: 4px;
	}
	
	.wechat-chat-list::-webkit-scrollbar-thumb,
	.wechat-messages::-webkit-scrollbar-thumb,
	.chat-sidebar::-webkit-scrollbar-thumb {
		background: var(--wechat-green);
		border-radius: 4px;
		transition: background 0.3s ease;
	}
	
	.wechat-chat-list::-webkit-scrollbar-thumb:hover,
	.wechat-messages::-webkit-scrollbar-thumb:hover,
	.chat-sidebar::-webkit-scrollbar-thumb:hover {
		background: #07c160;
	}
	
	/* Hide default scrollbar for Firefox */
	.wechat-chat-list,
	.wechat-messages,
	.chat-sidebar {
		scrollbar-width: thin;
		scrollbar-color: var(--wechat-green) #f1f1f1;
	}
	
	/* Remove default scrollbar for all elements */
	* {
		scrollbar-width: none; /* Firefox */
		-ms-overflow-style: none; /* Internet Explorer 10+ */
	}
	
	*::-webkit-scrollbar {
		display: none; /* WebKit */
	}
	
	/* Re-enable custom scrollbars for specific elements */
	.wechat-chat-list,
	.wechat-messages,
	.chat-sidebar {
		scrollbar-width: thin; /* Firefox */
		-ms-overflow-style: auto; /* Internet Explorer 10+ */
	}
	
	.wechat-chat-list::-webkit-scrollbar,
	.wechat-messages::-webkit-scrollbar,
	.chat-sidebar::-webkit-scrollbar {
		display: block; /* WebKit */
		width: 8px;
	}
	
	/* Smooth scrolling */
	.wechat-chat-list,
	.wechat-messages,
	.chat-sidebar {
		scroll-behavior: smooth;
	}
	
	/* Custom scrollbar for main page */
	body {
		overflow-x: hidden;
	}
	
	.wechat-main {
		overflow-x: hidden;
	}
	
	/* Ensure proper overflow handling */
	.wechat-chat-list {
		overflow-y: auto;
		overflow-x: hidden;
	}
	
	.wechat-messages {
		overflow-y: auto;
		overflow-x: hidden;
	}
	
	.chat-sidebar {
		overflow-y: auto;
		overflow-x: hidden;
	}
	
	/* Responsive Design */
	@media (max-width: 768px) {
		.chat-sidebar {
			width: 100%;
			position: absolute;
			top: 0;
			right: 0;
			z-index: 1000;
			background-color: white;
		}
		
		.chat-main-container {
			position: relative;
		}
		
		/* Mobile scrollbar adjustments */
		.wechat-chat-list::-webkit-scrollbar,
		.wechat-messages::-webkit-scrollbar,
		.chat-sidebar::-webkit-scrollbar {
			width: 6px;
		}
	}
	
	/* Context Menu Styles */
	.context-menu {
		position: fixed !important;
		background: rgba(40, 40, 40, 0.95);
		border-radius: 12px;
		padding: 8px;
		box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
		z-index: 999999 !important;
		min-width: 280px;
		backdrop-filter: blur(10px);
		border: 1px solid rgba(255, 255, 255, 0.1);
		pointer-events: auto;
		top: 0;
		left: 0;
	}
	
	.context-menu-reactions {
		display: flex;
		justify-content: space-between;
		padding: 8px 4px;
		margin-bottom: 8px;
		border-bottom: 1px solid rgba(255, 255, 255, 0.1);
	}
	
	.reaction-emoji {
		width: 40px;
		height: 40px;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 20px;
		border-radius: 50%;
		cursor: pointer;
		transition: all 0.2s ease;
		background: rgba(255, 255, 255, 0.1);
	}
	
	.reaction-emoji:hover {
		background: rgba(255, 255, 255, 0.2);
		transform: scale(1.2);
	}
	
	.context-menu-options {
		display: flex;
		flex-direction: column;
		gap: 2px;
	}
	
	.context-menu-item {
		display: flex;
		align-items: center;
		padding: 12px 16px;
		color: white;
		cursor: pointer;
		border-radius: 8px;
		transition: background-color 0.2s ease;
		font-size: 14px;
		gap: 12px;
	}
	
	.context-menu-item:hover {
		background: rgba(255, 255, 255, 0.1);
	}
	
	.context-menu-item i {
		width: 16px;
		flex-shrink: 0;
		opacity: 0.8;
	}
	
	.context-menu-item span {
		flex-grow: 1;
		font-weight: 400;
	}
	
	/* Message Reactions */
	.message-reactions {
		display: flex;
		gap: 4px;
		margin-top: 4px;
		flex-wrap: wrap;
	}
	
	.message-reaction {
		display: flex;
		align-items: center;
		gap: 2px;
		background: rgba(7, 193, 96, 0.1);
		border: 1px solid var(--wechat-green);
		border-radius: 12px;
		padding: 2px 6px;
		font-size: 12px;
		cursor: pointer;
		transition: all 0.2s ease;
	}
	
	.message-reaction:hover {
		background: var(--wechat-green);
		color: white;
	}
	
	.reaction-count {
		font-weight: 600;
		font-size: 11px;
		min-width: 12px;
		text-align: center;
	}
	
	/* Message States */
	.favorite-star {
		position: absolute;
		top: -5px;
		right: -5px;
		color: #ffc107;
		font-size: 12px;
		background: white;
		border-radius: 50%;
		width: 20px;
		height: 20px;
		display: flex;
		align-items: center;
		justify-content: center;
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
	}
	
	.selected-message {
		background: rgba(7, 193, 96, 0.1);
		border-left: 3px solid var(--wechat-green);
		padding-left: 8px;
	}
	
	.pinned-message {
		background: rgba(255, 193, 7, 0.1);
		border-left: 3px solid #ffc107;
		padding-left: 8px;
		position: relative;
	}
	
	.pinned-message::before {
		content: '📌';
		position: absolute;
		top: 5px;
		left: -8px;
		font-size: 12px;
		background: white;
		border-radius: 50%;
		width: 16px;
		height: 16px;
		display: flex;
		align-items: center;
		justify-content: center;
	}
	
	/* Message hover effect */
	.wechat-message {
		position: relative;
		transition: background-color 0.2s ease;
	}
	
	.wechat-message:hover {
		background: rgba(0, 0, 0, 0.02);
	}
	
	/* Animation for context menu */
	.context-menu {
		animation: contextMenuSlideIn 0.2s ease-out;
	}
	
	@keyframes contextMenuSlideIn {
		from {
			opacity: 0;
			transform: scale(0.9) translateY(-10px);
		}
		to {
			opacity: 1;
			transform: scale(1) translateY(0);
		}
	}
			
	/* ===== NEW FEATURES STYLES ===== */
			
	/* Enhanced Input Area */
	.wechat-input-area {
		position: relative;
	}
			
	.wechat-input-group {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 12px;
		background: white;
		border-top: 1px solid var(--wechat-border);
	}
			
	.input-actions-left,
	.input-actions-right {
		display: flex;
		align-items: center;
		gap: 6px;
	}
			
	.input-action-btn {
		background: none;
		border: none;
		color: var(--wechat-text-light);
		padding: 8px;
		border-radius: 50%;
		transition: all 0.2s ease;
		cursor: pointer;
	}
			
	.input-action-btn:hover {
		background: var(--wechat-light-green);
		color: var(--wechat-green);
	}
			
	.voice-record-btn {
		color: var(--wechat-green) !important;
	}
			
	.voice-record-btn:hover {
		background: var(--wechat-green) !important;
		color: white !important;
	}
			
	/* Voice Recording Interface */
	.voice-recording-interface {
		background: var(--wechat-green);
		color: white;
		padding: 16px;
		border-radius: 12px;
		margin: 8px;
		animation: slideInUp 0.3s ease;
	}
			
	.voice-recording-content {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 16px;
	}
			
	.voice-timer {
		font-size: 18px;
		font-weight: 600;
		min-width: 60px;
	}
			
	.voice-waveform {
		flex-grow: 1;
		display: flex;
		align-items: center;
		justify-content: center;
	}
			
	.waveform-bars {
		display: flex;
		align-items: end;
		gap: 3px;
		height: 30px;
	}
			
	.waveform-bars .bar {
		width: 4px;
		background: white;
		border-radius: 2px;
		transition: height 0.1s ease;
		height: 20%;
	}
			
	.voice-actions {
		display: flex;
		gap: 12px;
	}
			
	.voice-btn {
		width: 48px;
		height: 48px;
		border-radius: 50%;
		border: none;
		color: white;
		font-size: 18px;
		cursor: pointer;
		transition: all 0.2s ease;
	}
			
	.voice-btn.cancel {
		background: #ff4757;
	}
			
	.voice-btn.send {
		background: #2ecc71;
	}
			
	.voice-btn:hover {
		transform: scale(1.1);
	}
			
	/* Reply Preview */
	.reply-preview {
		background: var(--wechat-light-green);
		border: 1px solid var(--wechat-green);
		border-radius: 8px;
		padding: 8px 12px;
		margin: 8px;
		animation: slideInDown 0.3s ease;
	}
			
	.reply-content {
		position: relative;
	}
			
	.reply-header {
		display: flex;
		align-items: center;
		gap: 8px;
		margin-bottom: 4px;
		font-size: 14px;
		color: var(--wechat-green);
	}
			
	.reply-cancel {
		position: absolute;
		top: 0;
		right: 0;
		background: none;
		border: none;
		color: var(--wechat-text-light);
		cursor: pointer;
		padding: 4px;
	}
			
	.reply-text {
		font-size: 13px;
		color: var(--wechat-text);
		max-height: 40px;
		overflow: hidden;
		text-overflow: ellipsis;
	}
			
	/* Message Types */
	.voice-message {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 8px 12px;
		min-width: 120px;
	}
			
	.voice-play-btn {
		width: 36px;
		height: 36px;
		border-radius: 50%;
		border: none;
		background: rgba(255, 255, 255, 0.2);
		color: white;
		cursor: pointer;
		transition: all 0.2s ease;
	}
			
	.voice-play-btn:hover {
		background: rgba(255, 255, 255, 0.3);
		transform: scale(1.1);
	}
			
	.voice-duration {
		font-size: 12px;
		opacity: 0.8;
	}
			
	.file-message {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 12px;
		background: rgba(255, 255, 255, 0.1);
		border-radius: 8px;
		max-width: 250px;
	}
			
	.file-message i {
		font-size: 24px;
		color: var(--wechat-green);
	}
			
	.file-info {
		flex-grow: 1;
	}
			
	.file-name {
		font-size: 14px;
		font-weight: 500;
		margin-bottom: 2px;
	}
			
	.file-size {
		font-size: 12px;
		opacity: 0.7;
	}
			
	.file-download-btn {
		background: none;
		border: none;
		color: var(--wechat-green);
		cursor: pointer;
		padding: 4px;
		border-radius: 4px;
		transition: all 0.2s ease;
	}
			
	.file-download-btn:hover {
		background: rgba(255, 255, 255, 0.2);
	}
			
	.image-message img {
		max-width: 200px;
		max-height: 200px;
		border-radius: 8px;
		cursor: pointer;
		transition: all 0.2s ease;
	}
			
	.image-message img:hover {
		transform: scale(1.05);
	}
			
	/* Image Preview Modal */
	.image-preview-modal {
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: rgba(0, 0, 0, 0.9);
		z-index: 999999;
		display: flex;
		align-items: center;
		justify-content: center;
		cursor: pointer;
	}
			
	.image-preview-content {
		position: relative;
		max-width: 90%;
		max-height: 90%;
	}
			
	.image-preview-content img {
		max-width: 100%;
		max-height: 100%;
		object-fit: contain;
	}
			
	.image-preview-close {
		position: absolute;
		top: -40px;
		right: 0;
		color: white;
		font-size: 30px;
		cursor: pointer;
	}
			
	/* Reply Indicator */
	.reply-indicator {
		background: rgba(255, 255, 255, 0.1);
		padding: 4px 8px;
		border-radius: 4px;
		margin-bottom: 4px;
		font-size: 12px;
		opacity: 0.8;
		display: flex;
		align-items: center;
		gap: 4px;
	}
			
	/* Forward Modal */
	.forward-contacts-list {
		max-height: 300px;
		overflow-y: auto;
		border: 1px solid var(--wechat-border);
		border-radius: 8px;
		padding: 8px;
	}
			
	.forward-contact {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 8px;
		border-radius: 4px;
		transition: background-color 0.2s ease;
	}
			
	.forward-contact:hover {
		background: var(--wechat-light-green);
	}
			
	.forward-contact input {
		margin-right: 8px;
	}
			
	/* Call Interface */
	.call-interface {
		text-align: center;
		padding: 40px 20px;
	}
			
	.caller-avatar {
		width: 120px;
		height: 120px;
		border-radius: 50%;
		background: var(--wechat-green);
		display: flex;
		align-items: center;
		justify-content: center;
		margin: 0 auto 20px;
		font-size: 48px;
		color: white;
	}
			
	.call-controls {
		display: flex;
		justify-content: center;
		gap: 20px;
		margin-top: 30px;
	}
			
	.call-btn {
		width: 60px;
		height: 60px;
		border-radius: 50%;
		border: none;
		color: white;
		font-size: 24px;
		cursor: pointer;
		transition: all 0.2s ease;
	}
			
	.call-btn.mute {
		background: #3498db;
	}
			
	.call-btn.end {
		background: #e74c3c;
	}
			
	.call-btn:hover {
		transform: scale(1.1);
	}
			
	/* Message Edit Timing */
	.message-timestamp {
		font-size: 10px;
		opacity: 0.6;
		margin-top: 2px;
	}
			
	.edit-indicator {
		font-size: 10px;
		opacity: 0.6;
		font-style: italic;
		margin-left: 4px;
	}
			
	/* Animations */
	@keyframes slideInUp {
		from {
			transform: translateY(100%);
			opacity: 0;
		}
		to {
			transform: translateY(0);
			opacity: 1;
		}
	}
			
	@keyframes slideInDown {
		from {
			transform: translateY(-100%);
			opacity: 0;
		}
		to {
			transform: translateY(0);
			opacity: 1;
		}
	}
			
	/* Responsive Design */
	@media (max-width: 768px) {
		.wechat-input-group {
			padding: 8px;
		}
				
		.input-action-btn {
			padding: 6px;
		}
				
		.voice-recording-content {
			flex-direction: column;
			gap: 12px;
		}
				
		.caller-avatar {
			width: 80px;
			height: 80px;
			font-size: 32px;
		}
				
		.call-btn {
			width: 50px;
			height: 50px;
			font-size: 20px;
		}
	}
	</style>
	
	<!-- Voice / enhanced scripts (must load before call init) -->
	<script>
		window.__CHAT_USER_ID__ = <?php echo (int)$userId; ?>;
		window.__CHAT_PEER_ID__ = <?php echo (int)$peerId; ?>;
		window.__CHAT_PEER_NAME__ = <?php echo json_encode($peerUsername ?? '', JSON_UNESCAPED_UNICODE); ?>;
	</script>
	<script src="assets/js/voice-call.js?v=20260804b"></script>
	<script src="assets/js/enhanced-features.js?v=20260804b"></script>
	<script>
	(function initLineVoiceCall() {
		if (typeof VoiceCall === 'undefined') {
			console.error('VoiceCall missing');
			alert('Voice call script failed to load. Hard refresh (Ctrl+F5).');
			return;
		}

		const modalEl = document.getElementById('voiceCallModal');
		if (!modalEl) {
			console.error('voiceCallModal missing');
			return;
		}
		const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
		const callerNameEl = document.getElementById('callerName');
		const callStatusEl = document.getElementById('callStatus');
		const outgoingControls = document.getElementById('outgoingControls');
		const incomingControls = document.getElementById('incomingControls');
		let pendingIncoming = null;

		function showOutgoingUI() {
			if (outgoingControls) outgoingControls.style.display = 'flex';
			if (incomingControls) incomingControls.style.setProperty('display', 'none', 'important');
		}
		function showIncomingUI() {
			if (outgoingControls) outgoingControls.style.display = 'none';
			if (incomingControls) incomingControls.style.setProperty('display', 'flex', 'important');
		}
		function setStatus(text) {
			if (callStatusEl) callStatusEl.textContent = text;
		}

		window.lineVoiceCall = new VoiceCall({
			userId: window.__CHAT_USER_ID__,
			onOutgoing: function (data) {
				callerNameEl.textContent = data.peer_name || 'Friend';
				setStatus('Calling...');
				showOutgoingUI();
				modal.show();
			},
			onIncoming: function (call) {
				pendingIncoming = call;
				callerNameEl.textContent = call.caller_name || 'Incoming call';
				setStatus('Incoming voice call...');
				showIncomingUI();
				modal.show();
			},
			onConnected: function () {
				setStatus('Connected');
				showOutgoingUI();
			},
			onStatus: setStatus,
			onEnded: function (data) {
				pendingIncoming = null;
				modal.hide();
				const dur = data && data.duration ? ' (' + data.duration + 's)' : '';
				if (typeof showMessage === 'function') showMessage('Call ended' + dur, 'info');
			},
			onError: function (err) {
				setStatus(String(err));
				if (typeof showMessage === 'function') showMessage(String(err), 'error');
				console.error('[Call]', err);
			}
		});

		window.startLineCall = async function () {
			const peer = Number(window.__CHAT_PEER_ID__) || Number(new URLSearchParams(location.search).get('user_id')) || 0;
			const name = (window.__CHAT_PEER_NAME__ || document.querySelector('.wechat-chat-title')?.textContent || 'Friend').trim();
			console.log('[Call] startLineCall', peer, name);
			if (!peer) {
				alert('Open a friend chat first, then press Call.');
				return;
			}
			await window.lineVoiceCall.startCall(peer, name);
		};

		// Bind phone button (strip old listeners)
		const oldBtn = document.getElementById('voiceCall');
		if (oldBtn) {
			const btn = oldBtn.cloneNode(true);
			oldBtn.parentNode.replaceChild(btn, oldBtn);
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				window.startLineCall();
			});
			btn.setAttribute('title', 'Voice Call');
			console.log('[Call] button bound');
		} else {
			console.error('[Call] #voiceCall button not found');
		}

		document.getElementById('endCall')?.addEventListener('click', function () {
			window.lineVoiceCall.endCall();
		});
		document.getElementById('muteCall')?.addEventListener('click', function () {
			const muted = window.lineVoiceCall.toggleMute();
			this.innerHTML = muted ? '<i class="fas fa-microphone-slash"></i>' : '<i class="fas fa-microphone"></i>';
		});
		document.getElementById('acceptCall')?.addEventListener('click', async function () {
			if (!pendingIncoming) return;
			const call = pendingIncoming;
			pendingIncoming = null;
			showOutgoingUI();
			await window.lineVoiceCall.acceptIncoming(call);
		});
		document.getElementById('declineCall')?.addEventListener('click', async function () {
			if (pendingIncoming) {
				await window.lineVoiceCall.declineIncoming(pendingIncoming.id);
				pendingIncoming = null;
			}
			modal.hide();
		});

		console.log('[Call] init complete', {
			user: window.__CHAT_USER_ID__,
			peer: window.__CHAT_PEER_ID__
		});
	})();
	</script>
	<script>
		// Optional extras only (no voice calling)
		document.addEventListener('DOMContentLoaded', () => {
			if (window.EnhancedChatFeatures && !window.enhancedChat) {
				window.enhancedChat = new EnhancedChatFeatures({
					userId: window.__CHAT_USER_ID__,
					peerId: window.__CHAT_PEER_ID__,
					chatType: 'private'
				});
			}
		});
	</script>
	
	<!-- Feature Diagnostic Script -->
	<script>
		console.log('=== CHAT FEATURES DIAGNOSTIC ===');
		console.log('VoiceCall class:', typeof VoiceCall !== 'undefined' ? 'OK' : 'MISSING');
		console.log('lineVoiceCall:', window.lineVoiceCall ? 'OK' : 'MISSING');
		console.log('startLineCall:', typeof window.startLineCall);
		console.log('peer:', window.__CHAT_PEER_ID__);
	</script>
	<?php endif; ?>
</div>
	</div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
