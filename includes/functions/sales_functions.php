<?php
require_once __DIR__ . '/../../config/db_connection.php';

/**
 * Fetch daily, weekly, and monthly sales data
 */
function fetch_sales_data($conn, $start_date, $end_date, $sales_type) {
    // For debugging purposes, log the dates being used
    error_log("Fetching sales data with date range: $start_date to $end_date");
    
    // Ensure proper date formatting for SQL queries
    // If start_date doesn't include time, add 00:00:00
    if (strlen($start_date) == 10) {
        $start_date .= ' 00:00:00';
    }
    
    // If end_date doesn't include time, add 23:59:59
    if (strlen($end_date) == 10) {
        $end_date .= ' 23:59:59';
    }
    
    // Build the WHERE clause based on filters
    $where_clause = "WHERE order_date BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    $param_types = "ss";

    if ($sales_type !== 'all') {
        $where_clause .= " AND order_type = ?";
        $params[] = $sales_type;
        $param_types .= "s";
    }

    // Add filter for completed orders only
    $where_clause .= " AND status = 'completed'";

    // Query to check if there are any orders in the database for debugging
    $test_query = $conn->prepare("SELECT COUNT(*) as total FROM orders");
    $test_query->execute();
    $total_orders = $test_query->get_result()->fetch_assoc()['total'] ?? 0;
    error_log("Total orders in database: $total_orders");

    // Fetch daily sales data
    $daily_sales_query = "SELECT DATE(order_date) AS sale_date, SUM(total_amount) AS total_revenue 
                         FROM orders $where_clause 
                         GROUP BY sale_date
                         ORDER BY sale_date DESC";
    
    error_log("Daily sales query: " . $daily_sales_query);
    $daily_sales = $conn->prepare($daily_sales_query);
    $daily_sales->bind_param($param_types, ...$params);
    $daily_sales->execute();
    $daily_sales = $daily_sales->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch weekly sales data (Sunday to Saturday)
    $weekly_sales = $conn->prepare("
        SELECT 
            CONCAT(YEAR(order_date), '-W', WEEK(order_date)) AS sale_week,
            SUM(total_amount) AS total_revenue 
        FROM orders 
        $where_clause 
        GROUP BY YEAR(order_date), WEEK(order_date)
    ");
    $weekly_sales->bind_param($param_types, ...$params);
    $weekly_sales->execute();
    $weekly_sales = $weekly_sales->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch monthly sales data for the entire year
    $monthly_sales = $conn->prepare("
        SELECT 
            DATE_FORMAT(order_date, '%Y-%m') AS sale_month,
            SUM(total_amount) AS total_revenue 
        FROM orders 
        WHERE YEAR(order_date) = YEAR(?)
        GROUP BY YEAR(order_date), MONTH(order_date)
        ORDER BY MONTH(order_date)
    ");
    $monthly_sales->bind_param("s", $start_date);
    $monthly_sales->execute();
    $monthly_sales = $monthly_sales->get_result()->fetch_all(MYSQLI_ASSOC);

    return [$daily_sales, $weekly_sales, $monthly_sales];
}

/**
 * Fetch today's sales data
 */
function fetch_today_sales_data($conn, $date = null) {
    // If no date is provided, use today's date
    $date = $date ?? date('Y-m-d');
    
    // Query to get today's total sales
    $total_sales_query = $conn->prepare("
        SELECT 
            SUM(total_amount) AS total_sales,
            SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) AS cash_sales,
            SUM(CASE WHEN payment_method = 'card' THEN total_amount ELSE 0 END) AS card_sales,
            SUM(CASE WHEN payment_method = 'online' THEN total_amount ELSE 0 END) AS banking_sales
        FROM orders 
        WHERE DATE(order_date) = ? 
        AND status = 'completed'
    ");
    $total_sales_query->bind_param("s", $date);
    $total_sales_query->execute();
    $result = $total_sales_query->get_result()->fetch_assoc();

    return [
        'total_sales' => $result['total_sales'] ?? 0,
        'cash_sales' => $result['cash_sales'] ?? 0,
        'card_sales' => $result['card_sales'] ?? 0,
        'banking_sales' => $result['banking_sales'] ?? 0
    ];
}

/**
 * Fetch hot selling items
 */
function fetch_hot_items($conn, $start_date, $end_date) {
    // Ensure the end date includes the full day
    $adjusted_end_date = date('Y-m-d', strtotime($end_date . ' +1 day'));
    
    $query = "
        SELECT 
            p.product_name,
            p.id as product_id,
            SUM(od.quantity) as total_quantity,
            SUM(od.quantity * od.unit_price) as total_revenue,
            (SUM(od.quantity * od.unit_price) / (
                SELECT GREATEST(SUM(quantity * unit_price), 1)
                FROM order_items od2
                JOIN orders o2 ON od2.order_id = o2.id
                WHERE o2.order_date >= ? AND o2.order_date < ?
            ) * 100) as sales_percentage
        FROM order_items od
        JOIN inventory p ON od.product_id = p.id
        JOIN orders o ON od.order_id = o.id
        WHERE o.order_date >= ? AND o.order_date < ?
        AND o.status = 'completed'
        GROUP BY p.id, p.product_name
        ORDER BY total_quantity DESC
        LIMIT 10
    ";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssss", $start_date, $adjusted_end_date, $start_date, $adjusted_end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $hot_items = [];
        while ($row = $result->fetch_assoc()) {
            $hot_items[] = $row;
        }

        return $hot_items;
    } catch (Exception $e) {
        error_log("Hot items fetch error: " . $e->getMessage());
        return [];
    }
}
?>