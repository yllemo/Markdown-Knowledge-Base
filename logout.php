<?php
// logout.php - Logout page for Knowledge Base System

require_once 'config/config.php';

// Logout the user - clear all authentication cookies
logout();

// Always redirect to login page with logout confirmation
header('Location: login.php?logged_out=1');
exit;
?> 