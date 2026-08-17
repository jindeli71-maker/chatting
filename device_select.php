<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LINE Chat - Choose Your Device</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --line-green: #06C755;
            --line-green-dark: #05a847;
            --line-green-light: #e8f8ee;
            --line-green-soft: #f0faf3;
            --text-primary: #1a1a1a;
            --text-secondary: #666666;
            --text-muted: #999999;
            --card-shadow: 0 4px 24px rgba(6, 199, 85, 0.08);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--line-green-soft);
            background-image:
                radial-gradient(circle at 20% 80%, rgba(6, 199, 85, 0.06) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(6, 199, 85, 0.04) 0%, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .device-selector {
            background: #ffffff;
            border-radius: 16px;
            padding: 48px 40px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(6, 199, 85, 0.12);
            text-align: center;
            max-width: 600px;
            width: 100%;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            width: 72px;
            height: 72px;
            background: var(--line-green);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 4px 16px rgba(6, 199, 85, 0.3);
        }

        .logo i {
            font-size: 2rem;
            color: #ffffff;
        }

        .title {
            color: var(--text-primary);
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 36px;
            font-weight: 400;
            line-height: 1.5;
        }

        .device-options {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .device-option {
            background: #ffffff;
            border: 2px solid #e8ece9;
            border-radius: 12px;
            padding: 32px 24px;
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            color: var(--text-primary);
            min-width: 200px;
            flex: 1;
            max-width: 240px;
            position: relative;
        }

        .device-option:hover {
            transform: translateY(-4px);
            border-color: var(--line-green);
            background: var(--line-green-light);
            box-shadow: 0 8px 24px rgba(6, 199, 85, 0.12);
        }

        .device-option:active {
            transform: translateY(-2px);
        }

        .device-icon {
            font-size: 2.5rem;
            margin-bottom: 16px;
            display: block;
            color: var(--line-green);
        }

        .device-name {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .device-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .continue-btn {
            background: var(--line-green);
            color: #ffffff;
            border: none;
            padding: 14px 36px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(6, 199, 85, 0.25);
            display: none;
        }

        .continue-btn:hover {
            background: var(--line-green-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(6, 199, 85, 0.35);
        }

        .continue-btn.show {
            display: inline-block;
            animation: fadeInUp 0.4s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .selected {
            background: var(--line-green-light) !important;
            border-color: var(--line-green) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 16px rgba(6, 199, 85, 0.15) !important;
        }

        .footer-text {
            color: var(--text-muted);
            font-size: 0.8125rem;
            margin-top: 28px;
        }

        @media (max-width: 768px) {
            .device-selector {
                padding: 32px 24px;
                margin: 10px;
                border-radius: 12px;
            }

            .title {
                font-size: 1.5rem;
            }

            .device-options {
                flex-direction: column;
                gap: 12px;
            }

            .device-option {
                min-width: auto;
                max-width: none;
                padding: 24px 20px;
            }

            .device-icon {
                font-size: 2rem;
            }
        }

        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .particle {
            position: absolute;
            background: rgba(6, 199, 85, 0.08);
            border-radius: 50%;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Floating particles background -->
    <div class="particles" id="particles"></div>

    <div class="device-selector">
        <div class="logo"><i class="fas fa-comment-dots"></i></div>
        <h1 class="title">LINE Chat</h1>
        <p class="subtitle">Choose your device for the best experience</p>
        
        <div class="device-options">
            <div class="device-option" data-device="mobile" onclick="selectDevice('mobile')">
                <i class="fas fa-mobile-alt device-icon"></i>
                <div class="device-name">Mobile</div>
                <div class="device-description">Optimized touch interface with swipe gestures and mobile-friendly controls</div>
            </div>
            
            <div class="device-option" data-device="desktop" onclick="selectDevice('desktop')">
                <i class="fas fa-desktop device-icon"></i>
                <div class="device-name">Desktop</div>
                <div class="device-description">Full-featured interface with keyboard shortcuts and desktop optimizations</div>
            </div>
        </div>
        
        <button class="continue-btn" id="continueBtn" onclick="proceedToLogin()">
            Continue to Login
            <i class="fas fa-arrow-right" style="margin-left: 10px;"></i>
        </button>
        
        <p class="footer-text">Your selection will be remembered for future visits</p>
    </div>

    <script>
        let selectedDevice = null;

        // Create floating particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            
            for (let i = 0; i < 15; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.width = particle.style.height = (Math.random() * 4 + 2) + 'px';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = (Math.random() * 10 + 15) + 's';
                particlesContainer.appendChild(particle);
            }
        }

        function selectDevice(device) {
            // Remove previous selection
            document.querySelectorAll('.device-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selection to clicked device
            event.target.closest('.device-option').classList.add('selected');
            
            selectedDevice = device;
            
            // Show continue button with animation
            const continueBtn = document.getElementById('continueBtn');
            continueBtn.classList.add('show');
            
            // Store device preference
            localStorage.setItem('preferredDevice', device);
            
            console.log('Selected device:', device);
        }

        function proceedToLogin() {
            if (!selectedDevice) {
                alert('Please select a device type first');
                return;
            }
            
            // Store device preference and redirect to login
            localStorage.setItem('deviceType', selectedDevice);
            
            // Add loading animation
            const btn = document.getElementById('continueBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            btn.style.pointerEvents = 'none';
            
            // Redirect after animation
            setTimeout(() => {
                window.location.href = 'login.php?device=' + selectedDevice;
            }, 1000);
        }

        // Auto-detect device type and pre-select
        function autoDetectDevice() {
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            const savedDevice = localStorage.getItem('preferredDevice');
            
            if (savedDevice) {
                selectDevice(savedDevice);
                return;
            }
            
            // Auto-suggest based on user agent
            if (isMobile) {
                // Highlight mobile option
                document.querySelector('[data-device="mobile"]').style.background = '#e8f8ee';
            } else {
                // Highlight desktop option
                document.querySelector('[data-device="desktop"]').style.background = '#e8f8ee';
            }
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            autoDetectDevice();
            
            // Add keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && selectedDevice) {
                    proceedToLogin();
                }
                
                if (e.key === '1' || e.key === 'm' || e.key === 'M') {
                    selectDevice('mobile');
                }
                
                if (e.key === '2' || e.key === 'd' || e.key === 'D') {
                    selectDevice('desktop');
                }
            });
        });

        // Add hover sound effect (optional)
        document.querySelectorAll('.device-option').forEach(option => {
            option.addEventListener('mouseenter', function() {
                // Optional: Add subtle sound effect here
                this.style.transform = 'translateY(-4px)';
            });
            
            option.addEventListener('mouseleave', function() {
                if (!this.classList.contains('selected')) {
                    this.style.transform = '';
                }
            });
        });
    </script>
</body>
</html>