<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(2); // 2 for bakery staff (formerly clerk)
require_once '../config/db_connection.php';

// Check if order_id is provided
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    $_SESSION['error'] = 'Invalid order ID.';
    header("Location: bs_completed_orders.php");
    exit();
}

$order_id = (int)$_GET['order_id'];

// Fetch order details
$stmt = $conn->prepare("
    SELECT 
        o.order_number,
        o.order_date,
        o.total_amount,
        o.payment_method,
        o.order_type,
        o.payment_status,
        o.delivery_address,
        o.subtotal,
        o.discount,
        o.promo_code,
        g.fullname as customer_name,
        g.contact as customer_contact,
        g.email as customer_email
    FROM orders o
    LEFT JOIN guest g ON o.guest_id = g.id
    WHERE o.id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    $_SESSION['error'] = 'Order not found.';
    header("Location: bs_completed_orders.php");
    exit();
}

// Get order items
$stmt = $conn->prepare("
    SELECT oi.*, i.product_name 
    FROM order_items oi
    JOIN inventory i ON oi.product_id = i.id
    WHERE oi.order_id = ?
    ORDER BY i.product_name
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get bakery information
$bakery_name = "RotiSeri Bakery";
$bakery_address = "123 Main Street, City Center";
$bakery_contact = "+60-1234-5678";
$bakery_email = "info@rotiseribakery.com";
$bakery_website = "www.rotiseribakery.com";

// Set page title
$page_title = "Print Receipt - Bakery Staff";
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Receipt</h1>
                <p class="text-muted">Order #<?php echo htmlspecialchars($order['order_number']); ?></p>
            </div>
            <div>
                <button class="btn btn-primary me-2" id="printButton">
                    <i class="bi bi-printer me-2"></i> Print Receipt
                </button>
                <a href="<?php echo $_SERVER['HTTP_REFERER'] ?? 'bs_completed_orders.php'; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i> Back
                </a>
            </div>
        </div>
        
        <!-- Receipt Container -->
        <div class="receipt-container" id="receiptContainer">
            <div class="receipt-header">
                <div class="text-center mb-4">
                    <h2><?php echo $bakery_name; ?></h2>
                    <p class="mb-0"><?php echo $bakery_address; ?></p>
                    <p class="mb-0">Tel: <?php echo $bakery_contact; ?></p>
                    <p class="mb-0"><?php echo $bakery_email; ?></p>
                </div>
                
                <div class="row mb-4">
                    <div class="col-6">
                        <h5 class="border-bottom pb-2 mb-2">Receipt</h5>
                        <p class="mb-1">
                            <strong>Order #:</strong> <?php echo htmlspecialchars($order['order_number']); ?>
                        </p>
                        <p class="mb-1">
                            <strong>Date:</strong> <?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?>
                        </p>
                        <p class="mb-1">
                            <strong>Type:</strong> <?php echo ucfirst($order['order_type']); ?>
                        </p>
                        <p class="mb-1">
                            <strong>Payment:</strong> <?php echo ucfirst($order['payment_method']); ?> 
                            (<?php echo ucfirst($order['payment_status']); ?>)
                        </p>
                        <?php if (!empty($order['promo_code'])): ?>
                            <p class="mb-1">
                                <strong>Promo Code:</strong> <?php echo htmlspecialchars($order['promo_code']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-6">
                        <h5 class="border-bottom pb-2 mb-2">Customer Details</h5>
                        <p class="mb-1">
                            <strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?>
                        </p>
                        <p class="mb-1">
                            <strong>Contact:</strong> <?php echo htmlspecialchars($order['customer_contact']); ?>
                        </p>
                        <?php if (!empty($order['customer_email'])): ?>
                            <p class="mb-1">
                                <strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($order['delivery_address']) && $order['order_type'] === 'online'): ?>
                    <div class="mb-4">
                        <h5 class="border-bottom pb-2 mb-2">Delivery Address</h5>
                        <p><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="receipt-body">
                <h5 class="border-bottom pb-2 mb-3">Order Items</h5>
                <table class="table receipt-items">
                    <thead>
                        <tr>
                            <th width="50%">Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $subtotal = 0;
                        foreach ($order_items as $item): 
                            $subtotal += $item['subtotal'];
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td class="text-center"><?php echo $item['quantity']; ?></td>
                                <td class="text-end">RM<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="text-end">RM<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <!-- Subtotal row -->
                        <tr>
                            <td colspan="3" class="text-end">Subtotal:</td>
                            <td class="text-end">RM<?php echo number_format($order['subtotal'], 2); ?></td>
                        </tr>
                        
                        <!-- Discount row, if applicable -->
                        <?php if (!empty($order['discount'])): ?>
                        <tr>
                            <td colspan="3" class="text-end text-success">
                                <i class="bi bi-tag-fill"></i> Discount:
                                <?php if (!empty($order['promo_code'])): ?>
                                    <span class="small">(<?php echo htmlspecialchars($order['promo_code']); ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-success">-RM<?php echo number_format($order['discount'], 2); ?></td>
                        </tr>
                        <?php endif; ?>

                        <!-- Total row -->
                        <tr class="table-light fw-bold">
                            <td colspan="3" class="text-end">Total:</td>
                            <td class="text-end">RM<?php echo number_format($order['total_amount'], 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class="receipt-footer">
                <div class="text-center mt-5">
                    <p class="mb-1">Thank you for your purchase!</p>
                    <p class="mb-1">Please retain this receipt for your reference.</p>
                    <p class="mb-3 small">Receipt generated on <?php echo date('Y-m-d H:i:s'); ?></p>
                    <div class="mb-4 mt-4">
                        <svg id="barcode"></svg>
                    </div>
                    <p class="mb-0 small">
                        CUSTOMER COPY
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include JsBarcode library -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<script>
// Print functionality
document.getElementById('printButton').addEventListener('click', function() {
    window.print();
});

// Generate barcode
document.addEventListener('DOMContentLoaded', function() {
    JsBarcode("#barcode", "<?php echo $order['order_number']; ?>", {
        format: "CODE128",
        lineColor: "#000",
        width: 2,
        height: 50,
        displayValue: true
    });
});
</script>

<style>
/* General styles for receipt */
.receipt-container {
    max-width: 800px;
    margin: 0 auto 40px;
    padding: 30px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.receipt-header h2 {
    font-size: 24px;
    margin-bottom: 5px;
}

.receipt-header p {
    color: #6c757d;
    margin-bottom: 5px;
    font-size: 14px;
}

.receipt-items {
    width: 100%;
    border-collapse: collapse;
}

.receipt-items th {
    background-color: #f8f9fa;
    padding: 12px;
    border-bottom: 2px solid #e3e6f0;
    font-weight: 600;
}

.receipt-items td {
    padding: 12px;
    border-bottom: 1px solid #e3e6f0;
}

.receipt-items tfoot th {
    border-top: 2px solid #e3e6f0;
    border-bottom: none;
    padding: 12px;
    font-weight: 700;
}

.receipt-footer {
    color: #6c757d;
    font-size: 14px;
}

/* Print styles */
@media print {
    body * {
        visibility: hidden;
    }
    
    .receipt-container, .receipt-container * {
        visibility: visible;
    }
    
    .receipt-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none;
        border: none;
        padding: 15px;
    }
    
    .main-content {
        margin-left: 0;
        padding: 0;
    }
    
    /* Hide buttons and navigation */
    .sidebar-container, 
    .main-content > .d-flex, 
    .btn,
    button,
    .nav {
        display: none !important;
    }
}
</style>

<?php include '../includes/footer.php'; ?>