<?php
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

if (!isCustomer()) {
    header('Location: ../index.php');
    exit();
}

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;

// Debug: Check if user is logged in
if (!$user_id) {
    die("Error: No user ID found in session. Please login first.");
}

// Debug: Show user ID
echo "<!-- Debug: User ID from session: $user_id -->";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);
    $city = sanitizeInput($_POST['city']);
    $state = sanitizeInput($_POST['state']);
    $postal_code = sanitizeInput($_POST['postal_code']);
    $company_name = sanitizeInput($_POST['company_name'] ?? '');
    $gstin = sanitizeInput($_POST['gstin'] ?? '');
    
    // Get current user data first to handle profile photo
    $current_user_sql = "SELECT profile_photo FROM users WHERE id = ?";
    $current_user_stmt = $db->prepare($current_user_sql);
    $current_user_stmt->bind_param("i", $user_id);
    $current_user_stmt->execute();
    $current_user = $current_user_stmt->get_result()->fetch_assoc();
    
    // Handle profile photo upload
    $profile_photo = $current_user['profile_photo'] ?? null; // Keep existing photo by default
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $file = $_FILES['profile_photo'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
            $upload_path = '../assets/profiles/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Delete old photo if exists
                if ($current_user['profile_photo'] && file_exists('../assets/profiles/' . $current_user['profile_photo'])) {
                    unlink('../assets/profiles/' . $current_user['profile_photo']);
                }
                $profile_photo = $filename;
            } else {
                $error_message = "Failed to upload profile photo.";
            }
        } else {
            $error_message = "Invalid file type or size. Please upload JPG, PNG, GIF, or WebP (max 5MB).";
        }
    }
    
    // Update user information
    $update_sql = "UPDATE users SET name = ?, phone = ?, address = ?, city = ?, state = ?, 
                   postal_code = ?, company_name = ?, gstin = ?, profile_photo = ?, updated_at = CURRENT_TIMESTAMP 
                   WHERE id = ?";
    $update_stmt = $db->prepare($update_sql);
    $update_stmt->bind_param("sssssssssi", $name, $phone, $address, $city, $state, 
                             $postal_code, $company_name, $gstin, $profile_photo, $user_id);
    
    if ($update_stmt->execute()) {
        $success_message = "Profile updated successfully!";
        // Update session name
        $_SESSION['user_name'] = $name;
    } else {
        $error_message = "Failed to update profile. Please try again.";
    }
}

// Get user details
$sql = "SELECT id, name, email, phone, company_name, gstin, address, city, state, postal_code, profile_photo FROM users WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Debug: Check if user was found
echo "<!-- Debug: Query returned " . ($result->num_rows) . " rows -->";
if ($user) {
    echo "<!-- Debug: User data: " . json_encode($user) . " -->";
} else {
    echo "<!-- Debug: No user found for ID: $user_id -->";
}

// If no user found, redirect
if (!$user) {
    die("Error: User not found in database for ID: $user_id");
}

// Set default values to prevent undefined array key errors
$user = array_merge([
    'name' => '',
    'email' => '',
    'phone' => '',
    'company_name' => '',
    'gstin' => '',
    'address' => '',
    'city' => '',
    'state' => '',
    'postal_code' => '',
    'profile_photo' => null
], $user);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Rental Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../includes/navbar.php'; ?>

    <!-- Page Header -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white">
        <div class="container mx-auto px-4 py-8">
            <h1 class="text-3xl font-bold">My Profile</h1>
            <p class="text-blue-100">Manage your personal information and preferences</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Profile Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6 border-b">
                        <h2 class="text-xl font-semibold">Personal Information</h2>
                    </div>
                    <div class="p-6">
                        <?php if (isset($success_message)): ?>
                            <div id="successMessage" class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                                <?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data" class="space-y-4">
                            <!-- Hidden file input for profile photo -->
                            <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/*" class="hidden" onchange="this.form.submit()">
                            
                            <div class="grid md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                                    <input type="text" name="name" value="<?php echo $user['name']; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                    <input type="email" value="<?php echo $user['email']; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" readonly>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                                <input type="tel" name="phone" value="<?php echo $user['phone']; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                <textarea name="address" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500"><?php echo $user['address'] ?? ''; ?></textarea>
                            </div>
                            <div class="grid md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">City</label>
                                    <input type="text" name="city" value="<?php echo $user['city'] ?? ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">State</label>
                                    <input type="text" name="state" value="<?php echo $user['state'] ?? ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Postal Code</label>
                                    <input type="text" name="postal_code" value="<?php echo $user['postal_code'] ?? ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                </div>
                            </div>
                            
                            <!-- Business Information Section -->
                            <div class="border-t pt-4">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-medium text-gray-900">Business Information</h3>
                                    <button type="button" onclick="toggleBusinessInfo()" class="text-blue-600 hover:text-blue-800 text-sm">
                                        <span id="toggleText">Show</span>
                                    </button>
                                </div>
                                <div id="businessInfo" class="space-y-4 hidden">
                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Company Name</label>
                                            <input type="text" name="company_name" value="<?php echo $user['company_name'] ?? ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">GSTIN</label>
                                            <input type="text" name="gstin" value="<?php echo $user['gstin'] ?? ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Profile Summary -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">Profile Summary</h3>
                    <div class="text-center">
                        <div class="relative inline-block">
                            <?php if ($user['profile_photo']): ?>
                                <img src="../assets/profiles/<?php echo $user['profile_photo']; ?>" 
                                     alt="Profile" class="w-24 h-24 object-cover rounded-full mx-auto mb-4 border-4 border-gray-200 cursor-pointer hover:border-blue-400 transition-colors"
                                     onclick="document.getElementById('profilePhotoInput').click()">
                            <?php else: ?>
                                <div class="w-24 h-24 bg-gray-200 rounded-full mx-auto mb-4 flex items-center justify-center cursor-pointer hover:bg-gray-300 transition-colors"
                                     onclick="document.getElementById('profilePhotoInput').click()">
                                    <i class="fas fa-user text-4xl text-gray-400"></i>
                                </div>
                            <?php endif; ?>
                            <div class="absolute bottom-2 right-0 bg-blue-600 text-white rounded-full p-1 cursor-pointer hover:bg-blue-700"
                                 onclick="document.getElementById('profilePhotoInput').click()">
                                <i class="fas fa-camera text-xs"></i>
                            </div>
                        </div>
                        
                        <h4 class="font-semibold text-lg"><?php echo $user['name']; ?></h4>
                        <p class="text-gray-600"><?php echo $user['email']; ?></p>
                        <p class="text-sm text-gray-500 mt-1">Member since <?php echo date('M Y', strtotime($user['created_at'] ?? 'now')); ?></p>
                        
                        <button type="button" onclick="document.getElementById('profilePhotoInput').click()" 
                                class="mt-3 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors">
                            <i class="fas fa-camera mr-2"></i>Upload Photo
                        </button>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <div class="space-y-3">
                        <a href="Dashboard.php" class="flex items-center p-3 border rounded-lg hover:bg-gray-50">
                            <i class="fas fa-tachometer-alt text-blue-600 mr-3"></i>
                            Dashboard
                        </a>
                        <a href="orders.php" class="flex items-center p-3 border rounded-lg hover:bg-gray-50">
                            <i class="fas fa-shopping-bag text-green-600 mr-3"></i>
                            My Orders
                        </a>
                        <a href="#" class="flex items-center p-3 border rounded-lg hover:bg-gray-50">
                            <i class="fas fa-cog text-purple-600 mr-3"></i>
                            Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide success message after 3 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                setTimeout(function() {
                    successMessage.style.transition = 'opacity 0.5s ease-out';
                    successMessage.style.opacity = '0';
                    setTimeout(function() {
                        successMessage.remove();
                    }, 500);
                }, 3000);
            }
        });
        
        function toggleBusinessInfo() {
            const businessInfo = document.getElementById('businessInfo');
            const toggleText = document.getElementById('toggleText');
            
            if (businessInfo.classList.contains('hidden')) {
                businessInfo.classList.remove('hidden');
                toggleText.textContent = 'Hide';
            } else {
                businessInfo.classList.add('hidden');
                toggleText.textContent = 'Show';
            }
        }
        
        // Show business info if there's data
        document.addEventListener('DOMContentLoaded', function() {
            const companyName = document.querySelector('input[value*="company"]');
            const gstin = document.querySelector('input[value*="gst"]');
            
            if (companyName && companyName.value && gstin && gstin.value) {
                toggleBusinessInfo();
            }
        });
    </script>
</body>
</html>
