<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(3); // 3 for customer (formerly guest)
require_once '../config/db_connection.php';
require_once '../includes/functions/order_functions.php';

if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    $_SESSION['error'] = 'Invalid order ID';
    header("Location: c_orders.php");
    exit();
}

$order_id = (int)$_GET['order_id'];
$guest_id = $_SESSION['user']['id'];

// Get order details
$result = get_order_details($conn, $order_id, $guest_id);

if (!$result) {
    $_SESSION['error'] = 'Order not found';
    header("Location: c_orders.php");
    exit();
}

$order = $result['order'];
$order_items = $result['items'];

// Check if feedback exists for this order
$has_feedback = false;
$feedback = null;

if ($order['status'] === 'completed' || $order['status'] === 'approved') {
    $feedback_stmt = $conn->prepare("
        SELECT id, rating, comment, feedback_date 
        FROM feedback 
        WHERE order_id = ?
    ");
    $feedback_stmt->bind_param("i", $order_id);
    $feedback_stmt->execute();
    $feedback_result = $feedback_stmt->get_result();
    
    if ($feedback_result->num_rows > 0) {
        $has_feedback = true;
        $feedback = $feedback_result->fetch_assoc();
    }
}

// Set page title
$page_title = "Order Details - Customer";
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="c_orders.php">My Orders</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Order Details</li>
                        </ol>
                    </nav>
                </div>
            </div>
            
            <div class="order-details-card">
                <!-- Order Header -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h3 class="mb-1">Order #<?php echo htmlspecialchars($order['order_number']); ?></h3>
                                <p class="text-muted mb-0">
                                    Placed on <?php echo date('F j, Y \a\t g:i a', strtotime($order['order_date'])); ?>
                                </p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <span class="badge bg-<?php 
                                    echo match($order['status']) {
                                        'pending' => 'warning',
                                        'completed' => 'success',
                                        'cancelled' => 'danger',
                                        default => 'secondary'
                                    }; ?> fs-6 py-2 px-3">
                                    <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                                </span>
                                
                                <?php if ($order['status'] === 'pending'): ?>
                                    <a href="c_cancel_order.php?order_id=<?php echo $order_id; ?>" 
                                    class="btn btn-danger ms-2" 
                                    onclick="return confirm('Are you sure you want to cancel this order?')">
                                        <i class="bi bi-x-circle"></i> Cancel Order
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- Order Details -->
                    <div class="col-lg-8">
                        <!-- Order Items -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Order Items</h5>
                                <?php if ($order['status'] === 'completed' || $order['status'] === 'approved'): ?>
                                <a href="c_print_receipt.php?order_id=<?php echo $order_id; ?>" 
                                   class="btn btn-outline-secondary btn-sm"
                                   target="_blank">
                                    <i class="bi bi-printer me-1"></i> Print Receipt
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
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
                                            foreach ($order_items as $item):
                                                $item_subtotal = $item['quantity'] * $item['unit_price'];
                                                $subtotal += $item_subtotal;
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                                                    <td class="text-end">RM<?php echo number_format($item['unit_price'], 2); ?></td>
                                                    <td class="text-end">RM<?php echo number_format($item_subtotal, 2); ?></td>
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
                        </div>
                        
                        <!-- Order Timeline -->
                        <div class="card mb-4 mb-lg-0">
                            <div class="card-header">
                                <h5 class="mb-0">Order Timeline</h5>
                            </div>
                            <div class="card-body">
                                <div class="order-timeline">
                                    <div class="timeline-item">
                                        <div class="timeline-point <?php echo 'completed'; ?>">
                                            <i class="bi bi-check-circle-fill"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h6 class="mb-0">Order Placed</h6>
                                            <p class="text-muted small mb-0">
                                                <?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="timeline-item">
                                        <div class="timeline-point <?php echo $order['status'] === 'cancelled' ? 'cancelled' : ($order['status'] === 'completed' || $order['status'] === 'approved' ? 'completed' : 'pending'); ?>">
                                            <?php if ($order['status'] === 'cancelled'): ?>
                                                <i class="bi bi-x-circle-fill"></i>
                                            <?php elseif ($order['status'] === 'completed' || $order['status'] === 'approved'): ?>
                                                <i class="bi bi-check-circle-fill"></i>
                                            <?php else: ?>
                                                <i class="bi bi-clock-fill"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="timeline-content">
                                            <h6 class="mb-0">
                                                <?php if ($order['status'] === 'cancelled'): ?>
                                                    Order Cancelled
                                                <?php elseif ($order['status'] === 'completed' || $order['status'] === 'approved'): ?>
                                                    Order Completed
                                                <?php else: ?>
                                                    Processing Order
                                                <?php endif; ?>
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                <?php 
                                                    if ($order['status'] === 'pending') {
                                                        echo 'Your order is being processed';
                                                    } elseif ($order['status'] === 'completed' || $order['status'] === 'approved') {
                                                        echo 'Your order has been completed';
                                                    } else {
                                                        echo 'Your order has been cancelled';
                                                    }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($order['status'] === 'completed' || $order['status'] === 'approved'): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-point completed">
                                            <i class="bi bi-check-circle-fill"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h6 class="mb-0">Ready for Pickup/Delivery</h6>
                                            <p class="text-muted small mb-0">
                                                Your order is ready for pickup or delivery
                                            </p>
                                        </div>
                                    </div>
                                    <?php elseif ($order['status'] !== 'cancelled'): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-point upcoming">
                                            <i class="bi bi-circle"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h6 class="mb-0 text-muted">Ready for Pickup/Delivery</h6>
                                            <p class="text-muted small mb-0">
                                                This step will be updated soon
                                            </p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="col-lg-4">
                        <!-- Order Info -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Order Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="info-group mb-3">
                                    <label class="text-muted small">Order Number</label>
                                    <p class="mb-0 fw-medium"><?php echo htmlspecialchars($order['order_number']); ?></p>
                                </div>
                                
                                <div class="info-group mb-3">
                                    <label class="text-muted small">Order Date</label>
                                    <p class="mb-0"><?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></p>
                                </div>
                                
                                <div class="info-group mb-3">
                                    <label class="text-muted small">Order Type</label>
                                    <p class="mb-0"><?php echo ucfirst(htmlspecialchars($order['order_type'])); ?></p>
                                </div>
                                
                                <div class="info-group mb-3">
                                    <label class="text-muted small">Payment Method</label>
                                    <p class="mb-0"><?php echo ucfirst(htmlspecialchars($order['payment_method'])); ?></p>
                                </div>
                                
                                <div class="info-group mb-3">
                                    <label class="text-muted small">Payment Status</label>
                                    <p class="mb-0">
                                        <span class="badge bg-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst(htmlspecialchars($order['payment_status'])); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Delivery Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Delivery Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="info-group mb-0">
                                    <label class="text-muted small">Delivery Address</label>
                                    <p class="mb-0">
                                        <?php echo nl2br(htmlspecialchars($order['delivery_address'] ?? 'N/A')); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Feedback Section - Only for completed orders -->
                        <?php if ($order['status'] === 'completed' || $order['status'] === 'approved'): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <?php echo $has_feedback ? 'Your Feedback' : 'Order Feedback'; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if ($has_feedback): ?>
                                <!-- Display existing feedback -->
                                <div class="text-center mb-3">
                                    <div class="mb-2">Your Rating:</div>
                                    <div class="rating-display">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?php echo ($i <= $feedback['rating']) ? '-fill' : ''; ?> fs-4 text-warning"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="text-muted small mt-1">
                                        Submitted on <?php echo date('F j, Y', strtotime($feedback['feedback_date'])); ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($feedback['comment'])): ?>
                                <div class="mt-3">
                                    <label class="text-muted small">Your Comment:</label>
                                    <p class="feedback-comment p-3 bg-light rounded">
                                        <?php echo nl2br(htmlspecialchars($feedback['comment'])); ?>
                                    </p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-grid mt-3">
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editFeedbackModal">
                                        <i class="bi bi-pencil me-2"></i> Edit Feedback
                                    </button>
                                </div>
                                
                                <?php else: ?>
                                <!-- New feedback form -->
                                <p class="text-muted mb-3">How was your experience with this order? Your feedback helps us improve our service!</p>
                                
                                <form action="c_submit_feedback.php" method="post" id="feedbackForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                    
                                    <div class="mb-3 text-center">
                                        <label class="form-label">Your Rating</label>
                                        <div class="rating">
                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <input type="radio" name="rating" value="<?php echo $i; ?>" id="rating-<?php echo $i; ?>" <?php echo ($i === 5) ? 'checked' : ''; ?>>
                                            <label for="rating-<?php echo $i; ?>">
                                                <i class="bi bi-star-fill text-warning"></i>
                                            </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="comment" class="form-label">Comment (Optional)</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Share your experience with us..."></textarea>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-circle me-2"></i> Submit Feedback
                                        </button>
                                    </div>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Action Buttons -->
                        <div class="d-grid gap-2">
                            <a href="c_orders.php" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-left me-2"></i> Back to Orders
                            </a>
                            
                            <?php if ($order['status'] === 'pending'): ?>
                            <a href="c_cancel_order.php?order_id=<?php echo $order_id; ?>" 
                               class="btn btn-outline-danger" 
                               onclick="return confirm('Are you sure you want to cancel this order?')">
                                <i class="bi bi-x-circle me-2"></i> Cancel Order
                            </a>
                            <?php endif; ?>

                            <a href="c_place_order.php?reorder=<?php echo $order_id; ?>" class="btn btn-success">
                                <i class="bi bi-arrow-repeat me-2"></i> Order Again
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Feedback Modal -->
<?php if ($has_feedback): ?>
<div class="modal fade" id="editFeedbackModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Your Feedback</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="c_submit_feedback.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    
                    <div class="mb-3 text-center">
                        <label class="form-label">Your Rating</label>
                        <div class="rating edit-rating">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" value="<?php echo $i; ?>" id="edit-rating-<?php echo $i; ?>" <?php echo ($i === $feedback['rating']) ? 'checked' : ''; ?>>
                            <label for="edit-rating-<?php echo $i; ?>">
                                <i class="bi bi-star-fill text-warning"></i>
                            </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit-comment" class="form-label">Comment (Optional)</label>
                        <textarea class="form-control" id="edit-comment" name="comment" rows="3" placeholder="Share your experience with us..."><?php echo htmlspecialchars($feedback['comment']); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Feedback</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.card {
    border-radius: 8px;
    border: none;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    margin-bottom: 1.5rem;
}

.info-group label {
    display: block;
    margin-bottom: 5px;
}

.order-timeline {
    position: relative;
    padding-left: 30px;
}

.order-timeline:before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 15px;
    border-left: 2px dashed #e0e0e0;
    z-index: 0;
}

.timeline-item {
    position: relative;
    margin-bottom: 25px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-point {
    position: absolute;
    left: -30px;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    z-index: 1;
}

.timeline-point.completed {
    background-color: #e8f5e9;
    color: #28a745;
}

.timeline-point.pending {
    background-color: #fff3cd;
    color: #ffc107;
}

.timeline-point.cancelled {
    background-color: #f8d7da;
    color: #dc3545;
}

.timeline-point.upcoming {
    background-color: #f8f9fa;
    color: #6c757d;
}

.timeline-content {
    margin-bottom: 0;
}

/* Star Rating Styles */
.rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: center;
    gap: 0.5rem;
}

.rating input {
    display: none;
}

.rating label {
    cursor: pointer;
    font-size: 1.5rem;
    transition: all 0.2s ease;
}

.rating label:hover,
.rating label:hover ~ label,
.rating input:checked ~ label {
    transform: scale(1.2);
}

.rating-display {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
}

.feedback-comment {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-top: 10px;
}
</style>

<?php include '../includes/footer.php'; ?>