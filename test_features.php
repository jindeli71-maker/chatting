<?php
// Simple test page to verify if new features are loading
require_once __DIR__ . '/includes/auth.php';
require_login();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Feature Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="p-4">
    <h2>Chat Features Test</h2>
    
    <div class="card">
        <div class="card-body">
            <h5>Voice Recording Test</h5>
            <button type="button" id="recordVoice" class="btn btn-success">
                <i class="fas fa-microphone"></i> Record Voice
            </button>
            
            <h5 class="mt-3">File Attachment Test</h5>
            <button type="button" id="attachFile" class="btn btn-primary">
                <i class="fas fa-paperclip"></i> Attach File
            </button>
            
            <div id="testResults" class="mt-3"></div>
        </div>
    </div>
    
    <!-- Voice Recording Interface -->
    <div id="voiceRecording" class="voice-recording-interface" style="display: none;">
        <div class="voice-recording-content">
            <div class="voice-timer">00:00</div>
            <div class="voice-waveform">
                <div class="waveform-bars">
                    <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                </div>
            </div>
            <div class="voice-actions">
                <button id="cancelVoice" class="btn btn-danger"><i class="fas fa-times"></i></button>
                <button id="sendVoice" class="btn btn-success"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
    
    <input type="file" id="fileInput" style="display: none;" multiple accept="*/*">
    
    <script>
        document.getElementById('recordVoice').addEventListener('click', function() {
            document.getElementById('testResults').innerHTML += '<p>✅ Voice button clicked!</p>';
            document.getElementById('voiceRecording').style.display = 'block';
        });
        
        document.getElementById('attachFile').addEventListener('click', function() {
            document.getElementById('testResults').innerHTML += '<p>✅ File attach button clicked!</p>';
            document.getElementById('fileInput').click();
        });
        
        document.getElementById('fileInput').addEventListener('change', function() {
            if (this.files.length > 0) {
                document.getElementById('testResults').innerHTML += '<p>✅ File selected: ' + this.files[0].name + '</p>';
            }
        });
        
        document.getElementById('cancelVoice').addEventListener('click', function() {
            document.getElementById('voiceRecording').style.display = 'none';
            document.getElementById('testResults').innerHTML += '<p>✅ Voice recording cancelled!</p>';
        });
    </script>
</body>
</html>