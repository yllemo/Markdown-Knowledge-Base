<?php
// logout.php - Logout page for Knowledge Base System

require_once 'config.php';

// Logout the user
logout();

// Redirect to login page
header('Location: login.php');
exit;
?> 