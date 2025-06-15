<?php
require_once '../includes/security.php';
secure_session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }
} else {
    // Also allow GET requests to logout
    session_unset();
    session_destroy();
}

header("Location: login.php");
exit();