<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(1); // 1 for sales manager (formerly supervisor)
require_once '../config/db_connection.php';
require_once '../includes/functions/sales_functions.php';

// Initialize filter variables
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d', strtotime('-1 month'));
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');

// Fetch hot selling items data
$hot_items = fetch_hot_items($conn, $start_date, $end_date);

// Set page title
$page_title = "Analytics - Sales Manager";
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">Sales Analytics</h1>
                <p class="text-muted">Track and analyze your sales performance</p>
            </div>
            <div class="text-md-end">
                <div class="mb-2 text-muted">
                    <i class="bi bi-calendar3"></i> <?php echo date('l, d F Y'); ?>
                </div>
            </div>
        </div>
        
        <!-- Simplified Filter Form with Stretched Date Picker -->
        <div class="filter-section mb-4">
            <form method="POST">
                <div class="row align-items-center">
                    <div class="col-12 mb-3">
                        <label class="form-label mb-2">Date Range</label>
                        <div class="input-group date-picker-container">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-calendar-range"></i>
                            </span>
                            <input type="text" id="daterange" name="daterange" class="form-control" 
                                   value="<?php echo date('m/d/Y', strtotime($start_date)) . ' - ' . date('m/d/Y', strtotime($end_date)); ?>" />
                            <input type="hidden" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            <input type="hidden" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-1"></i> Apply Filter
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="printAnalytics()">
                                <i class="bi bi-printer me-1"></i> Print
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="row">
            <div class="col-md-7">
                <div class="analytics-card mb-4">
                    <div class="card-header py-3 bg-white">
                        <h5 class="card-title mb-0 fw-bold text-primary">Top Selling Products</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="topProductsChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-5">
                <div class="analytics-card mb-4">
                    <div class="card-header py-3 bg-white">
                        <h5 class="card-title mb-0 fw-bold text-primary">Revenue Distribution</h5>
                    </div>
                    <div class="card-body chart-container">
                        <canvas id="revenueDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="analytics-card">
            <div class="card-header py-3 bg-white">
                <h5 class="card-title mb-0 fw-bold text-primary">Hot Selling Items</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table top-products-table">
                        <thead class="table-light">
                            <tr>
                                <th>Rank</th>
                                <th>Product Name</th>
                                <th>Total Quantity Sold</th>
                                <th>Total Revenue</th>
                                <th>Percentage of Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rank = 1;
                            foreach ($hot_items as $item):
                            ?>
                                <tr>
                                    <td>
                                        <div class="product-rank"><?php echo $rank++; ?></div>
                                    </td>
                                    <td class="fw-medium"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td><?php echo number_format($item['total_quantity']); ?> units</td>
                                    <td>RM<?php echo number_format($item['total_revenue'], 2); ?></td>
                                    <td>
                                        <span class="sales-percentage-badge">
                                            <?php echo number_format($item['sales_percentage'], 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($hot_items)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">No data available for the selected period</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize date range picker
    $('#daterange').daterangepicker({
        startDate: moment('<?php echo $start_date; ?>'),
        endDate: moment('<?php echo $end_date; ?>'),
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
    });

    // Enhanced print functionality
    window.printAnalytics = function() {
        window.print();
    };

    // Prepare data for charts
    const productNames = <?php echo json_encode(array_column(array_slice($hot_items, 0, 5), 'product_name')); ?>;
    const quantities = <?php echo json_encode(array_column(array_slice($hot_items, 0, 5), 'total_quantity')); ?>;
    const revenues = <?php echo json_encode(array_column(array_slice($hot_items, 0, 5), 'total_revenue')); ?>;
    const percentages = <?php echo json_encode(array_column(array_slice($hot_items, 0, 5), 'sales_percentage')); ?>;

    // Enhanced chart animations
    Chart.defaults.animation = {
        duration: 2000,
        easing: 'easeOutQuart'
    };
    
    // Top 5 Products Chart
    const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
    const topProductsChart = new Chart(topProductsCtx, {
        type: 'bar',
        data: {
            labels: productNames,
            datasets: [{
                label: 'Quantity Sold',
                data: quantities,
                backgroundColor: 'rgba(5, 97, 252, 0.7)',
                borderColor: 'rgba(5, 97, 252, 1)',
                borderWidth: 1,
                borderRadius: 4,
                barPercentage: 0.6,
                categoryPercentage: 0.7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleFontSize: 12,
                    titleFontColor: '#fff',
                    bodyFontSize: 14,
                    bodyFontColor: '#fff',
                    displayColors: false,
                    callbacks: {
                        label: function(tooltipItem) {
                            return tooltipItem.parsed.y + ' units sold';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        zeroLineColor: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        font: {
                            size: 12
                        },
                        color: '#6c757d'
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
                        color: '#6c757d',
                        callback: function(value) {
                            if (value.length > 15) {
                                return value.substr(0, 15) + '...';
                            }
                            return value;
                        }
                    }
                }
            }
        }
    });
    
    // Revenue Distribution Chart
    const revenueCtx = document.getElementById('revenueDistributionChart').getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
        type: 'pie',
        data: {
            labels: productNames,
            datasets: [{
                data: percentages,
                backgroundColor: [
                    'rgba(5, 97, 252, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)'
                ],
                borderColor: [
                    'rgba(5, 97, 252, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 20,
                        boxWidth: 12,
                        font: {
                            size: 12
                        },
                        color: '#6c757d'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleFont: {
                        size: 12
                    },
                    bodyFont: {
                        size: 14
                    },
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            const label = context.label;
                            const value = context.parsed;
                            return `${label}: ${value}% of sales`;
                        }
                    }
                }
            }
        }
    });
});
</script>

<style>
/* Custom styles for the analytics page */
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

.analytics-card {
    border-radius: 12px;
    box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.05);
    background-color: white;
    border: none;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.analytics-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
}

.chart-container {
    height: 300px;
    padding: 1rem;
}

.filter-section {
    border-radius: 12px;
    background-color: white;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

/* Stretched date picker styling */
.date-picker-container {
    width: 100%;
}

#daterange {
    height: 38px;
    font-size: 0.9rem;
}

/* Style the buttons inside the input group */
.date-picker-container .btn {
    padding: 0.375rem 1rem;
    font-size: 0.9rem;
}

.date-picker-container .btn:not(:last-child) {
    margin-right: 0.5rem;
}

.top-products-table th {
    font-weight: 600;
    color: #495057;
    border-top: none;
    padding: 1rem;
    background-color: var(--light);
}

.top-products-table td {
    padding: 1rem;
    vertical-align: middle;
}

.top-products-table tbody tr {
    transition: background-color 0.2s ease;
}

.top-products-table tbody tr:hover {
    background-color: rgba(5, 97, 252, 0.03);
}

.product-rank {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background-color: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    margin-right: 0.75rem;
}

.sales-percentage-badge {
    background-color: var(--primary-light);
    color: var(--primary);
    font-weight: 500;
    border-radius: 30px;
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
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

/* Print styles */
@media print {
    .sidebar-container, 
    .main-content > .d-flex:first-child, 
    .filter-section,
    .btn {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0;
        width: 100%;
        padding: 0;
    }
    
    .analytics-card {
        border: none;
        box-shadow: none;
    }
}
</style>

<?php include '../includes/footer.php'; ?>