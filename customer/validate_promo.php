<?php
require_once '../includes/security.php';
secure_session_start();
// Remove the customer-only check to allow both customers and staff to validate codes
require_once '../config/db_connection.php';

header('Content-Type: application/json');

if (!isset($_POST['code']) || !isset($_POST['subtotal'])) {
    echo json_encode([
        'valid' => false, 
        'message' => 'Invalid request parameters'
    ]);
    exit;
}

$code = trim($_POST['code']);
$subtotal = floatval($_POST['subtotal']);

// Validate promotion code
$stmt = $conn->prepare("
    SELECT * FROM promotions 
    WHERE code = ? 
    AND status = 'active'
    AND NOW() BETWEEN start_date AND end_date
");
$stmt->bind_param("s", $code);
$stmt->execute();
$promotion = $stmt->get_result()->fetch_assoc();

if ($promotion) {
    // For fixed amount discounts, check if order meets minimum amount
    if ($promotion['discount_type'] === 'fixed' && $subtotal < $promotion['discount_value']) {
        echo json_encode([
            'valid' => false,
            'message' => 'Order total must be greater than the discount amount'
        ]);
        exit;
    }

    echo json_encode([
        'valid' => true,
        'discount_type' => $promotion['discount_type'],
        'discount_value' => (float)$promotion['discount_value'],
        'message' => 'Valid promotion code'
    ]);
} else {
    echo json_encode([
        'valid' => false,
        'message' => 'Invalid or expired promotion code'
    ]);
}