<?php
// setup.php - Initial setup script for Knowledge Base System

require_once 'config/config.php';

// Check if already configured
if (getConfig('password_protected') && !empty(getConfig('password'))) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $site_title = trim($_POST['site_title'] ?? 'Knowledge Base');
    
    // Validate input
    if (empty($password)) {
        $error = 'Password cannot be empty';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif (empty($site_title)) {
        $error = 'Site title cannot be empty';
    } else {
        try {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Save configuration
            saveConfig('password', $hashed_password);
            saveConfig('password_protected', true);
            saveConfig('site_title', $site_title);
            
            $success = 'Setup completed successfully! You can now login with your password.';
            
            // Redirect to login after 2 seconds
            header('Refresh: 2; URL=login.php');
            
        } catch (Exception $e) {
            $error = 'Error saving configuration: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Base - Setup</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .setup-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
        }
        
        .setup-card {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 8px 32px var(--shadow);
        }
        
        .setup-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .setup-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }
        
        .setup-header p {
            color: var(--text-secondary);
            font-size: 1rem;
        }
        
        .setup-form {
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
        
        .setup-btn {
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
        
        .setup-btn:hover {
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
        
        .success-message {
            background-color: rgba(0, 184, 148, 0.1);
            border: 1px solid rgba(0, 184, 148, 0.3);
            color: #00b894;
            padding: 0.75rem;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .setup-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-muted);
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div class="setup-header">
                <h1>🚀 Markdown Knowledge Base (MDKB) Setup</h1>
                <p>Configure your knowledge base for first use</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form class="setup-form" method="POST">
                <div class="form-group">
                    <label for="site_title">Site Title</label>
                    <input type="text" id="site_title" name="site_title" value="<?= htmlspecialchars($_POST['site_title'] ?? 'Markdown Knowledge Base (MDKB)') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="setup-btn">🔧 Complete Setup</button>
            </form>
            
            <div class="setup-footer">
                <p>This will create a secure password-protected knowledge base.</p>
                <p>You can change settings later from the settings panel.</p>
            </div>
        </div>
    </div>
</body>
</html> 