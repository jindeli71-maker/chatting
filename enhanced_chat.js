// Enhanced Chat JavaScript for WhatsApp-like features
let currentPeerId = null;
let lastMessageId = 0;
let voiceRecorder = null;
let isTyping = false;
let typingTimer = null;
let pollInterval = null;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeChat();
    setupEventListeners();
    
    // Initialize voice recorder
    if (typeof VoiceRecorder !== 'undefined') {
        voiceRecorder = new VoiceRecorder({
            onSendSuccess: (result) => {
                console.log('Voice message sent:', result);
                fetchMessages();
            },
            onSendError: (error) => {
                console.error('Voice message error:', error);
            }
        });
    }
});

function initializeChat() {
    // Get current peer from URL or page
    const urlParams = new URLSearchParams(window.location.search);
    const peerId = urlParams.get('user_id');
    
    if (peerId) {
        currentPeerId = parseInt(peerId);
        if (voiceRecorder) {
            voiceRecorder.updateChatSettings(currentPeerId, 'private');
        }
        startChatPolling();
    }
}

function setupEventListeners() {
    // Message form
    const sendForm = document.getElementById('sendForm');
    if (sendForm) {
        sendForm.addEventListener('submit', function(e) {
            e.preventDefault();
            sendMessage();
        });
    }

    // File attachments
    const attachFileBtn = document.getElementById('attachFileBtn');
    if (attachFileBtn) {
        attachFileBtn.addEventListener('click', () => {
            const fileInput = document.getElementById('fileInput') || createFileInput();
            fileInput.click();
        });
    }

    // Voice message button
    const voiceBtn = document.getElementById('voiceMessageBtn');
    if (voiceBtn && voiceRecorder) {
        voiceBtn.addEventListener('click', () => voiceRecorder.toggleRecording());
    }

    // Reply cancellation
    const cancelReply = document.getElementById('cancelReply');
    if (cancelReply) {
        cancelReply.addEventListener('click', () => cancelReplyMessage());
    }

    // Typing detection
    const messageInput = document.getElementById('msg');
    if (messageInput) {
        messageInput.addEventListener('input', handleTyping);
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendMessage();
            }
        });
    }
}

function createFileInput() {
    const input = document.createElement('input');
    input.type = 'file';
    input.id = 'fileInput';
    input.style.display = 'none';
    input.accept = '.pdf,.doc,.docx,.txt,.zip,.rar,image/*';
    input.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            uploadFile(e.target.files[0]);
        }
    });
    document.body.appendChild(input);
    return input;
}

function startChatPolling() {
    if (pollInterval) clearInterval(pollInterval);
    
    fetchMessages();
    pollInterval = setInterval(fetchMessages, 2000);
}

async function fetchMessages() {
    if (!currentPeerId) return;

    try {
        const response = await fetch(`chat_api.php?action=fetch&peer_id=${currentPeerId}&after_id=${lastMessageId}`);
        const data = await response.json();
        
        if (data.messages && data.messages.length > 0) {
            renderMessages(data.messages);
            data.messages.forEach(msg => {
                lastMessageId = Math.max(lastMessageId, parseInt(msg.id));
            });
        }
        
        checkTypingStatus();
    } catch (error) {
        console.error('Error fetching messages:', error);
    }
}

function renderMessages(messages) {
    const container = document.getElementById('messages');
    if (!container) return;

    messages.forEach(message => {
        if (container.querySelector(`[data-message-id="${message.id}"]`)) {
            return; // Message already exists
        }

        const messageElement = createMessageElement(message);
        container.appendChild(messageElement);
    });

    container.scrollTop = container.scrollHeight;
}

function createMessageElement(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `wechat-message ${message.sender === 'me' ? 'sent' : 'received'} fade-in`;
    messageDiv.setAttribute('data-message-id', message.id);
    
    let content = '';
    
    // Reply section
    if (message.reply_to) {
        content += `
            <div class="message-reply">
                <div class="reply-author">${escapeHtml(message.reply_to.sender)}</div>
                <div class="reply-text">${escapeHtml(message.reply_to.text || '[File]')}</div>
            </div>
        `;
    }
    
    // Message content based on type
    if (message.type === 'image' && message.file) {
        content += `
            <div class="image-message">
                <img src="serve_file.php?file=${message.file.path}" alt="Image" loading="lazy">
            </div>
        `;
    } else if (message.type === 'voice' && message.file) {
        content += `<div class="voice-message" data-voice='${JSON.stringify(message.file)}'></div>`;
    } else if (message.type === 'file' && message.file) {
        content += `
            <div class="file-message">
                <div class="file-icon"><i class="fas fa-file"></i></div>
                <div class="file-info">
                    <div class="file-name">${escapeHtml(message.file.name)}</div>
                    <div class="file-size">${formatFileSize(message.file.size)}</div>
                </div>
                <button class="file-download" onclick="downloadFile('${message.file.path}', '${message.file.name}')">
                    <i class="fas fa-download"></i>
                </button>
            </div>
        `;
    }
    
    if (message.text) {
        content += `<div class="message-text">${escapeHtml(message.text)}</div>`;
    }
    
    messageDiv.innerHTML = `
        <div class="wechat-message-container">
            <div class="wechat-message-bubble">
                ${content}
            </div>
            <div class="message-actions">
                <button class="message-action-btn" onclick="replyToMessage('${message.id}', '${escapeHtml(message.text)}', '${message.sender}')" title="Reply">
                    <i class="fas fa-reply"></i>
                </button>
                <button class="message-action-btn" onclick="showReactionPicker('${message.id}')" title="React">
                    <i class="fas fa-smile"></i>
                </button>
                ${message.sender === 'me' ? `
                    <button class="message-action-btn" onclick="editMessage('${message.id}', '${escapeHtml(message.text)}')" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="message-action-btn" onclick="deleteMessage('${message.id}')" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                ` : ''}
            </div>
            <div class="message-info">
                <span class="message-time">${message.time}</span>
                ${message.edited ? '<span class="message-edited">(edited)</span>' : ''}
                ${message.sender === 'me' ? `
                    <div class="message-status ${message.delivery_status}">
                        <i class="fas ${message.delivery_status === 'read' ? 'fa-check-double' : 'fa-check'} status-icon"></i>
                    </div>
                ` : ''}
            </div>
            <div class="message-reactions" id="reactions-${message.id}">
                ${renderReactions(message.reactions || [])}
            </div>
        </div>
    `;
    
    // Initialize voice players
    if (message.type === 'voice' && message.file && typeof VoicePlayer !== 'undefined') {
        setTimeout(() => {
            const voiceElement = messageDiv.querySelector('.voice-message');
            if (voiceElement) {
                new VoicePlayer(voiceElement, message.file);
            }
        }, 100);
    }
    
    return messageDiv;
}

async function sendMessage() {
    const input = document.getElementById('msg');
    const text = input.value.trim();
    const replyToId = document.getElementById('replyToMessageId')?.value;
    
    if (!text || !currentPeerId) return;
    
    input.value = '';
    cancelReplyMessage();
    
    try {
        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('peer_id', currentPeerId);
        formData.append('text', text);
        if (replyToId) {
            formData.append('reply_to_id', replyToId);
        }
        
        const response = await fetch('chat_api.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.ok) {
            if (result.blocked) {
                showBlockedMessage(result.message);
            } else {
                setTimeout(fetchMessages, 500);
            }
        } else {
            alert('Failed to send message: ' + result.error);
        }
    } catch (error) {
        console.error('Error sending message:', error);
        alert('Error sending message');
    }
}

async function uploadFile(file) {
    if (!currentPeerId) {
        alert('Please select a friend first');
        return;
    }
    
    const category = file.type.startsWith('image/') ? 'image' : 'file';
    const formData = new FormData();
    formData.append('action', 'upload');
    formData.append('file', file);
    formData.append('category', category);
    formData.append('chat_type', 'private');
    formData.append('chat_id', currentPeerId);
    
    try {
        const response = await fetch('file_upload_api.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            setTimeout(fetchMessages, 500);
        } else {
            alert('Failed to upload file: ' + result.error);
        }
    } catch (error) {
        console.error('Error uploading file:', error);
        alert('Error uploading file');
    }
}

function replyToMessage(messageId, messageText, sender) {
    const replyIdInput = document.getElementById('replyToMessageId');
    const replyPreview = document.getElementById('replyPreview');
    const replyToUser = document.getElementById('replyToUser');
    const replyToText = document.getElementById('replyToText');
    
    if (replyIdInput) replyIdInput.value = messageId;
    if (replyToUser) replyToUser.textContent = sender === 'me' ? 'You' : sender;
    if (replyToText) replyToText.textContent = messageText || '[File]';
    if (replyPreview) replyPreview.style.display = 'block';
    
    const messageInput = document.getElementById('msg');
    if (messageInput) messageInput.focus();
}

function cancelReplyMessage() {
    const replyIdInput = document.getElementById('replyToMessageId');
    const replyPreview = document.getElementById('replyPreview');
    
    if (replyIdInput) replyIdInput.value = '';
    if (replyPreview) replyPreview.style.display = 'none';
}

function handleTyping() {
    if (!currentPeerId) return;
    
    if (!isTyping) {
        isTyping = true;
        sendTypingStatus(true);
    }
    
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        isTyping = false;
        sendTypingStatus(false);
    }, 2000);
}

async function sendTypingStatus(typing) {
    try {
        const formData = new FormData();
        formData.append('action', 'typing');
        formData.append('peer_id', currentPeerId);
        formData.append('is_typing', typing);
        
        await fetch('chat_api.php', {
            method: 'POST',
            body: formData
        });
    } catch (error) {
        console.error('Error sending typing status:', error);
    }
}

async function checkTypingStatus() {
    if (!currentPeerId) return;
    
    try {
        const response = await fetch(`chat_api.php?action=get_typing&peer_id=${currentPeerId}`);
        const data = await response.json();
        
        const typingIndicator = document.getElementById('typingIndicator');
        if (typingIndicator) {
            typingIndicator.style.display = data.typing ? 'flex' : 'none';
        }
    } catch (error) {
        console.error('Error checking typing status:', error);
    }
}

// Utility functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function renderReactions(reactions) {
    return reactions.map(reaction => `
        <div class="message-reaction ${reaction.user_reacted ? 'user-reacted' : ''}" 
             onclick="toggleReaction('${reaction.message_id}', '${reaction.reaction}')">
            <span class="emoji">${reaction.reaction}</span>
            <span class="count">${reaction.count}</span>
        </div>
    `).join('');
}

function downloadFile(filePath, fileName) {
    const link = document.createElement('a');
    link.href = `serve_file.php?file=${filePath}`;
    link.download = fileName;
    link.click();
}

function showBlockedMessage(message) {
    const container = document.getElementById('messages');
    if (!container) return;
    
    const blockedDiv = document.createElement('div');
    blockedDiv.className = 'blocked-message fade-in';
    blockedDiv.innerHTML = `
        <div class="blocked-indicator">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="blocked-text">${message}</div>
    `;
    
    container.appendChild(blockedDiv);
    container.scrollTop = container.scrollHeight;
    
    setTimeout(() => {
        if (blockedDiv.parentNode) {
            blockedDiv.remove();
        }
    }, 5000);
}

// Message actions (to be implemented)
function showReactionPicker(messageId) {
    console.log('Show reaction picker for message:', messageId);
}

function editMessage(messageId, text) {
    console.log('Edit message:', messageId, text);
}

function deleteMessage(messageId) {
    if (confirm('Are you sure you want to delete this message?')) {
        console.log('Delete message:', messageId);
    }
}

function toggleReaction(messageId, reaction) {
    console.log('Toggle reaction:', messageId, reaction);
}