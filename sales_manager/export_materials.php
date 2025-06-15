<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(1); // 1 for sales manager
require_once '../config/db_connection.php';
require_once '../includes/functions/inventory_functions.php';

// Get export parameters
$search = $_GET['search'] ?? '';
$stock_filter = $_GET['stock'] ?? '';
$sort = $_GET['sort'] ?? 'name';
$format = $_GET['format'] ?? 'csv';

// Fetch raw materials inventory data
$small_inventory_data = fetch_small_inventory_data($conn, $sort);

// Apply filters
if (!empty($search) || !empty($stock_filter)) {
    $filtered_data = [];
    foreach ($small_inventory_data as $item) {
        $match_search = empty($search) ||
            (stripos($item['Ingredient_Name'], $search) !== false) ||
            (stripos((string)$item['Inventory_ID'], $search) !== false);

        $match_stock = true;
        if ($stock_filter === 'low') {
            $match_stock = $item['Ingredient_kg'] < 20 && $item['Ingredient_kg'] > 0;
        } else if ($stock_filter === 'out') {
            $match_stock = $item['Ingredient_kg'] <= 0;
        } else if ($stock_filter === 'sufficient') {
            $match_stock = $item['Ingredient_kg'] >= 20;
        }

        if ($match_search && $match_stock) {
            $filtered_data[] = $item;
        }
    }
    $small_inventory_data = $filtered_data;
}

// Set headers based on format
switch($format) {
    case 'csv':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="materials_inventory_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        
        // Write CSV header
        fputcsv($output, ['ID', 'Ingredient Name', 'Quantity (kg)', 'Status']);
        
        // Write data
        foreach ($small_inventory_data as $item) {
            $status = 'Sufficient';
            if ($item['Ingredient_kg'] <= 0) {
                $status = 'Out of Stock';
            } else if ($item['Ingredient_kg'] < 20) {
                $status = 'Low Stock';
            }
            
            fputcsv($output, [
                $item['Inventory_ID'],
                $item['Ingredient_Name'],
                number_format($item['Ingredient_kg'], 2),
                $status
            ]);
        }
        
        fclose($output);
        break;
        
    case 'excel':
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="materials_inventory_' . date('Y-m-d') . '.xls"');
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Ingredient Name</th><th>Quantity (kg)</th><th>Status</th></tr>";
        
        foreach ($small_inventory_data as $item) {
            $status = 'Sufficient';
            if ($item['Ingredient_kg'] <= 0) {
                $status = 'Out of Stock';
            } else if ($item['Ingredient_kg'] < 20) {
                $status = 'Low Stock';
            }
            
            echo "<tr>";
            echo "<td>" . $item['Inventory_ID'] . "</td>";
            echo "<td>" . htmlspecialchars($item['Ingredient_Name']) . "</td>";
            echo "<td>" . number_format($item['Ingredient_kg'], 2) . "</td>";
            echo "<td>" . $status . "</td>";
            echo "</tr>";
        }
          echo "</table>";
        break;
}