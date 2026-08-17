/**
 * VoiceRecorder - WhatsApp-like voice message recording component
 */
class VoiceRecorder {
    constructor(options = {}) {
        this.options = {
            maxDuration: 300, // 5 minutes
            chatId: null,
            chatType: 'private',
            onRecordingStart: () => {},
            onRecordingStop: () => {},
            onRecordingCancel: () => {},
            onSendSuccess: () => {},
            onSendError: () => {},
            ...options
        };
        
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.isRecording = false;
        this.startTime = null;
        this.timer = null;
        this.stream = null;
        
        this.init();
    }
    
    init() {
        this.createUI();
        this.attachEventListeners();
    }
    
    createUI() {
        // Create voice recording UI
        this.voiceRecorderUI = document.createElement('div');
        this.voiceRecorderUI.className = 'voice-recorder-ui';
        this.voiceRecorderUI.style.display = 'none';
        this.voiceRecorderUI.innerHTML = `
            <div class="voice-recorder-content">
                <div class="voice-recorder-header">
                    <button class="voice-cancel-btn" title="Cancel Recording">
                        <i class="fas fa-times"></i>
                    </button>
                    <span class="voice-status">Recording...</span>
                    <div class="voice-timer">00:00</div>
                </div>
                <div class="voice-recorder-body">
                    <div class="voice-waveform">
                        <div class="voice-wave-bar"></div>
                        <div class="voice-wave-bar"></div>
                        <div class="voice-wave-bar"></div>
                        <div class="voice-wave-bar"></div>
                        <div class="voice-wave-bar"></div>
                    </div>
                    <div class="voice-controls">
                        <button class="voice-stop-btn" title="Stop Recording">
                            <i class="fas fa-stop"></i>
                        </button>
                        <button class="voice-send-btn" title="Send Voice Message" style="display: none;">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                        <button class="voice-delete-btn" title="Delete Recording" style="display: none;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="voice-playback" style="display: none;">
                    <audio controls class="voice-audio-player"></audio>
                    <span class="voice-duration">00:00</span>
                </div>
            </div>
        `;
        
        // Add to chat area
        const chatArea = document.querySelector('.wechat-chat-area') || document.body;
        chatArea.appendChild(this.voiceRecorderUI);
    }
    
    attachEventListeners() {
        // Voice recording button (should be added to chat input area)
        const voiceBtn = document.getElementById('voiceMessageBtn');
        if (voiceBtn) {
            voiceBtn.addEventListener('click', () => this.toggleRecording());
        }
        
        // Control buttons
        this.voiceRecorderUI.querySelector('.voice-cancel-btn').addEventListener('click', () => this.cancelRecording());
        this.voiceRecorderUI.querySelector('.voice-stop-btn').addEventListener('click', () => this.stopRecording());
        this.voiceRecorderUI.querySelector('.voice-send-btn').addEventListener('click', () => this.sendVoiceMessage());
        this.voiceRecorderUI.querySelector('.voice-delete-btn').addEventListener('click', () => this.deleteRecording());
    }
    
    async toggleRecording() {
        if (this.isRecording) {
            this.stopRecording();
        } else {
            await this.startRecording();
        }
    }
    
    async startRecording() {
        try {
            // Request microphone permission
            this.stream = await navigator.mediaDevices.getUserMedia({ 
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                } 
            });
            
            // Create MediaRecorder
            const options = { mimeType: 'audio/webm;codecs=opus' };
            if (!MediaRecorder.isTypeSupported(options.mimeType)) {
                options.mimeType = 'audio/ogg;codecs=opus';
                if (!MediaRecorder.isTypeSupported(options.mimeType)) {
                    options.mimeType = 'audio/wav';
                }
            }
            
            this.mediaRecorder = new MediaRecorder(this.stream, options);
            this.audioChunks = [];
            
            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    this.audioChunks.push(event.data);
                }
            };
            
            this.mediaRecorder.onstop = () => {
                this.onRecordingComplete();
            };
            
            // Start recording
            this.mediaRecorder.start(100); // Collect data every 100ms
            this.isRecording = true;
            this.startTime = Date.now();
            
            // Show UI and start timer
            this.showRecordingUI();
            this.startTimer();
            this.startWaveAnimation();
            
            this.options.onRecordingStart();
            
        } catch (error) {
            console.error('Error starting recording:', error);
            alert('Could not access microphone. Please check permissions.');
        }
    }
    
    stopRecording() {
        if (this.mediaRecorder && this.isRecording) {
            this.mediaRecorder.stop();
            this.isRecording = false;
            this.stopTimer();
            this.stopWaveAnimation();
            this.options.onRecordingStop();
        }
    }
    
    cancelRecording() {
        if (this.isRecording) {
            this.stopRecording();
        }
        
        this.cleanup();
        this.hideRecordingUI();
        this.options.onRecordingCancel();
    }
    
    deleteRecording() {
        this.cleanup();
        this.hideRecordingUI();
    }
    
    onRecordingComplete() {
        // Create audio blob
        const mimeType = this.mediaRecorder.mimeType || 'audio/webm';
        const audioBlob = new Blob(this.audioChunks, { type: mimeType });
        
        // Calculate duration
        const duration = Math.round((Date.now() - this.startTime) / 1000);
        
        // Show playback controls
        this.showPlaybackUI(audioBlob, duration);
        
        this.audioBlob = audioBlob;
        this.duration = duration;
    }
    
    showRecordingUI() {
        this.voiceRecorderUI.style.display = 'block';
        this.voiceRecorderUI.querySelector('.voice-recorder-body').style.display = 'block';
        this.voiceRecorderUI.querySelector('.voice-playback').style.display = 'none';
        this.voiceRecorderUI.querySelector('.voice-send-btn').style.display = 'none';
        this.voiceRecorderUI.querySelector('.voice-delete-btn').style.display = 'none';
        this.voiceRecorderUI.querySelector('.voice-stop-btn').style.display = 'inline-block';
    }
    
    showPlaybackUI(audioBlob, duration) {
        const audioPlayer = this.voiceRecorderUI.querySelector('.voice-audio-player');
        const audioUrl = URL.createObjectURL(audioBlob);
        audioPlayer.src = audioUrl;
        
        this.voiceRecorderUI.querySelector('.voice-recorder-body').style.display = 'none';
        this.voiceRecorderUI.querySelector('.voice-playback').style.display = 'block';
        this.voiceRecorderUI.querySelector('.voice-send-btn').style.display = 'inline-block';
        this.voiceRecorderUI.querySelector('.voice-delete-btn').style.display = 'inline-block';
        this.voiceRecorderUI.querySelector('.voice-duration').textContent = this.formatTime(duration);
        this.voiceRecorderUI.querySelector('.voice-status').textContent = 'Recording complete';
    }
    
    hideRecordingUI() {
        this.voiceRecorderUI.style.display = 'none';
    }
    
    startTimer() {
        this.timer = setInterval(() => {
            const elapsed = Math.round((Date.now() - this.startTime) / 1000);
            this.voiceRecorderUI.querySelector('.voice-timer').textContent = this.formatTime(elapsed);
            
            // Auto-stop at max duration
            if (elapsed >= this.options.maxDuration) {
                this.stopRecording();
            }
        }, 1000);
    }
    
    stopTimer() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }
    
    startWaveAnimation() {
        const waveBars = this.voiceRecorderUI.querySelectorAll('.voice-wave-bar');
        waveBars.forEach((bar, index) => {
            bar.style.animationDelay = `${index * 0.1}s`;
            bar.classList.add('active');
        });
    }
    
    stopWaveAnimation() {
        const waveBars = this.voiceRecorderUI.querySelectorAll('.voice-wave-bar');
        waveBars.forEach(bar => {
            bar.classList.remove('active');
        });
    }
    
    async sendVoiceMessage() {
        if (!this.audioBlob || !this.options.chatId) {
            alert('No recording to send or chat ID not set');
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'save_voice_message');
            formData.append('chat_id', this.options.chatId);
            formData.append('chat_type', this.options.chatType);
            formData.append('duration', this.duration);
            formData.append('voice_data', this.audioBlob, `voice_${Date.now()}.webm`);
            
            const response = await fetch('voice_api.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.options.onSendSuccess(result);
                this.cleanup();
                this.hideRecordingUI();
            } else {
                throw new Error(result.error || 'Failed to send voice message');
            }
            
        } catch (error) {
            console.error('Error sending voice message:', error);
            this.options.onSendError(error);
            alert('Failed to send voice message: ' + error.message);
        }
    }
    
    formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    
    cleanup() {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        
        if (this.mediaRecorder) {
            this.mediaRecorder = null;
        }
        
        this.audioChunks = [];
        this.audioBlob = null;
        this.duration = null;
        this.stopTimer();
        this.stopWaveAnimation();
    }
    
    // Update chat settings
    updateChatSettings(chatId, chatType) {
        this.options.chatId = chatId;
        this.options.chatType = chatType;
    }
    
    // Destroy the recorder
    destroy() {
        this.cleanup();
        if (this.voiceRecorderUI && this.voiceRecorderUI.parentNode) {
            this.voiceRecorderUI.parentNode.removeChild(this.voiceRecorderUI);
        }
    }
}

// Voice Player Component for playing received voice messages
class VoicePlayer {
    constructor(messageElement, voiceData) {
        this.messageElement = messageElement;
        this.voiceData = voiceData;
        this.isPlaying = false;
        this.currentTime = 0;
        
        this.createPlayer();
    }
    
    createPlayer() {
        const playerHTML = `
            <div class="voice-message-player">
                <button class="voice-play-btn" title="Play Voice Message">
                    <i class="fas fa-play"></i>
                </button>
                <div class="voice-waveform-display">
                    <div class="voice-progress-bar">
                        <div class="voice-progress-fill"></div>
                    </div>
                </div>
                <span class="voice-message-duration">${this.formatDuration(this.voiceData.duration)}</span>
                <div class="voice-message-controls">
                    <button class="voice-download-btn" title="Download Voice Message">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
            </div>
        `;
        
        this.messageElement.innerHTML = playerHTML;
        
        // Create hidden audio element
        this.audio = document.createElement('audio');
        this.audio.src = `serve_file.php?file=${this.voiceData.file_path}`;
        this.audio.preload = 'metadata';
        
        this.attachPlayerEvents();
    }
    
    attachPlayerEvents() {
        const playBtn = this.messageElement.querySelector('.voice-play-btn');
        const downloadBtn = this.messageElement.querySelector('.voice-download-btn');
        const progressBar = this.messageElement.querySelector('.voice-progress-bar');
        
        playBtn.addEventListener('click', () => this.togglePlay());
        downloadBtn.addEventListener('click', () => this.downloadVoice());
        progressBar.addEventListener('click', (e) => this.seekTo(e));
        
        // Audio events
        this.audio.addEventListener('loadedmetadata', () => this.onMetadataLoaded());
        this.audio.addEventListener('timeupdate', () => this.onTimeUpdate());
        this.audio.addEventListener('ended', () => this.onEnded());
        this.audio.addEventListener('error', () => this.onError());
    }
    
    togglePlay() {
        if (this.isPlaying) {
            this.pause();
        } else {
            this.play();
        }
    }
    
    play() {
        this.audio.play().then(() => {
            this.isPlaying = true;
            this.updatePlayButton();
        }).catch(error => {
            console.error('Error playing voice message:', error);
        });
    }
    
    pause() {
        this.audio.pause();
        this.isPlaying = false;
        this.updatePlayButton();
    }
    
    seekTo(event) {
        const progressBar = event.currentTarget;
        const rect = progressBar.getBoundingClientRect();
        const clickX = event.clientX - rect.left;
        const percentage = clickX / rect.width;
        
        this.audio.currentTime = percentage * this.audio.duration;
    }
    
    onMetadataLoaded() {
        const duration = this.audio.duration || this.voiceData.duration;
        this.messageElement.querySelector('.voice-message-duration').textContent = this.formatDuration(duration);
    }
    
    onTimeUpdate() {
        const progress = (this.audio.currentTime / this.audio.duration) * 100;
        this.messageElement.querySelector('.voice-progress-fill').style.width = `${progress}%`;
        
        const remainingTime = this.audio.duration - this.audio.currentTime;
        this.messageElement.querySelector('.voice-message-duration').textContent = this.formatDuration(remainingTime);
    }
    
    onEnded() {
        this.isPlaying = false;
        this.updatePlayButton();
        this.messageElement.querySelector('.voice-progress-fill').style.width = '0%';
        this.messageElement.querySelector('.voice-message-duration').textContent = this.formatDuration(this.voiceData.duration);
    }
    
    onError() {
        console.error('Error loading voice message');
        this.messageElement.querySelector('.voice-message-player').innerHTML = `
            <div class=\"voice-error\">
                <i class=\"fas fa-exclamation-triangle\"></i>
                <span>Unable to load voice message</span>
            </div>
        `;
    }
    
    updatePlayButton() {
        const playBtn = this.messageElement.querySelector('.voice-play-btn i');
        playBtn.className = this.isPlaying ? 'fas fa-pause' : 'fas fa-play';
    }
    
    downloadVoice() {
        const link = document.createElement('a');
        link.href = `serve_file.php?file=${this.voiceData.file_path}`;
        link.download = this.voiceData.file_name || 'voice_message.webm';
        link.click();
    }
    
    formatDuration(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { VoiceRecorder, VoicePlayer };
}