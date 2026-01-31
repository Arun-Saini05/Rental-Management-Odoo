<?php
require_once '../config/database.php';
require_once '../config/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = sanitizeInput($_POST['role']);
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Role-specific fields
    $company_name = sanitizeInput($_POST['company_name'] ?? '');
    $gstin = sanitizeInput($_POST['gstin'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $business_type = sanitizeInput($_POST['business_type'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($role === 'vendor' && (empty($company_name) || empty($business_type))) {
        $error = 'Please fill all vendor-specific fields';
    } else {
        $db = new Database();
        
        // Check if email already exists
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email already exists';
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, email, phone, company_name, gstin, address, role) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param("sssssss", $name, $email, $phone, $company_name, $gstin, $address, $role);
            
            if ($stmt->execute()) {
                $user_id = $db->getLastId();
                
                // Create role-specific record
                if ($role === 'customer') {
                    $customer_sql = "INSERT INTO customers (user_id) VALUES (?)";
                    $customer_stmt = $db->prepare($customer_sql);
                    $customer_stmt->bind_param("i", $user_id);
                    $customer_stmt->execute();
                } elseif ($role === 'vendor') {
                    $vendor_sql = "INSERT INTO vendors (user_id, business_type) VALUES (?, ?)";
                    $vendor_stmt = $db->prepare($vendor_sql);
                    $vendor_stmt->bind_param("is", $user_id, $business_type);
                    $vendor_stmt->execute();
                }
                
                $success = 'Registration successful! Please login.';
                header('refresh:2;url=login.php');
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Rentify Management System</title>
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
        .form-section {
            transition: all 0.3s ease;
        }
        .hidden-form {
            display: none;
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-purple-50 via-blue-50 to-indigo-100">
    <!-- Background Pattern -->
    <div class="fixed inset-0 opacity-10">
        <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%239C92AC" fill-opacity="0.4"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
    </div>

    <div class="relative min-h-screen flex items-center justify-center p-4 py-8">
        <div class="w-full max-w-2xl">
            <!-- Logo/Brand -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center mb-4">
                    <img src="../assets/Logo.png" alt="Rentify Logo" class="h-20 w-auto">
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Join Rentify</h1>
                <p class="text-gray-600">Create your account to get started</p>
            </div>

            <!-- Role Selection Tabs -->
            <div class="form-container rounded-2xl shadow-2xl p-8 mb-6">
                <div class="mb-8">
                    <h3 class="text-center text-lg font-semibold text-gray-700 mb-6">Select Your Account Type</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <button onclick="selectRole('customer')" 
                                class="role-tab active p-4 rounded-xl text-center cursor-pointer border-2 border-purple-500" 
                                data-role="customer">
                            <div class="role-icon text-white mb-2">
                                <i class="fas fa-user text-2xl"></i>
                            </div>
                            <span class="text-white text-sm font-medium">Customer</span>
                            <p class="text-white text-xs mt-1 opacity-90">Rent products</p>
                        </button>
                        <button onclick="selectRole('vendor')" 
                                class="role-tab p-4 rounded-xl text-center cursor-pointer border-2 border-gray-200 bg-white" 
                                data-role="vendor">
                            <div class="role-icon text-gray-600 mb-2">
                                <i class="fas fa-store text-2xl"></i>
                            </div>
                            <span class="text-gray-700 text-sm font-medium">Vendor</span>
                            <p class="text-gray-500 text-xs mt-1">List products</p>
                        </button>
                    </div>
                </div>

                <!-- Registration Form -->
                <form method="POST" id="registerForm">
                    <input type="hidden" name="role" id="selectedRole" value="customer">
                    
                    <!-- Debug Info -->
                    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-2 rounded-lg mb-4 text-sm">
                        <strong>Debug:</strong> Selected Role: <span id="debugRole">customer</span>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Common Fields -->
                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user mr-1 text-gray-400"></i>
                                Full Name *
                            </label>
                            <input id="name" name="name" type="text" required
                                   placeholder="Enter your full name"
                                   class="input-focus w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-envelope mr-1 text-gray-400"></i>
                                Email Address *
                            </label>
                            <input id="email" name="email" type="email" required
                                   placeholder="Enter your email"
                                   class="input-focus w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-phone mr-1 text-gray-400"></i>
                                Phone Number *
                            </label>
                            <input id="phone" name="phone" type="tel" required
                                   placeholder="Enter your phone number"
                                   class="input-focus w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map-marker-alt mr-1 text-gray-400"></i>
                                Address
                            </label>
                            <input id="address" name="address" type="text"
                                   placeholder="Enter your address"
                                   class="input-focus w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Customer Specific Fields -->
                    <div id="customerFields" class="form-section">
                        <div class="bg-blue-50 rounded-lg p-4 mb-6">
                            <h4 class="font-medium text-blue-900 mb-3 flex items-center">
                                <i class="fas fa-user-circle mr-2"></i>
                                Customer Information
                            </h4>
                            <p class="text-sm text-blue-700">Join as a customer to rent products from our marketplace</p>
                        </div>
                    </div>

                    <!-- Vendor Specific Fields -->
                    <div id="vendorFields" class="form-section hidden-form">
                        <div class="bg-orange-50 rounded-lg p-4 mb-6">
                            <h4 class="font-medium text-orange-900 mb-3 flex items-center">
                                <i class="fas fa-store mr-2"></i>
                                Business Information
                            </h4>
                            <p class="text-sm text-orange-700">Register as a vendor to list your rental products</p>
                        </div>
                        
                        <div class="grid md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="company_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-building mr-1 text-gray-400"></i>
                                    Company Name *
                                </label>
                                <input id="company_name" name="company_name" type="text"
                                       placeholder="Enter your company name"
                                       class="input-focus w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                            
                            <div>
                                <label for="business_type" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-briefcase mr-1 text-gray-400"></i>
                                    Business Type *
                                </label>
                                <select id="business_type" name="business_type" 
                                        class="input-focus w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                    <option value="">Select business type</option>
                                    <option value="electronics">Electronics</option>
                                    <option value="furniture">Furniture</option>
                                    <option value="vehicles">Vehicles</option>
                                    <option value="equipment">Equipment</option>
                                    <option value="clothing">Clothing</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label for="gstin" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-file-invoice mr-1 text-gray-400"></i>
                                GSTIN (For Invoicing)
                            </label>
                            <input id="gstin" name="gstin" type="text" maxlength="15"
                                   placeholder="e.g., 27AAAPL1234C1ZV"
                                   class="input-focus w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Password Fields -->
                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-lock mr-1 text-gray-400"></i>
                                Password *
                            </label>
                            <div class="relative">
                                <input id="password" name="password" type="password" required
                                       placeholder="Create a password"
                                       class="input-focus w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <button type="button" onclick="togglePassword('password')" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i id="passwordToggle" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-lock mr-1 text-gray-400"></i>
                                Confirm Password *
                            </label>
                            <div class="relative">
                                <input id="confirm_password" name="confirm_password" type="password" required
                                       placeholder="Confirm your password"
                                       class="input-focus w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <button type="button" onclick="togglePassword('confirm_password')" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i id="confirmPasswordToggle" class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="flex items-center mb-6">
                        <input id="agree" name="agree" type="checkbox" required
                               class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                        <label for="agree" class="ml-2 block text-sm text-gray-700">
                            I agree to the <a href="#" class="text-purple-600 hover:text-purple-500">Terms and Conditions</a> and <a href="#" class="text-purple-600 hover:text-purple-500">Privacy Policy</a>
                        </label>
                    </div>

                    <button type="submit" 
                            class="w-full gradient-bg text-white py-3 px-4 rounded-lg font-medium hover:opacity-90 transition-opacity duration-200 flex items-center justify-center">
                        <i class="fas fa-user-plus mr-2"></i>
                        <span id="registerButtonText">Create Customer Account</span>
                    </button>
                </form>

                <!-- Login Link -->
                <div class="text-center mt-6">
                    <p class="text-gray-600 text-sm">
                        Already have an account? 
                        <a href="login.php" class="font-medium text-purple-600 hover:text-purple-500">
                            Sign in here
                        </a>
                    </p>
                </div>
            </div>

            <!-- Footer Info -->
            <div class="text-center text-gray-500 text-xs">
                <p>© 2026 Rentify. Secure registration portal for customers and vendors.</p>
            </div>
        </div>
    </div>

    <script>
        function selectRole(role) {
            console.log('Selecting role:', role); // Debug log
            
            // Update hidden input
            document.getElementById('selectedRole').value = role;
            console.log('Hidden input updated to:', document.getElementById('selectedRole').value); // Debug log
            
            // Update debug display
            document.getElementById('debugRole').textContent = role;
            
            // Update tab styles
            document.querySelectorAll('.role-tab').forEach(tab => {
                tab.classList.remove('active');
                tab.classList.remove('border-purple-500');
                tab.classList.add('border-gray-200', 'bg-white');
                
                const icon = tab.querySelector('.role-icon');
                const text = tab.querySelector('span');
                const desc = tab.querySelector('p');
                icon.classList.remove('text-white');
                icon.classList.add('text-gray-600');
                text.classList.remove('text-white');
                text.classList.add('text-gray-700');
                desc.classList.remove('text-white', 'opacity-90');
                desc.classList.add('text-gray-500');
            });
            
            // Activate selected tab
            const selectedTab = document.querySelector(`[data-role="${role}"]`);
            selectedTab.classList.add('active');
            selectedTab.classList.remove('border-gray-200', 'bg-white');
            selectedTab.classList.add('border-purple-500');
            
            const selectedIcon = selectedTab.querySelector('.role-icon');
            const selectedText = selectedTab.querySelector('span');
            const selectedDesc = selectedTab.querySelector('p');
            selectedIcon.classList.remove('text-gray-600');
            selectedIcon.classList.add('text-white');
            selectedText.classList.remove('text-gray-700');
            selectedText.classList.add('text-white');
            selectedDesc.classList.remove('text-gray-500');
            selectedDesc.classList.add('text-white', 'opacity-90');
            
            // Show/hide role-specific fields
            const customerFields = document.getElementById('customerFields');
            const vendorFields = document.getElementById('vendorFields');
            
            if (role === 'customer') {
                customerFields.classList.remove('hidden-form');
                vendorFields.classList.add('hidden-form');
                document.getElementById('registerButtonText').textContent = 'Create Customer Account';
            } else if (role === 'vendor') {
                customerFields.classList.add('hidden-form');
                vendorFields.classList.remove('hidden-form');
                document.getElementById('registerButtonText').textContent = 'Create Vendor Account';
            }
        }

        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(fieldId + 'Toggle');
            
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
