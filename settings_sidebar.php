<?php
// Get current page to highlight active tab
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="premium-sidebar">
    <div class="premium-sidebar-title">System Configuration</div>
    <nav class="space-y-1">
        <a href="settings.php" class="premium-sidebar-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-sliders-h"></i>
            <span>General Setup</span>
        </a>
        
        <a href="rental_periods.php" class="premium-sidebar-link <?php echo $current_page === 'rental_periods.php' ? 'active' : ''; ?>">
            <i class="fas fa-hourglass-half"></i>
            <span>Rental Durations</span>
        </a>
        
        <a href="attributes.php" class="premium-sidebar-link <?php echo $current_page === 'attributes.php' ? 'active' : ''; ?>">
            <i class="fas fa-layer-group"></i>
            <span>Product Attributes</span>
        </a>
        
        <a href="users.php" class="premium-sidebar-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i>
            <span>Access Control</span>
        </a>
    </nav>
    
    <div class="mt-auto pt-8 border-t border-white/5">
        <div class="premium-sidebar-title">Navigation</div>
        <a href="dashboard.php" class="premium-sidebar-link">
            <i class="fas fa-chart-pie"></i>
            <span>Main Dashboard</span>
        </a>
        <a href="logout.php" class="premium-sidebar-link text-red-400/70 hover:text-red-400">
            <i class="fas fa-power-off"></i>
            <span>Exit Session</span>
        </a>
    </div>
</aside>
