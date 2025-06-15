<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(1); // 1 for Sales Manager (formerly Supervisor)
require_once '../config/db_connection.php';
require_once '../includes/functions/sales_functions.php';
require_once '../includes/functions/inventory_functions.php';

// Initialize filter variables
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
$sales_type = isset($_POST['sales_type']) ? $_POST['sales_type'] : 'all';

// Fetch sales data based on filters
list($daily_sales, $weekly_sales, $monthly_sales) = fetch_sales_data($conn, $start_date, $end_date, $sales_type);

// Fetch today's sales data
$today_sales_data = fetch_today_sales_data($conn);

// Fetch hot selling items data
$hot_items = fetch_hot_items($conn, $start_date, $end_date);

// Calculate inventory statistics
$inventory_data = fetch_inventory_data($conn);
$total_products = count($inventory_data);
$low_stock_count = 0;
$out_of_stock_count = 0;

foreach ($inventory_data as $item) {
    if ($item['stock_level'] <= 0) {
        $out_of_stock_count++;
    } else if ($item['stock_level'] <= 10) {
        $low_stock_count++;
    }
}

// Set page title
$page_title = "Dashboard - Sales Manager";

// Process data for charts
$daily_labels = json_encode(array_column($daily_sales, 'sale_date'));
$daily_data = json_encode(array_column($daily_sales, 'total_revenue'));

// Calculate sales growth
$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterday_sales_data = fetch_today_sales_data($conn, $yesterday);
$yesterday_total = $yesterday_sales_data['total_sales'] ?? 0;
$sales_diff_percentage = $yesterday_total > 0
    ? (($today_sales_data['total_sales'] - $yesterday_total) / $yesterday_total) * 100
    : 0;

// Calculate payment method percentages
$total_sales = $today_sales_data['total_sales'] ?: 1; // Avoid division by zero
$cash_percentage = ($today_sales_data['cash_sales'] / $total_sales) * 100;
$card_percentage = ($today_sales_data['card_sales'] / $total_sales) * 100;
$banking_percentage = ($today_sales_data['banking_sales'] / $total_sales) * 100;
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Dashboard Header with Welcome Message -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">Dashboard</h1>
                <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['user']['fullname']); ?>!</p>
            </div>
            <div class="text-md-end">
                <div class="mb-2 text-muted">
                    <i class="bi bi-calendar3"></i> <?php echo date('l, d F Y'); ?>
                </div>
            </div>
        </div>

        <!-- Sales Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="icon bg-primary-light text-primary rounded-circle p-3">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    Total Sales
                                </div>
                                <div class="h5 mb-0 font-weight-bold">RM<?php echo number_format($today_sales_data['total_sales'], 2); ?></div>
                                <span class="small <?php echo $sales_diff_percentage >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <i class="bi bi-<?php echo $sales_diff_percentage >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                    <?php echo abs(number_format($sales_diff_percentage, 1)); ?>% from yesterday
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="icon bg-success-light text-success rounded-circle p-3">
                                    <i class="bi bi-cash-coin"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    Cash Sales
                                </div>
                                <div class="h5 mb-0 font-weight-bold">RM<?php echo number_format($today_sales_data['cash_sales'], 2); ?></div>
                                <span class="small text-muted">
                                    <?php echo number_format($cash_percentage, 1); ?>% of total sales
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="icon bg-info-light text-info rounded-circle p-3">
                                    <i class="bi bi-credit-card"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    Card Sales
                                </div>
                                <div class="h5 mb-0 font-weight-bold">RM<?php echo number_format($today_sales_data['card_sales'], 2); ?></div>
                                <span class="small text-muted">
                                    <?php echo number_format($card_percentage, 1); ?>% of total sales
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="icon bg-warning-light text-warning rounded-circle p-3">
                                    <i class="bi bi-bank"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    Banking Sales
                                </div>
                                <div class="h5 mb-0 font-weight-bold">RM<?php echo number_format($today_sales_data['banking_sales'], 2); ?></div>
                                <span class="small text-muted">
                                    <?php echo number_format($banking_percentage, 1); ?>% of total sales
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Status Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="icon bg-primary-light text-primary rounded-circle p-3">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    Total Products
                                </div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $total_products; ?></div>
                                <a href="sm_inventory.php" class="small text-primary mt-1 d-inline-block">
                                    View Inventory <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="icon bg-warning-light text-warning rounded-circle p-3">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    Low Stock Items
                                </div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $low_stock_count; ?></div>
                                <a href="sm_inventory.php?stock=low" class="small text-warning mt-1 d-inline-block">
                                    View Low Stock <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="icon bg-danger-light text-danger rounded-circle p-3">
                                    <i class="bi bi-x-circle"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    Out of Stock
                                </div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $out_of_stock_count; ?></div>
                                <a href="sm_inventory.php?stock=out" class="small text-danger mt-1 d-inline-block">
                                    View Out of Stock <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Chart and Hot Products Section -->
        <div class="row">
            <div class="col-xl-8 col-lg-7">
                <div class="card mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Sales Overview</h5>
                        <div class="d-flex align-items-center">
                            <div class="input-group date-range-container">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-calendar-range text-primary"></i>
                                </span>
                                <input type="text" id="daterange" name="daterange" class="form-control border-start-0" placeholder="Select Date Range" />
                                <input type="hidden" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                                <input type="hidden" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                                <button type="button" id="applyDateFilter" class="btn btn-primary">
                                    <i class="bi bi-funnel"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div style="height: 400px;">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-lg-5">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Hot Selling Items</h5>
                        <a href="sm_analytics.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-graph-up-arrow"></i> Details
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Sold</th>
                                        <th class="text-end">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $hot_items_displayed = array_slice($hot_items, 0, 5); // Show top 5
                                    foreach ($hot_items_displayed as $index => $item): 
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="fw-medium"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                        </td>
                                        <td class="text-end"><?php echo number_format($item['total_quantity']); ?></td>
                                        <td class="text-end">RM<?php echo number_format($item['total_revenue'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($hot_items_displayed)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-3">No data available</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styles for improved dashboard layout */
.icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.border-left-primary {
    border-left: 4px solid #0561FC !important;
}

.border-left-success {
    border-left: 4px solid #28a745 !important;
}

.border-left-info {
    border-left: 4px solid #17a2b8 !important;
}

.border-left-warning {
    border-left: 4px solid #ffc107 !important;
}

.border-left-danger {
    border-left: 4px solid #dc3545 !important;
}

.bg-primary-light {
    background-color: rgba(5, 97, 252, 0.1);
}

.bg-success-light {
    background-color: rgba(40, 167, 69, 0.1);
}

.bg-info-light {
    background-color: rgba(23, 162, 184, 0.1);
}

.bg-warning-light {
    background-color: rgba(255, 193, 7, 0.1);
}

.bg-danger-light {
    background-color: rgba(220, 53, 69, 0.1);
}

.card {
    border: none;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.25rem 1.85rem 0 rgba(58, 59, 69, 0.2);
}

.card .h5 {
    font-size: 1.25rem;
    margin-bottom: 0.25rem;
}

.card-header {
    background-color: white;
    border-bottom: 1px solid #eaecf0;
}

.text-xs {
    font-size: 0.7rem;
}

.py-2 {
    padding-top: 0.75rem !important;
    padding-bottom: 0.75rem !important;
}

.table th {
    font-weight: 600;
    border-top: none;
}

.table-hover tbody tr:hover {
    background-color: rgba(5, 97, 252, 0.03);
}

/* Improved date range picker styling */
.date-range-container {
    width: 350px;
}

#daterange {
    font-size: 0.9rem;
    padding: 0.5rem 0.75rem;
}

.daterangepicker {
    font-family: inherit;
    border-radius: 8px;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.daterangepicker .ranges li.active {
    background-color: #0561FC;
}

.daterangepicker td.active, 
.daterangepicker td.active:hover {
    background-color: #0561FC;
}

.daterangepicker td.in-range {
    background-color: rgba(5, 97, 252, 0.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize date range picker with improved styling
    $(function() {
        $('#daterange').daterangepicker({
            startDate: moment('<?php echo $start_date; ?>'),
            endDate: moment('<?php echo $end_date; ?>'),
            opens: 'left',
            buttonClasses: 'btn',
            applyButtonClasses: 'btn-primary',
            cancelButtonClasses: 'btn-light',
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, function(start, end, label) {
            $('#start_date').val(start.format('YYYY-MM-DD'));
            $('#end_date').val(end.format('YYYY-MM-DD'));
        });
    });

    // Handle apply filter button click
    document.getElementById('applyDateFilter').addEventListener('click', function() {
        // Create a form dynamically
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;
        
        // Add start date input
        const startInput = document.createElement('input');
        startInput.type = 'hidden';
        startInput.name = 'start_date';
        startInput.value = document.getElementById('start_date').value;
        form.appendChild(startInput);
        
        // Add end date input
        const endInput = document.createElement('input');
        endInput.type = 'hidden';
        endInput.name = 'end_date';
        endInput.value = document.getElementById('end_date').value;
        form.appendChild(endInput);
        
        // Add sales type input (keeping the current value)
        const salesTypeInput = document.createElement('input');
        salesTypeInput.type = 'hidden';
        salesTypeInput.name = 'sales_type';
        salesTypeInput.value = '<?php echo $sales_type; ?>';
        form.appendChild(salesTypeInput);
        
        // Submit the form
        document.body.appendChild(form);
        form.submit();
    });

    // Create a single sales chart with all data
    const createSalesChart = function() {
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        // Calculate gradient fill
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(5, 97, 252, 0.3)');
        gradient.addColorStop(1, 'rgba(5, 97, 252, 0.0)');
        
        // Format dates for better display
        const formattedLabels = JSON.parse('<?php echo $daily_labels; ?>').map(date => {
            return moment(date).format('MMM DD');
        });
        
        return new Chart(ctx, {
            type: 'line',
            data: {
                labels: formattedLabels,
                datasets: [{
                    label: 'Sales',
                    data: JSON.parse('<?php echo $daily_data; ?>'),
                    backgroundColor: gradient,
                    borderColor: 'rgba(5, 97, 252, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'white',
                    pointBorderColor: 'rgba(5, 97, 252, 1)',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(value) {
                                return 'RM ' + value;
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(5, 97, 252, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        callbacks: {
                            label: function(context) {
                                return 'RM ' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
    };

    // Initialize chart
    createSalesChart();
    
    // Add hover effect to hot selling items table rows
    document.querySelectorAll('.table-hover tr').forEach(row => {
        row.addEventListener('mouseover', function() {
            this.style.backgroundColor = 'rgba(5, 97, 252, 0.03)';
        });
        
        row.addEventListener('mouseout', function() {
            this.style.backgroundColor = '';
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>