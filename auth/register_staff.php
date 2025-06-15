<?php
require_once '../config/db_connection.php';
require_once '../includes/functions/auth_functions.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and process the form data
    $fullname = htmlspecialchars(trim($_POST['fullname']));
    $contact = htmlspecialchars(trim($_POST['contact']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = htmlspecialchars(trim($_POST['password']));
    $confirm_password = htmlspecialchars(trim($_POST['confirm_password']));
    $address = htmlspecialchars(trim($_POST['address']));
    $role = htmlspecialchars(trim($_POST['role']));    // Generate employee ID based on role
    if ($role === 'Administrator') {
        $prefix = 'AD-';
    } elseif ($role === 'Sales Manager') {
        $prefix = 'SV-';
    } else {
        $prefix = 'BS-';
    }
    
    // Get the latest employee ID for the current prefix
    $stmt = $conn->prepare("SELECT employee_id FROM guest WHERE employee_id LIKE ? ORDER BY employee_id DESC LIMIT 1");
    $search_prefix = $prefix . '%';
    $stmt->bind_param("s", $search_prefix);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $last_id = $result->fetch_assoc()['employee_id'];
        $number = intval(substr($last_id, -4)) + 1;
    } else {
        $number = 1;
    }
    $employee_id = $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);

    // Check if passwords match
    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } 
    // Password validation
    else if (strlen($password) < 8 || 
        !preg_match("/[A-Z]/", $password) || 
        !preg_match("/[a-z]/", $password) || 
        !preg_match("/[0-9]/", $password)) {
        $error = "Password must contain at least 8 characters, one uppercase letter, one lowercase letter, and one number.";
    } else {
        // First check if email already exists
        if (email_exists($email, $conn)) {
            $error = "Email address already exists. Please use a different email.";
        } else {
            try {
                $conn->begin_transaction();
                
                // Register the staff member
                if (register_staff($fullname, $email, $password, $contact, $address, $role, $employee_id, $conn)) {
                    $conn->commit();
                    
                    // Redirect to login page
                    header("Location: login.php?registration=success");
                    exit();
                } else {
                    throw new Exception("Registration failed. Please try again.");
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Registration - RotiSeri Bakery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        
        .registration-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .registration-container h2 {
            color: #2c3e50;
            margin-bottom: 30px;
            font-weight: 600;
            position: relative;
            padding-bottom: 10px;
            text-align: center;
        }

        .registration-container h2:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: #0561FC;
            border-radius: 2px;
        }

        .form-label {
            font-weight: 500;
            color: #34495e;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #dde1e5;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #0561FC;
            box-shadow: 0 0 0 0.2rem rgba(5, 97, 252, 0.15);
        }

        .btn-primary {
            padding: 12px;
            font-weight: 500;
            border-radius: 8px;
            background: #0561FC;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .alert {
            border-radius: 8px;
            padding: 15px;
        }

        .mt-3 p {
            color: #666;
            text-align: center;
        }

        .mt-3 a {
            color: #0561FC;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .mt-3 a:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        #employee_id_field {
            display: none;
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
    
    <div class="container mt-5">
        <div class="registration-container">
            <h2 class="text-center">Staff Registration</h2>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="fullname" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="contact" class="form-label">Contact Number</label>
                    <input type="text" class="form-control" id="contact" name="contact" value="<?php echo isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>                <div class="mb-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="">Select Role</option>
                        <option value="Administrator" <?php echo (isset($_POST['role']) && $_POST['role'] === 'Administrator') ? 'selected' : ''; ?>>Administrator</option>
                        <option value="Sales Manager" <?php echo (isset($_POST['role']) && $_POST['role'] === 'Sales Manager') ? 'selected' : ''; ?>>Sales Manager</option>
                        <option value="Bakery Staff" <?php echo (isset($_POST['role']) && $_POST['role'] === 'Bakery Staff') ? 'selected' : ''; ?>>Bakery Staff</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-person-plus me-2"></i> Register
                </button>
            </form>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger mt-3"><?php echo $error; ?></div>
            <?php endif; ?>
            <div class="mt-3">
                <p><i class="bi bi-box-arrow-in-right"></i> Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>