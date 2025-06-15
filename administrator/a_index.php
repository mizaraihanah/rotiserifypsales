<?php
require_once '../includes/security.php';
secure_session_start();
check_user_type(0); // 0 for Administrator
require_once '../config/db_connection.php';

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch system statistics
// Total users count
$users_query = $conn->query("
    SELECT
        COUNT(*) as total_users,
        SUM(CASE WHEN type = 0 THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN type = 1 THEN 1 ELSE 0 END) as manager_count,
        SUM(CASE WHEN type = 2 THEN 1 ELSE 0 END) as staff_count,
        SUM(CASE WHEN type = 3 THEN 1 ELSE 0 END) as customer_count
    FROM guest
");
$users_stats = $users_query->fetch_assoc();

// Recent registrations
$recent_users_query = $conn->query("
    SELECT id, fullname, email, 
           CASE WHEN type IS NULL THEN -1 ELSE type END AS type, 
           date_created
    FROM guest
    WHERE type IN (0, 1, 2, 3) OR type IS NULL
    ORDER BY date_created DESC
    LIMIT 5
");
$recent_users = $recent_users_query->fetch_all(MYSQLI_ASSOC);

// Set page title
$page_title = "Dashboard - Administrator";
?>

<?php include '../includes/header.php'; ?>

<div class="wrapper">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Dashboard Header with Welcome Message -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800 fw-bold">Administrator Dashboard</h1>
                <p class="text-muted">Welcome back, <?php echo sanitize_output($_SESSION['user']['fullname']); ?>!</p>
            </div>
            <div class="text-md-end">
                <div class="mb-2 text-muted">
                    <i class="bi bi-calendar3"></i> <?php echo date('l, d F Y'); ?>
                </div>
            </div>
        </div>        <!-- User Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-20p col-md-6 mb-3">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="icon bg-primary-light text-primary rounded-circle p-3">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    Total Users
                                </div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $users_stats['total_users']; ?></div>
                                <a href="a_user_management.php" class="small text-primary mt-1 d-inline-block">
                                    Manage Users <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-20p col-md-6 mb-3">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="icon bg-danger-light text-danger rounded-circle p-3">
                                    <i class="bi bi-gear"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    Administrators
                                </div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $users_stats['admin_count']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-20p col-md-6 mb-3">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="icon bg-info-light text-info rounded-circle p-3">
                                    <i class="bi bi-person-workspace"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    Sales Managers
                                </div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $users_stats['manager_count']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-20p col-md-6 mb-3">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="icon bg-primary-light text-primary rounded-circle p-3">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    Staff Members
                                </div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $users_stats['staff_count']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-20p col-md-6 mb-3">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="icon bg-success-light text-success rounded-circle p-3">
                                    <i class="bi bi-person-plus"></i>
                                </div>
                            </div>
                            <div class="col">
                                <div class="text-xs font-weight-bold text-uppercase mb-1 text-muted">
                                    Customers
                                </div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $users_stats['customer_count']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>        </div>

        <!-- Recent Users -->
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent User Registrations</h5>
                        <a href="a_user_management.php" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Date Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_users as $user): ?>
                                        <tr>
                                            <td><?php echo sanitize_output($user['fullname']); ?></td>
                                            <td><?php echo sanitize_output($user['email']); ?></td>                                            <td>
                                                <?php 
                                                // Ensure type is properly cast as integer to prevent issues
                                                $type = isset($user['type']) ? (int)$user['type'] : -1;
                                                
                                                $badge_color = match($type) {
                                                    0 => 'bg-danger',
                                                    1 => 'bg-info',
                                                    2 => 'bg-primary',
                                                    3 => 'bg-success',
                                                    default => 'bg-secondary'
                                                };
                                                
                                                $role_name = match($type) {
                                                    0 => 'Administrator',
                                                    1 => 'Manager',
                                                    2 => 'Staff',
                                                    3 => 'Customer',
                                                    default => 'Unknown'
                                                };
                                                ?>
                                                <span class="badge <?php echo $badge_color; ?>">
                                                    <?php echo $role_name; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['date_created'])); ?></td>
                                            <td>                                                <a href="a_user_management.php?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </td>
                                        </tr>                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styles matching the design system */
.icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.col-xl-20p {
    width: 20%;
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

.table th {
    font-weight: 600;
    border-top: none;
}

.table-hover tbody tr:hover {
    background-color: rgba(5, 97, 252, 0.03);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dashboard initialization (placeholder for any future dashboard JavaScript)
});
</script>

<?php include '../includes/footer.php'; ?>