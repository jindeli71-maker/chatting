<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat App - Test Page</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
            background-size: 400% 400%;
            animation: gradientFlow 15s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }

        @keyframes gradientFlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .test-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            text-align: center;
            max-width: 600px;
            width: 100%;
        }

        .test-title {
            color: #333;
            font-size: 2.5rem;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .test-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin: 30px 0;
        }

        .test-btn {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .test-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 172, 254, 0.4);
            text-decoration: none;
            color: white;
        }

        .status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 10px;
            font-weight: 500;
        }

        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1 class="test-title">🚀 Chat App Test Center</h1>
        <p>Test all the new features and functionality</p>
        
        <div class="test-buttons">
            <a href="device_select.php" class="test-btn">
                📱 Test Device Selection
            </a>
            
            <a href="login.php" class="test-btn">
                🔐 Test Login Page
            </a>
            
            <a href="admin_login.php" class="test-btn">
                👑 Quick Admin Login
            </a>
            
            <a href="chat.php?user_id=2" class="test-btn">
                💬 Test Chat Interface
            </a>
            
            <a href="index.php" class="test-btn">
                🏠 Test Main Dashboard
            </a>
        </div>
        
        <div id="status"></div>
        
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
            <h3>📋 Quick Instructions:</h3>
            <ol style="text-align: left; line-height: 1.6;">
                <li><strong>Device Selection:</strong> Choose Mobile or Desktop experience</li>
                <li><strong>Login:</strong> Use <code>admin</code> / <code>admin</code> or any test user</li>
                <li><strong>Chat:</strong> Full-screen interface with enhanced debugging</li>
                <li><strong>Dashboard:</strong> Colorful modern design with gradients</li>
            </ol>
        </div>
    </div>

    <script>
        // Test if JavaScript is working
        document.addEventListener('DOMContentLoaded', function() {
            const statusDiv = document.getElementById('status');
            statusDiv.innerHTML = '<div class="status success">✅ JavaScript is working! All systems ready.</div>';
            
            // Add click tracking
            document.querySelectorAll('.test-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    console.log('Testing:', href);
                    
                    // Add visual feedback
                    this.style.background = 'linear-gradient(135deg, #51cf66, #40c057)';
                    this.textContent = '⏳ Loading...';
                    
                    setTimeout(() => {
                        window.location.href = href;
                    }, 500);
                    
                    e.preventDefault();
                });
            });
        });
    </script>
</body>
</html>