    </div>
    
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="success-toast" style="position: fixed; top: 20px; right: 20px; background-color: #10b981; color: white; padding: 12px 16px; border-radius: 6px; z-index: 1000;">
            <?php 
            echo $_SESSION['success_message'];
            unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="error-toast" style="position: fixed; top: 20px; right: 20px; background-color: #ef4444; color: white; padding: 12px 16px; border-radius: 6px; z-index: 1000;">
            <?php 
            echo $_SESSION['error_message'];
            unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>

    <script>
        // Auto-hide success/error messages after 5 seconds
        setTimeout(() => {
            const toasts = document.querySelectorAll('.success-toast, .error-toast');
            toasts.forEach(toast => {
                toast.style.transition = 'opacity 0.5s';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
