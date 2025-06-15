<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(1); // 1 for sales manager (formerly supervisor)
require_once '../config/db_connection.php';
require_once '../includes/functions/inventory_functions.php';

// Fetch inventory data
$inventory_data = fetch_inventory_data($conn);

// Filter by search keyword if provided
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : '';

// Get all available categories
$categories = [];
foreach ($inventory_data as $item) {
    if (!empty($item['category']) && !in_array($item['category'], $categories)) {
        $categories[] = $item['category'];
    }
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

// Calculate inventory statistics
$total_products = count($inventory_data);
$low_stock_count = 0;
$out_of_stock_count = 0;
$total_inventory_value = 0;

foreach ($inventory_data as $item) {
    if ($item['stock_level'] <= 0) {
        $out_of_stock_count++;
    } else if ($item['stock_level'] <= 10) {
        $low_stock_count++;
    }
    $total_inventory_value += $item['unit_price'] * $item['stock_level'];
}

// Set page title
$page_title = "Inventory Management - Sales Manager";
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
            margin-left: var(--sidebar-width);
            transition: all 0.3s;
        }

        .inventory-card {
            border-radius: 12px;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
            background-color: white;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .inventory-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
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

        .border-left-success {
            border-left: 4px solid var(--success);
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

        .inventory-table th {
            font-weight: 600;
            color: #495057;
            border-top: none;
            padding: 1rem;
        }

        .inventory-table td {
            padding: 1rem;
            vertical-align: middle;
        }

        .inventory-table tbody tr {
            transition: background-color 0.2s ease;
        }

        .inventory-table tbody tr:hover {
            background-color: rgba(5, 97, 252, 0.03);
        }

        .progress {
            height: 6px;
            border-radius: 3px;
        }

        .badge-low-stock {
            background-color: var(--warning);
            color: #212529;
        }
        
        .badge-sufficient {
            background-color: var(--success);
            color: white;
        }
        
        .badge-out-of-stock {
            background-color: var(--danger);
            color: white;
        }

        /* For Print View */
        #printView {
            display: none;
        }        @media print {
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
            .progress {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800 fw-bold">Inventory Management</h1>
                    <p class="text-muted">Manage and track your product inventory</p>
                </div>
                <div class="text-md-end">
                <div class="mb-2 text-muted">
                    <i class="bi bi-calendar3"></i> <?php echo date('l, d F Y'); ?>
                </div>
            </div>            </div>
            
            <!-- Error Messages -->
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php
                        switch($_GET['error']) {
                            case 'invalid_format':
                                echo '<i class="bi bi-exclamation-triangle me-2"></i>Invalid export format selected.';
                                break;
                            case 'no_data':
                                echo '<i class="bi bi-info-circle me-2"></i>No data available to export.';
                                break;
                            case 'export_failed':
                                echo '<i class="bi bi-x-circle me-2"></i>Export failed. Please try again.';
                                break;
                            default:
                                echo '<i class="bi bi-exclamation-triangle me-2"></i>An error occurred.';
                        }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Filter and Search -->
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
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category ?: 'Uncategorized'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="stock" class="form-select">
                            <option value="">All Stock Levels</option>
                            <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                            <option value="sufficient" <?php echo $stock_filter === 'sufficient' ? 'selected' : ''; ?>>Sufficient</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="bi bi-funnel me-1"></i> Filter
                            </button>
                            <a href="sm_inventory.php" class="btn btn-outline-secondary flex-fill">
                                <i class="bi bi-x-circle me-1"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Inventory Summary Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto me-3">
                                    <div class="inventory-icon">
                                        <i class="bi bi-box-seam fs-3"></i>
                                    </div>
                                </div>
                                <div class="col me-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Products
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_products; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
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
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
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
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto me-3">
                                    <div class="inventory-icon">
                                        <i class="bi bi-cash-coin fs-3"></i>
                                    </div>
                                </div>
                                <div class="col me-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Total Value
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">RM<?php echo number_format($total_inventory_value, 2); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Inventory Table -->
            <div class="inventory-card">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-primary">Inventory Items</h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear me-1"></i> Actions
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#exportModal">
                                <i class="bi bi-download me-2"></i> Export Data
                            </a></li>
                            <li><a class="dropdown-item" href="sm_materials.php">
                                <i class="bi bi-list-task me-2"></i> View Raw Materials
                            </a></li>
                            <li><hr class="dropdown-divider"></li>                            <li><a class="dropdown-item" href="#" id="printInventoryBtn">
                                <i class="bi bi-printer me-2"></i> Print Inventory
                            </a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table inventory-table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Product Name</th>
                                    <th scope="col">Category</th>
                                    <th scope="col">Unit Price</th>
                                    <th scope="col">Stock Level</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($inventory_data)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">No inventory items found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($inventory_data as $item): ?>
                                        <tr>
                                            <td><?php echo $item['id']; ?></td>
                                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['category'] ?: 'Uncategorized'); ?></td>
                                            <td>RM<?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($item['stock_level'] <= 0): ?>
                                                        <div class="progress flex-grow-1 me-2">
                                                            <div class="progress-bar bg-danger" style="width: 0%"></div>
                                                        </div>
                                                    <?php elseif ($item['stock_level'] <= 10): ?>
                                                        <div class="progress flex-grow-1 me-2">
                                                            <div class="progress-bar bg-warning" style="width: <?php echo min(($item['stock_level'] / 10) * 100, 100); ?>%"></div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="progress flex-grow-1 me-2">
                                                            <div class="progress-bar bg-success" style="width: 100%"></div>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span><?php echo $item['stock_level']; ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($item['stock_level'] <= 0): ?>
                                                    <span class="badge badge-out-of-stock">Out of Stock</span>
                                                <?php elseif ($item['stock_level'] <= 10): ?>
                                                    <span class="badge badge-low-stock">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="badge badge-sufficient">Sufficient</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if (count($inventory_data) > 0): ?>
                    <div class="card-footer bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">
                                Showing <?php echo count($inventory_data); ?> items
                            </div>
                            <?php if (isset($_GET['search']) || isset($_GET['category']) || isset($_GET['stock'])): ?>
                            <a href="sm_inventory.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Clear Filters
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>                </div>
            </div>
            
            <!-- Export Modal -->
            <div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Export Inventory Data</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Select the format to export inventory data:</p>                            <div class="list-group">
                                <a href="#" class="list-group-item list-group-item-action export-btn" data-format="excel">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Excel Spreadsheet</h6>
                                            <p class="mb-1 small text-muted">Export to Microsoft Excel format</p>
                                        </div>
                                        <span class="badge bg-primary rounded-pill">.xls</span>
                                    </div>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action export-btn" data-format="csv">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">CSV File</h6>
                                            <p class="mb-1 small text-muted">Export as comma-separated values</p>
                                        </div>
                                        <span class="badge bg-primary rounded-pill">.csv</span>
                                    </div>
                                </a>
                            </div>
                        </div>                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <div class="d-none" id="exportLoading">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                <span>Preparing export...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>        </div>
    </div>

    <!-- Print View (Hidden from normal view, only shown when printing) -->
    <div id="printView">
        <div class="print-header">
            <h1>RotiSeri Bakery - Inventory Report</h1>
            <p>Generated on: <?php echo date('F j, Y'); ?></p>
            <p>Total Items: <?php echo count($inventory_data); ?></p>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Unit Price</th>
                    <th>Stock Level</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($inventory_data)): ?>
                    <?php foreach ($inventory_data as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['id']); ?></td>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category'] ?: 'Uncategorized'); ?></td>
                            <td>RM<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td><?php echo $item['stock_level']; ?></td>
                            <td>
                                <?php 
                                if ($item['stock_level'] <= 0) {
                                    echo 'Out of Stock';
                                } elseif ($item['stock_level'] <= 10) {
                                    echo 'Low Stock';
                                } else {
                                    echo 'Sufficient';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px;">No inventory data available</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Print functionality
        document.getElementById('printInventoryBtn').addEventListener('click', function (e) {
            e.preventDefault();
            
            // Set a short timeout to ensure the print view is ready
            setTimeout(function() {
                window.print();
            }, 100);
        });
        
        const exportButtons = document.querySelectorAll('.export-btn');
        const exportLoading = document.getElementById('exportLoading');
        
        exportButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Show loading indicator
                exportLoading.classList.remove('d-none');
                
                const format = this.getAttribute('data-format');
                const urlParams = new URLSearchParams(window.location.search);
                
                // Build export URL with current filters
                let exportUrl = 'export_inventory.php?format=' + format;
                
                // Add current filters to export URL
                if (urlParams.get('search')) {
                    exportUrl += '&search=' + encodeURIComponent(urlParams.get('search'));
                }
                if (urlParams.get('category')) {
                    exportUrl += '&category=' + encodeURIComponent(urlParams.get('category'));
                }
                if (urlParams.get('stock')) {
                    exportUrl += '&stock=' + encodeURIComponent(urlParams.get('stock'));
                }
                  // Handle export download
                window.location.href = exportUrl;
                
                // Hide loading and close modal after a short delay
                setTimeout(() => {
                    exportLoading.classList.add('d-none');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
                    modal.hide();
                }, 2000);
            });
        });
    });

    // Tooltip initialization
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    </script>

    </style>