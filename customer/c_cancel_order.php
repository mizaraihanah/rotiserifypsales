<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(3); // Ensure user is a customer (formerly guest)
require_once '../config/db_connection.php';
require_once '../includes/functions/order_functions.php';

if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    $_SESSION['error'] = 'Invalid order ID';
    header("Location: c_orders.php");
    exit();
}

$order_id = (int)$_GET['order_id'];
$guest_id = $_SESSION['user']['id'];

// Attempt to cancel the order
if (cancel_order($conn, $order_id, $guest_id)) {
    $_SESSION['success'] = "Order successfully cancelled.";
} else {
    $_SESSION['error'] = "Failed to cancel order. It may no longer be cancellable.";
}

header("Location: c_orders.php");
exit();
?>