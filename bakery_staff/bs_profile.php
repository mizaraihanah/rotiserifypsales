<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(2); // 2 for bakery staff (formerly clerk)
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

        // Check if email exists for other users
        $stmt = $conn->prepare("SELECT id FROM guest WHERE email = ? AND id != ? AND type = 2");
        $stmt->bind_param("si", $email, $_SESSION['user']['id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Email already exists for another staff member.");
        }

        // Verify current password if changing password
        if (!empty($current_password)) {
            $stmt = $conn->prepare("SELECT password, salt FROM guest WHERE id = ? AND type = 2");
            $stmt->bind_param("i", $_SESSION['user']['id']);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!password_verify($current_password . $user['salt'], $user['password'])) {
                throw new Exception("Current password is incorrect.");
            }

            // Validate new password
            if (empty($new_password)) {
                throw new Exception("New password cannot be empty.");
            }

            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match.");
            }

            if (strlen($new_password) < 8) {
                throw new Exception("Password must be at least 8 characters.");
            }

            // Generate new salt and hash for new password
            $salt = bin2hex(random_bytes(32));
            $hashed_password = password_hash($new_password . $salt, PASSWORD_DEFAULT);

            // Update staff with new password
            $stmt = $conn->prepare("UPDATE guest SET fullname = ?, contact = ?, address = ?, email = ?, password = ?, salt = ? WHERE id = ? AND type = 2");
            $stmt->bind_param("ssssssi", $fullname, $contact, $address, $email, $hashed_password, $salt, $_SESSION['user']['id']);
        } else {
            // Update staff without changing password
            $stmt = $conn->prepare("UPDATE guest SET fullname = ?, contact = ?, address = ?, email = ? WHERE id = ? AND type = 2");
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
$page_title = "My Profile - Bakery Staff";
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">My Profile</h1>
                <p class="text-muted">Update your account information</p>
            </div>
        </div>
        
        <div class="row">
            <!-- Profile Information -->
            <div class="col-xl-4 col-lg-5">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Profile Information</h6>
                    </div>
                    <div class="card-body text-center">
                        <div class="profile-avatar mb-3">
                            <div class="avatar-circle">
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
                        </div>
                        
                        <h5 class="mb-1"><?php echo htmlspecialchars($_SESSION['user']['fullname']); ?></h5>
                        <p class="text-muted mb-3">Bakery Staff</p>
                        
                        <div class="profile-info text-start p-3 border-top">
                            <div class="mb-3">
                                <label class="text-muted mb-1 small">Email Address</label>
                                <div><?php echo htmlspecialchars($_SESSION['user']['email']); ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted mb-1 small">Contact Number</label>
                                <div><?php echo htmlspecialchars($_SESSION['user']['contact']); ?></div>
                            </div>
                            <div class="mb-0">
                                <label class="text-muted mb-1 small">Employee ID</label>
                                <div><?php echo htmlspecialchars($_SESSION['user']['employee_id'] ?? 'Not assigned'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Quick Links</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <a href="bs_index.php" class="list-group-item list-group-item-action py-3">
                                <i class="bi bi-speedometer2 me-3 text-primary"></i> Dashboard
                            </a>
                            <a href="bs_new_order.php" class="list-group-item list-group-item-action py-3">
                                <i class="bi bi-cart-plus me-3 text-primary"></i> Create New Order
                            </a>
                            <a href="bs_orders.php" class="list-group-item list-group-item-action py-3">
                                <i class="bi bi-list-check me-3 text-primary"></i> Manage Pending Orders
                            </a>
                            <a href="bs_completed_orders.php" class="list-group-item list-group-item-action py-3">
                                <i class="bi bi-journal-check me-3 text-primary"></i> Order History
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Profile Edit Form -->
            <div class="col-xl-8 col-lg-7">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Edit Profile</h6>
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
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="fullname" 
                                           value="<?php echo htmlspecialchars($_SESSION['user']['fullname']); ?>" required>
                                    <div class="invalid-feedback">Please enter your full name.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($_SESSION['user']['email']); ?>" required>
                                    <div class="invalid-feedback">Please enter a valid email address.</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" name="contact" 
                                           value="<?php echo htmlspecialchars($_SESSION['user']['contact']); ?>" required>
                                    <div class="invalid-feedback">Please enter your contact number.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Employee ID</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['user']['employee_id'] ?? 'Not assigned'); ?>" readonly>
                                    <div class="form-text">Employee ID cannot be changed.</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3" required><?php echo htmlspecialchars($_SESSION['user']['address']); ?></textarea>
                                <div class="invalid-feedback">Please enter your address.</div>
                            </div>

                            <hr class="my-4">
                            <h5 class="mb-4">Change Password</h5>
                            <p class="text-muted mb-4">To change your password, please enter your current password and then the new password twice. If you don't want to change your password, leave these fields empty.</p>

                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Password must be at least 8 characters long.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <a href="bs_index.php" class="btn btn-outline-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Fetch all forms that need validation
    const forms = document.querySelectorAll('.needs-validation');
    
    // Loop over them and prevent submission
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const passwordInput = document.getElementById(targetId);
            
            // Toggle type attribute
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });
    });
});
</script>

<style>
/* Custom styles for the profile page */
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

.profile-info label {
    display: block;
    font-size: 0.8rem;
}

.profile-info div {
    font-weight: 500;
}

.border-left-primary {
    border-left: 4px solid #0561FC !important;
}

.list-group-item i {
    width: 20px;
    text-align: center;
}
</style>

<?php include '../includes/footer.php'; ?>