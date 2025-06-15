<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(3); // 3 for customer (formerly guest)
require_once '../config/db_connection.php';
require_once '../includes/functions/inventory_functions.php';
require_once '../includes/functions/order_functions.php';

// Add CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize reorder quantities array and out of stock items
$reorder_quantities = [];
$out_of_stock_items = [];

// Handle reorder parameter
if (isset($_GET['reorder']) && is_numeric($_GET['reorder'])) {
    $order_id = (int)$_GET['reorder'];
    // Fetch the order items and check stock levels
    $stmt = $conn->prepare("
        SELECT oi.product_id, oi.quantity, i.product_name, i.stock_level
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id 
        JOIN inventory i ON oi.product_id = i.id
        WHERE o.id = ? AND o.guest_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $_SESSION['user']['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($item = $result->fetch_assoc()) {
        if ($item['stock_level'] > 0) {
            // Only add items that are in stock, and limit to available quantity
            $reorder_quantities[$item['product_id']] = min($item['quantity'], $item['stock_level']);
            
            // If available stock is less than original order quantity
            if ($item['stock_level'] < $item['quantity']) {
                $out_of_stock_items[] = [
                    'name' => $item['product_name'],
                    'requested' => $item['quantity'],
                    'available' => $item['stock_level']
                ];
            }
        } else {
            $out_of_stock_items[] = [
                'name' => $item['product_name'],
                'requested' => $item['quantity'],
                'available' => 0
            ];
        }
    }
}

// Fetch products with actual stock level
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

// Highlight product if specified in URL
$highlight_product = isset($_GET['product']) ? (int)$_GET['product'] : null;

// Set page title
$page_title = "Place Order - Customer";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    try {
        $guest_id = $_SESSION['user']['id'];
        $order_number = 'ORD' . time();
        $delivery_address = htmlspecialchars($_POST['delivery_address']);
        $payment_method = $_POST['payment_method'];
        $promo_code = trim($_POST['promo_code'] ?? '');
          // Validate if any items are selected
        $has_items = false;
        $items = [];
        $subtotal = 0;
        $insufficient_stock_items = [];
        
        foreach ($_POST['items'] as $item_id => $quantity) {
            if ($quantity > 0) {
                $has_items = true;
                
                // Check stock availability and get price
                $stmt = $conn->prepare("SELECT product_name, unit_price, stock_level FROM inventory WHERE id = ?");
                $stmt->bind_param("i", $item_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                
                // Validate stock level
                if ($quantity > $product['stock_level']) {
                    $insufficient_stock_items[] = [
                        'name' => $product['product_name'],
                        'requested' => $quantity,
                        'available' => $product['stock_level']
                    ];
                } else {
                    $items[$item_id] = $quantity;
                    $subtotal += $product['unit_price'] * $quantity;
                }
            }
        }
        
        if (!$has_items) {
            throw new Exception("Please select at least one item");
        }
        
        if (!empty($insufficient_stock_items)) {
            $error_message = "Insufficient stock for the following items:<ul>";
            foreach ($insufficient_stock_items as $item) {
                $error_message .= "<li>" . htmlspecialchars($item['name']) . ": Requested " . $item['requested'] . ", Available " . $item['available'] . "</li>";
            }
            $error_message .= "</ul>";
            throw new Exception($error_message);
        }

        $total_amount = $subtotal; // Initialize total amount with subtotal
        $discount = 0; // Initialize discount

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
                    $discount = min($promotion['discount_value'], $subtotal); // Don't allow discount larger than subtotal
                }
                $total_amount = $subtotal - $discount;
            } else {
                throw new Exception("Invalid or expired promotion code");
            }
        }

        // Create order with all necessary information
        $order_id = create_order(
            $conn, 
            $guest_id, 
            $order_number, 
            $total_amount, 
            $payment_method, 
            $delivery_address, 
            'online', 
            $items,
            $promo_code,
            $subtotal
        );
        
        if ($order_id) {
            $_SESSION['success'] = "Order placed successfully! Order number: " . $order_number;
            header("Location: c_view_order.php?order_id=" . $order_id);
            exit();
        } else {
            throw new Exception("Error placing order. Please try again.");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-md-8">
                    <h2 class="mb-0">Place Your Order</h2>
                    <p class="text-muted">Select products and place your online order</p>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo sanitize_output($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($out_of_stock_items)): ?>
                <div class="alert alert-warning alert-dismissible fade show">
                    <strong>Warning:</strong> Some items in your reorder are out of stock or have limited availability.
                    <ul>
                        <?php foreach ($out_of_stock_items as $item): ?>
                            <li>
                                <?php echo htmlspecialchars($item['name']); ?>: Requested <?php echo $item['requested']; ?>, Available <?php echo $item['available']; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="row">
                    <div class="col-md-8">
                        <!-- Category Navigation Tabs -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <ul class="nav nav-pills nav-fill mb-3" id="categoryTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                                            All Products
                                        </button>
                                    </li>
                                    <?php 
                                    foreach (array_keys($categorized_products) as $category): 
                                        if (empty($category)) continue;
                                        $category_id = 'cat-' . preg_replace('/\s+/', '-', strtolower($category));
                                    ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="<?php echo $category_id; ?>-tab" data-bs-toggle="tab" data-bs-target="#<?php echo $category_id; ?>" type="button" role="tab">
                                                <?php echo htmlspecialchars($category); ?>
                                            </button>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <!-- Search Input -->
                                <div class="mb-0 mt-3">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white">
                                            <i class="bi bi-search"></i>
                                        </span>
                                        <input type="text" id="productSearch" class="form-control" placeholder="Search products...">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Products Tab Content -->
                        <div class="tab-content" id="categoryTabContent">
                            <!-- All Products Tab -->
                            <div class="tab-pane fade show active" id="all" role="tabpanel">
                                <div class="row">
                                    <?php 
                                    // Flatten all products into one array
                                    $all_products = [];
                                    foreach ($categorized_products as $category => $products) {
                                        foreach ($products as $product) {
                                            $all_products[] = $product;
                                        }
                                    }
                                    
                                    foreach ($all_products as $product): 
                                    ?>
                                        <div class="col-md-6 col-lg-4 mb-3 product-item" data-name="<?php echo strtolower($product['product_name']); ?>">
                                            <div class="card product-card h-100 <?php echo ($highlight_product == $product['id']) ? 'border-primary' : ''; ?> <?php echo ($product['stock_level'] === 0) ? 'out-of-stock' : ''; ?>">
                                                <div class="card-body">
                                                    <h5 class="card-title mb-2"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <div class="price">RM<?php echo number_format($product['unit_price'], 2); ?></div>
                                                        <span class="stock-badge badge <?php echo $product['stock_level'] === 0 ? 'bg-danger' : ($product['stock_level'] <= 10 ? 'bg-warning' : 'bg-success'); ?>">
                                                            <?php echo $product['stock_level'] === 0 ? 'Out of stock' : $product['stock_level'] . ' in stock'; ?>
                                                        </span>
                                                    </div>
                                                    <p class="card-text small text-muted mb-3">
                                                        <?php echo htmlspecialchars($product['description'] ?? 'Fresh from our bakery'); ?>
                                                    </p>
                                                    <div class="quantity-control">
                                                        <button type="button" class="btn-quantity" data-action="decrement" <?php echo ($product['stock_level'] === 0) ? 'disabled' : ''; ?>>-</button>
                                                        <input type="number" 
                                                               class="quantity-input" 
                                                               name="items[<?php echo $product['id']; ?>]" 
                                                               value="<?php echo $reorder_quantities[$product['id']] ?? 0; ?>" min="0"
                                                               max="<?php echo $product['stock_level']; ?>"
                                                               data-price="<?php echo $product['unit_price']; ?>"
                                                               onchange="updateOrderSummary()" 
                                                               oninput="this.value = this.value.replace(/[^0-9]/g, ''); 
                                                                        if(parseInt(this.value) > parseInt(this.max)) this.value = this.max;"
                                                               <?php echo ($product['stock_level'] === 0) ? 'disabled' : ''; ?>>
                                                        <button type="button" class="btn-quantity" data-action="increment" <?php echo ($product['stock_level'] === 0) ? 'disabled' : ''; ?>>+</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Category Tabs -->
                            <?php foreach ($categorized_products as $category => $products): 
                                if (empty($category)) continue;
                                $category_id = 'cat-' . preg_replace('/\s+/', '-', strtolower($category));
                            ?>
                                <div class="tab-pane fade" id="<?php echo $category_id; ?>" role="tabpanel">
                                    <div class="row">
                                        <?php foreach ($products as $product): ?>
                                            <div class="col-md-6 col-lg-4 mb-3 product-item" data-name="<?php echo strtolower($product['product_name']); ?>">
                                                <div class="card product-card h-100 <?php echo ($highlight_product == $product['id']) ? 'border-primary' : ''; ?> <?php echo ($product['stock_level'] === 0) ? 'out-of-stock' : ''; ?>">
                                                    <div class="card-body">
                                                        <h5 class="card-title mb-2"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <div class="price">RM<?php echo number_format($product['unit_price'], 2); ?></div>
                                                            <span class="stock-badge badge <?php echo $product['stock_level'] === 0 ? 'bg-danger' : ($product['stock_level'] <= 10 ? 'bg-warning' : 'bg-success'); ?>">
                                                                <?php echo $product['stock_level'] === 0 ? 'Out of stock' : $product['stock_level'] . ' in stock'; ?>
                                                            </span>
                                                        </div>
                                                        <p class="card-text small text-muted mb-3">
                                                            <?php echo htmlspecialchars($product['description'] ?? 'Fresh from our bakery'); ?>
                                                        </p>
                                                        <div class="quantity-control">
                                                            <button type="button" class="btn-quantity" data-action="decrement" <?php echo ($product['stock_level'] === 0) ? 'disabled' : ''; ?>>-</button>
                                                            <input type="number" 
                                                                   class="quantity-input" 
                                                                   name="items[<?php echo $product['id']; ?>]" 
                                                                   value="<?php echo $reorder_quantities[$product['id']] ?? 0; ?>" min="0"
                                                                   max="<?php echo $product['stock_level']; ?>"
                                                                   data-price="<?php echo $product['unit_price']; ?>"
                                                                   onchange="updateOrderSummary()" 
                                                                   oninput="this.value = this.value.replace(/[^0-9]/g, ''); 
                                                                            if(parseInt(this.value) > parseInt(this.max)) this.value = this.max;"
                                                                   <?php echo ($product['stock_level'] === 0) ? 'disabled' : ''; ?>>
                                                            <button type="button" class="btn-quantity" data-action="increment" <?php echo ($product['stock_level'] === 0) ? 'disabled' : ''; ?>>+</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Active Promotions Card -->
                        <?php if (!empty($active_promotions)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-tag-fill me-2"></i>Active Promotions</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($active_promotions as $promo): ?>
                                    <div class="promotion-item mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="promotion-code fw-bold"><?php echo htmlspecialchars($promo['code']); ?></div>
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

                        <!-- Order Summary Card -->
                        <div class="card order-summary-card sticky-top" style="top: 20px;">
                            <div class="card-header">
                                <h5 class="mb-0">Order Summary</h5>
                            </div>
                            <div class="card-body">
                                <div id="selected-items-container" class="mb-4">
                                    <div id="no-items-message" class="text-center py-3">
                                        <i class="bi bi-cart3 text-muted fs-1"></i>
                                        <p class="mt-2 text-muted">No items added yet</p>
                                    </div>
                                    <div id="selected-items-list" style="display: none;">
                                        <!-- Selected items will be displayed here -->
                                    </div>
                                </div>
                                
                                <div class="order-summary-item">
                                    <span>Total Items:</span>
                                    <span id="totalItems">0</span>
                                </div>
                                
                                <div class="order-summary-total">
                                    <span>Subtotal Amount:</span>
                                    <span>RM<span id="subtotalAmount">0.00</span></span>
                                </div>

                                <div id="discountSection" class="mb-4" style="display: none;">
                                    <div class="order-summary-item text-success">
                                        <span>Discount:</span>
                                        <span>-RM<span id="discountAmount">0.00</span></span>
                                    </div>
                                    <div class="order-summary-item border-top pt-2">
                                        <span class="fw-bold">Final Total:</span>
                                        <span class="fw-bold">RM<span id="finalAmount">0.00</span></span>
                                    </div>
                                </div>
                                
                                <div class="mb-3 mt-4">
                                    <label class="form-label">Delivery Address</label>
                                    <textarea class="form-control" name="delivery_address" rows="3" required><?php echo htmlspecialchars($_SESSION['user']['address']); ?></textarea>
                                    <div class="invalid-feedback">Please provide a delivery address.</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Payment Method</label>
                                    <select class="form-select" name="payment_method" required>
                                        <option value="cash">Cash on Delivery</option>
                                        <option value="card">Credit/Debit Card</option>
                                        <option value="online">Online Banking</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a payment method.</div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Promotion Code</label>
                                    <input type="text" class="form-control" name="promo_code" placeholder="Enter promotion code">
                                    <small id="promoMessage" class="form-text"></small>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" id="place-order-btn" class="btn btn-primary" name="place_order" disabled>
                                        <i class="bi bi-cart-check me-2"></i> Place Order
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Custom styles for product listing */
.product-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border-radius: 8px;
    overflow: hidden;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
}

.product-card.border-primary {
    border-width: 2px !important;
    box-shadow: 0 0 15px rgba(5, 97, 252, 0.3);
}

.product-card.out-of-stock {
    border-color: #dc3545;
    background-color: rgba(220, 53, 69, 0.05);
}

.quantity-control {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 10px;
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
    transition: all 0.2s;
    font-size: 1.25rem;
    user-select: none;
    margin: 0 6px;
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
    margin: 0 10px;
    font-size: 1.1rem;
}

.order-summary-card {
    border-radius: 8px;
    box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
}

.order-summary-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.order-summary-total {
    display: flex;
    justify-content: space-between;
    padding-top: 10px;
    border-top: 1px solid #e9ecef;
    font-weight: bold;
    font-size: 1.1rem;
}

.order-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #f2f2f2;
}

.order-item-name {
    font-weight: 500;
}

.order-item-details {
    font-size: 0.85rem;
    color: #6c757d;
}

.nav-pills .nav-link {
    color: #495057;
    padding: 0.5rem 1rem;
    border-radius: 50rem;
    margin-right: 0.5rem;
}

.nav-pills .nav-link.active {
    background-color: #0561FC;
    color: white;
}

.price {
    font-weight: 600;
    color: #0561FC;
    font-size: 1.1rem;
}

.stock-badge {
    font-size: 0.7rem;
    padding: 0.25em 0.6em;
}

.promotion-item {
    padding-bottom: 1rem;
    border-bottom: 1px solid #e9ecef;
}

.promotion-item:last-child {
    padding-bottom: 0;
    border-bottom: none;
}

.promotion-code {
    font-family: monospace;
    padding: 0.2rem 0.5rem;
    background: #f8f9fa;
    border-radius: 4px;
}

@media (max-width: 768px) {
    .quantity-control .btn-quantity {
        width: 44px;
        height: 44px;
        font-size: 1.4rem;
        margin: 0 8px;
    }
    .quantity-control input {
        width: 70px;
        height: 44px;
        font-size: 1.3rem;
        margin: 0 12px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle quantity increment/decrement using event delegation
    const container = document.querySelector('.main-content');
    if (container) {
        container.addEventListener('click', function(event) {
            const target = event.target;
            if (target.classList.contains('btn-quantity')) {
                const input = target.parentNode.querySelector('input');
                const action = target.getAttribute('data-action');
                
                let currentValue = parseInt(input.value);
                const max = parseInt(input.getAttribute('max'));
                
                if (action === 'increment' && currentValue < max) {
                    input.value = currentValue + 1;
                } else if (action === 'decrement' && currentValue > 0) {
                    input.value = currentValue - 1;
                }
                
                // Update order summary
                updateOrderSummary();
                
                // Highlight product card if selected
                const productCard = target.closest('.product-card');
                if (parseInt(input.value) > 0) {
                    productCard.classList.add('selected');
                } else {
                    productCard.classList.remove('selected');
                }
            }
        });
    }
      // Search functionality
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            document.querySelectorAll('.product-item').forEach(item => {
                const productName = item.getAttribute('data-name');
                if (productName.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
      // Add validation for manual input in quantity fields
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('input', function() {
            const max = parseInt(this.getAttribute('max')) || 0;
            const currentValue = parseInt(this.value) || 0;
            
            // Ensure the value is not negative and does not exceed max
            if (currentValue < 0) {
                this.value = 0;
            } else if (currentValue > max) {
                this.value = max;
            }
            
            // Update order summary when manually entering values
            updateOrderSummary();
            
            // Update highlight on the product card
            const productCard = this.closest('.product-card');
            if (parseInt(this.value) > 0) {
                productCard.classList.add('selected');
            } else {
                productCard.classList.remove('selected');
            }
        });
    });
    
    // Highlight product if passed in URL
    <?php if ($highlight_product): ?>
    const tabs = document.querySelectorAll('.nav-link');
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function() {
            const highlightedProduct = document.querySelector(`.product-card.border-primary`);
            if (highlightedProduct) {
                highlightedProduct.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    });
    
    setTimeout(() => {
        const highlightedProduct = document.querySelector(`.product-card.border-primary`);
        if (highlightedProduct) {
            highlightedProduct.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, 500);
    <?php endif; ?>
    
    // Initialize order summary
    updateOrderSummary();
});

// Update order summary
function updateOrderSummary() {
    let totalItems = 0;
    let subtotalAmount = 0;
    const selectedItems = {};
    
    // Calculate initial total
    document.querySelectorAll('.quantity-input').forEach(input => {
        const quantity = parseInt(input.value) || 0;
        const price = parseFloat(input.getAttribute('data-price'));
        
        if (quantity > 0) {
            totalItems += quantity;
            subtotalAmount += quantity * price;
            
            // Store selected item details
            const productCard = input.closest('.product-card');
            const productName = productCard.querySelector('.card-title').textContent;
            
            const productId = input.name.match(/\[(\d+)\]/)[1];
            selectedItems[productId] = {
                name: productName,
                quantity: quantity,
                price: price,
                subtotal: quantity * price
            };
        }
    });
    
    // Update summary elements
    document.getElementById('totalItems').textContent = totalItems;
    document.getElementById('subtotalAmount').textContent = subtotalAmount.toFixed(2);
    
    // Check for promotion code
    const promoInput = document.querySelector('input[name="promo_code"]');
    if (promoInput && promoInput.value.trim() && subtotalAmount > 0) {
        const formData = new FormData();
        formData.append('code', promoInput.value.trim());
        formData.append('subtotal', subtotalAmount);
        
        // Make AJAX request to validate promo code
        fetch('validate_promo.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const discountSection = document.getElementById('discountSection');
            const promoMessage = document.getElementById('promoMessage');
            
            if (data.valid) {
                let discount = data.discount_type === 'percentage' 
                    ? subtotalAmount * (data.discount_value / 100)
                    : data.discount_value;
                
                const finalAmount = Math.max(0, subtotalAmount - discount);
                
                document.getElementById('discountAmount').textContent = discount.toFixed(2);
                document.getElementById('finalAmount').textContent = finalAmount.toFixed(2);
                
                promoMessage.textContent = 'Promotion code applied successfully!';
                promoMessage.className = 'form-text text-success';
                discountSection.style.display = 'block';
            } else {
                promoMessage.textContent = data.message;
                promoMessage.className = 'form-text text-danger';
                discountSection.style.display = 'none';
                document.getElementById('finalAmount').textContent = subtotalAmount.toFixed(2);
            }
        })
        .catch(() => {
            document.getElementById('discountSection').style.display = 'none';
            document.getElementById('finalAmount').textContent = subtotalAmount.toFixed(2);
        });
    } else {
        document.getElementById('discountSection').style.display = 'none';
        document.getElementById('finalAmount').textContent = subtotalAmount.toFixed(2);
        if (promoInput) {
            document.getElementById('promoMessage').textContent = '';
        }
    }
    
    // Update selected items list
    const noItemsMessage = document.getElementById('no-items-message');
    const selectedItemsList = document.getElementById('selected-items-list');
    
    if (Object.keys(selectedItems).length > 0) {
        noItemsMessage.style.display = 'none';
        selectedItemsList.style.display = 'block';
        
        // Clear previous list
        selectedItemsList.innerHTML = '';
        
        // Add selected items to the list
        for (const [id, item] of Object.entries(selectedItems)) {
            const itemElement = document.createElement('div');
            itemElement.className = 'order-item';
            itemElement.innerHTML = `
                <div>
                    <div class="order-item-name">${item.quantity}x ${item.name}</div>
                    <div class="order-item-details">RM${item.price.toFixed(2)} each</div>
                </div>
                <div class="text-end fw-bold">RM${item.subtotal.toFixed(2)}</div>
            `;
            selectedItemsList.appendChild(itemElement);
        }
    } else {
        noItemsMessage.style.display = 'block';
        selectedItemsList.style.display = 'none';
    }
    
    // Enable/disable order button
    document.getElementById('place-order-btn').disabled = totalItems === 0;
}

// Add event listener for promo code input
document.querySelector('input[name="promo_code"]').addEventListener('input', function() {
    updateOrderSummary();
});

// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        const forms = document.getElementsByClassName('needs-validation');
        Array.from(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

<?php include '../includes/footer.php'; ?>