<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(3); // 3 for customer (formerly guest)
require_once '../config/db_connection.php';

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set page title
$page_title = "Dashboard - Customer";

// Fetch recent orders (last 3 months)
$three_months_ago = date('Y-m-d', strtotime('-3 months'));
$stmt = $conn->prepare("
    SELECT o.*, COUNT(oi.id) as item_count 
    FROM orders o 
    LEFT JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.guest_id = ? 
    AND o.order_date >= ?
    GROUP BY o.id 
    ORDER BY o.order_date DESC 
    LIMIT 5
");
$stmt->bind_param("is", $_SESSION['user']['id'], $three_months_ago);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch featured products (top 4 best-selling products)
$stmt = $conn->prepare("
    SELECT i.*, 
           COUNT(oi.id) as order_count,
           SUM(oi.quantity) as total_sold
    FROM inventory i
    LEFT JOIN order_items oi ON i.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE i.status = 'active' 
    AND i.stock_level > 0
    GROUP BY i.id
    ORDER BY total_sold DESC
    LIMIT 4
");
$stmt->execute();
$featured_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch active promotions
$stmt = $conn->prepare("
    SELECT * FROM promotions 
    WHERE status = 'active' 
    AND NOW() BETWEEN start_date AND end_date 
    LIMIT 3
");
$stmt->execute();
$promotions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get order statistics for this customer
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(total_amount) as total_spent
    FROM orders
    WHERE guest_id = ?
");
$stmt->bind_param("i", $_SESSION['user']['id']);
$stmt->execute();
$order_stats = $stmt->get_result()->fetch_assoc();
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Success Message -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo sanitize_output($_SESSION['success']); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Welcome Banner -->
        <div class="welcome-banner card bg-primary text-white mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-1">Welcome back, <?php echo sanitize_output($_SESSION['user']['fullname']); ?>!</h2>
                        <p class="mb-0 opacity-75">What would you like to order today?</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="c_place_order.php" class="btn btn-light">
                            <i class="bi bi-cart-plus me-2"></i> Place New Order
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Order Statistics & Profile -->
            <div class="col-lg-4 mb-4">
                <!-- Profile Card -->
                <div class="card profile-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">My Profile</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="avatar-circle mb-3">
                                <?php
                                // Generate initials from user's name
                                $initials = '';
                                $name_parts = explode(' ', $_SESSION['user']['fullname']);
                                foreach ($name_parts as $part) {
                                    if (!empty($part)) {
                                        $initials .= strtoupper(substr($part, 0, 1));
                                    }
                                }
                                echo $initials;
                                ?>
                            </div>
                            <h5 class="mb-1"><?php echo sanitize_output($_SESSION['user']['fullname']); ?></h5>
                            <p class="text-muted mb-0"><?php echo sanitize_output($_SESSION['user']['email']); ?></p>
                        </div>
                        
                        <div class="profile-info mt-4">
                            <div class="profile-info-item">
                                <label class="text-muted small">Contact Number</label>
                                <p class="mb-2"><?php echo sanitize_output($_SESSION['user']['contact']); ?></p>
                            </div>
                            <div class="profile-info-item">
                                <label class="text-muted small">Delivery Address</label>
                                <p class="mb-0"><?php echo nl2br(sanitize_output($_SESSION['user']['address'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="d-grid mt-4">
                            <a href="c_profile.php" class="btn btn-outline-primary">
                                <i class="bi bi-pencil me-2"></i> Edit Profile
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Order Stats Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Order Statistics</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-bag text-primary me-2"></i> Total Orders
                                </div>
                                <span class="badge bg-primary rounded-pill"><?php echo $order_stats['total_orders']; ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-hourglass-split text-warning me-2"></i> Pending
                                </div>
                                <span class="badge bg-warning text-dark rounded-pill"><?php echo $order_stats['pending_orders']; ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-check-circle text-success me-2"></i> Completed
                                </div>
                                <span class="badge bg-success rounded-pill"><?php echo $order_stats['completed_orders']; ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-cash-stack text-info me-2"></i> Total Spent
                                </div>
                                <span class="fw-bold">RM<?php echo number_format($order_stats['total_spent'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <a href="c_place_order.php" class="btn btn-primary">
                                <i class="bi bi-cart-plus me-2"></i> Place New Order
                            </a>
                            <a href="c_orders.php" class="btn btn-outline-primary">
                                <i class="bi bi-bag me-2"></i> View All Orders
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Orders & Featured Products -->
            <div class="col-lg-8">
                <!-- Recent Orders -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Orders</h5>
                        <a href="c_orders.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent_orders)): ?>
                            <div class="empty-state py-5 text-center">
                                <i class="bi bi-bag fs-1 text-muted"></i>
                                <h4 class="mt-3">No orders yet</h4>
                                <p class="text-muted">You haven't placed any orders yet. Start shopping to see your order history here.</p>
                                <a href="c_place_order.php" class="btn btn-primary mt-3">
                                    <i class="bi bi-cart-plus me-2"></i> Place Your First Order
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_orders as $order): ?>
                                    <a href="c_view_order.php?order_id=<?php echo $order['id']; ?>" class="list-group-item list-group-item-action p-3">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Order #<?php echo htmlspecialchars($order['order_number']); ?></h6>
                                            <span class="badge bg-<?php 
                                                echo match($order['status']) {
                                                    'completed' => 'success',
                                                    'cancelled' => 'danger',
                                                    'pending' => 'warning',
                                                    default => 'primary'
                                                }; ?>">
                                                <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                                            </span>
                                        </div>
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <p class="mb-1 text-muted">
                                                <i class="bi bi-calendar3 me-1"></i> 
                                                <?php echo date('M d, Y', strtotime($order['order_date'])); ?> · 
                                                <?php echo $order['item_count']; ?> items
                                            </p>
                                            <div class="text-end">
                                                <span class="text-primary fw-bold">
                                                    RM<?php echo number_format($order['total_amount'], 2); ?>
                                                </span>
                                                <?php if (!empty($order['discount'])): ?>
                                                    <div class="small text-success">
                                                        <i class="bi bi-tag-fill"></i> -RM<?php echo number_format($order['discount'], 2); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Featured Products -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Featured Products</h5>
                        <a href="c_place_order.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($featured_products as $product): ?>
                                <div class="col-md-6 col-lg-3 mb-4">
                                    <div class="product-card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo sanitize_output($product['product_name']); ?></h6>
                                            <div class="price mb-2">RM<?php echo number_format($product['unit_price'], 2); ?></div>
                                            <p class="card-text small text-muted mb-3">
                                                <?php echo sanitize_output(substr($product['description'] ?? 'Fresh from our bakery', 0, 50)); ?>...
                                            </p>
                                            <a href="c_place_order.php?product=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">
                                                Order Now
                                            </a>
                                            <span class="badge <?php echo $product['stock_level'] <= 10 ? 'bg-warning' : 'bg-success'; ?> stock-badge">
                                                <?php echo $product['stock_level']; ?> in stock
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Special Offers -->
                <?php if (!empty($promotions)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Special Offers</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($promotions as $promo): ?>
                                <div class="special-offer-card mb-3">
                                    <div class="row">
                                        <div class="col-md-2 d-flex align-items-center justify-content-center mb-3 mb-md-0">
                                            <i class="bi bi-gift text-primary" style="font-size: 2.5rem;"></i>
                                        </div>
                                        <div class="col-md-10">
                                            <h5 class="mb-2"><?php echo sanitize_output($promo['code']); ?></h5>
                                            <p class="mb-2"><?php echo sanitize_output($promo['description']); ?></p>
                                            <small class="text-muted">
                                                Valid until <?php echo date('F j, Y', strtotime($promo['end_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styles for customer dashboard */
.welcome-banner {
    border-radius: 10px;
    background: linear-gradient(135deg, #0561FC 0%, #0453d6 100%);
    box-shadow: 0 4px 15px rgba(5, 97, 252, 0.2);
    border: none;
}

.avatar-circle {
    width: 80px;
    height: 80px;
    background-color: #0561FC;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    font-weight: 600;
    margin: 0 auto;
}

.profile-info-item label {
    margin-bottom: 5px;
    display: block;
}

.empty-state i {
    opacity: 0.3;
}

.product-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid #eaeaea;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0,0,0,0.1);
}

.product-card .price {
    font-weight: 600;
    color: #0561FC;
}

.stock-badge {
    font-size: 0.7rem;
    padding: 0.25em 0.6em;
    vertical-align: middle;
}

.special-offer-card {
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    transition: all 0.3s ease;
}

.special-offer-card:hover {
    background-color: #e9ecef;
    transform: translateX(5px);
}
</style>

<?php include '../includes/footer.php'; ?>