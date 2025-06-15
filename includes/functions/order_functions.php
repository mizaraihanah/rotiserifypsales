<?php
require_once __DIR__ . '/../../config/db_connection.php';

/**
 * Create a new order
 */
function create_order($conn, $guest_id, $order_number, $total_amount, $payment_method, $delivery_address, $order_type, $items, $promo_code = null, $subtotal = null) {
    try {
        $conn->begin_transaction();
        
        // Calculate initial subtotal from items
        $subtotal = 0;
        foreach ($items as $item_id => $quantity) {
            if ($quantity > 0) {
                $stmt = $conn->prepare("SELECT unit_price FROM inventory WHERE id = ?");
                $stmt->bind_param("i", $item_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                $subtotal += $product['unit_price'] * $quantity;
            }
        }
        
        // Initialize discount
        $discount = 0;
        $total_amount = $subtotal;
        
        // Apply promotion if code provided
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
                    $discount = $promotion['discount_value'];
                }
                $total_amount = max(0, $subtotal - $discount);
            }
        }
        
        // Create order with proper subtotal and discount
        $stmt = $conn->prepare("
            INSERT INTO orders (
                guest_id, 
                order_number, 
                order_date, 
                total_amount,
                subtotal,
                discount,
                promo_code, 
                status,
                payment_method,
                payment_status,
                delivery_address, 
                order_type, 
                clerk_id
            ) VALUES (
                ?, ?, NOW(), ?, ?, ?, ?, 'pending', ?, 'pending', ?, ?, NULL
            )
        ");
        
        $stmt->bind_param("issdddsss", 
            $guest_id, 
            $order_number, 
            $total_amount,
            $subtotal,
            $discount,
            $promo_code,
            $payment_method, 
            $delivery_address, 
            $order_type
        );
        
        $stmt->execute();
        $order_id = $conn->insert_id;
        
        // Add order items and update stock_level
        foreach ($items as $item_id => $quantity) {
            if ($quantity > 0) {                // Get product details
                $stmt = $conn->prepare("SELECT unit_price, stock_level, product_name FROM inventory WHERE id = ?");
                $stmt->bind_param("i", $item_id);
                $stmt->execute();
                $product = $stmt->get_result()->fetch_assoc();
                
                // Double-check stock level before processing (safeguard against race conditions)
                if ($quantity > $product['stock_level']) {
                    throw new Exception("Insufficient stock for '" . $product['product_name'] . "'. Available: " . $product['stock_level'] . ", Requested: " . $quantity);
                }
                
                // Insert order item
                $subtotal = $product['unit_price'] * $quantity;
                $stmt = $conn->prepare("
                    INSERT INTO order_items 
                    (order_id, product_id, quantity, unit_price, subtotal) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iiids", 
                    $order_id, 
                    $item_id, 
                    $quantity, 
                    $product['unit_price'], 
                    $subtotal
                );
                $stmt->execute();
                  // Update stock level with safeguard against negative stock
                $new_stock = max(0, $product['stock_level'] - $quantity);
                $stmt = $conn->prepare("UPDATE inventory SET stock_level = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_stock, $item_id);
                $stmt->execute();
            }
        }
        
        $conn->commit();
        return $order_id;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Order creation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get order details by ID
 */
function get_order_details($conn, $order_id, $guest_id = null) {
    // Build the query based on whether we're filtering by guest_id
    $query = "
        SELECT o.*, g.fullname, g.contact, g.email 
        FROM orders o
        LEFT JOIN guest g ON o.guest_id = g.id
        WHERE o.id = ?
    ";
    $params = [$order_id];
    $types = "i";
    
    if ($guest_id !== null) {
        $query .= " AND o.guest_id = ?";
        $params[] = $guest_id;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    
    if (!$order) {
        return false;
    }
    
    // Get order items
    $stmt = $conn->prepare("
        SELECT oi.*, i.product_name 
        FROM order_items oi
        JOIN inventory i ON oi.product_id = i.id
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    return [
        'order' => $order,
        'items' => $order_items
    ];
}

/**
 * Cancel an order
 */
function cancel_order($conn, $order_id, $guest_id) {
    try {
        $conn->begin_transaction();
        
        // Check if order belongs to the current user and is still cancellable
        $stmt = $conn->prepare("
            SELECT id, status, total_amount 
            FROM orders 
            WHERE id = ? AND guest_id = ? AND status = 'pending'
        ");
        $stmt->bind_param("ii", $order_id, $guest_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        
        if (!$order) {
            throw new Exception("Order not found or cannot be cancelled.");
        }
        
        // Update order status to cancelled
        $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        
        if ($stmt->execute()) {
            // Restore inventory stock levels
            $stmt = $conn->prepare("
                UPDATE inventory i
                JOIN order_items oi ON i.id = oi.product_id
                SET i.stock_level = i.stock_level + oi.quantity
                WHERE oi.order_id = ?
            ");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            
            $conn->commit();
            return true;
        } else {
            throw new Exception("Failed to cancel order.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Order cancellation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get orders for a customer
 */
function get_customer_orders($conn, $guest_id, $filters = []) {
    $where_clauses = ["o.guest_id = ?"];
    $params = [$guest_id];
    $types = "i";
    
    // Add filters if provided
    if (!empty($filters['status'])) {
        $where_clauses[] = "o.status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }
    
    if (!empty($filters['date_from'])) {
        $where_clauses[] = "DATE(o.order_date) >= ?";
        $params[] = $filters['date_from'];
        $types .= "s";
    }
    
    if (!empty($filters['date_to'])) {
        $where_clauses[] = "DATE(o.order_date) <= ?";
        $params[] = $filters['date_to'];
        $types .= "s";
    }
    
    $where_clause = implode(" AND ", $where_clauses);
    
    // Set default sort order if not specified
    $sort_by = $filters['sort_by'] ?? 'order_date';
    $sort_order = $filters['sort_order'] ?? 'DESC';
    
    // Pagination
    $limit = $filters['limit'] ?? 10;
    $offset = $filters['offset'] ?? 0;
    
    // Get orders
    $query = "
        SELECT o.*, 
               COUNT(oi.id) as item_count,
               GROUP_CONCAT(CONCAT(oi.quantity, 'x ', i.product_name) SEPARATOR ', ') as items
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        LEFT JOIN inventory i ON oi.product_id = i.id
        WHERE {$where_clause}
        GROUP BY o.id 
        ORDER BY {$sort_by} {$sort_order}
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM orders o WHERE " . $where_clause;
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    
    return [
        'orders' => $orders,
        'total' => $total
    ];
}
?>