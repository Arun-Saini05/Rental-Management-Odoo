<?php
require_once '../config/database.php';
require_once '../config/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $role = sanitizeInput($_POST['role']);
    
    $db = new Database();
    $sql = "SELECT * FROM users WHERE email = ? AND role = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("ss", $email, $role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            
            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header('Location: ../admin/dashboard.php');
                    break;
                case 'vendor':
                    header('Location: ../vendor/dashboard.php');
                    break;
                default:
                    // All customers go to products.php as landing page
                    header('Location: ../products.php');
            }
            exit();
        } else {
            $error = 'Invalid password';
        }
    } else {
        $error = 'User not found or incorrect role';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Rentify Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .role-tab {
            transition: all 0.3s ease;
        }
        .role-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .role-icon {
            transition: all 0.3s ease;
        }
        .role-tab.active .role-icon {
            transform: scale(1.1);
        }
        .form-container {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .input-focus {
            transition: all 0.3s ease;
        }
        .input-focus:focus {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-purple-50 via-blue-50 to-indigo-100">
    <!-- Background Pattern -->
    <div class="fixed inset-0 opacity-10">
        <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%239C92AC" fill-opacity="0.4"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
    </div>

    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Logo/Brand -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center mb-4">
                    <img src="../assets/Logo.png" alt="Rentify Logo" class="h-20 w-auto">
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Rentify</h1>
                <p class="text-gray-600">Unified Login Portal</p>
            </div>

            <!-- Role Selection Tabs -->
            <div class="form-container rounded-2xl shadow-2xl p-8 mb-6">
                <div class="mb-8">
                    <h3 class="text-center text-lg font-semibold text-gray-700 mb-6">Select Your Role</h3>
                    <div class="grid grid-cols-3 gap-3">
                        <button onclick="selectRole('customer')" 
                                class="role-tab active p-4 rounded-xl text-center cursor-pointer border-2 border-purple-500" 
                                data-role="customer">
                            <div class="role-icon text-white mb-2">
                                <i class="fas fa-user text-2xl"></i>
                            </div>
                            <span class="text-white text-sm font-medium">Customer</span>
                        </button>
                        <button onclick="selectRole('vendor')" 
                                class="role-tab p-4 rounded-xl text-center cursor-pointer border-2 border-gray-200 bg-white" 
                                data-role="vendor">
                            <div class="role-icon text-gray-600 mb-2">
                                <i class="fas fa-store text-2xl"></i>
                            </div>
                            <span class="text-gray-700 text-sm font-medium">Vendor</span>
                        </button>
                        <button onclick="selectRole('admin')" 
                                class="role-tab p-4 rounded-xl text-center cursor-pointer border-2 border-gray-200 bg-white" 
                                data-role="admin">
                            <div class="role-icon text-gray-600 mb-2">
                                <i class="fas fa-shield-alt text-2xl"></i>
                            </div>
                            <span class="text-gray-700 text-sm font-medium">Admin</span>
                        </button>
                    </div>
                </div>

                <!-- Login Form -->
                <form method="POST" id="loginForm">
                    <input type="hidden" name="role" id="selectedRole" value="customer">
                    
                    <?php if ($error): ?>
                        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <div class="space-y-5">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-envelope mr-1 text-gray-400"></i>
                                Email Address
                            </label>
                            <input id="email" name="email" type="email" required
                                   placeholder="Enter your email"
                                   class="input-focus w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-lock mr-1 text-gray-400"></i>
                                Password
                            </label>
                            <div class="relative">
                                <input id="password" name="password" type="password" required
                                       placeholder="Enter your password"
                                       class="input-focus w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <button type="button" onclick="togglePassword()" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i id="passwordToggle" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mt-6">
                        <div class="flex items-center">
                            <input id="remember" name="remember" type="checkbox" 
                                   class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-gray-700">
                                Remember me
                            </label>
                        </div>

                        <div class="text-sm">
                            <a href="forgot-password.php" class="font-medium text-purple-600 hover:text-purple-500">
                                Forgot password?
                            </a>
                        </div>
                    </div>

                    <button type="submit" 
                            class="w-full mt-8 gradient-bg text-white py-3 px-4 rounded-lg font-medium hover:opacity-90 transition-opacity duration-200 flex items-center justify-center">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        <span id="loginButtonText">Sign in as Customer</span>
                    </button>
                </form>

                <!-- Sign Up Link -->
                <div class="text-center mt-6">
                    <p class="text-gray-600 text-sm">
                        Don't have an account? 
                        <a href="register.php" class="font-medium text-purple-600 hover:text-purple-500">
                            Sign up here
                        </a>
                    </p>
                </div>
            </div>

            <!-- Footer Info -->
            <div class="text-center text-gray-500 text-xs">
                <p>© 2026 Rentify. Secure login portal for all users.</p>
            </div>
        </div>
    </div>

    <script>
        function selectRole(role) {
            // Update hidden input
            document.getElementById('selectedRole').value = role;
            
            // Update tab styles
            document.querySelectorAll('.role-tab').forEach(tab => {
                tab.classList.remove('active');
                tab.classList.remove('border-purple-500');
                tab.classList.add('border-gray-200', 'bg-white');
                
                const icon = tab.querySelector('.role-icon');
                const text = tab.querySelector('span');
                icon.classList.remove('text-white');
                icon.classList.add('text-gray-600');
                text.classList.remove('text-white');
                text.classList.add('text-gray-700');
            });
            
            // Activate selected tab
            const selectedTab = document.querySelector(`[data-role="${role}"]`);
            selectedTab.classList.add('active');
            selectedTab.classList.remove('border-gray-200', 'bg-white');
            selectedTab.classList.add('border-purple-500');
            
            const selectedIcon = selectedTab.querySelector('.role-icon');
            const selectedText = selectedTab.querySelector('span');
            selectedIcon.classList.remove('text-gray-600');
            selectedIcon.classList.add('text-white');
            selectedText.classList.remove('text-gray-700');
            selectedText.classList.add('text-white');
            
            // Update button text
            const buttonTexts = {
                'customer': 'Sign in as Customer',
                'vendor': 'Sign in as Vendor',
                'admin': 'Sign in as Admin'
            };
            document.getElementById('loginButtonText').textContent = buttonTexts[role];
        }

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Initialize with customer role
        document.addEventListener('DOMContentLoaded', function() {
            selectRole('customer');
        });
    </script>
</body>
</html>
