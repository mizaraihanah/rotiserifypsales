<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(2); // 2 for bakery staff (formerly clerk)
require_once '../config/db_connection.php';
require_once '../includes/functions/order_functions.php';

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle approve/cancel actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_action'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token. Please try again.';
        header("Location: bs_orders.php");
        exit();
    }

    try {
        // Validate order_id and order_action
        $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
        $order_action = filter_input(INPUT_POST, 'order_action', FILTER_SANITIZE_STRING);

        if (!$order_id || !in_array($order_action, ['approve', 'cancel'])) {
            throw new Exception("Invalid input parameters.");
        }

        $clerk_id = $_SESSION['user']['id'];

        $conn->begin_transaction();

        // Check if order is still pending
        $check_stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
        $check_stmt->bind_param("i", $order_id);
        $check_stmt->execute();
        $order_status = $check_stmt->get_result()->fetch_assoc();
        
        if (!$order_status || $order_status['status'] !== 'pending') {
            throw new Exception("This order has already been processed.");
        }

        if ($order_action === 'approve') {
            $stmt = $conn->prepare("
                UPDATE orders 
                SET status = 'completed', 
                    clerk_id = ?,
                    payment_status = 'paid'
                WHERE id = ?
            ");
        } else {
            $stmt = $conn->prepare("
                UPDATE orders 
                SET status = 'cancelled', 
                    clerk_id = ?
                WHERE id = ?
            ");
        }

        $stmt->bind_param("ii", $clerk_id, $order_id);

        if (!$stmt->execute()) {
            throw new Exception("Database error occurred.");
        }

        // Restore inventory stock for cancelled orders
        if ($order_action === 'cancel') {
            $restore_stmt = $conn->prepare("
                UPDATE inventory i
                JOIN order_items oi ON i.id = oi.product_id
                SET i.stock_level = i.stock_level + oi.quantity
                WHERE oi.order_id = ?
            ");
            $restore_stmt->bind_param("i", $order_id);

            if (!$restore_stmt->execute()) {
                throw new Exception("Error restoring inventory levels.");
            }
        }

        $conn->commit();
        $_SESSION['success'] = $order_action === 'approve' ? "Order approved and marked as completed!" : "Order cancelled successfully!";
        header("Location: bs_orders.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: bs_orders.php");
        exit();
    }
}

// Fetch pending online orders
$stmt = $conn->prepare("
    SELECT 
        o.id,
        o.order_number,
        o.order_date,
        o.total_amount,
        o.payment_method,
        o.delivery_address,
        g.fullname as customer_name,
        g.contact as customer_contact,
        g.email as customer_email,
        GROUP_CONCAT(
            CONCAT(oi.quantity, 'x ', i.product_name)
            SEPARATOR ', '
        ) as items,
        TIMESTAMPDIFF(HOUR, o.order_date, NOW()) as hours_ago,
        o.discount
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    LEFT JOIN inventory i ON oi.product_id = i.id
    LEFT JOIN guest g ON o.guest_id = g.id
    WHERE o.status = 'pending' 
    AND o.order_type = 'online'
    GROUP BY o.id 
    ORDER BY o.order_date ASC
");
$stmt->execute();
$pending_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$page_title = "Order Management - Bakery Staff";
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Pending Orders</h1>
                <p class="text-muted">Manage online order requests</p>
            </div>
            <div>
                <a href="bs_new_order.php" class="btn btn-primary">
                    <i class="bi bi-cart-plus me-2"></i> Create New Order
                </a>
            </div>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Pending Orders -->
        <?php if (empty($pending_orders)): ?>
            <div class="card shadow">
                <div class="card-body text-center py-5">
                    <h4 class="text-muted">No Pending Orders</h4>
                    <p class="text-muted mb-0">There are no online orders waiting for approval right now.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($pending_orders as $order): ?>
                    <div class="col-xl-6 col-lg-12 mb-4">
                        <div class="card shadow order-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <span class="badge bg-warning me-2">Pending</span>
                                    Order #<?php echo htmlspecialchars($order['order_number']); ?>
                                </h5>
                                <span class="text-muted small">
                                    <?php 
                                    if ($order['hours_ago'] < 1) {
                                        echo 'Just now';
                                    } elseif ($order['hours_ago'] == 1) {
                                        echo '1 hour ago';
                                    } elseif ($order['hours_ago'] < 24) {
                                        echo $order['hours_ago'] . ' hours ago';
                                    } else {
                                        echo floor($order['hours_ago'] / 24) . ' days ago';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h6 class="mb-2"><i class="bi bi-person-circle me-2"></i> Customer Details</h6>
                                        <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                        <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($order['customer_contact']); ?></p>
                                        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="mb-2"><i class="bi bi-geo-alt me-2"></i> Delivery Address</h6>
                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="mb-2"><i class="bi bi-cart me-2"></i> Order Items</h6>
                                    <div class="card bg-light">
                                        <div class="card-body py-2">
                                            <?php 
                                            $items_array = explode(', ', $order['items']);
                                            foreach ($items_array as $item): 
                                            ?>
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span><?php echo htmlspecialchars($item); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <div class="fw-bold">
                                            <span class="fs-4 text-primary">
                                                RM<?php echo number_format($order['total_amount'], 2); ?>
                                            </span>
                                            <?php if (!empty($order['discount'])): ?>
                                                <div class="small text-success">
                                                    <i class="bi bi-tag-fill"></i> -RM<?php echo number_format($order['discount'], 2); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-6 text-end">
                                        <span class="badge bg-<?php 
                                            echo match($order['payment_method']) {
                                                'cash' => 'success',
                                                'card' => 'info',
                                                'online' => 'primary',
                                                default => 'secondary'
                                            }; 
                                        ?> me-2">
                                            <?php echo ucfirst($order['payment_method']); ?>
                                        </span>
                                        <span class="text-muted"><?php echo date('M d, H:i', strtotime($order['order_date'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#orderDetailsModal<?php echo $order['id']; ?>">
                                        <i class="bi bi-info-circle me-1"></i> View Details
                                    </button>
                                    <div>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <input type="hidden" name="order_action" value="approve">
                                            <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to approve this order?')">
                                                <i class="bi bi-check-circle me-1"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <input type="hidden" name="order_action" value="cancel">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this order?')">
                                                <i class="bi bi-x-circle me-1"></i> Cancel
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Details Modal -->
                        <div class="modal fade" id="orderDetailsModal<?php echo $order['id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Order #<?php echo htmlspecialchars($order['order_number']); ?> Details</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <h6 class="border-bottom pb-2 mb-3">Customer Information</h6>
                                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                                <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($order['customer_contact']); ?></p>
                                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="border-bottom pb-2 mb-3">Order Information</h6>
                                                <p class="mb-1"><strong>Order Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></p>
                                                <p class="mb-1"><strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method']); ?></p>
                                                <p class="mb-1"><strong>Total Amount:</strong> RM<?php echo number_format($order['total_amount'], 2); ?></p>
                                            </div>
                                        </div>
                                        
                                        <h6 class="border-bottom pb-2 mb-3">Delivery Address</h6>
                                        <p class="mb-4"><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
                                        
                                        <h6 class="border-bottom pb-2 mb-3">Order Items</h6>
                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Item</th>
                                                        <th class="text-center">Quantity</th>
                                                        <th class="text-end">Unit Price</th>
                                                        <th class="text-end">Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    // Fetch order items details
                                                    $items_stmt = $conn->prepare("
                                                        SELECT oi.quantity, i.product_name, oi.unit_price, 
                                                               (oi.quantity * oi.unit_price) as subtotal
                                                        FROM order_items oi
                                                        JOIN inventory i ON oi.product_id = i.id
                                                        WHERE oi.order_id = ?
                                                    ");
                                                    $items_stmt->bind_param("i", $order['id']);
                                                    $items_stmt->execute();
                                                    $items_result = $items_stmt->get_result();
                                                    $subtotal = 0;
                                                    
                                                    while ($item = $items_result->fetch_assoc()):
                                                        $subtotal += $item['subtotal'];
                                                    ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                                                            <td class="text-end">RM<?php echo number_format($item['unit_price'], 2); ?></td>
                                                            <td class="text-end">RM<?php echo number_format($item['subtotal'], 2); ?></td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="3" class="text-end">Subtotal:</td>
                                                        <td class="text-end">RM<?php echo number_format($subtotal, 2); ?></td>
                                                    </tr>
                                                    <?php if (!empty($order['discount'])): ?>
                                                    <tr class="text-success">
                                                        <td colspan="3" class="text-end">
                                                            <i class="bi bi-tag-fill"></i> Discount:
                                                        </td>
                                                        <td class="text-end">-RM<?php echo number_format($order['discount'], 2); ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <tr class="fw-bold">
                                                        <td colspan="3" class="text-end">Final Total:</td>
                                                        <td class="text-end">RM<?php echo number_format($order['total_amount'], 2); ?></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="modal-footer justify-content-between">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <div>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="order_action" value="approve">
                                                <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to approve this order?')">
                                                    <i class="bi bi-check-circle me-1"></i> Approve Order
                                                </button>
                                            </form>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="order_action" value="cancel">
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this order?')">
                                                    <i class="bi bi-x-circle me-1"></i> Cancel Order
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- No Active Orders Card -->
        <?php if (empty($pending_orders)): ?>
            <div class="card shadow mt-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <a href="bs_new_order.php" class="card h-100 text-center p-4 text-decoration-none">
                                <div class="mb-3">
                                    <i class="bi bi-cart-plus fs-1 text-primary"></i>
                                </div>
                                <h5>Create New Order</h5>
                                <p class="text-muted mb-0">Process an in-store purchase</p>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="bs_completed_orders.php" class="card h-100 text-center p-4 text-decoration-none">
                                <div class="mb-3">
                                    <i class="bi bi-journal-check fs-1 text-success"></i>
                                </div>
                                <h5>Order History</h5>
                                <p class="text-muted mb-0">View completed orders</p>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="bs_index.php" class="card h-100 text-center p-4 text-decoration-none">
                                <div class="mb-3">
                                    <i class="bi bi-speedometer2 fs-1 text-info"></i>
                                </div>
                                <h5>Dashboard</h5>
                                <p class="text-muted mb-0">Return to dashboard</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Custom styles */
.order-card {
    transition: all 0.2s ease;
}

.order-card:hover {
    transform: translateY(-5px);
}

/* Border styles for cards */
.border-left-primary {
    border-left: 4px solid #0561FC !important;
}
.border-left-success {
    border-left: 4px solid #28a745 !important;
}
.border-left-info {
    border-left: 4px solid #17a2b8 !important;
}
.border-left-warning {
    border-left: 4px solid #ffc107 !important;
}
</style>

<?php include '../includes/footer.php'; ?>