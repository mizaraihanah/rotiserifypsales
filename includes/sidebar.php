<?php
// Get current page for active link indication
$current_page = basename($_SERVER['PHP_SELF']);

// Set user role title and navigation items based on user type
$user_role = '';
$nav_items = [];
$company_name2 = ''; // Initialize company_name2

if (isset($_SESSION['user'])) {
    switch ($_SESSION['user']['type']) {
        // Add this to the switch statement in sidebar.php
        case 0: // Administrator
            $user_role = 'Administrator';
            $company_name2 = 'Admin';
            $nav_items = [
                ['url' => 'a_index.php', 'icon' => 'bi-speedometer2', 'text' => 'Dashboard'],
                ['url' => 'a_user_management.php', 'icon' => 'bi-people', 'text' => 'User Management'],
            ];
            break;        case 1: 
            $user_role = 'Sales Manager';
            $company_name2 = 'Manager';
            $nav_items = [
                ['url' => 'sm_index.php', 'icon' => 'bi-graph-up', 'text' => 'Dashboard'],
                ['url' => 'sm_inventory.php', 'icon' => 'bi-box-seam', 'text' => 'Inventory'],
                ['url' => 'sm_materials.php', 'icon' => 'bi-list-task', 'text' => 'Raw Materials'],
                ['url' => 'sm_analytics.php', 'icon' => 'bi-graph-up-arrow', 'text' => 'Analytics'],
                ['url' => 'sm_sales.php', 'icon' => 'bi-cash-coin', 'text' => 'Sales'],
                ['url' => 'sm_promotions.php', 'icon' => 'bi-tag', 'text' => 'Promotions']
            ];
            break;        case 2: // Bakery Staff
            $user_role = 'Bakery Staff';
            $company_name2 = 'Staff';
            $nav_items = [
                ['url' => 'bs_index.php', 'icon' => 'bi-speedometer2', 'text' => 'Dashboard'],
                ['url' => 'bs_new_order.php', 'icon' => 'bi-cart-plus', 'text' => 'New Order'],
                ['url' => 'bs_orders.php', 'icon' => 'bi-check2-circle', 'text' => 'Manage Orders'],
                ['url' => 'bs_completed_orders.php', 'icon' => 'bi-bag-check', 'text' => 'Order History'],
                ['url' => 'bs_feedback.php', 'icon' => 'bi-chat-square-text', 'text' => 'Customer Feedback'],
                ['url' => 'bs_profile.php', 'icon' => 'bi-person', 'text' => 'My Profile']
            ];
            break;
        case 3: // Customer
            $user_role = 'Customer';
            $company_name2 = 'Customer';
            $nav_items = [
                ['url' => 'c_index.php', 'icon' => 'bi-speedometer2', 'text' => 'Dashboard'],
                ['url' => 'c_place_order.php', 'icon' => 'bi-cart-plus', 'text' => 'Place Order'],
                ['url' => 'c_orders.php', 'icon' => 'bi-bag', 'text' => 'My Orders'],
                ['url' => 'c_profile.php', 'icon' => 'bi-person', 'text' => 'Profile']
            ];
            break;
    }
}

// Determine the logout path based on user type
$logout_path = $_SESSION['user']['type'] == 1 ? '../auth/logout.php' : '../auth/logout.php';

// Determine logo path based on user type
$logo_path = $_SESSION['user']['type'] == 1 ? '../assets/img/logo.png' : '../assets/img/logo.png';
?>

<div class="sidebar-container">
    <div class="header-section">
        <div class="company-logo">
            <img src="<?php echo $logo_path; ?>" class="logo-icon" alt="Company Logo">
            <div class="company-text">
                <span class="company-name">RotiSeri</span>
                <span class="company-name2"><?php echo $company_name2; ?></span>
            </div>
        </div>

        <nav class="nav-container">
            <?php foreach ($nav_items as $item): ?>
                <a href="<?php echo $item['url']; ?>"
                    class="nav-item <?php echo ($current_page == basename($item['url'])) ? 'active' : ''; ?>">
                    <i class="bi <?php echo $item['icon']; ?> nav-icon"></i>
                    <div class="nav-text"><?php echo $item['text']; ?></div>
                </a>
            <?php endforeach; ?>

            <a href="<?php echo $logout_path; ?>" class="nav-item">
                <i class="bi bi-box-arrow-right nav-icon"></i>
                <div class="nav-text">Log Out</div>
            </a>
        </nav>
    </div>

    <div class="footer-section">
        <?php if (isset($_SESSION['user'])): ?>
            <div class="user-profile">
                <div class="mb-2"><?php echo sanitize_output($_SESSION['user']['fullname']); ?></div>
                <span class="badge bg-primary"><?php echo $user_role; ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>