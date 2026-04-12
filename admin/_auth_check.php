<?php
// _auth_check.php - Authentication protection for admin directory
// Include this file at the top of any new PHP files in the admin directory

require_once __DIR__ . '/../config/config.php';

if (!isAuthenticated()) {
    header('Location: ../login.php');
    exit;
}
?>