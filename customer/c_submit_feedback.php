<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(3); // 3 for customer
require_once '../config/db_connection.php';

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = 'Invalid CSRF token. Please try again.';
        header("Location: c_orders.php");
        exit();
    }
    
    // Get and sanitize form data
    $order_id = (int)$_POST['order_id'];
    $guest_id = $_SESSION['user']['id'];
    $rating = (int)$_POST['rating'];
    $comment = htmlspecialchars(trim($_POST['comment']));
    
    // Validate rating (1-5)
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error'] = 'Invalid rating. Please select a rating between 1 and 5 stars.';
        header("Location: c_view_order.php?order_id=$order_id");
        exit();
    }
    
    try {
        // Check if order exists and belongs to the current user
        $check_stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND guest_id = ? AND (status = 'completed' OR status = 'approved')");
        $check_stmt->bind_param("ii", $order_id, $guest_id);
        $check_stmt->execute();
        $order = $check_stmt->get_result()->fetch_assoc();
        
        if (!$order) {
            throw new Exception("Order not found or not eligible for feedback.");
        }
        
        // Check if feedback already exists
        $exists_stmt = $conn->prepare("SELECT id FROM feedback WHERE order_id = ?");
        $exists_stmt->bind_param("i", $order_id);
        $exists_stmt->execute();
        
        if ($exists_stmt->get_result()->num_rows > 0) {
            // Update existing feedback
            $stmt = $conn->prepare("
                UPDATE feedback 
                SET rating = ?, comment = ?, feedback_date = NOW() 
                WHERE order_id = ?
            ");
            $stmt->bind_param("isi", $rating, $comment, $order_id);
            $stmt->execute();
            
            $_SESSION['success'] = 'Your feedback has been updated. Thank you for your review!';
        } else {
            // Insert new feedback
            $stmt = $conn->prepare("
                INSERT INTO feedback (order_id, guest_id, rating, comment, feedback_date) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("iiis", $order_id, $guest_id, $rating, $comment);
            $stmt->execute();
            
            $_SESSION['success'] = 'Thank you for your feedback!';
        }
        
        header("Location: c_view_order.php?order_id=$order_id");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: c_view_order.php?order_id=$order_id");
        exit();
    }
} else {
    // If accessed directly without POST, redirect to orders
    $_SESSION['error'] = 'Invalid request.';
    header("Location: c_orders.php");
    exit();
}
