<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(1); // 1 for sales manager
require_once '../config/db_connection.php';
require_once '../includes/functions/inventory_functions.php';

// Get export parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$stock_filter = $_GET['stock'] ?? '';
$format = $_GET['format'] ?? 'csv';

// Validate format
$allowed_formats = ['csv', 'excel'];
if (!in_array($format, $allowed_formats)) {
    header('Location: sm_inventory.php?error=invalid_format');
    exit;
}

try {
    // Fetch inventory data
    $inventory_data = fetch_inventory_data($conn);
    
    if (empty($inventory_data)) {
        header('Location: sm_inventory.php?error=no_data');
        exit;
    }

    // Apply filters
    if (!empty($search) || !empty($category_filter) || !empty($stock_filter)) {
        $filtered_data = [];
        foreach ($inventory_data as $item) {
            // Apply search filter
            $match_search = empty($search) || 
                            stripos($item['product_name'], $search) !== false ||
                            stripos((string)$item['id'], $search) !== false;
            
            // Apply category filter
            $match_category = empty($category_filter) || $item['category'] === $category_filter;
            
            // Apply stock filter
            $match_stock = true;
            if ($stock_filter === 'low') {
                $match_stock = $item['stock_level'] <= 10 && $item['stock_level'] > 0;
            } else if ($stock_filter === 'out') {
                $match_stock = $item['stock_level'] <= 0;
            } else if ($stock_filter === 'sufficient') {
                $match_stock = $item['stock_level'] > 10;
            }
            
            if ($match_search && $match_category && $match_stock) {
                $filtered_data[] = $item;
            }
        }
        $inventory_data = $filtered_data;
    }

} catch (Exception $e) {
    // Log error and redirect back
    error_log("Export error: " . $e->getMessage());
    header('Location: sm_inventory.php?error=export_failed');
    exit;
}

// Set headers based on format
switch($format) {
    case 'csv':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="inventory_report_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        
        // Write CSV header
        fputcsv($output, ['ID', 'Product Name', 'Category', 'Stock Level', 'Unit Price', 'Total Value', 'Status']);
        
        // Write data
        foreach ($inventory_data as $item) {
            $status = 'Sufficient';
            if ($item['stock_level'] <= 0) {
                $status = 'Out of Stock';
            } else if ($item['stock_level'] <= 10) {
                $status = 'Low Stock';
            }
            
            $total_value = $item['unit_price'] * $item['stock_level'];
              fputcsv($output, [
                $item['id'],
                $item['product_name'],
                $item['category'],
                $item['stock_level'],
                'RM ' . number_format($item['unit_price'], 2),
                'RM ' . number_format($total_value, 2),
                $status
            ]);
        }
        
        fclose($output);
        break;
        
    case 'excel':
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="inventory_report_' . date('Y-m-d') . '.xls"');
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Product Name</th><th>Category</th><th>Stock Level</th><th>Unit Price</th><th>Total Value</th><th>Status</th></tr>";
        
        foreach ($inventory_data as $item) {
            $status = 'Sufficient';
            if ($item['stock_level'] <= 0) {
                $status = 'Out of Stock';
            } else if ($item['stock_level'] <= 10) {
                $status = 'Low Stock';
            }
            
            $total_value = $item['unit_price'] * $item['stock_level'];
            
            echo "<tr>";
            echo "<td>" . $item['id'] . "</td>";
            echo "<td>" . htmlspecialchars($item['product_name']) . "</td>";
            echo "<td>" . htmlspecialchars($item['category']) . "</td>";
            echo "<td>" . $item['stock_level'] . "</td>";            echo "<td>RM " . number_format($item['unit_price'], 2) . "</td>";
            echo "<td>RM " . number_format($total_value, 2) . "</td>";
            echo "<td>" . $status . "</td>";
            echo "</tr>";
        }
          echo "</table>";
        break;
        
    default:
        // Redirect back if invalid format
        header('Location: sm_inventory.php');
        exit;
}
?>
