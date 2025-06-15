<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(3); // 3 for customer
require_once '../config/db_connection.php';
require_once '../includes/functions/order_functions.php';

if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    $_SESSION['error'] = 'Invalid order ID';
    header("Location: c_orders.php");
    exit();
}

$order_id = (int)$_GET['order_id'];
$guest_id = $_SESSION['user']['id'];
$is_modal = isset($_GET['format']) && $_GET['format'] === 'modal';

// Get order details
$result = get_order_details($conn, $order_id, $guest_id);

if (!$result) {
    $_SESSION['error'] = 'Order not found';
    header("Location: c_orders.php");
    exit();
}

$order = $result['order'];
$order_items = $result['items'];

// Check if order is completed/approved
if ($order['status'] !== 'completed' && $order['status'] !== 'approved') {
    $_SESSION['error'] = 'Only completed orders can be printed';
    header("Location: c_view_order.php?order_id=" . $order_id);
    exit();
}

// Get bakery information
$bakery_name = "RotiSeri Bakery";
$bakery_address = "123 Main Street, City Center";
$bakery_contact = "+60-1234-5678";
$bakery_email = "info@rotiseribakery.com";
$bakery_website = "www.rotiseribakery.com";

// If modal format requested, only return receipt content
if ($is_modal) {
    ?>
    <div class="receipt-container">
        <div class="receipt-header">
            <h1><?php echo $bakery_name; ?></h1>
            <p><?php echo $bakery_address; ?></p>
            <p>Tel: <?php echo $bakery_contact; ?></p>
            <p><?php echo $bakery_email; ?></p>
            <p><?php echo $bakery_website; ?></p>
        </div>

        <div class="receipt-details">
            <p>
                <span><strong>Order #:</strong></span>
                <span><?php echo htmlspecialchars($order['order_number']); ?></span>
            </p>
            <p>
                <span><strong>Date:</strong></span>
                <span><?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></span>
            </p>
            <p>
                <span><strong>Customer:</strong></span>
                <span><?php echo htmlspecialchars($order['fullname']); ?></span>
            </p>
            <?php if (!empty($order['contact'])): ?>
            <p>
                <span><strong>Contact:</strong></span>
                <span><?php echo htmlspecialchars($order['contact']); ?></span>
            </p>
            <?php endif; ?>
        </div>

        <?php if (!empty($order['delivery_address'])): ?>
        <div class="receipt-details">
            <p><strong>Delivery Address:</strong></p>
            <p><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
        </div>
        <?php endif; ?>

        <table class="receipt-items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="amount">Qty</th>
                    <th class="amount">Price</th>
                    <th class="amount">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $subtotal = 0;
                foreach ($order_items as $item):
                    $item_subtotal = $item['quantity'] * $item['unit_price'];
                    $subtotal += $item_subtotal;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td class="amount"><?php echo $item['quantity']; ?></td>
                    <td class="amount">RM<?php echo number_format($item['unit_price'], 2); ?></td>
                    <td class="amount">RM<?php echo number_format($item_subtotal, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="receipt-total">
            <p>
                <span>Subtotal:</span>
                <span>RM<?php echo number_format($subtotal, 2); ?></span>
            </p>
            <?php if (!empty($order['discount'])): ?>
            <p>
                <span>Discount:</span>
                <span>-RM<?php echo number_format($order['discount'], 2); ?></span>
            </p>
            <?php endif; ?>
            <p class="final-total">
                <span>Total:</span>
                <span>RM<?php echo number_format($order['total_amount'], 2); ?></span>
            </p>
            <p>
                <span>Payment Method:</span>
                <span><?php echo ucfirst(htmlspecialchars($order['payment_method'])); ?></span>
            </p>
            <p>
                <span>Payment Status:</span>
                <span><?php echo ucfirst(htmlspecialchars($order['payment_status'])); ?></span>
            </p>
        </div>

        <div class="receipt-footer">
            <p>Thank you for your order!</p>
            <p>Please keep this receipt for your reference.</p>
            <p>Visit us again!</p>
        </div>
    </div>
    <?php
    exit();
}

// Set page title for full page view
$page_title = "Print Receipt - Order #" . $order['order_number'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        :root {
            --primary: #0561FC;
            --primary-light: rgba(5, 97, 252, 0.1);
            --primary-dark: #0453d6;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 4rem 2rem;
            margin: 0;
        }

        .receipt-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            width: 80mm;
            margin: 0 auto;
            padding: 2rem;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px dashed #e0e0e0;
        }

        .receipt-header h1 {
            font-size: 24px;
            margin: 0;
            color: var(--primary);
            font-weight: 600;
        }

        .receipt-header p {
            margin: 5px 0;
            color: var(--secondary);
        }

        .receipt-details {
            margin: 1rem 0;
            padding: 1rem 0;
            border-bottom: 1px dashed #e0e0e0;
        }

        .receipt-details p {
            margin: 5px 0;
            display: flex;
            justify-content: space-between;
        }

        .receipt-items {
            width: 100%;
            margin: 1rem 0;
        }

        .receipt-items th {
            text-align: left;
            padding: 5px 0;
            color: var(--primary);
            font-weight: 600;
            border-bottom: 1px solid #e0e0e0;
        }

        .receipt-items td {
            padding: 5px 0;
        }

        .receipt-items .amount {
            text-align: right;
        }

        .receipt-total {
            margin: 1rem 0;
            padding-top: 1rem;
            border-top: 1px dashed #e0e0e0;
        }

        .receipt-total p {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }

        .receipt-total .final-total {
            font-weight: bold;
            color: var(--primary);
            font-size: 14px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #e0e0e0;
        }

        .receipt-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px dashed #e0e0e0;
            color: var(--secondary);
        }

        .print-actions {
            margin: 2rem auto;
            text-align: center;
        }

        .print-actions .btn {
            margin: 0 0.5rem;
            padding: 0.5rem 1.5rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .print-actions .btn:hover {
            transform: translateY(-2px);
        }

        @media print {
            body {
                padding: 15mm;
                margin: 0;
                background: white;
            }

            .receipt-container {
                box-shadow: none;
                padding: 0;
                margin: 0 auto;
                width: 80mm;
            }

            .print-actions {
                display: none !important;
            }

            @page {
                margin: 15mm;
                size: 80mm auto;
            }
        }
    </style>
</head>
<body>
    <div class="print-actions no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer me-2"></i> Print Receipt
        </button>
        <a href="c_view_order.php?order_id=<?php echo $order_id; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i> Back to Order
        </a>
    </div>

    <div class="receipt-container">
        <div class="receipt-header">
            <h1><?php echo $bakery_name; ?></h1>
            <p><?php echo $bakery_address; ?></p>
            <p>Tel: <?php echo $bakery_contact; ?></p>
            <p><?php echo $bakery_email; ?></p>
            <p><?php echo $bakery_website; ?></p>
        </div>

        <div class="receipt-details">
            <p>
                <span><strong>Order #:</strong></span>
                <span><?php echo htmlspecialchars($order['order_number']); ?></span>
            </p>
            <p>
                <span><strong>Date:</strong></span>
                <span><?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></span>
            </p>
            <p>
                <span><strong>Customer:</strong></span>
                <span><?php echo htmlspecialchars($order['fullname']); ?></span>
            </p>
            <?php if (!empty($order['contact'])): ?>
            <p>
                <span><strong>Contact:</strong></span>
                <span><?php echo htmlspecialchars($order['contact']); ?></span>
            </p>
            <?php endif; ?>
        </div>

        <?php if (!empty($order['delivery_address'])): ?>
        <div class="receipt-details">
            <p><strong>Delivery Address:</strong></p>
            <p><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
        </div>
        <?php endif; ?>

        <table class="receipt-items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="amount">Qty</th>
                    <th class="amount">Price</th>
                    <th class="amount">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $subtotal = 0;
                foreach ($order_items as $item):
                    $item_subtotal = $item['quantity'] * $item['unit_price'];
                    $subtotal += $item_subtotal;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td class="amount"><?php echo $item['quantity']; ?></td>
                    <td class="amount">RM<?php echo number_format($item['unit_price'], 2); ?></td>
                    <td class="amount">RM<?php echo number_format($item_subtotal, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="receipt-total">
            <p>
                <span>Subtotal:</span>
                <span>RM<?php echo number_format($subtotal, 2); ?></span>
            </p>
            <?php if (!empty($order['discount'])): ?>
            <p>
                <span>Discount:</span>
                <span>-RM<?php echo number_format($order['discount'], 2); ?></span>
            </p>
            <?php endif; ?>
            <p class="final-total">
                <span>Total:</span>
                <span>RM<?php echo number_format($order['total_amount'], 2); ?></span>
            </p>
            <p>
                <span>Payment Method:</span>
                <span><?php echo ucfirst(htmlspecialchars($order['payment_method'])); ?></span>
            </p>
            <p>
                <span>Payment Status:</span>
                <span><?php echo ucfirst(htmlspecialchars($order['payment_status'])); ?></span>
            </p>
        </div>

        <div class="receipt-footer">
            <p>Thank you for your order!</p>
            <p>Please keep this receipt for your reference.</p>
            <p>Visit us again!</p>
        </div>
    </div>
</body>
</html>