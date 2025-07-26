<?php
// debug.php - Debug authentication state

require_once 'config.php';
requireAuthentication();

echo "<h1>Authentication Debug</h1>";

// Show current configuration
echo "<h2>Configuration</h2>";
echo "<p>Password Protected: " . (getConfig('password_protected') ? 'YES' : 'NO') . "</p>";
echo "<p>Password Set: " . (empty(getConfig('password')) ? 'NO' : 'YES') . "</p>";
echo "<p>Password Value: " . (empty(getConfig('password')) ? 'EMPTY' : 'SET') . "</p>";

// Show authentication status
echo "<h2>Authentication Status</h2>";
$auth = isAuthenticated();
echo "<p>Is Authenticated: " . ($auth ? 'YES' : 'NO') . "</p>";

// Show cookies
echo "<h2>Current Cookies</h2>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

// Clear cookies if requested
if (isset($_GET['clear'])) {
    echo "<h2>Clearing Cookies</h2>";
    setcookie('kb_auth', '', time() - 3600, '/');
    setcookie('kb_auth_time', '', time() - 3600, '/');
    setcookie('PHPSESSID', '', time() - 3600, '/');
    echo "<p style='color: green;'>✅ Cookies cleared!</p>";
    echo "<script>setTimeout(function(){ window.location.reload(); }, 1000);</script>";
}

// Test login
if (isset($_POST['test_password'])) {
    echo "<h2>Login Test</h2>";
    $password = $_POST['test_password'];
    $result = login($password);
    echo "<p>Login Result: " . ($result ? 'SUCCESS' : 'FAILED') . "</p>";
    
    if ($result) {
        echo "<p style='color: green;'>✅ Login successful! Refreshing...</p>";
        echo "<script>setTimeout(function(){ window.location.reload(); }, 1000);</script>";
    } else {
        echo "<p style='color: red;'>❌ Login failed!</p>";
    }
}

// Show test forms
echo "<h2>Test Actions</h2>";
echo "<p><a href='debug.php?clear=1'>Clear All Cookies</a></p>";

echo "<h3>Test Login</h3>";
echo "<form method='POST'>";
echo "<input type='password' name='test_password' placeholder='Enter password'>";
echo "<button type='submit'>Test Login</button>";
echo "</form>";

// Show navigation
echo "<h2>Navigation</h2>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";
echo "<p><a href='index.php'>Go to Index Page</a></p>";
echo "<p><a href='logout.php'>Go to Logout Page</a></p>";

// Show what should happen
echo "<h2>What Should Happen</h2>";
if (!getConfig('password_protected') || empty(getConfig('password'))) {
    echo "<p>✅ Should go to setup.php (no password configured)</p>";
} elseif (isAuthenticated()) {
    echo "<p>✅ Should go to index.php (already authenticated)</p>";
} else {
    echo "<p>✅ Should go to login.php (not authenticated)</p>";
}
?> 