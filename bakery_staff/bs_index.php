<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(2); // 2 for bakery staff (formerly clerk)
require_once '../config/db_connection.php';

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set page title
$page_title = "Dashboard - Bakery Staff";

// Fetch recent completed orders (last 7 days)
$last_week = date('Y-m-d', strtotime('-7 days'));
$stmt = $conn->prepare("
    SELECT 
        o.id,
        o.order_number,
        o.order_date,
        o.total_amount,
        o.payment_method,
        o.status,
        GROUP_CONCAT(
            CONCAT(oi.quantity, 'x ', i.product_name, ' (RM', FORMAT(oi.unit_price, 2), ')')
            SEPARATOR ', '
        ) as items
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    LEFT JOIN inventory i ON oi.product_id = i.id
    WHERE o.clerk_id = ? AND o.status IN ('completed', 'approved')
    AND o.order_date >= ?
    GROUP BY o.id 
    ORDER BY o.order_date DESC 
    LIMIT 5
");
$stmt->bind_param("is", $_SESSION['user']['id'], $last_week);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch pending online orders
$stmt = $conn->prepare("
    SELECT 
        o.id,
        o.order_number,
        o.order_date,
        o.total_amount,
        o.payment_method,
        o.delivery_address,
        o.discount,
        o.promo_code,
        g.fullname as customer_name,
        g.contact as customer_contact,
        GROUP_CONCAT(
            CONCAT(oi.quantity, 'x ', i.product_name)
            SEPARATOR ', '
        ) as items
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

// Get today's sales data
$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as order_count,
        SUM(total_amount) as total_sales,
        SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) as cash_sales,
        SUM(CASE WHEN payment_method = 'card' THEN total_amount ELSE 0 END) as card_sales,
        SUM(CASE WHEN payment_method = 'online' THEN total_amount ELSE 0 END) as online_sales
    FROM orders
    WHERE DATE(order_date) = ?
    AND clerk_id = ?
    AND status IN ('completed', 'approved')
");
$stmt->bind_param("si", $today, $_SESSION['user']['id']);
$stmt->execute();
$today_sales = $stmt->get_result()->fetch_assoc();

// Get count of pending orders
$stmt = $conn->prepare("
    SELECT COUNT(*) as pending_count
    FROM orders
    WHERE status = 'pending'
    AND order_type = 'online'
");
$stmt->execute();
$pending_count = $stmt->get_result()->fetch_assoc()['pending_count'];

// Get low stock and out of stock products alert
$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN stock_level <= 10 AND stock_level > 0 AND status = 'active' THEN 1 ELSE 0 END) as low_stock_count,
        SUM(CASE WHEN stock_level = 0 AND status = 'active' THEN 1 ELSE 0 END) as out_of_stock_count
    FROM inventory
");
$stmt->execute();
$stock_alert = $stmt->get_result()->fetch_assoc();
$low_stock_count = $stock_alert['low_stock_count'];
$out_of_stock_count = $stock_alert['out_of_stock_count'];
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Welcome Banner -->
        <div class="card bg-primary text-white mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">Welcome, <?php echo sanitize_output($_SESSION['user']['fullname']); ?>!</h2>
                        <p class="mb-0">Today is <?php echo date('l, F j, Y'); ?></p>
                    </div>
                    <div class="text-center">
                        <h4 class="mb-0">RM<?php echo number_format($today_sales['total_sales'] ?? 0, 2); ?></h4>
                        <p class="mb-0 opacity-75">Today's Sales</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts Section -->
        <?php if ($pending_count > 0 || $low_stock_count > 0 || $out_of_stock_count > 0): ?>
            <div class="alerts-section mb-4">
                <?php if ($pending_count > 0): ?>
                    <div class="alert alert-warning d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-4"></i>
                        <div>
                            <strong>Attention!</strong> There are <strong><?php echo $pending_count; ?></strong> online orders awaiting approval.
                            <a href="bs_orders.php" class="alert-link ms-2">View Orders</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($out_of_stock_count > 0): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="bi bi-exclamation-octagon-fill me-2 fs-4"></i>
                        <div>
                            <strong>Critical Alert:</strong> There are <strong><?php echo $out_of_stock_count; ?></strong> products out of stock!
                            Immediate attention required.
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($low_stock_count > 0): ?>
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="bi bi-info-circle-fill me-2 fs-4"></i>
                        <div>
                            <strong>Inventory Alert:</strong> There are <strong><?php echo $low_stock_count; ?></strong> products with low stock.
                            Please inform the sales manager.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo sanitize_output($_SESSION['success']); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo sanitize_output($_SESSION['error']); unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Stats & Quick Actions -->
        <div class="row mb-4">
            <!-- Stats Cards -->
            <div class="col-md-8">
                <div class="row">
                    <!-- Today's Order Count -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Today's Orders
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $today_sales['order_count'] ?? 0; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-receipt fs-2 text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cash Sales -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Cash Sales
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            RM<?php echo number_format($today_sales['cash_sales'] ?? 0, 2); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-cash-coin fs-2 text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card Sales -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Card Sales
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            RM<?php echo number_format($today_sales['card_sales'] ?? 0, 2); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-credit-card fs-2 text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Online Sales -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Online Sales
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            RM<?php echo number_format($today_sales['online_sales'] ?? 0, 2); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-bank fs-2 text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Orders -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Orders</h6>
                        <a href="bs_completed_orders.php" class="btn btn-sm btn-primary">
                            View All
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_orders)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">No recent orders</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                                <td><?php echo date('M d, H:i', strtotime($order['order_date'])); ?></td>
                                                <td>RM<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo match($order['status']) {
                                                            'completed' => 'success',
                                                            'approved' => 'primary',
                                                            default => 'secondary'
                                                        }; ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="bs_print_receipt.php?order_id=<?php echo $order['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Print Receipt">
                                                        <i class="bi bi-printer"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions and Pending Orders -->
            <div class="col-md-4">
                <!-- Quick Actions -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <a href="bs_new_order.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-cart-plus me-2"></i> Create New Order
                            </a>
                            <a href="bs_orders.php" class="btn btn-outline-primary">
                                <i class="bi bi-list-check me-2"></i> Manage Pending Orders
                                <?php if ($pending_count > 0): ?>
                                <span class="badge bg-danger ms-2"><?php echo $pending_count; ?></span>
                                <?php endif; ?>
                            </a>                            <a href="bs_completed_orders.php" class="btn btn-outline-secondary">
                                <i class="bi bi-journal-check me-2"></i> Order History
                            </a>
                            <a href="bs_feedback.php" class="btn btn-outline-info">
                                <i class="bi bi-chat-square-text me-2"></i> Customer Feedback
                            </a>
                            <a href="bs_profile.php" class="btn btn-outline-secondary">
                                <i class="bi bi-person me-2"></i> My Profile
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Online Orders -->
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Pending Online Orders</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($pending_orders)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-check-circle fs-1"></i>
                                <p class="mt-2">No pending orders</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($pending_orders, 0, 3) as $order): ?>
                                    <div class="list-group-item p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($order['order_number']); ?></h6>
                                            <span class="badge bg-warning">Pending</span>
                                        </div>
                                        <p class="mb-2 small">
                                            <strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                            <strong>Amount:</strong>
                                            <div class="me-2">
                                                <span class="fw-bold fs-4 text-primary">
                                                    RM<?php echo number_format($order['total_amount'], 2); ?>
                                                </span>
                                                <?php if (!empty($order['discount'])): ?>
                                                    <div class="small text-success">
                                                        <i class="bi bi-tag-fill"></i> -RM<?php echo number_format($order['discount'], 2); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <strong>Date:</strong> <?php echo date('M d, H:i', strtotime($order['order_date'])); ?>
                                        </p>
                                        <div class="d-flex justify-content-between">
                                            <form method="POST" action="bs_orders.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" 
                                                       value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="order_action" value="approve">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="bi bi-check-circle me-1"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" action="bs_orders.php" class="d-inline">
                                                <input type="hidden" name="csrf_token" 
                                                       value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <input type="hidden" name="order_action" value="cancel">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-x-circle me-1"></i> Cancel
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($pending_orders) > 3): ?>
                                <div class="card-footer text-center py-2">
                                    <a href="bs_orders.php" class="text-primary">View All <?php echo count($pending_orders); ?> Pending Orders</a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom Styles */
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
.text-gray-300 {
    color: rgba(0, 0, 0, 0.2) !important;
}
.text-gray-800 {
    color: #333 !important;
}

/* Animation for alerts */
@keyframes slideInDown {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.alert {
    animation: slideInDown 0.3s ease-out;
}
</style>

<?php include '../includes/footer.php'; ?>