<?php
// filepath: c:\xampp\htdocs\BSMSv2\rotiseri-bakery\sales_manager\sm_sales_cleaned.php
require_once '../includes/security.php';
secure_session_start();
check_user_type(1); // 1 for sales manager (formerly supervisor)
require_once '../config/db_connection.php';
require_once '../includes/functions/sales_functions.php';

// Initialize filter variables
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : (isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-1 month')));
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : (isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'));

// Check if "Today" is specifically selected in the date range picker
$is_today_selected = (isset($_POST['is_today']) && $_POST['is_today'] == 1) || (isset($_GET['is_today']) && $_GET['is_today'] == 1);

// For debugging
$original_start_date = $start_date;
$original_end_date = $end_date;

// Adjust end_date for proper date range querying when selecting a single day
if ($start_date === $end_date) {
    // For today's date, set time to 23:59:59 to include all records for the day
    $end_date = $start_date . ' 23:59:59';
    // Set start_date time to 00:00:00 explicitly
    $start_date = $start_date . ' 00:00:00';
}
// Special handling for "Today" selection
elseif ($is_today_selected) {
    $today = date('Y-m-d');
    $start_date = $today . ' 00:00:00';
    $end_date = $today . ' 23:59:59';
}

$sales_type = isset($_POST['sales_type']) ? $_POST['sales_type'] : 'all';

// Fetch sales data based on filters
list($daily_sales, $weekly_sales, $monthly_sales) = fetch_sales_data($conn, $start_date, $end_date, $sales_type);

// Calculate total sales
$total_daily_sales = array_sum(array_column($daily_sales, 'total_revenue'));
$total_weekly_sales = array_sum(array_column($weekly_sales, 'total_revenue'));
$total_monthly_sales = array_sum(array_column($monthly_sales, 'total_revenue'));

// Get average daily sales
$avg_daily_sales = count($daily_sales) > 0 ? $total_daily_sales / count($daily_sales) : 0;

// Fetch online vs in-store sales data
$online_sales_query = $conn->prepare("
    SELECT SUM(total_amount) as total
    FROM orders
    WHERE order_date BETWEEN ? AND ?
    AND order_type = 'online'
    AND status = 'completed'
");
$online_sales_query->bind_param("ss", $start_date, $end_date);
$online_sales_query->execute();
$online_sales = $online_sales_query->get_result()->fetch_assoc()['total'] ?? 0;

$instore_sales_query = $conn->prepare("
    SELECT SUM(total_amount) as total
    FROM orders
    WHERE order_date BETWEEN ? AND ?
    AND order_type = 'in-store'
    AND status = 'completed'
");
$instore_sales_query->bind_param("ss", $start_date, $end_date);
$instore_sales_query->execute();
$instore_sales = $instore_sales_query->get_result()->fetch_assoc()['total'] ?? 0;

// Set page title
$page_title = "Sales Overview - Sales Manager";
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">Sales Overview</h1>
                <p class="text-muted">Track and analyze your sales performance</p>
            </div>
            <div class="text-md-end">
                <div class="mb-2 text-muted">
                    <i class="bi bi-calendar3"></i> <?php echo date('l, d F Y'); ?>
                </div>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if(isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show mb-4" role="alert">
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php 
            // Clear the message after displaying
            unset($_SESSION['message']); 
            unset($_SESSION['message_type']);
        endif; 
        ?>
        
        <!-- Simplified Filter Form with Stretched Date Picker -->
        <div class="filter-section mb-4">
            <form method="POST" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Date Range</label>
                        <div class="input-group date-picker-container">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-calendar-range"></i>
                            </span>
                            <input type="text" id="daterange" name="daterange" class="form-control" 
                                   value="<?php echo date('m/d/Y', strtotime($original_start_date)) . ' - ' . date('m/d/Y', strtotime($original_end_date)); ?>" />
                            <input type="hidden" id="start_date" name="start_date" value="<?php echo htmlspecialchars($original_start_date); ?>">
                            <input type="hidden" id="end_date" name="end_date" value="<?php echo htmlspecialchars($original_end_date); ?>">
                            <?php if ($is_today_selected): ?>
                            <input type="hidden" id="is_today" name="is_today" value="1">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Sales Type</label>
                        <select class="form-select" name="sales_type" id="sales_type">
                            <option value="all" <?php echo $sales_type === 'all' ? 'selected' : ''; ?>>All Sales</option>
                            <option value="online" <?php echo $sales_type === 'online' ? 'selected' : ''; ?>>Online Sales</option>
                            <option value="in-store" <?php echo $sales_type === 'in-store' ? 'selected' : ''; ?>>In-Store Sales</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary filter-btn w-100">
                            <i class="bi bi-funnel me-2"></i> Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Sales Summary Cards with same style as other pages -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="icon bg-primary-light text-primary rounded-circle p-3">
                                    <i class="bi bi-cash-coin"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    Total Sales
                                </div>
                                <div class="h5 mb-0 font-weight-bold">RM<?php echo number_format($total_daily_sales, 2); ?></div>
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
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    Average Daily Sales
                                </div>
                                <div class="h5 mb-0 font-weight-bold">RM<?php echo number_format($avg_daily_sales, 2); ?></div>
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
                                    <i class="bi bi-bag-check"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    In-Store Sales
                                </div>
                                <div class="h5 mb-0 font-weight-bold">RM<?php echo number_format($instore_sales, 2); ?></div>
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
                                    <i class="bi bi-laptop"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    Online Sales
                                </div>
                                <div class="h5 mb-0 font-weight-bold">RM<?php echo number_format($online_sales, 2); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sales Charts -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fw-bold text-primary">Sales Overview</h6>
                        <div>
                            <a href="#" class="btn btn-sm btn-outline-primary" onclick="printSalesReport()">
                                <i class="bi bi-printer"></i> Print
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div style="height: 350px;">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 fw-bold text-primary">Sales Distribution</h6>
                    </div>
                    <div class="card-body">
                        <div style="height: 350px;">
                            <canvas id="salesDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sales Table -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-primary">Sales Details</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th class="text-end">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($daily_sales)): ?>
                            <tr>
                                <td colspan="2" class="text-center py-4">No data available for the selected period</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($daily_sales as $sale): ?>
                                    <tr>
                                        <td><?php echo date('F j, Y', strtotime($sale['sale_date'])); ?></td>
                                        <td class="text-end fw-medium">RM <?php echo number_format($sale['total_revenue'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($daily_sales)): ?>
                        <tfoot>
                            <tr class="table-active">
                                <th>Total</th>
                                <th class="text-end">RM <?php echo number_format($total_daily_sales, 2); ?></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Export Sales Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Select the format to export sales data:</p>
                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Excel Spreadsheet</h6>
                                <p class="mb-1 small text-muted">Export to Microsoft Excel format</p>
                            </div>
                            <span class="badge bg-primary rounded-pill">.xlsx</span>
                        </div>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">CSV File</h6>
                                <p class="mb-1 small text-muted">Export as comma-separated values</p>
                            </div>
                            <span class="badge bg-primary rounded-pill">.csv</span>
                        </div>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">PDF Document</h6>
                                <p class="mb-1 small text-muted">Export as printable PDF document</p>
                            </div>
                            <span class="badge bg-primary rounded-pill">.pdf</span>
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
<!-- Moment.js -->
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/min/moment.min.js"></script>
<!-- DateRangePicker JS -->
<script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced date range picker configuration
    $('#daterange').daterangepicker({
        startDate: moment('<?php echo $original_start_date; ?>'),
        endDate: moment('<?php echo $original_end_date; ?>'),
        opens: 'left',
        locale: {
            format: 'MM/DD/YYYY'
        },
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
        
        // If "Today" is selected, add a hidden field to indicate this
        if (label === 'Today') {
            if (!$('#is_today').length) {
                $('#filterForm').append('<input type="hidden" id="is_today" name="is_today" value="1">');
            }
        } else {
            $('#is_today').remove();
        }
    });

    // Auto-submit form when daterange changes
    $('#daterange').on('apply.daterangepicker', function(ev, picker) {
        $('#start_date').val(picker.startDate.format('YYYY-MM-DD'));
        $('#end_date').val(picker.endDate.format('YYYY-MM-DD'));
        
        // Check if Today is selected
        if (picker.chosenLabel === 'Today') {
            if (!$('#is_today').length) {
                $('#filterForm').append('<input type="hidden" id="is_today" name="is_today" value="1">');
            }
        } else {
            $('#is_today').remove();
        }
        
        $('#filterForm').submit();
    });

    // Auto-submit form when sales type changes
    $('#sales_type').on('change', function() {
        $('#filterForm').submit();
    });

    // Advanced chart configurations and animations
    Chart.defaults.animation = {
        duration: 2000,
        easing: 'easeOutQuart'
    };
    Chart.defaults.hover = Chart.defaults.hover || {};
    Chart.defaults.hover.animationDuration = 0;
    Chart.defaults.responsiveAnimationDuration = 0;

    // Initialize sales chart if data is available
    <?php if (!empty($daily_sales)): ?>
    const salesChartCtx = document.getElementById('salesChart').getContext('2d');
    
    // Calculate gradient fill
    const gradient = salesChartCtx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(5, 97, 252, 0.3)');
    gradient.addColorStop(1, 'rgba(5, 97, 252, 0.0)');
    
    // Format dates for better display
    const formattedLabels = <?php echo json_encode(array_map(function($item) {
        return date('M d', strtotime($item['sale_date']));
    }, $daily_sales)); ?>;
    
    new Chart(salesChartCtx, {
        type: 'line',
        data: {
            labels: formattedLabels,
            datasets: [{
                label: 'Sales',
                data: <?php echo json_encode(array_column($daily_sales, 'total_revenue')); ?>,
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
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(5, 97, 252, 0.9)',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 14
                    },
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return 'RM ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 12
                        },
                        color: '#6c757d',
                        callback: function(value) {
                            return 'RM ' + value;
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 12
                        },
                        color: '#6c757d'
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // Sales Distribution Chart (Online vs In-store)
    const totalSales = <?php echo ($online_sales + $instore_sales) ?: 1; ?>;
    const onlinePercentage = <?php echo round(($online_sales / (($online_sales + $instore_sales) ?: 1)) * 100, 1); ?>;
    const instorePercentage = <?php echo round(($instore_sales / (($online_sales + $instore_sales) ?: 1)) * 100, 1); ?>;

    const distributionCtx = document.getElementById('salesDistributionChart').getContext('2d');
    new Chart(distributionCtx, {
        type: 'doughnut',
        data: {
            labels: ['In-Store Sales', 'Online Sales'],
            datasets: [{
                data: [instorePercentage, onlinePercentage],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 159, 64, 0.7)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        boxWidth: 12,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleFont: {
                        size: 14
                    },
                    bodyFont: {
                        size: 14
                    },
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.parsed + '%';
                        }
                    }
                }
            }
        }
    });

    // Print functionality
    window.printSalesReport = function() {
        window.print();
    };
});
</script>

<style>
/* Custom styles for sales overview */
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

.filter-section {
    border-radius: 12px;
    background-color: white;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.filter-btn {
    border-radius: 8px;
    font-weight: 500;
    padding: 0.65rem 0.85rem;
}

/* Stretched date picker styling */
.date-picker-container {
    width: 100%;
}

#daterange {
    height: 38px;
    font-size: 0.9rem;
}

/* DateRangePicker custom styling */
.daterangepicker {
    border-radius: 8px;
    font-family: inherit;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.daterangepicker .calendar-table {
    border-radius: 6px;
}

.daterangepicker td.active, 
.daterangepicker td.active:hover {
    background-color: var(--primary);
}

.daterangepicker .ranges li.active {
    background-color: var(--primary);
}

.daterangepicker .ranges {
    min-width: 170px;
}

.daterangepicker .drp-buttons .btn {
    margin-left: 8px;
    padding: 5px 12px;
    font-weight: 500;
}

.table th {
    font-weight: 600;
    border-top: none;
}

.table-hover tbody tr:hover {
    background-color: rgba(5, 97, 252, 0.03);
}

.table-light {
    background-color: var(--light);
}

/* Print styles */
@media print {
    .sidebar-container, 
    .main-content > .d-flex:first-child, 
    .filter-section,
    .dropdown,
    .btn, 
    .card-footer {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0;
        width: 100%;
        padding: 0;
    }
    
    .card {
        border: none;
        box-shadow: none;
    }
    
    /* Add header for printing */
    body::before {
        content: "RotiSeri Bakery - Sales Report";
        display: block;
        text-align: center;
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 20px;
    }
    
    /* Add date for printing */
    .chart-container::before {
        content: "Period: <?php echo date('F j, Y', strtotime($start_date)); ?> - <?php echo date('F j, Y', strtotime($end_date)); ?>";
        display: block;
        text-align: right;
        font-size: 12px;
        margin-bottom: 10px;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
