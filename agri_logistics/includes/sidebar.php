<div class="sidebar">
    <div class="p-3">
        <h5 class="text-white mb-3">Dashboard</h5>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'dashboard') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <?php if ($_SESSION["role"] == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'locations') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>locations/index.php">
                    <i class="fas fa-fw fa-map-marker-alt"></i> Location Management
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_SESSION["role"] == 'admin' || $_SESSION["role"] == 'farm_manager'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'products') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>products/index.php">
                    <i class="fas fa-fw fa-leaf"></i> Product Management
                </a>
            </li>
            <?php endif; ?>
            <?php if ($_SESSION["role"] == 'admin' || $_SESSION["role"] == 'warehouse_manager' || $_SESSION["role"] == 'farm_manager'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'inventory') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>inventory/index.php">
                    <i class="fas fa-fw fa-boxes"></i> Inventory Management
                </a>
            </li>
            <?php endif; ?>

            <?php if ($_SESSION["role"] == 'admin' || $_SESSION["role"] == 'logistics_manager'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'vehicles') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>vehicles/index.php">
                    <i class="fas fa-fw fa-truck"></i> Vehicle Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'drivers') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>drivers/index.php">
                    <i class="fas fa-fw fa-id-card"></i> Driver Management
                </a>
            </li>
            <?php endif; ?>

            <?php if ($_SESSION["role"] == 'admin' || $_SESSION["role"] == 'logistics_manager' || $_SESSION["role"] == 'customer'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'orders') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>orders/index.php">
                    <i class="fas fa-fw fa-list-alt"></i> Orders
                </a>
            </li>
            <?php endif; ?>

            <?php if ($_SESSION["role"] == 'admin' || $_SESSION["role"] == 'logistics_manager' || $_SESSION["role"] == 'driver' || $_SESSION["role"] == 'customer'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'shipments') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>shipments/index.php">
                    <i class="fas fa-fw fa-route"></i> Shipment Overview
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($_SESSION["role"] == 'admin' || $_SESSION["role"] == 'logistics_manager' || $_SESSION["role"] == 'driver'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'driver_interface') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>driver_interface/update_tracking.php">
                    <i class="fas fa-fw fa-map-marked-alt"></i> Driver Tracking Input
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'driver_search') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>driver_interface/search.php">
                    <i class="fas fa-fw fa-search"></i> Search by Order ID
                </a>
            </li>
            <?php endif; ?>

            <?php if ($_SESSION["role"] == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'users') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>users/index.php">
                    <i class="fas fa-fw fa-users"></i> User Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'reports') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>reports/index.php">
                    <i class="fas fa-fw fa-chart-line"></i> Reports & Analytics
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</div>