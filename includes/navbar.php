<!-- Simple Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>dashboard.php">
            <img src="<?php echo BASE_URL; ?>images/logo3.png" alt="FarmFelo Logo" height="40" class="d-inline-block align-text-top">
        </a>

        <!-- Navigation Links -->
        <div class="navbar-nav ms-auto">
            <!-- User Profile -->
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user"></i>
                    <?php echo htmlspecialchars($_SESSION['username']); ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>logout.php" onclick="return confirm('Do you want to logout for sure?')">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
