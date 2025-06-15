<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(2); // 2 for bakery staff
require_once '../config/db_connection.php';

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Set page title
$page_title = "Customer Feedback - Bakery Staff";

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$rating_filter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build the query
$where_clauses = ['1=1']; // Always true condition to start
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_clauses[] = "(g.fullname LIKE ? OR g.email LIKE ? OR o.order_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if ($rating_filter > 0) {
    $where_clauses[] = "f.rating = ?";
    $params[] = $rating_filter;
    $param_types .= 'i';
}

if (!empty($date_from)) {
    $where_clauses[] = "DATE(f.feedback_date) >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}

if (!empty($date_to)) {
    $where_clauses[] = "DATE(f.feedback_date) <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

$where_clause = implode(' AND ', $where_clauses);

// Count total feedback records
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM feedback f
    JOIN orders o ON f.order_id = o.id
    JOIN guest g ON f.guest_id = g.id
    WHERE $where_clause
");

if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $items_per_page);

// Get feedback with pagination
$stmt = $conn->prepare("
    SELECT 
        f.id,
        f.rating,
        f.comment,
        f.feedback_date,
        o.order_number,
        o.order_date,
        o.total_amount,
        o.id as order_id,
        g.fullname as customer_name,
        g.email as customer_email,
        g.contact as customer_contact
    FROM feedback f
    JOIN orders o ON f.order_id = o.id
    JOIN guest g ON f.guest_id = g.id
    WHERE $where_clause
    ORDER BY f.feedback_date DESC
    LIMIT ? OFFSET ?
");

$params[] = $items_per_page;
$params[] = $offset;
$param_types .= 'ii';
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$feedback_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get feedback statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_feedback,
        ROUND(AVG(rating), 1) as avg_rating,
        SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive_feedback,
        SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as negative_feedback
    FROM feedback
");
$stats_stmt->execute();
$feedback_stats = $stats_stmt->get_result()->fetch_assoc();
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Customer Feedback</h1>
                <p class="text-muted">View and analyze customer feedback on orders</p>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Feedback Stats -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Feedback
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($feedback_stats['total_feedback']); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-chat-square-text fs-2 text-gray-300"></i>
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
                                    Average Rating
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($feedback_stats['avg_rating'], 1); ?> / 5.0
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-star-half fs-2 text-gray-300"></i>
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
                                    Positive Feedback
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $positive_percent = ($feedback_stats['total_feedback'] > 0) 
                                        ? round(($feedback_stats['positive_feedback'] / $feedback_stats['total_feedback']) * 100) 
                                        : 0; 
                                    echo $positive_percent . '%';
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-emoji-smile fs-2 text-gray-300"></i>
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
                                    Negative Feedback
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $negative_percent = ($feedback_stats['total_feedback'] > 0) 
                                        ? round(($feedback_stats['negative_feedback'] / $feedback_stats['total_feedback']) * 100) 
                                        : 0; 
                                    echo $negative_percent . '%';
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-emoji-frown fs-2 text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by customer name or order #" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Rating</label>
                        <select name="rating" class="form-select">
                            <option value="0">All Ratings</option>
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $rating_filter === $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?> <?php echo $i === 1 ? 'Star' : 'Stars'; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-1"></i> Filter
                            </button>
                            <a href="bs_feedback.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Feedback Table -->
        <div class="card shadow">
            <div class="card-body">
                <?php if (empty($feedback_list)): ?>
                    <div class="text-center py-5">
                        <h4 class="text-muted">No Feedback Found</h4>
                        <p class="text-muted mb-0">No customer feedback matching your search criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Order #</th>
                                    <th>Rating</th>
                                    <th>Comment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feedback_list as $feedback): ?>
                                <tr>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($feedback['feedback_date'])); ?>
                                        <div class="small text-muted">
                                            <?php echo date('g:i a', strtotime($feedback['feedback_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($feedback['customer_name']); ?></div>
                                        <?php if (!empty($feedback['customer_contact'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($feedback['customer_contact']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="bs_print_receipt.php?order_id=<?php echo $feedback['order_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($feedback['order_number']); ?>
                                        </a>
                                        <div class="small text-muted">
                                            <?php echo date('M d, Y', strtotime($feedback['order_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="rating-display">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?php echo ($i <= $feedback['rating']) ? '-fill' : ''; ?> text-warning"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($feedback['comment'])): ?>
                                        <button type="button" class="btn btn-sm btn-link p-0" 
                                                data-bs-toggle="popover" 
                                                data-bs-trigger="focus" 
                                                data-bs-html="true"
                                                data-bs-title="Customer Comment" 
                                                data-bs-content="<?php echo htmlspecialchars(nl2br($feedback['comment'])); ?>">
                                            View Comment
                                        </button>
                                        <?php else: ?>
                                        <span class="text-muted">No comment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="bs_print_receipt.php?order_id=<?php echo $feedback['order_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" title="View Order">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center mt-4">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo $rating_filter; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php 
                                // Determine which page numbers to show
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                // Show first page if not in range
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&rating=' . $rating_filter . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                // Show page numbers
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&rating=' . $rating_filter . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '">' . $i . '</a></li>';
                                }
                                
                                // Show last page if not in range
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&rating=' . $rating_filter . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '">' . $total_pages . '</a></li>';
                                }
                                ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&rating=<?php echo $rating_filter; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
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

.rating-display {
    display: flex;
    font-size: 0.875rem;
}

.text-gray-300 {
    color: rgba(0,0,0,0.2);
}

.text-gray-800 {
    color: #333;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl, {
            html: true
        })
    });
});
</script>

<?php include '../includes/footer.php'; ?>
