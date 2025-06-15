<?php
// Get current page for active link indication
$current_page = basename($_SERVER['PHP_SELF']);
$current_view = isset($_GET['view']) ? $_GET['view'] : '';

// Determine the asset path based on current directory depth
$asset_path = strpos($current_page, 'sales_manager/') !== false || 
              strpos($current_page, 'bakery_staff/') !== false || 
              strpos($current_page, 'customer/') !== false ? 
              '../assets/' : 'assets/';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Roti Seri Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css" />
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="<?php echo $asset_path; ?>css/main.css">
    <?php if (isset($page_specific_css)): echo $page_specific_css; endif; ?>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar will be included here -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main content container -->
        <div class="dashboard-container">
            <!-- Content will be placed here -->
            <?php if (isset($dashboard_content)) echo $dashboard_content; ?>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="<?php echo $asset_path; ?>js/main.js"></script>
    <?php if (isset($page_specific_js)): echo $page_specific_js; endif; ?>
</body>
</html>