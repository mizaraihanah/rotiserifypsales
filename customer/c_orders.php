<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(3); // 3 for customer (formerly guest)
require_once '../config/db_connection.php';
require_once '../includes/functions/order_functions.php';

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Filter settings
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'order_date';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build filters array
$filters = [
    'status' => $status_filter,
    'date_from' => $date_from,
    'date_to' => $date_to,
    'sort_by' => $sort_by,
    'sort_order' => $sort_order,
    'limit' => $items_per_page,
    'offset' => $offset
];

// Get orders with pagination
$result = get_customer_orders($conn, $_SESSION['user']['id'], $filters);
$orders = $result['orders'];
$total_records = $result['total'];
$total_pages = ceil($total_records / $items_per_page);

// Set page title
$page_title = "My Orders - Customer";
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-md-8">
                    <h2 class="mb-0">My Orders</h2>
                    <p class="text-muted">View and manage your order history</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="c_place_order.php" class="btn btn-primary">
                        <i class="bi bi-cart-plus me-2"></i> Place New Order
                    </a>
                </div>
            </div>

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

            <!-- Filters -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sort By</label>
                            <select name="sort" class="form-select">
                                <option value="order_date" <?php echo $sort_by == 'order_date' ? 'selected' : ''; ?>>Order Date</option>
                                <option value="total_amount" <?php echo $sort_by == 'total_amount' ? 'selected' : ''; ?>>Amount</option>
                                <option value="status" <?php echo $sort_by == 'status' ? 'selected' : ''; ?>>Status</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-funnel me-1"></i> Apply Filters
                                </button>
                                <a href="c_orders.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="card shadow">
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-bag fs-1 text-muted"></i>
                            <h4 class="mt-3">No Orders Found</h4>
                            <p class="text-muted">You don't have any orders matching your search criteria.</p>
                            <a href="c_place_order.php" class="btn btn-primary mt-2">
                                <i class="bi bi-cart-plus me-2"></i> Place New Order
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-link p-0" 
                                                    data-bs-toggle="tooltip" 
                                                    data-bs-placement="top" 
                                                    title="<?php echo htmlspecialchars($order['items']); ?>">
                                                View Items
                                            </button>
                                        </td>
                                        <td class="fw-medium">RM<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($order['status']) {
                                                    'pending' => 'warning',
                                                    'completed' => 'success',
                                                    'cancelled' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="c_view_order.php?order_id=<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($order['status'] == 'pending'): ?>
                                                <a href="c_cancel_order.php?order_id=<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                class="btn btn-sm btn-outline-danger" 
                                                onclick="return confirm('Are you sure you want to cancel this order?')"
                                                title="Cancel Order">
                                                    <i class="bi bi-x-circle"></i>
                                                </a>
                                                <?php endif; ?>
                                                <a href="c_place_order.php?reorder=<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                class="btn btn-sm btn-outline-success" title="Order Again">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo htmlspecialchars($page-1, ENT_QUOTES, 'UTF-8'); ?>&status=<?php echo htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8'); ?>&date_from=<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>&date_to=<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>&sort=<?php echo htmlspecialchars($sort_by, ENT_QUOTES, 'UTF-8'); ?>&order=<?php echo htmlspecialchars($sort_order, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php 
                                // Determine which page numbers to show
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                // Show first page if not in range
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1&status=' . htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8') . '&date_from=' . htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8') . '&date_to=' . htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8') . '&sort=' . htmlspecialchars($sort_by, ENT_QUOTES, 'UTF-8') . '&order=' . htmlspecialchars($sort_order, ENT_QUOTES, 'UTF-8') . '">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                // Page numbers
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . '&status=' . htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8') . '&date_from=' . htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8') . '&date_to=' . htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8') . '&sort=' . htmlspecialchars($sort_by, ENT_QUOTES, 'UTF-8') . '&order=' . htmlspecialchars($sort_order, ENT_QUOTES, 'UTF-8') . '">' . $i . '</a></li>';
                                }
                                
                                // Show last page if not in range
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&status=' . htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8') . '&date_from=' . htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8') . '&date_to=' . htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8') . '&sort=' . htmlspecialchars($sort_by, ENT_QUOTES, 'UTF-8') . '&order=' . htmlspecialchars($sort_order, ENT_QUOTES, 'UTF-8') . '">' . $total_pages . '</a></li>';
                                }
                                ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo htmlspecialchars($page+1, ENT_QUOTES, 'UTF-8'); ?>&status=<?php echo htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8'); ?>&date_from=<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>&date_to=<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>&sort=<?php echo htmlspecialchars($sort_by, ENT_QUOTES, 'UTF-8'); ?>&order=<?php echo htmlspecialchars($sort_order, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border-radius: 8px;
    border: none;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    box-shadow: 0 0.25rem 1.85rem 0 rgba(58, 59, 69, 0.2);
}

.table th {
    font-weight: 600;
    border-top: none;
}

.table-hover tbody tr {
    transition: all 0.2s ease;
}

.table-hover tbody tr:hover {
    background-color: rgba(5, 97, 252, 0.05);
}

.page-link {
    color: #0561FC;
    border-color: #e0e0e0;
}

.page-item.active .page-link {
    background-color: #0561FC;
    border-color: #0561FC;
}

.alert {
    border-radius: 8px;
    animation: fadeIn 0.5s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            html: true
        })
    });
    
    // Make tooltip show on click for mobile devices
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            var tooltip = bootstrap.Tooltip.getInstance(this);
            tooltip.toggle();
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>