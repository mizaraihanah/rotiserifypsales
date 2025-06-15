<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(0); // 0 for Administrator
require_once '../config/db_connection.php';

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$error = '';
$success = '';
$edit_user = null;

// Handle user deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    // Cannot delete self
    if ($user_id === (int)$_SESSION['user']['id']) {
        $error = "You cannot delete your own account.";
    } else {
        // Check if user has orders
        $check_stmt = $conn->prepare("SELECT COUNT(*) AS order_count FROM orders WHERE guest_id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $order_count = $result->fetch_assoc()['order_count'];
        
        if ($order_count > 0) {
            $error = "Cannot delete user: This user has existing orders in the system. Consider deactivating the account instead.";
        } else {
            // Delete user if no orders exist
            $stmt = $conn->prepare("DELETE FROM guest WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $success = "User deleted successfully.";
            } else {
                $error = "Error deleting user: " . $conn->error;
            }
        }
    }
}

// Handle user edit form display
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("SELECT * FROM guest WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
}

// Handle user create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    $fullname = htmlspecialchars(trim($_POST['fullname']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $contact = htmlspecialchars(trim($_POST['contact']));
    $address = htmlspecialchars(trim($_POST['address']));
    $type = (int)$_POST['type'];
    
    // For edit operation
    if (isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        
        // If changing password
        if (!empty($_POST['password'])) {
            $password = $_POST['password'];
            $salt = bin2hex(random_bytes(32));
            $hashed_password = password_hash($password . $salt, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("
                UPDATE guest SET 
                    fullname = ?, 
                    email = ?, 
                    contact = ?, 
                    address = ?, 
                    type = ?,
                    password = ?,
                    salt = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssssissi", $fullname, $email, $contact, $address, $type, $hashed_password, $salt, $user_id);
        } else {
            $stmt = $conn->prepare("
                UPDATE guest SET 
                    fullname = ?, 
                    email = ?, 
                    contact = ?, 
                    address = ?, 
                    type = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssssis", $fullname, $email, $contact, $address, $type, $user_id);
        }
          if ($stmt->execute()) {
            $success = "User updated successfully.";
            
            // If we're updating ourselves, update session data
            if ($user_id === (int)$_SESSION['user']['id']) {
                $_SESSION['user']['fullname'] = $fullname;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['contact'] = $contact;
                $_SESSION['user']['address'] = $address;
                $_SESSION['user']['type'] = $type;
            }
            
            // Add flag to indicate modal should be closed
            $_SESSION['close_modal'] = true;
        } else {
            $error = "Error updating user: " . $conn->error;
        }
    } 
    // For create operation
    else {
        $password = $_POST['password']; // Required for new user
        $salt = bin2hex(random_bytes(32));
        $hashed_password = password_hash($password . $salt, PASSWORD_DEFAULT);
        
        // Generate employee ID for staff and managers
        $employee_id = null;
        if ($type === 0) { // Administrator
            $prefix = 'AD-';
        } elseif ($type === 1) { // Sales Manager
            $prefix = 'SV-';
        } elseif ($type === 2) { // Bakery Staff
            $prefix = 'BS-';
        }
        
        if ($type !== 3) { // Customer doesn't need employee_id
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
        }
        
        $stmt = $conn->prepare("
            INSERT INTO guest (
                fullname, 
                contact, 
                address, 
                email, 
                password, 
                salt, 
                type, 
                date_created,
                employee_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->bind_param("ssssssis", $fullname, $contact, $address, $email, $hashed_password, $salt, $type, $employee_id);
        
        if ($stmt->execute()) {
            $success = "User created successfully.";
        } else {
            $error = "Error creating user: " . $conn->error;
        }
    }
}

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Search/filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';

// Build the query
$where_conditions = [];
$params = [];
$param_types = "";

if (!empty($search)) {
    $where_conditions[] = "(fullname LIKE ? OR email LIKE ? OR contact LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

if ($type_filter !== '') {
    $where_conditions[] = "type = ?";
    $params[] = (int)$type_filter;
    $param_types .= "i";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total records for pagination
$count_query = "SELECT COUNT(*) as total FROM guest $where_clause";
if (!empty($params)) {
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $total_records = $stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_records = $conn->query($count_query)->fetch_assoc()['total'];
}

$total_pages = ceil($total_records / $records_per_page);

// Fetch users
$query = "SELECT * FROM guest $where_clause ORDER BY date_created DESC LIMIT ?, ?";
$stmt = $conn->prepare($query);

// Add pagination parameters
$params[] = $offset;
$params[] = $records_per_page;
$param_types .= "ii";

$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set page title
$page_title = "User Management - Administrator";
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">User Management</h1>
                <p class="text-muted">Manage system users and their access levels</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus me-2"></i> Add New User
                </button>
            </div>
        </div>
        
        <!-- Alerts -->
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
        
        <!-- Search and Filter -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" class="form-control" placeholder="Search users..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">                        <select class="form-select" name="type">
                            <option value="">All User Types</option>
                            <option value="0" <?php echo $type_filter === '0' ? 'selected' : ''; ?>>Administrators</option>
                            <option value="1" <?php echo $type_filter === '1' ? 'selected' : ''; ?>>Sales Managers</option>
                            <option value="2" <?php echo $type_filter === '2' ? 'selected' : ''; ?>>Bakery Staff</option>
                            <option value="3" <?php echo $type_filter === '3' ? 'selected' : ''; ?>>Customers</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-1"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Users Table -->
        <div class="card shadow">
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people fs-1 text-muted"></i>
                        <h4 class="mt-3">No Users Found</h4>
                        <p class="text-muted">Try adjusting your search criteria or add a new user.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                    <th>Employee ID</th>
                                    <th>Role</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo sanitize_output($user['fullname']); ?></td>
                                    <td><?php echo sanitize_output($user['email']); ?></td>
                                    <td><?php echo sanitize_output($user['contact']); ?></td>
                                    <td><?php echo !empty($user['employee_id']) ? sanitize_output($user['employee_id']) : 'N/A'; ?></td>
                                    <td>
                                        <span class="badge <?php echo match($user['type']) {
                                            0 => 'bg-danger',
                                            1 => 'bg-info',
                                            2 => 'bg-primary',
                                            3 => 'bg-success',
                                            default => 'bg-secondary'
                                        }; ?>">
                                            <?php echo match($user['type']) {
                                                0 => 'Administrator',
                                                1 => 'Manager',
                                                2 => 'Staff',
                                                3 => 'Customer',
                                                default => 'Unknown'
                                            }; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['date_created'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ($user['id'] !== $_SESSION['user']['id']): ?>
                                                <?php 
                                                // Check if user has orders before allowing delete
                                                $check_stmt = $conn->prepare("SELECT COUNT(*) AS order_count FROM orders WHERE guest_id = ?");
                                                $check_stmt->bind_param("i", $user['id']);
                                                $check_stmt->execute();
                                                $has_orders = $check_stmt->get_result()->fetch_assoc()['order_count'] > 0;
                                                ?>
                                                
                                                <?php if ($user['type'] == 3 || $has_orders): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Cannot delete: Has associated orders" disabled>
                                                        <i class="bi bi-lock"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this user?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php 
                            // Determine which page numbers to show
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            // Show first page if not in range
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&type=' . urlencode($type_filter) . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            // Page numbers
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&type=' . urlencode($type_filter) . '">' . $i . '</a></li>';
                            }
                            
                            // Show last page if not in range
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&type=' . urlencode($type_filter) . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="fullname" required>
                        <div class="invalid-feedback">Please enter the full name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact" required>
                            <div class="invalid-feedback">Please enter a contact number.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">User Role</label>
                            <select class="form-select" name="type" required>
                                <option value="">Select Role</option>
                                <option value="0">Administrator</option>
                                <option value="1">Sales Manager</option>
                                <option value="2">Bakery Staff</option>
                            </select>
                            <div class="invalid-feedback">Please select a role.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="3" required></textarea>
                        <div class="invalid-feedback">Please enter an address.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                        <div class="form-text">Password must be at least 8 characters long.</div>
                        <div class="invalid-feedback">Please enter a password.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addUserForm" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i> Add User
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<?php if ($edit_user): ?>
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="fullname" value="<?php echo sanitize_output($edit_user['fullname']); ?>" required>
                        <div class="invalid-feedback">Please enter the full name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo sanitize_output($edit_user['email']); ?>" required>
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact" value="<?php echo sanitize_output($edit_user['contact']); ?>" required>
                            <div class="invalid-feedback">Please enter a contact number.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">User Role</label>
                            <select class="form-select" name="type" required>
                                <option value="">Select Role</option>                                <option value="0" <?php echo $edit_user['type'] == 0 ? 'selected' : ''; ?>>Administrator</option>
                                <option value="1" <?php echo $edit_user['type'] == 1 ? 'selected' : ''; ?>>Sales Manager</option>
                                <option value="2" <?php echo $edit_user['type'] == 2 ? 'selected' : ''; ?>>Bakery Staff</option>
                                <?php if ($edit_user['type'] == 3): ?>
                                <option value="3" selected>Customer</option>
                                <?php endif; ?>
                            </select>
                            <div class="invalid-feedback">Please select a role.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="3" required><?php echo sanitize_output($edit_user['address']); ?></textarea>
                        <div class="invalid-feedback">Please enter an address.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password">
                        <div class="form-text">Leave blank to keep current password.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editUserForm" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-show edit modal when in edit mode
document.addEventListener('DOMContentLoaded', function() {
    const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
    editModal.show();
});
</script>
<?php endif; ?>

<style>
/* Custom styles matching the design system */
.card {
    border: none;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.card-header {
    background-color: white;
    border-bottom: 1px solid #eaecf0;
}

.btn-group .btn {
    margin-right: 0.25rem;
}

.table th {
    font-weight: 600;
    border-top: none;
}

.table-hover tbody tr:hover {
    background-color: rgba(5, 97, 252, 0.03);
}

.page-link {
    color: #0561FC;
    border-color: #e0e0e0;
}

.page-item.active .page-link {
    background-color: #0561FC;
    border-color: #0561FC;
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

    <?php if (isset($_SESSION['close_modal']) && $_SESSION['close_modal']): ?>
    // Close the edit modal after successful update
    if (document.getElementById('editUserModal')) {
        window.location.href = 'a_user_management.php';
    }
    <?php 
    // Clear the flag after using it
    unset($_SESSION['close_modal']);
    endif; ?>
});
</script>

<?php include '../includes/footer.php'; ?>