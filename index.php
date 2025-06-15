<?php
require_once 'config/db_connection.php';
session_start();

// Check if a user is logged in
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;

// Redirect to appropriate dashboard if already logged in
if ($user) {
    switch($user['type']) {
        case 1: // Sales Manager
            header("Location: sales_manager/sm_index.php");
            break;
        case 2: // Bakery Staff
            header("Location: bakery_staff/bs_index.php");
            break;
        case 3: // Customer
            header("Location: customer/c_index.php");
            break;
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RotiSeri Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .landing-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            margin-right: 10px;
        }
        
        .company-text {
            display: flex;
        }
        
        .company-name {
            font-size: 32px;
            font-weight: 500;
        }
        
        .company-name2 {
            font-size: 32px;
            font-weight: 600;
            color: #0561FC;
        }
        
        .card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        
        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .card-img-top {
            height: 200px;
            object-fit: cover;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .card-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #0561FC;
        }
        
        .btn-primary {
            background-color: #0561FC;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: background-color 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
        }
        
        .footer {
            margin-top: 60px;
            text-align: center;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <div class="header">
            <div class="logo-container">
                <img src="assets/img/logo.png" class="logo-icon" alt="RotiSeri Bakery Logo">
                <div class="company-text">
                    <span class="company-name">RotiSeri</span>
                    <span class="company-name2">Bakery</span>
                </div>
            </div>
            <p class="lead">Delicious pastries since the 1980s</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <img src="assets/img/customer.png" class="card-img-top" alt="Customer">
                    <div class="card-body">
                        <h5 class="card-title">Customers</h5>
                        <p class="card-text">Order delicious baked goods online and have them delivered to your doorstep.</p>
                        <div class="d-grid gap-2">
                            <a href="auth/login.php" class="btn btn-primary">Login</a>
                            <a href="auth/register_customer.php" class="btn btn-outline-primary">Register</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100">
                    <img src="assets/img/bakerystaff.png" class="card-img-top" alt="Bakery Staff">
                    <div class="card-body">
                        <h5 class="card-title">Bakery Staff</h5>
                        <p class="card-text">Manage in-store orders, process online orders, and help customers with their needs.</p>
                        <div class="d-grid gap-2">
                            <a href="auth/login.php" class="btn btn-primary">Staff Login</a>
                            <a href="auth/register_staff.php" class="btn btn-outline-primary">Staff Registration</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100">
                    <img src="assets/img/manager.png" class="card-img-top" alt="Sales Manager">
                    <div class="card-body">
                        <h5 class="card-title">Sales Managers</h5>
                        <p class="card-text">Track sales, manage inventory, analyze data, and make informed business decisions.</p>
                        <div class="d-grid">
                            <a href="auth/login.php" class="btn btn-primary">Manager Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date("Y"); ?> RotiSeri Bakery. All Rights Reserved.</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>