<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Your Device - Chat App</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
            background-size: 400% 400%;
            animation: gradientFlow 15s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        @keyframes gradientFlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .device-selector {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 50px 40px;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            max-width: 600px;
            width: 100%;
            animation: slideIn 0.8s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .title {
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.2rem;
            margin-bottom: 40px;
            font-weight: 300;
        }

        .device-options {
            display: flex;
            gap: 30px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        .device-option {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 2px solid transparent;
            border-radius: 20px;
            padding: 40px 30px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-decoration: none;
            color: white;
            min-width: 200px;
            position: relative;
            overflow: hidden;
        }

        .device-option::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s;
        }

        .device-option:hover::before {
            left: 100%;
        }

        .device-option:hover {
            transform: translateY(-10px) scale(1.05);
            border-color: rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .device-option:active {
            transform: translateY(-5px) scale(1.02);
        }

        .device-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            display: block;
            animation: deviceFloat 3s ease-in-out infinite;
        }

        .device-option:nth-child(1) .device-icon {
            animation-delay: 0s;
        }

        .device-option:nth-child(2) .device-icon {
            animation-delay: 0.5s;
        }

        @keyframes deviceFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .device-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .device-description {
            font-size: 0.95rem;
            opacity: 0.9;
            line-height: 1.4;
        }

        .continue-btn {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(79, 172, 254, 0.3);
            display: none;
        }

        .continue-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(79, 172, 254, 0.4);
        }

        .continue-btn.show {
            display: inline-block;
            animation: fadeInUp 0.5s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .selected {
            background: rgba(255, 255, 255, 0.25) !important;
            border-color: rgba(255, 255, 255, 0.6) !important;
            transform: translateY(-5px) !important;
        }

        .footer-text {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-top: 30px;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .device-selector {
                padding: 30px 20px;
                margin: 10px;
            }

            .title {
                font-size: 2rem;
            }

            .device-options {
                flex-direction: column;
                gap: 20px;
            }

            .device-option {
                min-width: auto;
                padding: 30px 20px;
            }

            .device-icon {
                font-size: 3rem;
            }
        }

        /* Particles background effect */
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
            background: rgba(255, 255, 255, 0.1);
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
        <div class="logo">💬</div>
        <h1 class="title">Welcome to Chat App</h1>
        <p class="subtitle">Choose your device type for the best experience</p>
        
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
                document.querySelector('[data-device="mobile"]').style.background = 'rgba(255, 255, 255, 0.2)';
            } else {
                // Highlight desktop option
                document.querySelector('[data-device="desktop"]').style.background = 'rgba(255, 255, 255, 0.2)';
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
                this.style.transform = 'translateY(-10px) scale(1.05)';
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