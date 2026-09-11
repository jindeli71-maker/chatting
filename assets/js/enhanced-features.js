/**
 * Enhanced Chat Features Integration
 * Combines all new messaging features into a cohesive system
 */

// Debug logging
function debugLog(message, data = null) {
    console.log(`[EnhancedChat] ${message}`, data || '');
}

class EnhancedChatFeatures {
    constructor(options = {}) {
        debugLog('Initializing EnhancedChatFeatures', options);
        
        this.options = {
            userId: null,
            peerId: null,
            chatType: 'private', // private or group
            debug: true,
            ...options
        };

        this.voiceRecorder = null;
        this.voiceCall = null;
        this.currentReplyTo = null;
        this.mentionAutocomplete = null;
        this.reactionPicker = null;
        this.contextMenu = null;
        this.editingMessage = null;
        this.lastMessageTimestamp = Date.now();

        // Check if essential DOM elements exist
        this.checkDOMElements();
        
        this.init();
    }
    
    checkDOMElements() {
        const requiredElements = {
            'recordVoice': 'Voice recording button',
            'attachFile': 'File attachment button', 
            'emojiBtn': 'Emoji button (Win + .)',
            'voiceRecording': 'Voice recording interface',
            'replyPreview': 'Reply preview',
            'msg': 'Message input'
        };
        
        debugLog('Checking DOM elements...');
        for (const [id, description] of Object.entries(requiredElements)) {
            const element = document.getElementById(id);
            if (element) {
                debugLog(`✅ Found ${description}`);
            } else {
                debugLog(`❌ Missing ${description} (ID: ${id})`);
            }
        }
    }

    init() {
        debugLog('Starting initialization of enhanced chat features...');
        
        try {
            this.initializeVoiceRecording();
            debugLog('✅ Voice recording initialized');
        } catch (error) {
            debugLog('❌ Voice recording initialization failed', error);
        }
        
        try {
            this.initializeVoiceCalling();
            debugLog('✅ Voice calling initialized');
        } catch (error) {
            debugLog('❌ Voice calling initialization failed', error);
        }
        
        try {
            this.initializeFileUpload();
            debugLog('✅ File upload initialized');
        } catch (error) {
            debugLog('❌ File upload initialization failed', error);
        }
        
        try {
            this.initializeMessageActions();
            debugLog('✅ Message actions initialized');
        } catch (error) {
            debugLog('❌ Message actions initialization failed', error);
        }
        
        try {
            this.initializeMentions();
            debugLog('✅ Mentions initialized');
        } catch (error) {
            debugLog('❌ Mentions initialization failed', error);
        }
        
        try {
            this.initializeReactions();
            debugLog('✅ Reactions initialized');
        } catch (error) {
            debugLog('❌ Reactions initialization failed', error);
        }
        
        try {
            this.initializeContextMenu();
            debugLog('✅ Context menu initialized');
        } catch (error) {
            debugLog('❌ Context menu initialization failed', error);
        }
        
        try {
            this.setupEventListeners();
            debugLog('✅ Event listeners set up');
        } catch (error) {
            debugLog('❌ Event listeners setup failed', error);
        }
        
        debugLog('✅ Enhanced chat features initialization complete!');
    }

    // Voice Recording
    initializeVoiceRecording() {
        debugLog('Initializing voice recording...');
        
        const recordBtn = document.getElementById('recordVoice');
        const cancelBtn = document.getElementById('cancelVoice');
        const sendBtn = document.getElementById('sendVoice');

        if (recordBtn) {
            debugLog('✅ Voice record button found, adding event listener');
            recordBtn.addEventListener('click', () => {
                debugLog('Voice record button clicked');
                this.startVoiceRecording();
            });
        } else {
            debugLog('❌ Voice record button not found');
        }
        
        if (cancelBtn) {
            debugLog('✅ Voice cancel button found');
            cancelBtn.addEventListener('click', () => {
                debugLog('Voice cancel button clicked');
                this.cancelVoiceRecording();
            });
        } else {
            debugLog('❌ Voice cancel button not found');
        }
        
        if (sendBtn) {
            debugLog('✅ Voice send button found');
            sendBtn.addEventListener('click', () => {
                debugLog('Voice send button clicked');
                this.sendVoiceMessage();
            });
        } else {
            debugLog('❌ Voice send button not found');
        }

        // Initialize voice recorder if supported
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            debugLog('✅ Voice recording supported by browser');
            this.voiceRecorder = {
                mediaRecorder: null,
                audioChunks: [],
                isRecording: false,
                startTime: null,
                timer: null
            };
        } else {
            debugLog('❌ Voice recording not supported by browser');
            // Disable voice recording button if not supported
            if (recordBtn) {
                recordBtn.disabled = true;
                recordBtn.title = 'Voice recording not supported by your browser';
            }
        }
    }

    async startVoiceRecording() {
        try {
            if (!this.voiceRecorder) {
                throw new Error('Voice recording not supported');
            }

            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.voiceRecorder.mediaRecorder = new MediaRecorder(stream);
            this.voiceRecorder.audioChunks = [];
            this.voiceRecorder.isRecording = true;
            this.voiceRecorder.startTime = Date.now();

            this.voiceRecorder.mediaRecorder.ondataavailable = (event) => {
                this.voiceRecorder.audioChunks.push(event.data);
            };

            this.voiceRecorder.mediaRecorder.onstop = () => {
                stream.getTracks().forEach(track => track.stop());
            };

            this.voiceRecorder.mediaRecorder.start();
            this.showVoiceRecordingInterface();
            this.startVoiceTimer();

            // Auto-stop after 5 minutes
            setTimeout(() => {
                if (this.voiceRecorder.isRecording) {
                    this.stopVoiceRecording();
                }
            }, 300000);

        } catch (error) {
            console.error('Error starting voice recording:', error);
            this.showMessage('Failed to start voice recording', 'error');
        }
    }

    stopVoiceRecording() {
        if (this.voiceRecorder && this.voiceRecorder.isRecording) {
            this.voiceRecorder.mediaRecorder.stop();
            this.voiceRecorder.isRecording = false;
            this.stopVoiceTimer();
        }
    }

    cancelVoiceRecording() {
        this.stopVoiceRecording();
        this.voiceRecorder.audioChunks = [];
        this.hideVoiceRecordingInterface();
    }

    async sendVoiceMessage() {
        if (!this.voiceRecorder || this.voiceRecorder.audioChunks.length === 0) {
            return;
        }

        const audioBlob = new Blob(this.voiceRecorder.audioChunks, { type: 'audio/webm' });
        const duration = Math.floor((Date.now() - this.voiceRecorder.startTime) / 1000);

        const formData = new FormData();
        formData.append('voice', audioBlob, 'voice_message.webm');
        formData.append('peer_id', this.options.peerId);
        formData.append('duration', duration);
        formData.append('action', 'send_voice');

        try {
            const response = await fetch('voice_api.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                this.showMessage('Voice message sent', 'success');
                this.refreshMessages();
            } else {
                throw new Error(data.error || 'Failed to send voice message');
            }
        } catch (error) {
            console.error('Error sending voice message:', error);
            this.showMessage('Failed to send voice message', 'error');
        } finally {
            this.hideVoiceRecordingInterface();
            this.voiceRecorder.audioChunks = [];
        }
    }

    showVoiceRecordingInterface() {
        document.getElementById('voiceRecording').style.display = 'block';
        document.getElementById('sendForm').style.display = 'none';
    }

    hideVoiceRecordingInterface() {
        document.getElementById('voiceRecording').style.display = 'none';
        document.getElementById('sendForm').style.display = 'flex';
    }

    startVoiceTimer() {
        const timerElement = document.querySelector('.voice-timer');
        this.voiceRecorder.timer = setInterval(() => {
            const elapsed = Math.floor((Date.now() - this.voiceRecorder.startTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            if (timerElement) {
                timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }
        }, 1000);
    }

    stopVoiceTimer() {
        if (this.voiceRecorder.timer) {
            clearInterval(this.voiceRecorder.timer);
            this.voiceRecorder.timer = null;
        }
    }

    // Voice Calling — handled by setupLineVoiceCall() in chat.php (avoid double-bind)
    initializeVoiceCalling() {
        debugLog('skipped - line voice call handles this');
    }

    async startVoiceCall() {
        return;
    }

    // File Upload
    initializeFileUpload() {
        debugLog('Initializing file upload...');
        
        const attachFileBtn = document.getElementById('attachFile');
        const fileInput = document.getElementById('fileInput');
        const imageInput = document.getElementById('imageInput');

        if (attachFileBtn && fileInput) {
            debugLog('✅ File attachment elements found');
            // Only the + button opens the folder — never the smile/emoji button
            attachFileBtn.addEventListener('click', (e) => {
                if (e.target.closest('#emojiBtn') || e.target.closest('.fa-smile')) return;
                debugLog('File attach button clicked');
                fileInput.click();
            });
            fileInput.addEventListener('change', (e) => {
                debugLog('File input changed', e.target.files);
                this.handleFileUpload(e, 'file');
            });
        } else {
            debugLog('❌ File attachment elements missing', {
                attachFileBtn: !!attachFileBtn,
                fileInput: !!fileInput
            });
        }

        // Image file input change only — never bind the smile/emoji button to it
        if (imageInput) {
            imageInput.addEventListener('change', (e) => {
                debugLog('Image input changed', e.target.files);
                this.handleFileUpload(e, 'image');
            });
        }
    }

    async handleFileUpload(event, category) {
        const files = event.target.files;
        if (!files || files.length === 0) return;

        for (let file of files) {
            await this.uploadFile(file, category);
        }

        // Clear the input
        event.target.value = '';
    }

    async uploadFile(file, category) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('category', category);
        formData.append('chat_type', this.options.chatType);
        formData.append('chat_id', this.options.peerId);
        formData.append('action', 'upload');

        try {
            // Show upload progress
            this.showUploadProgress(file);

            const response = await fetch('file_upload_api.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (data.success) {
                this.showMessage('File uploaded successfully', 'success');
                this.refreshMessages();
            } else {
                throw new Error(data.error || 'Upload failed');
            }
        } catch (error) {
            console.error('Upload error:', error);
            this.showMessage(`Upload failed: ${error.message}`, 'error');
        } finally {
            this.hideUploadProgress();
        }
    }

    showUploadProgress(file) {
        // Implementation for upload progress UI
        console.log('Uploading file:', file.name);
    }

    hideUploadProgress() {
        // Implementation to hide upload progress
        console.log('Upload completed');
    }

    // Message Actions (Reply, Edit, Delete, Pin)
    initializeMessageActions() {
        // Reply functionality
        const cancelReplyBtn = document.getElementById('cancelReply');
        if (cancelReplyBtn) {
            cancelReplyBtn.addEventListener('click', () => this.cancelReply());
        }
    }

    replyToMessage(messageId, messageText, senderName) {
        this.currentReplyTo = { messageId, messageText, senderName };
        
        document.getElementById('replyToMessageId').value = messageId;
        document.getElementById('replyToUser').textContent = senderName;
        document.getElementById('replyToText').textContent = messageText;
        document.getElementById('replyPreview').style.display = 'block';
        document.getElementById('msg').focus();
    }

    cancelReply() {
        this.currentReplyTo = null;
        document.getElementById('replyToMessageId').value = '';
        document.getElementById('replyPreview').style.display = 'none';
    }

    async editMessage(messageId, currentText) {
        const newText = prompt('Edit message:', currentText);
        if (!newText || newText === currentText) return;

        try {
            const response = await fetch('message_management_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'edit_message',
                    message_id: messageId,
                    new_text: newText,
                    message_type: this.options.chatType
                })
            });

            const data = await response.json();
            if (data.success) {
                this.showMessage('Message edited', 'success');
                this.refreshMessages();
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            this.showMessage(error.message, 'error');
        }
    }

    async deleteMessage(messageId, deleteType = 'for_me') {
        if (!confirm('Are you sure you want to delete this message?')) return;

        try {
            const response = await fetch('message_management_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'delete_message',
                    message_id: messageId,
                    message_type: this.options.chatType,
                    delete_type: deleteType
                })
            });

            const data = await response.json();
            if (data.success) {
                this.showMessage('Message deleted', 'success');
                this.refreshMessages();
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            this.showMessage(error.message, 'error');
        }
    }

    async pinMessage(messageId) {
        try {
            const response = await fetch('message_management_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'pin_message',
                    message_id: messageId,
                    message_type: this.options.chatType,
                    chat_id: this.options.peerId
                })
            });

            const data = await response.json();
            if (data.success) {
                this.showMessage(`Message ${data.action}`, 'success');
                this.refreshMessages();
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            this.showMessage(error.message, 'error');
        }
    }

    // Mentions
    initializeMentions() {
        const messageInput = document.getElementById('msg');
        if (messageInput && this.options.chatType === 'group') {
            messageInput.addEventListener('input', (e) => this.handleMentionInput(e));
            messageInput.addEventListener('keydown', (e) => this.handleMentionKeydown(e));
        }
    }

    handleMentionInput(event) {
        const input = event.target;
        const text = input.value;
        const cursorPos = input.selectionStart;
        
        // Find @ symbol before cursor
        const textBeforeCursor = text.substring(0, cursorPos);
        const lastAtIndex = textBeforeCursor.lastIndexOf('@');
        
        if (lastAtIndex !== -1) {
            const query = textBeforeCursor.substring(lastAtIndex + 1);
            if (query.length > 0 && !query.includes(' ')) {
                this.showMentionAutocomplete(query, lastAtIndex);
            } else {
                this.hideMentionAutocomplete();
            }
        } else {
            this.hideMentionAutocomplete();
        }
    }

    async showMentionAutocomplete(query, position) {
        try {
            const response = await fetch('mentions_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'get_group_members',
                    group_id: this.options.peerId,
                    query: query
                })
            });

            const data = await response.json();
            if (data.success && data.members && data.members.length > 0) {
                this.renderMentionAutocomplete(data.members, position);
            } else {
                this.hideMentionAutocomplete();
            }
        } catch (error) {
            console.error('Error fetching mentions:', error);
        }
    }

    renderMentionAutocomplete(members, position) {
        // Implementation for mention autocomplete UI
        console.log('Show mention autocomplete for:', members);
    }

    hideMentionAutocomplete() {
        // Implementation to hide mention autocomplete
        const autocomplete = document.querySelector('.mention-autocomplete');
        if (autocomplete) {
            autocomplete.remove();
        }
    }

    // Reactions
    initializeReactions() {
        // Reaction picker will be shown via context menu
        this.availableReactions = ['👍', '❤️', '😂', '😮', '😢', '😡', '🙏', '👏', '🔥', '💯', '🤔', '😍'];
    }

    async toggleReaction(messageId, reaction) {
        try {
            const response = await fetch('reactions_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'toggle_reaction',
                    message_id: messageId,
                    reaction: reaction,
                    message_type: this.options.chatType
                })
            });

            const data = await response.json();
            if (data.success) {
                this.refreshMessages();
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            this.showMessage(error.message, 'error');
        }
    }

    showReactionPicker(messageId, element) {
        if (this.reactionPicker) {
            this.hideReactionPicker();
        }

        this.reactionPicker = document.createElement('div');
        this.reactionPicker.className = 'reaction-picker';
        
        this.availableReactions.forEach(reaction => {
            const btn = document.createElement('button');
            btn.className = 'reaction-option';
            btn.textContent = reaction;
            btn.addEventListener('click', () => {
                this.toggleReaction(messageId, reaction);
                this.hideReactionPicker();
            });
            this.reactionPicker.appendChild(btn);
        });

        element.style.position = 'relative';
        element.appendChild(this.reactionPicker);
    }

    hideReactionPicker() {
        if (this.reactionPicker) {
            this.reactionPicker.remove();
            this.reactionPicker = null;
        }
    }

    // Context Menu
    initializeContextMenu() {
        document.addEventListener('click', () => this.hideContextMenu());
        document.addEventListener('contextmenu', (e) => {
            // Prevent default context menu on message elements
            if (e.target.closest('.wechat-message')) {
                e.preventDefault();
            }
        });
    }

    showContextMenu(event, messageId, messageText, messageTimestamp, isOwnMessage) {
        event.preventDefault();
        
        this.hideContextMenu();
        
        const now = Date.now();
        const twoMinutesAgo = now - (2 * 60 * 1000);
        const canEditDelete = isOwnMessage && messageTimestamp > twoMinutesAgo;
        
        this.contextMenu = document.createElement('div');
        this.contextMenu.className = 'context-menu';
        this.contextMenu.style.left = event.pageX + 'px';
        this.contextMenu.style.top = event.pageY + 'px';
        
        const menuItems = [
            { icon: 'fa-reply', text: 'Reply', action: () => this.replyToMessage(messageId, messageText, 'User') },
            { icon: 'fa-smile', text: 'React', action: () => this.showReactionPicker(messageId, event.target.closest('.wechat-message')) },
            { icon: 'fa-copy', text: 'Copy', action: () => this.copyToClipboard(messageText) },
            { icon: 'fa-share', text: 'Forward', action: () => this.forwardMessage(messageId) },
            { icon: 'fa-thumbtack', text: 'Pin', action: () => this.pinMessage(messageId) },
            { icon: 'fa-star', text: 'Favorite', action: () => this.favoriteMessage(messageId) }
        ];
        
        if (canEditDelete) {
            menuItems.push(
                { icon: 'fa-edit', text: 'Edit', action: () => this.editMessage(messageId, messageText) },
                { icon: 'fa-trash', text: 'Delete', action: () => this.deleteMessage(messageId) }
            );
        }
        
        menuItems.forEach(item => {
            const menuItem = document.createElement('div');
            menuItem.className = 'context-menu-item';
            menuItem.innerHTML = `<i class=\"fas ${item.icon}\"></i> ${item.text}`;
            menuItem.addEventListener('click', (e) => {
                e.stopPropagation();
                item.action();
                this.hideContextMenu();
            });
            this.contextMenu.appendChild(menuItem);
        });
        
        document.body.appendChild(this.contextMenu);
    }

    hideContextMenu() {
        if (this.contextMenu) {
            this.contextMenu.remove();
            this.contextMenu = null;
        }
    }

    // Utility Functions
    copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            this.showMessage('Text copied to clipboard', 'success');
        }).catch(() => {
            this.showMessage('Failed to copy text', 'error');
        });
    }

    async forwardMessage(messageId) {
        // Implementation for message forwarding
        console.log('Forward message:', messageId);
    }

    async favoriteMessage(messageId) {
        try {
            const response = await fetch('message_management_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'add_favorite',
                    message_id: messageId,
                    message_type: this.options.chatType
                })
            });

            const data = await response.json();
            if (data.success) {
                this.showMessage(data.message, 'success');
            } else {
                throw new Error(data.error);
            }
        } catch (error) {
            this.showMessage(error.message, 'error');
        }
    }

    formatDuration(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }

    showMessage(message, type = 'info') {
        debugLog(`Showing message: ${message} (${type})`);
        
        // Create notification
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'error' ? '#ff6b6b' : type === 'success' ? '#51cf66' : '#339af0'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            font-size: 14px;
            max-width: 300px;
            word-wrap: break-word;
            animation: slideIn 0.3s ease-out;
        `;
        notification.textContent = message;
        
        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        if (!document.querySelector('style[data-notification-styles]')) {
            style.setAttribute('data-notification-styles', 'true');
            document.head.appendChild(style);
        }
        
        // Add to page
        document.body.appendChild(notification);
        
        // Auto-remove after 4 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }
        }, 4000);
        
        // Add slideOut animation
        if (!document.querySelector('style[data-slideout-styles]')) {
            const slideOutStyle = document.createElement('style');
            slideOutStyle.setAttribute('data-slideout-styles', 'true');
            slideOutStyle.textContent = `
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(slideOutStyle);
        }
    }

    async refreshMessages() {
        // Trigger message refresh
        if (typeof fetchMessages === 'function') {
            fetchMessages();
        }
    }

    setupEventListeners() {
        // Setup delegation for message actions
        document.addEventListener('click', (e) => {
            // Handle message context menu
            if (e.target.closest('.wechat-message') && e.button === 2) {
                const messageElement = e.target.closest('.wechat-message');
                const messageId = messageElement.dataset.messageId;
                const messageText = messageElement.querySelector('.message-text')?.textContent || '';
                const messageTimestamp = parseInt(messageElement.dataset.timestamp);
                const isOwnMessage = messageElement.classList.contains('sent');
                
                this.showContextMenu(e, messageId, messageText, messageTimestamp, isOwnMessage);
            }
        });
        
        // Handle right-click on messages
        document.addEventListener('contextmenu', (e) => {
            if (e.target.closest('.wechat-message')) {
                const messageElement = e.target.closest('.wechat-message');
                const messageId = messageElement.dataset.messageId;
                const messageText = messageElement.querySelector('.message-text')?.textContent || '';
                const messageTimestamp = parseInt(messageElement.dataset.timestamp);
                const isOwnMessage = messageElement.classList.contains('sent');
                
                this.showContextMenu(e, messageId, messageText, messageTimestamp, isOwnMessage);
            }
        });
    }
}

// Auto-init only when chat.php has not already created an instance with user/peer IDs.
// Skip if IDs are missing — avoids a second EnhancedChatFeatures that would re-bind controls.
document.addEventListener('DOMContentLoaded', () => {
    if (window.enhancedChat) {
        debugLog('skipped auto-init - EnhancedChatFeatures already created by page');
        return;
    }
    debugLog('skipped auto-init - waiting for page to pass userId/peerId');
});
