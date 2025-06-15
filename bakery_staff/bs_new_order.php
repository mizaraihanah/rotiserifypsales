<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(2); // 2 for bakery staff (formerly clerk)
require_once '../config/db_connection.php';
require_once '../includes/functions/inventory_functions.php';

// Initialize filter variables
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch active inventory
$categorized_products = fetch_inventory_by_category($conn);

// Fetch active promotions
$stmt = $conn->prepare("
    SELECT * FROM promotions 
    WHERE status = 'active'
    AND NOW() BETWEEN start_date AND end_date
    ORDER BY discount_value DESC 
    LIMIT 3
");
$stmt->execute();
$active_promotions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$page_title = "New Order - Bakery Staff";

// Initialize variables
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$discount = isset($_SESSION['discount']) ? $_SESSION['discount'] : 0;
$promo_code = isset($_SESSION['promo_code']) ? $_SESSION['promo_code'] : '';
$promo_error = '';
$promo_success = '';

// Handle promo code application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_promo'])) {
    $promo_code = $_POST['promo_code'];

    // Validate promo code
    $stmt = $conn->prepare("
        SELECT * FROM promotions 
        WHERE code = ? 
        AND status = 'active'
        AND NOW() BETWEEN start_date AND end_date
    ");
    $stmt->bind_param("s", $promo_code);
    $stmt->execute();
    $promo = $stmt->get_result()->fetch_assoc();

    if ($promo) {
        $_SESSION['promo_code'] = $promo_code;
        $_SESSION['discount'] = $promo['discount_type'] === 'percentage'
            ? ($cart_total * ($promo['discount_value'] / 100))
            : $promo['discount_value'];
        $promo_success = 'Promo code applied successfully!';
        $discount = $_SESSION['discount'];
    } else {
        $promo_error = 'Invalid or expired promo code.';
        unset($_SESSION['promo_code']);
        unset($_SESSION['discount']);
        $discount = 0;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    try {
        $conn->begin_transaction();

        // Get customer details
        $customer_name = htmlspecialchars(trim($_POST['customer_name']));
        $customer_contact = htmlspecialchars(trim($_POST['customer_contact']));

        // Create walk-in guest record
        $stmt = $conn->prepare("
            INSERT INTO guest (
                fullname, 
                contact, 
                address, 
                email, 
                password, 
                type, 
                date_created
            ) VALUES (?, ?, '-', CONCAT('walk-in-', ?), '', 3, NOW())
        ");
        $current_time = time();
        $stmt->bind_param("sss", $customer_name, $customer_contact, $current_time);
        $stmt->execute();
        $guest_id = $conn->insert_id;

        // Create order
        $order_number = 'POS' . time();
        $total_amount = 0;
        $subtotal = 0;
        $discount = 0;
        $payment_method = $_POST['payment_method'];
        $promo_code = trim($_POST['promo_code'] ?? '');        // Calculate subtotal and validate stock
        foreach ($_POST['quantity'] as $product_id => $quantity) {
            // Make sure quantity is a valid positive number
            $quantity = max(0, intval($quantity));
            
            if ($quantity > 0) {
                $stmt = $conn->prepare("SELECT product_name, unit_price, stock_level FROM inventory WHERE id = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();

                if (!$product) {
                    throw new Exception("Product with ID {$product_id} not found");
                }

                // Strict stock validation
                if ($quantity > $product['stock_level']) {
                    throw new Exception("Insufficient stock for '{$product['product_name']}'. Available: {$product['stock_level']}, Requested: {$quantity}");
                }

                $subtotal += $product['unit_price'] * $quantity;
            }
        }

        $total_amount = $subtotal; // Initialize total with subtotal

        // Apply promotion if code is provided
        if (!empty($promo_code)) {
            $stmt = $conn->prepare("
                SELECT * FROM promotions 
                WHERE code = ? 
                AND status = 'active'
                AND NOW() BETWEEN start_date AND end_date
            ");
            $stmt->bind_param("s", $promo_code);
            $stmt->execute();
            $promotion = $stmt->get_result()->fetch_assoc();

            if ($promotion) {
                if ($promotion['discount_type'] === 'percentage') {
                    $discount = $subtotal * ($promotion['discount_value'] / 100);
                } else {
                    $discount = min($promotion['discount_value'], $subtotal);
                }
                $total_amount = $subtotal - $discount;
            } else {
                throw new Exception("Invalid or expired promotion code");
            }
        }

        // Insert order with complete details including discount
        $stmt = $conn->prepare("
            INSERT INTO orders (
                order_number, 
                order_date, 
                total_amount,
                subtotal,
                discount,
                promo_code,
                status, 
                payment_method, 
                payment_status, 
                order_type, 
                clerk_id,
                guest_id
            ) VALUES (?, NOW(), ?, ?, ?, ?, 'completed', ?, 'paid', 'in-store', ?, ?)
        ");
        $stmt->bind_param(
            "sdddssis",
            $order_number,
            $total_amount,
            $subtotal,
            $discount,
            $promo_code,
            $payment_method,
            $_SESSION['user']['id'],
            $guest_id
        );
        $stmt->execute();
        $order_id = $conn->insert_id;        // Insert order items
        foreach ($_POST['quantity'] as $product_id => $quantity) {
            // Ensure quantity is a positive integer
            $quantity = max(0, intval($quantity));
            
            if ($quantity > 0) {
                // Double-check current stock level before final processing
                $stmt = $conn->prepare("SELECT unit_price, product_name, stock_level FROM inventory WHERE id = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                
                // Final stock validation to prevent race conditions
                if ($quantity > $product['stock_level']) {
                    throw new Exception("Stock level changed for '{$product['product_name']}'. Now available: {$product['stock_level']}, Requested: {$quantity}");
                }

                $subtotal = $product['unit_price'] * $quantity;
                $stmt = $conn->prepare("
                    INSERT INTO order_items (
                        order_id, 
                        product_id, 
                        quantity, 
                        unit_price, 
                        subtotal
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iiids", $order_id, $product_id, $quantity, $product['unit_price'], $subtotal);
                $stmt->execute();

                // Update stock level with safeguard against negative stock
                $stmt = $conn->prepare("
                    UPDATE inventory 
                    SET stock_level = GREATEST(0, stock_level - ?)
                    WHERE id = ?
                ");
                $stmt->bind_param("ii", $quantity, $product_id);
                $stmt->execute();
            }
        }

        $conn->commit();

        // Set success message and order details in session for the popup
        $_SESSION['success'] = "Order #$order_number completed successfully!";
        $_SESSION['order_popup'] = [
            'order_number' => $order_number,
            'total_amount' => $total_amount,
            'order_id' => $order_id
        ];

        // Instead of direct redirect, we'll show a success popup first
        header("Location: bs_new_order.php?success=1");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error processing order: " . $e->getMessage();
    }
}

// Check if we just completed an order and should show the success popup
$show_success_popup = isset($_GET['success']) && $_GET['success'] == '1' && isset($_SESSION['order_popup']);
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">Create New Order</h1>
                <p class="text-muted">Process an in-store purchase</p>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo sanitize_output($_SESSION['error']);
                unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success']) && !$show_success_popup): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo sanitize_output($_SESSION['success']);
                unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Product Selection -->
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Select Products</h6>
                        <div class="input-group" style="width: 250px;">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" id="productSearch" class="form-control border-start-0"
                                placeholder="Search products...">
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="orderForm" class="new-order-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="place_order" value="1">

                            <div class="mb-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Customer Name</label>
                                            <input type="text" name="customer_name" class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Contact Number</label>
                                            <input type="text" name="customer_contact" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <div class="d-flex">
                                        <div class="form-check me-4">
                                            <input class="form-check-input" type="radio" name="payment_method"
                                                id="cashPayment" value="cash" checked>
                                            <label class="form-check-label" for="cashPayment">
                                                <i class="bi bi-cash-coin me-1 text-success"></i> Cash
                                            </label>
                                        </div>
                                        <div class="form-check me-4">
                                            <input class="form-check-input" type="radio" name="payment_method"
                                                id="cardPayment" value="card">
                                            <label class="form-check-label" for="cardPayment">
                                                <i class="bi bi-credit-card me-1 text-info"></i> Card
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method"
                                                id="onlinePayment" value="online">
                                            <label class="form-check-label" for="onlinePayment">
                                                <i class="bi bi-bank me-1 text-primary"></i> Online Banking
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <ul class="nav nav-tabs mb-4" id="productTabs" role="tablist">
                                <?php
                                $first_tab = true;
                                foreach (array_keys($categorized_products) as $category):
                                    $category_id = str_replace(' ', '-', strtolower($category));
                                    ?>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link <?php echo $first_tab ? 'active' : ''; ?>"
                                            id="<?php echo $category_id; ?>-tab" data-bs-toggle="tab"
                                            data-bs-target="#<?php echo $category_id; ?>" type="button" role="tab">
                                            <?php echo htmlspecialchars($category ?: 'Uncategorized'); ?>
                                        </button>
                                    </li>
                                    <?php
                                    $first_tab = false;
                                endforeach;
                                ?>
                            </ul>

                            <div class="tab-content" id="productTabContent">
                                <?php
                                $first_tab = true;
                                foreach ($categorized_products as $category => $products):
                                    $category_id = str_replace(' ', '-', strtolower($category));
                                    ?>
                                    <div class="tab-pane fade <?php echo $first_tab ? 'show active' : ''; ?>"
                                        id="<?php echo $category_id; ?>" role="tabpanel">
                                        <div class="row">
                                            <?php foreach ($products as $product): ?>
                                                <div class="col-lg-4 col-md-6 mb-3 product-item" data-name="<?php echo strtolower($product['product_name']); ?>">
                                                    <div class="card product-selection h-100 <?php echo $product['stock_level'] === 0 ? 'out-of-stock' : ''; ?>">
                                                        <div class="card-body">
                                                            <h6 class="card-title mb-2">
                                                                <?php echo htmlspecialchars($product['product_name']); ?>
                                                            </h6>
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <span class="text-primary fw-bold">
                                                                    RM<?php echo number_format($product['unit_price'], 2); ?>
                                                                </span>
                                                                <span class="badge <?php 
                                                                    if ($product['stock_level'] === 0) {
                                                                        echo 'bg-danger';
                                                                    } elseif ($product['stock_level'] <= 10) {
                                                                        echo 'bg-warning';
                                                                    } else {
                                                                        echo 'bg-success';
                                                                    }
                                                                ?>">
                                                                    <?php echo $product['stock_level'] === 0 ? 'Out of Stock' : 'Stock: ' . $product['stock_level']; ?>
                                                                </span>
                                                            </div>
                                                            <div class="quantity-control mt-2">
                                                                <button type="button" class="btn-quantity" data-action="decrement" <?php echo $product['stock_level'] === 0 ? 'disabled' : ''; ?>>-</button>                                                                <input type="number" class="quantity-input" 
                                                                       name="quantity[<?php echo $product['id']; ?>]" 
                                                                       value="0" 
                                                                       min="0" 
                                                                       max="<?php echo $product['stock_level']; ?>"
                                                                       data-price="<?php echo $product['unit_price']; ?>"
                                                                       data-id="<?php echo $product['id']; ?>"
                                                                       onchange="updateTotal()"
                                                                       oninput="this.value = this.value.replace(/[^0-9]/g, ''); 
                                                                                if(parseInt(this.value) > parseInt(this.max)) this.value = this.max;"
                                                                       <?php echo $product['stock_level'] === 0 ? 'disabled' : ''; ?>>                                                                <button type="button" class="btn-quantity" data-action="increment" <?php echo $product['stock_level'] === 0 ? 'disabled' : ''; ?>>+</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php
                                    $first_tab = false;
                                endforeach;
                                ?>
                            </div>

                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <!-- Active Promotions Card -->
                <?php if (!empty($active_promotions)): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h5 class="mb-0"><i class="bi bi-tag-fill me-2"></i>Active Promotions</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($active_promotions as $promo): ?>
                                <div class="promotion-item mb-3">
                                    <div class="d-flex align-items-center">
                                        <button type="button" class="promotion-code-btn fw-bold"
                                            onclick="applyPromoCode('<?php echo htmlspecialchars($promo['code']); ?>')">
                                            <?php echo htmlspecialchars($promo['code']); ?>
                                        </button>
                                        <span class="badge bg-success ms-2">Active</span>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars($promo['description']); ?></p>
                                    <div class="text-muted small">
                                        <?php if ($promo['discount_type'] === 'percentage'): ?>
                                            <?php echo number_format($promo['discount_value'], 0); ?>% off
                                        <?php else: ?>
                                            RM<?php echo number_format($promo['discount_value'], 2); ?> off
                                        <?php endif; ?>
                                        · Valid until <?php echo date('M d, Y', strtotime($promo['end_date'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="order-summary shadow">
                    <h5 class="card-title mb-4">Order Summary</h5>

                    <!-- Selected items container -->
                    <div id="selected-items-container" class="mb-4">
                        <div class="text-center text-muted py-5" id="no-items-message">
                            <i class="bi bi-cart fs-1"></i>
                            <p class="mt-2">No items added yet</p>
                        </div>
                        <div id="selected-items-list" style="display: none;">
                            <!-- Items will be dynamically added here -->
                        </div>
                    </div>

                    <!-- Order details section -->
                    <div class="order-details">
                        <!-- Total Items -->
                        <div class="order-summary-item">
                            <span>Total Items:</span>
                            <span id="totalItems">0</span>
                        </div>

                        <!-- Subtotal -->
                        <div class="order-summary-item">
                            <span>Subtotal Amount:</span>
                            <span>RM<span id="subtotalAmount">0.00</span></span>
                        </div>

                        <!-- Discount section -->
                        <div id="discountSection" class="mb-4" style="display: none;">
                            <div class="order-summary-item text-success">
                                <span><i class="bi bi-tag-fill me-2"></i>Discount:</span>
                                <span>-RM<span id="discountAmount">0.00</span></span>
                            </div>
                            <div class="order-summary-item border-top pt-2">
                                <span class="fw-bold">Final Total:</span>
                                <span class="fw-bold">RM<span id="finalAmount">0.00</span></span>
                            </div>
                        </div>

                        <!-- Final total when no discount -->
                        <div id="regularTotalRow" class="order-summary-item border-top pt-2 fw-bold">
                            <span>Total:</span>
                            <span>RM<span id="totalAmount">0.00</span></span>
                        </div>
                    </div>

                    <!-- Promotion code input -->
                    <div class="mb-3 mt-4">
                        <label class="form-label">Promotion Code</label>
                        <input type="text" class="form-control" name="promo_code" id="promoCode"
                            placeholder="Enter promotion code">
                        <div id="promoMessage" class="form-text"></div>
                    </div>

                    <!-- Action buttons -->
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg" id="submit-order-btn" form="orderForm"
                            disabled>
                            <i class="bi bi-check-circle me-2"></i> Complete Order
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                            <i class="bi bi-x-circle me-2"></i> Clear All
                        </button>
                    </div>
                </div>

                <!-- Today's Orders -->
                <div class="card shadow mt-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Today's Orders</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php
                        // Fetch today's orders
                        $today = date('Y-m-d');
                        $stmt = $conn->prepare("
                            SELECT id, order_number, order_date, total_amount, payment_method
                            FROM orders
                            WHERE DATE(order_date) = ?
                            AND clerk_id = ?
                            AND order_type = 'in-store'
                            ORDER BY order_date DESC
                            LIMIT 5
                        ");
                        $stmt->bind_param("si", $today, $_SESSION['user']['id']);
                        $stmt->execute();
                        $today_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        ?>

                        <div class="list-group list-group-flush">
                            <?php if (empty($today_orders)): ?>
                                <div class="list-group-item text-center text-muted py-4">
                                    <i class="bi bi-receipt fs-1"></i>
                                    <p class="mt-2">No orders today</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($today_orders as $order): ?>
                                    <a href="bs_print_receipt.php?order_id=<?php echo $order['id']; ?>"
                                        class="list-group-item list-group-item-action p-3">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo $order['order_number']; ?></h6>
                                            <small
                                                class="text-muted"><?php echo date('H:i', strtotime($order['order_date'])); ?></small>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span
                                                class="text-primary fw-bold">RM<?php echo number_format($order['total_amount'], 2); ?></span>
                                            <span class="badge bg-<?php
                                            echo match ($order['payment_method']) {
                                                'cash' => 'success',
                                                'card' => 'info',
                                                'online' => 'primary',
                                                default => 'secondary'
                                            };
                                            ?>"><?php echo ucfirst($order['payment_method']); ?></span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </form> <!-- Form closing tag moved here to properly contain all elements -->
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="successModalLabel">Order Completed</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-4">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                </div>
                <h4 class="mb-3">Order Completed Successfully!</h4>
                <?php if ($show_success_popup): ?>
                    <p>Order #<?php echo $_SESSION['order_popup']['order_number']; ?> has been processed.</p>
                    <p class="mb-4 fs-4 text-primary fw-bold">
                        RM<?php echo number_format($_SESSION['order_popup']['total_amount'], 2); ?></p>
                <?php endif; ?>
                <p class="text-muted mb-0">You will be redirected to the receipt page.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <?php if ($show_success_popup): ?>
                    <a href="bs_print_receipt.php?order_id=<?php echo $_SESSION['order_popup']['order_id']; ?>"
                        class="btn btn-primary btn-lg" id="goToReceiptBtn">
                        <i class="bi bi-printer me-2"></i> Print Receipt
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>    document.addEventListener('DOMContentLoaded', function () {
        // Handle quantity increment/decrement using event delegation
        const container = document.querySelector('.main-content');
        if (container) {
            container.addEventListener('click', function (event) {
                const target = event.target;
                if (target.classList.contains('btn-quantity')) {
                    const input = target.parentNode.querySelector('input');
                    const action = target.getAttribute('data-action');
                    const maxValue = parseInt(input.max) || 0;

                    if (action === 'increment' && parseInt(input.value) < maxValue) {
                        input.value = parseInt(input.value) + 1;
                    } else if (action === 'decrement' && parseInt(input.value) > 0) {
                        input.value = parseInt(input.value) - 1;
                    }

                    // Highlight selected product card
                    const productCard = target.closest('.product-selection');
                    if (parseInt(input.value) > 0) {
                        productCard.classList.add('selected');
                    } else {
                        productCard.classList.remove('selected');
                    }

                    // Trigger change event to update total
                    input.dispatchEvent(new Event('change'));
                }
            });
            
            // Add event listeners to all quantity inputs to prevent invalid values
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('input', function() {
                    const maxValue = parseInt(this.max) || 0;
                    const currentValue = parseInt(this.value) || 0;
                    
                    // Ensure the value is not negative and does not exceed max
                    if (currentValue < 0) {
                        this.value = 0;
                    } else if (currentValue > maxValue) {
                        this.value = maxValue;
                    }
                });
            });
        }

        // Search functionality
        document.getElementById('productSearch').addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase().trim();

            document.querySelectorAll('.product-item').forEach(item => {
                const productName = item.getAttribute('data-name');
                if (productName.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });

            // Show all tabs if search is active
            if (searchTerm) {
                document.querySelectorAll('.tab-pane').forEach(tab => {
                    tab.classList.add('show', 'active');
                });
            } else {
                // Reset to default tab view
                document.querySelectorAll('.tab-pane').forEach((tab, index) => {
                    if (index === 0) {
                        tab.classList.add('show', 'active');
                    } else {
                        tab.classList.remove('show', 'active');
                    }
                });

                document.querySelectorAll('.nav-link').forEach((link, index) => {
                    if (index === 0) {
                        link.classList.add('active');
                    } else {
                        link.classList.remove('active');
                    }
                });
            }
        });

        // Initialize total
        updateTotal();

        // Show success modal if needed
        <?php if ($show_success_popup): ?>
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();

            // Automatically redirect after a few seconds
            setTimeout(function () {
                window.location.href = "bs_print_receipt.php?order_id=<?php echo $_SESSION['order_popup']['order_id']; ?>";
            }, 4000);
            <?php
            // Clear the popup data after showing it
            unset($_SESSION['order_popup']);
        endif;
        ?>
    });

    function updateTotal() {
        let totalItems = 0;
        let subtotalAmount = 0;
        const selectedItems = {};
        const submitButton = document.getElementById('submit-order-btn');
        const noItemsMessage = document.getElementById('no-items-message');
        const selectedItemsList = document.getElementById('selected-items-list');

        // Calculate initial totals
        document.querySelectorAll('.quantity-input').forEach(input => {
            const quantity = parseInt(input.value) || 0;
            const price = parseFloat(input.dataset.price);
            const productId = input.dataset.id;

            if (quantity > 0) {
                totalItems += quantity;
                subtotalAmount += quantity * price;

                // Store selected item details
                const productCard = input.closest('.product-selection');
                const productName = productCard.querySelector('.card-title').textContent.trim();

                selectedItems[productId] = {
                    name: productName,
                    quantity: quantity,
                    price: price,
                    subtotal: quantity * price
                };
            }
        });

        // Update total items and subtotal display
        document.getElementById('totalItems').textContent = totalItems;
        document.getElementById('subtotalAmount').textContent = subtotalAmount.toFixed(2);

        // Show/hide empty cart message
        if (totalItems > 0) {
            noItemsMessage.style.display = 'none';
            selectedItemsList.style.display = 'block';
            submitButton.disabled = false;
        } else {
            noItemsMessage.style.display = 'block';
            selectedItemsList.style.display = 'none';
            submitButton.disabled = true;

            // If cart is empty, hide discount section and reset promo
            document.getElementById('discountSection').style.display = 'none';
            document.getElementById('regularTotalRow').style.display = 'block';
            const promoMessage = document.getElementById('promoMessage');
            if (promoMessage) promoMessage.textContent = '';

            // Update UI
            document.getElementById('totalAmount').textContent = '0.00';
            return; // Exit early if cart is empty
        }

        // Update selected items display
        updateSelectedItemsList(selectedItems);

        // Get promo code input and discount section elements
        const promoInput = document.getElementById('promoCode');
        const discountSection = document.getElementById('discountSection');
        const regularTotalRow = document.getElementById('regularTotalRow');
        const promoMessage = document.getElementById('promoMessage');

        // Initialize with subtotal as the final amount
        let finalAmount = subtotalAmount;
        document.getElementById('totalAmount').textContent = subtotalAmount.toFixed(2);

        // Handle promo code validation if code is entered and cart has items
        if (promoInput && promoInput.value.trim() && subtotalAmount > 0) {
            const formData = new FormData();
            formData.append('code', promoInput.value.trim());
            formData.append('subtotal', subtotalAmount);

            fetch('../customer/validate_promo.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.valid) {
                        // Calculate discount
                        const discount = data.discount_type === 'percentage'
                            ? subtotalAmount * (data.discount_value / 100)
                            : Math.min(data.discount_value, subtotalAmount);

                        // Calculate final amount after discount
                        finalAmount = Math.max(0, subtotalAmount - discount);

                        // Update discount display
                        document.getElementById('discountAmount').textContent = discount.toFixed(2);
                        document.getElementById('finalAmount').textContent = finalAmount.toFixed(2);

                        // Show discount section and hide regular total
                        discountSection.style.display = 'block';
                        regularTotalRow.style.display = 'none';

                        // Set success message
                        promoMessage.textContent = data.discount_type === 'percentage'
                            ? `${data.discount_value}% discount applied!`
                            : `RM${data.discount_value.toFixed(2)} discount applied!`;
                        promoMessage.className = 'form-text text-success';
                    } else {
                        // Hide discount section and show regular total
                        discountSection.style.display = 'none';
                        regularTotalRow.style.display = 'block';

                        // Set error message
                        promoMessage.textContent = data.message || 'Invalid promo code';
                        promoMessage.className = 'form-text text-danger';
                    }
                })
                .catch(error => {
                    // Handle errors
                    console.error('Error validating promo code:', error);
                    discountSection.style.display = 'none';
                    regularTotalRow.style.display = 'block';

                    if (promoMessage) {
                        promoMessage.textContent = 'Error validating promo code';
                        promoMessage.className = 'form-text text-danger';
                    }
                });
        } else {
            // No promo code entered - hide discount section
            discountSection.style.display = 'none';
            regularTotalRow.style.display = 'block';
            if (promoMessage) {
                promoMessage.textContent = '';
            }
        }
    }

    // Function to update the selected items list
    function updateSelectedItemsList(selectedItems) {
        const container = document.getElementById('selected-items-list');
        container.innerHTML = ''; // Clear existing items

        // Add each selected item to the list
        Object.entries(selectedItems).forEach(([productId, item]) => {
            const itemElement = document.createElement('div');
            itemElement.className = 'order-item';
            itemElement.innerHTML = `
            <div>
                <div class="fw-bold">${item.name}</div>
                <div class="text-muted small">${item.quantity} x RM${item.price.toFixed(2)}</div>
            </div>
            <div class="text-end">
                <div>RM${item.subtotal.toFixed(2)}</div>
            </div>
        `;
            container.appendChild(itemElement);
        });
    }

    // Function to reset the form
    function resetForm() {
        // Reset all quantity inputs
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.value = 0;
            const productCard = input.closest('.product-selection');
            if (productCard) {
                productCard.classList.remove('selected');
            }
        });

        // Clear promo code
        const promoInput = document.getElementById('promoCode');
        if (promoInput) {
            promoInput.value = '';
        }

        // Reset the display
        updateTotal();

        // Show confirmation
        alert('Order form has been cleared');
    }

    // Function to apply promo code from active promotions
    function applyPromoCode(code) {
        const promoInput = document.getElementById('promoCode');
        promoInput.value = code;
        promoInput.focus();

        // Use a slight delay to give visual feedback
        setTimeout(() => {
            updateTotal();
        }, 100);
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function () {
        // Listen for quantity changes
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', updateTotal);
        });

        // Listen for promo code input changes
        const promoInput = document.getElementById('promoCode');
        if (promoInput) {
            promoInput.addEventListener('input', updateTotal);
        }

        // Initialize the total on page load
        updateTotal();
    });
</script>

<style>
    /* Custom styles for New Order page */
    .product-selection {
        transition: all 0.2s ease;
        border: 1px solid #e3e6f0;
    }

    .product-selection:hover {
        border-color: #0561FC;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }

    .product-selection.selected {
        border-color: #0561FC;
        background-color: rgba(5, 97, 252, 0.05);
    }

    .product-selection.out-of-stock {
        border-color: #dc3545;
        background-color: rgba(220, 53, 69, 0.05);
    }

    .quantity-control {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .quantity-control .btn-quantity {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        border: 1px solid #ced4da;
        background-color: #f8f9fa;
        cursor: pointer;
        font-weight: bold;
        font-size: 1.25rem;
        user-select: none;
        margin: 0 4px;
    }

    .quantity-control .btn-quantity:hover {
        background-color: #e9ecef;
    }

    .quantity-control input {
        width: 60px;
        height: 40px;
        text-align: center;
        border: 1px solid #ced4da;
        border-radius: 6px;
        margin: 0 8px;
        font-size: 1.1rem;
    }

    .quantity-control input:disabled {
        background-color: #e9ecef;
        cursor: not-allowed;
    }

    .order-summary {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        padding: 1.5rem;
    }

    .order-item {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f2f2f2;
    }

    .order-summary-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        width: 100%;
    }

    .order-summary-total {
        display: flex;
        justify-content: space-between;
        padding-top: 1rem;
        margin-top: 0.5rem;
        border-top: 1px solid #e3e6f0;
        font-weight: 700;
        font-size: 1.1rem;
    }

    /* Promotion styles from c_place_order.php */
    .promotion-item {
        padding-bottom: 1rem;
        border-bottom: 1px solid #e9ecef;
    }

    .promotion-item:last-child {
        padding-bottom: 0;
        border-bottom: none;
    }

    .promotion-code-btn {
        font-family: monospace;
        padding: 0.4rem 0.8rem;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s ease;
        color: #0561FC;
    }

    .promotion-code-btn:hover {
        background: #e9ecef;
        border-color: #0561FC;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    #promoMessage {
        margin-top: 0.25rem;
        display: block;
    }

    #promoMessage.text-success {
        color: #28a745;
    }

    #promoMessage.text-danger {
        color: #dc3545;
    }

    #discountSection {
        margin: 1rem 0;
        padding: 0.5rem 0;
        border-top: 1px dashed #e3e6f0;
        border-bottom: 1px dashed #e3e6f0;
    }

    #discountSection .text-success,
    .discount-text {
        color: #28a745 !important;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    #discountAmount {
        font-weight: 500;
    }

    .promotion-badge {
        padding: 0.25em 0.5em;
        font-size: 0.875em;
        border-radius: 0.25rem;
    }

    /* Success modal animation */
    @keyframes checkmark {
        0% {
            transform: scale(0);
            opacity: 0;
        }

        50% {
            transform: scale(1.2);
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    #successModal .bi-check-circle-fill {
        animation: checkmark 0.5s ease-in-out;
    }

    .discount-section {
        margin: 0.75rem 0;
        padding: 0.75rem 0;
        border-top: 1px dashed #e0e0e0;
        border-bottom: 1px dashed #e0e0e0;
        background-color: rgba(40, 167, 69, 0.05);
        border-radius: 6px;
    }

    .discount-row {
        color: #28a745 !important;
        font-weight: 500;
    }

    .discount-row i {
        font-size: 0.9rem;
    }

    .discount-row span {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .total-with-discount {
        margin-top: 0.5rem;
    }

    #promoMessage.text-success {
        color: #28a745 !important;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        margin-top: 0.5rem;
    }

    #promoMessage.text-success::before {
        content: "\F286";
        font-family: "bootstrap-icons";
        font-size: 0.9rem;
    }

    #promoMessage.text-danger {
        color: #dc3545 !important;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        margin-top: 0.5rem;
    }

    #promoMessage.text-danger::before {
        content: "\F623";
        font-family: "bootstrap-icons";
        font-size: 0.9rem;
    }

    .order-summary-total {
        display: flex;
        justify-content: space-between;
        padding-top: 0.75rem;
        margin-top: 0.5rem;
        border-top: 1px solid #e3e6f0;
        font-weight: 500;
        font-size: 1rem;
    }

    /* Style for the promo code input focus state */
    #promoCode:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }

    /* Animation for the discount section */
    @keyframes highlightDiscount {
        0% {
            background-color: rgba(40, 167, 69, 0.2);
        }

        100% {
            background-color: rgba(40, 167, 69, 0.05);
        }
    }

    #discountSection {
        display: none;
        margin: 0.5rem 0;
    }

    #discountSection.show {
        display: block !important;
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    #discountSection span {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    @media (max-width: 768px) {
        .order-summary {
            position: relative;
            top: auto;
        }
        .quantity-control .btn-quantity {
            width: 44px;
            height: 44px;
            font-size: 1.4rem;
            margin: 0 6px;
        }
        .quantity-control input {
            width: 70px;
            height: 44px;
            font-size: 1.3rem;
            margin: 0 10px;
        }
    }
</style>

<?php include '../includes/footer.php'; ?>