<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(2); // 2 for bakery staff (formerly clerk)
require_once '../config/db_connection.php';

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$order_type = isset($_GET['order_type']) ? $_GET['order_type'] : '';

// Build the WHERE clause
$where_conditions = ["(o.clerk_id = ? AND o.status IN ('completed', 'approved'))"];
$params = [$_SESSION['user']['id']];
$param_types = "i";

if ($search) {
    $where_conditions[] = "(o.order_number LIKE ? OR g.fullname LIKE ? OR g.contact LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= "sss";
}

if ($date_from) {
    $where_conditions[] = "DATE(o.order_date) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if ($date_to) {
    $where_conditions[] = "DATE(o.order_date) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

if ($payment_method) {
    $where_conditions[] = "o.payment_method = ?";
    $params[] = $payment_method;
    $param_types .= "s";
}

if ($order_type) {
    $where_conditions[] = "o.order_type = ?";
    $params[] = $order_type;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total records for pagination
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM orders o
    LEFT JOIN guest g ON o.guest_id = g.id 
    WHERE $where_clause
");
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$total_records = $stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_records / $records_per_page);

// Fetch orders
$stmt = $conn->prepare("
    SELECT 
        o.*,
        g.fullname as customer_name,
        g.contact as customer_contact,
        g.email as customer_email
    FROM orders o
    LEFT JOIN guest g ON o.guest_id = g.id
    WHERE $where_clause
    ORDER BY o.order_date DESC
    LIMIT ? OFFSET ?
");

$params[] = $records_per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Add order items to each order
foreach ($orders as &$order) {
    $items_stmt = $conn->prepare("
        SELECT 
            oi.quantity,
            i.product_name,
            oi.unit_price,
            (oi.quantity * oi.unit_price) as subtotal
        FROM order_items oi
        JOIN inventory i ON oi.product_id = i.id
        WHERE oi.order_id = ?
    ");
    $items_stmt->bind_param("i", $order['id']);
    $items_stmt->execute();
    $items = $items_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Format items for display
    $items_display = array();
    foreach ($items as $item) {
        $items_display[] = "{$item['quantity']}x {$item['product_name']} (RM" . number_format($item['unit_price'], 2) . ")";
    }
    $order['items'] = implode(', ', $items_display);
    $order['order_items'] = $items; // Store full items data for modal
}
unset($order); // Unset reference

// Get order statistics based on current filters (except pagination)
$stats_where_clause = implode(" AND ", $where_conditions);
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(total_amount) as total_sales,
        SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) as cash_sales,
        SUM(CASE WHEN payment_method = 'card' THEN total_amount ELSE 0 END) as card_sales,
        SUM(CASE WHEN payment_method = 'online' THEN total_amount ELSE 0 END) as online_sales,
        SUM(CASE WHEN order_type = 'in-store' THEN 1 ELSE 0 END) as in_store_orders,
        SUM(CASE WHEN order_type = 'online' THEN 1 ELSE 0 END) as online_orders
    FROM orders o
    LEFT JOIN guest g ON o.guest_id = g.id 
    WHERE $stats_where_clause
");
$stats_param_types = substr($param_types, 0, -2);
$stats_params = array_slice($params, 0, -2);
$stats_stmt->bind_param($stats_param_types, ...$stats_params);
$stats_stmt->execute();
$order_stats = $stats_stmt->get_result()->fetch_assoc();

// Set page title
$page_title = "Order History - Bakery Staff";
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Order History</h1>
                <p class="text-muted">View and search your completed orders</p>
            </div>
            <div>
                <a href="bs_new_order.php" class="btn btn-primary">
                    <i class="bi bi-cart-plus me-2"></i> Create New Order
                </a>
            </div>
        </div>
        
        <!-- Order Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Orders
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($order_stats['total_orders']); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-receipt fs-2 text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Total Sales
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    RM<?php echo number_format($order_stats['total_sales'], 2); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-cash-stack fs-2 text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    In-Store Orders
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($order_stats['in_store_orders']); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-shop fs-2 text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Online Orders
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($order_stats['online_orders']); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-laptop fs-2 text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Search and Filter -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" name="search" class="form-control" placeholder="Order #, customer..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Date Range</label>
                        <div class="input-group">
                            <input type="date" name="date_from" class="form-control" 
                                   value="<?php echo htmlspecialchars($date_from); ?>" placeholder="From Date">
                            <span class="input-group-text bg-white">to</span>
                            <input type="date" name="date_to" class="form-control" 
                                   value="<?php echo htmlspecialchars($date_to); ?>" placeholder="To Date">
                        </div>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="">All Methods</option>
                            <option value="cash" <?php echo $payment_method === 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="card" <?php echo $payment_method === 'card' ? 'selected' : ''; ?>>Card</option>
                            <option value="online" <?php echo $payment_method === 'online' ? 'selected' : ''; ?>>Online</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Order Type</label>
                        <select name="order_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="in-store" <?php echo $order_type === 'in-store' ? 'selected' : ''; ?>>In-Store</option>
                            <option value="online" <?php echo $order_type === 'online' ? 'selected' : ''; ?>>Online</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-1"></i> Filter
                            </button>
                            <a href="bs_completed_orders.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Completed Orders</h6>
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                    <button class="btn btn-sm btn-outline-primary" onclick="exportTableToCSV('orders_export.csv')">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($orders)): ?>
                    <div class="text-center py-5">
                        <img src="../assets/img/empty-data.svg" alt="No Orders" class="mb-3" style="max-width: 150px; opacity: 0.5;">
                        <h4 class="text-muted">No Orders Found</h4>
                        <p class="text-muted mb-0">Try adjusting your search or filter criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="ordersTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Date/Time</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Payment</th>
                                    <th>Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></td>
                                        <td>
                                            <?php if (!empty($order['customer_name'])): ?>
                                                <div><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                                <?php if (!empty($order['customer_contact'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($order['customer_contact']); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Guest</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-link p-0" 
                                                    data-bs-toggle="popover" 
                                                    data-bs-trigger="focus" 
                                                    data-bs-html="true"
                                                    data-bs-title="Order Items" 
                                                    data-bs-content="<?php echo htmlspecialchars(str_replace(', ', '<br>', $order['items'])); ?>">
                                                View Items
                                            </button>
                                        </td>
                                        <td class="fw-bold">
                                            RM<?php echo number_format($order['total_amount'], 2); ?>
                                            <?php if (!empty($order['discount'])): ?>
                                                <div class="small text-success">
                                                    <i class="bi bi-tag-fill"></i> -RM<?php echo number_format($order['discount'], 2); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($order['payment_method']) {
                                                    'cash' => 'success',
                                                    'card' => 'info',
                                                    'online' => 'primary',
                                                    default => 'secondary'
                                                }; ?>">
                                                <?php echo ucfirst($order['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo $order['order_type'] === 'in-store' ? 'In-Store' : 'Online'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="bs_print_receipt.php?order_id=<?php echo $order['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Print Receipt">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#orderDetailsModal<?php echo $order['id']; ?>"
                                                        title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
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
                                                                    <?php if (!empty($order['customer_email'])): ?>
                                                                        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <h6 class="border-bottom pb-2 mb-3">Order Information</h6>
                                                                    <p class="mb-1"><strong>Order Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></p>
                                                                    <p class="mb-1"><strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method']); ?></p>
                                                                    <p class="mb-1"><strong>Order Type:</strong> <?php echo $order['order_type'] === 'in-store' ? 'In-Store' : 'Online'; ?></p>
                                                                    <p class="mb-1"><strong>Total Amount:</strong> RM<?php echo number_format($order['total_amount'], 2); ?></p>
                                                                </div>
                                                            </div>
                                                            
                                                            <?php if (!empty($order['delivery_address'])): ?>
                                                                <h6 class="border-bottom pb-2 mb-3">Delivery Address</h6>
                                                                <p class="mb-4"><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
                                                            <?php endif; ?>
                                                            
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
                                                                        $subtotal = 0;
                                                                        foreach ($order['order_items'] as $item):
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
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            <a href="bs_print_receipt.php?order_id=<?php echo $order['id']; ?>" class="btn btn-primary">
                                                                <i class="bi bi-printer me-1"></i> Print Receipt
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&payment_method=<?php echo urlencode($payment_method); ?>&order_type=<?php echo urlencode($order_type); ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php 
                            // Determine which page numbers to show
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            // Show first page if not in range
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '&payment_method=' . urlencode($payment_method) . '&order_type=' . urlencode($order_type) . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            // Show page numbers
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '&payment_method=' . urlencode($payment_method) . '&order_type=' . urlencode($order_type) . '">' . $i . '</a></li>';
                            }
                            
                            // Show last page if not in range
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '&payment_method=' . urlencode($payment_method) . '&order_type=' . urlencode($order_type) . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&payment_method=<?php echo urlencode($payment_method); ?>&order_type=<?php echo urlencode($order_type); ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Initialize popovers
document.addEventListener('DOMContentLoaded', function () {
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl)
    });
    
    // Make popovers work on dynamically added elements
    document.body.addEventListener('click', function(e) {
        if (e.target.getAttribute('data-bs-toggle') === 'popover') {
            var popover = bootstrap.Popover.getInstance(e.target);
            if (popover) {
                popover.show();
            }
        }
    });
});

// Export table to CSV function
function exportTableToCSV(filename) {
    var csv = [];
    var rows = document.querySelectorAll("#ordersTable tr");
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll("td, th");
        
        for (var j = 0; j < cols.length; j++) {
            // Get text content and clean it
            var text = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, "").replace(/"/g, '""');
            
            // For the "Items" column which has a button, we want to get the actual items
            if (cols[j].querySelector('[data-bs-toggle="popover"]')) {
                text = cols[j].querySelector('[data-bs-toggle="popover"]').getAttribute('data-bs-content')
                    .replace(/<br>/g, ", ")
                    .replace(/&lt;/g, "<")
                    .replace(/&gt;/g, ">")
                    .replace(/&quot;/g, '"')
                    .replace(/&amp;/g, "&");
            }
            
            row.push('"' + text + '"');
        }
        csv.push(row.join(","));
    }
    
    // Download CSV file
    downloadCSV(csv.join("\n"), filename);
}

function downloadCSV(csv, filename) {
    var csvFile;
    var downloadLink;
    
    // Create CSV file
    csvFile = new Blob([csv], {type: "text/csv"});
    
    // Create download link
    downloadLink = document.createElement("a");
    
    // File name
    downloadLink.download = filename;
    
    // Create a link to the file
    downloadLink.href = window.URL.createObjectURL(csvFile);
    
    // Hide download link
    downloadLink.style.display = "none";
    
    // Add the link to DOM
    document.body.appendChild(downloadLink);
    
    // Click download link
    downloadLink.click();
    
    // Remove link from DOM
    document.body.removeChild(downloadLink);
}
</script>

<style>
/* Custom styles for the page */
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

/* Print styles */
@media print {
    .sidebar-container, 
    .card-header,
    .card:first-of-type,
    form,
    .pagination,
    .main-content > .d-flex:first-child,
    button,
    .modal,
    .btn,
    .btn-group {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0;
        width: 100%;
        padding: 0;
    }
    
    .card {
        border: none;
        box-shadow: none;
    }
    
    .card-body {
        padding: 0;
    }
    
    /* Hide action column when printing */
    th:last-child, td:last-child {
        display: none;
    }
    
    /* Hide popover buttons and show actual items */
    button[data-bs-toggle="popover"] {
        display: none;
    }
    
    /* Add header for printing */
    body::before {
        content: "RotiSeri Bakery - Order History";
        display: block;
        text-align: center;
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 20px;
    }
}
</style>

<?php include '../includes/footer.php'; ?>