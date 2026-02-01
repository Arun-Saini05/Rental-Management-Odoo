<?php
if (defined('HEADER_INCLUDED')) {
    return;
}
define('HEADER_INCLUDED', true);

include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Rentify'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/premium.css">
</head>
<body class="bg-primary">
    <div class="min-h-screen">
        <!-- Premium Header -->
        <header class="premium-header">
            <div class="premium-nav">
                <!-- Logo -->
                <a href="dashboard.php" class="premium-logo">
                    <img src="assets/logo_premium.png" alt="Rentify Logo" class="h-10 w-auto object-contain">
                    <span class="ml-3 font-black tracking-tighter text-white">Rentify <span class="text-[10px] font-normal text-muted ml-0.5">2.0</span></span>
                </a>
                
                <!-- Navigation -->
                <nav class="premium-nav-links">
                    <!-- Orders Dropdown -->
                    <div class="premium-dropdown">
                        <a href="#" class="premium-nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php' || basename($_SERVER['PHP_SELF']) == 'invoices.php' || basename($_SERVER['PHP_SELF']) == 'customers.php') ? 'active' : ''; ?>">
                            <i class="fas fa-shopping-cart"></i>Orders
                            <i class="fas fa-chevron-down ml-1 text-[10px]"></i>
                        </a>
                        <div class="premium-dropdown-content">
                            <a href="dashboard.php" class="premium-dropdown-item">
                                <i class="fas fa-list"></i>Orders List
                            </a>
                            <a href="invoices.php" class="premium-dropdown-item">
                                <i class="fas fa-file-invoice"></i>Invoices
                            </a>
                            <a href="customers.php" class="premium-dropdown-item">
                                <i class="fas fa-users"></i>Customers
                            </a>
                        </div>
                    </div>
                    
                    <a href="products.php" class="premium-nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'products.php') ? 'active' : ''; ?>">
                        <i class="fas fa-box"></i>Products
                    </a>
                    
                    <a href="reports.php" class="premium-nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'reports.php') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>Reports
                    </a>
                    
                    <a href="settings.php" class="premium-nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>Settings
                    </a>
                </nav>
                
                <!-- User Menu -->
                <div class="premium-user-menu">
                    <div class="premium-notification" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <span class="premium-notification-badge">3</span>
                    </div>
                    
                    <!-- Admin Profile Dropdown -->
                    <div class="premium-dropdown" id="user-dropdown">
                        <div class="premium-user-profile">
                            <div class="premium-avatar">
                                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)); ?>
                            </div>
                            <div class="premium-user-info hidden lg:flex">
                                <span class="premium-user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                                <span class="premium-user-role"><?php echo ucfirst($_SESSION['user_role'] ?? 'Admin'); ?></span>
                            </div>
                            <i class="fas fa-chevron-down text-[10px] ml-1 text-muted"></i>
                        </div>
                        
                        <div class="premium-dropdown-content">
                            <a href="settings.php" class="premium-dropdown-item">
                                <i class="fas fa-cog"></i>Settings
                            </a>
                            <a href="profile.php" class="premium-dropdown-item">
                                <i class="fas fa-user-circle"></i>My Profile
                            </a>
                            <div class="premium-dropdown-divider"></div>
                            <a href="logout.php" class="premium-dropdown-item text-red-400 hover:text-red-300">
                                <i class="fas fa-sign-out-alt"></i>Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

    <script>
        // Smooth scroll implementation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
             // Handle simple dropdown behavior if needed via CSS :hover
             // or add JS toggle logic if you prefer click-based dropdowns
        });
    </script>
