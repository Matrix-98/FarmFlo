<?php
// Include activity notifications utility
require_once __DIR__ . '/../utils/activity_notifications.php';

// Check for new activities
$has_new_activities = false;
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $has_new_activities = hasNewActivities($_SESSION['user_id'], $_SESSION['role']);
}

// Check for pending registration requests (only for admin)
$has_pending_requests = false;
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    $has_pending_requests = hasPendingRegistrationRequests();
}
?>
<!-- Simple Sidebar -->
<div class="sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <img src="<?php echo BASE_URL; ?>images/logo3.png" alt="FarmFelo Logo" height="30" class="d-inline-block align-text-top mb-2">
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        <!-- Debug: Current page is: <?php echo isset($current_page) ? $current_page : 'NOT SET'; ?> -->
    </div>

    <!-- Navigation Menu -->
    <ul class="sidebar-nav">
        <!-- Dashboard Links - Always First -->
        <?php if ($_SESSION['role'] == 'admin'): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'dashboard') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard <?php echo (isset($current_page) && $current_page == 'dashboard') ? '(ACTIVE)' : ''; ?></span>
                <?php if ($has_new_activities): ?>
                    <span class="activity-dot"></span>
                <?php endif; ?>
            </a>
        </li>
        <?php elseif ($_SESSION['role'] == 'customer'): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'dashboard') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>customer_dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard <?php echo (isset($current_page) && $current_page == 'dashboard') ? '(ACTIVE)' : ''; ?></span>
                <?php if ($has_new_activities): ?>
                    <span class="activity-dot"></span>
                <?php endif; ?>
            </a>
        </li>
        <?php elseif ($_SESSION['role'] == 'driver'): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'dashboard') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>driver_dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard <?php echo (isset($current_page) && $current_page == 'dashboard') ? '(ACTIVE)' : ''; ?></span>
                <?php if ($has_new_activities): ?>
                    <span class="activity-dot"></span>
                <?php endif; ?>
            </a>
        </li>
        <?php elseif ($_SESSION['role'] == 'farm_manager'): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'dashboard') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>farm_manager_dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard <?php echo (isset($current_page) && $current_page == 'dashboard') ? '(ACTIVE)' : ''; ?></span>
                <?php if ($has_new_activities): ?>
                    <span class="activity-dot"></span>
                <?php endif; ?>
            </a>
        </li>
        <?php elseif ($_SESSION['role'] == 'warehouse_manager'): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'dashboard') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>warehouse_manager_dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard <?php echo (isset($current_page) && $current_page == 'dashboard') ? '(ACTIVE)' : ''; ?></span>
                <?php if ($has_new_activities): ?>
                    <span class="activity-dot"></span>
                <?php endif; ?>
            </a>
        </li>
        <?php elseif ($_SESSION['role'] == 'logistics_manager'): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'dashboard') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>logistics_manager_dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard <?php echo (isset($current_page) && $current_page == 'dashboard') ? '(ACTIVE)' : ''; ?></span>
                <?php if ($has_new_activities): ?>
                    <span class="activity-dot"></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endif; ?>

        <!-- Role-Specific Navigation -->
        <?php if ($_SESSION['role'] == 'customer'): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'orders') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>orders/">
                <i class="fas fa-shopping-cart"></i>
                <span>My Orders <?php echo (isset($current_page) && $current_page == 'orders') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'shipments') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>shipments/">
                <i class="fas fa-truck"></i>
                <span>My Shipments <?php echo (isset($current_page) && $current_page == 'shipments') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($_SESSION['role'] == 'driver'): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'shipments') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>shipments/">
                <i class="fas fa-truck"></i>
                <span>My Shipments <?php echo (isset($current_page) && $current_page == 'shipments') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'tracking_entry') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>driver/tracking_entry.php">
                <i class="fas fa-map-marker-alt"></i>
                <span>Update Tracking <?php echo (isset($current_page) && $current_page == 'tracking_entry') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($_SESSION['role'] == 'farm_manager'): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'farm_production') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>farm_production/">
                <i class="fas fa-leaf"></i>
                <span>Farm Production <?php echo (isset($current_page) && $current_page == 'farm_production') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'products') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>products/">
                <i class="fas fa-box"></i>
                <span>Products <?php echo (isset($current_page) && $current_page == 'products') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($_SESSION['role'] == 'warehouse_manager'): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'inventory') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>inventory/">
                <i class="fas fa-boxes"></i>
                <span>Inventory <?php echo (isset($current_page) && $current_page == 'inventory') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <?php endif; ?>

        <?php if ($_SESSION['role'] == 'logistics_manager'): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'orders') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>orders/">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders <?php echo (isset($current_page) && $current_page == 'orders') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'shipments') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>shipments/">
                <i class="fas fa-shipping-fast"></i>
                <span>Shipments <?php echo (isset($current_page) && $current_page == 'shipments') ? '(ACTIVE)' : ''; ?></span>
                <?php if (hasPendingShipmentRequests()): ?>
                    <span class="activity-dot"></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'shipment_requests') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>shipments/requested.php">
                <i class="fas fa-clipboard-list"></i>
                <span>Shipment Requests <?php echo (isset($current_page) && $current_page == 'shipment_requests') ? '(ACTIVE)' : ''; ?></span>
                <?php if (hasPendingShipmentRequests()): ?>
                    <span class="activity-dot"></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'drivers') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>drivers/">
                <i class="fas fa-users"></i>
                <span>Drivers <?php echo (isset($current_page) && $current_page == 'drivers') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'vehicles') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>vehicles/">
                <i class="fas fa-car"></i>
                <span>Vehicles <?php echo (isset($current_page) && $current_page == 'vehicles') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Admin Navigation -->
        <?php if ($_SESSION['role'] == 'admin'): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'inventory') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>inventory/">
                <i class="fas fa-boxes"></i>
                <span>Inventory <?php echo (isset($current_page) && $current_page == 'inventory') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'products') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>products/">
                <i class="fas fa-box"></i>
                <span>Products <?php echo (isset($current_page) && $current_page == 'products') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'farm_production') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>farm_production/">
                <i class="fas fa-leaf"></i>
                <span>Farm Production <?php echo (isset($current_page) && $current_page == 'farm_production') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'locations') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>locations/">
                <i class="fas fa-map-marker-alt"></i>
                <span>Locations <?php echo (isset($current_page) && $current_page == 'locations') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'orders') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>orders/">
                <i class="fas fa-shopping-cart"></i>
                <span>Orders <?php echo (isset($current_page) && $current_page == 'orders') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'shipments') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>shipments/">
                <i class="fas fa-shipping-fast"></i>
                <span>Shipments <?php echo (isset($current_page) && $current_page == 'shipments') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'drivers') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>drivers/">
                <i class="fas fa-users"></i>
                <span>Drivers <?php echo (isset($current_page) && $current_page == 'drivers') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'vehicles') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>vehicles/">
                <i class="fas fa-car"></i>
                <span>Vehicles <?php echo (isset($current_page) && $current_page == 'vehicles') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'users') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>users/">
                <i class="fas fa-users"></i>
                <span>Users <?php echo (isset($current_page) && $current_page == 'users') ? '(ACTIVE)' : ''; ?></span>
                <?php if ($has_pending_requests): ?>
                    <span class="activity-dot"></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'reports') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>reports/">
                <i class="fas fa-chart-bar"></i>
                <span>Reports <?php echo (isset($current_page) && $current_page == 'reports') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (isset($current_page) && $current_page == 'admin') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/">
                <i class="fas fa-cogs"></i>
                <span>Admin Tools <?php echo (isset($current_page) && $current_page == 'admin') ? '(ACTIVE)' : ''; ?></span>
            </a>
        </li>
        <?php endif; ?>









        <li class="nav-item">
            <a class="nav-link" href="<?php echo BASE_URL; ?>logout.php" onclick="return confirm('Do you want to logout for sure?')">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>
