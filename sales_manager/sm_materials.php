<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(1); // 1 for sales manager (formerly supervisor)
require_once '../config/db_connection.php';

// Helper class to fetch data from YOUR inventory system
class InventoryAPI {
    private $api_base_url = 'http://localhost/invsystem14-main/api/'; // YOUR API URL
    
    public function getInventoryData($search = '', $stock_filter = '', $sort = 'name') {
        $params = http_build_query([
            'search' => $search,
            'status' => $this->convertStockFilter($stock_filter),
            'type' => 'all'
        ]);
        
        $url = $this->api_base_url . 'get_inventory_status.php?' . $params;
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'method' => 'GET'
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                error_log("Failed to fetch from API: " . $url);
                return ['success' => false, 'error' => 'Cannot connect to inventory API', 'data' => []];
            }
            
            $data = json_decode($response, true);
            
            if ($data && $data['success']) {
                return [
                    'success' => true,
                    'data' => $this->formatInventoryData($data['data'], $sort),
                    'api_info' => $data['database_info'] ?? []
                ];
            } else {
                error_log("API returned error: " . ($data['error'] ?? 'Unknown error'));
                return ['success' => false, 'error' => $data['error'] ?? 'API error', 'data' => []];
            }
        } catch (Exception $e) {
            error_log("Inventory API Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'data' => []];
        }
    }
    
    private function convertStockFilter($filter) {
        switch($filter) {
            case 'low': return 'low_stock';
            case 'out': return 'out_of_stock';
            case 'sufficient': return 'sufficient';
            default: return 'all';
        }
    }
    
    private function formatInventoryData($data, $sort) {
        $formatted = [];
        
        foreach ($data as $item) {
            // Convert your products data to format expected by sm_materials.php
            $formatted[] = [
                'Inventory_ID' => $item['original_id'], // PROD0001, PROD0002, etc.
                'Ingredient_Name' => $item['item_name'], // Product name from your system
                'Ingredient_kg' => $item['quantity_available'], // Stock quantity
                'unit_type' => $item['unit_type'], // 'units'
                'stock_status' => $item['stock_status'], // low_stock, sufficient, etc.
                'source_table' => $item['source_table'], // 'products'
                'category' => $item['category'] ?? 'Inventory Items',
                'unit_price' => $item['unit_price'] ?? 0,
                'reorder_threshold' => $item['reorder_threshold'] ?? 10,
                'last_updated' => $item['last_updated'] ?? ''
            ];
        }
        
        // Sort the data
        if ($sort === 'name') {
            usort($formatted, function($a, $b) {
                return strcmp($a['Ingredient_Name'], $b['Ingredient_Name']);
            });
        } elseif ($sort === 'id') {
            usort($formatted, function($a, $b) {
                return strcmp($a['Inventory_ID'], $b['Inventory_ID']);
            });
        }
        
        return $formatted;
    }
    
    public function testConnection() {
        $url = $this->api_base_url . 'get_inventory_status.php?type=all';
        
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'method' => 'GET'
                ]
            ]);
            
            $response = file_get_contents($url, false, $context);
            
            if ($response === false) {
                return [
                    'success' => false,
                    'error' => 'Cannot connect to inventory API',
                    'url' => $url
                ];
            }
            
            $data = json_decode($response, true);
            
            return [
                'success' => $data['success'] ?? false,
                'url' => $url,
                'response' => $data,
                'total_items' => $data['total_items'] ?? 0
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'url' => $url
            ];
        }
    }
}

// Initialize the inventory API
$inventoryAPI = new InventoryAPI();

// Enhanced filtering and search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';

// Test connection first
$connection_test = $inventoryAPI->testConnection();

// Fetch data from API
if ($connection_test['success']) {
    $api_response = $inventoryAPI->getInventoryData($search, $stock_filter, $sort);
    $small_inventory_data = $api_response['data'];
    $api_info = $api_response['api_info'] ?? [];
} else {
    $small_inventory_data = [];
    $api_info = [];
}

// Calculate statistics
$total_ingredients = count($small_inventory_data);
$low_stock_count = 0;
$out_of_stock_count = 0;
$total_inventory_weight = 0;

foreach ($small_inventory_data as $item) {
    $quantity = $item['Ingredient_kg'];
    $total_inventory_weight += $quantity;
    
    if ($item['stock_status'] === 'out_of_stock') {
        $out_of_stock_count++;
    } elseif ($item['stock_status'] === 'low_stock') {
        $low_stock_count++;
    }
}

// Set page title
$page_title = "Raw Materials - Sales Manager (Live Inventory Data)";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        :root {
            --primary: #0561FC;
            --primary-light: rgba(5, 97, 252, 0.1);
            --primary-dark: #0453d6;
            --secondary: #6c757d;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #343a40;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            margin-left: 250px;
            transition: all 0.3s;
            min-height: 100vh;
            width: calc(100% - 250px);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 1rem;
            }
        }

        .materials-card {
            border-radius: 12px;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
            background-color: white;
            border: none;
            margin-bottom: 1.5rem;
            overflow: hidden;
            width: 100%;
        }

        .card {
            background-color: white;
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
        }

        .card-body {
            flex: 1 1 auto;
            min-height: 1px;
            padding: 1.25rem;
        }

        .card-header {
            padding: 0.75rem 1.25rem;
            margin-bottom: 0;
            background-color: rgba(0,0,0,.03);
            border-bottom: 1px solid rgba(0,0,0,.125);
        }

        .card-footer {
            padding: 0.75rem 1.25rem;
            background-color: rgba(0,0,0,.03);
            border-top: 1px solid rgba(0,0,0,.125);
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -0.75rem;
            margin-left: -0.75rem;
        }

        .col-xl-4, .col-md-6, .col-md-4, .col-md-3, .col-md-2 {
            position: relative;
            width: 100%;
            padding-right: 0.75rem;
            padding-left: 0.75rem;
            margin-bottom: 1rem;
        }

        .col-xl-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }

        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
        }

        .col-md-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }

        .col-md-3 {
            flex: 0 0 25%;
            max-width: 25%;
        }

        .col-md-2 {
            flex: 0 0 16.666667%;
            max-width: 16.666667%;
        }

        /* Force the summary cards to be in a row */
        .summary-cards .row {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
        }

        .summary-cards .col-xl-4 {
            flex: 1 !important;
            max-width: none !important;
            min-width: 0 !important;
        }

        @media (max-width: 1200px) {
            .summary-cards .col-xl-4 {
                flex: 0 0 33.333333% !important;
                max-width: 33.333333% !important;
            }
        }

        @media (max-width: 768px) {
            .summary-cards .row {
                flex-wrap: wrap !important;
            }
            .summary-cards .col-xl-4 {
                flex: 0 0 100% !important;
                max-width: 100% !important;
            }
        }

        .border-left-primary {
            border-left: 4px solid var(--primary);
        }

        .border-left-warning {
            border-left: 4px solid var(--warning);
        }

        .border-left-danger {
            border-left: 4px solid var(--danger);
        }

        .d-flex {
            display: flex !important;
        }

        .justify-content-between {
            justify-content: space-between !important;
        }

        .align-items-center {
            align-items: center !important;
        }

        .mb-4 {
            margin-bottom: 1.5rem !important;
        }

        .mb-0 {
            margin-bottom: 0 !important;
        }

        .me-3 {
            margin-right: 1rem !important;
        }

        .me-2 {
            margin-right: 0.5rem !important;
        }

        .g-3 > * {
            padding-right: calc(var(--bs-gutter-x) * .5);
            padding-left: calc(var(--bs-gutter-x) * .5);
            margin-top: var(--bs-gutter-y);
        }

        .g-3 {
            --bs-gutter-x: 1rem;
            --bs-gutter-y: 1rem;
        }

        .inventory-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }

        .materials-table th {
            font-weight: 600;
            color: #495057;
            border-top: none;
            padding: 1rem;
        }

        .materials-table td {
            padding: 1rem;
            vertical-align: middle;
        }

        .materials-table tbody tr {
            transition: background-color 0.2s ease;
        }

        .materials-table tbody tr:hover {
            background-color: rgba(5, 97, 252, 0.03);
        }

        .progress {
            height: 6px;
            border-radius: 3px;
        }

        .filter-section {
            border-radius: 12px;
            background-color: white;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .search-input {
            position: relative;
        }

        .search-input i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .search-input input {
            padding-left: 2.5rem;
            border-radius: 8px;
            border: 1px solid #ced4da;
        }

        .badge {
            padding: 0.5em 0.7em;
            font-weight: 500;
            border-radius: 30px;
        }

        .badge-sufficient {
            background-color: var(--success);
            color: white;
        }
        
        .badge-low-stock {
            background-color: var(--warning);
            color: #212529;
        }
        
        .badge-out-of-stock {
            background-color: var(--danger);
            color: white;
        }

        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #28a745;
            font-size: 0.9em;
            margin-left: 10px;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            background-color: #28a745;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .api-status {
            margin-bottom: 20px;
        }

        .connection-info {
            font-size: 0.85em;
            margin-top: 5px;
        }

        .no-gutters {
            margin-right: 0;
            margin-left: 0;
        }

        .no-gutters > .col,
        .no-gutters > [class*="col-"] {
            padding-right: 0;
            padding-left: 0;
        }

        .col-auto {
            flex: 0 0 auto;
            width: auto;
        }

        .h5 {
            font-size: 1.25rem;
        }

        .text-xs {
            font-size: 0.75rem;
        }

        .font-weight-bold {
            font-weight: 700;
        }

        .text-uppercase {
            text-transform: uppercase;
        }

        .text-gray-800 {
            color: #5a5c69;
        }

        .text-primary {
            color: var(--primary);
        }

        .text-warning {
            color: var(--warning);
        }

        .text-danger {
            color: var(--danger);
        }

        .text-muted {
            color: #6c757d;
        }

        .small {
            font-size: 0.875em;
        }

        .shadow {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .h-100 {
            height: 100%;
        }

        .py-2 {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }

        .py-3 {
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        /* For Print View */
        #printView {
            display: none;
        }
        
        @media print {
            #printView {
                display: block;
            }
            
            #printView .print-header {
                text-align: center;
                margin-bottom: 20px;
            }
            
            #printView .print-header h1 {
                font-size: 24px;
                margin-bottom: 5px;
            }
            
            #printView .print-header p {
                font-size: 14px;
                color: #6c757d;
            }
            
            #printView table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            
            #printView th, #printView td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            
            #printView th {
                background-color: #f8f9fa;
                font-weight: bold;
            }
            
            .sidebar-container, 
            .wrapper,
            .main-content > .d-flex:first-child, 
            .filter-section, 
            .card-header .dropdown,
            .btn, 
            .card-footer,
            .inventory-icon,
            .progress,
            .api-status {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">  
            <!-- API Connection Status -->
            <div class="api-status">
                <?php if ($connection_test['success']): ?>
                    <div class="alert alert-success">
                        <h6><i class="bi bi-check-circle"></i> <strong>Connected to Inventory System</strong>
                            <span class="live-indicator">
                                <span class="live-dot"></span>
                                LIVE DATA
                            </span>
                        </h6>
                        <div class="connection-info">
                            📊 Database: <?php echo $api_info['database_name'] ?? 'roti_seri_bakery_inventory'; ?> | 
                            📦 Items: <?php echo $connection_test['total_items']; ?> | 
                            🔄 Last updated: <?php echo date('H:i:s'); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <h6><i class="bi bi-exclamation-triangle"></i> <strong>Cannot Connect to Inventory System</strong></h6>
                        <p><strong>Error:</strong> <?php echo htmlspecialchars($connection_test['error']); ?></p>
                        <p><strong>URL:</strong> <?php echo htmlspecialchars($connection_test['url']); ?></p>
                        <div class="mt-2">
                            <strong>Please check:</strong>
                            <ul class="mb-0">
                                <li>Inventory system is running</li>
                                <li>API URL is correct</li>
                                <li>Network connection is available</li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800 font-weight-bold">
                        Raw Materials Inventory
                        <?php if ($connection_test['success']): ?>
                            <span class="live-indicator">
                                <span class="live-dot"></span>
                                Live from Main Inventory
                            </span>
                        <?php endif; ?>
                    </h1>
                    <p class="text-muted">Real-time data from main inventory database</p>
                </div>
                <div class="text-md-end">
                    <div class="mb-2 text-muted">
                        <i class="bi bi-calendar3"></i> <?php echo date('l, d F Y'); ?>
                        <button class="btn btn-sm btn-outline-primary ms-2" onclick="refreshData()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Filter and Search Section -->
            <div class="filter-section mb-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <div class="search-input">
                            <i class="bi bi-search"></i>
                            <input type="text" name="search" class="form-control" placeholder="Search by name or ID..."
                                value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="stock" class="form-select">
                            <option value="">All Stock Levels</option>
                            <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                            <option value="sufficient" <?php echo $stock_filter === 'sufficient' ? 'selected' : ''; ?>>Sufficient</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="sort" class="form-select">
                            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Sort by Name</option>
                            <option value="id" <?php echo $sort === 'id' ? 'selected' : ''; ?>>Sort by ID</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="bi bi-funnel me-1"></i> Filter
                            </button>
                            <a href="sm_materials.php" class="btn btn-outline-secondary flex-fill">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Materials Summary Cards -->
            <div class="summary-cards">
                <div class="row mb-4">
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col-auto me-3">
                                        <div class="inventory-icon">
                                            <i class="bi bi-list-task fs-3"></i>
                                        </div>
                                    </div>
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Products
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_ingredients; ?></div>
                                        <div class="small text-muted">From inventory system</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col-auto me-3">
                                        <div class="inventory-icon">
                                            <i class="bi bi-exclamation-triangle fs-3"></i>
                                        </div>
                                    </div>
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Low Stock Items
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $low_stock_count; ?></div>
                                        <div class="small text-muted">Need restocking</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col-auto me-3">
                                        <div class="inventory-icon">
                                            <i class="bi bi-x-circle fs-3"></i>
                                        </div>
                                    </div>
                                    <div class="col me-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Out of Stock
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $out_of_stock_count; ?></div>
                                        <div class="small text-muted">Immediate attention</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Materials Table -->
            <div class="materials-card">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 font-weight-bold text-primary">
                        Product Inventory
                        <?php if ($connection_test['success']): ?>
                            <span class="live-indicator">
                                <span class="live-dot"></span>
                                Live Data
                            </span>
                        <?php endif; ?>
                    </h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear me-1"></i> Actions
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" onclick="refreshData()">
                                <i class="bi bi-arrow-clockwise me-2"></i> Refresh Data
                            </a></li>
                            <li><a class="dropdown-item" href="#" id="printMaterialsBtn">
                                <i class="bi bi-printer me-2"></i> Print Report
                            </a></li>
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#exportModal">
                                <i class="bi bi-download me-2"></i> Export Data
                            </a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($small_inventory_data) && !$connection_test['success']): ?>
                        <div class="alert alert-warning m-3 text-center">
                            <h5><i class="bi bi-exclamation-triangle"></i> Cannot Load Inventory Data</h5>
                            <p>Unable to fetch real-time inventory data from main system.</p>
                            <button class="btn btn-primary" onclick="refreshData()">
                                <i class="bi bi-arrow-clockwise"></i> Try Again
                            </button>
                        </div>
                    <?php elseif (empty($small_inventory_data)): ?>
                        <div class="alert alert-info m-3 text-center">
                            <p>No products found matching your search criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table materials-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Product ID</th>
                                        <th>Product Name</th>
                                        <th>Quantity</th>
                                        <th>Status</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($small_inventory_data as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['Inventory_ID']); ?></td>
                                            <td><?php echo htmlspecialchars($item['Ingredient_Name']); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2">
                                                        <div class="progress-bar <?php
                                                        echo $item['stock_status'] === 'out_of_stock' ? 'bg-danger' : 
                                                             ($item['stock_status'] === 'low_stock' ? 'bg-warning' : 'bg-success');
                                                        ?>" style="width: <?php
                                                        $threshold = $item['reorder_threshold'] ?? 50;
                                                        echo min(($item['Ingredient_kg'] / $threshold) * 100, 100);
                                                        ?>%"></div>
                                                    </div>
                                                    <span><?php echo number_format($item['Ingredient_kg']); ?> <?php echo $item['unit_type']; ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($item['stock_status'] === 'out_of_stock'): ?>
                                                    <span class="badge badge-out-of-stock">Out of Stock</span>
                                                <?php elseif ($item['stock_status'] === 'low_stock'): ?>
                                                    <span class="badge badge-low-stock">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="badge badge-sufficient">Sufficient</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($item['category']); ?></small>
                                            </td>
                                            <td>
                                                RM <?php echo number_format($item['unit_price'], 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($small_inventory_data)): ?>
                    <div class="card-footer bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">
                                Showing <?php echo count($small_inventory_data); ?> products from inventory system
                                <span class="live-indicator">
                                    <span class="live-dot"></span>
                                    Updated: <?php echo date('H:i:s'); ?>
                                </span>
                            </div>
                            <?php if ($search || $stock_filter): ?>
                                <a href="sm_materials.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Clear Filters
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Print View (Hidden from normal view, only shown when printing) -->
    <div id="printView">
        <div class="print-header">
            <h1>RotiSeri Bakery - Raw Materials Report</h1>
            <p>Generated on: <?php echo date('F j, Y'); ?> | Data Source: Live Inventory System</p>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Product Name</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th>Category</th>
                    <th>Price (RM)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($small_inventory_data as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['Inventory_ID']); ?></td>
                        <td><?php echo htmlspecialchars($item['Ingredient_Name']); ?></td>
                        <td><?php echo number_format($item['Ingredient_kg']); ?> <?php echo $item['unit_type']; ?></td>
                        <td>
                            <?php 
                            echo $item['stock_status'] === 'out_of_stock' ? 'Out of Stock' :
                                 ($item['stock_status'] === 'low_stock' ? 'Low Stock' : 'Sufficient');
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                        <td><?php echo number_format($item['unit_price'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Materials Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Select the format to export materials data:</p>
                    <div class="list-group">
                        <a href="export_materials.php?format=excel&search=<?php echo urlencode($search); ?>&stock_filter=<?php echo urlencode($stock_filter); ?>&sort=<?php echo urlencode($sort); ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Excel Spreadsheet</h6>
                                    <p class="mb-1 small text-muted">Export to Microsoft Excel format</p>
                                </div>
                                <span class="badge bg-primary rounded-pill">.xlsx</span>
                            </div>
                        </a>
                        <a href="export_materials.php?format=csv&search=<?php echo urlencode($search); ?>&stock_filter=<?php echo urlencode($stock_filter); ?>&sort=<?php echo urlencode($sort); ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">CSV File</h6>
                                    <p class="mb-1 small text-muted">Export as comma-separated values</p>
                                </div>
                                <span class="badge bg-primary rounded-pill">.csv</span>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    function refreshData() {
        window.location.reload();
    }

    // Auto-refresh every 2 minutes for live data
    setInterval(function() {
        console.log('Auto-refreshing inventory data...');
        const indicators = document.querySelectorAll('.live-indicator span:last-child');
        indicators.forEach(indicator => {
            if (indicator.textContent.includes('Updated:')) {
                indicator.textContent = 'Updated: ' + new Date().toLocaleTimeString();
            }
        });
    }, 120000); // 2 minutes

    document.addEventListener('DOMContentLoaded', function () {
        // Print functionality
        document.getElementById('printMaterialsBtn').addEventListener('click', function (e) {
            e.preventDefault();
            
            // Set a short timeout to ensure the print view is ready
            setTimeout(function() {
                window.print();
            }, 100);
        });

        // Tooltip initialization
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Status indicator click handlers for refresh
        document.querySelectorAll('.live-indicator').forEach(function(indicator) {
            indicator.style.cursor = 'pointer';
            indicator.addEventListener('click', function() {
                refreshData();
            });
        });
    });
    </script>
</body>
</html>