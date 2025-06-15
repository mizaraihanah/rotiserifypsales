<?php
require_once '../config/db_connection.php';
require_once '../includes/functions/auth_functions.php';

session_start();

// Redirect if already logged in
if (isset($_SESSION['user'])) {
    switch($_SESSION['user']['type']) {
        case 0: // Administrator
            header("Location: ../administrator/a_index.php");
            break;
        case 1: // Sales Manager
            header("Location: ../sales_manager/sm_index.php");
            break;
        case 2: // Bakery Staff
            header("Location: ../bakery_staff/bs_index.php");
            break;
        case 3: // Customer
            header("Location: ../customer/c_index.php");
            break;
    }
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
    }
    $_SESSION['last_attempt'] = time();
    $_SESSION['login_attempts']++;
    
    $user = authenticate_user($email, $password, $conn);
    
    if ($user) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        $_SESSION['created'] = time();
        
        // Add CSRF token
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
          switch($user['type']) {
            case 0:
                header("Location: ../administrator/a_index.php");
                break;
            case 1:
                header("Location: ../sales_manager/sm_index.php");
                break;
            case 2:
                header("Location: ../bakery_staff/bs_index.php");
                break;
            case 3:
                header("Location: ../customer/c_index.php");
                break;
            default:
                header("Location: ../index.php");
        }
        exit();
    }
    
    $error = "Invalid email or password.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RotiSeri Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
        }
        
        .company-text {
            display: inline-flex;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: 500;
        }
        
        .company-name2 {
            font-size: 24px;
            font-weight: 600;
            color: #0561FC;
        }
        
        .form-label {
            font-weight: 500;
            color: #495057;
        }
        
        .form-control {
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ced4da;
        }
        
        .form-control:focus {
            border-color: #0561FC;
            box-shadow: 0 0 0 0.25rem rgba(5, 97, 252, 0.25);
        }
        
        .btn-primary {
            background-color: #0561FC;
            border-color: #0561FC;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        
        .register-links {
            margin-top: 20px;
            text-align: center;
        }
        
        .register-links a {
            color: #0561FC;
            text-decoration: none;
            font-weight: 500;
        }
        
        .register-links a:hover {
            text-decoration: underline;
        }
        
        .back-link {
            position: absolute;
            top: 20px;
            left: 20px;
        }
        
        .back-link a {
            color: #6c757d;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .back-link a:hover {
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="back-link">
        <a href="../index.php"><i class="bi bi-arrow-left me-2"></i> Back to Home</a>
    </div>

    <div class="login-container">
        <div class="logo-container">
            <img src="../assets/img/logo.png" class="logo-icon" alt="RotiSeri Bakery Logo">
            <div class="company-text">
                <span class="company-name">RotiSeri</span>
                <span class="company-name2">Bakery</span>
            </div>
            <p class="text-muted mt-2">Welcome back</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        
        <div class="register-links">
            <p>Don't have an account?</p>
            <div class="d-flex justify-content-between">
                <a href="register_customer.php">Customer Registration</a>
                <a href="register_staff.php">Staff Registration</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>