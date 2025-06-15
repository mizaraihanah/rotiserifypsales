<?php
function secure_session_start() {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

function check_user_type($required_type) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['type'] != $required_type) {
        header("Location: ../auth/login.php");
        exit();
    }
    
    // Prevent session fixation
    if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        header("Location: ../auth/login.php");
        exit();
    }
    $_SESSION['last_activity'] = time();
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

function sanitize_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function hash_password($password, $salt) {
    // Using PASSWORD_DEFAULT will use the strongest algorithm available (currently bcrypt)
    return password_hash($password . $salt, PASSWORD_DEFAULT);
}

function verify_password($password, $hash, $salt) {
    return password_verify($password . $salt, $hash);
}
?>