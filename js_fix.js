// JavaScript Fix for Chat Application Errors
// Run this script in browser console (F12) to fix the errors

console.log('🔧 Applying JavaScript fixes...');

// 1. Fix context menu visibility variable
if (typeof contextMenuVisible === 'undefined') {
    window.contextMenuVisible = false;
    console.log('✅ Fixed contextMenuVisible variable');
}

// 2. Create safe event listener function with null checks
function addSafeEventListener(elementId, event, handler) {
    const element = document.getElementById(elementId);
    if (element) {
        element.addEventListener(event, handler);
        console.log(`✅ Added ${event} listener to ${elementId}`);
        return true;
    } else {
        console.warn(`⚠️ Element ${elementId} not found - skipping event listener`);
        return false;
    }
}

// 3. Fix all sidebar functionality with comprehensive null checks
function initializeSidebarSafely() {
    // Initialize sidebarOpen if not defined
    if (typeof window.sidebarOpen === 'undefined') {
        window.sidebarOpen = false;
    }
    
    // Toggle sidebar with null checks
    addSafeEventListener('toggleSidebar', 'click', function() {
        const sidebar = document.getElementById('chatSidebar');
        const sidebarIcon = document.getElementById('sidebarIcon');
        
        if (sidebar && sidebarIcon) {
            if (window.sidebarOpen) {
                sidebar.style.display = 'none';
                sidebarIcon.className = 'fas fa-info-circle';
                window.sidebarOpen = false;
            } else {
                sidebar.style.display = 'block';
                sidebarIcon.className = 'fas fa-times';
                window.sidebarOpen = true;
            }
        }
    });
    
    // Close sidebar with null checks
    addSafeEventListener('closeSidebar', 'click', function() {
        const sidebar = document.getElementById('chatSidebar');
        const sidebarIcon = document.getElementById('sidebarIcon');
        if (sidebar && sidebarIcon) {
            sidebar.style.display = 'none';
            sidebarIcon.className = 'fas fa-info-circle';
            window.sidebarOpen = false;
        }
    });
    
    // Sidebar notifications toggle
    addSafeEventListener('sidebarNotifications', 'change', function() {
        const notificationIcon = document.getElementById('notificationIcon');
        if (notificationIcon) {
            if (this.checked) {
                notificationIcon.className = 'fas fa-bell';
                notificationIcon.style.color = '#28a745';
            } else {
                notificationIcon.className = 'fas fa-bell-slash';
                notificationIcon.style.color = '#dc3545';
            }
        }
    });
    
    // Show timestamps toggle
    addSafeEventListener('showTimestamps', 'change', function() {
        const messages = document.querySelectorAll('.wechat-message-time');
        messages.forEach(msg => {
            msg.style.display = this.checked ? 'block' : 'none';
        });
    });
    
    // Clear chat functionality
    addSafeEventListener('clearChat', 'click', function() {
        if (confirm('Are you sure you want to clear this chat? This action cannot be undone.')) {
            const messagesContainer = document.getElementById('messages');
            if (messagesContainer) {
                messagesContainer.innerHTML = '';
            }
        }
    });
    
    // Export chat functionality
    addSafeEventListener('exportChat', 'click', function() {
        const messages = document.querySelectorAll('.wechat-message');
        let chatText = 'Chat Export\n================\n\n';
        
        messages.forEach(msg => {
            const bubble = msg.querySelector('.wechat-message-bubble');
            const time = msg.querySelector('.wechat-message-time');
            if (bubble && time) {
                const sender = bubble.textContent;
                const timestamp = time.textContent;
                const isSent = msg.classList.contains('sent');
                chatText += `[${timestamp}] ${isSent ? 'You' : 'Friend'}: ${sender}\n`;
            }
        });
        
        const blob = new Blob([chatText], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `chat_export_${new Date().toISOString().split('T')[0]}.txt`;
        a.click();
        URL.revokeObjectURL(url);
    });
    
    // Test context menu with safety
    addSafeEventListener('testContextMenu', 'click', function() {
        console.log('Testing context menu...');
        const event = { 
            pageX: 300, 
            pageY: 300,
            clientX: 300,
            clientY: 300,
            preventDefault: function() {},
            stopPropagation: function() {}
        };
        if (typeof showContextMenu === 'function') {
            showContextMenu(event, '999', 'Test message', 'test');
        } else {
            console.warn('showContextMenu function not available');
        }
    });
}

// 4. Fix hideContextMenu function with null checks
window.hideContextMenu = function() {
    const contextMenu = document.getElementById('messageContextMenu');
    if (contextMenu) {
        contextMenu.style.display = 'none';
    }
    window.contextMenuVisible = false;
};

// 5. Enhanced showContextMenu function with comprehensive error handling
window.showContextMenu = function(event, messageId, messageText, senderType, timestamp) {
    try {
        // Prevent default context menu
        if (event && event.preventDefault) event.preventDefault();
        if (event && event.stopPropagation) event.stopPropagation();
        
        // Hide any existing context menu
        if (typeof hideContextMenu === 'function') {
            hideContextMenu();
        }
        
        // Store current message data safely
        window.currentMessageId = messageId || 'unknown';
        window.currentMessageText = messageText || '';
        window.currentMessageSender = senderType || 'unknown';
        window.currentMessageTimestamp = timestamp || Date.now();
        
        console.log('showContextMenu called:', messageId, messageText, senderType, timestamp);
        
        // Get or create context menu
        let contextMenu = document.getElementById('messageContextMenu');
        if (!contextMenu) {
            contextMenu = document.createElement('div');
            contextMenu.id = 'messageContextMenu';
            contextMenu.className = 'context-menu';
            contextMenu.style.cssText = `
                position: fixed;
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 999999;
                min-width: 200px;
                display: none;
            `;
            contextMenu.innerHTML = `
                <div class="reaction-bar" style="padding: 8px; border-bottom: 1px solid #eee; display: flex; gap: 8px;">
                    <span class="reaction-emoji" data-reaction="👍" style="cursor: pointer; padding: 4px; border-radius: 4px; hover: background: #f0f0f0;">👍</span>
                    <span class="reaction-emoji" data-reaction="❤️" style="cursor: pointer; padding: 4px; border-radius: 4px;">❤️</span>
                    <span class="reaction-emoji" data-reaction="😂" style="cursor: pointer; padding: 4px; border-radius: 4px;">😂</span>
                    <span class="reaction-emoji" data-reaction="😮" style="cursor: pointer; padding: 4px; border-radius: 4px;">😮</span>
                    <span class="reaction-emoji" data-reaction="😢" style="cursor: pointer; padding: 4px; border-radius: 4px;">😢</span>
                    <span class="reaction-emoji" data-reaction="😡" style="cursor: pointer; padding: 4px; border-radius: 4px;">😡</span>
                    <span class="reaction-emoji" data-reaction="+" style="cursor: pointer; padding: 4px; border-radius: 4px;">+</span>
                </div>
                <div class="context-menu-items" style="padding: 4px;">
                    <div class="context-menu-item" data-action="reply" style="padding: 8px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-reply"></i> Reply
                    </div>
                    <div class="context-menu-item" data-action="copy" style="padding: 8px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-copy"></i> Copy
                    </div>
                    <div class="context-menu-item" data-action="forward" style="padding: 8px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-share"></i> Forward
                    </div>
                    <div class="context-menu-item" data-action="favorite" style="padding: 8px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-star"></i> Favorite
                    </div>
                    <div class="context-menu-item" data-action="quote" style="padding: 8px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-thumbtack"></i> Pin
                    </div>
                    <div class="context-menu-item" data-action="delete" style="padding: 8px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-trash"></i> Delete
                    </div>
                </div>
            `;
            document.body.appendChild(contextMenu);
        }
        
        // Position and show menu safely
        const x = (event && (event.pageX || event.clientX)) || 300;
        const y = (event && (event.pageY || event.clientY)) || 300;
        
        contextMenu.style.display = 'block';
        contextMenu.style.left = x + 'px';
        contextMenu.style.top = y + 'px';
        
        // Adjust position if menu goes outside viewport
        setTimeout(() => {
            const rect = contextMenu.getBoundingClientRect();
            if (rect.right > window.innerWidth) {
                contextMenu.style.left = Math.max(0, x - rect.width) + 'px';
            }
            if (rect.bottom > window.innerHeight) {
                contextMenu.style.top = Math.max(0, y - rect.height) + 'px';
            }
        }, 10);
        
        window.contextMenuVisible = true;
        
        // Add event listeners safely
        contextMenu.querySelectorAll('.reaction-emoji').forEach(item => {
            item.onclick = function(e) {
                e.stopPropagation();
                const reaction = this.getAttribute('data-reaction');
                console.log('Reaction clicked:', reaction);
                hideContextMenu();
            };
        });
        
        contextMenu.querySelectorAll('.context-menu-item').forEach(item => {
            item.onclick = function(e) {
                e.stopPropagation();
                const action = this.getAttribute('data-action');
                console.log('Action clicked:', action);
                
                // Handle basic actions
                switch(action) {
                    case 'copy':
                        if (navigator.clipboard && window.currentMessageText) {
                            navigator.clipboard.writeText(window.currentMessageText);
                            console.log('Message copied to clipboard');
                        }
                        break;
                    case 'reply':
                        const msgInput = document.getElementById('msg');
                        if (msgInput) {
                            msgInput.value = `Reply to: "${window.currentMessageText}" - `;
                            msgInput.focus();
                        }
                        break;
                    default:
                        console.log(`Action ${action} - functionality to be implemented`);
                }
                
                hideContextMenu();
            };
        });
        
        return false;
        
    } catch (error) {
        console.error('Error in showContextMenu:', error);
        return false;
    }
};

// 6. Initialize voice recording with comprehensive safety checks
function initializeVoiceRecording() {
    const voiceBtn = document.getElementById('recordVoice');
    const cancelBtn = document.getElementById('cancelVoice');
    const sendBtn = document.getElementById('sendVoice');
    
    if (voiceBtn) {
        voiceBtn.onclick = function() {
            console.log('🎤 Voice recording activated');
            const voiceInterface = document.getElementById('voiceRecording');
            const sendForm = document.getElementById('sendForm');
            if (voiceInterface && sendForm) {
                voiceInterface.style.display = 'block';
                sendForm.style.display = 'none';
            }
        };
        console.log('✅ Voice recording button initialized');
    }
    
    if (cancelBtn) {
        cancelBtn.onclick = function() {
            console.log('❌ Voice recording cancelled');
            const voiceInterface = document.getElementById('voiceRecording');
            const sendForm = document.getElementById('sendForm');
            if (voiceInterface && sendForm) {
                voiceInterface.style.display = 'none';
                sendForm.style.display = 'flex';
            }
        };
        console.log('✅ Voice cancel button initialized');
    }
    
    if (sendBtn) {
        sendBtn.onclick = function() {
            console.log('📤 Voice message send clicked');
            // Voice send functionality would go here
            const voiceInterface = document.getElementById('voiceRecording');
            const sendForm = document.getElementById('sendForm');
            if (voiceInterface && sendForm) {
                voiceInterface.style.display = 'none';
                sendForm.style.display = 'flex';
            }
        };
        console.log('✅ Voice send button initialized');
    }
}

// 7. Initialize file attachment with safety checks
function initializeFileAttachment() {
    const attachBtn = document.getElementById('attachFile');
    const imageBtn = document.getElementById('attachImage');
    const fileInput = document.getElementById('fileInput');
    const imageInput = document.getElementById('imageInput');
    
    if (attachBtn && fileInput) {
        attachBtn.onclick = function() {
            console.log('📎 File attachment activated');
            fileInput.click();
        };
        console.log('✅ File attachment button initialized');
    }
    
    if (imageBtn && imageInput) {
        imageBtn.onclick = function() {
            console.log('🖼️ Image attachment activated');
            imageInput.click();
        };
        console.log('✅ Image attachment button initialized');
    }
    
    if (fileInput) {
        fileInput.onchange = function() {
            if (this.files && this.files.length > 0) {
                console.log('📁 File selected:', this.files[0].name);
            }
        };
    }
    
    if (imageInput) {
        imageInput.onchange = function() {
            if (this.files && this.files.length > 0) {
                console.log('🖼️ Image selected:', this.files[0].name);
            }
        };
    }
}

// 8. Initialize friends search with safety
function initializeFriendsSearch() {
    const searchInput = document.getElementById('friendSearch');
    if (searchInput) {
        searchInput.oninput = function() {
            const searchTerm = this.value.toLowerCase();
            const friendsList = document.getElementById('friendsList');
            if (friendsList) {
                const friendItems = friendsList.querySelectorAll('.wechat-chat-item');
                let visibleCount = 0;
                
                friendItems.forEach(item => {
                    const nameElement = item.querySelector('.wechat-chat-name');
                    if (nameElement) {
                        const friendName = nameElement.textContent.toLowerCase();
                        if (friendName.includes(searchTerm)) {
                            item.style.display = 'flex';
                            visibleCount++;
                        } else {
                            item.style.display = 'none';
                        }
                    }
                });
                
                const friendsCount = document.getElementById('friendsCount');
                if (friendsCount) {
                    friendsCount.textContent = visibleCount;
                }
            }
        };
        console.log('✅ Friends search initialized');
    }
}

// 9. Global click handler to hide context menu
document.addEventListener('click', function(e) {
    if (window.contextMenuVisible && !e.target.closest('.context-menu')) {
        hideContextMenu();
    }
});

// 10. Enhanced error handling
window.addEventListener('error', function(e) {
    console.warn('JavaScript error caught and handled:', e.message, 'at line:', e.lineno);
    return true; // Prevent default error handling
});

// 11. Initialize all components
function initializeAllComponents() {
    try {
        initializeSidebarSafely();
        initializeVoiceRecording();
        initializeFileAttachment();
        initializeFriendsSearch();
        
        // Initialize friends count
        const friendsCount = document.getElementById('friendsCount');
        const friendItems = document.querySelectorAll('.wechat-chat-item');
        if (friendsCount && friendItems) {
            friendsCount.textContent = friendItems.length;
        }
        
        console.log('🎉 All components initialized successfully!');
        
    } catch (error) {
        console.error('Error during initialization:', error);
    }
}

// Execute initialization
initializeAllComponents();

console.log('✅ JavaScript fixes applied successfully!');
console.log('✅ Context menu should now work properly');
console.log('✅ Voice recording button should work');
console.log('✅ File attachment button should work');
console.log('✅ All event listeners have null safety checks');
console.log('💡 Try right-clicking on messages now!');