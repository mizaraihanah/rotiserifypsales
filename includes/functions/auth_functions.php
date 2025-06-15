<?php
require_once __DIR__ . '/../../config/db_connection.php';

/**
 * Authenticate user based on email and password
 */
function authenticate_user($email, $password, $conn) {
    $stmt = $conn->prepare("SELECT * FROM guest WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verify password using proper hashing
        if (password_verify($password . $user['salt'], $user['password'])) {
            return $user;
        }
    }
    
    return false;
}

/**
 * Register a new customer
 */
function register_customer($fullname, $email, $password, $contact, $address, $conn) {
    // Generate salt for password
    $salt = bin2hex(random_bytes(32));
    // Hash password with salt
    $hashed_password = password_hash($password . $salt, PASSWORD_DEFAULT);
    $type = 3; // Type 3 for Customer
    
    $stmt = $conn->prepare("INSERT INTO guest (fullname, contact, email, password, salt, type, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssis", $fullname, $contact, $email, $hashed_password, $salt, $type, $address);
    
    if ($stmt->execute()) {
        return true;
    } else {
        return false;
    }
}

/**
 * Check if email already exists
 */
function email_exists($email, $conn) {
    $stmt = $conn->prepare("SELECT id FROM guest WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

/**
 * Register a staff member (bakery staff or sales manager)
 */
function register_staff($fullname, $email, $password, $contact, $address, $role, $employee_id, $conn) {
    // Determine type based on role
    $type = null;
    if ($role === 'Administrator') {
        $type = 0; // Type 0 for Administrator
    } elseif ($role === 'Sales Manager') {
        $type = 1; // Type 1 for Sales Manager (formerly Supervisor)
    } elseif ($role === 'Bakery Staff') {
        $type = 2; // Type 2 for Bakery Staff (formerly Clerk)
    }
    
    // Generate salt
    $salt = bin2hex(random_bytes(32));
    // Hash password
    $hashed_password = password_hash($password . $salt, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO guest (fullname, contact, email, password, salt, type, address, employee_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssiss", $fullname, $contact, $email, $hashed_password, $salt, $type, $address, $employee_id);
    
    if ($stmt->execute()) {
        return true;
    } else {
        return false;
    }
}
?>