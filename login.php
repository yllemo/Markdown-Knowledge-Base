<?php
// login.php - Login page for Knowledge Base System

// Start output buffering to prevent header issues
ob_start();

require_once 'config.php';

// Check if setup is needed
if (!getConfig('password_protected') || empty(getConfig('password'))) {
    header('Location: setup.php');
    exit;
}

// Check if already logged in
if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    if (login($password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid password. Please try again.';
    }
}

$site_title = getConfig('site_title', 'Knowledge Base');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_title) ?> - Login</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
        }
        
        .login-card {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 32px var(--shadow);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        .login-header p {
            color: var(--text-secondary);
            font-size: 1rem;
        }
        
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-group label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .form-group input {
            padding: 0.75rem;
            background-color: var(--bg-primary);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(74, 158, 255, 0.1);
        }
        
        .login-btn {
            background-color: var(--accent-primary);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.75rem;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 1rem;
        }
        
        .login-btn:hover {
            background-color: #3a8eff;
            transform: translateY(-1px);
        }
        
        .error-message {
            background-color: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.3);
            color: #e74c3c;
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        
        .debug-info {
            background-color: rgba(74, 158, 255, 0.1);
            border: 1px solid rgba(74, 158, 255, 0.3);
            color: var(--accent-primary);
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>🔐 <?= htmlspecialchars($site_title) ?></h1>
                <p>Enter your password to access the knowledge base</p>
            </div>
            
            <?php if (isset($_GET['debug'])): ?>
                <div class="debug-info">
                    <strong>Debug Mode Active</strong><br>
                    <a href="login.php">Remove debug mode</a>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form class="login-form" method="POST">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autofocus>
                </div>
                
                <button type="submit" class="login-btn">🔓 Login</button>
            </form>
            
            <div class="login-footer">
                <p>Secure cookie-based authentication</p>
                <p><a href="login.php?debug=1">Debug Mode</a> | <a href="debug.php">Full Debug</a></p>
            </div>
        </div>
    </div>
</body>
</html> 