document.addEventListener('DOMContentLoaded', function() {
    // Function to initialize charts
    function initializeCharts() {
        // Check if chart elements exist
        const dailyCtx = document.getElementById('dailySalesChart');
        const weeklyCtx = document.getElementById('weeklySalesChart');
        const monthlyCtx = document.getElementById('monthlySalesChart');
        
        if (!dailyCtx && !weeklyCtx && !monthlyCtx) return;
        
        // Function to create charts
        function createSalesChart(ctx, labels, data, title) {
            return new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: title,
                        data: data,
                        borderColor: 'rgba(5, 97, 252, 1)',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(5, 97, 252, 1)',
                        pointBorderColor: 'rgba(5, 97, 252, 1)',
                        pointHoverBackgroundColor: 'rgba(5, 97, 252, 1)',
                        pointHoverBorderColor: 'white',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)',
                                drawBorder: false
                            },
                            title: {
                                display: false
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0,0,0,0.1)',
                                drawBorder: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: false
                        }
                    },
                    layout: {
                        padding: {
                            top: 10,
                            bottom: 5
                        }
                    }
                }
            });
        }
        
        // Initialize charts if data is available
        if (typeof salesData !== 'undefined') {
            if (dailyCtx) {
                createSalesChart(
                    dailyCtx.getContext('2d'),
                    salesData.daily.labels,
                    salesData.daily.data,
                    'Daily Sales Overview'
                );
            }
            
            if (weeklyCtx) {
                createSalesChart(
                    weeklyCtx.getContext('2d'),
                    salesData.weekly.labels,
                    salesData.weekly.data,
                    'Weekly Sales Overview'
                );
            }
            
            if (monthlyCtx) {
                createSalesChart(
                    monthlyCtx.getContext('2d'),
                    salesData.monthly.labels,
                    salesData.monthly.data,
                    'Monthly Sales Overview'
                );
            }
        }
        
        // Handle tab switching for charts
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function(tab) {
            tab.addEventListener('shown.bs.tab', function(e) {
                const target = this.getAttribute('data-bs-target');
                const tableId = target.replace('-chart', '-table');
                document.querySelectorAll('.sales-table').forEach(function(table) {
                    table.style.display = 'none';
                });
                if (document.querySelector(tableId)) {
                    document.querySelector(tableId).style.display = 'block';
                }
            });
        });
    }
    
    // Initialize charts
    initializeCharts();
});