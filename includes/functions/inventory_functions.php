<?php
require_once __DIR__ . '/../../config/db_connection.php';

/**
 * Fetch inventory data
 */
function fetch_inventory_data($conn) {
    $inventory_query = $conn->query("SELECT id, product_name, stock_level, unit_price, category FROM inventory WHERE status = 'active'");
    return $inventory_query->fetch_all(MYSQLI_ASSOC);
}

/**
 * Fetch raw materials inventory data
 */
function fetch_small_inventory_data($conn, $sort = 'name') {
    $order_by = match($sort) {
        'id' => 'Inventory_ID',
        'name' => 'Ingredient_Name',
        default => 'Ingredient_Name'
    };
    
    $query = "SELECT * FROM small_inventory ORDER BY {$order_by}";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        return array();
    }
    
    $small_inventory_data = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $small_inventory_data[] = $row;
    }
    
    return $small_inventory_data;
}

/**
 * Fetch inventory by category
 */
function fetch_inventory_by_category($conn) {
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE status = 'active' ORDER BY category, product_name");
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Group products by category
    $categorized_products = [];
    foreach ($products as $product) {
        $categorized_products[$product['category']][] = $product;
    }
    
    return $categorized_products;
}

/**
 * Update product stock level
 */
function update_product_stock($conn, $product_id, $new_stock) {
    $stmt = $conn->prepare("UPDATE inventory SET stock_level = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_stock, $product_id);
    return $stmt->execute();
}
?>