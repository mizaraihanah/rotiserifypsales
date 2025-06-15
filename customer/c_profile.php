<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(3); // 3 for customer (formerly guest)
require_once '../config/db_connection.php';

// Initialize error and success messages
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    $fullname = htmlspecialchars(trim($_POST['fullname']), ENT_QUOTES, 'UTF-8');
    $contact = htmlspecialchars(trim($_POST['contact']), ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars(trim($_POST['address']), ENT_QUOTES, 'UTF-8');
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $current_password = $_POST['current_password'];
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    try {
        // Start transaction
        $conn->begin_transaction();

        // Verify current password for ANY profile update
        $stmt = $conn->prepare("SELECT password, salt FROM guest WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user']['id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!verify_password($current_password, $user['password'], $user['salt'])) {
            throw new Exception("Current password is incorrect.");
        }

        // Check if email exists for other users
        $stmt = $conn->prepare("SELECT id FROM guest WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $_SESSION['user']['id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Email already exists for another user.");
        }

        // Check if changing password
        if (!empty($new_password)) {
            // Validate new password
            if (empty($new_password)) {
                throw new Exception("New password cannot be empty.");
            }

            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match.");
            }

            if (strlen($new_password) < 8 || 
                !preg_match("/[A-Z]/", $new_password) || 
                !preg_match("/[a-z]/", $new_password) || 
                !preg_match("/[0-9]/", $new_password)) {
                throw new Exception("Password must be at least 8 characters and include uppercase, lowercase, and numbers.");
            }

            // Generate new salt and hash for new password
            $salt = bin2hex(random_bytes(32));
            $hashed_password = password_hash($new_password . $salt, PASSWORD_DEFAULT);

            // Update user with new password
            $stmt = $conn->prepare("UPDATE guest SET fullname = ?, contact = ?, address = ?, email = ?, password = ?, salt = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $fullname, $contact, $address, $email, $hashed_password, $salt, $_SESSION['user']['id']);
        } else {
            // Update user without changing password
            $stmt = $conn->prepare("UPDATE guest SET fullname = ?, contact = ?, address = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $fullname, $contact, $address, $email, $_SESSION['user']['id']);
        }

        if ($stmt->execute()) {
            // Update session data
            $_SESSION['user']['fullname'] = $fullname;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['contact'] = $contact;
            $_SESSION['user']['address'] = $address;

            $conn->commit();
            $success = "Profile updated successfully!";
        } else {
            throw new Exception("Error updating profile.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Set page title
$page_title = "My Profile - Customer";
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-md-8">
                    <h2 class="mb-0">My Profile</h2>
                    <p class="text-muted">View and update your account information</p>
                </div>
            </div>
                        
            <div class="row">
                <!-- Profile Summary Card -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-body text-center py-5">
                            <div class="avatar-circle mb-3">
                                <?php
                                // Generate initials from user's name
                                $initials = '';
                                $name_parts = explode(' ', $_SESSION['user']['fullname']);
                                foreach ($name_parts as $part) {
                                    if (!empty($part)) {
                                        $initials .= strtoupper(substr($part, 0, 1));
                                    }
                                }
                                echo $initials;
                                ?>
                            </div>
                            <h4 class="mb-1"><?php echo htmlspecialchars($_SESSION['user']['fullname']); ?></h4>
                            <p class="text-muted mb-3"><?php echo htmlspecialchars($_SESSION['user']['email']); ?></p>
                            
                            <div class="customer-info text-start mt-4">
                                <div class="info-item mb-3">
                                    <i class="bi bi-telephone me-2 text-primary"></i>
                                    <span><?php echo htmlspecialchars($_SESSION['user']['contact']); ?></span>
                                </div>
                                <div class="info-item">
                                    <i class="bi bi-geo-alt me-2 text-primary"></i>
                                    <span><?php echo nl2br(htmlspecialchars($_SESSION['user']['address'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light py-3">
                            <div class="row text-center">
                                <div class="col">
                                    <h5 class="mb-0" id="orderCount">-</h5>
                                    <div class="text-muted small">Total Orders</div>
                                </div>
                                <div class="col border-start">
                                    <h5 class="mb-0" id="totalSpent">-</h5>
                                    <div class="text-muted small">Total Spent</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <a href="c_place_order.php" class="list-group-item list-group-item-action py-3">
                                    <i class="bi bi-cart-plus me-2 text-primary"></i> Place New Order
                                </a>
                                <a href="c_orders.php" class="list-group-item list-group-item-action py-3">
                                    <i class="bi bi-bag me-2 text-primary"></i> View My Orders
                                </a>
                                <a href="c_index.php" class="list-group-item list-group-item-action py-3">
                                    <i class="bi bi-house me-2 text-primary"></i> Go to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Edit Form -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Edit Profile</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    <?php echo $success; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="fullname" 
                                           value="<?php echo htmlspecialchars($_SESSION['user']['fullname']); ?>" required>
                                    <div class="invalid-feedback">Please enter your full name.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($_SESSION['user']['email']); ?>" required>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" name="contact" 
                                           value="<?php echo htmlspecialchars($_SESSION['user']['contact']); ?>" required>
                                    <div class="invalid-feedback">Please enter your contact number.</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" rows="3" required><?php echo htmlspecialchars($_SESSION['user']['address']); ?></textarea>
                                    <div class="invalid-feedback">Please enter your address.</div>
                                </div>

                                <hr class="my-4">
                                <h5 class="mb-4">Change Password</h5>

                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="current_password" id="current_password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Required to make any changes to your profile</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="new_password" id="new_password">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-requirements small text-muted mt-2">
                                        Password must contain:
                                        <ul>
                                            <li>At least 8 characters</li>
                                            <li>At least one uppercase letter</li>
                                            <li>At least one lowercase letter</li>
                                            <li>At least one number</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="confirm_password" id="confirm_password">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="c_index.php" class="btn btn-outline-secondary me-md-2">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 100px;
    height: 100px;
    background-color: #0561FC;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 36px;
    font-weight: 600;
    margin: 0 auto;
}

.customer-info {
    padding: 0 10px;
}

.info-item {
    display: flex;
    align-items: flex-start;
}

.info-item i {
    margin-top: 3px;
}

.card {
    border-radius: 8px;
    border: none;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    margin-bottom: 1.5rem;
}

.card-header {
    background-color: transparent;
    border-bottom: 1px solid rgba(0,0,0,.125);
    padding: 1rem 1.25rem;
}

.list-group-item {
    padding: 1rem 1.25rem;
    border-left: none;
    border-right: none;
}

.list-group-item:first-child {
    border-top: none;
}

.list-group-item-action:hover {
    background-color: rgba(5, 97, 252, 0.05);
}

.toggle-password {
    cursor: pointer;
}

.password-requirements ul {
    padding-left: 20px;
    margin-top: 5px;
    margin-bottom: 0;
}

.alert {
    border-radius: 8px;
    animation: fadeIn 0.5s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password visibility toggle
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const passwordInput = document.getElementById(targetId);
            
            // Toggle type attribute
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });
    });
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Fetch order statistics
    fetchOrderStats();
});

// Fetch order statistics
function fetchOrderStats() {
    // In a real implementation, this would be an AJAX call to get data from the server
    // For now, we'll just use placeholder data
    
    // Get the user's order count and total spent from the session
    <?php
    // Fetch order stats for this customer
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_spent
        FROM orders
        WHERE guest_id = ?
    ");
    $stmt->bind_param("i", $_SESSION['user']['id']);
    $stmt->execute();
    $order_stats = $stmt->get_result()->fetch_assoc();
    ?>
    
    const orderCount = <?php echo $order_stats['total_orders'] ?: 0; ?>;
    const totalSpent = <?php echo $order_stats['total_spent'] ?: 0; ?>;
    
    // Update the UI
    document.getElementById('orderCount').textContent = orderCount;
    document.getElementById('totalSpent').textContent = 'RM' + totalSpent.toFixed(2);
}
</script>

<?php include '../includes/footer.php'; ?>